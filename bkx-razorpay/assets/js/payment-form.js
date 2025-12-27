/**
 * BookingX Razorpay Payment Form Handler
 *
 * Handles the Razorpay Checkout integration for payment processing.
 *
 * @package BookingX\Razorpay
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	/**
	 * Razorpay Payment Handler.
	 */
	var BkxRazorpayPayment = {

		/**
		 * Configuration from server.
		 */
		config: window.bkxRazorpayConfig || {},

		/**
		 * DOM elements.
		 */
		elements: {
			form: null,
			button: null,
			buttonText: null,
			buttonSpinner: null,
			errorContainer: null
		},

		/**
		 * State.
		 */
		state: {
			processing: false,
			razorpay: null
		},

		/**
		 * Initialize the payment handler.
		 */
		init: function() {
			this.cacheElements();
			this.bindEvents();
		},

		/**
		 * Cache DOM elements for performance.
		 */
		cacheElements: function() {
			this.elements.form = $( '#bkx-razorpay-payment-form' );
			this.elements.button = $( '#bkx-razorpay-pay-button' );
			this.elements.buttonText = this.elements.button.find( '.bkx-razorpay-button-text' );
			this.elements.buttonSpinner = this.elements.button.find( '.bkx-razorpay-button-spinner' );
			this.elements.errorContainer = $( '#bkx-razorpay-error' );
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			this.elements.button.on( 'click', this.handlePayButtonClick.bind( this ) );
		},

		/**
		 * Handle pay button click.
		 *
		 * @param {Event} e Click event.
		 */
		handlePayButtonClick: function( e ) {
			e.preventDefault();

			if ( this.state.processing ) {
				return;
			}

			this.hideError();
			this.setProcessing( true );
			this.createOrder();
		},

		/**
		 * Create Razorpay order via AJAX.
		 */
		createOrder: function() {
			var self = this;

			$.ajax( {
				url: this.config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_razorpay_create_order',
					nonce: this.config.nonce,
					booking_id: this.config.bookingId
				},
				success: function( response ) {
					if ( response.success && response.data ) {
						self.openRazorpayCheckout( response.data );
					} else {
						self.showError( response.data?.error || self.config.i18n.error );
						self.setProcessing( false );
					}
				},
				error: function( xhr, status, error ) {
					console.error( 'Razorpay order creation failed:', error );
					self.showError( self.config.i18n.error );
					self.setProcessing( false );
				}
			} );
		},

		/**
		 * Open Razorpay Checkout modal.
		 *
		 * @param {Object} orderData Order data from server.
		 */
		openRazorpayCheckout: function( orderData ) {
			var self = this;

			// Prepare checkout options.
			var options = {
				key: orderData.key_id,
				amount: orderData.amount_in_paise,
				currency: orderData.currency,
				order_id: orderData.order_id,
				name: this.config.businessName,
				description: 'Booking #' + this.config.bookingId,
				handler: function( response ) {
					self.handlePaymentSuccess( response );
				},
				modal: {
					ondismiss: function() {
						self.setProcessing( false );
					},
					escape: true,
					animation: true
				},
				prefill: {
					name: orderData.prefill?.name || '',
					email: orderData.prefill?.email || '',
					contact: orderData.prefill?.contact || ''
				},
				notes: {
					booking_id: self.config.bookingId.toString()
				},
				theme: {
					color: '#3399cc'
				}
			};

			// Add logo if available.
			if ( this.config.businessLogo ) {
				options.image = this.config.businessLogo;
			}

			// Allow filtering of options.
			options = this.applyFilters( 'bkxRazorpayCheckoutOptions', options );

			try {
				// Create Razorpay instance.
				this.state.razorpay = new Razorpay( options );

				// Handle payment failure.
				this.state.razorpay.on( 'payment.failed', function( response ) {
					self.handlePaymentFailure( response );
				} );

				// Open checkout.
				this.state.razorpay.open();

			} catch ( error ) {
				console.error( 'Razorpay initialization failed:', error );
				this.showError( this.config.i18n.error );
				this.setProcessing( false );
			}
		},

		/**
		 * Handle successful payment from Razorpay.
		 *
		 * @param {Object} response Razorpay response.
		 */
		handlePaymentSuccess: function( response ) {
			var self = this;

			// Verify payment on server.
			$.ajax( {
				url: this.config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_razorpay_verify_payment',
					nonce: this.config.nonce,
					booking_id: this.config.bookingId,
					razorpay_payment_id: response.razorpay_payment_id,
					razorpay_order_id: response.razorpay_order_id,
					razorpay_signature: response.razorpay_signature
				},
				success: function( verifyResponse ) {
					if ( verifyResponse.success && verifyResponse.data ) {
						self.showSuccess( self.config.i18n.redirecting );

						// Trigger custom event.
						$( document ).trigger( 'bkx_razorpay_payment_success', [
							verifyResponse.data,
							self.config.bookingId
						] );

						// Redirect to confirmation page.
						if ( verifyResponse.data.redirect_url ) {
							window.location.href = verifyResponse.data.redirect_url;
						}
					} else {
						self.showError( verifyResponse.data?.error || self.config.i18n.paymentFailed );
						self.setProcessing( false );
					}
				},
				error: function( xhr, status, error ) {
					console.error( 'Payment verification failed:', error );
					self.showError( self.config.i18n.paymentFailed );
					self.setProcessing( false );
				}
			} );
		},

		/**
		 * Handle payment failure from Razorpay.
		 *
		 * @param {Object} response Razorpay error response.
		 */
		handlePaymentFailure: function( response ) {
			var errorMessage = this.config.i18n.paymentFailed;

			if ( response.error ) {
				var errorCode = response.error.code || '';
				var errorDescription = response.error.description || '';
				var errorReason = response.error.reason || '';

				// Log for debugging.
				console.error( 'Razorpay payment failed:', {
					code: errorCode,
					description: errorDescription,
					reason: errorReason
				} );

				// Use description if available.
				if ( errorDescription ) {
					errorMessage = errorDescription;
				}
			}

			this.showError( errorMessage );
			this.setProcessing( false );

			// Trigger custom event.
			$( document ).trigger( 'bkx_razorpay_payment_failed', [
				response,
				this.config.bookingId
			] );
		},

		/**
		 * Set processing state.
		 *
		 * @param {boolean} processing Whether processing is active.
		 */
		setProcessing: function( processing ) {
			this.state.processing = processing;

			if ( processing ) {
				this.elements.button.prop( 'disabled', true ).addClass( 'bkx-processing' );
				this.elements.buttonText.text( this.config.i18n.processing );
				this.elements.buttonSpinner.show();
			} else {
				this.elements.button.prop( 'disabled', false ).removeClass( 'bkx-processing' );
				this.elements.buttonText.text( this.elements.button.data( 'original-text' ) || this.elements.buttonText.text() );
				this.elements.buttonSpinner.hide();
			}
		},

		/**
		 * Show error message.
		 *
		 * @param {string} message Error message.
		 */
		showError: function( message ) {
			this.elements.errorContainer
				.html( '<span class="bkx-razorpay-error-icon">&#9888;</span> ' + this.escapeHtml( message ) )
				.slideDown( 200 );

			// Trigger custom event.
			$( document ).trigger( 'bkx_razorpay_error', [ message, this.config.bookingId ] );
		},

		/**
		 * Hide error message.
		 */
		hideError: function() {
			this.elements.errorContainer.slideUp( 200 );
		},

		/**
		 * Show success message.
		 *
		 * @param {string} message Success message.
		 */
		showSuccess: function( message ) {
			this.elements.errorContainer
				.removeClass( 'bkx-razorpay-error' )
				.addClass( 'bkx-razorpay-success' )
				.html( '<span class="bkx-razorpay-success-icon">&#10003;</span> ' + this.escapeHtml( message ) )
				.slideDown( 200 );
		},

		/**
		 * Escape HTML for safe display.
		 *
		 * @param {string} text Text to escape.
		 * @return {string} Escaped text.
		 */
		escapeHtml: function( text ) {
			var div = document.createElement( 'div' );
			div.textContent = text;
			return div.innerHTML;
		},

		/**
		 * Apply filters (simple event-based filtering).
		 *
		 * @param {string} filterName Filter name.
		 * @param {*}      value      Value to filter.
		 * @return {*} Filtered value.
		 */
		applyFilters: function( filterName, value ) {
			var event = $.Event( filterName );
			event.value = value;
			$( document ).trigger( event );
			return event.value;
		}
	};

	// Initialize when DOM is ready.
	$( document ).ready( function() {
		// Only initialize if configuration exists.
		if ( window.bkxRazorpayConfig ) {
			// Store original button text.
			$( '#bkx-razorpay-pay-button' ).data( 'original-text', $( '#bkx-razorpay-pay-button .bkx-razorpay-button-text' ).text() );

			BkxRazorpayPayment.init();
		}
	} );

	// Expose for external access if needed.
	window.BkxRazorpayPayment = BkxRazorpayPayment;

} )( jQuery );
