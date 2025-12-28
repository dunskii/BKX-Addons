<?php
/**
 * Availability Calendar Elementor Widget.
 *
 * @package BookingX\Elementor\Widgets
 * @since   1.0.0
 */

namespace BookingX\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AvailabilityWidget Class.
 */
class AvailabilityWidget extends Widget_Base {

	/**
	 * Get widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'bkx-availability';
	}

	/**
	 * Get widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Availability Calendar', 'bkx-elementor' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-calendar';
	}

	/**
	 * Get widget categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		return array( 'bookingx' );
	}

	/**
	 * Get script dependencies.
	 *
	 * @return array
	 */
	public function get_script_depends() {
		return array( 'bkx-elementor-frontend' );
	}

	/**
	 * Register controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'bkx-elementor' ),
			)
		);

		$this->add_control(
			'service_id',
			array(
				'label'   => __( 'Service', 'bkx-elementor' ),
				'type'    => Controls_Manager::SELECT2,
				'options' => $this->get_services_options(),
			)
		);

		$this->add_control(
			'seat_id',
			array(
				'label'   => __( 'Staff/Resource', 'bkx-elementor' ),
				'type'    => Controls_Manager::SELECT2,
				'options' => $this->get_seats_options(),
			)
		);

		$this->add_control(
			'months_ahead',
			array(
				'label'   => __( 'Months Ahead', 'bkx-elementor' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 2,
				'min'     => 1,
				'max'     => 12,
			)
		);

		$this->add_control(
			'show_legend',
			array(
				'label'        => __( 'Show Legend', 'bkx-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->end_controls_section();

		// Style Section.
		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Calendar Style', 'bkx-elementor' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'available_color',
			array(
				'label'     => __( 'Available Color', 'bkx-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#46b450',
				'selectors' => array(
					'{{WRAPPER}} .bkx-day.available' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'unavailable_color',
			array(
				'label'     => __( 'Unavailable Color', 'bkx-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#dc3232',
				'selectors' => array(
					'{{WRAPPER}} .bkx-day.unavailable' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'limited_color',
			array(
				'label'     => __( 'Limited Availability Color', 'bkx-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffb900',
				'selectors' => array(
					'{{WRAPPER}} .bkx-day.limited' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget.
	 */
	protected function render() {
		$settings    = $this->get_settings_for_display();
		$service_id  = $settings['service_id'];
		$seat_id     = $settings['seat_id'];
		$months      = absint( $settings['months_ahead'] );
		$show_legend = 'yes' === $settings['show_legend'];
		?>
		<div class="bkx-availability-calendar"
			 data-service="<?php echo esc_attr( $service_id ); ?>"
			 data-seat="<?php echo esc_attr( $seat_id ); ?>"
			 data-months="<?php echo esc_attr( $months ); ?>">

			<div class="calendar-header">
				<button class="nav-prev" aria-label="<?php esc_attr_e( 'Previous Month', 'bkx-elementor' ); ?>">
					<span class="dashicons dashicons-arrow-left-alt2"></span>
				</button>
				<span class="current-month"></span>
				<button class="nav-next" aria-label="<?php esc_attr_e( 'Next Month', 'bkx-elementor' ); ?>">
					<span class="dashicons dashicons-arrow-right-alt2"></span>
				</button>
			</div>

			<div class="calendar-weekdays">
				<span><?php esc_html_e( 'Sun', 'bkx-elementor' ); ?></span>
				<span><?php esc_html_e( 'Mon', 'bkx-elementor' ); ?></span>
				<span><?php esc_html_e( 'Tue', 'bkx-elementor' ); ?></span>
				<span><?php esc_html_e( 'Wed', 'bkx-elementor' ); ?></span>
				<span><?php esc_html_e( 'Thu', 'bkx-elementor' ); ?></span>
				<span><?php esc_html_e( 'Fri', 'bkx-elementor' ); ?></span>
				<span><?php esc_html_e( 'Sat', 'bkx-elementor' ); ?></span>
			</div>

			<div class="calendar-days">
				<!-- Days populated via JavaScript -->
			</div>

			<?php if ( $show_legend ) : ?>
				<div class="calendar-legend">
					<span class="legend-item available">
						<span class="legend-color"></span>
						<?php esc_html_e( 'Available', 'bkx-elementor' ); ?>
					</span>
					<span class="legend-item limited">
						<span class="legend-color"></span>
						<?php esc_html_e( 'Limited', 'bkx-elementor' ); ?>
					</span>
					<span class="legend-item unavailable">
						<span class="legend-color"></span>
						<?php esc_html_e( 'Unavailable', 'bkx-elementor' ); ?>
					</span>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get services options.
	 *
	 * @return array
	 */
	private function get_services_options() {
		$options  = array( '' => __( 'All Services', 'bkx-elementor' ) );
		$services = get_posts( array(
			'post_type'      => 'bkx_base',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		foreach ( $services as $service ) {
			$options[ $service->ID ] = $service->post_title;
		}

		return $options;
	}

	/**
	 * Get seats options.
	 *
	 * @return array
	 */
	private function get_seats_options() {
		$alias   = get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-elementor' ) );
		$options = array( '' => sprintf( __( 'All %s', 'bkx-elementor' ), $alias ) );
		$seats   = get_posts( array(
			'post_type'      => 'bkx_seat',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		foreach ( $seats as $seat ) {
			$options[ $seat->ID ] = $seat->post_title;
		}

		return $options;
	}
}
