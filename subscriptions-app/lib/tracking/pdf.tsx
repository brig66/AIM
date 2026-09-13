/**
 * AIM-branded scorecard PDF, rendered server-side with @react-pdf/renderer.
 *
 * The existing aim-ai-visibility-tracker skill renders its PDF with
 * WeasyPrint (HTML+CSS -> PDF), which isn't practical inside a Vercel
 * serverless function (no system browser/render engine, and installing one
 * is a real deployment burden for a non-developer to maintain). This module
 * reuses the exact same brand.json color palette and copy/structure — cards,
 * branded-vs-non-branded split, per-engine table, prompt detail table,
 * confidentiality notice page — rebuilt with @react-pdf/renderer primitives,
 * a pure-JS PDF generator that runs fine in a serverless function.
 *
 * Deviation from the skill: react-pdf's font embedding wants TrueType/OpenType
 * files, and the skill's brand fonts are WOFF2 — rather than pull in a font
 * conversion step, this uses Helvetica (a PDF standard font) and leans on the
 * brand color palette + logo image to carry the AIM identity.
 */
import React from "react";
import { Document, Page, Text, View, Image, StyleSheet, renderToBuffer } from "@react-pdf/renderer";
import fs from "node:fs";
import path from "node:path";
import { brand, colors, colorFor } from "./brand";
import type { RunMetrics } from "./scoring";
import { groupByPrompt, type PromptOutcome } from "./scoring";

const logoPath = path.join(process.cwd(), "lib/tracking/assets/aim-logo-trans.png");

const styles = StyleSheet.create({
  page: { padding: 36, fontSize: 9, fontFamily: "Helvetica", color: colors.ink },
  headBand: { height: 6, backgroundColor: colors.indigo, marginBottom: 14 },
  logo: { width: 130, marginBottom: 8 },
  kicker: { fontSize: 8, letterSpacing: 2, color: colors.magenta, marginBottom: 4, textTransform: "uppercase" },
  h1: { fontSize: 20, fontWeight: 700, color: colors.indigo, marginBottom: 2 },
  sub: { fontSize: 9, color: colors.muted, marginBottom: 14 },
  h2: { fontSize: 13, fontWeight: 700, color: colors.indigo, marginTop: 16, marginBottom: 6, borderBottom: `2 solid ${colors.line}`, paddingBottom: 3 },
  cardsRow: { flexDirection: "row", gap: 8, marginBottom: 8 },
  card: { flex: 1, borderWidth: 1, borderColor: colors.line, borderRadius: 6, padding: 8 },
  cardValue: { fontSize: 22, fontWeight: 700 },
  cardLabel: { fontSize: 9, fontWeight: 700, marginTop: 2 },
  cardDesc: { fontSize: 7, color: colors.muted, marginTop: 2 },
  note: { backgroundColor: "#f6f2fb", borderLeftWidth: 3, borderLeftColor: colors.magenta, padding: 8, borderRadius: 4, fontSize: 8.5, marginBottom: 4 },
  splitRow: { flexDirection: "row", gap: 8, marginBottom: 6 },
  splitBox: { flex: 1, borderWidth: 1, borderColor: colors.line, borderRadius: 6, padding: 8 },
  splitHead: { fontSize: 9, fontWeight: 700, color: colors.indigo, marginBottom: 6 },
  splitMetricsRow: { flexDirection: "row", gap: 6 },
  splitMetric: { flex: 1, alignItems: "center" },
  splitMetricValue: { fontSize: 13, fontWeight: 700 },
  splitMetricLabel: { fontSize: 6.6, color: colors.muted, marginTop: 1, textAlign: "center" },
  table: { marginTop: 4, marginBottom: 6 },
  thRow: { flexDirection: "row", backgroundColor: colors.indigo },
  th: { color: "#fff", fontSize: 8, fontWeight: 700, padding: 5, flex: 1 },
  tr: { flexDirection: "row", borderBottomWidth: 1, borderBottomColor: colors.line },
  trAlt: { backgroundColor: "#faf7fc" },
  td: { fontSize: 8, padding: 5, flex: 1 },
  footer: { marginTop: 14, borderTopWidth: 2, borderTopColor: colors.line, paddingTop: 6, fontSize: 7.5, color: colors.muted },
  noticeTitle: { fontSize: 20, fontWeight: 700, color: colors.indigo, marginBottom: 6 },
  noticeSub: { fontSize: 9, color: colors.muted, marginBottom: 16 },
  noticeBox: { borderTopWidth: 3, borderTopColor: colors.magenta, borderWidth: 1, borderColor: colors.line, backgroundColor: "#f8f5fb", padding: 12 },
  noticeP: { fontSize: 8.5, marginBottom: 8, lineHeight: 1.5 },
});

