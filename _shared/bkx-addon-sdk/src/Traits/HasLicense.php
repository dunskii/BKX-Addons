<?php
/**
 * License Trait
 *
 * Provides license management functionality for add-ons.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Traits
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Traits;

/**
 * Trait for license management.
 *
 * @since 1.0.0
 */
trait HasLicense {

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
     * Get the license key option name.
     *
     * @since 1.0.0
     * @return string
     */
    protected function get_license_key_option(): string {
        return "{$this->addon_id}_license_key";
    }

    /**
     * Get the license status option name.
     *
     * @since 1.0.0
     * @return string
     */
    protected function get_license_status_option(): string {
        return "{$this->addon_id}_license_status";
    }

    /**
     * Get the trial start option name.
     *
     * @since 1.0.0
     * @return string
     */
    protected function get_trial_start_option(): string {
        return "{$this->addon_id}_trial_start";
    }

    /**
     * Get the stored license key.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_license_key(): string {
        return get_option( $this->get_license_key_option(), '' );
    }

    /**
     * Get the license status.
     *
     * @since 1.0.0
     * @return string Status: valid, invalid, expired, disabled, trial.
     */
    public function get_license_status(): string {
        return get_option( $this->get_license_status_option(), '' );
    }

    /**
     * Check if the license is valid.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_license_valid(): bool {
        $status = $this->get_license_status();
        return 'valid' === $status;
    }

    /**
     * Check if in trial mode.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_in_trial(): bool {
        $status = $this->get_license_status();

        if ( 'trial' !== $status ) {
            return false;
        }

        $trial_start = get_option( $this->get_trial_start_option(), 0 );

        if ( ! $trial_start ) {
            return false;
        }

        $trial_end = $trial_start + ( $this->trial_days * DAY_IN_SECONDS );

        return time() < $trial_end;
    }

    /**
     * Get remaining trial days.
     *
     * @since 1.0.0
     * @return int
     */
    public function get_trial_days_remaining(): int {
        if ( ! $this->is_in_trial() ) {
            return 0;
        }

        $trial_start = get_option( $this->get_trial_start_option(), 0 );
        $trial_end   = $trial_start + ( $this->trial_days * DAY_IN_SECONDS );
        $remaining   = $trial_end - time();

        return max( 0, (int) ceil( $remaining / DAY_IN_SECONDS ) );
    }

    /**
     * Start trial period.
     *
     * @since 1.0.0
     * @return bool
     */
    public function start_trial(): bool {
        $trial_start = get_option( $this->get_trial_start_option(), 0 );

        // Don't allow restarting trial
        if ( $trial_start ) {
            return false;
        }

        update_option( $this->get_trial_start_option(), time() );
        update_option( $this->get_license_status_option(), 'trial' );

        do_action( "bkx_{$this->addon_id}_trial_started" );

        return true;
    }

    /**
     * Activate a license key.
     *
     * @since 1.0.0
     * @param string $license_key License key to activate.
     * @return array Result with 'success' bool and 'message'.
     */
    public function activate_license( string $license_key ): array {
        $license_key = trim( $license_key );

        if ( empty( $license_key ) ) {
            return [
                'success' => false,
                'message' => __( 'Please enter a license key.', 'bkx-addon-sdk' ),
            ];
        }

        $response = $this->api_request( 'activate_license', [
            'license' => $license_key,
            'item_id' => $this->get_edd_item_id(),
            'url'     => home_url(),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ( 'valid' === ( $response['license'] ?? '' ) ) {
            update_option( $this->get_license_key_option(), $license_key );
            update_option( $this->get_license_status_option(), 'valid' );

            do_action( "bkx_{$this->addon_id}_license_activated", $license_key );

            return [
                'success' => true,
                'message' => __( 'License activated successfully!', 'bkx-addon-sdk' ),
            ];
        }

        $error_message = $this->get_license_error_message( $response['error'] ?? 'unknown' );

        return [
            'success' => false,
            'message' => $error_message,
        ];
    }

    /**
     * Deactivate the license.
     *
     * @since 1.0.0
     * @return array Result with 'success' bool and 'message'.
     */
    public function deactivate_license(): array {
        $license_key = $this->get_license_key();

        if ( empty( $license_key ) ) {
            return [
                'success' => false,
                'message' => __( 'No license key to deactivate.', 'bkx-addon-sdk' ),
            ];
        }

        $response = $this->api_request( 'deactivate_license', [
            'license' => $license_key,
            'item_id' => $this->get_edd_item_id(),
            'url'     => home_url(),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        if ( 'deactivated' === ( $response['license'] ?? '' ) ) {
            delete_option( $this->get_license_key_option() );
            update_option( $this->get_license_status_option(), '' );

            do_action( "bkx_{$this->addon_id}_license_deactivated" );

            return [
                'success' => true,
                'message' => __( 'License deactivated successfully.', 'bkx-addon-sdk' ),
            ];
        }

        return [
            'success' => false,
            'message' => __( 'Failed to deactivate license. Please try again.', 'bkx-addon-sdk' ),
        ];
    }

    /**
     * Check license status with server.
     *
     * @since 1.0.0
     * @return array License data or error.
     */
    public function check_license(): array {
        $license_key = $this->get_license_key();

        if ( empty( $license_key ) ) {
            return [
                'success' => false,
                'status'  => 'no_license',
                'message' => __( 'No license key found.', 'bkx-addon-sdk' ),
            ];
        }

        $response = $this->api_request( 'check_license', [
            'license' => $license_key,
            'item_id' => $this->get_edd_item_id(),
            'url'     => home_url(),
        ] );

        if ( is_wp_error( $response ) ) {
            return [
                'success' => false,
                'status'  => 'error',
                'message' => $response->get_error_message(),
            ];
        }

        $status = $response['license'] ?? 'invalid';
        update_option( $this->get_license_status_option(), $status );

        return [
            'success'           => 'valid' === $status,
            'status'            => $status,
            'expires'           => $response['expires'] ?? null,
            'activations_left'  => $response['activations_left'] ?? null,
            'customer_name'     => $response['customer_name'] ?? null,
            'customer_email'    => $response['customer_email'] ?? null,
        ];
    }

    /**
     * Make a request to the license server.
     *
     * @since 1.0.0
     * @param string $action API action.
     * @param array  $params Request parameters.
     * @return array|\WP_Error Response data or error.
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

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new \WP_Error( 'json_error', __( 'Invalid response from license server.', 'bkx-addon-sdk' ) );
        }

        return $data;
    }

    /**
     * Get EDD item ID for this add-on.
     *
     * Override in class if using numeric item ID instead of name.
     *
     * @since 1.0.0
     * @return string|int
     */
    protected function get_edd_item_id() {
        return $this->addon_name;
    }

    /**
     * Get human-readable license error message.
     *
     * @since 1.0.0
     * @param string $error Error code from EDD.
     * @return string Error message.
     */
    protected function get_license_error_message( string $error ): string {
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
     * Schedule license check.
     *
     * @since 1.0.0
     * @return void
     */
    public function schedule_license_check(): void {
        $hook = "bkx_{$this->addon_id}_license_check";

        if ( ! wp_next_scheduled( $hook ) ) {
            wp_schedule_event( time(), 'daily', $hook );
        }

        add_action( $hook, [ $this, 'check_license' ] );
    }

    /**
     * Clear scheduled license check.
     *
     * @since 1.0.0
     * @return void
     */
    public function clear_license_check(): void {
        $hook = "bkx_{$this->addon_id}_license_check";
        wp_clear_scheduled_hook( $hook );
    }
}
