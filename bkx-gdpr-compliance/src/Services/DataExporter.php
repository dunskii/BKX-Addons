<?php
/**
 * Data Exporter service.
 *
 * @package BookingX\GdprCompliance\Services
 */

namespace BookingX\GdprCompliance\Services;

defined( 'ABSPATH' ) || exit;

/**
 * DataExporter class.
 */
class DataExporter {

	/**
	 * Export user data.
	 *
	 * @param string $email  Email address.
	 * @param string $format Export format (json, csv, xml).
	 * @return array|\WP_Error Array with file path and URL, or error.
	 */
	public function export_user_data( $email, $format = 'json' ) {
		$data = $this->collect_user_data( $email );

		if ( empty( $data ) ) {
			return new \WP_Error( 'no_data', __( 'No data found for this email address.', 'bkx-gdpr-compliance' ) );
		}

		$upload_dir = wp_upload_dir();
		$export_dir = $upload_dir['basedir'] . '/bkx-gdpr-exports';

		if ( ! file_exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir );

			// Add .htaccess for security.
			file_put_contents( $export_dir . '/.htaccess', 'deny from all' );
		}

		$filename = 'bkx-export-' . md5( $email . time() ) . '.' . $format;
		$filepath = $export_dir . '/' . $filename;

		switch ( $format ) {
			case 'csv':
				$this->write_csv( $filepath, $data );
				break;

			case 'xml':
				$this->write_xml( $filepath, $data );
				break;

			case 'json':
			default:
				$this->write_json( $filepath, $data );
				break;
		}

		// Create secure download token.
		$token = bin2hex( random_bytes( 16 ) );
		set_transient( 'bkx_gdpr_export_' . $token, $filepath, 7 * DAY_IN_SECONDS );

		$download_url = add_query_arg(
			array(
				'bkx_gdpr_download' => $token,
			),
			home_url()
		);

		do_action( 'bkx_gdpr_data_exported', $email, $filepath, $format );

