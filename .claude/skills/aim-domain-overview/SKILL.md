---
name: aim-domain-overview
description: >-
  Run a Semrush-style Domain Overview for any domain using the DataForSEO API instead of a
  Semrush subscription, and return the whole dashboard: authority, organic and paid traffic
  estimates, organic and paid keyword counts, referring domains and backlinks, traffic share
  against the closest organic competitors, a 24-month traffic and keyword-position trend, the
  Google SERP position distribution (organic vs AI Overviews vs other SERP features), the
  branded/non-branded split, top keywords, per-country breakdown, and an optional AI search
  visibility block covering AI Overviews, Google AI Mode, ChatGPT and Gemini. Use this
  WHENEVER someone hands over a domain and wants the overview numbers — "run a domain
  overview", "domain analysis for X", "what does this domain rank for", "how much traffic
  does X get", "pull the Semrush numbers for this domain", "check their authority and
  backlinks", "competitive snapshot", "AI visibility for this domain" — and whenever the ask
  is to replace a Semrush Domain Overview with DataForSEO. Outputs a structured JSON plus an
  AIM-branded one-page PDF. NOT for the full client-facing SEO audit deliverable (use
  aim-seo-audit) and NOT for a site's real clicks and impressions (use aim-gsc-analysis —
  DataForSEO estimates traffic, Search Console measures it).
---

# AIM Domain Overview (DataForSEO)

Rebuilds the Semrush **Domain Overview** dashboard on the DataForSEO API v3 — the same API
the AIM analytics platform already uses for rank tracking (`dfs-rank`) and backlinks
(`dfs-backlinks`), so one set of credentials covers all three.

