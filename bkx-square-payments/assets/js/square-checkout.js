/**
 * Square Checkout JavaScript
 *
 * Handles Square Web Payments SDK integration for BookingX checkout.
 *
 * @package BookingX\SquarePayments
 */

(function ($) {
	'use strict';

	let payments;
	let card;

	/**
	 * Initialize Square payments.
	 */
	async function initializeSquarePayments() {
		if (!window.Square) {
			console.error('Square.js failed to load');
			return;
		}

		try {
			payments = window.Square.payments(
				bkxSquare.applicationId,
				bkxSquare.locationId
			);

			// Initialize card payment method
			await initializeCard();

			// Initialize digital wallets if enabled
			if (bkxSquare.enableApplePay === '1') {
				await initializeApplePay();
			}

			if (bkxSquare.enableGooglePay === '1') {
				await initializeGooglePay();
			}

			if (bkxSquare.enableCashAppPay === '1') {
				await initializeCashAppPay();
			}
		} catch (e) {
			console.error('Initializing Square Payments failed', e);
			showError(bkxSquare.i18n.error);
		}
	}

	/**
	 * Initialize card payment method.
	 */
	async function initializeCard() {
		card = await payments.card({
			style: {
				'.input-container': {
					borderRadius: '4px',
					borderColor: 'transparent'
				},
				'.input-container.is-focus': {
					borderColor: 'transparent'
				},
				'.message-text': {
					color: '#721c24'
				}
			}
		});

		await card.attach('#bkx-square-card-number');

		// Note: Square SDK handles expiry, CVV, and postal code fields automatically
		// when using the card payment method
	}

	/**
	 * Initialize Apple Pay.
	 */
	async function initializeApplePay() {
		const paymentRequest = buildPaymentRequest();

		try {
			const applePay = await payments.applePay(paymentRequest);
			const applePayButton = document.getElementById('bkx-square-apple-pay-button');

			if (applePayButton) {
				applePayButton.addEventListener('click', async () => {
					await handlePaymentMethodSubmission(applePay, 'applePay');
				});
			}
		} catch (e) {
			console.error('Apple Pay initialization failed', e);
		}
	}

	/**
	 * Initialize Google Pay.
	 */
	async function initializeGooglePay() {
		const paymentRequest = buildPaymentRequest();

		try {
			const googlePay = await payments.googlePay(paymentRequest);
			await googlePay.attach('#bkx-square-google-pay-button');

			const googlePayButton = document.getElementById('bkx-square-google-pay-button');

			if (googlePayButton) {
				googlePayButton.addEventListener('click', async () => {
					await handlePaymentMethodSubmission(googlePay, 'googlePay');
				});
			}
		} catch (e) {
			console.error('Google Pay initialization failed', e);
		}
	}

	/**
	 * Initialize Cash App Pay.
	 */
	async function initializeCashAppPay() {
		const paymentRequest = buildPaymentRequest();

		try {
			const cashAppPay = await payments.cashAppPay(paymentRequest, {
				redirectURL: window.location.href,
				referenceId: 'bkx-' + $('#bkx-square-booking-id').val()
			});

			await cashAppPay.attach('#bkx-square-cash-app-pay-button');

			const cashAppButton = document.getElementById('bkx-square-cash-app-pay-button');

			if (cashAppButton) {
				cashAppButton.addEventListener('click', async () => {
					await handlePaymentMethodSubmission(cashAppPay, 'cashAppPay');
				});
			}
		} catch (e) {
			console.error('Cash App Pay initialization failed', e);
		}
	}

	/**
	 * Build payment request for digital wallets.
	 */
	function buildPaymentRequest() {
		const amount = $('#bkx-square-amount').val();
		const currency = bkxSquare.currency || 'USD';

		return {
			countryCode: 'US',
			currencyCode: currency,
			total: {
				amount: amount,
				label: 'Booking Payment',
				pending: false
			}
		};
	}

	/**
	 * Handle payment method submission.
	 */
	async function handlePaymentMethodSubmission(paymentMethod, methodType = 'card') {
		try {
			showProcessing();

			// Tokenize payment method
			const tokenResult = await paymentMethod.tokenize();

			if (tokenResult.status === 'OK') {
				await processPayment(tokenResult.token, tokenResult);
			} else {
				let errorMessage = bkxSquare.i18n.error;

				if (tokenResult.errors) {
					errorMessage = tokenResult.errors[0].message;
				}

				showError(errorMessage);
				hideProcessing();
			}
		} catch (e) {
			console.error('Payment method submission failed', e);
			showError(e.message || bkxSquare.i18n.error);
			hideProcessing();
		}
	}

	/**
	 * Process payment via AJAX.
	 */
	async function processPayment(sourceId, tokenResult) {
		const bookingId = $('#bkx-square-booking-id').val();
		const amount = $('#bkx-square-amount').val();

		const paymentData = {
			action: 'bkx_square_process_payment',
			nonce: bkxSquare.nonce,
			booking_id: bookingId,
			source_id: sourceId,
			amount: amount,
			verification_token: tokenResult.verificationToken || ''
		};

		try {
			const response = await $.ajax({
				url: bkxSquare.ajaxUrl,
				type: 'POST',
				data: paymentData
			});

			if (response.success) {
				showSuccess(bkxSquare.i18n.success);

				// Redirect to confirmation page after 2 seconds
				setTimeout(() => {
					if (response.data.redirect_url) {
						window.location.href = response.data.redirect_url;
					}
				}, 2000);
			} else {
				showError(response.data.message || bkxSquare.i18n.error);
				hideProcessing();
			}
		} catch (error) {
			console.error('Payment processing failed', error);
			showError(error.responseJSON?.data?.message || bkxSquare.i18n.error);
			hideProcessing();
		}
	}

	/**
	 * Show error message.
	 */
	function showError(message) {
		const errorDiv = $('#bkx-square-error-message');
		errorDiv.text(message).slideDown();

		// Hide after 5 seconds
		setTimeout(() => {
			errorDiv.slideUp();
		}, 5000);
	}

	/**
	 * Show success message.
	 */
	function showSuccess(message) {
		const successDiv = $('#bkx-square-success-message');
		successDiv.text(message).slideDown();
	}

	/**
	 * Show processing state.
	 */
	function showProcessing() {
		$('#bkx-square-pay-button').prop('disabled', true).hide();
		$('.bkx-square-processing').show();
	}

	/**
	 * Hide processing state.
	 */
	function hideProcessing() {
		$('#bkx-square-pay-button').prop('disabled', false).show();
		$('.bkx-square-processing').hide();
	}

	/**
	 * Document ready.
	 */
	$(document).ready(function () {
		// Initialize Square Payments when the form is loaded
		if ($('#bkx-square-payment-form').length) {
			initializeSquarePayments();
		}

		// Handle pay button click (for card payments)
		$('#bkx-square-pay-button').on('click', async function (e) {
			e.preventDefault();
			await handlePaymentMethodSubmission(card, 'card');
		});
	});

})(jQuery);
