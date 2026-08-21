#!/usr/bin/env python3
"""
domain_overview.py — rebuild the Semrush "Domain Overview" dashboard from
DataForSEO, for one domain, as a single JSON file.

    python3 domain_overview.py aim-tex.com --out overview.json
    python3 domain_overview.py aim-tex.com --plan            # cost only, no calls
    python3 domain_overview.py aim-tex.com --ai --countries us,gb,de

Panels produced (see references/metric_map.md for the Semrush-to-DataForSEO
mapping and the caveats that go with each one):

    headline        organic/paid traffic + keywords, authority, backlinks
    trends          monthly organic/paid traffic and position buckets
    serp_features   what the domain's ranked positions actually are
    branded         branded vs non-branded share of ranked keywords
    top_keywords    the keywords carrying the traffic
    competitors     closest organic competitors + estimated traffic share
    backlinks       profile totals and, when available, the monthly trend
    countries       the headline block repeated per country (--countries)
    ai              AI Overview / AI Mode / ChatGPT / Gemini visibility (--ai)
    channels        traffic channel mix, only if supplied (--channels-json)

Two rules this file exists to enforce:

  1. Never invent a number. A panel that could not be retrieved is written as
     {"available": false, "reason": "..."} and the report renders an explicit
     empty state. Missing data is reported, not smoothed over.
  2. Never imply parity with Semrush. Authority, traffic share, branded split
     and the AI index are AIM's own definitions computed from DataForSEO
     inputs; they will not match Semrush's proprietary equivalents and every
     one of them carries a `definition` string saying so.
"""
import argparse
import json
import os
import re
import sys
import time
from datetime import date, datetime, timezone

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from dfs_client import Client, DFSError  # noqa: E402

LABS = "/v3/dataforseo_labs/google"
BACKLINKS = "/v3/backlinks"
SERP = "/v3/serp/google"
AI_OPT = "/v3/ai_optimization"

# Published list prices, used only by --plan. Actual spend is read back from
# each response's `cost` field and reported in meta.cost. Check the current
# numbers at https://dataforseo.com/pricing before quoting them to a client.
UNIT_COST = {
    "labs_task": 0.011,          # DataForSEO Labs, per call
    "labs_item": 0.0001,         # per returned row
    "backlinks_summary": 0.02,
    "backlinks_timeseries": 0.02,
    "serp_live_advanced": 0.002,
    "serp_ai_overview_addon": 0.0006,
    "ai_mode_live": 0.006,
    "llm_response": 0.006,       # base task fee + LLM fee; varies by prompt size
}

# ISO-3166 alpha-2 -> DataForSEO location_name, for the --countries shorthand.
COUNTRY_NAMES = {
    "us": "United States", "gb": "United Kingdom", "uk": "United Kingdom",
    "ca": "Canada", "au": "Australia", "de": "Germany", "fr": "France",
    "es": "Spain", "it": "Italy", "nl": "Netherlands", "mx": "Mexico",
    "br": "Brazil", "in": "India", "jp": "Japan", "ae": "United Arab Emirates",
    "sg": "Singapore", "za": "South Africa", "ie": "Ireland", "nz": "New Zealand",
}

POSITION_BUCKETS = [
    ("top_3", ["pos_1", "pos_2_3"]),
    ("pos_4_10", ["pos_4_10"]),
    ("pos_11_20", ["pos_11_20"]),
    ("pos_21_50", ["pos_21_30", "pos_31_40", "pos_41_50"]),
    ("pos_51_100", ["pos_51_60", "pos_61_70", "pos_71_80", "pos_81_90", "pos_91_100"]),
]

URL_RE = re.compile(r"https?://[^\s\"'<>)\]}]+", re.I)


# --------------------------------------------------------------- utilities --

def host_of(url):
    if not url:
        return ""
    s = str(url).strip()
    s = re.sub(r"^[a-z]+://", "", s, flags=re.I)
    s = s.split("/")[0].split("?")[0].split("#")[0]
    return s.lower().lstrip(".").removeprefix("www.")


