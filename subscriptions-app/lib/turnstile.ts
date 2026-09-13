/**
 * Server-side verification of a Cloudflare Turnstile token submitted from the
 * signup form. If TURNSTILE_SECRET_KEY is unset (local dev before Brig has
 * created a Turnstile site), verification is skipped with a console warning
 * rather than blocking every signup attempt.
 */
export async function verifyTurnstile(token: string | undefined, remoteIp?: string): Promise<{ ok: boolean; reason?: string }> {
  const secret = process.env.TURNSTILE_SECRET_KEY;
  if (!secret) {
    // eslint-disable-next-line no-console
    console.warn("[turnstile] TURNSTILE_SECRET_KEY not set — skipping bot-check verification.");
    return { ok: true };
  }
  if (!token) {
    return { ok: false, reason: "missing_token" };
  }
  try {
    const body = new URLSearchParams();
    body.set("secret", secret);
    body.set("response", token);
    if (remoteIp) body.set("remoteip", remoteIp);

    const res = await fetch("https://challenges.cloudflare.com/turnstile/v0/siteverify", {
      method: "POST",
      body,
    });
    const data = (await res.json()) as { success: boolean; ["error-codes"]?: string[] };
    if (!data.success) {
      return { ok: false, reason: (data["error-codes"] || []).join(",") || "verification_failed" };
    }
    return { ok: true };
  } catch (err) {
    return { ok: false, reason: err instanceof Error ? err.message : "network_error" };
  }
}
