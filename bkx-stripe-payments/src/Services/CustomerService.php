<?php
/**
 * Customer Service Class
 *
 * Handles Stripe Customer creation and management.
 *
 * @package BookingX\StripePayments\Services
 * @since   1.0.0
 */

namespace BookingX\StripePayments\Services;

use BookingX\StripePayments\StripePayments;
use Stripe\Exception\ApiErrorException;

/**
 * Customer management service.
 *
 * @since 1.0.0
 */
class CustomerService {

	/**
	 * Parent addon instance.
	 *
	 * @var StripePayments
	 */
	protected StripePayments $addon;

	/**
	 * Constructor.
	 *
	 * @param StripePayments $addon Parent addon instance.
	 */
	public function __construct( StripePayments $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Get or create Stripe customer for a booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return string Stripe customer ID.
	 * @throws \Exception If customer creation fails.
	 */
	public function get_or_create_customer( int $booking_id ): string {
		// Get customer email from booking
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		$customer_name  = get_post_meta( $booking_id, 'customer_name', true );
		$customer_phone = get_post_meta( $booking_id, 'customer_phone', true );

		if ( ! $customer_email ) {
			throw new \Exception( __( 'Customer email is required.', 'bkx-stripe-payments' ) );
		}

		// Check if customer already exists in Stripe
		$customer_id = $this->find_existing_customer( $customer_email );

		if ( $customer_id ) {
			return $customer_id;
		}

		// Create new customer
		try {
			$stripe = $this->addon->get_gateway()->get_stripe_client();

			$customer_data = array(
				'email'       => $customer_email,
				'name'        => $customer_name,
				'description' => sprintf(
					/* translators: %d: Booking ID */
					__( 'BookingX Customer - Booking #%d', 'bkx-stripe-payments' ),
					$booking_id
				),
				'metadata'    => array(
					'booking_id' => $booking_id,
					'site_url'   => home_url(),
				),
			);

			if ( $customer_phone ) {
				$customer_data['phone'] = $customer_phone;
			}

			$customer = $stripe->customers->create( $customer_data );

			// Store customer ID with booking
			update_post_meta( $booking_id, '_bkx_stripe_customer_id', $customer->id );

			// If logged in, store with user
			$user_id = get_current_user_id();
			if ( $user_id ) {
				update_user_meta( $user_id, '_bkx_stripe_customer_id', $customer->id );
			}

			$this->addon->get_logger()->info(
				'Stripe customer created',
				array(
					'customer_id' => $customer->id,
					'booking_id'  => $booking_id,
					'email'       => $customer_email,
				)
			);

			return $customer->id;

		} catch ( ApiErrorException $e ) {
			$this->addon->get_logger()->error(
				'Error creating Stripe customer',
				array(
					'booking_id' => $booking_id,
					'error'      => $e->getMessage(),
				)
			);

			throw new \Exception( $e->getMessage() );
		}
	}

	/**
	 * Find existing Stripe customer by email.
	 *
	 * @since 1.0.0
	 * @param string $email Customer email.
	 * @return string|null Customer ID or null if not found.
	 */
	protected function find_existing_customer( string $email ): ?string {
		// Check if logged-in user has a stored customer ID
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$customer_id = get_user_meta( $user_id, '_bkx_stripe_customer_id', true );
			if ( $customer_id ) {
				return $customer_id;
			}
		}

		// Search Stripe for customer by email
		try {
			$stripe = $this->addon->get_gateway()->get_stripe_client();

			$customers = $stripe->customers->all(
				array(
					'email' => $email,
					'limit' => 1,
				)
			);

			if ( count( $customers->data ) > 0 ) {
				return $customers->data[0]->id;
			}

		} catch ( ApiErrorException $e ) {
			$this->addon->get_logger()->warning(
				'Error searching for Stripe customer',
				array(
					'email' => $email,
					'error' => $e->getMessage(),
				)
			);
		}

		return null;
	}

	/**
	 * Attach a payment method to a customer.
	 *
	 * @since 1.0.0
	 * @param string $customer_id       Stripe customer ID.
	 * @param string $payment_method_id Payment method ID.
	 * @return bool Success status.
	 */
	public function attach_payment_method( string $customer_id, string $payment_method_id ): bool {
		try {
			$stripe = $this->addon->get_gateway()->get_stripe_client();

			$stripe->paymentMethods->attach(
				$payment_method_id,
				array( 'customer' => $customer_id )
			);

			// Set as default if it's the first payment method
			$customer = $stripe->customers->retrieve( $customer_id );

			if ( empty( $customer->invoice_settings->default_payment_method ) ) {
				$stripe->customers->update(
					$customer_id,
					array(
						'invoice_settings' => array(
							'default_payment_method' => $payment_method_id,
						),
					)
				);
			}

			return true;

		} catch ( ApiErrorException $e ) {
			$this->addon->get_logger()->error(
				'Error attaching payment method',
				array(
					'customer_id'        => $customer_id,
					'payment_method_id'  => $payment_method_id,
					'error'              => $e->getMessage(),
				)
			);

			return false;
		}
	}

	/**
	 * Get customer's saved payment methods.
	 *
	 * @since 1.0.0
	 * @param string $customer_id Stripe customer ID.
	 * @return array Payment methods.
	 */
	public function get_payment_methods( string $customer_id ): array {
		try {
			$stripe = $this->addon->get_gateway()->get_stripe_client();

			$payment_methods = $stripe->paymentMethods->all(
				array(
					'customer' => $customer_id,
					'type'     => 'card',
				)
			);

			return $payment_methods->data;

		} catch ( ApiErrorException $e ) {
			$this->addon->get_logger()->error(
				'Error retrieving payment methods',
				array(
					'customer_id' => $customer_id,
					'error'       => $e->getMessage(),
				)
			);

			return array();
		}
	}
}
