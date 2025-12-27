<?php
/**
 * Admin Notices Manager
 *
 * Manages admin notices for add-ons.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Admin
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Admin;

/**
 * Admin notices manager class.
 *
 * @since 1.0.0
 */
class AdminNotices {

    /**
     * Notices to display.
     *
     * @var array
     */
    protected static array $notices = [];

    /**
     * Add a notice.
     *
     * @since 1.0.0
     * @param string $id      Unique notice ID.
     * @param string $message Notice message.
     * @param string $type    Notice type (success, warning, error, info).
     * @param bool   $dismissible Whether the notice can be dismissed.
     * @return void
     */
    public static function add( string $id, string $message, string $type = 'info', bool $dismissible = true ): void {
        self::$notices[ $id ] = [
            'id'          => $id,
            'message'     => $message,
            'type'        => $type,
            'dismissible' => $dismissible,
        ];
    }

    /**
     * Add a success notice.
     *
     * @since 1.0.0
     * @param string $id      Unique notice ID.
     * @param string $message Notice message.
     * @param bool   $dismissible Whether the notice can be dismissed.
     * @return void
     */
    public static function success( string $id, string $message, bool $dismissible = true ): void {
        self::add( $id, $message, 'success', $dismissible );
    }

    /**
     * Add a warning notice.
     *
     * @since 1.0.0
     * @param string $id      Unique notice ID.
     * @param string $message Notice message.
     * @param bool   $dismissible Whether the notice can be dismissed.
     * @return void
     */
    public static function warning( string $id, string $message, bool $dismissible = true ): void {
        self::add( $id, $message, 'warning', $dismissible );
    }

    /**
     * Add an error notice.
     *
     * @since 1.0.0
     * @param string $id      Unique notice ID.
     * @param string $message Notice message.
     * @param bool   $dismissible Whether the notice can be dismissed.
     * @return void
     */
    public static function error( string $id, string $message, bool $dismissible = true ): void {
        self::add( $id, $message, 'error', $dismissible );
    }

    /**
     * Add an info notice.
     *
     * @since 1.0.0
     * @param string $id      Unique notice ID.
     * @param string $message Notice message.
     * @param bool   $dismissible Whether the notice can be dismissed.
     * @return void
     */
    public static function info( string $id, string $message, bool $dismissible = true ): void {
        self::add( $id, $message, 'info', $dismissible );
    }

    /**
     * Remove a notice.
     *
     * @since 1.0.0
     * @param string $id Notice ID.
     * @return void
     */
    public static function remove( string $id ): void {
        unset( self::$notices[ $id ] );
    }

    /**
     * Register hooks.
     *
     * @since 1.0.0
     * @return void
     */
    public static function init(): void {
        add_action( 'admin_notices', [ __CLASS__, 'display_notices' ] );
        add_action( 'wp_ajax_bkx_dismiss_notice', [ __CLASS__, 'ajax_dismiss_notice' ] );
    }

    /**
     * Display all notices.
     *
     * @since 1.0.0
     * @return void
     */
    public static function display_notices(): void {
        $dismissed = get_option( 'bkx_dismissed_notices', [] );

        foreach ( self::$notices as $notice ) {
            // Skip dismissed notices
            if ( in_array( $notice['id'], $dismissed, true ) ) {
                continue;
            }

            self::render_notice( $notice );
        }
    }

    /**
     * Render a single notice.
     *
     * @since 1.0.0
     * @param array $notice Notice data.
     * @return void
     */
    protected static function render_notice( array $notice ): void {
        $classes = [
            'notice',
            'notice-' . $notice['type'],
            'bkx-admin-notice',
        ];

        if ( $notice['dismissible'] ) {
            $classes[] = 'is-dismissible';
        }

        printf(
            '<div id="bkx-notice-%s" class="%s" data-notice-id="%s"><p>%s</p></div>',
            esc_attr( $notice['id'] ),
            esc_attr( implode( ' ', $classes ) ),
            esc_attr( $notice['id'] ),
            wp_kses_post( $notice['message'] )
        );
    }

