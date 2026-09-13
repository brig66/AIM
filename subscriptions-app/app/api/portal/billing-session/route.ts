import { NextRequest, NextResponse } from "next/server";
import { createSupabaseServerClient } from "@/lib/supabase/server";
import { getStripe } from "@/lib/stripe";

export const runtime = "nodejs";

/**
 * Creates a Stripe Billing Portal session for the logged-in client so they
 * can update card details or cancel entirely, with zero involvement from
 * Brig. Requires a Supabase Auth session (checked here, not just relying on
 * the middleware, since this is a POST that mutates nothing in our DB but
 * does hand back a link to the customer's own Stripe billing portal).
 */
export async function POST(req: NextRequest) {
  const supabase = await createSupabaseServerClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();
  if (!user) {
    return NextResponse.json({ error: "Not signed in." }, { status: 401 });
  }

  const { data: client } = await supabase
    .from("clients")
    .select("*")
    .eq("auth_user_id", user.id)
    .maybeSingle();

  if (!client?.stripe_customer_id) {
    return NextResponse.json({ error: "No billing account found yet." }, { status: 404 });
  }

  try {
    const stripe = getStripe();
    const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || `https://${req.headers.get("host")}`;
    const session = await stripe.billingPortal.sessions.create({
      customer: client.stripe_customer_id,
      return_url: `${siteUrl}/portal`,
    });
    return NextResponse.json({ url: session.url });
  } catch (err) {
    const message = err instanceof Error ? err.message : "Stripe is not configured yet.";
    return NextResponse.json({ error: message }, { status: 500 });
  }
}
