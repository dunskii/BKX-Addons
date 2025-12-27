<?php
/**
 * Time Off Service
 *
 * @package BookingX\StaffBreaks
 * @since   1.0.0
 */

namespace BookingX\StaffBreaks\Services;

use BookingX\StaffBreaks\StaffBreaksAddon;

/**
 * Class TimeOffService
 *
 * Handles time off, vacation, and blocked dates.
 *
 * @since 1.0.0
 */
class TimeOffService {

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
		$this->table = $wpdb->prefix . 'bkx_staff_timeoff';
	}

	/**
	 * Get time off entries.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id Seat ID (0 for all).
	 * @param string $status  Status filter.
	 * @return array
	 */
	public function get_timeoff( int $seat_id = 0, string $status = '' ): array {
		global $wpdb;

		$where = array( '1=1' );
		$args  = array();

		if ( $seat_id > 0 ) {
			$where[] = 'seat_id = %d';
			$args[]  = $seat_id;
		}

		if ( ! empty( $status ) ) {
			$where[] = 'status = %s';
			$args[]  = $status;
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table} WHERE {$where_clause} ORDER BY start_date DESC",
				...$args
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Get time off for a specific date range.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id    Seat ID.
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array
	 */
	public function get_timeoff_for_range( int $seat_id, string $start_date, string $end_date ): array {
		global $wpdb;

		$current_year = gmdate( 'Y' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table}
				WHERE seat_id = %d
				AND status = 'approved'
				AND (
					(start_date <= %s AND end_date >= %s)
					OR (recurring = 'yearly' AND CONCAT(%s, '-', DATE_FORMAT(start_date, '%%m-%%d')) <= %s
						AND CONCAT(%s, '-', DATE_FORMAT(end_date, '%%m-%%d')) >= %s)
				)
				ORDER BY start_date",
				$seat_id,
				$end_date,
				$start_date,
				$current_year,
				$end_date,
				$current_year,
				$start_date
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Check if a date is blocked.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id Seat ID.
	 * @param string $date    Date (Y-m-d).
	 * @param string $time    Optional time (H:i).
	 * @return bool
	 */
	public function is_date_blocked( int $seat_id, string $date, string $time = '' ): bool {
		$timeoff = $this->get_timeoff_for_range( $seat_id, $date, $date );

		foreach ( $timeoff as $entry ) {
			// Handle recurring yearly.
			$entry_start = $entry['start_date'];
			$entry_end   = $entry['end_date'];

			if ( 'yearly' === $entry['recurring'] ) {
				$current_year = gmdate( 'Y', strtotime( $date ) );
				$entry_start  = $current_year . '-' . gmdate( 'm-d', strtotime( $entry_start ) );
				$entry_end    = $current_year . '-' . gmdate( 'm-d', strtotime( $entry_end ) );
			}

			// Check date range.
			if ( $date < $entry_start || $date > $entry_end ) {
				continue;
			}

			// All day blocks.
			if ( $entry['all_day'] ) {
				return true;
			}

			// Time-specific blocks.
			if ( ! empty( $time ) ) {
				if ( $time >= $entry['start_time'] && $time < $entry['end_time'] ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get blocked time ranges for a date.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id Seat ID.
	 * @param string $date    Date (Y-m-d).
	 * @return array Array of blocked time ranges.
	 */
	public function get_blocked_times( int $seat_id, string $date ): array {
		$timeoff = $this->get_timeoff_for_range( $seat_id, $date, $date );
		$blocked = array();

		foreach ( $timeoff as $entry ) {
			// Handle recurring yearly.
			$entry_start = $entry['start_date'];
			$entry_end   = $entry['end_date'];

			if ( 'yearly' === $entry['recurring'] ) {
				$current_year = gmdate( 'Y', strtotime( $date ) );
				$entry_start  = $current_year . '-' . gmdate( 'm-d', strtotime( $entry_start ) );
				$entry_end    = $current_year . '-' . gmdate( 'm-d', strtotime( $entry_end ) );
			}

			// Check date range.
			if ( $date < $entry_start || $date > $entry_end ) {
				continue;
			}

			if ( $entry['all_day'] ) {
				$blocked[] = array(
					'start'   => '00:00',
					'end'     => '23:59',
					'all_day' => true,
					'type'    => $entry['type'],
					'reason'  => $entry['reason'],
				);
			} else {
				$blocked[] = array(
					'start'   => substr( $entry['start_time'], 0, 5 ),
					'end'     => substr( $entry['end_time'], 0, 5 ),
					'all_day' => false,
					'type'    => $entry['type'],
					'reason'  => $entry['reason'],
				);
			}
		}

		return $blocked;
	}

	/**
	 * Add time off entry.
	 *
	 * @since 1.0.0
	 * @param array $data Entry data.
	 * @return int|\WP_Error Entry ID or error.
	 */
	public function add_timeoff( array $data ) {
		global $wpdb;

		// Validate.
		$validation = $this->validate_timeoff( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			array(
				'seat_id'    => absint( $data['seat_id'] ),
				'start_date' => sanitize_text_field( $data['start_date'] ),
				'end_date'   => sanitize_text_field( $data['end_date'] ),
				'start_time' => sanitize_text_field( $data['start_time'] ?? '00:00:00' ),
				'end_time'   => sanitize_text_field( $data['end_time'] ?? '23:59:59' ),
				'all_day'    => ! empty( $data['all_day'] ) ? 1 : 0,
				'type'       => sanitize_text_field( $data['type'] ?? 'vacation' ),
				'reason'     => sanitize_textarea_field( $data['reason'] ?? '' ),
				'recurring'  => sanitize_text_field( $data['recurring'] ?? '' ),
				'status'     => sanitize_text_field( $data['status'] ?? 'pending' ),
				'created_by' => get_current_user_id(),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to save time off entry.', 'bkx-staff-breaks' ) );
		}

		$entry_id = $wpdb->insert_id;

		/**
		 * Fires after a time off entry is added.
		 *
		 * @since 1.0.0
		 * @param int   $entry_id Entry ID.
		 * @param array $data     Entry data.
		 */
		do_action( 'bkx_timeoff_added', $entry_id, $data );

		// Send notification if pending approval.
		if ( 'pending' === ( $data['status'] ?? 'pending' ) ) {
			$this->notify_pending_approval( $entry_id, $data );
		}

		return $entry_id;
	}

	/**
	 * Update time off entry.
	 *
	 * @since 1.0.0
	 * @param int   $entry_id Entry ID.
	 * @param array $data     Entry data.
	 * @return int|\WP_Error Entry ID or error.
	 */
	public function update_timeoff( int $entry_id, array $data ) {
		global $wpdb;

		// Validate.
		$validation = $this->validate_timeoff( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			array(
				'seat_id'    => absint( $data['seat_id'] ),
				'start_date' => sanitize_text_field( $data['start_date'] ),
				'end_date'   => sanitize_text_field( $data['end_date'] ),
				'start_time' => sanitize_text_field( $data['start_time'] ?? '00:00:00' ),
				'end_time'   => sanitize_text_field( $data['end_time'] ?? '23:59:59' ),
				'all_day'    => ! empty( $data['all_day'] ) ? 1 : 0,
				'type'       => sanitize_text_field( $data['type'] ?? 'vacation' ),
				'reason'     => sanitize_textarea_field( $data['reason'] ?? '' ),
				'recurring'  => sanitize_text_field( $data['recurring'] ?? '' ),
			),
			array( 'id' => $entry_id ),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to update time off entry.', 'bkx-staff-breaks' ) );
		}

		/**
		 * Fires after a time off entry is updated.
		 *
		 * @since 1.0.0
		 * @param int   $entry_id Entry ID.
		 * @param array $data     Entry data.
		 */
		do_action( 'bkx_timeoff_updated', $entry_id, $data );

		return $entry_id;
	}

	/**
	 * Update time off status.
	 *
	 * @since 1.0.0
	 * @param int    $entry_id Entry ID.
	 * @param string $status   New status.
	 * @return bool
	 */
	public function update_status( int $entry_id, string $status ): bool {
		global $wpdb;

		$valid_statuses = array( 'pending', 'approved', 'rejected' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			array(
				'status'      => $status,
				'approved_by' => 'approved' === $status ? get_current_user_id() : 0,
			),
			array( 'id' => $entry_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		if ( $result ) {
			/**
			 * Fires after time off status is updated.
			 *
			 * @since 1.0.0
			 * @param int    $entry_id Entry ID.
			 * @param string $status   New status.
			 */
			do_action( 'bkx_timeoff_status_changed', $entry_id, $status );
		}

		return (bool) $result;
	}

	/**
	 * Delete time off entry.
	 *
	 * @since 1.0.0
	 * @param int $entry_id Entry ID.
	 * @return bool
	 */
	public function delete_timeoff( int $entry_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( $this->table, array( 'id' => $entry_id ), array( '%d' ) );

		if ( $result ) {
			/**
			 * Fires after time off entry is deleted.
			 *
			 * @since 1.0.0
			 * @param int $entry_id Entry ID.
			 */
			do_action( 'bkx_timeoff_deleted', $entry_id );
		}

		return (bool) $result;
	}

	/**
	 * Cleanup old entries.
	 *
	 * @since 1.0.0
	 * @param int $days_old Days to keep.
	 * @return int Number of entries deleted.
	 */
	public function cleanup_old_entries( int $days_old = 90 ): int {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d', strtotime( "-{$days_old} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$this->table}
				WHERE end_date < %s
				AND recurring = ''",
				$cutoff_date
			)
		);

		return $result ?: 0;
	}

	/**
	 * Validate time off data.
	 *
	 * @since 1.0.0
	 * @param array $data Entry data.
	 * @return true|\WP_Error
	 */
	private function validate_timeoff( array $data ) {
		if ( empty( $data['seat_id'] ) ) {
			return new \WP_Error( 'no_seat', __( 'Resource is required.', 'bkx-staff-breaks' ) );
		}

		if ( empty( $data['start_date'] ) || empty( $data['end_date'] ) ) {
			return new \WP_Error( 'no_dates', __( 'Start and end dates are required.', 'bkx-staff-breaks' ) );
		}

		if ( $data['start_date'] > $data['end_date'] ) {
			return new \WP_Error( 'invalid_dates', __( 'End date must be on or after start date.', 'bkx-staff-breaks' ) );
		}

		$valid_types = array( 'vacation', 'sick', 'personal', 'blocked', 'holiday', 'other' );
		if ( ! empty( $data['type'] ) && ! in_array( $data['type'], $valid_types, true ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid time off type.', 'bkx-staff-breaks' ) );
		}

		return true;
	}

	/**
	 * Send notification for pending approval.
	 *
	 * @since 1.0.0
	 * @param int   $entry_id Entry ID.
	 * @param array $data     Entry data.
	 * @return void
	 */
	private function notify_pending_approval( int $entry_id, array $data ): void {
		$admin_email = get_option( 'admin_email' );
		$seat        = get_post( $data['seat_id'] );
		$seat_name   = $seat ? $seat->post_title : __( 'Unknown', 'bkx-staff-breaks' );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Time Off Request Pending Approval', 'bkx-staff-breaks' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: resource name, 2: type, 3: start date, 4: end date, 5: approval URL */
			__(
				"A new time off request requires your approval:\n\n" .
				"Resource: %1\$s\n" .
				"Type: %2\$s\n" .
				"From: %3\$s\n" .
				"To: %4\$s\n\n" .
				"Review at: %5\$s",
				'bkx-staff-breaks'
			),
			$seat_name,
			ucfirst( $data['type'] ?? 'vacation' ),
			$data['start_date'],
			$data['end_date'],
			admin_url( 'admin.php?page=bkx-staff-breaks&tab=pending' )
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Get time off types.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_types(): array {
		return array(
			'vacation' => __( 'Vacation', 'bkx-staff-breaks' ),
			'sick'     => __( 'Sick Leave', 'bkx-staff-breaks' ),
			'personal' => __( 'Personal', 'bkx-staff-breaks' ),
			'blocked'  => __( 'Blocked Time', 'bkx-staff-breaks' ),
			'holiday'  => __( 'Holiday', 'bkx-staff-breaks' ),
			'other'    => __( 'Other', 'bkx-staff-breaks' ),
		);
	}
}
