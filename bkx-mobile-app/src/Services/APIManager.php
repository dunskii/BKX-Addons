<?php
/**
 * API Manager Service for Mobile App Framework.
 *
 * @package BookingX\MobileApp\Services
 */

namespace BookingX\MobileApp\Services;

defined( 'ABSPATH' ) || exit;

/**
 * APIManager class.
 */
class APIManager {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'bkx-mobile/v1';

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Authentication.
		register_rest_route(
			self::REST_NAMESPACE,
			'/auth/login',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_login' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/auth/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_register' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/auth/refresh',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_token_refresh' ),
				'permission_callback' => '__return_true',
			)
		);

		// Device registration.
		register_rest_route(
			self::REST_NAMESPACE,
			'/devices',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'register_device' ),
				'permission_callback' => array( $this, 'check_api_auth' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/devices/(?P<token>[a-zA-Z0-9_-]+)',
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'unregister_device' ),
				'permission_callback' => array( $this, 'check_api_auth' ),
			)
		);

		// Bookings.
		register_rest_route(
			self::REST_NAMESPACE,
			'/bookings',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_bookings' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/bookings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_booking' ),
				'permission_callback' => array( $this, 'check_api_auth' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/bookings/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_booking' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/bookings/(?P<id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel_booking' ),
				'permission_callback' => array( $this, 'check_user_auth' ),
			)
		);

		// Services.
		register_rest_route(
			self::REST_NAMESPACE,
			'/services',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_services' ),
				'permission_callback' => array( $this, 'check_api_auth' ),
			)
		);

		// Resources.
		register_rest_route(
			self::REST_NAMESPACE,
			'/resources',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_resources' ),
				'permission_callback' => array( $this, 'check_api_auth' ),
			)
		);

		// Availability.
		register_rest_route(
			self::REST_NAMESPACE,
			'/availability',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_availability' ),
				'permission_callback' => array( $this, 'check_api_auth' ),
			)
		);

		// App config.
		register_rest_route(
			self::REST_NAMESPACE,
			'/config',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_app_config' ),
				'permission_callback' => array( $this, 'check_api_auth' ),
			)
		);
	}

	/**
	 * Check API authentication.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function check_api_auth( $request ) {
		$api_key = $request->get_header( 'X-API-Key' );

		if ( empty( $api_key ) ) {
			return false;
		}

		return $this->validate_api_key( $api_key );
	}

	/**
	 * Check user authentication.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	public function check_user_auth( $request ) {
		if ( ! $this->check_api_auth( $request ) ) {
			return false;
		}

		$auth_header = $request->get_header( 'Authorization' );

		if ( empty( $auth_header ) ) {
			return false;
		}

		if ( strpos( $auth_header, 'Bearer ' ) !== 0 ) {
			return false;
		}

		$token = substr( $auth_header, 7 );
		return $this->validate_user_token( $token );
	}

	/**
	 * Validate API key.
	 *
	 * @param string $api_key API key.
	 * @return bool
	 */
	private function validate_api_key( $api_key ) {
		global $wpdb;

		$key = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_mobile_api_keys
				WHERE api_key = %s AND is_active = 1",
				$api_key
			)
		);

		if ( ! $key ) {
			return false;
		}

		// Update last used.
		$wpdb->update(
			$wpdb->prefix . 'bkx_mobile_api_keys',
			array( 'last_used' => current_time( 'mysql' ) ),
			array( 'id' => $key->id )
		);

		return true;
	}

	/**
	 * Validate user token.
	 *
	 * @param string $token Token.
	 * @return bool|int User ID or false.
	 */
	private function validate_user_token( $token ) {
		// Decode JWT token (simplified - use proper JWT library in production).
		$parts = explode( '.', $token );
		if ( count( $parts ) !== 3 ) {
			return false;
		}

		$payload = json_decode( base64_decode( $parts[1] ), true );

		if ( ! $payload || ! isset( $payload['user_id'] ) || ! isset( $payload['exp'] ) ) {
			return false;
		}

		// Check expiration.
		if ( $payload['exp'] < time() ) {
			return false;
		}

		return (int) $payload['user_id'];
	}

	/**
	 * Generate API key.
	 *
	 * @param string $key_name Key name.
	 * @return array|WP_Error
	 */
	public function generate_api_key( $key_name ) {
		global $wpdb;

		$api_key    = bin2hex( random_bytes( 32 ) );
		$api_secret = bin2hex( random_bytes( 32 ) );

		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_mobile_api_keys',
			array(
				'key_name'   => $key_name,
				'api_key'    => $api_key,
				'api_secret' => $api_secret,
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);

		if ( ! $result ) {
			return new \WP_Error( 'insert_failed', __( 'Failed to generate API key.', 'bkx-mobile-app' ) );
		}

		return array(
			'id'         => $wpdb->insert_id,
			'key_name'   => $key_name,
			'api_key'    => $api_key,
			'api_secret' => $api_secret,
		);
	}

	/**
	 * Revoke API key.
	 *
	 * @param int $key_id Key ID.
	 * @return bool|WP_Error
	 */
	public function revoke_api_key( $key_id ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'bkx_mobile_api_keys',
			array( 'is_active' => 0 ),
			array( 'id' => $key_id )
		);

		if ( false === $result ) {
			return new \WP_Error( 'update_failed', __( 'Failed to revoke API key.', 'bkx-mobile-app' ) );
		}

		return true;
	}

	/**
	 * Get all API keys.
	 *
	 * @return array
	 */
	public function get_api_keys() {
		global $wpdb;

		return $wpdb->get_results(
			"SELECT id, key_name, api_key, is_active, last_used, created_at
			FROM {$wpdb->prefix}bkx_mobile_api_keys
			ORDER BY created_at DESC"
		);
	}

	/**
	 * Handle login.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_login( $request ) {
		$username = $request->get_param( 'username' );
		$password = $request->get_param( 'password' );

		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'Invalid credentials' ),
				401
			);
		}

		$token = $this->generate_user_token( $user->ID );

		return new \WP_REST_Response(
			array(
				'token'   => $token,
				'user'    => array(
					'id'           => $user->ID,
					'email'        => $user->user_email,
					'display_name' => $user->display_name,
				),
				'expires' => time() + ( 7 * DAY_IN_SECONDS ),
			),
			200
		);
	}

	/**
	 * Handle register.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_register( $request ) {
		$email    = $request->get_param( 'email' );
		$password = $request->get_param( 'password' );
		$name     = $request->get_param( 'name' );

		if ( email_exists( $email ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'Email already exists' ),
				400
			);
		}

		$user_id = wp_create_user( $email, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			return new \WP_REST_Response(
				array( 'error' => $user_id->get_error_message() ),
				400
			);
		}

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $name,
			)
		);

		$token = $this->generate_user_token( $user_id );

		return new \WP_REST_Response(
			array(
				'token' => $token,
				'user'  => array(
					'id'           => $user_id,
					'email'        => $email,
					'display_name' => $name,
				),
			),
			201
		);
	}

	/**
	 * Handle token refresh.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_token_refresh( $request ) {
		$refresh_token = $request->get_param( 'refresh_token' );

		// Validate refresh token and generate new access token.
		// Simplified for this example.

		return new \WP_REST_Response(
			array( 'error' => 'Not implemented' ),
			501
		);
	}

	/**
	 * Generate user token.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function generate_user_token( $user_id ) {
		$header = base64_encode( wp_json_encode( array( 'alg' => 'HS256', 'typ' => 'JWT' ) ) );

		$payload = base64_encode(
			wp_json_encode(
				array(
					'user_id' => $user_id,
					'iat'     => time(),
					'exp'     => time() + ( 7 * DAY_IN_SECONDS ),
				)
			)
		);

		$secret    = wp_salt( 'auth' );
		$signature = base64_encode( hash_hmac( 'sha256', $header . '.' . $payload, $secret, true ) );

		return $header . '.' . $payload . '.' . $signature;
	}

	/**
	 * Register device.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function register_device( $request ) {
		$addon          = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$device_manager = $addon->get_service( 'device_manager' );

		$result = $device_manager->register_device(
			array(
				'device_token' => $request->get_param( 'device_token' ),
				'device_type'  => $request->get_param( 'device_type' ),
				'device_name'  => $request->get_param( 'device_name' ),
				'device_model' => $request->get_param( 'device_model' ),
				'os_version'   => $request->get_param( 'os_version' ),
				'app_version'  => $request->get_param( 'app_version' ),
				'user_id'      => $request->get_param( 'user_id' ),
			)
		);

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				400
			);
		}

		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Unregister device.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function unregister_device( $request ) {
		$addon          = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$device_manager = $addon->get_service( 'device_manager' );

		$result = $device_manager->unregister_device( $request->get_param( 'token' ) );

		return new \WP_REST_Response( array( 'success' => $result ), 200 );
	}

	/**
	 * Get bookings.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_bookings( $request ) {
		// Get bookings for the authenticated user.
		$user_id = $this->get_current_user_id( $request );

		$args = array(
			'post_type'      => 'bkx_booking',
			'posts_per_page' => 50,
			'meta_query'     => array(
				array(
					'key'   => 'customer_id',
					'value' => $user_id,
				),
			),
		);

		$bookings = get_posts( $args );
		$data     = array();

		foreach ( $bookings as $booking ) {
			$data[] = $this->format_booking( $booking );
		}

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Get booking.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_booking( $request ) {
		$booking_id = $request->get_param( 'id' );
		$booking    = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_REST_Response(
				array( 'error' => 'Booking not found' ),
				404
			);
		}

		return new \WP_REST_Response( $this->format_booking( $booking ), 200 );
	}

	/**
	 * Create booking.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create_booking( $request ) {
		// Create booking via BookingX core.
		// This would integrate with BkxBooking class.

		return new \WP_REST_Response(
			array( 'message' => 'Booking creation via API' ),
			201
		);
	}

	/**
	 * Cancel booking.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function cancel_booking( $request ) {
		$booking_id = $request->get_param( 'id' );

		wp_update_post(
			array(
				'ID'          => $booking_id,
				'post_status' => 'bkx-cancelled',
			)
		);

		return new \WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Get services.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_services( $request ) {
		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		$data = array();
		foreach ( $services as $service ) {
			$data[] = array(
				'id'          => $service->ID,
				'name'        => $service->post_title,
				'description' => $service->post_excerpt,
				'price'       => get_post_meta( $service->ID, 'base_price', true ),
				'duration'    => get_post_meta( $service->ID, 'base_time', true ),
				'image'       => get_the_post_thumbnail_url( $service->ID, 'medium' ),
			);
		}

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Get resources.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_resources( $request ) {
		$resources = get_posts(
			array(
				'post_type'      => 'bkx_seat',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		$data = array();
		foreach ( $resources as $resource ) {
			$data[] = array(
				'id'       => $resource->ID,
				'name'     => $resource->post_title,
				'bio'      => $resource->post_content,
				'image'    => get_the_post_thumbnail_url( $resource->ID, 'medium' ),
				'services' => get_post_meta( $resource->ID, 'seat_base', true ),
			);
		}

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * Get availability.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_availability( $request ) {
		$service_id  = $request->get_param( 'service_id' );
		$resource_id = $request->get_param( 'resource_id' );
		$date        = $request->get_param( 'date' );

		// This would integrate with BookingX availability system.
		$slots = array();

		return new \WP_REST_Response( array( 'slots' => $slots ), 200 );
	}

	/**
	 * Get app config.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_app_config( $request ) {
		$addon      = \BookingX\MobileApp\MobileAppAddon::get_instance();
		$app_config = $addon->get_service( 'app_config' );

		return new \WP_REST_Response( $app_config->get_config(), 200 );
	}

	/**
	 * Format booking for API response.
	 *
	 * @param \WP_Post $booking Booking post.
	 * @return array
	 */
	private function format_booking( $booking ) {
		return array(
			'id'            => $booking->ID,
			'status'        => $booking->post_status,
			'date'          => get_post_meta( $booking->ID, 'booking_date', true ),
			'time'          => get_post_meta( $booking->ID, 'booking_time', true ),
			'service_id'    => get_post_meta( $booking->ID, 'base_id', true ),
			'resource_id'   => get_post_meta( $booking->ID, 'seat_id', true ),
			'customer_name' => get_post_meta( $booking->ID, 'customer_name', true ),
			'total'         => get_post_meta( $booking->ID, 'booking_total', true ),
			'created_at'    => $booking->post_date,
		);
	}

	/**
	 * Get current user ID from request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return int|false
	 */
	private function get_current_user_id( $request ) {
		$auth_header = $request->get_header( 'Authorization' );

		if ( empty( $auth_header ) || strpos( $auth_header, 'Bearer ' ) !== 0 ) {
			return false;
		}

		$token = substr( $auth_header, 7 );
		return $this->validate_user_token( $token );
	}
}
