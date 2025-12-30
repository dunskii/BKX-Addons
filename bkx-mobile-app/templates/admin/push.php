<?php
/**
 * Push Notifications Settings.
 *
 * @package BookingX\MobileApp
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\MobileApp\MobileAppAddon::get_instance();
$settings = get_option( 'bkx_mobile_app_settings', array() );

// Handle form submission.
if ( isset( $_POST['bkx_save_push_settings'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bkx_save_push_settings' ) ) {
	$settings['fcm_server_key']         = sanitize_text_field( wp_unslash( $_POST['fcm_server_key'] ?? '' ) );
	$settings['fcm_sender_id']          = sanitize_text_field( wp_unslash( $_POST['fcm_sender_id'] ?? '' ) );
	$settings['apns_bundle_id']         = sanitize_text_field( wp_unslash( $_POST['apns_bundle_id'] ?? '' ) );
	$settings['apns_team_id']           = sanitize_text_field( wp_unslash( $_POST['apns_team_id'] ?? '' ) );
	$settings['apns_key_id']            = sanitize_text_field( wp_unslash( $_POST['apns_key_id'] ?? '' ) );
	$settings['apns_environment']       = sanitize_text_field( wp_unslash( $_POST['apns_environment'] ?? 'sandbox' ) );
	$settings['push_booking_created']   = isset( $_POST['push_booking_created'] );
	$settings['push_booking_confirmed'] = isset( $_POST['push_booking_confirmed'] );
	$settings['push_booking_reminder']  = isset( $_POST['push_booking_reminder'] );
	$settings['push_booking_cancelled'] = isset( $_POST['push_booking_cancelled'] );
	$settings['reminder_hours']         = absint( $_POST['reminder_hours'] ?? 24 );

	// Handle APNS private key file upload.
	if ( ! empty( $_FILES['apns_private_key']['tmp_name'] ) ) {
		$upload = wp_handle_upload( $_FILES['apns_private_key'], array( 'test_form' => false ) );
		if ( ! isset( $upload['error'] ) ) {
			$settings['apns_private_key_path'] = $upload['file'];
		}
	}

	update_option( 'bkx_mobile_app_settings', $settings );

	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Push notification settings saved.', 'bkx-mobile-app' ) . '</p></div>';
}

// Get device manager for stats.
$device_manager = $addon->get_service( 'device_manager' );
$stats          = $device_manager->get_statistics();
?>

<div class="bkx-push-settings">
	<form method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'bkx_save_push_settings' ); ?>

		<!-- Push Stats -->
		<div class="bkx-push-stats">
			<div class="bkx-stat-card">
				<span class="bkx-stat-value"><?php echo esc_html( number_format( $stats['push_enabled'] ) ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Devices with Push Enabled', 'bkx-mobile-app' ); ?></span>
			</div>
		</div>

		<!-- Firebase Cloud Messaging (Android) -->
		<div class="bkx-card">
			<h3>
				<span class="dashicons dashicons-cloud"></span>
				<?php esc_html_e( 'Firebase Cloud Messaging (Android)', 'bkx-mobile-app' ); ?>
			</h3>
			<p class="description">
				<?php esc_html_e( 'Configure Firebase Cloud Messaging to send push notifications to Android devices.', 'bkx-mobile-app' ); ?>
				<a href="https://console.firebase.google.com/" target="_blank"><?php esc_html_e( 'Get credentials from Firebase Console', 'bkx-mobile-app' ); ?></a>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="fcm_server_key"><?php esc_html_e( 'Server Key', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<input type="password" id="fcm_server_key" name="fcm_server_key" class="regular-text"
							   value="<?php echo esc_attr( $settings['fcm_server_key'] ?? '' ); ?>">
						<p class="description">
							<?php esc_html_e( 'Found in Firebase Console > Project Settings > Cloud Messaging.', 'bkx-mobile-app' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="fcm_sender_id"><?php esc_html_e( 'Sender ID', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<input type="text" id="fcm_sender_id" name="fcm_sender_id" class="regular-text"
							   value="<?php echo esc_attr( $settings['fcm_sender_id'] ?? '' ); ?>">
					</td>
				</tr>
			</table>
		</div>

		<!-- Apple Push Notification Service (iOS) -->
		<div class="bkx-card">
			<h3>
				<span class="dashicons dashicons-smartphone"></span>
				<?php esc_html_e( 'Apple Push Notification Service (iOS)', 'bkx-mobile-app' ); ?>
			</h3>
			<p class="description">
				<?php esc_html_e( 'Configure APNs to send push notifications to iOS devices.', 'bkx-mobile-app' ); ?>
				<a href="https://developer.apple.com/account/resources/authkeys/list" target="_blank"><?php esc_html_e( 'Get credentials from Apple Developer Portal', 'bkx-mobile-app' ); ?></a>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="apns_bundle_id"><?php esc_html_e( 'Bundle ID', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<input type="text" id="apns_bundle_id" name="apns_bundle_id" class="regular-text"
							   value="<?php echo esc_attr( $settings['apns_bundle_id'] ?? '' ); ?>"
							   placeholder="com.example.bookingapp">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="apns_team_id"><?php esc_html_e( 'Team ID', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<input type="text" id="apns_team_id" name="apns_team_id" class="regular-text"
							   value="<?php echo esc_attr( $settings['apns_team_id'] ?? '' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="apns_key_id"><?php esc_html_e( 'Key ID', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<input type="text" id="apns_key_id" name="apns_key_id" class="regular-text"
							   value="<?php echo esc_attr( $settings['apns_key_id'] ?? '' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="apns_private_key"><?php esc_html_e( 'Private Key (.p8)', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<input type="file" id="apns_private_key" name="apns_private_key" accept=".p8">
						<?php if ( ! empty( $settings['apns_private_key_path'] ) ) : ?>
							<p class="description bkx-key-uploaded">
								<span class="dashicons dashicons-yes-alt"></span>
								<?php esc_html_e( 'Private key uploaded', 'bkx-mobile-app' ); ?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="apns_environment"><?php esc_html_e( 'Environment', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<select id="apns_environment" name="apns_environment">
							<option value="sandbox" <?php selected( $settings['apns_environment'] ?? '', 'sandbox' ); ?>>
								<?php esc_html_e( 'Sandbox (Development)', 'bkx-mobile-app' ); ?>
							</option>
							<option value="production" <?php selected( $settings['apns_environment'] ?? '', 'production' ); ?>>
								<?php esc_html_e( 'Production', 'bkx-mobile-app' ); ?>
							</option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<!-- Notification Triggers -->
		<div class="bkx-card">
			<h3>
				<span class="dashicons dashicons-megaphone"></span>
				<?php esc_html_e( 'Notification Triggers', 'bkx-mobile-app' ); ?>
			</h3>
			<p class="description">
				<?php esc_html_e( 'Choose which events trigger push notifications to customers.', 'bkx-mobile-app' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Booking Events', 'bkx-mobile-app' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="push_booking_created" value="1"
									   <?php checked( $settings['push_booking_created'] ?? true ); ?>>
								<?php esc_html_e( 'New booking created', 'bkx-mobile-app' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="push_booking_confirmed" value="1"
									   <?php checked( $settings['push_booking_confirmed'] ?? true ); ?>>
								<?php esc_html_e( 'Booking confirmed', 'bkx-mobile-app' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="push_booking_cancelled" value="1"
									   <?php checked( $settings['push_booking_cancelled'] ?? true ); ?>>
								<?php esc_html_e( 'Booking cancelled', 'bkx-mobile-app' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="push_booking_reminder" value="1"
									   <?php checked( $settings['push_booking_reminder'] ?? true ); ?>>
								<?php esc_html_e( 'Appointment reminder', 'bkx-mobile-app' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="reminder_hours"><?php esc_html_e( 'Reminder Time', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<select id="reminder_hours" name="reminder_hours">
							<option value="1" <?php selected( $settings['reminder_hours'] ?? 24, 1 ); ?>>
								<?php esc_html_e( '1 hour before', 'bkx-mobile-app' ); ?>
							</option>
							<option value="2" <?php selected( $settings['reminder_hours'] ?? 24, 2 ); ?>>
								<?php esc_html_e( '2 hours before', 'bkx-mobile-app' ); ?>
							</option>
							<option value="4" <?php selected( $settings['reminder_hours'] ?? 24, 4 ); ?>>
								<?php esc_html_e( '4 hours before', 'bkx-mobile-app' ); ?>
							</option>
							<option value="12" <?php selected( $settings['reminder_hours'] ?? 24, 12 ); ?>>
								<?php esc_html_e( '12 hours before', 'bkx-mobile-app' ); ?>
							</option>
							<option value="24" <?php selected( $settings['reminder_hours'] ?? 24, 24 ); ?>>
								<?php esc_html_e( '24 hours before', 'bkx-mobile-app' ); ?>
							</option>
							<option value="48" <?php selected( $settings['reminder_hours'] ?? 24, 48 ); ?>>
								<?php esc_html_e( '48 hours before', 'bkx-mobile-app' ); ?>
							</option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<!-- Test Push -->
		<div class="bkx-card">
			<h3>
				<span class="dashicons dashicons-bell"></span>
				<?php esc_html_e( 'Test Push Notification', 'bkx-mobile-app' ); ?>
			</h3>
			<p class="description">
				<?php esc_html_e( 'Send a test notification to verify your configuration.', 'bkx-mobile-app' ); ?>
			</p>
			<button type="button" class="button" id="bkx-test-push" disabled>
				<?php esc_html_e( 'Send Test Notification', 'bkx-mobile-app' ); ?>
			</button>
			<span class="bkx-test-result"></span>
		</div>

		<p class="submit">
			<button type="submit" name="bkx_save_push_settings" class="button button-primary">
				<?php esc_html_e( 'Save Push Settings', 'bkx-mobile-app' ); ?>
			</button>
		</p>
	</form>
</div>
