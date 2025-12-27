/**
 * Staff Breaks Admin JavaScript
 *
 * @package BookingX\StaffBreaks
 * @since   1.0.0
 */

(function($) {
	'use strict';

	var BkxStaffBreaks = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			// Break modal
			$('#bkx-add-break').on('click', this.openBreakModal);
			$(document).on('click', '.bkx-edit-break', this.editBreak);
			$(document).on('click', '.bkx-delete-break', this.deleteBreak);
			$('#bkx-break-form').on('submit', this.saveBreak);

			// Time off modal
			$('#bkx-add-timeoff').on('click', this.openTimeoffModal);
			$(document).on('click', '.bkx-edit-timeoff', this.editTimeoff);
			$(document).on('click', '.bkx-delete-timeoff', this.deleteTimeoff);
			$('#bkx-timeoff-form').on('submit', this.saveTimeoff);

			// Approval actions
			$(document).on('click', '.bkx-approve-timeoff', this.approveTimeoff);
			$(document).on('click', '.bkx-reject-timeoff', this.rejectTimeoff);

			// Modal close
			$(document).on('click', '.bkx-modal-close', this.closeModal);
			$(document).on('click', '.bkx-modal', function(e) {
				if (e.target === this) {
					BkxStaffBreaks.closeModal();
				}
			});

			// All day toggle
			$('#timeoff_all_day').on('change', this.toggleTimeFields);
		},

		openBreakModal: function(e) {
			e.preventDefault();
			$('#break_id').val(0);
			$('#bkx-break-form')[0].reset();
			$('#bkx-break-modal').show();
		},

		editBreak: function(e) {
			e.preventDefault();
			var $row = $(this).closest('tr');
			var breakId = $(this).data('id');

			// Load break data (simplified - in production would fetch via AJAX)
			$('#break_id').val(breakId);
			$('#bkx-break-modal').show();
		},

		deleteBreak: function(e) {
			e.preventDefault();
			var breakId = $(this).data('id');
			var $row = $(this).closest('tr');

			if (!confirm(bkxStaffBreaks.i18n.confirmDelete)) {
				return;
			}

			$.ajax({
				url: bkxStaffBreaks.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_delete_break',
					nonce: bkxStaffBreaks.nonce,
					break_id: breakId
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(function() {
							$(this).remove();
							if ($('.bkx-breaks-table tbody tr').length === 0) {
								$('.bkx-breaks-table').replaceWith('<p class="bkx-no-breaks">' + 'No breaks configured.' + '</p>');
							}
						});
					} else {
						alert(response.data.message || bkxStaffBreaks.i18n.error);
					}
				},
				error: function() {
					alert(bkxStaffBreaks.i18n.error);
				}
			});
		},

		saveBreak: function(e) {
			e.preventDefault();
			var $form = $(this);
			var $submit = $form.find('[type="submit"]');

			$submit.prop('disabled', true).text(bkxStaffBreaks.i18n.saving);

			$.ajax({
				url: bkxStaffBreaks.ajaxUrl,
				method: 'POST',
				data: $.extend({}, $form.serializeObject(), {
					action: 'bkx_save_break',
					nonce: bkxStaffBreaks.nonce
				}),
				success: function(response) {
					if (response.success) {
						BkxStaffBreaks.closeModal();
						location.reload(); // Reload to show updated list
					} else {
						alert(response.data.message || bkxStaffBreaks.i18n.error);
					}
				},
				error: function() {
					alert(bkxStaffBreaks.i18n.error);
				},
				complete: function() {
					$submit.prop('disabled', false).text('Save Break');
				}
			});
		},

		openTimeoffModal: function(e) {
			e.preventDefault();
			$('#timeoff_entry_id').val(0);
			$('#bkx-timeoff-form')[0].reset();
			$('#timeoff_all_day').prop('checked', true);
			$('.bkx-time-row').hide();
			$('#bkx-timeoff-modal').show();
		},

		editTimeoff: function(e) {
			e.preventDefault();
			var entryId = $(this).data('id');
			// Would fetch entry data via AJAX in production
			$('#timeoff_entry_id').val(entryId);
			$('#bkx-timeoff-modal').show();
		},

		deleteTimeoff: function(e) {
			e.preventDefault();
			var entryId = $(this).data('id');
			var $row = $(this).closest('tr');

			if (!confirm(bkxStaffBreaks.i18n.confirmDelete)) {
				return;
			}

			$.ajax({
				url: bkxStaffBreaks.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_delete_timeoff',
					nonce: bkxStaffBreaks.nonce,
					entry_id: entryId
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(function() {
							$(this).remove();
						});
					} else {
						alert(response.data.message || bkxStaffBreaks.i18n.error);
					}
				},
				error: function() {
					alert(bkxStaffBreaks.i18n.error);
				}
			});
		},

		saveTimeoff: function(e) {
			e.preventDefault();
			var $form = $(this);
			var $submit = $form.find('[type="submit"]');

			$submit.prop('disabled', true).text(bkxStaffBreaks.i18n.saving);

			$.ajax({
				url: bkxStaffBreaks.ajaxUrl,
				method: 'POST',
				data: $.extend({}, $form.serializeObject(), {
					action: 'bkx_save_timeoff',
					nonce: bkxStaffBreaks.nonce,
					all_day: $('#timeoff_all_day').is(':checked') ? 1 : 0
				}),
				success: function(response) {
					if (response.success) {
						BkxStaffBreaks.closeModal();
						location.reload();
					} else {
						alert(response.data.message || bkxStaffBreaks.i18n.error);
					}
				},
				error: function() {
					alert(bkxStaffBreaks.i18n.error);
				},
				complete: function() {
					$submit.prop('disabled', false).text('Save');
				}
			});
		},

		approveTimeoff: function(e) {
			e.preventDefault();
			var entryId = $(this).data('id');
			var $row = $(this).closest('tr');

			$.ajax({
				url: bkxStaffBreaks.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_approve_timeoff',
					nonce: bkxStaffBreaks.nonce,
					entry_id: entryId,
					approval_action: 'approve'
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(function() {
							$(this).remove();
						});
					} else {
						alert(response.data.message || bkxStaffBreaks.i18n.error);
					}
				},
				error: function() {
					alert(bkxStaffBreaks.i18n.error);
				}
			});
		},

		rejectTimeoff: function(e) {
			e.preventDefault();
			var entryId = $(this).data('id');
			var $row = $(this).closest('tr');

			if (!confirm('Are you sure you want to reject this request?')) {
				return;
			}

			$.ajax({
				url: bkxStaffBreaks.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_approve_timeoff',
					nonce: bkxStaffBreaks.nonce,
					entry_id: entryId,
					approval_action: 'reject'
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(function() {
							$(this).remove();
						});
					} else {
						alert(response.data.message || bkxStaffBreaks.i18n.error);
					}
				},
				error: function() {
					alert(bkxStaffBreaks.i18n.error);
				}
			});
		},

		toggleTimeFields: function() {
			var isAllDay = $(this).is(':checked');
			$('.bkx-time-row').toggle(!isAllDay);
		},

		closeModal: function() {
			$('.bkx-modal').hide();
		}
	};

	// Helper to serialize form to object
	$.fn.serializeObject = function() {
		var o = {};
		var a = this.serializeArray();
		$.each(a, function() {
			if (o[this.name]) {
				if (!o[this.name].push) {
					o[this.name] = [o[this.name]];
				}
				o[this.name].push(this.value || '');
			} else {
				o[this.name] = this.value || '';
			}
		});
		return o;
	};

	$(document).ready(function() {
		BkxStaffBreaks.init();
	});

})(jQuery);
