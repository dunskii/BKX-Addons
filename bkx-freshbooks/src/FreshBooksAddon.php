<?php
/**
 * Main FreshBooks Addon Class.
 *
 * @package BookingX\FreshBooks
 */

namespace BookingX\FreshBooks;

defined( 'ABSPATH' ) || exit;

/**
 * FreshBooksAddon class.
 */
class FreshBooksAddon {

	/**
	 * Single instance.
	 *
	 * @var FreshBooksAddon
	 */
	private static $instance = null;

	/**
	 * Services container.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Get single instance.
	 *
	 * @return FreshBooksAddon
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['api_client']   = new Services\APIClient( $this );
		$this->services['oauth']        = new Services\OAuthService( $this );
		$this->services['client_sync']  = new Services\ClientSync( $this );
		$this->services['invoice_sync'] = new Services\InvoiceSync( $this );
		$this->services['payment_sync'] = new Services\PaymentSync( $this );
	}

	/**
	 * Get a service.
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_freshbooks_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_freshbooks_disconnect', array( $this, 'ajax_disconnect' ) );
		add_action( 'wp_ajax_bkx_freshbooks_sync_booking', array( $this, 'ajax_sync_booking' ) );
		add_action( 'wp_ajax_bkx_freshbooks_get_services', array( $this, 'ajax_get_services' ) );

		// OAuth callback.
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );

		// Booking hooks.
		if ( $this->is_enabled() ) {
			add_action( 'bkx_booking_completed', array( $this, 'on_booking_completed' ), 10, 2 );
			add_action( 'bkx_payment_received', array( $this, 'on_payment_received' ), 10, 3 );
		}

		// Cron hooks.
		add_action( 'bkx_freshbooks_sync_cron', array( $this, 'run_sync_cron' ) );
		add_action( 'bkx_freshbooks_refresh_token', array( $this, 'refresh_token_cron' ) );

		// Schedule token refresh.
		if ( ! wp_next_scheduled( 'bkx_freshbooks_refresh_token' ) ) {
			wp_schedule_event( time(), 'hourly', 'bkx_freshbooks_refresh_token' );
		}

		// Meta box.
		add_action( 'add_meta_boxes', array( $this, 'add_booking_meta_box' ) );

		// Settings tab.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_freshbooks', array( $this, 'render_settings_tab' ) );
	}

	/**
	 * Check if integration is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) $this->get_setting( 'enabled', false );
	}

	/**
	 * Check if connected to FreshBooks.
	 *
	 * @return bool
	 */
	public function is_connected() {
		$access_token = $this->get_setting( 'access_token' );
		return ! empty( $access_token );
	}

