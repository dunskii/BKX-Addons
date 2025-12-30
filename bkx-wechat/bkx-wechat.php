<?php
/**
 * Plugin Name: BookingX - WeChat Integration
 * Plugin URI: https://flavor-flavor-flavor.local/plugins/bkx-wechat
 * Description: WeChat Mini Program and Official Account integration for BookingX bookings in China market.
 * Version: 1.0.0
 * Author: flavor-flavor-flavor.local
 * Author URI: https://flavor-flavor-flavor.local
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-wechat
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package BookingX\WeChat
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_WECHAT_VERSION', '1.0.0' );
define( 'BKX_WECHAT_PLUGIN_FILE', __FILE__ );
define( 'BKX_WECHAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_WECHAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_WECHAT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if BookingX is active.
 *
 * @return bool
 */
function bkx_wechat_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_wechat_missing_bookingx_notice' );
		return false;
	}
	return true;
}

/**
 * Show notice if BookingX is not active.
 */
function bkx_wechat_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX plugin to be installed and activated.', 'bkx-wechat' ),
				'<strong>BookingX WeChat Integration</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function bkx_wechat_init() {
	if ( ! bkx_wechat_check_dependencies() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_WECHAT_PLUGIN_DIR . 'src/autoload.php';

	// Initialize addon.
	\BookingX\WeChat\WeChatAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_wechat_init', 20 );

/**
 * Plugin activation.
 */
function bkx_wechat_activate() {
	// Set default options.
	$defaults = array(
		'enabled'               => false,
		'app_id'                => '',
		'app_secret'            => '',
		'mch_id'                => '',
		'api_key'               => '',
		'api_v3_key'            => '',
		'certificate_serial'    => '',
		'private_key_path'      => '',
		'certificate_path'      => '',
		'mini_program_app_id'   => '',
		'mini_program_secret'   => '',
		'official_account_enabled' => false,
		'mini_program_enabled'  => false,
		'wechat_pay_enabled'    => false,
		'template_messages'     => array(
			'booking_confirmed' => '',
			'booking_reminder'  => '',
			'booking_cancelled' => '',
		),
		'menu_config'           => array(),
		'auto_reply_enabled'    => false,
		'auto_reply_rules'      => array(),
		'qr_code_enabled'       => true,
		'sandbox_mode'          => true,
		'debug_mode'            => false,
	);

	if ( ! get_option( 'bkx_wechat_settings' ) ) {
		add_option( 'bkx_wechat_settings', $defaults );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_wechat_activate' );

/**
 * Plugin deactivation.
 */
function bkx_wechat_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_wechat_deactivate' );
