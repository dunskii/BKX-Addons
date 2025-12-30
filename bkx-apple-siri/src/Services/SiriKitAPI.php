<?php
/**
 * SiriKit API Service.
 *
 * Handles communication with Apple's SiriKit services.
 *
 * @package BookingX\AppleSiri
 */

namespace BookingX\AppleSiri\Services;

defined( 'ABSPATH' ) || exit;

/**
 * SiriKitAPI class.
 */
class SiriKitAPI {

	/**
	 * Addon instance.
	 *
	 * @var \BookingX\AppleSiri\AppleSiriAddon
	 */
	private $addon;

	/**
	 * Apple API base URL.
	 *
	 * @var string
	 */
	private $api_base = 'https://api.apple.com';

	/**
	 * Constructor.
	 *
	 * @param \BookingX\AppleSiri\AppleSiriAddon $addon Addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Donate shortcut to Siri.
	 *
	 * @param array $shortcut Shortcut data.
	 * @param int   $user_id  WordPress user ID.
	 * @return array
	 */
	public function donate_shortcut( $shortcut, $user_id ) {
		// Get Apple user ID.
		$apple_user_id = get_user_meta( $user_id, 'apple_user_id', true );

		if ( empty( $apple_user_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'User not linked to Apple ID.', 'bkx-apple-siri' ),
			);
		}

		// Store donation for retrieval by iOS app.
		$donation = array(
			'shortcut'      => $shortcut,
			'user_id'       => $user_id,
			'apple_user_id' => $apple_user_id,
			'created_at'    => current_time( 'mysql' ),
			'expires_at'    => gmdate( 'Y-m-d H:i:s', strtotime( '+7 days' ) ),
		);

		$donations   = get_option( 'bkx_apple_siri_donations', array() );
		$donations[] = $donation;

		// Keep only last 100 donations.
		if ( count( $donations ) > 100 ) {
			$donations = array_slice( $donations, -100 );
		}

		update_option( 'bkx_apple_siri_donations', $donations );

