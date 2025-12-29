/**
 * Bulk & Recurring Payments Frontend JavaScript.
 *
 * @package BookingX\BulkRecurringPayments
 * @since   1.0.0
 */

/* global jQuery, bkxPackages */

(function ($) {
	'use strict';

	const BkxPackagesFrontend = {
		/**
		 * Initialize.
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function () {
			// Purchase buttons.
			$(document).on('click', '.bkx-purchase-btn', this.handlePurchase.bind(this));

			// Subscription management.
			$(document).on('click', '.bkx-pause-subscription', this.pauseSubscription.bind(this));
			$(document).on('click', '.bkx-resume-subscription', this.resumeSubscription.bind(this));
			$(document).on('click', '.bkx-cancel-subscription', this.cancelSubscription.bind(this));

			// Credit usage.
			$(document).on('click', '.bkx-use-credit', this.useCredit.bind(this));
		},

		/**
		 * Handle purchase button click.
		 *
		 * @param {Event} e Click event.
		 */
		handlePurchase: function (e) {
			e.preventDefault();

			const $btn = $(e.currentTarget);
			const packageId = $btn.data('package-id');

			if ($btn.prop('disabled')) {
				return;
			}

			// Check if user is logged in.
			if (!this.isLoggedIn()) {
				window.location.href = bkxPackages.loginUrl + '?redirect_to=' + encodeURIComponent(window.location.href);
				return;
			}

			// Show payment modal or redirect to checkout.
			this.showPaymentOptions(packageId, $btn);
		},

		/**
		 * Check if user is logged in.
		 *
		 * @return {boolean} True if logged in.
		 */
		isLoggedIn: function () {
			return typeof bkxPackages !== 'undefined' && bkxPackages.isLoggedIn;
		},

		/**
		 * Show payment options.
		 *
		 * @param {number} packageId Package ID.
		 * @param {jQuery} $btn      Button element.
		 */
		showPaymentOptions: function (packageId, $btn) {
			const originalText = $btn.text();
			$btn.prop('disabled', true).text(bkxPackages.i18n.processing);

			// For now, process directly.
			// In production, this would show a payment gateway selection.
			$.ajax({
				url: bkxPackages.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_purchase_package',
					nonce: bkxPackages.nonce,
					package_id: packageId,
					gateway: 'stripe', // Default gateway.
					payment_method: '', // Would come from Stripe Elements.
				},
				success: function (response) {
					if (response.success) {
						if (response.data.redirect_url) {
							window.location.href = response.data.redirect_url;
						} else {
							alert(bkxPackages.i18n.purchaseSuccess);
							window.location.reload();
						}
					} else {
						alert(response.data.message || bkxPackages.i18n.error);
						$btn.prop('disabled', false).text(originalText);
					}
				},
				error: function () {
					alert(bkxPackages.i18n.error);
					$btn.prop('disabled', false).text(originalText);
				},
			});
		},

		/**
		 * Pause subscription.
		 *
		 * @param {Event} e Click event.
		 */
		pauseSubscription: function (e) {
			e.preventDefault();

			const $btn = $(e.currentTarget);
			const subscriptionId = $btn.data('subscription-id');

			if (!confirm('Are you sure you want to pause this subscription?')) {
				return;
			}

			$btn.prop('disabled', true);

			$.ajax({
				url: bkxPackages.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_pause_subscription',
					nonce: bkxPackages.nonce,
					subscription_id: subscriptionId,
				},
				success: function (response) {
					if (response.success) {
						window.location.reload();
					} else {
						alert(response.data.message || bkxPackages.i18n.error);
						$btn.prop('disabled', false);
					}
				},
				error: function () {
					alert(bkxPackages.i18n.error);
					$btn.prop('disabled', false);
				},
			});
		},

		/**
		 * Resume subscription.
		 *
		 * @param {Event} e Click event.
		 */
		resumeSubscription: function (e) {
			e.preventDefault();

			const $btn = $(e.currentTarget);
			const subscriptionId = $btn.data('subscription-id');

			$btn.prop('disabled', true);

			$.ajax({
				url: bkxPackages.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_resume_subscription',
					nonce: bkxPackages.nonce,
					subscription_id: subscriptionId,
				},
				success: function (response) {
					if (response.success) {
						window.location.reload();
					} else {
						alert(response.data.message || bkxPackages.i18n.error);
						$btn.prop('disabled', false);
					}
				},
				error: function () {
					alert(bkxPackages.i18n.error);
					$btn.prop('disabled', false);
				},
			});
		},

		/**
		 * Cancel subscription.
		 *
		 * @param {Event} e Click event.
		 */
		cancelSubscription: function (e) {
			e.preventDefault();

			const $btn = $(e.currentTarget);
			const subscriptionId = $btn.data('subscription-id');

			const reason = prompt('Please tell us why you are cancelling (optional):');

			if (reason === null) {
				return; // User clicked Cancel.
			}

			$btn.prop('disabled', true);

			$.ajax({
				url: bkxPackages.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_cancel_subscription',
					nonce: bkxPackages.nonce,
					subscription_id: subscriptionId,
					reason: reason,
				},
				success: function (response) {
					if (response.success) {
						alert(bkxPackages.i18n.cancelSuccess);
						window.location.reload();
					} else {
						alert(response.data.message || bkxPackages.i18n.error);
						$btn.prop('disabled', false);
					}
				},
				error: function () {
					alert(bkxPackages.i18n.error);
					$btn.prop('disabled', false);
				},
			});
		},

		/**
		 * Use credit for booking.
		 *
		 * @param {Event} e Click event.
		 */
		useCredit: function (e) {
			e.preventDefault();

			const $btn = $(e.currentTarget);
			const bulkPurchaseId = $btn.data('purchase-id');
			const bookingId = $btn.data('booking-id');

			$btn.prop('disabled', true);

			$.ajax({
				url: bkxPackages.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_apply_bulk_credit',
					nonce: bkxPackages.nonce,
					bulk_purchase_id: bulkPurchaseId,
					booking_id: bookingId,
				},
				success: function (response) {
					if (response.success) {
						// Trigger event for booking form to handle.
						$(document).trigger('bkx_credit_applied', [response.data]);

						// Update credits display.
						BkxPackagesFrontend.updateCreditsDisplay(response.data.remaining_credits);
					} else {
						alert(response.data.message || bkxPackages.i18n.error);
						$btn.prop('disabled', false);
					}
				},
				error: function () {
					alert(bkxPackages.i18n.error);
					$btn.prop('disabled', false);
				},
			});
		},

		/**
		 * Update credits display.
		 *
		 * @param {number} remaining Remaining credits.
		 */
		updateCreditsDisplay: function (remaining) {
			$('.bkx-credits-remaining-value').text(remaining);

			// Update progress bar if exists.
			const $progressBar = $('.bkx-credits-progress');
			if ($progressBar.length) {
				const total = parseInt($progressBar.data('total'), 10);
				const percentage = (remaining / total) * 100;
				$progressBar.css('width', percentage + '%');

				// Update color classes.
				$progressBar.removeClass('low critical');
				if (percentage <= 10) {
					$progressBar.addClass('critical');
				} else if (percentage <= 25) {
					$progressBar.addClass('low');
				}
			}
		},
	};

	// Initialize on document ready.
	$(document).ready(function () {
		BkxPackagesFrontend.init();
	});
})(jQuery);
