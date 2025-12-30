<?php
/**
 * Availability Calendar Divi Module.
 *
 * @package BookingX\Divi\Modules
 */

namespace BookingX\Divi\Modules;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'ET_Builder_Module' ) ) {
	return;
}

/**
 * AvailabilityCalendar Module class.
 */
class AvailabilityCalendar extends \ET_Builder_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	public $slug = 'bkx_availability_calendar';

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
		$this->name = esc_html__( 'BKX Availability Calendar', 'bkx-divi' );
		$this->icon = 'F';

		$this->settings_modal_toggles = array(
			'general'  => array(
				'toggles' => array(
					'main_content' => esc_html__( 'Content', 'bkx-divi' ),
					'filters'      => esc_html__( 'Filters', 'bkx-divi' ),
				),
			),
			'advanced' => array(
				'toggles' => array(
					'calendar_style' => esc_html__( 'Calendar Style', 'bkx-divi' ),
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
			'calendar_view'    => array(
				'label'           => esc_html__( 'Default View', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array(
					'month' => esc_html__( 'Month', 'bkx-divi' ),
					'week'  => esc_html__( 'Week', 'bkx-divi' ),
					'day'   => esc_html__( 'Day', 'bkx-divi' ),
				),
				'default'         => 'month',
				'toggle_slug'     => 'main_content',
			),
			'show_navigation'  => array(
				'label'           => esc_html__( 'Show Navigation', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'main_content',
			),
			'show_view_toggle' => array(
				'label'           => esc_html__( 'Show View Toggle', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'main_content',
			),
			'show_legend'      => array(
				'label'           => esc_html__( 'Show Legend', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'main_content',
			),
			'clickable_slots'  => array(
				'label'           => esc_html__( 'Clickable Slots', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'main_content',
				'description'     => esc_html__( 'Allow clicking on available slots to start booking.', 'bkx-divi' ),
			),
			'filter_service'   => array(
				'label'           => esc_html__( 'Filter by Service', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array_merge( array( '' => esc_html__( 'All Services', 'bkx-divi' ) ), $services ),
				'default'         => '',
				'toggle_slug'     => 'filters',
			),
			'filter_resource'  => array(
				'label'           => esc_html__( 'Filter by Resource', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array_merge( array( '' => esc_html__( 'All Resources', 'bkx-divi' ) ), $resources ),
				'default'         => '',
				'toggle_slug'     => 'filters',
			),
			'min_date'         => array(
				'label'           => esc_html__( 'Minimum Date', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array(
					'today'    => esc_html__( 'Today', 'bkx-divi' ),
					'tomorrow' => esc_html__( 'Tomorrow', 'bkx-divi' ),
					'week'     => esc_html__( '1 Week from now', 'bkx-divi' ),
				),
				'default'         => 'today',
				'toggle_slug'     => 'filters',
			),
			'max_months'       => array(
				'label'           => esc_html__( 'Max Months Ahead', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array(
					'1'  => '1',
					'2'  => '2',
					'3'  => '3',
					'6'  => '6',
					'12' => '12',
				),
				'default'         => '3',
				'toggle_slug'     => 'filters',
			),
			'available_color'  => array(
				'label'           => esc_html__( 'Available Color', 'bkx-divi' ),
				'type'            => 'color-alpha',
				'default'         => '#22c55e',
				'toggle_slug'     => 'calendar_style',
				'tab_slug'        => 'advanced',
			),
			'unavailable_color' => array(
				'label'           => esc_html__( 'Unavailable Color', 'bkx-divi' ),
				'type'            => 'color-alpha',
				'default'         => '#ef4444',
				'toggle_slug'     => 'calendar_style',
				'tab_slug'        => 'advanced',
			),
			'limited_color'    => array(
				'label'           => esc_html__( 'Limited Availability Color', 'bkx-divi' ),
				'type'            => 'color-alpha',
				'default'         => '#f59e0b',
				'toggle_slug'     => 'calendar_style',
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
		$calendar_view     = $this->props['calendar_view'];
		$show_navigation   = 'on' === $this->props['show_navigation'];
		$show_view_toggle  = 'on' === $this->props['show_view_toggle'];
		$show_legend       = 'on' === $this->props['show_legend'];
		$clickable_slots   = 'on' === $this->props['clickable_slots'];
		$filter_service    = $this->props['filter_service'];
		$filter_resource   = $this->props['filter_resource'];
		$min_date          = $this->props['min_date'];
		$max_months        = $this->props['max_months'];
		$available_color   = $this->props['available_color'];
		$unavailable_color = $this->props['unavailable_color'];
		$limited_color     = $this->props['limited_color'];

		$classes = array(
			'bkx-divi-availability-calendar',
			'bkx-view-' . $calendar_view,
		);

		$data_atts = array(
			'data-view'        => $calendar_view,
			'data-navigation'  => $show_navigation ? 'true' : 'false',
			'data-view-toggle' => $show_view_toggle ? 'true' : 'false',
			'data-clickable'   => $clickable_slots ? 'true' : 'false',
			'data-service'     => $filter_service,
			'data-resource'    => $filter_resource,
			'data-min-date'    => $min_date,
			'data-max-months'  => $max_months,
		);

		$data_string = '';
		foreach ( $data_atts as $key => $value ) {
			$data_string .= ' ' . $key . '="' . esc_attr( $value ) . '"';
		}

		// Custom CSS for colors.
		$custom_css = "
			.bkx-divi-availability-calendar .bkx-slot-available { background-color: {$available_color}; }
			.bkx-divi-availability-calendar .bkx-slot-unavailable { background-color: {$unavailable_color}; }
			.bkx-divi-availability-calendar .bkx-slot-limited { background-color: {$limited_color}; }
		";

		ob_start();
		?>
		<style><?php echo wp_strip_all_tags( $custom_css ); ?></style>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"<?php echo $data_string; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php if ( $show_navigation ) : ?>
				<div class="bkx-calendar-header">
					<button type="button" class="bkx-calendar-prev">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
					</button>
					<h3 class="bkx-calendar-title"></h3>
					<button type="button" class="bkx-calendar-next">
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>
				</div>
			<?php endif; ?>

			<?php if ( $show_view_toggle ) : ?>
				<div class="bkx-calendar-view-toggle">
					<button type="button" class="bkx-view-btn <?php echo 'month' === $calendar_view ? 'active' : ''; ?>" data-view="month">
						<?php esc_html_e( 'Month', 'bkx-divi' ); ?>
					</button>
					<button type="button" class="bkx-view-btn <?php echo 'week' === $calendar_view ? 'active' : ''; ?>" data-view="week">
						<?php esc_html_e( 'Week', 'bkx-divi' ); ?>
					</button>
					<button type="button" class="bkx-view-btn <?php echo 'day' === $calendar_view ? 'active' : ''; ?>" data-view="day">
						<?php esc_html_e( 'Day', 'bkx-divi' ); ?>
					</button>
				</div>
			<?php endif; ?>

			<div class="bkx-calendar-grid">
				<!-- Calendar will be rendered by JavaScript -->
				<div class="bkx-calendar-loading">
					<span class="spinner is-active"></span>
					<?php esc_html_e( 'Loading calendar...', 'bkx-divi' ); ?>
				</div>
			</div>

			<?php if ( $show_legend ) : ?>
				<div class="bkx-calendar-legend">
					<span class="bkx-legend-item">
						<span class="bkx-legend-color bkx-slot-available"></span>
						<?php esc_html_e( 'Available', 'bkx-divi' ); ?>
					</span>
					<span class="bkx-legend-item">
						<span class="bkx-legend-color bkx-slot-limited"></span>
						<?php esc_html_e( 'Limited', 'bkx-divi' ); ?>
					</span>
					<span class="bkx-legend-item">
						<span class="bkx-legend-color bkx-slot-unavailable"></span>
						<?php esc_html_e( 'Unavailable', 'bkx-divi' ); ?>
					</span>
				</div>
			<?php endif; ?>
		</div>
		<?php

		return ob_get_clean();
	}
}

new AvailabilityCalendar();
