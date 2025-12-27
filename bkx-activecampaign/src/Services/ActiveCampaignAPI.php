<?php
/**
 * ActiveCampaign API Service
 *
 * @package BookingX\ActiveCampaign
 * @since   1.0.0
 */

namespace BookingX\ActiveCampaign\Services;

use BookingX\ActiveCampaign\ActiveCampaignAddon;

/**
 * Class ActiveCampaignAPI
 *
 * Handles communication with ActiveCampaign API v3.
 *
 * @since 1.0.0
 */
class ActiveCampaignAPI {

	/**
	 * Addon instance.
	 *
	 * @var ActiveCampaignAddon
	 */
	private ActiveCampaignAddon $addon;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private string $api_url;

	/**
	 * API key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param ActiveCampaignAddon $addon Addon instance.
	 */
	public function __construct( ActiveCampaignAddon $addon ) {
		$this->addon   = $addon;
		$this->api_url = rtrim( $addon->get_setting( 'api_url', '' ), '/' );

		$encrypted_key = $addon->get_setting( 'api_key', '' );
		$this->api_key = $addon->get_encryption()->decrypt( $encrypted_key );
	}

	/**
	 * Test connection to ActiveCampaign.
	 *
	 * @since 1.0.0
	 * @return array|\WP_Error Account info or error.
	 */
	public function test_connection() {
		$response = $this->request( 'GET', 'accounts' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( empty( $response['accounts'] ) ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Invalid response from ActiveCampaign API', 'bkx-activecampaign' )
			);
		}

		return $response['accounts'][0];
	}

