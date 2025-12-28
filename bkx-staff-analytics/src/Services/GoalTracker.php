<?php
/**
 * Goal Tracker Service.
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
 * GoalTracker Class.
 */
class GoalTracker {

	/**
	 * Goal types.
	 *
	 * @var array
	 */
	private $goal_types = array(
		'revenue'      => 'Revenue Target',
		'bookings'     => 'Booking Count',
		'hours'        => 'Billable Hours',
		'rating'       => 'Average Rating',
		'new_customers' => 'New Customers',
		'completion'   => 'Completion Rate',
	);

	/**
	 * Save a goal.
	 *
	 * @param array $data Goal data.
	 * @return int|\WP_Error Goal ID or error.
	 */
	public function save_goal( $data ) {
		global $wpdb;

		// Validate.
		if ( empty( $data['staff_id'] ) ) {
			return new \WP_Error( 'missing_staff', __( 'Staff member is required', 'bkx-staff-analytics' ) );
		}

		if ( empty( $data['goal_type'] ) || ! isset( $this->goal_types[ $data['goal_type'] ] ) ) {
			return new \WP_Error( 'invalid_goal_type', __( 'Invalid goal type', 'bkx-staff-analytics' ) );
		}

		if ( $data['target_value'] <= 0 ) {
			return new \WP_Error( 'invalid_target', __( 'Target value must be greater than zero', 'bkx-staff-analytics' ) );
		}

		if ( empty( $data['start_date'] ) || empty( $data['end_date'] ) ) {
			return new \WP_Error( 'missing_dates', __( 'Start and end dates are required', 'bkx-staff-analytics' ) );
		}

		if ( strtotime( $data['end_date'] ) < strtotime( $data['start_date'] ) ) {
			return new \WP_Error( 'invalid_dates', __( 'End date must be after start date', 'bkx-staff-analytics' ) );
		}

		$table = $wpdb->prefix . 'bkx_staff_goals';

		$goal_data = array(
			'staff_id'     => absint( $data['staff_id'] ),
			'goal_type'    => sanitize_text_field( $data['goal_type'] ),
			'goal_period'  => sanitize_text_field( $data['goal_period'] ?? 'monthly' ),
			'target_value' => floatval( $data['target_value'] ),
			'start_date'   => sanitize_text_field( $data['start_date'] ),
			'end_date'     => sanitize_text_field( $data['end_date'] ),
			'is_active'    => 1,
		);

		$formats = array( '%d', '%s', '%s', '%f', '%s', '%s', '%d' );

		if ( ! empty( $data['id'] ) ) {
			// Update existing goal.
			$result = $wpdb->update(
				$table,
				$goal_data,
				array( 'id' => absint( $data['id'] ) ),
				$formats,
				array( '%d' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'db_error', __( 'Database error occurred', 'bkx-staff-analytics' ) );
			}

			return absint( $data['id'] );
		} else {
			// Insert new goal.
			$result = $wpdb->insert( $table, $goal_data, $formats );

			if ( ! $result ) {
				return new \WP_Error( 'db_error', __( 'Database error occurred', 'bkx-staff-analytics' ) );
			}

			return $wpdb->insert_id;
		}
	}

	/**
	 * Delete a goal.
	 *
	 * @param int $goal_id Goal ID.
	 * @return bool
	 */
	public function delete_goal( $goal_id ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'bkx_staff_goals';
		$result = $wpdb->delete( $table, array( 'id' => absint( $goal_id ) ), array( '%d' ) );

		return $result !== false;
	}

	/**
	 * Get staff goals.
	 *
	 * @param int   $staff_id    Staff ID.
	 * @param array $args        Query arguments.
	 * @return array
	 */
	public function get_staff_goals( $staff_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'active_only'     => true,
			'include_progress' => true,
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'bkx_staff_goals';

		$where = $wpdb->prepare( "WHERE staff_id = %d", $staff_id );

		if ( $args['active_only'] ) {
			$where .= " AND is_active = 1 AND end_date >= CURDATE()";
		}

		$goals = $wpdb->get_results(
			"SELECT * FROM {$table} {$where} ORDER BY end_date ASC",
			ARRAY_A
		);

		if ( $args['include_progress'] ) {
			foreach ( $goals as &$goal ) {
				$goal['progress'] = $this->calculate_goal_progress( $goal );
				$goal['type_label'] = $this->goal_types[ $goal['goal_type'] ] ?? $goal['goal_type'];
			}
		}

		return $goals;
	}

