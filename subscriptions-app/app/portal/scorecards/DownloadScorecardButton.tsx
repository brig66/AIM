"use client";

import { useState } from "react";

export default function DownloadScorecardButton({ scorecardId }: { scorecardId: string }) {
  const [loading, setLoading] = useState(false);

  async function download() {
    setLoading(true);
    try {
      const res = await fetch("/api/portal/scorecard-url", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ scorecardId }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.error);
      window.open(data.url, "_blank", "noopener,noreferrer");
    } catch {
      alert("Could not generate a download link. Please try again.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <button type="button" className="btn btn-secondary" style={{ padding: "4px 10px", fontSize: 12 }} onClick={download} disabled={loading}>
      {loading ? "…" : "Download PDF"}
    </button>
  );
}
