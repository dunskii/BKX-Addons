<?php
/**
 * Report Generator Service.
 *
 * @package BookingX\StaffAnalytics\Services
 * @since   1.0.0
 */

namespace BookingX\StaffAnalytics\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ReportGenerator Class.
 */
class ReportGenerator {

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
		$this->export_dir = $upload_dir['basedir'] . '/bkx-staff-reports/';

		if ( ! file_exists( $this->export_dir ) ) {
			wp_mkdir_p( $this->export_dir );

			// Protect directory.
			file_put_contents(
				$this->export_dir . '.htaccess',
				"Order deny,allow\nDeny from all\nAllow from env=allowed"
			);
		}
	}

	/**
	 * Generate export.
	 *
	 * @param int    $staff_id    Staff ID (0 for all).
	 * @param string $report_type Report type.
	 * @param string $format      Export format.
	 * @param string $start_date  Start date.
	 * @param string $end_date    End date.
	 * @return string|false Download URL or false.
	 */
	public function generate_export( $staff_id, $report_type, $format, $start_date, $end_date ) {
		$data = $this->gather_report_data( $staff_id, $report_type, $start_date, $end_date );

		if ( empty( $data ) ) {
			return false;
		}

		$filename = $this->generate_filename( $report_type, $staff_id, $format );

		switch ( $format ) {
			case 'csv':
				$result = $this->export_csv( $data, $filename, $report_type );
				break;

			case 'pdf':
				$result = $this->export_html( $data, $filename, $report_type ); // HTML fallback.
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
	 * Gather report data.
	 *
	 * @param int    $staff_id    Staff ID.
	 * @param string $report_type Report type.
	 * @param string $start_date  Start date.
	 * @param string $end_date    End date.
	 * @return array
	 */
	private function gather_report_data( $staff_id, $report_type, $start_date, $end_date ) {
		$metrics_service     = new PerformanceMetrics();
		$goals_service       = new GoalTracker();
		$reviews_service     = new ReviewManager();
		$time_service        = new TimeTracker();
		$leaderboard_service = new LeaderboardService();

		$data = array(
			'report_type' => $report_type,
			'staff_id'    => $staff_id,
			'period'      => array(
				'start' => $start_date,
				'end'   => $end_date,
			),
			'generated'   => current_time( 'mysql' ),
		);

		// Staff name.
		if ( $staff_id > 0 ) {
			$data['staff_name'] = get_the_title( $staff_id );
		} else {
			$data['staff_name'] = __( 'All Staff', 'bkx-staff-analytics' );
		}

		switch ( $report_type ) {
			case 'performance':
				$data['metrics'] = $metrics_service->get_staff_metrics( $staff_id, 'custom', $start_date, $end_date );
				if ( $staff_id > 0 ) {
					$data['rank'] = $leaderboard_service->get_staff_rank( $staff_id, 'revenue', 'custom' );
				}
				break;

			case 'goals':
				$data['goals'] = $goals_service->get_staff_goals( $staff_id );
				break;

			case 'reviews':
				$data['reviews'] = $reviews_service->get_staff_reviews( $staff_id, array( 'limit' => 100 ) );
				$data['summary'] = $reviews_service->get_rating_summary( $staff_id );
				break;

			case 'time':
				$data['logs']    = $time_service->get_time_logs(
					$staff_id,
					array(
						'start_date' => $start_date,
						'end_date'   => $end_date,
					)
				);
				$data['summary'] = $time_service->get_time_summary(
					$staff_id,
					array(
						'start_date' => $start_date,
						'end_date'   => $end_date,
					)
				);
				break;

			case 'comprehensive':
				$data['metrics'] = $metrics_service->get_staff_metrics( $staff_id, 'custom', $start_date, $end_date );
				if ( $staff_id > 0 ) {
					$data['goals']          = $goals_service->get_staff_goals( $staff_id );
					$data['reviews']        = $reviews_service->get_staff_reviews( $staff_id, array( 'limit' => 10 ) );
					$data['review_summary'] = $reviews_service->get_rating_summary( $staff_id );
					$data['time_summary']   = $time_service->get_time_summary(
						$staff_id,
						array(
							'start_date' => $start_date,
							'end_date'   => $end_date,
						)
					);
				}
				break;
		}

		return $data;
	}

	/**
	 * Export to CSV.
	 *
	 * @param array  $data        Report data.
	 * @param string $filename    Filename.
	 * @param string $report_type Report type.
	 * @return bool
	 */
	private function export_csv( $data, $filename, $report_type ) {
		$filepath = $this->export_dir . $filename;
		$handle   = fopen( $filepath, 'w' );

		if ( ! $handle ) {
			return false;
		}

		// Header.
		fputcsv( $handle, array( ucfirst( $report_type ) . ' Report' ) );
		fputcsv( $handle, array( 'Staff', $data['staff_name'] ) );
		fputcsv( $handle, array( 'Period', $data['period']['start'], 'to', $data['period']['end'] ) );
		fputcsv( $handle, array( 'Generated', $data['generated'] ) );
		fputcsv( $handle, array() );

		switch ( $report_type ) {
			case 'performance':
				$this->export_performance_csv( $handle, $data );
				break;

			case 'goals':
				$this->export_goals_csv( $handle, $data );
				break;

			case 'reviews':
				$this->export_reviews_csv( $handle, $data );
				break;

			case 'time':
				$this->export_time_csv( $handle, $data );
				break;

			case 'comprehensive':
				$this->export_comprehensive_csv( $handle, $data );
				break;
		}

		fclose( $handle );

		return file_exists( $filepath );
	}

	/**
	 * Export performance data to CSV.
	 *
	 * @param resource $handle File handle.
	 * @param array    $data   Report data.
	 */
	private function export_performance_csv( $handle, $data ) {
		fputcsv( $handle, array( 'Summary' ) );
		fputcsv( $handle, array( 'Total Bookings', $data['metrics']['summary']['total_bookings'] ) );
		fputcsv( $handle, array( 'Completed', $data['metrics']['summary']['completed_bookings'] ) );
		fputcsv( $handle, array( 'Cancelled', $data['metrics']['summary']['cancelled_bookings'] ) );
		fputcsv( $handle, array( 'No Shows', $data['metrics']['summary']['no_show_bookings'] ) );
		fputcsv( $handle, array( 'Total Revenue', number_format( $data['metrics']['summary']['total_revenue'], 2 ) ) );
		fputcsv( $handle, array( 'Total Hours', $data['metrics']['summary']['total_hours'] ) );
		fputcsv( $handle, array( 'Average Rating', $data['metrics']['summary']['avg_rating'] ) );
		fputcsv( $handle, array( 'Completion Rate', $data['metrics']['calculated']['completion_rate'] . '%' ) );
		fputcsv( $handle, array( 'Avg Booking Value', number_format( $data['metrics']['calculated']['avg_booking_value'], 2 ) ) );

		fputcsv( $handle, array() );
		fputcsv( $handle, array( 'Daily Breakdown' ) );
		fputcsv( $handle, array( 'Date', 'Bookings', 'Completed', 'Revenue', 'Hours' ) );

		foreach ( $data['metrics']['daily'] as $day ) {
			fputcsv(
				$handle,
				array(
					$day['metric_date'],
					$day['bookings'],
					$day['completed'],
					number_format( $day['revenue'], 2 ),
					$day['hours'],
				)
			);
		}
	}

	/**
	 * Export goals data to CSV.
	 *
	 * @param resource $handle File handle.
	 * @param array    $data   Report data.
	 */
	private function export_goals_csv( $handle, $data ) {
		fputcsv( $handle, array( 'Goal Type', 'Target', 'Current', 'Progress', 'Status', 'End Date' ) );

		foreach ( $data['goals'] as $goal ) {
			fputcsv(
				$handle,
				array(
					$goal['type_label'],
					$goal['target_value'],
					$goal['progress']['current_value'],
					$goal['progress']['percentage'] . '%',
					ucfirst( str_replace( '_', ' ', $goal['progress']['status'] ) ),
					$goal['end_date'],
				)
			);
		}
	}

	/**
	 * Export reviews data to CSV.
	 *
	 * @param resource $handle File handle.
	 * @param array    $data   Report data.
	 */
	private function export_reviews_csv( $handle, $data ) {
		// Summary.
		fputcsv( $handle, array( 'Average Rating', $data['summary']['avg_rating'] ) );
		fputcsv( $handle, array( 'Total Reviews', $data['summary']['total_reviews'] ) );
		fputcsv( $handle, array() );

		// Distribution.
		fputcsv( $handle, array( 'Rating Distribution' ) );
		for ( $i = 5; $i >= 1; $i-- ) {
			fputcsv( $handle, array( $i . ' Stars', $data['summary']['distribution'][ $i ] ) );
		}

		fputcsv( $handle, array() );
		fputcsv( $handle, array( 'Recent Reviews' ) );
		fputcsv( $handle, array( 'Date', 'Customer', 'Rating', 'Review' ) );

		foreach ( $data['reviews'] as $review ) {
			fputcsv(
				$handle,
				array(
					$review['reviewed_at'],
					$review['customer_name'],
					$review['rating'],
					$review['review_text'],
				)
			);
		}
	}

	/**
	 * Export time data to CSV.
	 *
	 * @param resource $handle File handle.
	 * @param array    $data   Report data.
	 */
	private function export_time_csv( $handle, $data ) {
		// Summary.
		fputcsv( $handle, array( 'Total Hours Worked', $data['summary']['total_hours'] ) );
		fputcsv( $handle, array( 'Days Worked', $data['summary']['days_worked'] ) );
		fputcsv( $handle, array( 'Average Hours/Day', $data['summary']['avg_hours_per_day'] ) );
		fputcsv( $handle, array( 'Total Breaks (min)', $data['summary']['total_break_minutes'] ) );

		fputcsv( $handle, array() );
		fputcsv( $handle, array( 'Time Logs' ) );
		fputcsv( $handle, array( 'Date', 'Clock In', 'Clock Out', 'Break (min)', 'Total Hours', 'Notes' ) );

		foreach ( $data['logs'] as $log ) {
			fputcsv(
				$handle,
				array(
					$log['log_date'],
					$log['clock_in'],
					$log['clock_out'],
					$log['break_minutes'],
					$log['total_hours'],
					$log['notes'],
				)
			);
		}
	}

	/**
	 * Export comprehensive data to CSV.
	 *
	 * @param resource $handle File handle.
	 * @param array    $data   Report data.
	 */
	private function export_comprehensive_csv( $handle, $data ) {
		// Performance section.
		fputcsv( $handle, array( '--- PERFORMANCE ---' ) );
		$this->export_performance_csv( $handle, $data );

		// Goals section.
		if ( ! empty( $data['goals'] ) ) {
			fputcsv( $handle, array() );
			fputcsv( $handle, array( '--- GOALS ---' ) );
			$this->export_goals_csv( $handle, $data );
		}

		// Reviews section.
		if ( ! empty( $data['review_summary'] ) ) {
			fputcsv( $handle, array() );
			fputcsv( $handle, array( '--- REVIEWS ---' ) );
			$data['summary'] = $data['review_summary'];
			$this->export_reviews_csv( $handle, $data );
		}

		// Time section.
		if ( ! empty( $data['time_summary'] ) ) {
			fputcsv( $handle, array() );
			fputcsv( $handle, array( '--- TIME TRACKING ---' ) );
			$data['summary'] = $data['time_summary'];
			$data['logs']    = array(); // Skip individual logs in comprehensive.
			$this->export_time_csv( $handle, $data );
		}
	}

	/**
	 * Export to HTML (PDF fallback).
	 *
	 * @param array  $data        Report data.
	 * @param string $filename    Filename.
	 * @param string $report_type Report type.
	 * @return bool
	 */
	private function export_html( $data, $filename, $report_type ) {
		$filepath = $this->export_dir . str_replace( '.pdf', '.html', $filename );

		$html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
		$html .= '<title>' . ucfirst( $report_type ) . ' Report - ' . esc_html( $data['staff_name'] ) . '</title>';
		$html .= '<style>
			body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
			h1 { color: #667eea; margin-bottom: 5px; }
			h2 { color: #555; border-bottom: 2px solid #667eea; padding-bottom: 5px; margin-top: 30px; }
			.meta { color: #666; margin-bottom: 20px; }
			table { width: 100%; border-collapse: collapse; margin: 15px 0; }
			th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
			th { background: #f5f5f5; font-weight: 600; }
			.metric { display: inline-block; background: #f8f9fa; padding: 15px; margin: 5px; border-radius: 4px; text-align: center; min-width: 120px; }
			.metric-value { font-size: 24px; font-weight: 700; color: #667eea; }
			.metric-label { font-size: 12px; color: #666; }
			.rating-bar { background: #e9ecef; border-radius: 4px; height: 20px; }
			.rating-fill { background: #ffc107; height: 100%; border-radius: 4px; }
		</style></head><body>';

		$html .= '<h1>' . ucfirst( $report_type ) . ' Report</h1>';
		$html .= '<div class="meta">';
		$html .= '<strong>Staff:</strong> ' . esc_html( $data['staff_name'] ) . '<br>';
		$html .= '<strong>Period:</strong> ' . esc_html( $data['period']['start'] ) . ' to ' . esc_html( $data['period']['end'] ) . '<br>';
		$html .= '<strong>Generated:</strong> ' . esc_html( $data['generated'] );
		$html .= '</div>';

		// Add content based on report type.
		if ( isset( $data['metrics'] ) ) {
			$html .= $this->render_performance_html( $data['metrics'] );
		}

		if ( ! empty( $data['goals'] ) ) {
			$html .= $this->render_goals_html( $data['goals'] );
		}

		if ( isset( $data['summary'] ) || isset( $data['review_summary'] ) ) {
			$summary = $data['review_summary'] ?? $data['summary'];
			$html   .= $this->render_reviews_html( $summary, $data['reviews'] ?? array() );
		}

		$html .= '</body></html>';

		return file_put_contents( $filepath, $html ) !== false;
	}

	/**
	 * Render performance HTML.
	 *
	 * @param array $metrics Metrics data.
	 * @return string
	 */
	private function render_performance_html( $metrics ) {
		$html = '<h2>Performance Summary</h2>';
		$html .= '<div class="metrics">';

		$summary_items = array(
			'Total Bookings'  => $metrics['summary']['total_bookings'],
			'Completed'       => $metrics['summary']['completed_bookings'],
			'Revenue'         => '$' . number_format( $metrics['summary']['total_revenue'], 2 ),
			'Hours Worked'    => $metrics['summary']['total_hours'],
			'Avg Rating'      => $metrics['summary']['avg_rating'] . '/5',
			'Completion Rate' => $metrics['calculated']['completion_rate'] . '%',
		);

		foreach ( $summary_items as $label => $value ) {
			$html .= '<div class="metric">';
			$html .= '<div class="metric-value">' . esc_html( $value ) . '</div>';
			$html .= '<div class="metric-label">' . esc_html( $label ) . '</div>';
			$html .= '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render goals HTML.
	 *
	 * @param array $goals Goals data.
	 * @return string
	 */
	private function render_goals_html( $goals ) {
		$html = '<h2>Goals</h2>';
		$html .= '<table><thead><tr><th>Goal</th><th>Target</th><th>Progress</th><th>Status</th></tr></thead><tbody>';

		foreach ( $goals as $goal ) {
			$status_class = $goal['progress']['status'] === 'achieved' ? 'color: green;' : ( $goal['progress']['status'] === 'behind' ? 'color: red;' : '' );
			$html        .= '<tr>';
			$html        .= '<td>' . esc_html( $goal['type_label'] ) . '</td>';
			$html        .= '<td>' . esc_html( $goal['target_value'] ) . '</td>';
			$html        .= '<td>' . esc_html( $goal['progress']['percentage'] ) . '%</td>';
			$html        .= '<td style="' . $status_class . '">' . ucfirst( str_replace( '_', ' ', $goal['progress']['status'] ) ) . '</td>';
			$html        .= '</tr>';
		}

		$html .= '</tbody></table>';

		return $html;
	}

	/**
	 * Render reviews HTML.
	 *
	 * @param array $summary Summary data.
	 * @param array $reviews Reviews data.
	 * @return string
	 */
	private function render_reviews_html( $summary, $reviews ) {
		$html = '<h2>Reviews</h2>';
		$html .= '<div class="metrics">';
		$html .= '<div class="metric"><div class="metric-value">' . esc_html( $summary['avg_rating'] ) . '</div><div class="metric-label">Average Rating</div></div>';
		$html .= '<div class="metric"><div class="metric-value">' . esc_html( $summary['total_reviews'] ) . '</div><div class="metric-label">Total Reviews</div></div>';
		$html .= '</div>';

		if ( ! empty( $reviews ) ) {
			$html .= '<table><thead><tr><th>Date</th><th>Customer</th><th>Rating</th><th>Review</th></tr></thead><tbody>';
			foreach ( array_slice( $reviews, 0, 10 ) as $review ) {
				$html .= '<tr>';
				$html .= '<td>' . esc_html( $review['reviewed_at'] ) . '</td>';
				$html .= '<td>' . esc_html( $review['customer_name'] ) . '</td>';
				$html .= '<td>' . str_repeat( '★', $review['rating'] ) . str_repeat( '☆', 5 - $review['rating'] ) . '</td>';
				$html .= '<td>' . esc_html( substr( $review['review_text'], 0, 100 ) ) . '</td>';
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';
		}

		return $html;
	}

	/**
	 * Generate filename.
	 *
	 * @param string $report_type Report type.
	 * @param int    $staff_id    Staff ID.
	 * @param string $format      Format.
	 * @return string
	 */
	private function generate_filename( $report_type, $staff_id, $format ) {
		$staff_slug = $staff_id > 0 ? sanitize_title( get_the_title( $staff_id ) ) : 'all-staff';

		return sprintf(
			'bkx-staff-%s-%s-%s.%s',
			$report_type,
			$staff_slug,
			gmdate( 'Y-m-d-His' ),
			$format
		);
	}

	/**
	 * Get download URL.
	 *
	 * @param string $filename Filename.
	 * @return string
	 */
	private function get_download_url( $filename ) {
		$token = wp_generate_password( 32, false );

		set_transient( 'bkx_staff_export_' . $token, $filename, HOUR_IN_SECONDS );

		return add_query_arg(
			array( 'bkx_staff_download' => $token ),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Handle download request.
	 */
	public static function handle_download() {
		if ( ! isset( $_GET['bkx_staff_download'] ) ) {
			return;
		}

		$token    = sanitize_text_field( wp_unslash( $_GET['bkx_staff_download'] ) );
		$filename = get_transient( 'bkx_staff_export_' . $token );

		if ( ! $filename ) {
			wp_die( 'Export expired or invalid.' );
		}

		$upload_dir = wp_upload_dir();
		$filepath   = $upload_dir['basedir'] . '/bkx-staff-reports/' . $filename;

		if ( ! file_exists( $filepath ) ) {
			wp_die( 'File not found.' );
		}

		$ext           = pathinfo( $filename, PATHINFO_EXTENSION );
		$content_types = array(
			'csv'  => 'text/csv',
			'pdf'  => 'application/pdf',
			'html' => 'text/html',
		);

		header( 'Content-Type: ' . ( $content_types[ $ext ] ?? 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $filepath ) );

		readfile( $filepath );

		delete_transient( 'bkx_staff_export_' . $token );

		exit;
	}
}
