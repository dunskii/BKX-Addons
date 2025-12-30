<?php
/**
 * Plugin Name: BookingX Developer SDK
 * Plugin URI: https://developer.developer/add-ons/developer-sdk
 * Description: Complete developer toolkit with code generators, CLI tools, testing suite, and comprehensive documentation for building BookingX integrations.
 * Version: 1.0.0
 * Author: Developer Starter
 * Author URI: https://developer.developer
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-developer-sdk
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\DeveloperSDK
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_DEV_SDK_VERSION', '1.0.0' );
define( 'BKX_DEV_SDK_FILE', __FILE__ );
define( 'BKX_DEV_SDK_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_DEV_SDK_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_DEV_SDK_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Initialize the plugin.
 */
function bkx_developer_sdk_init() {
	// Check for BookingX.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_developer_sdk_missing_bookingx_notice' );
		return;
	}

	// Load autoloader.
	require_once BKX_DEV_SDK_PATH . 'src/autoload.php';

	// Initialize addon.
	\BookingX\DeveloperSDK\DeveloperSDKAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_developer_sdk_init', 20 );

/**
 * Missing BookingX notice.
 */
function bkx_developer_sdk_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX Developer SDK requires BookingX plugin to be installed and activated.', 'bkx-developer-sdk' ); ?></p>
	</div>
	<?php
}

/**
 * Activation hook.
 */
function bkx_developer_sdk_activate() {
	// Set default options.
	$default_settings = array(
		'debug_mode'              => false,
		'enable_sandbox'          => true,
		'sandbox_prefix'          => 'bkx_sandbox_',
		'enable_code_generator'   => true,
		'enable_api_explorer'     => true,
		'enable_testing_tools'    => true,
		'enable_documentation'    => true,
		'api_explorer_cache_ttl'  => 3600,
		'generated_code_path'     => WP_CONTENT_DIR . '/bkx-generated/',
		'test_data_retention'     => 7,
		'log_api_requests'        => true,
		'enable_cli'              => true,
	);
	add_option( 'bkx_developer_sdk_settings', $default_settings );
	add_option( 'bkx_developer_sdk_version', BKX_DEV_SDK_VERSION );

	// Create generated code directory.
	$generated_path = WP_CONTENT_DIR . '/bkx-generated/';
	if ( ! file_exists( $generated_path ) ) {
		wp_mkdir_p( $generated_path );
		// Add .htaccess for security.
		file_put_contents( $generated_path . '.htaccess', 'deny from all' );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_developer_sdk_activate' );

/**
 * Deactivation hook.
 */
function bkx_developer_sdk_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_developer_sdk_deactivate' );

/**
 * Register WP-CLI commands if available.
 */
function bkx_developer_sdk_register_cli() {
	if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
		return;
	}

	$settings = get_option( 'bkx_developer_sdk_settings', array() );
	if ( empty( $settings['enable_cli'] ) ) {
		return;
	}

	require_once BKX_DEV_SDK_PATH . 'src/autoload.php';

	WP_CLI::add_command( 'bkx', \BookingX\DeveloperSDK\CLI\MainCommand::class );
	WP_CLI::add_command( 'bkx generate', \BookingX\DeveloperSDK\CLI\GenerateCommand::class );
	WP_CLI::add_command( 'bkx test', \BookingX\DeveloperSDK\CLI\TestCommand::class );
	WP_CLI::add_command( 'bkx api', \BookingX\DeveloperSDK\CLI\APICommand::class );
}
add_action( 'cli_init', 'bkx_developer_sdk_register_cli' );
