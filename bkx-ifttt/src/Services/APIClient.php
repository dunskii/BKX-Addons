<?php
/**
 * API Client Service for IFTTT Integration.
 *
 * Handles communication with IFTTT Platform API.
 *
 * @package BookingX\IFTTT\Services
 */

namespace BookingX\IFTTT\Services;

defined( 'ABSPATH' ) || exit;

/**
 * APIClient class.
 */
class APIClient {

	/**
	 * IFTTT Realtime API endpoint.
	 *
	 * @var string
	 */
	const REALTIME_API = 'https://realtime.ifttt.com/v1/notifications';

	/**
	 * IFTTT Service API endpoint.
	 *
	 * @var string
	 */
	const SERVICE_API = 'https://connect.ifttt.com/v2';

	/**
	 * Parent addon instance.
	 *
	 * @var \BookingX\IFTTT\IFTTTAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @param \BookingX\IFTTT\IFTTTAddon $addon Parent addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Get the service key.
	 *
	 * @return string
	 */
	private function get_service_key() {
		return $this->addon->get_setting( 'service_key', '' );
	}

	/**
	 * Make an API request.
	 *
	 * @param string $endpoint Endpoint URL.
	 * @param string $method   HTTP method.
	 * @param array  $body     Request body.
	 * @param array  $headers  Additional headers.
	 * @return array|\WP_Error
	 */
	private function request( $endpoint, $method = 'GET', $body = array(), $headers = array() ) {
		$service_key = $this->get_service_key();

		$default_headers = array(
			'Content-Type'       => 'application/json',
			'IFTTT-Service-Key'  => $service_key,
		);

		$args = array(
			'method'      => $method,
			'timeout'     => 30,
			'headers'     => array_merge( $default_headers, $headers ),
		);

		if ( ! empty( $body ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'API request failed', $response->get_error_message() );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			$error_message = $data['message'] ?? $body;
			$this->log_error( 'API error response', array(
				'code'    => $code,
				'message' => $error_message,
			) );
			return new \WP_Error( 'api_error', $error_message, array( 'status' => $code ) );
		}

		return array(
			'code' => $code,
			'data' => $data,
		);
	}

	/**
	 * Send realtime notification to IFTTT.
	 *
	 * Notifies IFTTT that new data is available for polling.
	 *
	 * @param string $user_id IFTTT user ID.
	 * @return bool Success status.
	 */
	public function send_realtime_notification( $user_id ) {
		$response = $this->request(
			self::REALTIME_API,
			'POST',
			array(
				'data' => array(
					array( 'user_id' => $user_id ),
				),
			)
		);

		return ! is_wp_error( $response );
	}

	/**
	 * Send realtime notifications to multiple users.
	 *
	 * @param array $user_ids Array of IFTTT user IDs.
	 * @return bool Success status.
	 */
	public function send_batch_notifications( $user_ids ) {
		$data = array_map(
			function ( $user_id ) {
				return array( 'user_id' => $user_id );
			},
			$user_ids
		);

		$response = $this->request(
			self::REALTIME_API,
			'POST',
			array( 'data' => $data )
		);

		return ! is_wp_error( $response );
	}

	/**
	 * Verify IFTTT service authentication.
	 *
	 * @param string $service_key Service key from request.
	 * @return bool
	 */
	public function verify_service_key( $service_key ) {
		$stored_key = $this->get_service_key();
		return hash_equals( $stored_key, $service_key );
	}

	/**
	 * Generate user token for IFTTT connection.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string
	 */
	public function generate_user_token( $user_id ) {
		$service_key = $this->get_service_key();
		$timestamp   = time();
		$data        = $user_id . '|' . $timestamp;

		return base64_encode( $data . '|' . hash_hmac( 'sha256', $data, $service_key ) );
	}

