<?php
/**
 * Slash Command Handler.
 *
 * @package BookingX\Slack\Services
 */

namespace BookingX\Slack\Services;

defined( 'ABSPATH' ) || exit;

/**
 * SlashCommandHandler class.
 *
 * Handles Slack slash commands.
 */
class SlashCommandHandler {

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
	 * Handle slash command request.
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

		// Parse form-encoded body.
		parse_str( $body, $params );

		$command      = $params['command'] ?? '';
		$text         = trim( $params['text'] ?? '' );
		$user_id      = $params['user_id'] ?? '';
		$user_name    = $params['user_name'] ?? '';
		$channel_id   = $params['channel_id'] ?? '';
		$response_url = $params['response_url'] ?? '';
		$trigger_id   = $params['trigger_id'] ?? '';
		$team_id      = $params['team_id'] ?? '';

		// Parse subcommand and arguments.
		$parts      = explode( ' ', $text, 2 );
		$subcommand = strtolower( $parts[0] ?? 'help' );
		$args       = $parts[1] ?? '';

		// Route to appropriate handler.
		switch ( $subcommand ) {
			case 'today':
				$response = $this->handle_today( $team_id );
				break;

			case 'upcoming':
				$response = $this->handle_upcoming( $team_id, $args );
				break;

			case 'search':
				$response = $this->handle_search( $team_id, $args );
				break;

			case 'stats':
				$response = $this->handle_stats( $team_id );
				break;

			case 'book':
				$response = $this->handle_book( $team_id, $trigger_id, $args );
				break;

			case 'cancel':
				$response = $this->handle_cancel( $team_id, $args );
				break;

			case 'confirm':
				$response = $this->handle_confirm( $team_id, $args );
				break;

			case 'help':
			default:
				$response = $this->handle_help();
				break;
		}

