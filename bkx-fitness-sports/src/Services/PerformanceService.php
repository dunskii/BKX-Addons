<?php
/**
 * Performance Service
 *
 * Manages workout logging and performance tracking.
 *
 * @package BookingX\FitnessSports\Services
 * @since   1.0.0
 */

namespace BookingX\FitnessSports\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PerformanceService
 *
 * @since 1.0.0
 */
class PerformanceService {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'bkx_workout_logs';
	}

	/**
	 * Log a workout.
	 *
	 * @since 1.0.0
	 * @param int   $user_id      User ID.
	 * @param array $workout_data Workout data.
	 * @return int|\WP_Error Log ID or error.
	 */
	public function log_workout( int $user_id, array $workout_data ) {
		global $wpdb;

		$data = array(
			'user_id'      => $user_id,
			'workout_type' => sanitize_text_field( $workout_data['type'] ?? 'general' ),
			'duration'     => absint( $workout_data['duration'] ?? 0 ),
			'calories'     => absint( $workout_data['calories'] ?? 0 ),
			'exercises'    => wp_json_encode( $workout_data['exercises'] ?? array() ),
			'notes'        => sanitize_textarea_field( $workout_data['notes'] ?? '' ),
			'workout_date' => sanitize_text_field( $workout_data['date'] ?? current_time( 'Y-m-d' ) ),
			'created_at'   => current_time( 'mysql' ),
		);

		$result = $wpdb->insert(
			$this->table_name,
			$data,
			array( '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'log_failed', __( 'Failed to log workout.', 'bkx-fitness-sports' ) );
		}

		$log_id = $wpdb->insert_id;

		/**
		 * Fires after a workout is logged.
		 *
		 * @param int   $log_id       Log ID.
		 * @param int   $user_id      User ID.
		 * @param array $workout_data Workout data.
		 */
		do_action( 'bkx_fitness_workout_logged', $log_id, $user_id, $workout_data );

		return $log_id;
	}

	/**
	 * Get workout logs for user.
	 *
	 * @since 1.0.0
	 * @param int   $user_id User ID.
	 * @param array $filters Filters.
	 * @return array
	 */
	public function get_workout_logs( int $user_id, array $filters = array() ): array {
		global $wpdb;

		$where = array( 'user_id = %d' );
		$args  = array( $user_id );

		if ( ! empty( $filters['start_date'] ) ) {
			$where[] = 'workout_date >= %s';
			$args[]  = sanitize_text_field( $filters['start_date'] );
		}

		if ( ! empty( $filters['end_date'] ) ) {
			$where[] = 'workout_date <= %s';
			$args[]  = sanitize_text_field( $filters['end_date'] );
		}

		if ( ! empty( $filters['type'] ) ) {
			$where[] = 'workout_type = %s';
			$args[]  = sanitize_text_field( $filters['type'] );
		}

		$where_clause = implode( ' AND ', $where );
		$limit        = absint( $filters['limit'] ?? 50 );
		$offset       = absint( $filters['offset'] ?? 0 );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE {$where_clause}
				ORDER BY workout_date DESC, created_at DESC
				LIMIT %d OFFSET %d",
				array_merge( $args, array( $limit, $offset ) )
			),
			ARRAY_A
		);

		return array_map( function( $row ) {
			$row['exercises'] = json_decode( $row['exercises'] ?: '[]', true );
			return $row;
		}, $results ?: array() );
	}

	/**
	 * Get workout statistics for user.
	 *
	 * @since 1.0.0
	 * @param int    $user_id    User ID.
	 * @param string $period     Period (week, month, year).
	 * @return array
	 */
	public function get_stats( int $user_id, string $period = 'month' ): array {
		global $wpdb;

		switch ( $period ) {
			case 'week':
				$start_date = date( 'Y-m-d', strtotime( 'monday this week' ) );
				break;
			case 'year':
				$start_date = date( 'Y-01-01' );
				break;
			case 'month':
			default:
				$start_date = date( 'Y-m-01' );
				break;
		}

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_workouts,
					SUM(duration) as total_duration,
					SUM(calories) as total_calories,
					AVG(duration) as avg_duration,
					AVG(calories) as avg_calories
				FROM {$this->table_name}
				WHERE user_id = %d AND workout_date >= %s",
				$user_id,
				$start_date
			),
			ARRAY_A
		);

		// Get workout breakdown by type.
		$breakdown = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT workout_type, COUNT(*) as count, SUM(duration) as total_duration
				FROM {$this->table_name}
				WHERE user_id = %d AND workout_date >= %s
				GROUP BY workout_type
				ORDER BY count DESC",
				$user_id,
				$start_date
			),
			ARRAY_A
		);

		// Get streak info.
		$streak = $this->calculate_streak( $user_id );

		return array(
			'period'           => $period,
			'start_date'       => $start_date,
			'total_workouts'   => absint( $stats['total_workouts'] ?? 0 ),
			'total_duration'   => absint( $stats['total_duration'] ?? 0 ),
			'total_calories'   => absint( $stats['total_calories'] ?? 0 ),
			'avg_duration'     => round( floatval( $stats['avg_duration'] ?? 0 ), 1 ),
			'avg_calories'     => round( floatval( $stats['avg_calories'] ?? 0 ), 0 ),
			'breakdown'        => $breakdown ?: array(),
			'current_streak'   => $streak['current'],
			'longest_streak'   => $streak['longest'],
		);
	}

	/**
	 * Calculate workout streak.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function calculate_streak( int $user_id ): array {
		global $wpdb;

		// Get distinct workout dates.
		$dates = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT workout_date FROM {$this->table_name}
				WHERE user_id = %d
				ORDER BY workout_date DESC",
				$user_id
			)
		);

		if ( empty( $dates ) ) {
			return array(
				'current' => 0,
				'longest' => 0,
			);
		}

		$current_streak = 0;
		$longest_streak = 0;
		$temp_streak    = 0;
		$prev_date      = null;

		foreach ( $dates as $date ) {
			if ( null === $prev_date ) {
				// Check if most recent workout was today or yesterday.
				$diff = ( strtotime( 'today' ) - strtotime( $date ) ) / DAY_IN_SECONDS;
				if ( $diff <= 1 ) {
					$temp_streak = 1;
				}
			} else {
				$diff = ( strtotime( $prev_date ) - strtotime( $date ) ) / DAY_IN_SECONDS;
				if ( 1 === (int) $diff ) {
					$temp_streak++;
				} else {
					// Streak broken.
					if ( 0 === $current_streak ) {
						$current_streak = $temp_streak;
					}
					$longest_streak = max( $longest_streak, $temp_streak );
					$temp_streak    = 1;
				}
			}
			$prev_date = $date;
		}

		// Final check.
		if ( 0 === $current_streak ) {
			$current_streak = $temp_streak;
		}
		$longest_streak = max( $longest_streak, $temp_streak );

		return array(
			'current' => $current_streak,
			'longest' => $longest_streak,
		);
	}

	/**
	 * Get personal records for user.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_personal_records( int $user_id ): array {
		global $wpdb;

		$records = array();

		// Longest workout.
		$longest = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE user_id = %d
				ORDER BY duration DESC
				LIMIT 1",
				$user_id
			),
			ARRAY_A
		);

		if ( $longest ) {
			$records['longest_workout'] = array(
				'duration' => absint( $longest['duration'] ),
				'date'     => $longest['workout_date'],
				'type'     => $longest['workout_type'],
			);
		}

		// Most calories burned.
		$most_calories = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE user_id = %d
				ORDER BY calories DESC
				LIMIT 1",
				$user_id
			),
			ARRAY_A
		);

		if ( $most_calories ) {
			$records['most_calories'] = array(
				'calories' => absint( $most_calories['calories'] ),
				'date'     => $most_calories['workout_date'],
				'type'     => $most_calories['workout_type'],
			);
		}

		// Most workouts in a month.
		$best_month = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(workout_date, '%%Y-%%m') as month, COUNT(*) as count
				FROM {$this->table_name}
				WHERE user_id = %d
				GROUP BY month
				ORDER BY count DESC
				LIMIT 1",
				$user_id
			),
			ARRAY_A
		);

		if ( $best_month ) {
			$records['best_month'] = array(
				'month'    => $best_month['month'],
				'workouts' => absint( $best_month['count'] ),
			);
		}

		return $records;
	}

	/**
	 * Get workout types.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_workout_types(): array {
		return array(
			'cardio'     => __( 'Cardio', 'bkx-fitness-sports' ),
			'strength'   => __( 'Strength Training', 'bkx-fitness-sports' ),
			'hiit'       => __( 'HIIT', 'bkx-fitness-sports' ),
			'yoga'       => __( 'Yoga', 'bkx-fitness-sports' ),
			'pilates'    => __( 'Pilates', 'bkx-fitness-sports' ),
			'cycling'    => __( 'Cycling', 'bkx-fitness-sports' ),
			'swimming'   => __( 'Swimming', 'bkx-fitness-sports' ),
			'running'    => __( 'Running', 'bkx-fitness-sports' ),
			'boxing'     => __( 'Boxing', 'bkx-fitness-sports' ),
			'crossfit'   => __( 'CrossFit', 'bkx-fitness-sports' ),
			'dance'      => __( 'Dance', 'bkx-fitness-sports' ),
			'stretching' => __( 'Stretching', 'bkx-fitness-sports' ),
			'other'      => __( 'Other', 'bkx-fitness-sports' ),
		);
	}

	/**
	 * Delete workout log.
	 *
	 * @since 1.0.0
	 * @param int $log_id  Log ID.
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function delete_log( int $log_id, int $user_id ): bool {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table_name,
			array(
				'id'      => $log_id,
				'user_id' => $user_id,
			),
			array( '%d', '%d' )
		);

		return (bool) $result;
	}
}
