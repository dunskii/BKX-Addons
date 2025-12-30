<?php
/**
 * Trigger Handler Service for IFTTT Integration.
 *
 * Handles booking event triggers to send to IFTTT.
 *
 * @package BookingX\IFTTT\Services
 */

namespace BookingX\IFTTT\Services;

defined( 'ABSPATH' ) || exit;

/**
 * TriggerHandler class.
 */
class TriggerHandler {

	/**
	 * Parent addon instance.
	 *
	 * @var \BookingX\IFTTT\IFTTTAddon
	 */
	private $addon;

	/**
	 * Available triggers.
	 *
	 * @var array
	 */
	private $triggers = array();

	/**
	 * Constructor.
	 *
	 * @param \BookingX\IFTTT\IFTTTAddon $addon Parent addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
		$this->register_triggers();
	}

	/**
	 * Initialize hooks.
	 */
	public function init() {
		// Booking lifecycle hooks.
		add_action( 'bkx_booking_created', array( $this, 'on_booking_created' ), 10, 2 );
		add_action( 'bkx_booking_confirmed', array( $this, 'on_booking_confirmed' ), 10, 2 );
		add_action( 'bkx_booking_cancelled', array( $this, 'on_booking_cancelled' ), 10, 2 );
		add_action( 'bkx_booking_completed', array( $this, 'on_booking_completed' ), 10, 2 );
		add_action( 'bkx_booking_reminder', array( $this, 'on_booking_reminder' ), 10, 2 );

		// Additional triggers.
		add_action( 'bkx_payment_received', array( $this, 'on_payment_received' ), 10, 3 );
		add_action( 'bkx_booking_rescheduled', array( $this, 'on_booking_rescheduled' ), 10, 3 );
	}

	/**
	 * Register available triggers.
	 */
	private function register_triggers() {
		$this->triggers = array(
			'booking_created'     => array(
				'slug'        => 'booking_created',
				'name'        => __( 'New Booking Created', 'bkx-ifttt' ),
				'description' => __( 'Fires when a new booking is created.', 'bkx-ifttt' ),
				'fields'      => $this->get_booking_fields(),
			),
			'booking_confirmed'   => array(
				'slug'        => 'booking_confirmed',
				'name'        => __( 'Booking Confirmed', 'bkx-ifttt' ),
				'description' => __( 'Fires when a booking is confirmed.', 'bkx-ifttt' ),
				'fields'      => $this->get_booking_fields(),
			),
			'booking_cancelled'   => array(
				'slug'        => 'booking_cancelled',
				'name'        => __( 'Booking Cancelled', 'bkx-ifttt' ),
				'description' => __( 'Fires when a booking is cancelled.', 'bkx-ifttt' ),
				'fields'      => $this->get_booking_fields(),
			),
			'booking_completed'   => array(
				'slug'        => 'booking_completed',
				'name'        => __( 'Booking Completed', 'bkx-ifttt' ),
				'description' => __( 'Fires when a booking is marked as completed.', 'bkx-ifttt' ),
				'fields'      => $this->get_booking_fields(),
			),
			'booking_reminder'    => array(
				'slug'        => 'booking_reminder',
				'name'        => __( 'Booking Reminder', 'bkx-ifttt' ),
				'description' => __( 'Fires when a booking reminder is due.', 'bkx-ifttt' ),
				'fields'      => $this->get_booking_fields(),
			),
			'payment_received'    => array(
				'slug'        => 'payment_received',
				'name'        => __( 'Payment Received', 'bkx-ifttt' ),
				'description' => __( 'Fires when payment is received for a booking.', 'bkx-ifttt' ),
				'fields'      => array_merge(
					$this->get_booking_fields(),
					array(
						array(
							'slug' => 'payment_amount',
							'name' => __( 'Payment Amount', 'bkx-ifttt' ),
							'type' => 'number',
						),
						array(
							'slug' => 'payment_method',
							'name' => __( 'Payment Method', 'bkx-ifttt' ),
							'type' => 'string',
						),
						array(
							'slug' => 'transaction_id',
							'name' => __( 'Transaction ID', 'bkx-ifttt' ),
							'type' => 'string',
						),
					)
				),
			),
			'booking_rescheduled' => array(
				'slug'        => 'booking_rescheduled',
				'name'        => __( 'Booking Rescheduled', 'bkx-ifttt' ),
				'description' => __( 'Fires when a booking is rescheduled.', 'bkx-ifttt' ),
				'fields'      => array_merge(
					$this->get_booking_fields(),
					array(
						array(
							'slug' => 'old_date',
							'name' => __( 'Previous Date', 'bkx-ifttt' ),
							'type' => 'datetime',
						),
						array(
							'slug' => 'old_time',
							'name' => __( 'Previous Time', 'bkx-ifttt' ),
							'type' => 'string',
						),
					)
				),
			),
		);

		/**
		 * Filter available IFTTT triggers.
		 *
		 * @param array $triggers Registered triggers.
		 */
		$this->triggers = apply_filters( 'bkx_ifttt_triggers', $this->triggers );
	}

	/**
	 * Get standard booking fields.
	 *
	 * @return array
	 */
	private function get_booking_fields() {
		return array(
			array(
				'slug' => 'booking_id',
				'name' => __( 'Booking ID', 'bkx-ifttt' ),
				'type' => 'number',
			),
			array(
				'slug' => 'customer_name',
				'name' => __( 'Customer Name', 'bkx-ifttt' ),
				'type' => 'string',
			),
			array(
				'slug' => 'customer_email',
				'name' => __( 'Customer Email', 'bkx-ifttt' ),
				'type' => 'string',
			),
			array(
				'slug' => 'customer_phone',
				'name' => __( 'Customer Phone', 'bkx-ifttt' ),
				'type' => 'string',
			),
			array(
				'slug' => 'service_name',
				'name' => __( 'Service Name', 'bkx-ifttt' ),
				'type' => 'string',
			),
			array(
				'slug' => 'staff_name',
				'name' => __( 'Staff Name', 'bkx-ifttt' ),
				'type' => 'string',
			),
			array(
				'slug' => 'booking_date',
				'name' => __( 'Booking Date', 'bkx-ifttt' ),
				'type' => 'datetime',
			),
			array(
				'slug' => 'booking_time',
				'name' => __( 'Booking Time', 'bkx-ifttt' ),
				'type' => 'string',
			),
			array(
				'slug' => 'duration',
				'name' => __( 'Duration (minutes)', 'bkx-ifttt' ),
				'type' => 'number',
			),
			array(
				'slug' => 'total_amount',
				'name' => __( 'Total Amount', 'bkx-ifttt' ),
				'type' => 'number',
			),
			array(
				'slug' => 'status',
				'name' => __( 'Booking Status', 'bkx-ifttt' ),
				'type' => 'string',
			),
			array(
				'slug' => 'notes',
				'name' => __( 'Customer Notes', 'bkx-ifttt' ),
				'type' => 'string',
			),
			array(
				'slug' => 'booking_url',
				'name' => __( 'Booking URL', 'bkx-ifttt' ),
				'type' => 'string',
			),
			array(
				'slug' => 'created_at',
				'name' => __( 'Created At', 'bkx-ifttt' ),
				'type' => 'datetime',
			),
		);
	}

	/**
	 * Get all registered triggers.
	 *
	 * @return array
	 */
	public function get_triggers() {
		return $this->triggers;
	}

	/**
	 * Get a specific trigger.
	 *
	 * @param string $trigger_slug Trigger slug.
	 * @return array|null
	 */
	public function get_trigger( $trigger_slug ) {
		return $this->triggers[ $trigger_slug ] ?? null;
	}

	/**
	 * Check if trigger is enabled.
	 *
	 * @param string $trigger_slug Trigger slug.
	 * @return bool
	 */
	public function is_trigger_enabled( $trigger_slug ) {
		$triggers = $this->addon->get_setting( 'triggers', array() );
		return ! empty( $triggers[ $trigger_slug ] );
	}

	/**
	 * Handle booking created event.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_created( $booking_id, $booking_data ) {
		if ( ! $this->is_trigger_enabled( 'booking_created' ) ) {
			return;
		}

		$payload = $this->prepare_booking_payload( $booking_id, $booking_data );
		$this->fire_trigger( 'booking_created', $payload );
	}

	/**
	 * Handle booking confirmed event.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_confirmed( $booking_id, $booking_data ) {
		if ( ! $this->is_trigger_enabled( 'booking_confirmed' ) ) {
			return;
		}

		$payload = $this->prepare_booking_payload( $booking_id, $booking_data );
		$this->fire_trigger( 'booking_confirmed', $payload );
	}

	/**
	 * Handle booking cancelled event.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_cancelled( $booking_id, $booking_data ) {
		if ( ! $this->is_trigger_enabled( 'booking_cancelled' ) ) {
			return;
		}

		$payload = $this->prepare_booking_payload( $booking_id, $booking_data );
		$this->fire_trigger( 'booking_cancelled', $payload );
	}

	/**
	 * Handle booking completed event.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_completed( $booking_id, $booking_data ) {
		if ( ! $this->is_trigger_enabled( 'booking_completed' ) ) {
			return;
		}

		$payload = $this->prepare_booking_payload( $booking_id, $booking_data );
		$this->fire_trigger( 'booking_completed', $payload );
	}

	/**
	 * Handle booking reminder event.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function on_booking_reminder( $booking_id, $booking_data ) {
		if ( ! $this->is_trigger_enabled( 'booking_reminder' ) ) {
			return;
		}

		$payload = $this->prepare_booking_payload( $booking_id, $booking_data );
		$this->fire_trigger( 'booking_reminder', $payload );
	}

	/**
	 * Handle payment received event.
	 *
	 * @param int    $booking_id     Booking ID.
	 * @param float  $amount         Payment amount.
	 * @param string $transaction_id Transaction ID.
	 */
	public function on_payment_received( $booking_id, $amount, $transaction_id ) {
		if ( ! $this->is_trigger_enabled( 'payment_received' ) ) {
			return;
		}

		$booking_data = $this->get_booking_data( $booking_id );
		$payload      = $this->prepare_booking_payload( $booking_id, $booking_data );

		$payload['payment_amount']  = $amount;
		$payload['payment_method']  = get_post_meta( $booking_id, 'payment_method', true );
		$payload['transaction_id']  = $transaction_id;

		$this->fire_trigger( 'payment_received', $payload );
	}

	/**
	 * Handle booking rescheduled event.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $old_date   Previous date.
	 * @param string $old_time   Previous time.
	 */
	public function on_booking_rescheduled( $booking_id, $old_date, $old_time ) {
		if ( ! $this->is_trigger_enabled( 'booking_rescheduled' ) ) {
			return;
		}

		$booking_data = $this->get_booking_data( $booking_id );
		$payload      = $this->prepare_booking_payload( $booking_id, $booking_data );

		$payload['old_date'] = $old_date;
		$payload['old_time'] = $old_time;

		$this->fire_trigger( 'booking_rescheduled', $payload );
	}

	/**
	 * Get booking data.
	 *
	 * @param int $booking_id Booking ID.
	 * @return array
	 */
	private function get_booking_data( $booking_id ) {
		$booking = get_post( $booking_id );
		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return array();
		}

