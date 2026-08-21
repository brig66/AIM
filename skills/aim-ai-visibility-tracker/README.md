# aim-ai-visibility-tracker — branded / non-branded split

`branded-split.patch` is the change applied to the synced skill at
`~/.claude/skills/synced/aim-ai-visibility-tracker/`, kept here because the
skill itself lives outside this repo and the change is worth reviewing.

## Why

A branded prompt names the company. AI cites a company back to itself nearly
every time it is asked about by name, so a blended citation rate is dominated by
however many branded prompts happen to be in the set — add three and the score
rises without anything improving.

On a live client the gap was 100% branded against 12.5% non-branded, blending to
30%. The 30% is the number that had been on the report.

## What changed

- `Prompt` gains `branded: bool | None`. `None` means detect it.
- `is_branded()` — an explicit `branded:` in the config wins; otherwise the
  prompt is branded if it names the brand or an alias on a word boundary, with a
  `brand` tag as a fallback.
- `results.branded` column, written at collection time so the split survives into
  later re-reporting. Databases written before this gain the column on open;
  their rows stay NULL and are reported as `unclassified_answers` rather than
  being counted as non-branded.
- `compute_metrics()` returns `non_branded` and `branded` blocks. The blended
  figures stay under both their old names and `blended_*`, so a number quoted
  from an earlier run can still be reconciled.
- The PDF leads with the non-branded rates, adds a branded vs non-branded table,
  and states the blended rate as blended. A run with no non-branded prompts falls
  back to the blended figures rather than inventing a bucket.

## Checked

Branded detection across eight cases including alias matching, word boundaries
and both explicit overrides; migration from a pre-split database including
idempotency; a full mock run; and the report wording for all three shapes
(both buckets, branded only, legacy run).
