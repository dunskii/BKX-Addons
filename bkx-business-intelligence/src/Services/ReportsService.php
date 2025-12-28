<?php
/**
 * Reports Service Class.
 *
 * @package BookingX\BusinessIntelligence\Services
 * @since   1.0.0
 */

namespace BookingX\BusinessIntelligence\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ReportsService Class.
 */
class ReportsService {

	/**
	 * Report types.
	 *
	 * @var array
	 */
	private $report_types = array(
		'revenue_summary'    => 'Revenue Summary',
		'booking_analysis'   => 'Booking Analysis',
		'service_performance' => 'Service Performance',
		'staff_performance'  => 'Staff Performance',
		'customer_insights'  => 'Customer Insights',
		'trend_analysis'     => 'Trend Analysis',
		'forecast_report'    => 'Forecast Report',
		'executive_summary'  => 'Executive Summary',
	);

	/**
	 * Get available report types.
	 *
	 * @return array
	 */
	public function get_report_types() {
		return $this->report_types;
	}

	/**
	 * Generate report.
	 *
	 * @param string $type       Report type.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param array  $options    Additional options.
	 * @return array
	 */
	public function generate_report( $type, $start_date, $end_date, $options = array() ) {
		$method = 'generate_' . $type;

		if ( method_exists( $this, $method ) ) {
			return $this->$method( $start_date, $end_date, $options );
		}

		return array(
			'error' => __( 'Unknown report type.', 'bkx-business-intelligence' ),
		);
	}

	/**
	 * Generate revenue summary report.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param array  $options    Options.
	 * @return array
	 */
	private function generate_revenue_summary( $start_date, $end_date, $options = array() ) {
		$kpi_service    = new KPIService();
		$trends_service = new TrendsService();

		$kpis  = $kpi_service->get_kpis( $start_date, $end_date );
		$trend = $trends_service->get_revenue_trend( $start_date, $end_date );

		return array(
			'title'      => __( 'Revenue Summary Report', 'bkx-business-intelligence' ),
			'period'     => array(
				'start' => $start_date,
				'end'   => $end_date,
			),
			'generated'  => current_time( 'mysql' ),
			'summary'    => array(
				'total_revenue'        => $kpis['revenue'],
				'average_booking_value' => $kpis['avg_booking_value'],
				'revenue_per_customer' => $kpis['revenue_per_customer'],
				'revenue_change'       => $kpis['revenue_change'],
			),
			'breakdown'  => $this->get_revenue_breakdown( $start_date, $end_date ),
			'trend_data' => $trend,
		);
	}

	/**
	 * Generate booking analysis report.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param array  $options    Options.
	 * @return array
	 */
	private function generate_booking_analysis( $start_date, $end_date, $options = array() ) {
		$kpi_service    = new KPIService();
		$trends_service = new TrendsService();

		$kpis   = $kpi_service->get_kpis( $start_date, $end_date );
		$trend  = $trends_service->get_booking_trend( $start_date, $end_date );
		$hourly = $trends_service->get_hourly_distribution( $start_date, $end_date );
		$daily  = $trends_service->get_day_of_week_distribution( $start_date, $end_date );

		return array(
			'title'       => __( 'Booking Analysis Report', 'bkx-business-intelligence' ),
			'period'      => array(
				'start' => $start_date,
				'end'   => $end_date,
			),
			'generated'   => current_time( 'mysql' ),
			'summary'     => array(
				'total_bookings'     => $kpis['bookings'],
				'completed_bookings' => $kpis['completed_bookings'],
				'cancelled_bookings' => $kpis['cancelled_bookings'],
				'completion_rate'    => $kpis['completion_rate'],
				'cancellation_rate'  => $kpis['cancellation_rate'],
				'noshow_rate'        => $kpis['noshow_rate'],
				'bookings_change'    => $kpis['bookings_change'],
			),
			'patterns'    => array(
				'hourly'       => $hourly,
				'day_of_week'  => $daily,
				'peak_hour'    => $this->find_peak( $hourly['datasets'][0]['data'] ),
				'peak_day'     => $this->find_peak_day( $daily['datasets'][0]['data'], $daily['labels'] ),
			),
			'trend_data'  => $trend,
		);
	}

