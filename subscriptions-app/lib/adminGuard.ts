import { cookies } from "next/headers";
import { verifyAdminSessionCookie, ADMIN_COOKIE_NAME } from "@/lib/adminAuth";

/** Defense-in-depth check for admin API routes, in addition to the middleware gate. */
export async function requireAdmin(): Promise<boolean> {
  const cookieStore = await cookies();
  const value = cookieStore.get(ADMIN_COOKIE_NAME)?.value;
  if (!value) return false;
  return verifyAdminSessionCookie(value);
}
