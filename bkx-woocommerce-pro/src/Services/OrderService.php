<?php
/**
 * Order Service.
 *
 * Handles WooCommerce order creation and synchronization with BookingX bookings.
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
 * OrderService Class.
 */
class OrderService {

	/**
	 * Instance.
	 *
	 * @var OrderService
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return OrderService
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
		// Create booking when order is placed.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_order' ), 10, 3 );
		add_action( 'woocommerce_thankyou', array( $this, 'display_booking_confirmation' ), 5 );

		// Order item meta.
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_order_item_meta_start', array( $this, 'display_order_item_meta' ), 10, 4 );

		// Status changes.
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_completed' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'on_order_cancelled' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'on_order_refunded' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_processing' ) );

		// Admin order meta box.
		add_action( 'add_meta_boxes', array( $this, 'add_order_meta_box' ) );

		// Order actions.
		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_actions' ) );
		add_action( 'woocommerce_order_action_bkx_create_booking', array( $this, 'action_create_booking' ) );
		add_action( 'woocommerce_order_action_bkx_cancel_booking', array( $this, 'action_cancel_booking' ) );
	}

	/**
	 * Process order and create bookings.
	 *
	 * @param int       $order_id    Order ID.
	 * @param array     $posted_data Posted checkout data.
	 * @param \WC_Order $order       Order object.
	 */
	public function process_order( $order_id, $posted_data, $order ) {
		$bookings_created = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();

			if ( ! $product || 'bkx_booking' !== $product->get_type() ) {
				continue;
			}

			// Get booking data from item meta.
			$booking_data = $item->get_meta( '_bkx_booking_data' );

			if ( empty( $booking_data ) ) {
				continue;
			}

			// Create booking.
			$booking_id = $this->create_booking_from_order_item( $order, $item, $booking_data );

			if ( $booking_id ) {
				$bookings_created[] = $booking_id;

				// Store booking ID in order item.
				$item->update_meta_data( '_bkx_booking_id', $booking_id );
				$item->save();
			}
		}