	/**
	 * Verify user token from IFTTT.
	 *
	 * @param string $token User token.
	 * @return int|false WordPress user ID or false if invalid.
	 */
	public function verify_user_token( $token ) {
		$decoded = base64_decode( $token, true );
		if ( ! $decoded ) {
			return false;
		}

		$parts = explode( '|', $decoded );
		if ( count( $parts ) !== 3 ) {
			return false;
		}

		list( $user_id, $timestamp, $signature ) = $parts;

		$service_key = $this->get_service_key();
		$data        = $user_id . '|' . $timestamp;
		$expected    = hash_hmac( 'sha256', $data, $service_key );

		if ( ! hash_equals( $expected, $signature ) ) {
			return false;
		}

		// Token expires after 24 hours.
		if ( time() - intval( $timestamp ) > 86400 ) {
			return false;
		}

		return intval( $user_id );
	}

	/**
	 * Build service status response.
	 *
	 * @return array
	 */
	public function get_service_status() {
		return array(
			'available'      => true,
			'version'        => BKX_IFTTT_VERSION,
			'triggers'       => $this->get_enabled_triggers(),
			'actions'        => $this->get_enabled_actions(),
		);
	}

	/**
	 * Get enabled triggers.
	 *
	 * @return array
	 */
	private function get_enabled_triggers() {
		$triggers        = $this->addon->get_setting( 'triggers', array() );
		$trigger_handler = $this->addon->get_service( 'trigger_handler' );

		if ( ! $trigger_handler ) {
			return array();
		}

		$enabled = array();
		foreach ( $triggers as $slug => $is_enabled ) {
			if ( $is_enabled ) {
				$trigger = $trigger_handler->get_trigger( $slug );
				if ( $trigger ) {
					$enabled[] = array(
						'slug' => $slug,
						'name' => $trigger['name'],
					);
				}
			}
		}

		return $enabled;
	}

	/**
	 * Get enabled actions.
	 *
	 * @return array
	 */
	private function get_enabled_actions() {
		$actions        = $this->addon->get_setting( 'actions', array() );
		$action_handler = $this->addon->get_service( 'action_handler' );

		if ( ! $action_handler ) {
			return array();
		}

		$enabled = array();
		foreach ( $actions as $slug => $is_enabled ) {
			if ( $is_enabled ) {
				$action = $action_handler->get_action( $slug );
				if ( $action ) {
					$enabled[] = array(
						'slug' => $slug,
						'name' => $action['name'],
					);
				}
			}
		}

		return $enabled;
	}

	/**
	 * Build trigger fields response for IFTTT.
	 *
	 * @param string $trigger_slug Trigger slug.
	 * @return array
	 */
	public function get_trigger_fields_response( $trigger_slug ) {
		$trigger_handler = $this->addon->get_service( 'trigger_handler' );
		if ( ! $trigger_handler ) {
			return array( 'data' => array() );
		}

		$trigger = $trigger_handler->get_trigger( $trigger_slug );
		if ( ! $trigger ) {
			return array( 'data' => array() );
		}

		$fields = array();
		foreach ( $trigger['fields'] as $field ) {
			$fields[] = array(
				'label'       => $field['name'],
				'value'       => '{{' . $field['slug'] . '}}',
				'helper_text' => '',
			);
		}

		return array( 'data' => $fields );
	}

	/**
	 * Build action fields response for IFTTT.
	 *
	 * @param string $action_slug Action slug.
	 * @return array
	 */
	public function get_action_fields_response( $action_slug ) {
		$action_handler = $this->addon->get_service( 'action_handler' );
		if ( ! $action_handler ) {
			return array( 'data' => array() );
		}

		$action = $action_handler->get_action( $action_slug );
		if ( ! $action ) {
			return array( 'data' => array() );
		}

		$fields = array();
		foreach ( $action['fields'] as $field ) {
			$fields[] = array(
				'name'         => $field['slug'],
				'label'        => $field['name'],
				'required'     => ! empty( $field['required'] ),
				'controlType'  => $this->get_control_type( $field['type'] ),
			);
		}

		return array( 'data' => $fields );
	}

	/**
	 * Map field type to IFTTT control type.
	 *
	 * @param string $type Field type.
	 * @return string
	 */
	private function get_control_type( $type ) {
		$map = array(
			'string'   => 'text',
			'number'   => 'text',
			'datetime' => 'text',
			'email'    => 'text',
			'phone'    => 'text',
			'url'      => 'text',
		);

		return $map[ $type ] ?? 'text';
	}

