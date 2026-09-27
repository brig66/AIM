# AIM Executive Dashboard: daily refresh job

You refresh the data behind AIM's Executive Dashboard, then email the owner a short morning brief.
Every call to QuickBooks, HubSpot, Supabase, Gmail and Outlook is READ-ONLY. Never create, update, send (except the one brief below), or delete anything in those systems.

Dashboard (Artifact): <dashboard artifact URL>
Its data store is read and written with the ArtifactData tool (load it with ToolSearch "select:ArtifactData").
Email the brief to: <owner email>, using the Gmail connector's send_message.

## 0. Load what you need
- `ArtifactData` get `ops/job` gives this document. `ops/recipe_finance`, `ops/recipe_hubspot` and `ops/recipe_ops` give the exact tool calls per source. `ops/script_tickets` and `ops/script_deals` give Python builders. Each has a `text` field: write it to a file in a scratch folder and run it with python3 after saving the raw pulls the recipe names.
- `ArtifactData` list `snapshot` gives yesterday's data. Keep it as a fallback.
- Work in a scratch folder and create `raw/` inside it.

## 1. Pull and build (one section per source; do them in parallel if you can)
Build these five JSON documents, following each recipe exactly. The shape must match yesterday's document for the same id.
1. `finance`: QuickBooks, per recipe_finance. Sum expense lines yourself (the report's "Total Expenses" header reads $0). Check that income − cogs − expenses ≈ net income for every month.
2. `tickets` and `deals`: HubSpot, per recipe_hubspot, then script_tickets and script_deals (set AS_OF and W90 at the top of each).
3. `clients` and `ops`: Supabase project hfmvugxpwgagvxsjbjfn (SELECT only) plus Gmail and Outlook receipt searches, per recipe_ops.
   - Build `clients` and `ops` yourself from the query results (no builder script); match yesterday's shape.
   - In `clients`, set each client's `risk` from its flags, but do NOT count `rank_down` toward risk while the dashboard notes say rank collection is unreliable (compute `risk_excluding_rank` too and use it as `risk`). Keep `rank_down` in `flags` for display.
   - Semrush has been cancelled. Never include it in spend or alerts.

If a source fails (connector error, auth expired), do not write a broken document. Keep yesterday's version, and add an alert saying which source did not refresh and why.

## 2. Build `meta`
```
{ "generated_at": ISO timestamp, "as_of": "YYYY-MM-DD",
  "sources": {"finance": as_of or "stale since …", "tickets": …, "deals": …, "clients": …, "ops": …},
  "summary": "2–3 plain sentences a business owner reads first: cash, anything that changed since yesterday, the single most important thing to act on.",
  "alerts": [ {"severity":"critical|warn|info","area":"Cash|Collections|Support|Clients|Sales|Spend|Platform|Data","text":"plain English, one sentence, with the number"} ] }
```
Alert rules. Include only items outside the normal range, at most 12, most severe first:
- Collections: any customer balance 31+ days past due (warn); 61+ days (critical). Undeposited funds over $10,000 (info).
- Support: open tickets with no owner (warn); any open ticket older than 14 days (warn); first-reply median for the last 7 days over 2× the 90-day median (warn).
- Clients: each high-risk client (warn); a client with leads down (warn); Google Search Console permission errors (info).
- Sales: a deal that moved to Won or Lost since yesterday's snapshot (info); open deals with a past close date, as a count (info).
- Spend: AI spend this month on pace to exceed last month by 25% or more (warn); any failed payment or billing-problem email from a vendor in the last 7 days (critical).
- Platform: a data feed with errors or stale for more than 3 days (warn).
- Data: sources that did not refresh (warn).
Contract alerts (contracts ending, billing below expected) are computed by the dashboard itself from the contracts the owner enters. Do not duplicate them.

## 3. Write the snapshot
One `ArtifactData` batch of six `set` operations: `snapshot/finance`, `snapshot/tickets`, `snapshot/deals`, `snapshot/clients`, `snapshot/ops`, `snapshot/meta`. Each document must stay under 250 KB. Then read `snapshot/meta` back to confirm the write.

## 4. Email the brief
Subject: `AIM daily brief · <Weekday, Mon D>`
The body is short HTML. Use AIM's colors (indigo #352597 heading bar, ink #241d3d text), no images and no attachments:
- The summary sentences.
- A small table of seven figures, each with its change since yesterday where you have one: cash in bank, receivables past due, open tickets (unowned), open pipeline excluding nurture, clients at high risk, AI spend this month, net income month to date.
- The alerts as a list (severity word first).
- "Contracts": read the `contracts` collection. List any contract ending within 60 days, or already ended, and state how many active clients still have no expected monthly value.
- The dashboard link as plain text: <dashboard artifact URL>
Send exactly one email per run. Contact names, email addresses and phone numbers of clients' customers must never appear in the brief.

## 5. Finish
Reply with one line: which sources refreshed, the alert count, and whether the email was sent.
