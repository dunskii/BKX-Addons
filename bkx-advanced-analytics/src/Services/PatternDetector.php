<?php
/**
 * Pattern Detector Service.
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
 * PatternDetector Class.
 */
class PatternDetector {

	/**
	 * Detect seasonal patterns.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function detect_seasonal_patterns( $start_date, $end_date ) {
		global $wpdb;

		// Monthly patterns.
		$monthly = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					MONTH(pd.meta_value) as month,
					COUNT(p.ID) as bookings,
					COALESCE(SUM(pt.meta_value), 0) as revenue
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY MONTH(pd.meta_value)
				ORDER BY month ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$month_names = array(
			1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
			5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
			9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
		);

		$monthly_data = array_fill( 1, 12, array( 'bookings' => 0, 'revenue' => 0 ) );
		foreach ( $monthly as $row ) {
			$monthly_data[ (int) $row['month'] ] = array(
				'bookings' => (int) $row['bookings'],
				'revenue'  => (float) $row['revenue'],
			);
		}

		// Find peak and low months.
		$booking_values = array_column( $monthly_data, 'bookings' );
		$peak_month     = array_search( max( $booking_values ), $booking_values, true ) + 1;
		$low_month      = array_search( min( array_filter( $booking_values ) ), $booking_values, true ) + 1;

		// Day of week patterns.
		$weekly = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DAYOFWEEK(pd.meta_value) as day,
					COUNT(p.ID) as bookings,
					COALESCE(SUM(pt.meta_value), 0) as revenue
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY DAYOFWEEK(pd.meta_value)
				ORDER BY day ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$day_names = array( 1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday' );
		$weekly_data = array();
		foreach ( $weekly as $row ) {
			$weekly_data[ $day_names[ (int) $row['day'] ] ] = array(
				'bookings' => (int) $row['bookings'],
				'revenue'  => (float) $row['revenue'],
			);
		}

		// Calculate seasonality index.
		$total_bookings = array_sum( array_column( $monthly_data, 'bookings' ) );
		$avg_monthly    = $total_bookings / 12;
		$seasonality    = array();

		foreach ( $monthly_data as $month => $data ) {
			$seasonality[ $month_names[ $month ] ] = $avg_monthly > 0 ? round( $data['bookings'] / $avg_monthly, 2 ) : 1;
		}

		return array(
			'monthly'           => array(
				'labels' => array_values( $month_names ),
				'data'   => array_column( $monthly_data, 'bookings' ),
			),
			'weekly'            => array(
				'labels' => array_keys( $weekly_data ),
				'data'   => array_column( $weekly_data, 'bookings' ),
			),
			'seasonality_index' => $seasonality,
			'peak_month'        => $month_names[ $peak_month ] ?? 'Unknown',
			'low_month'         => $month_names[ $low_month ] ?? 'Unknown',
			'insights'          => $this->generate_seasonal_insights( $peak_month, $low_month, $seasonality, $month_names ),
		);
	}

	/**
	 * Detect anomalies.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function detect_anomalies( $start_date, $end_date ) {
		global $wpdb;

		// Get daily data.
		$daily = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					pd.meta_value as date,
					COUNT(p.ID) as bookings,
					COALESCE(SUM(pt.meta_value), 0) as revenue
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY pd.meta_value
				ORDER BY pd.meta_value ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$bookings = array_column( $daily, 'bookings' );
		$revenues = array_column( $daily, 'revenue' );
		$dates    = array_column( $daily, 'date' );

		// Calculate statistics.
		$booking_mean   = count( $bookings ) > 0 ? array_sum( $bookings ) / count( $bookings ) : 0;
		$booking_stddev = $this->calculate_std_dev( $bookings );
		$revenue_mean   = count( $revenues ) > 0 ? array_sum( $revenues ) / count( $revenues ) : 0;
		$revenue_stddev = $this->calculate_std_dev( $revenues );

		// Detect anomalies (values outside 2 standard deviations).
		$anomalies = array();
		$threshold = 2;

		foreach ( $daily as $index => $row ) {
			$booking_z = $booking_stddev > 0 ? ( (int) $row['bookings'] - $booking_mean ) / $booking_stddev : 0;
			$revenue_z = $revenue_stddev > 0 ? ( (float) $row['revenue'] - $revenue_mean ) / $revenue_stddev : 0;

			if ( abs( $booking_z ) > $threshold || abs( $revenue_z ) > $threshold ) {
				$anomalies[] = array(
					'date'         => $row['date'],
					'bookings'     => (int) $row['bookings'],
					'revenue'      => (float) $row['revenue'],
					'booking_z'    => round( $booking_z, 2 ),
					'revenue_z'    => round( $revenue_z, 2 ),
					'type'         => $booking_z > 0 || $revenue_z > 0 ? 'spike' : 'drop',
					'description'  => $this->describe_anomaly( $booking_z, $revenue_z ),
				);
			}
		}

		return array(
			'anomalies'  => $anomalies,
			'statistics' => array(
				'booking_mean'   => round( $booking_mean, 1 ),
				'booking_stddev' => round( $booking_stddev, 1 ),
				'revenue_mean'   => round( $revenue_mean, 2 ),
				'revenue_stddev' => round( $revenue_stddev, 2 ),
			),
			'chart_data' => array(
				'labels'   => $dates,
				'datasets' => array(
					array(
						'label' => __( 'Bookings', 'bkx-advanced-analytics' ),
						'data'  => $bookings,
					),
					array(
						'label' => __( 'Upper Bound', 'bkx-advanced-analytics' ),
						'data'  => array_fill( 0, count( $bookings ), $booking_mean + ( $threshold * $booking_stddev ) ),
						'borderDash' => array( 5, 5 ),
					),
					array(
						'label' => __( 'Lower Bound', 'bkx-advanced-analytics' ),
						'data'  => array_fill( 0, count( $bookings ), max( 0, $booking_mean - ( $threshold * $booking_stddev ) ) ),
						'borderDash' => array( 5, 5 ),
					),
				),
			),
		);
	}

	/**
	 * Detect trends.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function detect_trends( $start_date, $end_date ) {
		global $wpdb;

		// Get weekly data for smoother trends.
		$weekly = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					YEARWEEK(pd.meta_value) as week,
					COUNT(p.ID) as bookings,
					COALESCE(SUM(pt.meta_value), 0) as revenue,
					COUNT(DISTINCT pe.meta_value) as customers
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
				LEFT JOIN {$wpdb->postmeta} pe ON p.ID = pe.post_id AND pe.meta_key = 'customer_email'
				INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
				WHERE p.post_type = 'bkx_booking'
				AND pd.meta_value BETWEEN %s AND %s
				GROUP BY YEARWEEK(pd.meta_value)
				ORDER BY week ASC",
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$bookings  = array_column( $weekly, 'bookings' );
		$revenues  = array_column( $weekly, 'revenue' );
		$customers = array_column( $weekly, 'customers' );

		// Calculate linear regression for trends.
		$booking_trend  = $this->calculate_trend( $bookings );
		$revenue_trend  = $this->calculate_trend( $revenues );
		$customer_trend = $this->calculate_trend( $customers );

		// Moving averages (4-week).
		$booking_ma = $this->moving_average( $bookings, 4 );
		$revenue_ma = $this->moving_average( $revenues, 4 );

		return array(
			'trends'      => array(
				'bookings' => array(
					'direction' => $booking_trend['slope'] > 0 ? 'up' : ( $booking_trend['slope'] < 0 ? 'down' : 'flat' ),
					'slope'     => round( $booking_trend['slope'], 2 ),
					'strength'  => $this->trend_strength( $booking_trend['r_squared'] ),
				),
				'revenue'  => array(
					'direction' => $revenue_trend['slope'] > 0 ? 'up' : ( $revenue_trend['slope'] < 0 ? 'down' : 'flat' ),
					'slope'     => round( $revenue_trend['slope'], 2 ),
					'strength'  => $this->trend_strength( $revenue_trend['r_squared'] ),
				),
				'customers' => array(
					'direction' => $customer_trend['slope'] > 0 ? 'up' : ( $customer_trend['slope'] < 0 ? 'down' : 'flat' ),
					'slope'     => round( $customer_trend['slope'], 2 ),
					'strength'  => $this->trend_strength( $customer_trend['r_squared'] ),
				),
			),
			'chart_data'  => array(
				'labels'   => array_column( $weekly, 'week' ),
				'datasets' => array(
					array(
						'label' => __( 'Bookings', 'bkx-advanced-analytics' ),
						'data'  => $bookings,
					),
					array(
						'label' => __( 'Moving Average', 'bkx-advanced-analytics' ),
						'data'  => $booking_ma,
						'borderDash' => array( 5, 5 ),
					),
				),
			),
			'insights'    => $this->generate_trend_insights( $booking_trend, $revenue_trend, $customer_trend ),
		);
	}

	/**
	 * Calculate standard deviation.
	 *
	 * @param array $values Values.
	 * @return float
	 */
	private function calculate_std_dev( $values ) {
		$n = count( $values );
		if ( $n < 2 ) {
			return 0;
		}

		$mean     = array_sum( $values ) / $n;
		$variance = 0;

		foreach ( $values as $value ) {
			$variance += pow( $value - $mean, 2 );
		}

		return sqrt( $variance / ( $n - 1 ) );
	}

