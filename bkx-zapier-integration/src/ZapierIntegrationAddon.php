<?php
/**
 * Zapier Integration Addon Main Class
 *
 * @package BookingX\ZapierIntegration
 * @since   1.0.0
 */

namespace BookingX\ZapierIntegration;

use BookingX\AddonSDK\Abstracts\AbstractIntegration;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\AddonSDK\Traits\HasDatabase;
use BookingX\AddonSDK\Traits\HasRestApi;
use BookingX\AddonSDK\Traits\HasWebhooks;
use BookingX\ZapierIntegration\Services\WebhookService;
use BookingX\ZapierIntegration\Services\TriggerService;
use BookingX\ZapierIntegration\Admin\SettingsPage;

/**
 * Class ZapierIntegrationAddon
 *
 * @since 1.0.0
 */
class ZapierIntegrationAddon extends AbstractIntegration {

	use HasSettings;
	use HasLicense;
	use HasDatabase;
	use HasRestApi;
	use HasWebhooks;

	/**
	 * Addon name.
	 *
	 * @var string
	 */
	protected string $addon_name = 'Zapier Integration';

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected string $addon_slug = 'bkx-zapier-integration';

	/**
	 * Addon version.
	 *
	 * @var string
	 */
	protected string $version = BKX_ZAPIER_VERSION;

	/**
	 * Webhook service instance.
	 *
	 * @var WebhookService
	 */
	private WebhookService $webhook_service;

	/**
	 * Trigger service instance.
	 *
	 * @var TriggerService
	 */
	private TriggerService $trigger_service;

	/**
	 * Settings page instance.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings_page;

	/**
	 * Get integration type.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_integration_type(): string {
		return 'automation';
	}

	/**
	 * Get integration name.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_integration_name(): string {
		return 'Zapier';
	}

	/**
	 * Check if integration is connected.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function is_connected(): bool {
		$subscriptions = $this->get_setting( 'webhook_subscriptions', array() );
		return ! empty( $subscriptions );
	}

	/**
	 * Connect to integration.
	 *
	 * @since 1.0.0
	 * @param array $credentials Credentials array.
	 * @return bool|\WP_Error
	 */
	public function connect( array $credentials ) {
		// Zapier uses webhook subscriptions, not traditional connection
		return true;
	}

