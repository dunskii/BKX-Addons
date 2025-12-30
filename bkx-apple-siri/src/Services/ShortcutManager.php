<?php
/**
 * Shortcut Manager Service.
 *
 * Manages Apple Shortcuts integration for BookingX.
 *
 * @package BookingX\AppleSiri
 */

namespace BookingX\AppleSiri\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ShortcutManager class.
 */
class ShortcutManager {

	/**
	 * Addon instance.
	 *
	 * @var \BookingX\AppleSiri\AppleSiriAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @param \BookingX\AppleSiri\AppleSiriAddon $addon Addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Get available shortcuts.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_shortcuts( $request ) {
		if ( ! $this->addon->get_setting( 'shortcuts_enabled', true ) ) {
			return new \WP_REST_Response( array( 'shortcuts' => array() ), 200 );
		}

		$shortcuts = array();
		$phrases   = $this->addon->get_setting( 'voice_phrases', array() );

		// Book appointment shortcut.
		$shortcuts[] = array(
			'id'                   => 'bkx_book_appointment',
			'title'                => __( 'Book Appointment', 'bkx-apple-siri' ),
			'subtitle'             => __( 'Schedule a new appointment', 'bkx-apple-siri' ),
			'suggestedInvocationPhrase' => $this->process_phrase( $phrases['book'] ?? 'Book an appointment' ),
			'shortcutDescription'  => __( 'Opens the booking form to schedule a new appointment', 'bkx-apple-siri' ),
			'intentIdentifier'     => 'BookAppointmentIntent',
			'icon'                 => array(
				'systemName' => 'calendar.badge.plus',
				'color'      => '#2271b1',
			),
			'parameters'           => array(
				array(
					'name'     => 'service',
					'type'     => 'String',
					'required' => false,
				),
				array(
					'name'     => 'date',
					'type'     => 'Date',
					'required' => false,
				),
				array(
					'name'     => 'time',
					'type'     => 'Time',
					'required' => false,
				),
			),
		);

		// Check availability shortcut.
		$shortcuts[] = array(
			'id'                   => 'bkx_check_availability',
			'title'                => __( 'Check Availability', 'bkx-apple-siri' ),
			'subtitle'             => __( 'See available appointment times', 'bkx-apple-siri' ),
			'suggestedInvocationPhrase' => $this->process_phrase( $phrases['check_availability'] ?? 'Check availability' ),
			'shortcutDescription'  => __( 'Shows available times for booking', 'bkx-apple-siri' ),
			'intentIdentifier'     => 'CheckAvailabilityIntent',
			'icon'                 => array(
				'systemName' => 'clock',
				'color'      => '#00a32a',
			),
			'parameters'           => array(
				array(
					'name'     => 'date',
					'type'     => 'Date',
					'required' => false,
				),
			),
		);

		// Reschedule shortcut.
		$shortcuts[] = array(
			'id'                   => 'bkx_reschedule',
			'title'                => __( 'Reschedule Appointment', 'bkx-apple-siri' ),
			'subtitle'             => __( 'Change your appointment time', 'bkx-apple-siri' ),
			'suggestedInvocationPhrase' => $this->process_phrase( $phrases['reschedule'] ?? 'Reschedule my appointment' ),
			'shortcutDescription'  => __( 'Reschedule an existing appointment', 'bkx-apple-siri' ),
			'intentIdentifier'     => 'RescheduleAppointmentIntent',
			'icon'                 => array(
				'systemName' => 'calendar.badge.clock',
				'color'      => '#dba617',
			),
			'parameters'           => array(
				array(
					'name'     => 'booking_id',
					'type'     => 'Integer',
					'required' => false,
				),
				array(
					'name'     => 'new_date',
					'type'     => 'Date',
					'required' => false,
				),
				array(
					'name'     => 'new_time',
					'type'     => 'Time',
					'required' => false,
				),
			),
		);

		// Cancel shortcut.
		$shortcuts[] = array(
			'id'                   => 'bkx_cancel',
			'title'                => __( 'Cancel Appointment', 'bkx-apple-siri' ),
			'subtitle'             => __( 'Cancel an upcoming appointment', 'bkx-apple-siri' ),
			'suggestedInvocationPhrase' => $this->process_phrase( $phrases['cancel'] ?? 'Cancel my appointment' ),
			'shortcutDescription'  => __( 'Cancel an existing appointment', 'bkx-apple-siri' ),
			'intentIdentifier'     => 'CancelAppointmentIntent',
			'icon'                 => array(
				'systemName' => 'calendar.badge.minus',
				'color'      => '#d63638',
			),
			'parameters'           => array(
				array(
					'name'     => 'booking_id',
					'type'     => 'Integer',
					'required' => false,
				),
			),
		);

		// Upcoming appointments shortcut.
		$shortcuts[] = array(
			'id'                   => 'bkx_upcoming',
			'title'                => __( 'My Appointments', 'bkx-apple-siri' ),
			'subtitle'             => __( 'View your upcoming appointments', 'bkx-apple-siri' ),
			'suggestedInvocationPhrase' => 'Show my appointments',
			'shortcutDescription'  => __( 'Lists all your upcoming appointments', 'bkx-apple-siri' ),
			'intentIdentifier'     => 'GetUpcomingAppointmentsIntent',
			'icon'                 => array(
				'systemName' => 'list.bullet.rectangle',
				'color'      => '#7c3aed',
			),
			'parameters'           => array(),
		);

		return new \WP_REST_Response(
			array(
				'shortcuts' => $shortcuts,
				'appInfo'   => array(
					'bundleIdentifier' => $this->addon->get_setting( 'bundle_identifier', '' ),
					'name'             => get_bloginfo( 'name' ),
					'version'          => BKX_APPLE_SIRI_VERSION,
				),
			),
			200
		);
	}

	/**
	 * Generate a shortcut file.
	 *
	 * @param string $type Shortcut type.
	 * @return array|null
	 */
	public function generate_shortcut( $type ) {
		$shortcuts = array(
			'book'              => $this->generate_book_shortcut(),
			'check_availability' => $this->generate_availability_shortcut(),
			'reschedule'        => $this->generate_reschedule_shortcut(),
			'cancel'            => $this->generate_cancel_shortcut(),
			'upcoming'          => $this->generate_upcoming_shortcut(),
		);

		return $shortcuts[ $type ] ?? null;
	}

