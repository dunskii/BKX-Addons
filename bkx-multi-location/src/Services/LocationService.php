<?php
/**
 * Location service for managing locations.
 *
 * @package BookingX\MultiLocation\Services
 */

namespace BookingX\MultiLocation\Services;

defined( 'ABSPATH' ) || exit;

/**
 * LocationService class.
 */
class LocationService {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'bkx_locations';
	}

	/**
	 * Get all locations.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'orderby'  => 'sort_order',
			'order'    => 'ASC',
			'limit'    => 0,
			'offset'   => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = '1=1';
		$params = array();

		if ( $args['status'] ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		if ( ! $orderby ) {
			$orderby = 'sort_order ASC';
		}

		$sql = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY {$orderby}";

		if ( $args['limit'] ) {
			$sql     .= ' LIMIT %d';
			$params[] = $args['limit'];

			if ( $args['offset'] ) {
				$sql     .= ' OFFSET %d';
				$params[] = $args['offset'];
			}
		}

		if ( $params ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql );
	}

	/**
	 * Get single location.
	 *
	 * @param int $id Location ID.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$location = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			)
		);

		if ( $location && $location->settings ) {
			$location->settings = json_decode( $location->settings, true );
		}

		return $location;
	}

	/**
	 * Get location by slug.
	 *
	 * @param string $slug Location slug.
	 * @return object|null
	 */
	public function get_by_slug( $slug ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE slug = %s",
				$slug
			)
		);
	}

	/**
	 * Count locations.
	 *
	 * @param string $status Optional status filter.
	 * @return int
	 */
	public function count( $status = '' ) {
		global $wpdb;

		if ( $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE status = %s",
					$status
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
	}

	/**
	 * Save location.
	 *
	 * @param int   $id   Location ID (0 for new).
	 * @param array $data Location data.
	 * @return int|\WP_Error Location ID on success, WP_Error on failure.
	 */
	public function save( $id, $data ) {
		global $wpdb;

		// Generate unique slug if needed.
		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		// Ensure slug is unique.
		$data['slug'] = $this->ensure_unique_slug( $data['slug'], $id );

		// Prepare settings.
		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$data['settings'] = wp_json_encode( $data['settings'] );
		}

		$columns = array(
			'name'           => '%s',
			'slug'           => '%s',
			'description'    => '%s',
			'address_line_1' => '%s',
			'address_line_2' => '%s',
			'city'           => '%s',
			'state'          => '%s',
			'postal_code'    => '%s',
			'country'        => '%s',
			'latitude'       => '%f',
			'longitude'      => '%f',
			'phone'          => '%s',
			'email'          => '%s',
			'timezone'       => '%s',
			'status'         => '%s',
			'settings'       => '%s',
			'sort_order'     => '%d',
		);

		$insert_data   = array();
		$insert_format = array();

		foreach ( $columns as $column => $format ) {
			if ( isset( $data[ $column ] ) ) {
				$insert_data[ $column ] = $data[ $column ];
				$insert_format[]        = $format;
			}
		}

		if ( $id ) {
			// Update existing.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->update(
				$this->table,
				$insert_data,
				array( 'id' => $id ),
				$insert_format,
				array( '%d' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'db_error', __( 'Failed to update location.', 'bkx-multi-location' ) );
			}

			return $id;
		}

		// Insert new.
		if ( ! isset( $insert_data['sort_order'] ) ) {
			$insert_data['sort_order'] = $this->get_next_sort_order();
			$insert_format[]           = '%d';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$this->table,
			$insert_data,
			$insert_format
		);

		if ( ! $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create location.', 'bkx-multi-location' ) );
		}

		$location_id = $wpdb->insert_id;

		// Create default hours for new location.
		$this->create_default_hours( $location_id );

		return $location_id;
	}

	/**
	 * Delete location.
	 *
	 * @param int $id Location ID.
	 * @return bool|\WP_Error
	 */
	public function delete( $id ) {
		global $wpdb;

		// Delete related data.
		$wpdb->delete( $wpdb->prefix . 'bkx_location_hours', array( 'location_id' => $id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bkx_location_holidays', array( 'location_id' => $id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bkx_location_staff', array( 'location_id' => $id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bkx_location_services', array( 'location_id' => $id ), array( '%d' ) );
		$wpdb->delete( $wpdb->prefix . 'bkx_location_resources', array( 'location_id' => $id ), array( '%d' ) );

		// Delete location.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete(
			$this->table,
			array( 'id' => $id ),
			array( '%d' )
		);

		return (bool) $result;
	}

	/**
	 * Reorder locations.
	 *
	 * @param array $order Array of location IDs in order.
	 */
	public function reorder( $order ) {
		global $wpdb;

		foreach ( $order as $sort_order => $location_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$this->table,
				array( 'sort_order' => $sort_order ),
				array( 'id' => $location_id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Ensure slug is unique.
	 *
	 * @param string $slug Base slug.
	 * @param int    $id   Exclude this ID from check.
	 * @return string
	 */
	private function ensure_unique_slug( $slug, $id = 0 ) {
		global $wpdb;

		$original_slug = $slug;
		$counter       = 1;

		while ( true ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$this->table} WHERE slug = %s AND id != %d",
					$slug,
					$id
				)
			);

			if ( ! $existing ) {
				break;
			}

			$slug = $original_slug . '-' . $counter;
			++$counter;
		}

		return $slug;
	}

	/**
	 * Get next sort order.
	 *
	 * @return int
	 */
	private function get_next_sort_order() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$max = $wpdb->get_var( "SELECT MAX(sort_order) FROM {$this->table}" );

		return $max ? $max + 1 : 0;
	}

	/**
	 * Create default hours for a new location.
	 *
	 * @param int $location_id Location ID.
	 */
	private function create_default_hours( $location_id ) {
		global $wpdb;

		$hours_table = $wpdb->prefix . 'bkx_location_hours';

		// Default: Mon-Fri 9 AM - 5 PM.
		for ( $day = 0; $day <= 6; $day++ ) {
			$is_open = ( $day >= 1 && $day <= 5 ) ? 1 : 0;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$hours_table,
				array(
					'location_id' => $location_id,
					'day_of_week' => $day,
					'is_open'     => $is_open,
					'open_time'   => $is_open ? '09:00:00' : null,
					'close_time'  => $is_open ? '17:00:00' : null,
				),
				array( '%d', '%d', '%d', '%s', '%s' )
			);
		}
	}

	/**
	 * Get locations near coordinates.
	 *
	 * @param float $lat      Latitude.
	 * @param float $lng      Longitude.
	 * @param int   $radius   Radius in miles.
	 * @param int   $limit    Max results.
	 * @return array
	 */
	public function get_nearby( $lat, $lng, $radius = 25, $limit = 10 ) {
		global $wpdb;

		// Haversine formula for distance in miles.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT *,
				(3959 * acos(cos(radians(%f)) * cos(radians(latitude)) * cos(radians(longitude) - radians(%f)) + sin(radians(%f)) * sin(radians(latitude)))) AS distance
				FROM {$this->table}
				WHERE status = 'active'
				AND latitude IS NOT NULL
				AND longitude IS NOT NULL
				HAVING distance < %d
				ORDER BY distance
				LIMIT %d",
				$lat,
				$lng,
				$lat,
				$radius,
				$limit
			)
		);
	}

	/**
	 * Get formatted address.
	 *
	 * @param object|int $location Location object or ID.
	 * @return string
	 */
	public function get_formatted_address( $location ) {
		if ( is_numeric( $location ) ) {
			$location = $this->get( $location );
		}

		if ( ! $location ) {
			return '';
		}

		$parts = array_filter(
			array(
				$location->address_line_1,
				$location->address_line_2,
				$location->city,
				$location->state . ' ' . $location->postal_code,
				$location->country,
			)
		);

		return implode( ', ', $parts );
	}
}
