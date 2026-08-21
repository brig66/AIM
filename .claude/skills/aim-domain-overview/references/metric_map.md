# Semrush Domain Overview → DataForSEO

Every tile in the Semrush dashboard, and what replaces it. "Same" means the two vendors are
measuring the same thing by comparable means; "different model" means the number will not
match Semrush's and should never be presented as if it did.

## SEO block

| Semrush tile | DataForSEO source | Parity |
| --- | --- | --- |
| Authority Score | `backlinks/summary/live` → `rank` (0–1000), reported ÷10 | **Different model.** Both are link-graph scores; the graphs and the curves differ. |
| Organic Traffic + % | Labs `domain_rank_overview` → `metrics.organic.etv`; % from the last two months of `historical_rank_overview` | Same *kind* of estimate (modelled clicks from ranked positions), different model. |
| Paid Traffic | `metrics.paid.etv` | Same kind. |
| Ref. Domains | `backlinks/summary/live` → `referring_domains` | **Same.** Index sizes differ, definition does not. |
| Traffic Share | derived: domain ETV ÷ (domain + top-N competitor ETV), competitors from Labs `competitors_domain` | **Different model.** Semrush computes share over a market-level set. |
| Organic Keywords | `metrics.organic.count` | **Same** definition; index coverage differs. |
| Paid Keywords | `metrics.paid.count` | Same. |
| Backlinks | `backlinks/summary/live` → `backlinks` | Same. |

## Charts

| Semrush chart | DataForSEO source | Notes |
| --- | --- | --- |
| Traffic, 2Y (organic / paid) | Labs `historical_rank_overview` | Monthly; history begins **2020-10-01**; refreshed weekly. |
| Traffic, branded | derived from Labs `ranked_keywords` by brand-token match | Approximation. Semrush's method is not published. Correct it with `--brand-terms`. |
| Keywords by position (Top 3 / 4–10 / 11–20 / 21–50 / 51–100) | `historical_rank_overview` `pos_*` fields | Semrush's Top 3 = DataForSEO `pos_1 + pos_2_3`; 21–50 and 51–100 are sums of the decile fields. |
| Keywords: AI Overviews / other SERP features | Labs `ranked_keywords` → `ranked_serp_element.serp_item.type` | Point-in-time, not a time series — DataForSEO does not carry a historical AI-Overview presence series. |
| Google SERP Positions Distribution donut | same, bucketed organic / AI-overview-ish / everything else | Sample is capped by `--keywords-limit`; the JSON flags `sample_is_capped`. |

## AI Search block

| Semrush row | DataForSEO source |
| --- | --- |
| AI Overview | SERP `google/organic/live/advanced` with `load_async_ai_overview: true`, reading the `ai_overview` item and its references |
| AI Mode | SERP `google/ai_mode/*` — the response mirrors the `ai_overview` element, including a `references` array |
| ChatGPT | AI Optimization `ai_optimization/chat_gpt/llm_responses/live` |
| Gemini | `ai_optimization/gemini/llm_responses/live` (Claude and Perplexity exist too and can be added to `--ai-engines`) |
| AI Visibility score | **AIM's own index.** Semrush's is proprietary. |
| Mentions / Cited Pages | prompts naming the brand / distinct URLs on the domain cited back |
| Top Cited Sources | host counts across every reference returned, all engines pooled |
| Distribution by Country | run the AI engines per location (`--countries` covers the SEO side today) |

**Parsing note.** The AI block finds mentions and citations by sweeping the *whole* response
payload for text and URLs rather than reading named fields. These schemas move as the engines
change, and a renamed field would otherwise return "no citations" — which reads as a
visibility collapse rather than a parser break. Over-collecting is the safe failure here.

## Market Trends and Channels

No DataForSEO equivalent, and no low-cost equivalent anywhere: Direct / Referral / AI-traffic
splits come from **clickstream panels**, sold by Similarweb (enterprise) and Semrush .Trends
(add-on). This skill leaves the panel empty and says why. For AIM clients the measured mix is
already in GA4 — pass it with `--channels-json`.

## Not in the dashboard, but cheap to add later

`domain_intersection` (keyword gap), `keywords_for_site`, `bulk_traffic_estimation` (score a
prospect list in one call), `domain_analytics/technologies` (tech stack), `backlinks/anchors`,
`backlinks/referring_domains`. All are Labs/Backlinks calls that fit the same client.
