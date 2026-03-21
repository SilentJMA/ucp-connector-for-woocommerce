/**
 * UCP Adapter Frontend JavaScript
 *
 * @package UCP_Adapter
 */

(function ($) {
	'use strict';

	const UCPAdapterClient = {
		init: function () {
			$(document).on('click', '.ucp-trigger-session', this.triggerSession.bind(this));
		},

		createCheckoutSession: function (data, protocol) {
			const base = protocol === 'acp' ? ucpAdapterData.acpRestUrl : ucpAdapterData.restUrl;
			return $.ajax({
				url: base + '/checkout_sessions',
				method: 'POST',
				contentType: 'application/json',
				data: JSON.stringify(data || {})
			});
		},

		updateCheckoutSession: function (sessionId, data, protocol) {
			const base = protocol === 'acp' ? ucpAdapterData.acpRestUrl : ucpAdapterData.restUrl;
			return $.ajax({
				url: base + '/checkout_sessions/' + sessionId,
				method: 'POST',
				contentType: 'application/json',
				data: JSON.stringify(data || {})
			});
		},

		completeCheckoutSession: function (sessionId, data, protocol) {
			const base = protocol === 'acp' ? ucpAdapterData.acpRestUrl : ucpAdapterData.restUrl;
			return $.ajax({
				url: base + '/checkout_sessions/' + sessionId + '/complete',
				method: 'POST',
				contentType: 'application/json',
				data: JSON.stringify(data || {})
			});
		},

		getCheckoutSession: function (sessionId, protocol) {
			const base = protocol === 'acp' ? ucpAdapterData.acpRestUrl : ucpAdapterData.restUrl;
			return $.ajax({
				url: base + '/checkout_sessions/' + sessionId,
				method: 'GET'
			});
		},

		// Legacy helpers kept for compatibility with old integrations.
		createSession: function (data) {
			return $.ajax({
				url: ucpAdapterData.restUrl + '/session',
				method: 'POST',
				contentType: 'application/json',
				data: JSON.stringify(data || {})
			});
		},

		updateSession: function (sessionId, action, data) {
			return $.ajax({
				url: ucpAdapterData.restUrl + '/update/' + sessionId,
				method: 'PUT',
				contentType: 'application/json',
				data: JSON.stringify({
					action: action,
					data: data || {}
				})
			});
		},

		getSessionStatus: function (sessionId) {
			return $.ajax({
				url: ucpAdapterData.restUrl + '/status/' + sessionId,
				method: 'GET'
			});
		},

		showLoading: function ($element) {
			$element.addClass('ucp-loading');
		},

		hideLoading: function ($element) {
			$element.removeClass('ucp-loading');
		},

		showMessage: function (message, type, $container) {
			const className = type === 'error' ? 'ucp-error' : 'ucp-success';
			const $notice = $('<div class="' + className + '"></div>').text(message);
			$container.prepend($notice);
			setTimeout(function () {
				$notice.fadeOut(function () {
					$(this).remove();
				});
			}, 4000);
		},

		triggerSession: function (event) {
			event.preventDefault();

			const $button = $(event.currentTarget);
			const $container = $button.closest('.ucp-adapter-container');
			this.showLoading($button);

			this.createCheckoutSession({
				buyer: {},
				line_items: []
			}, 'ucp').done((response) => {
				this.showMessage('Checkout session created: ' + response.id, 'success', $container);
			}).fail(() => {
				this.showMessage('Failed to create checkout session.', 'error', $container);
			}).always(() => {
				this.hideLoading($button);
			});
		}
	};

	$(document).ready(function () {
		UCPAdapterClient.init();
	});

	window.UCPAdapterClient = UCPAdapterClient;
})(jQuery);
