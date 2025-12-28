<?php
/**
 * Cash Flow Analysis Service.
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
 * CashFlowAnalysis Class.
 */
class CashFlowAnalysis {

	/**
	 * Analyze cash flow.
	 *
	 * @param string $period Period type.
	 * @return array Cash flow data.
	 */
	public function analyze( $period = 'month' ) {
		$dates = $this->get_date_range( $period );

		$inflows  = $this->get_inflows( $dates['start'], $dates['end'] );
		$outflows = $this->get_outflows( $dates['start'], $dates['end'] );

		$total_inflow  = array_sum( array_column( $inflows, 'amount' ) );
		$total_outflow = array_sum( array_column( $outflows, 'amount' ) );
		$net_flow      = $total_inflow - $total_outflow;

		// Get daily flow.
		$daily_flow = $this->get_daily_flow( $dates['start'], $dates['end'] );

		// Calculate running balance.
		$opening_balance = $this->get_opening_balance( $dates['start'] );
		$running_balance = $opening_balance;

		foreach ( $daily_flow as &$day ) {
			$running_balance += $day['net_flow'];
			$day['balance']   = $running_balance;
		}

		return array(
			'period'          => $period,
			'dates'           => $dates,
			'opening_balance' => $opening_balance,
			'closing_balance' => $running_balance,
			'inflows'         => array(
				'items' => $inflows,
				'total' => $total_inflow,
			),
			'outflows'        => array(
				'items' => $outflows,
				'total' => $total_outflow,
			),
			'net_flow'        => $net_flow,
			'daily_flow'      => $daily_flow,
			'forecaste'       => $this->forecast_next_period( $daily_flow ),
		);
	}

	/**
	 * Get cash inflows.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Inflow items.
	 */
	private function get_inflows( $start_date, $end_date ) {
		global $wpdb;

		// Get booking payments.
		$booking_revenue = $wpdb->get_var(
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
		);

		$inflows = array(
			array(
				'category' => 'Booking Revenue',
				'amount'   => floatval( $booking_revenue ),
			),
		);

		// Filter out zero amounts.
		return array_filter( $inflows, function( $item ) {
			return $item['amount'] > 0;
		});
	}

	/**
	 * Get cash outflows.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Outflow items.
	 */
	private function get_outflows( $start_date, $end_date ) {
		global $wpdb;
		$expense_table = $wpdb->prefix . 'bkx_financial_expenses';

		$expenses = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT category, SUM(amount) as amount
				FROM %i
				WHERE expense_date BETWEEN %s AND %s
				GROUP BY category
				ORDER BY amount DESC",
				$expense_table,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$outflows = array();
		foreach ( $expenses as $expense ) {
			$outflows[] = array(
				'category' => $expense['category'],
				'amount'   => floatval( $expense['amount'] ),
			);
		}

		// Add refunds.
		$refunds = $this->get_refunds( $start_date, $end_date );
		if ( $refunds > 0 ) {
			$outflows[] = array(
				'category' => 'Refunds',
				'amount'   => $refunds,
			);
		}

		return $outflows;
	}

