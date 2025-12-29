<?php
/**
 * HubSpot API wrapper service.
 *
 * @package BookingX\HubSpot
 */

namespace BookingX\HubSpot\Services;

defined( 'ABSPATH' ) || exit;

/**
 * HubSpotApi class.
 */
class HubSpotApi {

	/**
	 * HubSpot API base URL.
	 */
	const API_BASE_URL = 'https://api.hubapi.com';

	/**
	 * OAuth base URL.
	 */
	const OAUTH_BASE_URL = 'https://app.hubspot.com/oauth/authorize';

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
		$this->credentials = get_option( 'bkx_hubspot_credentials', array() );
		$this->settings    = get_option( 'bkx_hubspot_settings', array() );
	}

	/**
	 * Get authorization URL for OAuth.
	 *
	 * @return string|\WP_Error
	 */
	public function get_authorization_url() {
		$client_id = $this->settings['client_id'] ?? '';

		if ( empty( $client_id ) ) {
			return new \WP_Error( 'missing_client_id', __( 'Client ID is required', 'bkx-hubspot' ) );
		}

		$redirect_uri = add_query_arg(
			'bkx_hs_oauth',
			'1',
			admin_url( 'admin.php' )
		);

		$scopes = array(
			'crm.objects.contacts.read',
			'crm.objects.contacts.write',
			'crm.objects.deals.read',
			'crm.objects.deals.write',
			'crm.schemas.deals.read',
			'crm.lists.read',
			'crm.lists.write',
		);

		$params = array(
			'client_id'    => $client_id,
			'redirect_uri' => $redirect_uri,
			'scope'        => implode( ' ', $scopes ),
		);

		return self::OAUTH_BASE_URL . '?' . http_build_query( $params );
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
			return new \WP_Error( 'missing_credentials', __( 'Client ID and Secret are required', 'bkx-hubspot' ) );
		}

		$redirect_uri = add_query_arg(
			'bkx_hs_oauth',
			'1',
			admin_url( 'admin.php' )
		);

		$response = wp_remote_post(
			self::API_BASE_URL . '/oauth/v1/token',
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
				$body['error_description'] ?? __( 'OAuth error', 'bkx-hubspot' )
			);
		}

		// Store credentials.
		$credentials = array(
			'access_token'  => $body['access_token'],
			'refresh_token' => $body['refresh_token'],
			'expires_in'    => $body['expires_in'] ?? 3600,
			'token_type'    => $body['token_type'] ?? 'Bearer',
			'issued_at'     => time(),
		);

		update_option( 'bkx_hubspot_credentials', $credentials );
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
			return new \WP_Error( 'no_refresh_token', __( 'No refresh token available', 'bkx-hubspot' ) );
		}

		$response = wp_remote_post(
			self::API_BASE_URL . '/oauth/v1/token',
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
				$body['error_description'] ?? __( 'Token refresh failed', 'bkx-hubspot' )
			);
		}

		// Update credentials.
		$this->credentials['access_token']  = $body['access_token'];
		$this->credentials['refresh_token'] = $body['refresh_token'];
		$this->credentials['expires_in']    = $body['expires_in'] ?? 3600;
		$this->credentials['issued_at']     = time();

		update_option( 'bkx_hubspot_credentials', $this->credentials );

		return $this->credentials;
	}

	/**
	 * Test the connection.
	 *
	 * @return array|\WP_Error
	 */
	public function test_connection() {
		$result = $this->request( 'GET', '/account-info/v3/details' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'portal_id' => $result['portalId'] ?? '',
			'hub_id'    => $result['hubId'] ?? '',
			'timezone'  => $result['timeZone'] ?? '',
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

		if ( empty( $access_token ) ) {
			return new \WP_Error( 'not_connected', __( 'Not connected to HubSpot', 'bkx-hubspot' ) );
		}

		// Check if token needs refresh.
		$issued_at  = $this->credentials['issued_at'] ?? 0;
		$expires_in = $this->credentials['expires_in'] ?? 3600;
		if ( time() > ( $issued_at + $expires_in - 300 ) ) {
			$refresh_result = $this->refresh_token();
			if ( is_wp_error( $refresh_result ) ) {
				return $refresh_result;
			}
			$access_token = $this->credentials['access_token'];
		}

		$url = self::API_BASE_URL . $endpoint;

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

		// Handle errors.
		if ( $code >= 400 ) {
			$error_message = __( 'API request failed', 'bkx-hubspot' );
			if ( isset( $body['message'] ) ) {
				$error_message = $body['message'];
			} elseif ( isset( $body['error'] ) ) {
				$error_message = $body['error'];
			}

			return new \WP_Error( 'api_error', $error_message, array( 'status' => $code ) );
		}

		return $body ?? array();
	}

	/**
	 * Get object properties.
	 *
	 * @param string $object_type Object type (contacts, deals, etc.).
	 * @return array|\WP_Error
	 */
	public function get_properties( $object_type ) {
		$result = $this->request( 'GET', "/crm/v3/properties/{$object_type}" );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$properties = array();
		foreach ( $result['results'] ?? array() as $prop ) {
			$properties[] = array(
				'name'     => $prop['name'],
				'label'    => $prop['label'],
				'type'     => $prop['type'],
				'readOnly' => $prop['modificationMetadata']['readOnlyValue'] ?? false,
			);
		}

		return $properties;
	}

	/**
	 * Get deal pipelines.
	 *
	 * @return array|\WP_Error
	 */
	public function get_pipelines() {
		$result = $this->request( 'GET', '/crm/v3/pipelines/deals' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$pipelines = array();
		foreach ( $result['results'] ?? array() as $pipeline ) {
			$stages = array();
			foreach ( $pipeline['stages'] ?? array() as $stage ) {
				$stages[] = array(
					'id'    => $stage['id'],
					'label' => $stage['label'],
					'order' => $stage['displayOrder'],
				);
			}

			$pipelines[] = array(
				'id'     => $pipeline['id'],
				'label'  => $pipeline['label'],
				'stages' => $stages,
			);
		}

		return $pipelines;
	}

	/**
	 * Get contact lists.
	 *
	 * @return array|\WP_Error
	 */
	public function get_lists() {
		$result = $this->request( 'GET', '/crm/v3/lists' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$lists = array();
		foreach ( $result['lists'] ?? array() as $list ) {
			if ( 'STATIC' === ( $list['processingType'] ?? '' ) ) {
				$lists[] = array(
					'id'   => $list['listId'],
					'name' => $list['name'],
				);
			}
		}

		return $lists;
	}

	/**
	 * Create a contact.
	 *
	 * @param array $properties Contact properties.
	 * @return array|\WP_Error
	 */
	public function create_contact( $properties ) {
		return $this->request(
			'POST',
			'/crm/v3/objects/contacts',
			array( 'properties' => $properties )
		);
	}

	/**
	 * Update a contact.
	 *
	 * @param string $contact_id Contact ID.
	 * @param array  $properties Contact properties.
	 * @return array|\WP_Error
	 */
	public function update_contact( $contact_id, $properties ) {
		return $this->request(
			'PATCH',
			"/crm/v3/objects/contacts/{$contact_id}",
			array( 'properties' => $properties )
		);
	}

	/**
	 * Get a contact by ID.
	 *
	 * @param string $contact_id Contact ID.
	 * @param array  $properties Properties to retrieve.
	 * @return array|\WP_Error
	 */
	public function get_contact( $contact_id, $properties = array() ) {
		$endpoint = "/crm/v3/objects/contacts/{$contact_id}";

		if ( ! empty( $properties ) ) {
			$endpoint .= '?properties=' . implode( ',', $properties );
		}

		return $this->request( 'GET', $endpoint );
	}

	/**
	 * Search for a contact by email.
	 *
	 * @param string $email Email address.
	 * @return array|null Contact data or null.
	 */
	public function find_contact_by_email( $email ) {
		$result = $this->request(
			'POST',
			'/crm/v3/objects/contacts/search',
			array(
				'filterGroups' => array(
					array(
						'filters' => array(
							array(
								'propertyName' => 'email',
								'operator'     => 'EQ',
								'value'        => $email,
							),
						),
					),
				),
				'properties'   => array( 'firstname', 'lastname', 'email', 'phone' ),
				'limit'        => 1,
			)
		);

		if ( is_wp_error( $result ) || empty( $result['results'] ) ) {
			return null;
		}

		return $result['results'][0];
	}

	/**
	 * Create a deal.
	 *
	 * @param array $properties Deal properties.
	 * @return array|\WP_Error
	 */
	public function create_deal( $properties ) {
		return $this->request(
			'POST',
			'/crm/v3/objects/deals',
			array( 'properties' => $properties )
		);
	}

	/**
	 * Update a deal.
	 *
	 * @param string $deal_id    Deal ID.
	 * @param array  $properties Deal properties.
	 * @return array|\WP_Error
	 */
	public function update_deal( $deal_id, $properties ) {
		return $this->request(
			'PATCH',
			"/crm/v3/objects/deals/{$deal_id}",
			array( 'properties' => $properties )
		);
	}

	/**
	 * Associate a contact with a deal.
	 *
	 * @param string $deal_id    Deal ID.
	 * @param string $contact_id Contact ID.
	 * @return array|\WP_Error
	 */
	public function associate_deal_contact( $deal_id, $contact_id ) {
		return $this->request(
			'PUT',
			"/crm/v3/objects/deals/{$deal_id}/associations/contacts/{$contact_id}/deal_to_contact"
		);
	}

	/**
	 * Add contact to a list.
	 *
	 * @param string $list_id    List ID.
	 * @param string $contact_id Contact ID.
	 * @return array|\WP_Error
	 */
	public function add_contact_to_list( $list_id, $contact_id ) {
		return $this->request(
			'PUT',
			"/crm/v3/lists/{$list_id}/memberships/add",
			array( $contact_id )
		);
	}

	/**
	 * Create an engagement (activity).
	 *
	 * @param string $contact_id Contact ID.
	 * @param string $type       Engagement type (NOTE, TASK, etc.).
	 * @param array  $metadata   Engagement metadata.
	 * @return array|\WP_Error
	 */
	public function create_engagement( $contact_id, $type, $metadata ) {
		$engagement_data = array(
			'engagement'   => array(
				'active' => true,
				'type'   => $type,
			),
			'associations' => array(
				'contactIds' => array( (int) $contact_id ),
			),
			'metadata'     => $metadata,
		);

		return $this->request( 'POST', '/engagements/v1/engagements', $engagement_data );
	}

	/**
	 * Batch create contacts.
	 *
	 * @param array $contacts Array of contact property arrays.
	 * @return array|\WP_Error
	 */
	public function batch_create_contacts( $contacts ) {
		$inputs = array();
		foreach ( $contacts as $properties ) {
			$inputs[] = array( 'properties' => $properties );
		}

		return $this->request(
			'POST',
			'/crm/v3/objects/contacts/batch/create',
			array( 'inputs' => $inputs )
		);
	}

	/**
	 * Batch update contacts.
	 *
	 * @param array $contacts Array of contact data with id and properties.
	 * @return array|\WP_Error
	 */
	public function batch_update_contacts( $contacts ) {
		$inputs = array();
		foreach ( $contacts as $contact ) {
			$inputs[] = array(
				'id'         => $contact['id'],
				'properties' => $contact['properties'],
			);
		}

		return $this->request(
			'POST',
			'/crm/v3/objects/contacts/batch/update',
			array( 'inputs' => $inputs )
		);
	}
}
