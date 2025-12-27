<?php
/**
 * Redemption Service
 *
 * @package BookingX\BookingPackages\Services
 * @since   1.0.0
 */

namespace BookingX\BookingPackages\Services;

use BookingX\BookingPackages\BookingPackagesAddon;
use WP_Error;

/**
 * Service for managing package redemptions.
 *
 * @since 1.0.0
 */
class RedemptionService {

	/**
	 * Addon instance.
	 *
	 * @var BookingPackagesAddon
	 */
	protected BookingPackagesAddon $addon;

	/**
	 * Package service.
	 *
	 * @var PackageService
	 */
	protected PackageService $package_service;

	/**
	 * Redemptions table.
	 *
	 * @var string
	 */
	protected string $redemptions_table;

	/**
	 * Constructor.
	 *
	 * @param BookingPackagesAddon $addon Addon instance.
	 * @param PackageService       $package_service Package service.
	 */
	public function __construct( BookingPackagesAddon $addon, PackageService $package_service ) {
		global $wpdb;

		$this->addon             = $addon;
		$this->package_service   = $package_service;
		$this->redemptions_table = $wpdb->prefix . 'bkx_package_redemptions';
	}

	/**
	 * Reserve usage for a booking.
	 *
	 * @param int $customer_package_id Customer package ID.
	 * @param int $booking_id Booking ID.
	 * @param int $uses Number of uses.
	 * @return int|WP_Error Redemption ID or error.
	 */
	public function reserve_usage( int $customer_package_id, int $booking_id, int $uses = 1 ) {
		global $wpdb;

		$customer_package = $this->package_service->get_customer_package( $customer_package_id );

		if ( ! $customer_package ) {
			return new WP_Error( 'not_found', __( 'Package not found.', 'bkx-booking-packages' ) );
		}

		if ( ! $this->package_service->is_package_valid( $customer_package ) ) {
			return new WP_Error( 'invalid_package', __( 'Package is not valid.', 'bkx-booking-packages' ) );
		}

		if ( $customer_package['uses_remaining'] < $uses ) {
			return new WP_Error( 'insufficient_uses', __( 'Insufficient package uses.', 'bkx-booking-packages' ) );
		}

		// Create pending redemption.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->redemptions_table,
			array(
				'customer_package_id' => $customer_package_id,
				'booking_id'          => $booking_id,
				'uses_applied'        => $uses,
				'status'              => 'pending',
			),
			array( '%d', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to reserve usage.', 'bkx-booking-packages' ) );
		}

		$redemption_id = $wpdb->insert_id;

		// Decrement uses.
		$this->package_service->update_uses( $customer_package_id, -$uses );

		/**
		 * Fires when package usage is reserved.
		 *
		 * @param int $redemption_id Redemption ID.
		 * @param int $customer_package_id Customer package ID.
		 * @param int $booking_id Booking ID.
		 */
		do_action( 'bkx_package_usage_reserved', $redemption_id, $customer_package_id, $booking_id );

		return $redemption_id;
	}

	/**
	 * Confirm redemption.
	 *
	 * @param int $customer_package_id Customer package ID.
	 * @param int $booking_id Booking ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function confirm_redemption( int $customer_package_id, int $booking_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->redemptions_table,
			array(
				'status'      => 'confirmed',
				'redeemed_at' => current_time( 'mysql' ),
			),
			array(
				'customer_package_id' => $customer_package_id,
				'booking_id'          => $booking_id,
				'status'              => 'pending',
			),
			array( '%s', '%s' ),
			array( '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', __( 'Failed to confirm redemption.', 'bkx-booking-packages' ) );
		}

		/**
		 * Fires when package redemption is confirmed.
		 *
		 * @param int $customer_package_id Customer package ID.
		 * @param int $booking_id Booking ID.
		 */
		do_action( 'bkx_package_redemption_confirmed', $customer_package_id, $booking_id );

