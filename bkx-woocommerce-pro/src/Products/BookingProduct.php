<?php
/**
 * Custom WooCommerce Product Type for BookingX Bookings.
 *
 * @package BookingX\WooCommercePro\Products
 * @since   1.0.0
 */

namespace BookingX\WooCommercePro\Products;

use WC_Product;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BookingProduct Class.
 *
 * Extends WC_Product to add booking-specific functionality.
 */
class BookingProduct extends WC_Product {

	/**
	 * Product type.
	 *
	 * @var string
	 */
	protected $product_type = 'bkx_booking';

	/**
	 * Constructor.
	 *
	 * @param mixed $product Product to init.
	 */
	public function __construct( $product = 0 ) {
		parent::__construct( $product );
	}

	/**
	 * Get internal type.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'bkx_booking';
	}

	/**
	 * Check if product is virtual (bookings are always virtual).
	 *
	 * @return bool
	 */
	public function is_virtual() {
		return true;
	}

	/**
	 * Check if product is sold individually.
	 *
	 * @return bool
	 */
	public function is_sold_individually() {
		return 'yes' === $this->get_meta( '_bkx_sold_individually' );
	}

	/**
	 * Check if product is purchasable.
	 *
	 * @return bool
	 */
	public function is_purchasable() {
		$purchasable = true;

		// Check if linked service exists.
		$service_id = $this->get_linked_service_id();
		if ( $service_id && 'publish' !== get_post_status( $service_id ) ) {
			$purchasable = false;
		}

		return apply_filters( 'woocommerce_is_purchasable', $purchasable, $this );
	}

	/**
	 * Get linked BookingX service ID.
	 *
	 * @return int
	 */
	public function get_linked_service_id() {
		return absint( $this->get_meta( '_bkx_service_id' ) );
	}

	/**
	 * Set linked BookingX service ID.
	 *
	 * @param int $service_id Service ID.
	 */
	public function set_linked_service_id( $service_id ) {
		$this->update_meta_data( '_bkx_service_id', absint( $service_id ) );
	}

	/**
	 * Get linked BookingX seat ID (optional).
	 *
	 * @return int
	 */
	public function get_linked_seat_id() {
		return absint( $this->get_meta( '_bkx_seat_id' ) );
	}

	/**
	 * Set linked BookingX seat ID.
	 *
	 * @param int $seat_id Seat ID.
	 */
	public function set_linked_seat_id( $seat_id ) {
		$this->update_meta_data( '_bkx_seat_id', absint( $seat_id ) );
	}

	/**
	 * Get booking duration.
	 *
	 * @return int Duration in minutes.
	 */
	public function get_booking_duration() {
		$duration = $this->get_meta( '_bkx_duration' );

		if ( ! $duration ) {
			// Get from linked service.
			$service_id = $this->get_linked_service_id();
			if ( $service_id ) {
				$duration = get_post_meta( $service_id, 'base_time', true );
			}
		}

		return absint( $duration );
	}

	/**
	 * Get allowed extras.
	 *
	 * @return array Extra IDs.
	 */
	public function get_allowed_extras() {
		$extras = $this->get_meta( '_bkx_allowed_extras' );
		return is_array( $extras ) ? $extras : array();
	}

	/**
	 * Check if date/time selection is required.
	 *
	 * @return bool
	 */
	public function requires_date_selection() {
		return 'yes' === $this->get_meta( '_bkx_requires_date', 'yes' );
	}

	/**
	 * Check if seat selection is required.
	 *
	 * @return bool
	 */
	public function requires_seat_selection() {
		$requires = $this->get_meta( '_bkx_requires_seat' );
		return 'yes' === $requires || ( '' === $requires && ! $this->get_linked_seat_id() );
	}

	/**
	 * Get price based on service.
	 *
	 * @param string $context View or edit context.
	 * @return string
	 */
	public function get_price( $context = 'view' ) {
		$price = parent::get_price( $context );

		// Fallback to service price.
		if ( '' === $price || null === $price ) {
			$service_id = $this->get_linked_service_id();
			if ( $service_id ) {
				$price = get_post_meta( $service_id, 'base_price', true );
			}
		}

		return $price;
	}

	/**
	 * Get regular price.
	 *
	 * @param string $context View or edit context.
	 * @return string
	 */
	public function get_regular_price( $context = 'view' ) {
		$price = parent::get_regular_price( $context );

		// Fallback to service price.
		if ( '' === $price ) {
			$service_id = $this->get_linked_service_id();
			if ( $service_id ) {
				$price = get_post_meta( $service_id, 'base_price', true );
			}
		}

		return $price;
	}

	/**
	 * Check if product is in stock.
	 *
	 * @return bool
	 */
	public function is_in_stock() {
		$service_id = $this->get_linked_service_id();

		if ( ! $service_id ) {
			return false;
		}

		// Check service is published.
		if ( 'publish' !== get_post_status( $service_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get add to cart button text.
	 *
	 * @return string
	 */
	public function add_to_cart_text() {
		if ( $this->requires_date_selection() ) {
			return __( 'Book Now', 'bkx-woocommerce-pro' );
		}
		return __( 'Add to Cart', 'bkx-woocommerce-pro' );
	}

	/**
	 * Get single add to cart button text.
	 *
	 * @return string
	 */
	public function single_add_to_cart_text() {
		return $this->add_to_cart_text();
	}

	/**
	 * Get add to cart URL.
	 *
	 * @return string
	 */
	public function add_to_cart_url() {
		if ( $this->requires_date_selection() ) {
			return get_permalink( $this->get_id() );
		}
		return parent::add_to_cart_url();
	}

	/**
	 * Returns false if the product cannot be bought.
	 *
	 * @return bool
	 */
	public function supports( $feature ) {
		$supported = array(
			'ajax_add_to_cart' => ! $this->requires_date_selection(),
		);

		return isset( $supported[ $feature ] ) ? $supported[ $feature ] : parent::supports( $feature );
	}

	/**
	 * Get formatted booking duration.
	 *
	 * @return string
	 */
	public function get_formatted_duration() {
		$minutes = $this->get_booking_duration();

		if ( $minutes < 60 ) {
			/* translators: %d: minutes */
			return sprintf( _n( '%d minute', '%d minutes', $minutes, 'bkx-woocommerce-pro' ), $minutes );
		}

		$hours   = floor( $minutes / 60 );
		$mins    = $minutes % 60;

		if ( $mins === 0 ) {
			/* translators: %d: hours */
			return sprintf( _n( '%d hour', '%d hours', $hours, 'bkx-woocommerce-pro' ), $hours );
		}

		/* translators: %1$d: hours, %2$d: minutes */
		return sprintf( __( '%1$d hour %2$d min', 'bkx-woocommerce-pro' ), $hours, $mins );
	}

	/**
	 * Get linked service object.
	 *
	 * @return \WP_Post|null
	 */
	public function get_linked_service() {
		$service_id = $this->get_linked_service_id();
		return $service_id ? get_post( $service_id ) : null;
	}

	/**
	 * Get linked seat object.
	 *
	 * @return \WP_Post|null
	 */
	public function get_linked_seat() {
		$seat_id = $this->get_linked_seat_id();
		return $seat_id ? get_post( $seat_id ) : null;
	}
}
