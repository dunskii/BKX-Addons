<?php
/**
 * QR Code Service.
 *
 * Handles WeChat QR code generation.
 *
 * @package BookingX\WeChat
 */

namespace BookingX\WeChat\Services;

defined( 'ABSPATH' ) || exit;

/**
 * QRCodeService class.
 */
class QRCodeService {

	/**
	 * Addon instance.
	 *
	 * @var \BookingX\WeChat\WeChatAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @param \BookingX\WeChat\WeChatAddon $addon Addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Generate QR code.
	 *
	 * @param string $type       QR code type.
	 * @param array  $parameters Additional parameters.
	 * @return array|false
	 */
	public function generate( $type, $parameters = array() ) {
		switch ( $type ) {
			case 'follow':
				return $this->generate_follow_qr();

			case 'service':
				return $this->generate_service_qr( $parameters['service_id'] ?? 0 );

			case 'booking':
				return $this->generate_booking_qr( $parameters['booking_id'] ?? 0 );

			case 'mini_program':
				return $this->generate_mini_program_qr( $parameters['page'] ?? '', $parameters['scene'] ?? '' );

			default:
				return false;
		}
	}

	/**
	 * Generate QR code via REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function generate_qr_code( $request ) {
		$type = $request->get_param( 'type' );

		$parameters = array(
			'service_id' => absint( $request->get_param( 'service_id' ) ),
			'booking_id' => absint( $request->get_param( 'booking_id' ) ),
			'page'       => sanitize_text_field( $request->get_param( 'page' ) ),
			'scene'      => sanitize_text_field( $request->get_param( 'scene' ) ),
		);

		$result = $this->generate( $type, $parameters );

		if ( ! $result ) {
			return new \WP_REST_Response(
				array( 'error' => 'generation_failed', 'message' => __( 'Failed to generate QR code.', 'bkx-wechat' ) ),
				500
			);
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * Generate follow QR code.
	 *
	 * @return array|false
	 */
	private function generate_follow_qr() {
		$api = $this->addon->get_service( 'wechat_api' );

		$scene  = 'follow_' . time();
		$result = $api->create_qrcode( $scene, true ); // Permanent QR code.

		if ( ! $result ) {
			return false;
		}

		return array(
			'type'       => 'follow',
			'url'        => $result['qrcode_url'] ?? '',
			'ticket'     => $result['ticket'] ?? '',
			'scene'      => $scene,
			'expires_at' => null, // Permanent.
		);
	}

	/**
	 * Generate service-specific QR code.
	 *
	 * @param int $service_id Service ID.
	 * @return array|false
	 */
	private function generate_service_qr( $service_id ) {
		if ( ! $service_id ) {
			return false;
		}

		$api = $this->addon->get_service( 'wechat_api' );

		$scene  = 'service_' . $service_id;
		$result = $api->create_qrcode( $scene, true ); // Permanent QR code.

		if ( ! $result ) {
			return false;
		}

		$service = get_post( $service_id );

		return array(
			'type'         => 'service',
			'service_id'   => $service_id,
			'service_name' => $service ? $service->post_title : '',
			'url'          => $result['qrcode_url'] ?? '',
			'ticket'       => $result['ticket'] ?? '',
			'scene'        => $scene,
			'expires_at'   => null,
		);
	}

	/**
	 * Generate booking-specific QR code.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array|false
	 */
	private function generate_booking_qr( $booking_id ) {
		if ( ! $booking_id ) {
			return false;
		}

		$api = $this->addon->get_service( 'wechat_api' );

		$scene  = 'booking_' . $booking_id;
		$result = $api->create_qrcode( $scene, false, 604800 ); // Temporary, 7 days.

		if ( ! $result ) {
			return false;
		}

		return array(
			'type'       => 'booking',
			'booking_id' => $booking_id,
			'url'        => $result['qrcode_url'] ?? '',
			'ticket'     => $result['ticket'] ?? '',
			'scene'      => $scene,
			'expires_at' => gmdate( 'Y-m-d H:i:s', time() + ( $result['expire_time'] ?? 604800 ) ),
		);
	}