	/**
	 * Calculate linear regression trend.
	 *
	 * @param array $values Values.
	 * @return array
	 */
	private function calculate_trend( $values ) {
		$n = count( $values );
		if ( $n < 2 ) {
			return array( 'slope' => 0, 'intercept' => 0, 'r_squared' => 0 );
		}

		$sum_x  = 0;
		$sum_y  = 0;
		$sum_xy = 0;
		$sum_xx = 0;
		$sum_yy = 0;

		foreach ( $values as $i => $y ) {
			$x       = $i + 1;
			$sum_x  += $x;
			$sum_y  += $y;
			$sum_xy += $x * $y;
			$sum_xx += $x * $x;
			$sum_yy += $y * $y;
		}

		$denominator = ( $n * $sum_xx ) - ( $sum_x * $sum_x );
		if ( $denominator == 0 ) {
			return array( 'slope' => 0, 'intercept' => $sum_y / $n, 'r_squared' => 0 );
		}

		$slope     = ( ( $n * $sum_xy ) - ( $sum_x * $sum_y ) ) / $denominator;
		$intercept = ( $sum_y - ( $slope * $sum_x ) ) / $n;

		// R-squared.
		$ss_tot = 0;
		$ss_res = 0;
		$mean_y = $sum_y / $n;

		foreach ( $values as $i => $y ) {
			$x       = $i + 1;
			$y_pred  = $slope * $x + $intercept;
			$ss_res += pow( $y - $y_pred, 2 );
			$ss_tot += pow( $y - $mean_y, 2 );
		}

		$r_squared = $ss_tot > 0 ? 1 - ( $ss_res / $ss_tot ) : 0;

		return array(
			'slope'     => $slope,
			'intercept' => $intercept,
			'r_squared' => $r_squared,
		);
	}

