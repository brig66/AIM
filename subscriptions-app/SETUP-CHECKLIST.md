# Setup Checklist (for Brig — no code editor needed)

Follow these steps in order. Every step is something you click through on a website. Nothing here requires opening a code editor or running a command.

---

## 1. Create a Stripe account and get your keys + prices

1. Go to https://dashboard.stripe.com/register and create an account (or log into your existing one).
2. In the left sidebar, click **Developers** → **API keys**. Copy the **Secret key** (starts with `sk_`). Keep this page open or copy the value somewhere safe — you'll paste it into Vercel in Step 6.
3. Click **Product catalog** → **Add product**.
   - Name it something like "AI Visibility Tracker — Monthly".
   - Under pricing, choose **Recurring**, set the price and billing period to **Monthly**.
   - Save the product, then click into it and copy the **Price ID** (starts with `price_`). This is your `STRIPE_PRICE_MONTHLY` value.
4. Repeat step 3 for a second product/price: "AI Visibility Tracker — Weekly", recurring **Weekly**. Copy its Price ID — this is `STRIPE_PRICE_WEEKLY`.

## 2. Turn on automatic payment receipt emails

1. In Stripe, go to **Settings** → **Customer emails** (or search "Emails" in settings search).
2. Turn on **"Successful payments"**. Stripe will now automatically email a receipt to every client after each charge — you don't need to build or send anything for this.

## 3. Create a Cloudflare Turnstile site (blocks bot signups)

1. Go to https://dash.cloudflare.com/ and log in or sign up (free).
2. In the left sidebar, find **Turnstile** and click **Add site**.
3. Enter a name (e.g. "AIM Signup Form") and your domain (e.g. `aim-tex.com` or your Vercel domain).
4. Choose the **Managed** widget type and create it.
5. Copy the **Site Key** — this is `NEXT_PUBLIC_TURNSTILE_SITE_KEY`.
6. Copy the **Secret Key** — this is `TURNSTILE_SECRET_KEY`.

## 4. Create a Resend account (sends the scorecard emails)

1. Go to https://resend.com/signup and create an account.
2. Go to **Domains** → **Add Domain**, enter your sending domain (e.g. `aim-tex.com`), and add the DNS records it shows you at your domain registrar (GoDaddy, Cloudflare, etc. — wherever you manage DNS for that domain). Wait for it to show **Verified** (can take a few minutes to a few hours).
3. Go to **API Keys** → **Create API Key**. Copy it — this is `RESEND_API_KEY`.
4. Decide the "from" address emails should come from, e.g. `reports@aim-tex.com` — this is `RESEND_FROM_EMAIL` (format: `AIM AI Visibility <reports@aim-tex.com>`).

## 5. Deploy the app to Vercel

1. Go to https://vercel.com and log in (or sign up) with your GitHub account.
2. Click **Add New** → **Project**.
3. Select the `brig66/AIM` GitHub repository.
4. On the configuration screen, find **Root Directory** and set it to `subscriptions-app` (click "Edit" next to Root Directory and browse/select it).
5. Leave the framework preset as **Next.js** (Vercel detects this automatically).
6. Don't click Deploy yet — go to Step 6 first to add your environment variables, since the app won't work correctly without them.

## 6. Add every environment variable in Vercel

Still on the Vercel project setup screen (or afterwards under **Project Settings** → **Environment Variables**), add each of these one at a time (Name, then Value), then click **Deploy** (or **Save** + **Redeploy** if the project already exists):

| Variable name | Where to get the value |
|---|---|
| `NEXT_PUBLIC_SUPABASE_URL` | `https://rrmilbbyzulopuesqwic.supabase.co` (already provisioned — just paste this exactly) |
| `NEXT_PUBLIC_SUPABASE_ANON_KEY` | `sb_publishable_AoSdTTLIgN1MG5A_u5aLKw_frhfD03h` (already provisioned — paste exactly) |
| `SUPABASE_SERVICE_ROLE_KEY` | Supabase Dashboard → your project → **Project Settings** → **API** → **service_role** key (click "Reveal") |
| `STRIPE_SECRET_KEY` | From Step 1.2 above |
| `STRIPE_WEBHOOK_SECRET` | See Step 6a below — you'll come back and fill this in after creating the webhook |
| `STRIPE_PRICE_MONTHLY` | From Step 1.3 above |
| `STRIPE_PRICE_WEEKLY` | From Step 1.4 above |
| `RESEND_API_KEY` | From Step 4.3 above |
| `RESEND_FROM_EMAIL` | From Step 4.4 above, e.g. `AIM AI Visibility <reports@aim-tex.com>` |
| `CRON_SECRET` | Make up any long random string yourself (e.g. mash your keyboard for 30+ characters, or use a password generator website). Write it down. |
| `NEXT_PUBLIC_TURNSTILE_SITE_KEY` | From Step 3.5 above |
| `TURNSTILE_SECRET_KEY` | From Step 3.6 above |
| `ADMIN_PASSWORD` | Make up a strong password for your own `/admin` login. Write it down somewhere safe (e.g. a password manager). |
| `ADMIN_SESSION_SECRET` | Make up another long random string (different from `CRON_SECRET`). |
| `PERPLEXITY_API_KEY` | Your existing AIM Perplexity API key (same one the AI Visibility Tracker skill already uses) |
| `OPENAI_API_KEY` | Your existing AIM OpenAI API key |
| `GEMINI_API_KEY` | Your existing AIM Google Gemini API key |
| `ANTHROPIC_API_KEY` | Your existing AIM Anthropic (Claude) API key |
| `NEXT_PUBLIC_SITE_URL` | Your production URL once you know it, e.g. `https://track.aim-tex.com` or the `.vercel.app` URL Vercel gives you after first deploy |

