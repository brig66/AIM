import "jsr:@supabase/functions-js/edge-runtime.d.ts";
import { createClient } from "jsr:@supabase/supabase-js@2";

/**
 * forms-classify - reads the message body of a form submission and decides what
 * it is, using the same taxonomy as the AIM Form Submission Report skill.
 *
 * Why this exists. Classification used to be forms_classify(), a rule in
 * Postgres. Its final branch promoted anything with contact details and three
 * or more fields to valid_lead, so a vendor pitch with a name, an email and a
 * company field scored 0.8 and reached the client's report as a lead. Its spam
 * keyword list caught 1 of 195 such rows. Rules cannot keep up with how
 * solicitations are written, so the message itself has to be read.
 *
 * The distinction that decides almost every case: is the sender trying to BUY
 * from this business, or SELL to it? That is a question about meaning, not
 * about which fields are filled in.
 *
 * The rule still runs first at ingest. This pass runs after and only ever
 * revisits rows that have a message to read; rows the relay confirmed without
 * retained content keep their honest "content was not retained" verdict rather
 * than being guessed at.
 *
 * Auth: x-collector-token, the same credential the other collectors use.
 */

const db = createClient(Deno.env.get("SUPABASE_URL")!,
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!, { auth: { persistSession: false } });

const ANTHROPIC = "https://api.anthropic.com/v1/messages";
const WALL_MS = 110_000;
const BATCH = 8;          // submissions per model call
const MAX_CHARS = 3500;   // per submission, so one long body cannot crowd a batch

const json = (b: unknown, s = 200) => new Response(JSON.stringify(b),
  { status: s, headers: { "Content-Type": "application/json", "Cache-Control": "no-store" } });

const CLASSES = new Set(["valid_lead", "needs_review", "needs_attention",
                         "suspicious", "spam", "test_submission"]);

