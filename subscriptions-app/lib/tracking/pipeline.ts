/**
 * Orchestrates one client's tracking run end to end: query every active
 * engine for every active tracking_prompts row, score the results, render
 * the AIM-branded PDF, upload it to Supabase Storage, insert the scorecard
 * row, and email it — used by both the Vercel Cron route and the admin
 * "Run now" button so there is exactly one code path for a run.
 */
import { createSupabaseAdminClient } from "@/lib/supabase/server";
import type { Client, TrackingPrompt } from "@/lib/supabase/types";
import { queryEngine, queryMock, type Engine } from "./engines";
import { analyze } from "./analyze";
import { computeRunMetrics, type PromptOutcome } from "./scoring";
import { renderScorecardPdf } from "./pdf";
import { sendEmail } from "@/lib/resend";

const ALL_ENGINES: Engine[] = ["perplexity", "openai", "gemini", "anthropic"];
const SCORECARDS_BUCKET = "scorecards";

/** True if this engine has an API key configured; used to decide the "live" roster. */
function engineIsConfigured(engine: Engine): boolean {
  const map: Record<Engine, string> = {
    perplexity: "PERPLEXITY_API_KEY",
    openai: "OPENAI_API_KEY",
    gemini: "GEMINI_API_KEY",
    anthropic: "ANTHROPIC_API_KEY",
  };
  return Boolean(process.env[map[engine]]);
}

function normalizeDomain(input: string): string {
  try {
    const withScheme = input.includes("://") ? input : `https://${input}`;
    const host = new URL(withScheme).hostname.toLowerCase();
    return host.startsWith("www.") ? host.slice(4) : host;
  } catch {
    return input.replace(/^www\./i, "").toLowerCase();
  }
}

export interface RunResult {
  runId: string;
  status: "success" | "failed";
  errorMessage?: string;
}

/**
 * Runs the full pipeline for one client and returns the outcome. Never
 * throws — failures are captured, written to `runs.error_message`, and
 * raised as a critical admin_alerts row instead, per spec ("never fail
 * silently").
 */
export async function runTrackingForClient(client: Client, opts?: { mock?: boolean }): Promise<RunResult> {
  const supabase = createSupabaseAdminClient();
  const now = new Date();
  const periodEnd = now.toISOString().slice(0, 10);
  const periodStart = periodStartFor(client.plan, now).toISOString().slice(0, 10);

  const mock = opts?.mock ?? !ALL_ENGINES.some(engineIsConfigured);
  const engines = mock ? ALL_ENGINES : ALL_ENGINES.filter(engineIsConfigured);

  // Idempotency: don't double-run a client that already has a successful run
  // covering this period.
  const { data: existing } = await supabase
    .from("runs")
    .select("id,status")
    .eq("client_id", client.id)
    .eq("period_end", periodEnd)
    .eq("status", "success")
    .maybeSingle();
  if (existing) {
    return { runId: existing.id, status: "success" };
  }

  const { data: runRow, error: runInsertError } = await supabase
    .from("runs")
    .insert({
      client_id: client.id,
      period_start: periodStart,
      period_end: periodEnd,
      status: "running",
      engines_queried: engines,
      started_at: now.toISOString(),
    })
    .select()
    .single();

  if (runInsertError || !runRow) {
    await raiseAlert(supabase, client.id, `Could not create a run row for ${client.company_name}: ${runInsertError?.message}`);
    return { runId: "", status: "failed", errorMessage: runInsertError?.message };
  }

  try {
    const { data: prompts, error: promptsError } = await supabase
      .from("tracking_prompts")
      .select("*")
      .eq("client_id", client.id)
      .eq("active", true);
    if (promptsError) throw new Error(`Loading tracking_prompts failed: ${promptsError.message}`);
    if (!prompts || prompts.length === 0) {
      throw new Error("Client has no active tracking prompts configured.");
    }

    const outcomes = await queryAllPrompts(client, prompts, engines, mock);
    const metrics = computeRunMetrics(outcomes);

    const pdfBuffer = await renderScorecardPdf({
      companyName: client.company_name,
      websiteDomain: client.website_domain,
      periodStart,
      periodEnd,
      engines,
      metrics,
      outcomes,
      isMock: mock,
    });

    const storagePath = `${client.id}/${periodEnd}.pdf`;
    const { error: uploadError } = await supabase.storage.from(SCORECARDS_BUCKET).upload(storagePath, pdfBuffer, {
      contentType: "application/pdf",
      upsert: true,
    });
    if (uploadError) throw new Error(`Uploading scorecard PDF failed: ${uploadError.message}`);

    const { data: scorecard, error: scorecardError } = await supabase
      .from("scorecards")
      .insert({
        run_id: runRow.id,
        client_id: client.id,
        overall_score: metrics.overall.score,
        branded_score: metrics.branded.score,
        nonbranded_score: metrics.nonbranded.score,
        pdf_storage_path: storagePath,
      })
      .select()
      .single();
    if (scorecardError || !scorecard) {
      throw new Error(`Inserting scorecard row failed: ${scorecardError?.message}`);
    }

    const emailResult = await sendEmail({
      to: client.contact_email,
      subject: `Your AI Visibility Scorecard — ${periodEnd}`,
      html: scorecardEmailHtml(client, metrics.overall.score, periodStart, periodEnd),
      attachments: [{ filename: `AI-Visibility-Scorecard-${periodEnd}.pdf`, content: pdfBuffer }],
    });

    if (emailResult.sent) {
      await supabase.from("scorecards").update({ emailed_at: new Date().toISOString() }).eq("id", scorecard.id);
    } else {
      await raiseAlert(
        supabase,
        client.id,
        `Scorecard for ${client.company_name} generated but email delivery failed: ${emailResult.error}`,
        "warning"
      );
    }

    await supabase
      .from("runs")
      .update({ status: "success", finished_at: new Date().toISOString() })
      .eq("id", runRow.id);

    return { runId: runRow.id, status: "success" };
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    await supabase
      .from("runs")
      .update({ status: "failed", error_message: message, finished_at: new Date().toISOString() })
      .eq("id", runRow.id);
    await raiseAlert(supabase, client.id, `Tracking run failed for ${client.company_name}: ${message}`);
    return { runId: runRow.id, status: "failed", errorMessage: message };
  }
}

