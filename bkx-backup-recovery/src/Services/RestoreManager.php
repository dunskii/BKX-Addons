<?php
/**
 * Restore Manager service.
 *
 * @package BookingX\BackupRecovery
 */

namespace BookingX\BackupRecovery\Services;

defined( 'ABSPATH' ) || exit;

/**
 * RestoreManager class.
 */
class RestoreManager {

	/**
	 * Restore a backup.
	 *
	 * @param int $backup_id Backup ID.
	 * @return int|\WP_Error Items restored or error.
	 */
	public function restore_backup( $backup_id ) {
		global $wpdb;

		// Get backup info.
		$backup_manager = new BackupManager( array() );
		$backup         = $backup_manager->get_backup( $backup_id );

		if ( ! $backup ) {
			return new \WP_Error( 'not_found', __( 'Backup not found.', 'bkx-backup-recovery' ) );
		}

		if ( ! file_exists( $backup['file_path'] ) ) {
			return new \WP_Error( 'file_missing', __( 'Backup file not found.', 'bkx-backup-recovery' ) );
		}

		// Create restore record.
		$restore_id = $this->create_restore_record( $backup_id );

		try {
			// Read backup file.
			$file_path = $backup['file_path'];

			// If ZIP, extract first.
			if ( pathinfo( $file_path, PATHINFO_EXTENSION ) === 'zip' ) {
				$json_data = $this->extract_from_zip( $file_path );
			} else {
				$json_data = file_get_contents( $file_path );
			}

			if ( ! $json_data ) {
				throw new \Exception( __( 'Failed to read backup file.', 'bkx-backup-recovery' ) );
			}

			$data = json_decode( $json_data, true );
			if ( ! $data || ! isset( $data['data'] ) ) {
				throw new \Exception( __( 'Invalid backup format.', 'bkx-backup-recovery' ) );
			}

			$items_restored = 0;

			// Restore bookings.
			if ( ! empty( $data['data']['bookings'] ) ) {
				$items_restored += $this->restore_posts( $data['data']['bookings'], 'bkx_booking' );
			}

			// Restore services.
			if ( ! empty( $data['data']['services'] ) ) {
				$items_restored += $this->restore_posts( $data['data']['services'], 'bkx_base' );
			}

			// Restore staff.
			if ( ! empty( $data['data']['staff'] ) ) {
				$items_restored += $this->restore_posts( $data['data']['staff'], 'bkx_seat' );
			}

			// Restore extras.
			if ( ! empty( $data['data']['extras'] ) ) {
				$items_restored += $this->restore_posts( $data['data']['extras'], 'bkx_addition' );
			}

			// Restore settings.
			if ( ! empty( $data['data']['settings'] ) ) {
				foreach ( $data['data']['settings'] as $option ) {
					update_option( $option['option_name'], maybe_unserialize( $option['option_value'] ) );
				}
			}

			// Restore custom tables.
			$custom_tables = array( 'bkx_consents', 'bkx_booking_sessions' );
			foreach ( $custom_tables as $table_name ) {
				if ( ! empty( $data['data'][ $table_name ] ) ) {
					$items_restored += $this->restore_table( $table_name, $data['data'][ $table_name ] );
				}
			}

			// Update restore record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'bkx_restore_history',
				array(
					'status'         => 'completed',
					'items_restored' => $items_restored,
					'completed_at'   => current_time( 'mysql' ),
				),
				array( 'id' => $restore_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);

			// Clear caches.
			wp_cache_flush();

			return $items_restored;

		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'bkx_restore_history',
				array(
					'status'        => 'failed',
					'error_message' => $e->getMessage(),
					'completed_at'  => current_time( 'mysql' ),
				),
				array( 'id' => $restore_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			return new \WP_Error( 'restore_failed', $e->getMessage() );
		}
	}

	/**
	 * Extract data from ZIP file.
	 *
	 * @param string $zip_path ZIP file path.
	 * @return string|false JSON content or false.
	 */
	private function extract_from_zip( $zip_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return false;
		}

		$zip = new \ZipArchive();
		if ( $zip->open( $zip_path ) !== true ) {
			return false;
		}

		$json_content = $zip->getFromIndex( 0 );
		$zip->close();

		return $json_content;
	}

	/**
	 * Restore posts from backup.
	 *
	 * @param array  $items     Backup items.
	 * @param string $post_type Post type.
	 * @return int Number of items restored.
	 */
	private function restore_posts( $items, $post_type ) {
		$count = 0;

		foreach ( $items as $item ) {
			$post_data = $item['post'];
			$meta_data = $item['meta'] ?? array();

			// Remove ID to create new post or update existing.
			$original_id = $post_data['ID'];
			unset( $post_data['ID'] );

			// Check if post exists by guid or unique meta.
			$existing = get_posts( array(
				'post_type'   => $post_type,
				'meta_key'    => '_bkx_original_id',
				'meta_value'  => $original_id,
				'post_status' => 'any',
				'numberposts' => 1,
			) );

			if ( ! empty( $existing ) ) {
				// Update existing.
				$post_data['ID'] = $existing[0]->ID;
				wp_update_post( $post_data );
				$post_id = $existing[0]->ID;
			} else {
				// Create new.
				$post_id = wp_insert_post( $post_data );
				if ( $post_id && ! is_wp_error( $post_id ) ) {
					update_post_meta( $post_id, '_bkx_original_id', $original_id );
				}
			}

			if ( $post_id && ! is_wp_error( $post_id ) ) {
				// Restore meta.
				foreach ( $meta_data as $key => $values ) {
					delete_post_meta( $post_id, $key );
					foreach ( (array) $values as $value ) {
						add_post_meta( $post_id, $key, maybe_unserialize( $value ) );
					}
				}
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Restore custom table data.
	 *
	 * @param string $table_name Table name (without prefix).
	 * @param array  $rows       Table rows.
	 * @return int Number of rows restored.
	 */
	private function restore_table( $table_name, $rows ) {
		global $wpdb;

		$full_table = $wpdb->prefix . $table_name;
		$count      = 0;

		// Check if table exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_table ) );
		if ( ! $exists ) {
			return 0;
		}

		foreach ( $rows as $row ) {
			// Remove auto-increment ID.
			$original_id = $row['id'] ?? null;
			unset( $row['id'] );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert( $full_table, $row );

			if ( $result ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Create restore record.
	 *
	 * @param int $backup_id Backup ID.
	 * @return int
	 */
	private function create_restore_record( $backup_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'bkx_restore_history',
			array(
				'backup_id'   => $backup_id,
				'status'      => 'running',
				'started_at'  => current_time( 'mysql' ),
				'restored_by' => get_current_user_id(),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Get restore history.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_restore_history( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'per_page' => 20,
			'page'     => 1,
		);

		$args   = wp_parse_args( $args, $defaults );
		$offset = ( $args['page'] - 1 ) * $args['per_page'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}bkx_restore_history" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$history = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT r.*, b.file_name, b.backup_type
				FROM {$wpdb->prefix}bkx_restore_history r
				LEFT JOIN {$wpdb->prefix}bkx_backup_history b ON r.backup_id = b.id
				ORDER BY r.created_at DESC
				LIMIT %d OFFSET %d",
				$args['per_page'],
				$offset
			),
			ARRAY_A
		);

		return array(
			'history' => $history,
			'total'   => (int) $total,
			'pages'   => ceil( $total / $args['per_page'] ),
		);
	}
}
