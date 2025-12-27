<?php
/**
 * Template Service
 *
 * Handles SMS template management and parsing.
 *
 * @package BookingX\SmsNotificationsPro\Services
 * @since   1.0.0
 */

namespace BookingX\SmsNotificationsPro\Services;

use BookingX\SmsNotificationsPro\SmsNotificationsProAddon;
use WP_Error;

/**
 * Template service class.
 *
 * @since 1.0.0
 */
class TemplateService {

	/**
	 * Addon instance.
	 *
	 * @var SmsNotificationsProAddon
	 */
	protected SmsNotificationsProAddon $addon;

	/**
	 * Templates table.
	 *
	 * @var string
	 */
	protected string $table;

	/**
	 * Constructor.
	 *
	 * @param SmsNotificationsProAddon $addon Addon instance.
	 */
	public function __construct( SmsNotificationsProAddon $addon ) {
		global $wpdb;

		$this->addon = $addon;
		$this->table = $wpdb->prefix . 'bkx_sms_templates';
	}

	/**
	 * Get a template by key and recipient type.
	 *
	 * @param string $key Template key.
	 * @param string $recipient_type Recipient type.
	 * @return object|null Template or null.
	 */
	public function get_template( string $key, string $recipient_type ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE template_key = %s AND recipient_type = %s',
				$this->table,
				$key,
				$recipient_type
			)
		);
	}

	/**
	 * Get all templates.
	 *
	 * @param array $args Query arguments.
	 * @return array Templates.
	 */
	public function get_templates( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'recipient_type' => '',
			'is_active'      => null,
			'orderby'        => 'template_key',
			'order'          => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );

		if ( ! empty( $args['recipient_type'] ) ) {
			$where[] = $wpdb->prepare( 'recipient_type = %s', $args['recipient_type'] );
		}

		if ( null !== $args['is_active'] ) {
			$where[] = $wpdb->prepare( 'is_active = %d', $args['is_active'] ? 1 : 0 );
		}

		$where_clause = implode( ' AND ', $where );
		$orderby      = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE {$where_clause} ORDER BY {$orderby}",
				$this->table
			)
		);
	}

	/**
	 * Update a template.
	 *
	 * @param int   $id Template ID.
	 * @param array $data Template data.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_template( int $id, array $data ) {
		global $wpdb;

		$allowed = array( 'name', 'content', 'is_active' );
		$update  = array();

		foreach ( $allowed as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update[ $field ] = $data[ $field ];
			}
		}

		if ( empty( $update ) ) {
			return new WP_Error( 'no_data', __( 'No data to update.', 'bkx-sms-notifications-pro' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update( $this->table, $update, array( 'id' => $id ) );

		if ( false === $result ) {
			return new WP_Error( 'update_failed', __( 'Failed to update template.', 'bkx-sms-notifications-pro' ) );
		}

		return true;
	}

	/**
	 * Create a template.
	 *
	 * @param array $data Template data.
	 * @return int|WP_Error Template ID on success, WP_Error on failure.
	 */
	public function create_template( array $data ) {
		global $wpdb;

		$required = array( 'template_key', 'name', 'recipient_type', 'content' );

		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new WP_Error( 'missing_field', sprintf( __( 'Missing required field: %s', 'bkx-sms-notifications-pro' ), $field ) );
			}
		}

		$insert = array(
			'template_key'   => sanitize_key( $data['template_key'] ),
			'name'           => sanitize_text_field( $data['name'] ),
			'recipient_type' => sanitize_text_field( $data['recipient_type'] ),
			'content'        => sanitize_textarea_field( $data['content'] ),
			'is_active'      => isset( $data['is_active'] ) ? absint( $data['is_active'] ) : 1,
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $this->table, $insert );

		if ( false === $result ) {
			return new WP_Error( 'insert_failed', __( 'Failed to create template.', 'bkx-sms-notifications-pro' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Delete a template.
	 *
	 * @param int $id Template ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_template( int $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( $this->table, array( 'id' => $id ) );

		if ( false === $result ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete template.', 'bkx-sms-notifications-pro' ) );
		}

		return true;
	}

	/**
	 * Get parsed template for a booking.
	 *
	 * @param string $key Template key.
	 * @param string $recipient_type Recipient type.
	 * @param int    $booking_id Booking ID.
	 * @return string|WP_Error Parsed content or error.
	 */
	public function get_parsed_template( string $key, string $recipient_type, int $booking_id ) {
		$template = $this->get_template( $key, $recipient_type );

		if ( ! $template || ! $template->is_active ) {
			return new WP_Error( 'no_template', __( 'Template not found or inactive.', 'bkx-sms-notifications-pro' ) );
		}

		return $this->parse_template( $template->content, $booking_id );
	}

	/**
	 * Parse template placeholders.
	 *
	 * @param string $content Template content.
	 * @param int    $booking_id Booking ID.
	 * @return string Parsed content.
	 */
	public function parse_template( string $content, int $booking_id ): string {
		$placeholders = $this->get_booking_placeholders( $booking_id );

		foreach ( $placeholders as $key => $value ) {
			$content = str_replace( '{' . $key . '}', $value, $content );
		}

		return $content;
	}

	/**
	 * Get booking placeholders.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array Placeholders.
	 */
	public function get_booking_placeholders( int $booking_id ): array {
		$booking = get_post( $booking_id );

		if ( ! $booking ) {
			return array();
		}

		// Get booking meta.
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );
		$booking_time = get_post_meta( $booking_id, 'booking_time', true );
		$seat_id      = get_post_meta( $booking_id, 'seat_id', true );
		$base_id      = get_post_meta( $booking_id, 'base_id', true );

		// Get customer info.
		$customer_first = get_post_meta( $booking_id, 'customer_first_name', true );
		$customer_last  = get_post_meta( $booking_id, 'customer_last_name', true );
		$customer_email = get_post_meta( $booking_id, 'customer_email', true );
		$customer_phone = get_post_meta( $booking_id, 'customer_phone', true );

		// Get seat (resource) and base (service) info.
		$seat = get_post( $seat_id );
		$base = get_post( $base_id );

		// Get total.
		$total = get_post_meta( $booking_id, 'total_price', true );

		// Format date and time.
		$date_format = get_option( 'date_format', 'F j, Y' );
		$time_format = get_option( 'time_format', 'g:i a' );

		$formatted_date = ! empty( $booking_date ) ? date_i18n( $date_format, strtotime( $booking_date ) ) : '';
		$formatted_time = ! empty( $booking_time ) ? date_i18n( $time_format, strtotime( $booking_time ) ) : '';

		$placeholders = array(
			'booking_id'          => $booking_id,
			'booking_date'        => $formatted_date,
			'booking_time'        => $formatted_time,
			'booking_status'      => $booking->post_status,
			'customer_first_name' => $customer_first,
			'customer_last_name'  => $customer_last,
			'customer_name'       => trim( $customer_first . ' ' . $customer_last ),
			'customer_email'      => $customer_email,
			'customer_phone'      => $customer_phone,
			'service_name'        => $base ? $base->post_title : '',
			'staff_name'          => $seat ? $seat->post_title : '',
			'total'               => number_format( (float) $total, 2 ),
			'currency'            => get_option( 'bkx_currency', 'USD' ),
			'site_name'           => get_bloginfo( 'name' ),
			'site_url'            => home_url(),
		);

		/**
		 * Filter SMS template placeholders.
		 *
		 * @param array $placeholders Placeholder values.
		 * @param int   $booking_id Booking ID.
		 */
		return apply_filters( 'bkx_sms_template_placeholders', $placeholders, $booking_id );
	}

	/**
	 * Get available placeholders.
	 *
	 * @return array Available placeholders.
	 */
	public function get_available_placeholders(): array {
		return array(
			'booking_id'          => __( 'Booking ID', 'bkx-sms-notifications-pro' ),
			'booking_date'        => __( 'Booking Date', 'bkx-sms-notifications-pro' ),
			'booking_time'        => __( 'Booking Time', 'bkx-sms-notifications-pro' ),
			'booking_status'      => __( 'Booking Status', 'bkx-sms-notifications-pro' ),
			'customer_first_name' => __( 'Customer First Name', 'bkx-sms-notifications-pro' ),
			'customer_last_name'  => __( 'Customer Last Name', 'bkx-sms-notifications-pro' ),
			'customer_name'       => __( 'Customer Full Name', 'bkx-sms-notifications-pro' ),
			'customer_email'      => __( 'Customer Email', 'bkx-sms-notifications-pro' ),
			'customer_phone'      => __( 'Customer Phone', 'bkx-sms-notifications-pro' ),
			'service_name'        => __( 'Service Name', 'bkx-sms-notifications-pro' ),
			'staff_name'          => __( 'Staff Name', 'bkx-sms-notifications-pro' ),
			'total'               => __( 'Booking Total', 'bkx-sms-notifications-pro' ),
			'currency'            => __( 'Currency', 'bkx-sms-notifications-pro' ),
			'site_name'           => __( 'Site Name', 'bkx-sms-notifications-pro' ),
			'site_url'            => __( 'Site URL', 'bkx-sms-notifications-pro' ),
		);
	}

	/**
	 * Preview a template.
	 *
	 * @param string $content Template content.
	 * @return string Previewed content with sample data.
	 */
	public function preview_template( string $content ): string {
		$sample = array(
			'booking_id'          => '12345',
			'booking_date'        => date_i18n( get_option( 'date_format' ) ),
			'booking_time'        => '10:00 AM',
			'booking_status'      => 'confirmed',
			'customer_first_name' => 'John',
			'customer_last_name'  => 'Doe',
			'customer_name'       => 'John Doe',
			'customer_email'      => 'john@example.com',
			'customer_phone'      => '+1234567890',
			'service_name'        => 'Sample Service',
			'staff_name'          => 'Jane Smith',
			'total'               => '99.99',
			'currency'            => 'USD',
			'site_name'           => get_bloginfo( 'name' ),
			'site_url'            => home_url(),
		);

		foreach ( $sample as $key => $value ) {
			$content = str_replace( '{' . $key . '}', $value, $content );
		}

		return $content;
	}
}