	/**
	 * Calculate moving average.
	 *
	 * @param array $values Values.
	 * @param int   $window Window size.
	 * @return array
	 */
	private function moving_average( $values, $window ) {
		$ma    = array();
		$count = count( $values );

		for ( $i = 0; $i < $count; $i++ ) {
			if ( $i < $window - 1 ) {
				$ma[] = null;
			} else {
				$sum = 0;
				for ( $j = 0; $j < $window; $j++ ) {
					$sum += $values[ $i - $j ];
				}
				$ma[] = round( $sum / $window, 2 );
			}
		}

		return $ma;
	}

	/**
	 * Get trend strength label.
	 *
	 * @param float $r_squared R-squared value.
	 * @return string
	 */
	private function trend_strength( $r_squared ) {
		if ( $r_squared >= 0.7 ) {
			return __( 'Strong', 'bkx-advanced-analytics' );
		} elseif ( $r_squared >= 0.4 ) {
			return __( 'Moderate', 'bkx-advanced-analytics' );
		} else {
			return __( 'Weak', 'bkx-advanced-analytics' );
		}
	}

	/**
	 * Describe anomaly.
	 *
	 * @param float $booking_z Booking z-score.
	 * @param float $revenue_z Revenue z-score.
	 * @return string
	 */
	private function describe_anomaly( $booking_z, $revenue_z ) {
		if ( $booking_z > 2 && $revenue_z > 2 ) {
			return __( 'Unusually high bookings and revenue', 'bkx-advanced-analytics' );
		} elseif ( $booking_z > 2 ) {
			return __( 'Unusually high booking volume', 'bkx-advanced-analytics' );
		} elseif ( $revenue_z > 2 ) {
			return __( 'Unusually high revenue', 'bkx-advanced-analytics' );
		} elseif ( $booking_z < -2 && $revenue_z < -2 ) {
			return __( 'Unusually low bookings and revenue', 'bkx-advanced-analytics' );
		} elseif ( $booking_z < -2 ) {
			return __( 'Unusually low booking volume', 'bkx-advanced-analytics' );
		} else {
			return __( 'Unusually low revenue', 'bkx-advanced-analytics' );
		}
	}

