/**
 * Aggregate metrics + a single 0-100 score per prompt slice, following the
 * branded/non-branded split introduced in
 * skills/aim-ai-visibility-tracker/branded-split.patch: a branded prompt
 * names the brand outright (AI cites a company back to itself almost every
 * time it's asked about by name), so blending branded and non-branded
 * prompts into one number hides how the brand does on the harder,
 * non-branded category questions. `tracking_prompts.is_branded` is the same
 * flag as the skill's `branded:` config field.
 */
import type { AnalyzeResult } from "./analyze";
import type { Engine } from "./engines";

export interface PromptOutcome {
  promptId: string;
  promptText: string;
  isBranded: boolean;
  engine: Engine;
  analysis: AnalyzeResult;
  citedOn?: string;
  error?: string;
}

export interface SliceMetrics {
  answers: number;
  mentionRate: number; // 0-100
  citationRate: number; // 0-100
  primaryDomainRate: number; // 0-100
  score: number; // 0-100, the number shown on the scorecard
}

function pct(n: number, d: number): number {
  return d === 0 ? 0 : Math.round((1000 * n) / d) / 10;
}

function slice(outcomes: PromptOutcome[]): SliceMetrics {
  const ok = outcomes.filter((o) => !o.error);
  const n = ok.length;
  const mentionRate = pct(ok.filter((o) => o.analysis.brandMentioned).length, n);
  const citationRate = pct(ok.filter((o) => o.analysis.brandCited).length, n);
  const primaryDomainRate = pct(ok.filter((o) => o.analysis.citedPrimary).length, n);
  // The headline score weights citation (AI actually sending users to the
  // brand's site) well above a bare mention, matching what the AIM tracker
  // report leads with.
  const score = Math.round((citationRate * 0.7 + mentionRate * 0.3) * 10) / 10;
  return { answers: n, mentionRate, citationRate, primaryDomainRate, score };
}

export interface RunMetrics {
  totalAnswers: number;
  overall: SliceMetrics;
  branded: SliceMetrics;
  nonbranded: SliceMetrics;
  byEngine: Record<string, SliceMetrics>;
  sentiment: Record<string, number>;
  errors: string[];
}

export function computeRunMetrics(outcomes: PromptOutcome[]): RunMetrics {
  const branded = outcomes.filter((o) => o.isBranded);
  const nonbranded = outcomes.filter((o) => !o.isBranded);
  const byEngine: Record<string, SliceMetrics> = {};
  for (const engine of new Set(outcomes.map((o) => o.engine))) {
    byEngine[engine] = slice(outcomes.filter((o) => o.engine === engine));
  }
  const sentiment: Record<string, number> = {};
  for (const o of outcomes) {
    if (!o.error && o.analysis.brandMentioned) {
      sentiment[o.analysis.sentiment] = (sentiment[o.analysis.sentiment] || 0) + 1;
    }
  }
  return {
    totalAnswers: outcomes.filter((o) => !o.error).length,
    overall: slice(outcomes),
    branded: slice(branded),
    nonbranded: slice(nonbranded),
    byEngine,
    sentiment,
    errors: outcomes.filter((o) => o.error).map((o) => `${o.engine}/${o.promptId}: ${o.error}`),
  };
}

/** Group per-prompt outcomes across engines, for the prompt-level detail table. */
export function groupByPrompt(outcomes: PromptOutcome[]) {
  const byId = new Map<string, { promptText: string; isBranded: boolean; outcomes: PromptOutcome[] }>();
  for (const o of outcomes) {
    if (!byId.has(o.promptId)) {
      byId.set(o.promptId, { promptText: o.promptText, isBranded: o.isBranded, outcomes: [] });
    }
    byId.get(o.promptId)!.outcomes.push(o);
  }
  return Array.from(byId.values()).map((row) => {
    const n = row.outcomes.filter((o) => !o.error).length;
    const cited = row.outcomes.filter((o) => !o.error && o.analysis.brandCited);
    const mentioned = row.outcomes.filter((o) => !o.error && o.analysis.brandMentioned);
    return {
      promptText: row.promptText,
      isBranded: row.isBranded,
      total: n,
      citedCount: cited.length,
      mentionedCount: mentioned.length,
      citedOnEngines: cited.map((o) => o.engine),
    };
  });
}
