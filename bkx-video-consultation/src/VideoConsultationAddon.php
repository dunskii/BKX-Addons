<?php
/**
 * Video Consultation Addon Main Class.
 *
 * @package BookingX\VideoConsultation
 * @since   1.0.0
 */

namespace BookingX\VideoConsultation;

use BookingX\VideoConsultation\Services\RoomManager;
use BookingX\VideoConsultation\Services\ZoomProvider;
use BookingX\VideoConsultation\Services\GoogleMeetProvider;
use BookingX\VideoConsultation\Services\WebRTCProvider;
use BookingX\VideoConsultation\Services\RecordingManager;
use BookingX\VideoConsultation\Services\NotificationService;

/**
 * VideoConsultationAddon class.
 *
 * @since 1.0.0
 */
class VideoConsultationAddon {

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Services container.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'bkx_video_consultation_settings', array() );
	}

	/**
	 * Initialize the addon.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		$this->init_services();
		$this->register_hooks();
	}

	/**
	 * Initialize services.
	 *
	 * @since 1.0.0
	 */
	private function init_services() {
		$this->services['rooms']         = new RoomManager( $this->settings );
		$this->services['zoom']          = new ZoomProvider( $this->settings );
		$this->services['google_meet']   = new GoogleMeetProvider( $this->settings );
		$this->services['webrtc']        = new WebRTCProvider( $this->settings );
		$this->services['recordings']    = new RecordingManager( $this->settings );
		$this->services['notifications'] = new NotificationService( $this->settings );
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks() {
		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Frontend hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Booking integration.
		add_action( 'bkx_booking_created', array( $this, 'create_video_room' ), 10, 2 );
		add_action( 'bkx_booking_cancelled', array( $this, 'cancel_video_room' ), 10, 1 );
		add_filter( 'bkx_booking_meta_fields', array( $this, 'add_video_meta_fields' ) );

		// Settings.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_video_consultation', array( $this, 'render_settings_tab' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_create_video_room', array( $this, 'ajax_create_room' ) );
		add_action( 'wp_ajax_bkx_join_video_room', array( $this, 'ajax_join_room' ) );
		add_action( 'wp_ajax_nopriv_bkx_join_video_room', array( $this, 'ajax_join_room' ) );
		add_action( 'wp_ajax_bkx_end_video_session', array( $this, 'ajax_end_session' ) );
		add_action( 'wp_ajax_bkx_admit_participant', array( $this, 'ajax_admit_participant' ) );
		add_action( 'wp_ajax_bkx_get_video_room_status', array( $this, 'ajax_get_room_status' ) );
		add_action( 'wp_ajax_nopriv_bkx_get_video_room_status', array( $this, 'ajax_get_room_status' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Cron hooks.
		add_action( 'bkx_video_cleanup_recordings', array( $this->services['recordings'], 'cleanup_expired' ) );
		add_action( 'bkx_video_send_reminder', array( $this->services['notifications'], 'send_reminder' ), 10, 1 );

		// Shortcodes.
		add_shortcode( 'bkx_video_room', array( $this, 'render_video_room_shortcode' ) );
		add_shortcode( 'bkx_video_join', array( $this, 'render_join_button_shortcode' ) );
	}

	/**
	 * Add admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Video Consultation', 'bkx-video-consultation' ),
			__( 'Video Consultation', 'bkx-video-consultation' ),
			'manage_options',
			'bkx-video-consultation',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'bookingx_page_bkx-video-consultation' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-video-consultation-admin',
			BKX_VIDEO_CONSULTATION_URL . 'assets/css/admin.css',
			array(),
			BKX_VIDEO_CONSULTATION_VERSION
		);

		wp_enqueue_script(
			'bkx-video-consultation-admin',
			BKX_VIDEO_CONSULTATION_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_VIDEO_CONSULTATION_VERSION,
			true
		);

		wp_localize_script(
			'bkx-video-consultation-admin',
			'bkxVideoData',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'bkx_video_nonce' ),
				'i18n'     => array(
					'confirm_end' => __( 'Are you sure you want to end this session?', 'bkx-video-consultation' ),
					'confirm_delete' => __( 'Are you sure you want to delete this recording?', 'bkx-video-consultation' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_frontend_assets() {
		if ( ! $this->should_load_video_assets() ) {
			return;
		}

		wp_enqueue_style(
			'bkx-video-consultation-frontend',
			BKX_VIDEO_CONSULTATION_URL . 'assets/css/frontend.css',
			array(),
			BKX_VIDEO_CONSULTATION_VERSION
		);

		// WebRTC adapter for cross-browser compatibility.
		wp_enqueue_script(
			'webrtc-adapter',
			'https://webrtc.github.io/adapter/adapter-latest.js',
			array(),
			'8.2.3',
			true
		);

		wp_enqueue_script(
			'bkx-video-consultation-frontend',
			BKX_VIDEO_CONSULTATION_URL . 'assets/js/frontend.js',
			array( 'jquery', 'webrtc-adapter' ),
			BKX_VIDEO_CONSULTATION_VERSION,
			true
		);

		wp_localize_script(
			'bkx-video-consultation-frontend',
			'bkxVideoConfig',
			array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'bkx_video_nonce' ),
				'stun_servers' => $this->get_stun_servers(),
				'turn_server'  => $this->settings['webrtc_turn_server'] ?? '',
				'enable_chat'  => $this->settings['enable_chat'] ?? true,
				'enable_screen_share' => $this->settings['enable_screen_share'] ?? true,
				'i18n'         => array(
					'connecting'       => __( 'Connecting...', 'bkx-video-consultation' ),
					'connected'        => __( 'Connected', 'bkx-video-consultation' ),
					'disconnected'     => __( 'Disconnected', 'bkx-video-consultation' ),
					'waiting_room'     => __( 'Waiting for host to admit you...', 'bkx-video-consultation' ),
					'camera_error'     => __( 'Could not access camera', 'bkx-video-consultation' ),
					'microphone_error' => __( 'Could not access microphone', 'bkx-video-consultation' ),
					'connection_lost'  => __( 'Connection lost. Attempting to reconnect...', 'bkx-video-consultation' ),
					'session_ended'    => __( 'The session has ended.', 'bkx-video-consultation' ),
				),
			)
		);
	}

	/**
	 * Check if video assets should be loaded.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function should_load_video_assets() {
		global $post;

		// Load on video room pages.
		if ( isset( $_GET['bkx_video_room'] ) ) {
			return true;
		}

		// Check for shortcode in content.
		if ( $post && ( has_shortcode( $post->post_content, 'bkx_video_room' ) ||
			has_shortcode( $post->post_content, 'bkx_video_join' ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get STUN servers array.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private function get_stun_servers() {
		$stun_string = $this->settings['webrtc_stun_servers'] ?? 'stun:stun.l.google.com:19302';
		return array_filter( array_map( 'trim', explode( ',', $stun_string ) ) );
	}

	/**
	 * Render admin page.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_page() {
		include BKX_VIDEO_CONSULTATION_PATH . 'templates/admin/video-consultation.php';
	}

	/**
	 * Create video room for booking.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $booking_id Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function create_video_room( $booking_id, $booking_data ) {
		// Check if this is a video consultation booking.
		$is_video = get_post_meta( $booking_id, 'is_video_consultation', true );
		if ( ! $is_video ) {
			return;
		}

		$room = $this->services['rooms']->create_room( $booking_id, $booking_data );

		if ( ! is_wp_error( $room ) ) {
			// Schedule reminder notification.
			$reminder_minutes = $this->settings['reminder_before_minutes'] ?? 15;
			$booking_time     = strtotime( $booking_data['booking_date'] . ' ' . $booking_data['booking_time'] );
			$reminder_time    = $booking_time - ( $reminder_minutes * 60 );

			if ( $reminder_time > time() ) {
				wp_schedule_single_event( $reminder_time, 'bkx_video_send_reminder', array( $booking_id ) );
			}
		}
	}

	/**
	 * Cancel video room.
	 *
	 * @since 1.0.0
	 *
	 * @param int $booking_id Booking ID.
	 */
	public function cancel_video_room( $booking_id ) {
		$this->services['rooms']->cancel_room_by_booking( $booking_id );
	}

	/**
	 * Add video meta fields to booking.
	 *
	 * @since 1.0.0
	 *
	 * @param array $fields Meta fields.
	 * @return array
	 */
	public function add_video_meta_fields( $fields ) {
		$fields['is_video_consultation'] = array(
			'label'   => __( 'Video Consultation', 'bkx-video-consultation' ),
			'type'    => 'checkbox',
			'default' => false,
		);

		return $fields;
	}

	/**
	 * Add settings tab.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tabs Settings tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['video_consultation'] = __( 'Video Consultation', 'bkx-video-consultation' );
		return $tabs;
	}

	/**
	 * Render settings tab.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_tab() {
		include BKX_VIDEO_CONSULTATION_PATH . 'templates/admin/settings.php';
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-video/v1',
			'/rooms',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_rooms' ),
				'permission_callback' => array( $this, 'rest_check_permissions' ),
			)
		);

		register_rest_route(
			'bkx-video/v1',
			'/rooms/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_room' ),
				'permission_callback' => array( $this, 'rest_check_permissions' ),
			)
		);

		register_rest_route(
			'bkx-video/v1',
			'/signal',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_handle_signaling' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'bkx-video/v1',
			'/webhooks/zoom',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->services['zoom'], 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Check REST permissions.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function rest_check_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * REST: Get all rooms.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_rooms( $request ) {
		$rooms = $this->services['rooms']->get_rooms( $request->get_params() );
		return rest_ensure_response( $rooms );
	}

	/**
	 * REST: Get single room.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_room( $request ) {
		$room = $this->services['rooms']->get_room( $request->get_param( 'id' ) );
		return rest_ensure_response( $room );
	}

	/**
	 * REST: Handle WebRTC signaling.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_handle_signaling( $request ) {
		return $this->services['webrtc']->handle_signaling( $request );
	}

	/**
	 * AJAX: Create video room.
	 *
	 * @since 1.0.0
	 */
	public function ajax_create_room() {
		check_ajax_referer( 'bkx_video_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bkx-video-consultation' ) );
		}

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;

		if ( ! $booking_id ) {
			wp_send_json_error( __( 'Invalid booking ID.', 'bkx-video-consultation' ) );
		}

		$room = $this->services['rooms']->create_room( $booking_id );

		if ( is_wp_error( $room ) ) {
			wp_send_json_error( $room->get_error_message() );
		}

		wp_send_json_success( $room );
	}

	/**
	 * AJAX: Join video room.
	 *
	 * @since 1.0.0
	 */
	public function ajax_join_room() {
		check_ajax_referer( 'bkx_video_nonce', 'nonce' );

		$room_id = isset( $_POST['room_id'] ) ? sanitize_text_field( $_POST['room_id'] ) : '';
		$name    = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
		$is_host = isset( $_POST['is_host'] ) && $_POST['is_host'] === 'true';

		if ( empty( $room_id ) || empty( $name ) ) {
			wp_send_json_error( __( 'Missing required fields.', 'bkx-video-consultation' ) );
		}

		$result = $this->services['rooms']->join_room( $room_id, $name, $email, $is_host );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: End video session.
	 *
	 * @since 1.0.0
	 */
	public function ajax_end_session() {
		check_ajax_referer( 'bkx_video_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bkx-video-consultation' ) );
		}

		$room_id = isset( $_POST['room_id'] ) ? absint( $_POST['room_id'] ) : 0;

		$result = $this->services['rooms']->end_session( $room_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Admit participant from waiting room.
	 *
	 * @since 1.0.0
	 */
	public function ajax_admit_participant() {
		check_ajax_referer( 'bkx_video_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'bkx-video-consultation' ) );
		}

		$participant_id = isset( $_POST['participant_id'] ) ? absint( $_POST['participant_id'] ) : 0;

		$result = $this->services['rooms']->admit_participant( $participant_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: Get room status.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_room_status() {
		check_ajax_referer( 'bkx_video_nonce', 'nonce' );

		$room_id = isset( $_POST['room_id'] ) ? sanitize_text_field( $_POST['room_id'] ) : '';

		$status = $this->services['rooms']->get_room_status( $room_id );

		if ( is_wp_error( $status ) ) {
			wp_send_json_error( $status->get_error_message() );
		}

		wp_send_json_success( $status );
	}

	/**
	 * Render video room shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_video_room_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'room_id' => '',
			),
			$atts,
			'bkx_video_room'
		);

		$room_id = $atts['room_id'] ?: ( isset( $_GET['room'] ) ? sanitize_text_field( $_GET['room'] ) : '' );

		if ( empty( $room_id ) ) {
			return '<p>' . esc_html__( 'No video room specified.', 'bkx-video-consultation' ) . '</p>';
		}

		ob_start();
		include BKX_VIDEO_CONSULTATION_PATH . 'templates/frontend/video-room.php';
		return ob_get_clean();
	}

	/**
	 * Render join button shortcode.
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_join_button_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'booking_id' => '',
				'text'       => __( 'Join Video Consultation', 'bkx-video-consultation' ),
				'class'      => 'bkx-video-join-btn',
			),
			$atts,
			'bkx_video_join'
		);

		$booking_id = absint( $atts['booking_id'] );
		if ( ! $booking_id ) {
			return '';
		}

		$room = $this->services['rooms']->get_room_by_booking( $booking_id );
		if ( ! $room ) {
			return '';
		}

		$join_url = add_query_arg(
			array(
				'bkx_video_room' => 1,
				'room'           => $room->room_id,
			),
			home_url( '/video-consultation/' )
		);

		return sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $join_url ),
			esc_attr( $atts['class'] ),
			esc_html( $atts['text'] )
		);
	}

	/**
	 * Get a service instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string $service Service name.
	 * @return object|null
	 */
	public function get_service( $service ) {
		return $this->services[ $service ] ?? null;
	}
}
