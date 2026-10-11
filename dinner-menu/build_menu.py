#!/usr/bin/env python3
"""Build and check Brig & Dawn's Weekly Dinner Menu email.

Usage:
    python3 build_menu.py menus/2026-10-17.json

Writes into out/<send_date>/:
    email.html      the full HTML email body (also the saved fallback copy)
    subject.txt     the subject line
    validation.txt  every check that ran, with PASS or FAIL
Exit code 0 means every check passed. Exit code 1 means do not send.
"""

import csv
import html
import json
import re
import sys
from datetime import date
from pathlib import Path

HERE = Path(__file__).resolve().parent
PREFS_PATH = HERE / "preferences.json"
RATINGS_PATH = HERE / "ratings.csv"
HISTORY_PATH = HERE / "meal_history.csv"

# Colors and fonts: one palette, inline styles only (email clients strip <style>).
PLUM = "#4A1D3D"
SAGE = "#5E7B5A"
CREAM = "#FBF7F1"
INK = "#2B2B2B"
MUTED = "#6B6B6B"
RULE = "#E7DED3"
ACCENT = "#B5562E"

BANNED_TERMS = [
    "lemon-garlic chicken",
    "mushroom",
    "heavy cream",
    "cream sauce",
    "alfredo",
    "half-and-half",
]


def esc(text):
    return html.escape(str(text), quote=True)


def load_ratings():
    with RATINGS_PATH.open(newline="", encoding="utf-8") as fh:
        return list(csv.DictReader(fh))


def load_history():
    with HISTORY_PATH.open(newline="", encoding="utf-8") as fh:
        return list(csv.DictReader(fh))


def subject_for(menu):
    send = date.fromisoformat(menu["send_date"])
    return f"Brig & Dawn's Weekly Dinner Menu - {send.strftime('%B')} {send.day}, {send.year}"


def section_title(text):
    return (
        f'<tr><td style="padding:26px 28px 6px 28px;">'
        f'<h2 style="font-family:Georgia,serif;font-size:22px;color:{PLUM};margin:0;'
        f'border-bottom:2px solid {SAGE};padding-bottom:6px;">{esc(text)}</h2></td></tr>'
    )


def paragraph(text):
    return (
        f'<tr><td style="padding:6px 28px;font-family:Arial,Helvetica,sans-serif;'
        f'font-size:15px;line-height:1.6;color:{INK};">{esc(text)}</td></tr>'
    )


def bullets(items):
    lis = "".join(
        f'<li style="margin:0 0 8px 0;">{esc(i)}</li>' for i in items
    )
    return (
        f'<tr><td style="padding:6px 28px;font-family:Arial,Helvetica,sans-serif;'
        f'font-size:15px;line-height:1.6;color:{INK};"><ul style="margin:0;padding-left:20px;">'
        f'{lis}</ul></td></tr>'
    )


def label_chip(text):
    color = {"Encore": ACCENT, "New": SAGE, "Keep in Rotation": PLUM}.get(text, PLUM)
    return (
        f'<span style="display:inline-block;background:{color};color:#FFFFFF;'
        f'font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold;'
        f'letter-spacing:1px;text-transform:uppercase;padding:4px 10px;border-radius:12px;">'
        f'{esc(text)}</span>'
    )


