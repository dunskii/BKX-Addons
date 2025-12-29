/**
 * Amazon Alexa Admin JavaScript
 *
 * @package BookingX\Alexa
 */

(function($) {
	'use strict';

	var BkxAlexaAdmin = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.loadStats();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			$('#bkx-alexa-settings-form').on('submit', this.saveSettings.bind(this));
			$('#bkx-test-connection').on('click', this.testConnection.bind(this));
			$('#bkx-export-skill, #bkx-export-skill-guide').on('click', this.exportSkill.bind(this));
			$('.bkx-copy-btn').on('click', this.copyToClipboard.bind(this));
		},

		/**
		 * Save settings.
		 *
		 * @param {Event} e
		 */
		saveSettings: function(e) {
			e.preventDefault();

			var $form = $(e.target);
			var $button = $form.find('[type="submit"]');

			$button.prop('disabled', true).addClass('updating-message');

			$.ajax({
				url: bkxAlexa.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=bkx_alexa_save_settings',
				success: function(response) {
					if (response.success) {
						BkxAlexaAdmin.showToast(bkxAlexa.i18n.saved, 'success');
					} else {
						BkxAlexaAdmin.showToast(response.data.message || 'Error saving settings', 'error');
					}
				},
				error: function() {
					BkxAlexaAdmin.showToast('Network error', 'error');
				},
				complete: function() {
					$button.prop('disabled', false).removeClass('updating-message');
				}
			});
		},

		/**
		 * Test connection.
		 *
		 * @param {Event} e
		 */
		testConnection: function(e) {
			var $button = $(e.target);
			var $status = $('#connection-status');

			$button.prop('disabled', true);
			$status.html('<span class="bkx-loading"></span>');

			$.ajax({
				url: bkxAlexa.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_alexa_test_connection',
					nonce: bkxAlexa.nonce
				},
				success: function(response) {
					if (response.success) {
						$status.html('<span class="success">✓ ' + bkxAlexa.i18n.testSuccess + '</span>');
					} else {
						$status.html('<span class="error">✗ ' + (response.data.message || bkxAlexa.i18n.testFailed) + '</span>');
					}
				},
				error: function() {
					$status.html('<span class="error">✗ Network error</span>');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Export skill configuration.
		 *
		 * @param {Event} e
		 */
		exportSkill: function(e) {
			var $button = $(e.target);

			$button.prop('disabled', true);

			$.ajax({
				url: bkxAlexa.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_alexa_export_skill',
					nonce: bkxAlexa.nonce
				},
				success: function(response) {
					if (response.success) {
						// Download interaction model as JSON file.
						var interactionBlob = new Blob([JSON.stringify(response.data.interaction_model, null, 2)], {type: 'application/json'});
						var interactionUrl = URL.createObjectURL(interactionBlob);
						var a = document.createElement('a');
						a.href = interactionUrl;
						a.download = 'interaction-model.json';
						document.body.appendChild(a);
						a.click();
						document.body.removeChild(a);
						URL.revokeObjectURL(interactionUrl);

						// Also download manifest.
						setTimeout(function() {
							var manifestBlob = new Blob([JSON.stringify(response.data.manifest, null, 2)], {type: 'application/json'});
							var manifestUrl = URL.createObjectURL(manifestBlob);
							var b = document.createElement('a');
							b.href = manifestUrl;
							b.download = 'skill-manifest.json';
							document.body.appendChild(b);
							b.click();
							document.body.removeChild(b);
							URL.revokeObjectURL(manifestUrl);
						}, 500);

						BkxAlexaAdmin.showToast(bkxAlexa.i18n.exportSuccess, 'success');
					} else {
						BkxAlexaAdmin.showToast(response.data.message || 'Export failed', 'error');
					}
				},
				error: function() {
					BkxAlexaAdmin.showToast('Network error', 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Copy to clipboard.
		 *
		 * @param {Event} e
		 */
		copyToClipboard: function(e) {
			var $button = $(e.target);
			var targetId = $button.data('copy');
			var $target = $('#' + targetId);
			var text = $target.text();

			navigator.clipboard.writeText(text).then(function() {
				var originalText = $button.text();
				$button.text('Copied!');
				setTimeout(function() {
					$button.text(originalText);
				}, 2000);
			}).catch(function() {
				// Fallback for older browsers.
				var textarea = document.createElement('textarea');
				textarea.value = text;
				document.body.appendChild(textarea);
				textarea.select();
				document.execCommand('copy');
				document.body.removeChild(textarea);

				var originalText = $button.text();
				$button.text('Copied!');
				setTimeout(function() {
					$button.text(originalText);
				}, 2000);
			});
		},

		/**
		 * Load stats.
		 */
		loadStats: function() {
			if ($('#stat-total').length === 0) {
				return;
			}

			$.ajax({
				url: bkxAlexa.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_alexa_get_stats',
					nonce: bkxAlexa.nonce
				},
				success: function(response) {
					if (response.success) {
						$('#stat-total').text(response.data.total_requests || 0);
						$('#stat-successful').text(response.data.successful || 0);
						$('#stat-bookings').text(response.data.bookings_created || 0);
						$('#stat-accounts').text(response.data.linked_accounts || 0);
						$('#stat-today').text(response.data.today_requests || 0);
					}
				}
			});
		},

		/**
		 * Show toast notification.
		 *
		 * @param {string} message
		 * @param {string} type
		 */
		showToast: function(message, type) {
			var $toast = $('<div class="bkx-toast ' + type + '">' + message + '</div>');
			$('body').append($toast);

			setTimeout(function() {
				$toast.fadeOut(300, function() {
					$(this).remove();
				});
			}, 3000);
		}
	};

	$(document).ready(function() {
		BkxAlexaAdmin.init();
	});

})(jQuery);
