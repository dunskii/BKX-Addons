<?php
/**
 * Data Request Handler service.
 *
 * @package BookingX\GdprCompliance\Services
 */

namespace BookingX\GdprCompliance\Services;

defined( 'ABSPATH' ) || exit;

/**
 * DataRequestHandler class.
 */
class DataRequestHandler {

	/**
	 * Create a new data request.
	 *
	 * @param string $email        Email address.
	 * @param string $request_type Type of request (export, erasure, etc.).
	 * @return int|\WP_Error Request ID or error.
	 */
	public function create_request( $email, $request_type ) {
		global $wpdb;

		// Check for existing pending request.
		$existing = $this->get_pending_request( $email, $request_type );
		if ( $existing ) {
			return new \WP_Error(
				'duplicate_request',
				__( 'You already have a pending request of this type. Please check your email for the verification link.', 'bkx-gdpr-compliance' )
			);
		}

		$settings = get_option( 'bkx_gdpr_settings', array() );
		$expiry_hours = $settings['request_expiry_hours'] ?? 48;

		$verification_token = $this->generate_token();
		$user_id            = email_exists( $email );

		$data = array(
			'request_type'       => $request_type,
			'email'              => $email,
			'user_id'            => $user_id ?: null,
			'status'             => 'pending_verification',
			'verification_token' => $verification_token,
			'created_at'         => current_time( 'mysql' ),
			'expires_at'         => gmdate( 'Y-m-d H:i:s', time() + ( $expiry_hours * HOUR_IN_SECONDS ) ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_data_requests',
			$data,
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create request.', 'bkx-gdpr-compliance' ) );
		}

		$request_id = $wpdb->insert_id;

		// Send verification email.
		$this->send_verification_email( $email, $request_type, $verification_token );

		do_action( 'bkx_gdpr_request_created', $request_id, $email, $request_type );

		return $request_id;
	}

	/**
	 * Verify a request.
	 *
	 * @param string $token Verification token.
	 * @return int|\WP_Error Request ID or error.
	 */
	public function verify_request( $token ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$request = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_data_requests
				WHERE verification_token = %s AND status = 'pending_verification'",
				$token
			)
		);

		if ( ! $request ) {
			return new \WP_Error( 'invalid_token', __( 'Invalid or expired verification token.', 'bkx-gdpr-compliance' ) );
		}

		if ( strtotime( $request->expires_at ) < time() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$wpdb->prefix . 'bkx_data_requests',
				array( 'status' => 'expired' ),
				array( 'id' => $request->id ),
				array( '%s' ),
				array( '%d' )
			);

