<?php
/**
 * Message Handler Service.
 *
 * Handles WeChat message callback.
 *
 * @package BookingX\WeChat
 */

namespace BookingX\WeChat\Services;

defined( 'ABSPATH' ) || exit;

/**
 * MessageHandler class.
 */
class MessageHandler {

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
	 * Verify WeChat server.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|string
	 */
	public function verify_server( $request ) {
		$signature = $request->get_param( 'signature' );
		$timestamp = $request->get_param( 'timestamp' );
		$nonce     = $request->get_param( 'nonce' );
		$echostr   = $request->get_param( 'echostr' );

		$api = $this->addon->get_service( 'wechat_api' );

		if ( $api->verify_signature( $signature, $timestamp, $nonce ) ) {
			return new \WP_REST_Response( $echostr, 200 );
		}

		return new \WP_REST_Response( 'Verification failed', 403 );
	}

	/**
	 * Handle incoming message.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_message( $request ) {
		$xml_data = $request->get_body();
		$message  = $this->parse_xml( $xml_data );

		if ( ! $message ) {
			return new \WP_REST_Response( 'Invalid message', 400 );
		}

		$msg_type = $message['MsgType'] ?? '';
		$response = '';

		switch ( $msg_type ) {
			case 'text':
				$response = $this->handle_text_message( $message );
				break;

			case 'event':
				$response = $this->handle_event_message( $message );
				break;

			case 'image':
			case 'voice':
			case 'video':
			case 'shortvideo':
			case 'location':
			case 'link':
				$response = $this->handle_media_message( $message );
				break;
		}

		if ( empty( $response ) ) {
			return new \WP_REST_Response( 'success', 200 );
		}

		return new \WP_REST_Response( $response, 200, array( 'Content-Type' => 'text/xml' ) );
	}

	/**
	 * Handle text message.
	 *
	 * @param array $message Message data.
	 * @return string
	 */
	private function handle_text_message( $message ) {
		$content  = $message['Content'] ?? '';
		$from     = $message['FromUserName'] ?? '';
		$to       = $message['ToUserName'] ?? '';

		// Check auto-reply rules.
		if ( $this->addon->get_setting( 'auto_reply_enabled', false ) ) {
			$rules = $this->addon->get_setting( 'auto_reply_rules', array() );

			foreach ( $rules as $rule ) {
				$keyword = $rule['keyword'] ?? '';

				if ( ! empty( $keyword ) && false !== strpos( $content, $keyword ) ) {
					return $this->build_reply( $from, $to, $rule['type'] ?? 'text', $rule['content'] ?? '' );
				}
			}
		}

		// Handle booking-related keywords.
		$booking_keywords = array(
			'book'   => array( '预约', 'book', '订', '约' ),
			'cancel' => array( '取消', 'cancel', '不要' ),
			'query'  => array( '查询', 'query', '我的', '订单' ),
			'help'   => array( '帮助', 'help', '?', '？' ),
		);

		foreach ( $booking_keywords as $action => $keywords ) {
			foreach ( $keywords as $keyword ) {
				if ( false !== mb_strpos( $content, $keyword ) ) {
					return $this->handle_booking_intent( $from, $to, $action );
				}
			}
		}

		// Default reply.
		$default_reply = sprintf(
			/* translators: %s: Business name */
			__( 'Welcome to %s! Reply with keywords like "book" to make an appointment, or "query" to check your bookings.', 'bkx-wechat' ),
			get_bloginfo( 'name' )
		);

		return $this->build_text_reply( $from, $to, $default_reply );
	}

	/**
	 * Handle event message.
	 *
	 * @param array $message Message data.
	 * @return string
	 */
	private function handle_event_message( $message ) {
		$event     = $message['Event'] ?? '';
		$from      = $message['FromUserName'] ?? '';
		$to        = $message['ToUserName'] ?? '';
		$event_key = $message['EventKey'] ?? '';

		switch ( $event ) {
			case 'subscribe':
				return $this->handle_subscribe( $from, $to, $event_key );

			case 'unsubscribe':
				$this->handle_unsubscribe( $from );
				return '';

			case 'SCAN':
				return $this->handle_scan( $from, $to, $event_key );

			case 'CLICK':
				return $this->handle_menu_click( $from, $to, $event_key );

			case 'VIEW':
				// User clicked menu link - no reply needed.
				return '';

			case 'LOCATION':
				return $this->handle_location_event( $from, $to, $message );
		}

		return '';
	}

