<?php
/**
 * Group Bookings Addon
 *
 * @package BookingX\GroupBookings
 * @since   1.0.0
 */

namespace BookingX\GroupBookings;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasAjax;

/**
 * Main addon class for Group Bookings.
 *
 * @since 1.0.0
 */
class GroupBookingsAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasAjax;

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected string $slug = 'bkx-group-bookings';

	/**
	 * Addon version.
	 *
	 * @var string
	 */
	protected string $version = '1.0.0';

	/**
	 * EDD product ID.
	 *
	 * @var int
	 */
	protected int $product_id = 114;

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot the addon.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function boot(): void {
		// Initialize services.
		$this->init_services();

		// Register admin components.
		if ( is_admin() ) {
			new Admin\SettingsPage( $this );
			new Admin\BaseMetabox();
			new Admin\SeatMetabox();
		}

		// Enqueue assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Filter booking form.
		add_filter( 'bkx_booking_form_fields', array( $this, 'add_quantity_field' ), 10, 2 );

		// Filter booking calculations.
		add_filter( 'bkx_booking_total', array( $this, 'calculate_group_total' ), 10, 2 );

		// Filter availability.
		add_filter( 'bkx_available_slots', array( $this, 'filter_slots_by_capacity' ), 10, 3 );

		// Store group booking data.
		add_action( 'bkx_booking_created', array( $this, 'save_group_data' ), 10, 2 );

		// Validate group size.
		add_filter( 'bkx_validate_booking', array( $this, 'validate_group_size' ), 10, 2 );

		// Register AJAX handlers.
		$this->register_ajax_handlers();
	}

	/**
	 * Initialize services.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_services(): void {
		// Initialize services as needed.
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array(
			'enable_quantity'       => 1,
			'default_min_quantity'  => 1,
			'default_max_quantity'  => 10,
			'pricing_mode'          => 'per_person', // per_person, flat_rate, tiered.
			'show_quantity_label'   => 1,
			'quantity_label'        => __( 'Number of People', 'bkx-group-bookings' ),
			'group_discount_enable' => 0,
			'group_discount_min'    => 5,
			'group_discount_type'   => 'percentage', // percentage, fixed.
			'group_discount_value'  => 10,
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		$screen = get_current_screen();

		if ( $screen && in_array( $screen->post_type, array( 'bkx_base', 'bkx_seat' ), true ) ) {
			wp_enqueue_style(
				'bkx-group-bookings-admin',
				BKX_GROUP_BOOKINGS_URL . 'assets/css/admin.css',
				array(),
				BKX_GROUP_BOOKINGS_VERSION
			);

			wp_enqueue_script(
				'bkx-group-bookings-admin',
				BKX_GROUP_BOOKINGS_URL . 'assets/js/admin.js',
				array( 'jquery' ),
				BKX_GROUP_BOOKINGS_VERSION,
				true
			);

			wp_localize_script(
				'bkx-group-bookings-admin',
				'bkxGroupAdmin',
				array(
					'nonce' => wp_create_nonce( 'bkx_group_admin' ),
					'i18n'  => array(
						'error' => __( 'An error occurred.', 'bkx-group-bookings' ),
					),
				)
			);
		}
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		if ( ! $this->should_load_frontend_assets() ) {
			return;
		}

		wp_enqueue_style(
			'bkx-group-bookings-frontend',
			BKX_GROUP_BOOKINGS_URL . 'assets/css/frontend.css',
			array(),
			BKX_GROUP_BOOKINGS_VERSION
		);

		wp_enqueue_script(
			'bkx-group-bookings-frontend',
			BKX_GROUP_BOOKINGS_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_GROUP_BOOKINGS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-group-bookings-frontend',
			'bkxGroupFrontend',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_group_frontend' ),
				'i18n'    => array(
					'updating' => __( 'Updating...', 'bkx-group-bookings' ),
					'error'    => __( 'An error occurred.', 'bkx-group-bookings' ),
				),
			)
		);
	}

	/**
	 * Check if frontend assets should load.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function should_load_frontend_assets(): bool {
		global $post;

		if ( ! $post ) {
			return false;
		}

		// Load on booking-related pages.
		$post_types = array( 'bkx_base', 'bkx_seat', 'bkx_booking' );
		if ( in_array( $post->post_type, $post_types, true ) ) {
			return true;
		}

		// Check for shortcodes.
		if ( has_shortcode( $post->post_content, 'bookingx' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_ajax_handlers(): void {
		add_action( 'wp_ajax_bkx_calculate_group_price', array( $this, 'ajax_calculate_price' ) );
		add_action( 'wp_ajax_nopriv_bkx_calculate_group_price', array( $this, 'ajax_calculate_price' ) );

		add_action( 'wp_ajax_bkx_check_group_availability', array( $this, 'ajax_check_availability' ) );
		add_action( 'wp_ajax_nopriv_bkx_check_group_availability', array( $this, 'ajax_check_availability' ) );
	}

	/**
	 * Add quantity field to booking form.
	 *
	 * @since 1.0.0
	 * @param array $fields Form fields.
	 * @param int   $base_id Base (service) post ID.
	 * @return array
	 */
	public function add_quantity_field( array $fields, int $base_id ): array {
		$settings = $this->get_settings();

		if ( ! $settings['enable_quantity'] ) {
			return $fields;
		}

		// Get service-specific settings.
		$min_qty = (int) get_post_meta( $base_id, '_bkx_group_min_quantity', true );
		$max_qty = (int) get_post_meta( $base_id, '_bkx_group_max_quantity', true );
		$enabled = get_post_meta( $base_id, '_bkx_group_enabled', true );

		// Use defaults if not set.
		if ( ! $min_qty ) {
			$min_qty = $settings['default_min_quantity'];
		}
		if ( ! $max_qty ) {
			$max_qty = $settings['default_max_quantity'];
		}

		// Skip if not enabled for this service.
		if ( '' !== $enabled && ! $enabled ) {
			return $fields;
		}

		$label = $settings['show_quantity_label'] ? $settings['quantity_label'] : '';

		$fields['bkx_quantity'] = array(
			'type'     => 'number',
			'label'    => $label,
			'required' => true,
			'default'  => $min_qty,
			'min'      => $min_qty,
			'max'      => $max_qty,
			'priority' => 25,
			'class'    => 'bkx-quantity-field',
		);

		return $fields;
	}

	/**
	 * Calculate group total.
	 *
	 * @since 1.0.0
	 * @param float $total        Current total.
	 * @param array $booking_data Booking data.
	 * @return float
	 */
	public function calculate_group_total( float $total, array $booking_data ): float {
		$quantity = isset( $booking_data['bkx_quantity'] ) ? absint( $booking_data['bkx_quantity'] ) : 1;

		if ( $quantity <= 1 ) {
			return $total;
		}

		$base_id  = isset( $booking_data['base_id'] ) ? absint( $booking_data['base_id'] ) : 0;
		$settings = $this->get_settings();

		// Get pricing mode.
		$pricing_mode = get_post_meta( $base_id, '_bkx_group_pricing_mode', true );
		if ( ! $pricing_mode ) {
			$pricing_mode = $settings['pricing_mode'];
		}

		$service = new Services\GroupPricingService();

		return $service->calculate_price( $total, $quantity, $base_id, $pricing_mode, $settings );
	}

	/**
	 * Filter available slots by capacity.
	 *
	 * @since 1.0.0
	 * @param array $slots   Available slots.
	 * @param int   $base_id Base post ID.
	 * @param int   $seat_id Seat post ID.
	 * @return array
	 */
	public function filter_slots_by_capacity( array $slots, int $base_id, int $seat_id ): array {
		$requested_qty = isset( $_REQUEST['bkx_quantity'] ) ? absint( $_REQUEST['bkx_quantity'] ) : 1;

		if ( $requested_qty <= 1 ) {
			return $slots;
		}

		$service = new Services\GroupAvailabilityService();

		return $service->filter_by_capacity( $slots, $seat_id, $requested_qty );
	}

	/**
	 * Save group booking data.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking post ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function save_group_data( int $booking_id, array $booking_data ): void {
		$quantity = isset( $booking_data['bkx_quantity'] ) ? absint( $booking_data['bkx_quantity'] ) : 1;

		if ( $quantity > 1 ) {
			update_post_meta( $booking_id, '_bkx_group_quantity', $quantity );

			// Store participant details if provided.
			if ( ! empty( $booking_data['bkx_participants'] ) ) {
				update_post_meta( $booking_id, '_bkx_group_participants', $booking_data['bkx_participants'] );
			}
		}
	}

	/**
	 * Validate group size.
	 *
	 * @since 1.0.0
	 * @param bool|WP_Error $valid        Validation result.
	 * @param array         $booking_data Booking data.
	 * @return bool|WP_Error
	 */
	public function validate_group_size( $valid, array $booking_data ) {
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$quantity = isset( $booking_data['bkx_quantity'] ) ? absint( $booking_data['bkx_quantity'] ) : 1;
		$base_id  = isset( $booking_data['base_id'] ) ? absint( $booking_data['base_id'] ) : 0;
		$settings = $this->get_settings();

		// Get limits.
		$min_qty = (int) get_post_meta( $base_id, '_bkx_group_min_quantity', true );
		$max_qty = (int) get_post_meta( $base_id, '_bkx_group_max_quantity', true );

		if ( ! $min_qty ) {
			$min_qty = $settings['default_min_quantity'];
		}
		if ( ! $max_qty ) {
			$max_qty = $settings['default_max_quantity'];
		}

		if ( $quantity < $min_qty ) {
			return new \WP_Error(
				'group_size_below_min',
				sprintf(
					/* translators: %d: minimum quantity */
					__( 'Minimum group size is %d people.', 'bkx-group-bookings' ),
					$min_qty
				)
			);
		}

		if ( $quantity > $max_qty ) {
			return new \WP_Error(
				'group_size_above_max',
				sprintf(
					/* translators: %d: maximum quantity */
					__( 'Maximum group size is %d people.', 'bkx-group-bookings' ),
					$max_qty
				)
			);
		}

		return $valid;
	}

	/**
	 * AJAX: Calculate price.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_calculate_price(): void {
		check_ajax_referer( 'bkx_group_frontend', 'nonce' );

		$base_id  = isset( $_POST['base_id'] ) ? absint( $_POST['base_id'] ) : 0;
		$quantity = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

		if ( ! $base_id ) {
			wp_send_json_error( __( 'Invalid service.', 'bkx-group-bookings' ) );
		}

		$base_price = (float) get_post_meta( $base_id, 'base_price', true );
		$settings   = $this->get_settings();

		$pricing_mode = get_post_meta( $base_id, '_bkx_group_pricing_mode', true );
		if ( ! $pricing_mode ) {
			$pricing_mode = $settings['pricing_mode'];
		}

		$service = new Services\GroupPricingService();
		$total   = $service->calculate_price( $base_price, $quantity, $base_id, $pricing_mode, $settings );

		wp_send_json_success(
			array(
				'total'           => $total,
				'total_formatted' => ( function_exists( 'wc_price' ) ? wc_price( $total ) : '$' . number_format( $total, 2 ) ),
				'breakdown'       => $service->get_price_breakdown( $base_price, $quantity, $base_id, $pricing_mode, $settings ),
			)
		);
	}

	/**
	 * AJAX: Check availability.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_check_availability(): void {
		check_ajax_referer( 'bkx_group_frontend', 'nonce' );

		$seat_id  = isset( $_POST['seat_id'] ) ? absint( $_POST['seat_id'] ) : 0;
		$date     = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$time     = isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '';
		$quantity = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

		if ( ! $seat_id || ! $date || ! $time ) {
			wp_send_json_error( __( 'Invalid request.', 'bkx-group-bookings' ) );
		}

		$service   = new Services\GroupAvailabilityService();
		$available = $service->check_availability( $seat_id, $date, $time, $quantity );

		wp_send_json_success(
			array(
				'available'       => $available,
				'max_available'   => $service->get_max_available( $seat_id, $date, $time ),
				'message'         => $available
					? __( 'This time slot is available for your group.', 'bkx-group-bookings' )
					: __( 'This time slot cannot accommodate your group size.', 'bkx-group-bookings' ),
			)
		);
	}
}
