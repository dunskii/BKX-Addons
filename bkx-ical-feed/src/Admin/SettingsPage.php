<?php
/**
 * Settings Page
 *
 * @package BookingX\ICalFeed
 * @since   1.0.0
 */

namespace BookingX\ICalFeed\Admin;

use BookingX\ICalFeed\ICalFeedAddon;

/**
 * Class SettingsPage
 *
 * Handles the admin settings page.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var ICalFeedAddon
	 */
	private ICalFeedAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param ICalFeedAddon $addon Addon instance.
	 */
	public function __construct( ICalFeedAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Add menu page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'iCal Feed', 'bkx-ical-feed' ),
			__( 'iCal Feed', 'bkx-ical-feed' ),
			'manage_options',
			'bkx-ical-feed',
			array( $this, 'render_page' ),
			'dashicons-calendar-alt',
			57
		);
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		// Handle form submissions.
		$this->handle_actions();

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'feeds';
		?>
		<div class="wrap bkx-ical-settings">
			<h1><?php esc_html_e( 'iCal Feed Export', 'bkx-ical-feed' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=bkx-ical-feed&tab=feeds"
				   class="nav-tab <?php echo 'feeds' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Feed URLs', 'bkx-ical-feed' ); ?>
				</a>
				<a href="?page=bkx-ical-feed&tab=settings"
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'bkx-ical-feed' ); ?>
				</a>
				<a href="?page=bkx-ical-feed&tab=license"
				   class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'License', 'bkx-ical-feed' ); ?>
				</a>
			</nav>

			<div class="bkx-tab-content">
				<?php
				switch ( $active_tab ) {
					case 'settings':
						$this->render_settings_tab();
						break;
					case 'license':
						$this->render_license_tab();
						break;
					default:
						$this->render_feeds_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle form actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function handle_actions(): void {
		if ( ! isset( $_POST['bkx_ical_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_ical_nonce'] ) ), 'bkx_ical_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle settings save.
		if ( isset( $_POST['bkx_ical_save_settings'] ) ) {
			$settings = array(
				'event_title'       => sanitize_text_field( wp_unslash( $_POST['event_title'] ?? '{service} - {customer}' ) ),
				'event_description' => sanitize_textarea_field( wp_unslash( $_POST['event_description'] ?? '' ) ),
				'event_location'    => sanitize_text_field( wp_unslash( $_POST['event_location'] ?? '' ) ),
				'days_ahead'        => absint( $_POST['days_ahead'] ?? 90 ),
				'days_behind'       => absint( $_POST['days_behind'] ?? 30 ),
				'reminder_minutes'  => absint( $_POST['reminder_minutes'] ?? 30 ),
			);

			foreach ( $settings as $key => $value ) {
				$this->addon->update_setting( $key, $value );
			}

			add_settings_error( 'bkx_ical', 'settings_updated', __( 'Settings saved.', 'bkx-ical-feed' ), 'success' );
		}

		// Handle token regeneration.
		if ( isset( $_POST['bkx_ical_regenerate_token'] ) ) {
			$this->addon->regenerate_token();
			add_settings_error( 'bkx_ical', 'token_regenerated', __( 'Feed token regenerated. Update your calendar subscriptions with the new URL.', 'bkx-ical-feed' ), 'success' );
		}

		// Handle license activation.
		if ( isset( $_POST['bkx_ical_activate_license'] ) ) {
			$license_key = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );
			$result      = $this->addon->activate_license( $license_key );

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'bkx_ical', 'license_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'bkx_ical', 'license_activated', __( 'License activated successfully.', 'bkx-ical-feed' ), 'success' );
			}
		}

		// Handle license deactivation.
		if ( isset( $_POST['bkx_ical_deactivate_license'] ) ) {
			$this->addon->deactivate_license();
			add_settings_error( 'bkx_ical', 'license_deactivated', __( 'License deactivated.', 'bkx-ical-feed' ), 'success' );
		}
	}

	/**
	 * Render feeds tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_feeds_tab(): void {
		settings_errors( 'bkx_ical' );

		$main_feed_url = $this->addon->get_main_feed_url();
		?>
		<h2><?php esc_html_e( 'Main Feed', 'bkx-ical-feed' ); ?></h2>
		<p><?php esc_html_e( 'Subscribe to this feed to see all bookings in your calendar app.', 'bkx-ical-feed' ); ?></p>

		<div class="bkx-feed-url-box">
			<input type="text" readonly value="<?php echo esc_url( $main_feed_url ); ?>" class="large-text bkx-feed-url" />
			<button type="button" class="button bkx-copy-url" data-url="<?php echo esc_url( $main_feed_url ); ?>">
				<?php esc_html_e( 'Copy', 'bkx-ical-feed' ); ?>
			</button>
		</div>

		<p class="description">
			<?php esc_html_e( 'Use this URL in Google Calendar, Apple Calendar, Outlook, or any calendar app that supports iCal subscriptions.', 'bkx-ical-feed' ); ?>
		</p>

		<form method="post">
			<?php wp_nonce_field( 'bkx_ical_settings', 'bkx_ical_nonce' ); ?>
			<p>
				<button type="submit" name="bkx_ical_regenerate_token" class="button"
						onclick="return confirm('<?php esc_attr_e( 'Are you sure? Existing calendar subscriptions will stop working.', 'bkx-ical-feed' ); ?>');">
					<?php esc_html_e( 'Regenerate Feed Token', 'bkx-ical-feed' ); ?>
				</button>
			</p>
		</form>

		<hr />

		<h2><?php esc_html_e( 'Per-Resource Feeds', 'bkx-ical-feed' ); ?></h2>
		<p><?php esc_html_e( 'Each resource/staff member has their own feed URL:', 'bkx-ical-feed' ); ?></p>

		<?php
		$seats = get_posts(
			array(
				'post_type'      => 'bkx_seat',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( empty( $seats ) ) {
			echo '<p class="description">' . esc_html__( 'No resources found.', 'bkx-ical-feed' ) . '</p>';
		} else {
			echo '<table class="widefat striped">';
			echo '<thead><tr>';
			echo '<th>' . esc_html__( 'Resource', 'bkx-ical-feed' ) . '</th>';
			echo '<th>' . esc_html__( 'Feed URL', 'bkx-ical-feed' ) . '</th>';
			echo '<th>' . esc_html__( 'Actions', 'bkx-ical-feed' ) . '</th>';
			echo '</tr></thead>';
			echo '<tbody>';

			foreach ( $seats as $seat ) {
				$seat_url = $this->addon->get_seat_feed_url( $seat->ID );
				echo '<tr>';
				echo '<td>' . esc_html( $seat->post_title ) . '</td>';
				echo '<td><input type="text" readonly value="' . esc_url( $seat_url ) . '" class="regular-text bkx-feed-url" /></td>';
				echo '<td><button type="button" class="button bkx-copy-url" data-url="' . esc_url( $seat_url ) . '">' . esc_html__( 'Copy', 'bkx-ical-feed' ) . '</button></td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}
		?>

		<hr />

		<h2><?php esc_html_e( 'Customer Feeds', 'bkx-ical-feed' ); ?></h2>
		<p><?php esc_html_e( 'Customers can subscribe to their own booking feed. Show them this shortcode on a page:', 'bkx-ical-feed' ); ?></p>
		<code>[bkx_ical_feed_link]</code>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('.bkx-copy-url').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var url = this.dataset.url;
					navigator.clipboard.writeText(url).then(function() {
						btn.textContent = '<?php echo esc_js( __( 'Copied!', 'bkx-ical-feed' ) ); ?>';
						setTimeout(function() {
							btn.textContent = '<?php echo esc_js( __( 'Copy', 'bkx-ical-feed' ) ); ?>';
						}, 2000);
					});
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Render settings tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_settings_tab(): void {
		settings_errors( 'bkx_ical' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'bkx_ical_settings', 'bkx_ical_nonce' ); ?>

			<h2><?php esc_html_e( 'Event Display', 'bkx-ical-feed' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="event_title"><?php esc_html_e( 'Event Title', 'bkx-ical-feed' ); ?></label>
					</th>
					<td>
						<input type="text" name="event_title" id="event_title" class="regular-text"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'event_title', '{service} - {customer}' ) ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Available placeholders: {booking_id}, {service}, {resource}, {customer}, {email}, {phone}, {date}, {time}, {site_name}', 'bkx-ical-feed' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="event_description"><?php esc_html_e( 'Event Description', 'bkx-ical-feed' ); ?></label>
					</th>
					<td>
						<textarea name="event_description" id="event_description" rows="4" class="large-text"><?php echo esc_textarea( $this->addon->get_setting( 'event_description', '' ) ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Additional text to include in the event description. Same placeholders available.', 'bkx-ical-feed' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="event_location"><?php esc_html_e( 'Event Location', 'bkx-ical-feed' ); ?></label>
					</th>
					<td>
						<input type="text" name="event_location" id="event_location" class="regular-text"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'event_location', get_bloginfo( 'name' ) ) ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Location shown in calendar events. Placeholders available.', 'bkx-ical-feed' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Feed Options', 'bkx-ical-feed' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="days_ahead"><?php esc_html_e( 'Days Ahead', 'bkx-ical-feed' ); ?></label>
					</th>
					<td>
						<input type="number" name="days_ahead" id="days_ahead" class="small-text" min="7" max="365"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'days_ahead', 90 ) ); ?>" />
						<p class="description">
							<?php esc_html_e( 'How many days of future bookings to include in the feed.', 'bkx-ical-feed' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="days_behind"><?php esc_html_e( 'Days Behind', 'bkx-ical-feed' ); ?></label>
					</th>
					<td>
						<input type="number" name="days_behind" id="days_behind" class="small-text" min="0" max="365"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'days_behind', 30 ) ); ?>" />
						<p class="description">
							<?php esc_html_e( 'How many days of past bookings to include in the feed.', 'bkx-ical-feed' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="reminder_minutes"><?php esc_html_e( 'Reminder', 'bkx-ical-feed' ); ?></label>
					</th>
					<td>
						<input type="number" name="reminder_minutes" id="reminder_minutes" class="small-text" min="0" max="1440"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'reminder_minutes', 30 ) ); ?>" />
						<?php esc_html_e( 'minutes before event', 'bkx-ical-feed' ); ?>
						<p class="description">
							<?php esc_html_e( 'Set to 0 to disable reminders.', 'bkx-ical-feed' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="bkx_ical_save_settings" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'bkx-ical-feed' ); ?>
				</button>
			</p>
		</form>
		<?php
	}

	/**
	 * Render license tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_license_tab(): void {
		settings_errors( 'bkx_ical' );

		$license_key    = $this->addon->get_license_key();
		$license_status = $this->addon->get_license_status();
		$is_active      = 'valid' === $license_status;
		?>
		<form method="post">
			<?php wp_nonce_field( 'bkx_ical_settings', 'bkx_ical_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="license_key"><?php esc_html_e( 'License Key', 'bkx-ical-feed' ); ?></label>
					</th>
					<td>
						<input type="text" name="license_key" id="license_key" class="regular-text"
							   value="<?php echo esc_attr( $license_key ); ?>"
							   <?php echo $is_active ? 'readonly' : ''; ?> />

						<?php if ( $is_active ) : ?>
							<span class="bkx-license-status bkx-license-active">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Active', 'bkx-ical-feed' ); ?>
							</span>
						<?php elseif ( ! empty( $license_key ) ) : ?>
							<span class="bkx-license-status bkx-license-inactive">
								<?php esc_html_e( 'Inactive', 'bkx-ical-feed' ); ?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<p class="submit">
				<?php if ( $is_active ) : ?>
					<button type="submit" name="bkx_ical_deactivate_license" class="button">
						<?php esc_html_e( 'Deactivate License', 'bkx-ical-feed' ); ?>
					</button>
				<?php else : ?>
					<button type="submit" name="bkx_ical_activate_license" class="button button-primary">
						<?php esc_html_e( 'Activate License', 'bkx-ical-feed' ); ?>
					</button>
				<?php endif; ?>
			</p>
		</form>

		<hr />

		<h3><?php esc_html_e( 'License Benefits', 'bkx-ical-feed' ); ?></h3>
		<ul class="ul-disc">
			<li><?php esc_html_e( 'Automatic plugin updates', 'bkx-ical-feed' ); ?></li>
			<li><?php esc_html_e( 'Priority email support', 'bkx-ical-feed' ); ?></li>
			<li><?php esc_html_e( 'Access to new features', 'bkx-ical-feed' ); ?></li>
		</ul>
		<?php
	}
}
