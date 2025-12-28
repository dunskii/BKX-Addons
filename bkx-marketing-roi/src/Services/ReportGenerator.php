<?php
/**
 * Report Generator Service.
 *
 * @package BookingX\MarketingROI
 * @since   1.0.0
 */

namespace BookingX\MarketingROI\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ReportGenerator Class.
 *
 * Generates marketing ROI reports.
 */
class ReportGenerator {

	/**
	 * ROI Calculator.
	 *
	 * @var ROICalculator
	 */
	private $calculator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->calculator = new ROICalculator();
	}

	/**
	 * Export report to CSV.
	 *
	 * @param string $report_type Report type.
	 * @param string $start_date  Start date.
	 * @param string $end_date    End date.
	 * @return string|false File URL or false on failure.
	 */
	public function export_csv( $report_type, $start_date = '', $end_date = '' ) {
		$data = $this->get_report_data( $report_type, $start_date, $end_date );

		if ( empty( $data ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/bkx-roi-exports/';

		if ( ! is_dir( $export_dir ) ) {
			wp_mkdir_p( $export_dir );

			// Add .htaccess to protect exports.
			file_put_contents( $export_dir . '.htaccess', 'deny from all' );
		}

		$filename = sprintf(
			'bkx-roi-%s-%s.csv',
			$report_type,
			gmdate( 'Y-m-d-His' )
		);

		$filepath = $export_dir . $filename;

		$handle = fopen( $filepath, 'w' );
		if ( ! $handle ) {
			return false;
		}

		// Write headers.
		$headers = $this->get_csv_headers( $report_type );
		fputcsv( $handle, $headers );

		// Write data rows.
		foreach ( $data as $row ) {
			$csv_row = $this->format_csv_row( $report_type, $row );
			fputcsv( $handle, $csv_row );
		}

		fclose( $handle );

		// Schedule cleanup.
		$this->schedule_cleanup( $filepath );

		return $upload_dir['baseurl'] . '/bkx-roi-exports/' . $filename;
	}

	/**
	 * Get report data.
	 *
	 * @param string $report_type Report type.
	 * @param string $start_date  Start date.
	 * @param string $end_date    End date.
	 * @return array
	 */
	private function get_report_data( $report_type, $start_date, $end_date ) {
		switch ( $report_type ) {
			case 'campaigns':
				return $this->calculator->get_campaigns_with_roi( $start_date, $end_date );

			case 'sources':
				return $this->calculator->get_utm_report( $start_date, $end_date, 'source' );

			case 'mediums':
				return $this->calculator->get_utm_report( $start_date, $end_date, 'medium' );

			case 'daily':
				$utm_tracker = new UTMTracker();
				return $utm_tracker->get_daily_trends( $start_date, $end_date );

			default:
				return array();
		}
	}

	/**
	 * Get CSV headers for report type.
	 *
	 * @param string $report_type Report type.
	 * @return array
	 */
	private function get_csv_headers( $report_type ) {
		switch ( $report_type ) {
			case 'campaigns':
				return array(
					'Campaign Name',
					'UTM Source',
					'UTM Medium',
					'UTM Campaign',
					'Status',
					'Visits',
					'Conversions',
					'Conversion Rate',
					'Revenue',
					'Cost',
					'ROI',
					'ROAS',
					'CPA',
					'Profit',
				);

			case 'sources':
			case 'mediums':
				return array(
					ucfirst( str_replace( 's', '', $report_type ) ),
					'Visits',
					'Conversions',
					'Conversion Rate',
					'Revenue',
				);

			case 'daily':
				return array(
					'Date',
					'Visits',
					'Conversions',
					'Revenue',
				);

			default:
				return array();
		}
	}

	/**
	 * Format CSV row for report type.
	 *
	 * @param string $report_type Report type.
	 * @param array  $row         Data row.
	 * @return array
	 */
	private function format_csv_row( $report_type, $row ) {
		switch ( $report_type ) {
			case 'campaigns':
				return array(
					$row['campaign_name'],
					$row['utm_source'],
					$row['utm_medium'],
					$row['utm_campaign'],
					$row['status'],
					$row['visits'],
					$row['conversions'],
					$row['conversion_rate'] . '%',
					$row['revenue'],
					$row['cost'],
					$row['roi'] . '%',
					$row['roas'],
					$row['cpa'],
					$row['profit'],
				);

			case 'sources':
			case 'mediums':
				return array(
					$row['value'],
					$row['visits'],
					$row['conversions'],
					$row['conversion_rate'] . '%',
					$row['revenue'],
				);

			case 'daily':
				return array(
					$row['date'],
					$row['visits'],
					$row['conversions'],
					$row['revenue'],
				);

			default:
				return array_values( $row );
		}
	}

	/**
	 * Schedule file cleanup.
	 *
	 * @param string $filepath File path.
	 */
	private function schedule_cleanup( $filepath ) {
		wp_schedule_single_event(
			time() + HOUR_IN_SECONDS,
			'bkx_roi_cleanup_export',
			array( $filepath )
		);

		add_action(
			'bkx_roi_cleanup_export',
			function ( $path ) {
				if ( file_exists( $path ) ) {
					unlink( $path );
				}
			}
		);
	}

	/**
	 * Generate summary report.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function generate_summary_report( $start_date = '', $end_date = '' ) {
		$summary    = $this->calculator->get_summary( $start_date, $end_date );
		$campaigns  = $this->calculator->get_campaigns_with_roi( $start_date, $end_date );
		$performers = $this->calculator->get_top_performers( $start_date, $end_date );

		// Calculate period comparison if dates provided.
		$comparison = array();
		if ( ! empty( $start_date ) && ! empty( $end_date ) ) {
			$comparison = $this->calculate_period_comparison( $start_date, $end_date );
		}

		return array(
			'period'      => array(
				'start' => $start_date,
				'end'   => $end_date,
			),
			'summary'     => $summary,
			'campaigns'   => array(
				'total'    => count( $campaigns ),
				'active'   => count( array_filter( $campaigns, fn( $c ) => $c['status'] === 'active' ) ),
				'positive' => count( array_filter( $campaigns, fn( $c ) => $c['roi'] > 0 ) ),
			),
			'top_performers' => $performers,
			'comparison'     => $comparison,
		);
	}

	/**
	 * Calculate period comparison.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function calculate_period_comparison( $start_date, $end_date ) {
		$start    = new \DateTime( $start_date );
		$end      = new \DateTime( $end_date );
		$interval = $start->diff( $end );
		$days     = $interval->days + 1;

		// Previous period.
		$prev_end   = clone $start;
		$prev_end->modify( '-1 day' );
		$prev_start = clone $prev_end;
		$prev_start->modify( '-' . ( $days - 1 ) . ' days' );

		$current  = $this->calculator->get_summary( $start_date, $end_date );
		$previous = $this->calculator->get_summary(
			$prev_start->format( 'Y-m-d' ),
			$prev_end->format( 'Y-m-d' )
		);

		return array(
			'visits'          => $this->calculate_change( $previous['total_visits'], $current['total_visits'] ),
			'conversions'     => $this->calculate_change( $previous['conversions'], $current['conversions'] ),
			'revenue'         => $this->calculate_change( $previous['total_revenue'], $current['total_revenue'] ),
			'roi'             => $this->calculate_change( $previous['roi'], $current['roi'] ),
			'conversion_rate' => $this->calculate_change( $previous['conversion_rate'], $current['conversion_rate'] ),
		);
	}

	/**
	 * Calculate percentage change.
	 *
	 * @param float $previous Previous value.
	 * @param float $current  Current value.
	 * @return array
	 */
	private function calculate_change( $previous, $current ) {
		$change = $current - $previous;

		if ( $previous > 0 ) {
			$percent = ( $change / $previous ) * 100;
		} elseif ( $current > 0 ) {
			$percent = 100;
		} else {
			$percent = 0;
		}

		return array(
			'previous' => $previous,
			'current'  => $current,
			'change'   => $change,
			'percent'  => round( $percent, 1 ),
		);
	}
}
