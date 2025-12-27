<?php
/**
 * Google API Service
 *
 * Handles communication with Google Calendar API.
 *
 * @package BookingX\GoogleCalendar\Services
 * @since   1.0.0
 */

namespace BookingX\GoogleCalendar\Services;

use BookingX\GoogleCalendar\GoogleCalendarAddon;
use BookingX\AddonSDK\Services\EncryptionService;
use WP_Error;

/**
 * Google API service class.
 *
 * @since 1.0.0
 */
class GoogleApiService {

	/**
	 * Addon instance.
	 *
	 * @var GoogleCalendarAddon
	 */
	protected GoogleCalendarAddon $addon;

	/**
	 * Google OAuth URL.
	 *
	 * @var string
	 */
	protected string $oauth_url = 'https://oauth2.googleapis.com/token';

	/**
	 * Google Calendar API URL.
	 *
	 * @var string
	 */
	protected string $api_url = 'https://www.googleapis.com/calendar/v3';

	/**
	 * Connections table.
	 *
	 * @var string
	 */
	protected string $connections_table;

	/**
	 * Constructor.
	 *
	 * @param GoogleCalendarAddon $addon Addon instance.
	 */
	public function __construct( GoogleCalendarAddon $addon ) {
		global $wpdb;

		$this->addon             = $addon;
		$this->connections_table = $wpdb->prefix . 'bkx_google_connections';
	}

	/**
	 * Check if connected to Google.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		$connection = $this->get_main_connection();
		return ! empty( $connection ) && ! empty( $connection->access_token );
	}

	/**
	 * Check if a staff member is connected.
	 *
	 * @param int $staff_id Staff ID.
	 * @return bool
	 */
	public function is_staff_connected( int $staff_id ): bool {
		$connection = $this->get_staff_connection( $staff_id );
		return ! empty( $connection ) && ! empty( $connection->access_token );
	}

