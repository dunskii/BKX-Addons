/**
 * Security & Audit - Admin JavaScript
 *
 * @package BookingX\SecurityAudit
 */

/* global jQuery, bkxSecurityAdmin */
(function($) {
	'use strict';

	const BkxSecurity = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			// Modal events
			$(document).on('click', '.bkx-security-modal-close', this.closeModal);
			$(document).on('click', '.bkx-security-modal', this.closeModalOnBackground);
			$(document).on('keydown', this.handleEscape);

			// Dashboard
			$(document).on('click', '#bkx-run-scan', this.runQuickScan);
			$(document).on('click', '#bkx-export-logs', this.exportLogs);

			// Scanner
			$(document).on('click', '#bkx-run-full-scan', this.runFullScan);

			// Audit log
			$(document).on('click', '.bkx-view-details', this.viewDetails);
			$(document).on('click', '#bkx-export-audit-log', this.exportAuditLog);

			// IP actions
			$(document).on('click', '.bkx-ip-actions', this.showIPMenu);
			$(document).on('click', '.bkx-ip-whitelist', this.whitelistIP);
			$(document).on('click', '.bkx-ip-block', this.blockIP);
			$(document).on('click', '.bkx-whitelist-ip', this.whitelistIPDirect);
			$(document).on('click', '.bkx-block-ip', this.blockIPDirect);

			// Lockouts
			$(document).on('click', '.bkx-clear-lockout', this.clearLockout);

			// Security events
			$(document).on('click', '.bkx-resolve-event', this.resolveEvent);
			$(document).on('click', '.bkx-view-event-data', this.viewEventData);

			// File integrity
			$(document).on('click', '#bkx-init-baseline', this.initBaseline);
			$(document).on('click', '#bkx-check-integrity', this.checkIntegrity);
			$(document).on('click', '#bkx-reset-baseline', this.resetBaseline);
			$(document).on('click', '.bkx-accept-change', this.acceptChange);

			// Close dropdown on outside click
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.bkx-ip-actions, #bkx-ip-menu').length) {
					$('#bkx-ip-menu').hide();
				}
			});
		},

		closeModal: function(e) {
			e.preventDefault();
			$('.bkx-security-modal').hide();
		},

		closeModalOnBackground: function(e) {
			if ($(e.target).hasClass('bkx-security-modal')) {
				$('.bkx-security-modal').hide();
			}
		},

		handleEscape: function(e) {
			if (e.key === 'Escape') {
				$('.bkx-security-modal').hide();
				$('#bkx-ip-menu').hide();
			}
		},

		runQuickScan: function() {
			const $btn = $(this);
			$btn.prop('disabled', true).text(bkxSecurityAdmin.i18n.scanning);

			$.ajax({
				url: bkxSecurityAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_security_run_scan',
					nonce: bkxSecurityAdmin.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-shield-alt"></span> Run Security Scan');

					if (response.success) {
						BkxSecurity.showScanResults(response.data.results);
					} else {
						alert(response.data.message || bkxSecurityAdmin.i18n.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-shield-alt"></span> Run Security Scan');
					alert(bkxSecurityAdmin.i18n.error);
				}
			});
		},

		runFullScan: function() {
			const $btn = $(this);
			$btn.prop('disabled', true);
			$('#bkx-scan-progress').show();

			$.ajax({
				url: bkxSecurityAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_security_run_scan',
					nonce: bkxSecurityAdmin.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false);
					$('#bkx-scan-progress').hide();

					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || bkxSecurityAdmin.i18n.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					$('#bkx-scan-progress').hide();
					alert(bkxSecurityAdmin.i18n.error);
				}
			});
		},

		showScanResults: function(results) {
			let html = '<div class="bkx-scan-summary">';
			html += '<p><strong>Score:</strong> ' + results.score + '/100</p>';
			html += '<p><strong>Issues:</strong> ' + results.issues.length + '</p>';
			html += '<p><strong>Warnings:</strong> ' + results.warnings.length + '</p>';
			html += '</div>';

			if (results.issues.length > 0) {
				html += '<h3>Issues</h3><ul class="bkx-issues-list">';
				results.issues.forEach(function(issue) {
					html += '<li class="bkx-issue bkx-severity-' + issue.severity + '">';
					html += '<span class="bkx-issue-badge">' + issue.severity + '</span>';
					html += '<div class="bkx-issue-content"><strong>' + issue.title + '</strong>';
					html += '<p>' + issue.description + '</p></div></li>';
				});
				html += '</ul>';
			}

			$('#bkx-scan-results').html(html);
			$('#bkx-scan-results-modal').show();
		},

		exportLogs: function() {
			$.ajax({
				url: bkxSecurityAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_security_export_logs',
					nonce: bkxSecurityAdmin.nonce,
					format: 'csv',
					days: 30
				},
				success: function(response) {
					if (response.success) {
						window.location.href = response.data.download_url;
					} else {
						alert(response.data.message || bkxSecurityAdmin.i18n.error);
					}
				},
				error: function() {
					alert(bkxSecurityAdmin.i18n.error);
				}
			});
		},

		exportAuditLog: function() {
			BkxSecurity.exportLogs.call(this);
		},

		viewDetails: function() {
			const details = $(this).data('details');
			try {
				const parsed = typeof details === 'string' ? JSON.parse(details) : details;
				$('#bkx-details-content').text(JSON.stringify(parsed, null, 2));
			} catch (e) {
				$('#bkx-details-content').text(details);
			}
			$('#bkx-details-modal').show();
		},

		viewEventData: function() {
			const data = $(this).data('data');
			try {
				const parsed = typeof data === 'string' ? JSON.parse(data) : data;
				$('#bkx-details-content').text(JSON.stringify(parsed, null, 2));
			} catch (e) {
				$('#bkx-details-content').text(data);
			}
			$('#bkx-details-modal').show();
		},

		showIPMenu: function(e) {
			e.stopPropagation();
			const ip = $(this).data('ip');
			const offset = $(this).offset();

			$('#bkx-ip-menu')
				.data('ip', ip)
				.css({
					top: offset.top + 20,
					left: offset.left - 100
				})
				.show();
		},

		whitelistIP: function(e) {
			e.preventDefault();
			const ip = $('#bkx-ip-menu').data('ip');
			BkxSecurity.addToWhitelist(ip);
			$('#bkx-ip-menu').hide();
		},

		blockIP: function(e) {
			e.preventDefault();
			const ip = $('#bkx-ip-menu').data('ip');
			BkxSecurity.addToBlocklist(ip);
			$('#bkx-ip-menu').hide();
		},

		whitelistIPDirect: function() {
			const ip = $(this).data('ip');
			BkxSecurity.addToWhitelist(ip);
		},

		blockIPDirect: function() {
			const ip = $(this).data('ip');
			BkxSecurity.addToBlocklist(ip);
		},

		addToWhitelist: function(ip) {
			if (!confirm(bkxSecurityAdmin.i18n.confirm)) {
				return;
			}

			$.ajax({
				url: bkxSecurityAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_security_whitelist_ip',
					nonce: bkxSecurityAdmin.nonce,
					ip: ip
				},
				success: function(response) {
					if (response.success) {
						alert(bkxSecurityAdmin.i18n.success);
						location.reload();
					} else {
						alert(response.data.message || bkxSecurityAdmin.i18n.error);
					}
				},
				error: function() {
					alert(bkxSecurityAdmin.i18n.error);
				}
			});
		},

		addToBlocklist: function(ip) {
			if (!confirm(bkxSecurityAdmin.i18n.confirm)) {
				return;
			}

			$.ajax({
				url: bkxSecurityAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_security_block_ip',
					nonce: bkxSecurityAdmin.nonce,
					ip: ip
				},
				success: function(response) {
					if (response.success) {
						alert(bkxSecurityAdmin.i18n.success);
						location.reload();
					} else {
						alert(response.data.message || bkxSecurityAdmin.i18n.error);
					}
				},
				error: function() {
					alert(bkxSecurityAdmin.i18n.error);
				}
			});
		},

		clearLockout: function() {
			const ip = $(this).data('ip');
			if (!confirm(bkxSecurityAdmin.i18n.confirmClear)) {
				return;
			}

			const $btn = $(this);
			$btn.prop('disabled', true);

			$.ajax({
				url: bkxSecurityAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_security_clear_lockout',
					nonce: bkxSecurityAdmin.nonce,
					ip: ip
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						$btn.prop('disabled', false);
						alert(response.data.message || bkxSecurityAdmin.i18n.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					alert(bkxSecurityAdmin.i18n.error);
				}
			});
		},

		resolveEvent: function() {
			const eventId = $(this).data('id');
			if (!confirm(bkxSecurityAdmin.i18n.confirmResolve)) {
				return;
			}

			const $btn = $(this);
			$btn.prop('disabled', true);

			$.ajax({
				url: bkxSecurityAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_security_resolve_event',
					nonce: bkxSecurityAdmin.nonce,
					event_id: eventId
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						$btn.prop('disabled', false);
						alert(response.data.message || bkxSecurityAdmin.i18n.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					alert(bkxSecurityAdmin.i18n.error);
				}
			});
		},

		initBaseline: function() {
			const $btn = $(this);
			$btn.prop('disabled', true).text('Initializing...');

			$.ajax({
				url: bkxSecurityAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_security_init_baseline',
					nonce: bkxSecurityAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						$btn.prop('disabled', false).text('Initialize Baseline');
						alert(response.data.message || bkxSecurityAdmin.i18n.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false).text('Initialize Baseline');
					alert(bkxSecurityAdmin.i18n.error);
				}
			});
		},

		checkIntegrity: function() {
			const $btn = $(this);
			$btn.prop('disabled', true).text('Checking...');

			$.ajax({
				url: bkxSecurityAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_security_check_integrity',
					nonce: bkxSecurityAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						$btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Check Files');
						alert(response.data.message || bkxSecurityAdmin.i18n.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Check Files');
					alert(bkxSecurityAdmin.i18n.error);
				}
			});
		},

		resetBaseline: function() {
			if (!confirm('This will reset the file baseline. Continue?')) {
				return;
			}
			BkxSecurity.initBaseline.call(this);
		},

		acceptChange: function() {
			const path = $(this).data('path');
			const $btn = $(this);
			$btn.prop('disabled', true);

			$.ajax({
				url: bkxSecurityAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_security_accept_change',
					nonce: bkxSecurityAdmin.nonce,
					path: path
				},
				success: function(response) {
					if (response.success) {
						$btn.closest('tr').fadeOut();
					} else {
						$btn.prop('disabled', false);
						alert(response.data.message || bkxSecurityAdmin.i18n.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					alert(bkxSecurityAdmin.i18n.error);
				}
			});
		}
	};

	$(document).ready(function() {
		BkxSecurity.init();
	});

})(jQuery);
