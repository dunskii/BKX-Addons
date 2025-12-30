<?php
/**
 * Hook Inspector Service.
 *
 * @package BookingX\DeveloperSDK
 */

namespace BookingX\DeveloperSDK\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class HookInspector
 *
 * Inspects and documents BookingX hooks.
 */
class HookInspector {

	/**
	 * Get all BookingX hooks.
	 *
	 * @return array Hooks grouped by category.
	 */
	public function get_all_hooks(): array {
		return array(
			'booking'      => $this->get_booking_hooks(),
			'payment'      => $this->get_payment_hooks(),
			'notification' => $this->get_notification_hooks(),
			'calendar'     => $this->get_calendar_hooks(),
			'admin'        => $this->get_admin_hooks(),
			'frontend'     => $this->get_frontend_hooks(),
		);
	}

	/**
	 * Get hooks by category and search.
	 *
	 * @param string $category Category filter.
	 * @param string $search   Search term.
	 * @return array Filtered hooks.
	 */
	public function get_hooks( string $category = '', string $search = '' ): array {
		$all_hooks = $this->get_all_hooks();

		// Filter by category.
		if ( $category && isset( $all_hooks[ $category ] ) ) {
			$hooks = array( $category => $all_hooks[ $category ] );
		} else {
			$hooks = $all_hooks;
		}

		// Filter by search.
		if ( $search ) {
			$search = strtolower( $search );

			foreach ( $hooks as $cat => &$cat_hooks ) {
				$cat_hooks = array_filter(
					$cat_hooks,
					function ( $hook ) use ( $search ) {
						return strpos( strtolower( $hook['name'] ), $search ) !== false
							|| strpos( strtolower( $hook['description'] ), $search ) !== false;
					}
				);
			}
		}

		return $hooks;
	}

