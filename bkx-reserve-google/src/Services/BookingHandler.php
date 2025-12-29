<?php
/**
 * Booking Handler for Reserve with Google.
 *
 * Handles real-time booking API requests from Google.
 *
 * @package BookingX\ReserveGoogle
 */

namespace BookingX\ReserveGoogle\Services;

defined( 'ABSPATH' ) || exit;

/**
 * BookingHandler class.
 */
class BookingHandler {

	/**
	 * Merchant manager.
	 *
	 * @var MerchantManager
	 */
	private $merchant_manager;

	/**
	 * Constructor.
	 *
	 * @param MerchantManager $merchant_manager Merchant manager.
	 */
	public function __construct( MerchantManager $merchant_manager ) {
		$this->merchant_manager = $merchant_manager;
	}

	/**
	 * Check availability.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function check_availability( $request ) {
		$body = $request->get_json_params();

		$slot         = $body['slot'] ?? array();
		$merchant_id  = $slot['merchant_id'] ?? '';
		$service_id   = $slot['service_id'] ?? '';
		$start_time   = $slot['start_time'] ?? '';

		if ( empty( $merchant_id ) || empty( $service_id ) || empty( $start_time ) ) {
			return $this->error_response( 'INVALID_REQUEST', 'Missing required parameters' );
		}

		$service = $this->merchant_manager->get_service( $service_id, 'rwg' );

		if ( ! $service ) {
			return $this->error_response( 'SERVICE_NOT_FOUND', 'Service not found' );
		}

		$availability_sync = new AvailabilitySync();
		$date              = gmdate( 'Y-m-d', strtotime( $start_time ) );
		$time              = gmdate( 'H:i:s', strtotime( $start_time ) );

		$is_available = $availability_sync->is_slot_available( $service->id, $date, $time );

		$response = array(
			'slot'             => $slot,
			'count_available'  => $is_available ? 1 : 0,
			'duration_sec'     => $service->duration_minutes * 60,
			'availability_tag' => $this->generate_availability_tag( $slot ),
		);

		$this->log_request( 'CheckAvailability', 'POST', $body, $response );

		return rest_ensure_response( $response );
	}

	/**
	 * Create booking.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function create_booking( $request ) {
		$body = $request->get_json_params();

		$slot              = $body['slot'] ?? array();
		$user_information  = $body['user_information'] ?? array();
		$idempotency_token = $body['idempotency_token'] ?? '';

		// Check for duplicate request.
		if ( ! empty( $idempotency_token ) ) {
			$existing = $this->get_booking_by_idempotency( $idempotency_token );
			if ( $existing ) {
				return rest_ensure_response( array(
					'booking' => $this->format_booking_response( $existing ),
				) );
			}
		}

		// Validate slot.
		$service_id = $slot['service_id'] ?? '';
		$start_time = $slot['start_time'] ?? '';

		$service = $this->merchant_manager->get_service( $service_id, 'rwg' );

		if ( ! $service ) {
			return $this->error_response( 'SERVICE_NOT_FOUND', 'Service not found' );
		}

		// Check availability.
		$availability_sync = new AvailabilitySync();
		$date              = gmdate( 'Y-m-d', strtotime( $start_time ) );
		$time              = gmdate( 'H:i:s', strtotime( $start_time ) );

		if ( ! $availability_sync->is_slot_available( $service->id, $date, $time ) ) {
			return $this->error_response( 'SLOT_UNAVAILABLE', 'The requested time slot is no longer available' );
		}

		// Create booking in BookingX.
		$booking_id = $this->create_bookingx_booking( $service, $slot, $user_information );

		if ( is_wp_error( $booking_id ) ) {
			return $this->error_response( 'BOOKING_FAILED', $booking_id->get_error_message() );
		}

		// Create RWG booking record.
		$rwg_booking_id = $this->create_rwg_booking( $booking_id, $slot, $user_information, $idempotency_token, $body );

		$booking = $this->get_rwg_booking( $rwg_booking_id );

		$response = array(
			'booking' => $this->format_booking_response( $booking ),
		);

		$this->log_request( 'CreateBooking', 'POST', $body, $response );

		return rest_ensure_response( $response );
	}

	/**
	 * Update booking.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function update_booking( $request ) {
		$body = $request->get_json_params();

		$booking_id = $body['booking']['booking_id'] ?? '';
		$status     = $body['booking']['status'] ?? '';

		$booking = $this->get_rwg_booking_by_rwg_id( $booking_id );

		if ( ! $booking ) {
			return $this->error_response( 'BOOKING_NOT_FOUND', 'Booking not found' );
		}

		// Update BookingX booking.
		if ( $status === 'CANCELED' || $status === 'CANCELLED' ) {
			wp_update_post( array(
				'ID'          => $booking->bkx_booking_id,
				'post_status' => 'bkx-cancelled',
			) );

			do_action( 'bkx_booking_cancelled', $booking->bkx_booking_id );
		}

		// Update RWG booking record.
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'bkx_rwg_bookings',
			array(
				'status'     => strtolower( $status ),
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $booking->id )
		);

		$updated_booking = $this->get_rwg_booking( $booking->id );

		$response = array(
			'booking' => $this->format_booking_response( $updated_booking ),
		);

		$this->log_request( 'UpdateBooking', 'POST', $body, $response );

		return rest_ensure_response( $response );
	}

	/**
	 * Get booking status.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_booking_status( $request ) {
		$body = $request->get_json_params();

		$booking_id = $body['booking_id'] ?? '';

		$booking = $this->get_rwg_booking_by_rwg_id( $booking_id );

		if ( ! $booking ) {
			return $this->error_response( 'BOOKING_NOT_FOUND', 'Booking not found' );
		}

		$response = array(
			'booking' => $this->format_booking_response( $booking ),
		);

		$this->log_request( 'GetBookingStatus', 'POST', $body, $response );

		return rest_ensure_response( $response );
	}

	/**
	 * List bookings.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_bookings( $request ) {
		$body = $request->get_json_params();

		$user_id = $body['user_id'] ?? '';

		global $wpdb;

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_rwg_bookings
				WHERE customer_email = %s
				ORDER BY booking_date DESC
				LIMIT 50",
				$user_id
			)
		);

		$formatted_bookings = array();
		foreach ( $bookings as $booking ) {
			$formatted_bookings[] = $this->format_booking_response( $booking );
		}

		$response = array(
			'bookings' => $formatted_bookings,
		);

		$this->log_request( 'ListBookings', 'POST', $body, $response );

		return rest_ensure_response( $response );
	}

	/**
	 * Create BookingX booking.
	 *
	 * @param object $service          Service data.
	 * @param array  $slot             Slot data.
	 * @param array  $user_information User information.
	 * @return int|\WP_Error Booking ID or error.
	 */
	private function create_bookingx_booking( $service, $slot, $user_information ) {
		$bkx_service = get_post( $service->bkx_service_id );

		if ( ! $bkx_service ) {
			return new \WP_Error( 'invalid_service', 'Service not found in BookingX' );
		}

		$date = gmdate( 'Y-m-d', strtotime( $slot['start_time'] ) );
		$time = gmdate( 'H:i:s', strtotime( $slot['start_time'] ) );

		$booking_args = array(
			'post_type'   => 'bkx_booking',
			'post_status' => 'bkx-ack',
			'post_title'  => sprintf(
				'Reserve with Google - %s - %s',
				$bkx_service->post_title,
				$date
			),
		);

		$booking_id = wp_insert_post( $booking_args );

		if ( is_wp_error( $booking_id ) ) {
			return $booking_id;
		}

		// Add booking meta.
		update_post_meta( $booking_id, 'booking_date', $date );
		update_post_meta( $booking_id, 'booking_time', $time );
		update_post_meta( $booking_id, 'service_id', $service->bkx_service_id );
		update_post_meta( $booking_id, 'service_name', $bkx_service->post_title );
		update_post_meta( $booking_id, 'booking_source', 'reserve_with_google' );

		// Add customer info.
		if ( ! empty( $user_information['given_name'] ) ) {
			$name = trim( $user_information['given_name'] . ' ' . ( $user_information['family_name'] ?? '' ) );
			update_post_meta( $booking_id, 'customer_name', $name );
		}

		if ( ! empty( $user_information['email'] ) ) {
			update_post_meta( $booking_id, 'customer_email', $user_information['email'] );
		}

		if ( ! empty( $user_information['telephone'] ) ) {
			update_post_meta( $booking_id, 'customer_phone', $user_information['telephone'] );
		}

		do_action( 'bkx_booking_created', $booking_id, array() );

		return $booking_id;
	}

