# Overview tab rebuild — backend findings

Notes captured while preparing the Overview tab rebuild for `index.html`
(the dashboard at analytics.aim-tex.com).

**This repo is public.** Everything below is structural — API routes, function
names and JSON field names. No client figures are recorded here. Real payloads
are reproducible in one call each (see "Reproducing payloads").

## Status: blocked on the source file

`index.html` is not in this repo and was not reachable from the session that
wrote these notes:

- The repo was empty — zero commits, zero branches.
- `analytics.aim-tex.com` was denied by the cloud environment's egress policy
  (403 on CONNECT). The environment was set to **Trusted** network access,
  which allows only package registries, GitHub and cloud SDKs.

To let a session fetch the live file, set the environment's **Network access**
to **Custom** and add `analytics.aim-tex.com` to **Allowed domains** (tick
"Also include default list of common package managers"). Network policy is read
once at session start, so this only takes effect in a **new** session.

The Supabase MCP connector is unaffected by that policy — MCP traffic does not
go through the session allowlist — which is why the findings below were
obtainable anyway.

## Edge function

`dashboard` edge function, version 19. Auth is unchanged: `POST /login` returns
`{token, expires}`; `/api/*` requires the token in the `x-aim-session` header.

`ROUTES` maps each API path to one `dash_*` RPC. The map is an allowlist by
design — do not replace it with a name-prefix rule, because `dash_set_password`
is also a `dash_` function.

### `/api/summary` is still missing

`dash_summary(p_client, p_from, p_to)` **exists in the database** but has no
entry in `ROUTES`, so the dashboard cannot reach it yet. Adding it is one line:

```ts
"/api/summary": { fn: "dash_summary", args: ["client", "from", "to"] },
```

The RPC returns exactly the documented shape:

```
{ summary, points: [{label, text}], why, next_steps: [], generated_at, model, age_hours }
```

`POST /ask` with `{"mode":"report", ...}` still does not write to
`report_summaries`, so only the hand-seeded row has a cached summary. Every
other client falls through to the "Generate summary" button.

## Which RPC feeds which Overview section

`dash_report(p_client, p_from, p_to)` covers most of the page in one call:

| Key | Type | Fields | Feeds |
| --- | --- | --- | --- |
| `rankings_by_engine` | array | `engine, tracked, top3, p4_10, page1, page2` | Front Page Placement |
| `rankings_unified` | object | same, minus `engine` | Front Page Placement |
| `traffic` | array | `channel, users, sessions, engagement, conversions` | Search Traffic donuts |
| `backlinks` | object | `referring_domains, total_backlinks, new_links, lost_links, spam_score, domain_rank, dofollow_pct, referring_ips, day` | Backlinks |
| `gsc` | object | `clicks, impressions, ctr, position` | header context |
| `health` | array | `source, status, last_success` | "not connected" states |
| `ai` | array | `engine, answers, mention_rate, citation_rate, competitor_presence, primary_domain_rate` | — |
| `movers`, `top_queries`, `top_pages`, `gsc_months`, `traffic_months` | arrays | — | — |

`engine` values seen in `rankings_by_engine`: `google`, `google_local`, `bing`
— matching the three tiles the Overview needs.

`dash_traffic_totals(p_client, p_from, p_to)` returns one row per channel:
`channel, users, sessions, bounce_rate, conversions`. `bounce_rate` is a
fraction (0–1), not a percentage.

Channel names seen: `Direct`, `Organic Search`, `Referral`, `Email`,
`Organic Social`, `Cross-network`, `AI Assistant`, `Unassigned`.

## Three gaps that change what sections 5–8 can honestly show

The "never invent a number" rule bites here. Confirmed against the schema:

1. **Google Business Profile has no data and no route.** `fact_gbp` exists with
   the right columns (`profile_views, search_views, maps_views, calls,
   direction_requests, website_clicks, reviews_total, avg_rating`) but holds
   **0 rows for 0 clients**. There is no `dash_*` function and no `/api/` route
   exposing it, and `dash_health` does not report a `gbp` source at all — so
   the existing `vHealth()` pattern cannot even describe it as stale. Section 8
   can only render a "not connected" state until the GBP ingest is built.
   `fact_gbp` also has no mobile-vs-desktop split; `device_category` lives on
   `fact_traffic_tech`, which is site traffic, not GBP.

2. **"New Users" and "Views" for the Search Traffic donuts.** `new_users` is
   populated on `fact_traffic` but `dash_traffic_totals` does not select it.
   **Views/pageviews are not in the schema at all** — no column on
   `fact_traffic` or `fact_traffic_tech`. Either the donut set drops to Total
   Users + New Users, or a pageviews ingest is added first.

3. **Engagement rate.** Also populated on `fact_traffic`, also not returned by
   `dash_traffic_totals`. Bounce rate *is* returned per channel, so the
   sitewide-vs-organic bounce comparison works today; the engagement half of
   Website Performance needs the RPC widened.

Gaps 2 and 3 are both fixed by adding `new_users` and `engagement_rate` to the
`dash_traffic_totals` select list — the underlying data is already there.

## `dash_health` sources

`rankings`, `search_console`, `traffic`, `ai_visibility`, `backlinks`, `forms`,
`paid_media`, `social`. Each row is `{source, nrows, last_data, days_stale}`.
A source with `nrows: 0` and `last_data: null` is the "not connected" case.

## Reproducing payloads

Real payloads were deliberately not committed — this repo is public and the
data is client analytics. Regenerate them against Supabase when needed:

```sql
select to_jsonb(public.dash_report(:client, :from, :to));
select to_jsonb(public.dash_summary(:client, :from, :to));
select to_jsonb(public.dash_traffic_totals(:client, :from, :to));
select to_jsonb(public.dash_health(:client));
```

Capture those to local fixture files (outside the repo) to drive the headless
render tests, so an empty payload can be checked to produce an empty state
rather than a throw.
