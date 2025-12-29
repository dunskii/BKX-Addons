<?php
/**
 * Main Alexa Addon Class.
 *
 * @package BookingX\Alexa
 */

namespace BookingX\Alexa;

use BookingX\Alexa\Services\SkillHandler;
use BookingX\Alexa\Services\IntentHandler;
use BookingX\Alexa\Services\SessionManager;
use BookingX\Alexa\Services\AccountLinker;

defined( 'ABSPATH' ) || exit;

/**
 * AlexaAddon class.
 */
class AlexaAddon {

	/**
	 * Skill handler.
	 *
	 * @var SkillHandler
	 */
	private $skill_handler;

	/**
	 * Intent handler.
	 *
	 * @var IntentHandler
	 */
	private $intent_handler;

	/**
	 * Session manager.
	 *
	 * @var SessionManager
	 */
	private $session_manager;

	/**
	 * Account linker.
	 *
	 * @var AccountLinker
	 */
	private $account_linker;

	/**
	 * Initialize the addon.
	 */
	public function init() {
		$this->load_services();
		$this->register_hooks();
	}

	/**
	 * Load services.
	 */
	private function load_services() {
		$this->session_manager = new SessionManager();
		$this->account_linker  = new AccountLinker();
		$this->intent_handler  = new IntentHandler( $this->session_manager, $this->account_linker );
		$this->skill_handler   = new SkillHandler( $this->intent_handler );
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// REST API endpoints.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_alexa_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_alexa_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_bkx_alexa_get_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_bkx_alexa_export_skill', array( $this, 'ajax_export_skill' ) );

		// Cron for session cleanup.
		add_action( 'bkx_alexa_cleanup_sessions', array( $this, 'cleanup_expired_sessions' ) );

		if ( ! wp_next_scheduled( 'bkx_alexa_cleanup_sessions' ) ) {
			wp_schedule_event( time(), 'hourly', 'bkx_alexa_cleanup_sessions' );
		}

		// Settings link.
		add_filter( 'plugin_action_links_' . BKX_ALEXA_BASENAME, array( $this, 'add_settings_link' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Amazon Alexa', 'bkx-alexa' ),
			__( 'Amazon Alexa', 'bkx-alexa' ),
			'manage_options',
			'bkx-alexa',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-alexa' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-alexa-admin',
			BKX_ALEXA_URL . 'assets/css/admin.css',
			array(),
			BKX_ALEXA_VERSION
		);

		wp_enqueue_script(
			'bkx-alexa-admin',
			BKX_ALEXA_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			BKX_ALEXA_VERSION,
			true
		);

		wp_localize_script(
			'bkx-alexa-admin',
			'bkxAlexa',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'bkx_alexa' ),
				'skillUrl'   => rest_url( 'bkx-alexa/v1/skill' ),
				'i18n'       => array(
					'saved'         => __( 'Settings saved!', 'bkx-alexa' ),
					'testSuccess'   => __( 'Connection test successful!', 'bkx-alexa' ),
					'testFailed'    => __( 'Connection test failed.', 'bkx-alexa' ),
					'exportSuccess' => __( 'Skill configuration exported!', 'bkx-alexa' ),
				),
			)
		);
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes() {
		// Main skill endpoint for Alexa.
		register_rest_route(
			'bkx-alexa/v1',
			'/skill',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->skill_handler, 'handle_request' ),
				'permission_callback' => array( $this, 'verify_alexa_request' ),
			)
		);

		// Account linking endpoints.
		register_rest_route(
			'bkx-alexa/v1',
			'/auth',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->account_linker, 'handle_auth_request' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'bkx-alexa/v1',
			'/token',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->account_linker, 'handle_token_request' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Verify Alexa request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function verify_alexa_request( $request ) {
		$settings = get_option( 'bkx_alexa_settings', array() );

		// In development mode, skip verification.
		if ( ! empty( $settings['dev_mode'] ) ) {
			return true;
		}

		// Verify the request is from Alexa.
		$signature_url = $request->get_header( 'signaturecertchainurl' );
		$signature     = $request->get_header( 'signature-256' ) ?? $request->get_header( 'signature' );

		if ( empty( $signature_url ) || empty( $signature ) ) {
			return false;
		}

		// Verify certificate URL.
		if ( ! $this->verify_certificate_url( $signature_url ) ) {
			return false;
		}

		// Verify application ID from request body.
		$body = $request->get_json_params();
		if ( ! empty( $settings['skill_id'] ) ) {
			$request_app_id = $body['session']['application']['applicationId'] ?? $body['context']['System']['application']['applicationId'] ?? '';
			if ( $request_app_id !== $settings['skill_id'] ) {
				return false;
			}
		}

		// Verify timestamp is within tolerance (150 seconds).
		$request_timestamp = $body['request']['timestamp'] ?? '';
		if ( ! empty( $request_timestamp ) ) {
			$request_time = strtotime( $request_timestamp );
			$current_time = time();
			if ( abs( $current_time - $request_time ) > 150 ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Verify certificate URL.
	 *
	 * @param string $url Certificate URL.
	 * @return bool
	 */
	private function verify_certificate_url( $url ) {
		$parsed = wp_parse_url( $url );

		if ( empty( $parsed ) ) {
			return false;
		}

		// Must be HTTPS.
		if ( ( $parsed['scheme'] ?? '' ) !== 'https' ) {
			return false;
		}

		// Must be from Amazon.
		$host = strtolower( $parsed['host'] ?? '' );
		if ( $host !== 's3.amazonaws.com' && ! preg_match( '/^s3\.[a-z0-9-]+\.amazonaws\.com$/', $host ) ) {
			return false;
		}

		// Path must start with /echo.api/.
		$path = $parsed['path'] ?? '';
		if ( strpos( $path, '/echo.api/' ) !== 0 ) {
			return false;
		}

		// Port must be 443 or not specified.
		$port = $parsed['port'] ?? 443;
		if ( (int) $port !== 443 ) {
			return false;
		}

		return true;
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification
		include BKX_ALEXA_PATH . 'templates/admin/page.php';
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_alexa', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-alexa' ) ) );
		}

		$settings = array(
			'enabled'          => isset( $_POST['enabled'] ) ? 1 : 0,
			'skill_id'         => isset( $_POST['skill_id'] ) ? sanitize_text_field( wp_unslash( $_POST['skill_id'] ) ) : '',
			'client_id'        => isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '',
			'client_secret'    => isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '',
			'invocation_name'  => isset( $_POST['invocation_name'] ) ? sanitize_text_field( wp_unslash( $_POST['invocation_name'] ) ) : '',
			'welcome_message'  => isset( $_POST['welcome_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['welcome_message'] ) ) : '',
			'dev_mode'         => isset( $_POST['dev_mode'] ) ? 1 : 0,
			'require_linking'  => isset( $_POST['require_linking'] ) ? 1 : 0,
		);

		update_option( 'bkx_alexa_settings', $settings );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'bkx-alexa' ) ) );
	}

	/**
	 * AJAX: Test connection.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'bkx_alexa', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-alexa' ) ) );
		}

		$settings = get_option( 'bkx_alexa_settings', array() );

		if ( empty( $settings['skill_id'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Skill ID is required.', 'bkx-alexa' ) ) );
		}

		// Test Alexa Skills Kit connection.
		$response = wp_remote_get(
			'https://api.amazonalexa.com/v1/skills/' . $settings['skill_id'] . '/status',
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );

		// 401/403 is expected without auth - means the endpoint exists.
		if ( in_array( $code, array( 200, 401, 403 ), true ) ) {
			wp_send_json_success( array( 'message' => __( 'Connection successful!', 'bkx-alexa' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'Connection failed. Check your Skill ID.', 'bkx-alexa' ) ) );
	}

	/**
	 * AJAX: Get stats.
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'bkx_alexa', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-alexa' ) ) );
		}

		global $wpdb;

		$logs_table     = $wpdb->prefix . 'bkx_alexa_logs';
		$accounts_table = $wpdb->prefix . 'bkx_alexa_accounts';

		$stats = array(
			'total_requests'   => $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table}" ), // phpcs:ignore
			'successful'       => $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table} WHERE status = 'success'" ), // phpcs:ignore
			'bookings_created' => $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table} WHERE booking_id IS NOT NULL" ), // phpcs:ignore
			'linked_accounts'  => $wpdb->get_var( "SELECT COUNT(*) FROM {$accounts_table}" ), // phpcs:ignore
			'today_requests'   => $wpdb->get_var( // phpcs:ignore
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$logs_table} WHERE created_at >= %s",
					gmdate( 'Y-m-d 00:00:00' )
				)
			),
		);

		wp_send_json_success( $stats );
	}

	/**
	 * AJAX: Export skill configuration.
	 */
	public function ajax_export_skill() {
		check_ajax_referer( 'bkx_alexa', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-alexa' ) ) );
		}

		$settings = get_option( 'bkx_alexa_settings', array() );

		// Generate interaction model.
		$interaction_model = array(
			'interactionModel' => array(
				'languageModel' => array(
					'invocationName' => $settings['invocation_name'] ?? 'book appointment',
					'intents'        => array(
						array(
							'name'    => 'LaunchIntent',
							'samples' => array(),
						),
						array(
							'name'    => 'ListServicesIntent',
							'samples' => array(
								'what services do you offer',
								'list services',
								'show me services',
								'what can I book',
								'available services',
							),
						),
						array(
							'name'    => 'CheckAvailabilityIntent',
							'slots'   => array(
								array(
									'name' => 'date',
									'type' => 'AMAZON.DATE',
								),
							),
							'samples' => array(
								'check availability',
								'what times are available',
								'when can I book',
								'check availability for {date}',
								'what times are available on {date}',
							),
						),
						array(
							'name'    => 'BookAppointmentIntent',
							'slots'   => array(
								array(
									'name' => 'service',
									'type' => 'ServiceType',
								),
								array(
									'name' => 'date',
									'type' => 'AMAZON.DATE',
								),
								array(
									'name' => 'time',
									'type' => 'AMAZON.TIME',
								),
							),
							'samples' => array(
								'book an appointment',
								'make a reservation',
								'schedule a booking',
								'book {service}',
								'book {service} for {date}',
								'book {service} at {time}',
								'book {service} for {date} at {time}',
							),
						),
						array(
							'name'    => 'SelectServiceIntent',
							'slots'   => array(
								array(
									'name' => 'service',
									'type' => 'ServiceType',
								),
							),
							'samples' => array(
								'{service}',
								'I want {service}',
								'book {service}',
								'select {service}',
							),
						),
						array(
							'name'    => 'SelectDateIntent',
							'slots'   => array(
								array(
									'name' => 'date',
									'type' => 'AMAZON.DATE',
								),
							),
							'samples' => array(
								'{date}',
								'on {date}',
								'for {date}',
							),
						),
						array(
							'name'    => 'SelectTimeIntent',
							'slots'   => array(
								array(
									'name' => 'time',
									'type' => 'AMAZON.TIME',
								),
							),
							'samples' => array(
								'{time}',
								'at {time}',
							),
						),
						array(
							'name'    => 'ConfirmBookingIntent',
							'samples' => array(
								'yes',
								'confirm',
								'that sounds good',
								'book it',
								'yes please',
							),
						),
						array(
							'name'    => 'CancelBookingIntent',
							'slots'   => array(
								array(
									'name' => 'booking_id',
									'type' => 'AMAZON.NUMBER',
								),
							),
							'samples' => array(
								'cancel my booking',
								'cancel booking {booking_id}',
								'cancel appointment',
							),
						),
						array(
							'name'    => 'MyBookingsIntent',
							'samples' => array(
								'my bookings',
								'show my appointments',
								'list my bookings',
								'what appointments do I have',
							),
						),
						array(
							'name'    => 'AMAZON.HelpIntent',
							'samples' => array(),
						),
						array(
							'name'    => 'AMAZON.StopIntent',
							'samples' => array(),
						),
						array(
							'name'    => 'AMAZON.CancelIntent',
							'samples' => array(),
						),
						array(
							'name'    => 'AMAZON.FallbackIntent',
							'samples' => array(),
						),
					),
					'types'          => array(
						array(
							'name'   => 'ServiceType',
							'values' => $this->get_service_slot_values(),
						),
					),
				),
			),
		);

		// Generate skill manifest.
		$manifest = array(
			'manifest' => array(
				'manifestVersion' => '1.0',
				'publishingInformation' => array(
					'locales' => array(
						'en-US' => array(
							'name'        => get_bloginfo( 'name' ) . ' Booking',
							'summary'     => 'Book appointments via voice',
							'description' => 'Book appointments, check availability, and manage your bookings using Alexa.',
							'examplePhrases' => array(
								'Alexa, open ' . ( $settings['invocation_name'] ?? 'book appointment' ),
								'Alexa, ask ' . ( $settings['invocation_name'] ?? 'book appointment' ) . ' to list services',
								'Alexa, ask ' . ( $settings['invocation_name'] ?? 'book appointment' ) . ' to check availability',
							),
						),
					),
					'isAvailableWorldwide' => false,
					'category'             => 'BUSINESS_AND_FINANCE',
				),
				'apis' => array(
					'custom' => array(
						'endpoint' => array(
							'uri' => rest_url( 'bkx-alexa/v1/skill' ),
						),
					),
				),
				'permissions' => array(),
			),
		);

		if ( ! empty( $settings['require_linking'] ) ) {
			$manifest['manifest']['apis']['custom']['interfaces'] = array();
			$manifest['manifest']['accountLinking'] = array(
				'accessTokenScheme'      => 'HTTP_BASIC',
				'accessTokenUrl'         => rest_url( 'bkx-alexa/v1/token' ),
				'authorizationUrl'       => rest_url( 'bkx-alexa/v1/auth' ),
				'clientId'               => $settings['client_id'] ?? '',
				'scopes'                 => array( 'bookings:read', 'bookings:write' ),
			);
		}

		wp_send_json_success( array(
			'interaction_model' => $interaction_model,
			'manifest'          => $manifest,
		) );
	}

	/**
	 * Get service slot values for interaction model.
	 *
	 * @return array
	 */
	private function get_service_slot_values() {
		$services = get_posts( array(
			'post_type'      => 'bkx_base',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
		) );

		$values = array();
		foreach ( $services as $service ) {
			$values[] = array(
				'id'   => (string) $service->ID,
				'name' => array(
					'value'    => $service->post_title,
					'synonyms' => array(),
				),
			);
		}

		return $values;
	}

	/**
	 * Cleanup expired sessions.
	 */
	public function cleanup_expired_sessions() {
		$this->session_manager->cleanup_expired();
	}

	/**
	 * Add settings link.
	 *
	 * @param array $links Plugin links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=bkx-alexa' ) . '">' . __( 'Settings', 'bkx-alexa' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}
