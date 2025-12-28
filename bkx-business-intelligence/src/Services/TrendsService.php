<?php
/**
 * Trends Service Class.
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
 * TrendsService Class.
 */
class TrendsService {

	/**
	 * Get revenue trend.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_revenue_trend( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pd.meta_value as booking_date,
					COALESCE(SUM(pt.meta_value), 0) as revenue,
					COUNT(p.ID) as booking_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY pd.meta_value
				ORDER BY pd.meta_value ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$labels = array();
		$data   = array();

		// Fill in gaps for days with no data.
		$current = strtotime( $start_date );
		$end     = strtotime( $end_date );
		$indexed = array();

		foreach ( $results as $row ) {
			$indexed[ $row['booking_date'] ] = (float) $row['revenue'];
		}

		while ( $current <= $end ) {
			$date     = gmdate( 'Y-m-d', $current );
			$labels[] = gmdate( 'M j', $current );
			$data[]   = $indexed[ $date ] ?? 0;
			$current  = strtotime( '+1 day', $current );
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'label'           => __( 'Revenue', 'bkx-business-intelligence' ),
					'data'            => $data,
					'borderColor'     => '#0073aa',
					'backgroundColor' => 'rgba(0, 115, 170, 0.1)',
					'fill'            => true,
				),
			),
		);
	}

	/**
	 * Get booking trend.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_booking_trend( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pd.meta_value as booking_date,
					COUNT(p.ID) as booking_count
				FROM {$wpdb->posts} p
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

		$labels = array();
		$data   = array();

		$current = strtotime( $start_date );
		$end     = strtotime( $end_date );
		$indexed = array();

		foreach ( $results as $row ) {
			$indexed[ $row['booking_date'] ] = (int) $row['booking_count'];
		}

		while ( $current <= $end ) {
			$date     = gmdate( 'Y-m-d', $current );
			$labels[] = gmdate( 'M j', $current );
			$data[]   = $indexed[ $date ] ?? 0;
			$current  = strtotime( '+1 day', $current );
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'label'           => __( 'Bookings', 'bkx-business-intelligence' ),
					'data'            => $data,
					'borderColor'     => '#46b450',
					'backgroundColor' => 'rgba(70, 180, 80, 0.1)',
					'fill'            => true,
				),
			),
		);
	}

	/**
	 * Get service breakdown.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_service_breakdown( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ps.meta_value as service_id,
					COALESCE(SUM(pt.meta_value), 0) as revenue,
					COUNT(p.ID) as booking_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'base_id'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY ps.meta_value
				ORDER BY revenue DESC
				LIMIT 8",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$labels = array();
		$data   = array();
		$colors = array(
			'#0073aa', '#00a0d2', '#46b450', '#ffb900',
			'#dc3232', '#9b59b6', '#1abc9c', '#e67e22',
		);

		foreach ( $results as $index => $row ) {
			$service = get_post( $row['service_id'] );
			if ( $service ) {
				$labels[] = $service->post_title;
				$data[]   = (float) $row['revenue'];
			}
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'data'            => $data,
					'backgroundColor' => array_slice( $colors, 0, count( $data ) ),
				),
			),
		);
	}

	/**
	 * Get staff performance.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_staff_performance( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ps.meta_value as seat_id,
					COALESCE(SUM(pt.meta_value), 0) as revenue,
					COUNT(p.ID) as booking_count,
					COUNT(CASE WHEN p.post_status = 'bkx-completed' THEN 1 END) as completed
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'seat_id'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY ps.meta_value
				ORDER BY revenue DESC
				LIMIT 10",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$labels       = array();
		$revenue_data = array();
		$booking_data = array();

		foreach ( $results as $row ) {
			$seat = get_post( $row['seat_id'] );
			if ( $seat ) {
				$labels[]       = $seat->post_title;
				$revenue_data[] = (float) $row['revenue'];
				$booking_data[] = (int) $row['booking_count'];
			}
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'label'           => __( 'Revenue', 'bkx-business-intelligence' ),
					'data'            => $revenue_data,
					'backgroundColor' => '#0073aa',
					'yAxisID'         => 'y',
				),
				array(
					'label'           => __( 'Bookings', 'bkx-business-intelligence' ),
					'data'            => $booking_data,
					'backgroundColor' => '#46b450',
					'yAxisID'         => 'y1',
				),
			),
		);
	}

	/**
	 * Get hourly distribution.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_hourly_distribution( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					HOUR(STR_TO_DATE(pm.meta_value, '%%H:%%i')) as hour,
					COUNT(p.ID) as booking_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'booking_time'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY HOUR(STR_TO_DATE(pm.meta_value, '%%H:%%i'))
				ORDER BY hour ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$labels = array();
		$data   = array_fill( 0, 24, 0 );

		foreach ( $results as $row ) {
			$hour = (int) $row['hour'];
			$data[ $hour ] = (int) $row['booking_count'];
		}

		for ( $i = 0; $i < 24; $i++ ) {
			$labels[] = sprintf( '%02d:00', $i );
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'label'           => __( 'Bookings', 'bkx-business-intelligence' ),
					'data'            => $data,
					'backgroundColor' => '#0073aa',
				),
			),
		);
	}

	/**
	 * Get day of week distribution.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_day_of_week_distribution( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DAYOFWEEK(STR_TO_DATE(pd.meta_value, '%%Y-%%m-%%d')) as day_num,
					COUNT(p.ID) as booking_count,
					COALESCE(SUM(pt.meta_value), 0) as revenue
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY DAYOFWEEK(STR_TO_DATE(pd.meta_value, '%%Y-%%m-%%d'))
				ORDER BY day_num ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$day_names = array(
			1 => __( 'Sunday', 'bkx-business-intelligence' ),
			2 => __( 'Monday', 'bkx-business-intelligence' ),
			3 => __( 'Tuesday', 'bkx-business-intelligence' ),
			4 => __( 'Wednesday', 'bkx-business-intelligence' ),
			5 => __( 'Thursday', 'bkx-business-intelligence' ),
			6 => __( 'Friday', 'bkx-business-intelligence' ),
			7 => __( 'Saturday', 'bkx-business-intelligence' ),
		);

		$labels       = array();
		$booking_data = array_fill( 0, 7, 0 );
		$revenue_data = array_fill( 0, 7, 0 );

		foreach ( $results as $row ) {
			$day_index = (int) $row['day_num'] - 1;
			$booking_data[ $day_index ] = (int) $row['booking_count'];
			$revenue_data[ $day_index ] = (float) $row['revenue'];
		}

		for ( $i = 1; $i <= 7; $i++ ) {
			$labels[] = $day_names[ $i ];
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'label'           => __( 'Bookings', 'bkx-business-intelligence' ),
					'data'            => $booking_data,
					'backgroundColor' => '#0073aa',
				),
			),
		);
	}

	/**
	 * Get month over month comparison.
	 *
	 * @param int $months Number of months.
	 * @return array
	 */
	public function get_month_over_month( $months = 12 ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE_FORMAT(STR_TO_DATE(pd.meta_value, '%%Y-%%m-%%d'), '%%Y-%%m') as month,
					COUNT(p.ID) as booking_count,
					COALESCE(SUM(pt.meta_value), 0) as revenue
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value >= DATE_SUB(CURDATE(), INTERVAL %d MONTH)
				GROUP BY DATE_FORMAT(STR_TO_DATE(pd.meta_value, '%%Y-%%m-%%d'), '%%Y-%%m')
				ORDER BY month ASC",
				$months
			),
			ARRAY_A
		);

		$labels       = array();
		$revenue_data = array();
		$booking_data = array();

		foreach ( $results as $row ) {
			$labels[]       = gmdate( 'M Y', strtotime( $row['month'] . '-01' ) );
			$revenue_data[] = (float) $row['revenue'];
			$booking_data[] = (int) $row['booking_count'];
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'label'           => __( 'Revenue', 'bkx-business-intelligence' ),
					'data'            => $revenue_data,
					'borderColor'     => '#0073aa',
					'backgroundColor' => 'transparent',
					'yAxisID'         => 'y',
				),
				array(
					'label'           => __( 'Bookings', 'bkx-business-intelligence' ),
					'data'            => $booking_data,
					'borderColor'     => '#46b450',
					'backgroundColor' => 'transparent',
					'yAxisID'         => 'y1',
				),
			),
		);
	}
}
