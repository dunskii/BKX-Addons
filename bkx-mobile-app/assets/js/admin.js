/**
 * Mobile App Framework Admin JavaScript
 *
 * @package BookingX\MobileApp
 */

(function($) {
	'use strict';

	/**
	 * Mobile App Admin
	 */
	var BkxMobileAdmin = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.initCopyButtons();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			// Test push notification
			$('#bkx-test-push').on('click', this.testPushNotification);

			// Copy buttons
			$(document).on('click', '.bkx-copy-btn', this.handleCopy);

			// Confirm revoke
			$('.bkx-revoke-key').on('click', this.confirmRevoke);
		},

		/**
		 * Initialize copy buttons
		 */
		initCopyButtons: function() {
			// Enable copy buttons if clipboard API is available
			if (navigator.clipboard) {
				$('.bkx-copy-btn').prop('disabled', false);
			}
		},

		/**
		 * Handle copy button click
		 */
		handleCopy: function(e) {
			e.preventDefault();

			var $btn = $(this);
			var textToCopy = $btn.data('copy') || $btn.data('target');

			// If data-target is set, get text from target element
			if ($btn.data('target')) {
				textToCopy = $($btn.data('target')).text();
			}

			if (!textToCopy) {
				return;
			}

			navigator.clipboard.writeText(textToCopy).then(function() {
				// Show success feedback
				var originalHtml = $btn.html();
				$btn.html('<span class="dashicons dashicons-yes"></span>');

				setTimeout(function() {
					$btn.html(originalHtml);
				}, 2000);
			}).catch(function(err) {
				console.error('Failed to copy:', err);
			});
		},

		/**
		 * Test push notification
		 */
		testPushNotification: function(e) {
			e.preventDefault();

			var $btn = $(this);
			var $result = $('.bkx-test-result');

			$btn.prop('disabled', true);
			$result.html('<span class="spinner is-active" style="float:none;"></span>');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'bkx_test_push_notification',
					nonce: bkxMobileAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						$result.html('<span style="color:#10b981;">&#10003; ' + response.data.message + '</span>');
					} else {
						$result.html('<span style="color:#ef4444;">&#10007; ' + response.data.message + '</span>');
					}
				},
				error: function() {
					$result.html('<span style="color:#ef4444;">&#10007; Connection error</span>');
				},
				complete: function() {
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Confirm API key revocation
		 */
		confirmRevoke: function(e) {
			if (!confirm(bkxMobileAdmin.i18n.confirmRevoke)) {
				e.preventDefault();
				return false;
			}
		}
	};

	/**
	 * Device List Management
	 */
	var BkxDeviceList = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			// Auto-refresh device list
			if ($('.bkx-devices-table').length) {
				// Refresh every 60 seconds
				setInterval(this.refreshStats, 60000);
			}
		},

		/**
		 * Refresh device statistics
		 */
		refreshStats: function() {
			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'bkx_get_device_stats',
					nonce: bkxMobileAdmin.nonce
				},
				success: function(response) {
					if (response.success && response.data) {
						// Update stats display if needed
						$('.bkx-stat-total').text(response.data.total);
						$('.bkx-stat-ios').text(response.data.ios);
						$('.bkx-stat-android').text(response.data.android);
					}
				}
			});
		}
	};

	/**
	 * API Key Generator
	 */
	var BkxApiKeyGenerator = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			$('.bkx-create-key-form').on('submit', this.validateForm);
		},

		/**
		 * Validate form before submission
		 */
		validateForm: function(e) {
			var $form = $(this);
			var keyName = $form.find('#key_name').val().trim();

			if (keyName.length < 3) {
				e.preventDefault();
				alert(bkxMobileAdmin.i18n.keyNameTooShort || 'Key name must be at least 3 characters.');
				return false;
			}

			return true;
		}
	};

	/**
	 * Push Notification Settings
	 */
	var BkxPushSettings = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.checkPushConfiguration();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			// Enable test button when configuration is complete
			$('#fcm_server_key, #apns_bundle_id').on('change', this.checkPushConfiguration);
		},

		/**
		 * Check if push configuration is complete
		 */
		checkPushConfiguration: function() {
			var fcmKey = $('#fcm_server_key').val();
			var apnsBundle = $('#apns_bundle_id').val();

			// Enable test button if at least one provider is configured
			var hasConfig = fcmKey.length > 0 || apnsBundle.length > 0;
			$('#bkx-test-push').prop('disabled', !hasConfig);
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		BkxMobileAdmin.init();
		BkxDeviceList.init();
		BkxApiKeyGenerator.init();
		BkxPushSettings.init();
	});

})(jQuery);
