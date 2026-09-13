/**
 * Multi-engine query adapters, ported from
 * skills/aim-ai-visibility-tracker/scripts/aim_ai_visibility_tracker.py so the
 * subscription pipeline and the existing skill share the same provider logic
 * instead of diverging. Each adapter normalizes its provider's very different
 * response shape into a common ProviderResult.
 *
 * Env var names intentionally match the existing skill exactly:
 *   PERPLEXITY_API_KEY   OPENAI_API_KEY   GEMINI_API_KEY   ANTHROPIC_API_KEY
 */

export type Engine = "perplexity" | "openai" | "gemini" | "anthropic";

export interface Citation {
  url: string;
  title?: string | null;
  rank: number;
}

export interface ProviderResult {
  platform: Engine;
  model: string;
  answerText: string;
  citations: Citation[];
  error?: string;
}

function domainOf(url: string): string {
  try {
    const net = new URL(url).hostname.toLowerCase();
    return net.startsWith("www.") ? net.slice(4) : net;
  } catch {
    return "";
  }
}

function dedupCitations(cits: Citation[]): Citation[] {
  const seen = new Set<string>();
  const out: Citation[] = [];
  for (const c of [...cits].sort((a, b) => a.rank - b.rank)) {
    const u = (c.url || "").trim();
    if (u && !seen.has(u)) {
      seen.add(u);
      out.push(c);
    }
  }
  return out;
}

async function fetchJson(url: string, init: RequestInit, timeoutMs: number): Promise<any> {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const res = await fetch(url, { ...init, signal: controller.signal });
    if (!res.ok) {
      const text = await res.text().catch(() => "");
      throw new Error(`HTTP ${res.status} ${res.statusText}: ${text.slice(0, 500)}`);
    }
    return await res.json();
  } finally {
    clearTimeout(timer);
  }
}

// ---- Perplexity (Sonar) ----------------------------------------------------
async function queryPerplexity(prompt: string, model: string): Promise<ProviderResult> {
  const key = requireKey("PERPLEXITY_API_KEY");
  const d = await fetchJson(
    "https://api.perplexity.ai/chat/completions",
    {
      method: "POST",
      headers: { Authorization: `Bearer ${key}`, "Content-Type": "application/json" },
      body: JSON.stringify({
        model: model || "sonar-pro",
        messages: [{ role: "user", content: prompt }],
      }),
    },
    90_000
  );
  const answer = d?.choices?.[0]?.message?.content ?? "";
  const cits: Citation[] = [];
  let rank = 0;
  for (const u of d?.citations ?? []) {
    rank += 1;
    cits.push({ url: typeof u === "string" ? u : u?.url ?? "", rank });
  }
  for (const s of d?.search_results ?? []) {
    if (s?.url) {
      rank += 1;
      cits.push({ url: s.url, title: s.title, rank });
    }
  }
  return { platform: "perplexity", model: model || "sonar-pro", answerText: answer, citations: dedupCitations(cits) };
}

// ---- OpenAI (ChatGPT, Responses API + web_search) --------------------------
async function queryOpenAI(prompt: string, model: string): Promise<ProviderResult> {
  const key = requireKey("OPENAI_API_KEY");
  const d = await fetchJson(
    "https://api.openai.com/v1/responses",
    {
      method: "POST",
      headers: { Authorization: `Bearer ${key}`, "Content-Type": "application/json" },
      body: JSON.stringify({
        model: model || "gpt-4.1",
        tools: [{ type: "web_search" }],
        input: prompt,
      }),
    },
    120_000
  );
  const answerParts: string[] = [];
  const cits: Citation[] = [];
  let rank = 0;
  for (const item of d?.output ?? []) {
    for (const block of item?.content ?? []) {
      if (block?.type === "output_text" || block?.type === "text") {
        answerParts.push(block.text ?? "");
        for (const ann of block?.annotations ?? []) {
          if (ann?.type === "url_citation" && ann?.url) {
            rank += 1;
            cits.push({ url: ann.url, title: ann.title, rank });
          }
        }
      }
    }
  }
  const answer = answerParts.join("") || d?.output_text || "";
  return { platform: "openai", model: model || "gpt-4.1", answerText: answer, citations: dedupCitations(cits) };
}

