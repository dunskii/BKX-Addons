<?php
/**
 * Razorpay Payment Gateway
 *
 * Handles payment processing using Razorpay API.
 *
 * @package BookingX\Razorpay\Gateway
 * @since   1.0.0
 */

namespace BookingX\Razorpay\Gateway;

use BookingX\AddonSDK\Abstracts\AbstractPaymentGateway;
use BookingX\Razorpay\RazorpayAddon;
use BookingX\Razorpay\Api\RazorpayClient;
use BookingX\Razorpay\Services\OrderService;
use BookingX\Razorpay\Services\PaymentService;
use BookingX\Razorpay\Services\RefundService;

/**
 * Razorpay gateway class.
 *
 * @since 1.0.0
 */
class RazorpayGateway extends AbstractPaymentGateway {

	/**
	 * Addon instance.
	 *
	 * @var RazorpayAddon
	 */
	protected RazorpayAddon $addon;

	/**
	 * API client.
	 *
	 * @var RazorpayClient|null
	 */
	protected ?RazorpayClient $client = null;

	/**
	 * Order service.
	 *
	 * @var OrderService|null
	 */
	protected ?OrderService $order_service = null;

	/**
	 * Payment service.
	 *
	 * @var PaymentService|null
	 */
	protected ?PaymentService $payment_service = null;

	/**
	 * Refund service.
	 *
	 * @var RefundService|null
	 */
	protected ?RefundService $refund_service = null;

	/**
	 * Constructor.
	 *
	 * @param RazorpayAddon $addon Addon instance.
	 */
	public function __construct( RazorpayAddon $addon ) {
		$this->addon = $addon;
		$this->id = 'razorpay';
		$this->title = __( 'Razorpay (UPI, Cards, Netbanking)', 'bkx-razorpay' );
		$this->description = __( 'Pay securely with UPI, credit/debit cards, netbanking, or wallets.', 'bkx-razorpay' );
		$this->icon = BKX_RAZORPAY_URL . 'assets/images/razorpay-logo.png';
		$this->supports = array(
			'payments',
			'refunds',
			'upi',
			'cards',
			'netbanking',
			'wallets',
		);
	}

	/**
	 * Check if gateway is available.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_available(): bool {
		if ( ! $this->addon->get_setting( 'enabled', false ) ) {
			return false;
		}

		$key_id = $this->addon->get_setting( 'key_id', '' );
		$key_secret = $this->addon->get_setting( 'key_secret', '' );

		return ! empty( $key_id ) && ! empty( $key_secret );
	}

	/**
	 * Check if in test mode.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_test_mode(): bool {
		return 'test' === $this->addon->get_setting( 'razorpay_mode', 'test' );
	}

	/**
	 * Get the API client.
	 *
	 * @since 1.0.0
	 * @return RazorpayClient
	 */
	public function get_client(): RazorpayClient {
		if ( null === $this->client ) {
			$this->client = new RazorpayClient(
				$this->addon->get_setting( 'key_id', '' ),
				$this->addon->get_setting( 'key_secret', '' )
			);
		}
		return $this->client;
	}

	/**
	 * Get the order service.
	 *
	 * @since 1.0.0
	 * @return OrderService
	 */
	public function get_order_service(): OrderService {
		if ( null === $this->order_service ) {
			$this->order_service = new OrderService( $this->get_client(), $this );
		}
		return $this->order_service;
	}

	/**
	 * Get the payment service.
	 *
	 * @since 1.0.0
	 * @return PaymentService
	 */
	public function get_payment_service(): PaymentService {
		if ( null === $this->payment_service ) {
			$this->payment_service = new PaymentService( $this->get_client(), $this );
		}
		return $this->payment_service;
	}

	/**
	 * Get the refund service.
	 *
	 * @since 1.0.0
	 * @return RefundService
	 */
	public function get_refund_service(): RefundService {
		if ( null === $this->refund_service ) {
			$this->refund_service = new RefundService( $this->get_client(), $this );
		}
		return $this->refund_service;
	}

