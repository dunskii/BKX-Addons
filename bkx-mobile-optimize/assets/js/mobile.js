/**
 * Mobile Optimization JavaScript
 *
 * @package BookingX\MobileOptimize
 */

(function() {
	'use strict';

	/**
	 * BKX Mobile
	 */
	var BkxMobile = {
		/**
		 * Initialize
		 */
		init: function() {
			this.detectDevice();
			this.initFloatingCta();
			this.initBottomSheet();
			this.initFormEnhancements();
			this.initSkeletonLoading();
			this.initHapticFeedback();
			this.initAutofill();
		},

		/**
		 * Detect device type
		 */
		detectDevice: function() {
			this.isMobile = bkxMobile.isMobile;
			this.isTablet = bkxMobile.isTablet;
			this.isIos = bkxMobile.isIos;
			this.isAndroid = bkxMobile.isAndroid;
			this.isTouch = 'ontouchstart' in window;
		},

		/**
		 * Initialize floating CTA
		 */
		initFloatingCta: function() {
			if (!bkxMobile.floatingCta) return;

			var cta = document.getElementById('bkx-floating-cta');
			if (!cta) return;

			var lastScrollY = window.scrollY;
			var isVisible = false;

			// Show after scrolling 300px
			window.addEventListener('scroll', function() {
				var currentScrollY = window.scrollY;
				var scrollingDown = currentScrollY > lastScrollY;

				if (currentScrollY > 300 && !isVisible) {
					cta.style.display = 'block';
					cta.classList.add('visible');
					isVisible = true;
				} else if (currentScrollY < 100 && isVisible) {
					cta.style.display = 'none';
					cta.classList.remove('visible');
					isVisible = false;
				}

				// Hide when scrolling down on small screens
				if (window.innerWidth < 600) {
					if (scrollingDown && isVisible) {
						cta.style.transform = 'translateY(100px)';
					} else {
						cta.style.transform = 'translateY(0)';
					}
				}

				lastScrollY = currentScrollY;
			}, { passive: true });

			// Haptic feedback on tap
			cta.addEventListener('click', function() {
				BkxMobile.triggerHaptic('light');
			});
		},

		/**
		 * Initialize bottom sheet
		 */
		initBottomSheet: function() {
			if (!bkxMobile.bottomSheet) return;

			var sheet = document.getElementById('bkx-bottom-sheet');
			if (!sheet) return;

			var backdrop = sheet.querySelector('.bkx-bottom-sheet-backdrop');
			var container = sheet.querySelector('.bkx-bottom-sheet-container');
			var handle = sheet.querySelector('.bkx-bottom-sheet-handle');
			var closeBtn = sheet.querySelector('.bkx-bottom-sheet-close');

			// Close on backdrop click
			backdrop.addEventListener('click', function() {
				BkxMobile.closeBottomSheet();
			});

			// Close on button click
			closeBtn.addEventListener('click', function() {
				BkxMobile.closeBottomSheet();
			});

			// Swipe to dismiss
			var startY = 0;
			var currentY = 0;

			handle.addEventListener('touchstart', function(e) {
				startY = e.touches[0].clientY;
			}, { passive: true });

			handle.addEventListener('touchmove', function(e) {
				currentY = e.touches[0].clientY;
				var diff = currentY - startY;

				if (diff > 0) {
					container.style.transform = 'translateY(' + diff + 'px)';
				}
			}, { passive: true });

			handle.addEventListener('touchend', function() {
				var diff = currentY - startY;

				if (diff > 100) {
					BkxMobile.closeBottomSheet();
				} else {
					container.style.transform = 'translateY(0)';
				}
			});

			// Expose methods
			this.bottomSheet = {
				open: this.openBottomSheet.bind(this),
				close: this.closeBottomSheet.bind(this),
				setContent: this.setBottomSheetContent.bind(this)
			};
		},

		/**
		 * Open bottom sheet
		 */
		openBottomSheet: function(title, content, footer) {
			var sheet = document.getElementById('bkx-bottom-sheet');
			if (!sheet) return;

			sheet.querySelector('.bkx-bottom-sheet-title').textContent = title || '';
			sheet.querySelector('.bkx-bottom-sheet-content').innerHTML = content || '';
			sheet.querySelector('.bkx-bottom-sheet-footer').innerHTML = footer || '';

			sheet.style.display = 'block';
			requestAnimationFrame(function() {
				sheet.classList.add('visible');
			});

			document.body.style.overflow = 'hidden';
			this.triggerHaptic('light');
		},

		/**
		 * Close bottom sheet
		 */
		closeBottomSheet: function() {
			var sheet = document.getElementById('bkx-bottom-sheet');
			if (!sheet) return;

			sheet.classList.remove('visible');
			document.body.style.overflow = '';

			setTimeout(function() {
				sheet.style.display = 'none';
			}, 300);
		},

		/**
		 * Set bottom sheet content
		 */
		setBottomSheetContent: function(content) {
			var sheet = document.getElementById('bkx-bottom-sheet');
			if (!sheet) return;

			sheet.querySelector('.bkx-bottom-sheet-content').innerHTML = content;
		},

		/**
		 * Initialize form enhancements
		 */
		initFormEnhancements: function() {
			// Auto-scroll on focus
			document.querySelectorAll('.bkx-mobile-form input, .bkx-mobile-form select, .bkx-mobile-form textarea').forEach(function(input) {
				input.addEventListener('focus', function() {
					setTimeout(function() {
						input.scrollIntoView({ behavior: 'smooth', block: 'center' });
					}, 300);
				});
			});

			// Format phone number
			document.querySelectorAll('input[type="tel"]').forEach(function(input) {
				input.addEventListener('input', function() {
					var value = this.value.replace(/\D/g, '');
					if (value.length > 10) value = value.slice(0, 10);
					if (value.length > 6) {
						this.value = '(' + value.slice(0, 3) + ') ' + value.slice(3, 6) + '-' + value.slice(6);
					} else if (value.length > 3) {
						this.value = '(' + value.slice(0, 3) + ') ' + value.slice(3);
					} else if (value.length > 0) {
						this.value = '(' + value;
					}
				});
			});

			// Click to call
			if (bkxMobile.clickToCall !== false) {
				document.querySelectorAll('a[href^="tel:"]').forEach(function(link) {
					link.addEventListener('click', function() {
						BkxMobile.triggerHaptic('light');
					});
				});
			}
		},

		/**
		 * Initialize skeleton loading
		 */
		initSkeletonLoading: function() {
			if (!bkxMobile.skeletonLoading) return;

			// Replace content with skeleton on AJAX
			document.addEventListener('bkx-ajax-start', function(e) {
				var target = e.detail.target;
				if (target) {
					target.classList.add('bkx-loading');
					var skeleton = BkxMobile.createSkeleton(e.detail.type || 'card');
					target.innerHTML = skeleton;
				}
			});

			document.addEventListener('bkx-ajax-complete', function(e) {
				var target = e.detail.target;
				if (target) {
					target.classList.remove('bkx-loading');
				}
			});
		},

		/**
		 * Create skeleton HTML
		 */
		createSkeleton: function(type) {
			var templates = {
				card: '<div class="bkx-skeleton bkx-skeleton-card"><div class="bkx-skeleton-text"></div><div class="bkx-skeleton-text short"></div></div>',
				list: '<div class="bkx-skeleton bkx-skeleton-list"><div class="bkx-skeleton-text"></div><div class="bkx-skeleton-text"></div><div class="bkx-skeleton-text short"></div></div>',
				form: '<div class="bkx-skeleton bkx-skeleton-form"><div class="bkx-skeleton-input"></div><div class="bkx-skeleton-input"></div><div class="bkx-skeleton-button"></div></div>'
			};

			return templates[type] || templates.card;
		},

		/**
		 * Initialize haptic feedback
		 */
		initHapticFeedback: function() {
			if (!bkxMobile.hapticFeedback) return;

			// Add haptic to buttons
			document.querySelectorAll('.bkx-mobile-form button, .bkx-btn').forEach(function(btn) {
				btn.addEventListener('click', function() {
					BkxMobile.triggerHaptic('light');
				});
			});

			// Add haptic to selections
			document.querySelectorAll('.bkx-service-card, .bkx-time-slot').forEach(function(item) {
				item.addEventListener('click', function() {
					BkxMobile.triggerHaptic('selection');
				});
			});
		},

		/**
		 * Trigger haptic feedback
		 */
		triggerHaptic: function(type) {
			if (!bkxMobile.hapticFeedback) return;

			// Use Vibration API
			if ('vibrate' in navigator) {
				switch (type) {
					case 'light':
						navigator.vibrate(10);
						break;
					case 'medium':
						navigator.vibrate(20);
						break;
					case 'heavy':
						navigator.vibrate(30);
						break;
					case 'success':
						navigator.vibrate([10, 50, 10]);
						break;
					case 'error':
						navigator.vibrate([30, 30, 30]);
						break;
					case 'selection':
						navigator.vibrate(5);
						break;
				}
			}
		},

		/**
		 * Initialize smart autofill
		 */
		initAutofill: function() {
			if (!bkxMobile.smartAutofill) return;

			// Check for saved data
			var savedData = localStorage.getItem('bkx_booking_data');
			if (!savedData) return;

			try {
				var data = JSON.parse(savedData);

				// Pre-fill form fields
				if (data.name) {
					var nameField = document.querySelector('input[name="name"], input[name="customer_name"]');
					if (nameField && !nameField.value) nameField.value = data.name;
				}

				if (data.email) {
					var emailField = document.querySelector('input[name="email"], input[name="customer_email"]');
					if (emailField && !emailField.value) emailField.value = data.email;
				}

				if (data.phone) {
					var phoneField = document.querySelector('input[name="phone"], input[name="customer_phone"]');
					if (phoneField && !phoneField.value) phoneField.value = data.phone;
				}
			} catch (e) {
				// Invalid JSON, ignore
			}
		},

		/**
		 * Save form data for autofill
		 */
		saveFormData: function(form) {
			if (!bkxMobile.smartAutofill) return;

			var data = {};
			var formData = new FormData(form);

			['name', 'customer_name', 'email', 'customer_email', 'phone', 'customer_phone'].forEach(function(key) {
				if (formData.get(key)) {
					data[key.replace('customer_', '')] = formData.get(key);
				}
			});

			if (Object.keys(data).length > 0) {
				localStorage.setItem('bkx_booking_data', JSON.stringify(data));
			}
		},

		/**
		 * One-tap booking
		 */
		oneTapBook: function(serviceId, resourceId, date, time) {
			if (!bkxMobile.oneTapBooking) return;

			this.triggerHaptic('light');

			fetch(bkxMobile.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: new URLSearchParams({
					action: 'bkx_mobile_one_tap_book',
					nonce: bkxMobile.nonce,
					service_id: serviceId,
					resource_id: resourceId || '',
					date: date,
					time: time
				})
			})
			.then(function(response) { return response.json(); })
			.then(function(data) {
				if (data.success) {
					BkxMobile.triggerHaptic('success');
					window.location.href = data.data.redirect_url;
				} else {
					BkxMobile.triggerHaptic('error');
					alert(data.data.message);
				}
			})
			.catch(function(error) {
				BkxMobile.triggerHaptic('error');
				console.error('One-tap booking error:', error);
			});
		}
	};

	/**
	 * Initialize when DOM is ready
	 */
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function() {
			BkxMobile.init();
		});
	} else {
		BkxMobile.init();
	}

	// Expose for external use
	window.BkxMobile = BkxMobile;

})();
