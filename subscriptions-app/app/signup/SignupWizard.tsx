"use client";

import { useEffect, useRef, useState } from "react";
import { useSearchParams } from "next/navigation";
import Script from "next/script";

interface PromptDraft {
  text: string;
  isBranded: boolean;
}

const STEPS = ["Company", "Contact", "Plan", "Prompts", "Review"] as const;

declare global {
  interface Window {
    turnstile?: {
      render: (
        container: HTMLElement,
        opts: { sitekey: string; callback: (token: string) => void }
      ) => string;
    };
  }
}

export default function SignupWizard() {
  const searchParams = useSearchParams();
  const initialPlan = searchParams.get("plan") === "weekly" ? "weekly" : "monthly";

  const [step, setStep] = useState(0);
  const [companyName, setCompanyName] = useState("");
  const [websiteDomain, setWebsiteDomain] = useState("");
  const [contactName, setContactName] = useState("");
  const [contactEmail, setContactEmail] = useState("");
  const [plan, setPlan] = useState<"monthly" | "weekly">(initialPlan);
  const [prompts, setPrompts] = useState<PromptDraft[]>([
    { text: "", isBranded: true },
    { text: "", isBranded: false },
  ]);
  const [turnstileToken, setTurnstileToken] = useState<string>("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const widgetRef = useRef<HTMLDivElement | null>(null);
  const renderedRef = useRef(false);

  const siteKey = process.env.NEXT_PUBLIC_TURNSTILE_SITE_KEY;

  useEffect(() => {
    if (step !== STEPS.length - 1) return;
    if (!siteKey || renderedRef.current || !widgetRef.current) return;
    if (!window.turnstile) return;
    renderedRef.current = true;
    window.turnstile.render(widgetRef.current, {
      sitekey: siteKey,
      callback: (token: string) => setTurnstileToken(token),
    });
  }, [step, siteKey]);

  function updatePrompt(i: number, patch: Partial<PromptDraft>) {
    setPrompts((prev) => prev.map((p, idx) => (idx === i ? { ...p, ...patch } : p)));
  }

  function addPrompt() {
    setPrompts((prev) => [...prev, { text: "", isBranded: false }]);
  }

  function removePrompt(i: number) {
    setPrompts((prev) => prev.filter((_, idx) => idx !== i));
  }

  function canAdvance(): boolean {
    switch (step) {
      case 0:
        return companyName.trim().length > 0 && websiteDomain.trim().length > 0;
      case 1:
        return contactName.trim().length > 0 && /.+@.+\..+/.test(contactEmail);
      case 2:
        return true;
      case 3:
        return prompts.filter((p) => p.text.trim().length > 0).length >= 1;
      default:
        return true;
    }
  }

  async function submit() {
    setSubmitting(true);
    setError(null);
    try {
      const res = await fetch("/api/signup", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          companyName,
          websiteDomain,
          contactName,
          contactEmail,
          plan,
          prompts: prompts.filter((p) => p.text.trim().length > 0),
          turnstileToken,
        }),
      });
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.error || "Something went wrong. Please try again.");
      }
      if (data.checkoutUrl) {
        window.location.href = data.checkoutUrl;
        return;
      }
      throw new Error("No checkout URL returned.");
    } catch (err) {
      setError(err instanceof Error ? err.message : "Something went wrong.");
      setSubmitting(false);
    }
  }

  return (
    <div className="card" style={{ marginTop: 24 }}>
      {siteKey ? (
        <Script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer />
      ) : null}

      <div style={{ display: "flex", gap: 6, marginBottom: 24 }}>
        {STEPS.map((label, i) => (
          <div key={label} style={{ flex: 1, textAlign: "center" }}>
            <div
              style={{
                height: 4,
                borderRadius: 2,
                background: i <= step ? "var(--indigo)" : "var(--line)",
                marginBottom: 6,
              }}
            />
            <span style={{ fontSize: 11, color: i === step ? "var(--indigo)" : "var(--muted)" }}>{label}</span>
          </div>
        ))}
      </div>

      {step === 0 && (
        <div>
          <div className="field">
            <label htmlFor="companyName">Company name</label>
            <input id="companyName" value={companyName} onChange={(e) => setCompanyName(e.target.value)} placeholder="Acme Roofing Co." />
          </div>
          <div className="field">
            <label htmlFor="websiteDomain">Website domain</label>
            <input id="websiteDomain" value={websiteDomain} onChange={(e) => setWebsiteDomain(e.target.value)} placeholder="acmeroofing.com" />
          </div>
        </div>
      )}

      {step === 1 && (
        <div>
          <div className="field">
            <label htmlFor="contactName">Your name</label>
            <input id="contactName" value={contactName} onChange={(e) => setContactName(e.target.value)} placeholder="Jane Smith" />
          </div>
          <div className="field">
            <label htmlFor="contactEmail">Email</label>
            <input
              id="contactEmail"
              type="email"
              value={contactEmail}
              onChange={(e) => setContactEmail(e.target.value)}
              placeholder="jane@acmeroofing.com"
            />
            <p className="muted" style={{ fontSize: 12, marginTop: 4 }}>
              We&apos;ll send your monthly scorecards and portal login link here.
            </p>
          </div>
        </div>
      )}

      {step === 2 && (
        <div>
          <div className="field">
            <label>Tracking frequency</label>
            <div style={{ display: "flex", gap: 12 }}>
              {(["monthly", "weekly"] as const).map((p) => (
                <button
                  key={p}
                  type="button"
                  onClick={() => setPlan(p)}
                  className="btn"
                  style={{
                    flex: 1,
                    background: plan === p ? "var(--indigo)" : "#fff",
                    color: plan === p ? "#fff" : "var(--ink)",
                    border: "1px solid var(--line)",
                    textTransform: "capitalize",
                  }}
                >
                  {p}
                </button>
              ))}
            </div>
          </div>
        </div>
      )}

      {step === 3 && (
        <div>
          <p className="muted" style={{ fontSize: 13, marginBottom: 12 }}>
            Add the questions you want AI engines checked against. Mark each one <b>branded</b> (names your company) or{" "}
            <b>non-branded</b> (a category question a buyer would ask without knowing your brand). You can edit these
            any time from your client portal.
          </p>
          {prompts.map((p, i) => (
            <div key={i} className="field" style={{ display: "flex", gap: 8, alignItems: "flex-start" }}>
              <input
                value={p.text}
                onChange={(e) => updatePrompt(i, { text: e.target.value })}
                placeholder={i === 0 ? "Is Acme Roofing any good?" : "Best roofing company near me"}
                style={{ flex: 1 }}
              />
              <select
                value={p.isBranded ? "branded" : "nonbranded"}
                onChange={(e) => updatePrompt(i, { isBranded: e.target.value === "branded" })}
                style={{ width: 150 }}
              >
                <option value="branded">Branded</option>
                <option value="nonbranded">Non-branded</option>
              </select>
              <button
                type="button"
                onClick={() => removePrompt(i)}
                className="btn btn-secondary"
                style={{ padding: "10px 14px" }}
                aria-label="Remove prompt"
              >
                &times;
              </button>
            </div>
          ))}
          <button type="button" onClick={addPrompt} className="btn btn-secondary">
            + Add another prompt
          </button>
        </div>
      )}

      {step === 4 && (
        <div>
          <h3 style={{ marginTop: 0 }}>Review</h3>
          <ul style={{ fontSize: 14, lineHeight: 1.8 }}>
            <li>
              <b>{companyName}</b> ({websiteDomain})
            </li>
            <li>
              {contactName} &middot; {contactEmail}
            </li>
            <li style={{ textTransform: "capitalize" }}>{plan} tracking</li>
            <li>{prompts.filter((p) => p.text.trim()).length} tracking prompts</li>
          </ul>
          {siteKey ? (
            <div ref={widgetRef} style={{ margin: "14px 0" }} />
          ) : (
            <p className="muted" style={{ fontSize: 12 }}>
              (Bot-check widget will appear here once Turnstile is configured.)
            </p>
          )}
          <p className="muted" style={{ fontSize: 13 }}>
            Clicking below takes you to Stripe to enter payment details. Your subscription starts once payment succeeds.
          </p>
          {error ? <p className="error-text">{error}</p> : null}
        </div>
      )}

      <div style={{ display: "flex", justifyContent: "space-between", marginTop: 24 }}>
        <button
          type="button"
          className="btn btn-secondary"
          onClick={() => setStep((s) => Math.max(0, s - 1))}
          disabled={step === 0}
          style={{ visibility: step === 0 ? "hidden" : "visible" }}
        >
          Back
        </button>
        {step < STEPS.length - 1 ? (
          <button type="button" className="btn btn-primary" onClick={() => setStep((s) => s + 1)} disabled={!canAdvance()}>
            Continue
          </button>
        ) : (
          <button type="button" className="btn btn-primary" onClick={submit} disabled={submitting}>
            {submitting ? "Redirecting to Stripe…" : "Continue to payment"}
          </button>
        )}
      </div>
    </div>
  );
}
