<?php
/**
 * Service availability for managing service-location relationships.
 *
 * @package BookingX\MultiLocation\Services
 */

namespace BookingX\MultiLocation\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ServiceAvailability class.
 */
class ServiceAvailability {

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
		$this->table = $wpdb->prefix . 'bkx_location_services';
	}

	/**
	 * Get services for a location.
	 *
	 * @param int $location_id Location ID.
	 * @return array
	 */
	public function get_for_location( $location_id ) {
		global $wpdb;

		// Get all services with their location-specific settings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$services = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID as base_id, p.post_title as name,
				COALESCE(ls.is_available, 1) as is_available,
				ls.price_override,
				ls.duration_override
				FROM {$wpdb->posts} p
				LEFT JOIN {$this->table} ls ON p.ID = ls.base_id AND ls.location_id = %d
				WHERE p.post_type = 'bkx_base'
				AND p.post_status = 'publish'
				ORDER BY p.post_title",
				$location_id
			)
		);

		// Add default price and duration from the base service.
		foreach ( $services as &$service ) {
			$service->default_price    = get_post_meta( $service->base_id, 'base_price', true );
			$service->default_duration = get_post_meta( $service->base_id, 'base_time', true );

			$service->effective_price    = $service->price_override ?? $service->default_price;
			$service->effective_duration = $service->duration_override ?? $service->default_duration;
		}

		return $services;
	}

	/**
	 * Get available base IDs for a location.
	 *
	 * @param int $location_id Location ID.
	 * @return array
	 */
	public function get_available_base_ids( $location_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				LEFT JOIN {$this->table} ls ON p.ID = ls.base_id AND ls.location_id = %d
				WHERE p.post_type = 'bkx_base'
				AND p.post_status = 'publish'
				AND (ls.is_available IS NULL OR ls.is_available = 1)",
				$location_id
			)
		);
	}

	/**
	 * Get locations for a service.
	 *
	 * @param int $base_id Service (base) ID.
	 * @return array
	 */
	public function get_for_service( $base_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.*, ls.is_available, ls.price_override, ls.duration_override
				FROM {$wpdb->prefix}bkx_locations l
				LEFT JOIN {$this->table} ls ON l.id = ls.location_id AND ls.base_id = %d
				WHERE l.status = 'active'
				ORDER BY l.sort_order",
				$base_id
			)
		);
	}

	/**
	 * Save service settings for a location.
	 *
	 * @param int   $location_id Location ID.
	 * @param int   $base_id     Service (base) ID.
	 * @param array $data        Settings data.
	 * @return bool|\WP_Error
	 */
	public function save( $location_id, $base_id, $data ) {
		global $wpdb;

		// Check if record exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$this->table} WHERE location_id = %d AND base_id = %d",
				$location_id,
				$base_id
			)
		);

		$insert_data = array(
			'is_available'      => isset( $data['is_available'] ) ? absint( $data['is_available'] ) : 1,
			'price_override'    => $data['price_override'],
			'duration_override' => $data['duration_override'],
		);

		if ( $existing ) {
			// Update.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->update(
				$this->table,
				$insert_data,
				array( 'id' => $existing->id ),
				array( '%d', '%s', '%s' ),
				array( '%d' )
			);

			return false !== $result;
		}

		// Insert.
		$insert_data['location_id'] = $location_id;
		$insert_data['base_id']     = $base_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$this->table,
			$insert_data,
			array( '%d', '%d', '%d', '%s', '%s' )
		);

		return (bool) $result;
	}

	/**
	 * Get price override for a service at a location.
	 *
	 * @param int $location_id Location ID.
	 * @param int $base_id     Service (base) ID.
	 * @return float|null
	 */
	public function get_price_override( $location_id, $base_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$override = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT price_override FROM {$this->table} WHERE location_id = %d AND base_id = %d",
				$location_id,
				$base_id
			)
		);

		return null !== $override && '' !== $override ? floatval( $override ) : null;
	}

	/**
	 * Get duration override for a service at a location.
	 *
	 * @param int $location_id Location ID.
	 * @param int $base_id     Service (base) ID.
	 * @return int|null
	 */
	public function get_duration_override( $location_id, $base_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$override = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT duration_override FROM {$this->table} WHERE location_id = %d AND base_id = %d",
				$location_id,
				$base_id
			)
		);

		return null !== $override && '' !== $override ? absint( $override ) : null;
	}

	/**
	 * Check if service is available at location.
	 *
	 * @param int $location_id Location ID.
	 * @param int $base_id     Service (base) ID.
	 * @return bool
	 */
	public function is_available( $location_id, $base_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$is_available = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT is_available FROM {$this->table} WHERE location_id = %d AND base_id = %d",
				$location_id,
				$base_id
			)
		);

		// Default to available if no record exists.
		return null === $is_available || (bool) $is_available;
	}

	/**
	 * Set service availability at location.
	 *
	 * @param int  $location_id Location ID.
	 * @param int  $base_id     Service (base) ID.
	 * @param bool $available   Whether available.
	 * @return bool
	 */
	public function set_availability( $location_id, $base_id, $available ) {
		return $this->save(
			$location_id,
			$base_id,
			array(
				'is_available'      => $available ? 1 : 0,
				'price_override'    => null,
				'duration_override' => null,
			)
		);
	}

	/**
	 * Bulk set service availability.
	 *
	 * @param int   $location_id Location ID.
	 * @param array $base_ids    Array of service IDs.
	 * @param bool  $available   Whether available.
	 * @return int Number updated.
	 */
	public function bulk_set_availability( $location_id, $base_ids, $available ) {
		$count = 0;

		foreach ( $base_ids as $base_id ) {
			if ( $this->set_availability( $location_id, absint( $base_id ), $available ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Copy service settings from one location to another.
	 *
	 * @param int $source_id      Source location ID.
	 * @param int $destination_id Destination location ID.
	 * @return int Number of settings copied.
	 */
	public function copy_settings( $source_id, $destination_id ) {
		global $wpdb;

		// Get source settings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$settings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT base_id, is_available, price_override, duration_override FROM {$this->table} WHERE location_id = %d",
				$source_id
			)
		);

		$count = 0;

		foreach ( $settings as $setting ) {
			$result = $this->save(
				$destination_id,
				$setting->base_id,
				array(
					'is_available'      => $setting->is_available,
					'price_override'    => $setting->price_override,
					'duration_override' => $setting->duration_override,
				)
			);

			if ( $result ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get services count by location.
	 *
	 * @param bool $available_only Count only available services.
	 * @return array Array of location_id => count.
	 */
	public function get_counts_by_location( $available_only = true ) {
		global $wpdb;

		if ( $available_only ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$results = $wpdb->get_results(
				"SELECT l.id as location_id, COUNT(p.ID) as count
				FROM {$wpdb->prefix}bkx_locations l
				CROSS JOIN {$wpdb->posts} p
				LEFT JOIN {$this->table} ls ON l.id = ls.location_id AND p.ID = ls.base_id
				WHERE p.post_type = 'bkx_base'
				AND p.post_status = 'publish'
				AND l.status = 'active'
				AND (ls.is_available IS NULL OR ls.is_available = 1)
				GROUP BY l.id",
				OBJECT_K
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$results = $wpdb->get_results(
				"SELECT location_id, COUNT(*) as count
				FROM {$this->table}
				GROUP BY location_id",
				OBJECT_K
			);
		}

		$counts = array();
		foreach ( $results as $location_id => $row ) {
			$counts[ $location_id ] = absint( $row->count );
		}

		return $counts;
	}
}
