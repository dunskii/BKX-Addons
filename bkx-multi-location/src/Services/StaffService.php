<?php
/**
 * Staff service for managing staff-location assignments.
 *
 * @package BookingX\MultiLocation\Services
 */

namespace BookingX\MultiLocation\Services;

defined( 'ABSPATH' ) || exit;

/**
 * StaffService class.
 */
class StaffService {

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
		$this->table = $wpdb->prefix . 'bkx_location_staff';
	}

	/**
	 * Get staff assignments for a location.
	 *
	 * @param int $location_id Location ID.
	 * @return array
	 */
	public function get_for_location( $location_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$assignments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ls.*, p.post_title as seat_name
				FROM {$this->table} ls
				LEFT JOIN {$wpdb->posts} p ON ls.seat_id = p.ID
				WHERE ls.location_id = %d AND ls.status = 'active'
				ORDER BY ls.is_primary DESC, p.post_title ASC",
				$location_id
			)
		);

		return $assignments;
	}

	/**
	 * Get seat IDs for a location.
	 *
	 * @param int $location_id Location ID.
	 * @return array
	 */
	public function get_seat_ids_for_location( $location_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT seat_id FROM {$this->table} WHERE location_id = %d AND status = 'active'",
				$location_id
			)
		);
	}

	/**
	 * Get locations for a staff member.
	 *
	 * @param int $seat_id Seat (staff) ID.
	 * @return array
	 */
	public function get_for_staff( $seat_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ls.*, l.name as location_name
				FROM {$this->table} ls
				LEFT JOIN {$wpdb->prefix}bkx_locations l ON ls.location_id = l.id
				WHERE ls.seat_id = %d AND ls.status = 'active'
				ORDER BY ls.is_primary DESC",
				$seat_id
			)
		);
	}

	/**
	 * Get primary location for a staff member.
	 *
	 * @param int $seat_id Seat (staff) ID.
	 * @return int|null Location ID or null.
	 */
	public function get_primary_location( $seat_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$location_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT location_id FROM {$this->table}
				WHERE seat_id = %d AND status = 'active'
				ORDER BY is_primary DESC
				LIMIT 1",
				$seat_id
			)
		);

		return $location_id ? absint( $location_id ) : null;
	}

	/**
	 * Assign staff to location.
	 *
	 * @param int  $location_id Location ID.
	 * @param int  $seat_id     Seat (staff) ID.
	 * @param bool $is_primary  Whether this is their primary location.
	 * @return int|\WP_Error Assignment ID or error.
	 */
	public function assign( $location_id, $seat_id, $is_primary = false ) {
		global $wpdb;

		// Check if assignment exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE location_id = %d AND seat_id = %d",
				$location_id,
				$seat_id
			)
		);

		if ( $existing ) {
			// Update existing.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$this->table,
				array(
					'is_primary' => $is_primary ? 1 : 0,
					'status'     => 'active',
				),
				array( 'id' => $existing->id ),
				array( '%d', '%s' ),
				array( '%d' )
			);

			// If setting as primary, unset other primaries.
			if ( $is_primary ) {
				$this->clear_other_primaries( $seat_id, $location_id );
			}

			return $existing->id;
		}

		// Create new assignment.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$this->table,
			array(
				'location_id' => $location_id,
				'seat_id'     => $seat_id,
				'is_primary'  => $is_primary ? 1 : 0,
				'status'      => 'active',
			),
			array( '%d', '%d', '%d', '%s' )
		);

		if ( ! $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to assign staff.', 'bkx-multi-location' ) );
		}

		// If setting as primary, unset other primaries.
		if ( $is_primary ) {
			$this->clear_other_primaries( $seat_id, $location_id );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Remove staff from location.
	 *
	 * @param int $location_id Location ID.
	 * @param int $seat_id     Seat (staff) ID.
	 * @return bool
	 */
	public function remove( $location_id, $seat_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete(
			$this->table,
			array(
				'location_id' => $location_id,
				'seat_id'     => $seat_id,
			),
			array( '%d', '%d' )
		);
	}

	/**
	 * Set primary location for staff.
	 *
	 * @param int $seat_id     Seat (staff) ID.
	 * @param int $location_id Location ID.
	 * @return bool
	 */
	public function set_primary( $seat_id, $location_id ) {
		global $wpdb;

		// Clear all primaries for this staff.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$this->table,
			array( 'is_primary' => 0 ),
			array( 'seat_id' => $seat_id ),
			array( '%d' ),
			array( '%d' )
		);

		// Set the new primary.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->update(
			$this->table,
			array( 'is_primary' => 1 ),
			array(
				'seat_id'     => $seat_id,
				'location_id' => $location_id,
			),
			array( '%d' ),
			array( '%d', '%d' )
		);
	}

	/**
	 * Clear other primary assignments for a staff member.
	 *
	 * @param int $seat_id     Seat (staff) ID.
	 * @param int $location_id Location ID to keep as primary.
	 */
	private function clear_other_primaries( $seat_id, $location_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table} SET is_primary = 0 WHERE seat_id = %d AND location_id != %d",
				$seat_id,
				$location_id
			)
		);
	}

	/**
	 * Get available staff for a location on a date.
	 *
	 * @param int    $location_id Location ID.
	 * @param string $date        Date in Y-m-d format.
	 * @param int    $service_id  Optional service ID.
	 * @return array
	 */
	public function get_available_for_location( $location_id, $date, $service_id = 0 ) {
		global $wpdb;

		// Get all active staff for this location.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$staff = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ls.seat_id, p.post_title as name
				FROM {$this->table} ls
				LEFT JOIN {$wpdb->posts} p ON ls.seat_id = p.ID
				WHERE ls.location_id = %d
				AND ls.status = 'active'
				AND p.post_status = 'publish'",
				$location_id
			)
		);

		if ( empty( $staff ) ) {
			return array();
		}

		$available = array();

		foreach ( $staff as $member ) {
			// Check if staff can perform this service.
			if ( $service_id ) {
				$can_perform = $this->staff_can_perform_service( $member->seat_id, $service_id );
				if ( ! $can_perform ) {
					continue;
				}
			}

			// Check staff's own availability for this date.
			$staff_available = $this->check_staff_availability( $member->seat_id, $date );
			if ( $staff_available ) {
				$available[] = $member;
			}
		}

		return $available;
	}

	/**
	 * Check if staff can perform a service.
	 *
	 * @param int $seat_id    Seat (staff) ID.
	 * @param int $service_id Service ID.
	 * @return bool
	 */
	private function staff_can_perform_service( $seat_id, $service_id ) {
		// Get staff's assigned services.
		$seat_bases = get_post_meta( $seat_id, 'seat_base', true );

		if ( empty( $seat_bases ) || ! is_array( $seat_bases ) ) {
			// No restrictions, can do all services.
			return true;
		}

		return in_array( $service_id, $seat_bases, true ) || in_array( (string) $service_id, $seat_bases, true );
	}

	/**
	 * Check staff availability for a date.
	 *
	 * @param int    $seat_id Seat (staff) ID.
	 * @param string $date    Date in Y-m-d format.
	 * @return bool
	 */
	private function check_staff_availability( $seat_id, $date ) {
		// Check seat's working days.
		$working_days = get_post_meta( $seat_id, 'seat_days', true );

		if ( ! empty( $working_days ) && is_array( $working_days ) ) {
			$day_of_week = strtolower( gmdate( 'l', strtotime( $date ) ) );
			if ( ! in_array( $day_of_week, $working_days, true ) ) {
				return false;
			}
		}

		// Check for vacation/time-off (if implemented in core).
		$vacation_start = get_post_meta( $seat_id, 'seat_vacation_start', true );
		$vacation_end   = get_post_meta( $seat_id, 'seat_vacation_end', true );

		if ( $vacation_start && $vacation_end ) {
			$date_ts       = strtotime( $date );
			$vacation_start_ts = strtotime( $vacation_start );
			$vacation_end_ts   = strtotime( $vacation_end );

			if ( $date_ts >= $vacation_start_ts && $date_ts <= $vacation_end_ts ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get all unassigned staff (not assigned to any location).
	 *
	 * @return array
	 */
	public function get_unassigned_staff() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			"SELECT p.ID, p.post_title as name
			FROM {$wpdb->posts} p
			LEFT JOIN {$this->table} ls ON p.ID = ls.seat_id AND ls.status = 'active'
			WHERE p.post_type = 'bkx_seat'
			AND p.post_status = 'publish'
			AND ls.id IS NULL
			ORDER BY p.post_title"
		);
	}

	/**
	 * Bulk assign staff to location.
	 *
	 * @param int   $location_id Location ID.
	 * @param array $seat_ids    Array of seat IDs.
	 * @return int Number of assignments made.
	 */
	public function bulk_assign( $location_id, $seat_ids ) {
		$count = 0;

		foreach ( $seat_ids as $seat_id ) {
			$result = $this->assign( $location_id, absint( $seat_id ) );
			if ( ! is_wp_error( $result ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get staff count for each location.
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
}
