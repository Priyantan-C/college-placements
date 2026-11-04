/* College Placements – search and department filter for the student tables. */
(function () {
	'use strict';

	function norm(s) {
		return String(s || '').toLowerCase().replace(/\s+/g, ' ').trim();
	}

	function init(root) {
		var toolbar = root.querySelector('.cpl-rec-toolbar');
		var heading = document.querySelector('h1, h2, .entry-title');
		if (heading) {
			// Year headings use the theme's heading font, like the rest of the page's headings.
			root.style.setProperty('--cpl-heading-font', getComputedStyle(heading).fontFamily);
		}
		var input = root.querySelector('.cpl-search input');
		var chips = Array.prototype.slice.call(root.querySelectorAll('.cpl-chip'));
		var count = root.querySelector('.cpl-rec-count');
		var blocks = Array.prototype.slice.call(root.querySelectorAll('.cpl-acc'));
		var dept = '';
		var timer = null;
		var openBefore = null;

		blocks.forEach(function (b) {
			Array.prototype.forEach.call(b.querySelectorAll('tbody tr'), function (tr) {
				tr.$text = norm(tr.textContent + ' ' + (tr.getAttribute('data-company') || ''));
			});
		});
		toolbar.hidden = false;

		function apply() {
			var q = norm(input.value);
			var filtering = q !== '' || dept !== '';
			if (filtering && !openBefore) {
				openBefore = blocks.map(function (b) { return b.open; });
			}
			var shown = 0;
			var total = 0;
			blocks.forEach(function (b, i) {
				var n = 0;
				var rows = b.querySelectorAll('tbody tr');
				Array.prototype.forEach.call(rows, function (tr) {
					var ok = (!q || tr.$text.indexOf(q) !== -1) && (!dept || tr.getAttribute('data-dept') === dept);
					tr.hidden = !ok;
					if (ok) { n++; }
				});
				shown += n;
				total += rows.length;
				var meta = b.querySelector('.cpl-acc-meta');
				if (!meta.$orig) { meta.$orig = meta.textContent; }
				meta.textContent = filtering ? n + ' of ' + meta.getAttribute('data-total') + ' match' : meta.$orig;
				b.querySelector('.cpl-empty').hidden = n > 0;
				b.classList.toggle('is-empty', filtering && n === 0);
				if (filtering) {
					b.open = n > 0;
				} else if (openBefore) {
					b.open = openBefore[i];
				}
			});
			if (!filtering) { openBefore = null; }
			count.textContent = filtering ? 'Showing ' + shown + ' of ' + total + ' students' : '';
		}

		input.addEventListener('input', function () {
			clearTimeout(timer);
			timer = setTimeout(apply, 120);
		});
		chips.forEach(function (c) {
			c.addEventListener('click', function () {
				dept = c.getAttribute('data-dept');
				chips.forEach(function (x) {
					var on = x === c;
					x.classList.toggle('is-active', on);
					x.setAttribute('aria-pressed', on ? 'true' : 'false');
				});
				apply();
			});
		});
	}

	function boot() {
		document.querySelectorAll('[data-cpl-records]').forEach(init);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
