<?php
/**
 * Export Manager Service.
 *
 * @package BookingX\AdvancedReports
 * @since   1.0.0
 */

namespace BookingX\AdvancedReports\Services;

/**
 * ExportManager class.
 *
 * Handles report exports to CSV, XLSX, and PDF.
 *
 * @since 1.0.0
 */
class ExportManager {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		global $wpdb;
		$this->settings = $settings;
		$this->table    = $wpdb->prefix . 'bkx_report_exports';
	}

	/**
	 * Create an export job.
	 *
	 * @since 1.0.0
	 *
	 * @param string $report_type Report type.
	 * @param string $format Export format (csv, xlsx, pdf).
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @param array  $filters Filters.
	 * @return int|\WP_Error Export ID or error.
	 */
	public function create_export( $report_type, $format, $date_from, $date_to, $filters = array() ) {
		global $wpdb;

		$valid_formats = array( 'csv', 'xlsx', 'pdf' );
		if ( ! in_array( $format, $valid_formats, true ) ) {
			return new \WP_Error( 'invalid_format', __( 'Invalid export format.', 'bkx-advanced-reports' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			array(
				'user_id'       => get_current_user_id(),
				'report_type'   => $report_type,
				'export_format' => $format,
				'status'        => 'pending',
			),
			array( '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create export.', 'bkx-advanced-reports' ) );
		}

		$export_id = $wpdb->insert_id;

		// Store export parameters as meta.
		update_option(
			"bkx_export_{$export_id}_params",
			array(
				'date_from' => $date_from,
				'date_to'   => $date_to,
				'filters'   => $filters,
			)
		);

		return $export_id;
	}

	/**
	 * Process an export.
	 *
	 * @since 1.0.0
	 *
	 * @param int $export_id Export ID.
	 * @return bool|\WP_Error
	 */
	public function process_export( $export_id ) {
		global $wpdb;

		// Get export record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$export = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				$this->table,
				$export_id
			)
		);

		if ( ! $export ) {
			return new \WP_Error( 'not_found', __( 'Export not found.', 'bkx-advanced-reports' ) );
		}

		// Update status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array(
				'status'     => 'processing',
				'started_at' => current_time( 'mysql' ),
			),
			array( 'id' => $export_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Get parameters.
		$params = get_option( "bkx_export_{$export_id}_params", array() );

		try {
			// Get report data.
			$data = $this->get_export_data( $export->report_type, $params );

			if ( is_wp_error( $data ) ) {
				throw new \Exception( $data->get_error_message() );
			}

			// Generate file.
			$file_path = $this->generate_file( $export->report_type, $export->export_format, $data );

			if ( is_wp_error( $file_path ) ) {
				throw new \Exception( $file_path->get_error_message() );
			}

			// Update export record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->table,
				array(
					'status'        => 'completed',
					'file_path'     => $file_path,
					'rows_exported' => count( $data['rows'] ?? $data ),
					'completed_at'  => current_time( 'mysql' ),
				),
				array( 'id' => $export_id ),
				array( '%s', '%s', '%d', '%s' ),
				array( '%d' )
			);

			// Cleanup params.
			delete_option( "bkx_export_{$export_id}_params" );

			return true;

		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->table,
				array(
					'status'        => 'failed',
					'error_message' => $e->getMessage(),
				),
				array( 'id' => $export_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			return new \WP_Error( 'export_failed', $e->getMessage() );
		}
	}

	/**
	 * Get export data.
	 *
	 * @since 1.0.0
	 *
	 * @param string $report_type Report type.
	 * @param array  $params Export parameters.
	 * @return array|\WP_Error
	 */
	private function get_export_data( $report_type, $params ) {
		$date_from = $params['date_from'] ?? gmdate( 'Y-m-d', strtotime( '-30 days' ) );
		$date_to   = $params['date_to'] ?? current_time( 'Y-m-d' );
		$filters   = $params['filters'] ?? array();

		switch ( $report_type ) {
			case 'revenue':
				$service = new RevenueReports( $this->settings );
				$data    = $service->get_report( $date_from, $date_to, $filters );
				return $this->format_revenue_export( $data );

			case 'bookings':
				$service = new BookingReports( $this->settings );
				$data    = $service->get_report( $date_from, $date_to, $filters );
				return $this->format_bookings_export( $data, $date_from, $date_to );

			case 'staff':
				$service = new StaffReports( $this->settings );
				$data    = $service->get_report( $date_from, $date_to, $filters );
				return $this->format_staff_export( $data );

			case 'customers':
				$service = new CustomerReports( $this->settings );
				$data    = $service->get_report( $date_from, $date_to, $filters );
				return $this->format_customers_export( $data );

			default:
				return new \WP_Error( 'invalid_type', __( 'Invalid report type.', 'bkx-advanced-reports' ) );
		}
	}

	/**
	 * Format revenue data for export.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Report data.
	 * @return array
	 */
	private function format_revenue_export( $data ) {
		$headers = array(
			__( 'Service', 'bkx-advanced-reports' ),
			__( 'Bookings', 'bkx-advanced-reports' ),
			__( 'Revenue', 'bkx-advanced-reports' ),
		);

		$rows = array();

		foreach ( $data['by_service'] ?? array() as $service ) {
			$rows[] = array(
				$service['service_name'],
				$service['bookings'],
				$service['revenue'],
			);
		}

		return array(
			'title'   => __( 'Revenue Report', 'bkx-advanced-reports' ),
			'headers' => $headers,
			'rows'    => $rows,
			'summary' => $data['summary'] ?? array(),
		);
	}

	/**
	 * Format bookings data for export.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data Report data.
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	private function format_bookings_export( $data, $date_from, $date_to ) {
		global $wpdb;

		// Get actual booking records.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.ID,
					p.post_status,
					pm_date.meta_value as booking_date,
					pm_time.meta_value as booking_time,
					pm_name.meta_value as customer_name,
					pm_email.meta_value as customer_email,
					pm_base.meta_value as service_id,
					pm_seat.meta_value as staff_id,
					pm_total.meta_value as total
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'booking_time'
				LEFT JOIN {$wpdb->postmeta} pm_name ON p.ID = pm_name.post_id AND pm_name.meta_key = 'customer_name'
				LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'customer_email'
				LEFT JOIN {$wpdb->postmeta} pm_base ON p.ID = pm_base.post_id AND pm_base.meta_key = 'base_id'
				LEFT JOIN {$wpdb->postmeta} pm_seat ON p.ID = pm_seat.post_id AND pm_seat.meta_key = 'seat_id'
				LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'total_price'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s
				ORDER BY pm_date.meta_value ASC, pm_time.meta_value ASC",
				$date_from,
				$date_to
			)
		);

		$headers = array(
			__( 'Booking ID', 'bkx-advanced-reports' ),
			__( 'Date', 'bkx-advanced-reports' ),
			__( 'Time', 'bkx-advanced-reports' ),
			__( 'Customer', 'bkx-advanced-reports' ),
			__( 'Email', 'bkx-advanced-reports' ),
			__( 'Service', 'bkx-advanced-reports' ),
			__( 'Staff', 'bkx-advanced-reports' ),
			__( 'Status', 'bkx-advanced-reports' ),
			__( 'Total', 'bkx-advanced-reports' ),
		);

		$status_labels = array(
			'bkx-pending'   => __( 'Pending', 'bkx-advanced-reports' ),
			'bkx-ack'       => __( 'Confirmed', 'bkx-advanced-reports' ),
			'bkx-completed' => __( 'Completed', 'bkx-advanced-reports' ),
			'bkx-cancelled' => __( 'Cancelled', 'bkx-advanced-reports' ),
			'bkx-missed'    => __( 'Missed', 'bkx-advanced-reports' ),
		);

		$rows = array();

		foreach ( $bookings as $booking ) {
			$rows[] = array(
				$booking->ID,
				$booking->booking_date,
				$booking->booking_time,
				$booking->customer_name,
				$booking->customer_email,
				get_the_title( $booking->service_id ) ?: '',
				get_the_title( $booking->staff_id ) ?: '',
				$status_labels[ $booking->post_status ] ?? $booking->post_status,
				$booking->total,
			);
		}

		return array(
			'title'   => __( 'Bookings Report', 'bkx-advanced-reports' ),
			'headers' => $headers,
			'rows'    => $rows,
			'summary' => $data['summary'] ?? array(),
		);
	}

	/**
	 * Format staff data for export.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Report data.
	 * @return array
	 */
	private function format_staff_export( $data ) {
		$headers = array(
			__( 'Staff Name', 'bkx-advanced-reports' ),
			__( 'Total Bookings', 'bkx-advanced-reports' ),
			__( 'Completed', 'bkx-advanced-reports' ),
			__( 'Cancelled', 'bkx-advanced-reports' ),
			__( 'Completion Rate', 'bkx-advanced-reports' ),
			__( 'Total Revenue', 'bkx-advanced-reports' ),
			__( 'Avg Booking Value', 'bkx-advanced-reports' ),
		);

		$rows = array();

		foreach ( $data['performance'] ?? array() as $staff ) {
			$rows[] = array(
				$staff['staff_name'],
				$staff['total_bookings'],
				$staff['completed'],
				$staff['cancelled'],
				$staff['completion_rate'] . '%',
				$staff['total_revenue'],
				$staff['avg_booking_value'],
			);
		}

		return array(
			'title'   => __( 'Staff Performance Report', 'bkx-advanced-reports' ),
			'headers' => $headers,
			'rows'    => $rows,
			'summary' => $data['summary'] ?? array(),
		);
	}

	/**
	 * Format customers data for export.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Report data.
	 * @return array
	 */
	private function format_customers_export( $data ) {
		$headers = array(
			__( 'Customer Name', 'bkx-advanced-reports' ),
			__( 'Email', 'bkx-advanced-reports' ),
			__( 'Bookings', 'bkx-advanced-reports' ),
			__( 'Total Spent', 'bkx-advanced-reports' ),
		);

		$rows = array();

		foreach ( $data['top_customers'] ?? array() as $customer ) {
			$rows[] = array(
				$customer['name'],
				$customer['email'],
				$customer['bookings'],
				$customer['total_spent'],
			);
		}

		return array(
			'title'   => __( 'Customer Report', 'bkx-advanced-reports' ),
			'headers' => $headers,
			'rows'    => $rows,
			'summary' => $data['summary'] ?? array(),
		);
	}

	/**
	 * Generate export file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $report_type Report type.
	 * @param string $format File format.
	 * @param array  $data Export data.
	 * @return string|\WP_Error File path or error.
	 */
	private function generate_file( $report_type, $format, $data ) {
		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/bkx-exports/' . gmdate( 'Y/m' );

		// Create directory.
		if ( ! file_exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir );

			// Security.
			file_put_contents( $export_dir . '/.htaccess', 'deny from all' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$filename = sanitize_file_name( $report_type . '-' . gmdate( 'Y-m-d-His' ) . '.' . $format );
		$filepath = $export_dir . '/' . $filename;

		switch ( $format ) {
			case 'csv':
				return $this->generate_csv( $filepath, $data );

			case 'xlsx':
				return $this->generate_xlsx( $filepath, $data );

			case 'pdf':
				return $this->generate_pdf( $filepath, $data );

			default:
				return new \WP_Error( 'invalid_format', __( 'Invalid format.', 'bkx-advanced-reports' ) );
		}
	}

	/**
	 * Generate CSV file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $filepath File path.
	 * @param array  $data Export data.
	 * @return string|\WP_Error
	 */
	private function generate_csv( $filepath, $data ) {
		$handle = fopen( $filepath, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( ! $handle ) {
			return new \WP_Error( 'file_error', __( 'Could not create file.', 'bkx-advanced-reports' ) );
		}

		// BOM for UTF-8.
		fwrite( $handle, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		// Headers.
		fputcsv( $handle, $data['headers'] );

		// Rows.
		foreach ( $data['rows'] as $row ) {
			fputcsv( $handle, $row );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $filepath;
	}

	/**
	 * Generate XLSX file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $filepath File path.
	 * @param array  $data Export data.
	 * @return string|\WP_Error
	 */
	private function generate_xlsx( $filepath, $data ) {
		// For XLSX, we'll generate CSV as fallback.
		// Full XLSX support would require PhpSpreadsheet library.
		$csv_path = str_replace( '.xlsx', '.csv', $filepath );
		return $this->generate_csv( $csv_path, $data );
	}

	/**
	 * Generate PDF file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $filepath File path.
	 * @param array  $data Export data.
	 * @return string|\WP_Error
	 */
	private function generate_pdf( $filepath, $data ) {
		// Generate HTML for PDF.
		$html = $this->generate_pdf_html( $data );

		// Save as HTML (PDF would require DOMPDF or similar).
		$html_path = str_replace( '.pdf', '.html', $filepath );
		file_put_contents( $html_path, $html ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		return $html_path;
	}

	/**
	 * Generate HTML for PDF.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Export data.
	 * @return string
	 */
	private function generate_pdf_html( $data ) {
		$html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
		$html .= '<title>' . esc_html( $data['title'] ) . '</title>';
		$html .= '<style>
			body { font-family: Arial, sans-serif; font-size: 12px; }
			h1 { color: #333; }
			table { width: 100%; border-collapse: collapse; margin-top: 20px; }
			th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
			th { background: #f5f5f5; font-weight: bold; }
			tr:nth-child(even) { background: #fafafa; }
		</style></head><body>';

		$html .= '<h1>' . esc_html( $data['title'] ) . '</h1>';
		$html .= '<p>' . esc_html__( 'Generated:', 'bkx-advanced-reports' ) . ' ' . current_time( 'Y-m-d H:i:s' ) . '</p>';

		$html .= '<table><thead><tr>';

		foreach ( $data['headers'] as $header ) {
			$html .= '<th>' . esc_html( $header ) . '</th>';
		}

		$html .= '</tr></thead><tbody>';

		foreach ( $data['rows'] as $row ) {
			$html .= '<tr>';
			foreach ( $row as $cell ) {
				$html .= '<td>' . esc_html( $cell ) . '</td>';
			}
			$html .= '</tr>';
		}

		$html .= '</tbody></table></body></html>';

		return $html;
	}

	/**
	 * Get export status.
	 *
	 * @since 1.0.0
	 *
	 * @param int $export_id Export ID.
	 * @return array|\WP_Error
	 */
	public function get_status( $export_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$export = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				$this->table,
				$export_id
			)
		);

		if ( ! $export ) {
			return new \WP_Error( 'not_found', __( 'Export not found.', 'bkx-advanced-reports' ) );
		}

		return array(
			'id'            => $export->id,
			'status'        => $export->status,
			'format'        => $export->export_format,
			'rows_exported' => $export->rows_exported,
			'file_path'     => $export->file_path,
			'error_message' => $export->error_message,
			'started_at'    => $export->started_at,
			'completed_at'  => $export->completed_at,
		);
	}

	/**
	 * Download export file.
	 *
	 * @since 1.0.0
	 *
	 * @param int $export_id Export ID.
	 */
	public function download( $export_id ) {
		global $wpdb;

		// Verify ownership.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$export = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d AND user_id = %d",
				$this->table,
				$export_id,
				get_current_user_id()
			)
		);

		if ( ! $export || 'completed' !== $export->status || ! file_exists( $export->file_path ) ) {
			wp_die( esc_html__( 'Export not found.', 'bkx-advanced-reports' ) );
		}

		$filename = basename( $export->file_path );
		$mime     = match ( $export->export_format ) {
			'csv'  => 'text/csv',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'pdf'  => 'application/pdf',
			default => 'application/octet-stream',
		};

		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $export->file_path ) );

		readfile( $export->file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Cleanup old exports.
	 *
	 * @since 1.0.0
	 */
	public function cleanup_old_exports() {
		global $wpdb;

		$retention_days = $this->settings['export_retention_days'] ?? 30;
		$cutoff_date    = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		// Get old exports.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$old_exports = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, file_path FROM %i WHERE created_at < %s",
				$this->table,
				$cutoff_date
			)
		);

		foreach ( $old_exports as $export ) {
			// Delete file.
			if ( $export->file_path && file_exists( $export->file_path ) ) {
				wp_delete_file( $export->file_path );
			}

			// Delete record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $this->table, array( 'id' => $export->id ), array( '%d' ) );
		}
	}
}
