<?php
/**
 * Authorize.net API Client
 *
 * Wrapper for the Authorize.net SDK.
 *
 * @package BookingX\AuthorizeNet\Api
 * @since   1.0.0
 */

namespace BookingX\AuthorizeNet\Api;

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
use net\authorize\api\constants\ANetEnvironment;

/**
 * Authorize.net API client class.
 *
 * @since 1.0.0
 */
class AuthorizeNetClient {

	/**
	 * API Login ID.
	 *
	 * @var string
	 */
	protected string $api_login_id;

	/**
	 * Transaction Key.
	 *
	 * @var string
	 */
	protected string $transaction_key;

	/**
	 * Whether to use sandbox.
	 *
	 * @var bool
	 */
	protected bool $is_sandbox;

	/**
	 * Merchant authentication.
	 *
	 * @var AnetAPI\MerchantAuthenticationType|null
	 */
	protected ?AnetAPI\MerchantAuthenticationType $merchant_auth = null;

	/**
	 * Constructor.
	 *
	 * @param string $api_login_id API Login ID.
	 * @param string $transaction_key Transaction Key.
	 * @param bool   $is_sandbox Whether to use sandbox.
	 */
	public function __construct( string $api_login_id, string $transaction_key, bool $is_sandbox = true ) {
		$this->api_login_id = $api_login_id;
		$this->transaction_key = $transaction_key;
		$this->is_sandbox = $is_sandbox;
	}

	/**
	 * Get merchant authentication object.
	 *
	 * @since 1.0.0
	 * @return AnetAPI\MerchantAuthenticationType
	 */
	public function get_merchant_auth(): AnetAPI\MerchantAuthenticationType {
		if ( null === $this->merchant_auth ) {
			$this->merchant_auth = new AnetAPI\MerchantAuthenticationType();
			$this->merchant_auth->setName( $this->api_login_id );
			$this->merchant_auth->setTransactionKey( $this->transaction_key );
		}
		return $this->merchant_auth;
	}