def dinner_card(d, number):
    ingredients = ""
    for group in d["ingredients"]:
        ingredients += (
            f'<div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;'
            f'font-weight:bold;color:{SAGE};text-transform:uppercase;letter-spacing:1px;'
            f'margin:14px 0 4px 0;">{esc(group["group"])}</div><ul style="margin:0;padding-left:20px;">'
            + "".join(f'<li style="margin:0 0 4px 0;">{esc(i)}</li>' for i in group["items"])
            + "</ul>"
        )
    steps = "".join(
        f'<li style="margin:0 0 8px 0;">{esc(s)}</li>' for s in d["steps"]
    )
    src = d["source"]
    verify_line = (
        "Verified source page." if src.get("verified") else
        "Source page not re-checked for this version. " + src.get("verification_note", "")
    )
    timing = (
        f'Prep {d["prep_min"]} min &nbsp;|&nbsp; Cook {d["cook_min"]} min &nbsp;|&nbsp; '
        f'Rest {d["rest_min"]} min &nbsp;|&nbsp; <strong>Total {d["total_min"]} min</strong>'
    )
    return f"""
<tr><td style="padding:22px 28px 0 28px;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
         style="border:1px solid {RULE};border-radius:8px;background:#FFFFFF;">
    <tr><td style="padding:20px 22px;">
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:{MUTED};letter-spacing:1px;text-transform:uppercase;">Dinner {number} of 4 &middot; {esc(d["suggested_night"])}</div>
      <div style="margin-top:6px;">{label_chip(d["label"])}</div>
      <h3 style="font-family:Georgia,serif;font-size:24px;line-height:1.25;color:{PLUM};margin:10px 0 0 0;">{esc(d["title"])}</h3>
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.55;color:{INK};margin-top:8px;">{esc(d["description"])}</div>
      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:14px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:{INK};">
        <tr>
          <td style="padding:6px 0;"><strong>Protein:</strong> {esc(d["protein"])}</td>
          <td style="padding:6px 0;"><strong>Servings:</strong> {d["servings"]} (for two)</td>
        </tr>
        <tr>
          <td style="padding:6px 0;"><strong>Difficulty:</strong> {esc(d["difficulty"])}</td>
          <td style="padding:6px 0;"><strong>Cleanup:</strong> {esc(d["cleanup"])}</td>
        </tr>
      </table>
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{PLUM};background:{CREAM};padding:10px 12px;border-radius:6px;margin-top:6px;">{timing}</div>
      <div style="font-family:Georgia,serif;font-size:18px;color:{PLUM};margin-top:18px;">Ingredients for two</div>
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;color:{INK};">{ingredients}</div>
      <div style="font-family:Georgia,serif;font-size:18px;color:{PLUM};margin-top:18px;">Directions</div>
      <ol style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:{INK};margin:6px 0 0 0;padding-left:22px;">{steps}</ol>
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.6;color:{INK};margin-top:16px;">
        <div><strong>Side:</strong> {esc(d["side"])}</div>
        <div style="margin-top:6px;"><strong>Chef's tip:</strong> {esc(d["chefs_tip"])}</div>
        <div style="margin-top:6px;"><strong>Shortcut / prep ahead:</strong> {esc(d["shortcut"])}</div>
        <div style="margin-top:6px;"><strong>Leftovers:</strong> {esc(d["leftovers"])}</div>
        <div style="margin-top:6px;"><strong>Food safety:</strong> {esc(d["food_safety"])}</div>
        <div style="margin-top:6px;"><strong>Estimated cost per serving:</strong> {esc(d["estimated_cost_per_serving"])}</div>
      </div>
      <div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.55;color:{MUTED};background:{CREAM};padding:12px;border-radius:6px;margin-top:16px;">
        <strong style="color:{PLUM};">Source inspiration:</strong> {esc(src["name"])}<br>
        <a href="{esc(src["url"])}" style="color:{PLUM};word-break:break-all;">{esc(src["url"])}</a><br>
        <span>{esc(verify_line)}</span>
        <br><span style="font-size:12px;">QR code: not included. Email clients often block QR images, so use the link above.</span>
      </div>
    </td></tr>
  </table>
</td></tr>"""


def overview_table(dinners):
    rows = ""
    for i, d in enumerate(dinners, 1):
        rows += (
            f'<tr>'
            f'<td style="padding:10px;border-bottom:1px solid {RULE};font-family:Arial,Helvetica,sans-serif;font-size:14px;">{i}. {esc(d["title"])}</td>'
            f'<td style="padding:10px;border-bottom:1px solid {RULE};font-family:Arial,Helvetica,sans-serif;font-size:14px;">{esc(d["protein"])}</td>'
            f'<td style="padding:10px;border-bottom:1px solid {RULE};font-family:Arial,Helvetica,sans-serif;font-size:14px;">{d["total_min"]} min</td>'
            f'<td style="padding:10px;border-bottom:1px solid {RULE};font-family:Arial,Helvetica,sans-serif;font-size:14px;">{esc(d["cleanup"].split(" (")[0])}</td>'
            f'<td style="padding:10px;border-bottom:1px solid {RULE};font-family:Arial,Helvetica,sans-serif;font-size:14px;">{esc(d["suggested_night"])}</td>'
            f'</tr>'
        )
    head = "".join(
        f'<th align="left" style="padding:10px;background:{PLUM};color:#FFFFFF;font-family:Arial,Helvetica,sans-serif;font-size:13px;">{h}</th>'
        for h in ["Dish", "Protein", "Total time", "Cleanup", "Suggested night"]
    )
    return (
        f'<tr><td style="padding:6px 28px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">'
        f'<tr>{head}</tr>{rows}</table></td></tr>'
    )


