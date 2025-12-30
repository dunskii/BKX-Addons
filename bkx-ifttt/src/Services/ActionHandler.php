<?php
/**
 * Action Handler Service for IFTTT Integration.
 *
 * Handles incoming actions from IFTTT to perform booking operations.
 *
 * @package BookingX\IFTTT\Services
 */

namespace BookingX\IFTTT\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ActionHandler class.
 */
class ActionHandler {

	/**
	 * Parent addon instance.
	 *
	 * @var \BookingX\IFTTT\IFTTTAddon
	 */
	private $addon;

	/**
	 * Available actions.
	 *
	 * @var array
	 */
	private $actions = array();

	/**
	 * Constructor.
	 *
	 * @param \BookingX\IFTTT\IFTTTAddon $addon Parent addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
		$this->register_actions();
	}

	/**
	 * Register available actions.
	 */
	private function register_actions() {
		$this->actions = array(
			'create_booking'  => array(
				'slug'        => 'create_booking',
				'name'        => __( 'Create Booking', 'bkx-ifttt' ),
				'description' => __( 'Create a new booking in BookingX.', 'bkx-ifttt' ),
				'fields'      => array(
					array(
						'slug'     => 'customer_name',
						'name'     => __( 'Customer Name', 'bkx-ifttt' ),
						'type'     => 'string',
						'required' => true,
					),
					array(
						'slug'     => 'customer_email',
						'name'     => __( 'Customer Email', 'bkx-ifttt' ),
						'type'     => 'string',
						'required' => true,
					),
					array(
						'slug'     => 'customer_phone',
						'name'     => __( 'Customer Phone', 'bkx-ifttt' ),
						'type'     => 'string',
						'required' => false,
					),
					array(
						'slug'     => 'service_id',
						'name'     => __( 'Service ID', 'bkx-ifttt' ),
						'type'     => 'number',
						'required' => true,
					),
					array(
						'slug'     => 'staff_id',
						'name'     => __( 'Staff ID', 'bkx-ifttt' ),
						'type'     => 'number',
						'required' => false,
					),
					array(
						'slug'     => 'booking_date',
						'name'     => __( 'Booking Date (YYYY-MM-DD)', 'bkx-ifttt' ),
						'type'     => 'string',
						'required' => true,
					),
					array(
						'slug'     => 'booking_time',
						'name'     => __( 'Booking Time (HH:MM)', 'bkx-ifttt' ),
						'type'     => 'string',
						'required' => true,
					),
					array(
						'slug'     => 'notes',
						'name'     => __( 'Notes', 'bkx-ifttt' ),
						'type'     => 'string',
						'required' => false,
					),
				),
			),
			'cancel_booking'  => array(
				'slug'        => 'cancel_booking',
				'name'        => __( 'Cancel Booking', 'bkx-ifttt' ),
				'description' => __( 'Cancel an existing booking.', 'bkx-ifttt' ),
				'fields'      => array(
					array(
						'slug'     => 'booking_id',
						'name'     => __( 'Booking ID', 'bkx-ifttt' ),
						'type'     => 'number',
						'required' => true,
					),
					array(
						'slug'     => 'reason',
						'name'     => __( 'Cancellation Reason', 'bkx-ifttt' ),
						'type'     => 'string',
						'required' => false,
					),
				),
			),
			'update_booking'  => array(
				'slug'        => 'update_booking',
				'name'        => __( 'Update Booking', 'bkx-ifttt' ),
				'description' => __( 'Update an existing booking.', 'bkx-ifttt' ),
				'fields'      => array(
					array(
						'slug'     => 'booking_id',
						'name'     => __( 'Booking ID', 'bkx-ifttt' ),
						'type'     => 'number',
						'required' => true,
					),
					array(
						'slug'     => 'booking_date',
						'name'     => __( 'New Booking Date (YYYY-MM-DD)', 'bkx-ifttt' ),
						'type'     => 'string',
						'required' => false,
					),
					array(
						'slug'     => 'booking_time',
						'name'     => __( 'New Booking Time (HH:MM)', 'bkx-ifttt' ),
						'type'     => 'string',
						'required' => false,
					),
					array(
						'slug'     => 'status',
						'name'     => __( 'New Status', 'bkx-ifttt' ),
						'type'     => 'string',
						'required' => false,
					),
					array(
						'slug'     => 'notes',
						'name'     => __( 'Notes', 'bkx-ifttt' ),
						'type'     => 'string',
						'required' => false,
					),
				),
			),
			'confirm_booking' => array(
				'slug'        => 'confirm_booking',
				'name'        => __( 'Confirm Booking', 'bkx-ifttt' ),
				'description' => __( 'Confirm a pending booking.', 'bkx-ifttt' ),
				'fields'      => array(
					array(
						'slug'     => 'booking_id',
						'name'     => __( 'Booking ID', 'bkx-ifttt' ),
						'type'     => 'number',
						'required' => true,
					),
				),
			),
			'complete_booking' => array(
				'slug'        => 'complete_booking',
				'name'        => __( 'Complete Booking', 'bkx-ifttt' ),
				'description' => __( 'Mark a booking as completed.', 'bkx-ifttt' ),
				'fields'      => array(
					array(
						'slug'     => 'booking_id',
						'name'     => __( 'Booking ID', 'bkx-ifttt' ),
						'type'     => 'number',
						'required' => true,
					),
				),
			),
		);

		/**
		 * Filter available IFTTT actions.
		 *
		 * @param array $actions Registered actions.
		 */
		$this->actions = apply_filters( 'bkx_ifttt_actions', $this->actions );
	}

