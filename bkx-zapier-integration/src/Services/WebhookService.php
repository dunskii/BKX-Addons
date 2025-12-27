<?php
/**
 * Webhook Service for Zapier Integration
 *
 * @package BookingX\ZapierIntegration
 * @since   1.0.0
 */

namespace BookingX\ZapierIntegration\Services;

use BookingX\ZapierIntegration\ZapierIntegrationAddon;

/**
 * Class WebhookService
 *
 * Manages webhook subscriptions and delivery.
 *
 * @since 1.0.0
 */
class WebhookService {

	/**
	 * Addon instance.
	 *
	 * @var ZapierIntegrationAddon
	 */
	private ZapierIntegrationAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param ZapierIntegrationAddon $addon Addon instance.
	 */
	public function __construct( ZapierIntegrationAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Subscribe to a trigger.
	 *
	 * @since 1.0.0
	 * @param string $trigger    Trigger name.
	 * @param string $target_url Webhook URL.
	 * @return string|\WP_Error Subscription ID or error.
	 */
	public function subscribe( string $trigger, string $target_url ) {
		$valid_triggers = array(
			'booking_created',
			'booking_updated',
			'booking_status_changed',
			'booking_cancelled',
			'booking_completed',
			'customer_created',
			'payment_received',
		);

		if ( ! in_array( $trigger, $valid_triggers, true ) ) {
			return new \WP_Error(
				'invalid_trigger',
				__( 'Invalid trigger type', 'bkx-zapier-integration' )
			);
		}

		if ( ! filter_var( $target_url, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error(
				'invalid_url',
				__( 'Invalid webhook URL', 'bkx-zapier-integration' )
			);
		}

		$subscription_id = wp_generate_uuid4();

		$subscriptions = $this->addon->get_setting( 'webhook_subscriptions', array() );

		$subscriptions[ $subscription_id ] = array(
			'trigger'    => $trigger,
			'target_url' => $target_url,
			'created_at' => current_time( 'mysql' ),
			'last_sent'  => null,
			'send_count' => 0,
		);

		$this->addon->update_setting( 'webhook_subscriptions', $subscriptions );

		return $subscription_id;
	}

	/**
	 * Unsubscribe from a trigger.
	 *
	 * @since 1.0.0
	 * @param string $subscription_id Subscription ID.
	 * @return bool
	 */
	public function unsubscribe( string $subscription_id ): bool {
		$subscriptions = $this->addon->get_setting( 'webhook_subscriptions', array() );

		if ( ! isset( $subscriptions[ $subscription_id ] ) ) {
			return false;
		}

		unset( $subscriptions[ $subscription_id ] );
		$this->addon->update_setting( 'webhook_subscriptions', $subscriptions );

		return true;
	}

	/**
	 * Get subscriptions for a trigger.
	 *
	 * @since 1.0.0
	 * @param string $trigger Trigger name.
	 * @return array
	 */
	public function get_subscriptions_for_trigger( string $trigger ): array {
		$subscriptions = $this->addon->get_setting( 'webhook_subscriptions', array() );
		$result        = array();

		foreach ( $subscriptions as $id => $sub ) {
			if ( $sub['trigger'] === $trigger ) {
				$result[ $id ] = $sub;
			}
		}

		return $result;
	}

	/**
	 * Send webhook to all subscribers of a trigger.
	 *
	 * @since 1.0.0
	 * @param string $trigger Trigger name.
	 * @param array  $data    Data to send.
	 * @return array Results for each subscription.
	 */
	public function send( string $trigger, array $data ): array {
		$subscriptions = $this->get_subscriptions_for_trigger( $trigger );
		$results       = array();

		foreach ( $subscriptions as $id => $sub ) {
			$result = $this->send_webhook( $id, $sub['target_url'], $data );
			$results[ $id ] = $result;

			// Update subscription stats.
			$this->update_subscription_stats( $id, $result );
		}

		return $results;
	}

	/**
	 * Send a single webhook.
	 *
	 * @since 1.0.0
	 * @param string $subscription_id Subscription ID.
	 * @param string $target_url      Target URL.
	 * @param array  $data            Data to send.
	 * @return bool
	 */
	private function send_webhook( string $subscription_id, string $target_url, array $data ): bool {
		$response = wp_remote_post(
			$target_url,
			array(
				'headers' => array(
					'Content-Type'            => 'application/json',
					'X-BKX-Subscription-ID'   => $subscription_id,
					'X-BKX-Webhook-Timestamp' => time(),
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->addon->log(
				sprintf(
					'Webhook failed to %s: %s',
					$target_url,
					$response->get_error_message()
				),
				'error'
			);

			// Schedule retry if enabled.
			if ( $this->addon->get_setting( 'retry_failed', true ) ) {
				$this->schedule_retry( $subscription_id, $target_url, $data );
			}

			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$this->addon->log(
				sprintf(
					'Webhook returned %d from %s',
					$code,
					$target_url
				),
				'warning'
			);
			return false;
		}

		if ( $this->addon->get_setting( 'log_webhooks', true ) ) {
			$this->addon->log( sprintf( 'Webhook sent to %s', $target_url ) );
		}

		return true;
	}

	/**
	 * Update subscription statistics.
	 *
	 * @since 1.0.0
	 * @param string $subscription_id Subscription ID.
	 * @param bool   $success         Whether send was successful.
	 * @return void
	 */
	private function update_subscription_stats( string $subscription_id, bool $success ): void {
		$subscriptions = $this->addon->get_setting( 'webhook_subscriptions', array() );

		if ( isset( $subscriptions[ $subscription_id ] ) ) {
			$subscriptions[ $subscription_id ]['last_sent']  = current_time( 'mysql' );
			$subscriptions[ $subscription_id ]['send_count'] = ( $subscriptions[ $subscription_id ]['send_count'] ?? 0 ) + 1;

			if ( ! $success ) {
				$subscriptions[ $subscription_id ]['last_error']  = current_time( 'mysql' );
				$subscriptions[ $subscription_id ]['error_count'] = ( $subscriptions[ $subscription_id ]['error_count'] ?? 0 ) + 1;
			}

			$this->addon->update_setting( 'webhook_subscriptions', $subscriptions );
		}
	}

	/**
	 * Schedule a retry for a failed webhook.
	 *
	 * @since 1.0.0
	 * @param string $subscription_id Subscription ID.
	 * @param string $target_url      Target URL.
	 * @param array  $data            Data to send.
	 * @param int    $attempt         Current attempt number.
	 * @return void
	 */
	private function schedule_retry( string $subscription_id, string $target_url, array $data, int $attempt = 1 ): void {
		$max_retries = $this->addon->get_setting( 'max_retries', 3 );

		if ( $attempt > $max_retries ) {
			$this->addon->log(
				sprintf(
					'Webhook max retries exceeded for %s',
					$target_url
				),
				'error'
			);
			return;
		}

		// Exponential backoff: 1min, 5min, 25min.
		$delay = pow( 5, $attempt - 1 ) * MINUTE_IN_SECONDS;

		wp_schedule_single_event(
			time() + $delay,
			'bkx_zapier_retry_webhook',
			array( $subscription_id, $target_url, $data, $attempt + 1 )
		);
	}

	/**
	 * Get all subscriptions.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_all_subscriptions(): array {
		return $this->addon->get_setting( 'webhook_subscriptions', array() );
	}

	/**
	 * Clear all subscriptions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function clear_all(): void {
		$this->addon->update_setting( 'webhook_subscriptions', array() );
	}
}
