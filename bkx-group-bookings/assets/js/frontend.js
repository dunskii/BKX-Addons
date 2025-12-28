/**
 * Group Bookings Frontend JavaScript
 *
 * @package BookingX\GroupBookings
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKXGroupFrontend = {
		debounceTimer: null,

		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.updateButtonStates();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Quantity input change.
			$(document).on('change input', '.bkx-quantity-input', this.handleQuantityChange);

			// Plus/minus buttons.
			$(document).on('click', '.bkx-qty-minus', this.decreaseQuantity);
			$(document).on('click', '.bkx-qty-plus', this.increaseQuantity);

			// Slot selection (for availability check).
			$(document).on('change', '[name="booking_date"], [name="booking_time"]', this.checkAvailability);
		},

		/**
		 * Handle quantity change.
		 *
		 * @param {Event} e Change event.
		 */
		handleQuantityChange: function(e) {
			const $input = $(this);
			let value = parseInt($input.val(), 10);
			const min = parseInt($input.attr('min'), 10);
			const max = parseInt($input.attr('max'), 10);

			// Enforce limits.
			if (isNaN(value) || value < min) {
				value = min;
				$input.val(value);
			} else if (value > max) {
				value = max;
				$input.val(value);
			}

			BKXGroupFrontend.updateButtonStates();
			BKXGroupFrontend.updatePrice($input);
		},

		/**
		 * Decrease quantity.
		 *
		 * @param {Event} e Click event.
		 */
		decreaseQuantity: function(e) {
			e.preventDefault();
			const $input = $(this).siblings('.bkx-quantity-input');
			const min = parseInt($input.attr('min'), 10);
			let value = parseInt($input.val(), 10) - 1;

			if (value >= min) {
				$input.val(value).trigger('change');
			}
		},

		/**
		 * Increase quantity.
		 *
		 * @param {Event} e Click event.
		 */
		increaseQuantity: function(e) {
			e.preventDefault();
			const $input = $(this).siblings('.bkx-quantity-input');
			const max = parseInt($input.attr('max'), 10);
			let value = parseInt($input.val(), 10) + 1;

			if (value <= max) {
				$input.val(value).trigger('change');
			}
		},

		/**
		 * Update button states.
		 */
		updateButtonStates: function() {
			$('.bkx-quantity-input').each(function() {
				const $input = $(this);
				const value = parseInt($input.val(), 10);
				const min = parseInt($input.attr('min'), 10);
				const max = parseInt($input.attr('max'), 10);
				const $wrapper = $input.closest('.bkx-quantity-control');

				$wrapper.find('.bkx-qty-minus').prop('disabled', value <= min);
				$wrapper.find('.bkx-qty-plus').prop('disabled', value >= max);
			});
		},

		/**
		 * Update price display.
		 *
		 * @param {jQuery} $input Quantity input.
		 */
		updatePrice: function($input) {
			const baseId = $input.data('base-id');
			const quantity = parseInt($input.val(), 10);
			const $wrapper = $input.closest('.bkx-quantity-wrapper');

			// Clear any pending request.
			clearTimeout(this.debounceTimer);

			// Debounce the AJAX request.
			this.debounceTimer = setTimeout(function() {
				$wrapper.addClass('loading');

				$.post(bkxGroupFrontend.ajaxurl, {
					action: 'bkx_calculate_group_price',
					nonce: bkxGroupFrontend.nonce,
					base_id: baseId,
					quantity: quantity
				}, function(response) {
					if (response.success) {
						$('#bkx-group-total').html(response.data.total_formatted);

						// Show breakdown if available.
						const $breakdown = $('#bkx-price-breakdown');
						if (response.data.breakdown && response.data.breakdown.length > 0) {
							let html = '';
							response.data.breakdown.forEach(function(item) {
								const valueClass = item.is_discount ? 'is-discount' : '';
								const prefix = item.value < 0 ? '' : '+';
								html += '<div class="bkx-breakdown-item ' + valueClass + '">';
								html += '<span class="bkx-breakdown-label">' + item.label + '</span>';
								html += '<span class="bkx-breakdown-value">' +
									(item.is_discount ? '-$' + Math.abs(item.value).toFixed(2) : '$' + item.value.toFixed(2)) +
									'</span>';
								html += '</div>';
							});
							$breakdown.html(html).show();
						} else {
							$breakdown.hide();
						}
					}
				}).always(function() {
					$wrapper.removeClass('loading');
				});
			}, 300);
		},

		/**
		 * Check availability for group size.
		 */
		checkAvailability: function() {
			const $form = $(this).closest('form');
			const seatId = $form.find('[name="seat_id"]').val();
			const date = $form.find('[name="booking_date"]').val();
			const time = $form.find('[name="booking_time"]').val();
			const quantity = parseInt($form.find('.bkx-quantity-input').val(), 10);

			if (!seatId || !date || !time || !quantity) {
				return;
			}

			const $wrapper = $form.find('.bkx-quantity-wrapper');
			$wrapper.find('.bkx-availability-warning').remove();

			$.post(bkxGroupFrontend.ajaxurl, {
				action: 'bkx_check_group_availability',
				nonce: bkxGroupFrontend.nonce,
				seat_id: seatId,
				date: date,
				time: time,
				quantity: quantity
			}, function(response) {
				if (response.success) {
					const statusClass = response.data.available ? 'success' : 'error';
					const $warning = $('<div class="bkx-availability-warning ' + statusClass + '">' +
						response.data.message + '</div>');
					$wrapper.append($warning);

					// Update max if needed.
					if (!response.data.available && response.data.max_available > 0) {
						$wrapper.find('.bkx-quantity-input').attr('max', response.data.max_available);
						BKXGroupFrontend.updateButtonStates();
					}
				}
			});
		}
	};

	$(document).ready(function() {
		BKXGroupFrontend.init();
	});

})(jQuery);
