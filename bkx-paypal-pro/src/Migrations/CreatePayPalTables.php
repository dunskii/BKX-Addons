<?php
/**
 * Create PayPal Tables Migration
 *
 * Creates the database tables for PayPal Pro addon.
 *
 * @package BookingX\PayPalPro\Migrations
 * @since   1.0.0
 */

namespace BookingX\PayPalPro\Migrations;

use BookingX\AddonSDK\Database\Migration;

/**
 * Create PayPal tables migration class.
 *
 * @since 1.0.0
 */
class CreatePayPalTables extends Migration {

	/**
	 * Run the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		// Create transactions table.
		$this->create_transactions_table();

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
		$this->drop_table( 'paypal_transactions' );
		$this->drop_table( 'paypal_webhook_events' );
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
			paypal_order_id varchar(100) NOT NULL,
			capture_id varchar(100) DEFAULT NULL,
			amount decimal(10,2) DEFAULT NULL,
			currency varchar(10) DEFAULT NULL,
			status varchar(50) NOT NULL,
			payment_source text DEFAULT NULL,
			metadata longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_order (paypal_order_id),
			KEY booking_id (booking_id),
			KEY capture_id (capture_id),
			KEY booking_status (booking_id, status),
			KEY status (status),
			KEY created_at (created_at)
		";

		$this->create_table( 'paypal_transactions', $columns );
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
			paypal_event_id varchar(100) NOT NULL,
			event_type varchar(100) NOT NULL,
			payload longtext NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'received',
			message text DEFAULT NULL,
			processed_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY paypal_event_id (paypal_event_id),
			KEY event_type (event_type),
			KEY status (status),
			KEY processed_at (processed_at)
		";

		$this->create_table( 'paypal_webhook_events', $columns );
	}
}
