<?php
/**
 * Customer Sync Service
 *
 * @package BookingX\SquarePayments\Services
 */

namespace BookingX\SquarePayments\Services;

use BookingX\SquarePayments\Gateway\SquareGateway;
use BookingX\SquarePayments\Api\SquareClient;
use Square\Models\CreateCustomerRequest;
use Square\Models\SearchCustomersRequest;
use Square\Models\CustomerQuery;
use Square\Models\CustomerFilter;
use Square\Models\CustomerTextFilter;

/**
 * Customer service class.
 *
 * @since 1.0.0
 */
class CustomerService {

	/**
	 * Gateway instance.
	 *
	 * @var SquareGateway
	 */
	protected $gateway;

	/**
	 * Square client.
	 *
	 * @var SquareClient
	 */
	protected $client;

	/**
	 * Constructor.
	 *
	 * @param SquareGateway $gateway Gateway instance.
	 * @param SquareClient  $client  Square client.
	 */
	public function __construct( SquareGateway $gateway, SquareClient $client ) {
		$this->gateway = $gateway;
		$this->client  = $client;
	}

	/**
	 * Get or create a Square customer.
	 *
	 * @since 1.0.0
	 * @param array $customer_data Customer data.
	 * @return string|null Square customer ID or null on failure.
	 */
	public function get_or_create_customer( array $customer_data ): ?string {
		try {
			// Search for existing customer by email.
			$customer_id = $this->search_customer_by_email( $customer_data['customer_email'] );

			if ( $customer_id ) {
				return $customer_id;
			}

			// Create new customer.
			return $this->create_customer( $customer_data );

		} catch ( \Exception $e ) {
			// Log error but don't fail payment.
			if ( $this->gateway->get_setting( 'debug_log', false ) ) {
				error_log( 'Square customer sync error: ' . $e->getMessage() );
			}

			return null;
		}
	}

	/**
	 * Search for customer by email.
	 *
	 * @since 1.0.0
	 * @param string $email Customer email.
	 * @return string|null Customer ID or null if not found.
	 */
	protected function search_customer_by_email( string $email ): ?string {
		$customers_api = $this->client->get_customers_api();

		// Create email filter.
		$email_filter = new CustomerTextFilter();
		$email_filter->setExact( $email );

		// Create customer filter.
		$filter = new CustomerFilter();
		$filter->setEmailAddress( $email_filter );

		// Create query.
		$query = new CustomerQuery();
		$query->setFilter( $filter );

		// Create search request.
		$search_request = new SearchCustomersRequest();
		$search_request->setQuery( $query );

		// Execute search.
		$api_response = $customers_api->searchCustomers( $search_request );

		if ( $api_response->isError() ) {
			return null;
		}

		$customers = $api_response->getResult()->getCustomers();

		if ( ! empty( $customers ) ) {
			return $customers[0]->getId();
		}

		return null;
	}

	/**
	 * Create a new Square customer.
	 *
	 * @since 1.0.0
	 * @param array $customer_data Customer data.
	 * @return string|null Customer ID or null on failure.
	 */
	protected function create_customer( array $customer_data ): ?string {
		$customers_api = $this->client->get_customers_api();

		// Generate idempotency key.
		$idempotency_key = wp_generate_password( 32, false );

		// Prepare customer request.
		$create_request = new CreateCustomerRequest();
		$create_request->setIdempotencyKey( $idempotency_key );

		if ( ! empty( $customer_data['customer_email'] ) ) {
			$create_request->setEmailAddress( sanitize_email( $customer_data['customer_email'] ) );
		}

		if ( ! empty( $customer_data['customer_name'] ) ) {
			$name_parts = explode( ' ', $customer_data['customer_name'], 2 );
			$create_request->setGivenName( sanitize_text_field( $name_parts[0] ) );
			if ( isset( $name_parts[1] ) ) {
				$create_request->setFamilyName( sanitize_text_field( $name_parts[1] ) );
			}
		}

		if ( ! empty( $customer_data['customer_phone'] ) ) {
			$create_request->setPhoneNumber( sanitize_text_field( $customer_data['customer_phone'] ) );
		}

		// Execute create.
		$api_response = $customers_api->createCustomer( $create_request );

		if ( $api_response->isError() ) {
			return null;
		}

		$customer = $api_response->getResult()->getCustomer();

		return $customer ? $customer->getId() : null;
	}
}
