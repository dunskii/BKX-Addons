<?php
/**
 * Salesforce API wrapper service.
 *
 * @package BookingX\Salesforce
 */

namespace BookingX\Salesforce\Services;

defined( 'ABSPATH' ) || exit;

/**
 * SalesforceApi class.
 */
class SalesforceApi {

	/**
	 * Salesforce API version.
	 */
	const API_VERSION = 'v58.0';

	/**
	 * Credentials.
	 *
	 * @var array
	 */
	private $credentials;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->credentials = get_option( 'bkx_salesforce_credentials', array() );
		$this->settings    = get_option( 'bkx_salesforce_settings', array() );
	}

	/**
	 * Get Salesforce login URL.
	 *
	 * @return string
	 */
	private function get_login_url() {
		return ! empty( $this->settings['sandbox'] )
			? 'https://test.salesforce.com'
			: 'https://login.salesforce.com';
	}

	/**
	 * Get authorization URL for OAuth.
	 *
	 * @return string|\WP_Error
	 */
	public function get_authorization_url() {
		$client_id = $this->settings['client_id'] ?? '';

		if ( empty( $client_id ) ) {
			return new \WP_Error( 'missing_client_id', __( 'Client ID is required', 'bkx-salesforce' ) );
		}

		$redirect_uri = add_query_arg(
			'bkx_sf_oauth',
			'1',
			admin_url( 'admin.php' )
		);

		$params = array(
			'response_type' => 'code',
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'scope'         => 'api refresh_token',
		);

		return $this->get_login_url() . '/services/oauth2/authorize?' . http_build_query( $params );
	}

	/**
	 * Exchange authorization code for access token.
	 *
	 * @param string $code Authorization code.
	 * @return array|\WP_Error
	 */
	public function exchange_code_for_token( $code ) {
		$client_id     = $this->settings['client_id'] ?? '';
		$client_secret = $this->settings['client_secret'] ?? '';

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return new \WP_Error( 'missing_credentials', __( 'Client ID and Secret are required', 'bkx-salesforce' ) );
		}

		$redirect_uri = add_query_arg(
			'bkx_sf_oauth',
			'1',
			admin_url( 'admin.php' )
		);

		$response = wp_remote_post(
			$this->get_login_url() . '/services/oauth2/token',
			array(
				'body' => array(
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => $redirect_uri,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new \WP_Error(
				$body['error'],
				$body['error_description'] ?? __( 'OAuth error', 'bkx-salesforce' )
			);
		}

		// Store credentials.
		$credentials = array(
			'access_token'  => $body['access_token'],
			'refresh_token' => $body['refresh_token'],
			'instance_url'  => $body['instance_url'],
			'token_type'    => $body['token_type'] ?? 'Bearer',
			'issued_at'     => $body['issued_at'] ?? time(),
		);

		update_option( 'bkx_salesforce_credentials', $credentials );
		$this->credentials = $credentials;

		return $credentials;
	}

	/**
	 * Refresh the access token.
	 *
	 * @return array|\WP_Error
	 */
	public function refresh_token() {
		$client_id     = $this->settings['client_id'] ?? '';
		$client_secret = $this->settings['client_secret'] ?? '';
		$refresh_token = $this->credentials['refresh_token'] ?? '';

		if ( empty( $refresh_token ) ) {
			return new \WP_Error( 'no_refresh_token', __( 'No refresh token available', 'bkx-salesforce' ) );
		}

		$response = wp_remote_post(
			$this->get_login_url() . '/services/oauth2/token',
			array(
				'body' => array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => $refresh_token,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new \WP_Error(
				$body['error'],
				$body['error_description'] ?? __( 'Token refresh failed', 'bkx-salesforce' )
			);
		}

		// Update credentials.
		$this->credentials['access_token'] = $body['access_token'];
		$this->credentials['instance_url'] = $body['instance_url'];
		$this->credentials['issued_at']    = $body['issued_at'] ?? time();

		update_option( 'bkx_salesforce_credentials', $this->credentials );

		return $this->credentials;
	}

	/**
	 * Revoke the access token.
	 */
	public function revoke_token() {
		$access_token = $this->credentials['access_token'] ?? '';

		if ( empty( $access_token ) ) {
			return;
		}

		wp_remote_post(
			$this->get_login_url() . '/services/oauth2/revoke',
			array(
				'body' => array(
					'token' => $access_token,
				),
			)
		);
	}

	/**
	 * Test the connection.
	 *
	 * @return array|\WP_Error
	 */
	public function test_connection() {
		$result = $this->request( 'GET', '/services/data/' . self::API_VERSION . '/' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Get org info.
		$org_info = $this->request( 'GET', '/services/data/' . self::API_VERSION . '/sobjects/' );

		if ( is_wp_error( $org_info ) ) {
			return $org_info;
		}

		return array(
			'api_version' => self::API_VERSION,
			'sobjects'    => count( $org_info['sobjects'] ?? array() ),
		);
	}

	/**
	 * Make an API request.
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 * @return array|\WP_Error
	 */
	public function request( $method, $endpoint, $data = array() ) {
		$access_token = $this->credentials['access_token'] ?? '';
		$instance_url = $this->credentials['instance_url'] ?? '';

		if ( empty( $access_token ) || empty( $instance_url ) ) {
			return new \WP_Error( 'not_connected', __( 'Not connected to Salesforce', 'bkx-salesforce' ) );
		}

		$url = rtrim( $instance_url, '/' ) . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PATCH', 'PUT' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Handle token expiration.
		if ( 401 === $code ) {
			$refresh_result = $this->refresh_token();
			if ( is_wp_error( $refresh_result ) ) {
				return $refresh_result;
			}

			// Retry the request.
			return $this->request( $method, $endpoint, $data );
		}

		// Handle errors.
		if ( $code >= 400 ) {
			$error_message = __( 'API request failed', 'bkx-salesforce' );
			if ( is_array( $body ) && isset( $body[0]['message'] ) ) {
				$error_message = $body[0]['message'];
			} elseif ( is_array( $body ) && isset( $body['message'] ) ) {
				$error_message = $body['message'];
			}

			return new \WP_Error( 'api_error', $error_message, array( 'status' => $code ) );
		}

		return $body ?? array();
	}

	/**
	 * Describe a Salesforce object (get field metadata).
	 *
	 * @param string $object_type Object type (Contact, Lead, etc.).
	 * @return array|\WP_Error
	 */
	public function describe_object( $object_type ) {
		$result = $this->request( 'GET', '/services/data/' . self::API_VERSION . '/sobjects/' . $object_type . '/describe/' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$fields = array();
		foreach ( $result['fields'] ?? array() as $field ) {
			$fields[] = array(
				'name'       => $field['name'],
				'label'      => $field['label'],
				'type'       => $field['type'],
				'createable' => $field['createable'],
				'updateable' => $field['updateable'],
				'required'   => ! $field['nillable'] && ! $field['defaultedOnCreate'],
			);
		}

		return $fields;
	}

	/**
	 * Create a Salesforce record.
	 *
	 * @param string $object_type Object type.
	 * @param array  $data        Record data.
	 * @return array|\WP_Error
	 */
	public function create_record( $object_type, $data ) {
		return $this->request(
			'POST',
			'/services/data/' . self::API_VERSION . '/sobjects/' . $object_type . '/',
			$data
		);
	}

	/**
	 * Update a Salesforce record.
	 *
	 * @param string $object_type Object type.
	 * @param string $record_id   Record ID.
	 * @param array  $data        Record data.
	 * @return array|\WP_Error
	 */
	public function update_record( $object_type, $record_id, $data ) {
		return $this->request(
			'PATCH',
			'/services/data/' . self::API_VERSION . '/sobjects/' . $object_type . '/' . $record_id,
			$data
		);
	}

	/**
	 * Delete a Salesforce record.
	 *
	 * @param string $object_type Object type.
	 * @param string $record_id   Record ID.
	 * @return array|\WP_Error
	 */
	public function delete_record( $object_type, $record_id ) {
		return $this->request(
			'DELETE',
			'/services/data/' . self::API_VERSION . '/sobjects/' . $object_type . '/' . $record_id
		);
	}

	/**
	 * Get a Salesforce record by ID.
	 *
	 * @param string $object_type Object type.
	 * @param string $record_id   Record ID.
	 * @param array  $fields      Fields to retrieve.
	 * @return array|\WP_Error
	 */
	public function get_record( $object_type, $record_id, $fields = array() ) {
		$endpoint = '/services/data/' . self::API_VERSION . '/sobjects/' . $object_type . '/' . $record_id;

		if ( ! empty( $fields ) ) {
			$endpoint .= '?fields=' . implode( ',', $fields );
		}

		return $this->request( 'GET', $endpoint );
	}

	/**
	 * Query Salesforce records using SOQL.
	 *
	 * @param string $query SOQL query.
	 * @return array|\WP_Error
	 */
	public function query( $query ) {
		return $this->request(
			'GET',
			'/services/data/' . self::API_VERSION . '/query/?q=' . rawurlencode( $query )
		);
	}

	/**
	 * Search Salesforce records using SOSL.
	 *
	 * @param string $search SOSL search string.
	 * @return array|\WP_Error
	 */
	public function search( $search ) {
		return $this->request(
			'GET',
			'/services/data/' . self::API_VERSION . '/search/?q=' . rawurlencode( $search )
		);
	}

	/**
	 * Find a Contact by email.
	 *
	 * @param string $email Email address.
	 * @return array|null
	 */
	public function find_contact_by_email( $email ) {
		$query  = "SELECT Id, FirstName, LastName, Email, Phone FROM Contact WHERE Email = '" . esc_sql( $email ) . "' LIMIT 1";
		$result = $this->query( $query );

		if ( is_wp_error( $result ) || empty( $result['records'] ) ) {
			return null;
		}

		return $result['records'][0];
	}

	/**
	 * Find a Lead by email.
	 *
	 * @param string $email Email address.
	 * @return array|null
	 */
	public function find_lead_by_email( $email ) {
		$query  = "SELECT Id, FirstName, LastName, Email, Phone, Company, Status FROM Lead WHERE Email = '" . esc_sql( $email ) . "' LIMIT 1";
		$result = $this->query( $query );

		if ( is_wp_error( $result ) || empty( $result['records'] ) ) {
			return null;
		}

		return $result['records'][0];
	}

	/**
	 * Create or update a record using external ID.
	 *
	 * @param string $object_type    Object type.
	 * @param string $external_field External ID field name.
	 * @param string $external_id    External ID value.
	 * @param array  $data           Record data.
	 * @return array|\WP_Error
	 */
	public function upsert( $object_type, $external_field, $external_id, $data ) {
		return $this->request(
			'PATCH',
			'/services/data/' . self::API_VERSION . '/sobjects/' . $object_type . '/' . $external_field . '/' . $external_id,
			$data
		);
	}

	/**
	 * Create multiple records (composite API).
	 *
	 * @param string $object_type Object type.
	 * @param array  $records     Array of record data.
	 * @return array|\WP_Error
	 */
	public function create_records( $object_type, $records ) {
		return $this->request(
			'POST',
			'/services/data/' . self::API_VERSION . '/composite/sobjects/' . $object_type,
			array(
				'allOrNone' => false,
				'records'   => $records,
			)
		);
	}

	/**
	 * Update multiple records (composite API).
	 *
	 * @param string $object_type Object type.
	 * @param array  $records     Array of record data with Id field.
	 * @return array|\WP_Error
	 */
	public function update_records( $object_type, $records ) {
		return $this->request(
			'PATCH',
			'/services/data/' . self::API_VERSION . '/composite/sobjects/' . $object_type,
			array(
				'allOrNone' => false,
				'records'   => $records,
			)
		);
	}

	/**
	 * Delete multiple records (composite API).
	 *
	 * @param array $ids Array of record IDs.
	 * @return array|\WP_Error
	 */
	public function delete_records( $ids ) {
		return $this->request(
			'DELETE',
			'/services/data/' . self::API_VERSION . '/composite/sobjects?ids=' . implode( ',', $ids )
		);
	}
}
