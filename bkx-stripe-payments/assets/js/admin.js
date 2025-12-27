/**
 * Admin JavaScript
 *
 * @package BookingX\StripePayments
 * @since   1.0.0
 */

( function( $, window, document ) {
	'use strict';

	$( document ).ready( function() {
		// Toggle between test and live mode fields
		const $modeSelect = $( '#stripe_mode' );
		const $liveFields = $( '.bkx-stripe-live-fields' );
		const $testFields = $( '.bkx-stripe-test-fields' );

		if ( $modeSelect.length ) {
			$modeSelect.on( 'change', function() {
				const mode = $( this ).val();

				if ( mode === 'live' ) {
					$liveFields.show();
					$testFields.hide();
				} else {
					$liveFields.hide();
					$testFields.show();
				}
			} ).trigger( 'change' );
		}

		// Copy webhook URL to clipboard
		$( '.bkx-stripe-copy-webhook' ).on( 'click', function( e ) {
			e.preventDefault();

			const webhookUrl = $( this ).data( 'url' );
			const $temp = $( '<input>' );

			$( 'body' ).append( $temp );
			$temp.val( webhookUrl ).select();
			document.execCommand( 'copy' );
			$temp.remove();

			// Show feedback
			$( this ).text( 'Copied!' );
			setTimeout( function() {
				$( '.bkx-stripe-copy-webhook' ).text( 'Copy URL' );
			}, 2000 );
		} );

		// Validate API keys format
		$( 'input[name*="publishable_key"]' ).on( 'blur', function() {
			const value = $( this ).val();

			if ( value && ! value.startsWith( 'pk_' ) ) {
				alert( 'Publishable key should start with "pk_"' );
			}
		} );

		$( 'input[name*="secret_key"]' ).on( 'blur', function() {
			const value = $( this ).val();

			if ( value && ! value.startsWith( 'sk_' ) ) {
				alert( 'Secret key should start with "sk_"' );
			}
		} );

		$( 'input[name*="webhook_secret"]' ).on( 'blur', function() {
			const value = $( this ).val();

			if ( value && ! value.startsWith( 'whsec_' ) ) {
				alert( 'Webhook secret should start with "whsec_"' );
			}
		} );
	} );

} )( jQuery, window, document );
