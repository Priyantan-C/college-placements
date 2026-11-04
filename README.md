# College Placements

WordPress plugin that draws placement charts and year-wise student placement tables for a college website. Staff upload one Excel file per year; the plugin does the counting. It ships with no data, so any college can use it.

## Getting started on a new site

1. Install and activate the plugin.
2. **Placements → Settings**: set the college name (chart titles use it, e.g. "Placements in {college} over the years") and the department order (e.g. `CSE, AIML, EE, ECE, IT`).
3. Load data in any of these ways:
   * **Upload student list**: one year's list of offers (see below).
   * **Import summary workbook**: past years with company totals only, one sheet per year.
   * **Import from page**: student tables already typed into a WordPress page.
4. Add the shortcodes to your pages.

## Yearly routine for staff

1. **Placements → Upload student list**: pick the year and upload the Excel file. It needs a header row with at least `Name` and `Org` (or Organization / Company). `Sl No`, `Dept` and `U R N` (or University Roll Number) are used when present. A title row above the header is fine.
2. Check the preview. Change the "Counts as company" box to merge spellings (e.g. `CTS` → `Cognizant`). Matches are remembered for later uploads.
3. Save. Tick **Ongoing** while the year's list is still growing: the site shows an Ongoing badge with the date of the latest upload. On the year's page, fill in students appeared, highest and average package. In the company list, drag rows (or use the arrows) to set the order, and untick companies to group them into **Others**, which always comes last.

The charts and tables update immediately.

## Shortcodes

| Shortcode | What it shows |
|---|---|
| `[placement_trend]` | Placements over the years: offers stacked by company (top 8 recruiters + the rest), with Total offers and Students appeared lines |
| `[placement_year]` | Year buttons, stat tiles and a donut chart of offers by company with a list of shares (`year="2026"` to start on a given year) |
| `[placement_records]` | Year-wise student tables with search and department filters (`years="2026,2025"`, `open="none"` (default), `"first"` or `"all"`) |

`[placement_trend]` and `[placement_year]` also accept `title="…"`. Both charts have a **Download** button (PNG for reports; hidden on phones). The year view always shows a summary table beside the donut.

## Summary workbook format

One sheet per year, named after the year. Header row: `companyName`, `placements`, `Total_Students_Appeared`, `HighestPkg`, `AvgPkg`. Only the company and placements columns are required, and common variants such as "Company" or "Offers" also work.

## Data

* Each year has a **Charts use** setting: *Summary numbers* (from a workbook or typed in) or *Calculated from student list*. New years default to calculated.
* Data lives in four tables: `wp_cpl_years`, `wp_cpl_summary`, `wp_cpl_records` and `wp_cpl_aliases`. Deactivating or deleting the plugin keeps them.
* Who can manage the data: Editors and Administrators (Years, Upload, Company names, Settings). **Import summary workbook** and **Import from page** are for Administrators only. Change either with the `cpl_capability` and `cpl_admin_capability` filters.

## Releasing an update

Sites check this repo's `main` branch through [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker). An update is offered only when the version goes up:

```bash
bin/bump-version.sh          # 1.0.0 -> 1.0.1 (or: minor, major, 1.2.3)
git commit -am "Release 1.0.1" && git push
```

WordPress checks about every 12 hours. The **Check for updates** link under the plugin forces a check.

## Bundled libraries

* [Chart.js](https://www.chartjs.org) 4.4.1 (MIT)
* [SimpleXLSX](https://github.com/shuchkin/simplexlsx) (MIT)
* [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) 5.6 (MIT)
* Two [Font Awesome Free](https://fontawesome.com) icons (CC BY 4.0)
