<?php
/**
 * Breach Manager service.
 *
 * @package BookingX\GdprCompliance\Services
 */

namespace BookingX\GdprCompliance\Services;

defined( 'ABSPATH' ) || exit;

/**
 * BreachManager class.
 */
class BreachManager {

	/**
	 * Report a data breach.
	 *
	 * @param array $data Breach data.
	 * @return int|\WP_Error Breach ID or error.
	 */
	public function report_breach( $data ) {
		global $wpdb;

		$required = array( 'breach_date', 'discovered_date', 'nature', 'data_affected' );
		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new \WP_Error(
					'missing_field',
					sprintf(
						/* translators: %s: field name */
						__( 'Missing required field: %s', 'bkx-gdpr-compliance' ),
						$field
					)
				);
			}
		}

		$breach_data = array(
			'breach_date'       => sanitize_text_field( $data['breach_date'] ),
			'discovered_date'   => sanitize_text_field( $data['discovered_date'] ),
			'nature'            => sanitize_textarea_field( $data['nature'] ),
			'data_affected'     => sanitize_textarea_field( $data['data_affected'] ),
			'subjects_affected' => absint( $data['subjects_affected'] ?? 0 ),
			'consequences'      => sanitize_textarea_field( $data['consequences'] ?? '' ),
			'measures_taken'    => sanitize_textarea_field( $data['measures_taken'] ?? '' ),
			'reported_by'       => get_current_user_id(),
			'status'            => 'open',
			'created_at'        => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_data_breaches',
			$breach_data,
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to record breach.', 'bkx-gdpr-compliance' ) );
		}

		$breach_id = $wpdb->insert_id;

		// Send notification emails.
		$this->send_breach_notifications( $breach_id, $breach_data );

		do_action( 'bkx_gdpr_breach_reported', $breach_id, $breach_data );

		return $breach_id;
	}

	/**
	 * Update breach status.
	 *
	 * @param int    $breach_id Breach ID.
	 * @param string $status    New status.
	 * @param string $notes     Optional notes.
	 * @return bool
	 */
	public function update_status( $breach_id, $status, $notes = '' ) {
		global $wpdb;

		$valid_statuses = array( 'open', 'investigating', 'contained', 'resolved', 'closed' );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->update(
			$wpdb->prefix . 'bkx_data_breaches',
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $breach_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark DPA notification.
	 *
	 * @param int $breach_id Breach ID.
	 * @return bool
	 */
	public function mark_dpa_notified( $breach_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->update(
			$wpdb->prefix . 'bkx_data_breaches',
			array(
				'dpa_notified'    => 1,
				'dpa_notified_at' => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $breach_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Mark subjects notification.
	 *
	 * @param int $breach_id Breach ID.
	 * @return bool
	 */
	public function mark_subjects_notified( $breach_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->update(
			$wpdb->prefix . 'bkx_data_breaches',
			array(
				'subjects_notified'    => 1,
				'subjects_notified_at' => current_time( 'mysql' ),
				'updated_at'           => current_time( 'mysql' ),
			),
			array( 'id' => $breach_id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get breach by ID.
	 *
	 * @param int $breach_id Breach ID.
	 * @return object|null
	 */
	public function get_breach( $breach_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_data_breaches WHERE id = %d",
				$breach_id
			)
		);
	}

	/**
	 * Get all breaches.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_breaches( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'per_page' => 20,
			'page'     => 1,
			'orderby'  => 'breach_date',
			'order'    => 'DESC',
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$offset   = ( $args['page'] - 1 ) * $args['per_page'];
		$orderby  = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		$params[] = $args['per_page'];
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_data_breaches
				WHERE {$where}
				ORDER BY {$orderby}
				LIMIT %d OFFSET %d",
				$params
			)
		);
	}

	/**
	 * Count breaches by status.
	 *
	 * @return array
	 */
	public function count_by_status() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$wpdb->prefix}bkx_data_breaches GROUP BY status",
			ARRAY_A
		);

		$counts = array(
			'open'          => 0,
			'investigating' => 0,
			'contained'     => 0,
			'resolved'      => 0,
			'closed'        => 0,
		);

		foreach ( $results as $row ) {
			$counts[ $row['status'] ] = (int) $row['count'];
		}

		return $counts;
	}

	/**
	 * Check if DPA notification is required.
	 *
	 * @param array $breach_data Breach data.
	 * @return bool
	 */
	public function requires_dpa_notification( $breach_data ) {
		// GDPR Article 33: Notify within 72 hours unless unlikely to result in risk.
		// This is a simplified check - actual determination requires legal assessment.

		$subjects = absint( $breach_data['subjects_affected'] ?? 0 );

		// Always notify if significant number affected.
		if ( $subjects >= 100 ) {
			return true;
		}

		// Check data types affected.
		$sensitive_types = array(
			'payment',
			'financial',
			'health',
			'medical',
			'password',
			'credentials',
			'social security',
			'identity',
		);

		$data_affected = strtolower( $breach_data['data_affected'] ?? '' );
		foreach ( $sensitive_types as $type ) {
			if ( strpos( $data_affected, $type ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if subject notification is required.
	 *
	 * @param array $breach_data Breach data.
	 * @return bool
	 */
	public function requires_subject_notification( $breach_data ) {
		// GDPR Article 34: Notify if likely to result in high risk.
		return $this->requires_dpa_notification( $breach_data );
	}

	/**
	 * Get notification deadline.
	 *
	 * @param string $discovered_date Discovered date.
	 * @return string
	 */
	public function get_notification_deadline( $discovered_date ) {
		// GDPR requires 72 hours.
		return gmdate( 'Y-m-d H:i:s', strtotime( $discovered_date ) + ( 72 * HOUR_IN_SECONDS ) );
	}

	/**
	 * Send breach notifications.
	 *
	 * @param int   $breach_id   Breach ID.
	 * @param array $breach_data Breach data.
	 */
	private function send_breach_notifications( $breach_id, $breach_data ) {
		$settings = get_option( 'bkx_gdpr_settings', array() );
		$emails   = $settings['breach_notification_emails'] ?? get_option( 'admin_email' );

		if ( empty( $emails ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: %s: site name */
			__( '[URGENT] Data Breach Reported - %s', 'bkx-gdpr-compliance' ),
			get_bloginfo( 'name' )
		);

		$deadline        = $this->get_notification_deadline( $breach_data['discovered_date'] );
		$requires_dpa    = $this->requires_dpa_notification( $breach_data );
		$requires_notify = $this->requires_subject_notification( $breach_data );

		$message = sprintf(
			/* translators: breach report details */
			__(
				"A data breach has been reported and requires immediate attention.\n\n" .
				"Breach Date: %1\$s\n" .
				"Discovered: %2\$s\n" .
				"Subjects Affected: %3\$d\n\n" .
				"Nature of Breach:\n%4\$s\n\n" .
				"Data Affected:\n%5\$s\n\n" .
				"DPA Notification Required: %6\$s\n" .
				"DPA Notification Deadline: %7\$s\n" .
				"Subject Notification Required: %8\$s\n\n" .
				"Please review and take appropriate action:\n%9\$s",
				'bkx-gdpr-compliance'
			),
			$breach_data['breach_date'],
			$breach_data['discovered_date'],
			$breach_data['subjects_affected'],
			$breach_data['nature'],
			$breach_data['data_affected'],
			$requires_dpa ? __( 'YES', 'bkx-gdpr-compliance' ) : __( 'Assessment needed', 'bkx-gdpr-compliance' ),
			$deadline,
			$requires_notify ? __( 'YES', 'bkx-gdpr-compliance' ) : __( 'Assessment needed', 'bkx-gdpr-compliance' ),
			admin_url( 'edit.php?post_type=bkx_booking&page=bkx-gdpr&tab=breaches' )
		);

		wp_mail( $emails, $subject, $message );
	}
}