	/**
	 * Handle subscribe event.
	 *
	 * @param string $from      Subscriber OpenID.
	 * @param string $to        Official Account ID.
	 * @param string $event_key Event key (for QR code subscription).
	 * @return string
	 */
	private function handle_subscribe( $from, $to, $event_key = '' ) {
		// Get or create user.
		$oa_service = $this->addon->get_service( 'official_account' );
		$oa_service->get_or_create_user( $from );

		// Handle QR code scene.
		if ( ! empty( $event_key ) && 0 === strpos( $event_key, 'qrscene_' ) ) {
			$scene = substr( $event_key, 8 );
			$this->handle_qr_scene( $from, $scene );
		}

		// Welcome message.
		$welcome = sprintf(
			/* translators: %s: Business name */
			__( 'Welcome to %s! You can book appointments directly through this account. Reply "book" to get started.', 'bkx-wechat' ),
			get_bloginfo( 'name' )
		);

		return $this->build_text_reply( $from, $to, $welcome );
	}

	/**
	 * Handle unsubscribe event.
	 *
	 * @param string $from Subscriber OpenID.
	 */
	private function handle_unsubscribe( $from ) {
		// Mark user as unsubscribed.
		$users = get_users(
			array(
				'meta_key'   => 'wechat_openid',
				'meta_value' => $from,
				'number'     => 1,
			)
		);

		if ( ! empty( $users ) ) {
			update_user_meta( $users[0]->ID, 'wechat_unsubscribed', current_time( 'mysql' ) );
		}
	}

	/**
	 * Handle scan event.
	 *
	 * @param string $from  User OpenID.
	 * @param string $to    Official Account ID.
	 * @param string $scene Scene value.
	 * @return string
	 */
	private function handle_scan( $from, $to, $scene ) {
		$this->handle_qr_scene( $from, $scene );

		return $this->build_text_reply(
			$from,
			$to,
			__( 'Thanks for scanning! How can we help you today?', 'bkx-wechat' )
		);
	}

	/**
	 * Handle QR code scene.
	 *
	 * @param string $from  User OpenID.
	 * @param string $scene Scene value.
	 */
	private function handle_qr_scene( $from, $scene ) {
		// Parse scene value.
		$parts = explode( '_', $scene );

		if ( count( $parts ) >= 2 ) {
			$type  = $parts[0];
			$value = $parts[1];

			switch ( $type ) {
				case 'service':
					// Store preferred service.
					$this->store_user_preference( $from, 'preferred_service', $value );
					break;

				case 'referral':
					// Track referral.
					$this->track_referral( $from, $value );
					break;

				case 'booking':
					// Link to existing booking.
					$this->link_booking_to_user( $from, $value );
					break;
			}
		}
	}

	/**
	 * Handle menu click event.
	 *
	 * @param string $from  User OpenID.
	 * @param string $to    Official Account ID.
	 * @param string $key   Menu key.
	 * @return string
	 */
	private function handle_menu_click( $from, $to, $key ) {
		switch ( $key ) {
			case 'book':
				return $this->handle_booking_intent( $from, $to, 'book' );

			case 'my_bookings':
				return $this->handle_booking_intent( $from, $to, 'query' );

			case 'cancel':
				return $this->handle_booking_intent( $from, $to, 'cancel' );

			case 'contact':
				return $this->build_text_reply(
					$from,
					$to,
					sprintf(
						/* translators: %s: Contact info */
						__( 'Contact us: %s', 'bkx-wechat' ),
						get_option( 'admin_email' )
					)
				);

			default:
				return '';
		}
	}

