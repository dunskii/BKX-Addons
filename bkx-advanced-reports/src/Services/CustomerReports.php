<?php
/**
 * Customer Reports Service.
 *
 * @package BookingX\AdvancedReports
 * @since   1.0.0
 */

namespace BookingX\AdvancedReports\Services;

/**
 * CustomerReports class.
 *
 * Generates customer-related reports and analytics.
 *
 * @since 1.0.0
 */
class CustomerReports {

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
	 * Get full customer report.
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
			'new_vs_returning' => $this->get_new_vs_returning( $date_from, $date_to ),
			'top_customers'    => $this->get_top_customers( $date_from, $date_to ),
			'lifetime_value'   => $this->get_lifetime_value_distribution(),
			'retention'        => $this->get_retention_metrics( $date_from, $date_to ),
			'acquisition'      => $this->get_acquisition_trend( $date_from, $date_to ),
		);
	}

	/**
	 * Get customer summary.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_summary( $date_from, $date_to ) {
		global $wpdb;

		// Get unique customers in period.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(DISTINCT pm_email.meta_value) as unique_customers,
					COUNT(DISTINCT p.ID) as total_bookings,
					SUM(pm_total.meta_value) as total_revenue
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'customer_email'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'total_price'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);

		$unique_customers = (int) ( $result->unique_customers ?? 0 );
		$total_bookings   = (int) ( $result->total_bookings ?? 0 );
		$total_revenue    = (float) ( $result->total_revenue ?? 0 );

		return array(
			'unique_customers'       => $unique_customers,
			'total_bookings'         => $total_bookings,
			'total_revenue'          => $total_revenue,
			'avg_bookings_per_customer' => $unique_customers > 0 ? round( $total_bookings / $unique_customers, 2 ) : 0,
			'avg_revenue_per_customer'  => $unique_customers > 0 ? round( $total_revenue / $unique_customers, 2 ) : 0,
		);
	}

	/**
	 * Get new vs returning customers.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_new_vs_returning( $date_from, $date_to ) {
		global $wpdb;

		// Get customers who had bookings before the period.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pm_email.meta_value as email,
					MIN(pm_date.meta_value) as first_booking
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'customer_email'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value <= %s
				GROUP BY pm_email.meta_value",
				$date_to
			)
		);

		$new_customers       = 0;
		$returning_customers = 0;

		foreach ( $results as $customer ) {
			if ( $customer->first_booking >= $date_from ) {
				$new_customers++;
			} else {
				// Check if they had a booking in this period.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$has_booking = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*)
						FROM {$wpdb->posts} p
						LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'customer_email'
						LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
						WHERE p.post_type = 'bkx_booking'
						AND pm_email.meta_value = %s
						AND pm_date.meta_value BETWEEN %s AND %s",
						$customer->email,
						$date_from,
						$date_to
					)
				);

				if ( $has_booking > 0 ) {
					$returning_customers++;
				}
			}
		}

		$total = $new_customers + $returning_customers;

		return array(
			'new'              => $new_customers,
			'returning'        => $returning_customers,
			'new_percentage'   => $total > 0 ? round( ( $new_customers / $total ) * 100, 1 ) : 0,
			'returning_percentage' => $total > 0 ? round( ( $returning_customers / $total ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Get top customers.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @param int    $limit Limit.
	 * @return array
	 */
	public function get_top_customers( $date_from, $date_to, $limit = 10 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pm_email.meta_value as email,
					pm_name.meta_value as name,
					COUNT(DISTINCT p.ID) as bookings,
					SUM(pm_total.meta_value) as total_spent
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'customer_email'
				LEFT JOIN {$wpdb->postmeta} pm_name ON p.ID = pm_name.post_id AND pm_name.meta_key = 'customer_name'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'total_price'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s
				AND p.post_status IN ('bkx-completed', 'bkx-ack')
				GROUP BY pm_email.meta_value
				ORDER BY total_spent DESC
				LIMIT %d",
				$date_from,
				$date_to,
				$limit
			)
		);

		$data = array();

		foreach ( $results as $row ) {
			$data[] = array(
				'email'       => $row->email,
				'name'        => $row->name ?: __( 'Unknown', 'bkx-advanced-reports' ),
				'bookings'    => (int) $row->bookings,
				'total_spent' => (float) $row->total_spent,
			);
		}

		return $data;
	}

	/**
	 * Get lifetime value distribution.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_lifetime_value_distribution() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			"SELECT
				pm_email.meta_value as email,
				SUM(pm_total.meta_value) as lifetime_value
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'customer_email'
			LEFT JOIN {$wpdb->postmeta} pm_total ON p.ID = pm_total.post_id AND pm_total.meta_key = 'total_price'
			WHERE p.post_type = 'bkx_booking'
			AND p.post_status IN ('bkx-completed', 'bkx-ack')
			GROUP BY pm_email.meta_value"
		);

		// Define LTV buckets.
		$buckets = array(
			'0-50'     => 0,
			'51-100'   => 0,
			'101-250'  => 0,
			'251-500'  => 0,
			'501-1000' => 0,
			'1000+'    => 0,
		);

		$total_ltv  = 0;
		$count      = 0;

		foreach ( $results as $row ) {
			$ltv = (float) $row->lifetime_value;
			$total_ltv += $ltv;
			$count++;

			if ( $ltv <= 50 ) {
				$buckets['0-50']++;
			} elseif ( $ltv <= 100 ) {
				$buckets['51-100']++;
			} elseif ( $ltv <= 250 ) {
				$buckets['101-250']++;
			} elseif ( $ltv <= 500 ) {
				$buckets['251-500']++;
			} elseif ( $ltv <= 1000 ) {
				$buckets['501-1000']++;
			} else {
				$buckets['1000+']++;
			}
		}

		return array(
			'distribution' => array(
				'labels' => array_keys( $buckets ),
				'data'   => array_values( $buckets ),
			),
			'average_ltv'  => $count > 0 ? round( $total_ltv / $count, 2 ) : 0,
			'total_customers' => $count,
		);
	}

	/**
	 * Get retention metrics.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_retention_metrics( $date_from, $date_to ) {
		global $wpdb;

		// Get customers who booked in both current and previous period.
		$days_diff      = ( strtotime( $date_to ) - strtotime( $date_from ) ) / DAY_IN_SECONDS;
		$prev_date_from = gmdate( 'Y-m-d', strtotime( $date_from . " -{$days_diff} days" ) );
		$prev_date_to   = gmdate( 'Y-m-d', strtotime( $date_from . ' -1 day' ) );

		// Customers in previous period.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$prev_customers = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm_email.meta_value
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'customer_email'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s",
				$prev_date_from,
				$prev_date_to
			)
		);

		// Customers in current period.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$curr_customers = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm_email.meta_value
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'customer_email'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pm_date.meta_value BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);

		$retained = array_intersect( $prev_customers, $curr_customers );
		$churned  = array_diff( $prev_customers, $curr_customers );

		$prev_count      = count( $prev_customers );
		$retained_count  = count( $retained );
		$churned_count   = count( $churned );

		return array(
			'previous_customers' => $prev_count,
			'retained_customers' => $retained_count,
			'churned_customers'  => $churned_count,
			'retention_rate'     => $prev_count > 0 ? round( ( $retained_count / $prev_count ) * 100, 1 ) : 0,
			'churn_rate'         => $prev_count > 0 ? round( ( $churned_count / $prev_count ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Get customer acquisition trend.
	 *
	 * @since 1.0.0
	 *
	 * @param string $date_from Start date.
	 * @param string $date_to End date.
	 * @return array
	 */
	public function get_acquisition_trend( $date_from, $date_to ) {
		global $wpdb;

		// Get first booking date for each customer.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(MIN(pm_date.meta_value)) as first_booking,
					COUNT(DISTINCT pm_email.meta_value) as new_customers
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm_email ON p.ID = pm_email.post_id AND pm_email.meta_key = 'customer_email'
				LEFT JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				GROUP BY pm_email.meta_value
				HAVING first_booking BETWEEN %s AND %s",
				$date_from,
				$date_to
			)
		);

		// Aggregate by date.
		$acquisition = array();

		foreach ( $results as $row ) {
			$date = $row->first_booking;
			if ( ! isset( $acquisition[ $date ] ) ) {
				$acquisition[ $date ] = 0;
			}
			$acquisition[ $date ] += (int) $row->new_customers;
		}

		ksort( $acquisition );

		return array(
			'labels' => array_keys( $acquisition ),
			'data'   => array_values( $acquisition ),
		);
	}
}
