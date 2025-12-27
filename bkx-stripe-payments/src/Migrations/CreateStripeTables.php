<?php
/**
 * Create Stripe Tables Migration
 *
 * Creates all necessary database tables for Stripe integration.
 *
 * @package BookingX\StripePayments\Migrations
 * @since   1.0.0
 */

namespace BookingX\StripePayments\Migrations;

use BookingX\AddonSDK\Database\Schema;

/**
 * Migration to create Stripe tables.
 *
 * @since 1.0.0
 */
class CreateStripeTables {

	/**
	 * Run migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Create stripe_transactions table
		$transactions_schema = Schema::create()
			->id()
			->bigint( 'booking_id', true, false )
			->string( 'stripe_payment_intent_id', 100, false )
			->string( 'stripe_transaction_id', 100, false, '' )
			->string( 'stripe_customer_id', 100, false, '' )
			->decimal( 'amount', 10, 2, false )
			->string( 'currency', 10, false, 'USD' )
			->string( 'status', 50, false, 'pending' )
			->string( 'payment_method_type', 50, false, 'card' )
			->text( 'metadata', true )
			->timestamps()
			->index( 'idx_booking_id', 'booking_id' )
			->index( 'idx_payment_intent', 'stripe_payment_intent_id' )
			->index( 'idx_status', 'status' )
			->build();

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_stripe_transactions (
				{$transactions_schema}
			) {$charset_collate};"
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

		// Create stripe_subscriptions table
		$subscriptions_schema = Schema::create()
			->id()
			->bigint( 'booking_id', true, false )
			->string( 'stripe_subscription_id', 100, false )
			->string( 'stripe_customer_id', 100, false )
			->string( 'status', 50, false, 'active' )
			->datetime( 'current_period_start', false )
			->datetime( 'current_period_end', false )
			->boolean( 'cancel_at_period_end', false )
			->text( 'metadata', true )
			->timestamps()
			->index( 'idx_booking_id', 'booking_id' )
			->unique( 'idx_subscription_id', 'stripe_subscription_id' )
			->index( 'idx_status', 'status' )
			->build();

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_stripe_subscriptions (
				{$subscriptions_schema}
			) {$charset_collate};"
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

		// Create stripe_refunds table
		$refunds_schema = Schema::create()
			->id()
			->bigint( 'booking_id', true, false )
			->bigint( 'transaction_id', true, false )
			->string( 'stripe_refund_id', 100, false )
			->string( 'stripe_payment_intent_id', 100, false )
			->decimal( 'amount', 10, 2, false )
			->string( 'currency', 10, false, 'USD' )
			->string( 'status', 50, false, 'pending' )
			->string( 'reason', 100, false, '' )
			->text( 'metadata', true )
			->timestamps()
			->index( 'idx_booking_id', 'booking_id' )
			->index( 'idx_transaction_id', 'transaction_id' )
			->index( 'idx_refund_id', 'stripe_refund_id' )
			->build();

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_stripe_refunds (
				{$refunds_schema}
			) {$charset_collate};"
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

		// Create stripe_webhook_events table
		$webhook_schema = Schema::create()
			->id()
			->string( 'stripe_event_id', 100, false )
			->string( 'event_type', 100, false )
			->longtext( 'payload', false )
			->timestamp( 'created_at', false )
			->unique( 'idx_event_id', 'stripe_event_id' )
			->index( 'idx_event_type', 'event_type' )
			->build();

		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bkx_stripe_webhook_events (
				{$webhook_schema}
			) {$charset_collate};"
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	/**
	 * Rollback migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function down(): void {
		global $wpdb;

		$tables = array(
			'bkx_stripe_transactions',
			'bkx_stripe_subscriptions',
			'bkx_stripe_refunds',
			'bkx_stripe_webhook_events',
		);

		foreach ( $tables as $table ) {
			// Use prepared statement with %i for table identifier - SECURITY CRITICAL
			$wpdb->query(
				$wpdb->prepare(
					'DROP TABLE IF EXISTS %i',
					$wpdb->prefix . $table
				)
			); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		}
	}
}
