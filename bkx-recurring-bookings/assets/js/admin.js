/**
 * Recurring Bookings Admin JavaScript
 *
 * @package BookingX\RecurringBookings
 * @since   1.0.0
 */

(function($) {
    'use strict';

    /**
     * Admin Handler
     */
    var BKXRecurringAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initModal();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // View series instances
            $(document).on('click', '.bkx-view-series, .bkx-view-instances', function(e) {
                e.preventDefault();
                var seriesId = $(this).data('series-id');
                self.openSeriesModal(seriesId);
            });

            // Cancel series
            $(document).on('click', '.bkx-cancel-series', function(e) {
                e.preventDefault();
                if (confirm(bkxRecurringAdmin.i18n.confirm_cancel)) {
                    var seriesId = $(this).data('series-id');
                    self.cancelSeries(seriesId, $(this));
                }
            });

            // Skip instance
            $(document).on('click', '.bkx-skip-instance', function(e) {
                e.preventDefault();
                if (confirm(bkxRecurringAdmin.i18n.confirm_skip)) {
                    var instanceId = $(this).data('instance-id');
                    self.skipInstance(instanceId, $(this).closest('.bkx-instance-row'));
                }
            });

            // Reschedule instance
            $(document).on('click', '.bkx-reschedule-instance', function(e) {
                e.preventDefault();
                var instanceId = $(this).data('instance-id');
                var currentDate = $(this).data('current-date');
                self.openRescheduleDialog(instanceId, currentDate);
            });

            // Close modal
            $(document).on('click', '.bkx-modal-close, .bkx-series-modal', function(e) {
                if (e.target === this) {
                    self.closeModal();
                }
            });

            // ESC key to close modal
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape') {
                    self.closeModal();
                }
            });
        },

        /**
         * Initialize modal
         */
        initModal: function() {
            // Create modal if it doesn't exist
            if (!$('#bkx-series-modal').length) {
                var modal = '<div id="bkx-series-modal" class="bkx-series-modal">' +
                    '<div class="bkx-modal-content">' +
                    '<div class="bkx-modal-header">' +
                    '<h3>' + 'Series Instances' + '</h3>' +
                    '<button type="button" class="bkx-modal-close">&times;</button>' +
                    '</div>' +
                    '<div class="bkx-modal-body"></div>' +
                    '<div class="bkx-modal-footer">' +
                    '<button type="button" class="button bkx-modal-close">Close</button>' +
                    '</div>' +
                    '</div>' +
                    '</div>';
                $('body').append(modal);
            }
        },

        /**
         * Open series modal
         *
         * @param {int} seriesId Series ID
         */
        openSeriesModal: function(seriesId) {
            var self = this;
            var $modal = $('#bkx-series-modal');
            var $body = $modal.find('.bkx-modal-body');

            // Show loading
            $body.html('<div class="bkx-loading"></div>');
            $modal.addClass('is-open');

            // Fetch instances
            $.ajax({
                url: bkxRecurringAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_get_series_instances',
                    nonce: bkxRecurringAdmin.nonce,
                    series_id: seriesId
                },
                success: function(response) {
                    if (response.success && response.data) {
                        self.renderInstances($body, response.data);
                    } else {
                        $body.html('<p class="error">' + bkxRecurringAdmin.i18n.error + '</p>');
                    }
                },
                error: function() {
                    $body.html('<p class="error">' + bkxRecurringAdmin.i18n.error + '</p>');
                }
            });
        },

        /**
         * Render instances in modal
         *
         * @param {jQuery} $container Container element
         * @param {array} instances Instances data
         */
        renderInstances: function($container, instances) {
            var html = '';

            // Stats
            var stats = this.calculateStats(instances);
            html += '<div class="bkx-stats-grid">';
            html += '<div class="bkx-stat-card"><div class="bkx-stat-value">' + stats.total + '</div><div class="bkx-stat-label">Total</div></div>';
            html += '<div class="bkx-stat-card"><div class="bkx-stat-value">' + stats.scheduled + '</div><div class="bkx-stat-label">Scheduled</div></div>';
            html += '<div class="bkx-stat-card"><div class="bkx-stat-value">' + stats.completed + '</div><div class="bkx-stat-label">Completed</div></div>';
            html += '<div class="bkx-stat-card"><div class="bkx-stat-value">' + stats.skipped + '</div><div class="bkx-stat-label">Skipped</div></div>';
            html += '</div>';

            // Instances list
            html += '<ul class="bkx-instances-list">';

            if (instances.length) {
                $.each(instances, function(index, instance) {
                    html += '<li class="bkx-instance-row">';
                    html += '<div class="bkx-instance-info">';
                    html += '<span class="bkx-instance-number">' + instance.instance_number + '</span>';
                    html += '<span class="bkx-instance-date">' + instance.scheduled_date + '</span>';
                    html += '<span class="bkx-instance-time">' + instance.scheduled_time + '</span>';
                    html += '</div>';
                    html += '<span class="bkx-instance-status ' + instance.status + '">' + instance.status + '</span>';

                    if (instance.status === 'scheduled') {
                        html += '<div class="bkx-instance-actions">';
                        html += '<button type="button" class="button button-small bkx-reschedule-instance" data-instance-id="' + instance.id + '" data-current-date="' + instance.scheduled_date + '">Reschedule</button>';
                        html += '<button type="button" class="button button-small bkx-skip-instance" data-instance-id="' + instance.id + '">Skip</button>';
                        html += '</div>';
                    }

                    html += '</li>';
                });
            } else {
                html += '<li class="bkx-no-instances">No instances found.</li>';
            }

            html += '</ul>';

            $container.html(html);
        },

        /**
         * Calculate stats from instances
         *
         * @param {array} instances Instances
         * @return {object} Stats
         */
        calculateStats: function(instances) {
            var stats = {
                total: instances.length,
                scheduled: 0,
                completed: 0,
                skipped: 0,
                cancelled: 0
            };

            $.each(instances, function(index, instance) {
                if (stats.hasOwnProperty(instance.status)) {
                    stats[instance.status]++;
                }
            });

            return stats;
        },

        /**
         * Close modal
         */
        closeModal: function() {
            $('#bkx-series-modal').removeClass('is-open');
        },

        /**
         * Cancel series
         *
         * @param {int} seriesId Series ID
         * @param {jQuery} $button Button element
         */
        cancelSeries: function(seriesId, $button) {
            var reason = prompt('Cancellation reason (optional):');

            $button.prop('disabled', true).text('Cancelling...');

            $.ajax({
                url: bkxRecurringAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_cancel_recurring_series',
                    nonce: bkxRecurringAdmin.nonce,
                    series_id: seriesId,
                    reason: reason || ''
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data?.message || bkxRecurringAdmin.i18n.error);
                        $button.prop('disabled', false).text('Cancel');
                    }
                },
                error: function() {
                    alert(bkxRecurringAdmin.i18n.error);
                    $button.prop('disabled', false).text('Cancel');
                }
            });
        },

        /**
         * Skip instance
         *
         * @param {int} instanceId Instance ID
         * @param {jQuery} $row Row element
         */
        skipInstance: function(instanceId, $row) {
            var reason = prompt('Skip reason (optional):');

            $.ajax({
                url: bkxRecurringAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_skip_instance',
                    nonce: bkxRecurringAdmin.nonce,
                    instance_id: instanceId,
                    reason: reason || ''
                },
                success: function(response) {
                    if (response.success) {
                        $row.find('.bkx-instance-status')
                            .removeClass('scheduled')
                            .addClass('skipped')
                            .text('skipped');
                        $row.find('.bkx-instance-actions').remove();
                    } else {
                        alert(response.data?.message || bkxRecurringAdmin.i18n.error);
                    }
                },
                error: function() {
                    alert(bkxRecurringAdmin.i18n.error);
                }
            });
        },

        /**
         * Open reschedule dialog
         *
         * @param {int} instanceId Instance ID
         * @param {string} currentDate Current date
         */
        openRescheduleDialog: function(instanceId, currentDate) {
            var self = this;
            var newDate = prompt('Enter new date (YYYY-MM-DD):', currentDate);

            if (!newDate) {
                return;
            }

            // Validate date format
            if (!/^\d{4}-\d{2}-\d{2}$/.test(newDate)) {
                alert('Invalid date format. Please use YYYY-MM-DD.');
                return;
            }

            $.ajax({
                url: bkxRecurringAdmin.ajax_url,
                type: 'POST',
                data: {
                    action: 'bkx_reschedule_instance',
                    nonce: bkxRecurringAdmin.nonce,
                    instance_id: instanceId,
                    new_date: newDate
                },
                success: function(response) {
                    if (response.success) {
                        // Refresh the modal content
                        var seriesId = $('.bkx-instance-row').first().closest('.bkx-modal-body')
                            .data('series-id');
                        if (seriesId) {
                            self.openSeriesModal(seriesId);
                        } else {
                            location.reload();
                        }
                    } else {
                        alert(response.data?.message || bkxRecurringAdmin.i18n.error);
                    }
                },
                error: function() {
                    alert(bkxRecurringAdmin.i18n.error);
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        BKXRecurringAdmin.init();
    });

})(jQuery);
