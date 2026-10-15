/* MK Auto Elite - Lipa na M-Pesa (Paybill) for the Checkout block. */
(function () {
	'use strict';
	var reg = window.wc && window.wc.wcBlocksRegistry;
	var set = window.wc && window.wc.wcSettings;
	var el = window.wp && window.wp.element;
	if (!reg || !el) { return; }
	var data = (set && set.getSetting) ? set.getSetting('mkae_mpesa_data', {}) : {};
	var h = el.createElement;
	var title = data.title || 'M-Pesa (Paybill)';
	var Content = function () {
		return h('div', { className: 'mkae-mpesa-block', dangerouslySetInnerHTML: { __html: data.description || '' } });
	};
	reg.registerPaymentMethod({
		name: 'mkae_mpesa',
		label: h('span', { className: 'mkae-mpesa-label' }, title),
		content: h(Content),
		edit: h(Content),
		canMakePayment: function () { return true; },
		ariaLabel: title,
		supports: { features: data.supports || ['products'] }
	});
}());