    /**
     * Handle AJAX dismissal.
     *
     * @since 1.0.0
     * @return void
     */
    public static function ajax_dismiss_notice(): void {
        check_ajax_referer( 'bkx_dismiss_notice', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $notice_id = sanitize_key( $_POST['notice_id'] ?? '' );

        if ( empty( $notice_id ) ) {
            wp_send_json_error( 'Invalid notice ID' );
        }

        $dismissed   = get_option( 'bkx_dismissed_notices', [] );
        $dismissed[] = $notice_id;
        $dismissed   = array_unique( $dismissed );

        update_option( 'bkx_dismissed_notices', $dismissed );

        wp_send_json_success();
    }

    /**
     * Clear a dismissed notice (allow it to show again).
     *
     * @since 1.0.0
     * @param string $notice_id Notice ID.
     * @return void
     */
    public static function clear_dismissed( string $notice_id ): void {
        $dismissed = get_option( 'bkx_dismissed_notices', [] );
        $dismissed = array_diff( $dismissed, [ $notice_id ] );
        update_option( 'bkx_dismissed_notices', $dismissed );
    }

    /**
     * Add a license notice for an addon.
     *
     * @since 1.0.0
     * @param string $addon_name Addon name.
     * @param string $addon_slug Addon slug.
     * @param string $license_url License purchase URL.
     * @return void
     */
    public static function add_license_notice( string $addon_name, string $addon_slug, string $license_url = '' ): void {
        $message = sprintf(
            /* translators: 1: Addon name, 2: License page link */
            __( '<strong>%1$s</strong> requires a valid license to receive updates and support. %2$s', 'bkx-addon-sdk' ),
            $addon_name,
            ! empty( $license_url )
                ? '<a href="' . esc_url( $license_url ) . '" target="_blank">' . __( 'Purchase a license', 'bkx-addon-sdk' ) . '</a>'
                : ''
        );

        self::warning( 'bkx_license_' . $addon_slug, $message, false );
    }

    /**
     * Add a dependency notice.
     *
     * @since 1.0.0
     * @param string $addon_name    Addon name.
     * @param string $addon_slug    Addon slug.
     * @param string $dependency    Missing dependency name.
     * @param string $dependency_url Dependency URL.
     * @return void
     */
    public static function add_dependency_notice( string $addon_name, string $addon_slug, string $dependency, string $dependency_url = '' ): void {
        $message = sprintf(
            /* translators: 1: Addon name, 2: Dependency name, 3: Install link */
            __( '<strong>%1$s</strong> requires <strong>%2$s</strong> to be installed and active. %3$s', 'bkx-addon-sdk' ),
            $addon_name,
            $dependency,
            ! empty( $dependency_url )
                ? '<a href="' . esc_url( $dependency_url ) . '" target="_blank">' . __( 'Install now', 'bkx-addon-sdk' ) . '</a>'
                : ''
        );

        self::error( 'bkx_dependency_' . $addon_slug, $message, false );
    }

    /**
     * Add a version mismatch notice.
     *
     * @since 1.0.0
     * @param string $addon_name      Addon name.
     * @param string $addon_slug      Addon slug.
     * @param string $required_version Required BookingX version.
     * @param string $current_version  Current BookingX version.
     * @return void
     */
    public static function add_version_notice( string $addon_name, string $addon_slug, string $required_version, string $current_version ): void {
        $message = sprintf(
            /* translators: 1: Addon name, 2: Required version, 3: Current version */
            __( '<strong>%1$s</strong> requires BookingX version %2$s or higher. You are running version %3$s. Please update BookingX to use this addon.', 'bkx-addon-sdk' ),
            $addon_name,
            $required_version,
            $current_version
        );

        self::error( 'bkx_version_' . $addon_slug, $message, false );
    }
}
