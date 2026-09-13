import { Suspense } from "react";
import SignupWizard from "./SignupWizard";

export const metadata = { title: "Sign up — AIM AI Visibility Tracker" };

export default function SignupPage() {
  return (
    <>
      <div className="brand-band" />
      <div className="container" style={{ maxWidth: 720, paddingTop: 32, paddingBottom: 60 }}>
        <p className="kicker">Sign up</p>
        <h1 style={{ color: "var(--indigo)", marginTop: 6 }}>Set up your AI visibility tracking</h1>
        <p className="muted">Takes about 3 minutes. You&apos;ll enter payment on the next screen via Stripe.</p>
        <Suspense fallback={null}>
          <SignupWizard />
        </Suspense>
      </div>
    </>
  );
}