	/**
	 * Build dynamic options for dropdowns.
	 *
	 * @param string $field_slug Field slug.
	 * @return array
	 */
	public function get_field_options( $field_slug ) {
		switch ( $field_slug ) {
			case 'service_id':
				return $this->get_service_options();

			case 'staff_id':
				return $this->get_staff_options();

			case 'status':
				return $this->get_status_options();

			default:
				return array();
		}
	}

	/**
	 * Get service options for dropdown.
	 *
	 * @return array
	 */
	private function get_service_options() {
		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'posts_per_page' => 100,
				'post_status'    => 'publish',
			)
		);

		$options = array();
		foreach ( $services as $service ) {
			$options[] = array(
				'label' => $service->post_title,
				'value' => (string) $service->ID,
			);
		}

		return array( 'data' => $options );
	}

	/**
	 * Get staff options for dropdown.
	 *
	 * @return array
	 */
	private function get_staff_options() {
		$staff = get_posts(
			array(
				'post_type'      => 'bkx_seat',
				'posts_per_page' => 100,
				'post_status'    => 'publish',
			)
		);

		$options = array(
			array(
				'label' => __( 'Any Available', 'bkx-ifttt' ),
				'value' => '0',
			),
		);

		foreach ( $staff as $member ) {
			$options[] = array(
				'label' => $member->post_title,
				'value' => (string) $member->ID,
			);
		}

		return array( 'data' => $options );
	}

	/**
	 * Get status options for dropdown.
	 *
	 * @return array
	 */
	private function get_status_options() {
		return array(
			'data' => array(
				array(
					'label' => __( 'Pending', 'bkx-ifttt' ),
					'value' => 'pending',
				),
				array(
					'label' => __( 'Confirmed', 'bkx-ifttt' ),
					'value' => 'confirmed',
				),
				array(
					'label' => __( 'Completed', 'bkx-ifttt' ),
					'value' => 'completed',
				),
				array(
					'label' => __( 'Cancelled', 'bkx-ifttt' ),
					'value' => 'cancelled',
				),
			),
		);
	}

	/**
	 * Log error.
	 *
	 * @param string $message Error message.
	 * @param mixed  $context Error context.
	 */
	private function log_error( $message, $context = null ) {
		if ( ! $this->addon->get_setting( 'log_requests', false ) ) {
			return;
		}

		$logs = get_option( 'bkx_ifttt_error_logs', array() );

		$logs[] = array(
			'timestamp' => current_time( 'mysql' ),
			'message'   => $message,
			'context'   => $context,
		);

		// Keep only last 50 errors.
		$logs = array_slice( $logs, -50 );

		update_option( 'bkx_ifttt_error_logs', $logs );
	}

	/**
	 * Check rate limiting.
	 *
	 * @param string $identifier Request identifier.
	 * @return bool True if within limit.
	 */
	public function check_rate_limit( $identifier ) {
		$limit     = $this->addon->get_setting( 'rate_limit', 100 );
		$cache_key = 'bkx_ifttt_rate_' . md5( $identifier );

		$count = get_transient( $cache_key );
		if ( false === $count ) {
			$count = 0;
		}

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $cache_key, $count + 1, HOUR_IN_SECONDS );
		return true;
	}

	/**
	 * Test API connection.
	 *
	 * @return array
	 */
	public function test_connection() {
		$service_key = $this->get_service_key();

		if ( empty( $service_key ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Service key is not configured.', 'bkx-ifttt' ),
			);
		}

		// For now, just verify the key is set and has proper format.
		if ( strlen( $service_key ) < 16 ) {
			return array(
				'success' => false,
				'error'   => __( 'Service key appears to be invalid.', 'bkx-ifttt' ),
			);
		}

		return array(
			'success' => true,
			'data'    => array(
				'status'  => 'connected',
				'version' => BKX_IFTTT_VERSION,
			),
		);
	}
}
