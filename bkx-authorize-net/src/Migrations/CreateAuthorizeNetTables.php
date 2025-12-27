<?php
/**
 * Create Authorize.net Tables Migration
 *
 * Creates the database tables for Authorize.net addon.
 *
 * @package BookingX\AuthorizeNet\Migrations
 * @since   1.0.0
 */

namespace BookingX\AuthorizeNet\Migrations;

use BookingX\AddonSDK\Database\Migration;

/**
 * Create Authorize.net tables migration class.
 *
 * @since 1.0.0
 */
class CreateAuthorizeNetTables extends Migration {

	/**
	 * Run the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		// Create transactions table.
		$this->create_transactions_table();

		// Create refunds table.
		$this->create_refunds_table();

		// Create webhook events table.
		$this->create_webhook_events_table();

		// Create customer profiles table (for CIM).
		$this->create_customer_profiles_table();
	}

	/**
	 * Reverse the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$tables = array(
			'bkx_authnet_transactions',
			'bkx_authnet_refunds',
			'bkx_authnet_webhook_events',
			'bkx_authnet_customer_profiles',
		);

		foreach ( $tables as $table ) {
			// Use %i placeholder for table identifier - SECURITY.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->query(
				$wpdb->prepare(
					'DROP TABLE IF EXISTS %i',
					$wpdb->prefix . $table
				)
			);
		}
	}

	/**
	 * Create transactions table.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function create_transactions_table(): void {
		$columns = "
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			transaction_id varchar(50) NOT NULL,
			transaction_type varchar(30) NOT NULL DEFAULT 'auth_capture',
			amount decimal(10,2) NOT NULL,
			currency varchar(10) NOT NULL DEFAULT 'USD',
			status varchar(50) NOT NULL,
			auth_code varchar(20) DEFAULT NULL,
			avs_response varchar(10) DEFAULT NULL,
			cvv_response varchar(10) DEFAULT NULL,
			card_type varchar(20) DEFAULT NULL,
			last_four varchar(4) DEFAULT NULL,
			response_code varchar(10) DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_transaction (transaction_id),
			KEY booking_id (booking_id),
			KEY booking_status (booking_id, status),
			KEY status (status),
			KEY created_at (created_at)
		";

		$this->create_table( 'authnet_transactions', $columns );
	}

	/**
	 * Create refunds table.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function create_refunds_table(): void {
		$columns = "
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			transaction_id varchar(50) NOT NULL,
			refund_id varchar(50) NOT NULL,
			amount decimal(10,2) NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'completed',
			reason text DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY booking_id (booking_id),
			KEY transaction_id (transaction_id),
			KEY refund_id (refund_id),
			KEY status (status)
		";

		$this->create_table( 'authnet_refunds', $columns );
	}

	/**
	 * Create webhook events table.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function create_webhook_events_table(): void {
		$columns = "
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			notification_id varchar(100) NOT NULL,
			event_type varchar(100) NOT NULL,
			payload longtext NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'received',
			message text DEFAULT NULL,
			processed_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY notification_id (notification_id),
			KEY event_type (event_type),
			KEY status (status),
			KEY processed_at (processed_at)
		";

		$this->create_table( 'authnet_webhook_events', $columns );
	}

	/**
	 * Create customer profiles table for CIM.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function create_customer_profiles_table(): void {
		$columns = "
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned DEFAULT NULL,
			customer_email varchar(255) NOT NULL,
			profile_id varchar(50) NOT NULL,
			payment_profile_id varchar(50) DEFAULT NULL,
			card_type varchar(20) DEFAULT NULL,
			last_four varchar(4) DEFAULT NULL,
			expiration_date varchar(7) DEFAULT NULL,
			is_default tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_profile (profile_id),
			KEY user_id (user_id),
			KEY customer_email (customer_email),
			KEY payment_profile_id (payment_profile_id)
		";

		$this->create_table( 'authnet_customer_profiles', $columns );
	}
}
