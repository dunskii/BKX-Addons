<?php
/**
 * Distance Pricing Service.
 *
 * @package BookingX\MobileBookings\Services
 * @since   1.0.0
 */

namespace BookingX\MobileBookings\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DistancePricing Class.
 */
class DistancePricing {

	/**
	 * Distance calculator.
	 *
	 * @var DistanceCalculator
	 */
	private $distance_calculator;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param DistanceCalculator $distance_calculator Distance calculator.
	 * @param array              $settings            Plugin settings.
	 */
	public function __construct( DistanceCalculator $distance_calculator, $settings ) {
		$this->distance_calculator = $distance_calculator;
		$this->settings            = $settings;
	}

	/**
	 * Calculate distance fee for a booking.
	 *
	 * @param array $booking_data Booking data including location.
	 * @return float
	 */
	public function calculate_fee( $booking_data ) {
		if ( empty( $this->settings['enable_distance_pricing'] ) ) {
			return 0;
		}

		$distance_miles = $this->get_booking_distance( $booking_data );

		if ( $distance_miles <= 0 ) {
			return 0;
		}

		return $this->calculate_fee_for_distance( $distance_miles, $booking_data );
	}

	/**
	 * Calculate fee for a given distance.
	 *
	 * @param float $distance_miles Distance in miles.
	 * @param array $booking_data   Optional booking data for area-specific pricing.
	 * @return float
	 */
	public function calculate_fee_for_distance( $distance_miles, $booking_data = array() ) {
		// Check for area-specific pricing first.
		$area_pricing = $this->get_area_pricing( $booking_data );

		if ( $area_pricing ) {
			return $this->calculate_with_area_pricing( $distance_miles, $area_pricing );
		}

		// Use global settings.
		return $this->calculate_with_global_pricing( $distance_miles );
	}

	/**
	 * Calculate fee using global pricing settings.
	 *
	 * @param float $distance_miles Distance in miles.
	 * @return float
	 */
	private function calculate_with_global_pricing( $distance_miles ) {
		$base_fee      = floatval( $this->settings['base_travel_fee'] ?? 0 );
		$per_mile_rate = floatval( $this->settings['per_mile_rate'] ?? 0 );
		$free_distance = floatval( $this->settings['free_distance_miles'] ?? 0 );
		$max_distance  = floatval( $this->settings['max_distance_miles'] ?? 0 );

		// Check tiered pricing.
		$tiered_pricing = $this->settings['tiered_pricing'] ?? array();

		if ( ! empty( $tiered_pricing ) ) {
			return $this->calculate_tiered_fee( $distance_miles, $base_fee, $free_distance, $tiered_pricing );
		}

		// Simple per-mile calculation.
		$billable_distance = max( 0, $distance_miles - $free_distance );

		if ( $max_distance > 0 ) {
			$billable_distance = min( $billable_distance, $max_distance - $free_distance );
		}

		return $base_fee + ( $billable_distance * $per_mile_rate );
	}

	/**
	 * Calculate fee using area-specific pricing.
	 *
	 * @param float $distance_miles Distance in miles.
	 * @param array $area_pricing   Area pricing settings.
	 * @return float
	 */
	private function calculate_with_area_pricing( $distance_miles, $area_pricing ) {
		$base_fee      = floatval( $area_pricing['base_travel_fee'] ?? 0 );
		$per_mile_rate = floatval( $area_pricing['per_mile_rate'] ?? 0 );
		$min_distance  = floatval( $area_pricing['min_distance'] ?? 0 );
		$max_distance  = floatval( $area_pricing['max_distance'] ?? 0 );

		// Don't charge if below minimum.
		if ( $min_distance > 0 && $distance_miles < $min_distance ) {
			return 0;
		}

		// Cap at maximum.
		$billable_distance = $distance_miles;
		if ( $max_distance > 0 ) {
			$billable_distance = min( $distance_miles, $max_distance );
		}

		return $base_fee + ( $billable_distance * $per_mile_rate );
	}

