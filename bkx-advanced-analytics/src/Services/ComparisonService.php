<?php
/**
 * Comparison Service.
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
 * ComparisonService Class.
 */
class ComparisonService {

	/**
	 * Compare two periods.
	 *
	 * @param array $period_a   Period A (start, end).
	 * @param array $period_b   Period B (start, end).
	 * @param array $dimensions Dimensions to compare.
	 * @return array
	 */
	public function compare_periods( $period_a, $period_b, $dimensions ) {
		$data_a = $this->get_period_data( $period_a['start'], $period_a['end'] );
		$data_b = $this->get_period_data( $period_b['start'], $period_b['end'] );

		$comparison = array(
			'period_a'   => array(
				'label' => $period_a['start'] . ' - ' . $period_a['end'],
				'data'  => $data_a,
			),
			'period_b'   => array(
				'label' => $period_b['start'] . ' - ' . $period_b['end'],
				'data'  => $data_b,
			),
			'changes'    => array(),
			'chart_data' => array(),
		);

		// Calculate changes for each dimension.
		foreach ( $dimensions as $dim ) {
			$val_a = $data_a[ $dim ] ?? 0;
			$val_b = $data_b[ $dim ] ?? 0;

			$absolute_change = $val_b - $val_a;
			$percent_change  = $val_a > 0 ? ( ( $val_b - $val_a ) / $val_a ) * 100 : ( $val_b > 0 ? 100 : 0 );

			$comparison['changes'][ $dim ] = array(
				'period_a'        => $val_a,
				'period_b'        => $val_b,
				'absolute_change' => round( $absolute_change, 2 ),
				'percent_change'  => round( $percent_change, 1 ),
				'trend'           => $absolute_change > 0 ? 'up' : ( $absolute_change < 0 ? 'down' : 'flat' ),
			);
		}

		// Build chart data.
		$comparison['chart_data'] = array(
			'labels' => array( $comparison['period_a']['label'], $comparison['period_b']['label'] ),
			'datasets' => array(),
		);

		$colors = array( '#0073aa', '#46b450', '#ffb900', '#dc3232', '#00a0d2' );
		$i      = 0;

		foreach ( $dimensions as $dim ) {
			$comparison['chart_data']['datasets'][] = array(
				'label'           => ucwords( str_replace( '_', ' ', $dim ) ),
				'data'            => array( $data_a[ $dim ] ?? 0, $data_b[ $dim ] ?? 0 ),
				'backgroundColor' => $colors[ $i % count( $colors ) ],
			);
			$i++;
		}

		// Generate insights.
		$comparison['insights'] = $this->generate_comparison_insights( $comparison['changes'] );

		return $comparison;
	}

