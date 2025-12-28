<?php
/**
 * Performance Metrics Service.
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
 * PerformanceMetrics Class.
 */
class PerformanceMetrics {

	/**
	 * Record a new booking.
	 *
	 * @param int $booking_id Booking ID.
	 */
	public function record_booking( $booking_id ) {
		$staff_id     = get_post_meta( $booking_id, 'seat_id', true );
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );

		if ( ! $staff_id || ! $booking_date ) {
			return;
		}

		$this->increment_metric( $staff_id, $booking_date, 'total_bookings' );

		// Check if new or returning customer.
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		if ( $customer_email ) {
			$is_new = $this->is_new_customer( $staff_id, $customer_email, $booking_id );
			if ( $is_new ) {
				$this->increment_metric( $staff_id, $booking_date, 'new_customers' );
			} else {
				$this->increment_metric( $staff_id, $booking_date, 'returning_customers' );
			}
		}
	}

	/**
	 * Update booking status in metrics.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 */
	public function update_booking_status( $booking_id, $new_status, $old_status ) {
		$staff_id     = get_post_meta( $booking_id, 'seat_id', true );
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );

		if ( ! $staff_id || ! $booking_date ) {
			return;
		}

		// Handle completed bookings.
		if ( 'bkx-completed' === $new_status ) {
			$this->increment_metric( $staff_id, $booking_date, 'completed_bookings' );

			// Add revenue.
			$total = $this->get_booking_total( $booking_id );
			$this->add_revenue( $staff_id, $booking_date, $total );

			// Add duration.
			$duration = $this->get_booking_duration( $booking_id );
			$hours    = $duration / 60;
			$this->add_hours( $staff_id, $booking_date, $hours );
		}

		// Handle cancelled bookings.
		if ( 'bkx-cancelled' === $new_status ) {
			$this->increment_metric( $staff_id, $booking_date, 'cancelled_bookings' );
		}

		// Handle no-shows.
		if ( 'bkx-missed' === $new_status ) {
			$this->increment_metric( $staff_id, $booking_date, 'no_show_bookings' );
		}
	}

	/**
	 * Get staff metrics.
	 *
	 * @param int    $staff_id Staff ID (0 for all staff).
	 * @param string $period   Period: day, week, month, quarter, year, custom.
	 * @param string $start    Start date for custom period.
	 * @param string $end      End date for custom period.
	 * @return array
	 */
	public function get_staff_metrics( $staff_id = 0, $period = 'month', $start = '', $end = '' ) {
		global $wpdb;

		$dates = $this->get_period_dates( $period, $start, $end );
		$table = $wpdb->prefix . 'bkx_staff_metrics';

		$where = $wpdb->prepare(
			"WHERE metric_date BETWEEN %s AND %s",
			$dates['start'],
			$dates['end']
		);

		if ( $staff_id > 0 ) {
			$where .= $wpdb->prepare( " AND staff_id = %d", $staff_id );
		}

		// Aggregate metrics.
		$results = $wpdb->get_row(
			"SELECT
				SUM(total_bookings) as total_bookings,
				SUM(completed_bookings) as completed_bookings,
				SUM(cancelled_bookings) as cancelled_bookings,
				SUM(no_show_bookings) as no_show_bookings,
				SUM(total_revenue) as total_revenue,
				SUM(total_hours) as total_hours,
				AVG(avg_rating) as avg_rating,
				SUM(total_reviews) as total_reviews,
				SUM(new_customers) as new_customers,
				SUM(returning_customers) as returning_customers,
				AVG(utilization_rate) as utilization_rate
			FROM {$table}
			{$where}",
			ARRAY_A
		);

		// Get daily breakdown.
		$daily = $wpdb->get_results(
			"SELECT
				metric_date,
				SUM(total_bookings) as bookings,
				SUM(completed_bookings) as completed,
				SUM(total_revenue) as revenue,
				SUM(total_hours) as hours
			FROM {$table}
			{$where}
			GROUP BY metric_date
			ORDER BY metric_date ASC",
			ARRAY_A
		);

		// Get comparison with previous period.
		$prev_dates = $this->get_previous_period_dates( $period, $dates['start'], $dates['end'] );
		$prev_where = $wpdb->prepare(
			"WHERE metric_date BETWEEN %s AND %s",
			$prev_dates['start'],
			$prev_dates['end']
		);

		if ( $staff_id > 0 ) {
			$prev_where .= $wpdb->prepare( " AND staff_id = %d", $staff_id );
		}

		$prev_results = $wpdb->get_row(
			"SELECT
				SUM(total_revenue) as total_revenue,
				SUM(total_bookings) as total_bookings
			FROM {$table}
			{$prev_where}",
			ARRAY_A
		);

		// Calculate growth percentages.
		$revenue_growth = 0;
		$booking_growth = 0;

		if ( $prev_results && $prev_results['total_revenue'] > 0 ) {
			$revenue_growth = round(
				( ( $results['total_revenue'] - $prev_results['total_revenue'] ) / $prev_results['total_revenue'] ) * 100,
				1
			);
		}

		if ( $prev_results && $prev_results['total_bookings'] > 0 ) {
			$booking_growth = round(
				( ( $results['total_bookings'] - $prev_results['total_bookings'] ) / $prev_results['total_bookings'] ) * 100,
				1
			);
		}

		// Calculate completion rate.
		$completion_rate = 0;
		if ( $results['total_bookings'] > 0 ) {
			$completion_rate = round( ( $results['completed_bookings'] / $results['total_bookings'] ) * 100, 1 );
		}

		// Calculate cancellation rate.
		$cancellation_rate = 0;
		if ( $results['total_bookings'] > 0 ) {
			$cancellation_rate = round( ( $results['cancelled_bookings'] / $results['total_bookings'] ) * 100, 1 );
		}

		// Calculate average booking value.
		$avg_booking_value = 0;
		if ( $results['completed_bookings'] > 0 ) {
			$avg_booking_value = round( $results['total_revenue'] / $results['completed_bookings'], 2 );
		}

		// Calculate revenue per hour.
		$revenue_per_hour = 0;
		if ( $results['total_hours'] > 0 ) {
			$revenue_per_hour = round( $results['total_revenue'] / $results['total_hours'], 2 );
		}

		return array(
			'summary'           => array(
				'total_bookings'     => (int) $results['total_bookings'],
				'completed_bookings' => (int) $results['completed_bookings'],
				'cancelled_bookings' => (int) $results['cancelled_bookings'],
				'no_show_bookings'   => (int) $results['no_show_bookings'],
				'total_revenue'      => (float) $results['total_revenue'],
				'total_hours'        => (float) $results['total_hours'],
				'avg_rating'         => round( (float) $results['avg_rating'], 2 ),
				'total_reviews'      => (int) $results['total_reviews'],
				'new_customers'      => (int) $results['new_customers'],
				'returning_customers' => (int) $results['returning_customers'],
				'utilization_rate'   => round( (float) $results['utilization_rate'], 1 ),
			),
			'calculated'        => array(
				'completion_rate'    => $completion_rate,
				'cancellation_rate'  => $cancellation_rate,
				'avg_booking_value'  => $avg_booking_value,
				'revenue_per_hour'   => $revenue_per_hour,
			),
			'growth'            => array(
				'revenue'  => $revenue_growth,
				'bookings' => $booking_growth,
			),
			'daily'             => $daily,
			'period'            => array(
				'start' => $dates['start'],
				'end'   => $dates['end'],
			),
		);
	}

	/**
	 * Aggregate metrics for a specific date.
	 *
	 * @param string $date Date to aggregate (Y-m-d format).
	 */
	public function aggregate_metrics( $date ) {
		global $wpdb;

		// Get all staff.
		$staff = get_posts(
			array(
				'post_type'      => 'bkx_seat',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			)
		);

		foreach ( $staff as $staff_id ) {
			$this->aggregate_staff_metrics( $staff_id, $date );
		}
	}

	/**
	 * Aggregate metrics for a specific staff member and date.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $date     Date (Y-m-d format).
	 */
	private function aggregate_staff_metrics( $staff_id, $date ) {
		global $wpdb;

		// Get bookings for this staff on this date.
		$bookings = get_posts(
			array(
				'post_type'      => 'bkx_booking',
				'posts_per_page' => -1,
				'post_status'    => array( 'bkx-pending', 'bkx-ack', 'bkx-completed', 'bkx-cancelled', 'bkx-missed' ),
				'meta_query'     => array(
					array(
						'key'   => 'seat_id',
						'value' => $staff_id,
					),
					array(
						'key'   => 'booking_date',
						'value' => $date,
					),
				),
			)
		);

		$metrics = array(
			'total_bookings'      => 0,
			'completed_bookings'  => 0,
			'cancelled_bookings'  => 0,
			'no_show_bookings'    => 0,
			'total_revenue'       => 0,
			'total_hours'         => 0,
			'new_customers'       => 0,
			'returning_customers' => 0,
		);

		$seen_customers = array();

		foreach ( $bookings as $booking ) {
			$metrics['total_bookings']++;

			$status = $booking->post_status;

			if ( 'bkx-completed' === $status ) {
				$metrics['completed_bookings']++;
				$metrics['total_revenue'] += $this->get_booking_total( $booking->ID );
				$metrics['total_hours']   += $this->get_booking_duration( $booking->ID ) / 60;
			} elseif ( 'bkx-cancelled' === $status ) {
				$metrics['cancelled_bookings']++;
			} elseif ( 'bkx-missed' === $status ) {
				$metrics['no_show_bookings']++;
			}

			// Track customer type.
			$customer_email = get_post_meta( $booking->ID, 'customer_email', true );
			if ( $customer_email && ! isset( $seen_customers[ $customer_email ] ) ) {
				$seen_customers[ $customer_email ] = true;
				if ( $this->is_new_customer( $staff_id, $customer_email, $booking->ID ) ) {
					$metrics['new_customers']++;
				} else {
					$metrics['returning_customers']++;
				}
			}
		}

		// Get reviews for this date.
		$table_reviews = $wpdb->prefix . 'bkx_staff_reviews';
		$reviews       = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
				FROM {$table_reviews}
				WHERE staff_id = %d AND DATE(reviewed_at) = %s AND is_approved = 1",
				$staff_id,
				$date
			),
			ARRAY_A
		);

		$metrics['avg_rating']    = $reviews['avg_rating'] ? round( $reviews['avg_rating'], 2 ) : null;
		$metrics['total_reviews'] = (int) $reviews['total_reviews'];

		// Calculate utilization rate.
		$metrics['utilization_rate'] = $this->calculate_utilization( $staff_id, $date, $metrics['total_hours'] );

		// Insert or update metrics.
		$table = $wpdb->prefix . 'bkx_staff_metrics';

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE staff_id = %d AND metric_date = %s",
				$staff_id,
				$date
			)
		);

		if ( $existing ) {
			$wpdb->update(
				$table,
				$metrics,
				array(
					'staff_id'    => $staff_id,
					'metric_date' => $date,
				),
				array( '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%d', '%d', '%d', '%f' ),
				array( '%d', '%s' )
			);
		} else {
			$wpdb->insert(
				$table,
				array_merge(
					array(
						'staff_id'    => $staff_id,
						'metric_date' => $date,
					),
					$metrics
				),
				array( '%d', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%d', '%d', '%d', '%f' )
			);
		}
	}

	/**
	 * Increment a metric.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $date     Date.
	 * @param string $metric   Metric name.
	 * @param int    $value    Value to add.
	 */
	private function increment_metric( $staff_id, $date, $metric, $value = 1 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_metrics';

		// Ensure record exists.
		$this->ensure_metric_record( $staff_id, $date );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET {$metric} = {$metric} + %d WHERE staff_id = %d AND metric_date = %s",
				$value,
				$staff_id,
				$date
			)
		);
	}

	/**
	 * Add revenue.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $date     Date.
	 * @param float  $amount   Amount.
	 */
	private function add_revenue( $staff_id, $date, $amount ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_metrics';

		$this->ensure_metric_record( $staff_id, $date );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET total_revenue = total_revenue + %f WHERE staff_id = %d AND metric_date = %s",
				$amount,
				$staff_id,
				$date
			)
		);
	}

	/**
	 * Add hours.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $date     Date.
	 * @param float  $hours    Hours.
	 */
	private function add_hours( $staff_id, $date, $hours ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_metrics';

		$this->ensure_metric_record( $staff_id, $date );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET total_hours = total_hours + %f WHERE staff_id = %d AND metric_date = %s",
				$hours,
				$staff_id,
				$date
			)
		);
	}

	/**
	 * Ensure metric record exists.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $date     Date.
	 */
	private function ensure_metric_record( $staff_id, $date ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_staff_metrics';

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE staff_id = %d AND metric_date = %s",
				$staff_id,
				$date
			)
		);

		if ( ! $exists ) {
			$wpdb->insert(
				$table,
				array(
					'staff_id'    => $staff_id,
					'metric_date' => $date,
				),
				array( '%d', '%s' )
			);
		}
	}

	/**
	 * Check if customer is new to this staff member.
	 *
	 * @param int    $staff_id       Staff ID.
	 * @param string $customer_email Customer email.
	 * @param int    $exclude_id     Booking ID to exclude.
	 * @return bool
	 */
	private function is_new_customer( $staff_id, $customer_email, $exclude_id ) {
		$existing = get_posts(
			array(
				'post_type'      => 'bkx_booking',
				'posts_per_page' => 1,
				'post_status'    => array( 'bkx-pending', 'bkx-ack', 'bkx-completed' ),
				'post__not_in'   => array( $exclude_id ),
				'meta_query'     => array(
					array(
						'key'   => 'seat_id',
						'value' => $staff_id,
					),
					array(
						'key'   => 'customer_email',
						'value' => $customer_email,
					),
				),
			)
		);

		return empty( $existing );
	}

	/**
	 * Get booking total.
	 *
	 * @param int $booking_id Booking ID.
	 * @return float
	 */
	private function get_booking_total( $booking_id ) {
		$total = get_post_meta( $booking_id, 'booking_total', true );
		return $total ? floatval( $total ) : 0;
	}

	/**
	 * Get booking duration in minutes.
	 *
	 * @param int $booking_id Booking ID.
	 * @return int
	 */
	private function get_booking_duration( $booking_id ) {
		$duration = get_post_meta( $booking_id, 'booking_duration', true );
		return $duration ? absint( $duration ) : 0;
	}

	/**
	 * Calculate utilization rate.
	 *
	 * @param int    $staff_id     Staff ID.
	 * @param string $date         Date.
	 * @param float  $booked_hours Booked hours.
	 * @return float
	 */
	private function calculate_utilization( $staff_id, $date, $booked_hours ) {
		// Get staff working hours for this date.
		$day_of_week     = strtolower( gmdate( 'l', strtotime( $date ) ) );
		$available_hours = get_post_meta( $staff_id, "available_hours_{$day_of_week}", true );

		if ( ! $available_hours ) {
			$available_hours = 8; // Default 8 hours.
		}

		if ( $available_hours <= 0 ) {
			return 0;
		}

		return round( ( $booked_hours / $available_hours ) * 100, 1 );
	}

	/**
	 * Get period dates.
	 *
	 * @param string $period Period.
	 * @param string $start  Custom start date.
	 * @param string $end    Custom end date.
	 * @return array
	 */
	private function get_period_dates( $period, $start = '', $end = '' ) {
		$now = current_time( 'timestamp' );

		switch ( $period ) {
			case 'day':
				return array(
					'start' => gmdate( 'Y-m-d', $now ),
					'end'   => gmdate( 'Y-m-d', $now ),
				);

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

			case 'custom':
				return array(
					'start' => $start ?: gmdate( 'Y-m-01', $now ),
					'end'   => $end ?: gmdate( 'Y-m-t', $now ),
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
	 * @param string $period     Period.
	 * @param string $curr_start Current start date.
	 * @param string $curr_end   Current end date.
	 * @return array
	 */
	private function get_previous_period_dates( $period, $curr_start, $curr_end ) {
		$start = strtotime( $curr_start );
		$end   = strtotime( $curr_end );
		$diff  = $end - $start + DAY_IN_SECONDS;

		return array(
			'start' => gmdate( 'Y-m-d', $start - $diff ),
			'end'   => gmdate( 'Y-m-d', $start - DAY_IN_SECONDS ),
		);
	}
}
