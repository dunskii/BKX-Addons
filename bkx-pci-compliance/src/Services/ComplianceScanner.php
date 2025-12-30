<?php
/**
 * PCI DSS Compliance Scanner Service.
 *
 * @package BookingX\PCICompliance
 */

namespace BookingX\PCICompliance\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ComplianceScanner class.
 *
 * Scans for PCI DSS compliance across all 12 requirements.
 */
class ComplianceScanner {

	/**
	 * PCI DSS Requirements.
	 *
	 * @var array
	 */
	private $requirements = array(
		'1'  => 'Install and maintain a firewall configuration',
		'2'  => 'Do not use vendor-supplied defaults',
		'3'  => 'Protect stored cardholder data',
		'4'  => 'Encrypt transmission of cardholder data',
		'5'  => 'Protect all systems against malware',
		'6'  => 'Develop and maintain secure systems',
		'7'  => 'Restrict access to cardholder data',
		'8'  => 'Identify and authenticate access',
		'9'  => 'Restrict physical access to cardholder data',
		'10' => 'Track and monitor all access',
		'11' => 'Regularly test security systems',
		'12' => 'Maintain information security policy',
	);

	/**
	 * Run a compliance scan.
	 *
	 * @param string $scan_type Scan type (full, quick, vulnerability, configuration).
	 * @return int|\WP_Error Scan ID or error.
	 */
	public function run_scan( $scan_type = 'full' ) {
		global $wpdb;

		// Create scan record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'bkx_pci_scan_results',
			array(
				'scan_type'  => $scan_type,
				'status'     => 'running',
				'started_at' => current_time( 'mysql' ),
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%d', '%s' )
		);

		$scan_id = $wpdb->insert_id;

		try {
			$results = array();

			switch ( $scan_type ) {
				case 'full':
					$results = $this->run_full_scan();
					break;

				case 'quick':
					$results = $this->run_quick_scan();
					break;

				case 'vulnerability':
					$results = $this->run_vulnerability_scan();
					break;

				case 'configuration':
					$results = $this->run_configuration_scan();
					break;
			}

			// Calculate scores.
			$passed          = 0;
			$failed          = 0;
			$na              = 0;
			$vulnerabilities = 0;
			$critical        = 0;

			foreach ( $results as $requirement => $checks ) {
				foreach ( $checks as $check ) {
					switch ( $check['status'] ) {
						case 'pass':
							$passed++;
							break;
						case 'fail':
							$failed++;
							if ( 'critical' === $check['severity'] ) {
								$critical++;
							}
							break;
						case 'na':
							$na++;
							break;
					}
				}
			}

			$total_checks = $passed + $failed;
			$score        = $total_checks > 0 ? round( ( $passed / $total_checks ) * 100, 2 ) : 0;

			// Generate recommendations.
			$recommendations = $this->generate_recommendations( $results );

			// Update scan record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'bkx_pci_scan_results',
				array(
					'status'               => 'completed',
					'overall_score'        => $score,
					'requirements_passed'  => $passed,
					'requirements_failed'  => $failed,
					'requirements_na'      => $na,
					'vulnerabilities_found' => $vulnerabilities,
					'critical_issues'      => $critical,
					'results'              => wp_json_encode( $results ),
					'recommendations'      => wp_json_encode( $recommendations ),
					'completed_at'         => current_time( 'mysql' ),
				),
				array( 'id' => $scan_id ),
				array( '%s', '%f', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);

			return $scan_id;

		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'bkx_pci_scan_results',
				array(
					'status'       => 'failed',
					'completed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $scan_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			return new \WP_Error( 'scan_failed', $e->getMessage() );
		}
	}

	/**
	 * Run full scan.
	 *
	 * @return array
	 */
	private function run_full_scan() {
		$results = array();

		// Requirement 1: Firewall configuration.
		$results['1'] = $this->check_requirement_1();

		// Requirement 2: Default credentials.
		$results['2'] = $this->check_requirement_2();

		// Requirement 3: Stored cardholder data.
		$results['3'] = $this->check_requirement_3();

		// Requirement 4: Encryption in transit.
		$results['4'] = $this->check_requirement_4();

		// Requirement 5: Malware protection.
		$results['5'] = $this->check_requirement_5();

		// Requirement 6: Secure development.
		$results['6'] = $this->check_requirement_6();

		// Requirement 7: Access restriction.
		$results['7'] = $this->check_requirement_7();

		// Requirement 8: Authentication.
		$results['8'] = $this->check_requirement_8();

		// Requirement 9: Physical access.
		$results['9'] = $this->check_requirement_9();

		// Requirement 10: Logging.
		$results['10'] = $this->check_requirement_10();

		// Requirement 11: Security testing.
		$results['11'] = $this->check_requirement_11();

		// Requirement 12: Security policy.
		$results['12'] = $this->check_requirement_12();

		return $results;
	}

	/**
	 * Run quick scan.
	 *
	 * @return array
	 */
	private function run_quick_scan() {
		return array(
			'3'  => $this->check_requirement_3(),
			'4'  => $this->check_requirement_4(),
			'8'  => $this->check_requirement_8(),
			'10' => $this->check_requirement_10(),
		);
	}

	/**
	 * Run vulnerability scan.
	 *
	 * @return array
	 */
	private function run_vulnerability_scan() {
		return array(
			'6'  => $this->check_requirement_6(),
			'11' => $this->check_requirement_11(),
		);
	}

	/**
	 * Run configuration scan.
	 *
	 * @return array
	 */
	private function run_configuration_scan() {
		return array(
			'1' => $this->check_requirement_1(),
			'2' => $this->check_requirement_2(),
			'7' => $this->check_requirement_7(),
		);
	}

	/**
	 * Check Requirement 1: Firewall.
	 *
	 * @return array
	 */
	private function check_requirement_1() {
		$checks = array();

		// Check if WordPress firewall plugin is active.
		$firewall_plugins = array( 'wordfence/wordfence.php', 'sucuri-scanner/sucuri.php', 'better-wp-security/better-wp-security.php' );
		$firewall_active  = false;

		foreach ( $firewall_plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				$firewall_active = true;
				break;
			}
		}

		$checks[] = array(
			'id'          => '1.1',
			'title'       => __( 'Web Application Firewall', 'bkx-pci-compliance' ),
			'description' => __( 'Check for active firewall protection.', 'bkx-pci-compliance' ),
			'status'      => $firewall_active ? 'pass' : 'fail',
			'severity'    => 'high',
			'details'     => $firewall_active
				? __( 'Firewall plugin detected and active.', 'bkx-pci-compliance' )
				: __( 'No firewall plugin detected. Consider installing Wordfence or Sucuri.', 'bkx-pci-compliance' ),
		);

		return $checks;
	}

