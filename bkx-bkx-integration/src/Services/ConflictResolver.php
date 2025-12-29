<?php
/**
 * Conflict resolver service.
 *
 * @package BookingX\BkxIntegration\Services
 */

namespace BookingX\BkxIntegration\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ConflictResolver class.
 */
class ConflictResolver {

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
		$this->table = $wpdb->prefix . 'bkx_remote_conflicts';
	}

	/**
	 * Get pending conflicts.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_pending( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'site_id' => 0,
			'limit'   => 50,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = 'resolution IS NULL';
		$params = array();

		if ( $args['site_id'] ) {
			$where   .= ' AND site_id = %d';
			$params[] = $args['site_id'];
		}

		$params[] = $args['limit'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$conflicts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, s.name as site_name
				FROM {$this->table} c
				LEFT JOIN {$wpdb->prefix}bkx_remote_sites s ON c.site_id = s.id
				WHERE {$where}
				ORDER BY c.created_at DESC
				LIMIT %d",
				$params
			)
		);

		foreach ( $conflicts as &$conflict ) {
			$conflict->local_data  = json_decode( $conflict->local_data, true );
			$conflict->remote_data = json_decode( $conflict->remote_data, true );
		}

		return $conflicts;
	}

	/**
	 * Get single conflict.
	 *
	 * @param int $id Conflict ID.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$conflict = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$id
			)
		);

		if ( $conflict ) {
			$conflict->local_data  = json_decode( $conflict->local_data, true );
			$conflict->remote_data = json_decode( $conflict->remote_data, true );
		}

		return $conflict;
	}

	/**
	 * Resolve a conflict.
	 *
	 * @param int    $conflict_id Conflict ID.
	 * @param string $resolution  Resolution type (local, remote, merge, skip).
	 * @return bool|\WP_Error
	 */
	public function resolve( $conflict_id, $resolution ) {
		$conflict = $this->get( $conflict_id );

		if ( ! $conflict ) {
			return new \WP_Error( 'not_found', __( 'Conflict not found.', 'bkx-bkx-integration' ) );
		}

		if ( $conflict->resolution ) {
			return new \WP_Error( 'already_resolved', __( 'Conflict already resolved.', 'bkx-bkx-integration' ) );
		}

		$result = false;

		switch ( $resolution ) {
			case 'local':
				$result = $this->apply_local( $conflict );
				break;

			case 'remote':
				$result = $this->apply_remote( $conflict );
				break;

			case 'merge':
				$result = $this->apply_merge( $conflict );
				break;

			case 'skip':
				$result = true;
				break;
		}

		if ( ! $result && ! is_wp_error( $result ) ) {
			return new \WP_Error( 'resolution_failed', __( 'Failed to apply resolution.', 'bkx-bkx-integration' ) );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Mark as resolved.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$this->table,
			array(
				'resolution'  => $resolution,
				'resolved_at' => current_time( 'mysql' ),
				'resolved_by' => get_current_user_id(),
			),
			array( 'id' => $conflict_id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Apply local version (discard remote changes).
	 *
	 * @param object $conflict Conflict object.
	 * @return bool|\WP_Error
	 */
	private function apply_local( $conflict ) {
		// No action needed - local data is already in place.
		// Optionally push local data to remote.
		return true;
	}

	/**
	 * Apply remote version (overwrite local).
	 *
	 * @param object $conflict Conflict object.
	 * @return bool|\WP_Error
	 */
	private function apply_remote( $conflict ) {
		$data = $conflict->remote_data;

		switch ( $conflict->object_type ) {
			case 'booking':
				return $this->apply_remote_booking( $conflict->local_id, $data );

			case 'customer':
				return $this->apply_remote_customer( $conflict->local_id, $data );

			default:
				return new \WP_Error( 'unsupported', __( 'Unsupported object type.', 'bkx-bkx-integration' ) );
		}
	}

	/**
	 * Apply remote booking data.
	 *
	 * @param int   $local_id Local booking ID.
	 * @param array $data     Remote data.
	 * @return bool
	 */
	private function apply_remote_booking( $local_id, $data ) {
		if ( ! $local_id ) {
			return false;
		}

		// Update post.
		wp_update_post(
			array(
				'ID'          => $local_id,
				'post_status' => $data['status'] ?? 'bkx-pending',
			)
		);

		// Update meta.
		$meta_mapping = array(
			'date'           => 'booking_date',
			'time'           => 'booking_time',
			'service_id'     => 'booking_multi_base',
			'staff_id'       => 'booking_multi_seat',
			'customer_email' => 'customer_email',
			'customer_name'  => 'customer_primary_name',
			'customer_phone' => 'customer_phone',
			'total_price'    => 'booking_total_with_currency',
			'notes'          => 'booking_notes',
		);

		foreach ( $meta_mapping as $data_key => $meta_key ) {
			if ( isset( $data[ $data_key ] ) ) {
				update_post_meta( $local_id, $meta_key, $data[ $data_key ] );
			}
		}

		return true;
	}

	/**
	 * Apply remote customer data.
	 *
	 * @param int   $local_id Local user ID.
	 * @param array $data     Remote data.
	 * @return bool
	 */
	private function apply_remote_customer( $local_id, $data ) {
		if ( ! $local_id ) {
			return false;
		}

		wp_update_user(
			array(
				'ID'           => $local_id,
				'first_name'   => $data['first_name'] ?? '',
				'last_name'    => $data['last_name'] ?? '',
				'display_name' => $data['display_name'] ?? '',
			)
		);

		if ( ! empty( $data['phone'] ) ) {
			update_user_meta( $local_id, 'billing_phone', $data['phone'] );
		}

		return true;
	}

	/**
	 * Apply merged version.
	 *
	 * @param object $conflict Conflict object.
	 * @return bool|\WP_Error
	 */
	private function apply_merge( $conflict ) {
		// Merge strategy: prefer local for core fields, remote for metadata.
		$local  = $conflict->local_data ?? array();
		$remote = $conflict->remote_data ?? array();

		$merged = array_merge( $remote, $local );

		switch ( $conflict->object_type ) {
			case 'booking':
				return $this->apply_remote_booking( $conflict->local_id, $merged );

			case 'customer':
				return $this->apply_remote_customer( $conflict->local_id, $merged );

			default:
				return new \WP_Error( 'unsupported', __( 'Unsupported object type.', 'bkx-bkx-integration' ) );
		}
	}

	/**
	 * Count pending conflicts.
	 *
	 * @return int
	 */
	public function count_pending() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table} WHERE resolution IS NULL"
		);
	}

	/**
	 * Get conflict statistics.
	 *
	 * @return array
	 */
	public function get_stats() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$by_type = $wpdb->get_results(
			"SELECT conflict_type, COUNT(*) as count FROM {$this->table} WHERE resolution IS NULL GROUP BY conflict_type",
			OBJECT_K
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$resolved = $wpdb->get_results(
			"SELECT resolution, COUNT(*) as count FROM {$this->table} WHERE resolution IS NOT NULL GROUP BY resolution",
			OBJECT_K
		);

		return array(
			'pending'          => $this->count_pending(),
			'by_type'          => $by_type,
			'resolved_by_type' => $resolved,
		);
	}

	/**
	 * Delete resolved conflicts older than X days.
	 *
	 * @param int $days Days to keep.
	 * @return int Number deleted.
	 */
	public function cleanup( $days = 30 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE resolution IS NOT NULL AND resolved_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);
	}
}
