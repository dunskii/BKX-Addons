<?php
/**
 * Plugin Name: BookingX - PCI DSS Compliance Tools
 * Plugin URI: https://developer.jejewebs.com/bkx-pci-compliance
 * Description: PCI DSS compliance toolkit for BookingX. Secure payment data handling, compliance monitoring, vulnerability scanning, and audit reporting.
 * Version: 1.0.0
 * Author: JejeWebs
 * Author URI: https://jejewebs.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-pci-compliance
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\PCICompliance
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_PCI_VERSION', '1.0.0' );
define( 'BKX_PCI_FILE', __FILE__ );
define( 'BKX_PCI_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_PCI_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_PCI_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 *
 * @param string $class_name The class name to load.
 */
spl_autoload_register(
	function ( $class_name ) {
		$namespace = 'BookingX\\PCICompliance\\';
		if ( strpos( $class_name, $namespace ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $namespace ) );
		$file           = BKX_PCI_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Initialize the plugin.
 */
function bkx_pci_compliance_init() {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_pci_compliance_missing_bookingx_notice' );
		return;
	}

	// Load text domain.
	load_plugin_textdomain( 'bkx-pci-compliance', false, dirname( BKX_PCI_BASENAME ) . '/languages' );

	// Initialize the main addon class.
	$addon = \BookingX\PCICompliance\PCIComplianceAddon::get_instance();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_pci_compliance_init', 20 );

/**
 * Display admin notice if BookingX is not active.
 */
function bkx_pci_compliance_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-pci-compliance' ),
				'<strong>BookingX - PCI DSS Compliance Tools</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Plugin activation hook.
 */
function bkx_pci_compliance_activate() {
	// Create database tables.
	bkx_pci_compliance_create_tables();

	// Set default options.
	$defaults = array(
		'compliance_level'       => 'saq_a',
		'card_data_storage'      => 'none',
		'tokenization_enabled'   => true,
		'pci_scan_frequency'     => 'weekly',
		'ssl_enforcement'        => true,
		'session_timeout'        => 15,
		'password_requirements'  => array(
			'min_length'    => 12,
			'require_upper' => true,
			'require_lower' => true,
			'require_number' => true,
			'require_special' => true,
		),
		'failed_login_lockout'   => 5,
		'audit_log_retention'    => 365,
		'data_masking_enabled'   => true,
		'vulnerability_alerts'   => true,
		'alert_email'            => get_option( 'admin_email' ),
	);
	add_option( 'bkx_pci_compliance_settings', $defaults );

	// Set activation flag.
	update_option( 'bkx_pci_compliance_version', BKX_PCI_VERSION );

	// Schedule scans.
	if ( ! wp_next_scheduled( 'bkx_pci_compliance_scan' ) ) {
		wp_schedule_event( time(), 'weekly', 'bkx_pci_compliance_scan' );
	}

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_pci_compliance_activate' );

/**
 * Plugin deactivation hook.
 */
function bkx_pci_compliance_deactivate() {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_pci_compliance_scan' );
	wp_clear_scheduled_hook( 'bkx_pci_cleanup_logs' );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_pci_compliance_deactivate' );

/**
 * Create database tables.
 */
function bkx_pci_compliance_create_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// PCI audit log table.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_pci_audit_log (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_type varchar(50) NOT NULL,
		event_category enum('authentication','data_access','configuration','payment','security') NOT NULL,
		severity enum('info','warning','critical') NOT NULL DEFAULT 'info',
		user_id bigint(20) unsigned DEFAULT NULL,
		ip_address varchar(45) NOT NULL,
		user_agent text,
		resource_type varchar(50) DEFAULT NULL,
		resource_id bigint(20) unsigned DEFAULT NULL,
		action varchar(100) NOT NULL,
		details longtext,
		pci_requirement varchar(20) DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY event_type (event_type),
		KEY event_category (event_category),
		KEY severity (severity),
		KEY user_id (user_id),
		KEY created_at (created_at),
		KEY pci_requirement (pci_requirement)
	) $charset_collate;";

	// Compliance scan results table.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_pci_scan_results (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		scan_type enum('full','quick','vulnerability','configuration') NOT NULL DEFAULT 'full',
		status enum('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
		overall_score decimal(5,2) DEFAULT NULL,
		requirements_passed int(11) DEFAULT 0,
		requirements_failed int(11) DEFAULT 0,
		requirements_na int(11) DEFAULT 0,
		vulnerabilities_found int(11) DEFAULT 0,
		critical_issues int(11) DEFAULT 0,
		results longtext,
		recommendations longtext,
		started_at datetime DEFAULT NULL,
		completed_at datetime DEFAULT NULL,
		created_by bigint(20) unsigned DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY scan_type (scan_type),
		KEY status (status),
		KEY created_at (created_at)
	) $charset_collate;";

	// Payment data access log table.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_pci_data_access (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		data_type enum('card_number','cvv','expiry','cardholder_name','full_pan') NOT NULL,
		access_type enum('view','export','modify','delete') NOT NULL,
		booking_id bigint(20) unsigned DEFAULT NULL,
		justification text,
		ip_address varchar(45) NOT NULL,
		masked_data varchar(255) DEFAULT NULL,
		authorized tinyint(1) DEFAULT 1,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY data_type (data_type),
		KEY access_type (access_type),
		KEY created_at (created_at)
	) $charset_collate;";

	// Encryption key rotation log table.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_pci_key_rotation (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		key_type enum('encryption','tokenization','api') NOT NULL,
		key_identifier varchar(64) NOT NULL,
		rotation_reason enum('scheduled','compromised','policy','manual') NOT NULL,
		old_key_hash varchar(64) DEFAULT NULL,
		new_key_hash varchar(64) NOT NULL,
		rotated_by bigint(20) unsigned DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY key_type (key_type),
		KEY created_at (created_at)
	) $charset_collate;";

	// Vulnerability tracking table.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_pci_vulnerabilities (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		vulnerability_id varchar(50) DEFAULT NULL,
		title varchar(255) NOT NULL,
		description text,
		severity enum('low','medium','high','critical') NOT NULL,
		cvss_score decimal(3,1) DEFAULT NULL,
		affected_component varchar(255) DEFAULT NULL,
		status enum('open','in_progress','resolved','accepted','false_positive') NOT NULL DEFAULT 'open',
		remediation text,
		discovered_at datetime NOT NULL,
		resolved_at datetime DEFAULT NULL,
		resolved_by bigint(20) unsigned DEFAULT NULL,
		pci_requirements text,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY vulnerability_id (vulnerability_id),
		KEY severity (severity),
		KEY status (status),
		KEY discovered_at (discovered_at)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	foreach ( $sql as $query ) {
		dbDelta( $query );
	}

	update_option( 'bkx_pci_compliance_db_version', '1.0.0' );
}

/**
 * Add custom cron schedules.
 *
 * @param array $schedules Existing schedules.
 * @return array
 */
function bkx_pci_add_cron_schedules( $schedules ) {
	$schedules['weekly'] = array(
		'interval' => WEEK_IN_SECONDS,
		'display'  => __( 'Once Weekly', 'bkx-pci-compliance' ),
	);
	$schedules['monthly'] = array(
		'interval' => MONTH_IN_SECONDS,
		'display'  => __( 'Once Monthly', 'bkx-pci-compliance' ),
	);
	$schedules['quarterly'] = array(
		'interval' => 3 * MONTH_IN_SECONDS,
		'display'  => __( 'Once Quarterly', 'bkx-pci-compliance' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'bkx_pci_add_cron_schedules' );
