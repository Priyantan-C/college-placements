=== College Placements ===
Contributors: priyantanc
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Placement charts and year-wise student placement tables for a college website, built from an uploaded Excel file.

== Description ==

Admin staff upload the year's student placement Excel file. The plugin counts offers per company, merges different spellings of the same company, and draws the placement charts and the student tables. Past years with only company totals can be loaded from a summary workbook.

Shortcodes:

* `[placement_trend]` – placements over the years.
* `[placement_year]` – year picker, pie chart, totals and company table.
* `[placement_records]` – year-wise student tables (`years="2026,2025"`, `open="first|all|none"`).

== Changelog ==

= 1.1.0 =
* Set the order companies appear in the charts and company table: drag rows, use the arrow buttons, or sort by offers / A–Z. Works for calculated and summary years.
* Ordering and the Others choice now live in one list on the year page. Companies that appear in a later upload get their own row until someone unticks them.

= 1.0.0 =
* First release: student-list upload with preview, company-name matching, per-year figures and "Others" grouping, summary workbook import, import of tables from an existing page, charts and student tables, settings for college name and headings.
