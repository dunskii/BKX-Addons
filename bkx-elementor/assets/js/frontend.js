/**
 * BookingX Elementor Frontend Scripts
 *
 * @package BookingX\Elementor
 * @since   1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Booking Form Widget Handler
	 */
	var BookingFormWidget = function($scope) {
		var $widget = $scope.find('.bkx-booking-form-widget');
		if (!$widget.length) return;

		var currentStep = 1;
		var totalSteps = $widget.find('.form-step').length;
		var selectedService = null;
		var selectedSeat = null;
		var selectedDate = null;
		var selectedTime = null;

		// Initialize calendar
		initCalendar();

		// Service selection
		$widget.on('change', '.service-dropdown, input[name="service_id"]', function() {
			selectedService = $(this).val();
			loadSeats(selectedService);
		});

		$widget.on('click', '.service-card', function() {
			$widget.find('.service-card').removeClass('selected');
			$(this).addClass('selected');
			selectedService = $(this).data('service-id');
			$widget.find('input[name="service_id"]').val(selectedService);
			loadSeats(selectedService);
		});

		// Seat selection
		$widget.on('change', '.seat-dropdown, input[name="seat_id"]', function() {
			selectedSeat = $(this).val();
			if (selectedDate) {
				loadTimeSlots(selectedDate);
			}
		});

		// Time slot selection
		$widget.on('click', '.time-slot:not(.unavailable)', function() {
			$widget.find('.time-slot').removeClass('selected');
			$(this).addClass('selected');
			selectedTime = $(this).data('time');
			$widget.find('input[name="booking_time"]').val(selectedTime);
		});

		$widget.on('change', '.time-slots-dropdown', function() {
			selectedTime = $(this).val();
		});

		// Navigation
		$widget.on('click', '.btn-next', function(e) {
			e.preventDefault();
			if (validateStep(currentStep)) {
				goToStep(currentStep + 1);
			}
		});

		$widget.on('click', '.btn-prev', function(e) {
			e.preventDefault();
			goToStep(currentStep - 1);
		});

		// Form submission
		$widget.on('submit', 'form', function(e) {
			e.preventDefault();
			if (validateStep(currentStep)) {
				submitBooking();
			}
		});

		/**
		 * Initialize calendar
		 */
		function initCalendar() {
			var $calendar = $widget.find('.inline-calendar, .calendar-popup');
			if (!$calendar.length) return;

			var currentMonth = new Date();
			renderCalendar(currentMonth);

			// Navigation
			$widget.on('click', '.nav-prev', function(e) {
				e.preventDefault();
				currentMonth.setMonth(currentMonth.getMonth() - 1);
				renderCalendar(currentMonth);
			});

			$widget.on('click', '.nav-next', function(e) {
				e.preventDefault();
				currentMonth.setMonth(currentMonth.getMonth() + 1);
				renderCalendar(currentMonth);
			});

			// Day selection
			$widget.on('click', '.calendar-day:not(.disabled):not(.empty)', function() {
				$widget.find('.calendar-day').removeClass('selected');
				$(this).addClass('selected');
				selectedDate = $(this).data('date');
				$widget.find('input[name="booking_date"]').val(selectedDate);
				$widget.find('.popup-calendar-trigger span:first').text(formatDate(selectedDate));
				$widget.find('.calendar-popup').removeClass('open');
				loadTimeSlots(selectedDate);
			});

			// Popup trigger
			$widget.on('click', '.popup-calendar-trigger', function(e) {
				e.preventDefault();
				$widget.find('.calendar-popup').toggleClass('open');
			});

			// Close popup on outside click
			$(document).on('click', function(e) {
				if (!$(e.target).closest('.date-picker-wrapper').length) {
					$widget.find('.calendar-popup').removeClass('open');
				}
			});
		}

		/**
		 * Render calendar
		 */
		function renderCalendar(date) {
			var $calendar = $widget.find('.calendar-days');
			var $monthLabel = $widget.find('.current-month');

			var year = date.getFullYear();
			var month = date.getMonth();
			var firstDay = new Date(year, month, 1).getDay();
			var daysInMonth = new Date(year, month + 1, 0).getDate();
			var today = new Date();
			today.setHours(0, 0, 0, 0);

			// Update month label
			var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
				'July', 'August', 'September', 'October', 'November', 'December'];
			$monthLabel.text(monthNames[month] + ' ' + year);

			// Clear calendar
			$calendar.empty();

			// Empty cells before first day
			for (var i = 0; i < firstDay; i++) {
				$calendar.append('<div class="calendar-day empty"></div>');
			}

			// Days
			for (var day = 1; day <= daysInMonth; day++) {
				var dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
				var dayDate = new Date(year, month, day);
				var isPast = dayDate < today;
				var isToday = dayDate.getTime() === today.getTime();
				var isSelected = dateStr === selectedDate;

				var classes = ['calendar-day'];
				if (isPast) classes.push('disabled');
				if (isToday) classes.push('today');
				if (isSelected) classes.push('selected');

				$calendar.append(
					'<div class="' + classes.join(' ') + '" data-date="' + dateStr + '">' + day + '</div>'
				);
			}
		}

		/**
		 * Load seats for service
		 */
		function loadSeats(serviceId) {
			if (!serviceId) return;

			var $container = $widget.find('.seat-selection');
			if (!$container.length) return;

			$container.addClass('loading');

			$.ajax({
				url: bkxElementor.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_elementor_get_seats',
					nonce: bkxElementor.nonce,
					service_id: serviceId
				},
				success: function(response) {
					$container.removeClass('loading');
					if (response.success) {
						renderSeats(response.data);
					}
				}
			});
		}

		/**
		 * Render seats
		 */
		function renderSeats(seats) {
			var $container = $widget.find('.seat-list, .seat-dropdown');

			if ($container.is('select')) {
				$container.empty().append('<option value="">' + bkxElementor.i18n.selectStaff + '</option>');
				$.each(seats, function(i, seat) {
					$container.append('<option value="' + seat.id + '">' + seat.name + '</option>');
				});
			} else {
				$container.empty();
				$.each(seats, function(i, seat) {
					$container.append(
						'<label class="seat-radio-item">' +
						'<input type="radio" name="seat_id" value="' + seat.id + '">' +
						'<span class="seat-name">' + seat.name + '</span>' +
						'</label>'
					);
				});
			}
		}

		/**
		 * Load time slots
		 */
		function loadTimeSlots(date) {
			var $container = $widget.find('.time-slots-container');
			if (!$container.length) return;

			var serviceId = selectedService || $widget.find('input[name="service_id"]').val();
			var seatId = selectedSeat || $widget.find('input[name="seat_id"]').val();

			if (!date || !serviceId) return;

			$container.addClass('loading');

			$.ajax({
				url: bkxElementor.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_elementor_get_availability',
					nonce: bkxElementor.nonce,
					service_id: serviceId,
					seat_id: seatId,
					date: date
				},
				success: function(response) {
					$container.removeClass('loading');
					if (response.success) {
						renderTimeSlots(response.data);
					}
				}
			});
		}

		/**
		 * Render time slots
		 */
		function renderTimeSlots(slots) {
			var displayStyle = $widget.data('time-display') || 'grid';
			var $container = $widget.find('.time-slots-grid, .time-slots-list, .time-slots-dropdown');

			if (!slots || !slots.length) {
				$container.html('<div class="no-slots-message">' + bkxElementor.i18n.noSlots + '</div>');
				return;
			}

			if ($container.is('select')) {
				$container.empty().append('<option value="">' + bkxElementor.i18n.selectTime + '</option>');
				$.each(slots, function(i, slot) {
					var disabled = !slot.available ? ' disabled' : '';
					$container.append('<option value="' + slot.time + '"' + disabled + '>' + slot.display + '</option>');
				});
			} else {
				$container.empty();
				$.each(slots, function(i, slot) {
					var classes = ['time-slot'];
					if (!slot.available) classes.push('unavailable');
					if (slot.time === selectedTime) classes.push('selected');

					$container.append(
						'<div class="' + classes.join(' ') + '" data-time="' + slot.time + '">' +
						slot.display +
						'</div>'
					);
				});
			}
		}

		/**
		 * Format date for display
		 */
		function formatDate(dateStr) {
			var date = new Date(dateStr);
			var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
			return date.toLocaleDateString(undefined, options);
		}

		/**
		 * Validate step
		 */
		function validateStep(step) {
			var $step = $widget.find('.form-step[data-step="' + step + '"]');
			var isValid = true;

			$step.find('[required]').each(function() {
				var $field = $(this);
				var $wrapper = $field.closest('.form-field');

				if (!$field.val()) {
					$wrapper.addClass('error');
					isValid = false;
				} else {
					$wrapper.removeClass('error');
				}
			});

			// Custom validations per step
			switch (step) {
				case 1:
					if (!selectedService) {
						alert(bkxElementor.i18n.selectService || 'Please select a service');
						isValid = false;
					}
					break;
				case 2:
					if (!selectedDate) {
						alert(bkxElementor.i18n.selectDate);
						isValid = false;
					} else if (!selectedTime) {
						alert(bkxElementor.i18n.selectTime);
						isValid = false;
					}
					break;
			}

			return isValid;
		}

		/**
		 * Go to step
		 */
		function goToStep(step) {
			if (step < 1 || step > totalSteps) return;

			currentStep = step;

			// Update steps visibility
			$widget.find('.form-step').removeClass('active');
			$widget.find('.form-step[data-step="' + step + '"]').addClass('active');

			// Update step indicators
			$widget.find('.step-indicator li').each(function(i) {
				var $li = $(this);
				$li.removeClass('active completed');
				if (i + 1 < step) {
					$li.addClass('completed');
				} else if (i + 1 === step) {
					$li.addClass('active');
				}
			});

			// Update summary on last step
			if (step === totalSteps) {
				updateSummary();
			}

			// Scroll to top of form
			$('html, body').animate({
				scrollTop: $widget.offset().top - 50
			}, 300);
		}

		/**
		 * Update booking summary
		 */
		function updateSummary() {
			var $summary = $widget.find('.booking-summary');
			if (!$summary.length) return;

			// Service
			var $selectedService = $widget.find('.service-card.selected, .service-dropdown option:selected, input[name="service_id"]:checked');
			var serviceName = $selectedService.data('name') || $selectedService.text();
			var servicePrice = $selectedService.data('price') || 0;
			$summary.find('.summary-service').text(serviceName);

			// Date and time
			$summary.find('.summary-date').text(formatDate(selectedDate));
			$summary.find('.summary-time').text(selectedTime);

			// Total
			$summary.find('.summary-total').text('$' + parseFloat(servicePrice).toFixed(2));
		}

		/**
		 * Submit booking
		 */
		function submitBooking() {
			var $form = $widget.find('form');
			var $submitBtn = $widget.find('.btn-submit');

			$submitBtn.prop('disabled', true).text(bkxElementor.i18n.loading);
			$widget.addClass('submitting');

			$.ajax({
				url: bkxElementor.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=bkx_submit_booking&nonce=' + bkxElementor.nonce,
				success: function(response) {
					$widget.removeClass('submitting');
					$submitBtn.prop('disabled', false).text(bkxElementor.i18n.bookNow);

					if (response.success) {
						showSuccess(response.data);
					} else {
						showError(response.data.message || 'An error occurred');
					}
				},
				error: function() {
					$widget.removeClass('submitting');
					$submitBtn.prop('disabled', false).text(bkxElementor.i18n.bookNow);
					showError('An error occurred. Please try again.');
				}
			});
		}

		/**
		 * Show success message
		 */
		function showSuccess(data) {
			$widget.find('.form-steps-container').hide();
			$widget.find('.step-indicator').hide();

			var $success = $('<div class="booking-success">' +
				'<div class="success-icon">&#10003;</div>' +
				'<h3>' + bkxElementor.i18n.bookingSuccess + '</h3>' +
				'<p>Booking ID: #' + data.booking_id + '</p>' +
				'</div>');

			$widget.append($success);
		}

		/**
		 * Show error message
		 */
		function showError(message) {
			var $error = $('<div class="booking-error">' +
				'<p>' + message + '</p>' +
				'</div>');

			$widget.find('.form-navigation').before($error);

			setTimeout(function() {
				$error.fadeOut(function() {
					$(this).remove();
				});
			}, 5000);
		}
	};

	/**
	 * Staff Carousel Widget Handler
	 */
	var StaffCarouselWidget = function($scope) {
		var $widget = $scope.find('.bkx-staff-carousel');
		if (!$widget.length) return;

		var $track = $widget.find('.carousel-track');
		var $slides = $track.find('.carousel-slide');
		var slidesToShow = parseInt($widget.data('slides')) || 3;
		var autoplay = $widget.data('autoplay') === 'yes';
		var currentIndex = 0;
		var slideWidth = 100 / slidesToShow;
		var autoplayInterval;

		// Set slide widths
		$slides.css('width', slideWidth + '%');

		// Navigation
		$widget.on('click', '.carousel-prev', function(e) {
			e.preventDefault();
			prev();
		});

		$widget.on('click', '.carousel-next', function(e) {
			e.preventDefault();
			next();
		});

		// Autoplay
		if (autoplay) {
			startAutoplay();

			$widget.on('mouseenter', function() {
				stopAutoplay();
			}).on('mouseleave', function() {
				startAutoplay();
			});
		}

		// Touch support
		var touchStartX = 0;
		var touchEndX = 0;

		$track.on('touchstart', function(e) {
			touchStartX = e.originalEvent.touches[0].clientX;
		});

		$track.on('touchend', function(e) {
			touchEndX = e.originalEvent.changedTouches[0].clientX;
			handleSwipe();
		});

		function handleSwipe() {
			var diff = touchStartX - touchEndX;
			if (Math.abs(diff) > 50) {
				if (diff > 0) {
					next();
				} else {
					prev();
				}
			}
		}

		function next() {
			var maxIndex = $slides.length - slidesToShow;
			if (currentIndex < maxIndex) {
				currentIndex++;
				updatePosition();
			}
		}

		function prev() {
			if (currentIndex > 0) {
				currentIndex--;
				updatePosition();
			}
		}

		function updatePosition() {
			var offset = -currentIndex * slideWidth;
			$track.css('transform', 'translateX(' + offset + '%)');
		}

		function startAutoplay() {
			autoplayInterval = setInterval(function() {
				if (currentIndex >= $slides.length - slidesToShow) {
					currentIndex = 0;
				} else {
					currentIndex++;
				}
				updatePosition();
			}, 5000);
		}

		function stopAutoplay() {
			clearInterval(autoplayInterval);
		}

		// Responsive handling
		$(window).on('resize', function() {
			var width = $(window).width();
			if (width < 768) {
				slidesToShow = 1;
			} else if (width < 992) {
				slidesToShow = 2;
			} else {
				slidesToShow = parseInt($widget.data('slides')) || 3;
			}
			slideWidth = 100 / slidesToShow;
			$slides.css('width', slideWidth + '%');
			updatePosition();
		}).trigger('resize');
	};

	/**
	 * Availability Calendar Widget Handler
	 */
	var AvailabilityWidget = function($scope) {
		var $widget = $scope.find('.bkx-availability-calendar');
		if (!$widget.length) return;

		var serviceId = $widget.data('service');
		var seatId = $widget.data('seat');
		var monthsAhead = parseInt($widget.data('months')) || 2;
		var currentMonth = new Date();
		var endMonth = new Date();
		endMonth.setMonth(endMonth.getMonth() + monthsAhead);

		// Initialize
		loadAvailability(currentMonth);

		// Navigation
		$widget.on('click', '.nav-prev', function(e) {
			e.preventDefault();
			var today = new Date();
			today.setDate(1);
			if (currentMonth > today) {
				currentMonth.setMonth(currentMonth.getMonth() - 1);
				loadAvailability(currentMonth);
			}
		});

		$widget.on('click', '.nav-next', function(e) {
			e.preventDefault();
			if (currentMonth < endMonth) {
				currentMonth.setMonth(currentMonth.getMonth() + 1);
				loadAvailability(currentMonth);
			}
		});

		function loadAvailability(date) {
			var $calendar = $widget.find('.calendar-days');
			var $monthLabel = $widget.find('.current-month');

			var year = date.getFullYear();
			var month = date.getMonth();
			var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
				'July', 'August', 'September', 'October', 'November', 'December'];

			$monthLabel.text(monthNames[month] + ' ' + year);
			$calendar.addClass('loading');

			$.ajax({
				url: bkxElementor.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_elementor_get_month_availability',
					nonce: bkxElementor.nonce,
					service_id: serviceId,
					seat_id: seatId,
					year: year,
					month: month + 1
				},
				success: function(response) {
					$calendar.removeClass('loading');
					if (response.success) {
						renderCalendar(date, response.data);
					}
				}
			});
		}

		function renderCalendar(date, availability) {
			var $calendar = $widget.find('.calendar-days');
			var year = date.getFullYear();
			var month = date.getMonth();
			var firstDay = new Date(year, month, 1).getDay();
			var daysInMonth = new Date(year, month + 1, 0).getDate();
			var today = new Date();
			today.setHours(0, 0, 0, 0);

			$calendar.empty();

			// Empty cells
			for (var i = 0; i < firstDay; i++) {
				$calendar.append('<div class="bkx-day empty"></div>');
			}

			// Days
			for (var day = 1; day <= daysInMonth; day++) {
				var dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
				var dayDate = new Date(year, month, day);
				var isPast = dayDate < today;

				var classes = ['bkx-day'];

				if (isPast) {
					classes.push('past');
				} else if (availability[dateStr]) {
					var status = availability[dateStr];
					if (status === 'full') {
						classes.push('unavailable');
					} else if (status === 'limited') {
						classes.push('limited');
					} else {
						classes.push('available');
					}
				} else {
					classes.push('available');
				}

				$calendar.append('<div class="' + classes.join(' ') + '" data-date="' + dateStr + '">' + day + '</div>');
			}
		}
	};

	/**
	 * Initialize widgets on frontend
	 */
	$(window).on('elementor/frontend/init', function() {
		elementorFrontend.hooks.addAction('frontend/element_ready/bkx-booking-form.default', BookingFormWidget);
		elementorFrontend.hooks.addAction('frontend/element_ready/bkx-staff-carousel.default', StaffCarouselWidget);
		elementorFrontend.hooks.addAction('frontend/element_ready/bkx-availability.default', AvailabilityWidget);
	});

})(jQuery);
