/**
 * MK Auto Elite - cascading vehicle finder.
 * Loads dependent options via AJAX and persists the selected vehicle.
 */
(function () {
	'use strict';

	var MKAE = window.MKAE || {};
	var STORAGE_KEY = 'mkae_vehicle';

	// The step order defines the cascade: each step enables the next.
	var ORDER = ['mk_make', 'mk_model', 'mk_year', 'mk_engine', 'mk_fuel', 'mk_transmission'];

	// Fetch term options for a taxonomy, constrained by any parent selections
	// (e.g. only models for the chosen make) so the cascade is truly dependent.
	function fetchTerms(taxonomy, parents) {
		var body = new URLSearchParams();
		body.set('action', 'mkae_get_terms');
		body.set('nonce', MKAE.nonce || '');
		body.set('taxonomy', taxonomy);
		if (parents) {
			Object.keys(parents).forEach(function (k) {
				if (parents[k]) { body.set('parents[' + k + ']', parents[k]); }
			});
		}
		return fetch(MKAE.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (json) { return (json && json.success) ? json.data.options : []; })
			.catch(function () { return []; });
	}

	// Collect the chosen values for every step up to and including idx, so a
	// dependent field can be filtered by all earlier selections.
	function parentsUpTo(form, idx) {
		var parents = {};
		for (var i = 0; i <= idx; i++) {
			var s = form.querySelector('[data-tax="' + ORDER[i] + '"]');
			if (s && s.value) { parents[ORDER[i]] = s.value; }
		}
		return parents;
	}

	function fillSelect(select, options, placeholder) {
		select.innerHTML = '';
		var first = document.createElement('option');
		first.value = '';
		first.textContent = placeholder || '';
		select.appendChild(first);
		options.forEach(function (opt) {
			var el = document.createElement('option');
			el.value = opt.value;
			el.textContent = opt.count ? (opt.label + ' (' + opt.count + ')') : opt.label;
			select.appendChild(el);
		});
	}

	function persist(form) {
		var data = { label: '' };
		var parts = [];
		ORDER.forEach(function (tax) {
			var sel = form.querySelector('[data-tax="' + tax + '"]');
			if (sel && sel.value) {
				data[tax] = sel.value;
				var text = sel.options[sel.selectedIndex].textContent.replace(/\s*\(\d+\)\s*$/, '');
				parts.push(text);
			}
		});
		data.label = parts.join(' ');
		try { window.localStorage.setItem(STORAGE_KEY, JSON.stringify(data)); } catch (e) {}
		document.cookie = STORAGE_KEY + '=' + encodeURIComponent(JSON.stringify(data)) + '; path=/; max-age=' + (60 * 60 * 24 * 30);
	}

	function initFinder(form) {
		var selects = ORDER.map(function (tax) { return form.querySelector('[data-tax="' + tax + '"]'); });

		// Populate the first step immediately.
		var makeSelect = form.querySelector('[data-tax="mk_make"]');
		if (makeSelect) {
			fetchTerms('mk_make').then(function (opts) {
				// Always start unselected: the Make field shows "Select make" on
				// every load. We intentionally do NOT restore a previous choice.
				fillSelect(makeSelect, opts, (MKAE.i18n && MKAE.i18n.selectMake) || 'Select make');
			});
		}

		// Cascade: when a step changes, load the next one filtered by every
		// selection so far, and reset the steps beyond it.
		selects.forEach(function (sel, idx) {
			if (!sel) return;
			sel.addEventListener('change', function () {
				for (var j = idx + 1; j < selects.length; j++) {
					if (selects[j]) {
						selects[j].innerHTML = '<option value="">' + ((MKAE.i18n && MKAE.i18n.loading) || '...') + '</option>';
						selects[j].disabled = true;
					}
				}
				var next = selects[idx + 1];
				if (next && sel.value) {
					fetchTerms(ORDER[idx + 1], parentsUpTo(form, idx)).then(function (opts) {
						fillSelect(next, opts, '');
						next.disabled = false;
					});
				}
				persist(form);
			});
		});

		form.addEventListener('submit', function () { persist(form); });
	}

	function init() {
		document.querySelectorAll('.mkae-finder').forEach(initFinder);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
