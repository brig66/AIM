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
// A panel reading looks at one thing, so it gets a tighter budget than the whole
// report: fewer queries, a shorter answer, and a cost per click the agency can
// afford to have pressed on every panel of every client.
const PANEL_STEPS = 6;
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

// Kept in step with the dashboard function's copy. These two have drifted before -
// ask carried on accepting only the old two-part token after dashboard started
// minting three-part ones - and a role missing here 401s every summary for that
// user while the rest of their session works fine.
const ROLES = new Set(["admin", "staff", "client"]);

function timingSafeEqual(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let d = 0;
  for (let i = 0; i < a.length; i++) d |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return d === 0;
}

/**
 * Returns what a token proves, or null. This has to accept exactly what the
 * dashboard function mints, which is now three shapes:
 *   exp.role.client.sig - current; signature covers "exp.role.client"
 *   exp.role.sig        - before client scoping
 *   exp.sig             - issued before roles existed, signature covers "exp"
 *
 * Keeping this in step with dashboard/index.ts matters more than it looks: when
 * dashboard started minting a longer token and this did not follow, every summary
 * 401'd against a session that was working perfectly everywhere else.
 *
 * Reading only the first two segments, as this function used to, silently broke
 * every current token: split(".") yields three parts, so the role was compared
 * against the signature and every call 401'd. The page treats a 401 here as a
 * dead session, so a working login was thrown away on each Generate summary.
 */