	/**
	 * Disconnect from integration.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function disconnect(): bool {
		// Clear all webhook subscriptions
		$this->update_setting( 'webhook_subscriptions', array() );
		return true;
	}

	/**
	 * Boot the addon after initialization.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function boot(): void {
		// Initialize services.
		$this->webhook_service = new WebhookService( $this );
		$this->trigger_service = new TriggerService( $this );

		// Initialize admin.
		if ( is_admin() ) {
			$this->settings_page = new SettingsPage( $this );
		}

		// Register hooks.
		$this->register_hooks();

		// Register REST API routes.
		$this->register_rest_routes();
	}

	/**
	 * Register hooks for BookingX events.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_hooks(): void {
		// Booking created.
		add_action( 'bkx_booking_created', array( $this->trigger_service, 'on_booking_created' ), 10, 2 );

		// Booking updated.
		add_action( 'bkx_booking_updated', array( $this->trigger_service, 'on_booking_updated' ), 10, 2 );

		// Booking status changed.
		add_action( 'bkx_booking_status_changed', array( $this->trigger_service, 'on_booking_status_changed' ), 10, 3 );

		// Booking cancelled.
		add_action( 'bkx_booking_cancelled', array( $this->trigger_service, 'on_booking_cancelled' ), 10, 2 );

		// Booking completed.
		add_action( 'bkx_booking_completed', array( $this->trigger_service, 'on_booking_completed' ), 10, 2 );

		// Customer created.
		add_action( 'bkx_customer_created', array( $this->trigger_service, 'on_customer_created' ), 10, 2 );

		// Payment received.
		add_action( 'bkx_payment_received', array( $this->trigger_service, 'on_payment_received' ), 10, 2 );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function register_rest_routes(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_routes(): void {
		$namespace = 'bookingx/v1/zapier';

		// Subscribe to webhook.
		register_rest_route(
			$namespace,
			'/subscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_subscribe' ),
				'permission_callback' => array( $this, 'check_api_key' ),
			)
		);

		// Unsubscribe from webhook.
		register_rest_route(
			$namespace,
			'/unsubscribe',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_unsubscribe' ),
				'permission_callback' => array( $this, 'check_api_key' ),
			)
		);

		// List available triggers.
		register_rest_route(
			$namespace,
			'/triggers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_triggers' ),
				'permission_callback' => array( $this, 'check_api_key' ),
			)
		);

		// Sample data for triggers.
		register_rest_route(
			$namespace,
			'/triggers/(?P<trigger>[a-z_]+)/sample',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_trigger_sample' ),
				'permission_callback' => array( $this, 'check_api_key' ),
			)
		);

		// Actions.
		register_rest_route(
			$namespace,
			'/bookings',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_booking' ),
				'permission_callback' => array( $this, 'check_api_key' ),
			)
		);

		register_rest_route(
			$namespace,
			'/bookings/(?P<id>\d+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_booking' ),
				'permission_callback' => array( $this, 'check_api_key' ),
			)
		);

		register_rest_route(
			$namespace,
			'/bookings/(?P<id>\d+)',
			array(
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_booking' ),
				'permission_callback' => array( $this, 'check_api_key' ),
			)
		);

		register_rest_route(
			$namespace,
			'/bookings/(?P<id>\d+)/cancel',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'cancel_booking' ),
				'permission_callback' => array( $this, 'check_api_key' ),
			)
		);

		// Customers.
		register_rest_route(
			$namespace,
			'/customers',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_customers' ),
				'permission_callback' => array( $this, 'check_api_key' ),
			)
		);

		register_rest_route(
			$namespace,
			'/services',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_services' ),
				'permission_callback' => array( $this, 'check_api_key' ),
			)
		);

		register_rest_route(
			$namespace,
			'/resources',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_resources' ),
				'permission_callback' => array( $this, 'check_api_key' ),
			)
		);
	}

	/**
	 * Check API key permission.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function check_api_key( \WP_REST_Request $request ) {
		$api_key = $request->get_header( 'X-API-Key' );

		if ( empty( $api_key ) ) {
			$api_key = $request->get_param( 'api_key' );
		}

		$stored_key = get_option( 'bkx_zapier_api_key', '' );

		if ( empty( $stored_key ) || ! hash_equals( $stored_key, $api_key ) ) {
			return new \WP_Error(
				'unauthorized',
				__( 'Invalid API key', 'bkx-zapier-integration' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Handle webhook subscription.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_subscribe( \WP_REST_Request $request ) {
		$target_url = $request->get_param( 'target_url' );
		$trigger    = $request->get_param( 'trigger' );

		if ( empty( $target_url ) || empty( $trigger ) ) {
			return new \WP_Error(
				'missing_params',
				__( 'Missing target_url or trigger', 'bkx-zapier-integration' ),
				array( 'status' => 400 )
			);
		}

		$subscription_id = $this->webhook_service->subscribe( $trigger, $target_url );

		if ( is_wp_error( $subscription_id ) ) {
			return $subscription_id;
		}

		$this->log( sprintf( 'Zapier subscribed to %s: %s', $trigger, $target_url ) );

		return new \WP_REST_Response(
			array(
				'id'      => $subscription_id,
				'trigger' => $trigger,
				'url'     => $target_url,
			),
			201
		);
	}

	/**
	 * Handle webhook unsubscription.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_unsubscribe( \WP_REST_Request $request ) {
		$subscription_id = $request->get_param( 'id' );

		if ( empty( $subscription_id ) ) {
			return new \WP_Error(
				'missing_id',
				__( 'Missing subscription ID', 'bkx-zapier-integration' ),
				array( 'status' => 400 )
			);
		}

		$result = $this->webhook_service->unsubscribe( $subscription_id );

		if ( ! $result ) {
			return new \WP_Error(
				'not_found',
				__( 'Subscription not found', 'bkx-zapier-integration' ),
				array( 'status' => 404 )
			);
		}

		$this->log( sprintf( 'Zapier unsubscribed: %s', $subscription_id ) );

		return new \WP_REST_Response( null, 204 );
	}

	/**
	 * Get available triggers.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_triggers( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( $this->trigger_service->get_available_triggers() );
	}

	/**
	 * Get trigger sample data.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_trigger_sample( \WP_REST_Request $request ) {
		$trigger = $request->get_param( 'trigger' );
		$sample  = $this->trigger_service->get_sample_data( $trigger );

		if ( is_wp_error( $sample ) ) {
			return $sample;
		}

		return new \WP_REST_Response( array( $sample ) );
	}

	/**
	 * Create a booking via Zapier.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_booking( \WP_REST_Request $request ) {
		$data = $request->get_json_params();

		// Validate required fields.
		$required = array( 'service_id', 'resource_id', 'booking_date', 'booking_time', 'customer_email' );
		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new \WP_Error(
					'missing_field',
					sprintf( __( 'Missing required field: %s', 'bkx-zapier-integration' ), $field ),
					array( 'status' => 400 )
				);
			}
		}

		// Create the booking.
		$booking_data = array(
			'service_id'      => absint( $data['service_id'] ),
			'seat_id'         => absint( $data['resource_id'] ),
			'booking_date'    => sanitize_text_field( $data['booking_date'] ),
			'booking_time'    => sanitize_text_field( $data['booking_time'] ),
			'customer_email'  => sanitize_email( $data['customer_email'] ),
			'customer_name'   => isset( $data['customer_name'] ) ? sanitize_text_field( $data['customer_name'] ) : '',
			'customer_phone'  => isset( $data['customer_phone'] ) ? sanitize_text_field( $data['customer_phone'] ) : '',
			'notes'           => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
			'source'          => 'zapier',
		);

		$booking_id = $this->create_bkx_booking( $booking_data );

		if ( is_wp_error( $booking_id ) ) {
			return $booking_id;
		}

		$this->log( sprintf( 'Booking created via Zapier: %d', $booking_id ) );

		return new \WP_REST_Response(
			array(
				'id'      => $booking_id,
				'message' => __( 'Booking created successfully', 'bkx-zapier-integration' ),
			),
			201
		);
	}

	/**
	 * Create a BookingX booking.
	 *
	 * @since 1.0.0
	 * @param array $data Booking data.
	 * @return int|\WP_Error
	 */
	private function create_bkx_booking( array $data ) {
		if ( ! class_exists( 'BkxBooking' ) ) {
			return new \WP_Error( 'dependency_missing', __( 'BookingX core not available', 'bkx-zapier-integration' ) );
		}

		$booking = new \BkxBooking();

		$post_data = array(
			'post_type'   => 'bkx_booking',
			'post_status' => 'bkx-pending',
			'post_title'  => sprintf( 'Booking - %s', $data['customer_email'] ),
		);

		$booking_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $booking_id ) ) {
			return $booking_id;
		}

		// Save booking meta.
		update_post_meta( $booking_id, 'base_id', $data['service_id'] );
		update_post_meta( $booking_id, 'seat_id', $data['seat_id'] );
		update_post_meta( $booking_id, 'booking_date', $data['booking_date'] );
		update_post_meta( $booking_id, 'booking_time', $data['booking_time'] );
		update_post_meta( $booking_id, 'customer_email', $data['customer_email'] );
		update_post_meta( $booking_id, 'customer_name', $data['customer_name'] );
		update_post_meta( $booking_id, 'customer_phone', $data['customer_phone'] );
		update_post_meta( $booking_id, 'booking_notes', $data['notes'] );
		update_post_meta( $booking_id, 'booking_source', 'zapier' );

		/**
		 * Fires after a booking is created via Zapier.
		 *
		 * @since 1.0.0
		 * @param int   $booking_id The booking ID.
		 * @param array $data       The booking data.
		 */
		do_action( 'bkx_zapier_booking_created', $booking_id, $data );

		return $booking_id;
	}

	/**
	 * Get a booking.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_booking( \WP_REST_Request $request ) {
		$booking_id = absint( $request->get_param( 'id' ) );
		$booking    = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error(
				'not_found',
				__( 'Booking not found', 'bkx-zapier-integration' ),
				array( 'status' => 404 )
			);
		}

		return new \WP_REST_Response( $this->format_booking( $booking ) );
	}

	/**
	 * Update a booking.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_booking( \WP_REST_Request $request ) {
		$booking_id = absint( $request->get_param( 'id' ) );
		$booking    = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error(
				'not_found',
				__( 'Booking not found', 'bkx-zapier-integration' ),
				array( 'status' => 404 )
			);
		}

		$data = $request->get_json_params();

		// Update meta if provided.
		$meta_fields = array(
			'booking_date'   => 'booking_date',
			'booking_time'   => 'booking_time',
			'customer_email' => 'customer_email',
			'customer_name'  => 'customer_name',
			'customer_phone' => 'customer_phone',
			'notes'          => 'booking_notes',
		);

		foreach ( $meta_fields as $key => $meta_key ) {
			if ( isset( $data[ $key ] ) ) {
				update_post_meta( $booking_id, $meta_key, sanitize_text_field( $data[ $key ] ) );
			}
		}

		// Update status if provided.
		if ( ! empty( $data['status'] ) ) {
			$valid_statuses = array( 'bkx-pending', 'bkx-ack', 'bkx-completed', 'bkx-cancelled' );
			$status         = 'bkx-' . sanitize_text_field( $data['status'] );

			if ( in_array( $status, $valid_statuses, true ) ) {
				wp_update_post(
					array(
						'ID'          => $booking_id,
						'post_status' => $status,
					)
				);
			}
		}

		$this->log( sprintf( 'Booking updated via Zapier: %d', $booking_id ) );

		return new \WP_REST_Response( $this->format_booking( get_post( $booking_id ) ) );
	}

	/**
	 * Cancel a booking.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function cancel_booking( \WP_REST_Request $request ) {
		$booking_id = absint( $request->get_param( 'id' ) );
		$booking    = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return new \WP_Error(
				'not_found',
				__( 'Booking not found', 'bkx-zapier-integration' ),
				array( 'status' => 404 )
			);
		}

		wp_update_post(
			array(
				'ID'          => $booking_id,
				'post_status' => 'bkx-cancelled',
			)
		);

		$this->log( sprintf( 'Booking cancelled via Zapier: %d', $booking_id ) );

		/**
		 * Fires after a booking is cancelled via Zapier.
		 *
		 * @since 1.0.0
		 * @param int $booking_id The booking ID.
		 */
		do_action( 'bkx_zapier_booking_cancelled', $booking_id );

		return new \WP_REST_Response(
			array(
				'id'      => $booking_id,
				'status'  => 'cancelled',
				'message' => __( 'Booking cancelled successfully', 'bkx-zapier-integration' ),
			)
		);
	}

	/**
	 * Get customers.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_customers( \WP_REST_Request $request ): \WP_REST_Response {
		global $wpdb;

		$limit  = min( absint( $request->get_param( 'limit' ) ) ?: 25, 100 );
		$offset = absint( $request->get_param( 'offset' ) ) ?: 0;

		// Get unique customers from bookings.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value as email
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE p.post_type = 'bkx_booking'
				AND pm.meta_key = 'customer_email'
				AND pm.meta_value != ''
				ORDER BY pm.meta_id DESC
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		$customers = array();
		foreach ( $results as $row ) {
			$user = get_user_by( 'email', $row->email );
			$customers[] = array(
				'email'      => $row->email,
				'name'       => $user ? $user->display_name : '',
				'user_id'    => $user ? $user->ID : null,
				'registered' => $user ? $user->user_registered : null,
			);
		}

		return new \WP_REST_Response( $customers );
	}

	/**
	 * Get services.
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_services( \WP_REST_Request $request ): \WP_REST_Response {
		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			)
		);

		$data = array();
		foreach ( $services as $service ) {
			$data[] = array(
				'id'          => $service->ID,
				'name'        => $service->post_title,
				'description' => $service->post_excerpt,
				'duration'    => get_post_meta( $service->ID, 'base_time', true ),
				'price'       => get_post_meta( $service->ID, 'base_price', true ),
			);
		}

		return new \WP_REST_Response( $data );
	}

	/**
	 * Get resources (seats).
	 *
	 * @since 1.0.0
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_resources( \WP_REST_Request $request ): \WP_REST_Response {
		$resources = get_posts(
			array(
				'post_type'      => 'bkx_seat',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			)
		);

		$data = array();
		foreach ( $resources as $resource ) {
			$data[] = array(
				'id'    => $resource->ID,
				'name'  => $resource->post_title,
				'email' => get_post_meta( $resource->ID, 'seat_email', true ),
			);
		}

		return new \WP_REST_Response( $data );
	}

	/**
	 * Format booking for API response.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $booking Booking post object.
	 * @return array
	 */
	private function format_booking( \WP_Post $booking ): array {
		$status = str_replace( 'bkx-', '', $booking->post_status );

		return array(
			'id'             => $booking->ID,
			'status'         => $status,
			'service_id'     => get_post_meta( $booking->ID, 'base_id', true ),
			'resource_id'    => get_post_meta( $booking->ID, 'seat_id', true ),
			'booking_date'   => get_post_meta( $booking->ID, 'booking_date', true ),
			'booking_time'   => get_post_meta( $booking->ID, 'booking_time', true ),
			'customer_email' => get_post_meta( $booking->ID, 'customer_email', true ),
			'customer_name'  => get_post_meta( $booking->ID, 'customer_name', true ),
			'customer_phone' => get_post_meta( $booking->ID, 'customer_phone', true ),
			'notes'          => get_post_meta( $booking->ID, 'booking_notes', true ),
			'total'          => get_post_meta( $booking->ID, 'booking_total', true ),
			'created_at'     => $booking->post_date,
			'modified_at'    => $booking->post_modified,
		);
	}

	/**
	 * Get the webhook service.
	 *
	 * @since 1.0.0
	 * @return WebhookService
	 */
	public function get_webhook_service(): WebhookService {
		return $this->webhook_service;
	}

	/**
	 * Get the trigger service.
	 *
	 * @since 1.0.0
	 * @return TriggerService
	 */
	public function get_trigger_service(): TriggerService {
		return $this->trigger_service;
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	protected function get_default_settings(): array {
		return array(
			'enabled'               => true,
			'webhook_subscriptions' => array(),
			'log_webhooks'          => true,
			'retry_failed'          => true,
			'max_retries'           => 3,
		);
	}
}