	/**
	 * Handle booking intent.
	 *
	 * @param string $from   User OpenID.
	 * @param string $to     Official Account ID.
	 * @param string $action Booking action.
	 * @return string
	 */
	private function handle_booking_intent( $from, $to, $action ) {
		$booking_url = home_url( '/booking/?openid=' . $from );

		switch ( $action ) {
			case 'book':
				// Send article message with booking link.
				return $this->build_news_reply(
					$from,
					$to,
					array(
						array(
							'Title'       => __( 'Book an Appointment', 'bkx-wechat' ),
							'Description' => __( 'Click to view available times and make a booking.', 'bkx-wechat' ),
							'PicUrl'      => '',
							'Url'         => $booking_url,
						),
					)
				);

			case 'query':
				// Get user bookings.
				$bookings = $this->get_user_bookings( $from );

				if ( empty( $bookings ) ) {
					return $this->build_text_reply(
						$from,
						$to,
						__( 'You have no upcoming bookings.', 'bkx-wechat' )
					);
				}

				$message = __( 'Your upcoming bookings:', 'bkx-wechat' ) . "\n\n";

				foreach ( $bookings as $booking ) {
					$message .= sprintf(
						"#%d: %s\n%s %s\n\n",
						$booking['id'],
						$booking['service'],
						$booking['date'],
						$booking['time']
					);
				}

				return $this->build_text_reply( $from, $to, $message );

			case 'cancel':
				return $this->build_text_reply(
					$from,
					$to,
					__( 'To cancel a booking, please reply with the booking number, e.g., "cancel 12345".', 'bkx-wechat' )
				);

			case 'help':
				return $this->build_text_reply(
					$from,
					$to,
					__( "Available commands:\n- book: Make a new booking\n- query: View your bookings\n- cancel: Cancel a booking\n- help: Show this help", 'bkx-wechat' )
				);
		}

		return '';
	}

	/**
	 * Handle media message.
	 *
	 * @param array $message Message data.
	 * @return string
	 */
	private function handle_media_message( $message ) {
		$from = $message['FromUserName'] ?? '';
		$to   = $message['ToUserName'] ?? '';

		// Handle voice message for voice booking.
		if ( 'voice' === ( $message['MsgType'] ?? '' ) ) {
			$recognition = $message['Recognition'] ?? '';

			if ( ! empty( $recognition ) ) {
				// Treat voice recognition as text input.
				$message['Content'] = $recognition;
				$message['MsgType'] = 'text';
				return $this->handle_text_message( $message );
			}
		}

		return $this->build_text_reply(
			$from,
			$to,
			__( 'Thanks for your message! For bookings, please reply with "book".', 'bkx-wechat' )
		);
	}

	/**
	 * Handle location event.
	 *
	 * @param string $from    User OpenID.
	 * @param string $to      Official Account ID.
	 * @param array  $message Message data.
	 * @return string
	 */
	private function handle_location_event( $from, $to, $message ) {
		$latitude  = $message['Latitude'] ?? '';
		$longitude = $message['Longitude'] ?? '';

		if ( empty( $latitude ) || empty( $longitude ) ) {
			return '';
		}

		// Store user location for nearby service recommendations.
		$this->store_user_preference( $from, 'last_location', array(
			'latitude'  => $latitude,
			'longitude' => $longitude,
		) );

		return '';
	}

	/**
	 * Build text reply.
	 *
	 * @param string $from    User OpenID.
	 * @param string $to      Official Account ID.
	 * @param string $content Reply content.
	 * @return string
	 */
	private function build_text_reply( $from, $to, $content ) {
		return $this->build_reply( $from, $to, 'text', $content );
	}

