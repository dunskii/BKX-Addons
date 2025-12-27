<?php
/**
 * Admin Settings Page
 *
 * @package BookingX\SquarePayments\Admin
 */

namespace BookingX\SquarePayments\Admin;

use BookingX\SquarePayments\SquarePayments;

/**
 * Settings page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Add-on instance.
	 *
	 * @var SquarePayments
	 */
	protected $addon;

	/**
	 * Constructor.
	 *
	 * @param SquarePayments $addon Add-on instance.
	 */
	public function __construct( SquarePayments $addon ) {
		$this->addon = $addon;

		// Register settings tab.
		add_filter( 'bkx_settings_tabs', array( $this, 'register_settings_tab' ) );

		// Register settings fields.
		add_action( 'bkx_settings_square_payments', array( $this, 'render_settings' ) );

		// Handle settings save.
		add_action( 'admin_init', array( $this, 'save_settings' ) );
	}

	/**
	 * Register settings tab with BookingX.
	 *
	 * @since 1.0.0
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function register_settings_tab( $tabs ) {
		$tabs['square_payments'] = __( 'Square Payments', 'bkx-square-payments' );
		return $tabs;
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_settings(): void {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap bkx-square-settings">
			<h2><?php esc_html_e( 'Square Payments Settings', 'bkx-square-payments' ); ?></h2>

			<form method="post" action="">
				<?php
				wp_nonce_field( 'bkx_square_settings', 'bkx_square_settings_nonce' );
				settings_fields( 'bkx_square_payments' );
				?>

				<table class="form-table">
					<tbody>
						<!-- Mode -->
						<tr>
							<th scope="row">
								<label for="square_mode">
									<?php esc_html_e( 'Mode', 'bkx-square-payments' ); ?>
								</label>
							</th>
							<td>
								<select name="square_mode" id="square_mode" class="regular-text">
									<option value="sandbox" <?php selected( $this->addon->get_setting( 'square_mode', 'sandbox' ), 'sandbox' ); ?>>
										<?php esc_html_e( 'Sandbox (Testing)', 'bkx-square-payments' ); ?>
									</option>
									<option value="production" <?php selected( $this->addon->get_setting( 'square_mode' ), 'production' ); ?>>
										<?php esc_html_e( 'Production (Live)', 'bkx-square-payments' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Select whether to use sandbox or production mode.', 'bkx-square-payments' ); ?>
								</p>
							</td>
						</tr>

						<!-- Sandbox Credentials -->
						<tr class="square-sandbox-row">
							<th colspan="2">
								<h3><?php esc_html_e( 'Sandbox Credentials', 'bkx-square-payments' ); ?></h3>
							</th>
						</tr>

						<tr class="square-sandbox-row">
							<th scope="row">
								<label for="square_sandbox_application_id">
									<?php esc_html_e( 'Sandbox Application ID', 'bkx-square-payments' ); ?>
								</label>
							</th>
							<td>
								<input type="text" name="square_sandbox_application_id" id="square_sandbox_application_id" value="<?php echo esc_attr( $this->addon->get_setting( 'square_sandbox_application_id', '' ) ); ?>" class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Get this from your Square Developer Dashboard.', 'bkx-square-payments' ); ?>
								</p>
							</td>
						</tr>

						<tr class="square-sandbox-row">
							<th scope="row">
								<label for="square_sandbox_access_token">
									<?php esc_html_e( 'Sandbox Access Token', 'bkx-square-payments' ); ?>
								</label>
							</th>
							<td>
								<input type="password" name="square_sandbox_access_token" id="square_sandbox_access_token" value="<?php echo esc_attr( $this->addon->get_setting( 'square_sandbox_access_token', '' ) ); ?>" class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Your sandbox access token from Square.', 'bkx-square-payments' ); ?>
								</p>
							</td>
						</tr>

						<tr class="square-sandbox-row">
							<th scope="row">
								<label for="square_sandbox_location_id">
									<?php esc_html_e( 'Sandbox Location ID', 'bkx-square-payments' ); ?>
								</label>
							</th>
							<td>
								<input type="text" name="square_sandbox_location_id" id="square_sandbox_location_id" value="<?php echo esc_attr( $this->addon->get_setting( 'square_sandbox_location_id', '' ) ); ?>" class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Your sandbox location ID.', 'bkx-square-payments' ); ?>
								</p>
							</td>
						</tr>

						<!-- Production Credentials -->
						<tr class="square-production-row">
							<th colspan="2">
								<h3><?php esc_html_e( 'Production Credentials', 'bkx-square-payments' ); ?></h3>
							</th>
						</tr>

						<tr class="square-production-row">
							<th scope="row">
								<label for="square_production_application_id">
									<?php esc_html_e( 'Production Application ID', 'bkx-square-payments' ); ?>
								</label>
							</th>
							<td>
								<input type="text" name="square_production_application_id" id="square_production_application_id" value="<?php echo esc_attr( $this->addon->get_setting( 'square_production_application_id', '' ) ); ?>" class="regular-text">
							</td>
						</tr>

						<tr class="square-production-row">
							<th scope="row">
								<label for="square_production_access_token">
									<?php esc_html_e( 'Production Access Token', 'bkx-square-payments' ); ?>
								</label>
							</th>
							<td>
								<input type="password" name="square_production_access_token" id="square_production_access_token" value="<?php echo esc_attr( $this->addon->get_setting( 'square_production_access_token', '' ) ); ?>" class="regular-text">
							</td>
						</tr>

						<tr class="square-production-row">
							<th scope="row">
								<label for="square_production_location_id">
									<?php esc_html_e( 'Production Location ID', 'bkx-square-payments' ); ?>
								</label>
							</th>
							<td>
								<input type="text" name="square_production_location_id" id="square_production_location_id" value="<?php echo esc_attr( $this->addon->get_setting( 'square_production_location_id', '' ) ); ?>" class="regular-text">
							</td>
						</tr>

						<!-- Webhook Settings -->
						<tr>
							<th colspan="2">
								<h3><?php esc_html_e( 'Webhook Settings', 'bkx-square-payments' ); ?></h3>
							</th>
						</tr>

						<tr>
							<th scope="row">
								<label for="webhook_url">
									<?php esc_html_e( 'Webhook URL', 'bkx-square-payments' ); ?>
								</label>
							</th>
							<td>
								<input type="text" id="webhook_url" value="<?php echo esc_url( rest_url( 'bookingx/v1/webhooks/square' ) ); ?>" class="regular-text" readonly>
								<button type="button" class="button" onclick="navigator.clipboard.writeText(this.previousElementSibling.value)">
									<?php esc_html_e( 'Copy', 'bkx-square-payments' ); ?>
								</button>
								<p class="description">
									<?php esc_html_e( 'Configure this URL in your Square Developer Dashboard under Webhooks.', 'bkx-square-payments' ); ?>
								</p>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="square_webhook_signature_key">
									<?php esc_html_e( 'Webhook Signature Key', 'bkx-square-payments' ); ?>
								</label>
							</th>
							<td>
								<input type="password" name="square_webhook_signature_key" id="square_webhook_signature_key" value="<?php echo esc_attr( $this->addon->get_setting( 'square_webhook_signature_key', '' ) ); ?>" class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Signature key provided by Square for webhook verification.', 'bkx-square-payments' ); ?>
								</p>
							</td>
						</tr>

						<!-- Payment Options -->
						<tr>
							<th colspan="2">
								<h3><?php esc_html_e( 'Payment Options', 'bkx-square-payments' ); ?></h3>
							</th>
						</tr>

						<tr>
							<th scope="row"><?php esc_html_e( 'Digital Wallets', 'bkx-square-payments' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="enable_apple_pay" value="1" <?php checked( $this->addon->get_setting( 'enable_apple_pay', false ), true ); ?>>
									<?php esc_html_e( 'Enable Apple Pay', 'bkx-square-payments' ); ?>
								</label>
								<br>
								<label>
									<input type="checkbox" name="enable_google_pay" value="1" <?php checked( $this->addon->get_setting( 'enable_google_pay', false ), true ); ?>>
									<?php esc_html_e( 'Enable Google Pay', 'bkx-square-payments' ); ?>
								</label>
								<br>
								<label>
									<input type="checkbox" name="enable_cash_app_pay" value="1" <?php checked( $this->addon->get_setting( 'enable_cash_app_pay', false ), true ); ?>>
									<?php esc_html_e( 'Enable Cash App Pay', 'bkx-square-payments' ); ?>
								</label>
							</td>
						</tr>

						<tr>
							<th scope="row">
								<label for="currency">
									<?php esc_html_e( 'Currency', 'bkx-square-payments' ); ?>
								</label>
							</th>
							<td>
								<select name="currency" id="currency">
									<?php
									$currencies = array( 'USD', 'CAD', 'GBP', 'EUR', 'AUD', 'JPY' );
									foreach ( $currencies as $curr ) {
										printf(
											'<option value="%s" %s>%s</option>',
											esc_attr( $curr ),
											selected( $this->addon->get_setting( 'currency', 'USD' ), $curr, false ),
											esc_html( $curr )
										);
									}
									?>
								</select>
							</td>
						</tr>

						<!-- Additional Options -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Additional Options', 'bkx-square-payments' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="enable_customer_sync" value="1" <?php checked( $this->addon->get_setting( 'enable_customer_sync', false ), true ); ?>>
									<?php esc_html_e( 'Sync customers to Square', 'bkx-square-payments' ); ?>
								</label>
								<br>
								<label>
									<input type="checkbox" name="auto_refund_on_cancel" value="1" <?php checked( $this->addon->get_setting( 'auto_refund_on_cancel', false ), true ); ?>>
									<?php esc_html_e( 'Automatically refund on booking cancellation', 'bkx-square-payments' ); ?>
								</label>
								<br>
								<label>
									<input type="checkbox" name="debug_log" value="1" <?php checked( $this->addon->get_setting( 'debug_log', false ), true ); ?>>
									<?php esc_html_e( 'Enable debug logging', 'bkx-square-payments' ); ?>
								</label>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Settings', 'bkx-square-payments' ) ); ?>
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
		// Check if this is a settings save request.
		if ( ! isset( $_POST['bkx_square_settings_nonce'] ) ) {
			return;
		}

		// Verify nonce.
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_square_settings_nonce'] ) ), 'bkx_square_settings' ) ) {
			return;
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Prepare settings array.
		$settings = array(
			'square_mode'                       => isset( $_POST['square_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['square_mode'] ) ) : 'sandbox',
			'square_sandbox_application_id'     => isset( $_POST['square_sandbox_application_id'] ) ? sanitize_text_field( wp_unslash( $_POST['square_sandbox_application_id'] ) ) : '',
			'square_sandbox_access_token'       => isset( $_POST['square_sandbox_access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['square_sandbox_access_token'] ) ) : '',
			'square_sandbox_location_id'        => isset( $_POST['square_sandbox_location_id'] ) ? sanitize_text_field( wp_unslash( $_POST['square_sandbox_location_id'] ) ) : '',
			'square_production_application_id'  => isset( $_POST['square_production_application_id'] ) ? sanitize_text_field( wp_unslash( $_POST['square_production_application_id'] ) ) : '',
			'square_production_access_token'    => isset( $_POST['square_production_access_token'] ) ? sanitize_text_field( wp_unslash( $_POST['square_production_access_token'] ) ) : '',
			'square_production_location_id'     => isset( $_POST['square_production_location_id'] ) ? sanitize_text_field( wp_unslash( $_POST['square_production_location_id'] ) ) : '',
			'square_webhook_signature_key'      => isset( $_POST['square_webhook_signature_key'] ) ? sanitize_text_field( wp_unslash( $_POST['square_webhook_signature_key'] ) ) : '',
			'enable_apple_pay'                  => isset( $_POST['enable_apple_pay'] ),
			'enable_google_pay'                 => isset( $_POST['enable_google_pay'] ),
			'enable_cash_app_pay'               => isset( $_POST['enable_cash_app_pay'] ),
			'enable_customer_sync'              => isset( $_POST['enable_customer_sync'] ),
			'auto_refund_on_cancel'             => isset( $_POST['auto_refund_on_cancel'] ),
			'currency'                          => isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : 'USD',
			'debug_log'                         => isset( $_POST['debug_log'] ),
		);

		// Save settings.
		$this->addon->save_all_settings( $settings );

		// Add success notice.
		add_settings_error(
			'bkx_square_settings',
			'settings_updated',
			__( 'Settings saved successfully.', 'bkx-square-payments' ),
			'success'
		);
	}
}
