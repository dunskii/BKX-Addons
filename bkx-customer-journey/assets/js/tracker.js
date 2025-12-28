/**
 * BookingX Customer Journey - Frontend Tracker
 *
 * @package BookingX\CustomerJourney
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKX_CJ_Tracker = {
		sessionId: null,
		lastActivity: null,
		pageStartTime: null,
		trackedEvents: new Set(),

		init: function() {
			this.sessionId = bkxCJTracker.sessionId;
			this.pageStartTime = Date.now();
			this.lastActivity = Date.now();

			this.trackPageView();
			this.bindEvents();
			this.setupVisibilityTracking();
		},

		bindEvents: function() {
			const self = this;

			// Track service views
			$(document).on('click', '.bkx-service-item, [data-bkx-service]', function() {
				const serviceId = $(this).data('bkx-service') || $(this).data('service-id');
				if (serviceId) {
					self.track('service_view', { service_id: serviceId });
				}
			});

			// Track staff views
			$(document).on('click', '.bkx-staff-item, [data-bkx-staff]', function() {
				const staffId = $(this).data('bkx-staff') || $(this).data('staff-id');
				if (staffId) {
					self.track('staff_view', { staff_id: staffId });
				}
			});

			// Track widget open
			$(document).on('click', '.bkx-booking-widget-trigger, [data-bkx-widget]', function() {
				self.track('widget_open');
			});

			// Track widget interactions
			$(document).on('click', '.bkx-booking-widget .bkx-btn, .bkx-booking-form button', function() {
				const action = $(this).data('action') || $(this).text().trim().substring(0, 30);
				self.track('widget_interact', { action: action });
			});

			// Track form start
			$(document).on('focus', '.bkx-booking-form input, .bkx-booking-form select', function() {
				if (!self.trackedEvents.has('form_start')) {
					self.track('form_start');
					self.trackedEvents.add('form_start');
				}
			});

			// Track form steps
			$(document).on('click', '.bkx-form-next, .bkx-step-indicator', function() {
				const step = $(this).data('step') || 'next';
				self.track('form_step', { step: step });
			});

			// Track booking attempt (form submission)
			$(document).on('submit', '.bkx-booking-form, [data-bkx-booking-form]', function() {
				self.track('booking_attempt');
			});

			// Track links from emails (UTM parameters)
			if (this.hasEmailUtm()) {
				this.track('email_click', this.getUtmParams());
			}

			// Track social clicks
			if (this.hasSocialReferrer()) {
				this.track('social_click', { source: this.getSocialSource() });
			}

			// Track scroll depth
			this.trackScrollDepth();

			// Track time on page
			this.trackTimeOnPage();
		},

		trackPageView: function() {
			this.track('page_view', {
				title: document.title,
				path: window.location.pathname
			});
		},

		track: function(type, data) {
			const self = this;
			data = data || {};

			$.ajax({
				url: bkxCJTracker.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_cj_track',
					nonce: bkxCJTracker.nonce,
					session_id: this.sessionId,
					type: type,
					data: data,
					page_url: window.location.href,
					referrer: document.referrer
				},
				success: function(response) {
					if (response.success) {
						self.lastActivity = Date.now();
					}
				}
			});
		},

		trackScrollDepth: function() {
			const self = this;
			const depths = [25, 50, 75, 100];
			const trackedDepths = new Set();

			$(window).on('scroll', $.throttle(1000, function() {
				const scrollTop = $(window).scrollTop();
				const docHeight = $(document).height() - $(window).height();
				const scrollPercent = Math.round((scrollTop / docHeight) * 100);

				depths.forEach(function(depth) {
					if (scrollPercent >= depth && !trackedDepths.has(depth)) {
						trackedDepths.add(depth);
						// Only track significant scroll events
						if (depth >= 50) {
							self.track('widget_interact', { action: 'scroll_' + depth });
						}
					}
				});
			}));
		},

		trackTimeOnPage: function() {
			const self = this;

			// Track when user leaves
			$(window).on('beforeunload', function() {
				const timeOnPage = Math.round((Date.now() - self.pageStartTime) / 1000);

				// Check if they abandoned a form
				if (self.trackedEvents.has('form_start') && !self.trackedEvents.has('booking_attempt')) {
					// Use sendBeacon for reliable tracking on page unload
					if (navigator.sendBeacon) {
						const formData = new FormData();
						formData.append('action', 'bkx_cj_track');
						formData.append('nonce', bkxCJTracker.nonce);
						formData.append('session_id', self.sessionId);
						formData.append('type', 'form_abandon');
						formData.append('data[time_on_page]', timeOnPage);
						formData.append('page_url', window.location.href);
						formData.append('referrer', document.referrer);

						navigator.sendBeacon(bkxCJTracker.ajaxUrl, formData);
					}
				}
			});
		},

		setupVisibilityTracking: function() {
			const self = this;

			document.addEventListener('visibilitychange', function() {
				if (document.hidden) {
					// User switched tabs
					self.lastActivity = Date.now();
				} else {
					// User came back
					const awayTime = Date.now() - self.lastActivity;
					if (awayTime > 30 * 60 * 1000) { // 30 minutes
						// Treat as new session start
						self.track('return_visit', { away_time: Math.round(awayTime / 1000) });
					}
				}
			});
		},

		hasEmailUtm: function() {
			const params = new URLSearchParams(window.location.search);
			return params.get('utm_medium') === 'email' || params.get('utm_source')?.includes('email');
		},

		hasSocialReferrer: function() {
			const socialDomains = ['facebook.com', 'twitter.com', 't.co', 'instagram.com',
				'linkedin.com', 'pinterest.com', 'youtube.com', 'tiktok.com'];
			const referrer = document.referrer.toLowerCase();

			return socialDomains.some(domain => referrer.includes(domain));
		},

		getSocialSource: function() {
			const referrer = document.referrer.toLowerCase();
			const mapping = {
				'facebook.com': 'facebook',
				'twitter.com': 'twitter',
				't.co': 'twitter',
				'instagram.com': 'instagram',
				'linkedin.com': 'linkedin',
				'pinterest.com': 'pinterest',
				'youtube.com': 'youtube',
				'tiktok.com': 'tiktok'
			};

			for (const [domain, source] of Object.entries(mapping)) {
				if (referrer.includes(domain)) {
					return source;
				}
			}

			return 'unknown';
		},

		getUtmParams: function() {
			const params = new URLSearchParams(window.location.search);
			return {
				source: params.get('utm_source') || '',
				medium: params.get('utm_medium') || '',
				campaign: params.get('utm_campaign') || '',
				content: params.get('utm_content') || ''
			};
		}
	};

	// Simple throttle implementation if not available
	if (!$.throttle) {
		$.throttle = function(delay, callback) {
			let lastCall = 0;
			return function() {
				const now = Date.now();
				if (now - lastCall >= delay) {
					lastCall = now;
					callback.apply(this, arguments);
				}
			};
		};
	}

	$(document).ready(function() {
		BKX_CJ_Tracker.init();
	});

})(jQuery);
