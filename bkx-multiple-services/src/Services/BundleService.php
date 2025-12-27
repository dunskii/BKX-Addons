<?php
/**
 * Bundle Service
 *
 * Handles bundle pricing and service combinations.
 *
 * @package BookingX\MultipleServices
 * @since   1.0.0
 */

namespace BookingX\MultipleServices\Services;

use BookingX\MultipleServices\MultipleServicesAddon;

/**
 * Bundle Service class.
 *
 * @since 1.0.0
 */
class BundleService {

	/**
	 * Addon instance.
	 *
	 * @var MultipleServicesAddon
	 */
	protected MultipleServicesAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param MultipleServicesAddon $addon Addon instance.
	 */
	public function __construct( MultipleServicesAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Calculate bundle price for selected services.
	 *
	 * @since 1.0.0
	 * @param array  $service_ids      Array of service IDs.
	 * @param bool   $apply_discount   Whether to apply bundle discount.
	 * @param string $discount_type    Discount type (percentage or fixed).
	 * @param float  $discount_value   Discount value.
	 * @return float Total price.
	 */
	public function calculate_bundle_price( array $service_ids, bool $apply_discount, string $discount_type, float $discount_value ): float {
		$total_price = 0;

		foreach ( $service_ids as $service_id ) {
			$price = get_post_meta( $service_id, 'price', true );
			$total_price += floatval( $price );
		}

		// Apply bundle discount if applicable
		if ( $apply_discount && count( $service_ids ) > 1 ) {
			if ( 'percentage' === $discount_type ) {
				$discount = $total_price * ( $discount_value / 100 );
				$total_price -= $discount;
			} elseif ( 'fixed' === $discount_type ) {
				$total_price -= $discount_value;
			}

			// Ensure price doesn't go below zero
			$total_price = max( 0, $total_price );
		}

		return round( $total_price, 2 );
	}

	/**
	 * Get available service combinations.
	 *
	 * @since 1.0.0
	 * @param int $base_id Base service ID.
	 * @return array Available combinations.
	 */
	public function get_available_combinations( int $base_id ): array {
		$args = array(
			'post_type'      => 'bkx_base',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post__not_in'   => array( $base_id ),
		);

		$services = get_posts( $args );
		$combinations = array();

		foreach ( $services as $service ) {
			$combinations[] = array(
				'id'    => $service->ID,
				'title' => $service->post_title,
				'price' => get_post_meta( $service->ID, 'price', true ),
				'duration' => get_post_meta( $service->ID, 'duration', true ),
			);
		}

		return $combinations;
	}

	/**
	 * Check if service combination is allowed.
	 *
	 * @since 1.0.0
	 * @param array $service_ids        Selected service IDs.
	 * @param array $restricted_combos  Restricted combinations.
	 * @return bool Whether combination is allowed.
	 */
	public function is_combination_allowed( array $service_ids, array $restricted_combos ): bool {
		sort( $service_ids );

		foreach ( $restricted_combos as $restricted ) {
			sort( $restricted );
			if ( $service_ids === $restricted ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check availability for multiple services.
	 *
	 * @since 1.0.0
	 * @param array  $service_ids         Service IDs.
	 * @param string $date                Booking date.
	 * @param int    $seat_id             Resource/seat ID.
	 * @param bool   $require_same_resource Whether all services must use same resource.
	 * @return bool Whether services are available.
	 */
	public function check_services_availability( array $service_ids, string $date, int $seat_id, bool $require_same_resource ): bool {
		// Check each service for availability
		foreach ( $service_ids as $service_id ) {
			// Get service-specific seat requirements
			$service_seats = $this->get_service_seats( $service_id );

			if ( $require_same_resource ) {
				// All services must be available with the same resource
				if ( ! in_array( $seat_id, $service_seats, true ) ) {
					return false;
				}
			}

			// Check if service is available at the given date/time
			$available = $this->check_service_availability( $service_id, $date, $seat_id );
			if ( ! $available ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get seats/resources associated with a service.
	 *
	 * @since 1.0.0
	 * @param int $service_id Service ID.
	 * @return array Seat IDs.
	 */
	protected function get_service_seats( int $service_id ): array {
		$seats = get_post_meta( $service_id, 'bkx_seats', true );
		return is_array( $seats ) ? $seats : array();
	}

	/**
	 * Check if a single service is available.
	 *
	 * @since 1.0.0
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @param int    $seat_id    Seat ID.
	 * @return bool
	 */
	protected function check_service_availability( int $service_id, string $date, int $seat_id ): bool {
		// Use BookingX core availability checking
		if ( function_exists( 'bkx_check_availability' ) ) {
			return bkx_check_availability( $service_id, $date, $seat_id );
		}

		// Fallback: assume available
		return true;
	}
}