function pill(label: string, kind: "good" | "warn" | "bad") {
  const bg = kind === "good" ? colors.good : kind === "warn" ? colors.orange : colors.magenta;
  return (
    <Text style={{ backgroundColor: bg, color: "#fff", fontSize: 7, paddingVertical: 2, paddingHorizontal: 6, borderRadius: 8 }}>
      {label}
    </Text>
  );
}

function Card({ label, value, desc }: { label: string; value: number; desc: string }) {
  return (
    <View style={styles.card}>
      <Text style={[styles.cardValue, { color: colorFor(value) }]}>{value}%</Text>
      <Text style={styles.cardLabel}>{label}</Text>
      <Text style={styles.cardDesc}>{desc}</Text>
    </View>
  );
}

function SplitBox({ label, m }: { label: string; m: { answers: number; citationRate: number; mentionRate: number; primaryDomainRate: number } }) {
  const metrics: [string, number][] = [
    ["Citation rate", m.citationRate],
    ["Mention rate", m.mentionRate],
    ["Primary domain rate", m.primaryDomainRate],
  ];
  return (
    <View style={styles.splitBox}>
      <Text style={styles.splitHead}>
        {label} ({m.answers} answers)
      </Text>
      <View style={styles.splitMetricsRow}>
        {metrics.map(([k, v]) => (
          <View key={k} style={styles.splitMetric}>
            <Text style={[styles.splitMetricValue, { color: colorFor(v) }]}>{v}%</Text>
            <Text style={styles.splitMetricLabel}>{k}</Text>
          </View>
        ))}
      </View>
    </View>
  );
}

export interface ScorecardPdfProps {
  companyName: string;
  websiteDomain: string;
  periodStart: string;
  periodEnd: string;
  engines: string[];
  metrics: RunMetrics;
  outcomes: PromptOutcome[];
  isMock?: boolean;
}

