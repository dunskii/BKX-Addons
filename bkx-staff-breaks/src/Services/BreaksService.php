<?php
/**
 * Breaks Service
 *
 * @package BookingX\StaffBreaks
 * @since   1.0.0
 */

namespace BookingX\StaffBreaks\Services;

use BookingX\StaffBreaks\StaffBreaksAddon;

/**
 * Class BreaksService
 *
 * Handles recurring daily breaks (e.g., lunch breaks).
 *
 * @since 1.0.0
 */
class BreaksService {

	/**
	 * Addon instance.
	 *
	 * @var StaffBreaksAddon
	 */
	private StaffBreaksAddon $addon;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param StaffBreaksAddon $addon Addon instance.
	 */
	public function __construct( StaffBreaksAddon $addon ) {
		global $wpdb;
		$this->addon = $addon;
		$this->table = $wpdb->prefix . 'bkx_staff_breaks';
	}

	/**
	 * Get breaks for a seat.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id Seat ID (0 for all).
	 * @param string $day     Day filter (empty for all days).
	 * @return array
	 */
	public function get_breaks( int $seat_id = 0, string $day = '' ): array {
		global $wpdb;

		$where = array( '1=1' );
		$args  = array();

		if ( $seat_id > 0 ) {
			$where[] = 'seat_id = %d';
			$args[]  = $seat_id;
		}

		if ( ! empty( $day ) ) {
			$where[] = '(day = %s OR day = %s)';
			$args[]  = $day;
			$args[]  = 'all';
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table} WHERE {$where_clause} ORDER BY day, start_time",
				...$args
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Get breaks for a seat on a specific day.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id Seat ID.
	 * @param string $day     Day name (monday, tuesday, etc.).
	 * @return array
	 */
	public function get_breaks_for_day( int $seat_id, string $day ): array {
		return $this->get_breaks( $seat_id, strtolower( $day ) );
	}

	/**
	 * Add a break.
	 *
	 * @since 1.0.0
	 * @param array $data Break data.
	 * @return int|\WP_Error Break ID or error.
	 */
	public function add_break( array $data ) {
		global $wpdb;

		// Validate.
		$validation = $this->validate_break( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Check for overlapping breaks.
		$overlaps = $this->check_overlapping( $data );
		if ( $overlaps ) {
			return new \WP_Error( 'overlap', __( 'This break overlaps with an existing break.', 'bkx-staff-breaks' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			array(
				'seat_id'    => absint( $data['seat_id'] ),
				'day'        => sanitize_text_field( $data['day'] ),
				'start_time' => sanitize_text_field( $data['start_time'] ),
				'end_time'   => sanitize_text_field( $data['end_time'] ),
				'label'      => sanitize_text_field( $data['label'] ?? '' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to save break.', 'bkx-staff-breaks' ) );
		}

		$break_id = $wpdb->insert_id;

		/**
		 * Fires after a break is added.
		 *
		 * @since 1.0.0
		 * @param int   $break_id Break ID.
		 * @param array $data     Break data.
		 */
		do_action( 'bkx_staff_break_added', $break_id, $data );

		return $break_id;
	}

	/**
	 * Update a break.
	 *
	 * @since 1.0.0
	 * @param int   $break_id Break ID.
	 * @param array $data     Break data.
	 * @return int|\WP_Error Break ID or error.
	 */
	public function update_break( int $break_id, array $data ) {
		global $wpdb;

		// Validate.
		$validation = $this->validate_break( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Check for overlapping breaks (excluding self).
		$overlaps = $this->check_overlapping( $data, $break_id );
		if ( $overlaps ) {
			return new \WP_Error( 'overlap', __( 'This break overlaps with an existing break.', 'bkx-staff-breaks' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			array(
				'seat_id'    => absint( $data['seat_id'] ),
				'day'        => sanitize_text_field( $data['day'] ),
				'start_time' => sanitize_text_field( $data['start_time'] ),
				'end_time'   => sanitize_text_field( $data['end_time'] ),
				'label'      => sanitize_text_field( $data['label'] ?? '' ),
			),
			array( 'id' => $break_id ),
			array( '%d', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to update break.', 'bkx-staff-breaks' ) );
		}

		/**
		 * Fires after a break is updated.
		 *
		 * @since 1.0.0
		 * @param int   $break_id Break ID.
		 * @param array $data     Break data.
		 */
		do_action( 'bkx_staff_break_updated', $break_id, $data );

		return $break_id;
	}

	/**
	 * Delete a break.
	 *
	 * @since 1.0.0
	 * @param int $break_id Break ID.
	 * @return bool
	 */
	public function delete_break( int $break_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( $this->table, array( 'id' => $break_id ), array( '%d' ) );

		if ( $result ) {
			/**
			 * Fires after a break is deleted.
			 *
			 * @since 1.0.0
			 * @param int $break_id Break ID.
			 */
			do_action( 'bkx_staff_break_deleted', $break_id );
		}

		return (bool) $result;
	}

	/**
	 * Delete all breaks for a seat.
	 *
	 * @since 1.0.0
	 * @param int $seat_id Seat ID.
	 * @return bool
	 */
	public function delete_breaks_for_seat( int $seat_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( $this->table, array( 'seat_id' => $seat_id ), array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Validate break data.
	 *
	 * @since 1.0.0
	 * @param array $data Break data.
	 * @return true|\WP_Error
	 */
	private function validate_break( array $data ) {
		if ( empty( $data['seat_id'] ) ) {
			return new \WP_Error( 'no_seat', __( 'Seat is required.', 'bkx-staff-breaks' ) );
		}

		$valid_days = array( 'all', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		if ( empty( $data['day'] ) || ! in_array( strtolower( $data['day'] ), $valid_days, true ) ) {
			return new \WP_Error( 'invalid_day', __( 'Invalid day.', 'bkx-staff-breaks' ) );
		}

		if ( empty( $data['start_time'] ) || empty( $data['end_time'] ) ) {
			return new \WP_Error( 'no_time', __( 'Start and end times are required.', 'bkx-staff-breaks' ) );
		}

		if ( $data['start_time'] >= $data['end_time'] ) {
			return new \WP_Error( 'invalid_time', __( 'End time must be after start time.', 'bkx-staff-breaks' ) );
		}

		return true;
	}

	/**
	 * Check for overlapping breaks.
	 *
	 * @since 1.0.0
	 * @param array $data       Break data.
	 * @param int   $exclude_id Break ID to exclude.
	 * @return bool
	 */
	private function check_overlapping( array $data, int $exclude_id = 0 ): bool {
		global $wpdb;

		$query = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"SELECT COUNT(*) FROM {$this->table}
			WHERE seat_id = %d
			AND (day = %s OR day = 'all' OR %s = 'all')
			AND start_time < %s
			AND end_time > %s
			AND id != %d",
			$data['seat_id'],
			$data['day'],
			$data['day'],
			$data['end_time'],
			$data['start_time'],
			$exclude_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$count = (int) $wpdb->get_var( $query );

		return $count > 0;
	}

	/**
	 * Check if a time slot conflicts with breaks.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id    Seat ID.
	 * @param string $day        Day name.
	 * @param string $start_time Start time (H:i).
	 * @param string $end_time   End time (H:i).
	 * @return bool True if conflicts with a break.
	 */
	public function conflicts_with_break( int $seat_id, string $day, string $start_time, string $end_time ): bool {
		$breaks = $this->get_breaks_for_day( $seat_id, $day );

		foreach ( $breaks as $break ) {
			// Check if slot overlaps with break.
			if ( $start_time < $break['end_time'] && $end_time > $break['start_time'] ) {
				return true;
			}
		}

		return false;
	}
}