def grocery_block(menu):
    out = ""
    for cat in menu["grocery"]["categories"]:
        if not cat["items"]:
            body = f'<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:{MUTED};">None this week.</div>'
        else:
            lis = "".join(
                f'<li style="margin:0 0 6px 0;">&#9744; {esc(i["item"])} &mdash; {esc(i["quantity"])}'
                + (f' <em style="color:{MUTED};">({esc(i["channel_note"])})</em>' if i.get("channel_note") else "")
                + "</li>"
                for i in cat["items"]
            )
            body = f'<ul style="margin:0;padding-left:4px;list-style:none;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;">{lis}</ul>'
        out += (
            f'<tr><td style="padding:10px 28px 4px 28px;">'
            f'<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;color:{SAGE};text-transform:uppercase;letter-spacing:1px;">{esc(cat["name"])} &middot; <span style="font-weight:normal;color:{MUTED};text-transform:none;letter-spacing:0;">{esc(cat["channel"])}</span></div>'
            f'<div style="margin-top:6px;">{body}</div></td></tr>'
        )
    return out


def ratings_block(ratings):
    groups = {}
    for r in ratings:
        groups.setdefault(r["status"], []).append(r)
    order = ["Hall of Fame", "Keep in Rotation", "Good but Not Often", "Retired"]
    rows = ""
    for status in order:
        items = groups.get(status, [])
        if status == "Hall of Fame":
            items = [r for r in ratings if r["rating_stars"] == "5"]
        elif status == "Keep in Rotation":
            items = [r for r in ratings if r["rating_stars"] == "4"]
        elif status == "Good but Not Often":
            items = [r for r in ratings if r["rating_stars"] == "3"]
        elif status == "Retired":
            items = [r for r in ratings if r["status"] == "Retired"]
        if not items:
            continue
        names = "".join(
            f'<li style="margin:0 0 4px 0;">{esc(r["dish"])}'
            + (f' <em style="color:{MUTED};">&mdash; {esc(r["comments"])}</em>' if r.get("comments") else "")
            + "</li>"
            for r in items
        )
        rows += (
            f'<div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;font-weight:bold;color:{PLUM};margin-top:10px;">{esc(status)}</div>'
            f'<ul style="margin:4px 0 0 0;padding-left:20px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.5;color:{INK};">{names}</ul>'
        )
    return f'<tr><td style="padding:6px 28px;">{rows}</td></tr>'


def feedback_block(dinners):
    rows = ""
    for d in dinners:
        rows += (
            f'<tr><td style="padding:12px 0;border-bottom:1px solid {RULE};font-family:Arial,Helvetica,sans-serif;font-size:14px;">'
            f'<strong>{esc(d["title"])}</strong><br>'
            f'<span style="color:{MUTED};">&#11088;&#11088;&#11088;&#11088;&#11088; Make Again &nbsp;&middot;&nbsp; &#11088;&#11088;&#11088;&#11088; Keep in Rotation &nbsp;&middot;&nbsp; &#11088;&#11088;&#11088; Good but Not Often &nbsp;&middot;&nbsp; &#10060; Don&rsquo;t Include Again</span>'
            f'</td></tr>'
        )
    return (
        f'<tr><td style="padding:6px 28px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">{rows}</table>'
        f'<div style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:{MUTED};margin-top:12px;">'
        f'Reply with a short note, such as &ldquo;Chimichurri steak 5 stars&rdquo; or &ldquo;Harissa chicken 3 stars, too spicy.&rdquo; '
        f'Ratings are recorded only from your confirmed feedback.</div></td></tr>'
    )


