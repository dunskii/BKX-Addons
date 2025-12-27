/**
 * Recurring Bookings Frontend JavaScript
 *
 * @package BookingX\RecurringBookings
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Recurring Bookings Handler
     */
    var BKXRecurring = {
        /**
         * Initialize
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$container = $('.bkx-recurring-options');
            this.$patternSelect = $('#bkx-recurring-pattern');
            this.$config = $('.bkx-recurring-config');
            this.$preview = $('.bkx-recurring-preview');
            this.$previewDescription = $('.bkx-preview-description');
            this.$previewDates = $('.bkx-preview-dates');
            this.$previewMore = $('.bkx-preview-more');
            this.$bookingDateInput = $('input[name="booking_date"]');
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Pattern change
            this.$patternSelect.on('change', function() {
                self.handlePatternChange($(this).val());
            });

            // Day checkboxes
            this.$container.on('change', '.bkx-day-checkbox input', function() {
                self.updatePreview();
            });

            // Monthly type radios
            this.$container.on('change', 'input[name="recurring_options[type]"]', function() {
                self.updatePreview();
            });

            // End type radios
            this.$container.on('change', 'input[name="recurring_end_type"]', function() {
                var type = $(this).val();
                if (type === 'occurrences') {
                    $('input[name="recurring_options[occurrences]"]').prop('disabled', false);
                    $('input[name="recurring_options[end_date]"]').prop('disabled', true);
                } else {
                    $('input[name="recurring_options[occurrences]"]').prop('disabled', true);
                    $('input[name="recurring_options[end_date]"]').prop('disabled', false);
                }
                self.updatePreview();
            });

            // Custom interval changes
            this.$container.on('change', 'input[name="recurring_options[interval]"], select[name="recurring_options[unit]"]', function() {
                self.updatePreview();
            });

            // Monthly options changes
            this.$container.on('change', 'select[name="recurring_options[day_of_month]"], select[name="recurring_options[week_number]"], select[name="recurring_options[day_of_week]"]', function() {
                self.updatePreview();
            });

            // Occurrences or end date changes
            this.$container.on('change', 'input[name="recurring_options[occurrences]"], input[name="recurring_options[end_date]"]', function() {
                self.updatePreview();
            });

            // Booking date change
            this.$bookingDateInput.on('change', function() {
                self.updatePreview();
            });
        },

        /**
         * Handle pattern change
         *
         * @param {string} pattern Selected pattern
         */
        handlePatternChange: function(pattern) {
            // Hide all pattern options
            $('.bkx-pattern-options').hide();

            if (pattern === 'none') {
                this.$config.hide();
                this.$preview.hide();
                return;
            }

            // Show config
            this.$config.show();

            // Show relevant pattern options
            if (pattern === 'daily') {
                $('.bkx-pattern-daily').show();
            } else if (pattern === 'weekly' || pattern === 'biweekly') {
                $('.bkx-pattern-weekly').show();
                // Auto-select current day
                this.selectCurrentDay();
            } else if (pattern === 'monthly') {
                $('.bkx-pattern-monthly').show();
                // Set default day of month
                this.setDefaultDayOfMonth();
            } else if (pattern === 'custom') {
                $('.bkx-pattern-custom').show();
            }

            // Update preview
            this.updatePreview();
        },

        /**
         * Select current day in weekly selector
         */
        selectCurrentDay: function() {
            var bookingDate = this.$bookingDateInput.val();
            if (bookingDate) {
                var date = new Date(bookingDate);
                var dayOfWeek = date.getDay();
                $('.bkx-day-checkbox input[value="' + dayOfWeek + '"]').prop('checked', true);
            }
        },

        /**
         * Set default day of month
         */
        setDefaultDayOfMonth: function() {
            var bookingDate = this.$bookingDateInput.val();
            if (bookingDate) {
                var date = new Date(bookingDate);
                var dayOfMonth = date.getDate();
                $('select[name="recurring_options[day_of_month]"]').val(dayOfMonth);
            }
        },

        /**
         * Update preview
         */
        updatePreview: function() {
            var self = this;
            var pattern = this.$patternSelect.val();

            if (pattern === 'none') {
                this.$preview.hide();
                return;
            }

            var startDate = this.$bookingDateInput.val();
            if (!startDate) {
                this.$preview.hide();
                return;
            }

            // Collect options
            var options = this.collectOptions();

            // Show loading state
            this.$preview.show().addClass('is-loading');
            this.$previewDates.empty();
            this.$previewMore.hide();

            // Fetch preview
            $.ajax({
                url: bkxRecurring.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_get_recurrence_preview',
                    nonce: bkxRecurring.nonce,
                    pattern: pattern,
                    start_date: startDate,
                    options: options
                },
                success: function(response) {
                    self.$preview.removeClass('is-loading has-error');

                    if (response.success && response.data) {
                        self.renderPreview(response.data);
                    } else {
                        self.showPreviewError(response.data?.message || bkxRecurring.i18n.error);
                    }
                },
                error: function() {
                    self.$preview.removeClass('is-loading').addClass('has-error');
                    self.showPreviewError(bkxRecurring.i18n.error);
                }
            });
        },

        /**
         * Collect recurring options
         *
         * @return {object} Options
         */
        collectOptions: function() {
            var options = {};
            var pattern = this.$patternSelect.val();

            // Daily options
            if (pattern === 'daily') {
                options.skip_weekends = $('input[name="recurring_options[skip_weekends]"]').is(':checked');
            }

            // Weekly options
            if (pattern === 'weekly' || pattern === 'biweekly') {
                options.days = [];
                $('.bkx-day-checkbox input:checked').each(function() {
                    options.days.push(parseInt($(this).val(), 10));
                });
            }

            // Monthly options
            if (pattern === 'monthly') {
                options.type = $('input[name="recurring_options[type]"]:checked').val();
                if (options.type === 'day_of_month') {
                    options.day_of_month = parseInt($('select[name="recurring_options[day_of_month]"]').val(), 10);
                } else {
                    options.week_number = parseInt($('select[name="recurring_options[week_number]"]').val(), 10);
                    options.day_of_week = parseInt($('select[name="recurring_options[day_of_week]"]').val(), 10);
                }
            }

            // Custom options
            if (pattern === 'custom') {
                options.interval = parseInt($('input[name="recurring_options[interval]"]').val(), 10);
                options.unit = $('select[name="recurring_options[unit]"]').val();
            }

            // End options
            var endType = $('input[name="recurring_end_type"]:checked').val();
            if (endType === 'occurrences') {
                options.preview_count = Math.min(
                    parseInt($('input[name="recurring_options[occurrences]"]').val(), 10) || 5,
                    12
                );
            } else {
                options.end_date = $('input[name="recurring_options[end_date]"]').val();
                options.preview_count = 12;
            }

            return options;
        },

        /**
         * Render preview
         *
         * @param {object} data Preview data
         */
        renderPreview: function(data) {
            // Description
            this.$previewDescription.text(data.description || '');

            // Dates
            this.$previewDates.empty();
            if (data.dates && data.dates.length) {
                $.each(data.dates, function(index, date) {
                    var $li = $('<li></li>');
                    $li.append('<span class="bkx-date-day">' + date.day + '</span>');
                    $li.append('<span class="bkx-date-full">' + date.formatted + '</span>');
                    this.$previewDates.append($li);
                }.bind(this));
            }

            // More info
            if (data.total_count > 0) {
                var moreText = data.total_count + ' ' + bkxRecurring.i18n.occurrences;
                this.$previewMore.text(moreText).show();
            } else {
                this.$previewMore.hide();
            }
        },

        /**
         * Show preview error
         *
         * @param {string} message Error message
         */
        showPreviewError: function(message) {
            this.$previewDescription.empty();
            this.$previewDates.html('<li class="bkx-preview-error">' + message + '</li>');
            this.$previewMore.hide();
        }
    };

    /**
     * Customer Dashboard - Recurring Series
     */
    var BKXRecurringSeries = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // View instances
            $(document).on('click', '.bkx-view-instances', function(e) {
                e.preventDefault();
                var seriesId = $(this).data('series-id');
                self.loadInstances(seriesId);
            });

            // Skip instance
            $(document).on('click', '.bkx-skip-btn', function(e) {
                e.preventDefault();
                if (confirm('Skip this booking instance?')) {
                    var instanceId = $(this).data('instance-id');
                    self.skipInstance(instanceId, $(this).closest('.bkx-instance-item'));
                }
            });

            // Reschedule instance
            $(document).on('click', '.bkx-reschedule-btn', function(e) {
                e.preventDefault();
                var instanceId = $(this).data('instance-id');
                self.openRescheduleModal(instanceId);
            });

            // Cancel series
            $(document).on('click', '.bkx-cancel-series-btn', function(e) {
                e.preventDefault();
                if (confirm('Cancel all future instances in this series?')) {
                    var seriesId = $(this).data('series-id');
                    self.cancelSeries(seriesId);
                }
            });
        },

        /**
         * Load instances for a series
         *
         * @param {int} seriesId Series ID
         */
        loadInstances: function(seriesId) {
            var $container = $('.bkx-instances-container[data-series-id="' + seriesId + '"]');

            if ($container.is(':visible')) {
                $container.slideUp();
                return;
            }

            $container.html('<p class="loading">' + bkxRecurring.i18n.loading + '</p>').slideDown();

            $.ajax({
                url: bkxRecurring.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_get_series_instances',
                    nonce: bkxRecurring.nonce,
                    series_id: seriesId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        $container.html(response.data.html);
                    } else {
                        $container.html('<p class="error">' + bkxRecurring.i18n.error + '</p>');
                    }
                },
                error: function() {
                    $container.html('<p class="error">' + bkxRecurring.i18n.error + '</p>');
                }
            });
        },

        /**
         * Skip an instance
         *
         * @param {int} instanceId Instance ID
         * @param {jQuery} $element Element to update
         */
        skipInstance: function(instanceId, $element) {
            $.ajax({
                url: bkxRecurring.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_skip_instance',
                    nonce: bkxRecurring.nonce,
                    instance_id: instanceId
                },
                success: function(response) {
                    if (response.success) {
                        $element.find('.bkx-instance-status')
                            .removeClass('scheduled')
                            .addClass('skipped')
                            .text('Skipped');
                        $element.find('.bkx-instance-actions').remove();
                    } else {
                        alert(response.data?.message || bkxRecurring.i18n.error);
                    }
                },
                error: function() {
                    alert(bkxRecurring.i18n.error);
                }
            });
        },

        /**
         * Open reschedule modal
         *
         * @param {int} instanceId Instance ID
         */
        openRescheduleModal: function(instanceId) {
            // Simple prompt for now - could be enhanced with a modal
            var newDate = prompt('Enter new date (YYYY-MM-DD):');
            if (!newDate) return;

            $.ajax({
                url: bkxRecurring.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_reschedule_instance',
                    nonce: bkxRecurring.nonce,
                    instance_id: instanceId,
                    new_date: newDate
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data?.message || bkxRecurring.i18n.error);
                    }
                },
                error: function() {
                    alert(bkxRecurring.i18n.error);
                }
            });
        },

        /**
         * Cancel a series
         *
         * @param {int} seriesId Series ID
         */
        cancelSeries: function(seriesId) {
            $.ajax({
                url: bkxRecurring.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_cancel_recurring_series',
                    nonce: bkxRecurring.nonce,
                    series_id: seriesId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data?.message || bkxRecurring.i18n.error);
                    }
                },
                error: function() {
                    alert(bkxRecurring.i18n.error);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        if ($('.bkx-recurring-options').length) {
            BKXRecurring.init();
        }
        if ($('.bkx-recurring-series').length) {
            BKXRecurringSeries.init();
        }
    });

})(jQuery);
