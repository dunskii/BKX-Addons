<?php
/**
 * IP Manager service.
 *
 * @package BookingX\SecurityAudit
 */

namespace BookingX\SecurityAudit\Services;

defined( 'ABSPATH' ) || exit;

/**
 * IPManager class.
 */
class IPManager {

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Add IP to whitelist.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public function add_to_whitelist( $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		$whitelist = $this->settings['allowed_ips'] ?? '';
		$ips       = array_filter( array_map( 'trim', explode( "\n", $whitelist ) ) );

		if ( ! in_array( $ip, $ips, true ) ) {
			$ips[]     = $ip;
			$whitelist = implode( "\n", $ips );

			$this->settings['allowed_ips'] = $whitelist;
			update_option( 'bkx_security_audit_settings', $this->settings );
		}

		// Remove from blocklist if present.
		$this->remove_from_blocklist( $ip );

		return true;
	}

	/**
	 * Remove IP from whitelist.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public function remove_from_whitelist( $ip ) {
		$whitelist = $this->settings['allowed_ips'] ?? '';
		$ips       = array_filter( array_map( 'trim', explode( "\n", $whitelist ) ) );

		$key = array_search( $ip, $ips, true );
		if ( $key !== false ) {
			unset( $ips[ $key ] );
			$whitelist = implode( "\n", $ips );

			$this->settings['allowed_ips'] = $whitelist;
			update_option( 'bkx_security_audit_settings', $this->settings );
			return true;
		}

		return false;
	}

	/**
	 * Add IP to blocklist.
	 *
	 * @param string $ip     IP address.
	 * @param string $reason Reason for blocking.
	 * @return bool
	 */
	public function add_to_blocklist( $ip, $reason = '' ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}

		$blocklist = $this->settings['blocked_ips'] ?? '';
		$ips       = array_filter( array_map( 'trim', explode( "\n", $blocklist ) ) );

		if ( ! in_array( $ip, $ips, true ) ) {
			$ips[]     = $ip;
			$blocklist = implode( "\n", $ips );

			$this->settings['blocked_ips'] = $blocklist;
			update_option( 'bkx_security_audit_settings', $this->settings );

			// Log the block.
			$this->log_ip_block( $ip, $reason );
		}

		// Remove from whitelist if present.
		$this->remove_from_whitelist( $ip );

		return true;
	}

	/**
	 * Remove IP from blocklist.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public function remove_from_blocklist( $ip ) {
		$blocklist = $this->settings['blocked_ips'] ?? '';
		$ips       = array_filter( array_map( 'trim', explode( "\n", $blocklist ) ) );

		$key = array_search( $ip, $ips, true );
		if ( $key !== false ) {
			unset( $ips[ $key ] );
			$blocklist = implode( "\n", $ips );

			$this->settings['blocked_ips'] = $blocklist;
			update_option( 'bkx_security_audit_settings', $this->settings );
			return true;
		}

		return false;
	}

	/**
	 * Check if IP is whitelisted.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public function is_whitelisted( $ip ) {
		$whitelist = $this->settings['allowed_ips'] ?? '';
		$ips       = array_filter( array_map( 'trim', explode( "\n", $whitelist ) ) );

		return in_array( $ip, $ips, true );
	}

	/**
	 * Check if IP is blocked.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public function is_blocked( $ip ) {
		$blocklist = $this->settings['blocked_ips'] ?? '';
		$ips       = array_filter( array_map( 'trim', explode( "\n", $blocklist ) ) );

		return in_array( $ip, $ips, true );
	}

	/**
	 * Get all whitelisted IPs.
	 *
	 * @return array
	 */
	public function get_whitelist() {
		$whitelist = $this->settings['allowed_ips'] ?? '';
		return array_filter( array_map( 'trim', explode( "\n", $whitelist ) ) );
	}

	/**
	 * Get all blocked IPs.
	 *
	 * @return array
	 */
	public function get_blocklist() {
		$blocklist = $this->settings['blocked_ips'] ?? '';
		return array_filter( array_map( 'trim', explode( "\n", $blocklist ) ) );
	}

	/**
	 * Log IP block action.
	 *
	 * @param string $ip     IP address.
	 * @param string $reason Reason.
	 */
	private function log_ip_block( $ip, $reason = '' ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'bkx_security_events',
			array(
				'event_type'  => 'ip_blocked',
				'severity'    => 'medium',
				'title'       => sprintf( 'IP %s blocked', $ip ),
				'description' => $reason ?: __( 'Manually blocked by administrator.', 'bkx-security-audit' ),
				'ip_address'  => $ip,
				'user_id'     => get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}

	/**
	 * Get IP info from external service.
	 *
	 * @param string $ip IP address.
	 * @return array|false
	 */
	public function get_ip_info( $ip ) {
		$cache_key = 'bkx_ip_info_' . md5( $ip );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return $cached;
		}

		// Use ip-api.com for geolocation (free tier).
		$response = wp_remote_get(
			'http://ip-api.com/json/' . $ip . '?fields=status,message,country,regionName,city,isp,org,as,query',
			array( 'timeout' => 5 )
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || ( isset( $data['status'] ) && $data['status'] === 'fail' ) ) {
			return false;
		}

		// Cache for 24 hours.
		set_transient( $cache_key, $data, DAY_IN_SECONDS );

		return $data;
	}

	/**
	 * Check if IP is from known bad actor list.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public function is_known_bad_actor( $ip ) {
		// This would integrate with threat intelligence feeds.
		// For now, return false as we don't have API access.
		return false;
	}

	/**
	 * Bulk add IPs to blocklist.
	 *
	 * @param array  $ips    Array of IP addresses.
	 * @param string $reason Reason for blocking.
	 * @return int Number of IPs added.
	 */
	public function bulk_block( $ips, $reason = '' ) {
		$count = 0;

		foreach ( $ips as $ip ) {
			if ( $this->add_to_blocklist( $ip, $reason ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Import IPs from a text list.
	 *
	 * @param string $list   Text list of IPs (one per line).
	 * @param string $type   Type: 'whitelist' or 'blocklist'.
	 * @param string $reason Reason (for blocklist).
	 * @return int Number of IPs imported.
	 */
	public function import_list( $list, $type = 'blocklist', $reason = '' ) {
		$ips   = array_filter( array_map( 'trim', explode( "\n", $list ) ) );
		$count = 0;

		foreach ( $ips as $ip ) {
			// Skip comments.
			if ( strpos( $ip, '#' ) === 0 ) {
				continue;
			}

			// Validate IP.
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				continue;
			}

			if ( $type === 'whitelist' ) {
				if ( $this->add_to_whitelist( $ip ) ) {
					$count++;
				}
			} else {
				if ( $this->add_to_blocklist( $ip, $reason ) ) {
					$count++;
				}
			}
		}

		return $count;
	}

	/**
	 * Export IP list.
	 *
	 * @param string $type Type: 'whitelist' or 'blocklist'.
	 * @return string
	 */
	public function export_list( $type = 'blocklist' ) {
		if ( $type === 'whitelist' ) {
			return $this->settings['allowed_ips'] ?? '';
		}

		return $this->settings['blocked_ips'] ?? '';
	}
}
