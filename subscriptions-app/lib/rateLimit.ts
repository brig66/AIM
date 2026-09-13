/**
 * Best-effort in-memory rate limiter for the public /api/signup endpoint.
 *
 * LIMITATION: Vercel serverless functions are not guaranteed to be the same
 * warm instance between requests — under real traffic this Map can reset or
 * be split across multiple concurrent instances, so a determined attacker can
 * exceed the nominal limit. This is a "blunt abuse, not stop a determined
 * attacker" control. Combined with Turnstile (bot check) and Stripe Radar
 * (fraud on the actual charge), it's enough for a small self-service signup
 * flow. If real abuse shows up, swap this for a Supabase-table- or
 * Upstash-Redis-backed limiter shared across instances.
 */

type Bucket = { count: number; resetAt: number };

const WINDOW_MS = 10 * 60 * 1000; // 10 minutes
const MAX_ATTEMPTS = 5;

const buckets = new Map<string, Bucket>();

export function checkRateLimit(key: string): { allowed: boolean; retryAfterMs?: number } {
  const now = Date.now();
  const existing = buckets.get(key);

  if (!existing || existing.resetAt <= now) {
    buckets.set(key, { count: 1, resetAt: now + WINDOW_MS });
    return { allowed: true };
  }

  if (existing.count >= MAX_ATTEMPTS) {
    return { allowed: false, retryAfterMs: existing.resetAt - now };
  }

  existing.count += 1;
  return { allowed: true };
}

// Periodically forget stale buckets so this doesn't grow unbounded on a
// long-lived warm instance.
if (typeof setInterval !== "undefined") {
  setInterval(() => {
    const now = Date.now();
    for (const [key, bucket] of buckets) {
      if (bucket.resetAt <= now) buckets.delete(key);
    }
  }, WINDOW_MS).unref?.();
}
