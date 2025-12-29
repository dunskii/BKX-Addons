/**
 * Google Assistant Admin JavaScript
 *
 * @package BookingX\GoogleAssistant
 */

(function($) {
	'use strict';

	var BkxAssistantAdmin = {
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
			$('#bkx-assistant-settings-form').on('submit', this.saveSettings.bind(this));
			$('#bkx-test-connection').on('click', this.testConnection.bind(this));
			$('#bkx-export-package').on('click', this.exportPackage.bind(this));
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
				url: bkxAssistant.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=bkx_assistant_save_settings',
				success: function(response) {
					if (response.success) {
						BkxAssistantAdmin.showToast(bkxAssistant.i18n.saved, 'success');
					} else {
						BkxAssistantAdmin.showToast(response.data.message || 'Error saving settings', 'error');
					}
				},
				error: function() {
					BkxAssistantAdmin.showToast('Network error', 'error');
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
				url: bkxAssistant.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_assistant_test_connection',
					nonce: bkxAssistant.nonce
				},
				success: function(response) {
					if (response.success) {
						$status.html('<span class="success">✓ ' + bkxAssistant.i18n.testSuccess + '</span>');
					} else {
						$status.html('<span class="error">✗ ' + (response.data.message || bkxAssistant.i18n.testFailed) + '</span>');
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
		 * Export action package.
		 *
		 * @param {Event} e
		 */
		exportPackage: function(e) {
			var $button = $(e.target);

			$button.prop('disabled', true);

			$.ajax({
				url: bkxAssistant.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_assistant_export_action_package',
					nonce: bkxAssistant.nonce
				},
				success: function(response) {
					if (response.success) {
						// Download as JSON file.
						var blob = new Blob([JSON.stringify(response.data.package, null, 2)], {type: 'application/json'});
						var url = URL.createObjectURL(blob);
						var a = document.createElement('a');
						a.href = url;
						a.download = 'action-package.json';
						document.body.appendChild(a);
						a.click();
						document.body.removeChild(a);
						URL.revokeObjectURL(url);

						BkxAssistantAdmin.showToast(bkxAssistant.i18n.exportSuccess, 'success');
					} else {
						BkxAssistantAdmin.showToast(response.data.message || 'Export failed', 'error');
					}
				},
				error: function() {
					BkxAssistantAdmin.showToast('Network error', 'error');
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
				url: bkxAssistant.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_assistant_get_stats',
					nonce: bkxAssistant.nonce
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
		BkxAssistantAdmin.init();
	});

})(jQuery);
