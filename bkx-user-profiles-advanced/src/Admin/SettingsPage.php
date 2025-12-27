<?php
/**
 * User Profiles Settings Page
 *
 * @package BookingX\UserProfilesAdvanced\Admin
 * @since   1.0.0
 */

namespace BookingX\UserProfilesAdvanced\Admin;

use BookingX\UserProfilesAdvanced\UserProfilesAdvancedAddon;

/**
 * Settings page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var UserProfilesAdvancedAddon
	 */
	protected UserProfilesAdvancedAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param UserProfilesAdvancedAddon $addon Addon instance.
	 */
	public function __construct( UserProfilesAdvancedAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Register the settings page.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add menu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'bkx-settings',
			__( 'User Profiles', 'bkx-user-profiles-advanced' ),
			__( 'User Profiles', 'bkx-user-profiles-advanced' ),
			'manage_options',
			'bkx-user-profiles',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'bkx_user_profiles_settings',
			'bkx_user_profiles_advanced_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Profile settings.
		$sanitized['enable_profiles']           = ! empty( $input['enable_profiles'] );
		$sanitized['allow_profile_edit']        = ! empty( $input['allow_profile_edit'] );
		$sanitized['require_login_for_booking'] = ! empty( $input['require_login_for_booking'] );
		$sanitized['auto_create_account']       = ! empty( $input['auto_create_account'] );
		$sanitized['profile_page_id']           = absint( $input['profile_page_id'] ?? 0 );

		// Booking history.
		$sanitized['show_booking_history']      = ! empty( $input['show_booking_history'] );
		$sanitized['bookings_per_page']         = absint( $input['bookings_per_page'] ?? 10 );
		$sanitized['allow_rebooking']           = ! empty( $input['allow_rebooking'] );
		$sanitized['allow_cancellation']        = ! empty( $input['allow_cancellation'] );
		$sanitized['cancellation_period']       = absint( $input['cancellation_period'] ?? 24 );

		// Favorites.
		$sanitized['enable_favorites']          = ! empty( $input['enable_favorites'] );
		$sanitized['max_favorites']             = absint( $input['max_favorites'] ?? 50 );

		// Loyalty points.
		$sanitized['enable_loyalty']            = ! empty( $input['enable_loyalty'] );
		$sanitized['points_per_booking']        = absint( $input['points_per_booking'] ?? 10 );
		$sanitized['points_per_currency']       = floatval( $input['points_per_currency'] ?? 1 );
		$sanitized['points_for_referral']       = absint( $input['points_for_referral'] ?? 50 );
		$sanitized['points_redemption_rate']    = absint( $input['points_redemption_rate'] ?? 100 );
		$sanitized['points_redemption_value']   = floatval( $input['points_redemption_value'] ?? 1 );
		$sanitized['min_points_redeem']         = absint( $input['min_points_redeem'] ?? 100 );

		// Notifications.
		$sanitized['email_booking_reminder']    = ! empty( $input['email_booking_reminder'] );
		$sanitized['email_points_earned']       = ! empty( $input['email_points_earned'] );
		$sanitized['email_points_redeemed']     = ! empty( $input['email_points_redeemed'] );

		// Preferences.
		$sanitized['enable_preferences']        = ! empty( $input['enable_preferences'] );

		return $sanitized;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
		$settings   = get_option( 'bkx_user_profiles_advanced_settings', array() );
		?>
		<div class="wrap bkx-user-profiles-settings">
			<h1><?php esc_html_e( 'User Profiles Advanced', 'bkx-user-profiles-advanced' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=bkx-user-profiles&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General', 'bkx-user-profiles-advanced' ); ?>
				</a>
				<a href="?page=bkx-user-profiles&tab=loyalty" class="nav-tab <?php echo 'loyalty' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Loyalty Program', 'bkx-user-profiles-advanced' ); ?>
				</a>
				<a href="?page=bkx-user-profiles&tab=customers" class="nav-tab <?php echo 'customers' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Customers', 'bkx-user-profiles-advanced' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'loyalty':
						$this->render_loyalty_tab( $settings );
						break;
					case 'customers':
						$this->render_customers_tab();
						break;
					default:
						$this->render_general_tab( $settings );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render general tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	protected function render_general_tab( array $settings ): void {
		$pages = get_pages();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'bkx_user_profiles_settings' ); ?>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Profile Settings', 'bkx-user-profiles-advanced' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Profiles', 'bkx-user-profiles-advanced' ); ?></th>
						<td><label><input type="checkbox" name="bkx_user_profiles_advanced_settings[enable_profiles]" value="1" <?php checked( $settings['enable_profiles'] ?? true ); ?> /> <?php esc_html_e( 'Enable customer profile functionality', 'bkx-user-profiles-advanced' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Allow Profile Edit', 'bkx-user-profiles-advanced' ); ?></th>
						<td><label><input type="checkbox" name="bkx_user_profiles_advanced_settings[allow_profile_edit]" value="1" <?php checked( $settings['allow_profile_edit'] ?? true ); ?> /> <?php esc_html_e( 'Allow customers to edit their profiles', 'bkx-user-profiles-advanced' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Require Login', 'bkx-user-profiles-advanced' ); ?></th>
						<td><label><input type="checkbox" name="bkx_user_profiles_advanced_settings[require_login_for_booking]" value="1" <?php checked( $settings['require_login_for_booking'] ?? false ); ?> /> <?php esc_html_e( 'Require customers to log in before booking', 'bkx-user-profiles-advanced' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto Create Account', 'bkx-user-profiles-advanced' ); ?></th>
						<td><label><input type="checkbox" name="bkx_user_profiles_advanced_settings[auto_create_account]" value="1" <?php checked( $settings['auto_create_account'] ?? true ); ?> /> <?php esc_html_e( 'Automatically create account for new customers', 'bkx-user-profiles-advanced' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Profile Page', 'bkx-user-profiles-advanced' ); ?></th>
						<td>
							<select name="bkx_user_profiles_advanced_settings[profile_page_id]">
								<option value="0"><?php esc_html_e( '— Select —', 'bkx-user-profiles-advanced' ); ?></option>
								<?php foreach ( $pages as $page ) : ?>
									<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $settings['profile_page_id'] ?? 0, $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Page where [bkx_customer_profile] shortcode is placed.', 'bkx-user-profiles-advanced' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Booking History', 'bkx-user-profiles-advanced' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Show History', 'bkx-user-profiles-advanced' ); ?></th>
						<td><label><input type="checkbox" name="bkx_user_profiles_advanced_settings[show_booking_history]" value="1" <?php checked( $settings['show_booking_history'] ?? true ); ?> /> <?php esc_html_e( 'Show booking history in profile', 'bkx-user-profiles-advanced' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Bookings Per Page', 'bkx-user-profiles-advanced' ); ?></th>
						<td><input type="number" name="bkx_user_profiles_advanced_settings[bookings_per_page]" value="<?php echo esc_attr( $settings['bookings_per_page'] ?? 10 ); ?>" class="small-text" min="5" max="50" /></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Allow Rebooking', 'bkx-user-profiles-advanced' ); ?></th>
						<td><label><input type="checkbox" name="bkx_user_profiles_advanced_settings[allow_rebooking]" value="1" <?php checked( $settings['allow_rebooking'] ?? true ); ?> /> <?php esc_html_e( 'Allow customers to rebook past services', 'bkx-user-profiles-advanced' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Allow Cancellation', 'bkx-user-profiles-advanced' ); ?></th>
						<td><label><input type="checkbox" name="bkx_user_profiles_advanced_settings[allow_cancellation]" value="1" <?php checked( $settings['allow_cancellation'] ?? true ); ?> /> <?php esc_html_e( 'Allow customers to cancel bookings', 'bkx-user-profiles-advanced' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cancellation Period', 'bkx-user-profiles-advanced' ); ?></th>
						<td>
							<input type="number" name="bkx_user_profiles_advanced_settings[cancellation_period]" value="<?php echo esc_attr( $settings['cancellation_period'] ?? 24 ); ?>" class="small-text" min="0" /> <?php esc_html_e( 'hours before appointment', 'bkx-user-profiles-advanced' ); ?>
						</td>
					</tr>
				</table>
			</div>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Favorites', 'bkx-user-profiles-advanced' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Favorites', 'bkx-user-profiles-advanced' ); ?></th>
						<td><label><input type="checkbox" name="bkx_user_profiles_advanced_settings[enable_favorites]" value="1" <?php checked( $settings['enable_favorites'] ?? true ); ?> /> <?php esc_html_e( 'Allow customers to save favorite services', 'bkx-user-profiles-advanced' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Max Favorites', 'bkx-user-profiles-advanced' ); ?></th>
						<td><input type="number" name="bkx_user_profiles_advanced_settings[max_favorites]" value="<?php echo esc_attr( $settings['max_favorites'] ?? 50 ); ?>" class="small-text" min="10" max="100" /></td>
					</tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render loyalty tab.
	 *
	 * @param array $settings Current settings.
	 * @return void
	 */
	protected function render_loyalty_tab( array $settings ): void {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'bkx_user_profiles_settings' ); ?>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Loyalty Program', 'bkx-user-profiles-advanced' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Loyalty', 'bkx-user-profiles-advanced' ); ?></th>
						<td><label><input type="checkbox" name="bkx_user_profiles_advanced_settings[enable_loyalty]" value="1" <?php checked( $settings['enable_loyalty'] ?? true ); ?> /> <?php esc_html_e( 'Enable loyalty points program', 'bkx-user-profiles-advanced' ); ?></label></td>
					</tr>
				</table>
			</div>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Earning Points', 'bkx-user-profiles-advanced' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Points Per Booking', 'bkx-user-profiles-advanced' ); ?></th>
						<td>
							<input type="number" name="bkx_user_profiles_advanced_settings[points_per_booking]" value="<?php echo esc_attr( $settings['points_per_booking'] ?? 10 ); ?>" class="small-text" min="0" />
							<p class="description"><?php esc_html_e( 'Base points earned for each completed booking.', 'bkx-user-profiles-advanced' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Points Per Currency', 'bkx-user-profiles-advanced' ); ?></th>
						<td>
							<input type="number" name="bkx_user_profiles_advanced_settings[points_per_currency]" value="<?php echo esc_attr( $settings['points_per_currency'] ?? 1 ); ?>" class="small-text" min="0" step="0.1" />
							<p class="description"><?php esc_html_e( 'Additional points per currency unit spent (e.g., 1 = 1 point per $1).', 'bkx-user-profiles-advanced' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Referral Bonus', 'bkx-user-profiles-advanced' ); ?></th>
						<td>
							<input type="number" name="bkx_user_profiles_advanced_settings[points_for_referral]" value="<?php echo esc_attr( $settings['points_for_referral'] ?? 50 ); ?>" class="small-text" min="0" />
							<p class="description"><?php esc_html_e( 'Points earned when a referred friend makes their first booking.', 'bkx-user-profiles-advanced' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Redeeming Points', 'bkx-user-profiles-advanced' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Redemption Rate', 'bkx-user-profiles-advanced' ); ?></th>
						<td>
							<input type="number" name="bkx_user_profiles_advanced_settings[points_redemption_rate]" value="<?php echo esc_attr( $settings['points_redemption_rate'] ?? 100 ); ?>" class="small-text" min="1" />
							<?php esc_html_e( 'points =', 'bkx-user-profiles-advanced' ); ?>
							<input type="number" name="bkx_user_profiles_advanced_settings[points_redemption_value]" value="<?php echo esc_attr( $settings['points_redemption_value'] ?? 1 ); ?>" class="small-text" min="0.01" step="0.01" />
							<?php esc_html_e( 'currency', 'bkx-user-profiles-advanced' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Minimum to Redeem', 'bkx-user-profiles-advanced' ); ?></th>
						<td>
							<input type="number" name="bkx_user_profiles_advanced_settings[min_points_redeem]" value="<?php echo esc_attr( $settings['min_points_redeem'] ?? 100 ); ?>" class="small-text" min="0" />
							<p class="description"><?php esc_html_e( 'Minimum points required for redemption.', 'bkx-user-profiles-advanced' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="bkx-card">
				<h2><?php esc_html_e( 'Notifications', 'bkx-user-profiles-advanced' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Points Earned', 'bkx-user-profiles-advanced' ); ?></th>
						<td><label><input type="checkbox" name="bkx_user_profiles_advanced_settings[email_points_earned]" value="1" <?php checked( $settings['email_points_earned'] ?? true ); ?> /> <?php esc_html_e( 'Notify customers when they earn points', 'bkx-user-profiles-advanced' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Points Redeemed', 'bkx-user-profiles-advanced' ); ?></th>
						<td><label><input type="checkbox" name="bkx_user_profiles_advanced_settings[email_points_redeemed]" value="1" <?php checked( $settings['email_points_redeemed'] ?? true ); ?> /> <?php esc_html_e( 'Notify customers when they redeem points', 'bkx-user-profiles-advanced' ); ?></label></td>
					</tr>
				</table>
			</div>

			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render customers tab.
	 *
	 * @return void
	 */
	protected function render_customers_tab(): void {
		global $wpdb;

		$profiles_table = $wpdb->prefix . 'bkx_customer_profiles';
		$balance_table  = $wpdb->prefix . 'bkx_loyalty_balance';

		// Get top customers.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$top_customers = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT p.*, b.available_points, u.display_name, u.user_email
				FROM %i p
				LEFT JOIN %i b ON p.user_id = b.user_id
				LEFT JOIN %i u ON p.user_id = u.ID
				ORDER BY p.total_spent DESC
				LIMIT 20',
				$profiles_table,
				$balance_table,
				$wpdb->users
			)
		);
		?>
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Top Customers', 'bkx-user-profiles-advanced' ); ?></h2>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Customer', 'bkx-user-profiles-advanced' ); ?></th>
						<th><?php esc_html_e( 'Email', 'bkx-user-profiles-advanced' ); ?></th>
						<th><?php esc_html_e( 'Bookings', 'bkx-user-profiles-advanced' ); ?></th>
						<th><?php esc_html_e( 'Spent', 'bkx-user-profiles-advanced' ); ?></th>
						<th><?php esc_html_e( 'Points', 'bkx-user-profiles-advanced' ); ?></th>
						<th><?php esc_html_e( 'Member Since', 'bkx-user-profiles-advanced' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $top_customers ) ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'No customers yet.', 'bkx-user-profiles-advanced' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $top_customers as $customer ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( get_edit_user_link( $customer->user_id ) ); ?>">
										<?php echo esc_html( $customer->display_name ); ?>
									</a>
								</td>
								<td><?php echo esc_html( $customer->user_email ); ?></td>
								<td><?php echo esc_html( $customer->total_bookings ); ?></td>
								<td><?php echo esc_html( number_format( $customer->total_spent, 2 ) ); ?></td>
								<td><?php echo esc_html( $customer->available_points ?? 0 ); ?></td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $customer->created_at ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="bkx-card">
			<h2><?php esc_html_e( 'Shortcodes', 'bkx-user-profiles-advanced' ); ?></h2>
			<table class="widefat">
				<tr>
					<td><code>[bkx_customer_profile]</code></td>
					<td><?php esc_html_e( 'Display customer profile with all sections', 'bkx-user-profiles-advanced' ); ?></td>
				</tr>
				<tr>
					<td><code>[bkx_booking_history]</code></td>
					<td><?php esc_html_e( 'Display booking history only', 'bkx-user-profiles-advanced' ); ?></td>
				</tr>
				<tr>
					<td><code>[bkx_favorites]</code></td>
					<td><?php esc_html_e( 'Display favorites list', 'bkx-user-profiles-advanced' ); ?></td>
				</tr>
				<tr>
					<td><code>[bkx_loyalty_points]</code></td>
					<td><?php esc_html_e( 'Display loyalty points and redemption', 'bkx-user-profiles-advanced' ); ?></td>
				</tr>
			</table>
		</div>
		<?php
	}
}
