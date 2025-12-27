<?php
/**
 * Mailchimp Sync Service
 *
 * @package BookingX\MailchimpPro\Services
 * @since   1.0.0
 */

namespace BookingX\MailchimpPro\Services;

/**
 * Sync service class.
 *
 * @since 1.0.0
 */
class SyncService {

	/**
	 * Addon instance.
	 *
	 * @var \BookingX\MailchimpPro\MailchimpProAddon
	 */
	protected $addon;

	/**
	 * Mailchimp service.
	 *
	 * @var MailchimpService
	 */
	protected $mailchimp;

	/**
	 * Constructor.
	 *
	 * @param \BookingX\MailchimpPro\MailchimpProAddon $addon     Addon instance.
	 * @param MailchimpService                          $mailchimp Mailchimp service.
	 */
	public function __construct( $addon, MailchimpService $mailchimp ) {
		$this->addon     = $addon;
		$this->mailchimp = $mailchimp;
	}

	/**
	 * Handle booking created event.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return bool
	 */
	public function handle_booking_created( int $booking_id, array $booking_data ): bool {
		$customer_email = $booking_data['customer_email'] ?? '';

		if ( empty( $customer_email ) ) {
			return false;
		}

		$list_id = $this->addon->get_setting( 'default_list_id', '' );

		if ( empty( $list_id ) ) {
			return false;
		}

		// Prepare merge fields
		$merge_fields = $this->prepare_merge_fields( $booking_id, $booking_data );

		// Get tags
		$tags = [];
		$tag_on_booking = $this->addon->get_setting( 'tag_on_booking', '' );
		if ( ! empty( $tag_on_booking ) ) {
			$tags[] = $tag_on_booking;
		}

		// Add service-specific tag if enabled
		if ( ! empty( $booking_data['service_name'] ) ) {
			$tags[] = sanitize_title( $booking_data['service_name'] );
		}

		$double_optin = $this->addon->get_setting( 'double_optin', true );

		// Sync to Mailchimp
		$result = $this->mailchimp->add_or_update_subscriber(
			$list_id,
			$customer_email,
			$merge_fields,
			$tags,
			$double_optin
		);

		// Log the sync
		$this->log_sync( $booking_id, $customer_email, 'booking_created', $result );

		return ! is_wp_error( $result );
	}

	/**
	 * Handle booking status changed event.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id  Booking ID.
	 * @param string $old_status  Old status.
	 * @param string $new_status  New status.
	 * @return bool
	 */
	public function handle_booking_status_changed( int $booking_id, string $old_status, string $new_status ): bool {
		$booking = get_post( $booking_id );

		if ( ! $booking ) {
			return false;
		}

		$customer_email = get_post_meta( $booking_id, 'customer_email', true );

		if ( empty( $customer_email ) ) {
			return false;
		}

		$list_id = $this->addon->get_setting( 'default_list_id', '' );

		if ( empty( $list_id ) ) {
			return false;
		}

		// Add status-specific tag
		$tags = [ "status-{$new_status}" ];

		$result = $this->mailchimp->add_tags( $list_id, $customer_email, $tags );

		// Remove old status tag
		$old_tags = [ "status-{$old_status}" ];
		$this->mailchimp->remove_tags( $list_id, $customer_email, $old_tags );

		$this->log_sync( $booking_id, $customer_email, 'status_changed', $result );

		return ! is_wp_error( $result );
	}

	/**
	 * Handle booking completed event.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return bool
	 */
	public function handle_booking_completed( int $booking_id, array $booking_data ): bool {
		$customer_email = $booking_data['customer_email'] ?? '';

		if ( empty( $customer_email ) ) {
			return false;
		}

		$list_id = $this->addon->get_setting( 'default_list_id', '' );

		if ( empty( $list_id ) ) {
			return false;
		}

		// Add completed tag
		$tags = [];
		$tag_on_completed = $this->addon->get_setting( 'tag_on_completed', '' );
		if ( ! empty( $tag_on_completed ) ) {
			$tags[] = $tag_on_completed;
		}

		if ( ! empty( $tags ) ) {
			$result = $this->mailchimp->add_tags( $list_id, $customer_email, $tags );
			$this->log_sync( $booking_id, $customer_email, 'booking_completed', $result );
		}

		// Update merge fields with latest stats
		$merge_fields = $this->prepare_merge_fields( $booking_id, $booking_data );
		$this->mailchimp->update_merge_fields( $list_id, $customer_email, $merge_fields );

		return true;
	}

	/**
	 * Handle booking cancelled event.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return bool
	 */
	public function handle_booking_cancelled( int $booking_id, array $booking_data ): bool {
		$customer_email = $booking_data['customer_email'] ?? '';

		if ( empty( $customer_email ) ) {
			return false;
		}

		$list_id = $this->addon->get_setting( 'default_list_id', '' );

		if ( empty( $list_id ) ) {
			return false;
		}

		// Add cancelled tag
		$tags = [];
		$tag_on_cancelled = $this->addon->get_setting( 'tag_on_cancelled', '' );
		if ( ! empty( $tag_on_cancelled ) ) {
			$tags[] = $tag_on_cancelled;
		}

		if ( ! empty( $tags ) ) {
			$result = $this->mailchimp->add_tags( $list_id, $customer_email, $tags );
			$this->log_sync( $booking_id, $customer_email, 'booking_cancelled', $result );
		}

		return true;
	}