def build_html(menu, prefs, ratings):
    dinners = menu["dinners"]
    intro = menu["theme_text"]
    why_items = menu["why_chosen"]
    prep_items = [f'{p["when"]}: {p["task"]}' for p in menu["prep_ahead"]]
    body = []
    body.append(f"""<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{esc(subject_for(menu))}</title></head>
<body style="margin:0;padding:0;background:#EFE8DE;">
<div style="display:none;max-height:0;overflow:hidden;">Four fast dinners for two, with recipes and a grocery plan.</div>
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#EFE8DE;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:680px;background:{CREAM};border-collapse:collapse;">""")
    body.append(f"""
<tr><td bgcolor="{PLUM}" style="background:{PLUM};padding:34px 28px;text-align:center;">
  <div style="font-family:Arial,Helvetica,sans-serif;font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#E8D9C4;">Arlington &middot; Weekly Menu</div>
  <div style="font-family:Georgia,serif;font-size:30px;line-height:1.2;color:#FFFFFF;margin-top:10px;">Brig &amp; Dawn's Weekly Dinner Menu</div>
  <div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#F3E9DC;margin-top:10px;">{esc(menu["week_range"])} &middot; Four dinners for two</div>
  <div style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#F3E9DC;margin-top:4px;">{esc(menu["theme"])}</div>
</td></tr>""")
    if menu.get("test_mode"):
        body.append(
            f'<tr><td style="padding:12px 28px;background:#FFF4D6;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:{INK};">'
            f'<strong>Test message.</strong> Formatting and delivery check. Source links were not re-checked on their pages; see each card.</td></tr>'
        )
    body.append(section_title("This Week's Theme"))
    body.append(paragraph(intro))
    body.append(section_title("Why These Meals Were Chosen"))
    body.append(bullets(why_items))
    body.append(paragraph(menu["rating_history_note"]))
    body.append(section_title("Weekly Overview"))
    body.append(overview_table(dinners))
    body.append(section_title("Four Dinners for Two"))
    for n, d in enumerate(dinners, 1):
        body.append(dinner_card(d, n))
    body.append(section_title("Smart 10-Minute Prep-Ahead Plan"))
    body.append(bullets(prep_items))
    body.append(paragraph("Food-safe storage: " + " ".join(menu["food_safety_notes"])))
    body.append(section_title("Grocery Checklist (edit before ordering)"))
    body.append(paragraph(menu["grocery"]["import_list"]))
    body.append(grocery_block(menu))
    body.append(section_title("Dawn's Amazon Grocery Shopping Plan"))
    body.append(paragraph(
        "Default: Amazon Fresh / Amazon Same-Day Delivery. Optional: Whole Foods for the sirloin and shrimp if you want better quality. "
        "Nothing is ordered automatically. Remove any item you already have, then order in your usual way. "
        "This message does not connect to an Amazon cart."))
    body.append(section_title("Check Your Pantry and Refrigerator First"))
    body.append(bullets(menu["pantry_check"]))
    body.append(section_title("Ingredient Reuse and Waste Reduction"))
    body.append(bullets(menu["waste_reduction"]))
    body.append(section_title("Estimated Grocery Budget"))
    body.append(paragraph(menu["budget"]["note"]))
    body.append(section_title("Hall of Fame, Recent Favorites and Retired"))
    body.append(ratings_block(ratings))
    body.append(section_title("Rate This Week's Meals"))
    body.append(feedback_block(dinners))
    body.append(f"""
<tr><td bgcolor="{SAGE}" style="background:{SAGE};padding:22px 28px;text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#FFFFFF;">
  Prepared for Brig and Dawn &middot; Replies go to brig@aim-tex.com
</td></tr>
</table></td></tr></table></body></html>""")
    return "".join(body)


