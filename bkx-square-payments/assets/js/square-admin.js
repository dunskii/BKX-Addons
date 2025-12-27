/**
 * Square Admin JavaScript
 *
 * @package BookingX\SquarePayments
 */

(function ($) {
	'use strict';

	/**
	 * Toggle sandbox/production credentials visibility.
	 */
	function toggleCredentials() {
		const mode = $('#square_mode').val();

		if (mode === 'sandbox') {
			$('.square-sandbox-row').show();
			$('.square-production-row').hide();
		} else {
			$('.square-sandbox-row').hide();
			$('.square-production-row').show();
		}
	}

	/**
	 * Test API connection.
	 */
	function testConnection() {
		const mode = $('#square_mode').val();
		const appId = $(`#square_${mode}_application_id`).val();
		const accessToken = $(`#square_${mode}_access_token`).val();
		const locationId = $(`#square_${mode}_location_id`).val();

		if (!appId || !accessToken || !locationId) {
			alert('Please fill in all credentials before testing connection.');
			return;
		}

		const $button = $(this);
		const originalText = $button.text();

		$button.prop('disabled', true).text(bkxSquareAdmin.i18n.testConnection);

		$.ajax({
			url: bkxSquareAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'bkx_square_test_connection',
				nonce: bkxSquareAdmin.nonce,
				mode: mode,
				application_id: appId,
				access_token: accessToken,
				location_id: locationId
			},
			success: function (response) {
				if (response.success) {
					showTestResult($button, 'success', bkxSquareAdmin.i18n.connected);
				} else {
					showTestResult($button, 'error', response.data.message || bkxSquareAdmin.i18n.error);
				}
			},
			error: function () {
				showTestResult($button, 'error', bkxSquareAdmin.i18n.error);
			},
			complete: function () {
				$button.prop('disabled', false).text(originalText);
			}
		});
	}

	/**
	 * Show test result.
	 */
	function showTestResult($button, type, message) {
		const $result = $('<span>', {
			class: `bkx-square-test-result ${type}`,
			text: message
		});

		// Remove existing result
		$button.siblings('.bkx-square-test-result').remove();

		// Add new result
		$button.after($result);

		// Remove after 5 seconds
		setTimeout(() => {
			$result.fadeOut(() => $result.remove());
		}, 5000);
	}

	/**
	 * Document ready.
	 */
	$(document).ready(function () {
		// Toggle credentials on mode change
		$('#square_mode').on('change', toggleCredentials);

		// Initialize on page load
		toggleCredentials();

		// Add test connection button if credentials exist
		const modes = ['sandbox', 'production'];
		modes.forEach(mode => {
			const $locationField = $(`#square_${mode}_location_id`);
			if ($locationField.length && !$locationField.siblings('.bkx-square-test-connection').length) {
				const $testButton = $('<button>', {
					type: 'button',
					class: 'button bkx-square-test-connection',
					text: 'Test Connection'
				});

				$testButton.on('click', testConnection);
				$locationField.after($testButton);
			}
		});
	});

})(jQuery);
