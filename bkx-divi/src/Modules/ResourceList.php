<?php
/**
 * Resource List Divi Module.
 *
 * @package BookingX\Divi\Modules
 */

namespace BookingX\Divi\Modules;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'ET_Builder_Module' ) ) {
	return;
}

/**
 * ResourceList Module class.
 */
class ResourceList extends \ET_Builder_Module {

	/**
	 * Module slug.
	 *
	 * @var string
	 */
	public $slug = 'bkx_resource_list';

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
		$this->name = esc_html__( 'BKX Resource List', 'bkx-divi' );
		$this->icon = 'A';

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
		$addon     = \BookingX\Divi\DiviAddon::get_instance();
		$resources = $addon->get_resources();

		return array(
			'layout'           => array(
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
			'columns'          => array(
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
			'show_image'       => array(
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
			'show_bio'         => array(
				'label'           => esc_html__( 'Show Bio', 'bkx-divi' ),
				'type'            => 'yes_no_button',
				'option_category' => 'basic_option',
				'options'         => array(
					'on'  => esc_html__( 'Yes', 'bkx-divi' ),
					'off' => esc_html__( 'No', 'bkx-divi' ),
				),
				'default'         => 'on',
				'toggle_slug'     => 'display',
			),
			'show_services'    => array(
				'label'           => esc_html__( 'Show Services', 'bkx-divi' ),
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
			'filter_resources' => array(
				'label'           => esc_html__( 'Filter Resources', 'bkx-divi' ),
				'type'            => 'multiple_checkboxes',
				'option_category' => 'basic_option',
				'options'         => $resources,
				'toggle_slug'     => 'main_content',
				'description'     => esc_html__( 'Select resources to show. Leave empty for all.', 'bkx-divi' ),
			),
			'orderby'          => array(
				'label'           => esc_html__( 'Order By', 'bkx-divi' ),
				'type'            => 'select',
				'option_category' => 'basic_option',
				'options'         => array(
					'title'      => esc_html__( 'Name', 'bkx-divi' ),
					'date'       => esc_html__( 'Date', 'bkx-divi' ),
					'menu_order' => esc_html__( 'Menu Order', 'bkx-divi' ),
				),
				'default'         => 'title',
				'toggle_slug'     => 'main_content',
			),
			'order'            => array(
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
			'limit'            => array(
				'label'           => esc_html__( 'Limit', 'bkx-divi' ),
				'type'            => 'text',
				'option_category' => 'basic_option',
				'default'         => '-1',
				'toggle_slug'     => 'main_content',
				'description'     => esc_html__( 'Maximum number of resources to show (-1 for all).', 'bkx-divi' ),
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
		$show_bio         = 'on' === $this->props['show_bio'];
		$show_services    = 'on' === $this->props['show_services'];
		$show_book_button = 'on' === $this->props['show_book_button'];
		$orderby          = $this->props['orderby'];
		$order            = $this->props['order'];
		$limit            = intval( $this->props['limit'] );
		$book_button_text = $this->props['book_button_text'];

		$args = array(
			'post_type'      => 'bkx_seat',
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'orderby'        => $orderby,
			'order'          => $order,
		);

		$resources = get_posts( $args );

		if ( empty( $resources ) ) {
			return '<p class="bkx-no-resources">' . esc_html__( 'No resources found.', 'bkx-divi' ) . '</p>';
		}

		$classes = array(
			'bkx-divi-resource-list',
			'bkx-layout-' . $layout,
			'bkx-columns-' . $columns,
		);

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
			<?php foreach ( $resources as $resource ) : ?>
				<?php
				$image = get_the_post_thumbnail_url( $resource->ID, 'medium' );
				$booking_page = get_option( 'bkx_booking_page' );
				$book_url = $booking_page ? add_query_arg( 'resource_id', $resource->ID, get_permalink( $booking_page ) ) : '#';
				$seat_services = get_post_meta( $resource->ID, 'seat_base', true );
				?>
				<div class="bkx-resource-card">
					<?php if ( $show_image && $image ) : ?>
						<div class="bkx-resource-image">
							<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $resource->post_title ); ?>">
						</div>
					<?php endif; ?>

					<div class="bkx-resource-content">
						<h3 class="bkx-resource-title"><?php echo esc_html( $resource->post_title ); ?></h3>

						<?php if ( $show_bio && $resource->post_content ) : ?>
							<p class="bkx-resource-bio"><?php echo esc_html( wp_trim_words( $resource->post_content, 20 ) ); ?></p>
						<?php endif; ?>

						<?php if ( $show_services && ! empty( $seat_services ) ) : ?>
							<div class="bkx-resource-services">
								<?php
								$service_posts = get_posts(
									array(
										'post_type'      => 'bkx_base',
										'posts_per_page' => 5,
										'post__in'       => (array) $seat_services,
									)
								);
								foreach ( $service_posts as $service ) :
									?>
									<span class="bkx-service-tag"><?php echo esc_html( $service->post_title ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

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

new ResourceList();
