#!/usr/bin/env python3
"""
selftest.py — run the whole pipeline offline against canned DataForSEO-shaped
payloads, then render the report.

    python3 selftest.py

No credentials, no network, no cost. This exists because the parsers are the
part most likely to break quietly: a renamed field returns "no data", which on
a dashboard reads as a domain with no keywords rather than as a bug. Run it
after touching any panel_* function, and after any DataForSEO schema change.

The payloads below are fabricated. They mirror the documented response shapes;
they are not a snapshot of any real domain.
"""
import json
import os
import sys
import tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)

import domain_overview as D  # noqa: E402


def labs_metrics(count, etv, base=1):
    return {"count": count, "etv": etv, "estimated_paid_traffic_cost": etv * 5,
            "impressions_etv": etv * 60, "is_new": 3, "is_up": 9, "is_down": 4, "is_lost": 1,
            "pos_1": base, "pos_2_3": base * 2, "pos_4_10": base * 5, "pos_11_20": base * 7,
            "pos_21_30": base * 6, "pos_31_40": base * 5, "pos_41_50": base * 4,
            "pos_51_60": base * 3, "pos_61_70": base * 3, "pos_71_80": base * 2,
            "pos_81_90": base * 2, "pos_91_100": base}


FIXTURES = {
    "/v3/dataforseo_labs/google/domain_rank_overview/live": {
        "items": [{"metrics": {"organic": labs_metrics(292, 152.4),
                               "paid": labs_metrics(0, 0.0, base=0)}}]},
    "/v3/dataforseo_labs/google/historical_rank_overview/live": {
        "items": [{"year": 2026, "month": m,
                   "metrics": {"organic": labs_metrics(200 + m * 5, 100.0 + m * 7, base=m),
                               "paid": labs_metrics(0, 0.0, base=0)}}
                  for m in range(1, 9)]},
    "/v3/dataforseo_labs/google/ranked_keywords/live": {
        "items": [
            {"keyword_data": {"keyword": "widget repair",
                              "keyword_info": {"search_volume": 880, "cpc": 6.4}},
             "ranked_serp_element": {"serp_item": {"type": "organic", "rank_group": 3,
                                                   "rank_absolute": 5, "etv": 21.4,
                                                   "url": "https://example.com/repair"},
                                     "serp_item_types": ["organic", "ai_overview"]}},
            {"keyword_data": {"keyword": "example brand reviews",
                              "keyword_info": {"search_volume": 210, "cpc": 1.1}},
             "ranked_serp_element": {"serp_item": {"type": "organic", "rank_group": 1,
                                                   "rank_absolute": 1, "etv": 44.0,
                                                   "url": "https://example.com/"},
                                     "serp_item_types": ["organic"]}},
            {"keyword_data": {"keyword": "widget supplier near me",
                              "keyword_info": {"search_volume": 590, "cpc": 8.0}},
             "ranked_serp_element": {"serp_item": {"type": "local_pack", "rank_group": 2,
                                                   "rank_absolute": 2, "etv": 12.9,
                                                   "url": "https://example.com/dallas"},
                                     "serp_item_types": ["local_pack", "organic"]}},
            {"keyword_data": {"keyword": "what is a widget",
                              "keyword_info": {"search_volume": 2400, "cpc": 1.2}},
             "ranked_serp_element": {"serp_item": {"type": "ai_overview_reference",
                                                   "rank_group": 1, "rank_absolute": 1,
                                                   "etv": 30.0,
                                                   "url": "https://example.com/guide"},
                                     "serp_item_types": ["ai_overview", "organic"]}},
        ]},
    "/v3/dataforseo_labs/google/competitors_domain/live": {
        "items": [{"domain": "example.com", "intersections": 292,
                   "metrics": {"organic": {"count": 292, "etv": 152.4}}},
                  {"domain": "rival-one.com", "intersections": 120,
                   "metrics": {"organic": {"count": 900, "etv": 410.2}}},
                  {"domain": "rival-two.com", "intersections": 95,
                   "metrics": {"organic": {"count": 640, "etv": 300.7}}}]},
    "/v3/backlinks/summary/live": {
        "rank": 190, "backlinks": 3300, "referring_domains": 281,
        "referring_main_domains": 260, "referring_ips": 240, "broken_backlinks": 12,
        "backlinks_spam_score": 8, "first_seen": "2016-04-02",
        "referring_links_attributes": {"nofollow": 842}},
    "/v3/backlinks/timeseries_summary/live": {
        "items": [{"date": f"2026-0{m}-01T00:00:00+00:00", "backlinks": 3000 + m * 40,
                   "referring_domains": 250 + m, "new_backlinks": 9, "lost_backlinks": 4}
                  for m in range(1, 9)]},
    "/v3/serp/google/organic/live/advanced": {
        "items": [{"type": "organic", "url": "https://rival-one.com/a"},
                  {"type": "ai_overview", "references": [
                      {"domain": "example.com", "url": "https://example.com/guide",
                       "title": "Example guide"},
                      {"domain": "reddit.com", "url": "https://reddit.com/r/x/1"}],
                   "markdown": "Example is a widget maker in Texas."}]},
    "/v3/serp/google/ai_mode/live/advanced": {
        "items": [{"type": "ai_mode_message", "text": "Companies like Example serve that market.",
                   "references": [{"domain": "example.com", "url": "https://example.com/"},
                                  {"domain": "youtube.com", "url": "https://youtube.com/w?v=1"}]}]},
    "/v3/ai_optimization/chat_gpt/llm_responses/live": {
        "items": [{"sections": [{"text": "Example is a widget supplier.",
                                 "annotations": [{"url": "https://example.com/about"},
                                                 {"url": "https://rankmath.com/blog/x"}]}]}]},
}