	/**
	 * Make API request.
	 *
	 * @since 1.0.0
	 * @param string $method   HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 * @return array|\WP_Error
	 */
	public function request( string $method, string $endpoint, array $data = array() ) {
		$url = $this->api_url . '/api/3/' . ltrim( $endpoint, '/' );

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Api-Token'    => $this->api_key,
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) ) {
			if ( 'GET' === $method ) {
				$url = add_query_arg( $data, $url );
			} else {
				$args['body'] = wp_json_encode( $data );
			}
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->addon->log( sprintf( 'ActiveCampaign API error: %s', $response->get_error_message() ), 'error' );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			$message = $data['message'] ?? __( 'Unknown API error', 'bkx-activecampaign' );
			$this->addon->log( sprintf( 'ActiveCampaign API error %d: %s', $code, $message ), 'error' );

			return new \WP_Error(
				'api_error',
				$message,
				array( 'status' => $code )
			);
		}

		return $data;
	}

	/**
	 * Create a contact.
	 *
	 * @since 1.0.0
	 * @param array $contact_data Contact data.
	 * @return int|\WP_Error Contact ID or error.
	 */
	public function create_contact( array $contact_data ) {
		$response = $this->request(
			'POST',
			'contacts',
			array( 'contact' => $contact_data )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return (int) $response['contact']['id'];
	}

	/**
	 * Update a contact.
	 *
	 * @since 1.0.0
	 * @param int   $contact_id   Contact ID.
	 * @param array $contact_data Contact data.
	 * @return int|\WP_Error Contact ID or error.
	 */
	public function update_contact( int $contact_id, array $contact_data ) {
		$response = $this->request(
			'PUT',
			'contacts/' . $contact_id,
			array( 'contact' => $contact_data )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return (int) $response['contact']['id'];
	}

	/**
	 * Get contact by email.
	 *
	 * @since 1.0.0
	 * @param string $email Email address.
	 * @return array|null Contact data or null.
	 */
	public function get_contact_by_email( string $email ): ?array {
		$response = $this->request(
			'GET',
			'contacts',
			array( 'email' => $email )
		);

		if ( is_wp_error( $response ) || empty( $response['contacts'] ) ) {
			return null;
		}

		return $response['contacts'][0];
	}

	/**
	 * Add tag to contact.
	 *
	 * @since 1.0.0
	 * @param int    $contact_id Contact ID.
	 * @param string $tag_name   Tag name.
	 * @return bool|\WP_Error
	 */
	public function add_tag( int $contact_id, string $tag_name ) {
		// First, get or create the tag.
		$tag_id = $this->get_or_create_tag( $tag_name );

		if ( is_wp_error( $tag_id ) ) {
			return $tag_id;
		}

		$response = $this->request(
			'POST',
			'contactTags',
			array(
				'contactTag' => array(
					'contact' => $contact_id,
					'tag'     => $tag_id,
				),
			)
		);

		return ! is_wp_error( $response );
	}

	/**
	 * Get or create a tag.
	 *
	 * @since 1.0.0
	 * @param string $tag_name Tag name.
	 * @return int|\WP_Error Tag ID or error.
	 */
	public function get_or_create_tag( string $tag_name ) {
		// Search for existing tag.
		$response = $this->request(
			'GET',
			'tags',
			array( 'search' => $tag_name )
		);

		if ( ! is_wp_error( $response ) && ! empty( $response['tags'] ) ) {
			foreach ( $response['tags'] as $tag ) {
				if ( strtolower( $tag['tag'] ) === strtolower( $tag_name ) ) {
					return (int) $tag['id'];
				}
			}
		}

		// Create new tag.
		$response = $this->request(
			'POST',
			'tags',
			array(
				'tag' => array(
					'tag'         => $tag_name,
					'tagType'     => 'contact',
					'description' => 'Created by BookingX',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return (int) $response['tag']['id'];
	}

	/**
	 * Add contact to list.
	 *
	 * @since 1.0.0
	 * @param int $contact_id Contact ID.
	 * @param int $list_id    List ID.
	 * @return bool|\WP_Error
	 */
	public function add_to_list( int $contact_id, int $list_id ) {
		$response = $this->request(
			'POST',
			'contactLists',
			array(
				'contactList' => array(
					'contact' => $contact_id,
					'list'    => $list_id,
					'status'  => 1, // Active.
				),
			)
		);

		return ! is_wp_error( $response );
	}

	/**
	 * Add contact to automation.
	 *
	 * @since 1.0.0
	 * @param int $contact_id    Contact ID.
	 * @param int $automation_id Automation ID.
	 * @return bool|\WP_Error
	 */
	public function add_to_automation( int $contact_id, int $automation_id ) {
		$response = $this->request(
			'POST',
			'contactAutomations',
			array(
				'contactAutomation' => array(
					'contact'    => $contact_id,
					'automation' => $automation_id,
				),
			)
		);

		return ! is_wp_error( $response );
	}

	/**
	 * Get all lists.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_lists(): array {
		$response = $this->request( 'GET', 'lists', array( 'limit' => 100 ) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		return $response['lists'] ?? array();
	}

	/**
	 * Get all pipelines.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_pipelines(): array {
		$response = $this->request( 'GET', 'dealGroups', array( 'limit' => 100 ) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		return $response['dealGroups'] ?? array();
	}

	/**
	 * Get pipeline stages.
	 *
	 * @since 1.0.0
	 * @param int $pipeline_id Pipeline ID.
	 * @return array
	 */
	public function get_pipeline_stages( int $pipeline_id ): array {
		$response = $this->request(
			'GET',
			'dealStages',
			array(
				'd_groupid' => $pipeline_id,
				'limit'     => 100,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		return $response['dealStages'] ?? array();
	}

	/**
	 * Get all automations.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_automations(): array {
		$response = $this->request( 'GET', 'automations', array( 'limit' => 100 ) );

		if ( is_wp_error( $response ) ) {
			return array();
		}

		return $response['automations'] ?? array();
	}

	/**
	 * Create a deal.
	 *
	 * @since 1.0.0
	 * @param array $deal_data Deal data.
	 * @return int|\WP_Error Deal ID or error.
	 */
	public function create_deal( array $deal_data ) {
		$response = $this->request(
			'POST',
			'deals',
			array( 'deal' => $deal_data )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return (int) $response['deal']['id'];
	}

	/**
	 * Update a deal.
	 *
	 * @since 1.0.0
	 * @param int   $deal_id   Deal ID.
	 * @param array $deal_data Deal data.
	 * @return int|\WP_Error Deal ID or error.
	 */
	public function update_deal( int $deal_id, array $deal_data ) {
		$response = $this->request(
			'PUT',
			'deals/' . $deal_id,
			array( 'deal' => $deal_data )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return (int) $response['deal']['id'];
	}
}
