<?php
/**
 * File Integrity Monitoring service.
 *
 * @package BookingX\SecurityAudit
 */

namespace BookingX\SecurityAudit\Services;

defined( 'ABSPATH' ) || exit;

/**
 * FileIntegrity class.
 */
class FileIntegrity {

	/**
	 * Directories to monitor.
	 *
	 * @var array
	 */
	private $monitored_dirs = array();

	/**
	 * Excluded patterns.
	 *
	 * @var array
	 */
	private $excluded_patterns = array(
		'/\.log$/',
		'/\.tmp$/',
		'/\.bak$/',
		'/cache/',
		'/debug\.log$/',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->monitored_dirs = array(
			ABSPATH . 'wp-admin/',
			ABSPATH . 'wp-includes/',
			WP_CONTENT_DIR . '/plugins/bookingx/',
		);

		$this->monitored_dirs = apply_filters( 'bkx_security_monitored_dirs', $this->monitored_dirs );
	}

	/**
	 * Initialize baseline hashes.
	 *
	 * @return int Number of files hashed.
	 */
	public function initialize_baseline() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_file_hashes';
		$count = 0;

		// Clear existing hashes.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		foreach ( $this->monitored_dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			$files = $this->get_files_recursive( $dir );

			foreach ( $files as $file ) {
				if ( $this->is_excluded( $file ) ) {
					continue;
				}

				$this->save_file_hash( $file );
				$count++;
			}
		}

		update_option( 'bkx_security_baseline_date', current_time( 'mysql' ) );

		return $count;
	}

	/**
	 * Check file integrity against baseline.
	 *
	 * @return array Changes detected.
	 */
	public function check_integrity() {
		global $wpdb;

		$table   = $wpdb->prefix . 'bkx_file_hashes';
		$changes = array(
			'modified' => array(),
			'new'      => array(),
			'deleted'  => array(),
		);

		// Get all baseline files.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$baseline = $wpdb->get_results(
			"SELECT file_path, file_hash FROM {$table}",
			OBJECT_K
		);

		$current_files = array();

		// Check current files.
		foreach ( $this->monitored_dirs as $dir ) {
			if ( ! is_dir( $dir ) ) {
				continue;
			}

			$files = $this->get_files_recursive( $dir );

			foreach ( $files as $file ) {
				if ( $this->is_excluded( $file ) ) {
					continue;
				}

				$current_files[ $file ] = true;
				$current_hash           = hash_file( 'sha256', $file );

				if ( isset( $baseline[ $file ] ) ) {
					// File exists in baseline - check if modified.
					if ( $baseline[ $file ]->file_hash !== $current_hash ) {
						$changes['modified'][] = array(
							'path'     => $file,
							'old_hash' => $baseline[ $file ]->file_hash,
							'new_hash' => $current_hash,
						);

						// Update status.
						$this->update_file_status( $file, 'modified', $current_hash );
					}
				} else {
					// New file.
					$changes['new'][] = $file;
					$this->save_file_hash( $file, 'new' );
				}
			}
		}

		// Check for deleted files.
		foreach ( $baseline as $path => $data ) {
			if ( ! isset( $current_files[ $path ] ) && ! file_exists( $path ) ) {
				$changes['deleted'][] = $path;

				// Update status.
				$this->update_file_status( $path, 'deleted' );
			}
		}

		// Create security events for changes.
		$this->create_events_for_changes( $changes );

		// Save last check results.
		update_option( 'bkx_security_integrity_check', array(
			'timestamp' => current_time( 'mysql' ),
			'changes'   => $changes,
		) );

		return $changes;
	}

	/**
	 * Get files recursively from a directory.
	 *
	 * @param string $dir Directory path.
	 * @return array
	 */
	private function get_files_recursive( $dir ) {
		$files = array();

		try {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $dir, \RecursiveDirectoryIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $iterator as $file ) {
				if ( $file->isFile() && preg_match( '/\.(php|js|css|html|htm)$/i', $file->getFilename() ) ) {
					$files[] = $file->getPathname();
				}
			}
		} catch ( \Exception $e ) {
			// Log error but continue.
			error_log( 'BKX Security: Error scanning directory ' . $dir . ': ' . $e->getMessage() );
		}

		return $files;
	}

	/**
	 * Check if file should be excluded.
	 *
	 * @param string $file File path.
	 * @return bool
	 */
	private function is_excluded( $file ) {
		foreach ( $this->excluded_patterns as $pattern ) {
			if ( preg_match( $pattern, $file ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Save file hash to database.
	 *
	 * @param string $file   File path.
	 * @param string $status File status.
	 */
	private function save_file_hash( $file, $status = 'unchanged' ) {
		global $wpdb;

		if ( ! file_exists( $file ) ) {
			return;
		}

		$hash     = hash_file( 'sha256', $file );
		$size     = filesize( $file );
		$modified = gmdate( 'Y-m-d H:i:s', filemtime( $file ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->replace(
			$wpdb->prefix . 'bkx_file_hashes',
			array(
				'file_path'     => $file,
				'file_hash'     => $hash,
				'file_size'     => $size,
				'last_modified' => $modified,
				'status'        => $status,
				'checked_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Update file status.
	 *
	 * @param string $file     File path.
	 * @param string $status   New status.
	 * @param string $new_hash New hash (for modified files).
	 */
	private function update_file_status( $file, $status, $new_hash = null ) {
		global $wpdb;

		$data = array(
			'status'     => $status,
			'checked_at' => current_time( 'mysql' ),
		);

		if ( $new_hash ) {
			$data['file_hash'] = $new_hash;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'bkx_file_hashes',
			$data,
			array( 'file_path' => $file ),
			array( '%s', '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Create security events for file changes.
	 *
	 * @param array $changes Detected changes.
	 */
	private function create_events_for_changes( $changes ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_security_events';

		// Modified files (critical).
		if ( ! empty( $changes['modified'] ) ) {
			$core_modified = array_filter( $changes['modified'], function( $item ) {
				return strpos( $item['path'], 'wp-admin' ) !== false || strpos( $item['path'], 'wp-includes' ) !== false;
			} );

			if ( ! empty( $core_modified ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$table,
					array(
						'event_type'  => 'file_integrity',
						'severity'    => 'critical',
						'title'       => __( 'Core Files Modified', 'bkx-security-audit' ),
						'description' => sprintf(
							/* translators: %d: number of files */
							__( '%d WordPress core file(s) have been modified.', 'bkx-security-audit' ),
							count( $core_modified )
						),
						'data'        => wp_json_encode( array_column( $core_modified, 'path' ) ),
						'created_at'  => current_time( 'mysql' ),
					),
					array( '%s', '%s', '%s', '%s', '%s', '%s' )
				);
			}
		}

		// New files in core directories.
		if ( ! empty( $changes['new'] ) ) {
			$suspicious_new = array_filter( $changes['new'], function( $path ) {
				return strpos( $path, 'wp-admin' ) !== false || strpos( $path, 'wp-includes' ) !== false;
			} );

			if ( ! empty( $suspicious_new ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$table,
					array(
						'event_type'  => 'file_integrity',
						'severity'    => 'high',
						'title'       => __( 'New Files in Core Directories', 'bkx-security-audit' ),
						'description' => sprintf(
							/* translators: %d: number of files */
							__( '%d new file(s) detected in WordPress core directories.', 'bkx-security-audit' ),
							count( $suspicious_new )
						),
						'data'        => wp_json_encode( $suspicious_new ),
						'created_at'  => current_time( 'mysql' ),
					),
					array( '%s', '%s', '%s', '%s', '%s', '%s' )
				);
			}
		}
	}

	/**
	 * Get file changes since last check.
	 *
	 * @return array
	 */
	public function get_recent_changes() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_file_hashes';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			"SELECT * FROM {$table} WHERE status != 'unchanged' ORDER BY checked_at DESC LIMIT 100",
			ARRAY_A
		);
	}

	/**
	 * Get integrity check status.
	 *
	 * @return array
	 */
	public function get_status() {
		$baseline_date = get_option( 'bkx_security_baseline_date' );
		$last_check    = get_option( 'bkx_security_integrity_check' );

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_file_hashes';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			"SELECT
				COUNT(*) as total,
				SUM(CASE WHEN status = 'modified' THEN 1 ELSE 0 END) as modified,
				SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_files,
				SUM(CASE WHEN status = 'deleted' THEN 1 ELSE 0 END) as deleted
			FROM {$table}",
			ARRAY_A
		);

		return array(
			'baseline_date' => $baseline_date,
			'last_check'    => $last_check,
			'stats'         => $stats,
		);
	}

	/**
	 * Reset file status to unchanged.
	 *
	 * @param string $file File path.
	 */
	public function accept_change( $file ) {
		$this->save_file_hash( $file, 'unchanged' );
	}
}
