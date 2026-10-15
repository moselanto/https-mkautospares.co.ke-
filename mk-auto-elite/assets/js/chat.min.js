/**
 * MK Auto Elite - WhatsApp chat widget (redesigned 2.19.8).
 * Floating launcher, timed teaser, business-hours status, quick topics that
 * open WhatsApp with a prefilled message, and a composer that either talks to
 * the AI assistant (mkae_chat AJAX, when configured) or sends the typed text
 * straight to WhatsApp. Dependency-free; reads window.MKAE and window.MKAEChat.
 */
(function () {
	'use strict';

	var C = window.MKAEChat || {};
	if (!C.enabled) { return; }
	var base = window.MKAE || {};
	var i18n = C.i18n || {};
	var SEEN = 'mkw_seen', TEASED = 'mkw_teased';

	var root, panel, log, input, form, handoff, teaser, badge;
	var messages = [], busy = false, opened = false, teaserTimer = null;

	function q(s) { return root.querySelector(s); }
	function ss(k, v) { try { if (v === undefined) { return sessionStorage.getItem(k); } sessionStorage.setItem(k, v); } catch (e) { return null; } return null; }

	function waUrl(text) {
		return 'https://wa.me/' + String(C.whatsapp || '').replace(/\D/g, '') + '?text=' + encodeURIComponent(text);
	}

	function withContext(text) {
		if (C.context) { return text + '\n\nProduct: ' + C.context + '\n' + (C.pageUrl || window.location.href); }
		return text;
	}

	function nowParts() {
		var s = C.schedule || {};
		try {
			var f = new Intl.DateTimeFormat('en-GB', { timeZone: s.tz || 'Africa/Nairobi', weekday: 'short', hour: 'numeric', hour12: false });
			var parts = f.formatToParts(new Date()), wd = '', hr = 0;
			parts.forEach(function (p) { if (p.type === 'weekday') { wd = p.value; } if (p.type === 'hour') { hr = parseInt(p.value, 10); } });
			var map = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
			return { day: map[wd], hour: hr % 24 };
		} catch (e) {
			var d = new Date();
			return { day: d.getDay(), hour: d.getHours() };
		}
	}

	function isOpen() {
		var s = C.schedule || {}, n = nowParts();
		return (s.days || []).indexOf(n.day) !== -1 && n.hour >= (s.open || 8) && n.hour < (s.close || 18);
	}

	function setStatus() {
		var open = isOpen();
		var st = q('[data-mkw-status]'), dot = q('[data-mkw-dot]');
		if (st) { st.textContent = open ? 'Online \u00b7 typically replies in minutes' : 'Away \u00b7 we reply from ' + ((C.schedule || {}).open || 8) + ':00am'; }
		if (dot) { dot.classList.toggle('is-away', !open); }
	}

	function stamp() {
		var d = new Date();
		return (d.getHours() < 10 ? '0' : '') + d.getHours() + ':' + (d.getMinutes() < 10 ? '0' : '') + d.getMinutes();
	}

	function addMessage(role, text, typing) {
		var wrap = document.createElement('div');
		wrap.className = 'mkw__msg mkw__msg--' + (role === 'user' ? 'user' : 'bot') + (typing ? ' is-typing' : '');
		var p = document.createElement('p');
		p.textContent = text;
		wrap.appendChild(p);
		if (!typing) { var t = document.createElement('time'); t.textContent = stamp(); wrap.appendChild(t); }
		log.appendChild(wrap);
		log.scrollTop = log.scrollHeight;
		return wrap;
	}

	function transcript() {
		var lines = ['Hello ' + (C.brand || 'MK Auto Spares') + ', I was chatting on your website:'];
		messages.forEach(function (m) { lines.push((m.role === 'user' ? 'Me: ' : 'Assistant: ') + m.text); });
		var text = withContext(lines.join('\n'));
		return text.length > 1400 ? text.slice(text.length - 1400) : text;
	}

	function refreshHandoff() {
		if (!handoff) { return; }
		var hasUser = messages.some(function (m) { return m.role === 'user'; });
		handoff.hidden = !hasUser;
		handoff.setAttribute('href', waUrl(transcript()));
	}

	function sendToAi(text) {
		if (busy) { return; }
		messages.push({ role: 'user', text: text });
		addMessage('user', text);
		refreshHandoff();
		busy = true;
		var typing = addMessage('bot', i18n.thinking || 'Typing...', true);
		var fd = new FormData();
		fd.append('action', 'mkae_chat');
		fd.append('nonce', base.nonce || '');
		fd.append('messages', JSON.stringify(messages.slice(-14)));
		fetch(base.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: fd })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (typing.parentNode) { typing.parentNode.removeChild(typing); }
				if (res && res.success && res.data && res.data.reply) {
					messages.push({ role: 'model', text: res.data.reply });
					addMessage('bot', res.data.reply);
				} else {
					addMessage('bot', (res && res.data && res.data.message) || i18n.error || 'Please continue on WhatsApp.');
				}
				refreshHandoff();
			})
			.catch(function () {
				if (typing.parentNode) { typing.parentNode.removeChild(typing); }
				addMessage('bot', i18n.error || 'Please continue on WhatsApp.');
			})
			.then(function () { busy = false; });
	}

	function openWa(text) {
		var url = waUrl(text);
		var w = null;
		if (!window.matchMedia || !window.matchMedia('(pointer: coarse)').matches) { w = window.open(url, '_blank', 'noopener'); }
		if (!w) { window.location.href = url; }
	}

	function hideTeaser() {
		if (teaserTimer) { clearTimeout(teaserTimer); teaserTimer = null; }
		if (teaser) { teaser.hidden = true; }
	}

	function setOpen(state) {
		panel.hidden = !state;
		root.classList.toggle('is-open', state);
		var toggles = root.querySelectorAll('[data-mkae-chat-toggle]');
		for (var i = 0; i < toggles.length; i++) { toggles[i].setAttribute('aria-expanded', state ? 'true' : 'false'); }
		hideTeaser();
		if (state) {
			ss(SEEN, '1');
			if (badge) { badge.hidden = true; }
			setStatus();
			if (!opened) {
				opened = true;
				q('[data-mkw-time]').textContent = stamp();
			}
			if (input && window.innerWidth > 640) { input.focus(); }
			try { if (typeof window.gtag === 'function') { window.gtag('event', 'chat_open', {}); } } catch (e) {}
		}
	}

	function init() {
		root = document.querySelector('[data-mkae-chat]');
		if (!root) { return; }
		root.hidden = false;
		panel = q('[data-mkae-chat-panel]');
		log = q('[data-mkae-chat-log]');
		input = q('[data-mkae-chat-input]');
		form = q('[data-mkae-chat-form]');
		handoff = q('[data-mkae-chat-whatsapp]');
		teaser = q('[data-mkw-teaser]');
		badge = q('[data-mkw-badge]');

		q('[data-mkw-intro-text]').textContent = C.intro || 'Hi! How can we help you today?';
		if (C.context) {
			q('[data-mkw-context]').hidden = false;
			q('[data-mkw-context-name]').textContent = C.context;
		}
		if (ss(SEEN) && badge) { badge.hidden = true; }

		var topics = root.querySelectorAll('[data-mkw-topic]');
		for (var i = 0; i < topics.length; i++) {
			topics[i].setAttribute('href', waUrl(withContext(topics[i].getAttribute('data-mkw-topic'))));
		}

		var toggles = root.querySelectorAll('[data-mkae-chat-toggle]');
		for (var j = 0; j < toggles.length; j++) {
			toggles[j].addEventListener('click', function (e) { e.preventDefault(); setOpen(panel.hidden); });
		}
		var tx = q('[data-mkw-teaser-close]');
		if (tx) { tx.addEventListener('click', function () { hideTeaser(); ss(TEASED, '1'); }); }
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { setOpen(false); } });

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var text = (input.value || '').trim();
			if (!text) { return; }
			input.value = '';
			if (C.aiReady) { sendToAi(text); return; }
			addMessage('user', text);
			openWa(withContext('Hello ' + (C.brand || 'MK Auto Spares') + ', ' + text));
			try { if (typeof window.gtag === 'function') { window.gtag('event', 'whatsapp_click', { location: 'chat_widget' }); } } catch (er) {}
		});

		setStatus();
		if (!ss(SEEN) && !ss(TEASED) && teaser) {
			teaserTimer = setTimeout(function () {
				if (panel.hidden && !document.documentElement.classList.contains('mkwo-lock')) { teaser.hidden = false; ss(TEASED, '1'); }
			}, 9000);
		}
	}

	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
