import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "AIM AI Visibility Tracker",
  description: "Automated AI citation tracking and monthly scorecards from Advanced Integrated Marketing.",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
