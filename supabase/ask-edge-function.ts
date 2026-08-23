import "jsr:@supabase/functions-js/edge-runtime.d.ts";
import { createClient } from "jsr:@supabase/supabase-js@2";

// ask - natural-language analyst over the AIM platform.
// The model writes SELECTs, which run through ai.query(): a function owned by a
// nologin role that can read nine curated views and nothing else. That role, not
// the prompt, is the security boundary.

const db = createClient(Deno.env.get("SUPABASE_URL")!,
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!, { auth: { persistSession: false } });

const ANTHROPIC = "https://api.anthropic.com/v1/messages";
const MAX_STEPS = 8;
const REPORT_STEPS = 12;
const WALL_MS = 110_000;

const CORS = {
  "Access-Control-Allow-Origin": "*",
  "Access-Control-Allow-Methods": "POST, OPTIONS",
  "Access-Control-Allow-Headers": "content-type, x-aim-session",
  "Access-Control-Max-Age": "86400",
};
const json = (b: unknown, s = 200) => new Response(JSON.stringify(b),
  { status: s, headers: { ...CORS, "Content-Type": "application/json", "Cache-Control": "no-store" } });

// Secrets are named by hand in the dashboard, so accept the common variants
// rather than forcing one convention.
const KEY_NAMES: Record<string, string[]> = {
  anthropic:  ["ANTHROPIC_API_KEY", "Anthropic", "ANTHROPIC", "CLAUDE_API_KEY"],
  openai:     ["OPENAI_API_KEY", "OpenAI", "OPENAI"],
  perplexity: ["PERPLEXITY_API_KEY", "Perplexity", "PERPLEXITY"],
  gemini:     ["GEMINI_API_KEY", "Gemini", "GEMINI", "GOOGLE_API_KEY"],
};
function providerKey(p: string): string | undefined {
  for (const n of KEY_NAMES[p] ?? []) {
    // Pasted values often carry a newline, wrapping quotes, or the whole
    // NAME=value line rather than just the value.
    const v = (Deno.env.get(n) ?? "").trim()
      .replace(/^export\s+/i, "")
      .replace(/^[A-Za-z_][A-Za-z0-9_]*\s*=\s*/, "")
      .trim()
      .replace(/^["']|["']$/g, "");
    if (v) return v;
  }
  return undefined;
}
const EXPECT: Record<string, RegExp> = {
  anthropic: /^sk-ant-/, openai: /^sk-/, perplexity: /^pplx-/, gemini: /^AIza/,
};

/* -------------------------------------------------------------- session -- */

let keyCache: { key: CryptoKey; at: number } | null = null;
async function signingKey() {
  if (keyCache && Date.now() - keyCache.at < 300_000) return keyCache.key;
  const { data } = await db.from("internal_config").select("value")
    .eq("key", "dashboard_signing_key").single();
  if (!data?.value) throw new Error("dashboard_signing_key missing");
  const key = await crypto.subtle.importKey("raw", new TextEncoder().encode(data.value),
    { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  keyCache = { key, at: Date.now() };
  return key;
}

async function sign(v: string) {
  const raw = await crypto.subtle.sign("HMAC", await signingKey(), new TextEncoder().encode(v));
  return [...new Uint8Array(raw)].map((b) => b.toString(16).padStart(2, "0")).join("");
}

const ROLES = new Set(["admin", "client"]);

function timingSafeEqual(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let d = 0;
  for (let i = 0; i < a.length; i++) d |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return d === 0;
}

/**
 * Returns the role a token proves, or null. This has to accept exactly what the
 * dashboard function mints, which is now two shapes:
 *   exp.role.sig  - current, signature covers "exp.role"
 *   exp.sig       - issued before roles existed, signature covers "exp"
 *
 * Reading only the first two segments, as this function used to, silently broke
 * every current token: split(".") yields three parts, so the role was compared
 * against the signature and every call 401'd. The page treats a 401 here as a
 * dead session, so a working login was thrown away on each Generate summary.
 */
async function tokenRole(tok: string | null): Promise<string | null> {
  if (!tok) return null;
  const parts = tok.split(".");
  if (parts.length !== 2 && parts.length !== 3) return null;
  const exp = Number(parts[0]);
  if (!Number.isFinite(exp) || exp < Date.now()) return null;

  if (parts.length === 3) {
    const [e, role, sig] = parts;
    if (!ROLES.has(role)) return null;
    return timingSafeEqual(sig, await sign(`${e}.${role}`)) ? role : null;
  }
  const [e, sig] = parts;
  return timingSafeEqual(sig, await sign(e)) ? "admin" : null;
}

/* ----------------------------------------------------------------- tool -- */

const TOOL = {
  name: "run_sql",
  description:
    "Run one read-only SELECT against the ai.* views and get JSON rows back. " +
    "Only the ai schema is readable. Always filter by client_id when the user is " +
    "asking about one client, and keep date ranges narrow on the large views.",
  input_schema: {
    type: "object",
    properties: {
      query: { type: "string", description: "A single SELECT or WITH statement, no semicolon." },
      why: { type: "string", description: "One short line on what this is checking." },
    },
    required: ["query"],
  },
};

async function runSql(q: string) {
  const { data, error } = await db.rpc("ai_query_proxy", { p_sql: q, p_limit: 200 });
  if (error) return { error: error.message };
  return data;
}

// The report summary is a client-facing document, so it gets its own brief:
// longer than a chat answer, structured, and explicitly barred from inventing
// numbers or dressing up an absent data source as a zero.
const REPORT_ASK =
`Write the executive summary that opens this client's performance report for the
selected date range.

Query first. Look at the rankings trend, Search Console totals, website traffic,
AI visibility and form submissions for this client over the range, and check
ai.data_freshness so you do not describe a disconnected source as a decline.

Then return your answer as a single JSON object and nothing else - no prose
around it, no code fence:

{
  "summary": "2-4 sentences on what happened over the period, in plain English.",
  "points": [{"label": "Short bold lead-in", "text": "One sentence with the actual figures."}],
  "why": "2-3 sentences on what the numbers mean for this business, not a restatement.",
  "next": ["A concrete recommended action.", "Another."]
}

Rules:
- 4 to 6 points, each carrying real figures you just read from the database.
- 3 to 5 next steps. Each one names a specific thing to do, not \"improve SEO\".
- If a source is not connected, say so in a point rather than reporting zero.
- No opening pleasantries and no sign-off. This gets printed as-is.`;

/* -------------------------------------------------------------- handler -- */

Deno.serve(async (req: Request) => {
  if (req.method === "OPTIONS") return new Response(null, { status: 204, headers: CORS });
  if (req.method !== "POST") return json({ error: "method_not_allowed" }, 405);

  let body: any = {};
  try { body = await req.json(); } catch { /* empty */ }

  // Presence check only - never returns values.
  if (body.mode === "check") {
    const out: Record<string, unknown> = {};
    for (const p of Object.keys(KEY_NAMES)) {
      const name = KEY_NAMES[p].find((n) => !!Deno.env.get(n)) ?? null;
      const raw = name ? (Deno.env.get(name) ?? "") : "";
      const v = providerKey(p) ?? "";
      out[p] = name ? {
        secret_name: name,
        length: v.length,
        looks_right: EXPECT[p]?.test(v) ?? null,
        had_whitespace_or_quotes: raw !== v,
      } : false;
    }
    return json({ keys: out });
  }

  // Live-fire each provider with a minimal request. Returns status codes and a
  // short error snippet - never a key value.
  if (body.mode === "testkeys") {
    const probe: Record<string, unknown> = {};
    const snip = async (r: Response) => (await r.text()).replace(/\s+/g, " ").slice(0, 160);

    const ak = providerKey("anthropic");
    if (!ak) probe.anthropic = "no key found";
    else {
      const r = await fetch(ANTHROPIC, {
        method: "POST",
        headers: { "x-api-key": ak, "anthropic-version": "2023-06-01", "content-type": "application/json" },
        body: JSON.stringify({ model: "claude-sonnet-4-6", max_tokens: 1,
                               messages: [{ role: "user", content: "hi" }] }),
      });
      probe.anthropic = { status: r.status, ok: r.ok, detail: r.ok ? null : await snip(r) };
    }

    const ok2 = providerKey("openai");
    if (!ok2) probe.openai = "no key found";
    else {
      const r = await fetch("https://api.openai.com/v1/models",
        { headers: { Authorization: `Bearer ${ok2}` } });
      probe.openai = { status: r.status, ok: r.ok, detail: r.ok ? null : await snip(r) };
    }

    const pk = providerKey("perplexity");
    if (!pk) probe.perplexity = "no key found";
    else {
      const r = await fetch("https://api.perplexity.ai/chat/completions", {
        method: "POST",
        headers: { Authorization: `Bearer ${pk}`, "content-type": "application/json" },
        body: JSON.stringify({ model: "sonar", max_tokens: 1,
                               messages: [{ role: "user", content: "hi" }] }),
      });
      probe.perplexity = { status: r.status, ok: r.ok, detail: r.ok ? null : await snip(r) };
    }

    const gk = providerKey("gemini");
    if (!gk) probe.gemini = "no key found";
    else {
      const r = await fetch(
        "https://generativelanguage.googleapis.com/v1beta/models?key=" + encodeURIComponent(gk));
      probe.gemini = { status: r.status, ok: r.ok, detail: r.ok ? null : await snip(r) };
    }
    return json({ probe });
  }

  const role = await tokenRole(req.headers.get("x-aim-session"));
  if (!role) return json({ error: "unauthorized" }, 401);

  const apiKey = providerKey("anthropic");
  if (!apiKey) {
    return json({ error: "No Anthropic key found. Add a secret named ANTHROPIC_API_KEY or Anthropic." }, 500);
  }

  const isReport = body.mode === "report";
  const question = isReport ? REPORT_ASK : String(body.question ?? "").trim();
  if (!question) return json({ error: "question required" }, 400);

  const { data: schema } = await db.rpc("ai_schema");
  const { data: model } = await db.from("internal_config").select("value")
    .eq("key", "ask_model").maybeSingle();
  const MODEL = model?.value || "claude-sonnet-4-6";

  let ctx = "";
  if (body.client_id) {
    const { data: c } = await db.from("clients")
      .select("id,name,domain,onboarded_at").eq("id", Number(body.client_id)).maybeSingle();
    if (c) {
      ctx = `\nThe user is currently looking at ${c.name} (client_id ${c.id}, ${c.domain ?? "no domain"}` +
        `, engaged since ${c.onboarded_at ?? "unknown"}). Assume questions are about this client ` +
        `unless they name another.`;
    }
  }
  if (body.from && body.to) ctx += `\nThe selected date range is ${body.from} to ${body.to}.`;

  const system =
`You are the analyst for AIM's client analytics platform. You answer questions by querying
the database with the run_sql tool, then explaining what the numbers mean for a marketing
agency and its clients.

Today is ${new Date().toISOString().slice(0, 10)}.
${ctx}

These are the only readable views:

${schema}

How to work:
- Query before answering. Never state a figure you have not just read from the database.
- Prefer ai.search_console_daily over ai.search_console_queries unless the question is
  genuinely about individual queries or pages; the latter has millions of rows.
- If a first query returns something surprising, check it before reporting it. A metric that
  drops to zero usually means collection stopped, not that performance collapsed - the
  ai.data_freshness view tells you which sources are connected and when each last ran.
- Say plainly when data is missing or a source is not connected. Never present an absent
  source as a zero.
- Contact details for form submitters are deliberately not available. Do not try to fetch them.

${isReport ? `How to answer:
- You are writing a document a client will read. Follow the JSON shape you were given
  exactly, and return nothing outside it.
- Every figure must come from a query you just ran. Name the period the figures cover.
- Do not pad. A short summary with real numbers beats a long one without.` :
`How to answer:
- Lead with the answer, then the evidence. Keep it short and specific.
- Use exact numbers and name the date range they cover.
- Where the answer suggests an action, say what you would do and why. Be concrete.
- If the question cannot be answered from this data, say so and say what would be needed.`}`;

  const messages: any[] = [{ role: "user", content: question }];
  const sqlUsed: { query: string; why?: string; rows?: number; error?: string }[] = [];
  const deadline = Date.now() + WALL_MS;

  try {
    const steps = isReport ? REPORT_STEPS : MAX_STEPS;
    for (let step = 0; step < steps; step++) {
      if (Date.now() > deadline) break;

      const res = await fetch(ANTHROPIC, {
        method: "POST",
        headers: {
          "x-api-key": apiKey,
          "anthropic-version": "2023-06-01",
          "content-type": "application/json",
        },
        body: JSON.stringify({
          model: MODEL, max_tokens: isReport ? 3000 : 2000, system, tools: [TOOL], messages,
        }),
      });

      if (!res.ok) {
        const t = await res.text();
        return json({ error: `anthropic ${res.status}`, detail: t.slice(0, 400), model: MODEL }, 502);
      }
      const out = await res.json();
      messages.push({ role: "assistant", content: out.content });

      const calls = (out.content ?? []).filter((c: any) => c.type === "tool_use");
      if (!calls.length) {
        const text = (out.content ?? []).filter((c: any) => c.type === "text")
          .map((c: any) => c.text).join("\n").trim();
        if (isReport) {
          // Models sometimes wrap JSON in a fence despite being asked not to.
          const m = text.match(/\{[\s\S]*\}/);
          let report: any;
          try {
            report = JSON.parse(m ? m[0] : text);
          } catch {
            return json({ error: "summary_not_json", raw: text.slice(0, 1500) }, 502);
          }

          // Persist the report so it survives a reload and so periods can be
          // compared later. report_summaries is unique on
          // (client_id, period_from, period_to), so regenerating a period
          // replaces that period's row rather than accumulating duplicates.
          //
          // A failed write must not discard a report the user waited a minute
          // for, so the outcome is reported back instead of thrown, and the
          // page says whether what it is showing was stored.
          let saved = false;
          let saveError: string | null = null;
          const cid = Number(body.client_id);
          if (Number.isFinite(cid) && cid > 0 && body.from && body.to) {
            const { error } = await db.from("report_summaries").upsert({
              client_id: cid,
              period_from: String(body.from),
              period_to: String(body.to),
              summary: report.summary ?? null,
              points: report.points ?? [],
              why: report.why ?? null,
              next_steps: report.next ?? report.next_steps ?? [],
              model: MODEL,
              queries_run: Math.min(sqlUsed.length, 32767),
              generated_at: new Date().toISOString(),
            }, { onConflict: "client_id,period_from,period_to" });
            if (error) saveError = error.message;
            else saved = true;
          } else {
            saveError = "no client or date range was supplied, so there was nothing to key the summary on";
          }

          return json({ report, sql: sqlUsed, model: MODEL, steps: step + 1,
                        saved, save_error: saveError });
        }
        return json({ answer: text, sql: sqlUsed, model: MODEL, steps: step + 1 });
      }

      const results: any[] = [];
      for (const call of calls) {
        const q = String(call.input?.query ?? "");
        const r = await runSql(q);
        sqlUsed.push({
          query: q, why: call.input?.why,
          rows: r?.row_count, error: r?.error,
        });
        results.push({
          type: "tool_result", tool_use_id: call.id,
          content: JSON.stringify(r).slice(0, 60000),
          is_error: !!r?.error,
        });
      }
      messages.push({ role: "user", content: results });
    }
    return json({ answer: "I ran out of steps before reaching an answer. Try narrowing the question.",
                  sql: sqlUsed, model: MODEL }, 200);
  } catch (e) {
    return json({ error: String((e as Error).message ?? e) }, 500);
  }
});
