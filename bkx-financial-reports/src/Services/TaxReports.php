<?php
/**
 * Tax Reports Service.
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
 * TaxReports Class.
 */
class TaxReports {

	/**
	 * Generate tax report.
	 *
	 * @param string $period     Period type.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Tax report data.
	 */
	public function generate_report( $period = 'quarter', $start_date = '', $end_date = '' ) {
		$dates = $this->get_date_range( $period, $start_date, $end_date );

		$taxable_sales   = $this->get_taxable_sales( $dates['start'], $dates['end'] );
		$taxes_collected = $this->get_taxes_collected( $dates['start'], $dates['end'] );
		$tax_by_rate     = $this->get_tax_by_rate( $dates['start'], $dates['end'] );

		return array(
			'period'          => $period,
			'dates'           => $dates,
			'taxable_sales'   => $taxable_sales,
			'taxes_collected' => $taxes_collected,
			'effective_rate'  => $taxable_sales > 0 ? round( ( $taxes_collected / $taxable_sales ) * 100, 2 ) : 0,
			'by_rate'         => $tax_by_rate,
			'monthly_summary' => $this->get_monthly_tax_summary( $dates['start'], $dates['end'] ),
		);
	}

	/**
	 * Get taxable sales.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return float Taxable sales total.
	 */
	private function get_taxable_sales( $start_date, $end_date ) {
		global $wpdb;

		// Get total revenue (assuming all is taxable for now).
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
	 * Get taxes collected.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return float Taxes collected.
	 */
	private function get_taxes_collected( $start_date, $end_date ) {
		global $wpdb;

		// Try to get from tax_amount meta.
		$taxes = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm_tax.meta_value AS DECIMAL(15,2)))
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_tax ON p.ID = pm_tax.post_id AND pm_tax.meta_key = 'tax_amount'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-completed', 'bkx-ack')
				AND pm_date.meta_value BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		if ( $taxes ) {
			return floatval( $taxes );
		}

		// Estimate based on default tax rate.
		$default_rate  = floatval( get_option( 'bkx_fin_default_tax_rate', 0 ) );
		$taxable_sales = $this->get_taxable_sales( $start_date, $end_date );

		return $taxable_sales * ( $default_rate / 100 );
	}

	/**
	 * Get tax breakdown by rate.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Tax by rate.
	 */
	private function get_tax_by_rate( $start_date, $end_date ) {
		global $wpdb;
		$rates_table = $wpdb->prefix . 'bkx_financial_tax_rates';

		// Get configured rates.
		$rates = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE is_active = 1 ORDER BY priority ASC",
				$rates_table
			),
			ARRAY_A
		);

		if ( empty( $rates ) ) {
			// Return default rate breakdown.
			$default_rate  = floatval( get_option( 'bkx_fin_default_tax_rate', 0 ) );
			$taxable_sales = $this->get_taxable_sales( $start_date, $end_date );

			return array(
				array(
					'name'    => __( 'Standard Rate', 'bkx-financial-reports' ),
					'rate'    => $default_rate,
					'taxable' => $taxable_sales,
					'tax'     => $taxable_sales * ( $default_rate / 100 ),
				),
			);
		}

		$breakdown = array();
		foreach ( $rates as $rate ) {
			// In a real implementation, you'd filter bookings by the applicable rate.
			$breakdown[] = array(
				'name'    => $rate['name'],
				'rate'    => floatval( $rate['rate'] ),
				'taxable' => 0,
				'tax'     => 0,
			);
		}

		return $breakdown;
	}

	/**
	 * Get monthly tax summary.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array Monthly summary.
	 */
	private function get_monthly_tax_summary( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE_FORMAT(pm_date.meta_value, '%%Y-%%m') as month,
					SUM(CAST(pm_amount.meta_value AS DECIMAL(15,2))) as taxable_sales,
					SUM(CAST(COALESCE(pm_tax.meta_value, 0) AS DECIMAL(15,2))) as tax_collected
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = 'total_amount'
				LEFT JOIN {$wpdb->postmeta} pm_tax ON p.ID = pm_tax.post_id AND pm_tax.meta_key = 'tax_amount'
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

		$default_rate = floatval( get_option( 'bkx_fin_default_tax_rate', 0 ) );
		$summary      = array();

		foreach ( $results as $row ) {
			$taxable = floatval( $row['taxable_sales'] );
			$tax     = floatval( $row['tax_collected'] );

			// If no tax recorded, estimate.
			if ( $tax <= 0 && $default_rate > 0 ) {
				$tax = $taxable * ( $default_rate / 100 );
			}

			$summary[] = array(
				'month'         => $row['month'],
				'label'         => gmdate( 'M Y', strtotime( $row['month'] . '-01' ) ),
				'taxable_sales' => $taxable,
				'tax_collected' => $tax,
			);
		}

		return $summary;
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
}
