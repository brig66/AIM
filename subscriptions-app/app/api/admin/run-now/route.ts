import { NextRequest, NextResponse } from "next/server";
import { requireAdmin } from "@/lib/adminGuard";
import { createSupabaseAdminClient } from "@/lib/supabase/server";
import { runTrackingForClient } from "@/lib/tracking/pipeline";

export const runtime = "nodejs";
export const maxDuration = 300;

export async function POST(req: NextRequest) {
  if (!(await requireAdmin())) return NextResponse.json({ error: "Not authorized." }, { status: 401 });

  const { clientId } = await req.json();
  if (!clientId) return NextResponse.json({ error: "Missing clientId." }, { status: 400 });

  const supabase = createSupabaseAdminClient();
  const { data: client, error } = await supabase.from("clients").select("*").eq("id", clientId).maybeSingle();
  if (error || !client) return NextResponse.json({ error: "Client not found." }, { status: 404 });

  const result = await runTrackingForClient(client);
  return NextResponse.json(result);
}
