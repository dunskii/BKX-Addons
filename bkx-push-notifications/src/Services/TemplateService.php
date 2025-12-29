<?php
/**
 * Template Service.
 *
 * @package BookingX\PushNotifications
 */

namespace BookingX\PushNotifications\Services;

defined( 'ABSPATH' ) || exit;

/**
 * TemplateService class.
 */
class TemplateService {

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'bkx_push_templates';
	}

	/**
	 * Get all templates.
	 *
	 * @return array
	 */
	public function get_all_templates() {
		global $wpdb;

		return $wpdb->get_results( "SELECT * FROM {$this->table} ORDER BY name ASC" ); // phpcs:ignore
	}

	/**
	 * Get template by ID.
	 *
	 * @param int $template_id Template ID.
	 * @return object|null
	 */
	public function get_template( $template_id ) {
		global $wpdb;

		return $wpdb->get_row( // phpcs:ignore
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$template_id
			)
		);
	}

	/**
	 * Get templates by trigger event.
	 *
	 * @param string $event Event name.
	 * @return array
	 */
	public function get_templates_by_event( $event ) {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE trigger_event = %s AND status = 'active'",
				$event
			)
		);
	}

	/**
	 * Save template.
	 *
	 * @param int   $template_id Template ID (0 for new).
	 * @param array $data        Template data.
	 * @return int|\WP_Error Template ID or error.
	 */
	public function save_template( $template_id, $data ) {
		global $wpdb;

		// Generate slug if not provided.
		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['name'] );
		}

		// Check for duplicate slug.
		$existing = $this->get_template_by_slug( $data['slug'] );
		if ( $existing && (int) $existing->id !== $template_id ) {
			$data['slug'] .= '-' . time();
		}

		$data['updated_at'] = current_time( 'mysql' );

		if ( $template_id ) {
			// Update.
			$result = $wpdb->update( // phpcs:ignore
				$this->table,
				$data,
				array( 'id' => $template_id )
			);

			if ( false === $result ) {
				return new \WP_Error( 'db_error', __( 'Failed to update template.', 'bkx-push-notifications' ) );
			}

			return $template_id;
		} else {
			// Insert.
			$data['created_at'] = current_time( 'mysql' );

			$result = $wpdb->insert( $this->table, $data ); // phpcs:ignore

			if ( false === $result ) {
				return new \WP_Error( 'db_error', __( 'Failed to create template.', 'bkx-push-notifications' ) );
			}

			return $wpdb->insert_id;
		}
	}

	/**
	 * Get template by slug.
	 *
	 * @param string $slug Template slug.
	 * @return object|null
	 */
	public function get_template_by_slug( $slug ) {
		global $wpdb;

		return $wpdb->get_row( // phpcs:ignore
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE slug = %s",
				$slug
			)
		);
	}

	/**
	 * Delete template.
	 *
	 * @param int $template_id Template ID.
	 * @return bool
	 */
	public function delete_template( $template_id ) {
		global $wpdb;

		return (bool) $wpdb->delete( $this->table, array( 'id' => $template_id ) ); // phpcs:ignore
	}

	/**
	 * Get available trigger events.
	 *
	 * @return array
	 */
	public function get_trigger_events() {
		return array(
			'bkx_booking_created'     => __( 'Booking Created', 'bkx-push-notifications' ),
			'bkx_booking_confirmed'   => __( 'Booking Confirmed', 'bkx-push-notifications' ),
			'bkx_booking_cancelled'   => __( 'Booking Cancelled', 'bkx-push-notifications' ),
			'bkx_booking_completed'   => __( 'Booking Completed', 'bkx-push-notifications' ),
			'bkx_booking_rescheduled' => __( 'Booking Rescheduled', 'bkx-push-notifications' ),
			'bkx_booking_reminder'    => __( 'Booking Reminder', 'bkx-push-notifications' ),
		);
	}

	/**
	 * Get target audiences.
	 *
	 * @return array
	 */
	public function get_target_audiences() {
		return array(
			'customer' => __( 'Customer', 'bkx-push-notifications' ),
			'staff'    => __( 'Staff Member', 'bkx-push-notifications' ),
			'admin'    => __( 'Administrator', 'bkx-push-notifications' ),
		);
	}

	/**
	 * Get available variables.
	 *
	 * @return array
	 */
	public function get_available_variables() {
		return array(
			'{{booking_id}}'     => __( 'Booking ID', 'bkx-push-notifications' ),
			'{{customer_name}}'  => __( 'Customer Name', 'bkx-push-notifications' ),
			'{{service_name}}'   => __( 'Service Name', 'bkx-push-notifications' ),
			'{{staff_name}}'     => __( 'Staff Name', 'bkx-push-notifications' ),
			'{{booking_date}}'   => __( 'Booking Date', 'bkx-push-notifications' ),
			'{{booking_time}}'   => __( 'Booking Time', 'bkx-push-notifications' ),
			'{{booking_total}}'  => __( 'Booking Total', 'bkx-push-notifications' ),
		);
	}
}
