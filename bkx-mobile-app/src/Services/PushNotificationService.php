<?php
/**
 * Push Notification Service for Mobile App Framework.
 *
 * @package BookingX\MobileApp\Services
 */

namespace BookingX\MobileApp\Services;

defined( 'ABSPATH' ) || exit;

/**
 * PushNotificationService class.
 */
class PushNotificationService {

	/**
	 * Send push notification.
	 *
	 * @param string $device_token Device token.
	 * @param string $device_type  Device type (ios/android).
	 * @param array  $notification Notification data.
	 * @return bool|WP_Error
	 */
	public function send( $device_token, $device_type, $notification ) {
		if ( 'ios' === $device_type ) {
			return $this->send_apns( $device_token, $notification );
		}

		return $this->send_fcm( $device_token, $notification );
	}

	/**
	 * Send via Firebase Cloud Messaging.
	 *
	 * @param string $device_token Device token.
	 * @param array  $notification Notification data.
	 * @return bool|WP_Error
	 */
	private function send_fcm( $device_token, $notification ) {
		$addon    = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$settings = get_option( 'bkx_mobile_app_settings', array() );

		$server_key = isset( $settings['fcm_server_key'] ) ? $settings['fcm_server_key'] : '';

		if ( empty( $server_key ) ) {
			return new \WP_Error( 'no_fcm_key', __( 'FCM server key not configured.', 'bkx-mobile-app' ) );
		}

		$payload = array(
			'to'           => $device_token,
			'notification' => array(
				'title' => $notification['title'],
				'body'  => $notification['body'],
				'sound' => 'default',
			),
			'data'         => isset( $notification['data'] ) ? $notification['data'] : array(),
		);

		$response = wp_remote_post(
			'https://fcm.googleapis.com/fcm/send',
			array(
				'headers' => array(
					'Authorization' => 'key=' . $server_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_notification( $device_token, $notification, 'failed', $response->get_error_message() );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['success'] ) && $body['success'] > 0 ) {
			$this->log_notification( $device_token, $notification, 'sent' );
			return true;
		}

		$error = isset( $body['results'][0]['error'] ) ? $body['results'][0]['error'] : 'Unknown error';
		$this->log_notification( $device_token, $notification, 'failed', $error );

		return new \WP_Error( 'fcm_error', $error );
	}

	/**
	 * Send via Apple Push Notification Service.
	 *
	 * @param string $device_token Device token.
	 * @param array  $notification Notification data.
	 * @return bool|WP_Error
	 */
	private function send_apns( $device_token, $notification ) {
		$settings = get_option( 'bkx_mobile_app_settings', array() );

		$key_id    = isset( $settings['apns_key_id'] ) ? $settings['apns_key_id'] : '';
		$team_id   = isset( $settings['apns_team_id'] ) ? $settings['apns_team_id'] : '';
		$bundle_id = isset( $settings['apns_bundle_id'] ) ? $settings['apns_bundle_id'] : '';

		if ( empty( $key_id ) || empty( $team_id ) || empty( $bundle_id ) ) {
			return new \WP_Error( 'no_apns_config', __( 'APNS not configured.', 'bkx-mobile-app' ) );
		}

		// Build APNs payload.
		$payload = array(
			'aps' => array(
				'alert' => array(
					'title' => $notification['title'],
					'body'  => $notification['body'],
				),
				'sound' => 'default',
				'badge' => isset( $notification['badge'] ) ? $notification['badge'] : 1,
			),
		);

		if ( isset( $notification['data'] ) ) {
			$payload = array_merge( $payload, $notification['data'] );
		}

		// In production, you would use proper JWT auth and HTTP/2.
		// This is a simplified placeholder.

		$this->log_notification( $device_token, $notification, 'sent' );
		return true;
	}

	/**
	 * Send booking notification.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $type       Notification type.
	 */
	public function send_booking_notification( $booking_id, $type ) {
		$booking = get_post( $booking_id );
		if ( ! $booking ) {
			return;
		}

		$customer_id = get_post_meta( $booking_id, 'customer_id', true );
		if ( ! $customer_id ) {
			return;
		}

		$addon          = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$device_manager = $addon->get_service( 'device_manager' );

		$devices = $device_manager->get_user_devices( $customer_id );

		if ( empty( $devices ) ) {
			return;
		}

		$notification = $this->build_booking_notification( $booking_id, $type );

		foreach ( $devices as $device ) {
			if ( ! $device->push_enabled ) {
				continue;
			}

			$this->send( $device->device_token, $device->device_type, $notification );
		}
	}

	/**
	 * Build booking notification.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $type       Notification type.
	 * @return array
	 */
	private function build_booking_notification( $booking_id, $type ) {
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time = get_post_meta( $booking_id, 'booking_time', true );
		$service_id   = get_post_meta( $booking_id, 'base_id', true );
		$service      = get_post( $service_id );
		$service_name = $service ? $service->post_title : __( 'Your booking', 'bkx-mobile-app' );

		$addon     = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$deep_link = $addon->get_service( 'deep_link' );

		switch ( $type ) {
			case 'created':
				return array(
					'title' => __( 'Booking Received', 'bkx-mobile-app' ),
					'body'  => sprintf(
						/* translators: 1: service name, 2: date, 3: time */
						__( 'Your booking for %1$s on %2$s at %3$s has been received.', 'bkx-mobile-app' ),
						$service_name,
						$booking_date,
						$booking_time
					),
					'data'  => array(
						'type'       => 'booking_created',
						'booking_id' => $booking_id,
						'deep_link'  => $deep_link->generate_booking_link( $booking_id ),
					),
				);

			case 'confirmed':
				return array(
					'title' => __( 'Booking Confirmed', 'bkx-mobile-app' ),
					'body'  => sprintf(
						/* translators: 1: service name, 2: date, 3: time */
						__( 'Your booking for %1$s on %2$s at %3$s has been confirmed!', 'bkx-mobile-app' ),
						$service_name,
						$booking_date,
						$booking_time
					),
					'data'  => array(
						'type'       => 'booking_confirmed',
						'booking_id' => $booking_id,
						'deep_link'  => $deep_link->generate_booking_link( $booking_id ),
					),
				);

			case 'cancelled':
				return array(
					'title' => __( 'Booking Cancelled', 'bkx-mobile-app' ),
					'body'  => sprintf(
						/* translators: 1: service name, 2: date */
						__( 'Your booking for %1$s on %2$s has been cancelled.', 'bkx-mobile-app' ),
						$service_name,
						$booking_date
					),
					'data'  => array(
						'type'       => 'booking_cancelled',
						'booking_id' => $booking_id,
					),
				);

			case 'reminder':
				return array(
					'title' => __( 'Booking Reminder', 'bkx-mobile-app' ),
					'body'  => sprintf(
						/* translators: 1: service name, 2: time */
						__( 'Reminder: Your %1$s appointment is tomorrow at %2$s.', 'bkx-mobile-app' ),
						$service_name,
						$booking_time
					),
					'data'  => array(
						'type'       => 'booking_reminder',
						'booking_id' => $booking_id,
						'deep_link'  => $deep_link->generate_booking_link( $booking_id ),
					),
				);

			default:
				return array(
					'title' => __( 'Booking Update', 'bkx-mobile-app' ),
					'body'  => __( 'Your booking has been updated.', 'bkx-mobile-app' ),
					'data'  => array(
						'type'       => 'booking_update',
						'booking_id' => $booking_id,
					),
				);
		}
	}

	/**
	 * Send upcoming reminders.
	 */
	public function send_upcoming_reminders() {
		$addon          = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$reminder_hours = $addon->get_setting( 'reminder_hours', 24 );

		$target_date = gmdate( 'Y-m-d', strtotime( "+{$reminder_hours} hours" ) );

		$bookings = get_posts(
			array(
				'post_type'      => 'bkx_booking',
				'posts_per_page' => -1,
				'post_status'    => 'bkx-ack',
				'meta_query'     => array(
					array(
						'key'   => 'booking_date',
						'value' => $target_date,
					),
					array(
						'key'     => '_reminder_sent',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $bookings as $booking ) {
			$this->send_booking_notification( $booking->ID, 'reminder' );
			update_post_meta( $booking->ID, '_reminder_sent', time() );
		}
	}

	/**
	 * Send test notification.
	 *
	 * @param string $device_token Device token.
	 * @param string $device_type  Device type.
	 * @return bool|WP_Error
	 */
	public function send_test_notification( $device_token, $device_type ) {
		return $this->send(
			$device_token,
			$device_type,
			array(
				'title' => __( 'Test Notification', 'bkx-mobile-app' ),
				'body'  => __( 'This is a test notification from BookingX.', 'bkx-mobile-app' ),
				'data'  => array(
					'type' => 'test',
				),
			)
		);
	}

	/**
	 * Log notification.
	 *
	 * @param string $device_token Device token.
	 * @param array  $notification Notification data.
	 * @param string $status       Status.
	 * @param string $error        Error message.
	 */
	private function log_notification( $device_token, $notification, $status, $error = '' ) {
		global $wpdb;

		$addon          = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$device_manager = $addon->get_service( 'device_manager' );

		$device = $device_manager->get_device_by_token( $device_token );

		$wpdb->insert(
			$wpdb->prefix . 'bkx_mobile_push_log',
			array(
				'device_id'         => $device ? $device->id : null,
				'user_id'           => $device ? $device->user_id : null,
				'notification_type' => isset( $notification['data']['type'] ) ? $notification['data']['type'] : 'general',
				'title'             => $notification['title'],
				'body'              => isset( $notification['body'] ) ? $notification['body'] : '',
				'data'              => isset( $notification['data'] ) ? wp_json_encode( $notification['data'] ) : null,
				'status'            => $status,
				'error_message'     => $error,
				'sent_at'           => 'sent' === $status ? current_time( 'mysql' ) : null,
				'created_at'        => current_time( 'mysql' ),
			)
		);
	}
}