		return array(
			'file' => $filepath,
			'url'  => $download_url,
		);
	}

	/**
	 * Collect all user data.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	public function collect_user_data( $email ) {
		$data = array(
			'export_date' => current_time( 'mysql' ),
			'email'       => $email,
			'data'        => array(),
		);

		// Get WordPress user data.
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			$data['data']['user_account'] = array(
				'id'           => $user->ID,
				'login'        => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'registered'   => $user->user_registered,
			);
		}

		// Get bookings.
		$data['data']['bookings'] = $this->get_bookings_data( $email );

		// Get consents.
		$data['data']['consents'] = $this->get_consents_data( $email );

		// Get cookie consents.
		$data['data']['cookie_consents'] = $this->get_cookie_consents_data( $email );

		// Allow add-ons to add their data.
		$data['data'] = apply_filters( 'bkx_gdpr_export_data', $data['data'], $email );

		return $data;
	}

	/**
	 * Get bookings data.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	private function get_bookings_data( $email ) {
		global $wpdb;

		$bookings = array();

		// Get bookings by customer email meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$booking_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = 'customer_email' AND meta_value = %s",
				$email
			)
		);

		foreach ( $booking_ids as $booking_id ) {
			$post = get_post( $booking_id );
			if ( ! $post || 'bkx_booking' !== $post->post_type ) {
				continue;
			}

			$meta = get_post_meta( $booking_id );

			$bookings[] = array(
				'id'             => $booking_id,
				'date'           => $post->post_date,
				'status'         => $post->post_status,
				'booking_date'   => $meta['booking_date'][0] ?? '',
				'booking_time'   => $meta['booking_time'][0] ?? '',
				'service'        => $meta['base_name'][0] ?? '',
				'staff'          => $meta['seat_name'][0] ?? '',
				'customer_name'  => ( $meta['customer_first_name'][0] ?? '' ) . ' ' . ( $meta['customer_last_name'][0] ?? '' ),
				'customer_email' => $meta['customer_email'][0] ?? '',
				'customer_phone' => $meta['customer_phone'][0] ?? '',
				'total'          => $meta['total_amount'][0] ?? '',
				'notes'          => $meta['booking_notes'][0] ?? '',
			);
		}

		return $bookings;
	}

	/**
	 * Get consents data.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	private function get_consents_data( $email ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT consent_type, consent_given, consent_text, source, given_at, withdrawn_at
				FROM {$wpdb->prefix}bkx_consent_records
				WHERE email = %s
				ORDER BY created_at DESC",
				$email
			),
			ARRAY_A
		);
	}

	/**
	 * Get cookie consents data.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	private function get_cookie_consents_data( $email ) {
		global $wpdb;

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT necessary, functional, analytics, marketing, created_at, updated_at
				FROM {$wpdb->prefix}bkx_cookie_consents
				WHERE user_id = %d
				ORDER BY created_at DESC",
				$user->ID
			),
			ARRAY_A
		);
	}

	/**
	 * Write JSON file.
	 *
	 * @param string $filepath File path.
	 * @param array  $data     Data to write.
	 */
	private function write_json( $filepath, $data ) {
		file_put_contents( $filepath, wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Write CSV file.
	 *
	 * @param string $filepath File path.
	 * @param array  $data     Data to write.
	 */
	private function write_csv( $filepath, $data ) {
		$handle = fopen( $filepath, 'w' );

		// Write bookings.
		if ( ! empty( $data['data']['bookings'] ) ) {
			fputcsv( $handle, array( '=== BOOKINGS ===' ) );
			fputcsv( $handle, array_keys( $data['data']['bookings'][0] ) );
			foreach ( $data['data']['bookings'] as $booking ) {
				fputcsv( $handle, $booking );
			}
			fputcsv( $handle, array( '' ) );
		}

		// Write consents.
		if ( ! empty( $data['data']['consents'] ) ) {
			fputcsv( $handle, array( '=== CONSENTS ===' ) );
			fputcsv( $handle, array_keys( $data['data']['consents'][0] ) );
			foreach ( $data['data']['consents'] as $consent ) {
				fputcsv( $handle, $consent );
			}
		}

		fclose( $handle );
	}

	/**
	 * Write XML file.
	 *
	 * @param string $filepath File path.
	 * @param array  $data     Data to write.
	 */
	private function write_xml( $filepath, $data ) {
		$xml = new \SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><gdpr_export></gdpr_export>' );

		$xml->addChild( 'export_date', $data['export_date'] );
		$xml->addChild( 'email', $data['email'] );

		$this->array_to_xml( $data['data'], $xml->addChild( 'data' ) );

		$xml->asXML( $filepath );
	}

	/**
	 * Convert array to XML.
	 *
	 * @param array             $data   Data array.
	 * @param \SimpleXMLElement $xml    XML element.
	 */
	private function array_to_xml( $data, &$xml ) {
		foreach ( $data as $key => $value ) {
			$key = is_numeric( $key ) ? 'item' : $key;
			if ( is_array( $value ) ) {
				$subnode = $xml->addChild( $key );
				$this->array_to_xml( $value, $subnode );
			} else {
				$xml->addChild( $key, htmlspecialchars( (string) $value ) );
			}
		}
	}

	/**
	 * WordPress exporter callback.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public function wp_exporter_callback( $email, $page = 1 ) {
		$bookings = $this->get_bookings_data( $email );

		if ( empty( $bookings ) ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$export_items = array();

		foreach ( $bookings as $booking ) {
			$export_items[] = array(
				'group_id'          => 'bkx-bookings',
				'group_label'       => __( 'BookingX Bookings', 'bkx-gdpr-compliance' ),
				'group_description' => __( 'Booking records from BookingX.', 'bkx-gdpr-compliance' ),
				'item_id'           => 'booking-' . $booking['id'],
				'data'              => array(
					array(
						'name'  => __( 'Booking ID', 'bkx-gdpr-compliance' ),
						'value' => $booking['id'],
					),
					array(
						'name'  => __( 'Date', 'bkx-gdpr-compliance' ),
						'value' => $booking['booking_date'],
					),
					array(
						'name'  => __( 'Time', 'bkx-gdpr-compliance' ),
						'value' => $booking['booking_time'],
					),
					array(
						'name'  => __( 'Service', 'bkx-gdpr-compliance' ),
						'value' => $booking['service'],
					),
					array(
						'name'  => __( 'Staff', 'bkx-gdpr-compliance' ),
						'value' => $booking['staff'],
					),
					array(
						'name'  => __( 'Customer Name', 'bkx-gdpr-compliance' ),
						'value' => $booking['customer_name'],
					),
					array(
						'name'  => __( 'Email', 'bkx-gdpr-compliance' ),
						'value' => $booking['customer_email'],
					),
					array(
						'name'  => __( 'Phone', 'bkx-gdpr-compliance' ),
						'value' => $booking['customer_phone'],
					),
					array(
						'name'  => __( 'Total', 'bkx-gdpr-compliance' ),
						'value' => $booking['total'],
					),
					array(
						'name'  => __( 'Status', 'bkx-gdpr-compliance' ),
						'value' => $booking['status'],
					),
				),
			);
		}

		return array(
			'data' => $export_items,
			'done' => true,
		);
	}
}
