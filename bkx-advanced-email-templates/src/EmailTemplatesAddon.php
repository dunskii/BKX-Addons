<?php
/**
 * Main Email Templates Addon Class.
 *
 * @package BookingX\AdvancedEmailTemplates
 */

namespace BookingX\AdvancedEmailTemplates;

use BookingX\AdvancedEmailTemplates\Services\TemplateService;
use BookingX\AdvancedEmailTemplates\Services\EmailService;
use BookingX\AdvancedEmailTemplates\Services\VariableService;
use BookingX\AdvancedEmailTemplates\Services\PreviewService;

defined( 'ABSPATH' ) || exit;

/**
 * EmailTemplatesAddon class.
 */
class EmailTemplatesAddon {

	/**
	 * Template service.
	 *
	 * @var TemplateService
	 */
	private $template_service;

	/**
	 * Email service.
	 *
	 * @var EmailService
	 */
	private $email_service;

	/**
	 * Variable service.
	 *
	 * @var VariableService
	 */
	private $variable_service;

	/**
	 * Preview service.
	 *
	 * @var PreviewService
	 */
	private $preview_service;

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
		$this->variable_service = new VariableService();
		$this->template_service = new TemplateService();
		$this->email_service    = new EmailService( $this->variable_service );
		$this->preview_service  = new PreviewService( $this->variable_service );
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Intercept BookingX emails.
		add_filter( 'bkx_email_template', array( $this, 'filter_email_template' ), 10, 3 );
		add_filter( 'bkx_email_subject', array( $this, 'filter_email_subject' ), 10, 3 );
		add_filter( 'bkx_email_headers', array( $this, 'filter_email_headers' ), 10, 3 );

		// Booking lifecycle hooks.
		add_action( 'bkx_booking_confirmed', array( $this, 'on_booking_confirmed' ), 10, 2 );
		add_action( 'bkx_booking_cancelled', array( $this, 'on_booking_cancelled' ), 10, 2 );
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_email_save_template', array( $this, 'ajax_save_template' ) );
		add_action( 'wp_ajax_bkx_email_delete_template', array( $this, 'ajax_delete_template' ) );
		add_action( 'wp_ajax_bkx_email_duplicate_template', array( $this, 'ajax_duplicate_template' ) );
		add_action( 'wp_ajax_bkx_email_preview_template', array( $this, 'ajax_preview_template' ) );
		add_action( 'wp_ajax_bkx_email_send_test', array( $this, 'ajax_send_test' ) );
		add_action( 'wp_ajax_bkx_email_get_variables', array( $this, 'ajax_get_variables' ) );
		add_action( 'wp_ajax_bkx_email_save_block', array( $this, 'ajax_save_block' ) );
		add_action( 'wp_ajax_bkx_email_get_blocks', array( $this, 'ajax_get_blocks' ) );
		add_action( 'wp_ajax_bkx_email_delete_block', array( $this, 'ajax_delete_block' ) );
		add_action( 'wp_ajax_bkx_email_get_logs', array( $this, 'ajax_get_logs' ) );

		// Track email opens.
		add_action( 'init', array( $this, 'track_email_open' ) );

		// Settings link.
		add_filter( 'plugin_action_links_' . BKX_EMAIL_TEMPLATES_BASENAME, array( $this, 'add_settings_link' ) );

		// Cron for reminders.
		add_action( 'bkx_email_send_reminders', array( $this, 'send_scheduled_reminders' ) );

