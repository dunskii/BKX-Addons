<?php
/**
 * Settings Page
 *
 * Admin settings page for Authorize.net configuration.
 *
 * @package BookingX\AuthorizeNet\Admin
 * @since   1.0.0
 */

namespace BookingX\AuthorizeNet\Admin;

use BookingX\AuthorizeNet\AuthorizeNet;
use BookingX\AuthorizeNet\Controllers\WebhookController;

/**
 * Settings page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var AuthorizeNet
	 */
	protected AuthorizeNet $addon;

	/**
	 * Option group.
	 *
	 * @var string
	 */
	protected string $option_group = 'bkx_authorize_net';

	/**
	 * Constructor.
	 *
	 * @param AuthorizeNet $addon Addon instance.
	 */
	public function __construct( AuthorizeNet $addon ) {
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
		add_action( 'bkx_settings_tab_authorize_net', array( $this, 'render_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_bkx_authorize_net_save_settings', array( $this, 'save_settings' ) );
	}

	/**
	 * Add settings tab.
	 *
	 * @since 1.0.0
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( array $tabs ): array {
		$tabs['authorize_net'] = __( 'Authorize.net', 'bkx-authorize-net' );
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
			'bkx_authorize_net_settings',
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
			'enable_cim',
			'enable_arb',
			'require_cvv',
			'auto_refund_on_cancel',
			'debug_log',
		);

		foreach ( $boolean_fields as $field ) {
			$sanitized[ $field ] = ! empty( $input[ $field ] );
		}

		// Select fields with allowed values.
		$select_fields = array(
			'authnet_mode'       => array( 'sandbox', 'live' ),
			'integration_method' => array( 'accept_js', 'accept_hosted' ),
			'transaction_type'   => array( 'auth_capture', 'auth_only' ),
		);

		foreach ( $select_fields as $field => $allowed ) {
			$value = $input[ $field ] ?? '';
			$sanitized[ $field ] = in_array( $value, $allowed, true ) ? $value : $allowed[0];
		}

		// Text fields.
		$text_fields = array(
			'api_login_id',
			'transaction_key',
			'public_client_key',
			'signature_key',
		);

		foreach ( $text_fields as $field ) {
			$sanitized[ $field ] = sanitize_text_field( $input[ $field ] ?? '' );
		}

		// Card types array.
		$allowed_cards = array( 'visa', 'mastercard', 'amex', 'discover', 'jcb', 'diners' );
		$card_types = $input['accepted_card_types'] ?? array();
		if ( is_array( $card_types ) ) {
			$sanitized['accepted_card_types'] = array_values(
				array_intersect( array_map( 'sanitize_key', $card_types ), $allowed_cards )
			);
		} else {
			$sanitized['accepted_card_types'] = array( 'visa', 'mastercard', 'amex', 'discover' );
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
		if ( ! isset( $_POST['bkx_authorize_net_nonce'] ) ||
			 ! wp_verify_nonce( sanitize_key( $_POST['bkx_authorize_net_nonce'] ), 'bkx_authorize_net_save_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'bkx-authorize-net' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'bkx-authorize-net' ) );
		}

		// Get and sanitize posted data.
		$posted_data = array();
		if ( isset( $_POST['bkx_authorize_net'] ) && is_array( $_POST['bkx_authorize_net'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in sanitize_settings()
			$posted_data = wp_unslash( $_POST['bkx_authorize_net'] );
		}

		$sanitized = $this->sanitize_settings( $posted_data );

		// Save settings.
		$this->addon->save_settings( $sanitized );

		// Redirect back with success message.
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'bookingx-settings',
					'tab'     => 'authorize_net',
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
		<div class="bkx-authorize-net-settings">
			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved successfully.', 'bkx-authorize-net' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bkx_authorize_net_save_settings">
				<?php wp_nonce_field( 'bkx_authorize_net_save_settings', 'bkx_authorize_net_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<!-- Enable/Disable -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Enable/Disable', 'bkx-authorize-net' ); ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="bkx_authorize_net[enabled]" value="1"
										<?php checked( $settings['enabled'] ?? false ); ?>>
									<?php esc_html_e( 'Enable Authorize.net', 'bkx-authorize-net' ); ?>
								</label>
							</td>
						</tr>

						<!-- Mode -->
						<tr>
							<th scope="row">
								<label for="authnet_mode"><?php esc_html_e( 'Mode', 'bkx-authorize-net' ); ?></label>
							</th>
							<td>
								<select name="bkx_authorize_net[authnet_mode]" id="authnet_mode">
									<option value="sandbox" <?php selected( $settings['authnet_mode'] ?? 'sandbox', 'sandbox' ); ?>>
										<?php esc_html_e( 'Sandbox (Testing)', 'bkx-authorize-net' ); ?>
									</option>
									<option value="live" <?php selected( $settings['authnet_mode'] ?? 'sandbox', 'live' ); ?>>
										<?php esc_html_e( 'Live (Production)', 'bkx-authorize-net' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Use Sandbox mode for testing. Switch to Live for production.', 'bkx-authorize-net' ); ?>
								</p>
							</td>
						</tr>

						<!-- API Login ID -->
						<tr>
							<th scope="row">
								<label for="api_login_id"><?php esc_html_e( 'API Login ID', 'bkx-authorize-net' ); ?></label>
							</th>
							<td>
								<input type="text" name="bkx_authorize_net[api_login_id]" id="api_login_id"
									value="<?php echo esc_attr( $settings['api_login_id'] ?? '' ); ?>"
									class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Find this in your Authorize.net account under Account > Security Settings > API Credentials & Keys.', 'bkx-authorize-net' ); ?>
								</p>
							</td>
						</tr>

						<!-- Transaction Key -->
						<tr>
							<th scope="row">
								<label for="transaction_key"><?php esc_html_e( 'Transaction Key', 'bkx-authorize-net' ); ?></label>
							</th>
							<td>
								<input type="password" name="bkx_authorize_net[transaction_key]" id="transaction_key"
									value="<?php echo esc_attr( $settings['transaction_key'] ?? '' ); ?>"
									class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Your Transaction Key for API authentication.', 'bkx-authorize-net' ); ?>
								</p>
							</td>
						</tr>

						<!-- Public Client Key -->
						<tr>
							<th scope="row">
								<label for="public_client_key"><?php esc_html_e( 'Public Client Key', 'bkx-authorize-net' ); ?></label>
							</th>
							<td>
								<input type="text" name="bkx_authorize_net[public_client_key]" id="public_client_key"
									value="<?php echo esc_attr( $settings['public_client_key'] ?? '' ); ?>"
									class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Required for Accept.js. Generate this in your Authorize.net account.', 'bkx-authorize-net' ); ?>
								</p>
							</td>
						</tr>

						<!-- Signature Key -->
						<tr>
							<th scope="row">
								<label for="signature_key"><?php esc_html_e( 'Signature Key', 'bkx-authorize-net' ); ?></label>
							</th>
							<td>
								<input type="password" name="bkx_authorize_net[signature_key]" id="signature_key"
									value="<?php echo esc_attr( $settings['signature_key'] ?? '' ); ?>"
									class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Required for webhook signature verification.', 'bkx-authorize-net' ); ?>
								</p>
							</td>
						</tr>

						<!-- Webhook URL -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Webhook URL', 'bkx-authorize-net' ); ?>
							</th>
							<td>
								<code><?php echo esc_html( $webhook_url ); ?></code>
								<button type="button" class="button button-secondary bkx-copy-webhook-url"
									data-clipboard-text="<?php echo esc_attr( $webhook_url ); ?>">
									<?php esc_html_e( 'Copy', 'bkx-authorize-net' ); ?>
								</button>
								<p class="description">
									<?php esc_html_e( 'Add this URL to your Authorize.net webhooks configuration.', 'bkx-authorize-net' ); ?>
								</p>
							</td>
						</tr>

						<!-- Transaction Type -->
						<tr>
							<th scope="row">
								<label for="transaction_type"><?php esc_html_e( 'Transaction Type', 'bkx-authorize-net' ); ?></label>
							</th>
							<td>
								<select name="bkx_authorize_net[transaction_type]" id="transaction_type">
									<option value="auth_capture" <?php selected( $settings['transaction_type'] ?? 'auth_capture', 'auth_capture' ); ?>>
										<?php esc_html_e( 'Authorize and Capture', 'bkx-authorize-net' ); ?>
									</option>
									<option value="auth_only" <?php selected( $settings['transaction_type'] ?? 'auth_capture', 'auth_only' ); ?>>
										<?php esc_html_e( 'Authorize Only', 'bkx-authorize-net' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Choose whether to capture payment immediately or authorize only.', 'bkx-authorize-net' ); ?>
								</p>
							</td>
						</tr>

						<!-- Require CVV -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Require CVV', 'bkx-authorize-net' ); ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="bkx_authorize_net[require_cvv]" value="1"
										<?php checked( $settings['require_cvv'] ?? true ); ?>>
									<?php esc_html_e( 'Require CVV for card payments', 'bkx-authorize-net' ); ?>
								</label>
							</td>
						</tr>

						<!-- Customer Profiles (CIM) -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Customer Profiles', 'bkx-authorize-net' ); ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="bkx_authorize_net[enable_cim]" value="1"
										<?php checked( $settings['enable_cim'] ?? true ); ?>>
									<?php esc_html_e( 'Enable Customer Information Manager (CIM)', 'bkx-authorize-net' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Save customer payment methods for faster checkout.', 'bkx-authorize-net' ); ?>
								</p>
							</td>
						</tr>

						<!-- Auto Refund -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Auto Refund', 'bkx-authorize-net' ); ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="bkx_authorize_net[auto_refund_on_cancel]" value="1"
										<?php checked( $settings['auto_refund_on_cancel'] ?? true ); ?>>
									<?php esc_html_e( 'Automatically refund when booking is cancelled', 'bkx-authorize-net' ); ?>
								</label>
							</td>
						</tr>

						<!-- Accepted Card Types -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Accepted Cards', 'bkx-authorize-net' ); ?>
							</th>
							<td>
								<?php
								$card_types = array(
									'visa'       => __( 'Visa', 'bkx-authorize-net' ),
									'mastercard' => __( 'Mastercard', 'bkx-authorize-net' ),
									'amex'       => __( 'American Express', 'bkx-authorize-net' ),
									'discover'   => __( 'Discover', 'bkx-authorize-net' ),
									'jcb'        => __( 'JCB', 'bkx-authorize-net' ),
									'diners'     => __( 'Diners Club', 'bkx-authorize-net' ),
								);
								$accepted = $settings['accepted_card_types'] ?? array( 'visa', 'mastercard', 'amex', 'discover' );
								foreach ( $card_types as $value => $label ) :
									?>
									<label style="display: block; margin-bottom: 5px;">
										<input type="checkbox" name="bkx_authorize_net[accepted_card_types][]"
											value="<?php echo esc_attr( $value ); ?>"
											<?php checked( in_array( $value, $accepted, true ) ); ?>>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</td>
						</tr>

						<!-- Debug Log -->
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Debug Log', 'bkx-authorize-net' ); ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="bkx_authorize_net[debug_log]" value="1"
										<?php checked( $settings['debug_log'] ?? false ); ?>>
									<?php esc_html_e( 'Enable debug logging', 'bkx-authorize-net' ); ?>
								</label>
								<p class="description">
									<?php
									printf(
										/* translators: %s: log file path */
										esc_html__( 'Logs are saved to %s', 'bkx-authorize-net' ),
										'<code>wp-content/bkx-authorize-net-debug.log</code>'
									);
									?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Save Settings', 'bkx-authorize-net' ) ); ?>
			</form>
		</div>
		<?php
	}
}