	/**
	 * Get setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		$settings = get_option( 'bkx_freshbooks_settings', array() );
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Update setting.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 */
	public function update_setting( $key, $value ) {
		$settings         = get_option( 'bkx_freshbooks_settings', array() );
		$settings[ $key ] = $value;
		update_option( 'bkx_freshbooks_settings', $settings );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'FreshBooks Integration', 'bkx-freshbooks' ),
			__( 'FreshBooks', 'bkx-freshbooks' ),
			'manage_options',
			'bkx-freshbooks',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include BKX_FRESHBOOKS_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bookingx_page_bkx-freshbooks' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-freshbooks-admin',
			BKX_FRESHBOOKS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_FRESHBOOKS_VERSION
		);

		wp_enqueue_script(
			'bkx-freshbooks-admin',
			BKX_FRESHBOOKS_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_FRESHBOOKS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-freshbooks-admin',
			'bkxFreshBooksAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_freshbooks_admin' ),
				'strings' => array(
					'settingsSaved'     => __( 'Settings saved successfully.', 'bkx-freshbooks' ),
					'error'             => __( 'An error occurred. Please try again.', 'bkx-freshbooks' ),
					'confirmDisconnect' => __( 'Are you sure you want to disconnect from FreshBooks?', 'bkx-freshbooks' ),
					'disconnected'      => __( 'Disconnected from FreshBooks.', 'bkx-freshbooks' ),
					'syncing'           => __( 'Syncing...', 'bkx-freshbooks' ),
					'syncSuccess'       => __( 'Booking synced successfully.', 'bkx-freshbooks' ),
					'syncFailed'        => __( 'Sync failed. Please check the logs.', 'bkx-freshbooks' ),
				),
			)
		);
	}

	/**
	 * Handle OAuth callback.
	 */
	public function handle_oauth_callback() {
		if ( ! isset( $_GET['bkx_freshbooks_oauth'] ) || ! isset( $_GET['code'] ) ) {
			return;
		}

		// Verify state.
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		if ( ! wp_verify_nonce( $state, 'bkx_freshbooks_oauth' ) ) {
			wp_die( esc_html__( 'Invalid OAuth state.', 'bkx-freshbooks' ) );
		}

		$code  = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$oauth = $this->get_service( 'oauth' );

		$result = $oauth->exchange_code( $code );

		if ( is_wp_error( $result ) ) {
			wp_redirect( admin_url( 'admin.php?page=bkx-freshbooks&error=' . urlencode( $result->get_error_message() ) ) );
			exit;
		}

		wp_redirect( admin_url( 'admin.php?page=bkx-freshbooks&connected=1' ) );
		exit;
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_freshbooks_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bkx-freshbooks' ) );
		}

		$settings = get_option( 'bkx_freshbooks_settings', array() );

		// Update settings.
		$settings['enabled']              = ! empty( $_POST['enabled'] );
		$settings['client_id']            = sanitize_text_field( $_POST['client_id'] ?? '' );
		$settings['client_secret']        = sanitize_text_field( $_POST['client_secret'] ?? '' );
		$settings['auto_sync']            = ! empty( $_POST['auto_sync'] );
		$settings['sync_clients']         = ! empty( $_POST['sync_clients'] );
		$settings['sync_invoices']        = ! empty( $_POST['sync_invoices'] );
		$settings['sync_payments']        = ! empty( $_POST['sync_payments'] );
		$settings['sync_expenses']        = ! empty( $_POST['sync_expenses'] );
		$settings['default_service_code'] = sanitize_text_field( $_POST['default_service_code'] ?? '' );
		$settings['invoice_due_days']     = absint( $_POST['invoice_due_days'] ?? 14 );
		$settings['send_invoice_email']   = ! empty( $_POST['send_invoice_email'] );
		$settings['sync_on_complete']     = ! empty( $_POST['sync_on_complete'] );
		$settings['sync_on_payment']      = ! empty( $_POST['sync_on_payment'] );

		update_option( 'bkx_freshbooks_settings', $settings );

		wp_send_json_success();
	}

	/**
	 * AJAX: Disconnect from FreshBooks.
	 */
	public function ajax_disconnect() {
		check_ajax_referer( 'bkx_freshbooks_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bkx-freshbooks' ) );
		}

		// Clear tokens.
		$this->update_setting( 'access_token', '' );
		$this->update_setting( 'refresh_token', '' );
		$this->update_setting( 'token_expires', 0 );
		$this->update_setting( 'account_id', '' );
		$this->update_setting( 'business_id', '' );

		wp_send_json_success();
	}

	/**
	 * AJAX: Sync booking.
	 */
	public function ajax_sync_booking() {
		check_ajax_referer( 'bkx_freshbooks_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bkx-freshbooks' ) );
		}

		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		if ( ! $booking_id ) {
			wp_send_json_error( __( 'Invalid booking ID.', 'bkx-freshbooks' ) );
		}

		$result = $this->sync_booking( $booking_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get FreshBooks services.
	 */
	public function ajax_get_services() {
		check_ajax_referer( 'bkx_freshbooks_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bkx-freshbooks' ) );
		}

		$api      = $this->get_service( 'api_client' );
		$services = $api->get_services();

		if ( is_wp_error( $services ) ) {
			wp_send_json_error( $services->get_error_message() );
		}

		wp_send_json_success( $services );
	}

	/**
	 * Handle booking completed event.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_completed( $booking_id, $booking_data ) {
		if ( ! $this->get_setting( 'sync_on_complete', true ) ) {
			return;
		}

		$this->sync_booking( $booking_id );
	}

	/**
	 * Handle payment received event.
	 *
	 * @param int    $booking_id     Booking ID.
	 * @param float  $amount         Payment amount.
	 * @param string $transaction_id Transaction ID.
	 */
	public function on_payment_received( $booking_id, $amount, $transaction_id ) {
		if ( ! $this->get_setting( 'sync_on_payment', true ) ) {
			return;
		}

		$payment_sync = $this->get_service( 'payment_sync' );
		if ( $payment_sync ) {
			$payment_sync->sync_payment( $booking_id, $amount, $transaction_id );
		}
	}

	/**
	 * Sync a booking to FreshBooks.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array|\WP_Error
	 */
	public function sync_booking( $booking_id ) {
		$result = array();

		// Sync client first.
		if ( $this->get_setting( 'sync_clients', true ) ) {
			$client_sync = $this->get_service( 'client_sync' );
			$client      = $client_sync->sync_client( $booking_id );

			if ( is_wp_error( $client ) ) {
				return $client;
			}
			$result['client'] = $client;
		}

		// Sync invoice.
		if ( $this->get_setting( 'sync_invoices', true ) ) {
			$invoice_sync = $this->get_service( 'invoice_sync' );
			$invoice      = $invoice_sync->sync_invoice( $booking_id );

			if ( is_wp_error( $invoice ) ) {
				return $invoice;
			}
			$result['invoice'] = $invoice;
		}

		return $result;
	}

	/**
	 * Run sync cron.
	 */
	public function run_sync_cron() {
		$bookings = get_posts(
			array(
				'post_type'      => 'bkx_booking',
				'post_status'    => 'bkx-completed',
				'posts_per_page' => 10,
				'meta_query'     => array(
					array(
						'key'     => '_freshbooks_synced',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		foreach ( $bookings as $booking ) {
			$this->sync_booking( $booking->ID );
		}
	}

	/**
	 * Refresh token cron.
	 */
	public function refresh_token_cron() {
		$oauth = $this->get_service( 'oauth' );
		if ( $oauth ) {
			$oauth->refresh_token_if_needed();
		}
	}

	/**
	 * Add booking meta box.
	 */
	public function add_booking_meta_box() {
		add_meta_box(
			'bkx-freshbooks-sync',
			__( 'FreshBooks Sync', 'bkx-freshbooks' ),
			array( $this, 'render_booking_meta_box' ),
			'bkx_booking',
			'side',
			'default'
		);
	}

	/**
	 * Render booking meta box.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function render_booking_meta_box( $post ) {
		include BKX_FRESHBOOKS_PLUGIN_DIR . 'templates/admin/meta-box.php';
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['freshbooks'] = __( 'FreshBooks', 'bkx-freshbooks' );
		return $tabs;
	}

	/**
	 * Render settings tab.
	 */
	public function render_settings_tab() {
		include BKX_FRESHBOOKS_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}
}
