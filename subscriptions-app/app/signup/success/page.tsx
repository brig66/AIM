export const metadata = { title: "You're all set — AIM AI Visibility Tracker" };

export default function SignupSuccessPage() {
  return (
    <>
      <div className="brand-band" />
      <div className="container" style={{ maxWidth: 640, paddingTop: 60, textAlign: "center" }}>
        <h1 style={{ color: "var(--indigo)" }}>You&apos;re all set</h1>
        <p className="muted" style={{ fontSize: 16 }}>
          Payment received. Check your email for a link to activate your client portal login, and your first scorecard
          will be on its way on your tracking schedule.
        </p>
        <a href="/portal/login" className="btn btn-primary" style={{ marginTop: 16 }}>
          Go to client portal
        </a>
      </div>
    </>
  );
}
