/**
 * WooCommerce Pro Admin JavaScript.
 *
 * @package BookingX\WooCommercePro
 * @since   1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Sync Dashboard Handler.
	 */
	var BKXWooSync = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			$('#bkx-sync-all').on('click', this.syncAll.bind(this));
			$('#bkx-refresh-stats').on('click', this.refreshStats.bind(this));
		},

		syncAll: function(e) {
			e.preventDefault();

			var $button = $(e.target);
			var $results = $('#bkx-sync-results');

			$button.prop('disabled', true).append('<span class="bkx-loading"></span>');
			$results.hide().removeClass('success error');

			$.ajax({
				url: bkxWooAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_woo_sync_products',
					nonce: bkxWooAdmin.nonce
				},
				success: function(response) {
					$button.prop('disabled', false).find('.bkx-loading').remove();

					if (response.success) {
						var data = response.data;
						var message = '<strong>' + bkxWooAdmin.i18n.syncComplete + '</strong><br>';
						message += 'Created: ' + data.created + '<br>';
						message += 'Updated: ' + data.updated + '<br>';
						message += 'Skipped: ' + data.skipped;

						if (data.errors && data.errors.length > 0) {
							message += '<br><br><strong>Errors:</strong><br>';
							message += data.errors.join('<br>');
						}

						$results.html(message).addClass('success').show();

						// Refresh stats.
						BKXWooSync.refreshStats();
					} else {
						$results.html(response.data.message || bkxWooAdmin.i18n.syncError)
							.addClass('error').show();
					}
				},
				error: function() {
					$button.prop('disabled', false).find('.bkx-loading').remove();
					$results.html(bkxWooAdmin.i18n.syncError).addClass('error').show();
				}
			});
		},

		refreshStats: function(e) {
			if (e) e.preventDefault();

			$.ajax({
				url: bkxWooAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_woo_get_sync_stats',
					nonce: bkxWooAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						var data = response.data;

						// Update stat cards.
						$('.stat-card').each(function() {
							var $card = $(this);
							var label = $card.find('h3').text().toLowerCase();

							if (label.indexOf('service') > -1) {
								$card.find('.stat-value').text(data.total_services);
							} else if (label.indexOf('product') > -1) {
								$card.find('.stat-value').text(data.total_products);
							} else if (label.indexOf('synced') > -1 && label.indexOf('un') === -1) {
								$card.find('.stat-value').text(data.synced_services);
							} else if (label.indexOf('unsync') > -1) {
								$card.find('.stat-value')
									.text(data.unsynced_services)
									.toggleClass('warning', data.unsynced_services > 0);
							}
						});

						// Update progress bar.
						$('.progress-fill').css('width', data.sync_percentage + '%');
						$('.progress-text').text(data.sync_percentage + '% Synchronized');
					}
				}
			});
		}
	};

	/**
	 * Product Edit Handler.
	 */
	var BKXWooProduct = {
		init: function() {
			this.bindEvents();
			this.initProductType();
		},

		bindEvents: function() {
			$('#_bkx_service_id').on('change', this.onServiceChange.bind(this));
		},

		initProductType: function() {
			// Show/hide booking options based on product type.
			$('#product-type').on('change', function() {
				var type = $(this).val();

				if (type === 'bkx_booking') {
					$('.show_if_bkx_booking').show();
					$('.hide_if_bkx_booking').hide();

					// Set virtual.
					$('#_virtual').prop('checked', true).trigger('change');

					// Hide stock tab.
					$('.inventory_options').hide();
				} else {
					$('.show_if_bkx_booking').hide();
				}
			}).trigger('change');
		},

		onServiceChange: function(e) {
			var serviceId = $(e.target).val();

			if (!serviceId) {
				return;
			}

			// Could fetch service data and auto-fill fields if needed.
		}
	};

	/**
	 * Order Meta Box Handler.
	 */
	var BKXWooOrder = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			$('.bkx-create-booking').on('click', this.createBooking.bind(this));
			$('.bkx-cancel-booking').on('click', this.cancelBooking.bind(this));
		},

		createBooking: function(e) {
			e.preventDefault();

			if (!confirm('Create a booking for this order?')) {
				return;
			}

			var $button = $(e.target);
			var orderId = $button.data('order-id');

			$button.prop('disabled', true);

			// Trigger the order action via form submission.
			$('select[name="wc_order_action"]').val('bkx_create_booking');
			$('button.save_order').trigger('click');
		},

		cancelBooking: function(e) {
			e.preventDefault();

			if (!confirm('Cancel the booking associated with this order?')) {
				return;
			}

			var $button = $(e.target);

			$button.prop('disabled', true);

			$('select[name="wc_order_action"]').val('bkx_cancel_booking');
			$('button.save_order').trigger('click');
		}
	};

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function() {
		// Initialize sync dashboard if on the right page.
		if ($('.bkx-sync-dashboard').length) {
			BKXWooSync.init();
		}

		// Initialize product edit page.
		if ($('#woocommerce-product-data').length) {
			BKXWooProduct.init();
		}

		// Initialize order page.
		if ($('#bkx-order-bookings').length) {
			BKXWooOrder.init();
		}
	});

})(jQuery);