	/**
	 * Calculate tiered pricing fee.
	 *
	 * @param float $distance_miles Distance in miles.
	 * @param float $base_fee       Base fee.
	 * @param float $free_distance  Free distance threshold.
	 * @param array $tiers          Pricing tiers.
	 * @return float
	 */
	private function calculate_tiered_fee( $distance_miles, $base_fee, $free_distance, $tiers ) {
		if ( $distance_miles <= $free_distance ) {
			return $base_fee;
		}

		$fee               = $base_fee;
		$remaining_distance = $distance_miles - $free_distance;

		// Sort tiers by min value.
		usort(
			$tiers,
			function ( $a, $b ) {
				return $a['min'] - $b['min'];
			}
		);

		foreach ( $tiers as $tier ) {
			$tier_min  = floatval( $tier['min'] ) - $free_distance;
			$tier_max  = floatval( $tier['max'] ) - $free_distance;
			$tier_rate = floatval( $tier['rate'] );

			if ( $remaining_distance <= 0 ) {
				break;
			}

			// Calculate distance in this tier.
			if ( $remaining_distance > $tier_min ) {
				$distance_in_tier = min( $remaining_distance, $tier_max ) - max( 0, $tier_min );

				if ( $distance_in_tier > 0 ) {
					$fee += $distance_in_tier * $tier_rate;
				}
			}
		}

		return round( $fee, 2 );
	}

	/**
	 * Get booking distance.
	 *
	 * @param array $booking_data Booking data.
	 * @return float Distance in miles.
	 */
	private function get_booking_distance( $booking_data ) {
		// If distance is already provided.
		if ( ! empty( $booking_data['distance_miles'] ) ) {
			return floatval( $booking_data['distance_miles'] );
		}

		// Calculate from locations.
		$provider_location = $this->get_provider_location( $booking_data['provider_id'] ?? $booking_data['seat_id'] ?? 0 );
		$customer_location = $this->get_customer_location( $booking_data );

		if ( ! $provider_location || ! $customer_location ) {
			return 0;
		}

		$result = $this->distance_calculator->calculate_by_coordinates(
			$provider_location['lat'],
			$provider_location['lng'],
			$customer_location['lat'],
			$customer_location['lng']
		);

		if ( is_wp_error( $result ) ) {
			return 0;
		}

		return $result['distance_miles'];
	}