Click **Deploy**. Once it finishes, Vercel shows you the live URL (something like `aim-subscriptions-app.vercel.app`). Note it down.

### 6a. Create the Stripe webhook (do this after your first deploy)

1. In Stripe, go to **Developers** → **Webhooks** → **Add endpoint**.
2. Endpoint URL: `https://YOUR-VERCEL-URL/api/webhooks/stripe` (use your real deployed URL from Step 6).
3. Click **Select events** and check: `checkout.session.completed`, `invoice.paid`, `customer.subscription.updated`, `customer.subscription.deleted`.
4. Save the endpoint, then click into it and copy the **Signing secret** (starts with `whsec_`).
5. Go back to Vercel → **Project Settings** → **Environment Variables**, edit `STRIPE_WEBHOOK_SECRET`, paste this value in, and save.
6. Trigger a **Redeploy** from Vercel's Deployments tab so the new value takes effect.

## 7. Confirm the Vercel Cron job is enabled

1. In your Vercel project, go to **Settings** → **Cron Jobs**.
2. You should see one job listed: `/api/cron/run-tracking`, running daily. This is what checks every active client each day and sends out any monthly/weekly scorecards that are due.
3. If it's not there, redeploy the project (Cron Jobs are picked up automatically from the `vercel.json` file in the code — no manual setup needed beyond making sure the project deployed successfully).
4. Note: Vercel Cron Jobs on the free (Hobby) plan may run only once per day and can be delayed by up to an hour; this is fine for a monthly/weekly product. If you're on Vercel Pro, cron timing is more precise.

## 8. Test end-to-end before going live

1. In Stripe, toggle **Test mode** on (top-right switch in the Stripe dashboard).
2. Go to your live signup page: `https://YOUR-VERCEL-URL/signup` and go through the whole flow using a test card number `4242 4242 4242 4242`, any future expiry date, any CVC.
3. Confirm: you land on the success page, you receive a welcome email, and a separate email arrives with a portal login link.
4. Log into `/portal` with that link and confirm you can see your dashboard, add/edit tracking prompts, and that "Manage billing" opens Stripe's billing portal.
5. Log into `/admin` with your `ADMIN_PASSWORD` and confirm you can see the test client in the table, and click **Run now** next to it — this should generate a real (or mock, if your engine API keys aren't loaded yet) scorecard PDF and mark the run as "success". Check the Alerts table for anything red.
6. Once everything above works, switch Stripe back to **Live mode**, and repeat Steps 1–2 for your live API keys and webhook if you haven't already set those up as live-mode equivalents (Stripe keeps test and live keys/webhooks separate).

---

## How fraud and abuse are handled (for your awareness — nothing to set up beyond the above)

- **Stripe Radar** — Stripe automatically screens every card charge for fraud. This is on by default with no extra setup once your Stripe account is connected.
- **Cloudflare Turnstile** — blocks automated bots from submitting the signup form (Step 3).
- **Secret-protected routes** — the daily cron job and the Stripe webhook both require a secret key that only Vercel and Stripe know, so random people on the internet can't trigger a tracking run or fake a payment event.
- **Signup rate limiting** — the signup form blocks more than 5 attempts per 10 minutes from the same visitor/email as a basic speed bump. This isn't bulletproof against a determined attacker (a technical limitation of how the hosting works), but combined with Turnstile and Stripe Radar it's enough for a small self-service signup flow. If you ever see unusual signup activity, let your developer know — a stronger version of this control can be added later.

## What happens automatically, forever, with zero manual work from you

- A client signs up, pays, and sets their own tracking prompts.
- Every month (or week, per their plan), the system checks who's due, runs their prompts against ChatGPT, Perplexity, Gemini, and Claude, scores the results, and emails them a branded PDF scorecard.
- If a payment fails, Stripe handles retrying it and you get an alert on `/admin`.
- If anything breaks (an AI engine is down, a PDF fails to generate, an email bounces), it shows up as a red alert on `/admin` — that's the one page you should glance at now and then.
