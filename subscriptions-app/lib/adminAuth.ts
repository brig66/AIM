/**
 * Minimal signed-cookie session for the single-operator /admin dashboard.
 * Not a general auth system on purpose — Brig is the only admin, so a
 * password check (ADMIN_PASSWORD) plus an HMAC-signed cookie is enough, and
 * it's simple enough that nothing here needs a database round trip.
 *
 * Uses Web Crypto (SubtleCrypto) rather than Node's `crypto` module so this
 * also works from the Edge middleware runtime.
 */

const COOKIE_NAME = "aim_admin_session";
const SESSION_TTL_SECONDS = 60 * 60 * 12; // 12 hours

function getSecret(): string {
  const secret = process.env.ADMIN_SESSION_SECRET;
  if (!secret) {
    throw new Error(
      "ADMIN_SESSION_SECRET is not set. Add it in Vercel's Environment Variables."
    );
  }
  return secret;
}

async function hmac(data: string, secret: string): Promise<string> {
  const enc = new TextEncoder();
  const key = await crypto.subtle.importKey(
    "raw",
    enc.encode(secret),
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"]
  );
  const sig = await crypto.subtle.sign("HMAC", key, enc.encode(data));
  return Buffer.from(sig).toString("base64url");
}

/** Build the signed cookie value for a freshly-authenticated admin session. */
export async function createAdminSessionCookie(): Promise<string> {
  const expires = Math.floor(Date.now() / 1000) + SESSION_TTL_SECONDS;
  const payload = `admin.${expires}`;
  const sig = await hmac(payload, getSecret());
  return `${payload}.${sig}`;
}

/** Verify a cookie value produced by createAdminSessionCookie. */
export async function verifyAdminSessionCookie(cookieValue: string): Promise<boolean> {
  try {
    const parts = cookieValue.split(".");
    if (parts.length !== 3) return false;
    const [role, expiresStr, sig] = parts;
    if (role !== "admin") return false;
    const expires = Number(expiresStr);
    if (!Number.isFinite(expires) || Date.now() / 1000 > expires) return false;
    const expected = await hmac(`${role}.${expiresStr}`, getSecret());
    return timingSafeEqual(expected, sig);
  } catch {
    return false;
  }
}

function timingSafeEqual(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let result = 0;
  for (let i = 0; i < a.length; i++) {
    result |= a.charCodeAt(i) ^ b.charCodeAt(i);
  }
  return result === 0;
}

export { COOKIE_NAME as ADMIN_COOKIE_NAME };