function ScorecardDocument(props: ScorecardPdfProps) {
  const { companyName, periodStart, periodEnd, engines, metrics, outcomes, isMock } = props;
  const logoBuffer = fs.existsSync(logoPath) ? fs.readFileSync(logoPath) : null;
  const promptRows = groupByPrompt(outcomes);
  const sentimentStr =
    Object.entries(metrics.sentiment)
      .map(([k, v]) => `${k}: ${v}`)
      .join(" · ") || "n/a";

  return (
    <Document>
      <Page size="LETTER" style={styles.page}>
        <View style={styles.headBand} />
        {logoBuffer ? <Image src={logoBuffer} style={styles.logo} /> : null}
        <Text style={styles.kicker}>AI Visibility &middot; Monthly Scorecard</Text>
        <Text style={styles.h1}>{companyName}</Text>
        <Text style={styles.sub}>
          {periodStart} to {periodEnd} &middot; {engines.join(", ")} &middot; {metrics.totalAnswers} answers analyzed
          {isMock ? "  [SAMPLE / MOCK DATA]" : ""}
        </Text>

        <View style={styles.cardsRow}>
          <Card label="Citation rate" value={metrics.overall.citationRate} desc={`% of answers that cite a ${companyName} domain`} />
          <Card label="Mention rate" value={metrics.overall.mentionRate} desc="% of answers that name the brand" />
          <Card label="Overall score" value={metrics.overall.score} desc="Weighted citation + mention score" />
        </View>
        <Text style={styles.note}>
          Across {metrics.totalAnswers} AI answers this period, {companyName} was cited in {metrics.overall.citationRate}% and named in{" "}
          {metrics.overall.mentionRate}% of answers. Sentiment where mentioned: {sentimentStr}.
        </Text>

        <Text style={styles.h2}>Branded vs. non-branded performance</Text>
        <Text style={{ fontSize: 8, color: colors.muted, marginBottom: 6 }}>
          Branded prompts name {companyName} directly. Non-branded prompts are the harder, more valuable test: category
          questions where a buyer never mentions {companyName} at all.
        </Text>
        <View style={styles.splitRow}>
          <SplitBox label="Branded queries" m={metrics.branded} />
          <SplitBox label="Non-branded queries" m={metrics.nonbranded} />
        </View>

        <Text style={styles.h2}>Performance by AI engine</Text>
        <View style={styles.table}>
          <View style={styles.thRow}>
            <Text style={styles.th}>Engine</Text>
            <Text style={styles.th}>Answers</Text>
            <Text style={styles.th}>Mention rate</Text>
            <Text style={styles.th}>Citation rate</Text>
          </View>
          {Object.entries(metrics.byEngine).map(([engine, m], i) => (
            <View key={engine} style={[styles.tr, i % 2 === 1 ? styles.trAlt : {}]}>
              <Text style={styles.td}>{engine}</Text>
              <Text style={styles.td}>{m.answers}</Text>
              <Text style={styles.td}>{m.mentionRate}%</Text>
              <Text style={styles.td}>{m.citationRate}%</Text>
            </View>
          ))}
        </View>

        <Text style={styles.h2}>Prompt-level detail</Text>
        <View style={styles.table}>
          <View style={styles.thRow}>
            <Text style={[styles.th, { flex: 3 }]}>Prompt</Text>
            <Text style={styles.th}>Type</Text>
            <Text style={styles.th}>Result</Text>
            <Text style={[styles.th, { flex: 1.4 }]}>Cited on</Text>
          </View>
          {promptRows.map((row, i) => (
            <View key={i} style={[styles.tr, i % 2 === 1 ? styles.trAlt : {}]}>
              <Text style={[styles.td, { flex: 3 }]}>{row.promptText}</Text>
              <Text style={styles.td}>{row.isBranded ? "Branded" : "Non-branded"}</Text>
              <View style={[styles.td, { justifyContent: "center" }]}>
                {row.citedCount > 0
                  ? pill(`${row.citedCount}/${row.total} cited`, "good")
                  : row.mentionedCount > 0
                  ? pill(`${row.mentionedCount}/${row.total} mentioned`, "warn")
                  : pill("absent", "bad")}
              </View>
              <Text style={[styles.td, { flex: 1.4, fontSize: 7, color: colors.muted }]}>
                {row.citedOnEngines.join(", ") || "—"}
              </Text>
            </View>
          ))}
        </View>

        {metrics.errors.length > 0 ? (
          <Text style={[styles.note, { backgroundColor: "#fef6ef", borderLeftColor: colors.orange }]}>
            {metrics.errors.length} engine call(s) failed this run and were excluded from the metrics above:{" "}
            {metrics.errors.join("; ")}
          </Text>
        ) : null}

        <Text style={styles.footer}>
          Methodology: each prompt is sent to every configured engine; responses are parsed for brand mentions and source
          citations. Citation rate = answers citing a brand domain; mention rate = answers naming the brand.{" "}
          {isMock ? "Figures shown are mock data generated to validate the pipeline." : "Figures reflect live AI responses for the period."}
          {"\n"}
          {brand.firm_name} &middot; {brand.address} &middot; {brand.phone} &middot; {brand.website}
        </Text>
      </Page>

      <Page size="LETTER" style={styles.page}>
        <Text style={{ fontSize: 8, letterSpacing: 2, color: colors.magenta, marginBottom: 10, textTransform: "uppercase" }}>
          Notice
        </Text>
        <Text style={styles.noticeTitle}>Confidential &amp; Proprietary</Text>
        <Text style={styles.noticeSub}>Prepared for {companyName} by {brand.firm_name}.</Text>
        <View style={styles.noticeBox}>
          <Text style={styles.noticeP}>
            This document contains AIM Confidential Material, including proprietary methodologies, prompt sets, scoring
            rubrics, analytical frameworks, and trade secrets owned by {brand.firm_name} (&quot;AIM&quot;).
          </Text>
          <Text style={styles.noticeP}>
            It is provided solely for the internal use of the named recipient and may not be copied, excerpted,
            forwarded, published, or otherwise disclosed to any third party without AIM&apos;s prior written consent.
          </Text>
          <Text style={styles.noticeP}>
            Recipient agrees to protect this material with no less care than it uses for its own confidential
            information, and to return or destroy it upon request.
          </Text>
        </View>
        <Text style={{ fontSize: 8, color: colors.muted, marginTop: 12 }}>
          Questions regarding permitted use should be directed to {brand.firm_name} &middot; {brand.website} &middot;{" "}
          {brand.phone}.
        </Text>
      </Page>
    </Document>
  );
}

export async function renderScorecardPdf(props: ScorecardPdfProps): Promise<Buffer> {
  return renderToBuffer(<ScorecardDocument {...props} />);
}
