import "jsr:@supabase/functions-js/edge-runtime.d.ts";
import { createClient } from "jsr:@supabase/supabase-js@2";

// ai-collect - AI Visibility Tracker as a scheduled collector. One job per
// prompt set: ask every active prompt to every configured engine, score brand
// mention / citation / share of voice, write through ai_ingest(). Jobs resume -
// a run opens once per set per pass and only the (prompt, engine) pairs still
// missing are queried, so a job that runs out of wall clock yields and is
// picked up again by the dispatcher.
//
// 2026-09-07 - citations and retrieved sources separated:
//   cited          - brand domain in an answer-anchored citation
//   source_present - brand domain anywhere in the retrieved set
// Rows before this date used the old conflated definition and are not
// comparable; fact_ai_citations.anchored is NULL for them.
// 2026-09-07 - repeat sampling via ai_prompt_sets.repeats.
// 2026-09-07 - all-caps aliases match case-sensitively; typographic
// punctuation normalised on both sides before matching.
// 2026-09-07 - transient provider failures retry with backoff.
// 2026-09-27 - cost reduction:
//   - branded prompts take one sample, not ai_prompt_sets.repeats. A prompt
//     that names the brand is answered with the brand almost every time, so
//     extra samples bought nothing. They still run every pass, because
//     dash_ai_core reads branded performance from the latest run only.
//   - Claude web search capped at two searches per question (removed
//     2026-10-14, see below).
//   - every result is written as soon as it is scored (was batches of 8), and
//     no call is started that could still be running when the platform kills
//     the function. Before this, a kill discarded up to seven paid results
//     plus whatever was in flight, and the next drain bought them again.
//   - a pair that runs out of time with no sample is left unwritten so the
//     next drain retries it, instead of being written as an error row.
// 2026-10-01 - every provider call is logged to ai_usage_log (tokens, web
//   searches, client) for the AI Cost Dashboard.
// 2026-10-01 - default Anthropic model is claude-sonnet-5-5 (was
//   claude-sonnet-4-6). A model named in ai_prompt_sets.models still wins.
// 2026-10-01 - Perplexity answers branded prompts (the ones that name the
//   brand) with the cheaper sonar model; discovery prompts keep the model set
//   in ai_prompt_sets.models (sonar-pro). Change PERPLEXITY_BRANDED_MODEL to
//   revert.
// 2026-10-08 - Anthropic questions go through the Message Batches API (50% of
//   list price; web search works inside a batch and is billed as before).
//   - Switch: internal_config 'ai_anthropic_batch' = 'on' | 'off' | a
//     comma-separated list of set slugs. Missing means off.
//   - A queued job submits one batch with every Anthropic question for its set
//     (each repeat is its own request), asks the other engines as before, then
//     waits. Batch ids live in ai_batches. Each drain checks a waiting batch at
//     most once a minute; when it has ended the results are scored, written
//     and logged exactly like the one-at-a-time path, under the run's date.
//   - Usage rows from a batch carry meta.batch = true, which log_ai_usage
//     prices at half. Web search rows are priced as before.
//   - A job that is only waiting on its batch stays claimed while the drain
//     moves on to the next job, so jobs further down the queue still get their
//     batches submitted instead of waiting behind the first few.
//   - mode "run" (a manual "run now") stays one call at a time unless the
//     request says batch: true, because the person asking wants answers now.
//     A run that already has an open batch always finishes through it.
// 2026-10-08 - Claude's answer limit (max_tokens) raised from 1,024 to 4,096.
//   claude-sonnet-5-5 thinks before it answers, and at 1,024 about one answer
//   in five used the whole limit thinking and stopped with no text, which was
//   scored as "not mentioned" (72 of 329 Claude answers on the Oct 5 run).
//   Only tokens actually written are billed.
// 2026-10-14 - Claude web search uncapped again. On the Oct 5 pass Claude's
//   citation rate on answers that did have text fell from 23.8% to 17.5% on
//   the same questions: fewer searches find fewer sources, which skews the
//   measurement. A web_search_tool_result whose content is an error object
//   rather than a list is skipped instead of failing the whole answer (2
//   answers on Oct 5).

const db = createClient(Deno.env.get("SUPABASE_URL")!,
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!, { auth: { persistSession: false } });

// Edge functions are cut off at ~150s. Every call has to be finished (or
// abandoned) before then, or its result is lost after it has been paid for.
const PAIR_START_MS = 75_000;    // no new (prompt, engine) pair after this
const REPEAT_START_MS = 100_000; // no further sample of a started pair after this
const HARD_STOP_MS = 140_000;    // every provider call is aborted by this
const CONC = 4;                  // parallel provider calls
const CLAUDE_MAX_TOKENS = 4096;  // room to think and still write the answer
const PERPLEXITY_BRANDED_MODEL = "sonar"; // branded prompts only
const ANTHROPIC_MESSAGES = "https://api.anthropic.com/v1/messages";
const ANTHROPIC_BATCHES = "https://api.anthropic.com/v1/messages/batches";
const ANTHROPIC_DEFAULT_MODEL = "claude-sonnet-5-5";
const BATCH_POLL_MS = 60_000;    // check a waiting batch at most once a minute

let hardStop = Date.now() + HARD_STOP_MS;
const OUT_OF_TIME = "out of time";

const json = (b: unknown, s = 200) =>
  new Response(JSON.stringify(b), { status: s, headers: { "Content-Type": "application/json" } });