	/**
	 * Prepare merge fields for Mailchimp.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return array
	 */
	protected function prepare_merge_fields( int $booking_id, array $booking_data ): array {
		$merge_fields = [];

		$enabled_fields = $this->addon->get_setting( 'merge_fields', [] );

		// Customer name
		if ( ! empty( $booking_data['customer_name'] ) ) {
			$name_parts = explode( ' ', $booking_data['customer_name'], 2 );
			$merge_fields['FNAME'] = $name_parts[0];
			$merge_fields['LNAME'] = $name_parts[1] ?? '';
		}

		// Booking count
		if ( ! empty( $enabled_fields['BOOKINGS'] ) ) {
			$customer_email = $booking_data['customer_email'] ?? '';
			$booking_count  = $this->get_customer_booking_count( $customer_email );
			$merge_fields['BOOKINGS'] = $booking_count;
		}

		// Last booking date
		if ( ! empty( $enabled_fields['LASTBOOK'] ) ) {
			$merge_fields['LASTBOOK'] = gmdate( 'Y-m-d', strtotime( $booking_data['booking_date'] ?? 'now' ) );
		}

		// Total spent
		if ( ! empty( $enabled_fields['TOTSPENT'] ) ) {
			$customer_email = $booking_data['customer_email'] ?? '';
			$total_spent    = $this->get_customer_total_spent( $customer_email );
			$merge_fields['TOTSPENT'] = number_format( $total_spent, 2 );
		}

		return apply_filters( 'bkx_mailchimp_pro_merge_fields', $merge_fields, $booking_id, $booking_data );
	}

	/**
	 * Get customer booking count.
	 *
	 * @since 1.0.0
	 * @param string $email Customer email.
	 * @return int
	 */
	protected function get_customer_booking_count( string $email ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i pm
				INNER JOIN %i p ON pm.post_id = p.ID
				WHERE pm.meta_key = 'customer_email'
				AND pm.meta_value = %s
				AND p.post_type = 'bkx_booking'
				AND p.post_status NOT IN ('trash', 'bkx-cancelled')",
				$wpdb->postmeta,
				$wpdb->posts,
				$email
			)
		);

		return absint( $count );
	}

	/**
	 * Get customer total spent.
	 *
	 * @since 1.0.0
	 * @param string $email Customer email.
	 * @return float
	 */
	protected function get_customer_total_spent( string $email ): float {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CAST(pm2.meta_value AS DECIMAL(10,2))) FROM %i pm
				INNER JOIN %i p ON pm.post_id = p.ID
				INNER JOIN %i pm2 ON p.ID = pm2.post_id
				WHERE pm.meta_key = 'customer_email'
				AND pm.meta_value = %s
				AND pm2.meta_key = 'total_amount'
				AND p.post_type = 'bkx_booking'
				AND p.post_status = 'bkx-completed'",
				$wpdb->postmeta,
				$wpdb->posts,
				$wpdb->postmeta,
				$email
			)
		);

		return floatval( $total );
	}

	/**
	 * Run manual sync.
	 *
	 * @since 1.0.0
	 * @param string $sync_type Sync type (all, recent).
	 * @return array|\WP_Error Sync results.
	 */
	public function manual_sync( string $sync_type = 'all' ) {
		$list_id = $this->addon->get_setting( 'default_list_id', '' );

		if ( empty( $list_id ) ) {
			return new \WP_Error( 'no_list', __( 'Default list not configured.', 'bkx-mailchimp-pro' ) );
		}

		// Get bookings to sync
		$args = [
			'post_type'      => 'bkx_booking',
			'posts_per_page' => 'recent' === $sync_type ? 100 : -1,
			'post_status'    => 'any',
		];

		$bookings = get_posts( $args );

		$synced = 0;
		$failed = 0;

		foreach ( $bookings as $booking ) {
			$customer_email = get_post_meta( $booking->ID, 'customer_email', true );

			if ( empty( $customer_email ) ) {
				continue;
			}

			$booking_data = [
				'customer_email' => $customer_email,
				'customer_name'  => get_post_meta( $booking->ID, 'customer_name', true ),
				'booking_date'   => get_post_meta( $booking->ID, 'booking_date', true ),
				'service_name'   => get_the_title( get_post_meta( $booking->ID, 'service_id', true ) ),
			];

			$result = $this->handle_booking_created( $booking->ID, $booking_data );

			if ( $result ) {
				$synced++;
			} else {
				$failed++;
			}
		}

		return [
			'synced' => $synced,
			'failed' => $failed,
		];
	}

	/**
	 * Run scheduled sync.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function run_scheduled_sync(): void {
		$this->manual_sync( 'recent' );
	}

	/**
	 * Log sync activity.
	 *
	 * @since 1.0.0
	 * @param int          $booking_id Booking ID.
	 * @param string       $email      Customer email.
	 * @param string       $event      Event type.
	 * @param array|\WP_Error $result     Sync result.
	 * @return void
	 */
	protected function log_sync( int $booking_id, string $email, string $event, $result ): void {
		$table_name = $this->addon->get_table_name( 'mailchimp_sync_log' );

		$data = [
			'booking_id' => $booking_id,
			'email'      => $email,
			'event'      => $event,
			'status'     => is_wp_error( $result ) ? 'failed' : 'success',
			'message'    => is_wp_error( $result ) ? $result->get_error_message() : 'Synced successfully',
			'created_at' => current_time( 'mysql' ),
		];

		$this->addon->insert( 'mailchimp_sync_log', $data );
	}
}
