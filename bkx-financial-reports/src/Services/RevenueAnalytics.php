<?php
/**
 * Revenue Analytics Service.
 *
 * @package BookingX\FinancialReports\Services
 * @since   1.0.0
 */

namespace BookingX\FinancialReports\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RevenueAnalytics Class.
 */
class RevenueAnalytics {

	/**
	 * Get revenue data for period.
	 *
	 * @param string $period     Period type (day, week, month, quarter, year, custom).
	 * @param string $start_date Start date (for custom).
	 * @param string $end_date   End date (for custom).
	 * @return array Revenue data.
	 */
	public function get_revenue_data( $period = 'month', $start_date = '', $end_date = '' ) {
		$dates = $this->get_date_range( $period, $start_date, $end_date );

		global $wpdb;

		// Get bookings data.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(pm_date.meta_value) as booking_date,
					COUNT(p.ID) as total_bookings,
					SUM(CASE WHEN p.post_status IN ('bkx-completed', 'bkx-ack') THEN 1 ELSE 0 END) as completed,
					SUM(CASE WHEN p.post_status = 'bkx-cancelled' THEN 1 ELSE 0 END) as cancelled,
					SUM(CAST(pm_amount.meta_value AS DECIMAL(15,2))) as total_revenue
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'total_amount'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY DATE(pm_date.meta_value)
				ORDER BY booking_date ASC",
				$dates['start'],
				$dates['end']
			),
			ARRAY_A
		);

		// Fill in missing dates.
		$data_by_date = array();
		foreach ( $results as $row ) {
			$data_by_date[ $row['booking_date'] ] = $row;
		}

		$filled_data = array();
		$current     = new \DateTime( $dates['start'] );
		$end         = new \DateTime( $dates['end'] );

		while ( $current <= $end ) {
			$date_key = $current->format( 'Y-m-d' );

			$filled_data[] = array(
				'date'           => $date_key,
				'label'          => $current->format( 'M j' ),
				'total_bookings' => isset( $data_by_date[ $date_key ] ) ? absint( $data_by_date[ $date_key ]['total_bookings'] ) : 0,
				'completed'      => isset( $data_by_date[ $date_key ] ) ? absint( $data_by_date[ $date_key ]['completed'] ) : 0,
				'cancelled'      => isset( $data_by_date[ $date_key ] ) ? absint( $data_by_date[ $date_key ]['cancelled'] ) : 0,
				'revenue'        => isset( $data_by_date[ $date_key ] ) ? floatval( $data_by_date[ $date_key ]['total_revenue'] ) : 0,
			);

			$current->modify( '+1 day' );
		}

		// Calculate totals.
		$totals = array(
			'total_revenue'   => array_sum( array_column( $filled_data, 'revenue' ) ),
			'total_bookings'  => array_sum( array_column( $filled_data, 'total_bookings' ) ),
			'completed'       => array_sum( array_column( $filled_data, 'completed' ) ),
			'cancelled'       => array_sum( array_column( $filled_data, 'cancelled' ) ),
			'average_booking' => 0,
		);

		if ( $totals['completed'] > 0 ) {
			$totals['average_booking'] = $totals['total_revenue'] / $totals['completed'];
		}

		// Get comparison with previous period.
		$prev_dates = $this->get_previous_period_dates( $dates );
		$prev_total = $this->get_period_total( $prev_dates['start'], $prev_dates['end'] );

		$totals['previous_revenue'] = $prev_total;
		$totals['growth_percent']   = $prev_total > 0
			? round( ( ( $totals['total_revenue'] - $prev_total ) / $prev_total ) * 100, 1 )
			: 0;

		return array(
			'period'    => $period,
			'dates'     => $dates,
			'daily'     => $filled_data,
			'totals'    => $totals,
			'breakdown' => $this->get_revenue_breakdown( $dates['start'], $dates['end'] ),
		);
	}

	/**
	 * Get revenue breakdown by service and extras.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Breakdown data.
	 */
	private function get_revenue_breakdown( $start_date, $end_date ) {
		global $wpdb;

		// Get by service.
		$by_service = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pm_base.meta_value as service_id,
					bases.post_title as service_name,
					COUNT(*) as bookings,
					SUM(CAST(pm_amount.meta_value AS DECIMAL(15,2))) as revenue
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				INNER JOIN {$wpdb->postmeta} pm_base ON p.ID = pm_base.post_id AND pm_base.meta_key = 'base_id'
				LEFT JOIN {$wpdb->posts} bases ON bases.ID = pm_base.meta_value
				LEFT JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'total_amount'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-completed', 'bkx-ack')
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY pm_base.meta_value
				ORDER BY revenue DESC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Get by payment method.
		$by_payment = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					COALESCE(pm_method.meta_value, 'Unknown') as payment_method,
					COUNT(*) as bookings,
					SUM(CAST(pm_amount.meta_value AS DECIMAL(15,2))) as revenue
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_method ON p.ID = pm_method.post_id AND pm_method.meta_key = 'payment_method'
				LEFT JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'total_amount'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-completed', 'bkx-ack')
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY pm_method.meta_value
				ORDER BY revenue DESC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		return array(
			'by_service' => $by_service,
			'by_payment' => $by_payment,
		);
	}

	/**
	 * Get summary stats.
	 *
	 * @return array Summary data.
	 */
	public function get_summary() {
		$today     = gmdate( 'Y-m-d' );
		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		return array(
			'today'     => array(
				'revenue'  => $this->get_period_total( $today, $today ),
				'bookings' => $this->get_booking_count( $today, $today ),
			),
			'yesterday' => array(
				'revenue'  => $this->get_period_total( $yesterday, $yesterday ),
				'bookings' => $this->get_booking_count( $yesterday, $yesterday ),
			),
			'this_week' => array(
				'revenue'  => $this->get_period_total(
					gmdate( 'Y-m-d', strtotime( 'monday this week' ) ),
					$today
				),
				'bookings' => $this->get_booking_count(
					gmdate( 'Y-m-d', strtotime( 'monday this week' ) ),
					$today
				),
			),
			'this_month' => array(
				'revenue'  => $this->get_mtd_revenue(),
				'bookings' => $this->get_booking_count(
					gmdate( 'Y-m-01' ),
					$today
				),
			),
		);
	}

	/**
	 * Get chart data.
	 *
	 * @param string $period Period type.
	 * @return array Chart data.
	 */
	public function get_chart_data( $period = 'week' ) {
		$dates = $this->get_date_range( $period );
		$data  = $this->get_revenue_data( $period );

		return array(
			'labels' => array_column( $data['daily'], 'label' ),
			'values' => array_column( $data['daily'], 'revenue' ),
		);
	}

	/**
	 * Get top services.
	 *
	 * @param int $limit Number of services.
	 * @return array Top services.
	 */
	public function get_top_services( $limit = 5 ) {
		global $wpdb;

		$start_date = gmdate( 'Y-m-01' );
		$end_date   = gmdate( 'Y-m-d' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					bases.post_title as name,
					COUNT(*) as bookings,
					SUM(CAST(pm_amount.meta_value AS DECIMAL(15,2))) as revenue
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				INNER JOIN {$wpdb->postmeta} pm_base ON p.ID = pm_base.post_id AND pm_base.meta_key = 'base_id'
				LEFT JOIN {$wpdb->posts} bases ON bases.ID = pm_base.meta_value
				LEFT JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'total_amount'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-completed', 'bkx-ack')
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY pm_base.meta_value
				ORDER BY revenue DESC
				LIMIT %d",
				$start_date,
				$end_date,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Get recent bookings.
	 *
	 * @param int $limit Number of bookings.
	 * @return array Recent bookings.
	 */
	public function get_recent_bookings( $limit = 5 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.ID as booking_id,
					p.post_status as status,
					pm_date.meta_value as booking_date,
					pm_name.meta_value as customer_name,
					pm_amount.meta_value as amount,
					bases.post_title as service
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_name ON p.ID = pm_name.post_id AND pm_name.meta_key = 'customer_name'
				LEFT JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'total_amount'
				LEFT JOIN {$wpdb->postmeta} pm_base ON p.ID = pm_base.post_id AND pm_base.meta_key = 'base_id'
				LEFT JOIN {$wpdb->posts} bases ON bases.ID = pm_base.meta_value
				WHERE p.post_type = 'bkx_booking'
				ORDER BY p.post_date DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Get MTD revenue.
	 *
	 * @return float MTD revenue.
	 */
	public function get_mtd_revenue() {
		return $this->get_period_total( gmdate( 'Y-m-01' ), gmdate( 'Y-m-d' ) );
	}

	/**
	 * Get YTD revenue.
	 *
	 * @return float YTD revenue.
	 */
	public function get_ytd_revenue() {
		return $this->get_period_total( gmdate( 'Y-01-01' ), gmdate( 'Y-m-d' ) );
	}

	/**
	 * Get average booking value.
	 *
	 * @return float Average value.
	 */
	public function get_average_booking_value() {
		global $wpdb;

		return floatval(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT AVG(CAST(pm.meta_value AS DECIMAL(15,2)))
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'total_amount'
					WHERE p.post_type = 'bkx_booking'
					AND p.post_status IN ('bkx-completed', 'bkx-ack')
					AND p.post_date >= %s",
					gmdate( 'Y-m-01' )
				)
			)
		);
	}

	/**
	 * Get growth rate.
	 *
	 * @return float Growth rate percentage.
	 */
	public function get_growth_rate() {
		$this_month = $this->get_mtd_revenue();
		$last_month = $this->get_period_total(
			gmdate( 'Y-m-01', strtotime( '-1 month' ) ),
			gmdate( 'Y-m-t', strtotime( '-1 month' ) )
		);

		if ( $last_month > 0 ) {
			return round( ( ( $this_month - $last_month ) / $last_month ) * 100, 1 );
		}

		return 0;
	}

	/**
	 * Get period total.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return float Total revenue.
	 */
	public function get_period_total( $start_date, $end_date ) {
		global $wpdb;

		return floatval(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(CAST(pm_amount.meta_value AS DECIMAL(15,2)))
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
					INNER JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'total_amount'
					WHERE p.post_type = 'bkx_booking'
					AND p.post_status IN ('bkx-completed', 'bkx-ack')
					AND pm_date.meta_value BETWEEN %s AND %s",
					$start_date,
					$end_date
				)
			)
		);
	}

	/**
	 * Get booking count.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return int Booking count.
	 */
	private function get_booking_count( $start_date, $end_date ) {
		global $wpdb;

		return absint(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*)
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
					WHERE p.post_type = 'bkx_booking'
					AND pm_date.meta_value BETWEEN %s AND %s",
					$start_date,
					$end_date
				)
			)
		);
	}

	/**
	 * Get date range for period.
	 *
	 * @param string $period     Period type.
	 * @param string $start_date Custom start.
	 * @param string $end_date   Custom end.
	 * @return array Start and end dates.
	 */
	private function get_date_range( $period, $start_date = '', $end_date = '' ) {
		$today = gmdate( 'Y-m-d' );

		switch ( $period ) {
			case 'day':
				return array(
					'start' => $today,
					'end'   => $today,
				);

			case 'week':
				return array(
					'start' => gmdate( 'Y-m-d', strtotime( '-6 days' ) ),
					'end'   => $today,
				);

			case 'month':
				return array(
					'start' => gmdate( 'Y-m-01' ),
					'end'   => $today,
				);

			case 'quarter':
				$quarter_start = gmdate( 'Y-m-d', strtotime( 'first day of ' . ceil( gmdate( 'n' ) / 3 ) * 3 - 2 . ' months ago' ) );
				return array(
					'start' => $quarter_start,
					'end'   => $today,
				);

			case 'year':
				return array(
					'start' => gmdate( 'Y-01-01' ),
					'end'   => $today,
				);

			case 'custom':
				return array(
					'start' => $start_date ?: gmdate( 'Y-m-01' ),
					'end'   => $end_date ?: $today,
				);

			default:
				return array(
					'start' => gmdate( 'Y-m-01' ),
					'end'   => $today,
				);
		}
	}

	/**
	 * Get previous period dates.
	 *
	 * @param array $current_dates Current period dates.
	 * @return array Previous period dates.
	 */
	private function get_previous_period_dates( $current_dates ) {
		$start = new \DateTime( $current_dates['start'] );
		$end   = new \DateTime( $current_dates['end'] );
		$diff  = $start->diff( $end );

		$prev_end   = clone $start;
		$prev_end->modify( '-1 day' );
		$prev_start = clone $prev_end;
		$prev_start->modify( '-' . $diff->days . ' days' );

		return array(
			'start' => $prev_start->format( 'Y-m-d' ),
			'end'   => $prev_end->format( 'Y-m-d' ),
		);
	}

	/**
	 * Create revenue snapshot.
	 *
	 * @param string $date Date for snapshot.
	 * @return bool Success.
	 */
	public function create_snapshot( $date ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_financial_snapshots';

		$data = array(
			'snapshot_date'       => $date,
			'snapshot_type'       => 'daily',
			'total_revenue'       => $this->get_period_total( $date, $date ),
			'total_bookings'      => $this->get_booking_count( $date, $date ),
			'average_booking_value' => $this->get_average_booking_value(),
		);

		return $wpdb->replace(
			$table,
			$data,
			array( '%s', '%s', '%f', '%d', '%d', '%d', '%f', '%f', '%f', '%d', '%d' )
		);
	}
}
