<?php
/**
 * Subscription Manager Service.
 *
 * @package BookingX\BulkRecurringPayments
 * @since   1.0.0
 */

namespace BookingX\BulkRecurringPayments\Services;

/**
 * SubscriptionManager class.
 *
 * Handles subscription lifecycle management.
 *
 * @since 1.0.0
 */
class SubscriptionManager {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Table name.
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
		$this->table    = $wpdb->prefix . 'bkx_subscriptions';
	}

	/**
	 * Get a subscription by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Subscription ID.
	 * @return object|null
	 */
	public function get( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$subscription = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				$this->table,
				$id
			)
		);

		if ( $subscription ) {
			$subscription = $this->hydrate( $subscription );
		}

		return $subscription;
	}

	/**
	 * Get subscription by gateway subscription ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $gateway Gateway name.
	 * @param string $gateway_subscription_id Gateway subscription ID.
	 * @return object|null
	 */
	public function get_by_gateway_id( $gateway, $gateway_subscription_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$subscription = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE gateway = %s AND gateway_subscription_id = %s",
				$this->table,
				$gateway,
				$gateway_subscription_id
			)
		);

		if ( $subscription ) {
			$subscription = $this->hydrate( $subscription );
		}

		return $subscription;
	}

	/**
	 * Get subscriptions by customer.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $customer_id Customer ID.
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_by_customer( $customer_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'  => '',
			'orderby' => 'created_at',
			'order'   => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( $wpdb->prepare( 'customer_id = %d', $customer_id ) );

		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$subscriptions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_clause} ORDER BY %i %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->table,
				$args['orderby'],
				$args['order']
			)
		);

		return array_map( array( $this, 'hydrate' ), $subscriptions );
	}

	/**
	 * Get all subscriptions.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'  => '',
			'package' => 0,
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 100,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );

		if ( ! empty( $args['status'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}

		if ( ! empty( $args['package'] ) ) {
			$where[] = $wpdb->prepare( 'package_id = %d', $args['package'] );
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$subscriptions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_clause} ORDER BY %i %s LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->table,
				$args['orderby'],
				$args['order'],
				$args['limit'],
				$args['offset']
			)
		);

		return array_map( array( $this, 'hydrate' ), $subscriptions );
	}

	/**
	 * Create a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $customer_id Customer ID.
	 * @param object $package Package object.
	 * @param string $gateway Payment gateway.
	 * @param string $payment_method Payment method ID.
	 * @return array|\WP_Error
	 */
	public function create( $customer_id, $package, $gateway, $payment_method ) {
		global $wpdb;

		// Calculate dates.
		$start_date   = current_time( 'Y-m-d' );
		$trial_end    = null;
		$period_start = $start_date;

		if ( $package->trial_days > 0 ) {
			$trial_end    = gmdate( 'Y-m-d', strtotime( "+{$package->trial_days} days" ) );
			$period_start = $trial_end;
		}

		$period_end       = $this->calculate_period_end( $period_start, $package );
		$next_billing     = $period_end;

		// Insert subscription.
		$data = array(
			'customer_id'          => $customer_id,
			'package_id'           => $package->id,
			'gateway'              => $gateway,
			'status'               => 'pending',
			'start_date'           => $start_date,
			'current_period_start' => $period_start,
			'current_period_end'   => $period_end,
			'next_billing_date'    => $next_billing,
			'trial_end_date'       => $trial_end,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			$data,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create subscription.', 'bkx-bulk-recurring-payments' ) );
		}

		$subscription_id = $wpdb->insert_id;

		// Create gateway subscription.
		$gateway_result = $this->create_gateway_subscription( $subscription_id, $package, $gateway, $payment_method );

		if ( is_wp_error( $gateway_result ) ) {
			// Delete the local subscription.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete( $this->table, array( 'id' => $subscription_id ), array( '%d' ) );
			return $gateway_result;
		}

		// Update with gateway ID.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array(
				'gateway_subscription_id' => $gateway_result['subscription_id'],
				'status'                  => 'active',
			),
			array( 'id' => $subscription_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Increment package purchase count.
		$package_manager = new PackageManager( $this->settings );
		$package_manager->increment_purchase_count( $package->id );

		/**
		 * Fires after a subscription is created.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $subscription_id Subscription ID.
		 * @param int    $customer_id     Customer ID.
		 * @param object $package         Package object.
		 */
		do_action( 'bkx_subscription_created', $subscription_id, $customer_id, $package );

		return array(
			'subscription_id' => $subscription_id,
			'redirect_url'    => $gateway_result['redirect_url'] ?? null,
		);
	}

	/**
	 * Create gateway subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $subscription_id Local subscription ID.
	 * @param object $package Package object.
	 * @param string $gateway Gateway name.
	 * @param string $payment_method Payment method ID.
	 * @return array|\WP_Error
	 */
	private function create_gateway_subscription( $subscription_id, $package, $gateway, $payment_method ) {
		/**
		 * Filter to create gateway subscription.
		 *
		 * @since 1.0.0
		 *
		 * @param array|\WP_Error $result          Gateway result.
		 * @param int             $subscription_id Local subscription ID.
		 * @param object          $package         Package object.
		 * @param string          $payment_method  Payment method ID.
		 */
		$result = apply_filters(
			"bkx_create_{$gateway}_subscription",
			new \WP_Error( 'not_implemented', __( 'Gateway not configured.', 'bkx-bulk-recurring-payments' ) ),
			$subscription_id,
			$package,
			$payment_method
		);

		return $result;
	}

	/**
	 * Cancel a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $id Subscription ID.
	 * @param string $reason Cancellation reason.
	 * @param bool   $immediate Cancel immediately vs at period end.
	 * @return bool|\WP_Error
	 */
	public function cancel( $id, $reason = '', $immediate = false ) {
		global $wpdb;

		$subscription = $this->get( $id );

		if ( ! $subscription ) {
			return new \WP_Error( 'not_found', __( 'Subscription not found.', 'bkx-bulk-recurring-payments' ) );
		}

		if ( in_array( $subscription->status, array( 'cancelled', 'expired' ), true ) ) {
			return new \WP_Error( 'already_cancelled', __( 'Subscription is already cancelled.', 'bkx-bulk-recurring-payments' ) );
		}

		// Cancel at gateway.
		$gateway_result = $this->cancel_gateway_subscription( $subscription, $immediate );

		if ( is_wp_error( $gateway_result ) ) {
			return $gateway_result;
		}

		// Update local subscription.
		$update_data = array(
			'cancelled_at'  => current_time( 'mysql' ),
			'cancel_reason' => $reason,
		);

		if ( $immediate ) {
			$update_data['status'] = 'cancelled';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			$update_data,
			array( 'id' => $id ),
			null,
			array( '%d' )
		);

		/**
		 * Fires after a subscription is cancelled.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $id          Subscription ID.
		 * @param object $subscription Subscription object.
		 * @param bool   $immediate   Whether cancelled immediately.
		 */
		do_action( 'bkx_subscription_cancelled', $id, $subscription, $immediate );

		return true;
	}

	/**
	 * Cancel gateway subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param object $subscription Subscription object.
	 * @param bool   $immediate Cancel immediately.
	 * @return bool|\WP_Error
	 */
	private function cancel_gateway_subscription( $subscription, $immediate ) {
		/**
		 * Filter to cancel gateway subscription.
		 *
		 * @since 1.0.0
		 *
		 * @param bool|\WP_Error $result       Gateway result.
		 * @param object         $subscription Subscription object.
		 * @param bool           $immediate    Cancel immediately.
		 */
		return apply_filters(
			"bkx_cancel_{$subscription->gateway}_subscription",
			true,
			$subscription,
			$immediate
		);
	}

	/**
	 * Pause a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Subscription ID.
	 * @return bool|\WP_Error
	 */
	public function pause( $id ) {
		global $wpdb;

		$subscription = $this->get( $id );

		if ( ! $subscription ) {
			return new \WP_Error( 'not_found', __( 'Subscription not found.', 'bkx-bulk-recurring-payments' ) );
		}

		if ( 'active' !== $subscription->status ) {
			return new \WP_Error( 'invalid_status', __( 'Only active subscriptions can be paused.', 'bkx-bulk-recurring-payments' ) );
		}

		// Pause at gateway.
		$gateway_result = apply_filters(
			"bkx_pause_{$subscription->gateway}_subscription",
			true,
			$subscription
		);

		if ( is_wp_error( $gateway_result ) ) {
			return $gateway_result;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array(
				'status'    => 'paused',
				'meta_data' => wp_json_encode(
					array_merge(
						$subscription->meta_data ?? array(),
						array( 'paused_at' => current_time( 'mysql' ) )
					)
				),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		/**
		 * Fires after a subscription is paused.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $id           Subscription ID.
		 * @param object $subscription Subscription object.
		 */
		do_action( 'bkx_subscription_paused', $id, $subscription );

		return true;
	}

	/**
	 * Resume a subscription.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Subscription ID.
	 * @return bool|\WP_Error
	 */
	public function resume( $id ) {
		global $wpdb;

		$subscription = $this->get( $id );

		if ( ! $subscription ) {
			return new \WP_Error( 'not_found', __( 'Subscription not found.', 'bkx-bulk-recurring-payments' ) );
		}

		if ( 'paused' !== $subscription->status ) {
			return new \WP_Error( 'invalid_status', __( 'Only paused subscriptions can be resumed.', 'bkx-bulk-recurring-payments' ) );
		}

		// Check pause limit.
		$pause_limit = $this->settings['pause_subscription_limit_days'] ?? 30;
		$paused_at   = $subscription->meta_data['paused_at'] ?? null;

		if ( $paused_at ) {
			$paused_days = ( strtotime( current_time( 'mysql' ) ) - strtotime( $paused_at ) ) / DAY_IN_SECONDS;

			if ( $paused_days > $pause_limit ) {
				return new \WP_Error(
					'pause_limit_exceeded',
					sprintf(
						/* translators: %d: days limit */
						__( 'Subscription cannot be resumed after %d days. Please create a new subscription.', 'bkx-bulk-recurring-payments' ),
						$pause_limit
					)
				);
			}
		}

		// Resume at gateway.
		$gateway_result = apply_filters(
			"bkx_resume_{$subscription->gateway}_subscription",
			true,
			$subscription
		);

		if ( is_wp_error( $gateway_result ) ) {
			return $gateway_result;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array( 'status' => 'active' ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		/**
		 * Fires after a subscription is resumed.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $id           Subscription ID.
		 * @param object $subscription Subscription object.
		 */
		do_action( 'bkx_subscription_resumed', $id, $subscription );

		return true;
	}

	/**
	 * Renew a subscription period.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Subscription ID.
	 * @return bool|\WP_Error
	 */
	public function renew( $id ) {
		global $wpdb;

		$subscription = $this->get( $id );

		if ( ! $subscription ) {
			return new \WP_Error( 'not_found', __( 'Subscription not found.', 'bkx-bulk-recurring-payments' ) );
		}

		$package = ( new PackageManager( $this->settings ) )->get( $subscription->package_id );

		if ( ! $package ) {
			return new \WP_Error( 'package_not_found', __( 'Package not found.', 'bkx-bulk-recurring-payments' ) );
		}

		// Calculate new period.
		$new_period_start = $subscription->current_period_end;
		$new_period_end   = $this->calculate_period_end( $new_period_start, $package );

		// Check billing cycle limit.
		$cycles_completed = $subscription->billing_cycles_completed + 1;

		if ( $package->billing_cycles > 0 && $cycles_completed >= $package->billing_cycles ) {
			// Subscription complete.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->table,
				array( 'status' => 'expired' ),
				array( 'id' => $id ),
				array( '%s' ),
				array( '%d' )
			);

			/**
			 * Fires after a subscription expires.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $id           Subscription ID.
			 * @param object $subscription Subscription object.
			 */
			do_action( 'bkx_subscription_expired', $id, $subscription );

			return true;
		}

		// Update subscription.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array(
				'current_period_start'     => $new_period_start,
				'current_period_end'       => $new_period_end,
				'next_billing_date'        => $new_period_end,
				'billing_cycles_completed' => $cycles_completed,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		/**
		 * Fires after a subscription is renewed.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $id           Subscription ID.
		 * @param object $subscription Subscription object.
		 */
		do_action( 'bkx_subscription_renewed', $id, $subscription );

		return true;
	}

	/**
	 * Check renewals due today.
	 *
	 * @since 1.0.0
	 */
	public function check_renewals() {
		global $wpdb;

		$today = current_time( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$due_subscriptions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE status = 'active' AND next_billing_date <= %s",
				$this->table,
				$today
			)
		);

		foreach ( $due_subscriptions as $sub ) {
			/**
			 * Fires when a subscription renewal is due.
			 *
			 * @since 1.0.0
			 *
			 * @param int $subscription_id Subscription ID.
			 */
			do_action( 'bkx_subscription_renewal_due', $sub->id );
		}
	}

	/**
	 * Handle gateway activation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $gateway Gateway name.
	 * @param object $event Gateway event.
	 */
	public function handle_gateway_activation( $gateway, $event ) {
		$gateway_id = $this->extract_gateway_subscription_id( $gateway, $event );

		if ( ! $gateway_id ) {
			return;
		}

		$subscription = $this->get_by_gateway_id( $gateway, $gateway_id );

		if ( ! $subscription ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array( 'status' => 'active' ),
			array( 'id' => $subscription->id ),
			array( '%s' ),
			array( '%d' )
		);

		/**
		 * Fires after a subscription is activated by gateway.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $subscription_id Subscription ID.
		 * @param object $subscription    Subscription object.
		 * @param string $gateway         Gateway name.
		 */
		do_action( 'bkx_subscription_gateway_activated', $subscription->id, $subscription, $gateway );
	}

	/**
	 * Handle gateway cancellation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $gateway Gateway name.
	 * @param object $event Gateway event.
	 */
	public function handle_gateway_cancellation( $gateway, $event ) {
		$gateway_id = $this->extract_gateway_subscription_id( $gateway, $event );

		if ( ! $gateway_id ) {
			return;
		}

		$subscription = $this->get_by_gateway_id( $gateway, $gateway_id );

		if ( ! $subscription ) {
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->table,
			array(
				'status'       => 'cancelled',
				'cancelled_at' => current_time( 'mysql' ),
			),
			array( 'id' => $subscription->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		/**
		 * Fires after a subscription is cancelled by gateway.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $subscription_id Subscription ID.
		 * @param object $subscription    Subscription object.
		 * @param string $gateway         Gateway name.
		 */
		do_action( 'bkx_subscription_gateway_cancelled', $subscription->id, $subscription, $gateway );
	}

	/**
	 * Extract gateway subscription ID from event.
	 *
	 * @since 1.0.0
	 *
	 * @param string $gateway Gateway name.
	 * @param object $event Gateway event.
	 * @return string|null
	 */
	private function extract_gateway_subscription_id( $gateway, $event ) {
		switch ( $gateway ) {
			case 'stripe':
				return $event->data->object->id ?? null;

			case 'paypal':
				return $event->resource->id ?? null;

			default:
				return apply_filters( "bkx_extract_{$gateway}_subscription_id", null, $event );
		}
	}

	/**
	 * Calculate period end date.
	 *
	 * @since 1.0.0
	 *
	 * @param string $start_date Start date.
	 * @param object $package Package object.
	 * @return string
	 */
	private function calculate_period_end( $start_date, $package ) {
		$interval = $package->interval_count . ' ' . $package->interval_type;
		return gmdate( 'Y-m-d', strtotime( $start_date . " +{$interval}" ) );
	}

	/**
	 * Hydrate subscription object.
	 *
	 * @since 1.0.0
	 *
	 * @param object $subscription Raw subscription data.
	 * @return object
	 */
	private function hydrate( $subscription ) {
		$subscription->meta_data = json_decode( $subscription->meta_data, true ) ?: array();

		// Load package.
		$subscription->package = ( new PackageManager( $this->settings ) )->get( $subscription->package_id );

		// Load customer.
		$subscription->customer = get_userdata( $subscription->customer_id );

		// Add status labels.
		$subscription->status_label = $this->get_status_label( $subscription->status );

		return $subscription;
	}

	/**
	 * Get status label.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'pending'   => __( 'Pending', 'bkx-bulk-recurring-payments' ),
			'active'    => __( 'Active', 'bkx-bulk-recurring-payments' ),
			'paused'    => __( 'Paused', 'bkx-bulk-recurring-payments' ),
			'cancelled' => __( 'Cancelled', 'bkx-bulk-recurring-payments' ),
			'expired'   => __( 'Expired', 'bkx-bulk-recurring-payments' ),
			'failed'    => __( 'Failed', 'bkx-bulk-recurring-payments' ),
		);

		return $labels[ $status ] ?? ucfirst( $status );
	}
}