		if ( ! empty( $bookings_created ) ) {
			$order->update_meta_data( '_bkx_booking_ids', $bookings_created );
			$order->save();

			do_action( 'bkx_woo_bookings_created_from_order', $bookings_created, $order_id );
		}
	}

	/**
	 * Create booking from order item.
	 *
	 * @param \WC_Order      $order        Order object.
	 * @param \WC_Order_Item $item         Order item.
	 * @param array          $booking_data Booking data.
	 * @return int|false Booking ID or false.
	 */
	public function create_booking_from_order_item( $order, $item, $booking_data ) {
		$product = $item->get_product();

		// Get service and seat.
		$service_id = isset( $booking_data['service_id'] ) ? absint( $booking_data['service_id'] ) : $product->get_linked_service_id();
		$seat_id    = isset( $booking_data['seat_id'] ) ? absint( $booking_data['seat_id'] ) : $product->get_linked_seat_id();

		if ( ! $service_id ) {
			return false;
		}

		// Get dates.
		$booking_date = isset( $booking_data['booking_date'] ) ? $booking_data['booking_date'] : '';
		$booking_time = isset( $booking_data['booking_time'] ) ? $booking_data['booking_time'] : '';

		if ( empty( $booking_date ) || empty( $booking_time ) ) {
			return false;
		}

		// Get duration.
		$duration = $product->get_booking_duration();

		// Calculate end time.
		$start_datetime = strtotime( $booking_date . ' ' . $booking_time );
		$end_datetime   = $start_datetime + ( $duration * 60 );

		// Get extras.
		$extras    = isset( $booking_data['extras'] ) ? $booking_data['extras'] : array();
		$extra_ids = array_column( $extras, 'id' );

		// Build booking post data.
		$booking_post = array(
			'post_type'   => 'bkx_booking',
			'post_status' => $this->get_initial_booking_status( $order ),
			'post_title'  => sprintf(
				/* translators: %s: customer name */
				__( 'Booking for %s', 'bkx-woocommerce-pro' ),
				$order->get_formatted_billing_full_name()
			),
		);

		$booking_id = wp_insert_post( $booking_post );

		if ( is_wp_error( $booking_id ) ) {
			return false;
		}

		// Save booking meta.
		$meta = array(
			'seat_id'              => $seat_id,
			'booking_multi_base'   => array( $service_id ),
			'booking_date'         => $booking_date,
			'booking_time'         => $booking_time,
			'booking_date_end'     => gmdate( 'Y-m-d', $end_datetime ),
			'booking_time_end'     => gmdate( 'H:i', $end_datetime ),
			'booking_time_mins'    => $duration,
			'customer_email'       => $order->get_billing_email(),
			'customer_name'        => $order->get_formatted_billing_full_name(),
			'customer_first_name'  => $order->get_billing_first_name(),
			'customer_last_name'   => $order->get_billing_last_name(),
			'customer_phone'       => $order->get_billing_phone(),
			'booking_total'        => $item->get_total(),
			'booking_note'         => $order->get_customer_note(),
			'from_woo_order'       => $order->get_id(),
			'woo_order_item_id'    => $item->get_id(),
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $booking_id, $key, $value );
		}

		// Save extras.
		if ( ! empty( $extra_ids ) ) {
			update_post_meta( $booking_id, 'booking_extra', $extra_ids );
		}

		// Trigger booking created action.
		do_action( 'bkx_booking_created', $booking_id, array_merge( $meta, array( 'from_woo_order' => true ) ) );

		return $booking_id;
	}

	/**
	 * Create WooCommerce order from BookingX booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return int|false Order ID or false.
	 */
	public function create_order_from_booking( $booking_id ) {
		$booking = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return false;
		}

		// Check if order already exists.
		$existing_order = get_post_meta( $booking_id, 'from_woo_order', true );
		if ( $existing_order ) {
			return $existing_order;
		}

		// Get booking data.
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		$customer_name  = get_post_meta( $booking_id, 'customer_name', true );
		$customer_phone = get_post_meta( $booking_id, 'customer_phone', true );
		$service_ids    = get_post_meta( $booking_id, 'booking_multi_base', true );
		$booking_total  = get_post_meta( $booking_id, 'booking_total', true );
		$booking_date   = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time   = get_post_meta( $booking_id, 'booking_time', true );
		$seat_id        = get_post_meta( $booking_id, 'seat_id', true );
		$extra_ids      = get_post_meta( $booking_id, 'booking_extra', true );

		// Get or create customer.
		$customer = get_user_by( 'email', $customer_email );
		$user_id  = $customer ? $customer->ID : 0;

		// Create order.
		$order = wc_create_order( array(
			'customer_id' => $user_id,
			'status'      => 'pending',
		) );

		if ( is_wp_error( $order ) ) {
			return false;
		}

		// Set billing details.
		$name_parts = explode( ' ', $customer_name, 2 );
		$order->set_billing_first_name( $name_parts[0] );
		$order->set_billing_last_name( isset( $name_parts[1] ) ? $name_parts[1] : '' );
		$order->set_billing_email( $customer_email );
		$order->set_billing_phone( $customer_phone );

		// Add product for each service.
		foreach ( (array) $service_ids as $service_id ) {
			$product_integration = ProductIntegration::get_instance();
			$product_id          = $product_integration->get_product_for_service( $service_id );

			if ( ! $product_id ) {
				$product_id = $product_integration->create_product_from_service( $service_id );
			}

			if ( ! $product_id ) {
				continue;
			}

			$product = wc_get_product( $product_id );

			// Build extras data.
			$extras_data  = array();
			$extras_price = 0;

			if ( ! empty( $extra_ids ) ) {
				foreach ( (array) $extra_ids as $extra_id ) {
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
			}

			// Add product to order.
			$item_id = $order->add_product( $product, 1 );
			$item    = $order->get_item( $item_id );

			// Set custom price if needed.
			$item_total = floatval( $product->get_price() ) + $extras_price;
			$item->set_total( $item_total );
			$item->set_subtotal( $item_total );

			// Add booking meta.
			$item->update_meta_data( '_bkx_booking_data', array(
				'service_id'   => $service_id,
				'service_name' => get_the_title( $service_id ),
				'seat_id'      => $seat_id,
				'seat_name'    => $seat_id ? get_the_title( $seat_id ) : '',
				'booking_date' => $booking_date,
				'booking_time' => $booking_time,
				'extras'       => $extras_data,
				'extras_price' => $extras_price,
			) );

			$item->update_meta_data( '_bkx_booking_id', $booking_id );
			$item->save();
		}

		// Calculate totals.
		$order->calculate_totals();

		// Link booking to order.
		update_post_meta( $booking_id, 'from_woo_order', $order->get_id() );
		$order->update_meta_data( '_bkx_booking_ids', array( $booking_id ) );
		$order->save();

		do_action( 'bkx_woo_order_created_from_booking', $order->get_id(), $booking_id );

		return $order->get_id();
	}

	/**
	 * Add booking data to order item meta.
	 *
	 * @param \WC_Order_Item_Product $item         Order item.
	 * @param string                 $cart_item_key Cart item key.
	 * @param array                  $values       Cart item values.
	 * @param \WC_Order              $order        Order object.
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['bkx_booking_data'] ) ) {
			$item->update_meta_data( '_bkx_booking_data', $values['bkx_booking_data'] );
		}
	}

	/**
	 * Display booking info in order item.
	 *
	 * @param int                    $item_id Item ID.
	 * @param \WC_Order_Item_Product $item    Order item.
	 * @param \WC_Order              $order   Order object.
	 * @param bool                   $plain_text Plain text format.
	 */
	public function display_order_item_meta( $item_id, $item, $order, $plain_text = false ) {
		$booking_data = $item->get_meta( '_bkx_booking_data' );
		$booking_id   = $item->get_meta( '_bkx_booking_id' );

		if ( empty( $booking_data ) ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'Booking Details:', 'bkx-woocommerce-pro' ) . "\n";

			if ( ! empty( $booking_data['booking_date'] ) ) {
				echo esc_html__( 'Date: ', 'bkx-woocommerce-pro' ) . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking_data['booking_date'] ) ) ) . "\n";
			}

			if ( ! empty( $booking_data['booking_time'] ) ) {
				echo esc_html__( 'Time: ', 'bkx-woocommerce-pro' ) . esc_html( date_i18n( get_option( 'time_format' ), strtotime( $booking_data['booking_time'] ) ) ) . "\n";
			}

			if ( ! empty( $booking_data['seat_name'] ) ) {
				echo esc_html( get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-woocommerce-pro' ) ) . ': ' . $booking_data['seat_name'] ) . "\n";
			}
		} else {
			echo '<div class="bkx-order-booking-details">';
			echo '<strong>' . esc_html__( 'Booking Details:', 'bkx-woocommerce-pro' ) . '</strong><br>';

			if ( ! empty( $booking_data['booking_date'] ) ) {
				echo esc_html__( 'Date: ', 'bkx-woocommerce-pro' ) . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $booking_data['booking_date'] ) ) ) . '<br>';
			}

			if ( ! empty( $booking_data['booking_time'] ) ) {
				echo esc_html__( 'Time: ', 'bkx-woocommerce-pro' ) . esc_html( date_i18n( get_option( 'time_format' ), strtotime( $booking_data['booking_time'] ) ) ) . '<br>';
			}

			if ( ! empty( $booking_data['seat_name'] ) ) {
				echo esc_html( get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-woocommerce-pro' ) ) . ': ' . $booking_data['seat_name'] ) . '<br>';
			}

			if ( $booking_id && is_admin() ) {
				echo '<a href="' . esc_url( get_edit_post_link( $booking_id ) ) . '">' . esc_html__( 'View Booking', 'bkx-woocommerce-pro' ) . '</a>';
			}

			echo '</div>';
		}
	}

	/**
	 * Display booking confirmation on thank you page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function display_booking_confirmation( $order_id ) {
		$order       = wc_get_order( $order_id );
		$booking_ids = $order->get_meta( '_bkx_booking_ids' );

		if ( empty( $booking_ids ) ) {
			return;
		}

		echo '<div class="bkx-woo-booking-confirmation">';
		echo '<h2>' . esc_html__( 'Booking Confirmation', 'bkx-woocommerce-pro' ) . '</h2>';

		foreach ( $booking_ids as $booking_id ) {
			$booking      = get_post( $booking_id );
			$booking_date = get_post_meta( $booking_id, 'booking_date', true );
			$booking_time = get_post_meta( $booking_id, 'booking_time', true );
			$seat_id      = get_post_meta( $booking_id, 'seat_id', true );
			$service_ids  = get_post_meta( $booking_id, 'booking_multi_base', true );

			echo '<div class="bkx-booking-summary">';
			echo '<p><strong>' . esc_html__( 'Booking #', 'bkx-woocommerce-pro' ) . esc_html( $booking_id ) . '</strong></p>';

			if ( ! empty( $service_ids ) ) {
				$service_names = array_map( 'get_the_title', (array) $service_ids );
				echo '<p>' . esc_html__( 'Service: ', 'bkx-woocommerce-pro' ) . esc_html( implode( ', ', $service_names ) ) . '</p>';
			}

			if ( $booking_date && $booking_time ) {
				$datetime = date_i18n( get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' ), strtotime( $booking_date . ' ' . $booking_time ) );
				echo '<p>' . esc_html__( 'Date/Time: ', 'bkx-woocommerce-pro' ) . esc_html( $datetime ) . '</p>';
			}

			if ( $seat_id ) {
				echo '<p>' . esc_html( get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-woocommerce-pro' ) ) . ': ' . get_the_title( $seat_id ) ) . '</p>';
			}

			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Handle order completed.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_order_completed( $order_id ) {
		$this->update_booking_status( $order_id, 'bkx-completed' );
	}

	/**
	 * Handle order cancelled.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_order_cancelled( $order_id ) {
		$this->update_booking_status( $order_id, 'bkx-cancelled' );
	}

	/**
	 * Handle order refunded.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_order_refunded( $order_id ) {
		$this->update_booking_status( $order_id, 'bkx-cancelled' );
	}

	/**
	 * Handle order processing.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_order_processing( $order_id ) {
		$this->update_booking_status( $order_id, 'bkx-ack' );
	}

	/**
	 * Update booking status from order.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status   New booking status.
	 */
	private function update_booking_status( $order_id, $status ) {
		$order       = wc_get_order( $order_id );
		$booking_ids = $order->get_meta( '_bkx_booking_ids' );

		if ( empty( $booking_ids ) ) {
			return;
		}

		foreach ( $booking_ids as $booking_id ) {
			$current_status = get_post_status( $booking_id );

			if ( $current_status !== $status ) {
				wp_update_post( array(
					'ID'          => $booking_id,
					'post_status' => $status,
				) );

				do_action( 'bkx_booking_status_changed', $booking_id, $current_status, $status );
			}
		}
	}

	/**
	 * Add meta box to order page.
	 */
	public function add_order_meta_box() {
		add_meta_box(
			'bkx-order-bookings',
			__( 'BookingX Bookings', 'bkx-woocommerce-pro' ),
			array( $this, 'render_order_meta_box' ),
			wc_get_page_screen_id( 'shop-order' ),
			'side',
			'default'
		);

		// HPOS compatibility.
		add_meta_box(
			'bkx-order-bookings',
			__( 'BookingX Bookings', 'bkx-woocommerce-pro' ),
			array( $this, 'render_order_meta_box' ),
			'woocommerce_page_wc-orders',
			'side',
			'default'
		);
	}

	/**
	 * Render order meta box.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Post or order object.
	 */
	public function render_order_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );

		if ( ! $order ) {
			return;
		}

		$booking_ids = $order->get_meta( '_bkx_booking_ids' );

		if ( empty( $booking_ids ) ) {
			echo '<p>' . esc_html__( 'No bookings associated with this order.', 'bkx-woocommerce-pro' ) . '</p>';
			return;
		}

		echo '<ul class="bkx-order-bookings-list">';

		foreach ( $booking_ids as $booking_id ) {
			$booking = get_post( $booking_id );

			if ( ! $booking ) {
				continue;
			}

			$status       = get_post_status( $booking_id );
			$status_label = $this->get_status_label( $status );
			$booking_date = get_post_meta( $booking_id, 'booking_date', true );
			$booking_time = get_post_meta( $booking_id, 'booking_time', true );

			echo '<li>';
			echo '<a href="' . esc_url( get_edit_post_link( $booking_id ) ) . '">';
			echo '<strong>#' . esc_html( $booking_id ) . '</strong>';
			echo '</a>';
			echo ' - <span class="bkx-status bkx-status-' . esc_attr( $status ) . '">' . esc_html( $status_label ) . '</span>';

			if ( $booking_date && $booking_time ) {
				echo '<br><small>' . esc_html( date_i18n( 'M j, Y g:i a', strtotime( $booking_date . ' ' . $booking_time ) ) ) . '</small>';
			}

			echo '</li>';
		}

		echo '</ul>';
	}

	/**
	 * Add order actions.
	 *
	 * @param array $actions Order actions.
	 * @return array
	 */
	public function add_order_actions( $actions ) {
		global $theorder;

		if ( ! $theorder ) {
			return $actions;
		}

		$booking_ids = $theorder->get_meta( '_bkx_booking_ids' );

		if ( empty( $booking_ids ) ) {
			$actions['bkx_create_booking'] = __( 'Create BookingX Booking', 'bkx-woocommerce-pro' );
		} else {
			$actions['bkx_cancel_booking'] = __( 'Cancel BookingX Booking', 'bkx-woocommerce-pro' );
		}

		return $actions;
	}

	/**
	 * Action: Create booking.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function action_create_booking( $order ) {
		$this->process_order( $order->get_id(), array(), $order );

		$order->add_order_note( __( 'BookingX booking created manually.', 'bkx-woocommerce-pro' ) );
	}

	/**
	 * Action: Cancel booking.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function action_cancel_booking( $order ) {
		$this->update_booking_status( $order->get_id(), 'bkx-cancelled' );

		$order->add_order_note( __( 'BookingX booking cancelled manually.', 'bkx-woocommerce-pro' ) );
	}

	/**
	 * Get initial booking status based on order status.
	 *
	 * @param \WC_Order $order Order object.
	 * @return string
	 */
	private function get_initial_booking_status( $order ) {
		$settings = get_option( 'bkx_woocommerce_settings', array() );
		$mapping  = isset( $settings['order_status_mapping'] ) ? $settings['order_status_mapping'] : array();

		$order_status = $order->get_status();

		if ( isset( $mapping[ $order_status ] ) ) {
			return $mapping[ $order_status ];
		}

		// Default status based on payment.
		if ( $order->is_paid() ) {
			return 'bkx-ack';
		}

		return 'bkx-pending';
	}

	/**
	 * Get status label.
	 *
	 * @param string $status Status slug.
	 * @return string
	 */
	private function get_status_label( $status ) {
		$labels = array(
			'bkx-pending'   => __( 'Pending', 'bkx-woocommerce-pro' ),
			'bkx-ack'       => __( 'Acknowledged', 'bkx-woocommerce-pro' ),
			'bkx-completed' => __( 'Completed', 'bkx-woocommerce-pro' ),
			'bkx-cancelled' => __( 'Cancelled', 'bkx-woocommerce-pro' ),
			'bkx-missed'    => __( 'Missed', 'bkx-woocommerce-pro' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( str_replace( 'bkx-', '', $status ) );
	}
}
