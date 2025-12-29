/**
 * Salesforce Connector Admin JavaScript
 *
 * @package BookingX\Salesforce
 */

(function($) {
	'use strict';

	var BKXSalesforce = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.initMappingTabs();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Connection.
			$('#bkx-sf-connect').on('click', this.connect);
			$('#bkx-sf-disconnect').on('click', this.disconnect);
			$('#bkx-sf-test-connection').on('click', this.testConnection);

			// Settings.
			$('#bkx-sf-settings-form').on('submit', this.saveSettings);
			$('#bkx-sf-generate-secret').on('click', this.generateSecret);

			// Sync.
			$('#bkx-sf-sync-now').on('click', this.syncNow);

			// Mappings.
			$('.bkx-add-mapping').on('click', this.openAddMappingModal);
			$('.bkx-edit-mapping').on('click', this.openEditMappingModal);
			$('.bkx-delete-mapping').on('click', this.deleteMapping);
			$('.bkx-fetch-sf-fields').on('click', this.fetchSfFields);
			$('#bkx-mapping-form').on('submit', this.saveMapping);

			// Modal.
			$('.bkx-modal-close, .bkx-modal-cancel').on('click', this.closeModal);
			$('.bkx-modal').on('click', function(e) {
				if ($(e.target).hasClass('bkx-modal')) {
					BKXSalesforce.closeModal();
				}
			});

			// Log details.
			$('.bkx-view-log-details').on('click', this.viewLogDetails);

			// WP field custom toggle.
			$('#mapping-wp-field').on('change', function() {
				if ($(this).val() === 'custom') {
					$('#mapping-wp-field-custom').show().focus();
				} else {
					$('#mapping-wp-field-custom').hide();
				}
			});

			// Clear logs.
			$('#bkx-clear-old-logs').on('click', this.clearOldLogs);
		},

		/**
		 * Initialize mapping tabs.
		 */
		initMappingTabs: function() {
			$('.bkx-mapping-tab').on('click', function() {
				var type = $(this).data('type');

				$('.bkx-mapping-tab').removeClass('active');
				$(this).addClass('active');

				$('.bkx-mapping-panel').hide();
				$('#panel-' + type).show();
			});
		},

		/**
		 * Connect to Salesforce.
		 */
		connect: function() {
			var $btn = $(this);
			$btn.prop('disabled', true).text(bkxSalesforce.strings.connecting);

			$.ajax({
				url: bkxSalesforce.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_sf_connect',
					nonce: bkxSalesforce.nonce
				},
				success: function(response) {
					if (response.success) {
						window.location.href = response.data.auth_url;
					} else {
						BKXSalesforce.showToast(response.data.message, 'error');
						$btn.prop('disabled', false).text('Connect to Salesforce');
					}
				},
				error: function() {
					BKXSalesforce.showToast(bkxSalesforce.strings.error, 'error');
					$btn.prop('disabled', false).text('Connect to Salesforce');
				}
			});
		},

		/**
		 * Disconnect from Salesforce.
		 */
		disconnect: function() {
			if (!confirm('Are you sure you want to disconnect from Salesforce?')) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true);

			$.ajax({
				url: bkxSalesforce.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_sf_disconnect',
					nonce: bkxSalesforce.nonce
				},
				success: function(response) {
					if (response.success) {
						BKXSalesforce.showToast(response.data.message, 'success');
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						BKXSalesforce.showToast(response.data.message, 'error');
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					BKXSalesforce.showToast(bkxSalesforce.strings.error, 'error');
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Test connection.
		 */
		testConnection: function() {
			var $btn = $(this);
			$btn.prop('disabled', true).text('Testing...');

			$.ajax({
				url: bkxSalesforce.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_sf_test_connection',
					nonce: bkxSalesforce.nonce
				},
				success: function(response) {
					if (response.success) {
						var info = 'API Version: ' + response.data.org.api_version + '\n';
						info += 'Objects Available: ' + response.data.org.sobjects;

						$('#bkx-sf-connection-info').text(info).show();
						BKXSalesforce.showToast(response.data.message, 'success');
					} else {
						BKXSalesforce.showToast(response.data.message, 'error');
					}
					$btn.prop('disabled', false).text('Test Connection');
				},
				error: function() {
					BKXSalesforce.showToast(bkxSalesforce.strings.error, 'error');
					$btn.prop('disabled', false).text('Test Connection');
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

			var data = $form.serializeArray();
			data.push({name: 'action', value: 'bkx_sf_save_settings'});
			data.push({name: 'nonce', value: bkxSalesforce.nonce});

			$.ajax({
				url: bkxSalesforce.ajaxUrl,
				method: 'POST',
				data: data,
				success: function(response) {
					if (response.success) {
						BKXSalesforce.showToast(response.data.message, 'success');
					} else {
						BKXSalesforce.showToast(response.data.message, 'error');
					}
					$btn.prop('disabled', false);
				},
				error: function() {
					BKXSalesforce.showToast(bkxSalesforce.strings.error, 'error');
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Generate webhook secret.
		 */
		generateSecret: function() {
			var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
			var secret = '';
			for (var i = 0; i < 32; i++) {
				secret += chars.charAt(Math.floor(Math.random() * chars.length));
			}
			$('#webhook_secret').val(secret);
		},

		/**
		 * Manual sync.
		 */
		syncNow: function() {
			var $btn = $(this);
			var syncType = $('#bkx-sf-sync-type').val();
			var limit = $('#bkx-sf-sync-limit').val();

			$btn.prop('disabled', true);
			$('#bkx-sf-sync-progress').show();
			$('#bkx-sf-sync-result').hide();

			$.ajax({
				url: bkxSalesforce.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_sf_sync_now',
					nonce: bkxSalesforce.nonce,
					sync_type: syncType,
					limit: limit
				},
				success: function(response) {
					$('#bkx-sf-sync-progress').hide();
					$btn.prop('disabled', false);

					if (response.success) {
						var result = response.data;
						var html = '<strong>Sync Complete</strong><br>';
						html += 'Contacts: ' + result.contacts + '<br>';
						html += 'Leads: ' + result.leads + '<br>';
						html += 'Opportunities: ' + result.opportunities;

						if (result.errors && result.errors.length) {
							html += '<br><br><strong>Errors:</strong><br>';
							html += result.errors.join('<br>');
						}

						$('#bkx-sf-sync-result')
							.removeClass('error')
							.addClass('success')
							.html(html)
							.show();
					} else {
						$('#bkx-sf-sync-result')
							.removeClass('success')
							.addClass('error')
							.html(response.data.message)
							.show();
					}
				},
				error: function() {
					$('#bkx-sf-sync-progress').hide();
					$btn.prop('disabled', false);
					BKXSalesforce.showToast(bkxSalesforce.strings.error, 'error');
				}
			});
		},

		/**
		 * Open add mapping modal.
		 */
		openAddMappingModal: function() {
			var type = $(this).data('type');

			$('#bkx-modal-title').text('Add Field Mapping');
			$('#mapping-id').val('');
			$('#mapping-object-type').val(type);
			$('#bkx-mapping-form')[0].reset();
			$('#mapping-wp-field-custom').hide();

			// Load SF fields.
			BKXSalesforce.loadSfFieldsForModal(type);

			$('#bkx-mapping-modal').show();
		},

		/**
		 * Open edit mapping modal.
		 */
		openEditMappingModal: function() {
			var mapping = $(this).data('mapping');

			$('#bkx-modal-title').text('Edit Field Mapping');
			$('#mapping-id').val(mapping.id);
			$('#mapping-object-type').val(mapping.object_type);
			$('#mapping-direction').val(mapping.sync_direction);
			$('#mapping-transform').val(mapping.transform || '');
			$('#mapping-active').prop('checked', mapping.is_active == 1);

			// Handle WP field.
			var $wpField = $('#mapping-wp-field');
			if ($wpField.find('option[value="' + mapping.wp_field + '"]').length) {
				$wpField.val(mapping.wp_field);
				$('#mapping-wp-field-custom').hide();
			} else {
				$wpField.val('custom');
				$('#mapping-wp-field-custom').val(mapping.wp_field).show();
			}

			// Load SF fields and select current.
			BKXSalesforce.loadSfFieldsForModal(mapping.object_type, mapping.sf_field);

			$('#bkx-mapping-modal').show();
		},

		/**
		 * Load Salesforce fields for modal.
		 */
		loadSfFieldsForModal: function(objectType, selectedField) {
			var $select = $('#mapping-sf-field');

			// Check cache.
			if (bkxSfFieldsCache && bkxSfFieldsCache[objectType]) {
				BKXSalesforce.populateSfFieldSelect($select, bkxSfFieldsCache[objectType], selectedField);
				return;
			}

			$select.html('<option value="">Loading...</option>');

			$.ajax({
				url: bkxSalesforce.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_sf_get_sf_fields',
					nonce: bkxSalesforce.nonce,
					object_type: objectType
				},
				success: function(response) {
					if (response.success) {
						bkxSfFieldsCache[objectType] = response.data.fields;
						BKXSalesforce.populateSfFieldSelect($select, response.data.fields, selectedField);
					} else {
						$select.html('<option value="">Error loading fields</option>');
					}
				},
				error: function() {
					$select.html('<option value="">Error loading fields</option>');
				}
			});
		},

		/**
		 * Populate SF field select.
		 */
		populateSfFieldSelect: function($select, fields, selectedField) {
			var html = '<option value="">Select field...</option>';

			fields.forEach(function(field) {
				var selected = field.name === selectedField ? ' selected' : '';
				var required = field.required ? ' *' : '';
				html += '<option value="' + field.name + '"' + selected + '>';
				html += field.label + ' (' + field.name + ')' + required;
				html += '</option>';
			});

			$select.html(html);
		},

		/**
		 * Fetch SF fields.
		 */
		fetchSfFields: function() {
			var type = $(this).data('type');
			var $btn = $(this);

			$btn.prop('disabled', true).text('Fetching...');

			$.ajax({
				url: bkxSalesforce.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_sf_get_sf_fields',
					nonce: bkxSalesforce.nonce,
					object_type: type
				},
				success: function(response) {
					$btn.prop('disabled', false).text('Fetch Salesforce Fields');

					if (response.success) {
						bkxSfFieldsCache[type] = response.data.fields;
						BKXSalesforce.showToast('Loaded ' + response.data.fields.length + ' fields', 'success');
					} else {
						BKXSalesforce.showToast(response.data.message, 'error');
					}
				},
				error: function() {
					$btn.prop('disabled', false).text('Fetch Salesforce Fields');
					BKXSalesforce.showToast(bkxSalesforce.strings.error, 'error');
				}
			});
		},

		/**
		 * Save mapping.
		 */
		saveMapping: function(e) {
			e.preventDefault();

			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true);

			// Handle custom WP field.
			var wpField = $('#mapping-wp-field').val();
			if (wpField === 'custom') {
				wpField = $('#mapping-wp-field-custom').val();
			}

			// Handle SF field (custom or select).
			var sfField = $('#mapping-sf-field').val();
			var sfFieldCustom = $('#mapping-sf-field-custom').val();
			if (sfFieldCustom) {
				sfField = sfFieldCustom;
			}

			$.ajax({
				url: bkxSalesforce.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_sf_save_field_mapping',
					nonce: bkxSalesforce.nonce,
					id: $('#mapping-id').val(),
					object_type: $('#mapping-object-type').val(),
					wp_field: wpField,
					sf_field: sfField,
					sync_direction: $('#mapping-direction').val(),
					transform: $('#mapping-transform').val(),
					is_active: $('#mapping-active').is(':checked') ? 1 : 0
				},
				success: function(response) {
					$btn.prop('disabled', false);

					if (response.success) {
						BKXSalesforce.showToast(response.data.message, 'success');
						BKXSalesforce.closeModal();
						location.reload();
					} else {
						BKXSalesforce.showToast(response.data.message, 'error');
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					BKXSalesforce.showToast(bkxSalesforce.strings.error, 'error');
				}
			});
		},

		/**
		 * Delete mapping.
		 */
		deleteMapping: function() {
			if (!confirm(bkxSalesforce.strings.confirmDelete)) {
				return;
			}

			var $btn = $(this);
			var id = $btn.data('id');

			$btn.prop('disabled', true);

			$.ajax({
				url: bkxSalesforce.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_sf_delete_field_mapping',
					nonce: bkxSalesforce.nonce,
					id: id
				},
				success: function(response) {
					if (response.success) {
						$btn.closest('tr').fadeOut(function() {
							$(this).remove();
						});
						BKXSalesforce.showToast(response.data.message, 'success');
					} else {
						BKXSalesforce.showToast(response.data.message, 'error');
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					BKXSalesforce.showToast(bkxSalesforce.strings.error, 'error');
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * View log details.
		 */
		viewLogDetails: function() {
			var request = $(this).data('request');
			var response = $(this).data('response');

			try {
				request = JSON.stringify(JSON.parse(request), null, 2);
			} catch (e) {
				// Keep as is.
			}

			try {
				response = JSON.stringify(JSON.parse(response), null, 2);
			} catch (e) {
				// Keep as is.
			}

			$('#bkx-log-request-data').text(request || 'No request data');
			$('#bkx-log-response-data').text(response || 'No response data');
			$('#bkx-log-details-modal').show();
		},

		/**
		 * Clear old logs.
		 */
		clearOldLogs: function() {
			if (!confirm('Clear all logs older than 30 days?')) {
				return;
			}

			BKXSalesforce.showToast('Clearing logs...', 'success');
			setTimeout(function() {
				location.reload();
			}, 1000);
		},

		/**
		 * Close modal.
		 */
		closeModal: function() {
			$('.bkx-modal').hide();
		},

		/**
		 * Show toast notification.
		 */
		showToast: function(message, type) {
			var $toast = $('<div class="bkx-toast ' + type + '">' + message + '</div>');
			$('body').append($toast);

			setTimeout(function() {
				$toast.fadeOut(function() {
					$(this).remove();
				});
			}, 3000);
		}
	};

	$(document).ready(function() {
		BKXSalesforce.init();
	});

})(jQuery);
