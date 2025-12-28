<?php
/**
 * Settings Page
 *
 * @package BookingX\RegionalPayments\Admin
 * @since   1.0.0
 */

namespace BookingX\RegionalPayments\Admin;

/**
 * Admin settings page for Regional Payments.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Parent addon instance.
	 *
	 * @var \BookingX\RegionalPayments\RegionalPaymentsAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param \BookingX\RegionalPayments\RegionalPaymentsAddon $addon Parent addon.
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
			__( 'Regional Payments', 'bkx-regional-payments' ),
			__( 'Regional Payments', 'bkx-regional-payments' ),
			'manage_options',
			'bkx-regional-payments',
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
			'bkx_regional_payments_settings',
			'bkx_regional_payments_settings',
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
		if ( 'bookingx_page_bkx-regional-payments' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-regional-payments-admin',
			BKX_REGIONAL_PAYMENTS_URL . 'assets/css/admin.css',
			array(),
			BKX_REGIONAL_PAYMENTS_VERSION
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

		// Enabled gateways.
		$sanitized['enabled_gateways'] = array();
		if ( ! empty( $input['enabled_gateways'] ) && is_array( $input['enabled_gateways'] ) ) {
			foreach ( $input['enabled_gateways'] as $gateway ) {
				$sanitized['enabled_gateways'][] = sanitize_text_field( $gateway );
			}
		}

		$sanitized['auto_detect_country'] = isset( $input['auto_detect_country'] ) ? 1 : 0;
		$sanitized['fallback_gateway']    = sanitize_text_field( $input['fallback_gateway'] ?? '' );

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'gateways';
		$settings   = $this->addon->get_settings();
		?>
		<div class="wrap bkx-regional-payments-settings">
			<h1><?php esc_html_e( 'Regional Payment Hub', 'bkx-regional-payments' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-regional-payments&tab=gateways' ) ); ?>"
				   class="nav-tab <?php echo 'gateways' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Gateways', 'bkx-regional-payments' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-regional-payments&tab=settings' ) ); ?>"
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'bkx-regional-payments' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-regional-payments&tab=license' ) ); ?>"
				   class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'License', 'bkx-regional-payments' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'settings':
						$this->render_settings_tab( $settings );
						break;
					case 'license':
						$this->addon->render_license_page();
						break;
					default:
						$this->render_gateways_tab( $settings );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render gateways tab.
	 *
	 * @since 1.0.0
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_gateways_tab( array $settings ): void {
		$gateways         = $this->addon->get_gateway_registry()->get_all();
		$enabled_gateways = $settings['enabled_gateways'] ?? array();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'bkx_regional_payments_settings' ); ?>

			<!-- Hidden fields to preserve other settings -->
			<input type="hidden" name="bkx_regional_payments_settings[auto_detect_country]"
				   value="<?php echo esc_attr( $settings['auto_detect_country'] ?? 1 ); ?>">
			<input type="hidden" name="bkx_regional_payments_settings[fallback_gateway]"
				   value="<?php echo esc_attr( $settings['fallback_gateway'] ?? '' ); ?>">

			<h2><?php esc_html_e( 'Available Payment Gateways', 'bkx-regional-payments' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Enable the regional payment methods you want to offer. Gateways will be shown based on customer location.', 'bkx-regional-payments' ); ?>
			</p>

			<div class="bkx-gateway-grid">
				<?php foreach ( $gateways as $id => $gateway ) : ?>
					<div class="bkx-gateway-card <?php echo in_array( $id, $enabled_gateways, true ) ? 'enabled' : ''; ?>">
						<div class="bkx-gateway-header">
							<label>
								<input type="checkbox"
									   name="bkx_regional_payments_settings[enabled_gateways][]"
									   value="<?php echo esc_attr( $id ); ?>"
									   <?php checked( in_array( $id, $enabled_gateways, true ) ); ?>>
								<?php if ( ! empty( $gateway['icon'] ) ) : ?>
									<img src="<?php echo esc_url( $gateway['icon'] ); ?>"
										 alt="<?php echo esc_attr( $gateway['title'] ); ?>"
										 class="bkx-gateway-icon">
								<?php endif; ?>
								<span class="bkx-gateway-title"><?php echo esc_html( $gateway['title'] ); ?></span>
							</label>
						</div>

						<div class="bkx-gateway-body">
							<p class="bkx-gateway-description">
								<?php echo esc_html( $gateway['description'] ); ?>
							</p>

							<div class="bkx-gateway-meta">
								<?php if ( ! empty( $gateway['countries'] ) ) : ?>
									<span class="bkx-gateway-countries">
										<strong><?php esc_html_e( 'Countries:', 'bkx-regional-payments' ); ?></strong>
										<?php echo esc_html( implode( ', ', array_slice( $gateway['countries'], 0, 5 ) ) ); ?>
										<?php if ( count( $gateway['countries'] ) > 5 ) : ?>
											<span title="<?php echo esc_attr( implode( ', ', $gateway['countries'] ) ); ?>">
												+<?php echo count( $gateway['countries'] ) - 5; ?>
											</span>
										<?php endif; ?>
									</span>
								<?php endif; ?>

								<?php if ( ! empty( $gateway['currencies'] ) ) : ?>
									<span class="bkx-gateway-currencies">
										<strong><?php esc_html_e( 'Currencies:', 'bkx-regional-payments' ); ?></strong>
										<?php echo esc_html( implode( ', ', $gateway['currencies'] ) ); ?>
									</span>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php submit_button(); ?>
		</form>
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
			<?php settings_fields( 'bkx_regional_payments_settings' ); ?>

			<!-- Hidden field to preserve enabled gateways -->
			<?php foreach ( $settings['enabled_gateways'] ?? array() as $gateway ) : ?>
				<input type="hidden" name="bkx_regional_payments_settings[enabled_gateways][]"
					   value="<?php echo esc_attr( $gateway ); ?>">
			<?php endforeach; ?>

			<h2><?php esc_html_e( 'General Settings', 'bkx-regional-payments' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-Detect Country', 'bkx-regional-payments' ); ?></th>
					<td>
						<label>
							<input type="checkbox"
								   name="bkx_regional_payments_settings[auto_detect_country]"
								   value="1"
								   <?php checked( $settings['auto_detect_country'] ?? 1, 1 ); ?>>
							<?php esc_html_e( 'Automatically detect customer country and show relevant payment methods', 'bkx-regional-payments' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Uses GeoIP, CloudFlare headers, or WooCommerce geolocation to detect customer location.', 'bkx-regional-payments' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Fallback Gateway', 'bkx-regional-payments' ); ?></th>
					<td>
						<select name="bkx_regional_payments_settings[fallback_gateway]">
							<option value=""><?php esc_html_e( 'None', 'bkx-regional-payments' ); ?></option>
							<?php foreach ( $this->addon->get_gateway_registry()->get_all() as $id => $gateway ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>"
										<?php selected( $settings['fallback_gateway'] ?? '', $id ); ?>>
									<?php echo esc_html( $gateway['title'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Show this gateway if no regional gateway is available for the customer\'s location.', 'bkx-regional-payments' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Provider Settings', 'bkx-regional-payments' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Most regional gateways require Stripe or another payment provider. Ensure Stripe is configured with the BookingX Stripe Payments add-on.', 'bkx-regional-payments' ); ?>
			</p>

			<div class="bkx-info-box">
				<h4><?php esc_html_e( 'Stripe Integration', 'bkx-regional-payments' ); ?></h4>
				<p>
					<?php
					printf(
						/* translators: %s: Stripe settings link */
						esc_html__( 'Regional payment methods (iDEAL, SEPA, Bancontact, GiroPay, P24, Boleto) are processed through Stripe. %s', 'bkx-regional-payments' ),
						'<a href="' . esc_url( admin_url( 'admin.php?page=bkx-stripe-payments' ) ) . '">' . esc_html__( 'Configure Stripe', 'bkx-regional-payments' ) . '</a>'
					);
					?>
				</p>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}
}
