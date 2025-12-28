<?php
/**
 * Settings Page
 *
 * Admin settings page for Fitness & Sports addon.
 *
 * @package BookingX\FitnessSports\Admin
 * @since   1.0.0
 */

namespace BookingX\FitnessSports\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SettingsPage
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Option name.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'bkx_fitness_sports_settings';

	/**
	 * Initialize settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_submenu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add submenu page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_submenu_page(): void {
		add_submenu_page(
			'bookingx-options',
			__( 'Fitness & Sports', 'bkx-fitness-sports' ),
			__( 'Fitness & Sports', 'bkx-fitness-sports' ),
			'manage_options',
			'bkx-fitness-sports',
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
			'bkx_fitness_sports_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// General section.
		add_settings_section(
			'general',
			__( 'General Settings', 'bkx-fitness-sports' ),
			null,
			'bkx-fitness-sports'
		);

		$this->add_field( 'enabled', __( 'Enable Fitness & Sports', 'bkx-fitness-sports' ), 'checkbox', 'general' );
		$this->add_field( 'business_type', __( 'Business Type', 'bkx-fitness-sports' ), 'select', 'general', array(
			'options' => array(
				'gym'            => __( 'Gym/Fitness Center', 'bkx-fitness-sports' ),
				'personal_trainer' => __( 'Personal Trainer', 'bkx-fitness-sports' ),
				'yoga_studio'    => __( 'Yoga Studio', 'bkx-fitness-sports' ),
				'crossfit'       => __( 'CrossFit Box', 'bkx-fitness-sports' ),
				'sports_club'    => __( 'Sports Club', 'bkx-fitness-sports' ),
				'multi'          => __( 'Multi-Service', 'bkx-fitness-sports' ),
			),
		) );

		// Class scheduling section.
		add_settings_section(
			'classes',
			__( 'Class Scheduling', 'bkx-fitness-sports' ),
			null,
			'bkx-fitness-sports'
		);

		$this->add_field( 'enable_class_scheduling', __( 'Enable Class Scheduling', 'bkx-fitness-sports' ), 'checkbox', 'classes' );
		$this->add_field( 'max_class_size', __( 'Default Max Class Size', 'bkx-fitness-sports' ), 'number', 'classes', array( 'default' => 20 ) );
		$this->add_field( 'booking_window_days', __( 'Booking Window (days)', 'bkx-fitness-sports' ), 'number', 'classes', array( 'default' => 14, 'description' => __( 'How many days in advance can members book classes.', 'bkx-fitness-sports' ) ) );
		$this->add_field( 'cancellation_hours', __( 'Cancellation Deadline (hours)', 'bkx-fitness-sports' ), 'number', 'classes', array( 'default' => 4, 'description' => __( 'Minimum hours before class to allow cancellation.', 'bkx-fitness-sports' ) ) );
		$this->add_field( 'enable_waitlist', __( 'Enable Waitlist', 'bkx-fitness-sports' ), 'checkbox', 'classes' );

		// Membership section.
		add_settings_section(
			'membership',
			__( 'Membership', 'bkx-fitness-sports' ),
			null,
			'bkx-fitness-sports'
		);

		$this->add_field( 'enable_membership', __( 'Enable Memberships', 'bkx-fitness-sports' ), 'checkbox', 'membership' );
		$this->add_field( 'require_membership', __( 'Require Membership for Booking', 'bkx-fitness-sports' ), 'checkbox', 'membership', array( 'description' => __( 'Require an active membership to book classes.', 'bkx-fitness-sports' ) ) );

		// Equipment section.
		add_settings_section(
			'equipment',
			__( 'Equipment Booking', 'bkx-fitness-sports' ),
			null,
			'bkx-fitness-sports'
		);

		$this->add_field( 'enable_equipment_booking', __( 'Enable Equipment Booking', 'bkx-fitness-sports' ), 'checkbox', 'equipment' );
		$this->add_field( 'equipment_slot_duration', __( 'Default Slot Duration (min)', 'bkx-fitness-sports' ), 'number', 'equipment', array( 'default' => 30 ) );

		// Performance tracking section.
		add_settings_section(
			'performance',
			__( 'Performance Tracking', 'bkx-fitness-sports' ),
			null,
			'bkx-fitness-sports'
		);

		$this->add_field( 'enable_performance_tracking', __( 'Enable Performance Tracking', 'bkx-fitness-sports' ), 'checkbox', 'performance' );
		$this->add_field( 'enable_trainer_profiles', __( 'Enable Trainer Profiles', 'bkx-fitness-sports' ), 'checkbox', 'performance' );
	}

	/**
	 * Add settings field helper.
	 *
	 * @since 1.0.0
	 * @param string $id      Field ID.
	 * @param string $label   Field label.
	 * @param string $type    Field type.
	 * @param string $section Section ID.
	 * @param array  $args    Additional arguments.
	 * @return void
	 */
	private function add_field( string $id, string $label, string $type, string $section, array $args = array() ): void {
		add_settings_field(
			$id,
			$label,
			array( $this, 'render_field' ),
			'bkx-fitness-sports',
			$section,
			array_merge( $args, array( 'id' => $id, 'type' => $type ) )
		);
	}

	/**
	 * Render settings field.
	 *
	 * @since 1.0.0
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_field( array $args ): void {
		$settings = get_option( self::OPTION_NAME, array() );
		$value    = $settings[ $args['id'] ] ?? ( $args['default'] ?? '' );
		$name     = self::OPTION_NAME . '[' . $args['id'] . ']';

		switch ( $args['type'] ) {
			case 'checkbox':
				?>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( 1, $value ); ?>>
					<?php echo isset( $args['description'] ) ? esc_html( $args['description'] ) : ''; ?>
				</label>
				<?php
				break;

			case 'select':
				?>
				<select name="<?php echo esc_attr( $name ); ?>">
					<?php foreach ( $args['options'] as $opt_value => $opt_label ) : ?>
						<option value="<?php echo esc_attr( $opt_value ); ?>" <?php selected( $value, $opt_value ); ?>>
							<?php echo esc_html( $opt_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( isset( $args['description'] ) ) : ?>
					<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
				<?php endif; ?>
				<?php
				break;

			case 'number':
				?>
				<input type="number" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="small-text" min="0">
				<?php if ( isset( $args['description'] ) ) : ?>
					<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
				<?php endif; ?>
				<?php
				break;

			default:
				?>
				<input type="text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
				<?php if ( isset( $args['description'] ) ) : ?>
					<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
				<?php endif; ?>
				<?php
				break;
		}
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

		$checkboxes = array(
			'enabled', 'enable_class_scheduling', 'enable_membership',
			'require_membership', 'enable_equipment_booking', 'enable_waitlist',
			'enable_performance_tracking', 'enable_trainer_profiles',
		);

		foreach ( $checkboxes as $key ) {
			$sanitized[ $key ] = isset( $input[ $key ] ) ? 1 : 0;
		}

		$numbers = array( 'max_class_size', 'booking_window_days', 'cancellation_hours', 'equipment_slot_duration' );

		foreach ( $numbers as $key ) {
			$sanitized[ $key ] = absint( $input[ $key ] ?? 0 );
		}

		if ( isset( $input['business_type'] ) ) {
			$sanitized['business_type'] = sanitize_text_field( $input['business_type'] );
		}

		return $sanitized;
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap bkx-fitness-sports-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'bkx_fitness_sports_settings' );
				do_settings_sections( 'bkx-fitness-sports' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
