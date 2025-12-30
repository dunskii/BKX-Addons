<?php
/**
 * Service List Divi Module.
 *
 * @package BookingX\Divi\Modules
 */

namespace BookingX\Divi\Modules;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'ET_Builder_Module' ) ) {
	return;
}

/**
 * ServiceList Module class.
 */
class ServiceList extends \ET_Builder_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	public $slug = 'bkx_service_list';

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
		$this->name = esc_html__( 'BKX Service List', 'bkx-divi' );
		$this->icon = 'C';

		$this->settings_modal_toggles = array(
			'general'  => array(
				'toggles' => array(
					'main_content' => esc_html__( 'Content', 'bkx-divi' ),
					'display'      => esc_html__( 'Display Options', 'bkx-divi' ),
				),
			),
			'advanced' => array(
				'toggles' => array(
					'card_style' => esc_html__( 'Card Style', 'bkx-divi' ),
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

		return array(
			'layout'          => array(
				'label'           => esc_html__( 'Layout', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array(
					'grid'     => esc_html__( 'Grid', 'bkx-divi' ),
					'list'     => esc_html__( 'List', 'bkx-divi' ),
					'carousel' => esc_html__( 'Carousel', 'bkx-divi' ),
				),
				'default'         => 'grid',
				'toggle_slug'     => 'main_content',
			),
			'columns'         => array(
				'label'           => esc_html__( 'Columns', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'default'         => '3',
				'toggle_slug'     => 'main_content',
				'show_if'         => array(
					'layout' => array( 'grid' ),
				),
			),
			'show_image'      => array(
				'label'           => esc_html__( 'Show Image', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'display',
			),
			'show_price'      => array(
				'label'           => esc_html__( 'Show Price', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'display',
			),
			'show_duration'   => array(
				'label'           => esc_html__( 'Show Duration', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'display',
			),
			'show_description' => array(
				'label'           => esc_html__( 'Show Description', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'display',
			),
			'show_book_button' => array(
				'label'           => esc_html__( 'Show Book Button', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'display',
			),
			'filter_services' => array(
				'label'           => esc_html__( 'Filter Services', 'bkx-divi' ),
				'type'            => 'multiple_checkboxes',
				'option_category' => 'basic_option',
				'options'         => $services,
				'toggle_slug'     => 'main_content',
				'description'     => esc_html__( 'Select services to show. Leave empty for all.', 'bkx-divi' ),
			),
			'orderby'         => array(
				'label'           => esc_html__( 'Order By', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array(
					'title'      => esc_html__( 'Title', 'bkx-divi' ),
					'date'       => esc_html__( 'Date', 'bkx-divi' ),
					'menu_order' => esc_html__( 'Menu Order', 'bkx-divi' ),
					'price'      => esc_html__( 'Price', 'bkx-divi' ),
				),
				'default'         => 'title',
				'toggle_slug'     => 'main_content',
			),
			'order'           => array(
				'label'           => esc_html__( 'Order', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array(
					'ASC'  => esc_html__( 'Ascending', 'bkx-divi' ),
					'DESC' => esc_html__( 'Descending', 'bkx-divi' ),
				),
				'default'         => 'ASC',
				'toggle_slug'     => 'main_content',
			),
			'limit'           => array(
				'label'           => esc_html__( 'Limit', 'bkx-divi' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'default'         => '-1',
				'toggle_slug'     => 'main_content',
				'description'     => esc_html__( 'Maximum number of services to show (-1 for all).', 'bkx-divi' ),
			),
			'book_button_text' => array(
				'label'           => esc_html__( 'Book Button Text', 'bkx-divi' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'default'         => esc_html__( 'Book Now', 'bkx-divi' ),
				'toggle_slug'     => 'display',
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
		$layout           = $this->props['layout'];
		$columns          = $this->props['columns'];
		$show_image       = 'on' === $this->props['show_image'];
		$show_price       = 'on' === $this->props['show_price'];
		$show_duration    = 'on' === $this->props['show_duration'];
		$show_description = 'on' === $this->props['show_description'];
		$show_book_button = 'on' === $this->props['show_book_button'];
		$orderby          = $this->props['orderby'];
		$order            = $this->props['order'];
		$limit            = intval( $this->props['limit'] );
		$book_button_text = $this->props['book_button_text'];

		$args = array(
			'post_type'      => 'bkx_base',
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'orderby'        => $orderby,
			'order'          => $order,
		);

		if ( 'price' === $orderby ) {
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = 'base_price';
		}

		$services = get_posts( $args );

		if ( empty( $services ) ) {
			return '<p class="bkx-no-services">' . esc_html__( 'No services found.', 'bkx-divi' ) . '</p>';
		}

		$classes = array(
			'bkx-divi-service-list',
			'bkx-layout-' . $layout,
			'bkx-columns-' . $columns,
		);

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php foreach ( $services as $service ) : ?>
				<?php
				$price    = get_post_meta( $service->ID, 'base_price', true );
				$duration = get_post_meta( $service->ID, 'base_time', true );
				$image    = get_the_post_thumbnail_url( $service->ID, 'medium' );
				$booking_page = get_option( 'bkx_booking_page' );
				$book_url = $booking_page ? add_query_arg( 'service_id', $service->ID, get_permalink( $booking_page ) ) : '#';
				?>
				<div class="bkx-service-card">
					<?php if ( $show_image && $image ) : ?>
						<div class="bkx-service-image">
							<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $service->post_title ); ?>">
						</div>
					<?php endif; ?>

					<div class="bkx-service-content">
						<h3 class="bkx-service-title"><?php echo esc_html( $service->post_title ); ?></h3>

						<?php if ( $show_description && $service->post_excerpt ) : ?>
							<p class="bkx-service-description"><?php echo esc_html( $service->post_excerpt ); ?></p>
						<?php endif; ?>

						<div class="bkx-service-meta">
							<?php if ( $show_price && $price ) : ?>
								<span class="bkx-service-price"><?php echo esc_html( wc_price( $price ) ); ?></span>
							<?php endif; ?>

							<?php if ( $show_duration && $duration ) : ?>
								<span class="bkx-service-duration">
									<span class="dashicons dashicons-clock"></span>
									<?php echo esc_html( $duration ); ?> <?php esc_html_e( 'min', 'bkx-divi' ); ?>
								</span>
							<?php endif; ?>
						</div>

						<?php if ( $show_book_button ) : ?>
							<a href="<?php echo esc_url( $book_url ); ?>" class="bkx-book-button">
								<?php echo esc_html( $book_button_text ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php

		return ob_get_clean();
	}
}

new ServiceList();
