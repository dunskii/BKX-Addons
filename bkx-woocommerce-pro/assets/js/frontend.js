/**
 * WooCommerce Pro Frontend JavaScript.
 *
 * @package BookingX\WooCommercePro
 * @since   1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Booking Form Handler.
	 */
	var BKXWooBooking = {
		form: null,
		selectedDate: null,
		selectedTime: null,
		selectedSeat: null,
		selectedExtras: [],

		init: function() {
			this.form = $('.bkx-woo-booking-form');

			if (!this.form.length) {
				return;
			}

			this.bindEvents();
		},

		bindEvents: function() {
			// Date selection.
			this.form.on('change', '.bkx-booking-date', this.onDateChange.bind(this));

			// Time slot selection.
			this.form.on('click', '.bkx-time-slot:not(.unavailable)', this.onTimeSelect.bind(this));

			// Resource selection.
			this.form.on('change', '.bkx-resource-card input[type="radio"]', this.onResourceSelect.bind(this));
			this.form.on('click', '.bkx-resource-card', function() {
				$(this).find('input[type="radio"]').prop('checked', true).trigger('change');
			});

			// Extras selection.
			this.form.on('change', '.bkx-extra-item input[type="checkbox"]', this.onExtraChange.bind(this));
			this.form.on('click', '.bkx-extra-item', function(e) {
				if (e.target.type !== 'checkbox') {
					var $checkbox = $(this).find('input[type="checkbox"]');
					$checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
				}
			});

			// Add to cart.
			this.form.on('submit', this.onSubmit.bind(this));
		},

		onDateChange: function(e) {
			var date = $(e.target).val();
			this.selectedDate = date;

			// Load available time slots.
			this.loadTimeSlots(date);

			// Update hidden input.
			$('#bkx_booking_date').val(date);
		},

		loadTimeSlots: function(date) {
			var $container = this.form.find('.bkx-time-slots-container');
			var serviceId = this.form.find('input[name="bkx_service_id"]').val();
			var seatId = this.selectedSeat || this.form.find('input[name="bkx_seat_id"]').val();

			if (!date || !serviceId) {
				return;
			}

			$container.addClass('loading');

			// This would typically call an AJAX endpoint to get available slots.
			// For now, we'll use the BookingX availability if available.
			if (typeof bkxAvailability !== 'undefined') {
				bkxAvailability.getSlots(serviceId, seatId, date, function(slots) {
					BKXWooBooking.renderTimeSlots(slots, $container);
				});
			} else {
				$container.removeClass('loading');
			}
		},

		renderTimeSlots: function(slots, $container) {
			var html = '<div class="bkx-time-slots">';

			slots.forEach(function(slot) {
				var classes = 'bkx-time-slot';
				if (!slot.available) {
					classes += ' unavailable';
				}

				html += '<div class="' + classes + '" data-time="' + slot.time + '">';
				html += slot.formatted;
				html += '</div>';
			});

			html += '</div>';

			$container.html(html).removeClass('loading');
		},

		onTimeSelect: function(e) {
			var $slot = $(e.target).closest('.bkx-time-slot');
			var time = $slot.data('time');

			// Update selection.
			this.form.find('.bkx-time-slot').removeClass('selected');
			$slot.addClass('selected');
			this.selectedTime = time;

			// Update hidden input.
			$('#bkx_booking_time').val(time);

			// Enable add to cart if all required fields are set.
			this.validateForm();
		},

		onResourceSelect: function(e) {
			var $radio = $(e.target);
			var seatId = $radio.val();

			// Update selection UI.
			this.form.find('.bkx-resource-card').removeClass('selected');
			$radio.closest('.bkx-resource-card').addClass('selected');
			this.selectedSeat = seatId;

			// Reload time slots if date is selected.
			if (this.selectedDate) {
				this.loadTimeSlots(this.selectedDate);
			}

			this.validateForm();
		},

		onExtraChange: function(e) {
			var $checkbox = $(e.target);
			var extraId = $checkbox.val();
			var $item = $checkbox.closest('.bkx-extra-item');

			if ($checkbox.is(':checked')) {
				$item.addClass('selected');
				if (this.selectedExtras.indexOf(extraId) === -1) {
					this.selectedExtras.push(extraId);
				}
			} else {
				$item.removeClass('selected');
				var index = this.selectedExtras.indexOf(extraId);
				if (index > -1) {
					this.selectedExtras.splice(index, 1);
				}
			}

			// Update price display.
			this.updatePrice();
		},

		updatePrice: function() {
			var basePrice = parseFloat(this.form.data('base-price')) || 0;
			var extrasPrice = 0;

			this.selectedExtras.forEach(function(extraId) {
				var $extra = this.form.find('.bkx-extra-item input[value="' + extraId + '"]');
				var price = parseFloat($extra.data('price')) || 0;
				extrasPrice += price;
			}.bind(this));

			var totalPrice = basePrice + extrasPrice;

			// Update price display.
			this.form.find('.bkx-total-price').text(this.formatPrice(totalPrice));
		},

		formatPrice: function(price) {
			// Use WooCommerce formatting if available.
			if (typeof accounting !== 'undefined' && typeof wc_add_to_cart_params !== 'undefined') {
				return accounting.formatMoney(price, {
					symbol: wc_add_to_cart_params.currency_format_symbol,
					decimal: wc_add_to_cart_params.currency_format_decimal_sep,
					thousand: wc_add_to_cart_params.currency_format_thousand_sep,
					precision: wc_add_to_cart_params.currency_format_num_decimals,
					format: wc_add_to_cart_params.currency_format
				});
			}

			return '$' + price.toFixed(2);
		},

		validateForm: function() {
			var $button = this.form.find('.single_add_to_cart_button');
			var requiresDate = this.form.data('requires-date');
			var requiresSeat = this.form.data('requires-seat');
			var isValid = true;

			if (requiresDate && (!this.selectedDate || !this.selectedTime)) {
				isValid = false;
			}

			if (requiresSeat && !this.selectedSeat) {
				isValid = false;
			}

			$button.prop('disabled', !isValid);

			return isValid;
		},

		onSubmit: function(e) {
			if (!this.validateForm()) {
				e.preventDefault();
				alert(bkxWooSettings.i18n.error);
				return false;
			}

			// If using AJAX add to cart.
			if (this.form.hasClass('bkx-ajax-add-to-cart')) {
				e.preventDefault();
				this.ajaxAddToCart();
				return false;
			}

			return true;
		},

		ajaxAddToCart: function() {
			var $button = this.form.find('.single_add_to_cart_button');
			var $message = this.form.find('.bkx-cart-message');

			$button.addClass('loading').prop('disabled', true);
			$message.removeClass('show');

			var data = {
				action: 'bkx_woo_add_booking_to_cart',
				nonce: bkxWooSettings.nonce,
				service_id: this.form.find('input[name="bkx_service_id"]').val(),
				seat_id: this.selectedSeat || this.form.find('input[name="bkx_seat_id"]').val(),
				booking_date: this.selectedDate,
				booking_time: this.selectedTime,
				extras: this.selectedExtras
			};

			$.ajax({
				url: bkxWooSettings.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					$button.removeClass('loading').prop('disabled', false);

					if (response.success) {
						$button.addClass('added');

						// Show success message.
						var message = bkxWooSettings.i18n.addedToCart + ' ';
						message += '<a href="' + bkxWooSettings.cartUrl + '">' + bkxWooSettings.i18n.viewCart + '</a> | ';
						message += '<a href="' + bkxWooSettings.checkoutUrl + '">' + bkxWooSettings.i18n.checkout + '</a>';

						$message.html(message).addClass('show');

						// Update cart fragments if using WooCommerce.
						$(document.body).trigger('wc_fragment_refresh');

						// Handle redirect based on settings.
						switch (bkxWooSettings.cartBehavior) {
							case 'checkout':
								window.location.href = bkxWooSettings.checkoutUrl;
								break;
							case 'cart':
								window.location.href = bkxWooSettings.cartUrl;
								break;
							default:
								// Stay on page.
								setTimeout(function() {
									$button.removeClass('added');
								}, 3000);
								break;
						}
					} else {
						alert(response.data.message || bkxWooSettings.i18n.error);
					}
				},
				error: function() {
					$button.removeClass('loading').prop('disabled', false);
					alert(bkxWooSettings.i18n.error);
				}
			});
		}
	};

	/**
	 * Service Catalog Handler.
	 */
	var BKXWooCatalog = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			$('.bkx-service-catalog').on('click', '.book-button', this.onBookClick.bind(this));
		},

		onBookClick: function(e) {
			var $button = $(e.target);
			var productId = $button.data('product-id');

			if (!productId) {
				return;
			}

			// Add to cart via AJAX if doesn't require date selection.
			if ($button.data('requires-date') === false) {
				e.preventDefault();
				this.quickAddToCart(productId, $button);
			}
		},

		quickAddToCart: function(productId, $button) {
			$button.addClass('loading');

			$.ajax({
				url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart'),
				type: 'POST',
				data: {
					product_id: productId,
					quantity: 1
				},
				success: function(response) {
					$button.removeClass('loading');

					if (response.error) {
						window.location.href = $button.attr('href');
						return;
					}

					$button.addClass('added');

					// Update cart fragments.
					$(document.body).trigger('added_to_cart', [response.fragments, response.cart_hash, $button]);

					setTimeout(function() {
						$button.removeClass('added');
					}, 3000);
				},
				error: function() {
					$button.removeClass('loading');
					window.location.href = $button.attr('href');
				}
			});
		}
	};

	/**
	 * My Account Bookings Handler.
	 */
	var BKXWooAccount = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			$('.bkx-bookings-list').on('click', '.cancel-booking', this.onCancelClick.bind(this));
			$('.bkx-bookings-list').on('click', '.reschedule-booking', this.onRescheduleClick.bind(this));
		},

		onCancelClick: function(e) {
			e.preventDefault();

			if (!confirm('Are you sure you want to cancel this booking?')) {
				return;
			}

			var $button = $(e.target);
			var bookingId = $button.data('booking-id');

			$button.prop('disabled', true);

			$.ajax({
				url: bkxWooSettings.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_woo_cancel_booking',
					nonce: bkxWooSettings.nonce,
					booking_id: bookingId
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Failed to cancel booking.');
						$button.prop('disabled', false);
					}
				},
				error: function() {
					alert('Failed to cancel booking. Please try again.');
					$button.prop('disabled', false);
				}
			});
		},

		onRescheduleClick: function(e) {
			e.preventDefault();

			var $button = $(e.target);
			var bookingId = $button.data('booking-id');
			var rescheduleUrl = $button.data('reschedule-url');

			if (rescheduleUrl) {
				window.location.href = rescheduleUrl;
			}
		}
	};

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function() {
		BKXWooBooking.init();
		BKXWooCatalog.init();
		BKXWooAccount.init();
	});

})(jQuery);
