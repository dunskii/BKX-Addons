<?php
/**
 * Service Area Manager Service.
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
 * ServiceAreaManager Class.
 */
class ServiceAreaManager {

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
	 * Save a service area.
	 *
	 * @param array $data Service area data.
	 * @return int|\WP_Error
	 */
	public function save_area( $data ) {
		global $wpdb;

		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'Service area name is required.', 'bkx-mobile-bookings' ) );
		}

		// Convert radius miles to km.
		$radius_km = 0;
		if ( ! empty( $data['radius_miles'] ) ) {
			$radius_km = $data['radius_miles'] * 1.60934;
		}

		$area_data = array(
			'name'                     => sanitize_text_field( $data['name'] ),
			'description'              => sanitize_textarea_field( $data['description'] ?? '' ),
			'service_id'               => absint( $data['service_id'] ?? 0 ) ?: null,
			'provider_id'              => absint( $data['provider_id'] ?? 0 ) ?: null,
			'area_type'                => sanitize_text_field( $data['area_type'] ?? 'radius' ),
			'center_latitude'          => floatval( $data['center_latitude'] ?? 0 ),
			'center_longitude'         => floatval( $data['center_longitude'] ?? 0 ),
			'radius_km'                => $radius_km,
			'radius_miles'             => floatval( $data['radius_miles'] ?? 0 ),
			'zip_codes'                => sanitize_text_field( $data['zip_codes'] ?? '' ),
			'polygon_coordinates'      => $data['polygon_coordinates'] ?? '',
			'cities'                   => sanitize_text_field( $data['cities'] ?? '' ),
			'states'                   => sanitize_text_field( $data['states'] ?? '' ),
			'distance_pricing_enabled' => isset( $data['distance_pricing_enabled'] ) ? 1 : 0,
			'base_travel_fee'          => floatval( $data['base_travel_fee'] ?? 0 ),
			'per_km_rate'              => floatval( $data['per_km_rate'] ?? 0 ),
			'per_mile_rate'            => floatval( $data['per_mile_rate'] ?? 0 ),
			'min_distance'             => floatval( $data['min_distance'] ?? 0 ),
			'max_distance'             => floatval( $data['max_distance'] ?? 0 ) ?: null,
			'status'                   => sanitize_text_field( $data['status'] ?? 'active' ),
			'updated_at'               => current_time( 'mysql' ),
		);

		$formats = array(
			'%s', '%s', '%d', '%d', '%s', '%f', '%f', '%f', '%f',
			'%s', '%s', '%s', '%s', '%d', '%f', '%f', '%f', '%f', '%f', '%s', '%s',
		);

		$table = $wpdb->prefix . 'bkx_service_areas';

		if ( ! empty( $data['id'] ) ) {
			$result = $wpdb->update(
				$table,
				$area_data,
				array( 'id' => absint( $data['id'] ) ),
				$formats,
				array( '%d' )
			);

			return false === $result
				? new \WP_Error( 'db_error', __( 'Database error occurred.', 'bkx-mobile-bookings' ) )
				: absint( $data['id'] );
		}

		$area_data['created_at'] = current_time( 'mysql' );
		$formats[]               = '%s';

		$result = $wpdb->insert( $table, $area_data, $formats );

		return ! $result
			? new \WP_Error( 'db_error', __( 'Database error occurred.', 'bkx-mobile-bookings' ) )
			: $wpdb->insert_id;
	}

	/**
	 * Delete a service area.
	 *
	 * @param int $area_id Area ID.
	 * @return bool
	 */
	public function delete_area( $area_id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$wpdb->prefix . 'bkx_service_areas',
			array( 'id' => absint( $area_id ) ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get a service area by ID.
	 *
	 * @param int $area_id Area ID.
	 * @return array|null
	 */
	public function get_area( $area_id ) {
		global $wpdb;

		$area = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_service_areas WHERE id = %d",
				$area_id
			),
			ARRAY_A
		);

		if ( $area && ! empty( $area['polygon_coordinates'] ) ) {
			$area['polygon_coordinates'] = json_decode( $area['polygon_coordinates'], true );
		}

		return $area;
	}

	/**
	 * Get all service areas.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_areas( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'      => '',
			'service_id'  => 0,
			'provider_id' => 0,
			'orderby'     => 'name',
			'order'       => 'ASC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'bkx_service_areas';

		$where = 'WHERE 1=1';

		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		if ( ! empty( $args['service_id'] ) ) {
			$where .= $wpdb->prepare( ' AND (service_id = %d OR service_id IS NULL)', $args['service_id'] );
		}

		if ( ! empty( $args['provider_id'] ) ) {
			$where .= $wpdb->prepare( ' AND (provider_id = %d OR provider_id IS NULL)', $args['provider_id'] );
		}

		$orderby = in_array( $args['orderby'], array( 'name', 'created_at', 'status' ), true )
			? $args['orderby'] : 'name';
		$order   = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$areas = $wpdb->get_results(
			"SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order}",
			ARRAY_A
		);

		foreach ( $areas as &$area ) {
			if ( ! empty( $area['polygon_coordinates'] ) ) {
				$area['polygon_coordinates'] = json_decode( $area['polygon_coordinates'], true );
			}
		}

		return $areas;
	}

	/**
	 * Check if coordinates are within any service area.
	 *
	 * @param float $lat         Latitude.
	 * @param float $lng         Longitude.
	 * @param int   $service_id  Optional service ID filter.
	 * @param int   $provider_id Optional provider ID filter.
	 * @return array
	 */
	public function check_by_coordinates( $lat, $lng, $service_id = 0, $provider_id = 0 ) {
		$areas = $this->get_areas(
			array(
				'status'      => 'active',
				'service_id'  => $service_id,
				'provider_id' => $provider_id,
			)
		);

		if ( empty( $areas ) ) {
			// No service areas defined - allow all.
			return array(
				'in_service_area' => true,
				'areas'           => array(),
				'message'         => __( 'No service area restrictions.', 'bkx-mobile-bookings' ),
			);
		}

		$matching_areas = array();

		foreach ( $areas as $area ) {
			if ( $this->is_point_in_area( $lat, $lng, $area ) ) {
				$matching_areas[] = array(
					'id'                       => $area['id'],
					'name'                     => $area['name'],
					'distance_pricing_enabled' => (bool) $area['distance_pricing_enabled'],
					'base_travel_fee'          => $area['base_travel_fee'],
					'per_mile_rate'            => $area['per_mile_rate'],
				);
			}
		}

		$in_area = ! empty( $matching_areas );

		// Check enforcement setting.
		$enforce = ! empty( $this->settings['enforce_service_areas'] );

		return array(
			'in_service_area' => $in_area || ! $enforce,
			'areas'           => $matching_areas,
			'is_enforced'     => $enforce,
			'message'         => $in_area
				? __( 'Location is within service area.', 'bkx-mobile-bookings' )
				: ( $enforce
					? __( 'This location is outside our service area.', 'bkx-mobile-bookings' )
					: __( 'Location is outside defined service areas.', 'bkx-mobile-bookings' )
				),
		);
	}

	/**
	 * Check if address is within service area.
	 *
	 * @param string $address     Address.
	 * @param int    $service_id  Optional service ID.
	 * @param int    $provider_id Optional provider ID.
	 * @return array
	 */
	public function check_by_address( $address, $service_id = 0, $provider_id = 0 ) {
		$geocoded = $this->maps_service->geocode( $address );

		if ( is_wp_error( $geocoded ) ) {
			return array(
				'in_service_area' => false,
				'error'           => true,
				'message'         => $geocoded->get_error_message(),
			);
		}

		$result            = $this->check_by_coordinates( $geocoded['lat'], $geocoded['lng'], $service_id, $provider_id );
		$result['geocoded'] = $geocoded;

		return $result;
	}

	/**
	 * Check if a point is within a service area.
	 *
	 * @param float $lat  Latitude.
	 * @param float $lng  Longitude.
	 * @param array $area Service area data.
	 * @return bool
	 */
	private function is_point_in_area( $lat, $lng, $area ) {
		switch ( $area['area_type'] ) {
			case 'radius':
				return $this->is_point_in_radius(
					$lat,
					$lng,
					$area['center_latitude'],
					$area['center_longitude'],
					$area['radius_miles']
				);

			case 'zip_codes':
				return $this->is_point_in_zip_codes( $lat, $lng, $area['zip_codes'] );

			case 'polygon':
				return $this->is_point_in_polygon( $lat, $lng, $area['polygon_coordinates'] );

			case 'city':
				return $this->is_point_in_cities( $lat, $lng, $area['cities'] );

			case 'state':
				return $this->is_point_in_states( $lat, $lng, $area['states'] );

			default:
				return false;
		}
	}

	/**
	 * Check if point is within radius.
	 *
	 * @param float $lat        Point latitude.
	 * @param float $lng        Point longitude.
	 * @param float $center_lat Center latitude.
	 * @param float $center_lng Center longitude.
	 * @param float $radius     Radius in miles.
	 * @return bool
	 */
	private function is_point_in_radius( $lat, $lng, $center_lat, $center_lng, $radius ) {
		$distance = $this->maps_service->haversine_distance( $lat, $lng, $center_lat, $center_lng, 'miles' );
		return $distance <= $radius;
	}

	/**
	 * Check if point is in specified zip codes.
	 *
	 * @param float  $lat       Latitude.
	 * @param float  $lng       Longitude.
	 * @param string $zip_codes Comma-separated zip codes.
	 * @return bool
	 */
	private function is_point_in_zip_codes( $lat, $lng, $zip_codes ) {
		$geocoded = $this->maps_service->reverse_geocode( $lat, $lng );

		if ( is_wp_error( $geocoded ) ) {
			return false;
		}

		$point_zip  = $geocoded['address_components']['zip_code'] ?? '';
		$allowed    = array_map( 'trim', explode( ',', $zip_codes ) );

		return in_array( $point_zip, $allowed, true );
	}

	/**
	 * Check if point is within polygon.
	 *
	 * @param float $lat         Latitude.
	 * @param float $lng         Longitude.
	 * @param array $coordinates Polygon coordinates.
	 * @return bool
	 */
	private function is_point_in_polygon( $lat, $lng, $coordinates ) {
		if ( empty( $coordinates ) || ! is_array( $coordinates ) ) {
			return false;
		}

		$n      = count( $coordinates );
		$inside = false;

		for ( $i = 0, $j = $n - 1; $i < $n; $j = $i++ ) {
			$xi = $coordinates[ $i ]['lat'];
			$yi = $coordinates[ $i ]['lng'];
			$xj = $coordinates[ $j ]['lat'];
			$yj = $coordinates[ $j ]['lng'];

			if ( ( ( $yi > $lng ) !== ( $yj > $lng ) ) &&
				( $lat < ( $xj - $xi ) * ( $lng - $yi ) / ( $yj - $yi ) + $xi ) ) {
				$inside = ! $inside;
			}
		}

		return $inside;
	}

	/**
	 * Check if point is in specified cities.
	 *
	 * @param float  $lat    Latitude.
	 * @param float  $lng    Longitude.
	 * @param string $cities Comma-separated cities.
	 * @return bool
	 */
	private function is_point_in_cities( $lat, $lng, $cities ) {
		$geocoded = $this->maps_service->reverse_geocode( $lat, $lng );

		if ( is_wp_error( $geocoded ) ) {
			return false;
		}

		$point_city = strtolower( $geocoded['address_components']['city'] ?? '' );
		$allowed    = array_map( 'strtolower', array_map( 'trim', explode( ',', $cities ) ) );

		return in_array( $point_city, $allowed, true );
	}

	/**
	 * Check if point is in specified states.
	 *
	 * @param float  $lat    Latitude.
	 * @param float  $lng    Longitude.
	 * @param string $states Comma-separated states.
	 * @return bool
	 */
	private function is_point_in_states( $lat, $lng, $states ) {
		$geocoded = $this->maps_service->reverse_geocode( $lat, $lng );

		if ( is_wp_error( $geocoded ) ) {
			return false;
		}

		$point_state       = strtolower( $geocoded['address_components']['state'] ?? '' );
		$point_state_short = strtolower( $geocoded['address_components']['state_short'] ?? '' );
		$allowed           = array_map( 'strtolower', array_map( 'trim', explode( ',', $states ) ) );

		return in_array( $point_state, $allowed, true ) || in_array( $point_state_short, $allowed, true );
	}

	/**
	 * Get service area coverage statistics.
	 *
	 * @return array
	 */
	public function get_coverage_stats() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_service_areas';

		$total   = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$active  = $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" );
		$by_type = $wpdb->get_results(
			"SELECT area_type, COUNT(*) as count FROM {$table} GROUP BY area_type",
			ARRAY_A
		);

		return array(
			'total'   => (int) $total,
			'active'  => (int) $active,
			'by_type' => wp_list_pluck( $by_type, 'count', 'area_type' ),
		);
	}

	/**
	 * Get distance to nearest service area boundary.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return array|null
	 */
	public function get_distance_to_nearest_area( $lat, $lng ) {
		$areas = $this->get_areas( array( 'status' => 'active' ) );

		if ( empty( $areas ) ) {
			return null;
		}

		$nearest = null;

		foreach ( $areas as $area ) {
			if ( 'radius' === $area['area_type'] ) {
				$distance = $this->maps_service->haversine_distance(
					$lat,
					$lng,
					$area['center_latitude'],
					$area['center_longitude'],
					'miles'
				);

				$distance_to_edge = $distance - $area['radius_miles'];

				if ( null === $nearest || abs( $distance_to_edge ) < abs( $nearest['distance'] ) ) {
					$nearest = array(
						'area_id'   => $area['id'],
						'area_name' => $area['name'],
						'distance'  => $distance_to_edge,
						'inside'    => $distance_to_edge <= 0,
					);
				}
			}
		}

		return $nearest;
	}
}