	/**
	 * Check Requirement 2: Default credentials.
	 *
	 * @return array
	 */
	private function check_requirement_2() {
		$checks = array();

		// Check for default admin username.
		$admin_user = get_user_by( 'login', 'admin' );
		$checks[]   = array(
			'id'          => '2.1',
			'title'       => __( 'Default Admin Username', 'bkx-pci-compliance' ),
			'description' => __( 'Check that default admin username is not used.', 'bkx-pci-compliance' ),
			'status'      => $admin_user ? 'fail' : 'pass',
			'severity'    => 'high',
			'details'     => $admin_user
				? __( 'Default "admin" username exists. This should be changed.', 'bkx-pci-compliance' )
				: __( 'No default admin username found.', 'bkx-pci-compliance' ),
		);

		// Check database prefix.
		global $wpdb;
		$default_prefix = ( 'wp_' === $wpdb->prefix );
		$checks[]       = array(
			'id'          => '2.2',
			'title'       => __( 'Database Prefix', 'bkx-pci-compliance' ),
			'description' => __( 'Check that default database prefix is not used.', 'bkx-pci-compliance' ),
			'status'      => $default_prefix ? 'fail' : 'pass',
			'severity'    => 'medium',
			'details'     => $default_prefix
				? __( 'Default "wp_" database prefix is used. Consider changing it.', 'bkx-pci-compliance' )
				: __( 'Custom database prefix is used.', 'bkx-pci-compliance' ),
		);

		// Check debug mode.
		$debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$checks[]      = array(
			'id'          => '2.3',
			'title'       => __( 'Debug Mode', 'bkx-pci-compliance' ),
			'description' => __( 'Check that debug mode is disabled in production.', 'bkx-pci-compliance' ),
			'status'      => $debug_enabled ? 'fail' : 'pass',
			'severity'    => 'critical',
			'details'     => $debug_enabled
				? __( 'WP_DEBUG is enabled. This should be disabled in production.', 'bkx-pci-compliance' )
				: __( 'Debug mode is properly disabled.', 'bkx-pci-compliance' ),
		);

		return $checks;
	}

