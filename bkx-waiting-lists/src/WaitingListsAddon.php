<?php
/**
 * Waiting Lists Addon
 *
 * @package BookingX\WaitingLists
 * @since   1.0.0
 */

namespace BookingX\WaitingLists;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasAjax;
use BookingX\WaitingLists\Services\WaitingListService;
use BookingX\WaitingLists\Services\NotificationService;
use BookingX\WaitingLists\Admin\SettingsPage;

/**
 * Class WaitingListsAddon
 *
 * Main addon class for Waiting Lists.
 *
 * @since 1.0.0
 */
class WaitingListsAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasAjax;

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected string $slug = 'bkx-waiting-lists';

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
	protected string $name = 'Waiting Lists';

	/**
	 * License product ID.
	 *
	 * @var int
	 */
	protected int $product_id = 112;

	/**
	 * Waiting list service instance.
	 *
	 * @var WaitingListService
	 */
	private WaitingListService $waitlist_service;

	/**
	 * Notification service instance.
	 *
	 * @var NotificationService
	 */
	private NotificationService $notification_service;

	/**
	 * Boot the addon.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function boot(): void {
		$this->notification_service = new NotificationService( $this );
		$this->waitlist_service     = new WaitingListService( $this, $this->notification_service );

		// Hook into booking cancellation.
		add_action( 'bkx_booking_cancelled', array( $this, 'on_booking_cancelled' ), 10, 1 );
		add_action( 'bkx_booking_status_changed', array( $this, 'on_status_changed' ), 10, 3 );

		// Filter booking form to show waitlist option.
		add_filter( 'bkx_slot_unavailable_message', array( $this, 'add_waitlist_option' ), 10, 3 );
		add_action( 'bkx_after_booking_form', array( $this, 'render_waitlist_form' ) );

		// Admin.
		if ( is_admin() ) {
			$settings_page = new SettingsPage( $this );
			add_action( 'admin_menu', array( $settings_page, 'add_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		// Frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Register AJAX handlers.
		add_action( 'wp_ajax_bkx_join_waitlist', array( $this, 'ajax_join_waitlist' ) );
		add_action( 'wp_ajax_nopriv_bkx_join_waitlist', array( $this, 'ajax_join_waitlist' ) );
		add_action( 'wp_ajax_bkx_leave_waitlist', array( $this, 'ajax_leave_waitlist' ) );
		add_action( 'wp_ajax_nopriv_bkx_leave_waitlist', array( $this, 'ajax_leave_waitlist' ) );
		add_action( 'wp_ajax_bkx_confirm_waitlist', array( $this, 'ajax_confirm_waitlist' ) );
		add_action( 'wp_ajax_nopriv_bkx_confirm_waitlist', array( $this, 'ajax_confirm_waitlist' ) );

		// Cron jobs.
		add_action( 'bkx_waiting_list_cleanup', array( $this->waitlist_service, 'cleanup_old_entries' ) );
		add_action( 'bkx_waiting_list_check_expired', array( $this->waitlist_service, 'check_expired_offers' ) );

		if ( ! wp_next_scheduled( 'bkx_waiting_list_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_waiting_list_cleanup' );
		}
		if ( ! wp_next_scheduled( 'bkx_waiting_list_check_expired' ) ) {
			wp_schedule_event( time(), 'hourly', 'bkx_waiting_list_check_expired' );
		}

		// Shortcode.
		add_shortcode( 'bkx_my_waitlist', array( $this, 'render_my_waitlist_shortcode' ) );
	}

	/**
	 * Handle booking cancellation.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function on_booking_cancelled( int $booking_id ): void {
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time = get_post_meta( $booking_id, 'booking_time', true );
		$seat_id      = get_post_meta( $booking_id, 'seat_id', true );
		$service_id   = get_post_meta( $booking_id, 'base_id', true );

		if ( empty( $booking_date ) || empty( $booking_time ) ) {
			return;
		}

		// Notify next person on waiting list.
		$this->waitlist_service->process_cancellation( $seat_id, $service_id, $booking_date, $booking_time );
	}

	/**
	 * Handle booking status change.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 * @return void
	 */
	public function on_status_changed( int $booking_id, string $old_status, string $new_status ): void {
		if ( 'bkx-cancelled' === $new_status ) {
			$this->on_booking_cancelled( $booking_id );
		}
	}

	/**
	 * Add waitlist option to unavailable slot message.
	 *
	 * @since 1.0.0
	 * @param string $message  Current message.
	 * @param int    $seat_id  Seat ID.
	 * @param string $datetime Date and time.
	 * @return string
	 */
	public function add_waitlist_option( string $message, int $seat_id, string $datetime ): string {
		if ( ! $this->get_setting( 'enabled', true ) ) {
			return $message;
		}

		$message .= ' <a href="#" class="bkx-join-waitlist-link" data-seat="' . esc_attr( $seat_id ) . '" data-datetime="' . esc_attr( $datetime ) . '">';
		$message .= esc_html__( 'Join waiting list', 'bkx-waiting-lists' );
		$message .= '</a>';

		return $message;
	}

	/**
	 * Render waitlist form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_waitlist_form(): void {
		if ( ! $this->get_setting( 'enabled', true ) ) {
			return;
		}

		include BKX_WAITING_LISTS_PATH . 'templates/waitlist-form.php';
	}

	/**
	 * AJAX: Join waiting list.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_join_waitlist(): void {
		check_ajax_referer( 'bkx_waiting_list', 'nonce' );

		$seat_id    = absint( $_POST['seat_id'] ?? 0 );
		$service_id = absint( $_POST['service_id'] ?? 0 );
		$date       = sanitize_text_field( wp_unslash( $_POST['date'] ?? '' ) );
		$time       = sanitize_text_field( wp_unslash( $_POST['time'] ?? '' ) );
		$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$name       = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$phone      = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );

		if ( empty( $date ) || empty( $time ) || empty( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing required fields.', 'bkx-waiting-lists' ) ) );
		}

		$result = $this->waitlist_service->add_to_waitlist(
			array(
				'seat_id'    => $seat_id,
				'service_id' => $service_id,
				'date'       => $date,
				'time'       => $time,
				'email'      => $email,
				'name'       => $name,
				'phone'      => $phone,
				'user_id'    => get_current_user_id(),
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'  => __( 'You have been added to the waiting list. We will notify you if a spot becomes available.', 'bkx-waiting-lists' ),
				'entry_id' => $result,
			)
		);
	}

	/**
	 * AJAX: Leave waiting list.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_leave_waitlist(): void {
		check_ajax_referer( 'bkx_waiting_list', 'nonce' );

		$entry_id = absint( $_POST['entry_id'] ?? 0 );
		$token    = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );

		$result = $this->waitlist_service->remove_from_waitlist( $entry_id, $token );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'You have been removed from the waiting list.', 'bkx-waiting-lists' ) ) );
	}

	/**
	 * AJAX: Confirm waitlist offer.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_confirm_waitlist(): void {
		check_ajax_referer( 'bkx_waiting_list', 'nonce' );

		$entry_id = absint( $_POST['entry_id'] ?? 0 );
		$token    = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		$action   = sanitize_text_field( wp_unslash( $_POST['confirm_action'] ?? 'accept' ) );

		if ( 'accept' === $action ) {
			$result = $this->waitlist_service->accept_offer( $entry_id, $token );
		} else {
			$result = $this->waitlist_service->decline_offer( $entry_id, $token );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		if ( 'accept' === $action ) {
			wp_send_json_success(
				array(
					'message'     => __( 'Your booking has been confirmed! You will receive a confirmation email shortly.', 'bkx-waiting-lists' ),
					'booking_id'  => $result,
					'redirect_url' => $this->get_confirmation_url( $result ),
				)
			);
		} else {
			wp_send_json_success( array( 'message' => __( 'You have declined the offer. The next person on the waiting list will be notified.', 'bkx-waiting-lists' ) ) );
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_bkx-waiting-lists' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-waiting-lists-admin',
			BKX_WAITING_LISTS_URL . 'assets/css/admin.css',
			array(),
			BKX_WAITING_LISTS_VERSION
		);
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		if ( ! $this->get_setting( 'enabled', true ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-waiting-lists-frontend',
			BKX_WAITING_LISTS_URL . 'assets/css/frontend.css',
			array(),
			BKX_WAITING_LISTS_VERSION
		);

		wp_enqueue_script(
			'bkx-waiting-lists-frontend',
			BKX_WAITING_LISTS_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_WAITING_LISTS_VERSION,
			true
		);

		wp_localize_script(
			'bkx-waiting-lists-frontend',
			'bkxWaitlist',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_waiting_list' ),
				'i18n'    => array(
					'joining'     => __( 'Joining...', 'bkx-waiting-lists' ),
					'joined'      => __( 'Joined!', 'bkx-waiting-lists' ),
					'error'       => __( 'An error occurred.', 'bkx-waiting-lists' ),
					'confirming'  => __( 'Confirming...', 'bkx-waiting-lists' ),
				),
			)
		);
	}

	/**
	 * Render my waitlist shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_my_waitlist_shortcode( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your waiting list entries.', 'bkx-waiting-lists' ) . '</p>';
		}

		$entries = $this->waitlist_service->get_user_entries( get_current_user_id() );

		ob_start();
		include BKX_WAITING_LISTS_PATH . 'templates/my-waitlist.php';
		return ob_get_clean();
	}

	/**
	 * Get confirmation page URL.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return string
	 */
	private function get_confirmation_url( int $booking_id ): string {
		$page_id = bkx_crud_option_multisite( 'bkx_thank_you_page' );

		if ( $page_id ) {
			return add_query_arg( 'booking_id', $booking_id, get_permalink( $page_id ) );
		}

		return home_url();
	}

	/**
	 * Get waiting list service.
	 *
	 * @since 1.0.0
	 * @return WaitingListService
	 */
	public function get_waitlist_service(): WaitingListService {
		return $this->waitlist_service;
	}

	/**
	 * Get notification service.
	 *
	 * @since 1.0.0
	 * @return NotificationService
	 */
	public function get_notification_service(): NotificationService {
		return $this->notification_service;
	}
}
