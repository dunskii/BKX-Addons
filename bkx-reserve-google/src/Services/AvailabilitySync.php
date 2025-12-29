<?php
/**
 * Availability Sync for Reserve with Google.
 *
 * @package BookingX\ReserveGoogle
 */

namespace BookingX\ReserveGoogle\Services;

defined( 'ABSPATH' ) || exit;

/**
 * AvailabilitySync class.
 */
class AvailabilitySync {

	/**
	 * Sync all availability.
	 *
	 * @return int Number of slots synced.
	 */
	public function sync_all() {
		$settings = get_option( 'bkx_reserve_google_settings', array() );

		if ( empty( $settings['enabled'] ) ) {
			return 0;
		}

		$merchant_manager = new MerchantManager();
		$services         = $merchant_manager->get_services( true );
		$total_slots      = 0;

		$advance_days = $settings['advance_booking_days'] ?? 30;
		$start_date   = gmdate( 'Y-m-d' );
		$end_date     = gmdate( 'Y-m-d', strtotime( "+{$advance_days} days" ) );

		foreach ( $services as $service ) {
			$slots = $this->sync_service_availability( $service, $start_date, $end_date );
			$total_slots += $slots;
		}

		return $total_slots;
	}

	/**
	 * Sync availability for a specific service.
	 *
	 * @param object $service    Service data.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return int Number of slots synced.
	 */
	public function sync_service_availability( $service, $start_date, $end_date ) {
		global $wpdb;

		$slots_count = 0;

		// Get availability from BookingX.
		$current_date = $start_date;
		while ( strtotime( $current_date ) <= strtotime( $end_date ) ) {
			$slots = $this->get_bookingx_slots( $service->bkx_service_id, $current_date );

			foreach ( $slots as $slot ) {
				$this->save_slot( $service->id, $current_date, $slot );
				$slots_count++;
			}

			$current_date = gmdate( 'Y-m-d', strtotime( $current_date . ' +1 day' ) );
		}

		return $slots_count;
	}

	/**
	 * Get available slots from BookingX.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @return array Slots.
	 */
	private function get_bookingx_slots( $service_id, $date ) {
		// Use BookingX availability API if available.
		if ( function_exists( 'bkx_get_available_slots' ) ) {
			$raw_slots = bkx_get_available_slots( $date, $service_id );
			return array_map( function( $slot ) {
				return array(
					'start_time' => $slot,
					'end_time'   => gmdate( 'H:i:s', strtotime( $slot ) + 3600 ), // Default 1 hour.
				);
			}, $raw_slots );
		}

		// Fallback: Generate basic slots.
		$slots      = array();
		$start_hour = 9;
		$end_hour   = 17;

		for ( $hour = $start_hour; $hour < $end_hour; $hour++ ) {
			$slots[] = array(
				'start_time' => sprintf( '%02d:00:00', $hour ),
				'end_time'   => sprintf( '%02d:00:00', $hour + 1 ),
			);
		}

		// Filter out booked slots.
		$booked = $this->get_booked_slots( $service_id, $date );

		return array_filter( $slots, function( $slot ) use ( $booked ) {
			return ! in_array( $slot['start_time'], $booked, true );
		} );
	}

	/**
	 * Get booked slots for a date.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @return array Booked start times.
	 */
	private function get_booked_slots( $service_id, $date ) {
		global $wpdb;

		$bookings = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm_time.meta_value
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				INNER JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'booking_time'
				INNER JOIN {$wpdb->postmeta} pm_service ON p.ID = pm_service.post_id AND pm_service.meta_key = 'service_id'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status NOT IN ('bkx-cancelled', 'bkx-missed', 'trash')
				AND pm_date.meta_value = %s
				AND pm_service.meta_value = %d",
				$date,
				$service_id
			)
		);

		return $bookings;
	}

	/**
	 * Save slot to database.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @param array  $slot       Slot data.
	 * @return bool
	 */
	private function save_slot( $service_id, $date, $slot ) {
		global $wpdb;

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bkx_rwg_slots
				WHERE service_id = %d AND slot_date = %s AND start_time = %s",
				$service_id,
				$date,
				$slot['start_time']
			)
		);

		$data = array(
			'service_id'   => $service_id,
			'slot_date'    => $date,
			'start_time'   => $slot['start_time'],
			'end_time'     => $slot['end_time'],
			'spots_total'  => $slot['spots_total'] ?? 1,
			'spots_open'   => $slot['spots_open'] ?? 1,
			'status'       => ( $slot['spots_open'] ?? 1 ) > 0 ? 'available' : 'booked',
		);

		if ( $existing ) {
			return $wpdb->update(
				$wpdb->prefix . 'bkx_rwg_slots',
				$data,
				array( 'id' => $existing )
			);
		}

		$data['created_at'] = current_time( 'mysql', true );

		return $wpdb->insert( $wpdb->prefix . 'bkx_rwg_slots', $data );
	}

	/**
	 * Get available slots for a service.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_slots( $service_id, $start_date, $end_date ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_rwg_slots
				WHERE service_id = %d
				AND slot_date BETWEEN %s AND %s
				AND status = 'available'
				ORDER BY slot_date ASC, start_time ASC",
				$service_id,
				$start_date,
				$end_date
			)
		);
	}

	/**
	 * Check if a specific slot is available.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $date       Date.
	 * @param string $start_time Start time.
	 * @return bool
	 */
	public function is_slot_available( $service_id, $date, $start_time ) {
		global $wpdb;

		$slot = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_rwg_slots
				WHERE service_id = %d AND slot_date = %s AND start_time = %s",
				$service_id,
				$date,
				$start_time
			)
		);

		if ( ! $slot ) {
			// Slot doesn't exist in cache, check BookingX directly.
			$booked = $this->get_booked_slots( $service_id, $date );
			return ! in_array( $start_time, $booked, true );
		}

		return $slot->status === 'available' && $slot->spots_open > 0;
	}

	/**
	 * Book a slot.
	 *
	 * @param int $slot_id Slot ID.
	 * @return bool
	 */
	public function book_slot( $slot_id ) {
		global $wpdb;

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}bkx_rwg_slots
				SET spots_open = spots_open - 1,
				    status = CASE WHEN spots_open <= 1 THEN 'booked' ELSE status END
				WHERE id = %d AND spots_open > 0",
				$slot_id
			)
		);
	}

	/**
	 * Release a slot.
	 *
	 * @param int $slot_id Slot ID.
	 * @return bool
	 */
	public function release_slot( $slot_id ) {
		global $wpdb;

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}bkx_rwg_slots
				SET spots_open = LEAST(spots_open + 1, spots_total),
				    status = 'available'
				WHERE id = %d",
				$slot_id
			)
		);
	}

	/**
	 * Cleanup old slots.
	 *
	 * @return int Number of deleted slots.
	 */
	public function cleanup_old_slots() {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d', strtotime( '-7 days' ) );

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}bkx_rwg_slots WHERE slot_date < %s",
				$cutoff
			)
		);
	}
}
