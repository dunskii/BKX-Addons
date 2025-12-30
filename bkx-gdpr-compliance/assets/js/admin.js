/**
 * GDPR Compliance - Admin JavaScript
 *
 * @package BookingX\GdprCompliance
 */

/* global jQuery, bkxGdprAdmin */
(function($) {
	'use strict';

	const BkxGdpr = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			// Modal events
			$(document).on('click', '.bkx-gdpr-modal-close, .bkx-gdpr-modal-cancel', this.closeModal);
			$(document).on('click', '.bkx-gdpr-modal', this.closeModalOnBackground);
			$(document).on('keydown', this.handleEscape);

			// Request processing
			$(document).on('click', '.bkx-gdpr-process', this.processRequest);

			// Breach reporting
			$(document).on('click', '#bkx-gdpr-report-breach', this.openBreachModal);
			$(document).on('submit', '#bkx-gdpr-breach-form', this.submitBreach);

			// Policy generation
			$(document).on('click', '.bkx-gdpr-generate-policy', this.generatePolicy);
			$(document).on('click', '#bkx-gdpr-copy-policy', this.copyPolicy);
		},

		closeModal: function(e) {
			e.preventDefault();
			$('.bkx-gdpr-modal').hide();
		},

		closeModalOnBackground: function(e) {
			if ($(e.target).hasClass('bkx-gdpr-modal')) {
				$('.bkx-gdpr-modal').hide();
			}
		},

		handleEscape: function(e) {
			if (e.key === 'Escape') {
				$('.bkx-gdpr-modal').hide();
			}
		},

		processRequest: function(e) {
			e.preventDefault();

			const $btn = $(this);
			const requestId = $btn.data('id');
			const action = $btn.data('action');
			const $row = $btn.closest('tr');

			const confirmMsg = action === 'reject'
				? bkxGdprAdmin.i18n.confirmReject || 'Are you sure you want to reject this request?'
				: bkxGdprAdmin.i18n.confirmProcess || 'Process this request now?';

			if (!confirm(confirmMsg)) {
				return;
			}

			$btn.prop('disabled', true).addClass('updating-message');

			$.ajax({
				url: bkxGdprAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_gdpr_process_request',
					nonce: bkxGdprAdmin.nonce,
					request_id: requestId,
					request_action: action
				},
				success: function(response) {
					$btn.prop('disabled', false).removeClass('updating-message');
					if (response.success) {
						alert(bkxGdprAdmin.i18n.success || 'Success!');
						location.reload();
					} else {
						alert(response.data.message || bkxGdprAdmin.i18n.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false).removeClass('updating-message');
					alert(bkxGdprAdmin.i18n.error || 'An error occurred.');
				}
			});
		},

		openBreachModal: function(e) {
			e.preventDefault();
			$('#bkx-gdpr-breach-modal').show();
		},

		submitBreach: function(e) {
			e.preventDefault();

			const $form = $(this);
			const $submit = $form.find('button[type="submit"]');

			$submit.prop('disabled', true).addClass('updating-message');

			$.ajax({
				url: bkxGdprAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_gdpr_report_breach',
					nonce: bkxGdprAdmin.nonce,
					breach_date: $('#breach_date').val(),
					discovered_date: $('#discovered_date').val(),
					nature: $('#nature').val(),
					data_affected: $('#data_affected').val(),
					subjects_affected: $('#subjects_affected').val(),
					consequences: $('#consequences').val(),
					measures_taken: $('#measures_taken').val()
				},
				success: function(response) {
					$submit.prop('disabled', false).removeClass('updating-message');
					if (response.success) {
						alert(bkxGdprAdmin.i18n.success || 'Breach reported successfully!');
						$('#bkx-gdpr-breach-modal').hide();
						location.reload();
					} else {
						alert(response.data.message || bkxGdprAdmin.i18n.error);
					}
				},
				error: function() {
					$submit.prop('disabled', false).removeClass('updating-message');
					alert(bkxGdprAdmin.i18n.error || 'An error occurred.');
				}
			});
		},

		generatePolicy: function(e) {
			e.preventDefault();

			const $btn = $(this);
			const policyType = $btn.data('type');

			$btn.prop('disabled', true).addClass('updating-message');

			$.ajax({
				url: bkxGdprAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_gdpr_generate_policy',
					nonce: bkxGdprAdmin.nonce,
					policy_type: policyType
				},
				success: function(response) {
					$btn.prop('disabled', false).removeClass('updating-message');
					if (response.success) {
						$('#bkx-gdpr-policy-content').html(response.data.content);
						$('#bkx-gdpr-policy-preview').show();
						$('html, body').animate({
							scrollTop: $('#bkx-gdpr-policy-preview').offset().top - 50
						}, 500);
					} else {
						alert(response.data.message || bkxGdprAdmin.i18n.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false).removeClass('updating-message');
					alert(bkxGdprAdmin.i18n.error || 'An error occurred.');
				}
			});
		},

		copyPolicy: function(e) {
			e.preventDefault();

			const content = $('#bkx-gdpr-policy-content').html();
			const $temp = $('<textarea>');

			$('body').append($temp);
			$temp.val(content).select();

			try {
				document.execCommand('copy');
				alert(bkxGdprAdmin.i18n.copied || 'Copied to clipboard!');
			} catch (err) {
				alert('Failed to copy. Please select and copy manually.');
			}

			$temp.remove();
		}
	};

	$(document).ready(function() {
		BkxGdpr.init();
	});

})(jQuery);
