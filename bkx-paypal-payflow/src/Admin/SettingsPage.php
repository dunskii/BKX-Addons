<?php
/**
 * Settings Page
 *
 * @package BookingX\PayPalPayflow\Admin
 * @since   1.0.0
 */

namespace BookingX\PayPalPayflow\Admin;

/**
 * Admin settings page for PayPal Payflow.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Parent addon instance.
	 *
	 * @var \BookingX\PayPalPayflow\PayflowAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param \BookingX\PayPalPayflow\PayflowAddon $addon Parent addon.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
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
			__( 'PayPal Payflow', 'bkx-paypal-payflow' ),
			__( 'PayPal Payflow', 'bkx-paypal-payflow' ),
			'manage_options',
			'bkx-paypal-payflow',
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
			'bkx_paypal_payflow_settings',
			'bkx_paypal_payflow_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
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

		$sanitized['enabled']          = isset( $input['enabled'] ) ? 1 : 0;
		$sanitized['sandbox']          = isset( $input['sandbox'] ) ? 1 : 0;
		$sanitized['title']            = sanitize_text_field( $input['title'] ?? '' );
		$sanitized['description']      = sanitize_textarea_field( $input['description'] ?? '' );
		$sanitized['partner']          = sanitize_text_field( $input['partner'] ?? '' );
		$sanitized['vendor']           = sanitize_text_field( $input['vendor'] ?? '' );
		$sanitized['user']             = sanitize_text_field( $input['user'] ?? '' );
		$sanitized['password']         = $input['password'] ?? '';
		$sanitized['sandbox_partner']  = sanitize_text_field( $input['sandbox_partner'] ?? '' );
		$sanitized['sandbox_vendor']   = sanitize_text_field( $input['sandbox_vendor'] ?? '' );
		$sanitized['sandbox_user']     = sanitize_text_field( $input['sandbox_user'] ?? '' );
		$sanitized['sandbox_password'] = $input['sandbox_password'] ?? '';
		$sanitized['transaction_type'] = in_array( $input['transaction_type'] ?? '', array( 'S', 'A' ), true ) ? $input['transaction_type'] : 'S';
		$sanitized['verbosity']        = sanitize_text_field( $input['verbosity'] ?? 'MEDIUM' );
		$sanitized['fraud_protection'] = isset( $input['fraud_protection'] ) ? 1 : 0;

		$valid_cards = array( 'visa', 'mastercard', 'amex', 'discover', 'jcb', 'diners' );
		$sanitized['card_types'] = array();
		if ( ! empty( $input['card_types'] ) && is_array( $input['card_types'] ) ) {
			foreach ( $input['card_types'] as $card ) {
				if ( in_array( $card, $valid_cards, true ) ) {
					$sanitized['card_types'][] = $card;
				}
			}
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
		<div class="wrap bkx-paypal-payflow-settings">
			<h1><?php esc_html_e( 'PayPal Payflow Pro Settings', 'bkx-paypal-payflow' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-paypal-payflow&tab=settings' ) ); ?>"
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'bkx-paypal-payflow' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-paypal-payflow&tab=license' ) ); ?>"
				   class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'License', 'bkx-paypal-payflow' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				if ( 'license' === $active_tab ) {
					$this->addon->render_license_page();
				} else {
					$this->render_settings_tab( $settings );
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
			<?php settings_fields( 'bkx_paypal_payflow_settings' ); ?>

			<h2><?php esc_html_e( 'General Settings', 'bkx-paypal-payflow' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Gateway', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_paypal_payflow_settings[enabled]" value="1"
								   <?php checked( $settings['enabled'] ?? 0, 1 ); ?>>
							<?php esc_html_e( 'Enable PayPal Payflow Pro payments', 'bkx-paypal-payflow' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sandbox Mode', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_paypal_payflow_settings[sandbox]" value="1"
								   <?php checked( $settings['sandbox'] ?? 1, 1 ); ?>>
							<?php esc_html_e( 'Enable sandbox/test mode', 'bkx-paypal-payflow' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Use sandbox credentials for testing.', 'bkx-paypal-payflow' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Title', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<input type="text" name="bkx_paypal_payflow_settings[title]"
							   value="<?php echo esc_attr( $settings['title'] ?? '' ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Payment method title shown to customers.', 'bkx-paypal-payflow' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Description', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<textarea name="bkx_paypal_payflow_settings[description]" rows="3" class="large-text"><?php echo esc_textarea( $settings['description'] ?? '' ); ?></textarea>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Live Credentials', 'bkx-paypal-payflow' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Partner', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<input type="text" name="bkx_paypal_payflow_settings[partner]"
							   value="<?php echo esc_attr( $settings['partner'] ?? '' ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Usually "PayPal" or your reseller name.', 'bkx-paypal-payflow' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Vendor', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<input type="text" name="bkx_paypal_payflow_settings[vendor]"
							   value="<?php echo esc_attr( $settings['vendor'] ?? '' ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Your merchant login ID.', 'bkx-paypal-payflow' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'User', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<input type="text" name="bkx_paypal_payflow_settings[user]"
							   value="<?php echo esc_attr( $settings['user'] ?? '' ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'API user (same as vendor if not using user-based security).', 'bkx-paypal-payflow' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Password', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<input type="password" name="bkx_paypal_payflow_settings[password]"
							   value="<?php echo esc_attr( $settings['password'] ?? '' ); ?>" class="regular-text">
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Sandbox Credentials', 'bkx-paypal-payflow' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Sandbox Partner', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<input type="text" name="bkx_paypal_payflow_settings[sandbox_partner]"
							   value="<?php echo esc_attr( $settings['sandbox_partner'] ?? '' ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sandbox Vendor', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<input type="text" name="bkx_paypal_payflow_settings[sandbox_vendor]"
							   value="<?php echo esc_attr( $settings['sandbox_vendor'] ?? '' ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sandbox User', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<input type="text" name="bkx_paypal_payflow_settings[sandbox_user]"
							   value="<?php echo esc_attr( $settings['sandbox_user'] ?? '' ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sandbox Password', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<input type="password" name="bkx_paypal_payflow_settings[sandbox_password]"
							   value="<?php echo esc_attr( $settings['sandbox_password'] ?? '' ); ?>" class="regular-text">
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Transaction Settings', 'bkx-paypal-payflow' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Transaction Type', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<select name="bkx_paypal_payflow_settings[transaction_type]">
							<option value="S" <?php selected( $settings['transaction_type'] ?? 'S', 'S' ); ?>>
								<?php esc_html_e( 'Sale (Capture immediately)', 'bkx-paypal-payflow' ); ?>
							</option>
							<option value="A" <?php selected( $settings['transaction_type'] ?? 'S', 'A' ); ?>>
								<?php esc_html_e( 'Authorization (Capture later)', 'bkx-paypal-payflow' ); ?>
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Accepted Cards', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<?php
						$cards = array(
							'visa'       => 'Visa',
							'mastercard' => 'Mastercard',
							'amex'       => 'American Express',
							'discover'   => 'Discover',
							'jcb'        => 'JCB',
							'diners'     => 'Diners Club',
						);
						$selected_cards = $settings['card_types'] ?? array( 'visa', 'mastercard', 'amex', 'discover' );
						foreach ( $cards as $value => $label ) :
							?>
							<label style="display: inline-block; margin-right: 15px;">
								<input type="checkbox" name="bkx_paypal_payflow_settings[card_types][]"
									   value="<?php echo esc_attr( $value ); ?>"
									   <?php checked( in_array( $value, $selected_cards, true ) ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Fraud Protection', 'bkx-paypal-payflow' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_paypal_payflow_settings[fraud_protection]" value="1"
								   <?php checked( $settings['fraud_protection'] ?? 1, 1 ); ?>>
							<?php esc_html_e( 'Enable PayPal fraud protection services', 'bkx-paypal-payflow' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}
}
