/**
 * BKX to BKX Integration - Admin JavaScript
 *
 * @package BookingX\BkxIntegration
 */

/* global jQuery, bkxBkxAdmin */
(function($) {
	'use strict';

	/**
	 * BKX Integration Admin Module
	 */
	const BkxIntegration = {
		/**
		 * Initialize the module
		 */
		init: function() {
			this.bindEvents();
			this.initClipboard();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			// Modal events
			$(document).on('click', '#bkx-bkx-add-site', this.openAddSiteModal.bind(this));
			$(document).on('click', '.bkx-bkx-edit-site', this.openEditSiteModal.bind(this));
			$(document).on('click', '.bkx-bkx-modal-close, .bkx-bkx-modal-cancel', this.closeModal.bind(this));
			$(document).on('click', '.bkx-bkx-modal', this.closeModalOnBackground.bind(this));

			// Site form
			$(document).on('submit', '#bkx-bkx-site-form', this.saveSite.bind(this));
			$(document).on('click', '#bkx-bkx-test-connection', this.testConnection.bind(this));

			// Site actions
			$(document).on('click', '.bkx-bkx-delete-site', this.deleteSite.bind(this));
			$(document).on('click', '.bkx-bkx-sync-now', this.syncNow.bind(this));
			$(document).on('click', '.bkx-bkx-test-site', this.testSite.bind(this));

			// API settings
			$(document).on('click', '.bkx-bkx-toggle-secret', this.toggleSecret.bind(this));
			$(document).on('click', '#bkx-bkx-regenerate-keys', this.regenerateKeys.bind(this));

			// Conflicts
			$(document).on('click', '.bkx-bkx-resolve', this.resolveConflict.bind(this));
			$(document).on('click', '.bkx-bkx-view-data', this.viewData.bind(this));

			// Logs
			$(document).on('click', '#bkx-bkx-clear-logs', this.clearLogs.bind(this));

			// Keyboard events
			$(document).on('keydown', this.handleKeydown.bind(this));
		},

		/**
		 * Initialize clipboard functionality
		 */
		initClipboard: function() {
			$(document).on('click', '.bkx-bkx-copy', function(e) {
				e.preventDefault();
				const text = $(this).data('copy');
				BkxIntegration.copyToClipboard(text, $(this));
			});
		},

		/**
		 * Copy text to clipboard
		 */
		copyToClipboard: function(text, $button) {
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() {
					BkxIntegration.showCopyFeedback($button);
				});
			} else {
				// Fallback for older browsers
				const $temp = $('<textarea>');
				$('body').append($temp);
				$temp.val(text).select();
				document.execCommand('copy');
				$temp.remove();
				BkxIntegration.showCopyFeedback($button);
			}
		},

		/**
		 * Show copy feedback
		 */
		showCopyFeedback: function($button) {
			const originalText = $button.text();
			$button.text(bkxBkxAdmin.i18n.copied || 'Copied!');
			setTimeout(function() {
				$button.text(originalText);
			}, 2000);
		},

		/**
		 * Open add site modal
		 */
		openAddSiteModal: function(e) {
			e.preventDefault();
			this.resetSiteForm();
			$('#bkx-bkx-modal-title').text(bkxBkxAdmin.i18n.addSite || 'Add Remote Site');
			$('#bkx-bkx-site-modal').show();
		},

		/**
		 * Open edit site modal
		 */
		openEditSiteModal: function(e) {
			e.preventDefault();
			const siteId = $(e.currentTarget).data('id');

			this.resetSiteForm();
			$('#bkx-bkx-modal-title').text(bkxBkxAdmin.i18n.editSite || 'Edit Remote Site');
			$('#bkx-bkx-site-form').addClass('bkx-bkx-loading');

			$.ajax({
				url: bkxBkxAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_bkx_get_site',
					site_id: siteId,
					nonce: bkxBkxAdmin.nonce
				},
				success: function(response) {
					$('#bkx-bkx-site-form').removeClass('bkx-bkx-loading');
					if (response.success) {
						BkxIntegration.populateSiteForm(response.data);
						$('#bkx-bkx-site-modal').show();
					} else {
						alert(response.data.message || 'Failed to load site data.');
					}
				},
				error: function() {
					$('#bkx-bkx-site-form').removeClass('bkx-bkx-loading');
					alert('Failed to load site data.');
				}
			});
		},

		/**
		 * Reset site form
		 */
		resetSiteForm: function() {
			const $form = $('#bkx-bkx-site-form');
			$form[0].reset();
			$('#bkx-bkx-site-id').val(0);
			$('input[name="sync_bookings"]').prop('checked', true);
			$('input[name="sync_availability"]').prop('checked', true);
			$('input[name="sync_customers"]').prop('checked', false);
			$('#bkx-bkx-direction').val('both');
			$('#bkx-bkx-status').val('active');
		},

		/**
		 * Populate site form with data
		 */
		populateSiteForm: function(site) {
			$('#bkx-bkx-site-id').val(site.id);
			$('#bkx-bkx-name').val(site.name);
			$('#bkx-bkx-url').val(site.url);
			$('#bkx-bkx-api-key').val(site.api_key);
			$('#bkx-bkx-api-secret').val(''); // Don't show existing secret
			$('#bkx-bkx-direction').val(site.direction);
			$('#bkx-bkx-status').val(site.status);
			$('input[name="sync_bookings"]').prop('checked', site.sync_bookings);
			$('input[name="sync_availability"]').prop('checked', site.sync_availability);
			$('input[name="sync_customers"]').prop('checked', site.sync_customers);
		},

		/**
		 * Close modal
		 */
		closeModal: function(e) {
			e.preventDefault();
			$('.bkx-bkx-modal').hide();
		},

		/**
		 * Close modal on background click
		 */
		closeModalOnBackground: function(e) {
			if ($(e.target).hasClass('bkx-bkx-modal')) {
				$('.bkx-bkx-modal').hide();
			}
		},

		/**
		 * Handle keyboard events
		 */
		handleKeydown: function(e) {
			if (e.key === 'Escape') {
				$('.bkx-bkx-modal').hide();
			}
		},

		/**
		 * Save site
		 */
		saveSite: function(e) {
			e.preventDefault();

			const $form = $(e.currentTarget);
			const $submit = $form.find('button[type="submit"]');

			$submit.prop('disabled', true).addClass('updating-message');

			const data = {
				action: 'bkx_bkx_save_site',
				nonce: bkxBkxAdmin.nonce,
				site_id: $('#bkx-bkx-site-id').val(),
				name: $('#bkx-bkx-name').val(),
				url: $('#bkx-bkx-url').val(),
				api_key: $('#bkx-bkx-api-key').val(),
				api_secret: $('#bkx-bkx-api-secret').val(),
				direction: $('#bkx-bkx-direction').val(),
				status: $('#bkx-bkx-status').val(),
				sync_bookings: $('input[name="sync_bookings"]').is(':checked') ? 1 : 0,
				sync_availability: $('input[name="sync_availability"]').is(':checked') ? 1 : 0,
				sync_customers: $('input[name="sync_customers"]').is(':checked') ? 1 : 0
			};

			$.ajax({
				url: bkxBkxAdmin.ajaxUrl,
				type: 'POST',
				data: data,
				success: function(response) {
					$submit.prop('disabled', false).removeClass('updating-message');
					if (response.success) {
						$('#bkx-bkx-site-modal').hide();
						location.reload();
					} else {
						alert(response.data.message || 'Failed to save site.');
					}
				},
				error: function() {
					$submit.prop('disabled', false).removeClass('updating-message');
					alert('Failed to save site.');
				}
			});
		},

		/**
		 * Test connection from modal
		 */
		testConnection: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const url = $('#bkx-bkx-url').val();
			const apiKey = $('#bkx-bkx-api-key').val();
			const apiSecret = $('#bkx-bkx-api-secret').val();

			if (!url || !apiKey || !apiSecret) {
				alert(bkxBkxAdmin.i18n.fillRequired || 'Please fill in URL, API Key, and API Secret.');
				return;
			}

			$button.prop('disabled', true).addClass('updating-message');

			$.ajax({
				url: bkxBkxAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_bkx_test_connection',
					nonce: bkxBkxAdmin.nonce,
					url: url,
					api_key: apiKey,
					api_secret: apiSecret
				},
				success: function(response) {
					$button.prop('disabled', false).removeClass('updating-message');
					if (response.success) {
						alert(bkxBkxAdmin.i18n.connectionSuccess || 'Connection successful! Site: ' + response.data.site_name);
					} else {
						alert(response.data.message || 'Connection failed.');
					}
				},
				error: function() {
					$button.prop('disabled', false).removeClass('updating-message');
					alert('Connection test failed.');
				}
			});
		},

		/**
		 * Delete site
		 */
		deleteSite: function(e) {
			e.preventDefault();

			if (!confirm(bkxBkxAdmin.i18n.confirmDelete || 'Are you sure you want to delete this site? This action cannot be undone.')) {
				return;
			}

			const siteId = $(e.currentTarget).data('id');
			const $row = $(e.currentTarget).closest('tr');

			$row.addClass('bkx-bkx-loading');

			$.ajax({
				url: bkxBkxAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_bkx_delete_site',
					nonce: bkxBkxAdmin.nonce,
					site_id: siteId
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(300, function() {
							$(this).remove();
							if ($('.bkx-bkx-sites tbody tr').length === 0) {
								location.reload();
							}
						});
					} else {
						$row.removeClass('bkx-bkx-loading');
						alert(response.data.message || 'Failed to delete site.');
					}
				},
				error: function() {
					$row.removeClass('bkx-bkx-loading');
					alert('Failed to delete site.');
				}
			});
		},

		/**
		 * Sync now
		 */
		syncNow: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const siteId = $button.data('id');

			$button.prop('disabled', true);
			$button.after('<span class="bkx-bkx-spinner"></span>');

			$.ajax({
				url: bkxBkxAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_bkx_sync_now',
					nonce: bkxBkxAdmin.nonce,
					site_id: siteId
				},
				success: function(response) {
					$button.prop('disabled', false);
					$button.next('.bkx-bkx-spinner').remove();

					if (response.success) {
						alert(bkxBkxAdmin.i18n.syncComplete || 'Sync completed successfully. ' + response.data.message);
						location.reload();
					} else {
						alert(response.data.message || 'Sync failed.');
					}
				},
				error: function() {
					$button.prop('disabled', false);
					$button.next('.bkx-bkx-spinner').remove();
					alert('Sync failed.');
				}
			});
		},

		/**
		 * Test site connection
		 */
		testSite: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const siteId = $button.data('id');

			$button.prop('disabled', true);
			$button.after('<span class="bkx-bkx-spinner"></span>');

			$.ajax({
				url: bkxBkxAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_bkx_test_site',
					nonce: bkxBkxAdmin.nonce,
					site_id: siteId
				},
				success: function(response) {
					$button.prop('disabled', false);
					$button.next('.bkx-bkx-spinner').remove();

					if (response.success) {
						alert(bkxBkxAdmin.i18n.testSuccess || 'Connection test successful!');
					} else {
						alert(response.data.message || 'Connection test failed.');
					}
				},
				error: function() {
					$button.prop('disabled', false);
					$button.next('.bkx-bkx-spinner').remove();
					alert('Connection test failed.');
				}
			});
		},

		/**
		 * Toggle secret visibility
		 */
		toggleSecret: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const $code = $button.siblings('.bkx-bkx-secret');
			const $hidden = $code.find('.secret-hidden');
			const $visible = $code.find('.secret-visible');

			if ($hidden.is(':visible')) {
				$hidden.hide();
				$visible.show();
				$button.text(bkxBkxAdmin.i18n.hide || 'Hide');
			} else {
				$hidden.show();
				$visible.hide();
				$button.text(bkxBkxAdmin.i18n.show || 'Show');
			}
		},

		/**
		 * Regenerate API keys
		 */
		regenerateKeys: function(e) {
			e.preventDefault();

			if (!confirm(bkxBkxAdmin.i18n.confirmRegenerate || 'Are you sure you want to regenerate API keys? All existing connections will be invalidated.')) {
				return;
			}

			const $button = $(e.currentTarget);
			$button.prop('disabled', true).addClass('updating-message');

			$.ajax({
				url: bkxBkxAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_bkx_regenerate_keys',
					nonce: bkxBkxAdmin.nonce
				},
				success: function(response) {
					$button.prop('disabled', false).removeClass('updating-message');
					if (response.success) {
						alert(bkxBkxAdmin.i18n.keysRegenerated || 'API keys regenerated successfully.');
						location.reload();
					} else {
						alert(response.data.message || 'Failed to regenerate keys.');
					}
				},
				error: function() {
					$button.prop('disabled', false).removeClass('updating-message');
					alert('Failed to regenerate keys.');
				}
			});
		},

		/**
		 * Resolve conflict
		 */
		resolveConflict: function(e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const conflictId = $button.data('id');
			const resolution = $button.data('resolution');
			const $row = $button.closest('tr');

			$row.addClass('bkx-bkx-loading');

			$.ajax({
				url: bkxBkxAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_bkx_resolve_conflict',
					nonce: bkxBkxAdmin.nonce,
					conflict_id: conflictId,
					resolution: resolution
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(300, function() {
							$(this).remove();
							if ($('.bkx-bkx-conflicts tbody tr').length === 0) {
								location.reload();
							}
						});
					} else {
						$row.removeClass('bkx-bkx-loading');
						alert(response.data.message || 'Failed to resolve conflict.');
					}
				},
				error: function() {
					$row.removeClass('bkx-bkx-loading');
					alert('Failed to resolve conflict.');
				}
			});
		},

		/**
		 * View data in modal
		 */
		viewData: function(e) {
			e.preventDefault();

			const data = $(e.currentTarget).data('data');
			let formatted;

			try {
				if (typeof data === 'string') {
					formatted = JSON.stringify(JSON.parse(data), null, 2);
				} else {
					formatted = JSON.stringify(data, null, 2);
				}
			} catch (err) {
				formatted = data;
			}

			$('#bkx-bkx-data-content').text(formatted);
			$('#bkx-bkx-data-modal').show();
		},

		/**
		 * Clear logs
		 */
		clearLogs: function(e) {
			e.preventDefault();

			if (!confirm(bkxBkxAdmin.i18n.confirmClearLogs || 'Are you sure you want to clear all sync logs? This action cannot be undone.')) {
				return;
			}

			const $button = $(e.currentTarget);
			$button.prop('disabled', true).addClass('updating-message');

			$.ajax({
				url: bkxBkxAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_bkx_clear_logs',
					nonce: bkxBkxAdmin.nonce
				},
				success: function(response) {
					$button.prop('disabled', false).removeClass('updating-message');
					if (response.success) {
						alert(bkxBkxAdmin.i18n.logsCleared || 'Logs cleared successfully.');
						location.reload();
					} else {
						alert(response.data.message || 'Failed to clear logs.');
					}
				},
				error: function() {
					$button.prop('disabled', false).removeClass('updating-message');
					alert('Failed to clear logs.');
				}
			});
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		BkxIntegration.init();
	});

})(jQuery);