	/**
	 * Get all registered actions.
	 *
	 * @return array
	 */
	public function get_actions() {
		return $this->actions;
	}

	/**
	 * Get a specific action.
	 *
	 * @param string $action_slug Action slug.
	 * @return array|null
	 */
	public function get_action( $action_slug ) {
		return $this->actions[ $action_slug ] ?? null;
	}

	/**
	 * Check if action is enabled.
	 *
	 * @param string $action_slug Action slug.
	 * @return bool
	 */
	public function is_action_enabled( $action_slug ) {
		$actions = $this->addon->get_setting( 'actions', array() );
		return ! empty( $actions[ $action_slug ] );
	}

	/**
	 * Execute an action.
	 *
	 * @param string $action_slug Action slug.
	 * @param array  $action_data Action data.
	 * @return array Result with success status and data/error.
	 */
	public function execute_action( $action_slug, $action_data ) {
		if ( ! $this->is_action_enabled( $action_slug ) ) {
			return array(
				'success' => false,
				'error'   => __( 'This action is not enabled.', 'bkx-ifttt' ),
			);
		}

		$action = $this->get_action( $action_slug );
		if ( ! $action ) {
			return array(
				'success' => false,
				'error'   => __( 'Unknown action.', 'bkx-ifttt' ),
			);
		}

		// Validate required fields.
		$validation = $this->validate_action_data( $action, $action_data );
		if ( ! $validation['valid'] ) {
			return array(
				'success' => false,
				'error'   => $validation['error'],
			);
		}

		// Execute the action.
		$method = 'action_' . $action_slug;
		if ( method_exists( $this, $method ) ) {
			$result = $this->$method( $action_data );
		} else {
			$result = array(
				'success' => false,
				'error'   => __( 'Action handler not implemented.', 'bkx-ifttt' ),
			);
		}

		// Log the action.
		$this->log_action( $action_slug, $action_data, $result );

		/**
		 * Action after IFTTT action is executed.
		 *
		 * @param string $action_slug Action slug.
		 * @param array  $action_data Action data.
		 * @param array  $result      Execution result.
		 */
		do_action( 'bkx_ifttt_action_executed', $action_slug, $action_data, $result );

		return $result;
	}

