<?php
/**
 * WeChat Pay Service.
 *
 * Handles WeChat Pay operations.
 *
 * @package BookingX\WeChat
 */

namespace BookingX\WeChat\Services;

defined( 'ABSPATH' ) || exit;

/**
 * WeChatPayService class.
 */
class WeChatPayService {

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
	private $api_base = 'https://api.mch.weixin.qq.com';

	/**
	 * Sandbox API base URL.
	 *
	 * @var string
	 */
	private $sandbox_api_base = 'https://api.mch.weixin.qq.com/sandboxnew';

	/**
	 * Constructor.
	 *
	 * @param \BookingX\WeChat\WeChatAddon $addon Addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Get API base URL.
	 *
	 * @return string
	 */
	private function get_api_base() {
		return $this->addon->get_setting( 'sandbox_mode', true ) ? $this->sandbox_api_base : $this->api_base;
	}

	/**
	 * Create unified order.
	 *
	 * @param array $order_data Order data.
	 * @return array|false
	 */
	public function create_order( $order_data ) {
		$mch_id  = $this->addon->get_setting( 'mch_id' );
		$api_key = $this->addon->get_setting( 'api_key' );

		if ( empty( $mch_id ) || empty( $api_key ) ) {
			return false;
		}

		$nonce_str = $this->generate_nonce();

		$params = array(
			'appid'            => $order_data['appid'] ?? $this->addon->get_setting( 'mini_program_app_id' ),
			'mch_id'           => $mch_id,
			'nonce_str'        => $nonce_str,
			'body'             => $order_data['body'] ?? __( 'BookingX Payment', 'bkx-wechat' ),
			'out_trade_no'     => $order_data['out_trade_no'] ?? $this->generate_order_no(),
			'total_fee'        => absint( $order_data['total_fee'] ), // In cents.
			'spbill_create_ip' => $this->get_client_ip(),
			'notify_url'       => rest_url( 'bkx-wechat/v1/pay/notify' ),
			'trade_type'       => $order_data['trade_type'] ?? 'JSAPI',
			'openid'           => $order_data['openid'] ?? '',
		);

		// Sign request.
		$params['sign'] = $this->sign( $params, $api_key );

		// Send request.
		$response = $this->post_xml( $this->get_api_base() . '/pay/unifiedorder', $params );

		if ( ! $response || 'SUCCESS' !== ( $response['return_code'] ?? '' ) ) {
			$this->log_error( 'Create order failed: ' . ( $response['return_msg'] ?? 'Unknown error' ) );
			return false;
		}

		if ( 'SUCCESS' !== ( $response['result_code'] ?? '' ) ) {
			$this->log_error( 'Create order error: ' . ( $response['err_code_des'] ?? 'Unknown error' ) );
			return false;
		}

		return array(
			'prepay_id'    => $response['prepay_id'] ?? '',
			'out_trade_no' => $params['out_trade_no'],
		);
	}

	/**
	 * Get payment parameters for JSAPI.
	 *
	 * @param string $prepay_id Prepay ID.
	 * @param string $appid     App ID.
	 * @return array
	 */
	public function get_jsapi_params( $prepay_id, $appid = '' ) {
		if ( ! $appid ) {
			$appid = $this->addon->get_setting( 'mini_program_app_id' );
		}

		$api_key   = $this->addon->get_setting( 'api_key' );
		$nonce_str = $this->generate_nonce();
		$timestamp = (string) time();

		$params = array(
			'appId'     => $appid,
			'timeStamp' => $timestamp,
			'nonceStr'  => $nonce_str,
			'package'   => 'prepay_id=' . $prepay_id,
			'signType'  => 'MD5',
		);

		$params['paySign'] = $this->sign( $params, $api_key );

		return $params;
	}

