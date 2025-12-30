/**
 * Multi-Tenant Admin JavaScript.
 *
 * @package BookingX\MultiTenant
 */

(function($) {
	'use strict';

	/**
	 * Multi-Tenant Admin object.
	 */
	var BkxMultiTenant = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.initCharts();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function() {
			// Tab navigation.
			$(document).on('click', '.nav-tab', this.handleTabClick);

			// Media uploader.
			$(document).on('click', '.bkx-upload-btn', this.handleMediaUpload);

			// Confirm dialogs.
			$(document).on('click', '[data-confirm]', this.handleConfirm);

			// Plan slug auto-generation.
			$('#plan_name').on('blur', this.autoGenerateSlug);

			// Color picker initialization.
			if ($.fn.wpColorPicker) {
				$('.bkx-color-picker').wpColorPicker();
			}

			// Tenant search.
			$('#bkx-tenant-search').on('keyup', this.handleTenantSearch);

			// Bulk actions.
			$('#bkx-bulk-action').on('change', this.handleBulkAction);
		},

		/**
		 * Handle tab click.
		 *
		 * @param {Event} e Click event.
		 */
		handleTabClick: function(e) {
			e.preventDefault();
			var $tab = $(this);
			var target = $tab.attr('href');

			$('.nav-tab').removeClass('nav-tab-active');
			$('.bkx-tab-content').removeClass('bkx-tab-active');

			$tab.addClass('nav-tab-active');
			$(target).addClass('bkx-tab-active');

			// Update URL hash.
			if (history.pushState) {
				history.pushState(null, null, target);
			}
		},

		/**
		 * Handle media upload.
		 *
		 * @param {Event} e Click event.
		 */
		handleMediaUpload: function(e) {
			e.preventDefault();
			var $btn = $(this);
			var targetId = $btn.data('target');
			var $input = $('#' + targetId);

			var frame = wp.media({
				title: bkxMultiTenant.i18n.selectImage || 'Select Image',
				button: {
					text: bkxMultiTenant.i18n.useImage || 'Use Image'
				},
				multiple: false
			});

			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				$input.val(attachment.url);

				// Update preview if exists.
				var $preview = $btn.siblings('.bkx-preview');
				if ($preview.length) {
					$preview.find('img').attr('src', attachment.url);
				} else {
					$btn.after('<div class="bkx-preview"><img src="' + attachment.url + '" style="max-height: 50px;"></div>');
				}
			});

			frame.open();
		},

		/**
		 * Handle confirmation dialogs.
		 *
		 * @param {Event} e Click event.
		 * @return {boolean} Whether to proceed.
		 */
		handleConfirm: function(e) {
			var message = $(this).data('confirm');
			if (!confirm(message)) {
				e.preventDefault();
				return false;
			}
			return true;
		},

		/**
		 * Auto-generate plan slug from name.
		 */
		autoGenerateSlug: function() {
			var $slug = $('#plan_slug');
			if ($slug.val() === '') {
				var name = $(this).val();
				var slug = name.toLowerCase()
					.replace(/[^a-z0-9]+/g, '-')
					.replace(/^-|-$/g, '');
				$slug.val(slug);
			}
		},

		/**
		 * Handle tenant search.
		 */
		handleTenantSearch: function() {
			var query = $(this).val().toLowerCase();
			$('.bkx-tenant-list tbody tr').each(function() {
				var $row = $(this);
				var name = $row.find('td:first').text().toLowerCase();
				$row.toggle(name.indexOf(query) !== -1);
			});
		},

		/**
		 * Handle bulk action.
		 */
		handleBulkAction: function() {
			var action = $(this).val();
			if (!action) return;

			var selected = [];
			$('.bkx-tenant-checkbox:checked').each(function() {
				selected.push($(this).val());
			});

			if (selected.length === 0) {
				alert(bkxMultiTenant.i18n.selectTenants || 'Please select at least one tenant.');
				$(this).val('');
				return;
			}

			if (!confirm(bkxMultiTenant.i18n.confirmBulk || 'Are you sure?')) {
				$(this).val('');
				return;
			}

			// Submit bulk action.
			$('#bkx-bulk-tenants').val(selected.join(','));
			$('#bkx-bulk-form').submit();
		},

		/**
		 * Initialize charts.
		 */
		initCharts: function() {
			// Charts are initialized inline in templates using Chart.js.
			// This method can be extended for additional chart functionality.
		},

		/**
		 * Refresh usage data via AJAX.
		 *
		 * @param {number} tenantId Tenant ID.
		 */
		refreshUsage: function(tenantId) {
			$.ajax({
				url: bkxMultiTenant.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_get_tenant_usage',
					tenant_id: tenantId,
					nonce: bkxMultiTenant.nonce
				},
				success: function(response) {
					if (response.success) {
						BkxMultiTenant.updateUsageDisplay(response.data);
					}
				}
			});
		},

		/**
		 * Update usage display.
		 *
		 * @param {Object} usage Usage data.
		 */
		updateUsageDisplay: function(usage) {
			$.each(usage, function(metric, data) {
				var $card = $('.bkx-usage-card[data-metric="' + metric + '"]');
				if ($card.length) {
					$card.find('.bkx-current').text(data.current.toLocaleString());
					if (data.limit > 0) {
						$card.find('.bkx-usage-fill').css('width', Math.min(data.percent, 100) + '%');
						$card.find('.bkx-usage-percent').text(data.percent + '% used');

						$card.removeClass('bkx-warning bkx-danger');
						if (data.percent > 95) {
							$card.addClass('bkx-danger');
						} else if (data.percent > 80) {
							$card.addClass('bkx-warning');
						}
					}
				}
			});
		},

		/**
		 * Export tenant data.
		 *
		 * @param {number} tenantId Tenant ID.
		 * @param {string} format Export format.
		 */
		exportTenantData: function(tenantId, format) {
			window.location.href = bkxMultiTenant.ajaxUrl +
				'?action=bkx_export_tenant&tenant_id=' + tenantId +
				'&format=' + format + '&nonce=' + bkxMultiTenant.nonce;
		},

		/**
		 * Test tenant connection.
		 *
		 * @param {number} tenantId Tenant ID.
		 */
		testConnection: function(tenantId) {
			var $btn = $('[data-test-tenant="' + tenantId + '"]');
			$btn.prop('disabled', true).text(bkxMultiTenant.i18n.testing || 'Testing...');

			$.ajax({
				url: bkxMultiTenant.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_test_tenant_connection',
					tenant_id: tenantId,
					nonce: bkxMultiTenant.nonce
				},
				success: function(response) {
					if (response.success) {
						$btn.text(bkxMultiTenant.i18n.connected || 'Connected').addClass('button-primary');
					} else {
						$btn.text(bkxMultiTenant.i18n.failed || 'Failed').addClass('button-link-delete');
					}
				},
				error: function() {
					$btn.text(bkxMultiTenant.i18n.error || 'Error').addClass('button-link-delete');
				},
				complete: function() {
					setTimeout(function() {
						$btn.prop('disabled', false)
							.text(bkxMultiTenant.i18n.test || 'Test')
							.removeClass('button-primary button-link-delete');
					}, 3000);
				}
			});
		}
	};

	// Initialize on document ready.
	$(document).ready(function() {
		BkxMultiTenant.init();

		// Handle URL hash for tabs.
		if (window.location.hash) {
			var $tab = $('.nav-tab[href="' + window.location.hash + '"]');
			if ($tab.length) {
				$tab.trigger('click');
			}
		}
	});

	// Expose to global scope.
	window.BkxMultiTenant = BkxMultiTenant;

})(jQuery);
