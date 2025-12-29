/**
 * CRM Enhanced Admin JavaScript
 *
 * @package BookingX\CRM
 */

(function($) {
	'use strict';

	var BkxCRMAdmin = {
		conditionIndex: 0,

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
			// Settings form.
			$('#bkx-crm-settings-form').on('submit', this.saveSettings.bind(this));

			// Customer actions.
			$(document).on('click', '.bkx-view-customer', this.viewCustomer.bind(this));
			$(document).on('click', '#bkx-add-customer', this.addCustomer.bind(this));

			// Tag actions.
			$('#bkx-add-tag-form').on('submit', this.addTag.bind(this));
			$(document).on('click', '.bkx-delete-tag', this.deleteTag.bind(this));

			// Segment actions.
			$('#bkx-segment-form').on('submit', this.saveSegment.bind(this));
			$('#bkx-add-condition').on('click', this.addCondition.bind(this));
			$(document).on('click', '.bkx-remove-condition', this.removeCondition.bind(this));
			$('#bkx-preview-segment').on('click', this.previewSegment.bind(this));
			$(document).on('click', '.bkx-delete-segment', this.deleteSegment.bind(this));

			// Followup actions.
			$(document).on('click', '.bkx-cancel-followup', this.cancelFollowup.bind(this));

			// Modal actions.
			$(document).on('click', '.bkx-modal-close', this.closeModal.bind(this));
			$(document).on('click', '.bkx-modal', function(e) {
				if ($(e.target).hasClass('bkx-modal')) {
					BkxCRMAdmin.closeModal();
				}
			});

			// Export.
			$('#bkx-export-customers').on('click', this.exportCustomers.bind(this));
		},

		/**
		 * Save settings.
		 *
		 * @param {Event} e
		 */
		saveSettings: function(e) {
			e.preventDefault();

			var $form = $(e.target);
			var $button = $form.find('[type="submit"]');

			$button.prop('disabled', true).addClass('updating-message');

			$.ajax({
				url: bkxCRM.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=bkx_crm_save_settings&nonce=' + bkxCRM.nonce,
				success: function(response) {
					if (response.success) {
						BkxCRMAdmin.showToast(bkxCRM.i18n.saved, 'success');
					} else {
						BkxCRMAdmin.showToast(response.data.message || bkxCRM.i18n.error, 'error');
					}
				},
				error: function() {
					BkxCRMAdmin.showToast(bkxCRM.i18n.error, 'error');
				},
				complete: function() {
					$button.prop('disabled', false).removeClass('updating-message');
				}
			});
		},

		/**
		 * View customer profile.
		 *
		 * @param {Event} e
		 */
		viewCustomer: function(e) {
			e.preventDefault();

			var customerId = $(e.target).closest('[data-customer-id]').data('customer-id');

			$('#bkx-customer-modal').show();
			$('#bkx-customer-modal-content').html('<p>' + bkxCRM.i18n.loading + '</p>');

			$.ajax({
				url: bkxCRM.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_crm_get_customer',
					nonce: bkxCRM.nonce,
					customer_id: customerId
				},
				success: function(response) {
					if (response.success) {
						BkxCRMAdmin.renderCustomerProfile(response.data);
					} else {
						$('#bkx-customer-modal-content').html('<p>' + (response.data.message || bkxCRM.i18n.error) + '</p>');
					}
				},
				error: function() {
					$('#bkx-customer-modal-content').html('<p>' + bkxCRM.i18n.error + '</p>');
				}
			});
		},

		/**
		 * Render customer profile in modal.
		 *
		 * @param {object} customer Customer data.
		 */
		renderCustomerProfile: function(customer) {
			var html = '<div class="bkx-customer-profile">';

			// Main section.
			html += '<div class="bkx-customer-main">';
			html += '<h3>' + (customer.first_name || '') + ' ' + (customer.last_name || '') + '</h3>';
			html += '<p>' + customer.email + '</p>';

			if (customer.phone) {
				html += '<p>' + customer.phone + '</p>';
			}

			// Tags.
			if (customer.tags && customer.tags.length > 0) {
				html += '<div class="bkx-crm-tags">';
				customer.tags.forEach(function(tag) {
					html += '<span class="bkx-crm-tag" style="background-color: ' + tag.color + ';">' + tag.name + '</span>';
				});
				html += '</div>';
			}

			// Notes section.
			html += '<h4>Notes</h4>';
			if (customer.notes && customer.notes.length > 0) {
				html += '<ul class="bkx-notes-list">';
				customer.notes.forEach(function(note) {
					html += '<li class="bkx-note-item">';
					html += '<div class="bkx-note-header">';
					html += '<span>' + note.author_name + '</span>';
					html += '<span>' + note.created_at + '</span>';
					html += '</div>';
					html += '<div class="bkx-note-content">' + note.content + '</div>';
					html += '</li>';
				});
				html += '</ul>';
			} else {
				html += '<p class="bkx-muted">No notes yet.</p>';
			}

			html += '</div>';

			// Sidebar.
			html += '<div class="bkx-customer-sidebar">';
			html += '<p><strong>Bookings:</strong> ' + customer.total_bookings + '</p>';
			html += '<p><strong>Lifetime Value:</strong> $' + parseFloat(customer.lifetime_value).toFixed(2) + '</p>';
			html += '<p><strong>Status:</strong> ' + customer.status + '</p>';
			html += '<p><strong>Customer Since:</strong> ' + customer.customer_since + '</p>';

			// Activity.
			html += '<h4>Recent Activity</h4>';
			if (customer.activity && customer.activity.length > 0) {
				html += '<ul class="bkx-activity-timeline">';
				customer.activity.forEach(function(activity) {
					html += '<li class="bkx-activity-item">';
					html += '<div class="bkx-activity-icon"><span class="dashicons dashicons-' + activity.icon + '"></span></div>';
					html += '<div class="bkx-activity-content">';
					html += '<p>' + activity.description + '</p>';
					html += '<span class="bkx-activity-time">' + activity.created_at + '</span>';
					html += '</div>';
					html += '</li>';
				});
				html += '</ul>';
			} else {
				html += '<p class="bkx-muted">No activity yet.</p>';
			}

			html += '</div>';
			html += '</div>';

			$('#bkx-customer-modal-content').html(html);
		},

		/**
		 * Add new customer.
		 */
		addCustomer: function() {
			// Show add customer form in modal.
			var html = '<h3>Add New Customer</h3>';
			html += '<form id="bkx-add-customer-form">';
			html += '<table class="form-table">';
			html += '<tr><th><label>Email *</label></th><td><input type="email" name="email" required class="regular-text"></td></tr>';
			html += '<tr><th><label>First Name</label></th><td><input type="text" name="first_name" class="regular-text"></td></tr>';
			html += '<tr><th><label>Last Name</label></th><td><input type="text" name="last_name" class="regular-text"></td></tr>';
			html += '<tr><th><label>Phone</label></th><td><input type="text" name="phone" class="regular-text"></td></tr>';
			html += '</table>';
			html += '<p class="submit"><button type="submit" class="button button-primary">Add Customer</button></p>';
			html += '</form>';

			$('#bkx-customer-modal-content').html(html);
			$('#bkx-customer-modal').show();

			$('#bkx-add-customer-form').on('submit', function(e) {
				e.preventDefault();

				var $form = $(this);
				var $button = $form.find('[type="submit"]');

				$button.prop('disabled', true);

				$.ajax({
					url: bkxCRM.ajaxUrl,
					type: 'POST',
					data: $form.serialize() + '&action=bkx_crm_save_customer&nonce=' + bkxCRM.nonce,
					success: function(response) {
						if (response.success) {
							BkxCRMAdmin.showToast('Customer added!', 'success');
							location.reload();
						} else {
							BkxCRMAdmin.showToast(response.data.message || bkxCRM.i18n.error, 'error');
							$button.prop('disabled', false);
						}
					},
					error: function() {
						BkxCRMAdmin.showToast(bkxCRM.i18n.error, 'error');
						$button.prop('disabled', false);
					}
				});
			});
		},

		/**
		 * Add tag.
		 *
		 * @param {Event} e
		 */
		addTag: function(e) {
			e.preventDefault();

			var $form = $(e.target);
			var $button = $form.find('[type="submit"]');

			$button.prop('disabled', true);

			$.ajax({
				url: bkxCRM.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=bkx_crm_add_tag&nonce=' + bkxCRM.nonce,
				success: function(response) {
					if (response.success) {
						BkxCRMAdmin.showToast('Tag created!', 'success');
						location.reload();
					} else {
						BkxCRMAdmin.showToast(response.data.message || bkxCRM.i18n.error, 'error');
					}
				},
				error: function() {
					BkxCRMAdmin.showToast(bkxCRM.i18n.error, 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Delete tag.
		 *
		 * @param {Event} e
		 */
		deleteTag: function(e) {
			var tagId = $(e.target).data('tag-id');

			if (!confirm(bkxCRM.i18n.confirmDelete)) {
				return;
			}

			$.ajax({
				url: bkxCRM.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_crm_delete_tag',
					nonce: bkxCRM.nonce,
					tag_id: tagId
				},
				success: function(response) {
					if (response.success) {
						BkxCRMAdmin.showToast('Tag deleted!', 'success');
						$('[data-tag-id="' + tagId + '"]').fadeOut(300, function() {
							$(this).remove();
						});
					} else {
						BkxCRMAdmin.showToast(response.data.message || bkxCRM.i18n.error, 'error');
					}
				}
			});
		},

		/**
		 * Add condition to segment builder.
		 */
		addCondition: function() {
			var template = $('#bkx-condition-template').html();
			var html = template.replace(/\{\{index\}\}/g, this.conditionIndex++);

			$('.bkx-conditions-list').append(html);
		},

		/**
		 * Remove condition.
		 *
		 * @param {Event} e
		 */
		removeCondition: function(e) {
			$(e.target).closest('.bkx-condition-row').remove();
		},

		/**
		 * Preview segment.
		 */
		previewSegment: function() {
			var conditions = this.getConditions();

			$('#bkx-segment-preview-results').html('<p>' + bkxCRM.i18n.loading + '</p>');

			$.ajax({
				url: bkxCRM.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_crm_preview_segment',
					nonce: bkxCRM.nonce,
					conditions: JSON.stringify(conditions)
				},
				success: function(response) {
					if (response.success) {
						var html = '<p><strong>' + response.data.count + '</strong> customers match these conditions.</p>';

						if (response.data.customers && response.data.customers.length > 0) {
							html += '<ul>';
							response.data.customers.forEach(function(customer) {
								html += '<li>' + customer.email + '</li>';
							});
							html += '</ul>';
						}

						$('#bkx-segment-preview-results').html(html);
					}
				}
			});
		},

		/**
		 * Get conditions from form.
		 *
		 * @return {array}
		 */
		getConditions: function() {
			var conditions = [];

			$('.bkx-condition-row').each(function() {
				var $row = $(this);
				var field = $row.find('.bkx-condition-field').val();
				var operator = $row.find('.bkx-condition-operator').val();
				var value = $row.find('.bkx-condition-value').val();

				if (field) {
					conditions.push({
						field: field,
						operator: operator,
						value: value
					});
				}
			});

			return conditions;
		},

		/**
		 * Save segment.
		 *
		 * @param {Event} e
		 */
		saveSegment: function(e) {
			e.preventDefault();

			var $form = $(e.target);
			var $button = $form.find('[type="submit"]');
			var conditions = this.getConditions();

			$button.prop('disabled', true);

			$.ajax({
				url: bkxCRM.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_crm_save_segment',
					nonce: bkxCRM.nonce,
					segment_id: $form.find('[name="segment_id"]').val(),
					name: $form.find('[name="name"]').val(),
					description: $form.find('[name="description"]').val(),
					conditions: JSON.stringify(conditions),
					is_dynamic: $form.find('[name="is_dynamic"]').is(':checked') ? 1 : 0
				},
				success: function(response) {
					if (response.success) {
						BkxCRMAdmin.showToast('Segment saved!', 'success');
						location.reload();
					} else {
						BkxCRMAdmin.showToast(response.data.message || bkxCRM.i18n.error, 'error');
					}
				},
				error: function() {
					BkxCRMAdmin.showToast(bkxCRM.i18n.error, 'error');
				},
				complete: function() {
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Delete segment.
		 *
		 * @param {Event} e
		 */
		deleteSegment: function(e) {
			var segmentId = $(e.target).data('segment-id');

			if (!confirm(bkxCRM.i18n.confirmDelete)) {
				return;
			}

			$.ajax({
				url: bkxCRM.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_crm_delete_segment',
					nonce: bkxCRM.nonce,
					segment_id: segmentId
				},
				success: function(response) {
					if (response.success) {
						BkxCRMAdmin.showToast('Segment deleted!', 'success');
						$('[data-segment-id="' + segmentId + '"]').fadeOut(300, function() {
							$(this).remove();
						});
					}
				}
			});
		},

		/**
		 * Cancel followup.
		 *
		 * @param {Event} e
		 */
		cancelFollowup: function(e) {
			var followupId = $(e.target).data('followup-id');

			if (!confirm('Cancel this follow-up?')) {
				return;
			}

			$.ajax({
				url: bkxCRM.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_crm_cancel_followup',
					nonce: bkxCRM.nonce,
					followup_id: followupId
				},
				success: function(response) {
					if (response.success) {
						BkxCRMAdmin.showToast('Follow-up cancelled!', 'success');
						$('[data-followup-id="' + followupId + '"]').fadeOut(300, function() {
							$(this).remove();
						});
					}
				}
			});
		},

		/**
		 * Export customers.
		 */
		exportCustomers: function() {
			var params = new URLSearchParams(window.location.search);

			$.ajax({
				url: bkxCRM.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_crm_export_customers',
					nonce: bkxCRM.nonce,
					format: 'csv',
					segment_id: params.get('segment') || '',
					tag_id: params.get('tag') || ''
				},
				success: function() {
					BkxCRMAdmin.showToast('Export started. Download will begin shortly.', 'success');
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
		 *
		 * @param {string} message
		 * @param {string} type
		 */
		showToast: function(message, type) {
			var $toast = $('<div class="bkx-toast ' + type + '">' + message + '</div>');
			$('body').append($toast);

			setTimeout(function() {
				$toast.fadeOut(300, function() {
					$(this).remove();
				});
			}, 3000);
		}
	};

	// Initialize on document ready.
	$(document).ready(function() {
		BkxCRMAdmin.init();
	});

})(jQuery);
