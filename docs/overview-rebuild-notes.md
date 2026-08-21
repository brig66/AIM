# Overview tab rebuild — backend findings

Notes for `index.html`, the dashboard at analytics.aim-tex.com.

**This repo is public.** Everything below is structural — API routes, function
names and JSON field names. No client figures are recorded here. Real payloads
are reproducible in one call each (see "Reproducing payloads").

## Status

The Overview tab has been rebuilt and `index.html` is now committed to this
repo. The earlier blocker — the file was not in the repo and
`analytics.aim-tex.com` was refused by the environment's egress policy — is
resolved: the environment now allows that host, so the live file was fetched,
rebuilt and committed.

Two backend items are still outstanding. The front end is written to handle
both, and to light up on its own once either lands:

1. **`/api/summary` is not in `ROUTES`.** `dash_summary(p_client, p_from, p_to)`
   exists in the database but the `dashboard` edge function (version 19) has no
   route to it, so the page cannot reach it. One line adds it:

   ```ts
   "/api/summary": { fn: "dash_summary", args: ["client", "from", "to"] },
   ```

   Until then the page's summary read 404s, which it treats as "nothing cached"
   and falls through to the Generate button — not as an error.

2. **`ask` does not cache its own output.** `POST /ask` with
   `{"mode":"report", ...}` returns a report but never writes to
   `report_summaries`, so only the hand-seeded row (Exceptional HR,
   2026-07-22 → 2026-08-20) has a stored summary. The page says so explicitly:
   a summary generated in the browser is labelled as unstored.

`ROUTES` is an allowlist by design — do not replace it with a name-prefix rule,
because `dash_set_password` is also a `dash_` function.

## What the rebuilt Overview reads

| Call | Used for |
| --- | --- |
| `/api/forms` → `.report` (`dash_report`) | every section except bounce rate |
| `/api/traffic_totals` (`dash_traffic_totals`) | bounce rate, sitewide and organic |
| `/api/summary` (`dash_summary`) | the cached executive summary |
| `POST /ask` `{mode:"report"}` | generating a summary on demand |

`dash_summary` `RETURNS TABLE`, so the RPC answers with an **array** of zero or
one rows. The page handles an array, a bare object and an absent route.

The two summary sources disagree on one field name: `dash_summary` returns
`next_steps`, `ask` returns `next`. The page accepts either.

`dash_report` keys and the sections they feed:

| Key | Fields | Feeds |
| --- | --- | --- |
| `rankings_by_engine` | `engine, tracked, top3, p4_10, page1, page2` | Front Page Placement |
| `rankings_unified` | same, minus `engine` | Front Page Placement footnote |
| `traffic` | `channel, users, sessions, engagement, conversions` | Search Traffic donuts, engagement |
| `backlinks` | `referring_domains, total_backlinks, new_links, lost_links, spam_score, domain_rank, dofollow_pct, referring_ips, day` | Backlinks |
| `health` | `source, status, last_success` | source chips and every "not connected" state |
| `gsc` | `clicks, impressions, ctr, position` | — (Search tab) |
| `ai`, `movers`, `top_queries`, `top_pages`, `gsc_months`, `traffic_months` | — | other tabs |

`engine` values: `google`, `google_local`, `bing`, `google_mobile`. The three
big tiles cover the first three; the stacked bars cover every engine returned,
so nothing is hidden.

`traffic[].engagement` is already a percentage. `traffic_totals[].bounce_rate`
is a fraction (0–1). Both are combined session-weighted for the sitewide figure
so a three-visit channel cannot swing it.

Channel names seen: `Direct`, `Organic Search`, `Referral`, `Email`,
`Organic Social`, `Cross-network`, `AI Assistant`, `Unassigned`.

## Gaps that shape what sections 5–8 can honestly show

The "never invent a number" rule bites here. Confirmed against the schema.

1. **Google Business Profile has no data and no route.** `fact_gbp` exists with
   the right columns but holds **0 rows for 0 clients**. There is no `dash_*`
   function, no `/api/` route, and `client_connections` carries no `gbp` source,
   so `dash_health` cannot even describe it as stale. Section 8 renders a
   "not connected" state naming the five metrics it will carry. `fact_gbp` also
   has no mobile-vs-desktop split; `device_category` lives on
   `fact_traffic_tech`, which is site traffic, not GBP.

2. **"New Users" and "Views" for the traffic donuts.** `new_users` is populated
   on `fact_traffic` but neither `dash_report` nor `dash_traffic_totals` selects
   it. **Views are not in the schema at all** — no column on `fact_traffic` or
   `fact_traffic_tech`. Both donuts render a "not measured yet" card that names
   the reason.

   Adding `new_users` to the `traffic` CTE in `dash_report` is enough to light
   the New Users donut up — the page draws a donut for any measure whose key is
   present on the traffic rows and needs no front-end change. Views need an
   ingest first.

3. **Engagement rate** is on `fact_traffic` and *is* already returned by
   `dash_report` as `traffic[].engagement`, so Website Performance works today.
   `dash_traffic_totals` still omits it, which is why bounce and engagement are
   read from two different calls.

## `client_connections` and `dash_health`

`d.health` comes from `client_connections`, whose `source` values are
`backlinks`, `ga4`, `gsc` and whose `status` values are `connected`, `error`,
`not_connected`. `dash_health(p_client)` is a separate, richer read
(`source, last_data, days_stale, nrows`) over eight fact tables: `rankings`,
`search_console`, `traffic`, `ai_visibility`, `backlinks`, `forms`,
`paid_media`, `social`. The Overview uses the bundled `health` array, via
`vHealth()`, so it costs no extra call.

A source with no row at all, a row with `status: 'not_connected'`, and a live
source that simply returned nothing in the window are three different states
and the page words each one differently. None of them renders as a zero.

## Reproducing payloads

Real payloads were deliberately not committed — this repo is public and the
data is client analytics. Regenerate them against Supabase when needed:

```sql
select to_jsonb(public.dash_report(:client, :from, :to));
select coalesce(jsonb_agg(to_jsonb(s)), '[]'::jsonb)
  from public.dash_summary(:client, :from, :to) s;
select coalesce(jsonb_agg(to_jsonb(t)), '[]'::jsonb)
  from public.dash_traffic_totals(:client, :from, :to) t;
select to_jsonb(public.dash_health(:client));
```

Capture those to fixture files outside the repo (`fixtures/` and
`*.fixture.json` are gitignored) to drive the headless render checks, so an
empty payload can be checked to produce an empty state rather than a throw.

## Checking a change to the tab

No build step. Extract the script block and syntax-check it, then render the
tab against a captured payload and against `{}`:

```js
global.document={getElementById:()=>({innerHTML:'',textContent:'',
  classList:{add(){},remove(){}},style:{},dataset:{},addEventListener(){},
  querySelectorAll:()=>[]}),addEventListener(){},querySelectorAll:()=>[],body:{}};
global.window={addEventListener(){},matchMedia:()=>({matches:false,addEventListener(){}})};
global.sessionStorage={getItem:()=>null,setItem(){},removeItem(){}};
global.fetch=async()=>({ok:true,status:200,json:async()=>({})});
global.location={href:'',origin:'https://analytics.aim-tex.com'};
```

Then `new Function(src + ';return {overview,S};')()` and assert on the returned
string. The rebuild was checked this way against a real payload, an empty
payload, a connected-but-empty payload, an errored connector, all four summary
states, and a payload with `new_users`/`views` added to confirm the donuts
appear with no code change.
