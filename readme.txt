=== College Placements ===
Contributors: priyantanc
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.2.2
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

= 1.2.2 =
* Removed the change-vs-last-year note from the Total offers tile.

= 1.2.1 =
* Fix: the "Earlier" years dropdown showed repeated arrows when an earlier year was chosen, or when the theme styles dropdowns.

= 1.2.0 =
* New look: year pills, stat tiles (total offers, students eligible, packages, recruiters), colourful ranked bars with soft gradients.
* Trend chart: colourful total-offers bars with the students-appeared line, plus a "By company" view of the top 8 recruiters.
* Every chart: Download as PNG (white background, high resolution) and a Table view.
* Student tables: search (name, roll number or company, including merged names), department filters, offer counts per year, card layout on phones.
* Company names page now also lists spellings from summary numbers, so they can be merged too.

= 1.1.0 =
* Set the order companies appear in the charts and company table: drag rows, use the arrow buttons, or sort by offers / A–Z. Works for calculated and summary years.
* Ordering and the Others choice now live in one list on the year page. Companies that appear in a later upload get their own row until someone unticks them.

= 1.0.0 =
* First release: student-list upload with preview, company-name matching, per-year figures and "Others" grouping, summary workbook import, import of tables from an existing page, charts and student tables, settings for college name and headings.
