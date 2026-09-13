import { createSupabaseServerClient } from "@/lib/supabase/server";
import DownloadScorecardButton from "./DownloadScorecardButton";

export default async function ScorecardsPage() {
  const supabase = await createSupabaseServerClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();

  const { data: client } = await supabase.from("clients").select("id").eq("auth_user_id", user?.id ?? "").maybeSingle();

  const { data: scorecards } = client
    ? await supabase
        .from("scorecards")
        .select("*, runs(period_start, period_end)")
        .eq("client_id", client.id)
        .order("created_at", { ascending: false })
    : { data: [] };

  return (
    <>
      <h1 style={{ color: "var(--indigo)" }}>Scorecards</h1>
      {!scorecards || scorecards.length === 0 ? (
        <p className="muted">No scorecards yet &mdash; your first one will appear here after your first tracking run.</p>
      ) : (
        <table className="card">
          <thead>
            <tr>
              <th>Period</th>
              <th>Overall</th>
              <th>Branded</th>
              <th>Non-branded</th>
              <th>Emailed</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {scorecards.map((s: any) => (
              <tr key={s.id}>
                <td>
                  {s.runs?.period_start} &rarr; {s.runs?.period_end}
                </td>
                <td>{s.overall_score ?? "—"}%</td>
                <td>{s.branded_score ?? "—"}%</td>
                <td>{s.nonbranded_score ?? "—"}%</td>
                <td>{s.emailed_at ? new Date(s.emailed_at).toLocaleDateString() : "Not yet"}</td>
                <td>
                  <DownloadScorecardButton scorecardId={s.id} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </>
  );
}
