<?php
/**
 * Forecasting Service Class.
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
 * ForecastingService Class.
 */
class ForecastingService {

	/**
	 * Forecast revenue.
	 *
	 * @param int $days Number of days to forecast.
	 * @return array
	 */
	public function forecast_revenue( $days = 30 ) {
		$historical = $this->get_historical_data( 'revenue', 90 );
		return $this->generate_forecast( $historical, $days, 'revenue' );
	}

	/**
	 * Forecast bookings.
	 *
	 * @param int $days Number of days to forecast.
	 * @return array
	 */
	public function forecast_bookings( $days = 30 ) {
		$historical = $this->get_historical_data( 'bookings', 90 );
		return $this->generate_forecast( $historical, $days, 'bookings' );
	}

	/**
	 * Get historical data for forecasting.
	 *
	 * @param string $metric    Metric type.
	 * @param int    $days_back Number of days back.
	 * @return array
	 */
	private function get_historical_data( $metric, $days_back ) {
		global $wpdb;

		$start_date = gmdate( 'Y-m-d', strtotime( "-{$days_back} days" ) );
		$end_date   = gmdate( 'Y-m-d' );

		if ( 'revenue' === $metric ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						pd.meta_value as date,
						COALESCE(SUM(pt.meta_value), 0) as value
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pd ON p.ID = pd.post_id AND pd.meta_key = 'booking_date'
					LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'booking_total'
					WHERE p.post_type = 'bkx_booking'
					AND p.post_status IN ('bkx-ack', 'bkx-completed')
					AND pd.meta_value BETWEEN %s AND %s
					GROUP BY pd.meta_value
					ORDER BY pd.meta_value ASC",
					$start_date,
					$end_date
				),
				ARRAY_A
			);
		} else {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT
						pd.meta_value as date,
						COUNT(p.ID) as value
					FROM {$wpdb->posts} p
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
		}

		// Fill gaps with zeros.
		$data    = array();
		$current = strtotime( $start_date );
		$end     = strtotime( $end_date );
		$indexed = array();

		foreach ( $results as $row ) {
			$indexed[ $row['date'] ] = (float) $row['value'];
		}

		while ( $current <= $end ) {
			$date          = gmdate( 'Y-m-d', $current );
			$data[ $date ] = $indexed[ $date ] ?? 0;
			$current       = strtotime( '+1 day', $current );
		}

		return $data;
	}

	/**
	 * Generate forecast using simple exponential smoothing with seasonality.
	 *
	 * @param array  $historical Historical data.
	 * @param int    $days       Days to forecast.
	 * @param string $metric     Metric type.
	 * @return array
	 */
	private function generate_forecast( $historical, $days, $metric ) {
		$values = array_values( $historical );
		$dates  = array_keys( $historical );

		if ( count( $values ) < 14 ) {
			return $this->simple_forecast( $values, $days, $metric );
		}

		// Calculate weekly seasonality indices.
		$weekly_pattern = $this->calculate_weekly_pattern( $values );

		// Triple exponential smoothing parameters.
		$alpha = 0.3; // Level smoothing.
		$beta  = 0.1; // Trend smoothing.
		$gamma = 0.2; // Seasonality smoothing.

		// Initialize.
		$level = array_sum( array_slice( $values, 0, 7 ) ) / 7;
		$trend = ( array_sum( array_slice( $values, 7, 7 ) ) - array_sum( array_slice( $values, 0, 7 ) ) ) / 49;

		// Seasonal factors (7 days).
		$seasonal = $weekly_pattern;

		// Smooth historical data.
		$smoothed = array();
		$n        = count( $values );

		for ( $i = 0; $i < $n; $i++ ) {
			$day_index   = $i % 7;
			$observation = $values[ $i ];

			if ( $seasonal[ $day_index ] > 0 ) {
				$new_level = $alpha * ( $observation / $seasonal[ $day_index ] ) + ( 1 - $alpha ) * ( $level + $trend );
			} else {
				$new_level = $alpha * $observation + ( 1 - $alpha ) * ( $level + $trend );
			}

			$new_trend = $beta * ( $new_level - $level ) + ( 1 - $beta ) * $trend;

			if ( $new_level > 0 ) {
				$seasonal[ $day_index ] = $gamma * ( $observation / $new_level ) + ( 1 - $gamma ) * $seasonal[ $day_index ];
			}

			$level = $new_level;
			$trend = $new_trend;

			$smoothed[] = ( $level + $trend ) * $seasonal[ $day_index ];
		}

		// Generate forecast.
		$forecast         = array();
		$upper_bound      = array();
		$lower_bound      = array();
		$labels           = array();
		$last_date        = strtotime( end( $dates ) );
		$std_dev          = $this->calculate_std_dev( $values );
		$confidence_scale = 1.96; // 95% confidence interval.

		for ( $i = 1; $i <= $days; $i++ ) {
			$forecast_date = strtotime( "+{$i} day", $last_date );
			$day_index     = ( $n + $i - 1 ) % 7;

			$point_forecast = ( $level + $trend * $i ) * $seasonal[ $day_index ];
			$point_forecast = max( 0, $point_forecast );

			// Widen confidence interval over time.
			$interval_width = $confidence_scale * $std_dev * sqrt( 1 + $i / $n );

			$forecast[]    = round( $point_forecast, 2 );
			$upper_bound[] = round( $point_forecast + $interval_width, 2 );
			$lower_bound[] = round( max( 0, $point_forecast - $interval_width ), 2 );
			$labels[]      = gmdate( 'M j', $forecast_date );
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'label'           => __( 'Forecast', 'bkx-business-intelligence' ),
					'data'            => $forecast,
					'borderColor'     => '#0073aa',
					'backgroundColor' => 'transparent',
					'borderDash'      => array( 5, 5 ),
				),
				array(
					'label'           => __( 'Upper Bound', 'bkx-business-intelligence' ),
					'data'            => $upper_bound,
					'borderColor'     => 'rgba(0, 115, 170, 0.3)',
					'backgroundColor' => 'rgba(0, 115, 170, 0.1)',
					'fill'            => '+1',
				),
				array(
					'label'           => __( 'Lower Bound', 'bkx-business-intelligence' ),
					'data'            => $lower_bound,
					'borderColor'     => 'rgba(0, 115, 170, 0.3)',
					'backgroundColor' => 'transparent',
				),
			),
			'summary'  => array(
				'total_forecast'    => array_sum( $forecast ),
				'avg_daily'         => round( array_sum( $forecast ) / $days, 2 ),
				'confidence_level'  => 95,
				'model'             => 'Triple Exponential Smoothing',
			),
		);
	}

	/**
	 * Simple forecast for insufficient data.
	 *
	 * @param array  $values Historical values.
	 * @param int    $days   Days to forecast.
	 * @param string $metric Metric type.
	 * @return array
	 */
	private function simple_forecast( $values, $days, $metric ) {
		$avg      = count( $values ) > 0 ? array_sum( $values ) / count( $values ) : 0;
		$std_dev  = $this->calculate_std_dev( $values );
		$forecast = array();
		$labels   = array();
		$today    = strtotime( 'today' );

		for ( $i = 1; $i <= $days; $i++ ) {
			$forecast_date = strtotime( "+{$i} day", $today );
			$forecast[]    = round( $avg, 2 );
			$labels[]      = gmdate( 'M j', $forecast_date );
		}

		return array(
			'labels'   => $labels,
			'datasets' => array(
				array(
					'label'           => __( 'Forecast', 'bkx-business-intelligence' ),
					'data'            => $forecast,
					'borderColor'     => '#0073aa',
					'backgroundColor' => 'transparent',
					'borderDash'      => array( 5, 5 ),
				),
			),
			'summary'  => array(
				'total_forecast'   => round( $avg * $days, 2 ),
				'avg_daily'        => round( $avg, 2 ),
				'confidence_level' => 50,
				'model'            => 'Simple Average (insufficient data)',
				'note'             => __( 'More historical data needed for accurate forecasting.', 'bkx-business-intelligence' ),
			),
		);
	}

	/**
	 * Calculate weekly pattern (seasonality indices).
	 *
	 * @param array $values Historical values.
	 * @return array
	 */
	private function calculate_weekly_pattern( $values ) {
		$day_totals = array_fill( 0, 7, 0 );
		$day_counts = array_fill( 0, 7, 0 );

		foreach ( $values as $i => $value ) {
			$day_index = $i % 7;
			$day_totals[ $day_index ] += $value;
			$day_counts[ $day_index ]++;
		}

		$overall_avg = array_sum( $values ) / max( 1, count( $values ) );
		$pattern     = array();

		for ( $i = 0; $i < 7; $i++ ) {
			$day_avg = $day_counts[ $i ] > 0 ? $day_totals[ $i ] / $day_counts[ $i ] : 0;
			$pattern[ $i ] = $overall_avg > 0 ? $day_avg / $overall_avg : 1;
		}

		return $pattern;
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
	 * Save forecast to database.
	 *
	 * @param string $metric   Metric type.
	 * @param array  $forecast Forecast data.
	 * @return int|false
	 */
	public function save_forecast( $metric, $forecast ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_bi_forecasts';

		return $wpdb->insert(
			$table,
			array(
				'metric_type'      => $metric,
				'forecast_date'    => current_time( 'mysql' ),
				'forecast_data'    => wp_json_encode( $forecast ),
				'confidence_level' => $forecast['summary']['confidence_level'] ?? 0,
			),
			array( '%s', '%s', '%s', '%d' )
		);
	}

	/**
	 * Get forecast accuracy (compare past forecasts to actuals).
	 *
	 * @param string $metric Metric type.
	 * @return array
	 */
	public function get_forecast_accuracy( $metric ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_bi_forecasts';

		// Get forecasts from 30 days ago.
		$forecast_date = gmdate( 'Y-m-d', strtotime( '-30 days' ) );

		$forecast = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE metric_type = %s
				AND DATE(forecast_date) = %s
				LIMIT 1",
				$metric,
				$forecast_date
			)
		);

		if ( ! $forecast ) {
			return array(
				'available' => false,
				'message'   => __( 'No historical forecasts available for comparison.', 'bkx-business-intelligence' ),
			);
		}

		$forecast_data = json_decode( $forecast->forecast_data, true );
		$predicted     = $forecast_data['datasets'][0]['data'] ?? array();

		// Get actual data for the forecasted period.
		$actual_data = $this->get_historical_data( $metric, 30 );
		$actuals     = array_values( $actual_data );

		// Calculate MAPE (Mean Absolute Percentage Error).
		$errors = array();
		$n      = min( count( $predicted ), count( $actuals ) );

		for ( $i = 0; $i < $n; $i++ ) {
			if ( $actuals[ $i ] > 0 ) {
				$errors[] = abs( ( $actuals[ $i ] - $predicted[ $i ] ) / $actuals[ $i ] );
			}
		}

		$mape     = count( $errors ) > 0 ? ( array_sum( $errors ) / count( $errors ) ) * 100 : 0;
		$accuracy = max( 0, 100 - $mape );

		return array(
			'available' => true,
			'mape'      => round( $mape, 2 ),
			'accuracy'  => round( $accuracy, 2 ),
			'rating'    => $this->get_accuracy_rating( $accuracy ),
		);
	}

	/**
	 * Get accuracy rating label.
	 *
	 * @param float $accuracy Accuracy percentage.
	 * @return string
	 */
	private function get_accuracy_rating( $accuracy ) {
		if ( $accuracy >= 90 ) {
			return __( 'Excellent', 'bkx-business-intelligence' );
		} elseif ( $accuracy >= 80 ) {
			return __( 'Good', 'bkx-business-intelligence' );
		} elseif ( $accuracy >= 70 ) {
			return __( 'Fair', 'bkx-business-intelligence' );
		} else {
			return __( 'Needs Improvement', 'bkx-business-intelligence' );
		}
	}
}
