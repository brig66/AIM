import { createSupabaseServerClient } from "@/lib/supabase/server";
import ManageBillingButton from "./ManageBillingButton";

const STATUS_LABEL: Record<string, string> = {
  pending: "Awaiting first payment",
  active: "Active",
  past_due: "Payment past due",
  canceled: "Canceled",
};

export default async function PortalDashboardPage() {
  const supabase = await createSupabaseServerClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();

  const { data: client } = await supabase.from("clients").select("*").eq("auth_user_id", user?.id ?? "").maybeSingle();

  if (!client) {
    return (
      <div className="card">
        <p>
          We couldn&apos;t find an account linked to your login yet. If you just signed up, this can take a minute to
          sync &mdash; refresh in a bit, or contact AIM if it persists.
        </p>
      </div>
    );
  }

  const { data: lastRun } = await supabase
    .from("runs")
    .select("*")
    .eq("client_id", client.id)
    .order("created_at", { ascending: false })
    .limit(1)
    .maybeSingle();

  return (
    <>
      <h1 style={{ color: "var(--indigo)" }}>{client.company_name}</h1>
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: 16, marginTop: 20 }}>
        <div className="card">
          <p className="muted" style={{ fontSize: 12, margin: 0 }}>Status</p>
          <p style={{ fontSize: 20, fontWeight: 700, margin: "6px 0 0" }}>{STATUS_LABEL[client.status] ?? client.status}</p>
        </div>
        <div className="card">
          <p className="muted" style={{ fontSize: 12, margin: 0 }}>Plan</p>
          <p style={{ fontSize: 20, fontWeight: 700, margin: "6px 0 0", textTransform: "capitalize" }}>{client.plan}</p>
        </div>
        <div className="card">
          <p className="muted" style={{ fontSize: 12, margin: 0 }}>Next billing date</p>
          <p style={{ fontSize: 20, fontWeight: 700, margin: "6px 0 0" }}>
            {client.current_period_end ? new Date(client.current_period_end).toLocaleDateString() : "—"}
          </p>
        </div>
      </div>

      {lastRun ? (
        <div className="card" style={{ marginTop: 16 }}>
          <p className="muted" style={{ fontSize: 12, margin: 0 }}>Most recent tracking run</p>
          <p style={{ margin: "6px 0 0" }}>
            {lastRun.period_start} &rarr; {lastRun.period_end} &middot; <StatusBadge status={lastRun.status} />
          </p>
          {lastRun.status === "failed" && lastRun.error_message ? (
            <p className="error-text">{lastRun.error_message}</p>
          ) : null}
        </div>
      ) : null}

      <div style={{ marginTop: 24 }}>
        <ManageBillingButton disabled={!client.stripe_customer_id} />
      </div>
    </>
  );
}

function StatusBadge({ status }: { status: string }) {
  const kind = status === "success" ? "badge-good" : status === "failed" ? "badge-bad" : "badge-warn";
  return <span className={`badge ${kind}`}>{status}</span>;
}
