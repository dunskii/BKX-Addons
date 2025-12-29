/**
 * Advanced Email Templates Admin JavaScript.
 *
 * @package BookingX\AdvancedEmailTemplates
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BkxEmailTemplates = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.initVariableAccordion();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			// Template form.
			$(document).on('submit', '#bkx-email-template-form', this.saveTemplate.bind(this));

			// Delete template.
			$(document).on('click', '.bkx-delete-template', this.deleteTemplate.bind(this));

			// Duplicate template.
			$(document).on('click', '.bkx-duplicate-template', this.duplicateTemplate.bind(this));

			// Preview.
			$(document).on('click', '#bkx-preview-template', this.previewTemplate.bind(this));

			// Send test.
			$(document).on('click', '#bkx-send-test', this.sendTestEmail.bind(this));

			// Insert variable.
			$(document).on('click', '.bkx-insert-variable', this.openVariablePicker.bind(this));
			$(document).on('click', '.bkx-pick-variable', this.insertVariable.bind(this));
			$(document).on('click', '.bkx-variable-item', this.insertVariableFromSidebar.bind(this));

			// Variable search.
			$(document).on('input', '#bkx-variable-search', this.filterVariables.bind(this));

			// Modal close.
			$(document).on('click', '.bkx-modal-close', this.closeModal.bind(this));
			$(document).on('click', '.bkx-modal', function(e) {
				if (e.target === this) {
					$(this).hide();
				}
			});

			// Preview device toggle.
			$(document).on('click', '.bkx-preview-device-toggle button', this.togglePreviewDevice.bind(this));

			// View log.
			$(document).on('click', '.bkx-view-log', this.viewLog.bind(this));
		},

		/**
		 * Initialize variable accordion.
		 */
		initVariableAccordion: function() {
			$(document).on('click', '.bkx-group-toggle', function() {
				$(this).closest('.bkx-variable-group').toggleClass('open');
			});
		},

		/**
		 * Save template.
		 *
		 * @param {Event} e
		 */
		saveTemplate: function(e) {
			e.preventDefault();
			const $form = $(e.currentTarget);
			const $button = $form.find('button[type="submit"]');
			const originalText = $button.text();

			// Get content from TinyMCE.
			let content = '';
			if (typeof tinyMCE !== 'undefined' && tinyMCE.get('template-content')) {
				content = tinyMCE.get('template-content').getContent();
			} else {
				content = $('#template-content').val();
			}

			$button.text(bkxEmailTemplates.i18n.savingTemplate).prop('disabled', true);

			$.ajax({
				url: bkxEmailTemplates.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_email_save_template',
					nonce: bkxEmailTemplates.nonce,
					template_id: $form.find('[name="template_id"]').val(),
					name: $form.find('[name="name"]').val(),
					slug: $form.find('[name="slug"]').val(),
					subject: $form.find('[name="subject"]').val(),
					preheader: $form.find('[name="preheader"]').val(),
					content: content,
					template_type: $form.find('[name="template_type"]').val(),
					trigger_event: $form.find('[name="trigger_event"]').val(),
					status: $form.find('[name="status"]').val()
				},
				success: function(response) {
					if (response.success) {
						$button.text(bkxEmailTemplates.i18n.saved);

						// Update template ID for new templates.
						if (response.data.template_id) {
							$form.find('[name="template_id"]').val(response.data.template_id);

							// Update URL.
							if (window.history.replaceState) {
								const newUrl = bkxEmailTemplates.ajaxUrl.replace('admin-ajax.php', 'admin.php') +
									'?page=bkx-email-templates&action=edit&template_id=' + response.data.template_id;
								window.history.replaceState({}, '', newUrl);
							}
						}

						setTimeout(function() {
							$button.text(originalText).prop('disabled', false);
						}, 2000);
					} else {
						alert(response.data.message || 'Error saving template.');
						$button.text(originalText).prop('disabled', false);
					}
				},
				error: function() {
					alert('Error saving template.');
					$button.text(originalText).prop('disabled', false);
				}
			});
		},

		/**
		 * Delete template.
		 *
		 * @param {Event} e
		 */
		deleteTemplate: function(e) {
			e.preventDefault();

			if (!confirm(bkxEmailTemplates.i18n.confirmDelete)) {
				return;
			}

			const templateId = $(e.currentTarget).data('id');

			$.ajax({
				url: bkxEmailTemplates.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_email_delete_template',
					nonce: bkxEmailTemplates.nonce,
					template_id: templateId
				},
				success: function(response) {
					if (response.success) {
						$('tr[data-id="' + templateId + '"]').fadeOut(function() {
							$(this).remove();
						});
					} else {
						alert(response.data.message || 'Error deleting template.');
					}
				}
			});
		},

		/**
		 * Duplicate template.
		 *
		 * @param {Event} e
		 */
		duplicateTemplate: function(e) {
			e.preventDefault();

			if (!confirm(bkxEmailTemplates.i18n.confirmDuplicate)) {
				return;
			}

			const templateId = $(e.currentTarget).data('id');

			$.ajax({
				url: bkxEmailTemplates.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_email_duplicate_template',
					nonce: bkxEmailTemplates.nonce,
					template_id: templateId
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Error duplicating template.');
					}
				}
			});
		},

		/**
		 * Preview template.
		 *
		 * @param {Event} e
		 */
		previewTemplate: function(e) {
			e.preventDefault();

			// Get content from TinyMCE.
			let content = '';
			if (typeof tinyMCE !== 'undefined' && tinyMCE.get('template-content')) {
				content = tinyMCE.get('template-content').getContent();
			} else {
				content = $('#template-content').val();
			}

			const subject = $('#template-subject').val();

			$.ajax({
				url: bkxEmailTemplates.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_email_preview_template',
					nonce: bkxEmailTemplates.nonce,
					content: content,
					subject: subject
				},
				success: function(response) {
					if (response.success) {
						$('#bkx-preview-content').html(response.data.html);
						$('#bkx-preview-modal').show();
					}
				}
			});
		},

		/**
		 * Send test email.
		 *
		 * @param {Event} e
		 */
		sendTestEmail: function(e) {
			e.preventDefault();

			const email = $('#bkx-test-email').val();
			const subject = $('#template-subject').val();

			// Get content from TinyMCE.
			let content = '';
			if (typeof tinyMCE !== 'undefined' && tinyMCE.get('template-content')) {
				content = tinyMCE.get('template-content').getContent();
			} else {
				content = $('#template-content').val();
			}

			if (!email) {
				alert('Please enter an email address.');
				return;
			}

			const $button = $(e.currentTarget);
			$button.prop('disabled', true);

			$.ajax({
				url: bkxEmailTemplates.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_email_send_test',
					nonce: bkxEmailTemplates.nonce,
					email: email,
					subject: subject,
					content: content
				},
				success: function(response) {
					if (response.success) {
						alert(bkxEmailTemplates.i18n.testEmailSent);
					} else {
						alert(response.data.message || 'Error sending test email.');
					}
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Open variable picker.
		 *
		 * @param {Event} e
		 */
		openVariablePicker: function(e) {
			e.preventDefault();
			$('#bkx-variable-search').val('');
			$('.bkx-variable-section, .bkx-pick-variable').show();
			$('#bkx-variable-modal').show();
		},

		/**
		 * Insert variable.
		 *
		 * @param {Event} e
		 */
		insertVariable: function(e) {
			e.preventDefault();
			const variable = $(e.currentTarget).data('variable');
			this.insertAtCursor(variable);
			$('#bkx-variable-modal').hide();
		},

		/**
		 * Insert variable from sidebar.
		 *
		 * @param {Event} e
		 */
		insertVariableFromSidebar: function(e) {
			e.preventDefault();
			const variable = $(e.currentTarget).data('variable');
			this.insertAtCursor(variable);
		},

		/**
		 * Insert text at cursor in TinyMCE.
		 *
		 * @param {string} text
		 */
		insertAtCursor: function(text) {
			if (typeof tinyMCE !== 'undefined' && tinyMCE.get('template-content')) {
				tinyMCE.get('template-content').execCommand('mceInsertContent', false, text);
			} else {
				const $textarea = $('#template-content');
				const cursorPos = $textarea[0].selectionStart;
				const textBefore = $textarea.val().substring(0, cursorPos);
				const textAfter = $textarea.val().substring(cursorPos);
				$textarea.val(textBefore + text + textAfter);
			}
		},

		/**
		 * Filter variables.
		 *
		 * @param {Event} e
		 */
		filterVariables: function(e) {
			const search = $(e.currentTarget).val().toLowerCase();

			$('.bkx-pick-variable').each(function() {
				const text = $(this).text().toLowerCase();
				$(this).toggle(text.indexOf(search) > -1);
			});

			// Hide empty sections.
			$('.bkx-variable-section').each(function() {
				const hasVisible = $(this).find('.bkx-pick-variable:visible').length > 0;
				$(this).toggle(hasVisible);
			});
		},

		/**
		 * Close modal.
		 *
		 * @param {Event} e
		 */
		closeModal: function(e) {
			e.preventDefault();
			$(e.currentTarget).closest('.bkx-modal').hide();
		},

		/**
		 * Toggle preview device.
		 *
		 * @param {Event} e
		 */
		togglePreviewDevice: function(e) {
			e.preventDefault();
			const $button = $(e.currentTarget);
			const device = $button.data('device');

			$('.bkx-preview-device-toggle button').removeClass('active');
			$button.addClass('active');

			$('.bkx-preview-container')
				.removeClass('desktop mobile')
				.addClass(device);
		},

		/**
		 * View log.
		 *
		 * @param {Event} e
		 */
		viewLog: function(e) {
			e.preventDefault();
			const logId = $(e.currentTarget).data('id');
			const $row = $('tr[data-id="' + logId + '"]');

			// Get log data from row.
			const html = '<div class="bkx-log-info">' +
				'<p><strong>Recipient:</strong> ' + $row.find('td:eq(2)').text() + '</p>' +
				'<p><strong>Subject:</strong> ' + $row.find('td:eq(3)').text() + '</p>' +
				'<p><strong>Status:</strong> ' + $row.find('td:eq(4)').html() + '</p>' +
				'<p><strong>Sent:</strong> ' + $row.find('td:eq(6)').text() + '</p>' +
				'</div>';

			$('#bkx-log-detail').html(html);
			$('#bkx-log-modal').show();
		}
	};

	$(document).ready(function() {
		BkxEmailTemplates.init();
	});

})(jQuery);
