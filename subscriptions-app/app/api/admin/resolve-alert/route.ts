import { NextRequest, NextResponse } from "next/server";
import { requireAdmin } from "@/lib/adminGuard";
import { createSupabaseAdminClient } from "@/lib/supabase/server";

export const runtime = "nodejs";

export async function POST(req: NextRequest) {
  if (!(await requireAdmin())) return NextResponse.json({ error: "Not authorized." }, { status: 401 });

  const { alertId } = await req.json();
  if (!alertId) return NextResponse.json({ error: "Missing alertId." }, { status: 400 });

  const supabase = createSupabaseAdminClient();
  const { error } = await supabase.from("admin_alerts").update({ resolved: true }).eq("id", alertId);
  if (error) return NextResponse.json({ error: error.message }, { status: 500 });

  return NextResponse.json({ ok: true });
}
