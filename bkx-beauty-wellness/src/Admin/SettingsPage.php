<?php
/**
 * Settings Page
 *
 * Admin settings page for Beauty & Wellness addon.
 *
 * @package BookingX\BeautyWellness\Admin
 * @since   1.0.0
 */

namespace BookingX\BeautyWellness\Admin;

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
	 * Option name for settings.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'bkx_beauty_wellness_settings';

	/**
	 * Settings sections.
	 *
	 * @var array
	 */
	private array $sections;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->sections = $this->get_sections();
	}

	/**
	 * Initialize settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_submenu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
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
			__( 'Beauty & Wellness', 'bkx-beauty-wellness' ),
			__( 'Beauty & Wellness', 'bkx-beauty-wellness' ),
			'manage_options',
			'bkx-beauty-wellness',
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
			'bkx_beauty_wellness_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		foreach ( $this->sections as $section_id => $section ) {
			add_settings_section(
				$section_id,
				$section['title'],
				$section['callback'] ?? null,
				'bkx-beauty-wellness'
			);

			foreach ( $section['fields'] as $field_id => $field ) {
				add_settings_field(
					$field_id,
					$field['label'],
					array( $this, 'render_field' ),
					'bkx-beauty-wellness',
					$section_id,
					array_merge( $field, array( 'id' => $field_id ) )
				);
			}
		}
	}

	/**
	 * Get settings sections.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_sections(): array {
		return array(
			'general'     => array(
				'title'  => __( 'General Settings', 'bkx-beauty-wellness' ),
				'fields' => array(
					'enabled'              => array(
						'label'   => __( 'Enable Beauty & Wellness', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Enable or disable the Beauty & Wellness features.', 'bkx-beauty-wellness' ),
					),
					'business_type'        => array(
						'label'   => __( 'Business Type', 'bkx-beauty-wellness' ),
						'type'    => 'select',
						'options' => array(
							'spa'      => __( 'Spa', 'bkx-beauty-wellness' ),
							'salon'    => __( 'Hair Salon', 'bkx-beauty-wellness' ),
							'nail'     => __( 'Nail Salon', 'bkx-beauty-wellness' ),
							'wellness' => __( 'Wellness Center', 'bkx-beauty-wellness' ),
							'medspa'   => __( 'Medical Spa', 'bkx-beauty-wellness' ),
							'barbershop' => __( 'Barbershop', 'bkx-beauty-wellness' ),
							'multi'    => __( 'Multi-Service', 'bkx-beauty-wellness' ),
						),
						'default' => 'salon',
						'desc'    => __( 'Select your business type for optimized features.', 'bkx-beauty-wellness' ),
					),
				),
			),
			'treatment_menu' => array(
				'title'  => __( 'Treatment Menu', 'bkx-beauty-wellness' ),
				'fields' => array(
					'enable_treatment_menu' => array(
						'label'   => __( 'Enable Treatment Menu', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Display services as a treatment menu with categories.', 'bkx-beauty-wellness' ),
					),
					'menu_style'           => array(
						'label'   => __( 'Menu Style', 'bkx-beauty-wellness' ),
						'type'    => 'select',
						'options' => array(
							'grid'    => __( 'Grid', 'bkx-beauty-wellness' ),
							'list'    => __( 'List', 'bkx-beauty-wellness' ),
							'cards'   => __( 'Cards', 'bkx-beauty-wellness' ),
							'minimal' => __( 'Minimal', 'bkx-beauty-wellness' ),
						),
						'default' => 'grid',
					),
					'show_duration'        => array(
						'label'   => __( 'Show Duration', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Display treatment duration on menu.', 'bkx-beauty-wellness' ),
					),
					'show_prices'          => array(
						'label'   => __( 'Show Prices', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Display prices on treatment menu.', 'bkx-beauty-wellness' ),
					),
				),
			),
			'client_preferences' => array(
				'title'  => __( 'Client Preferences', 'bkx-beauty-wellness' ),
				'fields' => array(
					'enable_client_preferences' => array(
						'label'   => __( 'Enable Client Preferences', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Track client preferences and history.', 'bkx-beauty-wellness' ),
					),
					'skin_type_tracking'   => array(
						'label'   => __( 'Skin Type Tracking', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Track client skin types for personalized recommendations.', 'bkx-beauty-wellness' ),
					),
					'allergy_alerts'       => array(
						'label'   => __( 'Allergy Alerts', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Show alerts when treatments may conflict with allergies.', 'bkx-beauty-wellness' ),
					),
					'product_recommendations' => array(
						'label'   => __( 'Product Recommendations', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Show product recommendations based on client profile.', 'bkx-beauty-wellness' ),
					),
				),
			),
			'service_addons' => array(
				'title'  => __( 'Service Add-ons', 'bkx-beauty-wellness' ),
				'fields' => array(
					'enable_service_addons' => array(
						'label'   => __( 'Enable Service Add-ons', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Allow upsell add-ons during booking.', 'bkx-beauty-wellness' ),
					),
					'addon_display_style'  => array(
						'label'   => __( 'Add-on Display Style', 'bkx-beauty-wellness' ),
						'type'    => 'select',
						'options' => array(
							'grid'     => __( 'Grid', 'bkx-beauty-wellness' ),
							'list'     => __( 'List', 'bkx-beauty-wellness' ),
							'checkbox' => __( 'Checkboxes', 'bkx-beauty-wellness' ),
						),
						'default' => 'grid',
					),
					'show_recommended'     => array(
						'label'   => __( 'Show Recommended Add-ons', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Highlight recommended add-ons based on treatment.', 'bkx-beauty-wellness' ),
					),
					'enable_bundles'       => array(
						'label'   => __( 'Enable Bundles', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Allow bundled add-on packages with discounts.', 'bkx-beauty-wellness' ),
					),
				),
			),
			'portfolio' => array(
				'title'  => __( 'Stylist Portfolio', 'bkx-beauty-wellness' ),
				'fields' => array(
					'enable_stylist_portfolio' => array(
						'label'   => __( 'Enable Stylist Portfolio', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Allow stylists to showcase their work.', 'bkx-beauty-wellness' ),
					),
					'before_after_photos'  => array(
						'label'   => __( 'Before/After Photos', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Enable before/after photo comparisons.', 'bkx-beauty-wellness' ),
					),
					'portfolio_columns'    => array(
						'label'   => __( 'Portfolio Columns', 'bkx-beauty-wellness' ),
						'type'    => 'select',
						'options' => array(
							'2' => '2',
							'3' => '3',
							'4' => '4',
						),
						'default' => '3',
					),
					'enable_lightbox'      => array(
						'label'   => __( 'Enable Lightbox', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Open portfolio images in a lightbox.', 'bkx-beauty-wellness' ),
					),
				),
			),
			'consultation' => array(
				'title'  => __( 'Consultation Form', 'bkx-beauty-wellness' ),
				'fields' => array(
					'enable_consultation_form' => array(
						'label'   => __( 'Enable Consultation Form', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Require consultation form for specific treatments.', 'bkx-beauty-wellness' ),
					),
					'consultation_treatments' => array(
						'label' => __( 'Require Consultation For', 'bkx-beauty-wellness' ),
						'type'  => 'multiselect',
						'options_callback' => 'get_treatment_options',
						'desc'  => __( 'Select treatments that require a consultation form.', 'bkx-beauty-wellness' ),
					),
					'send_form_reminder'   => array(
						'label'   => __( 'Form Reminder', 'bkx-beauty-wellness' ),
						'type'    => 'checkbox',
						'default' => 1,
						'desc'    => __( 'Send reminder to complete consultation form before appointment.', 'bkx-beauty-wellness' ),
					),
				),
			),
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'bookingx_page_bkx-beauty-wellness' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-beauty-wellness-admin',
			BKX_BEAUTY_WELLNESS_URL . 'assets/css/admin.css',
			array(),
			BKX_BEAUTY_WELLNESS_VERSION
		);

		wp_enqueue_script(
			'bkx-beauty-wellness-admin',
			BKX_BEAUTY_WELLNESS_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_BEAUTY_WELLNESS_VERSION,
			true
		);
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

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
		?>
		<div class="wrap bkx-beauty-wellness-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $this->sections as $section_id => $section ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $section_id ) ); ?>"
					   class="nav-tab <?php echo $active_tab === $section_id ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $section['title'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'bkx_beauty_wellness_settings' );

				echo '<div class="bkx-settings-sections">';
				foreach ( $this->sections as $section_id => $section ) {
					$display = $active_tab === $section_id ? 'block' : 'none';
					echo '<div class="bkx-settings-section" id="section-' . esc_attr( $section_id ) . '" style="display:' . $display . '">';
					do_settings_sections( 'bkx-beauty-wellness' );
					echo '</div>';
					break; // Only show active section.
				}
				echo '</div>';

				submit_button();
				?>
			</form>
		</div>
		<?php
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
					<?php echo isset( $args['desc'] ) ? esc_html( $args['desc'] ) : ''; ?>
				</label>
				<?php
				break;

			case 'select':
				?>
				<select name="<?php echo esc_attr( $name ); ?>">
					<?php foreach ( $args['options'] as $option_value => $option_label ) : ?>
						<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
							<?php echo esc_html( $option_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( isset( $args['desc'] ) ) : ?>
					<p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
				<?php endif; ?>
				<?php
				break;

			case 'multiselect':
				$options = array();
				if ( isset( $args['options_callback'] ) && method_exists( $this, $args['options_callback'] ) ) {
					$options = $this->{$args['options_callback']}();
				} elseif ( isset( $args['options'] ) ) {
					$options = $args['options'];
				}
				$value = is_array( $value ) ? $value : array();
				?>
				<select name="<?php echo esc_attr( $name ); ?>[]" multiple style="min-width: 300px; height: 150px;">
					<?php foreach ( $options as $option_value => $option_label ) : ?>
						<option value="<?php echo esc_attr( $option_value ); ?>" <?php echo in_array( $option_value, $value, true ) ? 'selected' : ''; ?>>
							<?php echo esc_html( $option_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ( isset( $args['desc'] ) ) : ?>
					<p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
				<?php endif; ?>
				<?php
				break;

			case 'text':
			default:
				?>
				<input type="text" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text">
				<?php if ( isset( $args['desc'] ) ) : ?>
					<p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
				<?php endif; ?>
				<?php
				break;
		}
	}

	/**
	 * Sanitize settings.
	 *
	 * @since 1.0.0
	 * @param array $input Settings input.
	 * @return array
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		foreach ( $this->sections as $section ) {
			foreach ( $section['fields'] as $field_id => $field ) {
				if ( ! isset( $input[ $field_id ] ) ) {
					$sanitized[ $field_id ] = 'checkbox' === $field['type'] ? 0 : ( $field['default'] ?? '' );
					continue;
				}

				switch ( $field['type'] ) {
					case 'checkbox':
						$sanitized[ $field_id ] = absint( $input[ $field_id ] );
						break;

					case 'select':
						$sanitized[ $field_id ] = array_key_exists( $input[ $field_id ], $field['options'] )
							? sanitize_text_field( $input[ $field_id ] )
							: ( $field['default'] ?? '' );
						break;

					case 'multiselect':
						$sanitized[ $field_id ] = array_map( 'sanitize_text_field', (array) $input[ $field_id ] );
						break;

					default:
						$sanitized[ $field_id ] = sanitize_text_field( $input[ $field_id ] );
						break;
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Get treatment options for multiselect.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_treatment_options(): array {
		$treatments = get_posts( array(
			'post_type'      => 'bkx_base',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$options = array();

		foreach ( $treatments as $treatment ) {
			$options[ $treatment->ID ] = $treatment->post_title;
		}

		return $options;
	}
}
