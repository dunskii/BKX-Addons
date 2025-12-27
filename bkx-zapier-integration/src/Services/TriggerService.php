<?php
/**
 * Trigger Service for Zapier Integration
 *
 * @package BookingX\ZapierIntegration
 * @since   1.0.0
 */

namespace BookingX\ZapierIntegration\Services;

use BookingX\ZapierIntegration\ZapierIntegrationAddon;

/**
 * Class TriggerService
 *
 * Handles BookingX event triggers for Zapier.
 *
 * @since 1.0.0
 */
class TriggerService {

	/**
	 * Addon instance.
	 *
	 * @var ZapierIntegrationAddon
	 */
	private ZapierIntegrationAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param ZapierIntegrationAddon $addon Addon instance.
	 */
	public function __construct( ZapierIntegrationAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Get available triggers.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_available_triggers(): array {
		return array(
			array(
				'key'         => 'booking_created',
				'label'       => __( 'New Booking', 'bkx-zapier-integration' ),
				'description' => __( 'Triggers when a new booking is created.', 'bkx-zapier-integration' ),
			),
			array(
				'key'         => 'booking_updated',
				'label'       => __( 'Booking Updated', 'bkx-zapier-integration' ),
				'description' => __( 'Triggers when a booking is modified.', 'bkx-zapier-integration' ),
			),
			array(
				'key'         => 'booking_status_changed',
				'label'       => __( 'Booking Status Changed', 'bkx-zapier-integration' ),
				'description' => __( 'Triggers when a booking status changes.', 'bkx-zapier-integration' ),
			),
			array(
				'key'         => 'booking_cancelled',
				'label'       => __( 'Booking Cancelled', 'bkx-zapier-integration' ),
				'description' => __( 'Triggers when a booking is cancelled.', 'bkx-zapier-integration' ),
			),
			array(
				'key'         => 'booking_completed',
				'label'       => __( 'Booking Completed', 'bkx-zapier-integration' ),
				'description' => __( 'Triggers when a booking is completed.', 'bkx-zapier-integration' ),
			),
			array(
				'key'         => 'customer_created',
				'label'       => __( 'New Customer', 'bkx-zapier-integration' ),
				'description' => __( 'Triggers when a new customer makes their first booking.', 'bkx-zapier-integration' ),
			),
			array(
				'key'         => 'payment_received',
				'label'       => __( 'Payment Received', 'bkx-zapier-integration' ),
				'description' => __( 'Triggers when a payment is received for a booking.', 'bkx-zapier-integration' ),
			),
		);
	}

	/**
	 * Get sample data for a trigger.
	 *
	 * @since 1.0.0
	 * @param string $trigger Trigger key.
	 * @return array|\WP_Error
	 */
	public function get_sample_data( string $trigger ) {
		$samples = array(
			'booking_created'        => $this->get_booking_sample(),
			'booking_updated'        => $this->get_booking_sample(),
			'booking_status_changed' => $this->get_status_change_sample(),
			'booking_cancelled'      => $this->get_booking_sample(),
			'booking_completed'      => $this->get_booking_sample(),
			'customer_created'       => $this->get_customer_sample(),
			'payment_received'       => $this->get_payment_sample(),
		);

		if ( ! isset( $samples[ $trigger ] ) ) {
			return new \WP_Error(
				'invalid_trigger',
				__( 'Invalid trigger type', 'bkx-zapier-integration' )
			);
		}

		return $samples[ $trigger ];
	}

	/**
	 * Get sample booking data.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_booking_sample(): array {
		return array(
			'id'             => 123,
			'status'         => 'pending',
			'service_id'     => 10,
			'service_name'   => 'Haircut',
			'resource_id'    => 5,
			'resource_name'  => 'John Smith',
			'booking_date'   => '2024-12-28',
			'booking_time'   => '10:00',
			'duration'       => 60,
			'customer_email' => 'customer@example.com',
			'customer_name'  => 'Jane Doe',
			'customer_phone' => '+1234567890',
			'total'          => '50.00',
			'currency'       => 'USD',
			'notes'          => 'Please arrive 10 minutes early.',
			'created_at'     => '2024-12-28 09:00:00',
			'modified_at'    => '2024-12-28 09:00:00',
			'extras'         => array(
				array(
					'id'    => 1,
					'name'  => 'Deep Conditioning',
					'price' => '15.00',
				),
			),
		);
	}

	/**
	 * Get sample status change data.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_status_change_sample(): array {
		$booking = $this->get_booking_sample();

		return array_merge(
			$booking,
			array(
				'previous_status' => 'pending',
				'new_status'      => 'ack',
				'changed_at'      => '2024-12-28 09:15:00',
			)
		);
	}

	/**
	 * Get sample customer data.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_customer_sample(): array {
		return array(
			'email'            => 'customer@example.com',
			'name'             => 'Jane Doe',
			'phone'            => '+1234567890',
			'first_booking_id' => 123,
			'created_at'       => '2024-12-28 09:00:00',
		);
	}

	/**
	 * Get sample payment data.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_payment_sample(): array {
		return array(
			'booking_id'       => 123,
			'amount'           => '50.00',
			'currency'         => 'USD',
			'payment_method'   => 'stripe',
			'transaction_id'   => 'txn_1234567890',
			'customer_email'   => 'customer@example.com',
			'customer_name'    => 'Jane Doe',
			'service_name'     => 'Haircut',
			'payment_date'     => '2024-12-28 09:00:00',
		);
	}

	/**
	 * Handle booking created event.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function on_booking_created( int $booking_id, array $booking_data ): void {
		$data = $this->format_booking_data( $booking_id );
		$this->addon->get_webhook_service()->send( 'booking_created', $data );
	}

	/**
	 * Handle booking updated event.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function on_booking_updated( int $booking_id, array $booking_data ): void {
		$data = $this->format_booking_data( $booking_id );
		$this->addon->get_webhook_service()->send( 'booking_updated', $data );
	}

	/**
	 * Handle booking status changed event.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id      Booking ID.
	 * @param string $new_status      New status.
	 * @param string $previous_status Previous status.
	 * @return void
	 */
	public function on_booking_status_changed( int $booking_id, string $new_status, string $previous_status ): void {
		$data = $this->format_booking_data( $booking_id );

		$data['previous_status'] = str_replace( 'bkx-', '', $previous_status );
		$data['new_status']      = str_replace( 'bkx-', '', $new_status );
		$data['changed_at']      = current_time( 'mysql' );

		$this->addon->get_webhook_service()->send( 'booking_status_changed', $data );
	}

	/**
	 * Handle booking cancelled event.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function on_booking_cancelled( int $booking_id, array $booking_data ): void {
		$data = $this->format_booking_data( $booking_id );
		$this->addon->get_webhook_service()->send( 'booking_cancelled', $data );
	}

	/**
	 * Handle booking completed event.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function on_booking_completed( int $booking_id, array $booking_data ): void {
		$data = $this->format_booking_data( $booking_id );
		$this->addon->get_webhook_service()->send( 'booking_completed', $data );
	}

	/**
	 * Handle customer created event.
	 *
	 * @since 1.0.0
	 * @param string $email         Customer email.
	 * @param array  $customer_data Customer data.
	 * @return void
	 */
	public function on_customer_created( string $email, array $customer_data ): void {
		$data = array(
			'email'            => $email,
			'name'             => $customer_data['name'] ?? '',
			'phone'            => $customer_data['phone'] ?? '',
			'first_booking_id' => $customer_data['booking_id'] ?? null,
			'created_at'       => current_time( 'mysql' ),
		);

		$this->addon->get_webhook_service()->send( 'customer_created', $data );
	}

	/**
	 * Handle payment received event.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $payment_data Payment data.
	 * @return void
	 */
	public function on_payment_received( int $booking_id, array $payment_data ): void {
		$booking = get_post( $booking_id );

		$data = array(
			'booking_id'     => $booking_id,
			'amount'         => $payment_data['amount'] ?? '',
			'currency'       => $payment_data['currency'] ?? 'USD',
			'payment_method' => $payment_data['method'] ?? '',
			'transaction_id' => $payment_data['transaction_id'] ?? '',
			'customer_email' => get_post_meta( $booking_id, 'customer_email', true ),
			'customer_name'  => get_post_meta( $booking_id, 'customer_name', true ),
			'service_name'   => $this->get_service_name( $booking_id ),
			'payment_date'   => current_time( 'mysql' ),
		);

		$this->addon->get_webhook_service()->send( 'payment_received', $data );
	}

	/**
	 * Format booking data for webhook.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	private function format_booking_data( int $booking_id ): array {
		$booking = get_post( $booking_id );

		if ( ! $booking ) {
			return array();
		}

		$service_id  = get_post_meta( $booking_id, 'base_id', true );
		$resource_id = get_post_meta( $booking_id, 'seat_id', true );
		$service     = get_post( $service_id );
		$resource    = get_post( $resource_id );

		return array(
			'id'             => $booking_id,
			'status'         => str_replace( 'bkx-', '', $booking->post_status ),
			'service_id'     => $service_id,
			'service_name'   => $service ? $service->post_title : '',
			'resource_id'    => $resource_id,
			'resource_name'  => $resource ? $resource->post_title : '',
			'booking_date'   => get_post_meta( $booking_id, 'booking_date', true ),
			'booking_time'   => get_post_meta( $booking_id, 'booking_time', true ),
			'duration'       => get_post_meta( $service_id, 'base_time', true ),
			'customer_email' => get_post_meta( $booking_id, 'customer_email', true ),
			'customer_name'  => get_post_meta( $booking_id, 'customer_name', true ),
			'customer_phone' => get_post_meta( $booking_id, 'customer_phone', true ),
			'total'          => get_post_meta( $booking_id, 'booking_total', true ),
			'currency'       => get_option( 'bkx_currency', 'USD' ),
			'notes'          => get_post_meta( $booking_id, 'booking_notes', true ),
			'created_at'     => $booking->post_date,
			'modified_at'    => $booking->post_modified,
			'extras'         => $this->get_booking_extras( $booking_id ),
		);
	}

	/**
	 * Get booking extras.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	private function get_booking_extras( int $booking_id ): array {
		$extras_ids = get_post_meta( $booking_id, 'booking_extras', true );

		if ( empty( $extras_ids ) || ! is_array( $extras_ids ) ) {
			return array();
		}

		$extras = array();

		foreach ( $extras_ids as $extra_id ) {
			$extra = get_post( $extra_id );
			if ( $extra ) {
				$extras[] = array(
					'id'    => $extra_id,
					'name'  => $extra->post_title,
					'price' => get_post_meta( $extra_id, 'extra_price', true ),
				);
			}
		}

		return $extras;
	}

	/**
	 * Get service name for a booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @return string
	 */
	private function get_service_name( int $booking_id ): string {
		$service_id = get_post_meta( $booking_id, 'base_id', true );
		$service    = get_post( $service_id );

		return $service ? $service->post_title : '';
	}
}
