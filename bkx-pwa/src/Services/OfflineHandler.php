<?php
/**
 * Offline Handler Service.
 *
 * @package BookingX\PWA\Services
 */

namespace BookingX\PWA\Services;

defined( 'ABSPATH' ) || exit;

/**
 * OfflineHandler class.
 */
class OfflineHandler {

	/**
	 * Render offline page.
	 */
	public function render_offline_page() {
		$addon = \BookingX\PWA\PWAAddon::get_instance();

		// Check for custom offline page.
		$offline_page_id = $addon->get_setting( 'offline_page_id' );
		if ( $offline_page_id && get_post_status( $offline_page_id ) === 'publish' ) {
			// Use custom page.
			global $post;
			$post = get_post( $offline_page_id );
			setup_postdata( $post );
			get_header();
			the_content();
			get_footer();
			wp_reset_postdata();
			return;
		}

		// Default offline page.
		include BKX_PWA_PLUGIN_DIR . 'templates/offline.php';
	}

	/**
	 * Save offline booking.
	 *
	 * @param array $booking_data Booking data.
	 * @return int|WP_Error
	 */
	public function save_offline_booking( $booking_data ) {
		// Validate required fields.
		$required = array( 'service_id', 'booking_date', 'booking_time' );
		foreach ( $required as $field ) {
			if ( empty( $booking_data[ $field ] ) ) {
				return new \WP_Error(
					'missing_field',
					/* translators: %s: field name */
					sprintf( __( 'Missing required field: %s', 'bkx-pwa' ), $field )
				);
			}
		}

		// Mark as offline booking.
		$booking_data['booking_source']     = 'pwa_offline';
		$booking_data['offline_created_at'] = $booking_data['createdAt'] ?? current_time( 'mysql' );

		// Create booking via BookingX.
		if ( class_exists( 'BkxBooking' ) ) {
			$booking = new \BkxBooking();
			$result  = $booking->create_booking( $booking_data );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Mark as synced from offline.
			update_post_meta( $result, '_bkx_offline_synced', true );
			update_post_meta( $result, '_bkx_offline_id', $booking_data['offlineId'] ?? '' );

			return $result;
		}

		return new \WP_Error( 'bookingx_unavailable', __( 'BookingX is not available.', 'bkx-pwa' ) );
	}

	/**
	 * Sync offline bookings from REST API.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function sync_booking( $request ) {
		$booking_data = $request->get_json_params();

		if ( empty( $booking_data ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => __( 'No booking data provided.', 'bkx-pwa' ),
				)
			);
		}

		$result = $this->save_offline_booking( $booking_data );

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response(
				array(
					'success'    => false,
					'message'    => $result->get_error_message(),
					'offline_id' => $booking_data['offlineId'] ?? null,
				)
			);
		}

		return rest_ensure_response(
			array(
				'success'    => true,
				'booking_id' => $result,
				'offline_id' => $booking_data['offlineId'] ?? null,
			)
		);
	}

	/**
	 * Sync multiple offline bookings.
	 *
	 * @param array $bookings Array of booking data.
	 * @return array Results.
	 */
	public function sync_offline_bookings( $bookings ) {
		$results = array(
			'synced' => array(),
			'failed' => array(),
		);

		foreach ( $bookings as $booking_data ) {
			$result = $this->save_offline_booking( $booking_data );

			if ( is_wp_error( $result ) ) {
				$results['failed'][] = array(
					'offline_id' => $booking_data['offlineId'] ?? null,
					'error'      => $result->get_error_message(),
				);
			} else {
				$results['synced'][] = array(
					'offline_id' => $booking_data['offlineId'] ?? null,
					'booking_id' => $result,
				);
			}
		}

		return $results;
	}

	/**
	 * Get offline bookings count for user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_offline_bookings_count( $user_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta}
				WHERE meta_key = '_bkx_offline_synced'
				AND meta_value = '1'
				AND post_id IN (
					SELECT ID FROM {$wpdb->posts}
					WHERE post_type = 'bkx_booking'
					AND post_author = %d
				)",
				$user_id
			)
		);
	}

	/**
	 * Get pending offline bookings.
	 *
	 * @return array
	 */
	public function get_pending_offline_bookings() {
		$args = array(
			'post_type'   => 'bkx_booking',
			'post_status' => 'bkx-pending',
			'meta_query'  => array(
				array(
					'key'   => '_bkx_offline_synced',
					'value' => '1',
				),
			),
			'posts_per_page' => -1,
		);

		return get_posts( $args );
	}
}
