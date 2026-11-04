# STCET Placements

WordPress plugin that draws the placement charts and the year-wise student placement tables on the STCET website. It replaces the old Netlify app and the hand-made tables.

## How staff use it

1. **Placements → Upload student list**: pick the year and upload the Excel file (columns `Sl No`, `Dept`, `Name`, `U R N`, `Org`; a title row above the header is fine).
2. Check the preview. Change the "Counts as company" box to merge spellings (e.g. `CTS` → `Cognizant`). Matches are remembered.
3. Save. On the year's settings page, fill in students appeared, highest and average package, and tick the companies shown separately. The rest go into **Others**.

The charts and tables update immediately.

## Shortcodes

| Shortcode | What it shows |
|---|---|
| `[stcet_placement_trend]` | Placements over the years: stacked bars per company, Total Placements and Total Students Appeared lines |
| `[stcet_placement_year]` | Year picker, pie chart, totals and company table (`year="2026"` to start on a year) |
| `[stcet_placement_records]` | Year-wise student tables (`years="2026,2025"`, `open="first"`, `"all"` or `"none"`) |

## Data

* 2010–2026 company figures come from the vendor's v5 workbook (`data/summary-seed.json`), loaded on first activation.
* Each year has a **Charts use** setting: *Summary numbers* (typed in or from v5) or *Calculated from student list*. New years default to calculated.
* **Placements → Import from page** reads student tables already typed into a page (one-time move of the old Placement Data tables).
* Data lives in four tables: `wp_stcet_pl_years`, `wp_stcet_pl_summary`, `wp_stcet_pl_records`, `wp_stcet_pl_aliases`. Deactivating or deleting the plugin does not remove them.

## Releasing an update

Sites check this repo's `main` branch (via [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)). An update is offered only when the version goes up:

```bash
bin/bump-version.sh          # 1.0.0 -> 1.0.1 (or: minor, major, 1.2.3)
git commit -am "Release 1.0.1" && git push
```

WordPress checks about every 12 hours; **Plugins → Check for updates** under the plugin forces a check.

## Bundled libraries

* [Chart.js](https://www.chartjs.org) 4.4.1 (MIT)
* [SimpleXLSX](https://github.com/shuchkin/simplexlsx) (MIT)
* [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) 5.6 (MIT)
* Two [Font Awesome Free](https://fontawesome.com) icons (CC BY 4.0)
