<?php
/**
 * Payment Service
 *
 * Handles payment processing logic.
 *
 * @package BookingX\AuthorizeNet\Services
 * @since   1.0.0
 */

namespace BookingX\AuthorizeNet\Services;

use BookingX\AuthorizeNet\Api\AuthorizeNetClient;
use BookingX\AuthorizeNet\Gateway\AuthorizeNetGateway;

/**
 * Payment service class.
 *
 * @since 1.0.0
 */
class PaymentService {

	/**
	 * API client.
	 *
	 * @var AuthorizeNetClient
	 */
	protected AuthorizeNetClient $client;

	/**
	 * Gateway instance.
	 *
	 * @var AuthorizeNetGateway
	 */
	protected AuthorizeNetGateway $gateway;

	/**
	 * Constructor.
	 *
	 * @param AuthorizeNetClient  $client API client.
	 * @param AuthorizeNetGateway $gateway Gateway instance.
	 */
	public function __construct( AuthorizeNetClient $client, AuthorizeNetGateway $gateway ) {
		$this->client = $client;
		$this->gateway = $gateway;
	}

	/**
	 * Create a transaction.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param float  $amount Amount to charge.
	 * @param string $data_descriptor Opaque data descriptor from Accept.js.
	 * @param string $data_value Opaque data value (token) from Accept.js.
	 * @param string $transaction_type Transaction type (auth_capture or auth_only).
	 * @return array Result with success status.
	 */
	public function create_transaction(
		int $booking_id,
		float $amount,
		string $data_descriptor,
		string $data_value,
		string $transaction_type = 'auth_capture'
	): array {
		// Map our transaction type to Authorize.net types.
		$anet_type = 'auth_capture' === $transaction_type
			? 'authCaptureTransaction'
			: 'authOnlyTransaction';

		// Get booking and customer info.
		$order_info = $this->get_order_info( $booking_id );
		$customer_info = $this->get_customer_info( $booking_id );

		// Create the transaction.
		$result = $this->client->create_transaction(
			$amount,
			$data_descriptor,
			$data_value,
			$anet_type,
			$order_info,
			$customer_info
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		// Add additional info to result.
		$result['amount'] = $amount;
		$result['type'] = $transaction_type;

		// Get card info from Accept.js if available.
		$result['card_type'] = $this->extract_card_type( $data_descriptor );

		return $result;
	}

	/**
	 * Capture an authorized transaction.
	 *
	 * @since 1.0.0
	 * @param int        $booking_id Booking ID.
	 * @param float|null $amount Amount to capture (null for full amount).
	 * @return array Result with success status.
	 */
	public function capture_transaction( int $booking_id, ?float $amount = null ): array {
		$transaction_id = get_post_meta( $booking_id, '_authnet_transaction_id', true );

		if ( empty( $transaction_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No authorization found for this booking.', 'bkx-authorize-net' ),
			);
		}

		$status = get_post_meta( $booking_id, '_authnet_transaction_status', true );

		if ( 'AUTHORIZED' !== $status && 'APPROVED' !== $status ) {
			return array(
				'success' => false,
				'error'   => __( 'Transaction cannot be captured in its current state.', 'bkx-authorize-net' ),
			);
		}

		$result = $this->client->capture_transaction( $transaction_id, $amount );

		if ( $result['success'] ) {
			update_post_meta( $booking_id, '_authnet_transaction_status', 'CAPTURED' );
			update_post_meta( $booking_id, '_payment_complete', true );
		}

		return $result;
	}

	/**
	 * Void a transaction.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array Result with success status.
	 */
	public function void_transaction( int $booking_id ): array {
		$transaction_id = get_post_meta( $booking_id, '_authnet_transaction_id', true );

		if ( empty( $transaction_id ) ) {
			return array(
				'success' => false,
				'error'   => __( 'No transaction found for this booking.', 'bkx-authorize-net' ),
			);
		}

		$result = $this->client->void_transaction( $transaction_id );

		if ( $result['success'] ) {
			update_post_meta( $booking_id, '_authnet_transaction_status', 'VOIDED' );
			update_post_meta( $booking_id, '_payment_voided', true );
		}

		return $result;
	}

	/**
	 * Get order information for transaction.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array Order info.
	 */
	protected function get_order_info( int $booking_id ): array {
		$booking = get_post( $booking_id );

		return array(
			'invoice_number' => 'BKX-' . $booking_id,
			'description'    => sprintf(
				/* translators: %d: booking ID */
				__( 'Booking #%d', 'bkx-authorize-net' ),
				$booking_id
			),
		);
	}

	/**
	 * Get customer information from booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array Customer info.
	 */
	protected function get_customer_info( int $booking_id ): array {
		$customer_info = array();

		// Get customer email.
		$email = get_post_meta( $booking_id, 'customer_email', true );
		if ( ! empty( $email ) ) {
			$customer_info['email'] = $email;
		}

		// Get customer ID if logged in.
		$user_id = get_post_meta( $booking_id, 'customer_id', true );
		if ( ! empty( $user_id ) ) {
			$customer_info['id'] = $user_id;
		}

		// Get billing info.
		$first_name = get_post_meta( $booking_id, 'customer_first_name', true );
		$last_name = get_post_meta( $booking_id, 'customer_last_name', true );
		$address = get_post_meta( $booking_id, 'customer_address', true );
		$city = get_post_meta( $booking_id, 'customer_city', true );
		$state = get_post_meta( $booking_id, 'customer_state', true );
		$zip = get_post_meta( $booking_id, 'customer_zip', true );
		$country = get_post_meta( $booking_id, 'customer_country', true );

		if ( ! empty( $first_name ) || ! empty( $last_name ) ) {
			$customer_info['billing'] = array(
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'address'    => $address,
				'city'       => $city,
				'state'      => $state,
				'zip'        => $zip,
				'country'    => $country,
			);
		}

		return $customer_info;
	}

	/**
	 * Extract card type from data descriptor.
	 *
	 * @since 1.0.0
	 * @param string $data_descriptor Data descriptor.
	 * @return string Card type or empty string.
	 */
	protected function extract_card_type( string $data_descriptor ): string {
		// Accept.js uses COMMON.ACCEPT.INAPP.PAYMENT as data descriptor.
		// Card type is determined by the card number itself, which we don't have access to.
		// The card type will be available in the transaction response from Authorize.net.
		return '';
	}
}
