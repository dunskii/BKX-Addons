/**
 * Group Bookings Admin JavaScript
 *
 * @package BookingX\GroupBookings
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKXGroupAdmin = {
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
			// Add pricing tier.
			$('#bkx-add-tier-btn').on('click', this.addTier);

			// Delete pricing tier.
			$(document).on('click', '.bkx-delete-tier', this.deleteTier);

			// Discount type change.
			$('[name="bkx_group_bookings_settings[group_discount_type]"]').on('change', this.updateDiscountSuffix);
		},

		/**
		 * Add pricing tier.
		 *
		 * @param {Event} e Click event.
		 */
		addTier: function(e) {
			e.preventDefault();

			const $form = $(this).closest('.bkx-add-tier');
			const postId = $('#post_ID').val();

			const data = {
				action: 'bkx_add_pricing_tier',
				nonce: bkxGroupAdmin.nonce,
				base_id: postId,
				min_quantity: $form.find('[name="new_tier_min"]').val(),
				max_quantity: $form.find('[name="new_tier_max"]').val(),
				price_type: $form.find('[name="new_tier_price_type"]').val(),
				price: $form.find('[name="new_tier_price"]').val()
			};

			if (!data.min_quantity || !data.max_quantity || !data.price) {
				alert('Please fill in all tier fields.');
				return;
			}

			$(this).prop('disabled', true).text('Adding...');

			$.post(ajaxurl, data, function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data || bkxGroupAdmin.i18n.error);
				}
			}).always(function() {
				$('#bkx-add-tier-btn').prop('disabled', false).text('Add');
			});
		},

		/**
		 * Delete pricing tier.
		 *
		 * @param {Event} e Click event.
		 */
		deleteTier: function(e) {
			e.preventDefault();

			if (!confirm('Are you sure you want to delete this tier?')) {
				return;
			}

			const $btn = $(this);
			const tierId = $btn.data('id');

			$btn.prop('disabled', true);

			$.post(ajaxurl, {
				action: 'bkx_delete_pricing_tier',
				nonce: bkxGroupAdmin.nonce,
				tier_id: tierId
			}, function(response) {
				if (response.success) {
					$btn.closest('tr').fadeOut(300, function() {
						$(this).remove();
						if ($('#bkx-tiers-table tbody tr').length === 0) {
							$('#bkx-tiers-table tbody').html(
								'<tr class="bkx-no-tiers"><td colspan="5">No pricing tiers defined.</td></tr>'
							);
						}
					});
				} else {
					alert(response.data || bkxGroupAdmin.i18n.error);
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Update discount suffix.
		 */
		updateDiscountSuffix: function() {
			const type = $(this).val();
			$('.bkx-discount-suffix').text(type === 'percentage' ? '%' : '$');
		}
	};

	$(document).ready(function() {
		BKXGroupAdmin.init();
	});

})(jQuery);