def clean_domain(raw):
    d = host_of(raw)
    if not d or "." not in d:
        raise SystemExit(f"'{raw}' does not look like a domain")
    return d


def brand_tokens(domain, extra):
    """Tokens that mark a keyword as branded. Short tokens are dropped —
    a two-letter brand matches half the corpus and makes the split useless."""
    sld = domain.split(".")[0]
    toks = {t for t in re.split(r"[^a-z0-9]+", sld.lower()) if len(t) >= 3}
    toks.add(sld.lower().replace("-", ""))
    for t in (extra or "").split(","):
        t = t.strip().lower()
        if len(t) >= 3:
            toks.add(t)
    return sorted(t for t in toks if len(t) >= 3)


def walk_strings(node):
    """Every string anywhere in a nested payload."""
    if isinstance(node, str):
        yield node
    elif isinstance(node, dict):
        for v in node.values():
            yield from walk_strings(v)
    elif isinstance(node, (list, tuple)):
        for v in node:
            yield from walk_strings(v)


def walk_urls(node):
    """
    Every URL anywhere in a nested payload — from url-ish keys and from links
    embedded in markdown or prose.

    Deliberately generic. The AI endpoints nest citations differently per
    engine and the shapes move as the engines change; matching on structure
    would silently return "no citations" the first time a field is renamed,
    which reads as a visibility collapse. Over-collecting is the safe error
    here: a stray URL is visible in the output, a missed citation is not.
    """
    if isinstance(node, str):
        for m in URL_RE.findall(node):
            yield m.rstrip(".,);]")
    elif isinstance(node, dict):
        for k, v in node.items():
            if isinstance(v, str) and k in ("domain", "source_domain") and "." in v:
                yield "https://" + v
            else:
                yield from walk_urls(v)
    elif isinstance(node, (list, tuple)):
        for v in node:
            yield from walk_urls(v)


def pct(part, whole, nd=1):
    if not whole:
        return None
    return round(100.0 * part / whole, nd)


def unavailable(reason):
    return {"available": False, "reason": reason}


def month_label(item):
    y, m = item.get("year"), item.get("month")
    if y and m:
        return f"{int(y):04d}-{int(m):02d}"
    for key in ("date", "date_from"):
        v = item.get(key)
        if v:
            return str(v)[:7]
    return None


# ---------------------------------------------------------------- panels ---

def panel_headline(c, domain, location, language):
    res = c.call(f"{LABS}/domain_rank_overview/live", {
        "target": domain, "location_name": location, "language_code": language,
        "ignore_synonyms": True,
    })
    items = (res or {}).get("items") or []
    if not items:
        return unavailable("domain_rank_overview returned no rows for this domain/location")
    m = (items[0].get("metrics") or {})
    org, paid = m.get("organic") or {}, m.get("paid") or {}

    def block(src):
        return {
            "keywords": src.get("count"),
            "traffic_estimate_monthly": round(float(src.get("etv") or 0), 1),
            "traffic_cost_usd": round(float(src.get("estimated_paid_traffic_cost") or 0), 2),
            "impressions_estimate": src.get("impressions_etv"),
            "positions": {name: sum(int(src.get(f) or 0) for f in fields)
                          for name, fields in POSITION_BUCKETS},
            "movement": {k: src.get(k) for k in ("is_new", "is_up", "is_down", "is_lost")},
        }

    return {
        "available": True,
        "organic": block(org),
        "paid": block(paid),
        "definition": ("traffic_estimate_monthly is DataForSEO's ETV (estimated traffic "
                       "volume) for the location, not measured sessions. Compare trends, "
                       "not absolute values, against Semrush."),
    }


