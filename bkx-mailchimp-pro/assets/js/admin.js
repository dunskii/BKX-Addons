/**
 * Mailchimp Pro Admin Scripts
 *
 * @package BookingX\MailchimpPro
 * @since   1.0.0
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		/**
		 * Test connection button
		 */
		$('#test-connection').on('click', function() {
			const $btn = $(this);
			const $status = $('#connection-status');
			const apiKey = $('#api_key').val();

			$btn.prop('disabled', true).text(bkxMailchimpPro.i18n.testing);
			$status.removeClass('success error').text('');

			$.ajax({
				url: bkxMailchimpPro.url,
				method: 'POST',
				data: {
					action: bkxMailchimpPro.actions.test_connection,
					nonce: bkxMailchimpPro.nonces.test_connection,
					api_key: apiKey
				},
				success: function(response) {
					if (response.success) {
						$status.addClass('success').text(bkxMailchimpPro.i18n.success);
						// Refresh lists
						refreshLists();
					} else {
						$status.addClass('error').text(response.data.message || bkxMailchimpPro.i18n.failed);
					}
				},
				error: function() {
					$status.addClass('error').text(bkxMailchimpPro.i18n.failed);
				},
				complete: function() {
					$btn.prop('disabled', false).text($btn.data('original-text') || 'Test Connection');
				}
			});
		});

		/**
		 * Refresh lists button
		 */
		$('#refresh-lists').on('click', function() {
			refreshLists();
		});

		/**
		 * Manual sync buttons
		 */
		$('#manual-sync-all').on('click', function() {
			runManualSync('all');
		});

		$('#manual-sync-recent').on('click', function() {
			runManualSync('recent');
		});

		/**
		 * Refresh Mailchimp lists
		 */
		function refreshLists() {
			const $select = $('#default_list_id');
			const currentValue = $select.val();

			$.ajax({
				url: bkxMailchimpPro.url,
				method: 'POST',
				data: {
					action: bkxMailchimpPro.actions.get_lists,
					nonce: bkxMailchimpPro.nonces.get_lists
				},
				success: function(response) {
					if (response.success && response.data.lists) {
						$select.empty().append('<option value="">Select a list...</option>');

						response.data.lists.forEach(function(list) {
							const $option = $('<option>')
								.val(list.id)
								.text(list.name);

							if (list.id === currentValue) {
								$option.prop('selected', true);
							}

							$select.append($option);
						});
					}
				}
			});
		}

		/**
		 * Run manual sync
		 */
		function runManualSync(syncType) {
			const $status = $('#sync-status');

			$status.removeClass('success error').addClass('show').text(bkxMailchimpPro.i18n.syncing);

			$.ajax({
				url: bkxMailchimpPro.url,
				method: 'POST',
				data: {
					action: bkxMailchimpPro.actions.manual_sync,
					nonce: bkxMailchimpPro.nonces.manual_sync,
					sync_type: syncType
				},
				success: function(response) {
					if (response.success) {
						$status.addClass('success').text(response.data.message || bkxMailchimpPro.i18n.sync_complete);
					} else {
						$status.addClass('error').text(response.data.message || 'Sync failed');
					}
				},
				error: function() {
					$status.addClass('error').text('Sync failed');
				}
			});
		}
	});

})(jQuery);
