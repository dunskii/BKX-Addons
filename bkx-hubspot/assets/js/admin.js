/**
 * HubSpot Integration Admin JavaScript
 *
 * @package BookingX\HubSpot
 */

(function($) {
	'use strict';

	var BKXHubSpot = {
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
			$('#bkx-hs-connect').on('click', this.connect);
			$('#bkx-hs-disconnect').on('click', this.disconnect);
			$('#bkx-hs-test-connection').on('click', this.testConnection);

			// Settings.
			$('#bkx-hs-settings-form').on('submit', this.saveSettings);
			$('#bkx-add-to-list').on('change', this.toggleListRow);
			$('#bkx-load-lists').on('click', this.loadLists);
			$('#bkx-load-pipelines').on('click', this.loadPipelines);

			// Sync.
			$('#bkx-hs-sync-now').on('click', this.syncNow);

			// Mappings.
			$('.bkx-add-mapping').on('click', this.openAddMappingModal);
			$('.bkx-edit-mapping').on('click', this.openEditMappingModal);
			$('.bkx-delete-mapping').on('click', this.deleteMapping);
			$('.bkx-fetch-hs-props').on('click', this.fetchHsProps);
			$('#bkx-mapping-form').on('submit', this.saveMapping);

			// Modal.
			$('.bkx-modal-close, .bkx-modal-cancel').on('click', this.closeModal);
			$('.bkx-modal').on('click', function(e) {
				if ($(e.target).hasClass('bkx-modal')) {
					BKXHubSpot.closeModal();
				}
			});

			// WP field custom toggle.
			$('#mapping-wp-field').on('change', function() {
				if ($(this).val() === 'custom') {
					$('#mapping-wp-field-custom').show().focus();
				} else {
					$('#mapping-wp-field-custom').hide();
				}
			});
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
		 * Connect to HubSpot.
		 */
		connect: function() {
			var $btn = $(this);
			$btn.prop('disabled', true).text(bkxHubSpot.strings.connecting);

			$.ajax({
				url: bkxHubSpot.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_hs_connect',
					nonce: bkxHubSpot.nonce
				},
				success: function(response) {
					if (response.success) {
						window.location.href = response.data.auth_url;
					} else {
						BKXHubSpot.showToast(response.data.message, 'error');
						$btn.prop('disabled', false).text('Connect to HubSpot');
					}
				},
				error: function() {
					BKXHubSpot.showToast(bkxHubSpot.strings.error, 'error');
					$btn.prop('disabled', false).text('Connect to HubSpot');
				}
			});
		},

		/**
		 * Disconnect from HubSpot.
		 */
		disconnect: function() {
			if (!confirm('Are you sure you want to disconnect from HubSpot?')) {
				return;
			}

			var $btn = $(this);
			$btn.prop('disabled', true);

			$.ajax({
				url: bkxHubSpot.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_hs_disconnect',
					nonce: bkxHubSpot.nonce
				},
				success: function(response) {
					if (response.success) {
						BKXHubSpot.showToast(response.data.message, 'success');
						setTimeout(function() {
							location.reload();
						}, 1000);
					} else {
						BKXHubSpot.showToast(response.data.message, 'error');
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					BKXHubSpot.showToast(bkxHubSpot.strings.error, 'error');
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
				url: bkxHubSpot.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_hs_test_connection',
					nonce: bkxHubSpot.nonce
				},
				success: function(response) {
					if (response.success) {
						var info = 'Portal ID: ' + response.data.account.portal_id;
						$('#bkx-hs-connection-info').text(info).show();
						BKXHubSpot.showToast(response.data.message, 'success');
					} else {
						BKXHubSpot.showToast(response.data.message, 'error');
					}
					$btn.prop('disabled', false).text('Test Connection');
				},
				error: function() {
					BKXHubSpot.showToast(bkxHubSpot.strings.error, 'error');
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
			data.push({name: 'action', value: 'bkx_hs_save_settings'});
			data.push({name: 'nonce', value: bkxHubSpot.nonce});

			$.ajax({
				url: bkxHubSpot.ajaxUrl,
				method: 'POST',
				data: data,
				success: function(response) {
					if (response.success) {
						BKXHubSpot.showToast(response.data.message, 'success');
					} else {
						BKXHubSpot.showToast(response.data.message, 'error');
					}
					$btn.prop('disabled', false);
				},
				error: function() {
					BKXHubSpot.showToast(bkxHubSpot.strings.error, 'error');
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Toggle list row visibility.
		 */
		toggleListRow: function() {
			if ($(this).is(':checked')) {
				$('.bkx-list-row').show();
			} else {
				$('.bkx-list-row').hide();
			}
		},

		/**
		 * Load HubSpot lists.
		 */
		loadLists: function() {
			var $btn = $(this);
			$btn.prop('disabled', true).text('Loading...');

			$.ajax({
				url: bkxHubSpot.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_hs_get_lists',
					nonce: bkxHubSpot.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false).text('Load Lists');

					if (response.success) {
						var $select = $('#list_id');
						var current = $select.val();
						$select.html('<option value="">Select a list...</option>');

						response.data.lists.forEach(function(list) {
							var selected = list.id == current ? ' selected' : '';
							$select.append('<option value="' + list.id + '"' + selected + '>' + list.name + '</option>');
						});

						BKXHubSpot.showToast('Loaded ' + response.data.lists.length + ' lists', 'success');
					} else {
						BKXHubSpot.showToast(response.data.message, 'error');
					}
				},
				error: function() {
					$btn.prop('disabled', false).text('Load Lists');
					BKXHubSpot.showToast(bkxHubSpot.strings.error, 'error');
				}
			});
		},

		/**
		 * Load HubSpot pipelines.
		 */
		loadPipelines: function() {
			var $btn = $(this);
			$btn.prop('disabled', true).text('Loading...');

			$.ajax({
				url: bkxHubSpot.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_hs_get_pipelines',
					nonce: bkxHubSpot.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false).text('Load Pipelines');

					if (response.success) {
						var $pipelineSelect = $('#pipeline_id');
						var $stageSelect = $('#default_stage_id');
						var currentPipeline = $pipelineSelect.val();
						var currentStage = $stageSelect.val();

						$pipelineSelect.html('<option value="">Default Pipeline</option>');
						$stageSelect.html('<option value="">Select a stage...</option>');

						response.data.pipelines.forEach(function(pipeline) {
							var selected = pipeline.id == currentPipeline ? ' selected' : '';
							$pipelineSelect.append('<option value="' + pipeline.id + '"' + selected + '>' + pipeline.label + '</option>');

							pipeline.stages.forEach(function(stage) {
								var stageSelected = stage.id == currentStage ? ' selected' : '';
								$stageSelect.append('<option value="' + stage.id + '"' + stageSelected + '>' + pipeline.label + ' - ' + stage.label + '</option>');
							});
						});

						BKXHubSpot.showToast('Loaded ' + response.data.pipelines.length + ' pipelines', 'success');
					} else {
						BKXHubSpot.showToast(response.data.message, 'error');
					}
				},
				error: function() {
					$btn.prop('disabled', false).text('Load Pipelines');
					BKXHubSpot.showToast(bkxHubSpot.strings.error, 'error');
				}
			});
		},

		/**
		 * Manual sync.
		 */
		syncNow: function() {
			var $btn = $(this);
			var syncType = $('#bkx-hs-sync-type').val();
			var limit = $('#bkx-hs-sync-limit').val();

			$btn.prop('disabled', true);
			$('#bkx-hs-sync-progress').show();
			$('#bkx-hs-sync-result').hide();

			$.ajax({
				url: bkxHubSpot.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_hs_sync_now',
					nonce: bkxHubSpot.nonce,
					sync_type: syncType,
					limit: limit
				},
				success: function(response) {
					$('#bkx-hs-sync-progress').hide();
					$btn.prop('disabled', false);

					if (response.success) {
						var result = response.data;
						var html = '<strong>Sync Complete</strong><br>';
						html += 'Contacts: ' + result.contacts + '<br>';
						html += 'Deals: ' + result.deals;

						if (result.errors && result.errors.length) {
							html += '<br><br><strong>Errors:</strong><br>';
							html += result.errors.join('<br>');
						}

						$('#bkx-hs-sync-result')
							.removeClass('error')
							.addClass('success')
							.html(html)
							.show();
					} else {
						$('#bkx-hs-sync-result')
							.removeClass('success')
							.addClass('error')
							.html(response.data.message)
							.show();
					}
				},
				error: function() {
					$('#bkx-hs-sync-progress').hide();
					$btn.prop('disabled', false);
					BKXHubSpot.showToast(bkxHubSpot.strings.error, 'error');
				}
			});
		},

		/**
		 * Open add mapping modal.
		 */
		openAddMappingModal: function() {
			var type = $(this).data('type');

			$('#bkx-modal-title').text('Add Property Mapping');
			$('#mapping-id').val('');
			$('#mapping-object-type').val(type);
			$('#bkx-mapping-form')[0].reset();
			$('#mapping-wp-field-custom').hide();

			BKXHubSpot.loadHsPropsForModal(type);

			$('#bkx-mapping-modal').show();
		},

		/**
		 * Open edit mapping modal.
		 */
		openEditMappingModal: function() {
			var mapping = $(this).data('mapping');

			$('#bkx-modal-title').text('Edit Property Mapping');
			$('#mapping-id').val(mapping.id);
			$('#mapping-object-type').val(mapping.object_type);
			$('#mapping-direction').val(mapping.sync_direction);
			$('#mapping-active').prop('checked', mapping.is_active == 1);

			var $wpField = $('#mapping-wp-field');
			if ($wpField.find('option[value="' + mapping.wp_field + '"]').length) {
				$wpField.val(mapping.wp_field);
				$('#mapping-wp-field-custom').hide();
			} else {
				$wpField.val('custom');
				$('#mapping-wp-field-custom').val(mapping.wp_field).show();
			}

			BKXHubSpot.loadHsPropsForModal(mapping.object_type, mapping.hs_property);

			$('#bkx-mapping-modal').show();
		},

		/**
		 * Load HubSpot properties for modal.
		 */
		loadHsPropsForModal: function(objectType, selectedProp) {
			var $select = $('#mapping-hs-prop');

			if (bkxHsPropsCache && bkxHsPropsCache[objectType]) {
				BKXHubSpot.populateHsPropSelect($select, bkxHsPropsCache[objectType], selectedProp);
				return;
			}

			$select.html('<option value="">Loading...</option>');

			$.ajax({
				url: bkxHubSpot.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_hs_get_hs_properties',
					nonce: bkxHubSpot.nonce,
					object_type: objectType + 's' // HubSpot uses plural (contacts, deals)
				},
				success: function(response) {
					if (response.success) {
						bkxHsPropsCache[objectType] = response.data.properties;
						BKXHubSpot.populateHsPropSelect($select, response.data.properties, selectedProp);
					} else {
						$select.html('<option value="">Error loading properties</option>');
					}
				},
				error: function() {
					$select.html('<option value="">Error loading properties</option>');
				}
			});
		},

		/**
		 * Populate HubSpot property select.
		 */
		populateHsPropSelect: function($select, props, selectedProp) {
			var html = '<option value="">Select property...</option>';

			props.forEach(function(prop) {
				var selected = prop.name === selectedProp ? ' selected' : '';
				html += '<option value="' + prop.name + '"' + selected + '>';
				html += prop.label + ' (' + prop.name + ')';
				html += '</option>';
			});

			$select.html(html);
		},

		/**
		 * Fetch HubSpot properties.
		 */
		fetchHsProps: function() {
			var type = $(this).data('type');
			var $btn = $(this);

			$btn.prop('disabled', true).text('Fetching...');

			$.ajax({
				url: bkxHubSpot.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_hs_get_hs_properties',
					nonce: bkxHubSpot.nonce,
					object_type: type + 's'
				},
				success: function(response) {
					$btn.prop('disabled', false).text('Fetch HubSpot Properties');

					if (response.success) {
						bkxHsPropsCache[type] = response.data.properties;
						BKXHubSpot.showToast('Loaded ' + response.data.properties.length + ' properties', 'success');
					} else {
						BKXHubSpot.showToast(response.data.message, 'error');
					}
				},
				error: function() {
					$btn.prop('disabled', false).text('Fetch HubSpot Properties');
					BKXHubSpot.showToast(bkxHubSpot.strings.error, 'error');
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

			var wpField = $('#mapping-wp-field').val();
			if (wpField === 'custom') {
				wpField = $('#mapping-wp-field-custom').val();
			}

			var hsProp = $('#mapping-hs-prop').val();
			var hsPropCustom = $('#mapping-hs-prop-custom').val();
			if (hsPropCustom) {
				hsProp = hsPropCustom;
			}

			$.ajax({
				url: bkxHubSpot.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_hs_save_property_mapping',
					nonce: bkxHubSpot.nonce,
					id: $('#mapping-id').val(),
					object_type: $('#mapping-object-type').val(),
					wp_field: wpField,
					hs_property: hsProp,
					sync_direction: $('#mapping-direction').val(),
					is_active: $('#mapping-active').is(':checked') ? 1 : 0
				},
				success: function(response) {
					$btn.prop('disabled', false);

					if (response.success) {
						BKXHubSpot.showToast(response.data.message, 'success');
						BKXHubSpot.closeModal();
						location.reload();
					} else {
						BKXHubSpot.showToast(response.data.message, 'error');
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					BKXHubSpot.showToast(bkxHubSpot.strings.error, 'error');
				}
			});
		},

		/**
		 * Delete mapping.
		 */
		deleteMapping: function() {
			if (!confirm(bkxHubSpot.strings.confirmDelete)) {
				return;
			}

			var $btn = $(this);
			var id = $btn.data('id');

			$btn.prop('disabled', true);

			$.ajax({
				url: bkxHubSpot.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_hs_delete_property_mapping',
					nonce: bkxHubSpot.nonce,
					id: id
				},
				success: function(response) {
					if (response.success) {
						$btn.closest('tr').fadeOut(function() {
							$(this).remove();
						});
						BKXHubSpot.showToast(response.data.message, 'success');
					} else {
						BKXHubSpot.showToast(response.data.message, 'error');
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					BKXHubSpot.showToast(bkxHubSpot.strings.error, 'error');
					$btn.prop('disabled', false);
				}
			});
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
		BKXHubSpot.init();
	});

})(jQuery);
