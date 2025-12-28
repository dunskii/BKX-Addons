/**
 * Fitness & Sports Admin JavaScript
 *
 * @package BookingX\FitnessSports
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Fitness & Sports Admin Module
     */
    const BkxFitnessSportsAdmin = {
        /**
         * Initialize the module.
         */
        init: function() {
            this.initScheduleManager();
            this.initTrainerFields();
            this.initConditionalFields();
        },

        /**
         * Initialize schedule manager.
         */
        initScheduleManager: function() {
            const $container = $('#bkx-schedules-body');

            if (!$container.length) return;

            let scheduleIndex = $container.find('tr').length;

            // Add schedule row.
            $('#bkx-add-schedule').on('click', function() {
                const template = $('#bkx-schedule-row-template').html();
                const row = template.replace(/\{\{index\}\}/g, scheduleIndex);
                $container.append(row);
                scheduleIndex++;
            });

            // Remove schedule row.
            $(document).on('click', '.bkx-remove-schedule', function() {
                if (!confirm(bkxFitnessSportsAdmin.i18n.confirmDelete || 'Are you sure?')) {
                    return;
                }
                $(this).closest('tr').fadeOut(200, function() {
                    $(this).remove();
                });
            });

            // Make rows sortable.
            if (typeof $.fn.sortable !== 'undefined') {
                $container.sortable({
                    handle: 'td:first-child',
                    cursor: 'move',
                    placeholder: 'bkx-schedule-placeholder'
                });
            }
        },

        /**
         * Initialize trainer fields toggle.
         */
        initTrainerFields: function() {
            const $checkbox = $('input[name="bkx_is_trainer"]');
            const $fields = $('.bkx-trainer-fields');

            if (!$checkbox.length) return;

            $checkbox.on('change', function() {
                $fields.toggle($(this).is(':checked'));
            });
        },

        /**
         * Initialize conditional field visibility.
         */
        initConditionalFields: function() {
            // Virtual option.
            $('input[name="bkx_virtual_option"]').on('change', function() {
                $('.bkx-virtual-link-row').toggle($(this).is(':checked'));
            });

            // Settings page conditional fields.
            const conditionalSettings = {
                'enable_class_scheduling': ['max_class_size', 'booking_window_days', 'cancellation_hours', 'enable_waitlist'],
                'enable_membership': ['require_membership'],
                'enable_equipment_booking': ['equipment_slot_duration'],
                'enable_performance_tracking': ['enable_trainer_profiles']
            };

            $.each(conditionalSettings, function(parentField, childFields) {
                const $parent = $('input[name="bkx_fitness_sports_settings[' + parentField + ']"]');

                if (!$parent.length) return;

                function toggleFields() {
                    const isChecked = $parent.is(':checked');

                    $.each(childFields, function(i, childField) {
                        const $row = $('input[name="bkx_fitness_sports_settings[' + childField + ']"], select[name="bkx_fitness_sports_settings[' + childField + ']"]')
                            .closest('tr');

                        $row.toggle(isChecked);
                    });
                }

                $parent.on('change', toggleFields);
                toggleFields(); // Initial state.
            });
        }
    };

    /**
     * Initialize on document ready.
     */
    $(document).ready(function() {
        BkxFitnessSportsAdmin.init();
    });

})(jQuery);
