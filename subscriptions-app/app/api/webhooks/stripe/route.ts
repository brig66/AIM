import { NextRequest, NextResponse } from "next/server";
import type Stripe from "stripe";
import { getStripe } from "@/lib/stripe";
import { createSupabaseAdminClient } from "@/lib/supabase/server";
import { sendEmail } from "@/lib/resend";

export const runtime = "nodejs";

export async function POST(req: NextRequest) {
  const signature = req.headers.get("stripe-signature");
  const webhookSecret = process.env.STRIPE_WEBHOOK_SECRET;
  const rawBody = await req.text();

  if (!webhookSecret) {
    return NextResponse.json({ error: "STRIPE_WEBHOOK_SECRET is not configured." }, { status: 500 });
  }
  if (!signature) {
    return NextResponse.json({ error: "Missing stripe-signature header." }, { status: 400 });
  }

  let event: Stripe.Event;
  try {
    event = getStripe().webhooks.constructEvent(rawBody, signature, webhookSecret);
  } catch (err) {
    const message = err instanceof Error ? err.message : "Invalid signature";
    return NextResponse.json({ error: `Webhook signature verification failed: ${message}` }, { status: 400 });
  }

  const supabase = createSupabaseAdminClient();

  // Every event, acted on or not, gets an audit row. Idempotent on
  // stripe_event_id so Stripe's at-least-once delivery can't double-process.
  const { data: existing } = await supabase
    .from("billing_events")
    .select("id")
    .eq("stripe_event_id", event.id)
    .maybeSingle();

  if (existing) {
    return NextResponse.json({ received: true, duplicate: true });
  }

  let clientId: string | null = null;

  try {
    switch (event.type) {
      case "checkout.session.completed": {
        const session = event.data.object as Stripe.Checkout.Session;
        clientId = session.client_reference_id || (session.metadata?.client_id ?? null);
        if (clientId) {
          const { data: client } = await supabase
            .from("clients")
            .update({
              status: "active",
              stripe_customer_id: typeof session.customer === "string" ? session.customer : session.customer?.id,
              stripe_subscription_id:
                typeof session.subscription === "string" ? session.subscription : session.subscription?.id,
            })
            .eq("id", clientId)
            .select()
            .single();

          if (client) {
            await sendEmail({
              to: client.contact_email,
              subject: "Welcome to AIM AI Visibility Tracker",
              html: welcomeEmailHtml(client.company_name, client.contact_name),
            });

            // Invite them to set a portal password / magic link login, tied
            // to their client row via matching contact_email in /auth/callback.
            await supabase.auth.admin.inviteUserByEmail(client.contact_email, {
              redirectTo: `${process.env.NEXT_PUBLIC_SITE_URL || ""}/auth/callback`,
            });
          }
        }
        break;
      }

      case "invoice.paid": {
        // Stripe already emails the customer a receipt automatically once
        // "Emails > Successful payments" is enabled in Stripe settings (see
        // SETUP-CHECKLIST.md) — this handler just logs for audit/history, no
        // duplicate receipt email is sent from here.
        const invoice = event.data.object as Stripe.Invoice;
        clientId = await resolveClientIdFromCustomer(supabase, invoice.customer);
        break;
      }

      case "customer.subscription.updated": {
        const sub = event.data.object as Stripe.Subscription;
        clientId = await resolveClientIdFromCustomer(supabase, sub.customer);
        if (clientId && sub.status === "past_due") {
          await supabase.from("clients").update({ status: "past_due" }).eq("id", clientId);
          await supabase.from("admin_alerts").insert({
            client_id: clientId,
            severity: "warning",
            message: `Subscription for client ${clientId} is past_due (Stripe subscription ${sub.id}).`,
          });
        } else if (clientId && sub.status === "active") {
          await supabase
            .from("clients")
            .update({
              status: "active",
              current_period_end: new Date(sub.current_period_end * 1000).toISOString(),
            })
            .eq("id", clientId);
        }
        break;
      }

      case "customer.subscription.deleted": {
        const sub = event.data.object as Stripe.Subscription;
        clientId = await resolveClientIdFromCustomer(supabase, sub.customer);
        if (clientId) {
          await supabase.from("clients").update({ status: "canceled" }).eq("id", clientId);
        }
        break;
      }

      default:
        // Unhandled event types still get their audit row below.
        break;
    }
  } catch (err) {
    // Log the failure as a critical alert but still record the billing_event
    // below — never silently drop a Stripe event.
    await supabase.from("admin_alerts").insert({
      client_id: clientId,
      severity: "critical",
      message: `Error processing Stripe webhook ${event.type} (${event.id}): ${
        err instanceof Error ? err.message : String(err)
      }`,
    });
  }

  await supabase.from("billing_events").insert({
    stripe_event_id: event.id,
    client_id: clientId,
    event_type: event.type,
    payload: JSON.parse(JSON.stringify(event)),
    processed_at: new Date().toISOString(),
  });

  return NextResponse.json({ received: true });
}

async function resolveClientIdFromCustomer(
  supabase: ReturnType<typeof createSupabaseAdminClient>,
  customer: string | Stripe.Customer | Stripe.DeletedCustomer | null
): Promise<string | null> {
  const customerId = typeof customer === "string" ? customer : customer?.id;
  if (!customerId) return null;
  const { data } = await supabase.from("clients").select("id").eq("stripe_customer_id", customerId).maybeSingle();
  return data?.id ?? null;
}

function welcomeEmailHtml(companyName: string, contactName: string): string {
  return `
  <div style="font-family:Arial,sans-serif;color:#1c1830;max-width:560px;margin:0 auto">
    <div style="height:6px;background:linear-gradient(90deg,#352597,#c0228a 55%,#e7730d)"></div>
    <div style="padding:24px 8px">
      <h1 style="font-size:20px;color:#352597;margin:0 0 12px">Welcome to AIM AI Visibility Tracker, ${escapeHtml(companyName)}!</h1>
      <p>Hi ${escapeHtml(contactName)},</p>
      <p>Your subscription is active. Check your inbox for a separate email with a link to set up your client portal login — from there you can manage your tracking prompts, view past scorecards, and manage billing any time.</p>
      <p>Your first AI Visibility Scorecard will arrive automatically on your tracking schedule.</p>
      <p style="margin-top:24px;color:#6b6480;font-size:12px">Advanced Integrated Marketing, Inc. &middot; aim-tex.com</p>
    </div>
  </div>`;
}

function escapeHtml(s: string): string {
  return s.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]!));
}
