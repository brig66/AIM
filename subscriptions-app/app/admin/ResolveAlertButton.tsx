"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";

export default function ResolveAlertButton({ alertId }: { alertId: string }) {
  const router = useRouter();
  const [loading, setLoading] = useState(false);

  async function resolve() {
    setLoading(true);
    try {
      await fetch("/api/admin/resolve-alert", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ alertId }),
      });
      router.refresh();
    } finally {
      setLoading(false);
    }
  }

  return (
    <button type="button" className="btn btn-secondary" style={{ padding: "4px 10px", fontSize: 12 }} onClick={resolve} disabled={loading}>
      {loading ? "…" : "Mark resolved"}
    </button>
  );
}
