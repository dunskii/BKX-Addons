<?php
/**
 * Square API Client Wrapper
 *
 * @package BookingX\SquarePayments\Api
 */

namespace BookingX\SquarePayments\Api;

use Square\SquareClient as SquareSDKClient;
use Square\Environment;
use BookingX\SquarePayments\Gateway\SquareGateway;

/**
 * Square API client wrapper class.
 *
 * @since 1.0.0
 */
class SquareClient {

	/**
	 * Gateway instance.
	 *
	 * @var SquareGateway
	 */
	protected $gateway;

	/**
	 * Square SDK client.
	 *
	 * @var SquareSDKClient
	 */
	protected $client;

	/**
	 * Constructor.
	 *
	 * @param SquareGateway $gateway Gateway instance.
	 */
	public function __construct( SquareGateway $gateway ) {
		$this->gateway = $gateway;
		$this->initialize_client();
	}

	/**
	 * Initialize the Square SDK client.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function initialize_client(): void {
		$mode = $this->gateway->is_test_mode() ? 'sandbox' : 'production';

		$access_token = bkx_square_payments()->get_setting( "square_{$mode}_access_token", '' );

		if ( empty( $access_token ) ) {
			return;
		}

		$environment = 'sandbox' === $mode ? Environment::SANDBOX : Environment::PRODUCTION;

		$this->client = new SquareSDKClient([
			'accessToken' => $access_token,
			'environment' => $environment,
		]);
	}

	/**
	 * Get the SDK client instance.
	 *
	 * @since 1.0.0
	 * @return SquareSDKClient|null
	 */
	public function get_client(): ?SquareSDKClient {
		return $this->client;
	}

	/**
	 * Get the Payments API.
	 *
	 * @since 1.0.0
	 * @return \Square\Apis\PaymentsApi|null
	 */
	public function get_payments_api() {
		return $this->client ? $this->client->getPaymentsApi() : null;
	}

	/**
	 * Get the Refunds API.
	 *
	 * @since 1.0.0
	 * @return \Square\Apis\RefundsApi|null
	 */
	public function get_refunds_api() {
		return $this->client ? $this->client->getRefundsApi() : null;
	}

	/**
	 * Get the Customers API.
	 *
	 * @since 1.0.0
	 * @return \Square\Apis\CustomersApi|null
	 */
	public function get_customers_api() {
		return $this->client ? $this->client->getCustomersApi() : null;
	}

	/**
	 * Get the Orders API.
	 *
	 * @since 1.0.0
	 * @return \Square\Apis\OrdersApi|null
	 */
	public function get_orders_api() {
		return $this->client ? $this->client->getOrdersApi() : null;
	}

	/**
	 * Get the location ID.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_location_id(): string {
		$mode = $this->gateway->is_test_mode() ? 'sandbox' : 'production';
		return bkx_square_payments()->get_setting( "square_{$mode}_location_id", '' );
	}

	/**
	 * Get the application ID.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_application_id(): string {
		$mode = $this->gateway->is_test_mode() ? 'sandbox' : 'production';
		return bkx_square_payments()->get_setting( "square_{$mode}_application_id", '' );
	}

	/**
	 * Check if the client is initialized.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_initialized(): bool {
		return null !== $this->client;
	}
}
