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

## Fixed: Generate summary signed you out (ask v10 → v12)

Reported as "on iPad or phone, the Generate summary button kicks me out and I
have to log back in". It was not mobile-specific and not a timeout — the iPad
was simply the only device in the logs.

`dashboard` v24 added roles and changed the token it mints:

```
exp.role.sig    signature covers "exp.role"      <- current
exp.sig         signature covers "exp"           <- pre-roles, still accepted
```

`dashboard.tokenRole()` accepts both. The `ask` function was never updated and
still read the two-part form:

```ts
const [e, sig] = tok.split(".");   // sig = "admin", not the signature
if (sig.length !== want.length) return false;   // 5 !== 64 -> 401
```

So every `/ask` call 401'd in about 300 ms. `genSummary()` in the page treats a
401 as a dead session — it clears `sessionStorage` and shows the login screen —
so a perfectly good login was thrown away on every press of the button. The
edge logs show exactly this: three `POST /functions/v1/ask` 401s from an iPad,
each followed by a fresh login, while `/api/*` on the same token returned 200
throughout.

The fix ports `dashboard.tokenRole()` into `ask` verbatim, so both functions
accept the same two shapes. It is strictly more permissive than before, so no
existing session is invalidated by the deploy. Mirrored here in
`supabase/ask-edge-function.ts`.

Deploy note: `deploy_edge_function` defaults `verify_jwt` to **true**, and this
function needs it **false** — it does its own `x-aim-session` HMAC check, and
the browser sends no JWT. The first deploy (v11) flipped it on; v12 restored it
with identical code (same `ezbr_sha256`). No `/ask` request arrived during the
75 seconds it was wrong. Always pass `verify_jwt: false` when redeploying this
function or `dashboard`.

## Summaries are now stored (ask v13)

`ask` writes every generated report to `report_summaries` before returning it,
so a summary survives a reload and past periods can be read back and compared.

`report_summaries` is `UNIQUE (client_id, period_from, period_to)`, so the write
is an upsert on that key: regenerating a period replaces that period's row
rather than piling up duplicates. One row per client per period is exactly what
`dash_summary` reads, and what makes period-to-period comparison clean.

A failed write must not discard a report the user waited a minute for, so the
outcome is returned rather than thrown: the response carries `saved` and
`save_error`, and the page's caption says which of the three states it is in —
read from store, written and stored, or written but not stored (with the
reason).

### The date range has to be fixed for a summary to be found again

`dash_summary` matches `period_from` and `period_to` **exactly**, and the preset
range buttons (Week, 30 days, 90 days…) are computed from *today* — so their
boundaries move every day. A summary generated under a preset is stored, but
tomorrow's version of the same preset is a different period and will not find
it.

The From/To boxes are the ones to use for anything worth keeping: a fixed period
such as a calendar month is stable, so it hits the stored row every time and can
be compared against other fixed periods. This is a property of rolling windows,
not a bug — "the last 30 days" genuinely means something different each day.

## Fixed: a 401 from ask no longer signs the user out

`genSummary()` treated any 401 from `ask` as proof the dashboard session had
expired, cleared `sessionStorage` and dropped to the login screen. That is what
made the token mismatch above so painful: a working login was thrown away every
time the button was pressed.

`ask` and `dashboard` are separate functions with separate copies of the token
check, and they have already drifted apart once. So a 401 from one is no longer
taken as evidence about the other: `sessionAlive()` re-checks the token against
`/api/clients`, and the session is only cleared when the dashboard agrees it is
dead. Otherwise the page keeps the session and shows an error explaining that
`ask` is out of step and needs redeploying. A network failure returns "alive",
so losing signal never signs anyone out.

### Still outstanding

Nothing on the summary path. The remaining gaps are the data ones above:
Google Business Profile has no ingest, and `new_users` / `engagement_rate` are
collected but not exposed by `dash_traffic_totals`.

## Front Page Placement now shows movement, not just today

Asked for after a side-by-side with Agency Analytics, which draws this as four
separate stacked bar charts — one per engine, monthly. Four charts means four
y-axes, so the engines cannot be read against each other and the eye has to
hold four pictures at once.

What the Overview draws instead is **one line chart on a single axis**, a line
per engine, under the stacked bars that were already there. The bars answer
"how is today split"; the lines answer "which way is this going". Neither
answers the other's question, which is why both are on the page.

What was added:

| Piece | Where |
| --- | --- |
| `dash_rank_movement(p_client, p_from, p_to)` | database, mirrored in `supabase/dash_rank_movement.sql` |
| `idx_rank_kw_asof` on `rank_intervals` | database, mirrored in `supabase/idx_rank_kw_asof.sql` |
| `/api/rank_movement` | `ROUTES` in the `dashboard` edge function (v25) |
| `ovMovement()`, `lineChart()`, `moveSeries()`, `movDelta()`, `tileMove()` | `index.html` |

`supabase/README.md` carries the backend reasoning. The rest of this section is
what the front end does with it.

### Reading the chart

- **Two measures, one toggle.** Page one (1–10) is reach; the top three (1–3)
  is the part that actually gets clicked. The toggle is a view of data already
  fetched, so switching re-renders and never re-reads.
- **The last point equals the tiles above it.** That is a property of the RPC
  (see the README), and the tiles' own "▲ +59 since May 2026" line is computed
  from the same series, so the headline and the chart cannot drift apart.
- **Buckets follow the selected range** — weekly at 70 days or less, monthly
  beyond, up to 24 points. The x labels thin out rather than overlap, and the
  crosshair readout names the bucket for the ones that are not printed.
- **Coverage is called out when it moves.** Where an engine's tracked count
  changed by 10% or more across the window, the paragraph under the chart says
  so by name. Google Local went from 67 phrases to 303 at the DataForSEO
  cutover; without that sentence its line reads as a collapse in ranking.

### Why it is drawn the way it is

- **One colour per engine, fixed, in `--e1`–`--e4`.** Held apart from
  `--c1`–`--c8`, which colour position bands and traffic channels: an engine
  must not change colour because the number of traffic channels did. Both the
  light and dark sets were checked for colour-blind separation, chroma, the
  lightness band and contrast against the card they sit on — the dark steps are
  chosen, not lightened copies of the light ones.
- **Identity never rests on colour alone.** Every line is labelled at its
  right-hand end as well as in the legend, deltas carry ▲/▼ as well as green or
  orange, and `Show these numbers as a table` renders the whole series as a
  table for screen readers, for print, and for anyone who would rather read
  figures.
- **The crosshair and its readout are `pointer-events: none`.** An `opacity: 0`
  element still catches the pointer, and the readout spans the whole plot, so
  without that it would swallow hovers meant for other buckets.
- **The chart floors at 680px and scrolls inside its card.** Below that the
  SVG's own text stops being legible; the table is the small-screen answer.

### Checking a change to it

`ovPlacement()` is pure — it reads `S.rankMove` and its argument and returns a
string — so it can be exercised head-first with the harness above. The states
worth keeping green: a real payload on both measures, `S.rankMove` null (the
route not deployed, or the read failed), `points: []`, a single bucket, an
engine present in only some buckets, an all-zero client, and 24 buckets. None
of them may throw, and none may render a zero where nothing was measured.
