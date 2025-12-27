<?php
/**
 * Webhook Service Class
 *
 * Handles Stripe webhook event processing.
 *
 * @package BookingX\StripePayments\Services
 * @since   1.0.0
 */

namespace BookingX\StripePayments\Services;

use BookingX\StripePayments\StripePayments;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

/**
 * Webhook event processing service.
 *
 * @since 1.0.0
 */
class WebhookService {

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
	 * Handle incoming webhook.
	 *
	 * @since 1.0.0
	 * @param string $raw_payload Raw webhook payload (JSON string).
	 * @return array Result array.
	 */
	public function handle_webhook( string $raw_payload ): array {
		try {
			// Get webhook signature from header.
			$signature = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] )
				? sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) )
				: '';

			if ( empty( $signature ) ) {
				throw new \Exception( __( 'Missing webhook signature.', 'bkx-stripe-payments' ) );
			}

			// Verify webhook signature using RAW payload - SECURITY CRITICAL.
			// Stripe signature verification MUST use the original raw body.
			$event = $this->verify_webhook_signature( $raw_payload, $signature );

			if ( is_wp_error( $event ) ) {
				throw new \Exception( $event->get_error_message() );
			}

			// Log webhook event
			$this->log_webhook_event( $event );

			// Process event based on type
			$result = $this->process_event( $event );

			return array(
				'success' => true,
				'event'   => $event->type,
				'result'  => $result,
			);

		} catch ( \Exception $e ) {
			// Decode payload for logging (but don't log sensitive data).
			$decoded = json_decode( $raw_payload, true );
			$log_data = array(
				'error'      => $e->getMessage(),
				'event_type' => $decoded['type'] ?? 'unknown',
				'event_id'   => $decoded['id'] ?? 'unknown',
			);

			$this->addon->get_logger()->error( 'Webhook processing error', $log_data );

			return array(
				'success' => false,
				'error'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Verify webhook signature.
	 *
	 * @since 1.0.0
	 * @param string $raw_payload Raw webhook payload (JSON string).
	 * @param string $signature   Stripe signature header.
	 * @return object|\WP_Error Event object or WP_Error.
	 */
	protected function verify_webhook_signature( string $raw_payload, string $signature ) {
		$webhook_secret = $this->addon->get_setting( 'stripe_webhook_secret', '' );

		if ( empty( $webhook_secret ) ) {
			return new \WP_Error( 'missing_webhook_secret', __( 'Webhook secret not configured.', 'bkx-stripe-payments' ) );
		}

		try {
			// Use raw payload directly - DO NOT re-encode.
			// Stripe signature is computed from the exact bytes sent.
			$event = Webhook::constructEvent(
				$raw_payload,
				$signature,
				$webhook_secret
			);

			return $event;

		} catch ( SignatureVerificationException $e ) {
			return new \WP_Error( 'signature_verification_failed', $e->getMessage() );
		}
	}

	/**
	 * Process webhook event.
	 *
	 * @since 1.0.0
	 * @param object $event Stripe event object.
	 * @return array Processing result.
	 */
	protected function process_event( object $event ): array {
		switch ( $event->type ) {
			case 'payment_intent.succeeded':
				return $this->handle_payment_succeeded( $event->data->object );

			case 'payment_intent.payment_failed':
				return $this->handle_payment_failed( $event->data->object );

			case 'payment_intent.canceled':
				return $this->handle_payment_canceled( $event->data->object );

			case 'payment_intent.requires_action':
				return $this->handle_payment_requires_action( $event->data->object );

			case 'charge.refunded':
				return $this->handle_charge_refunded( $event->data->object );

			case 'charge.dispute.created':
				return $this->handle_dispute_created( $event->data->object );

			case 'customer.subscription.created':
			case 'customer.subscription.updated':
			case 'customer.subscription.deleted':
				return $this->handle_subscription_event( $event->type, $event->data->object );

			default:
				return array( 'message' => 'Event type not handled' );
		}
	}

	/**
	 * Handle payment succeeded event.
	 *
	 * @since 1.0.0
	 * @param object $payment_intent PaymentIntent object.
	 * @return array Result.
	 */
	protected function handle_payment_succeeded( object $payment_intent ): array {
		$booking_id = $payment_intent->metadata->booking_id ?? null;

		if ( ! $booking_id ) {
			return array( 'error' => 'No booking ID in metadata' );
		}

		// Update transaction status
		global $wpdb;
		$table = $this->addon->get_table_name( 'stripe_transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'status'     => 'succeeded',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'stripe_payment_intent_id' => $payment_intent->id )
		);

		// Update booking status
		wp_update_post(
			array(
				'ID'          => $booking_id,
				'post_status' => 'bkx-ack',
			)
		);

		// Fire action for other integrations
		do_action( 'bkx_stripe_payment_succeeded', $booking_id, $payment_intent );

		$this->addon->get_logger()->info(
			'Payment succeeded via webhook',
			array(
				'booking_id'        => $booking_id,
				'payment_intent_id' => $payment_intent->id,
			)
		);

		return array( 'booking_id' => $booking_id, 'status' => 'succeeded' );
	}

	/**
	 * Handle payment failed event.
	 *
	 * @since 1.0.0
	 * @param object $payment_intent PaymentIntent object.
	 * @return array Result.
	 */
	protected function handle_payment_failed( object $payment_intent ): array {
		$booking_id = $payment_intent->metadata->booking_id ?? null;

		if ( ! $booking_id ) {
			return array( 'error' => 'No booking ID in metadata' );
		}

		// Update transaction status
		global $wpdb;
		$table = $this->addon->get_table_name( 'stripe_transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'status'     => 'failed',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'stripe_payment_intent_id' => $payment_intent->id )
		);

		// Add note to booking
		$error_message = $payment_intent->last_payment_error->message ?? __( 'Payment failed', 'bkx-stripe-payments' );
		update_post_meta( $booking_id, '_bkx_payment_error', $error_message );

		// Fire action
		do_action( 'bkx_stripe_payment_failed', $booking_id, $payment_intent );

		$this->addon->get_logger()->warning(
			'Payment failed via webhook',
			array(
				'booking_id'        => $booking_id,
				'payment_intent_id' => $payment_intent->id,
				'error'             => $error_message,
			)
		);

		return array( 'booking_id' => $booking_id, 'status' => 'failed' );
	}

	/**
	 * Handle payment canceled event.
	 *
	 * @since 1.0.0
	 * @param object $payment_intent PaymentIntent object.
	 * @return array Result.
	 */
	protected function handle_payment_canceled( object $payment_intent ): array {
		$booking_id = $payment_intent->metadata->booking_id ?? null;

		if ( ! $booking_id ) {
			return array( 'error' => 'No booking ID in metadata' );
		}

		// Update transaction status
		global $wpdb;
		$table = $this->addon->get_table_name( 'stripe_transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'status'     => 'canceled',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'stripe_payment_intent_id' => $payment_intent->id )
		);

		return array( 'booking_id' => $booking_id, 'status' => 'canceled' );
	}

	/**
	 * Handle payment requires action event.
	 *
	 * @since 1.0.0
	 * @param object $payment_intent PaymentIntent object.
	 * @return array Result.
	 */
	protected function handle_payment_requires_action( object $payment_intent ): array {
		$booking_id = $payment_intent->metadata->booking_id ?? null;

		if ( ! $booking_id ) {
			return array( 'error' => 'No booking ID in metadata' );
		}

		// Typically handled on frontend, just log it
		$this->addon->get_logger()->info(
			'Payment requires action',
			array(
				'booking_id'        => $booking_id,
				'payment_intent_id' => $payment_intent->id,
			)
		);

		return array( 'booking_id' => $booking_id, 'status' => 'requires_action' );
	}

	/**
	 * Handle charge refunded event.
	 *
	 * @since 1.0.0
	 * @param object $charge Charge object.
	 * @return array Result.
	 */
	protected function handle_charge_refunded( object $charge ): array {
		// Find transaction by charge ID
		global $wpdb;
		$table = $this->addon->get_table_name( 'stripe_transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$transaction = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE stripe_transaction_id = %s",
				$table,
				$charge->id
			)
		);

		if ( ! $transaction ) {
			return array( 'error' => 'Transaction not found' );
		}

		// Fire action
		do_action( 'bkx_stripe_charge_refunded', $transaction->booking_id, $charge );

		return array( 'booking_id' => $transaction->booking_id, 'status' => 'refunded' );
	}

	/**
	 * Handle dispute created event.
	 *
	 * @since 1.0.0
	 * @param object $dispute Dispute object.
	 * @return array Result.
	 */
	protected function handle_dispute_created( object $dispute ): array {
		// Find transaction by charge ID
		global $wpdb;
		$table = $this->addon->get_table_name( 'stripe_transactions' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$transaction = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE stripe_transaction_id = %s",
				$table,
				$dispute->charge
			)
		);

		if ( ! $transaction ) {
			return array( 'error' => 'Transaction not found' );
		}

		// Add admin notice or notification
		update_post_meta( $transaction->booking_id, '_bkx_payment_dispute', array(
			'dispute_id' => $dispute->id,
			'reason'     => $dispute->reason,
			'amount'     => $dispute->amount,
			'status'     => $dispute->status,
		) );

		// Fire action for notifications
		do_action( 'bkx_stripe_dispute_created', $transaction->booking_id, $dispute );

		$this->addon->get_logger()->warning(
			'Payment dispute created',
			array(
				'booking_id' => $transaction->booking_id,
				'dispute_id' => $dispute->id,
				'reason'     => $dispute->reason,
			)
		);

		return array( 'booking_id' => $transaction->booking_id, 'dispute_id' => $dispute->id );
	}

	/**
	 * Handle subscription events.
	 *
	 * @since 1.0.0
	 * @param string $event_type  Event type.
	 * @param object $subscription Subscription object.
	 * @return array Result.
	 */
	protected function handle_subscription_event( string $event_type, object $subscription ): array {
		global $wpdb;
		$table = $this->addon->get_table_name( 'stripe_subscriptions' );

		$booking_id = $subscription->metadata->booking_id ?? null;

		if ( ! $booking_id ) {
			return array( 'error' => 'No booking ID in metadata' );
		}

		// Upsert subscription record
		$data = array(
			'booking_id'              => $booking_id,
			'stripe_subscription_id'  => $subscription->id,
			'stripe_customer_id'      => $subscription->customer,
			'status'                  => $subscription->status,
			'current_period_start'    => gmdate( 'Y-m-d H:i:s', $subscription->current_period_start ),
			'current_period_end'      => gmdate( 'Y-m-d H:i:s', $subscription->current_period_end ),
			'cancel_at_period_end'    => $subscription->cancel_at_period_end ? 1 : 0,
			'metadata'                => wp_json_encode( $subscription->metadata ),
			'updated_at'              => current_time( 'mysql' ),
		);

		// Check if exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE stripe_subscription_id = %s",
				$table,
				$subscription->id
			)
		);

		if ( $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $table, $data, array( 'stripe_subscription_id' => $subscription->id ) );
		} else {
			$data['created_at'] = current_time( 'mysql' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert( $table, $data );
		}

		return array( 'booking_id' => $booking_id, 'subscription_id' => $subscription->id, 'event' => $event_type );
	}

	/**
	 * Log webhook event to database.
	 *
	 * @since 1.0.0
	 * @param object $event Stripe event object.
	 * @return void
	 */
	protected function log_webhook_event( object $event ): void {
		global $wpdb;
		$table = $this->addon->get_table_name( 'stripe_webhook_events' );

		$data = array(
			'stripe_event_id' => $event->id,
			'event_type'      => $event->type,
			'payload'         => wp_json_encode( $event->data->object ),
			'created_at'      => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $table, $data );
	}
}
