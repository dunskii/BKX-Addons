<?php
/**
 * Discord Bot Handler.
 *
 * Handles Discord slash commands and interactions.
 *
 * @package BookingX\Discord
 */

namespace BookingX\Discord\Services;

defined( 'ABSPATH' ) || exit;

/**
 * BotHandler class.
 */
class BotHandler {

	/**
	 * Interaction types.
	 */
	private const INTERACTION_PING              = 1;
	private const INTERACTION_APPLICATION_COMMAND = 2;
	private const INTERACTION_MESSAGE_COMPONENT   = 3;
	private const INTERACTION_MODAL_SUBMIT        = 5;

	/**
	 * Response types.
	 */
	private const RESPONSE_PONG                     = 1;
	private const RESPONSE_CHANNEL_MESSAGE          = 4;
	private const RESPONSE_DEFERRED_CHANNEL_MESSAGE = 5;
	private const RESPONSE_DEFERRED_UPDATE          = 6;
	private const RESPONSE_UPDATE_MESSAGE           = 7;
	private const RESPONSE_MODAL                    = 9;

	/**
	 * Discord API.
	 *
	 * @var DiscordApi
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param DiscordApi $api Discord API.
	 */
	public function __construct( DiscordApi $api ) {
		$this->api = $api;
	}

	/**
	 * Handle incoming interaction.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function handle_interaction( $request ) {
		$body = $request->get_body();
		$data = json_decode( $body, true );

		// Verify signature.
		$signature = $request->get_header( 'X-Signature-Ed25519' );
		$timestamp = $request->get_header( 'X-Signature-Timestamp' );

		$bot = $this->get_active_bot();

		if ( ! $bot ) {
			return new \WP_REST_Response( array( 'error' => 'No active bot' ), 401 );
		}

		$public_key = $this->decrypt_value( $bot->public_key );

		if ( ! $this->api->verify_interaction( $public_key, $signature, $timestamp, $body ) ) {
			return new \WP_REST_Response( array( 'error' => 'Invalid signature' ), 401 );
		}

		// Handle by interaction type.
		switch ( $data['type'] ) {
			case self::INTERACTION_PING:
				return new \WP_REST_Response( array( 'type' => self::RESPONSE_PONG ) );

			case self::INTERACTION_APPLICATION_COMMAND:
				return $this->handle_command( $data );

			case self::INTERACTION_MESSAGE_COMPONENT:
				return $this->handle_component( $data );

			case self::INTERACTION_MODAL_SUBMIT:
				return $this->handle_modal( $data );
		}

		return new \WP_REST_Response( array( 'error' => 'Unknown interaction type' ), 400 );
	}

	/**
	 * Handle slash command.
	 *
	 * @param array $data Interaction data.
	 * @return \WP_REST_Response
	 */
	private function handle_command( $data ) {
		$command = $data['data']['name'] ?? '';
		$options = $this->parse_options( $data['data']['options'] ?? array() );

		switch ( $command ) {
			case 'bookings':
				return $this->command_bookings( $options );

			case 'booking':
				return $this->command_booking( $options );

			case 'stats':
				return $this->command_stats( $options );

			case 'search':
				return $this->command_search( $options );

			default:
				return $this->respond_message( 'Unknown command.' );
		}
	}

	/**
	 * Handle component interaction (buttons, selects).
	 *
	 * @param array $data Interaction data.
	 * @return \WP_REST_Response
	 */
	private function handle_component( $data ) {
		$custom_id = $data['data']['custom_id'] ?? '';
		$parts     = explode( ':', $custom_id );
		$action    = $parts[0] ?? '';
		$param     = $parts[1] ?? '';

		switch ( $action ) {
			case 'view_booking':
				return $this->show_booking_details( (int) $param );

			case 'confirm_booking':
				return $this->confirm_booking( (int) $param );

			case 'cancel_booking':
				return $this->cancel_booking( (int) $param );

			default:
				return $this->respond_message( 'Unknown action.' );
		}
	}

