import { Resend } from "resend";

let _resend: Resend | null = null;

function getResend(): Resend | null {
  const key = process.env.RESEND_API_KEY;
  if (!key) return null;
  if (!_resend) _resend = new Resend(key);
  return _resend;
}

const FROM = process.env.RESEND_FROM_EMAIL || "AIM AI Visibility <onboarding@resend.dev>";

export async function sendEmail(opts: {
  to: string;
  subject: string;
  html: string;
  attachments?: { filename: string; content: Buffer }[];
}): Promise<{ sent: boolean; error?: string }> {
  const resend = getResend();
  if (!resend) {
    // Never hard-crash a webhook/cron over a missing email key in dev/sandbox —
    // log loudly instead so the gap is visible without taking down the pipeline.
    // eslint-disable-next-line no-console
    console.warn(`[resend] RESEND_API_KEY not set — skipping email to ${opts.to}: "${opts.subject}"`);
    return { sent: false, error: "RESEND_API_KEY not set" };
  }
  try {
    const { error } = await resend.emails.send({
      from: FROM,
      to: opts.to,
      subject: opts.subject,
      html: opts.html,
      attachments: opts.attachments?.map((a) => ({
        filename: a.filename,
        content: a.content,
      })),
    });
    if (error) return { sent: false, error: error.message };
    return { sent: true };
  } catch (err) {
    return { sent: false, error: err instanceof Error ? err.message : String(err) };
  }
}
