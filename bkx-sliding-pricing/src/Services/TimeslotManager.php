<?php
/**
 * Timeslot Manager Service.
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
 * TimeslotManager Class.
 */
class TimeslotManager {

	/**
	 * Days of week.
	 *
	 * @var array
	 */
	private $days = array(
		'all'       => 'Every Day',
		'monday'    => 'Monday',
		'tuesday'   => 'Tuesday',
		'wednesday' => 'Wednesday',
		'thursday'  => 'Thursday',
		'friday'    => 'Friday',
		'saturday'  => 'Saturday',
		'sunday'    => 'Sunday',
		'weekday'   => 'Weekdays (Mon-Fri)',
		'weekend'   => 'Weekends (Sat-Sun)',
	);

	/**
	 * Save a timeslot.
	 *
	 * @param array $data Timeslot data.
	 * @return int|\WP_Error Timeslot ID or error.
	 */
	public function save_timeslot( $data ) {
		global $wpdb;

		// Validate.
		if ( empty( $data['name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'Timeslot name is required', 'bkx-sliding-pricing' ) );
		}

		if ( empty( $data['start_time'] ) || empty( $data['end_time'] ) ) {
			return new \WP_Error( 'missing_times', __( 'Start and end times are required', 'bkx-sliding-pricing' ) );
		}

		$table = $wpdb->prefix . 'bkx_pricing_timeslots';

		$timeslot_data = array(
			'name'             => sanitize_text_field( $data['name'] ),
			'day_of_week'      => sanitize_text_field( $data['day_of_week'] ?? 'all' ),
			'start_time'       => sanitize_text_field( $data['start_time'] ),
			'end_time'         => sanitize_text_field( $data['end_time'] ),
			'adjustment_type'  => sanitize_text_field( $data['adjustment_type'] ?? 'percentage' ),
			'adjustment_value' => floatval( $data['adjustment_value'] ?? 0 ),
			'applies_to'       => sanitize_text_field( $data['applies_to'] ?? 'all' ),
			'service_ids'      => maybe_serialize( $data['service_ids'] ?? array() ),
			'is_active'        => isset( $data['is_active'] ) ? 1 : 0,
		);

		$formats = array( '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d' );

		if ( ! empty( $data['id'] ) ) {
			// Update existing timeslot.
			$result = $wpdb->update(
				$table,
				$timeslot_data,
				array( 'id' => absint( $data['id'] ) ),
				$formats,
				array( '%d' )
			);

			if ( false === $result ) {
				return new \WP_Error( 'db_error', __( 'Database error occurred', 'bkx-sliding-pricing' ) );
			}

			return absint( $data['id'] );
		} else {
			// Insert new timeslot.
			$result = $wpdb->insert( $table, $timeslot_data, $formats );

			if ( ! $result ) {
				return new \WP_Error( 'db_error', __( 'Database error occurred', 'bkx-sliding-pricing' ) );
			}

			return $wpdb->insert_id;
		}
	}

	/**
	 * Delete a timeslot.
	 *
	 * @param int $timeslot_id Timeslot ID.
	 * @return bool
	 */
	public function delete_timeslot( $timeslot_id ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'bkx_pricing_timeslots';
		$result = $wpdb->delete( $table, array( 'id' => absint( $timeslot_id ) ), array( '%d' ) );

		return $result !== false;
	}

	/**
	 * Get a timeslot by ID.
	 *
	 * @param int $timeslot_id Timeslot ID.
	 * @return array|null
	 */
	public function get_timeslot( $timeslot_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_pricing_timeslots';

		$timeslot = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $timeslot_id ),
			ARRAY_A
		);

		if ( $timeslot ) {
			$timeslot['service_ids'] = maybe_unserialize( $timeslot['service_ids'] );
			$timeslot['day_label']   = $this->days[ $timeslot['day_of_week'] ] ?? $timeslot['day_of_week'];
		}

		return $timeslot;
	}

	/**
	 * Get all timeslots.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_timeslots( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'active_only' => false,
			'day_of_week' => '',
			'orderby'     => 'start_time',
			'order'       => 'ASC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'bkx_pricing_timeslots';

		$where = 'WHERE 1=1';

		if ( $args['active_only'] ) {
			$where .= ' AND is_active = 1';
		}

		if ( ! empty( $args['day_of_week'] ) ) {
			$where .= $wpdb->prepare( ' AND (day_of_week = %s OR day_of_week = "all")', $args['day_of_week'] );
		}

		$orderby = in_array( $args['orderby'], array( 'start_time', 'end_time', 'name', 'day_of_week' ), true ) ? $args['orderby'] : 'start_time';
		$order   = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$timeslots = $wpdb->get_results(
			"SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order}",
			ARRAY_A
		);

		foreach ( $timeslots as &$timeslot ) {
			$timeslot['service_ids'] = maybe_unserialize( $timeslot['service_ids'] );
			$timeslot['day_label']   = $this->days[ $timeslot['day_of_week'] ] ?? $timeslot['day_of_week'];
			$timeslot['time_range']  = $this->format_time_range( $timeslot['start_time'], $timeslot['end_time'] );
		}

		return $timeslots;
	}

	/**
	 * Get timeslots for a specific day.
	 *
	 * @param string $day Day of week.
	 * @return array
	 */
	public function get_day_timeslots( $day ) {
		return $this->get_timeslots(
			array(
				'active_only' => true,
				'day_of_week' => strtolower( $day ),
			)
		);
	}