	/**
	 * Handle modal submission.
	 *
	 * @param array $data Interaction data.
	 * @return \WP_REST_Response
	 */
	private function handle_modal( $data ) {
		$custom_id = $data['data']['custom_id'] ?? '';

		if ( 'search_booking' === $custom_id ) {
			$query = $data['data']['components'][0]['components'][0]['value'] ?? '';
			return $this->search_bookings( $query );
		}

		return $this->respond_message( 'Modal processed.' );
	}

	/**
	 * Command: /bookings [today|upcoming|all].
	 *
	 * @param array $options Command options.
	 * @return \WP_REST_Response
	 */
	private function command_bookings( $options ) {
		$filter = $options['filter'] ?? 'today';

		$args = array(
			'post_type'      => 'bkx_booking',
			'post_status'    => array( 'bkx-pending', 'bkx-ack' ),
			'posts_per_page' => 10,
			'orderby'        => 'meta_value',
			'meta_key'       => 'booking_date',
			'order'          => 'ASC',
		);

		$today = current_time( 'Y-m-d' );

		if ( 'today' === $filter ) {
			$args['meta_query'] = array(
				array(
					'key'   => 'booking_date',
					'value' => $today,
				),
			);
		} elseif ( 'upcoming' === $filter ) {
			$args['meta_query'] = array(
				array(
					'key'     => 'booking_date',
					'value'   => $today,
					'compare' => '>=',
				),
			);
		}

		$bookings = get_posts( $args );

		if ( empty( $bookings ) ) {
			return $this->respond_message( 'No bookings found.' );
		}

		$fields = array();
		foreach ( $bookings as $booking ) {
			$date    = get_post_meta( $booking->ID, 'booking_date', true );
			$time    = get_post_meta( $booking->ID, 'booking_time', true );
			$service = get_the_title( get_post_meta( $booking->ID, 'base_id', true ) );
			$customer = get_post_meta( $booking->ID, 'customer_name', true );

			$fields[] = array(
				'name'   => "#{$booking->ID} - {$customer}",
				'value'  => "{$service}\n{$date} at {$time}",
				'inline' => true,
			);
		}

		$embed = $this->api->build_embed( array(
			'title'  => ucfirst( $filter ) . ' Bookings',
			'color'  => '#5865F2',
			'fields' => $fields,
			'footer' => 'Showing ' . count( $bookings ) . ' bookings',
		) );

		return $this->respond_embed( $embed );
	}

	/**
	 * Command: /booking <id>.
	 *
	 * @param array $options Command options.
	 * @return \WP_REST_Response
	 */
	private function command_booking( $options ) {
		$booking_id = (int) ( $options['id'] ?? 0 );

		if ( ! $booking_id ) {
			return $this->respond_message( 'Please provide a booking ID.' );
		}

		return $this->show_booking_details( $booking_id );
	}