	/**
	 * Get all active goals.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all_goals( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'active_only'      => true,
			'include_progress' => true,
			'limit'            => 50,
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'bkx_staff_goals';

		$where = "WHERE 1=1";

		if ( $args['active_only'] ) {
			$where .= " AND is_active = 1 AND end_date >= CURDATE()";
		}

		$goals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT g.*, p.post_title as staff_name
				FROM {$table} g
				LEFT JOIN {$wpdb->posts} p ON g.staff_id = p.ID
				{$where}
				ORDER BY g.end_date ASC
				LIMIT %d",
				$args['limit']
			),
			ARRAY_A
		);

		if ( $args['include_progress'] ) {
			foreach ( $goals as &$goal ) {
				$goal['progress']   = $this->calculate_goal_progress( $goal );
				$goal['type_label'] = $this->goal_types[ $goal['goal_type'] ] ?? $goal['goal_type'];
			}
		}

		return $goals;
	}

	/**
	 * Calculate goal progress.
	 *
	 * @param array $goal Goal data.
	 * @return array
	 */
	public function calculate_goal_progress( $goal ) {
		$metrics_service = new PerformanceMetrics();
		$metrics         = $metrics_service->get_staff_metrics(
			$goal['staff_id'],
			'custom',
			$goal['start_date'],
			$goal['end_date']
		);

		$current_value = 0;

		switch ( $goal['goal_type'] ) {
			case 'revenue':
				$current_value = $metrics['summary']['total_revenue'];
				break;

			case 'bookings':
				$current_value = $metrics['summary']['completed_bookings'];
				break;

			case 'hours':
				$current_value = $metrics['summary']['total_hours'];
				break;

			case 'rating':
				$current_value = $metrics['summary']['avg_rating'];
				break;

			case 'new_customers':
				$current_value = $metrics['summary']['new_customers'];
				break;

			case 'completion':
				$current_value = $metrics['calculated']['completion_rate'];
				break;
		}

		$percentage = 0;
		if ( $goal['target_value'] > 0 ) {
			$percentage = min( 100, round( ( $current_value / $goal['target_value'] ) * 100, 1 ) );
		}

		// Calculate days remaining.
		$days_remaining = max( 0, ceil( ( strtotime( $goal['end_date'] ) - time() ) / DAY_IN_SECONDS ) );
		$total_days     = ceil( ( strtotime( $goal['end_date'] ) - strtotime( $goal['start_date'] ) ) / DAY_IN_SECONDS );
		$days_elapsed   = $total_days - $days_remaining;

		// Calculate expected progress.
		$expected_percentage = 0;
		if ( $total_days > 0 ) {
			$expected_percentage = round( ( $days_elapsed / $total_days ) * 100, 1 );
		}

		// Determine status.
		$status = 'on_track';
		if ( $percentage >= 100 ) {
			$status = 'achieved';
		} elseif ( $percentage < $expected_percentage - 10 ) {
			$status = 'behind';
		} elseif ( $percentage > $expected_percentage + 10 ) {
			$status = 'ahead';
		}

		return array(
			'current_value'       => $current_value,
			'target_value'        => floatval( $goal['target_value'] ),
			'percentage'          => $percentage,
			'expected_percentage' => $expected_percentage,
			'days_remaining'      => $days_remaining,
			'days_elapsed'        => $days_elapsed,
			'total_days'          => $total_days,
			'status'              => $status,
		);
	}

	/**
	 * Get goal types.
	 *
	 * @return array
	 */
	public function get_goal_types() {
		return $this->goal_types;
	}

	/**
	 * Check for goals at risk.
	 *
	 * @return array Goals that are behind schedule.
	 */
	public function get_goals_at_risk() {
		$all_goals    = $this->get_all_goals( array( 'include_progress' => true ) );
		$at_risk      = array();

		foreach ( $all_goals as $goal ) {
			if ( 'behind' === $goal['progress']['status'] ) {
				$at_risk[] = $goal;
			}
		}

		return $at_risk;
	}

	/**
	 * Get recently achieved goals.
	 *
	 * @param int $days Number of days to look back.
	 * @return array
	 */
	public function get_recently_achieved( $days = 7 ) {
		$all_goals = $this->get_all_goals(
			array(
				'active_only'      => false,
				'include_progress' => true,
			)
		);

		$achieved    = array();
		$cutoff_date = gmdate( 'Y-m-d', strtotime( "-{$days} days" ) );

		foreach ( $all_goals as $goal ) {
			if ( 'achieved' === $goal['progress']['status'] && $goal['end_date'] >= $cutoff_date ) {
				$achieved[] = $goal;
			}
		}

		return $achieved;
	}
}
