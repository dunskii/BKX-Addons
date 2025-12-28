/**
 * QuickBooks Admin JavaScript.
 *
 * @package BookingX\QuickBooks
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKX_QB = {
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
			$('#bkx-qb-connect').on('click', this.handleConnect);
			$('#bkx-qb-disconnect').on('click', this.handleDisconnect);
			$('#bkx-qb-sync-customers').on('click', () => this.handleSync('customers'));
			$('#bkx-qb-sync-products').on('click', () => this.handleSync('products'));
			$('#bkx-qb-sync-invoices').on('click', () => this.handleSync('invoices'));
			$('#bkx-qb-sync-all').on('click', () => this.handleSync('all'));
		},

		/**
		 * Handle connect button click.
		 */
		handleConnect: function() {
			const $btn = $(this);
			$btn.prop('disabled', true).text(bkxQB.i18n.connecting);

			$.ajax({
				url: bkxQB.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_qb_connect',
					nonce: bkxQB.nonce
				},
				success: function(response) {
					if (response.success && response.data.auth_url) {
						// Open QuickBooks authorization in popup.
						const popup = window.open(
							response.data.auth_url,
							'quickbooks_auth',
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
						alert(response.data?.message || bkxQB.i18n.error);
						$btn.prop('disabled', false).text('Connect to QuickBooks');
					}
				},
				error: function() {
					alert(bkxQB.i18n.error);
					$btn.prop('disabled', false).text('Connect to QuickBooks');
				}
			});
		},

		/**
		 * Handle disconnect button click.
		 */
		handleDisconnect: function() {
			if (!confirm('Are you sure you want to disconnect from QuickBooks?')) {
				return;
			}

			const $btn = $(this);
			$btn.prop('disabled', true);

			$.ajax({
				url: bkxQB.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_qb_disconnect',
					nonce: bkxQB.nonce
				},
				success: function(response) {
					if (response.success) {
						alert(bkxQB.i18n.disconnected);
						location.reload();
					} else {
						alert(response.data?.message || bkxQB.i18n.error);
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					alert(bkxQB.i18n.error);
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
			if (syncType === 'all' && !confirm(bkxQB.i18n.confirmSync)) {
				return;
			}

			const $btn = $('#bkx-qb-sync-' + syncType);
			const $progress = $('#bkx-qb-sync-progress');
			const $progressFill = $progress.find('.bkx-qb-progress-fill');
			const $progressMsg = $progress.find('.bkx-qb-progress-message');

			// Disable all sync buttons.
			$('.bkx-qb-button-group .button').prop('disabled', true);
			$btn.addClass('bkx-qb-syncing');

			// Show progress.
			$progress.show();
			$progressFill.css('width', '10%');
			$progressMsg.text(bkxQB.i18n.syncing);

			$.ajax({
				url: bkxQB.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_qb_manual_sync',
					nonce: bkxQB.nonce,
					sync_type: syncType
				},
				success: function(response) {
					$progressFill.css('width', '100%');

					if (response.success) {
						let message = bkxQB.i18n.success + ' ';
						const data = response.data;

						if (data.customers) {
							message += 'Customers: ' + data.customers.synced + ' synced. ';
						}
						if (data.products) {
							message += 'Services: ' + data.products.synced + ' synced. ';
						}
						if (data.invoices) {
							message += 'Invoices: ' + data.invoices.synced + ' synced. ';
						}

						$progressMsg.text(message);

						// Refresh stats.
						BKX_QB.loadSyncStatus();
						BKX_QB.loadLogs();
					} else {
						$progressMsg.text(response.data?.message || bkxQB.i18n.error);
					}
				},
				error: function() {
					$progressFill.css('width', '0');
					$progressMsg.text(bkxQB.i18n.error);
				},
				complete: function() {
					$('.bkx-qb-button-group .button').prop('disabled', false);
					$btn.removeClass('bkx-qb-syncing');

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
			const $stats = $('#bkx-qb-sync-stats');

			if (!$stats.length) {
				return;
			}

			$.ajax({
				url: bkxQB.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_qb_get_sync_status',
					nonce: bkxQB.nonce
				},
				success: function(response) {
					if (response.success && response.data.stats) {
						const stats = response.data.stats;
						$('#stat-customers').text(stats.customers_synced || 0);
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
			const $tbody = $('#bkx-qb-logs-body');

			if (!$tbody.length) {
				return;
			}

			$.ajax({
				url: bkxQB.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_qb_get_sync_status',
					nonce: bkxQB.nonce
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
							const statusClass = 'bkx-qb-log-status-' + log.sync_status;
							html += '<tr>';
							html += '<td>' + BKX_QB.formatDate(log.created_at) + '</td>';
							html += '<td>' + BKX_QB.escapeHtml(log.entity_type) + '</td>';
							html += '<td>' + (log.entity_id || '-') + '</td>';
							html += '<td>' + (log.qb_id || '-') + '</td>';
							html += '<td><span class="bkx-qb-log-status ' + statusClass + '">' + BKX_QB.escapeHtml(log.sync_status) + '</span></td>';
							html += '<td>' + BKX_QB.escapeHtml(log.error_message || '-') + '</td>';
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
		BKX_QB.init();
	});

})(jQuery);
