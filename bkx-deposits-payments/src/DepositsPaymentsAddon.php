<?php
/**
 * Main Deposits & Payments Addon Class
 *
 * @package BookingX\DepositsPayments
 * @since   1.0.0
 */

namespace BookingX\DepositsPayments;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasAjax;
use BookingX\DepositsPayments\Services\DepositService;
use BookingX\DepositsPayments\Services\BalanceService;
use BookingX\DepositsPayments\Admin\SettingsPage;
use BookingX\DepositsPayments\Migrations\CreateDepositTables;

/**
 * Main addon class for Deposits & Payments.
 *
 * @since 1.0.0
 */
class DepositsPaymentsAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasAjax;

	/**
	 * Deposit service instance.
	 *
	 * @var DepositService
	 */
	protected ?DepositService $deposit_service = null;

	/**
	 * Balance service instance.
	 *
	 * @var BalanceService
	 */
	protected ?BalanceService $balance_service = null;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Path to the main plugin file.
	 */
	public function __construct( string $plugin_file ) {
		// Set addon properties
		$this->addon_id        = 'bkx_deposits_payments';
		$this->addon_name      = __( 'BookingX - Deposits & Payments', 'bkx-deposits-payments' );
		$this->version         = BKX_DEPOSITS_VERSION;
		$this->text_domain     = 'bkx-deposits-payments';
		$this->min_bkx_version = '2.0.0';
		$this->min_php_version = '7.4';
		$this->min_wp_version  = '5.8';

		parent::__construct( $plugin_file );
	}

	/**
	 * Register with BookingX Framework.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function register_with_framework(): void {
		// Register settings tab
		add_filter( 'bkx_settings_tabs', array( $this, 'register_settings_tab' ) );

		// Register this addon as active
		add_filter( "bookingx_addon_{$this->addon_id}_active", '__return_true' );
	}

	/**
	 * Register settings tab.
	 *
	 * @since 1.0.0
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function register_settings_tab( array $tabs ): array {
		$tabs['deposits_payments'] = __( 'Deposits & Payments', 'bkx-deposits-payments' );
		return $tabs;
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_hooks(): void {
		// Modify booking price to show deposit amount
		add_filter( 'bkx_booking_price', array( $this, 'calculate_deposit_amount' ), 10, 2 );

		// Save deposit information
		add_action( 'bkx_booking_created', array( $this, 'save_deposit_info' ), 10, 2 );

		// Handle payment completion
		add_action( 'bkx_payment_completed', array( $this, 'handle_payment_completed' ), 10, 2 );

		// Schedule balance reminders
		add_action( 'bkx_deposits_balance_reminders', array( $this, 'send_balance_reminders' ) );

		// Add deposit summary to booking form
		add_action( 'bkx_after_booking_form', array( $this, 'render_deposit_summary' ), 10, 1 );

		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
	}

	/**
	 * Initialize admin functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_admin(): void {
		// Initialize settings page
		$settings_page = new SettingsPage( $this );

		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add meta box
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );

		// Add action links
		add_filter( 'plugin_action_links_' . BKX_DEPOSITS_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Initialize frontend functionality.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_frontend(): void {
		// Register AJAX actions
		$this->register_ajax_action( 'calculate_deposit', array( $this, 'ajax_calculate_deposit' ), true );
		$this->register_ajax_action( 'process_balance_payment', array( $this, 'ajax_process_balance_payment' ), true );
	}

	/**
	 * Get database migrations.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_migrations(): array {
		return array(
			'1.0.0' => array(
				CreateDepositTables::class,
			),
		);
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array(
			'enable_deposits'           => true,
			'deposit_type'              => 'percentage',
			'deposit_amount'            => 50,
			'minimum_deposit'           => 0,
			'balance_due_timing'        => 'before_appointment',
			'balance_due_days'          => 7,
			'enable_balance_reminders'  => true,
			'reminder_days_before'      => array( 7, 3, 1 ),
			'allow_full_payment'        => true,
			'refund_policy'             => 'percentage',
			'refund_percentage'         => 50,
			'deposit_status_required'   => 'bkx-ack',
		);
	}

	/**
	 * Get settings fields.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_settings_fields(): array {
		return array(
			'enable_deposits'           => array( 'type' => 'checkbox' ),
			'deposit_type'              => array( 'type' => 'select', 'options' => array( 'percentage' => 'Percentage', 'fixed' => 'Fixed Amount' ) ),
			'deposit_amount'            => array( 'type' => 'number' ),
			'minimum_deposit'           => array( 'type' => 'number' ),
			'balance_due_timing'        => array( 'type' => 'select', 'options' => array( 'before_appointment' => 'Before Appointment', 'at_appointment' => 'At Appointment' ) ),
			'balance_due_days'          => array( 'type' => 'integer' ),
			'enable_balance_reminders'  => array( 'type' => 'checkbox' ),
			'reminder_days_before'      => array( 'type' => 'json' ),
			'allow_full_payment'        => array( 'type' => 'checkbox' ),
			'refund_policy'             => array( 'type' => 'select', 'options' => array( 'full' => 'Full', 'percentage' => 'Percentage', 'none' => 'None' ) ),
			'refund_percentage'         => array( 'type' => 'number' ),
			'deposit_status_required'   => array( 'type' => 'text' ),
		);
	}

	/**
	 * Calculate deposit amount for booking.
	 *
	 * @since 1.0.0
	 * @param float $price        Original price.
	 * @param array $booking_data Booking data.
	 * @return float Deposit amount.
	 */
	public function calculate_deposit_amount( $price, $booking_data ): float {
		if ( ! $this->get_setting( 'enable_deposits', true ) ) {
			return $price;
		}

		// Check if customer chose to pay in full
		if ( ! empty( $booking_data['pay_in_full'] ) && $this->get_setting( 'allow_full_payment', true ) ) {
			return $price;
		}

		return $this->get_deposit_service()->calculate_deposit(
			$price,
			$this->get_setting( 'deposit_type', 'percentage' ),
			$this->get_setting( 'deposit_amount', 50 ),
			$this->get_setting( 'minimum_deposit', 0 )
		);
	}

	/**
	 * Save deposit information to booking.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function save_deposit_info( $booking_id, $booking_data ): void {
		$total_price = $booking_data['total_price'] ?? 0;
		$deposit_amount = $this->calculate_deposit_amount( $total_price, $booking_data );
		$balance = $total_price - $deposit_amount;

		// Save to custom table
		$this->get_deposit_service()->create_deposit_record(
			$booking_id,
			$total_price,
			$deposit_amount,
			$balance,
			$booking_data['pay_in_full'] ?? false
		);

		// Save meta
		update_post_meta( $booking_id, '_bkx_deposit_amount', $deposit_amount );
		update_post_meta( $booking_id, '_bkx_balance_amount', $balance );
		update_post_meta( $booking_id, '_bkx_is_deposit_booking', true );
	}

	/**
	 * Handle payment completion.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $payment_type Payment type (deposit or balance).
	 * @return void
	 */
	public function handle_payment_completed( $booking_id, $payment_type ): void {
		$this->get_deposit_service()->mark_payment_completed( $booking_id, $payment_type );
	}

	/**
	 * Send balance due reminders.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function send_balance_reminders(): void {
		if ( ! $this->get_setting( 'enable_balance_reminders', true ) ) {
			return;
		}

		$this->get_balance_service()->send_pending_reminders(
			$this->get_setting( 'reminder_days_before', array( 7, 3, 1 ) )
		);
	}

	/**
	 * Render deposit summary on booking form.
	 *
	 * @since 1.0.0
	 * @param int $base_id Service ID.
	 * @return void
	 */
	public function render_deposit_summary( $base_id ): void {
		$template_path = BKX_DEPOSITS_PATH . 'templates/frontend/deposit-summary.php';
		if ( file_exists( $template_path ) ) {
			include $template_path;
		}
	}

	/**
	 * AJAX: Calculate deposit amount.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_calculate_deposit(): void {
		$total_price = $this->get_post_param( 'total_price', 0, 'float' );
		$pay_in_full = $this->get_post_param( 'pay_in_full', false, 'bool' );

		$deposit = $this->get_deposit_service()->calculate_deposit(
			$total_price,
			$this->get_setting( 'deposit_type', 'percentage' ),
			$this->get_setting( 'deposit_amount', 50 ),
			$this->get_setting( 'minimum_deposit', 0 )
		);

		$amount = $pay_in_full ? $total_price : $deposit;
		$balance = $total_price - $deposit;

		$this->ajax_success( array(
			'deposit'      => $deposit,
			'balance'      => $balance,
			'amount_to_pay' => $amount,
		) );
	}

	/**
	 * AJAX: Process balance payment.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_process_balance_payment(): void {
		$booking_id = $this->get_post_param( 'booking_id', 0, 'int' );

		if ( ! $booking_id ) {
			$this->ajax_error( 'invalid_booking', __( 'Invalid booking ID.', 'bkx-deposits-payments' ) );
		}

		$result = $this->get_balance_service()->process_balance_payment( $booking_id );

		if ( $result['success'] ) {
			$this->ajax_success( $result );
		} else {
			$this->ajax_error( 'payment_failed', $result['message'] );
		}
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ): void {
		if ( 'bkx_booking_page_bkx_settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-deposits-admin',
			BKX_DEPOSITS_URL . 'assets/css/admin.css',
			array(),
			BKX_DEPOSITS_VERSION
		);

		wp_enqueue_script(
			'bkx-deposits-admin',
			BKX_DEPOSITS_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_DEPOSITS_VERSION,
			true
		);
	}

	/**
	 * Enqueue frontend scripts.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_scripts(): void {
		if ( ! is_page() && ! is_singular( 'bkx_base' ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-deposits-frontend',
			BKX_DEPOSITS_URL . 'assets/css/frontend.css',
			array(),
			BKX_DEPOSITS_VERSION
		);

		wp_enqueue_script(
			'bkx-deposits-frontend',
			BKX_DEPOSITS_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_DEPOSITS_VERSION,
			true
		);

		$this->localize_ajax_data(
			'bkx-deposits-frontend',
			'bkxDeposits',
			array( 'calculate_deposit', 'process_balance_payment' ),
			array(
				'settings' => array(
					'depositType'   => $this->get_setting( 'deposit_type', 'percentage' ),
					'depositAmount' => $this->get_setting( 'deposit_amount', 50 ),
					'allowFullPayment' => $this->get_setting( 'allow_full_payment', true ),
				),
				'i18n' => array(
					'calculating'    => __( 'Calculating...', 'bkx-deposits-payments' ),
					'depositDue'     => __( 'Deposit Due', 'bkx-deposits-payments' ),
					'balanceDue'     => __( 'Balance Due', 'bkx-deposits-payments' ),
				),
			)
		);
	}

	/**
	 * Register meta boxes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_meta_boxes(): void {
		add_meta_box(
			'bkx_deposit_details',
			__( 'Deposit & Payment Details', 'bkx-deposits-payments' ),
			array( $this, 'render_deposit_meta_box' ),
			'bkx_booking',
			'side',
			'high'
		);
	}

	/**
	 * Render deposit details meta box.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_deposit_meta_box( \WP_Post $post ): void {
		$deposit_info = $this->get_deposit_service()->get_deposit_info( $post->ID );

		if ( ! $deposit_info ) {
			echo '<p>' . esc_html__( 'No deposit information found.', 'bkx-deposits-payments' ) . '</p>';
			return;
		}

		echo '<table class="widefat">';
		echo '<tr><th>' . esc_html__( 'Total Price:', 'bkx-deposits-payments' ) . '</th><td>$' . esc_html( number_format( $deposit_info->total_price, 2 ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Deposit:', 'bkx-deposits-payments' ) . '</th><td>$' . esc_html( number_format( $deposit_info->deposit_amount, 2 ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Balance:', 'bkx-deposits-payments' ) . '</th><td>$' . esc_html( number_format( $deposit_info->balance_amount, 2 ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Deposit Status:', 'bkx-deposits-payments' ) . '</th><td><strong>' . esc_html( ucfirst( $deposit_info->deposit_status ) ) . '</strong></td></tr>';
		echo '<tr><th>' . esc_html__( 'Balance Status:', 'bkx-deposits-payments' ) . '</th><td><strong>' . esc_html( ucfirst( $deposit_info->balance_status ) ) . '</strong></td></tr>';
		echo '</table>';
	}

	/**
	 * Add action links to plugin list.
	 *
	 * @since 1.0.0
	 * @param array $links Existing links.
	 * @return array
	 */
	public function add_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx_settings&tab=deposits_payments' ) ),
			esc_html__( 'Settings', 'bkx-deposits-payments' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Get deposit service instance.
	 *
	 * @since 1.0.0
	 * @return DepositService
	 */
	public function get_deposit_service(): DepositService {
		if ( ! $this->deposit_service ) {
			$this->deposit_service = new DepositService( $this );
		}

		return $this->deposit_service;
	}

	/**
	 * Get balance service instance.
	 *
	 * @since 1.0.0
	 * @return BalanceService
	 */
	public function get_balance_service(): BalanceService {
		if ( ! $this->balance_service ) {
			$this->balance_service = new BalanceService( $this );
		}

		return $this->balance_service;
	}
}
