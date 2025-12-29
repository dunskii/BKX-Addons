<?php
/**
 * Location Tracker Service.
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
 * LocationTracker Class.
 */
class LocationTracker {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Save a location.
	 *
	 * @param array $data Location data.
	 * @return int|\WP_Error
	 */
	public function save_location( $data ) {
		global $wpdb;

		$location_data = array(
			'booking_id'        => absint( $data['booking_id'] ?? 0 ) ?: null,
			'customer_id'       => absint( $data['customer_id'] ?? 0 ) ?: null,
			'provider_id'       => absint( $data['provider_id'] ?? 0 ) ?: null,
			'location_type'     => sanitize_text_field( $data['location_type'] ?? 'customer' ),
			'address'           => sanitize_text_field( $data['address'] ?? '' ),
			'address_line_2'    => sanitize_text_field( $data['address_line_2'] ?? '' ),
			'city'              => sanitize_text_field( $data['city'] ?? '' ),
			'state'             => sanitize_text_field( $data['state'] ?? '' ),
			'zip_code'          => sanitize_text_field( $data['zip_code'] ?? '' ),
			'country'           => sanitize_text_field( $data['country'] ?? '' ),
			'latitude'          => floatval( $data['latitude'] ?? 0 ) ?: null,
			'longitude'         => floatval( $data['longitude'] ?? 0 ) ?: null,
			'formatted_address' => sanitize_text_field( $data['formatted_address'] ?? '' ),
			'place_id'          => sanitize_text_field( $data['place_id'] ?? '' ),
			'location_notes'    => sanitize_textarea_field( $data['location_notes'] ?? '' ),
			'is_verified'       => isset( $data['is_verified'] ) ? 1 : 0,
			'updated_at'        => current_time( 'mysql' ),
		);

		$formats = array(
			'%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
			'%f', '%f', '%s', '%s', '%s', '%d', '%s',
		);

		$table = $wpdb->prefix . 'bkx_locations';

		if ( ! empty( $data['id'] ) ) {
			$result = $wpdb->update(
				$table,
				$location_data,
				array( 'id' => absint( $data['id'] ) ),
				$formats,
				array( '%d' )
			);

			return false === $result
				? new \WP_Error( 'db_error', __( 'Failed to update location.', 'bkx-mobile-bookings' ) )
				: absint( $data['id'] );
		}

		$location_data['created_at'] = current_time( 'mysql' );
		$formats[]                   = '%s';

		$result = $wpdb->insert( $table, $location_data, $formats );

		return ! $result
			? new \WP_Error( 'db_error', __( 'Failed to save location.', 'bkx-mobile-bookings' ) )
			: $wpdb->insert_id;
	}

