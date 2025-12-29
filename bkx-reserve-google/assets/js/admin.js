/**
 * Reserve with Google Admin JavaScript
 *
 * @package BookingX\ReserveGoogle
 */

(function($) {
	'use strict';

	var BkxRwgAdmin = {
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
			$('#bkx-rwg-settings-form').on('submit', this.saveSettings.bind(this));
			$('#bkx-verify-merchant').on('click', this.verifyMerchant.bind(this));
			$('#bkx-sync-services').on('click', this.syncServices.bind(this));
			$('.bkx-test-feed').on('click', this.testFeed.bind(this));
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
				url: bkxRwg.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=bkx_rwg_save_settings',
				success: function(response) {
					if (response.success) {
						BkxRwgAdmin.showToast(bkxRwg.i18n.saved, 'success');
					} else {
						BkxRwgAdmin.showToast(response.data.message || 'Error saving settings', 'error');
					}
				},
				error: function() {
					BkxRwgAdmin.showToast('Network error', 'error');
				},
				complete: function() {
					$button.prop('disabled', false).removeClass('updating-message');
				}
			});
		},

		/**
		 * Verify merchant.
		 *
		 * @param {Event} e
		 */
		verifyMerchant: function(e) {
			var $button = $(e.target);
			var $status = $('#verify-status');

			$button.prop('disabled', true);
			$status.html('<span class="bkx-loading"></span> ' + bkxRwg.i18n.verifying);

			$.ajax({
				url: bkxRwg.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_rwg_verify_merchant',
					nonce: bkxRwg.nonce
				},
				success: function(response) {
					if (response.success) {
						$status.html('<span class="success">✓ ' + bkxRwg.i18n.verified + '</span>');
					} else {
						$status.html('<span class="error">✗ ' + (response.data.message || 'Verification failed') + '</span>');
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
		 * Sync services.
		 *
		 * @param {Event} e
		 */
		syncServices: function(e) {
			var $button = $(e.target);

			$button.prop('disabled', true).text('Syncing...');

			$.ajax({
				url: bkxRwg.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_rwg_sync_services',
					nonce: bkxRwg.nonce
				},
				success: function(response) {
					if (response.success) {
						BkxRwgAdmin.showToast(response.data.message || bkxRwg.i18n.synced, 'success');
						location.reload();
					} else {
						BkxRwgAdmin.showToast(response.data.message || 'Sync failed', 'error');
					}
				},
				error: function() {
					BkxRwgAdmin.showToast('Network error', 'error');
				},
				complete: function() {
					$button.prop('disabled', false).text('Sync Now');
				}
			});
		},

		/**
		 * Test feed.
		 *
		 * @param {Event} e
		 */
		testFeed: function(e) {
			var $button = $(e.target);
			var feedType = $button.data('feed');

			$button.prop('disabled', true).text('Testing...');

			$.ajax({
				url: bkxRwg.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_rwg_test_feed',
					nonce: bkxRwg.nonce,
					feed_type: feedType
				},
				success: function(response) {
					if (response.success && response.data.valid) {
						BkxRwgAdmin.showToast(bkxRwg.i18n.feedValid, 'success');
						$('#feed-preview').show();
						$('#feed-preview-content').text(JSON.stringify(response.data.feed, null, 2));
					} else {
						BkxRwgAdmin.showToast(bkxRwg.i18n.feedInvalid, 'error');
					}
				},
				error: function() {
					BkxRwgAdmin.showToast('Network error', 'error');
				},
				complete: function() {
					$button.prop('disabled', false).text('Test');
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
				url: bkxRwg.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_rwg_get_stats',
					nonce: bkxRwg.nonce
				},
				success: function(response) {
					if (response.success) {
						$('#stat-total').text(response.data.total_bookings || 0);
						$('#stat-confirmed').text(response.data.confirmed || 0);
						$('#stat-cancelled').text(response.data.cancelled || 0);
						$('#stat-services').text(response.data.services_synced || 0);
						$('#stat-today').text(response.data.today_bookings || 0);
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
		BkxRwgAdmin.init();
	});

})(jQuery);
