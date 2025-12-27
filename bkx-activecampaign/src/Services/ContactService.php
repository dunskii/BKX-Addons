<?php
/**
 * Contact Service for ActiveCampaign
 *
 * @package BookingX\ActiveCampaign
 * @since   1.0.0
 */

namespace BookingX\ActiveCampaign\Services;

use BookingX\ActiveCampaign\ActiveCampaignAddon;

/**
 * Class ContactService
 *
 * Handles contact synchronization with ActiveCampaign.
 *
 * @since 1.0.0
 */
class ContactService {

	/**
	 * API instance.
	 *
	 * @var ActiveCampaignAPI
	 */
	private ActiveCampaignAPI $api;

	/**
	 * Addon instance.
	 *
	 * @var ActiveCampaignAddon
	 */
	private ActiveCampaignAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param ActiveCampaignAPI   $api   API instance.
	 * @param ActiveCampaignAddon $addon Addon instance.
	 */
	public function __construct( ActiveCampaignAPI $api, ActiveCampaignAddon $addon ) {
		$this->api   = $api;
		$this->addon = $addon;
	}

	/**
	 * Sync a contact with ActiveCampaign.
	 *
	 * Creates a new contact or updates existing one.
	 *
	 * @since 1.0.0
	 * @param string $email        Email address.
	 * @param array  $contact_data Additional contact data.
	 * @return int|\WP_Error Contact ID or error.
	 */
	public function sync_contact( string $email, array $contact_data = array() ) {
		// Check for existing contact.
		$existing = $this->api->get_contact_by_email( $email );

		$data = array_merge(
			array( 'email' => $email ),
			$contact_data
		);

		if ( $existing ) {
			// Update existing contact.
			return $this->api->update_contact( (int) $existing['id'], $data );
		}

		// Create new contact.
		return $this->api->create_contact( $data );
	}

	/**
	 * Add tag to contact.
	 *
	 * @since 1.0.0
	 * @param int    $contact_id Contact ID.
	 * @param string $tag_name   Tag name.
	 * @return bool|\WP_Error
	 */
	public function add_tag( int $contact_id, string $tag_name ) {
		return $this->api->add_tag( $contact_id, $tag_name );
	}

	/**
	 * Add contact to list.
	 *
	 * @since 1.0.0
	 * @param int $contact_id Contact ID.
	 * @param int $list_id    List ID.
	 * @return bool|\WP_Error
	 */
	public function add_to_list( int $contact_id, int $list_id ) {
		return $this->api->add_to_list( $contact_id, $list_id );
	}

	/**
	 * Add contact to automation.
	 *
	 * @since 1.0.0
	 * @param int $contact_id    Contact ID.
	 * @param int $automation_id Automation ID.
	 * @return bool|\WP_Error
	 */
	public function add_to_automation( int $contact_id, int $automation_id ) {
		return $this->api->add_to_automation( $contact_id, $automation_id );
	}

	/**
	 * Sync all contacts from bookings.
	 *
	 * @since 1.0.0
	 * @param int $batch_size Number of contacts per batch.
	 * @return array Results.
	 */
	public function sync_all_contacts( int $batch_size = 50 ): array {
		global $wpdb;

		$results = array(
			'synced'  => 0,
			'failed'  => 0,
			'skipped' => 0,
		);

		// Get unique customer emails from bookings.
		$emails = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE p.post_type = 'bkx_booking'
				AND pm.meta_key = 'customer_email'
				AND pm.meta_value != ''
				ORDER BY pm.meta_id DESC
				LIMIT %d",
				$batch_size
			)
		);

		foreach ( $emails as $email ) {
			// Get customer data from most recent booking.
			$booking_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT p.ID
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					WHERE p.post_type = 'bkx_booking'
					AND pm.meta_key = 'customer_email'
					AND pm.meta_value = %s
					ORDER BY p.post_date DESC
					LIMIT 1",
					$email
				)
			);

			if ( ! $booking_id ) {
				++$results['skipped'];
				continue;
			}

			$name  = get_post_meta( $booking_id, 'customer_name', true );
			$phone = get_post_meta( $booking_id, 'customer_phone', true );

			$contact_id = $this->sync_contact(
				$email,
				array(
					'firstName' => $this->get_first_name( $name ),
					'lastName'  => $this->get_last_name( $name ),
					'phone'     => $phone,
				)
			);

			if ( is_wp_error( $contact_id ) ) {
				++$results['failed'];
				$this->addon->log(
					sprintf( 'Failed to sync %s: %s', $email, $contact_id->get_error_message() ),
					'error'
				);
			} else {
				++$results['synced'];
			}
		}

		return $results;
	}

	/**
	 * Get first name from full name.
	 *
	 * @since 1.0.0
	 * @param string $full_name Full name.
	 * @return string
	 */
	private function get_first_name( string $full_name ): string {
		$parts = explode( ' ', trim( $full_name ), 2 );
		return $parts[0] ?? '';
	}

	/**
	 * Get last name from full name.
	 *
	 * @since 1.0.0
	 * @param string $full_name Full name.
	 * @return string
	 */
	private function get_last_name( string $full_name ): string {
		$parts = explode( ' ', trim( $full_name ), 2 );
		return $parts[1] ?? '';
	}
}
