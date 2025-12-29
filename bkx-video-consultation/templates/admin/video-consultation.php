<?php
/**
 * Video Consultation Admin Template.
 *
 * @package BookingX\VideoConsultation
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'rooms';
$tabs       = array(
	'rooms'      => __( 'Video Rooms', 'bkx-video-consultation' ),
	'recordings' => __( 'Recordings', 'bkx-video-consultation' ),
	'settings'   => __( 'Settings', 'bkx-video-consultation' ),
);

$settings = get_option( 'bkx_video_consultation_settings', array() );
?>

<div class="wrap bkx-video-consultation">
	<h1><?php esc_html_e( 'Video Consultation', 'bkx-video-consultation' ); ?></h1>

	<!-- Tabs Navigation -->
	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id ) ); ?>"
			   class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-video-content">
		<?php if ( 'rooms' === $active_tab ) : ?>
			<!-- Video Rooms List -->
			<div class="bkx-rooms-section">
				<div class="bkx-rooms-filters">
					<select id="bkx-room-status-filter">
						<option value=""><?php esc_html_e( 'All Statuses', 'bkx-video-consultation' ); ?></option>
						<option value="scheduled"><?php esc_html_e( 'Scheduled', 'bkx-video-consultation' ); ?></option>
						<option value="active"><?php esc_html_e( 'Active', 'bkx-video-consultation' ); ?></option>
						<option value="ended"><?php esc_html_e( 'Ended', 'bkx-video-consultation' ); ?></option>
						<option value="cancelled"><?php esc_html_e( 'Cancelled', 'bkx-video-consultation' ); ?></option>
					</select>
					<select id="bkx-room-provider-filter">
						<option value=""><?php esc_html_e( 'All Providers', 'bkx-video-consultation' ); ?></option>
						<option value="webrtc"><?php esc_html_e( 'WebRTC', 'bkx-video-consultation' ); ?></option>
						<option value="zoom"><?php esc_html_e( 'Zoom', 'bkx-video-consultation' ); ?></option>
						<option value="google_meet"><?php esc_html_e( 'Google Meet', 'bkx-video-consultation' ); ?></option>
					</select>
					<button type="button" class="button" id="bkx-refresh-rooms">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Refresh', 'bkx-video-consultation' ); ?>
					</button>
				</div>

				<table class="wp-list-table widefat fixed striped" id="bkx-rooms-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Room ID', 'bkx-video-consultation' ); ?></th>
							<th><?php esc_html_e( 'Booking', 'bkx-video-consultation' ); ?></th>
							<th><?php esc_html_e( 'Provider', 'bkx-video-consultation' ); ?></th>
							<th><?php esc_html_e( 'Scheduled', 'bkx-video-consultation' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bkx-video-consultation' ); ?></th>
							<th><?php esc_html_e( 'Duration', 'bkx-video-consultation' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bkx-video-consultation' ); ?></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>

		<?php elseif ( 'recordings' === $active_tab ) : ?>
			<!-- Recordings List -->
			<div class="bkx-recordings-section">
				<?php if ( empty( $settings['enable_recording'] ) ) : ?>
					<div class="notice notice-warning">
						<p><?php esc_html_e( 'Recording is currently disabled. Enable it in Settings to start recording consultations.', 'bkx-video-consultation' ); ?></p>
					</div>
				<?php endif; ?>

				<div class="bkx-storage-info">
					<span class="dashicons dashicons-cloud"></span>
					<span id="bkx-storage-used">--</span>
					<?php esc_html_e( 'storage used', 'bkx-video-consultation' ); ?>
				</div>

				<table class="wp-list-table widefat fixed striped" id="bkx-recordings-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Recording', 'bkx-video-consultation' ); ?></th>
							<th><?php esc_html_e( 'Booking', 'bkx-video-consultation' ); ?></th>
							<th><?php esc_html_e( 'Duration', 'bkx-video-consultation' ); ?></th>
							<th><?php esc_html_e( 'Size', 'bkx-video-consultation' ); ?></th>
							<th><?php esc_html_e( 'Created', 'bkx-video-consultation' ); ?></th>
							<th><?php esc_html_e( 'Expires', 'bkx-video-consultation' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bkx-video-consultation' ); ?></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>

		<?php elseif ( 'settings' === $active_tab ) : ?>
			<!-- Settings Form -->
			<form method="post" action="" id="bkx-video-settings-form">
				<?php wp_nonce_field( 'bkx_video_settings', 'bkx_video_nonce' ); ?>

				<h2><?php esc_html_e( 'General Settings', 'bkx-video-consultation' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Video Consultation', 'bkx-video-consultation' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ?? true ); ?>>
								<?php esc_html_e( 'Enable video consultation for bookings', 'bkx-video-consultation' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default Provider', 'bkx-video-consultation' ); ?></th>
						<td>
							<select name="default_provider">
								<option value="webrtc" <?php selected( $settings['default_provider'] ?? 'webrtc', 'webrtc' ); ?>><?php esc_html_e( 'WebRTC (Built-in)', 'bkx-video-consultation' ); ?></option>
								<option value="zoom" <?php selected( $settings['default_provider'] ?? '', 'zoom' ); ?>><?php esc_html_e( 'Zoom', 'bkx-video-consultation' ); ?></option>
								<option value="google_meet" <?php selected( $settings['default_provider'] ?? '', 'google_meet' ); ?>><?php esc_html_e( 'Google Meet', 'bkx-video-consultation' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Max Participants', 'bkx-video-consultation' ); ?></th>
						<td>
							<input type="number" name="max_participants" value="<?php echo esc_attr( $settings['max_participants'] ?? 2 ); ?>" min="2" max="100">
							<p class="description"><?php esc_html_e( 'Maximum participants per video room.', 'bkx-video-consultation' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-End After', 'bkx-video-consultation' ); ?></th>
						<td>
							<input type="number" name="auto_end_after_minutes" value="<?php echo esc_attr( $settings['auto_end_after_minutes'] ?? 60 ); ?>" min="15" max="480">
							<?php esc_html_e( 'minutes', 'bkx-video-consultation' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Reminder Before', 'bkx-video-consultation' ); ?></th>
						<td>
							<input type="number" name="reminder_before_minutes" value="<?php echo esc_attr( $settings['reminder_before_minutes'] ?? 15 ); ?>" min="5" max="60">
							<?php esc_html_e( 'minutes', 'bkx-video-consultation' ); ?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Features', 'bkx-video-consultation' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Waiting Room', 'bkx-video-consultation' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_waiting_room" value="1" <?php checked( $settings['enable_waiting_room'] ?? true ); ?>>
								<?php esc_html_e( 'Require host to admit participants', 'bkx-video-consultation' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Screen Sharing', 'bkx-video-consultation' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_screen_share" value="1" <?php checked( $settings['enable_screen_share'] ?? true ); ?>>
								<?php esc_html_e( 'Allow screen sharing', 'bkx-video-consultation' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Chat', 'bkx-video-consultation' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_chat" value="1" <?php checked( $settings['enable_chat'] ?? true ); ?>>
								<?php esc_html_e( 'Enable in-call chat', 'bkx-video-consultation' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Recording', 'bkx-video-consultation' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_recording" value="1" <?php checked( $settings['enable_recording'] ?? false ); ?>>
								<?php esc_html_e( 'Enable session recording', 'bkx-video-consultation' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Recording Retention', 'bkx-video-consultation' ); ?></th>
						<td>
							<input type="number" name="recording_retention_days" value="<?php echo esc_attr( $settings['recording_retention_days'] ?? 30 ); ?>" min="7" max="365">
							<?php esc_html_e( 'days', 'bkx-video-consultation' ); ?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Zoom Integration', 'bkx-video-consultation' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Zoom', 'bkx-video-consultation' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="zoom_enabled" value="1" <?php checked( $settings['zoom_enabled'] ?? false ); ?>>
								<?php esc_html_e( 'Use Zoom for video consultations', 'bkx-video-consultation' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Account ID', 'bkx-video-consultation' ); ?></th>
						<td>
							<input type="text" name="zoom_account_id" value="<?php echo esc_attr( $settings['zoom_account_id'] ?? '' ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Client ID', 'bkx-video-consultation' ); ?></th>
						<td>
							<input type="text" name="zoom_api_key" value="<?php echo esc_attr( $settings['zoom_api_key'] ?? '' ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Client Secret', 'bkx-video-consultation' ); ?></th>
						<td>
							<input type="password" name="zoom_api_secret" value="<?php echo esc_attr( $settings['zoom_api_secret'] ?? '' ); ?>" class="regular-text">
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Google Meet Integration', 'bkx-video-consultation' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Google Meet', 'bkx-video-consultation' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="google_meet_enabled" value="1" <?php checked( $settings['google_meet_enabled'] ?? false ); ?>>
								<?php esc_html_e( 'Use Google Meet for video consultations', 'bkx-video-consultation' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Client ID', 'bkx-video-consultation' ); ?></th>
						<td>
							<input type="text" name="google_client_id" value="<?php echo esc_attr( $settings['google_client_id'] ?? '' ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Client Secret', 'bkx-video-consultation' ); ?></th>
						<td>
							<input type="password" name="google_client_secret" value="<?php echo esc_attr( $settings['google_client_secret'] ?? '' ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Connection Status', 'bkx-video-consultation' ); ?></th>
						<td>
							<?php
							$google_provider = new \BookingX\VideoConsultation\Services\GoogleMeetProvider( $settings );
							if ( $google_provider->is_connected() ) :
								?>
								<span class="bkx-status-connected"><?php esc_html_e( 'Connected', 'bkx-video-consultation' ); ?></span>
								<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'action', 'google_disconnect' ), 'google_disconnect' ) ); ?>" class="button button-secondary">
									<?php esc_html_e( 'Disconnect', 'bkx-video-consultation' ); ?>
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( $google_provider->get_auth_url() ); ?>" class="button button-primary">
									<?php esc_html_e( 'Connect Google Account', 'bkx-video-consultation' ); ?>
								</a>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'WebRTC Settings', 'bkx-video-consultation' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'STUN Servers', 'bkx-video-consultation' ); ?></th>
						<td>
							<input type="text" name="webrtc_stun_servers" value="<?php echo esc_attr( $settings['webrtc_stun_servers'] ?? 'stun:stun.l.google.com:19302' ); ?>" class="large-text">
							<p class="description"><?php esc_html_e( 'Comma-separated list of STUN servers.', 'bkx-video-consultation' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'TURN Server', 'bkx-video-consultation' ); ?></th>
						<td>
							<input type="text" name="webrtc_turn_server" value="<?php echo esc_attr( $settings['webrtc_turn_server'] ?? '' ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Optional TURN server for NAT traversal.', 'bkx-video-consultation' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'TURN Username', 'bkx-video-consultation' ); ?></th>
						<td>
							<input type="text" name="webrtc_turn_username" value="<?php echo esc_attr( $settings['webrtc_turn_username'] ?? '' ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'TURN Credential', 'bkx-video-consultation' ); ?></th>
						<td>
							<input type="password" name="webrtc_turn_credential" value="<?php echo esc_attr( $settings['webrtc_turn_credential'] ?? '' ); ?>" class="regular-text">
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Uninstall', 'bkx-video-consultation' ); ?></h2>
				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Delete Data', 'bkx-video-consultation' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $settings['delete_data_on_uninstall'] ?? false ); ?>>
								<?php esc_html_e( 'Delete all data when plugin is uninstalled', 'bkx-video-consultation' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		<?php endif; ?>
	</div>
</div>
