<?php
/**
 * Time Tracker Service.
 *
 * @package BookingX\StaffAnalytics\Services
 * @since   1.0.0
 */

namespace BookingX\StaffAnalytics\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TimeTracker Class.
 */
class TimeTracker {

	/**
	 * Clock in.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $notes    Optional notes.
	 * @return int|\WP_Error Log ID or error.
	 */
	public function clock_in( $staff_id, $notes = '' ) {
		global $wpdb;

		// Check if already clocked in.
		if ( $this->is_clocked_in( $staff_id ) ) {
			return new \WP_Error( 'already_clocked_in', __( 'Staff member is already clocked in', 'bkx-staff-analytics' ) );
		}

		$table = $wpdb->prefix . 'bkx_staff_time_logs';

		$result = $wpdb->insert(
			$table,
			array(
				'staff_id' => absint( $staff_id ),
				'log_date' => current_time( 'Y-m-d' ),
				'clock_in' => current_time( 'mysql' ),
				'notes'    => sanitize_textarea_field( $notes ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to clock in', 'bkx-staff-analytics' ) );
		}

		$log_id = $wpdb->insert_id;

		do_action( 'bkx_staff_clocked_in', $staff_id, $log_id );

		return $log_id;
	}

	/**
	 * Clock out.
	 *
	 * @param int $staff_id      Staff ID.
	 * @param int $break_minutes Break minutes.
	 * @return float|\WP_Error Total hours or error.
	 */
	public function clock_out( $staff_id, $break_minutes = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_time_logs';

		// Get active session.
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE staff_id = %d AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1",
				$staff_id
			),
			ARRAY_A
		);

		if ( ! $session ) {
			return new \WP_Error( 'not_clocked_in', __( 'Staff member is not clocked in', 'bkx-staff-analytics' ) );
		}

		$clock_out = current_time( 'mysql' );

		// Calculate total hours.
		$clock_in_time  = strtotime( $session['clock_in'] );
		$clock_out_time = strtotime( $clock_out );
		$total_minutes  = ( $clock_out_time - $clock_in_time ) / 60 - $break_minutes;
		$total_hours    = round( $total_minutes / 60, 2 );

		$result = $wpdb->update(
			$table,
			array(
				'clock_out'     => $clock_out,
				'break_minutes' => absint( $break_minutes ),
				'total_hours'   => $total_hours,
			),
			array( 'id' => $session['id'] ),
			array( '%s', '%d', '%f' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to clock out', 'bkx-staff-analytics' ) );
		}

		do_action( 'bkx_staff_clocked_out', $staff_id, $session['id'], $total_hours );

		return $total_hours;
	}

	/**
	 * Check if staff is clocked in.
	 *
	 * @param int $staff_id Staff ID.
	 * @return bool
	 */
	public function is_clocked_in( $staff_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_time_logs';

		$active = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE staff_id = %d AND clock_out IS NULL LIMIT 1",
				$staff_id
			)
		);

