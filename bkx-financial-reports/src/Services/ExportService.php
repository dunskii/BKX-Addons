<?php
/**
 * Export Service.
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
 * ExportService Class.
 */
class ExportService {

	/**
	 * Export directory.
	 *
	 * @var string
	 */
	private $export_dir;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$upload_dir       = wp_upload_dir();
		$this->export_dir = $upload_dir['basedir'] . '/bkx-financial-exports/';

		if ( ! file_exists( $this->export_dir ) ) {
			wp_mkdir_p( $this->export_dir );

			// Add .htaccess to protect files.
			file_put_contents(
				$this->export_dir . '.htaccess',
				"Order deny,allow\nDeny from all\nAllow from env=allowed"
			);
		}
	}

	/**
	 * Export report.
	 *
	 * @param string $report_type Report type.
	 * @param string $format      Export format.
	 * @param string $start_date  Start date.
	 * @param string $end_date    End date.
	 * @return string|false Download URL or false.
	 */
	public function export_report( $report_type, $format = 'csv', $start_date = '', $end_date = '' ) {
		$data = $this->get_report_data( $report_type, $start_date, $end_date );

		if ( empty( $data ) ) {
			return false;
		}

		$filename = $this->generate_filename( $report_type, $format );

		switch ( $format ) {
			case 'csv':
				$result = $this->export_csv( $data, $filename );
				break;

			case 'pdf':
				$result = $this->export_pdf( $data, $filename, $report_type );
				break;

			default:
				$result = false;
		}

		if ( $result ) {
			return $this->get_download_url( $filename );
		}

		return false;
	}

	/**
	 * Get report data.
	 *
	 * @param string $report_type Report type.
	 * @param string $start_date  Start date.
	 * @param string $end_date    End date.
	 * @return array Report data.
	 */
	private function get_report_data( $report_type, $start_date, $end_date ) {
		switch ( $report_type ) {
			case 'revenue':
				$service = new RevenueAnalytics();
				return $service->get_revenue_data( 'custom', $start_date, $end_date );

			case 'pnl':
				$service = new ProfitLossReport();
				return $service->generate_report( 'custom', $start_date, $end_date );

			case 'tax':
				$service = new TaxReports();
				return $service->generate_report( 'custom', $start_date, $end_date );

			case 'cashflow':
				$service = new CashFlowAnalysis();
				return $service->analyze( 'month' );

			case 'expenses':
				$service = new ExpenseTracker();
				return $service->get_expenses( $start_date, $end_date );

			default:
				return array();
		}
	}

	/**
	 * Export to CSV.
	 *
	 * @param array  $data     Report data.
	 * @param string $filename Filename.
	 * @return bool Success.
	 */
	private function export_csv( $data, $filename ) {
		$filepath = $this->export_dir . $filename;
		$handle   = fopen( $filepath, 'w' );

		if ( ! $handle ) {
			return false;
		}

		// Determine data structure and export accordingly.
		if ( isset( $data['daily'] ) ) {
			// Revenue report.
			fputcsv( $handle, array( 'Date', 'Bookings', 'Completed', 'Cancelled', 'Revenue' ) );
			foreach ( $data['daily'] as $row ) {
				fputcsv( $handle, array(
					$row['date'],
					$row['total_bookings'],
					$row['completed'],
					$row['cancelled'],
					$row['revenue'],
				) );
			}

			// Add totals.
			fputcsv( $handle, array() );
			fputcsv( $handle, array( 'Totals' ) );
			fputcsv( $handle, array( 'Total Revenue', $data['totals']['total_revenue'] ) );
			fputcsv( $handle, array( 'Total Bookings', $data['totals']['total_bookings'] ) );
			fputcsv( $handle, array( 'Average Booking', $data['totals']['average_booking'] ) );
		} elseif ( isset( $data['revenue'] ) && isset( $data['expenses'] ) ) {
			// P&L report.
			fputcsv( $handle, array( 'Profit & Loss Report' ) );
			fputcsv( $handle, array( 'Period', $data['dates']['start'], 'to', $data['dates']['end'] ) );
			fputcsv( $handle, array() );

			fputcsv( $handle, array( 'Revenue' ) );
			foreach ( $data['revenue']['items'] as $item ) {
				fputcsv( $handle, array( $item['name'], $item['amount'] ) );
			}
			fputcsv( $handle, array( 'Total Revenue', $data['revenue']['total'] ) );

			fputcsv( $handle, array() );
			fputcsv( $handle, array( 'Expenses' ) );
			foreach ( $data['expenses']['items'] as $item ) {
				fputcsv( $handle, array( $item['name'] ?? $item['category'], $item['amount'] ) );
			}
			fputcsv( $handle, array( 'Total Expenses', $data['expenses']['total'] ) );

			fputcsv( $handle, array() );
			fputcsv( $handle, array( 'Gross Profit', $data['gross_profit']['amount'] ) );
			fputcsv( $handle, array( 'Profit Margin', $data['gross_profit']['margin'] . '%' ) );
		} elseif ( isset( $data['expenses'] ) ) {
			// Expenses report.
			fputcsv( $handle, array( 'Date', 'Category', 'Description', 'Amount', 'Vendor', 'Payment Method' ) );
			foreach ( $data['expenses'] as $expense ) {
				fputcsv( $handle, array(
					$expense['expense_date'],
					$expense['category'],
					$expense['description'],
					$expense['amount'],
					$expense['vendor'] ?? '',
					$expense['payment_method'] ?? '',
				) );
			}
			fputcsv( $handle, array() );
			fputcsv( $handle, array( 'Total', '', '', $data['grand_total'] ) );
		} elseif ( isset( $data['taxable_sales'] ) ) {
			// Tax report.
			fputcsv( $handle, array( 'Tax Report' ) );
			fputcsv( $handle, array( 'Period', $data['dates']['start'], 'to', $data['dates']['end'] ) );
			fputcsv( $handle, array() );
			fputcsv( $handle, array( 'Taxable Sales', $data['taxable_sales'] ) );
			fputcsv( $handle, array( 'Taxes Collected', $data['taxes_collected'] ) );
			fputcsv( $handle, array( 'Effective Rate', $data['effective_rate'] . '%' ) );

			if ( ! empty( $data['monthly_summary'] ) ) {
				fputcsv( $handle, array() );
				fputcsv( $handle, array( 'Monthly Summary' ) );
				fputcsv( $handle, array( 'Month', 'Taxable Sales', 'Tax Collected' ) );
				foreach ( $data['monthly_summary'] as $month ) {
					fputcsv( $handle, array(
						$month['label'],
						$month['taxable_sales'],
						$month['tax_collected'],
					) );
				}
			}
		}

		fclose( $handle );

		return file_exists( $filepath );
	}

	/**
	 * Export to PDF.
	 *
	 * @param array  $data        Report data.
	 * @param string $filename    Filename.
	 * @param string $report_type Report type.
	 * @return bool Success.
	 */
	private function export_pdf( $data, $filename, $report_type ) {
		// Simple HTML to PDF conversion.
		$html = $this->generate_pdf_html( $data, $report_type );

		// For now, save as HTML (PDF generation would require a library like TCPDF or DOMPDF).
		$filepath = $this->export_dir . str_replace( '.pdf', '.html', $filename );
		$result   = file_put_contents( $filepath, $html );

		return $result !== false;
	}

	/**
	 * Generate PDF HTML content.
	 *
	 * @param array  $data        Report data.
	 * @param string $report_type Report type.
	 * @return string HTML content.
	 */
	private function generate_pdf_html( $data, $report_type ) {
		$html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
		$html .= '<title>' . ucfirst( $report_type ) . ' Report</title>';
		$html .= '<style>
			body { font-family: Arial, sans-serif; margin: 40px; }
			h1 { color: #333; }
			table { width: 100%; border-collapse: collapse; margin-top: 20px; }
			th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
			th { background: #f5f5f5; }
			.total { font-weight: bold; background: #f9f9f9; }
			.currency { text-align: right; }
		</style></head><body>';

		$html .= '<h1>' . ucfirst( $report_type ) . ' Report</h1>';

		if ( isset( $data['dates'] ) ) {
			$html .= '<p>Period: ' . esc_html( $data['dates']['start'] ) . ' to ' . esc_html( $data['dates']['end'] ) . '</p>';
		}

		// Generate table based on report type.
		if ( isset( $data['daily'] ) ) {
			$html .= '<table><thead><tr><th>Date</th><th>Bookings</th><th>Completed</th><th class="currency">Revenue</th></tr></thead><tbody>';
			foreach ( $data['daily'] as $row ) {
				$html .= '<tr><td>' . esc_html( $row['date'] ) . '</td>';
				$html .= '<td>' . esc_html( $row['total_bookings'] ) . '</td>';
				$html .= '<td>' . esc_html( $row['completed'] ) . '</td>';
				$html .= '<td class="currency">' . number_format( $row['revenue'], 2 ) . '</td></tr>';
			}
			$html .= '<tr class="total"><td colspan="3">Total</td><td class="currency">' . number_format( $data['totals']['total_revenue'], 2 ) . '</td></tr>';
			$html .= '</tbody></table>';
		}

		$html .= '</body></html>';

		return $html;
	}

	/**
	 * Generate filename.
	 *
	 * @param string $report_type Report type.
	 * @param string $format      File format.
	 * @return string Filename.
	 */
	private function generate_filename( $report_type, $format ) {
		return sprintf(
			'bkx-%s-report-%s.%s',
			$report_type,
			gmdate( 'Y-m-d-His' ),
			$format
		);
	}

	/**
	 * Get download URL.
	 *
	 * @param string $filename Filename.
	 * @return string URL.
	 */
	private function get_download_url( $filename ) {
		// Create a temporary download token.
		$token = wp_generate_password( 32, false );

		set_transient( 'bkx_export_' . $token, $filename, HOUR_IN_SECONDS );

		return add_query_arg(
			array(
				'bkx_download_export' => $token,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Handle download request.
	 */
	public static function handle_download() {
		if ( ! isset( $_GET['bkx_download_export'] ) ) {
			return;
		}

		$token    = sanitize_text_field( wp_unslash( $_GET['bkx_download_export'] ) );
		$filename = get_transient( 'bkx_export_' . $token );

		if ( ! $filename ) {
			wp_die( 'Export expired or invalid.' );
		}

		$upload_dir = wp_upload_dir();
		$filepath   = $upload_dir['basedir'] . '/bkx-financial-exports/' . $filename;

		if ( ! file_exists( $filepath ) ) {
			wp_die( 'File not found.' );
		}

		// Determine content type.
		$ext = pathinfo( $filename, PATHINFO_EXTENSION );
		$content_types = array(
			'csv'  => 'text/csv',
			'pdf'  => 'application/pdf',
			'html' => 'text/html',
		);

		header( 'Content-Type: ' . ( $content_types[ $ext ] ?? 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $filepath ) );

		readfile( $filepath );

		// Delete transient.
		delete_transient( 'bkx_export_' . $token );

		exit;
	}

	/**
	 * Clean up old exports.
	 */
	public function cleanup_old_exports() {
		$files = glob( $this->export_dir . '*' );
		$now   = time();

		foreach ( $files as $file ) {
			if ( is_file( $file ) && basename( $file ) !== '.htaccess' ) {
				// Delete files older than 24 hours.
				if ( $now - filemtime( $file ) > DAY_IN_SECONDS ) {
					unlink( $file );
				}
			}
		}
	}
}
