<?php
/**
 * Payment Processor Service.
 *
 * @package BookingX\BulkRecurringPayments
 * @since   1.0.0
 */

namespace BookingX\BulkRecurringPayments\Services;

/**
 * PaymentProcessor class.
 *
 * Handles payment processing and retries.
 *
 * @since 1.0.0
 */
class PaymentProcessor {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Payments table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		global $wpdb;
		$this->settings = $settings;
		$this->table    = $wpdb->prefix . 'bkx_subscription_payments';
	}

	/**
	 * Record a payment.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Payment data.
	 * @return int|\WP_Error Payment ID or error.
	 */
	public function record_payment( $data ) {
		global $wpdb;

		$defaults = array(
			'subscription_id'      => 0,
			'amount'               => 0,
			'currency'             => 'USD',
			'gateway'              => '',
			'gateway_payment_id'   => '',
			'status'               => 'pending',
			'payment_type'         => 'subscription',
			'billing_period_start' => null,
			'billing_period_end'   => null,
			'failure_reason'       => null,
			'retry_count'          => 0,
			'paid_at'              => null,
			'meta_data'            => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			array(
				'subscription_id'      => absint( $data['subscription_id'] ),
				'amount'               => floatval( $data['amount'] ),
				'currency'             => sanitize_text_field( $data['currency'] ),
				'gateway'              => sanitize_text_field( $data['gateway'] ),
				'gateway_payment_id'   => sanitize_text_field( $data['gateway_payment_id'] ),
				'status'               => sanitize_text_field( $data['status'] ),
				'payment_type'         => sanitize_text_field( $data['payment_type'] ),
				'billing_period_start' => $data['billing_period_start'],
				'billing_period_end'   => $data['billing_period_end'],
				'failure_reason'       => $data['failure_reason'],
				'retry_count'          => absint( $data['retry_count'] ),
				'paid_at'              => $data['paid_at'],
				'meta_data'            => $data['meta_data'] ? wp_json_encode( $data['meta_data'] ) : null,
			),
			array( '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to record payment.', 'bkx-bulk-recurring-payments' ) );
		}

		$payment_id = $wpdb->insert_id;

		/**
		 * Fires after a payment is recorded.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $payment_id Payment ID.
		 * @param array $data       Payment data.
		 */
		do_action( 'bkx_payment_recorded', $payment_id, $data );

		return $payment_id;
	}

	/**
	 * Update payment status.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $payment_id Payment ID.
	 * @param string $status New status.
	 * @param array  $extra_data Additional data to update.
	 * @return bool|\WP_Error
	 */
	public function update_status( $payment_id, $status, $extra_data = array() ) {
		global $wpdb;

		$data = array( 'status' => $status );

		if ( 'completed' === $status && empty( $extra_data['paid_at'] ) ) {
			$data['paid_at'] = current_time( 'mysql' );
		}

		$data = array_merge( $data, $extra_data );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			$data,
			array( 'id' => $payment_id ),
			null,
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to update payment.', 'bkx-bulk-recurring-payments' ) );
		}

		/**
		 * Fires after payment status is updated.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $payment_id Payment ID.
		 * @param string $status     New status.
		 */
		do_action( 'bkx_payment_status_updated', $payment_id, $status );

		return true;
	}

	/**
	 * Get payments for a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $subscription_id Subscription ID.
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_by_subscription( $subscription_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'  => '',
			'orderby' => 'created_at',
			'order'   => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( $wpdb->prepare( 'subscription_id = %d', $subscription_id ) );

		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_clause} ORDER BY %i %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->table,
				$args['orderby'],
				$args['order']
			)
		);
	}

	/**
	 * Process due payments.
	 *
	 * @since 1.0.0
	 */
	public function process_due_payments() {
		$subscription_manager = new SubscriptionManager( $this->settings );
		$subscriptions        = $subscription_manager->get_all(
			array(
				'status' => 'active',
			)
		);

		$today = current_time( 'Y-m-d' );

		foreach ( $subscriptions as $subscription ) {
			if ( $subscription->next_billing_date <= $today ) {
				$this->process_subscription_payment( $subscription );
			}
		}
	}

	/**
	 * Process a subscription payment.
	 *
	 * @since 1.0.0
	 *
	 * @param object $subscription Subscription object.
	 * @return bool|\WP_Error
	 */
	public function process_subscription_payment( $subscription ) {
		$package = $subscription->package;

		if ( ! $package ) {
			return new \WP_Error( 'no_package', __( 'Package not found.', 'bkx-bulk-recurring-payments' ) );
		}

		// Calculate amount.
		$amount = $package->price;

		// Record pending payment.
		$payment_id = $this->record_payment(
			array(
				'subscription_id'      => $subscription->id,
				'amount'               => $amount,
				'gateway'              => $subscription->gateway,
				'payment_type'         => 'subscription',
				'billing_period_start' => $subscription->current_period_end,
				'billing_period_end'   => $this->calculate_next_period_end( $subscription ),
			)
		);

		if ( is_wp_error( $payment_id ) ) {
			return $payment_id;
		}

		// Charge via gateway.
		$result = $this->charge_subscription( $subscription, $amount );

		if ( is_wp_error( $result ) ) {
			// Mark payment as failed.
			$this->update_status(
				$payment_id,
				'failed',
				array( 'failure_reason' => $result->get_error_message() )
			);

			// Handle failure.
			$this->handle_payment_failure( $subscription, $payment_id );

			return $result;
		}

		// Mark payment as completed.
		$this->update_status(
			$payment_id,
			'completed',
			array( 'gateway_payment_id' => $result['payment_id'] )
		);

		// Renew subscription.
		$subscription_manager = new SubscriptionManager( $this->settings );
		$subscription_manager->renew( $subscription->id );

		// Update total billed.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}bkx_subscriptions
				SET total_amount_billed = total_amount_billed + %f
				WHERE id = %d",
				$amount,
				$subscription->id
			)
		);

		/**
		 * Fires after a subscription payment is processed.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $subscription_id Subscription ID.
		 * @param int    $payment_id      Payment ID.
		 * @param float  $amount          Amount charged.
		 */
		do_action( 'bkx_subscription_payment_processed', $subscription->id, $payment_id, $amount );

		return true;
	}

	/**
	 * Charge subscription via gateway.
	 *
	 * @since 1.0.0
	 *
	 * @param object $subscription Subscription object.
	 * @param float  $amount Amount to charge.
	 * @return array|\WP_Error
	 */
	private function charge_subscription( $subscription, $amount ) {
		/**
		 * Filter to charge subscription.
		 *
		 * @since 1.0.0
		 *
		 * @param array|\WP_Error $result       Charge result.
		 * @param object          $subscription Subscription object.
		 * @param float           $amount       Amount to charge.
		 */
		return apply_filters(
			"bkx_charge_{$subscription->gateway}_subscription",
			new \WP_Error( 'not_implemented', __( 'Gateway not configured.', 'bkx-bulk-recurring-payments' ) ),
			$subscription,
			$amount
		);
	}

	/**
	 * Handle payment failure.
	 *
	 * @since 1.0.0
	 *
	 * @param object $subscription Subscription object.
	 * @param int    $payment_id Payment ID.
	 */
	private function handle_payment_failure( $subscription, $payment_id ) {
		global $wpdb;

		// Count failed payments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$failed_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i
				WHERE subscription_id = %d
				AND status = 'failed'
				AND created_at > %s",
				$this->table,
				$subscription->id,
				$subscription->current_period_start
			)
		);

		$auto_cancel_after = $this->settings['auto_cancel_failed_payments'] ?? 3;

		if ( $failed_count >= $auto_cancel_after ) {
			// Cancel subscription.
			$subscription_manager = new SubscriptionManager( $this->settings );
			$subscription_manager->cancel(
				$subscription->id,
				__( 'Automatically cancelled due to repeated payment failures.', 'bkx-bulk-recurring-payments' ),
				true
			);

			/**
			 * Fires when subscription is auto-cancelled due to payment failures.
			 *
			 * @since 1.0.0
			 *
			 * @param int $subscription_id Subscription ID.
			 * @param int $failed_count    Number of failed payments.
			 */
			do_action( 'bkx_subscription_auto_cancelled', $subscription->id, $failed_count );
		} else {
			/**
			 * Fires when a subscription payment fails.
			 *
			 * @since 1.0.0
			 *
			 * @param int $subscription_id Subscription ID.
			 * @param int $payment_id      Payment ID.
			 * @param int $failed_count    Number of failed payments.
			 */
			do_action( 'bkx_subscription_payment_failed', $subscription->id, $payment_id, $failed_count );
		}
	}

	/**
	 * Retry failed payments.
	 *
	 * @since 1.0.0
	 */
	public function retry_failed() {
		if ( empty( $this->settings['retry_failed_payments'] ) ) {
			return;
		}

		global $wpdb;

		$max_retries      = $this->settings['max_retry_attempts'] ?? 3;
		$retry_interval   = $this->settings['retry_interval_hours'] ?? 24;
		$retry_after      = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retry_interval} hours" ) );

		// Get failed payments eligible for retry.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$failed_payments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.*, s.gateway, s.gateway_subscription_id
				FROM %i p
				JOIN {$wpdb->prefix}bkx_subscriptions s ON p.subscription_id = s.id
				WHERE p.status = 'failed'
				AND p.retry_count < %d
				AND p.created_at < %s
				AND s.status = 'active'",
				$this->table,
				$max_retries,
				$retry_after
			)
		);

		foreach ( $failed_payments as $payment ) {
			$this->retry_payment( $payment );
		}
	}

	/**
	 * Retry a failed payment.
	 *
	 * @since 1.0.0
	 *
	 * @param object $payment Payment object.
	 * @return bool|\WP_Error
	 */
	private function retry_payment( $payment ) {
		global $wpdb;

		// Increment retry count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array( 'retry_count' => $payment->retry_count + 1 ),
			array( 'id' => $payment->id ),
			array( '%d' ),
			array( '%d' )
		);

		// Get subscription.
		$subscription_manager = new SubscriptionManager( $this->settings );
		$subscription         = $subscription_manager->get( $payment->subscription_id );

		if ( ! $subscription ) {
			return new \WP_Error( 'no_subscription', __( 'Subscription not found.', 'bkx-bulk-recurring-payments' ) );
		}

		// Attempt charge.
		$result = $this->charge_subscription( $subscription, $payment->amount );

		if ( is_wp_error( $result ) ) {
			// Update failure reason.
			$this->update_status(
				$payment->id,
				'failed',
				array( 'failure_reason' => $result->get_error_message() )
			);

			$this->handle_payment_failure( $subscription, $payment->id );

			return $result;
		}

		// Success!
		$this->update_status(
			$payment->id,
			'completed',
			array( 'gateway_payment_id' => $result['payment_id'] )
		);

		// Renew subscription.
		$subscription_manager->renew( $subscription->id );

		/**
		 * Fires after a payment retry succeeds.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $payment_id      Payment ID.
		 * @param int    $subscription_id Subscription ID.
		 * @param int    $retry_count     Number of retries.
		 */
		do_action( 'bkx_payment_retry_succeeded', $payment->id, $subscription->id, $payment->retry_count + 1 );

		return true;
	}

	/**
	 * Handle Stripe payment webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param object $event Stripe event object.
	 */
	public function handle_stripe_payment( $event ) {
		$invoice = $event->data->object;

		if ( empty( $invoice->subscription ) ) {
			return;
		}

		$subscription_manager = new SubscriptionManager( $this->settings );
		$subscription         = $subscription_manager->get_by_gateway_id( 'stripe', $invoice->subscription );

		if ( ! $subscription ) {
			return;
		}

		// Record payment.
		$this->record_payment(
			array(
				'subscription_id'    => $subscription->id,
				'amount'             => $invoice->amount_paid / 100, // Stripe uses cents.
				'currency'           => strtoupper( $invoice->currency ),
				'gateway'            => 'stripe',
				'gateway_payment_id' => $invoice->payment_intent,
				'status'             => 'completed',
				'paid_at'            => current_time( 'mysql' ),
				'meta_data'          => array(
					'invoice_id'  => $invoice->id,
					'invoice_url' => $invoice->hosted_invoice_url,
				),
			)
		);

		// Renew subscription.
		$subscription_manager->renew( $subscription->id );
	}

	/**
	 * Calculate next period end date.
	 *
	 * @since 1.0.0
	 *
	 * @param object $subscription Subscription object.
	 * @return string
	 */
	private function calculate_next_period_end( $subscription ) {
		$package = $subscription->package;

		if ( ! $package ) {
			return $subscription->current_period_end;
		}

		$interval = $package->interval_count . ' ' . $package->interval_type;
		return gmdate( 'Y-m-d', strtotime( $subscription->current_period_end . " +{$interval}" ) );
	}

	/**
	 * Get payment statistics.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_stats( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'period' => 'month',
		);

		$args = wp_parse_args( $args, $defaults );

		$date_from = match ( $args['period'] ) {
			'week'  => gmdate( 'Y-m-d', strtotime( '-7 days' ) ),
			'month' => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
			'year'  => gmdate( 'Y-m-d', strtotime( '-365 days' ) ),
			default => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
		};

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_payments,
					SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_payments,
					SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
					SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
					AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as average_payment
				FROM %i
				WHERE created_at >= %s",
				$this->table,
				$date_from
			)
		);

		return array(
			'total_payments'      => (int) $stats->total_payments,
			'successful_payments' => (int) $stats->successful_payments,
			'failed_payments'     => (int) $stats->failed_payments,
			'success_rate'        => $stats->total_payments > 0 ? round( ( $stats->successful_payments / $stats->total_payments ) * 100, 2 ) : 0,
			'total_revenue'       => (float) $stats->total_revenue,
			'average_payment'     => (float) $stats->average_payment,
		);
	}
}
