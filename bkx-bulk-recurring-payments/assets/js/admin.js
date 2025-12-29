/**
 * Bulk & Recurring Payments Admin JavaScript.
 *
 * @package BookingX\BulkRecurringPayments
 * @since   1.0.0
 */

/* global jQuery, bkxBulkRecurring */

(function ($) {
	'use strict';

	const BkxPaymentsAdmin = {
		/**
		 * Initialize.
		 */
		init: function () {
			this.bindEvents();
			this.loadInitialData();
		},

		/**
		 * Bind events.
		 */
		bindEvents: function () {
			// Package modal.
			$('#add-package-btn').on('click', this.openPackageModal.bind(this));
			$('#cancel-package-btn, #package-modal .bkx-modal-close').on('click', this.closePackageModal.bind(this));
			$('#package-form').on('submit', this.savePackage.bind(this));
			$('#package-type').on('change', this.togglePackageTypeFields.bind(this));

			// Package table actions.
			$(document).on('click', '.edit-package', this.editPackage.bind(this));
			$(document).on('click', '.delete-package', this.deletePackage.bind(this));

			// Subscription actions.
			$(document).on('click', '.pause-subscription', this.pauseSubscription.bind(this));
			$(document).on('click', '.resume-subscription', this.resumeSubscription.bind(this));
			$(document).on('click', '.cancel-subscription', this.cancelSubscription.bind(this));

			// Invoice actions.
			$(document).on('click', '.generate-invoice', this.generateInvoice.bind(this));

			// Filters.
			$('#package-type-filter').on('change', this.loadPackages.bind(this));
			$('#subscription-status-filter').on('change', this.loadSubscriptions.bind(this));
			$('#bulk-status-filter').on('change', this.loadBulkPurchases.bind(this));

			// Settings form.
			$('#settings-form').on('submit', this.saveSettings.bind(this));

			// Modal close on outside click.
			$('.bkx-modal').on('click', function (e) {
				if ($(e.target).hasClass('bkx-modal')) {
					$(this).hide();
				}
			});
		},

		/**
		 * Load initial data based on active tab.
		 */
		loadInitialData: function () {
			const activeTab = $('.bkx-tab-content.active').attr('id');

			switch (activeTab) {
				case 'packages-tab':
					this.loadPackages();
					break;
				case 'subscriptions-tab':
					this.loadSubscriptions();
					break;
				case 'bulk-tab':
					this.loadBulkPurchases();
					break;
				case 'invoices-tab':
					this.loadInvoiceTemplates();
					break;
			}
		},

		/**
		 * Load packages.
		 */
		loadPackages: function () {
			const type = $('#package-type-filter').val();

			$('#packages-list').html(
				'<tr class="bkx-loading-row"><td colspan="7">' +
					bkxBulkRecurring.i18n.saving.replace('...', '') + '...</td></tr>'
			);

			$.ajax({
				url: bkxBulkRecurring.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_get_packages',
					nonce: bkxBulkRecurring.nonce,
					type: type,
				},
				success: function (response) {
					if (response.success) {
						BkxPaymentsAdmin.renderPackages(response.data.packages);
					}
				},
			});
		},

		/**
		 * Render packages table.
		 *
		 * @param {Array} packages Packages data.
		 */
		renderPackages: function (packages) {
			const $tbody = $('#packages-list');
			$tbody.empty();

			if (!packages || packages.length === 0) {
				$tbody.html(
					'<tr><td colspan="7" class="bkx-empty">' +
						bkxBulkRecurring.i18n.noPackagesFound + '</td></tr>'
				);
				return;
			}

			packages.forEach(function (pkg) {
				const typeLabel = pkg.package_type === 'bulk' ? 'Bulk' : 'Recurring';
				const typeClass = pkg.package_type;

				let priceHtml = '<span class="bkx-price">$' + parseFloat(pkg.price).toFixed(2) + '</span>';
				if (pkg.package_type === 'recurring' && pkg.interval_label) {
					priceHtml += ' / ' + pkg.interval_label;
				}

				let discountHtml = '-';
				if (pkg.discount_amount > 0) {
					if (pkg.discount_type === 'percentage') {
						discountHtml = '<span class="bkx-price-discount">' + pkg.discount_amount + '%</span>';
					} else {
						discountHtml = '<span class="bkx-price-discount">$' + parseFloat(pkg.discount_amount).toFixed(2) + '</span>';
					}
				}

				const row = `
					<tr data-id="${pkg.id}">
						<td>
							<strong>${BkxPaymentsAdmin.escapeHtml(pkg.name)}</strong>
							${pkg.description ? '<br><small>' + BkxPaymentsAdmin.escapeHtml(pkg.description).substring(0, 50) + '</small>' : ''}
						</td>
						<td><span class="bkx-type ${typeClass}">${typeLabel}</span></td>
						<td>${priceHtml}</td>
						<td>${discountHtml}</td>
						<td>${pkg.purchase_count || 0}</td>
						<td><span class="bkx-status ${pkg.status}">${pkg.status}</span></td>
						<td>
							<div class="bkx-actions">
								<button type="button" class="bkx-action-btn edit-package" title="Edit">
									<span class="dashicons dashicons-edit"></span>
								</button>
								<button type="button" class="bkx-action-btn delete delete-package" title="Delete">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
						</td>
					</tr>
				`;

				$tbody.append(row);
			});
		},

		/**
		 * Open package modal.
		 */
		openPackageModal: function () {
			$('#package-modal-title').text('Add Package');
			$('#package-form')[0].reset();
			$('#package-id').val('');
			this.togglePackageTypeFields();
			$('#package-modal').show();
		},

		/**
		 * Close package modal.
		 */
		closePackageModal: function () {
			$('#package-modal').hide();
		},

		/**
		 * Toggle package type specific fields.
		 */
		togglePackageTypeFields: function () {
			const type = $('#package-type').val();

			if (type === 'recurring') {
				$('#recurring-fields').show();
				$('#quantity-field').hide();
			} else {
				$('#recurring-fields').hide();
				$('#quantity-field').show();
			}
		},

		/**
		 * Edit package.
		 *
		 * @param {Event} e Click event.
		 */
		editPackage: function (e) {
			const $row = $(e.currentTarget).closest('tr');
			const id = $row.data('id');

			// Load package data.
			$.ajax({
				url: bkxBulkRecurring.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_get_packages',
					nonce: bkxBulkRecurring.nonce,
				},
				success: function (response) {
					if (response.success) {
						const pkg = response.data.packages.find(p => p.id == id);
						if (pkg) {
							BkxPaymentsAdmin.populatePackageForm(pkg);
							$('#package-modal-title').text('Edit Package');
							$('#package-modal').show();
						}
					}
				},
			});
		},

		/**
		 * Populate package form with data.
		 *
		 * @param {Object} pkg Package data.
		 */
		populatePackageForm: function (pkg) {
			$('#package-id').val(pkg.id);
			$('#package-name').val(pkg.name);
			$('#package-description').val(pkg.description);
			$('#package-type').val(pkg.package_type);
			$('#package-price').val(pkg.price);
			$('#package-quantity').val(pkg.quantity || 1);
			$('#interval-count').val(pkg.interval_count || 1);
			$('#interval-type').val(pkg.interval_type || 'month');
			$('#billing-cycles').val(pkg.billing_cycles || 0);
			$('#trial-days').val(pkg.trial_days || 0);
			$('#setup-fee').val(pkg.setup_fee || 0);
			$('#discount-type').val(pkg.discount_type || 'percentage');
			$('#discount-amount').val(pkg.discount_amount || 0);
			$('#valid-from').val(pkg.valid_from || '');
			$('#valid-until').val(pkg.valid_until || '');
			$('#max-purchases').val(pkg.max_purchases || 0);
			$('#package-status').val(pkg.status);

			this.togglePackageTypeFields();
		},

		/**
		 * Save package.
		 *
		 * @param {Event} e Submit event.
		 */
		savePackage: function (e) {
			e.preventDefault();

			const $form = $('#package-form');
			const $submitBtn = $form.find('button[type="submit"]');
			const originalText = $submitBtn.text();

			$submitBtn.prop('disabled', true).text(bkxBulkRecurring.i18n.saving);

			const data = {
				action: 'bkx_save_package',
				nonce: bkxBulkRecurring.nonce,
				id: $('#package-id').val(),
				name: $('#package-name').val(),
				description: $('#package-description').val(),
				package_type: $('#package-type').val(),
				price: $('#package-price').val(),
				quantity: $('#package-quantity').val(),
				interval_count: $('#interval-count').val(),
				interval_type: $('#interval-type').val(),
				billing_cycles: $('#billing-cycles').val(),
				trial_days: $('#trial-days').val(),
				setup_fee: $('#setup-fee').val(),
				discount_type: $('#discount-type').val(),
				discount_amount: $('#discount-amount').val(),
				valid_from: $('#valid-from').val(),
				valid_until: $('#valid-until').val(),
				max_purchases: $('#max-purchases').val(),
				status: $('#package-status').val(),
			};

			$.ajax({
				url: bkxBulkRecurring.ajaxUrl,
				type: 'POST',
				data: data,
				success: function (response) {
					if (response.success) {
						BkxPaymentsAdmin.closePackageModal();
						BkxPaymentsAdmin.loadPackages();
					} else {
						alert(response.data.message || bkxBulkRecurring.i18n.error);
					}
				},
				error: function () {
					alert(bkxBulkRecurring.i18n.error);
				},
				complete: function () {
					$submitBtn.prop('disabled', false).text(originalText);
				},
			});
		},

		/**
		 * Delete package.
		 *
		 * @param {Event} e Click event.
		 */
		deletePackage: function (e) {
			if (!confirm(bkxBulkRecurring.i18n.confirmDelete)) {
				return;
			}

			const $row = $(e.currentTarget).closest('tr');
			const id = $row.data('id');

			$.ajax({
				url: bkxBulkRecurring.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_delete_package',
					nonce: bkxBulkRecurring.nonce,
					id: id,
				},
				success: function (response) {
					if (response.success) {
						$row.fadeOut(function () {
							$(this).remove();
						});
					} else {
						alert(response.data.message || bkxBulkRecurring.i18n.error);
					}
				},
			});
		},

		/**
		 * Load subscriptions.
		 */
		loadSubscriptions: function () {
			const status = $('#subscription-status-filter').val();

			$('#subscriptions-list').html(
				'<tr class="bkx-loading-row"><td colspan="7">Loading...</td></tr>'
			);

			// Implementation would call backend API.
			// For now, show empty state.
			$('#subscriptions-list').html(
				'<tr><td colspan="7" class="bkx-empty">No subscriptions found.</td></tr>'
			);
		},

		/**
		 * Load bulk purchases.
		 */
		loadBulkPurchases: function () {
			const status = $('#bulk-status-filter').val();

			$('#bulk-list').html(
				'<tr class="bkx-loading-row"><td colspan="7">Loading...</td></tr>'
			);

			// Implementation would call backend API.
			$('#bulk-list').html(
				'<tr><td colspan="7" class="bkx-empty">No bulk purchases found.</td></tr>'
			);
		},

		/**
		 * Load invoice templates.
		 */
		loadInvoiceTemplates: function () {
			$('#templates-list').html(
				'<tr class="bkx-loading-row"><td colspan="5">Loading...</td></tr>'
			);

			// Implementation would call backend API.
			$('#templates-list').html(
				'<tr><td colspan="5" class="bkx-empty">No invoice templates. Using defaults.</td></tr>'
			);
		},

		/**
		 * Pause subscription.
		 *
		 * @param {Event} e Click event.
		 */
		pauseSubscription: function (e) {
			const $row = $(e.currentTarget).closest('tr');
			const id = $row.data('id');

			$.ajax({
				url: bkxBulkRecurring.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_pause_subscription',
					nonce: bkxBulkRecurring.nonce,
					subscription_id: id,
				},
				success: function (response) {
					if (response.success) {
						BkxPaymentsAdmin.loadSubscriptions();
					} else {
						alert(response.data.message || bkxBulkRecurring.i18n.error);
					}
				},
			});
		},

		/**
		 * Resume subscription.
		 *
		 * @param {Event} e Click event.
		 */
		resumeSubscription: function (e) {
			const $row = $(e.currentTarget).closest('tr');
			const id = $row.data('id');

			$.ajax({
				url: bkxBulkRecurring.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_resume_subscription',
					nonce: bkxBulkRecurring.nonce,
					subscription_id: id,
				},
				success: function (response) {
					if (response.success) {
						BkxPaymentsAdmin.loadSubscriptions();
					} else {
						alert(response.data.message || bkxBulkRecurring.i18n.error);
					}
				},
			});
		},

		/**
		 * Cancel subscription.
		 *
		 * @param {Event} e Click event.
		 */
		cancelSubscription: function (e) {
			if (!confirm(bkxBulkRecurring.i18n.confirmCancel)) {
				return;
			}

			const $row = $(e.currentTarget).closest('tr');
			const id = $row.data('id');

			$.ajax({
				url: bkxBulkRecurring.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_cancel_subscription',
					nonce: bkxBulkRecurring.nonce,
					subscription_id: id,
					reason: '',
				},
				success: function (response) {
					if (response.success) {
						BkxPaymentsAdmin.loadSubscriptions();
					} else {
						alert(response.data.message || bkxBulkRecurring.i18n.error);
					}
				},
			});
		},

		/**
		 * Generate invoice.
		 *
		 * @param {Event} e Click event.
		 */
		generateInvoice: function (e) {
			const $row = $(e.currentTarget).closest('tr');
			const id = $row.data('id');
			const type = $row.data('type');

			$.ajax({
				url: bkxBulkRecurring.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_generate_invoice',
					nonce: bkxBulkRecurring.nonce,
					type: type,
					id: id,
				},
				success: function (response) {
					if (response.success && response.data.invoice_url) {
						window.open(response.data.invoice_url, '_blank');
					} else {
						alert(response.data.message || bkxBulkRecurring.i18n.error);
					}
				},
			});
		},

		/**
		 * Save settings.
		 *
		 * @param {Event} e Submit event.
		 */
		saveSettings: function (e) {
			e.preventDefault();

			const $form = $('#settings-form');
			const $submitBtn = $form.find('button[type="submit"]');
			const originalText = $submitBtn.text();

			$submitBtn.prop('disabled', true).text(bkxBulkRecurring.i18n.saving);

			const formData = $form.serializeArray();
			const data = {
				action: 'bkx_save_settings',
				nonce: bkxBulkRecurring.nonce,
			};

			formData.forEach(function (item) {
				data[item.name] = item.value;
			});

			// Handle checkboxes.
			$form.find('input[type="checkbox"]').each(function () {
				if (!this.checked) {
					data[this.name] = '';
				}
			});

			$.ajax({
				url: bkxBulkRecurring.ajaxUrl,
				type: 'POST',
				data: data,
				success: function (response) {
					if (response.success) {
						$submitBtn.text(bkxBulkRecurring.i18n.saved);
						setTimeout(function () {
							$submitBtn.text(originalText);
						}, 2000);
					} else {
						alert(response.data.message || bkxBulkRecurring.i18n.error);
					}
				},
				error: function () {
					alert(bkxBulkRecurring.i18n.error);
				},
				complete: function () {
					$submitBtn.prop('disabled', false);
				},
			});
		},

		/**
		 * Escape HTML.
		 *
		 * @param {string} str String to escape.
		 * @return {string} Escaped string.
		 */
		escapeHtml: function (str) {
			if (!str) {
				return '';
			}
			const div = document.createElement('div');
			div.textContent = str;
			return div.innerHTML;
		},
	};

	// Initialize on document ready.
	$(document).ready(function () {
		BkxPaymentsAdmin.init();
	});
})(jQuery);
