<?php
/**
 * Booking Analyzer Service.
 *
 * @package BookingX\AdvancedAnalytics\Services
 * @since   1.0.0
 */

namespace BookingX\AdvancedAnalytics\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BookingAnalyzer Class.
 */
class BookingAnalyzer {

	/**
	 * Get overview analysis.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_overview( $start_date, $end_date ) {
		global $wpdb;

		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(p.ID) as total_bookings,
					COUNT(CASE WHEN p.post_status = 'bkx-completed' THEN 1 END) as completed,
					COUNT(CASE WHEN p.post_status = 'bkx-cancelled' THEN 1 END) as cancelled,
					COUNT(CASE WHEN p.post_status = 'bkx-missed' THEN 1 END) as missed,
					COUNT(CASE WHEN p.post_status = 'bkx-pending' THEN 1 END) as pending,
					COALESCE(SUM(pt.meta_value), 0) as total_revenue,
					COALESCE(AVG(pt.meta_value), 0) as avg_value,
					COUNT(DISTINCT pe.meta_value) as unique_customers,
					COUNT(DISTINCT ps.meta_value) as services_booked,
					COUNT(DISTINCT pst.meta_value) as staff_utilized
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				LEFT JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
				LEFT JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'base_id'
				LEFT JOIN {$wpdb->postmeta} pst ON p.ID = pst.post_id AND pst.meta_key = 'seat_id'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		$total = (int) $stats->total_bookings;

		return array(
			'summary'            => array(
				'total_bookings'   => $total,
				'completed'        => (int) $stats->completed,
				'cancelled'        => (int) $stats->cancelled,
				'missed'           => (int) $stats->missed,
				'pending'          => (int) $stats->pending,
				'total_revenue'    => (float) $stats->total_revenue,
				'avg_booking_value' => round( (float) $stats->avg_value, 2 ),
				'unique_customers' => (int) $stats->unique_customers,
				'services_booked'  => (int) $stats->services_booked,
				'staff_utilized'   => (int) $stats->staff_utilized,
			),
			'rates'              => array(
				'completion_rate'   => $total > 0 ? round( ( $stats->completed / $total ) * 100, 1 ) : 0,
				'cancellation_rate' => $total > 0 ? round( ( $stats->cancelled / $total ) * 100, 1 ) : 0,
				'noshow_rate'       => $total > 0 ? round( ( $stats->missed / $total ) * 100, 1 ) : 0,
			),
			'daily_trend'        => $this->get_daily_trend( $start_date, $end_date ),
			'top_services'       => $this->get_top_services( $start_date, $end_date, 5 ),
			'top_staff'          => $this->get_top_staff( $start_date, $end_date, 5 ),
		);
	}

	/**
	 * Analyze conversion funnel.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function analyze_conversion_funnel( $start_date, $end_date ) {
		global $wpdb;

		// Get funnel stages.
		$created = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		$confirmed = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		$completed = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status = 'bkx-completed'
				AND pd.meta_value BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		$created   = (int) $created;
		$confirmed = (int) $confirmed;
		$completed = (int) $completed;

		return array(
			'funnel'      => array(
				array(
					'stage'      => __( 'Created', 'bkx-advanced-analytics' ),
					'count'      => $created,
					'percentage' => 100,
				),
				array(
					'stage'      => __( 'Confirmed', 'bkx-advanced-analytics' ),
					'count'      => $confirmed,
					'percentage' => $created > 0 ? round( ( $confirmed / $created ) * 100, 1 ) : 0,
				),
				array(
					'stage'      => __( 'Completed', 'bkx-advanced-analytics' ),
					'count'      => $completed,
					'percentage' => $created > 0 ? round( ( $completed / $created ) * 100, 1 ) : 0,
				),
			),
			'conversion_rates' => array(
				'creation_to_confirmation' => $created > 0 ? round( ( $confirmed / $created ) * 100, 1 ) : 0,
				'confirmation_to_completion' => $confirmed > 0 ? round( ( $completed / $confirmed ) * 100, 1 ) : 0,
				'overall_conversion' => $created > 0 ? round( ( $completed / $created ) * 100, 1 ) : 0,
			),
			'drop_off'        => array(
				'pre_confirmation' => $created - $confirmed,
				'post_confirmation' => $confirmed - $completed,
			),
		);
	}

	/**
	 * Analyze booking timing patterns.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function analyze_booking_timing( $start_date, $end_date ) {
		global $wpdb;

		// Lead time analysis (days between booking creation and appointment).
		$lead_times = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATEDIFF(pd.meta_value, DATE(p.post_date)) as lead_days,
					COUNT(p.ID) as count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY lead_days
				ORDER BY lead_days ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Categorize lead times.
		$lead_categories = array(
			'same_day'   => 0,
			'next_day'   => 0,
			'2_3_days'   => 0,
			'4_7_days'   => 0,
			'1_2_weeks'  => 0,
			'2_4_weeks'  => 0,
			'over_month' => 0,
		);

		foreach ( $lead_times as $row ) {
			$days  = (int) $row['lead_days'];
			$count = (int) $row['count'];

			if ( $days <= 0 ) {
				$lead_categories['same_day'] += $count;
			} elseif ( $days === 1 ) {
				$lead_categories['next_day'] += $count;
			} elseif ( $days <= 3 ) {
				$lead_categories['2_3_days'] += $count;
			} elseif ( $days <= 7 ) {
				$lead_categories['4_7_days'] += $count;
			} elseif ( $days <= 14 ) {
				$lead_categories['1_2_weeks'] += $count;
			} elseif ( $days <= 28 ) {
				$lead_categories['2_4_weeks'] += $count;
			} else {
				$lead_categories['over_month'] += $count;
			}
		}

		// Booking creation time of day.
		$creation_hours = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					HOUR(p.post_date) as hour,
					COUNT(p.ID) as count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY HOUR(p.post_date)
				ORDER BY hour ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$hourly = array_fill( 0, 24, 0 );
		foreach ( $creation_hours as $row ) {
			$hourly[ (int) $row['hour'] ] = (int) $row['count'];
		}

		// Find peak booking creation hour.
		$peak_hour = array_search( max( $hourly ), $hourly, true );

		// Day of week for booking creation.
		$creation_days = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DAYOFWEEK(p.post_date) as day_num,
					COUNT(p.ID) as count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY DAYOFWEEK(p.post_date)",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$day_names = array( 1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday' );
		$daily     = array_fill( 0, 7, 0 );
		foreach ( $creation_days as $row ) {
			$daily[ (int) $row['day_num'] - 1 ] = (int) $row['count'];
		}

		return array(
			'lead_time'          => array(
				'distribution' => $lead_categories,
				'chart_data'   => array(
					'labels' => array(
						__( 'Same Day', 'bkx-advanced-analytics' ),
						__( 'Next Day', 'bkx-advanced-analytics' ),
						__( '2-3 Days', 'bkx-advanced-analytics' ),
						__( '4-7 Days', 'bkx-advanced-analytics' ),
						__( '1-2 Weeks', 'bkx-advanced-analytics' ),
						__( '2-4 Weeks', 'bkx-advanced-analytics' ),
						__( 'Over 1 Month', 'bkx-advanced-analytics' ),
					),
					'data'   => array_values( $lead_categories ),
				),
			),
			'creation_time'      => array(
				'hourly'    => $hourly,
				'peak_hour' => $peak_hour,
				'daily'     => $daily,
				'chart_data' => array(
					'labels' => array_map( function ( $h ) { return sprintf( '%02d:00', $h ); }, range( 0, 23 ) ),
					'data'   => $hourly,
				),
			),
			'insights'           => $this->generate_timing_insights( $lead_categories, $peak_hour, $daily ),
		);
	}

	/**
	 * Analyze booking durations.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function analyze_booking_duration( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ps.meta_value as service_id,
					COALESCE(AVG(pd.meta_value), 0) as avg_duration,
					MIN(pd.meta_value) as min_duration,
					MAX(pd.meta_value) as max_duration,
					COUNT(p.ID) as booking_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'base_id'
				LEFT JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'total_duration'
				INNER JOIN {$wpdb->postmeta} pdt ON p.ID = pdt.post_id AND pdt.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pdt.meta_value BETWEEN %s AND %s
				GROUP BY ps.meta_value
				ORDER BY avg_duration DESC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$services = array();
		foreach ( $results as $row ) {
			$service = get_post( $row['service_id'] );
			if ( $service ) {
				$services[] = array(
					'id'            => $row['service_id'],
					'name'          => $service->post_title,
					'avg_duration'  => round( (float) $row['avg_duration'], 0 ),
					'min_duration'  => (int) $row['min_duration'],
					'max_duration'  => (int) $row['max_duration'],
					'booking_count' => (int) $row['booking_count'],
				);
			}
		}

		return array(
			'by_service' => $services,
			'chart_data' => array(
				'labels' => array_column( $services, 'name' ),
				'data'   => array_column( $services, 'avg_duration' ),
			),
		);
	}

	/**
	 * Analyze cancellations.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function analyze_cancellations( $start_date, $end_date ) {
		global $wpdb;

		// Overall stats.
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(p.ID) as total,
					COUNT(CASE WHEN p.post_status = 'bkx-cancelled' THEN 1 END) as cancelled,
					COALESCE(SUM(CASE WHEN p.post_status = 'bkx-cancelled' THEN pt.meta_value END), 0) as lost_revenue
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		// By service.
		$by_service = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ps.meta_value as service_id,
					COUNT(p.ID) as total,
					COUNT(CASE WHEN p.post_status = 'bkx-cancelled' THEN 1 END) as cancelled
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'base_id'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY ps.meta_value
				HAVING cancelled > 0
				ORDER BY cancelled DESC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$services = array();
		foreach ( $by_service as $row ) {
			$service = get_post( $row['service_id'] );
			if ( $service ) {
				$total = (int) $row['total'];
				$cancelled = (int) $row['cancelled'];
				$services[] = array(
					'name'       => $service->post_title,
					'total'      => $total,
					'cancelled'  => $cancelled,
					'rate'       => $total > 0 ? round( ( $cancelled / $total ) * 100, 1 ) : 0,
				);
			}
		}

		// By day of week.
		$by_day = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DAYOFWEEK(pd.meta_value) as day_num,
					COUNT(p.ID) as total,
					COUNT(CASE WHEN p.post_status = 'bkx-cancelled' THEN 1 END) as cancelled
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY DAYOFWEEK(pd.meta_value)",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$day_names = array( 1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday' );
		$daily_rates = array();
		foreach ( $by_day as $row ) {
			$total = (int) $row['total'];
			$cancelled = (int) $row['cancelled'];
			$daily_rates[ $day_names[ (int) $row['day_num'] ] ] = $total > 0 ? round( ( $cancelled / $total ) * 100, 1 ) : 0;
		}

		$total = (int) $stats->total;
		$cancelled = (int) $stats->cancelled;

		return array(
			'summary'     => array(
				'total_bookings'     => $total,
				'cancelled'          => $cancelled,
				'cancellation_rate'  => $total > 0 ? round( ( $cancelled / $total ) * 100, 1 ) : 0,
				'lost_revenue'       => (float) $stats->lost_revenue,
			),
			'by_service'  => $services,
			'by_day'      => $daily_rates,
			'chart_data'  => array(
				'services' => array(
					'labels' => array_column( $services, 'name' ),
					'data'   => array_column( $services, 'rate' ),
				),
				'daily'    => array(
					'labels' => array_keys( $daily_rates ),
					'data'   => array_values( $daily_rates ),
				),
			),
		);
	}

	/**
	 * Get daily booking trend.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_daily_trend( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pd.meta_value as date,
					COUNT(p.ID) as bookings,
					COALESCE(SUM(pt.meta_value), 0) as revenue
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY pd.meta_value
				ORDER BY pd.meta_value ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$labels   = array();
		$bookings = array();
		$revenue  = array();

		// Fill gaps.
		$current = strtotime( $start_date );
		$end     = strtotime( $end_date );
		$indexed = array();

		foreach ( $results as $row ) {
			$indexed[ $row['date'] ] = $row;
		}

		while ( $current <= $end ) {
			$date       = gmdate( 'Y-m-d', $current );
			$labels[]   = gmdate( 'M j', $current );
			$bookings[] = isset( $indexed[ $date ] ) ? (int) $indexed[ $date ]['bookings'] : 0;
			$revenue[]  = isset( $indexed[ $date ] ) ? (float) $indexed[ $date ]['revenue'] : 0;
			$current    = strtotime( '+1 day', $current );
		}

		return array(
			'labels'   => $labels,
			'bookings' => $bookings,
			'revenue'  => $revenue,
		);
	}

	/**
	 * Get top services.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param int    $limit      Limit.
	 * @return array
	 */
	private function get_top_services( $start_date, $end_date, $limit = 5 ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ps.meta_value as service_id,
					COUNT(p.ID) as bookings,
					COALESCE(SUM(pt.meta_value), 0) as revenue
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'base_id'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY ps.meta_value
				ORDER BY revenue DESC
				LIMIT %d",
				$start_date,
				$end_date,
				$limit
			),
			ARRAY_A
		);

		$services = array();
		foreach ( $results as $row ) {
			$service = get_post( $row['service_id'] );
			if ( $service ) {
				$services[] = array(
					'name'     => $service->post_title,
					'bookings' => (int) $row['bookings'],
					'revenue'  => (float) $row['revenue'],
				);
			}
		}

		return $services;
	}

	/**
	 * Get top staff.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param int    $limit      Limit.
	 * @return array
	 */
	private function get_top_staff( $start_date, $end_date, $limit = 5 ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ps.meta_value as staff_id,
					COUNT(p.ID) as bookings,
					COALESCE(SUM(pt.meta_value), 0) as revenue
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'seat_id'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY ps.meta_value
				ORDER BY revenue DESC
				LIMIT %d",
				$start_date,
				$end_date,
				$limit
			),
			ARRAY_A
		);

		$staff = array();
		foreach ( $results as $row ) {
			$seat = get_post( $row['staff_id'] );
			if ( $seat ) {
				$staff[] = array(
					'name'     => $seat->post_title,
					'bookings' => (int) $row['bookings'],
					'revenue'  => (float) $row['revenue'],
				);
			}
		}

		return $staff;
	}

	/**
	 * Generate timing insights.
	 *
	 * @param array $lead_categories Lead time categories.
	 * @param int   $peak_hour       Peak booking hour.
	 * @param array $daily           Daily distribution.
	 * @return array
	 */
	private function generate_timing_insights( $lead_categories, $peak_hour, $daily ) {
		$insights = array();

		// Lead time insight.
		$total_lead = array_sum( $lead_categories );
		if ( $total_lead > 0 ) {
			$same_day_pct = ( $lead_categories['same_day'] / $total_lead ) * 100;
			if ( $same_day_pct > 30 ) {
				$insights[] = array(
					'type'    => 'info',
					'message' => sprintf(
						/* translators: %s: percentage */
						__( '%s%% of bookings are same-day. Consider offering same-day booking incentives or express service options.', 'bkx-advanced-analytics' ),
						round( $same_day_pct, 0 )
					),
				);
			}
		}

		// Peak hour insight.
		if ( $peak_hour >= 9 && $peak_hour <= 17 ) {
			$insights[] = array(
				'type'    => 'positive',
				'message' => sprintf(
					/* translators: %s: hour */
					__( 'Peak booking creation time is %s:00, during business hours.', 'bkx-advanced-analytics' ),
					sprintf( '%02d', $peak_hour )
				),
			);
		} else {
			$insights[] = array(
				'type'    => 'info',
				'message' => sprintf(
					/* translators: %s: hour */
					__( 'Peak booking creation time is %s:00, outside typical business hours. Ensure 24/7 booking availability.', 'bkx-advanced-analytics' ),
					sprintf( '%02d', $peak_hour )
				),
			);
		}

		return $insights;
	}
}
