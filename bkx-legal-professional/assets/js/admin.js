/**
 * Legal & Professional Services - Admin JavaScript
 *
 * @package BookingX\LegalProfessional
 * @since   1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Fee arrangement visibility toggle.
	 */
	function initFeeArrangement() {
		const $select = $('#bkx_fee_arrangement');
		if (!$select.length) return;

		function updateVisibility() {
			const value = $select.val();
			const $form = $select.closest('form');

			$form.removeClass('bkx-fee-arrangement-hourly bkx-fee-arrangement-flat bkx-fee-arrangement-contingency bkx-fee-arrangement-hybrid');
			$form.addClass('bkx-fee-arrangement-' + value);
		}

		$select.on('change', updateVisibility);
		updateVisibility();
	}

	/**
	 * Attorney admissions repeater.
	 */
	function initAdmissionsRepeater() {
		const $container = $('#bkx-admissions-list');
		const $addBtn = $('#bkx-add-admission');

		if (!$container.length) return;

		// Add new admission row
		$addBtn.on('click', function() {
			const index = $container.find('.bkx-admission-row').length;
			const html = `
				<div class="bkx-admission-row" style="margin-bottom: 10px;">
					<input type="text" name="bkx_admissions[${index}][state]" value="" placeholder="State" style="width: 120px;">
					<input type="text" name="bkx_admissions[${index}][number]" value="" placeholder="Bar Number" style="width: 120px;">
					<input type="text" name="bkx_admissions[${index}][year]" value="" placeholder="Year" style="width: 80px;">
					<button type="button" class="button bkx-remove-admission">&times;</button>
				</div>
			`;
			$container.append(html);
		});

		// Remove admission row
		$container.on('click', '.bkx-remove-admission', function() {
			$(this).closest('.bkx-admission-row').remove();
		});
	}

	/**
	 * Create time entry from booking.
	 */
	function initTimeEntry() {
		const $btn = $('#bkx-create-time-entry');
		if (!$btn.length) return;

		$btn.on('click', function() {
			const $spinner = $(this).next('.spinner');
			const postId = $('#post_ID').val();
			const matterId = $('#bkx_matter_id').val();
			const hours = $('#bkx_time_hours').val() || 0;
			const minutes = $('#bkx_time_minutes').val() || 0;
			const activityCode = $('#bkx_activity_code').val();
			const description = $('#bkx_time_description').val();
			const billable = $('input[name="bkx_time_billable"]').is(':checked') ? 1 : 0;

			if (!matterId) {
				alert('Please select a matter first.');
				return;
			}

			if (parseInt(hours) === 0 && parseInt(minutes) === 0) {
				alert('Please enter time.');
				return;
			}

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'bkx_legal_create_time_entry',
					nonce: bkxLegal.nonce,
					booking_id: postId,
					matter_id: matterId,
					hours: hours,
					minutes: minutes,
					activity_code: activityCode,
					description: description,
					billable: billable
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'An error occurred.');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});
	}

	/**
	 * License activation.
	 */
	function initLicense() {
		const $activateBtn = $('#bkx-activate-license');
		const $deactivateBtn = $('#bkx-deactivate-license');
		const $licenseField = $('#license_key');

		if (!$activateBtn.length) return;

		$activateBtn.on('click', function() {
			const license = $licenseField.val();
			if (!license) {
				alert('Please enter a license key.');
				return;
			}

			$(this).prop('disabled', true).text('Activating...');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'bkx_legal_activate_license',
					nonce: bkxLegal.nonce,
					license: license
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Activation failed.');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$activateBtn.prop('disabled', false).text('Activate License');
				}
			});
		});

		$deactivateBtn.on('click', function() {
			if (!confirm('Are you sure you want to deactivate this license?')) {
				return;
			}

			$(this).prop('disabled', true).text('Deactivating...');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'bkx_legal_deactivate_license',
					nonce: bkxLegal.nonce
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Deactivation failed.');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$deactivateBtn.prop('disabled', false).text('Deactivate License');
				}
			});
		});
	}

	/**
	 * Conflict check.
	 */
	function initConflictCheck() {
		const $btn = $('#bkx-run-conflict-check');
		if (!$btn.length) return;

		$btn.on('click', function() {
			const matterId = $(this).data('matter-id');
			const $results = $('#bkx-conflict-results');
			const $spinner = $(this).next('.spinner');

			$(this).prop('disabled', true);
			$spinner.addClass('is-active');
			$results.html('<p>Running conflict check...</p>');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'bkx_legal_run_conflict_check',
					nonce: bkxLegal.nonce,
					matter_id: matterId
				},
				success: function(response) {
					if (response.success) {
						$results.html(response.data.html);
					} else {
						$results.html('<p class="error">' + (response.data.message || 'An error occurred.') + '</p>');
					}
				},
				error: function() {
					$results.html('<p class="error">An error occurred. Please try again.</p>');
				},
				complete: function() {
					$btn.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		});
	}

	/**
	 * Document upload.
	 */
	function initDocumentUpload() {
		const $form = $('#bkx-document-upload-form');
		if (!$form.length) return;

		$form.on('submit', function(e) {
			e.preventDefault();

			const formData = new FormData(this);
			formData.append('action', 'bkx_legal_upload_document');
			formData.append('nonce', bkxLegal.nonce);

			const $submit = $form.find('button[type="submit"]');
			const $progress = $form.find('.upload-progress');

			$submit.prop('disabled', true).text('Uploading...');
			$progress.show();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				xhr: function() {
					const xhr = new window.XMLHttpRequest();
					xhr.upload.addEventListener('progress', function(e) {
						if (e.lengthComputable) {
							const percent = Math.round((e.loaded / e.total) * 100);
							$progress.find('.progress-bar').css('width', percent + '%');
							$progress.find('.progress-text').text(percent + '%');
						}
					}, false);
					return xhr;
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Upload failed.');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$submit.prop('disabled', false).text('Upload Document');
					$progress.hide();
				}
			});
		});
	}

	/**
	 * Deadline management.
	 */
	function initDeadlines() {
		// Complete deadline
		$(document).on('click', '.bkx-complete-deadline', function() {
			const deadlineId = $(this).data('deadline-id');
			const $row = $(this).closest('.bkx-deadline-item');

			if (!confirm('Mark this deadline as completed?')) {
				return;
			}

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'bkx_legal_complete_deadline',
					nonce: bkxLegal.nonce,
					deadline_id: deadlineId
				},
				success: function(response) {
					if (response.success) {
						$row.addClass('completed');
						$row.find('.bkx-complete-deadline').remove();
					} else {
						alert(response.data.message || 'An error occurred.');
					}
				}
			});
		});
	}

	/**
	 * Invoice actions.
	 */
	function initInvoices() {
		// Send invoice
		$(document).on('click', '.bkx-send-invoice', function() {
			const invoiceId = $(this).data('invoice-id');
			const $btn = $(this);

			if (!confirm('Send this invoice to the client?')) {
				return;
			}

			$btn.prop('disabled', true).text('Sending...');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'bkx_legal_send_invoice',
					nonce: bkxLegal.nonce,
					invoice_id: invoiceId
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Failed to send invoice.');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$btn.prop('disabled', false).text('Send Invoice');
				}
			});
		});

		// Record payment
		$(document).on('click', '.bkx-record-payment', function() {
			const invoiceId = $(this).data('invoice-id');
			const balance = $(this).data('balance');

			$('#bkx-payment-invoice-id').val(invoiceId);
			$('#bkx-payment-amount').val(balance);
			$('#bkx-payment-modal').show();
		});

		$('.bkx-modal-close, .bkx-modal-overlay').on('click', function(e) {
			if (e.target === this) {
				$('.bkx-modal-overlay').hide();
			}
		});

		$('#bkx-payment-form').on('submit', function(e) {
			e.preventDefault();

			const $form = $(this);
			const $submit = $form.find('button[type="submit"]');

			$submit.prop('disabled', true).text('Recording...');

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'bkx_legal_record_payment',
					nonce: bkxLegal.nonce,
					invoice_id: $('#bkx-payment-invoice-id').val(),
					amount: $('#bkx-payment-amount').val(),
					payment_date: $('#bkx-payment-date').val(),
					payment_method: $('#bkx-payment-method').val(),
					reference: $('#bkx-payment-reference').val()
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Failed to record payment.');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				},
				complete: function() {
					$submit.prop('disabled', false).text('Record Payment');
				}
			});
		});
	}

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function() {
		initFeeArrangement();
		initAdmissionsRepeater();
		initTimeEntry();
		initLicense();
		initConflictCheck();
		initDocumentUpload();
		initDeadlines();
		initInvoices();
	});

})(jQuery);