	/**
	 * Generate Mini Program QR code.
	 *
	 * @param string $page  Page path.
	 * @param string $scene Scene parameter.
	 * @return array|false
	 */
	private function generate_mini_program_qr( $page, $scene ) {
		if ( empty( $page ) ) {
			$page = 'pages/index/index';
		}

		$api    = $this->addon->get_service( 'wechat_api' );
		$result = $api->get_mini_program_qrcode( $page, $scene );

		if ( ! $result ) {
			return false;
		}

		return array(
			'type'   => 'mini_program',
			'page'   => $page,
			'scene'  => $scene,
			'base64' => $result,
		);
	}

	/**
	 * Get cached QR codes.
	 *
	 * @return array
	 */
	public function get_cached_qr_codes() {
		return get_option( 'bkx_wechat_qr_codes', array() );
	}

	/**
	 * Cache QR code.
	 *
	 * @param string $key  Cache key.
	 * @param array  $data QR code data.
	 */
	public function cache_qr_code( $key, $data ) {
		$cache            = $this->get_cached_qr_codes();
		$cache[ $key ]    = $data;
		$cache[ $key ]['cached_at'] = current_time( 'mysql' );

		// Keep only last 50 QR codes.
		if ( count( $cache ) > 50 ) {
			$cache = array_slice( $cache, -50, 50, true );
		}

		update_option( 'bkx_wechat_qr_codes', $cache );
	}

	/**
	 * Get QR code image URL for display.
	 *
	 * @param string $ticket QR code ticket.
	 * @return string
	 */
	public function get_qr_image_url( $ticket ) {
		return 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . rawurlencode( $ticket );
	}

	/**
	 * Generate printable QR code with styling.
	 *
	 * @param string $type       QR code type.
	 * @param array  $parameters Parameters.
	 * @param array  $style      Style options.
	 * @return string HTML output.
	 */
	public function generate_printable_qr( $type, $parameters = array(), $style = array() ) {
		$qr_data = $this->generate( $type, $parameters );

		if ( ! $qr_data ) {
			return '';
		}

		$defaults = array(
			'size'             => 200,
			'margin'           => 20,
			'background_color' => '#ffffff',
			'foreground_color' => '#000000',
			'logo'             => '',
			'title'            => '',
			'subtitle'         => '',
		);

		$style = wp_parse_args( $style, $defaults );

		$html = '<div class="bkx-wechat-qr-printable" style="
			width: ' . ( $style['size'] + $style['margin'] * 2 ) . 'px;
			padding: ' . $style['margin'] . 'px;
			background: ' . esc_attr( $style['background_color'] ) . ';
			text-align: center;
		">';

		if ( ! empty( $style['title'] ) ) {
			$html .= '<h3 style="margin: 0 0 10px; font-size: 16px;">' . esc_html( $style['title'] ) . '</h3>';
		}

		if ( isset( $qr_data['base64'] ) ) {
			$html .= '<img src="' . esc_attr( $qr_data['base64'] ) . '" width="' . esc_attr( $style['size'] ) . '" height="' . esc_attr( $style['size'] ) . '" alt="QR Code">';
		} else {
			$html .= '<img src="' . esc_url( $qr_data['url'] ) . '" width="' . esc_attr( $style['size'] ) . '" height="' . esc_attr( $style['size'] ) . '" alt="QR Code">';
		}

		if ( ! empty( $style['subtitle'] ) ) {
			$html .= '<p style="margin: 10px 0 0; font-size: 12px; color: #666;">' . esc_html( $style['subtitle'] ) . '</p>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Generate all service QR codes.
	 *
	 * @return array
	 */
	public function generate_all_service_qr_codes() {
		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		$results = array();

		foreach ( $services as $service ) {
			$qr_data = $this->generate( 'service', array( 'service_id' => $service->ID ) );

			if ( $qr_data ) {
				$qr_data['service_name'] = $service->post_title;
				$results[ $service->ID ] = $qr_data;

				// Cache for later use.
				$this->cache_qr_code( 'service_' . $service->ID, $qr_data );
			}
		}

		return $results;
	}
}
