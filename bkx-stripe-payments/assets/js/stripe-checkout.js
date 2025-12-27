/**
 * Stripe Checkout JavaScript
 *
 * Handles Stripe Payment Element integration.
 *
 * @package BookingX\StripePayments
 * @since   1.0.0
 */

( function( $, window, document ) {
	'use strict';

	window.bkxStripeCheckout = {
		stripe: null,
		elements: null,
		paymentElement: null,
		paymentIntentId: null,

		/**
		 * Initialize Stripe checkout.
		 *
		 * @param {string} publishableKey Stripe publishable key.
		 */
		init: function( publishableKey ) {
			if ( ! publishableKey ) {
				console.error( 'Stripe publishable key is missing.' );
				return;
			}

			// Initialize Stripe.js
			this.stripe = Stripe( publishableKey ); // eslint-disable-line no-undef

			// Get booking details
			const bookingId = $( '#bkx-stripe-booking-id' ).val();
			const amount = $( '#bkx-stripe-amount' ).val();

			if ( ! bookingId || ! amount ) {
				console.error( 'Booking ID or amount is missing.' );
				return;
			}

			// Create PaymentIntent
			this.createPaymentIntent( bookingId );

			// Bind submit button
			$( '#bkx-stripe-submit-button' ).on( 'click', this.handleSubmit.bind( this ) );
		},

		/**
		 * Create PaymentIntent via REST API.
		 *
		 * @param {number} bookingId Booking ID.
		 */
		createPaymentIntent: function( bookingId ) {
			const self = this;

			$.ajax( {
				url: bkxStripe.restUrl + 'create-payment-intent',
				method: 'POST',
				data: {
					booking_id: bookingId,
					nonce: bkxStripe.nonce
				},
				beforeSend: function() {
					self.showSpinner( true );
				},
				success: function( response ) {
					if ( response.success && response.data.client_secret ) {
						self.paymentIntentId = response.data.payment_intent_id;
						self.mountPaymentElement( response.data.client_secret );
					} else {
						self.showError( response.data.error || bkxStripe.i18n.error );
					}
				},
				error: function() {
					self.showError( bkxStripe.i18n.error );
				},
				complete: function() {
					self.showSpinner( false );
				}
			} );
		},

		/**
		 * Mount Stripe Payment Element.
		 *
		 * @param {string} clientSecret PaymentIntent client secret.
		 */
		mountPaymentElement: function( clientSecret ) {
			const appearance = {
				theme: 'stripe',
				variables: {
					colorPrimary: '#0570de'
				}
			};

			this.elements = this.stripe.elements( {
				clientSecret: clientSecret,
				appearance: appearance
			} );

			this.paymentElement = this.elements.create( 'payment' );
			this.paymentElement.mount( '#bkx-stripe-payment-element' );

			// Handle element errors
			this.paymentElement.on( 'change', function( event ) {
				if ( event.error ) {
					this.showError( event.error.message );
				} else {
					this.hideError();
				}
			}.bind( this ) );
		},

		/**
		 * Handle form submission.
		 *
		 * @param {Event} e Click event.
		 */
		handleSubmit: function( e ) {
			e.preventDefault();

			this.showSpinner( true );
			this.disableSubmitButton( true );

			const self = this;

			this.stripe.confirmPayment( {
				elements: this.elements,
				confirmParams: {
					return_url: window.location.href
				},
				redirect: 'if_required'
			} ).then( function( result ) {
				if ( result.error ) {
					// Show error to customer
					self.showError( result.error.message );
					self.showSpinner( false );
					self.disableSubmitButton( false );
				} else {
					// Payment successful
					if ( result.paymentIntent.status === 'succeeded' ) {
						self.handlePaymentSuccess( result.paymentIntent );
					} else if ( result.paymentIntent.status === 'requires_action' ) {
						// 3D Secure authentication required
						self.handle3DSecure( result.paymentIntent );
					}
				}
			} );
		},

		/**
		 * Handle 3D Secure authentication.
		 *
		 * @param {object} paymentIntent PaymentIntent object.
		 */
		handle3DSecure: function( paymentIntent ) {
			const self = this;

			this.stripe.confirmCardPayment( paymentIntent.client_secret ).then( function( result ) {
				if ( result.error ) {
					self.showError( result.error.message );
					self.showSpinner( false );
					self.disableSubmitButton( false );
				} else {
					self.handlePaymentSuccess( result.paymentIntent );
				}
			} );
		},

		/**
		 * Handle successful payment.
		 *
		 * @param {object} paymentIntent PaymentIntent object.
		 */
		handlePaymentSuccess: function( paymentIntent ) {
			// Show success message
			this.showSuccess( 'Payment successful! Redirecting...' );

			// Trigger custom event
			$( document ).trigger( 'bkx_stripe_payment_succeeded', [ paymentIntent ] );

			// Redirect to confirmation page
			const bookingId = $( '#bkx-stripe-booking-id' ).val();
			const redirectUrl = new URL( window.location.origin + '/booking-confirmation/' );
			redirectUrl.searchParams.append( 'booking_id', bookingId );
			redirectUrl.searchParams.append( 'payment_intent', paymentIntent.id );

			setTimeout( function() {
				window.location.href = redirectUrl.toString();
			}, 2000 );
		},

		/**
		 * Show error message.
		 *
		 * @param {string} message Error message.
		 */
		showError: function( message ) {
			const errorElement = $( '#bkx-stripe-error-message' );
			errorElement.text( message ).show();
		},

		/**
		 * Hide error message.
		 */
		hideError: function() {
			$( '#bkx-stripe-error-message' ).hide();
		},

		/**
		 * Show success message.
		 *
		 * @param {string} message Success message.
		 */
		showSuccess: function( message ) {
			const errorElement = $( '#bkx-stripe-error-message' );
			errorElement.removeClass( 'error' ).addClass( 'success' ).text( message ).show();
		},

		/**
		 * Show/hide spinner.
		 *
		 * @param {boolean} show Whether to show spinner.
		 */
		showSpinner: function( show ) {
			if ( show ) {
				$( '#bkx-stripe-button-text' ).hide();
				$( '#bkx-stripe-spinner' ).show();
			} else {
				$( '#bkx-stripe-button-text' ).show();
				$( '#bkx-stripe-spinner' ).hide();
			}
		},

		/**
		 * Disable/enable submit button.
		 *
		 * @param {boolean} disabled Whether to disable button.
		 */
		disableSubmitButton: function( disabled ) {
			$( '#bkx-stripe-submit-button' ).prop( 'disabled', disabled );
		}
	};

	/**
	 * Auto-initialize when document is ready (CSP compliant - no inline JS).
	 */
	$( document ).ready( function() {
		if ( typeof bkxStripe !== 'undefined' && bkxStripe.publishableKey ) {
			// Only initialize if the payment form container exists
			if ( $( '.bkx-stripe-payment-form' ).length > 0 ) {
				window.bkxStripeCheckout.init( bkxStripe.publishableKey );
			}
		}
	} );

} )( jQuery, window, document );
