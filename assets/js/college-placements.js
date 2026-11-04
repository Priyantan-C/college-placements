/* College Placements – front-end charts. Mirrors the settings of the previous Netlify app. */
(function () {
	'use strict';

	var COLORS = [
		'rgba(0, 123, 255, 0.6)', 'rgba(23, 162, 184, 0.6)', 'rgba(40, 167, 69, 0.6)', 'rgba(255, 80, 0, 0.6)',
		'rgba(156, 204, 101, 0.6)', 'rgba(255, 161, 0, 0.6)', 'rgba(31, 58, 147, 0.6)', 'rgba(102, 51, 153, 0.6)',
		'rgba(64, 87, 135, 0.6)', 'rgba(220, 53, 69, 0.6)', 'rgba(25, 135, 84, 0.6)', 'rgba(249, 168, 37, 0.6)',
		'rgba(15, 107, 135, 0.6)', 'rgba(138, 141, 144, 0.6)'
	];

	// Font Awesome Free icons (CC BY 4.0): laptop-code and user-graduate.
	var ICON_LAPTOP = '<svg viewBox="0 0 640 512" aria-hidden="true"><path fill="currentColor" d="M64 96c0-35.3 28.7-64 64-64H512c35.3 0 64 28.7 64 64V352H512V96H128V352H64V96zM0 403.2C0 392.6 8.6 384 19.2 384H620.8c10.6 0 19.2 8.6 19.2 19.2c0 42.4-34.4 76.8-76.8 76.8H76.8C34.4 480 0 445.6 0 403.2zM281 209l-31 31 31 31c9.4 9.4 9.4 24.6 0 33.9s-24.6 9.4-33.9 0l-48-48c-9.4-9.4-9.4-24.6 0-33.9l48-48c9.4-9.4 24.6-9.4 33.9 0s9.4 24.6 0 33.9zM393 175l48 48c9.4 9.4 9.4 24.6 0 33.9l-48 48c-9.4 9.4-24.6 9.4-33.9 0s-9.4-24.6 0-33.9l31-31-31-31c-9.4-9.4-9.4-24.6 0-33.9s24.6-9.4 33.9 0z"/></svg>';
	var ICON_GRAD = '<svg viewBox="0 0 448 512" aria-hidden="true"><path fill="currentColor" d="M219.3 .5c3.1-.6 6.3-.6 9.4 0l200 40C439.9 42.7 448 52.6 448 64s-8.1 21.3-19.3 23.5L352 102.9V160c0 70.7-57.3 128-128 128s-128-57.3-128-128V102.9L48 93.3v65.1l15.7 78.4c.9 4.7-.3 9.6-3.3 13.3s-7.6 5.9-12.4 5.9H16c-4.8 0-9.3-2.1-12.4-5.9s-4.3-8.6-3.3-13.3L16 158.4V86.6C6.5 83.3 0 74.3 0 64C0 52.6 8.1 42.7 19.3 40.5l200-40zM111.9 327.7c10.5-3.4 21.8 .4 29.4 8.5l71 75.5c6.3 6.7 17 6.7 23.3 0l71-75.5c7.6-8.1 18.9-11.9 29.4-8.5C401 348.6 448 409.4 448 481.3c0 17-13.8 30.7-30.7 30.7H30.7C13.8 512 0 498.2 0 481.3c0-71.9 47-132.7 111.9-153.6z"/></svg>';

	function el(tag, attrs, children) {
		var node = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			if (k === 'text') { node.textContent = attrs[k]; } else if (k === 'html') { node.innerHTML = attrs[k]; } else { node.setAttribute(k, attrs[k]); }
		});
		(children || []).forEach(function (c) { if (c) { node.appendChild(c); } });
		return node;
	}

	function sum(rows) {
		return rows.reduce(function (t, r) { return t + r[1]; }, 0);
	}

	/* ---------- [placement_trend] ---------- */
	function renderTrend(root, data) {
		var years = data.years;
		var labels = years.map(function (y) { return y.year; });
		var companies = [];
		years.forEach(function (y) {
			y.rows.forEach(function (r) { if (companies.indexOf(r[0]) === -1) { companies.push(r[0]); } });
		});

		var datasets = companies.map(function (name, i) {
			return {
				label: name,
				data: years.map(function (y) {
					var row = y.rows.filter(function (r) { return r[0] === name; })[0];
					return row ? row[1] : 0;
				}),
				backgroundColor: COLORS[i % COLORS.length],
				type: 'bar'
			};
		});
		var totals = years.map(function (y) { return sum(y.rows); });
		var appeared = years.map(function (y) { return y.appeared; });
		var max = Math.max.apply(Math, appeared.concat([1]));

		datasets.push(
			{ label: 'Total Placements', data: totals, fill: false, borderColor: 'rgba(255, 99, 132, 0.6)', backgroundColor: 'rgba(255, 99, 132, 0.6)', tension: 0.4, yAxisID: 'y', type: 'line' },
			{ label: 'Total Students Appeared', data: appeared, fill: false, borderColor: 'rgba(54, 162, 235, 0.6)', backgroundColor: 'rgba(54, 162, 235, 0.6)', tension: 0.4, yAxisID: 'y1', type: 'line' }
		);

		new window.Chart(root.querySelector('canvas'), {
			type: 'bar',
			data: { labels: labels, datasets: datasets },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: { legend: { position: 'top' }, title: { display: true, text: root.getAttribute('data-title') } },
				scales: {
					x: { stacked: true },
					y: { beginAtZero: true, stacked: true, display: true, max: Math.ceil(1.5 * max), ticks: { stepSize: Math.ceil(max / 10), precision: 0 } },
					y1: { beginAtZero: true, stacked: false, display: false, max: Math.ceil(1.5 * max), ticks: { stepSize: Math.ceil(max / 10), precision: 0 } }
				}
			}
		});
	}

	/* ---------- [placement_year] ---------- */
	function renderYear(root, data) {
		var years = data.years.slice().sort(function (a, b) { return b.year - a.year; });
		if (!years.length) { return; }
		var wanted = parseInt(root.getAttribute('data-year'), 10);
		var current = years.filter(function (y) { return y.year === wanted; })[0] || years[0];
		var uid = 'cpl-year-' + Math.random().toString(36).slice(2, 8);

		var select = el('select', { id: uid, 'class': 'cpl-select' });
		years.forEach(function (y) {
			var opt = el('option', { value: y.year, text: String(y.year) });
			if (y.year === current.year) { opt.selected = true; }
			select.appendChild(opt);
		});

		var canvas = el('canvas', { role: 'img', 'aria-label': root.getAttribute('data-title') });
		var appearedEl = el('h5', { 'class': 'cpl-card-title' });
		var offersEl = el('h5', { 'class': 'cpl-card-title' });
		var tbody = el('tbody');
		var tfoot = el('tbody');

		root.appendChild(el('div', { 'class': 'cpl-picker' }, [
			el('label', { 'for': uid, text: 'Select Year:' }), select
		]));
		root.appendChild(el('div', { 'class': 'cpl-pie' }, [canvas]));
		root.appendChild(el('div', { 'class': 'cpl-cards' }, [
			el('div', { 'class': 'cpl-card' }, [el('span', { 'class': 'cpl-icon', html: ICON_LAPTOP }), appearedEl]),
			el('div', { 'class': 'cpl-card' }, [el('span', { 'class': 'cpl-icon', html: ICON_GRAD }), offersEl])
		]));
		var table = el('table', { 'class': 'cpl-company-table' }, [
			el('thead', {}, [el('tr', {}, ['Serial No.', 'Company Name', 'Number of Placements', 'Highest Package', 'Average Package'].map(function (h) {
				return el('th', { text: h });
			}))]),
			tbody, tfoot
		]);
		root.appendChild(el('div', { 'class': 'cpl-table-wrap' }, [table]));

		var chart = new window.Chart(canvas, {
			type: 'pie',
			data: { labels: [], datasets: [{ data: [], backgroundColor: [] }] },
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					title: { display: true, text: root.getAttribute('data-title'), font: { weight: 'bold' } },
					tooltip: { callbacks: { label: function (ctx) { return ctx.label + ': ' + ctx.parsed; } } }
				}
			}
		});

		function show(y) {
			var rows = y.rows.filter(function (r) { return r[1] !== 0; });
			chart.data.labels = rows.map(function (r) { return r[0]; });
			chart.data.datasets[0].data = rows.map(function (r) { return r[1]; });
			chart.data.datasets[0].backgroundColor = rows.map(function (r, i) { return COLORS[i % COLORS.length]; });
			chart.update();

			appearedEl.textContent = 'Total Students Participated/Eligible: ' + y.appeared;
			offersEl.textContent = 'Total Offers: ' + sum(rows);

			tbody.innerHTML = '';
			rows.forEach(function (r, i) {
				var tr = el('tr', {}, [el('td', { text: String(i + 1) }), el('td', { text: r[0] }), el('td', { text: String(r[1]) })]);
				if (i === 0) {
					tr.appendChild(el('td', { 'class': 'cpl-pkg', rowspan: String(rows.length), text: y.highest || '' }));
					tr.appendChild(el('td', { 'class': 'cpl-pkg', rowspan: String(rows.length), text: y.avg || '' }));
				}
				tbody.appendChild(tr);
			});
			tfoot.innerHTML = '';
			if (y.footnote) {
				tfoot.appendChild(el('tr', {}, [el('td', { colspan: '5', 'class': 'cpl-footnote', text: y.footnote })]));
			}
		}

		select.addEventListener('change', function () {
			var y = years.filter(function (x) { return x.year === parseInt(select.value, 10); })[0];
			if (y) { show(y); }
		});
		show(current);
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
