<?php
/**
 * Facebook Graph API wrapper.
 *
 * @package BookingX\FacebookBooking\Services
 */

namespace BookingX\FacebookBooking\Services;

defined( 'ABSPATH' ) || exit;

/**
 * FacebookApi class.
 *
 * Handles all Facebook Graph API communications.
 */
class FacebookApi {

	/**
	 * Graph API base URL.
	 *
	 * @var string
	 */
	const API_BASE = 'https://graph.facebook.com/v18.0';

	/**
	 * App ID.
	 *
	 * @var string
	 */
	private $app_id;

	/**
	 * App Secret.
	 *
	 * @var string
	 */
	private $app_secret;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$settings         = get_option( 'bkx_fb_booking_settings', array() );
		$this->app_id     = $settings['app_id'] ?? '';
		$this->app_secret = $settings['app_secret'] ?? '';
	}

	/**
	 * Make a GET request to the Graph API.
	 *
	 * @param string $endpoint     API endpoint.
	 * @param array  $params       Query parameters.
	 * @param string $access_token Access token.
	 * @return array|WP_Error
	 */
	public function get( $endpoint, $params = array(), $access_token = null ) {
		return $this->request( 'GET', $endpoint, $params, $access_token );
	}

	/**
	 * Make a POST request to the Graph API.
	 *
	 * @param string $endpoint     API endpoint.
	 * @param array  $data         POST data.
	 * @param string $access_token Access token.
	 * @return array|WP_Error
	 */
	public function post( $endpoint, $data = array(), $access_token = null ) {
		return $this->request( 'POST', $endpoint, $data, $access_token );
	}

	/**
	 * Make a DELETE request to the Graph API.
	 *
	 * @param string $endpoint     API endpoint.
	 * @param string $access_token Access token.
	 * @return array|WP_Error
	 */
	public function delete( $endpoint, $access_token = null ) {
		return $this->request( 'DELETE', $endpoint, array(), $access_token );
	}

	/**
	 * Make a request to the Graph API.
	 *
	 * @param string $method       HTTP method.
	 * @param string $endpoint     API endpoint.
	 * @param array  $data         Request data.
	 * @param string $access_token Access token.
	 * @return array|WP_Error
	 */
	private function request( $method, $endpoint, $data = array(), $access_token = null ) {
		$url = self::API_BASE . '/' . ltrim( $endpoint, '/' );

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Accept' => 'application/json',
			),
		);

		if ( $access_token ) {
			$data['access_token'] = $access_token;
		}

		if ( 'GET' === $method ) {
			$url = add_query_arg( $data, $url );
		} else {
			$args['body'] = $data;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'API request failed', array(
				'endpoint' => $endpoint,
				'error'    => $response->get_error_message(),
			) );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 || isset( $body['error'] ) ) {
			$error_message = $body['error']['message'] ?? 'Unknown API error';
			$error_code    = $body['error']['code'] ?? $code;

			$this->log_error( 'API error response', array(
				'endpoint' => $endpoint,
				'code'     => $error_code,
				'message'  => $error_message,
			) );

			return new \WP_Error( 'fb_api_error', $error_message, array( 'code' => $error_code ) );
		}

		return $body;
	}

	/**
	 * Exchange authorization code for access token.
	 *
	 * @param string $code         Authorization code.
	 * @param string $redirect_uri Redirect URI.
	 * @return array|WP_Error
	 */
	public function exchange_code_for_token( $code, $redirect_uri ) {
		return $this->get( 'oauth/access_token', array(
			'client_id'     => $this->app_id,
			'client_secret' => $this->app_secret,
			'code'          => $code,
			'redirect_uri'  => $redirect_uri,
		) );
	}

	/**
	 * Get long-lived access token.
	 *
	 * @param string $short_lived_token Short-lived token.
	 * @return array|WP_Error
	 */
	public function get_long_lived_token( $short_lived_token ) {
		return $this->get( 'oauth/access_token', array(
			'grant_type'        => 'fb_exchange_token',
			'client_id'         => $this->app_id,
			'client_secret'     => $this->app_secret,
			'fb_exchange_token' => $short_lived_token,
		) );
	}

	/**
	 * Get user's managed pages.
	 *
	 * @param string $user_access_token User access token.
	 * @return array|WP_Error
	 */
	public function get_user_pages( $user_access_token ) {
		return $this->get( 'me/accounts', array(
			'fields' => 'id,name,category,access_token,picture{url}',
		), $user_access_token );
	}

	/**
	 * Get page details.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $access_token Page access token.
	 * @return array|WP_Error
	 */
	public function get_page( $page_id, $access_token ) {
		return $this->get( $page_id, array(
			'fields' => 'id,name,category,about,phone,website,emails,location,hours,link,picture{url}',
		), $access_token );
	}

	/**
	 * Set up page call-to-action button.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $access_token Page access token.
	 * @param string $button_url   Button URL.
	 * @return array|WP_Error
	 */
	public function set_page_cta( $page_id, $access_token, $button_url ) {
		return $this->post( "{$page_id}/call_to_actions", array(
			'type'  => 'BOOK_NOW',
			'value' => array(
				'link' => $button_url,
			),
		), $access_token );
	}

	/**
	 * Subscribe page to webhooks.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $access_token Page access token.
	 * @return array|WP_Error
	 */
	public function subscribe_page_webhooks( $page_id, $access_token ) {
		return $this->post( "{$page_id}/subscribed_apps", array(
			'subscribed_fields' => 'messages,messaging_postbacks,messaging_optins,feed',
		), $access_token );
	}

	/**
	 * Unsubscribe page from webhooks.
	 *
	 * @param string $page_id      Page ID.
	 * @param string $access_token Page access token.
	 * @return array|WP_Error
	 */
	public function unsubscribe_page_webhooks( $page_id, $access_token ) {
		return $this->delete( "{$page_id}/subscribed_apps", $access_token );
	}

	/**
	 * Send message via Messenger.
	 *
	 * @param string $recipient_id Recipient PSID.
	 * @param array  $message      Message data.
	 * @param string $access_token Page access token.
	 * @return array|WP_Error
	 */
	public function send_message( $recipient_id, $message, $access_token ) {
		return $this->post( 'me/messages', array(
			'recipient' => array( 'id' => $recipient_id ),
			'message'   => $message,
		), $access_token );
	}

	/**
	 * Send quick reply message.
	 *
	 * @param string $recipient_id Recipient PSID.
	 * @param string $text         Message text.
	 * @param array  $quick_replies Quick reply buttons.
	 * @param string $access_token Page access token.
	 * @return array|WP_Error
	 */
	public function send_quick_replies( $recipient_id, $text, $quick_replies, $access_token ) {
		$message = array(
			'text'          => $text,
			'quick_replies' => array_map( function( $reply ) {
				return array(
					'content_type' => 'text',
					'title'        => $reply['title'],
					'payload'      => $reply['payload'],
				);
			}, $quick_replies ),
		);

		return $this->send_message( $recipient_id, $message, $access_token );
	}

	/**
	 * Send button template message.
	 *
	 * @param string $recipient_id Recipient PSID.
	 * @param string $text         Message text.
	 * @param array  $buttons      Buttons.
	 * @param string $access_token Page access token.
	 * @return array|WP_Error
	 */
	public function send_buttons( $recipient_id, $text, $buttons, $access_token ) {
		$message = array(
			'attachment' => array(
				'type'    => 'template',
				'payload' => array(
					'template_type' => 'button',
					'text'          => $text,
					'buttons'       => $buttons,
				),
			),
		);

		return $this->send_message( $recipient_id, $message, $access_token );
	}

	/**
	 * Send generic template (carousel).
	 *
	 * @param string $recipient_id Recipient PSID.
	 * @param array  $elements     Carousel elements.
	 * @param string $access_token Page access token.
	 * @return array|WP_Error
	 */
	public function send_carousel( $recipient_id, $elements, $access_token ) {
		$message = array(
			'attachment' => array(
				'type'    => 'template',
				'payload' => array(
					'template_type' => 'generic',
					'elements'      => $elements,
				),
			),
		);

		return $this->send_message( $recipient_id, $message, $access_token );
	}

	/**
	 * Send receipt template.
	 *
	 * @param string $recipient_id Recipient PSID.
	 * @param array  $receipt      Receipt data.
	 * @param string $access_token Page access token.
	 * @return array|WP_Error
	 */
	public function send_receipt( $recipient_id, $receipt, $access_token ) {
		$message = array(
			'attachment' => array(
				'type'    => 'template',
				'payload' => array_merge(
					array( 'template_type' => 'receipt' ),
					$receipt
				),
			),
		);

		return $this->send_message( $recipient_id, $message, $access_token );
	}

	/**
	 * Get user profile.
	 *
	 * @param string $psid         Page-scoped user ID.
	 * @param string $access_token Page access token.
	 * @return array|WP_Error
	 */
	public function get_user_profile( $psid, $access_token ) {
		return $this->get( $psid, array(
			'fields' => 'first_name,last_name,profile_pic,locale,timezone',
		), $access_token );
	}

	/**
	 * Debug token.
	 *
	 * @param string $token Token to debug.
	 * @return array|WP_Error
	 */
	public function debug_token( $token ) {
		$app_token = $this->app_id . '|' . $this->app_secret;

		return $this->get( 'debug_token', array(
			'input_token'  => $token,
			'access_token' => $app_token,
		) );
	}

	/**
	 * Verify webhook request signature.
	 *
	 * @param string $payload   Raw request payload.
	 * @param string $signature X-Hub-Signature-256 header.
	 * @return bool
	 */
	public function verify_webhook_signature( $payload, $signature ) {
		if ( empty( $signature ) || empty( $this->app_secret ) ) {
			return false;
		}

		$expected = 'sha256=' . hash_hmac( 'sha256', $payload, $this->app_secret );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Get OAuth login URL.
	 *
	 * @param string $redirect_uri Redirect URI.
	 * @param string $state        State parameter.
	 * @param array  $scopes       Permission scopes.
	 * @return string
	 */
	public function get_login_url( $redirect_uri, $state, $scopes = array() ) {
		$default_scopes = array(
			'pages_show_list',
			'pages_read_engagement',
			'pages_manage_metadata',
			'pages_messaging',
		);

		$scopes = array_merge( $default_scopes, $scopes );

		$params = array(
			'client_id'     => $this->app_id,
			'redirect_uri'  => $redirect_uri,
			'state'         => $state,
			'scope'         => implode( ',', $scopes ),
			'response_type' => 'code',
		);

		return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query( $params );
	}

	/**
	 * Check if API is configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->app_id ) && ! empty( $this->app_secret );
	}

	/**
	 * Log error.
	 *
	 * @param string $message Error message.
	 * @param array  $context Error context.
	 */
	private function log_error( $message, $context = array() ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf(
				'[BKX Facebook Booking] %s: %s',
				$message,
				wp_json_encode( $context )
			) );
		}
	}
}
