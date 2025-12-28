/**
 * Class Bookings Frontend JavaScript
 *
 * @package BookingX\ClassBookings
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const BKXClassFrontend = {
		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Book class button.
			$(document).on('click', '.bkx-book-class', this.openBookingModal);

			// Calendar session click.
			$(document).on('click', '.bkx-cal-session', this.openSessionDetail);

			// Close modal.
			$(document).on('click', '.bkx-modal-close', this.closeModal);
			$(document).on('click', '.bkx-modal', this.closeModalOnOverlay);

			// Cancel booking button.
			$(document).on('click', '.bkx-cancel-booking', this.closeModal);

			// Submit booking form.
			$(document).on('submit', '#bkx-class-booking-form', this.submitBooking);

			// Join waitlist.
			$(document).on('click', '.bkx-join-waitlist', this.joinWaitlist);

			// Escape key closes modal.
			$(document).on('keyup', function(e) {
				if (e.key === 'Escape') {
					BKXClassFrontend.closeModal();
				}
			});
		},

		/**
		 * Open booking modal.
		 *
		 * @param {Event} e Click event.
		 */
		openBookingModal: function(e) {
			e.preventDefault();

			const sessionId = $(this).data('session');

			// Show loading.
			BKXClassFrontend.showModal('<div class="bkx-loading">' + bkxClassFrontend.i18n.loading + '</div>');

			// Fetch booking form.
			$.post(bkxClassFrontend.ajaxurl, {
				action: 'bkx_get_booking_form',
				nonce: bkxClassFrontend.nonce,
				session_id: sessionId
			}, function(response) {
				if (response.success) {
					$('#bkx-session-modal .bkx-modal-body').html(response.data.html);
				} else {
					$('#bkx-session-modal .bkx-modal-body').html(
						'<p class="bkx-error">' + (response.data || bkxClassFrontend.i18n.error) + '</p>'
					);
				}
			});
		},

		/**
		 * Open session detail.
		 *
		 * @param {Event} e Click event.
		 */
		openSessionDetail: function(e) {
			e.preventDefault();

			const sessionId = $(this).data('session');

			// Show loading.
			BKXClassFrontend.showModal('<div class="bkx-loading">' + bkxClassFrontend.i18n.loading + '</div>');

			// Fetch session details.
			$.post(bkxClassFrontend.ajaxurl, {
				action: 'bkx_get_session_detail',
				nonce: bkxClassFrontend.nonce,
				session_id: sessionId
			}, function(response) {
				if (response.success) {
					$('#bkx-session-modal .bkx-modal-body').html(response.data.html);
				} else {
					$('#bkx-session-modal .bkx-modal-body').html(
						'<p class="bkx-error">' + (response.data || bkxClassFrontend.i18n.error) + '</p>'
					);
				}
			});
		},

		/**
		 * Show modal.
		 *
		 * @param {string} content Modal content HTML.
		 */
		showModal: function(content) {
			let $modal = $('#bkx-session-modal');

			if (!$modal.length) {
				$modal = $(
					'<div id="bkx-session-modal" class="bkx-modal">' +
					'<div class="bkx-modal-content">' +
					'<button class="bkx-modal-close">&times;</button>' +
					'<div class="bkx-modal-body"></div>' +
					'</div></div>'
				).appendTo('body');
			}

			$modal.find('.bkx-modal-body').html(content);
			$modal.fadeIn(200);
			$('body').addClass('bkx-modal-open');
		},

		/**
		 * Close modal.
		 */
		closeModal: function() {
			$('#bkx-session-modal').fadeOut(200);
			$('body').removeClass('bkx-modal-open');
		},

		/**
		 * Close modal on overlay click.
		 *
		 * @param {Event} e Click event.
		 */
		closeModalOnOverlay: function(e) {
			if ($(e.target).hasClass('bkx-modal')) {
				BKXClassFrontend.closeModal();
			}
		},

		/**
		 * Submit booking form.
		 *
		 * @param {Event} e Submit event.
		 */
		submitBooking: function(e) {
			e.preventDefault();

			const $form = $(this);
			const $submit = $form.find('.bkx-submit-booking');
			const $message = $form.find('.bkx-form-message');

			$submit.prop('disabled', true).text(bkxClassFrontend.i18n.processing);
			$message.hide();

			$.post(bkxClassFrontend.ajaxurl, $form.serialize(), function(response) {
				if (response.success) {
					$message
						.removeClass('error')
						.addClass('success')
						.html(response.data.message)
						.show();

					// Hide form, show success.
					$form.find('.bkx-form-row, .bkx-form-actions').hide();

					// Update availability on page.
					const sessionId = $form.find('[name="session_id"]').val();
					const $sessionItem = $('[data-session="' + sessionId + '"]').closest('.bkx-session-item');

					if (response.data.available <= 0) {
						$sessionItem.addClass('bkx-session-full');
						$sessionItem.find('.bkx-spots-available').text(bkxClassFrontend.i18n.full);
						$sessionItem.find('.bkx-book-class').remove();
					} else {
						$sessionItem.find('.bkx-spots-available').text(
							response.data.available + ' ' + bkxClassFrontend.i18n.spotsLeft
						);
					}

					// Close modal after delay.
					setTimeout(function() {
						BKXClassFrontend.closeModal();
					}, 2000);
				} else {
					$message
						.removeClass('success')
						.addClass('error')
						.html(response.data || bkxClassFrontend.i18n.error)
						.show();
					$submit.prop('disabled', false).text(bkxClassFrontend.i18n.confirmBooking);
				}
			});
		},

		/**
		 * Join waitlist.
		 *
		 * @param {Event} e Click event.
		 */
		joinWaitlist: function(e) {
			e.preventDefault();

			const sessionId = $(this).data('session');

			// Show loading.
			BKXClassFrontend.showModal('<div class="bkx-loading">' + bkxClassFrontend.i18n.loading + '</div>');

			// Fetch waitlist form.
			$.post(bkxClassFrontend.ajaxurl, {
				action: 'bkx_get_waitlist_form',
				nonce: bkxClassFrontend.nonce,
				session_id: sessionId
			}, function(response) {
				if (response.success) {
					$('#bkx-session-modal .bkx-modal-body').html(response.data.html);
				} else {
					$('#bkx-session-modal .bkx-modal-body').html(
						'<p class="bkx-error">' + (response.data || bkxClassFrontend.i18n.error) + '</p>'
					);
				}
			});
		}
	};

	$(document).ready(function() {
		BKXClassFrontend.init();
	});

})(jQuery);
