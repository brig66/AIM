import Link from "next/link";
import Image from "next/image";
import PortalSignOutButton from "./PortalSignOutButton";

export default function PortalLayout({ children }: { children: React.ReactNode }) {
  return (
    <>
      <div className="brand-band" />
      <header className="container" style={{ display: "flex", alignItems: "center", justifyContent: "space-between", paddingTop: 20, paddingBottom: 20 }}>
        <Link href="/portal">
          <Image src="/aim-logo.png" alt="AIM" width={110} height={15} />
        </Link>
        <nav style={{ display: "flex", gap: 18, alignItems: "center", fontSize: 14 }}>
          <Link href="/portal">Dashboard</Link>
          <Link href="/portal/prompts">Tracking prompts</Link>
          <Link href="/portal/scorecards">Scorecards</Link>
          <PortalSignOutButton />
        </nav>
      </header>
      <main className="container" style={{ paddingBottom: 60 }}>
        {children}
      </main>
    </>
  );
}
