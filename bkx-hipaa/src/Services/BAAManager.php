<?php
/**
 * BAA Manager Service for HIPAA Compliance.
 *
 * @package BookingX\HIPAA\Services
 */

namespace BookingX\HIPAA\Services;

defined( 'ABSPATH' ) || exit;

/**
 * BAAManager class.
 *
 * Manages Business Associate Agreements.
 */
class BAAManager {

	/**
	 * BAA statuses.
	 */
	const STATUS_PENDING  = 'pending';
	const STATUS_ACTIVE   = 'active';
	const STATUS_EXPIRED  = 'expired';
	const STATUS_REVOKED  = 'revoked';

	/**
	 * Create a BAA record.
	 *
	 * @param array $data BAA data.
	 * @return int|WP_Error
	 */
	public function create_baa( $data ) {
		global $wpdb;

		if ( empty( $data['vendor_name'] ) ) {
			return new \WP_Error( 'missing_vendor_name', __( 'Vendor name is required.', 'bkx-hipaa' ) );
		}

		if ( empty( $data['vendor_email'] ) || ! is_email( $data['vendor_email'] ) ) {
			return new \WP_Error( 'invalid_email', __( 'Valid vendor email is required.', 'bkx-hipaa' ) );
		}

		$status = self::STATUS_PENDING;
		if ( ! empty( $data['signed_date'] ) ) {
			$status = self::STATUS_ACTIVE;
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_hipaa_baa',
			array(
				'vendor_name'    => sanitize_text_field( $data['vendor_name'] ),
				'vendor_email'   => sanitize_email( $data['vendor_email'] ),
				'vendor_contact' => isset( $data['vendor_contact'] ) ? sanitize_text_field( $data['vendor_contact'] ) : '',
				'baa_status'     => $status,
				'signed_date'    => ! empty( $data['signed_date'] ) ? sanitize_text_field( $data['signed_date'] ) : null,
				'expiry_date'    => ! empty( $data['expiry_date'] ) ? sanitize_text_field( $data['expiry_date'] ) : null,
				'notes'          => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
				'created_by'     => get_current_user_id(),
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( ! $result ) {
			return new \WP_Error( 'insert_failed', __( 'Failed to create BAA.', 'bkx-hipaa' ) );
		}

		$baa_id = $wpdb->insert_id;

		// Log the BAA creation.
		$addon = \BookingX\HIPAA\HIPAAAddon::get_instance();
		$addon->get_service( 'audit_logger' )->log(
			'baa',
			'create',
			array(
				'resource_type' => 'baa',
				'resource_id'   => $baa_id,
				'metadata'      => array(
					'vendor_name' => $data['vendor_name'],
				),
			)
		);

		return $baa_id;
	}

	/**
	 * Update a BAA record.
	 *
	 * @param int   $baa_id BAA ID.
	 * @param array $data   BAA data.
	 * @return bool|WP_Error
	 */
	public function update_baa( $baa_id, $data ) {
		global $wpdb;

		$existing = $this->get_baa( $baa_id );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'BAA not found.', 'bkx-hipaa' ) );
		}

		$update_data = array(
			'updated_at' => current_time( 'mysql' ),
		);

		if ( isset( $data['vendor_name'] ) ) {
			$update_data['vendor_name'] = sanitize_text_field( $data['vendor_name'] );
		}

		if ( isset( $data['vendor_email'] ) ) {
			$update_data['vendor_email'] = sanitize_email( $data['vendor_email'] );
		}

		if ( isset( $data['vendor_contact'] ) ) {
			$update_data['vendor_contact'] = sanitize_text_field( $data['vendor_contact'] );
		}

		if ( isset( $data['baa_status'] ) ) {
			$update_data['baa_status'] = sanitize_text_field( $data['baa_status'] );
		}

		if ( isset( $data['signed_date'] ) ) {
			$update_data['signed_date'] = sanitize_text_field( $data['signed_date'] );
			if ( ! empty( $data['signed_date'] ) && $existing->baa_status === self::STATUS_PENDING ) {
				$update_data['baa_status'] = self::STATUS_ACTIVE;
			}
		}

		if ( isset( $data['expiry_date'] ) ) {
			$update_data['expiry_date'] = sanitize_text_field( $data['expiry_date'] );
		}

		if ( isset( $data['notes'] ) ) {
			$update_data['notes'] = sanitize_textarea_field( $data['notes'] );
		}

		if ( isset( $data['document_path'] ) ) {
			$update_data['document_path'] = sanitize_text_field( $data['document_path'] );
		}

		$result = $wpdb->update(
			$wpdb->prefix . 'bkx_hipaa_baa',
			$update_data,
			array( 'id' => $baa_id )
		);

		if ( false === $result ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update BAA.', 'bkx-hipaa' ) );
		}

		// Log the BAA update.
		$addon = \BookingX\HIPAA\HIPAAAddon::get_instance();
		$addon->get_service( 'audit_logger' )->log(
			'baa',
			'update',
			array(
				'resource_type' => 'baa',
				'resource_id'   => $baa_id,
				'data_before'   => (array) $existing,
				'data_after'    => $update_data,
			)
		);

