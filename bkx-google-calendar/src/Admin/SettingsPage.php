<?php
/**
 * Google Calendar Settings Page
 *
 * @package BookingX\GoogleCalendar\Admin
 * @since   1.0.0
 */

namespace BookingX\GoogleCalendar\Admin;

use BookingX\GoogleCalendar\GoogleCalendarAddon;
use BookingX\AddonSDK\Services\EncryptionService;

/**
 * Settings page class.
 *
 * @since 1.0.0
 */
class SettingsPage {

	/**
	 * Addon instance.
	 *
	 * @var GoogleCalendarAddon
	 */
	protected GoogleCalendarAddon $addon;

	/**
	 * Constructor.
	 *
	 * @param GoogleCalendarAddon $addon Addon instance.
	 */
	public function __construct( GoogleCalendarAddon $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Initialize settings page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add settings page to admin menu.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Google Calendar', 'bkx-google-calendar' ),
			__( 'Google Calendar', 'bkx-google-calendar' ),
			'manage_options',
			'bkx-google-calendar',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'bkx_google_calendar_settings',
			'bkx_google_calendar_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// API Credentials Section.
		add_settings_section(
			'bkx_google_credentials',
			__( 'Google API Credentials', 'bkx-google-calendar' ),
			array( $this, 'render_credentials_section' ),
			'bkx-google-calendar'
		);

		add_settings_field(
			'client_id',
			__( 'Client ID', 'bkx-google-calendar' ),
			array( $this, 'render_text_field' ),
			'bkx-google-calendar',
			'bkx_google_credentials',
			array( 'id' => 'client_id', 'type' => 'password' )
		);

		add_settings_field(
			'client_secret',
			__( 'Client Secret', 'bkx-google-calendar' ),
			array( $this, 'render_text_field' ),
			'bkx-google-calendar',
			'bkx_google_credentials',
			array( 'id' => 'client_secret', 'type' => 'password' )
		);

		// Sync Settings Section.
		add_settings_section(
			'bkx_google_sync',
			__( 'Sync Settings', 'bkx-google-calendar' ),
			array( $this, 'render_sync_section' ),
			'bkx-google-calendar'
		);

		add_settings_field(
			'sync_enabled',
			__( 'Enable Sync', 'bkx-google-calendar' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-google-calendar',
			'bkx_google_sync',
			array(
				'id'          => 'sync_enabled',
				'description' => __( 'Enable automatic synchronization with Google Calendar.', 'bkx-google-calendar' ),
			)
		);

		add_settings_field(
			'sync_direction',
			__( 'Sync Direction', 'bkx-google-calendar' ),
			array( $this, 'render_select_field' ),
			'bkx-google-calendar',
			'bkx_google_sync',
			array(
				'id'      => 'sync_direction',
				'options' => array(
					'two_way'             => __( 'Two-way sync', 'bkx-google-calendar' ),
					'one_way_to_google'   => __( 'BookingX to Google only', 'bkx-google-calendar' ),
					'one_way_from_google' => __( 'Google to BookingX only (availability blocking)', 'bkx-google-calendar' ),
				),
			)
		);

		add_settings_field(
			'sync_interval',
			__( 'Sync Interval', 'bkx-google-calendar' ),
			array( $this, 'render_number_field' ),
			'bkx-google-calendar',
			'bkx_google_sync',
			array(
				'id'          => 'sync_interval',
				'description' => __( 'How often to sync (in minutes).', 'bkx-google-calendar' ),
				'min'         => 5,
				'max'         => 60,
				'suffix'      => __( 'minutes', 'bkx-google-calendar' ),
			)
		);

		add_settings_field(
			'sync_staff_calendars',
			__( 'Staff Calendars', 'bkx-google-calendar' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-google-calendar',
			'bkx_google_sync',
			array(
				'id'          => 'sync_staff_calendars',
				'description' => __( 'Allow staff to connect their own Google Calendars.', 'bkx-google-calendar' ),
			)
		);

		// Event Settings Section.
		add_settings_section(
			'bkx_google_events',
			__( 'Event Settings', 'bkx-google-calendar' ),
			array( $this, 'render_events_section' ),
			'bkx-google-calendar'
		);

		add_settings_field(
			'event_title_format',
			__( 'Event Title Format', 'bkx-google-calendar' ),
			array( $this, 'render_text_field' ),
			'bkx-google-calendar',
			'bkx_google_events',
			array(
				'id'          => 'event_title_format',
				'description' => __( 'Available: {service_name}, {customer_name}, {staff_name}, {booking_id}', 'bkx-google-calendar' ),
			)
		);

		add_settings_field(
			'event_description',
			__( 'Event Description', 'bkx-google-calendar' ),
			array( $this, 'render_textarea_field' ),
			'bkx-google-calendar',
			'bkx_google_events',
			array(
				'id'          => 'event_description',
				'description' => __( 'Template for Google Calendar event description.', 'bkx-google-calendar' ),
			)
		);

		add_settings_field(
			'event_color',
			__( 'Event Color', 'bkx-google-calendar' ),
			array( $this, 'render_select_field' ),
			'bkx-google-calendar',
			'bkx_google_events',
			array(
				'id'      => 'event_color',
				'options' => array(
					'1'  => __( 'Lavender', 'bkx-google-calendar' ),
					'2'  => __( 'Sage', 'bkx-google-calendar' ),
					'3'  => __( 'Grape', 'bkx-google-calendar' ),
					'4'  => __( 'Flamingo', 'bkx-google-calendar' ),
					'5'  => __( 'Banana', 'bkx-google-calendar' ),
					'6'  => __( 'Tangerine', 'bkx-google-calendar' ),
					'7'  => __( 'Peacock', 'bkx-google-calendar' ),
					'8'  => __( 'Graphite', 'bkx-google-calendar' ),
					'9'  => __( 'Blueberry', 'bkx-google-calendar' ),
					'10' => __( 'Basil', 'bkx-google-calendar' ),
					'11' => __( 'Tomato', 'bkx-google-calendar' ),
				),
			)
		);

		add_settings_field(
			'add_reminders',
			__( 'Add Reminders', 'bkx-google-calendar' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-google-calendar',
			'bkx_google_events',
			array(
				'id'          => 'add_reminders',
				'description' => __( 'Add reminder notifications to calendar events.', 'bkx-google-calendar' ),
			)
		);

		add_settings_field(
			'reminder_minutes',
			__( 'Reminder Time', 'bkx-google-calendar' ),
			array( $this, 'render_number_field' ),
			'bkx-google-calendar',
			'bkx_google_events',
			array(
				'id'          => 'reminder_minutes',
				'description' => __( 'Minutes before event to send reminder.', 'bkx-google-calendar' ),
				'min'         => 5,
				'max'         => 1440,
				'suffix'      => __( 'minutes', 'bkx-google-calendar' ),
			)
		);

		// Availability Section.
		add_settings_section(
			'bkx_google_availability',
			__( 'Availability Settings', 'bkx-google-calendar' ),
			array( $this, 'render_availability_section' ),
			'bkx-google-calendar'
		);

		add_settings_field(
			'block_busy_times',
			__( 'Block Busy Times', 'bkx-google-calendar' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-google-calendar',
			'bkx_google_availability',
			array(
				'id'          => 'block_busy_times',
				'description' => __( 'Block booking slots when staff has Google Calendar events.', 'bkx-google-calendar' ),
			)
		);

		add_settings_field(
			'buffer_minutes',
			__( 'Buffer Time', 'bkx-google-calendar' ),
			array( $this, 'render_number_field' ),
			'bkx-google-calendar',
			'bkx_google_availability',
			array(
				'id'          => 'buffer_minutes',
				'description' => __( 'Additional buffer time around Google Calendar events.', 'bkx-google-calendar' ),
				'min'         => 0,
				'max'         => 120,
				'suffix'      => __( 'minutes', 'bkx-google-calendar' ),
			)
		);

		// Customer Calendar Section.
		add_settings_section(
			'bkx_google_customer',
			__( 'Customer Calendar', 'bkx-google-calendar' ),
			array( $this, 'render_customer_section' ),
			'bkx-google-calendar'
		);

		add_settings_field(
			'customer_add_to_gcal',
			__( 'Add to Google Calendar', 'bkx-google-calendar' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-google-calendar',
			'bkx_google_customer',
			array(
				'id'          => 'customer_add_to_gcal',
				'description' => __( 'Show "Add to Google Calendar" button to customers.', 'bkx-google-calendar' ),
			)
		);

		add_settings_field(
			'customer_ical_export',
			__( 'iCal Download', 'bkx-google-calendar' ),
			array( $this, 'render_checkbox_field' ),
			'bkx-google-calendar',
			'bkx_google_customer',
			array(
				'id'          => 'customer_ical_export',
				'description' => __( 'Allow customers to download .ics file for their booking.', 'bkx-google-calendar' ),
			)
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check for messages.
		if ( isset( $_GET['message'] ) && 'connected' === $_GET['message'] ) {
			add_settings_error(
				'bkx_google_messages',
				'bkx_google_connected',
				__( 'Successfully connected to Google Calendar!', 'bkx-google-calendar' ),
				'success'
			);
		}

		if ( isset( $_GET['error_message'] ) ) {
			add_settings_error(
				'bkx_google_messages',
				'bkx_google_error',
				sanitize_text_field( wp_unslash( $_GET['error_message'] ) ),
				'error'
			);
		}

		settings_errors( 'bkx_google_messages' );

		$google_api   = $this->addon->get_google_api();
		$is_connected = $google_api->is_connected();
		$connection   = $is_connected ? $google_api->get_main_connection() : null;

		?>
		<div class="wrap bkx-google-calendar-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<!-- Connection Status -->
			<div class="bkx-connection-status <?php echo $is_connected ? 'connected' : 'disconnected'; ?>">
				<?php if ( $is_connected ) : ?>
					<div class="bkx-connection-info">
						<span class="dashicons dashicons-yes-alt"></span>
						<div class="bkx-connection-details">
							<strong><?php esc_html_e( 'Connected', 'bkx-google-calendar' ); ?></strong>
							<span class="bkx-email"><?php echo esc_html( $connection->google_email ); ?></span>
						</div>
						<div class="bkx-connection-actions">
							<button type="button" class="button" id="bkx-test-connection">
								<?php esc_html_e( 'Test Connection', 'bkx-google-calendar' ); ?>
							</button>
							<button type="button" class="button" id="bkx-sync-now">
								<?php esc_html_e( 'Sync Now', 'bkx-google-calendar' ); ?>
							</button>
							<button type="button" class="button button-link-delete" id="bkx-disconnect">
								<?php esc_html_e( 'Disconnect', 'bkx-google-calendar' ); ?>
							</button>
						</div>
					</div>
				<?php else : ?>
					<div class="bkx-connection-info">
						<span class="dashicons dashicons-warning"></span>
						<strong><?php esc_html_e( 'Not Connected', 'bkx-google-calendar' ); ?></strong>
						<button type="button" class="button button-primary" id="bkx-connect">
							<?php esc_html_e( 'Connect Google Calendar', 'bkx-google-calendar' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'bkx_google_calendar_settings' );
				do_settings_sections( 'bkx-google-calendar' );
				submit_button( __( 'Save Settings', 'bkx-google-calendar' ) );
				?>
			</form>

			<!-- Setup Instructions -->
			<div class="bkx-setup-instructions">
				<h2><?php esc_html_e( 'Setup Instructions', 'bkx-google-calendar' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'Go to the Google Cloud Console', 'bkx-google-calendar' ); ?></li>
					<li><?php esc_html_e( 'Create a new project or select an existing one', 'bkx-google-calendar' ); ?></li>
					<li><?php esc_html_e( 'Enable the Google Calendar API', 'bkx-google-calendar' ); ?></li>
					<li><?php esc_html_e( 'Create OAuth 2.0 credentials (Web application)', 'bkx-google-calendar' ); ?></li>
					<li>
						<?php esc_html_e( 'Add this redirect URI:', 'bkx-google-calendar' ); ?>
						<code><?php echo esc_html( $google_api->get_redirect_uri() ); ?></code>
					</li>
					<li><?php esc_html_e( 'Copy the Client ID and Client Secret above', 'bkx-google-calendar' ); ?></li>
					<li><?php esc_html_e( 'Save settings and click Connect', 'bkx-google-calendar' ); ?></li>
				</ol>
			</div>
		</div>
		<?php
	}

	/**
	 * Render credentials section.
	 *
	 * @return void
	 */
	public function render_credentials_section(): void {
		echo '<p>' . esc_html__( 'Enter your Google API credentials from the Google Cloud Console.', 'bkx-google-calendar' ) . '</p>';
	}

	/**
	 * Render sync section.
	 *
	 * @return void
	 */
	public function render_sync_section(): void {
		echo '<p>' . esc_html__( 'Configure how bookings sync with Google Calendar.', 'bkx-google-calendar' ) . '</p>';
	}

	/**
	 * Render events section.
	 *
	 * @return void
	 */
	public function render_events_section(): void {
		echo '<p>' . esc_html__( 'Customize how booking events appear in Google Calendar.', 'bkx-google-calendar' ) . '</p>';
	}

	/**
	 * Render availability section.
	 *
	 * @return void
	 */
	public function render_availability_section(): void {
		echo '<p>' . esc_html__( 'Control how Google Calendar events affect booking availability.', 'bkx-google-calendar' ) . '</p>';
	}

	/**
	 * Render customer section.
	 *
	 * @return void
	 */
	public function render_customer_section(): void {
		echo '<p>' . esc_html__( 'Options for customers to add bookings to their calendars.', 'bkx-google-calendar' ) . '</p>';
	}

	/**
	 * Render text field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_text_field( array $args ): void {
		$id    = $args['id'];
		$type  = $args['type'] ?? 'text';
		$value = $this->addon->get_setting( $id, '' );

		// Decrypt if sensitive.
		if ( 'password' === $type && ! empty( $value ) ) {
			$value = EncryptionService::decrypt( $value );
		}

		?>
		<input type="<?php echo esc_attr( $type ); ?>" class="regular-text" name="bkx_google_calendar_settings[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $value ); ?>">
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render textarea field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_textarea_field( array $args ): void {
		$id    = $args['id'];
		$value = $this->addon->get_setting( $id, '' );

		?>
		<textarea class="large-text" rows="5" name="bkx_google_calendar_settings[<?php echo esc_attr( $id ); ?>]"><?php echo esc_textarea( $value ); ?></textarea>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_checkbox_field( array $args ): void {
		$id    = $args['id'];
		$value = $this->addon->get_setting( $id, false );

		?>
		<label>
			<input type="checkbox" name="bkx_google_calendar_settings[<?php echo esc_attr( $id ); ?>]" value="1" <?php checked( $value, true ); ?>>
			<?php echo esc_html( $args['description'] ?? '' ); ?>
		</label>
		<?php
	}

	/**
	 * Render select field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_select_field( array $args ): void {
		$id      = $args['id'];
		$value   = $this->addon->get_setting( $id, '' );
		$options = $args['options'] ?? array();

		?>
		<select name="bkx_google_calendar_settings[<?php echo esc_attr( $id ); ?>]">
			<?php foreach ( $options as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render number field.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	public function render_number_field( array $args ): void {
		$id     = $args['id'];
		$value  = $this->addon->get_setting( $id, '' );
		$min    = $args['min'] ?? 0;
		$max    = $args['max'] ?? '';
		$suffix = $args['suffix'] ?? '';

		?>
		<input type="number" class="small-text" name="bkx_google_calendar_settings[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $min ); ?>" <?php echo $max ? 'max="' . esc_attr( $max ) . '"' : ''; ?>>
		<?php if ( $suffix ) : ?>
			<span class="suffix"><?php echo esc_html( $suffix ); ?></span>
		<?php endif; ?>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		// Text fields (encrypt sensitive).
		$sensitive_fields = array( 'client_id', 'client_secret' );
		foreach ( $sensitive_fields as $key ) {
			if ( ! empty( $input[ $key ] ) ) {
				$sanitized[ $key ] = EncryptionService::encrypt( sanitize_text_field( $input[ $key ] ) );
			}
		}

		// Checkboxes.
		$checkboxes = array(
			'sync_enabled',
			'sync_staff_calendars',
			'add_reminders',
			'block_busy_times',
			'customer_add_to_gcal',
			'customer_ical_export',
			'notify_sync_errors',
			'debug_log',
		);
		foreach ( $checkboxes as $key ) {
			$sanitized[ $key ] = isset( $input[ $key ] );
		}

		// Select fields.
		$sanitized['sync_direction'] = sanitize_text_field( $input['sync_direction'] ?? 'two_way' );
		$sanitized['event_color']    = sanitize_text_field( $input['event_color'] ?? '1' );

		// Number fields.
		$sanitized['sync_interval']    = absint( $input['sync_interval'] ?? 15 );
		$sanitized['reminder_minutes'] = absint( $input['reminder_minutes'] ?? 30 );
		$sanitized['buffer_minutes']   = absint( $input['buffer_minutes'] ?? 0 );

		// Text fields.
		$sanitized['event_title_format'] = sanitize_text_field( $input['event_title_format'] ?? '' );
		$sanitized['event_description']  = sanitize_textarea_field( $input['event_description'] ?? '' );
		$sanitized['error_email']        = sanitize_email( $input['error_email'] ?? '' );

		return $sanitized;
	}
}
