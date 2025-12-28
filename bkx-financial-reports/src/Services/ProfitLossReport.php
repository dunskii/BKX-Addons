<?php
/**
 * Profit & Loss Report Service.
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
 * ProfitLossReport Class.
 */
class ProfitLossReport {

	/**
	 * Generate P&L report.
	 *
	 * @param string $period     Period type.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array P&L report data.
	 */
	public function generate_report( $period = 'month', $start_date = '', $end_date = '' ) {
		$dates = $this->get_date_range( $period, $start_date, $end_date );

		// Get revenue.
		$revenue = $this->get_revenue( $dates['start'], $dates['end'] );

		// Get expenses.
		$expenses = $this->get_expenses( $dates['start'], $dates['end'] );

		// Calculate totals.
		$total_revenue  = array_sum( array_column( $revenue, 'amount' ) );
		$total_expenses = array_sum( array_column( $expenses, 'amount' ) );
		$gross_profit   = $total_revenue - $total_expenses;
		$profit_margin  = $total_revenue > 0 ? ( $gross_profit / $total_revenue ) * 100 : 0;

		// Get comparison.
		$prev_dates        = $this->get_previous_period_dates( $dates );
		$prev_revenue      = $this->get_total_revenue( $prev_dates['start'], $prev_dates['end'] );
		$prev_expenses     = $this->get_total_expenses( $prev_dates['start'], $prev_dates['end'] );
		$prev_profit       = $prev_revenue - $prev_expenses;

		return array(
			'period'       => $period,
			'dates'        => $dates,
			'revenue'      => array(
				'items'    => $revenue,
				'total'    => $total_revenue,
				'previous' => $prev_revenue,
				'change'   => $prev_revenue > 0 ? round( ( ( $total_revenue - $prev_revenue ) / $prev_revenue ) * 100, 1 ) : 0,
			),
			'expenses'     => array(
				'items'    => $expenses,
				'total'    => $total_expenses,
				'previous' => $prev_expenses,
				'change'   => $prev_expenses > 0 ? round( ( ( $total_expenses - $prev_expenses ) / $prev_expenses ) * 100, 1 ) : 0,
			),
			'gross_profit' => array(
				'amount'   => $gross_profit,
				'margin'   => round( $profit_margin, 1 ),
				'previous' => $prev_profit,
				'change'   => $prev_profit != 0 ? round( ( ( $gross_profit - $prev_profit ) / abs( $prev_profit ) ) * 100, 1 ) : 0,
			),
			'monthly_breakdown' => $this->get_monthly_breakdown( $dates['start'], $dates['end'] ),
		);
	}

	/**
	 * Get revenue breakdown.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Revenue items.
	 */
	private function get_revenue( $start_date, $end_date ) {
		global $wpdb;

		// Get service revenue.
		$services = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					bases.post_title as name,
					SUM(CAST(pm_amount.meta_value AS DECIMAL(15,2))) as amount
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				INNER JOIN {$wpdb->postmeta} pm_base ON p.ID = pm_base.post_id AND pm_base.meta_key = 'base_id'
				LEFT JOIN {$wpdb->posts} bases ON bases.ID = pm_base.meta_value
				LEFT JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'total_amount'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-completed', 'bkx-ack')
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY pm_base.meta_value
				ORDER BY amount DESC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$revenue = array();
		foreach ( $services as $service ) {
			$revenue[] = array(
				'category' => 'Service Revenue',
				'name'     => $service['name'] ?: 'Unknown Service',
				'amount'   => floatval( $service['amount'] ),
			);
		}

		// Group small items.
		$threshold = array_sum( array_column( $revenue, 'amount' ) ) * 0.02; // 2% threshold.
		$other     = 0;
		$filtered  = array();

		foreach ( $revenue as $item ) {
			if ( $item['amount'] < $threshold ) {
				$other += $item['amount'];
			} else {
				$filtered[] = $item;
			}
		}

		if ( $other > 0 ) {
			$filtered[] = array(
				'category' => 'Service Revenue',
				'name'     => 'Other Services',
				'amount'   => $other,
			);
		}

		return $filtered;
	}

	/**
	 * Get expenses breakdown.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Expense items.
	 */
	private function get_expenses( $start_date, $end_date ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_financial_expenses';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT category, SUM(amount) as amount
				FROM %i
				WHERE expense_date BETWEEN %s AND %s
				GROUP BY category
				ORDER BY amount DESC",
				$table,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$expenses = array();
		foreach ( $results as $row ) {
			$expenses[] = array(
				'category' => $row['category'],
				'name'     => $row['category'],
				'amount'   => floatval( $row['amount'] ),
			);
		}

		return $expenses;
	}

	/**
	 * Get total revenue.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return float Total revenue.
	 */
	private function get_total_revenue( $start_date, $end_date ) {
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
	 * Get total expenses.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return float Total expenses.
	 */
	private function get_total_expenses( $start_date, $end_date ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_financial_expenses';

		return floatval(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(amount) FROM %i WHERE expense_date BETWEEN %s AND %s",
					$table,
					$start_date,
					$end_date
				)
			)
		);
	}

	/**
	 * Get monthly breakdown.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Monthly data.
	 */
	private function get_monthly_breakdown( $start_date, $end_date ) {
		global $wpdb;
		$expense_table = $wpdb->prefix . 'bkx_financial_expenses';

		// Get monthly revenue.
		$revenue_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE_FORMAT(pm_date.meta_value, '%%Y-%%m') as month,
					SUM(CAST(pm_amount.meta_value AS DECIMAL(15,2))) as revenue
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				INNER JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'total_amount'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-completed', 'bkx-ack')
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY DATE_FORMAT(pm_date.meta_value, '%%Y-%%m')
				ORDER BY month ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Get monthly expenses.
		$expense_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE_FORMAT(expense_date, '%%Y-%%m') as month,
					SUM(amount) as expenses
				FROM %i
				WHERE expense_date BETWEEN %s AND %s
				GROUP BY DATE_FORMAT(expense_date, '%%Y-%%m')
				ORDER BY month ASC",
				$expense_table,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Combine data.
		$revenue_by_month = array();
		foreach ( $revenue_data as $row ) {
			$revenue_by_month[ $row['month'] ] = floatval( $row['revenue'] );
		}

		$expense_by_month = array();
		foreach ( $expense_data as $row ) {
			$expense_by_month[ $row['month'] ] = floatval( $row['expenses'] );
		}

		$all_months = array_unique( array_merge( array_keys( $revenue_by_month ), array_keys( $expense_by_month ) ) );
		sort( $all_months );

		$breakdown = array();
		foreach ( $all_months as $month ) {
			$rev = $revenue_by_month[ $month ] ?? 0;
			$exp = $expense_by_month[ $month ] ?? 0;

			$breakdown[] = array(
				'month'    => $month,
				'label'    => gmdate( 'M Y', strtotime( $month . '-01' ) ),
				'revenue'  => $rev,
				'expenses' => $exp,
				'profit'   => $rev - $exp,
			);
		}

		return $breakdown;
	}

	/**
	 * Get date range.
	 *
	 * @param string $period     Period type.
	 * @param string $start_date Custom start.
	 * @param string $end_date   Custom end.
	 * @return array Dates.
	 */
	private function get_date_range( $period, $start_date = '', $end_date = '' ) {
		$today = gmdate( 'Y-m-d' );

		switch ( $period ) {
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
	 * @param array $current_dates Current dates.
	 * @return array Previous dates.
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
}