		return true;
	}

	/**
	 * Get a BAA record.
	 *
	 * @param int $baa_id BAA ID.
	 * @return object|null
	 */
	public function get_baa( $baa_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_hipaa_baa WHERE id = %d",
				$baa_id
			)
		);
	}

	/**
	 * Get all BAA records.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all_baas( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status' => '',
			'limit'  => 50,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['status'] ) ) {
			$where[] = 'baa_status = %s';
			$values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			$query = $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_hipaa_baa
				WHERE {$where_clause}
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( $values, array( $args['limit'], $args['offset'] ) )
			);
		} else {
			$query = $wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_hipaa_baa
				WHERE {$where_clause}
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$args['limit'],
				$args['offset']
			);
		}

		return $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Revoke a BAA.
	 *
	 * @param int $baa_id BAA ID.
	 * @return bool|WP_Error
	 */
	public function revoke_baa( $baa_id ) {
		return $this->update_baa(
			$baa_id,
			array( 'baa_status' => self::STATUS_REVOKED )
		);
	}

	/**
	 * Check for expiring BAAs.
	 */
	public function check_expiring_baas() {
		global $wpdb;

		// Get BAAs expiring in the next 30 days.
		$expiry_date = gmdate( 'Y-m-d', strtotime( '+30 days' ) );

		$expiring = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_hipaa_baa
				WHERE baa_status = %s
				AND expiry_date IS NOT NULL
				AND expiry_date <= %s",
				self::STATUS_ACTIVE,
				$expiry_date
			)
		);

		if ( empty( $expiring ) ) {
			return;
		}

		// Send notification.
		$admin_email = get_option( 'admin_email' );
		$subject     = __( '[HIPAA] Business Associate Agreements Expiring Soon', 'bkx-hipaa' );

		$message = sprintf(
			/* translators: %d: number of BAAs */
			__( 'The following %d Business Associate Agreement(s) are expiring within 30 days:', 'bkx-hipaa' ),
			count( $expiring )
		) . "\n\n";

		foreach ( $expiring as $baa ) {
			$days_until = ceil( ( strtotime( $baa->expiry_date ) - time() ) / DAY_IN_SECONDS );
			$message .= sprintf(
				"- %s: Expires on %s (%d days)\n",
				$baa->vendor_name,
				$baa->expiry_date,
				$days_until
			);
		}

		$message .= "\n" . sprintf(
			/* translators: %s: admin URL */
			__( 'Review BAAs at: %s', 'bkx-hipaa' ),
			admin_url( 'admin.php?page=bkx-hipaa&tab=baa' )
		);

		wp_mail( $admin_email, $subject, $message );

		// Log the notification.
		$addon = \BookingX\HIPAA\HIPAAAddon::get_instance();
		$addon->get_service( 'audit_logger' )->log(
			'system',
			'baa_expiry_notification',
			array(
				'metadata' => array(
					'expiring_count' => count( $expiring ),
				),
			)
		);

		// Update expired BAAs.
		$today = gmdate( 'Y-m-d' );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}bkx_hipaa_baa
				SET baa_status = %s, updated_at = %s
				WHERE baa_status = %s AND expiry_date < %s",
				self::STATUS_EXPIRED,
				current_time( 'mysql' ),
				self::STATUS_ACTIVE,
				$today
			)
		);
	}

	/**
	 * Get BAA status labels.
	 *
	 * @return array
	 */
	public static function get_status_labels() {
		return array(
			self::STATUS_PENDING => __( 'Pending', 'bkx-hipaa' ),
			self::STATUS_ACTIVE  => __( 'Active', 'bkx-hipaa' ),
			self::STATUS_EXPIRED => __( 'Expired', 'bkx-hipaa' ),
			self::STATUS_REVOKED => __( 'Revoked', 'bkx-hipaa' ),
		);
	}

	/**
	 * Get BAA statistics.
	 *
	 * @return array
	 */
	public function get_statistics() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_hipaa_baa';

		$stats = array(
			'total'    => 0,
			'pending'  => 0,
			'active'   => 0,
			'expired'  => 0,
			'revoked'  => 0,
			'expiring' => 0,
		);

		$counts = $wpdb->get_results(
			"SELECT baa_status, COUNT(*) as count FROM {$table} GROUP BY baa_status"
		);

		foreach ( $counts as $count ) {
			$stats[ $count->baa_status ] = (int) $count->count;
			$stats['total'] += (int) $count->count;
		}

		// Count expiring within 30 days.
		$expiry_date = gmdate( 'Y-m-d', strtotime( '+30 days' ) );
		$stats['expiring'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE baa_status = %s
				AND expiry_date IS NOT NULL
				AND expiry_date <= %s",
				self::STATUS_ACTIVE,
				$expiry_date
			)
		);

		return $stats;
	}
}
