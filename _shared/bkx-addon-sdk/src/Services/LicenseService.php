<?php
/**
 * License Service
 *
 * Handles license validation and management for add-ons.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Services
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Services;

use BookingX\AddonSDK\Contracts\AddonInterface;

/**
 * License management service.
 *
 * @since 1.0.0
 */
class LicenseService {

    /**
     * The add-on instance.
     *
     * @var AddonInterface
     */
    protected AddonInterface $addon;

    /**
     * License server URL.
     *
     * @var string
     */
    protected string $license_server = 'https://bookingx.com';

    /**
     * Trial period in days.
     *
     * @var int
     */
    protected int $trial_days = 14;

    /**
     * Cache duration for license checks.
     *
     * @var int
     */
    protected int $cache_duration = DAY_IN_SECONDS;

    /**
     * Constructor.
     *
     * @param AddonInterface $addon The add-on instance.
     */
    public function __construct( AddonInterface $addon ) {
        $this->addon = $addon;
    }

    /**
     * Get the license key.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_key(): string {
        $addon_info = $this->addon->get_addon_info();
        return get_option( $addon_info['key_name'], '' );
    }

    /**
     * Get the license status.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_status(): string {
        $addon_info = $this->addon->get_addon_info();
        return get_option( $addon_info['status'], '' );
    }

    /**
     * Check if the license is valid.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_valid(): bool {
        $status = $this->get_status();
        return 'valid' === $status;
    }

    /**
     * Check if in trial mode.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_in_trial(): bool {
        $status = $this->get_status();

        if ( 'trial' !== $status ) {
            return false;
        }

        return $this->get_trial_days_remaining() > 0;
    }

    /**
     * Get trial days remaining.
     *
     * @since 1.0.0
     * @return int
     */
    public function get_trial_days_remaining(): int {
        $addon_id    = $this->addon->get_id();
        $trial_start = get_option( "{$addon_id}_trial_start", 0 );

        if ( ! $trial_start ) {
            return 0;
        }

        $trial_end = $trial_start + ( $this->trial_days * DAY_IN_SECONDS );
        $remaining = $trial_end - time();

        return max( 0, (int) ceil( $remaining / DAY_IN_SECONDS ) );
    }

    /**
     * Start trial.
     *
     * @since 1.0.0
     * @return bool
     */
    public function start_trial(): bool {
        $addon_id    = $this->addon->get_id();
        $addon_info  = $this->addon->get_addon_info();
        $trial_start = get_option( "{$addon_id}_trial_start", 0 );

        // Don't restart trial
        if ( $trial_start ) {
            return false;
        }

        update_option( "{$addon_id}_trial_start", time() );
        update_option( $addon_info['status'], 'trial' );

        do_action( "bkx_{$addon_id}_trial_started" );

        return true;
    }