class FakeClient:
    """Stands in for dfs_client.Client: same surface, canned answers, zero cost."""

    def __init__(self, *a, **k):
        self.cost = 0.0
        self.calls = []
        self.cred_source = {"login_from": "SELFTEST", "password_from": "SELFTEST"}

    def call(self, path, task=None, allow_empty=True):
        self.calls.append({"path": path, "cost": 0.0, "result_count": 1})
        if path not in FIXTURES:
            raise D.DFSError(f"selftest has no fixture for {path}")
        return FIXTURES[path]

    def ledger(self):
        return {"total_cost_usd": 0.0, "calls": self.calls}


def check(label, got, want):
    status = "ok  " if got == want else "FAIL"
    print(f"  {status} {label}: {got!r}" + ("" if got == want else f" (expected {want!r})"))
    return got == want


def main():
    D.Client = FakeClient
    tmp = tempfile.mkdtemp()
    out = os.path.join(tmp, "overview.json")
    sys.argv = ["domain_overview.py", "example.com", "--out", out, "--ai",
                "--ai-engines", "ai_overview,ai_mode,chatgpt", "--countries", "us",
                "--brand-terms", "example"]
    D.main()

    data = json.load(open(out))
    P = data["panels"]
    passed = True

    print("\nheadline")
    passed &= check("organic keywords", P["headline"]["organic"]["keywords"], 292)
    passed &= check("organic traffic", P["headline"]["organic"]["traffic_estimate_monthly"], 152.4)
    passed &= check("top-3 bucket = pos_1 + pos_2_3", P["headline"]["organic"]["positions"]["top_3"], 3)
    passed &= check("51-100 bucket sums five fields",
                    P["headline"]["organic"]["positions"]["pos_51_100"], 11)

    print("trends")
    passed &= check("months parsed", P["trends"]["months"], 8)
    passed &= check("first month label", P["trends"]["series"][0]["month"], "2026-01")
    passed &= check("mom change computed", P["trends"]["organic_traffic_change_pct"] is not None, True)

    print("serp_features")
    d = P["serp_features"]["distribution"]
    passed &= check("organic count", d["organic"]["count"], 2)
    passed &= check("ai overview bucket catches ai_overview_reference", d["ai_overviews"]["count"], 1)
    passed &= check("other features count", d["other_serp_features"]["count"], 1)

    print("branded")
    # only "example brand reviews" carries the token; the other three are generic
    passed &= check("branded keywords", P["branded"]["branded"]["keywords"], 1)
    passed &= check("non-branded keywords", P["branded"]["non_branded"]["keywords"], 3)

    print("competitors")
    passed &= check("self excluded", [c["domain"] for c in P["competitors"]["competitors"]],
                    ["rival-one.com", "rival-two.com"])
    passed &= check("traffic share", P["competitors"]["traffic_share_pct"], 17.7)  # 152.4/863.3

    print("backlinks")
    passed &= check("authority = rank/10", P["backlinks"]["authority_0_100"], 19.0)
    passed &= check("dofollow pct", P["backlinks"]["dofollow_pct"], 74.5)
    passed &= check("trend months", len(P["backlinks_trend"]["series"]), 8)

    print("ai")
    passed &= check("engines reported", sorted(P["ai"]["by_engine"]),
                    ["ai_mode", "ai_overview", "chatgpt"])
    passed &= check("domain citations found", P["ai"]["cited_pages"] > 0, True)
    passed &= check("third-party sources pooled",
                    any(h in P["ai"]["top_cited_sources"] for h in ("reddit.com", "youtube.com")), True)
    passed &= check("index in range", 0 <= (P["ai"]["visibility_0_100"] or 0) <= 100, True)

    print("countries / channels")
    passed &= check("us present", P["countries"]["by_country"]["us"]["available"], True)
    passed &= check("channels explicitly unavailable", P["channels"]["available"], False)

    print("report")
    import build_report
    html = build_report.build_html(data)
    passed &= check("html built", len(html) > 5000, True)
    passed &= check("svg charts present", "<svg" in html, True)
    passed &= check("empty state rendered for channels", "Not available." in html, True)

    print("\n" + ("ALL CHECKS PASSED" if passed else "FAILURES ABOVE"))
    return 0 if passed else 1


if __name__ == "__main__":
    sys.exit(main())
