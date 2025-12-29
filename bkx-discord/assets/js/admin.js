/**
 * Discord Notifications Admin JavaScript
 *
 * @package BookingX\Discord
 */

(function($) {
	'use strict';

	var BkxDiscordAdmin = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.initColorPicker();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			// Settings form.
			$('#bkx-discord-settings-form').on('submit', this.saveSettings.bind(this));

			// Add webhook form.
			$('#bkx-discord-add-webhook-form').on('submit', this.addWebhook.bind(this));

			// Toggle webhook.
			$(document).on('change', '.bkx-toggle-webhook', this.toggleWebhook.bind(this));

			// Test webhook.
			$(document).on('click', '.bkx-test-webhook', this.testWebhook.bind(this));

			// Delete webhook.
			$(document).on('click', '.bkx-delete-webhook', this.deleteWebhook.bind(this));

			// Clear logs.
			$('#bkx-clear-logs').on('click', this.clearLogs.bind(this));

			// Update preview color.
			$('#embed-color').on('change', this.updatePreviewColor.bind(this));
		},

		/**
		 * Initialize color picker if available.
		 */
		initColorPicker: function() {
			if ($.fn.wpColorPicker) {
				$('#embed-color').wpColorPicker({
					change: this.updatePreviewColor.bind(this)
				});
			}
		},

		/**
		 * Update preview embed color.
		 *
		 * @param {Event} e
		 */
		updatePreviewColor: function(e) {
			var color = $(e.target).val() || '#5865F2';
			$('.bkx-discord-embed').css('border-left-color', color);
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
				url: bkxDiscord.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=bkx_discord_save_settings&nonce=' + bkxDiscord.nonce,
				success: function(response) {
					if (response.success) {
						BkxDiscordAdmin.showToast(bkxDiscord.i18n.saved, 'success');
					} else {
						BkxDiscordAdmin.showToast(response.data.message || bkxDiscord.i18n.error, 'error');
					}
				},
				error: function() {
					BkxDiscordAdmin.showToast(bkxDiscord.i18n.error, 'error');
				},
				complete: function() {
					$button.prop('disabled', false).removeClass('updating-message');
				}
			});
		},

		/**
		 * Add webhook.
		 *
		 * @param {Event} e
		 */
		addWebhook: function(e) {
			e.preventDefault();

			var $form = $(e.target);
			var $button = $form.find('[type="submit"]');

			$button.prop('disabled', true).addClass('updating-message');

			$.ajax({
				url: bkxDiscord.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=bkx_discord_add_webhook&nonce=' + bkxDiscord.nonce,
				success: function(response) {
					if (response.success) {
						BkxDiscordAdmin.showToast(bkxDiscord.i18n.webhookAdded, 'success');
						location.reload();
					} else {
						BkxDiscordAdmin.showToast(response.data.message || bkxDiscord.i18n.error, 'error');
					}
				},
				error: function() {
					BkxDiscordAdmin.showToast(bkxDiscord.i18n.error, 'error');
				},
				complete: function() {
					$button.prop('disabled', false).removeClass('updating-message');
				}
			});
		},

		/**
		 * Toggle webhook status.
		 *
		 * @param {Event} e
		 */
		toggleWebhook: function(e) {
			var $checkbox = $(e.target);
			var webhookId = $checkbox.data('webhook-id');
			var isActive = $checkbox.is(':checked');

			$.ajax({
				url: bkxDiscord.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_discord_toggle_webhook',
					nonce: bkxDiscord.nonce,
					webhook_id: webhookId,
					is_active: isActive ? 1 : 0
				}
			});
		},

		/**
		 * Test webhook.
		 *
		 * @param {Event} e
		 */
		testWebhook: function(e) {
			var $button = $(e.target);
			var webhookId = $button.data('webhook-id');

			$button.prop('disabled', true).text('Sending...');

			$.ajax({
				url: bkxDiscord.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_discord_test_webhook',
					nonce: bkxDiscord.nonce,
					webhook_id: webhookId
				},
				success: function(response) {
					if (response.success) {
						BkxDiscordAdmin.showToast(bkxDiscord.i18n.testSent, 'success');
					} else {
						BkxDiscordAdmin.showToast(response.data.message || bkxDiscord.i18n.error, 'error');
					}
				},
				error: function() {
					BkxDiscordAdmin.showToast(bkxDiscord.i18n.error, 'error');
				},
				complete: function() {
					$button.prop('disabled', false).text('Test');
				}
			});
		},

		/**
		 * Delete webhook.
		 *
		 * @param {Event} e
		 */
		deleteWebhook: function(e) {
			var $button = $(e.target);
			var webhookId = $button.data('webhook-id');

			if (!confirm(bkxDiscord.i18n.confirmDelete)) {
				return;
			}

			$button.prop('disabled', true);

			$.ajax({
				url: bkxDiscord.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_discord_delete_webhook',
					nonce: bkxDiscord.nonce,
					webhook_id: webhookId
				},
				success: function(response) {
					if (response.success) {
						BkxDiscordAdmin.showToast(bkxDiscord.i18n.webhookDeleted, 'success');
						$button.closest('tr').fadeOut(300, function() {
							$(this).remove();
						});
					} else {
						BkxDiscordAdmin.showToast(response.data.message || bkxDiscord.i18n.error, 'error');
						$button.prop('disabled', false);
					}
				},
				error: function() {
					BkxDiscordAdmin.showToast(bkxDiscord.i18n.error, 'error');
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Clear old logs.
		 */
		clearLogs: function() {
			if (!confirm('Are you sure you want to clear old logs (older than 30 days)?')) {
				return;
			}

			$.ajax({
				url: bkxDiscord.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_discord_clear_logs',
					nonce: bkxDiscord.nonce
				},
				success: function(response) {
					if (response.success) {
						BkxDiscordAdmin.showToast('Logs cleared', 'success');
						location.reload();
					} else {
						BkxDiscordAdmin.showToast(response.data.message || bkxDiscord.i18n.error, 'error');
					}
				},
				error: function() {
					BkxDiscordAdmin.showToast(bkxDiscord.i18n.error, 'error');
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

	// Initialize on document ready.
	$(document).ready(function() {
		BkxDiscordAdmin.init();
	});

})(jQuery);
