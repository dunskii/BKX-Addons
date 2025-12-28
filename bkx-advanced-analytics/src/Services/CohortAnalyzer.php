<?php
/**
 * Cohort Analyzer Service.
 *
 * @package BookingX\AdvancedAnalytics\Services
 * @since   1.0.0
 */

namespace BookingX\AdvancedAnalytics\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CohortAnalyzer Class.
 */
class CohortAnalyzer {

	/**
	 * Get cohort analysis.
	 *
	 * @param string $cohort_type Cohort type (monthly, weekly).
	 * @param string $metric      Metric (retention, revenue, frequency).
	 * @param int    $periods     Number of periods.
	 * @return array
	 */
	public function get_cohort_analysis( $cohort_type, $metric, $periods = 12 ) {
		switch ( $metric ) {
			case 'revenue':
				return $this->get_revenue_cohorts( $cohort_type, $periods );
			case 'frequency':
				return $this->get_frequency_cohorts( $cohort_type, $periods );
			default:
				return $this->get_retention_cohorts( $cohort_type, $periods );
		}
	}

	/**
	 * Get retention cohorts.
	 *
	 * @param string $cohort_type Cohort type.
	 * @param int    $periods     Number of periods.
	 * @return array
	 */
	private function get_retention_cohorts( $cohort_type, $periods ) {
		global $wpdb;

		$format       = 'monthly' === $cohort_type ? '%Y-%m' : '%Y-%u';
		$interval     = 'monthly' === $cohort_type ? 'MONTH' : 'WEEK';
		$period_label = 'monthly' === $cohort_type ? 'Month' : 'Week';

		// Get first booking date per customer.
		$first_bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pe.meta_value as customer_email,
					DATE_FORMAT(MIN(pd.meta_value), %s) as cohort
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pe.meta_value IS NOT NULL
				AND pe.meta_value != ''
				GROUP BY pe.meta_value
				ORDER BY cohort ASC
				LIMIT %d",
				$format,
				$periods * 100
			),
			ARRAY_A
		);

		// Build customer cohort mapping.
		$customer_cohorts = array();
		foreach ( $first_bookings as $row ) {
			$customer_cohorts[ $row['customer_email'] ] = $row['cohort'];
		}

		// Get all bookings per customer.
		$all_bookings = $wpdb->get_results(
			"SELECT
				pe.meta_value as customer_email,
				DATE_FORMAT(pd.meta_value, '{$format}') as booking_period
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
			INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
			WHERE p.post_type = 'bkx_booking'
			AND pe.meta_value IS NOT NULL
			AND pe.meta_value != ''
			ORDER BY pd.meta_value ASC",
			ARRAY_A
		);

		// Build cohort matrix.
		$cohorts    = array_unique( array_values( $customer_cohorts ) );
		sort( $cohorts );
		$cohorts    = array_slice( $cohorts, -$periods );
		$matrix     = array();
		$cohort_sizes = array();

		foreach ( $cohorts as $cohort ) {
			$matrix[ $cohort ]       = array_fill( 0, $periods, 0 );
			$cohort_sizes[ $cohort ] = count( array_filter( $customer_cohorts, function( $c ) use ( $cohort ) {
				return $c === $cohort;
			} ) );
		}

		// Populate matrix.
		foreach ( $all_bookings as $booking ) {
			$customer = $booking['customer_email'];
			if ( ! isset( $customer_cohorts[ $customer ] ) ) {
				continue;
			}

			$cohort = $customer_cohorts[ $customer ];
			if ( ! isset( $matrix[ $cohort ] ) ) {
				continue;
			}

			$cohort_index  = array_search( $cohort, $cohorts, true );
			$period_index  = array_search( $booking['booking_period'], $cohorts, true );

			if ( false !== $cohort_index && false !== $period_index ) {
				$relative_period = $period_index - $cohort_index;
				if ( $relative_period >= 0 && $relative_period < $periods ) {
					$matrix[ $cohort ][ $relative_period ]++;
				}
			}
		}

		// Convert to percentages.
		$retention_matrix = array();
		foreach ( $matrix as $cohort => $periods_data ) {
			$size = $cohort_sizes[ $cohort ];
			$retention_matrix[ $cohort ] = array(
				'cohort'   => $cohort,
				'size'     => $size,
				'periods'  => array(),
			);

			foreach ( $periods_data as $p => $count ) {
				$retention_matrix[ $cohort ]['periods'][ $p ] = $size > 0 ? round( ( $count / $size ) * 100, 1 ) : 0;
			}
		}

		// Calculate averages per period.
		$period_averages = array_fill( 0, $periods, 0 );
		$period_counts   = array_fill( 0, $periods, 0 );

		foreach ( $retention_matrix as $data ) {
			foreach ( $data['periods'] as $p => $rate ) {
				$period_averages[ $p ] += $rate;
				$period_counts[ $p ]++;
			}
		}

		for ( $i = 0; $i < $periods; $i++ ) {
			$period_averages[ $i ] = $period_counts[ $i ] > 0 ? round( $period_averages[ $i ] / $period_counts[ $i ], 1 ) : 0;
		}

		return array(
			'type'            => 'retention',
			'cohort_type'     => $cohort_type,
			'matrix'          => array_values( $retention_matrix ),
			'period_averages' => $period_averages,
			'header_labels'   => array_map( function( $i ) use ( $period_label ) {
				return $period_label . ' ' . $i;
			}, range( 0, $periods - 1 ) ),
			'insights'        => $this->generate_retention_insights( $period_averages ),
		);
	}

	/**
	 * Get revenue cohorts.
	 *
	 * @param string $cohort_type Cohort type.
	 * @param int    $periods     Number of periods.
	 * @return array
	 */
	private function get_revenue_cohorts( $cohort_type, $periods ) {
		global $wpdb;

		$format = 'monthly' === $cohort_type ? '%Y-%m' : '%Y-%u';

		// Get first booking date and revenue per customer.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pe.meta_value as customer_email,
					DATE_FORMAT(MIN(pd.meta_value), %s) as cohort,
					DATE_FORMAT(pd.meta_value, %s) as booking_period,
					SUM(COALESCE(pt.meta_value, 0)) as revenue
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pe.meta_value IS NOT NULL
				GROUP BY pe.meta_value, DATE_FORMAT(pd.meta_value, %s)
				ORDER BY cohort ASC, booking_period ASC",
				$format,
				$format,
				$format
			),
			ARRAY_A
		);

		// Build cohort revenue matrix.
		$cohorts = array();
		$matrix  = array();

		foreach ( $results as $row ) {
			$cohort = $row['cohort'];
			if ( ! isset( $cohorts[ $cohort ] ) ) {
				$cohorts[ $cohort ] = 0;
			}
			$cohorts[ $cohort ]++;

			if ( ! isset( $matrix[ $cohort ] ) ) {
				$matrix[ $cohort ] = array_fill( 0, $periods, 0 );
			}

			$cohort_keys = array_keys( $cohorts );
			$cohort_idx  = array_search( $cohort, $cohort_keys, true );
			$period_keys = array_unique( array_column( $results, 'booking_period' ) );
			sort( $period_keys );
			$period_idx = array_search( $row['booking_period'], $period_keys, true );

			if ( false !== $cohort_idx && false !== $period_idx ) {
				$relative = $period_idx - $cohort_idx;
				if ( $relative >= 0 && $relative < $periods ) {
					$matrix[ $cohort ][ $relative ] += (float) $row['revenue'];
				}
			}
		}

		// Limit to recent cohorts.
		$cohort_keys   = array_keys( $matrix );
		$cohort_keys   = array_slice( $cohort_keys, -$periods );
		$final_matrix  = array();

		foreach ( $cohort_keys as $cohort ) {
			$final_matrix[] = array(
				'cohort'  => $cohort,
				'size'    => $cohorts[ $cohort ],
				'periods' => $matrix[ $cohort ],
			);
		}

		return array(
			'type'        => 'revenue',
			'cohort_type' => $cohort_type,
			'matrix'      => $final_matrix,
		);
	}

	/**
	 * Get frequency cohorts.
	 *
	 * @param string $cohort_type Cohort type.
	 * @param int    $periods     Number of periods.
	 * @return array
	 */
	private function get_frequency_cohorts( $cohort_type, $periods ) {
		global $wpdb;

		$format = 'monthly' === $cohort_type ? '%Y-%m' : '%Y-%u';

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pe.meta_value as customer_email,
					DATE_FORMAT(MIN(pd.meta_value), %s) as cohort,
					DATE_FORMAT(pd.meta_value, %s) as booking_period,
					COUNT(p.ID) as booking_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pe.meta_value IS NOT NULL
				GROUP BY pe.meta_value, DATE_FORMAT(pd.meta_value, %s)
				ORDER BY cohort ASC",
				$format,
				$format,
				$format
			),
			ARRAY_A
		);

		// Similar processing as revenue cohorts.
		$cohorts = array();
		$matrix  = array();

		foreach ( $results as $row ) {
			$cohort = $row['cohort'];
			if ( ! isset( $cohorts[ $cohort ] ) ) {
				$cohorts[ $cohort ] = 0;
			}
			$cohorts[ $cohort ]++;

			if ( ! isset( $matrix[ $cohort ] ) ) {
				$matrix[ $cohort ] = array_fill( 0, $periods, 0 );
			}
		}

		$cohort_keys  = array_keys( $matrix );
		$cohort_keys  = array_slice( $cohort_keys, -$periods );
		$final_matrix = array();

		foreach ( $cohort_keys as $cohort ) {
			if ( isset( $matrix[ $cohort ] ) ) {
				$final_matrix[] = array(
					'cohort'  => $cohort,
					'size'    => $cohorts[ $cohort ],
					'periods' => $matrix[ $cohort ],
				);
			}
		}

		return array(
			'type'        => 'frequency',
			'cohort_type' => $cohort_type,
			'matrix'      => $final_matrix,
		);
	}

	/**
	 * Build cohorts (cron job).
	 */
	public function build_cohorts() {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_aa_cohorts';

		// Build monthly retention cohorts.
		$retention = $this->get_retention_cohorts( 'monthly', 12 );

		foreach ( $retention['matrix'] as $data ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE cohort_date = %s AND cohort_type = 'monthly'",
					$data['cohort'] . '-01'
				)
			);

			$row_data = array(
				'cohort_date'    => $data['cohort'] . '-01',
				'cohort_type'    => 'monthly',
				'customer_count' => $data['size'],
			);

			for ( $i = 0; $i < 12; $i++ ) {
				$row_data[ 'period_' . $i ] = $data['periods'][ $i ] ?? 0;
			}

			if ( $existing ) {
				$wpdb->update(
					$table,
					$row_data,
					array( 'id' => $existing )
				);
			} else {
				$wpdb->insert( $table, $row_data );
			}
		}
	}

	/**
	 * Generate retention insights.
	 *
	 * @param array $averages Period averages.
	 * @return array
	 */
	private function generate_retention_insights( $averages ) {
		$insights = array();

		if ( count( $averages ) < 2 ) {
			return $insights;
		}

		// First month retention.
		if ( $averages[1] > 0 ) {
			$retention_1 = $averages[1];
			if ( $retention_1 > 30 ) {
				$insights[] = array(
					'type'    => 'positive',
					'message' => sprintf(
						/* translators: %s: percentage */
						__( 'Strong first-period retention at %s%%. Customers are returning for repeat bookings.', 'bkx-advanced-analytics' ),
						$retention_1
					),
				);
			} elseif ( $retention_1 < 15 ) {
				$insights[] = array(
					'type'    => 'warning',
					'message' => sprintf(
						/* translators: %s: percentage */
						__( 'First-period retention is %s%%. Consider implementing follow-up campaigns to encourage repeat bookings.', 'bkx-advanced-analytics' ),
						$retention_1
					),
				);
			}
		}

		// Long-term retention.
		if ( isset( $averages[6] ) && $averages[6] > 10 ) {
			$insights[] = array(
				'type'    => 'positive',
				'message' => sprintf(
					/* translators: %s: percentage */
					__( 'Good long-term retention with %s%% of customers still active after 6 periods.', 'bkx-advanced-analytics' ),
					$averages[6]
				),
			);
		}

		return $insights;
	}
}
