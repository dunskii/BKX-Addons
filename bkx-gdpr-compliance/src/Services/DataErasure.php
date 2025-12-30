<?php
/**
 * Data Erasure service.
 *
 * @package BookingX\GdprCompliance\Services
 */

namespace BookingX\GdprCompliance\Services;

defined( 'ABSPATH' ) || exit;

/**
 * DataErasure class.
 */
class DataErasure {

	/**
	 * Erase user data.
	 *
	 * @param string $email Email address.
	 * @return array Summary of erasure.
	 */
	public function erase_user_data( $email ) {
		$settings  = get_option( 'bkx_gdpr_settings', array() );
		$anonymize = ! empty( $settings['anonymize_instead_delete'] );

		$summary = array(
			'bookings_erased'    => 0,
			'bookings_anonymized' => 0,
			'consents_erased'    => 0,
			'cookies_erased'     => 0,
			'retained'           => array(),
		);

		// Erase or anonymize bookings.
		$booking_result = $anonymize
			? $this->anonymize_bookings( $email )
			: $this->delete_bookings( $email );

		if ( $anonymize ) {
			$summary['bookings_anonymized'] = $booking_result;
		} else {
			$summary['bookings_erased'] = $booking_result;
		}

		// Erase consents.
		$summary['consents_erased'] = $this->delete_consents( $email );

		// Erase cookie consents.
		$summary['cookies_erased'] = $this->delete_cookie_consents( $email );

		// Check for retained data.
		$summary['retained'] = $this->check_retained_data( $email );

		do_action( 'bkx_gdpr_data_erased', $email, $summary );

		return $summary;
	}

	/**
	 * Delete bookings.
	 *
	 * @param string $email Email address.
	 * @return int Number of deleted bookings.
	 */
	private function delete_bookings( $email ) {
		global $wpdb;

		// Get booking IDs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$booking_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = 'customer_email' AND meta_value = %s",
				$email
			)
		);

		$deleted = 0;

		foreach ( $booking_ids as $booking_id ) {
			$post = get_post( $booking_id );
			if ( ! $post || 'bkx_booking' !== $post->post_type ) {
				continue;
			}

			// Check if booking can be deleted (not in certain statuses).
			$protected_statuses = apply_filters(
				'bkx_gdpr_protected_booking_statuses',
				array( 'bkx-completed' )
			);

			if ( in_array( $post->post_status, $protected_statuses, true ) ) {
				continue;
			}

			// Delete the booking.
			wp_delete_post( $booking_id, true );
			$deleted++;
		}

		return $deleted;
	}

	/**
	 * Anonymize bookings.
	 *
	 * @param string $email Email address.
	 * @return int Number of anonymized bookings.
	 */
	private function anonymize_bookings( $email ) {
		global $wpdb;

		// Get booking IDs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$booking_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = 'customer_email' AND meta_value = %s",
				$email
			)
		);

		$anonymized = 0;
		$anon_email = 'deleted@anonymized.invalid';
		$anon_name  = __( '[Deleted]', 'bkx-gdpr-compliance' );
		$anon_phone = '0000000000';

		foreach ( $booking_ids as $booking_id ) {
			$post = get_post( $booking_id );
			if ( ! $post || 'bkx_booking' !== $post->post_type ) {
				continue;
			}

			// Anonymize customer data.
			update_post_meta( $booking_id, 'customer_email', $anon_email );
			update_post_meta( $booking_id, 'customer_first_name', $anon_name );
			update_post_meta( $booking_id, 'customer_last_name', '' );
			update_post_meta( $booking_id, 'customer_phone', $anon_phone );
			update_post_meta( $booking_id, 'customer_address', '' );
			update_post_meta( $booking_id, 'booking_notes', '' );
			update_post_meta( $booking_id, '_bkx_anonymized', true );
			update_post_meta( $booking_id, '_bkx_anonymized_at', current_time( 'mysql' ) );

			// Delete any custom fields that might contain PII.
			$custom_fields = apply_filters( 'bkx_gdpr_booking_pii_fields', array() );
			foreach ( $custom_fields as $field ) {
				delete_post_meta( $booking_id, $field );
			}

			$anonymized++;
		}

		return $anonymized;
	}

	/**
	 * Delete consents.
	 *
	 * @param string $email Email address.
	 * @return int Number of deleted records.
	 */
	private function delete_consents( $email ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->delete(
			$wpdb->prefix . 'bkx_consent_records',
			array( 'email' => $email ),
			array( '%s' )
		);
	}

	/**
	 * Delete cookie consents.
	 *
	 * @param string $email Email address.
	 * @return int Number of deleted records.
	 */
	private function delete_cookie_consents( $email ) {
		global $wpdb;

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->delete(
			$wpdb->prefix . 'bkx_cookie_consents',
			array( 'user_id' => $user->ID ),
			array( '%d' )
		);
	}

	/**
	 * Check for retained data.
	 *
	 * @param string $email Email address.
	 * @return array List of retained data with reasons.
	 */
	private function check_retained_data( $email ) {
		global $wpdb;

		$retained = array();

		// Check for completed bookings that may need to be retained.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$completed_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(p.ID) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE pm.meta_key = 'customer_email' AND pm.meta_value = %s
				AND p.post_type = 'bkx_booking'
				AND p.post_status = 'bkx-completed'",
				$email
			)
		);

		if ( $completed_count > 0 ) {
			$retained[] = array(
				'type'   => 'bookings',
				'count'  => $completed_count,
				'reason' => __( 'Completed bookings retained for legal/accounting purposes.', 'bkx-gdpr-compliance' ),
			);
		}

		// Check for payment records.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$payment_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT pm2.post_id) FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
				WHERE pm.meta_key = 'customer_email' AND pm.meta_value = %s
				AND pm2.meta_key = 'payment_transaction_id' AND pm2.meta_value != ''",
				$email
			)
		);

		if ( $payment_count > 0 ) {
			$retained[] = array(
				'type'   => 'payment_records',
				'count'  => $payment_count,
				'reason' => __( 'Payment records retained for financial compliance.', 'bkx-gdpr-compliance' ),
			);
		}

		// Allow add-ons to report retained data.
		$retained = apply_filters( 'bkx_gdpr_retained_data', $retained, $email );

		return $retained;
	}

	/**
	 * WordPress eraser callback.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public function wp_eraser_callback( $email, $page = 1 ) {
		$result = $this->erase_user_data( $email );

		$items_removed  = $result['bookings_erased'] + $result['consents_erased'] + $result['cookies_erased'];
		$items_retained = ! empty( $result['retained'] );

		$messages = array();
		if ( $result['bookings_anonymized'] > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of bookings */
				__( '%d bookings were anonymized instead of deleted.', 'bkx-gdpr-compliance' ),
				$result['bookings_anonymized']
			);
		}

		foreach ( $result['retained'] as $retained ) {
			$messages[] = $retained['reason'];
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => $items_retained,
			'messages'       => $messages,
			'done'           => true,
		);
	}
}
