<?php
/**
 * Cookie Consent service.
 *
 * @package BookingX\GdprCompliance\Services
 */

namespace BookingX\GdprCompliance\Services;

defined( 'ABSPATH' ) || exit;

/**
 * CookieConsent class.
 */
class CookieConsent {

	/**
	 * Save cookie consent.
	 *
	 * @param string $visitor_id Visitor identifier.
	 * @param array  $consents   Consent preferences.
	 * @return int|false Insert/Update ID or false.
	 */
	public function save_consent( $visitor_id, $consents ) {
		global $wpdb;

		$user_id = get_current_user_id();

		// Check for existing record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bkx_cookie_consents WHERE visitor_id = %s",
				$visitor_id
			)
		);

		$data = array(
			'visitor_id' => $visitor_id,
			'user_id'    => $user_id ?: null,
			'necessary'  => 1, // Always true.
			'functional' => ! empty( $consents['functional'] ) ? 1 : 0,
			'analytics'  => ! empty( $consents['analytics'] ) ? 1 : 0,
			'marketing'  => ! empty( $consents['marketing'] ) ? 1 : 0,
			'ip_address' => $this->get_client_ip(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
		);

		if ( $existing ) {
			$data['updated_at'] = current_time( 'mysql' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->update(
				$wpdb->prefix . 'bkx_cookie_consents',
				$data,
				array( 'id' => $existing->id ),
				array( '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return $result ? $existing->id : false;
		}

		$data['created_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_cookie_consents',
			$data,
			array( '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get consent for visitor.
	 *
	 * @param string $visitor_id Visitor identifier.
	 * @return object|null
	 */
	public function get_consent( $visitor_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_cookie_consents WHERE visitor_id = %s",
				$visitor_id
			)
		);
	}

	/**
	 * Check if category is consented.
	 *
	 * @param string $visitor_id Visitor identifier.
	 * @param string $category   Cookie category.
	 * @return bool
	 */
	public function has_consent( $visitor_id, $category ) {
		$consent = $this->get_consent( $visitor_id );

		if ( ! $consent ) {
			return false;
		}

		return (bool) ( $consent->$category ?? false );
	}

	/**
	 * Delete consent for visitor.
	 *
	 * @param string $visitor_id Visitor identifier.
	 * @return int|false
	 */
	public function delete_consent( $visitor_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->delete(
			$wpdb->prefix . 'bkx_cookie_consents',
			array( 'visitor_id' => $visitor_id ),
			array( '%s' )
		);
	}

	/**
	 * Get cookie categories.
	 *
	 * @return array
	 */
	public function get_categories() {
		return apply_filters(
			'bkx_gdpr_cookie_categories',
			array(
				'necessary'  => array(
					'label'       => __( 'Necessary', 'bkx-gdpr-compliance' ),
					'description' => __( 'Essential for the website to function properly. Cannot be disabled.', 'bkx-gdpr-compliance' ),
					'required'    => true,
				),
				'functional' => array(
					'label'       => __( 'Functional', 'bkx-gdpr-compliance' ),
					'description' => __( 'Enable enhanced functionality and personalization.', 'bkx-gdpr-compliance' ),
					'required'    => false,
				),
				'analytics'  => array(
					'label'       => __( 'Analytics', 'bkx-gdpr-compliance' ),
					'description' => __( 'Help us understand how visitors interact with the website.', 'bkx-gdpr-compliance' ),
					'required'    => false,
				),
				'marketing'  => array(
					'label'       => __( 'Marketing', 'bkx-gdpr-compliance' ),
					'description' => __( 'Used to deliver relevant advertisements and track campaign effectiveness.', 'bkx-gdpr-compliance' ),
					'required'    => false,
				),
			)
		);
	}

	/**
	 * Get cookies by category.
	 *
	 * @return array
	 */
	public function get_cookies_by_category() {
		return apply_filters(
			'bkx_gdpr_cookies_list',
			array(
				'necessary'  => array(
					array(
						'name'        => 'wordpress_*',
						'provider'    => __( 'WordPress', 'bkx-gdpr-compliance' ),
						'purpose'     => __( 'Session management', 'bkx-gdpr-compliance' ),
						'expiry'      => __( 'Session', 'bkx-gdpr-compliance' ),
					),
					array(
						'name'        => 'wp-settings-*',
						'provider'    => __( 'WordPress', 'bkx-gdpr-compliance' ),
						'purpose'     => __( 'User preferences', 'bkx-gdpr-compliance' ),
						'expiry'      => __( '1 year', 'bkx-gdpr-compliance' ),
					),
					array(
						'name'        => 'bkx_cookie_consent',
						'provider'    => __( 'BookingX', 'bkx-gdpr-compliance' ),
						'purpose'     => __( 'Store cookie preferences', 'bkx-gdpr-compliance' ),
						'expiry'      => __( '1 year', 'bkx-gdpr-compliance' ),
					),
				),
				'functional' => array(
					array(
						'name'        => 'bkx_*',
						'provider'    => __( 'BookingX', 'bkx-gdpr-compliance' ),
						'purpose'     => __( 'Booking preferences', 'bkx-gdpr-compliance' ),
						'expiry'      => __( '30 days', 'bkx-gdpr-compliance' ),
					),
				),
				'analytics'  => array(
					array(
						'name'        => '_ga, _gid',
						'provider'    => __( 'Google Analytics', 'bkx-gdpr-compliance' ),
						'purpose'     => __( 'Website analytics', 'bkx-gdpr-compliance' ),
						'expiry'      => __( '2 years / 24 hours', 'bkx-gdpr-compliance' ),
					),
				),
				'marketing'  => array(
					array(
						'name'        => '_fbp',
						'provider'    => __( 'Facebook', 'bkx-gdpr-compliance' ),
						'purpose'     => __( 'Advertising tracking', 'bkx-gdpr-compliance' ),
						'expiry'      => __( '3 months', 'bkx-gdpr-compliance' ),
					),
				),
			)
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

		if ( strpos( $ip, ',' ) !== false ) {
			$ips = explode( ',', $ip );
			$ip  = trim( $ips[0] );
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}
}
