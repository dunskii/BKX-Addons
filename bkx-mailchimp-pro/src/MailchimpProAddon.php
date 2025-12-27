<?php
/**
 * Main Mailchimp Pro Addon Class
 *
 * @package BookingX\MailchimpPro
 * @since   1.0.0
 */

namespace BookingX\MailchimpPro;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasAjax;
use BookingX\MailchimpPro\Services\MailchimpService;
use BookingX\MailchimpPro\Services\SyncService;
use BookingX\MailchimpPro\Admin\SettingsPage;

/**
 * Main Mailchimp Pro addon class.
 *
 * @since 1.0.0
 */
class MailchimpProAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasAjax;

	/**
	 * Mailchimp service instance.
	 *
	 * @var MailchimpService
	 */
	protected $mailchimp_service;

	/**
	 * Sync service instance.
	 *
	 * @var SyncService
	 */
	protected $sync_service;

	/**
	 * Settings page instance.
	 *
	 * @var SettingsPage
	 */
	protected $settings_page;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Path to main plugin file.
	 */
	public function __construct( string $plugin_file ) {
		$this->addon_id      = 'mailchimp_pro';
		$this->addon_name    = 'Mailchimp Pro';
		$this->version       = BKX_MAILCHIMP_PRO_VERSION;
		$this->text_domain   = 'bkx-mailchimp-pro';

		parent::__construct( $plugin_file );
	}

	/**
	 * Register with BookingX Framework.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function register_with_framework(): void {
		// Register as integration
		add_filter( 'bkx_integrations', function( $integrations ) {
			$integrations['mailchimp'] = [
				'name'        => $this->addon_name,
				'category'    => 'marketing',
				'instance'    => $this,
			];
			return $integrations;
		} );

		// Register settings tab
		add_filter( 'bkx_settings_tabs', function( $tabs ) {
			$tabs['mailchimp_pro'] = __( 'Mailchimp Pro', 'bkx-mailchimp-pro' );
			return $tabs;
		} );
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_hooks(): void {
		// Initialize services
		$this->mailchimp_service = new MailchimpService( $this );
		$this->sync_service      = new SyncService( $this, $this->mailchimp_service );

		// Booking hooks - only if syncing is enabled
		if ( $this->get_setting( 'sync_enabled', false ) ) {
			add_action( 'bkx_booking_created', [ $this->sync_service, 'handle_booking_created' ], 10, 2 );
			add_action( 'bkx_booking_status_changed', [ $this->sync_service, 'handle_booking_status_changed' ], 10, 3 );
			add_action( 'bkx_booking_completed', [ $this->sync_service, 'handle_booking_completed' ], 10, 2 );
			add_action( 'bkx_booking_cancelled', [ $this->sync_service, 'handle_booking_cancelled' ], 10, 2 );
		}

		// Scheduled sync
		add_action( 'bkx_mailchimp_pro_sync', [ $this->sync_service, 'run_scheduled_sync' ] );
		add_action( 'bkx_mailchimp_pro_cleanup', [ $this, 'cleanup_old_logs' ] );
	}

	/**
	 * Initialize admin functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_admin(): void {
		$this->settings_page = new SettingsPage( $this );

		// Register AJAX handlers
		$this->register_ajax_action( 'test_connection', [ $this, 'ajax_test_connection' ], false, 'manage_options' );
		$this->register_ajax_action( 'get_lists', [ $this, 'ajax_get_lists' ], false, 'manage_options' );
		$this->register_ajax_action( 'get_tags', [ $this, 'ajax_get_tags' ], false, 'manage_options' );
		$this->register_ajax_action( 'manual_sync', [ $this, 'ajax_manual_sync' ], false, 'manage_options' );

		// Enqueue admin assets
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	/**
	 * Initialize frontend functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_frontend(): void {
		// No frontend functionality needed
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ): void {
		// Only load on BookingX settings page
		if ( 'bkx_booking_page_bkx_settings' !== $hook ) {
			return;
		}

		// Enqueue admin CSS
		wp_enqueue_style(
			'bkx-mailchimp-pro-admin',
			BKX_MAILCHIMP_PRO_URL . 'assets/css/admin.css',
			[],
			BKX_MAILCHIMP_PRO_VERSION
		);

		// Enqueue admin JS
		wp_enqueue_script(
			'bkx-mailchimp-pro-admin',
			BKX_MAILCHIMP_PRO_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			BKX_MAILCHIMP_PRO_VERSION,
			true
		);

		// Localize AJAX data
		$this->localize_ajax_data( 'bkx-mailchimp-pro-admin', 'bkxMailchimpPro', [
			'test_connection',
			'get_lists',
			'get_tags',
			'manual_sync',
		], [
			'i18n' => [
				'testing'       => __( 'Testing connection...', 'bkx-mailchimp-pro' ),
				'success'       => __( 'Connection successful!', 'bkx-mailchimp-pro' ),
				'failed'        => __( 'Connection failed. Please check your API key.', 'bkx-mailchimp-pro' ),
				'syncing'       => __( 'Syncing...', 'bkx-mailchimp-pro' ),
				'sync_complete' => __( 'Sync completed successfully!', 'bkx-mailchimp-pro' ),
			],
		] );
	}

	/**
	 * AJAX: Test Mailchimp connection.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_test_connection(): void {
		$api_key = $this->get_post_param( 'api_key', '', 'string' );

		if ( empty( $api_key ) ) {
			$api_key = $this->get_setting( 'api_key', '' );
		}

		if ( empty( $api_key ) ) {
			$this->ajax_error( 'missing_api_key', __( 'API key is required.', 'bkx-mailchimp-pro' ) );
		}

		// Test connection
		$result = $this->mailchimp_service->test_connection( $api_key );

		if ( is_wp_error( $result ) ) {
			$this->ajax_error( 'connection_failed', $result->get_error_message() );
		}

		$this->ajax_success( [
			'message'      => __( 'Connection successful!', 'bkx-mailchimp-pro' ),
			'account_name' => $result['account_name'] ?? '',
		] );
	}

	/**
	 * AJAX: Get Mailchimp lists/audiences.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_lists(): void {
		$lists = $this->mailchimp_service->get_lists();

		if ( is_wp_error( $lists ) ) {
			$this->ajax_error( 'fetch_failed', $lists->get_error_message() );
		}

		$this->ajax_success( [
			'lists' => $lists,
		] );
	}

	/**
	 * AJAX: Get Mailchimp tags.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_tags(): void {
		$list_id = $this->get_post_param( 'list_id', '', 'string' );

		if ( empty( $list_id ) ) {
			$this->ajax_error( 'missing_list_id', __( 'List ID is required.', 'bkx-mailchimp-pro' ) );
		}

		$tags = $this->mailchimp_service->get_tags( $list_id );

		if ( is_wp_error( $tags ) ) {
			$this->ajax_error( 'fetch_failed', $tags->get_error_message() );
		}

		$this->ajax_success( [
			'tags' => $tags,
		] );
	}

	/**
	 * AJAX: Run manual sync.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_manual_sync(): void {
		$sync_type = $this->get_post_param( 'sync_type', 'all', 'string' );

		// Run sync
		$result = $this->sync_service->manual_sync( $sync_type );

		if ( is_wp_error( $result ) ) {
			$this->ajax_error( 'sync_failed', $result->get_error_message() );
		}

		$this->ajax_success( [
			'message' => sprintf(
				/* translators: 1: synced count, 2: failed count */
				__( 'Synced %1$d contacts. Failed: %2$d', 'bkx-mailchimp-pro' ),
				$result['synced'],
				$result['failed']
			),
			'result'  => $result,
		] );
	}

	/**
	 * Get database migrations.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_migrations(): array {
		return [
			'1.0.0' => [
				\BookingX\MailchimpPro\Migrations\CreateSyncLogTable::class,
			],
		];
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return [
			'api_key'              => '',
			'sync_enabled'         => false,
			'default_list_id'      => '',
			'double_optin'         => true,
			'tag_on_booking'       => 'booking-created',
			'tag_on_completed'     => 'booking-completed',
			'tag_on_cancelled'     => 'booking-cancelled',
			'sync_frequency'       => 'realtime', // realtime, hourly, daily
			'merge_fields'         => [
				'BOOKINGS' => true,
				'LASTBOOK' => true,
				'TOTSPENT' => true,
			],
		];
	}

	/**
	 * Cleanup old sync logs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function cleanup_old_logs(): void {
		global $wpdb;

		$table_name = $this->get_table_name( 'mailchimp_sync_log' );
		$days_to_keep = absint( $this->get_setting( 'log_retention_days', 30 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$table_name,
				$days_to_keep
			)
		);
	}
}
