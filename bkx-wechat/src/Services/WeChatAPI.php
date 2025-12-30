<?php
/**
 * WeChat API Service.
 *
 * Core API client for WeChat platform.
 *
 * @package BookingX\WeChat
 */

namespace BookingX\WeChat\Services;

defined( 'ABSPATH' ) || exit;

/**
 * WeChatAPI class.
 */
class WeChatAPI {

	/**
	 * Addon instance.
	 *
	 * @var \BookingX\WeChat\WeChatAddon
	 */
	private $addon;

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_base = 'https://api.weixin.qq.com';

	/**
	 * Constructor.
	 *
	 * @param \BookingX\WeChat\WeChatAddon $addon Addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Get access token.
	 *
	 * @param string $type Token type (official_account or mini_program).
	 * @return string|false Access token or false on failure.
	 */
	public function get_access_token( $type = 'official_account' ) {
		$cache_key = 'bkx_wechat_access_token_' . $type;
		$token     = get_transient( $cache_key );

		if ( $token ) {
			return $token;
		}

		if ( 'mini_program' === $type ) {
			$app_id     = $this->addon->get_setting( 'mini_program_app_id' );
			$app_secret = $this->addon->get_setting( 'mini_program_secret' );
		} else {
			$app_id     = $this->addon->get_setting( 'app_id' );
			$app_secret = $this->addon->get_setting( 'app_secret' );
		}

		if ( empty( $app_id ) || empty( $app_secret ) ) {
			$this->log_error( 'Missing app credentials' );
			return false;
		}

		$url = add_query_arg(
			array(
				'grant_type' => 'client_credential',
				'appid'      => $app_id,
				'secret'     => $app_secret,
			),
			$this->api_base . '/cgi-bin/token'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 30 ) );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Token request failed: ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['errcode'] ) && 0 !== $body['errcode'] ) {
			$this->log_error( 'Token error: ' . ( $body['errmsg'] ?? 'Unknown error' ) );
			return false;
		}

		$token      = $body['access_token'] ?? '';
		$expires_in = ( $body['expires_in'] ?? 7200 ) - 300; // Expire 5 minutes early.

		if ( $token ) {
			set_transient( $cache_key, $token, $expires_in );
		}

		return $token;
	}

	/**
	 * Make API request.
	 *
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 * @param string $method   HTTP method.
	 * @param string $type     Token type.
	 * @return array|false
	 */
	public function request( $endpoint, $data = array(), $method = 'GET', $type = 'official_account' ) {
		$token = $this->get_access_token( $type );

		if ( ! $token ) {
			return false;
		}

		$url = add_query_arg( 'access_token', $token, $this->api_base . $endpoint );

		$args = array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
		);

		if ( 'POST' === $method ) {
			$args['method'] = 'POST';
			$args['body']   = wp_json_encode( $data, JSON_UNESCAPED_UNICODE );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'API request failed: ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['errcode'] ) && 0 !== $body['errcode'] ) {
			// Token expired, try to refresh.
			if ( in_array( $body['errcode'], array( 40001, 42001 ), true ) ) {
				delete_transient( 'bkx_wechat_access_token_' . $type );
				return $this->request( $endpoint, $data, $method, $type );
			}

			$this->log_error( 'API error: ' . ( $body['errmsg'] ?? 'Unknown error' ) );
			return false;
		}

		return $body;
	}

	/**
	 * Code to session (Mini Program login).
	 *
	 * @param string $code JS code from Mini Program.
	 * @return array|false
	 */
	public function code_to_session( $code ) {
		$app_id     = $this->addon->get_setting( 'mini_program_app_id' );
		$app_secret = $this->addon->get_setting( 'mini_program_secret' );

		if ( empty( $app_id ) || empty( $app_secret ) ) {
			return false;
		}

		$url = add_query_arg(
			array(
				'appid'      => $app_id,
				'secret'     => $app_secret,
				'js_code'    => $code,
				'grant_type' => 'authorization_code',
			),
			$this->api_base . '/sns/jscode2session'
		);

		$response = wp_remote_get( $url, array( 'timeout' => 30 ) );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Code to session failed: ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['errcode'] ) && 0 !== $body['errcode'] ) {
			$this->log_error( 'Code to session error: ' . ( $body['errmsg'] ?? 'Unknown error' ) );
			return false;
		}

		return $body;
	}

	/**
	 * Get user info.
	 *
	 * @param string $openid User OpenID.
	 * @return array|false
	 */
	public function get_user_info( $openid ) {
		return $this->request( '/cgi-bin/user/info', array( 'openid' => $openid, 'lang' => 'zh_CN' ) );
	}

	/**
	 * Send template message.
	 *
	 * @param string $openid      User OpenID.
	 * @param string $template_id Template ID.
	 * @param array  $data        Template data.
	 * @param string $url         Click URL.
	 * @return bool
	 */
	public function send_template_message( $openid, $template_id, $data, $url = '' ) {
		$message = array(
			'touser'      => $openid,
			'template_id' => $template_id,
			'data'        => $this->format_template_data( $data ),
		);

		if ( $url ) {
			$message['url'] = $url;
		}

		$result = $this->request( '/cgi-bin/message/template/send', $message, 'POST' );

		return false !== $result;
	}

	/**
	 * Format template data.
	 *
	 * @param array $data Raw data.
	 * @return array
	 */
	private function format_template_data( $data ) {
		$formatted = array();

		foreach ( $data as $key => $value ) {
			$formatted[ $key ] = array(
				'value' => $value,
			);
		}

		return $formatted;
	}

	/**
	 * Create custom menu.
	 *
	 * @param array $menu Menu configuration.
	 * @return bool
	 */
	public function create_menu( $menu ) {
		$result = $this->request( '/cgi-bin/menu/create', array( 'button' => $menu ), 'POST' );

		return false !== $result;
	}

	/**
	 * Get custom menu.
	 *
	 * @return array|false
	 */
	public function get_menu() {
		return $this->request( '/cgi-bin/get_current_selfmenu_info' );
	}

	/**
	 * Create QR code.
	 *
	 * @param string $scene       Scene value.
	 * @param bool   $is_limit    Whether it's a permanent QR code.
	 * @param int    $expire_time Expire time in seconds (for temporary QR codes).
	 * @return array|false
	 */
	public function create_qrcode( $scene, $is_limit = false, $expire_time = 2592000 ) {
		if ( $is_limit ) {
			$data = array(
				'action_name' => 'QR_LIMIT_STR_SCENE',
				'action_info' => array(
					'scene' => array( 'scene_str' => $scene ),
				),
			);
		} else {
			$data = array(
				'expire_seconds' => $expire_time,
				'action_name'    => 'QR_STR_SCENE',
				'action_info'    => array(
					'scene' => array( 'scene_str' => $scene ),
				),
			);
		}

		$result = $this->request( '/cgi-bin/qrcode/create', $data, 'POST' );

		if ( ! $result || empty( $result['ticket'] ) ) {
			return false;
		}

		return array(
			'ticket'      => $result['ticket'],
			'url'         => $result['url'] ?? '',
			'expire_time' => $result['expire_seconds'] ?? 0,
			'qrcode_url'  => 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . rawurlencode( $result['ticket'] ),
		);
	}

	/**
	 * Get Mini Program QR code.
	 *
	 * @param string $path  Page path.
	 * @param string $scene Scene value.
	 * @param int    $width QR code width.
	 * @return string|false Base64 image or false.
	 */
	public function get_mini_program_qrcode( $path, $scene = '', $width = 430 ) {
		$token = $this->get_access_token( 'mini_program' );

		if ( ! $token ) {
			return false;
		}

		$url  = $this->api_base . '/wxa/getwxacodeunlimit?access_token=' . $token;
		$data = array(
			'page'       => $path,
			'scene'      => $scene,
			'width'      => $width,
			'auto_color' => false,
			'line_color' => array( 'r' => 0, 'g' => 0, 'b' => 0 ),
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $data ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Mini Program QR code failed: ' . $response->get_error_message() );
			return false;
		}

		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		// If JSON response, it's an error.
		if ( strpos( $content_type, 'application/json' ) !== false ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$this->log_error( 'Mini Program QR code error: ' . ( $body['errmsg'] ?? 'Unknown error' ) );
			return false;
		}

		// Return base64 encoded image.
		return 'data:image/png;base64,' . base64_encode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Test connection.
	 *
	 * @return array
	 */
	public function test_connection() {
		// Test Official Account.
		$oa_token = $this->get_access_token( 'official_account' );
		$oa_ok    = ! empty( $oa_token );

		// Test Mini Program.
		$mp_token = $this->get_access_token( 'mini_program' );
		$mp_ok    = ! empty( $mp_token );

		if ( $oa_ok || $mp_ok ) {
			return array(
				'success' => true,
				'message' => __( 'Connection successful.', 'bkx-wechat' ),
				'details' => array(
					'official_account' => $oa_ok ? __( 'Connected', 'bkx-wechat' ) : __( 'Not configured', 'bkx-wechat' ),
					'mini_program'     => $mp_ok ? __( 'Connected', 'bkx-wechat' ) : __( 'Not configured', 'bkx-wechat' ),
				),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Connection failed. Check your credentials.', 'bkx-wechat' ),
		);
	}

	/**
	 * Verify message signature.
	 *
	 * @param string $signature Signature from WeChat.
	 * @param string $timestamp Timestamp.
	 * @param string $nonce     Nonce.
	 * @return bool
	 */
	public function verify_signature( $signature, $timestamp, $nonce ) {
		$token = $this->addon->get_setting( 'token', '' );

		if ( empty( $token ) ) {
			return false;
		}

		$params = array( $token, $timestamp, $nonce );
		sort( $params, SORT_STRING );

		$str = implode( '', $params );

		return sha1( $str ) === $signature;
	}

	/**
	 * Log error.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( $message ) {
		if ( $this->addon->get_setting( 'debug_mode', false ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BKX WeChat API: ' . $message );
		}
	}
}
