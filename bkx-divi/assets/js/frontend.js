/**
 * BookingX Divi Integration - Frontend JavaScript
 *
 * @package BookingX\Divi
 */

(function($) {
    'use strict';

    var BKX_Divi_Frontend = {
        init: function() {
            this.initCalendars();
            this.initModals();
            this.initCarousels();
        },

        initCalendars: function() {
            $('.bkx-divi-availability-calendar').each(function() {
                var $calendar = $(this);
                var config = {
                    view: $calendar.data('view') || 'month',
                    navigation: $calendar.data('navigation') === 'true',
                    viewToggle: $calendar.data('view-toggle') === 'true',
                    clickable: $calendar.data('clickable') === 'true',
                    service: $calendar.data('service') || '',
                    resource: $calendar.data('resource') || '',
                    minDate: $calendar.data('min-date') || 'today',
                    maxMonths: parseInt($calendar.data('max-months')) || 3
                };

                BKX_Divi_Frontend.loadCalendar($calendar, config);
            });
        },

        loadCalendar: function($calendar, config) {
            var $grid = $calendar.find('.bkx-calendar-grid');
            var $title = $calendar.find('.bkx-calendar-title');
            var currentDate = new Date();

            // Set min date
            switch (config.minDate) {
                case 'tomorrow':
                    currentDate.setDate(currentDate.getDate() + 1);
                    break;
                case 'week':
                    currentDate.setDate(currentDate.getDate() + 7);
                    break;
            }

            // Render initial calendar
            BKX_Divi_Frontend.renderCalendarMonth($grid, $title, currentDate, config);

            // Navigation
            $calendar.find('.bkx-calendar-prev').on('click', function() {
                currentDate.setMonth(currentDate.getMonth() - 1);
                BKX_Divi_Frontend.renderCalendarMonth($grid, $title, currentDate, config);
            });

            $calendar.find('.bkx-calendar-next').on('click', function() {
                currentDate.setMonth(currentDate.getMonth() + 1);
                BKX_Divi_Frontend.renderCalendarMonth($grid, $title, currentDate, config);
            });

            // View toggle
            $calendar.find('.bkx-view-btn').on('click', function() {
                var $btn = $(this);
                var newView = $btn.data('view');

                $calendar.find('.bkx-view-btn').removeClass('active');
                $btn.addClass('active');

                config.view = newView;
                BKX_Divi_Frontend.renderCalendarMonth($grid, $title, currentDate, config);
            });
        },

        renderCalendarMonth: function($grid, $title, date, config) {
            var monthNames = [
                'January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            var dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

            $title.text(monthNames[date.getMonth()] + ' ' + date.getFullYear());

            var firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
            var lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0);
            var startDay = firstDay.getDay();
            var daysInMonth = lastDay.getDate();

            var html = '<div class="bkx-calendar-weekdays">';
            for (var i = 0; i < 7; i++) {
                html += '<div class="bkx-weekday">' + dayNames[i] + '</div>';
            }
            html += '</div>';

            html += '<div class="bkx-calendar-days">';

            // Empty cells before first day
            for (var j = 0; j < startDay; j++) {
                html += '<div class="bkx-day bkx-day-empty"></div>';
            }

            // Days of month
            var today = new Date();
            today.setHours(0, 0, 0, 0);

            for (var d = 1; d <= daysInMonth; d++) {
                var dayDate = new Date(date.getFullYear(), date.getMonth(), d);
                var isPast = dayDate < today;
                var isToday = dayDate.getTime() === today.getTime();

                var classes = ['bkx-day'];
                if (isPast) {
                    classes.push('bkx-day-past');
                }
                if (isToday) {
                    classes.push('bkx-day-today');
                }

                // Simulate availability (in production, this would come from AJAX)
                if (!isPast) {
                    var random = Math.random();
                    if (random > 0.7) {
                        classes.push('bkx-slot-available');
                    } else if (random > 0.4) {
                        classes.push('bkx-slot-limited');
                    } else {
                        classes.push('bkx-slot-unavailable');
                    }
                }

                html += '<div class="' + classes.join(' ') + '" data-date="' + dayDate.toISOString().split('T')[0] + '">';
                html += '<span class="bkx-day-number">' + d + '</span>';
                html += '</div>';
            }

            html += '</div>';

            $grid.html(html);

            // Add click handlers
            if (config.clickable) {
                $grid.find('.bkx-day.bkx-slot-available, .bkx-day.bkx-slot-limited').on('click', function() {
                    var date = $(this).data('date');
                    BKX_Divi_Frontend.handleDateClick(date, config);
                });
            }

            // Add CSS
            if (!$('#bkx-calendar-styles').length) {
                $('head').append('<style id="bkx-calendar-styles">' +
                    '.bkx-calendar-weekdays { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; margin-bottom: 10px; }' +
                    '.bkx-weekday { text-align: center; font-weight: 600; font-size: 12px; color: #666; padding: 10px 5px; }' +
                    '.bkx-calendar-days { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; }' +
                    '.bkx-day { text-align: center; padding: 10px 5px; border-radius: 4px; cursor: pointer; transition: all 0.2s; }' +
                    '.bkx-day-empty { background: transparent; cursor: default; }' +
                    '.bkx-day-past { background: #f3f4f6; color: #9ca3af; cursor: not-allowed; }' +
                    '.bkx-day-today { font-weight: 700; }' +
                    '.bkx-day.bkx-slot-available:hover, .bkx-day.bkx-slot-limited:hover { transform: scale(1.1); }' +
                '</style>');
            }
        },

        handleDateClick: function(date, config) {
            var bookingPage = '';
            var url = bookingPage + '?date=' + date;

            if (config.service) {
                url += '&service_id=' + config.service;
            }
            if (config.resource) {
                url += '&resource_id=' + config.resource;
            }

            window.location.href = url;
        },

        initModals: function() {
            $(document).on('click', '.bkx-btn-modal-trigger', function(e) {
                e.preventDefault();

                var $button = $(this);
                var service = $button.data('service') || '';
                var resource = $button.data('resource') || '';

                // Create modal
                var $modal = $('<div class="bkx-booking-modal">' +
                    '<div class="bkx-modal-overlay"></div>' +
                    '<div class="bkx-modal-content">' +
                    '<button type="button" class="bkx-modal-close">&times;</button>' +
                    '<div class="bkx-modal-body"><div class="bkx-modal-loading"><span class="spinner is-active"></span> Loading...</div></div>' +
                    '</div></div>');

                $('body').append($modal);

                // Add modal styles
                if (!$('#bkx-modal-styles').length) {
                    $('head').append('<style id="bkx-modal-styles">' +
                        '.bkx-booking-modal { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 999999; display: flex; align-items: center; justify-content: center; }' +
                        '.bkx-modal-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); }' +
                        '.bkx-modal-content { position: relative; background: #fff; border-radius: 8px; max-width: 600px; width: 90%; max-height: 90vh; overflow: auto; }' +
                        '.bkx-modal-close { position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; cursor: pointer; z-index: 1; }' +
                        '.bkx-modal-body { padding: 30px; }' +
                        '.bkx-modal-loading { text-align: center; color: #666; }' +
                    '</style>');
                }

                // Close handlers
                $modal.find('.bkx-modal-overlay, .bkx-modal-close').on('click', function() {
                    $modal.fadeOut(function() {
                        $(this).remove();
                    });
                });

                // Load booking form via AJAX (placeholder)
                setTimeout(function() {
                    $modal.find('.bkx-modal-body').html('<p style="text-align:center">Booking form would load here via AJAX</p>');
                }, 1000);
            });
        },

        initCarousels: function() {
            $('.bkx-layout-carousel').each(function() {
                // Initialize carousel (would use a carousel library like Slick or Swiper)
                // This is a placeholder for the carousel functionality
            });
        }
    };

    $(document).ready(function() {
        BKX_Divi_Frontend.init();
    });

    // Divi Builder preview support
    $(window).on('et_builder_api_ready', function() {
        BKX_Divi_Frontend.init();
    });

})(jQuery);