    /**
     * Activate license.
     *
     * @since 1.0.0
     * @param string $license_key License key.
     * @return array Result.
     */
    public function activate( string $license_key ): array {
        $license_key = trim( $license_key );

        if ( empty( $license_key ) ) {
            return [
                'success' => false,
                'message' => __( 'Please enter a license key.', 'bkx-addon-sdk' ),
            ];
        }

        $response = $this->api_request( 'activate_license', [
            'license'   => $license_key,
            'item_name' => $this->addon->get_name(),
            'url'       => home_url(),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $addon_info = $this->addon->get_addon_info();

        if ( 'valid' === ( $response['license'] ?? '' ) ) {
            update_option( $addon_info['key_name'], $license_key );
            update_option( $addon_info['status'], 'valid' );
            delete_option( $addon_info['status'] . '_errors' );

            // Clear cache
            $this->clear_cache();

            do_action( "bkx_{$this->addon->get_id()}_license_activated", $license_key );

            return [
                'success'        => true,
                'message'        => __( 'License activated successfully!', 'bkx-addon-sdk' ),
                'expires'        => $response['expires'] ?? null,
                'customer_name'  => $response['customer_name'] ?? null,
                'customer_email' => $response['customer_email'] ?? null,
            ];
        }

        $error = $response['error'] ?? 'unknown';
        $message = $this->get_error_message( $error );

        update_option( $addon_info['status'] . '_errors', $message );

        return [
            'success' => false,
            'message' => $message,
            'error'   => $error,
        ];
    }

    /**
     * Deactivate license.
     *
     * @since 1.0.0
     * @return array Result.
     */
    public function deactivate(): array {
        $license_key = $this->get_key();

        if ( empty( $license_key ) ) {
            return [
                'success' => false,
                'message' => __( 'No license key to deactivate.', 'bkx-addon-sdk' ),
            ];
        }

        $response = $this->api_request( 'deactivate_license', [
            'license'   => $license_key,
            'item_name' => $this->addon->get_name(),
            'url'       => home_url(),
        ] );

        $addon_info = $this->addon->get_addon_info();

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ( 'deactivated' === ( $response['license'] ?? '' ) ) {
            delete_option( $addon_info['key_name'] );
            update_option( $addon_info['status'], '' );
            delete_option( $addon_info['status'] . '_errors' );

            // Clear cache
            $this->clear_cache();

            do_action( "bkx_{$this->addon->get_id()}_license_deactivated" );

            return [
                'success' => true,
                'message' => __( 'License deactivated successfully.', 'bkx-addon-sdk' ),
            ];
        }

        return [
            'success' => false,
            'message' => __( 'Failed to deactivate license.', 'bkx-addon-sdk' ),
        ];
    }

    /**
     * Check license status with server.
     *
     * @since 1.0.0
     * @param bool $force Force check (ignore cache).
     * @return array License data.
     */
    public function check( bool $force = false ): array {
        $addon_id = $this->addon->get_id();
        $cache_key = "bkx_{$addon_id}_license_check";

        // Check cache
        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $license_key = $this->get_key();

        if ( empty( $license_key ) ) {
            $result = [
                'success' => false,
                'status'  => 'no_license',
                'message' => __( 'No license key found.', 'bkx-addon-sdk' ),
            ];

            set_transient( $cache_key, $result, $this->cache_duration );
            return $result;
        }

        $response = $this->api_request( 'check_license', [
            'license'   => $license_key,
            'item_name' => $this->addon->get_name(),
            'url'       => home_url(),
        ] );

        if ( is_wp_error( $response ) ) {
            $result = [
                'success' => false,
                'status'  => 'error',
                'message' => $response->get_error_message(),
            ];

            // Cache errors for shorter time
            set_transient( $cache_key, $result, HOUR_IN_SECONDS );
            return $result;
        }

        $status     = $response['license'] ?? 'invalid';
        $addon_info = $this->addon->get_addon_info();

        update_option( $addon_info['status'], $status );

        $result = [
            'success'          => 'valid' === $status,
            'status'           => $status,
            'expires'          => $response['expires'] ?? null,
            'activations_left' => $response['activations_left'] ?? null,
            'customer_name'    => $response['customer_name'] ?? null,
            'customer_email'   => $response['customer_email'] ?? null,
            'payment_id'       => $response['payment_id'] ?? null,
            'license_limit'    => $response['license_limit'] ?? null,
            'site_count'       => $response['site_count'] ?? null,
        ];

        set_transient( $cache_key, $result, $this->cache_duration );

        return $result;
    }

    /**
     * Clear license cache.
     *
     * @since 1.0.0
     * @return void
     */
    public function clear_cache(): void {
        $addon_id = $this->addon->get_id();
        delete_transient( "bkx_{$addon_id}_license_check" );
    }

    /**
     * Make API request to license server.
     *
     * @since 1.0.0
     * @param string $action Action to perform.
     * @param array  $params Request parameters.
     * @return array|\WP_Error Response or error.
     */
    protected function api_request( string $action, array $params ) {
        $params['edd_action'] = $action;

        $response = wp_remote_post( $this->license_server, [
            'timeout'   => 15,
            'sslverify' => true,
            'body'      => $params,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code !== 200 ) {
            return new \WP_Error(
                'http_error',
                sprintf( __( 'License server returned error: %d', 'bkx-addon-sdk' ), $code )
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'json_error', __( 'Invalid response from license server.', 'bkx-addon-sdk' ) );
        }

        return $data;
    }

    /**
     * Get error message for error code.
     *
     * @since 1.0.0
     * @param string $error Error code.
     * @return string Error message.
     */
    protected function get_error_message( string $error ): string {
        $messages = [
            'expired'             => __( 'Your license key has expired.', 'bkx-addon-sdk' ),
            'disabled'            => __( 'Your license key has been disabled.', 'bkx-addon-sdk' ),
            'missing'             => __( 'Invalid license key.', 'bkx-addon-sdk' ),
            'invalid'             => __( 'Your license key is not valid for this product.', 'bkx-addon-sdk' ),
            'site_inactive'       => __( 'Your license is not active for this site.', 'bkx-addon-sdk' ),
            'item_name_mismatch'  => __( 'This license key is for a different product.', 'bkx-addon-sdk' ),
            'no_activations_left' => __( 'Your license key has reached its activation limit.', 'bkx-addon-sdk' ),
        ];

        return $messages[ $error ] ?? __( 'An error occurred. Please try again.', 'bkx-addon-sdk' );
    }

    /**
     * Get license info for display.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_info(): array {
        $info = [
            'key'          => $this->get_key(),
            'status'       => $this->get_status(),
            'is_valid'     => $this->is_valid(),
            'is_trial'     => $this->is_in_trial(),
            'trial_days'   => $this->get_trial_days_remaining(),
            'masked_key'   => '',
        ];

        // Mask license key for display
        if ( $info['key'] ) {
            $info['masked_key'] = substr( $info['key'], 0, 4 ) . str_repeat( '*', 20 ) . substr( $info['key'], -4 );
        }

        return $info;
    }
}