		if ( ! wp_next_scheduled( 'bkx_email_send_reminders' ) ) {
			wp_schedule_event( time(), 'hourly', 'bkx_email_send_reminders' );
		}
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Email Templates', 'bkx-advanced-email-templates' ),
			__( 'Email Templates', 'bkx-advanced-email-templates' ),
			'manage_options',
			'bkx-email-templates',
			array( $this, 'render_templates_page' )
		);

		add_submenu_page(
			'bookingx',
			__( 'Email Logs', 'bkx-advanced-email-templates' ),
			__( 'Email Logs', 'bkx-advanced-email-templates' ),
			'manage_options',
			'bkx-email-logs',
			array( $this, 'render_logs_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-email' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-email-templates-admin',
			BKX_EMAIL_TEMPLATES_URL . 'assets/css/admin.css',
			array(),
			BKX_EMAIL_TEMPLATES_VERSION
		);

		wp_enqueue_script(
			'bkx-email-templates-admin',
			BKX_EMAIL_TEMPLATES_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			BKX_EMAIL_TEMPLATES_VERSION,
			true
		);

		// Template editor page.
		if ( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'edit', 'new' ), true ) ) {
			wp_enqueue_editor();
			wp_enqueue_media();

			wp_enqueue_script(
				'bkx-email-editor',
				BKX_EMAIL_TEMPLATES_URL . 'assets/js/editor.js',
				array( 'jquery', 'wp-util', 'jquery-ui-sortable' ),
				BKX_EMAIL_TEMPLATES_VERSION,
				true
			);
		}

		wp_localize_script(
			'bkx-email-templates-admin',
			'bkxEmailTemplates',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'bkx_email_templates' ),
				'variables' => $this->variable_service->get_all_variables(),
				'i18n'      => array(
					'confirmDelete'    => __( 'Are you sure you want to delete this template?', 'bkx-advanced-email-templates' ),
					'confirmDuplicate' => __( 'Duplicate this template?', 'bkx-advanced-email-templates' ),
					'testEmailSent'    => __( 'Test email sent successfully!', 'bkx-advanced-email-templates' ),
					'savingTemplate'   => __( 'Saving...', 'bkx-advanced-email-templates' ),
					'saved'            => __( 'Saved!', 'bkx-advanced-email-templates' ),
				),
			)
		);
	}

	/**
	 * Render templates page.
	 */
	public function render_templates_page() {
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		switch ( $action ) {
			case 'edit':
			case 'new':
				$template_id = isset( $_GET['template_id'] ) ? absint( $_GET['template_id'] ) : 0;
				$template    = $template_id ? $this->template_service->get_template( $template_id ) : null;
				include BKX_EMAIL_TEMPLATES_PATH . 'templates/admin/editor.php';
				break;

			default:
				$templates = $this->template_service->get_all_templates();
				include BKX_EMAIL_TEMPLATES_PATH . 'templates/admin/list.php';
				break;
		}
	}

	/**
	 * Render logs page.
	 */
	public function render_logs_page() {
		global $wpdb;

		$per_page     = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$offset       = ( $current_page - 1 ) * $per_page;

		$table = $wpdb->prefix . 'bkx_email_logs';
		$logs  = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT l.*, t.name as template_name
				FROM {$table} l
				LEFT JOIN {$wpdb->prefix}bkx_email_templates t ON l.template_id = t.id
				ORDER BY l.sent_at DESC
				LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
		$pages = ceil( $total / $per_page );

		include BKX_EMAIL_TEMPLATES_PATH . 'templates/admin/logs.php';
	}

	/**
	 * Filter email template.
	 *
	 * @param string $content Email content.
	 * @param string $event   Event name.
	 * @param array  $data    Email data.
	 * @return string
	 */
	public function filter_email_template( $content, $event, $data ) {
		$template = $this->template_service->get_template_by_event( $event );

		if ( $template && 'active' === $template->status ) {
			return $this->email_service->render_template( $template, $data );
		}

		return $content;
	}

	/**
	 * Filter email subject.
	 *
	 * @param string $subject Email subject.
	 * @param string $event   Event name.
	 * @param array  $data    Email data.
	 * @return string
	 */
	public function filter_email_subject( $subject, $event, $data ) {
		$template = $this->template_service->get_template_by_event( $event );

		if ( $template && 'active' === $template->status ) {
			return $this->variable_service->replace_variables( $template->subject, $data );
		}

		return $subject;
	}

	/**
	 * Filter email headers.
	 *
	 * @param array  $headers Email headers.
	 * @param string $event   Event name.
	 * @param array  $data    Email data.
	 * @return array
	 */
	public function filter_email_headers( $headers, $event, $data ) {
		$template = $this->template_service->get_template_by_event( $event );

		if ( $template && 'active' === $template->status && $template->preheader ) {
			// Add preheader as X-Preview header (some clients use this).
			$headers[] = 'X-Preview-Text: ' . $this->variable_service->replace_variables( $template->preheader, $data );
		}

		return $headers;
	}

	/**
	 * On booking confirmed.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_confirmed( $booking_id, $booking_data ) {
		$this->email_service->send_booking_email( 'bkx_booking_confirmed', $booking_id );
	}

	/**
	 * On booking cancelled.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_cancelled( $booking_id, $booking_data ) {
		$this->email_service->send_booking_email( 'bkx_booking_cancelled', $booking_id );
	}

	/**
	 * On booking created.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		// Send admin notification.
		$this->email_service->send_admin_notification( 'bkx_booking_created', $booking_id );
	}

	/**
	 * AJAX: Save template.
	 */
	public function ajax_save_template() {
		check_ajax_referer( 'bkx_email_templates', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-email-templates' ) ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

		$data = array(
			'name'          => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'slug'          => isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '',
			'subject'       => isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '',
			'preheader'     => isset( $_POST['preheader'] ) ? sanitize_text_field( wp_unslash( $_POST['preheader'] ) ) : '',
			'content'       => isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '',
			'design_data'   => isset( $_POST['design_data'] ) ? sanitize_text_field( wp_unslash( $_POST['design_data'] ) ) : '',
			'template_type' => isset( $_POST['template_type'] ) ? sanitize_text_field( wp_unslash( $_POST['template_type'] ) ) : 'custom',
			'trigger_event' => isset( $_POST['trigger_event'] ) ? sanitize_text_field( wp_unslash( $_POST['trigger_event'] ) ) : '',
			'status'        => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'active',
		);

		if ( empty( $data['name'] ) || empty( $data['subject'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Name and subject are required.', 'bkx-advanced-email-templates' ) ) );
		}

		$result = $this->template_service->save_template( $template_id, $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'     => __( 'Template saved successfully.', 'bkx-advanced-email-templates' ),
				'template_id' => $result,
			)
		);
	}

	/**
	 * AJAX: Delete template.
	 */
	public function ajax_delete_template() {
		check_ajax_referer( 'bkx_email_templates', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-email-templates' ) ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

		if ( ! $template_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template ID.', 'bkx-advanced-email-templates' ) ) );
		}

		$result = $this->template_service->delete_template( $template_id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete template.', 'bkx-advanced-email-templates' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Template deleted.', 'bkx-advanced-email-templates' ) ) );
	}

	/**
	 * AJAX: Duplicate template.
	 */
	public function ajax_duplicate_template() {
		check_ajax_referer( 'bkx_email_templates', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-email-templates' ) ) );
		}

		$template_id = isset( $_POST['template_id'] ) ? absint( $_POST['template_id'] ) : 0;

		if ( ! $template_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template ID.', 'bkx-advanced-email-templates' ) ) );
		}

		$new_id = $this->template_service->duplicate_template( $template_id );

		if ( ! $new_id ) {
			wp_send_json_error( array( 'message' => __( 'Failed to duplicate template.', 'bkx-advanced-email-templates' ) ) );
		}

		wp_send_json_success(
			array(
				'message'     => __( 'Template duplicated.', 'bkx-advanced-email-templates' ),
				'template_id' => $new_id,
			)
		);
	}

	/**
	 * AJAX: Preview template.
	 */
	public function ajax_preview_template() {
		check_ajax_referer( 'bkx_email_templates', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-email-templates' ) ) );
		}

		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';

		$preview = $this->preview_service->render_preview( $content, $subject );

		wp_send_json_success( array( 'html' => $preview ) );
	}

	/**
	 * AJAX: Send test email.
	 */
	public function ajax_send_test() {
		check_ajax_referer( 'bkx_email_templates', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-email-templates' ) ) );
		}

		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid email address.', 'bkx-advanced-email-templates' ) ) );
		}

		$result = $this->email_service->send_test_email( $email, $subject, $content );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to send test email.', 'bkx-advanced-email-templates' ) ) );
		}

		wp_send_json_success( array( 'message' => __( 'Test email sent!', 'bkx-advanced-email-templates' ) ) );
	}

	/**
	 * AJAX: Get variables.
	 */
	public function ajax_get_variables() {
		check_ajax_referer( 'bkx_email_templates', 'nonce' );

		wp_send_json_success( array( 'variables' => $this->variable_service->get_all_variables() ) );
	}

	/**
	 * AJAX: Save block.
	 */
	public function ajax_save_block() {
		check_ajax_referer( 'bkx_email_templates', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-email-templates' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_email_blocks';

		$block_id = isset( $_POST['block_id'] ) ? absint( $_POST['block_id'] ) : 0;

		$data = array(
			'name'      => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
			'type'      => isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'custom',
			'content'   => isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '',
			'settings'  => isset( $_POST['settings'] ) ? sanitize_text_field( wp_unslash( $_POST['settings'] ) ) : '',
			'is_global' => isset( $_POST['is_global'] ) ? 1 : 0,
		);

		if ( $block_id ) {
			$wpdb->update( $table, $data, array( 'id' => $block_id ) ); // phpcs:ignore
		} else {
			$wpdb->insert( $table, $data ); // phpcs:ignore
			$block_id = $wpdb->insert_id;
		}

		wp_send_json_success(
			array(
				'message'  => __( 'Block saved.', 'bkx-advanced-email-templates' ),
				'block_id' => $block_id,
			)
		);
	}

	/**
	 * AJAX: Get blocks.
	 */
	public function ajax_get_blocks() {
		check_ajax_referer( 'bkx_email_templates', 'nonce' );

		global $wpdb;
		$table  = $wpdb->prefix . 'bkx_email_blocks';
		$blocks = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" ); // phpcs:ignore

		wp_send_json_success( array( 'blocks' => $blocks ) );
	}

	/**
	 * AJAX: Delete block.
	 */
	public function ajax_delete_block() {
		check_ajax_referer( 'bkx_email_templates', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-email-templates' ) ) );
		}

		$block_id = isset( $_POST['block_id'] ) ? absint( $_POST['block_id'] ) : 0;

		if ( ! $block_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid block ID.', 'bkx-advanced-email-templates' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_email_blocks';
		$wpdb->delete( $table, array( 'id' => $block_id ) ); // phpcs:ignore

		wp_send_json_success( array( 'message' => __( 'Block deleted.', 'bkx-advanced-email-templates' ) ) );
	}

	/**
	 * AJAX: Get logs.
	 */
	public function ajax_get_logs() {
		check_ajax_referer( 'bkx_email_templates', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-advanced-email-templates' ) ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_email_logs';
		$page  = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$limit = 20;
		$offset = ( $page - 1 ) * $limit;

		$logs = $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT l.*, t.name as template_name
				FROM {$table} l
				LEFT JOIN {$wpdb->prefix}bkx_email_templates t ON l.template_id = t.id
				ORDER BY l.sent_at DESC
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore

		wp_send_json_success(
			array(
				'logs'  => $logs,
				'total' => $total,
				'pages' => ceil( $total / $limit ),
			)
		);
	}

	/**
	 * Track email open.
	 */
	public function track_email_open() {
		if ( ! isset( $_GET['bkx_email_track'] ) ) {
			return;
		}

		$log_id = absint( $_GET['bkx_email_track'] );

		if ( ! $log_id ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_email_logs';

		$wpdb->update( // phpcs:ignore
			$table,
			array( 'opened_at' => current_time( 'mysql' ) ),
			array( 'id' => $log_id ),
			array( '%s' ),
			array( '%d' )
		);

		// Return a 1x1 transparent pixel.
		header( 'Content-Type: image/gif' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo base64_decode( 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' );
		exit;
	}

	/**
	 * Send scheduled reminders.
	 */
	public function send_scheduled_reminders() {
		$this->email_service->send_upcoming_reminders();
	}

	/**
	 * Add settings link.
	 *
	 * @param array $links Plugin links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'admin.php?page=bkx-email-templates' ) . '">' . __( 'Templates', 'bkx-advanced-email-templates' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}
