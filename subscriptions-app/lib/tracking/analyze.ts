/**
 * Brand-mention / citation analysis, ported from
 * skills/aim-ai-visibility-tracker/scripts/aim_ai_visibility_tracker.py
 * (`_mentions`, `_sentiment`, `analyze`) — same word-boundary matching and
 * simple lexicon-based sentiment so scores from this pipeline are comparable
 * to the existing skill's reports.
 */
import { extractDomain, type ProviderResult } from "./engines";

const POS = new Set(["best", "top", "leading", "recommended", "excellent", "premium", "trusted", "superior", "ideal"]);
const NEG = new Set(["expensive", "overpriced", "poor", "limited", "drawback", "downside", "complaint", "issue", "lacks"]);

function escapeRegExp(s: string): string {
  return s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function wordRegex(word: string): RegExp {
  return new RegExp(`\\b${escapeRegExp(word)}\\b`, "gi");
}

export function countMentions(text: string, aliases: string[]): number {
  let total = 0;
  for (const alias of aliases) {
    const matches = text.match(wordRegex(alias));
    total += matches ? matches.length : 0;
  }
  return total;
}

function splitSentences(text: string): string[] {
  return text.split(/(?<=[.!?])\s+/);
}

export function sentimentFor(text: string, aliases: string[]): "n/a" | "positive" | "negative" | "neutral" {
  if (countMentions(text, aliases) === 0) return "n/a";
  const relevant = splitSentences(text).filter((s) => aliases.some((a) => wordRegex(a).test(s)));
  const blob = relevant.join(" ").toLowerCase();
  let pos = 0;
  let neg = 0;
  for (const w of POS) if (blob.includes(w)) pos += 1;
  for (const w of NEG) if (blob.includes(w)) neg += 1;
  if (pos > neg) return "positive";
  if (neg > pos) return "negative";
  return "neutral";
}

export interface AnalyzeInput {
  brandAliases: string[];
  brandDomains: string[];
  primaryDomain: string;
}

export interface AnalyzeResult {
  brandMentioned: boolean;
  brandMentionCount: number;
  brandCited: boolean;
  citedPrimary: boolean;
  citedBrandDomains: string[];
  sentiment: "n/a" | "positive" | "negative" | "neutral";
}

/**
 * Single-brand version of the skill's `analyze()` — the subscriptions product
 * tracks one client brand per prompt set (no competitor roster to score share
 * of voice against), so this omits the competitor/SOV fields that need a
 * configured competitor list.
 */
export function analyze(res: ProviderResult, input: AnalyzeInput): AnalyzeResult {
  const text = res.answerText || "";
  const citedDomains = res.citations.map((c) => extractDomain(c.url)).filter(Boolean);
  const brandMentionCount = countMentions(text, input.brandAliases);
  const brandDomainSet = new Set(input.brandDomains);
  const citedBrandDomains = citedDomains.filter((d) => brandDomainSet.has(d));

  return {
    brandMentioned: brandMentionCount > 0,
    brandMentionCount,
    brandCited: citedBrandDomains.length > 0,
    citedPrimary: input.primaryDomain ? citedDomains.includes(input.primaryDomain) : false,
    citedBrandDomains,
    sentiment: sentimentFor(text, input.brandAliases),
  };
}
