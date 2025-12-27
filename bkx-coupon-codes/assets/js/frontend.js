/**
 * BookingX Coupon Codes Frontend Scripts
 *
 * @package BookingX\CouponCodes
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	var BkxCoupon = {

		/**
		 * Applied coupon data.
		 */
		appliedCoupon: null,

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
			$( document ).on( 'click', '.bkx-apply-coupon', this.applyCoupon.bind( this ) );
			$( document ).on( 'click', '.bkx-remove-coupon', this.removeCoupon.bind( this ) );
			$( document ).on( 'keypress', '.bkx-coupon-input', this.handleEnterKey.bind( this ) );

			// Listen for booking form updates to recalculate discount.
			$( document ).on( 'bkx_booking_updated', this.recalculateDiscount.bind( this ) );
		},

		/**
		 * Handle enter key in coupon input.
		 */
		handleEnterKey: function( e ) {
			if ( e.which === 13 ) {
				e.preventDefault();
				$( e.target ).closest( '.bkx-coupon-field' ).find( '.bkx-apply-coupon' ).click();
			}
		},

		/**
		 * Apply coupon.
		 */
		applyCoupon: function( e ) {
			e.preventDefault();

			var $container = $( e.target ).closest( '.bkx-coupon-field' );
			var $input = $container.find( '.bkx-coupon-input' );
			var $button = $container.find( '.bkx-apply-coupon' );
			var $message = $container.find( '.bkx-coupon-message' );
			var code = $input.val().trim().toUpperCase();

			if ( ! code ) {
				this.showMessage( $message, 'Please enter a coupon code.', 'error' );
				return;
			}

			var bookingData = this.getBookingData();
			var originalText = $button.text();

			$button.text( bkxCoupon.i18n.applying ).prop( 'disabled', true );

			$.ajax( {
				url: bkxCoupon.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_apply_coupon',
					nonce: bkxCoupon.nonce,
					code: code,
					booking_data: bookingData
				},
				success: function( response ) {
					if ( response.success ) {
						BkxCoupon.appliedCoupon = response.data;
						BkxCoupon.showAppliedState( $container, response.data );
						BkxCoupon.updatePriceDisplay( response.data );
						BkxCoupon.showMessage( $message, bkxCoupon.i18n.applied, 'success' );

						// Trigger event for other scripts.
						$( document ).trigger( 'bkx_coupon_applied', [ response.data ] );
					} else {
						BkxCoupon.showMessage( $message, response.data.message, 'error' );
					}
				},
				error: function() {
					BkxCoupon.showMessage( $message, bkxCoupon.i18n.invalid, 'error' );
				},
				complete: function() {
					$button.text( originalText ).prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Remove coupon.
		 */
		removeCoupon: function( e ) {
			e.preventDefault();

			var $container = $( e.target ).closest( '.bkx-coupon-field' );
			var $message = $container.find( '.bkx-coupon-message' );

			$.ajax( {
				url: bkxCoupon.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_remove_coupon',
					nonce: bkxCoupon.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						BkxCoupon.appliedCoupon = null;
						BkxCoupon.showInputState( $container );
						BkxCoupon.removePriceDiscount();
						BkxCoupon.showMessage( $message, bkxCoupon.i18n.removed, 'info' );

						// Trigger event for other scripts.
						$( document ).trigger( 'bkx_coupon_removed' );
					}
				}
			} );
		},

		/**
		 * Show applied coupon state.
		 */
		showAppliedState: function( $container, data ) {
			var $inputArea = $container.find( '.bkx-coupon-input-area' );
			var $appliedArea = $container.find( '.bkx-coupon-applied' );

			$inputArea.hide();

			if ( $appliedArea.length === 0 ) {
				$appliedArea = $( '<div class="bkx-coupon-applied"></div>' );
				$inputArea.after( $appliedArea );
			}

			$appliedArea.html(
				'<span class="bkx-coupon-code-badge">' + data.code + '</span>' +
				'<span class="bkx-coupon-discount-text">' + data.discount_text + '</span>' +
				'<button type="button" class="bkx-remove-coupon">&times;</button>'
			).show();

			if ( data.savings_text ) {
				$container.find( '.bkx-coupon-savings' ).remove();
				$appliedArea.after( '<div class="bkx-coupon-savings">' + data.savings_text + '</div>' );
			}
		},

		/**
		 * Show input state (reset).
		 */
		showInputState: function( $container ) {
			var $inputArea = $container.find( '.bkx-coupon-input-area' );
			var $appliedArea = $container.find( '.bkx-coupon-applied' );

			$appliedArea.hide();
			$container.find( '.bkx-coupon-savings' ).remove();
			$inputArea.show().find( '.bkx-coupon-input' ).val( '' );
		},

		/**
		 * Show message.
		 */
		showMessage: function( $element, message, type ) {
			$element
				.removeClass( 'bkx-message-success bkx-message-error bkx-message-info' )
				.addClass( 'bkx-message-' + type )
				.text( message )
				.fadeIn();

			// Auto-hide after 5 seconds.
			setTimeout( function() {
				$element.fadeOut();
			}, 5000 );
		},

		/**
		 * Update price display with discount.
		 */
		updatePriceDisplay: function( data ) {
			var $priceBreakdown = $( '.bkx-price-breakdown' );
			var $discountRow = $priceBreakdown.find( '.bkx-discount-row' );

			// Add or update discount row.
			if ( $discountRow.length === 0 ) {
				var $subtotalRow = $priceBreakdown.find( '.bkx-subtotal-row' );
				$discountRow = $(
					'<tr class="bkx-discount-row">' +
					'<td>' + 'Discount (' + data.code + ')</td>' +
					'<td class="bkx-discount-amount">-' + this.formatPrice( data.discount ) + '</td>' +
					'</tr>'
				);

				if ( $subtotalRow.length ) {
					$subtotalRow.after( $discountRow );
				} else {
					$priceBreakdown.find( 'tbody' ).append( $discountRow );
				}
			} else {
				$discountRow.find( '.bkx-discount-amount' ).text( '-' + this.formatPrice( data.discount ) );
			}

			// Update total.
			var $total = $( '.bkx-booking-total, .bkx-total-amount' );
			$total.text( this.formatPrice( data.new_total ) );

			// Store original total for reference.
			if ( ! $total.data( 'original-total' ) ) {
				$total.data( 'original-total', $total.text() );
			}
		},

		/**
		 * Remove discount from price display.
		 */
		removePriceDiscount: function() {
			$( '.bkx-discount-row' ).remove();

			var $total = $( '.bkx-booking-total, .bkx-total-amount' );
			var originalTotal = $total.data( 'original-total' );

			if ( originalTotal ) {
				$total.text( originalTotal );
			}
		},

		/**
		 * Recalculate discount when booking data changes.
		 */
		recalculateDiscount: function() {
			if ( ! this.appliedCoupon ) {
				return;
			}

			var bookingData = this.getBookingData();

			$.ajax( {
				url: bkxCoupon.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_apply_coupon',
					nonce: bkxCoupon.nonce,
					code: this.appliedCoupon.code,
					booking_data: bookingData
				},
				success: function( response ) {
					if ( response.success ) {
						BkxCoupon.appliedCoupon = response.data;
						BkxCoupon.updatePriceDisplay( response.data );
					} else {
						// Coupon no longer valid with new booking data.
						BkxCoupon.removeCoupon( { preventDefault: function() {} } );
					}
				}
			} );
		},

		/**
		 * Get current booking data.
		 */
		getBookingData: function() {
			var $form = $( '.bkx-booking-form, #bkx-booking-form' );

			var data = {
				service_id: $form.find( '[name="service_id"], [name="base_id"]' ).val() || 0,
				seat_id: $form.find( '[name="seat_id"]' ).val() || 0,
				extras: [],
				total: 0
			};

			// Get selected extras.
			$form.find( '[name="extras[]"]:checked, [name="additions[]"]:checked' ).each( function() {
				data.extras.push( parseInt( $( this ).val(), 10 ) );
			} );

			// Get total from display.
			var totalText = $( '.bkx-booking-total, .bkx-total-amount' ).first().text();
			data.total = parseFloat( totalText.replace( /[^0-9.]/g, '' ) ) || 0;

			return data;
		},

		/**
		 * Format price for display.
		 */
		formatPrice: function( amount ) {
			// Use WooCommerce format if available, otherwise basic format.
			if ( typeof wc_price_format !== 'undefined' ) {
				return wc_price_format( amount );
			}

			return '$' + parseFloat( amount ).toFixed( 2 );
		}
	};

	$( document ).ready( function() {
		if ( typeof bkxCoupon !== 'undefined' ) {
			BkxCoupon.init();
		}
	} );

} )( jQuery );