	/**
	 * Check if time is within a peak period.
	 *
	 * @param string $day  Day of week.
	 * @param string $time Time (H:i).
	 * @return bool
	 */
	public function is_peak_time( $day, $time ) {
		$timeslots = $this->get_day_timeslots( $day );

		foreach ( $timeslots as $slot ) {
			if ( $time >= $slot['start_time'] && $time <= $slot['end_time'] ) {
				if ( $slot['adjustment_value'] > 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if time is within an off-peak period.
	 *
	 * @param string $day  Day of week.
	 * @param string $time Time (H:i).
	 * @return bool
	 */
	public function is_off_peak_time( $day, $time ) {
		$timeslots = $this->get_day_timeslots( $day );

		foreach ( $timeslots as $slot ) {
			if ( $time >= $slot['start_time'] && $time <= $slot['end_time'] ) {
				if ( $slot['adjustment_value'] < 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get days of week options.
	 *
	 * @return array
	 */
	public function get_days() {
		return $this->days;
	}

	/**
	 * Format time range for display.
	 *
	 * @param string $start Start time.
	 * @param string $end   End time.
	 * @return string
	 */
	private function format_time_range( $start, $end ) {
		$start_formatted = gmdate( 'g:i A', strtotime( $start ) );
		$end_formatted   = gmdate( 'g:i A', strtotime( $end ) );

		return $start_formatted . ' - ' . $end_formatted;
	}

	/**
	 * Create default timeslots.
	 */
	public function create_default_timeslots() {
		// Peak morning hours.
		$this->save_timeslot(
			array(
				'name'             => __( 'Peak Morning', 'bkx-sliding-pricing' ),
				'day_of_week'      => 'weekday',
				'start_time'       => '09:00',
				'end_time'         => '11:00',
				'adjustment_type'  => 'percentage',
				'adjustment_value' => 10,
				'applies_to'       => 'all',
				'is_active'        => 0,
			)
		);

		// Off-peak afternoon.
		$this->save_timeslot(
			array(
				'name'             => __( 'Off-Peak Afternoon', 'bkx-sliding-pricing' ),
				'day_of_week'      => 'weekday',
				'start_time'       => '14:00',
				'end_time'         => '16:00',
				'adjustment_type'  => 'percentage',
				'adjustment_value' => -15,
				'applies_to'       => 'all',
				'is_active'        => 0,
			)
		);

		// Weekend premium.
		$this->save_timeslot(
			array(
				'name'             => __( 'Weekend Premium', 'bkx-sliding-pricing' ),
				'day_of_week'      => 'weekend',
				'start_time'       => '10:00',
				'end_time'         => '16:00',
				'adjustment_type'  => 'percentage',
				'adjustment_value' => 15,
				'applies_to'       => 'all',
				'is_active'        => 0,
			)
		);

		// Early bird slot.
		$this->save_timeslot(
			array(
				'name'             => __( 'Early Bird Slot', 'bkx-sliding-pricing' ),
				'day_of_week'      => 'all',
				'start_time'       => '07:00',
				'end_time'         => '08:30',
				'adjustment_type'  => 'percentage',
				'adjustment_value' => -10,
				'applies_to'       => 'all',
				'is_active'        => 0,
			)
		);

		// Evening premium.
		$this->save_timeslot(
			array(
				'name'             => __( 'Evening Premium', 'bkx-sliding-pricing' ),
				'day_of_week'      => 'all',
				'start_time'       => '18:00',
				'end_time'         => '21:00',
				'adjustment_type'  => 'percentage',
				'adjustment_value' => 20,
				'applies_to'       => 'all',
				'is_active'        => 0,
			)
		);
	}

	/**
	 * Get price heatmap data.
	 *
	 * @param int $service_id Service ID.
	 * @return array
	 */
	public function get_price_heatmap( $service_id = 0 ) {
		$days  = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		$hours = range( 7, 21 ); // 7 AM to 9 PM.

		$heatmap = array();

		foreach ( $days as $day ) {
			$heatmap[ $day ] = array();
			foreach ( $hours as $hour ) {
				$time = sprintf( '%02d:00', $hour );
				$heatmap[ $day ][ $hour ] = $this->get_time_adjustment( $day, $time, $service_id );
			}
		}

		return $heatmap;
	}

	/**
	 * Get adjustment for specific time.
	 *
	 * @param string $day        Day.
	 * @param string $time       Time.
	 * @param int    $service_id Service ID.
	 * @return float
	 */
	private function get_time_adjustment( $day, $time, $service_id = 0 ) {
		$timeslots = $this->get_day_timeslots( $day );

		foreach ( $timeslots as $slot ) {
			if ( $time >= $slot['start_time'] && $time <= $slot['end_time'] ) {
				// Check service filter.
				if ( $service_id > 0 && 'specific' === $slot['applies_to'] ) {
					if ( ! in_array( $service_id, (array) $slot['service_ids'], true ) ) {
						continue;
					}
				}

				return floatval( $slot['adjustment_value'] );
			}
		}

		return 0;
	}
}
