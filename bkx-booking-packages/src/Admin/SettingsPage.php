<?php
/**
 * Settings Page
 *
 * @package BookingX\BookingPackages\Admin
 * @since   1.0.0
 */

namespace BookingX\BookingPackages\Admin;

use BookingX\BookingPackages\BookingPackagesAddon;

/**
 * Admin settings page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var BookingPackagesAddon
	 */
	protected BookingPackagesAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param BookingPackagesAddon $addon Addon instance.
	 */
	public function __construct( BookingPackagesAddon $addon ) {
		$this->addon = $addon;

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_bkx_package', array( $this, 'save_package_meta' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_submenu_page(
			'bookingx',
			__( 'Packages Settings', 'bkx-booking-packages' ),
			__( 'Package Settings', 'bkx-booking-packages' ),
			'manage_options',
			'bkx-packages-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting( 'bkx_packages_settings', 'bkx_packages_settings', array( $this, 'sanitize_settings' ) );

		// General section.
		add_settings_section(
			'bkx_packages_general',
			__( 'General Settings', 'bkx-booking-packages' ),
			null,
			'bkx-packages-settings'
		);

		add_settings_field(
			'enable_packages',
			__( 'Enable Packages', 'bkx-booking-packages' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-packages-settings',
			'bkx_packages_general',
			array(
				'name'        => 'enable_packages',
				'description' => __( 'Enable the packages system.', 'bkx-booking-packages' ),
			)
		);

		add_settings_field(
			'show_on_booking',
			__( 'Show on Booking Form', 'bkx-booking-packages' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-packages-settings',
			'bkx_packages_general',
			array(
				'name'        => 'show_on_booking',
				'description' => __( 'Show package selection on booking form for logged-in users.', 'bkx-booking-packages' ),
			)
		);

		add_settings_field(
			'default_validity',
			__( 'Default Validity (Days)', 'bkx-booking-packages' ),
			array( $this, 'render_number_field' ),
			'bkx-packages-settings',
			'bkx_packages_general',
			array(
				'name'        => 'default_validity',
				'min'         => 0,
				'max'         => 1095,
				'description' => __( 'Default validity period for packages. Set to 0 for no expiry.', 'bkx-booking-packages' ),
			)
		);

		// Notifications section.
		add_settings_section(
			'bkx_packages_notifications',
			__( 'Notifications', 'bkx-booking-packages' ),
			null,
			'bkx-packages-settings'
		);

		add_settings_field(
			'notify_expiring',
			__( 'Expiry Notifications', 'bkx-booking-packages' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-packages-settings',
			'bkx_packages_notifications',
			array(
				'name'        => 'notify_expiring',
				'description' => __( 'Send notification when packages are about to expire.', 'bkx-booking-packages' ),
			)
		);

		add_settings_field(
			'expiry_notice_days',
			__( 'Expiry Notice Days', 'bkx-booking-packages' ),
			array( $this, 'render_number_field' ),
			'bkx-packages-settings',
			'bkx_packages_notifications',
			array(
				'name'        => 'expiry_notice_days',
				'min'         => 1,
				'max'         => 30,
				'description' => __( 'Days before expiry to send notification.', 'bkx-booking-packages' ),
			)
		);

		// Transfer section.
		add_settings_section(
			'bkx_packages_transfer',
			__( 'Transfer & Gifting', 'bkx-booking-packages' ),
			null,
			'bkx-packages-settings'
		);

		add_settings_field(
			'allow_gifting',
			__( 'Allow Gifting', 'bkx-booking-packages' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-packages-settings',
			'bkx_packages_transfer',
			array(
				'name'        => 'allow_gifting',
				'description' => __( 'Allow customers to purchase packages as gifts for others.', 'bkx-booking-packages' ),
			)
		);

		add_settings_field(
			'allow_transfer',
			__( 'Allow Transfer', 'bkx-booking-packages' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-packages-settings',
			'bkx_packages_transfer',
			array(
				'name'        => 'allow_transfer',
				'description' => __( 'Allow customers to transfer their packages to another user.', 'bkx-booking-packages' ),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input values.
	 * @return array Sanitized values.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Checkboxes.
		$checkboxes = array( 'enable_packages', 'show_on_booking', 'notify_expiring', 'allow_gifting', 'allow_transfer' );
		foreach ( $checkboxes as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		// Numbers.
		$sanitized['default_validity']   = isset( $input['default_validity'] ) ? absint( $input['default_validity'] ) : 365;
		$sanitized['expiry_notice_days'] = isset( $input['expiry_notice_days'] ) ? max( 1, min( 30, absint( $input['expiry_notice_days'] ) ) ) : 7;

		return $sanitized;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
		?>
		<div class="wrap bkx-packages-settings">
			<h1><?php esc_html_e( 'Booking Packages', 'bkx-booking-packages' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=bkx-packages-settings&tab=settings"
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'bkx-booking-packages' ); ?>
				</a>
				<a href="?page=bkx-packages-settings&tab=customers"
				   class="nav-tab <?php echo 'customers' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Customer Packages', 'bkx-booking-packages' ); ?>
				</a>
				<a href="?page=bkx-packages-settings&tab=license"
				   class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'License', 'bkx-booking-packages' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'customers':
						$this->render_customers_tab();
						break;

					case 'license':
						$this->render_license_tab();
						break;

					default:
						$this->render_settings_tab();
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
	 * @return void
	 */
	protected function render_settings_tab(): void {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'bkx_packages_settings' );
			do_settings_sections( 'bkx-packages-settings' );
			submit_button();
			?>
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

		$table = $wpdb->prefix . 'bkx_customer_packages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$packages = $wpdb->get_results(
			"SELECT cp.*, u.display_name, u.user_email
			FROM {$table} cp
			LEFT JOIN {$wpdb->users} u ON cp.customer_id = u.ID
			ORDER BY cp.created_at DESC
			LIMIT 50",
			ARRAY_A
		);
		?>
		<div class="bkx-customer-packages">
			<h2><?php esc_html_e( 'Customer Packages', 'bkx-booking-packages' ); ?></h2>

			<?php if ( empty( $packages ) ) : ?>
				<p><?php esc_html_e( 'No customer packages found.', 'bkx-booking-packages' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Customer', 'bkx-booking-packages' ); ?></th>
							<th><?php esc_html_e( 'Package', 'bkx-booking-packages' ); ?></th>
							<th><?php esc_html_e( 'Uses Remaining', 'bkx-booking-packages' ); ?></th>
							<th><?php esc_html_e( 'Expiry', 'bkx-booking-packages' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bkx-booking-packages' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $packages as $cp ) : ?>
							<?php $package_post = get_post( $cp['package_id'] ); ?>
							<tr>
								<td>
									<?php echo esc_html( $cp['display_name'] ?: $cp['user_email'] ); ?>
								</td>
								<td>
									<?php echo $package_post ? esc_html( $package_post->post_title ) : '—'; ?>
								</td>
								<td>
									<?php echo esc_html( $cp['uses_remaining'] . ' / ' . $cp['total_uses'] ); ?>
								</td>
								<td>
									<?php
									if ( $cp['expiry_date'] ) {
										echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $cp['expiry_date'] ) ) );
									} else {
										esc_html_e( 'Never', 'bkx-booking-packages' );
									}
									?>
								</td>
								<td>
									<span class="bkx-status-<?php echo esc_attr( $cp['status'] ); ?>">
										<?php echo esc_html( ucfirst( $cp['status'] ) ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render license tab.
	 *
	 * @return void
	 */
	protected function render_license_tab(): void {
		include BKX_PACKAGES_PATH . 'templates/admin/license.php';
	}

	/**
	 * Add meta boxes.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'bkx-package-details',
			__( 'Package Details', 'bkx-booking-packages' ),
			array( $this, 'render_details_meta_box' ),
			'bkx_package',
			'normal',
			'high'
		);

		add_meta_box(
			'bkx-package-services',
			__( 'Applicable Services', 'bkx-booking-packages' ),
			array( $this, 'render_services_meta_box' ),
			'bkx_package',
			'side',
			'default'
		);
	}

	/**
	 * Render details meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_details_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'bkx_package_meta', 'bkx_package_nonce' );

		$price         = get_post_meta( $post->ID, '_bkx_package_price', true );
		$regular_price = get_post_meta( $post->ID, '_bkx_package_regular_price', true );
		$uses          = get_post_meta( $post->ID, '_bkx_package_uses', true );
		$validity      = get_post_meta( $post->ID, '_bkx_package_validity_days', true );
		$active        = get_post_meta( $post->ID, '_bkx_package_active', true );
		?>
		<table class="form-table">
			<tr>
				<th><label for="bkx_package_price"><?php esc_html_e( 'Price', 'bkx-booking-packages' ); ?></label></th>
				<td>
					<input type="number" id="bkx_package_price" name="bkx_package_price"
						   value="<?php echo esc_attr( $price ); ?>" step="0.01" min="0" class="regular-text">
				</td>
			</tr>
			<tr>
				<th><label for="bkx_package_regular_price"><?php esc_html_e( 'Regular Price', 'bkx-booking-packages' ); ?></label></th>
				<td>
					<input type="number" id="bkx_package_regular_price" name="bkx_package_regular_price"
						   value="<?php echo esc_attr( $regular_price ); ?>" step="0.01" min="0" class="regular-text">
					<p class="description"><?php esc_html_e( 'Original price (for showing savings). Leave empty to hide.', 'bkx-booking-packages' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_package_uses"><?php esc_html_e( 'Number of Uses', 'bkx-booking-packages' ); ?></label></th>
				<td>
					<input type="number" id="bkx_package_uses" name="bkx_package_uses"
						   value="<?php echo esc_attr( $uses ); ?>" min="1" max="999" class="small-text">
					<p class="description"><?php esc_html_e( 'How many times this package can be used.', 'bkx-booking-packages' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_package_validity_days"><?php esc_html_e( 'Validity (Days)', 'bkx-booking-packages' ); ?></label></th>
				<td>
					<input type="number" id="bkx_package_validity_days" name="bkx_package_validity_days"
						   value="<?php echo esc_attr( $validity ); ?>" min="0" max="1095" class="small-text">
					<p class="description"><?php esc_html_e( 'Days until package expires. Set to 0 for no expiry.', 'bkx-booking-packages' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Status', 'bkx-booking-packages' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="bkx_package_active" value="1" <?php checked( $active ); ?>>
						<?php esc_html_e( 'Active (available for purchase)', 'bkx-booking-packages' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render services meta box.
	 *
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_services_meta_box( \WP_Post $post ): void {
		$all_services     = get_post_meta( $post->ID, '_bkx_package_all_services', true );
		$selected_services = get_post_meta( $post->ID, '_bkx_package_services', true ) ?: array();

		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);
		?>
		<p>
			<label>
				<input type="checkbox" name="bkx_package_all_services" value="1" <?php checked( $all_services ); ?>
					   id="bkx_package_all_services">
				<?php esc_html_e( 'All Services', 'bkx-booking-packages' ); ?>
			</label>
		</p>

		<div class="bkx-services-list" style="<?php echo $all_services ? 'display:none;' : ''; ?>">
			<?php if ( empty( $services ) ) : ?>
				<p><?php esc_html_e( 'No services found.', 'bkx-booking-packages' ); ?></p>
			<?php else : ?>
				<?php foreach ( $services as $service ) : ?>
					<label style="display: block; margin: 5px 0;">
						<input type="checkbox" name="bkx_package_services[]"
							   value="<?php echo esc_attr( $service->ID ); ?>"
							   <?php checked( in_array( $service->ID, (array) $selected_services, true ) ); ?>>
						<?php echo esc_html( $service->post_title ); ?>
					</label>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<script>
		jQuery(function($) {
			$('#bkx_package_all_services').on('change', function() {
				$('.bkx-services-list').toggle(!this.checked);
			});
		});
		</script>
		<?php
	}

	/**
	 * Save package meta.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_package_meta( int $post_id ): void {
		if ( ! isset( $_POST['bkx_package_nonce'] ) || ! wp_verify_nonce( $_POST['bkx_package_nonce'], 'bkx_package_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Price.
		if ( isset( $_POST['bkx_package_price'] ) ) {
			update_post_meta( $post_id, '_bkx_package_price', floatval( $_POST['bkx_package_price'] ) );
		}

		// Regular price.
		if ( isset( $_POST['bkx_package_regular_price'] ) ) {
			update_post_meta( $post_id, '_bkx_package_regular_price', floatval( $_POST['bkx_package_regular_price'] ) );
		}

		// Uses.
		if ( isset( $_POST['bkx_package_uses'] ) ) {
			update_post_meta( $post_id, '_bkx_package_uses', absint( $_POST['bkx_package_uses'] ) );
		}

		// Validity.
		if ( isset( $_POST['bkx_package_validity_days'] ) ) {
			update_post_meta( $post_id, '_bkx_package_validity_days', absint( $_POST['bkx_package_validity_days'] ) );
		}

		// Active.
		update_post_meta( $post_id, '_bkx_package_active', ! empty( $_POST['bkx_package_active'] ) ? '1' : '' );

		// All services.
		update_post_meta( $post_id, '_bkx_package_all_services', ! empty( $_POST['bkx_package_all_services'] ) ? '1' : '' );

		// Selected services.
		$services = isset( $_POST['bkx_package_services'] ) ? array_map( 'absint', (array) $_POST['bkx_package_services'] ) : array();
		update_post_meta( $post_id, '_bkx_package_services', $services );
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_checkbox_field( array $args ): void {
		$value = $this->addon->get_setting( $args['name'], false );
		?>
		<label>
			<input type="checkbox" name="bkx_packages_settings[<?php echo esc_attr( $args['name'] ); ?>]"
				   value="1" <?php checked( $value ); ?>>
			<?php echo esc_html( $args['description'] ?? '' ); ?>
		</label>
		<?php
	}

	/**
	 * Render number field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_number_field( array $args ): void {
		$value = $this->addon->get_setting( $args['name'], 0 );
		?>
		<input type="number"
			   name="bkx_packages_settings[<?php echo esc_attr( $args['name'] ); ?>]"
			   value="<?php echo esc_attr( $value ); ?>"
			   min="<?php echo esc_attr( $args['min'] ?? 0 ); ?>"
			   max="<?php echo esc_attr( $args['max'] ?? 9999 ); ?>"
			   class="small-text">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}
}
