<?php
/**
 * Authorize.net Payment Gateway
 *
 * Handles payment processing using Authorize.net API.
 *
 * @package BookingX\AuthorizeNet\Gateway
 * @since   1.0.0
 */

namespace BookingX\AuthorizeNet\Gateway;

use BookingX\AddonSDK\Abstracts\AbstractPaymentGateway;
use BookingX\AuthorizeNet\AuthorizeNet;
use BookingX\AuthorizeNet\Api\AuthorizeNetClient;
use BookingX\AuthorizeNet\Services\PaymentService;
use BookingX\AuthorizeNet\Services\RefundService;

/**
 * Authorize.net gateway class.
 *
 * @since 1.0.0
 */
class AuthorizeNetGateway extends AbstractPaymentGateway {

	/**
	 * Addon instance.
	 *
	 * @var AuthorizeNet
	 */
	protected AuthorizeNet $addon;

	/**
	 * API client.
	 *
	 * @var AuthorizeNetClient|null
	 */
	protected ?AuthorizeNetClient $client = null;

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
	 * @param AuthorizeNet $addon Addon instance.
	 */
	public function __construct( AuthorizeNet $addon ) {
		$this->addon = $addon;
		$this->id = 'authorize_net';
		$this->title = __( 'Credit Card (Authorize.net)', 'bkx-authorize-net' );
		$this->description = __( 'Pay securely with your credit card via Authorize.net.', 'bkx-authorize-net' );
		$this->icon = BKX_AUTHORIZE_NET_URL . 'assets/images/authorize-net-logo.png';
		$this->supports = array(
			'payments',
			'refunds',
			'tokenization',
			'subscriptions',
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

		$api_login_id = $this->addon->get_setting( 'api_login_id', '' );
		$transaction_key = $this->addon->get_setting( 'transaction_key', '' );
		$client_key = $this->addon->get_setting( 'public_client_key', '' );

		return ! empty( $api_login_id ) && ! empty( $transaction_key ) && ! empty( $client_key );
	}

	/**
	 * Check if in test mode.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_test_mode(): bool {
		return 'sandbox' === $this->addon->get_setting( 'authnet_mode', 'sandbox' );
	}

	/**
	 * Get the API client.
	 *
	 * @since 1.0.0
	 * @return AuthorizeNetClient
	 */
	public function get_client(): AuthorizeNetClient {
		if ( null === $this->client ) {
			$this->client = new AuthorizeNetClient(
				$this->addon->get_setting( 'api_login_id', '' ),
				$this->addon->get_setting( 'transaction_key', '' ),
				$this->is_test_mode()
			);
		}
		return $this->client;
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
	 * Process a payment.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param array $payment_data Payment data including opaque data from Accept.js.
	 * @return array Result with success status and message.
	 */
	public function process_payment( int $booking_id, array $payment_data = array() ): array {
		$this->log( sprintf( 'Processing payment for booking #%d', $booking_id ) );

		try {
			// Validate and sanitize opaque data from Accept.js.
			$opaque_descriptor = sanitize_text_field( $payment_data['opaque_data_descriptor'] ?? '' );
			$opaque_value = sanitize_text_field( $payment_data['opaque_data_value'] ?? '' );

			if ( empty( $opaque_descriptor ) || empty( $opaque_value ) ) {
				throw new \Exception( __( 'Invalid payment token. Please try again.', 'bkx-authorize-net' ) );
			}

			// Get booking amount.
			$amount = $this->get_booking_amount( $booking_id );
			if ( $amount <= 0 ) {
				throw new \Exception( __( 'Invalid booking amount.', 'bkx-authorize-net' ) );
			}

			// Get transaction type.
			$transaction_type = $this->addon->get_setting( 'transaction_type', 'auth_capture' );

			// Process payment through service.
			$result = $this->get_payment_service()->create_transaction(
				$booking_id,
				$amount,
				$opaque_descriptor,
				$opaque_value,
				$transaction_type
			);

			if ( ! $result['success'] ) {
				throw new \Exception( $result['error'] ?? __( 'Payment failed.', 'bkx-authorize-net' ) );
			}

			// Store transaction data.
			$this->save_transaction_meta( $booking_id, $result );

			$this->log( sprintf( 'Payment successful for booking #%d. Transaction ID: %s', $booking_id, $result['transaction_id'] ) );

			return array(
				'success'        => true,
				'transaction_id' => $result['transaction_id'],
				'message'        => __( 'Payment processed successfully.', 'bkx-authorize-net' ),
			);

		} catch ( \Exception $e ) {
			$this->log( sprintf( 'Payment failed for booking #%d: %s', $booking_id, $e->getMessage() ), 'error' );

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
	 * @param int        $booking_id Booking ID.
	 * @param float|null $amount Amount to refund (null for full refund).
	 * @param string     $reason Refund reason.
	 * @return array Result with success status and message.
	 */
	public function process_refund( int $booking_id, ?float $amount = null, string $reason = '' ): array {
		$this->log( sprintf( 'Processing refund for booking #%d', $booking_id ) );

		try {
			$transaction_id = get_post_meta( $booking_id, '_authnet_transaction_id', true );
			if ( empty( $transaction_id ) ) {
				throw new \Exception( __( 'No transaction found for this booking.', 'bkx-authorize-net' ) );
			}

			// Get original amount if not specified.
			if ( null === $amount ) {
				$amount = (float) get_post_meta( $booking_id, '_payment_amount', true );
			}

			// Get card details for refund.
			$last_four = get_post_meta( $booking_id, '_authnet_last_four', true );
			$expiration = get_post_meta( $booking_id, '_authnet_card_expiration', true );

			// Process refund through service.
			$result = $this->get_refund_service()->process_refund(
				$transaction_id,
				$amount,
				$last_four,
				$expiration,
				$reason
			);

			if ( ! $result['success'] ) {
				throw new \Exception( $result['error'] ?? __( 'Refund failed.', 'bkx-authorize-net' ) );
			}

			// Update booking meta.
			update_post_meta( $booking_id, '_authnet_refund_id', $result['refund_id'] );
			update_post_meta( $booking_id, '_authnet_refund_status', 'REFUNDED' );
			update_post_meta( $booking_id, '_authnet_refund_amount', $amount );
			update_post_meta( $booking_id, '_payment_refunded', true );

			// Store refund in database.
			$this->save_refund_record( $booking_id, $transaction_id, $result, $reason );

			$this->log( sprintf( 'Refund successful for booking #%d. Refund ID: %s', $booking_id, $result['refund_id'] ) );

			// Trigger action.
			do_action( 'bkx_authorize_net_refund_processed', $booking_id, $result );

			return array(
				'success'   => true,
				'refund_id' => $result['refund_id'],
				'message'   => __( 'Refund processed successfully.', 'bkx-authorize-net' ),
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
		$event_type = $event['eventType'] ?? '';
		$payload = $event['payload'] ?? array();

		$this->log( sprintf( 'Handling webhook event: %s', $event_type ) );

		switch ( $event_type ) {
			case 'net.authorize.payment.authcapture.created':
			case 'net.authorize.payment.capture.created':
				return $this->handle_payment_completed( $payload );

			case 'net.authorize.payment.refund.created':
				return $this->handle_refund_created( $payload );

			case 'net.authorize.payment.void.created':
				return $this->handle_void_created( $payload );

			case 'net.authorize.payment.fraud.held':
				return $this->handle_fraud_held( $payload );

			case 'net.authorize.payment.fraud.declined':
				return $this->handle_fraud_declined( $payload );

			default:
				return array(
					'success' => true,
					'message' => sprintf( __( 'Event type %s not handled.', 'bkx-authorize-net' ), $event_type ),
				);
		}
	}

	/**
	 * Handle payment completed webhook.
	 *
	 * @since 1.0.0
	 * @param array $payload Event payload.
	 * @return array Result.
	 */
	protected function handle_payment_completed( array $payload ): array {
		$transaction_id = $payload['id'] ?? '';
		$booking_id = $this->get_booking_id_from_transaction( $transaction_id );

		if ( ! $booking_id ) {
			return array(
				'success' => true,
				'message' => __( 'Booking not found for this transaction.', 'bkx-authorize-net' ),
			);
		}

		update_post_meta( $booking_id, '_authnet_transaction_status', 'CAPTURED' );
		update_post_meta( $booking_id, '_payment_complete', true );

		do_action( 'bkx_booking_payment_complete', $booking_id, $payload );

		return array(
			'success' => true,
			'message' => sprintf( __( 'Payment completed for booking #%d.', 'bkx-authorize-net' ), $booking_id ),
		);
	}

	/**
	 * Handle refund created webhook.
	 *
	 * @since 1.0.0
	 * @param array $payload Event payload.
	 * @return array Result.
	 */
	protected function handle_refund_created( array $payload ): array {
		$transaction_id = $payload['id'] ?? '';
		$booking_id = $this->get_booking_id_from_transaction( $transaction_id );

		if ( ! $booking_id ) {
			return array(
				'success' => true,
				'message' => __( 'Booking not found for this transaction.', 'bkx-authorize-net' ),
			);
		}

		update_post_meta( $booking_id, '_authnet_refund_status', 'COMPLETED' );

		do_action( 'bkx_booking_refunded', $booking_id, $payload );

		return array(
			'success' => true,
			'message' => sprintf( __( 'Refund processed for booking #%d.', 'bkx-authorize-net' ), $booking_id ),
		);
	}

	/**
	 * Handle void created webhook.
	 *
	 * @since 1.0.0
	 * @param array $payload Event payload.
	 * @return array Result.
	 */
	protected function handle_void_created( array $payload ): array {
		$transaction_id = $payload['id'] ?? '';
		$booking_id = $this->get_booking_id_from_transaction( $transaction_id );

		if ( ! $booking_id ) {
			return array(
				'success' => true,
				'message' => __( 'Booking not found for this transaction.', 'bkx-authorize-net' ),
			);
		}

		update_post_meta( $booking_id, '_authnet_transaction_status', 'VOIDED' );
		update_post_meta( $booking_id, '_payment_voided', true );

		do_action( 'bkx_booking_voided', $booking_id, $payload );

		return array(
			'success' => true,
			'message' => sprintf( __( 'Transaction voided for booking #%d.', 'bkx-authorize-net' ), $booking_id ),
		);
	}

	/**
	 * Handle fraud held webhook.
	 *
	 * @since 1.0.0
	 * @param array $payload Event payload.
	 * @return array Result.
	 */
	protected function handle_fraud_held( array $payload ): array {
		$transaction_id = $payload['id'] ?? '';
		$booking_id = $this->get_booking_id_from_transaction( $transaction_id );

		if ( ! $booking_id ) {
			return array(
				'success' => true,
				'message' => __( 'Booking not found for this transaction.', 'bkx-authorize-net' ),
			);
		}

		update_post_meta( $booking_id, '_authnet_transaction_status', 'FRAUD_HELD' );
		update_post_meta( $booking_id, '_authnet_fraud_held', true );

		do_action( 'bkx_booking_fraud_held', $booking_id, $payload );

		return array(
			'success' => true,
			'message' => sprintf( __( 'Transaction held for review for booking #%d.', 'bkx-authorize-net' ), $booking_id ),
		);
	}

	/**
	 * Handle fraud declined webhook.
	 *
	 * @since 1.0.0
	 * @param array $payload Event payload.
	 * @return array Result.
	 */
	protected function handle_fraud_declined( array $payload ): array {
		$transaction_id = $payload['id'] ?? '';
		$booking_id = $this->get_booking_id_from_transaction( $transaction_id );

		if ( ! $booking_id ) {
			return array(
				'success' => true,
				'message' => __( 'Booking not found for this transaction.', 'bkx-authorize-net' ),
			);
		}

		update_post_meta( $booking_id, '_authnet_transaction_status', 'FRAUD_DECLINED' );
		update_post_meta( $booking_id, '_payment_failed', true );

		// Update booking status.
		wp_update_post( array(
			'ID'          => $booking_id,
			'post_status' => 'bkx-cancelled',
		) );

		do_action( 'bkx_booking_fraud_declined', $booking_id, $payload );

		return array(
			'success' => true,
			'message' => sprintf( __( 'Transaction declined for fraud for booking #%d.', 'bkx-authorize-net' ), $booking_id ),
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
	protected function get_booking_amount( int $booking_id ): float {
		$amount = get_post_meta( $booking_id, 'total_amount', true );
		return (float) $amount;
	}

	/**
	 * Save transaction meta data.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param array $result Transaction result.
	 * @return void
	 */
	protected function save_transaction_meta( int $booking_id, array $result ): void {
		update_post_meta( $booking_id, '_payment_method', 'authorize_net' );
		update_post_meta( $booking_id, '_authnet_transaction_id', $result['transaction_id'] );
		update_post_meta( $booking_id, '_authnet_transaction_status', $result['status'] ?? 'AUTHORIZED' );
		update_post_meta( $booking_id, '_payment_amount', $result['amount'] ?? 0 );
		update_post_meta( $booking_id, '_authnet_auth_code', $result['auth_code'] ?? '' );

		if ( ! empty( $result['card_type'] ) ) {
			update_post_meta( $booking_id, '_authnet_card_type', $result['card_type'] );
		}
		if ( ! empty( $result['last_four'] ) ) {
			update_post_meta( $booking_id, '_authnet_last_four', $result['last_four'] );
		}
		if ( ! empty( $result['card_expiration'] ) ) {
			update_post_meta( $booking_id, '_authnet_card_expiration', $result['card_expiration'] );
		}

		// Record in transaction table.
		$this->save_transaction_record( $booking_id, $result );
	}

	/**
	 * Save transaction to database table.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param array $result Transaction result.
	 * @return void
	 */
	protected function save_transaction_record( int $booking_id, array $result ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_authnet_transactions';

		$data = array(
			'booking_id'       => $booking_id,
			'transaction_id'   => $result['transaction_id'],
			'transaction_type' => $result['type'] ?? 'auth_capture',
			'amount'           => $result['amount'] ?? 0,
			'currency'         => 'USD',
			'status'           => $result['status'] ?? 'AUTHORIZED',
			'auth_code'        => $result['auth_code'] ?? '',
			'avs_response'     => $result['avs_response'] ?? '',
			'cvv_response'     => $result['cvv_response'] ?? '',
			'card_type'        => $result['card_type'] ?? '',
			'last_four'        => $result['last_four'] ?? '',
			'created_at'       => current_time( 'mysql' ),
			'updated_at'       => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data );
	}

	/**
	 * Save refund to database table.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $transaction_id Original transaction ID.
	 * @param array  $result Refund result.
	 * @param string $reason Refund reason.
	 * @return void
	 */
	protected function save_refund_record( int $booking_id, string $transaction_id, array $result, string $reason ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_authnet_refunds';

		$data = array(
			'booking_id'     => $booking_id,
			'transaction_id' => $transaction_id,
			'refund_id'      => $result['refund_id'],
			'amount'         => $result['amount'] ?? 0,
			'status'         => 'COMPLETED',
			'reason'         => $reason,
			'created_at'     => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data );
	}

	/**
	 * Get booking ID from transaction ID.
	 *
	 * @since 1.0.0
	 * @param string $transaction_id Transaction ID.
	 * @return int|false Booking ID or false.
	 */
	protected function get_booking_id_from_transaction( string $transaction_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_authnet_transactions';

		// Use %i placeholder for table identifier - SECURITY.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare(
				'SELECT booking_id FROM %i WHERE transaction_id = %s LIMIT 1',
				$table,
				$transaction_id
			)
		);
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

		$log_file = WP_CONTENT_DIR . '/bkx-authorize-net-debug.log';
		$timestamp = current_time( 'c' );
		$formatted = sprintf( "[%s] [%s] %s\n", $timestamp, strtoupper( $level ), $message );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $log_file, $formatted, FILE_APPEND | LOCK_EX );
	}
}
