<?php
/**
 * Segmentation Service.
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
 * SegmentationService Class.
 */
class SegmentationService {

	/**
	 * Get customer segments.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_customer_segments( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pe.meta_value as customer_email,
					COUNT(p.ID) as booking_count,
					COALESCE(SUM(pt.meta_value), 0) as total_spent,
					MIN(pd.meta_value) as first_booking,
					MAX(pd.meta_value) as last_booking
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value BETWEEN %s AND %s
				AND pe.meta_value IS NOT NULL
				AND pe.meta_value != ''
				GROUP BY pe.meta_value",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$segments = array(
			'new'       => array( 'label' => __( 'New', 'bkx-advanced-analytics' ), 'count' => 0, 'revenue' => 0 ),
			'returning' => array( 'label' => __( 'Returning', 'bkx-advanced-analytics' ), 'count' => 0, 'revenue' => 0 ),
			'loyal'     => array( 'label' => __( 'Loyal', 'bkx-advanced-analytics' ), 'count' => 0, 'revenue' => 0 ),
			'vip'       => array( 'label' => __( 'VIP', 'bkx-advanced-analytics' ), 'count' => 0, 'revenue' => 0 ),
		);

		foreach ( $results as $row ) {
			$bookings = (int) $row['booking_count'];
			$spent    = (float) $row['total_spent'];

			if ( $bookings === 1 ) {
				$segments['new']['count']++;
				$segments['new']['revenue'] += $spent;
			} elseif ( $bookings >= 2 && $bookings <= 4 ) {
				$segments['returning']['count']++;
				$segments['returning']['revenue'] += $spent;
			} elseif ( $bookings >= 5 && $bookings <= 9 ) {
				$segments['loyal']['count']++;
				$segments['loyal']['revenue'] += $spent;
			} else {
				$segments['vip']['count']++;
				$segments['vip']['revenue'] += $spent;
			}
		}

		return array(
			'segments'   => $segments,
			'chart_data' => array(
				'labels'   => array_column( $segments, 'label' ),
				'datasets' => array(
					array(
						'label'           => __( 'Customers', 'bkx-advanced-analytics' ),
						'data'            => array_column( $segments, 'count' ),
						'backgroundColor' => array( '#00a0d2', '#0073aa', '#46b450', '#ffb900' ),
					),
				),
			),
		);
	}

	/**
	 * Get RFM segments (Recency, Frequency, Monetary).
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_rfm_segments( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pe.meta_value as customer_email,
					COUNT(p.ID) as frequency,
					COALESCE(SUM(pt.meta_value), 0) as monetary,
					DATEDIFF(%s, MAX(pd.meta_value)) as recency_days
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value BETWEEN %s AND %s
				AND pe.meta_value IS NOT NULL
				GROUP BY pe.meta_value",
				$end_date,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Calculate RFM scores.
		$customers = array();
		foreach ( $results as $row ) {
			$customers[] = array(
				'email'        => $row['customer_email'],
				'recency'      => (int) $row['recency_days'],
				'frequency'    => (int) $row['frequency'],
				'monetary'     => (float) $row['monetary'],
			);
		}

		// Calculate percentiles for scoring.
		$recencies   = array_column( $customers, 'recency' );
		$frequencies = array_column( $customers, 'frequency' );
		$monetaries  = array_column( $customers, 'monetary' );

		sort( $recencies );
		sort( $frequencies );
		sort( $monetaries );

		// Score each customer.
		foreach ( $customers as &$customer ) {
			// Recency: lower is better, so reverse score.
			$customer['r_score'] = 5 - $this->get_percentile_score( $customer['recency'], $recencies );
			$customer['f_score'] = $this->get_percentile_score( $customer['frequency'], $frequencies );
			$customer['m_score'] = $this->get_percentile_score( $customer['monetary'], $monetaries );
			$customer['rfm_score'] = $customer['r_score'] . $customer['f_score'] . $customer['m_score'];
			$customer['segment'] = $this->get_rfm_segment_name( $customer['r_score'], $customer['f_score'], $customer['m_score'] );
		}

		// Group by segment.
		$segments = array();
		foreach ( $customers as $customer ) {
			$segment = $customer['segment'];
			if ( ! isset( $segments[ $segment ] ) ) {
				$segments[ $segment ] = array(
					'count'    => 0,
					'revenue'  => 0,
					'avg_freq' => 0,
				);
			}
			$segments[ $segment ]['count']++;
			$segments[ $segment ]['revenue'] += $customer['monetary'];
			$segments[ $segment ]['avg_freq'] += $customer['frequency'];
		}

		// Calculate averages.
		foreach ( $segments as &$segment ) {
			if ( $segment['count'] > 0 ) {
				$segment['avg_freq'] = round( $segment['avg_freq'] / $segment['count'], 1 );
			}
		}

		return array(
			'customers'  => $customers,
			'segments'   => $segments,
			'chart_data' => array(
				'labels'   => array_keys( $segments ),
				'datasets' => array(
					array(
						'label' => __( 'Customers', 'bkx-advanced-analytics' ),
						'data'  => array_column( $segments, 'count' ),
					),
				),
			),
			'insights'   => $this->generate_rfm_insights( $segments ),
		);
	}

	/**
	 * Get value segments.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_value_segments( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pe.meta_value as customer_email,
					COALESCE(SUM(pt.meta_value), 0) as total_spent,
					COUNT(p.ID) as booking_count
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-ack', 'bkx-completed')
				AND pd.meta_value BETWEEN %s AND %s
				AND pe.meta_value IS NOT NULL
				GROUP BY pe.meta_value
				ORDER BY total_spent DESC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		// Calculate total revenue.
		$total_revenue = array_sum( array_column( $results, 'total_spent' ) );
		$customer_count = count( $results );

		// Segment by value tier.
		$segments = array(
			'high_value'   => array( 'label' => __( 'High Value (Top 20%)', 'bkx-advanced-analytics' ), 'count' => 0, 'revenue' => 0 ),
			'medium_value' => array( 'label' => __( 'Medium Value (Next 30%)', 'bkx-advanced-analytics' ), 'count' => 0, 'revenue' => 0 ),
			'low_value'    => array( 'label' => __( 'Low Value (Bottom 50%)', 'bkx-advanced-analytics' ), 'count' => 0, 'revenue' => 0 ),
		);

		$high_threshold   = ceil( $customer_count * 0.2 );
		$medium_threshold = ceil( $customer_count * 0.5 );

		foreach ( $results as $index => $row ) {
			$spent = (float) $row['total_spent'];

			if ( $index < $high_threshold ) {
				$segments['high_value']['count']++;
				$segments['high_value']['revenue'] += $spent;
			} elseif ( $index < $medium_threshold ) {
				$segments['medium_value']['count']++;
				$segments['medium_value']['revenue'] += $spent;
			} else {
				$segments['low_value']['count']++;
				$segments['low_value']['revenue'] += $spent;
			}
		}

		// Calculate percentages.
		foreach ( $segments as &$segment ) {
			$segment['revenue_share'] = $total_revenue > 0 ? round( ( $segment['revenue'] / $total_revenue ) * 100, 1 ) : 0;
			$segment['customer_share'] = $customer_count > 0 ? round( ( $segment['count'] / $customer_count ) * 100, 1 ) : 0;
		}

		return array(
			'segments'      => $segments,
			'total_revenue' => $total_revenue,
			'total_customers' => $customer_count,
			'chart_data'    => array(
				'labels'   => array_column( $segments, 'label' ),
				'datasets' => array(
					array(
						'label'           => __( 'Revenue Share', 'bkx-advanced-analytics' ),
						'data'            => array_column( $segments, 'revenue_share' ),
						'backgroundColor' => array( '#ffb900', '#0073aa', '#c3c4c7' ),
					),
				),
			),
		);
	}

	/**
	 * Get behavioral segments.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_behavioral_segments( $start_date, $end_date ) {
		global $wpdb;

		// Get booking patterns per customer.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pe.meta_value as customer_email,
					COUNT(p.ID) as booking_count,
					COUNT(DISTINCT ps.meta_value) as unique_services,
					COUNT(DISTINCT pst.meta_value) as unique_staff,
					AVG(HOUR(pd.meta_value)) as avg_hour
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
				LEFT JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'base_id'
				LEFT JOIN {$wpdb->postmeta} pst ON p.ID = pst.post_id AND pst.meta_key = 'seat_id'
				LEFT JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_time'
				INNER JOIN {$wpdb->postmeta} pdt ON p.ID = pdt.post_id AND pdt.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pdt.meta_value BETWEEN %s AND %s
				AND pe.meta_value IS NOT NULL
				GROUP BY pe.meta_value",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$segments = array(
			'explorers'   => array( 'label' => __( 'Explorers', 'bkx-advanced-analytics' ), 'description' => __( 'Try many services', 'bkx-advanced-analytics' ), 'count' => 0 ),
			'loyalists'   => array( 'label' => __( 'Loyalists', 'bkx-advanced-analytics' ), 'description' => __( 'Stick to favorites', 'bkx-advanced-analytics' ), 'count' => 0 ),
			'morning'     => array( 'label' => __( 'Morning People', 'bkx-advanced-analytics' ), 'description' => __( 'Book before noon', 'bkx-advanced-analytics' ), 'count' => 0 ),
			'evening'     => array( 'label' => __( 'Evening People', 'bkx-advanced-analytics' ), 'description' => __( 'Book after 5pm', 'bkx-advanced-analytics' ), 'count' => 0 ),
			'staff_pref'  => array( 'label' => __( 'Staff Preferrers', 'bkx-advanced-analytics' ), 'description' => __( 'Always same staff', 'bkx-advanced-analytics' ), 'count' => 0 ),
		);

		foreach ( $results as $row ) {
			$services = (int) $row['unique_services'];
			$staff    = (int) $row['unique_staff'];
			$bookings = (int) $row['booking_count'];
			$avg_hour = (float) $row['avg_hour'];

			// Explorer: tries 3+ different services.
			if ( $services >= 3 ) {
				$segments['explorers']['count']++;
			}

			// Loyalist: 3+ bookings, 1-2 services.
			if ( $bookings >= 3 && $services <= 2 ) {
				$segments['loyalists']['count']++;
			}

			// Morning person.
			if ( $avg_hour < 12 ) {
				$segments['morning']['count']++;
			}

			// Evening person.
			if ( $avg_hour >= 17 ) {
				$segments['evening']['count']++;
			}

			// Staff preferrer: 3+ bookings, same staff.
			if ( $bookings >= 3 && $staff === 1 ) {
				$segments['staff_pref']['count']++;
			}
		}

		return array(
			'segments'   => $segments,
			'chart_data' => array(
				'labels'   => array_column( $segments, 'label' ),
				'datasets' => array(
					array(
						'label' => __( 'Customers', 'bkx-advanced-analytics' ),
						'data'  => array_column( $segments, 'count' ),
					),
				),
			),
		);
	}

	/**
	 * Get percentile score (1-5).
	 *
	 * @param mixed $value  Value to score.
	 * @param array $sorted Sorted array of all values.
	 * @return int
	 */
	private function get_percentile_score( $value, $sorted ) {
		$count = count( $sorted );
		if ( $count === 0 ) {
			return 3;
		}

		$position = array_search( $value, $sorted, true );
		if ( false === $position ) {
			$position = 0;
		}

		$percentile = ( $position / $count ) * 100;

		if ( $percentile <= 20 ) {
			return 1;
		} elseif ( $percentile <= 40 ) {
			return 2;
		} elseif ( $percentile <= 60 ) {
			return 3;
		} elseif ( $percentile <= 80 ) {
			return 4;
		} else {
			return 5;
		}
	}