async function queryAllPrompts(
  client: Client,
  prompts: TrackingPrompt[],
  engines: Engine[],
  mock: boolean
): Promise<PromptOutcome[]> {
  const brandAliases = [client.company_name];
  const primaryDomain = normalizeDomain(client.website_domain);
  const brandDomains = [primaryDomain];

  const outcomes: PromptOutcome[] = [];
  for (const prompt of prompts) {
    for (const engine of engines) {
      const res = mock
        ? queryMock(prompt.prompt_text, engine, primaryDomain)
        : await queryEngine(engine, prompt.prompt_text);
      const outcome: PromptOutcome = {
        promptId: prompt.id,
        promptText: prompt.prompt_text,
        isBranded: prompt.is_branded,
        engine,
        analysis: analyze(res, { brandAliases, brandDomains, primaryDomain }),
        error: res.error,
      };
      outcomes.push(outcome);
      // Be polite to provider rate limits when running live; skip the delay in mock mode.
      if (!mock) await sleep(500);
    }
  }
  return outcomes;
}

function sleep(ms: number) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function periodStartFor(plan: string, now: Date): Date {
  const start = new Date(now);
  if (plan === "weekly") {
    start.setDate(start.getDate() - 7);
  } else {
    start.setMonth(start.getMonth() - 1);
  }
  return start;
}

async function raiseAlert(
  supabase: ReturnType<typeof createSupabaseAdminClient>,
  clientId: string,
  message: string,
  severity: "info" | "warning" | "critical" = "critical"
) {
  await supabase.from("admin_alerts").insert({ client_id: clientId, message, severity });
}

function scorecardEmailHtml(client: Client, score: number, periodStart: string, periodEnd: string): string {
  return `
  <div style="font-family:Arial,sans-serif;color:#1c1830;max-width:560px;margin:0 auto">
    <div style="height:6px;background:linear-gradient(90deg,#352597,#c0228a 55%,#e7730d)"></div>
    <div style="padding:24px 8px">
      <p style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:#c0228a;margin:0 0 6px">AI Visibility &middot; Scorecard</p>
      <h1 style="font-size:20px;color:#352597;margin:0 0 12px">${escapeHtml(client.company_name)}</h1>
      <p>Hi ${escapeHtml(client.contact_name)},</p>
      <p>Your AI Visibility Scorecard for ${periodStart} to ${periodEnd} is attached as a PDF.</p>
      <p style="font-size:32px;font-weight:bold;color:#352597;margin:20px 0 4px">${score}%</p>
      <p style="color:#6b6480;margin-top:0">Overall visibility score this period</p>
      <p>Log in to your client portal any time to review past scorecards, update your tracking prompts, or manage billing.</p>
      <p style="margin-top:24px;color:#6b6480;font-size:12px">Advanced Integrated Marketing, Inc. &middot; aim-tex.com</p>
    </div>
  </div>`;
}

function escapeHtml(s: string): string {
  return s.replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]!));
}

/** Which active clients are due for a run today, based on plan cadence. */
export async function findDueClients(): Promise<Client[]> {
  const supabase = createSupabaseAdminClient();
  const { data: clients, error } = await supabase.from("clients").select("*").eq("status", "active");
  if (error || !clients) return [];

  const due: Client[] = [];
  for (const client of clients) {
    const { data: lastRun } = await supabase
      .from("runs")
      .select("period_end,status")
      .eq("client_id", client.id)
      .order("period_end", { ascending: false })
      .limit(1)
      .maybeSingle();

    if (!lastRun) {
      due.push(client);
      continue;
    }
    const daysSince = (Date.now() - new Date(lastRun.period_end).getTime()) / 86_400_000;
    const cadenceDays = client.plan === "weekly" ? 7 : 28;
    if (daysSince >= cadenceDays) due.push(client);
  }
  return due;
}