def panel_trends(c, domain, location, language, months):
    today = date.today()
    start_year = today.year - (months // 12) - 1
    res = c.call(f"{LABS}/historical_rank_overview/live", {
        "target": domain, "location_name": location, "language_code": language,
        "date_from": f"{max(start_year, 2020)}-10-01",
        "ignore_synonyms": True,
    })
    items = (res or {}).get("items") or []
    if not items:
        return unavailable("historical_rank_overview returned no rows (history starts 2020-10-01)")

    series = []
    for it in items:
        label = month_label(it)
        if not label:
            continue
        m = it.get("metrics") or {}
        org, paid = m.get("organic") or {}, m.get("paid") or {}
        series.append({
            "month": label,
            "organic_traffic": round(float(org.get("etv") or 0), 1),
            "paid_traffic": round(float(paid.get("etv") or 0), 1),
            "organic_keywords": org.get("count") or 0,
            "positions": {name: sum(int(org.get(f) or 0) for f in fields)
                          for name, fields in POSITION_BUCKETS},
        })
    series.sort(key=lambda r: r["month"])
    series = series[-months:]

    change = None
    if len(series) >= 2:
        prev, last = series[-2]["organic_traffic"], series[-1]["organic_traffic"]
        if prev:
            change = round(100.0 * (last - prev) / prev, 1)
    return {"available": True, "months": len(series), "series": series,
            "organic_traffic_change_pct": change,
            "definition": "Month-over-month change on the last two points of the ETV series."}


def panel_keywords(c, domain, location, language, limit, tokens):
    res = c.call(f"{LABS}/ranked_keywords/live", {
        "target": domain, "location_name": location, "language_code": language,
        "limit": min(limit, 1000), "ignore_synonyms": True,
        "order_by": ["ranked_serp_element.serp_item.etv,desc"],
        "load_rank_absolute": True,
    })
    items = (res or {}).get("items") or []
    if not items:
        return unavailable("ranked_keywords returned no rows"), unavailable("no ranked keywords"), \
               unavailable("no ranked keywords")

    by_type, rows = {}, []
    branded = {"keywords": 0, "traffic": 0.0}
    generic = {"keywords": 0, "traffic": 0.0}

    for it in items:
        kd = it.get("keyword_data") or {}
        rse = it.get("ranked_serp_element") or {}
        si = rse.get("serp_item") or {}
        kw = kd.get("keyword") or ""
        etv = float(si.get("etv") or 0)
        stype = (si.get("type") or "unknown").lower()
        by_type[stype] = by_type.get(stype, 0) + 1

        bucket = branded if any(t in kw.lower() for t in tokens) else generic
        bucket["keywords"] += 1
        bucket["traffic"] += etv

        rows.append({
            "keyword": kw,
            "position": si.get("rank_group"),
            "position_absolute": si.get("rank_absolute"),
            "serp_element": stype,
            "url": si.get("url"),
            "search_volume": ((kd.get("keyword_info") or {}).get("search_volume")),
            "cpc": ((kd.get("keyword_info") or {}).get("cpc")),
            "traffic_estimate": round(etv, 1),
            "serp_features_present": rse.get("serp_item_types") or [],
        })

    total = sum(by_type.values())
    ai_types = {t: n for t, n in by_type.items() if "ai_overview" in t or "ai_mode" in t}
    organic_n = by_type.get("organic", 0)
    other_n = total - organic_n - sum(ai_types.values())

    serp_panel = {
        "available": True,
        "sampled_keywords": total,
        "sample_is_capped": total >= min(limit, 1000),
        "distribution": {
            "organic": {"count": organic_n, "pct": pct(organic_n, total)},
            "ai_overviews": {"count": sum(ai_types.values()), "pct": pct(sum(ai_types.values()), total)},
            "other_serp_features": {"count": other_n, "pct": pct(other_n, total)},
        },
        "by_element_type": dict(sorted(by_type.items(), key=lambda kv: -kv[1])),
        "definition": ("Share of the domain's ranked positions by the SERP element it "
                       "occupies, over the highest-traffic keywords retrieved. With the "
                       "sample capped this is the shape of the top of the profile, not "
                       "the whole profile."),
    }

    tt = branded["traffic"] + generic["traffic"]
    branded_panel = {
        "available": True,
        "brand_tokens": tokens,
        "branded": {"keywords": branded["keywords"], "traffic": round(branded["traffic"], 1),
                    "traffic_pct": pct(branded["traffic"], tt)},
        "non_branded": {"keywords": generic["keywords"], "traffic": round(generic["traffic"], 1),
                        "traffic_pct": pct(generic["traffic"], tt)},
        "definition": ("Keyword-token match against the brand tokens listed above — an "
                       "approximation of Semrush's branded-traffic split, not the same "
                       "method. Pass --brand-terms to correct it."),
    }

    top_panel = {"available": True, "rows": rows[:25],
                 "definition": "Top 25 retrieved keywords by estimated traffic."}
    return serp_panel, branded_panel, top_panel


def panel_competitors(c, domain, location, language, headline, n):
    res = c.call(f"{LABS}/competitors_domain/live", {
        "target": domain, "location_name": location, "language_code": language,
        "limit": max(n, 1) + 1, "ignore_synonyms": True,
        "order_by": ["intersections,desc"],
    })
    items = (res or {}).get("items") or []
    rivals = []
    for it in items:
        dom = (it.get("domain") or "").lower()
        if not dom or dom == domain:
            continue
        m = ((it.get("metrics") or {}).get("organic") or {})
        rivals.append({
            "domain": dom,
            "shared_keywords": it.get("intersections"),
            "keywords": m.get("count"),
            "traffic_estimate": round(float(m.get("etv") or 0), 1),
        })
        if len(rivals) >= n:
            break
    if not rivals:
        return unavailable("competitors_domain returned no overlapping domains")

    mine = 0.0
    if headline.get("available"):
        mine = float(headline["organic"]["traffic_estimate_monthly"] or 0)
    pool = mine + sum(r["traffic_estimate"] for r in rivals)
    return {
        "available": True,
        "competitors": rivals,
        "traffic_share_pct": pct(mine, pool),
        "definition": (f"Share of estimated organic traffic across this domain and its top "
                       f"{len(rivals)} organic competitors. Semrush's Traffic Share is "
                       f"computed over a different, market-level competitor set and will "
                       f"not match."),
    }


def panel_backlinks(c, domain):
    res = c.call(f"{BACKLINKS}/summary/live", {
        "target": domain, "internal_list_limit": 1,
        "backlinks_status_type": "live", "include_subdomains": True,
    })
    if not res:
        return unavailable("backlinks/summary returned no result")
    rank = res.get("rank")
    total = int(res.get("backlinks") or 0)
    nofollow = int(((res.get("referring_links_attributes") or {}).get("nofollow")) or 0)
    return {
        "available": True,
        "domain_rank": rank,
        "authority_0_100": round(float(rank) / 10.0, 1) if rank is not None else None,
        "backlinks": total,
        "referring_domains": res.get("referring_domains"),
        "referring_main_domains": res.get("referring_main_domains"),
        "referring_ips": res.get("referring_ips"),
        "broken_backlinks": res.get("broken_backlinks"),
        "spam_score": res.get("backlinks_spam_score"),
        "dofollow_pct": pct(total - nofollow, total) if total else None,
        "first_seen": res.get("first_seen"),
        "definition": ("authority_0_100 is DataForSEO's 0-1000 domain rank divided by 10. "
                       "It is a different model from Semrush's Authority Score and the two "
                       "numbers should never be presented as interchangeable."),
    }


def panel_backlinks_trend(c, domain, months):
    try:
        res = c.call(f"{BACKLINKS}/timeseries_summary/live", {
            "target": domain, "date_from": f"{date.today().year - 2}-01-01",
            "group_range": "month", "include_subdomains": True,
        })
    except DFSError as e:
        return unavailable(f"timeseries_summary unavailable: {e}")
    items = (res or {}).get("items") or []
    if not items:
        return unavailable("timeseries_summary returned no rows")
    series = [{
        "month": str(it.get("date") or it.get("date_from") or "")[:7],
        "backlinks": it.get("backlinks"),
        "referring_domains": it.get("referring_domains"),
        "new_backlinks": it.get("new_backlinks"),
        "lost_backlinks": it.get("lost_backlinks"),
    } for it in items if (it.get("date") or it.get("date_from"))]
    series.sort(key=lambda r: r["month"])
    return {"available": True, "series": series[-months:]}


def panel_countries(c, domain, codes, language):
    out = {}
    for code in codes:
        code = code.strip().lower()
        if not code:
            continue
        name = COUNTRY_NAMES.get(code, code if len(code) > 3 else None)
        if not name:
            out[code] = unavailable(f"unknown country code '{code}' — pass a full location name")
            continue
        try:
            out[code] = {"location": name, **panel_headline(c, domain, name, language)}
        except DFSError as e:
            out[code] = unavailable(str(e))
    return {"available": bool(out), "by_country": out}


# ------------------------------------------------------------------- AI ----

def _mentions(payload, domain, tokens):
    """(named, cited_urls) for one engine answer, from a whole-payload sweep."""
    text = " ".join(walk_strings(payload)).lower()
    named = domain in text or any(t in text for t in tokens)
    cited, sources = [], {}
    for u in walk_urls(payload):
        h = host_of(u)
        if not h:
            continue
        sources[h] = sources.get(h, 0) + 1
        if h == domain or h.endswith("." + domain):
            cited.append(u)
    return named, sorted(set(cited)), sources


def _ai_engine_run(c, engine, prompts, domain, tokens, location, language):
    answered = 0
    named_n = 0
    cited_pages, sources = set(), {}
    errors = []

    for p in prompts:
        try:
            if engine == "ai_overview":
                res = c.call(f"{SERP}/organic/live/advanced", {
                    "keyword": p, "location_name": location, "language_code": language,
                    "device": "desktop", "depth": 10, "load_async_ai_overview": True,
                })
                blocks = [i for i in ((res or {}).get("items") or [])
                          if "ai_overview" in str(i.get("type") or "").lower()]
                if not blocks:
                    answered += 1          # SERP returned, simply no AI Overview
                    continue
                payload = blocks
            elif engine == "ai_mode":
                res = c.call(f"{SERP}/ai_mode/live/advanced", {
                    "keyword": p, "location_name": location, "language_code": language,
                    "device": "desktop",
                })
                payload = (res or {}).get("items") or res
            else:  # chatgpt | gemini | claude | perplexity
                slug = {"chatgpt": "chat_gpt"}.get(engine, engine)
                res = c.call(f"{AI_OPT}/{slug}/llm_responses/live", {
                    "user_prompt": p, "web_search": True,
                })
                payload = (res or {}).get("items") or res
            if not payload:
                errors.append(f"{p!r}: empty response")
                continue
            answered += 1
            named, cited, srcs = _mentions(payload, domain, tokens)
            named_n += 1 if named else 0
            cited_pages.update(cited)
            for h, n in srcs.items():
                sources[h] = sources.get(h, 0) + n
        except DFSError as e:
            errors.append(f"{p!r}: {e}")

    if not answered:
        return unavailable(f"{engine}: no prompt returned a usable response. " +
                           ("; ".join(errors[:3]) if errors else ""))
    mention_rate = named_n / answered
    citation_rate = min(1.0, len(cited_pages) / answered)
    return {
        "available": True,
        "prompts_run": len(prompts),
        "prompts_answered": answered,
        "mentions": named_n,
        "cited_pages": len(cited_pages),
        "cited_urls": sorted(cited_pages)[:25],
        "visibility_0_100": round(100 * (0.6 * mention_rate + 0.4 * citation_rate)),
        "top_sources": dict(sorted(sources.items(), key=lambda kv: -kv[1])[:10]),
        "errors": errors[:5],
    }


def panel_ai(c, domain, tokens, prompts, engines, location, language):
    per_engine, sources = {}, {}
    for e in engines:
        per_engine[e] = _ai_engine_run(c, e, prompts, domain, tokens, location, language)
        for h, n in (per_engine[e].get("top_sources") or {}).items():
            sources[h] = sources.get(h, 0) + n

    scored = [v["visibility_0_100"] for v in per_engine.values() if v.get("available")]
    return {
        "available": bool(scored),
        "visibility_0_100": round(sum(scored) / len(scored)) if scored else None,
        "mentions": sum(v.get("mentions", 0) for v in per_engine.values() if v.get("available")),
        "cited_pages": sum(v.get("cited_pages", 0) for v in per_engine.values() if v.get("available")),
        "by_engine": per_engine,
        "top_cited_sources": dict(sorted(sources.items(), key=lambda kv: -kv[1])[:10]),
        "prompt_count": len(prompts),
        "definition": ("AIM AI Visibility Index: per engine, 60% share of prompts naming the "
                       "brand plus 40% share of prompts citing the domain, averaged across "
                       "engines. It is AIM's own formula on the run's own prompt set — it is "
                       "not Semrush's AI Visibility score and the two will differ."),
    }


# ------------------------------------------------------------------ plan ---

def plan(args, prompts, engines):
    kw = min(args.keywords_limit, 1000)
    labs = UNIT_COST["labs_task"]
    est = {
        "headline": labs,
        "trends": labs,
        "ranked_keywords": labs + kw * UNIT_COST["labs_item"],
        "competitors": labs + args.competitors * UNIT_COST["labs_item"],
        "backlinks_summary": UNIT_COST["backlinks_summary"],
        "backlinks_trend": UNIT_COST["backlinks_timeseries"],
    }
    if args.countries:
        est["countries"] = labs * len([x for x in args.countries.split(",") if x.strip()])
    if args.ai:
        n = len(prompts)
        per = {
            "ai_overview": n * (UNIT_COST["serp_live_advanced"] + UNIT_COST["serp_ai_overview_addon"]),
            "ai_mode": n * UNIT_COST["ai_mode_live"],
        }
        est["ai"] = sum(per.get(e, n * UNIT_COST["llm_response"]) for e in engines)
    total = sum(est.values())
    return {
        "estimated_cost_usd": round(total, 4),
        "by_panel": {k: round(v, 4) for k, v in est.items()},
        "note": ("List-price estimate only. The run reports actual spend from each "
                 "response's cost field. LLM response pricing varies with prompt size."),
    }


# ------------------------------------------------------------------ main ---

def main():
    ap = argparse.ArgumentParser(description="Semrush-style domain overview via DataForSEO")
    ap.add_argument("domain")
    ap.add_argument("--location", default="United States")
    ap.add_argument("--language", default="en")
    ap.add_argument("--countries", default="", help="extra ISO codes, e.g. us,gb,de")
    ap.add_argument("--months", type=int, default=24)
    ap.add_argument("--keywords-limit", type=int, default=1000)
    ap.add_argument("--competitors", type=int, default=5)
    ap.add_argument("--brand-terms", default="", help="comma-separated extra brand tokens")
    ap.add_argument("--ai", action="store_true", help="run the AI visibility block (costs more)")
    ap.add_argument("--ai-engines", default="ai_overview,ai_mode,chatgpt,gemini")
    ap.add_argument("--ai-prompts", default="", help="JSON file: {\"prompts\": [...]} or [...]")
    ap.add_argument("--channels-json", default="",
                    help="channel mix from GA4/the AIM platform: [{channel,sessions}]")
    ap.add_argument("--plan", action="store_true", help="print the cost estimate and exit")
    ap.add_argument("--out", default="")
    args = ap.parse_args()

    domain = clean_domain(args.domain)
    tokens = brand_tokens(domain, args.brand_terms)

    prompts = []
    if args.ai:
        path = args.ai_prompts or os.path.join(
            os.path.dirname(os.path.abspath(__file__)), "..", "references", "ai_prompt_set.json")
        try:
            raw = json.load(open(path))
            prompts = raw["prompts"] if isinstance(raw, dict) else raw
        except Exception as e:
            raise SystemExit(f"could not read AI prompt set at {path}: {e}")
        prompts = [p.replace("{domain}", domain) for p in prompts]
    engines = [e.strip() for e in args.ai_engines.split(",") if e.strip()]

    if args.plan:
        print(json.dumps(plan(args, prompts, engines), indent=2))
        return 0

    started = time.time()
    try:
        c = Client()
    except DFSError as e:
        raise SystemExit(str(e))
    print(f"DataForSEO · {domain} · {args.location}", file=sys.stderr)

    out = {
        "meta": {
            "domain": domain,
            "location": args.location,
            "language": args.language,
            "generated_at": datetime.now(timezone.utc).isoformat(timespec="seconds"),
            "source": "DataForSEO API v3",
            "credentials_from": c.cred_source,
        },
        "panels": {},
    }
    P = out["panels"]

    def run(name, fn):
        try:
            P[name] = fn()
        except DFSError as e:
            P[name] = unavailable(str(e))
            print(f"  ! {name}: {e}", file=sys.stderr)

    run("headline", lambda: panel_headline(c, domain, args.location, args.language))
    run("trends", lambda: panel_trends(c, domain, args.location, args.language, args.months))

    try:
        serp_p, brand_p, top_p = panel_keywords(
            c, domain, args.location, args.language, args.keywords_limit, tokens)
    except DFSError as e:
        serp_p = brand_p = top_p = unavailable(str(e))
        print(f"  ! ranked_keywords: {e}", file=sys.stderr)
    P["serp_features"], P["branded"], P["top_keywords"] = serp_p, brand_p, top_p

    run("competitors", lambda: panel_competitors(
        c, domain, args.location, args.language, P.get("headline", {}), args.competitors))
    run("backlinks", lambda: panel_backlinks(c, domain))
    run("backlinks_trend", lambda: panel_backlinks_trend(c, domain, args.months))

    if args.countries:
        run("countries", lambda: panel_countries(
            c, domain, args.countries.split(","), args.language))

    if args.ai:
        run("ai", lambda: panel_ai(c, domain, tokens, prompts, engines,
                                   args.location, args.language))

    if args.channels_json:
        try:
            rows = json.load(open(args.channels_json))
            rows = rows["channels"] if isinstance(rows, dict) else rows
            tot = sum(float(r.get("sessions") or r.get("users") or 0) for r in rows)
            P["channels"] = {
                "available": True,
                "source": os.path.basename(args.channels_json),
                "rows": [{"channel": r.get("channel"),
                          "sessions": r.get("sessions") or r.get("users"),
                          "pct": pct(float(r.get("sessions") or r.get("users") or 0), tot)}
                         for r in rows],
                "definition": ("Measured channel mix supplied by the operator (GA4 / the AIM "
                               "platform). DataForSEO has no clickstream channel data, so "
                               "this panel is never estimated."),
            }
        except Exception as e:
            P["channels"] = unavailable(f"could not read {args.channels_json}: {e}")
    else:
        P["channels"] = unavailable(
            "No channel data supplied. Semrush's channel mix is clickstream-derived and has "
            "no DataForSEO equivalent; pass --channels-json with GA4 figures for a real one.")

    out["meta"]["cost"] = c.ledger()
    out["meta"]["duration_seconds"] = round(time.time() - started, 1)

    path = args.out or f"{domain.replace('.', '-')}-overview.json"
    with open(path, "w") as f:
        json.dump(out, f, indent=2)

    h = P.get("headline", {})
    b = P.get("backlinks", {})
    print(f"\nWrote {path}", file=sys.stderr)
    if h.get("available"):
        print(f"  organic keywords {h['organic']['keywords']}  "
              f"traffic {h['organic']['traffic_estimate_monthly']}  "
              f"paid keywords {h['paid']['keywords']}", file=sys.stderr)
    if b.get("available"):
        print(f"  authority {b['authority_0_100']}  backlinks {b['backlinks']}  "
              f"referring domains {b['referring_domains']}", file=sys.stderr)
    print(f"  spent ${c.cost:.4f} across {len(c.calls)} calls", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())
