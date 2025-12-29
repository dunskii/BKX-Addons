<?php
/**
 * Main CRM Addon class.
 *
 * @package BookingX\CRM
 */

namespace BookingX\CRM;

use BookingX\CRM\Services\CustomerService;
use BookingX\CRM\Services\TagService;
use BookingX\CRM\Services\SegmentService;
use BookingX\CRM\Services\FollowupService;
use BookingX\CRM\Services\ActivityService;

defined( 'ABSPATH' ) || exit;

/**
 * CRMAddon class.
 */
class CRMAddon {

	/**
	 * Single instance.
	 *
	 * @var CRMAddon
	 */
	private static $instance = null;

	/**
	 * Customer service.
	 *
	 * @var CustomerService
	 */
	public $customers;

	/**
	 * Tag service.
	 *
	 * @var TagService
	 */
	public $tags;

	/**
	 * Segment service.
	 *
	 * @var SegmentService
	 */
	public $segments;

	/**
	 * Followup service.
	 *
	 * @var FollowupService
	 */
	public $followups;

	/**
	 * Activity service.
	 *
	 * @var ActivityService
	 */
	public $activities;

	/**
	 * Get instance.
	 *
	 * @return CRMAddon
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
		$this->customers  = new CustomerService();
		$this->tags       = new TagService();
		$this->segments   = new SegmentService();
		$this->activities = new ActivityService();
		$this->followups  = new FollowupService( $this->customers );
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// AJAX handlers.
		$this->register_ajax_handlers();

		// Booking hooks.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_status_changed', array( $this, 'on_booking_status_changed' ), 10, 3 );

		// User sync.
		add_action( 'user_register', array( $this->customers, 'sync_from_user' ) );
		add_action( 'profile_update', array( $this->customers, 'sync_from_user' ) );

		// Cron jobs.
		add_action( 'bkx_crm_process_followups', array( $this->followups, 'process_pending' ) );
		add_action( 'bkx_crm_check_birthdays', array( $this->followups, 'check_birthdays' ) );

		if ( ! wp_next_scheduled( 'bkx_crm_process_followups' ) ) {
			wp_schedule_event( time(), 'hourly', 'bkx_crm_process_followups' );
		}

		if ( ! wp_next_scheduled( 'bkx_crm_check_birthdays' ) ) {
			wp_schedule_event( strtotime( 'today 9:00' ), 'daily', 'bkx_crm_check_birthdays' );
		}

		// Add CRM column to bookings list.
		add_filter( 'manage_bkx_booking_posts_columns', array( $this, 'add_booking_columns' ) );
		add_action( 'manage_bkx_booking_posts_custom_column', array( $this, 'render_booking_columns' ), 10, 2 );

		// Add metabox to booking edit screen.
		add_action( 'add_meta_boxes', array( $this, 'add_booking_metaboxes' ) );
	}

	/**
	 * Register AJAX handlers.
	 */
	private function register_ajax_handlers() {
		$ajax_actions = array(
			'bkx_crm_save_settings',
			'bkx_crm_get_customer',
			'bkx_crm_save_customer',
			'bkx_crm_delete_customer',
			'bkx_crm_get_customers',
			'bkx_crm_add_tag',
			'bkx_crm_delete_tag',
			'bkx_crm_add_customer_tag',
			'bkx_crm_remove_customer_tag',
			'bkx_crm_add_note',
			'bkx_crm_delete_note',
			'bkx_crm_get_notes',
			'bkx_crm_save_segment',
			'bkx_crm_delete_segment',
			'bkx_crm_preview_segment',
			'bkx_crm_schedule_followup',
			'bkx_crm_cancel_followup',
			'bkx_crm_get_activity',
			'bkx_crm_log_communication',
			'bkx_crm_export_customers',
			'bkx_crm_import_customers',
		);

		foreach ( $ajax_actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, 'handle_ajax' ) );
		}
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'CRM', 'bkx-crm' ),
			__( 'CRM', 'bkx-crm' ),
			'manage_options',
			'bkx-crm',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		$allowed_hooks = array(
			'bkx_booking_page_bkx-crm',
			'post.php',
		);

		if ( ! in_array( $hook, $allowed_hooks, true ) ) {
			return;
		}

		// Only load on booking edit screens.
		if ( 'post.php' === $hook ) {
			$screen = get_current_screen();
			if ( ! $screen || 'bkx_booking' !== $screen->post_type ) {
				return;
			}
		}

		wp_enqueue_style(
			'bkx-crm-admin',
			BKX_CRM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_CRM_VERSION
		);

		wp_enqueue_script(
			'bkx-crm-admin',
			BKX_CRM_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			BKX_CRM_VERSION,
			true
		);

		wp_localize_script(
			'bkx-crm-admin',
			'bkxCRM',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => rest_url( 'bkx-crm/v1' ),
				'nonce'   => wp_create_nonce( 'bkx_crm_nonce' ),
				'i18n'    => array(
					'saved'           => __( 'Saved successfully.', 'bkx-crm' ),
					'error'           => __( 'An error occurred.', 'bkx-crm' ),
					'confirmDelete'   => __( 'Are you sure you want to delete this?', 'bkx-crm' ),
					'loading'         => __( 'Loading...', 'bkx-crm' ),
					'noResults'       => __( 'No results found.', 'bkx-crm' ),
				),
			)
		);
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-crm/v1',
			'/customers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->customers, 'rest_get_customers' ),
				'permission_callback' => array( $this, 'check_rest_permissions' ),
			)
		);

		register_rest_route(
			'bkx-crm/v1',
			'/customers/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->customers, 'rest_get_customer' ),
				'permission_callback' => array( $this, 'check_rest_permissions' ),
			)
		);

		register_rest_route(
			'bkx-crm/v1',
			'/customers/(?P<id>\d+)/activity',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->activities, 'rest_get_activity' ),
				'permission_callback' => array( $this, 'check_rest_permissions' ),
			)
		);

		register_rest_route(
			'bkx-crm/v1',
			'/segments',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->segments, 'rest_get_segments' ),
				'permission_callback' => array( $this, 'check_rest_permissions' ),
			)
		);

		register_rest_route(
			'bkx-crm/v1',
			'/tags',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->tags, 'rest_get_tags' ),
				'permission_callback' => array( $this, 'check_rest_permissions' ),
			)
		);
	}

	/**
	 * Check REST permissions.
	 *
	 * @return bool
	 */
	public function check_rest_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'customers';

		include BKX_CRM_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Handle AJAX requests.
	 */
	public function handle_ajax() {
		check_ajax_referer( 'bkx_crm_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-crm' ) ) );
		}

		$action = str_replace( 'bkx_crm_', '', current_action() );

		switch ( $action ) {
			case 'save_settings':
				$this->ajax_save_settings();
				break;

			case 'get_customer':
				$this->ajax_get_customer();
				break;

			case 'save_customer':
				$this->ajax_save_customer();
				break;

			case 'delete_customer':
				$this->ajax_delete_customer();
				break;

			case 'get_customers':
				$this->ajax_get_customers();
				break;

			case 'add_tag':
				$this->ajax_add_tag();
				break;

			case 'delete_tag':
				$this->ajax_delete_tag();
				break;

			case 'add_customer_tag':
				$this->ajax_add_customer_tag();
				break;

			case 'remove_customer_tag':
				$this->ajax_remove_customer_tag();
				break;

			case 'add_note':
				$this->ajax_add_note();
				break;

			case 'delete_note':
				$this->ajax_delete_note();
				break;

			case 'get_notes':
				$this->ajax_get_notes();
				break;

			case 'save_segment':
				$this->ajax_save_segment();
				break;

			case 'delete_segment':
				$this->ajax_delete_segment();
				break;

			case 'preview_segment':
				$this->ajax_preview_segment();
				break;

			case 'schedule_followup':
				$this->ajax_schedule_followup();
				break;

			case 'cancel_followup':
				$this->ajax_cancel_followup();
				break;

			case 'get_activity':
				$this->ajax_get_activity();
				break;

			case 'log_communication':
				$this->ajax_log_communication();
				break;

			case 'export_customers':
				$this->ajax_export_customers();
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'bkx-crm' ) ) );
		}
	}

	/**
	 * AJAX: Save settings.
	 */
	private function ajax_save_settings() {
		$settings = array(
			'auto_sync_users'       => ! empty( $_POST['auto_sync_users'] ),
			'auto_followup_enabled' => ! empty( $_POST['auto_followup_enabled'] ),
			'thankyou_delay'        => absint( $_POST['thankyou_delay'] ?? 24 ),
			'feedback_delay'        => absint( $_POST['feedback_delay'] ?? 72 ),
			'reengagement_days'     => absint( $_POST['reengagement_days'] ?? 30 ),
			'birthday_enabled'      => ! empty( $_POST['birthday_enabled'] ),
			'birthday_days_before'  => absint( $_POST['birthday_days_before'] ?? 7 ),
		);

		update_option( 'bkx_crm_settings', $settings );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'bkx-crm' ) ) );
	}

	/**
	 * AJAX: Get customer.
	 */
	private function ajax_get_customer() {
		$customer_id = absint( $_POST['customer_id'] ?? 0 );
		$customer    = $this->customers->get( $customer_id );

		if ( ! $customer ) {
			wp_send_json_error( array( 'message' => __( 'Customer not found.', 'bkx-crm' ) ) );
		}

		$customer->tags       = $this->tags->get_customer_tags( $customer_id );
		$customer->notes      = $this->customers->get_notes( $customer_id );
		$customer->activity   = $this->activities->get_recent( $customer_id, 10 );
		$customer->bookings   = $this->customers->get_bookings( $customer_id );
		$customer->followups  = $this->followups->get_customer_followups( $customer_id );

		wp_send_json_success( $customer );
	}

	/**
	 * AJAX: Save customer.
	 */
	private function ajax_save_customer() {
		$customer_id = absint( $_POST['customer_id'] ?? 0 );

		$data = array(
			'email'         => sanitize_email( $_POST['email'] ?? '' ),
			'first_name'    => sanitize_text_field( $_POST['first_name'] ?? '' ),
			'last_name'     => sanitize_text_field( $_POST['last_name'] ?? '' ),
			'phone'         => sanitize_text_field( $_POST['phone'] ?? '' ),
			'company'       => sanitize_text_field( $_POST['company'] ?? '' ),
			'address_1'     => sanitize_text_field( $_POST['address_1'] ?? '' ),
			'address_2'     => sanitize_text_field( $_POST['address_2'] ?? '' ),
			'city'          => sanitize_text_field( $_POST['city'] ?? '' ),
			'state'         => sanitize_text_field( $_POST['state'] ?? '' ),
			'postcode'      => sanitize_text_field( $_POST['postcode'] ?? '' ),
			'country'       => sanitize_text_field( $_POST['country'] ?? '' ),
			'date_of_birth' => sanitize_text_field( $_POST['date_of_birth'] ?? '' ),
			'gender'        => sanitize_text_field( $_POST['gender'] ?? '' ),
			'status'        => sanitize_text_field( $_POST['status'] ?? 'active' ),
		);

		if ( empty( $data['email'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Email is required.', 'bkx-crm' ) ) );
		}

		if ( $customer_id ) {
			$result = $this->customers->update( $customer_id, $data );
		} else {
			$result = $this->customers->create( $data );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'     => __( 'Customer saved.', 'bkx-crm' ),
			'customer_id' => $result,
		) );
	}

	/**
	 * AJAX: Delete customer.
	 */
	private function ajax_delete_customer() {
		$customer_id = absint( $_POST['customer_id'] ?? 0 );

		if ( ! $customer_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid customer ID.', 'bkx-crm' ) ) );
		}

		$this->customers->delete( $customer_id );

		wp_send_json_success( array( 'message' => __( 'Customer deleted.', 'bkx-crm' ) ) );
	}

	/**
	 * AJAX: Get customers list.
	 */
	private function ajax_get_customers() {
		$args = array(
			'search'   => sanitize_text_field( $_POST['search'] ?? '' ),
			'tag'      => absint( $_POST['tag'] ?? 0 ),
			'segment'  => absint( $_POST['segment'] ?? 0 ),
			'status'   => sanitize_text_field( $_POST['status'] ?? '' ),
			'orderby'  => sanitize_text_field( $_POST['orderby'] ?? 'created_at' ),
			'order'    => sanitize_text_field( $_POST['order'] ?? 'DESC' ),
			'page'     => absint( $_POST['page'] ?? 1 ),
			'per_page' => absint( $_POST['per_page'] ?? 20 ),
		);

		$result = $this->customers->query( $args );

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Add tag.
	 */
	private function ajax_add_tag() {
		$name  = sanitize_text_field( $_POST['name'] ?? '' );
		$color = sanitize_hex_color( $_POST['color'] ?? '#3b82f6' );

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => __( 'Tag name is required.', 'bkx-crm' ) ) );
		}

		$result = $this->tags->create( $name, $color );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message' => __( 'Tag created.', 'bkx-crm' ),
			'tag'     => $this->tags->get( $result ),
		) );
	}

	/**
	 * AJAX: Delete tag.
	 */
	private function ajax_delete_tag() {
		$tag_id = absint( $_POST['tag_id'] ?? 0 );

		$this->tags->delete( $tag_id );

		wp_send_json_success( array( 'message' => __( 'Tag deleted.', 'bkx-crm' ) ) );
	}

	/**
	 * AJAX: Add tag to customer.
	 */
	private function ajax_add_customer_tag() {
		$customer_id = absint( $_POST['customer_id'] ?? 0 );
		$tag_id      = absint( $_POST['tag_id'] ?? 0 );

		$this->tags->add_to_customer( $customer_id, $tag_id );

		$this->activities->log( $customer_id, 'tag_added', sprintf(
			/* translators: %s: Tag name */
			__( 'Tag "%s" added', 'bkx-crm' ),
			$this->tags->get( $tag_id )->name
		) );

		wp_send_json_success();
	}

	/**
	 * AJAX: Remove tag from customer.
	 */
	private function ajax_remove_customer_tag() {
		$customer_id = absint( $_POST['customer_id'] ?? 0 );
		$tag_id      = absint( $_POST['tag_id'] ?? 0 );

		$this->tags->remove_from_customer( $customer_id, $tag_id );

		wp_send_json_success();
	}

	/**
	 * AJAX: Add note.
	 */
	private function ajax_add_note() {
		$customer_id = absint( $_POST['customer_id'] ?? 0 );
		$booking_id  = absint( $_POST['booking_id'] ?? 0 );
		$note_type   = sanitize_text_field( $_POST['note_type'] ?? 'general' );
		$content     = sanitize_textarea_field( $_POST['content'] ?? '' );
		$is_private  = ! empty( $_POST['is_private'] );

		if ( empty( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'Note content is required.', 'bkx-crm' ) ) );
		}

		$result = $this->customers->add_note( $customer_id, array(
			'booking_id' => $booking_id ?: null,
			'note_type'  => $note_type,
			'content'    => $content,
			'is_private' => $is_private,
			'created_by' => get_current_user_id(),
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$this->activities->log( $customer_id, 'note_added', __( 'Note added', 'bkx-crm' ) );

		wp_send_json_success( array(
			'message' => __( 'Note added.', 'bkx-crm' ),
			'note_id' => $result,
		) );
	}

	/**
	 * AJAX: Delete note.
	 */
	private function ajax_delete_note() {
		$note_id = absint( $_POST['note_id'] ?? 0 );

		$this->customers->delete_note( $note_id );

		wp_send_json_success( array( 'message' => __( 'Note deleted.', 'bkx-crm' ) ) );
	}

	/**
	 * AJAX: Get notes.
	 */
	private function ajax_get_notes() {
		$customer_id = absint( $_POST['customer_id'] ?? 0 );

		$notes = $this->customers->get_notes( $customer_id );

		wp_send_json_success( $notes );
	}

	/**
	 * AJAX: Save segment.
	 */
	private function ajax_save_segment() {
		$segment_id = absint( $_POST['segment_id'] ?? 0 );

		$data = array(
			'name'        => sanitize_text_field( $_POST['name'] ?? '' ),
			'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
			'conditions'  => isset( $_POST['conditions'] ) ? wp_unslash( $_POST['conditions'] ) : '[]',
			'is_dynamic'  => ! empty( $_POST['is_dynamic'] ),
		);

		if ( empty( $data['name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Segment name is required.', 'bkx-crm' ) ) );
		}

		if ( $segment_id ) {
			$result = $this->segments->update( $segment_id, $data );
		} else {
			$data['created_by'] = get_current_user_id();
			$result = $this->segments->create( $data );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array(
			'message'    => __( 'Segment saved.', 'bkx-crm' ),
			'segment_id' => $result,
		) );
	}

	/**
	 * AJAX: Delete segment.
	 */
	private function ajax_delete_segment() {
		$segment_id = absint( $_POST['segment_id'] ?? 0 );

		$this->segments->delete( $segment_id );

		wp_send_json_success( array( 'message' => __( 'Segment deleted.', 'bkx-crm' ) ) );
	}

	/**
	 * AJAX: Preview segment.
	 */
	private function ajax_preview_segment() {
		$conditions = isset( $_POST['conditions'] ) ? json_decode( wp_unslash( $_POST['conditions'] ), true ) : array();

		$customers = $this->segments->get_matching_customers( $conditions, 10 );

		wp_send_json_success( array(
			'customers' => $customers,
			'count'     => count( $customers ),
		) );
	}

	/**
	 * AJAX: Schedule followup.
	 */
	private function ajax_schedule_followup() {
		$customer_id    = absint( $_POST['customer_id'] ?? 0 );
		$followup_type  = sanitize_text_field( $_POST['followup_type'] ?? 'custom' );
		$channel        = sanitize_text_field( $_POST['channel'] ?? 'email' );
		$scheduled_at   = sanitize_text_field( $_POST['scheduled_at'] ?? '' );
		$custom_message = sanitize_textarea_field( $_POST['custom_message'] ?? '' );

		$result = $this->followups->schedule( array(
			'customer_id'    => $customer_id,
			'followup_type'  => $followup_type,
			'channel'        => $channel,
			'scheduled_at'   => $scheduled_at,
			'custom_message' => $custom_message,
			'created_by'     => get_current_user_id(),
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$this->activities->log( $customer_id, 'followup_scheduled', sprintf(
			/* translators: %s: Followup type */
			__( '%s follow-up scheduled', 'bkx-crm' ),
			ucfirst( $followup_type )
		) );

		wp_send_json_success( array(
			'message'     => __( 'Follow-up scheduled.', 'bkx-crm' ),
			'followup_id' => $result,
		) );
	}

	/**
	 * AJAX: Cancel followup.
	 */
	private function ajax_cancel_followup() {
		$followup_id = absint( $_POST['followup_id'] ?? 0 );

		$this->followups->cancel( $followup_id );

		wp_send_json_success( array( 'message' => __( 'Follow-up cancelled.', 'bkx-crm' ) ) );
	}

	/**
	 * AJAX: Get activity.
	 */
	private function ajax_get_activity() {
		$customer_id = absint( $_POST['customer_id'] ?? 0 );
		$limit       = absint( $_POST['limit'] ?? 20 );
		$offset      = absint( $_POST['offset'] ?? 0 );

		$activities = $this->activities->get_recent( $customer_id, $limit, $offset );

		wp_send_json_success( $activities );
	}

	/**
	 * AJAX: Log communication.
	 */
	private function ajax_log_communication() {
		$customer_id = absint( $_POST['customer_id'] ?? 0 );
		$channel     = sanitize_text_field( $_POST['channel'] ?? 'email' );
		$direction   = sanitize_text_field( $_POST['direction'] ?? 'outbound' );
		$subject     = sanitize_text_field( $_POST['subject'] ?? '' );
		$content     = sanitize_textarea_field( $_POST['content'] ?? '' );

		$result = $this->customers->log_communication( $customer_id, array(
			'channel'   => $channel,
			'direction' => $direction,
			'subject'   => $subject,
			'content'   => $content,
			'sent_by'   => get_current_user_id(),
		) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$this->activities->log( $customer_id, 'communication_logged', sprintf(
			/* translators: %s: Channel name */
			__( '%s communication logged', 'bkx-crm' ),
			ucfirst( $channel )
		) );

		wp_send_json_success( array( 'message' => __( 'Communication logged.', 'bkx-crm' ) ) );
	}

	/**
	 * AJAX: Export customers.
	 */
	private function ajax_export_customers() {
		$format     = sanitize_text_field( $_POST['format'] ?? 'csv' );
		$segment_id = absint( $_POST['segment_id'] ?? 0 );
		$tag_id     = absint( $_POST['tag_id'] ?? 0 );

		$args = array();

		if ( $segment_id ) {
			$args['segment'] = $segment_id;
		}

		if ( $tag_id ) {
			$args['tag'] = $tag_id;
		}

		$customers = $this->customers->query( $args );

		// Generate export.
		$filename = 'customers-export-' . date( 'Y-m-d' );

		if ( 'csv' === $format ) {
			$this->export_csv( $customers['items'], $filename );
		}

		wp_send_json_success();
	}

	/**
	 * Export customers to CSV.
	 *
	 * @param array  $customers Customers data.
	 * @param string $filename  Filename.
	 */
	private function export_csv( $customers, $filename ) {
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );

		$output = fopen( 'php://output', 'w' );

		// Header row.
		fputcsv( $output, array(
			'ID',
			'Email',
			'First Name',
			'Last Name',
			'Phone',
			'Company',
			'Total Bookings',
			'Lifetime Value',
			'Status',
			'Created At',
		) );

		// Data rows.
		foreach ( $customers as $customer ) {
			fputcsv( $output, array(
				$customer->id,
				$customer->email,
				$customer->first_name,
				$customer->last_name,
				$customer->phone,
				$customer->company,
				$customer->total_bookings,
				$customer->lifetime_value,
				$customer->status,
				$customer->created_at,
			) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Handle new booking created.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		$email = get_post_meta( $booking_id, 'customer_email', true );

		if ( empty( $email ) ) {
			return;
		}

		// Find or create customer.
		$customer = $this->customers->get_by_email( $email );

		if ( ! $customer ) {
			$customer_id = $this->customers->create( array(
				'email'      => $email,
				'first_name' => get_post_meta( $booking_id, 'customer_first_name', true ),
				'last_name'  => get_post_meta( $booking_id, 'customer_last_name', true ),
				'phone'      => get_post_meta( $booking_id, 'customer_phone', true ),
				'source'     => 'booking',
			) );

			// Add "New Customer" tag.
			$new_tag = $this->tags->get_by_slug( 'new-customer' );
			if ( $new_tag ) {
				$this->tags->add_to_customer( $customer_id, $new_tag->id );
			}
		} else {
			$customer_id = $customer->id;
		}

		// Update customer stats.
		$this->customers->increment_booking_count( $customer_id );

		// Add booking total to lifetime value.
		$total = get_post_meta( $booking_id, 'booking_total', true );
		if ( $total ) {
			$this->customers->add_to_lifetime_value( $customer_id, (float) $total );
		}

		// Log activity.
		$this->activities->log( $customer_id, 'booking_created', sprintf(
			/* translators: %d: Booking ID */
			__( 'Booking #%d created', 'bkx-crm' ),
			$booking_id
		), array( 'booking_id' => $booking_id ) );

		// Schedule followups if enabled.
		$settings = get_option( 'bkx_crm_settings', array() );

		if ( ! empty( $settings['auto_followup_enabled'] ) ) {
			// Schedule thank you email.
			if ( ! empty( $settings['thankyou_delay'] ) ) {
				$this->followups->schedule( array(
					'customer_id'   => $customer_id,
					'booking_id'    => $booking_id,
					'followup_type' => 'thankyou',
					'channel'       => 'email',
					'scheduled_at'  => date( 'Y-m-d H:i:s', strtotime( '+' . $settings['thankyou_delay'] . ' hours' ) ),
				) );
			}

			// Schedule feedback request.
			if ( ! empty( $settings['feedback_delay'] ) ) {
				$this->followups->schedule( array(
					'customer_id'   => $customer_id,
					'booking_id'    => $booking_id,
					'followup_type' => 'feedback',
					'channel'       => 'email',
					'scheduled_at'  => date( 'Y-m-d H:i:s', strtotime( '+' . $settings['feedback_delay'] . ' hours' ) ),
				) );
			}
		}
	}

	/**
	 * Handle booking status change.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $old_status Old status.
	 * @param string $new_status New status.
	 */
	public function on_booking_status_changed( $booking_id, $old_status, $new_status ) {
		$email = get_post_meta( $booking_id, 'customer_email', true );

		if ( empty( $email ) ) {
			return;
		}

		$customer = $this->customers->get_by_email( $email );

		if ( ! $customer ) {
			return;
		}

		$this->activities->log( $customer->id, 'booking_status_changed', sprintf(
			/* translators: 1: Booking ID, 2: Old status, 3: New status */
			__( 'Booking #%1$d status changed from %2$s to %3$s', 'bkx-crm' ),
			$booking_id,
			$old_status,
			$new_status
		), array(
			'booking_id' => $booking_id,
			'old_status' => $old_status,
			'new_status' => $new_status,
		) );
	}

	/**
	 * Add CRM column to bookings list.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_booking_columns( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;

			if ( 'title' === $key ) {
				$new_columns['bkx_crm_customer'] = __( 'Customer Profile', 'bkx-crm' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render CRM column content.
	 *
	 * @param string $column  Column name.
	 * @param int    $post_id Post ID.
	 */
	public function render_booking_columns( $column, $post_id ) {
		if ( 'bkx_crm_customer' !== $column ) {
			return;
		}

		$email = get_post_meta( $post_id, 'customer_email', true );

		if ( empty( $email ) ) {
			echo '&mdash;';
			return;
		}

		$customer = $this->customers->get_by_email( $email );

		if ( ! $customer ) {
			echo '<span class="bkx-crm-no-profile">' . esc_html__( 'No profile', 'bkx-crm' ) . '</span>';
			return;
		}

		$tags = $this->tags->get_customer_tags( $customer->id );

		echo '<div class="bkx-crm-customer-preview">';
		echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-crm&customer=' . $customer->id ) ) . '">';
		echo esc_html( $customer->first_name . ' ' . $customer->last_name );
		echo '</a>';

		if ( ! empty( $tags ) ) {
			echo '<div class="bkx-crm-tags">';
			foreach ( array_slice( $tags, 0, 3 ) as $tag ) {
				echo '<span class="bkx-crm-tag" style="background-color: ' . esc_attr( $tag->color ) . ';">';
				echo esc_html( $tag->name );
				echo '</span>';
			}
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Add metaboxes to booking edit screen.
	 */
	public function add_booking_metaboxes() {
		add_meta_box(
			'bkx-crm-customer-profile',
			__( 'Customer Profile', 'bkx-crm' ),
			array( $this, 'render_customer_metabox' ),
			'bkx_booking',
			'side',
			'high'
		);
	}

	/**
	 * Render customer metabox.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function render_customer_metabox( $post ) {
		$email = get_post_meta( $post->ID, 'customer_email', true );

		if ( empty( $email ) ) {
			echo '<p>' . esc_html__( 'No customer email found.', 'bkx-crm' ) . '</p>';
			return;
		}

		$customer = $this->customers->get_by_email( $email );

		include BKX_CRM_PLUGIN_DIR . 'templates/admin/metabox-customer.php';
	}
}
