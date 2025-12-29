<?php
/**
 * Availability sync service.
 *
 * @package BookingX\BkxIntegration\Services
 */

namespace BookingX\BkxIntegration\Services;

defined( 'ABSPATH' ) || exit;

/**
 * AvailabilitySync class.
 */
class AvailabilitySync {

	/**
	 * API client.
	 *
	 * @var ApiClient
	 */
	private $api;

	/**
	 * Site service.
	 *
	 * @var SiteService
	 */
	private $sites;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api   = new ApiClient();
		$this->sites = new SiteService();
	}

	/**
	 * Queue outgoing availability sync.
	 *
	 * @param int   $seat_id Seat ID.
	 * @param array $data    Availability data.
	 */
	public function queue_outgoing( $seat_id, $data ) {
		global $wpdb;

		// Get active sites that sync availability.
		$sites = $this->sites->get_all( array( 'status' => 'active' ) );

		foreach ( $sites as $site ) {
			if ( ! $site->sync_availability || 'pull' === $site->direction ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$wpdb->prefix . 'bkx_remote_queue',
				array(
					'site_id'     => $site->id,
					'action'      => 'sync',
					'object_type' => 'availability',
					'object_id'   => $seat_id,
					'payload'     => wp_json_encode( $data ),
					'priority'    => 5, // High priority.
				),
				array( '%d', '%s', '%s', '%d', '%s', '%d' )
			);
		}
	}

	/**
	 * Get local availability.
	 *
	 * @param string $date       Date.
	 * @param int    $service_id Service ID (optional).
	 * @param int    $staff_id   Staff ID (optional).
	 * @return array
	 */
	public function get_local_availability( $date, $service_id = 0, $staff_id = 0 ) {
		$slots = array();

		// Get staff members.
		$staff_args = array(
			'post_type'      => 'bkx_seat',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		if ( $staff_id ) {
			$staff_args['p'] = $staff_id;
		}

		$staff_members = get_posts( $staff_args );

		foreach ( $staff_members as $staff ) {
			// Get booked slots for this staff on this date.
			$booked = $this->get_booked_slots( $staff->ID, $date );

			// Get working hours.
			$working_hours = $this->get_working_hours( $staff->ID, $date );

			$slots[ $staff->ID ] = array(
				'staff_id'      => $staff->ID,
				'staff_name'    => $staff->post_title,
				'working_hours' => $working_hours,
				'booked_slots'  => $booked,
			);
		}

		return array(
			'date'  => $date,
			'slots' => $slots,
		);
	}

	/**
	 * Get booked slots for a staff member.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $date     Date.
	 * @return array
	 */
	private function get_booked_slots( $staff_id, $date ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm_time.meta_value as time, pm_duration.meta_value as duration
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = 'booking_date'
				INNER JOIN {$wpdb->postmeta} pm_time ON p.ID = pm_time.post_id AND pm_time.meta_key = 'booking_time'
				INNER JOIN {$wpdb->postmeta} pm_staff ON p.ID = pm_staff.post_id AND pm_staff.meta_key = 'booking_multi_seat'
				LEFT JOIN {$wpdb->postmeta} pm_duration ON p.ID = pm_duration.post_id AND pm_duration.meta_key = 'booking_total_time'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status NOT IN ('trash', 'bkx-cancelled')
				AND pm_date.meta_value = %s
				AND pm_staff.meta_value = %s",
				$date,
				$staff_id
			)
		);

		$slots = array();
		foreach ( $bookings as $booking ) {
			$slots[] = array(
				'start'    => $booking->time,
				'duration' => absint( $booking->duration ) ?: 60,
			);
		}

		return $slots;
	}

	/**
	 * Get working hours for a staff member on a date.
	 *
	 * @param int    $staff_id Staff ID.
	 * @param string $date     Date.
	 * @return array
	 */
	private function get_working_hours( $staff_id, $date ) {
		$day_of_week = strtolower( gmdate( 'l', strtotime( $date ) ) );

		// Get staff's working days.
		$working_days = get_post_meta( $staff_id, 'seat_days', true );

		if ( empty( $working_days ) || ! is_array( $working_days ) || ! in_array( $day_of_week, $working_days, true ) ) {
			return array( 'closed' => true );
		}

		// Get staff's working hours.
		$start_time = get_post_meta( $staff_id, 'seat_start_time', true ) ?: '09:00';
		$end_time   = get_post_meta( $staff_id, 'seat_end_time', true ) ?: '17:00';

		return array(
			'closed' => false,
			'start'  => $start_time,
			'end'    => $end_time,
		);
	}

	/**
	 * Handle incoming availability sync.
	 *
	 * @param array $data Availability data.
	 * @return bool
	 */
	public function handle_incoming( $data ) {
		$source_site = $data['source_site'] ?? '';

		if ( ! $source_site ) {
			return false;
		}

		// Store remote availability for conflict checking.
		$transient_key = 'bkx_remote_availability_' . md5( $source_site . '_' . $data['date'] );
		set_transient( $transient_key, $data, HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * Check remote availability.
	 *
	 * @param string $date       Date.
	 * @param int    $service_id Service ID.
	 * @param int    $staff_id   Staff ID.
	 * @return bool
	 */
	public function check_remote( $date, $service_id, $staff_id ) {
		$sites = $this->sites->get_all( array( 'status' => 'active' ) );

		foreach ( $sites as $site ) {
			if ( ! $site->sync_availability ) {
				continue;
			}

			$result = $this->api->check_availability( $site->id, $date, $service_id, $staff_id );

			if ( is_wp_error( $result ) ) {
				continue;
			}

			// Check if any remote slots conflict.
			$remote_slots = $result['availability']['slots'] ?? array();

			// For now, just log the check. Full conflict checking would require
			// time slot comparison logic.
		}

		return true; // Return true for now, implement detailed logic as needed.
	}

	/**
	 * Sync availability for a site.
	 *
	 * @param int $site_id Site ID.
	 * @return array Results.
	 */
	public function sync_site( $site_id ) {
		$site = $this->sites->get( $site_id );

		if ( ! $site || ! $site->sync_availability ) {
			return array( 'synced' => 0, 'errors' => 0 );
		}

		$synced = 0;
		$errors = 0;

		// Sync next 30 days of availability.
		for ( $i = 0; $i < 30; $i++ ) {
			$date        = gmdate( 'Y-m-d', strtotime( "+{$i} days" ) );
			$availability = $this->get_local_availability( $date );

			$availability['source_site'] = home_url();

			$result = $this->api->send_availability( $site_id, $availability );

			if ( is_wp_error( $result ) ) {
				++$errors;
			} else {
				++$synced;
			}
		}

		return array(
			'synced' => $synced,
			'errors' => $errors,
		);
	}
}
