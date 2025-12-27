<?php
/**
 * Webhooks Trait
 *
 * Provides webhook handling functionality for add-ons.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Traits
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Traits;

/**
 * Trait for webhook handling.
 *
 * @since 1.0.0
 */
trait HasWebhooks {

    /**
     * Registered webhook handlers.
     *
     * @var array
     */
    protected array $webhook_handlers = [];

    /**
     * Register a webhook handler.
     *
     * @since 1.0.0
     * @param string   $event    Event name.
     * @param callable $callback Callback function.
     * @param int      $priority Priority (default 10).
     * @return void
     */
    protected function register_webhook_handler( string $event, callable $callback, int $priority = 10 ): void {
        $this->webhook_handlers[ $event ][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];

        // Sort by priority
        usort( $this->webhook_handlers[ $event ], function( $a, $b ) {
            return $a['priority'] - $b['priority'];
        } );
    }

    /**
     * Process an incoming webhook.
     *
     * @since 1.0.0
     * @param string $event   Event type.
     * @param array  $payload Webhook payload.
     * @return array Processing results.
     */
    protected function process_webhook( string $event, array $payload ): array {
        $results = [
            'event'    => $event,
            'success'  => true,
            'handlers' => [],
        ];

        // Log the incoming webhook
        $this->log_webhook( $event, $payload, 'received' );

        if ( ! isset( $this->webhook_handlers[ $event ] ) ) {
            $results['message'] = 'No handlers registered for this event.';
            return $results;
        }

        foreach ( $this->webhook_handlers[ $event ] as $handler ) {
            try {
                $handler_result = call_user_func( $handler['callback'], $payload );

                $results['handlers'][] = [
                    'success' => true,
                    'result'  => $handler_result,
                ];
            } catch ( \Exception $e ) {
                $results['success']    = false;
                $results['handlers'][] = [
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];

                $this->log_webhook( $event, $payload, 'error', $e->getMessage() );
            }
        }

        // Log successful processing
        if ( $results['success'] ) {
            $this->log_webhook( $event, $payload, 'processed' );
        }

        return $results;
    }

    /**
     * Verify webhook signature.
     *
     * @since 1.0.0
     * @param string $payload   Raw payload.
     * @param string $signature Signature from header.
     * @param string $secret    Webhook secret.
     * @param string $algorithm Hash algorithm (default sha256).
     * @return bool
     */
    protected function verify_webhook_signature( string $payload, string $signature, string $secret, string $algorithm = 'sha256' ): bool {
        $expected = hash_hmac( $algorithm, $payload, $secret );

        return hash_equals( $expected, $signature );
    }

    /**
     * Verify Stripe webhook signature.
     *
     * @since 1.0.0
     * @param string $payload         Raw payload.
     * @param string $signature_header Stripe signature header.
     * @param string $secret          Webhook secret.
     * @param int    $tolerance       Timestamp tolerance in seconds.
     * @return bool|\WP_Error
     */
    protected function verify_stripe_signature( string $payload, string $signature_header, string $secret, int $tolerance = 300 ) {
        $parts = [];
        foreach ( explode( ',', $signature_header ) as $part ) {
            $pair = explode( '=', $part, 2 );
            if ( count( $pair ) === 2 ) {
                $parts[ $pair[0] ] = $pair[1];
            }
        }

        if ( ! isset( $parts['t'] ) || ! isset( $parts['v1'] ) ) {
            return new \WP_Error( 'invalid_signature', 'Invalid signature format.' );
        }

        $timestamp = (int) $parts['t'];
        $signature = $parts['v1'];

        // Check timestamp
        if ( abs( time() - $timestamp ) > $tolerance ) {
            return new \WP_Error( 'timestamp_expired', 'Webhook timestamp is too old.' );
        }

        // Verify signature
        $signed_payload   = $timestamp . '.' . $payload;
        $expected         = hash_hmac( 'sha256', $signed_payload, $secret );

        if ( ! hash_equals( $expected, $signature ) ) {
            return new \WP_Error( 'signature_mismatch', 'Signature verification failed.' );
        }

        return true;
    }