	/**
	 * Get booking-related hooks.
	 *
	 * @return array Hooks.
	 */
	private function get_booking_hooks(): array {
		return array(
			array(
				'name'        => 'bkx_booking_created',
				'type'        => 'action',
				'description' => __( 'Fires after a new booking is created.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$booking_id',
						'type' => 'int',
						'desc' => __( 'The booking post ID.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$booking_data',
						'type' => 'array',
						'desc' => __( 'Array of booking data.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
				'example'     => "add_action( 'bkx_booking_created', function( \$id, \$data ) {\n    // Your code here\n}, 10, 2 );",
			),
			array(
				'name'        => 'bkx_booking_updated',
				'type'        => 'action',
				'description' => __( 'Fires after a booking is updated.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$booking_id',
						'type' => 'int',
						'desc' => __( 'The booking post ID.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$new_data',
						'type' => 'array',
						'desc' => __( 'New booking data.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$old_data',
						'type' => 'array',
						'desc' => __( 'Previous booking data.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
			),
			array(
				'name'        => 'bkx_booking_cancelled',
				'type'        => 'action',
				'description' => __( 'Fires when a booking is cancelled.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$booking_id',
						'type' => 'int',
						'desc' => __( 'The booking post ID.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$reason',
						'type' => 'string',
						'desc' => __( 'Cancellation reason.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
			),
			array(
				'name'        => 'bkx_booking_completed',
				'type'        => 'action',
				'description' => __( 'Fires when a booking is marked as completed.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$booking_id',
						'type' => 'int',
						'desc' => __( 'The booking post ID.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
			),
			array(
				'name'        => 'bkx_booking_total',
				'type'        => 'filter',
				'description' => __( 'Filter the calculated booking total.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$total',
						'type' => 'float',
						'desc' => __( 'The calculated total.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$booking_id',
						'type' => 'int',
						'desc' => __( 'The booking post ID.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
				'return'      => 'float',
			),
			array(
				'name'        => 'bkx_booking_status_transition',
				'type'        => 'action',
				'description' => __( 'Fires when booking status changes.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$new_status',
						'type' => 'string',
						'desc' => __( 'New status.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$old_status',
						'type' => 'string',
						'desc' => __( 'Previous status.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$booking_id',
						'type' => 'int',
						'desc' => __( 'Booking ID.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.1.0',
			),
		);
	}

	/**
	 * Get payment-related hooks.
	 *
	 * @return array Hooks.
	 */
	private function get_payment_hooks(): array {
		return array(
			array(
				'name'        => 'bkx_payment_completed',
				'type'        => 'action',
				'description' => __( 'Fires after successful payment.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$booking_id',
						'type' => 'int',
						'desc' => __( 'The booking post ID.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$payment_data',
						'type' => 'array',
						'desc' => __( 'Payment details including amount, method, transaction ID.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
			),
			array(
				'name'        => 'bkx_payment_failed',
				'type'        => 'action',
				'description' => __( 'Fires when payment fails.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$booking_id',
						'type' => 'int',
						'desc' => __( 'The booking post ID.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$error',
						'type' => 'string|WP_Error',
						'desc' => __( 'Error message or object.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
			),
			array(
				'name'        => 'bkx_payment_refunded',
				'type'        => 'action',
				'description' => __( 'Fires after a refund is processed.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$booking_id',
						'type' => 'int',
						'desc' => __( 'The booking post ID.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$amount',
						'type' => 'float',
						'desc' => __( 'Refunded amount.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
			),
			array(
				'name'        => 'bkx_payment_gateways',
				'type'        => 'filter',
				'description' => __( 'Filter available payment gateways.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$gateways',
						'type' => 'array',
						'desc' => __( 'Array of gateway class names.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
				'return'      => 'array',
			),
		);
	}

	/**
	 * Get notification-related hooks.
	 *
	 * @return array Hooks.
	 */
	private function get_notification_hooks(): array {
		return array(
			array(
				'name'        => 'bkx_send_notification',
				'type'        => 'action',
				'description' => __( 'Trigger to send notification.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$type',
						'type' => 'string',
						'desc' => __( 'Notification type (email, sms, etc).', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$recipient',
						'type' => 'string',
						'desc' => __( 'Recipient address.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$data',
						'type' => 'array',
						'desc' => __( 'Notification data.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.1.0',
			),
			array(
				'name'        => 'bkx_notification_content',
				'type'        => 'filter',
				'description' => __( 'Filter notification content before sending.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$content',
						'type' => 'string',
						'desc' => __( 'Message content.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$type',
						'type' => 'string',
						'desc' => __( 'Notification type.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$booking_id',
						'type' => 'int',
						'desc' => __( 'Related booking ID.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.1.0',
				'return'      => 'string',
			),
		);
	}

	/**
	 * Get calendar-related hooks.
	 *
	 * @return array Hooks.
	 */
	private function get_calendar_hooks(): array {
		return array(
			array(
				'name'        => 'bkx_calendar_event_created',
				'type'        => 'action',
				'description' => __( 'Fires after calendar event is created.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$event_id',
						'type' => 'string',
						'desc' => __( 'Calendar event ID.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$booking_id',
						'type' => 'int',
						'desc' => __( 'Related booking ID.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$provider',
						'type' => 'string',
						'desc' => __( 'Calendar provider (google, outlook).', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.1.0',
			),
			array(
				'name'        => 'bkx_available_time_slots',
				'type'        => 'filter',
				'description' => __( 'Filter available time slots.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$slots',
						'type' => 'array',
						'desc' => __( 'Available time slots.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$date',
						'type' => 'string',
						'desc' => __( 'Date (Y-m-d).', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$service_id',
						'type' => 'int',
						'desc' => __( 'Service ID.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$staff_id',
						'type' => 'int',
						'desc' => __( 'Staff ID.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
				'return'      => 'array',
			),
		);
	}

	/**
	 * Get admin-related hooks.
	 *
	 * @return array Hooks.
	 */
	private function get_admin_hooks(): array {
		return array(
			array(
				'name'        => 'bkx_settings_tabs',
				'type'        => 'filter',
				'description' => __( 'Filter settings page tabs.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$tabs',
						'type' => 'array',
						'desc' => __( 'Array of tab slugs => labels.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
				'return'      => 'array',
			),
			array(
				'name'        => 'bkx_admin_menu_pages',
				'type'        => 'filter',
				'description' => __( 'Filter admin menu pages.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$pages',
						'type' => 'array',
						'desc' => __( 'Array of menu page configurations.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
				'return'      => 'array',
			),
			array(
				'name'        => 'bkx_booking_columns',
				'type'        => 'filter',
				'description' => __( 'Filter booking list table columns.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$columns',
						'type' => 'array',
						'desc' => __( 'Array of column slugs => labels.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
				'return'      => 'array',
			),
		);
	}

	/**
	 * Get frontend-related hooks.
	 *
	 * @return array Hooks.
	 */
	private function get_frontend_hooks(): array {
		return array(
			array(
				'name'        => 'bkx_booking_form_fields',
				'type'        => 'filter',
				'description' => __( 'Filter booking form fields.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$fields',
						'type' => 'array',
						'desc' => __( 'Array of field configurations.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
				'return'      => 'array',
			),
			array(
				'name'        => 'bkx_booking_form_validation',
				'type'        => 'filter',
				'description' => __( 'Filter form validation errors.', 'bkx-developer-sdk' ),
				'params'      => array(
					array(
						'name' => '$errors',
						'type' => 'array',
						'desc' => __( 'Validation errors.', 'bkx-developer-sdk' ),
					),
					array(
						'name' => '$data',
						'type' => 'array',
						'desc' => __( 'Submitted form data.', 'bkx-developer-sdk' ),
					),
				),
				'since'       => '1.0.0',
				'return'      => 'array',
			),
			array(
				'name'        => 'bookingx_before_main_content',
				'type'        => 'action',
				'description' => __( 'Fires before main booking content.', 'bkx-developer-sdk' ),
				'params'      => array(),
				'since'       => '1.0.0',
			),
			array(
				'name'        => 'bookingx_after_main_content',
				'type'        => 'action',
				'description' => __( 'Fires after main booking content.', 'bkx-developer-sdk' ),
				'params'      => array(),
				'since'       => '1.0.0',
			),
		);
	}

	/**
	 * Get registered hooks on current page.
	 *
	 * @return array Currently registered hooks.
	 */
	public function get_registered_hooks(): array {
		global $wp_filter;

		$bkx_hooks = array();

		foreach ( $wp_filter as $hook_name => $hook_obj ) {
			if ( strpos( $hook_name, 'bkx' ) !== false || strpos( $hook_name, 'bookingx' ) !== false ) {
				$callbacks = array();

				foreach ( $hook_obj->callbacks as $priority => $funcs ) {
					foreach ( $funcs as $func ) {
						$callback_name = $this->get_callback_name( $func['function'] );
						$callbacks[]   = array(
							'callback' => $callback_name,
							'priority' => $priority,
							'args'     => $func['accepted_args'],
						);
					}
				}

				$bkx_hooks[ $hook_name ] = $callbacks;
			}
		}

		return $bkx_hooks;
	}

	/**
	 * Get callback name from function reference.
	 *
	 * @param mixed $callback Callback reference.
	 * @return string Callback name.
	 */
	private function get_callback_name( $callback ): string {
		if ( is_string( $callback ) ) {
			return $callback;
		}

		if ( is_array( $callback ) ) {
			if ( is_object( $callback[0] ) ) {
				return get_class( $callback[0] ) . '::' . $callback[1];
			}
			return $callback[0] . '::' . $callback[1];
		}

		if ( is_object( $callback ) && $callback instanceof \Closure ) {
			return 'Closure';
		}

		return 'Unknown';
	}
}
