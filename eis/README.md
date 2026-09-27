# Executive dashboard

A private, sign-in-only dashboard for AIM's owner: cash, client contracts,
HubSpot pipeline with deal modeling, budget vs. actual, support tickets,
client health, AI and software spend, and platform health.

**This repo is public.** No figures live here. The page is a shell; all data
lives in the Artifact's own data store, which only people the owner invites can
read, and which cannot be shared by public link.

| File | What it is |
| --- | --- |
| `dashboard.html` | Source of the published Artifact page |
| `daily-job.md` | What the scheduled morning job does (the live copy is stored in the Artifact's data store at `ops/job`, readable only by the owner) |

## Data store layout

| Path | Written by | Who can read |
| --- | --- | --- |
| `snapshot/{finance,tickets,deals,clients,ops,meta}` | the daily job (owner only) | anyone the owner shares with |
| `contracts/<id>` | Editors, on the Clients tab | anyone shared |
| `budget/<year>` | Editors, on the Budget tab | anyone shared |
| `deal_models/<hubspot deal id>` | Editors, on the Pipeline tab | anyone shared |
| `settings/main` | Editors | anyone shared |
| `ops/*` (job instructions, source recipes, builder scripts) | owner | owner only |

## Contract status rules

- **Month-to-month**: billing type is month-to-month.
- **Renewed**: the "client since" date is more than a month before the current contract's start.
- **First year**: first contract, within 12 months of starting.
- **Initial term, year 2+**: first contract, past its first year.
- End date defaults to start + term − 1 day. Contracts ending within 60 days, or already ended, are flagged.

## Projection

Contracted revenue per month comes from contracts with an expected monthly
value. Contracts that end drop off unless "assume renewals" is on. Modeled
deals add their monthly value from their start month for their term, or once for
one-time projects. Direct costs are a percentage of revenue (defaulting to the
year-to-date COGS ratio). Operating expenses come from the budget when one is
entered, otherwise from the average of the last three full months.
