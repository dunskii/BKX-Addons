<?php
/**
 * Google Maps Service.
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
 * GoogleMapsService Class.
 */
class GoogleMapsService {

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_base = 'https://maps.googleapis.com/maps/api';

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
		$this->api_key  = $settings['google_maps_api_key'] ?? '';
	}

	/**
	 * Check if API is configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->api_key );
	}

	/**
	 * Geocode an address.
	 *
	 * @param string $address Address to geocode.
	 * @return array|\WP_Error
	 */
	public function geocode( $address ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'no_api_key', __( 'Google Maps API key not configured.', 'bkx-mobile-bookings' ) );
		}

		$cache_key = 'bkx_geocode_' . md5( $address );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$url = add_query_arg(
			array(
				'address' => rawurlencode( $address ),
				'key'     => $this->api_key,
			),
			$this->api_base . '/geocode/json'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 'OK' !== $body['status'] ) {
			return new \WP_Error(
				'geocode_failed',
				$this->get_error_message( $body['status'] )
			);
		}

		$result = $body['results'][0];

		$data = array(
			'lat'               => $result['geometry']['location']['lat'],
			'lng'               => $result['geometry']['location']['lng'],
			'formatted_address' => $result['formatted_address'],
			'place_id'          => $result['place_id'],
			'address_components' => $this->parse_address_components( $result['address_components'] ),
		);

		set_transient( $cache_key, $data, DAY_IN_SECONDS );

		return $data;
	}

	/**
	 * Reverse geocode coordinates.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return array|\WP_Error
	 */
	public function reverse_geocode( $lat, $lng ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'no_api_key', __( 'Google Maps API key not configured.', 'bkx-mobile-bookings' ) );
		}

		$cache_key = 'bkx_rgeocode_' . md5( "{$lat},{$lng}" );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$url = add_query_arg(
			array(
				'latlng' => "{$lat},{$lng}",
				'key'    => $this->api_key,
			),
			$this->api_base . '/geocode/json'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 'OK' !== $body['status'] ) {
			return new \WP_Error(
				'reverse_geocode_failed',
				$this->get_error_message( $body['status'] )
			);
		}

		$result = $body['results'][0];

		$data = array(
			'formatted_address'  => $result['formatted_address'],
			'place_id'           => $result['place_id'],
			'address_components' => $this->parse_address_components( $result['address_components'] ),
		);

		set_transient( $cache_key, $data, DAY_IN_SECONDS );

		return $data;
	}

	/**
	 * Get distance matrix between origins and destinations.
	 *
	 * @param array $origins      Array of origin addresses or coordinates.
	 * @param array $destinations Array of destination addresses or coordinates.
	 * @param array $options      Additional options.
	 * @return array|\WP_Error
	 */
	public function distance_matrix( $origins, $destinations, $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'no_api_key', __( 'Google Maps API key not configured.', 'bkx-mobile-bookings' ) );
		}

		$defaults = array(
			'mode'          => 'driving',
			'units'         => 'imperial',
			'traffic_model' => $this->settings['traffic_model'] ?? 'best_guess',
		);

		$options = wp_parse_args( $options, $defaults );

		$cache_key = 'bkx_dm_' . md5( wp_json_encode( array( $origins, $destinations, $options ) ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$args = array(
			'origins'      => implode( '|', array_map( 'rawurlencode', (array) $origins ) ),
			'destinations' => implode( '|', array_map( 'rawurlencode', (array) $destinations ) ),
			'mode'         => $options['mode'],
			'units'        => $options['units'],
			'key'          => $this->api_key,
		);

		// Add departure time for traffic calculation.
		if ( ! empty( $this->settings['include_traffic'] ) ) {
			$args['departure_time'] = 'now';
			$args['traffic_model']  = $options['traffic_model'];
		}

		$url      = add_query_arg( $args, $this->api_base . '/distancematrix/json' );
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 'OK' !== $body['status'] ) {
			return new \WP_Error(
				'distance_matrix_failed',
				$this->get_error_message( $body['status'] )
			);
		}

		$cache_duration = absint( $this->settings['cache_duration_minutes'] ?? 30 ) * MINUTE_IN_SECONDS;
		set_transient( $cache_key, $body, $cache_duration );

		return $body;
	}

	/**
	 * Get directions between two points.
	 *
	 * @param string|array $origin      Origin address or coordinates.
	 * @param string|array $destination Destination address or coordinates.
	 * @param array        $waypoints   Optional waypoints.
	 * @param array        $options     Additional options.
	 * @return array|\WP_Error
	 */
	public function directions( $origin, $destination, $waypoints = array(), $options = array() ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'no_api_key', __( 'Google Maps API key not configured.', 'bkx-mobile-bookings' ) );
		}

		$defaults = array(
			'mode'          => 'driving',
			'alternatives'  => false,
			'units'         => 'imperial',
			'optimize'      => false,
			'traffic_model' => $this->settings['traffic_model'] ?? 'best_guess',
		);

		$options = wp_parse_args( $options, $defaults );

		$args = array(
			'origin'      => is_array( $origin ) ? implode( ',', $origin ) : $origin,
			'destination' => is_array( $destination ) ? implode( ',', $destination ) : $destination,
			'mode'        => $options['mode'],
			'units'       => $options['units'],
			'key'         => $this->api_key,
		);

		if ( $options['alternatives'] ) {
			$args['alternatives'] = 'true';
		}

		if ( ! empty( $waypoints ) ) {
			$prefix           = $options['optimize'] ? 'optimize:true|' : '';
			$args['waypoints'] = $prefix . implode( '|', $waypoints );
		}

		if ( ! empty( $this->settings['include_traffic'] ) ) {
			$args['departure_time'] = 'now';
			$args['traffic_model']  = $options['traffic_model'];
		}

		$url      = add_query_arg( $args, $this->api_base . '/directions/json' );
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 'OK' !== $body['status'] ) {
			return new \WP_Error(
				'directions_failed',
				$this->get_error_message( $body['status'] )
			);
		}

		return $body;
	}

	/**
	 * Get place details by Place ID.
	 *
	 * @param string $place_id Google Place ID.
	 * @return array|\WP_Error
	 */
	public function get_place_details( $place_id ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error( 'no_api_key', __( 'Google Maps API key not configured.', 'bkx-mobile-bookings' ) );
		}

		$cache_key = 'bkx_place_' . md5( $place_id );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$url = add_query_arg(
			array(
				'place_id' => $place_id,
				'fields'   => 'formatted_address,geometry,name,address_components',
				'key'      => $this->api_key,
			),
			$this->api_base . '/place/details/json'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 'OK' !== $body['status'] ) {
			return new \WP_Error(
				'place_details_failed',
				$this->get_error_message( $body['status'] )
			);
		}

		set_transient( $cache_key, $body['result'], DAY_IN_SECONDS );

		return $body['result'];
	}

	/**
	 * Calculate distance using Haversine formula (fallback).
	 *
	 * @param float $lat1 Latitude 1.
	 * @param float $lng1 Longitude 1.
	 * @param float $lat2 Latitude 2.
	 * @param float $lng2 Longitude 2.
	 * @param string $unit Distance unit (miles or km).
	 * @return float
	 */
	public function haversine_distance( $lat1, $lng1, $lat2, $lng2, $unit = 'miles' ) {
		$earth_radius = 'miles' === $unit ? 3959 : 6371;

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
	 * Parse address components from Google response.
	 *
	 * @param array $components Address components.
	 * @return array
	 */
	private function parse_address_components( $components ) {
		$parsed = array(
			'street_number' => '',
			'street_name'   => '',
			'city'          => '',
			'state'         => '',
			'state_short'   => '',
			'country'       => '',
			'country_short' => '',
			'zip_code'      => '',
		);

		$type_map = array(
			'street_number'               => 'street_number',
			'route'                       => 'street_name',
			'locality'                    => 'city',
			'administrative_area_level_1' => 'state',
			'country'                     => 'country',
			'postal_code'                 => 'zip_code',
		);

		foreach ( $components as $component ) {
			foreach ( $component['types'] as $type ) {
				if ( isset( $type_map[ $type ] ) ) {
					$key            = $type_map[ $type ];
					$parsed[ $key ] = $component['long_name'];

					if ( 'state' === $key ) {
						$parsed['state_short'] = $component['short_name'];
					}
					if ( 'country' === $key ) {
						$parsed['country_short'] = $component['short_name'];
					}
				}
			}
		}

		return $parsed;
	}

	/**
	 * Get error message for API status.
	 *
	 * @param string $status API status.
	 * @return string
	 */
	private function get_error_message( $status ) {
		$messages = array(
			'ZERO_RESULTS'         => __( 'No results found for the given address.', 'bkx-mobile-bookings' ),
			'OVER_QUERY_LIMIT'     => __( 'API quota exceeded. Please try again later.', 'bkx-mobile-bookings' ),
			'REQUEST_DENIED'       => __( 'API request denied. Please check your API key.', 'bkx-mobile-bookings' ),
			'INVALID_REQUEST'      => __( 'Invalid request. Please check the input.', 'bkx-mobile-bookings' ),
			'UNKNOWN_ERROR'        => __( 'An unknown error occurred. Please try again.', 'bkx-mobile-bookings' ),
			'NOT_FOUND'            => __( 'The requested resource was not found.', 'bkx-mobile-bookings' ),
			'MAX_WAYPOINTS_EXCEEDED' => __( 'Too many waypoints in the request.', 'bkx-mobile-bookings' ),
		);

		return $messages[ $status ] ?? __( 'An error occurred with the Maps API.', 'bkx-mobile-bookings' );
	}

	/**
	 * Get API key.
	 *
	 * @return string
	 */
	public function get_api_key() {
		return $this->api_key;
	}
}
