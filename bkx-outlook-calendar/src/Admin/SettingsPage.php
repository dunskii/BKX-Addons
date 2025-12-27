<?php
/**
 * Outlook Calendar Settings Page
 *
 * @package BookingX\OutlookCalendar
 * @since   1.0.0
 */

namespace BookingX\OutlookCalendar\Admin;

use BookingX\OutlookCalendar\OutlookCalendarAddon;

/**
 * Class SettingsPage
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var OutlookCalendarAddon
	 */
	private OutlookCalendarAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param OutlookCalendarAddon $addon Addon instance.
	 */
	public function __construct( OutlookCalendarAddon $addon ) {
		$this->addon = $addon;
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Add menu page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Outlook Calendar', 'bkx-outlook-calendar' ),
			__( 'Outlook Calendar', 'bkx-outlook-calendar' ),
			'manage_options',
			'bkx-outlook',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'bkx_booking_page_bkx-outlook' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'bkx-outlook-admin',
			BKX_OUTLOOK_URL . 'assets/css/admin.css',
			array(),
			BKX_OUTLOOK_VERSION
		);
	}

	/**
	 * Handle admin actions.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_actions(): void {
		// Handle OAuth callback.
		if ( isset( $_GET['page'] ) && 'bkx-outlook' === $_GET['page'] && isset( $_GET['action'] ) && 'oauth_callback' === $_GET['action'] ) {
			$this->handle_oauth_callback();
			return;
		}

		if ( ! isset( $_POST['bkx_outlook_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_outlook_nonce'] ) ), 'bkx_outlook_settings' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Save app credentials.
		if ( isset( $_POST['bkx_outlook_save_credentials'] ) ) {
			$client_id     = sanitize_text_field( wp_unslash( $_POST['bkx_outlook_client_id'] ?? '' ) );
			$client_secret = sanitize_text_field( wp_unslash( $_POST['bkx_outlook_client_secret'] ?? '' ) );

			$this->addon->update_setting( 'client_id', $client_id );
			$this->addon->update_setting( 'client_secret', $this->addon->get_encryption()->encrypt( $client_secret ) );

			add_settings_error( 'bkx_outlook', 'credentials_saved', __( 'Credentials saved.', 'bkx-outlook-calendar' ), 'success' );
		}

		// Disconnect.
		if ( isset( $_POST['bkx_outlook_disconnect'] ) ) {
			$this->addon->disconnect();
			add_settings_error( 'bkx_outlook', 'disconnected', __( 'Disconnected from Outlook.', 'bkx-outlook-calendar' ), 'success' );
		}

		// Save settings.
		if ( isset( $_POST['bkx_outlook_save_settings'] ) ) {
			$settings = array(
				'selected_calendar'  => sanitize_text_field( wp_unslash( $_POST['bkx_outlook_calendar'] ?? '' ) ),
				'sync_bookings'      => isset( $_POST['bkx_outlook_sync_bookings'] ),
				'check_availability' => isset( $_POST['bkx_outlook_check_availability'] ),
				'delete_on_cancel'   => isset( $_POST['bkx_outlook_delete_on_cancel'] ),
				'enable_sync'        => isset( $_POST['bkx_outlook_enable_sync'] ),
				'sync_cancellations' => isset( $_POST['bkx_outlook_sync_cancellations'] ),
				'sync_changes'       => isset( $_POST['bkx_outlook_sync_changes'] ),
				'event_title'        => sanitize_text_field( wp_unslash( $_POST['bkx_outlook_event_title'] ?? '' ) ),
				'event_description'  => sanitize_textarea_field( wp_unslash( $_POST['bkx_outlook_event_description'] ?? '' ) ),
			);

			foreach ( $settings as $key => $value ) {
				$this->addon->update_setting( $key, $value );
			}

			add_settings_error( 'bkx_outlook', 'settings_saved', __( 'Settings saved.', 'bkx-outlook-calendar' ), 'success' );
		}

		// License activation.
		if ( isset( $_POST['bkx_outlook_license_activate'] ) ) {
			$license_key = sanitize_text_field( wp_unslash( $_POST['bkx_outlook_license_key'] ?? '' ) );
			$result      = $this->addon->activate_license( $license_key );

			if ( is_wp_error( $result ) ) {
				add_settings_error( 'bkx_outlook', 'license_error', $result->get_error_message(), 'error' );
			} else {
				add_settings_error( 'bkx_outlook', 'license_activated', __( 'License activated.', 'bkx-outlook-calendar' ), 'success' );
			}
		}

		// License deactivation.
		if ( isset( $_POST['bkx_outlook_license_deactivate'] ) ) {
			$this->addon->deactivate_license();
			add_settings_error( 'bkx_outlook', 'license_deactivated', __( 'License deactivated.', 'bkx-outlook-calendar' ), 'success' );
		}
	}

	/**
	 * Handle OAuth callback.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function handle_oauth_callback(): void {
		if ( isset( $_GET['error'] ) ) {
			$error = sanitize_text_field( wp_unslash( $_GET['error_description'] ?? $_GET['error'] ) );
			add_settings_error( 'bkx_outlook', 'oauth_error', $error, 'error' );
			return;
		}

		if ( ! isset( $_GET['code'] ) || ! isset( $_GET['state'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['state'] ) ), 'bkx_outlook_oauth' ) ) {
			add_settings_error( 'bkx_outlook', 'invalid_state', __( 'Invalid OAuth state.', 'bkx-outlook-calendar' ), 'error' );
			return;
		}

		$code   = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$result = $this->addon->handle_oauth_callback( $code );

		if ( is_wp_error( $result ) ) {
			add_settings_error( 'bkx_outlook', 'oauth_error', $result->get_error_message(), 'error' );
		} else {
			add_settings_error( 'bkx_outlook', 'connected', __( 'Connected to Microsoft Outlook successfully!', 'bkx-outlook-calendar' ), 'success' );
		}

		// Redirect to remove query params.
		wp_safe_redirect( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-outlook' ) );
		exit;
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_page(): void {
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'connection';
		$tabs        = array(
			'connection' => __( 'Connection', 'bkx-outlook-calendar' ),
			'settings'   => __( 'Settings', 'bkx-outlook-calendar' ),
			'license'    => __( 'License', 'bkx-outlook-calendar' ),
		);
		?>
		<div class="wrap bkx-outlook-settings">
			<h1><?php esc_html_e( 'Outlook 365 Calendar', 'bkx-outlook-calendar' ); ?></h1>

			<?php settings_errors( 'bkx_outlook' ); ?>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id ) ); ?>"
					   class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="bkx-tab-content">
				<?php
				switch ( $current_tab ) {
					case 'settings':
						$this->render_settings_tab();
						break;
					case 'license':
						$this->render_license_tab();
						break;
					default:
						$this->render_connection_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render connection tab.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_connection_tab(): void {
		$is_connected = $this->addon->is_connected();
		$client_id    = $this->addon->get_setting( 'client_id', '' );
		$user_email   = $this->addon->get_setting( 'user_email', '' );
		$user_name    = $this->addon->get_setting( 'user_name', '' );
		?>
		<h2><?php esc_html_e( 'Microsoft Azure App Setup', 'bkx-outlook-calendar' ); ?></h2>

		<?php if ( ! $is_connected ) : ?>
			<div class="bkx-setup-instructions">
				<p><?php esc_html_e( 'To connect to Outlook 365, you need to create an Azure AD application:', 'bkx-outlook-calendar' ); ?></p>
				<ol>
					<li><?php esc_html_e( 'Go to Azure Portal > Azure Active Directory > App registrations', 'bkx-outlook-calendar' ); ?></li>
					<li><?php esc_html_e( 'Click "New registration"', 'bkx-outlook-calendar' ); ?></li>
					<li><?php esc_html_e( 'Set redirect URI to:', 'bkx-outlook-calendar' ); ?> <code><?php echo esc_url( admin_url( 'admin.php?page=bkx-outlook&action=oauth_callback' ) ); ?></code></li>
					<li><?php esc_html_e( 'Under "API permissions", add Microsoft Graph > Calendars.ReadWrite', 'bkx-outlook-calendar' ); ?></li>
					<li><?php esc_html_e( 'Create a client secret under "Certificates & secrets"', 'bkx-outlook-calendar' ); ?></li>
				</ol>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'bkx_outlook_settings', 'bkx_outlook_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="bkx_outlook_client_id"><?php esc_html_e( 'Application (client) ID', 'bkx-outlook-calendar' ); ?></label>
						</th>
						<td>
							<input type="text"
								   id="bkx_outlook_client_id"
								   name="bkx_outlook_client_id"
								   value="<?php echo esc_attr( $client_id ); ?>"
								   class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bkx_outlook_client_secret"><?php esc_html_e( 'Client Secret', 'bkx-outlook-calendar' ); ?></label>
						</th>
						<td>
							<input type="password"
								   id="bkx_outlook_client_secret"
								   name="bkx_outlook_client_secret"
								   value=""
								   class="regular-text"
								   placeholder="<?php esc_attr_e( 'Enter client secret', 'bkx-outlook-calendar' ); ?>">
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="bkx_outlook_save_credentials" class="button button-primary">
						<?php esc_html_e( 'Save Credentials', 'bkx-outlook-calendar' ); ?>
					</button>
				</p>
			</form>

			<?php if ( ! empty( $client_id ) ) : ?>
				<h3><?php esc_html_e( 'Connect Account', 'bkx-outlook-calendar' ); ?></h3>
				<p>
					<a href="<?php echo esc_url( $this->addon->get_auth_url() ); ?>" class="button button-primary button-hero">
						<?php esc_html_e( 'Connect to Microsoft Outlook', 'bkx-outlook-calendar' ); ?>
					</a>
				</p>
			<?php endif; ?>

		<?php else : ?>
			<div class="bkx-connection-status bkx-connected">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Connected', 'bkx-outlook-calendar' ); ?>
			</div>

			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Account', 'bkx-outlook-calendar' ); ?></th>
					<td>
						<?php echo esc_html( $user_name ); ?><br>
						<small><?php echo esc_html( $user_email ); ?></small>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Last Sync', 'bkx-outlook-calendar' ); ?></th>
					<td><?php echo esc_html( $this->addon->get_setting( 'last_sync', __( 'Never', 'bkx-outlook-calendar' ) ) ); ?></td>
				</tr>
			</table>

			<form method="post">
				<?php wp_nonce_field( 'bkx_outlook_settings', 'bkx_outlook_nonce' ); ?>
				<button type="submit" name="bkx_outlook_disconnect" class="button">
					<?php esc_html_e( 'Disconnect', 'bkx-outlook-calendar' ); ?>
				</button>
			</form>
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
		if ( ! $this->addon->is_connected() ) {
			echo '<p>' . esc_html__( 'Please connect to Outlook first.', 'bkx-outlook-calendar' ) . '</p>';
			return;
		}

		$calendars = $this->addon->get_api() ? ( new \BookingX\OutlookCalendar\Services\CalendarService( $this->addon->get_api(), $this->addon ) )->get_calendars() : array();
		?>
		<form method="post">
			<?php wp_nonce_field( 'bkx_outlook_settings', 'bkx_outlook_nonce' ); ?>

			<h2><?php esc_html_e( 'Calendar Settings', 'bkx-outlook-calendar' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="bkx_outlook_calendar"><?php esc_html_e( 'Calendar', 'bkx-outlook-calendar' ); ?></label>
					</th>
					<td>
						<select id="bkx_outlook_calendar" name="bkx_outlook_calendar">
							<option value=""><?php esc_html_e( '— Select Calendar —', 'bkx-outlook-calendar' ); ?></option>
							<?php foreach ( $calendars as $calendar ) : ?>
								<option value="<?php echo esc_attr( $calendar['id'] ); ?>" <?php selected( $this->addon->get_setting( 'selected_calendar' ), $calendar['id'] ); ?>>
									<?php echo esc_html( $calendar['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sync Options', 'bkx-outlook-calendar' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_outlook_sync_bookings" value="1" <?php checked( $this->addon->get_setting( 'sync_bookings', true ) ); ?>>
							<?php esc_html_e( 'Create Outlook events for new bookings', 'bkx-outlook-calendar' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="bkx_outlook_check_availability" value="1" <?php checked( $this->addon->get_setting( 'check_availability', true ) ); ?>>
							<?php esc_html_e( 'Check Outlook calendar for availability', 'bkx-outlook-calendar' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="bkx_outlook_delete_on_cancel" value="1" <?php checked( $this->addon->get_setting( 'delete_on_cancel', true ) ); ?>>
							<?php esc_html_e( 'Delete Outlook event when booking is cancelled', 'bkx-outlook-calendar' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="bkx_outlook_enable_sync" value="1" <?php checked( $this->addon->get_setting( 'enable_sync', true ) ); ?>>
							<?php esc_html_e( 'Enable automatic hourly sync', 'bkx-outlook-calendar' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Two-Way Sync', 'bkx-outlook-calendar' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_outlook_sync_cancellations" value="1" <?php checked( $this->addon->get_setting( 'sync_cancellations', false ) ); ?>>
							<?php esc_html_e( 'Cancel booking if Outlook event is deleted', 'bkx-outlook-calendar' ); ?>
						</label><br>
						<label>
							<input type="checkbox" name="bkx_outlook_sync_changes" value="1" <?php checked( $this->addon->get_setting( 'sync_changes', false ) ); ?>>
							<?php esc_html_e( 'Update booking if Outlook event time changes', 'bkx-outlook-calendar' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Event Format', 'bkx-outlook-calendar' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="bkx_outlook_event_title"><?php esc_html_e( 'Event Title', 'bkx-outlook-calendar' ); ?></label>
					</th>
					<td>
						<input type="text"
							   id="bkx_outlook_event_title"
							   name="bkx_outlook_event_title"
							   value="<?php echo esc_attr( $this->addon->get_setting( 'event_title', '{service} - {customer}' ) ); ?>"
							   class="regular-text">
						<p class="description">
							<?php esc_html_e( 'Available: {booking_id}, {service}, {resource}, {customer}, {email}, {phone}, {date}, {time}', 'bkx-outlook-calendar' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx_outlook_event_description"><?php esc_html_e( 'Event Description', 'bkx-outlook-calendar' ); ?></label>
					</th>
					<td>
						<textarea id="bkx_outlook_event_description"
								  name="bkx_outlook_event_description"
								  rows="5"
								  class="large-text"><?php echo esc_textarea( $this->addon->get_setting( 'event_description', '' ) ); ?></textarea>
					</td>
				</tr>
			</table>

			<p class="submit">
				<button type="submit" name="bkx_outlook_save_settings" class="button button-primary">
					<?php esc_html_e( 'Save Settings', 'bkx-outlook-calendar' ); ?>
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
		$license_key    = get_option( 'bkx_outlook_license_key', '' );
		$license_status = get_option( 'bkx_outlook_license_status', '' );
		?>
		<div class="bkx-license-settings">
			<h2><?php esc_html_e( 'License Activation', 'bkx-outlook-calendar' ); ?></h2>

			<form method="post">
				<?php wp_nonce_field( 'bkx_outlook_settings', 'bkx_outlook_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="bkx_outlook_license_key"><?php esc_html_e( 'License Key', 'bkx-outlook-calendar' ); ?></label>
						</th>
						<td>
							<input type="password"
								   id="bkx_outlook_license_key"
								   name="bkx_outlook_license_key"
								   value="<?php echo esc_attr( $license_key ); ?>"
								   class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'bkx-outlook-calendar' ); ?></th>
						<td>
							<?php if ( 'valid' === $license_status ) : ?>
								<span class="bkx-license-status bkx-license-active">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e( 'Active', 'bkx-outlook-calendar' ); ?>
								</span>
							<?php else : ?>
								<span class="bkx-license-status bkx-license-inactive">
									<span class="dashicons dashicons-marker"></span>
									<?php esc_html_e( 'Not Activated', 'bkx-outlook-calendar' ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<p class="submit">
					<?php if ( 'valid' === $license_status ) : ?>
						<button type="submit" name="bkx_outlook_license_deactivate" class="button">
							<?php esc_html_e( 'Deactivate License', 'bkx-outlook-calendar' ); ?>
						</button>
					<?php else : ?>
						<button type="submit" name="bkx_outlook_license_activate" class="button button-primary">
							<?php esc_html_e( 'Activate License', 'bkx-outlook-calendar' ); ?>
						</button>
					<?php endif; ?>
				</p>
			</form>
		</div>
		<?php
	}
}