	/**
	 * Validate action data against required fields.
	 *
	 * @param array $action      Action definition.
	 * @param array $action_data Action data.
	 * @return array
	 */
	private function validate_action_data( $action, $action_data ) {
		foreach ( $action['fields'] as $field ) {
			if ( ! empty( $field['required'] ) && empty( $action_data[ $field['slug'] ] ) ) {
				return array(
					'valid' => false,
					'error' => sprintf(
						/* translators: %s: field name */
						__( 'Missing required field: %s', 'bkx-ifttt' ),
						$field['name']
					),
				);
			}
		}

		return array( 'valid' => true );
	}

	/**
	 * Create booking action.
	 *
	 * @param array $data Action data.
	 * @return array
	 */
	private function action_create_booking( $data ) {
		// Validate service exists.
		$service = get_post( absint( $data['service_id'] ) );
		if ( ! $service || 'bkx_base' !== $service->post_type ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid service ID.', 'bkx-ifttt' ),
			);
		}

		// Validate staff if provided.
		$staff_id = 0;
		if ( ! empty( $data['staff_id'] ) ) {
			$staff = get_post( absint( $data['staff_id'] ) );
			if ( ! $staff || 'bkx_seat' !== $staff->post_type ) {
				return array(
					'success' => false,
					'error'   => __( 'Invalid staff ID.', 'bkx-ifttt' ),
				);
			}
			$staff_id = absint( $data['staff_id'] );
		}

