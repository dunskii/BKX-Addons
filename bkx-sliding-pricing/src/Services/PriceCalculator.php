<?php
/**
 * Price Calculator Service.
 *
 * @package BookingX\SlidingPricing\Services
 * @since   1.0.0
 */

namespace BookingX\SlidingPricing\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PriceCalculator Class.
 */
class PriceCalculator {

	/**
	 * Calculate dynamic price.
	 *
	 * @param float  $base_price  Base price.
	 * @param int    $service_id  Service ID.
	 * @param int    $staff_id    Staff ID.
	 * @param string $date        Date (Y-m-d).
	 * @param string $time        Time (H:i).
	 * @return float Final price.
	 */
	public function calculate_price( $base_price, $service_id, $staff_id = 0, $date = '', $time = '' ) {
		$result = $this->calculate_price_with_breakdown( $base_price, $service_id, $staff_id, $date, $time );
		return $result['final_price'];
	}

	/**
	 * Calculate price with full breakdown.
	 *
	 * @param float  $base_price  Base price.
	 * @param int    $service_id  Service ID.
	 * @param int    $staff_id    Staff ID.
	 * @param string $date        Date (Y-m-d).
	 * @param string $time        Time (H:i).
	 * @return array Price breakdown.
	 */
	public function calculate_price_with_breakdown( $base_price, $service_id, $staff_id = 0, $date = '', $time = '' ) {
		if ( empty( $date ) ) {
			$date = gmdate( 'Y-m-d' );
		}
		if ( empty( $time ) ) {
			$time = gmdate( 'H:i' );
		}

		$adjustments    = array();
		$current_price  = $base_price;
		$stack_rules    = get_option( 'bkx_sliding_pricing_stack_rules', 'yes' );
		$max_discount   = absint( get_option( 'bkx_sliding_pricing_max_discount', 50 ) );

		// Get all applicable adjustments.
		$season_adj   = $this->get_season_adjustment( $service_id, $date );
		$timeslot_adj = $this->get_timeslot_adjustment( $service_id, $date, $time );
		$rule_adjs    = $this->get_rule_adjustments( $service_id, $staff_id, $date, $time );

		// Apply season adjustment.
		if ( $season_adj ) {
			$adjustment = $this->apply_adjustment( $current_price, $season_adj );
			if ( 'yes' === $stack_rules || empty( $adjustments ) ) {
				$current_price          = $adjustment['new_price'];
				$adjustments['season'] = array(
					'name'   => $season_adj['name'],
					'type'   => $season_adj['adjustment_type'],
					'value'  => $season_adj['adjustment_value'],
					'amount' => $adjustment['amount'],
				);
			}
		}

		// Apply timeslot adjustment.
		if ( $timeslot_adj ) {
			$adjustment = $this->apply_adjustment( $current_price, $timeslot_adj );
			if ( 'yes' === $stack_rules || empty( $adjustments ) ) {
				$current_price            = $adjustment['new_price'];
				$adjustments['timeslot'] = array(
					'name'   => $timeslot_adj['name'],
					'type'   => $timeslot_adj['adjustment_type'],
					'value'  => $timeslot_adj['adjustment_value'],
					'amount' => $adjustment['amount'],
				);
			}
		}

		// Apply rule adjustments.
		foreach ( $rule_adjs as $rule ) {
			$adjustment = $this->apply_adjustment( $current_price, $rule );
			if ( 'yes' === $stack_rules || empty( $adjustments ) ) {
				$current_price                          = $adjustment['new_price'];
				$adjustments[ 'rule_' . $rule['id'] ] = array(
					'name'   => $rule['name'],
					'type'   => $rule['adjustment_type'],
					'value'  => $rule['adjustment_value'],
					'amount' => $adjustment['amount'],
				);
			}
		}

		// Apply max discount cap.
		$min_price = $base_price * ( 1 - $max_discount / 100 );
		if ( $current_price < $min_price ) {
			$current_price = $min_price;
		}

		// Don't allow negative prices.
		if ( $current_price < 0 ) {
			$current_price = 0;
		}

		return array(
			'base_price'   => $base_price,
			'final_price'  => round( $current_price, 2 ),
			'total_saving' => round( $base_price - $current_price, 2 ),
			'discount_pct' => $base_price > 0 ? round( ( ( $base_price - $current_price ) / $base_price ) * 100, 1 ) : 0,
			'adjustments'  => $adjustments,
			'date'         => $date,
			'time'         => $time,
		);
	}