    /**
     * Log a webhook event.
     *
     * @since 1.0.0
     * @param string $event   Event type.
     * @param array  $payload Webhook payload.
     * @param string $status  Status (received, processed, error).
     * @param string $message Optional message.
     * @return void
     */
    protected function log_webhook( string $event, array $payload, string $status, string $message = '' ): void {
        global $wpdb;

        $table = $wpdb->prefix . 'bkx_webhook_logs';

        // Check if table exists (it's optional)
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            // Fall back to standard logging
            do_action( 'bkx_webhook_log', $this->addon_id ?? '', $event, $status, $payload, $message );
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert( $table, [
            'addon_id'   => $this->addon_id ?? '',
            'event'      => $event,
            'payload'    => wp_json_encode( $payload ),
            'status'     => $status,
            'message'    => $message,
            'ip_address' => $this->get_client_ip(),
            'created_at' => current_time( 'mysql' ),
        ] );
    }

    /**
     * Get client IP address.
     *
     * @since 1.0.0
     * @return string
     */
    protected function get_client_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                // Handle comma-separated list
                if ( strpos( $ip, ',' ) !== false ) {
                    $ips = explode( ',', $ip );
                    $ip  = trim( $ips[0] );
                }
                return $ip;
            }
        }

        return '';
    }

    /**
     * Get raw request body.
     *
     * @since 1.0.0
     * @return string
     */
    protected function get_webhook_payload(): string {
        return file_get_contents( 'php://input' );
    }

    /**
     * Parse webhook payload.
     *
     * @since 1.0.0
     * @param string $raw_payload Raw payload string.
     * @return array|null Parsed payload or null on error.
     */
    protected function parse_webhook_payload( string $raw_payload ): ?array {
        $payload = json_decode( $raw_payload, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return null;
        }

        return $payload;
    }

    /**
     * Send a webhook response.
     *
     * @since 1.0.0
     * @param bool   $success Success status.
     * @param string $message Response message.
     * @param int    $status  HTTP status code.
     * @return void
     */
    protected function send_webhook_response( bool $success, string $message, int $status = 200 ): void {
        status_header( $status );
        header( 'Content-Type: application/json' );

        echo wp_json_encode( [
            'success' => $success,
            'message' => $message,
        ] );

        exit;
    }

    /**
     * Retry a failed webhook.
     *
     * @since 1.0.0
     * @param int   $log_id      Webhook log ID.
     * @param int   $max_retries Maximum retry attempts.
     * @param array $intervals   Retry intervals in seconds.
     * @return bool
     */
    protected function schedule_webhook_retry( int $log_id, int $max_retries = 3, array $intervals = [ 60, 300, 900 ] ): bool {
        $retry_count = (int) get_transient( "bkx_webhook_retry_{$log_id}" );

        if ( $retry_count >= $max_retries ) {
            return false;
        }

        $interval = $intervals[ $retry_count ] ?? end( $intervals );

        wp_schedule_single_event(
            time() + $interval,
            'bkx_webhook_retry',
            [ $log_id, $this->addon_id ?? '' ]
        );

        set_transient( "bkx_webhook_retry_{$log_id}", $retry_count + 1, DAY_IN_SECONDS );

        return true;
    }

    /**
     * Get webhook URL for this addon.
     *
     * @since 1.0.0
     * @param string $endpoint Optional endpoint suffix.
     * @return string
     */
    public function get_webhook_url( string $endpoint = '' ): string {
        $base = rest_url( "bookingx/v1/webhooks/{$this->addon_id}" );

        if ( $endpoint ) {
            $base .= '/' . ltrim( $endpoint, '/' );
        }

        return $base;
    }

    /**
     * Register webhook REST endpoint.
     *
     * @since 1.0.0
     * @return void
     */
    protected function register_webhook_endpoint(): void {
        register_rest_route( 'bookingx/v1', "/webhooks/{$this->addon_id}", [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_webhook_request' ],
            'permission_callback' => '__return_true', // Webhooks handle their own auth
        ] );
    }

    /**
     * Handle incoming webhook request.
     *
     * Override this method in the add-on class to handle webhooks.
     *
     * @since 1.0.0
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_webhook_request( \WP_REST_Request $request ) {
        return new \WP_REST_Response( [
            'success' => true,
            'message' => 'Webhook endpoint active.',
        ] );
    }
}
