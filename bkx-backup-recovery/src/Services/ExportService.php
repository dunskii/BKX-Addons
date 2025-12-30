<?php
/**
 * Export Service.
 *
 * @package BookingX\BackupRecovery
 */

namespace BookingX\BackupRecovery\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ExportService class.
 */
class ExportService {

	/**
	 * Export directory.
	 *
	 * @var string
	 */
	private $export_dir;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$upload_dir       = wp_upload_dir();
		$this->export_dir = $upload_dir['basedir'] . '/bkx-exports/';

		if ( ! is_dir( $this->export_dir ) ) {
			wp_mkdir_p( $this->export_dir );
			file_put_contents( $this->export_dir . '.htaccess', 'Deny from all' );
			file_put_contents( $this->export_dir . 'index.php', '<?php // Silence is golden.' );
		}
	}

	/**
	 * Export data.
	 *
	 * @param string $type   Export type (all, bookings, customers, services, staff).
	 * @param string $format Export format (csv, json, xml).
	 * @return string|\WP_Error Download URL or error.
	 */
	public function export( $type, $format = 'csv' ) {
		$data = $this->get_export_data( $type );

		if ( empty( $data ) ) {
			return new \WP_Error( 'no_data', __( 'No data to export.', 'bkx-backup-recovery' ) );
		}

		$filename = sprintf( 'bkx-export-%s-%s.%s', $type, gmdate( 'Y-m-d-His' ), $format );
		$filepath = $this->export_dir . $filename;

		switch ( $format ) {
			case 'csv':
				$this->export_to_csv( $data, $filepath );
				break;

			case 'json':
				$this->export_to_json( $data, $filepath );
				break;

			case 'xml':
				$this->export_to_xml( $data, $filepath, $type );
				break;

			default:
				return new \WP_Error( 'invalid_format', __( 'Invalid export format.', 'bkx-backup-recovery' ) );
		}

		// Return download URL.
		$upload_dir = wp_upload_dir();
		return $upload_dir['baseurl'] . '/bkx-exports/' . $filename;
	}

	/**
	 * Get data for export.
	 *
	 * @param string $type Export type.
	 * @return array
	 */
	private function get_export_data( $type ) {
		global $wpdb;

		$data = array();

		switch ( $type ) {
			case 'bookings':
				$bookings = get_posts( array(
					'post_type'      => 'bkx_booking',
					'posts_per_page' => -1,
					'post_status'    => 'any',
				) );

				foreach ( $bookings as $booking ) {
					$meta = get_post_meta( $booking->ID );
					$data[] = array(
						'id'              => $booking->ID,
						'title'           => $booking->post_title,
						'status'          => $booking->post_status,
						'date'            => $booking->post_date,
						'customer_name'   => $meta['customer_name'][0] ?? '',
						'customer_email'  => $meta['customer_email'][0] ?? '',
						'customer_phone'  => $meta['customer_phone'][0] ?? '',
						'booking_date'    => $meta['booking_date'][0] ?? '',
						'booking_time'    => $meta['booking_time'][0] ?? '',
						'service'         => $meta['base_name'][0] ?? '',
						'staff'           => $meta['seat_name'][0] ?? '',
						'total'           => $meta['total_amount'][0] ?? '',
					);
				}
				break;

			case 'customers':
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$customers = $wpdb->get_results(
					"SELECT DISTINCT
						MAX(pm1.meta_value) as email,
						MAX(pm2.meta_value) as name,
						MAX(pm3.meta_value) as phone,
						COUNT(p.ID) as booking_count
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'customer_email'
					LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'customer_name'
					LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'customer_phone'
					WHERE p.post_type = 'bkx_booking'
					GROUP BY pm1.meta_value",
					ARRAY_A
				);
				$data = $customers;
				break;

			case 'services':
				$services = get_posts( array(
					'post_type'      => 'bkx_base',
					'posts_per_page' => -1,
					'post_status'    => 'any',
				) );

				foreach ( $services as $service ) {
					$meta = get_post_meta( $service->ID );
					$data[] = array(
						'id'          => $service->ID,
						'title'       => $service->post_title,
						'status'      => $service->post_status,
						'description' => $service->post_content,
						'price'       => $meta['base_price'][0] ?? '',
						'duration'    => $meta['base_time'][0] ?? '',
					);
				}
				break;

			case 'staff':
				$staff = get_posts( array(
					'post_type'      => 'bkx_seat',
					'posts_per_page' => -1,
					'post_status'    => 'any',
				) );

				foreach ( $staff as $member ) {
					$meta = get_post_meta( $member->ID );
					$data[] = array(
						'id'          => $member->ID,
						'title'       => $member->post_title,
						'status'      => $member->post_status,
						'description' => $member->post_content,
						'email'       => $meta['seat_email'][0] ?? '',
						'phone'       => $meta['seat_phone'][0] ?? '',
					);
				}
				break;

			case 'all':
				// Combine all types.
				$data = array(
					'bookings'  => $this->get_export_data( 'bookings' ),
					'customers' => $this->get_export_data( 'customers' ),
					'services'  => $this->get_export_data( 'services' ),
					'staff'     => $this->get_export_data( 'staff' ),
				);
				break;
		}

		return $data;
	}

	/**
	 * Export to CSV.
	 *
	 * @param array  $data     Data to export.
	 * @param string $filepath File path.
	 */
	private function export_to_csv( $data, $filepath ) {
		$fp = fopen( $filepath, 'w' );

		if ( empty( $data ) ) {
			fclose( $fp );
			return;
		}

		// Handle nested data (for 'all' export).
		if ( isset( $data['bookings'] ) ) {
			foreach ( $data as $type => $items ) {
				fputcsv( $fp, array( strtoupper( $type ) ) );
				if ( ! empty( $items ) ) {
					fputcsv( $fp, array_keys( $items[0] ) );
					foreach ( $items as $row ) {
						fputcsv( $fp, $row );
					}
				}
				fputcsv( $fp, array( '' ) );
			}
		} else {
			// Single type export.
			fputcsv( $fp, array_keys( $data[0] ) );
			foreach ( $data as $row ) {
				fputcsv( $fp, $row );
			}
		}

		fclose( $fp );
	}

	/**
	 * Export to JSON.
	 *
	 * @param array  $data     Data to export.
	 * @param string $filepath File path.
	 */
	private function export_to_json( $data, $filepath ) {
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		file_put_contents( $filepath, $json );
	}

	/**
	 * Export to XML.
	 *
	 * @param array  $data     Data to export.
	 * @param string $filepath File path.
	 * @param string $type     Export type.
	 */
	private function export_to_xml( $data, $filepath, $type ) {
		$xml = new \SimpleXMLElement( '<?xml version="1.0" encoding="UTF-8"?><export/>' );
		$xml->addAttribute( 'type', $type );
		$xml->addAttribute( 'date', gmdate( 'Y-m-d H:i:s' ) );

		if ( isset( $data['bookings'] ) ) {
			// Nested data.
			foreach ( $data as $section => $items ) {
				$section_node = $xml->addChild( $section );
				foreach ( $items as $item ) {
					$item_node = $section_node->addChild( rtrim( $section, 's' ) );
					foreach ( $item as $key => $value ) {
						$item_node->addChild( $key, htmlspecialchars( (string) $value ) );
					}
				}
			}
		} else {
			// Single type.
			$items_node = $xml->addChild( $type );
			foreach ( $data as $item ) {
				$item_node = $items_node->addChild( 'item' );
				foreach ( $item as $key => $value ) {
					$item_node->addChild( $key, htmlspecialchars( (string) $value ) );
				}
			}
		}

		$xml->asXML( $filepath );
	}
}
