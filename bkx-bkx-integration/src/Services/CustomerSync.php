<?php
/**
 * Customer sync service.
 *
 * @package BookingX\BkxIntegration\Services
 */

namespace BookingX\BkxIntegration\Services;

defined( 'ABSPATH' ) || exit;

/**
 * CustomerSync class.
 */
class CustomerSync {

	/**
	 * API client.
	 *
	 * @var ApiClient
	 */
	private $api;

	/**
	 * Site service.
	 *
	 * @var SiteService
	 */
	private $sites;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api   = new ApiClient();
		$this->sites = new SiteService();
	}

	/**
	 * Queue outgoing customer sync.
	 *
	 * @param int    $user_id User ID.
	 * @param string $action  Action (create, update).
	 */
	public function queue_outgoing( $user_id, $action ) {
		global $wpdb;

		// Check if user is a customer.
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! in_array( 'subscriber', $user->roles, true ) ) {
			return;
		}

		// Get active sites that sync customers.
		$sites = $this->sites->get_all( array( 'status' => 'active' ) );

		foreach ( $sites as $site ) {
			if ( ! $site->sync_customers || 'pull' === $site->direction ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$wpdb->prefix . 'bkx_remote_queue',
				array(
					'site_id'     => $site->id,
					'action'      => $action,
					'object_type' => 'customer',
					'object_id'   => $user_id,
					'payload'     => wp_json_encode( $this->get_customer_data( $user_id ) ),
					'priority'    => 15, // Lower priority than bookings.
				),
				array( '%d', '%s', '%s', '%d', '%s', '%d' )
			);
		}
	}

	/**
	 * Get customer data for sync.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_customer_data( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return array();
		}

		return array(
			'local_id'     => $user_id,
			'email'        => $user->user_email,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'display_name' => $user->display_name,
			'phone'        => get_user_meta( $user_id, 'billing_phone', true ) ?: get_user_meta( $user_id, 'phone', true ),
			'address'      => array(
				'line_1'      => get_user_meta( $user_id, 'billing_address_1', true ),
				'line_2'      => get_user_meta( $user_id, 'billing_address_2', true ),
				'city'        => get_user_meta( $user_id, 'billing_city', true ),
				'state'       => get_user_meta( $user_id, 'billing_state', true ),
				'postal_code' => get_user_meta( $user_id, 'billing_postcode', true ),
				'country'     => get_user_meta( $user_id, 'billing_country', true ),
			),
			'source_site'  => home_url(),
			'created_at'   => $user->user_registered,
			'hash'         => md5( $user->user_email . $user->first_name . $user->last_name ),
		);
	}

	/**
	 * Handle incoming customer sync.
	 *
	 * @param array $data Customer data.
	 * @return int|\WP_Error User ID or error.
	 */
	public function handle_incoming( $data ) {
		$remote_id   = $data['local_id'] ?? 0;
		$email       = $data['email'] ?? '';
		$source_site = $data['source_site'] ?? '';

		if ( ! $email || ! $source_site ) {
			return new \WP_Error( 'missing_data', __( 'Missing required data.', 'bkx-bkx-integration' ) );
		}

		// Get site by URL.
		$site = $this->sites->get_by_url( $source_site );

		if ( ! $site ) {
			return new \WP_Error( 'unknown_site', __( 'Unknown source site.', 'bkx-bkx-integration' ) );
		}

		if ( 'push' === $site->direction || ! $site->sync_customers ) {
			return new \WP_Error( 'not_configured', __( 'Site not configured for customer sync.', 'bkx-bkx-integration' ) );
		}

		// Check if user exists by email.
		$existing_user = get_user_by( 'email', $email );

		if ( $existing_user ) {
			return $this->update_customer( $existing_user->ID, $data, $site->id, $remote_id );
		}

		return $this->create_customer( $data, $site->id, $remote_id );
	}

	/**
	 * Create customer from remote data.
	 *
	 * @param array $data      Customer data.
	 * @param int   $site_id   Site ID.
	 * @param int   $remote_id Remote customer ID.
	 * @return int|\WP_Error
	 */
	private function create_customer( $data, $site_id, $remote_id ) {
		$userdata = array(
			'user_login'   => $this->generate_username( $data['email'] ),
			'user_email'   => $data['email'],
			'user_pass'    => wp_generate_password( 16 ),
			'first_name'   => $data['first_name'] ?? '',
			'last_name'    => $data['last_name'] ?? '',
			'display_name' => $data['display_name'] ?? '',
			'role'         => 'subscriber',
		);

		$user_id = wp_insert_user( $userdata );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Save meta.
		$this->save_customer_meta( $user_id, $data );

		// Save mapping.
		$this->save_mapping( $site_id, 'customer', $user_id, $remote_id, $data['hash'] ?? '' );

		return $user_id;
	}

	/**
	 * Update customer from remote data.
	 *
	 * @param int   $user_id   User ID.
	 * @param array $data      Customer data.
	 * @param int   $site_id   Site ID.
	 * @param int   $remote_id Remote customer ID.
	 * @return int|\WP_Error
	 */
	private function update_customer( $user_id, $data, $site_id, $remote_id ) {
		$userdata = array(
			'ID'           => $user_id,
			'first_name'   => $data['first_name'] ?? '',
			'last_name'    => $data['last_name'] ?? '',
			'display_name' => $data['display_name'] ?? '',
		);

		$result = wp_update_user( $userdata );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update meta.
		$this->save_customer_meta( $user_id, $data );

		// Update mapping.
		$this->save_mapping( $site_id, 'customer', $user_id, $remote_id, $data['hash'] ?? '' );

		return $user_id;
	}

	/**
	 * Save customer meta.
	 *
	 * @param int   $user_id User ID.
	 * @param array $data    Customer data.
	 */
	private function save_customer_meta( $user_id, $data ) {
		if ( ! empty( $data['phone'] ) ) {
			update_user_meta( $user_id, 'billing_phone', $data['phone'] );
		}

		if ( ! empty( $data['address'] ) && is_array( $data['address'] ) ) {
			$address = $data['address'];
			if ( ! empty( $address['line_1'] ) ) {
				update_user_meta( $user_id, 'billing_address_1', $address['line_1'] );
			}
			if ( ! empty( $address['line_2'] ) ) {
				update_user_meta( $user_id, 'billing_address_2', $address['line_2'] );
			}
			if ( ! empty( $address['city'] ) ) {
				update_user_meta( $user_id, 'billing_city', $address['city'] );
			}
			if ( ! empty( $address['state'] ) ) {
				update_user_meta( $user_id, 'billing_state', $address['state'] );
			}
			if ( ! empty( $address['postal_code'] ) ) {
				update_user_meta( $user_id, 'billing_postcode', $address['postal_code'] );
			}
			if ( ! empty( $address['country'] ) ) {
				update_user_meta( $user_id, 'billing_country', $address['country'] );
			}
		}

		// Mark as synced from remote.
		update_user_meta( $user_id, '_bkx_synced_from_remote', true );
		update_user_meta( $user_id, '_bkx_source_site', $data['source_site'] ?? '' );
	}

	/**
	 * Generate unique username from email.
	 *
	 * @param string $email Email address.
	 * @return string
	 */
	private function generate_username( $email ) {
		$username = sanitize_user( current( explode( '@', $email ) ), true );

		if ( username_exists( $username ) ) {
			$username .= '_' . wp_rand( 100, 999 );
		}

		return $username;
	}

	/**
	 * Save mapping.
	 *
	 * @param int    $site_id     Site ID.
	 * @param string $object_type Object type.
	 * @param int    $local_id    Local ID.
	 * @param string $remote_id   Remote ID.
	 * @param string $hash        Data hash.
	 */
	private function save_mapping( $site_id, $object_type, $local_id, $remote_id, $hash = '' ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->replace(
			$wpdb->prefix . 'bkx_remote_mappings',
			array(
				'site_id'     => $site_id,
				'object_type' => $object_type,
				'local_id'    => $local_id,
				'remote_id'   => $remote_id,
				'sync_hash'   => $hash,
				'last_synced' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Sync customers for a site.
	 *
	 * @param int $site_id Site ID.
	 * @return array Results.
	 */
	public function sync_site( $site_id ) {
		$site = $this->sites->get( $site_id );

		if ( ! $site || ! $site->sync_customers ) {
			return array( 'synced' => 0, 'errors' => 0 );
		}

		$synced = 0;
		$errors = 0;

		// Get customers who have made bookings.
		$customers = get_users(
			array(
				'role'   => 'subscriber',
				'number' => 100,
			)
		);

		foreach ( $customers as $customer ) {
			$data   = $this->get_customer_data( $customer->ID );
			$result = $this->api->send_customer( $site_id, $data, 'create' );

			if ( is_wp_error( $result ) ) {
				++$errors;
			} else {
				++$synced;
			}
		}

		return array(
			'synced' => $synced,
			'errors' => $errors,
		);
	}
}
