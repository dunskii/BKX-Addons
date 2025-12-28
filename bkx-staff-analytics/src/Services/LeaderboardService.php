<?php
/**
 * Leaderboard Service.
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
 * LeaderboardService Class.
 */
class LeaderboardService {

	/**
	 * Get rankings.
	 *
	 * @param string $metric Metric to rank by.
	 * @param string $period Time period.
	 * @param int    $limit  Number of results.
	 * @return array
	 */
	public function get_rankings( $metric = 'revenue', $period = 'month', $limit = 10 ) {
		global $wpdb;

		$dates = $this->get_period_dates( $period );
		$table = $wpdb->prefix . 'bkx_staff_metrics';

		$order_column = $this->get_order_column( $metric );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					m.staff_id,
					p.post_title as staff_name,
					SUM(m.total_bookings) as total_bookings,
					SUM(m.completed_bookings) as completed_bookings,
					SUM(m.total_revenue) as total_revenue,
					SUM(m.total_hours) as total_hours,
					AVG(m.avg_rating) as avg_rating,
					SUM(m.total_reviews) as total_reviews,
					SUM(m.new_customers) as new_customers,
					AVG(m.utilization_rate) as utilization_rate
				FROM {$table} m
				LEFT JOIN {$wpdb->posts} p ON m.staff_id = p.ID
				WHERE m.metric_date BETWEEN %s AND %s
				GROUP BY m.staff_id
				ORDER BY {$order_column} DESC
				LIMIT %d",
				$dates['start'],
				$dates['end'],
				$limit
			),
			ARRAY_A
		);

		// Add ranking and calculated fields.
		$rank = 1;
		foreach ( $results as &$row ) {
			$row['rank'] = $rank++;

			// Calculate completion rate.
			$row['completion_rate'] = 0;
			if ( $row['total_bookings'] > 0 ) {
				$row['completion_rate'] = round( ( $row['completed_bookings'] / $row['total_bookings'] ) * 100, 1 );
			}

			// Calculate revenue per hour.
			$row['revenue_per_hour'] = 0;
			if ( $row['total_hours'] > 0 ) {
				$row['revenue_per_hour'] = round( $row['total_revenue'] / $row['total_hours'], 2 );
			}

			// Format numbers.
			$row['total_revenue']    = round( floatval( $row['total_revenue'] ), 2 );
			$row['total_hours']      = round( floatval( $row['total_hours'] ), 2 );
			$row['avg_rating']       = round( floatval( $row['avg_rating'] ), 2 );
			$row['utilization_rate'] = round( floatval( $row['utilization_rate'] ), 1 );

			// Get staff thumbnail.
			$row['thumbnail'] = get_the_post_thumbnail_url( $row['staff_id'], 'thumbnail' );
		}

		return array(
			'rankings' => $results,
			'metric'   => $metric,
			'period'   => array(
				'start' => $dates['start'],
				'end'   => $dates['end'],
			),
		);
	}

	/**
	 * Get metric trend comparison.
	 *
	 * @param string $metric Metric to compare.
	 * @param string $period Time period.
	 * @return array
	 */
	public function get_metric_trends( $metric = 'revenue', $period = 'month' ) {
		global $wpdb;

		$dates      = $this->get_period_dates( $period );
		$prev_dates = $this->get_previous_period_dates( $dates['start'], $dates['end'] );
		$table      = $wpdb->prefix . 'bkx_staff_metrics';

		$column = $this->get_order_column( $metric );

		// Get current period.
		$current = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT staff_id, SUM({$column}) as value
				FROM {$table}
				WHERE metric_date BETWEEN %s AND %s
				GROUP BY staff_id",
				$dates['start'],
				$dates['end']
			),
			OBJECT_K
		);

		// Get previous period.
		$previous = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT staff_id, SUM({$column}) as value
				FROM {$table}
				WHERE metric_date BETWEEN %s AND %s
				GROUP BY staff_id",
				$prev_dates['start'],
				$prev_dates['end']
			),
			OBJECT_K
		);

		// Calculate changes.
		$trends = array();
		foreach ( $current as $staff_id => $row ) {
			$prev_value = isset( $previous[ $staff_id ] ) ? floatval( $previous[ $staff_id ]->value ) : 0;
			$curr_value = floatval( $row->value );

			$change         = $curr_value - $prev_value;
			$change_percent = $prev_value > 0 ? round( ( $change / $prev_value ) * 100, 1 ) : 0;

			$trends[ $staff_id ] = array(
				'staff_id'       => $staff_id,
				'staff_name'     => get_the_title( $staff_id ),
				'current_value'  => $curr_value,
				'previous_value' => $prev_value,
				'change'         => $change,
				'change_percent' => $change_percent,
				'trending'       => $change > 0 ? 'up' : ( $change < 0 ? 'down' : 'flat' ),
			);
		}

		// Sort by change percentage.
		usort(
			$trends,
			function ( $a, $b ) {
				return $b['change_percent'] <=> $a['change_percent'];
			}
		);

		return array(
			'trends' => $trends,
			'metric' => $metric,
			'period' => array(
				'current'  => $dates,
				'previous' => $prev_dates,
			),
		);
	}

	/**
	 * Get top performers.
	 *
	 * @param string $period Time period.
	 * @return array
	 */
	public function get_top_performers( $period = 'month' ) {
		return array(
			'revenue'    => $this->get_rankings( 'revenue', $period, 3 ),
			'bookings'   => $this->get_rankings( 'bookings', $period, 3 ),
			'rating'     => $this->get_rankings( 'rating', $period, 3 ),
			'customers'  => $this->get_rankings( 'new_customers', $period, 3 ),
		);
	}

	/**
	 * Get staff rank for a specific metric.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $metric   Metric.
	 * @param string $period   Time period.
	 * @return array
	 */
	public function get_staff_rank( $staff_id, $metric = 'revenue', $period = 'month' ) {
		$all_rankings = $this->get_rankings( $metric, $period, 1000 );

		foreach ( $all_rankings['rankings'] as $ranking ) {
			if ( absint( $ranking['staff_id'] ) === absint( $staff_id ) ) {
				return array(
					'rank'        => $ranking['rank'],
					'total_staff' => count( $all_rankings['rankings'] ),
					'percentile'  => round( ( 1 - ( $ranking['rank'] / count( $all_rankings['rankings'] ) ) ) * 100, 1 ),
					'data'        => $ranking,
				);
			}
		}

		return array(
			'rank'        => null,
			'total_staff' => count( $all_rankings['rankings'] ),
			'percentile'  => 0,
			'data'        => null,
		);
	}

	/**
	 * Get column name for ordering.
	 *
	 * @param string $metric Metric name.
	 * @return string
	 */
	private function get_order_column( $metric ) {
		$columns = array(
			'revenue'      => 'total_revenue',
			'bookings'     => 'completed_bookings',
			'hours'        => 'total_hours',
			'rating'       => 'avg_rating',
			'reviews'      => 'total_reviews',
			'new_customers' => 'new_customers',
			'utilization'  => 'utilization_rate',
		);

		return $columns[ $metric ] ?? 'total_revenue';
	}

	/**
	 * Get period dates.
	 *
	 * @param string $period Period.
	 * @return array
	 */
	private function get_period_dates( $period ) {
		$now = current_time( 'timestamp' );

		switch ( $period ) {
			case 'week':
				return array(
					'start' => gmdate( 'Y-m-d', strtotime( 'monday this week', $now ) ),
					'end'   => gmdate( 'Y-m-d', strtotime( 'sunday this week', $now ) ),
				);

			case 'month':
				return array(
					'start' => gmdate( 'Y-m-01', $now ),
					'end'   => gmdate( 'Y-m-t', $now ),
				);

			case 'quarter':
				$quarter = ceil( gmdate( 'n', $now ) / 3 );
				$year    = gmdate( 'Y', $now );
				return array(
					'start' => gmdate( 'Y-m-d', strtotime( "$year-" . ( ( $quarter - 1 ) * 3 + 1 ) . '-01' ) ),
					'end'   => gmdate( 'Y-m-t', strtotime( "$year-" . ( $quarter * 3 ) . '-01' ) ),
				);

			case 'year':
				return array(
					'start' => gmdate( 'Y-01-01', $now ),
					'end'   => gmdate( 'Y-12-31', $now ),
				);

			default:
				return array(
					'start' => gmdate( 'Y-m-01', $now ),
					'end'   => gmdate( 'Y-m-t', $now ),
				);
		}
	}

	/**
	 * Get previous period dates.
	 *
	 * @param string $curr_start Current start.
	 * @param string $curr_end   Current end.
	 * @return array
	 */
	private function get_previous_period_dates( $curr_start, $curr_end ) {
		$start = strtotime( $curr_start );
		$end   = strtotime( $curr_end );
		$diff  = $end - $start + DAY_IN_SECONDS;

		return array(
			'start' => gmdate( 'Y-m-d', $start - $diff ),
			'end'   => gmdate( 'Y-m-d', $start - DAY_IN_SECONDS ),
		);
	}
}
