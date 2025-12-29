<?php
/**
 * Plugin Name: BookingX - Bulk & Recurring Payments
 * Plugin URI: https://developer.jetonjobs.com/bookingx/addons/bulk-recurring-payments
 * Description: Accept bulk bookings with package discounts and recurring payment subscriptions for ongoing services.
 * Version: 1.0.0
 * Author: Developer JetonJobs
 * Author URI: https://developer.jetonjobs.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-bulk-recurring-payments
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\BulkRecurringPayments
 */

namespace BookingX\BulkRecurringPayments;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'BKX_BULK_RECURRING_PAYMENTS_VERSION', '1.0.0' );
define( 'BKX_BULK_RECURRING_PAYMENTS_FILE', __FILE__ );
define( 'BKX_BULK_RECURRING_PAYMENTS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_BULK_RECURRING_PAYMENTS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_BULK_RECURRING_PAYMENTS_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
require_once BKX_BULK_RECURRING_PAYMENTS_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */
function init() {
	// Check for BookingX.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\dependency_notice' );
		return;
	}

	// Load text domain.
	load_plugin_textdomain(
		'bkx-bulk-recurring-payments',
		false,
		dirname( BKX_BULK_RECURRING_PAYMENTS_BASENAME ) . '/languages'
	);

	// Initialize addon.
	$addon = BulkRecurringPaymentsAddon::get_instance();
	$addon->init();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init', 20 );

/**
 * Display dependency notice.
 *
 * @since 1.0.0
 */
function dependency_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-bulk-recurring-payments' ),
				'<strong>Bulk & Recurring Payments</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Activation hook.
 *
 * @since 1.0.0
 */
function activate() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	// Payment packages table.
	$table_packages = $wpdb->prefix . 'bkx_payment_packages';
	$sql_packages   = "CREATE TABLE {$table_packages} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		description text,
		package_type enum('bulk','recurring') NOT NULL DEFAULT 'bulk',
		service_ids text COMMENT 'JSON array of base IDs',
		quantity int(11) DEFAULT NULL COMMENT 'For bulk packages',
		interval_type enum('day','week','month','year') DEFAULT NULL,
		interval_count int(11) DEFAULT 1,
		billing_cycles int(11) DEFAULT 0 COMMENT '0 = unlimited',
		price decimal(10,2) NOT NULL,
		discount_type enum('percentage','fixed') DEFAULT 'percentage',
		discount_amount decimal(10,2) DEFAULT 0,
		trial_days int(11) DEFAULT 0,
		setup_fee decimal(10,2) DEFAULT 0,
		status enum('active','inactive') DEFAULT 'active',
		valid_from date DEFAULT NULL,
		valid_until date DEFAULT NULL,
		max_purchases int(11) DEFAULT 0 COMMENT '0 = unlimited',
		purchase_count int(11) DEFAULT 0,
		meta_data text,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY package_type (package_type),
		KEY status (status),
		KEY valid_from (valid_from),
		KEY valid_until (valid_until)
	) {$charset_collate};";

	// Subscriptions table.
	$table_subscriptions = $wpdb->prefix . 'bkx_subscriptions';
	$sql_subscriptions   = "CREATE TABLE {$table_subscriptions} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		package_id bigint(20) unsigned NOT NULL,
		gateway varchar(50) NOT NULL,
		gateway_subscription_id varchar(255) DEFAULT NULL,
		status enum('pending','active','paused','cancelled','expired','failed') DEFAULT 'pending',
		start_date date NOT NULL,
		current_period_start date DEFAULT NULL,
		current_period_end date DEFAULT NULL,
		next_billing_date date DEFAULT NULL,
		trial_end_date date DEFAULT NULL,
		cancelled_at datetime DEFAULT NULL,
		cancel_reason text,
		billing_cycles_completed int(11) DEFAULT 0,
		total_amount_billed decimal(10,2) DEFAULT 0,
		meta_data text,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY customer_id (customer_id),
		KEY package_id (package_id),
		KEY gateway (gateway),
		KEY gateway_subscription_id (gateway_subscription_id),
		KEY status (status),
		KEY next_billing_date (next_billing_date)
	) {$charset_collate};";

	// Subscription payments table.
	$table_sub_payments = $wpdb->prefix . 'bkx_subscription_payments';
	$sql_sub_payments   = "CREATE TABLE {$table_sub_payments} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		subscription_id bigint(20) unsigned NOT NULL,
		amount decimal(10,2) NOT NULL,
		currency varchar(3) DEFAULT 'USD',
		gateway varchar(50) NOT NULL,
		gateway_payment_id varchar(255) DEFAULT NULL,
		status enum('pending','completed','failed','refunded') DEFAULT 'pending',
		payment_type enum('subscription','setup_fee','retry') DEFAULT 'subscription',
		billing_period_start date DEFAULT NULL,
		billing_period_end date DEFAULT NULL,
		failure_reason text,
		retry_count int(11) DEFAULT 0,
		paid_at datetime DEFAULT NULL,
		meta_data text,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY subscription_id (subscription_id),
		KEY gateway_payment_id (gateway_payment_id),
		KEY status (status),
		KEY paid_at (paid_at)
	) {$charset_collate};";

	// Bulk purchases table.
	$table_bulk = $wpdb->prefix . 'bkx_bulk_purchases';
	$sql_bulk   = "CREATE TABLE {$table_bulk} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) unsigned NOT NULL,
		package_id bigint(20) unsigned NOT NULL,
		quantity_purchased int(11) NOT NULL,
		quantity_used int(11) DEFAULT 0,
		quantity_remaining int(11) GENERATED ALWAYS AS (quantity_purchased - quantity_used) STORED,
		unit_price decimal(10,2) NOT NULL,
		total_price decimal(10,2) NOT NULL,
		discount_applied decimal(10,2) DEFAULT 0,
		gateway varchar(50) NOT NULL,
		gateway_payment_id varchar(255) DEFAULT NULL,
		status enum('pending','active','depleted','expired','refunded') DEFAULT 'pending',
		expires_at date DEFAULT NULL,
		activated_at datetime DEFAULT NULL,
		meta_data text,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY customer_id (customer_id),
		KEY package_id (package_id),
		KEY status (status),
		KEY expires_at (expires_at)
	) {$charset_collate};";

	// Bulk usage table.
	$table_bulk_usage = $wpdb->prefix . 'bkx_bulk_usage';
	$sql_bulk_usage   = "CREATE TABLE {$table_bulk_usage} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		bulk_purchase_id bigint(20) unsigned NOT NULL,
		booking_id bigint(20) unsigned NOT NULL,
		quantity_used int(11) DEFAULT 1,
		used_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY bulk_purchase_id (bulk_purchase_id),
		KEY booking_id (booking_id),
		UNIQUE KEY unique_booking (bulk_purchase_id, booking_id)
	) {$charset_collate};";

	// Invoice templates table.
	$table_invoices = $wpdb->prefix . 'bkx_invoice_templates';
	$sql_invoices   = "CREATE TABLE {$table_invoices} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		template_type enum('subscription','bulk','one_time') NOT NULL,
		logo_url varchar(500) DEFAULT NULL,
		company_name varchar(255) DEFAULT NULL,
		company_address text,
		company_tax_id varchar(100) DEFAULT NULL,
		header_text text,
		footer_text text,
		terms_text text,
		show_tax_breakdown tinyint(1) DEFAULT 1,
		show_discount_line tinyint(1) DEFAULT 1,
		custom_css text,
		is_default tinyint(1) DEFAULT 0,
		status enum('active','inactive') DEFAULT 'active',
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY template_type (template_type),
		KEY is_default (is_default)
	) {$charset_collate};";

	dbDelta( $sql_packages );
	dbDelta( $sql_subscriptions );
	dbDelta( $sql_sub_payments );
	dbDelta( $sql_bulk );
	dbDelta( $sql_bulk_usage );
	dbDelta( $sql_invoices );

	// Set default options.
	$defaults = array(
		'enable_bulk_packages'          => true,
		'enable_subscriptions'          => true,
		'bulk_expiry_days'              => 365,
		'allow_partial_refunds'         => true,
		'auto_cancel_failed_payments'   => 3,
		'send_renewal_reminders'        => true,
		'renewal_reminder_days'         => array( 7, 3, 1 ),
		'send_payment_receipts'         => true,
		'send_expiry_warnings'          => true,
		'expiry_warning_days'           => array( 30, 7, 1 ),
		'invoice_prefix'                => 'INV-',
		'invoice_starting_number'       => 1000,
		'invoice_include_tax'           => true,
		'auto_activate_bulk'            => true,
		'allow_package_switching'       => true,
		'prorate_upgrades'              => true,
		'retry_failed_payments'         => true,
		'retry_interval_hours'          => 24,
		'max_retry_attempts'            => 3,
		'pause_subscription_limit_days' => 30,
	);

	if ( ! get_option( 'bkx_bulk_recurring_payments_settings' ) ) {
		update_option( 'bkx_bulk_recurring_payments_settings', $defaults );
	}

	// Store version.
	update_option( 'bkx_bulk_recurring_payments_version', BKX_BULK_RECURRING_PAYMENTS_VERSION );

	// Clear rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Deactivation hook.
 *
 * @since 1.0.0
 */
function deactivate() {
	// Clear scheduled events.
	$cron_hooks = array(
		'bkx_process_recurring_payments',
		'bkx_check_subscription_renewals',
		'bkx_send_renewal_reminders',
		'bkx_check_bulk_expiry',
		'bkx_retry_failed_payments',
	);

	foreach ( $cron_hooks as $hook ) {
		$timestamp = wp_next_scheduled( $hook );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
		}
	}

	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );
