<?php
/**
 * Billing and Time Tracking Service.
 *
 * Handles time entries, invoices, trust accounting, and payment tracking.
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
 * Billing Service class.
 *
 * @since 1.0.0
 */
class BillingService {

	/**
	 * Service instance.
	 *
	 * @var BillingService|null
	 */
	private static ?BillingService $instance = null;

	/**
	 * Billing increment in minutes (default 6 = 0.1 hour).
	 *
	 * @var int
	 */
	private int $billing_increment = 6;

	/**
	 * Get service instance.
	 *
	 * @return BillingService
	 */
	public static function get_instance(): BillingService {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$settings = get_option( 'bkx_legal_settings', array() );
		$this->billing_increment = (int) ( $settings['time_tracking_increment'] ?? 6 );
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'bkx_booking_completed', array( $this, 'auto_create_time_entry' ), 10, 2 );
	}

	/**
	 * Create time entry.
	 *
	 * @param array $data Time entry data.
	 * @return int|WP_Error Time entry ID or error.
	 */
	public function create_time_entry( array $data ) {
		$defaults = array(
			'matter_id'     => 0,
			'user_id'       => get_current_user_id(),
			'date'          => current_time( 'Y-m-d' ),
			'hours'         => 0,
			'minutes'       => 0,
			'description'   => '',
			'activity_code' => '',
			'billable'      => true,
			'billed'        => false,
			'rate'          => 0,
			'booking_id'    => 0,
		);

		$data = wp_parse_args( $data, $defaults );

		// Validate matter.
		$matter = get_post( $data['matter_id'] );
		if ( ! $matter || 'bkx_matter' !== $matter->post_type ) {
			return new \WP_Error( 'invalid_matter', __( 'Invalid matter ID', 'bkx-legal-professional' ) );
		}

		// Calculate total minutes.
		$total_minutes = ( (int) $data['hours'] * 60 ) + (int) $data['minutes'];

		// Round to billing increment.
		$total_minutes = $this->round_to_increment( $total_minutes );

		// Get rate if not provided.
		if ( empty( $data['rate'] ) ) {
			$data['rate'] = $this->get_billing_rate( $data['matter_id'], $data['user_id'] );
		}

		// Calculate amount.
		$hours  = $total_minutes / 60;
		$amount = $hours * (float) $data['rate'];

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_time_entries';

		$result = $wpdb->insert(
			$table,
			array(
				'matter_id'     => absint( $data['matter_id'] ),
				'user_id'       => absint( $data['user_id'] ),
				'entry_date'    => sanitize_text_field( $data['date'] ),
				'minutes'       => $total_minutes,
				'description'   => sanitize_textarea_field( $data['description'] ),
				'activity_code' => sanitize_text_field( $data['activity_code'] ),
				'billable'      => $data['billable'] ? 1 : 0,
				'billed'        => $data['billed'] ? 1 : 0,
				'rate'          => (float) $data['rate'],
				'amount'        => $amount,
				'booking_id'    => absint( $data['booking_id'] ),
				'created_by'    => get_current_user_id(),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%d', '%f', '%f', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create time entry', 'bkx-legal-professional' ) );
		}

		$entry_id = $wpdb->insert_id;

		// Log activity.
		CaseManagementService::get_instance()->log_matter_activity(
			$data['matter_id'],
			'time_entry_created',
			sprintf(
				/* translators: 1: hours 2: description */
				__( 'Time entry: %.2f hours - %s', 'bkx-legal-professional' ),
				$hours,
				wp_trim_words( $data['description'], 10 )
			)
		);

		/**
		 * Fires after a time entry is created.
		 *
		 * @param int   $entry_id The time entry ID.
		 * @param array $data     The time entry data.
		 */
		do_action( 'bkx_legal_time_entry_created', $entry_id, $data );

		return $entry_id;
	}

	/**
	 * Update time entry.
	 *
	 * @param int   $entry_id Time entry ID.
	 * @param array $data     Updated data.
	 * @return bool|WP_Error
	 */
	public function update_time_entry( int $entry_id, array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_time_entries';

		$entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table, $entry_id ) );

		if ( ! $entry ) {
			return new \WP_Error( 'not_found', __( 'Time entry not found', 'bkx-legal-professional' ) );
		}

		// Cannot edit billed entries.
		if ( $entry->billed && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'already_billed', __( 'Cannot edit billed time entry', 'bkx-legal-professional' ) );
		}

		$update_data = array();
		$format      = array();

		if ( isset( $data['hours'] ) || isset( $data['minutes'] ) ) {
			$hours   = isset( $data['hours'] ) ? (int) $data['hours'] : floor( $entry->minutes / 60 );
			$minutes = isset( $data['minutes'] ) ? (int) $data['minutes'] : ( $entry->minutes % 60 );
			$total   = $this->round_to_increment( ( $hours * 60 ) + $minutes );

			$update_data['minutes'] = $total;
			$format[]               = '%d';

			// Recalculate amount.
			$rate = isset( $data['rate'] ) ? (float) $data['rate'] : (float) $entry->rate;
			$update_data['amount'] = ( $total / 60 ) * $rate;
			$format[]              = '%f';
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
			$format[]                   = '%s';
		}

		if ( isset( $data['activity_code'] ) ) {
			$update_data['activity_code'] = sanitize_text_field( $data['activity_code'] );
			$format[]                     = '%s';
		}

		if ( isset( $data['billable'] ) ) {
			$update_data['billable'] = $data['billable'] ? 1 : 0;
			$format[]                = '%d';
		}

		if ( isset( $data['rate'] ) ) {
			$update_data['rate'] = (float) $data['rate'];
			$format[]            = '%f';

			// Recalculate amount.
			$minutes = isset( $update_data['minutes'] ) ? $update_data['minutes'] : $entry->minutes;
			$update_data['amount'] = ( $minutes / 60 ) * $data['rate'];
			$format[]              = '%f';
		}

		if ( isset( $data['date'] ) ) {
			$update_data['entry_date'] = sanitize_text_field( $data['date'] );
			$format[]                  = '%s';
		}

		$update_data['updated_at'] = current_time( 'mysql' );
		$format[]                  = '%s';

		$result = $wpdb->update( $table, $update_data, array( 'id' => $entry_id ), $format, array( '%d' ) );

		return false !== $result;
	}

	/**
	 * Delete time entry.
	 *
	 * @param int $entry_id Time entry ID.
	 * @return bool|WP_Error
	 */
	public function delete_time_entry( int $entry_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_time_entries';

		$entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table, $entry_id ) );

		if ( ! $entry ) {
			return new \WP_Error( 'not_found', __( 'Time entry not found', 'bkx-legal-professional' ) );
		}

		if ( $entry->billed && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'already_billed', __( 'Cannot delete billed time entry', 'bkx-legal-professional' ) );
		}

		// Soft delete.
		$result = $wpdb->update(
			$table,
			array(
				'deleted_at' => current_time( 'mysql' ),
				'deleted_by' => get_current_user_id(),
			),
			array( 'id' => $entry_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get time entry by ID.
	 *
	 * @param int $entry_id Time entry ID.
	 * @return array|null
	 */
	public function get_time_entry( int $entry_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_time_entries';

		$entry = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d AND deleted_at IS NULL",
			$table,
			$entry_id
		), ARRAY_A );

		if ( ! $entry ) {
			return null;
		}

		// Add user name.
		$user = get_user_by( 'id', $entry['user_id'] );
		$entry['user_name'] = $user ? $user->display_name : __( 'Unknown', 'bkx-legal-professional' );

		// Format hours.
		$entry['hours_display'] = $this->format_hours( $entry['minutes'] );

		return $entry;
	}

	/**
	 * Get time entries for matter.
	 *
	 * @param int    $matter_id   Matter ID.
	 * @param string $start_date  Filter start date.
	 * @param string $end_date    Filter end date.
	 * @param bool   $billable_only Only billable entries.
	 * @param bool   $unbilled_only Only unbilled entries.
	 * @return array
	 */
	public function get_matter_time_entries(
		int $matter_id,
		string $start_date = '',
		string $end_date = '',
		bool $billable_only = false,
		bool $unbilled_only = false
	): array {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_time_entries';

		$sql = $wpdb->prepare(
			"SELECT * FROM %i WHERE matter_id = %d AND deleted_at IS NULL",
			$table,
			$matter_id
		);

		if ( ! empty( $start_date ) ) {
			$sql .= $wpdb->prepare( " AND entry_date >= %s", $start_date );
		}

		if ( ! empty( $end_date ) ) {
			$sql .= $wpdb->prepare( " AND entry_date <= %s", $end_date );
		}

		if ( $billable_only ) {
			$sql .= ' AND billable = 1';
		}

		if ( $unbilled_only ) {
			$sql .= ' AND billed = 0';
		}

		$sql .= ' ORDER BY entry_date DESC, created_at DESC';

		$entries = $wpdb->get_results( $sql, ARRAY_A );

		// Add user names and format hours.
		foreach ( $entries as &$entry ) {
			$user = get_user_by( 'id', $entry['user_id'] );
			$entry['user_name']     = $user ? $user->display_name : __( 'Unknown', 'bkx-legal-professional' );
			$entry['hours_display'] = $this->format_hours( $entry['minutes'] );
		}

		return $entries ?: array();
	}

	/**
	 * Get unbilled time summary for matter.
	 *
	 * @param int $matter_id Matter ID.
	 * @return array
	 */
	public function get_unbilled_summary( int $matter_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_time_entries';

		$result = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				SUM(minutes) as total_minutes,
				SUM(amount) as total_amount,
				COUNT(*) as entry_count
			FROM %i
			WHERE matter_id = %d
			AND billable = 1
			AND billed = 0
			AND deleted_at IS NULL",
			$table,
			$matter_id
		), ARRAY_A );

		return array(
			'total_minutes' => (int) ( $result['total_minutes'] ?? 0 ),
			'total_hours'   => $this->format_hours( (int) ( $result['total_minutes'] ?? 0 ) ),
			'total_amount'  => (float) ( $result['total_amount'] ?? 0 ),
			'entry_count'   => (int) ( $result['entry_count'] ?? 0 ),
		);
	}

	/**
	 * Get user time entries.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	public function get_user_time_entries( int $user_id, string $start_date = '', string $end_date = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_time_entries';

		$sql = $wpdb->prepare(
			"SELECT te.*, m.post_title as matter_name
			FROM %i te
			LEFT JOIN {$wpdb->posts} m ON te.matter_id = m.ID
			WHERE te.user_id = %d AND te.deleted_at IS NULL",
			$table,
			$user_id
		);

		if ( ! empty( $start_date ) ) {
			$sql .= $wpdb->prepare( " AND te.entry_date >= %s", $start_date );
		}

		if ( ! empty( $end_date ) ) {
			$sql .= $wpdb->prepare( " AND te.entry_date <= %s", $end_date );
		}

		$sql .= ' ORDER BY te.entry_date DESC, te.created_at DESC';

		$entries = $wpdb->get_results( $sql, ARRAY_A );

		foreach ( $entries as &$entry ) {
			$entry['hours_display'] = $this->format_hours( $entry['minutes'] );
		}

		return $entries ?: array();
	}

	/**
	 * Create invoice.
	 *
	 * @param int   $matter_id Matter ID.
	 * @param array $options   Invoice options.
	 * @return int|WP_Error Invoice ID or error.
	 */
	public function create_invoice( int $matter_id, array $options = array() ) {
		$defaults = array(
			'start_date'      => '',
			'end_date'        => current_time( 'Y-m-d' ),
			'include_expenses' => true,
			'notes'           => '',
			'due_days'        => 30,
		);

		$options = wp_parse_args( $options, $defaults );

		$matter = get_post( $matter_id );
		if ( ! $matter || 'bkx_matter' !== $matter->post_type ) {
			return new \WP_Error( 'invalid_matter', __( 'Invalid matter ID', 'bkx-legal-professional' ) );
		}

		global $wpdb;
		$time_table    = $wpdb->prefix . 'bkx_legal_time_entries';
		$expense_table = $wpdb->prefix . 'bkx_legal_expenses';
		$invoice_table = $wpdb->prefix . 'bkx_legal_invoices';

		// Get unbilled time entries.
		$time_sql = $wpdb->prepare(
			"SELECT * FROM %i WHERE matter_id = %d AND billable = 1 AND billed = 0 AND deleted_at IS NULL",
			$time_table,
			$matter_id
		);

		if ( ! empty( $options['start_date'] ) ) {
			$time_sql .= $wpdb->prepare( " AND entry_date >= %s", $options['start_date'] );
		}

		if ( ! empty( $options['end_date'] ) ) {
			$time_sql .= $wpdb->prepare( " AND entry_date <= %s", $options['end_date'] );
		}

		$time_entries = $wpdb->get_results( $time_sql, ARRAY_A );

		if ( empty( $time_entries ) ) {
			return new \WP_Error( 'no_entries', __( 'No unbilled time entries found', 'bkx-legal-professional' ) );
		}

		// Calculate time total.
		$time_total   = 0;
		$time_minutes = 0;
		foreach ( $time_entries as $entry ) {
			$time_total   += (float) $entry['amount'];
			$time_minutes += (int) $entry['minutes'];
		}

		// Get unbilled expenses.
		$expense_total = 0;
		$expenses      = array();

		if ( $options['include_expenses'] ) {
			$expenses = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM %i WHERE matter_id = %d AND billed = 0 AND deleted_at IS NULL",
				$expense_table,
				$matter_id
			), ARRAY_A );

			foreach ( $expenses as $expense ) {
				$expense_total += (float) $expense['amount'];
			}
		}

		// Generate invoice number.
		$invoice_number = $this->generate_invoice_number();

		// Calculate due date.
		$due_date = gmdate( 'Y-m-d', strtotime( '+' . $options['due_days'] . ' days' ) );

		// Get client ID.
		$client_id = (int) get_post_meta( $matter_id, '_bkx_client_id', true );

		// Create invoice.
		$result = $wpdb->insert(
			$invoice_table,
			array(
				'invoice_number'  => $invoice_number,
				'matter_id'       => $matter_id,
				'client_id'       => $client_id,
				'invoice_date'    => current_time( 'Y-m-d' ),
				'due_date'        => $due_date,
				'time_total'      => $time_total,
				'expense_total'   => $expense_total,
				'subtotal'        => $time_total + $expense_total,
				'tax_rate'        => 0,
				'tax_amount'      => 0,
				'total'           => $time_total + $expense_total,
				'balance'         => $time_total + $expense_total,
				'status'          => 'draft',
				'notes'           => sanitize_textarea_field( $options['notes'] ),
				'created_by'      => get_current_user_id(),
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create invoice', 'bkx-legal-professional' ) );
		}

		$invoice_id = $wpdb->insert_id;

		// Store line items.
		$this->save_invoice_line_items( $invoice_id, $time_entries, $expenses );

		// Mark time entries as billed.
		$entry_ids = wp_list_pluck( $time_entries, 'id' );
		if ( ! empty( $entry_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$time_table} SET billed = 1, invoice_id = %d WHERE id IN ($placeholders)",
				array_merge( array( $invoice_id ), $entry_ids )
			) );
		}

		// Mark expenses as billed.
		if ( ! empty( $expenses ) ) {
			$expense_ids  = wp_list_pluck( $expenses, 'id' );
			$placeholders = implode( ',', array_fill( 0, count( $expense_ids ), '%d' ) );
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$expense_table} SET billed = 1, invoice_id = %d WHERE id IN ($placeholders)",
				array_merge( array( $invoice_id ), $expense_ids )
			) );
		}

		// Log activity.
		CaseManagementService::get_instance()->log_matter_activity(
			$matter_id,
			'invoice_created',
			sprintf(
				/* translators: 1: invoice number 2: amount */
				__( 'Invoice %1$s created for %2$s', 'bkx-legal-professional' ),
				$invoice_number,
				$this->format_currency( $time_total + $expense_total )
			)
		);

		/**
		 * Fires after an invoice is created.
		 *
		 * @param int $invoice_id The invoice ID.
		 * @param int $matter_id  The matter ID.
		 */
		do_action( 'bkx_legal_invoice_created', $invoice_id, $matter_id );

		return $invoice_id;
	}

	/**
	 * Save invoice line items.
	 *
	 * @param int   $invoice_id   Invoice ID.
	 * @param array $time_entries Time entries.
	 * @param array $expenses     Expenses.
	 * @return void
	 */
	private function save_invoice_line_items( int $invoice_id, array $time_entries, array $expenses ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_invoice_items';

		// Add time entries.
		foreach ( $time_entries as $entry ) {
			$user = get_user_by( 'id', $entry['user_id'] );

			$wpdb->insert(
				$table,
				array(
					'invoice_id'   => $invoice_id,
					'item_type'    => 'time',
					'reference_id' => $entry['id'],
					'date'         => $entry['entry_date'],
					'description'  => sprintf(
						'%s - %s (%s)',
						$user ? $user->display_name : __( 'Unknown', 'bkx-legal-professional' ),
						$entry['description'],
						$this->format_hours( $entry['minutes'] )
					),
					'quantity'     => $entry['minutes'] / 60,
					'rate'         => $entry['rate'],
					'amount'       => $entry['amount'],
				),
				array( '%d', '%s', '%d', '%s', '%s', '%f', '%f', '%f' )
			);
		}

		// Add expenses.
		foreach ( $expenses as $expense ) {
			$wpdb->insert(
				$table,
				array(
					'invoice_id'   => $invoice_id,
					'item_type'    => 'expense',
					'reference_id' => $expense['id'],
					'date'         => $expense['expense_date'],
					'description'  => $expense['description'],
					'quantity'     => 1,
					'rate'         => $expense['amount'],
					'amount'       => $expense['amount'],
				),
				array( '%d', '%s', '%d', '%s', '%s', '%f', '%f', '%f' )
			);
		}
	}

	/**
	 * Send invoice to client.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return bool|WP_Error
	 */
	public function send_invoice( int $invoice_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_invoices';

		$invoice = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table, $invoice_id ) );

		if ( ! $invoice ) {
			return new \WP_Error( 'not_found', __( 'Invoice not found', 'bkx-legal-professional' ) );
		}

		$client = get_user_by( 'id', $invoice->client_id );
		if ( ! $client ) {
			return new \WP_Error( 'no_client', __( 'Client not found', 'bkx-legal-professional' ) );
		}

		$matter = get_post( $invoice->matter_id );

		// Generate view token.
		$token = wp_generate_password( 32, false );
		$view_url = add_query_arg( array(
			'action'  => 'bkx_view_invoice',
			'id'      => $invoice_id,
			'token'   => $token,
		), home_url() );

		// Update invoice status.
		$wpdb->update(
			$table,
			array(
				'status'     => 'sent',
				'sent_at'    => current_time( 'mysql' ),
				'view_token' => $token,
			),
			array( 'id' => $invoice_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Send email.
		$subject = sprintf(
			/* translators: 1: invoice number 2: firm name */
			__( 'Invoice %1$s from %2$s', 'bkx-legal-professional' ),
			$invoice->invoice_number,
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: client name 2: invoice number 3: amount 4: due date 5: view URL 6: firm name */
			__(
				"Dear %1\$s,\n\n" .
				"Please find attached Invoice #%2\$s.\n\n" .
				"Amount Due: %3\$s\n" .
				"Due Date: %4\$s\n\n" .
				"View Invoice: %5\$s\n\n" .
				"Thank you for your business.\n\n" .
				"Best regards,\n%6\$s",
				'bkx-legal-professional'
			),
			$client->display_name,
			$invoice->invoice_number,
			$this->format_currency( $invoice->total ),
			$invoice->due_date,
			$view_url,
			get_bloginfo( 'name' )
		);

		$sent = wp_mail( $client->user_email, $subject, $message );

		if ( ! $sent ) {
			return new \WP_Error( 'email_failed', __( 'Failed to send invoice', 'bkx-legal-professional' ) );
		}

		// Log activity.
		CaseManagementService::get_instance()->log_matter_activity(
			$invoice->matter_id,
			'invoice_sent',
			sprintf(
				/* translators: %s: invoice number */
				__( 'Invoice %s sent to client', 'bkx-legal-professional' ),
				$invoice->invoice_number
			)
		);

		return true;
	}

	/**
	 * Record payment.
	 *
	 * @param int   $invoice_id Invoice ID.
	 * @param float $amount     Payment amount.
	 * @param array $data       Payment data.
	 * @return int|WP_Error Payment ID or error.
	 */
	public function record_payment( int $invoice_id, float $amount, array $data = array() ) {
		global $wpdb;
		$invoice_table = $wpdb->prefix . 'bkx_legal_invoices';
		$payment_table = $wpdb->prefix . 'bkx_legal_payments';

		$invoice = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $invoice_table, $invoice_id ) );

		if ( ! $invoice ) {
			return new \WP_Error( 'not_found', __( 'Invoice not found', 'bkx-legal-professional' ) );
		}

		$defaults = array(
			'payment_date'   => current_time( 'Y-m-d' ),
			'payment_method' => 'check',
			'reference'      => '',
			'notes'          => '',
		);

		$data = wp_parse_args( $data, $defaults );

		// Create payment record.
		$result = $wpdb->insert(
			$payment_table,
			array(
				'invoice_id'     => $invoice_id,
				'matter_id'      => $invoice->matter_id,
				'client_id'      => $invoice->client_id,
				'amount'         => $amount,
				'payment_date'   => sanitize_text_field( $data['payment_date'] ),
				'payment_method' => sanitize_text_field( $data['payment_method'] ),
				'reference'      => sanitize_text_field( $data['reference'] ),
				'notes'          => sanitize_textarea_field( $data['notes'] ),
				'created_by'     => get_current_user_id(),
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to record payment', 'bkx-legal-professional' ) );
		}

		$payment_id = $wpdb->insert_id;

		// Update invoice balance.
		$new_balance = max( 0, (float) $invoice->balance - $amount );
		$new_status  = $new_balance <= 0 ? 'paid' : ( $new_balance < (float) $invoice->total ? 'partial' : 'sent' );

		$wpdb->update(
			$invoice_table,
			array(
				'balance'  => $new_balance,
				'status'   => $new_status,
				'paid_at'  => $new_balance <= 0 ? current_time( 'mysql' ) : null,
			),
			array( 'id' => $invoice_id ),
			array( '%f', '%s', '%s' ),
			array( '%d' )
		);

		// Log activity.
		CaseManagementService::get_instance()->log_matter_activity(
			$invoice->matter_id,
			'payment_received',
			sprintf(
				/* translators: 1: amount 2: invoice number */
				__( 'Payment of %1$s received for Invoice %2$s', 'bkx-legal-professional' ),
				$this->format_currency( $amount ),
				$invoice->invoice_number
			)
		);

		/**
		 * Fires after a payment is recorded.
		 *
		 * @param int   $payment_id The payment ID.
		 * @param int   $invoice_id The invoice ID.
		 * @param float $amount     The payment amount.
		 */
		do_action( 'bkx_legal_payment_recorded', $payment_id, $invoice_id, $amount );

		return $payment_id;
	}

	/**
	 * Create expense.
	 *
	 * @param array $data Expense data.
	 * @return int|WP_Error Expense ID or error.
	 */
	public function create_expense( array $data ) {
		$defaults = array(
			'matter_id'    => 0,
			'date'         => current_time( 'Y-m-d' ),
			'description'  => '',
			'category'     => 'other',
			'amount'       => 0,
			'billable'     => true,
			'vendor'       => '',
			'receipt_url'  => '',
		);

		$data = wp_parse_args( $data, $defaults );

		$matter = get_post( $data['matter_id'] );
		if ( ! $matter || 'bkx_matter' !== $matter->post_type ) {
			return new \WP_Error( 'invalid_matter', __( 'Invalid matter ID', 'bkx-legal-professional' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_expenses';

		$result = $wpdb->insert(
			$table,
			array(
				'matter_id'    => absint( $data['matter_id'] ),
				'expense_date' => sanitize_text_field( $data['date'] ),
				'description'  => sanitize_textarea_field( $data['description'] ),
				'category'     => sanitize_text_field( $data['category'] ),
				'amount'       => (float) $data['amount'],
				'billable'     => $data['billable'] ? 1 : 0,
				'vendor'       => sanitize_text_field( $data['vendor'] ),
				'receipt_url'  => esc_url_raw( $data['receipt_url'] ),
				'created_by'   => get_current_user_id(),
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create expense', 'bkx-legal-professional' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get invoice by ID.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return array|null
	 */
	public function get_invoice( int $invoice_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_invoices';

		$invoice = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d",
			$table,
			$invoice_id
		), ARRAY_A );

		if ( ! $invoice ) {
			return null;
		}

		// Add matter name.
		$matter = get_post( $invoice['matter_id'] );
		$invoice['matter_name'] = $matter ? $matter->post_title : '';

		// Add client name.
		$client = get_user_by( 'id', $invoice['client_id'] );
		$invoice['client_name'] = $client ? $client->display_name : '';

		// Add line items.
		$invoice['line_items'] = $this->get_invoice_line_items( $invoice_id );

		// Add payments.
		$invoice['payments'] = $this->get_invoice_payments( $invoice_id );

		return $invoice;
	}

	/**
	 * Get invoice line items.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return array
	 */
	private function get_invoice_line_items( int $invoice_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_invoice_items';

		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE invoice_id = %d ORDER BY date ASC",
			$table,
			$invoice_id
		), ARRAY_A );

		return $items ?: array();
	}

	/**
	 * Get invoice payments.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return array
	 */
	private function get_invoice_payments( int $invoice_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_payments';

		$payments = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE invoice_id = %d ORDER BY payment_date DESC",
			$table,
			$invoice_id
		), ARRAY_A );

		return $payments ?: array();
	}

	/**
	 * Get billing rate for user on matter.
	 *
	 * @param int $matter_id Matter ID.
	 * @param int $user_id   User ID.
	 * @return float
	 */
	private function get_billing_rate( int $matter_id, int $user_id ): float {
		// Check matter-specific rate.
		$matter_rate = get_post_meta( $matter_id, '_bkx_hourly_rate', true );
		if ( ! empty( $matter_rate ) ) {
			return (float) $matter_rate;
		}

		// Check user rate from attorney profile.
		$attorneys = get_posts( array(
			'post_type'   => 'bkx_attorney',
			'meta_query'  => array(
				array(
					'key'   => '_bkx_user_id',
					'value' => $user_id,
					'type'  => 'NUMERIC',
				),
			),
			'numberposts' => 1,
		) );

		if ( ! empty( $attorneys ) ) {
			$rate = get_post_meta( $attorneys[0]->ID, '_bkx_hourly_rate', true );
			if ( ! empty( $rate ) ) {
				return (float) $rate;
			}
		}

		// Fall back to default rate.
		$settings = get_option( 'bkx_legal_settings', array() );
		return (float) ( $settings['default_hourly_rate'] ?? 250 );
	}

	/**
	 * Round minutes to billing increment.
	 *
	 * @param int $minutes Minutes to round.
	 * @return int
	 */
	private function round_to_increment( int $minutes ): int {
		if ( $minutes <= 0 ) {
			return 0;
		}

		$increment = $this->billing_increment;

		// Round up to next increment.
		return (int) ceil( $minutes / $increment ) * $increment;
	}

	/**
	 * Format minutes as hours.
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	private function format_hours( int $minutes ): string {
		$hours = floor( $minutes / 60 );
		$mins  = $minutes % 60;

		if ( 0 === $hours ) {
			/* translators: %d: minutes */
			return sprintf( __( '%dm', 'bkx-legal-professional' ), $mins );
		}

		if ( 0 === $mins ) {
			/* translators: %d: hours */
			return sprintf( __( '%dh', 'bkx-legal-professional' ), $hours );
		}

		/* translators: 1: hours 2: minutes */
		return sprintf( __( '%1$dh %2$dm', 'bkx-legal-professional' ), $hours, $mins );
	}

	/**
	 * Format currency.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	private function format_currency( float $amount ): string {
		return '$' . number_format( $amount, 2 );
	}

	/**
	 * Generate invoice number.
	 *
	 * @return string
	 */
	private function generate_invoice_number(): string {
		$settings = get_option( 'bkx_legal_settings', array() );
		$prefix   = $settings['invoice_prefix'] ?? 'INV';
		$year     = gmdate( 'Y' );
		$month    = gmdate( 'm' );

		$sequence_key = 'bkx_legal_invoice_sequence_' . $year . $month;
		$sequence     = (int) get_option( $sequence_key, 0 ) + 1;
		update_option( $sequence_key, $sequence );

		return sprintf( '%s-%s%s-%04d', $prefix, $year, $month, $sequence );
	}

	/**
	 * Auto-create time entry from booking.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function auto_create_time_entry( int $booking_id, array $booking_data ): void {
		$settings = get_option( 'bkx_legal_settings', array() );

		if ( empty( $settings['auto_time_entry'] ) ) {
			return;
		}

		$matter_id = get_post_meta( $booking_id, '_bkx_matter_id', true );

		if ( empty( $matter_id ) ) {
			return;
		}

		// Get booking duration.
		$duration = get_post_meta( $booking_id, 'total_time_taken', true );

		if ( empty( $duration ) ) {
			return;
		}

		// Get service name for description.
		$service_id   = get_post_meta( $booking_id, 'base_id', true );
		$service      = get_post( $service_id );
		$service_name = $service ? $service->post_title : __( 'Appointment', 'bkx-legal-professional' );

		$this->create_time_entry( array(
			'matter_id'   => (int) $matter_id,
			'minutes'     => (int) $duration,
			'description' => sprintf(
				/* translators: 1: service name 2: booking ID */
				__( '%1$s (Booking #%2$d)', 'bkx-legal-professional' ),
				$service_name,
				$booking_id
			),
			'billable'    => true,
			'booking_id'  => $booking_id,
		) );
	}

	/**
	 * Get activity codes.
	 *
	 * @return array
	 */
	public function get_activity_codes(): array {
		return array(
			'A101' => __( 'Initial Case Assessment', 'bkx-legal-professional' ),
			'A102' => __( 'Case Strategy/Development', 'bkx-legal-professional' ),
			'A103' => __( 'Factual Investigation', 'bkx-legal-professional' ),
			'A104' => __( 'Document Review', 'bkx-legal-professional' ),
			'A105' => __( 'Draft/Revise Documents', 'bkx-legal-professional' ),
			'A106' => __( 'Legal Research', 'bkx-legal-professional' ),
			'A107' => __( 'Client Correspondence', 'bkx-legal-professional' ),
			'A108' => __( 'Client Meeting/Conference', 'bkx-legal-professional' ),
			'A109' => __( 'Opposing Counsel Communication', 'bkx-legal-professional' ),
			'A110' => __( 'Court Appearance', 'bkx-legal-professional' ),
			'A111' => __( 'Deposition', 'bkx-legal-professional' ),
			'A112' => __( 'Mediation/Arbitration', 'bkx-legal-professional' ),
			'A113' => __( 'Settlement Negotiation', 'bkx-legal-professional' ),
			'A114' => __( 'Trial Preparation', 'bkx-legal-professional' ),
			'A115' => __( 'Trial', 'bkx-legal-professional' ),
			'A199' => __( 'Other', 'bkx-legal-professional' ),
		);
	}

	/**
	 * Get expense categories.
	 *
	 * @return array
	 */
	public function get_expense_categories(): array {
		return array(
			'filing'       => __( 'Court Filing Fees', 'bkx-legal-professional' ),
			'service'      => __( 'Service of Process', 'bkx-legal-professional' ),
			'copies'       => __( 'Copies/Printing', 'bkx-legal-professional' ),
			'postage'      => __( 'Postage/Shipping', 'bkx-legal-professional' ),
			'travel'       => __( 'Travel', 'bkx-legal-professional' ),
			'meals'        => __( 'Meals', 'bkx-legal-professional' ),
			'expert'       => __( 'Expert Witness Fees', 'bkx-legal-professional' ),
			'transcript'   => __( 'Transcripts', 'bkx-legal-professional' ),
			'records'      => __( 'Medical/Police Records', 'bkx-legal-professional' ),
			'investigation' => __( 'Investigation', 'bkx-legal-professional' ),
			'other'        => __( 'Other', 'bkx-legal-professional' ),
		);
	}
}