	/**
	 * Generate service performance report.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param array  $options    Options.
	 * @return array
	 */
	private function generate_service_performance( $start_date, $end_date, $options = array() ) {
		$trends_service = new TrendsService();
		$breakdown      = $trends_service->get_service_breakdown( $start_date, $end_date );

		$services = $this->get_detailed_service_metrics( $start_date, $end_date );

		return array(
			'title'     => __( 'Service Performance Report', 'bkx-business-intelligence' ),
			'period'    => array(
				'start' => $start_date,
				'end'   => $end_date,
			),
			'generated' => current_time( 'mysql' ),
			'services'  => $services,
			'chart_data' => $breakdown,
		);
	}

	/**
	 * Generate staff performance report.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param array  $options    Options.
	 * @return array
	 */
	private function generate_staff_performance( $start_date, $end_date, $options = array() ) {
		$trends_service = new TrendsService();
		$chart_data     = $trends_service->get_staff_performance( $start_date, $end_date );

		$staff = $this->get_detailed_staff_metrics( $start_date, $end_date );

		return array(
			'title'      => __( 'Staff Performance Report', 'bkx-business-intelligence' ),
			'period'     => array(
				'start' => $start_date,
				'end'   => $end_date,
			),
			'generated'  => current_time( 'mysql' ),
			'staff'      => $staff,
			'chart_data' => $chart_data,
		);
	}

	/**
	 * Generate customer insights report.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param array  $options    Options.
	 * @return array
	 */
	private function generate_customer_insights( $start_date, $end_date, $options = array() ) {
		$kpi_service = new KPIService();
		$kpis        = $kpi_service->get_kpis( $start_date, $end_date );

		$customer_data = $this->get_customer_metrics( $start_date, $end_date );

		return array(
			'title'      => __( 'Customer Insights Report', 'bkx-business-intelligence' ),
			'period'     => array(
				'start' => $start_date,
				'end'   => $end_date,
			),
			'generated'  => current_time( 'mysql' ),
			'summary'    => array(
				'unique_customers'      => $kpis['customers'],
				'revenue_per_customer'  => $kpis['revenue_per_customer'],
				'bookings_per_customer' => $kpis['bookings_per_customer'],
			),
			'segments'   => $customer_data['segments'],
			'top_customers' => $customer_data['top_customers'],
			'retention'  => $customer_data['retention'],
		);
	}

	/**
	 * Generate executive summary report.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param array  $options    Options.
	 * @return array
	 */
	private function generate_executive_summary( $start_date, $end_date, $options = array() ) {
		$kpi_service        = new KPIService();
		$trends_service     = new TrendsService();
		$forecasting_service = new ForecastingService();

		$kpis            = $kpi_service->get_kpis( $start_date, $end_date );
		$revenue_trend   = $trends_service->get_revenue_trend( $start_date, $end_date );
		$revenue_forecast = $forecasting_service->forecast_revenue( 30 );

		return array(
			'title'       => __( 'Executive Summary Report', 'bkx-business-intelligence' ),
			'period'      => array(
				'start' => $start_date,
				'end'   => $end_date,
			),
			'generated'   => current_time( 'mysql' ),
			'kpis'        => array(
				'revenue'     => $kpis['revenue'],
				'bookings'    => $kpis['bookings'],
				'customers'   => $kpis['customers'],
				'avg_value'   => $kpis['avg_booking_value'],
			),
			'changes'     => array(
				'revenue_change'  => $kpis['revenue_change'],
				'bookings_change' => $kpis['bookings_change'],
			),
			'rates'       => array(
				'completion_rate'   => $kpis['completion_rate'],
				'cancellation_rate' => $kpis['cancellation_rate'],
			),
			'trend'       => $revenue_trend,
			'forecast'    => array(
				'next_30_days' => $revenue_forecast['summary']['total_forecast'],
				'confidence'   => $revenue_forecast['summary']['confidence_level'],
			),
			'insights'    => $this->generate_insights( $kpis ),
		);
	}