		return ! empty( $active );
	}

	/**
	 * Get current session.
	 *
	 * @param int $staff_id Staff ID.
	 * @return array|null
	 */
	public function get_current_session( $staff_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_time_logs';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE staff_id = %d AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1",
				$staff_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get time logs.
	 *
	 * @param int   $staff_id Staff ID (0 for all).
	 * @param array $args     Query arguments.
	 * @return array
	 */
	public function get_time_logs( $staff_id = 0, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'start_date' => gmdate( 'Y-m-01' ),
			'end_date'   => gmdate( 'Y-m-d' ),
			'limit'      => 100,
			'offset'     => 0,
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'bkx_staff_time_logs';

		$where = $wpdb->prepare(
			"WHERE log_date BETWEEN %s AND %s",
			$args['start_date'],
			$args['end_date']
		);

		if ( $staff_id > 0 ) {
			$where .= $wpdb->prepare( " AND staff_id = %d", $staff_id );
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.*, p.post_title as staff_name
				FROM {$table} t
				LEFT JOIN {$wpdb->posts} p ON t.staff_id = p.ID
				{$where}
				ORDER BY t.log_date DESC, t.clock_in DESC
				LIMIT %d OFFSET %d",
				$args['limit'],
				$args['offset']
			),
			ARRAY_A
		);
	}

	/**
	 * Get time summary.
	 *
	 * @param int   $staff_id Staff ID (0 for all).
	 * @param array $args     Query arguments.
	 * @return array
	 */
	public function get_time_summary( $staff_id = 0, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'start_date' => gmdate( 'Y-m-01' ),
			'end_date'   => gmdate( 'Y-m-d' ),
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'bkx_staff_time_logs';

		$where = $wpdb->prepare(
			"WHERE log_date BETWEEN %s AND %s AND clock_out IS NOT NULL",
			$args['start_date'],
			$args['end_date']
		);

		if ( $staff_id > 0 ) {
			$where .= $wpdb->prepare( " AND staff_id = %d", $staff_id );
		}

		$result = $wpdb->get_row(
			"SELECT
				SUM(total_hours) as total_hours,
				SUM(break_minutes) as total_break_minutes,
				COUNT(DISTINCT log_date) as days_worked,
				AVG(total_hours) as avg_hours_per_day,
				MIN(clock_in) as earliest_clock_in,
				MAX(clock_out) as latest_clock_out
			FROM {$table}
			{$where}",
			ARRAY_A
		);

		// Get daily breakdown.
		$daily = $wpdb->get_results(
			"SELECT
				log_date,
				SUM(total_hours) as hours,
				SUM(break_minutes) as breaks
			FROM {$table}
			{$where}
			GROUP BY log_date
			ORDER BY log_date ASC",
			ARRAY_A
		);

		return array(
			'total_hours'         => round( floatval( $result['total_hours'] ), 2 ),
			'total_break_minutes' => (int) $result['total_break_minutes'],
			'days_worked'         => (int) $result['days_worked'],
			'avg_hours_per_day'   => round( floatval( $result['avg_hours_per_day'] ), 2 ),
			'earliest_clock_in'   => $result['earliest_clock_in'],
			'latest_clock_out'    => $result['latest_clock_out'],
			'daily'               => $daily,
		);
	}

	/**
	 * Update time log.
	 *
	 * @param int   $log_id Log ID.
	 * @param array $data   Update data.
	 * @return bool
	 */
	public function update_log( $log_id, $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_time_logs';

		$update = array();
		$format = array();

		if ( isset( $data['clock_in'] ) ) {
			$update['clock_in'] = sanitize_text_field( $data['clock_in'] );
			$format[]           = '%s';
		}

		if ( isset( $data['clock_out'] ) ) {
			$update['clock_out'] = sanitize_text_field( $data['clock_out'] );
			$format[]            = '%s';
		}

		if ( isset( $data['break_minutes'] ) ) {
			$update['break_minutes'] = absint( $data['break_minutes'] );
			$format[]                = '%d';
		}

		if ( isset( $data['notes'] ) ) {
			$update['notes'] = sanitize_textarea_field( $data['notes'] );
			$format[]        = '%s';
		}

		// Recalculate total hours if times changed.
		if ( isset( $update['clock_in'] ) || isset( $update['clock_out'] ) || isset( $update['break_minutes'] ) ) {
			$log = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $log_id ),
				ARRAY_A
			);

			$clock_in  = isset( $update['clock_in'] ) ? $update['clock_in'] : $log['clock_in'];
			$clock_out = isset( $update['clock_out'] ) ? $update['clock_out'] : $log['clock_out'];
			$breaks    = isset( $update['break_minutes'] ) ? $update['break_minutes'] : $log['break_minutes'];

			if ( $clock_in && $clock_out ) {
				$total_minutes       = ( strtotime( $clock_out ) - strtotime( $clock_in ) ) / 60 - $breaks;
				$update['total_hours'] = round( $total_minutes / 60, 2 );
				$format[]            = '%f';
			}
		}

		if ( empty( $update ) ) {
			return true;
		}

		$result = $wpdb->update(
			$table,
			$update,
			array( 'id' => $log_id ),
			$format,
			array( '%d' )
		);

		return $result !== false;
	}

	/**
	 * Delete time log.
	 *
	 * @param int $log_id Log ID.
	 * @return bool
	 */
	public function delete_log( $log_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_time_logs';

		return $wpdb->delete( $table, array( 'id' => $log_id ), array( '%d' ) ) !== false;
	}

	/**
	 * Get staff currently clocked in.
	 *
	 * @return array
	 */
	public function get_clocked_in_staff() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_time_logs';

		return $wpdb->get_results(
			"SELECT t.*, p.post_title as staff_name,
				TIMESTAMPDIFF(MINUTE, t.clock_in, NOW()) as minutes_elapsed
			FROM {$table} t
			LEFT JOIN {$wpdb->posts} p ON t.staff_id = p.ID
			WHERE t.clock_out IS NULL
			ORDER BY t.clock_in ASC",
			ARRAY_A
		);
	}
}