	/**
	 * Handle payment notification.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_payment_notification( $request ) {
		$xml_data = $request->get_body();

		// Parse XML.
		$data = $this->parse_xml( $xml_data );

		if ( ! $data || 'SUCCESS' !== ( $data['return_code'] ?? '' ) ) {
			return $this->notification_response( false, 'Invalid data' );
		}

		// Verify signature.
		$api_key = $this->addon->get_setting( 'api_key' );

		if ( ! $this->verify_sign( $data, $api_key ) ) {
			return $this->notification_response( false, 'Signature verification failed' );
		}

		// Check result.
		if ( 'SUCCESS' !== ( $data['result_code'] ?? '' ) ) {
			return $this->notification_response( false, 'Payment failed' );
		}

		$out_trade_no   = $data['out_trade_no'] ?? '';
		$transaction_id = $data['transaction_id'] ?? '';
		$total_fee      = $data['total_fee'] ?? 0;

		// Find booking by order number.
		$bookings = get_posts(
			array(
				'post_type'  => 'bkx_booking',
				'meta_query' => array(
					array(
						'key'   => '_wechat_order_no',
						'value' => $out_trade_no,
					),
				),
				'numberposts' => 1,
			)
		);

		if ( empty( $bookings ) ) {
			return $this->notification_response( false, 'Order not found' );
		}

		$booking_id = $bookings[0]->ID;

		// Update booking status.
		wp_update_post(
			array(
				'ID'          => $booking_id,
				'post_status' => 'bkx-ack',
			)
		);

		// Save payment details.
		update_post_meta( $booking_id, '_wechat_transaction_id', $transaction_id );
		update_post_meta( $booking_id, '_wechat_paid_amount', $total_fee );
		update_post_meta( $booking_id, '_wechat_paid_at', current_time( 'mysql' ) );

		// Trigger action.
		do_action( 'bkx_wechat_payment_completed', $booking_id, $data );

		return $this->notification_response( true, 'OK' );
	}

	/**
	 * Build notification response.
	 *
	 * @param bool   $success Success flag.
	 * @param string $message Message.
	 * @return \WP_REST_Response
	 */
	private function notification_response( $success, $message ) {
		$response = array(
			'return_code' => $success ? 'SUCCESS' : 'FAIL',
			'return_msg'  => $message,
		);

		$xml = $this->array_to_xml( $response );

		return new \WP_REST_Response( $xml, 200, array( 'Content-Type' => 'application/xml' ) );
	}

	/**
	 * Query order status.
	 *
	 * @param string $out_trade_no Out trade number.
	 * @return array|false
	 */
	public function query_order( $out_trade_no ) {
		$mch_id  = $this->addon->get_setting( 'mch_id' );
		$api_key = $this->addon->get_setting( 'api_key' );
		$appid   = $this->addon->get_setting( 'mini_program_app_id' );

		$params = array(
			'appid'        => $appid,
			'mch_id'       => $mch_id,
			'out_trade_no' => $out_trade_no,
			'nonce_str'    => $this->generate_nonce(),
		);

		$params['sign'] = $this->sign( $params, $api_key );

		$response = $this->post_xml( $this->get_api_base() . '/pay/orderquery', $params );

		if ( ! $response || 'SUCCESS' !== ( $response['return_code'] ?? '' ) ) {
			return false;
		}

		return $response;
	}

	/**
	 * Refund order.
	 *
	 * @param string $out_trade_no Out trade number.
	 * @param int    $total_fee    Total fee in cents.
	 * @param int    $refund_fee   Refund fee in cents.
	 * @param string $reason       Refund reason.
	 * @return array|false
	 */
	public function refund( $out_trade_no, $total_fee, $refund_fee, $reason = '' ) {
		$mch_id  = $this->addon->get_setting( 'mch_id' );
		$api_key = $this->addon->get_setting( 'api_key' );
		$appid   = $this->addon->get_setting( 'mini_program_app_id' );

		$params = array(
			'appid'         => $appid,
			'mch_id'        => $mch_id,
			'nonce_str'     => $this->generate_nonce(),
			'out_trade_no'  => $out_trade_no,
			'out_refund_no' => 'REFUND_' . $out_trade_no . '_' . time(),
			'total_fee'     => $total_fee,
			'refund_fee'    => $refund_fee,
			'refund_desc'   => $reason ?: __( 'Booking cancelled', 'bkx-wechat' ),
		);

		$params['sign'] = $this->sign( $params, $api_key );

		// Refund requires certificate.
		$response = $this->post_xml_with_cert( $this->get_api_base() . '/secapi/pay/refund', $params );

		if ( ! $response || 'SUCCESS' !== ( $response['return_code'] ?? '' ) ) {
			$this->log_error( 'Refund failed: ' . ( $response['return_msg'] ?? 'Unknown error' ) );
			return false;
		}

		return $response;
	}