	/**
	 * Create RWG booking record.
	 *
	 * @param int    $bkx_booking_id    BookingX booking ID.
	 * @param array  $slot              Slot data.
	 * @param array  $user_information  User information.
	 * @param string $idempotency_token Idempotency token.
	 * @param array  $raw_request       Raw request data.
	 * @return int RWG booking ID.
	 */
	private function create_rwg_booking( $bkx_booking_id, $slot, $user_information, $idempotency_token, $raw_request ) {
		global $wpdb;

		$rwg_booking_id = 'rwg_' . wp_generate_uuid4();
		$date           = gmdate( 'Y-m-d', strtotime( $slot['start_time'] ) );
		$start_time     = gmdate( 'H:i:s', strtotime( $slot['start_time'] ) );
		$end_time       = gmdate( 'H:i:s', strtotime( $slot['end_time'] ?? $slot['start_time'] ) + 3600 );

		$data = array(
			'rwg_booking_id'    => $rwg_booking_id,
			'bkx_booking_id'    => $bkx_booking_id,
			'merchant_id'       => $slot['merchant_id'],
			'service_id'        => $slot['service_id'],
			'customer_email'    => $user_information['email'] ?? null,
			'customer_name'     => trim( ( $user_information['given_name'] ?? '' ) . ' ' . ( $user_information['family_name'] ?? '' ) ),
			'customer_phone'    => $user_information['telephone'] ?? null,
			'booking_date'      => $date,
			'start_time'        => $start_time,
			'end_time'          => $end_time,
			'party_size'        => $slot['party_size'] ?? 1,
			'status'            => 'confirmed',
			'idempotency_token' => $idempotency_token,
			'source'            => 'google_maps',
			'raw_request'       => wp_json_encode( $raw_request ),
			'created_at'        => current_time( 'mysql', true ),
			'updated_at'        => current_time( 'mysql', true ),
		);

		$wpdb->insert( $wpdb->prefix . 'bkx_rwg_bookings', $data );

		return $wpdb->insert_id;
	}