	/**
	 * Generate book appointment shortcut.
	 *
	 * @return array
	 */
	private function generate_book_shortcut() {
		$api_url = rest_url( 'bkx-apple-siri/v1/book' );

		return array(
			'WFWorkflowName'          => __( 'Book Appointment', 'bkx-apple-siri' ),
			'WFWorkflowMinimumClientVersion' => 900,
			'WFWorkflowIcon'          => array(
				'WFWorkflowIconStartColor' => 2846468607,
				'WFWorkflowIconGlyphNumber' => 59722,
			),
			'WFWorkflowInputContentItemClasses' => array(),
			'WFWorkflowActions'       => array(
				// Ask for service.
				array(
					'WFWorkflowActionIdentifier' => 'is.workflow.actions.ask',
					'WFWorkflowActionParameters' => array(
						'WFAskActionPrompt'      => __( 'Which service would you like to book?', 'bkx-apple-siri' ),
						'WFAskActionDefaultAnswer' => '',
					),
				),
				array(
					'WFWorkflowActionIdentifier' => 'is.workflow.actions.setvariable',
					'WFWorkflowActionParameters' => array(
						'WFVariableName' => 'Service',
					),
				),
				// Ask for date.
				array(
					'WFWorkflowActionIdentifier' => 'is.workflow.actions.ask',
					'WFWorkflowActionParameters' => array(
						'WFAskActionPrompt'      => __( 'When would you like to book?', 'bkx-apple-siri' ),
						'WFInputType'            => 'Date',
					),
				),
				array(
					'WFWorkflowActionIdentifier' => 'is.workflow.actions.setvariable',
					'WFWorkflowActionParameters' => array(
						'WFVariableName' => 'Date',
					),
				),
				// Make API request.
				array(
					'WFWorkflowActionIdentifier' => 'is.workflow.actions.downloadurl',
					'WFWorkflowActionParameters' => array(
						'WFURL'          => $api_url,
						'WFHTTPMethod'   => 'POST',
						'WFHTTPBodyType' => 'JSON',
						'WFJSONValues'   => array(
							'service' => array(
								'WFSerializationType' => 'WFTextTokenAttachment',
								'Value'               => array(
									'string'     => '',
									'attachmentsByRange' => array(),
								),
							),
						),
					),
				),
				// Show result.
				array(
					'WFWorkflowActionIdentifier' => 'is.workflow.actions.showresult',
					'WFWorkflowActionParameters' => array(
						'Text' => array(
							'WFSerializationType' => 'WFTextTokenString',
							'Value'               => array(
								'string' => __( 'Booking confirmed!', 'bkx-apple-siri' ),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Generate availability shortcut.
	 *
	 * @return array
	 */
	private function generate_availability_shortcut() {
		$api_url = rest_url( 'bkx-apple-siri/v1/availability' );

		return array(
			'WFWorkflowName'          => __( 'Check Availability', 'bkx-apple-siri' ),
			'WFWorkflowMinimumClientVersion' => 900,
			'WFWorkflowIcon'          => array(
				'WFWorkflowIconStartColor' => 431817727,
				'WFWorkflowIconGlyphNumber' => 59650,
			),
			'WFWorkflowActions'       => array(
				// Get current date.
				array(
					'WFWorkflowActionIdentifier' => 'is.workflow.actions.date',
					'WFWorkflowActionParameters' => array(),
				),
				array(
					'WFWorkflowActionIdentifier' => 'is.workflow.actions.setvariable',
					'WFWorkflowActionParameters' => array(
						'WFVariableName' => 'Today',
					),
				),
				// Make API request.
				array(
					'WFWorkflowActionIdentifier' => 'is.workflow.actions.downloadurl',
					'WFWorkflowActionParameters' => array(
						'WFURL'        => $api_url . '?date={{Today}}',
						'WFHTTPMethod' => 'GET',
					),
				),
				// Parse and show results.
				array(
					'WFWorkflowActionIdentifier' => 'is.workflow.actions.getvalueforkey',
					'WFWorkflowActionParameters' => array(
						'WFDictionaryKey' => 'slots',
					),
				),
				array(
					'WFWorkflowActionIdentifier' => 'is.workflow.actions.showresult',
					'WFWorkflowActionParameters' => array(),
				),
			),
		);
	}

	/**
	 * Generate reschedule shortcut.
	 *
	 * @return array
	 */
	private function generate_reschedule_shortcut() {
		return array(
			'WFWorkflowName'          => __( 'Reschedule Appointment', 'bkx-apple-siri' ),
			'WFWorkflowMinimumClientVersion' => 900,
			'WFWorkflowIcon'          => array(
				'WFWorkflowIconStartColor' => 4282601983,
				'WFWorkflowIconGlyphNumber' => 59722,
			),
			'WFWorkflowActions'       => array(),
		);
	}

	/**
	 * Generate cancel shortcut.
	 *
	 * @return array
	 */
	private function generate_cancel_shortcut() {
		return array(
			'WFWorkflowName'          => __( 'Cancel Appointment', 'bkx-apple-siri' ),
			'WFWorkflowMinimumClientVersion' => 900,
			'WFWorkflowIcon'          => array(
				'WFWorkflowIconStartColor' => 4282071039,
				'WFWorkflowIconGlyphNumber' => 59722,
			),
			'WFWorkflowActions'       => array(),
		);
	}

	/**
	 * Generate upcoming appointments shortcut.
	 *
	 * @return array
	 */
	private function generate_upcoming_shortcut() {
		return array(
			'WFWorkflowName'          => __( 'My Appointments', 'bkx-apple-siri' ),
			'WFWorkflowMinimumClientVersion' => 900,
			'WFWorkflowIcon'          => array(
				'WFWorkflowIconStartColor' => 2071128575,
				'WFWorkflowIconGlyphNumber' => 59692,
			),
			'WFWorkflowActions'       => array(),
		);
	}

	/**
	 * Donate booking shortcut after successful booking.
	 *
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 */
	public function donate_booking_shortcut( $booking_id, $booking_data ) {
		// This would integrate with the iOS app via push notification.
		// The iOS app would then donate the shortcut using INInteraction.
		$donation = array(
			'type'       => 'booking_completed',
			'booking_id' => $booking_id,
			'service'    => $booking_data['service_name'] ?? '',
			'date'       => $booking_data['date'] ?? '',
			'time'       => $booking_data['time'] ?? '',
			'shortcut'   => array(
				'suggestedInvocationPhrase' => sprintf(
					/* translators: %s: Service name */
					__( 'Book another %s', 'bkx-apple-siri' ),
					$booking_data['service_name'] ?? __( 'appointment', 'bkx-apple-siri' )
				),
			),
		);

		// Store for later retrieval by iOS app.
		$pending_donations   = get_option( 'bkx_apple_siri_pending_donations', array() );
		$pending_donations[] = $donation;
		update_option( 'bkx_apple_siri_pending_donations', $pending_donations );
	}

	/**
	 * Update booking shortcut on status change.
	 *
	 * @param int    $booking_id Booking ID.
	 * @param string $status     New status.
	 */
	public function update_booking_shortcut( $booking_id, $status ) {
		if ( in_array( $status, array( 'cancelled', 'completed' ), true ) ) {
			// Delete associated shortcut donation.
			$this->delete_booking_shortcut( $booking_id );
		}
	}

	/**
	 * Delete booking shortcut.
	 *
	 * @param int $booking_id Booking ID.
	 */
	public function delete_booking_shortcut( $booking_id ) {
		$pending_donations = get_option( 'bkx_apple_siri_pending_donations', array() );

		$pending_donations = array_filter(
			$pending_donations,
			function ( $donation ) use ( $booking_id ) {
				return ( $donation['booking_id'] ?? 0 ) !== $booking_id;
			}
		);

		update_option( 'bkx_apple_siri_pending_donations', array_values( $pending_donations ) );
	}

	/**
	 * Process phrase with placeholders.
	 *
	 * @param string $phrase Phrase with placeholders.
	 * @return string
	 */
	private function process_phrase( $phrase ) {
		$replacements = array(
			'{business_name}' => get_bloginfo( 'name' ),
			'{service_name}'  => __( 'appointment', 'bkx-apple-siri' ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $phrase );
	}
}
