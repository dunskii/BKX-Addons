<?php
/**
 * Xero Contact Sync Service.
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
 * ContactSync Class.
 */
class ContactSync {

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
	 * Sync contact by email.
	 *
	 * @param string $email Contact email.
	 * @return array|false Sync result or false.
	 */
	public function sync_contact_by_email( $email ) {
		$user = get_user_by( 'email', $email );

		if ( ! $user ) {
			return $this->create_or_update_contact(
				array(
					'email'      => $email,
					'first_name' => '',
					'last_name'  => '',
				)
			);
		}

		return $this->sync_contact( $user->ID );
	}

	/**
	 * Sync WordPress user to Xero contact.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return array|false Sync result or false.
	 */
	public function sync_contact( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			$this->log_sync_error( $user_id, 'contact', 'User not found' );
			return false;
		}

		$contact_data = array(
			'user_id'    => $user_id,
			'email'      => $user->user_email,
			'first_name' => $user->first_name,
			'last_name'  => $user->last_name,
			'phone'      => get_user_meta( $user_id, 'billing_phone', true ),
			'address'    => array(
				'line1'   => get_user_meta( $user_id, 'billing_address_1', true ),
				'line2'   => get_user_meta( $user_id, 'billing_address_2', true ),
				'city'    => get_user_meta( $user_id, 'billing_city', true ),
				'region'  => get_user_meta( $user_id, 'billing_state', true ),
				'postal'  => get_user_meta( $user_id, 'billing_postcode', true ),
				'country' => get_user_meta( $user_id, 'billing_country', true ),
			),
		);

