<?php
/**
 * Mini Program Service.
 *
 * Handles WeChat Mini Program operations.
 *
 * @package BookingX\WeChat
 */

namespace BookingX\WeChat\Services;

defined( 'ABSPATH' ) || exit;

/**
 * MiniProgramService class.
 */
class MiniProgramService {

	/**
	 * Addon instance.
	 *
	 * @var \BookingX\WeChat\WeChatAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @param \BookingX\WeChat\WeChatAddon $addon Addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Handle Mini Program login.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_login( $request ) {
		$code = $request->get_param( 'code' );

		if ( empty( $code ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'missing_code', 'message' => __( 'Missing code parameter.', 'bkx-wechat' ) ),
				400
			);
		}

		$api     = $this->addon->get_service( 'wechat_api' );
		$session = $api->code_to_session( $code );

		if ( ! $session ) {
			return new \WP_REST_Response(
				array( 'error' => 'login_failed', 'message' => __( 'Login failed.', 'bkx-wechat' ) ),
				400
			);
		}

		$openid      = $session['openid'] ?? '';
		$session_key = $session['session_key'] ?? '';
		$unionid     = $session['unionid'] ?? '';

		if ( empty( $openid ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'invalid_session', 'message' => __( 'Invalid session data.', 'bkx-wechat' ) ),
				400
			);
		}

		// Create session token.
		$token = $this->create_session_token( $openid, $session_key );

		// Get or create WordPress user.
		$user_id = $this->get_or_create_user( $openid, $unionid );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'token'   => $token,
				'user'    => array(
					'id'     => $user_id,
					'openid' => $openid,
				),
			),
			200
		);
	}

	/**
	 * Create session token.
	 *
	 * @param string $openid      User OpenID.
	 * @param string $session_key Session key.
	 * @return string
	 */
	private function create_session_token( $openid, $session_key ) {
		$token = wp_generate_password( 64, false );

		$session_data = array(
			'openid'      => $openid,
			'session_key' => $session_key,
			'created_at'  => time(),
		);

		set_transient( 'bkx_wechat_mp_session_' . $token, $session_data, DAY_IN_SECONDS );

		return $token;
	}

	/**
	 * Verify session token.
	 *
	 * @param string $token Session token.
	 * @return bool|array Session data or false.
	 */
	public function verify_session_token( $token ) {
		$session = get_transient( 'bkx_wechat_mp_session_' . $token );

		if ( ! $session ) {
			return false;
		}

		// Extend session.
		set_transient( 'bkx_wechat_mp_session_' . $token, $session, DAY_IN_SECONDS );

		return $session;
	}

	/**
	 * Get OpenID from token.
	 *
	 * @param string $token Session token.
	 * @return string|false
	 */
	public function get_openid_from_token( $token ) {
		$session = $this->verify_session_token( $token );

		if ( ! $session ) {
			return false;
		}

		return $session['openid'] ?? false;
	}

	/**
	 * Get or create WordPress user.
	 *
	 * @param string $openid  WeChat OpenID.
	 * @param string $unionid Union ID.
	 * @return int|false
	 */
	private function get_or_create_user( $openid, $unionid = '' ) {
		// Find existing user by OpenID.
		$users = get_users(
			array(
				'meta_key'   => 'wechat_mini_openid',
				'meta_value' => $openid,
				'number'     => 1,
			)
		);

		if ( ! empty( $users ) ) {
			return $users[0]->ID;
		}

		// Try to find by UnionID if exists.
		if ( $unionid ) {
			$users = get_users(
				array(
					'meta_key'   => 'wechat_unionid',
					'meta_value' => $unionid,
					'number'     => 1,
				)
			);

			if ( ! empty( $users ) ) {
				// Link Mini Program OpenID to existing user.
				update_user_meta( $users[0]->ID, 'wechat_mini_openid', $openid );
				return $users[0]->ID;
			}
		}

		// Create new user.
		$username = 'wechat_mp_' . substr( md5( $openid ), 0, 8 );
		$password = wp_generate_password( 16, true, true );
		$email    = $username . '@wechat.mini.placeholder';

		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			return false;
		}

