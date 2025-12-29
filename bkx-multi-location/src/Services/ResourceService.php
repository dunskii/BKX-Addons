<?php
/**
 * Resource service for managing location resources (rooms, equipment, etc.).
 *
 * @package BookingX\MultiLocation\Services
 */

namespace BookingX\MultiLocation\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ResourceService class.
 */
class ResourceService {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Resource types.
	 *
	 * @var array
	 */
	private $types = array(
		'room'      => 'Room',
		'equipment' => 'Equipment',
		'vehicle'   => 'Vehicle',
		'table'     => 'Table',
		'other'     => 'Other',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'bkx_location_resources';
	}

	/**
	 * Get resource types.
	 *
	 * @return array
	 */
	public function get_types() {
		return apply_filters( 'bkx_ml_resource_types', $this->types );
	}

	/**
	 * Get resources for a location.
	 *
	 * @param int   $location_id Location ID.
	 * @param array $args        Query arguments.
	 * @return array
	 */
	public function get_for_location( $location_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'type'   => '',
			'status' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = 'location_id = %d';
		$params = array( $location_id );

		if ( $args['type'] ) {
			$where   .= ' AND type = %s';
			$params[] = $args['type'];
		}

		if ( $args['status'] ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE {$where} ORDER BY type, name",
				$params
			)
		);
	}

	/**
	 * Get single resource.
	 *
	 * @param int $resource_id Resource ID.
	 * @return object|null
	 */
	public function get( $resource_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$resource_id
			)
		);
	}

	/**
	 * Save resource.
	 *
	 * @param int   $resource_id Resource ID (0 for new).
	 * @param array $data        Resource data.
	 * @return int|\WP_Error Resource ID or error.
	 */
	public function save( $resource_id, $data ) {
		global $wpdb;

		$insert_data = array(
			'location_id' => absint( $data['location_id'] ?? 0 ),
			'name'        => sanitize_text_field( $data['name'] ?? '' ),
			'type'        => sanitize_text_field( $data['type'] ?? 'room' ),
			'capacity'    => absint( $data['capacity'] ?? 1 ),
			'description' => sanitize_textarea_field( $data['description'] ?? '' ),
			'status'      => sanitize_text_field( $data['status'] ?? 'active' ),
		);

		if ( $resource_id ) {
			// Update.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->update(
				$this->table,
				$insert_data,
				array( 'id' => $resource_id ),
				array( '%d', '%s', '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'db_error', __( 'Failed to update resource.', 'bkx-multi-location' ) );
			}

			return $resource_id;
		}

		// Insert.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$this->table,
			$insert_data,
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create resource.', 'bkx-multi-location' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Delete resource.
	 *
	 * @param int $resource_id Resource ID.
	 * @return bool
	 */
	public function delete( $resource_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete(
			$this->table,
			array( 'id' => $resource_id ),
			array( '%d' )
		);
	}

	/**
	 * Get available resources for a location on a date/time.
	 *
	 * @param int    $location_id Location ID.
	 * @param string $date        Date in Y-m-d format.
	 * @param string $start_time  Start time.
	 * @param string $end_time    End time.
	 * @param string $type        Optional resource type.
	 * @return array
	 */
	public function get_available( $location_id, $date, $start_time, $end_time, $type = '' ) {
		global $wpdb;

		// Get all active resources.
		$resources = $this->get_for_location(
			$location_id,
			array(
				'type'   => $type,
				'status' => 'active',
			)
		);

		if ( empty( $resources ) ) {
			return array();
		}

		// Check each resource for booking conflicts.
		$available = array();

		foreach ( $resources as $resource ) {
			if ( ! $this->has_conflict( $resource->id, $date, $start_time, $end_time ) ) {
				$available[] = $resource;
			}
		}

		return $available;
	}

	/**
	 * Check if resource has a booking conflict.
	 *
	 * @param int    $resource_id Resource ID.
	 * @param string $date        Date in Y-m-d format.
	 * @param string $start_time  Start time.
	 * @param string $end_time    End time.
	 * @return bool
	 */
	public function has_conflict( $resource_id, $date, $start_time, $end_time ) {
		global $wpdb;

		// Check bookings that use this resource.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$conflicts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->postmeta} pm_date ON pm.post_id = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				INNER JOIN {$wpdb->postmeta} pm_time ON pm.post_id = pm_time.post_id AND pm_time.meta_key = 'booking_time'
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE pm.meta_key = '_bkx_resource_id'
				AND pm.meta_value = %d
				AND pm_date.meta_value = %s
				AND p.post_type = 'bkx_booking'
				AND p.post_status NOT IN ('trash', 'bkx-cancelled')
				AND (
					(pm_time.meta_value >= %s AND pm_time.meta_value < %s)
					OR (pm_time.meta_value < %s AND DATE_ADD(pm_time.meta_value, INTERVAL 60 MINUTE) > %s)
				)",
				$resource_id,
				$date,
				$start_time,
				$end_time,
				$start_time,
				$start_time
			)
		);

		return $conflicts > 0;
	}

	/**
	 * Get total capacity for a location by resource type.
	 *
	 * @param int    $location_id Location ID.
	 * @param string $type        Resource type.
	 * @return int
	 */
	public function get_capacity( $location_id, $type = '' ) {
		global $wpdb;

		$where  = 'location_id = %d AND status = %s';
		$params = array( $location_id, 'active' );

		if ( $type ) {
			$where   .= ' AND type = %s';
			$params[] = $type;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$capacity = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(capacity) FROM {$this->table} WHERE {$where}",
				$params
			)
		);

		return absint( $capacity );
	}

	/**
	 * Count resources by location.
	 *
	 * @return array Array of location_id => count.
	 */
	public function get_counts_by_location() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			"SELECT location_id, COUNT(*) as count
			FROM {$this->table}
			WHERE status = 'active'
			GROUP BY location_id",
			OBJECT_K
		);

		$counts = array();
		foreach ( $results as $location_id => $row ) {
			$counts[ $location_id ] = absint( $row->count );
		}

		return $counts;
	}

	/**
	 * Count resources by type for a location.
	 *
	 * @param int $location_id Location ID.
	 * @return array Array of type => count.
	 */
	public function get_counts_by_type( $location_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT type, COUNT(*) as count
				FROM {$this->table}
				WHERE location_id = %d AND status = 'active'
				GROUP BY type",
				$location_id
			),
			OBJECT_K
		);

		$counts = array();
		foreach ( $results as $type => $row ) {
			$counts[ $type ] = absint( $row->count );
		}

		return $counts;
	}

	/**
	 * Duplicate resources from one location to another.
	 *
	 * @param int $source_id      Source location ID.
	 * @param int $destination_id Destination location ID.
	 * @return int Number of resources duplicated.
	 */
	public function duplicate( $source_id, $destination_id ) {
		$resources = $this->get_for_location( $source_id );
		$count     = 0;

		foreach ( $resources as $resource ) {
			$result = $this->save(
				0,
				array(
					'location_id' => $destination_id,
					'name'        => $resource->name,
					'type'        => $resource->type,
					'capacity'    => $resource->capacity,
					'description' => $resource->description,
					'status'      => $resource->status,
				)
			);

			if ( ! is_wp_error( $result ) ) {
				++$count;
			}
		}

		return $count;
	}
}