		return array(
			'success' => true,
			'message' => __( 'Shortcut donation queued.', 'bkx-apple-siri' ),
		);
	}

	/**
	 * Get pending donations for a user.
	 *
	 * @param string $apple_user_id Apple user ID.
	 * @return array
	 */
	public function get_pending_donations( $apple_user_id ) {
		$donations = get_option( 'bkx_apple_siri_donations', array() );
		$now       = current_time( 'mysql' );
		$pending   = array();

		foreach ( $donations as $key => $donation ) {
			if ( $donation['apple_user_id'] === $apple_user_id && $donation['expires_at'] > $now ) {
				$pending[] = $donation;
			}
		}

		return $pending;
	}

	/**
	 * Clear donations for a user.
	 *
	 * @param string $apple_user_id Apple user ID.
	 */
	public function clear_donations( $apple_user_id ) {
		$donations = get_option( 'bkx_apple_siri_donations', array() );

		$donations = array_filter(
			$donations,
			function ( $donation ) use ( $apple_user_id ) {
				return $donation['apple_user_id'] !== $apple_user_id;
			}
		);

		update_option( 'bkx_apple_siri_donations', array_values( $donations ) );
	}

	/**
	 * Build intent response for booking confirmation.
	 *
	 * @param array $booking Booking data.
	 * @return array
	 */
	public function build_booking_response( $booking ) {
		return array(
			'responseCode' => 'success',
			'intent'       => array(
				'type'            => 'BookAppointmentIntent',
				'status'          => 'confirmed',
				'bookingID'       => $booking['id'] ?? 0,
				'bookingDate'     => $booking['date'] ?? '',
				'bookingTime'     => $booking['time'] ?? '',
				'serviceName'     => $booking['service_name'] ?? '',
				'providerName'    => $booking['provider_name'] ?? '',
				'confirmationURL' => $booking['confirmation_url'] ?? '',
			),
			'dialog'       => array(
				'speakableText'  => sprintf(
					/* translators: 1: Service name, 2: Date, 3: Time */
					__( 'I\'ve booked your %1$s for %2$s at %3$s.', 'bkx-apple-siri' ),
					$booking['service_name'] ?? __( 'appointment', 'bkx-apple-siri' ),
					$this->format_date_spoken( $booking['date'] ?? '' ),
					$this->format_time_spoken( $booking['time'] ?? '' )
				),
				'displayString'  => sprintf(
					/* translators: 1: Service name, 2: Date, 3: Time */
					__( 'Booked: %1$s on %2$s at %3$s', 'bkx-apple-siri' ),
					$booking['service_name'] ?? __( 'appointment', 'bkx-apple-siri' ),
					$booking['date'] ?? '',
					$booking['time'] ?? ''
				),
				'pronunciationHint' => '',
			),
		);
	}

	/**
	 * Build intent response for availability check.
	 *
	 * @param array $slots Available time slots.
	 * @param string $date  Date checked.
	 * @return array
	 */
	public function build_availability_response( $slots, $date ) {
		$slot_count = count( $slots );

		if ( 0 === $slot_count ) {
			$spoken_text = sprintf(
				/* translators: %s: Date */
				__( 'Sorry, there are no available times on %s.', 'bkx-apple-siri' ),
				$this->format_date_spoken( $date )
			);
		} elseif ( 1 === $slot_count ) {
			$spoken_text = sprintf(
				/* translators: 1: Date, 2: Time */
				__( 'There\'s one available time on %1$s at %2$s.', 'bkx-apple-siri' ),
				$this->format_date_spoken( $date ),
				$this->format_time_spoken( $slots[0]['time'] ?? '' )
			);
		} else {
			$first_slots = array_slice( $slots, 0, 3 );
			$times       = array_map(
				function ( $slot ) {
					return $this->format_time_spoken( $slot['time'] ?? '' );
				},
				$first_slots
			);

			$spoken_text = sprintf(
				/* translators: 1: Count, 2: Date, 3: Time list */
				__( 'There are %1$d available times on %2$s. The first few are %3$s.', 'bkx-apple-siri' ),
				$slot_count,
				$this->format_date_spoken( $date ),
				implode( ', ', $times )
			);
		}

		return array(
			'responseCode' => 'success',
			'intent'       => array(
				'type'      => 'CheckAvailabilityIntent',
				'date'      => $date,
				'slotCount' => $slot_count,
				'slots'     => $slots,
			),
			'dialog'       => array(
				'speakableText' => $spoken_text,
				'displayString' => sprintf(
					/* translators: 1: Count, 2: Date */
					__( '%1$d slots available on %2$s', 'bkx-apple-siri' ),
					$slot_count,
					$date
				),
			),
		);
	}

	/**
	 * Build intent response for cancellation.
	 *
	 * @param array $booking Cancelled booking data.
	 * @return array
	 */
	public function build_cancellation_response( $booking ) {
		return array(
			'responseCode' => 'success',
			'intent'       => array(
				'type'      => 'CancelAppointmentIntent',
				'status'    => 'cancelled',
				'bookingID' => $booking['id'] ?? 0,
			),
			'dialog'       => array(
				'speakableText' => sprintf(
					/* translators: 1: Service name, 2: Date */
					__( 'I\'ve cancelled your %1$s appointment on %2$s.', 'bkx-apple-siri' ),
					$booking['service_name'] ?? __( 'appointment', 'bkx-apple-siri' ),
					$this->format_date_spoken( $booking['date'] ?? '' )
				),
				'displayString' => __( 'Appointment cancelled', 'bkx-apple-siri' ),
			),
		);
	}

	/**
	 * Build intent response for reschedule.
	 *
	 * @param array $booking Rescheduled booking data.
	 * @return array
	 */
	public function build_reschedule_response( $booking ) {
		return array(
			'responseCode' => 'success',
			'intent'       => array(
				'type'        => 'RescheduleAppointmentIntent',
				'status'      => 'rescheduled',
				'bookingID'   => $booking['id'] ?? 0,
				'newDate'     => $booking['new_date'] ?? '',
				'newTime'     => $booking['new_time'] ?? '',
			),
			'dialog'       => array(
				'speakableText' => sprintf(
					/* translators: 1: New date, 2: New time */
					__( 'Done. Your appointment has been rescheduled to %1$s at %2$s.', 'bkx-apple-siri' ),
					$this->format_date_spoken( $booking['new_date'] ?? '' ),
					$this->format_time_spoken( $booking['new_time'] ?? '' )
				),
				'displayString' => sprintf(
					/* translators: 1: New date, 2: New time */
					__( 'Rescheduled to %1$s at %2$s', 'bkx-apple-siri' ),
					$booking['new_date'] ?? '',
					$booking['new_time'] ?? ''
				),
			),
		);
	}

	/**
	 * Build intent response for upcoming appointments.
	 *
	 * @param array $bookings List of upcoming bookings.
	 * @return array
	 */
	public function build_upcoming_response( $bookings ) {
		$count = count( $bookings );

		if ( 0 === $count ) {
			$spoken_text = __( 'You don\'t have any upcoming appointments.', 'bkx-apple-siri' );
		} elseif ( 1 === $count ) {
			$booking     = $bookings[0];
			$spoken_text = sprintf(
				/* translators: 1: Service name, 2: Date, 3: Time */
				__( 'You have one upcoming appointment: %1$s on %2$s at %3$s.', 'bkx-apple-siri' ),
				$booking['service_name'] ?? __( 'appointment', 'bkx-apple-siri' ),
				$this->format_date_spoken( $booking['date'] ?? '' ),
				$this->format_time_spoken( $booking['time'] ?? '' )
			);
		} else {
			$first        = $bookings[0];
			$spoken_text  = sprintf(
				/* translators: %d: Number of appointments */
				__( 'You have %d upcoming appointments.', 'bkx-apple-siri' ),
				$count
			);
			$spoken_text .= ' ' . sprintf(
				/* translators: 1: Service name, 2: Date, 3: Time */
				__( 'The next one is %1$s on %2$s at %3$s.', 'bkx-apple-siri' ),
				$first['service_name'] ?? __( 'an appointment', 'bkx-apple-siri' ),
				$this->format_date_spoken( $first['date'] ?? '' ),
				$this->format_time_spoken( $first['time'] ?? '' )
			);
		}

		return array(
			'responseCode' => 'success',
			'intent'       => array(
				'type'     => 'GetUpcomingAppointmentsIntent',
				'count'    => $count,
				'bookings' => $bookings,
			),
			'dialog'       => array(
				'speakableText' => $spoken_text,
				'displayString' => sprintf(
					/* translators: %d: Number of appointments */
					__( '%d upcoming appointments', 'bkx-apple-siri' ),
					$count
				),
			),
		);
	}

	/**
	 * Build error response.
	 *
	 * @param string $error_code    Error code.
	 * @param string $error_message Error message.
	 * @return array
	 */
	public function build_error_response( $error_code, $error_message ) {
		$spoken_messages = array(
			'invalid_date'       => __( 'Sorry, I couldn\'t understand that date. Please try again.', 'bkx-apple-siri' ),
			'no_availability'    => __( 'Sorry, there are no available times for that date.', 'bkx-apple-siri' ),
			'booking_failed'     => __( 'Sorry, I couldn\'t complete that booking. Please try again later.', 'bkx-apple-siri' ),
			'not_found'          => __( 'I couldn\'t find that appointment.', 'bkx-apple-siri' ),
			'already_cancelled'  => __( 'That appointment has already been cancelled.', 'bkx-apple-siri' ),
			'past_booking'       => __( 'Sorry, I can\'t modify past appointments.', 'bkx-apple-siri' ),
			'permission_denied'  => __( 'Sorry, you don\'t have permission to do that.', 'bkx-apple-siri' ),
			'service_unavailable' => __( 'The booking service is temporarily unavailable. Please try again later.', 'bkx-apple-siri' ),
		);

		$spoken_text = $spoken_messages[ $error_code ] ?? $error_message;

		return array(
			'responseCode' => 'failure',
			'error'        => array(
				'code'    => $error_code,
				'message' => $error_message,
			),
			'dialog'       => array(
				'speakableText' => $spoken_text,
				'displayString' => $error_message,
			),
		);
	}

	/**
	 * Build confirmation required response.
	 *
	 * @param array  $booking_data Proposed booking data.
	 * @param string $prompt       Confirmation prompt.
	 * @return array
	 */
	public function build_confirmation_response( $booking_data, $prompt = '' ) {
		if ( empty( $prompt ) ) {
			$prompt = sprintf(
				/* translators: 1: Service name, 2: Date, 3: Time */
				__( 'Would you like me to book %1$s for %2$s at %3$s?', 'bkx-apple-siri' ),
				$booking_data['service_name'] ?? __( 'this appointment', 'bkx-apple-siri' ),
				$this->format_date_spoken( $booking_data['date'] ?? '' ),
				$this->format_time_spoken( $booking_data['time'] ?? '' )
			);
		}

		return array(
			'responseCode'         => 'requiresConfirmation',
			'intent'               => array(
				'type'        => 'BookAppointmentIntent',
				'status'      => 'pending_confirmation',
				'bookingData' => $booking_data,
			),
			'dialog'               => array(
				'speakableText' => $prompt,
				'displayString' => __( 'Confirm booking?', 'bkx-apple-siri' ),
			),
			'requiresConfirmation' => true,
		);
	}

	/**
	 * Build disambiguation response.
	 *
	 * @param array  $options Options to choose from.
	 * @param string $prompt  Disambiguation prompt.
	 * @return array
	 */
	public function build_disambiguation_response( $options, $prompt ) {
		return array(
			'responseCode'            => 'requiresDisambiguation',
			'intent'                  => array(
				'type'    => 'DisambiguationIntent',
				'options' => $options,
			),
			'dialog'                  => array(
				'speakableText' => $prompt,
				'displayString' => $prompt,
			),
			'requiresDisambiguation' => true,
			'options'                 => $options,
		);
	}

	/**
	 * Format date for spoken output.
	 *
	 * @param string $date Date string.
	 * @return string
	 */
	private function format_date_spoken( $date ) {
		if ( empty( $date ) ) {
			return __( 'an unknown date', 'bkx-apple-siri' );
		}

		$timestamp = strtotime( $date );

		if ( ! $timestamp ) {
			return $date;
		}

		$today    = strtotime( 'today' );
		$tomorrow = strtotime( 'tomorrow' );

		if ( gmdate( 'Y-m-d', $timestamp ) === gmdate( 'Y-m-d', $today ) ) {
			return __( 'today', 'bkx-apple-siri' );
		}

		if ( gmdate( 'Y-m-d', $timestamp ) === gmdate( 'Y-m-d', $tomorrow ) ) {
			return __( 'tomorrow', 'bkx-apple-siri' );
		}

		// Check if same week.
		$diff = ( $timestamp - $today ) / DAY_IN_SECONDS;

		if ( $diff > 0 && $diff < 7 ) {
			/* translators: Day of week, e.g., "Monday" */
			return sprintf( __( 'this %s', 'bkx-apple-siri' ), date_i18n( 'l', $timestamp ) );
		}

		if ( $diff >= 7 && $diff < 14 ) {
			/* translators: Day of week, e.g., "Monday" */
			return sprintf( __( 'next %s', 'bkx-apple-siri' ), date_i18n( 'l', $timestamp ) );
		}

		/* translators: Full date format, e.g., "January 15th" */
		return date_i18n( 'F jS', $timestamp );
	}

	/**
	 * Format time for spoken output.
	 *
	 * @param string $time Time string.
	 * @return string
	 */
	private function format_time_spoken( $time ) {
		if ( empty( $time ) ) {
			return __( 'an unknown time', 'bkx-apple-siri' );
		}

		$timestamp = strtotime( $time );

		if ( ! $timestamp ) {
			return $time;
		}

		$hour   = (int) gmdate( 'G', $timestamp );
		$minute = (int) gmdate( 'i', $timestamp );

		$period = $hour >= 12 ? 'PM' : 'AM';
		$hour12 = $hour % 12;
		if ( 0 === $hour12 ) {
			$hour12 = 12;
		}

		if ( 0 === $minute ) {
			/* translators: 1: Hour, 2: AM/PM */
			return sprintf( __( '%1$d %2$s', 'bkx-apple-siri' ), $hour12, $period );
		}

		if ( 30 === $minute ) {
			/* translators: 1: Hour, 2: AM/PM */
			return sprintf( __( 'half past %1$d %2$s', 'bkx-apple-siri' ), $hour12, $period );
		}

		if ( 15 === $minute ) {
			/* translators: 1: Hour, 2: AM/PM */
			return sprintf( __( 'quarter past %1$d %2$s', 'bkx-apple-siri' ), $hour12, $period );
		}

		if ( 45 === $minute ) {
			/* translators: 1: Hour, 2: AM/PM */
			return sprintf( __( 'quarter to %1$d %2$s', 'bkx-apple-siri' ), ( $hour12 % 12 ) + 1, $period );
		}

		return date_i18n( 'g:i A', $timestamp );
	}

	/**
	 * Register app with Apple.
	 *
	 * @return array
	 */
	public function register_app() {
		// App registration is done through Apple Developer Portal.
		// This method provides the configuration needed.
		$site_url = get_site_url();

		return array(
			'success'       => true,
			'configuration' => array(
				'endpoints'      => array(
					'intent'       => rest_url( 'bkx-apple-siri/v1/intent' ),
					'shortcuts'    => rest_url( 'bkx-apple-siri/v1/shortcuts' ),
					'availability' => rest_url( 'bkx-apple-siri/v1/availability' ),
					'book'         => rest_url( 'bkx-apple-siri/v1/book' ),
				),
				'supportedIntents' => array(
					'BookAppointmentIntent',
					'RescheduleAppointmentIntent',
					'CancelAppointmentIntent',
					'CheckAvailabilityIntent',
					'GetUpcomingAppointmentsIntent',
				),
				'appAssociation' => array(
					'webcredentials' => array(
						'apps' => array(
							$this->addon->get_setting( 'team_id' ) . '.' . $this->addon->get_setting( 'bundle_identifier' ),
						),
					),
					'applinks'       => array(
						'details' => array(
							array(
								'appIDs'     => array(
									$this->addon->get_setting( 'team_id' ) . '.' . $this->addon->get_setting( 'bundle_identifier' ),
								),
								'components' => array(
									array(
										'/'       => '/book/*',
										'comment' => 'Booking deep links',
									),
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Generate Apple App Site Association file content.
	 *
	 * @return array
	 */
	public function get_apple_app_site_association() {
		$team_id   = $this->addon->get_setting( 'team_id' );
		$bundle_id = $this->addon->get_setting( 'bundle_identifier' );

		if ( empty( $team_id ) || empty( $bundle_id ) ) {
			return array();
		}

		$app_id = $team_id . '.' . $bundle_id;

		return array(
			'applinks'       => array(
				'apps'    => array(),
				'details' => array(
					array(
						'appID' => $app_id,
						'paths' => array( '/book/*', '/booking/*', '/appointments/*' ),
					),
				),
			),
			'webcredentials' => array(
				'apps' => array( $app_id ),
			),
			'appclips'       => array(
				'apps' => array( $app_id . '.Clip' ),
			),
		);
	}
}
