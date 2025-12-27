<?php
/**
 * Create Mailchimp Sync Log Table Migration
 *
 * @package BookingX\MailchimpPro\Migrations
 * @since   1.0.0
 */

namespace BookingX\MailchimpPro\Migrations;

use BookingX\AddonSDK\Database\Migration;

/**
 * Create sync log table.
 *
 * @since 1.0.0
 */
class CreateSyncLogTable extends Migration {

	/**
	 * Run the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		$table_name = $this->table( 'mailchimp_sync_log' );

		if ( $this->table_exists( 'mailchimp_sync_log' ) ) {
			return;
		}

		$columns = "
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			email varchar(255) NOT NULL,
			event varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			message text,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY booking_id (booking_id),
			KEY email (email),
			KEY event (event),
			KEY status (status),
			KEY created_at (created_at)
		";

		$this->create_table( 'mailchimp_sync_log', $columns );
	}

	/**
	 * Reverse the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function down(): void {
		$this->drop_table( 'mailchimp_sync_log' );
	}
}
