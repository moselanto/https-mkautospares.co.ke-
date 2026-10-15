/**
 * MK Auto Elite - vehicle finder (v2.23.0).
 * Make and Model come from one cached map (instant after the first load);
 * Year / Engine / Fuel / Gearbox use the filtered AJAX lookup with a session
 * cache. Persists the chosen vehicle and shows a live part count.
 */
(function () {
	'use strict';

	var MKAE = window.MKAE || {};
	var STORAGE_KEY = 'mkae_vehicle';
	var ORDER = ['mk_make', 'mk_model', 'mk_year', 'mk_engine', 'mk_fuel', 'mk_transmission'];
	var mapPromise = null;

	function ajaxUrl() { return MKAE.ajaxUrl || '/wp-admin/admin-ajax.php'; }

	function loadMap(v) {
		if (mapPromise) { return mapPromise; }
		var key = 'mkae_fmap_' + (v || '1');
		try {
			var raw = window.localStorage.getItem(key);
			if (raw) {
				var c = JSON.parse(raw);
				if (c && c.t && (Date.now() - c.t) < 12 * 3600 * 1000 && c.d && c.d.makes) {
					mapPromise = Promise.resolve(c.d);
					return mapPromise;
				}
			}
		} catch (e) {}
		mapPromise = fetch(ajaxUrl() + '?action=mkae_finder_map&v=' + encodeURIComponent(v || '1'), { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (j) {
				if (!j || !j.success) { throw new Error('map'); }
				try {
					Object.keys(window.localStorage).forEach(function (k) { if (k.indexOf('mkae_fmap_') === 0) { window.localStorage.removeItem(k); } });
					window.localStorage.setItem(key, JSON.stringify({ t: Date.now(), d: j.data }));
				} catch (e2) {}
				return j.data;
			})
			.catch(function () { mapPromise = null; return null; });
		return mapPromise;
	}

	function ajaxTerms(taxonomy, parents) {
		var body = new URLSearchParams();
		body.set('action', 'mkae_get_terms');
		body.set('nonce', MKAE.nonce || '');
		body.set('taxonomy', taxonomy);
		Object.keys(parents || {}).forEach(function (k) { if (parents[k]) { body.set('parents[' + k + ']', parents[k]); } });
		var ck = 'mkae_ft_' + body.toString();
		try { var hit = window.sessionStorage.getItem(ck); if (hit) { return Promise.resolve(JSON.parse(hit)); } } catch (e) {}
		return fetch(ajaxUrl(), { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (res) { return res.json(); })
			.then(function (json) {
				var o = (json && json.success) ? json.data.options : [];
				try { window.sessionStorage.setItem(ck, JSON.stringify(o)); } catch (e) {}
				return o;
			})
			.catch(function () { return []; });
	}

	function fetchTerms(form, taxonomy, parents) {
		var keys = Object.keys(parents || {}).filter(function (k) { return parents[k]; });
		if (taxonomy === 'mk_make' && !keys.length) {
			return loadMap(form.getAttribute('data-map-v')).then(function (m) {
				if (!m) { return ajaxTerms(taxonomy, parents); }
				return m.makes.map(function (x) { return { value: x.s, label: x.n, count: x.c }; });
			});
		}
		if (taxonomy === 'mk_model' && keys.length === 1 && keys[0] === 'mk_make') {
			return loadMap(form.getAttribute('data-map-v')).then(function (m) {
				if (!m) { return ajaxTerms(taxonomy, parents); }
				var mk = m.makes.filter(function (x) { return x.s === parents.mk_make; })[0];
				if (!mk) { return ajaxTerms(taxonomy, parents); }
				return mk.m.map(function (x) { return { value: x[0], label: x[1], count: x[2] }; });
			});
		}
		return ajaxTerms(taxonomy, parents);
	}

	function parentsUpTo(form, idx) {
		var parents = {};
		for (var i = 0; i <= idx; i++) {
			var s = form.querySelector('[data-tax="' + ORDER[i] + '"]');
			if (s && s.value) { parents[ORDER[i]] = s.value; }
		}
		return parents;
	}

	function fillSelect(select, options) {
		var ph = select.getAttribute('data-placeholder') || '';
		select.innerHTML = '';
		var first = document.createElement('option');
		first.value = '';
		first.textContent = options.length ? ph : (ph + ' (none listed)');
		select.appendChild(first);
		var frag = document.createDocumentFragment();
		options.forEach(function (opt) {
			var el = document.createElement('option');
			el.value = opt.value;
			el.textContent = opt.count ? (opt.label + ' (' + opt.count + ')') : opt.label;
			if (opt.count) { el.setAttribute('data-count', opt.count); }
			frag.appendChild(el);
		});
		select.appendChild(frag);
	}

	function label(sel) {
		return sel && sel.value ? sel.options[sel.selectedIndex].textContent.replace(/\s*\(\d+\)\s*$/, '') : '';
	}

	function persist(form) {
		var data = { label: '' };
		var parts = [];
		ORDER.forEach(function (tax) {
			var sel = form.querySelector('[data-tax="' + tax + '"]');
			if (sel && sel.value) { data[tax] = sel.value; parts.push(label(sel)); }
		});
		data.label = parts.join(' ');
		try { window.localStorage.setItem(STORAGE_KEY, JSON.stringify(data)); } catch (e) {}
		document.cookie = STORAGE_KEY + '=' + encodeURIComponent(JSON.stringify(data)) + '; path=/; max-age=' + (60 * 60 * 24 * 30) + '; SameSite=Lax';
	}

	function shopUrl(form, data) {
		var u = new URL(form.getAttribute('action'), window.location.href);
		ORDER.forEach(function (t) { if (data[t]) { u.searchParams.set(t, data[t]); } });
		return u.toString();
	}

	function updateCount(form) {
		var lbl = form.querySelector('[data-mkvf-submit-label]');
		if (!lbl) { return; }
		var n = 0;
		for (var i = ORDER.length - 1; i >= 0; i--) {
			var s = form.querySelector('[data-tax="' + ORDER[i] + '"]');
			if (s && s.value) {
				var o = s.options[s.selectedIndex];
				n = parseInt(o.getAttribute('data-count') || '0', 10);
				break;
			}
		}
		lbl.textContent = n > 0 ? ('Show ' + n.toLocaleString('en-KE') + ' part' + (n === 1 ? '' : 's')) : 'Find Parts';
	}

	function showSaved(form) {
		var box = form.querySelector('[data-mkvf-saved]');
		if (!box) { return; }
		var d = null;
		try { d = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || 'null'); } catch (e) {}
		if (d && d.mk_make && d.label) {
			box.hidden = false;
			box.querySelector('[data-mkvf-saved-label]').textContent = d.label;
			box.querySelector('[data-mkvf-saved-go]').setAttribute('href', shopUrl(form, d));
		} else {
			box.hidden = true;
		}
	}

	function initFinder(form) {
		var selects = ORDER.map(function (tax) { return form.querySelector('[data-tax="' + tax + '"]'); });
		var makeSelect = selects[0];
		var chips = form.querySelectorAll('[data-make]');

		function cascade(idx) {
			var sel = selects[idx];
			for (var j = idx + 1; j < selects.length; j++) {
				if (selects[j]) {
					selects[j].innerHTML = '<option value="">' + (selects[j].getAttribute('data-placeholder') || '') + '</option>';
					selects[j].disabled = true;
				}
			}
			var next = selects[idx + 1];
			if (next && sel.value) {
				next.closest('.mkae-finder__field').classList.add('is-loading');
				return fetchTerms(form, ORDER[idx + 1], parentsUpTo(form, idx)).then(function (opts) {
					fillSelect(next, opts);
					next.disabled = !opts.length;
					next.closest('.mkae-finder__field').classList.remove('is-loading');
					if (idx === 0 && opts.length && window.innerWidth > 640) { next.focus(); }
				});
			}
			return Promise.resolve();
		}

		if (makeSelect) {
			fetchTerms(form, 'mk_make', {}).then(function (opts) {
				fillSelect(makeSelect, opts);
				var preset = form.getAttribute('data-preset-make');
				if (preset && makeSelect.querySelector('option[value="' + preset + '"]')) {
					makeSelect.value = preset;
					cascade(0).then(function () { updateCount(form); });
				}
				updateCount(form);
			});
		}

		selects.forEach(function (sel, idx) {
			if (!sel) { return; }
			sel.addEventListener('change', function () {
				if (idx === 0) {
					chips.forEach(function (c) { c.classList.toggle('is-on', c.getAttribute('data-make') === sel.value); });
				}
				cascade(idx).then(function () { updateCount(form); });
				updateCount(form);
				persist(form);
			});
		});

		chips.forEach(function (c) {
			c.addEventListener('click', function () {
				if (!makeSelect) { return; }
				var v = c.getAttribute('data-make');
				var go = function () {
					if (makeSelect.querySelector('option[value="' + v + '"]')) {
						makeSelect.value = v;
						makeSelect.dispatchEvent(new Event('change', { bubbles: true }));
					}
				};
				if (makeSelect.options.length > 1) { go(); } else { fetchTerms(form, 'mk_make', {}).then(function (o) { fillSelect(makeSelect, o); go(); }); }
			});
		});

		var clear = form.querySelector('[data-mkae-clear-vehicle]');
		if (clear) {
			clear.addEventListener('click', function () {
				try { window.localStorage.removeItem(STORAGE_KEY); } catch (e) {}
				document.cookie = STORAGE_KEY + '=; path=/; max-age=0';
				showSaved(form);
				if (makeSelect) { makeSelect.value = ''; makeSelect.dispatchEvent(new Event('change', { bubbles: true })); makeSelect.focus(); }
			});
		}

		form.addEventListener('submit', function (e) {
			if (makeSelect && !makeSelect.value) {
				e.preventDefault();
				makeSelect.focus();
				form.classList.add('is-invalid');
				setTimeout(function () { form.classList.remove('is-invalid'); }, 1200);
				return;
			}
			persist(form);
			selects.forEach(function (s) { if (s && !s.value) { s.disabled = true; } });
			if (typeof window.gtag === 'function') {
				var d = parentsUpTo(form, ORDER.length - 1);
				window.gtag('event', 'vehicle_finder', { make: d.mk_make || '', model: d.mk_model || '', year: d.mk_year || '' });
			}
		});

		showSaved(form);
	}

	function init() {
		var forms = document.querySelectorAll('.mkae-finder');
		if (!forms.length) { return; }
		forms.forEach(initFinder);
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