	/**
	 * Get location by booking ID.
	 *
	 * @param int    $booking_id    Booking ID.
	 * @param string $location_type Location type.
	 * @return array|null
	 */
	public function get_booking_location( $booking_id, $location_type = 'customer' ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_locations
				WHERE booking_id = %d AND location_type = %s
				LIMIT 1",
				$booking_id,
				$location_type
			),
			ARRAY_A
		);
	}

	/**
	 * Record GPS check-in.
	 *
	 * @param int    $booking_id   Booking ID.
	 * @param int    $provider_id  Provider ID.
	 * @param float  $lat          Latitude.
	 * @param float  $lng          Longitude.
	 * @param float  $accuracy     GPS accuracy in meters.
	 * @param string $checkin_type Check-in type (arrival, departure).
	 * @param string $notes        Optional notes.
	 * @return array|\WP_Error
	 */
	public function record_checkin( $booking_id, $provider_id, $lat, $lng, $accuracy, $checkin_type = 'arrival', $notes = '' ) {
		global $wpdb;

		// Get booking location.
		$booking_location = $this->get_booking_location( $booking_id );

		// Calculate distance from booking location.
		$distance_from_location = 0;
		$is_verified            = 0;
		$verification_radius    = floatval( $this->settings['verification_radius_meters'] ?? 100 );

		if ( $booking_location && ! empty( $booking_location['latitude'] ) ) {
			$distance_from_location = $this->calculate_distance_meters(
				$lat,
				$lng,
				$booking_location['latitude'],
				$booking_location['longitude']
			);

			$is_verified = $distance_from_location <= $verification_radius ? 1 : 0;
		}

		// Check if GPS verification is required.
		$require_verification = ! empty( $this->settings['require_gps_verification'] );

		if ( $require_verification && ! $is_verified ) {
			return new \WP_Error(
				'location_mismatch',
				sprintf(
					/* translators: %d: distance in meters */
					__( 'You are %d meters from the booking location. Please move closer to check in.', 'bkx-mobile-bookings' ),
					round( $distance_from_location )
				)
			);
		}

		$checkin_data = array(
			'booking_id'             => $booking_id,
			'provider_id'            => $provider_id,
			'checkin_type'           => $checkin_type,
			'latitude'               => $lat,
			'longitude'              => $lng,
			'accuracy'               => $accuracy,
			'distance_from_location' => $distance_from_location,
			'is_verified'            => $is_verified,
			'verification_radius'    => $verification_radius,
			'checkin_time'           => current_time( 'mysql' ),
			'device_info'            => $this->get_device_info(),
			'notes'                  => sanitize_textarea_field( $notes ),
		);

		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_gps_checkins',
			$checkin_data,
			array( '%d', '%d', '%s', '%f', '%f', '%f', '%f', '%d', '%f', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to record check-in.', 'bkx-mobile-bookings' ) );
		}

		// Update booking status if arrival check-in.
		if ( 'arrival' === $checkin_type ) {
			$this->update_booking_status( $booking_id, 'arrived' );
		}

		return array(
			'checkin_id'             => $wpdb->insert_id,
			'is_verified'            => (bool) $is_verified,
			'distance_from_location' => round( $distance_from_location ),
			'message'                => $is_verified
				? __( 'Check-in recorded successfully.', 'bkx-mobile-bookings' )
				: __( 'Check-in recorded, but location could not be verified.', 'bkx-mobile-bookings' ),
		);
	}

	/**
	 * Update provider's real-time location.
	 *
	 * @param int   $provider_id Provider ID.
	 * @param float $lat         Latitude.
	 * @param float $lng         Longitude.
	 * @param float $accuracy    GPS accuracy.
	 * @param float $heading     Direction of travel.
	 * @param float $speed       Speed in m/s.
	 * @return bool|\WP_Error
	 */
	public function update_provider_location( $provider_id, $lat, $lng, $accuracy = 0, $heading = 0, $speed = 0 ) {
		global $wpdb;

		$data = array(
			'provider_id'  => $provider_id,
			'latitude'     => $lat,
			'longitude'    => $lng,
			'accuracy'     => $accuracy,
			'heading'      => $heading,
			'speed'        => $speed,
			'is_available' => 1,
			'last_updated' => current_time( 'mysql' ),
		);

		// Upsert.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bkx_provider_locations WHERE provider_id = %d",
				$provider_id
			)
		);

		if ( $existing ) {
			$result = $wpdb->update(
				$wpdb->prefix . 'bkx_provider_locations',
				$data,
				array( 'provider_id' => $provider_id ),
				array( '%d', '%f', '%f', '%f', '%f', '%f', '%d', '%s' ),
				array( '%d' )
			);
		} else {
			$result = $wpdb->insert(
				$wpdb->prefix . 'bkx_provider_locations',
				$data,
				array( '%d', '%f', '%f', '%f', '%f', '%f', '%d', '%s' )
			);
		}

		return false !== $result;
	}

	/**
	 * Get provider's current location.
	 *
	 * @param int $provider_id Provider ID.
	 * @return array|null
	 */
	public function get_provider_location( $provider_id ) {
		global $wpdb;

		$location = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_provider_locations
				WHERE provider_id = %d",
				$provider_id
			),
			ARRAY_A
		);

		if ( $location ) {
			// Check if location is stale (older than update interval * 3).
			$interval    = absint( $this->settings['location_update_interval'] ?? 300 );
			$stale_after = $interval * 3;
			$last_update = strtotime( $location['last_updated'] );

			$location['is_stale'] = ( time() - $last_update ) > $stale_after;
			$location['age_seconds'] = time() - $last_update;
		}

		return $location;
	}

	/**
	 * Get nearby providers.
	 *
	 * @param float $lat        Customer latitude.
	 * @param float $lng        Customer longitude.
	 * @param float $radius     Search radius in miles.
	 * @param int   $service_id Optional service ID filter.
	 * @return array
	 */
	public function get_nearby_providers( $lat, $lng, $radius = 10, $service_id = 0 ) {
		global $wpdb;

		// Convert radius to approximate degrees (1 degree ~ 69 miles).
		$lat_range = $radius / 69;
		$lng_range = $radius / ( 69 * cos( deg2rad( $lat ) ) );

		$min_lat = $lat - $lat_range;
		$max_lat = $lat + $lat_range;
		$min_lng = $lng - $lng_range;
		$max_lng = $lng + $lng_range;

		// Get recently active providers within bounding box.
		$stale_threshold = current_time( 'mysql', true );
		$stale_threshold = gmdate( 'Y-m-d H:i:s', strtotime( $stale_threshold ) - 900 ); // 15 minutes.

		$providers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pl.*, p.post_title as provider_name
				FROM {$wpdb->prefix}bkx_provider_locations pl
				JOIN {$wpdb->posts} p ON pl.provider_id = p.ID
				WHERE pl.latitude BETWEEN %f AND %f
				AND pl.longitude BETWEEN %f AND %f
				AND pl.is_available = 1
				AND pl.last_updated >= %s
				AND p.post_type = 'bkx_seat'
				AND p.post_status = 'publish'",
				$min_lat,
				$max_lat,
				$min_lng,
				$max_lng,
				$stale_threshold
			),
			ARRAY_A
		);

		// Calculate actual distances and filter.
		$nearby = array();

		foreach ( $providers as $provider ) {
			$distance = $this->calculate_distance_miles(
				$lat,
				$lng,
				$provider['latitude'],
				$provider['longitude']
			);

			if ( $distance <= $radius ) {
				$provider['distance_miles'] = round( $distance, 2 );
				$provider['distance_text']  = round( $distance, 1 ) . ' mi';

				// Estimate ETA (assuming average 25 mph).
				$eta_minutes            = round( $distance / 25 * 60 );
				$provider['eta_minutes'] = $eta_minutes;
				$provider['eta_text']    = $eta_minutes < 60
					? $eta_minutes . ' min'
					: round( $eta_minutes / 60, 1 ) . ' hr';

				$nearby[] = $provider;
			}
		}

		// Sort by distance.
		usort(
			$nearby,
			function ( $a, $b ) {
				return $a['distance_miles'] <=> $b['distance_miles'];
			}
		);

		return $nearby;
	}

	/**
	 * Get check-in history for a booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	public function get_checkin_history( $booking_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_gps_checkins
				WHERE booking_id = %d
				ORDER BY checkin_time ASC",
				$booking_id
			),
			ARRAY_A
		);
	}

	/**
	 * Calculate ETA for provider to reach booking location.
	 *
	 * @param int $provider_id Provider ID.
	 * @param int $booking_id  Booking ID.
	 * @return array|null
	 */
	public function calculate_eta( $provider_id, $booking_id ) {
		$provider_location = $this->get_provider_location( $provider_id );
		$booking_location  = $this->get_booking_location( $booking_id );

		if ( ! $provider_location || ! $booking_location ) {
			return null;
		}

		if ( empty( $booking_location['latitude'] ) ) {
			return null;
		}

		$distance = $this->calculate_distance_miles(
			$provider_location['latitude'],
			$provider_location['longitude'],
			$booking_location['latitude'],
			$booking_location['longitude']
		);

		// Estimate using speed if available, otherwise assume 25 mph.
		$speed_mph = ( $provider_location['speed'] ?? 0 ) * 2.237; // m/s to mph.

		if ( $speed_mph < 5 ) {
			$speed_mph = 25; // Default assumption.
		}

		$eta_minutes = round( $distance / $speed_mph * 60 );

		return array(
			'distance_miles' => round( $distance, 2 ),
			'eta_minutes'    => $eta_minutes,
			'eta_text'       => $eta_minutes < 60
				? sprintf( '%d min', $eta_minutes )
				: sprintf( '%d hr %d min', floor( $eta_minutes / 60 ), $eta_minutes % 60 ),
			'arrival_time'   => gmdate( 'g:i A', strtotime( '+' . $eta_minutes . ' minutes' ) ),
			'is_stale'       => $provider_location['is_stale'] ?? false,
		);
	}

	/**
	 * Calculate distance in meters between two points.
	 *
	 * @param float $lat1 Latitude 1.
	 * @param float $lng1 Longitude 1.
	 * @param float $lat2 Latitude 2.
	 * @param float $lng2 Longitude 2.
	 * @return float Distance in meters.
	 */
	private function calculate_distance_meters( $lat1, $lng1, $lat2, $lng2 ) {
		$earth_radius = 6371000; // meters

		$lat1 = deg2rad( $lat1 );
		$lng1 = deg2rad( $lng1 );
		$lat2 = deg2rad( $lat2 );
		$lng2 = deg2rad( $lng2 );

		$dlat = $lat2 - $lat1;
		$dlng = $lng2 - $lng1;

		$a = sin( $dlat / 2 ) * sin( $dlat / 2 ) +
			cos( $lat1 ) * cos( $lat2 ) *
			sin( $dlng / 2 ) * sin( $dlng / 2 );

		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return $earth_radius * $c;
	}

	/**
	 * Calculate distance in miles between two points.
	 *
	 * @param float $lat1 Latitude 1.
	 * @param float $lng1 Longitude 1.
	 * @param float $lat2 Latitude 2.
	 * @param float $lng2 Longitude 2.
	 * @return float Distance in miles.
	 */
	private function calculate_distance_miles( $lat1, $lng1, $lat2, $lng2 ) {
		return $this->calculate_distance_meters( $lat1, $lng1, $lat2, $lng2 ) / 1609.34;
	}

	/**
	 * Get device info from request.
	 *
	 * @return string
	 */
	private function get_device_info() {
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		return wp_json_encode(
			array(
				'user_agent' => $user_agent,
				'ip'         => $this->get_client_ip(),
				'timestamp'  => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// Handle comma-separated IPs.
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				return $ip;
			}
		}

		return '';
	}

	/**
	 * Update booking status.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $status     New status.
	 */
	private function update_booking_status( $booking_id, $status ) {
		update_post_meta( $booking_id, 'provider_arrival_status', $status );
		update_post_meta( $booking_id, 'provider_arrival_time', current_time( 'mysql' ) );

		do_action( 'bkx_provider_arrived', $booking_id, $status );
	}
}
