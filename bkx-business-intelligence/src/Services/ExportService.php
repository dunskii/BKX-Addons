<?php
/**
 * Export Service Class.
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
		$this->export_dir = $upload_dir['basedir'] . '/bkx-bi-exports/';

		if ( ! file_exists( $this->export_dir ) ) {
			wp_mkdir_p( $this->export_dir );
			// Add .htaccess to protect exports.
			file_put_contents( $this->export_dir . '.htaccess', 'deny from all' );
		}
	}

	/**
	 * Export report to CSV.
	 *
	 * @param array  $report Report data.
	 * @param string $filename Filename.
	 * @return string|false File URL or false on failure.
	 */
	public function export_csv( $report, $filename = '' ) {
		if ( empty( $filename ) ) {
			$filename = 'report-' . gmdate( 'Y-m-d-His' ) . '.csv';
		}

		$filepath = $this->export_dir . sanitize_file_name( $filename );
		$handle   = fopen( $filepath, 'w' );

		if ( ! $handle ) {
			return false;
		}

		// Add BOM for Excel UTF-8 compatibility.
		fwrite( $handle, "\xEF\xBB\xBF" );

		// Write report metadata.
		fputcsv( $handle, array( 'Report:', $report['title'] ?? 'Business Intelligence Report' ) );
		fputcsv( $handle, array( 'Generated:', $report['generated'] ?? current_time( 'mysql' ) ) );

		if ( isset( $report['period'] ) ) {
			fputcsv( $handle, array( 'Period:', $report['period']['start'] . ' to ' . $report['period']['end'] ) );
		}

		fputcsv( $handle, array() ); // Empty row.

		// Write summary section if exists.
		if ( isset( $report['summary'] ) && is_array( $report['summary'] ) ) {
			fputcsv( $handle, array( 'Summary' ) );
			foreach ( $report['summary'] as $key => $value ) {
				$label = ucwords( str_replace( '_', ' ', $key ) );
				fputcsv( $handle, array( $label, $this->format_value( $value ) ) );
			}
			fputcsv( $handle, array() );
		}

		// Write KPIs section if exists.
		if ( isset( $report['kpis'] ) && is_array( $report['kpis'] ) ) {
			fputcsv( $handle, array( 'Key Performance Indicators' ) );
			foreach ( $report['kpis'] as $key => $value ) {
				$label = ucwords( str_replace( '_', ' ', $key ) );
				fputcsv( $handle, array( $label, $this->format_value( $value ) ) );
			}
			fputcsv( $handle, array() );
		}

		// Write services section if exists.
		if ( isset( $report['services'] ) && is_array( $report['services'] ) ) {
			fputcsv( $handle, array( 'Service Performance' ) );
			$headers = array( 'Service', 'Bookings', 'Completed', 'Cancelled', 'Completion Rate', 'Revenue', 'Avg Value' );
			fputcsv( $handle, $headers );

			foreach ( $report['services'] as $service ) {
				fputcsv( $handle, array(
					$service['name'],
					$service['total_bookings'],
					$service['completed'],
					$service['cancelled'],
					$service['completion_rate'] . '%',
					$this->format_currency( $service['revenue'] ),
					$this->format_currency( $service['avg_booking_value'] ),
				) );
			}
			fputcsv( $handle, array() );
		}

		// Write staff section if exists.
		if ( isset( $report['staff'] ) && is_array( $report['staff'] ) ) {
			fputcsv( $handle, array( 'Staff Performance' ) );
			$headers = array( 'Staff', 'Bookings', 'Completed', 'Cancelled', 'Missed', 'Completion Rate', 'Revenue', 'Customers' );
			fputcsv( $handle, $headers );

			foreach ( $report['staff'] as $member ) {
				fputcsv( $handle, array(
					$member['name'],
					$member['total_bookings'],
					$member['completed'],
					$member['cancelled'],
					$member['missed'],
					$member['completion_rate'] . '%',
					$this->format_currency( $member['revenue'] ),
					$member['unique_customers'],
				) );
			}
			fputcsv( $handle, array() );
		}

		// Write trend data if exists.
		if ( isset( $report['trend_data'] ) && is_array( $report['trend_data'] ) ) {
			fputcsv( $handle, array( 'Trend Data' ) );

			$labels = $report['trend_data']['labels'] ?? array();
			$datasets = $report['trend_data']['datasets'] ?? array();

			// Build header.
			$headers = array( 'Date' );
			foreach ( $datasets as $dataset ) {
				$headers[] = $dataset['label'] ?? 'Value';
			}
			fputcsv( $handle, $headers );

			// Write data rows.
			foreach ( $labels as $index => $label ) {
				$row = array( $label );
				foreach ( $datasets as $dataset ) {
					$row[] = $dataset['data'][ $index ] ?? 0;
				}
				fputcsv( $handle, $row );
			}
			fputcsv( $handle, array() );
		}

		// Write breakdown data if exists.
		if ( isset( $report['breakdown'] ) && is_array( $report['breakdown'] ) ) {
			fputcsv( $handle, array( 'Revenue Breakdown' ) );
			$headers = array( 'Payment Method', 'Revenue', 'Count' );
			fputcsv( $handle, $headers );

			foreach ( $report['breakdown'] as $item ) {
				fputcsv( $handle, array(
					ucfirst( $item['payment_method'] ),
					$this->format_currency( $item['revenue'] ),
					$item['count'],
				) );
			}
			fputcsv( $handle, array() );
		}

		// Write top customers if exists.
		if ( isset( $report['top_customers'] ) && is_array( $report['top_customers'] ) ) {
			fputcsv( $handle, array( 'Top Customers' ) );
			$headers = array( 'Customer', 'Bookings', 'Total Spent' );
			fputcsv( $handle, $headers );

			foreach ( $report['top_customers'] as $customer ) {
				fputcsv( $handle, array(
					$this->mask_email( $customer['customer_email'] ),
					$customer['booking_count'],
					$this->format_currency( $customer['total_spent'] ),
				) );
			}
		}

		fclose( $handle );

		// Return download URL.
		$upload_dir = wp_upload_dir();
		return $upload_dir['baseurl'] . '/bkx-bi-exports/' . sanitize_file_name( $filename );
	}

	/**
	 * Export report to PDF.
	 *
	 * @param array  $report   Report data.
	 * @param string $filename Filename.
	 * @return string|false File URL or false on failure.
	 */
	public function export_pdf( $report, $filename = '' ) {
		if ( empty( $filename ) ) {
			$filename = 'report-' . gmdate( 'Y-m-d-His' ) . '.pdf';
		}

		$filepath = $this->export_dir . sanitize_file_name( $filename );

		// Generate HTML for PDF.
		$html = $this->generate_pdf_html( $report );

		// Use DomPDF if available, otherwise fall back to simple HTML-to-PDF.
		if ( class_exists( 'Dompdf\Dompdf' ) ) {
			$dompdf = new \Dompdf\Dompdf();
			$dompdf->loadHtml( $html );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			file_put_contents( $filepath, $dompdf->output() );
		} else {
			// Fallback: Save as HTML (can be printed to PDF).
			$filepath = str_replace( '.pdf', '.html', $filepath );
			$filename = str_replace( '.pdf', '.html', $filename );
			file_put_contents( $filepath, $html );
		}

		$upload_dir = wp_upload_dir();
		return $upload_dir['baseurl'] . '/bkx-bi-exports/' . sanitize_file_name( $filename );
	}

	/**
	 * Generate PDF HTML content.
	 *
	 * @param array $report Report data.
	 * @return string
	 */
	private function generate_pdf_html( $report ) {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<title><?php echo esc_html( $report['title'] ?? 'Business Intelligence Report' ); ?></title>
			<style>
				body {
					font-family: 'Helvetica', 'Arial', sans-serif;
					font-size: 12px;
					line-height: 1.5;
					color: #333;
					margin: 40px;
				}
				h1 {
					color: #0073aa;
					font-size: 24px;
					margin-bottom: 10px;
				}
				h2 {
					color: #23282d;
					font-size: 16px;
					margin-top: 30px;
					margin-bottom: 10px;
					border-bottom: 2px solid #0073aa;
					padding-bottom: 5px;
				}
				.meta {
					color: #666;
					font-size: 11px;
					margin-bottom: 20px;
				}
				table {
					width: 100%;
					border-collapse: collapse;
					margin-bottom: 20px;
				}
				th, td {
					border: 1px solid #ddd;
					padding: 8px;
					text-align: left;
				}
				th {
					background-color: #f5f5f5;
					font-weight: bold;
				}
				tr:nth-child(even) {
					background-color: #fafafa;
				}
				.kpi-grid {
					display: table;
					width: 100%;
				}
				.kpi-item {
					display: table-cell;
					width: 25%;
					padding: 15px;
					text-align: center;
					border: 1px solid #ddd;
				}
				.kpi-value {
					font-size: 24px;
					font-weight: bold;
					color: #0073aa;
				}
				.kpi-label {
					font-size: 11px;
					color: #666;
				}
				.insight {
					padding: 10px;
					margin: 10px 0;
					border-radius: 4px;
				}
				.insight.positive {
					background-color: #e7f5e9;
					border-left: 4px solid #46b450;
				}
				.insight.warning {
					background-color: #fff8e5;
					border-left: 4px solid #ffb900;
				}
				.footer {
					margin-top: 40px;
					padding-top: 20px;
					border-top: 1px solid #ddd;
					font-size: 10px;
					color: #999;
					text-align: center;
				}
			</style>
		</head>
		<body>
			<h1><?php echo esc_html( $report['title'] ?? 'Business Intelligence Report' ); ?></h1>
			<div class="meta">
				<?php if ( isset( $report['period'] ) ) : ?>
					<strong>Period:</strong> <?php echo esc_html( $report['period']['start'] ); ?> to <?php echo esc_html( $report['period']['end'] ); ?><br>
				<?php endif; ?>
				<strong>Generated:</strong> <?php echo esc_html( $report['generated'] ?? current_time( 'mysql' ) ); ?>
			</div>

			<?php if ( isset( $report['kpis'] ) ) : ?>
				<h2>Key Performance Indicators</h2>
				<div class="kpi-grid">
					<?php foreach ( $report['kpis'] as $key => $value ) : ?>
						<div class="kpi-item">
							<div class="kpi-value"><?php echo esc_html( $this->format_value( $value ) ); ?></div>
							<div class="kpi-label"><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( isset( $report['summary'] ) ) : ?>
				<h2>Summary</h2>
				<table>
					<tr>
						<th>Metric</th>
						<th>Value</th>
					</tr>
					<?php foreach ( $report['summary'] as $key => $value ) : ?>
						<tr>
							<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></td>
							<td><?php echo esc_html( $this->format_value( $value ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endif; ?>

			<?php if ( isset( $report['services'] ) ) : ?>
				<h2>Service Performance</h2>
				<table>
					<tr>
						<th>Service</th>
						<th>Bookings</th>
						<th>Completed</th>
						<th>Completion Rate</th>
						<th>Revenue</th>
					</tr>
					<?php foreach ( $report['services'] as $service ) : ?>
						<tr>
							<td><?php echo esc_html( $service['name'] ); ?></td>
							<td><?php echo esc_html( $service['total_bookings'] ); ?></td>
							<td><?php echo esc_html( $service['completed'] ); ?></td>
							<td><?php echo esc_html( $service['completion_rate'] ); ?>%</td>
							<td><?php echo esc_html( $this->format_currency( $service['revenue'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endif; ?>

			<?php if ( isset( $report['staff'] ) ) : ?>
				<h2>Staff Performance</h2>
				<table>
					<tr>
						<th>Staff</th>
						<th>Bookings</th>
						<th>Completed</th>
						<th>Completion Rate</th>
						<th>Revenue</th>
					</tr>
					<?php foreach ( $report['staff'] as $member ) : ?>
						<tr>
							<td><?php echo esc_html( $member['name'] ); ?></td>
							<td><?php echo esc_html( $member['total_bookings'] ); ?></td>
							<td><?php echo esc_html( $member['completed'] ); ?></td>
							<td><?php echo esc_html( $member['completion_rate'] ); ?>%</td>
							<td><?php echo esc_html( $this->format_currency( $member['revenue'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endif; ?>

			<?php if ( isset( $report['insights'] ) ) : ?>
				<h2>Insights</h2>
				<?php foreach ( $report['insights'] as $insight ) : ?>
					<div class="insight <?php echo esc_attr( $insight['type'] ); ?>">
						<?php echo esc_html( $insight['message'] ); ?>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>

			<div class="footer">
				Generated by BookingX Business Intelligence &bull; <?php echo esc_html( get_bloginfo( 'name' ) ); ?>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}

	/**
	 * Export raw data to JSON.
	 *
	 * @param array  $data     Data to export.
	 * @param string $filename Filename.
	 * @return string|false File URL or false on failure.
	 */
	public function export_json( $data, $filename = '' ) {
		if ( empty( $filename ) ) {
			$filename = 'data-' . gmdate( 'Y-m-d-His' ) . '.json';
		}

		$filepath = $this->export_dir . sanitize_file_name( $filename );
		$json     = wp_json_encode( $data, JSON_PRETTY_PRINT );

		if ( false === file_put_contents( $filepath, $json ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		return $upload_dir['baseurl'] . '/bkx-bi-exports/' . sanitize_file_name( $filename );
	}

	/**
	 * Generate Excel-compatible XML (SpreadsheetML).
	 *
	 * @param array  $report   Report data.
	 * @param string $filename Filename.
	 * @return string|false File URL or false on failure.
	 */
	public function export_excel( $report, $filename = '' ) {
		if ( empty( $filename ) ) {
			$filename = 'report-' . gmdate( 'Y-m-d-His' ) . '.xls';
		}

		$filepath = $this->export_dir . sanitize_file_name( $filename );

		// Generate SpreadsheetML.
		$xml = $this->generate_spreadsheet_xml( $report );

		if ( false === file_put_contents( $filepath, $xml ) ) {
			return false;
		}

		$upload_dir = wp_upload_dir();
		return $upload_dir['baseurl'] . '/bkx-bi-exports/' . sanitize_file_name( $filename );
	}

	/**
	 * Generate SpreadsheetML XML.
	 *
	 * @param array $report Report data.
	 * @return string
	 */
	private function generate_spreadsheet_xml( $report ) {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
		$xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
		$xml .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";

		// Styles.
		$xml .= '<Styles>' . "\n";
		$xml .= '<Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#f5f5f5" ss:Pattern="Solid"/></Style>' . "\n";
		$xml .= '<Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14" ss:Color="#0073aa"/></Style>' . "\n";
		$xml .= '<Style ss:ID="Currency"><NumberFormat ss:Format="&quot;$&quot;#,##0.00"/></Style>' . "\n";
		$xml .= '</Styles>' . "\n";

		// Worksheet.
		$xml .= '<Worksheet ss:Name="Report">' . "\n";
		$xml .= '<Table>' . "\n";

		// Title.
		$xml .= '<Row><Cell ss:StyleID="Title"><Data ss:Type="String">' . esc_html( $report['title'] ?? 'Report' ) . '</Data></Cell></Row>' . "\n";
		$xml .= '<Row></Row>' . "\n";

		// Summary.
		if ( isset( $report['summary'] ) ) {
			$xml .= '<Row><Cell ss:StyleID="Header"><Data ss:Type="String">Summary</Data></Cell></Row>' . "\n";
			foreach ( $report['summary'] as $key => $value ) {
				$xml .= '<Row>';
				$xml .= '<Cell><Data ss:Type="String">' . esc_html( ucwords( str_replace( '_', ' ', $key ) ) ) . '</Data></Cell>';
				$xml .= '<Cell><Data ss:Type="Number">' . esc_html( $value ) . '</Data></Cell>';
				$xml .= '</Row>' . "\n";
			}
			$xml .= '<Row></Row>' . "\n";
		}

		// Services.
		if ( isset( $report['services'] ) ) {
			$xml .= '<Row><Cell ss:StyleID="Header"><Data ss:Type="String">Service Performance</Data></Cell></Row>' . "\n";
			$xml .= '<Row>';
			$xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Service</Data></Cell>';
			$xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Bookings</Data></Cell>';
			$xml .= '<Cell ss:StyleID="Header"><Data ss:Type="String">Revenue</Data></Cell>';
			$xml .= '</Row>' . "\n";

			foreach ( $report['services'] as $service ) {
				$xml .= '<Row>';
				$xml .= '<Cell><Data ss:Type="String">' . esc_html( $service['name'] ) . '</Data></Cell>';
				$xml .= '<Cell><Data ss:Type="Number">' . $service['total_bookings'] . '</Data></Cell>';
				$xml .= '<Cell ss:StyleID="Currency"><Data ss:Type="Number">' . $service['revenue'] . '</Data></Cell>';
				$xml .= '</Row>' . "\n";
			}
		}

		$xml .= '</Table>' . "\n";
		$xml .= '</Worksheet>' . "\n";
		$xml .= '</Workbook>';

		return $xml;
	}

	/**
	 * Send report via email.
	 *
	 * @param array  $report     Report data.
	 * @param array  $recipients Recipients.
	 * @param string $format     Export format (csv, pdf).
	 * @return bool
	 */
	public function send_email( $report, $recipients, $format = 'pdf' ) {
		if ( empty( $recipients ) ) {
			return false;
		}

		// Generate export file.
		$filename = sanitize_title( $report['title'] ?? 'report' ) . '-' . gmdate( 'Y-m-d' );

		if ( 'csv' === $format ) {
			$file_url = $this->export_csv( $report, $filename . '.csv' );
		} else {
			$file_url = $this->export_pdf( $report, $filename . '.pdf' );
		}

		if ( ! $file_url ) {
			return false;
		}

		// Convert URL to path for attachment.
		$upload_dir = wp_upload_dir();
		$file_path  = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $file_url );

		$subject = sprintf(
			/* translators: 1: report title, 2: site name */
			__( '[%2$s] %1$s', 'bkx-business-intelligence' ),
			$report['title'] ?? __( 'Business Intelligence Report', 'bkx-business-intelligence' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: report title, 2: period start, 3: period end */
			__( "Please find attached your %1\$s.\n\nPeriod: %2\$s to %3\$s\n\nThis report was automatically generated by BookingX Business Intelligence.", 'bkx-business-intelligence' ),
			$report['title'] ?? __( 'Business Intelligence Report', 'bkx-business-intelligence' ),
			$report['period']['start'] ?? gmdate( 'Y-m-d' ),
			$report['period']['end'] ?? gmdate( 'Y-m-d' )
		);

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		$success = true;
		foreach ( $recipients as $email ) {
			if ( is_email( $email ) ) {
				$sent = wp_mail( $email, $subject, $message, $headers, array( $file_path ) );
				if ( ! $sent ) {
					$success = false;
				}
			}
		}

		return $success;
	}

	/**
	 * Clean up old export files.
	 *
	 * @param int $days Days to keep files.
	 * @return int Number of files deleted.
	 */
	public function cleanup_old_exports( $days = 7 ) {
		$deleted   = 0;
		$threshold = time() - ( $days * DAY_IN_SECONDS );

		$files = glob( $this->export_dir . '*' );

		foreach ( $files as $file ) {
			if ( is_file( $file ) && filemtime( $file ) < $threshold ) {
				if ( unlink( $file ) ) {
					$deleted++;
				}
			}
		}

		return $deleted;
	}

	/**
	 * Format value for display.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function format_value( $value ) {
		if ( is_numeric( $value ) ) {
			if ( strpos( (string) $value, '.' ) !== false ) {
				return number_format( (float) $value, 2 );
			}
			return number_format( (int) $value );
		}
		return (string) $value;
	}

	/**
	 * Format currency value.
	 *
	 * @param float $value Value.
	 * @return string
	 */
	private function format_currency( $value ) {
		$currency_symbol = get_option( 'bkx_currency_symbol', '$' );
		return $currency_symbol . number_format( (float) $value, 2 );
	}

	/**
	 * Mask email for privacy.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private function mask_email( $email ) {
		$parts = explode( '@', $email );
		if ( count( $parts ) !== 2 ) {
			return $email;
		}

		$name   = $parts[0];
		$domain = $parts[1];

		if ( strlen( $name ) > 2 ) {
			$name = substr( $name, 0, 2 ) . str_repeat( '*', strlen( $name ) - 2 );
		}

		return $name . '@' . $domain;
	}
}
