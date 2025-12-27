<?php
/**
 * Mailchimp API Service
 *
 * @package BookingX\MailchimpPro\Services
 * @since   1.0.0
 */

namespace BookingX\MailchimpPro\Services;

use BookingX\AddonSDK\Services\HttpClient;
use BookingX\AddonSDK\Services\EncryptionService;

/**
 * Mailchimp API service class.
 *
 * @since 1.0.0
 */
class MailchimpService {

	/**
	 * Addon instance.
	 *
	 * @var \BookingX\MailchimpPro\MailchimpProAddon
	 */
	protected $addon;

	/**
	 * HTTP client.
	 *
	 * @var HttpClient
	 */
	protected $http_client;

	/**
	 * Encryption service.
	 *
	 * @var EncryptionService
	 */
	protected $encryption;

	/**
	 * API datacenter.
	 *
	 * @var string
	 */
	protected $datacenter;

	/**
	 * Constructor.
	 *
	 * @param \BookingX\MailchimpPro\MailchimpProAddon $addon Addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon      = $addon;
		$this->encryption = new EncryptionService();

		$this->initialize_api();
	}

	/**
	 * Initialize API connection.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function initialize_api(): void {
		$api_key = $this->get_api_key();

		if ( empty( $api_key ) ) {
			return;
		}

		// Extract datacenter from API key
		$parts = explode( '-', $api_key );
		$this->datacenter = end( $parts );

		$base_url = "https://{$this->datacenter}.api.mailchimp.com/3.0/";

		$this->http_client = new HttpClient( $base_url );
		$this->http_client->set_headers( [
			'Authorization' => 'Basic ' . base64_encode( "anystring:{$api_key}" ),
			'Content-Type'  => 'application/json',
		] );
	}

	/**
	 * Get API key (decrypted).
	 *
	 * @since 1.0.0
	 * @return string
	 */
	protected function get_api_key(): string {
		$encrypted = $this->addon->get_setting( 'api_key', '' );

		if ( empty( $encrypted ) ) {
			return '';
		}

		if ( $this->encryption->is_encrypted( $encrypted ) ) {
			return $this->encryption->decrypt( $encrypted );
		}

		return $encrypted;
	}

