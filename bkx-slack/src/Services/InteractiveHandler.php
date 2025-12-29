<?php
/**
 * Interactive Component Handler.
 *
 * @package BookingX\Slack\Services
 */

namespace BookingX\Slack\Services;

defined( 'ABSPATH' ) || exit;

/**
 * InteractiveHandler class.
 *
 * Handles Slack interactive components (buttons, modals, etc.).
 */
class InteractiveHandler {

	/**
	 * Slack API instance.
	 *
	 * @var SlackApi
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param SlackApi $api Slack API instance.
	 */
	public function __construct( SlackApi $api ) {
		$this->api = $api;
	}

	/**
	 * Handle interactive request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle( $request ) {
		$body      = $request->get_body();
		$timestamp = $request->get_header( 'X-Slack-Request-Timestamp' );
		$signature = $request->get_header( 'X-Slack-Signature' );

		// Verify signature.
		if ( ! $this->api->verify_signature( $body, $timestamp, $signature ) ) {
			return new \WP_REST_Response( array( 'error' => 'Invalid signature' ), 401 );
		}

		// Parse form-encoded payload.
		parse_str( $body, $params );

		if ( ! isset( $params['payload'] ) ) {
			return new \WP_REST_Response( array( 'error' => 'Missing payload' ), 400 );
		}

		$payload = json_decode( $params['payload'], true );

		if ( ! $payload ) {
			return new \WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
		}

		$type = $payload['type'] ?? '';

		switch ( $type ) {
			case 'block_actions':
				return $this->handle_block_actions( $payload );

			case 'view_submission':
				return $this->handle_view_submission( $payload );

			case 'view_closed':
				return $this->handle_view_closed( $payload );

			case 'shortcut':
				return $this->handle_shortcut( $payload );

			default:
				return new \WP_REST_Response( array( 'ok' => true ), 200 );
		}
	}

	/**
	 * Handle block actions.
	 *
	 * @param array $payload Payload data.
	 * @return \WP_REST_Response
	 */
	private function handle_block_actions( $payload ) {
		$actions      = $payload['actions'] ?? array();
		$user         = $payload['user'] ?? array();
		$team         = $payload['team'] ?? array();
		$channel      = $payload['channel'] ?? array();
		$response_url = $payload['response_url'] ?? '';
		$trigger_id   = $payload['trigger_id'] ?? '';
		$message      = $payload['message'] ?? array();

		foreach ( $actions as $action ) {
			$action_id = $action['action_id'] ?? '';
			$value     = $action['value'] ?? '';

			switch ( $action_id ) {
				case 'confirm_booking':
					$this->action_confirm_booking( $value, $response_url, $message, $team['id'] );
					break;

				case 'cancel_booking':
					$this->action_cancel_booking( $value, $response_url, $message, $team['id'] );
					break;

				case 'view_booking':
					// This is a link button, no action needed.
					break;

				case 'reschedule_booking':
					$this->action_reschedule_booking( $value, $trigger_id, $team['id'] );
					break;
			}
		}

		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Handle view submission.
	 *
	 * @param array $payload Payload data.
	 * @return \WP_REST_Response
	 */
	private function handle_view_submission( $payload ) {
		$view          = $payload['view'] ?? array();
		$callback_id   = $view['callback_id'] ?? '';
		$values        = $view['state']['values'] ?? array();
		$private_metadata = json_decode( $view['private_metadata'] ?? '{}', true );

		switch ( $callback_id ) {
			case 'reschedule_modal':
				return $this->submit_reschedule( $values, $private_metadata );

			case 'quick_book_modal':
				return $this->submit_quick_book( $values, $private_metadata );
		}

		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Handle view closed.
	 *
	 * @param array $payload Payload data.
	 * @return \WP_REST_Response
	 */
	private function handle_view_closed( $payload ) {
		// Modal was closed, nothing to do.
		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Handle shortcut.
	 *
	 * @param array $payload Payload data.
	 * @return \WP_REST_Response
	 */
	private function handle_shortcut( $payload ) {
		$callback_id = $payload['callback_id'] ?? '';
		$trigger_id  = $payload['trigger_id'] ?? '';
		$team        = $payload['team'] ?? array();

		switch ( $callback_id ) {
			case 'quick_book':
				$this->open_quick_book_modal( $trigger_id, $team['id'] );
				break;

			case 'view_today':
				$this->respond_with_today( $payload );
				break;
		}

		return new \WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Action: Confirm booking.
	 *
	 * @param string $booking_id   Booking ID.
	 * @param string $response_url Response URL.
	 * @param array  $message      Original message.
	 * @param string $team_id      Team ID.
	 */
	private function action_confirm_booking( $booking_id, $response_url, $message, $team_id ) {
		$booking_id = absint( $booking_id );
		$booking    = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			$this->api->respond( $response_url, array(
				'replace_original' => false,
				'text'             => ':x: Booking not found.',
			) );
			return;
		}

		// Update booking status.
		wp_update_post( array(
			'ID'          => $booking_id,
			'post_status' => 'bkx-ack',
		) );

		$customer_name = get_post_meta( $booking_id, 'customer_name', true );

		// Update the message.
		$updated_message = $this->update_message_after_action(
			$message,
			sprintf(
				':white_check_mark: Confirmed by <@%s>',
				$_POST['user']['id'] ?? 'someone'
			)
		);

		$this->api->respond( $response_url, array(
			'replace_original' => true,
			'text'             => $updated_message['text'] ?? '',
			'attachments'      => $updated_message['attachments'] ?? array(),
		) );
	}

	/**
	 * Action: Cancel booking.
	 *
	 * @param string $booking_id   Booking ID.
	 * @param string $response_url Response URL.
	 * @param array  $message      Original message.
	 * @param string $team_id      Team ID.
	 */
	private function action_cancel_booking( $booking_id, $response_url, $message, $team_id ) {
		$booking_id = absint( $booking_id );
		$booking    = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			$this->api->respond( $response_url, array(
				'replace_original' => false,
				'text'             => ':x: Booking not found.',
			) );
			return;
		}

		// Update booking status.
		wp_update_post( array(
			'ID'          => $booking_id,
			'post_status' => 'bkx-cancelled',
		) );

		// Update the message.
		$updated_message = $this->update_message_after_action(
			$message,
			sprintf(
				':x: Cancelled by <@%s>',
				$_POST['user']['id'] ?? 'someone'
			)
		);

		$this->api->respond( $response_url, array(
			'replace_original' => true,
			'text'             => $updated_message['text'] ?? '',
			'attachments'      => $updated_message['attachments'] ?? array(),
		) );
	}

	/**
	 * Action: Reschedule booking (opens modal).
	 *
	 * @param string $booking_id Booking ID.
	 * @param string $trigger_id Trigger ID.
	 * @param string $team_id    Team ID.
	 */
	private function action_reschedule_booking( $booking_id, $trigger_id, $team_id ) {
		$booking_id = absint( $booking_id );
		$booking    = get_post( $booking_id );

		if ( ! $booking ) {
			return;
		}

		$current_date = get_post_meta( $booking_id, 'booking_date', true );
		$current_time = get_post_meta( $booking_id, 'booking_time', true );

		$token = $this->get_team_token( $team_id );

		if ( ! $token ) {
			return;
		}

		$view = array(
			'type'             => 'modal',
			'callback_id'      => 'reschedule_modal',
			'private_metadata' => wp_json_encode( array( 'booking_id' => $booking_id ) ),
			'title'            => array(
				'type'  => 'plain_text',
				'text'  => 'Reschedule Booking',
				'emoji' => true,
			),
			'submit'           => array(
				'type' => 'plain_text',
				'text' => 'Reschedule',
			),
			'close'            => array(
				'type' => 'plain_text',
				'text' => 'Cancel',
			),
			'blocks'           => array(
				array(
					'type'     => 'input',
					'block_id' => 'new_date',
					'element'  => array(
						'type'         => 'datepicker',
						'action_id'    => 'date_picker',
						'initial_date' => $current_date,
						'placeholder'  => array(
							'type' => 'plain_text',
							'text' => 'Select a date',
						),
					),
					'label'    => array(
						'type' => 'plain_text',
						'text' => 'New Date',
					),
				),
				array(
					'type'     => 'input',
					'block_id' => 'new_time',
					'element'  => array(
						'type'         => 'timepicker',
						'action_id'    => 'time_picker',
						'initial_time' => $current_time ? substr( $current_time, 0, 5 ) : '09:00',
						'placeholder'  => array(
							'type' => 'plain_text',
							'text' => 'Select a time',
						),
					),
					'label'    => array(
						'type' => 'plain_text',
						'text' => 'New Time',
					),
				),
			),
		);

		$this->api->open_view( $trigger_id, $view, $token );
	}

	/**
	 * Submit reschedule form.
	 *
	 * @param array $values           Form values.
	 * @param array $private_metadata Private metadata.
	 * @return \WP_REST_Response
	 */
	private function submit_reschedule( $values, $private_metadata ) {
		$booking_id = $private_metadata['booking_id'] ?? 0;
		$new_date   = $values['new_date']['date_picker']['selected_date'] ?? '';
		$new_time   = $values['new_time']['time_picker']['selected_time'] ?? '';

		if ( ! $booking_id || ! $new_date || ! $new_time ) {
			return new \WP_REST_Response( array(
				'response_action' => 'errors',
				'errors'          => array(
					'new_date' => 'Please select a valid date',
					'new_time' => 'Please select a valid time',
				),
			), 200 );
		}

		// Update booking.
		update_post_meta( $booking_id, 'booking_date', $new_date );
		update_post_meta( $booking_id, 'booking_time', $new_time . ':00' );

		return new \WP_REST_Response( array(
			'response_action' => 'clear',
		), 200 );
	}

	/**
	 * Open quick book modal.
	 *
	 * @param string $trigger_id Trigger ID.
	 * @param string $team_id    Team ID.
	 */
	private function open_quick_book_modal( $trigger_id, $team_id ) {
		$token = $this->get_team_token( $team_id );

		if ( ! $token ) {
			return;
		}

		// Get services.
		$services = get_posts( array(
			'post_type'      => 'bkx_base',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
		) );

		$service_options = array();
		foreach ( $services as $service ) {
			$service_options[] = array(
				'text'  => array(
					'type' => 'plain_text',
					'text' => $service->post_title,
				),
				'value' => (string) $service->ID,
			);
		}

		$view = array(
			'type'        => 'modal',
			'callback_id' => 'quick_book_modal',
			'title'       => array(
				'type' => 'plain_text',
				'text' => 'Quick Book',
			),
			'submit'      => array(
				'type' => 'plain_text',
				'text' => 'Book',
			),
			'close'       => array(
				'type' => 'plain_text',
				'text' => 'Cancel',
			),
			'blocks'      => array(
				array(
					'type'     => 'input',
					'block_id' => 'customer_name',
					'element'  => array(
						'type'        => 'plain_text_input',
						'action_id'   => 'customer_name_input',
						'placeholder' => array(
							'type' => 'plain_text',
							'text' => 'Enter customer name',
						),
					),
					'label'    => array(
						'type' => 'plain_text',
						'text' => 'Customer Name',
					),
				),
				array(
					'type'     => 'input',
					'block_id' => 'customer_email',
					'optional' => true,
					'element'  => array(
						'type'        => 'plain_text_input',
						'action_id'   => 'customer_email_input',
						'placeholder' => array(
							'type' => 'plain_text',
							'text' => 'Enter email (optional)',
						),
					),
					'label'    => array(
						'type' => 'plain_text',
						'text' => 'Customer Email',
					),
				),
				array(
					'type'     => 'input',
					'block_id' => 'service',
					'element'  => array(
						'type'        => 'static_select',
						'action_id'   => 'service_select',
						'placeholder' => array(
							'type' => 'plain_text',
							'text' => 'Select a service',
						),
						'options'     => $service_options,
					),
					'label'    => array(
						'type' => 'plain_text',
						'text' => 'Service',
					),
				),
				array(
					'type'     => 'input',
					'block_id' => 'booking_date',
					'element'  => array(
						'type'      => 'datepicker',
						'action_id' => 'date_select',
					),
					'label'    => array(
						'type' => 'plain_text',
						'text' => 'Date',
					),
				),
				array(
					'type'     => 'input',
					'block_id' => 'booking_time',
					'element'  => array(
						'type'      => 'timepicker',
						'action_id' => 'time_select',
					),
					'label'    => array(
						'type' => 'plain_text',
						'text' => 'Time',
					),
				),
			),
		);

		$this->api->open_view( $trigger_id, $view, $token );
	}

	/**
	 * Submit quick book form.
	 *
	 * @param array $values           Form values.
	 * @param array $private_metadata Private metadata.
	 * @return \WP_REST_Response
	 */
	private function submit_quick_book( $values, $private_metadata ) {
		$customer_name  = $values['customer_name']['customer_name_input']['value'] ?? '';
		$customer_email = $values['customer_email']['customer_email_input']['value'] ?? '';
		$service_id     = $values['service']['service_select']['selected_option']['value'] ?? '';
		$booking_date   = $values['booking_date']['date_select']['selected_date'] ?? '';
		$booking_time   = $values['booking_time']['time_select']['selected_time'] ?? '';

		$errors = array();

		if ( empty( $customer_name ) ) {
			$errors['customer_name'] = 'Customer name is required';
		}

		if ( empty( $service_id ) ) {
			$errors['service'] = 'Please select a service';
		}

		if ( empty( $booking_date ) ) {
			$errors['booking_date'] = 'Please select a date';
		}

		if ( empty( $booking_time ) ) {
			$errors['booking_time'] = 'Please select a time';
		}

		if ( ! empty( $errors ) ) {
			return new \WP_REST_Response( array(
				'response_action' => 'errors',
				'errors'          => $errors,
			), 200 );
		}

		// Create booking.
		$booking_id = wp_insert_post( array(
			'post_type'   => 'bkx_booking',
			'post_status' => 'bkx-pending',
			'post_title'  => sprintf(
				__( 'Booking by %s on %s', 'bkx-slack' ),
				$customer_name,
				$booking_date
			),
		) );

		if ( is_wp_error( $booking_id ) ) {
			return new \WP_REST_Response( array(
				'response_action' => 'errors',
				'errors'          => array(
					'customer_name' => 'Failed to create booking',
				),
			), 200 );
		}

		// Add meta.
		update_post_meta( $booking_id, 'customer_name', $customer_name );
		update_post_meta( $booking_id, 'customer_email', $customer_email );
		update_post_meta( $booking_id, 'base_id', $service_id );
		update_post_meta( $booking_id, 'booking_date', $booking_date );
		update_post_meta( $booking_id, 'booking_time', $booking_time . ':00' );
		update_post_meta( $booking_id, 'booking_source', 'slack' );

		return new \WP_REST_Response( array(
			'response_action' => 'clear',
		), 200 );
	}

	/**
	 * Update message after an action.
	 *
	 * @param array  $message Original message.
	 * @param string $status  Status text to add.
	 * @return array
	 */
	private function update_message_after_action( $message, $status ) {
		// Remove action buttons from attachments.
		if ( isset( $message['attachments'] ) ) {
			foreach ( $message['attachments'] as &$attachment ) {
				if ( isset( $attachment['blocks'] ) ) {
					$attachment['blocks'] = array_filter( $attachment['blocks'], function( $block ) {
						return 'actions' !== ( $block['type'] ?? '' );
					} );

					// Add status context.
					$attachment['blocks'][] = array(
						'type'     => 'context',
						'elements' => array(
							array(
								'type' => 'mrkdwn',
								'text' => $status,
							),
						),
					);

					$attachment['blocks'] = array_values( $attachment['blocks'] );
				}
			}
		}

		return $message;
	}

	/**
	 * Get team token.
	 *
	 * @param string $team_id Team ID.
	 * @return string|null
	 */
	private function get_team_token( $team_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$workspace = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}bkx_slack_workspaces WHERE team_id = %s AND status = 'active'",
			$team_id
		) );

		if ( ! $workspace ) {
			return null;
		}

		return $this->decrypt_token( $workspace->access_token );
	}

	/**
	 * Decrypt stored token.
	 *
	 * @param string $encrypted Encrypted token.
	 * @return string
	 */
	private function decrypt_token( $encrypted ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return base64_decode( $encrypted );
		}

		$key  = $this->get_encryption_key();
		$data = base64_decode( $encrypted );
		$iv   = substr( $data, 0, 16 );
		$cipher = substr( $data, 16 );

		return openssl_decrypt( $cipher, 'AES-256-CBC', $key, 0, $iv );
	}

	/**
	 * Get encryption key.
	 *
	 * @return string
	 */
	private function get_encryption_key() {
		$key = defined( 'BKX_SLACK_ENCRYPTION_KEY' ) ? BKX_SLACK_ENCRYPTION_KEY : AUTH_KEY;
		return hash( 'sha256', $key, true );
	}
}
