<?php
/**
 * Booking Form Elementor Widget.
 *
 * @package BookingX\Elementor\Widgets
 * @since   1.0.0
 */

namespace BookingX\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BookingFormWidget Class.
 */
class BookingFormWidget extends Widget_Base {

	/**
	 * Get widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'bkx-booking-form';
	}

	/**
	 * Get widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Booking Form', 'bkx-elementor' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-form-horizontal';
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
	 * Get widget keywords.
	 *
	 * @return array
	 */
	public function get_keywords() {
		return array( 'booking', 'form', 'appointment', 'schedule', 'bookingx' );
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
		// Content Section.
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'bkx-elementor' ),
			)
		);

		$this->add_control(
			'service_display',
			array(
				'label'   => __( 'Service Selection', 'bkx-elementor' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'dropdown',
				'options' => array(
					'dropdown' => __( 'Dropdown', 'bkx-elementor' ),
					'radio'    => __( 'Radio Buttons', 'bkx-elementor' ),
					'cards'    => __( 'Service Cards', 'bkx-elementor' ),
					'hidden'   => __( 'Pre-selected (Hidden)', 'bkx-elementor' ),
				),
			)
		);

		$this->add_control(
			'selected_service',
			array(
				'label'     => __( 'Pre-selected Service', 'bkx-elementor' ),
				'type'      => Controls_Manager::SELECT2,
				'options'   => $this->get_services_options(),
				'condition' => array(
					'service_display' => 'hidden',
				),
			)
		);

		$this->add_control(
			'show_staff_selection',
			array(
				'label'        => __( 'Show Staff Selection', 'bkx-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'bkx-elementor' ),
				'label_off'    => __( 'No', 'bkx-elementor' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_extras',
			array(
				'label'        => __( 'Show Extras', 'bkx-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'bkx-elementor' ),
				'label_off'    => __( 'No', 'bkx-elementor' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'show_price_summary',
			array(
				'label'        => __( 'Show Price Summary', 'bkx-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'bkx-elementor' ),
				'label_off'    => __( 'No', 'bkx-elementor' ),
				'return_value' => 'yes',
				'default'      => 'yes',
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'   => __( 'Button Text', 'bkx-elementor' ),
				'type'    => Controls_Manager::TEXT,
				'default' => __( 'Book Now', 'bkx-elementor' ),
			)
		);

		$this->end_controls_section();

		// Calendar Section.
		$this->start_controls_section(
			'section_calendar',
			array(
				'label' => __( 'Calendar', 'bkx-elementor' ),
			)
		);

		$this->add_control(
			'calendar_style',
			array(
				'label'   => __( 'Calendar Style', 'bkx-elementor' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'inline',
				'options' => array(
					'inline' => __( 'Inline Calendar', 'bkx-elementor' ),
					'popup'  => __( 'Date Picker Popup', 'bkx-elementor' ),
				),
			)
		);

		$this->add_control(
			'time_slot_display',
			array(
				'label'   => __( 'Time Slot Display', 'bkx-elementor' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'grid',
				'options' => array(
					'grid'     => __( 'Grid', 'bkx-elementor' ),
					'list'     => __( 'List', 'bkx-elementor' ),
					'dropdown' => __( 'Dropdown', 'bkx-elementor' ),
				),
			)
		);

		$this->end_controls_section();

		// Style: Form Section.
		$this->start_controls_section(
			'section_style_form',
			array(
				'label' => __( 'Form', 'bkx-elementor' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'form_padding',
			array(
				'label'      => __( 'Padding', 'bkx-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .bkx-booking-form' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'form_background',
			array(
				'label'     => __( 'Background', 'bkx-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .bkx-booking-form' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			array(
				'name'     => 'form_border',
				'selector' => '{{WRAPPER}} .bkx-booking-form',
			)
		);

		$this->add_control(
			'form_border_radius',
			array(
				'label'      => __( 'Border Radius', 'bkx-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .bkx-booking-form' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Box_Shadow::get_type(),
			array(
				'name'     => 'form_box_shadow',
				'selector' => '{{WRAPPER}} .bkx-booking-form',
			)
		);

		$this->end_controls_section();

		// Style: Button Section.
		$this->start_controls_section(
			'section_style_button',
			array(
				'label' => __( 'Button', 'bkx-elementor' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'button_typography',
				'selector' => '{{WRAPPER}} .bkx-submit-button',
			)
		);

		$this->start_controls_tabs( 'button_style_tabs' );

		$this->start_controls_tab(
			'button_normal',
			array(
				'label' => __( 'Normal', 'bkx-elementor' ),
			)
		);

		$this->add_control(
			'button_color',
			array(
				'label'     => __( 'Text Color', 'bkx-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .bkx-submit-button' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_background',
			array(
				'label'     => __( 'Background', 'bkx-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .bkx-submit-button' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->start_controls_tab(
			'button_hover',
			array(
				'label' => __( 'Hover', 'bkx-elementor' ),
			)
		);

		$this->add_control(
			'button_hover_color',
			array(
				'label'     => __( 'Text Color', 'bkx-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .bkx-submit-button:hover' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'button_hover_background',
			array(
				'label'     => __( 'Background', 'bkx-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .bkx-submit-button:hover' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->add_responsive_control(
			'button_padding',
			array(
				'label'      => __( 'Padding', 'bkx-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'separator'  => 'before',
				'selectors'  => array(
					'{{WRAPPER}} .bkx-submit-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'button_border_radius',
			array(
				'label'      => __( 'Border Radius', 'bkx-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .bkx-submit-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$service_display   = $settings['service_display'];
		$show_staff        = 'yes' === $settings['show_staff_selection'];
		$show_extras       = 'yes' === $settings['show_extras'];
		$show_price        = 'yes' === $settings['show_price_summary'];
		$button_text       = $settings['button_text'];
		$calendar_style    = $settings['calendar_style'];
		$time_slot_display = $settings['time_slot_display'];

		// Get services.
		$services = get_posts( array(
			'post_type'      => 'bkx_base',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		// Get seats.
		$seats = get_posts( array(
			'post_type'      => 'bkx_seat',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		$seat_alias = get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-elementor' ) );
		?>
		<div class="bkx-booking-form bkx-elementor-booking"
			 data-calendar="<?php echo esc_attr( $calendar_style ); ?>"
			 data-timeslots="<?php echo esc_attr( $time_slot_display ); ?>">

			<form class="bkx-form" method="post">
				<?php wp_nonce_field( 'bkx_booking_form', 'bkx_booking_nonce' ); ?>

				<!-- Service Selection -->
				<?php if ( 'hidden' !== $service_display ) : ?>
					<div class="bkx-form-field bkx-service-field">
						<label><?php esc_html_e( 'Select Service', 'bkx-elementor' ); ?></label>

						<?php if ( 'dropdown' === $service_display ) : ?>
							<select name="service_id" class="bkx-service-select" required>
								<option value=""><?php esc_html_e( '-- Select a service --', 'bkx-elementor' ); ?></option>
								<?php foreach ( $services as $service ) : ?>
									<?php $price = get_post_meta( $service->ID, 'base_price', true ); ?>
									<option value="<?php echo esc_attr( $service->ID ); ?>"
											data-price="<?php echo esc_attr( $price ); ?>">
										<?php echo esc_html( $service->post_title ); ?>
										<?php if ( $price ) : ?>
											- $<?php echo esc_html( number_format( (float) $price, 2 ) ); ?>
										<?php endif; ?>
									</option>
								<?php endforeach; ?>
							</select>
						<?php elseif ( 'cards' === $service_display ) : ?>
							<div class="bkx-service-cards">
								<?php foreach ( $services as $service ) : ?>
									<?php
									$price    = get_post_meta( $service->ID, 'base_price', true );
									$duration = get_post_meta( $service->ID, 'base_time', true );
									?>
									<label class="bkx-service-card">
										<input type="radio" name="service_id" value="<?php echo esc_attr( $service->ID ); ?>"
											   data-price="<?php echo esc_attr( $price ); ?>" required>
										<?php if ( has_post_thumbnail( $service->ID ) ) : ?>
											<div class="service-image">
												<?php echo get_the_post_thumbnail( $service->ID, 'medium' ); ?>
											</div>
										<?php endif; ?>
										<div class="service-info">
											<span class="service-name"><?php echo esc_html( $service->post_title ); ?></span>
											<?php if ( $duration ) : ?>
												<span class="service-duration"><?php echo esc_html( $duration ); ?> min</span>
											<?php endif; ?>
											<?php if ( $price ) : ?>
												<span class="service-price">$<?php echo esc_html( number_format( (float) $price, 2 ) ); ?></span>
											<?php endif; ?>
										</div>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<input type="hidden" name="service_id" value="<?php echo esc_attr( $settings['selected_service'] ); ?>">
				<?php endif; ?>

				<!-- Staff Selection -->
				<?php if ( $show_staff && ! empty( $seats ) ) : ?>
					<div class="bkx-form-field bkx-staff-field">
						<label><?php echo esc_html( sprintf( __( 'Select %s', 'bkx-elementor' ), $seat_alias ) ); ?></label>
						<select name="seat_id" class="bkx-seat-select">
							<option value=""><?php esc_html_e( 'Any available', 'bkx-elementor' ); ?></option>
							<?php foreach ( $seats as $seat ) : ?>
								<option value="<?php echo esc_attr( $seat->ID ); ?>">
									<?php echo esc_html( $seat->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<!-- Date Selection -->
				<div class="bkx-form-field bkx-date-field">
					<label><?php esc_html_e( 'Select Date', 'bkx-elementor' ); ?></label>
					<?php if ( 'inline' === $calendar_style ) : ?>
						<div class="bkx-calendar-inline"></div>
					<?php else : ?>
						<input type="date" name="booking_date" class="bkx-date-input"
							   min="<?php echo esc_attr( gmdate( 'Y-m-d' ) ); ?>" required>
					<?php endif; ?>
					<input type="hidden" name="booking_date" class="bkx-date-value">
				</div>

				<!-- Time Selection -->
				<div class="bkx-form-field bkx-time-field" style="display: none;">
					<label><?php esc_html_e( 'Select Time', 'bkx-elementor' ); ?></label>
					<div class="bkx-time-slots bkx-time-<?php echo esc_attr( $time_slot_display ); ?>"></div>
					<input type="hidden" name="booking_time" class="bkx-time-value">
				</div>

				<!-- Customer Info -->
				<div class="bkx-form-field">
					<label><?php esc_html_e( 'Your Name', 'bkx-elementor' ); ?></label>
					<input type="text" name="customer_name" required>
				</div>

				<div class="bkx-form-field">
					<label><?php esc_html_e( 'Email Address', 'bkx-elementor' ); ?></label>
					<input type="email" name="customer_email" required>
				</div>

				<div class="bkx-form-field">
					<label><?php esc_html_e( 'Phone Number', 'bkx-elementor' ); ?></label>
					<input type="tel" name="customer_phone">
				</div>

				<!-- Price Summary -->
				<?php if ( $show_price ) : ?>
					<div class="bkx-price-summary">
						<div class="price-row total">
							<span class="label"><?php esc_html_e( 'Total', 'bkx-elementor' ); ?></span>
							<span class="value bkx-total-price">$0.00</span>
						</div>
					</div>
				<?php endif; ?>

				<!-- Submit Button -->
				<div class="bkx-form-field bkx-submit-field">
					<button type="submit" class="bkx-submit-button">
						<?php echo esc_html( $button_text ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Get services options for select control.
	 *
	 * @return array
	 */
	private function get_services_options() {
		$options  = array();
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
}
