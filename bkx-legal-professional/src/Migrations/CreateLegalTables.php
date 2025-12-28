<?php
/**
 * Database migration for Legal & Professional Services.
 *
 * @package BookingX\LegalProfessional
 * @since   1.0.0
 */

namespace BookingX\LegalProfessional\Migrations;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create Legal Tables migration class.
 *
 * @since 1.0.0
 */
class CreateLegalTables {

	/**
	 * Run the migration.
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Client Intakes table.
		$table_intakes = $wpdb->prefix . 'bkx_legal_intakes';
		$sql_intakes   = "CREATE TABLE {$table_intakes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			template_id bigint(20) unsigned DEFAULT NULL,
			intake_data longtext NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			submitted_at datetime DEFAULT NULL,
			reviewed_by bigint(20) unsigned DEFAULT NULL,
			reviewed_at datetime DEFAULT NULL,
			notes text DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY template_id (template_id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql_intakes );

		// Parties table.
		$table_parties = $wpdb->prefix . 'bkx_legal_parties';
		$sql_parties   = "CREATE TABLE {$table_parties} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			matter_id bigint(20) unsigned NOT NULL,
			party_name varchar(255) NOT NULL,
			party_type varchar(50) NOT NULL DEFAULT 'individual',
			relationship varchar(50) NOT NULL DEFAULT 'related',
			email varchar(255) DEFAULT NULL,
			phone varchar(50) DEFAULT NULL,
			address text DEFAULT NULL,
			notes text DEFAULT NULL,
			created_by bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			deleted_at datetime DEFAULT NULL,
			deleted_by bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY (id),
			KEY matter_id (matter_id),
			KEY party_name (party_name),
			KEY relationship (relationship)
		) {$charset_collate};";
		dbDelta( $sql_parties );

		// Conflict Checks table.
		$table_conflicts = $wpdb->prefix . 'bkx_legal_conflict_checks';
		$sql_conflicts   = "CREATE TABLE {$table_conflicts} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			matter_id bigint(20) unsigned NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'clear',
			total_flags int(11) NOT NULL DEFAULT 0,
			check_data longtext DEFAULT NULL,
			waiver_data longtext DEFAULT NULL,
			checked_by bigint(20) unsigned NOT NULL,
			checked_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY matter_id (matter_id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql_conflicts );

		// Documents table.
		$table_documents = $wpdb->prefix . 'bkx_legal_documents';
		$sql_documents   = "CREATE TABLE {$table_documents} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			matter_id bigint(20) unsigned NOT NULL,
			filename varchar(255) NOT NULL,
			stored_name varchar(255) NOT NULL,
			file_type varchar(20) NOT NULL,
			file_size bigint(20) unsigned NOT NULL,
			mime_type varchar(100) NOT NULL,
			category varchar(50) NOT NULL DEFAULT 'general',
			description text DEFAULT NULL,
			version int(11) NOT NULL DEFAULT 1,
			parent_id bigint(20) unsigned DEFAULT NULL,
			version_notes text DEFAULT NULL,
			file_hash varchar(64) DEFAULT NULL,
			uploaded_by bigint(20) unsigned NOT NULL,
			uploaded_at datetime NOT NULL,
			deleted_at datetime DEFAULT NULL,
			deleted_by bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY (id),
			KEY matter_id (matter_id),
			KEY category (category),
			KEY filename (filename),
			KEY parent_id (parent_id)
		) {$charset_collate};";
		dbDelta( $sql_documents );

		// Document Access Log table.
		$table_doc_access = $wpdb->prefix . 'bkx_legal_document_access_log';
		$sql_doc_access   = "CREATE TABLE {$table_doc_access} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			document_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			action varchar(50) NOT NULL,
			ip_address varchar(45) DEFAULT NULL,
			accessed_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY document_id (document_id),
			KEY user_id (user_id)
		) {$charset_collate};";
		dbDelta( $sql_doc_access );

		// Retainers table.
		$table_retainers = $wpdb->prefix . 'bkx_legal_retainers';
		$sql_retainers   = "CREATE TABLE {$table_retainers} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			matter_id bigint(20) unsigned NOT NULL,
			template_id bigint(20) unsigned DEFAULT NULL,
			client_id bigint(20) unsigned NOT NULL,
			content longtext NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			signature_token varchar(64) DEFAULT NULL,
			signature_data longtext DEFAULT NULL,
			sent_at datetime DEFAULT NULL,
			signed_at datetime DEFAULT NULL,
			signed_ip varchar(45) DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY matter_id (matter_id),
			KEY client_id (client_id),
			KEY status (status)
		) {$charset_collate};";
		dbDelta( $sql_retainers );

		// Time Entries table.
		$table_time = $wpdb->prefix . 'bkx_legal_time_entries';
		$sql_time   = "CREATE TABLE {$table_time} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			matter_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			entry_date date NOT NULL,
			minutes int(11) NOT NULL,
			description text NOT NULL,
			activity_code varchar(20) DEFAULT NULL,
			billable tinyint(1) NOT NULL DEFAULT 1,
			billed tinyint(1) NOT NULL DEFAULT 0,
			rate decimal(10,2) NOT NULL DEFAULT 0.00,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			invoice_id bigint(20) unsigned DEFAULT NULL,
			booking_id bigint(20) unsigned DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			deleted_at datetime DEFAULT NULL,
			deleted_by bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY (id),
			KEY matter_id (matter_id),
			KEY user_id (user_id),
			KEY entry_date (entry_date),
			KEY billable (billable),
			KEY billed (billed),
			KEY invoice_id (invoice_id)
		) {$charset_collate};";
		dbDelta( $sql_time );

		// Expenses table.
		$table_expenses = $wpdb->prefix . 'bkx_legal_expenses';
		$sql_expenses   = "CREATE TABLE {$table_expenses} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			matter_id bigint(20) unsigned NOT NULL,
			expense_date date NOT NULL,
			description text NOT NULL,
			category varchar(50) NOT NULL DEFAULT 'other',
			amount decimal(10,2) NOT NULL,
			billable tinyint(1) NOT NULL DEFAULT 1,
			billed tinyint(1) NOT NULL DEFAULT 0,
			invoice_id bigint(20) unsigned DEFAULT NULL,
			vendor varchar(255) DEFAULT NULL,
			receipt_url varchar(500) DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			deleted_at datetime DEFAULT NULL,
			deleted_by bigint(20) unsigned DEFAULT NULL,
			PRIMARY KEY (id),
			KEY matter_id (matter_id),
			KEY expense_date (expense_date),
			KEY category (category),
			KEY billed (billed)
		) {$charset_collate};";
		dbDelta( $sql_expenses );

		// Invoices table.
		$table_invoices = $wpdb->prefix . 'bkx_legal_invoices';
		$sql_invoices   = "CREATE TABLE {$table_invoices} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			invoice_number varchar(50) NOT NULL,
			matter_id bigint(20) unsigned NOT NULL,
			client_id bigint(20) unsigned NOT NULL,
			invoice_date date NOT NULL,
			due_date date NOT NULL,
			time_total decimal(10,2) NOT NULL DEFAULT 0.00,
			expense_total decimal(10,2) NOT NULL DEFAULT 0.00,
			subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
			tax_rate decimal(5,2) NOT NULL DEFAULT 0.00,
			tax_amount decimal(10,2) NOT NULL DEFAULT 0.00,
			total decimal(10,2) NOT NULL DEFAULT 0.00,
			balance decimal(10,2) NOT NULL DEFAULT 0.00,
			status varchar(50) NOT NULL DEFAULT 'draft',
			notes text DEFAULT NULL,
			view_token varchar(64) DEFAULT NULL,
			sent_at datetime DEFAULT NULL,
			paid_at datetime DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY invoice_number (invoice_number),
			KEY matter_id (matter_id),
			KEY client_id (client_id),
			KEY status (status),
			KEY due_date (due_date)
		) {$charset_collate};";
		dbDelta( $sql_invoices );

		// Invoice Items table.
		$table_items = $wpdb->prefix . 'bkx_legal_invoice_items';
		$sql_items   = "CREATE TABLE {$table_items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			invoice_id bigint(20) unsigned NOT NULL,
			item_type varchar(50) NOT NULL DEFAULT 'time',
			reference_id bigint(20) unsigned DEFAULT NULL,
			date date DEFAULT NULL,
			description text NOT NULL,
			quantity decimal(10,2) NOT NULL DEFAULT 1.00,
			rate decimal(10,2) NOT NULL DEFAULT 0.00,
			amount decimal(10,2) NOT NULL DEFAULT 0.00,
			PRIMARY KEY (id),
			KEY invoice_id (invoice_id),
			KEY item_type (item_type)
		) {$charset_collate};";
		dbDelta( $sql_items );

		// Payments table.
		$table_payments = $wpdb->prefix . 'bkx_legal_payments';
		$sql_payments   = "CREATE TABLE {$table_payments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			invoice_id bigint(20) unsigned NOT NULL,
			matter_id bigint(20) unsigned NOT NULL,
			client_id bigint(20) unsigned NOT NULL,
			amount decimal(10,2) NOT NULL,
			payment_date date NOT NULL,
			payment_method varchar(50) NOT NULL DEFAULT 'check',
			reference varchar(255) DEFAULT NULL,
			notes text DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY invoice_id (invoice_id),
			KEY matter_id (matter_id),
			KEY client_id (client_id),
			KEY payment_date (payment_date)
		) {$charset_collate};";
		dbDelta( $sql_payments );

		// Deadlines table.
		$table_deadlines = $wpdb->prefix . 'bkx_legal_deadlines';
		$sql_deadlines   = "CREATE TABLE {$table_deadlines} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			matter_id bigint(20) unsigned NOT NULL,
			title varchar(255) NOT NULL,
			due_date date NOT NULL,
			deadline_type varchar(50) NOT NULL DEFAULT 'other',
			description text DEFAULT NULL,
			reminder_days int(11) NOT NULL DEFAULT 7,
			status varchar(50) NOT NULL DEFAULT 'pending',
			reminder_sent tinyint(1) NOT NULL DEFAULT 0,
			reminder_sent_at datetime DEFAULT NULL,
			overdue_notified tinyint(1) NOT NULL DEFAULT 0,
			completed_at datetime DEFAULT NULL,
			completed_by bigint(20) unsigned DEFAULT NULL,
			completion_notes text DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY matter_id (matter_id),
			KEY due_date (due_date),
			KEY status (status),
			KEY deadline_type (deadline_type)
		) {$charset_collate};";
		dbDelta( $sql_deadlines );

		// Notes table.
		$table_notes = $wpdb->prefix . 'bkx_legal_notes';
		$sql_notes   = "CREATE TABLE {$table_notes} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			matter_id bigint(20) unsigned NOT NULL,
			content longtext NOT NULL,
			note_type varchar(50) NOT NULL DEFAULT 'general',
			is_private tinyint(1) NOT NULL DEFAULT 0,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY matter_id (matter_id),
			KEY note_type (note_type),
			KEY is_private (is_private)
		) {$charset_collate};";
		dbDelta( $sql_notes );

		// Activity Log table.
		$table_activity = $wpdb->prefix . 'bkx_legal_activity_log';
		$sql_activity   = "CREATE TABLE {$table_activity} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			matter_id bigint(20) unsigned NOT NULL,
			action varchar(100) NOT NULL,
			details text DEFAULT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY matter_id (matter_id),
			KEY action (action),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql_activity );

		// Trust Account Transactions table.
		$table_trust = $wpdb->prefix . 'bkx_legal_trust_transactions';
		$sql_trust   = "CREATE TABLE {$table_trust} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			matter_id bigint(20) unsigned NOT NULL,
			client_id bigint(20) unsigned NOT NULL,
			transaction_type varchar(50) NOT NULL,
			amount decimal(10,2) NOT NULL,
			balance_after decimal(10,2) NOT NULL,
			description text NOT NULL,
			reference varchar(255) DEFAULT NULL,
			transaction_date date NOT NULL,
			created_by bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY matter_id (matter_id),
			KEY client_id (client_id),
			KEY transaction_type (transaction_type),
			KEY transaction_date (transaction_date)
		) {$charset_collate};";
		dbDelta( $sql_trust );

		// Client Messages table.
		$table_messages = $wpdb->prefix . 'bkx_legal_messages';
		$sql_messages   = "CREATE TABLE {$table_messages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			matter_id bigint(20) unsigned DEFAULT NULL,
			sender_id bigint(20) unsigned NOT NULL,
			recipient_id bigint(20) unsigned NOT NULL,
			subject varchar(255) NOT NULL,
			message longtext NOT NULL,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			read_at datetime DEFAULT NULL,
			parent_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY matter_id (matter_id),
			KEY sender_id (sender_id),
			KEY recipient_id (recipient_id),
			KEY is_read (is_read),
			KEY parent_id (parent_id)
		) {$charset_collate};";
		dbDelta( $sql_messages );

		// Update version.
		update_option( 'bkx_legal_db_version', BKX_LEGAL_VERSION );
	}

	/**
	 * Rollback the migration.
	 *
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$tables = array(
			'bkx_legal_intakes',
			'bkx_legal_parties',
			'bkx_legal_conflict_checks',
			'bkx_legal_documents',
			'bkx_legal_document_access_log',
			'bkx_legal_retainers',
			'bkx_legal_time_entries',
			'bkx_legal_expenses',
			'bkx_legal_invoices',
			'bkx_legal_invoice_items',
			'bkx_legal_payments',
			'bkx_legal_deadlines',
			'bkx_legal_notes',
			'bkx_legal_activity_log',
			'bkx_legal_trust_transactions',
			'bkx_legal_messages',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		delete_option( 'bkx_legal_db_version' );
	}
}
