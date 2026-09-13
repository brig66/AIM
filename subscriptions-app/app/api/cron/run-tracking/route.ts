import { NextRequest, NextResponse } from "next/server";
import { findDueClients, runTrackingForClient } from "@/lib/tracking/pipeline";

export const runtime = "nodejs";
export const maxDuration = 300;

/**
 * Invoked by Vercel Cron (see vercel.json — runs daily). Decides which
 * active clients are actually due today based on plan cadence vs. their last
 * run (see findDueClients), then runs the pipeline for each. Idempotent:
 * runTrackingForClient() itself no-ops if a successful run already exists
 * for the current period, so a retried/duplicate cron invocation can't
 * double-run (and can't double-charge — billing is entirely separate, driven
 * by Stripe subscriptions, not by this route).
 */
export async function GET(req: NextRequest) {
  const auth = req.headers.get("authorization");
  const expected = process.env.CRON_SECRET;
  if (!expected) {
    return NextResponse.json({ error: "CRON_SECRET is not configured on the server." }, { status: 500 });
  }
  if (auth !== `Bearer ${expected}`) {
    return NextResponse.json({ error: "Unauthorized." }, { status: 401 });
  }

  const dueClients = await findDueClients();
  const results = [];
  for (const client of dueClients) {
    const result = await runTrackingForClient(client);
    results.push({ clientId: client.id, companyName: client.company_name, ...result });
  }

  return NextResponse.json({ checked: dueClients.length, results });
}
