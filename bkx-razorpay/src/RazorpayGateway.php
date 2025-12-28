<?php
/**
 * Razorpay Payment Gateway Class.
 *
 * @package BookingX\Razorpay
 * @since   1.0.0
 */

namespace BookingX\Razorpay;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RazorpayGateway Class.
 */
class RazorpayGateway {

	/**
	 * Gateway ID.
	 *
	 * @var string
	 */
	public $id = 'razorpay';

	/**
	 * Instance.
	 *
	 * @var RazorpayGateway
	 */
	private static $instance = null;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * API Service.
	 *
	 * @var ApiService
	 */
	private $api;

	/**
	 * Get instance.
	 *
	 * @return RazorpayGateway
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
		$this->settings = get_option( 'bkx_razorpay_settings', array() );
		$this->api      = new ApiService();

		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin settings.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		}

		// Frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

		// Payment form.
		add_filter( 'bkx_payment_form_' . $this->id, array( $this, 'render_payment_form' ), 10, 2 );

		// Process payment.
		add_action( 'wp_ajax_bkx_razorpay_create_order', array( $this, 'ajax_create_order' ) );
		add_action( 'wp_ajax_nopriv_bkx_razorpay_create_order', array( $this, 'ajax_create_order' ) );
		add_action( 'wp_ajax_bkx_razorpay_verify_payment', array( $this, 'ajax_verify_payment' ) );
		add_action( 'wp_ajax_nopriv_bkx_razorpay_verify_payment', array( $this, 'ajax_verify_payment' ) );

		// Webhook handler.
		add_action( 'rest_api_init', array( $this, 'register_webhook_endpoint' ) );

		// Refund.
		add_action( 'bkx_booking_status_changed', array( $this, 'maybe_process_refund' ), 10, 3 );
	}

	/**
	 * Check if gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->settings['enabled'] );
	}

	/**
	 * Check if in test mode.
	 *
	 * @return bool
	 */
	public function is_test_mode() {
		return ! empty( $this->settings['test_mode'] );
	}

	/**
	 * Get setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Get API key ID.
	 *
	 * @return string
	 */
	public function get_key_id() {
		if ( $this->is_test_mode() ) {
			return $this->get_setting( 'test_key_id', '' );
		}
		return $this->get_setting( 'key_id', '' );
	}

	/**
	 * Get API key secret.
	 *
	 * @return string
	 */
	public function get_key_secret() {
		if ( $this->is_test_mode() ) {
			return $this->decrypt_key( $this->get_setting( 'test_key_secret', '' ) );
		}
		return $this->decrypt_key( $this->get_setting( 'key_secret', '' ) );
	}

	/**
	 * Decrypt API key.
	 *
	 * @param string $encrypted_key Encrypted key.
	 * @return string
	 */
	private function decrypt_key( $encrypted_key ) {
		if ( empty( $encrypted_key ) ) {
			return '';
		}

		// Use BookingX encryption service if available.
		if ( class_exists( 'BKX_Data_Encryption' ) ) {
			$encryption = new \BKX_Data_Encryption();
			return $encryption->decrypt( $encrypted_key );
		}

		return $encrypted_key;
	}

	/**
	 * Encrypt API key.
	 *
	 * @param string $key API key.
	 * @return string
	 */
	private function encrypt_key( $key ) {
		if ( empty( $key ) ) {
			return '';
		}

		// Use BookingX encryption service if available.
		if ( class_exists( 'BKX_Data_Encryption' ) ) {
			$encryption = new \BKX_Data_Encryption();
			return $encryption->encrypt( $key );
		}

		return $key;
	}

