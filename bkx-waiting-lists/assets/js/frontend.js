/**
 * Waiting Lists Frontend JavaScript
 *
 * @package BookingX\WaitingLists
 * @since   1.0.0
 */

(function($) {
	'use strict';

	var BkxWaitlist = {
		init: function() {
			this.bindEvents();
			this.handleUrlActions();
		},

		bindEvents: function() {
			// Open modal
			$(document).on('click', '.bkx-join-waitlist-link', this.openModal);

			// Close modal
			$(document).on('click', '.bkx-waitlist-close, .bkx-waitlist-cancel', this.closeModal);
			$(document).on('click', '.bkx-waitlist-modal', function(e) {
				if (e.target === this) {
					BkxWaitlist.closeModal();
				}
			});

			// Submit form
			$('#bkx-waitlist-form').on('submit', this.joinWaitlist);

			// Accept/decline offer
			$(document).on('click', '.bkx-accept-offer', this.acceptOffer);
			$(document).on('click', '.bkx-decline-offer', this.declineOffer);

			// Leave waitlist
			$(document).on('click', '.bkx-leave-waitlist', this.leaveWaitlist);
		},

		handleUrlActions: function() {
			// Handle action URLs from emails
			var urlParams = new URLSearchParams(window.location.search);
			var action = urlParams.get('bkx_waitlist_action');
			var entryId = urlParams.get('entry_id');
			var token = urlParams.get('token');

			if (action && entryId && token) {
				if (action === 'accept') {
					this.processConfirmation(entryId, token, 'accept');
				} else if (action === 'decline') {
					this.processConfirmation(entryId, token, 'decline');
				} else if (action === 'leave') {
					this.processLeave(entryId, token);
				}
			}
		},

		openModal: function(e) {
			e.preventDefault();
			var $link = $(this);
			var seatId = $link.data('seat') || '';
			var datetime = $link.data('datetime') || '';
			var serviceId = $link.data('service') || '';

			// Parse datetime if combined
			var date = '';
			var time = '';
			if (datetime.indexOf(' ') !== -1) {
				var parts = datetime.split(' ');
				date = parts[0];
				time = parts[1];
			} else {
				date = $link.data('date') || '';
				time = $link.data('time') || '';
			}

			// Populate form
			$('#waitlist_seat_id').val(seatId);
			$('#waitlist_service_id').val(serviceId);
			$('#waitlist_date').val(date);
			$('#waitlist_time').val(time);
			$('#waitlist_slot_display').text(date + ' at ' + time);

			// Show modal
			$('#bkx-waitlist-modal').show();
		},

		closeModal: function() {
			$('#bkx-waitlist-modal').hide();
			$('.bkx-waitlist-message').hide().removeClass('success error');
		},

		joinWaitlist: function(e) {
			e.preventDefault();
			var $form = $(this);
			var $submit = $form.find('.bkx-waitlist-submit');
			var $message = $form.find('.bkx-waitlist-message');

			$submit.prop('disabled', true).text(bkxWaitlist.i18n.joining);
			$message.hide();

			$.ajax({
				url: bkxWaitlist.ajaxUrl,
				method: 'POST',
				data: $.extend({}, BkxWaitlist.serializeForm($form), {
					action: 'bkx_join_waitlist',
					nonce: bkxWaitlist.nonce
				}),
				success: function(response) {
					if (response.success) {
						$message.addClass('success').text(response.data.message).show();
						$form[0].reset();
						setTimeout(function() {
							BkxWaitlist.closeModal();
						}, 3000);
					} else {
						$message.addClass('error').text(response.data.message).show();
					}
				},
				error: function() {
					$message.addClass('error').text(bkxWaitlist.i18n.error).show();
				},
				complete: function() {
					$submit.prop('disabled', false).text('Join Waiting List');
				}
			});
		},

		acceptOffer: function(e) {
			e.preventDefault();
			var entryId = $(this).data('entry-id');
			var token = $(this).data('token');

			BkxWaitlist.processConfirmation(entryId, token, 'accept');
		},

		declineOffer: function(e) {
			e.preventDefault();
			var entryId = $(this).data('entry-id');
			var token = $(this).data('token');

			if (!confirm('Are you sure you want to decline this offer?')) {
				return;
			}

			BkxWaitlist.processConfirmation(entryId, token, 'decline');
		},

		processConfirmation: function(entryId, token, action) {
			$.ajax({
				url: bkxWaitlist.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_confirm_waitlist',
					nonce: bkxWaitlist.nonce,
					entry_id: entryId,
					token: token,
					confirm_action: action
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						if (response.data.redirect_url) {
							window.location.href = response.data.redirect_url;
						} else {
							location.reload();
						}
					} else {
						alert(response.data.message || bkxWaitlist.i18n.error);
					}
				},
				error: function() {
					alert(bkxWaitlist.i18n.error);
				}
			});
		},

		leaveWaitlist: function(e) {
			e.preventDefault();
			var entryId = $(this).data('entry-id');
			var token = $(this).data('token');

			if (!confirm('Are you sure you want to leave the waiting list?')) {
				return;
			}

			BkxWaitlist.processLeave(entryId, token);
		},

		processLeave: function(entryId, token) {
			$.ajax({
				url: bkxWaitlist.ajaxUrl,
				method: 'POST',
				data: {
					action: 'bkx_leave_waitlist',
					nonce: bkxWaitlist.nonce,
					entry_id: entryId,
					token: token
				},
				success: function(response) {
					if (response.success) {
						alert(response.data.message);
						location.reload();
					} else {
						alert(response.data.message || bkxWaitlist.i18n.error);
					}
				},
				error: function() {
					alert(bkxWaitlist.i18n.error);
				}
			});
		},

		serializeForm: function($form) {
			var o = {};
			var a = $form.serializeArray();
			$.each(a, function() {
				if (o[this.name]) {
					if (!o[this.name].push) {
						o[this.name] = [o[this.name]];
					}
					o[this.name].push(this.value || '');
				} else {
					o[this.name] = this.value || '';
				}
			});
			return o;
		}
	};

	$(document).ready(function() {
		BkxWaitlist.init();
	});

})(jQuery);
