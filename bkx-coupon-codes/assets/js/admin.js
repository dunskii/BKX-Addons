/**
 * BookingX Coupon Codes Admin Scripts
 *
 * @package BookingX\CouponCodes
 * @since   1.0.0
 */

( function( $ ) {
	'use strict';

	var BkxCouponAdmin = {

		/**
		 * Initialize.
		 */
		init: function() {
			this.bindEvents();
			this.initDatePickers();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function() {
			$( '#bkx-generate-code' ).on( 'click', this.generateCode.bind( this ) );
			$( '.bkx-toggle-coupon' ).on( 'click', this.toggleCoupon.bind( this ) );
			$( '.bkx-delete-coupon' ).on( 'click', this.deleteCoupon.bind( this ) );
			$( '#bkx-discount-type' ).on( 'change', this.toggleDiscountFields.bind( this ) );
			$( '#bkx-coupon-form' ).on( 'submit', this.validateForm.bind( this ) );
		},

		/**
		 * Initialize date pickers.
		 */
		initDatePickers: function() {
			if ( $.fn.datepicker ) {
				$( '#bkx-start-date, #bkx-end-date' ).datepicker( {
					dateFormat: 'yy-mm-dd',
					changeMonth: true,
					changeYear: true
				} );
			}
		},

		/**
		 * Generate unique coupon code.
		 */
		generateCode: function( e ) {
			e.preventDefault();

			var $button = $( e.target );
			var $input = $( '#bkx-coupon-code' );
			var originalText = $button.text();

			$button.text( bkxCouponAdmin.i18n.generating ).prop( 'disabled', true );

			$.ajax( {
				url: bkxCouponAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_generate_coupon_code',
					nonce: bkxCouponAdmin.nonce
				},
				success: function( response ) {
					if ( response.success ) {
						$input.val( response.data.code );
					}
				},
				complete: function() {
					$button.text( originalText ).prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Toggle coupon active status.
		 */
		toggleCoupon: function( e ) {
			e.preventDefault();

			var $button = $( e.target );
			var couponId = $button.data( 'id' );

			$button.prop( 'disabled', true );

			$.ajax( {
				url: bkxCouponAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_admin_toggle_coupon',
					nonce: bkxCouponAdmin.nonce,
					coupon_id: couponId
				},
				success: function( response ) {
					if ( response.success ) {
						var $status = $button.closest( 'tr' ).find( '.bkx-status' );
						if ( response.data.is_active ) {
							$status.removeClass( 'bkx-status-inactive' ).addClass( 'bkx-status-active' ).text( 'Active' );
							$button.text( 'Deactivate' );
						} else {
							$status.removeClass( 'bkx-status-active' ).addClass( 'bkx-status-inactive' ).text( 'Inactive' );
							$button.text( 'Activate' );
						}
					}
				},
				complete: function() {
					$button.prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Delete coupon.
		 */
		deleteCoupon: function( e ) {
			e.preventDefault();

			if ( ! confirm( bkxCouponAdmin.i18n.confirmDelete ) ) {
				return;
			}

			var $button = $( e.target );
			var couponId = $button.data( 'id' );
			var $row = $button.closest( 'tr' );

			$button.text( bkxCouponAdmin.i18n.deleting ).prop( 'disabled', true );

			$.ajax( {
				url: bkxCouponAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'bkx_admin_delete_coupon',
					nonce: bkxCouponAdmin.nonce,
					coupon_id: couponId
				},
				success: function( response ) {
					if ( response.success ) {
						$row.fadeOut( function() {
							$( this ).remove();
						} );
					} else {
						alert( response.data.message );
						$button.text( 'Delete' ).prop( 'disabled', false );
					}
				},
				error: function() {
					$button.text( 'Delete' ).prop( 'disabled', false );
				}
			} );
		},

		/**
		 * Toggle discount value fields based on type.
		 */
		toggleDiscountFields: function( e ) {
			var type = $( e.target ).val();
			var $valueField = $( '#bkx-discount-value-row' );
			var $maxField = $( '#bkx-max-discount-row' );

			if ( type === 'free_service' || type === 'free_extra' ) {
				$valueField.hide();
				$maxField.hide();
			} else {
				$valueField.show();
				if ( type === 'percentage' ) {
					$maxField.show();
				} else {
					$maxField.hide();
				}
			}
		},

		/**
		 * Validate form before submit.
		 */
		validateForm: function( e ) {
			var code = $( '#bkx-coupon-code' ).val().trim();
			var type = $( '#bkx-discount-type' ).val();
			var value = parseFloat( $( '#bkx-discount-value' ).val() );

			// Validate code.
			if ( ! code ) {
				alert( 'Please enter a coupon code.' );
				e.preventDefault();
				return false;
			}

			// Validate discount value for percentage and fixed types.
			if ( ( type === 'percentage' || type === 'fixed' ) && ( isNaN( value ) || value <= 0 ) ) {
				alert( 'Please enter a valid discount value.' );
				e.preventDefault();
				return false;
			}

			// Validate percentage range.
			if ( type === 'percentage' && value > 100 ) {
				alert( 'Percentage discount cannot exceed 100%.' );
				e.preventDefault();
				return false;
			}

			// Validate dates.
			var startDate = $( '#bkx-start-date' ).val();
			var endDate = $( '#bkx-end-date' ).val();

			if ( startDate && endDate && new Date( startDate ) > new Date( endDate ) ) {
				alert( 'End date must be after start date.' );
				e.preventDefault();
				return false;
			}

			return true;
		}
	};

	$( document ).ready( function() {
		if ( typeof bkxCouponAdmin !== 'undefined' ) {
			BkxCouponAdmin.init();
		}
	} );

} )( jQuery );