/* --- keys --- */
const KEY_NAMES: Record<string, string[]> = {
  anthropic:  ["ANTHROPIC_API_KEY", "Anthropic", "ANTHROPIC", "CLAUDE_API_KEY"],
  openai:     ["OPENAI_API_KEY", "OpenAI", "OPENAI"],
  perplexity: ["PERPLEXITY_API_KEY", "Perplexity", "PERPLEXITY"],
  gemini:     ["GEMINI_API_KEY", "Gemini", "GEMINI", "GOOGLE_API_KEY"],
};
function providerKey(p: string): string | undefined {
  for (const n of KEY_NAMES[p] ?? []) {
    const v = (Deno.env.get(n) ?? "").trim()
      .replace(/^export\s+/i, "")
      .replace(/^[A-Za-z_][A-Za-z0-9_]*\s*=\s*/, "")
      .trim().replace(/^["']|["']$/g, "");
    if (v) return v;
  }
}

/* --- batch switch --- */
let batchSetting: string | null = null;
async function batchEnabled(setSlug: string): Promise<boolean> {
  if (batchSetting === null) {
    const { data } = await db.from("internal_config").select("value")
      .eq("key", "ai_anthropic_batch").maybeSingle();
    batchSetting = String(data?.value ?? "").trim().toLowerCase();
  }
  const v = batchSetting;
  if (!v || v === "off") return false;
  if (v === "on") return true;
  return v.split(",").map((s) => s.trim()).filter(Boolean).includes(setSlug.toLowerCase());
}

/* --- cost logging --- */
// One ai_usage_log row per provider call, plus one per batch of web searches
// (billed per search, not per token). The set's client rides along so the AI
// Cost Dashboard can show spend by client. A logging failure never fails a pair.
// tokenMeta lands on the token row (meta.batch = true halves its estimate);
// searchMeta on the web search row (search fees are not discounted).
let usageClient: number | null = null;
async function logUsage(provider: string, model: string, d: any,
                        tokenMeta?: Record<string, unknown>, searchMeta?: Record<string, unknown>) {
  try {
    const u = d?.usage ?? {}, g = d?.usageMetadata ?? {};
    let i = 0, o = 0, cr = 0, cw = 0, searches = 0;
    if (provider === "anthropic") {
      i = u.input_tokens ?? 0; o = u.output_tokens ?? 0;
      cr = u.cache_read_input_tokens ?? 0; cw = u.cache_creation_input_tokens ?? 0;
      searches = u.server_tool_use?.web_search_requests ?? 0;
    } else if (provider === "openai") {
      cr = u.input_tokens_details?.cached_tokens ?? 0;
      i = (u.input_tokens ?? 0) - cr; o = u.output_tokens ?? 0;
      searches = (d?.output ?? []).filter((x: any) => x?.type === "web_search_call").length;
    } else if (provider === "gemini") {
      cr = g.cachedContentTokenCount ?? 0; i = (g.promptTokenCount ?? 0) - cr;
      o = (g.candidatesTokenCount ?? 0) + (g.thoughtsTokenCount ?? 0);
    } else if (provider === "perplexity") {
      i = u.prompt_tokens ?? 0; o = u.completion_tokens ?? 0;
    }
    const meta = { ...(u.cost ? { reported_cost: u.cost } : {}), ...(tokenMeta ?? {}) };
    const base = { p_provider: provider, p_task: "ai-collect", p_client_id: usageClient };
    await db.rpc("log_ai_usage", { ...base, p_model: d?.model ?? model, p_input: i, p_output: o,
      p_cache_read: cr, p_cache_write: cw, p_requests: 1,
      p_meta: Object.keys(meta).length ? meta : null });
    if (searches) await db.rpc("log_ai_usage", { ...base, p_model: "web_search", p_requests: searches,
      p_meta: searchMeta ?? null });
  } catch { /* cost logging must never break collection */ }
}

/* --- providers --- */
type Cit = { url: string; title?: string; rank: number };
type PR = { answer: string; cits: Cit[]; srcs: Cit[]; error?: string };

const dedup = (c: Cit[], start = 1, exclude?: Set<string>): Cit[] => {
  const seen = new Set<string>(), out: Cit[] = [];
  for (const x of [...c].sort((a, b) => a.rank - b.rank)) {
    const u = (x.url || "").trim();
    if (!u || seen.has(u) || exclude?.has(u)) continue;
    seen.add(u); out.push({ ...x, rank: start + out.length });
  }
  return out;
};
const urlSet = (c: Cit[]) => new Set(c.map((x) => (x.url || "").trim()));

// Retries 429 and 5xx with exponential backoff and jitter. Without this a
// provider rate limit becomes a permanent error row: the pair is written as
// failed, resume then skips it, and the run silently reports on three engines
// instead of four. Only transient statuses retry - a 401 fails immediately so
// a real credentials or billing problem is not hidden behind three sleeps.
// No attempt starts, and none runs, past hardStop.
async function post(url: string, headers: Record<string, string>, body: unknown,
                    ms = 90_000, tries = 3) {
  let lastErr = "";
  for (let attempt = 0; attempt < tries; attempt++) {
    if (attempt) {
      const wait = Math.min(1500 * 2 ** (attempt - 1), 6000) + Math.floor(Math.random() * 600);
      if (Date.now() + wait + 5000 > hardStop) break;
      await new Promise((r) => setTimeout(r, wait));
    }
    const left = hardStop - Date.now();
    if (left < 5000) { lastErr = lastErr || OUT_OF_TIME; break; }
    const ac = new AbortController();
    const t = setTimeout(() => ac.abort(), Math.min(ms, left));
    let status = 0, txt = "", netErr = "";
    try {
      const r = await fetch(url, {
        method: "POST", signal: ac.signal,
        headers: { "Content-Type": "application/json", ...headers },
        body: JSON.stringify(body),
      });
      status = r.status;
      txt = await r.text();
    } catch (e) {
      netErr = String((e as Error).message ?? e);
    } finally { clearTimeout(t); }

    if (!netErr && status >= 200 && status < 300) return JSON.parse(txt);
    lastErr = netErr || `${status} ${txt.replace(/\s+/g, " ").slice(0, 180)}`;
    if (!(netErr || status === 429 || status >= 500)) break;
  }
  throw new Error(lastErr || "request failed");
}

// GET with the same retry and deadline rules as post(); returns the body text
// (batch results are JSON Lines, not one JSON document).
async function getText(url: string, headers: Record<string, string>, ms = 30_000, tries = 3) {
  let lastErr = "";
  for (let attempt = 0; attempt < tries; attempt++) {
    if (attempt) {
      const wait = Math.min(1500 * 2 ** (attempt - 1), 6000) + Math.floor(Math.random() * 600);
      if (Date.now() + wait + 5000 > hardStop) break;
      await new Promise((r) => setTimeout(r, wait));
    }
    const left = hardStop - Date.now();
    if (left < 5000) { lastErr = lastErr || OUT_OF_TIME; break; }
    const ac = new AbortController();
    const t = setTimeout(() => ac.abort(), Math.min(ms, left));
    let status = 0, txt = "", netErr = "";
    try {
      const r = await fetch(url, { headers, signal: ac.signal });
      status = r.status;
      txt = await r.text();
    } catch (e) {
      netErr = String((e as Error).message ?? e);
    } finally { clearTimeout(t); }

    if (!netErr && status >= 200 && status < 300) return txt;
    lastErr = netErr || `${status} ${txt.replace(/\s+/g, " ").slice(0, 180)}`;
    if (!(netErr || status === 429 || status >= 500)) break;
  }
  throw new Error(lastErr || "request failed");
}

// Perplexity: neither `citations` nor `search_results` marks what the answer
// leaned on - both are the consulted set and overlap almost entirely. The
// anchoring signal is the inline [n] markers, which index into `citations`.
async function askPerplexity(prompt: string, model: string): Promise<PR> {
  const k = providerKey("perplexity");
  if (!k) return { answer: "", cits: [], srcs: [], error: "no perplexity key" };
  const d = await post("https://api.perplexity.ai/chat/completions",
    { Authorization: `Bearer ${k}` },
    { model: model || "sonar-pro", messages: [{ role: "user", content: prompt }] });
  await logUsage("perplexity", model || "sonar-pro", d);
  const answer = d?.choices?.[0]?.message?.content ?? "";

  const titles = new Map<string, string>();
  const results: Cit[] = [];
  (d.search_results ?? []).forEach((s: any, i: number) => {
    if (!s?.url) return;
    if (s.title) titles.set(s.url, s.title);
    results.push({ url: s.url, title: s.title, rank: i + 1 });
  });
  const listed: Cit[] = (d.citations ?? []).map((u: unknown, i: number) => {
    const url = typeof u === "string" ? u : ((u as any)?.url ?? "");
    return { url, title: titles.get(url), rank: i + 1 };
  });

  const refs = new Set<number>();
  for (const m of String(answer).matchAll(/\[(\d{1,3})\]/g)) refs.add(Number(m[1]));

  const anchored = refs.size ? listed.filter((_, i) => refs.has(i + 1)) : listed;
  const anchoredUrls = new Set(anchored.map((c) => c.url));
  const rest = [...listed, ...results].filter((c) => !anchoredUrls.has(c.url));

  const C = dedup(anchored);
  return { answer, cits: C, srcs: dedup(rest, C.length + 1, urlSet(C)) };
}

async function askOpenAI(prompt: string, model: string): Promise<PR> {
  const k = providerKey("openai");
  if (!k) return { answer: "", cits: [], srcs: [], error: "no openai key" };
  const d = await post("https://api.openai.com/v1/responses",
    { Authorization: `Bearer ${k}` },
    { model: model || "gpt-4.1", tools: [{ type: "web_search" }], input: prompt }, 120_000);
  await logUsage("openai", model || "gpt-4.1", d);
  const parts: string[] = [], cits: Cit[] = [];
  let rank = 0;
  for (const item of d.output ?? []) {
    for (const b of item.content ?? []) {
      if (b.type === "output_text" || b.type === "text") {
        parts.push(b.text ?? "");
        for (const a of b.annotations ?? []) {
          if (a.type === "url_citation" && a.url) cits.push({ url: a.url, title: a.title, rank: ++rank });
        }
      }
    }
  }
  return { answer: parts.join("") || (d.output_text ?? ""), cits: dedup(cits), srcs: [] };
}

// Gemini: groundingChunks is the retrieved set; groundingSupports names the
// chunk indices the answer actually leaned on.
async function askGemini(prompt: string, model: string): Promise<PR> {
  const k = providerKey("gemini");
  if (!k) return { answer: "", cits: [], srcs: [], error: "no gemini key" };
  const m = model || "gemini-2.5-flash";
  const d = await post(
    `https://generativelanguage.googleapis.com/v1beta/models/${m}:generateContent?key=${encodeURIComponent(k)}`,
    {}, { contents: [{ parts: [{ text: prompt }] }], tools: [{ google_search: {} }] }, 120_000);
  await logUsage("gemini", m, d);
  const c = (d.candidates ?? [])[0] ?? {};
  const answer = (c.content?.parts ?? []).map((p: any) => p.text ?? "").join("");

  const chunks: any[] = c.groundingMetadata?.groundingChunks ?? [];
  const supported = new Set<number>();
  for (const s of c.groundingMetadata?.groundingSupports ?? []) {
    for (const i of s?.groundingChunkIndices ?? []) supported.add(Number(i));
  }
  const cits: Cit[] = [], srcs: Cit[] = [];
  chunks.forEach((ch: any, i: number) => {
    const w = ch.web ?? {};
    if (!w.uri) return;
    (supported.has(i) ? cits : srcs).push({ url: w.uri, title: w.title, rank: i + 1 });
  });
  if (supported.size === 0 && srcs.length) {
    const C = dedup([...cits, ...srcs]);
    return { answer, cits: C, srcs: [] };
  }
  const C = dedup(cits);
  return { answer, cits: C, srcs: dedup(srcs, C.length + 1, urlSet(C)) };
}

// The request and the parsing are shared by the one-at-a-time call and the
// batch path, so both ask the same question and read the answer the same way.
function anthropicParams(prompt: string, model: string) {
  return {
    model: model || ANTHROPIC_DEFAULT_MODEL, max_tokens: CLAUDE_MAX_TOKENS,
    tools: [{ type: "web_search_20250305", name: "web_search" }],
    messages: [{ role: "user", content: prompt }],
  };
}
function parseAnthropic(d: any): PR {
  const parts: string[] = [], cits: Cit[] = [], srcs: Cit[] = [];
  let rank = 0, srank = 0;
  for (const b of d?.content ?? []) {
    if (b.type === "text") {
      parts.push(b.text ?? "");
      for (const c of b.citations ?? []) if (c.url) cits.push({ url: c.url, title: c.title, rank: ++rank });
    } else if (b.type === "web_search_tool_result") {
      // content is an error object, not a list, when the search itself failed.
      const results = Array.isArray(b.content) ? b.content : [];
      for (const r of results) if (r?.url) srcs.push({ url: r.url, title: r.title, rank: ++srank });
    }
  }
  const C = dedup(cits);
  return { answer: parts.join(""), cits: C, srcs: dedup(srcs, C.length + 1, urlSet(C)) };
}
async function askAnthropic(prompt: string, model: string): Promise<PR> {
  const k = providerKey("anthropic");
  if (!k) return { answer: "", cits: [], srcs: [], error: "no anthropic key" };
  const params = anthropicParams(prompt, model);
  const d = await post(ANTHROPIC_MESSAGES,
    { "x-api-key": k, "anthropic-version": "2023-06-01" }, params, 120_000);
  await logUsage("anthropic", params.model, d);
  return parseAnthropic(d);
}

const ENGINES: Record<string, (p: string, m: string) => Promise<PR>> = {
  perplexity: askPerplexity, openai: askOpenAI, gemini: askGemini, anthropic: askAnthropic,
};
const ALIAS: Record<string, string> = {
  chatgpt: "openai", gpt: "openai", "gpt-4": "openai", openai: "openai",
  claude: "anthropic", anthropic: "anthropic",
  google: "gemini", gemini: "gemini", bard: "gemini",
  perplexity: "perplexity", sonar: "perplexity",
};
const engineOf = (e: string) => ALIAS[(e ?? "").trim().toLowerCase()] ?? "";

/* --- scoring --- */
const REDIRECT = /(^|\.)vertexaisearch\.cloud\.google\.com$/;
function host(url: string, title?: string): string {
  let d = "";
  try { d = new URL(url).hostname.toLowerCase().replace(/^www\./, ""); } catch { /* bad url */ }
  if ((!d || REDIRECT.test(d)) && title && /^[a-z0-9.-]+\.[a-z]{2,}$/i.test(title.trim())) {
    d = title.trim().toLowerCase().replace(/^www\./, "");
  }
  return d;
}
// Engines write typographic punctuation - "The Man's Shop" with a curly
// apostrophe - while a config stores the typewriter form. Left unnormalised
// that missed 30 of 80 answers for one client, a 37-point undercount.
const norm = (s: string) => s
  .replace(/[‘’ʼʹ′]/g, "'")
  .replace(/[“”″]/g, '"')
  .replace(/[‐-―−]/g, "-")
  .replace(/[  -   　]/g, " ");
const esc = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
// An all-caps alias is an initialism, and case-insensitively it collides with
// an ordinary word - "AIM" matches "aim to provide".
const allCaps = (a: string) => /[A-Z]{2,}/.test(a) && !/[a-z]/.test(a);
const rx = (a: string) =>
  new RegExp(`(?<![A-Za-z0-9])${esc(a)}(?![A-Za-z0-9])`, allCaps(a) ? "g" : "gi");
const count = (text: string, aliases: string[]) => {
  const t = norm(text);
  return aliases.reduce((n, a) => n + (a ? (t.match(rx(norm(a)))?.length ?? 0) : 0), 0);
};

const POS = ["best", "top", "leading", "recommended", "excellent", "premium", "trusted", "superior", "ideal"];
const NEG = ["expensive", "overpriced", "poor", "limited", "drawback", "downside", "complaint", "issue", "lacks"];
function sentiment(text: string, aliases: string[]): string {
  if (!count(text, aliases)) return "n/a";
  const A = aliases.map(norm);
  const rel = norm(text).split(/(?<=[.!?])\s+/)
    .filter((s) => A.some((a) => a && rx(a).test(s)));
  const blob = rel.join(" ").toLowerCase();
  const p = POS.filter((w) => blob.includes(w)).length;
  const n = NEG.filter((w) => blob.includes(w)).length;
  return p > n ? "positive" : n > p ? "negative" : "neutral";
}

type Ent = { name: string; aliases?: string[]; domains?: string[] };
const brandAliases = (set: any): string[] => {
  const brand: Ent = set.brand ?? {};
  return brand.aliases?.length ? brand.aliases : [brand.name].filter(Boolean);
};

// ai_prompts.branded when it is set; otherwise the prompt is branded if it
// names the brand, the same rule dash_ai_core falls back to.
const isBranded = (p: any, set: any) =>
  typeof p.branded === "boolean" ? p.branded : count(p.prompt ?? "", brandAliases(set)) > 0;

function score(res: PR, set: any) {
  const text = res.answer ?? "";
  const brand: Ent = set.brand ?? {};
  const bAliases = brandAliases(set);
  const bDomains = new Set<string>((brand.domains ?? []).map((d: string) => d.toLowerCase()));
  const comps: Ent[] = set.competitors ?? [];
  const cDomains = new Set<string>(comps.flatMap((c) => (c.domains ?? []).map((d) => d.toLowerCase())));
  const primary = (set.primary_domain ?? "").toLowerCase();

  const tag = (c: Cit, anchored: boolean) => {
    const d = host(c.url, c.title);
    return { url: c.url, domain: d, title: c.title ?? null, rank: c.rank, anchored,
             is_brand: bDomains.has(d), is_primary: d === primary && !!d, is_competitor: cDomains.has(d) };
  };
  const anchoredCits = res.cits.map((c) => tag(c, true));
  const retrieved = res.srcs.map((c) => tag(c, false));
  const all = [...anchoredCits, ...retrieved];

  const bMent = count(text, bAliases);
  const cMent = comps.map((c) => count(text, c.aliases?.length ? c.aliases : [c.name]));
  const total = bMent + cMent.reduce((a, b) => a + b, 0);

  const brandCit = anchoredCits.find((c) => c.is_brand);
  const compCitedNames = comps
    .filter((c) => (c.domains ?? []).some((d) =>
      anchoredCits.some((x) => x.is_competitor && x.domain === d.toLowerCase())))
    .map((c) => c.name);

  return {
    mentioned: bMent > 0, mention_count: bMent,
    cited: !!brandCit, cited_primary: anchoredCits.some((c) => c.is_primary),
    source_present: all.some((c) => c.is_brand),
    position: brandCit?.rank ?? null, url_cited: brandCit?.url ?? null,
    competitor_mentioned: cMent.some((n) => n > 0),
    competitor_cited: anchoredCits.some((c) => c.is_competitor),
    competitors_cited: compCitedNames,
    competitors_mentioned: comps.filter((_, i) => cMent[i] > 0).map((c) => c.name),
    share_of_voice: total ? Number((bMent / total).toFixed(4)) : 0,
    sentiment: sentiment(text, bAliases),
    citations: all,
  };
}

type Scored = ReturnType<typeof score>;

// One row from one or more samples of the same (prompt, engine) pair. Used by
// both the one-at-a-time path and the batch path so they score identically.
function buildRow(base: any, S: Scored[], answer: string) {
  const n = S.length;
  const share = (f: (s: Scored) => boolean) =>
    Number((S.filter(f).length / n).toFixed(4));
  const mention_share = share((s) => s.mentioned);
  const cite_share = share((s) => s.cited);
  const source_share = share((s) => s.source_present);

  const cm = new Map<string, any>();
  for (const s of S) {
    for (const c of s.citations) {
      const prev = cm.get(c.url);
      if (!prev) cm.set(c.url, { ...c });
      else {
        if (c.anchored) prev.anchored = true;
        if (c.rank < prev.rank) prev.rank = c.rank;
      }
    }
  }
  const positions = S.map((s) => s.position).filter((p): p is number => p != null);
  const sentiments = S.map((s) => s.sentiment);
  const modal = sentiments.sort((a, b) =>
    sentiments.filter((v) => v === b).length - sentiments.filter((v) => v === a).length)[0];

  return {
    ...base, raw_response: answer, error: null,
    repeats: n, mention_share, cite_share, source_share,
    mentioned: mention_share >= 0.5,
    cited: cite_share >= 0.5,
    source_present: source_share >= 0.5,
    cited_primary: share((s) => s.cited_primary) >= 0.5,
    mention_count: Math.round(S.reduce((a, s) => a + s.mention_count, 0) / n),
    share_of_voice: Number((S.reduce((a, s) => a + s.share_of_voice, 0) / n).toFixed(4)),
    position: positions.length ? Math.min(...positions) : null,
    url_cited: S.find((s) => s.url_cited)?.url_cited ?? null,
    competitor_mentioned: S.some((s) => s.competitor_mentioned),
    competitor_cited: S.some((s) => s.competitor_cited),
    competitors_mentioned: [...new Set(S.flatMap((s) => s.competitors_mentioned))],
    competitors_cited: [...new Set(S.flatMap((s) => s.competitors_cited))],
    sentiment: modal ?? "n/a",
    citations: [...cm.values()],
  };
}
const errorRow = (base: any, err: string, set: any) => ({
  ...base, raw_response: "", error: err, repeats: 0,
  ...score({ answer: "", cits: [], srcs: [] }, set),
});

/* --- work --- */
async function pool<T>(items: T[], n: number, fn: (t: T) => Promise<void>) {
  let i = 0;
  await Promise.all(Array.from({ length: Math.min(n, items.length) }, async () => {
    while (i < items.length) await fn(items[i++]);
  }));
}

type Task = { p: any; e: string };
type BatchOut = { pending: number; written: number; errors: number; note: string; fallback?: Task[] };

// Anthropic questions for one run through the Message Batches API.
//   no open batch for the run -> submit one with every pending question
//   open, in progress         -> check it (at most once a minute)
//   open, ended               -> read the results, score, write, log usage
// Rows are dated with the run's start day, so answers that arrive after
// midnight UTC still land in the same report window as the rest of the pass.
async function anthropicBatch(set: any, runId: number, runDay: string, tasks: Task[],
                              setReps: number, model: string, jobId: number | null,
                              write: (row: any) => Promise<void>): Promise<BatchOut> {
  const k = providerKey("anthropic");
  const { data: open } = await db.from("ai_batches").select("*")
    .eq("run_id", runId).in("status", ["in_progress", "ended"])
    .order("id", { ascending: false }).limit(1).maybeSingle();

  if (!k) {
    return open ? { pending: tasks.length, written: 0, errors: 0, note: "no anthropic key" }
                : { pending: 0, written: 0, errors: 0, note: "no anthropic key", fallback: tasks };
  }
  const H = { "x-api-key": k, "anthropic-version": "2023-06-01" };
  const useModel = model || ANTHROPIC_DEFAULT_MODEL;
  const nowIso = () => new Date().toISOString();

  if (!open) {
    const requests: any[] = [];
    for (const t of tasks) {
      const reps = isBranded(t.p, set) ? 1 : setReps;
      for (let i = 0; i < reps; i++) {
        requests.push({ custom_id: `p${t.p.id}_r${i}`, params: anthropicParams(t.p.prompt, useModel) });
      }
    }
    let b: any;
    try {
      b = await post(ANTHROPIC_BATCHES, H, { requests }, 60_000, 2);
    } catch (e) {
      // The batch never started, so nothing is billed; ask these one at a time instead.
      return { pending: 0, written: 0, errors: 0, fallback: tasks,
               note: `batch submit failed, asked one at a time: ${String((e as Error).message ?? e).slice(0, 200)}` };
    }
    const { error } = await db.from("ai_batches").insert({
      provider: "anthropic", batch_id: b.id, run_id: runId, set_id: set.id, client_id: set.client_id,
      job_id: jobId, model: useModel, requests: requests.length, prompts: tasks.map((t) => t.p.id),
      status: "in_progress", request_counts: b.request_counts ?? null, checked_at: nowIso(),
    });
    if (error) throw new Error(`ai_batches insert for ${b.id}: ${error.message}`);
    return { pending: tasks.length, written: 0, errors: 0,
             note: `submitted ${b.id} (${requests.length} requests)` };
  }

  let row: any = open;
  if (row.status === "in_progress") {
    if (row.checked_at && Date.now() - Date.parse(row.checked_at) < BATCH_POLL_MS) {
      return { pending: tasks.length, written: 0, errors: 0, note: `waiting on ${row.batch_id}` };
    }
    const st = JSON.parse(await getText(`${ANTHROPIC_BATCHES}/${row.batch_id}`, H));
    const upd: Record<string, unknown> = { checked_at: nowIso(), request_counts: st.request_counts ?? null };
    if (st.processing_status === "ended") {
      Object.assign(upd, { status: "ended", ended_at: st.ended_at ?? nowIso(), results_url: st.results_url ?? null });
    }
    await db.from("ai_batches").update(upd).eq("id", row.id);
    if (st.processing_status !== "ended") {
      return { pending: tasks.length, written: 0, errors: 0,
               note: `waiting on ${row.batch_id}: ${JSON.stringify(st.request_counts ?? {})}` };
    }
    row = { ...row, ...upd };
  }

  // Ended: read every result line and group the samples by prompt.
  const text = await getText(row.results_url || `${ANTHROPIC_BATCHES}/${row.batch_id}/results`, H, 60_000);
  const byPrompt = new Map<number, { msgs: any[]; errs: string[] }>();
  for (const line of text.split("\n")) {
    if (!line.trim()) continue;
    let o: any;
    try { o = JSON.parse(line); } catch { continue; }
    const m = /^p(\d+)_r(\d+)$/.exec(String(o.custom_id ?? ""));
    if (!m) continue;
    const pid = Number(m[1]);
    const e = byPrompt.get(pid) ?? { msgs: [], errs: [] };
    const r = o.result ?? {};
    if (r.type === "succeeded" && r.message) e.msgs.push(r.message);
    else if (r.type === "errored") {
      const er = r.error?.error ?? r.error ?? {};
      e.errs.push(`batch errored: ${er.type ?? ""} ${er.message ?? ""}`.trim().slice(0, 400));
    } else e.errs.push(`batch ${r.type ?? "missing result"}`);
    byPrompt.set(pid, e);
  }

  const pending = new Map(tasks.map((t) => [t.p.id, t]));
  let written = 0, errors = 0;
  for (const [pid, e] of byPrompt) {
    const t = pending.get(pid);
    if (!t) continue; // prompt switched off, or already written, since the batch went in
    const base = {
      run_id: runId, set_id: set.id, client_id: set.client_id,
      prompt_id: pid, prompt_ref: t.p.prompt_ref, day: runDay, engine: "anthropic", model: useModel,
    };
    const S: Scored[] = [];
    let answer = "";
    for (const msg of e.msgs) {
      await logUsage("anthropic", useModel, msg,
        { batch: true, batch_id: row.batch_id }, { batch_id: row.batch_id });
      const pr = parseAnthropic(msg);
      if (!answer) answer = pr.answer;
      S.push(score(pr, set));
    }
    if (S.length) await write(buildRow(base, S, answer));
    else { errors++; await write(errorRow(base, e.errs[0] ?? "no samples", set)); }
    written++;
    pending.delete(pid);
  }
  await db.from("ai_batches").update({ status: "written", written_at: nowIso(), rows_written: written })
    .eq("id", row.id);

  // Anything still pending (a prompt added after the batch went in) gets a new
  // batch on the next drain.
  return { pending: pending.size, written, errors, note: `collected ${row.batch_id}` };
}

async function runSet(setSlug: string, started: number, jobId: number | null, allowBatch: boolean) {
  const pairCutoff = started + PAIR_START_MS;
  const repeatCutoff = started + REPEAT_START_MS;

  const { data: set } = await db.from("ai_prompt_sets").select("*").eq("set_slug", setSlug).single();
  if (!set) return { set: setSlug, error: "set not found" };
  usageClient = set.client_id ?? null;

  const { data: prompts } = await db.from("ai_prompts")
    .select("id,prompt,prompt_ref,engines,sort_order,branded").eq("set_id", set.id).eq("active", true)
    .order("sort_order", { ascending: true });
  if (!prompts?.length) return { set: setSlug, error: "no active prompts" };

  const { data: runId } = await db.rpc("ai_run_open", { p_set_id: set.id, p_client_id: set.client_id });

  const { data: done } = await db.from("fact_ai_visibility")
    .select("prompt_id,engine").eq("run_id", runId);
  const seen = new Set((done ?? []).map((d: any) => `${d.prompt_id}|${d.engine}`));

  const platforms: string[] = set.platforms?.length ? set.platforms : ["perplexity"];
  const models: Record<string, string> = set.models ?? {};
  const day = new Date().toISOString().slice(0, 10);

  const todo: Task[] = [];
  const skipped = new Set<string>();
  for (const p of prompts) {
    for (const raw of (p.engines?.length ? p.engines : platforms)) {
      const e = engineOf(raw);
      if (!e || !ENGINES[e]) { skipped.add(String(raw)); continue; }
      if (!seen.has(`${p.id}|${e}`)) todo.push({ p, e });
    }
  }

  let written = 0, errors = 0, ranOut = false;
  // One row per round trip: a row held in a buffer is a paid answer that a
  // wall-clock kill throws away.
  const write = async (row: any) => {
    const { error } = await db.rpc("ai_ingest", { p_rows: [row] });
    if (error) throw new Error(`ai_ingest: ${error.message}`);
    written++;
  };

  const setReps = Math.min(Math.max(Number(set.repeats ?? 1), 1), 5);

  // Anthropic through a batch when the switch says so, or when this run already
  // has a batch out (that batch is always finished, whatever the switch says).
  let syncTodo = todo;
  let batch: BatchOut | null = null;
  const anthTasks = todo.filter((t) => t.e === "anthropic");
  if (anthTasks.length) {
    const { count: openCount } = await db.from("ai_batches").select("id", { count: "exact", head: true })
      .eq("run_id", runId).in("status", ["in_progress", "ended"]);
    if ((openCount ?? 0) > 0 || (allowBatch && await batchEnabled(setSlug))) {
      syncTodo = todo.filter((t) => t.e !== "anthropic");
      const { data: runRow } = await db.from("ai_runs").select("started_at").eq("id", runId).single();
      const runDay = String(runRow?.started_at ?? new Date().toISOString()).slice(0, 10);
      try {
        batch = await anthropicBatch(set, runId, runDay, anthTasks, setReps,
                                     models.anthropic ?? "", jobId, write);
      } catch (e) {
        batch = { pending: anthTasks.length, written: 0, errors: 0,
                  note: `batch check failed, will retry: ${String((e as Error).message ?? e).slice(0, 200)}` };
      }
      errors += batch.errors;
      if (batch.fallback?.length) syncTodo = [...syncTodo, ...batch.fallback];
    }
  }
  const batchWritten = batch?.written ?? 0;

  await pool(syncTodo, CONC, async (t) => {
    if (Date.now() > pairCutoff) { ranOut = true; return; }
    const branded = isBranded(t.p, set);
    const model = t.e === "perplexity" && branded
      ? PERPLEXITY_BRANDED_MODEL : (models[t.e] ?? "");
    const reps = branded ? 1 : setReps;

    const S: Scored[] = [];
    let answer = "", firstErr: string | null = null, timedOut = false;
    for (let i = 0; i < reps; i++) {
      if (i > 0 && Date.now() > repeatCutoff) { ranOut = true; break; }
      let r: PR;
      try { r = await ENGINES[t.e](t.p.prompt, model); }
      catch (e) { r = { answer: "", cits: [], srcs: [], error: String((e as Error).message ?? e).slice(0, 400) }; }
      if (r.error) {
        if (r.error === OUT_OF_TIME || Date.now() >= hardStop - 5000) timedOut = true;
        firstErr = firstErr ?? r.error;
        continue;
      }
      if (!answer) answer = r.answer;
      S.push(score(r, set));
    }

    const base = {
      run_id: runId, set_id: set.id, client_id: set.client_id,
      prompt_id: t.p.id, prompt_ref: t.p.prompt_ref, day, engine: t.e,
      model: model || t.e,
    };

    if (!S.length) {
      // Our own deadline, not the provider's failure: leave the pair for the
      // next drain rather than recording an error that ai_repair must clear.
      if (timedOut) { ranOut = true; return; }
      errors++;
      await write(errorRow(base, firstErr ?? "no samples", set));
      return;
    }
    await write(buildRow(base, S, answer));
  });

  const syncRemaining = Math.max(syncTodo.length - (written - batchWritten), 0);
  const batchPending = batch?.pending ?? 0;
  const remaining = syncRemaining + batchPending;
  if (!ranOut && remaining <= 0) {
    await db.from("ai_runs").update({ finished_at: new Date().toISOString() }).eq("id", runId);
  }
  return { set: setSlug, run_id: runId, queued: todo.length, written, errors,
           remaining, yielded: ranOut, job: jobId,
           // Only the batch is outstanding: nothing for this drain to do but wait.
           waiting: !ranOut && syncRemaining === 0 && batchPending > 0,
           batch: batch ? { pending: batch.pending, written: batch.written, errors: batch.errors, note: batch.note }
                        : undefined,
           repeats: setReps, skipped_engines: [...skipped] };
}

/* --- handler --- */
Deno.serve(async (req: Request) => {
  const started = Date.now();
  hardStop = started + HARD_STOP_MS;
  batchSetting = null;

  const { data: cfg } = await db.from("internal_config").select("value").eq("key", "collector_token").single();
  if (req.headers.get("x-collector-token") !== cfg?.value) return json({ error: "forbidden" }, 403);

  let body: any = {};
  try { body = await req.json(); } catch { /* empty */ }
  const mode = body.mode ?? "drain";

  try {
    if (mode === "enqueue") {
      const { data, error } = await db.rpc("ai_enqueue",
        { p_slugs: body.sets ?? null, p_priority: body.priority ?? 5 });
      return error ? json({ error: error.message }, 500) : json(data);
    }

    if (mode === "run") {
      if (!body.set) return json({ error: "set required" }, 400);
      return json(await runSet(body.set, started, null, body.batch === true));
    }

    // Drain: claim one job at a time. A job that is only waiting on its batch
    // stays claimed (so the next claim moves down the queue) and is handed back
    // at the end; jobs with real work count against the limit as before.
    const limit = Number(body.limit ?? 1);
    const out: any[] = [];
    const held: { id: number; written: number }[] = [];
    let claimed = 0, worked = 0;
    try {
      while (worked < limit && Date.now() <= started + PAIR_START_MS) {
        const { data: jobs, error } = await db.rpc("collector_claim_jobs", { p_source: "ai", p_limit: 1 });
        if (error) { if (!claimed) return json({ error: error.message }, 500); break; }
        if (!jobs?.length) break;
        const j = jobs[0];
        claimed++;
        try {
          const r: any = await runSet(j.target, started, j.id, true);
          if (r.error) {
            worked++;
            await db.from("collector_jobs").update({ status: "error", error: r.error, finished_at: new Date().toISOString() }).eq("id", j.id);
          } else if (r.waiting) {
            held.push({ id: j.id, written: r.written });
          } else if (r.yielded || (r.remaining ?? 0) > 0) {
            worked++;
            await db.rpc("ai_job_yield", { p_job_id: j.id, p_done: r.written });
          } else {
            worked++;
            await db.from("collector_jobs").update({
              status: "done", rows_written: (j.rows_written ?? 0) + r.written,
              days_done: (j.days_done ?? 0) + r.written, finished_at: new Date().toISOString(),
            }).eq("id", j.id);
          }
          out.push(r);
        } catch (e) {
          worked++;
          const msg = String((e as Error).message ?? e).slice(0, 500);
          await db.from("collector_jobs").update({ status: "pending", error: msg, claimed_at: null }).eq("id", j.id);
          out.push({ job: j.id, set: j.target, error: msg });
        }
      }
    } finally {
      for (const h of held) await db.rpc("ai_job_yield", { p_job_id: h.id, p_done: h.written });
    }
    if (!claimed) return json({ claimed: 0 });
    return json({ claimed, waiting: held.length, results: out });
  } catch (e) {
    return json({ error: String((e as Error).message ?? e) }, 500);
  }
});
