import Link from "next/link";
import Image from "next/image";

const PLANS = [
  {
    name: "Monthly",
    plan: "monthly",
    price: "Billed monthly",
    cadence: "One scorecard every month",
    blurb: "For brands that want a steady read on AI visibility without the noise of weekly swings.",
  },
  {
    name: "Weekly",
    plan: "weekly",
    price: "Billed monthly",
    cadence: "A fresh scorecard every week",
    blurb: "For brands actively working AI visibility and watching how each change moves the needle.",
  },
];

export default function LandingPage() {
  return (
    <>
      <div className="brand-band" />
      <header className="container" style={{ paddingTop: 28, paddingBottom: 8 }}>
        <Image src="/aim-logo.png" alt="AIM" width={140} height={19} priority />
      </header>

      <section className="container" style={{ paddingTop: 40, paddingBottom: 40, textAlign: "center" }}>
        <p className="kicker">AI Visibility Tracker</p>
        <h1 style={{ fontSize: 40, color: "var(--indigo)", margin: "10px 0 16px", lineHeight: 1.15 }}>
          Know exactly what ChatGPT, Perplexity, Gemini, and Claude say about your business.
        </h1>
        <p className="muted" style={{ fontSize: 18, maxWidth: 640, margin: "0 auto 28px" }}>
          Every AI engine your customers ask before they buy — tracked automatically, scored, and delivered to your
          inbox as a branded scorecard. Set it up once. Never touch it again.
        </p>
        <Link href="/signup" className="btn btn-primary" style={{ fontSize: 17, padding: "14px 32px" }}>
          Start tracking my AI visibility
        </Link>
      </section>

      <section className="container" style={{ paddingBottom: 56 }}>
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(260px, 1fr))", gap: 20 }}>
          {PLANS.map((p) => (
            <div key={p.plan} className="card">
              <h2 style={{ color: "var(--indigo)", margin: "0 0 4px" }}>{p.name}</h2>
              <p className="muted" style={{ margin: "0 0 4px" }}>{p.price}</p>
              <p style={{ fontWeight: 600, margin: "0 0 10px" }}>{p.cadence}</p>
              <p className="muted" style={{ fontSize: 14 }}>{p.blurb}</p>
              <ul style={{ fontSize: 14, paddingLeft: 18, margin: "14px 0" }}>
                <li>Your own custom prompt set, branded &amp; non-branded</li>
                <li>Citation &amp; mention tracking across 4 AI engines</li>
                <li>AIM-branded PDF scorecard emailed automatically</li>
                <li>Self-service client portal &mdash; manage prompts, billing, history</li>
              </ul>
              <Link href={`/signup?plan=${p.plan}`} className="btn btn-secondary" style={{ width: "100%", textAlign: "center" }}>
                Choose {p.name}
              </Link>
            </div>
          ))}
        </div>
      </section>

      <section className="container" style={{ paddingBottom: 60 }}>
        <div className="card" style={{ textAlign: "center" }}>
          <h2 style={{ color: "var(--indigo)" }}>How it works</h2>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))", gap: 18, marginTop: 16, textAlign: "left" }}>
            <div>
              <p style={{ fontWeight: 700 }}>1. Sign up</p>
              <p className="muted" style={{ fontSize: 14 }}>Tell us about your business and the questions customers ask AI before buying.</p>
            </div>
            <div>
              <p style={{ fontWeight: 700 }}>2. We track it</p>
              <p className="muted" style={{ fontSize: 14 }}>Every cycle, we query every engine with your prompt set and score the results.</p>
            </div>
            <div>
              <p style={{ fontWeight: 700 }}>3. You get the scorecard</p>
              <p className="muted" style={{ fontSize: 14 }}>A branded PDF lands in your inbox automatically &mdash; no manual work, ever.</p>
            </div>
          </div>
        </div>
      </section>

      <footer className="container muted" style={{ fontSize: 12, paddingBottom: 40 }}>
        Advanced Integrated Marketing, Inc. &middot; aim-tex.com
      </footer>
    </>
  );
}
