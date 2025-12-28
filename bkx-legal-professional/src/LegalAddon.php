<?php
/**
 * Legal Addon main class.
 *
 * @package BookingX\LegalProfessional
 * @since   1.0.0
 */

namespace BookingX\LegalProfessional;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasAjax;

/**
 * Main Legal & Professional Services addon class.
 *
 * @since 1.0.0
 */
class LegalAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasAjax;

	/**
	 * Singleton instance.
	 *
	 * @var LegalAddon|null
	 */
	private static ?LegalAddon $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return LegalAddon
	 */
	public static function get_instance(): LegalAddon {
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
		return 'bkx-legal-professional';
	}

	/**
	 * Get addon name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_name(): string {
		return __( 'Legal & Professional Services', 'bkx-legal-professional' );
	}

	/**
	 * Get addon version.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_version(): string {
		return BKX_LEGAL_VERSION;
	}

	/**
	 * Initialize the addon.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		$this->init_settings( 'bkx_legal_settings' );
		$this->init_license( 'bkx_legal_professional' );
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
		add_shortcode( 'bkx_client_intake', array( $this, 'render_client_intake_shortcode' ) );
		add_shortcode( 'bkx_client_portal', array( $this, 'render_client_portal_shortcode' ) );
		add_shortcode( 'bkx_retainer_agreement', array( $this, 'render_retainer_shortcode' ) );
		add_shortcode( 'bkx_consultation_booking', array( $this, 'render_consultation_shortcode' ) );

		// BookingX integration hooks.
		add_action( 'bkx_before_booking_form', array( $this, 'check_intake_requirements' ) );
		add_action( 'bkx_booking_created', array( $this, 'process_client_intake' ), 10, 2 );
		add_action( 'bkx_booking_created', array( $this, 'run_conflict_check' ), 10, 2 );
		add_filter( 'bkx_booking_meta_fields', array( $this, 'add_legal_meta_fields' ) );

		// Time tracking hooks.
		add_action( 'bkx_booking_status_changed', array( $this, 'track_billable_time' ), 10, 3 );

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
		Services\ClientIntakeService::get_instance();
		Services\CaseManagementService::get_instance();
		Services\DocumentService::get_instance();
		Services\BillingService::get_instance();
		Services\ConflictCheckService::get_instance();
	}

	/**
	 * Register custom post types.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_post_types(): void {
		// Client/Matter post type.
		register_post_type(
			'bkx_matter',
			array(
				'labels'              => array(
					'name'               => __( 'Matters', 'bkx-legal-professional' ),
					'singular_name'      => __( 'Matter', 'bkx-legal-professional' ),
					'add_new'            => __( 'Add New', 'bkx-legal-professional' ),
					'add_new_item'       => __( 'Add New Matter', 'bkx-legal-professional' ),
					'edit_item'          => __( 'Edit Matter', 'bkx-legal-professional' ),
					'new_item'           => __( 'New Matter', 'bkx-legal-professional' ),
					'view_item'          => __( 'View Matter', 'bkx-legal-professional' ),
					'search_items'       => __( 'Search Matters', 'bkx-legal-professional' ),
					'not_found'          => __( 'No matters found', 'bkx-legal-professional' ),
					'not_found_in_trash' => __( 'No matters found in trash', 'bkx-legal-professional' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'bookingx',
				'capability_type'     => 'post',
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor', 'author' ),
				'has_archive'         => false,
				'rewrite'             => false,
				'show_in_rest'        => true,
			)
		);

		// Client intake form template.
		register_post_type(
			'bkx_intake_template',
			array(
				'labels'              => array(
					'name'               => __( 'Intake Templates', 'bkx-legal-professional' ),
					'singular_name'      => __( 'Intake Template', 'bkx-legal-professional' ),
					'add_new'            => __( 'Add New', 'bkx-legal-professional' ),
					'add_new_item'       => __( 'Add New Template', 'bkx-legal-professional' ),
					'edit_item'          => __( 'Edit Template', 'bkx-legal-professional' ),
					'new_item'           => __( 'New Template', 'bkx-legal-professional' ),
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

		// Retainer agreement template.
		register_post_type(
			'bkx_retainer',
			array(
				'labels'              => array(
					'name'               => __( 'Retainer Agreements', 'bkx-legal-professional' ),
					'singular_name'      => __( 'Retainer Agreement', 'bkx-legal-professional' ),
					'add_new'            => __( 'Add New', 'bkx-legal-professional' ),
					'add_new_item'       => __( 'Add New Agreement', 'bkx-legal-professional' ),
					'edit_item'          => __( 'Edit Agreement', 'bkx-legal-professional' ),
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

		// Attorney/Professional profile.
		register_post_type(
			'bkx_attorney',
			array(
				'labels'              => array(
					'name'               => __( 'Attorneys', 'bkx-legal-professional' ),
					'singular_name'      => __( 'Attorney', 'bkx-legal-professional' ),
					'add_new'            => __( 'Add New', 'bkx-legal-professional' ),
					'add_new_item'       => __( 'Add New Attorney', 'bkx-legal-professional' ),
					'edit_item'          => __( 'Edit Attorney', 'bkx-legal-professional' ),
				),
				'public'              => true,
				'show_ui'             => true,
				'show_in_menu'        => 'bookingx',
				'capability_type'     => 'post',
				'hierarchical'        => false,
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'has_archive'         => true,
				'rewrite'             => array( 'slug' => 'attorneys' ),
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
		// Practice area.
		register_taxonomy(
			'bkx_practice_area',
			array( 'bkx_attorney', 'bkx_matter', 'bkx_base' ),
			array(
				'labels'            => array(
					'name'          => __( 'Practice Areas', 'bkx-legal-professional' ),
					'singular_name' => __( 'Practice Area', 'bkx-legal-professional' ),
					'search_items'  => __( 'Search Practice Areas', 'bkx-legal-professional' ),
					'all_items'     => __( 'All Practice Areas', 'bkx-legal-professional' ),
					'edit_item'     => __( 'Edit Practice Area', 'bkx-legal-professional' ),
					'update_item'   => __( 'Update Practice Area', 'bkx-legal-professional' ),
					'add_new_item'  => __( 'Add New Practice Area', 'bkx-legal-professional' ),
					'new_item_name' => __( 'New Practice Area Name', 'bkx-legal-professional' ),
				),
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'practice-area' ),
				'show_in_rest'      => true,
			)
		);

		// Matter type.
		register_taxonomy(
			'bkx_matter_type',
			array( 'bkx_matter' ),
			array(
				'labels'            => array(
					'name'          => __( 'Matter Types', 'bkx-legal-professional' ),
					'singular_name' => __( 'Matter Type', 'bkx-legal-professional' ),
				),
				'hierarchical'      => true,
				'show_ui'           => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => 'matter-type' ),
				'show_in_rest'      => true,
			)
		);

		// Matter status.
		register_taxonomy(
			'bkx_matter_status',
			array( 'bkx_matter' ),
			array(
				'labels'            => array(
					'name'          => __( 'Matter Status', 'bkx-legal-professional' ),
					'singular_name' => __( 'Status', 'bkx-legal-professional' ),
				),
				'hierarchical'      => false,
				'show_ui'           => true,
				'show_admin_column' => true,
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
			__( 'Legal Settings', 'bkx-legal-professional' ),
			__( 'Legal Services', 'bkx-legal-professional' ),
			'manage_options',
			'bkx-legal-settings',
			array( Admin\SettingsPage::class, 'render' )
		);

		add_submenu_page(
			'bookingx',
			__( 'Clients', 'bkx-legal-professional' ),
			__( 'Clients', 'bkx-legal-professional' ),
			'manage_options',
			'bkx-clients',
			array( $this, 'render_clients_page' )
		);

		add_submenu_page(
			'bookingx',
			__( 'Time Tracking', 'bkx-legal-professional' ),
			__( 'Time Tracking', 'bkx-legal-professional' ),
			'manage_options',
			'bkx-time-tracking',
			array( $this, 'render_time_tracking_page' )
		);

		add_submenu_page(
			'bookingx',
			__( 'Conflict Check', 'bkx-legal-professional' ),
			__( 'Conflict Check', 'bkx-legal-professional' ),
			'manage_options',
			'bkx-conflict-check',
			array( $this, 'render_conflict_check_page' )
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

		$legal_screens = array(
			'bookingx_page_bkx-legal-settings',
			'bookingx_page_bkx-clients',
			'bookingx_page_bkx-time-tracking',
			'bookingx_page_bkx-conflict-check',
			'bkx_matter',
			'bkx_intake_template',
			'bkx_retainer',
			'bkx_attorney',
			'bkx_booking',
		);

		if ( ! $screen || ! in_array( $screen->id, $legal_screens, true ) ) {
			return;
		}

		wp_enqueue_style(
			'bkx-legal-admin',
			BKX_LEGAL_URL . 'assets/css/admin.css',
			array(),
			BKX_LEGAL_VERSION
		);

		wp_enqueue_script(
			'bkx-legal-admin',
			BKX_LEGAL_URL . 'assets/js/admin.js',
			array( 'jquery', 'jquery-ui-sortable', 'jquery-ui-datepicker' ),
			BKX_LEGAL_VERSION,
			true
		);

		wp_localize_script(
			'bkx-legal-admin',
			'bkxLegalAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_legal_admin' ),
				'i18n'    => array(
					'confirmDelete' => __( 'Are you sure you want to delete this?', 'bkx-legal-professional' ),
					'saving'        => __( 'Saving...', 'bkx-legal-professional' ),
					'saved'         => __( 'Saved', 'bkx-legal-professional' ),
					'error'         => __( 'An error occurred', 'bkx-legal-professional' ),
					'timerStart'    => __( 'Start Timer', 'bkx-legal-professional' ),
					'timerStop'     => __( 'Stop Timer', 'bkx-legal-professional' ),
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
			'bkx-legal-frontend',
			BKX_LEGAL_URL . 'assets/css/frontend.css',
			array(),
			BKX_LEGAL_VERSION
		);

		wp_enqueue_script(
			'bkx-legal-frontend',
			BKX_LEGAL_URL . 'assets/js/frontend.js',
			array( 'jquery' ),
			BKX_LEGAL_VERSION,
			true
		);

		wp_localize_script(
			'bkx-legal-frontend',
			'bkxLegal',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_legal_frontend' ),
				'i18n'    => array(
					'required'      => __( 'This field is required', 'bkx-legal-professional' ),
					'submitting'    => __( 'Submitting...', 'bkx-legal-professional' ),
					'formComplete'  => __( 'Form submitted successfully', 'bkx-legal-professional' ),
					'conflictFound' => __( 'A potential conflict has been identified', 'bkx-legal-professional' ),
					'noConflict'    => __( 'No conflicts found', 'bkx-legal-professional' ),
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
			'bkx_client_intake',
			'bkx_client_portal',
			'bkx_retainer_agreement',
			'bkx_consultation_booking',
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
		// Matter details.
		add_meta_box(
			'bkx_matter_details',
			__( 'Matter Details', 'bkx-legal-professional' ),
			array( Admin\MatterMetabox::class, 'render' ),
			'bkx_matter',
			'normal',
			'high'
		);

		// Attorney credentials.
		add_meta_box(
			'bkx_attorney_credentials',
			__( 'Credentials & Bar Admissions', 'bkx-legal-professional' ),
			array( Admin\AttorneyMetabox::class, 'render_credentials' ),
			'bkx_attorney',
			'normal',
			'high'
		);

		// Attorney rates.
		add_meta_box(
			'bkx_attorney_rates',
			__( 'Billing Rates', 'bkx-legal-professional' ),
			array( Admin\AttorneyMetabox::class, 'render_rates' ),
			'bkx_attorney',
			'side',
			'default'
		);

		// Retainer settings.
		add_meta_box(
			'bkx_retainer_settings',
			__( 'Agreement Settings', 'bkx-legal-professional' ),
			array( Admin\RetainerMetabox::class, 'render' ),
			'bkx_retainer',
			'side',
			'default'
		);

		// Client info on bookings.
		add_meta_box(
			'bkx_client_info',
			__( 'Client Information', 'bkx-legal-professional' ),
			array( Admin\ClientMetabox::class, 'render' ),
			'bkx_booking',
			'side',
			'high'
		);

		// Time entry on bookings.
		add_meta_box(
			'bkx_time_entry',
			__( 'Time Entry', 'bkx-legal-professional' ),
			array( Admin\TimeEntryMetabox::class, 'render' ),
			'bkx_booking',
			'normal',
			'default'
		);

		// Intake form builder.
		add_meta_box(
			'bkx_intake_builder',
			__( 'Intake Form Fields', 'bkx-legal-professional' ),
			array( Admin\IntakeBuilderMetabox::class, 'render' ),
			'bkx_intake_template',
			'normal',
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

		if ( ! isset( $_POST['bkx_legal_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_legal_nonce'] ) ), 'bkx_legal_save_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		switch ( $post->post_type ) {
			case 'bkx_matter':
				Admin\MatterMetabox::save( $post_id );
				break;
			case 'bkx_attorney':
				Admin\AttorneyMetabox::save( $post_id );
				break;
			case 'bkx_retainer':
				Admin\RetainerMetabox::save( $post_id );
				break;
			case 'bkx_intake_template':
				Admin\IntakeBuilderMetabox::save( $post_id );
				break;
			case 'bkx_booking':
				Admin\TimeEntryMetabox::save( $post_id );
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
			'bkx_submit_client_intake'   => array( $this, 'ajax_submit_client_intake' ),
			'bkx_sign_retainer'          => array( $this, 'ajax_sign_retainer' ),
			'bkx_run_conflict_check'     => array( $this, 'ajax_run_conflict_check' ),
			'bkx_search_clients'         => array( $this, 'ajax_search_clients' ),
			'bkx_save_time_entry'        => array( $this, 'ajax_save_time_entry' ),
			'bkx_get_client_matters'     => array( $this, 'ajax_get_client_matters' ),
			'bkx_upload_document'        => array( $this, 'ajax_upload_document' ),
			'bkx_get_client_portal_data' => array( $this, 'ajax_get_client_portal_data' ),
		);
	}

	/**
	 * AJAX: Submit client intake.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_submit_client_intake(): void {
		check_ajax_referer( 'bkx_legal_frontend', 'nonce' );

		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;
		$data        = isset( $_POST['form_data'] ) ? $this->sanitize_intake_data( wp_unslash( $_POST['form_data'] ) ) : array();

		if ( ! $template_id || empty( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid form data', 'bkx-legal-professional' ) ) );
		}

		$result = Services\ClientIntakeService::get_instance()->save_intake( $template_id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'   => __( 'Intake form submitted successfully', 'bkx-legal-professional' ),
			'intake_id' => $result,
		) );
	}

	/**
	 * AJAX: Sign retainer agreement.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_sign_retainer(): void {
		check_ajax_referer( 'bkx_legal_frontend', 'nonce' );

		$retainer_id = isset( $_POST['retainer_id'] ) ? absint( $_POST['retainer_id'] ) : 0;
		$signature   = isset( $_POST['signature'] ) ? sanitize_text_field( wp_unslash( $_POST['signature'] ) ) : '';
		$agreed      = isset( $_POST['agreed'] ) && 'true' === $_POST['agreed'];

		if ( ! $retainer_id || ! $agreed ) {
			wp_send_json_error( array( 'message' => __( 'Agreement is required', 'bkx-legal-professional' ) ) );
		}

		$result = Services\DocumentService::get_instance()->sign_retainer( $retainer_id, $signature );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Retainer agreement signed successfully', 'bkx-legal-professional' ),
		) );
	}

	/**
	 * AJAX: Run conflict check.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_run_conflict_check(): void {
		check_ajax_referer( 'bkx_legal_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-legal-professional' ) ) );
		}

		$search_data = array(
			'name'    => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'company' => isset( $_POST['company'] ) ? sanitize_text_field( wp_unslash( $_POST['company'] ) ) : '',
			'email'   => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
		);

		$result = Services\ConflictCheckService::get_instance()->check_conflicts( $search_data );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Search clients.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_search_clients(): void {
		check_ajax_referer( 'bkx_legal_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-legal-professional' ) ) );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		$results = Services\ClientIntakeService::get_instance()->search_clients( $search );

		wp_send_json_success( $results );
	}

	/**
	 * AJAX: Save time entry.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_save_time_entry(): void {
		check_ajax_referer( 'bkx_legal_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-legal-professional' ) ) );
		}

		$entry_data = array(
			'matter_id'   => isset( $_POST['matter_id'] ) ? absint( $_POST['matter_id'] ) : 0,
			'booking_id'  => isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0,
			'attorney_id' => isset( $_POST['attorney_id'] ) ? absint( $_POST['attorney_id'] ) : 0,
			'duration'    => isset( $_POST['duration'] ) ? absint( $_POST['duration'] ) : 0,
			'description' => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
			'billable'    => isset( $_POST['billable'] ) && 'true' === $_POST['billable'],
			'date'        => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : current_time( 'Y-m-d' ),
		);

		$result = Services\BillingService::get_instance()->save_time_entry( $entry_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'  => __( 'Time entry saved', 'bkx-legal-professional' ),
			'entry_id' => $result,
		) );
	}

	/**
	 * AJAX: Get client matters.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_client_matters(): void {
		check_ajax_referer( 'bkx_legal_admin', 'nonce' );

		$client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

		if ( ! $client_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid client ID', 'bkx-legal-professional' ) ) );
		}

		$matters = Services\CaseManagementService::get_instance()->get_client_matters( $client_id );

		wp_send_json_success( $matters );
	}

	/**
	 * AJAX: Upload document.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_upload_document(): void {
		check_ajax_referer( 'bkx_legal_admin', 'nonce' );

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied', 'bkx-legal-professional' ) ) );
		}

		if ( empty( $_FILES['document'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded', 'bkx-legal-professional' ) ) );
		}

		$matter_id = isset( $_POST['matter_id'] ) ? absint( $_POST['matter_id'] ) : 0;

		$result = Services\DocumentService::get_instance()->upload_document( $_FILES['document'], $matter_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Get client portal data.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_get_client_portal_data(): void {
		check_ajax_referer( 'bkx_legal_frontend', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in', 'bkx-legal-professional' ) ) );
		}

		$user_id = get_current_user_id();
		$data    = Services\ClientIntakeService::get_instance()->get_client_portal_data( $user_id );

		wp_send_json_success( $data );
	}

	/**
	 * Sanitize intake form data.
	 *
	 * @since 1.0.0
	 * @param array $data Raw form data.
	 * @return array
	 */
	private function sanitize_intake_data( array $data ): array {
		$sanitized = array();

		foreach ( $data as $key => $value ) {
			$key = sanitize_key( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $key ] = array_map( 'sanitize_text_field', $value );
			} elseif ( 'email' === $key ) {
				$sanitized[ $key ] = sanitize_email( $value );
			} elseif ( in_array( $key, array( 'case_summary', 'notes', 'description' ), true ) ) {
				$sanitized[ $key ] = sanitize_textarea_field( $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Render client intake shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_client_intake_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'template_id'  => 0,
				'practice_area' => '',
			),
			$atts
		);

		return Services\ClientIntakeService::get_instance()->render_form( absint( $atts['template_id'] ), $atts['practice_area'] );
	}

	/**
	 * Render client portal shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_client_portal_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'show_matters'    => 'yes',
				'show_documents'  => 'yes',
				'show_billing'    => 'yes',
				'show_messages'   => 'yes',
			),
			$atts
		);

		return Services\ClientIntakeService::get_instance()->render_client_portal( $atts );
	}

	/**
	 * Render retainer agreement shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_retainer_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'retainer_id' => 0,
				'matter_id'   => '',
			),
			$atts
		);

		return Services\DocumentService::get_instance()->render_retainer_form( absint( $atts['retainer_id'] ), $atts['matter_id'] );
	}

	/**
	 * Render consultation booking shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_consultation_shortcode( array $atts ): string {
		$atts = shortcode_atts(
			array(
				'attorney_id'   => 0,
				'practice_area' => '',
				'show_fee'      => 'yes',
			),
			$atts
		);

		return Services\CaseManagementService::get_instance()->render_consultation_form( $atts );
	}

	/**
	 * Check intake requirements before booking.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function check_intake_requirements(): void {
		$settings = $this->get_settings();

		if ( empty( $settings['require_intake_before_booking'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id     = get_current_user_id();
		$has_intake  = Services\ClientIntakeService::get_instance()->user_has_intake( $user_id );

		if ( ! $has_intake ) {
			$intake_page = get_permalink( $settings['intake_page_id'] ?? 0 );
			if ( $intake_page ) {
				echo '<div class="bkx-intake-required-notice">';
				echo '<p>' . esc_html__( 'Please complete the client intake form before scheduling a consultation:', 'bkx-legal-professional' ) . '</p>';
				echo '<a href="' . esc_url( $intake_page ) . '" class="bkx-btn bkx-btn-primary">' . esc_html__( 'Complete Intake Form', 'bkx-legal-professional' ) . '</a>';
				echo '</div>';
			}
		}
	}

	/**
	 * Process client intake after booking.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function process_client_intake( int $booking_id, array $booking_data ): void {
		$settings = $this->get_settings();

		if ( empty( $settings['enable_client_intake'] ) ) {
			return;
		}

		Services\ClientIntakeService::get_instance()->link_to_booking( $booking_id, $booking_data );
	}

	/**
	 * Run conflict check on new booking.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function run_conflict_check( int $booking_id, array $booking_data ): void {
		$settings = $this->get_settings();

		if ( empty( $settings['enable_conflict_check'] ) ) {
			return;
		}

		$client_name  = $booking_data['customer_name'] ?? '';
		$client_email = $booking_data['customer_email'] ?? '';

		if ( $client_name || $client_email ) {
			$result = Services\ConflictCheckService::get_instance()->check_conflicts( array(
				'name'  => $client_name,
				'email' => $client_email,
			) );

			if ( ! empty( $result['conflicts'] ) ) {
				update_post_meta( $booking_id, '_bkx_potential_conflict', true );
				update_post_meta( $booking_id, '_bkx_conflict_details', $result );
			}
		}
	}

	/**
	 * Add legal meta fields to booking.
	 *
	 * @since 1.0.0
	 * @param array $fields Existing meta fields.
	 * @return array
	 */
	public function add_legal_meta_fields( array $fields ): array {
		$legal_fields = array(
			'matter_id'         => __( 'Matter', 'bkx-legal-professional' ),
			'client_intake_id'  => __( 'Client Intake', 'bkx-legal-professional' ),
			'retainer_signed'   => __( 'Retainer Signed', 'bkx-legal-professional' ),
			'billable_hours'    => __( 'Billable Hours', 'bkx-legal-professional' ),
			'conflict_cleared'  => __( 'Conflict Cleared', 'bkx-legal-professional' ),
		);

		return array_merge( $fields, $legal_fields );
	}

	/**
	 * Track billable time when booking status changes.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @return void
	 */
	public function track_billable_time( int $booking_id, string $new_status, string $old_status ): void {
		$settings = $this->get_settings();

		if ( empty( $settings['enable_billing_tracking'] ) ) {
			return;
		}

		// Auto-create time entry when appointment is completed.
		if ( 'bkx-completed' === $new_status ) {
			$base_id  = get_post_meta( $booking_id, 'base_id', true );
			$duration = $base_id ? get_post_meta( $base_id, 'base_time', true ) : 60;

			Services\BillingService::get_instance()->auto_create_time_entry( $booking_id, absint( $duration ) );
		}
	}

	/**
	 * Render clients page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_clients_page(): void {
		include BKX_LEGAL_PATH . 'templates/admin/clients.php';
	}

	/**
	 * Render time tracking page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_time_tracking_page(): void {
		include BKX_LEGAL_PATH . 'templates/admin/time-tracking.php';
	}

	/**
	 * Render conflict check page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_conflict_check_page(): void {
		include BKX_LEGAL_PATH . 'templates/admin/conflict-check.php';
	}
}
