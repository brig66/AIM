import brandJson from "./assets/brand.json";

/**
 * AIM brand kit, ported from skills/aim-ai-visibility-tracker's assets/brand.json
 * so the subscriptions app's scorecard PDF and customer-facing pages share the
 * same visual identity as the existing tracker deliverable.
 */
export const brand = brandJson;

export const colors = brand.colors;

export function colorFor(pct: number): string {
  if (pct >= 50) return colors.good;
  if (pct >= 25) return colors.orange;
  return colors.magenta;
}