		return true;
	}

	/**
	 * Cancel redemption (refund uses).
	 *
	 * @param int    $customer_package_id Customer package ID.
	 * @param int    $booking_id Booking ID.
	 * @param string $reason Cancellation reason.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public function cancel_redemption( int $customer_package_id, int $booking_id, string $reason = '' ) {
		global $wpdb;

		// Get the redemption.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$redemption = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE customer_package_id = %d AND booking_id = %d',
				$this->redemptions_table,
				$customer_package_id,
				$booking_id
			),
			ARRAY_A
		);

		if ( ! $redemption ) {
			return new WP_Error( 'not_found', __( 'Redemption not found.', 'bkx-booking-packages' ) );
		}

		if ( 'cancelled' === $redemption['status'] ) {
			return new WP_Error( 'already_cancelled', __( 'Redemption already cancelled.', 'bkx-booking-packages' ) );
		}

		// Update status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->redemptions_table,
			array( 'status' => 'cancelled' ),
			array( 'id' => $redemption['id'] ),
			array( '%s' ),
			array( '%d' )
		);

		// Refund uses.
		$this->package_service->update_uses( $customer_package_id, $redemption['uses_applied'] );

		/**
		 * Fires when package redemption is cancelled.
		 *
		 * @param int    $customer_package_id Customer package ID.
		 * @param int    $booking_id Booking ID.
		 * @param string $reason Cancellation reason.
		 */
		do_action( 'bkx_package_redemption_cancelled', $customer_package_id, $booking_id, $reason );

		return true;
	}

	/**
	 * Get redemption by booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array|null Redemption or null.
	 */
	public function get_redemption_by_booking( int $booking_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE booking_id = %d',
				$this->redemptions_table,
				$booking_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get redemption history for customer package.
	 *
	 * @param int $customer_package_id Customer package ID.
	 * @return array Redemptions.
	 */
	public function get_redemption_history( int $customer_package_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE customer_package_id = %d ORDER BY created_at DESC',
				$this->redemptions_table,
				$customer_package_id
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Get customer redemption statistics.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array Statistics.
	 */
	public function get_customer_stats( int $customer_id ): array {
		global $wpdb;

		$customer_packages_table = $wpdb->prefix . 'bkx_customer_packages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(DISTINCT r.id) as total_redemptions,
					SUM(r.uses_applied) as total_uses_redeemed,
					SUM(CASE WHEN r.status = 'confirmed' THEN r.uses_applied ELSE 0 END) as confirmed_uses,
					SUM(CASE WHEN r.status = 'pending' THEN r.uses_applied ELSE 0 END) as pending_uses,
					SUM(CASE WHEN r.status = 'cancelled' THEN r.uses_applied ELSE 0 END) as cancelled_uses
				FROM %i r
				INNER JOIN %i cp ON r.customer_package_id = cp.id
				WHERE cp.customer_id = %d",
				$this->redemptions_table,
				$customer_packages_table,
				$customer_id
			),
			ARRAY_A
		);

		return $stats ?: array(
			'total_redemptions'  => 0,
			'total_uses_redeemed' => 0,
			'confirmed_uses'     => 0,
			'pending_uses'       => 0,
			'cancelled_uses'     => 0,
		);
	}

	/**
	 * Cleanup expired pending redemptions.
	 *
	 * @param int $hours Hours after which pending redemptions expire.
	 * @return int Number of cleaned up redemptions.
	 */
	public function cleanup_expired_pending( int $hours = 24 ): int {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$hours} hours" ) );

		// Get pending redemptions to refund.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE status = 'pending' AND created_at < %s",
				$this->redemptions_table,
				$cutoff
			),
			ARRAY_A
		);

		$count = 0;

		foreach ( $pending as $redemption ) {
			// Refund uses.
			$this->package_service->update_uses( $redemption['customer_package_id'], $redemption['uses_applied'] );

			// Update status.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$this->redemptions_table,
				array( 'status' => 'expired' ),
				array( 'id' => $redemption['id'] ),
				array( '%s' ),
				array( '%d' )
			);

			++$count;
		}

		return $count;
	}
}
