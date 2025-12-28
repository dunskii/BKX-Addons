<?php
/**
 * Cart Service.
 *
 * Handles adding bookings to the WooCommerce cart and cart data management.
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
 * CartService Class.
 */
class CartService {

	/**
	 * Instance.
	 *
	 * @var CartService
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return CartService
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
		// Cart item data.
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );

		// Cart item pricing.
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'recalculate_cart_item_price' ), 20 );

		// Validate cart items.
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 5 );

		// Cart item removal.
		add_action( 'woocommerce_remove_cart_item', array( $this, 'on_cart_item_removed' ), 10, 2 );

		// Cart item name.
		add_filter( 'woocommerce_cart_item_name', array( $this, 'modify_cart_item_name' ), 10, 3 );

		// Cart item thumbnail.
		add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'modify_cart_item_thumbnail' ), 10, 3 );

		// Quantity disabled for booking products.
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'disable_quantity_change' ), 10, 3 );

		// Add to cart redirect.
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'add_to_cart_redirect' ), 10, 2 );
	}

	/**
	 * Add booking to cart.
	 *
	 * @param array $booking_data Booking data.
	 * @return array|\WP_Error Result with cart_key or error.
	 */
	public function add_booking_to_cart( $booking_data ) {
		$service_id   = isset( $booking_data['service_id'] ) ? absint( $booking_data['service_id'] ) : 0;
		$seat_id      = isset( $booking_data['seat_id'] ) ? absint( $booking_data['seat_id'] ) : 0;
		$booking_date = isset( $booking_data['booking_date'] ) ? sanitize_text_field( $booking_data['booking_date'] ) : '';
		$booking_time = isset( $booking_data['booking_time'] ) ? sanitize_text_field( $booking_data['booking_time'] ) : '';
		$extras       = isset( $booking_data['extras'] ) ? array_map( 'absint', (array) $booking_data['extras'] ) : array();

		// Validate required fields.
		if ( ! $service_id ) {
			return new \WP_Error( 'missing_service', __( 'Please select a service.', 'bkx-woocommerce-pro' ) );
		}

		// Get or create product.
		$product_integration = ProductIntegration::get_instance();
		$product_id          = $product_integration->get_product_for_service( $service_id );

		if ( ! $product_id ) {
			$product_id = $product_integration->create_product_from_service( $service_id );
		}

		if ( ! $product_id ) {
			return new \WP_Error( 'no_product', __( 'Unable to create booking product.', 'bkx-woocommerce-pro' ) );
		}

		$product = wc_get_product( $product_id );

		if ( ! $product || ! $product->is_purchasable() ) {
			return new \WP_Error( 'not_purchasable', __( 'This service is not available for booking.', 'bkx-woocommerce-pro' ) );
		}

		// Validate date/time.
		if ( $product->requires_date_selection() && ( empty( $booking_date ) || empty( $booking_time ) ) ) {
			return new \WP_Error( 'missing_datetime', __( 'Please select a date and time.', 'bkx-woocommerce-pro' ) );
		}

		// Validate availability.
		if ( ! empty( $booking_date ) && ! empty( $booking_time ) ) {
			$availability = $this->check_availability( $service_id, $seat_id, $booking_date, $booking_time );

			if ( ! $availability['available'] ) {
				return new \WP_Error( 'not_available', $availability['message'] );
			}
		}

		// Calculate extras price.
		$extras_data  = array();
		$extras_price = 0;

		foreach ( $extras as $extra_id ) {
			$extra = get_post( $extra_id );
			if ( $extra && 'bkx_addition' === $extra->post_type ) {
				$extra_price    = floatval( get_post_meta( $extra_id, 'addition_price', true ) );
				$extras_data[]  = array(
					'id'    => $extra_id,
					'name'  => $extra->post_title,
					'price' => $extra_price,
				);
				$extras_price += $extra_price;
			}
		}

		// Build cart item data.
		$cart_item_data = array(
			'bkx_booking_data' => array(
				'service_id'    => $service_id,
				'service_name'  => get_the_title( $service_id ),
				'seat_id'       => $seat_id,
				'seat_name'     => $seat_id ? get_the_title( $seat_id ) : '',
				'booking_date'  => $booking_date,
				'booking_time'  => $booking_time,
				'extras'        => $extras_data,
				'extras_price'  => $extras_price,
				'customer_data' => isset( $booking_data['customer_data'] ) ? $booking_data['customer_data'] : array(),
				'created_at'    => current_time( 'mysql' ),
			),
		);

		// Add unique key for this booking.
		$cart_item_data['unique_key'] = md5( wp_json_encode( $cart_item_data ) . time() );

		// Clear existing similar items if sold individually.
		if ( $product->is_sold_individually() ) {
			$this->remove_similar_cart_items( $product_id );
		}

		// Add to cart.
		$cart_key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );

