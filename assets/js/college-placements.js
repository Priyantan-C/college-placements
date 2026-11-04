/* College Placements – front-end charts. */
(function () {
	'use strict';

	/* ---------- Palette ----------
	 * CATEGORICAL: fixed slot order, checked for colour-blind separation (adjacent ΔE ≥ 9) and normal-vision
	 * separation (ΔE ≥ 19). Aqua, yellow and pink are under 3:1 on white, so every chart has labels + a table view.
	 * GRADIENT: brand navy → blue → violet → pink → orange. Single-series bar charts spread their bars evenly along it
	 * (in the order shown), each bar with a soft tint-to-colour gradient. The colour is decoration that makes the charts
	 * lively in reports; every bar is still named on the axis and labelled with its value, so nothing depends on it. */
	var CATEGORICAL = ['#1e73be', '#ff5722', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948'];
	var GRADIENT = ['#004287', '#1e73be', '#6250d6', '#d9468f', '#ff5722'];
	var OTHER = '#aab1bb';
	var INK = { primary: '#1d2327', secondary: '#565d66', muted: '#80868e', grid: '#e7e9ec', axis: '#c9cdd2', surface: '#ffffff' };
	var REDUCED = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function key(s) {
		return String(s || '').toLowerCase().replace(/[^\p{L}\p{N}]+/gu, '');
	}
	function isOthers(name) {
		return key(name) === 'others';
	}
	function sum(rows) {
		return rows.reduce(function (t, r) { return t + r[1]; }, 0);
	}
	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			if (k === 'text') { node.textContent = attrs[k]; } else if (k === 'html') { node.innerHTML = attrs[k]; } else { node.setAttribute(k, attrs[k]); }
		});
		(children || []).forEach(function (c) { if (c) { node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); } });
		return node;
	}
	function fonts(root) {
		var body = getComputedStyle(root).fontFamily || 'system-ui, sans-serif';
		var probe = document.querySelector('h1, h2, .entry-title') || root;
		var head = getComputedStyle(probe).fontFamily || body;
		return { body: body, heading: head };
	}
	function truncate(s, n) {
		s = String(s);
		return s.length > n ? s.slice(0, n - 1) + '…' : s;
	}
	function slug(s) {
		return String(s).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
	}

	/* Colour at position t (0–1) along the brand spectrum. */
	function hexToRgb(h) {
		var n = parseInt(h.slice(1), 16);
		return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
	}
	function rgb(c) {
		return 'rgb(' + c.map(function (v) { return Math.round(v); }).join(',') + ')';
	}
	function spectrum(t) {
		t = Math.max(0, Math.min(1, t));
		var seg = t * (GRADIENT.length - 1);
		var i = Math.min(Math.floor(seg), GRADIENT.length - 2);
		var a = hexToRgb(GRADIENT[i]);
		var b = hexToRgb(GRADIENT[i + 1]);
		var f = seg - i;
		return [a[0] + (b[0] - a[0]) * f, a[1] + (b[1] - a[1]) * f, a[2] + (b[2] - a[2]) * f];
	}
	function lighten(c, amt) {
		return c.map(function (v) { return v + (255 - v) * amt; });
	}
	/* Colours for n bars spread evenly across the spectrum. */
	function spread(n) {
		var out = [];
		for (var i = 0; i < n; i++) { out.push(spectrum(n === 1 ? 0.3 : i / (n - 1))); }
		return out;
	}
	/* A soft gradient along one bar: a lighter tint at its base, the full colour at its end. */
	function barGradient(ctx, color) {
		// Use the bar's final size (from the value scale), not its animated size, which starts at zero.
		var chart = ctx.chart;
		var horizontal = chart.options.indexAxis === 'y';
		var scale = chart.scales[horizontal ? 'x' : 'y'];
		var value = ctx.raw;
		if (!scale || !chart.chartArea || !value) { return rgb(color); }
		var p0 = scale.getPixelForValue(0);
		var p1 = scale.getPixelForValue(value);
		if (!isFinite(p0) || !isFinite(p1) || p0 === p1) { return rgb(color); }
		var g = horizontal
			? chart.ctx.createLinearGradient(p0, 0, p1, 0)
			: chart.ctx.createLinearGradient(0, p0, 0, p1);
		g.addColorStop(0, rgb(lighten(color, 0.35)));
		g.addColorStop(1, rgb(color));
		return g;
	}

	/* White plot background so screenshots and downloads are never transparent. */
	var surfacePlugin = {
		id: 'cplSurface',
		beforeDraw: function (chart) {
			var ctx = chart.ctx;
			ctx.save();
			ctx.globalCompositeOperation = 'destination-over';
			ctx.fillStyle = INK.surface;
			ctx.fillRect(0, 0, chart.width, chart.height);
			ctx.restore();
		}
	};

	/* Value labels at the end of bars. opts.only(index) limits which bars get one. */
	var valueLabelPlugin = {
		id: 'cplValues',
		afterDatasetsDraw: function (chart, args, opts) {
			if (!opts || !opts.enabled) { return; }
			var ctx = chart.ctx;
			var horizontal = chart.options.indexAxis === 'y';
			chart.data.datasets.forEach(function (ds, di) {
				if (ds.type === 'line' || ds.cplNoLabels) { return; }
				var meta = chart.getDatasetMeta(di);
				if (meta.hidden) { return; }
				meta.data.forEach(function (bar, i) {
					if (opts.only && !opts.only(i)) { return; }
					var v = ds.data[i];
					if (!v) { return; }
					ctx.save();
					ctx.font = '600 13px ' + opts.font;
					ctx.fillStyle = INK.primary;
					if (horizontal) {
						ctx.textAlign = 'left';
						ctx.textBaseline = 'middle';
						ctx.fillText(String(v), bar.x + 8, bar.y);
					} else {
						ctx.textAlign = 'center';
						ctx.textBaseline = 'bottom';
						ctx.fillText(String(v), bar.x, bar.y - 6);
					}
					ctx.restore();
				});
			});
		}
	};

	function baseOptions(f, title, subtitle) {
		return {
			responsive: true,
			maintainAspectRatio: false,
			animation: REDUCED ? false : { duration: 700, easing: 'easeOutQuart' },
			layout: { padding: { top: 4, right: 12, bottom: 4, left: 4 } },
			plugins: {
				title: { display: true, text: title, align: 'start', color: INK.primary, font: { family: f.heading, size: 18, weight: '600' }, padding: { top: 6, bottom: 2 } },
				subtitle: { display: !!subtitle, text: subtitle, align: 'start', color: INK.secondary, font: { family: f.body, size: 13 }, padding: { bottom: 14 } },
				legend: { display: false },
				tooltip: {
					backgroundColor: '#1d2327', titleColor: '#fff', bodyColor: '#fff', padding: 10, cornerRadius: 6, boxPadding: 4,
					titleFont: { family: f.body, weight: '600', size: 13 }, bodyFont: { family: f.body, size: 13 }
				},
				cplValues: { enabled: false, font: f.body }
			}
		};
	}

	function axisStyle(f) {
		return {
			grid: { color: INK.grid, drawTicks: false },
			border: { color: INK.axis },
			ticks: { color: INK.muted, font: { family: f.body, size: 12 }, padding: 8 }
		};
	}

	/* ---------- Shared bits: download, table view, lazy render ---------- */

	function downloadChart(chart, filename) {
		var prev = chart.options.devicePixelRatio;
		chart.options.devicePixelRatio = Math.max(2, window.devicePixelRatio || 1);
		chart.resize();
		chart.update('none');
		var url = chart.toBase64Image('image/png', 1);
		chart.options.devicePixelRatio = prev;
		chart.resize();
		chart.update('none');
		var a = el('a', { href: url, download: filename + '.png' });
		document.body.appendChild(a);
		a.click();
		a.remove();
	}

	function actionButton(label, icon) {
		var b = el('button', { type: 'button', 'class': 'cpl-action' });
		b.innerHTML = icon + '<span>' + label + '</span>';
		return b;
	}
	var ICON_DOWNLOAD = '<svg viewBox="0 0 20 20" aria-hidden="true"><path fill="currentColor" d="M9.25 3h1.5v8.19l2.97-2.97 1.06 1.06L10 14.06 5.22 9.28l1.06-1.06 2.97 2.97V3ZM4 15.5h12V17H4z"/></svg>';
	var ICON_TABLE = '<svg viewBox="0 0 20 20" aria-hidden="true"><path fill="currentColor" d="M3 4h14v12H3V4Zm1.5 1.5v2.25h11V5.5h-11Zm0 3.75v2.25h4.75V9.25H4.5Zm6.25 0v2.25h4.75V9.25h-4.75ZM4.5 13v1.5h4.75V13H4.5Zm6.25 0v1.5h4.75V13h-4.75Z"/></svg>';

	function tableToggle(button, wrap) {
		button.setAttribute('aria-expanded', 'false');
		button.addEventListener('click', function () {
			var open = wrap.hidden;
			wrap.hidden = !open;
			button.setAttribute('aria-expanded', open ? 'true' : 'false');
			button.querySelector('span').textContent = open ? 'Hide table' : 'Table';
		});
	}

	function whenVisible(node, fn) {
		if (!('IntersectionObserver' in window)) { fn(); return; }
		var io = new IntersectionObserver(function (entries) {
			if (entries.some(function (e) { return e.isIntersecting; })) { io.disconnect(); fn(); }
		}, { rootMargin: '120px' });
		io.observe(node);
	}

	/* ---------- [placement_year] ---------- */

	function renderYear(root, data) {
		var years = data.years.slice().sort(function (a, b) { return b.year - a.year; });
		if (!years.length) { return; }
		var byYear = {};
		data.years.forEach(function (y) { byYear[y.year] = y; });
		var wanted = parseInt(root.getAttribute('data-year'), 10);
		var current = byYear[wanted] || years[0];
		var f = fonts(root);
		var titleBase = root.getAttribute('data-title') || 'Placement Data Year wise';

		/* Toolbar: year pills (recent six) + "Earlier" select, then actions. */
		var pills = el('div', { 'class': 'cpl-pills', role: 'group', 'aria-label': 'Choose a year' });
		var recent = years.slice(0, 6);
		var older = years.slice(6);
		var pillButtons = recent.map(function (y) {
			var b = el('button', { type: 'button', 'class': 'cpl-pill', 'data-year': y.year, text: String(y.year) });
			pills.appendChild(b);
			return b;
		});
		var earlier = null;
		if (older.length) {
			earlier = el('select', { 'class': 'cpl-pill-select', 'aria-label': 'Earlier years' });
			earlier.appendChild(el('option', { value: '', text: 'Earlier' }));
			older.forEach(function (y) { earlier.appendChild(el('option', { value: y.year, text: String(y.year) })); });
			// The arrow is a separate icon, not a background image, so themes that style <select> can't tile or double it.
			var earlierWrap = el('span', { 'class': 'cpl-pill cpl-pill-dropdown' }, [earlier]);
			earlierWrap.insertAdjacentHTML('beforeend', '<svg class="cpl-pill-caret" viewBox="0 0 20 20" aria-hidden="true"><path fill="currentColor" d="M5.5 7.5 10 12l4.5-4.5 1 1L10 14 4.5 8.5z"/></svg>');
			pills.appendChild(earlierWrap);
		}
		var tableBtn = actionButton('Table', ICON_TABLE);
		var dlBtn = actionButton('Download', ICON_DOWNLOAD);
		dlBtn.setAttribute('aria-label', 'Download chart as image');
		var toolbar = el('div', { 'class': 'cpl-toolbar' }, [pills, el('div', { 'class': 'cpl-actions' }, [tableBtn, dlBtn])]);

		var tiles = el('div', { 'class': 'cpl-tiles' });
		var canvasWrap = el('div', { 'class': 'cpl-canvas' });
		var canvas = el('canvas', { role: 'img' });
		canvasWrap.appendChild(canvas);
		var tableWrap = el('div', { 'class': 'cpl-data-table', hidden: '' });
		var foot = el('p', { 'class': 'cpl-footnote', hidden: '' });
		var card = el('div', { 'class': 'cpl-card' }, [canvasWrap, tableWrap, foot]);

		root.appendChild(toolbar);
		root.appendChild(tiles);
		root.appendChild(card);
		tableToggle(tableBtn, tableWrap);

		var chart = null;

		function tile(label, value, extra) {
			return el('div', { 'class': 'cpl-tile' }, [
				el('span', { 'class': 'cpl-tile-label', text: label }),
				el('span', { 'class': 'cpl-tile-value', text: value === '' || value == null ? '–' : String(value) }),
				extra || null
			]);
		}

		function show(y) {
			current = y;
			var rows = y.rows.filter(function (r) { return r[1] > 0; });
			var total = sum(rows);

			pillButtons.forEach(function (b) {
				var on = parseInt(b.getAttribute('data-year'), 10) === y.year;
				b.classList.toggle('is-active', on);
				b.setAttribute('aria-pressed', on ? 'true' : 'false');
			});
			if (earlier) {
				var isOld = older.some(function (o) { return o.year === y.year; });
				earlier.value = isOld ? String(y.year) : '';
				earlier.parentNode.classList.toggle('is-active', isOld);
			}

			/* Tiles */
			tiles.innerHTML = '';
			tiles.appendChild(tile('Total offers', total));
			tiles.appendChild(tile('Students eligible', y.appeared || ''));
			tiles.appendChild(tile('Highest package', y.highest && y.highest !== 'N/A' ? y.highest : ''));
			tiles.appendChild(tile('Average package', y.avg && y.avg !== 'N/A' ? y.avg : ''));
			if (y.recruiters) { tiles.appendChild(tile('Recruiters', y.recruiters)); }

			/* Chart */
			var subtitle = [data.college, total + ' offers' + (y.appeared ? ' · ' + y.appeared + ' students eligible' : '')].filter(Boolean).join(' · ');
			var title = titleBase + ' – ' + y.year;
			canvas.setAttribute('aria-label', title + '. ' + rows.map(function (r) { return r[0] + ' ' + r[1]; }).join(', '));
			canvasWrap.style.height = Math.max(300, rows.length * 34 + 110) + 'px';
			var companyRows = rows.filter(function (r) { return !isOthers(r[0]); }).length;
			var colours = spread(companyRows);
			var colourAt = function (i) {
				var r = rows[i];
				if (!r || isOthers(r[0])) { return null; }
				var n = 0;
				for (var j = 0; j < i; j++) { if (!isOthers(rows[j][0])) { n++; } }
				return colours[n];
			};

			var cfg = {
				type: 'bar',
				data: {
					labels: rows.map(function (r) { return r[0]; }),
					datasets: [{
						label: 'Offers',
						data: rows.map(function (r) { return r[1]; }),
						backgroundColor: function (ctx) {
							var c = colourAt(ctx.dataIndex);
							return c ? barGradient(ctx, c) : OTHER;
						},
						hoverBackgroundColor: function (ctx) {
							var c = colourAt(ctx.dataIndex);
							return c ? rgb(c) : '#949ba6';
						},
						borderRadius: { topRight: 4, bottomRight: 4 },
						borderSkipped: 'start',
						barPercentage: 0.78,
						categoryPercentage: 0.9,
						maxBarThickness: 24
					}]
				},
				options: (function () {
					var o = baseOptions(f, title, subtitle);
					o.indexAxis = 'y';
					o.plugins.cplValues.enabled = true;
					o.plugins.tooltip.callbacks = {
						title: function (items) { return rows[items[0].dataIndex][0]; },
						label: function (item) {
							var v = item.raw;
							return ' ' + v + (v === 1 ? ' offer' : ' offers') + ' (' + Math.round(v / total * 100) + '%)';
						}
					};
					var x = axisStyle(f);
					x.beginAtZero = true;
					x.grace = '10%';
					x.ticks.precision = 0;
					var yAxis = axisStyle(f);
					yAxis.grid = { display: false };
					yAxis.ticks.color = INK.primary;
					yAxis.ticks.font = { family: f.body, size: 13 };
					yAxis.ticks.callback = function (v) { return truncate(this.getLabelForValue(v), window.innerWidth < 600 ? 18 : 30); };
					o.scales = { x: x, y: yAxis };
					return o;
				}()),
				plugins: [surfacePlugin, valueLabelPlugin]
			};
			if (chart) { chart.destroy(); }
			chart = new window.Chart(canvas, cfg);

			/* Table view */
			tableWrap.innerHTML = '';
			var tb = el('tbody');
			rows.forEach(function (r, i) {
				tb.appendChild(el('tr', {}, [
					el('td', { 'class': 'cpl-num', text: String(i + 1) }),
					el('td', { text: r[0] }),
					el('td', { 'class': 'cpl-num', text: String(r[1]) }),
					el('td', { 'class': 'cpl-num', text: Math.round(r[1] / total * 100) + '%' })
				]));
			});
			tb.appendChild(el('tr', { 'class': 'cpl-total-row' }, [el('td'), el('td', { text: 'Total' }), el('td', { 'class': 'cpl-num', text: String(total) }), el('td', { 'class': 'cpl-num', text: '100%' })]));
			tableWrap.appendChild(el('table', {}, [
				el('caption', { 'class': 'screen-reader-text', text: title }),
				el('thead', {}, [el('tr', {}, ['#', 'Company', 'Offers', 'Share'].map(function (h, i) { return el('th', { scope: 'col', 'class': i === 1 ? '' : 'cpl-num', text: h }); }))]),
				tb
			]));

			foot.hidden = !y.footnote;
			foot.textContent = y.footnote || '';
		}

		pillButtons.forEach(function (b) {
			b.addEventListener('click', function () { show(byYear[parseInt(b.getAttribute('data-year'), 10)]); });
		});
		if (earlier) {
			earlier.addEventListener('change', function () {
				if (earlier.value) { show(byYear[parseInt(earlier.value, 10)]); }
			});
		}
		dlBtn.addEventListener('click', function () {
			if (chart) { downloadChart(chart, 'placements-' + current.year); }
		});

		whenVisible(root, function () { show(current); });
	}

	/* ---------- [placement_trend] ---------- */

	function renderTrend(root, data) {
		var years = data.years;
		if (!years.length) { return; }
		var f = fonts(root);
		var title = root.getAttribute('data-title') || 'Placements over the years';
		var view = root.getAttribute('data-view') === 'companies' ? 'companies' : 'totals';
		var labels = years.map(function (y) { return String(y.year); });
		var totals = years.map(function (y) { return sum(y.rows); });
		var appeared = years.map(function (y) { return y.appeared || null; });
		var span = years[0].year + '–' + years[years.length - 1].year;
		var yearColours = spread(years.length);

		/* Top 8 companies across all years keep a fixed colour; the rest share grey. */
		var groups = {};
		years.forEach(function (y, yi) {
			y.rows.forEach(function (r) {
				var name = r[2] || r[0];
				var k = isOthers(name) ? '__other' : key(name);
				if (!groups[k]) { groups[k] = { name: name, total: 0, data: years.map(function () { return 0; }) }; }
				groups[k].total += r[1];
				groups[k].data[yi] += r[1];
				if (k !== '__other') { groups[k].name = name; }
			});
		});
		var ranked = Object.keys(groups).filter(function (k) { return k !== '__other'; })
			.sort(function (a, b) { return groups[b].total - groups[a].total; });
		var top = ranked.slice(0, CATEGORICAL.length);
		var otherData = years.map(function (y, yi) {
			var t = groups.__other ? groups.__other.data[yi] : 0;
			ranked.slice(CATEGORICAL.length).forEach(function (k) { t += groups[k].data[yi]; });
			return t;
		});

		var seg = el('div', { 'class': 'cpl-segmented', role: 'group', 'aria-label': 'Chart view' });
		var btnTotals = el('button', { type: 'button', 'class': 'cpl-seg', text: 'Total offers' });
		var btnCompanies = el('button', { type: 'button', 'class': 'cpl-seg', text: 'By company' });
		seg.appendChild(btnTotals);
		seg.appendChild(btnCompanies);
		var tableBtn = actionButton('Table', ICON_TABLE);
		var dlBtn = actionButton('Download', ICON_DOWNLOAD);
		dlBtn.setAttribute('aria-label', 'Download chart as image');
		var toolbar = el('div', { 'class': 'cpl-toolbar' }, [seg, el('div', { 'class': 'cpl-actions' }, [tableBtn, dlBtn])]);
		var canvasWrap = el('div', { 'class': 'cpl-canvas cpl-canvas-trend' });
		var canvas = el('canvas', { role: 'img' });
		canvasWrap.appendChild(canvas);
		var tableWrap = el('div', { 'class': 'cpl-data-table', hidden: '' });
		root.appendChild(toolbar);
		root.appendChild(el('div', { 'class': 'cpl-card' }, [canvasWrap, tableWrap]));
		tableToggle(tableBtn, tableWrap);

		var chart = null;
		var maxIndex = totals.indexOf(Math.max.apply(Math, totals));

		function legendOptions() {
			return {
				display: true, position: 'top', align: 'start',
				labels: { color: INK.secondary, font: { family: f.body, size: 12 }, boxWidth: 12, boxHeight: 12, useBorderRadius: true, borderRadius: 3, padding: 14 }
			};
		}

		function draw() {
			btnTotals.classList.toggle('is-active', view === 'totals');
			btnCompanies.classList.toggle('is-active', view === 'companies');
			btnTotals.setAttribute('aria-pressed', view === 'totals' ? 'true' : 'false');
			btnCompanies.setAttribute('aria-pressed', view === 'companies' ? 'true' : 'false');

			var o = baseOptions(f, title, view === 'totals'
				? span + ' · total offers and students appeared each year'
				: span + ' · offers by company (top ' + top.length + ' recruiters, rest grouped)');
			var x = axisStyle(f);
			x.grid = { display: false };
			x.stacked = view === 'companies';
			var yAxis = axisStyle(f);
			yAxis.beginAtZero = true;
			yAxis.grace = '8%';
			yAxis.ticks.precision = 0;
			yAxis.stacked = view === 'companies';
			yAxis.border = { display: false };
			o.scales = { x: x, y: yAxis };
			o.interaction = { mode: 'index', intersect: false };
			o.plugins.legend = legendOptions();

			var datasets;
			if (view === 'totals') {
				o.plugins.cplValues.enabled = true;
				o.plugins.cplValues.only = function (i) { return i === maxIndex || i === totals.length - 1; };
				o.plugins.legend.labels.generateLabels = function (c) {
					return c.data.datasets.map(function (ds, i) {
						return {
							text: ds.label, datasetIndex: i, hidden: !c.isDatasetVisible(i), lineWidth: 0,
							fillStyle: ds.type === 'line' ? '#2b2f36' : '#6250d6', strokeStyle: 'transparent',
							fontColor: INK.secondary, borderRadius: 3
						};
					});
				};
				datasets = [
					{
						type: 'line', label: 'Students appeared', data: appeared, order: 0,
						borderColor: '#2b2f36', backgroundColor: '#2b2f36', borderWidth: 2, tension: 0.3,
						pointRadius: 4, pointHoverRadius: 6, pointBorderColor: '#fff', pointBorderWidth: 2, spanGaps: true
					},
					{
						type: 'bar', label: 'Total offers', data: totals, order: 1,
						backgroundColor: function (ctx) { return barGradient(ctx, yearColours[ctx.dataIndex]); },
						hoverBackgroundColor: function (ctx) { return rgb(yearColours[ctx.dataIndex]); },
						borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'start',
						barPercentage: 0.72, categoryPercentage: 0.9, maxBarThickness: 40
					}
				];
			} else {
				datasets = top.map(function (k, i) {
					return {
						type: 'bar', label: groups[k].name, data: groups[k].data, backgroundColor: CATEGORICAL[i],
						borderColor: INK.surface, borderWidth: { top: 1, bottom: 1, left: 0, right: 0 }, borderSkipped: false,
						barPercentage: 0.72, categoryPercentage: 0.9, maxBarThickness: 40
					};
				});
				if (otherData.some(function (v) { return v > 0; })) {
					datasets.push({
						type: 'bar', label: 'Other companies', data: otherData, backgroundColor: OTHER,
						borderColor: INK.surface, borderWidth: { top: 1, bottom: 1, left: 0, right: 0 }, borderSkipped: false,
						barPercentage: 0.72, categoryPercentage: 0.9, maxBarThickness: 40
					});
				}
				o.plugins.tooltip.filter = function (item) { return item.raw > 0; };
				o.plugins.tooltip.itemSort = function (a, b) { return b.raw - a.raw; };
				o.plugins.tooltip.callbacks = {
					footer: function (items) {
						return 'Total: ' + totals[items[0].dataIndex];
					}
				};
			}

			canvas.setAttribute('aria-label', title + ', ' + span + '. ' + years.map(function (y, i) { return y.year + ': ' + totals[i] + ' offers'; }).join(', '));
			if (chart) { chart.destroy(); }
			chart = new window.Chart(canvas, {
				type: 'bar',
				data: { labels: labels, datasets: datasets },
				options: o,
				plugins: [surfacePlugin, valueLabelPlugin]
			});
			buildTable();
		}

		function buildTable() {
			tableWrap.innerHTML = '';
			var head, body = el('tbody');
			if (view === 'totals') {
				head = ['Year', 'Total offers', 'Students appeared'];
				years.forEach(function (y, i) {
					body.appendChild(el('tr', {}, [el('td', { text: String(y.year) }), el('td', { 'class': 'cpl-num', text: String(totals[i]) }), el('td', { 'class': 'cpl-num', text: y.appeared ? String(y.appeared) : '–' })]));
				});
			} else {
				head = ['Year'].concat(top.map(function (k) { return groups[k].name; }), ['Other companies', 'Total']);
				years.forEach(function (y, i) {
					var cells = [el('td', { text: String(y.year) })];
					top.forEach(function (k) { cells.push(el('td', { 'class': 'cpl-num', text: String(groups[k].data[i] || '–') })); });
					cells.push(el('td', { 'class': 'cpl-num', text: String(otherData[i] || '–') }));
					cells.push(el('td', { 'class': 'cpl-num', text: String(totals[i]) }));
					body.appendChild(el('tr', {}, cells));
				});
			}
			tableWrap.appendChild(el('table', {}, [
				el('caption', { 'class': 'screen-reader-text', text: title }),
				el('thead', {}, [el('tr', {}, head.map(function (h, i) { return el('th', { scope: 'col', 'class': i ? 'cpl-num' : '', text: h }); }))]),
				body
			]));
		}

		btnTotals.addEventListener('click', function () { if (view !== 'totals') { view = 'totals'; draw(); } });
		btnCompanies.addEventListener('click', function () { if (view !== 'companies') { view = 'companies'; draw(); } });
		dlBtn.addEventListener('click', function () { if (chart) { downloadChart(chart, slug(title) + (view === 'companies' ? '-by-company' : '')); } });

		whenVisible(root, draw);
	}

	function boot() {
		var data = window.CPL_DATA;
		if (!data || !window.Chart) { return; }
		document.querySelectorAll('.cpl-trend').forEach(function (root) { renderTrend(root, data); });
		document.querySelectorAll('.cpl-year').forEach(function (root) { renderYear(root, data); });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
