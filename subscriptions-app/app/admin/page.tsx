import Image from "next/image";
import { createSupabaseAdminClient } from "@/lib/supabase/server";
import type { Run } from "@/lib/supabase/types";
import AdminLogoutButton from "./AdminLogoutButton";
import RunNowButton from "./RunNowButton";
import ResolveAlertButton from "./ResolveAlertButton";

export const dynamic = "force-dynamic";

function nextRunEstimate(plan: string, lastPeriodEnd: string | null): string {
  if (!lastPeriodEnd) return "Due now (no runs yet)";
  const cadenceDays = plan === "weekly" ? 7 : 28;
  const next = new Date(lastPeriodEnd);
  next.setDate(next.getDate() + cadenceDays);
  return next.toLocaleDateString();
}

export default async function AdminDashboardPage() {
  const supabase = createSupabaseAdminClient();

  const { data: clients } = await supabase.from("clients").select("*").order("created_at", { ascending: false });
  const { data: alerts } = await supabase
    .from("admin_alerts")
    .select("*")
    .eq("resolved", false)
    .order("created_at", { ascending: false });

  const clientIds = (clients ?? []).map((c) => c.id);
  const { data: runs } = clientIds.length
    ? await supabase
        .from("runs")
        .select("*")
        .in("client_id", clientIds)
        .order("created_at", { ascending: false })
    : { data: [] };

  const lastRunByClient = new Map<string, Run>();
  for (const run of runs ?? []) {
    if (!lastRunByClient.has(run.client_id)) lastRunByClient.set(run.client_id, run);
  }

  return (
    <>
      <div className="brand-band" />
      <header className="container" style={{ display: "flex", justifyContent: "space-between", alignItems: "center", paddingTop: 20, paddingBottom: 20 }}>
        <Image src="/aim-logo.png" alt="AIM" width={110} height={15} />
        <AdminLogoutButton />
      </header>

      <main className="container" style={{ paddingBottom: 60 }}>
        <h1 style={{ color: "var(--indigo)" }}>Admin</h1>

        <h2 style={{ color: "var(--indigo)", marginTop: 30 }}>
          Alerts {alerts && alerts.length > 0 ? <span className="badge badge-bad">{alerts.length} open</span> : null}
        </h2>
        {!alerts || alerts.length === 0 ? (
          <p className="muted">No open alerts. Everything is running clean.</p>
        ) : (
          <table className="card">
            <thead>
              <tr>
                <th>Severity</th>
                <th>Message</th>
                <th>When</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {alerts.map((a) => (
                <tr key={a.id}>
                  <td>
                    <span className={`badge ${a.severity === "critical" ? "badge-bad" : a.severity === "warning" ? "badge-warn" : "badge-neutral"}`}>
                      {a.severity}
                    </span>
                  </td>
                  <td>{a.message}</td>
                  <td className="muted">{new Date(a.created_at).toLocaleString()}</td>
                  <td>
                    <ResolveAlertButton alertId={a.id} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}

        <h2 style={{ color: "var(--indigo)", marginTop: 30 }}>Clients</h2>
        {!clients || clients.length === 0 ? (
          <p className="muted">No clients yet.</p>
        ) : (
          <table className="card">
            <thead>
              <tr>
                <th>Company</th>
                <th>Status</th>
                <th>Plan</th>
                <th>Last run</th>
                <th>Next run</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {clients.map((c) => {
                const lastRun = lastRunByClient.get(c.id);
                return (
                  <tr key={c.id}>
                    <td>
                      <div style={{ fontWeight: 600 }}>{c.company_name}</div>
                      <div className="muted" style={{ fontSize: 12 }}>{c.contact_email}</div>
                    </td>
                    <td>
                      <span
                        className={`badge ${
                          c.status === "active" ? "badge-good" : c.status === "past_due" ? "badge-warn" : c.status === "canceled" ? "badge-bad" : "badge-neutral"
                        }`}
                      >
                        {c.status}
                      </span>
                    </td>
                    <td style={{ textTransform: "capitalize" }}>{c.plan}</td>
                    <td>
                      {lastRun ? (
                        <>
                          <span
                            className={`badge ${lastRun.status === "success" ? "badge-good" : lastRun.status === "failed" ? "badge-bad" : "badge-warn"}`}
                          >
                            {lastRun.status}
                          </span>{" "}
                          <span className="muted" style={{ fontSize: 12 }}>{lastRun.period_end}</span>
                        </>
                      ) : (
                        <span className="muted">never</span>
                      )}
                    </td>
                    <td className="muted">{nextRunEstimate(c.plan, lastRun?.period_end ?? null)}</td>
                    <td>
                      <RunNowButton clientId={c.id} />
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        )}
      </main>
    </>
  );
}
