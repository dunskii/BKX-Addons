<?php
/**
 * Metrics Service Class.
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
 * MetricsService Class.
 */
class MetricsService {

	/**
	 * Get metrics for date range.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param string $metric     Specific metric or 'all'.
	 * @return array
	 */
	public function get_metrics( $start_date, $end_date, $metric = 'all' ) {
		global $wpdb;

		$cache_key = 'bkx_bi_metrics_' . md5( $start_date . $end_date . $metric );
		$cached    = wp_cache_get( $cache_key, 'bkx_bi' );

		if ( false !== $cached ) {
			return $cached;
		}

		$metrics = array();

		// Revenue metrics.
		if ( 'all' === $metric || 'revenue' === $metric ) {
			$metrics['revenue'] = $this->get_revenue_metrics( $start_date, $end_date );
		}

		// Booking metrics.
		if ( 'all' === $metric || 'bookings' === $metric ) {
			$metrics['bookings'] = $this->get_booking_metrics( $start_date, $end_date );
		}

		// Customer metrics.
		if ( 'all' === $metric || 'customers' === $metric ) {
			$metrics['customers'] = $this->get_customer_metrics( $start_date, $end_date );
		}

		// Service metrics.
		if ( 'all' === $metric || 'services' === $metric ) {
			$metrics['services'] = $this->get_service_metrics( $start_date, $end_date );
		}

		// Staff metrics.
		if ( 'all' === $metric || 'staff' === $metric ) {
			$metrics['staff'] = $this->get_staff_metrics( $start_date, $end_date );
		}

		wp_cache_set( $cache_key, $metrics, 'bkx_bi', 3600 );

		return $metrics;
	}

