<?php
/**
 * Create Fitness Tables Migration
 *
 * Creates database tables for Fitness & Sports addon.
 *
 * @package BookingX\FitnessSports\Migrations
 * @since   1.0.0
 */

namespace BookingX\FitnessSports\Migrations;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CreateFitnessTables
 *
 * @since 1.0.0
 */
class CreateFitnessTables {

	/**
	 * Run the migration.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function up(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Class schedules table.
		$table_name = $wpdb->prefix . 'bkx_class_schedules';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			class_id bigint(20) UNSIGNED NOT NULL,
			trainer_id bigint(20) UNSIGNED DEFAULT 0,
			start_datetime datetime NOT NULL,
			end_datetime datetime NOT NULL,
			location varchar(255) DEFAULT '',
			max_capacity int(11) DEFAULT 20,
			is_recurring tinyint(1) DEFAULT 0,
			recurrence_day varchar(20) DEFAULT '',
			recurrence_end_date date DEFAULT NULL,
			status varchar(20) DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY class_id (class_id),
			KEY trainer_id (trainer_id),
			KEY start_datetime (start_datetime),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );

		// Class bookings table.
		$table_name = $wpdb->prefix . 'bkx_class_bookings';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			class_id bigint(20) UNSIGNED NOT NULL,
			schedule_id bigint(20) UNSIGNED NOT NULL,
			status varchar(20) DEFAULT 'confirmed',
			booked_at datetime DEFAULT CURRENT_TIMESTAMP,
			attended_at datetime DEFAULT NULL,
			cancelled_at datetime DEFAULT NULL,
			reminder_sent tinyint(1) DEFAULT 0,
			notes text,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY class_id (class_id),
			KEY schedule_id (schedule_id),
			KEY status (status),
			UNIQUE KEY user_schedule (user_id, schedule_id)
		) {$charset_collate};";

		dbDelta( $sql );

		// Class waitlist table.
		$table_name = $wpdb->prefix . 'bkx_class_waitlist';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			class_id bigint(20) UNSIGNED NOT NULL,
			schedule_id bigint(20) UNSIGNED NOT NULL,
			position int(11) NOT NULL,
			status varchar(20) DEFAULT 'waiting',
			added_at datetime DEFAULT CURRENT_TIMESTAMP,
			notified_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY schedule_id (schedule_id),
			KEY status (status),
			KEY position (position)
		) {$charset_collate};";

		dbDelta( $sql );

		// User memberships table.
		$table_name = $wpdb->prefix . 'bkx_user_memberships';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			plan_id bigint(20) UNSIGNED NOT NULL,
			status varchar(20) DEFAULT 'active',
			start_date date NOT NULL,
			end_date date NOT NULL,
			classes_remaining int(11) DEFAULT -1,
			pt_sessions_remaining int(11) DEFAULT 0,
			auto_renew tinyint(1) DEFAULT 0,
			payment_id bigint(20) UNSIGNED DEFAULT 0,
			expiry_notified tinyint(1) DEFAULT 0,
			cancelled_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY plan_id (plan_id),
			KEY status (status),
			KEY end_date (end_date)
		) {$charset_collate};";

		dbDelta( $sql );

		// Equipment bookings table.
		$table_name = $wpdb->prefix . 'bkx_equipment_bookings';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			equipment_id bigint(20) UNSIGNED NOT NULL,
			start_time datetime NOT NULL,
			end_time datetime NOT NULL,
			status varchar(20) DEFAULT 'confirmed',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY equipment_id (equipment_id),
			KEY start_time (start_time),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );

		// Workout logs table.
		$table_name = $wpdb->prefix . 'bkx_workout_logs';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			workout_type varchar(50) NOT NULL,
			duration int(11) DEFAULT 0,
			calories int(11) DEFAULT 0,
			exercises longtext,
			notes text,
			workout_date date NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY workout_type (workout_type),
			KEY workout_date (workout_date)
		) {$charset_collate};";

		dbDelta( $sql );

		// Update version option.
		update_option( 'bkx_fitness_sports_db_version', '1.0.0' );
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
			$wpdb->prefix . 'bkx_class_schedules',
			$wpdb->prefix . 'bkx_class_bookings',
			$wpdb->prefix . 'bkx_class_waitlist',
			$wpdb->prefix . 'bkx_user_memberships',
			$wpdb->prefix . 'bkx_equipment_bookings',
			$wpdb->prefix . 'bkx_workout_logs',
		);

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is constructed internally.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( 'bkx_fitness_sports_db_version' );
	}
}
