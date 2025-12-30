<?php
/**
 * Main Backup & Recovery Addon class.
 *
 * @package BookingX\BackupRecovery
 */

namespace BookingX\BackupRecovery;

use BookingX\BackupRecovery\Services\BackupManager;
use BookingX\BackupRecovery\Services\RestoreManager;
use BookingX\BackupRecovery\Services\ExportService;
use BookingX\BackupRecovery\Services\ImportService;

defined( 'ABSPATH' ) || exit;

/**
 * BackupRecoveryAddon class.
 */
class BackupRecoveryAddon {

	/**
	 * Singleton instance.
	 *
	 * @var BackupRecoveryAddon
	 */
	private static $instance = null;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Service instances.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Get singleton instance.
	 *
	 * @return BackupRecoveryAddon
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->settings = get_option( 'bkx_backup_recovery_settings', array() );
	}

	/**
	 * Initialize the addon.
	 */
	public function init() {
		// Initialize services.
		$this->init_services();

		// Admin hooks.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_admin_menu' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
		}

		// BookingX integration.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_backups', array( $this, 'render_settings_tab' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_backup_create', array( $this, 'ajax_create_backup' ) );
		add_action( 'wp_ajax_bkx_backup_delete', array( $this, 'ajax_delete_backup' ) );
		add_action( 'wp_ajax_bkx_backup_download', array( $this, 'ajax_download_backup' ) );
		add_action( 'wp_ajax_bkx_backup_restore', array( $this, 'ajax_restore_backup' ) );
		add_action( 'wp_ajax_bkx_backup_export', array( $this, 'ajax_export_data' ) );
		add_action( 'wp_ajax_bkx_backup_import', array( $this, 'ajax_import_data' ) );
		add_action( 'wp_ajax_bkx_backup_status', array( $this, 'ajax_backup_status' ) );

		// Cron handlers.
		add_action( 'bkx_backup_scheduled_backup', array( $this, 'run_scheduled_backup' ) );
		add_action( 'bkx_backup_cleanup_old_backups', array( $this, 'cleanup_old_backups' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['backup']  = new BackupManager( $this->settings );
		$this->services['restore'] = new RestoreManager();
		$this->services['export']  = new ExportService();
		$this->services['import']  = new ImportService();
	}

	/**
	 * Get a service instance.
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Register admin menu.
	 */
	public function register_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Backup & Recovery', 'bkx-backup-recovery' ),
			__( 'Backup & Recovery', 'bkx-backup-recovery' ),
			'manage_options',
			'bkx-backup-recovery',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-backup-recovery' ) === false && strpos( $hook, 'bookingx' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-backup-admin',
			BKX_BACKUP_URL . 'assets/css/admin.css',
			array(),
			BKX_BACKUP_VERSION
		);

		wp_enqueue_script(
			'bkx-backup-admin',
			BKX_BACKUP_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_BACKUP_VERSION,
			true
		);

		wp_localize_script(
			'bkx-backup-admin',
			'bkxBackupAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_backup_admin' ),
				'i18n'    => array(
					'confirm'         => __( 'Are you sure?', 'bkx-backup-recovery' ),
					'confirmRestore'  => __( 'Restore this backup? This will overwrite current data.', 'bkx-backup-recovery' ),
					'confirmDelete'   => __( 'Delete this backup permanently?', 'bkx-backup-recovery' ),
					'creating'        => __( 'Creating backup...', 'bkx-backup-recovery' ),
					'restoring'       => __( 'Restoring backup...', 'bkx-backup-recovery' ),
					'success'         => __( 'Success!', 'bkx-backup-recovery' ),
					'error'           => __( 'An error occurred.', 'bkx-backup-recovery' ),
					'backupComplete'  => __( 'Backup created successfully!', 'bkx-backup-recovery' ),
					'restoreComplete' => __( 'Restore completed successfully!', 'bkx-backup-recovery' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'backups';

		include BKX_BACKUP_PATH . 'templates/admin/page.php';
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'bkx_backup_recovery',
			'bkx_backup_recovery_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Settings input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['backup_frequency']   = sanitize_text_field( $input['backup_frequency'] ?? 'daily' );
		$sanitized['backup_time']        = sanitize_text_field( $input['backup_time'] ?? '03:00' );
		$sanitized['backup_retention']   = absint( $input['backup_retention'] ?? 30 );
		$sanitized['compression']        = sanitize_text_field( $input['compression'] ?? 'zip' );
		$sanitized['max_backup_size']    = absint( $input['max_backup_size'] ?? 100 );

		// Boolean settings.
		$booleans = array(
			'include_bookings',
			'include_customers',
			'include_settings',
			'include_services',
			'include_staff',
			'email_notification',
		);

		foreach ( $booleans as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		$sanitized['notify_email']    = sanitize_email( $input['notify_email'] ?? '' );
		$sanitized['remote_storage']  = sanitize_text_field( $input['remote_storage'] ?? 'none' );

		// Reschedule cron if frequency changed.
		$old_settings = get_option( 'bkx_backup_recovery_settings', array() );
		if ( ( $old_settings['backup_frequency'] ?? '' ) !== $sanitized['backup_frequency']
			|| ( $old_settings['backup_time'] ?? '' ) !== $sanitized['backup_time'] ) {
			// Schedule will be updated on next page load.
			wp_clear_scheduled_hook( 'bkx_backup_scheduled_backup' );
		}

		return $sanitized;
	}

	/**
	 * Add settings tab to BookingX.
	 *
	 * @param array $tabs Settings tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['backups'] = __( 'Backups', 'bkx-backup-recovery' );
		return $tabs;
	}

	/**
	 * Render settings tab content.
	 */
	public function render_settings_tab() {
		include BKX_BACKUP_PATH . 'templates/admin/settings-tab.php';
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-backup/v1',
			'/backups',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'rest_get_backups' ),
					'permission_callback' => function() {
						return current_user_can( 'manage_options' );
					},
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'rest_create_backup' ),
					'permission_callback' => function() {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);
	}

	/**
	 * AJAX: Create backup.
	 */
	public function ajax_create_backup() {
		check_ajax_referer( 'bkx_backup_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-backup-recovery' ) ) );
		}

		$backup_type = isset( $_POST['backup_type'] ) ? sanitize_text_field( wp_unslash( $_POST['backup_type'] ) ) : 'full';

		$backup = $this->get_service( 'backup' );
		$result = $backup->create_backup( $backup_type );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'   => __( 'Backup created successfully!', 'bkx-backup-recovery' ),
			'backup_id' => $result,
		) );
	}

	/**
	 * AJAX: Delete backup.
	 */
	public function ajax_delete_backup() {
		check_ajax_referer( 'bkx_backup_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-backup-recovery' ) ) );
		}

		$backup_id = isset( $_POST['backup_id'] ) ? absint( $_POST['backup_id'] ) : 0;
		if ( ! $backup_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid backup ID.', 'bkx-backup-recovery' ) ) );
		}

		$backup = $this->get_service( 'backup' );
		$result = $backup->delete_backup( $backup_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Backup deleted.', 'bkx-backup-recovery' ) ) );
	}

	/**
	 * AJAX: Download backup.
	 */
	public function ajax_download_backup() {
		check_ajax_referer( 'bkx_backup_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-backup-recovery' ) ) );
		}

		$backup_id = isset( $_POST['backup_id'] ) ? absint( $_POST['backup_id'] ) : 0;
		if ( ! $backup_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid backup ID.', 'bkx-backup-recovery' ) ) );
		}

		$backup = $this->get_service( 'backup' );
		$url    = $backup->get_download_url( $backup_id );

		if ( ! $url ) {
			wp_send_json_error( array( 'message' => __( 'Backup file not found.', 'bkx-backup-recovery' ) ) );
		}

		wp_send_json_success( array( 'download_url' => $url ) );
	}

	/**
	 * AJAX: Restore backup.
	 */
	public function ajax_restore_backup() {
		check_ajax_referer( 'bkx_backup_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-backup-recovery' ) ) );
		}

		$backup_id = isset( $_POST['backup_id'] ) ? absint( $_POST['backup_id'] ) : 0;
		if ( ! $backup_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid backup ID.', 'bkx-backup-recovery' ) ) );
		}

		$restore = $this->get_service( 'restore' );
		$result  = $restore->restore_backup( $backup_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'        => __( 'Restore completed successfully!', 'bkx-backup-recovery' ),
			'items_restored' => $result,
		) );
	}

	/**
	 * AJAX: Export data.
	 */
	public function ajax_export_data() {
		check_ajax_referer( 'bkx_backup_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-backup-recovery' ) ) );
		}

		$export_type = isset( $_POST['export_type'] ) ? sanitize_text_field( wp_unslash( $_POST['export_type'] ) ) : 'all';
		$format      = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'csv';

		$export = $this->get_service( 'export' );
		$result = $export->export( $export_type, $format );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'download_url' => $result,
			'message'      => __( 'Export completed!', 'bkx-backup-recovery' ),
		) );
	}

	/**
	 * AJAX: Import data.
	 */
	public function ajax_import_data() {
		check_ajax_referer( 'bkx_backup_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-backup-recovery' ) ) );
		}

		if ( ! isset( $_FILES['import_file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'bkx-backup-recovery' ) ) );
		}

		$import = $this->get_service( 'import' );
		$result = $import->import( $_FILES['import_file'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'        => __( 'Import completed!', 'bkx-backup-recovery' ),
			'items_imported' => $result,
		) );
	}

	/**
	 * AJAX: Get backup status.
	 */
	public function ajax_backup_status() {
		check_ajax_referer( 'bkx_backup_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-backup-recovery' ) ) );
		}

		$backup_id = isset( $_POST['backup_id'] ) ? absint( $_POST['backup_id'] ) : 0;
		if ( ! $backup_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid backup ID.', 'bkx-backup-recovery' ) ) );
		}

		$backup = $this->get_service( 'backup' );
		$status = $backup->get_backup_status( $backup_id );

		wp_send_json_success( $status );
	}

	/**
	 * Run scheduled backup.
	 */
	public function run_scheduled_backup() {
		$backup = $this->get_service( 'backup' );
		$result = $backup->create_backup( 'full' );

		// Send notification if enabled.
		if ( ! empty( $this->settings['email_notification'] ) && ! empty( $this->settings['notify_email'] ) ) {
			$this->send_backup_notification( $result );
		}
	}

	/**
	 * Send backup notification email.
	 *
	 * @param int|\WP_Error $result Backup result.
	 */
	private function send_backup_notification( $result ) {
		$to      = $this->settings['notify_email'];
		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Backup Report', 'bkx-backup-recovery' ),
			get_bloginfo( 'name' )
		);

		if ( is_wp_error( $result ) ) {
			$message = sprintf(
				/* translators: %s: error message */
				__( "Scheduled backup failed.\n\nError: %s", 'bkx-backup-recovery' ),
				$result->get_error_message()
			);
		} else {
			$backup = $this->get_service( 'backup' );
			$info   = $backup->get_backup( $result );

			$message = sprintf(
				/* translators: 1: backup ID, 2: file size, 3: items count */
				__( "Scheduled backup completed successfully.\n\nBackup ID: %1\$s\nFile Size: %2\$s\nItems: %3\$d\n\nView backups: %4\$s", 'bkx-backup-recovery' ),
				$result,
				size_format( $info['file_size'] ?? 0 ),
				$info['items_count'] ?? 0,
				admin_url( 'admin.php?page=bkx-backup-recovery' )
			);
		}

		wp_mail( $to, $subject, $message );
	}

	/**
	 * Cleanup old backups.
	 */
	public function cleanup_old_backups() {
		$retention = absint( $this->settings['backup_retention'] ?? 30 );
		if ( ! $retention ) {
			return;
		}

		$backup = $this->get_service( 'backup' );
		$backup->cleanup_old_backups( $retention );
	}

	/**
	 * REST: Get backups.
	 *
	 * @return \WP_REST_Response
	 */
	public function rest_get_backups() {
		$backup  = $this->get_service( 'backup' );
		$backups = $backup->get_backups();

		return rest_ensure_response( $backups );
	}

	/**
	 * REST: Create backup.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_create_backup( $request ) {
		$backup_type = $request->get_param( 'type' ) ?? 'full';

		$backup = $this->get_service( 'backup' );
		$result = $backup->create_backup( $backup_type );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				400
			);
		}

		return rest_ensure_response( array( 'backup_id' => $result ) );
	}
}
