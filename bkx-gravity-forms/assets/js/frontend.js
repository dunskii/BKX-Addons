/**
 * BookingX Gravity Forms Frontend Scripts
 *
 * @package BookingX\GravityForms
 * @since   1.0.0
 */

(function($) {
	'use strict';

	var BkxGravityForms = {
		selectedService: null,
		selectedSeat: null,
		selectedDate: null,
		selectedTime: null,
		selectedExtras: [],

		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.initCalendars();
		},

		/**
		 * Bind events
		 */
		bindEvents: function() {
			var self = this;

			// Service selection
			$(document).on('change', '.bkx-gf-service-field .bkx-service-select', function() {
				self.onServiceChange($(this).val());
			});

			$(document).on('change', '.bkx-gf-service-field input[type="radio"]', function() {
				self.onServiceChange($(this).val());
			});

			$(document).on('click', '.bkx-gf-service-field .bkx-service-card', function() {
				var $card = $(this);
				var $container = $card.closest('.bkx-gf-service-field');

				$container.find('.bkx-service-card').removeClass('active');
				$card.addClass('active');
				$container.find('.bkx-service-hidden').val($card.data('id'));

				self.onServiceChange($card.data('id'));
			});

			// Seat selection
			$(document).on('change', '.bkx-gf-seat-field .bkx-seat-select', function() {
				self.onSeatChange($(this).val());
			});

			$(document).on('click', '.bkx-gf-seat-field .bkx-seat-card', function() {
				var $card = $(this);
				var $container = $card.closest('.bkx-gf-seat-field');

				$container.find('.bkx-seat-card').removeClass('active');
				$card.addClass('active');
				$container.find('.bkx-seat-hidden').val($card.data('id'));

				self.onSeatChange($card.data('id'));
			});

			// Time slot selection
			$(document).on('click', '.bkx-gf-time-field .time-slot:not(.unavailable)', function() {
				var $slot = $(this);
				var $container = $slot.closest('.bkx-gf-time-field');

				$container.find('.time-slot').removeClass('selected');
				$slot.addClass('selected');
				$container.find('.bkx-time-input').val($slot.data('time'));

				self.selectedTime = $slot.data('time');
				self.updateSummary();
			});

			// Extras selection
			$(document).on('change', '.bkx-gf-extras-field input[type="checkbox"]', function() {
				self.updateSelectedExtras();
				self.updateSummary();
			});
		},

		/**
		 * Initialize calendars
		 */
		initCalendars: function() {
			var self = this;

			$('.bkx-gf-date-field').each(function() {
				var $field = $(this);
				var $input = $field.find('.bkx-date-picker');
				var $container = $field.find('.bkx-calendar-container');
				var minDate = parseInt($input.data('min-date')) || 0;
				var maxDate = parseInt($input.data('max-date')) || 90;
				var currentMonth = new Date();

				// Create calendar structure
				$container.html(
					'<div class="calendar-header">' +
						'<button type="button" class="prev-month">&larr;</button>' +
						'<span class="current-month"></span>' +
						'<button type="button" class="next-month">&rarr;</button>' +
					'</div>' +
					'<div class="calendar-weekdays">' +
						'<span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span>' +
						'<span>Thu</span><span>Fri</span><span>Sat</span>' +
					'</div>' +
					'<div class="calendar-days"></div>'
				);

				// Render initial calendar
				self.renderCalendar($container, currentMonth, minDate, maxDate);

				// Toggle calendar on input click
				$input.on('click', function(e) {
					e.stopPropagation();
					$container.toggleClass('open');
				});

				// Navigation
				$container.on('click', '.prev-month', function(e) {
					e.preventDefault();
					currentMonth.setMonth(currentMonth.getMonth() - 1);
					self.renderCalendar($container, currentMonth, minDate, maxDate);
				});

				$container.on('click', '.next-month', function(e) {
					e.preventDefault();
					currentMonth.setMonth(currentMonth.getMonth() + 1);
					self.renderCalendar($container, currentMonth, minDate, maxDate);
				});

				// Day selection
				$container.on('click', '.calendar-day:not(.disabled):not(.empty)', function() {
					var date = $(this).data('date');

					$container.find('.calendar-day').removeClass('selected');
					$(this).addClass('selected');

					$input.val(self.formatDateDisplay(date));
					$container.removeClass('open');

					self.selectedDate = date;
					self.loadTimeSlots();
					self.updateSummary();
				});
			});

			// Close calendar on outside click
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.bkx-gf-date-field').length) {
					$('.bkx-calendar-container').removeClass('open');
				}
			});
		},

		/**
		 * Render calendar
		 */
		renderCalendar: function($container, date, minDays, maxDays) {
			var $days = $container.find('.calendar-days');
			var $monthLabel = $container.find('.current-month');

			var year = date.getFullYear();
			var month = date.getMonth();
			var firstDay = new Date(year, month, 1).getDay();
			var daysInMonth = new Date(year, month + 1, 0).getDate();

			var today = new Date();
			today.setHours(0, 0, 0, 0);

			var minDate = new Date(today);
			minDate.setDate(minDate.getDate() + minDays);

			var maxDateObj = new Date(today);
			maxDateObj.setDate(maxDateObj.getDate() + maxDays);

			var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
				'July', 'August', 'September', 'October', 'November', 'December'];

			$monthLabel.text(monthNames[month] + ' ' + year);
			$days.empty();

			// Empty cells before first day
			for (var i = 0; i < firstDay; i++) {
				$days.append('<div class="calendar-day empty"></div>');
			}

			// Days of month
			for (var day = 1; day <= daysInMonth; day++) {
				var dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
				var dayDate = new Date(year, month, day);
				var isDisabled = dayDate < minDate || dayDate > maxDateObj;
				var isToday = dayDate.getTime() === today.getTime();
				var isSelected = dateStr === this.selectedDate;

				var classes = ['calendar-day'];
				if (isDisabled) classes.push('disabled');
				if (isToday) classes.push('today');
				if (isSelected) classes.push('selected');

				$days.append(
					'<div class="' + classes.join(' ') + '" data-date="' + dateStr + '">' + day + '</div>'
				);
			}
		},

		/**
		 * Format date for display
		 */
		formatDateDisplay: function(dateStr) {
			var date = new Date(dateStr);
			var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
			return date.toLocaleDateString(undefined, options);
		},

		/**
		 * On service change
		 */
		onServiceChange: function(serviceId) {
			this.selectedService = serviceId;
			this.loadSeats();
			this.loadTimeSlots();
			this.updateSummary();
		},

		/**
		 * On seat change
		 */
		onSeatChange: function(seatId) {
			this.selectedSeat = seatId;
			this.loadTimeSlots();
			this.updateSummary();
		},

		/**
		 * Load seats for service
		 */
		loadSeats: function() {
			if (!this.selectedService) return;

			var self = this;
			var $seatField = $('.bkx-gf-seat-field');
			if (!$seatField.length) return;

			$.ajax({
				url: bkxGravityForms.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_gf_get_seats',
					nonce: bkxGravityForms.nonce,
					service_id: this.selectedService
				},
				success: function(response) {
					if (response.success && response.data.length) {
						// Update seat options if needed
						// For now we'll keep the full list
					}
				}
			});
		},

		/**
		 * Load time slots
		 */
		loadTimeSlots: function() {
			if (!this.selectedDate) return;

			var self = this;
			var $container = $('.bkx-gf-time-field .bkx-time-slots-container');

			$container.addClass('loading').html('');

			$.ajax({
				url: bkxGravityForms.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_gf_get_time_slots',
					nonce: bkxGravityForms.nonce,
					service_id: this.selectedService || 0,
					seat_id: this.selectedSeat || 0,
					date: this.selectedDate
				},
				success: function(response) {
					$container.removeClass('loading');

					if (response.success && response.data.length) {
						self.renderTimeSlots($container, response.data);
					} else {
						$container.html(
							'<p class="no-slots-message">' +
							(bkxGravityForms.i18n.noSlots || 'No available time slots') +
							'</p>'
						);
					}
				},
				error: function() {
					$container.removeClass('loading').html(
						'<p class="no-slots-message">Error loading time slots</p>'
					);
				}
			});
		},

		/**
		 * Render time slots
		 */
		renderTimeSlots: function($container, slots) {
			var self = this;
			var isGrid = $container.closest('.bkx-gf-time-field').hasClass('bkx-time-grid');
			var wrapperClass = isGrid ? 'time-slots-grid' : 'time-slots-list';

			var html = '<div class="' + wrapperClass + '">';

			$.each(slots, function(i, slot) {
				var classes = ['time-slot'];
				if (!slot.available) classes.push('unavailable');
				if (slot.time === self.selectedTime) classes.push('selected');

				html += '<div class="' + classes.join(' ') + '" data-time="' + slot.time + '">' +
					slot.display +
					'</div>';
			});

			html += '</div>';
			$container.html(html);
		},

		/**
		 * Update selected extras
		 */
		updateSelectedExtras: function() {
			this.selectedExtras = [];
			var self = this;

			$('.bkx-gf-extras-field input[type="checkbox"]:checked').each(function() {
				self.selectedExtras.push({
					id: $(this).val(),
					name: $(this).closest('.bkx-extra-item').find('.extra-name').text(),
					price: parseFloat($(this).data('price')) || 0
				});
			});
		},

		/**
		 * Update booking summary
		 */
		updateSummary: function() {
			var $summary = $('.bkx-gf-summary-field');
			if (!$summary.length) return;

			var total = 0;

			// Service
			if (this.selectedService) {
				var $serviceOption = $('.bkx-gf-service-field select option:selected, ' +
					'.bkx-gf-service-field input[type="radio"]:checked, ' +
					'.bkx-gf-service-field .bkx-service-card.active');

				var serviceName = $serviceOption.text() || $serviceOption.find('.card-title').text();
				var servicePrice = parseFloat($serviceOption.data('price')) || 0;

				$summary.find('.summary-service .value').text(serviceName.trim());
				total += servicePrice;
			} else {
				$summary.find('.summary-service .value').text('-');
			}

			// Seat
			if (this.selectedSeat) {
				var $seatOption = $('.bkx-gf-seat-field select option:selected, ' +
					'.bkx-gf-seat-field .bkx-seat-card.active');

				var seatName = $seatOption.text() || $seatOption.find('.card-name').text();
				$summary.find('.summary-seat .value').text(seatName.trim());
			} else {
				$summary.find('.summary-seat .value').text('-');
			}

			// Date & Time
			if (this.selectedDate && this.selectedTime) {
				var displayDate = this.formatDateDisplay(this.selectedDate);
				var displayTime = this.selectedTime;
				$summary.find('.summary-datetime .value').text(displayDate + ' at ' + displayTime);
			} else if (this.selectedDate) {
				$summary.find('.summary-datetime .value').text(this.formatDateDisplay(this.selectedDate));
			} else {
				$summary.find('.summary-datetime .value').text('-');
			}

			// Extras
			if (this.selectedExtras.length) {
				var extraNames = [];
				var extrasTotal = 0;

				$.each(this.selectedExtras, function(i, extra) {
					extraNames.push(extra.name);
					extrasTotal += extra.price;
				});

				$summary.find('.summary-extras').show().find('.value').text(extraNames.join(', '));
				total += extrasTotal;
			} else {
				$summary.find('.summary-extras').hide();
			}

			// Total
			$summary.find('.summary-total .value').text('$' + total.toFixed(2));
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		BkxGravityForms.init();
	});

	// Re-initialize on AJAX form load
	$(document).on('gform_post_render', function() {
		BkxGravityForms.init();
	});

})(jQuery);