	/**
	 * Build news reply.
	 *
	 * @param string $from     User OpenID.
	 * @param string $to       Official Account ID.
	 * @param array  $articles Articles data.
	 * @return string
	 */
	private function build_news_reply( $from, $to, $articles ) {
		$items_xml = '';

		foreach ( $articles as $article ) {
			$items_xml .= sprintf(
				'<item>
					<Title><![CDATA[%s]]></Title>
					<Description><![CDATA[%s]]></Description>
					<PicUrl><![CDATA[%s]]></PicUrl>
					<Url><![CDATA[%s]]></Url>
				</item>',
				$article['Title'] ?? '',
				$article['Description'] ?? '',
				$article['PicUrl'] ?? '',
				$article['Url'] ?? ''
			);
		}

		return sprintf(
			'<xml>
				<ToUserName><![CDATA[%s]]></ToUserName>
				<FromUserName><![CDATA[%s]]></FromUserName>
				<CreateTime>%d</CreateTime>
				<MsgType><![CDATA[news]]></MsgType>
				<ArticleCount>%d</ArticleCount>
				<Articles>%s</Articles>
			</xml>',
			$from,
			$to,
			time(),
			count( $articles ),
			$items_xml
		);
	}

	/**
	 * Build reply message.
	 *
	 * @param string $from    User OpenID.
	 * @param string $to      Official Account ID.
	 * @param string $type    Message type.
	 * @param string $content Content.
	 * @return string
	 */
	private function build_reply( $from, $to, $type, $content ) {
		switch ( $type ) {
			case 'text':
				return sprintf(
					'<xml>
						<ToUserName><![CDATA[%s]]></ToUserName>
						<FromUserName><![CDATA[%s]]></FromUserName>
						<CreateTime>%d</CreateTime>
						<MsgType><![CDATA[text]]></MsgType>
						<Content><![CDATA[%s]]></Content>
					</xml>',
					$from,
					$to,
					time(),
					$content
				);

			default:
				return '';
		}
	}

	/**
	 * Parse XML message.
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
	 * Get user bookings.
	 *
	 * @param string $openid User OpenID.
	 * @return array
	 */
	private function get_user_bookings( $openid ) {
		$bookings = get_posts(
			array(
				'post_type'      => 'bkx_booking',
				'posts_per_page' => 10,
				'post_status'    => array( 'bkx-pending', 'bkx-ack' ),
				'meta_query'     => array(
					array(
						'key'   => '_wechat_openid',
						'value' => $openid,
					),
					array(
						'key'     => 'booking_date',
						'value'   => gmdate( 'Y-m-d' ),
						'compare' => '>=',
						'type'    => 'DATE',
					),
				),
				'orderby'        => 'meta_value',
				'meta_key'       => 'booking_date',
				'order'          => 'ASC',
			)
		);

		$result = array();

		foreach ( $bookings as $booking ) {
			$service_id = get_post_meta( $booking->ID, 'base_id', true );
			$service    = get_post( $service_id );

			$result[] = array(
				'id'      => $booking->ID,
				'service' => $service ? $service->post_title : '',
				'date'    => get_post_meta( $booking->ID, 'booking_date', true ),
				'time'    => get_post_meta( $booking->ID, 'booking_time', true ),
			);
		}

		return $result;
	}

	/**
	 * Store user preference.
	 *
	 * @param string $openid User OpenID.
	 * @param string $key    Preference key.
	 * @param mixed  $value  Preference value.
	 */
	private function store_user_preference( $openid, $key, $value ) {
		$users = get_users(
			array(
				'meta_key'   => 'wechat_openid',
				'meta_value' => $openid,
				'number'     => 1,
			)
		);

		if ( ! empty( $users ) ) {
			update_user_meta( $users[0]->ID, 'wechat_pref_' . $key, $value );
		}
	}

	/**
	 * Track referral.
	 *
	 * @param string $openid      User OpenID.
	 * @param string $referrer_id Referrer ID.
	 */
	private function track_referral( $openid, $referrer_id ) {
		$this->store_user_preference( $openid, 'referrer', $referrer_id );
	}

	/**
	 * Link booking to user.
	 *
	 * @param string $openid     User OpenID.
	 * @param int    $booking_id Booking ID.
	 */
	private function link_booking_to_user( $openid, $booking_id ) {
		update_post_meta( absint( $booking_id ), '_wechat_openid', $openid );
	}
}
