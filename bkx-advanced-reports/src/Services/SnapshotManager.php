<?php
/**
 * Snapshot Manager Service.
 *
 * @package BookingX\AdvancedReports
 * @since   1.0.0
 */

namespace BookingX\AdvancedReports\Services;

/**
 * SnapshotManager class.
 *
 * Manages report snapshots for caching and historical data.
 *
 * @since 1.0.0
 */
class SnapshotManager {

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
		$this->table    = $wpdb->prefix . 'bkx_report_snapshots';
	}

	/**
	 * Generate daily snapshots.
	 *
	 * @since 1.0.0
	 */
	public function generate_daily() {
		$yesterday = gmdate( 'Y-m-d', strtotime( '-1 day' ) );

		// Generate snapshots for each report type.
		$report_types = array( 'revenue', 'bookings', 'staff', 'customers' );

		foreach ( $report_types as $type ) {
			$this->create_snapshot( $type, $yesterday, 'day' );
		}

		// Check if we need weekly/monthly snapshots.
		$day_of_week = (int) gmdate( 'N' ); // 1 = Monday, 7 = Sunday.
		$day_of_month = (int) gmdate( 'j' );

		if ( 1 === $day_of_week ) {
			// It's Monday, create weekly snapshots.
			$week_start = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
			foreach ( $report_types as $type ) {
				$this->create_snapshot( $type, $week_start, 'week' );
			}
		}

		if ( 1 === $day_of_month ) {
			// First of month, create monthly snapshots.
			$month_start = gmdate( 'Y-m-d', strtotime( 'first day of last month' ) );
			foreach ( $report_types as $type ) {
				$this->create_snapshot( $type, $month_start, 'month' );
			}
		}

		// Cleanup old snapshots.
		$this->cleanup_old_snapshots();
	}

	/**
	 * Create a snapshot.
	 *
	 * @since 1.0.0
	 *
	 * @param string $report_type Report type.
	 * @param string $date Snapshot date.
	 * @param string $period_type Period type (day, week, month).
	 * @return int|\WP_Error Snapshot ID or error.
	 */
	public function create_snapshot( $report_type, $date, $period_type = 'day' ) {
		global $wpdb;

		// Calculate date range based on period type.
		switch ( $period_type ) {
			case 'week':
				$date_from = $date;
				$date_to   = gmdate( 'Y-m-d', strtotime( $date . ' +6 days' ) );
				break;

			case 'month':
				$date_from = $date;
				$date_to   = gmdate( 'Y-m-d', strtotime( 'last day of ' . $date ) );
				break;

			default:
				$date_from = $date;
				$date_to   = $date;
		}

		// Get report data.
		$data = $this->get_report_data( $report_type, $date_from, $date_to );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Insert or update snapshot.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE report_type = %s AND snapshot_date = %s AND period_type = %s",
				$this->table,
				$report_type,
				$date,
				$period_type
			)
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->table,
				array( 'data' => wp_json_encode( $data ) ),
				array( 'id' => $existing ),
				array( '%s' ),
				array( '%d' )
			);
			return (int) $existing;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$this->table,
			array(
				'report_type'   => $report_type,
				'snapshot_date' => $date,
				'period_type'   => $period_type,
				'data'          => wp_json_encode( $data ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Get report data for snapshot.
	 *
	 * @since 1.0.0
	 *
	 * @param string $report_type Report type.
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array|\WP_Error
	 */
	private function get_report_data( $report_type, $date_from, $date_to ) {
		switch ( $report_type ) {
			case 'revenue':
				$service = new RevenueReports( $this->settings );
				return $service->get_summary( $date_from, $date_to );

			case 'bookings':
				$service = new BookingReports( $this->settings );
				return $service->get_summary( $date_from, $date_to );

			case 'staff':
				$service = new StaffReports( $this->settings );
				return $service->get_summary( $date_from, $date_to );

			case 'customers':
				$service = new CustomerReports( $this->settings );
				return $service->get_summary( $date_from, $date_to );

			default:
				return new \WP_Error( 'invalid_type', __( 'Invalid report type.', 'bkx-advanced-reports' ) );
		}
	}

	/**
	 * Get snapshot.
	 *
	 * @since 1.0.0
	 *
	 * @param string $report_type Report type.
	 * @param string $date Snapshot date.
	 * @param string $period_type Period type.
	 * @return array|null
	 */
	public function get_snapshot( $report_type, $date, $period_type = 'day' ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$snapshot = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT data FROM %i WHERE report_type = %s AND snapshot_date = %s AND period_type = %s",
				$this->table,
				$report_type,
				$date,
				$period_type
			)
		);

		return $snapshot ? json_decode( $snapshot, true ) : null;
	}

	/**
	 * Get snapshots for a date range.
	 *
	 * @since 1.0.0
	 *
	 * @param string $report_type Report type.
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @param string $period_type Period type.
	 * @return array
	 */
	public function get_snapshots( $report_type, $date_from, $date_to, $period_type = 'day' ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT snapshot_date, data FROM %i
				WHERE report_type = %s
				AND period_type = %s
				AND snapshot_date BETWEEN %s AND %s
				ORDER BY snapshot_date ASC",
				$this->table,
				$report_type,
				$period_type,
				$date_from,
				$date_to
			)
		);

		$snapshots = array();

		foreach ( $results as $row ) {
			$snapshots[ $row->snapshot_date ] = json_decode( $row->data, true );
		}

		return $snapshots;
	}

	/**
	 * Get historical comparison.
	 *
	 * @since 1.0.0
	 *
	 * @param string $report_type Report type.
	 * @param string $period_type Period type.
	 * @param int    $periods Number of periods to compare.
	 * @return array
	 */
	public function get_historical_comparison( $report_type, $period_type = 'month', $periods = 12 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT snapshot_date, data FROM %i
				WHERE report_type = %s
				AND period_type = %s
				ORDER BY snapshot_date DESC
				LIMIT %d",
				$this->table,
				$report_type,
				$period_type,
				$periods
			)
		);

		$data = array();

		foreach ( array_reverse( $results ) as $row ) {
			$snapshot_data = json_decode( $row->data, true );

			$data[] = array(
				'date' => $row->snapshot_date,
				'data' => $snapshot_data,
			);
		}

		return $data;
	}

	/**
	 * Cleanup old snapshots.
	 *
	 * @since 1.0.0
	 */
	public function cleanup_old_snapshots() {
		global $wpdb;

		$retention_days = $this->settings['snapshot_retention_days'] ?? 365;
		$cutoff_date    = gmdate( 'Y-m-d', strtotime( "-{$retention_days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE snapshot_date < %s",
				$this->table,
				$cutoff_date
			)
		);
	}
}
