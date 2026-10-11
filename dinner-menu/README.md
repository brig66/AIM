# Brig & Dawn's Weekly Dinner Menu

Every Saturday at 10:00 AM Central, this project plans four fast dinners for two,
builds a magazine-style email, and sends it to brig@aim-tex.com and dawn.serman@mac.com.

**Status:** Not yet live. The scheduled task is not enabled until the test send is verified
and Brig confirms the sending method. See "Current status" below.

## What is in this folder

| File | What it is | Who changes it |
|---|---|---|
| `preferences.json` | Household rules: recipients, schedule, banned dishes and ingredients, food-safety temperatures | Brig, by asking Claude |
| `ratings.csv` | Confirmed feedback only (5, 4, 3 stars, or retired). Unknown dates stay unknown. | Updated by Claude when Brig sends feedback |
| `meal_history.csv` | Every menu sent or proposed, so dishes are not repeated too soon | Updated by Claude after each send |
| `menus/2026-10-17.json` | The menu for one week: recipes, grocery list, prep plan, sources | Written by Claude each week |
| `build_menu.py` | Builds the HTML email from a menu file, runs every safety and rule check, and saves a copy | Not edited by hand |
| `tests/test_build_menu.py` | Automatic tests that prove the rules still work | Not edited by hand |
| `out/<date>/email.html` | The finished email, saved as a fallback copy | Created by the builder |
| `out/<date>/validation.txt` | Every check and whether it passed | Created by the builder |

## How a weekly run works

1. Load `preferences.json`, `ratings.csv`, and `meal_history.csv`.
2. Pick four dinners: exactly four, each 30 minutes or less, with different proteins,
   no lemon-garlic chicken, no mushrooms, no cream sauces, and no dish served in the last
   8 weeks unless it is an Encore.
3. Verify each source page and its total time. Record which links were checked.
4. Write the menu file and run `build_menu.py`. If any check fails, do not send.
5. Check Sent Items for an existing email with the same subject. If one exists, do not send again.
6. Send the HTML email, then confirm it appears in Sent Items with the right subject,
   recipients, and date.
7. Save the menu to `meal_history.csv` and save the HTML copy.

If sending fails, the saved HTML is kept, the exact error is reported, and the schedule stays on.

## Giving feedback

Reply in plain words, for example:

- "Garlic shrimp pasta 5 stars"
- "Harissa chicken 3 stars, too spicy"
- "Chimichurri steak: don't include again"

Claude records only what you say. A dish that appeared on a menu is not a rating.

Scale: 5 stars Make Again (Hall of Fame), 4 stars Keep in Rotation, 3 stars Good but Not Often,
and "Don't Include Again" retires a dish.

## Changing preferences or pausing

- **Change a preference** (for example, "no fish" or "more vegetables"): tell Claude in a
  message. Claude updates `preferences.json` and the next menu follows it.
- **Pause the weekly email:** ask Claude to pause the schedule. It is turned off in the
  Routines list and nothing else changes.
- **Resume:** ask Claude to turn the schedule back on.

## Where the schedule lives

The schedule is a Claude Routine. It runs on Claude's servers, not on a computer, so it runs
even when no session is open. To see it, open the Routines list in Claude, or ask Claude to
show it. Its exact next run and recipients are shown when it is set up.

## Current status

- [x] Menu builder and rules: built and tested (65 checks, 11 tests).
- [x] Outlook send test: a short formatting probe went to Brig and appears in Sent Items.
- [ ] Full styled test send: waiting on the sending-method decision below.
- [ ] Recipe source pages re-checked: not possible from the build environment (recipe sites
      were blocked by the network policy). Each source is marked "not re-checked" in the email.
- [ ] Scheduled Routine: not created yet.
- [ ] Old ChatGPT task disabled: Brig to do this after the first Claude delivery is verified.

## Important: avoid duplicate emails

The old ChatGPT task may still be enabled and send on the same Saturday. Disable it after
Claude's first delivery is verified. Two Saturday menus would be a duplicate, and Claude cannot
disable the ChatGPT task.

## Sending method decision

Outlook (Microsoft 365) is the sending method the spec names. Verified on this account:
the text and structure of the email arrive, but Outlook removes all colors, fonts, and spacing.
The result is readable but plain.

The styled AIM briefings use Gmail instead, which keeps the design but sends from the Gmail
account, not brig@aim-tex.com. Choose one before the schedule is turned on.
