/**
 * GDPR Compliance - Frontend JavaScript
 *
 * @package BookingX\GdprCompliance
 */

/* global jQuery, bkxGdpr */
(function($) {
	'use strict';

	const BkxCookies = {
		init: function() {
			this.visitorId = this.getVisitorId();
			this.bindEvents();
		},

		bindEvents: function() {
			// Cookie banner
			$(document).on('click', '#bkx-cookie-accept-all', this.acceptAll.bind(this));
			$(document).on('click', '#bkx-cookie-reject-all', this.rejectAll.bind(this));
			$(document).on('click', '#bkx-cookie-customize', this.showPreferences);
			$(document).on('click', '#bkx-cookie-save-preferences', this.savePreferences.bind(this));

			// Request form
			$(document).on('submit', '#bkx-gdpr-request-form', this.submitRequest);
		},

		getVisitorId: function() {
			let visitorId = this.getCookie('bkx_visitor_id');
			if (!visitorId) {
				visitorId = 'bkx_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
				this.setCookie('bkx_visitor_id', visitorId, 365);
			}
			return visitorId;
		},

		acceptAll: function(e) {
			e.preventDefault();
			this.saveConsent({
				necessary: true,
				functional: true,
				analytics: true,
				marketing: true
			});
		},

		rejectAll: function(e) {
			e.preventDefault();
			this.saveConsent({
				necessary: true,
				functional: false,
				analytics: false,
				marketing: false
			});
		},

		showPreferences: function(e) {
			e.preventDefault();
			$('#bkx-cookie-preferences').slideToggle();
		},

		savePreferences: function(e) {
			e.preventDefault();

			const consents = {
				necessary: true,
				functional: $('input[data-category="functional"]').is(':checked'),
				analytics: $('input[data-category="analytics"]').is(':checked'),
				marketing: $('input[data-category="marketing"]').is(':checked')
			};

			this.saveConsent(consents);
		},

		saveConsent: function(consents) {
			const self = this;

			// Save to cookie
			this.setCookie(bkxGdpr.cookieName, JSON.stringify(consents), bkxGdpr.cookieExpiry);

			// Save to server
			$.ajax({
				url: bkxGdpr.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_gdpr_save_cookie_consent',
					nonce: bkxGdpr.nonce,
					visitor_id: self.visitorId,
					functional: consents.functional ? 1 : 0,
					analytics: consents.analytics ? 1 : 0,
					marketing: consents.marketing ? 1 : 0
				},
				success: function() {
					$('#bkx-cookie-banner').slideUp(300, function() {
						$(this).remove();
					});

					// Trigger events for other scripts
					$(document).trigger('bkx_cookie_consent_saved', [consents]);

					// Enable/disable tracking based on consent
					self.applyConsent(consents);
				}
			});
		},

		applyConsent: function(consents) {
			if (consents.analytics) {
				$(document).trigger('bkx_enable_analytics');
			} else {
				$(document).trigger('bkx_disable_analytics');
			}

			if (consents.marketing) {
				$(document).trigger('bkx_enable_marketing');
			} else {
				$(document).trigger('bkx_disable_marketing');
			}
		},

		submitRequest: function(e) {
			e.preventDefault();

			const $form = $(this);
			const $submit = $form.find('.bkx-gdpr-submit-btn');
			const $message = $form.find('.bkx-gdpr-form-message');

			$submit.prop('disabled', true).text(bkxGdpr.i18n.processing || 'Processing...');
			$message.hide().removeClass('success error');

			$.ajax({
				url: bkxGdpr.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_gdpr_submit_request',
					nonce: bkxGdpr.nonce,
					email: $form.find('#bkx-gdpr-request-email').val(),
					request_type: $form.find('#bkx-gdpr-request-type').val()
				},
				success: function(response) {
					$submit.prop('disabled', false).text(bkxGdpr.i18n.submit || 'Submit Request');

					if (response.success) {
						$message.addClass('success').text(response.data.message).show();
						$form[0].reset();
					} else {
						$message.addClass('error').text(response.data.message || 'An error occurred.').show();
					}
				},
				error: function() {
					$submit.prop('disabled', false).text(bkxGdpr.i18n.submit || 'Submit Request');
					$message.addClass('error').text('An error occurred. Please try again.').show();
				}
			});
		},

		getCookie: function(name) {
			const value = '; ' + document.cookie;
			const parts = value.split('; ' + name + '=');
			if (parts.length === 2) {
				return parts.pop().split(';').shift();
			}
			return null;
		},

		setCookie: function(name, value, days) {
			let expires = '';
			if (days) {
				const date = new Date();
				date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
				expires = '; expires=' + date.toUTCString();
			}
			document.cookie = name + '=' + (value || '') + expires + '; path=/; SameSite=Lax';
		}
	};

	$(document).ready(function() {
		BkxCookies.init();
	});

})(jQuery);
