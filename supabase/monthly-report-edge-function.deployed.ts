import "jsr:@supabase/functions-js/edge-runtime.d.ts";
import { createClient } from "jsr:@supabase/supabase-js@2";

/**
 * monthly-report — renders the live Overview page exactly as the dashboard's
 * "Save as PDF" button does (same @media print stylesheet, same donut charts,
 * same AIM branding) and emails it to each client's report_recipients.
 *
 * Why a headless browser instead of drawing the PDF here: "Save as PDF" is
 * just window.print() against the live dashboard — there is no server-side
 * report generator to imitate, only a page and its print CSS. Reproducing
 * that pixel-for-pixel means actually running a browser against that page,
 * not re-implementing its charts in a PDF library.
 *
 * Auth to this function: shared token in internal_config('collector_token'),
 * sent as x-collector-token. verify_jwt off so pg_cron can call it (see the
 * monthly-report-dispatch cron job, 13:00 UTC on the 1st of the month).
 *
 * Auth to the dashboard page: a short-lived 'client'-role session token,
 * minted here with the same HMAC scheme /login uses (internal_config
 * 'dashboard_signing_key'), passed as ?report_token=. The page
 * (web/index.html) recognizes that param, signs itself in, forces the
 * requested client + date window, and sets data-report-ready="1" on <body>
 * once the Overview has finished loading — that's what the renderer waits on
 * before printing, so charts are never captured half-drawn.
 *
 * Rendering: a headless-Chrome-as-a-service (Browserless-compatible REST
 * /pdf endpoint). Credentials: internal_config('browserless_api_key',
 * 'browserless_base_url'). The page URL: internal_config('dashboard_public_url').
 *
 * Executive Summary: before rendering, this calls the 'ask' function
 * (mode:"report") for the same client/window, the same call the dashboard's
 * "Generate summary" button makes. That writes report_summaries, which
 * dash_summary (and so the Overview page) reads — so by the time the browser
 * loads the page, the summary is already there instead of showing "No summary
 * has been written yet." Best-effort: it can take up to ~100s per client, and
 * a slow or failed generation does not block sending the rest of the report.
 *
 * Sending: SMTP2GO's Send Email API. Its own API key —
 * internal_config('report_smtp2go_api_key') — deliberately separate from
 * 'smtp2go_api_key' (the forms-archive read key): they're scoped
 * differently in SMTP2GO (sending vs. reading the archive), and one account
 * can hold several keys. Sender identity: internal_config
 * ('report_sender_email' / 'report_sender_name').
 *
 * Modes (POST JSON body):
 *   {}                                  live run — every active client with
 *                                        report_recipients, window ending
 *                                        yesterday, 60 days back, emailed out.
 *   {dry_run:true, client_id}           render one client's PDF and return it
 *                                        base64 instead of sending — for
 *                                        checking a report before it goes out.
 *   {client_id}                         live run restricted to one client
 *                                        (for a manual resend).
 */

const SUPABASE_URL = Deno.env.get("SUPABASE_URL")!;
const SERVICE_KEY = Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!;
const db = createClient(SUPABASE_URL, SERVICE_KEY, { auth: { persistSession: false } });

const SEND_API = "https://api.smtp2go.com/v3/email/send";
const ASK_API = `${SUPABASE_URL}/functions/v1/ask`;
// Generous enough for one client's summary (up to ~100s) + render (~60s) +
// send. Multiple clients in one run may not all fit before the deadline —
// the loop below marks the rest "skipped_deadline" rather than half-finishing
// one, and a skipped client can be resent with {client_id:N}.
const WALL_MS = 260_000;
const WINDOW_DAYS = 60;
const TOKEN_TTL_MS = 15 * 60 * 1000;   // covers summary generation + render
const RENDER_TIMEOUT_MS = 60_000;
const SUMMARY_TIMEOUT_MS = 100_000;    // just under ask's own 110s budget

const json = (b: unknown, s = 200) =>
  new Response(JSON.stringify(b), { status: s, headers: { "Content-Type": "application/json" } });

// Chunked to stay well under the JS engine's max-arguments limit — a
// multi-page PDF is easily 100-300KB, too many bytes to spread into
// String.fromCharCode(...bytes) in one call.
function bytesToBase64(bytes: Uint8Array): string {
  let binary = "";
  const chunk = 8192;
  for (let i = 0; i < bytes.length; i += chunk) {
    binary += String.fromCharCode(...bytes.subarray(i, i + chunk));
  }
  return btoa(binary);
}

function ctEq(a: string, b: string): boolean {
  if (!a || !b || a.length !== b.length) return false;
  let d = 0;
  for (let i = 0; i < a.length; i++) d |= a.charCodeAt(i) ^ b.charCodeAt(i);
  return d === 0;
}