	/**
	 * Add settings page.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Razorpay Settings', 'bkx-razorpay' ),
			__( 'Razorpay', 'bkx-razorpay' ),
			'manage_options',
			'bkx-razorpay',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting(
			'bkx_razorpay_settings',
			'bkx_razorpay_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input values.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();
		$old       = get_option( 'bkx_razorpay_settings', array() );

		$sanitized['enabled']     = ! empty( $input['enabled'] );
		$sanitized['title']       = sanitize_text_field( $input['title'] ?? '' );
		$sanitized['description'] = sanitize_textarea_field( $input['description'] ?? '' );
		$sanitized['test_mode']   = ! empty( $input['test_mode'] );

		// Handle live keys.
		$sanitized['key_id'] = sanitize_text_field( $input['key_id'] ?? '' );

		if ( ! empty( $input['key_secret'] ) && $input['key_secret'] !== '********' ) {
			$sanitized['key_secret'] = $this->encrypt_key( $input['key_secret'] );
		} else {
			$sanitized['key_secret'] = $old['key_secret'] ?? '';
		}

		// Handle test keys.
		$sanitized['test_key_id'] = sanitize_text_field( $input['test_key_id'] ?? '' );

		if ( ! empty( $input['test_key_secret'] ) && $input['test_key_secret'] !== '********' ) {
			$sanitized['test_key_secret'] = $this->encrypt_key( $input['test_key_secret'] );
		} else {
			$sanitized['test_key_secret'] = $old['test_key_secret'] ?? '';
		}

		// Webhook secret.
		if ( ! empty( $input['webhook_secret'] ) && $input['webhook_secret'] !== '********' ) {
			$sanitized['webhook_secret'] = $this->encrypt_key( $input['webhook_secret'] );
		} else {
			$sanitized['webhook_secret'] = $old['webhook_secret'] ?? '';
		}

		$sanitized['payment_action'] = in_array( $input['payment_action'] ?? '', array( 'capture', 'authorize' ), true )
			? $input['payment_action']
			: 'capture';

		return $sanitized;
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		$webhook_url = rest_url( 'bkx-razorpay/v1/webhook' );
		?>
		<div class="wrap bkx-razorpay-settings">
			<h1>
				<span class="dashicons dashicons-money-alt"></span>
				<?php esc_html_e( 'Razorpay Payment Gateway', 'bkx-razorpay' ); ?>
			</h1>

			<?php if ( $this->is_test_mode() ) : ?>
				<div class="notice notice-warning">
					<p>
						<strong><?php esc_html_e( 'Test Mode Enabled', 'bkx-razorpay' ); ?></strong> -
						<?php esc_html_e( 'Payments will be simulated. Switch to live mode for real transactions.', 'bkx-razorpay' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'bkx_razorpay_settings' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable/Disable', 'bkx-razorpay' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="bkx_razorpay_settings[enabled]" value="1"
									<?php checked( $this->get_setting( 'enabled' ) ); ?>>
								<?php esc_html_e( 'Enable Razorpay Payment Gateway', 'bkx-razorpay' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Title', 'bkx-razorpay' ); ?></th>
						<td>
							<input type="text" name="bkx_razorpay_settings[title]" class="regular-text"
								value="<?php echo esc_attr( $this->get_setting( 'title', 'Razorpay' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Title shown to customers during checkout.', 'bkx-razorpay' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Description', 'bkx-razorpay' ); ?></th>
						<td>
							<textarea name="bkx_razorpay_settings[description]" class="large-text" rows="3"><?php
								echo esc_textarea( $this->get_setting( 'description', '' ) );
							?></textarea>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Test Mode', 'bkx-razorpay' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="bkx_razorpay_settings[test_mode]" value="1"
									<?php checked( $this->get_setting( 'test_mode', true ) ); ?>>
								<?php esc_html_e( 'Enable test mode', 'bkx-razorpay' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Use test API keys for sandbox transactions.', 'bkx-razorpay' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row" colspan="2">
							<h2 class="title"><?php esc_html_e( 'Live API Credentials', 'bkx-razorpay' ); ?></h2>
						</th>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Key ID', 'bkx-razorpay' ); ?></th>
						<td>
							<input type="text" name="bkx_razorpay_settings[key_id]" class="regular-text"
								value="<?php echo esc_attr( $this->get_setting( 'key_id', '' ) ); ?>"
								placeholder="rzp_live_xxxxx">
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Key Secret', 'bkx-razorpay' ); ?></th>
						<td>
							<input type="password" name="bkx_razorpay_settings[key_secret]" class="regular-text"
								value="<?php echo $this->get_setting( 'key_secret' ) ? '********' : ''; ?>"
								placeholder="<?php esc_attr_e( 'Enter Key Secret', 'bkx-razorpay' ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row" colspan="2">
							<h2 class="title"><?php esc_html_e( 'Test API Credentials', 'bkx-razorpay' ); ?></h2>
						</th>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Test Key ID', 'bkx-razorpay' ); ?></th>
						<td>
							<input type="text" name="bkx_razorpay_settings[test_key_id]" class="regular-text"
								value="<?php echo esc_attr( $this->get_setting( 'test_key_id', '' ) ); ?>"
								placeholder="rzp_test_xxxxx">
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Test Key Secret', 'bkx-razorpay' ); ?></th>
						<td>
							<input type="password" name="bkx_razorpay_settings[test_key_secret]" class="regular-text"
								value="<?php echo $this->get_setting( 'test_key_secret' ) ? '********' : ''; ?>"
								placeholder="<?php esc_attr_e( 'Enter Test Key Secret', 'bkx-razorpay' ); ?>">
						</td>
					</tr>

					<tr>
						<th scope="row" colspan="2">
							<h2 class="title"><?php esc_html_e( 'Webhook Settings', 'bkx-razorpay' ); ?></h2>
						</th>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Webhook URL', 'bkx-razorpay' ); ?></th>
						<td>
							<code><?php echo esc_html( $webhook_url ); ?></code>
							<button type="button" class="button button-secondary copy-webhook-url"
								data-url="<?php echo esc_attr( $webhook_url ); ?>">
								<?php esc_html_e( 'Copy', 'bkx-razorpay' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Add this URL in your Razorpay Dashboard under Webhooks.', 'bkx-razorpay' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Webhook Secret', 'bkx-razorpay' ); ?></th>
						<td>
							<input type="password" name="bkx_razorpay_settings[webhook_secret]" class="regular-text"
								value="<?php echo $this->get_setting( 'webhook_secret' ) ? '********' : ''; ?>"
								placeholder="<?php esc_attr_e( 'Enter Webhook Secret', 'bkx-razorpay' ); ?>">
							<p class="description"><?php esc_html_e( 'Found in Razorpay Dashboard → Webhooks → Secret.', 'bkx-razorpay' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Payment Action', 'bkx-razorpay' ); ?></th>
						<td>
							<select name="bkx_razorpay_settings[payment_action]">
								<option value="capture" <?php selected( $this->get_setting( 'payment_action' ), 'capture' ); ?>>
									<?php esc_html_e( 'Capture (Immediate)', 'bkx-razorpay' ); ?>
								</option>
								<option value="authorize" <?php selected( $this->get_setting( 'payment_action' ), 'authorize' ); ?>>
									<?php esc_html_e( 'Authorize Only', 'bkx-razorpay' ); ?>
								</option>
							</select>
							<p class="description"><?php esc_html_e( 'Choose whether to capture payments immediately or authorize for later capture.', 'bkx-razorpay' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'bkx_booking_page_bkx-razorpay' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-razorpay-admin',
			BKX_RAZORPAY_URL . 'assets/css/admin.css',
			array(),
			BKX_RAZORPAY_VERSION
		);

		wp_enqueue_script(
			'bkx-razorpay-admin',
			BKX_RAZORPAY_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_RAZORPAY_VERSION,
			true
		);
	}

	/**
	 * Enqueue frontend scripts.
	 */
	public function enqueue_frontend_scripts() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Razorpay checkout script.
		wp_enqueue_script(
			'razorpay-checkout',
			'https://checkout.razorpay.com/v1/checkout.js',
			array(),
			null,
			true
		);