		return new \WP_REST_Response( $response, 200 );
	}

	/**
	 * Handle today's bookings command.
	 *
	 * @param string $team_id Team ID.
	 * @return array
	 */
	private function handle_today( $team_id ) {
		$today = current_time( 'Y-m-d' );

		$bookings = get_posts( array(
			'post_type'      => 'bkx_booking',
			'post_status'    => array( 'bkx-pending', 'bkx-ack' ),
			'posts_per_page' => 20,
			'meta_query'     => array(
				array(
					'key'   => 'booking_date',
					'value' => $today,
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => 'booking_time',
			'order'          => 'ASC',
		) );

		if ( empty( $bookings ) ) {
			return array(
				'response_type' => 'ephemeral',
				'text'          => ':calendar: No bookings scheduled for today.',
			);
		}

		$blocks = array(
			array(
				'type' => 'header',
				'text' => array(
					'type'  => 'plain_text',
					'text'  => sprintf( ":calendar: Today's Bookings (%s)", wp_date( 'F j, Y' ) ),
					'emoji' => true,
				),
			),
			array(
				'type' => 'divider',
			),
		);

		foreach ( $bookings as $booking ) {
			$blocks[] = $this->format_booking_block( $booking );
		}

		return array(
			'response_type' => 'ephemeral',
			'blocks'        => $blocks,
		);
	}

	/**
	 * Handle upcoming bookings command.
	 *
	 * @param string $team_id Team ID.
	 * @param string $args    Command arguments (number of days).
	 * @return array
	 */
	private function handle_upcoming( $team_id, $args ) {
		$days = absint( $args ) ?: 7;
		$days = min( $days, 30 ); // Max 30 days.

		$start_date = current_time( 'Y-m-d' );
		$end_date   = gmdate( 'Y-m-d', strtotime( "+{$days} days" ) );

		$bookings = get_posts( array(
			'post_type'      => 'bkx_booking',
			'post_status'    => array( 'bkx-pending', 'bkx-ack' ),
			'posts_per_page' => 50,
			'meta_query'     => array(
				array(
					'key'     => 'booking_date',
					'value'   => array( $start_date, $end_date ),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => 'booking_date',
			'order'          => 'ASC',
		) );

		if ( empty( $bookings ) ) {
			return array(
				'response_type' => 'ephemeral',
				'text'          => sprintf( ':calendar: No bookings in the next %d days.', $days ),
			);
		}

		$blocks = array(
			array(
				'type' => 'header',
				'text' => array(
					'type'  => 'plain_text',
					'text'  => sprintf( ':calendar: Upcoming Bookings (Next %d Days)', $days ),
					'emoji' => true,
				),
			),
			array(
				'type' => 'divider',
			),
		);

		// Group by date.
		$by_date = array();
		foreach ( $bookings as $booking ) {
			$date = get_post_meta( $booking->ID, 'booking_date', true );
			$by_date[ $date ][] = $booking;
		}

		foreach ( $by_date as $date => $date_bookings ) {
			$blocks[] = array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => '*' . wp_date( 'l, F j', strtotime( $date ) ) . '*',
				),
			);

			foreach ( $date_bookings as $booking ) {
				$blocks[] = $this->format_booking_block( $booking, false );
			}

			$blocks[] = array( 'type' => 'divider' );
		}

		return array(
			'response_type' => 'ephemeral',
			'blocks'        => $blocks,
		);
	}

	/**
	 * Handle search command.
	 *
	 * @param string $team_id Team ID.
	 * @param string $query   Search query.
	 * @return array
	 */
	private function handle_search( $team_id, $query ) {
		if ( empty( $query ) ) {
			return array(
				'response_type' => 'ephemeral',
				'text'          => ':mag: Please provide a search term. Usage: `/bookingx search John`',
			);
		}

		global $wpdb;

		// Search by customer name or email.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$booking_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			WHERE meta_key IN ('customer_name', 'customer_email')
			AND meta_value LIKE %s
			LIMIT 20",
			'%' . $wpdb->esc_like( $query ) . '%'
		) );

		if ( empty( $booking_ids ) ) {
			return array(
				'response_type' => 'ephemeral',
				'text'          => sprintf( ':mag: No bookings found for "%s".', $query ),
			);
		}

		$bookings = get_posts( array(
			'post_type'      => 'bkx_booking',
			'post__in'       => array_unique( $booking_ids ),
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		$blocks = array(
			array(
				'type' => 'header',
				'text' => array(
					'type'  => 'plain_text',
					'text'  => sprintf( ':mag: Search Results for "%s"', $query ),
					'emoji' => true,
				),
			),
			array(
				'type' => 'divider',
			),
		);

		foreach ( $bookings as $booking ) {
			$blocks[] = $this->format_booking_block( $booking );
		}

		return array(
			'response_type' => 'ephemeral',
			'blocks'        => $blocks,
		);
	}

	/**
	 * Handle stats command.
	 *
	 * @param string $team_id Team ID.
	 * @return array
	 */
	private function handle_stats( $team_id ) {
		$today = current_time( 'Y-m-d' );
		$week_start = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
		$month_start = gmdate( 'Y-m-01' );

		// Today's bookings.
		$today_count = $this->count_bookings( $today, $today );

		// This week's bookings.
		$week_count = $this->count_bookings( $week_start, $today );

		// This month's bookings.
		$month_count = $this->count_bookings( $month_start, $today );

		// Pending bookings.
		$pending_count = count( get_posts( array(
			'post_type'      => 'bkx_booking',
			'post_status'    => 'bkx-pending',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) ) );

		// Revenue this month.
		$revenue = $this->calculate_revenue( $month_start, $today );

		$blocks = array(
			array(
				'type' => 'header',
				'text' => array(
					'type'  => 'plain_text',
					'text'  => ':bar_chart: Booking Statistics',
					'emoji' => true,
				),
			),
			array(
				'type' => 'divider',
			),
			array(
				'type'   => 'section',
				'fields' => array(
					array(
						'type' => 'mrkdwn',
						'text' => sprintf( "*Today:*\n%d bookings", $today_count ),
					),
					array(
						'type' => 'mrkdwn',
						'text' => sprintf( "*This Week:*\n%d bookings", $week_count ),
					),
					array(
						'type' => 'mrkdwn',
						'text' => sprintf( "*This Month:*\n%d bookings", $month_count ),
					),
					array(
						'type' => 'mrkdwn',
						'text' => sprintf( "*Pending:*\n%d bookings", $pending_count ),
					),
				),
			),
			array(
				'type' => 'divider',
			),
			array(
				'type' => 'section',
				'text' => array(
					'type' => 'mrkdwn',
					'text' => sprintf( "*Monthly Revenue:* %s", wc_price( $revenue ) ),
				),
			),
		);

		return array(
			'response_type' => 'ephemeral',
			'blocks'        => $blocks,
		);
	}

	/**
	 * Handle book command (opens modal).
	 *
	 * @param string $team_id    Team ID.
	 * @param string $trigger_id Trigger ID.
	 * @param string $args       Command arguments.
	 * @return array
	 */
	private function handle_book( $team_id, $trigger_id, $args ) {
		// For now, return link to booking page.
		// Full modal implementation would require more complex view handling.
		$booking_url = home_url( '/book/' );

		return array(
			'response_type' => 'ephemeral',
			'blocks'        => array(
				array(
					'type' => 'section',
					'text' => array(
						'type' => 'mrkdwn',
						'text' => ':calendar: To create a new booking, please use our booking form:',
					),
					'accessory' => array(
						'type' => 'button',
						'text' => array(
							'type'  => 'plain_text',
							'text'  => 'Book Now',
							'emoji' => true,
						),
						'url'   => $booking_url,
						'style' => 'primary',
					),
				),
			),
		);
	}

	/**
	 * Handle cancel command.
	 *
	 * @param string $team_id    Team ID.
	 * @param string $booking_id Booking ID.
	 * @return array
	 */
	private function handle_cancel( $team_id, $booking_id ) {
		$booking_id = absint( $booking_id );

		if ( ! $booking_id ) {
			return array(
				'response_type' => 'ephemeral',
				'text'          => ':warning: Please provide a booking ID. Usage: `/bookingx cancel 123`',
			);
		}

		$booking = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return array(
				'response_type' => 'ephemeral',
				'text'          => sprintf( ':x: Booking #%d not found.', $booking_id ),
			);
		}

		// Update status.
		wp_update_post( array(
			'ID'          => $booking_id,
			'post_status' => 'bkx-cancelled',
		) );

		$customer_name = get_post_meta( $booking_id, 'customer_name', true );

		return array(
			'response_type' => 'in_channel',
			'text'          => sprintf(
				':x: Booking #%d for %s has been cancelled.',
				$booking_id,
				$customer_name ?: __( 'Guest', 'bkx-slack' )
			),
		);
	}

	/**
	 * Handle confirm command.
	 *
	 * @param string $team_id    Team ID.
	 * @param string $booking_id Booking ID.
	 * @return array
	 */
	private function handle_confirm( $team_id, $booking_id ) {
		$booking_id = absint( $booking_id );

		if ( ! $booking_id ) {
			return array(
				'response_type' => 'ephemeral',
				'text'          => ':warning: Please provide a booking ID. Usage: `/bookingx confirm 123`',
			);
		}

		$booking = get_post( $booking_id );

		if ( ! $booking || 'bkx_booking' !== $booking->post_type ) {
			return array(
				'response_type' => 'ephemeral',
				'text'          => sprintf( ':x: Booking #%d not found.', $booking_id ),
			);
		}

		// Update status.
		wp_update_post( array(
			'ID'          => $booking_id,
			'post_status' => 'bkx-ack',
		) );

		$customer_name = get_post_meta( $booking_id, 'customer_name', true );

		return array(
			'response_type' => 'in_channel',
			'text'          => sprintf(
				':white_check_mark: Booking #%d for %s has been confirmed.',
				$booking_id,
				$customer_name ?: __( 'Guest', 'bkx-slack' )
			),
		);
	}

	/**
	 * Handle help command.
	 *
	 * @return array
	 */
	private function handle_help() {
		return array(
			'response_type' => 'ephemeral',
			'blocks'        => array(
				array(
					'type' => 'header',
					'text' => array(
						'type'  => 'plain_text',
						'text'  => ':book: BookingX Commands',
						'emoji' => true,
					),
				),
				array(
					'type' => 'divider',
				),
				array(
					'type' => 'section',
					'text' => array(
						'type' => 'mrkdwn',
						'text' => "*Available Commands:*\n\n"
							. "`/bookingx today` - View today's bookings\n"
							. "`/bookingx upcoming [days]` - View upcoming bookings (default: 7 days)\n"
							. "`/bookingx search [query]` - Search bookings by customer name or email\n"
							. "`/bookingx stats` - View booking statistics\n"
							. "`/bookingx book` - Create a new booking\n"
							. "`/bookingx confirm [id]` - Confirm a pending booking\n"
							. "`/bookingx cancel [id]` - Cancel a booking\n"
							. "`/bookingx help` - Show this help message",
					),
				),
			),
		);
	}

	/**
	 * Format a booking as a Slack block.
	 *
	 * @param \WP_Post $booking   Booking post.
	 * @param bool     $show_date Whether to show date.
	 * @return array
	 */
	private function format_booking_block( $booking, $show_date = true ) {
		$booking_date   = get_post_meta( $booking->ID, 'booking_date', true );
		$booking_time   = get_post_meta( $booking->ID, 'booking_time', true );
		$customer_name  = get_post_meta( $booking->ID, 'customer_name', true );
		$service_id     = get_post_meta( $booking->ID, 'base_id', true );

		$service      = get_post( $service_id );
		$service_name = $service ? $service->post_title : __( 'Unknown', 'bkx-slack' );

		$status_emoji = array(
			'bkx-pending'   => ':hourglass:',
			'bkx-ack'       => ':white_check_mark:',
			'bkx-completed' => ':star:',
			'bkx-cancelled' => ':x:',
		);

		$emoji = $status_emoji[ $booking->post_status ] ?? ':calendar:';
		$time  = $booking_time ? wp_date( 'g:i A', strtotime( $booking_time ) ) : '';
		$date  = $booking_date && $show_date ? wp_date( 'M j', strtotime( $booking_date ) ) . ' ' : '';

		return array(
			'type' => 'section',
			'text' => array(
				'type' => 'mrkdwn',
				'text' => sprintf(
					'%s *#%d* - %s%s - %s (%s)',
					$emoji,
					$booking->ID,
					$date,
					$time,
					$customer_name ?: __( 'Guest', 'bkx-slack' ),
					$service_name
				),
			),
			'accessory' => array(
				'type'      => 'button',
				'text'      => array(
					'type'  => 'plain_text',
					'text'  => 'View',
					'emoji' => true,
				),
				'url'       => admin_url( 'post.php?post=' . $booking->ID . '&action=edit' ),
				'action_id' => 'view_booking_' . $booking->ID,
			),
		);
	}

	/**
	 * Count bookings in date range.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return int
	 */
	private function count_bookings( $start_date, $end_date ) {
		return count( get_posts( array(
			'post_type'      => 'bkx_booking',
			'post_status'    => array( 'bkx-pending', 'bkx-ack', 'bkx-completed' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => 'booking_date',
					'value'   => array( $start_date, $end_date ),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
		) ) );
	}

	/**
	 * Calculate revenue in date range.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return float
	 */
	private function calculate_revenue( $start_date, $end_date ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(pm2.meta_value)
			FROM {$wpdb->posts} p
			JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'booking_date'
			JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'booking_total'
			WHERE p.post_type = 'bkx_booking'
			AND p.post_status IN ('bkx-ack', 'bkx-completed')
			AND pm1.meta_value BETWEEN %s AND %s",
			$start_date,
			$end_date
		) );

		return floatval( $result );
	}
}
