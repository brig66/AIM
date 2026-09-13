"use client";

import { useState } from "react";
import { createSupabaseBrowserClient } from "@/lib/supabase/browser";

export default function PortalLoginPage() {
  const [email, setEmail] = useState("");
  const [sent, setSent] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function sendLink(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const supabase = createSupabaseBrowserClient();
      const { error } = await supabase.auth.signInWithOtp({
        email,
        options: { emailRedirectTo: `${window.location.origin}/auth/callback` },
      });
      if (error) throw error;
      setSent(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not send login link.");
    } finally {
      setLoading(false);
    }
  }

  return (
    <>
      <div className="brand-band" />
      <div className="container" style={{ maxWidth: 420, paddingTop: 60 }}>
        <p className="kicker">Client portal</p>
        <h1 style={{ color: "var(--indigo)" }}>Log in</h1>
        {sent ? (
          <p>Check <b>{email}</b> for a login link.</p>
        ) : (
          <form onSubmit={sendLink} className="card">
            <div className="field">
              <label htmlFor="email">Email address</label>
              <input id="email" type="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
            </div>
            {error ? <p className="error-text">{error}</p> : null}
            <button type="submit" className="btn btn-primary" disabled={loading} style={{ width: "100%" }}>
              {loading ? "Sending…" : "Email me a login link"}
            </button>
          </form>
        )}
      </div>
    </>
  );
}
