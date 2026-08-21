# Supabase backend

`dashboard-edge-function.ts` is the deployed source of the `dashboard` edge
function, kept here so the API surface is reviewable alongside the page that
calls it. Deployed as version 20.

## Roles

`POST /login` returns `{token, expires, role}`. The token is
`exp.role.signature`, signed over `exp.role`, so the role cannot be edited by
anything the browser can reach — relabelling a client token as admin invalidates
the signature.

Tokens minted before roles existed (`exp.signature`) are still accepted and
resolve to `admin`, because the only credential that could have minted one is
AIM's own. That is what stops a deploy signing anyone out mid-session; the form
disappears on its own as tokens expire.

Reads accept any valid token. Writes require `admin`.

**Still to do before client logins ship:** reads are not yet scoped by client. A
client token would currently be able to read any client's data by asking for a
different id. The role already travels in the token, so the remaining work is to
carry a `client_id` alongside it and filter every read against it.

## Write routes (admin only)

| Route | RPC | Notes |
| --- | --- | --- |
| `POST /api/prompt_add` | `dash_prompt_add` | Refuses duplicates, phrases under 8 or over 500 characters, and clients with no prompt set |
| `POST /api/prompt_active` | `dash_prompt_set_active` | Pause / resume |
| `POST /api/prompt_remove` | `dash_prompt_remove` | Deletes a phrase never asked; deactivates one with answers, so the trend survives |
| `POST /api/client_add` | `dash_client_add` | Generates the slug, refuses a duplicate domain, and seeds the three collector connection rows |
| `POST /api/client_update` | `dash_client_update` | Patch semantics — see below |
| `POST /api/keywords_add` | `dash_keywords_add` | Bulk; existing phrases are skipped rather than errored on |
| `POST /api/keyword_remove` | `dash_keyword_remove` | Deletes a phrase never checked; deactivates one with rank readings |

Reads added alongside them: `/api/clients_admin`, `/api/keywords_recent`.

### Patch semantics on client_update

The RPC takes a jsonb object. A key **present** is written, a key **absent** is
left alone, and an **explicit null** clears the field. That three-way
distinction is why it takes an object rather than one argument per column —
with plain arguments there is no way to say "clear this" that is distinct from
"do not touch this".

The edge function only forwards a fixed allowlist of keys. `slug` is not among
them: other systems join on it, so it is generated once at creation and never
edited.

### The AI prompt set is created on demand

`dash_client_add` seeds the collector connection rows but not an AI prompt set,
so the first tracked question for a new client had nothing to attach to and was
refused. Seven active clients were in that state.

`dash_prompt_set_ensure(p_client)` now creates one the first time a question is
added. Platforms and models are copied from the most recently created active set
rather than hardcoded, so a set made this way matches whatever the tracker
currently runs. Brand carries only what the client record says — name, and the
domain with and without `www.`.

**Competitors are left empty on purpose.** Naming a client's competitors is a
judgement call, and inventing them would put fabricated names into a client
deliverable. Every pre-existing set has them; a set created here does not, so
`dash_clients_admin` returns `prompt_set_id` and a `competitors` count and the
AI Visibility tab warns that share of voice cannot be calculated until they are
filled in. There is no UI for editing them yet.

### What a new client needs before it collects anything

`dash_clients_admin` returns a `ready` flag, true only when the client has a GA4
property, a Search Console property, and at least one active phrase. The Clients
tab leads with the ones that are not ready, because an incomplete client sits in
the dropdown collecting nothing and looks fine.

Every one of the 32 existing clients carries exactly one `client_connections`
row per collector, so `dash_client_add` seeds all three at `not_connected`.
Without them the dashboard cannot tell "never connected" from "source unknown".

### Normalisers

`dash_norm_domain`, `dash_norm_ga4` and `dash_norm_location` clean input at the
database rather than in the browser, so the same value typed three ways lands
once. GA4 accepts a pasted `properties/312345678`. A Search Console property is
validated against `sc-domain:…` or `https://…` and rejected otherwise, because a
wrong string there fails silently at collection time.

Named errors carry a plain-English `hint`, which the page shows verbatim rather
than "request failed".

## Migrations applied

- `dash_prompt_management_and_login_role` — `dash_check_login` (returns a role,
  leaving `dash_check_password` untouched so a half-applied deploy cannot lock
  anyone out), `dash_prompt_set_for`, `dash_prompts`, `dash_prompt_set_active`,
  `dash_prompt_remove`.
- `fix_dash_prompt_add_ambiguity` — the OUT column `prompt_ref` collided with
  `ai_prompts.prompt_ref` inside the uniqueness loop.

`/api/summary` was also added to `ROUTES`, which was the outstanding item from
the first round of dashboard work: the page had been treating its absence as
"nothing cached" and falling through to the Generate button.

## Prompt storage

Prompts live in `ai_prompts`, joined to a client through `ai_prompt_sets`
(342 prompts across 32 sets). `dash_prompts` returns each with its collected
answer count and a `branded` flag computed from the set's brand aliases on word
boundaries — the same rule the tracker skill applies.

## Form classification

`forms-classify-edge-function.ts` is the deployed source of `forms-classify`.

### What was wrong

Classification happened in `forms_classify()`, a Postgres rule called from
`forms_ingest`. Its final branch was:

```
if contactable and (substantive or nfields >= 3) then valid_lead, confidence 0.8
```

`nfields >= 3` alone is enough. Any form with an email and three filled boxes
became a valid lead regardless of what the message said, and the spam keyword
list matched 1 of the 195 rows classified that way. That is how vendor pitches
reached client reports as leads.

### What replaced it

The rule still runs at ingest as a first pass. `forms-classify` then re-reads
the message with a model, using the taxonomy from the Form Submission Report
skill: valid_lead, needs_review, needs_attention, suspicious, spam,
test_submission.

The prompt turns on one distinction — is the sender trying to **buy** from this
business or **sell** to it — and is given the client's name and domain so it can
tell a customer from a supplier. Contact details never make a lead.

Three things it will not do:

- **Rows with no retained content are never touched.** 482 of 781 submissions
  were confirmed by the relay without a body. They keep saying so rather than
  being guessed at.
- **A human ruling always wins.** `human_reviewed` rows are excluded from the
  select and the update carries the same predicate, so a decision made mid-run
  is not overwritten.
- **Nothing is written without a preview.** `dry_run` returns the full list of
  proposed moves and changes nothing. The dashboard's Forms tab previews first
  and only enables Apply once there is something to apply.

It reads every key *and* value, not just a field called "message". The email
parser sometimes splits a prose body across many keys, so the text that matters
can end up as a key — reading the whole payload makes classification immune to
how well the body happened to parse.

`classified_by` records which pass decided: `rule` or `llm`.

### Triggering it

`POST /api/forms_reclassify` on the dashboard function, admin only. It reads the
collector token from `internal_config` and calls `forms-classify` server to
server, so the browser never holds that credential.

### Still to do

- **The parser shreds some bodies.** 28 rows have prose split into fake keys,
  because `parseBody` falls back to pairing consecutive lines when it finds
  fewer than two `Label: Value` matches. Classification is unaffected — it reads
  keys as well as values — but those rows display badly.
- **No scheduled run.** New submissions get the rule at ingest and keep it until
  someone presses the button. It should run automatically after each collection.
