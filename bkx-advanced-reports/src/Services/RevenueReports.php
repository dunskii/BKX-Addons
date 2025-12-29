<?php
/**
 * Revenue Reports Service.
 *
 * @package BookingX\AdvancedReports
 * @since   1.0.0
 */

namespace BookingX\AdvancedReports\Services;

/**
 * RevenueReports class.
 *
 * Generates revenue-related reports and analytics.
 *
 * @since 1.0.0
 */
class RevenueReports {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get revenue summary.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_summary( $date_from, $date_to ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(pm_total.meta_value) as total_revenue,
					COUNT(DISTINCT p.ID) as total_bookings,
					AVG(pm_total.meta_value) as average_booking_value
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'total_price'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-completed', 'bkx-ack')
				AND pm_date.meta_value BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);

		return array(
			'total'   => (float) ( $result->total_revenue ?? 0 ),
			'count'   => (int) ( $result->total_bookings ?? 0 ),
			'average' => round( (float) ( $result->average_booking_value ?? 0 ), 2 ),
		);
	}

	/**
	 * Get full revenue report.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @param array  $filters Filters.
	 * @return array
	 */
	public function get_report( $date_from, $date_to, $filters = array() ) {
		return array(
			'summary'          => $this->get_summary( $date_from, $date_to ),
			'trend'            => $this->get_trend( $date_from, $date_to ),
			'by_service'       => $this->get_by_service( $date_from, $date_to ),
			'by_staff'         => $this->get_by_staff( $date_from, $date_to ),
			'by_payment_method' => $this->get_by_payment_method( $date_from, $date_to ),
			'projections'      => $this->get_projections( $date_from, $date_to ),
		);
	}

	/**
	 * Get revenue trend.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @param string $interval Interval (day, week, month).
	 * @return array
	 */
	public function get_trend( $date_from, $date_to, $interval = 'day' ) {
		global $wpdb;

		$date_format = match ( $interval ) {
			'week'  => '%Y-%u',
			'month' => '%Y-%m',
			default => '%Y-%m-%d',
		};

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE_FORMAT(pm_date.meta_value, %s) as period,
					SUM(pm_total.meta_value) as revenue,
					COUNT(DISTINCT p.ID) as bookings
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'total_price'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-completed', 'bkx-ack')
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY period
				ORDER BY period ASC",
				$date_format,
				$date_from,
				$date_to
			)
		);

		$labels = array();
		$data   = array();

		foreach ( $results as $row ) {
			$labels[] = $row->period;
			$data[]   = (float) $row->revenue;
		}

		return array(
			'labels' => $labels,
			'data'   => $data,
		);
	}

	/**
	 * Get revenue by service.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_by_service( $date_from, $date_to ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pm_base.meta_value as service_id,
					SUM(pm_total.meta_value) as revenue,
					COUNT(DISTINCT p.ID) as bookings
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'total_price'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_base ON p.ID = pm_base.post_id AND pm_base.meta_key = 'base_id'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-completed', 'bkx-ack')
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY pm_base.meta_value
				ORDER BY revenue DESC",
				$date_from,
				$date_to
			)
		);

		$data = array();

		foreach ( $results as $row ) {
			$service_name = get_the_title( $row->service_id ) ?: __( 'Unknown Service', 'bkx-advanced-reports' );

			$data[] = array(
				'service_id'   => (int) $row->service_id,
				'service_name' => $service_name,
				'revenue'      => (float) $row->revenue,
				'bookings'     => (int) $row->bookings,
			);
		}

		return $data;
	}

	/**
	 * Get revenue by staff.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_by_staff( $date_from, $date_to ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pm_seat.meta_value as staff_id,
					SUM(pm_total.meta_value) as revenue,
					COUNT(DISTINCT p.ID) as bookings
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'total_price'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_seat ON p.ID = pm_seat.post_id AND pm_seat.meta_key = 'seat_id'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-completed', 'bkx-ack')
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY pm_seat.meta_value
				ORDER BY revenue DESC",
				$date_from,
				$date_to
			)
		);

		$data = array();

		foreach ( $results as $row ) {
			$staff_name = get_the_title( $row->staff_id ) ?: __( 'Unknown Staff', 'bkx-advanced-reports' );

			$data[] = array(
				'staff_id'   => (int) $row->staff_id,
				'staff_name' => $staff_name,
				'revenue'    => (float) $row->revenue,
				'bookings'   => (int) $row->bookings,
			);
		}

		return $data;
	}

	/**
	 * Get revenue by payment method.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_by_payment_method( $date_from, $date_to ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					COALESCE(pm_method.meta_value, 'unknown') as payment_method,
					SUM(pm_total.meta_value) as revenue,
					COUNT(DISTINCT p.ID) as bookings
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'total_price'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_method ON p.ID = pm_method.post_id AND pm_method.meta_key = 'payment_method'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-completed', 'bkx-ack')
				AND pm_date.meta_value BETWEEN %s AND %s
				GROUP BY payment_method
				ORDER BY revenue DESC",
				$date_from,
				$date_to
			)
		);

		$data = array();

		foreach ( $results as $row ) {
			$data[] = array(
				'method'   => $row->payment_method,
				'revenue'  => (float) $row->revenue,
				'bookings' => (int) $row->bookings,
			);
		}

		return $data;
	}

	/**
	 * Get revenue projections.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_projections( $date_from, $date_to ) {
		$summary = $this->get_summary( $date_from, $date_to );

		// Calculate daily average.
		$days          = max( 1, ( strtotime( $date_to ) - strtotime( $date_from ) ) / DAY_IN_SECONDS );
		$daily_average = $summary['total'] / $days;

		// Get pending bookings revenue.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(pm_total.meta_value)
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'total_price'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-pending', 'bkx-ack')
				AND pm_date.meta_value >= %s",
				current_time( 'Y-m-d' )
			)
		);

		// Project for remaining month.
		$days_remaining_month = (int) gmdate( 't' ) - (int) gmdate( 'j' );
		$month_projection     = $summary['total'] + ( $daily_average * $days_remaining_month );

		// Project for year.
		$days_remaining_year = (int) ( strtotime( gmdate( 'Y-12-31' ) ) - time() ) / DAY_IN_SECONDS;
		$year_projection     = $summary['total'] + ( $daily_average * $days_remaining_year );

		return array(
			'daily_average'     => round( $daily_average, 2 ),
			'pending_revenue'   => (float) $pending,
			'month_projection'  => round( $month_projection, 2 ),
			'year_projection'   => round( $year_projection, 2 ),
		);
	}
}