	/**
	 * Get main account connection.
	 *
	 * @return object|null
	 */
	public function get_main_connection(): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d AND staff_id IS NULL LIMIT 1',
				$this->connections_table,
				get_current_user_id()
			)
		);
	}

	/**
	 * Get staff connection.
	 *
	 * @param int $staff_id Staff ID.
	 * @return object|null
	 */
	public function get_staff_connection( int $staff_id ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE staff_id = %d LIMIT 1',
				$this->connections_table,
				$staff_id
			)
		);
	}

	/**
	 * Exchange authorization code for tokens.
	 *
	 * @param string   $code Authorization code.
	 * @param int|null $staff_id Optional staff ID.
	 * @return array|WP_Error Tokens or error.
	 */
	public function exchange_code( string $code, ?int $staff_id = null ) {
		$client_id     = $this->get_decrypted_setting( 'client_id' );
		$client_secret = $this->get_decrypted_setting( 'client_secret' );
		$redirect_uri  = $this->get_redirect_uri( $staff_id );

		$response = wp_remote_post(
			$this->oauth_url,
			array(
				'body' => array(
					'code'          => $code,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'redirect_uri'  => $redirect_uri,
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'oauth_error', $body['error_description'] ?? $body['error'] );
		}

		return $body;
	}

	/**
	 * Refresh access token.
	 *
	 * @param string $refresh_token Refresh token.
	 * @return array|WP_Error New tokens or error.
	 */
	public function refresh_token( string $refresh_token ) {
		$client_id     = $this->get_decrypted_setting( 'client_id' );
		$client_secret = $this->get_decrypted_setting( 'client_secret' );

		$response = wp_remote_post(
			$this->oauth_url,
			array(
				'body' => array(
					'refresh_token' => $refresh_token,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'refresh_error', $body['error_description'] ?? $body['error'] );
		}

		return $body;
	}

	/**
	 * Get valid access token (refresh if needed).
	 *
	 * @param object $connection Connection object.
	 * @return string|WP_Error Access token or error.
	 */
	public function get_valid_token( object $connection ) {
		// Check if token is expired (with 5 min buffer).
		$expires_at = strtotime( $connection->token_expires_at );
		$now        = time();

		if ( $expires_at - 300 < $now ) {
			// Token is expired or about to expire - refresh it.
			$refresh_token = EncryptionService::decrypt( $connection->refresh_token );
			$new_tokens    = $this->refresh_token( $refresh_token );

			if ( is_wp_error( $new_tokens ) ) {
				return $new_tokens;
			}

			// Update stored tokens.
			$this->update_connection_tokens( $connection->id, $new_tokens );

			return $new_tokens['access_token'];
		}

		return EncryptionService::decrypt( $connection->access_token );
	}

	/**
	 * Update connection tokens.
	 *
	 * @param int   $connection_id Connection ID.
	 * @param array $tokens Token data.
	 * @return void
	 */
	protected function update_connection_tokens( int $connection_id, array $tokens ): void {
		global $wpdb;

		$update_data = array(
			'access_token'     => EncryptionService::encrypt( $tokens['access_token'] ),
			'token_expires_at' => gmdate( 'Y-m-d H:i:s', time() + ( $tokens['expires_in'] ?? 3600 ) ),
		);

		if ( ! empty( $tokens['refresh_token'] ) ) {
			$update_data['refresh_token'] = EncryptionService::encrypt( $tokens['refresh_token'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->connections_table,
			$update_data,
			array( 'id' => $connection_id )
		);
	}

	/**
	 * Store connection.
	 *
	 * @param array    $tokens Token data.
	 * @param int|null $staff_id Optional staff ID.
	 * @return int|WP_Error Connection ID or error.
	 */
	public function store_connection( array $tokens, ?int $staff_id = null ) {
		global $wpdb;

		// Get user info from Google.
		$user_info = $this->get_user_info( $tokens['access_token'] );

		if ( is_wp_error( $user_info ) ) {
			return $user_info;
		}

		// Check for existing connection.
		$existing = $staff_id
			? $this->get_staff_connection( $staff_id )
			: $this->get_main_connection();

		$data = array(
			'access_token'     => EncryptionService::encrypt( $tokens['access_token'] ),
			'refresh_token'    => EncryptionService::encrypt( $tokens['refresh_token'] ),
			'token_expires_at' => gmdate( 'Y-m-d H:i:s', time() + ( $tokens['expires_in'] ?? 3600 ) ),
			'google_email'     => $user_info['email'] ?? '',
		);

		if ( $existing ) {
			// Update existing connection.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $this->connections_table, $data, array( 'id' => $existing->id ) );
			return $existing->id;
		}

		// Create new connection.
		$data['user_id']  = get_current_user_id();
		$data['staff_id'] = $staff_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert( $this->connections_table, $data );

		return $wpdb->insert_id;
	}

	/**
	 * Remove connection.
	 *
	 * @param int|null $staff_id Optional staff ID.
	 * @return bool
	 */
	public function remove_connection( ?int $staff_id = null ): bool {
		global $wpdb;

		if ( $staff_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return (bool) $wpdb->delete( $this->connections_table, array( 'staff_id' => $staff_id ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->delete(
			$this->connections_table,
			array(
				'user_id'  => get_current_user_id(),
				'staff_id' => null,
			)
		);
	}

	/**
	 * Get user info from Google.
	 *
	 * @param string $access_token Access token.
	 * @return array|WP_Error User info or error.
	 */
	public function get_user_info( string $access_token ) {
		$response = wp_remote_get(
			'https://www.googleapis.com/oauth2/v2/userinfo',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'api_error', $body['error']['message'] ?? 'Unknown error' );
		}

		return $body;
	}

	/**
	 * Test the connection.
	 *
	 * @return array|WP_Error Connection info or error.
	 */
	public function test_connection() {
		$connection = $this->get_main_connection();

		if ( ! $connection ) {
			return new WP_Error( 'not_connected', __( 'Not connected to Google.', 'bkx-google-calendar' ) );
		}

		$access_token = $this->get_valid_token( $connection );

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		// Get calendar list.
		$calendars = $this->get_calendars( $access_token );

		if ( is_wp_error( $calendars ) ) {
			return $calendars;
		}

		return array(
			'email'     => $connection->google_email,
			'calendars' => $calendars,
		);
	}

	/**
	 * Get list of calendars.
	 *
	 * @param string $access_token Access token.
	 * @return array|WP_Error Calendars or error.
	 */
	public function get_calendars( string $access_token ) {
		$response = wp_remote_get(
			$this->api_url . '/users/me/calendarList',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'api_error', $body['error']['message'] ?? 'Unknown error' );
		}

		return $body['items'] ?? array();
	}

	/**
	 * Create calendar event.
	 *
	 * @param string $access_token Access token.
	 * @param string $calendar_id Calendar ID.
	 * @param array  $event_data Event data.
	 * @return array|WP_Error Created event or error.
	 */
	public function create_event( string $access_token, string $calendar_id, array $event_data ) {
		$response = wp_remote_post(
			$this->api_url . '/calendars/' . rawurlencode( $calendar_id ) . '/events',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $event_data ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'api_error', $body['error']['message'] ?? 'Unknown error' );
		}

		return $body;
	}

	/**
	 * Update calendar event.
	 *
	 * @param string $access_token Access token.
	 * @param string $calendar_id Calendar ID.
	 * @param string $event_id Event ID.
	 * @param array  $event_data Event data.
	 * @return array|WP_Error Updated event or error.
	 */
	public function update_event( string $access_token, string $calendar_id, string $event_id, array $event_data ) {
		$response = wp_remote_request(
			$this->api_url . '/calendars/' . rawurlencode( $calendar_id ) . '/events/' . rawurlencode( $event_id ),
			array(
				'method'  => 'PUT',
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $event_data ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'api_error', $body['error']['message'] ?? 'Unknown error' );
		}

		return $body;
	}

	/**
	 * Delete calendar event.
	 *
	 * @param string $access_token Access token.
	 * @param string $calendar_id Calendar ID.
	 * @param string $event_id Event ID.
	 * @return bool|WP_Error Success or error.
	 */
	public function delete_event( string $access_token, string $calendar_id, string $event_id ) {
		$response = wp_remote_request(
			$this->api_url . '/calendars/' . rawurlencode( $calendar_id ) . '/events/' . rawurlencode( $event_id ),
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 204 === $code || 200 === $code ) {
			return true;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'api_error', $body['error']['message'] ?? 'Unknown error' );
		}

		return true;
	}

	/**
	 * Get free/busy information.
	 *
	 * @param string $access_token Access token.
	 * @param string $calendar_id Calendar ID.
	 * @param string $time_min Start time (ISO 8601).
	 * @param string $time_max End time (ISO 8601).
	 * @return array|WP_Error Busy times or error.
	 */
	public function get_freebusy( string $access_token, string $calendar_id, string $time_min, string $time_max ) {
		$response = wp_remote_post(
			$this->api_url . '/freeBusy',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'timeMin' => $time_min,
						'timeMax' => $time_max,
						'items'   => array(
							array( 'id' => $calendar_id ),
						),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new WP_Error( 'api_error', $body['error']['message'] ?? 'Unknown error' );
		}

		return $body['calendars'][ $calendar_id ]['busy'] ?? array();
	}

	/**
	 * Get events for sync.
	 *
	 * @param string      $access_token Access token.
	 * @param string      $calendar_id Calendar ID.
	 * @param string|null $sync_token Optional sync token.
	 * @return array|WP_Error Events or error.
	 */
	public function get_events( string $access_token, string $calendar_id, ?string $sync_token = null ) {
		$params = array();

		if ( $sync_token ) {
			$params['syncToken'] = $sync_token;
		} else {
			// Full sync - get events from now onwards.
			$params['timeMin']      = gmdate( 'c' );
			$params['maxResults']   = 250;
			$params['singleEvents'] = 'true';
		}

		$response = wp_remote_get(
			add_query_arg( $params, $this->api_url . '/calendars/' . rawurlencode( $calendar_id ) . '/events' ),
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			// Sync token invalid - need full sync.
			if ( 410 === $body['error']['code'] && $sync_token ) {
				return $this->get_events( $access_token, $calendar_id, null );
			}

			return new WP_Error( 'api_error', $body['error']['message'] ?? 'Unknown error' );
		}

		return array(
			'items'          => $body['items'] ?? array(),
			'nextSyncToken'  => $body['nextSyncToken'] ?? null,
			'nextPageToken'  => $body['nextPageToken'] ?? null,
		);
	}

	/**
	 * Get OAuth redirect URI.
	 *
	 * @param int|null $staff_id Optional staff ID.
	 * @return string
	 */
	public function get_redirect_uri( ?int $staff_id = null ): string {
		$base_url = admin_url( 'admin.php' );

		$params = array(
			'page'     => 'bkx-google-calendar',
			'action'   => 'oauth_callback',
		);

		if ( $staff_id ) {
			$params['staff_id'] = $staff_id;
		}

		return add_query_arg( $params, $base_url );
	}

	/**
	 * Get decrypted setting.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	protected function get_decrypted_setting( string $key ): string {
		$value = $this->addon->get_setting( $key, '' );

		if ( empty( $value ) ) {
			return '';
		}

		return EncryptionService::decrypt( $value );
	}
}
