<?php
/**
 * Main MYOB Addon Class.
 *
 * @package BookingX\MYOB
 */

namespace BookingX\MYOB;

defined( 'ABSPATH' ) || exit;

/**
 * MYOBAddon class.
 */
class MYOBAddon {

	/**
	 * Single instance.
	 *
	 * @var MYOBAddon
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
	 * @return MYOBAddon
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
		$this->services['api_client']     = new Services\APIClient( $this );
		$this->services['oauth']          = new Services\OAuthService( $this );
		$this->services['customer_sync']  = new Services\CustomerSync( $this );
		$this->services['invoice_sync']   = new Services\InvoiceSync( $this );
		$this->services['payment_sync']   = new Services\PaymentSync( $this );
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
		add_action( 'wp_ajax_bkx_myob_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_myob_disconnect', array( $this, 'ajax_disconnect' ) );
		add_action( 'wp_ajax_bkx_myob_sync_booking', array( $this, 'ajax_sync_booking' ) );
		add_action( 'wp_ajax_bkx_myob_get_accounts', array( $this, 'ajax_get_accounts' ) );
		add_action( 'wp_ajax_bkx_myob_get_tax_codes', array( $this, 'ajax_get_tax_codes' ) );

		// OAuth callback.
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );

		// Booking hooks.
		if ( $this->is_enabled() ) {
			add_action( 'bkx_booking_completed', array( $this, 'on_booking_completed' ), 10, 2 );
			add_action( 'bkx_payment_received', array( $this, 'on_payment_received' ), 10, 3 );
		}

		// Cron hooks.
		add_action( 'bkx_myob_sync_cron', array( $this, 'run_sync_cron' ) );
		add_action( 'bkx_myob_refresh_token', array( $this, 'refresh_token_cron' ) );

		// Schedule cron if not already.
		if ( ! wp_next_scheduled( 'bkx_myob_refresh_token' ) ) {
			wp_schedule_event( time(), 'hourly', 'bkx_myob_refresh_token' );
		}

		// Add meta box to booking edit screen.
		add_action( 'add_meta_boxes', array( $this, 'add_booking_meta_box' ) );

		// Settings tab.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_myob', array( $this, 'render_settings_tab' ) );
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
	 * Check if connected to MYOB.
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
		$settings = get_option( 'bkx_myob_settings', array() );
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Update setting.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 */
	public function update_setting( $key, $value ) {
		$settings         = get_option( 'bkx_myob_settings', array() );
		$settings[ $key ] = $value;
		update_option( 'bkx_myob_settings', $settings );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'MYOB Integration', 'bkx-myob' ),
			__( 'MYOB', 'bkx-myob' ),
			'manage_options',
			'bkx-myob',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include BKX_MYOB_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bookingx_page_bkx-myob' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-myob-admin',
			BKX_MYOB_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_MYOB_VERSION
		);

		wp_enqueue_script(
			'bkx-myob-admin',
			BKX_MYOB_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_MYOB_VERSION,
			true
		);

		wp_localize_script(
			'bkx-myob-admin',
			'bkxMyobAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_myob_admin' ),
				'strings' => array(
					'settingsSaved'     => __( 'Settings saved successfully.', 'bkx-myob' ),
					'error'             => __( 'An error occurred. Please try again.', 'bkx-myob' ),
					'confirmDisconnect' => __( 'Are you sure you want to disconnect from MYOB?', 'bkx-myob' ),
					'disconnected'      => __( 'Disconnected from MYOB.', 'bkx-myob' ),
					'syncing'           => __( 'Syncing...', 'bkx-myob' ),
					'syncSuccess'       => __( 'Booking synced successfully.', 'bkx-myob' ),
					'syncFailed'        => __( 'Sync failed. Please check the logs.', 'bkx-myob' ),
				),
			)
		);
	}

	/**
	 * Handle OAuth callback.
	 */
	public function handle_oauth_callback() {
		if ( ! isset( $_GET['bkx_myob_oauth'] ) || ! isset( $_GET['code'] ) ) {
			return;
		}

		// Verify state.
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		if ( ! wp_verify_nonce( $state, 'bkx_myob_oauth' ) ) {
			wp_die( esc_html__( 'Invalid OAuth state.', 'bkx-myob' ) );
		}

		$code  = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$oauth = $this->get_service( 'oauth' );

		$result = $oauth->exchange_code( $code );

		if ( is_wp_error( $result ) ) {
			wp_redirect( admin_url( 'admin.php?page=bkx-myob&error=' . urlencode( $result->get_error_message() ) ) );
			exit;
		}

		wp_redirect( admin_url( 'admin.php?page=bkx-myob&connected=1' ) );
		exit;
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_myob_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bkx-myob' ) );
		}

		$settings = get_option( 'bkx_myob_settings', array() );

		// Update settings.
		$settings['enabled']              = ! empty( $_POST['enabled'] );
		$settings['api_type']             = sanitize_text_field( $_POST['api_type'] ?? 'essentials' );
		$settings['client_id']            = sanitize_text_field( $_POST['client_id'] ?? '' );
		$settings['client_secret']        = sanitize_text_field( $_POST['client_secret'] ?? '' );
		$settings['auto_sync']            = ! empty( $_POST['auto_sync'] );
		$settings['sync_invoices']        = ! empty( $_POST['sync_invoices'] );
		$settings['sync_customers']       = ! empty( $_POST['sync_customers'] );
		$settings['sync_payments']        = ! empty( $_POST['sync_payments'] );
		$settings['default_income_account'] = sanitize_text_field( $_POST['default_income_account'] ?? '' );
		$settings['default_tax_code']     = sanitize_text_field( $_POST['default_tax_code'] ?? '' );
		$settings['invoice_prefix']       = sanitize_text_field( $_POST['invoice_prefix'] ?? 'BKX-' );
		$settings['sync_on_complete']     = ! empty( $_POST['sync_on_complete'] );
		$settings['sync_on_payment']      = ! empty( $_POST['sync_on_payment'] );

		update_option( 'bkx_myob_settings', $settings );

		wp_send_json_success();
	}

	/**
	 * AJAX: Disconnect from MYOB.
	 */
	public function ajax_disconnect() {
		check_ajax_referer( 'bkx_myob_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bkx-myob' ) );
		}

		// Clear tokens.
		$this->update_setting( 'access_token', '' );
		$this->update_setting( 'refresh_token', '' );
		$this->update_setting( 'token_expires', 0 );
		$this->update_setting( 'company_file_id', '' );
		$this->update_setting( 'company_file_name', '' );

		wp_send_json_success();
	}

	/**
	 * AJAX: Sync booking.
	 */
	public function ajax_sync_booking() {
		check_ajax_referer( 'bkx_myob_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bkx-myob' ) );
		}

		$booking_id = absint( $_POST['booking_id'] ?? 0 );
		if ( ! $booking_id ) {
			wp_send_json_error( __( 'Invalid booking ID.', 'bkx-myob' ) );
		}

		$result = $this->sync_booking( $booking_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get accounts.
	 */
	public function ajax_get_accounts() {
		check_ajax_referer( 'bkx_myob_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bkx-myob' ) );
		}

		$api      = $this->get_service( 'api_client' );
		$accounts = $api->get_income_accounts();

		if ( is_wp_error( $accounts ) ) {
			wp_send_json_error( $accounts->get_error_message() );
		}

		wp_send_json_success( $accounts );
	}

	/**
	 * AJAX: Get tax codes.
	 */
	public function ajax_get_tax_codes() {
		check_ajax_referer( 'bkx_myob_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bkx-myob' ) );
		}

		$api       = $this->get_service( 'api_client' );
		$tax_codes = $api->get_tax_codes();

		if ( is_wp_error( $tax_codes ) ) {
			wp_send_json_error( $tax_codes->get_error_message() );
		}

		wp_send_json_success( $tax_codes );
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
	 * Sync a booking to MYOB.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array|\WP_Error
	 */
	public function sync_booking( $booking_id ) {
		$result = array();

		// Sync customer first.
		if ( $this->get_setting( 'sync_customers', true ) ) {
			$customer_sync = $this->get_service( 'customer_sync' );
			$customer      = $customer_sync->sync_customer( $booking_id );

			if ( is_wp_error( $customer ) ) {
				return $customer;
			}
			$result['customer'] = $customer;
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
		// Get pending bookings to sync.
		$bookings = get_posts(
			array(
				'post_type'      => 'bkx_booking',
				'post_status'    => 'bkx-completed',
				'posts_per_page' => 10,
				'meta_query'     => array(
					array(
						'key'     => '_myob_synced',
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
			'bkx-myob-sync',
			__( 'MYOB Sync', 'bkx-myob' ),
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
		include BKX_MYOB_PLUGIN_DIR . 'templates/admin/meta-box.php';
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['myob'] = __( 'MYOB', 'bkx-myob' );
		return $tabs;
	}

	/**
	 * Render settings tab.
	 */
	public function render_settings_tab() {
		include BKX_MYOB_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}
}
