/**
 * Hold Blocks Admin JavaScript
 *
 * @package BookingX\HoldBlocks
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKXHoldBlocks = {
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
			// Toggle time fields.
			$('#bkx_block_all_day').on('change', this.toggleTimeFields);

			// Add block form.
			$('#bkx-add-block-form').on('submit', this.addBlock);

			// Delete block.
			$(document).on('click', '.bkx-delete-block', this.deleteBlock);

			// Filter by seat.
			$('#bkx_filter_seat').on('change', this.filterBySeat);
		},

		/**
		 * Toggle time fields visibility.
		 */
		toggleTimeFields: function() {
			const $timeFields = $('.bkx-time-fields');

			if ($(this).is(':checked')) {
				$timeFields.slideUp(200);
			} else {
				$timeFields.slideDown(200);
			}
		},

		/**
		 * Add a block.
		 *
		 * @param {Event} e Submit event.
		 */
		addBlock: function(e) {
			e.preventDefault();

			const $form = $(this);
			const $submit = $form.find('button[type="submit"]');
			const formData = $form.serializeArray();

			// Add nonce and action.
			formData.push({ name: 'action', value: 'bkx_add_hold_block' });
			formData.push({ name: 'nonce', value: bkxHoldBlocks.nonce });

			// Handle checkbox.
			const allDayChecked = $('#bkx_block_all_day').is(':checked');
			formData.push({ name: 'all_day', value: allDayChecked ? 'true' : 'false' });

			$submit.prop('disabled', true).text(bkxHoldBlocks.i18n.adding);

			$.post(bkxHoldBlocks.ajaxurl, $.param(formData), function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data || bkxHoldBlocks.i18n.error);
				}
			}).fail(function() {
				alert(bkxHoldBlocks.i18n.error);
			}).always(function() {
				$submit.prop('disabled', false).text('Add Block');
			});
		},

		/**
		 * Delete a block.
		 *
		 * @param {Event} e Click event.
		 */
		deleteBlock: function(e) {
			e.preventDefault();

			if (!confirm(bkxHoldBlocks.i18n.confirmDelete)) {
				return;
			}

			const $btn = $(this);
			const blockId = $btn.data('id');

			$btn.prop('disabled', true);

			$.post(bkxHoldBlocks.ajaxurl, {
				action: 'bkx_delete_hold_block',
				nonce: bkxHoldBlocks.nonce,
				block_id: blockId
			}, function(response) {
				if (response.success) {
					$btn.closest('tr').fadeOut(300, function() {
						$(this).remove();

						// Check if table is empty.
						if ($('#bkx-blocks-table tbody tr').length === 0) {
							$('#bkx-blocks-table').replaceWith(
								'<p class="bkx-no-blocks">No blocks scheduled.</p>'
							);
						}
					});
				} else {
					alert(response.data || bkxHoldBlocks.i18n.error);
					$btn.prop('disabled', false);
				}
			}).fail(function() {
				alert(bkxHoldBlocks.i18n.error);
				$btn.prop('disabled', false);
			});
		},

		/**
		 * Filter blocks by seat.
		 */
		filterBySeat: function() {
			const seatId = $(this).val();
			const url = new URL(window.location.href);

			if (seatId) {
				url.searchParams.set('seat_id', seatId);
			} else {
				url.searchParams.delete('seat_id');
			}

			window.location.href = url.toString();
		}
	};

	$(document).ready(function() {
		BKXHoldBlocks.init();
	});

})(jQuery);