	/**
	 * Get RWG booking by ID.
	 *
	 * @param int $id Booking ID.
	 * @return object|null
	 */
	private function get_rwg_booking( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_rwg_bookings WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Get RWG booking by RWG booking ID.
	 *
	 * @param string $rwg_booking_id RWG booking ID.
	 * @return object|null
	 */
	private function get_rwg_booking_by_rwg_id( $rwg_booking_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_rwg_bookings WHERE rwg_booking_id = %s",
				$rwg_booking_id
			)
		);
	}

	/**
	 * Get booking by idempotency token.
	 *
	 * @param string $token Idempotency token.
	 * @return object|null
	 */
	private function get_booking_by_idempotency( $token ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_rwg_bookings WHERE idempotency_token = %s",
				$token
			)
		);
	}

	/**
	 * Format booking response.
	 *
	 * @param object $booking Booking data.
	 * @return array
	 */
	private function format_booking_response( $booking ) {
		return array(
			'booking_id'       => $booking->rwg_booking_id,
			'slot'             => array(
				'merchant_id' => $booking->merchant_id,
				'service_id'  => $booking->service_id,
				'start_time'  => gmdate( 'c', strtotime( $booking->booking_date . ' ' . $booking->start_time ) ),
				'end_time'    => gmdate( 'c', strtotime( $booking->booking_date . ' ' . $booking->end_time ) ),
			),
			'user_information' => array(
				'email'       => $booking->customer_email,
				'given_name'  => explode( ' ', $booking->customer_name )[0] ?? '',
				'family_name' => explode( ' ', $booking->customer_name )[1] ?? '',
				'telephone'   => $booking->customer_phone,
			),
			'status'           => strtoupper( $booking->status ),
			'payment_information' => $booking->payment_status ? array(
				'prepayment_status' => strtoupper( $booking->payment_status ),
			) : null,
		);
	}

	/**
	 * Sync booking status from BookingX.
	 *
	 * @param int $bkx_booking_id BookingX booking ID.
	 */
	public function sync_booking_status( $bkx_booking_id ) {
		global $wpdb;

		$booking = get_post( $bkx_booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return;
		}

		// Map BookingX status to RWG status.
		$status_map = array(
			'bkx-pending'   => 'pending',
			'bkx-ack'       => 'confirmed',
			'bkx-completed' => 'completed',
			'bkx-cancelled' => 'cancelled',
			'bkx-missed'    => 'no_show',
		);

		$rwg_status = $status_map[ $booking->post_status ] ?? 'confirmed';

		$wpdb->update(
			$wpdb->prefix . 'bkx_rwg_bookings',
			array(
				'status'     => $rwg_status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'bkx_booking_id' => $bkx_booking_id )
		);
	}

	/**
	 * Generate availability tag.
	 *
	 * @param array $slot Slot data.
	 * @return string
	 */
	private function generate_availability_tag( $slot ) {
		return md5( wp_json_encode( $slot ) . time() );
	}

	/**
	 * Generate error response.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @return \WP_REST_Response
	 */
	private function error_response( $code, $message ) {
		return rest_ensure_response( array(
			'error' => array(
				'code'    => $code,
				'message' => $message,
			),
		) );
	}

	/**
	 * Log API request.
	 *
	 * @param string $endpoint Endpoint.
	 * @param string $method   HTTP method.
	 * @param mixed  $request  Request data.
	 * @param mixed  $response Response data.
	 */
	private function log_request( $endpoint, $method, $request, $response ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bkx_rwg_logs',
			array(
				'endpoint'         => $endpoint,
				'method'           => $method,
				'request_payload'  => wp_json_encode( $request ),
				'response_payload' => wp_json_encode( $response ),
				'response_code'    => isset( $response['error'] ) ? 400 : 200,
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}
}
