<?php
/**
 * Distance Calculator Service.
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
 * DistanceCalculator Class.
 */
class DistanceCalculator {

	/**
	 * Google Maps service.
	 *
	 * @var GoogleMapsService
	 */
	private $maps_service;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param GoogleMapsService $maps_service Google Maps service.
	 * @param array             $settings     Plugin settings.
	 */
	public function __construct( GoogleMapsService $maps_service, $settings ) {
		$this->maps_service = $maps_service;
		$this->settings     = $settings;
	}

	/**
	 * Calculate distance by addresses.
	 *
	 * @param string $from_address Origin address.
	 * @param string $to_address   Destination address.
	 * @return array|\WP_Error
	 */
	public function calculate_by_addresses( $from_address, $to_address ) {
		$method = $this->settings['calculation_method'] ?? 'google_maps';

		if ( 'google_maps' === $method && $this->maps_service->is_configured() ) {
			return $this->calculate_via_google_maps( $from_address, $to_address );
		}

		// Fallback to geocode + haversine.
		$from_geocoded = $this->maps_service->geocode( $from_address );
		$to_geocoded   = $this->maps_service->geocode( $to_address );

		if ( is_wp_error( $from_geocoded ) ) {
			return $from_geocoded;
		}

		if ( is_wp_error( $to_geocoded ) ) {
			return $to_geocoded;
		}

		return $this->calculate_by_coordinates(
			$from_geocoded['lat'],
			$from_geocoded['lng'],
			$to_geocoded['lat'],
			$to_geocoded['lng']
		);
	}

	/**
	 * Calculate distance by coordinates.
	 *
	 * @param float $from_lat Origin latitude.
	 * @param float $from_lng Origin longitude.
	 * @param float $to_lat   Destination latitude.
	 * @param float $to_lng   Destination longitude.
	 * @return array|\WP_Error
	 */
	public function calculate_by_coordinates( $from_lat, $from_lng, $to_lat, $to_lng ) {
		$method = $this->settings['calculation_method'] ?? 'google_maps';
		$unit   = $this->settings['distance_unit'] ?? 'miles';

		if ( 'google_maps' === $method && $this->maps_service->is_configured() ) {
			$origin      = "{$from_lat},{$from_lng}";
			$destination = "{$to_lat},{$to_lng}";

			return $this->calculate_via_google_maps( $origin, $destination );
		}

		// Haversine calculation.
		$distance_miles = $this->maps_service->haversine_distance( $from_lat, $from_lng, $to_lat, $to_lng, 'miles' );
		$distance_km    = $this->maps_service->haversine_distance( $from_lat, $from_lng, $to_lat, $to_lng, 'km' );

		// Estimate duration (average 30 mph for urban driving).
		$duration_minutes = round( $distance_miles / 30 * 60 );

		return array(
			'distance_miles'   => round( $distance_miles, 2 ),
			'distance_km'      => round( $distance_km, 2 ),
			'distance_text'    => 'miles' === $unit
				? round( $distance_miles, 1 ) . ' mi'
				: round( $distance_km, 1 ) . ' km',
			'duration_minutes' => $duration_minutes,
			'duration_text'    => $this->format_duration( $duration_minutes ),
			'duration_in_traffic_minutes' => null,
			'duration_in_traffic_text'    => null,
			'method'           => 'haversine',
		);
	}

