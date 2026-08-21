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
