=== College Placements ===
Contributors: priyantanc
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.4.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Placement charts and year-wise student placement tables for a college website, built from an uploaded Excel file.

== Description ==

Admin staff upload the year's student placement Excel file. The plugin counts offers per company, merges different spellings of the same company, and draws the placement charts and the student tables. Past years with only company totals can be loaded from a summary workbook.

Shortcodes:

* `[placement_trend]` – placements over the years.
* `[placement_year]` – year buttons, stat tiles and a donut chart of offers by company.
* `[placement_records]` – year-wise student tables (`years="2026,2025"`, `open="none|first|all", default none`).

== Changelog ==

= 1.4.0 =
* Placement Data Year Wise is now a colourful donut chart: 12 vivid colours with soft gradients, the total in the centre, and a list of companies with offers and share. Hovering a slice or a list row highlights both.
* Downloads of the donut include the title and the company list, with larger text for reports.
* Fix: the arrow next to the year buttons is centred on phones; the Ongoing badge stays on one line.

= 1.3.2 =
* Student tables now all start closed. Use open="first" or open="all" on the shortcode to change that.

= 1.3.1 =
* Chart titles are now page headings: they wrap on phones and use the theme's heading font. Downloads still include the title, drawn at desktop size even when saved from a phone.
* Phones: Table and Download buttons are hidden; year buttons scroll in one row with a fade and arrow showing there are more; the selected year scrolls into view.
* Trend chart on phones: taller, compact legend, level year labels.

= 1.3.0 =
* Trend chart is now one combined chart: offers stacked by company with a Total offers line and a Students appeared line (the view toggle is gone).
* New Ongoing switch per year (year settings and upload screen). It shows a prominent Ongoing badge with the date the student table was last updated, on the student table and the year chart (including downloads). The date updates by itself on every new upload.
* Existing "On-going Data, Updated till …" notes are converted to the switch, keeping their date.
* New Department order setting: department filter buttons and the rows of each student table follow it.

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
