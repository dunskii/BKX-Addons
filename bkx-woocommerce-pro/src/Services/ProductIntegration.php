<?php
/**
 * Product Integration Service.
 *
 * Handles synchronization between BookingX services and WooCommerce products.
 *
 * @package BookingX\WooCommercePro\Services
 * @since   1.0.0
 */

namespace BookingX\WooCommercePro\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ProductIntegration Class.
 */
class ProductIntegration {

	/**
	 * Instance.
	 *
	 * @var ProductIntegration
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return ProductIntegration
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Sync service changes to products.
		add_action( 'save_post_bkx_base', array( $this, 'sync_service_to_product' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'delete_linked_product' ) );
		add_action( 'trashed_post', array( $this, 'trash_linked_product' ) );
		add_action( 'untrashed_post', array( $this, 'untrash_linked_product' ) );

		// Product data tabs.
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_data' ) );

		// Show/hide standard tabs based on product type.
		add_action( 'admin_footer', array( $this, 'product_type_js' ) );
	}

	/**
	 * Get product for a service.
	 *
	 * @param int $service_id Service ID.
	 * @return int|null Product ID or null.
	 */
	public function get_product_for_service( $service_id ) {
		global $wpdb;

		$product_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key = '_bkx_service_id' AND meta_value = %d
			LIMIT 1",
			$service_id
		) );

		return $product_id ? absint( $product_id ) : null;
	}

	/**
	 * Get service for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return int|null Service ID or null.
	 */
	public function get_service_for_product( $product_id ) {
		$service_id = get_post_meta( $product_id, '_bkx_service_id', true );
		return $service_id ? absint( $service_id ) : null;
	}

	/**
	 * Create WooCommerce product from BookingX service.
	 *
	 * @param int   $service_id Service ID.
	 * @param array $args       Additional arguments.
	 * @return int|false Product ID or false on failure.
	 */
	public function create_product_from_service( $service_id, $args = array() ) {
		$service = get_post( $service_id );

		if ( ! $service || 'bkx_base' !== $service->post_type ) {
			return false;
		}

		// Check if product already exists.
		$existing = $this->get_product_for_service( $service_id );
		if ( $existing ) {
			return $existing;
		}

		// Get service data.
		$price    = get_post_meta( $service_id, 'base_price', true );
		$duration = get_post_meta( $service_id, 'base_time', true );
		$image_id = get_post_thumbnail_id( $service_id );

		// Create product.
		$product = new \WC_Product();
		$product->set_name( $service->post_title );
		$product->set_description( $service->post_content );
		$product->set_short_description( $service->post_excerpt );
		$product->set_status( $service->post_status );
		$product->set_catalog_visibility( 'visible' );
		$product->set_regular_price( $price );
		$product->set_virtual( true );
		$product->set_sold_individually( true );

		if ( $image_id ) {
			$product->set_image_id( $image_id );
		}

		// Save basic product first.
		$product_id = $product->save();

		if ( ! $product_id ) {
			return false;
		}

		// Convert to booking product type.
		wp_set_object_terms( $product_id, 'bkx_booking', 'product_type' );

		// Set booking meta.
		update_post_meta( $product_id, '_bkx_service_id', $service_id );
		update_post_meta( $product_id, '_bkx_duration', $duration );
		update_post_meta( $product_id, '_bkx_requires_date', 'yes' );
		update_post_meta( $product_id, '_bkx_requires_seat', 'yes' );
		update_post_meta( $product_id, '_bkx_sold_individually', 'yes' );

		// Link service to product.
		update_post_meta( $service_id, '_bkx_woo_product_id', $product_id );

		// Handle extras as linked products.
		if ( ! empty( $args['sync_extras'] ) ) {
			$this->sync_extras_to_products( $service_id, $product_id );
		}

		do_action( 'bkx_woo_product_created', $product_id, $service_id );

		return $product_id;
	}

	/**
	 * Update product from service.
	 *
	 * @param int $service_id Service ID.
	 * @return bool
	 */
	public function update_product_from_service( $service_id ) {
		$product_id = $this->get_product_for_service( $service_id );

		if ( ! $product_id ) {
			return false;
		}

		$service = get_post( $service_id );
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return false;
		}

		// Update product data.
		$product->set_name( $service->post_title );
		$product->set_description( $service->post_content );
		$product->set_short_description( $service->post_excerpt );
		$product->set_status( $service->post_status );

		// Update price.
		$price = get_post_meta( $service_id, 'base_price', true );
		$product->set_regular_price( $price );

		// Update duration.
		$duration = get_post_meta( $service_id, 'base_time', true );
		$product->update_meta_data( '_bkx_duration', $duration );

		// Update image.
		$image_id = get_post_thumbnail_id( $service_id );
		if ( $image_id ) {
			$product->set_image_id( $image_id );
		}

		$product->save();

		do_action( 'bkx_woo_product_updated', $product_id, $service_id );

		return true;
	}

	/**
	 * Sync service to product on save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function sync_service_to_product( $post_id, $post ) {
		// Verify not autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check post type.
		if ( 'bkx_base' !== $post->post_type ) {
			return;
		}

		$settings = get_option( 'bkx_woocommerce_settings', array() );

		// Check if sync is enabled.
		if ( empty( $settings['sync_inventory'] ) ) {
			return;
		}

		// Update or create product.
		$product_id = $this->get_product_for_service( $post_id );

		if ( $product_id ) {
			$this->update_product_from_service( $post_id );
		} elseif ( ! empty( $settings['auto_create_products'] ) ) {
			$this->create_product_from_service( $post_id );
		}
	}

	/**
	 * Delete linked product when service is deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public function delete_linked_product( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || 'bkx_base' !== $post->post_type ) {
			return;
		}

		$product_id = $this->get_product_for_service( $post_id );

		if ( $product_id ) {
			wp_delete_post( $product_id, true );
		}
	}

	/**
	 * Trash linked product.
	 *
	 * @param int $post_id Post ID.
	 */
	public function trash_linked_product( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || 'bkx_base' !== $post->post_type ) {
			return;
		}

		$product_id = $this->get_product_for_service( $post_id );

		if ( $product_id ) {
			wp_trash_post( $product_id );
		}
	}

	/**
	 * Untrash linked product.
	 *
	 * @param int $post_id Post ID.
	 */
	public function untrash_linked_product( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post || 'bkx_base' !== $post->post_type ) {
			return;
		}

		$product_id = $this->get_product_for_service( $post_id );

		if ( $product_id ) {
			wp_untrash_post( $product_id );
		}
	}

	/**
	 * Add booking data tab to product edit.
	 *
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public function add_product_data_tab( $tabs ) {
		$tabs['bkx_booking'] = array(
			'label'    => __( 'Booking Settings', 'bkx-woocommerce-pro' ),
			'target'   => 'bkx_booking_data',
			'class'    => array( 'show_if_bkx_booking' ),
			'priority' => 25,
		);

		return $tabs;
	}

	/**
	 * Render booking data panel.
	 */
	public function render_product_data_panel() {
		global $post;

		$product    = wc_get_product( $post->ID );
		$service_id = $product ? $product->get_meta( '_bkx_service_id' ) : '';
		$seat_id    = $product ? $product->get_meta( '_bkx_seat_id' ) : '';
		$duration   = $product ? $product->get_meta( '_bkx_duration' ) : '';

		// Get services.
		$services = get_posts( array(
			'post_type'      => 'bkx_base',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		// Get seats.
		$seats = get_posts( array(
			'post_type'      => 'bkx_seat',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		?>
		<div id="bkx_booking_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<p class="form-field">
					<label for="_bkx_service_id"><?php esc_html_e( 'Linked Service', 'bkx-woocommerce-pro' ); ?></label>
					<select name="_bkx_service_id" id="_bkx_service_id" class="wc-enhanced-select" style="width: 50%;">
						<option value=""><?php esc_html_e( 'Select a service...', 'bkx-woocommerce-pro' ); ?></option>
						<?php foreach ( $services as $service ) : ?>
							<option value="<?php echo esc_attr( $service->ID ); ?>" <?php selected( $service_id, $service->ID ); ?>>
								<?php echo esc_html( $service->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php echo wc_help_tip( __( 'Link this product to a BookingX service.', 'bkx-woocommerce-pro' ) ); ?>
				</p>

				<p class="form-field">
					<label for="_bkx_seat_id"><?php esc_html_e( 'Default Resource', 'bkx-woocommerce-pro' ); ?></label>
					<select name="_bkx_seat_id" id="_bkx_seat_id" class="wc-enhanced-select" style="width: 50%;">
						<option value=""><?php esc_html_e( 'Customer selects...', 'bkx-woocommerce-pro' ); ?></option>
						<?php foreach ( $seats as $seat ) : ?>
							<option value="<?php echo esc_attr( $seat->ID ); ?>" <?php selected( $seat_id, $seat->ID ); ?>>
								<?php echo esc_html( $seat->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php echo wc_help_tip( __( 'Optionally pre-select a resource/staff member.', 'bkx-woocommerce-pro' ) ); ?>
				</p>

				<?php
				woocommerce_wp_text_input( array(
					'id'                => '_bkx_duration',
					'label'             => __( 'Duration (minutes)', 'bkx-woocommerce-pro' ),
					'desc_tip'          => true,
					'description'       => __( 'Override service duration if needed.', 'bkx-woocommerce-pro' ),
					'type'              => 'number',
					'custom_attributes' => array( 'min' => 0, 'step' => 5 ),
					'value'             => $duration,
				) );

				woocommerce_wp_checkbox( array(
					'id'          => '_bkx_requires_date',
					'label'       => __( 'Requires Date Selection', 'bkx-woocommerce-pro' ),
					'description' => __( 'Customer must select date/time before checkout.', 'bkx-woocommerce-pro' ),
					'value'       => $product ? $product->get_meta( '_bkx_requires_date' ) : 'yes',
					'cbvalue'     => 'yes',
				) );

				woocommerce_wp_checkbox( array(
					'id'          => '_bkx_requires_seat',
					'label'       => __( 'Requires Resource Selection', 'bkx-woocommerce-pro' ),
					'description' => __( 'Customer must select resource/staff if not pre-set.', 'bkx-woocommerce-pro' ),
					'value'       => $product ? $product->get_meta( '_bkx_requires_seat' ) : 'yes',
					'cbvalue'     => 'yes',
				) );

				woocommerce_wp_checkbox( array(
					'id'          => '_bkx_sold_individually',
					'label'       => __( 'Sold Individually', 'bkx-woocommerce-pro' ),
					'description' => __( 'Only allow one booking per cart.', 'bkx-woocommerce-pro' ),
					'value'       => $product ? $product->get_meta( '_bkx_sold_individually' ) : 'yes',
					'cbvalue'     => 'yes',
				) );
				?>
			</div>

			<div class="options_group">
				<p class="form-field">
					<label><?php esc_html_e( 'Allowed Extras', 'bkx-woocommerce-pro' ); ?></label>
					<?php
					$extras         = get_posts( array(
						'post_type'      => 'bkx_addition',
						'posts_per_page' => -1,
						'orderby'        => 'title',
						'order'          => 'ASC',
					) );
					$allowed_extras = $product ? $product->get_meta( '_bkx_allowed_extras' ) : array();
					$allowed_extras = is_array( $allowed_extras ) ? $allowed_extras : array();

					foreach ( $extras as $extra ) :
						?>
						<label style="display: block; margin-left: 150px;">
							<input type="checkbox" name="_bkx_allowed_extras[]" value="<?php echo esc_attr( $extra->ID ); ?>"
								<?php checked( in_array( $extra->ID, $allowed_extras, true ) ); ?>>
							<?php echo esc_html( $extra->post_title ); ?>
							(<?php echo esc_html( wc_price( get_post_meta( $extra->ID, 'addition_price', true ) ) ); ?>)
						</label>
					<?php endforeach; ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Save product booking data.
	 *
	 * @param int $product_id Product ID.
	 */
	public function save_product_data( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product || 'bkx_booking' !== $product->get_type() ) {
			return;
		}

		// Service ID.
		if ( isset( $_POST['_bkx_service_id'] ) ) {
			$product->update_meta_data( '_bkx_service_id', absint( $_POST['_bkx_service_id'] ) );
		}

		// Seat ID.
		if ( isset( $_POST['_bkx_seat_id'] ) ) {
			$product->update_meta_data( '_bkx_seat_id', absint( $_POST['_bkx_seat_id'] ) );
		}

		// Duration.
		if ( isset( $_POST['_bkx_duration'] ) ) {
			$product->update_meta_data( '_bkx_duration', absint( $_POST['_bkx_duration'] ) );
		}

		// Checkboxes.
		$checkboxes = array( '_bkx_requires_date', '_bkx_requires_seat', '_bkx_sold_individually' );
		foreach ( $checkboxes as $checkbox ) {
			$product->update_meta_data( $checkbox, isset( $_POST[ $checkbox ] ) ? 'yes' : 'no' );
		}

		// Allowed extras.
		$allowed_extras = isset( $_POST['_bkx_allowed_extras'] ) ? array_map( 'absint', (array) $_POST['_bkx_allowed_extras'] ) : array();
		$product->update_meta_data( '_bkx_allowed_extras', $allowed_extras );

		$product->save();
	}

	/**
	 * Add JS to show/hide tabs based on product type.
	 */
	public function product_type_js() {
		global $post;

		if ( ! $post || 'product' !== $post->post_type ) {
			return;
		}
		?>
		<script type="text/javascript">
		jQuery(function($) {
			// Show/hide tabs based on product type.
			$('select#product-type').on('change', function() {
				var type = $(this).val();

				if (type === 'bkx_booking') {
					$('.show_if_simple').hide();
					$('.show_if_external').hide();
					$('.show_if_grouped').hide();
					$('.show_if_variable').hide();
					$('.show_if_bkx_booking').show();

					// Virtual products.
					$('#_virtual').prop('checked', true).change();

					// Hide inventory tab.
					$('.inventory_options').hide();
				} else {
					$('.show_if_bkx_booking').hide();
				}
			}).trigger('change');

			// Trigger on load.
			$('select#product-type').trigger('change');
		});
		</script>
		<?php
	}

	/**
	 * Sync extras to linked products.
	 *
	 * @param int $service_id Service ID.
	 * @param int $product_id Product ID.
	 */
	private function sync_extras_to_products( $service_id, $product_id ) {
		// Get extras assigned to service.
		$extras = get_post_meta( $service_id, '_bkx_extras', true );

		if ( empty( $extras ) || ! is_array( $extras ) ) {
			return;
		}

		// Create cross-sells from extras.
		$cross_sell_ids = array();

		foreach ( $extras as $extra_id ) {
			$extra = get_post( $extra_id );

			if ( ! $extra || 'bkx_addition' !== $extra->post_type ) {
				continue;
			}

			// Check if extra already has a product.
			$extra_product_id = get_post_meta( $extra_id, '_bkx_woo_product_id', true );

			if ( ! $extra_product_id ) {
				// Create simple product for extra.
				$extra_product = new \WC_Product_Simple();
				$extra_product->set_name( $extra->post_title );
				$extra_product->set_description( $extra->post_content );
				$extra_product->set_status( 'publish' );
				$extra_product->set_virtual( true );
				$extra_product->set_regular_price( get_post_meta( $extra_id, 'addition_price', true ) );
				$extra_product->set_catalog_visibility( 'hidden' );

				$extra_product_id = $extra_product->save();
				update_post_meta( $extra_id, '_bkx_woo_product_id', $extra_product_id );
			}

			$cross_sell_ids[] = $extra_product_id;
		}

		if ( ! empty( $cross_sell_ids ) ) {
			update_post_meta( $product_id, '_crosssell_ids', $cross_sell_ids );
		}
	}

	/**
	 * Get all booking products.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all_booking_products( $args = array() ) {
		$defaults = array(
			'status'  => 'publish',
			'limit'   => -1,
			'orderby' => 'title',
			'order'   => 'ASC',
			'type'    => 'bkx_booking',
		);

		$args     = wp_parse_args( $args, $defaults );
		$products = wc_get_products( $args );
		$result   = array();

		foreach ( $products as $product ) {
			$service = $product->get_linked_service();

			$result[] = array(
				'id'           => $product->get_id(),
				'name'         => $product->get_name(),
				'price'        => $product->get_price(),
				'price_html'   => $product->get_price_html(),
				'duration'     => $product->get_booking_duration(),
				'service_id'   => $product->get_linked_service_id(),
				'service_name' => $service ? $service->post_title : '',
				'seat_id'      => $product->get_linked_seat_id(),
				'permalink'    => $product->get_permalink(),
			);
		}

		return $result;
	}
}