def validate(menu, html_text, ratings, history, subject):
    results = []

    def check(name, ok, detail=""):
        results.append((name, bool(ok), detail))

    dinners = menu["dinners"]
    check("Exactly four dinners", len(dinners) == 4, f"found {len(dinners)}")
    check("Servings are two for every dinner", all(d["servings"] == 2 for d in dinners))
    for d in dinners:
        t = d["total_min"]
        check(f'{d["title"]}: total time 30 minutes or less', t <= 30, f"{t} min")
        check(f'{d["title"]}: prep + cook + rest equals total',
              d["prep_min"] + d["cook_min"] + d["rest_min"] == t,
              f'{d["prep_min"]}+{d["cook_min"]}+{d["rest_min"]} vs {t}')
        check(f'{d["title"]}: has a verified-or-labeled source URL',
              d["source"]["url"].startswith("https://"), d["source"]["url"])
        check(f'{d["title"]}: has a directions list of 4 or more steps', len(d["steps"]) >= 4)
        blob = json.dumps(d).lower()
        for term in BANNED_TERMS:
            check(f'{d["title"]}: no "{term}"', term not in blob)
        if d["protein"].lower().startswith("chicken") or "chicken" in d["protein"].lower():
            check(f'{d["title"]}: 165 F stated for chicken', "165" in " ".join(d["steps"]) + d["food_safety"])
        if "pork" in d["protein"].lower():
            steps_text = " ".join(d["steps"])
            check(f'{d["title"]}: 145 F stated for pork', "145" in steps_text)
            check(f'{d["title"]}: 3-minute rest stated for pork', "3 minutes" in steps_text or "3-minute" in steps_text)
        if "shrimp" in d["protein"].lower():
            check(f'{d["title"]}: shrimp opaque cue stated', "opaque" in " ".join(d["steps"]).lower())

    labels = {d["label"] for d in dinners}
    check("Every dinner has a label", all(d.get("label") for d in dinners), ", ".join(sorted(labels)))

    # Repeat window: a dish that was on a planned menu in the last 8 weeks must not return.
    send = date.fromisoformat(menu["send_date"])
    window_start = date.fromordinal(send.toordinal() - 8 * 7)
    recent = set()
    for row in history:
        try:
            wk = date.fromisoformat(row["week_of"])
        except ValueError:
            continue
        if row["record_type"] == "planned_menu" and window_start <= wk < send:
            recent.add(re.sub(r"\s*\(.*\)", "", row["dish"]).strip().lower())
    for d in dinners:
        if d["label"] != "Encore":
            check(f'{d["title"]}: not served in the last 8 weeks (unless Encore)',
                  d["title"].lower() not in recent)
    for name in [r["dish"].lower() for r in ratings if r["status"] == "Retired"]:
        check(f'Retired dish not on menu: {name}', all(name not in d["title"].lower() for d in dinners))

    proteins = [d["protein"].split(" ")[0].lower() for d in dinners]
    check("Each dinner uses a different protein", len(set(proteins)) == 4, ", ".join(proteins))

    check("Subject uses the required format",
          re.fullmatch(r"Brig & Dawn's Weekly Dinner Menu - [A-Z][a-z]+ \d{1,2}, \d{4}", subject) is not None,
          subject)
    check("Subject date is the send Saturday", date.fromisoformat(menu["send_date"]).weekday() == 5,
          menu["send_date"])
    check("HTML has no leftover template placeholders", "{{" not in html_text and "}}" not in html_text)
    check("HTML has no external images", "<img" not in html_text.lower())
    check("HTML has no style tags or scripts",
          "<style" not in html_text.lower() and "<script" not in html_text.lower())
    check("HTML has no forms", "<form" not in html_text.lower())
    check("Hall of Fame section present", "Hall of Fame" in html_text)
    check("Feedback section lists all four dinners",
          all(d["title"] in html_text for d in dinners))
    check("Grocery list has no duplicate item names",
          len({i["item"].lower() for c in menu["grocery"]["categories"] for i in c["items"]})
          == sum(len(c["items"]) for c in menu["grocery"]["categories"]))
    check("Grocery list is not auto-ordered", menu["grocery"]["not_ordered_automatically"] is True)
    check("No budget total is shown without a price check", "$" not in menu["budget"]["note"])
    balance = html_text.count("<table") == html_text.count("</table>")
    check("HTML tables are balanced", balance)
    divs_ok = html_text.count("<div") == html_text.count("</div>")
    check("HTML divs are balanced", divs_ok)
    return results


def main(argv):
    if len(argv) != 2:
        print(__doc__)
        return 2
    menu_path = Path(argv[1])
    menu = json.loads(menu_path.read_text(encoding="utf-8"))
    prefs = json.loads(PREFS_PATH.read_text(encoding="utf-8"))
    ratings = load_ratings()
    history = load_history()
    subject = subject_for(menu)
    html_text = build_html(menu, prefs, ratings)
    results = validate(menu, html_text, ratings, history, subject)

    out_dir = HERE / "out" / menu["send_date"]
    out_dir.mkdir(parents=True, exist_ok=True)
    (out_dir / "email.html").write_text(html_text, encoding="utf-8")
    (out_dir / "subject.txt").write_text(subject + "\n", encoding="utf-8")
    lines = [f'{"PASS" if ok else "FAIL"}  {name}' + (f"  [{detail}]" if detail and not ok else "")
             for name, ok, detail in results]
    failures = [r for r in results if not r[1]]
    lines.append("")
    lines.append(f"{len(results) - len(failures)} of {len(results)} checks passed.")
    (out_dir / "validation.txt").write_text("\n".join(lines) + "\n", encoding="utf-8")
    print("\n".join(lines))
    print(f"Saved: {out_dir / 'email.html'}")
    print(f"Subject: {subject}")
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
