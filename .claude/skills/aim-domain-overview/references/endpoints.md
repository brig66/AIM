# Endpoints, payloads and cost

Auth is HTTP Basic (`login:password`), one task per POST body array element. Success is
`status_code == 20000` at both the response and the task level — a 20000 response can still
carry a failed task, which is why `dfs_client.call()` checks both and raises rather than
returning an empty result.

## Calls this skill makes

| Panel | Endpoint | Key payload fields |
| --- | --- | --- |
| headline, countries | `POST /v3/dataforseo_labs/google/domain_rank_overview/live` | `target`, `location_name`, `language_code` |
| trends | `POST /v3/dataforseo_labs/google/historical_rank_overview/live` | `target`, `location_name`, `language_code`, `date_from` |
| serp_features, branded, top_keywords | `POST /v3/dataforseo_labs/google/ranked_keywords/live` | `target`, `limit` (≤1000), `order_by`, `load_rank_absolute` |
| competitors | `POST /v3/dataforseo_labs/google/competitors_domain/live` | `target`, `limit`, `order_by: ["intersections,desc"]` |
| backlinks | `POST /v3/backlinks/summary/live` | `target`, `backlinks_status_type: "live"`, `include_subdomains` |
| backlinks_trend | `POST /v3/backlinks/timeseries_summary/live` | `target`, `date_from`, `group_range: "month"` |
| ai / AI Overview | `POST /v3/serp/google/organic/live/advanced` | `keyword`, `location_name`, `load_async_ai_overview: true` |
| ai / AI Mode | `POST /v3/serp/google/ai_mode/live/advanced` | `keyword`, `location_name` |
| ai / ChatGPT, Gemini | `POST /v3/ai_optimization/{chat_gpt,gemini}/llm_responses/live` | `user_prompt`, `web_search` |
| credential check | `GET /v3/appendix/user_data` | — |

Response field names used by the parsers: `metrics.organic.{count,etv,pos_1,pos_2_3,pos_4_10,
pos_11_20,pos_21_30…pos_91_100,is_new,is_up,is_down,is_lost}`; `items[].keyword_data.keyword`,
`items[].ranked_serp_element.serp_item.{type,rank_group,rank_absolute,url,etv}`;
`backlinks/summary` `{rank,backlinks,referring_domains,referring_main_domains,referring_ips,
broken_backlinks,backlinks_spam_score,referring_links_attributes.nofollow,first_seen}`.

Where a field is absent the parsers fall through to `None` and the panel still renders — the
report shows a dash, never a zero standing in for a missing value.

## Cost model

Read actual spend from `meta.cost.total_cost_usd`, which sums the `cost` field DataForSEO
returns on every response. The `--plan` estimate uses published list prices, which move:
verify at <https://dataforseo.com/pricing> before quoting a client.

Shape of it (list price, US, at time of writing):

- **DataForSEO Labs** — a per-call task fee around $0.011 plus a per-returned-row fee around
  $0.0001. So `ranked_keywords` at `--keywords-limit 1000` is the most expensive non-AI call
  in the run by an order of magnitude; the other Labs calls are a cent or two each.
- **Backlinks** — ~$0.02 per `summary`/`timeseries_summary` call.
- **SERP live advanced** — ~$0.002 per keyword, plus ~$0.0006 for the async AI Overview.
  The *Standard queue* (`task_post` + `tasks_ready`) is roughly a third of live pricing, which
  is why the platform's `dfs-rank` collector uses the queue for daily volume. This skill uses
  live endpoints because an interactive overview cannot wait 45 minutes.
- **AI Optimization / LLM responses** — a base task fee plus a pass-through LLM fee that
  scales with prompt and answer size, so treat `--ai` as the variable line.

Ten prompts across four engines is forty calls. Trim `--ai-engines` before trimming prompts:
one engine on the real buyer-intent set tells you more than four engines on generic ones.

## Rate limits and failure

2,000 API calls per minute account-wide, ≤100 tasks per POST. `dfs_client` sends one task per
call, retries transport errors three times with exponential backoff, and raises `DFSError` on
anything else. Panels catch that individually, so one dead endpoint costs one panel, not the
run.

## Alternatives considered

- **SerpApi / Serper / ValueSERP** — SERPs only. No backlink graph, no historical rank
  overview, no traffic estimate. Cheaper per SERP, but they cannot fill this dashboard.
- **Ahrefs / Moz / Semrush APIs** — subscription-gated (Semrush and Ahrefs API access start in
  the hundreds per month), which is the thing being replaced.
- **Similarweb** — the only real source for the channel-mix strip, enterprise-priced, not
  pay-as-you-go.
- **Google Search Console API** — free, measured, and better than every estimate here, but
  only for sites AIM verifies. It complements this skill; it cannot replace it for prospects
  and competitors. That path is `aim-gsc-analysis`.
- **OpenPageRank** — free domain-authority proxy. Worth adding only as a cross-check on the
  authority tile; it carries no traffic, keyword or backlink detail.

DataForSEO is the only pay-as-you-go source that covers the whole dashboard in one account.