	/**
	 * Command: /stats.
	 *
	 * @param array $options Command options.
	 * @return \WP_REST_Response
	 */
	private function command_stats( $options ) {
		global $wpdb;

		$today     = current_time( 'Y-m-d' );
		$week_ago  = date( 'Y-m-d', strtotime( '-7 days' ) );
		$month_ago = date( 'Y-m-d', strtotime( '-30 days' ) );

		// Today's bookings.
		$today_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = 'booking_date'
			WHERE p.post_type = 'bkx_booking' AND m.meta_value = %s",
			$today
		) );

		// This week.
		$week_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = 'booking_date'
			WHERE p.post_type = 'bkx_booking' AND m.meta_value >= %s",
			$week_ago
		) );

		// This month revenue.
		$revenue = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(m2.meta_value) FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = 'booking_date'
			JOIN {$wpdb->postmeta} m2 ON p.ID = m2.post_id AND m2.meta_key = 'booking_total'
			WHERE p.post_type = 'bkx_booking'
			AND p.post_status = 'bkx-completed'
			AND m.meta_value >= %s",
			$month_ago
		) );

		$embed = $this->api->build_embed( array(
			'title'  => 'Booking Statistics',
			'color'  => '#5865F2',
			'fields' => array(
				array(
					'name'   => 'Today',
					'value'  => (string) ( $today_count ?: 0 ),
					'inline' => true,
				),
				array(
					'name'   => 'This Week',
					'value'  => (string) ( $week_count ?: 0 ),
					'inline' => true,
				),
				array(
					'name'   => 'Monthly Revenue',
					'value'  => '$' . number_format( (float) $revenue, 2 ),
					'inline' => true,
				),
			),
		) );

		return $this->respond_embed( $embed );
	}

	/**
	 * Command: /search <query>.
	 *
	 * @param array $options Command options.
	 * @return \WP_REST_Response
	 */
	private function command_search( $options ) {
		$query = $options['query'] ?? '';

		if ( empty( $query ) ) {
			return $this->respond_message( 'Please provide a search query.' );
		}

		return $this->search_bookings( $query );
	}

	/**
	 * Search bookings.
	 *
	 * @param string $query Search query.
	 * @return \WP_REST_Response
	 */
	private function search_bookings( $query ) {
		global $wpdb;

		// Search by customer name/email or booking ID.
		if ( is_numeric( $query ) ) {
			$booking = get_post( (int) $query );
			if ( $booking && 'bkx_booking' === $booking->post_type ) {
				return $this->show_booking_details( $booking->ID );
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$bookings = $wpdb->get_results( $wpdb->prepare(
			"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} m ON p.ID = m.post_id
			WHERE p.post_type = 'bkx_booking'
			AND (
				(m.meta_key = 'customer_name' AND m.meta_value LIKE %s)
				OR (m.meta_key = 'customer_email' AND m.meta_value LIKE %s)
			)
			LIMIT 10",
			'%' . $wpdb->esc_like( $query ) . '%',
			'%' . $wpdb->esc_like( $query ) . '%'
		) );

		if ( empty( $bookings ) ) {
			return $this->respond_message( "No bookings found for '{$query}'." );
		}

		$fields = array();
		foreach ( $bookings as $booking ) {
			$date     = get_post_meta( $booking->ID, 'booking_date', true );
			$customer = get_post_meta( $booking->ID, 'customer_name', true );
			$status   = get_post_status( $booking->ID );

			$fields[] = array(
				'name'   => "#{$booking->ID} - {$customer}",
				'value'  => "{$date} | " . ucfirst( str_replace( 'bkx-', '', $status ) ),
				'inline' => true,
			);
		}

		$embed = $this->api->build_embed( array(
			'title'  => "Search Results for '{$query}'",
			'color'  => '#5865F2',
			'fields' => $fields,
		) );

		return $this->respond_embed( $embed );
	}

	/**
	 * Show booking details.
	 *
	 * @param int $booking_id Booking ID.
	 * @return \WP_REST_Response
	 */
	private function show_booking_details( $booking_id ) {
		$booking = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return $this->respond_message( 'Booking not found.' );
		}

		$date     = get_post_meta( $booking_id, 'booking_date', true );
		$time     = get_post_meta( $booking_id, 'booking_time', true );
		$service  = get_the_title( get_post_meta( $booking_id, 'base_id', true ) );
		$staff    = get_the_title( get_post_meta( $booking_id, 'seat_id', true ) );
		$customer = get_post_meta( $booking_id, 'customer_name', true );
		$email    = get_post_meta( $booking_id, 'customer_email', true );
		$total    = get_post_meta( $booking_id, 'booking_total', true );
		$status   = ucfirst( str_replace( 'bkx-', '', get_post_status( $booking_id ) ) );

		$embed = $this->api->build_embed( array(
			'title'  => "Booking #{$booking_id}",
			'color'  => '#5865F2',
			'fields' => array(
				array( 'name' => 'Service', 'value' => $service ?: 'N/A', 'inline' => true ),
				array( 'name' => 'Staff', 'value' => $staff ?: 'N/A', 'inline' => true ),
				array( 'name' => 'Status', 'value' => $status, 'inline' => true ),
				array( 'name' => 'Date', 'value' => $date ?: 'N/A', 'inline' => true ),
				array( 'name' => 'Time', 'value' => $time ?: 'N/A', 'inline' => true ),
				array( 'name' => 'Total', 'value' => $total ? '$' . number_format( (float) $total, 2 ) : 'N/A', 'inline' => true ),
				array( 'name' => 'Customer', 'value' => $customer ?: 'N/A', 'inline' => true ),
				array( 'name' => 'Email', 'value' => $email ?: 'N/A', 'inline' => true ),
			),
		) );

		// Add action buttons for pending bookings.
		$components = array();
		if ( in_array( get_post_status( $booking_id ), array( 'bkx-pending' ), true ) ) {
			$components = $this->api->build_components( array(
				array(
					'label'     => 'Confirm',
					'custom_id' => "confirm_booking:{$booking_id}",
					'style'     => 3, // Success/Green.
				),
				array(
					'label'     => 'Cancel',
					'custom_id' => "cancel_booking:{$booking_id}",
					'style'     => 4, // Danger/Red.
				),
			) );
		}

		return $this->respond_embed( $embed, $components );
	}

	/**
	 * Confirm booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return \WP_REST_Response
	 */
	private function confirm_booking( $booking_id ) {
		$result = wp_update_post( array(
			'ID'          => $booking_id,
			'post_status' => 'bkx-ack',
		) );

		if ( is_wp_error( $result ) ) {
			return $this->respond_message( 'Failed to confirm booking.' );
		}

		return $this->respond_message( "Booking #{$booking_id} has been confirmed!", true );
	}

	/**
	 * Cancel booking.
	 *
	 * @param int $booking_id Booking ID.
	 * @return \WP_REST_Response
	 */
	private function cancel_booking( $booking_id ) {
		$result = wp_update_post( array(
			'ID'          => $booking_id,
			'post_status' => 'bkx-cancelled',
		) );

		if ( is_wp_error( $result ) ) {
			return $this->respond_message( 'Failed to cancel booking.' );
		}

		return $this->respond_message( "Booking #{$booking_id} has been cancelled.", true );
	}

	/**
	 * Parse command options.
	 *
	 * @param array $options Raw options.
	 * @return array
	 */
	private function parse_options( $options ) {
		$parsed = array();

		foreach ( $options as $option ) {
			$parsed[ $option['name'] ] = $option['value'];
		}

		return $parsed;
	}

	/**
	 * Get active bot connection.
	 *
	 * @return object|null
	 */
	private function get_active_bot() {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_discord_bots';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( "SELECT * FROM {$table} WHERE status = 'active' LIMIT 1" );
	}

	/**
	 * Decrypt value.
	 *
	 * @param string $value Encrypted value.
	 * @return string
	 */
	private function decrypt_value( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$key = wp_salt( 'auth' );

		$decoded = base64_decode( $value );
		if ( false === $decoded || strlen( $decoded ) < 16 ) {
			return $value; // Return as-is if not encrypted.
		}

		$iv         = substr( $decoded, 0, 16 );
		$ciphertext = substr( $decoded, 16 );

		$decrypted = openssl_decrypt( $ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );

		return $decrypted ?: $value;
	}

	/**
	 * Respond with message.
	 *
	 * @param string $message   Message content.
	 * @param bool   $ephemeral Whether message is ephemeral.
	 * @return \WP_REST_Response
	 */
	private function respond_message( $message, $ephemeral = false ) {
		$data = array(
			'content' => $message,
		);

		if ( $ephemeral ) {
			$data['flags'] = 64; // Ephemeral.
		}

		return new \WP_REST_Response( array(
			'type' => self::RESPONSE_CHANNEL_MESSAGE,
			'data' => $data,
		) );
	}

	/**
	 * Respond with embed.
	 *
	 * @param array $embed      Embed data.
	 * @param array $components Optional components.
	 * @return \WP_REST_Response
	 */
	private function respond_embed( $embed, $components = array() ) {
		$data = array(
			'embeds' => array( $embed ),
		);

		if ( ! empty( $components ) ) {
			$data['components'] = $components;
		}

		return new \WP_REST_Response( array(
			'type' => self::RESPONSE_CHANNEL_MESSAGE,
			'data' => $data,
		) );
	}
}
