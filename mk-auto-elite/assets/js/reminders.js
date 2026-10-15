/* MK Auto Elite - guest cart reminder capture at checkout (only with consent). */
(function () {
	'use strict';
	var C = window.MKAERemind || {};
	if (!C.ajaxUrl) { return; }
	var timer = null, lastSent = '';
	function field(sel) { return document.querySelector(sel); }
	function val(list) {
		for (var i = 0; i < list.length; i++) { var el = field(list[i]); if (el && el.value) { return String(el.value).trim(); } }
		return '';
	}
	function send() {
		var box = field('[data-mkrm-consent]');
		if (!box || !box.checked) { return; }
		var email = val(['#billing_email', '#email', 'input[type="email"][autocomplete="email"]', 'input[type="email"]']);
		if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { return; }
		var phone = val(['#billing_phone', '#billing-phone', '#shipping-phone', 'input[type="tel"]']);
		var name = val(['#billing_first_name', '#billing-first_name', '#shipping-first_name']);
		var key = email + '|' + phone;
		if (key === lastSent) { return; }
		lastSent = key;
		var b = new URLSearchParams();
		b.set('action', 'mkae_capture'); b.set('nonce', C.nonce); b.set('email', email); b.set('phone', phone); b.set('name', name); b.set('consent', '1');
		fetch(C.ajaxUrl, { method: 'POST', body: b, credentials: 'same-origin' }).catch(function () {});
	}
	function later() { clearTimeout(timer); timer = setTimeout(send, 1200); }
	document.addEventListener('input', function (e) { if (e.target && (e.target.type === 'email' || e.target.type === 'tel')) { later(); } }, true);
	document.addEventListener('change', function (e) { if (e.target && (e.target.matches('[data-mkrm-consent]') || e.target.type === 'email' || e.target.type === 'tel')) { later(); } }, true);
})();
