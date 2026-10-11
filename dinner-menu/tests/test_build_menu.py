"""Regression tests for the weekly dinner menu builder.

Run from the dinner-menu folder:
    python3 -m unittest discover -s tests -v
"""

import copy
import json
import sys
import unittest
from pathlib import Path

HERE = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(HERE))

import build_menu as bm  # noqa: E402

MENU = json.loads((HERE / "menus" / "2026-10-17.json").read_text(encoding="utf-8"))
RATINGS = bm.load_ratings()
HISTORY = bm.load_history()


def run_checks(menu):
    html_text = bm.build_html(menu, {}, RATINGS)
    subject = bm.subject_for(menu)
    return bm.validate(menu, html_text, RATINGS, HISTORY, subject)


def failures(results):
    return [name for name, ok, _ in results if not ok]


class ValidMenu(unittest.TestCase):
    def test_example_menu_passes_every_check(self):
        self.assertEqual(failures(run_checks(MENU)), [])

    def test_subject_format(self):
        self.assertEqual(bm.subject_for(MENU),
                         "Brig & Dawn's Weekly Dinner Menu - October 17, 2026")

    def test_send_date_is_saturday(self):
        self.assertEqual(bm.date.fromisoformat(MENU["send_date"]).weekday(), 5)


class RejectedMenus(unittest.TestCase):
    def test_three_dinners_fails(self):
        bad = copy.deepcopy(MENU)
        bad["dinners"] = bad["dinners"][:3]
        self.assertIn("Exactly four dinners", failures(run_checks(bad)))

    def test_dinner_over_30_minutes_fails(self):
        bad = copy.deepcopy(MENU)
        d = bad["dinners"][0]
        d["rest_min"] = d["rest_min"] + 10
        d["total_min"] = d["total_min"] + 10
        self.assertTrue(any("30 minutes or less" in f for f in failures(run_checks(bad))))

    def test_lemon_garlic_chicken_fails(self):
        bad = copy.deepcopy(MENU)
        bad["dinners"][3]["title"] = "Lemon-Garlic Chicken"
        self.assertTrue(any("lemon-garlic chicken" in f.lower() for f in failures(run_checks(bad))))

    def test_mushrooms_fail(self):
        bad = copy.deepcopy(MENU)
        bad["dinners"][1]["steps"].append("Add sliced mushrooms.")
        self.assertTrue(any('"mushroom"' in f for f in failures(run_checks(bad))))

    def test_recently_served_dish_fails(self):
        bad = copy.deepcopy(MENU)
        bad["dinners"][2]["title"] = "Honey-Dijon Pork Chops with Quick Apples"
        self.assertTrue(failures(run_checks(bad)))

    def test_pork_without_145_fails(self):
        bad = copy.deepcopy(MENU)
        bad["dinners"][2]["steps"] = [s.replace("145", "140") for s in bad["dinners"][2]["steps"]]
        self.assertTrue(any("145 F" in f for f in failures(run_checks(bad))))

    def test_auto_order_fails(self):
        bad = copy.deepcopy(MENU)
        bad["grocery"]["not_ordered_automatically"] = False
        self.assertIn("Grocery list is not auto-ordered", failures(run_checks(bad)))

    def test_repeated_protein_fails(self):
        bad = copy.deepcopy(MENU)
        bad["dinners"][3]["protein"] = "Beef sirloin (steak)"
        self.assertIn("Each dinner uses a different protein", failures(run_checks(bad)))


if __name__ == "__main__":
    unittest.main()
