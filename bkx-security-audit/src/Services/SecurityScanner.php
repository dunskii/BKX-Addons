<?php
/**
 * Security Scanner service.
 *
 * @package BookingX\SecurityAudit
 */

namespace BookingX\SecurityAudit\Services;

defined( 'ABSPATH' ) || exit;

/**
 * SecurityScanner class.
 */
class SecurityScanner {

	/**
	 * Scan results.
	 *
	 * @var array
	 */
	private $results = array();

	/**
	 * Run a full security scan.
	 *
	 * @return array
	 */
	public function run_full_scan() {
		$this->results = array(
			'timestamp' => current_time( 'mysql' ),
			'score'     => 100,
			'issues'    => array(),
			'warnings'  => array(),
			'info'      => array(),
		);

		// Run all checks.
		$this->check_wordpress_version();
		$this->check_php_version();
		$this->check_ssl();
		$this->check_file_permissions();
		$this->check_debug_mode();
		$this->check_admin_user();
		$this->check_database_prefix();
		$this->check_directory_listing();
		$this->check_security_keys();
		$this->check_file_editor();
		$this->check_exposed_files();
		$this->check_weak_passwords();
		$this->check_user_enumeration();

		// Calculate final score.
		$this->results['score'] = max( 0, $this->results['score'] );

		// Save results.
		update_option( 'bkx_security_last_scan', $this->results );

		// Create events for critical issues.
		$this->create_events_for_issues();

		return $this->results;
	}

	/**
	 * Check WordPress version.
	 */
	private function check_wordpress_version() {
		global $wp_version;

		$updates = get_site_transient( 'update_core' );

		if ( isset( $updates->updates ) && ! empty( $updates->updates ) ) {
			$latest = $updates->updates[0];

			if ( 'upgrade' === $latest->response ) {
				$this->add_issue(
					'critical',
					__( 'WordPress Update Available', 'bkx-security-audit' ),
					sprintf(
						/* translators: 1: current version, 2: latest version */
						__( 'You are running WordPress %1$s. Version %2$s is available.', 'bkx-security-audit' ),
						$wp_version,
						$latest->current
					),
					20
				);
			}
		}

		$this->add_info(
			__( 'WordPress Version', 'bkx-security-audit' ),
			$wp_version
		);
	}

	/**
	 * Check PHP version.
	 */
	private function check_php_version() {
		$php_version = PHP_VERSION;
		$min_version = '7.4';
		$recommended = '8.1';

		if ( version_compare( $php_version, $min_version, '<' ) ) {
			$this->add_issue(
				'critical',
				__( 'PHP Version Too Old', 'bkx-security-audit' ),
				sprintf(
					/* translators: 1: current version, 2: minimum version */
					__( 'PHP %1$s is no longer supported. Please upgrade to at least PHP %2$s.', 'bkx-security-audit' ),
					$php_version,
					$min_version
				),
				25
			);
		} elseif ( version_compare( $php_version, $recommended, '<' ) ) {
			$this->add_warning(
				__( 'PHP Version Could Be Newer', 'bkx-security-audit' ),
				sprintf(
					/* translators: 1: current version, 2: recommended version */
					__( 'You are running PHP %1$s. PHP %2$s is recommended for better security and performance.', 'bkx-security-audit' ),
					$php_version,
					$recommended
				),
				5
			);
		}

		$this->add_info(
			__( 'PHP Version', 'bkx-security-audit' ),
			$php_version
		);
	}

	/**
	 * Check SSL/HTTPS.
	 */
	private function check_ssl() {
		$is_ssl = is_ssl();

		if ( ! $is_ssl ) {
			$this->add_issue(
				'high',
				__( 'SSL Not Enabled', 'bkx-security-audit' ),
				__( 'Your site is not using HTTPS. This exposes user data and login credentials.', 'bkx-security-audit' ),
				15
			);
		} else {
			$this->add_info(
				__( 'SSL Status', 'bkx-security-audit' ),
				__( 'Enabled', 'bkx-security-audit' )
			);
		}
	}