	/**
	 * Get season adjustment for date.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @return array|null
	 */
	private function get_season_adjustment( $service_id, $date ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_pricing_seasons';

		// Check for matching season.
		$season = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE is_active = 1
				AND (
					(recurs_yearly = 0 AND %s BETWEEN start_date AND end_date)
					OR (recurs_yearly = 1 AND (
						DATE_FORMAT(%s, '%%m-%%d') BETWEEN DATE_FORMAT(start_date, '%%m-%%d') AND DATE_FORMAT(end_date, '%%m-%%d')
						OR (DATE_FORMAT(start_date, '%%m-%%d') > DATE_FORMAT(end_date, '%%m-%%d')
							AND (DATE_FORMAT(%s, '%%m-%%d') >= DATE_FORMAT(start_date, '%%m-%%d')
								OR DATE_FORMAT(%s, '%%m-%%d') <= DATE_FORMAT(end_date, '%%m-%%d')))
					))
				)
				ORDER BY adjustment_value DESC
				LIMIT 1",
				$date,
				$date,
				$date,
				$date
			),
			ARRAY_A
		);

		if ( ! $season ) {
			return null;
		}

		// Check if applies to this service.
		if ( 'specific' === $season['applies_to'] && ! empty( $season['service_ids'] ) ) {
			$service_ids = maybe_unserialize( $season['service_ids'] );
			if ( ! in_array( $service_id, (array) $service_ids, true ) ) {
				return null;
			}
		}

		return $season;
	}

	/**
	 * Get timeslot adjustment.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @param string $time       Time.
	 * @return array|null
	 */
	private function get_timeslot_adjustment( $service_id, $date, $time ) {
		global $wpdb;

		$table       = $wpdb->prefix . 'bkx_pricing_timeslots';
		$day_of_week = strtolower( gmdate( 'l', strtotime( $date ) ) );

		$timeslot = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE is_active = 1
				AND (day_of_week = %s OR day_of_week = 'all')
				AND %s BETWEEN start_time AND end_time
				ORDER BY ABS(adjustment_value) DESC
				LIMIT 1",
				$day_of_week,
				$time
			),
			ARRAY_A
		);

		if ( ! $timeslot ) {
			return null;
		}

		// Check if applies to this service.
		if ( 'specific' === $timeslot['applies_to'] && ! empty( $timeslot['service_ids'] ) ) {
			$service_ids = maybe_unserialize( $timeslot['service_ids'] );
			if ( ! in_array( $service_id, (array) $service_ids, true ) ) {
				return null;
			}
		}

		return $timeslot;
	}

	/**
	 * Get applicable rule adjustments.
	 *
	 * @param int    $service_id Service ID.
	 * @param int    $staff_id   Staff ID.
	 * @param string $date       Date.
	 * @param string $time       Time.
	 * @return array
	 */
	private function get_rule_adjustments( $service_id, $staff_id, $date, $time ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_pricing_rules';

		$rules = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE is_active = 1
				AND (start_date IS NULL OR start_date <= %s)
				AND (end_date IS NULL OR end_date >= %s)
				ORDER BY priority ASC",
				$date,
				$date
			),
			ARRAY_A
		);

		$applicable = array();

		foreach ( $rules as $rule ) {
			// Check service applicability.
			if ( 'specific' === $rule['applies_to'] && ! empty( $rule['service_ids'] ) ) {
				$service_ids = maybe_unserialize( $rule['service_ids'] );
				if ( ! in_array( $service_id, (array) $service_ids, true ) ) {
					continue;
				}
			}

			// Check staff applicability.
			if ( $staff_id > 0 && ! empty( $rule['staff_ids'] ) ) {
				$staff_ids = maybe_unserialize( $rule['staff_ids'] );
				if ( ! in_array( $staff_id, (array) $staff_ids, true ) ) {
					continue;
				}
			}

			// Check conditions.
			if ( ! $this->check_conditions( $rule, $service_id, $date, $time ) ) {
				continue;
			}

			$applicable[] = $rule;
		}

		return $applicable;
	}

	/**
	 * Check rule conditions.
	 *
	 * @param array  $rule       Rule data.
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @param string $time       Time.
	 * @return bool
	 */
	private function check_conditions( $rule, $service_id, $date, $time ) {
		$conditions = maybe_unserialize( $rule['conditions'] );

		if ( empty( $conditions ) ) {
			return true;
		}

		foreach ( $conditions as $condition ) {
			if ( ! $this->evaluate_condition( $condition, $service_id, $date, $time ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Evaluate a single condition.
	 *
	 * @param array  $condition  Condition data.
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @param string $time       Time.
	 * @return bool
	 */
	private function evaluate_condition( $condition, $service_id, $date, $time ) {
		$type     = $condition['type'] ?? '';
		$operator = $condition['operator'] ?? 'equals';
		$value    = $condition['value'] ?? '';

		switch ( $type ) {
			case 'days_before':
				$days_before = ( strtotime( $date ) - time() ) / DAY_IN_SECONDS;
				return $this->compare_values( $days_before, $operator, floatval( $value ) );

			case 'day_of_week':
				$dow = strtolower( gmdate( 'l', strtotime( $date ) ) );
				return $this->compare_values( $dow, $operator, strtolower( $value ) );

			case 'time_of_day':
				return $this->compare_values( $time, $operator, $value );

			case 'booking_count':
				// Check existing bookings for date/service.
				$count = $this->get_booking_count( $service_id, $date );
				return $this->compare_values( $count, $operator, absint( $value ) );

			case 'availability':
				// Check remaining availability percentage.
				$availability = $this->get_availability_percentage( $service_id, $date );
				return $this->compare_values( $availability, $operator, floatval( $value ) );

			default:
				return true;
		}
	}

	/**
	 * Compare values with operator.
	 *
	 * @param mixed  $actual   Actual value.
	 * @param string $operator Comparison operator.
	 * @param mixed  $expected Expected value.
	 * @return bool
	 */
	private function compare_values( $actual, $operator, $expected ) {
		switch ( $operator ) {
			case 'equals':
			case '=':
				return $actual == $expected;

			case 'not_equals':
			case '!=':
				return $actual != $expected;

			case 'greater':
			case '>':
				return $actual > $expected;

			case 'greater_equals':
			case '>=':
				return $actual >= $expected;

			case 'less':
			case '<':
				return $actual < $expected;

			case 'less_equals':
			case '<=':
				return $actual <= $expected;

			case 'contains':
				return strpos( $actual, $expected ) !== false;

			default:
				return false;
		}
	}

	/**
	 * Apply adjustment to price.
	 *
	 * @param float $price      Current price.
	 * @param array $adjustment Adjustment data.
	 * @return array New price and adjustment amount.
	 */
	private function apply_adjustment( $price, $adjustment ) {
		$type   = $adjustment['adjustment_type'];
		$value  = floatval( $adjustment['adjustment_value'] );
		$amount = 0;

		switch ( $type ) {
			case 'percentage':
				// Negative value = discount, positive = surcharge.
				$amount    = $price * ( $value / 100 );
				$new_price = $price + $amount;
				break;

			case 'fixed':
				$amount    = $value;
				$new_price = $price + $value;
				break;

			case 'set':
				$amount    = $value - $price;
				$new_price = $value;
				break;

			default:
				$new_price = $price;
		}

		return array(
			'new_price' => $new_price,
			'amount'    => $amount,
		);
	}

	/**
	 * Get booking count for service/date.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @return int
	 */
	private function get_booking_count( $service_id, $date ) {
		$bookings = get_posts(
			array(
				'post_type'      => 'bkx_booking',
				'posts_per_page' => -1,
				'post_status'    => array( 'bkx-pending', 'bkx-ack', 'bkx-completed' ),
				'meta_query'     => array(
					array(
						'key'   => 'base_id',
						'value' => $service_id,
					),
					array(
						'key'   => 'booking_date',
						'value' => $date,
					),
				),
				'fields'         => 'ids',
			)
		);

		return count( $bookings );
	}

	/**
	 * Get availability percentage for service/date.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @return float
	 */
	private function get_availability_percentage( $service_id, $date ) {
		// This would need integration with BookingX availability system.
		// For now, return a placeholder calculation.
		$total_slots  = 10; // Placeholder.
		$booked_slots = $this->get_booking_count( $service_id, $date );
		$remaining    = max( 0, $total_slots - $booked_slots );

		return ( $remaining / $total_slots ) * 100;
	}

	/**
	 * Get pricing info for display.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @param string $time       Time.
	 * @return array
	 */
	public function get_pricing_info( $service_id, $date, $time ) {
		$badges = array();

		// Check for peak hours.
		$timeslot = $this->get_timeslot_adjustment( $service_id, $date, $time );
		if ( $timeslot ) {
			if ( $timeslot['adjustment_value'] > 0 ) {
				$badges[] = array(
					'type'  => 'peak',
					'label' => __( 'Peak Hours', 'bkx-sliding-pricing' ),
				);
			} else {
				$badges[] = array(
					'type'  => 'off-peak',
					'label' => __( 'Off-Peak', 'bkx-sliding-pricing' ),
				);
			}
		}

		// Check for season.
		$season = $this->get_season_adjustment( $service_id, $date );
		if ( $season ) {
			$badges[] = array(
				'type'  => 'season',
				'label' => $season['name'],
			);
		}

		// Check for early bird.
		$days_ahead = ( strtotime( $date ) - time() ) / DAY_IN_SECONDS;
		if ( $days_ahead >= 14 ) {
			$badges[] = array(
				'type'  => 'early-bird',
				'label' => __( 'Early Bird', 'bkx-sliding-pricing' ),
			);
		} elseif ( $days_ahead <= 1 && $days_ahead >= 0 ) {
			$badges[] = array(
				'type'  => 'last-minute',
				'label' => __( 'Last Minute', 'bkx-sliding-pricing' ),
			);
		}

		return array(
			'badges' => $badges,
		);
	}

	/**
	 * Save pricing history for booking.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function save_history( $booking_id, $booking_data ) {
		global $wpdb;

		$service_id = isset( $booking_data['base_id'] ) ? absint( $booking_data['base_id'] ) : 0;
		$date       = isset( $booking_data['booking_date'] ) ? $booking_data['booking_date'] : '';
		$time       = isset( $booking_data['booking_time'] ) ? $booking_data['booking_time'] : '';

		$base_price = get_post_meta( $service_id, 'base_price', true );
		$base_price = $base_price ? floatval( $base_price ) : 0;

		$breakdown = $this->calculate_price_with_breakdown( $base_price, $service_id, 0, $date, $time );

		$table = $wpdb->prefix . 'bkx_pricing_history';

		$wpdb->insert(
			$table,
			array(
				'booking_id'  => $booking_id,
				'base_price'  => $breakdown['base_price'],
				'final_price' => $breakdown['final_price'],
				'adjustments' => maybe_serialize( $breakdown['adjustments'] ),
			),
			array( '%d', '%f', '%f', '%s' )
		);
	}
}
