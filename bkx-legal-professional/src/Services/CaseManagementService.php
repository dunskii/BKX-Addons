<?php
/**
 * Case/Matter Management Service.
 *
 * Handles matter lifecycle, case assignment, status tracking, and deadlines.
 *
 * @package BookingX\LegalProfessional
 * @since   1.0.0
 */

namespace BookingX\LegalProfessional\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Case Management Service class.
 *
 * @since 1.0.0
 */
class CaseManagementService {

	/**
	 * Service instance.
	 *
	 * @var CaseManagementService|null
	 */
	private static ?CaseManagementService $instance = null;

	/**
	 * Get service instance.
	 *
	 * @return CaseManagementService
	 */
	public static function get_instance(): CaseManagementService {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'save_post_bkx_matter', array( $this, 'save_matter_meta' ), 10, 2 );
		add_action( 'bkx_booking_created', array( $this, 'link_booking_to_matter' ), 10, 2 );
		add_action( 'bkx_legal_daily_deadline_check', array( $this, 'check_upcoming_deadlines' ) );

		// Schedule deadline checks.
		if ( ! wp_next_scheduled( 'bkx_legal_daily_deadline_check' ) ) {
			wp_schedule_event( time(), 'daily', 'bkx_legal_daily_deadline_check' );
		}
	}

	/**
	 * Create a new matter.
	 *
	 * @param array $data Matter data.
	 * @return int|WP_Error Matter ID or error.
	 */
	public function create_matter( array $data ) {
		$defaults = array(
			'client_id'         => 0,
			'matter_name'       => '',
			'matter_number'     => '',
			'practice_area'     => '',
			'matter_type'       => '',
			'description'       => '',
			'responsible_attorney' => 0,
			'originating_attorney' => 0,
			'billing_attorney'  => 0,
			'status'            => 'active',
			'open_date'         => current_time( 'Y-m-d' ),
			'close_date'        => '',
			'statute_of_limitations' => '',
			'retainer_id'       => 0,
			'fee_arrangement'   => 'hourly', // hourly, flat, contingency, hybrid.
			'hourly_rate'       => 0,
			'flat_fee'          => 0,
			'contingency_percent' => 0,
			'estimated_value'   => 0,
		);

		$data = wp_parse_args( $data, $defaults );

		// Generate matter number if not provided.
		if ( empty( $data['matter_number'] ) ) {
			$data['matter_number'] = $this->generate_matter_number();
		}

		// Create matter post.
		$matter_id = wp_insert_post( array(
			'post_type'    => 'bkx_matter',
			'post_title'   => sanitize_text_field( $data['matter_name'] ),
			'post_content' => wp_kses_post( $data['description'] ),
			'post_status'  => 'publish',
		) );

		if ( is_wp_error( $matter_id ) ) {
			return $matter_id;
		}

		// Save meta data.
		$meta_fields = array(
			'client_id', 'matter_number', 'responsible_attorney', 'originating_attorney',
			'billing_attorney', 'status', 'open_date', 'close_date', 'statute_of_limitations',
			'retainer_id', 'fee_arrangement', 'hourly_rate', 'flat_fee', 'contingency_percent',
			'estimated_value',
		);

		foreach ( $meta_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				update_post_meta( $matter_id, '_bkx_' . $field, $data[ $field ] );
			}
		}

		// Set taxonomies.
		if ( ! empty( $data['practice_area'] ) ) {
			wp_set_object_terms( $matter_id, array( $data['practice_area'] ), 'bkx_practice_area' );
		}

		if ( ! empty( $data['matter_type'] ) ) {
			wp_set_object_terms( $matter_id, array( $data['matter_type'] ), 'bkx_matter_type' );
		}

		if ( ! empty( $data['status'] ) ) {
			wp_set_object_terms( $matter_id, array( $data['status'] ), 'bkx_matter_status' );
		}

		// Log creation.
		$this->log_matter_activity( $matter_id, 'created', __( 'Matter created', 'bkx-legal-professional' ) );

		/**
		 * Fires after a matter is created.
		 *
		 * @param int   $matter_id The matter ID.
		 * @param array $data      The matter data.
		 */
		do_action( 'bkx_legal_matter_created', $matter_id, $data );

		return $matter_id;
	}

	/**
	 * Update matter.
	 *
	 * @param int   $matter_id Matter ID.
	 * @param array $data      Matter data.
	 * @return bool|WP_Error
	 */
	public function update_matter( int $matter_id, array $data ) {
		$matter = get_post( $matter_id );

		if ( ! $matter || 'bkx_matter' !== $matter->post_type ) {
			return new \WP_Error( 'invalid_matter', __( 'Invalid matter ID', 'bkx-legal-professional' ) );
		}

		// Update post.
		if ( isset( $data['matter_name'] ) || isset( $data['description'] ) ) {
			wp_update_post( array(
				'ID'           => $matter_id,
				'post_title'   => isset( $data['matter_name'] ) ? sanitize_text_field( $data['matter_name'] ) : $matter->post_title,
				'post_content' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : $matter->post_content,
			) );
		}

		// Track status change.
		$old_status = get_post_meta( $matter_id, '_bkx_status', true );

		// Update meta.
		$meta_fields = array(
			'client_id', 'responsible_attorney', 'originating_attorney', 'billing_attorney',
			'status', 'open_date', 'close_date', 'statute_of_limitations', 'retainer_id',
			'fee_arrangement', 'hourly_rate', 'flat_fee', 'contingency_percent', 'estimated_value',
		);

		foreach ( $meta_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				update_post_meta( $matter_id, '_bkx_' . $field, $data[ $field ] );
			}
		}

		// Log status change.
		if ( isset( $data['status'] ) && $old_status !== $data['status'] ) {
			$this->log_matter_activity(
				$matter_id,
				'status_changed',
				sprintf(
					/* translators: 1: old status 2: new status */
					__( 'Status changed from %1$s to %2$s', 'bkx-legal-professional' ),
					$old_status,
					$data['status']
				)
			);

			wp_set_object_terms( $matter_id, array( $data['status'] ), 'bkx_matter_status' );
		}

		/**
		 * Fires after a matter is updated.
		 *
		 * @param int   $matter_id The matter ID.
		 * @param array $data      The updated data.
		 */
		do_action( 'bkx_legal_matter_updated', $matter_id, $data );

		return true;
	}

	/**
	 * Close a matter.
	 *
	 * @param int    $matter_id Matter ID.
	 * @param string $reason    Closure reason.
	 * @return bool|WP_Error
	 */
	public function close_matter( int $matter_id, string $reason = '' ) {
		$result = $this->update_matter( $matter_id, array(
			'status'     => 'closed',
			'close_date' => current_time( 'Y-m-d' ),
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! empty( $reason ) ) {
			update_post_meta( $matter_id, '_bkx_close_reason', sanitize_text_field( $reason ) );
		}

		$this->log_matter_activity( $matter_id, 'closed', __( 'Matter closed', 'bkx-legal-professional' ) . ( $reason ? ': ' . $reason : '' ) );

		/**
		 * Fires after a matter is closed.
		 *
		 * @param int    $matter_id The matter ID.
		 * @param string $reason    The closure reason.
		 */
		do_action( 'bkx_legal_matter_closed', $matter_id, $reason );

		return true;
	}

	/**
	 * Reopen a matter.
	 *
	 * @param int    $matter_id Matter ID.
	 * @param string $reason    Reopen reason.
	 * @return bool|WP_Error
	 */
	public function reopen_matter( int $matter_id, string $reason = '' ) {
		$result = $this->update_matter( $matter_id, array(
			'status'     => 'active',
			'close_date' => '',
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->log_matter_activity( $matter_id, 'reopened', __( 'Matter reopened', 'bkx-legal-professional' ) . ( $reason ? ': ' . $reason : '' ) );

		return true;
	}

	/**
	 * Get matter by ID.
	 *
	 * @param int $matter_id Matter ID.
	 * @return array|null Matter data or null.
	 */
	public function get_matter( int $matter_id ): ?array {
		$matter = get_post( $matter_id );

		if ( ! $matter || 'bkx_matter' !== $matter->post_type ) {
			return null;
		}

		return $this->format_matter( $matter );
	}

	/**
	 * Get matters for a client.
	 *
	 * @param int    $client_id Client user ID.
	 * @param string $status    Filter by status.
	 * @return array
	 */
	public function get_client_matters( int $client_id, string $status = '' ): array {
		$args = array(
			'post_type'      => 'bkx_matter',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => '_bkx_client_id',
					'value' => $client_id,
					'type'  => 'NUMERIC',
				),
			),
		);

		if ( ! empty( $status ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'bkx_matter_status',
					'field'    => 'slug',
					'terms'    => $status,
				),
			);
		}

		$matters = get_posts( $args );
		$result  = array();

		foreach ( $matters as $matter ) {
			$result[] = $this->format_matter( $matter );
		}

		return $result;
	}

	/**
	 * Get matters for an attorney.
	 *
	 * @param int    $attorney_id Attorney user ID.
	 * @param string $role        Attorney role: responsible, originating, or billing.
	 * @param string $status      Filter by status.
	 * @return array
	 */
	public function get_attorney_matters( int $attorney_id, string $role = 'responsible', string $status = '' ): array {
		$meta_key = '_bkx_' . $role . '_attorney';

		$args = array(
			'post_type'      => 'bkx_matter',
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'   => $meta_key,
					'value' => $attorney_id,
					'type'  => 'NUMERIC',
				),
			),
		);

		if ( ! empty( $status ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'bkx_matter_status',
					'field'    => 'slug',
					'terms'    => $status,
				),
			);
		}

		$matters = get_posts( $args );
		$result  = array();

		foreach ( $matters as $matter ) {
			$result[] = $this->format_matter( $matter );
		}

		return $result;
	}

	/**
	 * Format matter data.
	 *
	 * @param WP_Post $matter Matter post object.
	 * @return array
	 */
	private function format_matter( $matter ): array {
		$practice_areas = wp_get_object_terms( $matter->ID, 'bkx_practice_area', array( 'fields' => 'names' ) );
		$matter_types   = wp_get_object_terms( $matter->ID, 'bkx_matter_type', array( 'fields' => 'names' ) );
		$statuses       = wp_get_object_terms( $matter->ID, 'bkx_matter_status', array( 'fields' => 'slugs' ) );

		return array(
			'id'                    => $matter->ID,
			'matter_name'           => $matter->post_title,
			'matter_number'         => get_post_meta( $matter->ID, '_bkx_matter_number', true ),
			'description'           => $matter->post_content,
			'client_id'             => (int) get_post_meta( $matter->ID, '_bkx_client_id', true ),
			'client_name'           => $this->get_client_name( (int) get_post_meta( $matter->ID, '_bkx_client_id', true ) ),
			'responsible_attorney'  => (int) get_post_meta( $matter->ID, '_bkx_responsible_attorney', true ),
			'originating_attorney'  => (int) get_post_meta( $matter->ID, '_bkx_originating_attorney', true ),
			'billing_attorney'      => (int) get_post_meta( $matter->ID, '_bkx_billing_attorney', true ),
			'practice_area'         => ! empty( $practice_areas ) ? $practice_areas[0] : '',
			'matter_type'           => ! empty( $matter_types ) ? $matter_types[0] : '',
			'status'                => ! empty( $statuses ) ? $statuses[0] : 'active',
			'open_date'             => get_post_meta( $matter->ID, '_bkx_open_date', true ),
			'close_date'            => get_post_meta( $matter->ID, '_bkx_close_date', true ),
			'statute_of_limitations' => get_post_meta( $matter->ID, '_bkx_statute_of_limitations', true ),
			'retainer_id'           => (int) get_post_meta( $matter->ID, '_bkx_retainer_id', true ),
			'fee_arrangement'       => get_post_meta( $matter->ID, '_bkx_fee_arrangement', true ) ?: 'hourly',
			'hourly_rate'           => (float) get_post_meta( $matter->ID, '_bkx_hourly_rate', true ),
			'flat_fee'              => (float) get_post_meta( $matter->ID, '_bkx_flat_fee', true ),
			'contingency_percent'   => (float) get_post_meta( $matter->ID, '_bkx_contingency_percent', true ),
			'estimated_value'       => (float) get_post_meta( $matter->ID, '_bkx_estimated_value', true ),
			'created_date'          => $matter->post_date,
		);
	}

	/**
	 * Get client name.
	 *
	 * @param int $client_id Client user ID.
	 * @return string
	 */
	private function get_client_name( int $client_id ): string {
		$user = get_user_by( 'id', $client_id );
		return $user ? $user->display_name : '';
	}

	/**
	 * Generate unique matter number.
	 *
	 * @return string
	 */
	private function generate_matter_number(): string {
		$settings = get_option( 'bkx_legal_settings', array() );
		$prefix   = $settings['matter_number_prefix'] ?? 'M';
		$year     = gmdate( 'Y' );

		// Get next sequence number.
		$sequence_key = 'bkx_legal_matter_sequence_' . $year;
		$sequence     = (int) get_option( $sequence_key, 0 ) + 1;
		update_option( $sequence_key, $sequence );

		return sprintf( '%s%s-%04d', $prefix, $year, $sequence );
	}

	/**
	 * Add deadline to matter.
	 *
	 * @param int    $matter_id    Matter ID.
	 * @param string $title        Deadline title.
	 * @param string $due_date     Due date.
	 * @param string $type         Deadline type: statute, filing, hearing, discovery, other.
	 * @param string $description  Optional description.
	 * @param int    $reminder_days Days before to remind.
	 * @return int|WP_Error Deadline ID or error.
	 */
	public function add_deadline( int $matter_id, string $title, string $due_date, string $type = 'other', string $description = '', int $reminder_days = 7 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_legal_deadlines';

		// Verify matter exists.
		$matter = get_post( $matter_id );
		if ( ! $matter || 'bkx_matter' !== $matter->post_type ) {
			return new \WP_Error( 'invalid_matter', __( 'Invalid matter ID', 'bkx-legal-professional' ) );
		}

		$result = $wpdb->insert(
			$table,
			array(
				'matter_id'     => $matter_id,
				'title'         => sanitize_text_field( $title ),
				'due_date'      => sanitize_text_field( $due_date ),
				'deadline_type' => sanitize_text_field( $type ),
				'description'   => sanitize_textarea_field( $description ),
				'reminder_days' => $reminder_days,
				'status'        => 'pending',
				'created_by'    => get_current_user_id(),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create deadline', 'bkx-legal-professional' ) );
		}

		$deadline_id = $wpdb->insert_id;

		$this->log_matter_activity( $matter_id, 'deadline_added', sprintf(
			/* translators: 1: deadline title 2: due date */
			__( 'Deadline added: %1$s (Due: %2$s)', 'bkx-legal-professional' ),
			$title,
			$due_date
		) );

		return $deadline_id;
	}

	/**
	 * Get matter deadlines.
	 *
	 * @param int    $matter_id Matter ID.
	 * @param string $status    Filter by status: pending, completed, all.
	 * @return array
	 */
	public function get_deadlines( int $matter_id, string $status = 'all' ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_legal_deadlines';

		$sql = $wpdb->prepare(
			"SELECT * FROM %i WHERE matter_id = %d",
			$table,
			$matter_id
		);

		if ( 'all' !== $status ) {
			$sql .= $wpdb->prepare( " AND status = %s", $status );
		}

		$sql .= ' ORDER BY due_date ASC';

		$results = $wpdb->get_results( $sql, ARRAY_A );

		return $results ?: array();
	}

	/**
	 * Complete a deadline.
	 *
	 * @param int    $deadline_id Deadline ID.
	 * @param string $notes       Completion notes.
	 * @return bool
	 */
	public function complete_deadline( int $deadline_id, string $notes = '' ): bool {
		global $wpdb;

		$table    = $wpdb->prefix . 'bkx_legal_deadlines';
		$deadline = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table, $deadline_id ) );

		if ( ! $deadline ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			array(
				'status'         => 'completed',
				'completed_at'   => current_time( 'mysql' ),
				'completed_by'   => get_current_user_id(),
				'completion_notes' => sanitize_textarea_field( $notes ),
			),
			array( 'id' => $deadline_id ),
			array( '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			$this->log_matter_activity( $deadline->matter_id, 'deadline_completed', sprintf(
				/* translators: %s: deadline title */
				__( 'Deadline completed: %s', 'bkx-legal-professional' ),
				$deadline->title
			) );
		}

		return false !== $result;
	}

	/**
	 * Check for upcoming deadlines and send reminders.
	 *
	 * @return void
	 */
	public function check_upcoming_deadlines(): void {
		global $wpdb;

		$table   = $wpdb->prefix . 'bkx_legal_deadlines';
		$today   = current_time( 'Y-m-d' );

		// Get pending deadlines where reminder should be sent.
		$deadlines = $wpdb->get_results( $wpdb->prepare(
			"SELECT d.*, m.post_title as matter_name
			 FROM %i d
			 LEFT JOIN {$wpdb->posts} m ON d.matter_id = m.ID
			 WHERE d.status = 'pending'
			 AND d.reminder_sent = 0
			 AND DATE_SUB(d.due_date, INTERVAL d.reminder_days DAY) <= %s",
			$table,
			$today
		) );

		foreach ( $deadlines as $deadline ) {
			$this->send_deadline_reminder( $deadline );

			// Mark reminder as sent.
			$wpdb->update(
				$table,
				array( 'reminder_sent' => 1, 'reminder_sent_at' => current_time( 'mysql' ) ),
				array( 'id' => $deadline->id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		// Check for overdue deadlines.
		$overdue = $wpdb->get_results( $wpdb->prepare(
			"SELECT d.*, m.post_title as matter_name
			 FROM %i d
			 LEFT JOIN {$wpdb->posts} m ON d.matter_id = m.ID
			 WHERE d.status = 'pending'
			 AND d.due_date < %s
			 AND d.overdue_notified = 0",
			$table,
			$today
		) );

		foreach ( $overdue as $deadline ) {
			$this->send_overdue_notification( $deadline );

			$wpdb->update(
				$table,
				array( 'overdue_notified' => 1 ),
				array( 'id' => $deadline->id ),
				array( '%d' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Send deadline reminder.
	 *
	 * @param object $deadline Deadline object.
	 * @return void
	 */
	private function send_deadline_reminder( object $deadline ): void {
		$responsible = (int) get_post_meta( $deadline->matter_id, '_bkx_responsible_attorney', true );
		$user        = get_user_by( 'id', $responsible );

		if ( ! $user ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: deadline title 2: matter name */
			__( 'Upcoming Deadline: %1$s - %2$s', 'bkx-legal-professional' ),
			$deadline->title,
			$deadline->matter_name
		);

		$message = sprintf(
			/* translators: 1: user name 2: deadline title 3: matter name 4: due date */
			__( "Dear %1\$s,\n\nThis is a reminder about the upcoming deadline:\n\nDeadline: %2\$s\nMatter: %3\$s\nDue Date: %4\$s\n\nPlease ensure this deadline is met.\n\nBest regards,\nYour Law Firm", 'bkx-legal-professional' ),
			$user->display_name,
			$deadline->title,
			$deadline->matter_name,
			$deadline->due_date
		);

		wp_mail( $user->user_email, $subject, $message );
	}

	/**
	 * Send overdue notification.
	 *
	 * @param object $deadline Deadline object.
	 * @return void
	 */
	private function send_overdue_notification( object $deadline ): void {
		// Notify responsible attorney and admin.
		$responsible = (int) get_post_meta( $deadline->matter_id, '_bkx_responsible_attorney', true );
		$emails      = array( get_option( 'admin_email' ) );

		$user = get_user_by( 'id', $responsible );
		if ( $user ) {
			$emails[] = $user->user_email;
		}

		$subject = sprintf(
			/* translators: 1: deadline title 2: matter name */
			__( 'OVERDUE Deadline: %1$s - %2$s', 'bkx-legal-professional' ),
			$deadline->title,
			$deadline->matter_name
		);

		$message = sprintf(
			/* translators: 1: deadline title 2: matter name 3: due date */
			__( "ATTENTION: The following deadline is now OVERDUE:\n\nDeadline: %1\$s\nMatter: %2\$s\nDue Date: %3\$s\n\nPlease address this immediately.", 'bkx-legal-professional' ),
			$deadline->title,
			$deadline->matter_name,
			$deadline->due_date
		);

		wp_mail( array_unique( $emails ), $subject, $message );
	}

	/**
	 * Add note to matter.
	 *
	 * @param int    $matter_id Matter ID.
	 * @param string $content   Note content.
	 * @param string $type      Note type: general, call, meeting, research, other.
	 * @param bool   $is_private Whether note is private.
	 * @return int|WP_Error Note ID or error.
	 */
	public function add_note( int $matter_id, string $content, string $type = 'general', bool $is_private = false ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_legal_notes';

		$result = $wpdb->insert(
			$table,
			array(
				'matter_id'  => $matter_id,
				'content'    => wp_kses_post( $content ),
				'note_type'  => sanitize_text_field( $type ),
				'is_private' => $is_private ? 1 : 0,
				'created_by' => get_current_user_id(),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to add note', 'bkx-legal-professional' ) );
		}

		$this->log_matter_activity( $matter_id, 'note_added', __( 'Note added to matter', 'bkx-legal-professional' ) );

		return $wpdb->insert_id;
	}

	/**
	 * Get matter notes.
	 *
	 * @param int  $matter_id      Matter ID.
	 * @param bool $include_private Include private notes.
	 * @return array
	 */
	public function get_notes( int $matter_id, bool $include_private = true ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_legal_notes';

		$sql = $wpdb->prepare( "SELECT * FROM %i WHERE matter_id = %d", $table, $matter_id );

		if ( ! $include_private ) {
			$sql .= ' AND is_private = 0';
		}

		$sql .= ' ORDER BY created_at DESC';

		$results = $wpdb->get_results( $sql, ARRAY_A );

		// Add author names.
		foreach ( $results as &$note ) {
			$user = get_user_by( 'id', $note['created_by'] );
			$note['author_name'] = $user ? $user->display_name : __( 'Unknown', 'bkx-legal-professional' );
		}

		return $results ?: array();
	}

	/**
	 * Log matter activity.
	 *
	 * @param int    $matter_id Matter ID.
	 * @param string $action    Action type.
	 * @param string $details   Action details.
	 * @return void
	 */
	public function log_matter_activity( int $matter_id, string $action, string $details ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_legal_activity_log';

		$wpdb->insert(
			$table,
			array(
				'matter_id'  => $matter_id,
				'action'     => sanitize_text_field( $action ),
				'details'    => sanitize_text_field( $details ),
				'user_id'    => get_current_user_id(),
				'ip_address' => $this->get_client_ip(),
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Get matter activity log.
	 *
	 * @param int $matter_id Matter ID.
	 * @param int $limit     Number of entries.
	 * @return array
	 */
	public function get_activity_log( int $matter_id, int $limit = 50 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_legal_activity_log';

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE matter_id = %d ORDER BY created_at DESC LIMIT %d",
			$table,
			$matter_id,
			$limit
		), ARRAY_A );

		// Add user names.
		foreach ( $results as &$entry ) {
			$user = get_user_by( 'id', $entry['user_id'] );
			$entry['user_name'] = $user ? $user->display_name : __( 'System', 'bkx-legal-professional' );
		}

		return $results ?: array();
	}

	/**
	 * Link booking to matter.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function link_booking_to_matter( int $booking_id, array $booking_data ): void {
		if ( isset( $booking_data['matter_id'] ) && ! empty( $booking_data['matter_id'] ) ) {
			update_post_meta( $booking_id, '_bkx_matter_id', absint( $booking_data['matter_id'] ) );

			$this->log_matter_activity(
				absint( $booking_data['matter_id'] ),
				'booking_linked',
				sprintf(
					/* translators: %d: booking ID */
					__( 'Booking #%d linked to matter', 'bkx-legal-professional' ),
					$booking_id
				)
			);
		}
	}

	/**
	 * Save matter meta on post save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_matter_meta( int $post_id, $post ): void {
		// Verify this is a matter post type.
		if ( 'bkx_matter' !== $post->post_type ) {
			return;
		}

		// Check nonce.
		if ( ! isset( $_POST['bkx_matter_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_matter_nonce'] ) ), 'bkx_matter_meta' ) ) {
			return;
		}

		// Check user can edit.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Don't save autosaves.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Save meta fields.
		$meta_fields = array(
			'client_id'             => 'absint',
			'responsible_attorney'  => 'absint',
			'originating_attorney'  => 'absint',
			'billing_attorney'      => 'absint',
			'status'                => 'sanitize_text_field',
			'open_date'             => 'sanitize_text_field',
			'close_date'            => 'sanitize_text_field',
			'statute_of_limitations' => 'sanitize_text_field',
			'fee_arrangement'       => 'sanitize_text_field',
			'hourly_rate'           => 'floatval',
			'flat_fee'              => 'floatval',
			'contingency_percent'   => 'floatval',
			'estimated_value'       => 'floatval',
		);

		foreach ( $meta_fields as $field => $sanitize ) {
			$key = 'bkx_' . $field;
			if ( isset( $_POST[ $key ] ) ) {
				$value = call_user_func( $sanitize, wp_unslash( $_POST[ $key ] ) );
				update_post_meta( $post_id, '_bkx_' . $field, $value );
			}
		}
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Search matters.
	 *
	 * @param string $query      Search query.
	 * @param array  $filters    Optional filters.
	 * @return array
	 */
	public function search_matters( string $query, array $filters = array() ): array {
		$args = array(
			'post_type'      => 'bkx_matter',
			'posts_per_page' => 50,
			's'              => $query,
		);

		$meta_query = array();

		if ( ! empty( $filters['client_id'] ) ) {
			$meta_query[] = array(
				'key'   => '_bkx_client_id',
				'value' => absint( $filters['client_id'] ),
				'type'  => 'NUMERIC',
			);
		}

		if ( ! empty( $filters['attorney_id'] ) ) {
			$meta_query[] = array(
				'key'   => '_bkx_responsible_attorney',
				'value' => absint( $filters['attorney_id'] ),
				'type'  => 'NUMERIC',
			);
		}

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		if ( ! empty( $filters['status'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'bkx_matter_status',
					'field'    => 'slug',
					'terms'    => $filters['status'],
				),
			);
		}

		if ( ! empty( $filters['practice_area'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'bkx_practice_area',
				'field'    => 'slug',
				'terms'    => $filters['practice_area'],
			);
		}

		$matters = get_posts( $args );
		$result  = array();

		foreach ( $matters as $matter ) {
			$result[] = $this->format_matter( $matter );
		}

		return $result;
	}
}
