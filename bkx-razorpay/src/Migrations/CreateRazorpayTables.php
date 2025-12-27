<?php
/**
 * Create Razorpay Tables Migration
 *
 * Creates the database tables for Razorpay addon.
 *
 * @package BookingX\Razorpay\Migrations
 * @since   1.0.0
 */

namespace BookingX\Razorpay\Migrations;

use BookingX\AddonSDK\Database\Migration;

/**
 * Create Razorpay tables migration class.
 *
 * @since 1.0.0
 */
class CreateRazorpayTables extends Migration {

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
			'bkx_razorpay_transactions',
			'bkx_razorpay_refunds',
			'bkx_razorpay_webhook_events',
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
			razorpay_order_id varchar(100) NOT NULL,
			razorpay_payment_id varchar(100) DEFAULT NULL,
			amount decimal(10,2) NOT NULL,
			currency varchar(10) NOT NULL DEFAULT 'INR',
			status varchar(50) NOT NULL,
			payment_method varchar(50) DEFAULT NULL,
			customer_email varchar(255) DEFAULT NULL,
			customer_contact varchar(50) DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_order (razorpay_order_id),
			KEY booking_id (booking_id),
			KEY razorpay_payment_id (razorpay_payment_id),
			KEY booking_status (booking_id, status),
			KEY status (status),
			KEY created_at (created_at)
		";

		$this->create_table( 'razorpay_transactions', $columns );
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
			razorpay_payment_id varchar(100) NOT NULL,
			razorpay_refund_id varchar(100) NOT NULL,
			amount decimal(10,2) NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'processed',
			reason text DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY booking_id (booking_id),
			KEY razorpay_payment_id (razorpay_payment_id),
			KEY razorpay_refund_id (razorpay_refund_id),
			KEY status (status)
		";

		$this->create_table( 'razorpay_refunds', $columns );
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
			event_id varchar(100) NOT NULL,
			event_type varchar(100) NOT NULL,
			payload longtext NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'received',
			message text DEFAULT NULL,
			processed_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY event_id (event_id),
			KEY event_type (event_type),
			KEY status (status),
			KEY processed_at (processed_at)
		";

		$this->create_table( 'razorpay_webhook_events', $columns );
	}
}