async function cfg(key: string): Promise<string> {
  const { data } = await db.from("internal_config").select("value").eq("key", key).single();
  return data?.value ?? "";
}

/* -------------------------------------------------- dashboard session -- */
// Mirrors supabase/functions/dashboard/index.ts's sign()/makeToken() exactly:
// token = "<exp>.<role>.<hexHmacSha256(exp.role)>". Minting it here (rather
// than adding a new dashboard endpoint) means the page's existing auth is
// unchanged — this just signs itself in as a normal, short-lived session.
let keyCache: { key: CryptoKey; at: number } | null = null;
async function signingKey(): Promise<CryptoKey> {
  if (keyCache && Date.now() - keyCache.at < 300_000) return keyCache.key;
  const raw = await cfg("dashboard_signing_key");
  if (!raw) throw new Error("dashboard_signing_key missing from internal_config");
  const key = await crypto.subtle.importKey("raw", new TextEncoder().encode(raw),
    { name: "HMAC", hash: "SHA-256" }, false, ["sign"]);
  keyCache = { key, at: Date.now() };
  return key;
}

async function sign(v: string): Promise<string> {
  const sig = await crypto.subtle.sign("HMAC", await signingKey(), new TextEncoder().encode(v));
  return [...new Uint8Array(sig)].map((b) => b.toString(16).padStart(2, "0")).join("");
}

// Scoped to the one client whose report is being rendered. The dashboard function
// reads the client out of the signature and refuses to serve any other, so a render
// token that leaked could not be used to read the rest of the book.
//
// Mirrors makeToken() in dashboard/index.ts: exp.role.client.sig.
async function reportToken(clientId: number): Promise<string> {
  const body = `${Date.now() + TOKEN_TTL_MS}.client.${clientId}`;
  return `${body}.${await sign(body)}`;
}

/* --------------------------------------------------------------- summary -- */
// Same call the dashboard's "Generate summary" button makes. Best-effort: a
// timeout or an error here is swallowed (returned as {ok:false}) rather than
// thrown, because the report is still worth sending without a fresh summary —
// the Overview just shows "No summary has been written yet," same as today.
async function triggerSummary(token: string, clientId: number, from: string, to: string):
  Promise<{ ok: boolean; reason?: string }> {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), SUMMARY_TIMEOUT_MS);
  try {
    const res = await fetch(ASK_API, {
      method: "POST",
      headers: { "Content-Type": "application/json", "x-aim-session": token },
      signal: ctrl.signal,
      body: JSON.stringify({ mode: "report", client_id: clientId, from, to }),
    });
    const j = await res.json().catch(() => ({}));
    if (!res.ok || j.error) return { ok: false, reason: j.error ?? `ask ${res.status}` };
    if (!j.report) return { ok: false, reason: "ask returned no report (ran out of steps)" };
    if (j.saved === false) return { ok: false, reason: j.save_error ?? "generated but not saved" };
    return { ok: true };
  } catch (e) {
    return { ok: false, reason: String((e as Error).message ?? e) };
  } finally {
    clearTimeout(timer);
  }
}

/* ------------------------------------------------------------ rendering -- */

async function renderPdf(url: string): Promise<Uint8Array> {
  const apiKey = await cfg("browserless_api_key");
  const baseUrl = (await cfg("browserless_base_url") || "https://chrome.browserless.io").replace(/\/$/, "");
  if (!apiKey) throw new Error("browserless_api_key missing from internal_config");

  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), RENDER_TIMEOUT_MS);
  try {
    const res = await fetch(`${baseUrl}/pdf?token=${encodeURIComponent(apiKey)}`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      signal: ctrl.signal,
      body: JSON.stringify({
        url,
        options: { printBackground: true, format: "Letter",
          margin: { top: "0", bottom: "0", left: "0", right: "0" } },
        gotoOptions: { waitUntil: "networkidle2", timeout: RENDER_TIMEOUT_MS - 5000 },
        waitForSelector: { selector: "[data-report-ready]", timeout: RENDER_TIMEOUT_MS - 5000 },
      }),
    });
    if (!res.ok) {
      const text = await res.text().catch(() => "");
      throw new Error(`render failed (${res.status}): ${text.slice(0, 500)}`);
    }
    return new Uint8Array(await res.arrayBuffer());
  } finally {
    clearTimeout(timer);
  }
}

/* ------------------------------------------------------------- sending -- */