	/**
	 * Calculate via Google Maps Distance Matrix API.
	 *
	 * @param string $origin      Origin address or coordinates.
	 * @param string $destination Destination address or coordinates.
	 * @return array|\WP_Error
	 */
	private function calculate_via_google_maps( $origin, $destination ) {
		$unit = $this->settings['distance_unit'] ?? 'miles';

		$result = $this->maps_service->distance_matrix(
			array( $origin ),
			array( $destination ),
			array(
				'units' => 'miles' === $unit ? 'imperial' : 'metric',
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$element = $result['rows'][0]['elements'][0];

		if ( 'OK' !== $element['status'] ) {
			return new \WP_Error(
				'route_not_found',
				__( 'Unable to calculate route between these locations.', 'bkx-mobile-bookings' )
			);
		}

		$distance_meters  = $element['distance']['value'];
		$duration_seconds = $element['duration']['value'];

		$distance_miles = $distance_meters / 1609.34;
		$distance_km    = $distance_meters / 1000;

		$data = array(
			'distance_miles'   => round( $distance_miles, 2 ),
			'distance_km'      => round( $distance_km, 2 ),
			'distance_text'    => $element['distance']['text'],
			'duration_minutes' => round( $duration_seconds / 60 ),
			'duration_text'    => $element['duration']['text'],
			'method'           => 'google_maps',
		);

		// Add traffic duration if available.
		if ( isset( $element['duration_in_traffic'] ) ) {
			$traffic_seconds                       = $element['duration_in_traffic']['value'];
			$data['duration_in_traffic_minutes']   = round( $traffic_seconds / 60 );
			$data['duration_in_traffic_text']      = $element['duration_in_traffic']['text'];
		}

		return $data;
	}

	/**
	 * Calculate distance between multiple points.
	 *
	 * @param array $points Array of points (each with lat, lng).
	 * @return array|\WP_Error
	 */
	public function calculate_multi_point( $points ) {
		if ( count( $points ) < 2 ) {
			return new \WP_Error( 'insufficient_points', __( 'At least 2 points required.', 'bkx-mobile-bookings' ) );
		}

		$total_distance_miles = 0;
		$total_distance_km    = 0;
		$total_duration       = 0;
		$segments             = array();

		for ( $i = 0; $i < count( $points ) - 1; $i++ ) {
			$from = $points[ $i ];
			$to   = $points[ $i + 1 ];

			$result = $this->calculate_by_coordinates(
				$from['lat'],
				$from['lng'],
				$to['lat'],
				$to['lng']
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$total_distance_miles += $result['distance_miles'];
			$total_distance_km    += $result['distance_km'];
			$total_duration       += $result['duration_minutes'];

			$segments[] = array(
				'from'             => $from,
				'to'               => $to,
				'distance_miles'   => $result['distance_miles'],
				'distance_km'      => $result['distance_km'],
				'duration_minutes' => $result['duration_minutes'],
			);
		}

		$unit = $this->settings['distance_unit'] ?? 'miles';

		return array(
			'total_distance_miles' => round( $total_distance_miles, 2 ),
			'total_distance_km'    => round( $total_distance_km, 2 ),
			'total_distance_text'  => 'miles' === $unit
				? round( $total_distance_miles, 1 ) . ' mi'
				: round( $total_distance_km, 1 ) . ' km',
			'total_duration_minutes' => $total_duration,
			'total_duration_text'    => $this->format_duration( $total_duration ),
			'segments'               => $segments,
			'point_count'            => count( $points ),
		);
	}

	/**
	 * Check if distance is within maximum allowed.
	 *
	 * @param float $distance_miles Distance in miles.
	 * @return bool
	 */
	public function is_within_max_distance( $distance_miles ) {
		$max_distance = floatval( $this->settings['max_distance_miles'] ?? 50 );

		if ( $max_distance <= 0 ) {
			return true;
		}

		return $distance_miles <= $max_distance;
	}

	/**
	 * Get estimated travel time with buffer.
	 *
	 * @param int $base_duration_minutes Base duration in minutes.
	 * @return int
	 */
	public function get_buffered_travel_time( $base_duration_minutes ) {
		if ( empty( $this->settings['add_travel_buffer'] ) ) {
			return $base_duration_minutes;
		}

		$buffer_percentage = absint( $this->settings['travel_buffer_percentage'] ?? 20 );
		$min_buffer        = absint( $this->settings['min_buffer_minutes'] ?? 10 );
		$max_buffer        = absint( $this->settings['max_buffer_minutes'] ?? 60 );

		$buffer = $base_duration_minutes * ( $buffer_percentage / 100 );
		$buffer = max( $min_buffer, min( $max_buffer, $buffer ) );

		$total = $base_duration_minutes + $buffer;

		// Round up to nearest 5 minutes.
		return (int) ceil( $total / 5 ) * 5;
	}

	/**
	 * Format duration for display.
	 *
	 * @param int $minutes Duration in minutes.
	 * @return string
	 */
	public function format_duration( $minutes ) {
		if ( $minutes < 60 ) {
			return sprintf(
				/* translators: %d: number of minutes */
				_n( '%d min', '%d mins', $minutes, 'bkx-mobile-bookings' ),
				$minutes
			);
		}

		$hours = floor( $minutes / 60 );
		$mins  = $minutes % 60;

		if ( $mins === 0 ) {
			return sprintf(
				/* translators: %d: number of hours */
				_n( '%d hour', '%d hours', $hours, 'bkx-mobile-bookings' ),
				$hours
			);
		}

		return sprintf(
			/* translators: 1: number of hours, 2: number of minutes */
			__( '%1$d hr %2$d min', 'bkx-mobile-bookings' ),
			$hours,
			$mins
		);
	}

	/**
	 * Convert miles to kilometers.
	 *
	 * @param float $miles Distance in miles.
	 * @return float
	 */
	public function miles_to_km( $miles ) {
		return $miles * 1.60934;
	}

	/**
	 * Convert kilometers to miles.
	 *
	 * @param float $km Distance in kilometers.
	 * @return float
	 */
	public function km_to_miles( $km ) {
		return $km / 1.60934;
	}
}
