<?php
/**
 * Microsoft Graph API Service
 *
 * @package BookingX\OutlookCalendar
 * @since   1.0.0
 */

namespace BookingX\OutlookCalendar\Services;

use BookingX\OutlookCalendar\OutlookCalendarAddon;

/**
 * Class MicrosoftGraphAPI
 *
 * Handles communication with Microsoft Graph API.
 *
 * @since 1.0.0
 */
class MicrosoftGraphAPI {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private const API_BASE = 'https://graph.microsoft.com/v1.0';

	/**
	 * Addon instance.
	 *
	 * @var OutlookCalendarAddon
	 */
	private OutlookCalendarAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param OutlookCalendarAddon $addon Addon instance.
	 */
	public function __construct( OutlookCalendarAddon $addon ) {
		$this->addon = $addon;
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
		$access_token = $this->get_access_token();

		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$url = self::API_BASE . '/' . ltrim( $endpoint, '/' );

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PATCH', 'PUT' ), true ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->addon->log( sprintf( 'Graph API error: %s', $response->get_error_message() ), 'error' );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		// Handle 204 No Content.
		if ( 204 === $code ) {
			return array( 'success' => true );
		}

		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			$error_msg = $data['error']['message'] ?? __( 'Unknown API error', 'bkx-outlook-calendar' );
			$this->addon->log( sprintf( 'Graph API error %d: %s', $code, $error_msg ), 'error' );

			return new \WP_Error( 'api_error', $error_msg, array( 'status' => $code ) );
		}

		return $data;
	}

	/**
	 * Get access token, refreshing if necessary.
	 *
	 * @since 1.0.0
	 * @return string|\WP_Error
	 */
	private function get_access_token() {
		$token_expires = $this->addon->get_setting( 'token_expires', 0 );

		// Refresh if token expires in less than 5 minutes.
		if ( $token_expires < time() + 300 ) {
			$refreshed = $this->refresh_token();
			if ( is_wp_error( $refreshed ) ) {
				return $refreshed;
			}
		}

		$encrypted_token = $this->addon->get_setting( 'access_token', '' );
		return $this->addon->get_encryption()->decrypt( $encrypted_token );
	}

	/**
	 * Refresh access token.
	 *
	 * @since 1.0.0
	 * @return bool|\WP_Error
	 */
	private function refresh_token() {
		$refresh_token = $this->addon->get_encryption()->decrypt(
			$this->addon->get_setting( 'refresh_token', '' )
		);

		if ( empty( $refresh_token ) ) {
			return new \WP_Error( 'no_refresh_token', __( 'No refresh token available', 'bkx-outlook-calendar' ) );
		}

		$client_id     = $this->addon->get_setting( 'client_id', '' );
		$client_secret = $this->addon->get_encryption()->decrypt(
			$this->addon->get_setting( 'client_secret', '' )
		);

		$response = wp_remote_post(
			'https://login.microsoftonline.com/common/oauth2/v2.0/token',
			array(
				'body' => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
					'scope'         => 'offline_access Calendars.ReadWrite User.Read',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			$this->addon->log( sprintf( 'Token refresh failed: %s', $body['error_description'] ?? $body['error'] ), 'error' );
			return new \WP_Error( 'refresh_failed', $body['error_description'] ?? $body['error'] );
		}

		// Store new tokens.
		$this->addon->update_setting( 'access_token', $this->addon->get_encryption()->encrypt( $body['access_token'] ) );

		if ( isset( $body['refresh_token'] ) ) {
			$this->addon->update_setting( 'refresh_token', $this->addon->get_encryption()->encrypt( $body['refresh_token'] ) );
		}

		$this->addon->update_setting( 'token_expires', time() + $body['expires_in'] );

		return true;
	}

	/**
	 * Get user info.
	 *
	 * @since 1.0.0
	 * @return array|\WP_Error
	 */
	public function get_user_info() {
		return $this->request( 'GET', 'me' );
	}

	/**
	 * Get calendars.
	 *
	 * @since 1.0.0
	 * @return array|\WP_Error
	 */
	public function get_calendars() {
		$response = $this->request( 'GET', 'me/calendars' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['value'] ?? array();
	}

	/**
	 * Get calendar events.
	 *
	 * @since 1.0.0
	 * @param string $calendar_id Calendar ID.
	 * @param string $start_date  Start date (ISO format).
	 * @param string $end_date    End date (ISO format).
	 * @return array|\WP_Error
	 */
	public function get_events( string $calendar_id, string $start_date, string $end_date ) {
		$endpoint = sprintf(
			'me/calendars/%s/calendarView?startDateTime=%s&endDateTime=%s&$top=100',
			$calendar_id,
			rawurlencode( $start_date ),
			rawurlencode( $end_date )
		);

		$response = $this->request( 'GET', $endpoint );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response['value'] ?? array();
	}

	/**
	 * Create calendar event.
	 *
	 * @since 1.0.0
	 * @param string $calendar_id Calendar ID.
	 * @param array  $event_data  Event data.
	 * @return array|\WP_Error
	 */
	public function create_event( string $calendar_id, array $event_data ) {
		return $this->request( 'POST', "me/calendars/{$calendar_id}/events", $event_data );
	}

	/**
	 * Update calendar event.
	 *
	 * @since 1.0.0
	 * @param string $event_id   Event ID.
	 * @param array  $event_data Event data.
	 * @return array|\WP_Error
	 */
	public function update_event( string $event_id, array $event_data ) {
		return $this->request( 'PATCH', "me/events/{$event_id}", $event_data );
	}

	/**
	 * Delete calendar event.
	 *
	 * @since 1.0.0
	 * @param string $event_id Event ID.
	 * @return array|\WP_Error
	 */
	public function delete_event( string $event_id ) {
		return $this->request( 'DELETE', "me/events/{$event_id}" );
	}

	/**
	 * Get free/busy schedule.
	 *
	 * @since 1.0.0
	 * @param string $start_date Start date (ISO format).
	 * @param string $end_date   End date (ISO format).
	 * @return array|\WP_Error
	 */
	public function get_schedule( string $start_date, string $end_date ) {
		$user_email = $this->addon->get_setting( 'user_email', '' );

		$data = array(
			'schedules'        => array( $user_email ),
			'startTime'        => array(
				'dateTime' => $start_date,
				'timeZone' => wp_timezone_string(),
			),
			'endTime'          => array(
				'dateTime' => $end_date,
				'timeZone' => wp_timezone_string(),
			),
			'availabilityViewInterval' => 30,
		);

		return $this->request( 'POST', 'me/calendar/getSchedule', $data );
	}
}
