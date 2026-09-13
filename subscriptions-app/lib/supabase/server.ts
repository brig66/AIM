import { createServerClient } from "@supabase/ssr";
import { createClient } from "@supabase/supabase-js";
import { cookies } from "next/headers";
import type { Database } from "./types";
import { SUPABASE_ANON_KEY, SUPABASE_URL } from "./env";

/**
 * Anon-key client for use in Server Components / Route Handlers, bound to the
 * incoming request's Supabase Auth cookies. RLS applies — this is how the
 * client portal reads/writes only its own client_id's rows.
 */
export async function createSupabaseServerClient() {
  const cookieStore = await cookies();
  return createServerClient<Database>(SUPABASE_URL, SUPABASE_ANON_KEY, {
    cookies: {
      getAll() {
        return cookieStore.getAll();
      },
      setAll(cookiesToSet) {
        try {
          for (const { name, value, options } of cookiesToSet) {
            cookieStore.set(name, value, options);
          }
        } catch {
          // Called from a Server Component that can't set cookies (no
          // response to attach them to) — middleware refreshes the session
          // instead, so this is safe to ignore.
        }
      },
    },
  });
}

/**
 * Service-role client. Bypasses RLS entirely — only ever import this from
 * server-only code (API routes, cron, webhooks), never from anything that
 * ships to the browser. Requires SUPABASE_SERVICE_ROLE_KEY.
 */
export function createSupabaseAdminClient() {
  const serviceRoleKey = process.env.SUPABASE_SERVICE_ROLE_KEY;
  if (!serviceRoleKey) {
    throw new Error(
      "SUPABASE_SERVICE_ROLE_KEY is not set. Add it in Vercel's Environment Variables."
    );
  }
  return createClient<Database>(SUPABASE_URL, serviceRoleKey, {
    auth: { autoRefreshToken: false, persistSession: false },
  });
}
