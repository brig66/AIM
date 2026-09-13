import { NextRequest, NextResponse } from "next/server";
import { createSupabaseServerClient, createSupabaseAdminClient } from "@/lib/supabase/server";

export const runtime = "nodejs";

/**
 * Issues a short-lived signed Supabase Storage URL for one of the logged-in
 * client's own scorecards. Ownership is checked with the RLS-scoped server
 * client (the row can only be read if it belongs to this auth user), then
 * the signed URL itself is minted with the service-role client since Storage
 * RLS is a separate policy surface from the table policies.
 */
export async function POST(req: NextRequest) {
  const supabase = await createSupabaseServerClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();
  if (!user) return NextResponse.json({ error: "Not signed in." }, { status: 401 });

  const { scorecardId } = await req.json();
  if (!scorecardId) return NextResponse.json({ error: "Missing scorecardId." }, { status: 400 });

  // RLS on `scorecards` (client can only read rows for their own client_id)
  // does the ownership check here: a non-owner's query simply returns no row.
  const { data: scorecard, error } = await supabase
    .from("scorecards")
    .select("pdf_storage_path")
    .eq("id", scorecardId)
    .maybeSingle();

  if (error || !scorecard) {
    return NextResponse.json({ error: "Scorecard not found." }, { status: 404 });
  }

  const admin = createSupabaseAdminClient();
  const { data: signed, error: signError } = await admin.storage
    .from("scorecards")
    .createSignedUrl(scorecard.pdf_storage_path, 60 * 10);

  if (signError || !signed) {
    return NextResponse.json({ error: signError?.message || "Could not create download link." }, { status: 500 });
  }

  return NextResponse.json({ url: signed.signedUrl });
}
