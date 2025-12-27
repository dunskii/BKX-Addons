<?php
/**
 * AJAX Trait
 *
 * Provides AJAX handler registration and utilities for add-ons.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Traits
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Traits;

/**
 * Trait for AJAX functionality.
 *
 * @since 1.0.0
 */
trait HasAjax {

    /**
     * Registered AJAX actions.
     *
     * @var array
     */
    protected array $ajax_actions = [];

    /**
     * Register an AJAX action.
     *
     * @since 1.0.0
     * @param string   $action       Action name (without prefix).
     * @param callable $callback     Callback function.
     * @param bool     $public       Whether to allow non-logged-in users.
     * @param string   $capability   Required capability (empty for none).
     * @return void
     */
    protected function register_ajax_action( string $action, callable $callback, bool $public = false, string $capability = '' ): void {
        $full_action = $this->get_ajax_action_name( $action );

        $this->ajax_actions[ $action ] = [
            'action'     => $full_action,
            'callback'   => $callback,
            'public'     => $public,
            'capability' => $capability,
        ];

        // Register for logged-in users
        add_action( "wp_ajax_{$full_action}", [ $this, 'handle_ajax_request' ] );

        // Register for non-logged-in users if public
        if ( $public ) {
            add_action( "wp_ajax_nopriv_{$full_action}", [ $this, 'handle_ajax_request' ] );
        }
    }

    /**
     * Handle an AJAX request.
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_ajax_request(): void {
        // Determine which action was called
        $action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

        // Find the registered action
        $prefix = "bkx_{$this->addon_id}_";
        if ( strpos( $action, $prefix ) !== 0 ) {
            $this->ajax_error( 'invalid_action', __( 'Invalid action.', 'bkx-addon-sdk' ) );
        }

        $short_action = str_replace( $prefix, '', $action );

        if ( ! isset( $this->ajax_actions[ $short_action ] ) ) {
            $this->ajax_error( 'unknown_action', __( 'Unknown action.', 'bkx-addon-sdk' ) );
        }

        $config = $this->ajax_actions[ $short_action ];

        // Check nonce
        $nonce_action = $this->get_nonce_action( $short_action );
        if ( ! $this->verify_ajax_nonce( $nonce_action ) ) {
            $this->ajax_error( 'invalid_nonce', __( 'Security check failed. Please refresh the page and try again.', 'bkx-addon-sdk' ), 403 );
        }

        // Check capability
        if ( ! empty( $config['capability'] ) && ! current_user_can( $config['capability'] ) ) {
            $this->ajax_error( 'no_permission', __( 'You do not have permission to perform this action.', 'bkx-addon-sdk' ), 403 );
        }

        // Execute callback
        try {
            call_user_func( $config['callback'] );
        } catch ( \Exception $e ) {
            $this->ajax_error( 'exception', $e->getMessage(), 500 );
        }

        // If callback didn't exit, send empty success
        $this->ajax_success();
    }

    /**
     * Get the full AJAX action name.
     *
     * @since 1.0.0
     * @param string $action Short action name.
     * @return string Full action name with addon prefix.
     */
    protected function get_ajax_action_name( string $action ): string {
        return "bkx_{$this->addon_id}_{$action}";
    }

    /**
     * Get the nonce action name.
     *
     * @since 1.0.0
     * @param string $action Short action name.
     * @return string Nonce action.
     */
    protected function get_nonce_action( string $action ): string {
        return "bkx_{$this->addon_id}_{$action}_nonce";
    }

    /**
     * Create a nonce for an action.
     *
     * @since 1.0.0
     * @param string $action Short action name.
     * @return string Nonce.
     */
    public function create_ajax_nonce( string $action ): string {
        return wp_create_nonce( $this->get_nonce_action( $action ) );
    }

    /**
     * Verify an AJAX nonce.
     *
     * @since 1.0.0
     * @param string $action  Nonce action.
     * @param string $field   POST/GET field name (default 'nonce').
     * @return bool
     */
    protected function verify_ajax_nonce( string $action, string $field = 'nonce' ): bool {
        $nonce = isset( $_REQUEST[ $field ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $field ] ) ) : '';

