/**
 * BookingX Booking Reminders Admin Scripts
 *
 * @package BookingX\BookingReminders
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	var BkxRemindersAdmin = {

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
			$( '#bkx-send-test-email' ).on( 'click', this.sendTestEmail.bind( this ) );
			$( '#bkx-send-test-sms' ).on( 'click', this.sendTestSms.bind( this ) );
			$( document ).on( 'click', '.bkx-resend-reminder', this.resendReminder.bind( this ) );
		},

		/**
		 * Send test email.
		 */
		sendTestEmail: function( e ) {
			e.preventDefault();

			var $button = $( e.target );
			var $result = $( '#bkx-test-email-result' );
			var email = $( '#bkx-test-email' ).val();

			if ( ! email ) {
				$result.text( 'Please enter an email address.' ).css( 'color', 'red' );
				return;
			}

			$button.prop( 'disabled', true );
			$result.text( bkxRemindersAdmin.i18n.sending ).css( 'color', '' );

			$.ajax( {
				url: bkxRemindersAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_send_test_reminder',
					nonce: bkxRemindersAdmin.nonce,
					type: 'email',
					email: email
				},
				success: function( response ) {
					if ( response.success ) {
						$result.text( bkxRemindersAdmin.i18n.sent ).css( 'color', 'green' );
					} else {
						$result.text( response.data.message || bkxRemindersAdmin.i18n.failed ).css( 'color', 'red' );
					}
				},
				error: function() {
					$result.text( bkxRemindersAdmin.i18n.failed ).css( 'color', 'red' );
				},
				complete: function() {
					$button.prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Send test SMS.
		 */
		sendTestSms: function( e ) {
			e.preventDefault();

			var $button = $( e.target );
			var $result = $( '#bkx-test-sms-result' );
			var phone = $( '#bkx-test-phone' ).val();

			if ( ! phone ) {
				$result.text( 'Please enter a phone number.' ).css( 'color', 'red' );
				return;
			}

			$button.prop( 'disabled', true );
			$result.text( bkxRemindersAdmin.i18n.sending ).css( 'color', '' );

			$.ajax( {
				url: bkxRemindersAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_send_test_reminder',
					nonce: bkxRemindersAdmin.nonce,
					type: 'sms',
					phone: phone
				},
				success: function( response ) {
					if ( response.success ) {
						$result.text( bkxRemindersAdmin.i18n.sent ).css( 'color', 'green' );
					} else {
						$result.text( response.data.message || bkxRemindersAdmin.i18n.failed ).css( 'color', 'red' );
					}
				},
				error: function() {
					$result.text( bkxRemindersAdmin.i18n.failed ).css( 'color', 'red' );
				},
				complete: function() {
					$button.prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Resend reminder.
		 */
		resendReminder: function( e ) {
			e.preventDefault();

			if ( ! confirm( bkxRemindersAdmin.i18n.confirmResend ) ) {
				return;
			}

			var $button = $( e.target );
			var reminderId = $button.data( 'id' );

			$button.prop( 'disabled', true ).text( bkxRemindersAdmin.i18n.sending );

			$.ajax( {
				url: bkxRemindersAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_resend_reminder',
					nonce: bkxRemindersAdmin.nonce,
					reminder_id: reminderId
				},
				success: function( response ) {
					if ( response.success ) {
						$button.text( bkxRemindersAdmin.i18n.sent ).css( 'background', '#46b450' );
					} else {
						$button.text( bkxRemindersAdmin.i18n.failed ).css( 'background', '#dc3232' );
					}
				},
				error: function() {
					$button.text( bkxRemindersAdmin.i18n.failed ).css( 'background', '#dc3232' );
				}
			} );
		}
	};

	$( document ).ready( function() {
		if ( typeof bkxRemindersAdmin !== 'undefined' ) {
			BkxRemindersAdmin.init();
		}
	} );

} )( jQuery );
