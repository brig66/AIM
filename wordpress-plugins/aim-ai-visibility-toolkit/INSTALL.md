# AIM AI Visibility Toolkit — how to install and use it

Built for **sentrimax.com** from the AI Visibility Checklist dated 12 September 2026 (rubric v3.1.0).

You do not need to touch any code. Everything is done from screens in WordPress.

---

## 1. Install it

1. Log in to WordPress as an administrator.
2. Go to **Plugins → Add New → Upload Plugin**.
3. Choose `aim-ai-visibility-toolkit.zip` and click **Install Now**.
4. Click **Activate**.
5. A new **AI Visibility** menu appears in the left-hand sidebar.

**Nothing on the website changes yet.** Every front-end change ships switched off.

---

## 2. Check the details before anything goes live

The plugin comes pre-filled with the addresses, phone numbers and profile links
recorded in the assessment. Verify them — do not assume they are current.

Work through these three screens:

| Screen | What to check |
|---|---|
| **Organization** | Company name, website, phone, description, year founded. **The email address is deliberately blank** — the assessment could not evidence one, and it is one of the fields the rule measures. Fill it in. |
| **Locations** | All three plants: Edmonton, Mansfield, Kitchener. Street, town, state/province, postal code, phone, opening hours. Check the opening hours against the real shop hours. |
| **People** | **Blank on purpose.** Add whoever should be the named, accountable engineer. Nothing publishes for a person until you enter a name. |

While you are on the Locations screen, note the warning: a single mismatched
abbreviation costs the rule. The address in the plugin and the address printed
on your contact page have to match character for character.

---

## 3. Turn it on safely

On the **Dashboard**:

1. Click **Turn on Preview mode** — changes will be visible to logged-in administrators only.
2. Click **Turn output on**.
3. Open the site in your normal browser tab (still logged in) and walk a few pages: the homepage, the contact page, a product page, the news page.
4. Go to **Health Check** and read the results.
5. When you are happy, come back to the Dashboard and click **Turn Preview mode off (go live)**.

Two addresses help while you are checking, and only work for administrators:

- Add `?aim_avt=off` to any page address to see it as if the plugin were switched off.
- Add `?aim_avt=debug` to see it normally with a diagnostic note added at the bottom of the page source.

---

## 4. Do Task 1.1 this week

This is the urgent one, and it is separate from everything else.

Go to **Phase 1 — Technical** and click **Scan the site now**. The scan changes
nothing; it tells you where the spam links actually are.

- **If they are in your pages or comments** — tick the boxes under "Clean the database" and run it. A restore point is saved first.
- **If the scan says the links render on the page but are nowhere in the database** — that is the injection signature. Switch on the **live filter** immediately, then treat it as a security incident: audit plugins and themes, review every administrator account, check the theme folder for unexpected files, force a password reset, and ask your host to run a malware scan. The live filter hides the symptom. It does not fix a compromise.

Removing these links **deliberately costs 15 points**. That is the correct
outcome and the projected scores already account for it.

---

## 5. If anything looks wrong

There are three levels of undo, all on the **Undo & Restore** screen.

1. **Turn everything off now** — one button, at the top of every plugin screen. Every front-end change stops instantly and the site renders exactly as it did before. Your settings are kept.
2. **Restore a point in time** — every save and every database change saved one first. Restoring puts back settings, page content, deleted comments and files on disk. Restoring is itself undoable: a backup of the current state is taken before any restore runs.
3. **Deactivate the plugin** — stops all output, keeps all data.

---

## 6. Shortcodes

Paste these into any page or post.

| Shortcode | What it does |
|---|---|
| `[aim_locations]` | Prints all three addresses from the same source the schema uses, so the visible text and the markup cannot drift apart. Add `location="edmonton"` for one plant. |
| `[aim_map location="edmonton"]` | A place-linked map embed, replacing the address-query embeds that pass no signal. Add `key="..."` with a Google Maps Embed API key for a live map. |
| `[aim_testimonials]` | The testimonials, with Review markup and the aggregate rating. |
| `[aim_case_studies]` | An index of published case studies. |
| `[aim_press]` | The press and recognition list, grouped by type. |
| `[aim_pricing]` | The pricing bands, with Offer markup. Put this on `/decanter-centrifuge-repair-cost/`. |

---

## 7. What the plugin will not do

These are refusals, not missing features:

- **It will not invent a rating.** `aggregateRating` is published only once enough real, attributed, visible reviews exist to substantiate it. An unsubstantiated rating is a policy violation and engines cross-check it.
- **It will not publish a price it made up.** A pricing band with no figure in it renders nothing at all.
- **It will not rewrite the 24 city URLs.** The assessment is explicit that the current slugs are correct for buyers and for search, and that rewriting them to satisfy a detector would trade real equity for a reporting artifact.
- **It will not delete a page.** The duplicate equipment URL is redirected, not deleted, so the change stays reversible.
- **It will not write your case studies or articles.** It supplies the structure, the fields and the markup. The measurements and the written customer permission have to come from the shop.

---

## 8. What still needs a person

The plugin covers every task it can. These cannot be automated:

| Task | Who |
|---|---|
| 2.1 — collect six attributed customer quotes, and ask four or five customers for a Google review | Sentrimax |
| 2.4 — claim the Google Business Profiles, add Thomasnet and association directories | Sentrimax |
| 3.1 — three case studies with real measurements and written permission | Sentrimax engineering |
| 3.2 — two technical articles a month, 1,200+ words | Sentrimax engineering |
| 3.4 — pitch trade publications; engage no paid link vendors | AIM + Sentrimax |
| 3.5 — extend the About page past 900 words | AIM + Sentrimax |

The checklist is blunt about the critical path: the single most valuable thing
Sentrimax can do in week one is nominate one engineer as the source for
case-study and article material, and identify five customers willing to be
quoted. Everything in Phase 3 depends on those two decisions, and they take a day.

---

## Requirements

WordPress 5.8 or newer, PHP 7.4 or newer. No other plugins required. Designed to
sit alongside your existing SEO plugin rather than replace it — by default it
merges the missing fields into the Organization node your site already publishes
instead of adding a competing one.
