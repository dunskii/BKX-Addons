<?php
/**
 * Main Bulk & Recurring Payments Addon Class.
 *
 * @package BookingX\BulkRecurringPayments
 * @since   1.0.0
 */

namespace BookingX\BulkRecurringPayments;

use BookingX\BulkRecurringPayments\Services\PackageManager;
use BookingX\BulkRecurringPayments\Services\SubscriptionManager;
use BookingX\BulkRecurringPayments\Services\BulkPurchaseManager;
use BookingX\BulkRecurringPayments\Services\InvoiceGenerator;
use BookingX\BulkRecurringPayments\Services\PaymentProcessor;
use BookingX\BulkRecurringPayments\Services\NotificationService;

/**
 * BulkRecurringPaymentsAddon class.
 *
 * @since 1.0.0
 */
class BulkRecurringPaymentsAddon {

	/**
	 * Singleton instance.
	 *
	 * @var BulkRecurringPaymentsAddon
	 */
	private static $instance = null;

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Services container.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return BulkRecurringPaymentsAddon
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->settings = get_option( 'bkx_bulk_recurring_payments_settings', array() );
	}

	/**
	 * Initialize the addon.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		$this->init_services();
		$this->register_hooks();
	}

	/**
	 * Initialize services.
	 *
	 * @since 1.0.0
	 */
	private function init_services() {
		$this->services['packages']      = new PackageManager( $this->settings );
		$this->services['subscriptions'] = new SubscriptionManager( $this->settings );
		$this->services['bulk']          = new BulkPurchaseManager( $this->settings );
		$this->services['invoices']      = new InvoiceGenerator( $this->settings );
		$this->services['payments']      = new PaymentProcessor( $this->settings );
		$this->services['notifications'] = new NotificationService( $this->settings );
	}

	/**
	 * Get a service.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks() {
		// Admin.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_save_package', array( $this, 'ajax_save_package' ) );
		add_action( 'wp_ajax_bkx_delete_package', array( $this, 'ajax_delete_package' ) );
		add_action( 'wp_ajax_bkx_get_packages', array( $this, 'ajax_get_packages' ) );
		add_action( 'wp_ajax_bkx_purchase_package', array( $this, 'ajax_purchase_package' ) );
		add_action( 'wp_ajax_nopriv_bkx_purchase_package', array( $this, 'ajax_purchase_package' ) );
		add_action( 'wp_ajax_bkx_cancel_subscription', array( $this, 'ajax_cancel_subscription' ) );
		add_action( 'wp_ajax_bkx_pause_subscription', array( $this, 'ajax_pause_subscription' ) );
		add_action( 'wp_ajax_bkx_resume_subscription', array( $this, 'ajax_resume_subscription' ) );
		add_action( 'wp_ajax_bkx_get_customer_packages', array( $this, 'ajax_get_customer_packages' ) );
		add_action( 'wp_ajax_bkx_apply_bulk_credit', array( $this, 'ajax_apply_bulk_credit' ) );
		add_action( 'wp_ajax_bkx_generate_invoice', array( $this, 'ajax_generate_invoice' ) );
		add_action( 'wp_ajax_bkx_save_settings', array( $this, 'ajax_save_settings' ) );

		// Cron events.
		add_action( 'bkx_process_recurring_payments', array( $this->services['payments'], 'process_due_payments' ) );
		add_action( 'bkx_check_subscription_renewals', array( $this->services['subscriptions'], 'check_renewals' ) );
		add_action( 'bkx_send_renewal_reminders', array( $this->services['notifications'], 'send_renewal_reminders' ) );
		add_action( 'bkx_check_bulk_expiry', array( $this->services['bulk'], 'check_expiry' ) );
		add_action( 'bkx_retry_failed_payments', array( $this->services['payments'], 'retry_failed' ) );

		// Schedule cron events.
		add_action( 'init', array( $this, 'schedule_cron_events' ) );

		// BookingX integration.
		add_filter( 'bkx_booking_payment_options', array( $this, 'add_payment_options' ), 10, 2 );
		add_filter( 'bkx_booking_total', array( $this, 'apply_package_discount' ), 10, 2 );
		add_action( 'bkx_booking_created', array( $this, 'handle_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_cancelled', array( $this, 'handle_booking_cancelled' ), 10, 2 );

		// Webhook handlers.
		add_action( 'bkx_stripe_webhook_invoice_payment_succeeded', array( $this, 'handle_stripe_payment' ) );
		add_action( 'bkx_stripe_webhook_customer_subscription_deleted', array( $this, 'handle_stripe_cancellation' ) );
		add_action( 'bkx_paypal_webhook_BILLING_SUBSCRIPTION_ACTIVATED', array( $this, 'handle_paypal_activation' ) );
		add_action( 'bkx_paypal_webhook_BILLING_SUBSCRIPTION_CANCELLED', array( $this, 'handle_paypal_cancellation' ) );

		// Shortcodes.
		add_shortcode( 'bkx_packages', array( $this, 'render_packages_shortcode' ) );
		add_shortcode( 'bkx_my_subscriptions', array( $this, 'render_subscriptions_shortcode' ) );
		add_shortcode( 'bkx_my_bulk_credits', array( $this, 'render_bulk_credits_shortcode' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Schedule cron events.
	 *
	 * @since 1.0.0
	 */
	public function schedule_cron_events() {
		if ( ! wp_next_scheduled( 'bkx_process_recurring_payments' ) ) {
			wp_schedule_event( time(), 'hourly', 'bkx_process_recurring_payments' );
		}

		if ( ! wp_next_scheduled( 'bkx_check_subscription_renewals' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_check_subscription_renewals' );
		}

		if ( ! wp_next_scheduled( 'bkx_send_renewal_reminders' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_send_renewal_reminders' );
		}

		if ( ! wp_next_scheduled( 'bkx_check_bulk_expiry' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_check_bulk_expiry' );
		}

		if ( ! wp_next_scheduled( 'bkx_retry_failed_payments' ) ) {
			wp_schedule_event( time(), 'hourly', 'bkx_retry_failed_payments' );
		}
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Bulk & Recurring Payments', 'bkx-bulk-recurring-payments' ),
			__( 'Payments', 'bkx-bulk-recurring-payments' ),
			'manage_options',
			'bkx-bulk-recurring-payments',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bkx_booking_page_bkx-bulk-recurring-payments' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-bulk-recurring-payments-admin',
			BKX_BULK_RECURRING_PAYMENTS_URL . 'assets/css/admin.css',
			array(),
			BKX_BULK_RECURRING_PAYMENTS_VERSION
		);

		wp_enqueue_script(
			'bkx-bulk-recurring-payments-admin',
			BKX_BULK_RECURRING_PAYMENTS_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			BKX_BULK_RECURRING_PAYMENTS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-bulk-recurring-payments-admin',
			'bkxBulkRecurring',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bkx_bulk_recurring_nonce' ),
				'settings' => $this->settings,
				'i18n'     => array(
					'confirmDelete'    => __( 'Are you sure you want to delete this package?', 'bkx-bulk-recurring-payments' ),
					'confirmCancel'    => __( 'Are you sure you want to cancel this subscription?', 'bkx-bulk-recurring-payments' ),
					'saving'           => __( 'Saving...', 'bkx-bulk-recurring-payments' ),
					'saved'            => __( 'Saved!', 'bkx-bulk-recurring-payments' ),
					'error'            => __( 'An error occurred.', 'bkx-bulk-recurring-payments' ),
					'selectServices'   => __( 'Select services...', 'bkx-bulk-recurring-payments' ),
					'noPackagesFound'  => __( 'No packages found.', 'bkx-bulk-recurring-payments' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_frontend_assets() {
		if ( ! is_singular() ) {
			return;
		}

		global $post;
		if ( ! has_shortcode( $post->post_content, 'bkx_packages' ) &&
			 ! has_shortcode( $post->post_content, 'bkx_my_subscriptions' ) &&
			 ! has_shortcode( $post->post_content, 'bkx_my_bulk_credits' ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-bulk-recurring-payments',
			BKX_BULK_RECURRING_PAYMENTS_URL . 'assets/css/frontend.css',
			array(),
			BKX_BULK_RECURRING_PAYMENTS_VERSION
		);

		wp_enqueue_script(
			'bkx-bulk-recurring-payments',
			BKX_BULK_RECURRING_PAYMENTS_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_BULK_RECURRING_PAYMENTS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-bulk-recurring-payments',
			'bkxPackages',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_bulk_recurring_nonce' ),
				'i18n'    => array(
					'processing'      => __( 'Processing...', 'bkx-bulk-recurring-payments' ),
					'purchaseSuccess' => __( 'Purchase successful!', 'bkx-bulk-recurring-payments' ),
					'cancelSuccess'   => __( 'Subscription cancelled.', 'bkx-bulk-recurring-payments' ),
					'error'           => __( 'An error occurred.', 'bkx-bulk-recurring-payments' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_page() {
		include BKX_BULK_RECURRING_PAYMENTS_PATH . 'templates/admin/bulk-recurring-payments.php';
	}

	/**
	 * AJAX: Save package.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_package() {
		check_ajax_referer( 'bkx_bulk_recurring_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-bulk-recurring-payments' ) ) );
		}

		$data = array(
			'id'              => isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0,
			'name'            => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'description'     => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'package_type'    => isset( $_POST['package_type'] ) ? sanitize_text_field( wp_unslash( $_POST['package_type'] ) ) : 'bulk',
			'service_ids'     => isset( $_POST['service_ids'] ) ? array_map( 'absint', (array) $_POST['service_ids'] ) : array(),
			'quantity'        => isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : null,
			'interval_type'   => isset( $_POST['interval_type'] ) ? sanitize_text_field( wp_unslash( $_POST['interval_type'] ) ) : null,
			'interval_count'  => isset( $_POST['interval_count'] ) ? absint( $_POST['interval_count'] ) : 1,
			'billing_cycles'  => isset( $_POST['billing_cycles'] ) ? absint( $_POST['billing_cycles'] ) : 0,
			'price'           => isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0,
			'discount_type'   => isset( $_POST['discount_type'] ) ? sanitize_text_field( wp_unslash( $_POST['discount_type'] ) ) : 'percentage',
			'discount_amount' => isset( $_POST['discount_amount'] ) ? floatval( $_POST['discount_amount'] ) : 0,
			'trial_days'      => isset( $_POST['trial_days'] ) ? absint( $_POST['trial_days'] ) : 0,
			'setup_fee'       => isset( $_POST['setup_fee'] ) ? floatval( $_POST['setup_fee'] ) : 0,
			'status'          => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active',
			'valid_from'      => isset( $_POST['valid_from'] ) ? sanitize_text_field( wp_unslash( $_POST['valid_from'] ) ) : null,
			'valid_until'     => isset( $_POST['valid_until'] ) ? sanitize_text_field( wp_unslash( $_POST['valid_until'] ) ) : null,
			'max_purchases'   => isset( $_POST['max_purchases'] ) ? absint( $_POST['max_purchases'] ) : 0,
		);

		$result = $this->services['packages']->save( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'package' => $result ) );
	}

	/**
	 * AJAX: Delete package.
	 *
	 * @since 1.0.0
	 */
	public function ajax_delete_package() {
		check_ajax_referer( 'bkx_bulk_recurring_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-bulk-recurring-payments' ) ) );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid package ID.', 'bkx-bulk-recurring-payments' ) ) );
		}

		$result = $this->services['packages']->delete( $id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Get packages.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_packages() {
		check_ajax_referer( 'bkx_bulk_recurring_nonce', 'nonce' );

		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		$packages = $this->services['packages']->get_all( array( 'type' => $type ) );

		wp_send_json_success( array( 'packages' => $packages ) );
	}

	/**
	 * AJAX: Purchase package.
	 *
	 * @since 1.0.0
	 */
	public function ajax_purchase_package() {
		check_ajax_referer( 'bkx_bulk_recurring_nonce', 'nonce' );

		$package_id     = isset( $_POST['package_id'] ) ? absint( $_POST['package_id'] ) : 0;
		$gateway        = isset( $_POST['gateway'] ) ? sanitize_text_field( wp_unslash( $_POST['gateway'] ) ) : '';
		$payment_method = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : '';

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to purchase.', 'bkx-bulk-recurring-payments' ) ) );
		}

		$package = $this->services['packages']->get( $package_id );

		if ( ! $package ) {
			wp_send_json_error( array( 'message' => __( 'Package not found.', 'bkx-bulk-recurring-payments' ) ) );
		}

		$customer_id = get_current_user_id();

		if ( 'recurring' === $package->package_type ) {
			$result = $this->services['subscriptions']->create( $customer_id, $package, $gateway, $payment_method );
		} else {
			$result = $this->services['bulk']->create( $customer_id, $package, $gateway, $payment_method );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Cancel subscription.
	 *
	 * @since 1.0.0
	 */
	public function ajax_cancel_subscription() {
		check_ajax_referer( 'bkx_bulk_recurring_nonce', 'nonce' );

		$subscription_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;
		$reason          = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';

		$subscription = $this->services['subscriptions']->get( $subscription_id );

		if ( ! $subscription ) {
			wp_send_json_error( array( 'message' => __( 'Subscription not found.', 'bkx-bulk-recurring-payments' ) ) );
		}

		// Check ownership or admin.
		if ( ! current_user_can( 'manage_options' ) && (int) $subscription->customer_id !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-bulk-recurring-payments' ) ) );
		}

		$result = $this->services['subscriptions']->cancel( $subscription_id, $reason );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Pause subscription.
	 *
	 * @since 1.0.0
	 */
	public function ajax_pause_subscription() {
		check_ajax_referer( 'bkx_bulk_recurring_nonce', 'nonce' );

		$subscription_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;

		$subscription = $this->services['subscriptions']->get( $subscription_id );

		if ( ! $subscription ) {
			wp_send_json_error( array( 'message' => __( 'Subscription not found.', 'bkx-bulk-recurring-payments' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) && (int) $subscription->customer_id !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-bulk-recurring-payments' ) ) );
		}

		$result = $this->services['subscriptions']->pause( $subscription_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Resume subscription.
	 *
	 * @since 1.0.0
	 */
	public function ajax_resume_subscription() {
		check_ajax_referer( 'bkx_bulk_recurring_nonce', 'nonce' );

		$subscription_id = isset( $_POST['subscription_id'] ) ? absint( $_POST['subscription_id'] ) : 0;

		$subscription = $this->services['subscriptions']->get( $subscription_id );

		if ( ! $subscription ) {
			wp_send_json_error( array( 'message' => __( 'Subscription not found.', 'bkx-bulk-recurring-payments' ) ) );
		}

		if ( ! current_user_can( 'manage_options' ) && (int) $subscription->customer_id !== get_current_user_id() ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-bulk-recurring-payments' ) ) );
		}

		$result = $this->services['subscriptions']->resume( $subscription_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Get customer packages.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_customer_packages() {
		check_ajax_referer( 'bkx_bulk_recurring_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'bkx-bulk-recurring-payments' ) ) );
		}

		$customer_id = get_current_user_id();

		$subscriptions = $this->services['subscriptions']->get_by_customer( $customer_id );
		$bulk_credits  = $this->services['bulk']->get_by_customer( $customer_id );

		wp_send_json_success(
			array(
				'subscriptions' => $subscriptions,
				'bulk_credits'  => $bulk_credits,
			)
		);
	}

	/**
	 * AJAX: Apply bulk credit.
	 *
	 * @since 1.0.0
	 */
	public function ajax_apply_bulk_credit() {
		check_ajax_referer( 'bkx_bulk_recurring_nonce', 'nonce' );

		$bulk_purchase_id = isset( $_POST['bulk_purchase_id'] ) ? absint( $_POST['bulk_purchase_id'] ) : 0;
		$booking_id       = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in.', 'bkx-bulk-recurring-payments' ) ) );
		}

		$result = $this->services['bulk']->apply_credit( $bulk_purchase_id, $booking_id, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Generate invoice.
	 *
	 * @since 1.0.0
	 */
	public function ajax_generate_invoice() {
		check_ajax_referer( 'bkx_bulk_recurring_nonce', 'nonce' );

		$type = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
		$id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

		$invoice_url = $this->services['invoices']->generate( $type, $id );

		if ( is_wp_error( $invoice_url ) ) {
			wp_send_json_error( array( 'message' => $invoice_url->get_error_message() ) );
		}

		wp_send_json_success( array( 'invoice_url' => $invoice_url ) );
	}

	/**
	 * AJAX: Save settings.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_bulk_recurring_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-bulk-recurring-payments' ) ) );
		}

		$settings = array(
			'enable_bulk_packages'          => isset( $_POST['enable_bulk_packages'] ),
			'enable_subscriptions'          => isset( $_POST['enable_subscriptions'] ),
			'bulk_expiry_days'              => isset( $_POST['bulk_expiry_days'] ) ? absint( $_POST['bulk_expiry_days'] ) : 365,
			'allow_partial_refunds'         => isset( $_POST['allow_partial_refunds'] ),
			'auto_cancel_failed_payments'   => isset( $_POST['auto_cancel_failed_payments'] ) ? absint( $_POST['auto_cancel_failed_payments'] ) : 3,
			'send_renewal_reminders'        => isset( $_POST['send_renewal_reminders'] ),
			'renewal_reminder_days'         => isset( $_POST['renewal_reminder_days'] ) ? array_map( 'absint', (array) $_POST['renewal_reminder_days'] ) : array( 7, 3, 1 ),
			'send_payment_receipts'         => isset( $_POST['send_payment_receipts'] ),
			'send_expiry_warnings'          => isset( $_POST['send_expiry_warnings'] ),
			'expiry_warning_days'           => isset( $_POST['expiry_warning_days'] ) ? array_map( 'absint', (array) $_POST['expiry_warning_days'] ) : array( 30, 7, 1 ),
			'invoice_prefix'                => isset( $_POST['invoice_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice_prefix'] ) ) : 'INV-',
			'invoice_starting_number'       => isset( $_POST['invoice_starting_number'] ) ? absint( $_POST['invoice_starting_number'] ) : 1000,
			'invoice_include_tax'           => isset( $_POST['invoice_include_tax'] ),
			'auto_activate_bulk'            => isset( $_POST['auto_activate_bulk'] ),
			'allow_package_switching'       => isset( $_POST['allow_package_switching'] ),
			'prorate_upgrades'              => isset( $_POST['prorate_upgrades'] ),
			'retry_failed_payments'         => isset( $_POST['retry_failed_payments'] ),
			'retry_interval_hours'          => isset( $_POST['retry_interval_hours'] ) ? absint( $_POST['retry_interval_hours'] ) : 24,
			'max_retry_attempts'            => isset( $_POST['max_retry_attempts'] ) ? absint( $_POST['max_retry_attempts'] ) : 3,
			'pause_subscription_limit_days' => isset( $_POST['pause_subscription_limit_days'] ) ? absint( $_POST['pause_subscription_limit_days'] ) : 30,
		);

		update_option( 'bkx_bulk_recurring_payments_settings', $settings );
		$this->settings = $settings;

		wp_send_json_success();
	}

	/**
	 * Add payment options to booking form.
	 *
	 * @since 1.0.0
	 *
	 * @param array $options Payment options.
	 * @param int   $booking_id Booking ID.
	 * @return array
	 */
	public function add_payment_options( $options, $booking_id ) {
		if ( ! is_user_logged_in() ) {
			return $options;
		}

		$customer_id = get_current_user_id();
		$bulk_credits = $this->services['bulk']->get_available( $customer_id );

		if ( ! empty( $bulk_credits ) ) {
			$options['bulk_credit'] = array(
				'label'   => __( 'Use Bulk Credit', 'bkx-bulk-recurring-payments' ),
				'credits' => $bulk_credits,
			);
		}

		return $options;
	}

	/**
	 * Apply package discount to booking total.
	 *
	 * @since 1.0.0
	 *
	 * @param float $total Booking total.
	 * @param int   $booking_id Booking ID.
	 * @return float
	 */
	public function apply_package_discount( $total, $booking_id ) {
		$payment_method = get_post_meta( $booking_id, '_bkx_payment_method', true );

		if ( 'bulk_credit' !== $payment_method ) {
			return $total;
		}

		// If using bulk credit, the total is 0 (already paid).
		return 0;
	}

	/**
	 * Handle booking created.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $data Booking data.
	 */
	public function handle_booking_created( $booking_id, $data ) {
		$payment_method = isset( $data['payment_method'] ) ? $data['payment_method'] : '';

		if ( 'bulk_credit' !== $payment_method ) {
			return;
		}

		$bulk_purchase_id = isset( $data['bulk_purchase_id'] ) ? absint( $data['bulk_purchase_id'] ) : 0;

		if ( $bulk_purchase_id ) {
			$this->services['bulk']->use_credit( $bulk_purchase_id, $booking_id );
		}
	}

	/**
	 * Handle booking cancelled.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $data Cancellation data.
	 */
	public function handle_booking_cancelled( $booking_id, $data ) {
		$payment_method = get_post_meta( $booking_id, '_bkx_payment_method', true );

		if ( 'bulk_credit' !== $payment_method ) {
			return;
		}

		// Refund the credit.
		$this->services['bulk']->refund_credit( $booking_id );
	}

	/**
	 * Handle Stripe payment webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param object $event Stripe event.
	 */
	public function handle_stripe_payment( $event ) {
		$this->services['payments']->handle_stripe_payment( $event );
	}

	/**
	 * Handle Stripe cancellation webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param object $event Stripe event.
	 */
	public function handle_stripe_cancellation( $event ) {
		$this->services['subscriptions']->handle_gateway_cancellation( 'stripe', $event );
	}

	/**
	 * Handle PayPal activation webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param object $event PayPal event.
	 */
	public function handle_paypal_activation( $event ) {
		$this->services['subscriptions']->handle_gateway_activation( 'paypal', $event );
	}

	/**
	 * Handle PayPal cancellation webhook.
	 *
	 * @since 1.0.0
	 *
	 * @param object $event PayPal event.
	 */
	public function handle_paypal_cancellation( $event ) {
		$this->services['subscriptions']->handle_gateway_cancellation( 'paypal', $event );
	}

	/**
	 * Render packages shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_packages_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'type'    => '',
				'columns' => 3,
			),
			$atts
		);

		$packages = $this->services['packages']->get_all(
			array(
				'type'   => $atts['type'],
				'status' => 'active',
			)
		);

		ob_start();
		include BKX_BULK_RECURRING_PAYMENTS_PATH . 'templates/frontend/packages.php';
		return ob_get_clean();
	}

	/**
	 * Render subscriptions shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function render_subscriptions_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your subscriptions.', 'bkx-bulk-recurring-payments' ) . '</p>';
		}

		$subscriptions = $this->services['subscriptions']->get_by_customer( get_current_user_id() );

		ob_start();
		include BKX_BULK_RECURRING_PAYMENTS_PATH . 'templates/frontend/subscriptions.php';
		return ob_get_clean();
	}

	/**
	 * Render bulk credits shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function render_bulk_credits_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your credits.', 'bkx-bulk-recurring-payments' ) . '</p>';
		}

		$bulk_credits = $this->services['bulk']->get_by_customer( get_current_user_id() );

		ob_start();
		include BKX_BULK_RECURRING_PAYMENTS_PATH . 'templates/frontend/bulk-credits.php';
		return ob_get_clean();
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx/v1',
			'/packages',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_packages' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'bkx/v1',
			'/subscriptions',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_subscriptions' ),
				'permission_callback' => array( $this, 'rest_check_auth' ),
			)
		);
	}

	/**
	 * REST: Get packages.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_packages( $request ) {
		$packages = $this->services['packages']->get_all(
			array(
				'type'   => $request->get_param( 'type' ),
				'status' => 'active',
			)
		);

		return rest_ensure_response( $packages );
	}

	/**
	 * REST: Get subscriptions.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_subscriptions( $request ) {
		$subscriptions = $this->services['subscriptions']->get_by_customer( get_current_user_id() );

		return rest_ensure_response( $subscriptions );
	}

	/**
	 * REST: Check authentication.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function rest_check_auth() {
		return is_user_logged_in();
	}
}
