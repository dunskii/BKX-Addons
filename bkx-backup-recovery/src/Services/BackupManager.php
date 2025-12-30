<?php
/**
 * Backup Manager service.
 *
 * @package BookingX\BackupRecovery
 */

namespace BookingX\BackupRecovery\Services;

defined( 'ABSPATH' ) || exit;

/**
 * BackupManager class.
 */
class BackupManager {

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Backup directory path.
	 *
	 * @var string
	 */
	private $backup_dir;

	/**
	 * Constructor.
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;

		$upload_dir       = wp_upload_dir();
		$this->backup_dir = $upload_dir['basedir'] . '/bkx-backups/';
	}

	/**
	 * Create a backup.
	 *
	 * @param string $type Backup type (full, incremental, manual).
	 * @return int|\WP_Error Backup ID or error.
	 */
	public function create_backup( $type = 'full' ) {
		global $wpdb;

		// Create backup record.
		$backup_id = $this->create_backup_record( $type );
		if ( is_wp_error( $backup_id ) ) {
			return $backup_id;
		}

		$this->update_backup_status( $backup_id, 'running' );

		try {
			// Collect data to backup.
			$data = $this->collect_backup_data();

			// Generate file name.
			$filename = sprintf(
				'bkx-backup-%s-%s.json',
				$type,
				gmdate( 'Y-m-d-His' )
			);

			$filepath = $this->backup_dir . $filename;

			// Write JSON data.
			$json_content = wp_json_encode( $data, JSON_PRETTY_PRINT );
			if ( false === file_put_contents( $filepath, $json_content ) ) {
				throw new \Exception( __( 'Failed to write backup file.', 'bkx-backup-recovery' ) );
			}

			// Compress if enabled.
			$compression = $this->settings['compression'] ?? 'zip';
			if ( 'zip' === $compression && class_exists( 'ZipArchive' ) ) {
				$zip_file = $this->compress_to_zip( $filepath, $filename );
				if ( $zip_file ) {
					wp_delete_file( $filepath );
					$filepath = $zip_file;
					$filename = basename( $zip_file );
				}
			}

			// Update backup record.
			$file_size = filesize( $filepath );
			$checksum  = md5_file( $filepath );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'bkx_backup_history',
				array(
					'status'          => 'completed',
					'file_name'       => $filename,
					'file_size'       => $file_size,
					'file_path'       => $filepath,
					'checksum'        => $checksum,
					'items_count'     => $data['meta']['total_items'],
					'tables_included' => wp_json_encode( array_keys( $data['data'] ) ),
					'completed_at'    => current_time( 'mysql' ),
				),
				array( 'id' => $backup_id ),
				array( '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);

			return $backup_id;

		} catch ( \Exception $e ) {
			$this->update_backup_status( $backup_id, 'failed', $e->getMessage() );
			return new \WP_Error( 'backup_failed', $e->getMessage() );
		}
	}

	/**
	 * Collect data for backup.
	 *
	 * @return array
	 */
	private function collect_backup_data() {
		global $wpdb;

		$data = array(
			'meta' => array(
				'version'       => BKX_BACKUP_VERSION,
				'wp_version'    => get_bloginfo( 'version' ),
				'site_url'      => get_site_url(),
				'created_at'    => current_time( 'mysql' ),
				'total_items'   => 0,
			),
			'data' => array(),
		);

		// Bookings.
		if ( ! empty( $this->settings['include_bookings'] ) ) {
			$bookings = get_posts( array(
				'post_type'      => 'bkx_booking',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			) );

			$data['data']['bookings'] = array();
			foreach ( $bookings as $booking ) {
				$data['data']['bookings'][] = array(
					'post'      => (array) $booking,
					'meta'      => get_post_meta( $booking->ID ),
				);
			}
			$data['meta']['total_items'] += count( $bookings );
		}

		// Services (bkx_base).
		if ( ! empty( $this->settings['include_services'] ) ) {
			$services = get_posts( array(
				'post_type'      => 'bkx_base',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			) );

			$data['data']['services'] = array();
			foreach ( $services as $service ) {
				$data['data']['services'][] = array(
					'post'      => (array) $service,
					'meta'      => get_post_meta( $service->ID ),
				);
			}
			$data['meta']['total_items'] += count( $services );
		}

		// Staff (bkx_seat).
		if ( ! empty( $this->settings['include_staff'] ) ) {
			$staff = get_posts( array(
				'post_type'      => 'bkx_seat',
				'posts_per_page' => -1,
				'post_status'    => 'any',
			) );

			$data['data']['staff'] = array();
			foreach ( $staff as $member ) {
				$data['data']['staff'][] = array(
					'post'      => (array) $member,
					'meta'      => get_post_meta( $member->ID ),
				);
			}
			$data['meta']['total_items'] += count( $staff );
		}

		// Extras (bkx_addition).
		$extras = get_posts( array(
			'post_type'      => 'bkx_addition',
			'posts_per_page' => -1,
			'post_status'    => 'any',
		) );

		$data['data']['extras'] = array();
		foreach ( $extras as $extra ) {
			$data['data']['extras'][] = array(
				'post'      => (array) $extra,
				'meta'      => get_post_meta( $extra->ID ),
			);
		}
		$data['meta']['total_items'] += count( $extras );

		// Settings.
		if ( ! empty( $this->settings['include_settings'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$options = $wpdb->get_results(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE 'bkx_%' OR option_name LIKE 'bookingx_%'",
				ARRAY_A
			);

			$data['data']['settings'] = $options;
		}

		// Customers.
		if ( ! empty( $this->settings['include_customers'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$customers = $wpdb->get_results(
				"SELECT DISTINCT pm.meta_value as email
				FROM {$wpdb->postmeta} pm
				WHERE pm.meta_key = 'customer_email'",
				ARRAY_A
			);

			$data['data']['customers'] = $customers;
			$data['meta']['total_items'] += count( $customers );
		}

		// Custom tables.
		$custom_tables = array(
			$wpdb->prefix . 'bkx_consents',
			$wpdb->prefix . 'bkx_booking_sessions',
		);

		foreach ( $custom_tables as $table ) {
			// Check if table exists.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $exists ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$rows = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

				$table_name = str_replace( $wpdb->prefix, '', $table );
				$data['data'][ $table_name ] = $rows;
				$data['meta']['total_items'] += count( $rows );
			}
		}

		return $data;
	}

	/**
	 * Compress backup to ZIP.
	 *
	 * @param string $filepath JSON file path.
	 * @param string $filename Original filename.
	 * @return string|false ZIP file path or false.
	 */
	private function compress_to_zip( $filepath, $filename ) {
		$zip_path = str_replace( '.json', '.zip', $filepath );

		$zip = new \ZipArchive();
		if ( $zip->open( $zip_path, \ZipArchive::CREATE ) !== true ) {
			return false;
		}

		$zip->addFile( $filepath, $filename );
		$zip->close();

		return $zip_path;
	}

	/**
	 * Create backup record.
	 *
	 * @param string $type Backup type.
	 * @return int|\WP_Error
	 */
	private function create_backup_record( $type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'bkx_backup_history',
			array(
				'backup_type' => $type,
				'status'      => 'pending',
				'file_name'   => '',
				'file_path'   => '',
				'started_at'  => current_time( 'mysql' ),
				'created_by'  => get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create backup record.', 'bkx-backup-recovery' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update backup status.
	 *
	 * @param int    $backup_id     Backup ID.
	 * @param string $status        Status.
	 * @param string $error_message Error message (optional).
	 */
	private function update_backup_status( $backup_id, $status, $error_message = '' ) {
		global $wpdb;

		$data = array( 'status' => $status );
		$format = array( '%s' );

		if ( $error_message ) {
			$data['error_message'] = $error_message;
			$format[] = '%s';
		}

		if ( 'completed' === $status || 'failed' === $status ) {
			$data['completed_at'] = current_time( 'mysql' );
			$format[] = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'bkx_backup_history',
			$data,
			array( 'id' => $backup_id ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Get backup by ID.
	 *
	 * @param int $backup_id Backup ID.
	 * @return array|null
	 */
	public function get_backup( $backup_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_backup_history WHERE id = %d",
				$backup_id
			),
			ARRAY_A
		);
	}

	/**
	 * Get all backups.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_backups( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
			'status'   => null,
			'type'     => null,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array( '1=1' );
		$values = array();

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( $args['type'] ) {
			$where[]  = 'backup_type = %s';
			$values[] = $args['type'];
		}

		$where_clause = implode( ' AND ', $where );
		$offset       = ( $args['page'] - 1 ) * $args['per_page'];

		// Get total.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $wpdb->get_var(
			empty( $values )
				? "SELECT COUNT(*) FROM {$wpdb->prefix}bkx_backup_history WHERE {$where_clause}"
				: $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}bkx_backup_history WHERE {$where_clause}", ...$values ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// Get backups.
		$values[] = $args['per_page'];
		$values[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$backups = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_backup_history WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$values
			),
			ARRAY_A
		);

		return array(
			'backups' => $backups,
			'total'   => (int) $total,
			'pages'   => ceil( $total / $args['per_page'] ),
		);
	}

	/**
	 * Get backup status.
	 *
	 * @param int $backup_id Backup ID.
	 * @return array
	 */
	public function get_backup_status( $backup_id ) {
		$backup = $this->get_backup( $backup_id );

		if ( ! $backup ) {
			return array( 'status' => 'not_found' );
		}

		return array(
			'status'       => $backup['status'],
			'progress'     => 'completed' === $backup['status'] ? 100 : 50,
			'file_size'    => $backup['file_size'],
			'items_count'  => $backup['items_count'],
			'completed_at' => $backup['completed_at'],
		);
	}

	/**
	 * Delete backup.
	 *
	 * @param int $backup_id Backup ID.
	 * @return bool|\WP_Error
	 */
	public function delete_backup( $backup_id ) {
		global $wpdb;

		$backup = $this->get_backup( $backup_id );
		if ( ! $backup ) {
			return new \WP_Error( 'not_found', __( 'Backup not found.', 'bkx-backup-recovery' ) );
		}

		// Delete file.
		if ( ! empty( $backup['file_path'] ) && file_exists( $backup['file_path'] ) ) {
			wp_delete_file( $backup['file_path'] );
		}

		// Delete record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'bkx_backup_history',
			array( 'id' => $backup_id ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Get download URL for backup.
	 *
	 * @param int $backup_id Backup ID.
	 * @return string|false
	 */
	public function get_download_url( $backup_id ) {
		$backup = $this->get_backup( $backup_id );

		if ( ! $backup || ! file_exists( $backup['file_path'] ) ) {
			return false;
		}

		// Generate temporary download URL.
		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'] . '/bkx-backups/';

		// For security, we should use a download handler instead of direct URL.
		// For now, return admin-ajax URL.
		return add_query_arg(
			array(
				'action'    => 'bkx_backup_download_file',
				'backup_id' => $backup_id,
				'nonce'     => wp_create_nonce( 'bkx_backup_download_' . $backup_id ),
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Cleanup old backups.
	 *
	 * @param int $retention_days Number of days to retain.
	 */
	public function cleanup_old_backups( $retention_days ) {
		global $wpdb;

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		// Get old backups.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$old_backups = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bkx_backup_history WHERE created_at < %s AND backup_type != 'manual'",
				$cutoff_date
			)
		);

		foreach ( $old_backups as $backup_id ) {
			$this->delete_backup( $backup_id );
		}
	}
}
