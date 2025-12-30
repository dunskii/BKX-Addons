<?php
/**
 * Plugin Name: BookingX - Multi-Tenant Management
 * Plugin URI: https://flavor-flavor-flavor.dev/bookingx/addons/multi-tenant
 * Description: Enterprise multi-tenant management for franchise operations, agency white-label solutions, and multi-location businesses.
 * Version: 1.0.0
 * Author: flavor-flavor-flavor.dev
 * Author URI: https://flavor-flavor-flavor.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-multi-tenant
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 * Network: true
 *
 * @package BookingX\MultiTenant
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_MULTI_TENANT_VERSION', '1.0.0' );
define( 'BKX_MULTI_TENANT_PLUGIN_FILE', __FILE__ );
define( 'BKX_MULTI_TENANT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BKX_MULTI_TENANT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader.
require_once BKX_MULTI_TENANT_PLUGIN_DIR . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @return void
 */
function bkx_multi_tenant_init() {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_multi_tenant_missing_bookingx_notice' );
		return;
	}

	// Initialize the addon.
	\BookingX\MultiTenant\MultiTenantAddon::get_instance();
}
add_action( 'plugins_loaded', 'bkx_multi_tenant_init', 20 );

/**
 * Missing BookingX notice.
 *
 * @return void
 */
function bkx_multi_tenant_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'BookingX - Multi-Tenant Management', 'bkx-multi-tenant' ); ?></strong>
			<?php esc_html_e( 'requires the BookingX plugin to be installed and activated.', 'bkx-multi-tenant' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Plugin activation.
 *
 * @param bool $network_wide Whether the plugin is being activated network-wide.
 * @return void
 */
function bkx_multi_tenant_activate( $network_wide ) {
	global $wpdb;

	// Create database tables.
	$charset_collate = $wpdb->get_charset_collate();

	// Tenants table.
	$sql_tenants = "CREATE TABLE IF NOT EXISTS {$wpdb->base_prefix}bkx_tenants (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		slug varchar(100) NOT NULL,
		status varchar(20) DEFAULT 'active',
		owner_id bigint(20) UNSIGNED DEFAULT NULL,
		plan_id bigint(20) UNSIGNED DEFAULT NULL,
		domain varchar(255) DEFAULT NULL,
		settings longtext DEFAULT NULL,
		branding longtext DEFAULT NULL,
		limits longtext DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug),
		KEY status (status),
		KEY owner_id (owner_id)
	) {$charset_collate};";

	// Tenant sites mapping.
	$sql_sites = "CREATE TABLE IF NOT EXISTS {$wpdb->base_prefix}bkx_tenant_sites (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		tenant_id bigint(20) UNSIGNED NOT NULL,
		site_id bigint(20) UNSIGNED NOT NULL,
		role varchar(50) DEFAULT 'child',
		is_primary tinyint(1) DEFAULT 0,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY tenant_site (tenant_id, site_id),
		KEY tenant_id (tenant_id),
		KEY site_id (site_id)
	) {$charset_collate};";

	// Tenant users.
	$sql_users = "CREATE TABLE IF NOT EXISTS {$wpdb->base_prefix}bkx_tenant_users (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		tenant_id bigint(20) UNSIGNED NOT NULL,
		user_id bigint(20) UNSIGNED NOT NULL,
		role varchar(50) DEFAULT 'member',
		permissions longtext DEFAULT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY tenant_user (tenant_id, user_id),
		KEY tenant_id (tenant_id),
		KEY user_id (user_id)
	) {$charset_collate};";

	// Plans table.
	$sql_plans = "CREATE TABLE IF NOT EXISTS {$wpdb->base_prefix}bkx_tenant_plans (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		slug varchar(100) NOT NULL,
		description text DEFAULT NULL,
		price decimal(10,2) DEFAULT 0.00,
		billing_cycle varchar(20) DEFAULT 'monthly',
		limits longtext DEFAULT NULL,
		features longtext DEFAULT NULL,
		is_active tinyint(1) DEFAULT 1,
		sort_order int DEFAULT 0,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY slug (slug)
	) {$charset_collate};";

	// Usage tracking.
	$sql_usage = "CREATE TABLE IF NOT EXISTS {$wpdb->base_prefix}bkx_tenant_usage (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		tenant_id bigint(20) UNSIGNED NOT NULL,
		metric varchar(50) NOT NULL,
		value bigint(20) DEFAULT 0,
		period varchar(20) NOT NULL,
		period_start date NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY tenant_metric_period (tenant_id, metric, period, period_start),
		KEY tenant_id (tenant_id)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_tenants );
	dbDelta( $sql_sites );
	dbDelta( $sql_users );
	dbDelta( $sql_plans );
	dbDelta( $sql_usage );

	// Create default plans.
	bkx_multi_tenant_create_default_plans();

	// Store version.
	update_site_option( 'bkx_multi_tenant_version', BKX_MULTI_TENANT_VERSION );
	update_site_option( 'bkx_multi_tenant_db_version', '1.0.0' );
}
register_activation_hook( __FILE__, 'bkx_multi_tenant_activate' );

/**
 * Create default tenant plans.
 *
 * @return void
 */
function bkx_multi_tenant_create_default_plans() {
	global $wpdb;

	$plans = array(
		array(
			'name'          => 'Starter',
			'slug'          => 'starter',
			'description'   => 'Perfect for small businesses',
			'price'         => 49.00,
			'billing_cycle' => 'monthly',
			'limits'        => wp_json_encode(
				array(
					'sites'         => 1,
					'bookings'      => 100,
					'users'         => 2,
					'storage_mb'    => 500,
					'api_calls'     => 1000,
				)
			),
			'features'      => wp_json_encode(
				array(
					'basic_reports',
					'email_support',
				)
			),
			'sort_order'    => 1,
		),
		array(
			'name'          => 'Professional',
			'slug'          => 'professional',
			'description'   => 'For growing businesses',
			'price'         => 99.00,
			'billing_cycle' => 'monthly',
			'limits'        => wp_json_encode(
				array(
					'sites'         => 5,
					'bookings'      => 500,
					'users'         => 10,
					'storage_mb'    => 2000,
					'api_calls'     => 10000,
				)
			),
			'features'      => wp_json_encode(
				array(
					'advanced_reports',
					'priority_support',
					'custom_branding',
					'api_access',
				)
			),
			'sort_order'    => 2,
		),
		array(
			'name'          => 'Enterprise',
			'slug'          => 'enterprise',
			'description'   => 'Unlimited for large organizations',
			'price'         => 299.00,
			'billing_cycle' => 'monthly',
			'limits'        => wp_json_encode(
				array(
					'sites'         => -1,
					'bookings'      => -1,
					'users'         => -1,
					'storage_mb'    => -1,
					'api_calls'     => -1,
				)
			),
			'features'      => wp_json_encode(
				array(
					'advanced_reports',
					'priority_support',
					'custom_branding',
					'api_access',
					'white_label',
					'dedicated_support',
					'sla_guarantee',
				)
			),
			'sort_order'    => 3,
		),
	);

	foreach ( $plans as $plan ) {
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->base_prefix}bkx_tenant_plans WHERE slug = %s",
				$plan['slug']
			)
		);

		if ( ! $exists ) {
			$wpdb->insert( $wpdb->base_prefix . 'bkx_tenant_plans', $plan );
		}
	}
}
