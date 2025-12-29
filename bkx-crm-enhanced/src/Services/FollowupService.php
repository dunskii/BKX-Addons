<?php
/**
 * Followup Service.
 *
 * @package BookingX\CRM
 */

namespace BookingX\CRM\Services;

defined( 'ABSPATH' ) || exit;

/**
 * FollowupService class.
 */
class FollowupService {

	/**
	 * Customer service.
	 *
	 * @var CustomerService
	 */
	private $customers;

	/**
	 * Constructor.
	 *
	 * @param CustomerService $customers Customer service.
	 */
	public function __construct( CustomerService $customers ) {
		$this->customers = $customers;
	}

	/**
	 * Schedule a followup.
	 *
	 * @param array $data Followup data.
	 * @return int|WP_Error
	 */
	public function schedule( $data ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_followups';

		$required = array( 'customer_id', 'followup_type', 'scheduled_at' );

		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new \WP_Error( 'missing_field', sprintf(
					/* translators: %s: Field name */
					__( '%s is required.', 'bkx-crm' ),
					$field
				) );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $table, array(
			'customer_id'    => $data['customer_id'],
			'booking_id'     => $data['booking_id'] ?? null,
			'followup_type'  => $data['followup_type'],
			'channel'        => $data['channel'] ?? 'email',
			'scheduled_at'   => $data['scheduled_at'],
			'template_id'    => $data['template_id'] ?? null,
			'custom_message' => $data['custom_message'] ?? null,
			'created_by'     => $data['created_by'] ?? get_current_user_id(),
		) );

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to schedule followup.', 'bkx-crm' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Cancel a followup.
	 *
	 * @param int $followup_id Followup ID.
	 * @return bool
	 */
	public function cancel( $followup_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_followups';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->update(
			$table,
			array( 'status' => 'cancelled' ),
			array( 'id' => $followup_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Get customer followups.
	 *
	 * @param int $customer_id Customer ID.
	 * @return array
	 */
	public function get_customer_followups( $customer_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_followups';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE customer_id = %d ORDER BY scheduled_at DESC",
			$customer_id
		) );
	}

	/**
	 * Process pending followups.
	 */
	public function process_pending() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_followups';