	/**
	 * Check Requirement 3: Stored cardholder data.
	 *
	 * @return array
	 */
	private function check_requirement_3() {
		$checks = array();

		// Check if payment data is stored.
		$settings        = get_option( 'bkx_pci_compliance_settings', array() );
		$storage         = $settings['card_data_storage'] ?? 'none';
		$checks[]        = array(
			'id'          => '3.1',
			'title'       => __( 'Card Data Storage Policy', 'bkx-pci-compliance' ),
			'description' => __( 'Verify card data storage configuration.', 'bkx-pci-compliance' ),
			'status'      => 'none' === $storage ? 'pass' : ( 'tokenized' === $storage ? 'pass' : 'fail' ),
			'severity'    => 'critical',
			'details'     => 'none' === $storage
				? __( 'No card data is stored locally.', 'bkx-pci-compliance' )
				: ( 'tokenized' === $storage
					? __( 'Card data is tokenized (compliant).', 'bkx-pci-compliance' )
					: __( 'Card data storage detected. This requires additional compliance measures.', 'bkx-pci-compliance' ) ),
		);

		// Check for encryption.
		$encryption_key = get_option( 'bkx_encryption_key' );
		$checks[]       = array(
			'id'          => '3.4',
			'title'       => __( 'Data Encryption', 'bkx-pci-compliance' ),
			'description' => __( 'Check that encryption is configured for sensitive data.', 'bkx-pci-compliance' ),
			'status'      => $encryption_key ? 'pass' : 'fail',
			'severity'    => 'critical',
			'details'     => $encryption_key
				? __( 'Encryption key is configured.', 'bkx-pci-compliance' )
				: __( 'No encryption key found. Sensitive data may not be encrypted.', 'bkx-pci-compliance' ),
		);

		return $checks;
	}

	/**
	 * Check Requirement 4: Encryption in transit.
	 *
	 * @return array
	 */
	private function check_requirement_4() {
		$checks = array();

		// Check SSL.
		$is_ssl   = is_ssl();
		$checks[] = array(
			'id'          => '4.1',
			'title'       => __( 'SSL/TLS Encryption', 'bkx-pci-compliance' ),
			'description' => __( 'Check that SSL/TLS is enabled for the site.', 'bkx-pci-compliance' ),
			'status'      => $is_ssl ? 'pass' : 'fail',
			'severity'    => 'critical',
			'details'     => $is_ssl
				? __( 'Site is using SSL/TLS encryption.', 'bkx-pci-compliance' )
				: __( 'SSL/TLS is not detected. All payment data must be encrypted in transit.', 'bkx-pci-compliance' ),
		);

		// Check if site URL uses HTTPS.
		$site_url  = get_option( 'siteurl' );
		$uses_https = strpos( $site_url, 'https://' ) === 0;
		$checks[]   = array(
			'id'          => '4.2',
			'title'       => __( 'HTTPS Configuration', 'bkx-pci-compliance' ),
			'description' => __( 'Check that site URL is configured with HTTPS.', 'bkx-pci-compliance' ),
			'status'      => $uses_https ? 'pass' : 'fail',
			'severity'    => 'critical',
			'details'     => $uses_https
				? __( 'Site URL is properly configured with HTTPS.', 'bkx-pci-compliance' )
				: __( 'Site URL should use HTTPS for PCI compliance.', 'bkx-pci-compliance' ),
		);

		return $checks;
	}

	/**
	 * Check Requirement 5: Malware protection.
	 *
	 * @return array
	 */
	private function check_requirement_5() {
		$checks = array();

		// Check for security plugin.
		$security_plugins = array(
			'wordfence/wordfence.php',
			'sucuri-scanner/sucuri.php',
			'better-wp-security/better-wp-security.php',
			'all-in-one-wp-security-and-firewall/wp-security.php',
		);

		$security_active = false;
		foreach ( $security_plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				$security_active = true;
				break;
			}
		}

		$checks[] = array(
			'id'          => '5.1',
			'title'       => __( 'Malware Protection', 'bkx-pci-compliance' ),
			'description' => __( 'Check for active malware protection.', 'bkx-pci-compliance' ),
			'status'      => $security_active ? 'pass' : 'fail',
			'severity'    => 'high',
			'details'     => $security_active
				? __( 'Security plugin with malware scanning is active.', 'bkx-pci-compliance' )
				: __( 'No malware protection detected. Install a security plugin.', 'bkx-pci-compliance' ),
		);