			return new \WP_Error( 'request_expired', __( 'This request has expired. Please submit a new request.', 'bkx-gdpr-compliance' ) );
		}

		// Update request status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$wpdb->prefix . 'bkx_data_requests',
			array(
				'status'      => 'verified',
				'verified_at' => current_time( 'mysql' ),
			),
			array( 'id' => $request->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Notify admin.
		$this->notify_admin_new_request( $request );

		do_action( 'bkx_gdpr_request_verified', $request->id, $request->email, $request->request_type );

		return $request->id;
	}

	/**
	 * Process a verified request.
	 *
	 * @param int    $request_id Request ID.
	 * @param string $action     Action to take (approve, reject).
	 * @return bool|\WP_Error
	 */
	public function process_request( $request_id, $action = 'approve' ) {
		global $wpdb;

		$request = $this->get_request( $request_id );
		if ( ! $request ) {
			return new \WP_Error( 'not_found', __( 'Request not found.', 'bkx-gdpr-compliance' ) );
		}

		if ( 'verified' !== $request->status ) {
			return new \WP_Error( 'invalid_status', __( 'Request cannot be processed in its current state.', 'bkx-gdpr-compliance' ) );
		}

		if ( 'reject' === $action ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$wpdb->prefix . 'bkx_data_requests',
				array(
					'status'       => 'rejected',
					'processed_at' => current_time( 'mysql' ),
					'processed_by' => get_current_user_id(),
				),
				array( 'id' => $request_id ),
				array( '%s', '%s', '%d' ),
				array( '%d' )
			);

			$this->send_rejection_email( $request );
			return true;
		}

		// Process based on request type.
		$addon = \BookingX\GdprCompliance\GdprComplianceAddon::get_instance();

		switch ( $request->request_type ) {
			case 'export':
			case 'access':
			case 'portability':
				$exporter = $addon->get_service( 'export' );
				$result   = $exporter->export_user_data( $request->email, 'json' );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update(
					$wpdb->prefix . 'bkx_data_requests',
					array(
						'status'       => 'completed',
						'processed_at' => current_time( 'mysql' ),
						'processed_by' => get_current_user_id(),
						'export_file'  => $result['file'],
					),
					array( 'id' => $request_id ),
					array( '%s', '%s', '%d', '%s' ),
					array( '%d' )
				);

				$this->send_export_email( $request, $result['url'] );
				break;

			case 'erasure':
				$erasure = $addon->get_service( 'erasure' );
				$result  = $erasure->erase_user_data( $request->email );

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update(
					$wpdb->prefix . 'bkx_data_requests',
					array(
						'status'       => 'completed',
						'processed_at' => current_time( 'mysql' ),
						'processed_by' => get_current_user_id(),
						'notes'        => wp_json_encode( $result ),
					),
					array( 'id' => $request_id ),
					array( '%s', '%s', '%d', '%s' ),
					array( '%d' )
				);

				$this->send_erasure_confirmation_email( $request );
				break;

			case 'rectification':
			case 'restriction':
				// These require manual processing.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update(
					$wpdb->prefix . 'bkx_data_requests',
					array(
						'status'       => 'requires_action',
						'processed_at' => current_time( 'mysql' ),
						'processed_by' => get_current_user_id(),
					),
					array( 'id' => $request_id ),
					array( '%s', '%s', '%d' ),
					array( '%d' )
				);
				break;
		}

		do_action( 'bkx_gdpr_request_processed', $request_id, $request->email, $request->request_type );

		return true;
	}

	/**
	 * Get a request by ID.
	 *
	 * @param int $request_id Request ID.
	 * @return object|null
	 */
	public function get_request( $request_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_data_requests WHERE id = %d",
				$request_id
			)
		);
	}

	/**
	 * Get pending request.
	 *
	 * @param string $email        Email address.
	 * @param string $request_type Request type.
	 * @return object|null
	 */
	public function get_pending_request( $email, $request_type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_data_requests
				WHERE email = %s AND request_type = %s
				AND status IN ('pending_verification', 'verified')
				AND expires_at > NOW()",
				$email,
				$request_type
			)
		);
	}

	/**
	 * Get all requests.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_requests( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'type'     => '',
			'per_page' => 20,
			'page'     => 1,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['type'] ) ) {
			$where   .= ' AND request_type = %s';
			$params[] = $args['type'];
		}

		$offset   = ( $args['page'] - 1 ) * $args['per_page'];
		$orderby  = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );
		$params[] = $args['per_page'];
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_data_requests
				WHERE {$where}
				ORDER BY {$orderby}
				LIMIT %d OFFSET %d",
				$params
			)
		);
	}

	/**
	 * Count requests by status.
	 *
	 * @return array
	 */
	public function count_by_status() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count FROM {$wpdb->prefix}bkx_data_requests GROUP BY status",
			ARRAY_A
		);

		$counts = array(
			'pending_verification' => 0,
			'verified'             => 0,
			'completed'            => 0,
			'rejected'             => 0,
			'expired'              => 0,
			'requires_action'      => 0,
		);

		foreach ( $results as $row ) {
			$counts[ $row['status'] ] = (int) $row['count'];
		}

		return $counts;
	}

	/**
	 * Expire old requests.
	 */
	public function expire_old_requests() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}bkx_data_requests
				SET status = 'expired'
				WHERE status = 'pending_verification'
				AND expires_at < %s",
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Generate verification token.
	 *
	 * @return string
	 */
	private function generate_token() {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Send verification email.
	 *
	 * @param string $email        Email address.
	 * @param string $request_type Request type.
	 * @param string $token        Verification token.
	 */
	private function send_verification_email( $email, $request_type, $token ) {
		$verify_url = rest_url( 'bkx-gdpr/v1/verify/' . $token );

		$type_labels = array(
			'export'        => __( 'data export', 'bkx-gdpr-compliance' ),
			'erasure'       => __( 'data erasure', 'bkx-gdpr-compliance' ),
			'access'        => __( 'data access', 'bkx-gdpr-compliance' ),
			'rectification' => __( 'data rectification', 'bkx-gdpr-compliance' ),
			'restriction'   => __( 'processing restriction', 'bkx-gdpr-compliance' ),
			'portability'   => __( 'data portability', 'bkx-gdpr-compliance' ),
		);

		$type_label = $type_labels[ $request_type ] ?? $request_type;

		$subject = sprintf(
			/* translators: %s: site name */
			__( 'Verify your %s request', 'bkx-gdpr-compliance' ),
			$type_label
		);

		$message = sprintf(
			/* translators: 1: type label, 2: site name, 3: verify URL, 4: expiry hours */
			__(
				"You have requested a %1\$s from %2\$s.\n\n" .
				"Please click the link below to verify your request:\n\n" .
				"%3\$s\n\n" .
				"This link will expire in %4\$d hours.\n\n" .
				"If you did not make this request, you can safely ignore this email.",
				'bkx-gdpr-compliance'
			),
			$type_label,
			get_bloginfo( 'name' ),
			$verify_url,
			get_option( 'bkx_gdpr_settings' )['request_expiry_hours'] ?? 48
		);

		wp_mail( $email, $subject, $message );
	}

	/**
	 * Notify admin of new verified request.
	 *
	 * @param object $request Request object.
	 */
	private function notify_admin_new_request( $request ) {
		$admin_email = get_option( 'admin_email' );

		$subject = sprintf(
			/* translators: %s: request type */
			__( 'New GDPR %s request requires attention', 'bkx-gdpr-compliance' ),
			$request->request_type
		);

		$admin_url = admin_url( 'edit.php?post_type=bkx_booking&page=bkx-gdpr&tab=requests' );

		$message = sprintf(
			/* translators: 1: request type, 2: email, 3: admin URL */
			__(
				"A new %1\$s request has been verified and requires processing.\n\n" .
				"Email: %2\$s\n\n" .
				"Please review and process this request:\n%3\$s",
				'bkx-gdpr-compliance'
			),
			$request->request_type,
			$request->email,
			$admin_url
		);

		wp_mail( $admin_email, $subject, $message );
	}

	/**
	 * Send rejection email.
	 *
	 * @param object $request Request object.
	 */
	private function send_rejection_email( $request ) {
		$settings = get_option( 'bkx_gdpr_settings', array() );
		$dpo_email = $settings['dpo_email'] ?? get_option( 'admin_email' );

		$subject = __( 'Your data request has been reviewed', 'bkx-gdpr-compliance' );

		$message = sprintf(
			/* translators: 1: request type, 2: site name, 3: DPO email */
			__(
				"Your %1\$s request to %2\$s has been reviewed.\n\n" .
				"Unfortunately, we are unable to process your request at this time. " .
				"If you believe this is in error, please contact our Data Protection Officer at %3\$s.",
				'bkx-gdpr-compliance'
			),
			$request->request_type,
			get_bloginfo( 'name' ),
			$dpo_email
		);

		wp_mail( $request->email, $subject, $message );
	}

	/**
	 * Send export email.
	 *
	 * @param object $request     Request object.
	 * @param string $download_url Download URL.
	 */
	private function send_export_email( $request, $download_url ) {
		$subject = __( 'Your data export is ready', 'bkx-gdpr-compliance' );

		$message = sprintf(
			/* translators: 1: site name, 2: download URL, 3: expiry days */
			__(
				"Your data export from %1\$s is ready for download.\n\n" .
				"Download link: %2\$s\n\n" .
				"This link will expire in %3\$d days.\n\n" .
				"If you did not request this export, please contact us immediately.",
				'bkx-gdpr-compliance'
			),
			get_bloginfo( 'name' ),
			$download_url,
			7
		);

		wp_mail( $request->email, $subject, $message );
	}

	/**
	 * Send erasure confirmation email.
	 *
	 * @param object $request Request object.
	 */
	private function send_erasure_confirmation_email( $request ) {
		$subject = __( 'Your data has been erased', 'bkx-gdpr-compliance' );

		$message = sprintf(
			/* translators: %s: site name */
			__(
				"Your personal data has been erased from %s as requested.\n\n" .
				"Some data may have been retained for legal or legitimate business purposes as required by law.\n\n" .
				"Thank you for your request.",
				'bkx-gdpr-compliance'
			),
			get_bloginfo( 'name' )
		);

		wp_mail( $request->email, $subject, $message );
	}
}
