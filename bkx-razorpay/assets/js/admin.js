/**
 * BookingX Razorpay Admin Scripts
 *
 * Handles admin functionality for Razorpay settings.
 *
 * @package BookingX\Razorpay
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Razorpay Admin Handler.
	 */
	var BkxRazorpayAdmin = {

		/**
		 * Initialize the admin handler.
		 */
		init: function() {
			this.bindEvents();
			this.initializeTestMode();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			// Copy webhook URL.
			$( '.bkx-razorpay-copy-webhook' ).on( 'click', this.copyWebhookUrl.bind( this ) );

			// Test mode toggle.
			$( 'input[name="bkx_razorpay_settings[mode]"]' ).on( 'change', this.handleModeChange.bind( this ) );

			// Verify API credentials.
			$( '.bkx-razorpay-verify-credentials' ).on( 'click', this.verifyCredentials.bind( this ) );

			// Toggle password visibility.
			$( '.bkx-razorpay-toggle-visibility' ).on( 'click', this.toggleVisibility.bind( this ) );
		},

		/**
		 * Initialize test mode indicator.
		 */
		initializeTestMode: function() {
			var mode = $( 'input[name="bkx_razorpay_settings[mode]"]:checked' ).val();
			this.updateModeIndicator( mode );
		},

		/**
		 * Handle mode change.
		 *
		 * @param {Event} e Change event.
		 */
		handleModeChange: function( e ) {
			var mode = $( e.target ).val();
			this.updateModeIndicator( mode );
		},

		/**
		 * Update mode indicator.
		 *
		 * @param {string} mode Current mode (test/live).
		 */
		updateModeIndicator: function( mode ) {
			var $indicator = $( '.bkx-razorpay-mode-indicator' );

			if ( mode === 'test' ) {
				$indicator
					.removeClass( 'bkx-razorpay-mode-live' )
					.addClass( 'bkx-razorpay-mode-test' )
					.text( 'Test Mode' );
			} else {
				$indicator
					.removeClass( 'bkx-razorpay-mode-test' )
					.addClass( 'bkx-razorpay-mode-live' )
					.text( 'Live Mode' );
			}
		},

		/**
		 * Copy webhook URL to clipboard.
		 *
		 * @param {Event} e Click event.
		 */
		copyWebhookUrl: function( e ) {
			e.preventDefault();

			var $button = $( e.currentTarget );
			var $input = $button.siblings( 'input' ).first();
			var url = $input.val();

			if ( ! url ) {
				return;
			}

			// Use modern clipboard API if available.
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( url ).then(
					function() {
						BkxRazorpayAdmin.showCopyFeedback( $button, true );
					},
					function() {
						BkxRazorpayAdmin.fallbackCopy( url, $button );
					}
				);
			} else {
				this.fallbackCopy( url, $button );
			}
		},

		/**
		 * Fallback copy method for older browsers.
		 *
		 * @param {string}  text    Text to copy.
		 * @param {jQuery}  $button Button element.
		 */
		fallbackCopy: function( text, $button ) {
			var textarea = document.createElement( 'textarea' );
			textarea.value = text;
			textarea.style.position = 'fixed';
			textarea.style.opacity = '0';
			document.body.appendChild( textarea );
			textarea.select();

			try {
				document.execCommand( 'copy' );
				this.showCopyFeedback( $button, true );
			} catch ( err ) {
				this.showCopyFeedback( $button, false );
			}

			document.body.removeChild( textarea );
		},

		/**
		 * Show copy feedback.
		 *
		 * @param {jQuery}  $button Button element.
		 * @param {boolean} success Whether copy was successful.
		 */
		showCopyFeedback: function( $button, success ) {
			var originalText = $button.text();
			var feedbackText = success ? 'Copied!' : 'Failed';

			$button
				.text( feedbackText )
				.addClass( success ? 'bkx-copy-success' : 'bkx-copy-error' );

			setTimeout( function() {
				$button
					.text( originalText )
					.removeClass( 'bkx-copy-success bkx-copy-error' );
			}, 2000 );
		},

		/**
		 * Verify API credentials.
		 *
		 * @param {Event} e Click event.
		 */
		verifyCredentials: function( e ) {
			e.preventDefault();

			var $button = $( e.currentTarget );
			var $result = $( '.bkx-razorpay-verify-result' );

			// Get credentials.
			var keyId = $( 'input[name="bkx_razorpay_settings[key_id]"]' ).val();
			var keySecret = $( 'input[name="bkx_razorpay_settings[key_secret]"]' ).val();

			if ( ! keyId || ! keySecret ) {
				$result
					.removeClass( 'bkx-verify-success' )
					.addClass( 'bkx-verify-error' )
					.text( 'Please enter both Key ID and Key Secret.' )
					.show();
				return;
			}

			// Show loading state.
			$button.prop( 'disabled', true ).text( 'Verifying...' );
			$result.hide();

			// Verify via AJAX.
			$.ajax( {
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'bkx_razorpay_verify_credentials',
					nonce: $( '#bkx_razorpay_admin_nonce' ).val(),
					key_id: keyId,
					key_secret: keySecret
				},
				success: function( response ) {
					if ( response.success ) {
						$result
							.removeClass( 'bkx-verify-error' )
							.addClass( 'bkx-verify-success' )
							.text( 'Credentials verified successfully!' );
					} else {
						$result
							.removeClass( 'bkx-verify-success' )
							.addClass( 'bkx-verify-error' )
							.text( response.data?.error || 'Verification failed.' );
					}
					$result.show();
				},
				error: function() {
					$result
						.removeClass( 'bkx-verify-success' )
						.addClass( 'bkx-verify-error' )
						.text( 'Verification request failed.' )
						.show();
				},
				complete: function() {
					$button.prop( 'disabled', false ).text( 'Verify Credentials' );
				}
			} );
		},

		/**
		 * Toggle password field visibility.
		 *
		 * @param {Event} e Click event.
		 */
		toggleVisibility: function( e ) {
			e.preventDefault();

			var $button = $( e.currentTarget );
			var $input = $button.siblings( 'input' ).first();

			if ( $input.attr( 'type' ) === 'password' ) {
				$input.attr( 'type', 'text' );
				$button.text( 'Hide' );
			} else {
				$input.attr( 'type', 'password' );
				$button.text( 'Show' );
			}
		}
	};

	// Initialize when DOM is ready.
	$( document ).ready( function() {
		BkxRazorpayAdmin.init();
	} );

} )( jQuery );