	/**
	 * Get revenue metrics.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_revenue_metrics( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(p.ID) as booking_count,
					COALESCE(SUM(pm.meta_value), 0) as total_revenue,
					COALESCE(AVG(pm.meta_value), 0) as avg_booking_value,
					COALESCE(MAX(pm.meta_value), 0) as max_booking_value,
					COALESCE(MIN(pm.meta_value), 0) as min_booking_value
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		// Get previous period for comparison.
		$days_diff   = ( strtotime( $end_date ) - strtotime( $start_date ) ) / 86400;
		$prev_start  = gmdate( 'Y-m-d', strtotime( $start_date . ' -' . $days_diff . ' days' ) );
		$prev_end    = gmdate( 'Y-m-d', strtotime( $start_date . ' -1 day' ) );

		$prev_results = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COALESCE(SUM(pm.meta_value), 0) as total_revenue,
					COUNT(p.ID) as booking_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value BETWEEN %s AND %s",
				$prev_start,
				$prev_end
			)
		);

		$revenue_change = 0;
		if ( $prev_results->total_revenue > 0 ) {
			$revenue_change = ( ( $results->total_revenue - $prev_results->total_revenue ) / $prev_results->total_revenue ) * 100;
		}

		return array(
			'total'           => (float) $results->total_revenue,
			'average'         => (float) $results->avg_booking_value,
			'max'             => (float) $results->max_booking_value,
			'min'             => (float) $results->min_booking_value,
			'change_percent'  => round( $revenue_change, 1 ),
			'previous_total'  => (float) $prev_results->total_revenue,
		);
	}

	/**
	 * Get booking metrics.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_booking_metrics( $start_date, $end_date ) {
		global $wpdb;

		// Total bookings by status.
		$by_status = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.post_status,
					COUNT(p.ID) as count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY p.post_status",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$status_counts = array();
		$total         = 0;
		foreach ( $by_status as $row ) {
			$status_counts[ $row['post_status'] ] = (int) $row['count'];
			$total += (int) $row['count'];
		}

		// Completion rate.
		$completed = ( $status_counts['bkx-completed'] ?? 0 );
		$completion_rate = $total > 0 ? ( $completed / $total ) * 100 : 0;

		// Cancellation rate.
		$cancelled = ( $status_counts['bkx-cancelled'] ?? 0 );
		$cancellation_rate = $total > 0 ? ( $cancelled / $total ) * 100 : 0;

		// No-show rate.
		$missed = ( $status_counts['bkx-missed'] ?? 0 );
		$noshow_rate = $total > 0 ? ( $missed / $total ) * 100 : 0;

		return array(
			'total'             => $total,
			'by_status'         => $status_counts,
			'completed'         => $completed,
			'cancelled'         => $cancelled,
			'missed'            => $missed,
			'completion_rate'   => round( $completion_rate, 1 ),
			'cancellation_rate' => round( $cancellation_rate, 1 ),
			'noshow_rate'       => round( $noshow_rate, 1 ),
		);
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

		// Unique customers.
		$unique_customers = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm.meta_value)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'customer_email'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		// New vs returning customers.
		$customer_breakdown = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(DISTINCT CASE WHEN booking_count = 1 THEN email END) as new_customers,
					COUNT(DISTINCT CASE WHEN booking_count > 1 THEN email END) as returning_customers
				FROM (
					SELECT
						pm.meta_value as email,
						COUNT(p.ID) as booking_count
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'customer_email'
					INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
					WHERE p.post_type = 'bkx_booking'
					AND pd.meta_value BETWEEN %s AND %s
					GROUP BY pm.meta_value
				) as customer_bookings",
				$start_date,
				$end_date
			)
		);

		// Average bookings per customer.
		$total_bookings = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		$avg_bookings_per_customer = $unique_customers > 0 ? $total_bookings / $unique_customers : 0;

		return array(
			'unique_customers'          => (int) $unique_customers,
			'new_customers'             => (int) $customer_breakdown->new_customers,
			'returning_customers'       => (int) $customer_breakdown->returning_customers,
			'retention_rate'            => $unique_customers > 0 ? round( ( $customer_breakdown->returning_customers / $unique_customers ) * 100, 1 ) : 0,
			'avg_bookings_per_customer' => round( $avg_bookings_per_customer, 2 ),
		);
	}

	/**
	 * Get service metrics.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_service_metrics( $start_date, $end_date ) {
		global $wpdb;

		$services = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ps.meta_value as service_id,
					COUNT(p.ID) as booking_count,
					COALESCE(SUM(pt.meta_value), 0) as revenue
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'base_id'
				INNER JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY ps.meta_value
				ORDER BY revenue DESC
				LIMIT 10",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$service_data = array();
		foreach ( $services as $row ) {
			$service = get_post( $row['service_id'] );
			if ( $service ) {
				$service_data[] = array(
					'id'            => (int) $row['service_id'],
					'name'          => $service->post_title,
					'booking_count' => (int) $row['booking_count'],
					'revenue'       => (float) $row['revenue'],
				);
			}
		}

		return array(
			'top_services'    => $service_data,
			'total_services'  => count( $services ),
		);
	}

	/**
	 * Get staff metrics.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_staff_metrics( $start_date, $end_date ) {
		global $wpdb;

		$staff = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ps.meta_value as seat_id,
					COUNT(p.ID) as booking_count,
					COALESCE(SUM(pt.meta_value), 0) as revenue,
					COUNT(CASE WHEN p.post_status = 'bkx-completed' THEN 1 END) as completed
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'seat_id'
				INNER JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
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

		$staff_data = array();
		foreach ( $staff as $row ) {
			$seat = get_post( $row['seat_id'] );
			if ( $seat ) {
				$staff_data[] = array(
					'id'              => (int) $row['seat_id'],
					'name'            => $seat->post_title,
					'booking_count'   => (int) $row['booking_count'],
					'revenue'         => (float) $row['revenue'],
					'completion_rate' => $row['booking_count'] > 0 ? round( ( $row['completed'] / $row['booking_count'] ) * 100, 1 ) : 0,
				);
			}
		}

		return array(
			'top_staff'    => $staff_data,
			'total_staff'  => count( $staff ),
		);
	}

	/**
	 * Aggregate daily metrics.
	 */
	public function aggregate_daily_metrics() {
		global $wpdb;

		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		// Check if already aggregated.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}bkx_bi_metrics WHERE metric_date = %s",
				$yesterday
			)
		);

		if ( $exists > 0 ) {
			return;
		}

		// Aggregate revenue.
		$revenue = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(pm.meta_value), 0)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value = %s",
				$yesterday
			)
		);

		$wpdb->insert(
			$wpdb->prefix . 'bkx_bi_metrics',
			array(
				'metric_date'  => $yesterday,
				'metric_type'  => 'daily',
				'metric_key'   => 'revenue',
				'metric_value' => $revenue,
			),
			array( '%s', '%s', '%s', '%f' )
		);

		// Aggregate bookings count.
		$bookings = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(p.ID)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value = %s",
				$yesterday
			)
		);

		$wpdb->insert(
			$wpdb->prefix . 'bkx_bi_metrics',
			array(
				'metric_date'  => $yesterday,
				'metric_type'  => 'daily',
				'metric_key'   => 'bookings',
				'metric_value' => $bookings,
			),
			array( '%s', '%s', '%s', '%f' )
		);

		// Aggregate unique customers.
		$customers = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm.meta_value)
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'customer_email'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value = %s",
				$yesterday
			)
		);

		$wpdb->insert(
			$wpdb->prefix . 'bkx_bi_metrics',
			array(
				'metric_date'  => $yesterday,
				'metric_type'  => 'daily',
				'metric_key'   => 'customers',
				'metric_value' => $customers,
			),
			array( '%s', '%s', '%s', '%f' )
		);
	}
}
