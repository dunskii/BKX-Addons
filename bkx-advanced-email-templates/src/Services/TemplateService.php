<?php
/**
 * Template Service.
 *
 * @package BookingX\AdvancedEmailTemplates
 */

namespace BookingX\AdvancedEmailTemplates\Services;

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
		$this->table = $wpdb->prefix . 'bkx_email_templates';
	}

	/**
	 * Get all templates.
	 *
	 * @param string $type Filter by type.
	 * @return array
	 */
	public function get_all_templates( $type = '' ) {
		global $wpdb;

		$where = '';
		if ( $type ) {
			$where = $wpdb->prepare( ' WHERE template_type = %s', $type );
		}

		return $wpdb->get_results( "SELECT * FROM {$this->table}{$where} ORDER BY name ASC" ); // phpcs:ignore
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
	 * Get template by trigger event.
	 *
	 * @param string $event Event name.
	 * @return object|null
	 */
	public function get_template_by_event( $event ) {
		global $wpdb;

		return $wpdb->get_row( // phpcs:ignore
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE trigger_event = %s AND status = 'active' LIMIT 1",
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
				return new \WP_Error( 'db_error', __( 'Failed to update template.', 'bkx-advanced-email-templates' ) );
			}

			return $template_id;
		} else {
			// Insert.
			$data['created_at'] = current_time( 'mysql' );
			$data['created_by'] = get_current_user_id();

			$result = $wpdb->insert( $this->table, $data ); // phpcs:ignore

			if ( false === $result ) {
				return new \WP_Error( 'db_error', __( 'Failed to create template.', 'bkx-advanced-email-templates' ) );
			}

			return $wpdb->insert_id;
		}
	}

	/**
	 * Delete template.
	 *
	 * @param int $template_id Template ID.
	 * @return bool
	 */
	public function delete_template( $template_id ) {
		global $wpdb;

		// Don't allow deleting default templates.
		$template = $this->get_template( $template_id );
		if ( $template && $template->is_default ) {
			return false;
		}

		return (bool) $wpdb->delete( $this->table, array( 'id' => $template_id ) ); // phpcs:ignore
	}

	/**
	 * Duplicate template.
	 *
	 * @param int $template_id Template ID.
	 * @return int|false New template ID or false.
	 */
	public function duplicate_template( $template_id ) {
		global $wpdb;

		$template = $this->get_template( $template_id );
		if ( ! $template ) {
			return false;
		}

		$data = array(
			'name'          => $template->name . ' (Copy)',
			'slug'          => $template->slug . '-copy-' . time(),
			'subject'       => $template->subject,
			'preheader'     => $template->preheader,
			'content'       => $template->content,
			'design_data'   => $template->design_data,
			'template_type' => $template->template_type,
			'trigger_event' => '', // Clear trigger to avoid conflicts.
			'status'        => 'draft',
			'is_default'    => 0,
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
			'created_by'    => get_current_user_id(),
		);

		$result = $wpdb->insert( $this->table, $data ); // phpcs:ignore

		if ( ! $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update template status.
	 *
	 * @param int    $template_id Template ID.
	 * @param string $status      Status.
	 * @return bool
	 */
	public function update_status( $template_id, $status ) {
		global $wpdb;

		return (bool) $wpdb->update( // phpcs:ignore
			$this->table,
			array( 'status' => $status ),
			array( 'id' => $template_id )
		);
	}

	/**
	 * Get available trigger events.
	 *
	 * @return array
	 */
	public function get_trigger_events() {
		return array(
			'bkx_booking_created'     => __( 'Booking Created', 'bkx-advanced-email-templates' ),
			'bkx_booking_confirmed'   => __( 'Booking Confirmed', 'bkx-advanced-email-templates' ),
			'bkx_booking_cancelled'   => __( 'Booking Cancelled', 'bkx-advanced-email-templates' ),
			'bkx_booking_completed'   => __( 'Booking Completed', 'bkx-advanced-email-templates' ),
			'bkx_booking_rescheduled' => __( 'Booking Rescheduled', 'bkx-advanced-email-templates' ),
			'bkx_booking_reminder'    => __( 'Booking Reminder', 'bkx-advanced-email-templates' ),
			'bkx_payment_received'    => __( 'Payment Received', 'bkx-advanced-email-templates' ),
			'bkx_payment_refunded'    => __( 'Payment Refunded', 'bkx-advanced-email-templates' ),
		);
	}

	/**
	 * Get template types.
	 *
	 * @return array
	 */
	public function get_template_types() {
		return array(
			'notification' => __( 'Customer Notification', 'bkx-advanced-email-templates' ),
			'admin'        => __( 'Admin Notification', 'bkx-advanced-email-templates' ),
			'reminder'     => __( 'Reminder', 'bkx-advanced-email-templates' ),
			'marketing'    => __( 'Marketing', 'bkx-advanced-email-templates' ),
			'custom'       => __( 'Custom', 'bkx-advanced-email-templates' ),
		);
	}
}
