import { NextRequest, NextResponse } from "next/server";
import { createSupabaseAdminClient } from "@/lib/supabase/server";
import { verifyTurnstile } from "@/lib/turnstile";
import { checkRateLimit } from "@/lib/rateLimit";
import { getStripe, priceIdForPlan } from "@/lib/stripe";

export const runtime = "nodejs";

interface SignupBody {
  companyName: string;
  websiteDomain: string;
  contactName: string;
  contactEmail: string;
  plan: "monthly" | "weekly";
  prompts: { text: string; isBranded: boolean }[];
  turnstileToken?: string;
}

function normalizeDomain(input: string): string {
  return input
    .trim()
    .replace(/^https?:\/\//i, "")
    .replace(/^www\./i, "")
    .replace(/\/.*$/, "")
    .toLowerCase();
}

export async function POST(req: NextRequest) {
  let body: SignupBody;
  try {
    body = await req.json();
  } catch {
    return NextResponse.json({ error: "Invalid request body." }, { status: 400 });
  }

  const { companyName, websiteDomain, contactName, contactEmail, plan, prompts, turnstileToken } = body;

  if (!companyName?.trim() || !websiteDomain?.trim() || !contactName?.trim() || !contactEmail?.trim()) {
    return NextResponse.json({ error: "Missing required fields." }, { status: 400 });
  }
  if (plan !== "monthly" && plan !== "weekly") {
    return NextResponse.json({ error: "Invalid plan." }, { status: 400 });
  }
  if (!Array.isArray(prompts) || prompts.filter((p) => p.text?.trim()).length === 0) {
    return NextResponse.json({ error: "Add at least one tracking prompt." }, { status: 400 });
  }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactEmail)) {
    return NextResponse.json({ error: "Invalid email address." }, { status: 400 });
  }

  // Best-effort abuse control — see lib/rateLimit.ts for the serverless caveat.
  const ip = req.headers.get("x-forwarded-for")?.split(",")[0]?.trim() || "unknown";
  const rateLimitKey = `${ip}:${contactEmail.toLowerCase()}`;
  const rl = checkRateLimit(rateLimitKey);
  if (!rl.allowed) {
    return NextResponse.json(
      { error: "Too many signup attempts. Please try again in a few minutes." },
      { status: 429 }
    );
  }

  const turnstile = await verifyTurnstile(turnstileToken, ip !== "unknown" ? ip : undefined);
  if (!turnstile.ok) {
    return NextResponse.json({ error: "Bot verification failed. Please try again." }, { status: 400 });
  }

  const supabase = createSupabaseAdminClient();

  // A signup that never completes checkout leaves behind a 'pending' client
  // row; re-signing up with the same email reuses & updates that row rather
  // than erroring, so an abandoned checkout can just be retried.
  const { data: existingClient } = await supabase
    .from("clients")
    .select("id,status")
    .eq("contact_email", contactEmail.toLowerCase())
    .maybeSingle();

  if (existingClient && existingClient.status !== "pending") {
    return NextResponse.json(
      { error: "An account with this email already exists. Log in to the client portal instead." },
      { status: 409 }
    );
  }

  const domain = normalizeDomain(websiteDomain);

  let clientId: string;
  if (existingClient) {
    clientId = existingClient.id;
    const { error: updateError } = await supabase
      .from("clients")
      .update({
        company_name: companyName.trim(),
        website_domain: domain,
        contact_name: contactName.trim(),
        plan,
      })
      .eq("id", clientId);
    if (updateError) {
      return NextResponse.json({ error: "Could not update signup." }, { status: 500 });
    }
    await supabase.from("tracking_prompts").delete().eq("client_id", clientId);
  } else {
    const { data: newClient, error: insertError } = await supabase
      .from("clients")
      .insert({
        company_name: companyName.trim(),
        website_domain: domain,
        contact_name: contactName.trim(),
        contact_email: contactEmail.toLowerCase(),
        plan,
        status: "pending",
      })
      .select()
      .single();
    if (insertError || !newClient) {
      return NextResponse.json({ error: "Could not create account. Please try again." }, { status: 500 });
    }
    clientId = newClient.id;
  }

  const promptRows = prompts
    .filter((p) => p.text?.trim())
    .map((p) => ({ client_id: clientId, prompt_text: p.text.trim(), is_branded: Boolean(p.isBranded), active: true }));
  const { error: promptsError } = await supabase.from("tracking_prompts").insert(promptRows);
  if (promptsError) {
    return NextResponse.json({ error: "Could not save tracking prompts." }, { status: 500 });
  }

  try {
    const stripe = getStripe();
    const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || `https://${req.headers.get("host")}`;
    const session = await stripe.checkout.sessions.create({
      mode: "subscription",
      client_reference_id: clientId,
      customer_email: contactEmail,
      line_items: [{ price: priceIdForPlan(plan), quantity: 1 }],
      success_url: `${siteUrl}/signup/success?session_id={CHECKOUT_SESSION_ID}`,
      cancel_url: `${siteUrl}/signup?canceled=1`,
      metadata: { client_id: clientId },
    });

    if (!session.url) {
      return NextResponse.json({ error: "Could not start checkout." }, { status: 500 });
    }
    return NextResponse.json({ checkoutUrl: session.url });
  } catch (err) {
    const message = err instanceof Error ? err.message : "Stripe is not configured yet.";
    return NextResponse.json({ error: message }, { status: 500 });
  }
}
