<?php
/**
 * Official Account Service.
 *
 * Handles WeChat Official Account operations.
 *
 * @package BookingX\WeChat
 */

namespace BookingX\WeChat\Services;

defined( 'ABSPATH' ) || exit;

/**
 * OfficialAccountService class.
 */
class OfficialAccountService {

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
	 * Send template message.
	 *
	 * @param string $openid      User OpenID.
	 * @param string $template_id Template ID.
	 * @param array  $data        Message data.
	 * @param string $url         Click URL.
	 * @return bool
	 */
	public function send_template_message( $openid, $template_id, $data, $url = '' ) {
		$api = $this->addon->get_service( 'wechat_api' );

		// Build template data from booking data.
		$template_data = $this->build_template_data( $data );

		return $api->send_template_message( $openid, $template_id, $template_data, $url );
	}

	/**
	 * Build template data from booking data.
	 *
	 * @param array $data Booking data.
	 * @return array
	 */
	private function build_template_data( $data ) {
		$template_data = array();

		if ( isset( $data['booking_id'] ) ) {
			$booking    = get_post( $data['booking_id'] );
			$booking_id = $data['booking_id'];

			if ( $booking ) {
				$template_data['first']    = array( 'value' => __( 'Your booking has been confirmed.', 'bkx-wechat' ) );
				$template_data['keyword1'] = array( 'value' => get_post_meta( $booking_id, 'service_name', true ) ?: $data['service_name'] ?? '' );
				$template_data['keyword2'] = array( 'value' => get_post_meta( $booking_id, 'booking_date', true ) ?: $data['date'] ?? '' );
				$template_data['keyword3'] = array( 'value' => get_post_meta( $booking_id, 'booking_time', true ) ?: $data['time'] ?? '' );
				$template_data['keyword4'] = array( 'value' => '#' . $booking_id );
				$template_data['remark']   = array( 'value' => __( 'Thank you for your booking!', 'bkx-wechat' ) );
			}
		} else {
			// Generic template data.
			foreach ( $data as $key => $value ) {
				$template_data[ $key ] = array( 'value' => $value );
			}
		}

		return $template_data;
	}

	/**
	 * Sync custom menu.
	 *
	 * @return array
	 */
	public function sync_menu() {
		$menu_config = $this->addon->get_setting( 'menu_config', array() );

		if ( empty( $menu_config ) ) {
			return array(
				'success' => false,
				'message' => __( 'No menu configuration found.', 'bkx-wechat' ),
			);
		}

		$api    = $this->addon->get_service( 'wechat_api' );
		$result = $api->create_menu( $menu_config );

		if ( $result ) {
			return array(
				'success' => true,
				'message' => __( 'Menu synced successfully.', 'bkx-wechat' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Failed to sync menu.', 'bkx-wechat' ),
		);
	}

	/**
	 * Get menu.
	 *
	 * @return array|false
	 */
	public function get_menu() {
		$api = $this->addon->get_service( 'wechat_api' );
		return $api->get_menu();
	}

	/**
	 * Send customer service message.
	 *
	 * @param string $openid  User OpenID.
	 * @param string $type    Message type.
	 * @param array  $content Message content.
	 * @return bool
	 */
	public function send_customer_message( $openid, $type, $content ) {
		$api = $this->addon->get_service( 'wechat_api' );

		$message = array(
			'touser'  => $openid,
			'msgtype' => $type,
		);

		switch ( $type ) {
			case 'text':
				$message['text'] = array( 'content' => $content['text'] ?? '' );
				break;

			case 'image':
				$message['image'] = array( 'media_id' => $content['media_id'] ?? '' );
				break;

			case 'news':
				$message['news'] = array( 'articles' => $content['articles'] ?? array() );
				break;

			case 'miniprogrampage':
				$message['miniprogrampage'] = array(
					'title'          => $content['title'] ?? '',
					'appid'          => $this->addon->get_setting( 'mini_program_app_id' ),
					'pagepath'       => $content['pagepath'] ?? '',
					'thumb_media_id' => $content['thumb_media_id'] ?? '',
				);
				break;
		}

		$result = $api->request( '/cgi-bin/message/custom/send', $message, 'POST' );

		return false !== $result;
	}

	/**
	 * Get followers list.
	 *
	 * @param string $next_openid Next OpenID for pagination.
	 * @return array|false
	 */
	public function get_followers( $next_openid = '' ) {
		$api      = $this->addon->get_service( 'wechat_api' );
		$endpoint = '/cgi-bin/user/get';

		if ( $next_openid ) {
			$endpoint .= '?next_openid=' . $next_openid;
		}

		return $api->request( $endpoint );
	}

	/**
	 * Get user info.
	 *
	 * @param string $openid User OpenID.
	 * @return array|false
	 */
	public function get_user_info( $openid ) {
		$api = $this->addon->get_service( 'wechat_api' );
		return $api->get_user_info( $openid );
	}

	/**
	 * Create or get WordPress user from WeChat.
	 *
	 * @param string $openid   WeChat OpenID.
	 * @param array  $userinfo WeChat user info.
	 * @return int|false WordPress user ID or false.
	 */
	public function get_or_create_user( $openid, $userinfo = array() ) {
		// Find existing user by OpenID.
		$users = get_users(
			array(
				'meta_key'   => 'wechat_openid',
				'meta_value' => $openid,
				'number'     => 1,
			)
		);

		if ( ! empty( $users ) ) {
			return $users[0]->ID;
		}

		// Create new user.
		$username = 'wechat_' . substr( md5( $openid ), 0, 8 );
		$password = wp_generate_password( 16, true, true );
		$email    = $username . '@wechat.placeholder';

		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			return false;
		}

		// Save WeChat data.
		update_user_meta( $user_id, 'wechat_openid', $openid );

		if ( ! empty( $userinfo ) ) {
			update_user_meta( $user_id, 'wechat_nickname', $userinfo['nickname'] ?? '' );
			update_user_meta( $user_id, 'wechat_headimgurl', $userinfo['headimgurl'] ?? '' );
			update_user_meta( $user_id, 'wechat_sex', $userinfo['sex'] ?? 0 );
			update_user_meta( $user_id, 'wechat_city', $userinfo['city'] ?? '' );
			update_user_meta( $user_id, 'wechat_province', $userinfo['province'] ?? '' );
			update_user_meta( $user_id, 'wechat_country', $userinfo['country'] ?? '' );

			// Update display name.
			if ( ! empty( $userinfo['nickname'] ) ) {
				wp_update_user(
					array(
						'ID'           => $user_id,
						'display_name' => $userinfo['nickname'],
					)
				);
			}
		}

		return $user_id;
	}

	/**
	 * Get available template messages.
	 *
	 * @return array|false
	 */
	public function get_template_list() {
		$api = $this->addon->get_service( 'wechat_api' );
		return $api->request( '/cgi-bin/template/get_all_private_template' );
	}

	/**
	 * Get template industry.
	 *
	 * @return array|false
	 */
	public function get_industry() {
		$api = $this->addon->get_service( 'wechat_api' );
		return $api->request( '/cgi-bin/template/get_industry' );
	}
}
