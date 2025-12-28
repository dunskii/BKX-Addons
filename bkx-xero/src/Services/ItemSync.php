<?php
/**
 * Xero Item Sync Service.
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
 * ItemSync Class.
 */
class ItemSync {

	/**
	 * OAuth service.
	 *
	 * @var OAuthService
	 */
	private $oauth;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->oauth = new OAuthService();
	}

	/**
	 * Sync BookingX service (base) to Xero.
	 *
	 * @param int $service_id BookingX service (base) ID.
	 * @return array|false Result or false.
	 */
	public function sync_service( $service_id ) {
		$service = get_post( $service_id );

		if ( ! $service || 'bkx_base' !== $service->post_type ) {
			$this->log_sync_error( $service_id, 'service', 'Invalid service' );
			return false;
		}

		$item_data = array(
			'id'          => $service_id,
			'name'        => $service->post_title,
			'description' => $service->post_excerpt ?: wp_trim_words( $service->post_content, 50 ),
			'price'       => floatval( get_post_meta( $service_id, 'base_price', true ) ),
			'code'        => 'BKX-SVC-' . $service_id,
		);

		return $this->create_or_update_item( $item_data, 'service' );
	}

	/**
	 * Sync BookingX extra (addition) to Xero.
	 *
	 * @param int $extra_id BookingX extra ID.
	 * @return array|false Result or false.
	 */
	public function sync_extra( $extra_id ) {
		$extra = get_post( $extra_id );

		if ( ! $extra || 'bkx_addition' !== $extra->post_type ) {
			$this->log_sync_error( $extra_id, 'extra', 'Invalid extra' );
			return false;
		}

		$item_data = array(
			'id'          => $extra_id,
			'name'        => $extra->post_title,
			'description' => $extra->post_excerpt ?: wp_trim_words( $extra->post_content, 50 ),
			'price'       => floatval( get_post_meta( $extra_id, 'extra_price', true ) ),
			'code'        => 'BKX-EXT-' . $extra_id,
		);

		return $this->create_or_update_item( $item_data, 'extra' );
	}

	/**
	 * Create or update item in Xero.
	 *
	 * @param array  $item_data Item data.
	 * @param string $type      Entity type (service|extra).
	 * @return array|false Result or false.
	 */
	public function create_or_update_item( $item_data, $type ) {
		$item_id = absint( $item_data['id'] );

		// Check if item already mapped.
		$existing_xero_code = $this->get_xero_item_code( $item_id, $type );

		$xero_item = $this->build_xero_item( $item_data );

		if ( $existing_xero_code ) {
			// Update existing item.
			$xero_item['Code'] = $existing_xero_code;
			$result = $this->update_xero_item( $xero_item );
		} else {
			// Check if item exists in Xero by code.
			$found = $this->find_xero_item_by_code( $item_data['code'] );

			if ( $found ) {
				// Map existing Xero item.
				$this->save_mapping( $item_id, $type, $found['Code'] );
				$result = $found;
			} else {
				// Create new item.
				$result = $this->create_xero_item( $xero_item );
			}
		}

		if ( $result && isset( $result['Code'] ) ) {
			$this->save_mapping( $item_id, $type, $result['Code'] );
			$this->log_sync_success( $item_id, $type, $result['Code'] );

			return array(
				'xero_id' => $result['Code'],
				'synced'  => true,
			);
		}

		return false;
	}

	/**
	 * Build Xero item object.
	 *
	 * @param array $data Item data.
	 * @return array Xero item object.
	 */
	private function build_xero_item( $data ) {
		$item = array(
			'Code'               => $this->sanitize_item_code( $data['code'] ),
			'Name'               => substr( $data['name'], 0, 50 ),
			'Description'        => $data['description'] ?? '',
			'IsSold'             => true,
			'IsPurchased'        => false,
			'SalesDetails'       => array(
				'UnitPrice'   => $data['price'],
				'AccountCode' => $this->get_revenue_account(),
			),
		);

		$tax_type = get_option( 'bkx_xero_tax_type' );
		if ( $tax_type ) {
			$item['SalesDetails']['TaxType'] = $tax_type;
		}

		return $item;
	}

	/**
	 * Sanitize item code for Xero.
	 *
	 * @param string $code Original code.
	 * @return string Sanitized code (max 30 chars, alphanumeric and dash).
	 */
	private function sanitize_item_code( $code ) {
		$code = preg_replace( '/[^A-Za-z0-9\-]/', '', $code );
		return substr( $code, 0, 30 );
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
	 * Create item in Xero.
	 *
	 * @param array $item Item data.
	 * @return array|false Response or false.
	 */
	private function create_xero_item( $item ) {
		$response = $this->oauth->api_request(
			'Items',
			'POST',
			array( 'Items' => array( $item ) )
		);

		if ( $response && isset( $response['Items'][0] ) ) {
			return $response['Items'][0];
		}

		return false;
	}

	/**
	 * Update item in Xero.
	 *
	 * @param array $item Item data with Code.
	 * @return array|false Response or false.
	 */
	private function update_xero_item( $item ) {
		$response = $this->oauth->api_request(
			'Items/' . $item['Code'],
			'POST',
			array( 'Items' => array( $item ) )
		);

		if ( $response && isset( $response['Items'][0] ) ) {
			return $response['Items'][0];
		}

		return false;
	}

	/**
	 * Get item from Xero.
	 *
	 * @param string $code Xero item code.
	 * @return array|false Item data or false.
	 */
	public function get_xero_item( $code ) {
		$response = $this->oauth->api_request( "Items/{$code}" );

		if ( $response && isset( $response['Items'][0] ) ) {
			return $response['Items'][0];
		}

		return false;
	}

	/**
	 * Find item in Xero by code.
	 *
	 * @param string $code Item code.
	 * @return array|false Item data or false.
	 */
	public function find_xero_item_by_code( $code ) {
		$response = $this->oauth->api_request(
			'Items?where=Code=="' . rawurlencode( $code ) . '"'
		);

		if ( $response && isset( $response['Items'][0] ) ) {
			return $response['Items'][0];
		}

		return false;
	}

	/**
	 * Sync all services to Xero.
	 *
	 * @return array Results.
	 */
	public function sync_all_services() {
		$results = array(
			'synced'  => 0,
			'failed'  => 0,
			'skipped' => 0,
		);

		// Sync base services.
		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		foreach ( $services as $service ) {
			$result = $this->sync_service( $service->ID );

			if ( $result && $result['synced'] ) {
				++$results['synced'];
			} elseif ( false === $result ) {
				++$results['failed'];
			} else {
				++$results['skipped'];
			}
		}

		// Sync extras.
		$extras = get_posts(
			array(
				'post_type'      => 'bkx_addition',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		foreach ( $extras as $extra ) {
			$result = $this->sync_extra( $extra->ID );

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
	 * Get Xero item code from mapping.
	 *
	 * @param int    $item_id BKX item ID.
	 * @param string $type    Entity type.
	 * @return string|false Xero item code or false.
	 */
	private function get_xero_item_code( $item_id, $type ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_mapping';

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT xero_id FROM %i WHERE entity_type = %s AND bkx_id = %d",
				$table,
				$type,
				$item_id
			)
		);
	}

	/**
	 * Save item mapping.
	 *
	 * @param int    $item_id BKX item ID.
	 * @param string $type    Entity type.
	 * @param string $xero_id Xero item code.
	 */
	private function save_mapping( $item_id, $type, $xero_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_mapping';

		$wpdb->replace(
			$table,
			array(
				'entity_type' => $type,
				'bkx_id'      => $item_id,
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
	 * @param string $type      Entity type.
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
			\BKX_Error_Logger::log( "Xero Item Sync Error ({$entity_id}): {$message}", 'error' );
		}
	}
}