	/**
	 * Get API environment.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_environment(): string {
		return $this->is_sandbox ? ANetEnvironment::SANDBOX : ANetEnvironment::PRODUCTION;
	}

	/**
	 * Create a transaction with opaque data from Accept.js.
	 *
	 * @since 1.0.0
	 * @param float  $amount Amount to charge.
	 * @param string $data_descriptor Opaque data descriptor.
	 * @param string $data_value Opaque data value (token).
	 * @param string $transaction_type Transaction type (authCaptureTransaction or authOnlyTransaction).
	 * @param array  $order_info Order information (invoice, description).
	 * @param array  $customer_info Customer information.
	 * @return array Result with success status.
	 */
	public function create_transaction(
		float $amount,
		string $data_descriptor,
		string $data_value,
		string $transaction_type = 'authCaptureTransaction',
		array $order_info = array(),
		array $customer_info = array()
	): array {
		try {
			// Create opaque data payment type.
			$opaque_data = new AnetAPI\OpaqueDataType();
			$opaque_data->setDataDescriptor( $data_descriptor );
			$opaque_data->setDataValue( $data_value );

			$payment_type = new AnetAPI\PaymentType();
			$payment_type->setOpaqueData( $opaque_data );

			// Create transaction request.
			$transaction_request = new AnetAPI\TransactionRequestType();
			$transaction_request->setTransactionType( $transaction_type );
			$transaction_request->setAmount( $amount );
			$transaction_request->setPayment( $payment_type );

			// Add order information.
			if ( ! empty( $order_info['invoice_number'] ) || ! empty( $order_info['description'] ) ) {
				$order = new AnetAPI\OrderType();
				if ( ! empty( $order_info['invoice_number'] ) ) {
					$order->setInvoiceNumber( substr( $order_info['invoice_number'], 0, 20 ) );
				}
				if ( ! empty( $order_info['description'] ) ) {
					$order->setDescription( substr( $order_info['description'], 0, 255 ) );
				}
				$transaction_request->setOrder( $order );
			}

			// Add customer information.
			if ( ! empty( $customer_info['email'] ) ) {
				$customer = new AnetAPI\CustomerDataType();
				$customer->setEmail( $customer_info['email'] );
				if ( ! empty( $customer_info['id'] ) ) {
					$customer->setId( $customer_info['id'] );
				}
				$transaction_request->setCustomer( $customer );
			}

			// Add billing address.
			if ( ! empty( $customer_info['billing'] ) ) {
				$billing = $this->create_address( $customer_info['billing'] );
				$transaction_request->setBillTo( $billing );
			}

			// Create the request.
			$request = new AnetAPI\CreateTransactionRequest();
			$request->setMerchantAuthentication( $this->get_merchant_auth() );
			$request->setTransactionRequest( $transaction_request );

			// Execute the request.
			$controller = new AnetController\CreateTransactionController( $request );
			$response = $controller->executeWithApiResponse( $this->get_environment() );

			return $this->parse_transaction_response( $response );

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Refund a transaction.
	 *
	 * @since 1.0.0
	 * @param string $transaction_id Original transaction ID.
	 * @param float  $amount Amount to refund.
	 * @param string $last_four Last four digits of card.
	 * @param string $expiration Card expiration (MMYY or YYYY-MM).
	 * @return array Result with success status.
	 */
	public function refund_transaction(
		string $transaction_id,
		float $amount,
		string $last_four,
		string $expiration = 'XXXX'
	): array {
		try {
			// Create credit card for refund (masked).
			$credit_card = new AnetAPI\CreditCardType();
			$credit_card->setCardNumber( $last_four );
			$credit_card->setExpirationDate( $expiration );

			$payment_type = new AnetAPI\PaymentType();
			$payment_type->setCreditCard( $credit_card );

			// Create transaction request.
			$transaction_request = new AnetAPI\TransactionRequestType();
			$transaction_request->setTransactionType( 'refundTransaction' );
			$transaction_request->setAmount( $amount );
			$transaction_request->setPayment( $payment_type );
			$transaction_request->setRefTransId( $transaction_id );

			// Create the request.
			$request = new AnetAPI\CreateTransactionRequest();
			$request->setMerchantAuthentication( $this->get_merchant_auth() );
			$request->setTransactionRequest( $transaction_request );

			// Execute the request.
			$controller = new AnetController\CreateTransactionController( $request );
			$response = $controller->executeWithApiResponse( $this->get_environment() );

			$result = $this->parse_transaction_response( $response );

			if ( $result['success'] ) {
				$result['refund_id'] = $result['transaction_id'];
			}

			return $result;

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Void a transaction.
	 *
	 * @since 1.0.0
	 * @param string $transaction_id Transaction ID to void.
	 * @return array Result with success status.
	 */
	public function void_transaction( string $transaction_id ): array {
		try {
			// Create transaction request.
			$transaction_request = new AnetAPI\TransactionRequestType();
			$transaction_request->setTransactionType( 'voidTransaction' );
			$transaction_request->setRefTransId( $transaction_id );

			// Create the request.
			$request = new AnetAPI\CreateTransactionRequest();
			$request->setMerchantAuthentication( $this->get_merchant_auth() );
			$request->setTransactionRequest( $transaction_request );

			// Execute the request.
			$controller = new AnetController\CreateTransactionController( $request );
			$response = $controller->executeWithApiResponse( $this->get_environment() );

			return $this->parse_transaction_response( $response );

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Capture a previously authorized transaction.
	 *
	 * @since 1.0.0
	 * @param string     $transaction_id Transaction ID to capture.
	 * @param float|null $amount Amount to capture (null for full amount).
	 * @return array Result with success status.
	 */
	public function capture_transaction( string $transaction_id, ?float $amount = null ): array {
		try {
			// Create transaction request.
			$transaction_request = new AnetAPI\TransactionRequestType();
			$transaction_request->setTransactionType( 'priorAuthCaptureTransaction' );
			$transaction_request->setRefTransId( $transaction_id );

			if ( null !== $amount ) {
				$transaction_request->setAmount( $amount );
			}

			// Create the request.
			$request = new AnetAPI\CreateTransactionRequest();
			$request->setMerchantAuthentication( $this->get_merchant_auth() );
			$request->setTransactionRequest( $transaction_request );

			// Execute the request.
			$controller = new AnetController\CreateTransactionController( $request );
			$response = $controller->executeWithApiResponse( $this->get_environment() );

			return $this->parse_transaction_response( $response );

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Get transaction details.
	 *
	 * @since 1.0.0
	 * @param string $transaction_id Transaction ID.
	 * @return array Result with transaction details.
	 */
	public function get_transaction_details( string $transaction_id ): array {
		try {
			// Create the request.
			$request = new AnetAPI\GetTransactionDetailsRequest();
			$request->setMerchantAuthentication( $this->get_merchant_auth() );
			$request->setTransId( $transaction_id );

			// Execute the request.
			$controller = new AnetController\GetTransactionDetailsController( $request );
			$response = $controller->executeWithApiResponse( $this->get_environment() );

			if ( null === $response ) {
				return array(
					'success' => false,
					'error'   => __( 'No response from Authorize.net.', 'bkx-authorize-net' ),
				);
			}

			if ( 'Ok' === $response->getMessages()->getResultCode() ) {
				$transaction = $response->getTransaction();

				return array(
					'success'        => true,
					'transaction_id' => $transaction->getTransId(),
					'status'         => $transaction->getTransactionStatus(),
					'amount'         => $transaction->getSettleAmount(),
					'auth_code'      => $transaction->getAuthCode(),
				);
			}

			$errors = $response->getMessages()->getMessage();
			$error_message = ! empty( $errors ) ? $errors[0]->getText() : __( 'Unknown error.', 'bkx-authorize-net' );

			return array(
				'success' => false,
				'error'   => $error_message,
			);

		} catch ( \Exception $e ) {
			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Parse transaction response.
	 *
	 * @since 1.0.0
	 * @param AnetAPI\CreateTransactionResponse|null $response API response.
	 * @return array Parsed result.
	 */
	protected function parse_transaction_response( ?AnetAPI\CreateTransactionResponse $response ): array {
		if ( null === $response ) {
			return array(
				'success' => false,
				'error'   => __( 'No response from Authorize.net.', 'bkx-authorize-net' ),
			);
		}

		$transaction_response = $response->getTransactionResponse();

		if ( null === $transaction_response ) {
			$errors = $response->getMessages()->getMessage();
			$error_message = ! empty( $errors ) ? $errors[0]->getText() : __( 'Unknown error.', 'bkx-authorize-net' );

			return array(
				'success' => false,
				'error'   => $error_message,
			);
		}

		// Check response code: 1 = Approved, 2 = Declined, 3 = Error, 4 = Held for Review.
		$response_code = $transaction_response->getResponseCode();

		if ( '1' === $response_code ) {
			return array(
				'success'        => true,
				'transaction_id' => $transaction_response->getTransId(),
				'auth_code'      => $transaction_response->getAuthCode(),
				'avs_response'   => $transaction_response->getAvsResultCode(),
				'cvv_response'   => $transaction_response->getCvvResultCode(),
				'status'         => 'APPROVED',
				'response_code'  => $response_code,
			);
		}

		if ( '4' === $response_code ) {
			return array(
				'success'        => true,
				'transaction_id' => $transaction_response->getTransId(),
				'auth_code'      => $transaction_response->getAuthCode(),
				'status'         => 'HELD_FOR_REVIEW',
				'response_code'  => $response_code,
			);
		}

		// Get error message.
		$errors = $transaction_response->getErrors();
		if ( ! empty( $errors ) ) {
			$error_message = $errors[0]->getErrorText();
		} else {
			$error_message = __( 'Transaction failed.', 'bkx-authorize-net' );
		}

		return array(
			'success'       => false,
			'error'         => $error_message,
			'response_code' => $response_code,
		);
	}

	/**
	 * Create address object.
	 *
	 * @since 1.0.0
	 * @param array $address Address data.
	 * @return AnetAPI\CustomerAddressType
	 */
	protected function create_address( array $address ): AnetAPI\CustomerAddressType {
		$address_obj = new AnetAPI\CustomerAddressType();

		if ( ! empty( $address['first_name'] ) ) {
			$address_obj->setFirstName( substr( $address['first_name'], 0, 50 ) );
		}
		if ( ! empty( $address['last_name'] ) ) {
			$address_obj->setLastName( substr( $address['last_name'], 0, 50 ) );
		}
		if ( ! empty( $address['company'] ) ) {
			$address_obj->setCompany( substr( $address['company'], 0, 50 ) );
		}
		if ( ! empty( $address['address'] ) ) {
			$address_obj->setAddress( substr( $address['address'], 0, 60 ) );
		}
		if ( ! empty( $address['city'] ) ) {
			$address_obj->setCity( substr( $address['city'], 0, 40 ) );
		}
		if ( ! empty( $address['state'] ) ) {
			$address_obj->setState( substr( $address['state'], 0, 40 ) );
		}
		if ( ! empty( $address['zip'] ) ) {
			$address_obj->setZip( substr( $address['zip'], 0, 20 ) );
		}
		if ( ! empty( $address['country'] ) ) {
			$address_obj->setCountry( substr( $address['country'], 0, 60 ) );
		}

		return $address_obj;
	}
}
