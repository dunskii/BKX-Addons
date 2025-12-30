<?php
/**
 * Import Service.
 *
 * @package BookingX\BackupRecovery
 */

namespace BookingX\BackupRecovery\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ImportService class.
 */
class ImportService {

	/**
	 * Allowed import types.
	 *
	 * @var array
	 */
	private $allowed_types = array( 'csv', 'json', 'xml' );

	/**
	 * Import data from file.
	 *
	 * @param string $file_path File path.
	 * @param string $type      Data type (bookings, services, staff, customers).
	 * @param array  $options   Import options.
	 * @return int|\WP_Error Number of items imported or error.
	 */
	public function import( $file_path, $type, $options = array() ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_missing', __( 'Import file not found.', 'bkx-backup-recovery' ) );
		}

		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, $this->allowed_types, true ) ) {
			return new \WP_Error( 'invalid_format', __( 'Invalid import format. Allowed: CSV, JSON, XML.', 'bkx-backup-recovery' ) );
		}

		// Parse file based on extension.
		switch ( $extension ) {
			case 'csv':
				$data = $this->parse_csv( $file_path );
				break;

			case 'json':
				$data = $this->parse_json( $file_path );
				break;

			case 'xml':
				$data = $this->parse_xml( $file_path );
				break;

			default:
				return new \WP_Error( 'invalid_format', __( 'Unsupported file format.', 'bkx-backup-recovery' ) );
		}

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data ) ) {
			return new \WP_Error( 'no_data', __( 'No data found in import file.', 'bkx-backup-recovery' ) );
		}

		// Import based on type.
		$defaults = array(
			'update_existing' => false,
			'skip_duplicates' => true,
			'dry_run'         => false,
		);
		$options  = wp_parse_args( $options, $defaults );

		switch ( $type ) {
			case 'bookings':
				return $this->import_bookings( $data, $options );

			case 'services':
				return $this->import_services( $data, $options );

			case 'staff':
				return $this->import_staff( $data, $options );

			case 'customers':
				return $this->import_customers( $data, $options );

			default:
				return new \WP_Error( 'invalid_type', __( 'Invalid import type.', 'bkx-backup-recovery' ) );
		}
	}

	/**
	 * Parse CSV file.
	 *
	 * @param string $file_path File path.
	 * @return array|\WP_Error
	 */
	private function parse_csv( $file_path ) {
		$data = array();
		$fp   = fopen( $file_path, 'r' );

		if ( ! $fp ) {
			return new \WP_Error( 'read_error', __( 'Could not read CSV file.', 'bkx-backup-recovery' ) );
		}

		$headers = fgetcsv( $fp );
		if ( ! $headers ) {
			fclose( $fp );
			return new \WP_Error( 'invalid_csv', __( 'CSV file has no headers.', 'bkx-backup-recovery' ) );
		}

		// Sanitize headers.
		$headers = array_map( 'sanitize_key', $headers );

		while ( ( $row = fgetcsv( $fp ) ) !== false ) {
			if ( count( $row ) === count( $headers ) ) {
				$data[] = array_combine( $headers, $row );
			}
		}

		fclose( $fp );
		return $data;
	}

	/**
	 * Parse JSON file.
	 *
	 * @param string $file_path File path.
	 * @return array|\WP_Error
	 */
	private function parse_json( $file_path ) {
		$content = file_get_contents( $file_path );

		if ( ! $content ) {
			return new \WP_Error( 'read_error', __( 'Could not read JSON file.', 'bkx-backup-recovery' ) );
		}

		$data = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'invalid_json', __( 'Invalid JSON format.', 'bkx-backup-recovery' ) );
		}

		return $data;
	}

	/**
	 * Parse XML file.
	 *
	 * @param string $file_path File path.
	 * @return array|\WP_Error
	 */
	private function parse_xml( $file_path ) {
		libxml_use_internal_errors( true );
		$xml = simplexml_load_file( $file_path );

		if ( ! $xml ) {
			$errors = libxml_get_errors();
			libxml_clear_errors();
			return new \WP_Error( 'invalid_xml', __( 'Invalid XML format.', 'bkx-backup-recovery' ) );
		}

		$data = array();

		// Handle different XML structures.
		foreach ( $xml->children() as $section ) {
			foreach ( $section->children() as $item ) {
				$row = array();
				foreach ( $item->children() as $key => $value ) {
					$row[ $key ] = (string) $value;
				}
				$data[] = $row;
			}
		}

		return $data;
	}

	/**
	 * Import bookings.
	 *
	 * @param array $data    Import data.
	 * @param array $options Import options.
	 * @return int|\WP_Error
	 */
	private function import_bookings( $data, $options ) {
		$imported = 0;
		$skipped  = 0;
		$errors   = array();

		foreach ( $data as $row ) {
			// Validate required fields.
			if ( empty( $row['customer_email'] ) || empty( $row['booking_date'] ) ) {
				$errors[] = sprintf(
					/* translators: %s: Row identifier */
					__( 'Missing required fields in row: %s', 'bkx-backup-recovery' ),
					wp_json_encode( $row )
				);
				continue;
			}

			// Check for duplicates.
			if ( $options['skip_duplicates'] ) {
				$existing = $this->find_existing_booking( $row );
				if ( $existing ) {
					if ( $options['update_existing'] ) {
						$this->update_booking( $existing, $row );
						$imported++;
					} else {
						$skipped++;
					}
					continue;
				}
			}

			if ( $options['dry_run'] ) {
				$imported++;
				continue;
			}

			// Create booking.
			$result = $this->create_booking( $row );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			} else {
				$imported++;
			}
		}

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * Import services.
	 *
	 * @param array $data    Import data.
	 * @param array $options Import options.
	 * @return array
	 */
	private function import_services( $data, $options ) {
		$imported = 0;
		$skipped  = 0;
		$errors   = array();

		foreach ( $data as $row ) {
			if ( empty( $row['title'] ) ) {
				$errors[] = __( 'Service title is required.', 'bkx-backup-recovery' );
				continue;
			}

			// Check for duplicates.
			if ( $options['skip_duplicates'] ) {
				$existing = get_page_by_title( $row['title'], OBJECT, 'bkx_base' );
				if ( $existing ) {
					if ( $options['update_existing'] ) {
						$this->update_service( $existing->ID, $row );
						$imported++;
					} else {
						$skipped++;
					}
					continue;
				}
			}

			if ( $options['dry_run'] ) {
				$imported++;
				continue;
			}

			// Create service.
			$post_data = array(
				'post_type'    => 'bkx_base',
				'post_title'   => sanitize_text_field( $row['title'] ),
				'post_content' => isset( $row['description'] ) ? wp_kses_post( $row['description'] ) : '',
				'post_status'  => isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'publish',
			);

			$post_id = wp_insert_post( $post_data );

			if ( is_wp_error( $post_id ) ) {
				$errors[] = $post_id->get_error_message();
			} else {
				// Save meta.
				if ( isset( $row['price'] ) ) {
					update_post_meta( $post_id, 'base_price', floatval( $row['price'] ) );
				}
				if ( isset( $row['duration'] ) ) {
					update_post_meta( $post_id, 'base_time', absint( $row['duration'] ) );
				}
				$imported++;
			}
		}

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * Import staff.
	 *
	 * @param array $data    Import data.
	 * @param array $options Import options.
	 * @return array
	 */
	private function import_staff( $data, $options ) {
		$imported = 0;
		$skipped  = 0;
		$errors   = array();

		foreach ( $data as $row ) {
			if ( empty( $row['title'] ) ) {
				$errors[] = __( 'Staff name is required.', 'bkx-backup-recovery' );
				continue;
			}

			// Check for duplicates.
			if ( $options['skip_duplicates'] ) {
				$existing = get_page_by_title( $row['title'], OBJECT, 'bkx_seat' );
				if ( $existing ) {
					if ( $options['update_existing'] ) {
						$this->update_staff( $existing->ID, $row );
						$imported++;
					} else {
						$skipped++;
					}
					continue;
				}
			}

			if ( $options['dry_run'] ) {
				$imported++;
				continue;
			}

			// Create staff.
			$post_data = array(
				'post_type'    => 'bkx_seat',
				'post_title'   => sanitize_text_field( $row['title'] ),
				'post_content' => isset( $row['description'] ) ? wp_kses_post( $row['description'] ) : '',
				'post_status'  => isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'publish',
			);

			$post_id = wp_insert_post( $post_data );

			if ( is_wp_error( $post_id ) ) {
				$errors[] = $post_id->get_error_message();
			} else {
				// Save meta.
				if ( isset( $row['email'] ) ) {
					update_post_meta( $post_id, 'seat_email', sanitize_email( $row['email'] ) );
				}
				if ( isset( $row['phone'] ) ) {
					update_post_meta( $post_id, 'seat_phone', sanitize_text_field( $row['phone'] ) );
				}
				$imported++;
			}
		}

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * Import customers.
	 *
	 * @param array $data    Import data.
	 * @param array $options Import options.
	 * @return array
	 */
	private function import_customers( $data, $options ) {
		$imported = 0;
		$skipped  = 0;
		$errors   = array();

		foreach ( $data as $row ) {
			if ( empty( $row['email'] ) ) {
				$errors[] = __( 'Customer email is required.', 'bkx-backup-recovery' );
				continue;
			}

			$email = sanitize_email( $row['email'] );

			// Check for existing user.
			$existing_user = get_user_by( 'email', $email );

			if ( $existing_user ) {
				if ( $options['update_existing'] ) {
					// Update user meta.
					if ( isset( $row['name'] ) ) {
						wp_update_user( array(
							'ID'           => $existing_user->ID,
							'display_name' => sanitize_text_field( $row['name'] ),
						) );
					}
					if ( isset( $row['phone'] ) ) {
						update_user_meta( $existing_user->ID, 'billing_phone', sanitize_text_field( $row['phone'] ) );
					}
					$imported++;
				} else {
					$skipped++;
				}
				continue;
			}

			if ( $options['dry_run'] ) {
				$imported++;
				continue;
			}

			// Create user.
			$username = sanitize_user( current( explode( '@', $email ) ) );
			$username = $this->generate_unique_username( $username );

			$user_id = wp_create_user( $username, wp_generate_password(), $email );

			if ( is_wp_error( $user_id ) ) {
				$errors[] = $user_id->get_error_message();
			} else {
				// Update user data.
				if ( isset( $row['name'] ) ) {
					wp_update_user( array(
						'ID'           => $user_id,
						'display_name' => sanitize_text_field( $row['name'] ),
						'first_name'   => sanitize_text_field( $row['name'] ),
					) );
				}
				if ( isset( $row['phone'] ) ) {
					update_user_meta( $user_id, 'billing_phone', sanitize_text_field( $row['phone'] ) );
				}

				// Set role.
				$user = new \WP_User( $user_id );
				$user->set_role( 'customer' );

				$imported++;
			}
		}

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * Find existing booking.
	 *
	 * @param array $row Row data.
	 * @return int|false Post ID or false.
	 */
	private function find_existing_booking( $row ) {
		$args = array(
			'post_type'   => 'bkx_booking',
			'post_status' => 'any',
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'   => 'customer_email',
					'value' => sanitize_email( $row['customer_email'] ),
				),
				array(
					'key'   => 'booking_date',
					'value' => sanitize_text_field( $row['booking_date'] ),
				),
			),
			'fields'      => 'ids',
			'numberposts' => 1,
		);

		if ( isset( $row['booking_time'] ) ) {
			$args['meta_query'][] = array(
				'key'   => 'booking_time',
				'value' => sanitize_text_field( $row['booking_time'] ),
			);
		}

		$posts = get_posts( $args );

		return ! empty( $posts ) ? $posts[0] : false;
	}

	/**
	 * Create booking.
	 *
	 * @param array $row Row data.
	 * @return int|\WP_Error
	 */
	private function create_booking( $row ) {
		$post_data = array(
			'post_type'   => 'bkx_booking',
			'post_title'  => sprintf(
				'Booking - %s - %s',
				sanitize_text_field( $row['customer_email'] ),
				sanitize_text_field( $row['booking_date'] )
			),
			'post_status' => isset( $row['status'] ) ? sanitize_key( $row['status'] ) : 'bkx-pending',
		);

		$post_id = wp_insert_post( $post_data );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Save meta.
		$meta_fields = array(
			'customer_name'  => 'sanitize_text_field',
			'customer_email' => 'sanitize_email',
			'customer_phone' => 'sanitize_text_field',
			'booking_date'   => 'sanitize_text_field',
			'booking_time'   => 'sanitize_text_field',
			'base_name'      => 'sanitize_text_field',
			'seat_name'      => 'sanitize_text_field',
			'total_amount'   => 'floatval',
		);

		foreach ( $meta_fields as $field => $sanitizer ) {
			if ( isset( $row[ $field ] ) ) {
				update_post_meta( $post_id, $field, call_user_func( $sanitizer, $row[ $field ] ) );
			}
		}

		return $post_id;
	}

	/**
	 * Update booking.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $row     Row data.
	 */
	private function update_booking( $post_id, $row ) {
		$meta_fields = array(
			'customer_name'  => 'sanitize_text_field',
			'customer_email' => 'sanitize_email',
			'customer_phone' => 'sanitize_text_field',
			'booking_date'   => 'sanitize_text_field',
			'booking_time'   => 'sanitize_text_field',
			'base_name'      => 'sanitize_text_field',
			'seat_name'      => 'sanitize_text_field',
			'total_amount'   => 'floatval',
		);

		foreach ( $meta_fields as $field => $sanitizer ) {
			if ( isset( $row[ $field ] ) ) {
				update_post_meta( $post_id, $field, call_user_func( $sanitizer, $row[ $field ] ) );
			}
		}

		if ( isset( $row['status'] ) ) {
			wp_update_post( array(
				'ID'          => $post_id,
				'post_status' => sanitize_key( $row['status'] ),
			) );
		}
	}

	/**
	 * Update service.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $row     Row data.
	 */
	private function update_service( $post_id, $row ) {
		$post_data = array( 'ID' => $post_id );

		if ( isset( $row['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $row['title'] );
		}
		if ( isset( $row['description'] ) ) {
			$post_data['post_content'] = wp_kses_post( $row['description'] );
		}
		if ( isset( $row['status'] ) ) {
			$post_data['post_status'] = sanitize_key( $row['status'] );
		}

		wp_update_post( $post_data );

		if ( isset( $row['price'] ) ) {
			update_post_meta( $post_id, 'base_price', floatval( $row['price'] ) );
		}
		if ( isset( $row['duration'] ) ) {
			update_post_meta( $post_id, 'base_time', absint( $row['duration'] ) );
		}
	}

	/**
	 * Update staff.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $row     Row data.
	 */
	private function update_staff( $post_id, $row ) {
		$post_data = array( 'ID' => $post_id );

		if ( isset( $row['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $row['title'] );
		}
		if ( isset( $row['description'] ) ) {
			$post_data['post_content'] = wp_kses_post( $row['description'] );
		}
		if ( isset( $row['status'] ) ) {
			$post_data['post_status'] = sanitize_key( $row['status'] );
		}

		wp_update_post( $post_data );

		if ( isset( $row['email'] ) ) {
			update_post_meta( $post_id, 'seat_email', sanitize_email( $row['email'] ) );
		}
		if ( isset( $row['phone'] ) ) {
			update_post_meta( $post_id, 'seat_phone', sanitize_text_field( $row['phone'] ) );
		}
	}

	/**
	 * Generate unique username.
	 *
	 * @param string $username Base username.
	 * @return string
	 */
	private function generate_unique_username( $username ) {
		$original = $username;
		$counter  = 1;

		while ( username_exists( $username ) ) {
			$username = $original . $counter;
			$counter++;
		}

		return $username;
	}

	/**
	 * Validate import file.
	 *
	 * @param array $file Uploaded file data.
	 * @return true|\WP_Error
	 */
	public function validate_file( $file ) {
		if ( empty( $file['tmp_name'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file uploaded.', 'bkx-backup-recovery' ) );
		}

		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, $this->allowed_types, true ) ) {
			return new \WP_Error(
				'invalid_type',
				sprintf(
					/* translators: %s: Allowed file types */
					__( 'Invalid file type. Allowed types: %s', 'bkx-backup-recovery' ),
					implode( ', ', $this->allowed_types )
				)
			);
		}

		// Max file size: 50MB.
		$max_size = 50 * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			return new \WP_Error( 'file_too_large', __( 'File size exceeds maximum limit (50MB).', 'bkx-backup-recovery' ) );
		}

		return true;
	}

	/**
	 * Get import template.
	 *
	 * @param string $type Data type.
	 * @param string $format File format.
	 * @return string|false Template content or false.
	 */
	public function get_template( $type, $format = 'csv' ) {
		$headers = $this->get_headers_for_type( $type );

		if ( empty( $headers ) ) {
			return false;
		}

		switch ( $format ) {
			case 'csv':
				return implode( ',', $headers ) . "\n";

			case 'json':
				$template = array();
				foreach ( $headers as $header ) {
					$template[ $header ] = '';
				}
				return wp_json_encode( array( $template ), JSON_PRETTY_PRINT );

			default:
				return false;
		}
	}

	/**
	 * Get headers for data type.
	 *
	 * @param string $type Data type.
	 * @return array
	 */
	private function get_headers_for_type( $type ) {
		$headers = array(
			'bookings'  => array(
				'customer_name',
				'customer_email',
				'customer_phone',
				'booking_date',
				'booking_time',
				'service',
				'staff',
				'total',
				'status',
			),
			'services'  => array(
				'title',
				'description',
				'price',
				'duration',
				'status',
			),
			'staff'     => array(
				'title',
				'description',
				'email',
				'phone',
				'status',
			),
			'customers' => array(
				'name',
				'email',
				'phone',
			),
		);

		return $headers[ $type ] ?? array();
	}
}
