<?php
/**
 * Services Grid Elementor Widget.
 *
 * @package BookingX\Elementor\Widgets
 * @since   1.0.0
 */

namespace BookingX\Elementor\Widgets;

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Image_Size;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ServicesGridWidget Class.
 */
class ServicesGridWidget extends Widget_Base {

	/**
	 * Get widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'bkx-services-grid';
	}

	/**
	 * Get widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Services Grid', 'bkx-elementor' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-gallery-grid';
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

		$this->add_responsive_control(
			'columns',
			array(
				'label'          => __( 'Columns', 'bkx-elementor' ),
				'type'           => Controls_Manager::SELECT,
				'default'        => '3',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'options'        => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'selectors'      => array(
					'{{WRAPPER}} .bkx-services-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
				),
			)
		);

		$this->add_control(
			'posts_per_page',
			array(
				'label'   => __( 'Number of Services', 'bkx-elementor' ),
				'type'    => Controls_Manager::NUMBER,
				'default' => 6,
				'min'     => 1,
				'max'     => 50,
			)
		);

		$this->add_control(
			'show_image',
			array(
				'label'        => __( 'Show Image', 'bkx-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_price',
			array(
				'label'        => __( 'Show Price', 'bkx-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_duration',
			array(
				'label'        => __( 'Show Duration', 'bkx-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_description',
			array(
				'label'        => __( 'Show Description', 'bkx-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_book_button',
			array(
				'label'        => __( 'Show Book Button', 'bkx-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'     => __( 'Button Text', 'bkx-elementor' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'Book Now', 'bkx-elementor' ),
				'condition' => array(
					'show_book_button' => 'yes',
				),
			)
		);

		$this->end_controls_section();

		// Style Section.
		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Card Style', 'bkx-elementor' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => __( 'Gap', 'bkx-elementor' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array( 'min' => 0, 'max' => 100 ),
				),
				'default'    => array( 'size' => 30, 'unit' => 'px' ),
				'selectors'  => array(
					'{{WRAPPER}} .bkx-services-grid' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'card_background',
			array(
				'label'     => __( 'Background', 'bkx-elementor' ),
				'type'      => Controls_Manager::COLOR,
				'default'   => '#ffffff',
				'selectors' => array(
					'{{WRAPPER}} .bkx-service-card' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'card_padding',
			array(
				'label'      => __( 'Padding', 'bkx-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .bkx-service-card .card-content' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'card_border_radius',
			array(
				'label'      => __( 'Border Radius', 'bkx-elementor' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .bkx-service-card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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

		$services = get_posts( array(
			'post_type'      => 'bkx_base',
			'posts_per_page' => absint( $settings['posts_per_page'] ),
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		if ( empty( $services ) ) {
			echo '<p>' . esc_html__( 'No services found.', 'bkx-elementor' ) . '</p>';
			return;
		}
		?>
		<div class="bkx-services-grid">
			<?php foreach ( $services as $service ) : ?>
				<?php
				$price    = get_post_meta( $service->ID, 'base_price', true );
				$duration = get_post_meta( $service->ID, 'base_time', true );
				?>
				<div class="bkx-service-card">
					<?php if ( 'yes' === $settings['show_image'] && has_post_thumbnail( $service->ID ) ) : ?>
						<div class="card-image">
							<a href="<?php echo esc_url( get_permalink( $service->ID ) ); ?>">
								<?php echo get_the_post_thumbnail( $service->ID, 'medium' ); ?>
							</a>
						</div>
					<?php endif; ?>

					<div class="card-content">
						<h3 class="card-title">
							<a href="<?php echo esc_url( get_permalink( $service->ID ) ); ?>">
								<?php echo esc_html( $service->post_title ); ?>
							</a>
						</h3>

						<?php if ( 'yes' === $settings['show_description'] && $service->post_excerpt ) : ?>
							<div class="card-description">
								<?php echo wp_kses_post( $service->post_excerpt ); ?>
							</div>
						<?php endif; ?>

						<div class="card-meta">
							<?php if ( 'yes' === $settings['show_price'] && $price ) : ?>
								<span class="card-price">$<?php echo esc_html( number_format( (float) $price, 2 ) ); ?></span>
							<?php endif; ?>

							<?php if ( 'yes' === $settings['show_duration'] && $duration ) : ?>
								<span class="card-duration"><?php echo esc_html( $duration ); ?> min</span>
							<?php endif; ?>
						</div>

						<?php if ( 'yes' === $settings['show_book_button'] ) : ?>
							<a href="<?php echo esc_url( get_permalink( $service->ID ) ); ?>" class="card-button">
								<?php echo esc_html( $settings['button_text'] ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
