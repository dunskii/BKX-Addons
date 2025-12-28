<?php
/**
 * Main WooCommerce Pro Addon Class.
 *
 * @package BookingX\WooCommercePro
 * @since   1.0.0
 */

namespace BookingX\WooCommercePro;

use BookingX\WooCommercePro\Services\ProductIntegration;
use BookingX\WooCommercePro\Services\CartService;
use BookingX\WooCommercePro\Services\OrderService;
use BookingX\WooCommercePro\Services\SyncService;
use BookingX\WooCommercePro\Admin\SettingsPage;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerceAddon Class.
 */
class WooCommerceAddon {

	/**
	 * Instance.
	 *
	 * @var WooCommerceAddon
	 */
	private static $instance = null;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Get instance.
	 *
	 * @return WooCommerceAddon
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
		$this->settings = get_option( 'bkx_woocommerce_settings', array() );

		$this->init_hooks();
		$this->init_services();
		$this->init_admin();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Register custom product type.
		add_action( 'init', array( $this, 'register_product_type' ) );
		add_filter( 'product_type_selector', array( $this, 'add_product_type_selector' ) );
		add_filter( 'woocommerce_product_class', array( $this, 'product_class' ), 10, 2 );

		// Enqueue scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_woo_add_booking_to_cart', array( $this, 'ajax_add_booking_to_cart' ) );
		add_action( 'wp_ajax_nopriv_bkx_woo_add_booking_to_cart', array( $this, 'ajax_add_booking_to_cart' ) );
		add_action( 'wp_ajax_bkx_woo_get_booking_product', array( $this, 'ajax_get_booking_product' ) );
		add_action( 'wp_ajax_nopriv_bkx_woo_get_booking_product', array( $this, 'ajax_get_booking_product' ) );

		// BookingX hooks.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_status_changed', array( $this, 'on_booking_status_changed' ), 10, 3 );

		// Shortcodes.
		add_shortcode( 'bkx_woo_booking_products', array( $this, 'booking_products_shortcode' ) );
		add_shortcode( 'bkx_woo_service_catalog', array( $this, 'service_catalog_shortcode' ) );

		// My Account integration.
		if ( $this->get_setting( 'display_in_account', true ) ) {
			add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_menu_item' ) );
			add_action( 'woocommerce_account_bookings_endpoint', array( $this, 'render_account_bookings' ) );
			add_action( 'init', array( $this, 'add_account_endpoint' ) );
		}

