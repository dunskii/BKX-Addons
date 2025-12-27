<?php
/**
 * Settings Page
 *
 * Admin settings page for Razorpay configuration.
 *
 * @package BookingX\Razorpay\Admin
 * @since   1.0.0
 */

namespace BookingX\Razorpay\Admin;

use BookingX\Razorpay\RazorpayAddon;
use BookingX\Razorpay\Controllers\WebhookController;

/**
 * Settings page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var RazorpayAddon
	 */
	protected RazorpayAddon $addon;

	/**
	 * Option group.
	 *
	 * @var string
	 */
	protected string $option_group = 'bkx_razorpay';

	/**
	 * Constructor.
	 *
	 * @param RazorpayAddon $addon Addon instance.
	 */
	public function __construct( RazorpayAddon $addon ) {
		$this->addon = $addon;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function init_hooks(): void {
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_razorpay', array( $this, 'render_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_bkx_razorpay_save_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Add settings tab.
	 *
	 * @since 1.0.0
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( array $tabs ): array {
		$tabs['razorpay'] = __( 'Razorpay', 'bkx-razorpay' );
		return $tabs;
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			$this->option_group,
			'bkx_razorpay_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @since 1.0.0
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Boolean fields.
		$boolean_fields = array(
			'enabled',
			'enable_upi',
			'enable_cards',
			'enable_netbanking',
			'enable_wallet',
			'auto_refund_on_cancel',
			'debug_log',
		);

		foreach ( $boolean_fields as $field ) {
			$sanitized[ $field ] = ! empty( $input[ $field ] );
		}

		// Select fields with allowed values.
		$select_fields = array(
			'razorpay_mode'  => array( 'test', 'live' ),
			'payment_action' => array( 'capture', 'authorize' ),
			'currency'       => array( 'INR', 'USD', 'EUR', 'GBP', 'SGD', 'AED', 'MYR' ),
		);

		foreach ( $select_fields as $field => $allowed ) {
			$value = $input[ $field ] ?? '';
			$sanitized[ $field ] = in_array( $value, $allowed, true ) ? $value : $allowed[0];
		}

		// Text fields.
		$text_fields = array(
			'key_id',
			'key_secret',
			'webhook_secret',
			'order_prefix',
		);

		foreach ( $text_fields as $field ) {
			$sanitized[ $field ] = sanitize_text_field( $input[ $field ] ?? '' );
		}

		return $sanitized;
	}

	/**
	 * Save settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function save_settings(): void {
		// Verify nonce.
		if ( ! isset( $_POST['bkx_razorpay_nonce'] ) ||
			 ! wp_verify_nonce( sanitize_key( $_POST['bkx_razorpay_nonce'] ), 'bkx_razorpay_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bkx-razorpay' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bkx-razorpay' ) );
		}

		// Get and sanitize posted data.
		$posted_data = array();
		if ( isset( $_POST['bkx_razorpay'] ) && is_array( $_POST['bkx_razorpay'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in sanitize_settings()
			$posted_data = wp_unslash( $_POST['bkx_razorpay'] );
		}

		$sanitized = $this->sanitize_settings( $posted_data );

		// Save settings.
		$this->addon->save_settings( $sanitized );

		// Redirect back with success message.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'bookingx-settings',
					'tab'     => 'razorpay',
					'updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings_page(): void {
		$settings = $this->addon->get_all_settings();
		$webhook_url = WebhookController::get_webhook_url();

		// Check if settings were just updated.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- just checking for display message
		$updated = isset( $_GET['updated'] ) && 'true' === $_GET['updated'];
		?>
		<div class="bkx-razorpay-settings">
			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved successfully.', 'bkx-razorpay' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bkx_razorpay_save_settings">
				<?php wp_nonce_field( 'bkx_razorpay_save_settings', 'bkx_razorpay_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<!-- Enable/Disable -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Enable/Disable', 'bkx-razorpay' ); ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="bkx_razorpay[enabled]" value="1"
										<?php checked( $settings['enabled'] ?? false ); ?>>
									<?php esc_html_e( 'Enable Razorpay', 'bkx-razorpay' ); ?>
								</label>
							</td>
						</tr>

						<!-- Mode -->
						<tr>
							<th scope="row">
								<label for="razorpay_mode"><?php esc_html_e( 'Mode', 'bkx-razorpay' ); ?></label>
							</th>
							<td>
								<select name="bkx_razorpay[razorpay_mode]" id="razorpay_mode">
									<option value="test" <?php selected( $settings['razorpay_mode'] ?? 'test', 'test' ); ?>>
										<?php esc_html_e( 'Test Mode', 'bkx-razorpay' ); ?>
									</option>
									<option value="live" <?php selected( $settings['razorpay_mode'] ?? 'test', 'live' ); ?>>
										<?php esc_html_e( 'Live Mode', 'bkx-razorpay' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Use Test mode for testing. Switch to Live for production.', 'bkx-razorpay' ); ?>
								</p>
							</td>
						</tr>

						<!-- Key ID -->
						<tr>
							<th scope="row">
								<label for="key_id"><?php esc_html_e( 'Key ID', 'bkx-razorpay' ); ?></label>
							</th>
							<td>
								<input type="text" name="bkx_razorpay[key_id]" id="key_id"
									value="<?php echo esc_attr( $settings['key_id'] ?? '' ); ?>"
									class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Your Razorpay Key ID from the Dashboard.', 'bkx-razorpay' ); ?>
								</p>
							</td>
						</tr>

						<!-- Key Secret -->
						<tr>
							<th scope="row">
								<label for="key_secret"><?php esc_html_e( 'Key Secret', 'bkx-razorpay' ); ?></label>
							</th>
							<td>
								<input type="password" name="bkx_razorpay[key_secret]" id="key_secret"
									value="<?php echo esc_attr( $settings['key_secret'] ?? '' ); ?>"
									class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Your Razorpay Key Secret.', 'bkx-razorpay' ); ?>
								</p>
							</td>
						</tr>

						<!-- Webhook Secret -->
						<tr>
							<th scope="row">
								<label for="webhook_secret"><?php esc_html_e( 'Webhook Secret', 'bkx-razorpay' ); ?></label>
							</th>
							<td>
								<input type="password" name="bkx_razorpay[webhook_secret]" id="webhook_secret"
									value="<?php echo esc_attr( $settings['webhook_secret'] ?? '' ); ?>"
									class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Your webhook secret for signature verification.', 'bkx-razorpay' ); ?>
								</p>
							</td>
						</tr>

						<!-- Webhook URL -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Webhook URL', 'bkx-razorpay' ); ?>
							</th>
							<td>
								<code><?php echo esc_html( $webhook_url ); ?></code>
								<button type="button" class="button button-secondary bkx-copy-webhook-url"
									data-clipboard-text="<?php echo esc_attr( $webhook_url ); ?>">
									<?php esc_html_e( 'Copy', 'bkx-razorpay' ); ?>
								</button>
								<p class="description">
									<?php esc_html_e( 'Add this URL to your Razorpay Dashboard under Settings > Webhooks.', 'bkx-razorpay' ); ?>
								</p>
							</td>
						</tr>

						<!-- Payment Action -->
						<tr>
							<th scope="row">
								<label for="payment_action"><?php esc_html_e( 'Payment Action', 'bkx-razorpay' ); ?></label>
							</th>
							<td>
								<select name="bkx_razorpay[payment_action]" id="payment_action">
									<option value="capture" <?php selected( $settings['payment_action'] ?? 'capture', 'capture' ); ?>>
										<?php esc_html_e( 'Capture (Immediate)', 'bkx-razorpay' ); ?>
									</option>
									<option value="authorize" <?php selected( $settings['payment_action'] ?? 'capture', 'authorize' ); ?>>
										<?php esc_html_e( 'Authorize Only', 'bkx-razorpay' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Choose whether to capture payment immediately or authorize only.', 'bkx-razorpay' ); ?>
								</p>
							</td>
						</tr>

						<!-- Currency -->
						<tr>
							<th scope="row">
								<label for="currency"><?php esc_html_e( 'Currency', 'bkx-razorpay' ); ?></label>
							</th>
							<td>
								<select name="bkx_razorpay[currency]" id="currency">
									<?php
									$currencies = array(
										'INR' => __( 'Indian Rupee (INR)', 'bkx-razorpay' ),
										'USD' => __( 'US Dollar (USD)', 'bkx-razorpay' ),
										'EUR' => __( 'Euro (EUR)', 'bkx-razorpay' ),
										'GBP' => __( 'British Pound (GBP)', 'bkx-razorpay' ),
										'SGD' => __( 'Singapore Dollar (SGD)', 'bkx-razorpay' ),
										'AED' => __( 'UAE Dirham (AED)', 'bkx-razorpay' ),
										'MYR' => __( 'Malaysian Ringgit (MYR)', 'bkx-razorpay' ),
									);
									foreach ( $currencies as $code => $label ) :
										?>
										<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $settings['currency'] ?? 'INR', $code ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>

						<!-- Order Prefix -->
						<tr>
							<th scope="row">
								<label for="order_prefix"><?php esc_html_e( 'Order Prefix', 'bkx-razorpay' ); ?></label>
							</th>
							<td>
								<input type="text" name="bkx_razorpay[order_prefix]" id="order_prefix"
									value="<?php echo esc_attr( $settings['order_prefix'] ?? 'BKX-' ); ?>"
									class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Prefix for order receipts (e.g., BKX-123).', 'bkx-razorpay' ); ?>
								</p>
							</td>
						</tr>

						<!-- Payment Methods -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Payment Methods', 'bkx-razorpay' ); ?>
							</th>
							<td>
								<fieldset>
									<label style="display: block; margin-bottom: 5px;">
										<input type="checkbox" name="bkx_razorpay[enable_upi]" value="1"
											<?php checked( $settings['enable_upi'] ?? true ); ?>>
										<?php esc_html_e( 'UPI (Google Pay, PhonePe, etc.)', 'bkx-razorpay' ); ?>
									</label>
									<label style="display: block; margin-bottom: 5px;">
										<input type="checkbox" name="bkx_razorpay[enable_cards]" value="1"
											<?php checked( $settings['enable_cards'] ?? true ); ?>>
										<?php esc_html_e( 'Credit/Debit Cards', 'bkx-razorpay' ); ?>
									</label>
									<label style="display: block; margin-bottom: 5px;">
										<input type="checkbox" name="bkx_razorpay[enable_netbanking]" value="1"
											<?php checked( $settings['enable_netbanking'] ?? true ); ?>>
										<?php esc_html_e( 'Net Banking', 'bkx-razorpay' ); ?>
									</label>
									<label style="display: block; margin-bottom: 5px;">
										<input type="checkbox" name="bkx_razorpay[enable_wallet]" value="1"
											<?php checked( $settings['enable_wallet'] ?? true ); ?>>
										<?php esc_html_e( 'Wallets (Paytm, Amazon Pay, etc.)', 'bkx-razorpay' ); ?>
									</label>
								</fieldset>
							</td>
						</tr>

						<!-- Auto Refund -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Auto Refund', 'bkx-razorpay' ); ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="bkx_razorpay[auto_refund_on_cancel]" value="1"
										<?php checked( $settings['auto_refund_on_cancel'] ?? true ); ?>>
									<?php esc_html_e( 'Automatically refund when booking is cancelled', 'bkx-razorpay' ); ?>
								</label>
							</td>
						</tr>

						<!-- Debug Log -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Debug Log', 'bkx-razorpay' ); ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="bkx_razorpay[debug_log]" value="1"
										<?php checked( $settings['debug_log'] ?? false ); ?>>
									<?php esc_html_e( 'Enable debug logging', 'bkx-razorpay' ); ?>
								</label>
								<p class="description">
									<?php
									printf(
										/* translators: %s: log file path */
										esc_html__( 'Logs are saved to %s', 'bkx-razorpay' ),
										'<code>wp-content/bkx-razorpay-debug.log</code>'
									);
									?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Settings', 'bkx-razorpay' ) ); ?>
			</form>
		</div>
		<?php
	}
}