	/**
	 * Check file permissions.
	 */
	private function check_file_permissions() {
		$checks = array(
			ABSPATH . 'wp-config.php' => '0440',
			ABSPATH . '.htaccess'     => '0644',
		);

		foreach ( $checks as $file => $expected ) {
			if ( ! file_exists( $file ) ) {
				continue;
			}

			$perms = substr( sprintf( '%o', fileperms( $file ) ), -4 );

			if ( $file === ABSPATH . 'wp-config.php' && $perms > '0644' ) {
				$this->add_issue(
					'high',
					__( 'wp-config.php Permissions Too Open', 'bkx-security-audit' ),
					sprintf(
						/* translators: 1: current permissions, 2: recommended permissions */
						__( 'wp-config.php has permissions %1$s. Should be %2$s or less.', 'bkx-security-audit' ),
						$perms,
						$expected
					),
					10
				);
			}
		}
	}

	/**
	 * Check debug mode.
	 */
	private function check_debug_mode() {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$this->add_warning(
				__( 'Debug Mode Enabled', 'bkx-security-audit' ),
				__( 'WP_DEBUG is enabled. This should be disabled on production sites.', 'bkx-security-audit' ),
				5
			);
		}

		if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
			$this->add_issue(
				'high',
				__( 'Debug Display Enabled', 'bkx-security-audit' ),
				__( 'WP_DEBUG_DISPLAY is enabled. Errors are visible to visitors.', 'bkx-security-audit' ),
				10
			);
		}
	}

	/**
	 * Check for admin username.
	 */
	private function check_admin_user() {
		$user = get_user_by( 'login', 'admin' );

		if ( $user ) {
			$this->add_issue(
				'medium',
				__( 'Default Admin Username', 'bkx-security-audit' ),
				__( 'A user with the username "admin" exists. This is a common attack target.', 'bkx-security-audit' ),
				10
			);
		}
	}

	/**
	 * Check database prefix.
	 */
	private function check_database_prefix() {
		global $wpdb;

		if ( $wpdb->prefix === 'wp_' ) {
			$this->add_warning(
				__( 'Default Database Prefix', 'bkx-security-audit' ),
				__( 'You are using the default "wp_" database prefix. Consider using a custom prefix.', 'bkx-security-audit' ),
				5
			);
		}

		$this->add_info(
			__( 'Database Prefix', 'bkx-security-audit' ),
			$wpdb->prefix
		);
	}

	/**
	 * Check directory listing.
	 */
	private function check_directory_listing() {
		$test_dirs = array(
			WP_CONTENT_DIR . '/uploads/',
			WP_PLUGIN_DIR . '/',
			get_theme_root() . '/',
		);

		foreach ( $test_dirs as $dir ) {
			$index = $dir . 'index.php';
			$htaccess = $dir . '.htaccess';

			if ( ! file_exists( $index ) && ! file_exists( $htaccess ) ) {
				$this->add_warning(
					__( 'Directory Listing May Be Exposed', 'bkx-security-audit' ),
					sprintf(
						/* translators: %s: directory path */
						__( 'Directory %s may allow listing. Add an index.php file.', 'bkx-security-audit' ),
						str_replace( ABSPATH, '', $dir )
					),
					3
				);
				break;
			}
		}
	}

	/**
	 * Check security keys.
	 */
	private function check_security_keys() {
		$keys = array(
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		);

		$weak_keys = 0;
		foreach ( $keys as $key ) {
			if ( ! defined( $key ) || strlen( constant( $key ) ) < 32 ) {
				$weak_keys++;
			}
		}

		if ( $weak_keys > 0 ) {
			$this->add_issue(
				'high',
				__( 'Weak Security Keys', 'bkx-security-audit' ),
				sprintf(
					/* translators: %d: number of weak keys */
					__( '%d security keys are missing or weak. Generate new keys at api.wordpress.org/secret-key', 'bkx-security-audit' ),
					$weak_keys
				),
				15
			);
		}
	}

	/**
	 * Check if file editor is enabled.
	 */
	private function check_file_editor() {
		if ( ! defined( 'DISALLOW_FILE_EDIT' ) || ! DISALLOW_FILE_EDIT ) {
			$this->add_warning(
				__( 'File Editor Enabled', 'bkx-security-audit' ),
				__( 'The WordPress file editor is enabled. Consider disabling it with DISALLOW_FILE_EDIT.', 'bkx-security-audit' ),
				5
			);
		}
	}

	/**
	 * Check for exposed sensitive files.
	 */
	private function check_exposed_files() {
		$sensitive_files = array(
			'readme.html'       => ABSPATH . 'readme.html',
			'license.txt'       => ABSPATH . 'license.txt',
			'wp-config-sample.php' => ABSPATH . 'wp-config-sample.php',
		);

		foreach ( $sensitive_files as $name => $path ) {
			if ( file_exists( $path ) ) {
				$this->add_info(
					sprintf( __( 'File Exists: %s', 'bkx-security-audit' ), $name ),
					__( 'Consider removing to hide WordPress version info.', 'bkx-security-audit' )
				);
			}
		}
	}

	/**
	 * Check for weak passwords.
	 */
	private function check_weak_passwords() {
		$weak_passwords = array( 'password', '123456', 'admin', 'password123', 'qwerty' );
		$admins         = get_users( array( 'role' => 'administrator' ) );

		// We can't check actual passwords, but we can warn about password policy.
		if ( count( $admins ) > 0 ) {
			$this->add_info(
				__( 'Administrator Accounts', 'bkx-security-audit' ),
				sprintf(
					/* translators: %d: number of admins */
					__( '%d administrator account(s) found.', 'bkx-security-audit' ),
					count( $admins )
				)
			);
		}
	}

	/**
	 * Check user enumeration.
	 */
	private function check_user_enumeration() {
		// Check if user enumeration is blocked.
		// This is just a suggestion as we can't fully test this.
		$this->add_info(
			__( 'User Enumeration', 'bkx-security-audit' ),
			__( 'Consider blocking user enumeration via ?author=N and REST API.', 'bkx-security-audit' )
		);
	}

	/**
	 * Add an issue.
	 *
	 * @param string $severity    Issue severity (critical, high, medium, low).
	 * @param string $title       Issue title.
	 * @param string $description Issue description.
	 * @param int    $deduction   Score deduction.
	 */
	private function add_issue( $severity, $title, $description, $deduction = 0 ) {
		$this->results['issues'][] = array(
			'severity'    => $severity,
			'title'       => $title,
			'description' => $description,
		);

		$this->results['score'] -= $deduction;
	}

	/**
	 * Add a warning.
	 *
	 * @param string $title       Warning title.
	 * @param string $description Warning description.
	 * @param int    $deduction   Score deduction.
	 */
	private function add_warning( $title, $description, $deduction = 0 ) {
		$this->results['warnings'][] = array(
			'title'       => $title,
			'description' => $description,
		);

		$this->results['score'] -= $deduction;
	}

	/**
	 * Add info.
	 *
	 * @param string $title Title.
	 * @param string $value Value.
	 */
	private function add_info( $title, $value ) {
		$this->results['info'][] = array(
			'title' => $title,
			'value' => $value,
		);
	}

	/**
	 * Create security events for critical issues.
	 */
	private function create_events_for_issues() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_security_events';

		foreach ( $this->results['issues'] as $issue ) {
			if ( in_array( $issue['severity'], array( 'critical', 'high' ), true ) ) {
				// Check if similar event already exists.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$exists = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$table} WHERE event_type = 'security_scan' AND title = %s AND resolved = 0",
						$issue['title']
					)
				);

				if ( ! $exists ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$wpdb->insert(
						$table,
						array(
							'event_type'  => 'security_scan',
							'severity'    => $issue['severity'],
							'title'       => $issue['title'],
							'description' => $issue['description'],
							'created_at'  => current_time( 'mysql' ),
						),
						array( '%s', '%s', '%s', '%s', '%s' )
					);
				}
			}
		}
	}

	/**
	 * Get last scan results.
	 *
	 * @return array|false
	 */
	public function get_last_scan() {
		return get_option( 'bkx_security_last_scan', false );
	}
}
