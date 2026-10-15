/* MK Auto Elite - contact page: WhatsApp enquiry form + live opening status. */
(function () {
	'use strict';
	function ready(fn) { if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }

	function nairobiNow() {
		var parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Africa/Nairobi', weekday: 'short', hour: 'numeric', minute: 'numeric', hour12: false }).formatToParts(new Date());
		var o = {};
		parts.forEach(function (p) { o[p.type] = p.value; });
		var map = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
		return { day: map[o.weekday], hour: parseInt(o.hour, 10) % 24, minute: parseInt(o.minute, 10) };
	}

	function status() {
		var el = document.querySelector('[data-mkc-status]');
		if (!el) { return; }
		var open = parseInt(el.getAttribute('data-open'), 10);
		var close = parseInt(el.getAttribute('data-close'), 10);
		var days = el.getAttribute('data-days').split(',').map(Number);
		var t = nairobiNow();
		var mins = t.hour * 60 + t.minute;
		var isOpen = days.indexOf(t.day) > -1 && mins >= open * 60 && mins < close * 60;
		var txt = el.querySelector('[data-mkc-status-text]');
		el.classList.toggle('is-open', isOpen);
		el.classList.toggle('is-closed', !isOpen);
		if (txt) {
			txt.textContent = isOpen
				? 'Open now - closes ' + (close > 12 ? (close - 12) + ':00pm' : close + ':00am') + '. Typically replies in minutes.'
				: 'Closed now - opens ' + open + ':00am ' + (days.indexOf(t.day) > -1 && mins < open * 60 ? 'today' : (days.indexOf((t.day + 1) % 7) > -1 ? 'tomorrow' : 'Monday')) + '. Send your enquiry and we reply first thing.';
		}
	}

	function ref() {
		var d = new Date();
		var p = function (n) { return (n < 10 ? '0' : '') + n; };
		var r = Math.random().toString(36).slice(2, 6).toUpperCase();
		return 'MK-C-' + String(d.getFullYear()).slice(2) + p(d.getMonth() + 1) + p(d.getDate()) + '-' + r;
	}

	function validPhone(v) {
		var s = v.replace(/[\s\-()]/g, '');
		return /^(\+?254|0)?(7|1)\d{8}$/.test(s) || /^\+\d{8,15}$/.test(s);
	}

	function form() {
		var f = document.querySelector('[data-mkc-form]');
		if (!f) { return; }
		var err = f.querySelector('[data-mkc-error]');
		var vehicle = f.querySelector('[data-mkc-vehicle]');
		var orderField = f.querySelector('[data-mkc-order]');
		var started = Date.now();

		function topicKey() {
			var r = f.querySelector('input[name="topic"]:checked');
			return r ? r.getAttribute('data-key') : 'other';
		}
		function syncTopic() {
			var k = topicKey();
			if (orderField) { orderField.hidden = !(k === 'order' || k === 'return'); }
			if (vehicle) { vehicle.hidden = (k === 'order'); }
		}
		f.addEventListener('change', function (e) { if (e.target.name === 'topic') { syncTopic(); } });
		syncTopic();

		function val(n) { var el = f.elements[n]; return el ? String(el.value || '').trim() : ''; }

		function build() {
			var r = f.querySelector('input[name="topic"]:checked');
			var lines = [];
			var reference = ref();
			lines.push('*New enquiry - MK Auto Spares*');
			lines.push('Ref: ' + reference);
			lines.push('Topic: ' + (r ? r.value : 'General'));
			lines.push('');
			lines.push('Name: ' + val('name'));
			lines.push('Phone: ' + val('phone'));
			if (val('order')) { lines.push('Order no.: ' + val('order')); }
			var car = [val('make'), val('model'), val('year')].filter(Boolean).join(' ');
			if (car && (!vehicle || !vehicle.hidden)) { lines.push('Vehicle: ' + car); }
			if (val('chassis') && (!vehicle || !vehicle.hidden)) { lines.push('Chassis/VIN: ' + val('chassis').toUpperCase()); }
			lines.push('');
			lines.push(val('message'));
			lines.push('');
			lines.push('Sent from ' + window.location.host + '/contact');
			return { text: lines.join('\n'), ref: reference };
		}

		function check() {
			var problems = [];
			if (val('name').length < 2) { problems.push('your name'); }
			if (!validPhone(val('phone'))) { problems.push('a valid phone number'); }
			if (val('message').length < 3) { problems.push('a short message'); }
			['name', 'phone', 'message'].forEach(function (n) {
				var el = f.elements[n];
				if (el) { el.setAttribute('aria-invalid', problems.length && ((n === 'name' && val('name').length < 2) || (n === 'phone' && !validPhone(val('phone'))) || (n === 'message' && val('message').length < 3)) ? 'true' : 'false'); }
			});
			if (problems.length) {
				err.textContent = 'Please add ' + problems.join(', ') + '.';
				err.hidden = false;
				var bad = f.querySelector('[aria-invalid="true"]');
				if (bad) { bad.focus(); }
				return false;
			}
			err.hidden = true;
			return true;
		}

		function spam() { return val('website') !== '' || (Date.now() - started) < 2500; }

		function track(method, reference) {
			if (typeof window.gtag === 'function') {
				window.gtag('event', 'generate_lead', { method: method, lead_source: 'contact_form', topic: topicKey(), reference: reference });
			}
			if (typeof window.fbq === 'function') { window.fbq('track', 'Contact'); }
			if (window.dataLayer) { window.dataLayer.push({ event: 'contact_form_submit', method: method, topic: topicKey() }); }
		}

		f.addEventListener('submit', function (e) {
			e.preventDefault();
			if (!check() || spam()) { return; }
			var m = build();
			track('whatsapp', m.ref);
			var url = 'https://wa.me/' + f.getAttribute('data-wa') + '?text=' + encodeURIComponent(m.text);
			var w = window.open(url, '_blank', 'noopener');
			if (!w) { window.location.href = url; }
			f.classList.add('is-sent');
			err.hidden = false;
			err.classList.add('is-ok');
			err.textContent = 'WhatsApp opened with your message (ref ' + m.ref + '). Just press Send.';
		});

		var emailBtn = f.querySelector('[data-mkc-email]');
		if (emailBtn) {
			emailBtn.addEventListener('click', function () {
				if (!check() || spam()) { return; }
				var m = build();
				track('email', m.ref);
				window.location.href = 'mailto:' + f.getAttribute('data-email') + '?subject=' + encodeURIComponent('Enquiry ' + m.ref) + '&body=' + encodeURIComponent(m.text.replace(/\*/g, ''));
			});
		}
	}

	ready(function () { status(); form(); });
}());
