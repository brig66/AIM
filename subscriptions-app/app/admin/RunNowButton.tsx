"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

export default function RunNowButton({ clientId }: { clientId: string }) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function run() {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch("/api/admin/run-now", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ clientId }),
      });
      const data = await res.json();
      if (!res.ok || data.status === "failed") {
        throw new Error(data.error || data.errorMessage || "Run failed.");
      }
      router.refresh();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Run failed.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div>
      <button type="button" className="btn btn-secondary" style={{ padding: "4px 10px", fontSize: 12 }} onClick={run} disabled={loading}>
        {loading ? "Running…" : "Run now"}
      </button>
      {error ? <p className="error-text" style={{ margin: "4px 0 0" }}>{error}</p> : null}
    </div>
  );
}
