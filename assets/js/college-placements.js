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
	/* DONUT: 12 vivid hues, ordered so neighbouring slices always contrast (checked: normal-vision ΔE ≥ 27 between
	   neighbours; one neighbouring pair sits in the colour-blind warning band, covered by the white gaps between
	   slices and the labelled list). Past 12 companies the hues repeat in a lighter tint. */
	var DONUT = ['#1e73be', '#ff5722', '#1baf7a', '#8e44ec', '#eda100', '#e83e8c', '#0097a7', '#e34948', '#7cb342', '#3949ab', '#fb8c00', '#ad1457'];
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

	/* Value labels above chosen points of the first line (Total offers): its highest year and the latest year. */
	function lineLabelPlugin(maxIndex, lastIndex, f) {
		return {
			id: 'cplLineValues',
			afterDatasetsDraw: function (chart) {
				var meta = chart.getDatasetMeta(0);
				if (!meta || meta.hidden) { return; }
				var ctx = chart.ctx;
				[maxIndex, lastIndex].filter(function (v, i, a) { return a.indexOf(v) === i; }).forEach(function (i) {
					var pt = meta.data[i];
					var v = chart.data.datasets[0].data[i];
					if (!pt || v == null) { return; }
					ctx.save();
					ctx.font = '700 13px ' + f.body;
					ctx.textAlign = 'center';
					ctx.textBaseline = 'bottom';
					ctx.lineWidth = 4;
					ctx.strokeStyle = '#fff';
					ctx.strokeText(String(v), pt.x, pt.y - 9);
					ctx.fillStyle = INK.primary;
					ctx.fillText(String(v), pt.x, pt.y - 9);
					ctx.restore();
				});
			}
		};
	}

	/* On the page the title and subtitle are HTML headings (they wrap and use the theme's heading font).
	   Downloads draw them inside the image instead, at desktop size, so a PNG saved on a phone looks right. */
	function baseOptions(f, title, subtitle, forExport) {
		return {
			responsive: !forExport,
			maintainAspectRatio: false,
			devicePixelRatio: forExport ? 2 : undefined,
			animation: forExport || REDUCED ? false : { duration: 700, easing: 'easeOutQuart' },
			layout: { padding: forExport ? { top: 12, right: 24, bottom: 12, left: 16 } : { top: 4, right: 12, bottom: 4, left: 4 } },
			plugins: {
				title: { display: !!forExport, text: title, align: 'start', color: INK.primary, font: { family: f.heading, size: forExport ? 28 : 18, weight: '600' }, padding: { top: 6, bottom: forExport ? 6 : 2 } },
				subtitle: { display: !!forExport && !!subtitle, text: subtitle, align: 'start', color: INK.secondary, font: { family: f.body, size: forExport ? 17 : 13 }, padding: { bottom: forExport ? 24 : 14 } },
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

	/* Render a fresh copy of the chart off-screen at a fixed desktop size and save it as a PNG. */
	function exportChart(buildConfig, width, height, filename) {
		var holder = el('div', { style: 'position:fixed;left:-10000px;top:0;width:' + width + 'px;height:' + height + 'px;' });
		var c = el('canvas', { width: String(width), height: String(height) });
		holder.appendChild(c);
		document.body.appendChild(holder);
		var copy = new window.Chart(c, buildConfig(true));
		var url = copy.toBase64Image('image/png', 1);
		copy.destroy();
		holder.remove();
		var a = el('a', { href: url, download: filename + '.png' });
		document.body.appendChild(a);
		a.click();
		a.remove();
	}

	/* Heading shown above a chart on the page. */
	function chartHead() {
		var h = el('h3', { 'class': 'cpl-chart-title' });
		var sub = el('p', { 'class': 'cpl-chart-sub' });
		return { node: el('div', { 'class': 'cpl-chart-head' }, [h, sub]), title: h, sub: sub };
	}

	/* Year buttons: a single scrollable row on small screens, with a fade + arrow while more years are hidden. */
	function scrollHint(scroller, wrap) {
		var more = el('button', { type: 'button', 'class': 'cpl-more', 'aria-label': 'Show more years', tabindex: '-1' });
		more.innerHTML = '<svg viewBox="0 0 20 20" aria-hidden="true"><path fill="currentColor" d="M7.5 5.5 12 10l-4.5 4.5 1 1L14 10 8.5 4.5z"/></svg>';
		wrap.appendChild(more);
		function update() {
			var max = scroller.scrollWidth - scroller.clientWidth;
			wrap.classList.toggle('has-more-right', max > 4 && scroller.scrollLeft < max - 4);
			wrap.classList.toggle('has-more-left', scroller.scrollLeft > 4);
		}
		more.addEventListener('click', function () { scroller.scrollBy({ left: scroller.clientWidth * 0.7, behavior: REDUCED ? 'auto' : 'smooth' }); });
		scroller.addEventListener('scroll', update, { passive: true });
		window.addEventListener('resize', update);
		update();
		return update;
	}

	function actionButton(label, icon) {
		var b = el('button', { type: 'button', 'class': 'cpl-action' });
		b.innerHTML = icon + '<span>' + label + '</span>';
		return b;
	}
	var ICON_DOWNLOAD = '<svg viewBox="0 0 20 20" aria-hidden="true"><path fill="currentColor" d="M9.25 3h1.5v8.19l2.97-2.97 1.06 1.06L10 14.06 5.22 9.28l1.06-1.06 2.97 2.97V3ZM4 15.5h12V17H4z"/></svg>';

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
		var dlBtn = actionButton('Download', ICON_DOWNLOAD);
		dlBtn.setAttribute('aria-label', 'Download chart as image');
		var pillsWrap = el('div', { 'class': 'cpl-pills-wrap' }, [pills]);
		var toolbar = el('div', { 'class': 'cpl-toolbar' }, [pillsWrap, el('div', { 'class': 'cpl-actions' }, [dlBtn])]);

		var status = el('div', { 'class': 'cpl-year-status', hidden: '' });
		var tiles = el('div', { 'class': 'cpl-tiles' });
		var canvasWrap = el('div', { 'class': 'cpl-donut-canvas' });
		var canvas = el('canvas', { role: 'img' });
		canvasWrap.appendChild(canvas);
		var list = el('tbody');
		var summary = el('table', { 'class': 'cpl-summary-table' });
		var summaryWrap = el('div', { 'class': 'cpl-summary-wrap' }, [summary]);
		var donut = el('div', { 'class': 'cpl-donut' }, [canvasWrap, summaryWrap]);
		var foot = el('p', { 'class': 'cpl-footnote', hidden: '' });
		var head = chartHead();
		var card = el('div', { 'class': 'cpl-card' }, [head.node, donut, foot]);

		root.appendChild(toolbar);
		root.appendChild(status);
		root.appendChild(tiles);
		root.appendChild(card);
		var updateHint = scrollHint(pills, pillsWrap);

		var chart = null;
		var buildConfig = null;

		function highlight(i) {
			Array.prototype.forEach.call(list.children, function (tr, k) { tr.classList.toggle('is-active', k === i); });
		}
		function activate(i) {
			if (!chart) { return; }
			var els = i < 0 ? [] : [{ datasetIndex: 0, index: i }];
			chart.setActiveElements(els);
			var arc = i < 0 ? null : chart.getDatasetMeta(0).data[i];
			chart.tooltip.setActiveElements(els, arc ? arc.tooltipPosition() : { x: 0, y: 0 });
			chart.update();
			highlight(i);
		}

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
			var activePill = pills.querySelector('.is-active');
			if (activePill && pills.scrollWidth > pills.clientWidth) {
				pills.scrollTo({ left: activePill.offsetLeft - pills.clientWidth / 2 + activePill.offsetWidth / 2, behavior: REDUCED ? 'auto' : 'smooth' });
			}
			updateHint();

			/* Ongoing badge */
			status.innerHTML = '';
			status.hidden = !y.ongoing;
			if (y.ongoing) {
				var badge = el('span', { 'class': 'cpl-live' }, [el('span', { 'class': 'cpl-live-dot', 'aria-hidden': 'true' }), 'Ongoing']);
				if (y.updated) { badge.appendChild(el('span', { 'class': 'cpl-live-date', text: ' · Updated ' + y.updated })); }
				status.appendChild(badge);
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
			var pageSubtitle = subtitle;
			if (y.ongoing) { subtitle += ' · Ongoing' + (y.updated ? ', updated ' + y.updated : ''); }
			var title = titleBase + ' – ' + y.year;
			head.title.textContent = title;
			head.sub.textContent = pageSubtitle;
			canvas.setAttribute('aria-label', title + '. ' + rows.map(function (r) { return r[0] + ' ' + r[1]; }).join(', '));
			/* Colours: each company its own vivid hue in the order shown; Others grey, always last. */
			var fills = [];
			var n = 0;
			rows.forEach(function (r) {
				if (isOthers(r[0])) { fills.push(hexToRgb(OTHER)); return; }
				var base = hexToRgb(DONUT[n % DONUT.length]);
				fills.push(n >= DONUT.length ? lighten(base, 0.4) : base);
				n++;
			});
			var share = function (v) { return Math.round(v / total * 100) + '%'; };

			/* One conic gradient for the whole ring: each slice runs from a lighter tint to its full colour.
			   Angles come from the data, not the animated arcs, so the gradient is right from the first frame. */
			function ringFill(ctx) {
				var chart = ctx.chart;
				var area = chart.chartArea;
				var i = ctx.dataIndex;
				if (!area || typeof chart.ctx.createConicGradient !== 'function') { return rgb(fills[i]); }
				var sig = area.left + ',' + area.top + ',' + area.right + ',' + area.bottom;
				if (chart.$cplRing && chart.$cplRingSig === sig) { return chart.$cplRing; }
				var g = chart.ctx.createConicGradient(-Math.PI / 2, (area.left + area.right) / 2, (area.top + area.bottom) / 2);
				var acc = 0;
				rows.forEach(function (r, k) {
					var a = acc / total;
					acc += r[1];
					var b = acc / total;
					var grey = isOthers(r[0]);
					g.addColorStop(Math.min(1, a + 0.0005), rgb(grey ? fills[k] : lighten(fills[k], 0.28)));
					g.addColorStop(Math.max(0, b - 0.0005), rgb(fills[k]));
				});
				chart.$cplRing = g;
				chart.$cplRingSig = sig;
				return g;
			}

			/* Total in the middle of the ring. */
			var centrePlugin = {
				id: 'cplCentre',
				afterDatasetsDraw: function (chart) {
					var meta = chart.getDatasetMeta(0);
					var arc = meta && meta.data[0];
					if (!arc) { return; }
					var r = arc.innerRadius;
					var ctx = chart.ctx;
					ctx.save();
					ctx.textAlign = 'center';
					ctx.textBaseline = 'middle';
					ctx.fillStyle = INK.primary;
					ctx.font = '700 ' + Math.round(r * 0.52) + 'px ' + f.body;
					ctx.fillText(String(total), arc.x, arc.y - r * 0.12);
					ctx.fillStyle = INK.secondary;
					ctx.font = '600 ' + Math.max(11, Math.round(r * 0.15)) + 'px ' + f.body;
					ctx.fillText('offers in ' + y.year, arc.x, arc.y + r * 0.3);
					ctx.restore();
				}
			};

			buildConfig = function (forExport) {
				var o = baseOptions(f, title, subtitle, forExport);
				o.cutout = '62%';
				o.layout = { padding: forExport ? { top: 12, right: 32, bottom: 20, left: 32 } : 14 };
				o.plugins.tooltip.callbacks = {
					label: function (item) {
						var v = item.raw;
						return ' ' + v + (v === 1 ? ' offer' : ' offers') + ' (' + share(v) + ')';
					},
					labelColor: function (item) {
						var c = rgb(fills[item.dataIndex]);
						return { borderColor: c, backgroundColor: c, borderRadius: 3 };
					}
				};
				if (forExport) {
					// Downloads carry their own list of companies beside the ring.
					o.plugins.legend = {
						display: true, position: 'right', align: 'center',
						labels: {
							font: { family: f.body, size: 20, weight: '600' }, color: INK.primary, padding: 16, usePointStyle: true, pointStyle: 'circle', boxWidth: 16, boxHeight: 16,
							generateLabels: function () {
								return rows.map(function (r, i) {
									return { text: r[0] + '   ' + r[1] + '  (' + share(r[1]) + ')', fillStyle: rgb(fills[i]), strokeStyle: rgb(fills[i]), lineWidth: 0, index: i, pointStyle: 'circle' };
								});
							}
						}
					};
				} else {
					o.onHover = function (evt, els) { highlight(els.length ? els[0].index : -1); };
				}
				return {
					type: 'doughnut',
					data: {
						labels: rows.map(function (r) { return r[0]; }),
						datasets: [{
							data: rows.map(function (r) { return r[1]; }),
							backgroundColor: ringFill,
							hoverBackgroundColor: function (ctx) { return rgb(fills[ctx.dataIndex]); },
							borderColor: INK.surface,
							borderWidth: 3,
							borderRadius: 6,
							hoverOffset: forExport ? 0 : 12,
							spacing: 1
						}]
					},
					options: o,
					plugins: [surfacePlugin, centrePlugin]
				};
			};
			if (chart) { chart.destroy(); }
			chart = new window.Chart(canvas, buildConfig(false));

			/* Summary table beside the ring: rank, colour + company, offers, share (bar + %). Always visible.
			   Hovering or focusing a row lights up its slice. */
			var maxV = Math.max.apply(Math, rows.map(function (r) { return r[1]; }));
			summary.innerHTML = '';
			list = el('tbody');
			rows.forEach(function (r, i) {
				var c = fills[i];
				var dot = el('span', { 'class': 'cpl-dot', 'aria-hidden': 'true' });
				var fillCss = isOthers(r[0]) ? rgb(c) : 'linear-gradient(90deg, ' + rgb(lighten(c, 0.28)) + ', ' + rgb(c) + ')';
				dot.style.background = fillCss;
				var bar = el('span', { 'class': 'cpl-share-bar', 'aria-hidden': 'true' }, [el('span', { 'class': 'cpl-share-fill' })]);
				bar.firstChild.style.width = Math.max(2, Math.round(r[1] / maxV * 100)) + '%';
				bar.firstChild.style.background = fillCss;
				var tr = el('tr', { tabindex: '0' }, [
					el('td', { 'class': 'cpl-rank', text: isOthers(r[0]) ? '' : String(i + 1) }),
					el('th', { scope: 'row', 'class': 'cpl-company' }, [dot, el('span', { text: r[0] })]),
					el('td', { 'class': 'cpl-num cpl-offers', text: String(r[1]) }),
					el('td', { 'class': 'cpl-share' }, [bar, el('span', { 'class': 'cpl-share-pct', text: share(r[1]) })])
				]);
				tr.addEventListener('mouseenter', function () { activate(i); });
				tr.addEventListener('focus', function () { activate(i); });
				tr.addEventListener('mouseleave', function () { activate(-1); });
				tr.addEventListener('blur', function () { activate(-1); });
				list.appendChild(tr);
			});
			summary.appendChild(el('caption', { 'class': 'screen-reader-text', text: title }));
			summary.appendChild(el('thead', {}, [el('tr', {}, [
				el('th', { scope: 'col', 'class': 'cpl-rank', text: '#' }),
				el('th', { scope: 'col', text: 'Company' }),
				el('th', { scope: 'col', 'class': 'cpl-num', text: 'Offers' }),
				el('th', { scope: 'col', 'class': 'cpl-share', text: 'Share' })
			])]));
			summary.appendChild(list);
			summary.appendChild(el('tfoot', {}, [el('tr', {}, [
				el('td', { 'class': 'cpl-rank' }),
				el('th', { scope: 'row', 'class': 'cpl-company', text: 'Total' }),
				el('td', { 'class': 'cpl-num cpl-offers', text: String(total) }),
				el('td', { 'class': 'cpl-share' }, [el('span', { 'class': 'cpl-share-pct', text: '100%' })])
			])]));

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
			if (!buildConfig) { show(current); }
			if (buildConfig) {
				var n = chart ? chart.data.labels.length : 10;
				exportChart(buildConfig, 1200, Math.max(640, n * 34 + 220), 'placements-' + current.year);
			}
		});

		whenVisible(root, function () { show(current); });
	}

	/* ---------- [placement_trend] ---------- */

	function renderTrend(root, data) {
		var years = data.years;
		if (!years.length) { return; }
		var f = fonts(root);
		var title = root.getAttribute('data-title') || 'Placements over the years';
		var totals = years.map(function (y) { return sum(y.rows); });
		var appeared = years.map(function (y) { return y.appeared || null; });
		var span = years[0].year + '–' + years[years.length - 1].year;
		var TOP = DONUT.length; // companies with their own colour by default
		var LINE_TOTAL = '#1d2327';
		var LINE_APPEARED = '#565d66';

		/* Every company across all years (spellings merged), ranked by total offers. */
		var groups = {};
		years.forEach(function (y, yi) {
			y.rows.forEach(function (r) {
				var name = r[2] || r[0];
				var k = isOthers(name) ? '__other' : key(name);
				if (!groups[k]) { groups[k] = { key: k, name: name, total: 0, data: years.map(function () { return 0; }) }; }
				groups[k].total += r[1];
				groups[k].data[yi] += r[1];
				if (k !== '__other') { groups[k].name = name; }
			});
		});
		var ranked = Object.keys(groups).filter(function (k) { return k !== '__other'; })
			.sort(function (a, b) { return groups[b].total - groups[a].total || groups[a].name.localeCompare(groups[b].name); });
		ranked.forEach(function (k, i) { groups[k].colour = i < TOP ? DONUT[i] : '#1e73be'; });
		function restOf(keep) {
			return years.map(function (y, yi) {
				var t = groups.__other ? groups.__other.data[yi] : 0;
				ranked.forEach(function (k) { if (keep.indexOf(k) === -1) { t += groups[k].data[yi]; } });
				return t;
			});
		}

		var state = { focus: null, sideways: false };

		/* ---- DOM: toolbar, heading, canvas, company chips ---- */
		var dlBtn = actionButton('Download', ICON_DOWNLOAD);
		dlBtn.setAttribute('aria-label', 'Download chart as image');
		var toolbar = el('div', { 'class': 'cpl-toolbar cpl-toolbar-end cpl-toolbar-below' }, [el('div', { 'class': 'cpl-actions' }, [dlBtn])]);
		var canvasWrap = el('div', { 'class': 'cpl-canvas cpl-canvas-trend' });
		var canvas = el('canvas', { role: 'img' });
		canvasWrap.appendChild(canvas);
		var trendSub = span + ' · offers by company, with total offers and students appeared';
		var head = chartHead();
		head.title.textContent = title;
		head.sub.textContent = trendSub;

		var legendKey = el('div', { 'class': 'cpl-legend-key' });
		var chips = el('div', { 'class': 'cpl-company-chips', role: 'group', 'aria-label': 'Highlight a company' });
		var note = el('p', { 'class': 'cpl-focus-note', 'aria-live': 'polite' });
		var legend = el('div', { 'class': 'cpl-legend' }, [legendKey, chips, note]);
		// The numbers behind the chart, for screen readers.
		var tableWrap = el('div', { 'class': 'screen-reader-text' });
		root.appendChild(el('div', { 'class': 'cpl-card' }, [head.node, canvasWrap, legend, tableWrap]));
		root.appendChild(toolbar); // Download sits under the chart

		function chip(k) {
			var g = groups[k];
			var b = el('button', { type: 'button', 'class': 'cpl-cchip', 'data-key': k, 'aria-pressed': 'false' }, [
				el('span', { 'class': 'cpl-dot', 'aria-hidden': 'true' }),
				el('span', { 'class': 'cpl-cchip-name', text: g.name }),
				el('span', { 'class': 'cpl-cchip-count', text: String(g.total) })
			]);
			b.firstChild.style.background = g.colour;
			b.addEventListener('click', function () { setFocus(state.focus === k ? null : k); });
			return b;
		}
		var chipButtons = {};
		ranked.forEach(function (k, i) {
			var b = chip(k);
			if (i >= TOP) { b.classList.add('is-extra'); b.hidden = true; }
			chipButtons[k] = b;
			chips.appendChild(b);
		});
		var moreBtn = null;
		if (ranked.length > TOP) {
			moreBtn = el('button', { type: 'button', 'class': 'cpl-cchip cpl-cchip-more', 'aria-expanded': 'false', text: '+ ' + (ranked.length - TOP) + ' more companies' });
			moreBtn.addEventListener('click', function () {
				var open = moreBtn.getAttribute('aria-expanded') !== 'true';
				Array.prototype.forEach.call(chips.querySelectorAll('.is-extra'), function (b) { b.hidden = !open; });
				moreBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
				moreBtn.textContent = open ? 'Show fewer' : '+ ' + (ranked.length - TOP) + ' more companies';
				chips.appendChild(moreBtn);
			});
			chips.appendChild(moreBtn);
		}

		function setFocus(k) {
			state.focus = k;
			Object.keys(chipButtons).forEach(function (x) {
				var on = x === k;
				chipButtons[x].classList.toggle('is-active', on);
				chipButtons[x].setAttribute('aria-pressed', on ? 'true' : 'false');
			});
			chips.classList.toggle('has-focus', !!k);
			if (k) {
				var g = groups[k];
				var best = g.data.indexOf(Math.max.apply(Math, g.data));
				var yearsWith = g.data.filter(function (v) { return v > 0; }).length;
				note.innerHTML = '';
				note.appendChild(el('strong', { text: g.name }));
				note.appendChild(document.createTextNode(': ' + g.total + ' offers in ' + yearsWith + (yearsWith === 1 ? ' year' : ' years') + ' · best year ' + years[best].year + ' (' + g.data[best] + ') '));
				var clear = el('button', { type: 'button', 'class': 'cpl-linkbtn', text: 'Show all companies' });
				clear.addEventListener('click', function () { setFocus(null); });
				note.appendChild(clear);
			} else {
				note.textContent = 'Tap a company to highlight it.';
			}
			draw();
		}

		/* ---- Chart configuration (page: vertical on wide screens, sideways on phones; downloads: always vertical) ---- */
		function buildConfig(forExport) {
			var sideways = !forExport && state.sideways;
			var order = years.map(function (y, i) { return i; });
			if (sideways) { order.reverse(); } // latest year at the top
			var pick = function (arr) { return order.map(function (i) { return arr[i]; }); };
			var labels = pick(years.map(function (y) { return String(y.year); }));
			var focus = state.focus;

			var o = baseOptions(f, title, focus ? span + ' · ' + groups[focus].name + ' highlighted' : trendSub, forExport);
			if (sideways) { o.indexAxis = 'y'; }
			var cat = axisStyle(f);
			cat.grid = { display: false };
			cat.stacked = true;
			cat.ticks.maxRotation = 0;
			cat.ticks.autoSkipPadding = 6;
			if (sideways) { cat.ticks.autoSkip = false; cat.ticks.color = INK.primary; cat.ticks.font = { family: f.body, size: 13, weight: '600' }; }
			var val = axisStyle(f);
			val.beginAtZero = true;
			val.grace = sideways ? '14%' : '10%';
			val.ticks.precision = 0;
			val.stacked = true;
			val.border = { display: false };
			if (sideways) { val.position = 'top'; val.ticks.maxTicksLimit = 5; }
			o.scales = sideways ? { y: cat, x: val } : { x: cat, y: val };
			o.interaction = { mode: 'index', intersect: false, axis: sideways ? 'y' : 'x' };
			o.plugins.tooltip.filter = function (item) { return item.raw > 0; };
			o.plugins.tooltip.itemSort = function (a, b) {
				var al = a.dataset.type === 'line', bl = b.dataset.type === 'line';
				if (al !== bl) { return al ? -1 : 1; }
				return b.raw - a.raw;
			};
			o.plugins.tooltip.callbacks = {
				title: function (items) { var i = order[items[0].dataIndex]; return years[i].year + ' · ' + totals[i] + ' offers'; }
			};
			if (forExport) {
				o.plugins.legend = {
					display: true, position: 'top', align: 'start',
					labels: {
						color: INK.secondary, font: { family: f.body, size: 15 }, boxWidth: 14, boxHeight: 14, padding: 14, usePointStyle: true, pointStyleWidth: 16,
						generateLabels: function (c) {
							return c.data.datasets.map(function (ds, i) {
								var line = ds.type === 'line';
								return {
									text: ds.label, datasetIndex: i, hidden: false,
									fillStyle: line ? '#fff' : ds.backgroundColor, strokeStyle: line ? ds.borderColor : 'transparent',
									lineWidth: line ? 2 : 0, lineDash: ds.borderDash || [], pointStyle: line ? (ds.pointStyle || 'circle') : 'rectRounded',
									fontColor: INK.secondary
								};
							});
						}
					}
				};
			}

			var barStyle = {
				type: 'bar', stack: 'offers', borderColor: INK.surface,
				borderWidth: sideways ? { top: 0, bottom: 0, left: 1, right: 1 } : { top: 1, bottom: 1, left: 0, right: 0 },
				borderSkipped: false, barPercentage: sideways ? 0.78 : 0.72, categoryPercentage: 0.9, maxBarThickness: sideways ? 22 : 40, order: 2
			};
			var datasets = [];
			if (!sideways) {
				datasets.push({
					type: 'line', label: 'Total offers', data: pick(totals), stack: 'total', order: 0,
					borderColor: LINE_TOTAL, backgroundColor: LINE_TOTAL, borderWidth: 2.5, cubicInterpolationMode: 'monotone',
					pointRadius: 4, pointHoverRadius: 6, pointBorderColor: '#fff', pointBorderWidth: 2
				});
			}
			datasets.push({
				type: 'line', label: 'Students appeared', data: pick(appeared), stack: 'appeared', order: 1,
				borderColor: LINE_APPEARED, backgroundColor: LINE_APPEARED, borderWidth: 2, borderDash: [6, 4], cubicInterpolationMode: 'monotone',
				showLine: !sideways, indexAxis: sideways ? 'y' : 'x', pointStyle: 'rectRot', pointRadius: sideways ? 6 : 5, pointHoverRadius: 7, pointBorderColor: '#fff', pointBorderWidth: 2, spanGaps: true
			});
			var keep;
			if (focus) {
				keep = [focus];
				datasets.push(Object.assign({ label: groups[focus].name, data: pick(groups[focus].data), backgroundColor: groups[focus].colour, cplFocus: true }, barStyle));
				datasets.push(Object.assign({ label: 'All other companies', data: pick(restOf(keep)), backgroundColor: 'rgba(170, 177, 187, 0.45)' }, barStyle));
			} else {
				keep = ranked.slice(0, TOP);
				keep.forEach(function (k) {
					datasets.push(Object.assign({ label: groups[k].name, data: pick(groups[k].data), backgroundColor: groups[k].colour }, barStyle));
				});
				var rest = restOf(keep);
				if (rest.some(function (v) { return v > 0; })) {
					datasets.push(Object.assign({ label: 'Other companies', data: pick(rest), backgroundColor: OTHER }, barStyle));
				}
			}

			var maxIndex = totals.indexOf(Math.max.apply(Math, totals));
			var plugins = [surfacePlugin];
			if (sideways) {
				plugins.push(endTotalsPlugin(pick(totals), pick(appeared), f));
			} else {
				plugins.push(lineLabelPlugin(maxIndex, totals.length - 1, f));
			}
			if (focus) { plugins.push(focusValuesPlugin(f)); }
			return { type: 'bar', data: { labels: labels, datasets: datasets }, options: o, plugins: plugins };
		}

		var chart = null;
		function draw() {
			state.sideways = root.clientWidth < 560;
			canvasWrap.classList.toggle('is-sideways', state.sideways);
			canvasWrap.style.height = state.sideways ? (years.length * 30 + 70) + 'px' : '';
			legendKey.innerHTML = state.sideways
				? '<span class="cpl-key-item"><strong>Bold number</strong>&nbsp;= total offers</span><span class="cpl-key-item"><span class="cpl-key-diamond" aria-hidden="true"></span> Students appeared</span>'
				: '<span class="cpl-key-item"><span class="cpl-key-line" aria-hidden="true"></span> Total offers</span><span class="cpl-key-item"><span class="cpl-key-dash" aria-hidden="true"></span> Students appeared</span>';
			canvas.setAttribute('aria-label', title + ', ' + span + '. ' + years.map(function (y, i) {
				return y.year + ': ' + totals[i] + ' offers' + (y.appeared ? ', ' + y.appeared + ' students appeared' : '');
			}).join('; '));
			if (chart) { chart.destroy(); }
			chart = new window.Chart(canvas, buildConfig(false));
		}

		function buildTable() {
			var head2 = ['Year'].concat(ranked.slice(0, TOP).map(function (k) { return groups[k].name; }), ['Other companies', 'Total offers', 'Students appeared']);
			var rest = restOf(ranked.slice(0, TOP));
			var body = el('tbody');
			years.forEach(function (y, i) {
				var cells = [el('td', { text: String(y.year) })];
				ranked.slice(0, TOP).forEach(function (k) { cells.push(el('td', { text: String(groups[k].data[i] || 0) })); });
				cells.push(el('td', { text: String(rest[i] || 0) }));
				cells.push(el('td', { text: String(totals[i]) }));
				cells.push(el('td', { text: y.appeared ? String(y.appeared) : '–' }));
				body.appendChild(el('tr', {}, cells));
			});
			tableWrap.appendChild(el('table', {}, [
				el('caption', { text: title }),
				el('thead', {}, [el('tr', {}, head2.map(function (h) { return el('th', { scope: 'col', text: h }); }))]),
				body
			]));
		}

		dlBtn.addEventListener('click', function () {
			exportChart(buildConfig, 1200, 640, slug(title) + (state.focus ? '-' + slug(groups[state.focus].name) : ''));
		});
		var lastSideways = null;
		window.addEventListener('resize', function () {
			var sw = root.clientWidth < 560;
			if (chart && sw !== lastSideways) { lastSideways = sw; draw(); }
		});

		whenVisible(root, function () {
			buildTable();
			lastSideways = root.clientWidth < 560;
			setFocus(null);
		});
	}

	/* Sideways trend chart: the year's total offers at the end of each stacked bar. */
	function endTotalsPlugin(values, markers, f) {
		return {
			id: 'cplEndTotals',
			afterDatasetsDraw: function (chart) {
				var xs = chart.scales.x, ys = chart.scales.y, ctx = chart.ctx;
				ctx.save();
				ctx.font = '700 13px ' + f.body;
				ctx.fillStyle = INK.primary;
				ctx.textAlign = 'left';
				ctx.textBaseline = 'middle';
				values.forEach(function (v, i) {
					if (!v) { return; }
					// Start after the bar end, or after the students-appeared diamond if that sits further right.
					var x = xs.getPixelForValue(v) + 6;
					if (markers[i]) { x = Math.max(x, xs.getPixelForValue(markers[i]) + 10); }
					ctx.lineWidth = 3;
					ctx.strokeStyle = '#fff';
					ctx.strokeText(String(v), x, ys.getPixelForValue(i));
					ctx.fillText(String(v), x, ys.getPixelForValue(i));
				});
				ctx.restore();
			}
		};
	}

	/* Highlighted company: its number on each bar segment, where it fits. */
	function focusValuesPlugin(f) {
		return {
			id: 'cplFocusValues',
			afterDatasetsDraw: function (chart) {
				var di = chart.data.datasets.findIndex(function (d) { return d.cplFocus; });
				if (di < 0) { return; }
				var meta = chart.getDatasetMeta(di);
				var horizontal = chart.options.indexAxis === 'y';
				var ctx = chart.ctx;
				ctx.save();
				ctx.font = '700 12px ' + f.body;
				ctx.fillStyle = '#fff';
				ctx.textAlign = 'center';
				ctx.textBaseline = 'middle';
				meta.data.forEach(function (bar, i) {
					var v = chart.data.datasets[di].data[i];
					if (!v) { return; }
					var size = horizontal ? Math.abs(bar.x - bar.base) : Math.abs(bar.base - bar.y);
					if (size < 18) { return; }
					ctx.fillText(String(v), horizontal ? (bar.x + bar.base) / 2 : bar.x, horizontal ? bar.y : (bar.y + bar.base) / 2);
				});
				ctx.restore();
			}
		};
	}

	/* Match the theme: heading font for chart titles, and the page colour behind the year buttons' fade. */
	function themeVars(root) {
		root.style.setProperty('--cpl-heading-font', fonts(root).heading);
		var n = root.parentElement;
		while (n) {
			var bg = getComputedStyle(n).backgroundColor;
			if (bg && bg !== 'transparent' && !/rgba\(.*,\s*0\)$/.test(bg)) { root.style.setProperty('--cpl-page', bg); break; }
			n = n.parentElement;
		}
	}

	function boot() {
		var data = window.CPL_DATA;
		if (!data || !window.Chart) { return; }
		document.querySelectorAll('.cpl-trend, .cpl-year').forEach(themeVars);
		document.querySelectorAll('.cpl-trend').forEach(function (root) { renderTrend(root, data); });
		document.querySelectorAll('.cpl-year').forEach(function (root) { renderYear(root, data); });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
