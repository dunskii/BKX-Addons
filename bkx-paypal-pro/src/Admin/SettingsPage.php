<?php
/**
 * PayPal Pro Settings Page
 *
 * @package BookingX\PayPalPro\Admin
 * @since   1.0.0
 */

namespace BookingX\PayPalPro\Admin;

use BookingX\PayPalPro\PayPalPro;
use BookingX\PayPalPro\Gateway\PayPalGateway;

/**
 * Settings page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var PayPalPro
	 */
	protected PayPalPro $addon;

	/**
	 * Gateway instance.
	 *
	 * @var PayPalGateway|null
	 */
	protected ?PayPalGateway $gateway = null;

	/**
	 * Constructor.
	 *
	 * @param PayPalPro $addon Addon instance.
	 */
	public function __construct( PayPalPro $addon ) {
		$this->addon   = $addon;
		$this->gateway = new PayPalGateway();

		// Register settings.
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Add settings sections and fields.
		add_action( 'bkx_settings_paypal_pro', array( $this, 'render_settings_page' ) );

		// Handle settings save.
		add_action( 'admin_post_bkx_save_paypal_pro_settings', array( $this, 'save_settings' ) );

		// AJAX test connection.
		add_action( 'wp_ajax_bkx_paypal_pro_test_connection', array( $this, 'test_connection' ) );
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'bkx_paypal_pro_settings',
			'bkx_paypal_pro_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings_page(): void {
		$settings = $this->addon->get_all_settings();
		?>
		<div class="wrap">
			<h2><?php esc_html_e( 'PayPal Pro Settings', 'bkx-paypal-pro' ); ?></h2>

			<?php settings_errors( 'bkx_paypal_pro_settings' ); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'bkx_paypal_pro_settings', 'bkx_paypal_pro_nonce' ); ?>
				<input type="hidden" name="action" value="bkx_save_paypal_pro_settings">

				<table class="form-table">
					<!-- Enable PayPal -->
					<tr>
						<th scope="row">
							<label for="enabled"><?php esc_html_e( 'Enable PayPal', 'bkx-paypal-pro' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" name="settings[enabled]" id="enabled" value="1" <?php checked( $settings['enabled'] ?? false, true ); ?>>
								<?php esc_html_e( 'Accept payments via PayPal', 'bkx-paypal-pro' ); ?>
							</label>
						</td>
					</tr>

					<!-- PayPal Mode -->
					<tr>
						<th scope="row">
							<label for="paypal_mode"><?php esc_html_e( 'PayPal Mode', 'bkx-paypal-pro' ); ?></label>
						</th>
						<td>
							<select name="settings[paypal_mode]" id="paypal_mode">
								<option value="sandbox" <?php selected( $settings['paypal_mode'] ?? 'sandbox', 'sandbox' ); ?>>
									<?php esc_html_e( 'Sandbox (Testing)', 'bkx-paypal-pro' ); ?>
								</option>
								<option value="live" <?php selected( $settings['paypal_mode'] ?? 'sandbox', 'live' ); ?>>
									<?php esc_html_e( 'Live (Production)', 'bkx-paypal-pro' ); ?>
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Select sandbox for testing or live for production.', 'bkx-paypal-pro' ); ?>
							</p>
						</td>
					</tr>

					<!-- Sandbox Credentials -->
					<tr class="sandbox-row">
						<th scope="row">
							<label for="paypal_sandbox_client_id"><?php esc_html_e( 'Sandbox Client ID', 'bkx-paypal-pro' ); ?></label>
						</th>
						<td>
							<input type="text" name="settings[paypal_sandbox_client_id]" id="paypal_sandbox_client_id"
								   value="<?php echo esc_attr( $settings['paypal_sandbox_client_id'] ?? '' ); ?>" class="regular-text">
							<p class="description">
								<?php
								printf(
									/* translators: %s: PayPal Developer Dashboard URL */
									esc_html__( 'Get your API credentials from %s', 'bkx-paypal-pro' ),
									'<a href="https://developer.paypal.com/dashboard/" target="_blank">' . esc_html__( 'PayPal Developer Dashboard', 'bkx-paypal-pro' ) . '</a>'
								);
								?>
							</p>
						</td>
					</tr>

					<tr class="sandbox-row">
						<th scope="row">
							<label for="paypal_sandbox_client_secret"><?php esc_html_e( 'Sandbox Client Secret', 'bkx-paypal-pro' ); ?></label>
						</th>
						<td>
							<input type="password" name="settings[paypal_sandbox_client_secret]" id="paypal_sandbox_client_secret"
								   value="<?php echo esc_attr( $settings['paypal_sandbox_client_secret'] ?? '' ); ?>" class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Keep this secret safe and never share it.', 'bkx-paypal-pro' ); ?>
							</p>
						</td>
					</tr>

					<!-- Live Credentials -->
					<tr class="live-row">
						<th scope="row">
							<label for="paypal_live_client_id"><?php esc_html_e( 'Live Client ID', 'bkx-paypal-pro' ); ?></label>
						</th>
						<td>
							<input type="text" name="settings[paypal_live_client_id]" id="paypal_live_client_id"
								   value="<?php echo esc_attr( $settings['paypal_live_client_id'] ?? '' ); ?>" class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Live API credentials for production.', 'bkx-paypal-pro' ); ?>
							</p>
						</td>
					</tr>

					<tr class="live-row">
						<th scope="row">
							<label for="paypal_live_client_secret"><?php esc_html_e( 'Live Client Secret', 'bkx-paypal-pro' ); ?></label>
						</th>
						<td>
							<input type="password" name="settings[paypal_live_client_secret]" id="paypal_live_client_secret"
								   value="<?php echo esc_attr( $settings['paypal_live_client_secret'] ?? '' ); ?>" class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Live client secret for production.', 'bkx-paypal-pro' ); ?>
							</p>
						</td>
					</tr>

					<!-- Test Connection Button -->
					<tr>
						<th scope="row"></th>
						<td>
							<button type="button" id="test-paypal-connection" class="button">
								<?php esc_html_e( 'Test Connection', 'bkx-paypal-pro' ); ?>
							</button>
							<span class="spinner"></span>
							<span id="connection-result"></span>
						</td>
					</tr>

					<!-- Webhook ID -->
					<tr>
						<th scope="row">
							<label for="paypal_webhook_id"><?php esc_html_e( 'Webhook ID', 'bkx-paypal-pro' ); ?></label>
						</th>
						<td>
							<input type="text" name="settings[paypal_webhook_id]" id="paypal_webhook_id"
								   value="<?php echo esc_attr( $settings['paypal_webhook_id'] ?? '' ); ?>" class="regular-text">
							<p class="description">
								<?php
								printf(
									/* translators: %s: webhook URL */
									esc_html__( 'Create a webhook in PayPal Dashboard with this URL: %s', 'bkx-paypal-pro' ),
									'<br><code>' . esc_url( rest_url( 'bookingx/v1/webhooks/bkx_paypal_pro' ) ) . '</code>'
								);
								?>
							</p>
						</td>
					</tr>

					<!-- Advanced Card Processing -->
					<tr>
						<th scope="row">
							<label for="enable_card_fields"><?php esc_html_e( 'Advanced Card Processing', 'bkx-paypal-pro' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" name="settings[enable_card_fields]" id="enable_card_fields" value="1"
									   <?php checked( $settings['enable_card_fields'] ?? false, true ); ?>>
								<?php esc_html_e( 'Allow customers to enter credit/debit card details directly', 'bkx-paypal-pro' ); ?>
							</label>
						</td>
					</tr>

					<!-- Pay Later -->
					<tr>
						<th scope="row">
							<label for="enable_pay_later"><?php esc_html_e( 'Pay in 4', 'bkx-paypal-pro' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" name="settings[enable_pay_later]" id="enable_pay_later" value="1"
									   <?php checked( $settings['enable_pay_later'] ?? true, true ); ?>>
								<?php esc_html_e( 'Show Pay Later option to eligible customers', 'bkx-paypal-pro' ); ?>
							</label>
						</td>
					</tr>

					<!-- Button Color -->
					<tr>
						<th scope="row">
							<label for="button_color"><?php esc_html_e( 'Button Color', 'bkx-paypal-pro' ); ?></label>
						</th>
						<td>
							<select name="settings[button_color]" id="button_color">
								<option value="gold" <?php selected( $settings['button_color'] ?? 'gold', 'gold' ); ?>><?php esc_html_e( 'Gold', 'bkx-paypal-pro' ); ?></option>
								<option value="blue" <?php selected( $settings['button_color'] ?? 'gold', 'blue' ); ?>><?php esc_html_e( 'Blue', 'bkx-paypal-pro' ); ?></option>
								<option value="silver" <?php selected( $settings['button_color'] ?? 'gold', 'silver' ); ?>><?php esc_html_e( 'Silver', 'bkx-paypal-pro' ); ?></option>
								<option value="white" <?php selected( $settings['button_color'] ?? 'gold', 'white' ); ?>><?php esc_html_e( 'White', 'bkx-paypal-pro' ); ?></option>
								<option value="black" <?php selected( $settings['button_color'] ?? 'gold', 'black' ); ?>><?php esc_html_e( 'Black', 'bkx-paypal-pro' ); ?></option>
							</select>
						</td>
					</tr>

					<!-- Button Shape -->
					<tr>
						<th scope="row">
							<label for="button_shape"><?php esc_html_e( 'Button Shape', 'bkx-paypal-pro' ); ?></label>
						</th>
						<td>
							<select name="settings[button_shape]" id="button_shape">
								<option value="rect" <?php selected( $settings['button_shape'] ?? 'rect', 'rect' ); ?>><?php esc_html_e( 'Rectangle', 'bkx-paypal-pro' ); ?></option>
								<option value="pill" <?php selected( $settings['button_shape'] ?? 'rect', 'pill' ); ?>><?php esc_html_e( 'Pill', 'bkx-paypal-pro' ); ?></option>
							</select>
						</td>
					</tr>

					<!-- Payment Intent -->
					<tr>
						<th scope="row">
							<label for="intent"><?php esc_html_e( 'Payment Intent', 'bkx-paypal-pro' ); ?></label>
						</th>
						<td>
							<select name="settings[intent]" id="intent">
								<option value="capture" <?php selected( $settings['intent'] ?? 'capture', 'capture' ); ?>><?php esc_html_e( 'Capture', 'bkx-paypal-pro' ); ?></option>
								<option value="authorize" <?php selected( $settings['intent'] ?? 'capture', 'authorize' ); ?>><?php esc_html_e( 'Authorize', 'bkx-paypal-pro' ); ?></option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Capture immediately or authorize for later capture.', 'bkx-paypal-pro' ); ?>
							</p>
						</td>
					</tr>

					<!-- Currency -->
					<tr>
						<th scope="row">
							<label for="currency"><?php esc_html_e( 'Currency', 'bkx-paypal-pro' ); ?></label>
						</th>
						<td>
							<select name="settings[currency]" id="currency">
								<option value="USD" <?php selected( $settings['currency'] ?? 'USD', 'USD' ); ?>><?php esc_html_e( 'US Dollar', 'bkx-paypal-pro' ); ?></option>
								<option value="EUR" <?php selected( $settings['currency'] ?? 'USD', 'EUR' ); ?>><?php esc_html_e( 'Euro', 'bkx-paypal-pro' ); ?></option>
								<option value="GBP" <?php selected( $settings['currency'] ?? 'USD', 'GBP' ); ?>><?php esc_html_e( 'British Pound', 'bkx-paypal-pro' ); ?></option>
								<option value="CAD" <?php selected( $settings['currency'] ?? 'USD', 'CAD' ); ?>><?php esc_html_e( 'Canadian Dollar', 'bkx-paypal-pro' ); ?></option>
								<option value="AUD" <?php selected( $settings['currency'] ?? 'USD', 'AUD' ); ?>><?php esc_html_e( 'Australian Dollar', 'bkx-paypal-pro' ); ?></option>
								<option value="JPY" <?php selected( $settings['currency'] ?? 'USD', 'JPY' ); ?>><?php esc_html_e( 'Japanese Yen', 'bkx-paypal-pro' ); ?></option>
							</select>
						</td>
					</tr>

					<!-- Debug Logging -->
					<tr>
						<th scope="row">
							<label for="debug_log"><?php esc_html_e( 'Debug Logging', 'bkx-paypal-pro' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" name="settings[debug_log]" id="debug_log" value="1"
									   <?php checked( $settings['debug_log'] ?? false, true ); ?>>
								<?php esc_html_e( 'Log all PayPal API requests and responses', 'bkx-paypal-pro' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Save settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function save_settings(): void {
		// Check nonce.
		if ( ! isset( $_POST['bkx_paypal_pro_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_paypal_pro_nonce'] ) ), 'bkx_paypal_pro_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bkx-paypal-pro' ) );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to save settings.', 'bkx-paypal-pro' ) );
		}

		// Get settings data.
		$settings = isset( $_POST['settings'] ) ? (array) $_POST['settings'] : array();

		// Sanitize and save.
		$sanitized = $this->sanitize_settings( $settings );
		$this->addon->save_all_settings( $sanitized );

		// Redirect back with success message.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'bkx_settings',
					'tab'     => 'paypal_pro',
					'updated' => 'true',
				),
				admin_url( 'edit.php?post_type=bkx_booking' )
			)
		);
		exit;
	}

	/**
	 * Sanitize settings.
	 *
	 * @since 1.0.0
	 * @param array $settings Settings to sanitize.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $settings ): array {
		$sanitized = array();

		// Boolean fields.
		$sanitized['enabled']           = ! empty( $settings['enabled'] );
		$sanitized['enable_card_fields'] = ! empty( $settings['enable_card_fields'] );
		$sanitized['enable_pay_later']  = ! empty( $settings['enable_pay_later'] );
		$sanitized['debug_log']         = ! empty( $settings['debug_log'] );

		// Text fields.
		$sanitized['paypal_sandbox_client_id']     = sanitize_text_field( $settings['paypal_sandbox_client_id'] ?? '' );
		$sanitized['paypal_sandbox_client_secret'] = sanitize_text_field( $settings['paypal_sandbox_client_secret'] ?? '' );
		$sanitized['paypal_live_client_id']        = sanitize_text_field( $settings['paypal_live_client_id'] ?? '' );
		$sanitized['paypal_live_client_secret']    = sanitize_text_field( $settings['paypal_live_client_secret'] ?? '' );
		$sanitized['paypal_webhook_id']            = sanitize_text_field( $settings['paypal_webhook_id'] ?? '' );

		// Select fields.
		$sanitized['paypal_mode']  = in_array( $settings['paypal_mode'] ?? 'sandbox', array( 'sandbox', 'live' ), true ) ? $settings['paypal_mode'] : 'sandbox';
		$sanitized['button_color'] = in_array( $settings['button_color'] ?? 'gold', array( 'gold', 'blue', 'silver', 'white', 'black' ), true ) ? $settings['button_color'] : 'gold';
		$sanitized['button_shape'] = in_array( $settings['button_shape'] ?? 'rect', array( 'rect', 'pill' ), true ) ? $settings['button_shape'] : 'rect';
		$sanitized['intent']       = in_array( $settings['intent'] ?? 'capture', array( 'capture', 'authorize' ), true ) ? $settings['intent'] : 'capture';
		$sanitized['currency']     = sanitize_text_field( $settings['currency'] ?? 'USD' );

		return $sanitized;
	}

	/**
	 * Test PayPal connection via AJAX.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function test_connection(): void {
		// Check nonce.
		check_ajax_referer( 'bkx_paypal_pro_admin', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-paypal-pro' ) ) );
		}

		// Test connection by getting access token.
		$client = $this->gateway->get_client();
		$token  = $client->get_access_token();

		if ( $token ) {
			wp_send_json_success( array( 'message' => __( 'Connection successful!', 'bkx-paypal-pro' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Connection failed. Please check your credentials.', 'bkx-paypal-pro' ) ) );
		}
	}
}