// ---- Google (Gemini, generateContent + google_search grounding) ------------
async function queryGemini(prompt: string, model: string): Promise<ProviderResult> {
  const key = requireKey("GEMINI_API_KEY");
  const m = model || "gemini-2.5-flash";
  const d = await fetchJson(
    `https://generativelanguage.googleapis.com/v1beta/models/${m}:generateContent?key=${key}`,
    {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        contents: [{ parts: [{ text: prompt }] }],
        tools: [{ google_search: {} }],
      }),
    },
    120_000
  );
  const cand = d?.candidates?.[0] ?? {};
  const answer = (cand?.content?.parts ?? []).map((p: any) => p?.text ?? "").join("");
  const cits: Citation[] = [];
  const gm = cand?.groundingMetadata ?? {};
  (gm?.groundingChunks ?? []).forEach((chunk: any, i: number) => {
    const web = chunk?.web ?? {};
    if (web?.uri) cits.push({ url: web.uri, title: web.title, rank: i + 1 });
  });
  return { platform: "gemini", model: m, answerText: answer, citations: dedupCitations(cits) };
}

// ---- Anthropic (Claude, Messages API + web_search tool) --------------------
async function queryAnthropic(prompt: string, model: string): Promise<ProviderResult> {
  const key = requireKey("ANTHROPIC_API_KEY");
  const m = model || "claude-sonnet-4-6";
  const d = await fetchJson(
    "https://api.anthropic.com/v1/messages",
    {
      method: "POST",
      headers: {
        "x-api-key": key,
        "anthropic-version": "2023-06-01",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        model: m,
        max_tokens: 1024,
        tools: [{ type: "web_search_20250305", name: "web_search" }],
        messages: [{ role: "user", content: prompt }],
      }),
    },
    120_000
  );
  const answerParts: string[] = [];
  const cits: Citation[] = [];
  let rank = 0;
  for (const block of d?.content ?? []) {
    if (block?.type === "text") {
      answerParts.push(block.text ?? "");
      for (const c of block?.citations ?? []) {
        if (c?.url) {
          rank += 1;
          cits.push({ url: c.url, title: c.title, rank });
        }
      }
    } else if (block?.type === "web_search_tool_result") {
      for (const res of block?.content ?? []) {
        if (res?.url) {
          rank += 1;
          cits.push({ url: res.url, title: res.title, rank });
        }
      }
    }
  }
  return { platform: "anthropic", model: m, answerText: answerParts.join(""), citations: dedupCitations(cits) };
}

function requireKey(name: string): string {
  const v = process.env[name];
  if (!v) throw new Error(`${name} is not set`);
  return v;
}

const PROVIDERS: Record<Engine, (prompt: string, model: string) => Promise<ProviderResult>> = {
  perplexity: queryPerplexity,
  openai: queryOpenAI,
  gemini: queryGemini,
  anthropic: queryAnthropic,
};

export async function queryEngine(engine: Engine, prompt: string, model = ""): Promise<ProviderResult> {
  try {
    return await PROVIDERS[engine](prompt, model);
  } catch (err) {
    return {
      platform: engine,
      model: model || engine,
      answerText: "",
      citations: [],
      error: err instanceof Error ? err.message : String(err),
    };
  }
}

export function extractDomain(url: string): string {
  return domainOf(url);
}

/** Mock provider so the pipeline can be exercised end to end without live API keys. */
export function queryMock(prompt: string, engine: Engine, brandDomain: string): ProviderResult {
  // Deterministic-ish pseudo-random from the prompt text, mirrors the skill's mock.
  let seed = 0;
  for (const ch of prompt + engine) seed = (seed * 31 + ch.charCodeAt(0)) >>> 0;
  const rand = () => {
    seed = (seed * 1664525 + 1013904223) >>> 0;
    return seed / 0xffffffff;
  };
  const mentionBrand = rand() < 0.62;
  const citeBrand = mentionBrand && rand() < 0.55;
  const cits: Citation[] = [];
  let rank = 0;
  if (citeBrand && brandDomain) {
    rank += 1;
    cits.push({ url: `https://${brandDomain}/`, title: "Brand site", rank });
  }
  if (rand() < 0.4) {
    rank += 1;
    cits.push({ url: "https://example-review-site.com/review/", title: "Review site", rank });
  }
  const answer = mentionBrand
    ? "This company is frequently recommended as a strong option in its category."
    : "There are several options on the market worth considering.";
  return { platform: engine, model: `mock-${engine}`, answerText: answer, citations: cits };
}