	/**
	 * Get refunds.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return float Total refunds.
	 */
	private function get_refunds( $start_date, $end_date ) {
		global $wpdb;

		$refunds = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm_amount.meta_value AS DECIMAL(15,2)))
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'refund_amount'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status = 'bkx-cancelled'
				AND pm_date.meta_value BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		return floatval( $refunds );
	}

	/**
	 * Get daily cash flow.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Daily flow data.
	 */
	private function get_daily_flow( $start_date, $end_date ) {
		global $wpdb;
		$expense_table = $wpdb->prefix . 'bkx_financial_expenses';

		// Get daily revenue.
		$revenue_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(pm_date.meta_value) as date,
					SUM(CAST(pm_amount.meta_value AS DECIMAL(15,2))) as inflow
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				INNER JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'total_amount'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-completed', 'bkx-ack')
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY DATE(pm_date.meta_value)
				ORDER BY date ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Get daily expenses.
		$expense_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT expense_date as date, SUM(amount) as outflow
				FROM %i
				WHERE expense_date BETWEEN %s AND %s
				GROUP BY expense_date
				ORDER BY date ASC",
				$expense_table,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Index by date.
		$revenue_by_date = array();
		foreach ( $revenue_data as $row ) {
			$revenue_by_date[ $row['date'] ] = floatval( $row['inflow'] );
		}

		$expense_by_date = array();
		foreach ( $expense_data as $row ) {
			$expense_by_date[ $row['date'] ] = floatval( $row['outflow'] );
		}

		// Generate daily flow.
		$daily_flow = array();
		$current    = new \DateTime( $start_date );
		$end        = new \DateTime( $end_date );

		while ( $current <= $end ) {
			$date_key = $current->format( 'Y-m-d' );
			$inflow   = $revenue_by_date[ $date_key ] ?? 0;
			$outflow  = $expense_by_date[ $date_key ] ?? 0;

			$daily_flow[] = array(
				'date'     => $date_key,
				'label'    => $current->format( 'M j' ),
				'inflow'   => $inflow,
				'outflow'  => $outflow,
				'net_flow' => $inflow - $outflow,
			);

			$current->modify( '+1 day' );
		}

		return $daily_flow;
	}

	/**
	 * Get opening balance.
	 *
	 * @param string $date Date.
	 * @return float Opening balance.
	 */
	private function get_opening_balance( $date ) {
		// Get historical data from before this period.
		global $wpdb;
		$expense_table = $wpdb->prefix . 'bkx_financial_expenses';

		// Total revenue before period.
		$total_revenue = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm_amount.meta_value AS DECIMAL(15,2)))
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				INNER JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'total_amount'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-completed', 'bkx-ack')
				AND pm_date.meta_value < %s",
				$date
			)
		);

		// Total expenses before period.
		$total_expenses = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(amount) FROM %i WHERE expense_date < %s",
				$expense_table,
				$date
			)
		);

		return floatval( $total_revenue ) - floatval( $total_expenses );
	}

	/**
	 * Forecast next period.
	 *
	 * @param array $daily_flow Historical daily flow.
	 * @return array Forecast data.
	 */
	private function forecast_next_period( $daily_flow ) {
		if ( count( $daily_flow ) < 7 ) {
			return array(
				'available'     => false,
				'message'       => __( 'Not enough data for forecast', 'bkx-financial-reports' ),
			);
		}

		// Calculate averages.
		$total_inflow  = array_sum( array_column( $daily_flow, 'inflow' ) );
		$total_outflow = array_sum( array_column( $daily_flow, 'outflow' ) );
		$days          = count( $daily_flow );

		$avg_daily_inflow  = $total_inflow / $days;
		$avg_daily_outflow = $total_outflow / $days;

		// Forecast next 30 days.
		$forecast_days       = 30;
		$projected_inflow    = $avg_daily_inflow * $forecast_days;
		$projected_outflow   = $avg_daily_outflow * $forecast_days;
		$projected_net       = $projected_inflow - $projected_outflow;
		$current_balance     = end( $daily_flow )['balance'] ?? 0;
		$projected_balance   = $current_balance + $projected_net;

		return array(
			'available'          => true,
			'days_forecast'      => $forecast_days,
			'avg_daily_inflow'   => round( $avg_daily_inflow, 2 ),
			'avg_daily_outflow'  => round( $avg_daily_outflow, 2 ),
			'projected_inflow'   => round( $projected_inflow, 2 ),
			'projected_outflow'  => round( $projected_outflow, 2 ),
			'projected_net'      => round( $projected_net, 2 ),
			'projected_balance'  => round( $projected_balance, 2 ),
		);
	}

	/**
	 * Get date range.
	 *
	 * @param string $period Period type.
	 * @return array Dates.
	 */
	private function get_date_range( $period ) {
		$today = gmdate( 'Y-m-d' );

		switch ( $period ) {
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
				$quarter = ceil( gmdate( 'n' ) / 3 );
				$start   = gmdate( 'Y-m-01', strtotime( gmdate( 'Y' ) . '-' . ( ( $quarter - 1 ) * 3 + 1 ) . '-01' ) );
				return array(
					'start' => $start,
					'end'   => $today,
				);

			case 'year':
				return array(
					'start' => gmdate( 'Y-01-01' ),
					'end'   => $today,
				);

			default:
				return array(
					'start' => gmdate( 'Y-m-01' ),
					'end'   => $today,
				);
		}
	}
}
