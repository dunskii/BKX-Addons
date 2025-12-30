<?php
/**
 * Webhook Manager Service for IFTTT Integration.
 *
 * Manages webhook subscriptions and delivery.
 *
 * @package BookingX\IFTTT\Services
 */

namespace BookingX\IFTTT\Services;

defined( 'ABSPATH' ) || exit;

/**
 * WebhookManager class.
 */
class WebhookManager {

	/**
	 * Parent addon instance.
	 *
	 * @var \BookingX\IFTTT\IFTTTAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @param \BookingX\IFTTT\IFTTTAddon $addon Parent addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Get all webhooks.
	 *
	 * @return array
	 */
	public function get_webhooks() {
		return $this->addon->get_setting( 'webhooks', array() );
	}

	/**
	 * Get a specific webhook.
	 *
	 * @param string $webhook_id Webhook ID.
	 * @return array|null
	 */
	public function get_webhook( $webhook_id ) {
		$webhooks = $this->get_webhooks();
		return $webhooks[ $webhook_id ] ?? null;
	}

	/**
	 * Get webhooks for a specific trigger.
	 *
	 * @param string $trigger_slug Trigger slug.
	 * @return array
	 */
	public function get_webhooks_for_trigger( $trigger_slug ) {
		$webhooks = $this->get_webhooks();
		$result   = array();

		foreach ( $webhooks as $id => $webhook ) {
			if ( $webhook['trigger'] === $trigger_slug && $webhook['active'] ) {
				$webhook['id'] = $id;
				$result[]      = $webhook;
			}
		}

		return $result;
	}

