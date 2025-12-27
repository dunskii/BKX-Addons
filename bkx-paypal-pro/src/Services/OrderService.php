<?php
/**
 * PayPal Order Service
 *
 * Handles order creation and capture operations.
 *
 * @package BookingX\PayPalPro\Services
 * @since   1.0.0
 */

namespace BookingX\PayPalPro\Services;

use BookingX\PayPalPro\Api\PayPalClient;
use BookingX\PayPalPro\Gateway\PayPalGateway;

/**
 * Order service class.
 *
 * @since 1.0.0
 */
class OrderService {

	/**
	 * PayPal API client.
	 *
	 * @var PayPalClient
	 */
	protected PayPalClient $client;

	/**
	 * Gateway instance.
	 *
	 * @var PayPalGateway
	 */
	protected PayPalGateway $gateway;

	/**
	 * Constructor.
	 *
	 * @param PayPalClient   $client  PayPal client.
	 * @param PayPalGateway  $gateway Gateway instance.
	 */
	public function __construct( PayPalClient $client, PayPalGateway $gateway ) {
		$this->client  = $client;
		$this->gateway = $gateway;
	}

	/**
	 * Create a PayPal order.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id Booking ID.
	 * @param float $amount     Amount to charge.
	 * @param array $metadata   Additional order metadata.
	 * @return array Result with 'success' and 'data' or 'error'.
	 */
	public function create_order( int $booking_id, float $amount, array $metadata = array() ): array {
		try {
			// Get booking details.
			$booking = get_post( $booking_id );

			if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
				throw new \Exception( __( 'Invalid booking ID.', 'bkx-paypal-pro' ) );
			}

			// Get currency and intent.
			$currency = $this->gateway->get_setting( 'currency', 'USD' );
			$intent   = strtoupper( $this->gateway->get_setting( 'intent', 'capture' ) );

			// Get customer details.
			$customer_email = get_post_meta( $booking_id, 'customer_email', true );
			$customer_name  = get_post_meta( $booking_id, 'customer_name', true );

			// Build order data.
			$order_data = array(
				'intent'              => $intent,
				'purchase_units'      => array(
					array(
						'reference_id' => 'booking_' . $booking_id,
						'description'  => sprintf(
							/* translators: 1: Site name, 2: Booking ID */
							__( 'Booking #%2$s from %1$s', 'bkx-paypal-pro' ),
							get_bloginfo( 'name' ),
							$booking_id
						),
						'amount'       => array(
							'currency_code' => $currency,
							'value'         => number_format( $amount, 2, '.', '' ),
						),
						'custom_id'    => (string) $booking_id,
					),
				),
				'application_context' => array(
					'brand_name'          => get_bloginfo( 'name' ),
					'locale'              => $this->get_locale(),
					'landing_page'        => 'NO_PREFERENCE',
					'shipping_preference' => 'NO_SHIPPING',
					'user_action'         => 'PAY_NOW',
					'return_url'          => $this->gateway->get_return_url( $booking_id, 'success' ),
					'cancel_url'          => $this->gateway->get_return_url( $booking_id, 'cancel' ),
				),
			);

			// Add payer information if available.
			if ( ! empty( $customer_email ) ) {
				$order_data['payer'] = array(
					'email_address' => $customer_email,
				);

				if ( ! empty( $customer_name ) ) {
					$name_parts = explode( ' ', $customer_name, 2 );
					$order_data['payer']['name'] = array(
						'given_name'  => $name_parts[0],
						'surname'     => $name_parts[1] ?? '',
					);
				}
			}

			// Allow filtering of order data.
			$order_data = apply_filters( 'bkx_paypal_pro_order_data', $order_data, $booking_id, $amount, $metadata );

			// Create the order.
			$response = $this->client->create_order( $order_data );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? __( 'Failed to create PayPal order.', 'bkx-paypal-pro' ) );
			}

			$order = $response['data'];

			// Store order ID in booking meta.
			update_post_meta( $booking_id, '_paypal_order_id', $order['id'] );
			update_post_meta( $booking_id, '_paypal_order_status', $order['status'] );

			// Save to transactions table.
			$this->save_transaction( $booking_id, $order['id'], $order, 'created' );

			return array(
				'success' => true,
				'data'    => array(
					'order_id' => $order['id'],
					'status'   => $order['status'],
					'links'    => $order['links'] ?? array(),
				),
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Capture a PayPal order.
	 *
	 * @since 1.0.0
	 * @param string $order_id   PayPal order ID.
	 * @param int    $booking_id Booking ID.
	 * @return array Result with 'success' and 'data' or 'error'.
	 */
	public function capture_order( string $order_id, int $booking_id ): array {
		try {
			// Capture the order.
			$response = $this->client->capture_order( $order_id );

			if ( ! $response['success'] ) {
				throw new \Exception( $response['error'] ?? __( 'Failed to capture PayPal order.', 'bkx-paypal-pro' ) );
			}

			$capture_data = $response['data'];
			$status       = $capture_data['status'] ?? 'UNKNOWN';

			if ( 'COMPLETED' !== $status ) {
				throw new \Exception(
					sprintf(
						/* translators: %s: order status */
						__( 'Order capture returned unexpected status: %s', 'bkx-paypal-pro' ),
						$status
					)
				);
			}

			// Extract capture details.
			$purchase_units = $capture_data['purchase_units'] ?? array();
			$capture_info   = $purchase_units[0]['payments']['captures'][0] ?? null;

			if ( ! $capture_info ) {
				throw new \Exception( __( 'No capture information found in response.', 'bkx-paypal-pro' ) );
			}

			$capture_id     = $capture_info['id'];
			$amount         = $capture_info['amount']['value'] ?? '0.00';
			$currency       = $capture_info['amount']['currency_code'] ?? 'USD';
			$payment_source = $capture_data['payment_source'] ?? array();

			// Update booking meta.
			update_post_meta( $booking_id, '_paypal_capture_id', $capture_id );
			update_post_meta( $booking_id, '_paypal_order_status', 'COMPLETED' );
			update_post_meta( $booking_id, '_paypal_payment_amount', $amount );
			update_post_meta( $booking_id, '_paypal_payment_currency', $currency );

			// Update transaction record.
			$this->save_transaction(
				$booking_id,
				$order_id,
				$capture_data,
				'completed',
				$capture_id,
				$amount,
				$currency,
				$payment_source
			);

			return array(
				'success' => true,
				'data'    => array(
					'capture_id'     => $capture_id,
					'order_id'       => $order_id,
					'amount'         => $amount,
					'currency'       => $currency,
					'status'         => 'COMPLETED',
					'payment_source' => $payment_source,
				),
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Save transaction to database.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id     Booking ID.
	 * @param string $order_id       PayPal order ID.
	 * @param array  $order_data     Full order/capture data.
	 * @param string $status         Transaction status.
	 * @param string $capture_id     Capture ID (optional).
	 * @param string $amount         Amount (optional).
	 * @param string $currency       Currency (optional).
	 * @param array  $payment_source Payment source data (optional).
	 * @return int|false Insert ID or false on failure.
	 */
	protected function save_transaction( int $booking_id, string $order_id, array $order_data, string $status, string $capture_id = '', string $amount = '', string $currency = '', array $payment_source = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_paypal_transactions';

		$data = array(
			'booking_id'     => $booking_id,
			'paypal_order_id' => $order_id,
			'capture_id'     => $capture_id,
			'amount'         => $amount,
			'currency'       => $currency,
			'status'         => $status,
			'payment_source' => wp_json_encode( $payment_source ),
			'metadata'       => wp_json_encode( $order_data ),
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		);

		// Check if record exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE paypal_order_id = %s",
				$order_id
			)
		);

		if ( $exists ) {
			// Update existing record.
			unset( $data['created_at'] );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $table, $data, array( 'paypal_order_id' => $order_id ) );
			return (int) $exists;
		}

		// Insert new record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data );

		return $wpdb->insert_id;
	}

	/**
	 * Get PayPal locale based on WordPress locale.
	 *
	 * @since 1.0.0
	 * @return string PayPal locale code.
	 */
	protected function get_locale(): string {
		$wp_locale = get_locale();

		$locale_map = array(
			'en_US' => 'en-US',
			'en_GB' => 'en-GB',
			'es_ES' => 'es-ES',
			'fr_FR' => 'fr-FR',
			'de_DE' => 'de-DE',
			'it_IT' => 'it-IT',
			'ja'    => 'ja-JP',
			'zh_CN' => 'zh-CN',
			'pt_BR' => 'pt-BR',
		);

		return $locale_map[ $wp_locale ] ?? 'en-US';
	}
}
