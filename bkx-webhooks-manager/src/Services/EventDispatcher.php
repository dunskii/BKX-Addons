<?php
/**
 * Event Dispatcher Service.
 *
 * @package BookingX\WebhooksManager
 */

namespace BookingX\WebhooksManager\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class EventDispatcher
 *
 * Dispatches events to subscribed webhooks.
 */
class EventDispatcher {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'bkx_webhooks_manager_settings', array() );
	}

	/**
	 * Dispatch an event to all subscribed webhooks.
	 *
	 * @param string $event_type Event type.
	 * @param array  $data       Event data.
	 * @param array  $context    Additional context.
	 * @return array Results of delivery attempts.
	 */
	public function dispatch( string $event_type, array $data, array $context = array() ): array {
		if ( empty( $this->settings['enabled'] ) ) {
			return array();
		}

		$addon           = \BookingX\WebhooksManager\WebhooksManagerAddon::get_instance();
		$webhook_manager = $addon->get_service( 'webhook_manager' );
		$delivery_service = $addon->get_service( 'delivery_service' );

		// Get webhooks subscribed to this event.
		$webhooks = $webhook_manager->get_by_event( $event_type );

		if ( empty( $webhooks ) ) {
			return array();
		}

		$results = array();

		foreach ( $webhooks as $webhook ) {
			// Check if webhook is within active window.
			if ( ! $webhook_manager->is_within_active_window( $webhook ) ) {
				continue;
			}

			// Build payload.
			$payload = $this->build_payload( $event_type, $data, $context, $webhook );

			// Check conditions.
			if ( ! $webhook_manager->evaluate_conditions( $webhook, $payload ) ) {
				continue;
			}

			// Create delivery record.
			$delivery_id = $delivery_service->create(
				$webhook->id,
				$event_type,
				$payload,
				$context['event_id'] ?? ''
			);

			if ( ! $delivery_id ) {
				continue;
			}

			// Dispatch based on settings.
			if ( ! empty( $this->settings['async_delivery'] ) ) {
				// Schedule for async delivery.
				$this->schedule_delivery( $delivery_id, $webhook->id );
				$results[ $webhook->id ] = array(
					'status'      => 'scheduled',
					'delivery_id' => $delivery_id,
				);
			} else {
				// Deliver immediately.
				$success = $delivery_service->deliver( $delivery_id, $webhook );
				$results[ $webhook->id ] = array(
					'status'      => $success ? 'delivered' : 'failed',
					'delivery_id' => $delivery_id,
				);
			}
		}

		return $results;
	}

	/**
	 * Build webhook payload.
	 *
	 * @param string $event_type Event type.
	 * @param array  $data       Event data.
	 * @param array  $context    Additional context.
	 * @param object $webhook    Webhook object.
	 * @return array Payload.
	 */
	private function build_payload( string $event_type, array $data, array $context, $webhook ): array {
		$payload = array(
			'event'      => $event_type,
			'created_at' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'data'       => $data,
		);

		// Add timestamp if enabled.
		if ( ! empty( $this->settings['include_timestamp'] ) ) {
			$payload['timestamp'] = time();
		}

		// Add API version.
		$payload['api_version'] = '1.0';

		// Add site info.
		$payload['site'] = array(
			'url'  => home_url(),
			'name' => get_bloginfo( 'name' ),
		);

		// Add context.
		if ( ! empty( $context ) ) {
			$payload['context'] = $context;
		}

		/**
		 * Filter the webhook payload before dispatch.
		 *
		 * @param array  $payload    The payload.
		 * @param string $event_type The event type.
		 * @param array  $data       The event data.
		 * @param object $webhook    The webhook object.
		 */
		return apply_filters( 'bkx_webhook_payload', $payload, $event_type, $data, $webhook );
	}

	/**
	 * Schedule delivery for async processing.
	 *
	 * @param int $delivery_id Delivery ID.
	 * @param int $webhook_id  Webhook ID.
	 */
	private function schedule_delivery( int $delivery_id, int $webhook_id ): void {
		// Use Action Scheduler if available.
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time(),
				'bkx_webhook_deliver',
				array(
					'delivery_id' => $delivery_id,
					'webhook_id'  => $webhook_id,
				),
				'bkx-webhooks'
			);
		} else {
			// Fall back to wp_schedule_single_event.
			wp_schedule_single_event(
				time(),
				'bkx_webhook_deliver',
				array( $delivery_id, $webhook_id )
			);
		}
	}

	/**
	 * Process scheduled delivery.
	 *
	 * @param int $delivery_id Delivery ID.
	 * @param int $webhook_id  Webhook ID.
	 */
	public function process_scheduled_delivery( int $delivery_id, int $webhook_id ): void {
		$addon           = \BookingX\WebhooksManager\WebhooksManagerAddon::get_instance();
		$webhook_manager = $addon->get_service( 'webhook_manager' );
		$delivery_service = $addon->get_service( 'delivery_service' );

		$webhook = $webhook_manager->get( $webhook_id );
		if ( ! $webhook ) {
			return;
		}

		$delivery_service->deliver( $delivery_id, $webhook );
	}

	/**
	 * Dispatch batch events.
	 *
	 * @param array $events Array of events with type and data.
	 * @return array Results.
	 */
	public function dispatch_batch( array $events ): array {
		$results = array();

		foreach ( $events as $event ) {
			$type    = $event['type'] ?? '';
			$data    = $event['data'] ?? array();
			$context = $event['context'] ?? array();

			if ( empty( $type ) ) {
				continue;
			}

			$results[ $type ] = $this->dispatch( $type, $data, $context );
		}

		return $results;
	}

	/**
	 * Get available BookingX events.
	 *
	 * @return array Events grouped by category.
	 */
	public function get_available_events(): array {
		return array(
			'booking'  => array(
				'booking.created'    => array(
					'label'       => __( 'Booking Created', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when a new booking is created.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_booking_created',
				),
				'booking.updated'    => array(
					'label'       => __( 'Booking Updated', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when a booking is updated.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_booking_updated',
				),
				'booking.cancelled'  => array(
					'label'       => __( 'Booking Cancelled', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when a booking is cancelled.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_booking_cancelled',
				),
				'booking.completed'  => array(
					'label'       => __( 'Booking Completed', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when a booking is marked complete.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_booking_completed',
				),
				'booking.reminder'   => array(
					'label'       => __( 'Booking Reminder', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when a booking reminder is sent.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_booking_reminder',
				),
			),
			'payment'  => array(
				'payment.completed'  => array(
					'label'       => __( 'Payment Completed', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when payment is successfully processed.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_payment_completed',
				),
				'payment.failed'     => array(
					'label'       => __( 'Payment Failed', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when a payment fails.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_payment_failed',
				),
				'payment.refunded'   => array(
					'label'       => __( 'Payment Refunded', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when a payment is refunded.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_payment_refunded',
				),
			),
			'customer' => array(
				'customer.created'   => array(
					'label'       => __( 'Customer Created', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when a new customer is created.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_customer_created',
				),
				'customer.updated'   => array(
					'label'       => __( 'Customer Updated', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when customer info is updated.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_customer_updated',
				),
			),
			'service'  => array(
				'service.created'    => array(
					'label'       => __( 'Service Created', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when a new service is created.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_service_created',
				),
				'service.updated'    => array(
					'label'       => __( 'Service Updated', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when a service is updated.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_service_updated',
				),
				'service.deleted'    => array(
					'label'       => __( 'Service Deleted', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when a service is deleted.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_service_deleted',
				),
			),
			'staff'    => array(
				'staff.created'      => array(
					'label'       => __( 'Staff Created', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when a staff member is created.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_staff_created',
				),
				'staff.updated'      => array(
					'label'       => __( 'Staff Updated', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when a staff member is updated.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_staff_updated',
				),
				'staff.availability' => array(
					'label'       => __( 'Staff Availability Changed', 'bkx-webhooks-manager' ),
					'description' => __( 'Triggered when staff availability changes.', 'bkx-webhooks-manager' ),
					'hook'        => 'bkx_staff_availability_changed',
				),
			),
		);
	}

	/**
	 * Register BookingX event listeners.
	 */
	public function register_event_listeners(): void {
		$events = $this->get_available_events();

		foreach ( $events as $category => $category_events ) {
			foreach ( $category_events as $event_type => $event_config ) {
				add_action(
					$event_config['hook'],
					function ( ...$args ) use ( $event_type, $category ) {
						$this->handle_event( $event_type, $category, $args );
					},
					99,
					10
				);
			}
		}

		// Register delivery handler.
		add_action( 'bkx_webhook_deliver', array( $this, 'process_scheduled_delivery' ), 10, 2 );
	}

	/**
	 * Handle an event from BookingX.
	 *
	 * @param string $event_type Event type.
	 * @param string $category   Event category.
	 * @param array  $args       Event arguments.
	 */
	private function handle_event( string $event_type, string $category, array $args ): void {
		$data = $this->prepare_event_data( $event_type, $category, $args );

		if ( empty( $data ) ) {
			return;
		}

		$this->dispatch( $event_type, $data );
	}

	/**
	 * Prepare event data based on event type.
	 *
	 * @param string $event_type Event type.
	 * @param string $category   Event category.
	 * @param array  $args       Event arguments.
	 * @return array Prepared data.
	 */
	private function prepare_event_data( string $event_type, string $category, array $args ): array {
		$data = array();

		switch ( $category ) {
			case 'booking':
				$data = $this->prepare_booking_data( $args );
				break;
			case 'payment':
				$data = $this->prepare_payment_data( $args );
				break;
			case 'customer':
				$data = $this->prepare_customer_data( $args );
				break;
			case 'service':
				$data = $this->prepare_service_data( $args );
				break;
			case 'staff':
				$data = $this->prepare_staff_data( $args );
				break;
		}

		/**
		 * Filter event data before dispatch.
		 *
		 * @param array  $data       The event data.
		 * @param string $event_type The event type.
		 * @param array  $args       The original arguments.
		 */
		return apply_filters( 'bkx_webhook_event_data', $data, $event_type, $args );
	}

	/**
	 * Prepare booking data.
	 *
	 * @param array $args Event arguments.
	 * @return array Booking data.
	 */
	private function prepare_booking_data( array $args ): array {
		$booking_id = $args[0] ?? 0;
		if ( ! $booking_id ) {
			return array();
		}

		$booking = get_post( $booking_id );
		if ( ! $booking ) {
			return array();
		}

		$meta = get_post_meta( $booking_id );

		return array(
			'id'            => $booking_id,
			'status'        => $booking->post_status,
			'date'          => $meta['booking_date'][0] ?? '',
			'time'          => $meta['booking_time'][0] ?? '',
			'service_id'    => $meta['base_id'][0] ?? '',
			'staff_id'      => $meta['seat_id'][0] ?? '',
			'customer'      => array(
				'name'  => $meta['customer_name'][0] ?? '',
				'email' => $meta['customer_email'][0] ?? '',
				'phone' => $meta['customer_phone'][0] ?? '',
			),
			'total'         => $meta['booking_total'][0] ?? '',
			'created_at'    => $booking->post_date_gmt,
			'updated_at'    => $booking->post_modified_gmt,
		);
	}

	/**
	 * Prepare payment data.
	 *
	 * @param array $args Event arguments.
	 * @return array Payment data.
	 */
	private function prepare_payment_data( array $args ): array {
		$booking_id = $args[0] ?? 0;
		$payment_data = $args[1] ?? array();

		return array(
			'booking_id'    => $booking_id,
			'amount'        => $payment_data['amount'] ?? '',
			'currency'      => $payment_data['currency'] ?? 'USD',
			'method'        => $payment_data['method'] ?? '',
			'transaction_id' => $payment_data['transaction_id'] ?? '',
			'status'        => $payment_data['status'] ?? '',
		);
	}

	/**
	 * Prepare customer data.
	 *
	 * @param array $args Event arguments.
	 * @return array Customer data.
	 */
	private function prepare_customer_data( array $args ): array {
		$customer_id = $args[0] ?? 0;
		if ( ! $customer_id ) {
			return array();
		}

		$user = get_userdata( $customer_id );
		if ( ! $user ) {
			return array();
		}

		return array(
			'id'         => $customer_id,
			'email'      => $user->user_email,
			'first_name' => $user->first_name,
			'last_name'  => $user->last_name,
			'phone'      => get_user_meta( $customer_id, 'phone', true ),
			'created_at' => $user->user_registered,
		);
	}

	/**
	 * Prepare service data.
	 *
	 * @param array $args Event arguments.
	 * @return array Service data.
	 */
	private function prepare_service_data( array $args ): array {
		$service_id = $args[0] ?? 0;
		if ( ! $service_id ) {
			return array();
		}

		$service = get_post( $service_id );
		if ( ! $service ) {
			return array();
		}

		$meta = get_post_meta( $service_id );

		return array(
			'id'          => $service_id,
			'name'        => $service->post_title,
			'description' => $service->post_content,
			'price'       => $meta['base_price'][0] ?? '',
			'duration'    => $meta['base_time'][0] ?? '',
			'status'      => $service->post_status,
		);
	}

	/**
	 * Prepare staff data.
	 *
	 * @param array $args Event arguments.
	 * @return array Staff data.
	 */
	private function prepare_staff_data( array $args ): array {
		$staff_id = $args[0] ?? 0;
		if ( ! $staff_id ) {
			return array();
		}

		$staff = get_post( $staff_id );
		if ( ! $staff ) {
			return array();
		}

		$meta = get_post_meta( $staff_id );

		return array(
			'id'    => $staff_id,
			'name'  => $staff->post_title,
			'email' => $meta['seat_email'][0] ?? '',
			'phone' => $meta['seat_phone'][0] ?? '',
			'status' => $staff->post_status,
		);
	}

	/**
	 * Test webhook with sample data.
	 *
	 * @param int    $webhook_id Webhook ID.
	 * @param string $event_type Event type to test.
	 * @return array Test result.
	 */
	public function test_webhook( int $webhook_id, string $event_type = 'booking.created' ): array {
		$addon           = \BookingX\WebhooksManager\WebhooksManagerAddon::get_instance();
		$webhook_manager = $addon->get_service( 'webhook_manager' );
		$delivery_service = $addon->get_service( 'delivery_service' );

		$webhook = $webhook_manager->get( $webhook_id );
		if ( ! $webhook ) {
			return array(
				'success' => false,
				'error'   => __( 'Webhook not found.', 'bkx-webhooks-manager' ),
			);
		}

		// Generate sample data.
		$sample_data = $this->generate_sample_data( $event_type );

		$payload = $this->build_payload( $event_type, $sample_data, array( 'test' => true ), $webhook );

		// Create delivery record.
		$delivery_id = $delivery_service->create(
			$webhook->id,
			$event_type,
			$payload,
			'test_' . time()
		);

		if ( ! $delivery_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to create delivery record.', 'bkx-webhooks-manager' ),
			);
		}

		// Deliver immediately.
		$success = $delivery_service->deliver( $delivery_id, $webhook );

		$delivery = $delivery_service->get( $delivery_id );

		return array(
			'success'       => $success,
			'delivery_id'   => $delivery_id,
			'response_code' => $delivery->response_code,
			'response_time' => $delivery->response_time,
			'error'         => $delivery->error_message,
		);
	}

	/**
	 * Generate sample data for testing.
	 *
	 * @param string $event_type Event type.
	 * @return array Sample data.
	 */
	private function generate_sample_data( string $event_type ): array {
		$category = explode( '.', $event_type )[0] ?? 'booking';

		switch ( $category ) {
			case 'booking':
				return array(
					'id'         => 12345,
					'status'     => 'bkx-ack',
					'date'       => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
					'time'       => '10:00:00',
					'service_id' => 100,
					'staff_id'   => 50,
					'customer'   => array(
						'name'  => 'John Doe',
						'email' => 'john@example.com',
						'phone' => '+1234567890',
					),
					'total'      => '99.99',
					'created_at' => gmdate( 'Y-m-d H:i:s' ),
				);

			case 'payment':
				return array(
					'booking_id'     => 12345,
					'amount'         => '99.99',
					'currency'       => 'USD',
					'method'         => 'stripe',
					'transaction_id' => 'pi_' . bin2hex( random_bytes( 12 ) ),
					'status'         => 'completed',
				);

			case 'customer':
				return array(
					'id'         => 100,
					'email'      => 'customer@example.com',
					'first_name' => 'Jane',
					'last_name'  => 'Smith',
					'phone'      => '+1987654321',
					'created_at' => gmdate( 'Y-m-d H:i:s' ),
				);

			case 'service':
				return array(
					'id'          => 100,
					'name'        => 'Sample Service',
					'description' => 'This is a sample service for testing.',
					'price'       => '50.00',
					'duration'    => '60',
					'status'      => 'publish',
				);

			case 'staff':
				return array(
					'id'     => 50,
					'name'   => 'Staff Member',
					'email'  => 'staff@example.com',
					'phone'  => '+1122334455',
					'status' => 'publish',
				);

			default:
				return array( 'test' => true );
		}
	}
}
