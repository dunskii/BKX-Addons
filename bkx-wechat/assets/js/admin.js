/**
 * WeChat Admin JavaScript
 *
 * @package BookingX\WeChat
 */

(function($) {
	'use strict';

	var BkxWeChat = {
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
			// Save settings.
			$('#bkx-wechat-settings-form').on('submit', this.saveSettings.bind(this));

			// Test connection.
			$('#bkx-test-connection').on('click', this.testConnection.bind(this));

			// Sync menu.
			$('#bkx-sync-menu').on('click', this.syncMenu.bind(this));

			// QR code generation.
			$('[data-type="follow"]').on('click', this.generateFollowQR.bind(this));
			$('#generate-service-qr').on('click', this.generateServiceQR.bind(this));
			$('#generate-mp-qr, #bkx-generate-mp-qr').on('click', this.generateMiniProgramQR.bind(this));
			$('#bkx-batch-generate').on('click', this.batchGenerateQR.bind(this));

			// Copy code.
			$('#bkx-copy-code').on('click', this.copyCode.bind(this));

			// Auto reply rules.
			$('#bkx-add-rule').on('click', this.showRuleModal.bind(this));
			$(document).on('click', '.bkx-edit-rule', this.editRule.bind(this));
			$(document).on('click', '.bkx-delete-rule', this.deleteRule.bind(this));
			$('#bkx-rule-form').on('submit', this.saveRule.bind(this));

			// Modal.
			$(document).on('click', '.bkx-modal-close', this.closeModal.bind(this));
			$(document).on('click', '.bkx-modal', function(e) {
				if ($(e.target).hasClass('bkx-modal')) {
					BkxWeChat.closeModal();
				}
			});

			// Menu builder.
			$('.bkx-menu-item').on('click', this.selectMenuItem.bind(this));
		},

		/**
		 * Save settings.
		 */
		saveSettings: function(e) {
			e.preventDefault();

			var $form = $(e.target);
			var $button = $('#bkx-save-settings');
			var $spinner = $form.find('.spinner');
			var $status = $('#bkx-save-status');

			$button.prop('disabled', true);
			$spinner.addClass('is-active');
			$status.removeClass('success error').text('');

			var settings = this.collectSettings();

			$.ajax({
				url: bkxWeChat.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_wechat_save_settings',
					nonce: bkxWeChat.nonce,
					settings: settings
				},
				success: function(response) {
					if (response.success) {
						$status.addClass('success').text(response.data.message);
					} else {
						$status.addClass('error').text(response.data.message || bkxWeChat.i18n.error);
					}
				},
				error: function() {
					$status.addClass('error').text(bkxWeChat.i18n.error);
				},
				complete: function() {
					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			});
		},

		/**
		 * Collect settings from form.
		 */
		collectSettings: function() {
			var settings = {
				enabled: $('#enabled').is(':checked'),
				official_account_enabled: $('#official_account_enabled').is(':checked'),
				mini_program_enabled: $('#mini_program_enabled').is(':checked'),
				wechat_pay_enabled: $('#wechat_pay_enabled').is(':checked'),
				app_id: $('#app_id').val(),
				app_secret: $('#app_secret').val(),
				mini_program_app_id: $('#mini_program_app_id').val(),
				mini_program_secret: $('#mini_program_secret').val(),
				mch_id: $('#mch_id').val(),
				api_key: $('#api_key').val(),
				api_v3_key: $('#api_v3_key').val(),
				certificate_serial: $('#certificate_serial').val(),
				certificate_path: $('#certificate_path').val(),
				private_key_path: $('#private_key_path').val(),
				auto_reply_enabled: $('#auto_reply_enabled').is(':checked'),
				qr_code_enabled: $('#qr_code_enabled').is(':checked'),
				sandbox_mode: $('#sandbox_mode').is(':checked'),
				debug_mode: $('#debug_mode').is(':checked'),
				template_messages: {},
				auto_reply_rules: this.autoReplyRules || []
			};

			// Collect template messages.
			$('input[name^="template_messages["]').each(function() {
				var name = $(this).attr('name').match(/\[([^\]]+)\]/)[1];
				settings.template_messages[name] = $(this).val();
			});

			return settings;
		},

		/**
		 * Test connection.
		 */
		testConnection: function(e) {
			e.preventDefault();

			var $button = $(e.target);
			var $status = $('#bkx-save-status');

			$button.prop('disabled', true);
			$status.removeClass('success error').text(bkxWeChat.i18n.testing);

			$.ajax({
				url: bkxWeChat.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_wechat_test_connection',
					nonce: bkxWeChat.nonce
				},
				success: function(response) {
					if (response.success) {
						var details = '';
						if (response.data.details) {
							details = ' (' + response.data.details.official_account + ', ' + response.data.details.mini_program + ')';
						}
						$status.addClass('success').text(response.data.message + details);
					} else {
						$status.addClass('error').text(response.data.message || bkxWeChat.i18n.connectionFailed);
					}
				},
				error: function() {
					$status.addClass('error').text(bkxWeChat.i18n.error);
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Sync menu.
		 */
		syncMenu: function(e) {
			e.preventDefault();

			var $button = $(e.target);
			var $status = $('#bkx-menu-status');

			$button.prop('disabled', true);
			$status.text(bkxWeChat.i18n.syncing);

			$.ajax({
				url: bkxWeChat.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_wechat_sync_menu',
					nonce: bkxWeChat.nonce
				},
				success: function(response) {
					if (response.success) {
						$status.text(response.data.message).css('color', '#00a32a');
					} else {
						$status.text(response.data.message || bkxWeChat.i18n.error).css('color', '#d63638');
					}
				},
				error: function() {
					$status.text(bkxWeChat.i18n.error).css('color', '#d63638');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Generate follow QR code.
		 */
		generateFollowQR: function(e) {
			e.preventDefault();
			this.generateQR('follow', {}, '#qr-follow-preview');
		},

		/**
		 * Generate service QR code.
		 */
		generateServiceQR: function(e) {
			e.preventDefault();

			var serviceId = $('#qr-service-select').val();
			if (!serviceId) {
				alert('Please select a service.');
				return;
			}

			this.generateQR('service', { service_id: serviceId }, '#qr-service-preview');
		},

		/**
		 * Generate Mini Program QR code.
		 */
		generateMiniProgramQR: function(e) {
			e.preventDefault();

			var page = $('#qr-mp-page, #mp_qr_page').val() || 'pages/index/index';
			var scene = $('#qr-mp-scene, #mp_qr_scene').val() || '';

			this.generateQR('mini_program', { page: page, scene: scene }, '#qr-mp-preview, #mp-qr-preview');
		},

		/**
		 * Generate QR code.
		 */
		generateQR: function(type, params, previewSelector) {
			var $preview = $(previewSelector);

			$preview.html('<span class="spinner is-active"></span>');

			$.ajax({
				url: bkxWeChat.ajaxUrl,
				type: 'POST',
				data: $.extend({
					action: 'bkx_wechat_generate_qrcode',
					nonce: bkxWeChat.nonce,
					type: type
				}, params),
				success: function(response) {
					if (response.success) {
						var imgSrc = response.data.base64 || response.data.url;
						$preview.html('<img src="' + imgSrc + '" alt="QR Code">');

						if (type === 'mini_program') {
							$('#mp-qr-image').attr('src', imgSrc);
							$('#mp-qr-preview').show();
						}
					} else {
						$preview.html('<span style="color: #d63638;">' + (response.data.message || 'Failed') + '</span>');
					}
				},
				error: function() {
					$preview.html('<span style="color: #d63638;">Error generating QR code.</span>');
				}
			});
		},

		/**
		 * Batch generate QR codes.
		 */
		batchGenerateQR: function(e) {
			e.preventDefault();

			var $button = $(e.target);
			var $results = $('#bkx-batch-results');
			var $grid = $results.find('.bkx-qr-batch-grid');

			$button.prop('disabled', true).text('Generating...');
			$results.hide();
			$grid.empty();

			// Get all service IDs.
			var serviceIds = [];
			$('#qr-service-select option').each(function() {
				var val = $(this).val();
				if (val) {
					serviceIds.push({
						id: val,
						name: $(this).text()
					});
				}
			});

			if (serviceIds.length === 0) {
				$button.prop('disabled', false).text('Generate All Service QR Codes');
				alert('No services found.');
				return;
			}

			var generated = 0;
			var total = serviceIds.length;

			serviceIds.forEach(function(service) {
				$.ajax({
					url: bkxWeChat.ajaxUrl,
					type: 'POST',
					data: {
						action: 'bkx_wechat_generate_qrcode',
						nonce: bkxWeChat.nonce,
						type: 'service',
						service_id: service.id
					},
					success: function(response) {
						generated++;

						if (response.success) {
							var imgSrc = response.data.url || response.data.base64;
							$grid.append(
								'<div class="bkx-qr-batch-item">' +
								'<img src="' + imgSrc + '" alt="' + service.name + '">' +
								'<p>' + service.name + '</p>' +
								'</div>'
							);
						}

						if (generated === total) {
							$button.prop('disabled', false).text('Generate All Service QR Codes');
							$results.show();
						}
					},
					error: function() {
						generated++;
						if (generated === total) {
							$button.prop('disabled', false).text('Generate All Service QR Codes');
							$results.show();
						}
					}
				});
			});
		},

		/**
		 * Copy code.
		 */
		copyCode: function(e) {
			e.preventDefault();

			var code = $('#bkx-code-content').text();
			navigator.clipboard.writeText(code).then(function() {
				var $button = $(e.target);
				var originalText = $button.text();
				$button.text('Copied!');
				setTimeout(function() {
					$button.text(originalText);
				}, 2000);
			});
		},

		/**
		 * Auto reply rules storage.
		 */
		autoReplyRules: bkxWeChat.settings.auto_reply_rules || [],

		/**
		 * Show rule modal.
		 */
		showRuleModal: function(e) {
			e.preventDefault();

			$('#rule_index').val(-1);
			$('#rule_keyword').val('');
			$('#rule_type').val('text');
			$('#rule_content').val('');

			$('#bkx-rule-modal').show();
		},

		/**
		 * Edit rule.
		 */
		editRule: function(e) {
			e.preventDefault();

			var index = $(e.target).data('index');
			var rule = this.autoReplyRules[index];

			if (!rule) return;

			$('#rule_index').val(index);
			$('#rule_keyword').val(rule.keyword || '');
			$('#rule_type').val(rule.type || 'text');
			$('#rule_content').val(rule.content || '');

			$('#bkx-rule-modal').show();
		},

		/**
		 * Delete rule.
		 */
		deleteRule: function(e) {
			e.preventDefault();

			if (!confirm('Are you sure you want to delete this rule?')) {
				return;
			}

			var index = $(e.target).data('index');
			this.autoReplyRules.splice(index, 1);

			this.refreshRulesTable();
		},

		/**
		 * Save rule.
		 */
		saveRule: function(e) {
			e.preventDefault();

			var index = parseInt($('#rule_index').val(), 10);
			var rule = {
				keyword: $('#rule_keyword').val(),
				type: $('#rule_type').val(),
				content: $('#rule_content').val()
			};

			if (index >= 0) {
				this.autoReplyRules[index] = rule;
			} else {
				this.autoReplyRules.push(rule);
			}

			this.refreshRulesTable();
			this.closeModal();
		},

		/**
		 * Refresh rules table.
		 */
		refreshRulesTable: function() {
			var $tbody = $('#auto-reply-rules-body');
			$tbody.empty();

			if (this.autoReplyRules.length === 0) {
				$tbody.append(
					'<tr class="no-items">' +
					'<td colspan="4">No rules configured. Click "Add Rule" to create one.</td>' +
					'</tr>'
				);
				return;
			}

			var that = this;
			this.autoReplyRules.forEach(function(rule, index) {
				$tbody.append(
					'<tr data-index="' + index + '">' +
					'<td><code>' + that.escapeHtml(rule.keyword) + '</code></td>' +
					'<td>' + that.escapeHtml(rule.type) + '</td>' +
					'<td>' + that.escapeHtml(rule.content.substring(0, 50)) + (rule.content.length > 50 ? '...' : '') + '</td>' +
					'<td>' +
					'<button type="button" class="button button-small bkx-edit-rule" data-index="' + index + '">Edit</button> ' +
					'<button type="button" class="button button-small bkx-delete-rule" data-index="' + index + '">Delete</button>' +
					'</td>' +
					'</tr>'
				);
			});
		},

		/**
		 * Escape HTML.
		 */
		escapeHtml: function(str) {
			var div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		},

		/**
		 * Close modal.
		 */
		closeModal: function() {
			$('.bkx-modal').hide();
		},

		/**
		 * Select menu item.
		 */
		selectMenuItem: function(e) {
			$('.bkx-menu-item').removeClass('active');
			$(e.currentTarget).addClass('active');

			var index = $(e.currentTarget).data('index');
			this.showMenuEditor(index);
		},

		/**
		 * Show menu editor.
		 */
		showMenuEditor: function(index) {
			var menu = bkxWeChat.settings.menu_config || [];
			var item = menu[index] || { name: 'Menu ' + (index + 1), type: 'view', url: '' };

			var html = '<div class="bkx-menu-edit-form">' +
				'<p>' +
				'<label>Name</label>' +
				'<input type="text" id="menu_name" value="' + (item.name || '') + '">' +
				'</p>' +
				'<p>' +
				'<label>Type</label>' +
				'<select id="menu_type">' +
				'<option value="view"' + (item.type === 'view' ? ' selected' : '') + '>Link</option>' +
				'<option value="click"' + (item.type === 'click' ? ' selected' : '') + '>Click Event</option>' +
				'<option value="miniprogram"' + (item.type === 'miniprogram' ? ' selected' : '') + '>Mini Program</option>' +
				'</select>' +
				'</p>' +
				'<p class="menu-url-field">' +
				'<label>URL</label>' +
				'<input type="url" id="menu_url" value="' + (item.url || '') + '">' +
				'</p>' +
				'<p class="menu-key-field" style="display: none;">' +
				'<label>Key</label>' +
				'<input type="text" id="menu_key" value="' + (item.key || '') + '">' +
				'</p>' +
				'<p>' +
				'<button type="button" class="button button-primary" id="save-menu-item" data-index="' + index + '">Save</button>' +
				'</p>' +
				'</div>';

			$('#bkx-menu-form').html(html);

			$('#menu_type').on('change', function() {
				var type = $(this).val();
				if (type === 'view' || type === 'miniprogram') {
					$('.menu-url-field').show();
					$('.menu-key-field').hide();
				} else {
					$('.menu-url-field').hide();
					$('.menu-key-field').show();
				}
			});

			$('#save-menu-item').on('click', function() {
				var idx = $(this).data('index');
				var menuConfig = bkxWeChat.settings.menu_config || [];

				menuConfig[idx] = {
					name: $('#menu_name').val(),
					type: $('#menu_type').val(),
					url: $('#menu_url').val(),
					key: $('#menu_key').val()
				};

				bkxWeChat.settings.menu_config = menuConfig;

				// Update preview.
				$('.bkx-menu-item[data-index="' + idx + '"] .bkx-menu-name').text($('#menu_name').val());

				alert('Menu item saved. Click "Sync Menu to WeChat" to apply changes.');
			});
		}
	};

	$(document).ready(function() {
		BkxWeChat.init();
	});

})(jQuery);
