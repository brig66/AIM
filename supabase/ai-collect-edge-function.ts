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
//   - Claude web search capped at CLAUDE_MAX_SEARCHES per question.
//   - every result is written as soon as it is scored (was batches of 8), and
//     no call is started that could still be running when the platform kills
//     the function. Before this, a kill discarded up to seven paid results
//     plus whatever was in flight, and the next drain bought them again.
//   - a pair that runs out of time with no sample is left unwritten so the
//     next drain retries it, instead of being written as an error row.

const db = createClient(Deno.env.get("SUPABASE_URL")!,
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!, { auth: { persistSession: false } });

// Edge functions are cut off at ~150s. Every call has to be finished (or
// abandoned) before then, or its result is lost after it has been paid for.
const PAIR_START_MS = 75_000;    // no new (prompt, engine) pair after this
const REPEAT_START_MS = 100_000; // no further sample of a started pair after this
const HARD_STOP_MS = 140_000;    // every provider call is aborted by this
const CONC = 4;                  // parallel provider calls
const CLAUDE_MAX_SEARCHES = 2;

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

// Perplexity: neither `citations` nor `search_results` marks what the answer
// leaned on - both are the consulted set and overlap almost entirely. The
// anchoring signal is the inline [n] markers, which index into `citations`.
async function askPerplexity(prompt: string, model: string): Promise<PR> {
  const k = providerKey("perplexity");
  if (!k) return { answer: "", cits: [], srcs: [], error: "no perplexity key" };
  const d = await post("https://api.perplexity.ai/chat/completions",
    { Authorization: `Bearer ${k}` },
    { model: model || "sonar-pro", messages: [{ role: "user", content: prompt }] });
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

// Each web search is billed on its own and pulls its results into the input
// tokens, and uncapped the model will run several for one question. Two is
// enough to find the sources an answer leans on.
async function askAnthropic(prompt: string, model: string): Promise<PR> {
  const k = providerKey("anthropic");
  if (!k) return { answer: "", cits: [], srcs: [], error: "no anthropic key" };
  const d = await post("https://api.anthropic.com/v1/messages",
    { "x-api-key": k, "anthropic-version": "2023-06-01" },
    { model: model || "claude-sonnet-4-6", max_tokens: 1024,
      tools: [{ type: "web_search_20250305", name: "web_search", max_uses: CLAUDE_MAX_SEARCHES }],
      messages: [{ role: "user", content: prompt }] }, 120_000);
  const parts: string[] = [], cits: Cit[] = [], srcs: Cit[] = [];
  let rank = 0, srank = 0;
  for (const b of d.content ?? []) {
    if (b.type === "text") {
      parts.push(b.text ?? "");
      for (const c of b.citations ?? []) if (c.url) cits.push({ url: c.url, title: c.title, rank: ++rank });
    } else if (b.type === "web_search_tool_result") {
      for (const r of b.content ?? []) if (r?.url) srcs.push({ url: r.url, title: r.title, rank: ++srank });
    }
  }
  const C = dedup(cits);
  return { answer: parts.join(""), cits: C, srcs: dedup(srcs, C.length + 1, urlSet(C)) };
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
  .replace(/[    ]/g, " ");
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

/* --- work --- */
async function pool<T>(items: T[], n: number, fn: (t: T) => Promise<void>) {
  let i = 0;
  await Promise.all(Array.from({ length: Math.min(n, items.length) }, async () => {
    while (i < items.length) await fn(items[i++]);
  }));
}

async function runSet(setSlug: string, started: number, jobId: number | null) {
  const pairCutoff = started + PAIR_START_MS;
  const repeatCutoff = started + REPEAT_START_MS;

  const { data: set } = await db.from("ai_prompt_sets").select("*").eq("set_slug", setSlug).single();
  if (!set) return { set: setSlug, error: "set not found" };

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

  type Task = { p: any; e: string };
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

  await pool(todo, CONC, async (t) => {
    if (Date.now() > pairCutoff) { ranOut = true; return; }
    const model = models[t.e] ?? "";
    const reps = isBranded(t.p, set) ? 1 : setReps;

    const S: ReturnType<typeof score>[] = [];
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
      await write({ ...base, raw_response: "", error: firstErr ?? "no samples",
                    repeats: 0, ...score({ answer: "", cits: [], srcs: [] }, set) });
      return;
    }

    const n = S.length;
    const share = (f: (s: (typeof S)[number]) => boolean) =>
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

    await write({
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
    });
  });

  const remaining = todo.length - written;
  if (!ranOut && remaining <= 0) {
    await db.from("ai_runs").update({ finished_at: new Date().toISOString() }).eq("id", runId);
  }
  return { set: setSlug, run_id: runId, queued: todo.length, written, errors,
           remaining: Math.max(remaining, 0), yielded: ranOut, job: jobId,
           repeats: setReps, skipped_engines: [...skipped] };
}

/* --- handler --- */
Deno.serve(async (req: Request) => {
  const started = Date.now();
  hardStop = started + HARD_STOP_MS;

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
      return json(await runSet(body.set, started, null));
    }

    const { data: jobs, error } = await db.rpc("collector_claim_jobs",
      { p_source: "ai", p_limit: body.limit ?? 1 });
    if (error) return json({ error: error.message }, 500);
    if (!jobs?.length) return json({ claimed: 0 });

    const out: any[] = [];
    for (const j of jobs) {
      if (Date.now() > started + PAIR_START_MS) {
        await db.rpc("ai_job_yield", { p_job_id: j.id, p_done: 0 });
        out.push({ job: j.id, set: j.target, yielded: true, written: 0 });
        continue;
      }
      try {
        const r = await runSet(j.target, started, j.id);
        if (r.error) {
          await db.from("collector_jobs").update({ status: "error", error: r.error, finished_at: new Date().toISOString() }).eq("id", j.id);
        } else if (r.yielded || (r.remaining ?? 0) > 0) {
          await db.rpc("ai_job_yield", { p_job_id: j.id, p_done: r.written });
        } else {
          await db.from("collector_jobs").update({
            status: "done", rows_written: (j.rows_written ?? 0) + r.written,
            days_done: (j.days_done ?? 0) + r.written, finished_at: new Date().toISOString(),
          }).eq("id", j.id);
        }
        out.push(r);
      } catch (e) {
        const msg = String((e as Error).message ?? e).slice(0, 500);
        await db.from("collector_jobs").update({ status: "pending", error: msg, claimed_at: null }).eq("id", j.id);
        out.push({ job: j.id, set: j.target, error: msg });
      }
    }
    return json({ claimed: jobs.length, results: out });
  } catch (e) {
    return json({ error: String((e as Error).message ?? e) }, 500);
  }
});