		if ( ! $cart_key ) {
			return new \WP_Error( 'add_to_cart_failed', __( 'Failed to add booking to cart.', 'bkx-woocommerce-pro' ) );
		}

		return array(
			'cart_key'   => $cart_key,
			'product_id' => $product_id,
		);
	}

	/**
	 * Add booking data to cart item.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id     Product ID.
	 * @param int   $variation_id   Variation ID.
	 * @return array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		// Check if booking data was passed via POST (form submission).
		if ( isset( $_POST['bkx_booking_date'] ) && isset( $_POST['bkx_booking_time'] ) ) {
			$product = wc_get_product( $product_id );

			if ( $product && 'bkx_booking' === $product->get_type() ) {
				$cart_item_data['bkx_booking_data'] = array(
					'service_id'   => $product->get_linked_service_id(),
					'service_name' => $product->get_name(),
					'seat_id'      => isset( $_POST['bkx_seat_id'] ) ? absint( $_POST['bkx_seat_id'] ) : $product->get_linked_seat_id(),
					'seat_name'    => '',
					'booking_date' => sanitize_text_field( wp_unslash( $_POST['bkx_booking_date'] ) ),
					'booking_time' => sanitize_text_field( wp_unslash( $_POST['bkx_booking_time'] ) ),
					'extras'       => array(),
					'extras_price' => 0,
					'created_at'   => current_time( 'mysql' ),
				);

				// Get seat name.
				if ( ! empty( $cart_item_data['bkx_booking_data']['seat_id'] ) ) {
					$cart_item_data['bkx_booking_data']['seat_name'] = get_the_title( $cart_item_data['bkx_booking_data']['seat_id'] );
				}

				// Handle extras.
				if ( isset( $_POST['bkx_extras'] ) && is_array( $_POST['bkx_extras'] ) ) {
					$extras_data  = array();
					$extras_price = 0;

					foreach ( array_map( 'absint', $_POST['bkx_extras'] ) as $extra_id ) {
						$extra = get_post( $extra_id );
						if ( $extra ) {
							$extra_price    = floatval( get_post_meta( $extra_id, 'addition_price', true ) );
							$extras_data[]  = array(
								'id'    => $extra_id,
								'name'  => $extra->post_title,
								'price' => $extra_price,
							);
							$extras_price += $extra_price;
						}
					}

					$cart_item_data['bkx_booking_data']['extras']       = $extras_data;
					$cart_item_data['bkx_booking_data']['extras_price'] = $extras_price;
				}

				$cart_item_data['unique_key'] = md5( wp_json_encode( $cart_item_data ) . time() );
			}
		}

		return $cart_item_data;
	}

	/**
	 * Restore booking data from session.
	 *
	 * @param array $cart_item      Cart item.
	 * @param array $cart_item_data Session data.
	 * @return array
	 */
	public function get_cart_item_from_session( $cart_item, $cart_item_data ) {
		if ( isset( $cart_item_data['bkx_booking_data'] ) ) {
			$cart_item['bkx_booking_data'] = $cart_item_data['bkx_booking_data'];
		}

		if ( isset( $cart_item_data['unique_key'] ) ) {
			$cart_item['unique_key'] = $cart_item_data['unique_key'];
		}

		return $cart_item;
	}

	/**
	 * Display booking data in cart.
	 *
	 * @param array $item_data Item data to display.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['bkx_booking_data'] ) ) {
			return $item_data;
		}

		$booking_data = $cart_item['bkx_booking_data'];

		// Date.
		if ( ! empty( $booking_data['booking_date'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Date', 'bkx-woocommerce-pro' ),
				'value' => date_i18n( get_option( 'date_format' ), strtotime( $booking_data['booking_date'] ) ),
			);
		}

		// Time.
		if ( ! empty( $booking_data['booking_time'] ) ) {
			$item_data[] = array(
				'key'   => __( 'Time', 'bkx-woocommerce-pro' ),
				'value' => date_i18n( get_option( 'time_format' ), strtotime( $booking_data['booking_time'] ) ),
			);
		}

		// Resource/Staff.
		if ( ! empty( $booking_data['seat_name'] ) ) {
			$seat_alias = get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-woocommerce-pro' ) );
			$item_data[] = array(
				'key'   => $seat_alias,
				'value' => $booking_data['seat_name'],
			);
		}

		// Extras.
		if ( ! empty( $booking_data['extras'] ) ) {
			$extras_list = array();
			foreach ( $booking_data['extras'] as $extra ) {
				$extras_list[] = $extra['name'] . ' (+' . wc_price( $extra['price'] ) . ')';
			}

			$item_data[] = array(
				'key'   => __( 'Extras', 'bkx-woocommerce-pro' ),
				'value' => implode( ', ', $extras_list ),
			);
		}

		return $item_data;
	}

	/**
	 * Recalculate cart item price including extras.
	 *
	 * @param \WC_Cart $cart Cart object.
	 */
	public function recalculate_cart_item_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_key => $cart_item ) {
			if ( empty( $cart_item['bkx_booking_data'] ) ) {
				continue;
			}

			$booking_data = $cart_item['bkx_booking_data'];
			$base_price   = floatval( $cart_item['data']->get_price() );
			$extras_price = isset( $booking_data['extras_price'] ) ? floatval( $booking_data['extras_price'] ) : 0;

			$total_price = $base_price + $extras_price;

			$cart_item['data']->set_price( $total_price );
		}
	}

	/**
	 * Validate add to cart.
	 *
	 * @param bool $passed     Validation result.
	 * @param int  $product_id Product ID.
	 * @param int  $quantity   Quantity.
	 * @param int  $variation_id Variation ID.
	 * @param array $variations Variations.
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0, $variations = array() ) {
		$product = wc_get_product( $product_id );

		if ( ! $product || 'bkx_booking' !== $product->get_type() ) {
			return $passed;
		}

		// Check if date/time required but not provided.
		if ( $product->requires_date_selection() ) {
			if ( empty( $_POST['bkx_booking_date'] ) || empty( $_POST['bkx_booking_time'] ) ) {
				wc_add_notice( __( 'Please select a date and time for your booking.', 'bkx-woocommerce-pro' ), 'error' );
				return false;
			}

			// Validate availability.
			$booking_date = sanitize_text_field( wp_unslash( $_POST['bkx_booking_date'] ) );
			$booking_time = sanitize_text_field( wp_unslash( $_POST['bkx_booking_time'] ) );
			$seat_id      = isset( $_POST['bkx_seat_id'] ) ? absint( $_POST['bkx_seat_id'] ) : $product->get_linked_seat_id();

			$availability = $this->check_availability(
				$product->get_linked_service_id(),
				$seat_id,
				$booking_date,
				$booking_time
			);

			if ( ! $availability['available'] ) {
				wc_add_notice( $availability['message'], 'error' );
				return false;
			}
		}

		// Check seat selection.
		if ( $product->requires_seat_selection() && ! $product->get_linked_seat_id() ) {
			if ( empty( $_POST['bkx_seat_id'] ) ) {
				wc_add_notice( __( 'Please select a resource for your booking.', 'bkx-woocommerce-pro' ), 'error' );
				return false;
			}
		}

		return $passed;
	}

	/**
	 * Handle cart item removal.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param \WC_Cart $cart        Cart object.
	 */
	public function on_cart_item_removed( $cart_item_key, $cart ) {
		$cart_item = $cart->get_cart_item( $cart_item_key );

		if ( ! empty( $cart_item['bkx_booking_data'] ) ) {
			// Release any held availability slot.
			$booking_data = $cart_item['bkx_booking_data'];

			do_action( 'bkx_woo_cart_booking_removed', $booking_data, $cart_item_key );
		}
	}

	/**
	 * Modify cart item name to include booking details.
	 *
	 * @param string $name      Item name.
	 * @param array  $cart_item Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function modify_cart_item_name( $name, $cart_item, $cart_item_key ) {
		if ( empty( $cart_item['bkx_booking_data'] ) ) {
			return $name;
		}

		$booking_data = $cart_item['bkx_booking_data'];

		if ( ! empty( $booking_data['booking_date'] ) && ! empty( $booking_data['booking_time'] ) ) {
			$date = date_i18n( get_option( 'date_format' ), strtotime( $booking_data['booking_date'] ) );
			$time = date_i18n( get_option( 'time_format' ), strtotime( $booking_data['booking_time'] ) );

			$name .= '<br><small class="bkx-booking-datetime">' . esc_html( $date . ' @ ' . $time ) . '</small>';
		}

		return $name;
	}

	/**
	 * Modify cart item thumbnail for booking products.
	 *
	 * @param string $thumbnail  Thumbnail HTML.
	 * @param array  $cart_item  Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function modify_cart_item_thumbnail( $thumbnail, $cart_item, $cart_item_key ) {
		if ( empty( $cart_item['bkx_booking_data'] ) ) {
			return $thumbnail;
		}

		// Use service image if available.
		$booking_data = $cart_item['bkx_booking_data'];
		$service_id   = $booking_data['service_id'];

		if ( has_post_thumbnail( $service_id ) ) {
			$thumbnail = get_the_post_thumbnail( $service_id, 'woocommerce_thumbnail' );
		}

		return $thumbnail;
	}

	/**
	 * Disable quantity change for booking products.
	 *
	 * @param string $quantity   Quantity input HTML.
	 * @param string $cart_key   Cart item key.
	 * @param array  $cart_item  Cart item.
	 * @return string
	 */
	public function disable_quantity_change( $quantity, $cart_key, $cart_item ) {
		if ( ! empty( $cart_item['bkx_booking_data'] ) ) {
			return '1';
		}

		return $quantity;
	}

	/**
	 * Redirect after adding booking to cart.
	 *
	 * @param string $url     Redirect URL.
	 * @param object $product Product object.
	 * @return string
	 */
	public function add_to_cart_redirect( $url, $product = null ) {
		if ( ! $product ) {
			return $url;
		}

		if ( 'bkx_booking' !== $product->get_type() ) {
			return $url;
		}

		$settings = get_option( 'bkx_woocommerce_settings', array() );
		$behavior = isset( $settings['cart_behavior'] ) ? $settings['cart_behavior'] : 'redirect';

		switch ( $behavior ) {
			case 'checkout':
				return wc_get_checkout_url();

			case 'cart':
				return wc_get_cart_url();

			case 'stay':
				return false;

			default:
				return wc_get_cart_url();
		}
	}

	/**
	 * Check availability for a booking slot.
	 *
	 * @param int    $service_id   Service ID.
	 * @param int    $seat_id      Seat ID.
	 * @param string $date         Date (Y-m-d).
	 * @param string $time         Time (H:i).
	 * @return array
	 */
	private function check_availability( $service_id, $seat_id, $date, $time ) {
		// Use BookingX availability check.
		if ( function_exists( 'bkx_check_slot_availability' ) ) {
			$available = bkx_check_slot_availability( $seat_id, $service_id, $date, $time );

			if ( ! $available ) {
				return array(
					'available' => false,
					'message'   => __( 'Sorry, this time slot is no longer available. Please select a different time.', 'bkx-woocommerce-pro' ),
				);
			}
		}

		// Check for existing bookings at this time.
		$existing = get_posts( array(
			'post_type'      => 'bkx_booking',
			'posts_per_page' => 1,
			'post_status'    => array( 'publish', 'bkx-pending', 'bkx-ack' ),
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => 'seat_id',
					'value' => $seat_id,
				),
				array(
					'key'   => 'booking_date',
					'value' => $date,
				),
				array(
					'key'   => 'booking_time',
					'value' => $time,
				),
			),
		) );

		if ( ! empty( $existing ) ) {
			return array(
				'available' => false,
				'message'   => __( 'This time slot has just been booked. Please select a different time.', 'bkx-woocommerce-pro' ),
			);
		}

		return array(
			'available' => true,
			'message'   => '',
		);
	}

	/**
	 * Remove similar cart items for individually sold products.
	 *
	 * @param int $product_id Product ID.
	 */
	private function remove_similar_cart_items( $product_id ) {
		$cart = WC()->cart;

		foreach ( $cart->get_cart() as $cart_key => $cart_item ) {
			if ( absint( $cart_item['product_id'] ) === $product_id ) {
				$cart->remove_cart_item( $cart_key );
			}
		}
	}
}
