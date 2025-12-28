<?php
/**
 * Staff Carousel Elementor Widget.
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
 * StaffCarouselWidget Class.
 */
class StaffCarouselWidget extends Widget_Base {

	/**
	 * Get widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'bkx-staff-carousel';
	}

	/**
	 * Get widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		$alias = get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-elementor' ) );
		return sprintf( __( '%s Carousel', 'bkx-elementor' ), $alias );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-carousel';
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
		$this->start_controls_section(
			'section_content',
			array(
				'label' => __( 'Content', 'bkx-elementor' ),
			)
		);

		$this->add_control(
			'slides_to_show',
			array(
				'label'   => __( 'Slides to Show', 'bkx-elementor' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '3',
				'options' => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
				),
			)
		);

		$this->add_control(
			'autoplay',
			array(
				'label'        => __( 'Autoplay', 'bkx-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_navigation',
			array(
				'label'        => __( 'Show Navigation', 'bkx-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_bio',
			array(
				'label'        => __( 'Show Bio', 'bkx-elementor' ),
				'type'         => Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render widget.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		$seats = get_posts( array(
			'post_type'      => 'bkx_seat',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		if ( empty( $seats ) ) {
			echo '<p>' . esc_html__( 'No staff found.', 'bkx-elementor' ) . '</p>';
			return;
		}

		$alias = get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-elementor' ) );
		?>
		<div class="bkx-staff-carousel"
			 data-slides="<?php echo esc_attr( $settings['slides_to_show'] ); ?>"
			 data-autoplay="<?php echo esc_attr( $settings['autoplay'] ); ?>">

			<div class="carousel-track">
				<?php foreach ( $seats as $seat ) : ?>
					<div class="carousel-slide">
						<div class="staff-card">
							<div class="staff-image">
								<?php if ( has_post_thumbnail( $seat->ID ) ) : ?>
									<?php echo get_the_post_thumbnail( $seat->ID, 'medium' ); ?>
								<?php else : ?>
									<div class="placeholder-image"></div>
								<?php endif; ?>
							</div>

							<div class="staff-info">
								<h4 class="staff-name"><?php echo esc_html( $seat->post_title ); ?></h4>

								<?php
								$title = get_post_meta( $seat->ID, 'seat_title', true );
								if ( $title ) :
									?>
									<span class="staff-title"><?php echo esc_html( $title ); ?></span>
								<?php endif; ?>

								<?php if ( 'yes' === $settings['show_bio'] && $seat->post_excerpt ) : ?>
									<p class="staff-bio"><?php echo esc_html( $seat->post_excerpt ); ?></p>
								<?php endif; ?>

								<a href="<?php echo esc_url( get_permalink( $seat->ID ) ); ?>" class="staff-link">
									<?php esc_html_e( 'View Profile', 'bkx-elementor' ); ?>
								</a>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( 'yes' === $settings['show_navigation'] ) : ?>
				<div class="carousel-navigation">
					<button class="carousel-prev" aria-label="<?php esc_attr_e( 'Previous', 'bkx-elementor' ); ?>">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
					</button>
					<button class="carousel-next" aria-label="<?php esc_attr_e( 'Next', 'bkx-elementor' ); ?>">
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