		// Validate date format.
		$booking_date = sanitize_text_field( $data['booking_date'] );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $booking_date ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid date format. Use YYYY-MM-DD.', 'bkx-ifttt' ),
			);
		}

		// Validate time format.
		$booking_time = sanitize_text_field( $data['booking_time'] );
		if ( ! preg_match( '/^\d{2}:\d{2}$/', $booking_time ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid time format. Use HH:MM.', 'bkx-ifttt' ),
			);
		}

		// Create the booking.
		$booking_data = array(
			'post_type'   => 'bkx_booking',
			'post_status' => 'bkx-pending',
			'post_title'  => sprintf(
				/* translators: %s: customer name */
				__( 'Booking by %s', 'bkx-ifttt' ),
				sanitize_text_field( $data['customer_name'] )
			),
		);

		$booking_id = wp_insert_post( $booking_data, true );

		if ( is_wp_error( $booking_id ) ) {
			return array(
				'success' => false,
				'error'   => $booking_id->get_error_message(),
			);
		}

		// Save booking meta.
		update_post_meta( $booking_id, 'customer_name', sanitize_text_field( $data['customer_name'] ) );
		update_post_meta( $booking_id, 'customer_email', sanitize_email( $data['customer_email'] ) );
		update_post_meta( $booking_id, 'customer_phone', sanitize_text_field( $data['customer_phone'] ?? '' ) );
		update_post_meta( $booking_id, 'base_id', absint( $data['service_id'] ) );
		update_post_meta( $booking_id, 'seat_id', $staff_id );
		update_post_meta( $booking_id, 'booking_date', $booking_date );
		update_post_meta( $booking_id, 'booking_time', $booking_time );
		update_post_meta( $booking_id, 'notes', sanitize_textarea_field( $data['notes'] ?? '' ) );
		update_post_meta( $booking_id, '_created_via', 'ifttt' );

		// Calculate total.
		$service_price = (float) get_post_meta( $data['service_id'], 'base_price', true );
		update_post_meta( $booking_id, 'total_amount', $service_price );

		/**
		 * Action after booking created via IFTTT.
		 *
		 * @param int   $booking_id Booking ID.
		 * @param array $data       Action data.
		 */
		do_action( 'bkx_ifttt_booking_created', $booking_id, $data );

		return array(
			'success' => true,
			'data'    => array(
				'id'   => $booking_id,
				'url'  => admin_url( 'post.php?post=' . $booking_id . '&action=edit' ),
			),
		);
	}

	/**
	 * Cancel booking action.
	 *
	 * @param array $data Action data.
	 * @return array
	 */
	private function action_cancel_booking( $data ) {
		$booking_id = absint( $data['booking_id'] );
		$booking    = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return array(
				'success' => false,
				'error'   => __( 'Booking not found.', 'bkx-ifttt' ),
			);
		}

		// Check if already cancelled.
		if ( 'bkx-cancelled' === $booking->post_status ) {
			return array(
				'success' => false,
				'error'   => __( 'Booking is already cancelled.', 'bkx-ifttt' ),
			);
		}

		// Update status.
		$result = wp_update_post(
			array(
				'ID'          => $booking_id,
				'post_status' => 'bkx-cancelled',
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'error'   => $result->get_error_message(),
			);
		}

		// Save cancellation reason.
		if ( ! empty( $data['reason'] ) ) {
			update_post_meta( $booking_id, '_cancellation_reason', sanitize_textarea_field( $data['reason'] ) );
		}
		update_post_meta( $booking_id, '_cancelled_via', 'ifttt' );
		update_post_meta( $booking_id, '_cancelled_at', current_time( 'mysql' ) );

		/**
		 * Action after booking cancelled via IFTTT.
		 *
		 * @param int   $booking_id Booking ID.
		 * @param array $data       Action data.
		 */
		do_action( 'bkx_ifttt_booking_cancelled', $booking_id, $data );

		return array(
			'success' => true,
			'data'    => array(
				'id'     => $booking_id,
				'status' => 'cancelled',
			),
		);
	}

	/**
	 * Update booking action.
	 *
	 * @param array $data Action data.
	 * @return array
	 */
	private function action_update_booking( $data ) {
		$booking_id = absint( $data['booking_id'] );
		$booking    = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return array(
				'success' => false,
				'error'   => __( 'Booking not found.', 'bkx-ifttt' ),
			);
		}

		$updated = array();

		// Update date if provided.
		if ( ! empty( $data['booking_date'] ) ) {
			$booking_date = sanitize_text_field( $data['booking_date'] );
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $booking_date ) ) {
				$old_date = get_post_meta( $booking_id, 'booking_date', true );
				update_post_meta( $booking_id, 'booking_date', $booking_date );
				$updated['booking_date'] = $booking_date;

				// Track reschedule.
				if ( $old_date !== $booking_date ) {
					update_post_meta( $booking_id, '_previous_date', $old_date );
				}
			}
		}

		// Update time if provided.
		if ( ! empty( $data['booking_time'] ) ) {
			$booking_time = sanitize_text_field( $data['booking_time'] );
			if ( preg_match( '/^\d{2}:\d{2}$/', $booking_time ) ) {
				$old_time = get_post_meta( $booking_id, 'booking_time', true );
				update_post_meta( $booking_id, 'booking_time', $booking_time );
				$updated['booking_time'] = $booking_time;

				// Track reschedule.
				if ( $old_time !== $booking_time ) {
					update_post_meta( $booking_id, '_previous_time', $old_time );
				}
			}
		}

		// Update status if provided.
		if ( ! empty( $data['status'] ) ) {
			$status_map = array(
				'pending'   => 'bkx-pending',
				'confirmed' => 'bkx-ack',
				'completed' => 'bkx-completed',
				'cancelled' => 'bkx-cancelled',
				'missed'    => 'bkx-missed',
			);

			$status = strtolower( sanitize_text_field( $data['status'] ) );
			if ( isset( $status_map[ $status ] ) ) {
				wp_update_post(
					array(
						'ID'          => $booking_id,
						'post_status' => $status_map[ $status ],
					)
				);
				$updated['status'] = $status;
			}
		}

		// Update notes if provided.
		if ( ! empty( $data['notes'] ) ) {
			update_post_meta( $booking_id, 'notes', sanitize_textarea_field( $data['notes'] ) );
			$updated['notes'] = $data['notes'];
		}

		update_post_meta( $booking_id, '_updated_via', 'ifttt' );
		update_post_meta( $booking_id, '_updated_at', current_time( 'mysql' ) );

		/**
		 * Action after booking updated via IFTTT.
		 *
		 * @param int   $booking_id Booking ID.
		 * @param array $data       Action data.
		 * @param array $updated    Updated fields.
		 */
		do_action( 'bkx_ifttt_booking_updated', $booking_id, $data, $updated );

		return array(
			'success' => true,
			'data'    => array(
				'id'      => $booking_id,
				'updated' => $updated,
			),
		);
	}

	/**
	 * Confirm booking action.
	 *
	 * @param array $data Action data.
	 * @return array
	 */
	private function action_confirm_booking( $data ) {
		$booking_id = absint( $data['booking_id'] );
		$booking    = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return array(
				'success' => false,
				'error'   => __( 'Booking not found.', 'bkx-ifttt' ),
			);
		}

		// Check if already confirmed or completed.
		if ( in_array( $booking->post_status, array( 'bkx-ack', 'bkx-completed' ), true ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Booking is already confirmed or completed.', 'bkx-ifttt' ),
			);
		}

		$result = wp_update_post(
			array(
				'ID'          => $booking_id,
				'post_status' => 'bkx-ack',
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'error'   => $result->get_error_message(),
			);
		}

		update_post_meta( $booking_id, '_confirmed_via', 'ifttt' );
		update_post_meta( $booking_id, '_confirmed_at', current_time( 'mysql' ) );

		/**
		 * Action after booking confirmed via IFTTT.
		 *
		 * @param int   $booking_id Booking ID.
		 * @param array $data       Action data.
		 */
		do_action( 'bkx_ifttt_booking_confirmed', $booking_id, $data );

		return array(
			'success' => true,
			'data'    => array(
				'id'     => $booking_id,
				'status' => 'confirmed',
			),
		);
	}

	/**
	 * Complete booking action.
	 *
	 * @param array $data Action data.
	 * @return array
	 */
	private function action_complete_booking( $data ) {
		$booking_id = absint( $data['booking_id'] );
		$booking    = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return array(
				'success' => false,
				'error'   => __( 'Booking not found.', 'bkx-ifttt' ),
			);
		}

		// Check if already completed.
		if ( 'bkx-completed' === $booking->post_status ) {
			return array(
				'success' => false,
				'error'   => __( 'Booking is already completed.', 'bkx-ifttt' ),
			);
		}

		$result = wp_update_post(
			array(
				'ID'          => $booking_id,
				'post_status' => 'bkx-completed',
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return array(
				'success' => false,
				'error'   => $result->get_error_message(),
			);
		}

		update_post_meta( $booking_id, '_completed_via', 'ifttt' );
		update_post_meta( $booking_id, '_completed_at', current_time( 'mysql' ) );

		/**
		 * Action after booking completed via IFTTT.
		 *
		 * @param int   $booking_id Booking ID.
		 * @param array $data       Action data.
		 */
		do_action( 'bkx_ifttt_booking_completed', $booking_id, $data );

		return array(
			'success' => true,
			'data'    => array(
				'id'     => $booking_id,
				'status' => 'completed',
			),
		);
	}

	/**
	 * Log action execution.
	 *
	 * @param string $action_slug Action slug.
	 * @param array  $action_data Action data.
	 * @param array  $result      Execution result.
	 */
	private function log_action( $action_slug, $action_data, $result ) {
		if ( ! $this->addon->get_setting( 'log_requests', false ) ) {
			return;
		}

		$logs = get_option( 'bkx_ifttt_action_logs', array() );

		$logs[] = array(
			'timestamp' => current_time( 'mysql' ),
			'action'    => $action_slug,
			'success'   => $result['success'],
			'data'      => $result['data'] ?? null,
			'error'     => $result['error'] ?? null,
		);

		// Keep only last 100 logs.
		$logs = array_slice( $logs, -100 );

		update_option( 'bkx_ifttt_action_logs', $logs );
	}

	/**
	 * Get action fields for IFTTT service config.
	 *
	 * @param string $action_slug Action slug.
	 * @return array
	 */
	public function get_action_fields( $action_slug ) {
		$action = $this->get_action( $action_slug );
		if ( ! $action ) {
			return array();
		}

		return $action['fields'];
	}
}
