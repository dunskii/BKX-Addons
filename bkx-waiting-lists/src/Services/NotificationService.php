<?php
/**
 * Notification Service
 *
 * @package BookingX\WaitingLists
 * @since   1.0.0
 */

namespace BookingX\WaitingLists\Services;

use BookingX\WaitingLists\WaitingListsAddon;

/**
 * Class NotificationService
 *
 * Handles waitlist email notifications.
 *
 * @since 1.0.0
 */
class NotificationService {

	/**
	 * Addon instance.
	 *
	 * @var WaitingListsAddon
	 */
	private WaitingListsAddon $addon;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param WaitingListsAddon $addon Addon instance.
	 */
	public function __construct( WaitingListsAddon $addon ) {
		global $wpdb;
		$this->addon = $addon;
		$this->table = $wpdb->prefix . 'bkx_waiting_list';
	}

	/**
	 * Send joined notification.
	 *
	 * @since 1.0.0
	 * @param int $entry_id Entry ID.
	 * @return bool
	 */
	public function send_joined_notification( int $entry_id ): bool {
		$entry = $this->get_entry( $entry_id );
		if ( ! $entry ) {
			return false;
		}

		$service = get_post( $entry['service_id'] );
		$seat    = get_post( $entry['seat_id'] );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] You have joined the waiting list', 'bkx-waiting-lists' ),
			get_bloginfo( 'name' )
		);

		$message = $this->get_joined_template( $entry, $service, $seat );

		return $this->send_email( $entry['customer_email'], $subject, $message );
	}

	/**
	 * Send offer notification.
	 *
	 * @since 1.0.0
	 * @param int $entry_id Entry ID.
	 * @return bool
	 */
	public function send_offer_notification( int $entry_id ): bool {
		$entry = $this->get_entry( $entry_id );
		if ( ! $entry ) {
			return false;
		}

		$service = get_post( $entry['service_id'] );
		$seat    = get_post( $entry['seat_id'] );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] A spot is available!', 'bkx-waiting-lists' ),
			get_bloginfo( 'name' )
		);

		$message = $this->get_offer_template( $entry, $service, $seat );

		return $this->send_email( $entry['customer_email'], $subject, $message );
	}

	/**
	 * Send accepted notification.
	 *
	 * @since 1.0.0
	 * @param int $entry_id   Entry ID.
	 * @param int $booking_id Booking ID.
	 * @return bool
	 */
	public function send_accepted_notification( int $entry_id, int $booking_id ): bool {
		$entry = $this->get_entry( $entry_id );
		if ( ! $entry ) {
			return false;
		}

		$service = get_post( $entry['service_id'] );
		$seat    = get_post( $entry['seat_id'] );

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[%s] Booking Confirmed!', 'bkx-waiting-lists' ),
			get_bloginfo( 'name' )
		);

		$message = $this->get_accepted_template( $entry, $service, $seat, $booking_id );

		return $this->send_email( $entry['customer_email'], $subject, $message );
	}

	/**
	 * Get joined email template.
	 *
	 * @since 1.0.0
	 * @param array     $entry   Entry data.
	 * @param \WP_Post  $service Service post.
	 * @param \WP_Post  $seat    Seat post.
	 * @return string
	 */
	private function get_joined_template( array $entry, ?\WP_Post $service, ?\WP_Post $seat ): string {
		$leave_url = $this->get_action_url( $entry['id'], $entry['token'], 'leave' );

		$message = sprintf(
			/* translators: %s: customer name */
			__( 'Hi %s,', 'bkx-waiting-lists' ),
			$entry['customer_name'] ?: __( 'there', 'bkx-waiting-lists' )
		) . "\n\n";

		$message .= __( 'You have successfully joined the waiting list for:', 'bkx-waiting-lists' ) . "\n\n";

		if ( $service ) {
			/* translators: %s: service name */
			$message .= sprintf( __( 'Service: %s', 'bkx-waiting-lists' ), $service->post_title ) . "\n";
		}
		if ( $seat ) {
			$seat_alias = bkx_crud_option_multisite( 'bkx_alias_seat' ) ?: __( 'Resource', 'bkx-waiting-lists' );
			/* translators: 1: resource alias, 2: resource name */
			$message .= sprintf( __( '%1$s: %2$s', 'bkx-waiting-lists' ), $seat_alias, $seat->post_title ) . "\n";
		}

		/* translators: %s: booking date */
		$message .= sprintf( __( 'Date: %s', 'bkx-waiting-lists' ), $entry['booking_date'] ) . "\n";
		/* translators: %s: booking time */
		$message .= sprintf( __( 'Time: %s', 'bkx-waiting-lists' ), substr( $entry['booking_time'], 0, 5 ) ) . "\n";
		/* translators: %s: position number */
		$message .= sprintf( __( 'Your position: #%s', 'bkx-waiting-lists' ), $entry['position'] ) . "\n\n";

		$message .= __( 'We will notify you if a spot becomes available.', 'bkx-waiting-lists' ) . "\n\n";

		$message .= __( 'To leave the waiting list, click here:', 'bkx-waiting-lists' ) . "\n";
		$message .= $leave_url . "\n\n";

		$message .= get_bloginfo( 'name' );

		return $message;
	}

	/**
	 * Get offer email template.
	 *
	 * @since 1.0.0
	 * @param array     $entry   Entry data.
	 * @param \WP_Post  $service Service post.
	 * @param \WP_Post  $seat    Seat post.
	 * @return string
	 */
	private function get_offer_template( array $entry, ?\WP_Post $service, ?\WP_Post $seat ): string {
		$accept_url  = $this->get_action_url( $entry['id'], $entry['token'], 'accept' );
		$decline_url = $this->get_action_url( $entry['id'], $entry['token'], 'decline' );

		$message = sprintf(
			/* translators: %s: customer name */
			__( 'Hi %s,', 'bkx-waiting-lists' ),
			$entry['customer_name'] ?: __( 'there', 'bkx-waiting-lists' )
		) . "\n\n";

		$message .= __( 'Great news! A spot has become available for:', 'bkx-waiting-lists' ) . "\n\n";

		if ( $service ) {
			/* translators: %s: service name */
			$message .= sprintf( __( 'Service: %s', 'bkx-waiting-lists' ), $service->post_title ) . "\n";
		}
		if ( $seat ) {
			$seat_alias = bkx_crud_option_multisite( 'bkx_alias_seat' ) ?: __( 'Resource', 'bkx-waiting-lists' );
			/* translators: 1: resource alias, 2: resource name */
			$message .= sprintf( __( '%1$s: %2$s', 'bkx-waiting-lists' ), $seat_alias, $seat->post_title ) . "\n";
		}

		/* translators: %s: booking date */
		$message .= sprintf( __( 'Date: %s', 'bkx-waiting-lists' ), $entry['booking_date'] ) . "\n";
		/* translators: %s: booking time */
		$message .= sprintf( __( 'Time: %s', 'bkx-waiting-lists' ), substr( $entry['booking_time'], 0, 5 ) ) . "\n\n";

		$expiry_hours = $this->addon->get_setting( 'offer_expiry_hours', 24 );
		/* translators: %d: hours until expiry */
		$message .= sprintf(
			__( 'This offer expires in %d hours. Please respond as soon as possible.', 'bkx-waiting-lists' ),
			$expiry_hours
		) . "\n\n";

		$message .= __( 'To accept this booking:', 'bkx-waiting-lists' ) . "\n";
		$message .= $accept_url . "\n\n";

		$message .= __( 'To decline (the spot will be offered to the next person):', 'bkx-waiting-lists' ) . "\n";
		$message .= $decline_url . "\n\n";

		$message .= get_bloginfo( 'name' );

		return $message;
	}

	/**
	 * Get accepted email template.
	 *
	 * @since 1.0.0
	 * @param array     $entry      Entry data.
	 * @param \WP_Post  $service    Service post.
	 * @param \WP_Post  $seat       Seat post.
	 * @param int       $booking_id Booking ID.
	 * @return string
	 */
	private function get_accepted_template( array $entry, ?\WP_Post $service, ?\WP_Post $seat, int $booking_id ): string {
		$message = sprintf(
			/* translators: %s: customer name */
			__( 'Hi %s,', 'bkx-waiting-lists' ),
			$entry['customer_name'] ?: __( 'there', 'bkx-waiting-lists' )
		) . "\n\n";

		$message .= __( 'Your booking has been confirmed!', 'bkx-waiting-lists' ) . "\n\n";

		/* translators: %d: booking ID */
		$message .= sprintf( __( 'Booking #%d', 'bkx-waiting-lists' ), $booking_id ) . "\n";

		if ( $service ) {
			/* translators: %s: service name */
			$message .= sprintf( __( 'Service: %s', 'bkx-waiting-lists' ), $service->post_title ) . "\n";
		}
		if ( $seat ) {
			$seat_alias = bkx_crud_option_multisite( 'bkx_alias_seat' ) ?: __( 'Resource', 'bkx-waiting-lists' );
			/* translators: 1: resource alias, 2: resource name */
			$message .= sprintf( __( '%1$s: %2$s', 'bkx-waiting-lists' ), $seat_alias, $seat->post_title ) . "\n";
		}

		/* translators: %s: booking date */
		$message .= sprintf( __( 'Date: %s', 'bkx-waiting-lists' ), $entry['booking_date'] ) . "\n";
		/* translators: %s: booking time */
		$message .= sprintf( __( 'Time: %s', 'bkx-waiting-lists' ), substr( $entry['booking_time'], 0, 5 ) ) . "\n\n";

		$message .= __( 'We look forward to seeing you!', 'bkx-waiting-lists' ) . "\n\n";

		$message .= get_bloginfo( 'name' );

		return $message;
	}

	/**
	 * Get action URL.
	 *
	 * @since 1.0.0
	 * @param int    $entry_id Entry ID.
	 * @param string $token    Security token.
	 * @param string $action   Action type.
	 * @return string
	 */
	private function get_action_url( int $entry_id, string $token, string $action ): string {
		return add_query_arg(
			array(
				'bkx_waitlist_action' => $action,
				'entry_id'            => $entry_id,
				'token'               => $token,
			),
			home_url()
		);
	}

	/**
	 * Send email.
	 *
	 * @since 1.0.0
	 * @param string $to      Recipient email.
	 * @param string $subject Email subject.
	 * @param string $message Email message.
	 * @return bool
	 */
	private function send_email( string $to, string $subject, string $message ): bool {
		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
		);

		return wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Get entry by ID.
	 *
	 * @since 1.0.0
	 * @param int $entry_id Entry ID.
	 * @return array|null
	 */
	private function get_entry( int $entry_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table} WHERE id = %d",
				$entry_id
			),
			ARRAY_A
		);

		return $entry ?: null;
	}
}
