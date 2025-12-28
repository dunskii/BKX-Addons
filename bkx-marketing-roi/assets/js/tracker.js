/**
 * BookingX Marketing ROI - Frontend UTM Tracker
 *
 * @package BookingX\MarketingROI
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKX_ROI_Tracker = {
		sessionId: null,
		tracked: false,

		init: function() {
			this.sessionId = bkxROITracker.sessionId;
			this.trackVisit();
		},

		trackVisit: function() {
			if (this.tracked) return;

			const utmParams = this.getUTMParams();

			// Only track if at least utm_source is present
			if (!utmParams.utm_source) {
				return;
			}

			const self = this;

			$.ajax({
				url: bkxROITracker.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_roi_track',
					nonce: bkxROITracker.nonce,
					session_id: this.sessionId,
					utm_source: utmParams.utm_source,
					utm_medium: utmParams.utm_medium,
					utm_campaign: utmParams.utm_campaign,
					utm_content: utmParams.utm_content,
					utm_term: utmParams.utm_term,
					landing_page: window.location.href,
					referrer: document.referrer
				},
				success: function(response) {
					if (response.success) {
						self.tracked = true;
						self.storeUTMParams(utmParams);
					}
				}
			});
		},

		getUTMParams: function() {
			const params = new URLSearchParams(window.location.search);

			return {
				utm_source: params.get('utm_source') || '',
				utm_medium: params.get('utm_medium') || '',
				utm_campaign: params.get('utm_campaign') || '',
				utm_content: params.get('utm_content') || '',
				utm_term: params.get('utm_term') || ''
			};
		},

		storeUTMParams: function(params) {
			// Store UTM params in sessionStorage for conversion tracking
			try {
				sessionStorage.setItem('bkx_roi_utm', JSON.stringify(params));
			} catch (e) {
				// sessionStorage not available
			}
		}
	};

	$(document).ready(function() {
		BKX_ROI_Tracker.init();
	});

})(jQuery);