async function tokenAuth(tok: string | null): Promise<{ role: string; clientId: number | null } | null> {
  if (!tok) return null;
  const parts = tok.split(".");
  if (parts.length < 2 || parts.length > 4) return null;
  const exp = Number(parts[0]);
  if (!Number.isFinite(exp) || exp < Date.now()) return null;

  if (parts.length === 4) {
    const [e, role, cli, sig] = parts;
    if (!ROLES.has(role)) return null;
    if (!timingSafeEqual(sig, await sign(`${e}.${role}.${cli}`))) return null;
    let clientId: number | null = null;
    if (cli !== "-") {
      clientId = Number(cli);
      if (!Number.isInteger(clientId) || clientId <= 0) return null;
    }
    if (role === "client" && clientId == null) return null;
    return { role, clientId };
  }
  if (parts.length === 3) {
    const [e, role, sig] = parts;
    if (!ROLES.has(role)) return null;
    return timingSafeEqual(sig, await sign(`${e}.${role}`)) ? { role, clientId: null } : null;
  }
  const [e, sig] = parts;
  return timingSafeEqual(sig, await sign(e)) ? { role: "admin", clientId: null } : null;
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

/* ------------------------------------------------------ report guardrails --
 * This summary is the first thing a paying client reads, and it has two ways to
 * go wrong, pulling in opposite directions.
 *
 * The first is language that reads like a post-mortem. A truthful set of figures
 * wrapped in "freefall", "collapsed" and "largely invisible" reads as a verdict on
 * the client's business rather than a report on their data, and it costs the
 * account without changing a single number.
 *
 * The second is the cure for the first. A model told to sound positive will reach
 * for a reassuring cause, and the most tempting one is a named Google update that
 * may not exist. A client who checks that claim and finds nothing loses far more
 * confidence than blunt wording ever cost. So the rules below soften the framing
 * and tighten the evidence at the same time; neither half works alone.
 *
 * Nothing here permits a figure to be dropped, rounded kindly, or hidden behind a
 * flattering comparison window. A decline is still reported as a decline.
 */
const TONE_RULES =
`How this has to read:

This is a document a paying client reads about their own business. Report on the
data, do not pass verdict on the company. Every figure stays exactly as the
database gave it - the framing is what is being controlled here, never the facts.

- Plain direction words only: fell, rose, held, slowed, recovered. Never
  catastrophising ones. These are banned outright: freefall, collapse, plummet,
  nosedive, crater, haemorrhage, decimated, disaster, catastrophic, dire,
  alarming, grim, bleak, crisis, invisible, wiped out, effectively stalled,
  pipeline risk, material and growing.
- Never tell a client their business is failing, that they are invisible to
  buyers, or that their pipeline is at risk. State what the metric did, over what
  window, and what is being done about it.
- Every decline you report must be paired with the specific action that answers
  it - either in the same sentence or in a matching entry in "next".
- Say what held steady or improved as well. Do not manufacture a positive, but do
  not report only the losses when the data shows otherwise.

Explaining a change, without inventing one:

- Never assert an external cause as established fact. No named algorithm update,
  no seasonality claim, no competitor action, unless it is visible in the data you
  queried or was given to you.
- When a movement is sharp, lands on a single date, and spans many keywords at
  once, that shape is consistent with an external ranking update OR with a
  site-side change such as a deployment, a robots or indexing change, or a
  migration. Say both are candidates and that the next step is to establish
  which. Do not pick one to sound reassuring.
- When a movement is gradual and uneven, say so - that pattern points to content
  and authority rather than a single event.
- Phrase every hypothesis as a hypothesis: "the shape is consistent with",
  "worth ruling out first". Never "this was caused by".
- If the data cannot distinguish between causes, say that plainly. An honest
  "we are isolating which of two things happened" reads as competence. A
  confident wrong cause does not.`;

// The report summary is a client-facing document, so it gets its own brief:
// longer than a chat answer, structured, and explicitly barred from inventing
// numbers or dressing up an absent data source as a zero.
const REPORT_ASK =
`Write the executive summary that opens this client's performance report for the
selected date range.

Query first. Look at the rankings trend, Search Console totals, website traffic,
AI visibility and form submissions for this client over the range, and check
ai.data_freshness so you do not describe a disconnected source as a decline.

When something moved sharply, query around the date it moved before you write
about it - how many keywords moved, on which engines, and whether it happened in
one tracker pass or over weeks. The shape of the movement is what lets you
describe a likely driver honestly instead of guessing at one.

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
- "summary" opens with what changed over the period, not with a judgement on it.
- "why" explains what the movement means commercially and what is being done -
  it is not the place for a warning.
- No opening pleasantries and no sign-off. This gets printed as-is.

${TONE_RULES}`;

// Prompt rules drift, so the same list is enforced mechanically on the way out.
// A trip sends the draft back once for a rewrite rather than failing the request:
// the user has already waited a minute, and the figures in hand are correct - it
// is only the wording that needs another pass.
const TONE_BAN =
  /\b(freefall|free[- ]falls?|collaps\w+|plummet\w*|nose[- ]?dives?|cratered?|h(?:a)?emorrhag\w*|decimat\w+|disaster\w*|catastroph\w+|dire|alarming|grim|bleak|crisis|invisible|wiped out|effectively stalled|pipeline risk|material and growing)\b/i;

const TONE_FIX =
`That draft uses language this report cannot carry. Rewrite it and return the same
JSON object with every figure unchanged - do not soften, drop or re-window a single
number.

Fix only the framing:
- Replace any catastrophising word with a plain direction word.
- Remove any statement that the business is failing, invisible to buyers, or at
  risk. Say what the metric did and what is being done about it instead.
- Make sure each decline is paired with the action that answers it.
- Keep every cause phrased as a candidate to be ruled out, never as established
  fact, and do not name an algorithm update.

Return the JSON object only.`;

/* ------------------------------------------------------------- panel mode --
 * Per-panel analysis: the same analyst, pointed at one panel instead of the whole
 * account, and handed a benchmark block it is not allowed to go outside.
 *
 * WHY THE BENCHMARKS ARE NOT LEFT TO THE MODEL
 *
 * The obvious way to build "compare this to the industry average" is to ask the
 * model for the industry average. It will answer, fluently, with a number that has
 * no source. That number then goes into a client-facing panel, and the client - who
 * knows their own industry - checks it. Being caught inventing a benchmark costs
 * more than never having offered one.
 *
 * So panel_benchmark_context() builds the comparison in SQL from two things only:
 * medians over AIM's own book, labelled with n, and published figures that carry a
 * citation. The model is handed that block as fact and told, in the strongest terms
 * the prompt can manage, that a benchmark outside it does not exist. The rule is
 * worth stating because it is also checkable - see BENCH_BAN below.
 */

const PANELS: Record<string, { label: string; views: string; focus: string }> = {
  search: {
    label: "Search Console",
    views: "ai.search_console_daily, and ai.search_console_queries only if you need to name specific queries or pages",
    focus:
      "What happened to clicks and impressions, and whether CTR and average position moved with them or against them. " +
      "Rising impressions with falling CTR is usually reach into broader queries rather than a problem - say so rather than flagging it.",
  },
  rankings: {
    label: "Keyword rankings",
    views: "ai.rankings_daily and ai.tracked_keywords",
    focus:
      "How the tracked set moved: how many keywords are in the top 3 and on page 1, and whether any movement was gradual or landed on one date. " +
      "A sharp single-date move across many keywords is worth calling out as needing a cause established, not assigned.",
  },
  traffic: {
    label: "Website traffic",
    views: "ai.traffic, and ai.events if the question reaches conversions",
    focus:
      "Sessions by channel and whether engagement held as volume changed. Name the channel that actually moved rather than reporting the total.",
  },
  ads: {
    label: "Paid media",
    views: "ai.ppc_daily",
    focus:
      "Spend efficiency: CTR, cost per click, conversion rate and above all cost per conversion. " +
      "Filter to level = 'campaign' or you will count the same spend several times. Name the campaign carrying the result, not just the account total.",
  },
  social: {
    label: "Organic social",
    views: "ai.social_daily and ai.social_posts",
    focus:
      "Engagement rate and follower movement per platform, and which specific posts did the work. " +
      "Do not blend platforms into one engagement rate without saying you have, and remember Facebook reach is no longer reported at all.",
  },
  experience: {
    label: "Site experience",
    views: "ai.pagespeed, ai.clarity_daily and ai.clarity_pages",
    focus:
      "Whether real users are hitting a slow or awkward site: field Core Web Vitals first, lab scores second, then the friction signals. " +
      "Where friction is high, use ai.clarity_pages to name the page causing it. That is the difference between a score and an action.",
  },
  gbp: {
    label: "Business Profile",
    views: "ai.gbp_daily and ai.gbp_reviews",
    focus:
      "Calls, direction requests and website clicks - the actions, not the views. " +
      "Check for unanswered negative reviews; an unreplied one-star review is a concrete thing to fix this week.",
  },
  forms: {
    label: "Form submissions",
    views: "ai.form_submissions",
    focus:
      "Volume and quality of enquiries. Submissions classified needs_review are not yet judged, so do not count them against the client as non-leads. " +
      "Check delivery: a lead that was captured but not delivered is the most urgent thing this panel can show.",
  },
  backlinks: {
    label: "Backlinks",
    views: "ai.backlinks",
    focus:
      "Referring domains rather than total backlinks, and net new against lost. Sampling is weekly at best, so check distinct days before calling anything a trend.",
  },
  ai: {
    label: "AI visibility",
    views: "ai.visibility_summary, ai.visibility_runs and ai.visibility_citations",
    focus:
      "Whether the brand is mentioned and cited when AI engines answer the tracked prompts, and who is being cited instead. " +
      "Name the competitor taking the citations where the data shows one.",
  },
};

// Stated once, in the strongest form the prompt can carry, because this is the rule
// most likely to be broken helpfully.
const BENCH_RULES =
`BENCHMARKS - read this before writing anything comparative.

The BENCHMARK EVIDENCE block below is the only benchmark data that exists for this
panel. You may state a comparison only if it is in that block.

You must not state, estimate, recall, approximate or infer any industry average,
typical value, "most businesses" figure or rule of thumb from your own knowledge.
Not hedged, not rounded, not "generally around". A figure you supply from memory has
no source, and the client reading the panel can check it.

If the block carries no benchmark for a metric, say the comparison is not available
and move on. That is a complete answer and a defensible one.

When you do cite a benchmark, say exactly what it is:
- A peer figure is "the median of N other AIM clients" - our own book. Never call it
  an industry average. Being explicit that it is our client base is a strength: it is
  a real comparison against comparable local businesses, which is more than a generic
  national average would give.
- A published figure gets its source named, with its date: "Google's Core Web Vitals
  threshold" and so on.
- A threshold is not an average. Do not describe passing a threshold as beating the
  industry, or failing one as being below average.

Where the block says the same-vertical comparison was withheld because the peer group
was too small, do not work around it by comparing to the whole book and calling that
the industry. Report it as the whole book, which is what it is.`;

function panelAsk(panel: string, bench: unknown): string {
  const p = PANELS[panel];
  return `You are analysing one panel of the dashboard: ${p.label}.

Query first. The views that matter here are ${p.views}. Also check ai.data_freshness
before you describe anything as a decline, so a source that stopped collecting is not
reported as performance falling.

${p.focus}

Then return a single JSON object and nothing else - no prose around it, no code fence:

{
  "headline": "One sentence: the most important thing this panel shows for this period.",
  "bullets": [{"label": "Short bold lead-in", "text": "One sentence carrying the actual figures."}],
  "benchmark": "One or two sentences on how this client sits against the benchmark evidence below - or, if none applies, that no comparison was available and why.",
  "next": "The single action you would take next, specific enough to put on a task list."
}

Rules:
- 2 to 4 bullets. Every one carries a figure you just read from the database.
- Name the period the figures cover.
- If the panel's source is not connected, say that in the headline instead of reporting zeroes.
- No pleasantries, no sign-off. This is rendered directly into the panel.

${BENCH_RULES}

BENCHMARK EVIDENCE (the only benchmarks that exist for this panel):
${JSON.stringify(bench, null, 1)}`;
}

// Mechanical backstop for the one rule that matters most. If the evidence block
// contained no benchmark of any kind, then nothing in the output may appeal to an
// industry norm - there was nothing to appeal to. A trip sends it back once.
const BENCH_BAN =
  /\b(industry (average|averages|standard|norm|norms|benchmark|benchmarks|typical)|average for (the )?industry|typical (for|in|across)|most businesses|industry[- ]wide|national average)\b/i;

const BENCH_FIX =
`That draft appeals to an industry norm, but no benchmark was available for this panel -
the evidence block was empty, so there is nothing to compare against and no source to
cite.

Rewrite it and return the same JSON object with every figure about this client
unchanged. Remove the comparison entirely and put in its place a plain statement that
no benchmark was available for this panel. Do not substitute a different comparison,
and do not supply a figure from your own knowledge.

Return the JSON object only.`;

// True when the SQL block gave the model nothing to compare against, in which case
// any comparative claim in the output was invented.
function noBenchmarks(bench: any): boolean {
  const ms = bench?.metrics;
  if (!Array.isArray(ms) || !ms.length) return true;
  return !ms.some((m: any) =>
    m?.peer_industry || m?.peer_all ||
    (Array.isArray(m?.published) && m.published.length > 0));
}

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

  const auth = await tokenAuth(req.headers.get("x-aim-session"));
  if (!auth) return json({ error: "unauthorized" }, 401);
  const role = auth.role;

  /* A client-scoped session cannot use the analyst at all.
   *
   * Everything else in the platform is scoped by replacing a client parameter. That
   * is not possible here: this function hands a model a SQL tool over the ai.* views,
   * which span every client, and no amount of prompt instruction is a security
   * boundary - the whole point of the ai.query design is that the ROLE is the
   * boundary, not the wording.
   *
   * Scoping this properly means teaching the ai.* views to filter on a client set for
   * the session. Until that exists, a client login gets the summaries and panel
   * readings AIM has already generated - served by the dashboard function, which IS
   * scoped - and cannot generate new ones. That also keeps the metered spend on the
   * agency's side of the line.
   */
  if (role === "client") {
    return json({ error: "forbidden",
                  detail: "The analyst is not available on a client login." }, 403);
  }

  const apiKey = providerKey("anthropic");
  if (!apiKey) {
    return json({ error: "No Anthropic key found. Add a secret named ANTHROPIC_API_KEY or Anthropic." }, 500);
  }

  const isReport = body.mode === "report";
  const isPanel  = body.mode === "panel";

  // The panel mode needs a client and a window to key its cache and to build its
  // benchmark block, so these are required rather than defaulted - a panel insight
  // silently generated for the wrong period would be worse than an error.
  let panel = "";
  let bench: any = null;
  if (isPanel) {
    panel = String(body.panel ?? "");
    if (!PANELS[panel]) {
      return json({ error: "unknown_panel", detail: `panel must be one of ${Object.keys(PANELS).join(", ")}` }, 400);
    }
    const cid = Number(body.client_id);
    if (!Number.isFinite(cid) || cid <= 0 || !body.from || !body.to) {
      return json({ error: "panel_needs_client_and_range" }, 400);
    }
    const { data, error } = await db.rpc("panel_benchmark_context", {
      p_client: cid, p_panel: panel, p_from: String(body.from), p_to: String(body.to),
    });
    if (error) return json({ error: "benchmark_context_failed", detail: error.message }, 500);
    bench = data;
    if (bench?.error) return json({ error: bench.error }, 400);
  }

  const question = isReport ? REPORT_ASK
                 : isPanel  ? panelAsk(panel, bench)
                 : String(body.question ?? "").trim();
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
- Do not pad. A short summary with real numbers beats a long one without.

${TONE_RULES}` : isPanel ? `How to answer:
- This is read inside a dashboard panel, alongside the chart it describes. Be short.
  Do not restate what the chart already shows - say what it means.
- Follow the JSON shape you were given exactly and return nothing outside it.
- Every figure must come from a query you just ran or from the benchmark block.
- An account manager reads this before a client call, so the value is in the specific:
  which campaign, which page, which post, which keyword.

${TONE_RULES}` :
`How to answer:
- Lead with the answer, then the evidence. Keep it short and specific.
- Use exact numbers and name the date range they cover.
- Where the answer suggests an action, say what you would do and why. Be concrete.
- If the question cannot be answered from this data, say so and say what would be needed.`}`;

  const messages: any[] = [{ role: "user", content: question }];
  const sqlUsed: { query: string; why?: string; rows?: number; error?: string }[] = [];
  const deadline = Date.now() + WALL_MS;
  let toneRetried = false;
  let benchRetried = false;

  try {
    const steps = isReport ? REPORT_STEPS : isPanel ? PANEL_STEPS : MAX_STEPS;
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
          model: MODEL, max_tokens: isReport ? 3000 : isPanel ? 1500 : 2000,
          system, tools: [TOOL], messages,
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
        if (isPanel) {
          // Same fence tolerance as the report mode.
          const m = text.match(/\{[\s\S]*\}/);
          let ins: any;
          try {
            ins = JSON.parse(m ? m[0] : text);
          } catch {
            return json({ error: "insight_not_json", raw: text.slice(0, 1200) }, 502);
          }

          const blob = JSON.stringify(ins);

          // Tone first: a panel insight is as client-visible as the report.
          const banned = blob.match(TONE_BAN);
          if (banned && !toneRetried && Date.now() < deadline) {
            toneRetried = true;
            messages.push({ role: "user", content: TONE_FIX });
            continue;
          }

          // Then the benchmark rule. This only fires when the evidence block was
          // genuinely empty, so a comparative claim in the output cannot have come
          // from anywhere but the model's own recollection.
          const invented = noBenchmarks(bench) ? blob.match(BENCH_BAN) : null;
          if (invented && !benchRetried && Date.now() < deadline) {
            benchRetried = true;
            messages.push({ role: "user", content: BENCH_FIX });
            continue;
          }

          const cid = Number(body.client_id);
          let saved = false;
          let saveError: string | null = null;
          const { error: upErr } = await db.from("panel_insights").upsert({
            client_id: cid,
            panel,
            period_from: String(body.from),
            period_to: String(body.to),
            headline: ins.headline ?? null,
            bullets: Array.isArray(ins.bullets) ? ins.bullets : [],
            benchmark_note: ins.benchmark ?? null,
            next_step: typeof ins.next === "string" ? ins.next
                     : Array.isArray(ins.next) ? ins.next[0] ?? null : null,
            model: MODEL,
            queries_run: Math.min(sqlUsed.length, 32767),
            benchmark_context: bench,
            generated_at: new Date().toISOString(),
          }, { onConflict: "client_id,panel,period_from,period_to" });
          if (upErr) saveError = upErr.message; else saved = true;

          return json({
            insight: ins, panel, sql: sqlUsed, model: MODEL, steps: step + 1,
            saved, save_error: saveError,
            tone_rewritten: toneRetried,
            benchmark_rewritten: benchRetried,
            had_benchmarks: !noBenchmarks(bench),
          });
        }

        if (isReport) {
          // Models sometimes wrap JSON in a fence despite being asked not to.
          const m = text.match(/\{[\s\S]*\}/);
          let report: any;
          try {
            report = JSON.parse(m ? m[0] : text);
          } catch {
            return json({ error: "summary_not_json", raw: text.slice(0, 1500) }, 502);
          }

          // Tone check on the finished draft. One rewrite, then accept whatever
          // comes back: a second failure means the wording is arguable rather
          // than plainly wrong, and returning nothing would be worse than
          // returning a correct report the account manager can edit.
          const banned = JSON.stringify(report).match(TONE_BAN);
          if (banned && !toneRetried && Date.now() < deadline) {
            toneRetried = true;
            messages.push({ role: "user", content: TONE_FIX });
            continue;
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
                        saved, save_error: saveError,
                        tone_rewritten: toneRetried,
                        tone_flag: banned ? banned[0] : null });
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
