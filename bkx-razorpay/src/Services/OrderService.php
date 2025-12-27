<?php
/**
 * Order Service
 *
 * Handles Razorpay order creation.
 *
 * @package BookingX\Razorpay\Services
 * @since   1.0.0
 */

namespace BookingX\Razorpay\Services;

use BookingX\Razorpay\Api\RazorpayClient;
use BookingX\Razorpay\Gateway\RazorpayGateway;

/**
 * Order service class.
 *
 * @since 1.0.0
 */
class OrderService {

	/**
	 * API client.
	 *
	 * @var RazorpayClient
	 */
	protected RazorpayClient $client;

	/**
	 * Gateway instance.
	 *
	 * @var RazorpayGateway
	 */
	protected RazorpayGateway $gateway;

	/**
	 * Constructor.
	 *
	 * @param RazorpayClient  $client API client.
	 * @param RazorpayGateway $gateway Gateway instance.
	 */
	public function __construct( RazorpayClient $client, RazorpayGateway $gateway ) {
		$this->client = $client;
		$this->gateway = $gateway;
	}

	/**
	 * Create a Razorpay order for a booking.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param float $amount Amount in main currency unit.
	 * @return array Result with order details.
	 */
	public function create_order( int $booking_id, float $amount ): array {
		// Convert to paise (smallest currency unit).
		$amount_in_paise = (int) round( $amount * 100 );

		// Generate receipt ID.
		$receipt = $this->gateway->get_order_prefix() . $booking_id;

		// Get currency.
		$currency = $this->gateway->get_currency();

		// Prepare notes.
		$notes = array(
			'booking_id' => (string) $booking_id,
			'source'     => 'BookingX',
		);

		// Create order via API.
		$result = $this->client->create_order(
			$amount_in_paise,
			$currency,
			$receipt,
			$notes
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		// Save order to database.
		$this->save_order_record( $booking_id, $result );

		return array(
			'success'         => true,
			'order_id'        => $result['order_id'],
			'amount'          => $amount,
			'amount_in_paise' => $amount_in_paise,
			'currency'        => $currency,
			'key_id'          => $this->get_key_id(),
		);
	}

	/**
	 * Save order record to database.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param array $order_data Order data from API.
	 * @return void
	 */
	protected function save_order_record( int $booking_id, array $order_data ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_razorpay_transactions';

		$data = array(
			'booking_id'        => $booking_id,
			'razorpay_order_id' => $order_data['order_id'],
			'amount'            => ( $order_data['amount'] ?? 0 ) / 100,
			'currency'          => $order_data['currency'] ?? 'INR',
			'status'            => 'created',
			'created_at'        => current_time( 'mysql' ),
			'updated_at'        => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data );
	}

	/**
	 * Get Key ID for checkout.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_key_id(): string {
		return $this->gateway->get_client()->get_api()->getKey();
	}
}