async function sendReport(apiKey: string, sender: string, senderName: string,
  to: string[], clientName: string, from: string, toDate: string, pdfBytes: Uint8Array): Promise<void> {
  const b64 = bytesToBase64(pdfBytes);
  const res = await fetch(SEND_API, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({
      api_key: apiKey,
      sender: senderName ? `${senderName} <${sender}>` : sender,
      to,
      subject: `${clientName} — Monthly Performance Report (${from} to ${toDate})`,
      html_body:
        `<p>Hi,</p><p>Attached is ${escapeHtml(clientName)}'s performance report for ` +
        `${from} to ${toDate}.</p><p>Questions? Just reply to this email.</p><p>— AIM</p>`,
      attachments: [{
        filename: `${clientName.replace(/[^A-Za-z0-9 _-]/g, "").trim() || "report"}-${toDate}.pdf`,
        fileblob: b64,
        mimetype: "application/pdf",
      }],
    }),
  });
  const body = await res.json().catch(() => ({}));
  const failures = body?.data?.failures;
  if (!res.ok || (Array.isArray(failures) && failures.length)) {
    throw new Error(`smtp2go send failed: ${JSON.stringify(body).slice(0, 500)}`);
  }
}

function escapeHtml(s: string): string {
  return String(s).replace(/[&<>"']/g, (m) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[m]!));
}

/* --------------------------------------------------------------- handler -- */

Deno.serve(async (req: Request) => {
  if (req.method !== "POST") return json({ error: "method_not_allowed" }, 405);
  const token = await cfg("collector_token");
  if (!ctEq(req.headers.get("x-collector-token") ?? "", token)) {
    return json({ error: "unauthorized" }, 401);
  }

  const started = Date.now();
  const deadlineAt = started + WALL_MS;
  let body: any = {};
  try { body = await req.json(); } catch { /* default: live full run */ }
  const dryRun = !!body.dry_run;
  const onlyClientId = body.client_id ? Number(body.client_id) : null;

  const to = new Date(); to.setUTCDate(to.getUTCDate() - 1);
  const toStr = to.toISOString().slice(0, 10);
  const fromDate = new Date(to); fromDate.setUTCDate(fromDate.getUTCDate() - (WINDOW_DAYS - 1));
  const fromStr = fromDate.toISOString().slice(0, 10);

  let q = db.from("clients").select("id,name,report_recipients").eq("active", true);
  q = onlyClientId ? q.eq("id", onlyClientId) : q.not("report_recipients", "is", null);
  const { data: clients, error: cErr } = await q;
  if (cErr) return json({ error: cErr.message }, 500);

  const targets = (clients ?? []).filter((c) => onlyClientId || (c.report_recipients?.length ?? 0) > 0);
  if (dryRun && targets.length !== 1) {
    return json({ error: "dry_run requires exactly one client_id" }, 400);
  }

  const siteUrl = (await cfg("dashboard_public_url")).replace(/\/$/, "");
  if (!siteUrl) return json({ error: "dashboard_public_url missing from internal_config" }, 500);

  const apiKey = dryRun ? "" : await cfg("report_smtp2go_api_key");
  const sender = dryRun ? "" : await cfg("report_sender_email");
  const senderName = dryRun ? "" : await cfg("report_sender_name");
  if (!dryRun && (!apiKey || !sender)) {
    return json({ error: "report_smtp2go_api_key or report_sender_email missing from internal_config" }, 500);
  }

  const results: any[] = [];
  for (const client of targets) {
    if (Date.now() > deadlineAt) { results.push({ client: client.name, status: "skipped_deadline" }); continue; }
    try {
      const token = await reportToken(client.id);
      const summary = await triggerSummary(token, client.id, fromStr, toStr);

      const reportUrl = `${siteUrl}/?report_token=${encodeURIComponent(token)}` +
        `&client=${client.id}&from=${fromStr}&to=${toStr}`;
      const pdfBytes = await renderPdf(reportUrl);

      if (dryRun) {
        return json({
          client: client.name, window: { from: fromStr, to: toStr }, summary,
          pdf_base64: bytesToBase64(pdfBytes),
        });
      }

      const recipients = (client.report_recipients ?? []).filter(Boolean);
      if (!recipients.length) { results.push({ client: client.name, status: "skipped_no_recipients" }); continue; }
      await sendReport(apiKey, sender, senderName, recipients, client.name, fromStr, toStr, pdfBytes);

      await db.from("collection_log").insert({
        source: "monthly_report", status: "success", rows_written: recipients.length,
        window_from: fromStr, window_to: toStr, duration_ms: Date.now() - started,
        error: `client=${client.name} to=${recipients.join(",")}`,
      });
      results.push({ client: client.name, status: "sent", to: recipients, summary });
    } catch (e) {
      const msg = String((e as Error).message ?? e);
      await db.from("collection_log").insert({
        source: "monthly_report", status: "error", rows_written: 0,
        window_from: fromStr, window_to: toStr, duration_ms: Date.now() - started,
        error: `client=${client.name}: ${msg}`.slice(0, 900),
      });
      results.push({ client: client.name, status: "error", error: msg });
    }
  }

  return json({ window: { from: fromStr, to: toStr }, clients: results.length, results });
});
