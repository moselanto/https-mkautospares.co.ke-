/* MKauto Desk - Garage & Service booking form (1.2.0). */
(function () {
	'use strict';
	var C = window.MKD_GARAGE || {}, d = document;
	var form = d.querySelector('[data-mkg-form]');
	if (!form) { return; }
	var t0 = Date.now();
	var $date = form.querySelector('[data-mkg-date]'), $slots = form.querySelector('[data-mkg-slots]'), $time = form.querySelector('[data-mkg-time]');
	var $err = form.querySelector('[data-mkg-error]'), $btn = form.querySelector('[data-mkg-submit]'), $svc = form.querySelector('[data-mkg-service]');
	var $loc = form.querySelector('[data-mkg-loc]');

	function err(msg, field) {
		form.querySelectorAll('.is-bad').forEach(function (el) { el.classList.remove('is-bad'); });
		if (!msg) { $err.hidden = true; return; }
		$err.textContent = msg; $err.hidden = false;
		if (field) { var w = field.closest('.mkg-f'); if (w) { w.classList.add('is-bad'); } field.focus({ preventScroll: true }); }
		$err.scrollIntoView({ behavior: 'smooth', block: 'center' });
	}
	function label(t) {
		var p = t.split(':'), h = +p[0], m = p[1];
		return ((h % 12) || 12) + ':' + m + (h < 12 ? ' am' : ' pm');
	}
	function today() { var n = new Date(); return n.getFullYear() + '-' + String(n.getMonth() + 1).padStart(2, '0') + '-' + String(n.getDate()).padStart(2, '0'); }

	/* Service cards preselect the service and bring the form into view. */
	d.querySelectorAll('[data-mkg-pick]').forEach(function (a) {
		a.addEventListener('click', function (e) {
			e.preventDefault();
			$svc.value = a.getAttribute('data-mkg-pick');
			var card = d.getElementById('mkg-book');
			card.scrollIntoView({ behavior: 'smooth', block: 'start' });
			setTimeout(function () { var v = form.elements.vehicle; (v.value ? form.elements.date : v).focus({ preventScroll: true }); }, 450);
		});
	});

	/* Mobile: show a sticky "Book a service" button whenever the form is off screen. */
	var sticky = d.querySelector('[data-mkg-sticky]'), card = d.getElementById('mkg-book');
	if (sticky && card && 'IntersectionObserver' in window) {
		sticky.hidden = false;
		new IntersectionObserver(function (en) { sticky.classList.toggle('is-on', !en[0].isIntersecting); }, { threshold: 0.05 }).observe(card);
		sticky.addEventListener('click', function (e) { e.preventDefault(); card.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
	}

	/* Garage vs mobile mechanic. */
	form.querySelectorAll('input[name=type]').forEach(function (r) {
		r.addEventListener('change', function () { $loc.hidden = form.querySelector('input[name=type]:checked').value !== 'video'; });
	});

	/* Time slots from the Desk calendar (opening days, hours and capacity). */
	$date.addEventListener('change', function () {
		$time.value = '';
		var v = $date.value;
		if (!v) { $slots.innerHTML = '<p class="mkg-hint">Pick a date to see available times.</p>'; return; }
		$slots.innerHTML = '<p class="mkg-hint">Checking available times...</p>';
		fetch(C.slots + (C.slots.indexOf('?') > -1 ? '&' : '?') + 'date=' + encodeURIComponent(v), { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (r) {
				if (!r || r.closed || !r.slots || !r.slots.length) { $slots.innerHTML = '<p class="mkg-hint">We are closed on this day. Please choose another date.</p>'; return; }
				var now = new Date(), isToday = v === today(), hm = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
				var open = 0;
				$slots.innerHTML = r.slots.map(function (s) {
					var off = (r.full || []).indexOf(s) > -1 || (isToday && s <= hm);
					if (!off) { open++; }
					return '<button type="button" class="mkg-slot" data-t="' + s + '"' + (off ? ' disabled aria-disabled="true"' : '') + '>' + label(s) + '</button>';
				}).join('');
				if (!open) { $slots.innerHTML = '<p class="mkg-hint">This day is fully booked. Please choose another date.</p>'; }
			})
			.catch(function () { $slots.innerHTML = '<p class="mkg-hint">Could not load times. Please try again.</p>'; });
	});
	$slots.addEventListener('click', function (e) {
		var b = e.target.closest('.mkg-slot');
		if (!b || b.disabled) { return; }
		$slots.querySelectorAll('.mkg-slot').forEach(function (x) { x.classList.remove('is-on'); x.setAttribute('aria-pressed', 'false'); });
		b.classList.add('is-on'); b.setAttribute('aria-pressed', 'true');
		$time.value = b.getAttribute('data-t');
		err('');
	});

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var f = form.elements;
		if (f.vehicle.value.trim().length < 2) { return err('Please enter the make, model and year of your vehicle.', f.vehicle); }
		if (!f.service.value) { return err('Please choose a service.', f.service); }
		if (form.querySelector('input[name=type]:checked').value === 'video' && f.location.value.trim().length < 3) { return err('Please tell us where the mobile mechanic should come.', f.location); }
		if (!f.date.value) { return err('Please choose a date.', f.date); }
		if (!$time.value) { return err('Please choose a time.'); }
		if (f.name.value.trim().length < 2) { return err('Please enter your name.', f.name); }
		if (f.phone.value.replace(/\D/g, '').length < 9) { return err('Please enter a valid phone number, e.g. 0712 345 678.', f.phone); }
		err('');

		var mobile = window.matchMedia('(max-width: 768px)').matches;
		var win = mobile ? null : window.open('', '_blank');
		var fd = new FormData(form);
		fd.append('action', 'mkd_garage_book');
		fd.append('nonce', C.nonce || '');
		fd.append('elapsed', String(Math.round((Date.now() - t0) / 1000)));
		$btn.disabled = true;
		var $lbl = $btn.querySelector('span'), old = $lbl.textContent;
		$lbl.textContent = 'Saving your booking...';

		fetch(C.ajax, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (r) {
				if (!r || !r.success) {
					if (win) { win.close(); }
					err((r && r.data && r.data.message) || 'Something went wrong. Please try again or message us on WhatsApp.');
					if (r && r.data && /time/i.test(r.data.message || '')) { $date.dispatchEvent(new Event('change')); }
					return;
				}
				var done = d.querySelector('[data-mkg-done]');
				done.querySelector('[data-mkg-ref]').textContent = r.data.ref;
				done.querySelector('[data-mkg-summary]').textContent = r.data.summary || '';
				var wa = done.querySelector('[data-mkg-wa]');
				wa.href = r.data.wa || '#';
				form.hidden = true; done.hidden = false;
				done.scrollIntoView({ behavior: 'smooth', block: 'center' });
				if (r.data.wa) {
					if (win) { win.location.href = r.data.wa; } else { window.location.href = r.data.wa; }
				} else if (win) { win.close(); }
			})
			.catch(function () { if (win) { win.close(); } err('Connection problem. Please try again or message us on WhatsApp.'); })
			.then(function () { $btn.disabled = false; $lbl.textContent = old; });
	});
})();