		return $checks;
	}

	/**
	 * Check Requirement 6: Secure development.
	 *
	 * @return array
	 */
	private function check_requirement_6() {
		$checks = array();

		// Check WordPress version.
		global $wp_version;
		$latest_wp = $this->get_latest_wp_version();
		$wp_current = version_compare( $wp_version, $latest_wp, '>=' );

		$checks[] = array(
			'id'          => '6.2',
			'title'       => __( 'WordPress Version', 'bkx-pci-compliance' ),
			'description' => __( 'Check that WordPress is up to date.', 'bkx-pci-compliance' ),
			'status'      => $wp_current ? 'pass' : 'fail',
			'severity'    => 'high',
			'details'     => $wp_current
				? sprintf( __( 'WordPress %s is current.', 'bkx-pci-compliance' ), $wp_version )
				: sprintf( __( 'WordPress %1$s is installed. Latest is %2$s. Please update.', 'bkx-pci-compliance' ), $wp_version, $latest_wp ),
		);

		// Check PHP version.
		$php_version = phpversion();
		$php_current = version_compare( $php_version, '8.0', '>=' );
		$checks[]    = array(
			'id'          => '6.3',
			'title'       => __( 'PHP Version', 'bkx-pci-compliance' ),
			'description' => __( 'Check that PHP version is current and supported.', 'bkx-pci-compliance' ),
			'status'      => $php_current ? 'pass' : 'fail',
			'severity'    => 'medium',
			'details'     => sprintf(
				/* translators: %s: PHP version */
				__( 'PHP version %s is installed.', 'bkx-pci-compliance' ),
				$php_version
			) . ( ! $php_current ? ' ' . __( 'PHP 8.0+ is recommended.', 'bkx-pci-compliance' ) : '' ),
		);

		// Check for plugin updates.
		$updates  = get_plugin_updates();
		$checks[] = array(
			'id'          => '6.4',
			'title'       => __( 'Plugin Updates', 'bkx-pci-compliance' ),
			'description' => __( 'Check for pending plugin updates.', 'bkx-pci-compliance' ),
			'status'      => empty( $updates ) ? 'pass' : 'fail',
			'severity'    => 'medium',
			'details'     => empty( $updates )
				? __( 'All plugins are up to date.', 'bkx-pci-compliance' )
				: sprintf(
					/* translators: %d: Number of plugin updates */
					__( '%d plugin updates are available. Please update.', 'bkx-pci-compliance' ),
					count( $updates )
				),
		);

		return $checks;
	}

	/**
	 * Check Requirement 7: Access restriction.
	 *
	 * @return array
	 */
	private function check_requirement_7() {
		$checks = array();

		// Check admin users count.
		$admins   = get_users( array( 'role' => 'administrator' ) );
		$checks[] = array(
			'id'          => '7.1',
			'title'       => __( 'Administrator Accounts', 'bkx-pci-compliance' ),
			'description' => __( 'Review number of administrator accounts.', 'bkx-pci-compliance' ),
			'status'      => count( $admins ) <= 3 ? 'pass' : 'fail',
			'severity'    => 'medium',
			'details'     => sprintf(
				/* translators: %d: Number of admin users */
				__( '%d administrator accounts found.', 'bkx-pci-compliance' ),
				count( $admins )
			) . ( count( $admins ) > 3 ? ' ' . __( 'Review if all accounts are necessary.', 'bkx-pci-compliance' ) : '' ),
		);

		// Check if user registration is enabled.
		$registration = get_option( 'users_can_register' );
		$checks[]     = array(
			'id'          => '7.2',
			'title'       => __( 'User Registration', 'bkx-pci-compliance' ),
			'description' => __( 'Check user registration settings.', 'bkx-pci-compliance' ),
			'status'      => $registration ? 'fail' : 'pass',
			'severity'    => 'low',
			'details'     => $registration
				? __( 'User registration is enabled. Ensure this is intentional.', 'bkx-pci-compliance' )
				: __( 'User registration is disabled.', 'bkx-pci-compliance' ),
		);

		return $checks;
	}

	/**
	 * Check Requirement 8: Authentication.
	 *
	 * @return array
	 */
	private function check_requirement_8() {
		$checks   = array();
		$settings = get_option( 'bkx_pci_compliance_settings', array() );

		// Check password requirements.
		$pass_req = $settings['password_requirements'] ?? array();
		$min_len  = $pass_req['min_length'] ?? 8;
		$checks[] = array(
			'id'          => '8.2',
			'title'       => __( 'Password Policy', 'bkx-pci-compliance' ),
			'description' => __( 'Check password complexity requirements.', 'bkx-pci-compliance' ),
			'status'      => $min_len >= 12 ? 'pass' : 'fail',
			'severity'    => 'high',
			'details'     => $min_len >= 12
				? __( 'Password policy meets PCI requirements (12+ characters).', 'bkx-pci-compliance' )
				: sprintf(
					/* translators: %d: Current minimum password length */
					__( 'Password minimum length is %d. PCI DSS requires 12+ characters.', 'bkx-pci-compliance' ),
					$min_len
				),
		);

		// Check session timeout.
		$timeout  = $settings['session_timeout'] ?? 0;
		$checks[] = array(
			'id'          => '8.1.8',
			'title'       => __( 'Session Timeout', 'bkx-pci-compliance' ),
			'description' => __( 'Check idle session timeout configuration.', 'bkx-pci-compliance' ),
			'status'      => $timeout > 0 && $timeout <= 15 ? 'pass' : 'fail',
			'severity'    => 'medium',
			'details'     => $timeout > 0
				? sprintf(
					/* translators: %d: Session timeout in minutes */
					__( 'Session timeout is set to %d minutes.', 'bkx-pci-compliance' ),
					$timeout
				)
				: __( 'Session timeout is not configured. PCI requires 15 minute idle timeout.', 'bkx-pci-compliance' ),
		);

		return $checks;
	}

	/**
	 * Check Requirement 9: Physical access (N/A for most web apps).
	 *
	 * @return array
	 */
	private function check_requirement_9() {
		return array(
			array(
				'id'          => '9',
				'title'       => __( 'Physical Access Controls', 'bkx-pci-compliance' ),
				'description' => __( 'Physical access controls for cardholder data.', 'bkx-pci-compliance' ),
				'status'      => 'na',
				'severity'    => 'low',
				'details'     => __( 'Physical access controls are typically handled by hosting provider for web applications.', 'bkx-pci-compliance' ),
			),
		);
	}

	/**
	 * Check Requirement 10: Logging.
	 *
	 * @return array
	 */
	private function check_requirement_10() {
		$checks = array();

		// Check if audit logging is enabled.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$log_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bkx_pci_audit_log" );

		$checks[] = array(
			'id'          => '10.1',
			'title'       => __( 'Audit Logging', 'bkx-pci-compliance' ),
			'description' => __( 'Check that audit logging is active.', 'bkx-pci-compliance' ),
			'status'      => $log_count > 0 ? 'pass' : 'fail',
			'severity'    => 'critical',
			'details'     => $log_count > 0
				? sprintf(
					/* translators: %d: Number of audit log entries */
					__( 'Audit logging is active (%d entries).', 'bkx-pci-compliance' ),
					$log_count
				)
				: __( 'Audit logging appears inactive. No log entries found.', 'bkx-pci-compliance' ),
		);

		// Check log retention.
		$settings  = get_option( 'bkx_pci_compliance_settings', array() );
		$retention = $settings['audit_log_retention'] ?? 0;
		$checks[]  = array(
			'id'          => '10.7',
			'title'       => __( 'Log Retention', 'bkx-pci-compliance' ),
			'description' => __( 'Check audit log retention period.', 'bkx-pci-compliance' ),
			'status'      => $retention >= 365 ? 'pass' : 'fail',
			'severity'    => 'high',
			'details'     => $retention >= 365
				? sprintf(
					/* translators: %d: Log retention days */
					__( 'Logs are retained for %d days (meets 1 year requirement).', 'bkx-pci-compliance' ),
					$retention
				)
				: __( 'Log retention should be at least 1 year (365 days) for PCI compliance.', 'bkx-pci-compliance' ),
		);

		return $checks;
	}

	/**
	 * Check Requirement 11: Security testing.
	 *
	 * @return array
	 */
	private function check_requirement_11() {
		$checks = array();

		// Check for recent scans.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$last_scan = $wpdb->get_var(
			"SELECT created_at FROM {$wpdb->prefix}bkx_pci_scan_results
			WHERE status = 'completed'
			ORDER BY created_at DESC LIMIT 1"
		);

		$days_since = $last_scan ? floor( ( time() - strtotime( $last_scan ) ) / DAY_IN_SECONDS ) : 999;

		$checks[] = array(
			'id'          => '11.2',
			'title'       => __( 'Vulnerability Scanning', 'bkx-pci-compliance' ),
			'description' => __( 'Check for recent vulnerability scans.', 'bkx-pci-compliance' ),
			'status'      => $days_since <= 90 ? 'pass' : 'fail',
			'severity'    => 'high',
			'details'     => $last_scan
				? sprintf(
					/* translators: %d: Days since last scan */
					__( 'Last scan was %d days ago.', 'bkx-pci-compliance' ),
					$days_since
				) . ( $days_since > 90 ? ' ' . __( 'Scans should be quarterly or more frequent.', 'bkx-pci-compliance' ) : '' )
				: __( 'No completed scans found. Regular scanning is required.', 'bkx-pci-compliance' ),
		);

		return $checks;
	}

	/**
	 * Check Requirement 12: Security policy.
	 *
	 * @return array
	 */
	private function check_requirement_12() {
		return array(
			array(
				'id'          => '12',
				'title'       => __( 'Information Security Policy', 'bkx-pci-compliance' ),
				'description' => __( 'Security policy documentation.', 'bkx-pci-compliance' ),
				'status'      => 'na',
				'severity'    => 'medium',
				'details'     => __( 'Security policy documentation should be maintained separately. Ensure policies are documented and reviewed annually.', 'bkx-pci-compliance' ),
			),
		);
	}

	/**
	 * Get scan result.
	 *
	 * @param int $scan_id Scan ID.
	 * @return array|null
	 */
	public function get_scan_result( $scan_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_pci_scan_results WHERE id = %d",
				$scan_id
			),
			ARRAY_A
		);

		if ( $result ) {
			$result['results']         = json_decode( $result['results'], true );
			$result['recommendations'] = json_decode( $result['recommendations'], true );
		}

		return $result;
	}

	/**
	 * Get scan history.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public function get_scan_history( $limit = 10 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_pci_scan_results
				ORDER BY created_at DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Generate recommendations.
	 *
	 * @param array $results Scan results.
	 * @return array
	 */
	private function generate_recommendations( $results ) {
		$recommendations = array();

		foreach ( $results as $requirement => $checks ) {
			foreach ( $checks as $check ) {
				if ( 'fail' === $check['status'] ) {
					$recommendations[] = array(
						'requirement' => $requirement,
						'check_id'    => $check['id'],
						'title'       => $check['title'],
						'severity'    => $check['severity'],
						'action'      => $check['details'],
					);
				}
			}
		}

		// Sort by severity.
		usort( $recommendations, function ( $a, $b ) {
			$order = array( 'critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3 );
			return ( $order[ $a['severity'] ] ?? 4 ) - ( $order[ $b['severity'] ] ?? 4 );
		} );

		return $recommendations;
	}

	/**
	 * Generate compliance report.
	 *
	 * @param string $report_type Report type.
	 * @param string $date_from   Start date.
	 * @param string $date_to     End date.
	 * @return string|\WP_Error Report URL or error.
	 */
	public function generate_report( $report_type, $date_from = '', $date_to = '' ) {
		// Get latest scan result.
		$history = $this->get_scan_history( 1 );
		if ( empty( $history ) ) {
			return new \WP_Error( 'no_scans', __( 'No scan results available for report.', 'bkx-pci-compliance' ) );
		}

		$scan = $history[0];
		$scan['results'] = json_decode( $scan['results'], true );
		$scan['recommendations'] = json_decode( $scan['recommendations'], true );

		// Generate PDF or HTML report.
		$upload_dir = wp_upload_dir();
		$report_dir = $upload_dir['basedir'] . '/bkx-pci-reports/';

		if ( ! is_dir( $report_dir ) ) {
			wp_mkdir_p( $report_dir );
			file_put_contents( $report_dir . '.htaccess', 'Deny from all' );
		}

		$filename = sprintf( 'pci-compliance-report-%s.html', gmdate( 'Y-m-d-His' ) );
		$filepath = $report_dir . $filename;

		// Generate HTML report.
		ob_start();
		include BKX_PCI_PATH . 'templates/reports/compliance-report.php';
		$html = ob_get_clean();

		file_put_contents( $filepath, $html );

		return $upload_dir['baseurl'] . '/bkx-pci-reports/' . $filename;
	}

	/**
	 * Get latest WordPress version.
	 *
	 * @return string
	 */
	private function get_latest_wp_version() {
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$updates = get_core_updates();
		if ( ! empty( $updates ) && isset( $updates[0]->version ) ) {
			return $updates[0]->version;
		}

		global $wp_version;
		return $wp_version;
	}
}
