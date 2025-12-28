<?php
/**
 * QuickBooks Product/Service Sync Service.
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
 * ProductSync Class.
 */
class ProductSync {

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
	 * Sync BookingX service (base) to QuickBooks.
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

		$service_data = array(
			'id'          => $service_id,
			'name'        => $service->post_title,
			'description' => $service->post_excerpt ?: wp_trim_words( $service->post_content, 50 ),
			'price'       => floatval( get_post_meta( $service_id, 'base_price', true ) ),
			'sku'         => 'BKX-SVC-' . $service_id,
		);

		return $this->create_or_update_item( $service_data, 'service' );
	}

	/**
	 * Sync BookingX extra (addition) to QuickBooks.
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

		$extra_data = array(
			'id'          => $extra_id,
			'name'        => $extra->post_title,
			'description' => $extra->post_excerpt ?: wp_trim_words( $extra->post_content, 50 ),
			'price'       => floatval( get_post_meta( $extra_id, 'extra_price', true ) ),
			'sku'         => 'BKX-EXT-' . $extra_id,
		);

		return $this->create_or_update_item( $extra_data, 'extra' );
	}

	/**
	 * Create or update item in QuickBooks.
	 *
	 * @param array  $item_data Item data.
	 * @param string $type      Entity type (service|extra).
	 * @return array|false Result or false.
	 */
	public function create_or_update_item( $item_data, $type ) {
		$item_id = absint( $item_data['id'] );

		// Check if item already mapped.
		$existing_qb_id = $this->get_qb_item_id( $item_id, $type );

		$qb_item = $this->build_qb_item( $item_data );

		if ( $existing_qb_id ) {
			// Update existing item.
			$result = $this->update_qb_item( $existing_qb_id, $qb_item );
		} else {
			// Check if item exists in QB by SKU.
			$found = $this->find_qb_item_by_sku( $item_data['sku'] );

			if ( $found ) {
				// Map existing QB item.
				$this->save_mapping( $item_id, $type, $found['Id'], $found['SyncToken'] ?? null );
				$result = $found;
			} else {
				// Create new item.
				$result = $this->create_qb_item( $qb_item );
			}
		}

		if ( $result && isset( $result['Id'] ) ) {
			$this->save_mapping( $item_id, $type, $result['Id'], $result['SyncToken'] ?? null );
			$this->log_sync_success( $item_id, $type, $result['Id'] );

			return array(
				'qb_id'  => $result['Id'],
				'synced' => true,
			);
		}

		return false;
	}

	/**
	 * Build QuickBooks item object.
	 *
	 * @param array $data Item data.
	 * @return array QB item object.
	 */
	private function build_qb_item( $data ) {
		$income_account = $this->get_default_income_account();

		$item = array(
			'Name'             => $this->sanitize_item_name( $data['name'] ),
			'Type'             => 'Service',
			'Sku'              => $data['sku'],
			'Description'      => $data['description'] ?? '',
			'UnitPrice'        => $data['price'],
			'IncomeAccountRef' => array( 'value' => $income_account ),
			'Active'           => true,
			'Taxable'          => true,
		);

		return $item;
	}

	/**
	 * Sanitize item name for QuickBooks.
	 *
	 * @param string $name Original name.
	 * @return string Sanitized name (max 100 chars, no colons).
	 */
	private function sanitize_item_name( $name ) {
		// Remove colons (reserved in QB).
		$name = str_replace( ':', '-', $name );

		// Truncate to 100 chars.
		if ( strlen( $name ) > 100 ) {
			$name = substr( $name, 0, 97 ) . '...';
		}

		return $name;
	}

	/**
	 * Create item in QuickBooks.
	 *
	 * @param array $item Item data.
	 * @return array|false Response or false.
	 */
	private function create_qb_item( $item ) {
		$response = $this->oauth->api_request( 'item', 'POST', $item );

		if ( $response && isset( $response['Item'] ) ) {
			return $response['Item'];
		}

		return false;
	}

	/**
	 * Update item in QuickBooks.
	 *
	 * @param string $qb_id QB item ID.
	 * @param array  $item  Item data.
	 * @return array|false Response or false.
	 */
	private function update_qb_item( $qb_id, $item ) {
		$current = $this->get_qb_item( $qb_id );

		if ( ! $current ) {
			return false;
		}

		$item['Id']        = $qb_id;
		$item['SyncToken'] = $current['SyncToken'];
		$item['sparse']    = true;

		$response = $this->oauth->api_request( 'item', 'POST', $item );

		if ( $response && isset( $response['Item'] ) ) {
			return $response['Item'];
		}

		return false;
	}

	/**
	 * Get item from QuickBooks.
	 *
	 * @param string $qb_id QB item ID.
	 * @return array|false Item data or false.
	 */
	public function get_qb_item( $qb_id ) {
		$response = $this->oauth->api_request( "item/{$qb_id}" );

		if ( $response && isset( $response['Item'] ) ) {
			return $response['Item'];
		}

		return false;
	}

	/**
	 * Find item in QuickBooks by SKU.
	 *
	 * @param string $sku Item SKU.
	 * @return array|false Item data or false.
	 */
	public function find_qb_item_by_sku( $sku ) {
		$query    = "SELECT * FROM Item WHERE Sku = '{$sku}'";
		$response = $this->oauth->api_request( 'query?query=' . rawurlencode( $query ) );

		if ( $response && isset( $response['QueryResponse']['Item'][0] ) ) {
			return $response['QueryResponse']['Item'][0];
		}

		return false;
	}

	/**
	 * Sync all services to QuickBooks.
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
	 * Get all items from QuickBooks.
	 *
	 * @param int $start_position Start position for pagination.
	 * @param int $max_results    Max results per page.
	 * @return array Items.
	 */
	public function get_all_qb_items( $start_position = 1, $max_results = 100 ) {
		$query = sprintf(
			'SELECT * FROM Item STARTPOSITION %d MAXRESULTS %d',
			$start_position,
			$max_results
		);

		$response = $this->oauth->api_request( 'query?query=' . rawurlencode( $query ) );

		if ( $response && isset( $response['QueryResponse']['Item'] ) ) {
			return $response['QueryResponse']['Item'];
		}

		return array();
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
	 * Get QB item ID from mapping.
	 *
	 * @param int    $item_id BKX item ID.
	 * @param string $type    Entity type.
	 * @return string|false QB item ID or false.
	 */
	private function get_qb_item_id( $item_id, $type ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_mapping';

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT qb_id FROM %i WHERE entity_type = %s AND bkx_id = %d",
				$table,
				$type,
				$item_id
			)
		);
	}

	/**
	 * Save item mapping.
	 *
	 * @param int    $item_id    BKX item ID.
	 * @param string $type       Entity type.
	 * @param string $qb_id      QuickBooks item ID.
	 * @param string $sync_token QB sync token.
	 */
	private function save_mapping( $item_id, $type, $qb_id, $sync_token = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_mapping';

		$wpdb->replace(
			$table,
			array(
				'entity_type'   => $type,
				'bkx_id'        => $item_id,
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
	 * @param string $type      Entity type.
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
			\BKX_Error_Logger::log( "QB Product Sync Error ({$entity_id}): {$message}", 'error' );
		}
	}
}
