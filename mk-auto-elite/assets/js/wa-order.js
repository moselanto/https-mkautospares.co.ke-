/**
 * MK Auto Elite - WhatsApp order form (2.19.8).
 * Intercepts [data-mkae-order] buttons, collects order details, shows a live
 * total and opens WhatsApp with a formatted order. Saves a lead in the
 * background. Dependency-free; config in window.MKAEOrder.
 */
(function () {
	'use strict';

	var C = window.MKAEOrder || {};
	if (!C.number) { return; }

	var root, form, item, startedAt = 0, lastUrl = '';
	var STORE = 'mkae_wo_contact';

	function q(sel) { return root ? root.querySelector(sel) : null; }
	function qa(sel) { return root ? root.querySelectorAll(sel) : []; }

	function money(n) {
		return (C.currency || 'KSh') + ' ' + Math.round(n).toLocaleString('en-KE');
	}

	function track(name, params) {
		try { if (typeof window.gtag === 'function') { window.gtag('event', name, params || {}); } } catch (e) {}
		try { (window.dataLayer = window.dataLayer || []).push(Object.assign({ event: 'mkae_' + name }, params || {})); } catch (e) {}
	}

	function val(name) {
		var el = form.elements[name];
		if (!el) { return ''; }
		if (el.length && !el.tagName) {
			for (var i = 0; i < el.length; i++) { if (el[i].checked) { return el[i].value; } }
			return '';
		}
		return String(el.value || '').trim();
	}

	function qty() {
		var n = parseInt(form.elements.qty.value, 10);
		if (isNaN(n) || n < 1) { n = 1; }
		if (n > 50) { n = 50; }
		return n;
	}

	function totals() {
		var unit = item && item.price > 0 ? item.price : 0;
		var sub = unit * qty();
		var d = val('delivery');
		var fee = 0;
		if (unit > 0 && d !== 'pickup' && sub < (C.freeOver || 0)) { fee = C.fee || 0; }
		return { unit: unit, sub: sub, fee: fee, total: sub + fee, delivery: d };
	}

	function render() {
		var t = totals();
		var subEl = q('[data-mkwo-subtotal]'), feeEl = q('[data-mkwo-fee]'), totEl = q('[data-mkwo-total]');
		var feeLabels = qa('[data-mkwo-fee-label]');
		var feeText = (t.unit > 0 && t.sub >= (C.freeOver || 0)) ? 'Free' : money(C.fee || 0);
		for (var i = 0; i < feeLabels.length; i++) { feeLabels[i].textContent = feeText; }
		if (t.unit > 0) {
			subEl.textContent = money(t.sub);
			feeEl.textContent = t.delivery === 'pickup' ? 'Free pick-up' : (t.fee ? money(t.fee) : 'Free');
			totEl.textContent = money(t.total);
		} else {
			subEl.textContent = 'Price on request';
			feeEl.textContent = t.delivery === 'pickup' ? 'Free pick-up' : 'Confirmed on WhatsApp';
			totEl.textContent = 'We will quote you';
		}
		var area = q('[data-mkwo-area]');
		if (area) { area.hidden = t.delivery === 'pickup'; }
		var lbl = q('[data-mkwo-submit-label]');
		if (lbl) { lbl.textContent = t.unit > 0 ? 'Send order on WhatsApp \u00b7 ' + money(t.total) : 'Request price on WhatsApp'; }
	}

	function normalisePhone(raw) {
		var d = String(raw || '').replace(/[^\d+]/g, '');
		if (/^\+?254[17]\d{8}$/.test(d)) { return '+' + d.replace(/^\+/, ''); }
		if (/^0[17]\d{8}$/.test(d)) { return '+254' + d.slice(1); }
		if (/^[17]\d{8}$/.test(d)) { return '+254' + d; }
		if (/^\+\d{8,15}$/.test(d)) { return d; }
		return '';
	}

	function showError(msg, field) {
		var e = q('[data-mkwo-error]');
		e.textContent = msg;
		e.hidden = false;
		if (field && form.elements[field] && form.elements[field].focus) {
			form.elements[field].focus();
			if (form.elements[field].classList) { form.elements[field].classList.add('is-invalid'); }
		}
	}

	function ref() {
		var d = new Date();
		var p = function (n) { return (n < 10 ? '0' : '') + n; };
		var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', r = '';
		for (var i = 0; i < 4; i++) { r += chars.charAt(Math.floor(Math.random() * chars.length)); }
		return 'MK-' + String(d.getFullYear()).slice(2) + p(d.getMonth() + 1) + p(d.getDate()) + '-' + r;
	}

	function deliveryLabel(d) {
		if (d === 'pickup') { return 'Pick-up at our shop (' + (C.pickup || 'Nairobi') + ')'; }
		if (d === 'countrywide') { return 'Countrywide delivery'; }
		return 'Delivery within Nairobi';
	}

	function message(r, t, phone) {
		var L = [];
		L.push('*New order - ' + (C.brand || 'MK Auto Spares') + '*');
		L.push('Order ref: ' + r);
		L.push('');
		L.push('*Item*');
		L.push(item.name);
		if (item.sku) { L.push('Part ref: ' + item.sku); }
		if (item.condition) { L.push('Condition: ' + item.condition); }
		if (t.unit > 0) {
			L.push('Qty: ' + qty() + ' x ' + money(t.unit));
			L.push('');
			L.push('Subtotal: ' + money(t.sub));
			L.push('Delivery: ' + (t.delivery === 'pickup' ? 'Free pick-up' : (t.fee ? money(t.fee) : 'Free')));
			L.push('*Total: ' + money(t.total) + '*');
		} else {
			L.push('Qty: ' + qty());
			L.push('Price: please send me the price and availability');
		}
		L.push('');
		L.push('*Customer*');
		L.push('Name: ' + val('name'));
		L.push('Phone: ' + phone);
		if (val('vehicle')) { L.push('Vehicle: ' + val('vehicle')); }
		if (val('chassis')) { L.push('Chassis/VIN: ' + val('chassis').toUpperCase()); }
		L.push('');
		L.push('*Delivery and payment*');
		L.push(deliveryLabel(t.delivery));
		if (t.delivery !== 'pickup' && val('area')) { L.push('Location: ' + val('area')); }
		L.push('Payment: ' + val('payment'));
		if (val('payment') === 'M-Pesa' && C.mpesa) { L.push(C.mpesa + ' (I will pay once you confirm availability)'); }
		if (val('notes')) { L.push('Notes: ' + val('notes')); }
		L.push('');
		L.push('Product link: ' + item.url);
		return L.join('\n');
	}

	function saveLead(r, phone) {
		try {
			var fd = new FormData();
			fd.append('action', 'mkae_wa_order');
			fd.append('product', item.id);
			fd.append('ref', r);
			fd.append('qty', qty());
			fd.append('phone', phone);
			['name', 'vehicle', 'chassis', 'delivery', 'area', 'payment', 'notes', 'website'].forEach(function (k) { fd.append(k, val(k)); });
			fd.append('elapsed', Math.round((Date.now() - startedAt) / 1000));
			fd.append('page', window.location.href);
			if (navigator.sendBeacon) { navigator.sendBeacon(C.ajaxUrl, fd); }
			else { fetch(C.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true }); }
		} catch (e) {}
	}

	function remember() {
		try {
			localStorage.setItem(STORE, JSON.stringify({ name: val('name'), phone: val('phone'), vehicle: val('vehicle'), area: val('area') }));
		} catch (e) {}
	}

	function restore() {
		try {
			var s = JSON.parse(localStorage.getItem(STORE) || '{}');
			['name', 'phone', 'vehicle', 'area'].forEach(function (k) { if (s[k] && !form.elements[k].value) { form.elements[k].value = s[k]; } });
		} catch (e) {}
	}

	function openWhatsApp(url) {
		var w = null;
		if (!window.matchMedia || !window.matchMedia('(pointer: coarse)').matches) {
			w = window.open(url, '_blank', 'noopener');
		}
		if (!w) { window.location.href = url; }
	}

	function submit(e) {
		e.preventDefault();
		var errs = form.querySelectorAll('.is-invalid');
		for (var i = 0; i < errs.length; i++) { errs[i].classList.remove('is-invalid'); }
		q('[data-mkwo-error]').hidden = true;
		if (!val('name')) { showError('Please enter your name.', 'name'); return; }
		var phone = normalisePhone(val('phone'));
		if (!phone) { showError('Please enter a valid phone number, e.g. 0712 345 678.', 'phone'); return; }
		var t = totals();
		if (t.delivery !== 'pickup' && !val('area')) { showError('Please tell us your town or area for delivery.', 'area'); return; }

		var r = ref();
		var url = 'https://wa.me/' + C.number + '?text=' + encodeURIComponent(message(r, t, phone));
		lastUrl = url;
		remember();
		saveLead(r, phone);
		track('whatsapp_click', { location: 'order_form', value: t.total, currency: 'KES', item_id: item.sku || String(item.id), item_name: item.name, quantity: qty() });
		try { if (typeof window.fbq === 'function') { window.fbq('track', 'Lead', { value: t.total, currency: 'KES', content_name: item.name }); } } catch (er) {}
		openWhatsApp(url);

		form.hidden = true;
		q('[data-mkwo-done]').hidden = false;
		q('[data-mkwo-ref]').textContent = 'Order ref: ' + r + (t.unit > 0 ? ' \u00b7 Total ' + money(t.total) : '');
		q('[data-mkwo-again]').setAttribute('href', url);
	}

	function open(data) {
		item = data;
		form.hidden = false;
		q('[data-mkwo-done]').hidden = true;
		q('[data-mkwo-error]').hidden = true;
		form.elements.qty.value = 1;
		q('[data-mkwo-heading]').textContent = item.quote ? 'Request a price on WhatsApp' : 'Order on WhatsApp';
		q('[data-mkwo-name]').textContent = item.name;
		var meta = [];
		if (item.sku) { meta.push('Ref ' + item.sku); }
		if (item.condition) { meta.push(item.condition); }
		meta.push(item.inStock === false ? 'Check availability' : 'In stock');
		q('[data-mkwo-meta]').textContent = meta.join(' \u00b7 ');
		q('[data-mkwo-unit]').textContent = item.price > 0 ? money(item.price) : 'Price on request';
		var img = q('[data-mkwo-img]');
		if (item.image) { img.src = item.image; img.hidden = false; } else { img.hidden = true; }
		var pick = q('[data-mkwo-pickup]');
		if (pick) { pick.textContent = C.pickup || ''; }
		restore();
		if (!form.elements.vehicle.value && item.vehicle) { form.elements.vehicle.placeholder = 'e.g. ' + item.vehicle + ' 2015'; }
		render();
		root.hidden = false;
		document.documentElement.classList.add('mkwo-lock');
		startedAt = Date.now();
		setTimeout(function () { var f = form.elements.name.value ? form.elements.phone : form.elements.name; if (f && window.innerWidth > 640) { f.focus(); } }, 60);
		track('whatsapp_order_start', { item_id: item.sku || String(item.id), item_name: item.name, value: item.price || 0, currency: 'KES' });
	}

	function close() {
		if (!root) { return; }
		root.hidden = true;
		document.documentElement.classList.remove('mkwo-lock');
	}

	function init() {
		root = document.querySelector('[data-mkwo]');
		if (!root) { return; }
		form = q('[data-mkwo-form]');

		// Window capture runs before the theme's document-level click tracking,
		// so opening the form is not counted as a WhatsApp lead; submitting is.
		window.addEventListener('click', function (e) {
			var t = e.target && e.target.closest ? e.target.closest('[data-mkae-order]') : null;
			if (!t) { return; }
			var data;
			try { data = JSON.parse(t.getAttribute('data-mkae-order')); } catch (er) { return; }
			if (!data || !data.id) { return; }
			e.preventDefault();
			e.stopPropagation();
			open(data);
		}, true);

		var closers = qa('[data-mkwo-close]');
		for (var i = 0; i < closers.length; i++) { closers[i].addEventListener('click', close); }
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !root.hidden) { close(); } });

		var steps = qa('[data-mkwo-qty]');
		for (var j = 0; j < steps.length; j++) {
			steps[j].addEventListener('click', function () {
				form.elements.qty.value = Math.max(1, Math.min(50, qty() + parseInt(this.getAttribute('data-mkwo-qty'), 10)));
				render();
			});
		}
		form.addEventListener('input', render);
		form.addEventListener('change', render);
		form.addEventListener('submit', submit);
		q('[data-mkwo-again]').addEventListener('click', function (e) { if (lastUrl) { e.preventDefault(); openWhatsApp(lastUrl); } });
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
