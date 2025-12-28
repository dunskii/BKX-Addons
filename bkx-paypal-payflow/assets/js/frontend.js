/**
 * PayPal Payflow Frontend JavaScript
 *
 * @package BookingX\PayPalPayflow
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKXPayflow = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Card number formatting.
			$(document).on('input', '#bkx_card_number', this.formatCardNumber);
			$(document).on('keydown', '#bkx_card_number', this.handleCardKeydown);

			// Expiry formatting.
			$(document).on('input', '#bkx_card_expiry', this.formatExpiry);

			// CVV - numbers only.
			$(document).on('input', '#bkx_card_cvv', this.formatCVV);

			// Card type detection.
			$(document).on('input', '#bkx_card_number', this.detectCardType);

			// Form validation before submit.
			$(document).on('submit', 'form:has(.bkx-payflow-fields)', this.validateForm);
		},

		/**
		 * Format card number with spaces.
		 *
		 * @param {Event} e Input event.
		 */
		formatCardNumber: function(e) {
			let value = $(this).val().replace(/\D/g, '');

			// Limit to 16 digits.
			value = value.substring(0, 16);

			// Add spaces every 4 digits.
			value = value.replace(/(\d{4})(?=\d)/g, '$1 ');

			$(this).val(value);
		},

		/**
		 * Handle card number keydown.
		 *
		 * @param {Event} e Keydown event.
		 */
		handleCardKeydown: function(e) {
			// Allow: backspace, delete, tab, escape, enter.
			if ([46, 8, 9, 27, 13].includes(e.keyCode) ||
				// Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X.
				(e.keyCode >= 35 && e.keyCode <= 40) ||
				((e.keyCode === 65 || e.keyCode === 67 || e.keyCode === 86 || e.keyCode === 88) && (e.ctrlKey === true || e.metaKey === true))) {
				return;
			}

			// Only allow numbers.
			if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
				e.preventDefault();
			}
		},

		/**
		 * Format expiry date.
		 *
		 * @param {Event} e Input event.
		 */
		formatExpiry: function(e) {
			let value = $(this).val().replace(/\D/g, '');

			if (value.length >= 2) {
				const month = parseInt(value.substring(0, 2), 10);
				if (month > 12) {
					value = '12' + value.substring(2);
				}
				value = value.substring(0, 2) + '/' + value.substring(2, 4);
			}

			$(this).val(value);
		},

		/**
		 * Format CVV - numbers only.
		 *
		 * @param {Event} e Input event.
		 */
		formatCVV: function(e) {
			let value = $(this).val().replace(/\D/g, '');
			$(this).val(value.substring(0, 4));
		},

		/**
		 * Detect and highlight card type.
		 *
		 * @param {Event} e Input event.
		 */
		detectCardType: function(e) {
			const number = $(this).val().replace(/\D/g, '');
			const $icons = $('.bkx-card-icon');

			$icons.removeClass('active');

			if (!number) {
				return;
			}

			let cardType = null;

			// Visa.
			if (/^4/.test(number)) {
				cardType = 'visa';
			}
			// Mastercard.
			else if (/^5[1-5]/.test(number) || /^2[2-7]/.test(number)) {
				cardType = 'mastercard';
			}
			// Amex.
			else if (/^3[47]/.test(number)) {
				cardType = 'amex';
			}
			// Discover.
			else if (/^6(?:011|5)/.test(number)) {
				cardType = 'discover';
			}

			if (cardType) {
				$('.bkx-card-' + cardType).addClass('active');
			}
		},

		/**
		 * Validate form before submission.
		 *
		 * @param {Event} e Submit event.
		 * @returns {boolean}
		 */
		validateForm: function(e) {
			const $form = $(this);
			const $fields = $form.find('.bkx-payflow-fields');

			if (!$fields.length) {
				return true;
			}

			// Clear previous errors.
			$fields.find('.bkx-payflow-error').remove();
			$fields.find('input').removeClass('error');

			const cardNumber = $('#bkx_card_number').val().replace(/\D/g, '');
			const expiry = $('#bkx_card_expiry').val();
			const cvv = $('#bkx_card_cvv').val();

			let errors = [];

			// Validate card number.
			if (!cardNumber || cardNumber.length < 13 || !BKXPayflow.luhnCheck(cardNumber)) {
				$('#bkx_card_number').addClass('error');
				errors.push(bkxPayflow.i18n.invalid_card);
			}

			// Validate expiry.
			if (!expiry || !/^\d{2}\/\d{2}$/.test(expiry)) {
				$('#bkx_card_expiry').addClass('error');
				errors.push('Invalid expiration date.');
			} else {
				const parts = expiry.split('/');
				const month = parseInt(parts[0], 10);
				const year = parseInt('20' + parts[1], 10);
				const now = new Date();
				const cardDate = new Date(year, month - 1);

				if (cardDate < now) {
					$('#bkx_card_expiry').addClass('error');
					errors.push('Card has expired.');
				}
			}

			// Validate CVV.
			if (!cvv || cvv.length < 3) {
				$('#bkx_card_cvv').addClass('error');
				errors.push('Invalid CVV.');
			}

			if (errors.length > 0) {
				e.preventDefault();
				$fields.append('<div class="bkx-payflow-error">' + errors[0] + '</div>');
				return false;
			}

			// Add processing state.
			$fields.addClass('processing');

			return true;
		},

		/**
		 * Luhn algorithm check.
		 *
		 * @param {string} number Card number.
		 * @returns {boolean}
		 */
		luhnCheck: function(number) {
			let sum = 0;
			let isEven = false;

			for (let i = number.length - 1; i >= 0; i--) {
				let digit = parseInt(number.charAt(i), 10);

				if (isEven) {
					digit *= 2;
					if (digit > 9) {
						digit -= 9;
					}
				}

				sum += digit;
				isEven = !isEven;
			}

			return (sum % 10) === 0;
		}
	};

	$(document).ready(function() {
		BKXPayflow.init();
	});

})(jQuery);
