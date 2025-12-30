<?php
/**
 * Access Control Service for HIPAA Compliance.
 *
 * @package BookingX\HIPAA\Services
 */

namespace BookingX\HIPAA\Services;

defined( 'ABSPATH' ) || exit;

/**
 * AccessControl class.
 */
class AccessControl {

	/**
	 * Access levels.
	 *
	 * @var array
	 */
	const ACCESS_LEVELS = array(
		'none'        => 0,
		'view_own'    => 1,
		'view_all'    => 2,
		'edit_own'    => 3,
		'edit_all'    => 4,
		'admin'       => 5,
		'super_admin' => 6,
	);

	/**
	 * Check if user can access PHI.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function can_access_phi( $user_id ) {
		if ( ! $user_id ) {
			return false;
		}

		// Super admins always have access.
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		$access = $this->get_user_access( $user_id );

		if ( ! $access || ! $access->is_active ) {
			return false;
		}

		// Check if access has expired.
		if ( $access->expires_at && strtotime( $access->expires_at ) < time() ) {
			return false;
		}

		// At least view_all level required for PHI access.
		return self::ACCESS_LEVELS[ $access->access_level ] >= self::ACCESS_LEVELS['view_all'];
	}

	/**
	 * Check if user can access specific PHI field.
	 *
	 * @param int    $user_id User ID.
	 * @param string $field   PHI field name.
	 * @return bool
	 */
	public function can_access_phi_field( $user_id, $field ) {
		if ( ! $this->can_access_phi( $user_id ) ) {
			return false;
		}

		$access = $this->get_user_access( $user_id );

		if ( ! $access ) {
			return false;
		}

		// If no field restrictions, allow all.
		if ( empty( $access->phi_fields ) ) {
			return true;
		}

		$allowed_fields = json_decode( $access->phi_fields, true );
		return in_array( $field, $allowed_fields, true );
	}

