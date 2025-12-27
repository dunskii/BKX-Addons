<?php
/**
 * Discount Calculator
 *
 * Handles discount calculations for different coupon types.
 *
 * @package BookingX\CouponCodes\Services
 * @since   1.0.0
 */

namespace BookingX\CouponCodes\Services;

use BookingX\CouponCodes\CouponCodesAddon;

/**
 * Discount calculator class.
 *
 * @since 1.0.0
 */
class DiscountCalculator {

	/**
	 * Addon instance.
	 *
	 * @var CouponCodesAddon
	 */
	protected CouponCodesAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param CouponCodesAddon $addon Addon instance.
	 */
	public function __construct( CouponCodesAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Calculate discount amount.
	 *
	 * @param array $coupon Coupon data.
	 * @param float $subtotal Subtotal before discount.
	 * @param array $booking_data Booking data.
	 * @return float Discount amount.
	 */
	public function calculate( array $coupon, float $subtotal, array $booking_data = array() ): float {
		$discount = 0;

		switch ( $coupon['discount_type'] ) {
			case 'percentage':
				$discount = $this->calculate_percentage( $coupon, $subtotal );
				break;

			case 'fixed':
				$discount = $this->calculate_fixed( $coupon, $subtotal );
				break;

			case 'free_service':
				$discount = $this->calculate_free_service( $coupon, $booking_data );
				break;

			case 'free_extra':
				$discount = $this->calculate_free_extra( $coupon, $booking_data );
				break;
		}

		// Apply max discount cap if set.
		if ( ! empty( $coupon['max_discount'] ) && $coupon['max_discount'] > 0 ) {
			$discount = min( $discount, $coupon['max_discount'] );
		}

		// Discount cannot exceed subtotal.
		$discount = min( $discount, $subtotal );

		/**
		 * Filter the calculated discount amount.
		 *
		 * @since 1.0.0
		 * @param float $discount     Calculated discount.
		 * @param array $coupon       Coupon data.
		 * @param float $subtotal     Subtotal before discount.
		 * @param array $booking_data Booking data.
		 */
		return apply_filters( 'bkx_coupon_discount_amount', $discount, $coupon, $subtotal, $booking_data );
	}

	/**
	 * Calculate percentage discount.
	 *
	 * @param array $coupon Coupon data.
	 * @param float $subtotal Subtotal.
	 * @return float Discount amount.
	 */
	protected function calculate_percentage( array $coupon, float $subtotal ): float {
		$percentage = floatval( $coupon['discount_value'] );

		// Validate percentage range.
		$percentage = max( 0, min( 100, $percentage ) );

		return round( $subtotal * ( $percentage / 100 ), 2 );
	}

	/**
	 * Calculate fixed amount discount.
	 *
	 * @param array $coupon Coupon data.
	 * @param float $subtotal Subtotal.
	 * @return float Discount amount.
	 */
	protected function calculate_fixed( array $coupon, float $subtotal ): float {
		$discount = floatval( $coupon['discount_value'] );

		// Cannot discount more than subtotal.
		return min( $discount, $subtotal );
	}

	/**
	 * Calculate free service discount.
	 *
	 * @param array $coupon Coupon data.
	 * @param array $booking_data Booking data.
	 * @return float Discount amount (service price).
	 */
	protected function calculate_free_service( array $coupon, array $booking_data ): float {
		if ( empty( $booking_data['service_id'] ) ) {
			return 0;
		}

		// Get service price.
		$service_price = $this->get_service_price( $booking_data['service_id'] );

		return floatval( $service_price );
	}

	/**
	 * Calculate free extra/add-on discount.
	 *
	 * @param array $coupon Coupon data.
	 * @param array $booking_data Booking data.
	 * @return float Discount amount.
	 */
	protected function calculate_free_extra( array $coupon, array $booking_data ): float {
		if ( empty( $booking_data['extras'] ) ) {
			return 0;
		}

		// If specific extra is defined in coupon, use that.
		if ( ! empty( $coupon['free_extra_id'] ) ) {
			if ( in_array( $coupon['free_extra_id'], $booking_data['extras'], true ) ) {
				return $this->get_extra_price( $coupon['free_extra_id'] );
			}
			return 0;
		}

		// Otherwise, discount the most expensive extra.
		$max_price = 0;

		foreach ( $booking_data['extras'] as $extra_id ) {
			$price = $this->get_extra_price( $extra_id );
			if ( $price > $max_price ) {
				$max_price = $price;
			}
		}

		return floatval( $max_price );
	}

	/**
	 * Get service price.
	 *
	 * @param int $service_id Service ID.
	 * @return float Price.
	 */
	protected function get_service_price( int $service_id ): float {
		$price = get_post_meta( $service_id, 'base_price', true );

		if ( empty( $price ) ) {
			$price = get_post_meta( $service_id, '_price', true );
		}

		return floatval( $price );
	}

	/**
	 * Get extra/add-on price.
	 *
	 * @param int $extra_id Extra ID.
	 * @return float Price.
	 */
	protected function get_extra_price( int $extra_id ): float {
		$price = get_post_meta( $extra_id, 'addition_price', true );

		if ( empty( $price ) ) {
			$price = get_post_meta( $extra_id, '_price', true );
		}

		return floatval( $price );
	}

	/**
	 * Get formatted discount text for display.
	 *
	 * @param array $coupon Coupon data.
	 * @return string Formatted discount text.
	 */
	public function get_discount_text( array $coupon ): string {
		switch ( $coupon['discount_type'] ) {
			case 'percentage':
				return sprintf(
					/* translators: %s: percentage value */
					__( '%s%% off', 'bkx-coupon-codes' ),
					number_format( $coupon['discount_value'], 0 )
				);

			case 'fixed':
				return sprintf(
					/* translators: %s: discount amount */
					__( '%s off', 'bkx-coupon-codes' ),
					wc_price( $coupon['discount_value'] )
				);

			case 'free_service':
				return __( 'Free service', 'bkx-coupon-codes' );

			case 'free_extra':
				return __( 'Free add-on', 'bkx-coupon-codes' );

			default:
				return '';
		}
	}

	/**
	 * Validate discount type.
	 *
	 * @param string $type Discount type.
	 * @return bool True if valid.
	 */
	public function is_valid_type( string $type ): bool {
		$allowed_types = array();

		if ( $this->addon->get_setting( 'allow_percentage', true ) ) {
			$allowed_types[] = 'percentage';
		}
		if ( $this->addon->get_setting( 'allow_fixed_amount', true ) ) {
			$allowed_types[] = 'fixed';
		}
		if ( $this->addon->get_setting( 'allow_free_service', true ) ) {
			$allowed_types[] = 'free_service';
		}
		if ( $this->addon->get_setting( 'allow_free_extra', true ) ) {
			$allowed_types[] = 'free_extra';
		}

		return in_array( $type, $allowed_types, true );
	}

	/**
	 * Get available discount types.
	 *
	 * @return array Discount types.
	 */
	public function get_available_types(): array {
		$types = array();

		if ( $this->addon->get_setting( 'allow_percentage', true ) ) {
			$types['percentage'] = __( 'Percentage discount', 'bkx-coupon-codes' );
		}
		if ( $this->addon->get_setting( 'allow_fixed_amount', true ) ) {
			$types['fixed'] = __( 'Fixed amount discount', 'bkx-coupon-codes' );
		}
		if ( $this->addon->get_setting( 'allow_free_service', true ) ) {
			$types['free_service'] = __( 'Free service', 'bkx-coupon-codes' );
		}
		if ( $this->addon->get_setting( 'allow_free_extra', true ) ) {
			$types['free_extra'] = __( 'Free add-on/extra', 'bkx-coupon-codes' );
		}

		return $types;
	}
}
