<?php
/**
 * Data Retention service.
 *
 * @package BookingX\GdprCompliance\Services
 */

namespace BookingX\GdprCompliance\Services;

defined( 'ABSPATH' ) || exit;

/**
 * DataRetention class.
 */
class DataRetention {

	/**
	 * Process expired data based on retention settings.
	 *
	 * @return array Summary of processed data.
	 */
	public function process_expired_data() {
		$settings = get_option( 'bkx_gdpr_settings', array() );

		if ( empty( $settings['auto_delete_expired'] ) ) {
			return array( 'skipped' => true );
		}

		$summary = array(
			'bookings_processed'  => 0,
			'consents_processed'  => 0,
			'logs_processed'      => 0,
			'requests_processed'  => 0,
		);

		// Process old bookings.
		$booking_days = absint( $settings['booking_retention_days'] ?? 730 );
		if ( $booking_days > 0 ) {
			$summary['bookings_processed'] = $this->process_old_bookings( $booking_days );
		}

		// Process old consents.
		$consent_days = absint( $settings['data_retention_days'] ?? 365 );
		if ( $consent_days > 0 ) {
			$summary['consents_processed'] = $this->process_old_consents( $consent_days );
		}

		// Process old logs.
		$summary['logs_processed'] = $this->process_old_logs( 90 );

		// Process old requests.
		$summary['requests_processed'] = $this->process_old_requests( 365 );

		do_action( 'bkx_gdpr_retention_processed', $summary );

		return $summary;
	}

	/**
	 * Process old bookings.
	 *
	 * @param int $days Retention period in days.
	 * @return int Number of processed bookings.
	 */
	private function process_old_bookings( $days ) {
		global $wpdb;

		$settings  = get_option( 'bkx_gdpr_settings', array() );
		$anonymize = ! empty( $settings['anonymize_instead_delete'] );

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Get old booking IDs.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$booking_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'bkx_booking'
				AND post_date < %s
				AND post_status IN ('bkx-completed', 'bkx-cancelled', 'bkx-missed')",
				$cutoff_date
			)
		);

		$processed = 0;

		foreach ( $booking_ids as $booking_id ) {
			// Skip already anonymized bookings.
			if ( get_post_meta( $booking_id, '_bkx_anonymized', true ) ) {
				continue;
			}

			if ( $anonymize ) {
				$this->anonymize_booking( $booking_id );
			} else {
				wp_delete_post( $booking_id, true );
			}

			$processed++;
		}

		return $processed;
	}

	/**
	 * Anonymize a booking.
	 *
	 * @param int $booking_id Booking ID.
	 */
	private function anonymize_booking( $booking_id ) {
		$anon_email = 'deleted@anonymized.invalid';
		$anon_name  = __( '[Deleted]', 'bkx-gdpr-compliance' );

		update_post_meta( $booking_id, 'customer_email', $anon_email );
		update_post_meta( $booking_id, 'customer_first_name', $anon_name );
		update_post_meta( $booking_id, 'customer_last_name', '' );
		update_post_meta( $booking_id, 'customer_phone', '' );
		update_post_meta( $booking_id, 'customer_address', '' );
		update_post_meta( $booking_id, 'booking_notes', '' );
		update_post_meta( $booking_id, '_bkx_anonymized', true );
		update_post_meta( $booking_id, '_bkx_anonymized_at', current_time( 'mysql' ) );
	}

	/**
	 * Process old consents.
	 *
	 * @param int $days Retention period in days.
	 * @return int Number of processed records.
	 */
	private function process_old_consents( $days ) {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Delete old withdrawn consents.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}bkx_consent_records
				WHERE consent_given = 0
				AND withdrawn_at < %s",
				$cutoff_date
			)
		);
	}

	/**
	 * Process old logs.
	 *
	 * @param int $days Retention period in days.
	 * @return int Number of processed records.
	 */
	private function process_old_logs( $days ) {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Delete old cookie consent logs (keep the most recent for each visitor).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE c1 FROM {$wpdb->prefix}bkx_cookie_consents c1
				INNER JOIN {$wpdb->prefix}bkx_cookie_consents c2
				ON c1.visitor_id = c2.visitor_id AND c1.id < c2.id
				WHERE c1.created_at < %s",
				$cutoff_date
			)
		);

		return $deleted;
	}

	/**
	 * Process old data requests.
	 *
	 * @param int $days Retention period in days.
	 * @return int Number of processed records.
	 */
	private function process_old_requests( $days ) {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// Delete old completed/expired requests.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}bkx_data_requests
				WHERE status IN ('completed', 'expired', 'rejected')
				AND created_at < %s",
				$cutoff_date
			)
		);

		// Delete associated export files.
		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/bkx-gdpr-exports';

		if ( file_exists( $export_dir ) ) {
			$files = glob( $export_dir . '/bkx-export-*' );
			foreach ( $files as $file ) {
				if ( filemtime( $file ) < strtotime( "-{$days} days" ) ) {
					unlink( $file );
				}
			}
		}

		return $deleted;
	}

	/**
	 * Get retention policies.
	 *
	 * @return array
	 */
	public function get_retention_policies() {
		$settings = get_option( 'bkx_gdpr_settings', array() );

		return array(
			array(
				'data_type'   => __( 'Booking Records', 'bkx-gdpr-compliance' ),
				'retention'   => sprintf(
					/* translators: %d: number of days */
					__( '%d days after completion', 'bkx-gdpr-compliance' ),
					$settings['booking_retention_days'] ?? 730
				),
				'action'      => ! empty( $settings['anonymize_instead_delete'] )
					? __( 'Anonymize', 'bkx-gdpr-compliance' )
					: __( 'Delete', 'bkx-gdpr-compliance' ),
				'legal_basis' => __( 'Legitimate interest / Contract performance', 'bkx-gdpr-compliance' ),
			),
			array(
				'data_type'   => __( 'Consent Records', 'bkx-gdpr-compliance' ),
				'retention'   => sprintf(
					/* translators: %d: number of days */
					__( '%d days after withdrawal', 'bkx-gdpr-compliance' ),
					$settings['data_retention_days'] ?? 365
				),
				'action'      => __( 'Delete', 'bkx-gdpr-compliance' ),
				'legal_basis' => __( 'Legal obligation (proof of consent)', 'bkx-gdpr-compliance' ),
			),
			array(
				'data_type'   => __( 'Cookie Consents', 'bkx-gdpr-compliance' ),
				'retention'   => __( '90 days (duplicate removal)', 'bkx-gdpr-compliance' ),
				'action'      => __( 'Delete duplicates', 'bkx-gdpr-compliance' ),
				'legal_basis' => __( 'Legal obligation (ePrivacy)', 'bkx-gdpr-compliance' ),
			),
			array(
				'data_type'   => __( 'Data Requests', 'bkx-gdpr-compliance' ),
				'retention'   => __( '365 days after completion', 'bkx-gdpr-compliance' ),
				'action'      => __( 'Delete', 'bkx-gdpr-compliance' ),
				'legal_basis' => __( 'Legal obligation (GDPR compliance proof)', 'bkx-gdpr-compliance' ),
			),
		);
	}
}