	/**
	 * Get user access record.
	 *
	 * @param int $user_id User ID.
	 * @return object|null
	 */
	public function get_user_access( $user_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_hipaa_access_control
				WHERE user_id = %d AND is_active = 1
				ORDER BY id DESC LIMIT 1",
				$user_id
			)
		);
	}

	/**
	 * Set user access.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $access_level Access level.
	 * @param array  $phi_fields   Allowed PHI fields.
	 * @param array  $restrictions Additional restrictions.
	 * @return int|WP_Error
	 */
	public function set_user_access( $user_id, $access_level, $phi_fields = array(), $restrictions = array() ) {
		global $wpdb;

		if ( ! array_key_exists( $access_level, self::ACCESS_LEVELS ) ) {
			return new \WP_Error( 'invalid_access_level', __( 'Invalid access level.', 'bkx-hipaa' ) );
		}

		// Deactivate existing access.
		$wpdb->update(
			$wpdb->prefix . 'bkx_hipaa_access_control',
			array( 'is_active' => 0 ),
			array( 'user_id' => $user_id )
		);

		// Create new access record.
		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_hipaa_access_control',
			array(
				'user_id'      => $user_id,
				'access_level' => $access_level,
				'phi_fields'   => ! empty( $phi_fields ) ? wp_json_encode( $phi_fields ) : null,
				'restrictions' => ! empty( $restrictions ) ? wp_json_encode( $restrictions ) : null,
				'granted_by'   => get_current_user_id(),
				'granted_at'   => current_time( 'mysql' ),
				'is_active'    => 1,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%d' )
		);

		if ( ! $result ) {
			return new \WP_Error( 'insert_failed', __( 'Failed to set access control.', 'bkx-hipaa' ) );
		}

		// Log the access change.
		$addon = \BookingX\HIPAA\HIPAAAddon::get_instance();
		$addon->get_service( 'audit_logger' )->log(
			'access_control',
			'grant',
			array(
				'resource_type' => 'user',
				'resource_id'   => $user_id,
				'metadata'      => array(
					'access_level' => $access_level,
					'phi_fields'   => $phi_fields,
				),
			)
		);

		return $wpdb->insert_id;
	}

	/**
	 * Revoke user access.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function revoke_access( $user_id ) {
		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'bkx_hipaa_access_control',
			array( 'is_active' => 0 ),
			array( 'user_id' => $user_id )
		);

		// Log the access revocation.
		$addon = \BookingX\HIPAA\HIPAAAddon::get_instance();
		$addon->get_service( 'audit_logger' )->log(
			'access_control',
			'revoke',
			array(
				'resource_type' => 'user',
				'resource_id'   => $user_id,
			)
		);

		return $result !== false;
	}

	/**
	 * Get all access records.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_all_access( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'is_active' => true,
			'limit'     => 50,
			'offset'    => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );

		if ( $args['is_active'] ) {
			$where[] = 'is_active = 1';
		}

		$where_clause = implode( ' AND ', $where );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ac.*, u.user_login, u.display_name
				FROM {$wpdb->prefix}bkx_hipaa_access_control ac
				LEFT JOIN {$wpdb->users} u ON ac.user_id = u.ID
				WHERE {$where_clause}
				ORDER BY ac.granted_at DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$args['limit'],
				$args['offset']
			)
		);
	}

	/**
	 * Send access review reminder.
	 */
	public function send_review_reminder() {
		$settings = get_option( 'bkx_hipaa_settings', array() );
		$review_days = isset( $settings['access_review_days'] ) ? $settings['access_review_days'] : 90;

		$due_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$review_days} days" ) );

		global $wpdb;

		$pending_reviews = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ac.*, u.user_email, u.display_name
				FROM {$wpdb->prefix}bkx_hipaa_access_control ac
				LEFT JOIN {$wpdb->users} u ON ac.user_id = u.ID
				WHERE ac.is_active = 1 AND ac.granted_at <= %s",
				$due_date
			)
		);

		if ( empty( $pending_reviews ) ) {
			return;
		}

		// Get admin email.
		$admin_email = get_option( 'admin_email' );

		// Build email content.
		$subject = __( '[HIPAA] Access Control Review Required', 'bkx-hipaa' );
		$message = sprintf(
			/* translators: %d: number of users */
			__( 'The following %d user(s) require access control review:', 'bkx-hipaa' ),
			count( $pending_reviews )
		) . "\n\n";

		foreach ( $pending_reviews as $review ) {
			$message .= sprintf(
				"- %s (%s) - Access Level: %s - Granted: %s\n",
				$review->display_name,
				$review->user_email,
				$review->access_level,
				$review->granted_at
			);
		}

		$message .= "\n" . sprintf(
			/* translators: %s: admin URL */
			__( 'Review access controls at: %s', 'bkx-hipaa' ),
			admin_url( 'admin.php?page=bkx-hipaa&tab=access' )
		);

		wp_mail( $admin_email, $subject, $message );

		// Log the review reminder.
		$addon = \BookingX\HIPAA\HIPAAAddon::get_instance();
		$addon->get_service( 'audit_logger' )->log(
			'system',
			'access_review_reminder',
			array(
				'metadata' => array(
					'users_pending' => count( $pending_reviews ),
				),
			)
		);
	}

	/**
	 * Get available access levels.
	 *
	 * @return array
	 */
	public function get_access_levels() {
		return array(
			'none'        => __( 'No Access', 'bkx-hipaa' ),
			'view_own'    => __( 'View Own Records', 'bkx-hipaa' ),
			'view_all'    => __( 'View All Records', 'bkx-hipaa' ),
			'edit_own'    => __( 'Edit Own Records', 'bkx-hipaa' ),
			'edit_all'    => __( 'Edit All Records', 'bkx-hipaa' ),
			'admin'       => __( 'Administrator', 'bkx-hipaa' ),
			'super_admin' => __( 'Super Administrator', 'bkx-hipaa' ),
		);
	}
}
