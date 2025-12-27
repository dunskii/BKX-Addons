<?php
/**
 * Settings Page
 *
 * @package BookingX\WaitingLists
 * @since   1.0.0
 */

namespace BookingX\WaitingLists\Admin;

use BookingX\WaitingLists\WaitingListsAddon;

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
	 * @var WaitingListsAddon
	 */
	private WaitingListsAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param WaitingListsAddon $addon Addon instance.
	 */
	public function __construct( WaitingListsAddon $addon ) {
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
			__( 'Waiting Lists', 'bkx-waiting-lists' ),
			__( 'Waiting Lists', 'bkx-waiting-lists' ),
			'manage_options',
			'bkx-waiting-lists',
			array( $this, 'render_page' ),
			'dashicons-list-view',
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
		$this->handle_actions();

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'entries';
		?>
		<div class="wrap bkx-waiting-lists-settings">
			<h1><?php esc_html_e( 'Waiting Lists', 'bkx-waiting-lists' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=bkx-waiting-lists&tab=entries"
				   class="nav-tab <?php echo 'entries' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Entries', 'bkx-waiting-lists' ); ?>
				</a>
				<a href="?page=bkx-waiting-lists&tab=settings"
				   class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'bkx-waiting-lists' ); ?>
				</a>
				<a href="?page=bkx-waiting-lists&tab=license"
				   class="nav-tab <?php echo 'license' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'License', 'bkx-waiting-lists' ); ?>
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
						$this->render_entries_tab();
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
		if ( ! isset( $_POST['bkx_waitlist_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_waitlist_nonce'] ) ), 'bkx_waiting_lists' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Save settings.
		if ( isset( $_POST['bkx_save_settings'] ) ) {
			$settings = array(
				'enabled'            => ! empty( $_POST['enabled'] ),
				'max_waitlist_size'  => absint( $_POST['max_waitlist_size'] ?? 10 ),
				'offer_expiry_hours' => absint( $_POST['offer_expiry_hours'] ?? 24 ),
				'retention_days'     => absint( $_POST['retention_days'] ?? 30 ),
				'show_position'      => ! empty( $_POST['show_position'] ),
				'notify_admin'       => ! empty( $_POST['notify_admin'] ),
			);

			foreach ( $settings as $key => $value ) {
				$this->addon->update_setting( $key, $value );
			}

			add_settings_error( 'bkx_waitlist', 'settings_updated', __( 'Settings saved.', 'bkx-waiting-lists' ), 'success' );
		}
	}

	/**
	 * Render entries tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_entries_tab(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_waiting_list';

		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
		$date_filter   = isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';

		$where = array( '1=1' );
		$args  = array();

		if ( ! empty( $status_filter ) ) {
			$where[] = 'status = %s';
			$args[]  = $status_filter;
		}

		if ( ! empty( $date_filter ) ) {
			$where[] = 'booking_date = %s';
			$args[]  = $date_filter;
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$entries = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY booking_date DESC, booking_time DESC LIMIT 100",
				...$args
			),
			ARRAY_A
		);

		$statuses = array(
			'waiting'   => __( 'Waiting', 'bkx-waiting-lists' ),
			'offered'   => __( 'Offered', 'bkx-waiting-lists' ),
			'accepted'  => __( 'Accepted', 'bkx-waiting-lists' ),
			'declined'  => __( 'Declined', 'bkx-waiting-lists' ),
			'expired'   => __( 'Expired', 'bkx-waiting-lists' ),
			'cancelled' => __( 'Cancelled', 'bkx-waiting-lists' ),
		);
		?>
		<div class="bkx-entries-header">
			<form method="get" class="bkx-filter-form">
				<input type="hidden" name="page" value="bkx-waiting-lists" />
				<input type="hidden" name="tab" value="entries" />
				<select name="status">
					<option value=""><?php esc_html_e( 'All Statuses', 'bkx-waiting-lists' ); ?></option>
					<?php foreach ( $statuses as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status_filter, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<input type="date" name="date" value="<?php echo esc_attr( $date_filter ); ?>" />
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-waiting-lists' ); ?></button>
			</form>
		</div>

		<?php if ( empty( $entries ) ) : ?>
			<p><?php esc_html_e( 'No waiting list entries found.', 'bkx-waiting-lists' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date/Time', 'bkx-waiting-lists' ); ?></th>
						<th><?php esc_html_e( 'Customer', 'bkx-waiting-lists' ); ?></th>
						<th><?php esc_html_e( 'Service', 'bkx-waiting-lists' ); ?></th>
						<th><?php esc_html_e( 'Resource', 'bkx-waiting-lists' ); ?></th>
						<th><?php esc_html_e( 'Position', 'bkx-waiting-lists' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-waiting-lists' ); ?></th>
						<th><?php esc_html_e( 'Created', 'bkx-waiting-lists' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $entries as $entry ) : ?>
						<?php
						$service   = get_post( $entry['service_id'] );
						$seat      = get_post( $entry['seat_id'] );
						$status_class = 'bkx-status-' . $entry['status'];
						?>
						<tr>
							<td>
								<?php echo esc_html( $entry['booking_date'] ); ?><br />
								<small><?php echo esc_html( substr( $entry['booking_time'], 0, 5 ) ); ?></small>
							</td>
							<td>
								<?php echo esc_html( $entry['customer_name'] ); ?><br />
								<small><?php echo esc_html( $entry['customer_email'] ); ?></small>
							</td>
							<td><?php echo $service ? esc_html( $service->post_title ) : '—'; ?></td>
							<td><?php echo $seat ? esc_html( $seat->post_title ) : '—'; ?></td>
							<td><?php echo esc_html( $entry['position'] ); ?></td>
							<td>
								<span class="bkx-status-badge <?php echo esc_attr( $status_class ); ?>">
									<?php echo esc_html( $statuses[ $entry['status'] ] ?? $entry['status'] ); ?>
								</span>
							</td>
							<td>
								<small><?php echo esc_html( $entry['created_at'] ); ?></small>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render settings tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_settings_tab(): void {
		settings_errors( 'bkx_waitlist' );
		?>
		<form method="post">
			<?php wp_nonce_field( 'bkx_waiting_lists', 'bkx_waitlist_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th><label for="enabled"><?php esc_html_e( 'Enable Waiting Lists', 'bkx-waiting-lists' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" name="enabled" id="enabled" value="1"
								<?php checked( $this->addon->get_setting( 'enabled', true ) ); ?> />
							<?php esc_html_e( 'Allow customers to join waiting lists for fully booked slots', 'bkx-waiting-lists' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="max_waitlist_size"><?php esc_html_e( 'Maximum List Size', 'bkx-waiting-lists' ); ?></label></th>
					<td>
						<input type="number" name="max_waitlist_size" id="max_waitlist_size" class="small-text" min="1" max="100"
							value="<?php echo esc_attr( $this->addon->get_setting( 'max_waitlist_size', 10 ) ); ?>" />
						<p class="description"><?php esc_html_e( 'Maximum number of people that can join the waiting list for a single slot.', 'bkx-waiting-lists' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="offer_expiry_hours"><?php esc_html_e( 'Offer Expiry', 'bkx-waiting-lists' ); ?></label></th>
					<td>
						<input type="number" name="offer_expiry_hours" id="offer_expiry_hours" class="small-text" min="1" max="168"
							value="<?php echo esc_attr( $this->addon->get_setting( 'offer_expiry_hours', 24 ) ); ?>" />
						<?php esc_html_e( 'hours', 'bkx-waiting-lists' ); ?>
						<p class="description"><?php esc_html_e( 'How long a customer has to accept an offered slot before it goes to the next person.', 'bkx-waiting-lists' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="retention_days"><?php esc_html_e( 'Data Retention', 'bkx-waiting-lists' ); ?></label></th>
					<td>
						<input type="number" name="retention_days" id="retention_days" class="small-text" min="7" max="365"
							value="<?php echo esc_attr( $this->addon->get_setting( 'retention_days', 30 ) ); ?>" />
						<?php esc_html_e( 'days', 'bkx-waiting-lists' ); ?>
						<p class="description"><?php esc_html_e( 'Delete old waiting list entries after this many days.', 'bkx-waiting-lists' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="show_position"><?php esc_html_e( 'Show Position', 'bkx-waiting-lists' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" name="show_position" id="show_position" value="1"
								<?php checked( $this->addon->get_setting( 'show_position', true ) ); ?> />
							<?php esc_html_e( 'Show customers their position in the waiting list', 'bkx-waiting-lists' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="notify_admin"><?php esc_html_e( 'Admin Notifications', 'bkx-waiting-lists' ); ?></label></th>
					<td>
						<label>
							<input type="checkbox" name="notify_admin" id="notify_admin" value="1"
								<?php checked( $this->addon->get_setting( 'notify_admin', false ) ); ?> />
							<?php esc_html_e( 'Notify admin when someone joins the waiting list', 'bkx-waiting-lists' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="bkx_save_settings" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'bkx-waiting-lists' ); ?>
				</button>
			</p>
		</form>

		<hr />

		<h2><?php esc_html_e( 'Shortcodes', 'bkx-waiting-lists' ); ?></h2>
		<table class="widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Shortcode', 'bkx-waiting-lists' ); ?></th>
					<th><?php esc_html_e( 'Description', 'bkx-waiting-lists' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>[bkx_my_waitlist]</code></td>
					<td><?php esc_html_e( 'Displays the current user\'s waiting list entries.', 'bkx-waiting-lists' ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render license tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_license_tab(): void {
		$license_key    = $this->addon->get_license_key();
		$license_status = $this->addon->get_license_status();
		$is_active      = 'valid' === $license_status;
		?>
		<form method="post">
			<?php wp_nonce_field( 'bkx_waiting_lists', 'bkx_waitlist_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th><label for="license_key"><?php esc_html_e( 'License Key', 'bkx-waiting-lists' ); ?></label></th>
					<td>
						<input type="text" name="license_key" id="license_key" class="regular-text"
							value="<?php echo esc_attr( $license_key ); ?>"
							<?php echo $is_active ? 'readonly' : ''; ?> />
						<?php if ( $is_active ) : ?>
							<span class="bkx-license-active">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Active', 'bkx-waiting-lists' ); ?>
							</span>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<p class="submit">
				<?php if ( $is_active ) : ?>
					<button type="submit" name="bkx_deactivate_license" class="button">
						<?php esc_html_e( 'Deactivate License', 'bkx-waiting-lists' ); ?>
					</button>
				<?php else : ?>
					<button type="submit" name="bkx_activate_license" class="button button-primary">
						<?php esc_html_e( 'Activate License', 'bkx-waiting-lists' ); ?>
					</button>
				<?php endif; ?>
			</p>
		</form>
		<?php
	}
}
