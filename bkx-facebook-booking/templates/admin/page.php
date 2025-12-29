<?php
/**
 * Admin settings page template.
 *
 * @package BookingX\FacebookBooking
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_fb_booking_settings', array() );
$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
?>

<div class="wrap bkx-fb-admin">
	<h1>
		<span class="dashicons dashicons-facebook-alt"></span>
		<?php esc_html_e( 'Facebook Booking', 'bkx-facebook-booking' ); ?>
	</h1>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-facebook-booking&tab=settings' ) ); ?>"
		   class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'bkx-facebook-booking' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-facebook-booking&tab=pages' ) ); ?>"
		   class="nav-tab <?php echo 'pages' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Connected Pages', 'bkx-facebook-booking' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-facebook-booking&tab=bookings' ) ); ?>"
		   class="nav-tab <?php echo 'bookings' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Bookings', 'bkx-facebook-booking' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-facebook-booking&tab=setup' ) ); ?>"
		   class="nav-tab <?php echo 'setup' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Setup Guide', 'bkx-facebook-booking' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-facebook-booking&tab=logs' ) ); ?>"
		   class="nav-tab <?php echo 'logs' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Webhook Logs', 'bkx-facebook-booking' ); ?>
		</a>
	</nav>

	<div class="bkx-fb-content">
		<?php
		switch ( $tab ) {
			case 'pages':
				$this->render_pages_tab();
				break;

			case 'bookings':
				$this->render_bookings_tab();
				break;

			case 'setup':
				$this->render_setup_tab();
				break;

			case 'logs':
				$this->render_logs_tab();
				break;

			default:
				$this->render_settings_tab();
				break;
		}
		?>
	</div>
</div>

<?php
/**
 * Render Settings Tab
 */
