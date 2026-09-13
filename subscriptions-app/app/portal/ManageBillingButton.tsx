"use client";

import { useState } from "react";

export default function ManageBillingButton({ disabled }: { disabled?: boolean }) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function go() {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch("/api/portal/billing-session", { method: "POST" });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error || "Could not open billing portal.");
      window.location.href = data.url;
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not open billing portal.");
      setLoading(false);
    }
  }

  return (
    <div>
      <button type="button" className="btn btn-primary" onClick={go} disabled={disabled || loading}>
        {loading ? "Opening…" : "Manage billing"}
      </button>
      {disabled ? (
        <p className="muted" style={{ fontSize: 12, marginTop: 6 }}>
          Available once your first payment has processed.
        </p>
      ) : null}
      {error ? <p className="error-text">{error}</p> : null}
    </div>
  );
}
