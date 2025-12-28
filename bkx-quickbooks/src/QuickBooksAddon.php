<?php
/**
 * QuickBooks Addon Main Class.
 *
 * @package BookingX\QuickBooks
 * @since   1.0.0
 */

namespace BookingX\QuickBooks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use BookingX\QuickBooks\Services\OAuthService;
use BookingX\QuickBooks\Services\CustomerSync;
use BookingX\QuickBooks\Services\InvoiceSync;
use BookingX\QuickBooks\Services\PaymentSync;
use BookingX\QuickBooks\Services\ProductSync;

/**
 * QuickBooksAddon Class.
 */
class QuickBooksAddon {

	/**
	 * Services.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Initialize addon.
	 */
	public function init() {
		$this->init_services();
		$this->register_hooks();
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['oauth']        = new OAuthService();
		$this->services['customer']     = new CustomerSync();
		$this->services['invoice']      = new InvoiceSync();
		$this->services['payment']      = new PaymentSync();
		$this->services['product']      = new ProductSync();
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Settings.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// OAuth callback.
		add_action( 'admin_init', array( $this, 'handle_oauth_callback' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_qb_connect', array( $this, 'ajax_connect' ) );
		add_action( 'wp_ajax_bkx_qb_disconnect', array( $this, 'ajax_disconnect' ) );
		add_action( 'wp_ajax_bkx_qb_sync_customers', array( $this, 'ajax_sync_customers' ) );
		add_action( 'wp_ajax_bkx_qb_sync_booking', array( $this, 'ajax_sync_booking' ) );
		add_action( 'wp_ajax_bkx_qb_get_sync_status', array( $this, 'ajax_get_sync_status' ) );
		add_action( 'wp_ajax_bkx_qb_manual_sync', array( $this, 'ajax_manual_sync' ) );

		// BookingX hooks for auto-sync.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_status_changed', array( $this, 'on_booking_status_changed' ), 10, 3 );
		add_action( 'bkx_payment_completed', array( $this, 'on_payment_completed' ), 10, 2 );

		// Cron for batch sync.
		add_action( 'bkx_qb_batch_sync', array( $this, 'run_batch_sync' ) );
		if ( ! wp_next_scheduled( 'bkx_qb_batch_sync' ) ) {
			wp_schedule_event( time(), 'hourly', 'bkx_qb_batch_sync' );
		}
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'QuickBooks', 'bkx-quickbooks' ),
			__( 'QuickBooks', 'bkx-quickbooks' ),
			'manage_options',
			'bkx-quickbooks',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bookingx_page_bkx-quickbooks' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-quickbooks-admin',
			BKX_QUICKBOOKS_URL . 'assets/css/admin.css',
			array(),
			BKX_QUICKBOOKS_VERSION
		);

		wp_enqueue_script(
			'bkx-quickbooks-admin',
			BKX_QUICKBOOKS_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_QUICKBOOKS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-quickbooks-admin',
			'bkxQB',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_qb_admin' ),
				'i18n'    => array(
					'connecting'   => __( 'Connecting...', 'bkx-quickbooks' ),
					'syncing'      => __( 'Syncing...', 'bkx-quickbooks' ),
					'success'      => __( 'Success!', 'bkx-quickbooks' ),
					'error'        => __( 'An error occurred', 'bkx-quickbooks' ),
					'confirmSync'  => __( 'Are you sure you want to sync all data?', 'bkx-quickbooks' ),
					'disconnected' => __( 'Disconnected from QuickBooks', 'bkx-quickbooks' ),
				),
			)
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'bkx_quickbooks', 'bkx_qb_client_id' );
		register_setting( 'bkx_quickbooks', 'bkx_qb_client_secret' );
		register_setting( 'bkx_quickbooks', 'bkx_qb_environment' );
		register_setting( 'bkx_quickbooks', 'bkx_qb_auto_sync_customers' );
		register_setting( 'bkx_quickbooks', 'bkx_qb_auto_sync_invoices' );
		register_setting( 'bkx_quickbooks', 'bkx_qb_auto_sync_payments' );
		register_setting( 'bkx_quickbooks', 'bkx_qb_default_income_account' );
		register_setting( 'bkx_quickbooks', 'bkx_qb_default_tax_code' );
	}

	/**
	 * Handle OAuth callback.
	 */
	public function handle_oauth_callback() {
		if ( ! isset( $_GET['page'] ) || 'bkx-quickbooks' !== $_GET['page'] ) {
			return;
		}

		if ( isset( $_GET['code'] ) && isset( $_GET['realmId'] ) ) {
			$code     = sanitize_text_field( wp_unslash( $_GET['code'] ) );
			$realm_id = sanitize_text_field( wp_unslash( $_GET['realmId'] ) );

			$oauth  = $this->services['oauth'];
			$result = $oauth->exchange_code_for_tokens( $code, $realm_id );

			if ( $result ) {
				add_settings_error(
					'bkx_quickbooks',
					'connected',
					__( 'Successfully connected to QuickBooks!', 'bkx-quickbooks' ),
					'success'
				);
			} else {
				add_settings_error(
					'bkx_quickbooks',
					'connection_failed',
					__( 'Failed to connect to QuickBooks. Please try again.', 'bkx-quickbooks' ),
					'error'
				);
			}

			// Redirect to remove code from URL.
			wp_safe_redirect( admin_url( 'admin.php?page=bkx-quickbooks' ) );
			exit;
		}
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include BKX_QUICKBOOKS_PATH . 'templates/admin/settings.php';
	}

	/**
	 * AJAX: Connect to QuickBooks.
	 */
	public function ajax_connect() {
		check_ajax_referer( 'bkx_qb_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$oauth    = $this->services['oauth'];
		$auth_url = $oauth->get_authorization_url();

		wp_send_json_success( array( 'auth_url' => $auth_url ) );
	}

	/**
	 * AJAX: Disconnect from QuickBooks.
	 */
	public function ajax_disconnect() {
		check_ajax_referer( 'bkx_qb_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$oauth = $this->services['oauth'];
		$oauth->revoke_tokens();

		wp_send_json_success();
	}

	/**
	 * AJAX: Sync customers.
	 */
	public function ajax_sync_customers() {
		check_ajax_referer( 'bkx_qb_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$customer_sync = $this->services['customer'];
		$result        = $customer_sync->sync_all_customers();

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Sync single booking.
	 */
	public function ajax_sync_booking() {
		check_ajax_referer( 'bkx_qb_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$booking_id = absint( $_POST['booking_id'] ?? 0 );

		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => 'Invalid booking ID' ) );
		}

		$invoice_sync = $this->services['invoice'];
		$result       = $invoice_sync->sync_booking( $booking_id );

		if ( $result ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( array( 'message' => 'Sync failed' ) );
		}
	}

	/**
	 * AJAX: Get sync status.
	 */
	public function ajax_get_sync_status() {
		check_ajax_referer( 'bkx_qb_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		global $wpdb;

		$log_table     = $wpdb->prefix . 'bkx_qb_sync_log';
		$mapping_table = $wpdb->prefix . 'bkx_qb_mapping';

		// Get sync stats.
		$stats = array(
			'customers_synced' => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE entity_type = 'customer'",
					$mapping_table
				)
			),
			'invoices_synced'  => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE entity_type = 'invoice'",
					$mapping_table
				)
			),
			'payments_synced'  => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE entity_type = 'payment'",
					$mapping_table
				)
			),
			'pending_syncs'    => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE sync_status = 'pending'",
					$log_table
				)
			),
			'failed_syncs'     => $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE sync_status = 'failed'",
					$log_table
				)
			),
		);

		// Get recent sync log.
		$recent_logs = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i ORDER BY created_at DESC LIMIT 20",
				$log_table
			),
			ARRAY_A
		);

		wp_send_json_success(
			array(
				'stats' => $stats,
				'logs'  => $recent_logs,
			)
		);
	}

	/**
	 * AJAX: Manual full sync.
	 */
	public function ajax_manual_sync() {
		check_ajax_referer( 'bkx_qb_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$sync_type = sanitize_text_field( $_POST['sync_type'] ?? 'all' );

		$results = array();

		if ( 'all' === $sync_type || 'customers' === $sync_type ) {
			$customer_sync       = $this->services['customer'];
			$results['customers'] = $customer_sync->sync_all_customers();
		}

		if ( 'all' === $sync_type || 'invoices' === $sync_type ) {
			$invoice_sync       = $this->services['invoice'];
			$results['invoices'] = $invoice_sync->sync_all_bookings();
		}

		if ( 'all' === $sync_type || 'products' === $sync_type ) {
			$product_sync       = $this->services['product'];
			$results['products'] = $product_sync->sync_all_services();
		}

		wp_send_json_success( $results );
	}

	/**
	 * On booking created.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		if ( ! get_option( 'bkx_qb_auto_sync_invoices' ) ) {
			return;
		}

		if ( ! $this->services['oauth']->is_connected() ) {
			return;
		}

		// Sync customer first.
		$email = $booking_data['customer_email'] ?? '';
		if ( $email && get_option( 'bkx_qb_auto_sync_customers' ) ) {
			$this->services['customer']->sync_customer_by_email( $email );
		}

		// Create invoice.
		$this->services['invoice']->sync_booking( $booking_id );
	}

	/**
	 * On booking status changed.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 */
	public function on_booking_status_changed( $booking_id, $old_status, $new_status ) {
		if ( ! $this->services['oauth']->is_connected() ) {
			return;
		}

		// Update invoice status in QuickBooks if needed.
		if ( 'bkx-cancelled' === $new_status ) {
			$this->services['invoice']->void_invoice( $booking_id );
		}
	}

	/**
	 * On payment completed.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $payment_data Payment data.
	 */
	public function on_payment_completed( $booking_id, $payment_data ) {
		if ( ! get_option( 'bkx_qb_auto_sync_payments' ) ) {
			return;
		}

		if ( ! $this->services['oauth']->is_connected() ) {
			return;
		}

		$this->services['payment']->sync_payment( $booking_id, $payment_data );
	}

	/**
	 * Run batch sync (cron).
	 */
	public function run_batch_sync() {
		if ( ! $this->services['oauth']->is_connected() ) {
			return;
		}

		global $wpdb;
		$log_table = $wpdb->prefix . 'bkx_qb_sync_log';

		// Get pending syncs.
		$pending = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE sync_status = 'pending' ORDER BY created_at ASC LIMIT 50",
				$log_table
			),
			ARRAY_A
		);

		foreach ( $pending as $item ) {
			switch ( $item['entity_type'] ) {
				case 'customer':
					$this->services['customer']->sync_customer( $item['entity_id'] );
					break;
				case 'invoice':
					$this->services['invoice']->sync_booking( $item['entity_id'] );
					break;
				case 'payment':
					$this->services['payment']->sync_payment( $item['entity_id'] );
					break;
			}
		}
	}
}