	/**
	 * Get RFM segment name.
	 *
	 * @param int $r Recency score.
	 * @param int $f Frequency score.
	 * @param int $m Monetary score.
	 * @return string
	 */
	private function get_rfm_segment_name( $r, $f, $m ) {
		// Champions: High R, F, M.
		if ( $r >= 4 && $f >= 4 && $m >= 4 ) {
			return __( 'Champions', 'bkx-advanced-analytics' );
		}

		// Loyal Customers: High F and M.
		if ( $f >= 4 && $m >= 4 ) {
			return __( 'Loyal Customers', 'bkx-advanced-analytics' );
		}

		// Potential Loyalists: High R and F.
		if ( $r >= 4 && $f >= 3 ) {
			return __( 'Potential Loyalists', 'bkx-advanced-analytics' );
		}

		// New Customers: High R, low F.
		if ( $r >= 4 && $f <= 2 ) {
			return __( 'New Customers', 'bkx-advanced-analytics' );
		}

		// At Risk: Low R, high F.
		if ( $r <= 2 && $f >= 3 ) {
			return __( 'At Risk', 'bkx-advanced-analytics' );
		}

		// Can\'t Lose Them: Low R, very high F and M.
		if ( $r <= 2 && $f >= 4 && $m >= 4 ) {
			return __( 'Can\'t Lose Them', 'bkx-advanced-analytics' );
		}

		// Hibernating: Low R, F, M.
		if ( $r <= 2 && $f <= 2 ) {
			return __( 'Hibernating', 'bkx-advanced-analytics' );
		}

		return __( 'Others', 'bkx-advanced-analytics' );
	}

