<?php
/**
 * Settings Page
 *
 * @package BookingX\GooglePay\Admin
 * @since   1.0.0
 */

namespace BookingX\GooglePay\Admin;

/**
 * Admin settings page for Google Pay.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Parent addon instance.
	 *
	 * @var \BookingX\GooglePay\GooglePayAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param \BookingX\GooglePay\GooglePayAddon $addon Parent addon.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add menu page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'bookingx',
			__( 'Google Pay', 'bkx-google-pay' ),
			__( 'Google Pay', 'bkx-google-pay' ),
			'manage_options',
			'bkx-google-pay',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'bkx_google_pay_settings',
			'bkx_google_pay_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook ): void {
		if ( 'bookingx_page_bkx-google-pay' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-google-pay-admin',
			BKX_GOOGLE_PAY_URL . 'assets/css/admin.css',
			array(),
			BKX_GOOGLE_PAY_VERSION
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @since 1.0.0
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		$sanitized['enabled']             = isset( $input['enabled'] ) ? 1 : 0;
		$sanitized['environment']         = in_array( $input['environment'] ?? '', array( 'TEST', 'PRODUCTION' ), true ) ? $input['environment'] : 'TEST';
		$sanitized['merchant_id']         = sanitize_text_field( $input['merchant_id'] ?? '' );
		$sanitized['merchant_name']       = sanitize_text_field( $input['merchant_name'] ?? '' );
		$sanitized['gateway']             = sanitize_text_field( $input['gateway'] ?? 'stripe' );
		$sanitized['gateway_merchant_id'] = sanitize_text_field( $input['gateway_merchant_id'] ?? '' );
		$sanitized['button_color']        = in_array( $input['button_color'] ?? '', array( 'default', 'black', 'white' ), true ) ? $input['button_color'] : 'black';
		$sanitized['button_type']         = in_array( $input['button_type'] ?? '', array( 'book', 'buy', 'checkout', 'donate', 'order', 'pay', 'plain', 'subscribe' ), true ) ? $input['button_type'] : 'pay';
		$sanitized['button_locale']       = sanitize_text_field( $input['button_locale'] ?? 'en' );

		$valid_cards = array( 'AMEX', 'DISCOVER', 'INTERAC', 'JCB', 'MASTERCARD', 'VISA' );
		$sanitized['allowed_cards'] = array();
		if ( ! empty( $input['allowed_cards'] ) && is_array( $input['allowed_cards'] ) ) {
			foreach ( $input['allowed_cards'] as $card ) {
				if ( in_array( $card, $valid_cards, true ) ) {
					$sanitized['allowed_cards'][] = $card;
				}
			}
		}

		if ( empty( $sanitized['allowed_cards'] ) ) {
			$sanitized['allowed_cards'] = array( 'AMEX', 'MASTERCARD', 'VISA', 'DISCOVER' );
		}

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
		$settings   = $this->addon->get_settings();
		?>
		<div class="wrap bkx-google-pay-settings">
			<h1><?php esc_html_e( 'Google Pay Settings', 'bkx-google-pay' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-google-pay&tab=settings' ) ); ?>"
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'bkx-google-pay' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-google-pay&tab=appearance' ) ); ?>"
				   class="nav-tab <?php echo 'appearance' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Appearance', 'bkx-google-pay' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-google-pay&tab=license' ) ); ?>"
				   class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'License', 'bkx-google-pay' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'appearance':
						$this->render_appearance_tab( $settings );
						break;
					case 'license':
						$this->addon->render_license_page();
						break;
					default:
						$this->render_settings_tab( $settings );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings tab.
	 *
	 * @since 1.0.0
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_settings_tab( array $settings ): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'bkx_google_pay_settings' ); ?>

			<h2><?php esc_html_e( 'General Settings', 'bkx-google-pay' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Google Pay', 'bkx-google-pay' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_google_pay_settings[enabled]" value="1"
								   <?php checked( $settings['enabled'] ?? 0, 1 ); ?>>
							<?php esc_html_e( 'Enable Google Pay payments', 'bkx-google-pay' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Environment', 'bkx-google-pay' ); ?></th>
					<td>
						<select name="bkx_google_pay_settings[environment]">
							<option value="TEST" <?php selected( $settings['environment'] ?? 'TEST', 'TEST' ); ?>>
								<?php esc_html_e( 'Test (Sandbox)', 'bkx-google-pay' ); ?>
							</option>
							<option value="PRODUCTION" <?php selected( $settings['environment'] ?? 'TEST', 'PRODUCTION' ); ?>>
								<?php esc_html_e( 'Production (Live)', 'bkx-google-pay' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Use Test mode for development. Switch to Production when ready to accept real payments.', 'bkx-google-pay' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Merchant Information', 'bkx-google-pay' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Merchant ID', 'bkx-google-pay' ); ?></th>
					<td>
						<input type="text" name="bkx_google_pay_settings[merchant_id]"
							   value="<?php echo esc_attr( $settings['merchant_id'] ?? '' ); ?>" class="regular-text">
						<p class="description">
							<?php
							printf(
								/* translators: %s: Google Pay Business Console link */
								esc_html__( 'Your Google Pay Merchant ID from the %s.', 'bkx-google-pay' ),
								'<a href="https://pay.google.com/business/console" target="_blank">Google Pay Business Console</a>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Merchant Name', 'bkx-google-pay' ); ?></th>
					<td>
						<input type="text" name="bkx_google_pay_settings[merchant_name]"
							   value="<?php echo esc_attr( $settings['merchant_name'] ?? get_bloginfo( 'name' ) ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Business name displayed during checkout.', 'bkx-google-pay' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Payment Gateway', 'bkx-google-pay' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Google Pay requires a payment gateway to process payments. Select your preferred gateway below.', 'bkx-google-pay' ); ?>
			</p>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Gateway', 'bkx-google-pay' ); ?></th>
					<td>
						<select name="bkx_google_pay_settings[gateway]">
							<option value="stripe" <?php selected( $settings['gateway'] ?? 'stripe', 'stripe' ); ?>>
								Stripe
							</option>
							<option value="braintree" <?php selected( $settings['gateway'] ?? 'stripe', 'braintree' ); ?>>
								Braintree
							</option>
							<option value="square" <?php selected( $settings['gateway'] ?? 'stripe', 'square' ); ?>>
								Square
							</option>
							<option value="adyen" <?php selected( $settings['gateway'] ?? 'stripe', 'adyen' ); ?>>
								Adyen
							</option>
							<option value="cybersource" <?php selected( $settings['gateway'] ?? 'stripe', 'cybersource' ); ?>>
								CyberSource
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Ensure the corresponding BookingX payment add-on is installed and configured.', 'bkx-google-pay' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Gateway Merchant ID', 'bkx-google-pay' ); ?></th>
					<td>
						<input type="text" name="bkx_google_pay_settings[gateway_merchant_id]"
							   value="<?php echo esc_attr( $settings['gateway_merchant_id'] ?? '' ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Your merchant ID with the selected payment gateway.', 'bkx-google-pay' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Accepted Cards', 'bkx-google-pay' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Card Networks', 'bkx-google-pay' ); ?></th>
					<td>
						<?php
						$cards = array(
							'AMEX'       => 'American Express',
							'DISCOVER'   => 'Discover',
							'INTERAC'    => 'Interac',
							'JCB'        => 'JCB',
							'MASTERCARD' => 'Mastercard',
							'VISA'       => 'Visa',
						);
						$selected_cards = $settings['allowed_cards'] ?? array( 'AMEX', 'MASTERCARD', 'VISA', 'DISCOVER' );
						foreach ( $cards as $value => $label ) :
							?>
							<label style="display: inline-block; margin-right: 15px;">
								<input type="checkbox" name="bkx_google_pay_settings[allowed_cards][]"
									   value="<?php echo esc_attr( $value ); ?>"
									   <?php checked( in_array( $value, $selected_cards, true ) ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render appearance tab.
	 *
	 * @since 1.0.0
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_appearance_tab( array $settings ): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'bkx_google_pay_settings' ); ?>

			<!-- Hidden fields to preserve other settings -->
			<input type="hidden" name="bkx_google_pay_settings[enabled]" value="<?php echo esc_attr( $settings['enabled'] ?? 0 ); ?>">
			<input type="hidden" name="bkx_google_pay_settings[environment]" value="<?php echo esc_attr( $settings['environment'] ?? 'TEST' ); ?>">
			<input type="hidden" name="bkx_google_pay_settings[merchant_id]" value="<?php echo esc_attr( $settings['merchant_id'] ?? '' ); ?>">
			<input type="hidden" name="bkx_google_pay_settings[merchant_name]" value="<?php echo esc_attr( $settings['merchant_name'] ?? '' ); ?>">
			<input type="hidden" name="bkx_google_pay_settings[gateway]" value="<?php echo esc_attr( $settings['gateway'] ?? 'stripe' ); ?>">
			<input type="hidden" name="bkx_google_pay_settings[gateway_merchant_id]" value="<?php echo esc_attr( $settings['gateway_merchant_id'] ?? '' ); ?>">
			<?php
			$selected_cards = $settings['allowed_cards'] ?? array( 'AMEX', 'MASTERCARD', 'VISA', 'DISCOVER' );
			foreach ( $selected_cards as $card ) :
				?>
				<input type="hidden" name="bkx_google_pay_settings[allowed_cards][]" value="<?php echo esc_attr( $card ); ?>">
			<?php endforeach; ?>

			<h2><?php esc_html_e( 'Button Appearance', 'bkx-google-pay' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Button Color', 'bkx-google-pay' ); ?></th>
					<td>
						<select name="bkx_google_pay_settings[button_color]">
							<option value="default" <?php selected( $settings['button_color'] ?? 'black', 'default' ); ?>>
								<?php esc_html_e( 'Default', 'bkx-google-pay' ); ?>
							</option>
							<option value="black" <?php selected( $settings['button_color'] ?? 'black', 'black' ); ?>>
								<?php esc_html_e( 'Black', 'bkx-google-pay' ); ?>
							</option>
							<option value="white" <?php selected( $settings['button_color'] ?? 'black', 'white' ); ?>>
								<?php esc_html_e( 'White', 'bkx-google-pay' ); ?>
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Button Type', 'bkx-google-pay' ); ?></th>
					<td>
						<select name="bkx_google_pay_settings[button_type]">
							<option value="pay" <?php selected( $settings['button_type'] ?? 'pay', 'pay' ); ?>>
								<?php esc_html_e( 'Pay with Google Pay', 'bkx-google-pay' ); ?>
							</option>
							<option value="book" <?php selected( $settings['button_type'] ?? 'pay', 'book' ); ?>>
								<?php esc_html_e( 'Book with Google Pay', 'bkx-google-pay' ); ?>
							</option>
							<option value="buy" <?php selected( $settings['button_type'] ?? 'pay', 'buy' ); ?>>
								<?php esc_html_e( 'Buy with Google Pay', 'bkx-google-pay' ); ?>
							</option>
							<option value="checkout" <?php selected( $settings['button_type'] ?? 'pay', 'checkout' ); ?>>
								<?php esc_html_e( 'Checkout with Google Pay', 'bkx-google-pay' ); ?>
							</option>
							<option value="order" <?php selected( $settings['button_type'] ?? 'pay', 'order' ); ?>>
								<?php esc_html_e( 'Order with Google Pay', 'bkx-google-pay' ); ?>
							</option>
							<option value="plain" <?php selected( $settings['button_type'] ?? 'pay', 'plain' ); ?>>
								<?php esc_html_e( 'Plain (Google Pay logo only)', 'bkx-google-pay' ); ?>
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Button Locale', 'bkx-google-pay' ); ?></th>
					<td>
						<input type="text" name="bkx_google_pay_settings[button_locale]"
							   value="<?php echo esc_attr( $settings['button_locale'] ?? 'en' ); ?>"
							   class="small-text" maxlength="5">
						<p class="description"><?php esc_html_e( 'Language code (e.g., en, de, fr, es).', 'bkx-google-pay' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Preview', 'bkx-google-pay' ); ?></h2>
			<div class="bkx-button-preview">
				<p class="description"><?php esc_html_e( 'Button preview will appear on the booking page.', 'bkx-google-pay' ); ?></p>
				<div class="bkx-preview-container" style="padding: 20px; background: #f5f5f5; border-radius: 4px; display: inline-block;">
					<img src="<?php echo esc_url( BKX_GOOGLE_PAY_URL . 'assets/images/google-pay-button-preview.png' ); ?>"
						 alt="<?php esc_attr_e( 'Google Pay Button Preview', 'bkx-google-pay' ); ?>"
						 style="max-width: 200px; height: auto;">
				</div>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}
}