		// Save WeChat data.
		update_user_meta( $user_id, 'wechat_mini_openid', $openid );

		if ( $unionid ) {
			update_user_meta( $user_id, 'wechat_unionid', $unionid );
		}

		return $user_id;
	}

	/**
	 * Get services for Mini Program.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_services( $request ) {
		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);

		$result = array();

		foreach ( $services as $service ) {
			$result[] = array(
				'id'          => $service->ID,
				'name'        => $service->post_title,
				'description' => $service->post_excerpt,
				'duration'    => get_post_meta( $service->ID, 'base_time', true ),
				'price'       => get_post_meta( $service->ID, 'base_price', true ),
				'image'       => get_the_post_thumbnail_url( $service->ID, 'medium' ),
			);
		}

		return new \WP_REST_Response( array( 'services' => $result ), 200 );
	}

	/**
	 * Get availability for Mini Program.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_availability( $request ) {
		$service_id = absint( $request->get_param( 'service_id' ) );
		$date       = sanitize_text_field( $request->get_param( 'date' ) );

		if ( ! $date ) {
			$date = gmdate( 'Y-m-d' );
		}

		// Get available slots using BookingX core.
		$slots = array();

		if ( function_exists( 'bkx_get_available_slots' ) ) {
			$slots = bkx_get_available_slots( $service_id, $date );
		} else {
			// Fallback: generate sample slots.
			$working_hours = array( '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00' );
			foreach ( $working_hours as $time ) {
				$slots[] = array(
					'time'      => $time,
					'available' => true,
				);
			}
		}

		return new \WP_REST_Response(
			array(
				'date'  => $date,
				'slots' => $slots,
			),
			200
		);
	}

	/**
	 * Create booking from Mini Program.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function create_booking( $request ) {
		$token  = $request->get_header( 'X-WeChat-Token' );
		$openid = $this->get_openid_from_token( $token );

		if ( ! $openid ) {
			return new \WP_REST_Response(
				array( 'error' => 'unauthorized', 'message' => __( 'Unauthorized.', 'bkx-wechat' ) ),
				401
			);
		}

		$service_id = absint( $request->get_param( 'service_id' ) );
		$seat_id    = absint( $request->get_param( 'seat_id' ) );
		$date       = sanitize_text_field( $request->get_param( 'date' ) );
		$time       = sanitize_text_field( $request->get_param( 'time' ) );
		$name       = sanitize_text_field( $request->get_param( 'name' ) );
		$phone      = sanitize_text_field( $request->get_param( 'phone' ) );

		// Validate required fields.
		if ( empty( $service_id ) || empty( $date ) || empty( $time ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'missing_fields', 'message' => __( 'Missing required fields.', 'bkx-wechat' ) ),
				400
			);
		}

		// Create booking.
		$booking_data = array(
			'post_type'   => 'bkx_booking',
			'post_status' => 'bkx-pending',
			'post_title'  => sprintf( '%s - %s %s', $name ?: 'WeChat User', $date, $time ),
		);

		$booking_id = wp_insert_post( $booking_data );

		if ( is_wp_error( $booking_id ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'booking_failed', 'message' => __( 'Failed to create booking.', 'bkx-wechat' ) ),
				500
			);
		}

		// Save booking meta.
		update_post_meta( $booking_id, 'base_id', $service_id );
		update_post_meta( $booking_id, 'seat_id', $seat_id );
		update_post_meta( $booking_id, 'booking_date', $date );
		update_post_meta( $booking_id, 'booking_time', $time );
		update_post_meta( $booking_id, 'customer_name', $name );
		update_post_meta( $booking_id, 'customer_phone', $phone );
		update_post_meta( $booking_id, '_wechat_openid', $openid );
		update_post_meta( $booking_id, '_wechat_source', 'mini_program' );

		// Get service details.
		$service = get_post( $service_id );

		// Trigger booking created action.
		do_action(
			'bkx_booking_created',
			$booking_id,
			array(
				'service_name' => $service ? $service->post_title : '',
				'date'         => $date,
				'time'         => $time,
			)
		);

		return new \WP_REST_Response(
			array(
				'success'    => true,
				'booking_id' => $booking_id,
				'message'    => __( 'Booking created successfully.', 'bkx-wechat' ),
			),
			200
		);
	}

	/**
	 * Get user bookings.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_user_bookings( $request ) {
		$token  = $request->get_header( 'X-WeChat-Token' );
		$openid = $this->get_openid_from_token( $token );

		if ( ! $openid ) {
			return new \WP_REST_Response(
				array( 'error' => 'unauthorized', 'message' => __( 'Unauthorized.', 'bkx-wechat' ) ),
				401
			);
		}

		$status = $request->get_param( 'status' ) ?: 'upcoming';

		$args = array(
			'post_type'      => 'bkx_booking',
			'posts_per_page' => 50,
			'meta_query'     => array(
				array(
					'key'   => '_wechat_openid',
					'value' => $openid,
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => 'booking_date',
			'order'          => 'ASC',
		);

		if ( 'upcoming' === $status ) {
			$args['meta_query'][] = array(
				'key'     => 'booking_date',
				'value'   => gmdate( 'Y-m-d' ),
				'compare' => '>=',
				'type'    => 'DATE',
			);
			$args['post_status'] = array( 'bkx-pending', 'bkx-ack' );
		} elseif ( 'past' === $status ) {
			$args['meta_query'][] = array(
				'key'     => 'booking_date',
				'value'   => gmdate( 'Y-m-d' ),
				'compare' => '<',
				'type'    => 'DATE',
			);
		}

		$bookings = get_posts( $args );
		$result   = array();

		foreach ( $bookings as $booking ) {
			$service_id = get_post_meta( $booking->ID, 'base_id', true );
			$service    = get_post( $service_id );

			$result[] = array(
				'id'           => $booking->ID,
				'service_name' => $service ? $service->post_title : '',
				'date'         => get_post_meta( $booking->ID, 'booking_date', true ),
				'time'         => get_post_meta( $booking->ID, 'booking_time', true ),
				'status'       => $booking->post_status,
				'status_label' => $this->get_status_label( $booking->post_status ),
			);
		}

		return new \WP_REST_Response( array( 'bookings' => $result ), 200 );
	}

	/**
	 * Get status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'bkx-pending'   => __( 'Pending', 'bkx-wechat' ),
			'bkx-ack'       => __( 'Confirmed', 'bkx-wechat' ),
			'bkx-completed' => __( 'Completed', 'bkx-wechat' ),
			'bkx-cancelled' => __( 'Cancelled', 'bkx-wechat' ),
			'bkx-missed'    => __( 'Missed', 'bkx-wechat' ),
		);

		return $labels[ $status ] ?? $status;
	}

	/**
	 * Send subscribe message (for Mini Program).
	 *
	 * @param string $openid      User OpenID.
	 * @param string $template_id Template ID.
	 * @param array  $data        Message data.
	 * @param string $page        Target page.
	 * @return bool
	 */
	public function send_subscribe_message( $openid, $template_id, $data, $page = '' ) {
		$api = $this->addon->get_service( 'wechat_api' );

		$message = array(
			'touser'      => $openid,
			'template_id' => $template_id,
			'data'        => $data,
		);

		if ( $page ) {
			$message['page'] = $page;
		}

		$result = $api->request( '/cgi-bin/message/subscribe/send', $message, 'POST', 'mini_program' );

		return false !== $result;
	}
}
