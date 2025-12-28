<?php
/**
 * QuickBooks Invoice Sync Service.
 *
 * @package BookingX\QuickBooks\Services
 * @since   1.0.0
 */

namespace BookingX\QuickBooks\Services;

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
	 * Customer sync service.
	 *
	 * @var CustomerSync
	 */
	private $customer_sync;

	/**
	 * Product sync service.
	 *
	 * @var ProductSync
	 */
	private $product_sync;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->oauth         = new OAuthService();
		$this->customer_sync = new CustomerSync();
		$this->product_sync  = new ProductSync();
	}

	/**
	 * Sync booking to QuickBooks as invoice.
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

		// Ensure customer exists in QB.
		$customer_result = $this->ensure_customer_synced( $booking_data );

		if ( ! $customer_result ) {
			$this->log_sync_error( $booking_id, 'invoice', 'Failed to sync customer' );
			return false;
		}

		// Ensure services exist in QB.
		$this->ensure_services_synced( $booking_data );

		// Check if invoice already exists.
		$existing_qb_id = $this->get_qb_invoice_id( $booking_id );

		$invoice = $this->build_qb_invoice( $booking_data, $customer_result['qb_id'] );

		if ( $existing_qb_id ) {
			$result = $this->update_qb_invoice( $existing_qb_id, $invoice );
		} else {
			$result = $this->create_qb_invoice( $invoice );
		}

		if ( $result && isset( $result['Id'] ) ) {
			$this->save_mapping( $booking_id, $result['Id'], $result['SyncToken'] ?? null );
			$this->log_sync_success( $booking_id, 'invoice', $result['Id'] );

			// Store QB invoice ID in booking meta.
			update_post_meta( $booking_id, '_bkx_qb_invoice_id', $result['Id'] );

			return array(
				'qb_id'   => $result['Id'],
				'doc_num' => $result['DocNumber'] ?? '',
				'synced'  => true,
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
			$base              = get_post( $data['base_id'] );
			$data['base_name'] = $base ? $base->post_title : 'Service';
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
	 * Ensure customer is synced to QuickBooks.
	 *
	 * @param array $booking_data Booking data.
	 * @return array|false Customer sync result or false.
	 */
	private function ensure_customer_synced( $booking_data ) {
		$email = $booking_data['customer_email'];
		$user  = get_user_by( 'email', $email );

		if ( $user ) {
			return $this->customer_sync->sync_customer( $user->ID );
		}

		// Parse customer name.
		$name_parts  = explode( ' ', $booking_data['customer_name'], 2 );
		$first_name  = $name_parts[0] ?? '';
		$last_name   = $name_parts[1] ?? '';

		return $this->customer_sync->create_or_update_customer(
			array(
				'email'      => $email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'phone'      => $booking_data['customer_phone'] ?? '',
			)
		);
	}

	/**
	 * Ensure services are synced to QuickBooks.
	 *
	 * @param array $booking_data Booking data.
	 */
	private function ensure_services_synced( $booking_data ) {
		// Sync main service.
		if ( $booking_data['base_id'] ) {
			$this->product_sync->sync_service( $booking_data['base_id'] );
		}

		// Sync extras.
		foreach ( $booking_data['extras'] as $extra ) {
			$this->product_sync->sync_extra( $extra['id'] );
		}
	}

	/**
	 * Build QuickBooks invoice object.
	 *
	 * @param array  $booking_data Booking data.
	 * @param string $customer_id  QB customer ID.
	 * @return array QB invoice object.
	 */
	private function build_qb_invoice( $booking_data, $customer_id ) {
		$line_items = array();

		// Main service line.
		if ( ! empty( $booking_data['base_name'] ) ) {
			$service_item_id = $this->get_or_create_service_item( $booking_data['base_id'], $booking_data['base_name'] );

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

			$line_items[] = array(
				'DetailType'         => 'SalesItemLineDetail',
				'Amount'             => $booking_data['base_price'],
				'Description'        => $description,
				'SalesItemLineDetail' => array(
					'ItemRef' => array(
						'value' => $service_item_id,
					),
					'Qty'     => 1,
				),
			);
		}

		// Extra line items.
		foreach ( $booking_data['extras'] as $extra ) {
			$extra_item_id = $this->get_or_create_extra_item( $extra['id'], $extra['name'] );

			$line_items[] = array(
				'DetailType'         => 'SalesItemLineDetail',
				'Amount'             => $extra['price'],
				'Description'        => $extra['name'],
				'SalesItemLineDetail' => array(
					'ItemRef' => array(
						'value' => $extra_item_id,
					),
					'Qty'     => 1,
				),
			);
		}

		$invoice = array(
			'CustomerRef'      => array( 'value' => $customer_id ),
			'Line'             => $line_items,
			'DocNumber'        => 'BKX-' . $booking_data['id'],
			'TxnDate'          => $booking_data['booking_date'] ?: gmdate( 'Y-m-d' ),
			'DueDate'          => $booking_data['booking_date'] ?: gmdate( 'Y-m-d' ),
			'PrivateNote'      => sprintf(
				'BookingX Booking #%d - Status: %s',
				$booking_data['id'],
				$booking_data['status']
			),
			'CustomerMemo'     => array(
				'value' => sprintf(
					'Booking for %s on %s',
					$booking_data['base_name'] ?? 'Service',
					$booking_data['booking_date'] ?? 'N/A'
				),
			),
		);

		// Apply default tax code if set.
		$tax_code = get_option( 'bkx_qb_default_tax_code' );
		if ( $tax_code ) {
			$invoice['GlobalTaxCalculation'] = 'TaxExcluded';
		}

		return $invoice;
	}

	/**
	 * Get or create service item in QuickBooks.
	 *
	 * @param int    $service_id   BookingX service ID.
	 * @param string $service_name Service name.
	 * @return string QB item ID.
	 */
	private function get_or_create_service_item( $service_id, $service_name ) {
		// Check mapping first.
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_mapping';

		$qb_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT qb_id FROM %i WHERE entity_type = 'service' AND bkx_id = %d",
				$table,
				$service_id
			)
		);

		if ( $qb_id ) {
			return $qb_id;
		}

		// Sync service.
		$result = $this->product_sync->sync_service( $service_id );

		if ( $result && isset( $result['qb_id'] ) ) {
			return $result['qb_id'];
		}

		// Fallback to default income account item.
		return $this->get_default_item_id();
	}

	/**
	 * Get or create extra item in QuickBooks.
	 *
	 * @param int    $extra_id   BookingX extra ID.
	 * @param string $extra_name Extra name.
	 * @return string QB item ID.
	 */
	private function get_or_create_extra_item( $extra_id, $extra_name ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_mapping';

		$qb_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT qb_id FROM %i WHERE entity_type = 'extra' AND bkx_id = %d",
				$table,
				$extra_id
			)
		);

		if ( $qb_id ) {
			return $qb_id;
		}

		$result = $this->product_sync->sync_extra( $extra_id );

		if ( $result && isset( $result['qb_id'] ) ) {
			return $result['qb_id'];
		}

		return $this->get_default_item_id();
	}

	/**
	 * Get default item ID from settings or create one.
	 *
	 * @return string Default item ID.
	 */
	private function get_default_item_id() {
		$default_item = get_option( 'bkx_qb_default_item_id' );

		if ( $default_item ) {
			return $default_item;
		}

		// Create a default service item.
		$income_account = $this->get_default_income_account();

		$item = array(
			'Name'           => 'BookingX Service',
			'Type'           => 'Service',
			'IncomeAccountRef' => array( 'value' => $income_account ),
			'Description'    => 'Booking service from BookingX',
		);

		$response = $this->oauth->api_request( 'item', 'POST', $item );

		if ( $response && isset( $response['Item']['Id'] ) ) {
			update_option( 'bkx_qb_default_item_id', $response['Item']['Id'] );
			return $response['Item']['Id'];
		}

		return '1'; // Fallback.
	}

	/**
	 * Get default income account.
	 *
	 * @return string Account ID.
	 */
	private function get_default_income_account() {
		$account = get_option( 'bkx_qb_default_income_account' );

		if ( $account ) {
			return $account;
		}

		// Query for income accounts.
		$query    = "SELECT * FROM Account WHERE AccountType = 'Income' MAXRESULTS 1";
		$response = $this->oauth->api_request( 'query?query=' . rawurlencode( $query ) );

		if ( $response && isset( $response['QueryResponse']['Account'][0]['Id'] ) ) {
			$account_id = $response['QueryResponse']['Account'][0]['Id'];
			update_option( 'bkx_qb_default_income_account', $account_id );
			return $account_id;
		}

		return '1';
	}

	/**
	 * Create invoice in QuickBooks.
	 *
	 * @param array $invoice Invoice data.
	 * @return array|false Response or false.
	 */
	private function create_qb_invoice( $invoice ) {
		$response = $this->oauth->api_request( 'invoice', 'POST', $invoice );

		if ( $response && isset( $response['Invoice'] ) ) {
			return $response['Invoice'];
		}

		return false;
	}

	/**
	 * Update invoice in QuickBooks.
	 *
	 * @param string $qb_id   QuickBooks invoice ID.
	 * @param array  $invoice Invoice data.
	 * @return array|false Response or false.
	 */
	private function update_qb_invoice( $qb_id, $invoice ) {
		$current = $this->get_qb_invoice( $qb_id );

		if ( ! $current ) {
			return false;
		}

		$invoice['Id']        = $qb_id;
		$invoice['SyncToken'] = $current['SyncToken'];
		$invoice['sparse']    = true;

		$response = $this->oauth->api_request( 'invoice', 'POST', $invoice );

		if ( $response && isset( $response['Invoice'] ) ) {
			return $response['Invoice'];
		}

		return false;
	}

	/**
	 * Get invoice from QuickBooks.
	 *
	 * @param string $qb_id QuickBooks invoice ID.
	 * @return array|false Invoice data or false.
	 */
	public function get_qb_invoice( $qb_id ) {
		$response = $this->oauth->api_request( "invoice/{$qb_id}" );

		if ( $response && isset( $response['Invoice'] ) ) {
			return $response['Invoice'];
		}

		return false;
	}

	/**
	 * Void invoice in QuickBooks.
	 *
	 * @param int $booking_id BookingX booking ID.
	 * @return bool Success.
	 */
	public function void_invoice( $booking_id ) {
		$qb_id = $this->get_qb_invoice_id( $booking_id );

		if ( ! $qb_id ) {
			return false;
		}

		$current = $this->get_qb_invoice( $qb_id );

		if ( ! $current ) {
			return false;
		}

		$void_data = array(
			'Id'        => $qb_id,
			'SyncToken' => $current['SyncToken'],
		);

		$response = $this->oauth->api_request( 'invoice?operation=void', 'POST', $void_data );

		if ( $response && isset( $response['Invoice'] ) ) {
			$this->log_sync_success( $booking_id, 'invoice_void', $qb_id );
			return true;
		}

		return false;
	}

	/**
	 * Sync all bookings to QuickBooks.
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
	 * Get QB invoice ID from mapping.
	 *
	 * @param int $booking_id Booking ID.
	 * @return string|false QB invoice ID or false.
	 */
	private function get_qb_invoice_id( $booking_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_mapping';

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT qb_id FROM %i WHERE entity_type = 'invoice' AND bkx_id = %d",
				$table,
				$booking_id
			)
		);
	}

	/**
	 * Save invoice mapping.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $qb_id      QuickBooks invoice ID.
	 * @param string $sync_token QB sync token.
	 */
	private function save_mapping( $booking_id, $qb_id, $sync_token = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_mapping';

		$wpdb->replace(
			$table,
			array(
				'entity_type'   => 'invoice',
				'bkx_id'        => $booking_id,
				'qb_id'         => $qb_id,
				'qb_sync_token' => $sync_token,
				'last_synced'   => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Log sync success.
	 *
	 * @param int    $entity_id Entity ID.
	 * @param string $type      Sync type.
	 * @param string $qb_id     QuickBooks ID.
	 */
	private function log_sync_success( $entity_id, $type, $qb_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_sync_log';

		$wpdb->insert(
			$table,
			array(
				'entity_type' => $type,
				'entity_id'   => $entity_id,
				'qb_id'       => $qb_id,
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
		$table = $wpdb->prefix . 'bkx_qb_sync_log';

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
			\BKX_Error_Logger::log( "QB Invoice Sync Error ({$entity_id}): {$message}", 'error' );
		}
	}
}
