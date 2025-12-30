/**
 * BKX Backup & Recovery Admin JavaScript
 *
 * @package BookingX\BackupRecovery
 */

(function($) {
	'use strict';

	var BKXBackup = {
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
			// Modal events.
			$(document).on('click', '.bkx-modal-close, #bkx-cancel-backup, #bkx-cancel-restore', this.closeModal);
			$(document).on('click', '.bkx-modal', this.closeModalOnOverlay);

			// Backup events.
			$('#bkx-create-backup').on('click', this.showBackupModal);
			$('#bkx-start-backup').on('click', this.startBackup);

			// Restore events.
			$(document).on('click', '.bkx-restore-backup', this.showRestoreModal);
			$('#bkx-restore-confirm').on('change', this.toggleRestoreButton);
			$('#bkx-confirm-restore').on('click', this.startRestore);

			// Download event.
			$(document).on('click', '.bkx-download-backup', this.downloadBackup);

			// Delete event.
			$(document).on('click', '.bkx-delete-backup', this.deleteBackup);

			// Export form.
			$('#bkx-export-form').on('submit', this.handleExport);

			// Import form.
			$('#bkx-import-form').on('submit', this.handleImport);

			// Settings form.
			$('#bkx-settings-form').on('submit', this.handleSettings);
		},

		/**
		 * Show backup modal.
		 */
		showBackupModal: function(e) {
			e.preventDefault();
			$('#bkx-backup-modal').show();
		},

		/**
		 * Show restore modal.
		 */
		showRestoreModal: function(e) {
			e.preventDefault();
			var backupId = $(this).data('id');
			$('#bkx-restore-backup-id').val(backupId);
			$('#bkx-restore-confirm').prop('checked', false);
			$('#bkx-confirm-restore').prop('disabled', true);
			$('#bkx-restore-modal').show();
		},

		/**
		 * Close modal.
		 */
		closeModal: function() {
			$('.bkx-modal').hide();
		},

		/**
		 * Close modal on overlay click.
		 */
		closeModalOnOverlay: function(e) {
			if ($(e.target).hasClass('bkx-modal')) {
				$('.bkx-modal').hide();
			}
		},

		/**
		 * Toggle restore button.
		 */
		toggleRestoreButton: function() {
			$('#bkx-confirm-restore').prop('disabled', !$(this).is(':checked'));
		},

		/**
		 * Start backup.
		 */
		startBackup: function(e) {
			e.preventDefault();

			var $btn = $(this);
			var $spinner = $btn.find('.spinner');
			var formData = $('#bkx-backup-form').serialize();

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			$.post(bkxBackupAdmin.ajaxUrl, {
				action: 'bkx_backup_create',
				nonce: bkxBackupAdmin.nonce,
				backup_type: $('#backup_type').val(),
				include: $('input[name="include[]"]:checked').map(function() {
					return $(this).val();
				}).get()
			}, function(response) {
				$spinner.removeClass('is-active');
				$btn.prop('disabled', false);

				if (response.success) {
					BKXBackup.showNotice('success', response.data.message || bkxBackupAdmin.i18n.backupSuccess);
					BKXBackup.closeModal();
					location.reload();
				} else {
					BKXBackup.showNotice('error', response.data.message || bkxBackupAdmin.i18n.backupError);
				}
			}).fail(function() {
				$spinner.removeClass('is-active');
				$btn.prop('disabled', false);
				BKXBackup.showNotice('error', bkxBackupAdmin.i18n.requestError);
			});
		},

		/**
		 * Start restore.
		 */
		startRestore: function(e) {
			e.preventDefault();

			var $btn = $(this);
			var $spinner = $btn.find('.spinner');
			var backupId = $('#bkx-restore-backup-id').val();

			$btn.prop('disabled', true);
			$spinner.addClass('is-active');

			$.post(bkxBackupAdmin.ajaxUrl, {
				action: 'bkx_backup_restore',
				nonce: bkxBackupAdmin.nonce,
				backup_id: backupId
			}, function(response) {
				$spinner.removeClass('is-active');
				$btn.prop('disabled', false);

				if (response.success) {
					BKXBackup.showNotice('success', response.data.message || bkxBackupAdmin.i18n.restoreSuccess);
					BKXBackup.closeModal();
					location.reload();
				} else {
					BKXBackup.showNotice('error', response.data.message || bkxBackupAdmin.i18n.restoreError);
				}
			}).fail(function() {
				$spinner.removeClass('is-active');
				$btn.prop('disabled', false);
				BKXBackup.showNotice('error', bkxBackupAdmin.i18n.requestError);
			});
		},

		/**
		 * Download backup.
		 */
		downloadBackup: function(e) {
			e.preventDefault();

			var backupId = $(this).data('id');

			$.post(bkxBackupAdmin.ajaxUrl, {
				action: 'bkx_backup_download',
				nonce: bkxBackupAdmin.nonce,
				backup_id: backupId
			}, function(response) {
				if (response.success && response.data.url) {
					window.location.href = response.data.url;
				} else {
					BKXBackup.showNotice('error', response.data.message || bkxBackupAdmin.i18n.downloadError);
				}
			});
		},

		/**
		 * Delete backup.
		 */
		deleteBackup: function(e) {
			e.preventDefault();

			if (!confirm(bkxBackupAdmin.i18n.confirmDelete)) {
				return;
			}

			var $btn = $(this);
			var $row = $btn.closest('tr');
			var backupId = $btn.data('id');

			$btn.prop('disabled', true);

			$.post(bkxBackupAdmin.ajaxUrl, {
				action: 'bkx_backup_delete',
				nonce: bkxBackupAdmin.nonce,
				backup_id: backupId
			}, function(response) {
				if (response.success) {
					$row.fadeOut(300, function() {
						$(this).remove();
					});
				} else {
					$btn.prop('disabled', false);
					BKXBackup.showNotice('error', response.data.message || bkxBackupAdmin.i18n.deleteError);
				}
			}).fail(function() {
				$btn.prop('disabled', false);
				BKXBackup.showNotice('error', bkxBackupAdmin.i18n.requestError);
			});
		},

		/**
		 * Handle export.
		 */
		handleExport: function(e) {
			e.preventDefault();

			var $form = $(this);
			var $btn = $('#bkx-export-btn');
			var $result = $('#bkx-export-result');

			$btn.prop('disabled', true);

			$.post(bkxBackupAdmin.ajaxUrl, {
				action: 'bkx_backup_export',
				nonce: bkxBackupAdmin.nonce,
				export_type: $('#export_type').val(),
				export_format: $('#export_format').val(),
				date_from: $('#date_from').val(),
				date_to: $('#date_to').val(),
				booking_status: $('#booking_status').val()
			}, function(response) {
				$btn.prop('disabled', false);

				if (response.success && response.data.url) {
					$('#bkx-export-download').attr('href', response.data.url);
					$result.show();
				} else {
					BKXBackup.showNotice('error', response.data.message || bkxBackupAdmin.i18n.exportError);
				}
			}).fail(function() {
				$btn.prop('disabled', false);
				BKXBackup.showNotice('error', bkxBackupAdmin.i18n.requestError);
			});
		},

		/**
		 * Handle import.
		 */
		handleImport: function(e) {
			e.preventDefault();

			var $form = $(this);
			var $btn = $('#bkx-import-btn');
			var $result = $('#bkx-import-result');
			var formData = new FormData($form[0]);

			formData.append('action', 'bkx_backup_import');
			formData.append('nonce', bkxBackupAdmin.nonce);

			$btn.prop('disabled', true);

			$.ajax({
				url: bkxBackupAdmin.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					$btn.prop('disabled', false);

					if (response.success) {
						var data = response.data;
						var html = '<div class="result-item success">' +
							bkxBackupAdmin.i18n.imported + ': ' + data.imported + '</div>';

						if (data.skipped > 0) {
							html += '<div class="result-item warning">' +
								bkxBackupAdmin.i18n.skipped + ': ' + data.skipped + '</div>';
						}

						if (data.errors && data.errors.length > 0) {
							html += '<div class="result-item error">' +
								bkxBackupAdmin.i18n.errors + ': ' + data.errors.length + '</div>';
							html += '<ul class="error-list">';
							data.errors.forEach(function(error) {
								html += '<li>' + error + '</li>';
							});
							html += '</ul>';
						}

						$result.find('.bkx-result-summary').html(html);
						$result.show();
					} else {
						BKXBackup.showNotice('error', response.data.message || bkxBackupAdmin.i18n.importError);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					BKXBackup.showNotice('error', bkxBackupAdmin.i18n.requestError);
				}
			});
		},

		/**
		 * Handle settings save.
		 */
		handleSettings: function(e) {
			e.preventDefault();

			var $form = $(this);
			var $btn = $('#bkx-save-settings');

			$btn.prop('disabled', true);

			$.post(bkxBackupAdmin.ajaxUrl, {
				action: 'bkx_backup_save_settings',
				nonce: bkxBackupAdmin.nonce,
				settings: $form.serialize()
			}, function(response) {
				$btn.prop('disabled', false);

				if (response.success) {
					BKXBackup.showNotice('success', response.data.message || bkxBackupAdmin.i18n.settingsSaved);
				} else {
					BKXBackup.showNotice('error', response.data.message || bkxBackupAdmin.i18n.settingsError);
				}
			}).fail(function() {
				$btn.prop('disabled', false);
				BKXBackup.showNotice('error', bkxBackupAdmin.i18n.requestError);
			});
		},

		/**
		 * Show admin notice.
		 */
		showNotice: function(type, message) {
			var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			$('.bkx-backup-recovery > h1').after($notice);

			// Auto dismiss after 5 seconds.
			setTimeout(function() {
				$notice.fadeOut(300, function() {
					$(this).remove();
				});
			}, 5000);

			// Initialize dismiss button.
			if (typeof wp !== 'undefined' && wp.a11y && wp.a11y.speak) {
				wp.a11y.speak(message);
			}
		}
	};

	// Initialize on document ready.
	$(document).ready(function() {
		BKXBackup.init();
	});

})(jQuery);
