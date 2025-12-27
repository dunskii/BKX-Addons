<?php
/**
 * Create Square Database Tables Migration
 *
 * @package BookingX\SquarePayments\Migrations
 */

namespace BookingX\SquarePayments\Migrations;

use BookingX\AddonSDK\Database\Migration;
use BookingX\AddonSDK\Database\Schema;

/**
 * Create Square tables migration.
 *
 * @since 1.0.0
 */
class CreateSquareTables extends Migration {

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
		// Drop tables in reverse order (child tables first).
		$this->drop_table( 'square_refunds' );
		$this->drop_table( 'square_webhook_events' );
		$this->drop_table( 'square_transactions' );
	}

	/**
	 * Create transactions table.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function create_transactions_table(): void {
		$schema = Schema::create()
			->id()
			->bigint( 'booking_id', true, false )
			->string( 'square_payment_id', 255, false )
			->string( 'square_order_id', 255, true )
			->string( 'square_customer_id', 255, true )
			->bigint( 'amount_money', false, false )
			->string( 'currency', 10, false, 'USD' )
			->string( 'status', 50, false, 'pending' )
			->string( 'source_type', 50, true )
			->string( 'card_brand', 50, true )
			->string( 'last_4', 4, true )
			->text( 'receipt_url', true )
			->timestamps()
			->index( 'idx_booking_id', 'booking_id' )
			->index( 'idx_square_payment_id', 'square_payment_id' )
			->index( 'idx_square_customer_id', 'square_customer_id' )
			->index( 'idx_status', 'status' );

		$this->create_table( 'square_transactions', $schema->build() );
	}

	/**
	 * Create refunds table.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function create_refunds_table(): void {
		$schema = Schema::create()
			->id()
			->bigint( 'transaction_id', true, false )
			->string( 'square_refund_id', 255, false )
			->bigint( 'amount_money', false, false )
			->text( 'reason', true )
			->string( 'status', 50, false, 'pending' )
			->timestamps()
			->index( 'idx_transaction_id', 'transaction_id' )
			->index( 'idx_square_refund_id', 'square_refund_id' )
			->index( 'idx_status', 'status' );

		$this->create_table( 'square_refunds', $schema->build() );
	}

	/**
	 * Create webhook events table.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function create_webhook_events_table(): void {
		$schema = Schema::create()
			->id()
			->string( 'square_event_id', 255, true )
			->string( 'event_type', 100, false )
			->longtext( 'payload', false )
			->datetime( 'processed_at', false )
			->index( 'idx_square_event_id', 'square_event_id' )
			->index( 'idx_event_type', 'event_type' );

		$this->create_table( 'square_webhook_events', $schema->build() );
	}
}
