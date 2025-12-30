<?php
/**
 * Plugin Name: BookingX - Data Backup & Recovery
 * Plugin URI: https://developer.jejewebs.com/bkx-backup-recovery
 * Description: Automated backup and point-in-time recovery for BookingX data. Schedule backups, export data, and restore from any point in time.
 * Version: 1.0.0
 * Author: JejeWebs
 * Author URI: https://jejewebs.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-backup-recovery
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\BackupRecovery
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_BACKUP_VERSION', '1.0.0' );
define( 'BKX_BACKUP_FILE', __FILE__ );
define( 'BKX_BACKUP_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_BACKUP_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_BACKUP_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes.
 *
 * @param string $class_name The class name to load.
 */
spl_autoload_register(
	function ( $class_name ) {
		$namespace = 'BookingX\\BackupRecovery\\';
		if ( strpos( $class_name, $namespace ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class_name, strlen( $namespace ) );
		$file           = BKX_BACKUP_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Initialize the plugin.
 */
function bkx_backup_recovery_init() {
	// Check if BookingX is active.
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_backup_recovery_missing_bookingx_notice' );
		return;
	}

	// Load text domain.
	load_plugin_textdomain( 'bkx-backup-recovery', false, dirname( BKX_BACKUP_BASENAME ) . '/languages' );

	// Initialize the main addon class.
	$addon = \BookingX\BackupRecovery\BackupRecoveryAddon::get_instance();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_backup_recovery_init', 20 );

/**
 * Display admin notice if BookingX is not active.
 */
function bkx_backup_recovery_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: BookingX plugin name */
				esc_html__( '%s requires BookingX to be installed and activated.', 'bkx-backup-recovery' ),
				'<strong>BookingX - Data Backup & Recovery</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Plugin activation hook.
 */
function bkx_backup_recovery_activate() {
	// Create database tables.
	bkx_backup_recovery_create_tables();

	// Set default options.
	$defaults = array(
		'backup_frequency'     => 'daily',
		'backup_time'          => '03:00',
		'backup_retention'     => 30,
		'include_bookings'     => true,
		'include_customers'    => true,
		'include_settings'     => true,
		'include_services'     => true,
		'include_staff'        => true,
		'compression'          => 'zip',
		'email_notification'   => true,
		'notify_email'         => get_option( 'admin_email' ),
		'remote_storage'       => 'none',
		'max_backup_size'      => 100, // MB.
	);
	add_option( 'bkx_backup_recovery_settings', $defaults );

	// Create backup directory.
	$upload_dir = wp_upload_dir();
	$backup_dir = $upload_dir['basedir'] . '/bkx-backups/';
	if ( ! is_dir( $backup_dir ) ) {
		wp_mkdir_p( $backup_dir );
		// Protect directory.
		file_put_contents( $backup_dir . '.htaccess', 'Deny from all' );
		file_put_contents( $backup_dir . 'index.php', '<?php // Silence is golden.' );
	}

	// Schedule backup cron.
	bkx_backup_schedule_cron();

	// Set activation flag.
	update_option( 'bkx_backup_recovery_version', BKX_BACKUP_VERSION );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'bkx_backup_recovery_activate' );

/**
 * Plugin deactivation hook.
 */
function bkx_backup_recovery_deactivate() {
	// Clear scheduled events.
	wp_clear_scheduled_hook( 'bkx_backup_scheduled_backup' );
	wp_clear_scheduled_hook( 'bkx_backup_cleanup_old_backups' );

	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'bkx_backup_recovery_deactivate' );

/**
 * Schedule backup cron job.
 */
function bkx_backup_schedule_cron() {
	$settings = get_option( 'bkx_backup_recovery_settings', array() );

	// Clear existing schedule.
	wp_clear_scheduled_hook( 'bkx_backup_scheduled_backup' );

	$frequency = $settings['backup_frequency'] ?? 'daily';
	$time      = $settings['backup_time'] ?? '03:00';

	// Parse time.
	$parts = explode( ':', $time );
	$hour  = isset( $parts[0] ) ? absint( $parts[0] ) : 3;
	$min   = isset( $parts[1] ) ? absint( $parts[1] ) : 0;

	// Calculate next run time.
	$next_run = strtotime( "today {$hour}:{$min}" );
	if ( $next_run < time() ) {
		$next_run = strtotime( "tomorrow {$hour}:{$min}" );
	}

	// Map frequency to schedule.
	$schedules = array(
		'hourly'  => 'hourly',
		'daily'   => 'daily',
		'weekly'  => 'weekly',
		'monthly' => 'monthly',
	);

	$schedule = $schedules[ $frequency ] ?? 'daily';

	if ( ! wp_next_scheduled( 'bkx_backup_scheduled_backup' ) ) {
		wp_schedule_event( $next_run, $schedule, 'bkx_backup_scheduled_backup' );
	}

	// Schedule cleanup.
	if ( ! wp_next_scheduled( 'bkx_backup_cleanup_old_backups' ) ) {
		wp_schedule_event( time(), 'daily', 'bkx_backup_cleanup_old_backups' );
	}
}

/**
 * Create database tables.
 */
function bkx_backup_recovery_create_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// Backup history table.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_backup_history (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		backup_type enum('full','incremental','manual') NOT NULL DEFAULT 'full',
		status enum('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
		file_name varchar(255) NOT NULL,
		file_size bigint(20) unsigned DEFAULT 0,
		file_path varchar(500) NOT NULL,
		checksum varchar(64) DEFAULT NULL,
		items_count int(11) DEFAULT 0,
		tables_included text,
		error_message text,
		started_at datetime DEFAULT NULL,
		completed_at datetime DEFAULT NULL,
		created_by bigint(20) unsigned DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY status (status),
		KEY backup_type (backup_type),
		KEY created_at (created_at)
	) $charset_collate;";

	// Restore history table.
	$sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_restore_history (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		backup_id bigint(20) unsigned NOT NULL,
		status enum('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
		items_restored int(11) DEFAULT 0,
		error_message text,
		started_at datetime DEFAULT NULL,
		completed_at datetime DEFAULT NULL,
		restored_by bigint(20) unsigned NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY backup_id (backup_id),
		KEY status (status)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	foreach ( $sql as $query ) {
		dbDelta( $query );
	}

	update_option( 'bkx_backup_recovery_db_version', '1.0.0' );
}

/**
 * Add custom cron schedules.
 *
 * @param array $schedules Existing schedules.
 * @return array
 */
function bkx_backup_add_cron_schedules( $schedules ) {
	$schedules['weekly'] = array(
		'interval' => WEEK_IN_SECONDS,
		'display'  => __( 'Once Weekly', 'bkx-backup-recovery' ),
	);
	$schedules['monthly'] = array(
		'interval' => MONTH_IN_SECONDS,
		'display'  => __( 'Once Monthly', 'bkx-backup-recovery' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'bkx_backup_add_cron_schedules' );
