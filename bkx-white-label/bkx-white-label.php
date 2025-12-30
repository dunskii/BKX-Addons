<?php
/**
 * Plugin Name: BookingX White Label Solution
 * Plugin URI: https://flavflavor.dev/bookingx/addons/white-label
 * Description: Complete white-labeling solution for BookingX - customize branding, colors, emails, and remove all BookingX references.
 * Version: 1.0.0
 * Author: flavflavor.dev
 * Author URI: https://flavflavor.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-white-label
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\WhiteLabel
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_WHITE_LABEL_VERSION', '1.0.0' );
define( 'BKX_WHITE_LABEL_PLUGIN_FILE', __FILE__ );
define( 'BKX_WHITE_LABEL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_WHITE_LABEL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_WHITE_LABEL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Minimum requirements.
define( 'BKX_WHITE_LABEL_MIN_PHP_VERSION', '7.4' );
define( 'BKX_WHITE_LABEL_MIN_WP_VERSION', '5.8' );
define( 'BKX_WHITE_LABEL_MIN_BKX_VERSION', '2.0.0' );

/**
 * Check if requirements are met before loading the plugin.
 *
 * @return bool
 */
function bkx_white_label_requirements_met() {
	// Check PHP version.
	if ( version_compare( PHP_VERSION, BKX_WHITE_LABEL_MIN_PHP_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'bkx_white_label_php_notice' );
		return false;
	}

	// Check WordPress version.
	if ( version_compare( get_bloginfo( 'version' ), BKX_WHITE_LABEL_MIN_WP_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'bkx_white_label_wp_notice' );
		return false;
	}

	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_white_label_bookingx_notice' );
		return false;
	}

	return true;
}

/**
 * PHP version notice.
 */
function bkx_white_label_php_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Required PHP version, 2: Current PHP version */
				esc_html__( 'BookingX White Label requires PHP %1$s or higher. You are running PHP %2$s.', 'bkx-white-label' ),
				esc_html( BKX_WHITE_LABEL_MIN_PHP_VERSION ),
				esc_html( PHP_VERSION )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * WordPress version notice.
 */
function bkx_white_label_wp_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Required WP version */
				esc_html__( 'BookingX White Label requires WordPress %1$s or higher.', 'bkx-white-label' ),
				esc_html( BKX_WHITE_LABEL_MIN_WP_VERSION )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * BookingX dependency notice.
 */
function bkx_white_label_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: 1: Required BookingX version */
				esc_html__( 'BookingX White Label requires BookingX %1$s or higher to be installed and active.', 'bkx-white-label' ),
				esc_html( BKX_WHITE_LABEL_MIN_BKX_VERSION )
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function bkx_white_label_init() {
	if ( ! bkx_white_label_requirements_met() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_WHITE_LABEL_PLUGIN_DIR . 'src/autoload.php';

	// Initialize the addon.
	\BookingX\WhiteLabel\WhiteLabelAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_white_label_init', 15 );

/**
 * Activation hook.
 */
function bkx_white_label_activate() {
	// Create default settings.
	if ( false === get_option( 'bkx_white_label_settings' ) ) {
		$defaults = array(
			'enabled'                 => false,
			'brand_name'              => '',
			'brand_logo'              => '',
			'brand_logo_dark'         => '',
			'brand_icon'              => '',
			'brand_url'               => '',
			'support_email'           => '',
			'support_url'             => '',
			'hide_bookingx_branding'  => true,
			'hide_powered_by'         => true,
			'hide_plugin_notices'     => false,
			'hide_changelog'          => false,
			'custom_admin_footer'     => '',
			'primary_color'           => '#2271b1',
			'secondary_color'         => '#135e96',
			'accent_color'            => '#72aee6',
			'success_color'           => '#00a32a',
			'warning_color'           => '#dba617',
			'error_color'             => '#d63638',
			'text_color'              => '#1d2327',
			'background_color'        => '#ffffff',
			'custom_css_admin'        => '',
			'custom_css_frontend'     => '',
			'custom_js_admin'         => '',
			'custom_js_frontend'      => '',
			'email_from_name'         => '',
			'email_from_address'      => '',
			'email_header_image'      => '',
			'email_footer_text'       => '',
			'email_background_color'  => '#f7f7f7',
			'email_body_color'        => '#ffffff',
			'email_text_color'        => '#636363',
			'email_link_color'        => '#2271b1',
			'replace_strings'         => array(),
			'hide_menu_items'         => array(),
			'custom_menu_order'       => array(),
			'login_logo'              => '',
			'login_background'        => '',
			'login_custom_css'        => '',
		);
		add_option( 'bkx_white_label_settings', $defaults );
	}

	// Store version.
	update_option( 'bkx_white_label_version', BKX_WHITE_LABEL_VERSION );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_white_label_activate' );

/**
 * Deactivation hook.
 */
function bkx_white_label_deactivate() {
	// Flush rewrite rules.
	flush_rewrite_rules();

	// Clear any cached styles.
	delete_transient( 'bkx_white_label_custom_css' );
}
register_deactivation_hook( __FILE__, 'bkx_white_label_deactivate' );