	/**
	 * Sign parameters.
	 *
	 * @param array  $params  Parameters.
	 * @param string $api_key API key.
	 * @return string
	 */
	private function sign( $params, $api_key ) {
		// Remove sign field if exists.
		unset( $params['sign'] );

		// Sort by key.
		ksort( $params );

		// Build query string.
		$string_a = array();
		foreach ( $params as $key => $value ) {
			if ( '' !== $value && 'sign' !== $key ) {
				$string_a[] = $key . '=' . $value;
			}
		}

		$string_sign_temp = implode( '&', $string_a ) . '&key=' . $api_key;

		return strtoupper( md5( $string_sign_temp ) );
	}

	/**
	 * Verify signature.
	 *
	 * @param array  $data    Data.
	 * @param string $api_key API key.
	 * @return bool
	 */
	private function verify_sign( $data, $api_key ) {
		$sign = $data['sign'] ?? '';

		if ( empty( $sign ) ) {
			return false;
		}

		$calculated = $this->sign( $data, $api_key );

		return $sign === $calculated;
	}

	/**
	 * Generate nonce string.
	 *
	 * @return string
	 */
	private function generate_nonce() {
		return md5( uniqid( wp_rand(), true ) );
	}

	/**
	 * Generate order number.
	 *
	 * @return string
	 */
	private function generate_order_no() {
		return 'BKX' . gmdate( 'YmdHis' ) . wp_rand( 1000, 9999 );
	}

	/**
	 * Get client IP.
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip ?: '127.0.0.1';
	}

	/**
	 * Post XML request.
	 *
	 * @param string $url    URL.
	 * @param array  $params Parameters.
	 * @return array|false
	 */
	private function post_xml( $url, $params ) {
		$xml = $this->array_to_xml( $params );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'text/xml' ),
				'body'    => $xml,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'HTTP request failed: ' . $response->get_error_message() );
			return false;
		}

		return $this->parse_xml( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Post XML request with certificate.
	 *
	 * @param string $url    URL.
	 * @param array  $params Parameters.
	 * @return array|false
	 */
	private function post_xml_with_cert( $url, $params ) {
		$cert_path = $this->addon->get_setting( 'certificate_path' );
		$key_path  = $this->addon->get_setting( 'private_key_path' );

		if ( empty( $cert_path ) || empty( $key_path ) ) {
			$this->log_error( 'Certificate not configured' );
			return false;
		}

		$xml = $this->array_to_xml( $params );

		$response = wp_remote_post(
			$url,
			array(
				'timeout'   => 30,
				'headers'   => array( 'Content-Type' => 'text/xml' ),
				'body'      => $xml,
				'sslcertificates' => $cert_path,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'HTTP request failed: ' . $response->get_error_message() );
			return false;
		}

		return $this->parse_xml( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Convert array to XML.
	 *
	 * @param array $data Data.
	 * @return string
	 */
	private function array_to_xml( $data ) {
		$xml = '<xml>';

		foreach ( $data as $key => $value ) {
			if ( is_numeric( $value ) ) {
				$xml .= '<' . $key . '>' . $value . '</' . $key . '>';
			} else {
				$xml .= '<' . $key . '><![CDATA[' . $value . ']]></' . $key . '>';
			}
		}

		$xml .= '</xml>';

		return $xml;
	}

	/**
	 * Parse XML to array.
	 *
	 * @param string $xml XML string.
	 * @return array|false
	 */
	private function parse_xml( $xml ) {
		if ( empty( $xml ) ) {
			return false;
		}

		// Disable external entity loading.
		$disable_entity_loader = libxml_disable_entity_loader( true );

		$result = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NOCDATA );

		libxml_disable_entity_loader( $disable_entity_loader );

		if ( ! $result ) {
			return false;
		}

		return json_decode( wp_json_encode( $result ), true );
	}

	/**
	 * Log error.
	 *
	 * @param string $message Error message.
	 */
	private function log_error( $message ) {
		if ( $this->addon->get_setting( 'debug_mode', false ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'BKX WeChat Pay: ' . $message );
		}
	}
}
