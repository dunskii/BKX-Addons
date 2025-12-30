<?php
/**
 * GDPR/CCPA Compliance Suite main addon class.
 *
 * @package BookingX\GdprCompliance
 */

namespace BookingX\GdprCompliance;

defined( 'ABSPATH' ) || exit;

/**
 * GdprComplianceAddon class.
 */
class GdprComplianceAddon {

	/**
	 * Single instance.
	 *
	 * @var GdprComplianceAddon
	 */
	private static $instance = null;

	/**
	 * Services container.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Get instance.
	 *
	 * @return GdprComplianceAddon
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['consent']    = new Services\ConsentManager();
		$this->services['requests']   = new Services\DataRequestHandler();
		$this->services['export']     = new Services\DataExporter();
		$this->services['erasure']    = new Services\DataErasure();
		$this->services['cookies']    = new Services\CookieConsent();
		$this->services['retention']  = new Services\DataRetention();
		$this->services['breaches']   = new Services\BreachManager();
		$this->services['policy']     = new Services\PolicyGenerator();
	}

	/**
	 * Get service.
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 25 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Frontend hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_cookie_banner' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_gdpr_save_consent', array( $this, 'ajax_save_consent' ) );
		add_action( 'wp_ajax_nopriv_bkx_gdpr_save_consent', array( $this, 'ajax_save_consent' ) );
		add_action( 'wp_ajax_bkx_gdpr_save_cookie_consent', array( $this, 'ajax_save_cookie_consent' ) );
		add_action( 'wp_ajax_nopriv_bkx_gdpr_save_cookie_consent', array( $this, 'ajax_save_cookie_consent' ) );
		add_action( 'wp_ajax_bkx_gdpr_submit_request', array( $this, 'ajax_submit_request' ) );
		add_action( 'wp_ajax_nopriv_bkx_gdpr_submit_request', array( $this, 'ajax_submit_request' ) );
		add_action( 'wp_ajax_bkx_gdpr_process_request', array( $this, 'ajax_process_request' ) );
		add_action( 'wp_ajax_bkx_gdpr_export_data', array( $this, 'ajax_export_data' ) );
		add_action( 'wp_ajax_bkx_gdpr_report_breach', array( $this, 'ajax_report_breach' ) );
		add_action( 'wp_ajax_bkx_gdpr_generate_policy', array( $this, 'ajax_generate_policy' ) );

		// Cron hooks.
		add_action( 'bkx_gdpr_data_retention_check', array( $this, 'process_data_retention' ) );
		add_action( 'bkx_gdpr_request_expiry_check', array( $this, 'process_request_expiry' ) );

		// BookingX integration hooks.
		add_action( 'bkx_booking_form_after_fields', array( $this, 'render_consent_checkboxes' ) );
		add_action( 'bkx_before_booking_created', array( $this, 'validate_consent' ), 10, 1 );
		add_action( 'bkx_booking_created', array( $this, 'save_booking_consent' ), 10, 2 );

		// WordPress privacy hooks.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_data_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_data_eraser' ) );

		// Settings registration.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_gdpr', array( $this, 'render_settings_tab' ) );

		// Shortcodes.
		add_shortcode( 'bkx_privacy_request_form', array( $this, 'shortcode_privacy_request_form' ) );
		add_shortcode( 'bkx_consent_preferences', array( $this, 'shortcode_consent_preferences' ) );
		add_shortcode( 'bkx_cookie_settings', array( $this, 'shortcode_cookie_settings' ) );
		add_shortcode( 'bkx_ccpa_opt_out', array( $this, 'shortcode_ccpa_opt_out' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'GDPR Compliance', 'bkx-gdpr-compliance' ),
			__( 'GDPR Compliance', 'bkx-gdpr-compliance' ),
			'manage_options',
			'bkx-gdpr',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-gdpr' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-gdpr-admin',
			BKX_GDPR_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_GDPR_VERSION
		);

		wp_enqueue_script(
			'bkx-gdpr-admin',
			BKX_GDPR_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			BKX_GDPR_VERSION,
			true
		);

		wp_localize_script(
			'bkx-gdpr-admin',
			'bkxGdprAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_gdpr_admin' ),
				'i18n'    => array(
					'confirm'         => __( 'Are you sure?', 'bkx-gdpr-compliance' ),
					'processing'      => __( 'Processing...', 'bkx-gdpr-compliance' ),
					'success'         => __( 'Success!', 'bkx-gdpr-compliance' ),
					'error'           => __( 'An error occurred.', 'bkx-gdpr-compliance' ),
					'confirmDelete'   => __( 'This will permanently delete the data. Are you sure?', 'bkx-gdpr-compliance' ),
					'confirmProcess'  => __( 'Process this request now?', 'bkx-gdpr-compliance' ),
					'policyGenerated' => __( 'Policy generated successfully!', 'bkx-gdpr-compliance' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets() {
		$settings = get_option( 'bkx_gdpr_settings', array() );

		if ( empty( $settings['cookie_banner_enabled'] ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-gdpr-frontend',
			BKX_GDPR_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			BKX_GDPR_VERSION
		);

		wp_enqueue_script(
			'bkx-gdpr-frontend',
			BKX_GDPR_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_GDPR_VERSION,
			true
		);

		wp_localize_script(
			'bkx-gdpr-frontend',
			'bkxGdpr',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'bkx_gdpr_frontend' ),
				'cookieExpiry'  => 365,
				'cookieName'    => 'bkx_cookie_consent',
				'privacyPolicy' => get_privacy_policy_url(),
				'i18n'          => array(
					'accept'     => __( 'Accept All', 'bkx-gdpr-compliance' ),
					'reject'     => __( 'Reject All', 'bkx-gdpr-compliance' ),
					'customize'  => __( 'Customize', 'bkx-gdpr-compliance' ),
					'save'       => __( 'Save Preferences', 'bkx-gdpr-compliance' ),
					'necessary'  => __( 'Necessary', 'bkx-gdpr-compliance' ),
					'functional' => __( 'Functional', 'bkx-gdpr-compliance' ),
					'analytics'  => __( 'Analytics', 'bkx-gdpr-compliance' ),
					'marketing'  => __( 'Marketing', 'bkx-gdpr-compliance' ),
				),
			)
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = sanitize_text_field( $_GET['tab'] ?? 'dashboard' );
		include BKX_GDPR_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Render cookie banner.
	 */
	public function render_cookie_banner() {
		$settings = get_option( 'bkx_gdpr_settings', array() );

		if ( empty( $settings['cookie_banner_enabled'] ) ) {
			return;
		}

		// Check if consent already given.
		if ( isset( $_COOKIE['bkx_cookie_consent'] ) ) {
			return;
		}

		include BKX_GDPR_PLUGIN_DIR . 'templates/frontend/cookie-banner.php';
	}