	/**
	 * Create a Razorpay order for a booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array Result with order details.
	 */
	public function create_order( int $booking_id ): array {
		$this->log( sprintf( 'Creating Razorpay order for booking #%d', $booking_id ) );

		try {
			$amount = $this->get_booking_amount( $booking_id );
			if ( $amount <= 0 ) {
				throw new \Exception( __( 'Invalid booking amount.', 'bkx-razorpay' ) );
			}

			// Create order via service.
			$result = $this->get_order_service()->create_order( $booking_id, $amount );

			if ( ! $result['success'] ) {
				throw new \Exception( $result['error'] ?? __( 'Failed to create order.', 'bkx-razorpay' ) );
			}

			// Store order ID on booking.
			update_post_meta( $booking_id, '_razorpay_order_id', $result['order_id'] );

			$this->log( sprintf( 'Order created: %s for booking #%d', $result['order_id'], $booking_id ) );

			return $result;

		} catch ( \Exception $e ) {
			$this->log( sprintf( 'Order creation failed for booking #%d: %s', $booking_id, $e->getMessage() ), 'error' );

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Process/verify a payment.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param array $payment_data Payment data from Razorpay Checkout.
	 * @return array Result with success status and message.
	 */
	public function process_payment( int $booking_id, array $payment_data = array() ): array {
		$this->log( sprintf( 'Processing payment for booking #%d', $booking_id ) );

		try {
			// Validate required payment data.
			$razorpay_payment_id = sanitize_text_field( $payment_data['razorpay_payment_id'] ?? '' );
			$razorpay_order_id = sanitize_text_field( $payment_data['razorpay_order_id'] ?? '' );
			$razorpay_signature = sanitize_text_field( $payment_data['razorpay_signature'] ?? '' );

			if ( empty( $razorpay_payment_id ) || empty( $razorpay_order_id ) || empty( $razorpay_signature ) ) {
				throw new \Exception( __( 'Invalid payment data.', 'bkx-razorpay' ) );
			}

			// Verify payment signature.
			$is_valid = $this->get_payment_service()->verify_payment_signature(
				$razorpay_order_id,
				$razorpay_payment_id,
				$razorpay_signature
			);

			if ( ! $is_valid ) {
				throw new \Exception( __( 'Payment signature verification failed.', 'bkx-razorpay' ) );
			}

			// Fetch payment details.
			$payment_details = $this->get_client()->fetch_payment( $razorpay_payment_id );

			if ( ! $payment_details['success'] ) {
				throw new \Exception( $payment_details['error'] ?? __( 'Failed to fetch payment details.', 'bkx-razorpay' ) );
			}

			// Save payment meta.
			$this->save_payment_meta( $booking_id, $razorpay_payment_id, $razorpay_order_id, $payment_details['data'] );

			// Save to database.
			$this->save_transaction_record( $booking_id, $razorpay_payment_id, $razorpay_order_id, $payment_details['data'] );

			$this->log( sprintf( 'Payment verified for booking #%d. Payment ID: %s', $booking_id, $razorpay_payment_id ) );

			// Trigger action.
			do_action( 'bkx_razorpay_payment_verified', $booking_id, $payment_details['data'] );

			return array(
				'success'    => true,
				'payment_id' => $razorpay_payment_id,
				'order_id'   => $razorpay_order_id,
				'message'    => __( 'Payment verified successfully.', 'bkx-razorpay' ),
			);

		} catch ( \Exception $e ) {
			$this->log( sprintf( 'Payment verification failed for booking #%d: %s', $booking_id, $e->getMessage() ), 'error' );

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Process a refund.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param float  $amount Amount to refund (0 for full refund).
	 * @param string $reason Refund reason.
	 * @param string $transaction_id Original transaction ID (optional).
	 * @return array Result with success status and message.
	 */
	public function process_refund( int $booking_id, float $amount, string $reason = '', string $transaction_id = '' ): array {
		$this->log( sprintf( 'Processing refund for booking #%d', $booking_id ) );

		try {
			// Use provided transaction ID or fetch from booking meta.
			$payment_id = ! empty( $transaction_id )
				? $transaction_id
				: get_post_meta( $booking_id, '_razorpay_payment_id', true );

			if ( empty( $payment_id ) ) {
				throw new \Exception( __( 'No payment found for this booking.', 'bkx-razorpay' ) );
			}

			// Get original amount if not specified (0 means full refund).
			if ( 0.0 === $amount ) {
				$amount = (float) get_post_meta( $booking_id, '_payment_amount', true );
			}

			// Process refund through service.
			$result = $this->get_refund_service()->create_refund( $payment_id, $amount, $reason );

			if ( ! $result['success'] ) {
				throw new \Exception( $result['error'] ?? __( 'Refund failed.', 'bkx-razorpay' ) );
			}

			// Update booking meta.
			update_post_meta( $booking_id, '_razorpay_refund_id', $result['refund_id'] );
			update_post_meta( $booking_id, '_razorpay_refund_status', 'processed' );
			update_post_meta( $booking_id, '_razorpay_refund_amount', $amount );
			update_post_meta( $booking_id, '_payment_refunded', true );

			// Save refund record.
			$this->save_refund_record( $booking_id, $payment_id, $result, $reason );

			$this->log( sprintf( 'Refund processed for booking #%d. Refund ID: %s', $booking_id, $result['refund_id'] ) );

			// Trigger action.
			do_action( 'bkx_razorpay_refund_processed', $booking_id, $result );

			return array(
				'success'   => true,
				'refund_id' => $result['refund_id'],
				'message'   => __( 'Refund processed successfully.', 'bkx-razorpay' ),
			);

		} catch ( \Exception $e ) {
			$this->log( sprintf( 'Refund failed for booking #%d: %s', $booking_id, $e->getMessage() ), 'error' );

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Handle webhook events.
	 *
	 * @since 1.0.0
	 * @param array $event Webhook event data.
	 * @return array Result.
	 */
	public function handle_webhook( array $event ): array {
		$event_type = $event['event'] ?? '';
		$payload = $event['payload'] ?? array();

		$this->log( sprintf( 'Handling webhook event: %s', $event_type ) );

		switch ( $event_type ) {
			case 'payment.authorized':
				return $this->handle_payment_authorized( $payload );

			case 'payment.captured':
				return $this->handle_payment_captured( $payload );

			case 'payment.failed':
				return $this->handle_payment_failed( $payload );

			case 'refund.created':
				return $this->handle_refund_created( $payload );

			case 'order.paid':
				return $this->handle_order_paid( $payload );

			default:
				return array(
					'success' => true,
					'message' => sprintf( __( 'Event type %s not handled.', 'bkx-razorpay' ), $event_type ),
				);
		}
	}

	/**
	 * Handle payment.authorized webhook.
	 *
	 * @since 1.0.0
	 * @param array $payload Event payload.
	 * @return array Result.
	 */
	protected function handle_payment_authorized( array $payload ): array {
		$payment = $payload['payment']['entity'] ?? array();
		$payment_id = $payment['id'] ?? '';
		$order_id = $payment['order_id'] ?? '';

		$booking_id = $this->get_booking_id_from_order( $order_id );
		if ( ! $booking_id ) {
			return array(
				'success' => true,
				'message' => __( 'Booking not found for this order.', 'bkx-razorpay' ),
			);
		}

		update_post_meta( $booking_id, '_razorpay_payment_status', 'authorized' );

		// Auto-capture if configured.
		if ( 'capture' === $this->addon->get_setting( 'payment_action', 'capture' ) ) {
			$amount = $payment['amount'] ?? 0;
			$this->get_client()->capture_payment( $payment_id, $amount );
		}

		return array(
			'success' => true,
			'message' => sprintf( __( 'Payment authorized for booking #%d.', 'bkx-razorpay' ), $booking_id ),
		);
	}

	/**
	 * Handle payment.captured webhook.
	 *
	 * @since 1.0.0
	 * @param array $payload Event payload.
	 * @return array Result.
	 */
	protected function handle_payment_captured( array $payload ): array {
		$payment = $payload['payment']['entity'] ?? array();
		$order_id = $payment['order_id'] ?? '';

		$booking_id = $this->get_booking_id_from_order( $order_id );
		if ( ! $booking_id ) {
			return array(
				'success' => true,
				'message' => __( 'Booking not found for this order.', 'bkx-razorpay' ),
			);
		}

		update_post_meta( $booking_id, '_razorpay_payment_status', 'captured' );
		update_post_meta( $booking_id, '_payment_complete', true );

		do_action( 'bkx_booking_payment_complete', $booking_id, $payload );

		return array(
			'success' => true,
			'message' => sprintf( __( 'Payment captured for booking #%d.', 'bkx-razorpay' ), $booking_id ),
		);
	}

	/**
	 * Handle payment.failed webhook.
	 *
	 * @since 1.0.0
	 * @param array $payload Event payload.
	 * @return array Result.
	 */
	protected function handle_payment_failed( array $payload ): array {
		$payment = $payload['payment']['entity'] ?? array();
		$order_id = $payment['order_id'] ?? '';

		$booking_id = $this->get_booking_id_from_order( $order_id );
		if ( ! $booking_id ) {
			return array(
				'success' => true,
				'message' => __( 'Booking not found for this order.', 'bkx-razorpay' ),
			);
		}

		update_post_meta( $booking_id, '_razorpay_payment_status', 'failed' );
		update_post_meta( $booking_id, '_payment_failed', true );

		do_action( 'bkx_booking_payment_failed', $booking_id, $payload );

		return array(
			'success' => true,
			'message' => sprintf( __( 'Payment failed for booking #%d.', 'bkx-razorpay' ), $booking_id ),
		);
	}

	/**
	 * Handle refund.created webhook.
	 *
	 * @since 1.0.0
	 * @param array $payload Event payload.
	 * @return array Result.
	 */
	protected function handle_refund_created( array $payload ): array {
		$refund = $payload['refund']['entity'] ?? array();
		$payment_id = $refund['payment_id'] ?? '';

		$booking_id = $this->get_booking_id_from_payment( $payment_id );
		if ( ! $booking_id ) {
			return array(
				'success' => true,
				'message' => __( 'Booking not found for this payment.', 'bkx-razorpay' ),
			);
		}

		update_post_meta( $booking_id, '_razorpay_refund_status', 'completed' );

		do_action( 'bkx_booking_refunded', $booking_id, $payload );

		return array(
			'success' => true,
			'message' => sprintf( __( 'Refund created for booking #%d.', 'bkx-razorpay' ), $booking_id ),
		);
	}

	/**
	 * Handle order.paid webhook.
	 *
	 * @since 1.0.0
	 * @param array $payload Event payload.
	 * @return array Result.
	 */
	protected function handle_order_paid( array $payload ): array {
		$order = $payload['order']['entity'] ?? array();
		$order_id = $order['id'] ?? '';

		$booking_id = $this->get_booking_id_from_order( $order_id );
		if ( ! $booking_id ) {
			return array(
				'success' => true,
				'message' => __( 'Booking not found for this order.', 'bkx-razorpay' ),
			);
		}

		update_post_meta( $booking_id, '_razorpay_order_status', 'paid' );

		return array(
			'success' => true,
			'message' => sprintf( __( 'Order paid for booking #%d.', 'bkx-razorpay' ), $booking_id ),
		);
	}

	/**
	 * Get settings fields.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_settings_fields(): array {
		return $this->addon->get_settings_fields();
	}

	/**
	 * Get booking amount.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return float
	 */
	public function get_booking_amount( int $booking_id ): float {
		$amount = get_post_meta( $booking_id, 'total_amount', true );
		return (float) $amount;
	}

	/**
	 * Get currency code.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_currency(): string {
		return $this->addon->get_setting( 'currency', 'INR' );
	}

	/**
	 * Get order prefix.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_order_prefix(): string {
		return $this->addon->get_setting( 'order_prefix', 'BKX-' );
	}

	/**
	 * Save payment meta data.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $payment_id Razorpay payment ID.
	 * @param string $order_id Razorpay order ID.
	 * @param array  $payment_data Payment details.
	 * @return void
	 */
	protected function save_payment_meta( int $booking_id, string $payment_id, string $order_id, array $payment_data ): void {
		update_post_meta( $booking_id, '_payment_method', 'razorpay' );
		update_post_meta( $booking_id, '_razorpay_payment_id', $payment_id );
		update_post_meta( $booking_id, '_razorpay_order_id', $order_id );
		update_post_meta( $booking_id, '_razorpay_payment_status', $payment_data['status'] ?? 'captured' );
		update_post_meta( $booking_id, '_payment_amount', ( $payment_data['amount'] ?? 0 ) / 100 );
		update_post_meta( $booking_id, '_payment_complete', true );

		if ( ! empty( $payment_data['method'] ) ) {
			update_post_meta( $booking_id, '_razorpay_payment_method', $payment_data['method'] );
		}
		if ( ! empty( $payment_data['email'] ) ) {
			update_post_meta( $booking_id, '_razorpay_customer_email', $payment_data['email'] );
		}
		if ( ! empty( $payment_data['contact'] ) ) {
			update_post_meta( $booking_id, '_razorpay_customer_contact', $payment_data['contact'] );
		}
	}

	/**
	 * Save transaction to database table.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $payment_id Razorpay payment ID.
	 * @param string $order_id Razorpay order ID.
	 * @param array  $payment_data Payment details.
	 * @return void
	 */
	protected function save_transaction_record( int $booking_id, string $payment_id, string $order_id, array $payment_data ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_razorpay_transactions';

		$data = array(
			'booking_id'      => $booking_id,
			'razorpay_order_id' => $order_id,
			'razorpay_payment_id' => $payment_id,
			'amount'          => ( $payment_data['amount'] ?? 0 ) / 100,
			'currency'        => $payment_data['currency'] ?? 'INR',
			'status'          => $payment_data['status'] ?? 'captured',
			'payment_method'  => $payment_data['method'] ?? '',
			'customer_email'  => $payment_data['email'] ?? '',
			'customer_contact' => $payment_data['contact'] ?? '',
			'metadata'        => wp_json_encode( $payment_data ),
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data );
	}

	/**
	 * Save refund to database table.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $payment_id Original payment ID.
	 * @param array  $result Refund result.
	 * @param string $reason Refund reason.
	 * @return void
	 */
	protected function save_refund_record( int $booking_id, string $payment_id, array $result, string $reason ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_razorpay_refunds';

		$data = array(
			'booking_id'          => $booking_id,
			'razorpay_payment_id' => $payment_id,
			'razorpay_refund_id'  => $result['refund_id'],
			'amount'              => $result['amount'] ?? 0,
			'status'              => 'processed',
			'reason'              => $reason,
			'created_at'          => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data );
	}

	/**
	 * Get booking ID from Razorpay order ID.
	 *
	 * @since 1.0.0
	 * @param string $order_id Razorpay order ID.
	 * @return int|false Booking ID or false.
	 */
	protected function get_booking_id_from_order( string $order_id ) {
		// Check cache first.
		$cache_key = 'bkx_rzp_order_' . md5( $order_id );
		$booking_id = wp_cache_get( $cache_key, 'bkx_razorpay' );

		if ( false !== $booking_id ) {
			return $booking_id;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_razorpay_transactions';

		// Use %i placeholder for table identifier - SECURITY.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$booking_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT booking_id FROM %i WHERE razorpay_order_id = %s LIMIT 1',
				$table,
				$order_id
			)
		);

		// Cache for 1 hour.
		wp_cache_set( $cache_key, $booking_id, 'bkx_razorpay', HOUR_IN_SECONDS );

		return $booking_id;
	}

	/**
	 * Get booking ID from Razorpay payment ID.
	 *
	 * @since 1.0.0
	 * @param string $payment_id Razorpay payment ID.
	 * @return int|false Booking ID or false.
	 */
	protected function get_booking_id_from_payment( string $payment_id ) {
		// Check cache first.
		$cache_key = 'bkx_rzp_payment_' . md5( $payment_id );
		$booking_id = wp_cache_get( $cache_key, 'bkx_razorpay' );

		if ( false !== $booking_id ) {
			return $booking_id;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_razorpay_transactions';

		// Use %i placeholder for table identifier - SECURITY.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$booking_id = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT booking_id FROM %i WHERE razorpay_payment_id = %s LIMIT 1',
				$table,
				$payment_id
			)
		);

		// Cache for 1 hour.
		wp_cache_set( $cache_key, $booking_id, 'bkx_razorpay', HOUR_IN_SECONDS );

		return $booking_id;
	}

	/**
	 * Log a message.
	 *
	 * @since 1.0.0
	 * @param string $message Message to log.
	 * @param string $level Log level (info, error, debug).
	 * @return void
	 */
	public function log( string $message, string $level = 'info' ): void {
		if ( ! $this->addon->get_setting( 'debug_log', false ) && 'error' !== $level ) {
			return;
		}

		$log_file = WP_CONTENT_DIR . '/bkx-razorpay-debug.log';
		$timestamp = current_time( 'c' );
		$formatted = sprintf( "[%s] [%s] %s\n", $timestamp, strtoupper( $level ), $message );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $log_file, $formatted, FILE_APPEND | LOCK_EX );
	}
}
