<?php
/**
 * Hold Blocks Addon
 *
 * @package BookingX\HoldBlocks
 * @since   1.0.0
 */

namespace BookingX\HoldBlocks;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasAjax;

/**
 * Main addon class for Hold Blocks.
 *
 * @since 1.0.0
 */
class HoldBlocksAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasAjax;

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected string $slug = 'bkx-hold-blocks';

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
	protected int $product_id = 115;

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
			new Admin\HoldBlocksPage( $this );
		}

		// Enqueue assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Filter availability.
		add_filter( 'bkx_available_slots', array( $this, 'filter_blocked_slots' ), 20, 3 );
		add_filter( 'bkx_is_date_available', array( $this, 'check_date_blocked' ), 10, 3 );
		add_filter( 'bkx_is_time_available', array( $this, 'check_time_blocked' ), 10, 4 );

		// Validate bookings.
		add_filter( 'bkx_validate_booking', array( $this, 'validate_against_blocks' ), 10, 2 );

		// Register AJAX handlers.
		$this->register_ajax_handlers();

		// Schedule cleanup.
		if ( ! wp_next_scheduled( 'bkx_hold_blocks_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_hold_blocks_cleanup' );
		}
		add_action( 'bkx_hold_blocks_cleanup', array( $this, 'cleanup_expired_blocks' ) );
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
			'show_blocked_as_unavailable' => 1,
			'allow_admin_override'        => 1,
			'block_reason_required'       => 0,
			'auto_cleanup_days'           => 30,
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
		if ( false === strpos( $hook, 'bkx-hold-blocks' ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-hold-blocks-admin',
			BKX_HOLD_BLOCKS_URL . 'assets/css/admin.css',
			array(),
			BKX_HOLD_BLOCKS_VERSION
		);

		wp_enqueue_script(
			'bkx-hold-blocks-admin',
			BKX_HOLD_BLOCKS_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-datepicker' ),
			BKX_HOLD_BLOCKS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-hold-blocks-admin',
			'bkxHoldBlocks',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_hold_blocks' ),
				'i18n'    => array(
					'confirmDelete' => __( 'Are you sure you want to delete this block?', 'bkx-hold-blocks' ),
					'adding'        => __( 'Adding...', 'bkx-hold-blocks' ),
					'error'         => __( 'An error occurred.', 'bkx-hold-blocks' ),
				),
			)
		);
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_ajax_handlers(): void {
		add_action( 'wp_ajax_bkx_add_hold_block', array( $this, 'ajax_add_block' ) );
		add_action( 'wp_ajax_bkx_delete_hold_block', array( $this, 'ajax_delete_block' ) );
		add_action( 'wp_ajax_bkx_get_hold_blocks', array( $this, 'ajax_get_blocks' ) );
	}

	/**
	 * Filter blocked slots from availability.
	 *
	 * @since 1.0.0
	 * @param array $slots   Available slots.
	 * @param int   $base_id Base post ID.
	 * @param int   $seat_id Seat post ID.
	 * @return array
	 */
	public function filter_blocked_slots( array $slots, int $base_id, int $seat_id ): array {
		$service = new Services\BlockService();

		foreach ( $slots as $date => $times ) {
			foreach ( $times as $time => $slot_data ) {
				if ( $service->is_blocked( $seat_id, $date, $time ) ) {
					unset( $slots[ $date ][ $time ] );
				}
			}

			// Remove empty dates.
			if ( empty( $slots[ $date ] ) ) {
				unset( $slots[ $date ] );
			}
		}

		return $slots;
	}

	/**
	 * Check if date is blocked.
	 *
	 * @since 1.0.0
	 * @param bool   $available Current availability.
	 * @param string $date      Date (Y-m-d).
	 * @param int    $seat_id   Seat post ID.
	 * @return bool
	 */
	public function check_date_blocked( bool $available, string $date, int $seat_id ): bool {
		if ( ! $available ) {
			return false;
		}

		$service = new Services\BlockService();

		return ! $service->is_date_fully_blocked( $seat_id, $date );
	}

	/**
	 * Check if time is blocked.
	 *
	 * @since 1.0.0
	 * @param bool   $available Current availability.
	 * @param string $date      Date (Y-m-d).
	 * @param string $time      Time (H:i).
	 * @param int    $seat_id   Seat post ID.
	 * @return bool
	 */
	public function check_time_blocked( bool $available, string $date, string $time, int $seat_id ): bool {
		if ( ! $available ) {
			return false;
		}

		$service = new Services\BlockService();

		return ! $service->is_blocked( $seat_id, $date, $time );
	}

	/**
	 * Validate booking against blocks.
	 *
	 * @since 1.0.0
	 * @param bool|WP_Error $valid        Validation result.
	 * @param array         $booking_data Booking data.
	 * @return bool|WP_Error
	 */
	public function validate_against_blocks( $valid, array $booking_data ) {
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$seat_id = isset( $booking_data['seat_id'] ) ? absint( $booking_data['seat_id'] ) : 0;
		$date    = isset( $booking_data['booking_date'] ) ? sanitize_text_field( $booking_data['booking_date'] ) : '';
		$time    = isset( $booking_data['booking_time'] ) ? sanitize_text_field( $booking_data['booking_time'] ) : '';

		if ( ! $seat_id || ! $date || ! $time ) {
			return $valid;
		}

		$service = new Services\BlockService();
		$block   = $service->get_active_block( $seat_id, $date, $time );

		if ( $block ) {
			$settings = $this->get_settings();

			// Allow admin override.
			if ( $settings['allow_admin_override'] && current_user_can( 'manage_options' ) ) {
				return $valid;
			}

			$reason = ! empty( $block['reason'] ) ? $block['reason'] : __( 'This time slot is unavailable.', 'bkx-hold-blocks' );

			return new \WP_Error( 'time_blocked', $reason );
		}

		return $valid;
	}

	/**
	 * AJAX: Add a block.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_add_block(): void {
		check_ajax_referer( 'bkx_hold_blocks', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-hold-blocks' ) );
		}

		$data = array(
			'seat_id'    => isset( $_POST['seat_id'] ) ? absint( $_POST['seat_id'] ) : 0,
			'start_date' => isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : '',
			'end_date'   => isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : '',
			'start_time' => isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '',
			'end_time'   => isset( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : '',
			'all_day'    => isset( $_POST['all_day'] ) && 'true' === $_POST['all_day'],
			'block_type' => isset( $_POST['block_type'] ) ? sanitize_text_field( wp_unslash( $_POST['block_type'] ) ) : 'hold',
			'reason'     => isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '',
			'recurring'  => isset( $_POST['recurring'] ) ? sanitize_text_field( wp_unslash( $_POST['recurring'] ) ) : '',
		);

		// Validate required fields.
		if ( empty( $data['start_date'] ) ) {
			wp_send_json_error( __( 'Start date is required.', 'bkx-hold-blocks' ) );
		}

		$service  = new Services\BlockService();
		$block_id = $service->add_block( $data );

		if ( ! $block_id ) {
			wp_send_json_error( __( 'Failed to create block.', 'bkx-hold-blocks' ) );
		}

		wp_send_json_success(
			array(
				'block_id' => $block_id,
				'message'  => __( 'Block created successfully.', 'bkx-hold-blocks' ),
			)
		);
	}

	/**
	 * AJAX: Delete a block.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_delete_block(): void {
		check_ajax_referer( 'bkx_hold_blocks', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-hold-blocks' ) );
		}

		$block_id = isset( $_POST['block_id'] ) ? absint( $_POST['block_id'] ) : 0;

		if ( ! $block_id ) {
			wp_send_json_error( __( 'Invalid block ID.', 'bkx-hold-blocks' ) );
		}

		$service = new Services\BlockService();
		$result  = $service->delete_block( $block_id );

		if ( ! $result ) {
			wp_send_json_error( __( 'Failed to delete block.', 'bkx-hold-blocks' ) );
		}

		wp_send_json_success( array( 'message' => __( 'Block deleted.', 'bkx-hold-blocks' ) ) );
	}

	/**
	 * AJAX: Get blocks for calendar.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_blocks(): void {
		check_ajax_referer( 'bkx_hold_blocks', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-hold-blocks' ) );
		}

		$seat_id    = isset( $_POST['seat_id'] ) ? absint( $_POST['seat_id'] ) : 0;
		$start_date = isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : '';
		$end_date   = isset( $_POST['end'] ) ? sanitize_text_field( wp_unslash( $_POST['end'] ) ) : '';

		$service = new Services\BlockService();
		$blocks  = $service->get_blocks( $seat_id, $start_date, $end_date );

		// Format for calendar.
		$events = array();
		foreach ( $blocks as $block ) {
			$events[] = array(
				'id'        => $block['id'],
				'title'     => $block['reason'] ?: $this->get_block_type_label( $block['block_type'] ),
				'start'     => $block['start_date'] . ( $block['all_day'] ? '' : 'T' . $block['start_time'] ),
				'end'       => ( $block['end_date'] ?: $block['start_date'] ) . ( $block['all_day'] ? '' : 'T' . $block['end_time'] ),
				'allDay'    => (bool) $block['all_day'],
				'color'     => $this->get_block_type_color( $block['block_type'] ),
				'blockType' => $block['block_type'],
			);
		}

		wp_send_json_success( $events );
	}

	/**
	 * Get block type label.
	 *
	 * @since 1.0.0
	 * @param string $type Block type.
	 * @return string
	 */
	private function get_block_type_label( string $type ): string {
		$types = array(
			'hold'        => __( 'Hold', 'bkx-hold-blocks' ),
			'holiday'     => __( 'Holiday', 'bkx-hold-blocks' ),
			'maintenance' => __( 'Maintenance', 'bkx-hold-blocks' ),
			'private'     => __( 'Private Event', 'bkx-hold-blocks' ),
			'break'       => __( 'Break', 'bkx-hold-blocks' ),
			'other'       => __( 'Other', 'bkx-hold-blocks' ),
		);

		return $types[ $type ] ?? $type;
	}

	/**
	 * Get block type color.
	 *
	 * @since 1.0.0
	 * @param string $type Block type.
	 * @return string
	 */
	private function get_block_type_color( string $type ): string {
		$colors = array(
			'hold'        => '#9e9e9e',
			'holiday'     => '#4caf50',
			'maintenance' => '#ff9800',
			'private'     => '#9c27b0',
			'break'       => '#2196f3',
			'other'       => '#607d8b',
		);

		return $colors[ $type ] ?? '#9e9e9e';
	}

	/**
	 * Cleanup expired blocks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function cleanup_expired_blocks(): void {
		$settings = $this->get_settings();
		$days     = absint( $settings['auto_cleanup_days'] ?? 30 );

		if ( $days <= 0 ) {
			return;
		}

		$service = new Services\BlockService();
		$service->cleanup_expired( $days );
	}
}