	/**
	 * Render consent checkboxes on booking form.
	 */
	public function render_consent_checkboxes() {
		$settings = get_option( 'bkx_gdpr_settings', array() );

		if ( empty( $settings['consent_required'] ) ) {
			return;
		}

		include BKX_GDPR_PLUGIN_DIR . 'templates/frontend/consent-checkboxes.php';
	}

	/**
	 * Validate consent before booking.
	 *
	 * @param array $booking_data Booking data.
	 * @return array
	 */
	public function validate_consent( $booking_data ) {
		$settings = get_option( 'bkx_gdpr_settings', array() );

		if ( empty( $settings['consent_required'] ) ) {
			return $booking_data;
		}

		// Check if privacy consent was given.
		if ( empty( $_POST['bkx_privacy_consent'] ) ) {
			throw new \Exception( __( 'You must accept the privacy policy to proceed with the booking.', 'bkx-gdpr-compliance' ) );
		}

		return $booking_data;
	}

	/**
	 * Save booking consent.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function save_booking_consent( $booking_id, $booking_data ) {
		$email = $booking_data['customer_email'] ?? '';

		if ( empty( $email ) ) {
			return;
		}

		$consent_manager = $this->get_service( 'consent' );

		// Save privacy consent.
		if ( ! empty( $_POST['bkx_privacy_consent'] ) ) {
			$consent_manager->record_consent(
				$email,
				'privacy',
				true,
				__( 'I accept the privacy policy', 'bkx-gdpr-compliance' ),
				'booking_form'
			);
		}

		// Save marketing consent.
		if ( ! empty( $_POST['bkx_marketing_consent'] ) ) {
			$consent_manager->record_consent(
				$email,
				'marketing',
				true,
				__( 'I agree to receive marketing communications', 'bkx-gdpr-compliance' ),
				'booking_form'
			);
		}

		// Save third-party consent.
		if ( ! empty( $_POST['bkx_third_party_consent'] ) ) {
			$consent_manager->record_consent(
				$email,
				'third_party',
				true,
				__( 'I agree to share my data with third parties', 'bkx-gdpr-compliance' ),
				'booking_form'
			);
		}
	}

	/**
	 * AJAX: Save consent.
	 */
	public function ajax_save_consent() {
		check_ajax_referer( 'bkx_gdpr_frontend', 'nonce' );

		$email        = sanitize_email( $_POST['email'] ?? '' );
		$consent_type = sanitize_text_field( $_POST['consent_type'] ?? '' );
		$consent      = ! empty( $_POST['consent'] );
		$text         = sanitize_textarea_field( $_POST['text'] ?? '' );

		if ( empty( $email ) || empty( $consent_type ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'bkx-gdpr-compliance' ) ) );
		}

		$consent_manager = $this->get_service( 'consent' );
		$result          = $consent_manager->record_consent( $email, $consent_type, $consent, $text, 'ajax' );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Consent saved successfully.', 'bkx-gdpr-compliance' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save consent.', 'bkx-gdpr-compliance' ) ) );
		}
	}

	/**
	 * AJAX: Save cookie consent.
	 */
	public function ajax_save_cookie_consent() {
		check_ajax_referer( 'bkx_gdpr_frontend', 'nonce' );

		$cookies    = $this->get_service( 'cookies' );
		$visitor_id = sanitize_text_field( $_POST['visitor_id'] ?? '' );
		$consents   = array(
			'necessary'  => true,
			'functional' => ! empty( $_POST['functional'] ),
			'analytics'  => ! empty( $_POST['analytics'] ),
			'marketing'  => ! empty( $_POST['marketing'] ),
		);

		$result = $cookies->save_consent( $visitor_id, $consents );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Cookie preferences saved.', 'bkx-gdpr-compliance' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to save preferences.', 'bkx-gdpr-compliance' ) ) );
		}
	}

	/**
	 * AJAX: Submit data request.
	 */
	public function ajax_submit_request() {
		check_ajax_referer( 'bkx_gdpr_frontend', 'nonce' );

		$email        = sanitize_email( $_POST['email'] ?? '' );
		$request_type = sanitize_text_field( $_POST['request_type'] ?? '' );

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please provide a valid email address.', 'bkx-gdpr-compliance' ) ) );
		}

		$valid_types = array( 'export', 'erasure', 'access', 'rectification', 'restriction', 'portability' );
		if ( ! in_array( $request_type, $valid_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request type.', 'bkx-gdpr-compliance' ) ) );
		}

		$request_handler = $this->get_service( 'requests' );
		$result          = $request_handler->create_request( $email, $request_type );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Your request has been submitted. Please check your email to verify the request.', 'bkx-gdpr-compliance' ),
			)
		);
	}

	/**
	 * AJAX: Process data request (admin).
	 */
	public function ajax_process_request() {
		check_ajax_referer( 'bkx_gdpr_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'bkx-gdpr-compliance' ) ) );
		}

		$request_id = absint( $_POST['request_id'] ?? 0 );
		$action     = sanitize_text_field( $_POST['request_action'] ?? '' );

		if ( empty( $request_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request ID.', 'bkx-gdpr-compliance' ) ) );
		}

		$request_handler = $this->get_service( 'requests' );
		$result          = $request_handler->process_request( $request_id, $action );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Request processed successfully.', 'bkx-gdpr-compliance' ) ) );
	}

	/**
	 * AJAX: Export data (admin).
	 */
	public function ajax_export_data() {
		check_ajax_referer( 'bkx_gdpr_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'bkx-gdpr-compliance' ) ) );
		}

		$email  = sanitize_email( $_POST['email'] ?? '' );
		$format = sanitize_text_field( $_POST['format'] ?? 'json' );

		if ( empty( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Email is required.', 'bkx-gdpr-compliance' ) ) );
		}

		$exporter = $this->get_service( 'export' );
		$result   = $exporter->export_user_data( $email, $format );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'Data exported successfully.', 'bkx-gdpr-compliance' ),
				'download_url' => $result['url'],
			)
		);
	}

	/**
	 * AJAX: Report breach.
	 */
	public function ajax_report_breach() {
		check_ajax_referer( 'bkx_gdpr_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'bkx-gdpr-compliance' ) ) );
		}

		$breach_manager = $this->get_service( 'breaches' );
		$data           = array(
			'breach_date'       => sanitize_text_field( $_POST['breach_date'] ?? '' ),
			'discovered_date'   => sanitize_text_field( $_POST['discovered_date'] ?? '' ),
			'nature'            => sanitize_textarea_field( $_POST['nature'] ?? '' ),
			'data_affected'     => sanitize_textarea_field( $_POST['data_affected'] ?? '' ),
			'subjects_affected' => absint( $_POST['subjects_affected'] ?? 0 ),
			'consequences'      => sanitize_textarea_field( $_POST['consequences'] ?? '' ),
			'measures_taken'    => sanitize_textarea_field( $_POST['measures_taken'] ?? '' ),
		);

		$result = $breach_manager->report_breach( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Breach reported successfully.', 'bkx-gdpr-compliance' ) ) );
	}

	/**
	 * AJAX: Generate policy.
	 */
	public function ajax_generate_policy() {
		check_ajax_referer( 'bkx_gdpr_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'bkx-gdpr-compliance' ) ) );
		}

		$policy_type = sanitize_text_field( $_POST['policy_type'] ?? 'privacy' );
		$policy_gen  = $this->get_service( 'policy' );
		$content     = $policy_gen->generate( $policy_type );

		wp_send_json_success(
			array(
				'message' => __( 'Policy generated successfully.', 'bkx-gdpr-compliance' ),
				'content' => $content,
			)
		);
	}

	/**
	 * Process data retention.
	 */
	public function process_data_retention() {
		$retention = $this->get_service( 'retention' );
		$retention->process_expired_data();
	}

	/**
	 * Process request expiry.
	 */
	public function process_request_expiry() {
		$requests = $this->get_service( 'requests' );
		$requests->expire_old_requests();
	}

	/**
	 * Register WordPress data exporter.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public function register_data_exporter( $exporters ) {
		$exporters['bkx-gdpr-bookings'] = array(
			'exporter_friendly_name' => __( 'BookingX Bookings', 'bkx-gdpr-compliance' ),
			'callback'               => array( $this->get_service( 'export' ), 'wp_exporter_callback' ),
		);
		$exporters['bkx-gdpr-consents'] = array(
			'exporter_friendly_name' => __( 'BookingX Consents', 'bkx-gdpr-compliance' ),
			'callback'               => array( $this->get_service( 'consent' ), 'wp_exporter_callback' ),
		);
		return $exporters;
	}

	/**
	 * Register WordPress data eraser.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public function register_data_eraser( $erasers ) {
		$erasers['bkx-gdpr-bookings'] = array(
			'eraser_friendly_name' => __( 'BookingX Bookings', 'bkx-gdpr-compliance' ),
			'callback'             => array( $this->get_service( 'erasure' ), 'wp_eraser_callback' ),
		);
		$erasers['bkx-gdpr-consents'] = array(
			'eraser_friendly_name' => __( 'BookingX Consents', 'bkx-gdpr-compliance' ),
			'callback'             => array( $this->get_service( 'consent' ), 'wp_eraser_callback' ),
		);
		return $erasers;
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['gdpr'] = __( 'GDPR/CCPA', 'bkx-gdpr-compliance' );
		return $tabs;
	}

	/**
	 * Render settings tab.
	 */
	public function render_settings_tab() {
		include BKX_GDPR_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}

	/**
	 * Shortcode: Privacy request form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_privacy_request_form( $atts ) {
		$atts = shortcode_atts(
			array(
				'types' => 'export,erasure',
			),
			$atts
		);

		ob_start();
		include BKX_GDPR_PLUGIN_DIR . 'templates/frontend/request-form.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: Consent preferences.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_consent_preferences( $atts ) {
		ob_start();
		include BKX_GDPR_PLUGIN_DIR . 'templates/frontend/consent-preferences.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: Cookie settings.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_cookie_settings( $atts ) {
		ob_start();
		include BKX_GDPR_PLUGIN_DIR . 'templates/frontend/cookie-settings.php';
		return ob_get_clean();
	}

	/**
	 * Shortcode: CCPA opt-out.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_ccpa_opt_out( $atts ) {
		ob_start();
		include BKX_GDPR_PLUGIN_DIR . 'templates/frontend/ccpa-opt-out.php';
		return ob_get_clean();
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-gdpr/v1',
			'/verify/(?P<token>[a-zA-Z0-9]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_verify_request' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'token' => array(
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return preg_match( '/^[a-zA-Z0-9]{32,64}$/', $param );
						},
					),
				),
			)
		);

		register_rest_route(
			'bkx-gdpr/v1',
			'/consent',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_record_consent' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * REST: Verify request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_verify_request( $request ) {
		$token           = $request->get_param( 'token' );
		$request_handler = $this->get_service( 'requests' );
		$result          = $request_handler->verify_request( $token );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Your request has been verified and will be processed shortly.', 'bkx-gdpr-compliance' ),
			),
			200
		);
	}

	/**
	 * REST: Record consent.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_record_consent( $request ) {
		$email        = sanitize_email( $request->get_param( 'email' ) );
		$consent_type = sanitize_text_field( $request->get_param( 'consent_type' ) );
		$consent      = (bool) $request->get_param( 'consent' );
		$text         = sanitize_textarea_field( $request->get_param( 'text' ) );

		if ( empty( $email ) || ! is_email( $email ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Valid email is required.', 'bkx-gdpr-compliance' ),
				),
				400
			);
		}

		$consent_manager = $this->get_service( 'consent' );
		$result          = $consent_manager->record_consent( $email, $consent_type, $consent, $text, 'api' );

		if ( $result ) {
			return new \WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Consent recorded successfully.', 'bkx-gdpr-compliance' ),
				),
				200
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Failed to record consent.', 'bkx-gdpr-compliance' ),
			),
			500
		);
	}
}
