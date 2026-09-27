# Anthropic prompt caching — `ask` edge function

Status: **proposed, not deployed.**

## Files

| File | What it is |
|---|---|
| `ask-edge-function.deployed.ts` | The deployed source, saved as the baseline for rolling back |
| `ask-edge-function.ts` | Proposed version with caching |
| `ai-collect-edge-function.deployed.ts` | Deployed source, for reference only. Not changed |
| `monthly-report-edge-function.deployed.ts` | Deployed source, for reference only. Not changed |

## What changes (ask only)

`ask` runs a tool loop of up to 6, 8 or 12 steps, depending on the mode. Before this change, every step re-sent the full system prompt, which includes the large `ai_schema` text. It also re-sent the tool definition and the whole conversation so far. None of it was cached. The date and client context also sat near the top of the system prompt, so no cache could have matched across requests anyway.

1. **System prompt is split into two blocks.**
   - Block 1 is static and ends with `cache_control: {type: "ephemeral"}`. It holds the role intro, the views and schema, "How to work", and the mode-specific "How to answer" text, including `TONE_RULES`.
   - Block 2 is volatile: "Today is …", the client context and the selected date range. It comes after the breakpoint, so it cannot break the cached prefix.
2. **Top-level `cache_control: {type: "ephemeral"}` on the request.** Automatic caching moves a breakpoint to the end of the growing conversation, so earlier turns and tool results are read from cache on the next step.
3. **Usage is summed across steps.** Every successful JSON response now includes a `usage` object with `input_tokens`, `output_tokens`, `cache_creation_input_tokens` and `cache_read_input_tokens`. Callers ignore unknown fields, so the extra key does not affect `monthly-report` or the dashboard.

Nothing else changes: prompts, wording, models, step limits and guardrails are all the same. The request uses 2 of the 4 allowed breakpoints.

## Expected effect

- Cache order is tools → system block 1 → messages. The static prefix is well above Sonnet 4.6's 1024-token minimum because of the schema text.
- Step 1 writes the prefix to cache at 1.25× the input price. Steps 2 and later read it back at 0.1×, and the same goes for the earlier conversation turns.
- For a multi-step run, input-token cost should drop sharply. Output tokens are unaffected.
- Requests in the same mode within the 5-minute TTL share block 1 across clients. An example is monthly-report generating summaries one client after another. The three modes (chat, report, panel) each cache their own block 1.
- Latency on steps 2 and later should improve somewhat.

## How to verify (after an authorised deploy)

1. Run any question that takes 2 or more steps.
2. Check `usage` in the response:
   - `cache_creation_input_tokens > 0` (the step-1 write)
   - `cache_read_input_tokens > 0` (from step 2 on; this is the key signal)
3. If `cache_read_input_tokens` stays at 0, check whether anything in block 1 varies per request. It must not contain the date, client or range. If the static text has fallen below 1024 tokens, it won't cache.

## Roll back

Redeploy `ask-edge-function.deployed.ts` as the `ask` function. It is the exact source that is currently deployed. No schema, config or secret changes are involved.

## ai-collect and monthly-report: no change

- **ai-collect**: each call sends only the one-line tracker prompt as a user message. There is no system prompt and no shared instruction prefix, so there is nothing ≥1024 tokens to cache. Its costs come from:
  - the number of prompts × engines × `ai_prompt_sets.repeats` (1–5) per run
  - Claude calls: `claude-sonnet-4-6` (overridable per set), `max_tokens: 1024`, with the `web_search_20250305` tool, so search fees and the search-result tokens it returns are billed on every call
  - other engines: Perplexity `sonar-pro`, OpenAI `gpt-4.1` with web_search, Gemini `gemini-2.5-flash` with google_search
  - up to 3 retries on 429/5xx
- **monthly-report**: does not call Claude directly. It calls `ask` (mode `report`) once per client, so it benefits from the `ask` change automatically.
- Note: in `ai-collect-edge-function.deployed.ts`, the whitespace character class in `norm()` may not match the deployed bytes exactly. The originals are probably non-breaking-space characters. Do not redeploy ai-collect from this copy; fetch it fresh if it is ever needed.