	/**
	 * Test API connection.
	 *
	 * @since 1.0.0
	 * @param string $api_key API key to test (optional).
	 * @return array|\WP_Error Connection result or error.
	 */
	public function test_connection( string $api_key = '' ) {
		if ( empty( $api_key ) ) {
			$api_key = $this->get_api_key();
		}

		// Create temporary HTTP client for test
		$parts = explode( '-', $api_key );
		$datacenter = end( $parts );

		$client = new HttpClient( "https://{$datacenter}.api.mailchimp.com/3.0/" );
		$client->set_headers( [
			'Authorization' => 'Basic ' . base64_encode( "anystring:{$api_key}" ),
			'Content-Type'  => 'application/json',
		] );

		$response = $client->get( '' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return [
			'account_name' => $response['account_name'] ?? '',
			'account_id'   => $response['account_id'] ?? '',
		];
	}

	/**
	 * Get all lists/audiences.
	 *
	 * @since 1.0.0
	 * @param int $count Number of lists to retrieve.
	 * @return array|\WP_Error Lists array or error.
	 */
	public function get_lists( int $count = 100 ) {
		if ( ! $this->http_client ) {
			return new \WP_Error( 'no_api_key', __( 'API key not configured.', 'bkx-mailchimp-pro' ) );
		}

		// Check cache
		$cached = get_transient( 'bkx_mailchimp_pro_lists' );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->http_client->get( "lists?count={$count}" );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$lists = [];
		if ( isset( $response['lists'] ) && is_array( $response['lists'] ) ) {
			foreach ( $response['lists'] as $list ) {
				$lists[] = [
					'id'   => $list['id'],
					'name' => $list['name'],
				];
			}
		}

		// Cache for 1 hour
		set_transient( 'bkx_mailchimp_pro_lists', $lists, HOUR_IN_SECONDS );

		return $lists;
	}

	/**
	 * Get tags for a list.
	 *
	 * @since 1.0.0
	 * @param string $list_id List ID.
	 * @return array|\WP_Error Tags array or error.
	 */
	public function get_tags( string $list_id ) {
		if ( ! $this->http_client ) {
			return new \WP_Error( 'no_api_key', __( 'API key not configured.', 'bkx-mailchimp-pro' ) );
		}

		$response = $this->http_client->get( "lists/{$list_id}/tag-search" );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$tags = [];
		if ( isset( $response['tags'] ) && is_array( $response['tags'] ) ) {
			foreach ( $response['tags'] as $tag ) {
				$tags[] = [
					'id'   => $tag['id'],
					'name' => $tag['name'],
				];
			}
		}

		return $tags;
	}

	/**
	 * Add or update a subscriber.
	 *
	 * @since 1.0.0
	 * @param string $list_id       List ID.
	 * @param string $email         Email address.
	 * @param array  $merge_fields  Merge fields.
	 * @param array  $tags          Tags to apply.
	 * @param bool   $double_optin  Whether to use double opt-in.
	 * @return array|\WP_Error Response or error.
	 */
	public function add_or_update_subscriber( string $list_id, string $email, array $merge_fields = [], array $tags = [], bool $double_optin = true ) {
		if ( ! $this->http_client ) {
			return new \WP_Error( 'no_api_key', __( 'API key not configured.', 'bkx-mailchimp-pro' ) );
		}

		$subscriber_hash = md5( strtolower( $email ) );

		$data = [
			'email_address' => $email,
			'status_if_new' => $double_optin ? 'pending' : 'subscribed',
			'merge_fields'  => $merge_fields,
			'tags'          => $tags,
		];

		$response = $this->http_client->put(
			"lists/{$list_id}/members/{$subscriber_hash}",
			$data
		);

		return $response;
	}

	/**
	 * Add tags to a subscriber.
	 *
	 * @since 1.0.0
	 * @param string $list_id List ID.
	 * @param string $email   Email address.
	 * @param array  $tags    Tags to add.
	 * @return array|\WP_Error Response or error.
	 */
	public function add_tags( string $list_id, string $email, array $tags ) {
		if ( ! $this->http_client ) {
			return new \WP_Error( 'no_api_key', __( 'API key not configured.', 'bkx-mailchimp-pro' ) );
		}

		$subscriber_hash = md5( strtolower( $email ) );

		$formatted_tags = array_map( function( $tag ) {
			return [
				'name'   => $tag,
				'status' => 'active',
			];
		}, $tags );

		$data = [
			'tags' => $formatted_tags,
		];

		$response = $this->http_client->post(
			"lists/{$list_id}/members/{$subscriber_hash}/tags",
			$data
		);

		return $response;
	}

	/**
	 * Remove tags from a subscriber.
	 *
	 * @since 1.0.0
	 * @param string $list_id List ID.
	 * @param string $email   Email address.
	 * @param array  $tags    Tags to remove.
	 * @return array|\WP_Error Response or error.
	 */
	public function remove_tags( string $list_id, string $email, array $tags ) {
		if ( ! $this->http_client ) {
			return new \WP_Error( 'no_api_key', __( 'API key not configured.', 'bkx-mailchimp-pro' ) );
		}

		$subscriber_hash = md5( strtolower( $email ) );

		$formatted_tags = array_map( function( $tag ) {
			return [
				'name'   => $tag,
				'status' => 'inactive',
			];
		}, $tags );

		$data = [
			'tags' => $formatted_tags,
		];

		$response = $this->http_client->post(
			"lists/{$list_id}/members/{$subscriber_hash}/tags",
			$data
		);

		return $response;
	}

	/**
	 * Update subscriber merge fields.
	 *
	 * @since 1.0.0
	 * @param string $list_id      List ID.
	 * @param string $email        Email address.
	 * @param array  $merge_fields Merge fields to update.
	 * @return array|\WP_Error Response or error.
	 */
	public function update_merge_fields( string $list_id, string $email, array $merge_fields ) {
		if ( ! $this->http_client ) {
			return new \WP_Error( 'no_api_key', __( 'API key not configured.', 'bkx-mailchimp-pro' ) );
		}

		$subscriber_hash = md5( strtolower( $email ) );

		$data = [
			'merge_fields' => $merge_fields,
		];

		$response = $this->http_client->patch(
			"lists/{$list_id}/members/{$subscriber_hash}",
			$data
		);

		return $response;
	}

	/**
	 * Unsubscribe a member.
	 *
	 * @since 1.0.0
	 * @param string $list_id List ID.
	 * @param string $email   Email address.
	 * @return array|\WP_Error Response or error.
	 */
	public function unsubscribe( string $list_id, string $email ) {
		if ( ! $this->http_client ) {
			return new \WP_Error( 'no_api_key', __( 'API key not configured.', 'bkx-mailchimp-pro' ) );
		}

		$subscriber_hash = md5( strtolower( $email ) );

		$data = [
			'status' => 'unsubscribed',
		];

		$response = $this->http_client->patch(
			"lists/{$list_id}/members/{$subscriber_hash}",
			$data
		);

		return $response;
	}
}
