<?php
/**
 * Recording Manager Service.
 *
 * @package BookingX\VideoConsultation
 * @since   1.0.0
 */

namespace BookingX\VideoConsultation\Services;

/**
 * RecordingManager class.
 *
 * Manages video recordings storage and retrieval.
 *
 * @since 1.0.0
 */
class RecordingManager {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings Plugin settings.
	 */
	public function __construct( $settings ) {
		global $wpdb;
		$this->settings = $settings;
		$this->table    = $wpdb->prefix . 'bkx_video_recordings';
	}

	/**
	 * Check if recording is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->settings['enable_recording'] );
	}

	/**
	 * Save a recording.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $room_id Room database ID.
	 * @param int    $booking_id Booking ID.
	 * @param array  $file_data File data (tmp_name, name, size, type).
	 * @return int|\WP_Error Recording ID or error.
	 */
	public function save_recording( $room_id, $booking_id, $file_data ) {
		if ( ! $this->is_enabled() ) {
			return new \WP_Error( 'recording_disabled', __( 'Recording is not enabled.', 'bkx-video-consultation' ) );
		}

		// Validate file type.
		$allowed_types = array( 'video/webm', 'video/mp4', 'video/ogg' );
		if ( ! in_array( $file_data['type'], $allowed_types, true ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid file type.', 'bkx-video-consultation' ) );
		}

		// Generate unique filename.
		$recording_id = 'rec-' . wp_generate_password( 16, false );
		$extension    = pathinfo( $file_data['name'], PATHINFO_EXTENSION ) ?: 'webm';
		$filename     = $recording_id . '.' . $extension;

		// Get upload directory.
		$upload_dir = $this->get_recordings_directory();
		if ( is_wp_error( $upload_dir ) ) {
			return $upload_dir;
		}

		$file_path = $upload_dir['path'] . '/' . $filename;
		$file_url  = $upload_dir['url'] . '/' . $filename;

		// Move uploaded file.
		if ( isset( $file_data['tmp_name'] ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$moved = move_uploaded_file( $file_data['tmp_name'], $file_path );
		} elseif ( isset( $file_data['content'] ) ) {
			// Base64 content from WebRTC recording.
			$content = base64_decode( $file_data['content'] );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$moved = file_put_contents( $file_path, $content );
		} else {
			return new \WP_Error( 'no_file', __( 'No file data provided.', 'bkx-video-consultation' ) );
		}

		if ( ! $moved ) {
			return new \WP_Error( 'save_failed', __( 'Failed to save recording.', 'bkx-video-consultation' ) );
		}

		// Calculate expiration.
		$retention_days = $this->settings['recording_retention_days'] ?? 30;
		$expires_at     = gmdate( 'Y-m-d H:i:s', strtotime( "+{$retention_days} days" ) );

		// Insert record.
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$this->table,
			array(
				'room_id'          => $room_id,
				'booking_id'       => $booking_id,
				'recording_id'     => $recording_id,
				'file_url'         => $file_url,
				'file_size'        => filesize( $file_path ),
				'duration_seconds' => $file_data['duration'] ?? 0,
				'format'           => $extension,
				'status'           => 'completed',
				'expires_at'       => $expires_at,
			),
			array( '%d', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			// Clean up file.
			wp_delete_file( $file_path );
			return new \WP_Error( 'db_error', __( 'Failed to save recording record.', 'bkx-video-consultation' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get recordings for a room.
	 *
	 * @since 1.0.0
	 *
	 * @param int $room_id Room database ID.
	 * @return array
	 */
	public function get_recordings_by_room( $room_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE room_id = %d ORDER BY created_at DESC",
				$this->table,
				$room_id
			)
		);
	}

	/**
	 * Get recordings for a booking.
	 *
	 * @since 1.0.0
	 *
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	public function get_recordings_by_booking( $booking_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE booking_id = %d ORDER BY created_at DESC",
				$this->table,
				$booking_id
			)
		);
	}

	/**
	 * Get a recording by ID.
	 *
	 * @since 1.0.0
	 *
	 * @param int $recording_id Recording database ID.
	 * @return object|null
	 */
	public function get_recording( $recording_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				$this->table,
				$recording_id
			)
		);
	}

	/**
	 * Delete a recording.
	 *
	 * @since 1.0.0
	 *
	 * @param int $recording_id Recording database ID.
	 * @return bool|\WP_Error
	 */
	public function delete_recording( $recording_id ) {
		global $wpdb;

		$recording = $this->get_recording( $recording_id );
		if ( ! $recording ) {
			return new \WP_Error( 'not_found', __( 'Recording not found.', 'bkx-video-consultation' ) );
		}

		// Delete file.
		$file_path = $this->url_to_path( $recording->file_url );
		if ( file_exists( $file_path ) ) {
			wp_delete_file( $file_path );
		}

		// Delete record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$this->table,
			array( 'id' => $recording_id ),
			array( '%d' )
		);

		return true;
	}

	/**
	 * Increment download count.
	 *
	 * @since 1.0.0
	 *
	 * @param int $recording_id Recording database ID.
	 */
	public function increment_download_count( $recording_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET download_count = download_count + 1 WHERE id = %d",
				$this->table,
				$recording_id
			)
		);
	}

	/**
	 * Cleanup expired recordings.
	 *
	 * @since 1.0.0
	 */
	public function cleanup_expired() {
		global $wpdb;

		// Get expired recordings.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$expired = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, file_url FROM %i WHERE expires_at < NOW()",
				$this->table
			)
		);

		foreach ( $expired as $recording ) {
			// Delete file.
			$file_path = $this->url_to_path( $recording->file_url );
			if ( file_exists( $file_path ) ) {
				wp_delete_file( $file_path );
			}

			// Delete record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->delete(
				$this->table,
				array( 'id' => $recording->id ),
				array( '%d' )
			);
		}

		/**
		 * Fires after expired recordings are cleaned up.
		 *
		 * @param int $count Number of recordings deleted.
		 */
		do_action( 'bkx_video_recordings_cleaned', count( $expired ) );
	}

	/**
	 * Get total storage used.
	 *
	 * @since 1.0.0
	 *
	 * @return int Size in bytes.
	 */
	public function get_total_storage() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(file_size) FROM %i",
				$this->table
			)
		);
	}

	/**
	 * Get recordings directory.
	 *
	 * @since 1.0.0
	 *
	 * @return array|\WP_Error
	 */
	private function get_recordings_directory() {
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'] . '/bkx-recordings';
		$base_url   = $upload_dir['baseurl'] . '/bkx-recordings';

		// Create directory if not exists.
		if ( ! file_exists( $base_dir ) ) {
			if ( ! wp_mkdir_p( $base_dir ) ) {
				return new \WP_Error( 'mkdir_failed', __( 'Failed to create recordings directory.', 'bkx-video-consultation' ) );
			}

			// Add .htaccess to protect files.
			$htaccess_content = "Options -Indexes\n";
			$htaccess_content .= "<FilesMatch \"\.(webm|mp4|ogg)$\">\n";
			$htaccess_content .= "    # Only allow authenticated access\n";
			$htaccess_content .= "</FilesMatch>\n";

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $base_dir . '/.htaccess', $htaccess_content );

			// Add index.php.
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $base_dir . '/index.php', '<?php // Silence is golden' );
		}

		// Create year/month subdirectory.
		$subdir  = gmdate( 'Y/m' );
		$full_dir = $base_dir . '/' . $subdir;

		if ( ! file_exists( $full_dir ) ) {
			if ( ! wp_mkdir_p( $full_dir ) ) {
				return new \WP_Error( 'mkdir_failed', __( 'Failed to create recordings subdirectory.', 'bkx-video-consultation' ) );
			}
		}

		return array(
			'path' => $full_dir,
			'url'  => $base_url . '/' . $subdir,
		);
	}

	/**
	 * Convert URL to file path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url File URL.
	 * @return string
	 */
	private function url_to_path( $url ) {
		$upload_dir = wp_upload_dir();
		return str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
	}

	/**
	 * Generate secure download URL.
	 *
	 * @since 1.0.0
	 *
	 * @param int $recording_id Recording database ID.
	 * @param int $expires_in Expiration time in seconds.
	 * @return string
	 */
	public function get_download_url( $recording_id, $expires_in = 3600 ) {
		$expires = time() + $expires_in;
		$token   = wp_hash( $recording_id . '|' . $expires );

		return add_query_arg(
			array(
				'bkx_download_recording' => $recording_id,
				'expires'                => $expires,
				'token'                  => $token,
			),
			home_url()
		);
	}

	/**
	 * Verify download token.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $recording_id Recording ID.
	 * @param int    $expires Expiration timestamp.
	 * @param string $token Token to verify.
	 * @return bool
	 */
	public function verify_download_token( $recording_id, $expires, $token ) {
		if ( time() > $expires ) {
			return false;
		}

		$expected = wp_hash( $recording_id . '|' . $expires );
		return hash_equals( $expected, $token );
	}
}
