/**
 * White Label Admin JavaScript
 *
 * @package BookingX\WhiteLabel
 */

(function($) {
	'use strict';

	const WhiteLabel = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.initColorPickers();
			this.initCodeTabs();
			this.initImportDropzone();
			this.updatePreview();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			// Save settings.
			$('.bkx-save-settings').on('click', this.saveSettings.bind(this));

			// Enable toggle.
			$('#bkx-wl-enabled').on('change', this.toggleEnabled.bind(this));

			// Image upload.
			$('.bkx-upload-image').on('click', this.openMediaUploader);
			$('.bkx-remove-image').on('click', this.removeImage);

			// Color presets.
			$('.bkx-preset').on('click', this.applyPreset.bind(this));

			// String replacements.
			$('.bkx-add-replacement').on('click', this.addReplacement);
			$(document).on('click', '.bkx-remove-replacement', this.removeReplacement);
			$('.bkx-add-suggestion').on('click', this.addSuggestion);

			// Menu visibility.
			$('.bkx-menu-visibility').on('change', this.updateMenuVisibility);
			$('#bkx-add-hide-slug').on('click', this.addHideSlug);
			$(document).on('click', '.bkx-remove-custom-hide', this.removeHideSlug);

			// Import/Export.
			$('.bkx-export-settings').on('click', this.exportSettings.bind(this));
			$('#bkx-select-import-file').on('click', () => $('#bkx-import-file').click());
			$('#bkx-import-file').on('change', this.handleFileSelect.bind(this));
			$('.bkx-confirm-import').on('click', this.confirmImport.bind(this));
			$('.bkx-cancel-import').on('click', this.cancelImport);
			$('.bkx-reset-settings').on('click', this.resetSettings.bind(this));

			// Email preview.
			$('.bkx-preview-email').on('click', this.previewEmail.bind(this));

			// Code tabs.
			$('.bkx-code-tab').on('click', this.switchCodeTab);

			// Color changes for preview.
			$(document).on('change', '.bkx-color-picker', this.updatePreview.bind(this));
		},

		/**
		 * Initialize color pickers.
		 */
		initColorPickers: function() {
			$('.bkx-color-picker').wpColorPicker({
				change: (event, ui) => {
					setTimeout(() => this.updatePreview(), 100);
				}
			});
		},

		/**
		 * Initialize code tabs.
		 */
		initCodeTabs: function() {
			// Initialize code editors if available.
			if (typeof wp !== 'undefined' && wp.codeEditor) {
				$('.bkx-code-editor').each(function() {
					const $textarea = $(this);
					const lang = $textarea.data('lang') || 'css';

					const settings = wp.codeEditor.defaultSettings ?
						_.clone(wp.codeEditor.defaultSettings) : {};

					settings.codemirror = _.extend({}, settings.codemirror, {
						mode: lang === 'javascript' ? 'javascript' : 'css',
						lineNumbers: true,
						lineWrapping: true,
						theme: 'default'
					});

					wp.codeEditor.initialize($textarea, settings);
				});
			}
		},

		/**
		 * Switch code tab.
		 */
		switchCodeTab: function() {
			const tab = $(this).data('tab');

			$('.bkx-code-tab').removeClass('active');
			$(this).addClass('active');

			$('.bkx-code-panel').removeClass('active');
			$('#bkx-panel-' + tab).addClass('active');
		},

		/**
		 * Toggle enabled state.
		 */
		toggleEnabled: function(e) {
			const enabled = $(e.target).is(':checked');
			// Visual feedback could be added here.
		},

		/**
		 * Open media uploader.
		 */
		openMediaUploader: function() {
			const $container = $(this).closest('.bkx-image-upload');
			const $input = $container.find('input[type="hidden"]');
			const $preview = $container.find('.bkx-image-preview');
			const $removeBtn = $container.find('.bkx-remove-image');

			const frame = wp.media({
				title: bkxWhiteLabel.i18n.selectImage,
				button: {
					text: bkxWhiteLabel.i18n.useImage
				},
				multiple: false
			});

			frame.on('select', function() {
				const attachment = frame.state().get('selection').first().toJSON();
				$input.val(attachment.url);
				$preview.html('<img src="' + attachment.url + '" alt="">');
				$removeBtn.show();
			});

			frame.open();
		},

		/**
		 * Remove image.
		 */
		removeImage: function() {
			const $container = $(this).closest('.bkx-image-upload');
			const $input = $container.find('input[type="hidden"]');
			const $preview = $container.find('.bkx-image-preview');

			$input.val('');
			$preview.html('');
			$(this).hide();
		},

		/**
		 * Apply color preset.
		 */
		applyPreset: function(e) {
			const preset = $(e.currentTarget).data('preset');
			const presets = {
				default: {
					primary: '#2271b1',
					secondary: '#135e96',
					accent: '#72aee6'
				},
				green: {
					primary: '#00a32a',
					secondary: '#007c1e',
					accent: '#6ce597'
				},
				purple: {
					primary: '#7c3aed',
					secondary: '#5b21b6',
					accent: '#a78bfa'
				},
				orange: {
					primary: '#ea580c',
					secondary: '#c2410c',
					accent: '#fb923c'
				},
				teal: {
					primary: '#0d9488',
					secondary: '#0f766e',
					accent: '#5eead4'
				},
				pink: {
					primary: '#db2777',
					secondary: '#be185d',
					accent: '#f472b6'
				}
			};

			if (presets[preset]) {
				$('#primary_color').wpColorPicker('color', presets[preset].primary);
				$('#secondary_color').wpColorPicker('color', presets[preset].secondary);
				$('#accent_color').wpColorPicker('color', presets[preset].accent);
				this.updatePreview();
			}
		},

		/**
		 * Update live preview.
		 */
		updatePreview: function() {
			const primary = $('#primary_color').val() || '#2271b1';
			const secondary = $('#secondary_color').val() || '#135e96';
			const accent = $('#accent_color').val() || '#72aee6';
			const success = $('#success_color').val() || '#00a32a';
			const warning = $('#warning_color').val() || '#dba617';
			const error = $('#error_color').val() || '#d63638';

			document.documentElement.style.setProperty('--bkx-primary', primary);
			document.documentElement.style.setProperty('--bkx-secondary', secondary);
			document.documentElement.style.setProperty('--bkx-accent', accent);
			document.documentElement.style.setProperty('--bkx-success', success);
			document.documentElement.style.setProperty('--bkx-warning', warning);
			document.documentElement.style.setProperty('--bkx-error', error);
		},

		/**
		 * Add string replacement row.
		 */
		addReplacement: function() {
			const $tbody = $('#bkx-replacements-body');
			const index = $tbody.find('.bkx-replacement-row').length;
			const template = $('#bkx-replacement-row-template').html();
			const html = template.replace(/\{\{index\}\}/g, index);

			$tbody.find('.bkx-no-replacements').remove();
			$tbody.append(html);
		},

		/**
		 * Remove string replacement row.
		 */
		removeReplacement: function() {
			$(this).closest('.bkx-replacement-row').remove();
		},

		/**
		 * Add suggestion replacement.
		 */
		addSuggestion: function() {
			const search = $(this).data('search');
			const replace = $(this).data('replace');
			const $tbody = $('#bkx-replacements-body');
			const index = $tbody.find('.bkx-replacement-row').length;

			// Check if already exists.
			let exists = false;
			$tbody.find('input[name*="[search]"]').each(function() {
				if ($(this).val() === search) {
					exists = true;
					return false;
				}
			});

			if (exists) {
				return;
			}

			const template = $('#bkx-replacement-row-template').html();
			let html = template.replace(/\{\{index\}\}/g, index);

			$tbody.find('.bkx-no-replacements').remove();
			$tbody.append(html);

			const $row = $tbody.find('.bkx-replacement-row').last();
			$row.find('input[name*="[search]"]').val(search);
			$row.find('input[name*="[replace]"]').val(replace);
		},

		/**
		 * Add custom hide slug.
		 */
		addHideSlug: function() {
			const slug = $('#bkx-new-hide-slug').val().trim();
			if (!slug) return;

			const html = `
				<div class="bkx-custom-hide-item">
					<input type="text" value="${slug}" class="regular-text" readonly>
					<button type="button" class="button bkx-remove-custom-hide">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
			`;

			$('#bkx-custom-hide-list').append(html);
			$('#bkx-new-hide-slug').val('');
		},

		/**
		 * Remove custom hide slug.
		 */
		removeHideSlug: function() {
			$(this).closest('.bkx-custom-hide-item').remove();
		},

		/**
		 * Collect all settings.
		 */
		collectSettings: function() {
			const settings = {};

			// Boolean fields.
			settings.enabled = $('#bkx-wl-enabled').is(':checked');
			settings.hide_bookingx_branding = $('input[name="hide_bookingx_branding"]').is(':checked');
			settings.hide_powered_by = $('input[name="hide_powered_by"]').is(':checked');
			settings.hide_plugin_notices = $('input[name="hide_plugin_notices"]').is(':checked');
			settings.hide_changelog = $('input[name="hide_changelog"]').is(':checked');

			// Text fields.
			const textFields = [
				'brand_name', 'brand_url', 'support_email', 'support_url',
				'custom_admin_footer', 'email_from_name', 'email_from_address',
				'email_footer_text'
			];
			textFields.forEach(field => {
				const $input = $(`#${field}`);
				if ($input.length) {
					settings[field] = $input.val();
				}
			});

			// URL fields (images).
			const urlFields = [
				'brand_logo', 'brand_logo_dark', 'brand_icon',
				'email_header_image', 'login_logo', 'login_background'
			];
			urlFields.forEach(field => {
				const $input = $(`#${field}`);
				if ($input.length) {
					settings[field] = $input.val();
				}
			});

			// Color fields.
			const colorFields = [
				'primary_color', 'secondary_color', 'accent_color',
				'success_color', 'warning_color', 'error_color',
				'text_color', 'background_color',
				'email_background_color', 'email_body_color',
				'email_text_color', 'email_link_color'
			];
			colorFields.forEach(field => {
				const $input = $(`#${field}`);
				if ($input.length) {
					settings[field] = $input.val();
				}
			});

			// CSS/JS fields.
			const codeFields = [
				'custom_css_admin', 'custom_css_frontend',
				'custom_js_admin', 'custom_js_frontend', 'login_custom_css'
			];
			codeFields.forEach(field => {
				const $input = $(`#${field}`);
				if ($input.length) {
					// Get value from CodeMirror if available.
					const cm = $input.next('.CodeMirror');
					if (cm.length && cm[0].CodeMirror) {
						settings[field] = cm[0].CodeMirror.getValue();
					} else {
						settings[field] = $input.val();
					}
				}
			});

			// String replacements.
			settings.replace_strings = [];
			$('.bkx-replacement-row').each(function() {
				const search = $(this).find('input[name*="[search]"]').val();
				const replace = $(this).find('input[name*="[replace]"]').val();
				if (search) {
					settings.replace_strings.push({ search, replace });
				}
			});

			// Hidden menu items.
			settings.hide_menu_items = [];
			$('.bkx-menu-visibility').each(function() {
				if (!$(this).is(':checked')) {
					settings.hide_menu_items.push($(this).val());
				}
			});
			$('.bkx-custom-hide-item input').each(function() {
				settings.hide_menu_items.push($(this).val());
			});

			return settings;
		},

		/**
		 * Save settings.
		 */
		saveSettings: function() {
			const $btn = $('.bkx-save-settings');
			const $status = $('.bkx-save-status');
			const settings = this.collectSettings();

			$btn.prop('disabled', true).text(bkxWhiteLabel.i18n.saving);
			$status.text('');

			$.ajax({
				url: bkxWhiteLabel.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_white_label_save_settings',
					nonce: bkxWhiteLabel.nonce,
					settings: settings
				},
				success: function(response) {
					if (response.success) {
						$status.text(bkxWhiteLabel.i18n.saved).css('color', '#00a32a');
					} else {
						$status.text(response.data.message || bkxWhiteLabel.i18n.error).css('color', '#d63638');
					}
				},
				error: function() {
					$status.text(bkxWhiteLabel.i18n.error).css('color', '#d63638');
				},
				complete: function() {
					$btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Save All Settings');
				}
			});
		},

		/**
		 * Export settings.
		 */
		exportSettings: function() {
			$.ajax({
				url: bkxWhiteLabel.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_white_label_export_settings',
					nonce: bkxWhiteLabel.nonce
				},
				success: function(response) {
					if (response.success) {
						const blob = new Blob([JSON.stringify(response.data.data, null, 2)], { type: 'application/json' });
						const url = URL.createObjectURL(blob);
						const a = document.createElement('a');
						a.href = url;
						a.download = response.data.filename;
						document.body.appendChild(a);
						a.click();
						document.body.removeChild(a);
						URL.revokeObjectURL(url);
					}
				}
			});
		},

		/**
		 * Initialize import dropzone.
		 */
		initImportDropzone: function() {
			const $dropzone = $('#bkx-import-dropzone');

			$dropzone.on('dragover dragenter', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$(this).addClass('dragover');
			});

			$dropzone.on('dragleave dragend drop', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$(this).removeClass('dragover');
			});

			$dropzone.on('drop', (e) => {
				const files = e.originalEvent.dataTransfer.files;
				if (files.length) {
					this.processImportFile(files[0]);
				}
			});
		},

		/**
		 * Handle file select.
		 */
		handleFileSelect: function(e) {
			const file = e.target.files[0];
			if (file) {
				this.processImportFile(file);
			}
		},

		/**
		 * Process import file.
		 */
		processImportFile: function(file) {
			if (!file.name.endsWith('.json')) {
				alert('Please select a JSON file.');
				return;
			}

			const reader = new FileReader();
			reader.onload = (e) => {
				try {
					const data = JSON.parse(e.target.result);
					this.importData = data;

					$('#bkx-import-filename').text(file.name);
					$('#bkx-import-version').text(data.version || 'Unknown');
					$('#bkx-import-date').text(data.exported || 'Unknown');
					$('#bkx-import-site').text(data.site_url || 'Unknown');

					$('#bkx-import-dropzone').hide();
					$('#bkx-import-preview').show();
				} catch (err) {
					alert('Invalid JSON file.');
				}
			};
			reader.readAsText(file);
		},

		/**
		 * Confirm import.
		 */
		confirmImport: function() {
			if (!this.importData || !confirm(bkxWhiteLabel.i18n.confirmImport)) {
				return;
			}

			$.ajax({
				url: bkxWhiteLabel.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_white_label_import_settings',
					nonce: bkxWhiteLabel.nonce,
					import_data: JSON.stringify(this.importData)
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Import failed.');
					}
				}
			});
		},

		/**
		 * Cancel import.
		 */
		cancelImport: function() {
			$('#bkx-import-preview').hide();
			$('#bkx-import-dropzone').show();
			$('#bkx-import-file').val('');
		},

		/**
		 * Reset settings.
		 */
		resetSettings: function() {
			if (!confirm(bkxWhiteLabel.i18n.confirmReset)) {
				return;
			}

			$.ajax({
				url: bkxWhiteLabel.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_white_label_reset_settings',
					nonce: bkxWhiteLabel.nonce
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					}
				}
			});
		},

		/**
		 * Preview email.
		 */
		previewEmail: function() {
			$.ajax({
				url: bkxWhiteLabel.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_white_label_preview_email',
					nonce: bkxWhiteLabel.nonce
				},
				success: function(response) {
					if (response.success) {
						const $preview = $('#bkx-email-preview');
						$preview.html('<iframe srcdoc="' + response.data.html.replace(/"/g, '&quot;') + '"></iframe>');
					}
				}
			});
		}
	};

	// Initialize on document ready.
	$(document).ready(function() {
		WhiteLabel.init();
	});

})(jQuery);