function render_settings_tab() {
	$settings = get_option( 'bkx_fb_booking_settings', array() );
	?>
	<div class="bkx-fb-settings">
		<form id="bkx-fb-settings-form" method="post">
			<?php wp_nonce_field( 'bkx_fb_settings', 'bkx_fb_nonce' ); ?>

			<div class="bkx-fb-card">
				<h2><?php esc_html_e( 'Facebook App Configuration', 'bkx-facebook-booking' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Enter your Facebook App credentials. You can create an app at', 'bkx-facebook-booking' ); ?>
					<a href="https://developers.facebook.com/apps/" target="_blank">developers.facebook.com</a>
				</p>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="app_id"><?php esc_html_e( 'App ID', 'bkx-facebook-booking' ); ?></label>
						</th>
						<td>
							<input type="text" id="app_id" name="app_id"
								   value="<?php echo esc_attr( $settings['app_id'] ?? '' ); ?>"
								   class="regular-text" required>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="app_secret"><?php esc_html_e( 'App Secret', 'bkx-facebook-booking' ); ?></label>
						</th>
						<td>
							<input type="password" id="app_secret" name="app_secret"
								   value="<?php echo esc_attr( $settings['app_secret'] ?? '' ); ?>"
								   class="regular-text" required>
							<button type="button" class="button button-secondary bkx-toggle-password">
								<?php esc_html_e( 'Show', 'bkx-facebook-booking' ); ?>
							</button>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="verify_token"><?php esc_html_e( 'Webhook Verify Token', 'bkx-facebook-booking' ); ?></label>
						</th>
						<td>
							<input type="text" id="verify_token" name="verify_token"
								   value="<?php echo esc_attr( $settings['verify_token'] ?? wp_generate_password( 32, false ) ); ?>"
								   class="regular-text" required>
							<button type="button" class="button button-secondary" id="bkx-generate-token">
								<?php esc_html_e( 'Generate', 'bkx-facebook-booking' ); ?>
							</button>
							<p class="description">
								<?php esc_html_e( 'Use this token when configuring webhooks in Facebook App settings.', 'bkx-facebook-booking' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<div class="bkx-fb-card">
				<h2><?php esc_html_e( 'Webhook URLs', 'bkx-facebook-booking' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Use these URLs when setting up webhooks in your Facebook App.', 'bkx-facebook-booking' ); ?>
				</p>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Callback URL', 'bkx-facebook-booking' ); ?></th>
						<td>
							<code id="webhook-url"><?php echo esc_url( rest_url( 'bkx-fb/v1/webhook' ) ); ?></code>
							<button type="button" class="button button-secondary bkx-copy-btn" data-copy="webhook-url">
								<?php esc_html_e( 'Copy', 'bkx-facebook-booking' ); ?>
							</button>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'OAuth Redirect URI', 'bkx-facebook-booking' ); ?></th>
						<td>
							<code id="oauth-url"><?php echo esc_url( admin_url( 'admin.php?page=bkx-facebook-booking&action=oauth_callback' ) ); ?></code>
							<button type="button" class="button button-secondary bkx-copy-btn" data-copy="oauth-url">
								<?php esc_html_e( 'Copy', 'bkx-facebook-booking' ); ?>
							</button>
						</td>
					</tr>
				</table>
			</div>

			<div class="bkx-fb-card">
				<h2><?php esc_html_e( 'Business Settings', 'bkx-facebook-booking' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="business_name"><?php esc_html_e( 'Business Name', 'bkx-facebook-booking' ); ?></label>
						</th>
						<td>
							<input type="text" id="business_name" name="business_name"
								   value="<?php echo esc_attr( $settings['business_name'] ?? get_bloginfo( 'name' ) ); ?>"
								   class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Displayed in Messenger conversations.', 'bkx-facebook-booking' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="contact_email"><?php esc_html_e( 'Contact Email', 'bkx-facebook-booking' ); ?></label>
						</th>
						<td>
							<input type="email" id="contact_email" name="contact_email"
								   value="<?php echo esc_attr( $settings['contact_email'] ?? get_option( 'admin_email' ) ); ?>"
								   class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="contact_phone"><?php esc_html_e( 'Contact Phone', 'bkx-facebook-booking' ); ?></label>
						</th>
						<td>
							<input type="tel" id="contact_phone" name="contact_phone"
								   value="<?php echo esc_attr( $settings['contact_phone'] ?? '' ); ?>"
								   class="regular-text">
						</td>
					</tr>
				</table>
			</div>

			<div class="bkx-fb-card">
				<h2><?php esc_html_e( 'Booking Settings', 'bkx-facebook-booking' ); ?></h2>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="auto_confirm"><?php esc_html_e( 'Auto-confirm Bookings', 'bkx-facebook-booking' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" id="auto_confirm" name="auto_confirm" value="1"
									   <?php checked( ! empty( $settings['auto_confirm'] ) ); ?>>
								<?php esc_html_e( 'Automatically confirm bookings made through Facebook', 'bkx-facebook-booking' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="send_reminders"><?php esc_html_e( 'Send Reminders', 'bkx-facebook-booking' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" id="send_reminders" name="send_reminders" value="1"
									   <?php checked( ! empty( $settings['send_reminders'] ) ); ?>>
								<?php esc_html_e( 'Send booking reminders via Messenger', 'bkx-facebook-booking' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="reminder_hours"><?php esc_html_e( 'Reminder Time', 'bkx-facebook-booking' ); ?></label>
						</th>
						<td>
							<select id="reminder_hours" name="reminder_hours">
								<option value="1" <?php selected( ( $settings['reminder_hours'] ?? 24 ), 1 ); ?>>
									<?php esc_html_e( '1 hour before', 'bkx-facebook-booking' ); ?>
								</option>
								<option value="2" <?php selected( ( $settings['reminder_hours'] ?? 24 ), 2 ); ?>>
									<?php esc_html_e( '2 hours before', 'bkx-facebook-booking' ); ?>
								</option>
								<option value="24" <?php selected( ( $settings['reminder_hours'] ?? 24 ), 24 ); ?>>
									<?php esc_html_e( '24 hours before', 'bkx-facebook-booking' ); ?>
								</option>
								<option value="48" <?php selected( ( $settings['reminder_hours'] ?? 24 ), 48 ); ?>>
									<?php esc_html_e( '48 hours before', 'bkx-facebook-booking' ); ?>
								</option>
							</select>
						</td>
					</tr>
				</table>
			</div>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'bkx-facebook-booking' ); ?>
				</button>
			</p>
		</form>
	</div>
	<?php
}

/**
 * Render Pages Tab
 */
function render_pages_tab() {
	$page_manager = new \BookingX\FacebookBooking\Services\PageManager(
		new \BookingX\FacebookBooking\Services\FacebookApi()
	);
	$pages = $page_manager->get_pages( 'all' );
	$settings = get_option( 'bkx_fb_booking_settings', array() );
	?>
	<div class="bkx-fb-pages">
		<div class="bkx-fb-card">
			<h2><?php esc_html_e( 'Connect Facebook Page', 'bkx-facebook-booking' ); ?></h2>
			<?php if ( empty( $settings['app_id'] ) || empty( $settings['app_secret'] ) ) : ?>
				<div class="notice notice-warning inline">
					<p><?php esc_html_e( 'Please configure your Facebook App credentials in the Settings tab first.', 'bkx-facebook-booking' ); ?></p>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'Connect your Facebook Business Page to enable booking functionality.', 'bkx-facebook-booking' ); ?></p>
				<button type="button" class="button button-primary" id="bkx-connect-page">
					<span class="dashicons dashicons-facebook-alt"></span>
					<?php esc_html_e( 'Connect Facebook Page', 'bkx-facebook-booking' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $pages ) ) : ?>
			<div class="bkx-fb-card">
				<h2><?php esc_html_e( 'Connected Pages', 'bkx-facebook-booking' ); ?></h2>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Page', 'bkx-facebook-booking' ); ?></th>
							<th><?php esc_html_e( 'Category', 'bkx-facebook-booking' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bkx-facebook-booking' ); ?></th>
							<th><?php esc_html_e( 'Last Sync', 'bkx-facebook-booking' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bkx-facebook-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $pages as $page ) : ?>
							<tr data-page-id="<?php echo esc_attr( $page->page_id ); ?>">
								<td>
									<strong><?php echo esc_html( $page->page_name ); ?></strong>
									<br>
									<small><?php echo esc_html( $page->page_id ); ?></small>
								</td>
								<td><?php echo esc_html( $page->category ?: '—' ); ?></td>
								<td>
									<span class="bkx-status bkx-status-<?php echo esc_attr( $page->status ); ?>">
										<?php echo esc_html( ucfirst( str_replace( '_', ' ', $page->status ) ) ); ?>
									</span>
								</td>
								<td>
									<?php
									if ( $page->last_sync ) {
										echo esc_html( human_time_diff( strtotime( $page->last_sync ), current_time( 'timestamp' ) ) . ' ago' );
									} else {
										esc_html_e( 'Never', 'bkx-facebook-booking' );
									}
									?>
								</td>
								<td>
									<button type="button" class="button button-small bkx-manage-services"
											data-page-id="<?php echo esc_attr( $page->page_id ); ?>">
										<?php esc_html_e( 'Services', 'bkx-facebook-booking' ); ?>
									</button>
									<button type="button" class="button button-small bkx-sync-page"
											data-page-id="<?php echo esc_attr( $page->page_id ); ?>">
										<?php esc_html_e( 'Sync', 'bkx-facebook-booking' ); ?>
									</button>
									<button type="button" class="button button-small button-link-delete bkx-disconnect-page"
											data-page-id="<?php echo esc_attr( $page->page_id ); ?>">
										<?php esc_html_e( 'Disconnect', 'bkx-facebook-booking' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<!-- Services Modal -->
	<div id="bkx-services-modal" class="bkx-modal" style="display: none;">
		<div class="bkx-modal-content">
			<span class="bkx-modal-close">&times;</span>
			<h2><?php esc_html_e( 'Manage Services', 'bkx-facebook-booking' ); ?></h2>
			<div id="bkx-services-list">
				<?php esc_html_e( 'Loading...', 'bkx-facebook-booking' ); ?>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Render Bookings Tab
 */
function render_bookings_tab() {
	global $wpdb;
	$table = $wpdb->prefix . 'bkx_fb_bookings';

	$page_num = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	$per_page = 20;
	$offset = ( $page_num - 1 ) * $per_page;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$bookings = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
		$per_page,
		$offset
	) );
	?>
	<div class="bkx-fb-bookings">
		<div class="bkx-fb-card">
			<div class="bkx-fb-card-header">
				<h2><?php esc_html_e( 'Facebook Bookings', 'bkx-facebook-booking' ); ?></h2>
				<button type="button" class="button" id="bkx-export-csv">
					<?php esc_html_e( 'Export CSV', 'bkx-facebook-booking' ); ?>
				</button>
			</div>

			<?php if ( empty( $bookings ) ) : ?>
				<p><?php esc_html_e( 'No bookings yet.', 'bkx-facebook-booking' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Booking ID', 'bkx-facebook-booking' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'bkx-facebook-booking' ); ?></th>
							<th><?php esc_html_e( 'Date & Time', 'bkx-facebook-booking' ); ?></th>
							<th><?php esc_html_e( 'Source', 'bkx-facebook-booking' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bkx-facebook-booking' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bkx-facebook-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $bookings as $booking ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $booking->fb_booking_id ); ?></strong>
									<?php if ( $booking->bkx_booking_id ) : ?>
										<br>
										<small>
											<a href="<?php echo esc_url( get_edit_post_link( $booking->bkx_booking_id ) ); ?>">
												<?php echo esc_html( '#' . $booking->bkx_booking_id ); ?>
											</a>
										</small>
									<?php endif; ?>
								</td>
								<td>
									<?php echo esc_html( $booking->customer_name ?: __( 'Facebook User', 'bkx-facebook-booking' ) ); ?>
									<?php if ( $booking->customer_email ) : ?>
										<br><small><?php echo esc_html( $booking->customer_email ); ?></small>
									<?php endif; ?>
								</td>
								<td>
									<?php
									echo esc_html( wp_date( 'M j, Y', strtotime( $booking->booking_date ) ) );
									echo '<br><small>' . esc_html( wp_date( 'g:i A', strtotime( $booking->start_time ) ) ) . '</small>';
									?>
								</td>
								<td>
									<span class="bkx-source bkx-source-<?php echo esc_attr( $booking->source ); ?>">
										<?php echo esc_html( ucfirst( str_replace( '_', ' ', $booking->source ) ) ); ?>
									</span>
								</td>
								<td>
									<span class="bkx-status bkx-status-<?php echo esc_attr( $booking->status ); ?>">
										<?php echo esc_html( ucfirst( $booking->status ) ); ?>
									</span>
								</td>
								<td>
									<?php if ( 'pending' === $booking->status || 'confirmed' === $booking->status ) : ?>
										<button type="button" class="button button-small bkx-cancel-booking"
												data-booking-id="<?php echo esc_attr( $booking->fb_booking_id ); ?>">
											<?php esc_html_e( 'Cancel', 'bkx-facebook-booking' ); ?>
										</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php
				$total_pages = ceil( $total / $per_page );
				if ( $total_pages > 1 ) :
					?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<?php
							echo paginate_links( array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $page_num,
							) );
							?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * Render Setup Tab
 */
function render_setup_tab() {
	?>
	<div class="bkx-fb-setup">
		<div class="bkx-fb-card">
			<h2><?php esc_html_e( 'Setup Guide', 'bkx-facebook-booking' ); ?></h2>
			<p><?php esc_html_e( 'Follow these steps to configure Facebook Booking for your business.', 'bkx-facebook-booking' ); ?></p>
		</div>

		<div class="bkx-fb-card">
			<h3><?php esc_html_e( 'Step 1: Create a Facebook App', 'bkx-facebook-booking' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Go to', 'bkx-facebook-booking' ); ?> <a href="https://developers.facebook.com/apps/" target="_blank">developers.facebook.com/apps</a></li>
				<li><?php esc_html_e( 'Click "Create App" and select "Business" type', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'Enter your app name and contact email', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'Copy the App ID and App Secret to the Settings tab', 'bkx-facebook-booking' ); ?></li>
			</ol>
		</div>

		<div class="bkx-fb-card">
			<h3><?php esc_html_e( 'Step 2: Configure Messenger', 'bkx-facebook-booking' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'In your Facebook App dashboard, go to "Add Products"', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'Add "Messenger" to your app', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'In Messenger settings, add your Facebook Page', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'Generate a Page Access Token', 'bkx-facebook-booking' ); ?></li>
			</ol>
		</div>

		<div class="bkx-fb-card">
			<h3><?php esc_html_e( 'Step 3: Set Up Webhooks', 'bkx-facebook-booking' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'In your app dashboard, go to Messenger > Settings > Webhooks', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'Click "Add Callback URL"', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'Enter the Callback URL from the Settings tab', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'Enter the Verify Token from the Settings tab', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'Subscribe to: messages, messaging_postbacks, messaging_optins', 'bkx-facebook-booking' ); ?></li>
			</ol>
		</div>

		<div class="bkx-fb-card">
			<h3><?php esc_html_e( 'Step 4: Connect Your Page', 'bkx-facebook-booking' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Go to the "Connected Pages" tab', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'Click "Connect Facebook Page"', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'Select the page(s) you want to connect', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'Map your BookingX services to the page', 'bkx-facebook-booking' ); ?></li>
			</ol>
		</div>

		<div class="bkx-fb-card">
			<h3><?php esc_html_e( 'Step 5: Test Your Integration', 'bkx-facebook-booking' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Go to your Facebook Page', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'Click the "Message" button', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'Type "book" to start the booking flow', 'bkx-facebook-booking' ); ?></li>
				<li><?php esc_html_e( 'Complete a test booking', 'bkx-facebook-booking' ); ?></li>
			</ol>
		</div>

		<div class="bkx-fb-card">
			<h3><?php esc_html_e( 'Required Permissions', 'bkx-facebook-booking' ); ?></h3>
			<p><?php esc_html_e( 'Your app needs these permissions to work:', 'bkx-facebook-booking' ); ?></p>
			<ul>
				<li><code>pages_show_list</code> - <?php esc_html_e( 'Show list of pages you manage', 'bkx-facebook-booking' ); ?></li>
				<li><code>pages_read_engagement</code> - <?php esc_html_e( 'Read page engagement', 'bkx-facebook-booking' ); ?></li>
				<li><code>pages_manage_metadata</code> - <?php esc_html_e( 'Manage page settings', 'bkx-facebook-booking' ); ?></li>
				<li><code>pages_messaging</code> - <?php esc_html_e( 'Send and receive messages', 'bkx-facebook-booking' ); ?></li>
			</ul>
		</div>
	</div>
	<?php
}

/**
 * Render Logs Tab
 */
function render_logs_tab() {
	global $wpdb;
	$table = $wpdb->prefix . 'bkx_fb_webhooks';

	$page_num = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	$per_page = 50;
	$offset = ( $page_num - 1 ) * $per_page;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$logs = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
		$per_page,
		$offset
	) );
	?>
	<div class="bkx-fb-logs">
		<div class="bkx-fb-card">
			<div class="bkx-fb-card-header">
				<h2><?php esc_html_e( 'Webhook Logs', 'bkx-facebook-booking' ); ?></h2>
				<button type="button" class="button" id="bkx-clear-logs">
					<?php esc_html_e( 'Clear Old Logs', 'bkx-facebook-booking' ); ?>
				</button>
			</div>

			<?php if ( empty( $logs ) ) : ?>
				<p><?php esc_html_e( 'No webhook events logged yet.', 'bkx-facebook-booking' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 150px;"><?php esc_html_e( 'Date/Time', 'bkx-facebook-booking' ); ?></th>
							<th style="width: 150px;"><?php esc_html_e( 'Event Type', 'bkx-facebook-booking' ); ?></th>
							<th style="width: 150px;"><?php esc_html_e( 'Page ID', 'bkx-facebook-booking' ); ?></th>
							<th><?php esc_html_e( 'Payload', 'bkx-facebook-booking' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', strtotime( $log->created_at ) ) ); ?></td>
								<td>
									<code><?php echo esc_html( $log->event_type ); ?></code>
								</td>
								<td><?php echo esc_html( $log->page_id ?: '—' ); ?></td>
								<td>
									<details>
										<summary><?php esc_html_e( 'View payload', 'bkx-facebook-booking' ); ?></summary>
										<pre style="max-height: 200px; overflow: auto;"><?php echo esc_html( wp_json_encode( json_decode( $log->payload ), JSON_PRETTY_PRINT ) ); ?></pre>
									</details>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php
				$total_pages = ceil( $total / $per_page );
				if ( $total_pages > 1 ) :
					?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<?php
							echo paginate_links( array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $page_num,
							) );
							?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
