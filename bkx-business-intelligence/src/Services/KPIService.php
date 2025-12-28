<?php
/**
 * KPI Service Class.
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
 * KPIService Class.
 */
class KPIService {

	/**
	 * Get today's KPIs.
	 *
	 * @return array
	 */
	public function get_today_kpis() {
		$today = gmdate( 'Y-m-d' );
		return $this->get_kpis( $today, $today );
	}

	/**
	 * Get KPIs for a period.
	 *
	 * @param string $period Period (week, month, quarter, year).
	 * @return array
	 */
	public function get_period_kpis( $period ) {
		$end_date   = gmdate( 'Y-m-d' );
		$start_date = $end_date;

		switch ( $period ) {
			case 'week':
				$start_date = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
				break;
			case 'month':
				$start_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
				break;
			case 'quarter':
				$start_date = gmdate( 'Y-m-d', strtotime( '-90 days' ) );
				break;
			case 'year':
				$start_date = gmdate( 'Y-m-d', strtotime( '-365 days' ) );
				break;
		}

		return $this->get_kpis( $start_date, $end_date );
	}

	/**
	 * Get KPIs for date range.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_kpis( $start_date, $end_date ) {
		global $wpdb;

		$cache_key = 'bkx_bi_kpis_' . md5( $start_date . $end_date );
		$cached    = wp_cache_get( $cache_key, 'bkx_bi' );

		if ( false !== $cached ) {
			return $cached;
		}

		// Core KPIs.
		$core = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(p.ID) as total_bookings,
					COALESCE(SUM(pt.meta_value), 0) as total_revenue,
					COALESCE(AVG(pt.meta_value), 0) as avg_booking_value,
					COUNT(DISTINCT pe.meta_value) as unique_customers,
					COUNT(CASE WHEN p.post_status = 'bkx-completed' THEN 1 END) as completed_bookings,
					COUNT(CASE WHEN p.post_status = 'bkx-cancelled' THEN 1 END) as cancelled_bookings,
					COUNT(CASE WHEN p.post_status = 'bkx-missed' THEN 1 END) as missed_bookings
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				LEFT JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		// Calculate rates.
		$total = (int) $core->total_bookings;
		$conversion_rate = 0;
		$completion_rate = $total > 0 ? ( $core->completed_bookings / $total ) * 100 : 0;
		$cancellation_rate = $total > 0 ? ( $core->cancelled_bookings / $total ) * 100 : 0;
		$noshow_rate = $total > 0 ? ( $core->missed_bookings / $total ) * 100 : 0;

		// Revenue per customer.
		$revenue_per_customer = $core->unique_customers > 0 ? $core->total_revenue / $core->unique_customers : 0;

		// Calculate conversion rate from page views if available.
		$page_views = $this->get_booking_page_views( $start_date, $end_date );
		if ( $page_views > 0 ) {
			$conversion_rate = ( $total / $page_views ) * 100;
		}

		// Previous period comparison.
		$days_diff   = max( 1, ( strtotime( $end_date ) - strtotime( $start_date ) ) / 86400 );
		$prev_start  = gmdate( 'Y-m-d', strtotime( $start_date . ' -' . $days_diff . ' days' ) );
		$prev_end    = gmdate( 'Y-m-d', strtotime( $start_date . ' -1 day' ) );

		$prev = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(p.ID) as total_bookings,
					COALESCE(SUM(pt.meta_value), 0) as total_revenue
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s",
				$prev_start,
				$prev_end
			)
		);

		// Calculate changes.
		$revenue_change = $prev->total_revenue > 0
			? ( ( $core->total_revenue - $prev->total_revenue ) / $prev->total_revenue ) * 100
			: 0;

		$bookings_change = $prev->total_bookings > 0
			? ( ( $total - $prev->total_bookings ) / $prev->total_bookings ) * 100
			: 0;

		$kpis = array(
			// Core metrics.
			'revenue'               => (float) $core->total_revenue,
			'bookings'              => $total,
			'customers'             => (int) $core->unique_customers,
			'avg_booking_value'     => (float) $core->avg_booking_value,

			// Rates.
			'conversion_rate'       => round( $conversion_rate, 2 ),
			'completion_rate'       => round( $completion_rate, 2 ),
			'cancellation_rate'     => round( $cancellation_rate, 2 ),
			'noshow_rate'           => round( $noshow_rate, 2 ),

			// Per-customer metrics.
			'revenue_per_customer'  => round( $revenue_per_customer, 2 ),
			'bookings_per_customer' => $core->unique_customers > 0 ? round( $total / $core->unique_customers, 2 ) : 0,

			// Changes vs previous period.
			'revenue_change'        => round( $revenue_change, 1 ),
			'bookings_change'       => round( $bookings_change, 1 ),

			// Breakdown.
			'completed_bookings'    => (int) $core->completed_bookings,
			'cancelled_bookings'    => (int) $core->cancelled_bookings,
			'missed_bookings'       => (int) $core->missed_bookings,
		);

		wp_cache_set( $cache_key, $kpis, 'bkx_bi', 3600 );

		return $kpis;
	}

	/**
	 * Get booking page views (if tracking is available).
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return int
	 */
	private function get_booking_page_views( $start_date, $end_date ) {
		// This could integrate with Google Analytics or similar.
		// For now, return 0 to indicate unavailable.
		return apply_filters( 'bkx_bi_page_views', 0, $start_date, $end_date );
	}

	/**
	 * Get KPI summary for dashboard widget.
	 *
	 * @return array
	 */
	public function get_dashboard_summary() {
		$today = $this->get_today_kpis();
		$week  = $this->get_period_kpis( 'week' );
		$month = $this->get_period_kpis( 'month' );

		return array(
			'today' => array(
				'revenue'  => $today['revenue'],
				'bookings' => $today['bookings'],
			),
			'week' => array(
				'revenue'        => $week['revenue'],
				'bookings'       => $week['bookings'],
				'revenue_change' => $week['revenue_change'],
			),
			'month' => array(
				'revenue'        => $month['revenue'],
				'bookings'       => $month['bookings'],
				'customers'      => $month['customers'],
				'avg_value'      => $month['avg_booking_value'],
				'revenue_change' => $month['revenue_change'],
			),
		);
	}

	/**
	 * Get goal progress.
	 *
	 * @param string $goal_type Goal type.
	 * @param float  $target    Target value.
	 * @param string $period    Period.
	 * @return array
	 */
	public function get_goal_progress( $goal_type, $target, $period = 'month' ) {
		$kpis    = $this->get_period_kpis( $period );
		$current = 0;

		switch ( $goal_type ) {
			case 'revenue':
				$current = $kpis['revenue'];
				break;
			case 'bookings':
				$current = $kpis['bookings'];
				break;
			case 'customers':
				$current = $kpis['customers'];
				break;
		}

		$progress = $target > 0 ? min( 100, ( $current / $target ) * 100 ) : 0;

		return array(
			'goal_type' => $goal_type,
			'target'    => $target,
			'current'   => $current,
			'progress'  => round( $progress, 1 ),
			'remaining' => max( 0, $target - $current ),
		);
	}
}