	/**
	 * Get period data.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_period_data( $start_date, $end_date ) {
		global $wpdb;

		$data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(p.ID) as bookings,
					COUNT(CASE WHEN p.post_status = 'bkx-completed' THEN 1 END) as completed,
					COUNT(CASE WHEN p.post_status = 'bkx-cancelled' THEN 1 END) as cancelled,
					COALESCE(SUM(pt.meta_value), 0) as revenue,
					COALESCE(AVG(pt.meta_value), 0) as avg_value,
					COUNT(DISTINCT pe.meta_value) as customers,
					COUNT(DISTINCT ps.meta_value) as services,
					COUNT(DISTINCT pst.meta_value) as staff
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				LEFT JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
				LEFT JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'base_id'
				LEFT JOIN {$wpdb->postmeta} pst ON p.ID = pst.post_id AND pst.meta_key = 'seat_id'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$bookings = (int) $data['bookings'];

		return array(
			'bookings'          => $bookings,
			'completed'         => (int) $data['completed'],
			'cancelled'         => (int) $data['cancelled'],
			'revenue'           => (float) $data['revenue'],
			'avg_value'         => round( (float) $data['avg_value'], 2 ),
			'customers'         => (int) $data['customers'],
			'services'          => (int) $data['services'],
			'staff'             => (int) $data['staff'],
			'completion_rate'   => $bookings > 0 ? round( ( (int) $data['completed'] / $bookings ) * 100, 1 ) : 0,
			'cancellation_rate' => $bookings > 0 ? round( ( (int) $data['cancelled'] / $bookings ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Compare services.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function compare_services( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ps.meta_value as service_id,
					COUNT(p.ID) as bookings,
					COUNT(CASE WHEN p.post_status = 'bkx-completed' THEN 1 END) as completed,
					COALESCE(SUM(pt.meta_value), 0) as revenue,
					COALESCE(AVG(pt.meta_value), 0) as avg_value
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'base_id'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY ps.meta_value
				ORDER BY revenue DESC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$services = array();
		$total_revenue = 0;
		$total_bookings = 0;

		foreach ( $results as $row ) {
			$service = get_post( $row['service_id'] );
			if ( $service ) {
				$bookings = (int) $row['bookings'];
				$revenue  = (float) $row['revenue'];

				$services[] = array(
					'id'              => $row['service_id'],
					'name'            => $service->post_title,
					'bookings'        => $bookings,
					'completed'       => (int) $row['completed'],
					'revenue'         => $revenue,
					'avg_value'       => round( (float) $row['avg_value'], 2 ),
					'completion_rate' => $bookings > 0 ? round( ( (int) $row['completed'] / $bookings ) * 100, 1 ) : 0,
				);

				$total_revenue  += $revenue;
				$total_bookings += $bookings;
			}
		}

		// Add percentages.
		foreach ( $services as &$service ) {
			$service['revenue_share']  = $total_revenue > 0 ? round( ( $service['revenue'] / $total_revenue ) * 100, 1 ) : 0;
			$service['booking_share']  = $total_bookings > 0 ? round( ( $service['bookings'] / $total_bookings ) * 100, 1 ) : 0;
		}

		return array(
			'services'       => $services,
			'total_revenue'  => $total_revenue,
			'total_bookings' => $total_bookings,
			'chart_data'     => array(
				'labels'   => array_column( $services, 'name' ),
				'datasets' => array(
					array(
						'label' => __( 'Revenue', 'bkx-advanced-analytics' ),
						'data'  => array_column( $services, 'revenue' ),
					),
				),
			),
		);
	}

	/**
	 * Compare staff performance.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function compare_staff( $start_date, $end_date ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					ps.meta_value as staff_id,
					COUNT(p.ID) as bookings,
					COUNT(CASE WHEN p.post_status = 'bkx-completed' THEN 1 END) as completed,
					COUNT(CASE WHEN p.post_status = 'bkx-cancelled' THEN 1 END) as cancelled,
					COALESCE(SUM(pt.meta_value), 0) as revenue,
					COUNT(DISTINCT pe.meta_value) as unique_customers
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} ps ON p.ID = ps.post_id AND ps.meta_key = 'seat_id'
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				LEFT JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY ps.meta_value
				ORDER BY revenue DESC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$staff = array();

		foreach ( $results as $row ) {
			$seat = get_post( $row['staff_id'] );
			if ( $seat ) {
				$bookings = (int) $row['bookings'];

				$staff[] = array(
					'id'               => $row['staff_id'],
					'name'             => $seat->post_title,
					'bookings'         => $bookings,
					'completed'        => (int) $row['completed'],
					'cancelled'        => (int) $row['cancelled'],
					'revenue'          => (float) $row['revenue'],
					'unique_customers' => (int) $row['unique_customers'],
					'completion_rate'  => $bookings > 0 ? round( ( (int) $row['completed'] / $bookings ) * 100, 1 ) : 0,
					'cancellation_rate' => $bookings > 0 ? round( ( (int) $row['cancelled'] / $bookings ) * 100, 1 ) : 0,
				);
			}
		}

		return array(
			'staff'      => $staff,
			'chart_data' => array(
				'labels'   => array_column( $staff, 'name' ),
				'datasets' => array(
					array(
						'label'           => __( 'Revenue', 'bkx-advanced-analytics' ),
						'data'            => array_column( $staff, 'revenue' ),
						'backgroundColor' => '#0073aa',
					),
					array(
						'label'           => __( 'Bookings', 'bkx-advanced-analytics' ),
						'data'            => array_column( $staff, 'bookings' ),
						'backgroundColor' => '#46b450',
					),
				),
			),
		);
	}

	/**
	 * Generate comparison insights.
	 *
	 * @param array $changes Changes data.
	 * @return array
	 */
	private function generate_comparison_insights( $changes ) {
		$insights = array();

		// Revenue insight.
		if ( isset( $changes['revenue'] ) ) {
			$change = $changes['revenue'];
			if ( $change['percent_change'] > 20 ) {
				$insights[] = array(
					'type'    => 'positive',
					'message' => sprintf(
						/* translators: %s: percentage */
						__( 'Revenue increased by %s%% - excellent growth!', 'bkx-advanced-analytics' ),
						$change['percent_change']
					),
				);
			} elseif ( $change['percent_change'] < -20 ) {
				$insights[] = array(
					'type'    => 'warning',
					'message' => sprintf(
						/* translators: %s: percentage */
						__( 'Revenue decreased by %s%%. Review marketing and pricing strategies.', 'bkx-advanced-analytics' ),
						abs( $change['percent_change'] )
					),
				);
			}
		}

		// Booking volume insight.
		if ( isset( $changes['bookings'] ) ) {
			$change = $changes['bookings'];
			if ( $change['percent_change'] > 15 ) {
				$insights[] = array(
					'type'    => 'positive',
					'message' => sprintf(
						/* translators: %s: percentage */
						__( 'Booking volume up %s%% - strong demand growth.', 'bkx-advanced-analytics' ),
						$change['percent_change']
					),
				);
			}
		}

		// Customer growth insight.
		if ( isset( $changes['customers'] ) ) {
			$change = $changes['customers'];
			if ( $change['percent_change'] > 10 ) {
				$insights[] = array(
					'type'    => 'positive',
					'message' => sprintf(
						/* translators: %s: percentage */
						__( 'Customer base grew by %s%%.', 'bkx-advanced-analytics' ),
						$change['percent_change']
					),
				);
			}
		}

		return $insights;
	}
}
