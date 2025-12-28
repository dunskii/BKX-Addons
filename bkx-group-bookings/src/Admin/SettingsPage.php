<?php
/**
 * Settings Page
 *
 * @package BookingX\GroupBookings\Admin
 * @since   1.0.0
 */

namespace BookingX\GroupBookings\Admin;

/**
 * Admin settings page for Group Bookings.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Parent addon instance.
	 *
	 * @var \BookingX\GroupBookings\GroupBookingsAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param \BookingX\GroupBookings\GroupBookingsAddon $addon Parent addon.
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
			__( 'Group Bookings', 'bkx-group-bookings' ),
			__( 'Group Bookings', 'bkx-group-bookings' ),
			'manage_options',
			'bkx-group-bookings',
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
			'bkx_group_bookings_settings',
			'bkx_group_bookings_settings',
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

		$sanitized['enable_quantity']       = isset( $input['enable_quantity'] ) ? 1 : 0;
		$sanitized['default_min_quantity']  = isset( $input['default_min_quantity'] ) ? absint( $input['default_min_quantity'] ) : 1;
		$sanitized['default_max_quantity']  = isset( $input['default_max_quantity'] ) ? absint( $input['default_max_quantity'] ) : 10;
		$sanitized['pricing_mode']          = isset( $input['pricing_mode'] ) ? sanitize_text_field( $input['pricing_mode'] ) : 'per_person';
		$sanitized['show_quantity_label']   = isset( $input['show_quantity_label'] ) ? 1 : 0;
		$sanitized['quantity_label']        = isset( $input['quantity_label'] ) ? sanitize_text_field( $input['quantity_label'] ) : '';
		$sanitized['group_discount_enable'] = isset( $input['group_discount_enable'] ) ? 1 : 0;
		$sanitized['group_discount_min']    = isset( $input['group_discount_min'] ) ? absint( $input['group_discount_min'] ) : 5;
		$sanitized['group_discount_type']   = isset( $input['group_discount_type'] ) ? sanitize_text_field( $input['group_discount_type'] ) : 'percentage';
		$sanitized['group_discount_value']  = isset( $input['group_discount_value'] ) ? floatval( $input['group_discount_value'] ) : 0;

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
		<div class="wrap bkx-group-bookings-settings">
			<h1><?php esc_html_e( 'Group Bookings Settings', 'bkx-group-bookings' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-group-bookings&tab=settings' ) ); ?>"
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'bkx-group-bookings' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-group-bookings&tab=pricing' ) ); ?>"
				   class="nav-tab <?php echo 'pricing' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Pricing', 'bkx-group-bookings' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-group-bookings&tab=license' ) ); ?>"
				   class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'License', 'bkx-group-bookings' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'pricing':
						$this->render_pricing_tab( $settings );
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
			<?php settings_fields( 'bkx_group_bookings_settings' ); ?>

			<h2><?php esc_html_e( 'Quantity Settings', 'bkx-group-bookings' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Quantity Selection', 'bkx-group-bookings' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_group_bookings_settings[enable_quantity]" value="1"
								   <?php checked( $settings['enable_quantity'] ?? 1, 1 ); ?>>
							<?php esc_html_e( 'Allow customers to select number of people', 'bkx-group-bookings' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default Minimum', 'bkx-group-bookings' ); ?></th>
					<td>
						<input type="number" name="bkx_group_bookings_settings[default_min_quantity]"
							   value="<?php echo esc_attr( $settings['default_min_quantity'] ?? 1 ); ?>"
							   min="1" max="100" class="small-text">
						<p class="description"><?php esc_html_e( 'Default minimum number of people per booking.', 'bkx-group-bookings' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Default Maximum', 'bkx-group-bookings' ); ?></th>
					<td>
						<input type="number" name="bkx_group_bookings_settings[default_max_quantity]"
							   value="<?php echo esc_attr( $settings['default_max_quantity'] ?? 10 ); ?>"
							   min="1" max="500" class="small-text">
						<p class="description"><?php esc_html_e( 'Default maximum number of people per booking.', 'bkx-group-bookings' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Display Options', 'bkx-group-bookings' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Show Label', 'bkx-group-bookings' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_group_bookings_settings[show_quantity_label]" value="1"
								   <?php checked( $settings['show_quantity_label'] ?? 1, 1 ); ?>>
							<?php esc_html_e( 'Display a label above the quantity field', 'bkx-group-bookings' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Quantity Label', 'bkx-group-bookings' ); ?></th>
					<td>
						<input type="text" name="bkx_group_bookings_settings[quantity_label]"
							   value="<?php echo esc_attr( $settings['quantity_label'] ?? __( 'Number of People', 'bkx-group-bookings' ) ); ?>"
							   class="regular-text">
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render pricing tab.
	 *
	 * @since 1.0.0
	 * @param array $settings Current settings.
	 * @return void
	 */
	private function render_pricing_tab( array $settings ): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'bkx_group_bookings_settings' ); ?>

			<h2><?php esc_html_e( 'Pricing Mode', 'bkx-group-bookings' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Default Pricing Mode', 'bkx-group-bookings' ); ?></th>
					<td>
						<select name="bkx_group_bookings_settings[pricing_mode]">
							<option value="per_person" <?php selected( $settings['pricing_mode'] ?? 'per_person', 'per_person' ); ?>>
								<?php esc_html_e( 'Per Person', 'bkx-group-bookings' ); ?>
							</option>
							<option value="flat_rate" <?php selected( $settings['pricing_mode'] ?? 'per_person', 'flat_rate' ); ?>>
								<?php esc_html_e( 'Flat Rate (fixed price regardless of quantity)', 'bkx-group-bookings' ); ?>
							</option>
							<option value="tiered" <?php selected( $settings['pricing_mode'] ?? 'per_person', 'tiered' ); ?>>
								<?php esc_html_e( 'Tiered Pricing', 'bkx-group-bookings' ); ?>
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'This can be overridden per service.', 'bkx-group-bookings' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Group Discount', 'bkx-group-bookings' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Group Discount', 'bkx-group-bookings' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_group_bookings_settings[group_discount_enable]" value="1"
								   <?php checked( $settings['group_discount_enable'] ?? 0, 1 ); ?>>
							<?php esc_html_e( 'Apply automatic discount for large groups', 'bkx-group-bookings' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Minimum for Discount', 'bkx-group-bookings' ); ?></th>
					<td>
						<input type="number" name="bkx_group_bookings_settings[group_discount_min]"
							   value="<?php echo esc_attr( $settings['group_discount_min'] ?? 5 ); ?>"
							   min="2" max="100" class="small-text">
						<?php esc_html_e( 'people', 'bkx-group-bookings' ); ?>
						<p class="description"><?php esc_html_e( 'Minimum group size to qualify for discount.', 'bkx-group-bookings' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Discount Type', 'bkx-group-bookings' ); ?></th>
					<td>
						<select name="bkx_group_bookings_settings[group_discount_type]">
							<option value="percentage" <?php selected( $settings['group_discount_type'] ?? 'percentage', 'percentage' ); ?>>
								<?php esc_html_e( 'Percentage', 'bkx-group-bookings' ); ?>
							</option>
							<option value="fixed" <?php selected( $settings['group_discount_type'] ?? 'percentage', 'fixed' ); ?>>
								<?php esc_html_e( 'Fixed Amount', 'bkx-group-bookings' ); ?>
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Discount Value', 'bkx-group-bookings' ); ?></th>
					<td>
						<input type="number" name="bkx_group_bookings_settings[group_discount_value]"
							   value="<?php echo esc_attr( $settings['group_discount_value'] ?? 10 ); ?>"
							   min="0" step="0.01" class="small-text">
						<span class="bkx-discount-suffix">
							<?php echo 'percentage' === ( $settings['group_discount_type'] ?? 'percentage' ) ? '%' : '$'; ?>
						</span>
					</td>
				</tr>
			</table>

			<p class="description">
				<strong><?php esc_html_e( 'Note:', 'bkx-group-bookings' ); ?></strong>
				<?php esc_html_e( 'For tiered pricing, configure tiers individually on each service.', 'bkx-group-bookings' ); ?>
			</p>

			<?php submit_button(); ?>
		</form>
		<?php
	}
}