	/**
	 * Register a new webhook.
	 *
	 * @param array $data Webhook data.
	 * @return array Result with webhook ID or error.
	 */
	public function register_webhook( $data ) {
		// Validate required fields.
		if ( empty( $data['url'] ) || empty( $data['trigger'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'URL and trigger are required.', 'bkx-ifttt' ),
			);
		}

		// Validate URL.
		$url = esc_url_raw( $data['url'] );
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid webhook URL.', 'bkx-ifttt' ),
			);
		}

		// Validate trigger exists.
		$trigger_handler = $this->addon->get_service( 'trigger_handler' );
		if ( ! $trigger_handler || ! $trigger_handler->get_trigger( $data['trigger'] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid trigger.', 'bkx-ifttt' ),
			);
		}

		// Generate webhook ID.
		$webhook_id = wp_generate_uuid4();

		// Create webhook record.
		$webhook = array(
			'url'        => $url,
			'trigger'    => sanitize_text_field( $data['trigger'] ),
			'active'     => true,
			'secret'     => wp_generate_password( 32, false ),
			'created_at' => current_time( 'mysql' ),
			'last_sent'  => null,
			'send_count' => 0,
			'fail_count' => 0,
		);

		// Save webhook.
		$webhooks               = $this->get_webhooks();
		$webhooks[ $webhook_id ] = $webhook;
		$this->save_webhooks( $webhooks );

		return array(
			'success' => true,
			'data'    => array(
				'id'     => $webhook_id,
				'secret' => $webhook['secret'],
			),
		);
	}

	/**
	 * Update a webhook.
	 *
	 * @param string $webhook_id Webhook ID.
	 * @param array  $data       Webhook data.
	 * @return array Result.
	 */
	public function update_webhook( $webhook_id, $data ) {
		$webhooks = $this->get_webhooks();

		if ( ! isset( $webhooks[ $webhook_id ] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Webhook not found.', 'bkx-ifttt' ),
			);
		}

		$webhook = $webhooks[ $webhook_id ];

		// Update URL if provided.
		if ( ! empty( $data['url'] ) ) {
			$url = esc_url_raw( $data['url'] );
			if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$webhook['url'] = $url;
			}
		}

		// Update trigger if provided.
		if ( ! empty( $data['trigger'] ) ) {
			$trigger_handler = $this->addon->get_service( 'trigger_handler' );
			if ( $trigger_handler && $trigger_handler->get_trigger( $data['trigger'] ) ) {
				$webhook['trigger'] = sanitize_text_field( $data['trigger'] );
			}
		}

		// Update active status if provided.
		if ( isset( $data['active'] ) ) {
			$webhook['active'] = (bool) $data['active'];
		}

		$webhooks[ $webhook_id ] = $webhook;
		$this->save_webhooks( $webhooks );

		return array(
			'success' => true,
			'data'    => array( 'id' => $webhook_id ),
		);
	}

	/**
	 * Delete a webhook.
	 *
	 * @param string $webhook_id Webhook ID.
	 * @return array Result.
	 */
	public function delete_webhook( $webhook_id ) {
		$webhooks = $this->get_webhooks();

		if ( ! isset( $webhooks[ $webhook_id ] ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Webhook not found.', 'bkx-ifttt' ),
			);
		}

		unset( $webhooks[ $webhook_id ] );
		$this->save_webhooks( $webhooks );

		return array(
			'success' => true,
		);
	}

	/**
	 * Send webhook notification.
	 *
	 * @param array $webhook Webhook configuration.
	 * @param array $payload Payload data.
	 * @return bool Success status.
	 */
	public function send_webhook( $webhook, $payload ) {
		$url = $webhook['url'];

		// Generate signature.
		$signature = $this->generate_signature( $payload, $webhook['secret'] ?? '' );

		// Build headers.
		$headers = array(
			'Content-Type'            => 'application/json',
			'X-BKX-IFTTT-Signature'   => $signature,
			'X-BKX-IFTTT-Timestamp'   => time(),
			'X-BKX-IFTTT-Event'       => $webhook['trigger'],
		);

		// Send request.
		$response = wp_remote_post(
			$url,
			array(
				'timeout'     => 15,
				'headers'     => $headers,
				'body'        => wp_json_encode( $payload ),
				'data_format' => 'body',
			)
		);

		$success = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) < 400;

		// Update webhook stats.
		$this->update_webhook_stats( $webhook['id'], $success );

		// Log delivery.
		$this->log_delivery( $webhook, $payload, $response, $success );

		return $success;
	}

	/**
	 * Generate HMAC signature for payload.
	 *
	 * @param array  $payload Payload data.
	 * @param string $secret  Webhook secret.
	 * @return string
	 */
	private function generate_signature( $payload, $secret ) {
		$data = wp_json_encode( $payload );
		return hash_hmac( 'sha256', $data, $secret );
	}

	/**
	 * Verify incoming webhook signature.
	 *
	 * @param string $payload   Raw payload.
	 * @param string $signature Provided signature.
	 * @param string $secret    Webhook secret.
	 * @return bool
	 */
	public function verify_signature( $payload, $signature, $secret ) {
		$expected = hash_hmac( 'sha256', $payload, $secret );
		return hash_equals( $expected, $signature );
	}

	/**
	 * Update webhook statistics.
	 *
	 * @param string $webhook_id Webhook ID.
	 * @param bool   $success    Whether delivery succeeded.
	 */
	private function update_webhook_stats( $webhook_id, $success ) {
		$webhooks = $this->get_webhooks();

		if ( ! isset( $webhooks[ $webhook_id ] ) ) {
			return;
		}

		$webhooks[ $webhook_id ]['last_sent']  = current_time( 'mysql' );
		$webhooks[ $webhook_id ]['send_count'] = ( $webhooks[ $webhook_id ]['send_count'] ?? 0 ) + 1;

		if ( ! $success ) {
			$webhooks[ $webhook_id ]['fail_count'] = ( $webhooks[ $webhook_id ]['fail_count'] ?? 0 ) + 1;
		}

		$this->save_webhooks( $webhooks );
	}

	/**
	 * Log webhook delivery.
	 *
	 * @param array                $webhook  Webhook configuration.
	 * @param array                $payload  Payload data.
	 * @param array|\WP_Error      $response Response or error.
	 * @param bool                 $success  Whether delivery succeeded.
	 */
	private function log_delivery( $webhook, $payload, $response, $success ) {
		if ( ! $this->addon->get_setting( 'log_requests', false ) ) {
			return;
		}

		$logs = get_option( 'bkx_ifttt_webhook_logs', array() );

		$log_entry = array(
			'timestamp'  => current_time( 'mysql' ),
			'webhook_id' => $webhook['id'],
			'trigger'    => $webhook['trigger'],
			'url'        => $webhook['url'],
			'success'    => $success,
		);

		if ( is_wp_error( $response ) ) {
			$log_entry['error'] = $response->get_error_message();
		} else {
			$log_entry['response_code'] = wp_remote_retrieve_response_code( $response );
		}

		$logs[] = $log_entry;

		// Keep only last 200 logs.
		$logs = array_slice( $logs, -200 );

		update_option( 'bkx_ifttt_webhook_logs', $logs );
	}

	/**
	 * Save webhooks to settings.
	 *
	 * @param array $webhooks Webhooks array.
	 */
	private function save_webhooks( $webhooks ) {
		$settings             = get_option( 'bkx_ifttt_settings', array() );
		$settings['webhooks'] = $webhooks;
		update_option( 'bkx_ifttt_settings', $settings );
	}

	/**
	 * Test a webhook by sending sample data.
	 *
	 * @param string $webhook_id Webhook ID.
	 * @return array Result.
	 */
	public function test_webhook( $webhook_id ) {
		$webhook = $this->get_webhook( $webhook_id );

		if ( ! $webhook ) {
			return array(
				'success' => false,
				'error'   => __( 'Webhook not found.', 'bkx-ifttt' ),
			);
		}

		// Get sample data for the trigger.
		$trigger_handler = $this->addon->get_service( 'trigger_handler' );
		if ( ! $trigger_handler ) {
			return array(
				'success' => false,
				'error'   => __( 'Trigger handler not available.', 'bkx-ifttt' ),
			);
		}

		$sample_data         = $trigger_handler->get_sample_data( $webhook['trigger'] );
		$sample_data['test'] = true;

		$webhook['id'] = $webhook_id;
		$success       = $this->send_webhook( $webhook, $sample_data );

		return array(
			'success' => $success,
			'data'    => array(
				'webhook_id' => $webhook_id,
				'trigger'    => $webhook['trigger'],
			),
		);
	}

	/**
	 * Get webhook delivery logs.
	 *
	 * @param string|null $webhook_id Optional webhook ID to filter by.
	 * @param int         $limit      Number of logs to return.
	 * @return array
	 */
	public function get_delivery_logs( $webhook_id = null, $limit = 50 ) {
		$logs = get_option( 'bkx_ifttt_webhook_logs', array() );

		if ( $webhook_id ) {
			$logs = array_filter(
				$logs,
				function ( $log ) use ( $webhook_id ) {
					return $log['webhook_id'] === $webhook_id;
				}
			);
		}

		// Return most recent first.
		$logs = array_reverse( $logs );

		return array_slice( $logs, 0, $limit );
	}

	/**
	 * Get webhook statistics.
	 *
	 * @return array
	 */
	public function get_stats() {
		$webhooks    = $this->get_webhooks();
		$total       = count( $webhooks );
		$active      = 0;
		$total_sent  = 0;
		$total_fails = 0;

		foreach ( $webhooks as $webhook ) {
			if ( $webhook['active'] ) {
				++$active;
			}
			$total_sent  += $webhook['send_count'] ?? 0;
			$total_fails += $webhook['fail_count'] ?? 0;
		}

		return array(
			'total_webhooks'    => $total,
			'active_webhooks'   => $active,
			'total_sent'        => $total_sent,
			'total_failures'    => $total_fails,
			'success_rate'      => $total_sent > 0 ? round( ( ( $total_sent - $total_fails ) / $total_sent ) * 100, 2 ) : 100,
		);
	}

	/**
	 * Cleanup old logs.
	 */
	public function cleanup_logs() {
		$logs = get_option( 'bkx_ifttt_webhook_logs', array() );

		// Keep only logs from last 30 days.
		$cutoff = strtotime( '-30 days' );
		$logs   = array_filter(
			$logs,
			function ( $log ) use ( $cutoff ) {
				return strtotime( $log['timestamp'] ) > $cutoff;
			}
		);

		update_option( 'bkx_ifttt_webhook_logs', array_values( $logs ) );
	}
}
