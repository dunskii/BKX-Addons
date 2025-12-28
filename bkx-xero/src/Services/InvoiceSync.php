<?php
/**
 * Xero Invoice Sync Service.
 *
 * @package BookingX\Xero\Services
 * @since   1.0.0
 */

namespace BookingX\Xero\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * InvoiceSync Class.
 */
class InvoiceSync {

	/**
	 * OAuth service.
	 *
	 * @var OAuthService
	 */
	private $oauth;

	/**
	 * Contact sync service.
	 *
	 * @var ContactSync
	 */
	private $contact_sync;

	/**
	 * Item sync service.
	 *
	 * @var ItemSync
	 */
	private $item_sync;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->oauth        = new OAuthService();
		$this->contact_sync = new ContactSync();
		$this->item_sync    = new ItemSync();
	}

	/**
	 * Sync booking to Xero as invoice.
	 *
	 * @param int $booking_id BookingX booking ID.
	 * @return array|false Result or false.
	 */
	public function sync_booking( $booking_id ) {
		$booking = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			$this->log_sync_error( $booking_id, 'invoice', 'Invalid booking' );
			return false;
		}

		// Get booking data.
		$booking_data = $this->get_booking_data( $booking_id );

		if ( ! $booking_data ) {
			return false;
		}

		// Ensure contact exists in Xero.
		$contact_result = $this->ensure_contact_synced( $booking_data );

		if ( ! $contact_result ) {
			$this->log_sync_error( $booking_id, 'invoice', 'Failed to sync contact' );
			return false;
		}

		// Ensure items exist in Xero.
		$this->ensure_items_synced( $booking_data );

		// Check if invoice already exists.
		$existing_xero_id = $this->get_xero_invoice_id( $booking_id );

		$invoice = $this->build_xero_invoice( $booking_data, $contact_result['xero_id'] );

		if ( $existing_xero_id ) {
			$invoice['InvoiceID'] = $existing_xero_id;
			$result = $this->update_xero_invoice( $invoice );
		} else {
			$result = $this->create_xero_invoice( $invoice );
		}

		if ( $result && isset( $result['InvoiceID'] ) ) {
			$this->save_mapping( $booking_id, $result['InvoiceID'] );
			$this->log_sync_success( $booking_id, 'invoice', $result['InvoiceID'] );

			// Store Xero invoice ID in booking meta.
			update_post_meta( $booking_id, '_bkx_xero_invoice_id', $result['InvoiceID'] );

			return array(
				'xero_id'    => $result['InvoiceID'],
				'invoice_no' => $result['InvoiceNumber'] ?? '',
				'synced'     => true,
			);
		}

		return false;
	}

	/**
	 * Get booking data.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array|false Booking data or false.
	 */
	private function get_booking_data( $booking_id ) {
		$data = array(
			'id'             => $booking_id,
			'customer_email' => get_post_meta( $booking_id, 'customer_email', true ),
			'customer_name'  => get_post_meta( $booking_id, 'customer_name', true ),
			'customer_phone' => get_post_meta( $booking_id, 'customer_phone', true ),
			'booking_date'   => get_post_meta( $booking_id, 'booking_date', true ),
			'booking_time'   => get_post_meta( $booking_id, 'booking_time', true ),
			'seat_id'        => get_post_meta( $booking_id, 'seat_id', true ),
			'base_id'        => get_post_meta( $booking_id, 'base_id', true ),
			'extra_ids'      => get_post_meta( $booking_id, 'extra_ids', true ),
			'total_amount'   => floatval( get_post_meta( $booking_id, 'total_amount', true ) ),
			'status'         => get_post_status( $booking_id ),
		);

		if ( empty( $data['customer_email'] ) ) {
			$this->log_sync_error( $booking_id, 'invoice', 'Missing customer email' );
			return false;
		}

		// Get service details.
		if ( $data['base_id'] ) {
			$base               = get_post( $data['base_id'] );
			$data['base_name']  = $base ? $base->post_title : 'Service';
			$data['base_price'] = floatval( get_post_meta( $data['base_id'], 'base_price', true ) );
		}

		// Get seat/staff details.
		if ( $data['seat_id'] ) {
			$seat              = get_post( $data['seat_id'] );
			$data['seat_name'] = $seat ? $seat->post_title : '';
		}

		// Get extras.
		$data['extras'] = array();
		if ( ! empty( $data['extra_ids'] ) ) {
			$extra_ids = is_array( $data['extra_ids'] ) ? $data['extra_ids'] : explode( ',', $data['extra_ids'] );
			foreach ( $extra_ids as $extra_id ) {
				$extra = get_post( absint( $extra_id ) );
				if ( $extra ) {
					$data['extras'][] = array(
						'id'    => $extra_id,
						'name'  => $extra->post_title,
						'price' => floatval( get_post_meta( $extra_id, 'extra_price', true ) ),
					);
				}
			}
		}

		return $data;
	}

	/**
	 * Ensure contact is synced to Xero.
	 *
	 * @param array $booking_data Booking data.
	 * @return array|false Contact sync result or false.
	 */
	private function ensure_contact_synced( $booking_data ) {
		$email = $booking_data['customer_email'];
		$user  = get_user_by( 'email', $email );

		if ( $user ) {
			return $this->contact_sync->sync_contact( $user->ID );
		}

		$name_parts = explode( ' ', $booking_data['customer_name'], 2 );
		$first_name = $name_parts[0] ?? '';
		$last_name  = $name_parts[1] ?? '';

		return $this->contact_sync->create_or_update_contact(
			array(
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'phone'      => $booking_data['customer_phone'] ?? '',
			)
		);
	}

	/**
	 * Ensure items are synced to Xero.
	 *
	 * @param array $booking_data Booking data.
	 */
	private function ensure_items_synced( $booking_data ) {
		// Sync main service.
		if ( $booking_data['base_id'] ) {
			$this->item_sync->sync_service( $booking_data['base_id'] );
		}

		// Sync extras.
		foreach ( $booking_data['extras'] as $extra ) {
			$this->item_sync->sync_extra( $extra['id'] );
		}
	}

	/**
	 * Build Xero invoice object.
	 *
	 * @param array  $booking_data Booking data.
	 * @param string $contact_id   Xero contact ID.
	 * @return array Xero invoice object.
	 */
	private function build_xero_invoice( $booking_data, $contact_id ) {
		$line_items = array();

		// Main service line.
		if ( ! empty( $booking_data['base_name'] ) ) {
			$item_code = $this->get_item_code( $booking_data['base_id'], 'service' );

			$description = $booking_data['base_name'];
			if ( ! empty( $booking_data['seat_name'] ) ) {
				$description .= ' with ' . $booking_data['seat_name'];
			}
			if ( ! empty( $booking_data['booking_date'] ) ) {
				$description .= ' on ' . $booking_data['booking_date'];
			}
			if ( ! empty( $booking_data['booking_time'] ) ) {
				$description .= ' at ' . $booking_data['booking_time'];
			}

			$line_item = array(
				'Description' => $description,
				'Quantity'    => 1,
				'UnitAmount'  => $booking_data['base_price'],
				'AccountCode' => $this->get_revenue_account(),
			);

			if ( $item_code ) {
				$line_item['ItemCode'] = $item_code;
			}

			$tax_type = get_option( 'bkx_xero_tax_type' );
			if ( $tax_type ) {
				$line_item['TaxType'] = $tax_type;
			}

			$line_items[] = $line_item;
		}

		// Extra line items.
		foreach ( $booking_data['extras'] as $extra ) {
			$item_code = $this->get_item_code( $extra['id'], 'extra' );

			$line_item = array(
				'Description' => $extra['name'],
				'Quantity'    => 1,
				'UnitAmount'  => $extra['price'],
				'AccountCode' => $this->get_revenue_account(),
			);

			if ( $item_code ) {
				$line_item['ItemCode'] = $item_code;
			}

			$tax_type = get_option( 'bkx_xero_tax_type' );
			if ( $tax_type ) {
				$line_item['TaxType'] = $tax_type;
			}

			$line_items[] = $line_item;
		}

		$invoice = array(
			'Type'            => 'ACCREC',
			'Contact'         => array( 'ContactID' => $contact_id ),
			'LineItems'       => $line_items,
			'Date'            => $booking_data['booking_date'] ?: gmdate( 'Y-m-d' ),
			'DueDate'         => $booking_data['booking_date'] ?: gmdate( 'Y-m-d' ),
			'Reference'       => 'BKX-' . $booking_data['id'],
			'Status'          => 'AUTHORISED',
			'LineAmountTypes' => 'Exclusive',
		);

		// Add branding theme if set.
		$branding_theme = get_option( 'bkx_xero_branding_theme' );
		if ( $branding_theme ) {
			$invoice['BrandingThemeID'] = $branding_theme;
		}

		return $invoice;
	}

	/**
	 * Get item code from mapping.
	 *
	 * @param int    $item_id Item ID.
	 * @param string $type    Item type.
	 * @return string|null Item code or null.
	 */
	private function get_item_code( $item_id, $type ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_mapping';

		$xero_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT xero_id FROM %i WHERE entity_type = %s AND bkx_id = %d",
				$table,
				$type,
				$item_id
			)
		);

		return $xero_id;
	}

	/**
	 * Get revenue account code.
	 *
	 * @return string Account code.
	 */
	private function get_revenue_account() {
		$account = get_option( 'bkx_xero_revenue_account' );

		if ( $account ) {
			return $account;
		}

		return '200'; // Default sales account.
	}

	/**
	 * Create invoice in Xero.
	 *
	 * @param array $invoice Invoice data.
	 * @return array|false Response or false.
	 */
	private function create_xero_invoice( $invoice ) {
		$response = $this->oauth->api_request(
			'Invoices',
			'POST',
			array( 'Invoices' => array( $invoice ) )
		);

		if ( $response && isset( $response['Invoices'][0] ) ) {
			return $response['Invoices'][0];
		}

		return false;
	}

	/**
	 * Update invoice in Xero.
	 *
	 * @param array $invoice Invoice data with InvoiceID.
	 * @return array|false Response or false.
	 */
	private function update_xero_invoice( $invoice ) {
		$response = $this->oauth->api_request(
			'Invoices/' . $invoice['InvoiceID'],
			'POST',
			array( 'Invoices' => array( $invoice ) )
		);

		if ( $response && isset( $response['Invoices'][0] ) ) {
			return $response['Invoices'][0];
		}

		return false;
	}

	/**
	 * Void invoice in Xero.
	 *
	 * @param int $booking_id BookingX booking ID.
	 * @return bool Success.
	 */
	public function void_invoice( $booking_id ) {
		$xero_id = $this->get_xero_invoice_id( $booking_id );

		if ( ! $xero_id ) {
			return false;
		}

		$invoice = array(
			'InvoiceID' => $xero_id,
			'Status'    => 'VOIDED',
		);

		$response = $this->oauth->api_request(
			'Invoices/' . $xero_id,
			'POST',
			array( 'Invoices' => array( $invoice ) )
		);

		if ( $response && isset( $response['Invoices'][0] ) ) {
			$this->log_sync_success( $booking_id, 'invoice_void', $xero_id );
			return true;
		}

		return false;
	}

	/**
	 * Sync all bookings to Xero.
	 *
	 * @return array Results.
	 */
	public function sync_all_bookings() {
		$results = array(
			'synced'  => 0,
			'failed'  => 0,
			'skipped' => 0,
		);

		$bookings = get_posts(
			array(
				'post_type'      => 'bkx_booking',
				'post_status'    => array( 'bkx-pending', 'bkx-ack', 'bkx-completed' ),
				'posts_per_page' => 100,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		foreach ( $bookings as $booking ) {
			$result = $this->sync_booking( $booking->ID );

			if ( $result && $result['synced'] ) {
				++$results['synced'];
			} elseif ( false === $result ) {
				++$results['failed'];
			} else {
				++$results['skipped'];
			}
		}

		return $results;
	}

	/**
	 * Get Xero invoice ID from mapping.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|false Xero invoice ID or false.
	 */
	private function get_xero_invoice_id( $booking_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_mapping';

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT xero_id FROM %i WHERE entity_type = 'invoice' AND bkx_id = %d",
				$table,
				$booking_id
			)
		);
	}

	/**
	 * Save invoice mapping.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $xero_id    Xero invoice ID.
	 */
	private function save_mapping( $booking_id, $xero_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_mapping';

		$wpdb->replace(
			$table,
			array(
				'entity_type' => 'invoice',
				'bkx_id'      => $booking_id,
				'xero_id'     => $xero_id,
				'last_synced' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Log sync success.
	 *
	 * @param int    $entity_id Entity ID.
	 * @param string $type      Sync type.
	 * @param string $xero_id   Xero ID.
	 */
	private function log_sync_success( $entity_id, $type, $xero_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_sync_log';

		$wpdb->insert(
			$table,
			array(
				'entity_type' => $type,
				'entity_id'   => $entity_id,
				'xero_id'     => $xero_id,
				'sync_type'   => 'create_or_update',
				'sync_status' => 'success',
				'synced_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Log sync error.
	 *
	 * @param int    $entity_id Entity ID.
	 * @param string $type      Entity type.
	 * @param string $message   Error message.
	 */
	private function log_sync_error( $entity_id, $type, $message ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_sync_log';

		$wpdb->insert(
			$table,
			array(
				'entity_type'   => $type,
				'entity_id'     => $entity_id,
				'sync_type'     => 'create_or_update',
				'sync_status'   => 'failed',
				'error_message' => $message,
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);

		if ( class_exists( 'BKX_Error_Logger' ) ) {
			\BKX_Error_Logger::log( "Xero Invoice Sync Error ({$entity_id}): {$message}", 'error' );
		}
	}
}
