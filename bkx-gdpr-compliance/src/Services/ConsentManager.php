<?php
/**
 * Consent Manager service.
 *
 * @package BookingX\GdprCompliance\Services
 */

namespace BookingX\GdprCompliance\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ConsentManager class.
 */
class ConsentManager {

	/**
	 * Record consent.
	 *
	 * @param string $email        Email address.
	 * @param string $consent_type Type of consent.
	 * @param bool   $consent      Whether consent was given.
	 * @param string $text         Consent text shown to user.
	 * @param string $source       Where consent was collected.
	 * @return int|false Insert ID or false on failure.
	 */
	public function record_consent( $email, $consent_type, $consent, $text, $source = 'website' ) {
		global $wpdb;

		$user_id = email_exists( $email );

		// If withdrawing consent, update existing record.
		if ( ! $consent ) {
			return $this->withdraw_consent( $email, $consent_type );
		}

		$data = array(
			'user_id'       => $user_id ?: null,
			'email'         => $email,
			'consent_type'  => $consent_type,
			'consent_given' => 1,
			'consent_text'  => $text,
			'ip_address'    => $this->get_client_ip(),
			'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'source'        => $source,
			'version'       => BKX_GDPR_VERSION,
			'given_at'      => current_time( 'mysql' ),
			'created_at'    => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_consent_records',
			$data,
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $result ) {
			do_action( 'bkx_gdpr_consent_recorded', $wpdb->insert_id, $email, $consent_type, $consent );
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Withdraw consent.
	 *
	 * @param string $email        Email address.
	 * @param string $consent_type Type of consent.
	 * @return bool
	 */
	public function withdraw_consent( $email, $consent_type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$wpdb->prefix . 'bkx_consent_records',
			array(
				'consent_given' => 0,
				'withdrawn_at'  => current_time( 'mysql' ),
			),
			array(
				'email'         => $email,
				'consent_type'  => $consent_type,
				'consent_given' => 1,
			),
			array( '%d', '%s' ),
			array( '%s', '%s', '%d' )
		);

		if ( $result ) {
			do_action( 'bkx_gdpr_consent_withdrawn', $email, $consent_type );
		}

		return (bool) $result;
	}

	/**
	 * Check if consent is given.
	 *
	 * @param string $email        Email address.
	 * @param string $consent_type Type of consent.
	 * @return bool
	 */
	public function has_consent( $email, $consent_type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT consent_given FROM {$wpdb->prefix}bkx_consent_records
				WHERE email = %s AND consent_type = %s
				ORDER BY created_at DESC LIMIT 1",
				$email,
				$consent_type
			)
		);

		return (bool) $result;
	}

	/**
	 * Get all consents for email.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	public function get_consents( $email ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_consent_records
				WHERE email = %s
				ORDER BY created_at DESC",
				$email
			)
		);
	}

	/**
	 * Get consent history for email.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	public function get_consent_history( $email ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT consent_type, consent_given, consent_text, source, given_at, withdrawn_at, created_at
				FROM {$wpdb->prefix}bkx_consent_records
				WHERE email = %s
				ORDER BY created_at DESC",
				$email
			)
		);
	}

	/**
	 * Get active consent types for email.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	public function get_active_consents( $email ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT consent_type FROM {$wpdb->prefix}bkx_consent_records
				WHERE email = %s AND consent_given = 1 AND withdrawn_at IS NULL",
				$email
			),
			ARRAY_A
		);

		return array_column( $results, 'consent_type' );
	}

	/**
	 * Delete all consents for email.
	 *
	 * @param string $email Email address.
	 * @return int Number of deleted records.
	 */
	public function delete_consents( $email ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->delete(
			$wpdb->prefix . 'bkx_consent_records',
			array( 'email' => $email ),
			array( '%s' )
		);
	}

	/**
	 * WordPress exporter callback.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public function wp_exporter_callback( $email, $page = 1 ) {
		$consents = $this->get_consents( $email );

		if ( empty( $consents ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$export_items = array();

		foreach ( $consents as $consent ) {
			$export_items[] = array(
				'group_id'          => 'bkx-consents',
				'group_label'       => __( 'BookingX Consents', 'bkx-gdpr-compliance' ),
				'group_description' => __( 'Consent records from BookingX.', 'bkx-gdpr-compliance' ),
				'item_id'           => 'consent-' . $consent->id,
				'data'              => array(
					array(
						'name'  => __( 'Consent Type', 'bkx-gdpr-compliance' ),
						'value' => $consent->consent_type,
					),
					array(
						'name'  => __( 'Consent Given', 'bkx-gdpr-compliance' ),
						'value' => $consent->consent_given ? __( 'Yes', 'bkx-gdpr-compliance' ) : __( 'No', 'bkx-gdpr-compliance' ),
					),
					array(
						'name'  => __( 'Consent Text', 'bkx-gdpr-compliance' ),
						'value' => $consent->consent_text,
					),
					array(
						'name'  => __( 'Source', 'bkx-gdpr-compliance' ),
						'value' => $consent->source,
					),
					array(
						'name'  => __( 'Date Given', 'bkx-gdpr-compliance' ),
						'value' => $consent->given_at,
					),
					array(
						'name'  => __( 'Date Withdrawn', 'bkx-gdpr-compliance' ),
						'value' => $consent->withdrawn_at ?: __( 'N/A', 'bkx-gdpr-compliance' ),
					),
				),
			);
		}

		return array(
			'data' => $export_items,
			'done' => true,
		);
	}

	/**
	 * WordPress eraser callback.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public function wp_eraser_callback( $email, $page = 1 ) {
		$deleted = $this->delete_consents( $email );

		return array(
			'items_removed'  => $deleted,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// Handle comma-separated IPs (proxies).
		if ( strpos( $ip, ',' ) !== false ) {
			$ips = explode( ',', $ip );
			$ip  = trim( $ips[0] );
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
