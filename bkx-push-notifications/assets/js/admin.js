/**
 * Push Notifications Admin JavaScript.
 *
 * @package BookingX\PushNotifications
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BkxPushAdmin = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			// Settings form.
			$(document).on('submit', '#bkx-push-settings-form', this.saveSettings.bind(this));

			// Send test.
			$(document).on('click', '#bkx-send-test', this.sendTest.bind(this));

			// Template modal.
			$(document).on('click', '#bkx-add-template', this.openAddTemplate.bind(this));
			$(document).on('click', '.bkx-edit-template', this.openEditTemplate.bind(this));
			$(document).on('click', '.bkx-delete-template', this.deleteTemplate.bind(this));
			$(document).on('submit', '#bkx-template-form', this.saveTemplate.bind(this));

			// Modal close.
			$(document).on('click', '.bkx-modal-close', this.closeModal.bind(this));
			$(document).on('click', '.bkx-modal', function(e) {
				if (e.target === this) {
					$(this).hide();
				}
			});
		},

		/**
		 * Save settings.
		 *
		 * @param {Event} e
		 */
		saveSettings: function(e) {
			e.preventDefault();
			const $form = $(e.currentTarget);
			const $button = $form.find('button[type="submit"]');

			$button.prop('disabled', true);

			$.ajax({
				url: bkxPush.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_push_save_settings',
					nonce: bkxPush.nonce,
					enabled: $form.find('[name="enabled"]').is(':checked') ? 1 : 0,
					prompt_delay: $form.find('[name="prompt_delay"]').val(),
					prompt_message: $form.find('[name="prompt_message"]').val(),
					icon: $form.find('[name="icon"]').val(),
					badge: $form.find('[name="badge"]').val(),
					reminder_hours: $form.find('[name="reminder_hours"]').val()
				},
				success: function(response) {
					if (response.success) {
						alert(bkxPush.i18n.saved);
					} else {
						alert(response.data.message || 'Error saving settings.');
					}
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Send test notification.
		 *
		 * @param {Event} e
		 */
		sendTest: function(e) {
			e.preventDefault();
			const $button = $(e.currentTarget);

			const title = $('#test-title').val();
			const body = $('#test-body').val();

			$button.prop('disabled', true);

			$.ajax({
				url: bkxPush.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_push_send_test',
					nonce: bkxPush.nonce,
					title: title,
					body: body
				},
				success: function(response) {
					if (response.success) {
						alert(bkxPush.i18n.testSent);
					} else {
						alert(response.data.message || 'Error sending test.');
					}
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Open add template modal.
		 *
		 * @param {Event} e
		 */
		openAddTemplate: function(e) {
			e.preventDefault();

			$('#bkx-template-modal-title').text('Add Template');
			$('#bkx-template-form')[0].reset();
			$('#template-id').val('');
			$('#bkx-template-modal').show();
		},

		/**
		 * Open edit template modal.
		 *
		 * @param {Event} e
		 */
		openEditTemplate: function(e) {
			e.preventDefault();
			const $btn = $(e.currentTarget);

			$('#bkx-template-modal-title').text('Edit Template');
			$('#template-id').val($btn.data('id'));
			$('#template-name').val($btn.data('name'));
			$('#template-slug').val($btn.data('slug'));
			$('#template-trigger').val($btn.data('trigger'));
			$('#template-title').val($btn.data('title'));
			$('#template-body').val($btn.data('body'));
			$('#template-icon').val($btn.data('icon'));
			$('#template-url').val($btn.data('url'));
			$('#template-audience').val($btn.data('audience'));
			$('#template-status').val($btn.data('status'));

			$('#bkx-template-modal').show();
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

			$button.prop('disabled', true);

			$.ajax({
				url: bkxPush.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_push_save_template',
					nonce: bkxPush.nonce,
					template_id: $('#template-id').val(),
					name: $('#template-name').val(),
					slug: $('#template-slug').val(),
					trigger_event: $('#template-trigger').val(),
					title: $('#template-title').val(),
					body: $('#template-body').val(),
					icon: $('#template-icon').val(),
					url: $('#template-url').val(),
					target_audience: $('#template-audience').val(),
					status: $('#template-status').val()
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Error saving template.');
						$button.prop('disabled', false);
					}
				},
				error: function() {
					alert('Error saving template.');
					$button.prop('disabled', false);
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

			if (!confirm(bkxPush.i18n.confirmDelete)) {
				return;
			}

			const templateId = $(e.currentTarget).data('id');

			$.ajax({
				url: bkxPush.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_push_delete_template',
					nonce: bkxPush.nonce,
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
		 * Close modal.
		 *
		 * @param {Event} e
		 */
		closeModal: function(e) {
			e.preventDefault();
			$(e.currentTarget).closest('.bkx-modal').hide();
		}
	};

	$(document).ready(function() {
		BkxPushAdmin.init();
	});

})(jQuery);
