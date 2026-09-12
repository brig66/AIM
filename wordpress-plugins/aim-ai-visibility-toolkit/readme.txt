=== AIM AI Visibility Toolkit ===
Contributors: advancedintegratedmarketing
Tags: schema, structured data, json-ld, llms.txt, ai search, geo, aeo, local business
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Implements the AIM AI Visibility Checklist for sentrimax.com. Every change is reversible from one screen.

== Description ==

This plugin was built for one website, from one document: the AIM AI Visibility
Checklist for sentrimax.com dated 2026-09-12 (rubric v3.1.0). Each screen in the
plugin corresponds to a numbered task in that checklist, states the points it
recovers, and explains what it does before you switch it on.

What it does:

* **Task 1.1** — finds and strips foreign outbound links from the news page, and
  tells you whether they are in your content, in comments, or being injected.
* **Task 1.2, 1.3, 1.5, 1.6** — completes the Organization schema node with
  telephone, email, postal address, description, foundingDate and sameAs.
* **Task 1.4** — adds a LocalBusiness node for each plant, linked to the parent
  Organization by @id.
* **Task 1.7, 2.5** — guarantees exactly one H1 per page and closes heading-level
  gaps, without editing a theme template.
* **Task 1.8** — regenerates llms.txt as clean UTF-8 with no links to withdrawn
  pages.
* **Task 1.9** — redirects the duplicate equipment URL instead of deleting it.
* **Task 2.1** — testimonials with Review markup and an aggregateRating that is
  only published once real, attributed, visible reviews substantiate it.
* **Task 2.2, 2.3** — deep Person nodes and author attribution on news content.
* **Task 3.1, 3.3, 3.4** — case studies with Article schema, pricing bands with
  Offer markup, and a press and recognition page.

What it deliberately will not do: invent a rating, publish a price it made up,
rewrite your city URLs, or delete a page.

== Undo ==

Three levels, all on one screen:

1. **Turn everything off.** One button. All front-end changes stop immediately
   and the site renders exactly as it did before. Settings are kept.
2. **Restore points.** Every save and every database change saves one first.
   Restoring puts back settings, page content, deleted comments and files on
   disk. Restoring is itself undoable.
3. **Deactivate or delete.** Deactivating stops all output and keeps your data.

There is also a Preview mode that shows the changes to logged-in administrators
only, so you can check the site before the public and AI crawlers see anything.

== Installation ==

1. In WordPress, go to Plugins → Add New → Upload Plugin.
2. Choose the ZIP file and click Install Now, then Activate.
3. Go to AI Visibility in the left-hand menu and follow the Start here panel.

Nothing on your website changes until you switch output on.

== Frequently Asked Questions ==

= Will this conflict with my SEO plugin's schema? =

No. By default it merges the missing fields into the Organization node your site
already publishes rather than adding a second one, and it never overwrites a
value your site already sets. Run the Health Check after switching output on: if
Organization appears twice in the types column, set the Organization screen to
"Merge only".

= Something looks wrong on the site. What do I do? =

Use "Turn everything off now" at the top of any plugin screen. The site reverts
instantly and nothing is lost.

== Changelog ==

= 1.0.0 =
* First release. Implements the AIM AI Visibility Checklist for sentrimax.com,
  rubric v3.1.0.
