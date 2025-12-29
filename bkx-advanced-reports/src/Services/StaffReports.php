<?php
/**
 * Staff Reports Service.
 *
 * @package BookingX\AdvancedReports
 * @since   1.0.0
 */

namespace BookingX\AdvancedReports\Services;

/**
 * StaffReports class.
 *
 * Generates staff performance reports and analytics.
 *
 * @since 1.0.0
 */
class StaffReports {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get full staff report.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @param array  $filters Filters.
	 * @return array
	 */
	public function get_report( $date_from, $date_to, $filters = array() ) {
		return array(
			'summary'       => $this->get_summary( $date_from, $date_to ),
			'top_performers' => $this->get_top_performers( $date_from, $date_to ),
			'performance'   => $this->get_performance_metrics( $date_from, $date_to ),
			'utilization'   => $this->get_utilization( $date_from, $date_to ),
			'comparison'    => $this->get_comparison( $date_from, $date_to ),
		);
	}

	/**
	 * Get staff summary.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_summary( $date_from, $date_to ) {
		global $wpdb;

		// Get total active staff.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_staff = $wpdb->get_var(
			"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'bkx_seat' AND post_status = 'publish'"
		);

		// Get staff with bookings in period.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$active_staff = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm_seat.meta_value)
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_seat ON p.ID = pm_seat.post_id AND pm_seat.meta_key = 'seat_id'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);

		// Get average bookings per staff.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$avg_bookings = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(booking_count) FROM (
					SELECT COUNT(DISTINCT p.ID) as booking_count
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} pm_seat ON p.ID = pm_seat.post_id AND pm_seat.meta_key = 'seat_id'
					LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
					WHERE p.post_type = 'bkx_booking'
					AND pm_date.meta_value BETWEEN %s AND %s
					GROUP BY pm_seat.meta_value
				) as staff_counts",
				$date_from,
				$date_to
			)
		);

		return array(
			'total_staff'           => (int) $total_staff,
			'active_staff'          => (int) $active_staff,
			'average_bookings'      => round( (float) $avg_bookings, 1 ),
			'staff_utilization_rate' => $total_staff > 0 ? round( ( (int) $active_staff / (int) $total_staff ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Get top performers.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @param int    $limit Limit.
	 * @return array
	 */
	public function get_top_performers( $date_from, $date_to, $limit = 10 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pm_seat.meta_value as staff_id,
					COUNT(DISTINCT p.ID) as bookings,
					SUM(CASE WHEN p.post_status = 'bkx-completed' THEN 1 ELSE 0 END) as completed,
					SUM(pm_total.meta_value) as revenue
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_seat ON p.ID = pm_seat.post_id AND pm_seat.meta_key = 'seat_id'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'total_price'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY pm_seat.meta_value
				ORDER BY revenue DESC
				LIMIT %d",
				$date_from,
				$date_to,
				$limit
			)
		);

		$data = array();

		foreach ( $results as $row ) {
			$total = (int) $row->bookings;

			$data[] = array(
				'staff_id'        => (int) $row->staff_id,
				'staff_name'      => get_the_title( $row->staff_id ) ?: __( 'Unknown', 'bkx-advanced-reports' ),
				'bookings'        => $total,
				'completed'       => (int) $row->completed,
				'revenue'         => (float) $row->revenue,
				'completion_rate' => $total > 0 ? round( ( (int) $row->completed / $total ) * 100, 1 ) : 0,
			);
		}

		return $data;
	}

	/**
	 * Get performance metrics for all staff.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_performance_metrics( $date_from, $date_to ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pm_seat.meta_value as staff_id,
					COUNT(DISTINCT p.ID) as total_bookings,
					SUM(CASE WHEN p.post_status = 'bkx-completed' THEN 1 ELSE 0 END) as completed,
					SUM(CASE WHEN p.post_status = 'bkx-cancelled' THEN 1 ELSE 0 END) as cancelled,
					SUM(CASE WHEN p.post_status = 'bkx-missed' THEN 1 ELSE 0 END) as missed,
					SUM(pm_total.meta_value) as total_revenue,
					AVG(pm_total.meta_value) as avg_booking_value
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_seat ON p.ID = pm_seat.post_id AND pm_seat.meta_key = 'seat_id'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'total_price'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY pm_seat.meta_value",
				$date_from,
				$date_to
			)
		);

		$data = array();

		foreach ( $results as $row ) {
			$total = (int) $row->total_bookings;

			$data[] = array(
				'staff_id'          => (int) $row->staff_id,
				'staff_name'        => get_the_title( $row->staff_id ) ?: __( 'Unknown', 'bkx-advanced-reports' ),
				'total_bookings'    => $total,
				'completed'         => (int) $row->completed,
				'cancelled'         => (int) $row->cancelled,
				'missed'            => (int) $row->missed,
				'completion_rate'   => $total > 0 ? round( ( (int) $row->completed / $total ) * 100, 1 ) : 0,
				'cancellation_rate' => $total > 0 ? round( ( (int) $row->cancelled / $total ) * 100, 1 ) : 0,
				'no_show_rate'      => $total > 0 ? round( ( (int) $row->missed / $total ) * 100, 1 ) : 0,
				'total_revenue'     => (float) $row->total_revenue,
				'avg_booking_value' => round( (float) $row->avg_booking_value, 2 ),
			);
		}

		return $data;
	}

	/**
	 * Get staff utilization data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_utilization( $date_from, $date_to ) {
		global $wpdb;

		// Get all staff.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$staff_list = $wpdb->get_results(
			"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'bkx_seat' AND post_status = 'publish'"
		);

		// Calculate working days in period.
		$days = max( 1, ( strtotime( $date_to ) - strtotime( $date_from ) ) / DAY_IN_SECONDS );

		// Assume 8 hours per day, 6 days per week.
		$working_days        = $days * ( 6 / 7 );
		$total_available_hours = $working_days * 8;

		$data = array();

		foreach ( $staff_list as $staff ) {
			// Get booked hours for this staff.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$booked_hours = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(pm_duration.meta_value) / 60
					FROM {$wpdb->posts} p
					LEFT JOIN {$wpdb->postmeta} pm_seat ON p.ID = pm_seat.post_id AND pm_seat.meta_key = 'seat_id'
					LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
					LEFT JOIN {$wpdb->postmeta} pm_duration ON p.ID = pm_duration.post_id AND pm_duration.meta_key = 'total_duration'
					WHERE p.post_type = 'bkx_booking'
					AND pm_seat.meta_value = %d
					AND pm_date.meta_value BETWEEN %s AND %s
					AND p.post_status NOT IN ('bkx-cancelled')",
					$staff->ID,
					$date_from,
					$date_to
				)
			);

			$booked_hours     = (float) $booked_hours;
			$utilization_rate = $total_available_hours > 0 ? ( $booked_hours / $total_available_hours ) * 100 : 0;

			$data[] = array(
				'staff_id'         => $staff->ID,
				'staff_name'       => $staff->post_title,
				'booked_hours'     => round( $booked_hours, 1 ),
				'available_hours'  => round( $total_available_hours, 1 ),
				'utilization_rate' => round( min( 100, $utilization_rate ), 1 ),
			);
		}

		// Sort by utilization rate descending.
		usort(
			$data,
			function ( $a, $b ) {
				return $b['utilization_rate'] <=> $a['utilization_rate'];
			}
		);

		return $data;
	}

	/**
	 * Get staff comparison data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_comparison( $date_from, $date_to ) {
		$performance = $this->get_performance_metrics( $date_from, $date_to );

		if ( empty( $performance ) ) {
			return array();
		}

		// Calculate averages.
		$total_staff    = count( $performance );
		$avg_bookings   = array_sum( array_column( $performance, 'total_bookings' ) ) / $total_staff;
		$avg_revenue    = array_sum( array_column( $performance, 'total_revenue' ) ) / $total_staff;
		$avg_completion = array_sum( array_column( $performance, 'completion_rate' ) ) / $total_staff;

		// Calculate performance scores.
		foreach ( $performance as &$staff ) {
			$booking_score   = $avg_bookings > 0 ? ( $staff['total_bookings'] / $avg_bookings ) * 100 : 0;
			$revenue_score   = $avg_revenue > 0 ? ( $staff['total_revenue'] / $avg_revenue ) * 100 : 0;
			$completion_score = $avg_completion > 0 ? ( $staff['completion_rate'] / $avg_completion ) * 100 : 0;

			$staff['performance_score'] = round( ( $booking_score + $revenue_score + $completion_score ) / 3, 1 );
			$staff['vs_average']        = array(
				'bookings'   => round( $booking_score - 100, 1 ),
				'revenue'    => round( $revenue_score - 100, 1 ),
				'completion' => round( $completion_score - 100, 1 ),
			);
		}

		// Sort by performance score descending.
		usort(
			$performance,
			function ( $a, $b ) {
				return $b['performance_score'] <=> $a['performance_score'];
			}
		);

		return array(
			'staff'    => $performance,
			'averages' => array(
				'bookings'        => round( $avg_bookings, 1 ),
				'revenue'         => round( $avg_revenue, 2 ),
				'completion_rate' => round( $avg_completion, 1 ),
			),
		);
	}
}