        return wp_verify_nonce( $nonce, $action );
    }

    /**
     * Send an AJAX success response.
     *
     * @since 1.0.0
     * @param mixed $data Response data.
     * @return void
     */
    protected function ajax_success( $data = null ): void {
        wp_send_json_success( $data );
    }

    /**
     * Send an AJAX error response.
     *
     * @since 1.0.0
     * @param string $code    Error code.
     * @param string $message Error message.
     * @param int    $status  HTTP status code.
     * @return void
     */
    protected function ajax_error( string $code, string $message, int $status = 400 ): void {
        wp_send_json_error( [
            'code'    => $code,
            'message' => $message,
        ], $status );
    }

    /**
     * Get a POST parameter.
     *
     * @since 1.0.0
     * @param string $key     Parameter key.
     * @param mixed  $default Default value.
     * @param string $type    Type to cast to (string, int, float, bool, array).
     * @return mixed
     */
    protected function get_post_param( string $key, $default = '', string $type = 'string' ) {
        if ( ! isset( $_POST[ $key ] ) ) {
            return $default;
        }

        $value = wp_unslash( $_POST[ $key ] );

        return $this->cast_value( $value, $type );
    }

    /**
     * Get a GET parameter.
     *
     * @since 1.0.0
     * @param string $key     Parameter key.
     * @param mixed  $default Default value.
     * @param string $type    Type to cast to.
     * @return mixed
     */
    protected function get_get_param( string $key, $default = '', string $type = 'string' ) {
        if ( ! isset( $_GET[ $key ] ) ) {
            return $default;
        }

        $value = wp_unslash( $_GET[ $key ] );

        return $this->cast_value( $value, $type );
    }

    /**
     * Get a REQUEST parameter (POST or GET).
     *
     * @since 1.0.0
     * @param string $key     Parameter key.
     * @param mixed  $default Default value.
     * @param string $type    Type to cast to.
     * @return mixed
     */
    protected function get_request_param( string $key, $default = '', string $type = 'string' ) {
        if ( ! isset( $_REQUEST[ $key ] ) ) {
            return $default;
        }

        $value = wp_unslash( $_REQUEST[ $key ] );

        return $this->cast_value( $value, $type );
    }

    /**
     * Cast a value to a specific type.
     *
     * @since 1.0.0
     * @param mixed  $value Value to cast.
     * @param string $type  Type to cast to.
     * @return mixed
     */
    protected function cast_value( $value, string $type ) {
        switch ( $type ) {
            case 'int':
            case 'integer':
                return (int) $value;

            case 'float':
            case 'double':
                return (float) $value;

            case 'bool':
            case 'boolean':
                return filter_var( $value, FILTER_VALIDATE_BOOLEAN );

            case 'array':
                if ( is_array( $value ) ) {
                    return array_map( 'sanitize_text_field', $value );
                }
                return [];

            case 'json':
                if ( is_string( $value ) ) {
                    $decoded = json_decode( $value, true );
                    return json_last_error() === JSON_ERROR_NONE ? $decoded : [];
                }
                return is_array( $value ) ? $value : [];

            case 'html':
                return wp_kses_post( $value );

            case 'email':
                return sanitize_email( $value );

            case 'url':
                return esc_url_raw( $value );

            case 'string':
            default:
                return sanitize_text_field( $value );
        }
    }

    /**
     * Get all POST parameters.
     *
     * @since 1.0.0
     * @param array $expected Expected parameters with types.
     * @return array
     */
    protected function get_all_post_params( array $expected ): array {
        $params = [];

        foreach ( $expected as $key => $config ) {
            if ( is_string( $config ) ) {
                $type    = $config;
                $default = '';
            } else {
                $type    = $config['type'] ?? 'string';
                $default = $config['default'] ?? '';
            }

            $params[ $key ] = $this->get_post_param( $key, $default, $type );
        }

        return $params;
    }

    /**
     * Get AJAX URL data for JavaScript.
     *
     * @since 1.0.0
     * @param array $actions Actions to include (short names).
     * @return array
     */
    public function get_ajax_data( array $actions = [] ): array {
        $data = [
            'url' => admin_url( 'admin-ajax.php' ),
        ];

        if ( empty( $actions ) ) {
            $actions = array_keys( $this->ajax_actions );
        }

        foreach ( $actions as $action ) {
            $data['actions'][ $action ] = $this->get_ajax_action_name( $action );
            $data['nonces'][ $action ]  = $this->create_ajax_nonce( $action );
        }

        return $data;
    }

    /**
     * Localize AJAX data for a script.
     *
     * @since 1.0.0
     * @param string $handle  Script handle.
     * @param string $name    JavaScript variable name.
     * @param array  $actions Actions to include.
     * @param array  $extra   Extra data to merge.
     * @return void
     */
    protected function localize_ajax_data( string $handle, string $name, array $actions = [], array $extra = [] ): void {
        $data = array_merge( $this->get_ajax_data( $actions ), $extra );
        wp_localize_script( $handle, $name, $data );
    }
}