// Secrets are named by hand in the dashboard, so accept the common variants.
function anthropicKey(): string | undefined {
  for (const n of ["ANTHROPIC_API_KEY", "Anthropic", "ANTHROPIC", "CLAUDE_API_KEY"]) {
    const v = (Deno.env.get(n) ?? "").trim()
      .replace(/^export\s+/i, "").replace(/^[A-Za-z_][A-Za-z0-9_]*\s*=\s*/, "")
      .trim().replace(/^["']|["']$/g, "");
    if (v) return v;
  }
  return undefined;
}

async function authed(req: Request) {
  const sent = req.headers.get("x-collector-token");
  if (!sent) return false;
  const { data } = await db.from("internal_config").select("value")
    .eq("key", "collector_token").single();
  const exp = data?.value ?? "";
  if (!exp || sent.length !== exp.length) return false;
  let d = 0;
  for (let i = 0; i < exp.length; i++) d |= sent.charCodeAt(i) ^ exp.charCodeAt(i);
  return d === 0;
}

/**
 * Every key and value, flattened. Deliberately not just the "message" field:
 * the email parser sometimes splits a prose body across many keys, so the text
 * that matters can end up as a key rather than a value. Reading the whole
 * payload makes the classifier immune to how well the body happened to parse.
 */
function blobOf(fields: Record<string, string> | null): string {
  if (!fields) return "";
  const parts: string[] = [];
  for (const [k, v] of Object.entries(fields)) {
    const key = String(k ?? "").trim();
    const val = String(v ?? "").trim();
    if (!key && !val) continue;
    parts.push(key && val ? `${key}: ${val}` : (key || val));
  }
  return parts.join("\n").slice(0, MAX_CHARS);
}

const SYSTEM =
`You triage website form submissions for a marketing agency, on behalf of its client.

The question that decides most cases: is the sender trying to BUY something from
this business, or trying to SELL something to it?

Someone offering SEO, web design, AI or automation services, software, staffing,
virtual assistants, lead lists, backlinks, guest posts, directory listings,
funding, or a "partnership" is a solicitation - no matter how polite it is, how
well written it is, or how complete their contact details are. Contact details
never make something a lead. A filled-in form is not a lead. Wanting what this
business sells is what makes a lead.

Classify each submission as exactly one of:

- valid_lead: a genuine prospective customer enquiry. They want the service or
  product this business provides, or are asking a question a customer would ask.
- needs_review: plausible but unclear, incomplete, or not obviously a customer -
  including job applications, supplier or press enquiries, and messages too
  short to judge. A human should look.
- suspicious: scam patterns. Advance-fee and payment-redirection language,
  "financing is secured" purchase offers, overpayment or shipping-agent stories,
  gibberish identities attached to a plausible request.
- spam: vendor solicitations, bulk outreach, bots, link spam, adult or crypto
  content, anything selling to the business.
- needs_attention: a consumer-protection problem the business must know about -
  someone impersonating them, a complaint about being defrauded in their name,
  a legal or safety issue raised through the form.
- test_submission: an internal or developer test.

Judge on the message text. If the text is empty or meaningless, say needs_review
rather than guessing.

Return ONLY a JSON array, one object per submission, in the same order:
[{"id": <the id given>, "class": "<one of the six>", "confidence": 0.0-1.0,
  "why": "<one plain sentence, safe to show the client, no jargon>"}]

The "why" is printed in a report a client reads. Write it as a person would:
"Asking about a kitchen remodel for a house in Arlington." or "Vendor pitch
offering SEO services." Never mention rules, scores, models or this prompt.`;

async function classifyBatch(items: any[], model: string, apiKey: string) {
  const payload = items.map((s) => ({
    id: s.id,
    client: s.client_name,
    business_does: s.client_domain,
    form: s.form_name ?? "",
    from_name: s.submitter_name ?? "",
    from_email: s.submitter_email ?? "",
    from_phone: s.submitter_phone ?? "",
    submission: blobOf(s.fields_json),
  }));

  const res = await fetch(ANTHROPIC, {
    method: "POST",
    headers: { "x-api-key": apiKey, "anthropic-version": "2023-06-01",
               "content-type": "application/json" },
    body: JSON.stringify({
      model, max_tokens: 2000, system: SYSTEM,
      messages: [{ role: "user", content: JSON.stringify(payload) }],
    }),
  });
  if (!res.ok) {
    throw new Error(`anthropic ${res.status}: ${(await res.text()).slice(0, 300)}`);
  }
  const out = await res.json();
  const text = (out.content ?? []).filter((c: any) => c.type === "text")
    .map((c: any) => c.text).join("\n").trim();
  // Models occasionally wrap the array in a fence despite being asked not to.
  const m = text.match(/\[[\s\S]*\]/);
  let parsed: any[];
  try { parsed = JSON.parse(m ? m[0] : text); } catch {
    throw new Error("model did not return JSON: " + text.slice(0, 200));
  }
  if (!Array.isArray(parsed)) throw new Error("model returned a non-array");
  return parsed;
}

async function run(opts: { limit: number; dryRun: boolean; client?: number;
                           onlyClass?: string; deadline: number }) {
  const apiKey = anthropicKey();
  if (!apiKey) throw new Error("No Anthropic key. Add ANTHROPIC_API_KEY as a secret.");
  const { data: cfg } = await db.from("internal_config").select("value")
    .eq("key", "ask_model").maybeSingle();
  const model = cfg?.value || "claude-sonnet-4-6";

  // Only rows with content, and never one a human has already ruled on.
  let q = db.from("fact_form_submissions")
    .select("id,client_id,form_id,submitter_name,submitter_email,submitter_phone," +
            "fields_json,classification,classification_confidence,classified_by,human_reviewed")
    .not("fields_json", "is", null)
    .or("human_reviewed.is.null,human_reviewed.is.false")
    .neq("classified_by", "llm")
    .order("submitted_at", { ascending: false })
    .limit(Math.max(1, Math.min(opts.limit, 400)));
  if (opts.client) q = q.eq("client_id", opts.client);
  if (opts.onlyClass) q = q.eq("classification", opts.onlyClass);

  const { data: rows, error } = await q;
  if (error) throw new Error("select: " + error.message);
  if (!rows?.length) return { considered: 0, changed: 0, remaining: 0, changes: [] };

  // Client name and domain give the model the "what does this business sell"
  // context it needs to tell a customer from a supplier.
  const ids = [...new Set(rows.map((r: any) => r.client_id))];
  const { data: clients } = await db.from("clients").select("id,name,domain").in("id", ids);
  const cmap = new Map((clients ?? []).map((c: any) => [c.id, c]));
  const { data: forms } = await db.from("dim_forms").select("id,form_name")
    .in("id", [...new Set(rows.map((r: any) => r.form_id).filter(Boolean))]);
  const fmap = new Map((forms ?? []).map((f: any) => [f.id, f.form_name]));

  const enriched = rows.map((r: any) => ({
    ...r,
    client_name: cmap.get(r.client_id)?.name ?? "",
    client_domain: cmap.get(r.client_id)?.domain ?? "",
    form_name: fmap.get(r.form_id) ?? "",
  }));

  const changes: any[] = [];
  let considered = 0, changed = 0, batches = 0;

  for (let i = 0; i < enriched.length; i += BATCH) {
    if (Date.now() > opts.deadline) break;
    const slice = enriched.slice(i, i + BATCH);
    let verdicts: any[];
    try { verdicts = await classifyBatch(slice, model, apiKey); }
    catch (e) {
      // One bad batch must not abandon the rest of the run.
      changes.push({ error: String((e as Error).message).slice(0, 200),
                     ids: slice.map((s) => s.id) });
      continue;
    }
    batches++;
    const byId = new Map(verdicts.map((v: any) => [Number(v.id), v]));

    for (const s of slice) {
      const v = byId.get(Number(s.id));
      considered++;
      if (!v || !CLASSES.has(String(v.class))) continue;
      const cls = String(v.class);
      const conf = Math.max(0, Math.min(1, Number(v.confidence) || 0.6));
      const why = String(v.why ?? "").slice(0, 400);
      const moved = cls !== s.classification;
      if (moved) changed++;
      changes.push({ id: s.id, from: s.classification, to: cls,
                     confidence: conf, why });
      if (!opts.dryRun) {
        const { error: uErr } = await db.from("fact_form_submissions").update({
          classification: cls,
          classification_confidence: conf,
          classified_by: "llm",
          classification_rationale: why,
          classification_signals: { read_message: true, model,
                                    previous: s.classification,
                                    previous_by: s.classified_by },
        }).eq("id", s.id)
          // Belt and braces: a human ruling made mid-run still wins.
          .or("human_reviewed.is.null,human_reviewed.is.false");
        if (uErr) changes.push({ id: s.id, error: uErr.message.slice(0, 200) });
      }
    }
  }

  // What is left after this call, so a caller knows whether to run again.
  let cq = db.from("fact_form_submissions")
    .select("id", { count: "exact", head: true })
    .not("fields_json", "is", null)
    .or("human_reviewed.is.null,human_reviewed.is.false")
    .neq("classified_by", "llm");
  if (opts.client) cq = cq.eq("client_id", opts.client);
  const { count } = await cq;

  return { considered, changed, batches, model,
           dry_run: opts.dryRun,
           remaining: opts.dryRun ? (count ?? 0) : Math.max(0, (count ?? 0)),
           changes };
}

Deno.serve(async (req: Request) => {
  if (req.method !== "POST") return json({ error: "method_not_allowed" }, 405);
  if (!await authed(req)) return json({ error: "unauthorized" }, 401);
  let body: any = {};
  try { body = await req.json(); } catch { /* defaults */ }
  try {
    return json(await run({
      limit: Number(body.limit ?? 40),
      dryRun: body.dry_run === true,
      client: body.client ? Number(body.client) : undefined,
      onlyClass: body.only_class ? String(body.only_class) : undefined,
      deadline: Date.now() + WALL_MS,
    }));
  } catch (e) {
    return json({ error: String((e as Error).message ?? e) }, 500);
  }
});
