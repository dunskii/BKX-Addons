<?php
/**
 * iCal Feed Export Addon
 *
 * @package BookingX\ICalFeed
 * @since   1.0.0
 */

namespace BookingX\ICalFeed;

use BookingX\AddonSDK\Abstracts\AbstractAddon;
use BookingX\AddonSDK\Traits\HasSettings;
use BookingX\AddonSDK\Traits\HasLicense;
use BookingX\ICalFeed\Services\ICalService;
use BookingX\ICalFeed\Admin\SettingsPage;

/**
 * Class ICalFeedAddon
 *
 * Main addon class for iCal Feed Export.
 *
 * @since 1.0.0
 */
class ICalFeedAddon extends AbstractAddon {

	use HasSettings;
	use HasLicense;

	/**
	 * Addon slug.
	 *
	 * @var string
	 */
	protected string $slug = 'bkx-ical-feed';

	/**
	 * Addon version.
	 *
	 * @var string
	 */
	protected string $version = '1.0.0';

	/**
	 * Addon name.
	 *
	 * @var string
	 */
	protected string $name = 'iCal Feed Export';

	/**
	 * License product ID.
	 *
	 * @var int
	 */
	protected int $product_id = 110;

	/**
	 * iCal service instance.
	 *
	 * @var ICalService
	 */
	private ICalService $ical_service;

	/**
	 * Settings page instance.
	 *
	 * @var SettingsPage
	 */
	private SettingsPage $settings_page;

	/**
	 * Boot the addon.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function boot(): void {
		$this->ical_service  = new ICalService( $this );
		$this->settings_page = new SettingsPage( $this );

		// Register rewrite rules.
		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_feed_request' ) );

		// Admin.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this->settings_page, 'add_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		// Per-seat feed generation.
		add_action( 'save_post_bkx_seat', array( $this, 'generate_seat_token' ), 10, 2 );
	}

	/**
	 * Register rewrite rules for feed URLs.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_rewrite_rules(): void {
		// Main feed: /bookingx-feed/{token}/
		add_rewrite_rule(
			'^bookingx-feed/([a-zA-Z0-9]+)/?$',
			'index.php?bkx_ical_feed=1&bkx_feed_token=$matches[1]',
			'top'
		);

		// Seat-specific feed: /bookingx-feed/{token}/seat/{seat_id}/
		add_rewrite_rule(
			'^bookingx-feed/([a-zA-Z0-9]+)/seat/([0-9]+)/?$',
			'index.php?bkx_ical_feed=1&bkx_feed_token=$matches[1]&bkx_seat_id=$matches[2]',
			'top'
		);

		// Customer feed: /bookingx-feed/customer/{token}/
		add_rewrite_rule(
			'^bookingx-feed/customer/([a-zA-Z0-9]+)/?$',
			'index.php?bkx_ical_feed=1&bkx_customer_token=$matches[1]',
			'top'
		);
	}

	/**
	 * Add query vars.
	 *
	 * @since 1.0.0
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( array $vars ): array {
		$vars[] = 'bkx_ical_feed';
		$vars[] = 'bkx_feed_token';
		$vars[] = 'bkx_seat_id';
		$vars[] = 'bkx_customer_token';
		return $vars;
	}

	/**
	 * Handle feed request.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_feed_request(): void {
		if ( ! get_query_var( 'bkx_ical_feed' ) ) {
			return;
		}

		$feed_token     = sanitize_text_field( get_query_var( 'bkx_feed_token', '' ) );
		$customer_token = sanitize_text_field( get_query_var( 'bkx_customer_token', '' ) );
		$seat_id        = absint( get_query_var( 'bkx_seat_id', 0 ) );

		// Customer feed.
		if ( ! empty( $customer_token ) ) {
			$this->serve_customer_feed( $customer_token );
			return;
		}

		// Validate main token.
		$stored_token = get_option( 'bkx_ical_feed_token', '' );
		if ( empty( $feed_token ) || ! hash_equals( $stored_token, $feed_token ) ) {
			status_header( 403 );
			exit( 'Invalid feed token' );
		}

		// Seat-specific feed.
		if ( $seat_id > 0 ) {
			$this->serve_seat_feed( $seat_id );
			return;
		}

		// Main feed (all bookings).
		$this->serve_main_feed();
	}

	/**
	 * Serve main feed with all bookings.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function serve_main_feed(): void {
		$days_ahead  = $this->get_setting( 'days_ahead', 90 );
		$days_behind = $this->get_setting( 'days_behind', 30 );

		$args = array(
			'post_type'      => 'bkx_booking',
			'post_status'    => array( 'bkx-pending', 'bkx-ack' ),
			'posts_per_page' => 500,
			'meta_query'     => array(
				array(
					'key'     => 'booking_date',
					'value'   => array(
						gmdate( 'Y-m-d', strtotime( "-{$days_behind} days" ) ),
						gmdate( 'Y-m-d', strtotime( "+{$days_ahead} days" ) ),
					),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
		);

		$bookings = get_posts( $args );

		$this->output_ical( $bookings, __( 'All Bookings', 'bkx-ical-feed' ) );
	}

	/**
	 * Serve seat-specific feed.
	 *
	 * @since 1.0.0
	 * @param int $seat_id Seat ID.
	 * @return void
	 */
	private function serve_seat_feed( int $seat_id ): void {
		$seat = get_post( $seat_id );
		if ( ! $seat || 'bkx_seat' !== $seat->post_type ) {
			status_header( 404 );
			exit( 'Seat not found' );
		}

		$days_ahead  = $this->get_setting( 'days_ahead', 90 );
		$days_behind = $this->get_setting( 'days_behind', 30 );

		$args = array(
			'post_type'      => 'bkx_booking',
			'post_status'    => array( 'bkx-pending', 'bkx-ack' ),
			'posts_per_page' => 500,
			'meta_query'     => array(
				array(
					'key'   => 'seat_id',
					'value' => $seat_id,
				),
				array(
					'key'     => 'booking_date',
					'value'   => array(
						gmdate( 'Y-m-d', strtotime( "-{$days_behind} days" ) ),
						gmdate( 'Y-m-d', strtotime( "+{$days_ahead} days" ) ),
					),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
		);

		$bookings = get_posts( $args );

		/* translators: %s: seat/resource name */
		$this->output_ical( $bookings, sprintf( __( '%s Bookings', 'bkx-ical-feed' ), $seat->post_title ) );
	}

	/**
	 * Serve customer-specific feed.
	 *
	 * @since 1.0.0
	 * @param string $token Customer token.
	 * @return void
	 */
	private function serve_customer_feed( string $token ): void {
		global $wpdb;

		// Find user by token.
		$user_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_bkx_ical_token' AND meta_value = %s",
				$token
			)
		);

		if ( ! $user_id ) {
			status_header( 403 );
			exit( 'Invalid customer token' );
		}

		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) {
			status_header( 404 );
			exit( 'User not found' );
		}

