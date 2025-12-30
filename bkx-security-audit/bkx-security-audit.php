<?php
/**
 * Plugin Name: BookingX - Advanced Security & Audit
 * Plugin URI: https://developer.jejewebs.com/bkx-security-audit
 * Description: Comprehensive security hardening and audit logging for BookingX. Includes intrusion detection, security scanning, and detailed audit trails.
 * Version: 1.0.0
 * Author: JejeWebs
 * Author URI: https://jejewebs.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-security-audit
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\SecurityAudit
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_SECURITY_AUDIT_VERSION', '1.0.0' );
define( 'BKX_SECURITY_AUDIT_FILE', __FILE__ );
define( 'BKX_SECURITY_AUDIT_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_SECURITY_AUDIT_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_SECURITY_AUDIT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 *
 * @param string $class_name The class name to load.
 */
spl_autoload_register(
	function ( $class_name ) {
		$namespace = 'BookingX\\SecurityAudit\\';
		if ( strpos( $class_name, $namespace ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $namespace ) );
		$file           = BKX_SECURITY_AUDIT_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Initialize the plugin.
 */
function bkx_security_audit_init() {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_security_audit_missing_bookingx_notice' );
		return;
	}

	// Load text domain.
	load_plugin_textdomain( 'bkx-security-audit', false, dirname( BKX_SECURITY_AUDIT_BASENAME ) . '/languages' );

	// Initialize the main addon class.
	$addon = \BookingX\SecurityAudit\SecurityAuditAddon::get_instance();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_security_audit_init', 20 );

/**
 * Display admin notice if BookingX is not active.
 */
function bkx_security_audit_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-security-audit' ),
				'<strong>BookingX - Advanced Security & Audit</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Plugin activation hook.
 */
function bkx_security_audit_activate() {
	// Create database tables.
	bkx_security_audit_create_tables();

	// Set default options.
	$defaults = array(
		'enable_audit_logging'     => true,
		'enable_login_protection'  => true,
		'enable_file_monitoring'   => true,
		'enable_security_scanning' => true,
		'max_login_attempts'       => 5,
		'lockout_duration'         => 30,
		'audit_retention_days'     => 90,
		'notify_admin_on_lockout'  => true,
		'notify_admin_on_breach'   => true,
		'allowed_ips'              => '',
		'blocked_ips'              => '',
		'security_headers'         => true,
		'disable_xmlrpc'           => true,
		'two_factor_enabled'       => false,
	);
	add_option( 'bkx_security_audit_settings', $defaults );

	// Schedule cron jobs.
	if ( ! wp_next_scheduled( 'bkx_security_daily_scan' ) ) {
		wp_schedule_event( time(), 'daily', 'bkx_security_daily_scan' );
	}
	if ( ! wp_next_scheduled( 'bkx_security_audit_cleanup' ) ) {
		wp_schedule_event( time(), 'daily', 'bkx_security_audit_cleanup' );
	}

	// Create uploads directory for logs.
	$upload_dir = wp_upload_dir();
	$log_dir    = $upload_dir['basedir'] . '/bkx-security-logs/';
	if ( ! is_dir( $log_dir ) ) {
		wp_mkdir_p( $log_dir );
		// Add .htaccess to protect logs.
		file_put_contents( $log_dir . '.htaccess', 'Deny from all' );
		file_put_contents( $log_dir . 'index.php', '<?php // Silence is golden.' );
	}

	// Set activation flag.
	update_option( 'bkx_security_audit_version', BKX_SECURITY_AUDIT_VERSION );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_security_audit_activate' );

/**
 * Plugin deactivation hook.
 */
function bkx_security_audit_deactivate() {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_security_daily_scan' );
	wp_clear_scheduled_hook( 'bkx_security_audit_cleanup' );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_security_audit_deactivate' );

/**
 * Create database tables.
 */
function bkx_security_audit_create_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Audit log table.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_audit_log (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned DEFAULT NULL,
		user_email varchar(100) DEFAULT NULL,
		action varchar(100) NOT NULL,
		object_type varchar(50) DEFAULT NULL,
		object_id bigint(20) unsigned DEFAULT NULL,
		object_name varchar(255) DEFAULT NULL,
		details longtext,
		ip_address varchar(45) NOT NULL,
		user_agent text,
		session_id varchar(64) DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY user_id (user_id),
		KEY action (action),
		KEY object_type (object_type, object_id),
		KEY created_at (created_at),
		KEY ip_address (ip_address)
	) $charset_collate;";

	// Login attempts table.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_login_attempts (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		ip_address varchar(45) NOT NULL,
		username varchar(60) DEFAULT NULL,
		attempt_type enum('login','password_reset','2fa') DEFAULT 'login',
		success tinyint(1) NOT NULL DEFAULT 0,
		failure_reason varchar(100) DEFAULT NULL,
		user_agent text,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY ip_address (ip_address),
		KEY username (username),
		KEY created_at (created_at),
		KEY ip_success (ip_address, success)
	) $charset_collate;";

	// IP lockouts table.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_ip_lockouts (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		ip_address varchar(45) NOT NULL,
		lockout_reason varchar(100) NOT NULL,
		attempts_count int(11) NOT NULL DEFAULT 0,
		locked_until datetime NOT NULL,
		unlocked_at datetime DEFAULT NULL,
		unlocked_by bigint(20) unsigned DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY ip_address (ip_address),
		KEY locked_until (locked_until)
	) $charset_collate;";

	// Security events table.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_security_events (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_type varchar(50) NOT NULL,
		severity enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
		title varchar(255) NOT NULL,
		description text,
		data longtext,
		ip_address varchar(45) DEFAULT NULL,
		user_id bigint(20) unsigned DEFAULT NULL,
		resolved tinyint(1) NOT NULL DEFAULT 0,
		resolved_at datetime DEFAULT NULL,
		resolved_by bigint(20) unsigned DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY event_type (event_type),
		KEY severity (severity),
		KEY resolved (resolved),
		KEY created_at (created_at)
	) $charset_collate;";

	// File integrity hashes table.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_file_hashes (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		file_path varchar(500) NOT NULL,
		file_hash varchar(64) NOT NULL,
		file_size bigint(20) unsigned NOT NULL,
		last_modified datetime NOT NULL,
		status enum('unchanged','modified','new','deleted') DEFAULT 'unchanged',
		checked_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY file_path (file_path(255)),
		KEY status (status)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	foreach ( $sql as $query ) {
		dbDelta( $query );
	}

	update_option( 'bkx_security_audit_db_version', '1.0.0' );
}
