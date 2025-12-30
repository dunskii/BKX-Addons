/**
 * BKX API Builder Admin JavaScript
 *
 * @package BookingX\APIBuilder
 */

(function($) {
	'use strict';

	var BKXAPIBuilder = {
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

			// Endpoint events.
			$(document).on('click', '#bkx-add-endpoint', this.showEndpointModal);
			$(document).on('click', '.bkx-edit-endpoint', this.editEndpoint);
			$(document).on('click', '.bkx-delete-endpoint', this.deleteEndpoint);
			$(document).on('click', '.bkx-test-endpoint', this.testEndpoint);
			$('#bkx-endpoint-form').on('submit', this.saveEndpoint);

			// API Key events.
			$(document).on('click', '#bkx-generate-key', this.showKeyModal);
			$(document).on('click', '.bkx-revoke-key', this.revokeKey);
			$(document).on('click', '.bkx-copy-key, .bkx-copy-credential', this.copyToClipboard);
			$('#bkx-key-form').on('submit', this.generateKey);

			// Webhook events.
			$(document).on('click', '#bkx-add-webhook', this.showWebhookModal);
			$(document).on('click', '.bkx-test-webhook', this.testWebhook);
			$(document).on('click', '.bkx-delete-webhook', this.deleteWebhook);
			$('#bkx-webhook-form').on('submit', this.saveWebhook);

			// Log events.
			$(document).on('click', '.bkx-view-log', this.viewLog);
			$(document).on('click', '#bkx-clear-logs', this.clearLogs);

			// Documentation events.
			$(document).on('click', '#bkx-export-openapi, #bkx-export-markdown, #bkx-export-html', this.exportDocs);

			// Settings form.
			$('#bkx-api-settings-form').on('submit', this.saveSettings);

			// All events checkbox.
			$(document).on('change', '.bkx-all-events', this.toggleAllEvents);
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
		 * Show endpoint modal.
		 */
		showEndpointModal: function() {
			$('#endpoint_id').val(0);
			$('#bkx-endpoint-form')[0].reset();
			$('#endpoint_handler_config').val('{}');
			$('#endpoint_request_schema').val('{}');
			$('#bkx-endpoint-modal').show();
		},

		/**
		 * Edit endpoint.
		 */
		editEndpoint: function() {
			var id = $(this).data('id');

			// In a real implementation, fetch endpoint data via AJAX.
			// For now, we'll just open the modal.
			$('#endpoint_id').val(id);
			$('#bkx-endpoint-modal').show();
		},

		/**
		 * Save endpoint.
		 */
		saveEndpoint: function(e) {
			e.preventDefault();

			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');

			$btn.prop('disabled', true).text(bkxAPIBuilder.i18n.saving);

			$.post(bkxAPIBuilder.ajaxUrl, {
				action: 'bkx_api_save_endpoint',
				nonce: bkxAPIBuilder.nonce,
				endpoint_id: $('#endpoint_id').val(),
				name: $('#endpoint_name').val(),
				method: $('#endpoint_method').val(),
				route: $('#endpoint_route').val(),
				description: $('#endpoint_description').val(),
				handler_type: $('#endpoint_handler_type').val(),
				handler_config: $('#endpoint_handler_config').val(),
				request_schema: $('#endpoint_request_schema').val(),
				authentication: $('#endpoint_auth').val(),
				rate_limit: $('#endpoint_rate_limit').val(),
				cache_enabled: $('#endpoint_cache_enabled').is(':checked') ? 1 : 0,
				status: $('#endpoint_status').val()
			}, function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message || bkxAPIBuilder.i18n.error);
					$btn.prop('disabled', false).text(bkxAPIBuilder.i18n.saved);
				}
			}).fail(function() {
				alert(bkxAPIBuilder.i18n.error);
				$btn.prop('disabled', false);
			});
		},

		/**
		 * Delete endpoint.
		 */
		deleteEndpoint: function() {
			if (!confirm(bkxAPIBuilder.i18n.confirmDelete)) {
				return;
			}

			var id = $(this).data('id');
			var $row = $(this).closest('tr');

			$.post(bkxAPIBuilder.ajaxUrl, {
				action: 'bkx_api_delete_endpoint',
				nonce: bkxAPIBuilder.nonce,
				endpoint_id: id
			}, function(response) {
				if (response.success) {
					$row.fadeOut(function() {
						$(this).remove();
					});
				} else {
					alert(response.data.message || bkxAPIBuilder.i18n.error);
				}
			});
		},

		/**
		 * Test endpoint.
		 */
		testEndpoint: function() {
			var $btn = $(this);
			var id = $btn.data('id');

			$btn.prop('disabled', true).text(bkxAPIBuilder.i18n.testing);

			$.post(bkxAPIBuilder.ajaxUrl, {
				action: 'bkx_api_test_endpoint',
				nonce: bkxAPIBuilder.nonce,
				endpoint_id: id
			}, function(response) {
				$btn.prop('disabled', false).text('Test');

				var msg = response.success
					? 'Status: ' + response.data.status_code + '\nDuration: ' + response.data.duration + 'ms'
					: 'Error: ' + (response.data.message || 'Unknown error');

				alert(msg);
			});
		},

		/**
		 * Show API key modal.
		 */
		showKeyModal: function() {
			$('#bkx-key-form')[0].reset();
			$('#bkx-key-modal').show();
		},

		/**
		 * Generate API key.
		 */
		generateKey: function(e) {
			e.preventDefault();

			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');

			$btn.prop('disabled', true).text(bkxAPIBuilder.i18n.generating);

			var ips = $('#key_ips').val().trim().split('\n').filter(function(ip) {
				return ip.trim() !== '';
			});

			$.post(bkxAPIBuilder.ajaxUrl, {
				action: 'bkx_api_generate_key',
				nonce: bkxAPIBuilder.nonce,
				name: $('#key_name').val(),
				rate_limit: $('#key_rate_limit').val(),
				expires_at: $('#key_expires').val(),
				allowed_ips: JSON.stringify(ips)
			}, function(response) {
				$btn.prop('disabled', false).text('Generate');

				if (response.success) {
					$('#bkx-key-modal').hide();

					// Show credentials modal.
					$('#new-api-key').text(response.data.api_key);
					$('#new-api-secret').text(response.data.api_secret);
					$('#bkx-credentials-modal').show();
				} else {
					alert(response.data.message || bkxAPIBuilder.i18n.error);
				}
			});
		},

		/**
		 * Revoke API key.
		 */
		revokeKey: function() {
			if (!confirm(bkxAPIBuilder.i18n.confirmRevoke)) {
				return;
			}

			var id = $(this).data('id');
			var $row = $(this).closest('tr');

			$.post(bkxAPIBuilder.ajaxUrl, {
				action: 'bkx_api_revoke_key',
				nonce: bkxAPIBuilder.nonce,
				key_id: id
			}, function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message || bkxAPIBuilder.i18n.error);
				}
			});
		},

		/**
		 * Copy to clipboard.
		 */
		copyToClipboard: function() {
			var $btn = $(this);
			var text;

			if ($btn.hasClass('bkx-copy-key')) {
				text = $btn.data('key');
			} else {
				var targetId = $btn.data('target');
				text = $('#' + targetId).text();
			}

			navigator.clipboard.writeText(text).then(function() {
				var originalText = $btn.html();
				$btn.html('<span class="dashicons dashicons-yes"></span>');
				setTimeout(function() {
					$btn.html(originalText);
				}, 2000);
			});
		},

		/**
		 * Show webhook modal.
		 */
		showWebhookModal: function() {
			$('#webhook_id').val(0);
			$('#bkx-webhook-form')[0].reset();
			$('#bkx-webhook-modal').show();
		},

		/**
		 * Save webhook.
		 */
		saveWebhook: function(e) {
			e.preventDefault();

			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');

			var events = [];
			$form.find('input[name="events[]"]:checked').each(function() {
				events.push($(this).val());
			});

			$btn.prop('disabled', true).text(bkxAPIBuilder.i18n.saving);

			$.post(bkxAPIBuilder.ajaxUrl, {
				action: 'bkx_api_save_webhook',
				nonce: bkxAPIBuilder.nonce,
				webhook_id: $('#webhook_id').val(),
				name: $('#webhook_name').val(),
				url: $('#webhook_url').val(),
				events: JSON.stringify(events),
				retry_count: $('#webhook_retry').val(),
				timeout: $('#webhook_timeout').val()
			}, function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message || bkxAPIBuilder.i18n.error);
					$btn.prop('disabled', false).text('Save Webhook');
				}
			});
		},

		/**
		 * Test webhook.
		 */
		testWebhook: function() {
			var $btn = $(this);
			var id = $btn.data('id');

			$btn.prop('disabled', true).text(bkxAPIBuilder.i18n.testing);

			$.post(bkxAPIBuilder.ajaxUrl, {
				action: 'bkx_api_test_webhook',
				nonce: bkxAPIBuilder.nonce,
				webhook_id: id
			}, function(response) {
				$btn.prop('disabled', false).text('Test');

				var msg = response.success
					? 'Success! Status: ' + response.data.response_code + '\nDuration: ' + response.data.duration + 'ms'
					: 'Failed: ' + (response.data.message || 'Unknown error');

				alert(msg);
			});
		},

		/**
		 * Delete webhook.
		 */
		deleteWebhook: function() {
			if (!confirm(bkxAPIBuilder.i18n.confirmDelete)) {
				return;
			}

			var id = $(this).data('id');
			var $row = $(this).closest('tr');

			$.post(bkxAPIBuilder.ajaxUrl, {
				action: 'bkx_api_delete_webhook',
				nonce: bkxAPIBuilder.nonce,
				webhook_id: id
			}, function(response) {
				if (response.success) {
					$row.fadeOut(function() {
						$(this).remove();
					});
				} else {
					alert(response.data.message || bkxAPIBuilder.i18n.error);
				}
			});
		},

		/**
		 * Toggle all events checkbox.
		 */
		toggleAllEvents: function() {
			var isChecked = $(this).is(':checked');
			$(this).closest('.bkx-checkbox-group')
				.find('input[type="checkbox"]').not(this)
				.prop('checked', isChecked);
		},

		/**
		 * View log details.
		 */
		viewLog: function() {
			var details = $(this).data('details');
			var html = '<table class="form-table">';

			for (var key in details) {
				if (details.hasOwnProperty(key) && details[key]) {
					var value = details[key];
					if (typeof value === 'object') {
						value = '<pre>' + BKXAPIBuilder.escapeHtml(JSON.stringify(JSON.parse(value), null, 2)) + '</pre>';
					} else {
						value = BKXAPIBuilder.escapeHtml(String(value));
					}

					html += '<tr><th>' + key.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); }) + '</th>';
					html += '<td>' + value + '</td></tr>';
				}
			}

			html += '</table>';

			$('#bkx-log-details').html(html);
			$('#bkx-log-modal').show();
		},

		/**
		 * Clear logs.
		 */
		clearLogs: function() {
			if (!confirm('Are you sure you want to clear all logs? This cannot be undone.')) {
				return;
			}

			$.post(bkxAPIBuilder.ajaxUrl, {
				action: 'bkx_api_clear_logs',
				nonce: bkxAPIBuilder.nonce
			}, function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message || bkxAPIBuilder.i18n.error);
				}
			});
		},

		/**
		 * Export documentation.
		 */
		exportDocs: function() {
			var format = $(this).data('format');

			$.post(bkxAPIBuilder.ajaxUrl, {
				action: 'bkx_api_export_docs',
				nonce: bkxAPIBuilder.nonce,
				format: format
			}, function(response) {
				if (response.success) {
					var blob = new Blob([response.data.content], { type: 'application/octet-stream' });
					var url = URL.createObjectURL(blob);
					var a = document.createElement('a');
					a.href = url;
					a.download = response.data.filename;
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					URL.revokeObjectURL(url);
				} else {
					alert(response.data.message || bkxAPIBuilder.i18n.error);
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

			$btn.prop('disabled', true).text(bkxAPIBuilder.i18n.saving);

			$.post(bkxAPIBuilder.ajaxUrl, $.extend({
				action: 'bkx_api_save_settings',
				nonce: bkxAPIBuilder.nonce
			}, formData), function(response) {
				$btn.prop('disabled', false).text('Save Settings');

				if (response.success) {
					BKXAPIBuilder.showNotice('success', response.data.message);
				} else {
					BKXAPIBuilder.showNotice('error', response.data.message || bkxAPIBuilder.i18n.error);
				}
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
		BKXAPIBuilder.init();
	});

})(jQuery);
