/**
 * Xero Admin JavaScript.
 *
 * @package BookingX\Xero
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKX_Xero = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.loadSyncStatus();
			this.loadLogs();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			$('#bkx-xero-connect').on('click', this.handleConnect);
			$('#bkx-xero-disconnect').on('click', this.handleDisconnect);
			$('#bkx-xero-sync-contacts').on('click', () => this.handleSync('contacts'));
			$('#bkx-xero-sync-items').on('click', () => this.handleSync('items'));
			$('#bkx-xero-sync-invoices').on('click', () => this.handleSync('invoices'));
			$('#bkx-xero-sync-all').on('click', () => this.handleSync('all'));
		},

		/**
		 * Handle connect button click.
		 */
		handleConnect: function() {
			const $btn = $(this);
			$btn.prop('disabled', true).text(bkxXero.i18n.connecting);

			$.ajax({
				url: bkxXero.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_xero_connect',
					nonce: bkxXero.nonce
				},
				success: function(response) {
					if (response.success && response.data.auth_url) {
						// Open Xero authorization in popup.
						const popup = window.open(
							response.data.auth_url,
							'xero_auth',
							'width=600,height=700,scrollbars=yes'
						);

						// Check for popup close.
						const checkClosed = setInterval(function() {
							if (popup.closed) {
								clearInterval(checkClosed);
								location.reload();
							}
						}, 500);
					} else {
						alert(response.data?.message || bkxXero.i18n.error);
						$btn.prop('disabled', false).text('Connect to Xero');
					}
				},
				error: function() {
					alert(bkxXero.i18n.error);
					$btn.prop('disabled', false).text('Connect to Xero');
				}
			});
		},

		/**
		 * Handle disconnect button click.
		 */
		handleDisconnect: function() {
			if (!confirm('Are you sure you want to disconnect from Xero?')) {
				return;
			}

			const $btn = $(this);
			$btn.prop('disabled', true);

			$.ajax({
				url: bkxXero.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_xero_disconnect',
					nonce: bkxXero.nonce
				},
				success: function(response) {
					if (response.success) {
						alert(bkxXero.i18n.disconnected);
						location.reload();
					} else {
						alert(response.data?.message || bkxXero.i18n.error);
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					alert(bkxXero.i18n.error);
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Handle sync button click.
		 *
		 * @param {string} syncType Type of sync.
		 */
		handleSync: function(syncType) {
			if (syncType === 'all' && !confirm(bkxXero.i18n.confirmSync)) {
				return;
			}

			const $btn = $('#bkx-xero-sync-' + syncType);
			const $progress = $('#bkx-xero-sync-progress');
			const $progressFill = $progress.find('.bkx-xero-progress-fill');
			const $progressMsg = $progress.find('.bkx-xero-progress-message');

			// Disable all sync buttons.
			$('.bkx-xero-button-group .button').prop('disabled', true);
			$btn.addClass('bkx-xero-syncing');

			// Show progress.
			$progress.show();
			$progressFill.css('width', '10%');
			$progressMsg.text(bkxXero.i18n.syncing);

			$.ajax({
				url: bkxXero.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_xero_manual_sync',
					nonce: bkxXero.nonce,
					sync_type: syncType
				},
				success: function(response) {
					$progressFill.css('width', '100%');

					if (response.success) {
						let message = bkxXero.i18n.success + ' ';
						const data = response.data;

						if (data.contacts) {
							message += 'Contacts: ' + data.contacts.synced + ' synced. ';
						}
						if (data.items) {
							message += 'Items: ' + data.items.synced + ' synced. ';
						}
						if (data.invoices) {
							message += 'Invoices: ' + data.invoices.synced + ' synced. ';
						}

						$progressMsg.text(message);

						// Refresh stats.
						BKX_Xero.loadSyncStatus();
						BKX_Xero.loadLogs();
					} else {
						$progressMsg.text(response.data?.message || bkxXero.i18n.error);
					}
				},
				error: function() {
					$progressFill.css('width', '0');
					$progressMsg.text(bkxXero.i18n.error);
				},
				complete: function() {
					$('.bkx-xero-button-group .button').prop('disabled', false);
					$btn.removeClass('bkx-xero-syncing');

					// Hide progress after delay.
					setTimeout(function() {
						$progress.slideUp();
					}, 5000);
				}
			});
		},

		/**
		 * Load sync status.
		 */
		loadSyncStatus: function() {
			const $stats = $('#bkx-xero-sync-stats');

			if (!$stats.length) {
				return;
			}

			$.ajax({
				url: bkxXero.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_xero_get_sync_status',
					nonce: bkxXero.nonce
				},
				success: function(response) {
					if (response.success && response.data.stats) {
						const stats = response.data.stats;
						$('#stat-contacts').text(stats.contacts_synced || 0);
						$('#stat-invoices').text(stats.invoices_synced || 0);
						$('#stat-payments').text(stats.payments_synced || 0);
						$('#stat-pending').text(stats.pending_syncs || 0);
						$('#stat-failed').text(stats.failed_syncs || 0);
					}
				}
			});
		},

		/**
		 * Load sync logs.
		 */
		loadLogs: function() {
			const $tbody = $('#bkx-xero-logs-body');

			if (!$tbody.length) {
				return;
			}

			$.ajax({
				url: bkxXero.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_xero_get_sync_status',
					nonce: bkxXero.nonce
				},
				success: function(response) {
					if (response.success && response.data.logs) {
						const logs = response.data.logs;

						if (logs.length === 0) {
							$tbody.html('<tr><td colspan="6">No sync logs found.</td></tr>');
							return;
						}

						let html = '';
						logs.forEach(function(log) {
							const statusClass = 'bkx-xero-log-status-' + log.sync_status;
							html += '<tr>';
							html += '<td>' + BKX_Xero.formatDate(log.created_at) + '</td>';
							html += '<td>' + BKX_Xero.escapeHtml(log.entity_type) + '</td>';
							html += '<td>' + (log.entity_id || '-') + '</td>';
							html += '<td>' + (log.xero_id || '-') + '</td>';
							html += '<td><span class="bkx-xero-log-status ' + statusClass + '">' + BKX_Xero.escapeHtml(log.sync_status) + '</span></td>';
							html += '<td>' + BKX_Xero.escapeHtml(log.error_message || '-') + '</td>';
							html += '</tr>';
						});

						$tbody.html(html);
					}
				},
				error: function() {
					$tbody.html('<tr><td colspan="6">Error loading logs.</td></tr>');
				}
			});
		},

		/**
		 * Format date.
		 *
		 * @param {string} dateStr Date string.
		 * @return {string} Formatted date.
		 */
		formatDate: function(dateStr) {
			if (!dateStr) return '-';
			const date = new Date(dateStr);
			return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
		},

		/**
		 * Escape HTML.
		 *
		 * @param {string} str String to escape.
		 * @return {string} Escaped string.
		 */
		escapeHtml: function(str) {
			if (!str) return '';
			const div = document.createElement('div');
			div.appendChild(document.createTextNode(str));
			return div.innerHTML;
		}
	};

	// Initialize on document ready.
	$(document).ready(function() {
		BKX_Xero.init();
	});

})(jQuery);
