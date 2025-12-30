/**
 * Touch Gesture Handling
 *
 * @package BookingX\MobileOptimize
 */

(function() {
	'use strict';

	/**
	 * Gesture Handler
	 */
	var BkxGestures = {
		config: {
			swipeThreshold: 50,
			swipeVelocity: 0.3,
			tapDelay: 300,
			longPressDelay: 500
		},

		/**
		 * Initialize
		 */
		init: function() {
			this.initSwipeCalendar();
			this.initPullToRefresh();
		},

		/**
		 * Initialize swipe calendar
		 */
		initSwipeCalendar: function() {
			var calendars = document.querySelectorAll('.bkx-calendar, .bkx-availability-calendar');

			calendars.forEach(function(calendar) {
				var startX = 0;
				var startY = 0;
				var startTime = 0;
				var distX = 0;
				var distY = 0;

				calendar.addEventListener('touchstart', function(e) {
					startX = e.touches[0].clientX;
					startY = e.touches[0].clientY;
					startTime = Date.now();
				}, { passive: true });

				calendar.addEventListener('touchmove', function(e) {
					if (!startX || !startY) return;

					distX = e.touches[0].clientX - startX;
					distY = e.touches[0].clientY - startY;

					// If horizontal swipe is dominant
					if (Math.abs(distX) > Math.abs(distY)) {
						e.preventDefault();
						calendar.style.transform = 'translateX(' + (distX * 0.3) + 'px)';
					}
				}, { passive: false });

				calendar.addEventListener('touchend', function() {
					var elapsed = Date.now() - startTime;
					var velocity = Math.abs(distX) / elapsed;

					calendar.style.transform = '';

					// Check if swipe threshold met
					if (Math.abs(distX) > BkxGestures.config.swipeThreshold || velocity > BkxGestures.config.swipeVelocity) {
						if (distX > 0) {
							// Swipe right - previous month
							BkxGestures.navigateCalendar(calendar, 'prev');
						} else {
							// Swipe left - next month
							BkxGestures.navigateCalendar(calendar, 'next');
						}
					}

					// Reset
					startX = 0;
					startY = 0;
					distX = 0;
					distY = 0;
				});
			});
		},

		/**
		 * Navigate calendar
		 */
		navigateCalendar: function(calendar, direction) {
			// Haptic feedback
			if (window.BkxMobile) {
				window.BkxMobile.triggerHaptic('selection');
			}

			// Find and click navigation button
			var selector = direction === 'prev' ? '.bkx-calendar-prev, .prev-month' : '.bkx-calendar-next, .next-month';
			var btn = calendar.querySelector(selector);

			if (btn) {
				btn.click();
			}

			// Dispatch custom event
			calendar.dispatchEvent(new CustomEvent('bkx-calendar-navigate', {
				detail: { direction: direction }
			}));
		},

		/**
		 * Initialize pull to refresh
		 */
		initPullToRefresh: function() {
			var container = document.querySelector('.bkx-booking-form, .bkx-services-list');
			if (!container) return;

			var indicator = document.createElement('div');
			indicator.className = 'bkx-pull-indicator';
			indicator.innerHTML = '<div class="bkx-spinner"></div>';
			document.body.insertBefore(indicator, document.body.firstChild);

			var startY = 0;
			var pulling = false;
			var maxPull = 120;

			container.addEventListener('touchstart', function(e) {
				if (window.scrollY === 0) {
					startY = e.touches[0].clientY;
				}
			}, { passive: true });

			container.addEventListener('touchmove', function(e) {
				if (!startY) return;

				var currentY = e.touches[0].clientY;
				var diff = currentY - startY;

				if (diff > 0 && window.scrollY === 0) {
					pulling = true;
					var pullDistance = Math.min(diff / 2.5, maxPull);

					indicator.style.transform = 'translateY(' + (pullDistance - 60) + 'px)';

					if (pullDistance >= 60) {
						indicator.classList.add('pulling');
					}
				}
			}, { passive: true });

			container.addEventListener('touchend', function() {
				if (pulling && indicator.classList.contains('pulling')) {
					// Trigger refresh
					if (window.BkxMobile) {
						window.BkxMobile.triggerHaptic('medium');
					}

					// Dispatch refresh event
					container.dispatchEvent(new CustomEvent('bkx-pull-refresh'));

					// Reset after delay
					setTimeout(function() {
						indicator.classList.remove('pulling');
						indicator.style.transform = 'translateY(-100%)';
					}, 1000);
				} else {
					indicator.classList.remove('pulling');
					indicator.style.transform = 'translateY(-100%)';
				}

				startY = 0;
				pulling = false;
			});
		},

		/**
		 * Create swipeable list
		 */
		createSwipeableList: function(container, options) {
			var items = container.querySelectorAll(options.itemSelector);

			items.forEach(function(item) {
				var startX = 0;
				var currentX = 0;
				var threshold = options.threshold || 80;

				item.addEventListener('touchstart', function(e) {
					startX = e.touches[0].clientX;
					item.style.transition = 'none';
				}, { passive: true });

				item.addEventListener('touchmove', function(e) {
					currentX = e.touches[0].clientX;
					var diff = currentX - startX;

					// Limit swipe distance
					if (Math.abs(diff) < 150) {
						item.style.transform = 'translateX(' + diff + 'px)';
					}
				}, { passive: true });

				item.addEventListener('touchend', function() {
					var diff = currentX - startX;
					item.style.transition = 'transform 0.2s';

					if (Math.abs(diff) > threshold) {
						// Trigger action
						var action = diff > 0 ? 'swipe-right' : 'swipe-left';
						item.dispatchEvent(new CustomEvent('bkx-swipe', {
							detail: { direction: action }
						}));

						if (window.BkxMobile) {
							window.BkxMobile.triggerHaptic('medium');
						}
					}

					// Reset position
					item.style.transform = 'translateX(0)';
					startX = 0;
					currentX = 0;
				});
			});
		},

		/**
		 * Handle long press
		 */
		handleLongPress: function(element, callback) {
			var timer = null;
			var delay = this.config.longPressDelay;

			element.addEventListener('touchstart', function(e) {
				timer = setTimeout(function() {
					if (window.BkxMobile) {
						window.BkxMobile.triggerHaptic('heavy');
					}
					callback(e);
				}, delay);
			}, { passive: true });

			element.addEventListener('touchend', function() {
				clearTimeout(timer);
			});

			element.addEventListener('touchmove', function() {
				clearTimeout(timer);
			}, { passive: true });
		}
	};

	/**
	 * Initialize when DOM is ready
	 */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			BkxGestures.init();
		});
	} else {
		BkxGestures.init();
	}

	// Expose for external use
	window.BkxGestures = BkxGestures;

})();
