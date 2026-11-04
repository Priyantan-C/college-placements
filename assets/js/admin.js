/* College Placements – admin helpers. */
(function () {
	'use strict';

	function key(s) {
		return String(s || '').toLowerCase().replace(/[^\p{L}\p{N}]+/gu, '');
	}

	function $all(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	/* Reorder rows: drag the handle, or use the arrow buttons. Works for any tbody.cpl-sortable. */
	var dragging = null;
	function moveRow(row, dir) {
		var target = dir === 'up' ? row.previousElementSibling : row.nextElementSibling;
		if (!target) { return; }
		if (dir === 'up') { target.before(row); } else { target.after(row); }
		row.querySelector('.cpl-move[data-dir="' + dir + '"]').focus();
		flash(row);
	}
	function flash(row) {
		row.classList.remove('cpl-moved');
		void row.offsetWidth;
		row.classList.add('cpl-moved');
	}
	$all('tbody.cpl-sortable').forEach(function (body) {
		body.addEventListener('click', function (e) {
			var btn = e.target.closest('.cpl-move');
			if (btn) { e.preventDefault(); moveRow(btn.closest('tr'), btn.getAttribute('data-dir')); }
		});
		body.addEventListener('dragstart', function (e) {
			if (!e.target.classList || !e.target.classList.contains('cpl-handle')) { return; }
			dragging = e.target.closest('tr');
			dragging.classList.add('cpl-dragging');
			e.dataTransfer.effectAllowed = 'move';
			try { e.dataTransfer.setData('text/plain', ''); e.dataTransfer.setDragImage(dragging, 20, 20); } catch (err) { /* older browsers */ }
		});
		body.addEventListener('dragover', function (e) {
			if (!dragging || dragging.parentNode !== body) { return; }
			e.preventDefault();
			var over = e.target.closest('tr');
			if (!over || over === dragging || over.parentNode !== body) { return; }
			var rect = over.getBoundingClientRect();
			if (e.clientY < rect.top + rect.height / 2) { over.before(dragging); } else { over.after(dragging); }
		});
		body.addEventListener('drop', function (e) { if (dragging) { e.preventDefault(); } });
		body.addEventListener('dragend', function () {
			if (dragging) { dragging.classList.remove('cpl-dragging'); flash(dragging); dragging = null; }
		});
	});

	/* Companies: order, Others, live totals. */
	var featured = document.querySelector('.cpl-featured');
	if (featured) {
		var totalsCell = featured.querySelector('.cpl-totals');
		var companyBody = featured.querySelector('tbody.cpl-sortable');
		var boxes = $all('input[name="featured[]"]', featured);
		var updateTotals = function () {
			var total = parseInt(totalsCell.getAttribute('data-total'), 10) || 0;
			var shown = 0;
			var ticked = 0;
			boxes.forEach(function (b) {
				b.closest('tr').classList.toggle('cpl-in-others', !b.checked);
				if (b.checked) { shown += parseInt(b.getAttribute('data-offers'), 10) || 0; ticked++; }
			});
			totalsCell.textContent = 'Own rows: ' + ticked + ' companies, ' + shown + ' offers · Others: ' + (total - shown) + ' offers · Total: ' + total;
		};
		boxes.forEach(function (b) { b.addEventListener('change', updateTotals); });
		$all('[data-cpl-check]').forEach(function (a) {
			a.addEventListener('click', function (e) {
				e.preventDefault();
				var on = a.getAttribute('data-cpl-check') === 'all';
				boxes.forEach(function (b) { b.checked = on; });
				updateTotals();
			});
		});
		$all('[data-cpl-sort]').forEach(function (a) {
			a.addEventListener('click', function (e) {
				e.preventDefault();
				var by = a.getAttribute('data-cpl-sort');
				var rows = $all('tr', companyBody);
				rows.sort(function (x, y) {
					if (by === 'offers') {
						return (parseInt(y.getAttribute('data-offers'), 10) - parseInt(x.getAttribute('data-offers'), 10)) ||
							x.getAttribute('data-name').localeCompare(y.getAttribute('data-name'));
					}
					return x.getAttribute('data-name').localeCompare(y.getAttribute('data-name'), undefined, { sensitivity: 'base' });
				});
				rows.forEach(function (r) { companyBody.appendChild(r); });
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

/* Replacing a saved student list: the Replace button stays disabled until the confirmation box is ticked. */
(function () {
	'use strict';
	var gate = document.querySelector('[data-cpl-gate]');
	var button = document.querySelector('[data-cpl-gated]');
	if (!gate || !button) { return; }
	var sync = function () { button.disabled = !gate.checked; };
	gate.addEventListener('change', sync);
	sync();
}());
