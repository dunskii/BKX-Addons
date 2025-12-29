<?php
/**
 * Slack API wrapper.
 *
 * @package BookingX\Slack\Services
 */

namespace BookingX\Slack\Services;

defined( 'ABSPATH' ) || exit;

/**
 * SlackApi class.
 *
 * Handles all Slack API communications.
 */
class SlackApi {

	/**
	 * Slack API base URL.
	 *
	 * @var string
	 */
	const API_BASE = 'https://slack.com/api';

	/**
	 * OAuth authorization URL.
	 *
	 * @var string
	 */
	const OAUTH_AUTHORIZE = 'https://slack.com/oauth/v2/authorize';

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
		$this->settings = get_option( 'bkx_slack_settings', array() );
	}

	/**
	 * Make an API request.
	 *
	 * @param string $method     API method.
	 * @param array  $params     Request parameters.
	 * @param string $token      Access token.
	 * @param string $http_method HTTP method (GET or POST).
	 * @return array|WP_Error
	 */
	public function request( $method, $params = array(), $token = null, $http_method = 'POST' ) {
		$url = self::API_BASE . '/' . $method;

		$headers = array(
			'Content-Type' => 'application/json; charset=utf-8',
		);

		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$args = array(
			'method'  => $http_method,
			'headers' => $headers,
			'timeout' => 30,
		);

		if ( 'POST' === $http_method ) {
			$args['body'] = wp_json_encode( $params );
		} else {
			$url = add_query_arg( $params, $url );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'API request failed', array(
				'method' => $method,
				'error'  => $response->get_error_message(),
			) );
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ok'] ) ) {
			$error = $body['error'] ?? 'Unknown Slack API error';
			$this->log_error( 'API error', array(
				'method' => $method,
				'error'  => $error,
			) );
			return new \WP_Error( 'slack_api_error', $error );
		}

		return $body;
	}

	/**
	 * Exchange authorization code for access token.
	 *
	 * @param string $code Authorization code.
	 * @return array|WP_Error
	 */
	public function exchange_code_for_token( $code ) {
		$response = wp_remote_post( self::API_BASE . '/oauth.v2.access', array(
			'body' => array(
				'client_id'     => $this->settings['client_id'] ?? '',
				'client_secret' => $this->settings['client_secret'] ?? '',
				'code'          => $code,
				'redirect_uri'  => $this->get_oauth_redirect_uri(),
			),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ok'] ) ) {
			return new \WP_Error( 'oauth_error', $body['error'] ?? 'OAuth failed' );
		}

		return $body;
	}

	/**
	 * Get OAuth authorization URL.
	 *
	 * @return string
	 */
	public function get_oauth_url() {
		$scopes = array(
			'chat:write',
			'chat:write.public',
			'channels:read',
			'commands',
			'incoming-webhook',
			'users:read',
			'users:read.email',
		);

		$params = array(
			'client_id'    => $this->settings['client_id'] ?? '',
			'scope'        => implode( ',', $scopes ),
			'redirect_uri' => $this->get_oauth_redirect_uri(),
		);

		return self::OAUTH_AUTHORIZE . '?' . http_build_query( $params );
	}

	/**
	 * Get OAuth redirect URI.
	 *
	 * @return string
	 */
	public function get_oauth_redirect_uri() {
		return admin_url( 'admin.php?page=bkx-slack' );
	}

	/**
	 * Post a message to a channel.
	 *
	 * @param string $channel Channel ID.
	 * @param array  $message Message data.
	 * @param string $token   Access token.
	 * @return array|WP_Error
	 */
	public function post_message( $channel, $message, $token ) {
		$params = array_merge( array( 'channel' => $channel ), $message );
		return $this->request( 'chat.postMessage', $params, $token );
	}

	/**
	 * Post a message using incoming webhook.
	 *
	 * @param string $webhook_url Webhook URL.
	 * @param array  $message     Message data.
	 * @return bool|WP_Error
	 */
	public function post_webhook( $webhook_url, $message ) {
		$response = wp_remote_post( $webhook_url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $message ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			return new \WP_Error( 'webhook_error', 'Webhook request failed' );
		}

		return true;
	}

	/**
	 * Update a message.
	 *
	 * @param string $channel   Channel ID.
	 * @param string $ts        Message timestamp.
	 * @param array  $message   Updated message data.
	 * @param string $token     Access token.
	 * @return array|WP_Error
	 */
	public function update_message( $channel, $ts, $message, $token ) {
		$params = array_merge(
			array(
				'channel' => $channel,
				'ts'      => $ts,
			),
			$message
		);
		return $this->request( 'chat.update', $params, $token );
	}

	/**
	 * Get list of channels.
	 *
	 * @param string $token  Access token.
	 * @param array  $params Additional parameters.
	 * @return array|WP_Error
	 */
	public function get_channels( $token, $params = array() ) {
		$default_params = array(
			'types'            => 'public_channel,private_channel',
			'exclude_archived' => true,
			'limit'            => 200,
		);

		$params = array_merge( $default_params, $params );

		return $this->request( 'conversations.list', $params, $token, 'GET' );
	}

	/**
	 * Get channel info.
	 *
	 * @param string $channel Channel ID.
	 * @param string $token   Access token.
	 * @return array|WP_Error
	 */
	public function get_channel_info( $channel, $token ) {
		return $this->request( 'conversations.info', array( 'channel' => $channel ), $token, 'GET' );
	}

	/**
	 * Get user info.
	 *
	 * @param string $user_id User ID.
	 * @param string $token   Access token.
	 * @return array|WP_Error
	 */
	public function get_user_info( $user_id, $token ) {
		return $this->request( 'users.info', array( 'user' => $user_id ), $token, 'GET' );
	}

	/**
	 * Open a modal/view.
	 *
	 * @param string $trigger_id Trigger ID from interaction.
	 * @param array  $view       View definition.
	 * @param string $token      Access token.
	 * @return array|WP_Error
	 */
	public function open_view( $trigger_id, $view, $token ) {
		return $this->request( 'views.open', array(
			'trigger_id' => $trigger_id,
			'view'       => $view,
		), $token );
	}

	/**
	 * Respond to a slash command.
	 *
	 * @param string $response_url Response URL.
	 * @param array  $message      Message data.
	 * @return bool|WP_Error
	 */
	public function respond( $response_url, $message ) {
		$response = wp_remote_post( $response_url, array(
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $message ),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Verify request signature.
	 *
	 * @param string $body      Request body.
	 * @param string $timestamp Request timestamp.
	 * @param string $signature Request signature.
	 * @return bool
	 */
	public function verify_signature( $body, $timestamp, $signature ) {
		$signing_secret = $this->settings['signing_secret'] ?? '';

		if ( empty( $signing_secret ) ) {
			return false;
		}

		// Check timestamp to prevent replay attacks.
		if ( abs( time() - intval( $timestamp ) ) > 300 ) {
			return false;
		}

		$sig_basestring = 'v0:' . $timestamp . ':' . $body;
		$my_signature   = 'v0=' . hash_hmac( 'sha256', $sig_basestring, $signing_secret );

		return hash_equals( $my_signature, $signature );
	}

	/**
	 * Check if API is configured.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return ! empty( $this->settings['client_id'] )
			&& ! empty( $this->settings['client_secret'] );
	}

	/**
	 * Get workspace access token.
	 *
	 * @param int $workspace_id Workspace ID.
	 * @return string|null
	 */
	public function get_workspace_token( $workspace_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$workspace = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}bkx_slack_workspaces WHERE id = %d AND status = 'active'",
			$workspace_id
		) );

		if ( ! $workspace ) {
			return null;
		}

		return $this->decrypt_token( $workspace->access_token );
	}

	/**
	 * Decrypt stored token.
	 *
	 * @param string $encrypted Encrypted token.
	 * @return string
	 */
	private function decrypt_token( $encrypted ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return base64_decode( $encrypted );
		}

		$key  = $this->get_encryption_key();
		$data = base64_decode( $encrypted );
		$iv   = substr( $data, 0, 16 );
		$cipher = substr( $data, 16 );

		return openssl_decrypt( $cipher, 'AES-256-CBC', $key, 0, $iv );
	}

	/**
	 * Get encryption key.
	 *
	 * @return string
	 */
	private function get_encryption_key() {
		$key = defined( 'BKX_SLACK_ENCRYPTION_KEY' ) ? BKX_SLACK_ENCRYPTION_KEY : AUTH_KEY;
		return hash( 'sha256', $key, true );
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
				'[BKX Slack] %s: %s',
				$message,
				wp_json_encode( $context )
			) );
		}
	}
}
