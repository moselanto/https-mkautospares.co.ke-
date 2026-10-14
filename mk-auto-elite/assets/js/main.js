/**
 * MK Auto Elite - front-end behaviour.
 * Vanilla ES2024, no dependencies. Loaded deferred.
 */
(function () {
	'use strict';

	var MKAE = window.MKAE || {};

	function wcAjax(endpoint) {
		var base = MKAE.homeUrl || '/';
		return base + (base.indexOf('?') > -1 ? '&' : '?') + 'wc-ajax=' + endpoint;
	}

	function escapeHtml(str) {
		return String(str == null ? '' : str).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	function getCookie(name) {
		var m = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
		return m ? decodeURIComponent(m.pop()) : '';
	}

	/* ---- Analytics: GA4 + Meta Pixel event tracking -------------------- */
	var MKAEA = window.MKAEA || {};
	var MKAE_FB_STD = { add_to_cart: 'AddToCart', whatsapp_click: 'Contact', vehicle_finder: 'Search', search: 'Search', begin_checkout: 'InitiateCheckout' };
	function track(name, params) {
		if (MKAEA.track === 0) return;
		params = params || {};
		try { if (typeof window.gtag === 'function') window.gtag('event', name, params); } catch (e) {}
		try {
			if (typeof window.fbq === 'function') {
				var std = MKAE_FB_STD[name];
				if (std) window.fbq('track', std, params);
				else window.fbq('trackCustom', name, params);
			}
		} catch (e) {}
		try { (window.dataLayer = window.dataLayer || []).push(Object.assign({ event: 'mkae_' + name }, params)); } catch (e) {}
	}
	function waLoc(a) {
		if (a.classList && a.classList.contains('mkae-wa-float')) return 'float';
		if (a.closest('.single-product, .mkae-single')) return 'product';
		if (a.closest('li.product, .mkae-card, .mkae-product-card')) return 'product_card';
		return 'other';
	}
	function clickTracking() {
		document.addEventListener('click', function (e) {
			var t = e.target;
			if (!t || !t.closest) return;
			var wa = t.closest('a[href*="wa.me"], a[href*="api.whatsapp.com"], a[href*="whatsapp.com/send"], .mkae-btn--whatsapp, .mkae-wa-float');
			if (wa) { track('whatsapp_click', { link_url: wa.href || '', location: waLoc(wa) }); return; }
			var co = t.closest('a[href*="/checkout"]');
			if (co) { track('begin_checkout', {}); }
		}, true);
	}
	function formTracking() {
		document.addEventListener('submit', function (e) {
			var f = e.target;
			if (!f || !f.classList) return;
			if (f.classList.contains('mkae-finder')) {
				var v = function (n) { var el = f.querySelector('[name="' + n + '"]'); return el ? el.value : ''; };
				track('vehicle_finder', { make: v('mk_make'), model: v('mk_model'), year: v('mk_year') });
			} else if (f.hasAttribute('data-mkae-search')) {
				var input = f.querySelector('.mkae-search__input');
				track('search', { search_term: input ? input.value.trim() : '' });
			}
		}, true);
	}

	/* ---- Sticky header shrink ------------------------------------------ */
	function stickyHeader() {
		var header = document.querySelector('[data-mkae-sticky]');
		if (!header) return;
		var shrunk = false;
		var onScroll = function () {
			var y = window.scrollY || window.pageYOffset;
			var next = y > 40;
			if (next !== shrunk) {
				shrunk = next;
				header.dataset.shrunk = shrunk ? 'true' : 'false';
			}
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}

	/* ---- Mobile menu toggle -------------------------------------------- */
	function mobileMenu() {
		var toggles = document.querySelectorAll('[data-mkae-menu-toggle]');
		if (!toggles.length) return;
		var header = document.querySelector('.mkae-header');
		var syncExpanded = function (open) {
			toggles.forEach(function (t) { t.setAttribute('aria-expanded', open ? 'true' : 'false'); });
		};
		var setHeaderH = function () {
			if (header) {
				document.documentElement.style.setProperty('--mkae-header-h', header.offsetHeight + 'px');
			}
		};
		toggles.forEach(function (toggle) {
			toggle.addEventListener('click', function () {
				var willOpen = !document.body.classList.contains('mkae-menu-open');
				// Open from the top so the fixed drawer overlay sits cleanly
				// below the sticky header (works even when scrolled down).
				if (willOpen) { window.scrollTo(0, 0); }
				setHeaderH();
				var open = document.body.classList.toggle('mkae-menu-open');
				syncExpanded(open);
				if (open) { requestAnimationFrame(setHeaderH); }
			});
		});
		window.addEventListener('resize', function () {
			if (document.body.classList.contains('mkae-menu-open')) { setHeaderH(); }
		});
	}

	/* ---- Predictive search --------------------------------------------- */
	function predictiveSearch() {
		var forms = document.querySelectorAll('[data-mkae-search]');
		if (!forms.length || !MKAE.ajaxUrl) return;

		var memo = {}; // in-memory cache of query -> results for instant repeats

		forms.forEach(function (form) {
			var input = form.querySelector('.mkae-search__input');
			var box = form.querySelector('.mkae-search__results');
			if (!input || !box) return;

			var timer = null;
			var controller = null;
			var active = -1;

			var close = function () {
				box.hidden = true;
				box.innerHTML = '';
				input.setAttribute('aria-expanded', 'false');
				active = -1;
			};

			var render = function (results) {
				var items = results || [];
				if (!items.length) {
					box.innerHTML = '<p class="mkae-search__empty">' + escapeHtml((MKAE.i18n && MKAE.i18n.noResults) || 'No matches found') + '</p>';
					box.hidden = false;
					return;
				}
				box.innerHTML = items.map(function (r, i) {
					var thumb = r.thumb ? '<img src="' + encodeURI(r.thumb) + '" alt="" loading="lazy">' : '<span class="mkae-search__thumb-ph" aria-hidden="true"></span>';
					var price = r.price ? '<span class="mkae-search__price">' + escapeHtml(r.price) + '</span>' : '';
					return '<a class="mkae-search__result" role="option" id="mkae-opt-' + i + '" href="' +
						encodeURI(r.url) + '">' + thumb + '<span class="mkae-search__result-body"><span class="mkae-search__result-title">' +
						escapeHtml(r.title) + '</span>' + price + '</span></a>';
				}).join('');
				box.hidden = false;
				input.setAttribute('aria-expanded', 'true');
			};

			var query = function (q) {
				if (memo[q]) { render(memo[q]); return; }
				if (controller) controller.abort();
				controller = new AbortController();
				var body = new URLSearchParams();
				body.set('action', 'mkae_search_suggest');
				body.set('nonce', MKAE.nonce || '');
				body.set('q', q);
				fetch(MKAE.ajaxUrl, { method: 'POST', body: body, signal: controller.signal, credentials: 'same-origin' })
					.then(function (res) { return res.json(); })
					.then(function (json) {
						if (json && json.success) {
							memo[q] = json.data.results;
							if (input.value.trim() === q) render(json.data.results);
						}
					})
					.catch(function () { /* aborted or offline: ignore */ });
			};

			input.addEventListener('input', function () {
				var q = input.value.trim();
				window.clearTimeout(timer);
				if (q.length < 2) { close(); return; }
				// Fast path: show cached results instantly, still debounce the network.
				if (memo[q]) render(memo[q]);
				timer = window.setTimeout(function () { query(q); }, 120);
			});

			input.addEventListener('keydown', function (e) {
				if (box.hidden) return;
				var links = box.querySelectorAll('.mkae-search__result');
				if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, links.length - 1); }
				else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); }
				else if (e.key === 'Enter' && active > -1 && links[active]) { window.location.href = links[active].href; }
				else if (e.key === 'Escape') { close(); return; }
				links.forEach(function (l, i) { l.setAttribute('aria-selected', i === active ? 'true' : 'false'); });
				if (links[active]) input.setAttribute('aria-activedescendant', links[active].id);
			});

			document.addEventListener('click', function (e) {
				if (!form.contains(e.target)) close();
			});
		});
	}

	/* ---- Active-vehicle clear ------------------------------------------ */
	function clearVehicle() {
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-mkae-clear-vehicle]');
			if (!btn) return;
			e.preventDefault();
			try { window.localStorage.removeItem('mkae_vehicle'); } catch (err) {}
			document.cookie = 'mkae_vehicle=; path=/; max-age=0';
			window.location.reload();
		});
	}

	/* ---- Shop by Category mega menu (tap to open on touch/mobile) ------- */
	function categoryMenu() {
		var wrap = document.querySelector('[data-mkae-catmenu]');
		if (!wrap) return;
		var toggle = wrap.querySelector('[data-mkae-catmenu-toggle]');
		if (!toggle) return;
		toggle.addEventListener('click', function (e) {
			e.preventDefault();
			var open = wrap.getAttribute('data-open') === 'true';
			wrap.setAttribute('data-open', open ? 'false' : 'true');
			toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
		});
		document.addEventListener('click', function (e) {
			if (!wrap.contains(e.target)) {
				wrap.setAttribute('data-open', 'false');
				toggle.setAttribute('aria-expanded', 'false');
			}
		});
	}

	/* ---- Brand scroller (single-row horizontal scroll) ----------------- */
	function brandScroller() {
		document.querySelectorAll('[data-mkae-scroller]').forEach(function (wrap) {
			var row = wrap.querySelector('[data-mkae-scroll-row]');
			if (!row) return;
			var prev = wrap.querySelector('.mkae-brand-nav--prev');
			var next = wrap.querySelector('.mkae-brand-nav--next');
			var update = function () {
				var max = row.scrollWidth - row.clientWidth - 2;
				var scrollable = max > 2;
				if (prev) prev.hidden = !scrollable || row.scrollLeft <= 2;
				if (next) next.hidden = !scrollable || row.scrollLeft >= max;
			};
			var step = function (dir) {
				row.scrollBy({ left: dir * Math.max(240, row.clientWidth * 0.8), behavior: 'smooth' });
			};
			if (prev) prev.addEventListener('click', function () { step(-1); });
			if (next) next.addEventListener('click', function () { step(1); });
			row.addEventListener('scroll', update, { passive: true });
			window.addEventListener('resize', update, { passive: true });
			update();
		});
	}

	/* ---- Mini-cart drawer + AJAX add-to-cart --------------------------- */
	function miniCart() {
		var drawer = document.querySelector('[data-mkae-cart-drawer]');
		var counts = document.querySelectorAll('.mkae-tool__count');
		if (!MKAE.homeUrl) return;

		var body = drawer ? drawer.querySelector('[data-mkae-cart-body]') : null;
		var foot = drawer ? drawer.querySelector('[data-mkae-cart-foot]') : null;
		var subtotalEl = drawer ? drawer.querySelector('[data-mkae-cart-subtotal]') : null;
		var loaded = false;

		var setCounts = function (n) {
			counts.forEach(function (el) {
				el.textContent = String(n);
				el.hidden = false;
				el.classList.toggle('is-empty', Number(n) <= 0);
			});
		};

		// Keep the header count correct even behind a full-page cache: the
		// WooCommerce cookie holds the live item count.
		var cookieCount = getCookie('woocommerce_items_in_cart');
		if (cookieCount !== '') setCounts(parseInt(cookieCount, 10) || 0);

		var openDrawer = function () {
			if (!drawer) return;
			drawer.classList.add('is-open');
			drawer.setAttribute('aria-hidden', 'false');
			document.body.classList.add('mkae-cart-open');
		};
		var closeDrawer = function () {
			if (!drawer) return;
			drawer.classList.remove('is-open');
			drawer.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('mkae-cart-open');
		};

		var renderCart = function (data) {
			setCounts(data.count || 0);
			if (!body) return;
			if (!data.items || !data.items.length) {
				body.innerHTML = '<p class="mkae-cart-drawer__empty">' + escapeHtml((MKAE.i18n && MKAE.i18n.emptyCart) || 'Your cart is empty.') + '</p>';
				if (foot) foot.hidden = true;
				return;
			}
			body.innerHTML = data.items.map(function (it) {
				var thumb = it.thumb ? '<img src="' + encodeURI(it.thumb) + '" alt="" loading="lazy">' : '';
				return '<div class="mkae-cart-item" data-key="' + escapeHtml(it.key) + '">' +
					'<a class="mkae-cart-item__media" href="' + encodeURI(it.url) + '">' + thumb + '</a>' +
					'<div class="mkae-cart-item__body">' +
					'<a class="mkae-cart-item__name" href="' + encodeURI(it.url) + '">' + escapeHtml(it.name) + '</a>' +
					'<span class="mkae-cart-item__meta">' + escapeHtml(String(it.qty)) + ' &times; <strong>' + escapeHtml(it.line) + '</strong></span>' +
					'</div>' +
					'<button type="button" class="mkae-cart-item__remove" data-mkae-cart-remove="' + escapeHtml(it.key) + '" aria-label="Remove">&times;</button>' +
					'</div>';
			}).join('');
			if (subtotalEl) subtotalEl.innerHTML = data.subtotal || '';
			if (foot) foot.hidden = false;
		};

		var request = function (endpoint, params) {
			var body2 = new URLSearchParams();
			body2.set('nonce', MKAE.nonce || '');
			Object.keys(params || {}).forEach(function (k) { body2.set(k, params[k]); });
			return fetch(wcAjax(endpoint), { method: 'POST', body: body2, credentials: 'same-origin' })
				.then(function (res) { return res.json(); });
		};

		var fetchCart = function () {
			request('mkae_get_cart', {})
				.then(function (json) { if (json && json.success) { renderCart(json.data); loaded = true; } })
				.catch(function () {});
		};

		// Add-to-cart (event-delegated so it covers every product grid).
		document.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-mkae-add]');
			if (!btn) return;
			e.preventDefault();
			if (btn.classList.contains('is-loading')) return;
			var pid = btn.getAttribute('data-product-id');
			if (!pid) return;
			var label = btn.querySelector('.mkae-add-to-cart__text');
			var original = label ? label.textContent : '';
			btn.classList.add('is-loading');
			if (label) label.textContent = (MKAE.i18n && MKAE.i18n.adding) || 'Adding...';
			request('mkae_add_to_cart', { product_id: pid, qty: 1 })
				.then(function (json) {
					btn.classList.remove('is-loading');
					if (json && json.success) {
						renderCart(json.data);
						loaded = true;
						openDrawer();
						track('add_to_cart', { product_id: pid, quantity: 1, currency: 'KES', content_ids: [pid], content_type: 'product', items: [{ item_id: pid, quantity: 1 }] });
						if (label) { label.textContent = (MKAE.i18n && MKAE.i18n.added) || 'Added'; setTimeout(function () { label.textContent = original; }, 1600); }
					} else {
						if (label) label.textContent = original;
						window.alert((json && json.data && json.data.message) || (MKAE.i18n && MKAE.i18n.cartError) || 'Please try again.');
					}
				})
				.catch(function () {
					btn.classList.remove('is-loading');
					if (label) label.textContent = original;
				});
		});

		// Open cart drawer from the header cart tool.
		document.addEventListener('click', function (e) {
			var tool = e.target.closest('[data-mkae-cart]');
			if (!tool || !drawer) return;
			e.preventDefault();
			openDrawer();
			if (!loaded) fetchCart();
		});

		// Remove line item.
		document.addEventListener('click', function (e) {
			var rm = e.target.closest('[data-mkae-cart-remove]');
			if (!rm) return;
			e.preventDefault();
			request('mkae_remove_item', { key: rm.getAttribute('data-mkae-cart-remove') })
				.then(function (json) { if (json && json.success) renderCart(json.data); })
				.catch(function () {});
		});

		// Close controls.
		document.addEventListener('click', function (e) {
			if (e.target.closest('[data-mkae-cart-close]')) { e.preventDefault(); closeDrawer(); }
		});
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') closeDrawer();
		});

		// Hijack the single-product add-to-cart form for simple products so it
		// adds via AJAX and opens the drawer instead of reloading the page.
		var singleForm = document.querySelector('form.cart:not(.variations_form):not(.grouped_form)');
		if (singleForm) {
			singleForm.addEventListener('submit', function (e) {
				var addBtn = singleForm.querySelector('[name="add-to-cart"], button.single_add_to_cart_button');
				var pid = singleForm.querySelector('[name="add-to-cart"]');
				pid = pid ? pid.value : (addBtn ? addBtn.value : '');
				if (!pid) return; // let WooCommerce handle anything unexpected
				var qtyEl = singleForm.querySelector('input.qty');
				var qty = qtyEl ? (parseInt(qtyEl.value, 10) || 1) : 1;
				e.preventDefault();
				var sb = singleForm.querySelector('.single_add_to_cart_button');
				if (sb) sb.classList.add('loading');
				request('mkae_add_to_cart', { product_id: pid, qty: qty })
					.then(function (json) {
						if (sb) sb.classList.remove('loading');
						if (json && json.success) { renderCart(json.data); loaded = true; openDrawer(); track('add_to_cart', { product_id: pid, quantity: qty, currency: 'KES', content_ids: [pid], content_type: 'product', items: [{ item_id: pid, quantity: qty }] }); }
						else { window.alert((json && json.data && json.data.message) || (MKAE.i18n && MKAE.i18n.cartError) || 'Please try again.'); }
					})
					.catch(function () { if (sb) sb.classList.remove('loading'); });
			});
		}
	}

	/* ---- Boot ----------------------------------------------------------- */
	function init() {
		stickyHeader();
		mobileMenu();
		predictiveSearch();
		clearVehicle();
		categoryMenu();
		brandScroller();
		miniCart();
		clickTracking();
		formTracking();
	}

	// expose small utilities for the finder module
	window.MKAEHelpers = { escapeHtml: escapeHtml };

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