		// Get due followups.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$followups = $wpdb->get_results(
			"SELECT f.*, c.email, c.first_name, c.last_name
			FROM {$table} f
			INNER JOIN {$wpdb->prefix}bkx_crm_customers c ON f.customer_id = c.id
			WHERE f.status = 'pending'
			AND f.scheduled_at <= NOW()
			LIMIT 50"
		);

		foreach ( $followups as $followup ) {
			$this->send_followup( $followup );
		}
	}

	/**
	 * Send a followup.
	 *
	 * @param object $followup Followup object.
	 * @return bool
	 */
	private function send_followup( $followup ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_crm_followups';

		if ( 'email' === $followup->channel ) {
			$result = $this->send_email_followup( $followup );
		} else {
			// SMS would be handled by SMS provider addon.
			$result = apply_filters( 'bkx_crm_send_sms_followup', false, $followup );
		}

		// Update status.
		$new_status = $result ? 'sent' : 'failed';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
			array(
				'status'  => $new_status,
				'sent_at' => $result ? current_time( 'mysql' ) : null,
			),
			array( 'id' => $followup->id )
		);

		// Log communication.
		if ( $result ) {
			$this->customers->log_communication( $followup->customer_id, array(
				'booking_id' => $followup->booking_id,
				'channel'    => $followup->channel,
				'direction'  => 'outbound',
				'subject'    => $this->get_followup_subject( $followup ),
				'content'    => $followup->custom_message,
				'status'     => 'sent',
			) );
		}

		return $result;
	}

	/**
	 * Send email followup.
	 *
	 * @param object $followup Followup object.
	 * @return bool
	 */
	private function send_email_followup( $followup ) {
		$to      = $followup->email;
		$subject = $this->get_followup_subject( $followup );
		$message = $this->get_followup_message( $followup );
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Get followup subject.
	 *
	 * @param object $followup Followup object.
	 * @return string
	 */
	private function get_followup_subject( $followup ) {
		$site_name = get_bloginfo( 'name' );

		switch ( $followup->followup_type ) {
			case 'thankyou':
				return sprintf(
					/* translators: %s: Site name */
					__( 'Thank you for your booking - %s', 'bkx-crm' ),
					$site_name
				);

			case 'feedback':
				return sprintf(
					/* translators: %s: Site name */
					__( 'How was your experience? - %s', 'bkx-crm' ),
					$site_name
				);

			case 'reminder':
				return sprintf(
					/* translators: %s: Site name */
					__( 'Booking Reminder - %s', 'bkx-crm' ),
					$site_name
				);

			case 'reengagement':
				return sprintf(
					/* translators: %s: Site name */
					__( 'We miss you! - %s', 'bkx-crm' ),
					$site_name
				);

			case 'birthday':
				return sprintf(
					/* translators: %s: Site name */
					__( 'Happy Birthday from %s!', 'bkx-crm' ),
					$site_name
				);

			default:
				return sprintf(
					/* translators: %s: Site name */
					__( 'Message from %s', 'bkx-crm' ),
					$site_name
				);
		}
	}

	/**
	 * Get followup message.
	 *
	 * @param object $followup Followup object.
	 * @return string
	 */
	private function get_followup_message( $followup ) {
		// Use custom message if provided.
		if ( ! empty( $followup->custom_message ) ) {
			return $this->apply_merge_tags( $followup->custom_message, $followup );
		}

		// Default templates.
		$template = $this->get_default_template( $followup->followup_type );

		return $this->apply_merge_tags( $template, $followup );
	}

	/**
	 * Get default template.
	 *
	 * @param string $type Followup type.
	 * @return string
	 */
	private function get_default_template( $type ) {
		$templates = array(
			'thankyou' => '
				<h2>Thank You for Your Booking!</h2>
				<p>Dear {{first_name}},</p>
				<p>Thank you for booking with us. We look forward to seeing you!</p>
				<p>If you have any questions, please don\'t hesitate to contact us.</p>
				<p>Best regards,<br>{{site_name}}</p>
			',

			'feedback' => '
				<h2>How Was Your Experience?</h2>
				<p>Dear {{first_name}},</p>
				<p>We hope you enjoyed your recent visit with us. Your feedback is valuable to help us improve our services.</p>
				<p>Would you mind taking a moment to let us know how we did?</p>
				<p>Thank you for your time!</p>
				<p>Best regards,<br>{{site_name}}</p>
			',

			'reminder' => '
				<h2>Booking Reminder</h2>
				<p>Dear {{first_name}},</p>
				<p>This is a friendly reminder about your upcoming booking.</p>
				<p>We look forward to seeing you soon!</p>
				<p>Best regards,<br>{{site_name}}</p>
			',

			'reengagement' => '
				<h2>We Miss You!</h2>
				<p>Dear {{first_name}},</p>
				<p>It\'s been a while since your last visit. We\'d love to see you again!</p>
				<p>Book your next appointment today and enjoy our great services.</p>
				<p>Best regards,<br>{{site_name}}</p>
			',

			'birthday' => '
				<h2>Happy Birthday!</h2>
				<p>Dear {{first_name}},</p>
				<p>Wishing you a wonderful birthday filled with joy and happiness!</p>
				<p>As a special treat, we have a surprise waiting for you. Come visit us soon!</p>
				<p>Best wishes,<br>{{site_name}}</p>
			',
		);

		return $templates[ $type ] ?? $templates['thankyou'];
	}

	/**
	 * Apply merge tags to message.
	 *
	 * @param string $message  Message with merge tags.
	 * @param object $followup Followup object.
	 * @return string
	 */
	private function apply_merge_tags( $message, $followup ) {
		$replacements = array(
			'{{first_name}}'  => $followup->first_name ?: __( 'Valued Customer', 'bkx-crm' ),
			'{{last_name}}'   => $followup->last_name ?: '',
			'{{full_name}}'   => trim( $followup->first_name . ' ' . $followup->last_name ) ?: __( 'Valued Customer', 'bkx-crm' ),
			'{{email}}'       => $followup->email,
			'{{site_name}}'   => get_bloginfo( 'name' ),
			'{{site_url}}'    => home_url(),
			'{{booking_id}}'  => $followup->booking_id ?: '',
		);

		// Add booking details if available.
		if ( $followup->booking_id ) {
			$booking_date = get_post_meta( $followup->booking_id, 'booking_date', true );
			$booking_time = get_post_meta( $followup->booking_id, 'booking_time', true );

			$replacements['{{booking_date}}'] = $booking_date ? wp_date( 'l, F j, Y', strtotime( $booking_date ) ) : '';
			$replacements['{{booking_time}}'] = $booking_time ? wp_date( 'g:i A', strtotime( $booking_time ) ) : '';
		}

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $message );
	}

	/**
	 * Check for upcoming birthdays.
	 */
	public function check_birthdays() {
		$settings = get_option( 'bkx_crm_settings', array() );

		if ( empty( $settings['birthday_enabled'] ) ) {
			return;
		}

		$days_before = $settings['birthday_days_before'] ?? 7;

		global $wpdb;

		$customers_table = $wpdb->prefix . 'bkx_crm_customers';
		$followups_table = $wpdb->prefix . 'bkx_crm_followups';

		// Find customers with birthdays in X days.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$customers = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.*
			FROM {$customers_table} c
			WHERE c.date_of_birth IS NOT NULL
			AND c.status = 'active'
			AND DAYOFYEAR(c.date_of_birth) = DAYOFYEAR(DATE_ADD(CURDATE(), INTERVAL %d DAY))
			AND c.id NOT IN (
				SELECT customer_id
				FROM {$followups_table}
				WHERE followup_type = 'birthday'
				AND YEAR(scheduled_at) = YEAR(CURDATE())
			)",
			$days_before
		) );

		foreach ( $customers as $customer ) {
			// Calculate actual birthday date this year.
			$birthday = date( 'Y' ) . date( '-m-d', strtotime( $customer->date_of_birth ) );

			$this->schedule( array(
				'customer_id'   => $customer->id,
				'followup_type' => 'birthday',
				'channel'       => 'email',
				'scheduled_at'  => $birthday . ' 09:00:00',
			) );
		}
	}
}
