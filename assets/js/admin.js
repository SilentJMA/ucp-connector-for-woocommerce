/**
 * UCP Adapter Admin JavaScript
 *
 * @package UCP_Adapter
 */

(function ($) {
	'use strict';

	const UCPAdapterAdminClient = {
		init: function () {
			this.bindEvents();
			this.attachCopyButton();
		},

		bindEvents: function () {
			$('#regenerate-api-key').on('click', this.regenerateAPIKey.bind(this));
		},

		attachCopyButton: function () {
			const $apiKeyField = $('#ucp_adapter_api_key');
			if (! $apiKeyField.length) {
				return;
			}

			const $copyBtn = $('<button type="button" class="button button-secondary">Copy</button>');
			$apiKeyField.after($copyBtn);

			$copyBtn.on('click', async function () {
				const value = $apiKeyField.val();
				let copied = false;

				if (navigator.clipboard && window.isSecureContext) {
					try {
						await navigator.clipboard.writeText(value);
						copied = true;
					} catch (e) {
						copied = false;
					}
				}

				if (!copied) {
					$apiKeyField.trigger('focus').trigger('select');
					copied = document.execCommand('copy');
				}

				const original = $copyBtn.text();
				$copyBtn.text(copied ? 'Copied' : 'Copy failed');
				setTimeout(function () {
					$copyBtn.text(original);
				}, 1200);
			});
		},

		regenerateAPIKey: function (event) {
			event.preventDefault();

			const confirmed = window.confirm('Regenerate API key? Existing clients using the old key will stop working.');
			if (! confirmed) {
				return;
			}

			const $button = $(event.currentTarget);
			$button.prop('disabled', true).text('Regenerating...');

			$.ajax({
				url: ucpAdapterAdminData.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: 'ucp_adapter_regenerate_api_key',
					nonce: ucpAdapterAdminData.nonce
				}
			}).done((response) => {
				if (response.success && response.data.api_key) {
					$('#ucp_adapter_api_key').val(response.data.api_key);
					this.showNotice('API key regenerated.', 'success');
					return;
				}

				this.showNotice('Failed to regenerate API key.', 'error');
			}).fail(() => {
				this.showNotice('Failed to regenerate API key.', 'error');
			}).always(() => {
				$button.prop('disabled', false).text('Regenerate');
			});
		},

		showNotice: function (message, type) {
			const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			$('.wrap h1').first().after($notice);
			setTimeout(function () {
				$notice.fadeOut(250, function () {
					$(this).remove();
				});
			}, 3000);
		}
	};

	$(document).ready(function () {
		UCPAdapterAdminClient.init();
	});
})(jQuery);
