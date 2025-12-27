<?php
/**
 * Staff Breaks & Time Off Addon
 *
 * @package BookingX\StaffBreaks
 * @since   1.0.0
 */

namespace BookingX\StaffBreaks;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasAjax;
use BookingX\StaffBreaks\Services\BreaksService;
use BookingX\StaffBreaks\Services\TimeOffService;
use BookingX\StaffBreaks\Services\AvailabilityService;
use BookingX\StaffBreaks\Admin\SettingsPage;
use BookingX\StaffBreaks\Admin\SeatMetabox;

/**
 * Class StaffBreaksAddon
 *
 * Main addon class for Staff Breaks & Time Off.
 *
 * @since 1.0.0
 */
class StaffBreaksAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasAjax;

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected string $slug = 'bkx-staff-breaks';

	/**
	 * Addon version.
	 *
	 * @var string
	 */
	protected string $version = '1.0.0';

	/**
	 * Addon name.
	 *
	 * @var string
	 */
	protected string $name = 'Staff Breaks & Time Off';

	/**
	 * License product ID.
	 *
	 * @var int
	 */
	protected int $product_id = 111;

	/**
	 * Breaks service instance.
	 *
	 * @var BreaksService
	 */
	private BreaksService $breaks_service;

	/**
	 * Time off service instance.
	 *
	 * @var TimeOffService
	 */
	private TimeOffService $timeoff_service;

	/**
	 * Availability service instance.
	 *
	 * @var AvailabilityService
	 */
	private AvailabilityService $availability_service;

	/**
	 * Boot the addon.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function boot(): void {
		$this->breaks_service       = new BreaksService( $this );
		$this->timeoff_service      = new TimeOffService( $this );
		$this->availability_service = new AvailabilityService( $this, $this->breaks_service, $this->timeoff_service );

		// Filter availability slots.
		add_filter( 'bkx_available_slots', array( $this->availability_service, 'filter_available_slots' ), 10, 3 );
		add_filter( 'bkx_seat_available', array( $this->availability_service, 'check_seat_available' ), 10, 4 );

		// Admin.
		if ( is_admin() ) {
			$settings_page = new SettingsPage( $this );
			$seat_metabox  = new SeatMetabox( $this );

			add_action( 'admin_menu', array( $settings_page, 'add_menu' ) );
			add_action( 'add_meta_boxes', array( $seat_metabox, 'add_metaboxes' ) );
			add_action( 'save_post_bkx_seat', array( $seat_metabox, 'save_metabox' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		// Register AJAX handlers.
		$this->register_ajax_handlers();

		// Cron for cleanup.
		add_action( 'bkx_staff_breaks_cleanup', array( $this, 'cleanup_old_entries' ) );
		if ( ! wp_next_scheduled( 'bkx_staff_breaks_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_staff_breaks_cleanup' );
		}
	}

	/**
	 * Register AJAX handlers.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_ajax_handlers(): void {
		// Breaks AJAX.
		add_action( 'wp_ajax_bkx_get_breaks', array( $this, 'ajax_get_breaks' ) );
		add_action( 'wp_ajax_bkx_save_break', array( $this, 'ajax_save_break' ) );
		add_action( 'wp_ajax_bkx_delete_break', array( $this, 'ajax_delete_break' ) );

		// Time off AJAX.
		add_action( 'wp_ajax_bkx_get_timeoff', array( $this, 'ajax_get_timeoff' ) );
		add_action( 'wp_ajax_bkx_save_timeoff', array( $this, 'ajax_save_timeoff' ) );
		add_action( 'wp_ajax_bkx_delete_timeoff', array( $this, 'ajax_delete_timeoff' ) );
		add_action( 'wp_ajax_bkx_approve_timeoff', array( $this, 'ajax_approve_timeoff' ) );
	}

	/**
	 * AJAX: Get breaks for a seat.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_breaks(): void {
		check_ajax_referer( 'bkx_staff_breaks', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-staff-breaks' ) ) );
		}

		$seat_id = absint( $_POST['seat_id'] ?? 0 );
		$breaks  = $this->breaks_service->get_breaks( $seat_id );

		wp_send_json_success( array( 'breaks' => $breaks ) );
	}

	/**
	 * AJAX: Save a break.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_save_break(): void {
		check_ajax_referer( 'bkx_staff_breaks', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-staff-breaks' ) ) );
		}

		$break_id   = absint( $_POST['break_id'] ?? 0 );
		$seat_id    = absint( $_POST['seat_id'] ?? 0 );
		$day        = sanitize_text_field( wp_unslash( $_POST['day'] ?? '' ) );
		$start_time = sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '' ) );
		$end_time   = sanitize_text_field( wp_unslash( $_POST['end_time'] ?? '' ) );
		$label      = sanitize_text_field( wp_unslash( $_POST['label'] ?? '' ) );

		$data = array(
			'seat_id'    => $seat_id,
			'day'        => $day,
			'start_time' => $start_time,
			'end_time'   => $end_time,
			'label'      => $label,
		);

		if ( $break_id > 0 ) {
			$result = $this->breaks_service->update_break( $break_id, $data );
		} else {
			$result = $this->breaks_service->add_break( $data );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'break_id' => $result ) );
	}

	/**
	 * AJAX: Delete a break.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_delete_break(): void {
		check_ajax_referer( 'bkx_staff_breaks', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-staff-breaks' ) ) );
		}

		$break_id = absint( $_POST['break_id'] ?? 0 );
		$result   = $this->breaks_service->delete_break( $break_id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete break.', 'bkx-staff-breaks' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Get time off entries.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_timeoff(): void {
		check_ajax_referer( 'bkx_staff_breaks', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-staff-breaks' ) ) );
		}

		$seat_id  = absint( $_POST['seat_id'] ?? 0 );
		$status   = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );
		$entries  = $this->timeoff_service->get_timeoff( $seat_id, $status );

		wp_send_json_success( array( 'entries' => $entries ) );
	}

	/**
	 * AJAX: Save time off entry.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_save_timeoff(): void {
		check_ajax_referer( 'bkx_staff_breaks', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-staff-breaks' ) ) );
		}

		$entry_id   = absint( $_POST['entry_id'] ?? 0 );
		$seat_id    = absint( $_POST['seat_id'] ?? 0 );
		$start_date = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) );
		$end_date   = sanitize_text_field( wp_unslash( $_POST['end_date'] ?? '' ) );
		$start_time = sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '' ) );
		$end_time   = sanitize_text_field( wp_unslash( $_POST['end_time'] ?? '' ) );
		$all_day    = ! empty( $_POST['all_day'] );
		$type       = sanitize_text_field( wp_unslash( $_POST['type'] ?? 'vacation' ) );
		$reason     = sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) );
		$recurring  = sanitize_text_field( wp_unslash( $_POST['recurring'] ?? '' ) );

		$data = array(
			'seat_id'    => $seat_id,
			'start_date' => $start_date,
			'end_date'   => $end_date,
			'start_time' => $all_day ? '00:00' : $start_time,
			'end_time'   => $all_day ? '23:59' : $end_time,
			'all_day'    => $all_day,
			'type'       => $type,
			'reason'     => $reason,
			'recurring'  => $recurring,
			'status'     => current_user_can( 'manage_options' ) ? 'approved' : 'pending',
		);

		if ( $entry_id > 0 ) {
			$result = $this->timeoff_service->update_timeoff( $entry_id, $data );
		} else {
			$result = $this->timeoff_service->add_timeoff( $data );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'entry_id' => $result ) );
	}

	/**
	 * AJAX: Delete time off entry.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_delete_timeoff(): void {
		check_ajax_referer( 'bkx_staff_breaks', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-staff-breaks' ) ) );
		}

		$entry_id = absint( $_POST['entry_id'] ?? 0 );
		$result   = $this->timeoff_service->delete_timeoff( $entry_id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete entry.', 'bkx-staff-breaks' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Approve time off entry.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_approve_timeoff(): void {
		check_ajax_referer( 'bkx_staff_breaks', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-staff-breaks' ) ) );
		}

		$entry_id = absint( $_POST['entry_id'] ?? 0 );
		$action   = sanitize_text_field( wp_unslash( $_POST['approval_action'] ?? 'approve' ) );

		$result = $this->timeoff_service->update_status(
			$entry_id,
			'approve' === $action ? 'approved' : 'rejected'
		);

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to update status.', 'bkx-staff-breaks' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		$allowed_hooks = array(
			'toplevel_page_bkx-staff-breaks',
			'post.php',
			'post-new.php',
		);

		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		// Only on bkx_seat post type.
		global $post_type;
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) && 'bkx_seat' !== $post_type ) {
			return;
		}

		wp_enqueue_style(
			'bkx-staff-breaks-admin',
			BKX_STAFF_BREAKS_URL . 'assets/css/admin.css',
			array(),
			BKX_STAFF_BREAKS_VERSION
		);

		wp_enqueue_script(
			'bkx-staff-breaks-admin',
			BKX_STAFF_BREAKS_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_STAFF_BREAKS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-staff-breaks-admin',
			'bkxStaffBreaks',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_staff_breaks' ),
				'i18n'    => array(
					'confirmDelete' => __( 'Are you sure you want to delete this?', 'bkx-staff-breaks' ),
					'saving'        => __( 'Saving...', 'bkx-staff-breaks' ),
					'saved'         => __( 'Saved!', 'bkx-staff-breaks' ),
					'error'         => __( 'An error occurred.', 'bkx-staff-breaks' ),
				),
			)
		);
	}

	/**
	 * Cleanup old time off entries.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function cleanup_old_entries(): void {
		$retention_days = $this->get_setting( 'retention_days', 90 );
		$this->timeoff_service->cleanup_old_entries( $retention_days );
	}

	/**
	 * Get breaks service.
	 *
	 * @since 1.0.0
	 * @return BreaksService
	 */
	public function get_breaks_service(): BreaksService {
		return $this->breaks_service;
	}

	/**
	 * Get time off service.
	 *
	 * @since 1.0.0
	 * @return TimeOffService
	 */
	public function get_timeoff_service(): TimeOffService {
		return $this->timeoff_service;
	}

	/**
	 * Get availability service.
	 *
	 * @since 1.0.0
	 * @return AvailabilityService
	 */
	public function get_availability_service(): AvailabilityService {
		return $this->availability_service;
	}
}
