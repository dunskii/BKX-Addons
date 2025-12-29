<?php
/**
 * Booking Reports Service.
 *
 * @package BookingX\AdvancedReports
 * @since   1.0.0
 */

namespace BookingX\AdvancedReports\Services;

/**
 * BookingReports class.
 *
 * Generates booking-related reports and analytics.
 *
 * @since 1.0.0
 */
class BookingReports {

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
	 * Get booking overview for dashboard.
	 *
	 * @since 1.0.0
	 *
	 * @param string $period Period (today, week, month).
	 * @return array
	 */
	public function get_overview( $period = 'today' ) {
		$date_from = match ( $period ) {
			'week'  => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
			'month' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			default => current_time( 'Y-m-d' ),
		};

		$date_to = current_time( 'Y-m-d' );

		return $this->get_summary( $date_from, $date_to );
	}

	/**
	 * Get booking summary.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_summary( $date_from, $date_to ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(DISTINCT p.ID) as total_bookings,
					SUM(CASE WHEN p.post_status = 'bkx-completed' THEN 1 ELSE 0 END) as completed,
					SUM(CASE WHEN p.post_status = 'bkx-pending' THEN 1 ELSE 0 END) as pending,
					SUM(CASE WHEN p.post_status = 'bkx-ack' THEN 1 ELSE 0 END) as confirmed,
					SUM(CASE WHEN p.post_status = 'bkx-cancelled' THEN 1 ELSE 0 END) as cancelled,
					SUM(CASE WHEN p.post_status = 'bkx-missed' THEN 1 ELSE 0 END) as missed
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);

		$total = (int) ( $result->total_bookings ?? 0 );

		return array(
			'total'     => $total,
			'completed' => (int) ( $result->completed ?? 0 ),
			'pending'   => (int) ( $result->pending ?? 0 ),
			'confirmed' => (int) ( $result->confirmed ?? 0 ),
			'cancelled' => (int) ( $result->cancelled ?? 0 ),
			'missed'    => (int) ( $result->missed ?? 0 ),
			'completion_rate' => $total > 0 ? round( ( (int) $result->completed / $total ) * 100, 1 ) : 0,
			'cancellation_rate' => $total > 0 ? round( ( (int) $result->cancelled / $total ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Get full booking report.
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
			'summary'           => $this->get_summary( $date_from, $date_to ),
			'trend'             => $this->get_trend( $date_from, $date_to ),
			'status_breakdown'  => $this->get_status_breakdown( $date_from, $date_to ),
			'by_day_of_week'    => $this->get_by_day_of_week( $date_from, $date_to ),
			'by_time_of_day'    => $this->get_by_time_of_day( $date_from, $date_to ),
			'top_services'      => $this->get_top_services( $date_from, $date_to ),
			'peak_times'        => $this->get_peak_times( $date_from, $date_to ),
		);
	}

	/**
	 * Get booking trend.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_trend( $date_from, $date_to ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(pm_date.meta_value) as booking_date,
					COUNT(DISTINCT p.ID) as bookings
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY DATE(pm_date.meta_value)
				ORDER BY booking_date ASC",
				$date_from,
				$date_to
			)
		);

		$labels = array();
		$data   = array();

		foreach ( $results as $row ) {
			$labels[] = $row->booking_date;
			$data[]   = (int) $row->bookings;
		}

		return array(
			'labels' => $labels,
			'data'   => $data,
		);
	}

	/**
	 * Get status breakdown.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_status_breakdown( $date_from, $date_to ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.post_status as status,
					COUNT(DISTINCT p.ID) as count
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY p.post_status",
				$date_from,
				$date_to
			)
		);

		$status_labels = array(
			'bkx-pending'   => __( 'Pending', 'bkx-advanced-reports' ),
			'bkx-ack'       => __( 'Confirmed', 'bkx-advanced-reports' ),
			'bkx-completed' => __( 'Completed', 'bkx-advanced-reports' ),
			'bkx-cancelled' => __( 'Cancelled', 'bkx-advanced-reports' ),
			'bkx-missed'    => __( 'Missed', 'bkx-advanced-reports' ),
		);

		$labels = array();
		$data   = array();

		foreach ( $results as $row ) {
			$labels[] = $status_labels[ $row->status ] ?? $row->status;
			$data[]   = (int) $row->count;
		}

		return array(
			'labels' => $labels,
			'data'   => $data,
		);
	}

	/**
	 * Get bookings by day of week.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_by_day_of_week( $date_from, $date_to ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DAYOFWEEK(pm_date.meta_value) as day_num,
					COUNT(DISTINCT p.ID) as bookings
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY DAYOFWEEK(pm_date.meta_value)
				ORDER BY day_num",
				$date_from,
				$date_to
			)
		);

		$day_names = array(
			1 => __( 'Sunday', 'bkx-advanced-reports' ),
			2 => __( 'Monday', 'bkx-advanced-reports' ),
			3 => __( 'Tuesday', 'bkx-advanced-reports' ),
			4 => __( 'Wednesday', 'bkx-advanced-reports' ),
			5 => __( 'Thursday', 'bkx-advanced-reports' ),
			6 => __( 'Friday', 'bkx-advanced-reports' ),
			7 => __( 'Saturday', 'bkx-advanced-reports' ),
		);

		$data = array_fill( 1, 7, 0 );

		foreach ( $results as $row ) {
			$data[ $row->day_num ] = (int) $row->bookings;
		}

		return array(
			'labels' => array_values( $day_names ),
			'data'   => array_values( $data ),
		);
	}

	/**
	 * Get bookings by time of day.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_by_time_of_day( $date_from, $date_to ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					HOUR(pm_time.meta_value) as hour,
					COUNT(DISTINCT p.ID) as bookings
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'booking_time'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY HOUR(pm_time.meta_value)
				ORDER BY hour",
				$date_from,
				$date_to
			)
		);

		$hours = array();
		$data  = array();

		for ( $h = 0; $h < 24; $h++ ) {
			$hours[] = sprintf( '%02d:00', $h );
			$data[]  = 0;
		}

		foreach ( $results as $row ) {
			if ( isset( $row->hour ) ) {
				$data[ (int) $row->hour ] = (int) $row->bookings;
			}
		}

		return array(
			'labels' => $hours,
			'data'   => $data,
		);
	}

	/**
	 * Get top services.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @param int    $limit Limit.
	 * @return array
	 */
	public function get_top_services( $date_from, $date_to, $limit = 10 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pm_base.meta_value as service_id,
					COUNT(DISTINCT p.ID) as bookings,
					SUM(pm_total.meta_value) as revenue
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_base ON p.ID = pm_base.post_id AND pm_base.meta_key = 'base_id'
				LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'total_price'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY pm_base.meta_value
				ORDER BY bookings DESC
				LIMIT %d",
				$date_from,
				$date_to,
				$limit
			)
		);

		$data = array();

		foreach ( $results as $row ) {
			$data[] = array(
				'service_id'   => (int) $row->service_id,
				'service_name' => get_the_title( $row->service_id ) ?: __( 'Unknown', 'bkx-advanced-reports' ),
				'bookings'     => (int) $row->bookings,
				'revenue'      => (float) $row->revenue,
			);
		}

		return $data;
	}

	/**
	 * Get services report.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @param array  $filters Filters.
	 * @return array
	 */
	public function get_services_report( $date_from, $date_to, $filters = array() ) {
		return array(
			'services'    => $this->get_top_services( $date_from, $date_to, 100 ),
			'performance' => $this->get_service_performance( $date_from, $date_to ),
		);
	}

	/**
	 * Get service performance comparison.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	private function get_service_performance( $date_from, $date_to ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pm_base.meta_value as service_id,
					COUNT(DISTINCT p.ID) as total,
					SUM(CASE WHEN p.post_status = 'bkx-completed' THEN 1 ELSE 0 END) as completed,
					SUM(CASE WHEN p.post_status = 'bkx-cancelled' THEN 1 ELSE 0 END) as cancelled,
					AVG(pm_total.meta_value) as avg_revenue
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_base ON p.ID = pm_base.post_id AND pm_base.meta_key = 'base_id'
				LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'total_price'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY pm_base.meta_value",
				$date_from,
				$date_to
			)
		);

		$data = array();

		foreach ( $results as $row ) {
			$total = (int) $row->total;

			$data[] = array(
				'service_id'        => (int) $row->service_id,
				'service_name'      => get_the_title( $row->service_id ) ?: __( 'Unknown', 'bkx-advanced-reports' ),
				'total_bookings'    => $total,
				'completed'         => (int) $row->completed,
				'cancelled'         => (int) $row->cancelled,
				'completion_rate'   => $total > 0 ? round( ( (int) $row->completed / $total ) * 100, 1 ) : 0,
				'cancellation_rate' => $total > 0 ? round( ( (int) $row->cancelled / $total ) * 100, 1 ) : 0,
				'avg_revenue'       => round( (float) $row->avg_revenue, 2 ),
			);
		}

		return $data;
	}

	/**
	 * Get peak booking times.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_peak_times( $date_from, $date_to ) {
		$by_day  = $this->get_by_day_of_week( $date_from, $date_to );
		$by_hour = $this->get_by_time_of_day( $date_from, $date_to );

		// Find peak day.
		$peak_day_index = array_search( max( $by_day['data'] ), $by_day['data'], true );
		$peak_day       = $by_day['labels'][ $peak_day_index ] ?? '';

		// Find peak hour.
		$peak_hour_index = array_search( max( $by_hour['data'] ), $by_hour['data'], true );
		$peak_hour       = $by_hour['labels'][ $peak_hour_index ] ?? '';

		// Find slowest day.
		$slow_day_index = array_search( min( $by_day['data'] ), $by_day['data'], true );
		$slow_day       = $by_day['labels'][ $slow_day_index ] ?? '';

		return array(
			'peak_day'    => $peak_day,
			'peak_hour'   => $peak_hour,
			'slowest_day' => $slow_day,
			'heatmap'     => $this->generate_heatmap( $date_from, $date_to ),
		);
	}

	/**
	 * Generate booking heatmap data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	private function generate_heatmap( $date_from, $date_to ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DAYOFWEEK(pm_date.meta_value) as day_num,
					HOUR(pm_time.meta_value) as hour,
					COUNT(DISTINCT p.ID) as bookings
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'booking_time'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY DAYOFWEEK(pm_date.meta_value), HOUR(pm_time.meta_value)",
				$date_from,
				$date_to
			)
		);

		// Initialize 7x24 grid.
		$heatmap = array();
		for ( $day = 0; $day < 7; $day++ ) {
			$heatmap[ $day ] = array_fill( 0, 24, 0 );
		}

		foreach ( $results as $row ) {
			if ( isset( $row->day_num ) && isset( $row->hour ) ) {
				$day_index = ( (int) $row->day_num ) - 1; // Convert 1-7 to 0-6.
				$heatmap[ $day_index ][ (int) $row->hour ] = (int) $row->bookings;
			}
		}

		return $heatmap;
	}
}
