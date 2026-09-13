import { NextRequest, NextResponse } from "next/server";
import { createSupabaseServerClient, createSupabaseAdminClient } from "@/lib/supabase/server";

export const runtime = "nodejs";

/**
 * Landed on after a Supabase Auth magic-link / invite email is followed.
 * Exchanges the code for a session, then links this auth user to their
 * `clients` row by matching contact_email (the invite was sent to that
 * exact address in the checkout.session.completed webhook handler).
 */
export async function GET(req: NextRequest) {
  const code = req.nextUrl.searchParams.get("code");
  const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || req.nextUrl.origin;

  if (code) {
    const supabase = await createSupabaseServerClient();
    const { data, error } = await supabase.auth.exchangeCodeForSession(code);
    if (!error && data.user?.email) {
      const admin = createSupabaseAdminClient();
      await admin
        .from("clients")
        .update({ auth_user_id: data.user.id })
        .eq("contact_email", data.user.email.toLowerCase())
        .is("auth_user_id", null);
    }
  }

  return NextResponse.redirect(`${siteUrl}/portal`);
}
