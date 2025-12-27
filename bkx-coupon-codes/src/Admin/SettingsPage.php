<?php
/**
 * Coupon Settings Page
 *
 * @package BookingX\CouponCodes\Admin
 * @since   1.0.0
 */

namespace BookingX\CouponCodes\Admin;

use BookingX\CouponCodes\CouponCodesAddon;

/**
 * Settings page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var CouponCodesAddon
	 */
	protected CouponCodesAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param CouponCodesAddon $addon Addon instance.
	 */
	public function __construct( CouponCodesAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Initialize settings page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings page to admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Coupon Settings', 'bkx-coupon-codes' ),
			__( 'Coupon Settings', 'bkx-coupon-codes' ),
			'manage_options',
			'bkx-coupon-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'bkx_coupon_settings',
			'bkx_coupon_codes_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// General Settings Section.
		add_settings_section(
			'bkx_coupon_general',
			__( 'General Settings', 'bkx-coupon-codes' ),
			array( $this, 'render_general_section' ),
			'bkx-coupon-settings'
		);

		$this->add_general_fields();

		// Discount Types Section.
		add_settings_section(
			'bkx_coupon_types',
			__( 'Discount Types', 'bkx-coupon-codes' ),
			array( $this, 'render_types_section' ),
			'bkx-coupon-settings'
		);

		$this->add_type_fields();

		// Restrictions Section.
		add_settings_section(
			'bkx_coupon_restrictions',
			__( 'Restrictions', 'bkx-coupon-codes' ),
			array( $this, 'render_restrictions_section' ),
			'bkx-coupon-settings'
		);

		$this->add_restriction_fields();

		// Display Section.
		add_settings_section(
			'bkx_coupon_display',
			__( 'Display Settings', 'bkx-coupon-codes' ),
			array( $this, 'render_display_section' ),
			'bkx-coupon-settings'
		);

		$this->add_display_fields();

		// Notifications Section.
		add_settings_section(
			'bkx_coupon_notifications',
			__( 'Notifications', 'bkx-coupon-codes' ),
			array( $this, 'render_notifications_section' ),
			'bkx-coupon-settings'
		);

		$this->add_notification_fields();
	}

	/**
	 * Add general settings fields.
	 *
	 * @return void
	 */
	protected function add_general_fields(): void {
		add_settings_field(
			'enable_coupons',
			__( 'Enable Coupons', 'bkx-coupon-codes' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_general',
			array(
				'id'          => 'enable_coupons',
				'description' => __( 'Allow customers to use coupon codes during booking.', 'bkx-coupon-codes' ),
			)
		);

		add_settings_field(
			'coupon_field_label',
			__( 'Field Label', 'bkx-coupon-codes' ),
			array( $this, 'render_text_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_general',
			array(
				'id'          => 'coupon_field_label',
				'description' => __( 'Label shown above the coupon input field.', 'bkx-coupon-codes' ),
			)
		);

		add_settings_field(
			'coupon_placeholder',
			__( 'Placeholder Text', 'bkx-coupon-codes' ),
			array( $this, 'render_text_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_general',
			array(
				'id'          => 'coupon_placeholder',
				'description' => __( 'Placeholder text for the coupon input field.', 'bkx-coupon-codes' ),
			)
		);

		add_settings_field(
			'apply_button_text',
			__( 'Apply Button Text', 'bkx-coupon-codes' ),
			array( $this, 'render_text_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_general',
			array(
				'id'          => 'apply_button_text',
				'description' => __( 'Text for the apply coupon button.', 'bkx-coupon-codes' ),
			)
		);
	}

	/**
	 * Add discount type fields.
	 *
	 * @return void
	 */
	protected function add_type_fields(): void {
		add_settings_field(
			'allow_percentage',
			__( 'Percentage Discounts', 'bkx-coupon-codes' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_types',
			array(
				'id'          => 'allow_percentage',
				'description' => __( 'Allow percentage-based discounts (e.g., 10% off).', 'bkx-coupon-codes' ),
			)
		);

		add_settings_field(
			'allow_fixed_amount',
			__( 'Fixed Amount Discounts', 'bkx-coupon-codes' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_types',
			array(
				'id'          => 'allow_fixed_amount',
				'description' => __( 'Allow fixed amount discounts (e.g., $10 off).', 'bkx-coupon-codes' ),
			)
		);

		add_settings_field(
			'allow_free_service',
			__( 'Free Service', 'bkx-coupon-codes' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_types',
			array(
				'id'          => 'allow_free_service',
				'description' => __( 'Allow coupons that make the service free.', 'bkx-coupon-codes' ),
			)
		);

		add_settings_field(
			'allow_free_extra',
			__( 'Free Add-on', 'bkx-coupon-codes' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_types',
			array(
				'id'          => 'allow_free_extra',
				'description' => __( 'Allow coupons that make add-ons/extras free.', 'bkx-coupon-codes' ),
			)
		);
	}

	/**
	 * Add restriction fields.
	 *
	 * @return void
	 */
	protected function add_restriction_fields(): void {
		add_settings_field(
			'allow_stacking',
			__( 'Allow Coupon Stacking', 'bkx-coupon-codes' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_restrictions',
			array(
				'id'          => 'allow_stacking',
				'description' => __( 'Allow multiple coupons to be used on a single booking.', 'bkx-coupon-codes' ),
			)
		);

		add_settings_field(
			'max_coupons_per_booking',
			__( 'Max Coupons per Booking', 'bkx-coupon-codes' ),
			array( $this, 'render_number_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_restrictions',
			array(
				'id'          => 'max_coupons_per_booking',
				'description' => __( 'Maximum number of coupons that can be applied to a single booking (if stacking enabled).', 'bkx-coupon-codes' ),
				'min'         => 1,
				'max'         => 10,
			)
		);

		add_settings_field(
			'require_login',
			__( 'Require Login', 'bkx-coupon-codes' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_restrictions',
			array(
				'id'          => 'require_login',
				'description' => __( 'Require customers to be logged in to use coupons.', 'bkx-coupon-codes' ),
			)
		);

		add_settings_field(
			'min_booking_amount',
			__( 'Default Minimum Amount', 'bkx-coupon-codes' ),
			array( $this, 'render_number_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_restrictions',
			array(
				'id'          => 'min_booking_amount',
				'description' => __( 'Default minimum booking amount required to use coupons.', 'bkx-coupon-codes' ),
				'min'         => 0,
				'step'        => '0.01',
			)
		);

		add_settings_field(
			'default_usage_limit',
			__( 'Default Usage Limit', 'bkx-coupon-codes' ),
			array( $this, 'render_number_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_restrictions',
			array(
				'id'          => 'default_usage_limit',
				'description' => __( 'Default maximum number of times a coupon can be used (0 = unlimited).', 'bkx-coupon-codes' ),
				'min'         => 0,
			)
		);

		add_settings_field(
			'default_per_user_limit',
			__( 'Default Per-User Limit', 'bkx-coupon-codes' ),
			array( $this, 'render_number_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_restrictions',
			array(
				'id'          => 'default_per_user_limit',
				'description' => __( 'Default maximum times a single user can use a coupon (0 = unlimited).', 'bkx-coupon-codes' ),
				'min'         => 0,
			)
		);
	}

	/**
	 * Add display fields.
	 *
	 * @return void
	 */
	protected function add_display_fields(): void {
		add_settings_field(
			'show_discount_breakdown',
			__( 'Show Discount Breakdown', 'bkx-coupon-codes' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_display',
			array(
				'id'          => 'show_discount_breakdown',
				'description' => __( 'Show the discount as a line item in the price breakdown.', 'bkx-coupon-codes' ),
			)
		);

		add_settings_field(
			'show_savings',
			__( 'Show Savings Message', 'bkx-coupon-codes' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_display',
			array(
				'id'          => 'show_savings',
				'description' => __( 'Display a message showing how much the customer is saving.', 'bkx-coupon-codes' ),
			)
		);

		add_settings_field(
			'savings_format',
			__( 'Savings Message Format', 'bkx-coupon-codes' ),
			array( $this, 'render_text_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_display',
			array(
				'id'          => 'savings_format',
				'description' => __( 'Use {amount} as placeholder for the discount amount.', 'bkx-coupon-codes' ),
			)
		);

		add_settings_field(
			'show_expiry_warning',
			__( 'Show Expiry Warning', 'bkx-coupon-codes' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_display',
			array(
				'id'          => 'show_expiry_warning',
				'description' => __( 'Warn customers when a coupon is about to expire.', 'bkx-coupon-codes' ),
			)
		);

		add_settings_field(
			'expiry_warning_days',
			__( 'Expiry Warning Days', 'bkx-coupon-codes' ),
			array( $this, 'render_number_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_display',
			array(
				'id'          => 'expiry_warning_days',
				'description' => __( 'Show warning when coupon expires within this many days.', 'bkx-coupon-codes' ),
				'min'         => 1,
				'max'         => 30,
			)
		);
	}

	/**
	 * Add notification fields.
	 *
	 * @return void
	 */
	protected function add_notification_fields(): void {
		add_settings_field(
			'notify_admin_on_use',
			__( 'Notify Admin on Use', 'bkx-coupon-codes' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_notifications',
			array(
				'id'          => 'notify_admin_on_use',
				'description' => __( 'Send email notification when a coupon is used.', 'bkx-coupon-codes' ),
			)
		);

		add_settings_field(
			'admin_notification_email',
			__( 'Admin Email', 'bkx-coupon-codes' ),
			array( $this, 'render_email_field' ),
			'bkx-coupon-settings',
			'bkx_coupon_notifications',
			array(
				'id'          => 'admin_notification_email',
				'description' => __( 'Email address for admin notifications.', 'bkx-coupon-codes' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check for saved message.
		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error(
				'bkx_coupon_messages',
				'bkx_coupon_message',
				__( 'Settings saved.', 'bkx-coupon-codes' ),
				'updated'
			);
		}

		settings_errors( 'bkx_coupon_messages' );

		?>
		<div class="wrap bkx-coupon-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'bkx_coupon_settings' );
				do_settings_sections( 'bkx-coupon-settings' );
				submit_button( __( 'Save Settings', 'bkx-coupon-codes' ) );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render general section description.
	 *
	 * @return void
	 */
	public function render_general_section(): void {
		echo '<p>' . esc_html__( 'Configure the general coupon settings.', 'bkx-coupon-codes' ) . '</p>';
	}

	/**
	 * Render types section description.
	 *
	 * @return void
	 */
	public function render_types_section(): void {
		echo '<p>' . esc_html__( 'Choose which discount types are available when creating coupons.', 'bkx-coupon-codes' ) . '</p>';
	}

	/**
	 * Render restrictions section description.
	 *
	 * @return void
	 */
	public function render_restrictions_section(): void {
		echo '<p>' . esc_html__( 'Set default restrictions for coupon usage.', 'bkx-coupon-codes' ) . '</p>';
	}

	/**
	 * Render display section description.
	 *
	 * @return void
	 */
	public function render_display_section(): void {
		echo '<p>' . esc_html__( 'Configure how coupon information is displayed to customers.', 'bkx-coupon-codes' ) . '</p>';
	}

	/**
	 * Render notifications section description.
	 *
	 * @return void
	 */
	public function render_notifications_section(): void {
		echo '<p>' . esc_html__( 'Configure coupon-related notifications.', 'bkx-coupon-codes' ) . '</p>';
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_checkbox_field( array $args ): void {
		$value = $this->addon->get_setting( $args['id'], false );
		?>
		<label>
			<input type="checkbox" name="bkx_coupon_codes_settings[<?php echo esc_attr( $args['id'] ); ?>]" value="1" <?php checked( $value, true ); ?>>
			<?php echo esc_html( $args['description'] ?? '' ); ?>
		</label>
		<?php
	}

	/**
	 * Render text field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_text_field( array $args ): void {
		$value = $this->addon->get_setting( $args['id'], '' );
		?>
		<input type="text" class="regular-text" name="bkx_coupon_codes_settings[<?php echo esc_attr( $args['id'] ); ?>]" value="<?php echo esc_attr( $value ); ?>">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render email field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_email_field( array $args ): void {
		$value = $this->addon->get_setting( $args['id'], '' );
		?>
		<input type="email" class="regular-text" name="bkx_coupon_codes_settings[<?php echo esc_attr( $args['id'] ); ?>]" value="<?php echo esc_attr( $value ); ?>">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render number field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_number_field( array $args ): void {
		$value = $this->addon->get_setting( $args['id'], 0 );
		$min   = $args['min'] ?? 0;
		$max   = $args['max'] ?? '';
		$step  = $args['step'] ?? 1;
		?>
		<input type="number" class="small-text" name="bkx_coupon_codes_settings[<?php echo esc_attr( $args['id'] ); ?>]" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $min ); ?>" <?php echo $max ? 'max="' . esc_attr( $max ) . '"' : ''; ?> step="<?php echo esc_attr( $step ); ?>">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Checkboxes.
		$checkboxes = array(
			'enable_coupons',
			'allow_percentage',
			'allow_fixed_amount',
			'allow_free_service',
			'allow_free_extra',
			'allow_stacking',
			'require_login',
			'show_discount_breakdown',
			'show_savings',
			'show_expiry_warning',
			'notify_admin_on_use',
		);

		foreach ( $checkboxes as $key ) {
			$sanitized[ $key ] = isset( $input[ $key ] );
		}

		// Text fields.
		$text_fields = array(
			'coupon_field_label',
			'coupon_placeholder',
			'apply_button_text',
			'savings_format',
		);

		foreach ( $text_fields as $key ) {
			$sanitized[ $key ] = sanitize_text_field( $input[ $key ] ?? '' );
		}

		// Number fields.
		$number_fields = array(
			'max_coupons_per_booking' => 1,
			'min_booking_amount'      => 0,
			'default_usage_limit'     => 0,
			'default_per_user_limit'  => 0,
			'expiry_warning_days'     => 3,
		);

		foreach ( $number_fields as $key => $default ) {
			$sanitized[ $key ] = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : $default;
		}

		// Email.
		$sanitized['admin_notification_email'] = sanitize_email( $input['admin_notification_email'] ?? '' );

		return $sanitized;
	}
}
