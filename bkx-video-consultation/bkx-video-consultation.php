<?php
/**
 * Plugin Name: BookingX - Video Consultation
 * Plugin URI: https://flavflavor.dev/bookingx/addons/video-consultation
 * Description: Add video consultation capabilities to BookingX with Zoom, Google Meet, and WebRTC support.
 * Version: 1.0.0
 * Author: flavflavor.dev
 * Author URI: https://flavflavor.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: bkx-video-consultation
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\VideoConsultation
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_VIDEO_CONSULTATION_VERSION', '1.0.0' );
define( 'BKX_VIDEO_CONSULTATION_FILE', __FILE__ );
define( 'BKX_VIDEO_CONSULTATION_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_VIDEO_CONSULTATION_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_VIDEO_CONSULTATION_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if BookingX is active.
 *
 * @return bool
 */
function bkx_video_consultation_check_dependencies() {
	if ( ! class_exists( 'Bookingx' ) ) {
		add_action( 'admin_notices', 'bkx_video_consultation_missing_bookingx_notice' );
		return false;
	}
	return true;
}

/**
 * Admin notice for missing BookingX.
 */
function bkx_video_consultation_missing_bookingx_notice() {
	?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'BookingX Video Consultation requires BookingX to be installed and activated.', 'bkx-video-consultation' ); ?></p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 */
function bkx_video_consultation_init() {
	if ( ! bkx_video_consultation_check_dependencies() ) {
		return;
	}

	// Load autoloader.
	require_once BKX_VIDEO_CONSULTATION_PATH . 'src/autoload.php';

	// Initialize the addon.
	$addon = new BookingX\VideoConsultation\VideoConsultationAddon();
	$addon->init();
}
add_action( 'plugins_loaded', 'bkx_video_consultation_init' );

/**
 * Activation hook.
 */
function bkx_video_consultation_activate() {
	global $wpdb;

	$charset_collate = $wpdb->get_charset_collate();

	// Video rooms table.
	$table_rooms = $wpdb->prefix . 'bkx_video_rooms';
	$sql_rooms   = "CREATE TABLE {$table_rooms} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		booking_id bigint(20) UNSIGNED NOT NULL,
		room_id varchar(100) NOT NULL,
		provider varchar(50) NOT NULL DEFAULT 'webrtc',
		host_url text NOT NULL,
		participant_url text NOT NULL,
		password varchar(100) DEFAULT NULL,
		status varchar(20) NOT NULL DEFAULT 'scheduled',
		scheduled_start datetime DEFAULT NULL,
		scheduled_end datetime DEFAULT NULL,
		actual_start datetime DEFAULT NULL,
		actual_end datetime DEFAULT NULL,
		duration_minutes int(11) DEFAULT 0,
		recording_url text DEFAULT NULL,
		metadata longtext DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY booking_id (booking_id),
		KEY room_id (room_id),
		KEY provider (provider),
		KEY status (status),
		KEY scheduled_start (scheduled_start)
	) {$charset_collate};";

	// Recording storage table.
	$table_recordings = $wpdb->prefix . 'bkx_video_recordings';
	$sql_recordings   = "CREATE TABLE {$table_recordings} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		room_id bigint(20) UNSIGNED NOT NULL,
		booking_id bigint(20) UNSIGNED NOT NULL,
		recording_id varchar(100) NOT NULL,
		file_url text NOT NULL,
		file_size bigint(20) UNSIGNED DEFAULT 0,
		duration_seconds int(11) DEFAULT 0,
		format varchar(20) DEFAULT 'mp4',
		status varchar(20) NOT NULL DEFAULT 'processing',
		download_count int(11) DEFAULT 0,
		expires_at datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY room_id (room_id),
		KEY booking_id (booking_id),
		KEY status (status)
	) {$charset_collate};";

	// Session logs table.
	$table_logs = $wpdb->prefix . 'bkx_video_session_logs';
	$sql_logs   = "CREATE TABLE {$table_logs} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		room_id bigint(20) UNSIGNED NOT NULL,
		user_id bigint(20) UNSIGNED DEFAULT NULL,
		participant_type varchar(20) NOT NULL,
		event_type varchar(50) NOT NULL,
		event_data text DEFAULT NULL,
		ip_address varchar(45) DEFAULT NULL,
		user_agent text DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY room_id (room_id),
		KEY user_id (user_id),
		KEY event_type (event_type),
		KEY created_at (created_at)
	) {$charset_collate};";

	// Waiting room table.
	$table_waiting = $wpdb->prefix . 'bkx_video_waiting_room';
	$sql_waiting   = "CREATE TABLE {$table_waiting} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		room_id bigint(20) UNSIGNED NOT NULL,
		participant_name varchar(255) NOT NULL,
		participant_email varchar(255) DEFAULT NULL,
		session_token varchar(100) NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'waiting',
		admitted_at datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY room_id (room_id),
		KEY session_token (session_token),
		KEY status (status)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_rooms );
	dbDelta( $sql_recordings );
	dbDelta( $sql_logs );
	dbDelta( $sql_waiting );

	// Set default options.
	$default_settings = array(
		'enabled'                  => true,
		'default_provider'         => 'webrtc',
		'zoom_enabled'             => false,
		'zoom_api_key'             => '',
		'zoom_api_secret'          => '',
		'google_meet_enabled'      => false,
		'google_client_id'         => '',
		'google_client_secret'     => '',
		'webrtc_enabled'           => true,
		'webrtc_stun_servers'      => 'stun:stun.l.google.com:19302',
		'webrtc_turn_server'       => '',
		'webrtc_turn_username'     => '',
		'webrtc_turn_credential'   => '',
		'enable_recording'         => false,
		'recording_storage'        => 'local',
		'recording_retention_days' => 30,
		'enable_waiting_room'      => true,
		'enable_screen_share'      => true,
		'enable_chat'              => true,
		'enable_virtual_background' => false,
		'max_participants'         => 2,
		'auto_end_after_minutes'   => 60,
		'reminder_before_minutes'  => 15,
		'delete_data_on_uninstall' => false,
	);

	add_option( 'bkx_video_consultation_settings', $default_settings );
	add_option( 'bkx_video_consultation_version', BKX_VIDEO_CONSULTATION_VERSION );

	// Schedule cleanup cron.
	if ( ! wp_next_scheduled( 'bkx_video_cleanup_recordings' ) ) {
		wp_schedule_event( time(), 'daily', 'bkx_video_cleanup_recordings' );
	}
}
register_activation_hook( __FILE__, 'bkx_video_consultation_activate' );

/**
 * Deactivation hook.
 */
function bkx_video_consultation_deactivate() {
	wp_clear_scheduled_hook( 'bkx_video_cleanup_recordings' );
	wp_clear_scheduled_hook( 'bkx_video_check_orphaned_rooms' );
}
register_deactivation_hook( __FILE__, 'bkx_video_consultation_deactivate' );
