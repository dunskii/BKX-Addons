/**
 * PWA Admin JavaScript
 *
 * @package BookingX\PWA
 */

(function($) {
	'use strict';

	/**
	 * PWA Admin
	 */
	var BkxPwaAdmin = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.initColorPickers();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			// Icon upload
			$('.bkx-upload-icon').on('click', this.handleIconUpload);
			$('.bkx-remove-icon').on('click', this.handleIconRemove);

			// Clear cache
			$('#bkx-clear-cache').on('click', this.handleClearCache);

			// Live preview updates
			$('#theme_color').on('change', this.updatePreview);
			$('#background_color').on('change', this.updatePreview);
			$('#app_short_name').on('input', this.updatePreviewName);
		},

		/**
		 * Initialize color pickers
		 */
		initColorPickers: function() {
			$('.bkx-color-picker').wpColorPicker({
				change: function(event, ui) {
					BkxPwaAdmin.updatePreview();
				}
			});
		},

		/**
		 * Handle icon upload
		 */
		handleIconUpload: function(e) {
			e.preventDefault();

			var $btn = $(this);
			var targetId = $btn.data('target');

			var frame = wp.media({
				title: bkxPwaAdmin.i18n.selectIcon,
				button: {
					text: bkxPwaAdmin.i18n.useThisIcon
				},
				multiple: false,
				library: {
					type: 'image'
				}
			});

			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				$('#' + targetId).val(attachment.url);

				// Update preview
				var $container = $btn.closest('.bkx-icon-upload');
				$container.find('.bkx-icon-preview').remove();
				$container.prepend('<img src="' + attachment.url + '" class="bkx-icon-preview" width="96" height="96">');
				$container.find('.bkx-remove-icon').show();

				// Update phone preview if applicable
				if (targetId === 'icon_192') {
					$('.bkx-preview-icon').attr('src', attachment.url);
					$('.bkx-preview-icon-placeholder').replaceWith('<img src="' + attachment.url + '" class="bkx-preview-icon">');
				}
			});

			frame.open();
		},

		/**
		 * Handle icon remove
		 */
		handleIconRemove: function(e) {
			e.preventDefault();

			var $btn = $(this);
			var targetId = $btn.data('target');

			$('#' + targetId).val('');

			var $container = $btn.closest('.bkx-icon-upload');
			$container.find('.bkx-icon-preview').remove();
			$btn.hide();

			// Update phone preview
			if (targetId === 'icon_192') {
				$('.bkx-preview-icon').replaceWith('<div class="bkx-preview-icon-placeholder"></div>');
			}
		},

		/**
		 * Handle clear cache
		 */
		handleClearCache: function(e) {
			e.preventDefault();

			var $btn = $(this);
			var $status = $('.bkx-cache-status');

			$btn.prop('disabled', true);
			$status.html('<span class="spinner is-active" style="float:none;"></span>');

			$.ajax({
				url: bkxPwaAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_pwa_clear_cache',
					nonce: bkxPwaAdmin.nonce
				},
				success: function(response) {
					if (response.success) {
						$status.html('<span style="color:#10b981;">&#10003; Cache cleared</span>');
					} else {
						$status.html('<span style="color:#ef4444;">&#10007; Error</span>');
					}
				},
				error: function() {
					$status.html('<span style="color:#ef4444;">&#10007; Connection error</span>');
				},
				complete: function() {
					$btn.prop('disabled', false);
					setTimeout(function() {
						$status.html('');
					}, 3000);
				}
			});
		},

		/**
		 * Update phone preview
		 */
		updatePreview: function() {
			var themeColor = $('#theme_color').val() || $('#theme_color').wpColorPicker('color');
			var bgColor = $('#background_color').val() || $('#background_color').wpColorPicker('color');

			$('.bkx-phone-status-bar').css('background-color', themeColor);
			$('.bkx-phone-screen').css('background-color', bgColor);
			$('.bkx-install-prompt-demo').css('background-color', themeColor);
		},

		/**
		 * Update preview name
		 */
		updatePreviewName: function() {
			var name = $(this).val() || 'App';
			$('.bkx-preview-name').text(name);
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function() {
		BkxPwaAdmin.init();
	});

})(jQuery);
