/**
 * BookingX Google Calendar Admin Scripts
 *
 * @package BookingX\GoogleCalendar
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	var BkxGoogleCalendar = {

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
			$( '#bkx-connect' ).on( 'click', this.connect.bind( this ) );
			$( '#bkx-disconnect' ).on( 'click', this.disconnect.bind( this ) );
			$( '#bkx-sync-now' ).on( 'click', this.syncNow.bind( this ) );
			$( '#bkx-test-connection' ).on( 'click', this.testConnection.bind( this ) );

			// Staff calendar buttons.
			$( document ).on( 'click', '.bkx-staff-connect-calendar', this.staffConnect.bind( this ) );
			$( document ).on( 'click', '.bkx-staff-disconnect-calendar', this.staffDisconnect.bind( this ) );
		},

		/**
		 * Connect to Google.
		 */
		connect: function( e ) {
			e.preventDefault();

			var $button = $( e.target );
			var originalText = $button.text();

			$button.text( bkxGoogleCalendar.i18n.connecting ).prop( 'disabled', true );

			$.ajax( {
				url: bkxGoogleCalendar.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_google_connect',
					nonce: bkxGoogleCalendar.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						// Redirect to Google auth.
						window.location.href = response.data.auth_url;
					} else {
						alert( response.data.message );
						$button.text( originalText ).prop( 'disabled', false );
					}
				},
				error: function() {
					alert( 'Connection failed. Please try again.' );
					$button.text( originalText ).prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Disconnect from Google.
		 */
		disconnect: function( e ) {
			e.preventDefault();

			if ( ! confirm( bkxGoogleCalendar.i18n.confirmDisconnect ) ) {
				return;
			}

			var $button = $( e.target );
			var originalText = $button.text();

			$button.text( bkxGoogleCalendar.i18n.disconnecting ).prop( 'disabled', true );

			$.ajax( {
				url: bkxGoogleCalendar.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_google_disconnect',
					nonce: bkxGoogleCalendar.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						location.reload();
					} else {
						alert( response.data.message );
						$button.text( originalText ).prop( 'disabled', false );
					}
				},
				error: function() {
					alert( 'Disconnection failed. Please try again.' );
					$button.text( originalText ).prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Run sync now.
		 */
		syncNow: function( e ) {
			e.preventDefault();

			var $button = $( e.target );
			var originalText = $button.text();

			$button.text( bkxGoogleCalendar.i18n.syncing ).prop( 'disabled', true );

			$.ajax( {
				url: bkxGoogleCalendar.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_google_sync_now',
					nonce: bkxGoogleCalendar.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						var stats = response.data.stats;
						var message = response.data.message + '\n\n';
						message += 'Synced to Google: ' + ( stats.synced_to_google || 0 ) + '\n';
						message += 'Synced from Google: ' + ( stats.synced_from_google || 0 ) + '\n';
						message += 'Errors: ' + ( stats.errors || 0 );
						alert( message );
					} else {
						alert( response.data.message );
					}
				},
				error: function() {
					alert( 'Sync failed. Please try again.' );
				},
				complete: function() {
					$button.text( originalText ).prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Test connection.
		 */
		testConnection: function( e ) {
			e.preventDefault();

			var $button = $( e.target );
			var originalText = $button.text();

			$button.text( bkxGoogleCalendar.i18n.testing ).prop( 'disabled', true );

			$.ajax( {
				url: bkxGoogleCalendar.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_google_test_connection',
					nonce: bkxGoogleCalendar.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						var message = response.data.message + '\n\n';
						message += 'Account: ' + response.data.email + '\n';
						message += 'Calendars: ' + response.data.calendars.length;
						alert( message );
					} else {
						alert( 'Test failed: ' + response.data.message );
					}
				},
				error: function() {
					alert( 'Test failed. Please try again.' );
				},
				complete: function() {
					$button.text( originalText ).prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Staff connect calendar.
		 */
		staffConnect: function( e ) {
			e.preventDefault();

			var $button = $( e.target );
			var staffId = $button.data( 'staff-id' );
			var originalText = $button.text();

			$button.text( bkxGoogleCalendar.i18n.connecting ).prop( 'disabled', true );

			$.ajax( {
				url: bkxGoogleCalendar.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_staff_connect_calendar',
					nonce: bkxGoogleCalendar.nonce,
					staff_id: staffId
				},
				success: function( response ) {
					if ( response.success ) {
						window.location.href = response.data.auth_url;
					} else {
						alert( response.data.message );
						$button.text( originalText ).prop( 'disabled', false );
					}
				},
				error: function() {
					alert( 'Connection failed. Please try again.' );
					$button.text( originalText ).prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Staff disconnect calendar.
		 */
		staffDisconnect: function( e ) {
			e.preventDefault();

			if ( ! confirm( bkxGoogleCalendar.i18n.confirmDisconnect ) ) {
				return;
			}

			var $button = $( e.target );
			var staffId = $button.data( 'staff-id' );
			var originalText = $button.text();

			$button.text( bkxGoogleCalendar.i18n.disconnecting ).prop( 'disabled', true );

			$.ajax( {
				url: bkxGoogleCalendar.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_staff_disconnect_calendar',
					nonce: bkxGoogleCalendar.nonce,
					staff_id: staffId
				},
				success: function( response ) {
					if ( response.success ) {
						location.reload();
					} else {
						alert( response.data.message );
						$button.text( originalText ).prop( 'disabled', false );
					}
				},
				error: function() {
					alert( 'Disconnection failed. Please try again.' );
					$button.text( originalText ).prop( 'disabled', false );
				}
			} );
		}
	};

	$( document ).ready( function() {
		if ( typeof bkxGoogleCalendar !== 'undefined' ) {
			BkxGoogleCalendar.init();
		}
	} );

} )( jQuery );
