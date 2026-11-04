/* College Placements – admin helpers. */
(function () {
	'use strict';

	function key(s) {
		return String(s || '').toLowerCase().replace(/[^\p{L}\p{N}]+/gu, '');
	}

	function $all(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	/* Companies shown separately: live totals and tick all / none. */
	var featured = document.querySelector('.cpl-featured');
	if (featured) {
		var totalsCell = featured.querySelector('.cpl-totals');
		var boxes = $all('input[name="featured[]"]', featured);
		var updateTotals = function () {
			var total = parseInt(totalsCell.getAttribute('data-total'), 10) || 0;
			var shown = 0;
			var ticked = 0;
			boxes.forEach(function (b) {
				if (b.checked && !b.disabled) { shown += parseInt(b.getAttribute('data-offers'), 10) || 0; ticked++; }
			});
			totalsCell.textContent = 'Shown separately: ' + ticked + ' companies, ' + shown + ' offers · Others: ' + (total - shown) + ' offers · Total: ' + total;
		};
		boxes.forEach(function (b) { b.addEventListener('change', updateTotals); });
		$all('[data-cpl-check]').forEach(function (a) {
			a.addEventListener('click', function (e) {
				e.preventDefault();
				var on = a.getAttribute('data-cpl-check') === 'all';
				boxes.forEach(function (b) { if (!b.disabled) { b.checked = on; } });
				updateTotals();
			});
		});
		updateTotals();
	}

	/* Dim the panel the charts are not using. */
	var radios = $all('input[name="chart_source"]');
	var syncPanels = function () {
		var current = (radios.filter(function (r) { return r.checked; })[0] || {}).value;
		$all('.cpl-panel').forEach(function (p) {
			p.classList.toggle('cpl-inactive', p.getAttribute('data-panel') !== current);
		});
	};
	radios.forEach(function (r) { r.addEventListener('change', syncPanels); });
	if (radios.length) { syncPanels(); }

	/* Summary rows. */
	var summary = document.querySelector('.cpl-summary');
	if (summary) {
		var body = summary.querySelector('tbody');
		var totalEl = summary.querySelector('.cpl-sum-total');
		var updateSum = function () {
			var t = 0;
			$all('input[name="summary_offers[]"]', summary).forEach(function (i) { t += parseInt(i.value, 10) || 0; });
			totalEl.textContent = 'Total: ' + t;
		};
		summary.addEventListener('input', updateSum);
		summary.addEventListener('click', function (e) {
			if (e.target.classList.contains('cpl-remove-row')) {
				var row = e.target.closest('tr');
				if (body.rows.length > 1) { row.remove(); } else { $all('input', row).forEach(function (i) { i.value = ''; }); }
				updateSum();
			}
			if (e.target.classList.contains('cpl-add-row')) {
				var tpl = body.rows[body.rows.length - 1].cloneNode(true);
				$all('input', tpl).forEach(function (i) { i.value = ''; });
				body.appendChild(tpl);
				tpl.querySelector('input').focus();
			}
		});
		updateSum();
	}

	/* Upload preview: offers per company as names are edited. */
	var names = document.querySelector('.cpl-names[data-live-totals]');
	var live = document.querySelector('.cpl-live tbody');
	if (names && live) {
		var updateLive = function () {
			var groups = {};
			var order = [];
			$all('tbody tr', names).forEach(function (tr) {
				var input = tr.querySelector('input[name="company[]"]');
				var n = parseInt(tr.querySelector('[data-rows]').getAttribute('data-rows'), 10) || 0;
				var name = input.value.trim() || tr.querySelector('input[name="spelling[]"]').value;
				var k = key(name);
				if (!groups[k]) { groups[k] = { name: name, n: 0 }; order.push(k); }
				groups[k].n += n;
			});
			order.sort(function (a, b) { return groups[b].n - groups[a].n; });
			live.innerHTML = '';
			var total = 0;
			order.forEach(function (k) {
				total += groups[k].n;
				var tr = document.createElement('tr');
				var a = document.createElement('td');
				var b = document.createElement('td');
				a.textContent = groups[k].name;
				b.textContent = groups[k].n;
				b.className = 'num';
				tr.appendChild(a);
				tr.appendChild(b);
				live.appendChild(tr);
			});
			var tr = document.createElement('tr');
			tr.innerHTML = '<th>Total</th><th class="num"></th>';
			tr.lastChild.textContent = total;
			live.appendChild(tr);
		};
		names.addEventListener('input', updateLive);
		updateLive();
	}
}());
