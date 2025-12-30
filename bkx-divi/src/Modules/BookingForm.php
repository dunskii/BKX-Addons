<?php
/**
 * Booking Form Divi Module.
 *
 * @package BookingX\Divi\Modules
 */

namespace BookingX\Divi\Modules;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'ET_Builder_Module' ) ) {
	return;
}

/**
 * BookingForm Module class.
 */
class BookingForm extends \ET_Builder_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	public $slug = 'bkx_booking_form';

	/**
	 * VB support.
	 *
	 * @var string
	 */
	public $vb_support = 'on';

	/**
	 * Module credits.
	 *
	 * @var array
	 */
	protected $module_credits = array(
		'module_uri' => 'https://bookingx.com/add-ons/divi',
		'author'     => 'BookingX',
		'author_uri' => 'https://bookingx.com',
	);

	/**
	 * Initialize module.
	 */
	public function init() {
		$this->name = esc_html__( 'BKX Booking Form', 'bkx-divi' );
		$this->icon = 'E';

		$this->settings_modal_toggles = array(
			'general'  => array(
				'toggles' => array(
					'main_content' => esc_html__( 'Content', 'bkx-divi' ),
					'filters'      => esc_html__( 'Filters', 'bkx-divi' ),
				),
			),
			'advanced' => array(
				'toggles' => array(
					'form_style'   => esc_html__( 'Form Style', 'bkx-divi' ),
					'button_style' => esc_html__( 'Button Style', 'bkx-divi' ),
				),
			),
		);
	}

	/**
	 * Get module fields.
	 *
	 * @return array
	 */
	public function get_fields() {
		$addon    = \BookingX\Divi\DiviAddon::get_instance();
		$services = $addon->get_services();
		$resources = $addon->get_resources();

		return array(
			'form_style'       => array(
				'label'           => esc_html__( 'Form Style', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array(
					'default'    => esc_html__( 'Default', 'bkx-divi' ),
					'compact'    => esc_html__( 'Compact', 'bkx-divi' ),
					'horizontal' => esc_html__( 'Horizontal', 'bkx-divi' ),
					'wizard'     => esc_html__( 'Multi-Step Wizard', 'bkx-divi' ),
				),
				'default'         => 'default',
				'toggle_slug'     => 'main_content',
			),
			'show_service'     => array(
				'label'           => esc_html__( 'Show Service Selection', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'main_content',
			),
			'show_resource'    => array(
				'label'           => esc_html__( 'Show Resource Selection', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'main_content',
			),
			'show_calendar'    => array(
				'label'           => esc_html__( 'Show Calendar', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'main_content',
			),
			'show_extras'      => array(
				'label'           => esc_html__( 'Show Extras', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'main_content',
			),
			'default_service'  => array(
				'label'           => esc_html__( 'Default Service', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array_merge( array( '' => esc_html__( 'None', 'bkx-divi' ) ), $services ),
				'default'         => '',
				'toggle_slug'     => 'filters',
			),
			'default_resource' => array(
				'label'           => esc_html__( 'Default Resource', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array_merge( array( '' => esc_html__( 'None', 'bkx-divi' ) ), $resources ),
				'default'         => '',
				'toggle_slug'     => 'filters',
			),
			'filter_services'  => array(
				'label'           => esc_html__( 'Filter Services', 'bkx-divi' ),
				'type'            => 'multiple_checkboxes',
				'option_category' => 'basic_option',
				'options'         => $services,
				'toggle_slug'     => 'filters',
				'description'     => esc_html__( 'Select services to show. Leave empty for all.', 'bkx-divi' ),
			),
			'filter_resources' => array(
				'label'           => esc_html__( 'Filter Resources', 'bkx-divi' ),
				'type'            => 'multiple_checkboxes',
				'option_category' => 'basic_option',
				'options'         => $resources,
				'toggle_slug'     => 'filters',
				'description'     => esc_html__( 'Select resources to show. Leave empty for all.', 'bkx-divi' ),
			),
			'submit_text'      => array(
				'label'           => esc_html__( 'Submit Button Text', 'bkx-divi' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'default'         => esc_html__( 'Book Now', 'bkx-divi' ),
				'toggle_slug'     => 'main_content',
			),
		);
	}

	/**
	 * Render module.
	 *
	 * @param array  $attrs       Attributes.
	 * @param string $content     Content.
	 * @param string $render_slug Render slug.
	 * @return string
	 */
	public function render( $attrs, $content, $render_slug ) {
		$form_style       = $this->props['form_style'];
		$show_service     = 'on' === $this->props['show_service'];
		$show_resource    = 'on' === $this->props['show_resource'];
		$show_calendar    = 'on' === $this->props['show_calendar'];
		$show_extras      = 'on' === $this->props['show_extras'];
		$default_service  = $this->props['default_service'];
		$default_resource = $this->props['default_resource'];
		$submit_text      = $this->props['submit_text'];

		$shortcode_atts = array();

		if ( $default_service ) {
			$shortcode_atts[] = 'service_id="' . intval( $default_service ) . '"';
		}

		if ( $default_resource ) {
			$shortcode_atts[] = 'resource_id="' . intval( $default_resource ) . '"';
		}

		if ( ! $show_service ) {
			$shortcode_atts[] = 'hide_service="true"';
		}

		if ( ! $show_resource ) {
			$shortcode_atts[] = 'hide_resource="true"';
		}

		if ( ! $show_calendar ) {
			$shortcode_atts[] = 'hide_calendar="true"';
		}

		if ( ! $show_extras ) {
			$shortcode_atts[] = 'hide_extras="true"';
		}

		if ( $submit_text ) {
			$shortcode_atts[] = 'submit_text="' . esc_attr( $submit_text ) . '"';
		}

		$shortcode = '[bookingx_form ' . implode( ' ', $shortcode_atts ) . ']';

		$classes = array(
			'bkx-divi-booking-form',
			'bkx-form-style-' . $form_style,
		);

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php echo do_shortcode( $shortcode ); ?>
		</div>
		<?php

		return ob_get_clean();
	}
}

new BookingForm();
