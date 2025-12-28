/**
 * Regional Payments Frontend JavaScript
 *
 * @package BookingX\RegionalPayments
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKXRegionalPayments = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.formatInputs();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// CPF formatting (Brazil).
			$(document).on('input', '#bkx_pix_cpf, #bkx_boleto_cpf', this.formatCPF);

			// IBAN formatting.
			$(document).on('input', '#bkx_sepa_iban', this.formatIBAN);

			// Copy to clipboard.
			$(document).on('click', '.bkx-copy-btn', this.copyToClipboard);

			// Payment status polling.
			$(document).on('bkx_regional_payment_pending', this.startPolling);

			// Handle redirect returns.
			this.handleRedirectReturn();
		},

		/**
		 * Format CPF input.
		 *
		 * @param {Event} e Input event.
		 */
		formatCPF: function(e) {
			let value = $(this).val().replace(/\D/g, '');

			if (value.length > 11) {
				value = value.substring(0, 11);
			}

			if (value.length > 9) {
				value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
			} else if (value.length > 6) {
				value = value.replace(/^(\d{3})(\d{3})(\d{0,3})$/, '$1.$2.$3');
			} else if (value.length > 3) {
				value = value.replace(/^(\d{3})(\d{0,3})$/, '$1.$2');
			}

			$(this).val(value);
		},

		/**
		 * Format IBAN input.
		 *
		 * @param {Event} e Input event.
		 */
		formatIBAN: function(e) {
			let value = $(this).val().toUpperCase().replace(/[^A-Z0-9]/g, '');

			// Add spaces every 4 characters.
			value = value.replace(/(.{4})/g, '$1 ').trim();

			$(this).val(value);
		},

		/**
		 * Copy text to clipboard.
		 *
		 * @param {Event} e Click event.
		 */
		copyToClipboard: function(e) {
			e.preventDefault();

			const $btn = $(this);
			const $input = $btn.siblings('input');
			const text = $input.val();

			if (navigator.clipboard) {
				navigator.clipboard.writeText(text).then(function() {
					BKXRegionalPayments.showCopySuccess($btn);
				});
			} else {
				// Fallback for older browsers.
				$input.select();
				document.execCommand('copy');
				BKXRegionalPayments.showCopySuccess($btn);
			}
		},

		/**
		 * Show copy success feedback.
		 *
		 * @param {jQuery} $btn Button element.
		 */
		showCopySuccess: function($btn) {
			const originalText = $btn.text();
			$btn.text(bkxRegionalPayments.i18n.copied || 'Copied!');

			setTimeout(function() {
				$btn.text(originalText);
			}, 2000);
		},

		/**
		 * Format inputs on page load.
		 */
		formatInputs: function() {
			// Trigger formatting on existing values.
			$('#bkx_pix_cpf, #bkx_boleto_cpf').trigger('input');
			$('#bkx_sepa_iban').trigger('input');
		},

		/**
		 * Start polling for payment status.
		 *
		 * @param {Event} e Event.
		 * @param {Object} data Payment data.
		 */
		startPolling: function(e, data) {
			if (!data || !data.booking_id) {
				return;
			}

			BKXRegionalPayments.pollPaymentStatus(data.booking_id, 0);
		},

		/**
		 * Poll payment status.
		 *
		 * @param {number} bookingId Booking ID.
		 * @param {number} attempts Number of attempts.
		 */
		pollPaymentStatus: function(bookingId, attempts) {
			// Stop after 60 attempts (10 minutes with 10-second intervals).
			if (attempts >= 60) {
				return;
			}

			$.ajax({
				url: bkxRegionalPayments.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_regional_check_payment_status',
					nonce: bkxRegionalPayments.nonce,
					booking_id: bookingId
				},
				success: function(response) {
					if (response.success && response.data.completed) {
						// Payment completed.
						$(document).trigger('bkx_payment_success', {
							gateway: 'regional',
							booking_id: bookingId
						});

						BKXRegionalPayments.showSuccess(bkxRegionalPayments.i18n.success);

						// Reload or redirect.
						if (response.data.redirect_url) {
							window.location.href = response.data.redirect_url;
						}
					} else {
						// Continue polling.
						setTimeout(function() {
							BKXRegionalPayments.pollPaymentStatus(bookingId, attempts + 1);
						}, 10000);
					}
				}
			});
		},

		/**
		 * Handle redirect return.
		 */
		handleRedirectReturn: function() {
			const urlParams = new URLSearchParams(window.location.search);

			if (urlParams.get('bkx_payment_return') === '1') {
				const bookingId = urlParams.get('booking_id');
				const gateway = urlParams.get('gateway');
				const status = urlParams.get('redirect_status');

				if (status === 'succeeded') {
					this.showSuccess(bkxRegionalPayments.i18n.success);

					$(document).trigger('bkx_payment_success', {
						gateway: gateway,
						booking_id: bookingId
					});
				} else if (status === 'failed') {
					this.showError(bkxRegionalPayments.i18n.error);
				}

				// Clean URL.
				const cleanUrl = window.location.pathname;
				window.history.replaceState({}, document.title, cleanUrl);
			}
		},

		/**
		 * Process regional payment.
		 *
		 * @param {string} gateway Gateway ID.
		 * @param {number} bookingId Booking ID.
		 * @param {Object} paymentData Payment data.
		 * @param {jQuery} $container Form container.
		 */
		processPayment: function(gateway, bookingId, paymentData, $container) {
			$container.addClass('bkx-regional-processing');
			this.clearMessages($container);

			$.ajax({
				url: bkxRegionalPayments.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_regional_process_payment',
					nonce: bkxRegionalPayments.nonce,
					gateway: gateway,
					booking_id: bookingId,
					payment_data: paymentData
				},
				success: function(response) {
					$container.removeClass('bkx-regional-processing');

					if (response.success) {
						if (response.data.redirect) {
							// Redirect flow.
							window.location.href = response.data.redirect_url;
						} else if (response.data.pending) {
							// Pending payment (PIX, Boleto).
							BKXRegionalPayments.handlePendingPayment(response.data, $container, bookingId);
						} else {
							// Immediate success.
							BKXRegionalPayments.showSuccess(response.data.message, $container);

							$(document).trigger('bkx_payment_success', {
								gateway: gateway,
								booking_id: bookingId,
								transaction_id: response.data.transaction_id
							});
						}
					} else {
						BKXRegionalPayments.showError(response.data.message, $container);
					}
				},
				error: function() {
					$container.removeClass('bkx-regional-processing');
					BKXRegionalPayments.showError(bkxRegionalPayments.i18n.error, $container);
				}
			});
		},

		/**
		 * Handle pending payment (QR codes, boletos).
		 *
		 * @param {Object} data Response data.
		 * @param {jQuery} $container Form container.
		 * @param {number} bookingId Booking ID.
		 */
		handlePendingPayment: function(data, $container, bookingId) {
			// Show QR code if available.
			if (data.qr_code) {
				const $qrContainer = $container.find('.bkx-pix-qr-container, .bkx-upi-qr-container');
				$qrContainer.show();

				if (data.qr_code.startsWith('data:') || data.qr_code.startsWith('http')) {
					$qrContainer.find('.bkx-pix-qr-code, .bkx-upi-qr-code').html(
						'<img src="' + data.qr_code + '" alt="QR Code">'
					);
				} else {
					// Base64 encoded.
					$qrContainer.find('.bkx-pix-qr-code, .bkx-upi-qr-code').html(
						'<img src="data:image/png;base64,' + data.qr_code + '" alt="QR Code">'
					);
				}
			}

			// Show copy-paste code.
			if (data.copy_paste) {
				$container.find('#bkx_pix_code').val(data.copy_paste);
			}

			// Show boleto URL.
			if (data.boleto_url) {
				$container.find('.bkx-boleto-result').show();
				$container.find('.bkx-boleto-download').attr('href', data.boleto_url);

				if (data.barcode) {
					$container.find('.bkx-boleto-barcode').text(data.barcode);
				}
			}

			// Start polling for payment completion.
			$(document).trigger('bkx_regional_payment_pending', {
				booking_id: bookingId
			});

			this.showSuccess(data.message, $container);
		},

		/**
		 * Show success message.
		 *
		 * @param {string} message Message.
		 * @param {jQuery} $container Container element.
		 */
		showSuccess: function(message, $container) {
			const $msg = $('<div class="bkx-regional-success">' + message + '</div>');

			if ($container) {
				$container.append($msg);
			} else {
				$('.bkx-booking-form').append($msg);
			}
		},

		/**
		 * Show error message.
		 *
		 * @param {string} message Message.
		 * @param {jQuery} $container Container element.
		 */
		showError: function(message, $container) {
			const $msg = $('<div class="bkx-regional-error">' + message + '</div>');

			if ($container) {
				$container.append($msg);
			} else {
				$('.bkx-booking-form').append($msg);
			}
		},

		/**
		 * Clear messages.
		 *
		 * @param {jQuery} $container Container element.
		 */
		clearMessages: function($container) {
			if ($container) {
				$container.find('.bkx-regional-error, .bkx-regional-success').remove();
			} else {
				$('.bkx-regional-error, .bkx-regional-success').remove();
			}
		}
	};

	// Initialize when document is ready.
	$(document).ready(function() {
		BKXRegionalPayments.init();
	});

	// Expose to global scope.
	window.BKXRegionalPayments = BKXRegionalPayments;

})(jQuery);