		return $this->create_or_update_contact( $contact_data );
	}

	/**
	 * Create or update contact in Xero.
	 *
	 * @param array $contact_data Contact data.
	 * @return array|false Result or false.
	 */
	public function create_or_update_contact( $contact_data ) {
		$user_id = isset( $contact_data['user_id'] ) ? absint( $contact_data['user_id'] ) : 0;
		$email   = sanitize_email( $contact_data['email'] );

		if ( empty( $email ) ) {
			$this->log_sync_error( $user_id, 'contact', 'Missing email' );
			return false;
		}

		// Check if contact already mapped.
		$existing_xero_id = $this->get_xero_contact_id( $user_id, $email );

		$xero_contact = $this->build_xero_contact( $contact_data );

		if ( $existing_xero_id ) {
			// Update existing contact.
			$xero_contact['ContactID'] = $existing_xero_id;
			$result = $this->update_xero_contact( $xero_contact );
		} else {
			// Check if contact exists in Xero by email.
			$found = $this->find_xero_contact_by_email( $email );

			if ( $found ) {
				// Map existing Xero contact.
				$this->save_mapping( $user_id ?: $email, $found['ContactID'] );
				$result = $found;
			} else {
				// Create new contact.
				$result = $this->create_xero_contact( $xero_contact );
			}
		}

		if ( $result && isset( $result['ContactID'] ) ) {
			$this->save_mapping( $user_id ?: $email, $result['ContactID'] );
			$this->log_sync_success( $user_id ?: $email, 'contact', $result['ContactID'] );

			return array(
				'xero_id' => $result['ContactID'],
				'synced'  => true,
				'created' => ! $existing_xero_id && ! isset( $found ),
			);
		}

		return false;
	}

	/**
	 * Build Xero contact object.
	 *
	 * @param array $data Contact data.
	 * @return array Xero contact object.
	 */
	private function build_xero_contact( $data ) {
		$name = trim( $data['first_name'] . ' ' . $data['last_name'] );

		if ( empty( $name ) ) {
			$name = $data['email'];
		}

		$contact = array(
			'Name'         => $name,
			'FirstName'    => $data['first_name'] ?: null,
			'LastName'     => $data['last_name'] ?: null,
			'EmailAddress' => $data['email'],
			'IsCustomer'   => true,
		);

		// Add phone if available.
		if ( ! empty( $data['phone'] ) ) {
			$contact['Phones'] = array(
				array(
					'PhoneType'   => 'DEFAULT',
					'PhoneNumber' => $data['phone'],
				),
			);
		}

		// Add address if available.
		if ( ! empty( $data['address']['line1'] ) ) {
			$contact['Addresses'] = array(
				array(
					'AddressType'  => 'POBOX',
					'AddressLine1' => $data['address']['line1'],
					'AddressLine2' => $data['address']['line2'] ?? null,
					'City'         => $data['address']['city'] ?? null,
					'Region'       => $data['address']['region'] ?? null,
					'PostalCode'   => $data['address']['postal'] ?? null,
					'Country'      => $data['address']['country'] ?? null,
				),
			);
		}

		// Remove null values.
		return array_filter( $contact, function( $v ) {
			return null !== $v;
		});
	}

	/**
	 * Create contact in Xero.
	 *
	 * @param array $contact Contact data.
	 * @return array|false Response or false.
	 */
	private function create_xero_contact( $contact ) {
		$response = $this->oauth->api_request(
			'Contacts',
			'POST',
			array( 'Contacts' => array( $contact ) )
		);

		if ( $response && isset( $response['Contacts'][0] ) ) {
			return $response['Contacts'][0];
		}

		return false;
	}

	/**
	 * Update contact in Xero.
	 *
	 * @param array $contact Contact data with ContactID.
	 * @return array|false Response or false.
	 */
	private function update_xero_contact( $contact ) {
		$response = $this->oauth->api_request(
			'Contacts/' . $contact['ContactID'],
			'POST',
			array( 'Contacts' => array( $contact ) )
		);

		if ( $response && isset( $response['Contacts'][0] ) ) {
			return $response['Contacts'][0];
		}

		return false;
	}

	/**
	 * Get contact from Xero.
	 *
	 * @param string $xero_id Xero contact ID.
	 * @return array|false Contact data or false.
	 */
	public function get_xero_contact( $xero_id ) {
		$response = $this->oauth->api_request( "Contacts/{$xero_id}" );

		if ( $response && isset( $response['Contacts'][0] ) ) {
			return $response['Contacts'][0];
		}

		return false;
	}

	/**
	 * Find contact in Xero by email.
	 *
	 * @param string $email Email address.
	 * @return array|false Contact data or false.
	 */
	public function find_xero_contact_by_email( $email ) {
		$response = $this->oauth->api_request(
			'Contacts?where=EmailAddress=="' . rawurlencode( $email ) . '"'
		);

		if ( $response && isset( $response['Contacts'][0] ) ) {
			return $response['Contacts'][0];
		}

		return false;
	}

	/**
	 * Sync all WordPress users to Xero.
	 *
	 * @return array Sync results.
	 */
	public function sync_all_contacts() {
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
				$result = $this->sync_contact( $user->ID );
			} else {
				$result = $this->sync_contact_by_email( $email );
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
	 * Get Xero contact ID from mapping.
	 *
	 * @param int|string $identifier User ID or email.
	 * @param string     $email      Email for fallback lookup.
	 * @return string|false Xero contact ID or false.
	 */
	private function get_xero_contact_id( $identifier, $email = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_mapping';

		// Try by user ID first.
		if ( is_numeric( $identifier ) && $identifier > 0 ) {
			$xero_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT xero_id FROM %i WHERE entity_type = 'contact' AND bkx_id = %d",
					$table,
					$identifier
				)
			);

			if ( $xero_id ) {
				return $xero_id;
			}
		}

		// Try by email hash.
		if ( $email ) {
			$email_hash = md5( strtolower( $email ) );
			$xero_id    = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT xero_id FROM %i WHERE entity_type = 'contact_email' AND bkx_id = %s",
					$table,
					$email_hash
				)
			);

			if ( $xero_id ) {
				return $xero_id;
			}
		}

		return false;
	}

	/**
	 * Save contact mapping.
	 *
	 * @param int|string $identifier User ID or email.
	 * @param string     $xero_id    Xero contact ID.
	 */
	private function save_mapping( $identifier, $xero_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_mapping';

		$entity_type = is_numeric( $identifier ) ? 'contact' : 'contact_email';
		$bkx_id      = is_numeric( $identifier ) ? $identifier : md5( strtolower( $identifier ) );

		$wpdb->replace(
			$table,
			array(
				'entity_type' => $entity_type,
				'bkx_id'      => $bkx_id,
				'xero_id'     => $xero_id,
				'last_synced' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Log sync success.
	 *
	 * @param int|string $entity_id Entity ID.
	 * @param string     $type      Entity type.
	 * @param string     $xero_id   Xero ID.
	 */
	private function log_sync_success( $entity_id, $type, $xero_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_sync_log';

		$wpdb->insert(
			$table,
			array(
				'entity_type' => $type,
				'entity_id'   => is_numeric( $entity_id ) ? $entity_id : 0,
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
	 * @param int|string $entity_id Entity ID.
	 * @param string     $type      Entity type.
	 * @param string     $message   Error message.
	 */
	private function log_sync_error( $entity_id, $type, $message ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_xero_sync_log';

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
			\BKX_Error_Logger::log( "Xero Contact Sync Error ({$entity_id}): {$message}", 'error' );
		}
	}
}