		return array(
			'booking_id'     => $booking_id,
			'customer_name'  => get_post_meta( $booking_id, 'customer_name', true ),
			'customer_email' => get_post_meta( $booking_id, 'customer_email', true ),
			'customer_phone' => get_post_meta( $booking_id, 'customer_phone', true ),
			'seat_id'        => get_post_meta( $booking_id, 'seat_id', true ),
			'base_id'        => get_post_meta( $booking_id, 'base_id', true ),
			'booking_date'   => get_post_meta( $booking_id, 'booking_date', true ),
			'booking_time'   => get_post_meta( $booking_id, 'booking_time', true ),
			'total_amount'   => get_post_meta( $booking_id, 'total_amount', true ),
			'status'         => $booking->post_status,
		);
	}

	/**
	 * Prepare booking payload for IFTTT.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return array
	 */
	private function prepare_booking_payload( $booking_id, $booking_data ) {
		// Get service and staff names.
		$service_name = '';
		$staff_name   = '';

		if ( ! empty( $booking_data['base_id'] ) ) {
			$service = get_post( $booking_data['base_id'] );
			if ( $service ) {
				$service_name = $service->post_title;
			}
		}

		if ( ! empty( $booking_data['seat_id'] ) ) {
			$staff = get_post( $booking_data['seat_id'] );
			if ( $staff ) {
				$staff_name = $staff->post_title;
			}
		}

		// Calculate duration.
		$duration = 0;
		if ( ! empty( $booking_data['base_id'] ) ) {
			$duration = (int) get_post_meta( $booking_data['base_id'], 'base_time', true );
		}

		// Build booking URL.
		$booking_url = admin_url( 'post.php?post=' . $booking_id . '&action=edit' );

		// Map status to human-readable.
		$status_map = array(
			'bkx-pending'   => __( 'Pending', 'bkx-ifttt' ),
			'bkx-ack'       => __( 'Confirmed', 'bkx-ifttt' ),
			'bkx-completed' => __( 'Completed', 'bkx-ifttt' ),
			'bkx-cancelled' => __( 'Cancelled', 'bkx-ifttt' ),
			'bkx-missed'    => __( 'Missed', 'bkx-ifttt' ),
		);

		$status = $booking_data['status'] ?? '';
		$status_label = $status_map[ $status ] ?? $status;

		return array(
			'booking_id'     => $booking_id,
			'customer_name'  => $booking_data['customer_name'] ?? '',
			'customer_email' => $booking_data['customer_email'] ?? '',
			'customer_phone' => $booking_data['customer_phone'] ?? '',
			'service_name'   => $service_name,
			'staff_name'     => $staff_name,
			'booking_date'   => $booking_data['booking_date'] ?? '',
			'booking_time'   => $booking_data['booking_time'] ?? '',
			'duration'       => $duration,
			'total_amount'   => floatval( $booking_data['total_amount'] ?? 0 ),
			'status'         => $status_label,
			'notes'          => $booking_data['notes'] ?? '',
			'booking_url'    => $booking_url,
			'created_at'     => get_the_date( 'c', $booking_id ),
		);
	}

	/**
	 * Fire a trigger to all registered webhooks.
	 *
	 * @param string $trigger_slug Trigger slug.
	 * @param array  $payload      Trigger payload.
	 */
	public function fire_trigger( $trigger_slug, $payload ) {
		$webhook_manager = $this->addon->get_service( 'webhook_manager' );
		if ( ! $webhook_manager ) {
			return;
		}

		// Add trigger metadata.
		$payload['meta'] = array(
			'id'        => wp_generate_uuid4(),
			'timestamp' => time(),
			'trigger'   => $trigger_slug,
		);

		// Get webhooks for this trigger.
		$webhooks = $webhook_manager->get_webhooks_for_trigger( $trigger_slug );

		foreach ( $webhooks as $webhook ) {
			$webhook_manager->send_webhook( $webhook, $payload );
		}

		// Log the trigger.
		$this->log_trigger( $trigger_slug, $payload, count( $webhooks ) );

		/**
		 * Action after trigger is fired.
		 *
		 * @param string $trigger_slug Trigger slug.
		 * @param array  $payload      Trigger payload.
		 * @param int    $webhook_count Number of webhooks notified.
		 */
		do_action( 'bkx_ifttt_trigger_fired', $trigger_slug, $payload, count( $webhooks ) );
	}

	/**
	 * Log trigger execution.
	 *
	 * @param string $trigger_slug  Trigger slug.
	 * @param array  $payload       Trigger payload.
	 * @param int    $webhook_count Number of webhooks notified.
	 */
	private function log_trigger( $trigger_slug, $payload, $webhook_count ) {
		if ( ! $this->addon->get_setting( 'log_requests', false ) ) {
			return;
		}

		$logs = get_option( 'bkx_ifttt_trigger_logs', array() );

		$logs[] = array(
			'timestamp'     => current_time( 'mysql' ),
			'trigger'       => $trigger_slug,
			'booking_id'    => $payload['booking_id'] ?? 0,
			'webhook_count' => $webhook_count,
		);

		// Keep only last 100 logs.
		$logs = array_slice( $logs, -100 );

		update_option( 'bkx_ifttt_trigger_logs', $logs );
	}

	/**
	 * Get trigger fields for IFTTT service config.
	 *
	 * @param string $trigger_slug Trigger slug.
	 * @return array
	 */
	public function get_trigger_fields( $trigger_slug ) {
		$trigger = $this->get_trigger( $trigger_slug );
		if ( ! $trigger ) {
			return array();
		}

		return $trigger['fields'];
	}

	/**
	 * Generate sample data for a trigger.
	 *
	 * @param string $trigger_slug Trigger slug.
	 * @return array
	 */
	public function get_sample_data( $trigger_slug ) {
		return array(
			'booking_id'     => 123,
			'customer_name'  => 'John Doe',
			'customer_email' => 'john@example.com',
			'customer_phone' => '+1234567890',
			'service_name'   => 'Haircut',
			'staff_name'     => 'Jane Smith',
			'booking_date'   => gmdate( 'Y-m-d', strtotime( '+1 day' ) ),
			'booking_time'   => '10:00',
			'duration'       => 60,
			'total_amount'   => 50.00,
			'status'         => 'Confirmed',
			'notes'          => 'Please call upon arrival',
			'booking_url'    => admin_url( 'post.php?post=123&action=edit' ),
			'created_at'     => gmdate( 'c' ),
			'meta'           => array(
				'id'        => wp_generate_uuid4(),
				'timestamp' => time(),
				'trigger'   => $trigger_slug,
			),
		);
	}
}
