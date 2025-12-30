<?php
/**
 * Booking Button Divi Module.
 *
 * @package BookingX\Divi\Modules
 */

namespace BookingX\Divi\Modules;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'ET_Builder_Module' ) ) {
	return;
}

/**
 * BookingButton Module class.
 */
class BookingButton extends \ET_Builder_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	public $slug = 'bkx_booking_button';

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
		$this->name = esc_html__( 'BKX Booking Button', 'bkx-divi' );
		$this->icon = 'U';

		$this->settings_modal_toggles = array(
			'general'  => array(
				'toggles' => array(
					'main_content' => esc_html__( 'Button', 'bkx-divi' ),
					'link'         => esc_html__( 'Link', 'bkx-divi' ),
				),
			),
			'advanced' => array(
				'toggles' => array(
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
		$addon     = \BookingX\Divi\DiviAddon::get_instance();
		$services  = $addon->get_services();
		$resources = $addon->get_resources();

		return array(
			'button_text'      => array(
				'label'           => esc_html__( 'Button Text', 'bkx-divi' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'default'         => esc_html__( 'Book Now', 'bkx-divi' ),
				'toggle_slug'     => 'main_content',
			),
			'button_style'     => array(
				'label'           => esc_html__( 'Button Style', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array(
					'primary'   => esc_html__( 'Primary', 'bkx-divi' ),
					'secondary' => esc_html__( 'Secondary', 'bkx-divi' ),
					'outline'   => esc_html__( 'Outline', 'bkx-divi' ),
					'text'      => esc_html__( 'Text', 'bkx-divi' ),
				),
				'default'         => 'primary',
				'toggle_slug'     => 'main_content',
			),
			'button_size'      => array(
				'label'           => esc_html__( 'Button Size', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array(
					'small'  => esc_html__( 'Small', 'bkx-divi' ),
					'medium' => esc_html__( 'Medium', 'bkx-divi' ),
					'large'  => esc_html__( 'Large', 'bkx-divi' ),
				),
				'default'         => 'medium',
				'toggle_slug'     => 'main_content',
			),
			'button_icon'      => array(
				'label'           => esc_html__( 'Button Icon', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array(
					'none'     => esc_html__( 'None', 'bkx-divi' ),
					'calendar' => esc_html__( 'Calendar', 'bkx-divi' ),
					'clock'    => esc_html__( 'Clock', 'bkx-divi' ),
					'arrow'    => esc_html__( 'Arrow', 'bkx-divi' ),
					'plus'     => esc_html__( 'Plus', 'bkx-divi' ),
				),
				'default'         => 'calendar',
				'toggle_slug'     => 'main_content',
			),
			'icon_position'    => array(
				'label'           => esc_html__( 'Icon Position', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array(
					'left'  => esc_html__( 'Left', 'bkx-divi' ),
					'right' => esc_html__( 'Right', 'bkx-divi' ),
				),
				'default'         => 'left',
				'toggle_slug'     => 'main_content',
				'show_if_not'     => array(
					'button_icon' => 'none',
				),
			),
			'full_width'       => array(
				'label'           => esc_html__( 'Full Width', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'off',
				'toggle_slug'     => 'main_content',
			),
			'link_type'        => array(
				'label'           => esc_html__( 'Link Type', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array(
					'page'   => esc_html__( 'Booking Page', 'bkx-divi' ),
					'modal'  => esc_html__( 'Modal Popup', 'bkx-divi' ),
					'custom' => esc_html__( 'Custom URL', 'bkx-divi' ),
				),
				'default'         => 'page',
				'toggle_slug'     => 'link',
			),
			'custom_url'       => array(
				'label'           => esc_html__( 'Custom URL', 'bkx-divi' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'default'         => '',
				'toggle_slug'     => 'link',
				'show_if'         => array(
					'link_type' => 'custom',
				),
			),
			'preselect_service' => array(
				'label'           => esc_html__( 'Preselect Service', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array_merge( array( '' => esc_html__( 'None', 'bkx-divi' ) ), $services ),
				'default'         => '',
				'toggle_slug'     => 'link',
				'show_if_not'     => array(
					'link_type' => 'custom',
				),
			),
			'preselect_resource' => array(
				'label'           => esc_html__( 'Preselect Resource', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array_merge( array( '' => esc_html__( 'None', 'bkx-divi' ) ), $resources ),
				'default'         => '',
				'toggle_slug'     => 'link',
				'show_if_not'     => array(
					'link_type' => 'custom',
				),
			),
			'open_new_tab'     => array(
				'label'           => esc_html__( 'Open in New Tab', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'off',
				'toggle_slug'     => 'link',
				'show_if'         => array(
					'link_type' => array( 'page', 'custom' ),
				),
			),
			'button_bg_color'  => array(
				'label'           => esc_html__( 'Background Color', 'bkx-divi' ),
				'type'            => 'color-alpha',
				'default'         => '#2563eb',
				'toggle_slug'     => 'button_style',
				'tab_slug'        => 'advanced',
			),
			'button_text_color' => array(
				'label'           => esc_html__( 'Text Color', 'bkx-divi' ),
				'type'            => 'color-alpha',
				'default'         => '#ffffff',
				'toggle_slug'     => 'button_style',
				'tab_slug'        => 'advanced',
			),
			'button_hover_bg'  => array(
				'label'           => esc_html__( 'Hover Background Color', 'bkx-divi' ),
				'type'            => 'color-alpha',
				'default'         => '#1d4ed8',
				'toggle_slug'     => 'button_style',
				'tab_slug'        => 'advanced',
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
		$button_text        = $this->props['button_text'];
		$button_style       = $this->props['button_style'];
		$button_size        = $this->props['button_size'];
		$button_icon        = $this->props['button_icon'];
		$icon_position      = $this->props['icon_position'];
		$full_width         = 'on' === $this->props['full_width'];
		$link_type          = $this->props['link_type'];
		$custom_url         = $this->props['custom_url'];
		$preselect_service  = $this->props['preselect_service'];
		$preselect_resource = $this->props['preselect_resource'];
		$open_new_tab       = 'on' === $this->props['open_new_tab'];
		$button_bg_color    = $this->props['button_bg_color'];
		$button_text_color  = $this->props['button_text_color'];
		$button_hover_bg    = $this->props['button_hover_bg'];

		// Build URL.
		$url = '#';
		if ( 'custom' === $link_type ) {
			$url = $custom_url;
		} elseif ( 'page' === $link_type ) {
			$booking_page = get_option( 'bkx_booking_page' );
			if ( $booking_page ) {
				$url = get_permalink( $booking_page );
				$query_args = array();
				if ( $preselect_service ) {
					$query_args['service_id'] = $preselect_service;
				}
				if ( $preselect_resource ) {
					$query_args['resource_id'] = $preselect_resource;
				}
				if ( ! empty( $query_args ) ) {
					$url = add_query_arg( $query_args, $url );
				}
			}
		}

		// Build classes.
		$classes = array(
			'bkx-divi-booking-button',
			'bkx-btn-style-' . $button_style,
			'bkx-btn-size-' . $button_size,
		);

		if ( $full_width ) {
			$classes[] = 'bkx-btn-full-width';
		}

		if ( 'modal' === $link_type ) {
			$classes[] = 'bkx-btn-modal-trigger';
		}

		// Icon mapping.
		$icon_map = array(
			'calendar' => 'dashicons-calendar-alt',
			'clock'    => 'dashicons-clock',
			'arrow'    => 'dashicons-arrow-right-alt',
			'plus'     => 'dashicons-plus-alt',
		);

		$icon_class = isset( $icon_map[ $button_icon ] ) ? $icon_map[ $button_icon ] : '';

		// Generate unique ID for custom styles.
		$unique_id = 'bkx-btn-' . uniqid();

		// Custom CSS.
		$custom_css = "
			#{$unique_id} {
				background-color: {$button_bg_color};
				color: {$button_text_color};
			}
			#{$unique_id}:hover {
				background-color: {$button_hover_bg};
			}
		";

		$data_atts = '';
		if ( 'modal' === $link_type ) {
			$data_atts = ' data-modal="true"';
			if ( $preselect_service ) {
				$data_atts .= ' data-service="' . esc_attr( $preselect_service ) . '"';
			}
			if ( $preselect_resource ) {
				$data_atts .= ' data-resource="' . esc_attr( $preselect_resource ) . '"';
			}
		}

		ob_start();
		?>
		<style><?php echo wp_strip_all_tags( $custom_css ); ?></style>
		<a href="<?php echo esc_url( $url ); ?>"
		   id="<?php echo esc_attr( $unique_id ); ?>"
		   class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
		   <?php echo $open_new_tab ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
		   <?php echo $data_atts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( 'none' !== $button_icon && 'left' === $icon_position ) : ?>
				<span class="dashicons <?php echo esc_attr( $icon_class ); ?> bkx-btn-icon"></span>
			<?php endif; ?>
			<span class="bkx-btn-text"><?php echo esc_html( $button_text ); ?></span>
			<?php if ( 'none' !== $button_icon && 'right' === $icon_position ) : ?>
				<span class="dashicons <?php echo esc_attr( $icon_class ); ?> bkx-btn-icon"></span>
			<?php endif; ?>
		</a>
		<?php

		return ob_get_clean();
	}
}

new BookingButton();