		$days_ahead  = $this->get_setting( 'days_ahead', 90 );
		$days_behind = $this->get_setting( 'days_behind', 30 );

		$args = array(
			'post_type'      => 'bkx_booking',
			'post_status'    => array( 'bkx-pending', 'bkx-ack' ),
			'posts_per_page' => 100,
			'meta_query'     => array(
				array(
					'key'   => 'customer_email',
					'value' => $user->user_email,
				),
				array(
					'key'     => 'booking_date',
					'value'   => array(
						gmdate( 'Y-m-d', strtotime( "-{$days_behind} days" ) ),
						gmdate( 'Y-m-d', strtotime( "+{$days_ahead} days" ) ),
					),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
		);

		$bookings = get_posts( $args );

		/* translators: %s: customer name */
		$this->output_ical( $bookings, sprintf( __( '%s\'s Bookings', 'bkx-ical-feed' ), $user->display_name ) );
	}

	/**
	 * Output iCal feed.
	 *
	 * @since 1.0.0
	 * @param array  $bookings Booking posts.
	 * @param string $cal_name Calendar name.
	 * @return void
	 */
	private function output_ical( array $bookings, string $cal_name ): void {
		$ical = $this->ical_service->generate_ical( $bookings, $cal_name );

		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: inline; filename="bookings.ics"' );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: Sat, 01 Jan 2000 00:00:00 GMT' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCal format requires raw output
		echo $ical;
		exit;
	}

	/**
	 * Generate token for seat on save.
	 *
	 * @since 1.0.0
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function generate_seat_token( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$existing_token = get_post_meta( $post_id, '_bkx_ical_token', true );
		if ( empty( $existing_token ) ) {
			update_post_meta( $post_id, '_bkx_ical_token', wp_generate_password( 24, false ) );
		}
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_bkx-ical-feed' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-ical-feed-admin',
			BKX_ICAL_FEED_URL . 'assets/css/admin.css',
			array(),
			BKX_ICAL_FEED_VERSION
		);
	}

	/**
	 * Get the iCal service.
	 *
	 * @since 1.0.0
	 * @return ICalService
	 */
	public function get_ical_service(): ICalService {
		return $this->ical_service;
	}

	/**
	 * Get main feed URL.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_main_feed_url(): string {
		$token = get_option( 'bkx_ical_feed_token', '' );
		return home_url( '/bookingx-feed/' . $token . '/' );
	}

	/**
	 * Get seat feed URL.
	 *
	 * @since 1.0.0
	 * @param int $seat_id Seat ID.
	 * @return string
	 */
	public function get_seat_feed_url( int $seat_id ): string {
		$token = get_option( 'bkx_ical_feed_token', '' );
		return home_url( '/bookingx-feed/' . $token . '/seat/' . $seat_id . '/' );
	}

	/**
	 * Get customer feed URL.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function get_customer_feed_url( int $user_id ): string {
		$token = get_user_meta( $user_id, '_bkx_ical_token', true );

		if ( empty( $token ) ) {
			$token = wp_generate_password( 24, false );
			update_user_meta( $user_id, '_bkx_ical_token', $token );
		}

		return home_url( '/bookingx-feed/customer/' . $token . '/' );
	}

	/**
	 * Regenerate main feed token.
	 *
	 * @since 1.0.0
	 * @return string New token.
	 */
	public function regenerate_token(): string {
		$new_token = wp_generate_password( 32, false );
		update_option( 'bkx_ical_feed_token', $new_token );
		return $new_token;
	}
}
