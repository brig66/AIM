#!/usr/bin/env python3
"""
build_report.py — render overview.json as an AIM-branded one-page dashboard.

    python3 build_report.py overview.json                 # -> <domain>-Domain-Overview-AIM.pdf
    python3 build_report.py overview.json --html out.html # HTML only, no WeasyPrint needed

Charts are inline SVG generated here, because WeasyPrint does not run
JavaScript — no chart library is involved and the output is byte-stable for
the same input.

Every panel marked {"available": false} renders as a visible empty state
carrying its reason. A panel is never dropped silently: on a client-facing
page, a missing section reads as "zero", which is a different claim from
"not retrieved".
"""
import argparse
import base64
import html
import json
import os
import sys

PALETTE = {
    "indigo": "#352597", "magenta": "#c0228a", "orange": "#e7730d",
    "cream": "#FFFAFB", "ink": "#241d3d", "muted": "#6b647f",
    "line": "#e8e2ee", "soft": "#f7f3fa",
}
BUCKET_COLORS = {
    "top_3": "#352597", "pos_4_10": "#5a45c4", "pos_11_20": "#8a5fc0",
    "pos_21_50": "#c0228a", "pos_51_100": "#e7730d",
}
BUCKET_LABELS = {
    "top_3": "Top 3", "pos_4_10": "4–10", "pos_11_20": "11–20",
    "pos_21_50": "21–50", "pos_51_100": "51–100",
}
CONTACT = ("Advanced Integrated Marketing, Inc. · aim-tex.com · 817-592-5586 · "
           "1905 Ascension Blvd, Suite 154, Arlington, TX 76006")


# ------------------------------------------------------------- brand kit ---

def find_assets():
    """AIM brand assets, if this machine has them. The report degrades to
    system fonts rather than failing — it is an analyst tool first."""
    cands = [os.environ.get("AIM_BRAND_ASSETS", "")]
    here = os.path.dirname(os.path.abspath(__file__))
    cands += [os.path.join(here, "..", "assets")]
    home = os.path.expanduser("~/.claude/skills")
    for root in (os.path.join(home, "synced"), home):
        if os.path.isdir(root):
            for name in sorted(os.listdir(root)):
                cands.append(os.path.join(root, name, "assets"))
    for c in cands:
        if c and os.path.exists(os.path.join(c, "brand.json")):
            return os.path.abspath(c)
    return None


def font_face_css(assets):
    if not assets:
        return "", "'Helvetica Neue', Arial, sans-serif", "Georgia, 'Times New Roman', serif"
    fonts = {"Inter-400.woff2": ("Inter", 400), "Inter-600.woff2": ("Inter", 600),
             "Inter-700.woff2": ("Inter", 700), "Fraunces-700.woff2": ("Fraunces", 700)}
    css = []
    for fn, (fam, weight) in fonts.items():
        p = os.path.join(assets, "fonts", fn)
        if os.path.exists(p):
            b = base64.b64encode(open(p, "rb").read()).decode()
            css.append(f"@font-face{{font-family:'{fam}';font-weight:{weight};"
                       f"src:url(data:font/woff2;base64,{b}) format('woff2');}}")
    body = "Inter, 'Helvetica Neue', Arial, sans-serif" if css else "'Helvetica Neue', Arial, sans-serif"
    disp = "Fraunces, Georgia, serif" if css else "Georgia, serif"
    return "\n".join(css), body, disp


def logo_uri(assets):
    if not assets:
        return None
    for name in ("aim-logo.png", "aim_logo.png", "aim-logo-trans.png"):
        p = os.path.join(assets, name)
        if os.path.exists(p):
            return "data:image/png;base64," + base64.b64encode(open(p, "rb").read()).decode()
    return None


# -------------------------------------------------------------- helpers ----

def e(s):
    return html.escape("" if s is None else str(s))


def fmt(n, nd=0):
    if n is None:
        return "—"
    try:
        n = float(n)
    except (TypeError, ValueError):
        return e(n)
    if abs(n) >= 1_000_000:
        return f"{n/1_000_000:.1f}M"
    if abs(n) >= 1_000:
        return f"{n/1_000:.1f}K"
    return f"{n:,.{nd}f}"


