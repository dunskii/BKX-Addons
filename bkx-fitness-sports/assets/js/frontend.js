/**
 * Fitness & Sports Frontend JavaScript
 *
 * @package BookingX\FitnessSports
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Fitness & Sports Module
     */
    const BkxFitnessSports = {
        /**
         * Initialize the module.
         */
        init: function() {
            this.bindEvents();
            this.initScheduleNavigation();
            this.initEquipmentBooking();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            // Class booking.
            $(document).on('click', '.bkx-book-class-btn', this.handleBookClass.bind(this));

            // Class cancellation.
            $(document).on('click', '.bkx-cancel-class-btn', this.handleCancelClass.bind(this));

            // Waitlist.
            $(document).on('click', '.bkx-join-waitlist-btn', this.handleJoinWaitlist.bind(this));

            // Equipment selection.
            $(document).on('click', '.bkx-select-equipment-btn', this.handleSelectEquipment.bind(this));

            // Equipment booking form.
            $(document).on('submit', '#bkx-equipment-booking-form', this.handleEquipmentBooking.bind(this));

            // Cancel equipment selection.
            $(document).on('click', '.bkx-cancel-btn', this.handleCancelSelection.bind(this));

            // Check equipment availability on time change.
            $(document).on('change', '#bkx-booking-date, #bkx-start-time, #bkx-duration', this.checkEquipmentAvailability.bind(this));
        },

        /**
         * Initialize schedule navigation.
         */
        initScheduleNavigation: function() {
            const $schedule = $('.bkx-class-schedule');

            if (!$schedule.length) return;

            let currentWeekStart = new Date();
            currentWeekStart.setDate(currentWeekStart.getDate() - currentWeekStart.getDay() + 1);

            this.updateScheduleRange(currentWeekStart);

            $('.bkx-schedule-nav.bkx-prev').on('click', function() {
                currentWeekStart.setDate(currentWeekStart.getDate() - 7);
                BkxFitnessSports.loadSchedule(currentWeekStart);
            });

            $('.bkx-schedule-nav.bkx-next').on('click', function() {
                currentWeekStart.setDate(currentWeekStart.getDate() + 7);
                BkxFitnessSports.loadSchedule(currentWeekStart);
            });
        },

        /**
         * Update schedule range display.
         *
         * @param {Date} startDate Week start date.
         */
        updateScheduleRange: function(startDate) {
            const endDate = new Date(startDate);
            endDate.setDate(endDate.getDate() + 6);

            const options = { month: 'short', day: 'numeric' };
            const rangeText = startDate.toLocaleDateString('en-US', options) + ' - ' + endDate.toLocaleDateString('en-US', options);

            $('.bkx-schedule-range').text(rangeText);
        },

        /**
         * Load schedule for a given week.
         *
         * @param {Date} startDate Week start date.
         */
        loadSchedule: function(startDate) {
            const self = this;

            $.ajax({
                url: bkxFitnessSports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_load_class_schedule',
                    nonce: bkxFitnessSports.nonce,
                    start_date: startDate.toISOString().split('T')[0]
                },
                beforeSend: function() {
                    $('.bkx-schedule-body').addClass('loading');
                },
                success: function(response) {
                    if (response.success) {
                        $('.bkx-schedule-body').html(response.data.html);
                        self.updateScheduleRange(startDate);
                    }
                },
                complete: function() {
                    $('.bkx-schedule-body').removeClass('loading');
                }
            });
        },

        /**
         * Handle class booking.
         *
         * @param {Event} e Click event.
         */
        handleBookClass: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $card = $btn.closest('.bkx-class-card');
            const classId = $card.data('class-id');
            const scheduleId = $card.data('schedule-id');

            $btn.prop('disabled', true).text(bkxFitnessSports.i18n.booking || 'Booking...');

            $.ajax({
                url: bkxFitnessSports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_book_class',
                    nonce: bkxFitnessSports.nonce,
                    class_id: classId,
                    schedule_id: scheduleId
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text(bkxFitnessSports.i18n.booked || 'Booked!').addClass('booked');
                        BkxFitnessSports.showNotification('success', response.data.message);
                    } else {
                        BkxFitnessSports.showNotification('error', response.data.message);
                        $btn.prop('disabled', false).text(bkxFitnessSports.i18n.bookClass || 'Book Class');
                    }
                },
                error: function() {
                    BkxFitnessSports.showNotification('error', bkxFitnessSports.i18n.error);
                    $btn.prop('disabled', false).text(bkxFitnessSports.i18n.bookClass || 'Book Class');
                }
            });
        },

        /**
         * Handle class cancellation.
         *
         * @param {Event} e Click event.
         */
        handleCancelClass: function(e) {
            e.preventDefault();

            if (!confirm(bkxFitnessSports.i18n.confirmCancel)) {
                return;
            }

            const $btn = $(e.currentTarget);
            const bookingId = $btn.data('booking-id');

            $btn.prop('disabled', true);

            $.ajax({
                url: bkxFitnessSports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_cancel_class_booking',
                    nonce: bkxFitnessSports.nonce,
                    booking_id: bookingId
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('.bkx-booking-item').fadeOut(300, function() {
                            $(this).remove();
                        });
                        BkxFitnessSports.showNotification('success', response.data.message);
                    } else {
                        BkxFitnessSports.showNotification('error', response.data.message);
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    BkxFitnessSports.showNotification('error', bkxFitnessSports.i18n.error);
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Handle joining waitlist.
         *
         * @param {Event} e Click event.
         */
        handleJoinWaitlist: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const $card = $btn.closest('.bkx-class-card');
            const classId = $card.data('class-id');
            const scheduleId = $btn.data('schedule-id') || $card.data('schedule-id');

            $btn.prop('disabled', true).text(bkxFitnessSports.i18n.joining || 'Joining...');

            $.ajax({
                url: bkxFitnessSports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_join_waitlist',
                    nonce: bkxFitnessSports.nonce,
                    class_id: classId,
                    schedule_id: scheduleId
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text(bkxFitnessSports.i18n.waitlistAdded + ' (#' + response.data.position + ')');
                        BkxFitnessSports.showNotification('success', response.data.message);
                    } else {
                        BkxFitnessSports.showNotification('error', response.data.message);
                        $btn.prop('disabled', false).text(bkxFitnessSports.i18n.joinWaitlist || 'Join Waitlist');
                    }
                },
                error: function() {
                    BkxFitnessSports.showNotification('error', bkxFitnessSports.i18n.error);
                    $btn.prop('disabled', false).text(bkxFitnessSports.i18n.joinWaitlist || 'Join Waitlist');
                }
            });
        },

        /**
         * Initialize equipment booking.
         */
        initEquipmentBooking: function() {
            // Set min date to today.
            const today = new Date().toISOString().split('T')[0];
            $('#bkx-booking-date').attr('min', today).val(today);
        },

        /**
         * Handle equipment selection.
         *
         * @param {Event} e Click event.
         */
        handleSelectEquipment: function(e) {
            e.preventDefault();

            const $btn = $(e.currentTarget);
            const equipmentId = $btn.data('equipment-id');
            const $item = $btn.closest('.bkx-equipment-item');

            // Update hidden input.
            $('#bkx-equipment-id').val(equipmentId);

            // Highlight selected item.
            $('.bkx-equipment-item').removeClass('selected');
            $item.addClass('selected');

            // Show booking form.
            $('.bkx-booking-form').slideDown();

            // Scroll to form.
            $('html, body').animate({
                scrollTop: $('.bkx-booking-form').offset().top - 50
            }, 300);
        },

        /**
         * Handle cancel selection.
         *
         * @param {Event} e Click event.
         */
        handleCancelSelection: function(e) {
            e.preventDefault();

            $('.bkx-booking-form').slideUp();
            $('.bkx-equipment-item').removeClass('selected');
            $('#bkx-equipment-id').val('');
            $('.bkx-availability-status').empty().removeClass('available unavailable');
        },

        /**
         * Check equipment availability.
         */
        checkEquipmentAvailability: function() {
            const equipmentId = $('#bkx-equipment-id').val();
            const date = $('#bkx-booking-date').val();
            const startTime = $('#bkx-start-time').val();
            const duration = $('#bkx-duration').val();

            if (!equipmentId || !date || !startTime || !duration) {
                return;
            }

            const $status = $('.bkx-availability-status');

            $.ajax({
                url: bkxFitnessSports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_check_equipment_availability',
                    nonce: bkxFitnessSports.nonce,
                    equipment_id: equipmentId,
                    date: date,
                    start_time: startTime,
                    duration: duration
                },
                success: function(response) {
                    if (response.success) {
                        if (response.data.available) {
                            $status.removeClass('unavailable').addClass('available')
                                .text(response.data.message);
                        } else {
                            $status.removeClass('available').addClass('unavailable')
                                .text(response.data.message);
                        }
                    }
                }
            });
        },

        /**
         * Handle equipment booking form submission.
         *
         * @param {Event} e Submit event.
         */
        handleEquipmentBooking: function(e) {
            e.preventDefault();

            const $form = $(e.currentTarget);
            const $btn = $form.find('.bkx-book-equipment-btn');

            const equipmentId = $('#bkx-equipment-id').val();
            const date = $('#bkx-booking-date').val();
            const startTime = $('#bkx-start-time').val();
            const duration = $('#bkx-duration').val();

            // Calculate end time.
            const startMinutes = parseInt(startTime.split(':')[0]) * 60 + parseInt(startTime.split(':')[1]);
            const endMinutes = startMinutes + parseInt(duration);
            const endHour = Math.floor(endMinutes / 60).toString().padStart(2, '0');
            const endMin = (endMinutes % 60).toString().padStart(2, '0');
            const endTime = endHour + ':' + endMin;

            $btn.prop('disabled', true).text(bkxFitnessSports.i18n.booking || 'Booking...');

            $.ajax({
                url: bkxFitnessSports.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bkx_book_equipment',
                    nonce: bkxFitnessSports.nonce,
                    equipment_id: equipmentId,
                    start_time: date + ' ' + startTime + ':00',
                    end_time: date + ' ' + endTime + ':00'
                },
                success: function(response) {
                    if (response.success) {
                        BkxFitnessSports.showNotification('success', response.data.message);
                        $form[0].reset();
                        $('.bkx-booking-form').slideUp();
                        $('.bkx-equipment-item').removeClass('selected');
                    } else {
                        BkxFitnessSports.showNotification('error', response.data.message);
                    }
                },
                error: function() {
                    BkxFitnessSports.showNotification('error', bkxFitnessSports.i18n.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text(bkxFitnessSports.i18n.bookEquipment || 'Book Equipment');
                }
            });
        },

        /**
         * Show notification.
         *
         * @param {string} type    Notification type (success, error).
         * @param {string} message Notification message.
         */
        showNotification: function(type, message) {
            const $notification = $('<div class="bkx-notification bkx-notification-' + type + '">' + message + '</div>');

            $('body').append($notification);

            setTimeout(function() {
                $notification.addClass('show');
            }, 10);

            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 3000);
        }
    };

    /**
     * Initialize on document ready.
     */
    $(document).ready(function() {
        BkxFitnessSports.init();
    });

})(jQuery);