		// Email integration.
		if ( $this->get_setting( 'email_integration', true ) ) {
			add_filter( 'woocommerce_email_classes', array( $this, 'add_email_classes' ) );
		}

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		ProductIntegration::get_instance();
		CartService::get_instance();
		OrderService::get_instance();
		SyncService::get_instance();
	}

	/**
	 * Initialize admin.
	 */
	private function init_admin() {
		if ( is_admin() ) {
			SettingsPage::get_instance();
		}
	}

	/**
	 * Get setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Register custom product type.
	 */
	public function register_product_type() {
		require_once BKX_WOOCOMMERCE_PATH . 'src/Products/BookingProduct.php';
	}

	/**
	 * Add booking product type to selector.
	 *
	 * @param array $types Product types.
	 * @return array
	 */
	public function add_product_type_selector( $types ) {
		$types['bkx_booking'] = __( 'BookingX Booking', 'bkx-woocommerce-pro' );
		return $types;
	}

	/**
	 * Filter product class.
	 *
	 * @param string $classname Product class name.
	 * @param string $type      Product type.
	 * @return string
	 */
	public function product_class( $classname, $type ) {
		if ( 'bkx_booking' === $type ) {
			return 'BookingX\\WooCommercePro\\Products\\BookingProduct';
		}
		return $classname;
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets() {
		wp_enqueue_style(
			'bkx-woocommerce-frontend',
			BKX_WOOCOMMERCE_URL . 'assets/css/frontend.css',
			array(),
			BKX_WOOCOMMERCE_VERSION
		);

		wp_enqueue_script(
			'bkx-woocommerce-frontend',
			BKX_WOOCOMMERCE_URL . 'assets/js/frontend.js',
			array( 'jquery', 'wc-add-to-cart' ),
			BKX_WOOCOMMERCE_VERSION,
			true
		);

		wp_localize_script( 'bkx-woocommerce-frontend', 'bkxWooSettings', array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'bkx_woo_nonce' ),
			'cartBehavior'  => $this->get_setting( 'cart_behavior', 'redirect' ),
			'cartUrl'       => wc_get_cart_url(),
			'checkoutUrl'   => wc_get_checkout_url(),
			'i18n'          => array(
				'addingToCart'  => __( 'Adding to cart...', 'bkx-woocommerce-pro' ),
				'addedToCart'   => __( 'Added to cart!', 'bkx-woocommerce-pro' ),
				'viewCart'      => __( 'View Cart', 'bkx-woocommerce-pro' ),
				'checkout'      => __( 'Checkout', 'bkx-woocommerce-pro' ),
				'error'         => __( 'Failed to add to cart. Please try again.', 'bkx-woocommerce-pro' ),
			),
		) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		// Load on product edit and settings pages.
		if ( 'product' === $screen->post_type || strpos( $hook, 'bkx-woocommerce' ) !== false ) {
			wp_enqueue_style(
				'bkx-woocommerce-admin',
				BKX_WOOCOMMERCE_URL . 'assets/css/admin.css',
				array(),
				BKX_WOOCOMMERCE_VERSION
			);

			wp_enqueue_script(
				'bkx-woocommerce-admin',
				BKX_WOOCOMMERCE_URL . 'assets/js/admin.js',
				array( 'jquery', 'wp-util' ),
				BKX_WOOCOMMERCE_VERSION,
				true
			);

			wp_localize_script( 'bkx-woocommerce-admin', 'bkxWooAdmin', array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_woo_admin_nonce' ),
				'i18n'    => array(
					'selectService' => __( 'Select a service...', 'bkx-woocommerce-pro' ),
					'selectSeat'    => __( 'Select a resource...', 'bkx-woocommerce-pro' ),
					'syncComplete'  => __( 'Sync completed successfully!', 'bkx-woocommerce-pro' ),
					'syncError'     => __( 'Sync failed. Please check the logs.', 'bkx-woocommerce-pro' ),
				),
			) );
		}
	}

	/**
	 * AJAX: Add booking to cart.
	 */
	public function ajax_add_booking_to_cart() {
		check_ajax_referer( 'bkx_woo_nonce', 'nonce' );

		$booking_data = array(
			'service_id'    => isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0,
			'seat_id'       => isset( $_POST['seat_id'] ) ? absint( $_POST['seat_id'] ) : 0,
			'booking_date'  => isset( $_POST['booking_date'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_date'] ) ) : '',
			'booking_time'  => isset( $_POST['booking_time'] ) ? sanitize_text_field( wp_unslash( $_POST['booking_time'] ) ) : '',
			'extras'        => isset( $_POST['extras'] ) ? array_map( 'absint', (array) $_POST['extras'] ) : array(),
			'customer_data' => isset( $_POST['customer'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['customer'] ) ) : array(),
		);

		$cart_service = CartService::get_instance();
		$result       = $cart_service->add_booking_to_cart( $booking_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message' => $result->get_error_message(),
			) );
		}

		wp_send_json_success( array(
			'cart_key'      => $result['cart_key'],
			'cart_url'      => wc_get_cart_url(),
			'checkout_url'  => wc_get_checkout_url(),
			'cart_count'    => WC()->cart->get_cart_contents_count(),
			'cart_total'    => WC()->cart->get_cart_total(),
		) );
	}

	/**
	 * AJAX: Get booking product.
	 */
	public function ajax_get_booking_product() {
		check_ajax_referer( 'bkx_woo_nonce', 'nonce' );

		$service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;

		if ( ! $service_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service.', 'bkx-woocommerce-pro' ) ) );
		}

		$product_integration = ProductIntegration::get_instance();
		$product_id          = $product_integration->get_product_for_service( $service_id );

		if ( ! $product_id ) {
			// Auto-create product if enabled.
			if ( $this->get_setting( 'auto_create_products', true ) ) {
				$product_id = $product_integration->create_product_from_service( $service_id );
			}
		}

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'No product found for this service.', 'bkx-woocommerce-pro' ) ) );
		}

		$product = wc_get_product( $product_id );

		wp_send_json_success( array(
			'product_id'   => $product_id,
			'product_name' => $product->get_name(),
			'price'        => $product->get_price(),
			'price_html'   => $product->get_price_html(),
		) );
	}

	/**
	 * Handle booking created.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		// Check if this booking should create a WooCommerce order.
		if ( ! $this->get_setting( 'create_orders', true ) ) {
			return;
		}

		// Skip if booking was created from WooCommerce.
		if ( ! empty( $booking_data['from_woo_order'] ) ) {
			return;
		}

		$order_service = OrderService::get_instance();
		$order_service->create_order_from_booking( $booking_id );
	}

	/**
	 * Handle booking status changed.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 */
	public function on_booking_status_changed( $booking_id, $old_status, $new_status ) {
		$sync_service = SyncService::get_instance();
		$sync_service->sync_booking_status_to_order( $booking_id, $new_status );
	}

	/**
	 * Booking products shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function booking_products_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'columns'    => 3,
			'limit'      => 12,
			'orderby'    => 'title',
			'order'      => 'ASC',
			'category'   => '',
			'seat'       => '',
		), $atts );

		ob_start();
		include BKX_WOOCOMMERCE_PATH . 'templates/frontend/booking-products.php';
		return ob_get_clean();
	}

	/**
	 * Service catalog shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function service_catalog_shortcode( $atts ) {
		$atts = shortcode_atts( array(
			'columns'     => 3,
			'show_price'  => 'yes',
			'show_desc'   => 'yes',
			'add_to_cart' => 'yes',
			'seat'        => '',
			'category'    => '',
		), $atts );

		ob_start();
		include BKX_WOOCOMMERCE_PATH . 'templates/frontend/service-catalog.php';
		return ob_get_clean();
	}

	/**
	 * Add bookings to My Account menu.
	 *
	 * @param array $items Menu items.
	 * @return array
	 */
	public function add_account_menu_item( $items ) {
		$new_items = array();

		foreach ( $items as $key => $item ) {
			$new_items[ $key ] = $item;

			// Add after orders.
			if ( 'orders' === $key ) {
				$new_items['bookings'] = __( 'My Bookings', 'bkx-woocommerce-pro' );
			}
		}

		return $new_items;
	}

	/**
	 * Add bookings endpoint.
	 */
	public function add_account_endpoint() {
		add_rewrite_endpoint( 'bookings', EP_ROOT | EP_PAGES );
	}

	/**
	 * Render account bookings page.
	 */
	public function render_account_bookings() {
		$customer_id = get_current_user_id();

		// Get customer bookings.
		$bookings = get_posts( array(
			'post_type'      => 'bkx_booking',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => 'customer_email',
					'value' => wp_get_current_user()->user_email,
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		include BKX_WOOCOMMERCE_PATH . 'templates/frontend/account-bookings.php';
	}

	/**
	 * Add custom email classes.
	 *
	 * @param array $emails Email classes.
	 * @return array
	 */
	public function add_email_classes( $emails ) {
		$emails['BKX_Booking_Confirmation'] = new Emails\BookingConfirmationEmail();
		$emails['BKX_Booking_Reminder']     = new Emails\BookingReminderEmail();
		return $emails;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		register_rest_route( 'bkx-woo/v1', '/products', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'rest_get_products' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( 'bkx-woo/v1', '/sync', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'rest_sync_products' ),
			'permission_callback' => function () {
				return current_user_can( 'manage_woocommerce' );
			},
		) );
	}

	/**
	 * REST: Get booking products.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_products( $request ) {
		$product_integration = ProductIntegration::get_instance();
		$products            = $product_integration->get_all_booking_products();

		return rest_ensure_response( $products );
	}

	/**
	 * REST: Sync products.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_sync_products( $request ) {
		$sync_service = SyncService::get_instance();
		$result       = $sync_service->sync_all_products();

		return rest_ensure_response( $result );
	}
}
