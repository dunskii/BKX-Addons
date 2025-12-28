/**
 * Google Pay Frontend JavaScript
 *
 * @package BookingX\GooglePay
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKXGooglePay = {
		/**
		 * Google Pay client instance.
		 */
		paymentsClient: null,

		/**
		 * Current booking ID.
		 */
		bookingId: null,

		/**
		 * Current payment amount.
		 */
		amount: null,

		/**
		 * Initialize.
		 */
		init: function() {
			if (typeof google === 'undefined' || typeof google.payments === 'undefined') {
				console.warn('Google Pay API not loaded');
				return;
			}

			this.initPaymentsClient();
			this.checkPaymentAvailability();
			this.bindEvents();
		},

		/**
		 * Initialize the Google Pay client.
		 */
		initPaymentsClient: function() {
			this.paymentsClient = new google.payments.api.PaymentsClient({
				environment: bkxGooglePay.environment
			});
		},

		/**
		 * Get base request configuration.
		 *
		 * @returns {Object}
		 */
		getBaseRequest: function() {
			return {
				apiVersion: 2,
				apiVersionMinor: 0
			};
		},

		/**
		 * Get allowed card networks.
		 *
		 * @returns {Array}
		 */
		getAllowedCardNetworks: function() {
			return bkxGooglePay.allowedCards || ['AMEX', 'MASTERCARD', 'VISA', 'DISCOVER'];
		},

		/**
		 * Get allowed card authentication methods.
		 *
		 * @returns {Array}
		 */
		getAllowedCardAuthMethods: function() {
			return ['PAN_ONLY', 'CRYPTOGRAM_3DS'];
		},

		/**
		 * Get tokenization specification.
		 *
		 * @returns {Object}
		 */
		getTokenizationSpecification: function() {
			return {
				type: 'PAYMENT_GATEWAY',
				parameters: {
					gateway: bkxGooglePay.gateway,
					gatewayMerchantId: bkxGooglePay.gatewayMerchantId
				}
			};
		},

		/**
		 * Get base card payment method.
		 *
		 * @returns {Object}
		 */
		getBaseCardPaymentMethod: function() {
			return {
				type: 'CARD',
				parameters: {
					allowedAuthMethods: this.getAllowedCardAuthMethods(),
					allowedCardNetworks: this.getAllowedCardNetworks()
				}
			};
		},

		/**
		 * Get card payment method.
		 *
		 * @returns {Object}
		 */
		getCardPaymentMethod: function() {
			return Object.assign(
				{},
				this.getBaseCardPaymentMethod(),
				{
					tokenizationSpecification: this.getTokenizationSpecification()
				}
			);
		},

		/**
		 * Get IsReadyToPayRequest.
		 *
		 * @returns {Object}
		 */
		getIsReadyToPayRequest: function() {
			return Object.assign(
				{},
				this.getBaseRequest(),
				{
					allowedPaymentMethods: [this.getBaseCardPaymentMethod()]
				}
			);
		},

		/**
		 * Get PaymentDataRequest.
		 *
		 * @returns {Object}
		 */
		getPaymentDataRequest: function() {
			const paymentDataRequest = Object.assign({}, this.getBaseRequest());

			paymentDataRequest.allowedPaymentMethods = [this.getCardPaymentMethod()];
			paymentDataRequest.transactionInfo = this.getTransactionInfo();
			paymentDataRequest.merchantInfo = this.getMerchantInfo();

			return paymentDataRequest;
		},

		/**
		 * Get merchant info.
		 *
		 * @returns {Object}
		 */
		getMerchantInfo: function() {
			const merchantInfo = {
				merchantName: bkxGooglePay.merchantName
			};

			// Only include merchantId in production.
			if (bkxGooglePay.environment === 'PRODUCTION' && bkxGooglePay.merchantId) {
				merchantInfo.merchantId = bkxGooglePay.merchantId;
			}

			return merchantInfo;
		},

		/**
		 * Get transaction info.
		 *
		 * @returns {Object}
		 */
		getTransactionInfo: function() {
			return {
				totalPriceStatus: 'FINAL',
				totalPrice: this.amount ? this.amount.toString() : '0.00',
				currencyCode: bkxGooglePay.currencyCode,
				countryCode: bkxGooglePay.countryCode
			};
		},

		/**
		 * Check if Google Pay is available.
		 */
		checkPaymentAvailability: function() {
			const self = this;
			const $container = $('#bkx-google-pay-container');

			this.paymentsClient.isReadyToPay(this.getIsReadyToPayRequest())
				.then(function(response) {
					if (response.result) {
						self.addGooglePayButton();
					} else {
						$container.addClass('not-available');
						self.showMessage(bkxGooglePay.i18n.notSupported, 'error');
					}
				})
				.catch(function(error) {
					console.error('Error checking Google Pay availability:', error);
					$container.addClass('not-available');
				});
		},

		/**
		 * Add Google Pay button.
		 */
		addGooglePayButton: function() {
			const self = this;
			const $buttonContainer = $('#bkx-google-pay-button');

			if (!$buttonContainer.length) {
				return;
			}

			const button = this.paymentsClient.createButton({
				buttonColor: bkxGooglePay.buttonColor,
				buttonType: bkxGooglePay.buttonType,
				buttonLocale: bkxGooglePay.buttonLocale,
				buttonSizeMode: 'fill',
				onClick: function() {
					self.onGooglePayButtonClicked();
				}
			});

			$buttonContainer.html(button);
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			const self = this;

			// Update amount when booking form changes.
			$(document).on('bkx_booking_total_updated', function(e, data) {
				if (data && data.total) {
					self.amount = parseFloat(data.total).toFixed(2);
				}
			});

			// Store booking ID when available.
			$(document).on('bkx_booking_created', function(e, data) {
				if (data && data.booking_id) {
					self.bookingId = data.booking_id;
				}
			});
		},

		/**
		 * Handle Google Pay button click.
		 */
		onGooglePayButtonClicked: function() {
			const self = this;
			const $container = $('#bkx-google-pay-container');

			// Validate we have booking info.
			if (!this.bookingId) {
				// Try to get from hidden field.
				this.bookingId = $('input[name="booking_id"]').val();
			}

			if (!this.amount) {
				// Try to get from booking form.
				this.amount = $('#bkx_booking_total').val() || $('[data-booking-total]').data('booking-total');
			}

			if (!this.amount || parseFloat(this.amount) <= 0) {
				this.showMessage('Please complete the booking form first.', 'error');
				return;
			}

			$container.addClass('loading');
			this.clearMessage();

			this.paymentsClient.loadPaymentData(this.getPaymentDataRequest())
				.then(function(paymentData) {
					self.processPayment(paymentData);
				})
				.catch(function(error) {
					$container.removeClass('loading');

					if (error.statusCode === 'CANCELED') {
						// User cancelled, no message needed.
						return;
					}

					console.error('Google Pay error:', error);
					self.showMessage(bkxGooglePay.i18n.paymentFailed, 'error');
				});
		},

		/**
		 * Process the payment.
		 *
		 * @param {Object} paymentData Payment data from Google Pay.
		 */
		processPayment: function(paymentData) {
			const self = this;
			const $container = $('#bkx-google-pay-container');

			this.showMessage(bkxGooglePay.i18n.processingPayment, 'processing');

			$.ajax({
				url: bkxGooglePay.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_google_pay_process',
					nonce: bkxGooglePay.nonce,
					booking_id: this.bookingId,
					payment_data: JSON.stringify(paymentData)
				},
				success: function(response) {
					$container.removeClass('loading');

					if (response.success) {
						self.showMessage(response.data.message || 'Payment successful!', 'success');

						// Trigger success event.
						$(document).trigger('bkx_payment_success', {
							gateway: 'google_pay',
							booking_id: self.bookingId,
							transaction_id: response.data.transaction_id
						});

						// Redirect if URL provided.
						if (response.data.redirect_url) {
							window.location.href = response.data.redirect_url;
						}
					} else {
						self.showMessage(response.data.message || bkxGooglePay.i18n.paymentFailed, 'error');

						// Trigger failure event.
						$(document).trigger('bkx_payment_failed', {
							gateway: 'google_pay',
							booking_id: self.bookingId,
							message: response.data.message
						});
					}
				},
				error: function(xhr, status, error) {
					$container.removeClass('loading');
					self.showMessage(bkxGooglePay.i18n.paymentFailed, 'error');

					console.error('AJAX error:', status, error);
				}
			});
		},

		/**
		 * Show a message.
		 *
		 * @param {string} message Message text.
		 * @param {string} type    Message type (error, success, processing).
		 */
		showMessage: function(message, type) {
			const $messageContainer = $('#bkx-google-pay-message');

			$messageContainer
				.removeClass('error success processing')
				.addClass(type)
				.text(message)
				.show();
		},

		/**
		 * Clear the message.
		 */
		clearMessage: function() {
			$('#bkx-google-pay-message')
				.removeClass('error success processing')
				.text('')
				.hide();
		},

		/**
		 * Set booking ID.
		 *
		 * @param {number} id Booking ID.
		 */
		setBookingId: function(id) {
			this.bookingId = id;
		},

		/**
		 * Set payment amount.
		 *
		 * @param {number|string} amount Payment amount.
		 */
		setAmount: function(amount) {
			this.amount = parseFloat(amount).toFixed(2);
		}
	};

	// Initialize when document is ready.
	$(document).ready(function() {
		// Wait for Google Pay API to load.
		if (typeof google !== 'undefined' && typeof google.payments !== 'undefined') {
			BKXGooglePay.init();
		} else {
			// Retry after a short delay.
			setTimeout(function() {
				if (typeof google !== 'undefined' && typeof google.payments !== 'undefined') {
					BKXGooglePay.init();
				}
			}, 1000);
		}
	});

	// Expose to global scope.
	window.BKXGooglePay = BKXGooglePay;

})(jQuery);
