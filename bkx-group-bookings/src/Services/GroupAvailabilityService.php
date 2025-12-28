<?php
/**
 * Group Availability Service
 *
 * @package BookingX\GroupBookings\Services
 * @since   1.0.0
 */

namespace BookingX\GroupBookings\Services;

/**
 * Service for checking group availability.
 *
 * @since 1.0.0
 */
class GroupAvailabilityService {

	/**
	 * Filter slots by capacity.
	 *
	 * @since 1.0.0
	 * @param array $slots        Available slots.
	 * @param int   $seat_id      Seat post ID.
	 * @param int   $requested_qty Requested quantity.
	 * @return array
	 */
	public function filter_by_capacity( array $slots, int $seat_id, int $requested_qty ): array {
		$max_capacity = $this->get_seat_capacity( $seat_id );

		if ( $max_capacity && $requested_qty > $max_capacity ) {
			return array(); // No slots available for this group size.
		}

		$filtered = array();

		foreach ( $slots as $date => $times ) {
			foreach ( $times as $time => $slot_data ) {
				$remaining = $this->get_remaining_capacity( $seat_id, $date, $time );

				if ( $remaining >= $requested_qty ) {
					if ( ! isset( $filtered[ $date ] ) ) {
						$filtered[ $date ] = array();
					}
					$filtered[ $date ][ $time ] = $slot_data;
				}
			}
		}

		return $filtered;
	}

	/**
	 * Check availability for a specific slot.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id  Seat post ID.
	 * @param string $date     Date (Y-m-d).
	 * @param string $time     Time (H:i).
	 * @param int    $quantity Requested quantity.
	 * @return bool
	 */
	public function check_availability( int $seat_id, string $date, string $time, int $quantity ): bool {
		$max_capacity = $this->get_seat_capacity( $seat_id );

		// If no capacity limit set, use default slot logic.
		if ( ! $max_capacity ) {
			$max_capacity = 1; // Single booking per slot.
		}

		$remaining = $this->get_remaining_capacity( $seat_id, $date, $time );

		return $remaining >= $quantity;
	}

	/**
	 * Get maximum available spots for a slot.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id Seat post ID.
	 * @param string $date    Date (Y-m-d).
	 * @param string $time    Time (H:i).
	 * @return int
	 */
	public function get_max_available( int $seat_id, string $date, string $time ): int {
		return $this->get_remaining_capacity( $seat_id, $date, $time );
	}

	/**
	 * Get seat capacity.
	 *
	 * @since 1.0.0
	 * @param int $seat_id Seat post ID.
	 * @return int
	 */
	private function get_seat_capacity( int $seat_id ): int {
		$capacity = get_post_meta( $seat_id, '_bkx_group_capacity', true );

		if ( $capacity ) {
			return absint( $capacity );
		}

		// Fall back to default capacity setting.
		$settings = get_option( 'bkx_group_bookings_settings', array() );

		return isset( $settings['default_max_quantity'] ) ? absint( $settings['default_max_quantity'] ) : 10;
	}

	/**
	 * Get remaining capacity for a slot.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id Seat post ID.
	 * @param string $date    Date (Y-m-d).
	 * @param string $time    Time (H:i).
	 * @return int
	 */
	private function get_remaining_capacity( int $seat_id, string $date, string $time ): int {
		$max_capacity = $this->get_seat_capacity( $seat_id );
		$booked       = $this->get_booked_quantity( $seat_id, $date, $time );

		return max( 0, $max_capacity - $booked );
	}

	/**
	 * Get total booked quantity for a slot.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id Seat post ID.
	 * @param string $date    Date (Y-m-d).
	 * @param string $time    Time (H:i).
	 * @return int
	 */
	private function get_booked_quantity( int $seat_id, string $date, string $time ): int {
		global $wpdb;

		// Query existing bookings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(CAST(pm_qty.meta_value AS UNSIGNED)), COUNT(p.ID)) as total
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_seat ON p.ID = pm_seat.post_id AND pm_seat.meta_key = 'seat_id'
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				INNER JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'booking_time'
				LEFT JOIN {$wpdb->postmeta} pm_qty ON p.ID = pm_qty.post_id AND pm_qty.meta_key = '_bkx_group_quantity'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('publish', 'bkx-pending', 'bkx-ack')
				AND pm_seat.meta_value = %s
				AND pm_date.meta_value = %s
				AND pm_time.meta_value = %s",
				$seat_id,
				$date,
				$time
			)
		);

		return $result ? absint( $result ) : 0;
	}

	/**
	 * Get all bookings for a slot with their quantities.
	 *
	 * @since 1.0.0
	 * @param int    $seat_id Seat post ID.
	 * @param string $date    Date (Y-m-d).
	 * @param string $time    Time (H:i).
	 * @return array
	 */
	public function get_slot_bookings( int $seat_id, string $date, string $time ): array {
		$args = array(
			'post_type'      => 'bkx_booking',
			'post_status'    => array( 'publish', 'bkx-pending', 'bkx-ack' ),
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'seat_id',
					'value'   => $seat_id,
					'compare' => '=',
				),
				array(
					'key'     => 'booking_date',
					'value'   => $date,
					'compare' => '=',
				),
				array(
					'key'     => 'booking_time',
					'value'   => $time,
					'compare' => '=',
				),
			),
		);

		$query    = new \WP_Query( $args );
		$bookings = array();

		foreach ( $query->posts as $post ) {
			$quantity = get_post_meta( $post->ID, '_bkx_group_quantity', true );

			$bookings[] = array(
				'booking_id' => $post->ID,
				'quantity'   => $quantity ? absint( $quantity ) : 1,
				'status'     => $post->post_status,
			);
		}

		return $bookings;
	}
}
