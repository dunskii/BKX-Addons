/**
 * BKX PCI Compliance Admin JavaScript
 *
 * @package BookingX\PCICompliance
 */

(function($) {
	'use strict';

	var BKXPCI = {
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
			$(document).on('click', '.bkx-modal-close, .bkx-modal-cancel', this.closeModal);
			$(document).on('click', '.bkx-modal', this.closeModalOnOverlay);

			// Scan events.
			$(document).on('click', '.bkx-run-scan', this.runScan);
			$(document).on('click', '#bkx-scan-vuln', this.runVulnScan);

			// Vulnerability events.
			$(document).on('click', '.bkx-resolve-vuln', this.showResolveModal);
			$(document).on('click', '#bkx-submit-resolve', this.submitResolve);
			$(document).on('click', '.bkx-view-vuln', this.showVulnDetails);

			// Audit log events.
			$(document).on('click', '.bkx-show-details', this.showLogDetails);
			$(document).on('click', '#bkx-export-audit-csv', this.exportAuditLog);

			// Report events.
			$(document).on('click', '.bkx-generate-report', this.generateReport);

			// Settings form.
			$('#bkx-pci-settings-form').on('submit', this.saveSettings);
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
		 * Run compliance scan.
		 */
		runScan: function(e) {
			e.preventDefault();

			var $btn = $(this);
			var scanType = $btn.data('type');
			var $progress = $('#bkx-scan-progress');

			$btn.prop('disabled', true);
			$progress.show();
			$('.bkx-progress-bar').css('width', '10%');
			$('.bkx-progress-text').text(bkxPCIAdmin.i18n.scanning);

			// Animate progress.
			var progress = 10;
			var interval = setInterval(function() {
				progress += 5;
				if (progress < 90) {
					$('.bkx-progress-bar').css('width', progress + '%');
				}
			}, 500);

			$.post(bkxPCIAdmin.ajaxUrl, {
				action: 'bkx_pci_run_scan',
				nonce: bkxPCIAdmin.nonce,
				scan_type: scanType
			}, function(response) {
				clearInterval(interval);
				$('.bkx-progress-bar').css('width', '100%');

				if (response.success) {
					$('.bkx-progress-text').text(bkxPCIAdmin.i18n.scanComplete);
					setTimeout(function() {
						location.reload();
					}, 1000);
				} else {
					$('.bkx-progress-text').text(response.data.message || bkxPCIAdmin.i18n.scanError);
					$btn.prop('disabled', false);
				}
			}).fail(function() {
				clearInterval(interval);
				$('.bkx-progress-text').text(bkxPCIAdmin.i18n.requestError);
				$btn.prop('disabled', false);
			});
		},

		/**
		 * Run vulnerability scan.
		 */
		runVulnScan: function(e) {
			e.preventDefault();

			var $btn = $(this);
			$btn.prop('disabled', true).text(bkxPCIAdmin.i18n.scanning);

			$.post(bkxPCIAdmin.ajaxUrl, {
				action: 'bkx_pci_run_scan',
				nonce: bkxPCIAdmin.nonce,
				scan_type: 'vulnerability'
			}, function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message || bkxPCIAdmin.i18n.scanError);
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Run Vulnerability Scan');
				}
			});
		},

		/**
		 * Show resolve modal.
		 */
		showResolveModal: function(e) {
			e.preventDefault();

			var vulnId = $(this).data('id');
			$('#resolve-vuln-id').val(vulnId);
			$('#resolve-status').val('resolved');
			$('#resolve-notes').val('');
			$('#bkx-resolve-modal').show();
		},

		/**
		 * Submit resolve.
		 */
		submitResolve: function(e) {
			e.preventDefault();

			var $btn = $(this);
			$btn.prop('disabled', true);

			$.post(bkxPCIAdmin.ajaxUrl, {
				action: 'bkx_pci_resolve_vulnerability',
				nonce: bkxPCIAdmin.nonce,
				vulnerability_id: $('#resolve-vuln-id').val(),
				status: $('#resolve-status').val(),
				resolution: $('#resolve-notes').val()
			}, function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message || bkxPCIAdmin.i18n.requestError);
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Show vulnerability details.
		 */
		showVulnDetails: function(e) {
			e.preventDefault();

			var details = $(this).data('details');
			var html = '<table class="form-table">';

			for (var key in details) {
				if (details.hasOwnProperty(key) && details[key]) {
					html += '<tr><th>' + key.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); }) + '</th>';
					html += '<td>' + BKXPCI.escapeHtml(String(details[key])) + '</td></tr>';
				}
			}

			html += '</table>';

			$('#bkx-vuln-details').html(html);
			$('#bkx-view-modal').show();
		},

		/**
		 * Show log details.
		 */
		showLogDetails: function(e) {
			e.preventDefault();

			var details = $(this).data('details');
			$('#bkx-details-content').text(JSON.stringify(details, null, 2));
			$('#bkx-details-modal').show();
		},

		/**
		 * Export audit log.
		 */
		exportAuditLog: function(e) {
			e.preventDefault();

			var $btn = $(this);
			$btn.prop('disabled', true);

			var params = new URLSearchParams(window.location.search);

			$.post(bkxPCIAdmin.ajaxUrl, {
				action: 'bkx_pci_export_report',
				nonce: bkxPCIAdmin.nonce,
				report_type: 'audit',
				category: params.get('category') || '',
				severity: params.get('severity') || '',
				search: params.get('search') || ''
			}, function(response) {
				$btn.prop('disabled', false);

				if (response.success && response.data.url) {
					window.location.href = response.data.url;
				} else {
					alert(response.data.message || bkxPCIAdmin.i18n.requestError);
				}
			});
		},

		/**
		 * Generate report.
		 */
		generateReport: function(e) {
			e.preventDefault();

			var $btn = $(this);
			var reportType = $btn.data('type');
			var $result = $('#bkx-report-result');

			$btn.prop('disabled', true);

			$.post(bkxPCIAdmin.ajaxUrl, {
				action: 'bkx_pci_export_report',
				nonce: bkxPCIAdmin.nonce,
				report_type: reportType,
				date_from: $('#report-date-from').val() || '',
				date_to: $('#report-date-to').val() || ''
			}, function(response) {
				$btn.prop('disabled', false);

				if (response.success && response.data.url) {
					$('#bkx-report-download').attr('href', response.data.url);
					$result.show();
					$('html, body').animate({
						scrollTop: $result.offset().top - 50
					}, 500);
				} else {
					alert(response.data.message || bkxPCIAdmin.i18n.requestError);
				}
			});
		},

		/**
		 * Save settings.
		 */
		saveSettings: function(e) {
			e.preventDefault();

			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');

			$btn.prop('disabled', true);

			// Collect form data.
			var formData = {};
			$form.find('input, select, textarea').each(function() {
				var $field = $(this);
				var name = $field.attr('name');

				if (!name) return;

				if ($field.attr('type') === 'checkbox') {
					formData[name] = $field.is(':checked') ? 1 : 0;
				} else {
					formData[name] = $field.val();
				}
			});

			$.post(bkxPCIAdmin.ajaxUrl, $.extend({
				action: 'bkx_pci_save_settings',
				nonce: bkxPCIAdmin.nonce
			}, formData), function(response) {
				$btn.prop('disabled', false);

				if (response.success) {
					BKXPCI.showNotice('success', response.data.message);
				} else {
					BKXPCI.showNotice('error', response.data.message || bkxPCIAdmin.i18n.requestError);
				}
			}).fail(function() {
				$btn.prop('disabled', false);
				BKXPCI.showNotice('error', bkxPCIAdmin.i18n.requestError);
			});
		},

		/**
		 * Show admin notice.
		 */
		showNotice: function(type, message) {
			var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
			$('.wrap > h1').first().after($notice);

			setTimeout(function() {
				$notice.fadeOut(300, function() {
					$(this).remove();
				});
			}, 5000);
		},

		/**
		 * Escape HTML.
		 */
		escapeHtml: function(text) {
			var div = document.createElement('div');
			div.appendChild(document.createTextNode(text));
			return div.innerHTML;
		}
	};

	// Initialize on document ready.
	$(document).ready(function() {
		BKXPCI.init();
	});

})(jQuery);
