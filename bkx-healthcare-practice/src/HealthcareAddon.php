<?php
/**
 * Healthcare Addon main class.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

namespace BookingX\HealthcarePractice;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasAjax;

/**
 * Main Healthcare Practice addon class.
 *
 * @since 1.0.0
 */
class HealthcareAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasAjax;

	/**
	 * Singleton instance.
	 *
	 * @var HealthcareAddon|null
	 */
	private static ?HealthcareAddon $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return HealthcareAddon
	 */
	public static function get_instance(): HealthcareAddon {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Get addon ID.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_id(): string {
		return 'bkx-healthcare-practice';
	}

	/**
	 * Get addon name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Healthcare Practice Management', 'bkx-healthcare-practice' );
	}

	/**
	 * Get addon version.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_version(): string {
		return BKX_HEALTHCARE_VERSION;
	}

	/**
	 * Initialize the addon.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		$this->init_settings( 'bkx_healthcare_settings' );
		$this->init_license( 'bkx_healthcare_practice' );
		$this->init_database();
		$this->init_ajax();

		// Register custom post types.
		add_action( 'init', array( $this, 'register_post_types' ) );

		// Register taxonomies.
		add_action( 'init', array( $this, 'register_taxonomies' ) );

		// Admin hooks.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
			add_action( 'save_post', array( $this, 'save_meta_boxes' ), 10, 2 );
		}

		// Frontend hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Shortcodes.
		add_shortcode( 'bkx_patient_intake', array( $this, 'render_patient_intake_shortcode' ) );
		add_shortcode( 'bkx_consent_form', array( $this, 'render_consent_form_shortcode' ) );
		add_shortcode( 'bkx_patient_portal', array( $this, 'render_patient_portal_shortcode' ) );
		add_shortcode( 'bkx_telemedicine', array( $this, 'render_telemedicine_shortcode' ) );

		// BookingX integration hooks.
		add_action( 'bkx_before_booking_form', array( $this, 'check_consent_requirements' ) );
		add_action( 'bkx_booking_created', array( $this, 'process_patient_intake' ), 10, 2 );
		add_action( 'bkx_booking_created', array( $this, 'schedule_appointment_reminders' ), 10, 2 );
		add_filter( 'bkx_booking_meta_fields', array( $this, 'add_healthcare_meta_fields' ) );

		// HIPAA compliance hooks.
		add_action( 'bkx_healthcare_audit_log', array( $this, 'log_hipaa_event' ), 10, 3 );
		add_filter( 'bkx_export_booking_data', array( $this, 'include_patient_data' ), 10, 2 );

		// Cron events.
		add_action( 'bkx_healthcare_send_reminders', array( $this, 'send_appointment_reminders' ) );
		add_action( 'bkx_healthcare_data_retention', array( $this, 'process_data_retention' ) );

		// Initialize services.
		$this->init_services();
	}

	/**
	 * Initialize services.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_services(): void {
		Services\PatientIntakeService::get_instance();
		Services\ConsentService::get_instance();
		Services\PatientPortalService::get_instance();
		Services\InsuranceService::get_instance();
		Services\TelemedicineService::get_instance();
	}

	/**
	 * Register custom post types.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_types(): void {
		// Consent Form Template post type.
		register_post_type(
			'bkx_consent_form',
			array(
				'labels'              => array(
					'name'               => __( 'Consent Forms', 'bkx-healthcare-practice' ),
					'singular_name'      => __( 'Consent Form', 'bkx-healthcare-practice' ),
					'add_new'            => __( 'Add New', 'bkx-healthcare-practice' ),
					'add_new_item'       => __( 'Add New Consent Form', 'bkx-healthcare-practice' ),
					'edit_item'          => __( 'Edit Consent Form', 'bkx-healthcare-practice' ),
					'new_item'           => __( 'New Consent Form', 'bkx-healthcare-practice' ),
					'view_item'          => __( 'View Consent Form', 'bkx-healthcare-practice' ),
					'search_items'       => __( 'Search Consent Forms', 'bkx-healthcare-practice' ),
					'not_found'          => __( 'No consent forms found', 'bkx-healthcare-practice' ),
					'not_found_in_trash' => __( 'No consent forms found in trash', 'bkx-healthcare-practice' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'bookingx',
				'capability_type'     => 'post',
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'show_in_rest'        => true,
			)
		);

		// Intake Form Template post type.
		register_post_type(
			'bkx_intake_form',
			array(
				'labels'              => array(
					'name'               => __( 'Intake Forms', 'bkx-healthcare-practice' ),
					'singular_name'      => __( 'Intake Form', 'bkx-healthcare-practice' ),
					'add_new'            => __( 'Add New', 'bkx-healthcare-practice' ),
					'add_new_item'       => __( 'Add New Intake Form', 'bkx-healthcare-practice' ),
					'edit_item'          => __( 'Edit Intake Form', 'bkx-healthcare-practice' ),
					'new_item'           => __( 'New Intake Form', 'bkx-healthcare-practice' ),
					'view_item'          => __( 'View Intake Form', 'bkx-healthcare-practice' ),
					'search_items'       => __( 'Search Intake Forms', 'bkx-healthcare-practice' ),
					'not_found'          => __( 'No intake forms found', 'bkx-healthcare-practice' ),
					'not_found_in_trash' => __( 'No intake forms found in trash', 'bkx-healthcare-practice' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'bookingx',
				'capability_type'     => 'post',
				'hierarchical'        => false,
				'supports'            => array( 'title', 'revisions' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'show_in_rest'        => true,
			)
		);

		// Provider profile (extends bkx_seat).
		register_post_type(
			'bkx_provider',
			array(
				'labels'              => array(
					'name'               => __( 'Providers', 'bkx-healthcare-practice' ),
					'singular_name'      => __( 'Provider', 'bkx-healthcare-practice' ),
					'add_new'            => __( 'Add New', 'bkx-healthcare-practice' ),
					'add_new_item'       => __( 'Add New Provider', 'bkx-healthcare-practice' ),
					'edit_item'          => __( 'Edit Provider', 'bkx-healthcare-practice' ),
					'new_item'           => __( 'New Provider', 'bkx-healthcare-practice' ),
					'view_item'          => __( 'View Provider', 'bkx-healthcare-practice' ),
					'search_items'       => __( 'Search Providers', 'bkx-healthcare-practice' ),
					'not_found'          => __( 'No providers found', 'bkx-healthcare-practice' ),
					'not_found_in_trash' => __( 'No providers found in trash', 'bkx-healthcare-practice' ),
				),
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => 'bookingx',
				'capability_type'     => 'post',
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'has_archive'         => true,
				'rewrite'             => array( 'slug' => 'providers' ),
				'show_in_rest'        => true,
			)
		);
	}

	/**
	 * Register taxonomies.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_taxonomies(): void {
		// Medical specialty.
		register_taxonomy(
			'bkx_medical_specialty',
			array( 'bkx_provider', 'bkx_base' ),
			array(
				'labels'            => array(
					'name'          => __( 'Medical Specialties', 'bkx-healthcare-practice' ),
					'singular_name' => __( 'Specialty', 'bkx-healthcare-practice' ),
					'search_items'  => __( 'Search Specialties', 'bkx-healthcare-practice' ),
					'all_items'     => __( 'All Specialties', 'bkx-healthcare-practice' ),
					'edit_item'     => __( 'Edit Specialty', 'bkx-healthcare-practice' ),
					'update_item'   => __( 'Update Specialty', 'bkx-healthcare-practice' ),
					'add_new_item'  => __( 'Add New Specialty', 'bkx-healthcare-practice' ),
					'new_item_name' => __( 'New Specialty Name', 'bkx-healthcare-practice' ),
				),
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'specialty' ),
				'show_in_rest'      => true,
			)
		);

		// Appointment type.
		register_taxonomy(
			'bkx_appointment_type',
			array( 'bkx_base' ),
			array(
				'labels'            => array(
					'name'          => __( 'Appointment Types', 'bkx-healthcare-practice' ),
					'singular_name' => __( 'Appointment Type', 'bkx-healthcare-practice' ),
					'search_items'  => __( 'Search Types', 'bkx-healthcare-practice' ),
					'all_items'     => __( 'All Types', 'bkx-healthcare-practice' ),
					'edit_item'     => __( 'Edit Type', 'bkx-healthcare-practice' ),
					'update_item'   => __( 'Update Type', 'bkx-healthcare-practice' ),
					'add_new_item'  => __( 'Add New Type', 'bkx-healthcare-practice' ),
					'new_item_name' => __( 'New Type Name', 'bkx-healthcare-practice' ),
				),
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'appointment-type' ),
				'show_in_rest'      => true,
			)
		);

		// Insurance network.
		register_taxonomy(
			'bkx_insurance_network',
			array( 'bkx_provider' ),
			array(
				'labels'            => array(
					'name'          => __( 'Insurance Networks', 'bkx-healthcare-practice' ),
					'singular_name' => __( 'Insurance Network', 'bkx-healthcare-practice' ),
					'search_items'  => __( 'Search Networks', 'bkx-healthcare-practice' ),
					'all_items'     => __( 'All Networks', 'bkx-healthcare-practice' ),
					'edit_item'     => __( 'Edit Network', 'bkx-healthcare-practice' ),
					'update_item'   => __( 'Update Network', 'bkx-healthcare-practice' ),
					'add_new_item'  => __( 'Add New Network', 'bkx-healthcare-practice' ),
					'new_item_name' => __( 'New Network Name', 'bkx-healthcare-practice' ),
				),
				'hierarchical'      => false,
				'show_ui'           => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'insurance' ),
				'show_in_rest'      => true,
			)
		);
	}

	/**
	 * Add admin menu items.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_submenu_page(
			'bookingx',
			__( 'Healthcare Settings', 'bkx-healthcare-practice' ),
			__( 'Healthcare', 'bkx-healthcare-practice' ),
			'manage_options',
			'bkx-healthcare-settings',
			array( Admin\SettingsPage::class, 'render' )
		);

		add_submenu_page(
			'bookingx',
			__( 'Patient Records', 'bkx-healthcare-practice' ),
			__( 'Patients', 'bkx-healthcare-practice' ),
			'manage_options',
			'bkx-patient-records',
			array( $this, 'render_patient_records_page' )
		);

		add_submenu_page(
			'bookingx',
			__( 'HIPAA Audit Log', 'bkx-healthcare-practice' ),
			__( 'Audit Log', 'bkx-healthcare-practice' ),
			'manage_options',
			'bkx-hipaa-audit',
			array( $this, 'render_audit_log_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		$screen = get_current_screen();

		$healthcare_screens = array(
			'bookingx_page_bkx-healthcare-settings',
			'bookingx_page_bkx-patient-records',
			'bookingx_page_bkx-hipaa-audit',
			'bkx_consent_form',
			'bkx_intake_form',
			'bkx_provider',
			'bkx_booking',
		);

		if ( ! $screen || ! in_array( $screen->id, $healthcare_screens, true ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-healthcare-admin',
			BKX_HEALTHCARE_URL . 'assets/css/admin.css',
			array(),
			BKX_HEALTHCARE_VERSION
		);

		wp_enqueue_script(
			'bkx-healthcare-admin',
			BKX_HEALTHCARE_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable', 'jquery-ui-datepicker' ),
			BKX_HEALTHCARE_VERSION,
			true
		);

		wp_localize_script(
			'bkx-healthcare-admin',
			'bkxHealthcareAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_healthcare_admin' ),
				'i18n'    => array(
					'confirmDelete'   => __( 'Are you sure you want to delete this?', 'bkx-healthcare-practice' ),
					'confirmConsent'  => __( 'Mark consent as obtained?', 'bkx-healthcare-practice' ),
					'saving'          => __( 'Saving...', 'bkx-healthcare-practice' ),
					'saved'           => __( 'Saved', 'bkx-healthcare-practice' ),
					'error'           => __( 'An error occurred', 'bkx-healthcare-practice' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_frontend_assets(): void {
		if ( ! $this->should_load_frontend_assets() ) {
			return;
		}

		wp_enqueue_style(
			'bkx-healthcare-frontend',
			BKX_HEALTHCARE_URL . 'assets/css/frontend.css',
			array(),
			BKX_HEALTHCARE_VERSION
		);

		wp_enqueue_script(
			'bkx-healthcare-frontend',
			BKX_HEALTHCARE_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_HEALTHCARE_VERSION,
			true
		);

		wp_localize_script(
			'bkx-healthcare-frontend',
			'bkxHealthcare',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_healthcare_frontend' ),
				'i18n'    => array(
					'required'         => __( 'This field is required', 'bkx-healthcare-practice' ),
					'invalidEmail'     => __( 'Please enter a valid email address', 'bkx-healthcare-practice' ),
					'invalidPhone'     => __( 'Please enter a valid phone number', 'bkx-healthcare-practice' ),
					'invalidDate'      => __( 'Please enter a valid date', 'bkx-healthcare-practice' ),
					'submitting'       => __( 'Submitting...', 'bkx-healthcare-practice' ),
					'consentRequired'  => __( 'You must agree to the consent form to continue', 'bkx-healthcare-practice' ),
					'formComplete'     => __( 'Form submitted successfully', 'bkx-healthcare-practice' ),
				),
			)
		);
	}

	/**
	 * Check if frontend assets should be loaded.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	private function should_load_frontend_assets(): bool {
		global $post;

		if ( ! $post ) {
			return false;
		}

		$shortcodes = array(
			'bkx_patient_intake',
			'bkx_consent_form',
			'bkx_patient_portal',
			'bkx_telemedicine',
			'bkx_booking_form',
		);

		foreach ( $shortcodes as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add meta boxes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_meta_boxes(): void {
		// Provider credentials metabox.
		add_meta_box(
			'bkx_provider_credentials',
			__( 'Provider Credentials', 'bkx-healthcare-practice' ),
			array( Admin\ProviderMetabox::class, 'render_credentials' ),
			'bkx_provider',
			'normal',
			'high'
		);

		// Provider availability.
		add_meta_box(
			'bkx_provider_availability',
			__( 'Availability & Scheduling', 'bkx-healthcare-practice' ),
			array( Admin\ProviderMetabox::class, 'render_availability' ),
			'bkx_provider',
			'normal',
			'default'
		);

		// Consent form settings.
		add_meta_box(
			'bkx_consent_settings',
			__( 'Consent Form Settings', 'bkx-healthcare-practice' ),
			array( Admin\ConsentMetabox::class, 'render' ),
			'bkx_consent_form',
			'side',
			'default'
		);

		// Intake form builder.
		add_meta_box(
			'bkx_intake_builder',
			__( 'Form Fields', 'bkx-healthcare-practice' ),
			array( Admin\IntakeFormMetabox::class, 'render' ),
			'bkx_intake_form',
			'normal',
			'high'
		);

		// Patient info on bookings.
		add_meta_box(
			'bkx_patient_info',
			__( 'Patient Information', 'bkx-healthcare-practice' ),
			array( Admin\PatientMetabox::class, 'render' ),
			'bkx_booking',
			'side',
			'high'
		);
	}

	/**
	 * Save meta box data.
	 *
	 * @since 1.0.0
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta_boxes( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['bkx_healthcare_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_healthcare_nonce'] ) ), 'bkx_healthcare_save_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		switch ( $post->post_type ) {
			case 'bkx_provider':
				Admin\ProviderMetabox::save( $post_id );
				break;
			case 'bkx_consent_form':
				Admin\ConsentMetabox::save( $post_id );
				break;
			case 'bkx_intake_form':
				Admin\IntakeFormMetabox::save( $post_id );
				break;
		}
	}

	/**
	 * Get AJAX handlers.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_ajax_handlers(): array {
		return array(
			'bkx_submit_intake_form'    => array( $this, 'ajax_submit_intake_form' ),
			'bkx_submit_consent'        => array( $this, 'ajax_submit_consent' ),
			'bkx_get_patient_history'   => array( $this, 'ajax_get_patient_history' ),
			'bkx_verify_insurance'      => array( $this, 'ajax_verify_insurance' ),
			'bkx_start_telemedicine'    => array( $this, 'ajax_start_telemedicine' ),
			'bkx_get_patient_portal'    => array( $this, 'ajax_get_patient_portal' ),
			'bkx_search_patients'       => array( $this, 'ajax_search_patients' ),
			'bkx_export_patient_data'   => array( $this, 'ajax_export_patient_data' ),
		);
	}

	/**
	 * AJAX: Submit intake form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_submit_intake_form(): void {
		check_ajax_referer( 'bkx_healthcare_frontend', 'nonce' );

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$data    = isset( $_POST['form_data'] ) ? $this->sanitize_intake_data( wp_unslash( $_POST['form_data'] ) ) : array();

		if ( ! $form_id || empty( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid form data', 'bkx-healthcare-practice' ) ) );
		}

		$result = Services\PatientIntakeService::get_instance()->save_intake( $form_id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Log HIPAA event.
		do_action( 'bkx_healthcare_audit_log', 'intake_submitted', $result, $data );

		wp_send_json_success( array(
			'message'   => __( 'Intake form submitted successfully', 'bkx-healthcare-practice' ),
			'intake_id' => $result,
		) );
	}

	/**
	 * AJAX: Submit consent form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_submit_consent(): void {
		check_ajax_referer( 'bkx_healthcare_frontend', 'nonce' );

		$form_id   = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$signature = isset( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : '';
		$agreed    = isset( $_POST['agreed'] ) && 'true' === $_POST['agreed'];

		if ( ! $form_id || ! $agreed ) {
			wp_send_json_error( array( 'message' => __( 'Consent is required', 'bkx-healthcare-practice' ) ) );
		}

		$result = Services\ConsentService::get_instance()->record_consent( $form_id, $signature );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Log HIPAA event.
		do_action( 'bkx_healthcare_audit_log', 'consent_recorded', $form_id, array( 'consent_id' => $result ) );

		wp_send_json_success( array(
			'message'    => __( 'Consent recorded successfully', 'bkx-healthcare-practice' ),
			'consent_id' => $result,
		) );
	}

	/**
	 * AJAX: Get patient history.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_patient_history(): void {
		check_ajax_referer( 'bkx_healthcare_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-healthcare-practice' ) ) );
		}

		$patient_id = isset( $_POST['patient_id'] ) ? absint( $_POST['patient_id'] ) : 0;

		if ( ! $patient_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid patient ID', 'bkx-healthcare-practice' ) ) );
		}

		$history = Services\PatientPortalService::get_instance()->get_patient_history( $patient_id );

		// Log HIPAA access.
		do_action( 'bkx_healthcare_audit_log', 'patient_record_accessed', $patient_id, array(
			'accessed_by' => get_current_user_id(),
		) );

		wp_send_json_success( $history );
	}

	/**
	 * AJAX: Verify insurance.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_verify_insurance(): void {
		check_ajax_referer( 'bkx_healthcare_frontend', 'nonce' );

		$insurance_data = array(
			'provider'   => isset( $_POST['provider'] ) ? sanitize_text_field( wp_unslash( $_POST['provider'] ) ) : '',
			'member_id'  => isset( $_POST['member_id'] ) ? sanitize_text_field( wp_unslash( $_POST['member_id'] ) ) : '',
			'group_id'   => isset( $_POST['group_id'] ) ? sanitize_text_field( wp_unslash( $_POST['group_id'] ) ) : '',
			'dob'        => isset( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( $_POST['dob'] ) ) : '',
		);

		$result = Services\InsuranceService::get_instance()->verify_eligibility( $insurance_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Start telemedicine session.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_start_telemedicine(): void {
		check_ajax_referer( 'bkx_healthcare_frontend', 'nonce' );

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;

		if ( ! $booking_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid booking', 'bkx-healthcare-practice' ) ) );
		}

		$result = Services\TelemedicineService::get_instance()->start_session( $booking_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get patient portal data.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_patient_portal(): void {
		check_ajax_referer( 'bkx_healthcare_frontend', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to access the patient portal', 'bkx-healthcare-practice' ) ) );
		}

		$user_id = get_current_user_id();
		$data    = Services\PatientPortalService::get_instance()->get_portal_data( $user_id );

		wp_send_json_success( $data );
	}

	/**
	 * AJAX: Search patients.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_search_patients(): void {
		check_ajax_referer( 'bkx_healthcare_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-healthcare-practice' ) ) );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		$results = Services\PatientPortalService::get_instance()->search_patients( $search );

		wp_send_json_success( $results );
	}

	/**
	 * AJAX: Export patient data (GDPR).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_export_patient_data(): void {
		check_ajax_referer( 'bkx_healthcare_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-healthcare-practice' ) ) );
		}

		$patient_id = isset( $_POST['patient_id'] ) ? absint( $_POST['patient_id'] ) : 0;

		if ( ! $patient_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid patient ID', 'bkx-healthcare-practice' ) ) );
		}

		$data = Services\PatientPortalService::get_instance()->export_patient_data( $patient_id );

		// Log HIPAA event.
		do_action( 'bkx_healthcare_audit_log', 'patient_data_exported', $patient_id, array(
			'exported_by' => get_current_user_id(),
		) );

		wp_send_json_success( $data );
	}

	/**
	 * Sanitize intake form data.
	 *
	 * @since 1.0.0
	 * @param array $data Raw form data.
	 * @return array Sanitized data.
	 */
	private function sanitize_intake_data( array $data ): array {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			$key = sanitize_key( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = array_map( 'sanitize_text_field', $value );
			} elseif ( 'email' === $key ) {
				$sanitized[ $key ] = sanitize_email( $value );
			} elseif ( in_array( $key, array( 'medical_history', 'notes', 'allergies' ), true ) ) {
				$sanitized[ $key ] = sanitize_textarea_field( $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Render patient intake shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_patient_intake_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'form_id' => 0,
				'booking' => '',
			),
			$atts
		);

		return Services\PatientIntakeService::get_instance()->render_form( absint( $atts['form_id'] ), $atts['booking'] );
	}

	/**
	 * Render consent form shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_consent_form_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'form_id'    => 0,
				'required'   => 'yes',
				'show_print' => 'yes',
			),
			$atts
		);

		return Services\ConsentService::get_instance()->render_form(
			absint( $atts['form_id'] ),
			'yes' === $atts['required'],
			'yes' === $atts['show_print']
		);
	}

	/**
	 * Render patient portal shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_patient_portal_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'show_appointments' => 'yes',
				'show_documents'    => 'yes',
				'show_messages'     => 'yes',
				'show_billing'      => 'no',
			),
			$atts
		);

		return Services\PatientPortalService::get_instance()->render_portal( $atts );
	}

	/**
	 * Render telemedicine shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_telemedicine_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'booking_id' => 0,
			),
			$atts
		);

		return Services\TelemedicineService::get_instance()->render_session( absint( $atts['booking_id'] ) );
	}

	/**
	 * Check consent requirements before booking.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function check_consent_requirements(): void {
		$settings = $this->get_settings();

		if ( empty( $settings['require_consent_before_booking'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id           = get_current_user_id();
		$pending_consents  = Services\ConsentService::get_instance()->get_pending_consents( $user_id );

		if ( ! empty( $pending_consents ) ) {
			echo '<div class="bkx-consent-required-notice">';
			echo '<p>' . esc_html__( 'Please complete the required consent forms before booking:', 'bkx-healthcare-practice' ) . '</p>';
			echo '<ul>';
			foreach ( $pending_consents as $consent ) {
				printf(
					'<li><a href="%s">%s</a></li>',
					esc_url( add_query_arg( 'consent_form', $consent->ID, get_permalink() ) ),
					esc_html( $consent->post_title )
				);
			}
			echo '</ul>';
			echo '</div>';
		}
	}

	/**
	 * Process patient intake after booking.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function process_patient_intake( int $booking_id, array $booking_data ): void {
		$settings = $this->get_settings();

		if ( empty( $settings['enable_patient_intake'] ) ) {
			return;
		}

		// Link any pending intake forms to this booking.
		Services\PatientIntakeService::get_instance()->link_to_booking( $booking_id, $booking_data );
	}

	/**
	 * Schedule appointment reminders.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function schedule_appointment_reminders( int $booking_id, array $booking_data ): void {
		$settings = $this->get_settings();

		if ( empty( $settings['enable_appointment_reminders'] ) ) {
			return;
		}

		$reminder_hours = absint( $settings['reminder_hours'] ?? 24 );
		$booking_date   = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time   = get_post_meta( $booking_id, 'booking_time', true );

		if ( ! $booking_date || ! $booking_time ) {
			return;
		}

		$appointment_time = strtotime( $booking_date . ' ' . $booking_time );
		$reminder_time    = $appointment_time - ( $reminder_hours * HOUR_IN_SECONDS );

		if ( $reminder_time > time() ) {
			wp_schedule_single_event( $reminder_time, 'bkx_healthcare_send_reminders', array( $booking_id ) );
		}
	}

	/**
	 * Add healthcare meta fields to booking.
	 *
	 * @since 1.0.0
	 * @param array $fields Existing meta fields.
	 * @return array
	 */
	public function add_healthcare_meta_fields( array $fields ): array {
		$healthcare_fields = array(
			'patient_intake_id'    => __( 'Patient Intake', 'bkx-healthcare-practice' ),
			'consent_ids'          => __( 'Consent Forms', 'bkx-healthcare-practice' ),
			'insurance_verified'   => __( 'Insurance Verified', 'bkx-healthcare-practice' ),
			'telemedicine_enabled' => __( 'Telemedicine', 'bkx-healthcare-practice' ),
			'provider_notes'       => __( 'Provider Notes', 'bkx-healthcare-practice' ),
		);

		return array_merge( $fields, $healthcare_fields );
	}

	/**
	 * Log HIPAA audit event.
	 *
	 * @since 1.0.0
	 * @param string $event_type Event type.
	 * @param mixed  $object_id  Object ID.
	 * @param array  $data       Additional data.
	 * @return void
	 */
	public function log_hipaa_event( string $event_type, $object_id, array $data = array() ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_hipaa_audit_log';

		$wpdb->insert(
			$table_name,
			array(
				'event_type'   => $event_type,
				'object_id'    => $object_id,
				'user_id'      => get_current_user_id(),
				'ip_address'   => $this->get_client_ip(),
				'user_agent'   => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'event_data'   => wp_json_encode( $data ),
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Handle comma-separated IPs (X-Forwarded-For).
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip  = trim( $ips[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Include patient data in export.
	 *
	 * @since 1.0.0
	 * @param array $data       Export data.
	 * @param int   $booking_id Booking ID.
	 * @return array
	 */
	public function include_patient_data( array $data, int $booking_id ): array {
		$intake_id = get_post_meta( $booking_id, 'patient_intake_id', true );

		if ( $intake_id ) {
			$data['patient_intake'] = Services\PatientIntakeService::get_instance()->get_intake_data( absint( $intake_id ) );
		}

		$consent_ids = get_post_meta( $booking_id, 'consent_ids', true );

		if ( $consent_ids ) {
			$data['consents'] = Services\ConsentService::get_instance()->get_consent_records( $consent_ids );
		}

		return $data;
	}

	/**
	 * Send appointment reminders.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return void
	 */
	public function send_appointment_reminders( int $booking_id ): void {
		$booking = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return;
		}

		// Get patient email.
		$email = get_post_meta( $booking_id, 'customer_email', true );

		if ( ! $email ) {
			return;
		}

		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time = get_post_meta( $booking_id, 'booking_time', true );

		$subject = sprintf(
			/* translators: %s: Practice name */
			__( 'Appointment Reminder from %s', 'bkx-healthcare-practice' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: Booking date, 2: Booking time */
			__( 'This is a reminder that you have an upcoming appointment on %1$s at %2$s.', 'bkx-healthcare-practice' ),
			$booking_date,
			$booking_time
		);

		wp_mail( $email, $subject, $message );

		// Log the reminder.
		do_action( 'bkx_healthcare_audit_log', 'reminder_sent', $booking_id, array( 'email' => $email ) );
	}

	/**
	 * Process data retention policy.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function process_data_retention(): void {
		$settings       = $this->get_settings();
		$retention_days = absint( $settings['patient_data_retention_days'] ?? 2555 );

		if ( $retention_days < 1 ) {
			return;
		}

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		// This would archive or delete old records based on compliance requirements.
		// Implementation depends on specific HIPAA requirements.
		do_action( 'bkx_healthcare_data_retention_processed', $cutoff_date, $retention_days );
	}

	/**
	 * Render patient records page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_patient_records_page(): void {
		include BKX_HEALTHCARE_PATH . 'templates/admin/patient-records.php';
	}

	/**
	 * Render audit log page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_audit_log_page(): void {
		include BKX_HEALTHCARE_PATH . 'templates/admin/audit-log.php';
	}
}
