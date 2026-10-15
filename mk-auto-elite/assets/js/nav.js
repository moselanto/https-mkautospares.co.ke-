/* MK Auto Elite v2.19.9 - mobile drawer, labelled menu button and bottom tab bar. */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
	}

	ready(function () {
		var drawer = document.querySelector('[data-mkd]');
		var openers = document.querySelectorAll('[data-mkd-open]');
		var panel = drawer ? drawer.querySelector('.mkd__panel') : null;
		var lastFocus = null;
		var closeTimer = null;

		function track(label) {
			if (typeof window.gtag === 'function') {
				window.gtag('event', 'mobile_nav', { nav_item: label });
			}
		}

		function setExpanded(v) {
			openers.forEach(function (b) { b.setAttribute('aria-expanded', v ? 'true' : 'false'); });
		}

		function isOpen() { return drawer && drawer.classList.contains('is-open'); }

		function open() {
			if (!drawer) { return; }
			clearTimeout(closeTimer);
			lastFocus = document.activeElement;
			drawer.hidden = false;
			document.documentElement.classList.add('mkd-lock');
			document.body.classList.add('mkd-open');
			setExpanded(true);
			requestAnimationFrame(function () {
				requestAnimationFrame(function () {
					drawer.classList.add('is-open');
					var c = drawer.querySelector('.mkd__close');
					if (c) { c.focus({ preventScroll: true }); }
				});
			});
			track('open_menu');
		}

		function close(restore) {
			if (!isOpen()) { return; }
			drawer.classList.remove('is-open');
			document.documentElement.classList.remove('mkd-lock');
			document.body.classList.remove('mkd-open');
			setExpanded(false);
			closeTimer = setTimeout(function () { drawer.hidden = true; }, 300);
			if (restore !== false && lastFocus && lastFocus.focus) { lastFocus.focus({ preventScroll: true }); }
		}

		openers.forEach(function (b) {
			b.addEventListener('click', function (e) {
				e.preventDefault();
				if (isOpen()) { close(); } else { open(); }
			});
		});

		if (drawer) {
			drawer.addEventListener('click', function (e) {
				if (e.target.closest('[data-mkd-close]')) { close(); return; }
				var acc = e.target.closest('[data-mkd-acc]');
				if (acc) {
					var sub = document.getElementById(acc.getAttribute('aria-controls'));
					var exp = acc.getAttribute('aria-expanded') === 'true';
					acc.setAttribute('aria-expanded', exp ? 'false' : 'true');
					if (sub) { sub.hidden = exp; }
					return;
				}
				var a = e.target.closest('a[href]');
				if (a) {
					track((a.textContent || '').trim().slice(0, 40));
					if (a.hasAttribute('data-mkd-finder') && document.querySelector('.mkae-finder')) {
						e.preventDefault();
						close(false);
						scrollToFinder();
					} else if (a.getAttribute('target') !== '_blank') {
						setTimeout(function () { close(false); }, 120);
					}
				}
			});

			document.addEventListener('keydown', function (e) {
				if (!isOpen()) { return; }
				if (e.key === 'Escape') { close(); return; }
				if (e.key !== 'Tab' || !panel) { return; }
				var f = panel.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
				var list = Array.prototype.filter.call(f, function (el) { return el.offsetParent !== null; });
				if (!list.length) { return; }
				var first = list[0];
				var last = list[list.length - 1];
				if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
				else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
			});

			// Swipe left to close.
			var sx = null;
			var sy = null;
			if (panel) {
				panel.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; sy = e.touches[0].clientY; }, { passive: true });
				panel.addEventListener('touchend', function (e) {
					if (sx === null) { return; }
					var dx = e.changedTouches[0].clientX - sx;
					var dy = e.changedTouches[0].clientY - sy;
					if (dx < -70 && Math.abs(dy) < 60) { close(); }
					sx = null;
				}, { passive: true });
			}

			window.addEventListener('resize', function () {
				if (window.innerWidth >= 1024) { close(false); }
			});
		}

		function scrollToFinder() {
			var f = document.querySelector('.mkae-finder');
			if (!f) { return; }
			var header = document.querySelector('.mkae-header');
			var off = header ? header.offsetHeight + 12 : 80;
			window.scrollTo({ top: f.getBoundingClientRect().top + window.pageYOffset - off, behavior: 'smooth' });
			var sel = f.querySelector('select, input');
			if (sel) { setTimeout(function () { sel.focus({ preventScroll: true }); }, 450); }
		}

		if (window.location.hash === '#find-parts') {
			setTimeout(scrollToFinder, 250);
		}

		// Search tab: jump to the header search box and open the keyboard.
		document.querySelectorAll('[data-mkd-search]').forEach(function (b) {
			b.addEventListener('click', function (e) {
				var input = document.querySelector('.mkae-header .mkae-search__input') || document.querySelector('.mkae-header input[type="search"]');
				if (!input) { return; }
				e.preventDefault();
				close(false);
				input.focus({ preventScroll: true });
				window.scrollTo({ top: 0, behavior: 'smooth' });
				var wrap = input.closest('.mkae-search');
				if (wrap) {
					wrap.classList.add('is-flash');
					setTimeout(function () { wrap.classList.remove('is-flash'); }, 1200);
				}
				track('search_tab');
			});
		});
	});
}());
