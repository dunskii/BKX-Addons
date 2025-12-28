/**
 * Class Bookings Admin JavaScript
 *
 * @package BookingX\ClassBookings
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKXClassAdmin = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Add schedule.
			$('#bkx-add-schedule-btn').on('click', this.addSchedule);

			// Delete schedule.
			$(document).on('click', '.bkx-delete-schedule', this.deleteSchedule);

			// Generate sessions.
			$('#bkx-generate-sessions-btn').on('click', this.generateSessions);

			// Attendance actions.
			$(document).on('click', '.bkx-check-in', this.checkIn);
			$(document).on('click', '.bkx-mark-absent', this.markAbsent);
		},

		/**
		 * Add a schedule.
		 *
		 * @param {Event} e Click event.
		 */
		addSchedule: function(e) {
			e.preventDefault();

			const $form = $(this).closest('.bkx-add-schedule');
			const postId = $('#post_ID').val();

			const data = {
				action: 'bkx_add_class_schedule',
				nonce: bkxClassAdmin.nonce,
				class_id: postId,
				day_of_week: $form.find('[name="bkx_new_schedule_day"]').val(),
				start_time: $form.find('[name="bkx_new_schedule_start"]').val(),
				end_time: $form.find('[name="bkx_new_schedule_end"]').val(),
				capacity: $form.find('[name="bkx_new_schedule_capacity"]').val()
			};

			$(this).prop('disabled', true).text(bkxClassAdmin.i18n.adding);

			$.post(ajaxurl, data, function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data || bkxClassAdmin.i18n.error);
				}
			}).always(function() {
				$('#bkx-add-schedule-btn').prop('disabled', false).text(bkxClassAdmin.i18n.add);
			});
		},

		/**
		 * Delete a schedule.
		 *
		 * @param {Event} e Click event.
		 */
		deleteSchedule: function(e) {
			e.preventDefault();

			if (!confirm(bkxClassAdmin.i18n.confirmDelete)) {
				return;
			}

			const $btn = $(this);
			const scheduleId = $btn.data('id');

			$btn.prop('disabled', true);

			$.post(ajaxurl, {
				action: 'bkx_delete_class_schedule',
				nonce: bkxClassAdmin.nonce,
				schedule_id: scheduleId
			}, function(response) {
				if (response.success) {
					$btn.closest('tr').fadeOut(300, function() {
						$(this).remove();
						if ($('#bkx-schedule-table tbody tr').length === 0) {
							$('#bkx-schedule-table tbody').html(
								'<tr class="bkx-no-schedules"><td colspan="6">' +
								bkxClassAdmin.i18n.noSchedules + '</td></tr>'
							);
						}
					});
				} else {
					alert(response.data || bkxClassAdmin.i18n.error);
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Generate sessions from schedules.
		 *
		 * @param {Event} e Click event.
		 */
		generateSessions: function(e) {
			e.preventDefault();

			const $section = $(this).closest('.bkx-generate-sessions');
			const postId = $('#post_ID').val();
			const fromDate = $section.find('[name="bkx_generate_from"]').val();
			const toDate = $section.find('[name="bkx_generate_to"]').val();

			if (!fromDate || !toDate) {
				alert(bkxClassAdmin.i18n.selectDates);
				return;
			}

			$(this).prop('disabled', true).text(bkxClassAdmin.i18n.generating);

			$.post(ajaxurl, {
				action: 'bkx_generate_class_sessions',
				nonce: bkxClassAdmin.nonce,
				class_id: postId,
				from_date: fromDate,
				to_date: toDate
			}, function(response) {
				if (response.success) {
					alert(response.data.message);
				} else {
					alert(response.data || bkxClassAdmin.i18n.error);
				}
			}).always(function() {
				$('#bkx-generate-sessions-btn').prop('disabled', false)
					.text(bkxClassAdmin.i18n.generateSessions);
			});
		},

		/**
		 * Check in a participant.
		 *
		 * @param {Event} e Click event.
		 */
		checkIn: function(e) {
			e.preventDefault();

			const $btn = $(this);
			const bookingId = $btn.data('id');
			const $row = $btn.closest('tr');

			$btn.prop('disabled', true);

			$.post(ajaxurl, {
				action: 'bkx_mark_class_attendance',
				nonce: bkxClassAdmin.nonce,
				booking_id: bookingId,
				status: 'present'
			}, function(response) {
				if (response.success) {
					$row.find('.bkx-status')
						.removeClass('bkx-status-registered')
						.addClass('bkx-status-present')
						.text('Present');
					$btn.closest('td').html('<span class="dashicons dashicons-yes-alt" style="color:#2e7d32;"></span>');
				} else {
					alert(response.data || bkxClassAdmin.i18n.error);
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Mark participant as absent.
		 *
		 * @param {Event} e Click event.
		 */
		markAbsent: function(e) {
			e.preventDefault();

			const $btn = $(this);
			const bookingId = $btn.data('id');
			const $row = $btn.closest('tr');

			$btn.prop('disabled', true);

			$.post(ajaxurl, {
				action: 'bkx_mark_class_attendance',
				nonce: bkxClassAdmin.nonce,
				booking_id: bookingId,
				status: 'absent'
			}, function(response) {
				if (response.success) {
					$row.find('.bkx-status')
						.removeClass('bkx-status-registered')
						.addClass('bkx-status-absent')
						.text('Absent');
					$btn.closest('td').html('<span class="dashicons dashicons-no-alt" style="color:#c62828;"></span>');
				} else {
					alert(response.data || bkxClassAdmin.i18n.error);
					$btn.prop('disabled', false);
				}
			});
		}
	};

	$(document).ready(function() {
		BKXClassAdmin.init();
	});

})(jQuery);