	/**
	 * Generate seasonal insights.
	 *
	 * @param int    $peak_month   Peak month.
	 * @param int    $low_month    Low month.
	 * @param array  $seasonality  Seasonality index.
	 * @param array  $month_names  Month names.
	 * @return array
	 */
	private function generate_seasonal_insights( $peak_month, $low_month, $seasonality, $month_names ) {
		$insights = array();

		if ( isset( $month_names[ $peak_month ] ) ) {
			$insights[] = array(
				'type'    => 'info',
				'message' => sprintf(
					/* translators: %s: month name */
					__( 'Peak season is %s. Plan for increased capacity and staff during this period.', 'bkx-advanced-analytics' ),
					$month_names[ $peak_month ]
				),
			);
		}

		if ( isset( $month_names[ $low_month ] ) ) {
			$insights[] = array(
				'type'    => 'info',
				'message' => sprintf(
					/* translators: %s: month name */
					__( 'Lowest activity is in %s. Consider promotions to boost bookings.', 'bkx-advanced-analytics' ),
					$month_names[ $low_month ]
				),
			);
		}

		return $insights;
	}

	/**
	 * Generate trend insights.
	 *
	 * @param array $booking_trend  Booking trend.
	 * @param array $revenue_trend  Revenue trend.
	 * @param array $customer_trend Customer trend.
	 * @return array
	 */
	private function generate_trend_insights( $booking_trend, $revenue_trend, $customer_trend ) {
		$insights = array();

		if ( $booking_trend['slope'] > 0 && $booking_trend['r_squared'] > 0.5 ) {
			$insights[] = array(
				'type'    => 'positive',
				'message' => __( 'Strong upward trend in bookings. Business is growing!', 'bkx-advanced-analytics' ),
			);
		} elseif ( $booking_trend['slope'] < 0 && $booking_trend['r_squared'] > 0.5 ) {
			$insights[] = array(
				'type'    => 'warning',
				'message' => __( 'Downward trend in bookings detected. Review marketing strategies.', 'bkx-advanced-analytics' ),
			);
		}

		if ( $revenue_trend['slope'] > 0 && $customer_trend['slope'] < 0 ) {
			$insights[] = array(
				'type'    => 'info',
				'message' => __( 'Revenue growing while customer count declining - existing customers spending more.', 'bkx-advanced-analytics' ),
			);
		}

		return $insights;
	}
}