def delta(v):
    if v is None:
        return ""
    cls = "up" if v > 0 else ("down" if v < 0 else "flat")
    sign = "+" if v > 0 else ""
    return f'<span class="delta {cls}">{sign}{v}%</span>'


def empty(reason):
    return f'<div class="empty"><strong>Not available.</strong> {e(reason)}</div>'


def ok(panel):
    return isinstance(panel, dict) and panel.get("available")


# ----------------------------------------------------------------- SVG -----

def _axis(vmax):
    """Round the axis up to something a reader can do arithmetic against."""
    if vmax <= 0:
        return 1, [0, 1]
    import math
    step = 10 ** math.floor(math.log10(vmax))
    for m in (1, 2, 2.5, 5, 10):
        if vmax / (step * m) <= 4:
            step *= m
            break
    top = step * math.ceil(vmax / step)
    ticks, v = [], 0
    while v <= top + 1e-9:
        ticks.append(v)
        v += step
    return top, ticks


def line_chart(labels, series, width=760, height=210, pad_l=46, pad_b=22, pad_t=8):
    """series: [(name, colour, [values])] — overlaid lines with a soft fill."""
    if not labels:
        return ""
    vmax = max([max(vals) for _, _, vals in series if vals] + [0])
    top, ticks = _axis(vmax)
    iw, ih = width - pad_l - 8, height - pad_b - pad_t
    n = max(len(labels) - 1, 1)
    x = lambda i: pad_l + iw * i / n
    y = lambda v: pad_t + ih - (ih * (v / top if top else 0))

    out = [f'<svg viewBox="0 0 {width} {height}" width="100%" role="img">']
    for t in ticks:
        out.append(f'<line x1="{pad_l}" y1="{y(t):.1f}" x2="{width-8}" y2="{y(t):.1f}" '
                   f'stroke="{PALETTE["line"]}" stroke-width="1"/>')
        out.append(f'<text x="{pad_l-6}" y="{y(t)+3:.1f}" text-anchor="end" '
                   f'font-size="8" fill="{PALETTE["muted"]}">{fmt(t)}</text>')
    for name, colour, vals in series:
        if not vals:
            continue
        pts = " ".join(f"{x(i):.1f},{y(v):.1f}" for i, v in enumerate(vals))
        out.append(f'<polygon points="{pad_l},{y(0):.1f} {pts} {x(len(vals)-1):.1f},{y(0):.1f}" '
                   f'fill="{colour}" fill-opacity="0.10"/>')
        out.append(f'<polyline points="{pts}" fill="none" stroke="{colour}" '
                   f'stroke-width="1.8" stroke-linejoin="round"/>')
    every = max(1, len(labels) // 6)
    for i, lab in enumerate(labels):
        if i % every == 0 or i == len(labels) - 1:
            out.append(f'<text x="{x(i):.1f}" y="{height-6}" text-anchor="middle" '
                       f'font-size="8" fill="{PALETTE["muted"]}">{e(lab)}</text>')
    out.append("</svg>")
    return "".join(out)


def stacked_chart(labels, stacks, width=760, height=210, pad_l=46, pad_b=22, pad_t=8):
    """stacks: [(name, colour, [values])] drawn bottom-up as filled bands."""
    if not labels:
        return ""
    totals = [sum(s[2][i] if i < len(s[2]) else 0 for s in stacks) for i in range(len(labels))]
    top, ticks = _axis(max(totals + [0]))
    iw, ih = width - pad_l - 8, height - pad_b - pad_t
    n = max(len(labels) - 1, 1)
    x = lambda i: pad_l + iw * i / n
    y = lambda v: pad_t + ih - (ih * (v / top if top else 0))

    out = [f'<svg viewBox="0 0 {width} {height}" width="100%" role="img">']
    for t in ticks:
        out.append(f'<line x1="{pad_l}" y1="{y(t):.1f}" x2="{width-8}" y2="{y(t):.1f}" '
                   f'stroke="{PALETTE["line"]}" stroke-width="1"/>')
        out.append(f'<text x="{pad_l-6}" y="{y(t)+3:.1f}" text-anchor="end" '
                   f'font-size="8" fill="{PALETTE["muted"]}">{fmt(t)}</text>')
    base = [0.0] * len(labels)
    for name, colour, vals in stacks:
        upper = [base[i] + (vals[i] if i < len(vals) else 0) for i in range(len(labels))]
        pts_up = " ".join(f"{x(i):.1f},{y(v):.1f}" for i, v in enumerate(upper))
        pts_dn = " ".join(f"{x(i):.1f},{y(v):.1f}" for i, v in reversed(list(enumerate(base))))
        out.append(f'<polygon points="{pts_up} {pts_dn}" fill="{colour}" fill-opacity="0.85"/>')
        base = upper
    every = max(1, len(labels) // 6)
    for i, lab in enumerate(labels):
        if i % every == 0 or i == len(labels) - 1:
            out.append(f'<text x="{x(i):.1f}" y="{height-6}" text-anchor="middle" '
                       f'font-size="8" fill="{PALETTE["muted"]}">{e(lab)}</text>')
    out.append("</svg>")
    return "".join(out)


def donut(segments, size=150, thickness=22):
    """segments: [(label, value, colour)]"""
    total = sum(v for _, v, _ in segments) or 1
    r = (size - thickness) / 2
    cx = cy = size / 2
    circ = 2 * 3.141592653589793 * r
    out = [f'<svg viewBox="0 0 {size} {size}" width="{size}" height="{size}" role="img">']
    offset = 0.0
    for _, v, colour in segments:
        frac = v / total
        out.append(
            f'<circle cx="{cx}" cy="{cy}" r="{r:.2f}" fill="none" stroke="{colour}" '
            f'stroke-width="{thickness}" stroke-dasharray="{circ*frac:.2f} {circ:.2f}" '
            f'stroke-dashoffset="{-circ*offset:.2f}" transform="rotate(-90 {cx} {cy})"/>')
        offset += frac
    out.append("</svg>")
    return "".join(out)


def legend(items):
    return '<div class="legend">' + "".join(
        f'<span><i style="background:{c}"></i>{e(l)}</span>' for l, c in items) + "</div>"


# ---------------------------------------------------------------- panels ---

def kpi(label, value, sub=""):
    return (f'<div class="kpi"><div class="kl">{e(label)}</div>'
            f'<div class="kv">{value}</div><div class="ks">{sub}</div></div>')


def section(title, body, note=""):
    n = f'<p class="note">{e(note)}</p>' if note else ""
    return f'<section><h2>{e(title)}</h2>{body}{n}</section>'


def render_headline(P):
    h, b, t, comp = P.get("headline", {}), P.get("backlinks", {}), P.get("trends", {}), P.get("competitors", {})
    if not ok(h) and not ok(b):
        return empty((h.get("reason") if isinstance(h, dict) else "") or "no headline data")
    cells = []
    if ok(b):
        cells.append(kpi("Authority (0–100)", fmt(b.get("authority_0_100"), 1),
                         f"DFS rank {fmt(b.get('domain_rank'))}"))
    if ok(h):
        cells.append(kpi("Organic traffic / mo", fmt(h["organic"]["traffic_estimate_monthly"]),
                         delta(t.get("organic_traffic_change_pct") if ok(t) else None)))
        cells.append(kpi("Paid traffic / mo", fmt(h["paid"]["traffic_estimate_monthly"])))
    if ok(b):
        cells.append(kpi("Referring domains", fmt(b.get("referring_domains"))))
    if ok(comp):
        cells.append(kpi("Traffic share", f'{comp.get("traffic_share_pct")}%',
                         f'vs top {len(comp.get("competitors", []))}'))
    if ok(h):
        cells.append(kpi("Organic keywords", fmt(h["organic"]["keywords"])))
        cells.append(kpi("Paid keywords", fmt(h["paid"]["keywords"])))
    if ok(b):
        cells.append(kpi("Backlinks", fmt(b.get("backlinks")),
                         f'{b.get("dofollow_pct")}% dofollow' if b.get("dofollow_pct") is not None else ""))
    return f'<div class="kpis">{"".join(cells)}</div>'


def render_ai(P):
    a = P.get("ai")
    if a is None:
        return empty("AI block not run. Re-run with --ai to include it.")
    if not ok(a):
        return empty(a.get("reason", "AI block unavailable"))
    cells = [kpi("AI Visibility (AIM index)", fmt(a.get("visibility_0_100"))),
             kpi("Mentions", fmt(a.get("mentions"))),
             kpi("Cited pages", fmt(a.get("cited_pages"))),
             kpi("Prompts", fmt(a.get("prompt_count")))]
    rows = []
    for name, v in (a.get("by_engine") or {}).items():
        if ok(v):
            rows.append(f"<tr><td>{e(name)}</td><td>{fmt(v.get('visibility_0_100'))}</td>"
                        f"<td>{fmt(v.get('mentions'))}</td><td>{fmt(v.get('cited_pages'))}</td>"
                        f"<td>{fmt(v.get('prompts_answered'))}/{fmt(v.get('prompts_run'))}</td></tr>")
        else:
            rows.append(f'<tr><td>{e(name)}</td><td colspan="4" class="mut">{e(v.get("reason"))}</td></tr>')
    src = "".join(f"<tr><td>{e(k)}</td><td>{v}</td></tr>"
                  for k, v in (a.get("top_cited_sources") or {}).items())
    return (f'<div class="kpis">{"".join(cells)}</div>'
            f'<div class="cols"><div><table><thead><tr><th>Engine</th><th>Visibility</th>'
            f'<th>Mentions</th><th>Cited pages</th><th>Answered</th></tr></thead>'
            f'<tbody>{"".join(rows)}</tbody></table></div>'
            f'<div><table><thead><tr><th>Top cited sources</th><th>#</th></tr></thead>'
            f'<tbody>{src or "<tr><td colspan=2 class=mut>none</td></tr>"}</tbody></table></div></div>')


def render_trends(P):
    t = P.get("trends", {})
    if not ok(t):
        return empty(t.get("reason", "no trend data") if isinstance(t, dict) else "no trend data")
    s = t["series"]
    labels = [r["month"] for r in s]
    traffic = line_chart(labels, [
        ("Organic", PALETTE["indigo"], [r["organic_traffic"] for r in s]),
        ("Paid", PALETTE["magenta"], [r["paid_traffic"] for r in s]),
    ])
    stacks = [(BUCKET_LABELS[k], BUCKET_COLORS[k], [r["positions"].get(k, 0) for r in s])
              for k in BUCKET_COLORS]
    kws = stacked_chart(labels, stacks)
    return (f'<h3>Estimated traffic</h3>{traffic}'
            + legend([("Organic", PALETTE["indigo"]), ("Paid", PALETTE["magenta"])])
            + f'<h3>Ranked keywords by position</h3>{kws}'
            + legend([(BUCKET_LABELS[k], BUCKET_COLORS[k]) for k in BUCKET_COLORS]))


def render_serp(P):
    s = P.get("serp_features", {})
    if not ok(s):
        return empty(s.get("reason", "no ranked keyword sample") if isinstance(s, dict) else "n/a")
    d = s["distribution"]
    segs = [("Organic", d["organic"]["count"], PALETTE["indigo"]),
            ("AI Overviews", d["ai_overviews"]["count"], PALETTE["magenta"]),
            ("Other SERP features", d["other_serp_features"]["count"], PALETTE["orange"])]
    rows = "".join(f'<tr><td>{e(l)}</td><td>{fmt(v)}</td>'
                   f'<td>{d[k]["pct"] if d[k]["pct"] is not None else "—"}%</td></tr>'
                   for (l, v, _), k in zip(segs, ["organic", "ai_overviews", "other_serp_features"]))
    detail = "".join(f"<tr><td>{e(k)}</td><td>{v}</td></tr>"
                     for k, v in list((s.get("by_element_type") or {}).items())[:8])
    cap = (" Sample capped at the highest-traffic keywords retrieved."
           if s.get("sample_is_capped") else "")
    return (f'<div class="cols"><div class="dn">{donut(segs)}'
            f'{legend([(l, c) for l, _, c in segs])}</div>'
            f'<div><table><thead><tr><th>Position type</th><th>Keywords</th><th>Share</th></tr>'
            f'</thead><tbody>{rows}</tbody></table>'
            f'<table><thead><tr><th>SERP element</th><th>#</th></tr></thead>'
            f'<tbody>{detail}</tbody></table></div></div>'
            f'<p class="note">Based on {fmt(s.get("sampled_keywords"))} ranked keywords.{cap}</p>')


def render_branded(P):
    b = P.get("branded", {})
    if not ok(b):
        return empty(b.get("reason", "n/a") if isinstance(b, dict) else "n/a")
    return (f'<div class="kpis">'
            + kpi("Branded traffic", f'{b["branded"]["traffic_pct"]}%',
                  f'{fmt(b["branded"]["keywords"])} keywords')
            + kpi("Non-branded traffic", f'{b["non_branded"]["traffic_pct"]}%',
                  f'{fmt(b["non_branded"]["keywords"])} keywords')
            + kpi("Brand tokens", e(", ".join(b.get("brand_tokens", []))[:40] or "—"))
            + '</div>')


def render_table(P, key, cols, empty_msg):
    p = P.get(key, {})
    if not ok(p):
        return empty(p.get("reason", empty_msg) if isinstance(p, dict) else empty_msg)
    rows = p.get("rows") or p.get("competitors") or []
    head = "".join(f"<th>{e(c[0])}</th>" for c in cols)
    body = ""
    for r in rows:
        body += "<tr>" + "".join(
            f'<td>{fmt(r.get(c[1])) if c[2] == "n" else e(r.get(c[1]))}</td>' for c in cols) + "</tr>"
    return f'<table><thead><tr>{head}</tr></thead><tbody>{body}</tbody></table>'


def render_backlinks(P):
    b, tr = P.get("backlinks", {}), P.get("backlinks_trend", {})
    if not ok(b):
        return empty(b.get("reason", "n/a") if isinstance(b, dict) else "n/a")
    cells = [kpi("Backlinks", fmt(b.get("backlinks"))),
             kpi("Referring domains", fmt(b.get("referring_domains"))),
             kpi("Referring IPs", fmt(b.get("referring_ips"))),
             kpi("Spam score", fmt(b.get("spam_score"))),
             kpi("Broken links", fmt(b.get("broken_backlinks"))),
             kpi("Dofollow", f'{b.get("dofollow_pct")}%' if b.get("dofollow_pct") is not None else "—")]
    chart = ""
    if ok(tr) and tr.get("series"):
        s = tr["series"]
        chart = line_chart([r["month"] for r in s], [
            ("Referring domains", PALETTE["indigo"], [r.get("referring_domains") or 0 for r in s]),
        ], height=150) + legend([("Referring domains", PALETTE["indigo"])])
    elif isinstance(tr, dict) and tr.get("reason"):
        chart = empty(tr["reason"])
    return f'<div class="kpis">{"".join(cells)}</div>{chart}'


def render_countries(P):
    c = P.get("countries")
    if c is None:
        return ""
    if not ok(c):
        return section("By country", empty(c.get("reason", "n/a")))
    rows = ""
    for code, v in (c.get("by_country") or {}).items():
        if ok(v):
            rows += (f'<tr><td>{e(v.get("location", code))}</td>'
                     f'<td>{fmt(v["organic"]["keywords"])}</td>'
                     f'<td>{fmt(v["organic"]["traffic_estimate_monthly"])}</td>'
                     f'<td>{fmt(v["organic"]["positions"]["top_3"])}</td></tr>')
        else:
            rows += f'<tr><td>{e(code)}</td><td colspan="3" class="mut">{e(v.get("reason"))}</td></tr>'
    return section("By country", '<table><thead><tr><th>Country</th><th>Organic keywords</th>'
                                 f'<th>Traffic / mo</th><th>Top 3</th></tr></thead>'
                                 f'<tbody>{rows}</tbody></table>')


def render_channels(P):
    c = P.get("channels", {})
    if not ok(c):
        return empty(c.get("reason", "n/a") if isinstance(c, dict) else "n/a")
    rows = "".join(f'<tr><td>{e(r.get("channel"))}</td><td>{fmt(r.get("sessions"))}</td>'
                   f'<td>{r.get("pct")}%</td></tr>' for r in c.get("rows", []))
    return (f'<table><thead><tr><th>Channel</th><th>Sessions</th><th>Share</th></tr></thead>'
            f'<tbody>{rows}</tbody></table><p class="note">{e(c.get("definition",""))}</p>')


# ------------------------------------------------------------------ page ---

def build_html(data):
    meta, P = data.get("meta", {}), data.get("panels", {})
    assets = find_assets()
    faces, body_font, disp_font = font_face_css(assets)
    logo = logo_uri(assets)
    cost = (meta.get("cost") or {}).get("total_cost_usd")

    defs = []
    for name in ("headline", "backlinks", "competitors", "branded", "serp_features", "ai", "trends"):
        p = P.get(name)
        if ok(p) and p.get("definition"):
            defs.append(f"<li><strong>{e(name)}</strong> — {e(p['definition'])}</li>")

    css = f"""
{faces}
@page {{ size: A4; margin: 14mm 12mm 16mm 12mm;
  @bottom-center {{ content: "{CONTACT}"; font-size: 7pt; color: {PALETTE['muted']}; }}
  @bottom-right {{ content: counter(page); font-size: 7pt; color: {PALETTE['muted']}; }} }}
* {{ box-sizing: border-box; }}
body {{ font-family: {body_font}; color: {PALETTE['ink']}; background: {PALETTE['cream']};
       font-size: 9pt; line-height: 1.45; margin: 0; }}
header {{ display: flex; justify-content: space-between; align-items: flex-end;
          border-bottom: 2px solid {PALETTE['indigo']}; padding-bottom: 8px; margin-bottom: 14px; }}
h1 {{ font-family: {disp_font}; font-size: 20pt; margin: 0; color: {PALETTE['indigo']}; }}
h2 {{ font-family: {disp_font}; font-size: 12pt; margin: 18px 0 8px; color: {PALETTE['indigo']}; }}
h3 {{ font-size: 9pt; text-transform: uppercase; letter-spacing: .06em;
      color: {PALETTE['muted']}; margin: 12px 0 4px; }}
.sub {{ color: {PALETTE['muted']}; font-size: 8.5pt; }}
.logo {{ height: 34px; }}
section {{ break-inside: avoid; }}
.kpis {{ display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; }}
.kpi {{ border: 1px solid {PALETTE['line']}; border-radius: 6px;
        background: #fff; padding: 8px 9px; }}
.kl {{ font-size: 7.5pt; color: {PALETTE['muted']}; text-transform: uppercase;
       letter-spacing: .04em; }}
.kv {{ font-size: 15pt; font-weight: 700; color: {PALETTE['indigo']}; line-height: 1.15; }}
.ks {{ font-size: 7.5pt; color: {PALETTE['muted']}; min-height: 10px; }}
.delta.up {{ color: #1a7f4b; }} .delta.down {{ color: {PALETTE['magenta']}; }}
.delta.flat {{ color: {PALETTE['muted']}; }}
table {{ width: 100%; border-collapse: collapse; margin: 6px 0 10px; font-size: 8pt; }}
th {{ text-align: left; font-size: 7.5pt; text-transform: uppercase; letter-spacing: .04em;
      color: {PALETTE['muted']}; border-bottom: 1px solid {PALETTE['line']}; padding: 4px 5px; }}
td {{ padding: 4px 5px; border-bottom: 1px solid {PALETTE['soft']}; }}
td.mut, .mut {{ color: {PALETTE['muted']}; }}
.cols {{ display: flex; gap: 14px; align-items: flex-start; }}
.cols > div {{ flex: 1; }}
.dn {{ flex: 0 0 160px; text-align: center; }}
.legend {{ display: flex; flex-wrap: wrap; gap: 10px; font-size: 7.5pt;
           color: {PALETTE['muted']}; margin: 2px 0 6px; }}
.legend i {{ display: inline-block; width: 8px; height: 8px; border-radius: 2px;
             margin-right: 4px; }}
.empty {{ border: 1px dashed {PALETTE['line']}; background: {PALETTE['soft']};
          border-radius: 6px; padding: 8px 10px; font-size: 8pt; color: {PALETTE['muted']}; }}
.note {{ font-size: 7.5pt; color: {PALETTE['muted']}; margin: 4px 0 0; }}
footer {{ margin-top: 18px; border-top: 1px solid {PALETTE['line']}; padding-top: 8px;
          font-size: 7.5pt; color: {PALETTE['muted']}; }}
footer ul {{ margin: 4px 0 0; padding-left: 14px; }}
"""

    head = (f'<div><h1>{e(meta.get("domain"))}</h1>'
            f'<div class="sub">Domain Overview · {e(meta.get("location"))} · '
            f'{e((meta.get("generated_at") or "")[:10])} · source: {e(meta.get("source"))}</div></div>'
            + (f'<img class="logo" src="{logo}" alt="AIM"/>' if logo else
               '<div class="sub">Advanced Integrated Marketing</div>'))

    parts = [
        section("SEO overview", render_headline(P)),
        section("AI search visibility", render_ai(P)),
        section("Trends", render_trends(P)),
        section("Google SERP position distribution", render_serp(P)),
        section("Branded vs non-branded", render_branded(P)),
        section("Backlink profile", render_backlinks(P)),
        section("Closest organic competitors", render_table(
            P, "competitors",
            [("Domain", "domain", "s"), ("Shared keywords", "shared_keywords", "n"),
             ("Keywords", "keywords", "n"), ("Traffic / mo", "traffic_estimate", "n")],
            "no competitor data")),
        section("Top keywords by estimated traffic", render_table(
            P, "top_keywords",
            [("Keyword", "keyword", "s"), ("Pos", "position", "n"),
             ("Element", "serp_element", "s"), ("Volume", "search_volume", "n"),
             ("Traffic", "traffic_estimate", "n"), ("URL", "url", "s")],
            "no ranked keywords")),
        render_countries(P),
        section("Traffic channel mix", render_channels(P)),
    ]

    footer = (f'<footer><strong>Method.</strong> Every figure is retrieved from the '
              f'DataForSEO API v3 at the time shown; nothing is interpolated. '
              f'{"API spend for this run: $" + format(cost, ".4f") + ". " if cost is not None else ""}'
              f'AIM-defined metrics:<ul>{"".join(defs)}</ul></footer>')

    return (f'<!doctype html><html><head><meta charset="utf-8">'
            f'<title>{e(meta.get("domain"))} — Domain Overview</title>'
            f'<style>{css}</style></head><body><header>{head}</header>'
            f'{"".join(parts)}{footer}</body></html>')


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("input")
    ap.add_argument("--html", default="", help="write HTML here and skip the PDF")
    ap.add_argument("--output", default="")
    args = ap.parse_args()

    data = json.load(open(args.input))
    page = build_html(data)
    domain = (data.get("meta", {}).get("domain") or "domain").replace(".", "-")

    if args.html:
        open(args.html, "w").write(page)
        print(args.html)
        return 0

    out = args.output or f"{domain}-Domain-Overview-AIM.pdf"
    try:
        from weasyprint import HTML
    except ImportError:
        fallback = out.rsplit(".", 1)[0] + ".html"
        open(fallback, "w").write(page)
        print(f"WeasyPrint is not installed — wrote {fallback} instead.\n"
              f"Install it with: pip install weasyprint", file=sys.stderr)
        print(fallback)
        return 0
    HTML(string=page).write_pdf(out)
    print(out)
    return 0


if __name__ == "__main__":
    sys.exit(main())