	/**
	 * Get provider location.
	 *
	 * @param int $provider_id Provider ID.
	 * @return array|null
	 */
	private function get_provider_location( $provider_id ) {
		if ( ! $provider_id ) {
			return null;
		}

		global $wpdb;

		$location = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT latitude as lat, longitude as lng
				FROM {$wpdb->prefix}bkx_locations
				WHERE provider_id = %d AND location_type = 'provider'
				ORDER BY created_at DESC
				LIMIT 1",
				$provider_id
			),
			ARRAY_A
		);

		return $location;
	}

	/**
	 * Get customer location from booking data.
	 *
	 * @param array $booking_data Booking data.
	 * @return array|null
	 */
	private function get_customer_location( $booking_data ) {
		// Direct coordinates.
		if ( ! empty( $booking_data['customer_lat'] ) && ! empty( $booking_data['customer_lng'] ) ) {
			return array(
				'lat' => floatval( $booking_data['customer_lat'] ),
				'lng' => floatval( $booking_data['customer_lng'] ),
			);
		}

		// From booking ID.
		if ( ! empty( $booking_data['booking_id'] ) ) {
			global $wpdb;

			$location = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT latitude as lat, longitude as lng
					FROM {$wpdb->prefix}bkx_locations
					WHERE booking_id = %d AND location_type = 'customer'
					LIMIT 1",
					$booking_data['booking_id']
				),
				ARRAY_A
			);

			return $location;
		}

		return null;
	}

	/**
	 * Get area-specific pricing if applicable.
	 *
	 * @param array $booking_data Booking data.
	 * @return array|null
	 */
	private function get_area_pricing( $booking_data ) {
		$customer_location = $this->get_customer_location( $booking_data );

		if ( ! $customer_location ) {
			return null;
		}

		global $wpdb;

		// Get matching service area with distance pricing enabled.
		$areas = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}bkx_service_areas
			WHERE status = 'active' AND distance_pricing_enabled = 1",
			ARRAY_A
		);

		foreach ( $areas as $area ) {
			if ( $this->is_location_in_area( $customer_location, $area ) ) {
				return $area;
			}
		}

		return null;
	}

	/**
	 * Check if location is within an area.
	 *
	 * @param array $location Location coordinates.
	 * @param array $area     Service area.
	 * @return bool
	 */
	private function is_location_in_area( $location, $area ) {
		if ( 'radius' === $area['area_type'] ) {
			$distance = $this->distance_calculator->calculate_by_coordinates(
				$location['lat'],
				$location['lng'],
				$area['center_latitude'],
				$area['center_longitude']
			);

			if ( ! is_wp_error( $distance ) ) {
				return $distance['distance_miles'] <= $area['radius_miles'];
			}
		}

		return false;
	}

	/**
	 * Get pricing breakdown for display.
	 *
	 * @param float $distance_miles Distance in miles.
	 * @param array $booking_data   Optional booking data.
	 * @return array
	 */
	public function get_pricing_breakdown( $distance_miles, $booking_data = array() ) {
		$area_pricing = $this->get_area_pricing( $booking_data );

		$breakdown = array(
			'distance_miles'    => round( $distance_miles, 2 ),
			'pricing_type'      => $area_pricing ? 'area' : 'global',
			'base_fee'          => 0,
			'distance_fee'      => 0,
			'total_fee'         => 0,
			'free_distance'     => 0,
			'billable_distance' => 0,
			'per_mile_rate'     => 0,
		);

		if ( $area_pricing ) {
			$breakdown['base_fee']      = floatval( $area_pricing['base_travel_fee'] ?? 0 );
			$breakdown['per_mile_rate'] = floatval( $area_pricing['per_mile_rate'] ?? 0 );
			$breakdown['area_name']     = $area_pricing['name'];

			$min_distance = floatval( $area_pricing['min_distance'] ?? 0 );
			$max_distance = floatval( $area_pricing['max_distance'] ?? 0 );

			if ( $distance_miles >= $min_distance ) {
				$billable = $max_distance > 0 ? min( $distance_miles, $max_distance ) : $distance_miles;
				$breakdown['billable_distance'] = $billable;
				$breakdown['distance_fee']      = $billable * $breakdown['per_mile_rate'];
			}
		} else {
			$breakdown['base_fee']       = floatval( $this->settings['base_travel_fee'] ?? 0 );
			$breakdown['per_mile_rate']  = floatval( $this->settings['per_mile_rate'] ?? 0 );
			$breakdown['free_distance']  = floatval( $this->settings['free_distance_miles'] ?? 0 );

			$billable                        = max( 0, $distance_miles - $breakdown['free_distance'] );
			$breakdown['billable_distance'] = $billable;
			$breakdown['distance_fee']      = $billable * $breakdown['per_mile_rate'];
		}

		$breakdown['total_fee'] = round( $breakdown['base_fee'] + $breakdown['distance_fee'], 2 );

		return $breakdown;
	}

	/**
	 * Format fee for display.
	 *
	 * @param float $fee Fee amount.
	 * @return string
	 */
	public function format_fee( $fee ) {
		$symbol = function_exists( 'bkx_get_currency_symbol' ) ? bkx_get_currency_symbol() : '$';
		return $symbol . number_format( $fee, 2 );
	}

	/**
	 * Check if distance pricing is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->settings['enable_distance_pricing'] );
	}
}
