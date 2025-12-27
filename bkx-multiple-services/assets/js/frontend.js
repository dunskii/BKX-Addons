/**
 * Frontend JavaScript for Multiple Services
 *
 * @package BookingX\MultipleServices
 * @since   1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Multiple Services Handler
	 */
	const BkxMultipleServices = {

		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.updateSummary();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			// Service selection change
			$(document).on('change', '.bkx-service-checkbox, .bkx-service-dropdown', this.handleSelectionChange.bind(this));
		},

		/**
		 * Handle service selection change
		 */
		handleSelectionChange: function() {
			const selectedCount = this.getSelectedCount();
			const maxServices = bkxMultipleServices.settings.maxServices || 5;

			// Check max services limit
			if (selectedCount > maxServices) {
				alert(bkxMultipleServices.i18n.maxReached);
				$(event.target).prop('checked', false);
				return;
			}

			// Update summary
			this.updateSummary();

			// Check availability
			this.checkAvailability();
		},

		/**
		 * Get selected service count
		 */
		getSelectedCount: function() {
			return $('.bkx-service-checkbox:checked').length +
				   $('.bkx-service-dropdown option:selected').length;
		},

		/**
		 * Get selected service IDs
		 */
		getSelectedServiceIds: function() {
			const ids = [];

			$('.bkx-service-checkbox:checked').each(function() {
				ids.push($(this).val());
			});

			$('.bkx-service-dropdown option:selected').each(function() {
				ids.push($(this).val());
			});

			return ids;
		},

		/**
		 * Update bundle summary
		 */
		updateSummary: function() {
			const serviceIds = this.getSelectedServiceIds();

			if (serviceIds.length === 0) {
				$('.bkx-bundle-summary').hide();
				return;
			}

			// Show summary
			$('.bkx-bundle-summary').show();

			// Calculate price via AJAX
			$.ajax({
				url: bkxMultipleServices.url,
				type: 'POST',
				data: {
					action: bkxMultipleServices.actions.calculate_bundle_price,
					nonce: bkxMultipleServices.nonces.calculate_bundle_price,
					service_ids: serviceIds
				},
				success: function(response) {
					if (response.success) {
						$('.price-amount').text('$' + parseFloat(response.data.price).toFixed(2));
					}
				}
			});

			// Calculate duration (client-side for now)
			this.updateDuration();
		},

		/**
		 * Update duration display
		 */
		updateDuration: function() {
			let totalDuration = 0;
			const mode = bkxMultipleServices.settings.durationMode || 'sequential';

			if (mode === 'sequential') {
				$('.bkx-service-checkbox:checked, .bkx-service-dropdown option:selected').each(function() {
					const duration = parseInt($(this).data('duration')) || 0;
					totalDuration += duration;
				});
			} else {
				// For parallel/longest, use max duration
				$('.bkx-service-checkbox:checked, .bkx-service-dropdown option:selected').each(function() {
					const duration = parseInt($(this).data('duration')) || 0;
					totalDuration = Math.max(totalDuration, duration);
				});
			}

			$('.duration-amount').text(totalDuration + ' min');
		},

		/**
		 * Check availability for selected services
		 */
		checkAvailability: function() {
			const serviceIds = this.getSelectedServiceIds();
			const date = $('#booking_date').val();
			const seatId = $('#seat_id').val();

			if (!date || serviceIds.length === 0) {
				return;
			}

			$.ajax({
				url: bkxMultipleServices.url,
				type: 'POST',
				data: {
					action: bkxMultipleServices.actions.check_availability,
					nonce: bkxMultipleServices.nonces.check_availability,
					service_ids: serviceIds,
					date: date,
					seat_id: seatId
				},
				success: function(response) {
					if (response.success && !response.data.available) {
						alert('Selected services are not available at this time.');
					}
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		BkxMultipleServices.init();
	});

})(jQuery);