Two outputs from one command: `overview.json` (every retrieved figure, its provenance and the
run's actual API spend) and an AIM-branded one-page PDF laid out like the dashboard.

## What you need

- **DataForSEO credentials** in the environment as `DATAFORSEO_LOGIN` / `DATAFORSEO_PASSWORD`.
  The scripts accept the same alternate names the Supabase collectors accept
  (`DFS_LOGIN`, `DATAFORSEO_API_KEY`, …), so a client `keys.env` works unchanged:
  ```bash
  set -a; . keys.env; set +a
  python3 scripts/dfs_client.py --check     # balance + which variables matched
  ```
  Never echo a credential. `--check` prints the *names* that matched, never the values.
- **Outbound access to `api.dataforseo.com`.** Cloud sessions on the Trusted network policy
  cannot reach it — set the environment's Network access to Custom and allow
  `api.dataforseo.com`, then start a **new** session (policy is read once at session start).
- **WeasyPrint** for the PDF: `pip install weasyprint`. Without it the builder writes the
  HTML instead and says so — the JSON is unaffected.

## Workflow

### 1. Price the run before spending
```bash
python3 scripts/domain_overview.py aim-tex.com --plan
```
Prints the list-price estimate per panel. A default run is a few cents; `--ai` is what moves
the number, because it is one API call per prompt per engine.

### 2. Pull the data
```bash
python3 scripts/domain_overview.py aim-tex.com --out overview.json
```
Useful flags:

| Flag | Effect |
| --- | --- |
| `--location "United States"` `--language en` | the market to report on (default US/en) |
| `--countries us,gb,de` | repeat the headline block per country |
| `--months 24` | length of the trend series (history starts 2020-10) |
| `--keywords-limit 1000` | ranked-keyword sample; drives the SERP distribution |
| `--brand-terms "aim,advanced integrated"` | fixes the branded/non-branded split |
| `--competitors 5` | competitor set behind traffic share |
| `--ai` | add the AI visibility block (costs more) |
| `--ai-engines ai_overview,ai_mode,chatgpt,gemini` | which engines to query |
| `--channels-json ga4.json` | supply a **measured** channel mix (see below) |

### 3. Render
```bash
python3 scripts/build_report.py overview.json          # -> <domain>-Domain-Overview-AIM.pdf
python3 scripts/build_report.py overview.json --html preview.html
```

### 4. Deliver
Lead with what changed and what it means, not the tile values — the PDF already carries those.
Say plainly which panels came back unavailable and why; they render as visible empty states,
so do not paper over them in the summary.

## The four numbers that are not Semrush's numbers

Semrush's headline metrics are proprietary. Four panels here are **AIM definitions computed
from DataForSEO inputs**, and each carries its own `definition` string in the JSON and in the
PDF footer. Say so when a client compares the two tools side by side:

1. **Authority (0–100)** — DataForSEO's 0–1000 domain rank ÷ 10. A different model from
   Semrush Authority Score; the two will not match and neither is "wrong".
2. **Traffic share** — this domain's estimated traffic over the pool formed by it and its top
   N organic competitors. Semrush computes share over a market-level competitor set.
3. **Branded split** — keyword-token matching against the brand tokens. Pass `--brand-terms`
   for anything the domain name does not imply.
4. **AI Visibility index** — per engine, 60% share of prompts naming the brand plus 40% share
   of prompts citing the domain, averaged. It scores *this run's prompt set*: with the default
   brand-recognition prompts it answers "do the engines know this brand", not "does this brand
   win the questions its buyers ask". For a client engagement, swap in the buyer-intent set
   from `aim-ai-visibility-tracker` via `--ai-prompts`.

Organic/paid traffic are DataForSEO **ETV estimates**, not sessions. For an AIM client, real
clicks and impressions come from Search Console — that is `aim-gsc-analysis`, and it beats any
estimate.

## The one panel DataForSEO cannot fill

Semrush's "Market Trends and Channels" strip (Direct / Referral / AI traffic / Organic / Paid)
is **clickstream** data. DataForSEO has no equivalent at any price, and neither does any
low-cost API — the sources for it are Similarweb and Semrush .Trends, both enterprise-priced.

So the skill never estimates it. For an AIM client the real mix already exists in GA4 (the
platform's `fact_traffic` / `dash_traffic_totals`); pass it in:

```bash
echo '[{"channel":"Organic Search","sessions":9310},{"channel":"Direct","sessions":32900}]' > ga4.json
python3 scripts/domain_overview.py client.com --channels-json ga4.json
```
For a prospect, leave it out. The panel then states why it is empty, which is a defensible
thing to say in a pitch — unlike a fabricated channel split.

## Cost

Every DataForSEO response reports its own `cost`; the run sums it into `meta.cost` and prints
it. Quote **that**, not the `--plan` estimate. Rough shape of a default US run: the Labs calls
and the backlink summary are cents, the ranked-keyword sample scales with `--keywords-limit`,
and `--ai` scales with prompts × engines. `references/endpoints.md` has the per-endpoint
model.

## Judgment notes

- **A capped keyword sample is the top of the profile, not the profile.** At
  `--keywords-limit 1000` on a large site the SERP distribution describes the highest-traffic
  keywords only. The JSON flags this as `sample_is_capped`; repeat it in the summary.
- **Trend beats level.** ETV levels differ between vendors by design. Month-over-month
  movement in one vendor's series is the defensible read.
- **A failed call is never zero.** The scripts raise on API failure and write
  `{"available": false, "reason": …}` rather than a 0. If a panel is missing, investigate
  before reporting — a rate limit and a domain with no keywords are opposite findings.
- **Franchise or multi-location domains**: the domain-level numbers cover the whole network.
  Run the subfolder as its own target when the question is about one location.

## Maintenance

`scripts/selftest.py` runs the whole pipeline offline against canned DataForSEO-shaped
payloads and renders the report — no credentials, no network, no cost. Run it after touching
any `panel_*` function, and whenever DataForSEO changes a response schema:

```bash
python3 scripts/selftest.py        # 24 checks; prints ALL CHECKS PASSED
```

It guards the failure that matters: a renamed field returns "no data", and on a dashboard
that reads as a domain with no keywords rather than as a broken parser.
`references/sample_overview.synthetic.json` is a fabricated overview for render-testing the
PDF on its own; the numbers in it are invented and must never reach a client.

## Related skills

- `aim-seo-audit` — the full client-facing branded audit (Semrush MCP + crawl + PageSpeed).
  This skill is the fast research snapshot behind it.
- `aim-gsc-analysis` — real Search Console clicks/impressions for AIM client sites.
- `aim-ai-visibility-tracker` / `aim-ai-visibility-assessment` — recurring AI citation
  monitoring and the scored AI-readiness deliverable. The `--ai` block here is a snapshot,
  not a replacement for either.