		wp_enqueue_style(
			'bkx-razorpay-frontend',
			BKX_RAZORPAY_URL . 'assets/css/frontend.css',
			array(),
			BKX_RAZORPAY_VERSION
		);

		wp_enqueue_script(
			'bkx-razorpay-frontend',
			BKX_RAZORPAY_URL . 'assets/js/frontend.js',
			array( 'jquery', 'razorpay-checkout' ),
			BKX_RAZORPAY_VERSION,
			true
		);

		wp_localize_script(
			'bkx-razorpay-frontend',
			'bkxRazorpay',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'bkx_razorpay_nonce' ),
				'keyId'       => $this->get_key_id(),
				'siteName'    => get_bloginfo( 'name' ),
				'siteUrl'     => home_url(),
				'description' => $this->get_setting( 'description', '' ),
				'testMode'    => $this->is_test_mode(),
				'i18n'        => array(
					'payNow'     => __( 'Pay Now', 'bkx-razorpay' ),
					'processing' => __( 'Processing...', 'bkx-razorpay' ),
					'error'      => __( 'Payment failed. Please try again.', 'bkx-razorpay' ),
					'success'    => __( 'Payment successful!', 'bkx-razorpay' ),
				),
			)
		);
	}

	/**
	 * Render payment form.
	 *
	 * @param string $html       Existing HTML.
	 * @param int    $booking_id Booking ID.
	 * @return string
	 */
	public function render_payment_form( $html, $booking_id ) {
		if ( ! $this->is_enabled() ) {
			return $html;
		}

		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return $html;
		}

		$total        = get_post_meta( $booking_id, 'booking_total', true );
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		$customer_name  = get_post_meta( $booking_id, 'customer_name', true );
		$customer_phone = get_post_meta( $booking_id, 'customer_phone', true );

		ob_start();
		?>
		<div class="bkx-razorpay-payment-form" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">
			<div class="payment-summary">
				<h4><?php esc_html_e( 'Payment Summary', 'bkx-razorpay' ); ?></h4>
				<div class="amount-row">
					<span><?php esc_html_e( 'Total Amount:', 'bkx-razorpay' ); ?></span>
					<strong><?php echo esc_html( bkx_format_price( $total ) ); ?></strong>
				</div>
			</div>

			<input type="hidden" class="razorpay-amount" value="<?php echo esc_attr( $total * 100 ); ?>">
			<input type="hidden" class="razorpay-currency" value="<?php echo esc_attr( get_option( 'bkx_currency', 'INR' ) ); ?>">
			<input type="hidden" class="razorpay-email" value="<?php echo esc_attr( $customer_email ); ?>">
			<input type="hidden" class="razorpay-name" value="<?php echo esc_attr( $customer_name ); ?>">
			<input type="hidden" class="razorpay-phone" value="<?php echo esc_attr( $customer_phone ); ?>">

			<button type="button" class="button bkx-razorpay-pay-button">
				<?php echo esc_html( sprintf( __( 'Pay %s', 'bkx-razorpay' ), bkx_format_price( $total ) ) ); ?>
			</button>

			<div class="payment-methods-info">
				<p><?php esc_html_e( 'Secure payment via Razorpay', 'bkx-razorpay' ); ?></p>
				<div class="payment-icons">
					<span class="icon-card" title="<?php esc_attr_e( 'Credit/Debit Cards', 'bkx-razorpay' ); ?>"></span>
					<span class="icon-upi" title="<?php esc_attr_e( 'UPI', 'bkx-razorpay' ); ?>"></span>
					<span class="icon-netbanking" title="<?php esc_attr_e( 'Net Banking', 'bkx-razorpay' ); ?>"></span>
					<span class="icon-wallet" title="<?php esc_attr_e( 'Wallets', 'bkx-razorpay' ); ?>"></span>
				</div>
			</div>

			<?php if ( $this->is_test_mode() ) : ?>
				<div class="test-mode-notice">
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Test mode is enabled. No real payments will be processed.', 'bkx-razorpay' ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * AJAX: Create Razorpay order.
	 */
	public function ajax_create_order() {
		check_ajax_referer( 'bkx_razorpay_nonce', 'nonce' );

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;

		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid booking ID.', 'bkx-razorpay' ) ) );
		}

		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Booking not found.', 'bkx-razorpay' ) ) );
		}

		$total    = (float) get_post_meta( $booking_id, 'booking_total', true );
		$currency = get_option( 'bkx_currency', 'INR' );

		// Create Razorpay order.
		$order = $this->api->create_order(
			array(
				'amount'   => $this->convert_to_subunit( $total, $currency ),
				'currency' => $currency,
				'receipt'  => 'bkx_' . $booking_id,
				'notes'    => array(
					'booking_id' => $booking_id,
					'site_url'   => home_url(),
				),
			)
		);

		if ( is_wp_error( $order ) ) {
			wp_send_json_error( array( 'message' => $order->get_error_message() ) );
		}

		// Store transaction.
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'bkx_razorpay_transactions',
			array(
				'booking_id' => $booking_id,
				'order_id'   => $order['id'],
				'amount'     => $order['amount'],
				'currency'   => $order['currency'],
				'status'     => 'created',
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);

		wp_send_json_success(
			array(
				'order_id' => $order['id'],
				'amount'   => $order['amount'],
				'currency' => $order['currency'],
			)
		);
	}

	/**
	 * AJAX: Verify payment.
	 */
	public function ajax_verify_payment() {
		check_ajax_referer( 'bkx_razorpay_nonce', 'nonce' );

		$order_id   = isset( $_POST['razorpay_order_id'] ) ? sanitize_text_field( wp_unslash( $_POST['razorpay_order_id'] ) ) : '';
		$payment_id = isset( $_POST['razorpay_payment_id'] ) ? sanitize_text_field( wp_unslash( $_POST['razorpay_payment_id'] ) ) : '';
		$signature  = isset( $_POST['razorpay_signature'] ) ? sanitize_text_field( wp_unslash( $_POST['razorpay_signature'] ) ) : '';
		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;

		if ( ! $order_id || ! $payment_id || ! $signature ) {
			wp_send_json_error( array( 'message' => __( 'Missing payment details.', 'bkx-razorpay' ) ) );
		}

		// Verify signature.
		$is_valid = $this->verify_signature( $order_id, $payment_id, $signature );

		if ( ! $is_valid ) {
			$this->log_error( 'Signature verification failed', compact( 'order_id', 'payment_id' ) );
			wp_send_json_error( array( 'message' => __( 'Payment verification failed.', 'bkx-razorpay' ) ) );
		}

		// Get payment details.
		$payment = $this->api->get_payment( $payment_id );

		if ( is_wp_error( $payment ) ) {
			wp_send_json_error( array( 'message' => $payment->get_error_message() ) );
		}

		// Update transaction.
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bkx_razorpay_transactions',
			array(
				'payment_id'     => $payment_id,
				'status'         => $payment['status'],
				'payment_method' => $payment['method'] ?? '',
				'raw_response'   => wp_json_encode( $payment ),
			),
			array( 'order_id' => $order_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%s' )
		);

		// Update booking status.
		if ( 'captured' === $payment['status'] || 'authorized' === $payment['status'] ) {
			wp_update_post(
				array(
					'ID'          => $booking_id,
					'post_status' => 'bkx-ack',
				)
			);

			update_post_meta( $booking_id, 'payment_status', 'paid' );
			update_post_meta( $booking_id, 'razorpay_payment_id', $payment_id );
			update_post_meta( $booking_id, 'razorpay_order_id', $order_id );

			/**
			 * Fires after a successful Razorpay payment.
			 *
			 * @param int   $booking_id Booking ID.
			 * @param array $payment    Payment details.
			 */
			do_action( 'bkx_razorpay_payment_success', $booking_id, $payment );

			wp_send_json_success(
				array(
					'message'    => __( 'Payment successful!', 'bkx-razorpay' ),
					'booking_id' => $booking_id,
					'payment_id' => $payment_id,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: Payment status */
						__( 'Payment status: %s', 'bkx-razorpay' ),
						$payment['status']
					),
				)
			);
		}
	}

	/**
	 * Verify payment signature.
	 *
	 * @param string $order_id   Order ID.
	 * @param string $payment_id Payment ID.
	 * @param string $signature  Signature.
	 * @return bool
	 */
	private function verify_signature( $order_id, $payment_id, $signature ) {
		$key_secret = $this->get_key_secret();

		if ( empty( $key_secret ) ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $order_id . '|' . $payment_id, $key_secret );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Convert amount to subunit.
	 *
	 * @param float  $amount   Amount.
	 * @param string $currency Currency code.
	 * @return int
	 */
	private function convert_to_subunit( $amount, $currency ) {
		$three_decimal_currencies = array( 'KWD', 'BHD', 'OMR' );

		if ( in_array( $currency, $three_decimal_currencies, true ) ) {
			return (int) round( $amount * 1000 );
		}

		return (int) round( $amount * 100 );
	}

	/**
	 * Register webhook endpoint.
	 */
	public function register_webhook_endpoint() {
		register_rest_route(
			'bkx-razorpay/v1',
			'/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle webhook.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$payload   = $request->get_body();
		$signature = $request->get_header( 'X-Razorpay-Signature' );

		// Verify webhook signature.
		$webhook_secret = $this->decrypt_key( $this->get_setting( 'webhook_secret', '' ) );

		if ( $webhook_secret ) {
			$expected_signature = hash_hmac( 'sha256', $payload, $webhook_secret );

			if ( ! hash_equals( $expected_signature, $signature ) ) {
				$this->log_error( 'Webhook signature verification failed' );
				return new \WP_REST_Response( array( 'error' => 'Invalid signature' ), 400 );
			}
		}

		$data = json_decode( $payload, true );

		if ( ! $data || ! isset( $data['event'] ) ) {
			return new \WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
		}

		$event = $data['event'];

		/**
		 * Fires on any Razorpay webhook event.
		 *
		 * @param string $event Event name.
		 * @param array  $data  Webhook data.
		 */
		do_action( 'bkx_razorpay_webhook', $event, $data );

		switch ( $event ) {
			case 'payment.captured':
				$this->handle_payment_captured( $data );
				break;

			case 'payment.failed':
				$this->handle_payment_failed( $data );
				break;

			case 'refund.processed':
				$this->handle_refund_processed( $data );
				break;
		}

		return new \WP_REST_Response( array( 'status' => 'ok' ), 200 );
	}

	/**
	 * Handle payment captured webhook.
	 *
	 * @param array $data Webhook data.
	 */
	private function handle_payment_captured( $data ) {
		$payment    = $data['payload']['payment']['entity'] ?? array();
		$payment_id = $payment['id'] ?? '';
		$order_id   = $payment['order_id'] ?? '';

		if ( ! $order_id ) {
			return;
		}

		global $wpdb;

		// Get booking ID from transaction.
		$transaction = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_razorpay_transactions WHERE order_id = %s",
				$order_id
			)
		);

		if ( ! $transaction ) {
			return;
		}

		// Update transaction.
		$wpdb->update(
			$wpdb->prefix . 'bkx_razorpay_transactions',
			array(
				'payment_id'     => $payment_id,
				'status'         => 'captured',
				'payment_method' => $payment['method'] ?? '',
				'raw_response'   => wp_json_encode( $payment ),
			),
			array( 'order_id' => $order_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%s' )
		);

		// Update booking.
		$current_status = get_post_status( $transaction->booking_id );
		if ( 'bkx-pending' === $current_status ) {
			wp_update_post(
				array(
					'ID'          => $transaction->booking_id,
					'post_status' => 'bkx-ack',
				)
			);

			update_post_meta( $transaction->booking_id, 'payment_status', 'paid' );
			update_post_meta( $transaction->booking_id, 'razorpay_payment_id', $payment_id );
		}

		/**
		 * Fires after payment captured via webhook.
		 *
		 * @param int   $booking_id Booking ID.
		 * @param array $payment    Payment data.
		 */
		do_action( 'bkx_razorpay_webhook_payment_captured', $transaction->booking_id, $payment );
	}

	/**
	 * Handle payment failed webhook.
	 *
	 * @param array $data Webhook data.
	 */
	private function handle_payment_failed( $data ) {
		$payment    = $data['payload']['payment']['entity'] ?? array();
		$order_id   = $payment['order_id'] ?? '';
		$error_code = $payment['error_code'] ?? '';
		$error_desc = $payment['error_description'] ?? '';

		if ( ! $order_id ) {
			return;
		}

		global $wpdb;

		// Update transaction.
		$wpdb->update(
			$wpdb->prefix . 'bkx_razorpay_transactions',
			array(
				'status'            => 'failed',
				'error_code'        => $error_code,
				'error_description' => $error_desc,
				'raw_response'      => wp_json_encode( $payment ),
			),
			array( 'order_id' => $order_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%s' )
		);

		$this->log_error( 'Payment failed', compact( 'order_id', 'error_code', 'error_desc' ) );
	}

	/**
	 * Handle refund processed webhook.
	 *
	 * @param array $data Webhook data.
	 */
	private function handle_refund_processed( $data ) {
		$refund     = $data['payload']['refund']['entity'] ?? array();
		$payment_id = $refund['payment_id'] ?? '';
		$refund_id  = $refund['id'] ?? '';
		$amount     = $refund['amount'] ?? 0;

		if ( ! $payment_id ) {
			return;
		}

		global $wpdb;

		// Update transaction.
		$wpdb->update(
			$wpdb->prefix . 'bkx_razorpay_transactions',
			array(
				'refund_id'     => $refund_id,
				'refund_amount' => $amount,
				'status'        => 'refunded',
			),
			array( 'payment_id' => $payment_id ),
			array( '%s', '%d', '%s' ),
			array( '%s' )
		);

		// Get booking and update meta.
		$transaction = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_razorpay_transactions WHERE payment_id = %s",
				$payment_id
			)
		);

		if ( $transaction ) {
			update_post_meta( $transaction->booking_id, 'razorpay_refund_id', $refund_id );
			update_post_meta( $transaction->booking_id, 'payment_status', 'refunded' );

			/**
			 * Fires after refund processed via webhook.
			 *
			 * @param int   $booking_id Booking ID.
			 * @param array $refund     Refund data.
			 */
			do_action( 'bkx_razorpay_webhook_refund_processed', $transaction->booking_id, $refund );
		}
	}

	/**
	 * Maybe process refund on booking cancellation.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 */
	public function maybe_process_refund( $booking_id, $new_status, $old_status ) {
		if ( 'bkx-cancelled' !== $new_status ) {
			return;
		}

		$payment_id = get_post_meta( $booking_id, 'razorpay_payment_id', true );
		$payment_status = get_post_meta( $booking_id, 'payment_status', true );

		if ( ! $payment_id || 'paid' !== $payment_status ) {
			return;
		}

		// Check if already refunded.
		$refund_id = get_post_meta( $booking_id, 'razorpay_refund_id', true );
		if ( $refund_id ) {
			return;
		}

		// Process refund.
		$refund = $this->api->create_refund( $payment_id );

		if ( is_wp_error( $refund ) ) {
			$this->log_error( 'Auto-refund failed: ' . $refund->get_error_message(), array( 'booking_id' => $booking_id ) );
			return;
		}

		update_post_meta( $booking_id, 'razorpay_refund_id', $refund['id'] );
		update_post_meta( $booking_id, 'payment_status', 'refunded' );

		// Update transaction.
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'bkx_razorpay_transactions',
			array(
				'refund_id'     => $refund['id'],
				'refund_amount' => $refund['amount'],
				'status'        => 'refunded',
			),
			array( 'payment_id' => $payment_id ),
			array( '%s', '%d', '%s' ),
			array( '%s' )
		);

		/**
		 * Fires after auto-refund is processed.
		 *
		 * @param int   $booking_id Booking ID.
		 * @param array $refund     Refund data.
		 */
		do_action( 'bkx_razorpay_auto_refund_processed', $booking_id, $refund );
	}

	/**
	 * Log error.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	private function log_error( $message, $context = array() ) {
		if ( class_exists( 'BKX_Error_Logger' ) ) {
			\BKX_Error_Logger::log( $message, 'error', $context );
		} else {
			error_log( sprintf( '[BKX Razorpay] %s - %s', $message, wp_json_encode( $context ) ) );
		}
	}
}