	/**
	 * Generate RFM insights.
	 *
	 * @param array $segments Segments.
	 * @return array
	 */
	private function generate_rfm_insights( $segments ) {
		$insights = array();

		if ( isset( $segments[ __( 'At Risk', 'bkx-advanced-analytics' ) ] ) && $segments[ __( 'At Risk', 'bkx-advanced-analytics' ) ]['count'] > 0 ) {
			$insights[] = array(
				'type'    => 'warning',
				'message' => sprintf(
					/* translators: %d: count */
					__( '%d valuable customers are at risk of churning. Consider a win-back campaign.', 'bkx-advanced-analytics' ),
					$segments[ __( 'At Risk', 'bkx-advanced-analytics' ) ]['count']
				),
			);
		}

		if ( isset( $segments[ __( 'Champions', 'bkx-advanced-analytics' ) ] ) && $segments[ __( 'Champions', 'bkx-advanced-analytics' ) ]['count'] > 0 ) {
			$insights[] = array(
				'type'    => 'positive',
				'message' => sprintf(
					/* translators: %d: count */
					__( '%d customers are Champions - your best customers. Reward them!', 'bkx-advanced-analytics' ),
					$segments[ __( 'Champions', 'bkx-advanced-analytics' ) ]['count']
				),
			);
		}

		return $insights;
	}
}