	/**
	 * Get revenue breakdown by payment method.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_revenue_breakdown( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					COALESCE(pm.meta_value, 'unknown') as payment_method,
					COALESCE(SUM(pt.meta_value), 0) as revenue,
					COUNT(p.ID) as count
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'payment_method'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY pm.meta_value
				ORDER BY revenue DESC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		return $results;
	}

	/**
	 * Get detailed service metrics.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_detailed_service_metrics( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ps.meta_value as service_id,
					COUNT(p.ID) as total_bookings,
					COUNT(CASE WHEN p.post_status = 'bkx-completed' THEN 1 END) as completed,
					COUNT(CASE WHEN p.post_status = 'bkx-cancelled' THEN 1 END) as cancelled,
					COALESCE(SUM(pt.meta_value), 0) as revenue,
					COALESCE(AVG(pt.meta_value), 0) as avg_value
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'base_id'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY ps.meta_value
				ORDER BY revenue DESC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$services = array();
		foreach ( $results as $row ) {
			$service = get_post( $row['service_id'] );
			if ( $service ) {
				$total = (int) $row['total_bookings'];
				$services[] = array(
					'id'               => $row['service_id'],
					'name'             => $service->post_title,
					'total_bookings'   => $total,
					'completed'        => (int) $row['completed'],
					'cancelled'        => (int) $row['cancelled'],
					'completion_rate'  => $total > 0 ? round( ( $row['completed'] / $total ) * 100, 1 ) : 0,
					'revenue'          => (float) $row['revenue'],
					'avg_booking_value' => round( (float) $row['avg_value'], 2 ),
				);
			}
		}

		return $services;
	}

	/**
	 * Get detailed staff metrics.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_detailed_staff_metrics( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ps.meta_value as seat_id,
					COUNT(p.ID) as total_bookings,
					COUNT(CASE WHEN p.post_status = 'bkx-completed' THEN 1 END) as completed,
					COUNT(CASE WHEN p.post_status = 'bkx-cancelled' THEN 1 END) as cancelled,
					COUNT(CASE WHEN p.post_status = 'bkx-missed' THEN 1 END) as missed,
					COALESCE(SUM(pt.meta_value), 0) as revenue,
					COUNT(DISTINCT pe.meta_value) as unique_customers
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'seat_id'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				LEFT JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY ps.meta_value
				ORDER BY revenue DESC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$staff = array();
		foreach ( $results as $row ) {
			$seat = get_post( $row['seat_id'] );
			if ( $seat ) {
				$total = (int) $row['total_bookings'];
				$staff[] = array(
					'id'               => $row['seat_id'],
					'name'             => $seat->post_title,
					'total_bookings'   => $total,
					'completed'        => (int) $row['completed'],
					'cancelled'        => (int) $row['cancelled'],
					'missed'           => (int) $row['missed'],
					'completion_rate'  => $total > 0 ? round( ( $row['completed'] / $total ) * 100, 1 ) : 0,
					'revenue'          => (float) $row['revenue'],
					'unique_customers' => (int) $row['unique_customers'],
				);
			}
		}

		return $staff;
	}

	/**
	 * Get customer metrics.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_customer_metrics( $start_date, $end_date ) {
		global $wpdb;

		// Top customers by revenue.
		$top_customers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pe.meta_value as customer_email,
					COUNT(p.ID) as booking_count,
					COALESCE(SUM(pt.meta_value), 0) as total_spent
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY pe.meta_value
				ORDER BY total_spent DESC
				LIMIT 10",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Customer segments by booking frequency.
		$segments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					CASE
						WHEN booking_count = 1 THEN 'one_time'
						WHEN booking_count BETWEEN 2 AND 3 THEN 'occasional'
						WHEN booking_count BETWEEN 4 AND 6 THEN 'regular'
						ELSE 'loyal'
					END as segment,
					COUNT(*) as customer_count
				FROM (
					SELECT pe.meta_value as email, COUNT(p.ID) as booking_count
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
					INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
					WHERE p.post_type = 'bkx_booking'
					AND pd.meta_value BETWEEN %s AND %s
					GROUP BY pe.meta_value
				) as customer_bookings
				GROUP BY segment",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Retention (returning customers).
		$prev_end   = gmdate( 'Y-m-d', strtotime( $start_date . ' -1 day' ) );
		$days_diff  = max( 1, ( strtotime( $end_date ) - strtotime( $start_date ) ) / 86400 );
		$prev_start = gmdate( 'Y-m-d', strtotime( $start_date . ' -' . $days_diff . ' days' ) );

		$returning = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT current.meta_value)
				FROM {$wpdb->postmeta} current
				INNER JOIN {$wpdb->posts} p1 ON current.post_id = p1.ID
				INNER JOIN {$wpdb->postmeta} pd1 ON p1.ID = pd1.post_id AND pd1.meta_key = 'booking_date'
				WHERE current.meta_key = 'customer_email'
				AND p1.post_type = 'bkx_booking'
				AND pd1.meta_value BETWEEN %s AND %s
				AND current.meta_value IN (
					SELECT prev.meta_value
					FROM {$wpdb->postmeta} prev
					INNER JOIN {$wpdb->posts} p2 ON prev.post_id = p2.ID
					INNER JOIN {$wpdb->postmeta} pd2 ON p2.ID = pd2.post_id AND pd2.meta_key = 'booking_date'
					WHERE prev.meta_key = 'customer_email'
					AND p2.post_type = 'bkx_booking'
					AND pd2.meta_value BETWEEN %s AND %s
				)",
				$start_date,
				$end_date,
				$prev_start,
				$prev_end
			)
		);

		$total_current = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pe.meta_value)
				FROM {$wpdb->postmeta} pe
				INNER JOIN {$wpdb->posts} p ON pe.post_id = p.ID
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE pe.meta_key = 'customer_email'
				AND p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		$retention_rate = $total_current > 0 ? round( ( $returning / $total_current ) * 100, 1 ) : 0;

		return array(
			'top_customers' => $top_customers,
			'segments'      => $segments,
			'retention'     => array(
				'returning_customers' => (int) $returning,
				'total_customers'     => (int) $total_current,
				'retention_rate'      => $retention_rate,
			),
		);
	}

	/**
	 * Generate insights from KPIs.
	 *
	 * @param array $kpis KPI data.
	 * @return array
	 */
	private function generate_insights( $kpis ) {
		$insights = array();

		// Revenue insight.
		if ( $kpis['revenue_change'] > 10 ) {
			$insights[] = array(
				'type'    => 'positive',
				'message' => sprintf(
					/* translators: %s: percentage change */
					__( 'Revenue is up %s%% compared to the previous period. Great job!', 'bkx-business-intelligence' ),
					$kpis['revenue_change']
				),
			);
		} elseif ( $kpis['revenue_change'] < -10 ) {
			$insights[] = array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %s: percentage change */
					__( 'Revenue is down %s%% compared to the previous period. Consider running a promotion.', 'bkx-business-intelligence' ),
					abs( $kpis['revenue_change'] )
				),
			);
		}

		// Cancellation insight.
		if ( $kpis['cancellation_rate'] > 20 ) {
			$insights[] = array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %s: cancellation rate */
					__( 'Cancellation rate is %s%%. Consider implementing deposit requirements.', 'bkx-business-intelligence' ),
					$kpis['cancellation_rate']
				),
			);
		}

		// No-show insight.
		if ( $kpis['noshow_rate'] > 10 ) {
			$insights[] = array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %s: no-show rate */
					__( 'No-show rate is %s%%. Consider sending reminder notifications.', 'bkx-business-intelligence' ),
					$kpis['noshow_rate']
				),
			);
		}

		return $insights;
	}

	/**
	 * Find peak hour index.
	 *
	 * @param array $data Hourly data.
	 * @return int
	 */
	private function find_peak( $data ) {
		$max_index = 0;
		$max_value = 0;

		foreach ( $data as $index => $value ) {
			if ( $value > $max_value ) {
				$max_value = $value;
				$max_index = $index;
			}
		}

		return $max_index;
	}

	/**
	 * Find peak day.
	 *
	 * @param array $data   Daily data.
	 * @param array $labels Day labels.
	 * @return string
	 */
	private function find_peak_day( $data, $labels ) {
		$max_index = $this->find_peak( $data );
		return $labels[ $max_index ] ?? '';
	}

	/**
	 * Save report.
	 *
	 * @param array $report Report data.
	 * @param array $meta   Report metadata.
	 * @return int|false
	 */
	public function save_report( $report, $meta = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_bi_reports';

		$schedule = ! empty( $meta['schedule'] ) ? $meta['schedule'] : null;
		$next_run = null;

		if ( $schedule ) {
			$next_run = $this->calculate_next_run( $schedule );
		}

		return $wpdb->insert(
			$table,
			array(
				'report_name' => $meta['name'] ?? $report['title'],
				'report_type' => $meta['type'] ?? 'custom',
				'report_data' => wp_json_encode( $report ),
				'schedule'    => $schedule,
				'next_run'    => $next_run,
				'recipients'  => isset( $meta['recipients'] ) ? wp_json_encode( $meta['recipients'] ) : null,
				'created_by'  => get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Get saved reports.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public function get_saved_reports( $args = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_bi_reports';

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
			'type'   => null,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = '1=1';
		$params = array();

		if ( $args['type'] ) {
			$where .= ' AND report_type = %s';
			$params[] = $args['type'];
		}

		$params[] = $args['limit'];
		$params[] = $args['offset'];

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE {$where}
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				...$params
			)
		);

		return $results;
	}

	/**
	 * Get scheduled reports due for sending.
	 *
	 * @return array
	 */
	public function get_due_reports() {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_bi_reports';

		$now = current_time( 'mysql' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE schedule IS NOT NULL
				AND next_run <= %s",
				$now
			)
		);
	}

	/**
	 * Update report next run time.
	 *
	 * @param int $report_id Report ID.
	 * @return bool
	 */
	public function update_next_run( $report_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_bi_reports';

		$report = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $report_id )
		);

		if ( ! $report || ! $report->schedule ) {
			return false;
		}

		$next_run = $this->calculate_next_run( $report->schedule );

		return $wpdb->update(
			$table,
			array( 'next_run' => $next_run ),
			array( 'id' => $report_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Calculate next run time based on schedule.
	 *
	 * @param string $schedule Schedule type.
	 * @return string
	 */
	private function calculate_next_run( $schedule ) {
		$now = current_time( 'timestamp' );

		switch ( $schedule ) {
			case 'daily':
				$next = strtotime( 'tomorrow 9:00 AM', $now );
				break;
			case 'weekly':
				$next = strtotime( 'next Monday 9:00 AM', $now );
				break;
			case 'monthly':
				$next = strtotime( 'first day of next month 9:00 AM', $now );
				break;
			default:
				$next = strtotime( '+1 day', $now );
		}

		return gmdate( 'Y-m-d H:i:s', $next );
	}

	/**
	 * Delete report.
	 *
	 * @param int $report_id Report ID.
	 * @return bool
	 */
	public function delete_report( $report_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_bi_reports';

		return $wpdb->delete(
			$table,
			array( 'id' => $report_id ),
			array( '%d' )
		);
	}
}
