<?php
/**
 * Settings Page
 *
 * @package BookingX\RecurringBookings\Admin
 * @since   1.0.0
 */

namespace BookingX\RecurringBookings\Admin;

use BookingX\RecurringBookings\RecurringBookingsAddon;

/**
 * Admin settings page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var RecurringBookingsAddon
	 */
	protected RecurringBookingsAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param RecurringBookingsAddon $addon Addon instance.
	 */
	public function __construct( RecurringBookingsAddon $addon ) {
		$this->addon = $addon;

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		add_submenu_page(
			'bookingx',
			__( 'Recurring Bookings', 'bkx-recurring-bookings' ),
			__( 'Recurring Bookings', 'bkx-recurring-bookings' ),
			'manage_options',
			'bkx-recurring-settings',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting( 'bkx_recurring_settings', 'bkx_recurring_settings', array( $this, 'sanitize_settings' ) );

		// General section.
		add_settings_section(
			'bkx_recurring_general',
			__( 'General Settings', 'bkx-recurring-bookings' ),
			array( $this, 'render_general_section' ),
			'bkx-recurring-settings'
		);

		add_settings_field(
			'enable_recurring',
			__( 'Enable Recurring Bookings', 'bkx-recurring-bookings' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-recurring-settings',
			'bkx_recurring_general',
			array(
				'name'        => 'enable_recurring',
				'description' => __( 'Allow customers to create recurring bookings.', 'bkx-recurring-bookings' ),
			)
		);

		add_settings_field(
			'enable_custom_patterns',
			__( 'Custom Patterns', 'bkx-recurring-bookings' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-recurring-settings',
			'bkx_recurring_general',
			array(
				'name'        => 'enable_custom_patterns',
				'description' => __( 'Allow customers to create custom recurrence patterns.', 'bkx-recurring-bookings' ),
			)
		);

		add_settings_field(
			'disabled_patterns',
			__( 'Disabled Patterns', 'bkx-recurring-bookings' ),
			array( $this, 'render_patterns_field' ),
			'bkx-recurring-settings',
			'bkx_recurring_general'
		);

		// Limits section.
		add_settings_section(
			'bkx_recurring_limits',
			__( 'Limits', 'bkx-recurring-bookings' ),
			array( $this, 'render_limits_section' ),
			'bkx-recurring-settings'
		);

		add_settings_field(
			'max_occurrences',
			__( 'Maximum Occurrences', 'bkx-recurring-bookings' ),
			array( $this, 'render_number_field' ),
			'bkx-recurring-settings',
			'bkx_recurring_limits',
			array(
				'name'        => 'max_occurrences',
				'min'         => 2,
				'max'         => 365,
				'description' => __( 'Maximum number of occurrences in a series.', 'bkx-recurring-bookings' ),
			)
		);

		add_settings_field(
			'max_advance_days',
			__( 'Maximum Advance Days', 'bkx-recurring-bookings' ),
			array( $this, 'render_number_field' ),
			'bkx-recurring-settings',
			'bkx_recurring_limits',
			array(
				'name'        => 'max_advance_days',
				'min'         => 30,
				'max'         => 730,
				'description' => __( 'Maximum days in advance a series can be scheduled.', 'bkx-recurring-bookings' ),
			)
		);

		add_settings_field(
			'generate_ahead_days',
			__( 'Generate Ahead Days', 'bkx-recurring-bookings' ),
			array( $this, 'render_number_field' ),
			'bkx-recurring-settings',
			'bkx_recurring_limits',
			array(
				'name'        => 'generate_ahead_days',
				'min'         => 7,
				'max'         => 90,
				'description' => __( 'How many days ahead to pre-generate booking instances.', 'bkx-recurring-bookings' ),
			)
		);

		// Permissions section.
		add_settings_section(
			'bkx_recurring_permissions',
			__( 'Customer Permissions', 'bkx-recurring-bookings' ),
			array( $this, 'render_permissions_section' ),
			'bkx-recurring-settings'
		);

		add_settings_field(
			'allow_series_edit',
			__( 'Allow Series Edit', 'bkx-recurring-bookings' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-recurring-settings',
			'bkx_recurring_permissions',
			array(
				'name'        => 'allow_series_edit',
				'description' => __( 'Allow customers to edit their recurring series.', 'bkx-recurring-bookings' ),
			)
		);

		add_settings_field(
			'allow_instance_skip',
			__( 'Allow Skip Instances', 'bkx-recurring-bookings' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-recurring-settings',
			'bkx_recurring_permissions',
			array(
				'name'        => 'allow_instance_skip',
				'description' => __( 'Allow customers to skip individual instances.', 'bkx-recurring-bookings' ),
			)
		);

		add_settings_field(
			'allow_instance_reschedule',
			__( 'Allow Reschedule Instances', 'bkx-recurring-bookings' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-recurring-settings',
			'bkx_recurring_permissions',
			array(
				'name'        => 'allow_instance_reschedule',
				'description' => __( 'Allow customers to reschedule individual instances.', 'bkx-recurring-bookings' ),
			)
		);

		// Pricing section.
		add_settings_section(
			'bkx_recurring_pricing',
			__( 'Pricing', 'bkx-recurring-bookings' ),
			array( $this, 'render_pricing_section' ),
			'bkx-recurring-settings'
		);

		add_settings_field(
			'recurring_discount',
			__( 'Recurring Discount (%)', 'bkx-recurring-bookings' ),
			array( $this, 'render_number_field' ),
			'bkx-recurring-settings',
			'bkx_recurring_pricing',
			array(
				'name'        => 'recurring_discount',
				'min'         => 0,
				'max'         => 50,
				'step'        => 1,
				'description' => __( 'Percentage discount for recurring bookings.', 'bkx-recurring-bookings' ),
			)
		);

		add_settings_field(
			'require_full_payment',
			__( 'Require Full Payment', 'bkx-recurring-bookings' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-recurring-settings',
			'bkx_recurring_pricing',
			array(
				'name'        => 'require_full_payment',
				'description' => __( 'Require payment for all instances upfront (subscription-style).', 'bkx-recurring-bookings' ),
			)
		);

		// Data retention section.
		add_settings_section(
			'bkx_recurring_data',
			__( 'Data Management', 'bkx-recurring-bookings' ),
			null,
			'bkx-recurring-settings'
		);

		add_settings_field(
			'data_retention_days',
			__( 'Data Retention (Days)', 'bkx-recurring-bookings' ),
			array( $this, 'render_number_field' ),
			'bkx-recurring-settings',
			'bkx_recurring_data',
			array(
				'name'        => 'data_retention_days',
				'min'         => 30,
				'max'         => 1095,
				'description' => __( 'Days to retain completed/skipped instance data before cleanup.', 'bkx-recurring-bookings' ),
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
		$checkboxes = array(
			'enable_recurring',
			'enable_custom_patterns',
			'allow_series_edit',
			'allow_instance_skip',
			'allow_instance_reschedule',
			'require_full_payment',
		);

		foreach ( $checkboxes as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		// Numbers.
		$numbers = array(
			'max_occurrences'     => array( 'min' => 2, 'max' => 365, 'default' => 52 ),
			'max_advance_days'    => array( 'min' => 30, 'max' => 730, 'default' => 365 ),
			'generate_ahead_days' => array( 'min' => 7, 'max' => 90, 'default' => 30 ),
			'recurring_discount'  => array( 'min' => 0, 'max' => 50, 'default' => 0 ),
			'data_retention_days' => array( 'min' => 30, 'max' => 1095, 'default' => 365 ),
		);

		foreach ( $numbers as $key => $config ) {
			$value = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : $config['default'];
			$sanitized[ $key ] = max( $config['min'], min( $config['max'], $value ) );
		}

		// Disabled patterns.
		$sanitized['disabled_patterns'] = array();
		if ( ! empty( $input['disabled_patterns'] ) && is_array( $input['disabled_patterns'] ) ) {
			$valid_patterns = array( 'daily', 'weekly', 'biweekly', 'monthly', 'custom' );
			$sanitized['disabled_patterns'] = array_intersect( $input['disabled_patterns'], $valid_patterns );
		}

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
		<div class="wrap bkx-recurring-settings">
			<h1><?php esc_html_e( 'Recurring Bookings', 'bkx-recurring-bookings' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=bkx-recurring-settings&tab=settings"
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'bkx-recurring-bookings' ); ?>
				</a>
				<a href="?page=bkx-recurring-settings&tab=series"
				   class="nav-tab <?php echo 'series' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Active Series', 'bkx-recurring-bookings' ); ?>
				</a>
				<a href="?page=bkx-recurring-settings&tab=license"
				   class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'License', 'bkx-recurring-bookings' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'series':
						$this->render_series_tab();
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
			settings_fields( 'bkx_recurring_settings' );
			do_settings_sections( 'bkx-recurring-settings' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render series tab.
	 *
	 * @return void
	 */
	protected function render_series_tab(): void {
		$recurrence_service = $this->addon->get_recurrence_service();
		$instance_generator = $this->addon->get_instance_generator();

		$series_list = $recurrence_service->get_all_series( array( 'status' => 'active' ) );
		?>
		<div class="bkx-series-list">
			<h2><?php esc_html_e( 'Active Recurring Series', 'bkx-recurring-bookings' ); ?></h2>

			<?php if ( empty( $series_list ) ) : ?>
				<p><?php esc_html_e( 'No active recurring series found.', 'bkx-recurring-bookings' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'ID', 'bkx-recurring-bookings' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'bkx-recurring-bookings' ); ?></th>
							<th><?php esc_html_e( 'Service', 'bkx-recurring-bookings' ); ?></th>
							<th><?php esc_html_e( 'Pattern', 'bkx-recurring-bookings' ); ?></th>
							<th><?php esc_html_e( 'Progress', 'bkx-recurring-bookings' ); ?></th>
							<th><?php esc_html_e( 'Next Instance', 'bkx-recurring-bookings' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bkx-recurring-bookings' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $series_list as $series ) : ?>
							<?php
							$customer = get_userdata( $series['customer_id'] );
							$base     = get_post( $series['base_id'] );
							$stats    = $instance_generator->get_series_stats( $series['id'] );

							$next_instances = $instance_generator->get_instances(
								$series['id'],
								array(
									'status'    => 'scheduled',
									'from_date' => gmdate( 'Y-m-d' ),
									'limit'     => 1,
								)
							);
							$next = ! empty( $next_instances ) ? $next_instances[0] : null;
							?>
							<tr>
								<td><?php echo esc_html( $series['id'] ); ?></td>
								<td>
									<?php if ( $customer ) : ?>
										<?php echo esc_html( $customer->display_name ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Guest', 'bkx-recurring-bookings' ); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $base ) : ?>
										<a href="<?php echo esc_url( get_edit_post_link( $base->ID ) ); ?>">
											<?php echo esc_html( $base->post_title ); ?>
										</a>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<?php echo esc_html( $this->addon->get_pattern_label( $series['pattern'] ) ); ?>
								</td>
								<td>
									<?php
									printf(
										/* translators: 1: completed count, 2: total count */
										esc_html__( '%1$d / %2$d completed', 'bkx-recurring-bookings' ),
										$stats['completed'],
										$series['max_occurrences']
									);
									?>
								</td>
								<td>
									<?php if ( $next ) : ?>
										<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $next['scheduled_date'] ) ) ); ?>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
								<td>
									<button type="button" class="button button-small bkx-view-series"
											data-series-id="<?php echo esc_attr( $series['id'] ); ?>">
										<?php esc_html_e( 'View', 'bkx-recurring-bookings' ); ?>
									</button>
									<button type="button" class="button button-small bkx-cancel-series"
											data-series-id="<?php echo esc_attr( $series['id'] ); ?>">
										<?php esc_html_e( 'Cancel', 'bkx-recurring-bookings' ); ?>
									</button>
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
		include BKX_RECURRING_PATH . 'templates/admin/license.php';
	}

	/**
	 * Render general section.
	 *
	 * @return void
	 */
	public function render_general_section(): void {
		echo '<p>' . esc_html__( 'Configure general settings for recurring bookings.', 'bkx-recurring-bookings' ) . '</p>';
	}

	/**
	 * Render limits section.
	 *
	 * @return void
	 */
	public function render_limits_section(): void {
		echo '<p>' . esc_html__( 'Set limits for recurring booking series.', 'bkx-recurring-bookings' ) . '</p>';
	}

	/**
	 * Render permissions section.
	 *
	 * @return void
	 */
	public function render_permissions_section(): void {
		echo '<p>' . esc_html__( 'Control what customers can do with their recurring bookings.', 'bkx-recurring-bookings' ) . '</p>';
	}

	/**
	 * Render pricing section.
	 *
	 * @return void
	 */
	public function render_pricing_section(): void {
		echo '<p>' . esc_html__( 'Configure pricing options for recurring bookings.', 'bkx-recurring-bookings' ) . '</p>';
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
			<input type="checkbox" name="bkx_recurring_settings[<?php echo esc_attr( $args['name'] ); ?>]"
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
		$min   = $args['min'] ?? 0;
		$max   = $args['max'] ?? 999;
		$step  = $args['step'] ?? 1;
		?>
		<input type="number"
			   name="bkx_recurring_settings[<?php echo esc_attr( $args['name'] ); ?>]"
			   value="<?php echo esc_attr( $value ); ?>"
			   min="<?php echo esc_attr( $min ); ?>"
			   max="<?php echo esc_attr( $max ); ?>"
			   step="<?php echo esc_attr( $step ); ?>"
			   class="small-text">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render patterns field.
	 *
	 * @return void
	 */
	public function render_patterns_field(): void {
		$disabled = $this->addon->get_setting( 'disabled_patterns', array() );
		$patterns = array(
			'daily'    => __( 'Daily', 'bkx-recurring-bookings' ),
			'weekly'   => __( 'Weekly', 'bkx-recurring-bookings' ),
			'biweekly' => __( 'Biweekly', 'bkx-recurring-bookings' ),
			'monthly'  => __( 'Monthly', 'bkx-recurring-bookings' ),
		);
		?>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Disabled Patterns', 'bkx-recurring-bookings' ); ?></legend>
			<?php foreach ( $patterns as $key => $label ) : ?>
				<label style="display: block; margin-bottom: 5px;">
					<input type="checkbox"
						   name="bkx_recurring_settings[disabled_patterns][]"
						   value="<?php echo esc_attr( $key ); ?>"
						   <?php checked( in_array( $key, $disabled, true ) ); ?>>
					<?php echo esc_html( $label ); ?>
				</label>
			<?php endforeach; ?>
			<p class="description"><?php esc_html_e( 'Check patterns to disable them from customer selection.', 'bkx-recurring-bookings' ); ?></p>
		</fieldset>
		<?php
	}
}
