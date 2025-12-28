<?php
/**
 * QuickBooks Customer Sync Service.
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
 * CustomerSync Class.
 */
class CustomerSync {

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
	 * Sync customer by email.
	 *
	 * @param string $email Customer email.
	 * @return array|false Sync result or false.
	 */
	public function sync_customer_by_email( $email ) {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			// Create customer from email only.
			return $this->create_or_update_customer(
				array(
					'email'      => $email,
					'first_name' => '',
					'last_name'  => '',
				)
			);
		}

		return $this->sync_customer( $user->ID );
	}

	/**
	 * Sync WordPress user to QuickBooks customer.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array|false Sync result or false.
	 */
	public function sync_customer( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			$this->log_sync_error( $user_id, 'customer', 'User not found' );
			return false;
		}

		$customer_data = array(
			'user_id'    => $user_id,
			'email'      => $user->user_email,
			'first_name' => $user->first_name,
			'last_name'  => $user->last_name,
			'phone'      => get_user_meta( $user_id, 'billing_phone', true ),
			'address'    => array(
				'line1'   => get_user_meta( $user_id, 'billing_address_1', true ),
				'line2'   => get_user_meta( $user_id, 'billing_address_2', true ),
				'city'    => get_user_meta( $user_id, 'billing_city', true ),
				'state'   => get_user_meta( $user_id, 'billing_state', true ),
				'zip'     => get_user_meta( $user_id, 'billing_postcode', true ),
				'country' => get_user_meta( $user_id, 'billing_country', true ),
			),
		);

		return $this->create_or_update_customer( $customer_data );
	}

	/**
	 * Create or update customer in QuickBooks.
	 *
	 * @param array $customer_data Customer data.
	 * @return array|false Result or false.
	 */
	public function create_or_update_customer( $customer_data ) {
		$user_id = isset( $customer_data['user_id'] ) ? absint( $customer_data['user_id'] ) : 0;
		$email   = sanitize_email( $customer_data['email'] );

		if ( empty( $email ) ) {
			$this->log_sync_error( $user_id, 'customer', 'Missing email' );
			return false;
		}

		// Check if customer already mapped.
		$existing_qb_id = $this->get_qb_customer_id( $user_id, $email );

		$qb_customer = $this->build_qb_customer( $customer_data );

		if ( $existing_qb_id ) {
			// Update existing customer.
			$result = $this->update_qb_customer( $existing_qb_id, $qb_customer );
		} else {
			// Check if customer exists in QB by email.
			$found = $this->find_qb_customer_by_email( $email );

			if ( $found ) {
				// Map existing QB customer.
				$this->save_mapping( $user_id ?: $email, $found['Id'], $found['SyncToken'] ?? null );
				$result = $found;
			} else {
				// Create new customer.
				$result = $this->create_qb_customer( $qb_customer );
			}
		}

		if ( $result && isset( $result['Id'] ) ) {
			$this->save_mapping( $user_id ?: $email, $result['Id'], $result['SyncToken'] ?? null );
			$this->log_sync_success( $user_id ?: $email, 'customer', $result['Id'] );

			return array(
				'qb_id'   => $result['Id'],
				'synced'  => true,
				'created' => ! $existing_qb_id && ! isset( $found ),
			);
		}

		return false;
	}

	/**
	 * Build QuickBooks customer object.
	 *
	 * @param array $data Customer data.
	 * @return array QB customer object.
	 */
	private function build_qb_customer( $data ) {
		$display_name = trim( $data['first_name'] . ' ' . $data['last_name'] );

		if ( empty( $display_name ) ) {
			$display_name = $data['email'];
		}

		// QB requires unique DisplayName.
		$display_name = $this->ensure_unique_display_name( $display_name );

		$customer = array(
			'DisplayName'       => $display_name,
			'PrimaryEmailAddr'  => array( 'Address' => $data['email'] ),
			'GivenName'         => $data['first_name'] ?: null,
			'FamilyName'        => $data['last_name'] ?: null,
			'CompanyName'       => null,
			'Active'            => true,
		);

		// Add phone if available.
		if ( ! empty( $data['phone'] ) ) {
			$customer['PrimaryPhone'] = array( 'FreeFormNumber' => $data['phone'] );
		}

		// Add address if available.
		if ( ! empty( $data['address']['line1'] ) ) {
			$customer['BillAddr'] = array(
				'Line1'                  => $data['address']['line1'],
				'Line2'                  => $data['address']['line2'] ?? null,
				'City'                   => $data['address']['city'] ?? null,
				'CountrySubDivisionCode' => $data['address']['state'] ?? null,
				'PostalCode'             => $data['address']['zip'] ?? null,
				'Country'                => $data['address']['country'] ?? null,
			);
		}

		// Remove null values.
		return array_filter( $customer, function( $v ) {
			return null !== $v;
		});
	}

	/**
	 * Create customer in QuickBooks.
	 *
	 * @param array $customer Customer data.
	 * @return array|false Response or false.
	 */
	private function create_qb_customer( $customer ) {
		$response = $this->oauth->api_request( 'customer', 'POST', $customer );

		if ( $response && isset( $response['Customer'] ) ) {
			return $response['Customer'];
		}

		return false;
	}

	/**
	 * Update customer in QuickBooks.
	 *
	 * @param string $qb_id    QuickBooks customer ID.
	 * @param array  $customer Customer data.
	 * @return array|false Response or false.
	 */
	private function update_qb_customer( $qb_id, $customer ) {
		// Get current customer to get SyncToken.
		$current = $this->get_qb_customer( $qb_id );

		if ( ! $current ) {
			return false;
		}

		$customer['Id']        = $qb_id;
		$customer['SyncToken'] = $current['SyncToken'];
		$customer['sparse']    = true;

		$response = $this->oauth->api_request( 'customer', 'POST', $customer );

		if ( $response && isset( $response['Customer'] ) ) {
			return $response['Customer'];
		}

		return false;
	}

	/**
	 * Get customer from QuickBooks.
	 *
	 * @param string $qb_id QuickBooks customer ID.
	 * @return array|false Customer data or false.
	 */
	public function get_qb_customer( $qb_id ) {
		$response = $this->oauth->api_request( "customer/{$qb_id}" );

		if ( $response && isset( $response['Customer'] ) ) {
			return $response['Customer'];
		}

		return false;
	}

	/**
	 * Find customer in QuickBooks by email.
	 *
	 * @param string $email Email address.
	 * @return array|false Customer data or false.
	 */
	public function find_qb_customer_by_email( $email ) {
		$query    = "SELECT * FROM Customer WHERE PrimaryEmailAddr = '{$email}'";
		$response = $this->oauth->api_request( 'query?query=' . rawurlencode( $query ) );

		if ( $response && isset( $response['QueryResponse']['Customer'][0] ) ) {
			return $response['QueryResponse']['Customer'][0];
		}

		return false;
	}

	/**
	 * Sync all WordPress users to QuickBooks.
	 *
	 * @return array Sync results.
	 */
	public function sync_all_customers() {
		$results = array(
			'synced'  => 0,
			'failed'  => 0,
			'skipped' => 0,
		);

		// Get users who have made bookings.
		global $wpdb;

		$emails = $wpdb->get_col(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
			 WHERE meta_key = 'customer_email'
			 AND meta_value != ''"
		);

		foreach ( $emails as $email ) {
			$user = get_user_by( 'email', $email );

			if ( $user ) {
				$result = $this->sync_customer( $user->ID );
			} else {
				$result = $this->sync_customer_by_email( $email );
			}

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
	 * Get QB customer ID from mapping.
	 *
	 * @param int|string $identifier User ID or email.
	 * @param string     $email      Email for fallback lookup.
	 * @return string|false QB customer ID or false.
	 */
	private function get_qb_customer_id( $identifier, $email = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_mapping';

		// Try by user ID first.
		if ( is_numeric( $identifier ) && $identifier > 0 ) {
			$qb_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT qb_id FROM %i WHERE entity_type = 'customer' AND bkx_id = %d",
					$table,
					$identifier
				)
			);

			if ( $qb_id ) {
				return $qb_id;
			}
		}

		// Try by email hash.
		if ( $email ) {
			$email_hash = md5( strtolower( $email ) );
			$qb_id      = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT qb_id FROM %i WHERE entity_type = 'customer_email' AND bkx_id = %s",
					$table,
					$email_hash
				)
			);

			if ( $qb_id ) {
				return $qb_id;
			}
		}

		return false;
	}

	/**
	 * Save customer mapping.
	 *
	 * @param int|string $identifier User ID or email.
	 * @param string     $qb_id      QuickBooks customer ID.
	 * @param string     $sync_token QB sync token.
	 */
	private function save_mapping( $identifier, $qb_id, $sync_token = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_mapping';

		$entity_type = is_numeric( $identifier ) ? 'customer' : 'customer_email';
		$bkx_id      = is_numeric( $identifier ) ? $identifier : md5( strtolower( $identifier ) );

		$wpdb->replace(
			$table,
			array(
				'entity_type'   => $entity_type,
				'bkx_id'        => $bkx_id,
				'qb_id'         => $qb_id,
				'qb_sync_token' => $sync_token,
				'last_synced'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Ensure unique display name.
	 *
	 * @param string $display_name Original display name.
	 * @return string Unique display name.
	 */
	private function ensure_unique_display_name( $display_name ) {
		$original = $display_name;
		$counter  = 1;

		while ( $this->display_name_exists( $display_name ) ) {
			$display_name = $original . ' (' . $counter . ')';
			++$counter;

			if ( $counter > 100 ) {
				// Failsafe.
				$display_name = $original . ' ' . wp_generate_password( 4, false );
				break;
			}
		}

		return $display_name;
	}

	/**
	 * Check if display name exists in QuickBooks.
	 *
	 * @param string $display_name Display name to check.
	 * @return bool True if exists.
	 */
	private function display_name_exists( $display_name ) {
		$query    = "SELECT COUNT(*) FROM Customer WHERE DisplayName = '{$display_name}'";
		$response = $this->oauth->api_request( 'query?query=' . rawurlencode( $query ) );

		if ( $response && isset( $response['QueryResponse']['totalCount'] ) ) {
			return $response['QueryResponse']['totalCount'] > 0;
		}

		return false;
	}

	/**
	 * Log sync success.
	 *
	 * @param int|string $entity_id Entity ID.
	 * @param string     $type      Entity type.
	 * @param string     $qb_id     QuickBooks ID.
	 */
	private function log_sync_success( $entity_id, $type, $qb_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_sync_log';

		$wpdb->insert(
			$table,
			array(
				'entity_type' => $type,
				'entity_id'   => is_numeric( $entity_id ) ? $entity_id : 0,
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
	 * @param int|string $entity_id Entity ID.
	 * @param string     $type      Entity type.
	 * @param string     $message   Error message.
	 */
	private function log_sync_error( $entity_id, $type, $message ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_qb_sync_log';

		$wpdb->insert(
			$table,
			array(
				'entity_type'   => $type,
				'entity_id'     => is_numeric( $entity_id ) ? $entity_id : 0,
				'sync_type'     => 'create_or_update',
				'sync_status'   => 'failed',
				'error_message' => $message,
			),
			array( '%s', '%d', '%s', '%s', '%s' )
		);

		if ( class_exists( 'BKX_Error_Logger' ) ) {
			\BKX_Error_Logger::log( "QB Customer Sync Error ({$entity_id}): {$message}", 'error' );
		}
	}
}
