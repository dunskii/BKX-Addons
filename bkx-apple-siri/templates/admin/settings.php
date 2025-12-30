<?php
/**
 * Apple Siri settings template.
 *
 * @package BookingX\AppleSiri
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\AppleSiri\AppleSiriAddon::get_instance();
$settings = $addon->get_settings();

// Get available services.
$services = get_posts(
	array(
		'post_type'      => 'bkx_base',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	)
);
?>
<form id="bkx-apple-siri-settings-form" class="bkx-settings-form">
	<?php wp_nonce_field( 'bkx_apple_siri_nonce', 'bkx_apple_siri_nonce' ); ?>

	<!-- Enable/Disable -->
	<div class="bkx-settings-section">
		<h2><?php esc_html_e( 'General Settings', 'bkx-apple-siri' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="enabled"><?php esc_html_e( 'Enable Apple Siri', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="enabled" id="enabled" value="1"
							   <?php checked( ! empty( $settings['enabled'] ) ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Enable Siri voice commands and Apple Shortcuts integration.', 'bkx-apple-siri' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<!-- Apple Developer Credentials -->
	<div class="bkx-settings-section">
		<h2><?php esc_html_e( 'Apple Developer Credentials', 'bkx-apple-siri' ); ?></h2>
		<p class="description">
			<?php
			printf(
				/* translators: %s: Apple Developer URL */
				esc_html__( 'Configure your Apple Developer credentials from %s.', 'bkx-apple-siri' ),
				'<a href="https://developer.apple.com/account" target="_blank">Apple Developer Portal</a>'
			);
			?>
		</p>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="team_id"><?php esc_html_e( 'Team ID', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<input type="text" name="team_id" id="team_id" class="regular-text"
						   value="<?php echo esc_attr( $settings['team_id'] ?? '' ); ?>"
						   placeholder="XXXXXXXXXX">
					<p class="description">
						<?php esc_html_e( 'Your 10-character Apple Developer Team ID.', 'bkx-apple-siri' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="key_id"><?php esc_html_e( 'Key ID', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<input type="text" name="key_id" id="key_id" class="regular-text"
						   value="<?php echo esc_attr( $settings['key_id'] ?? '' ); ?>"
						   placeholder="XXXXXXXXXX">
					<p class="description">
						<?php esc_html_e( 'The Key ID for your Sign in with Apple private key.', 'bkx-apple-siri' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="bundle_identifier"><?php esc_html_e( 'Bundle Identifier', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<input type="text" name="bundle_identifier" id="bundle_identifier" class="regular-text"
						   value="<?php echo esc_attr( $settings['bundle_identifier'] ?? '' ); ?>"
						   placeholder="com.example.bookingapp">
					<p class="description">
						<?php esc_html_e( 'Your iOS app bundle identifier.', 'bkx-apple-siri' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="private_key"><?php esc_html_e( 'Private Key', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<textarea name="private_key" id="private_key" rows="6" class="large-text code"
							  placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"><?php echo esc_textarea( $settings['private_key'] ?? '' ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Your Sign in with Apple private key (.p8 file contents).', 'bkx-apple-siri' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<p>
			<button type="button" class="button" id="bkx-test-connection">
				<?php esc_html_e( 'Test Connection', 'bkx-apple-siri' ); ?>
			</button>
			<span id="bkx-connection-status"></span>
		</p>
	</div>

	<!-- Intent Settings -->
	<div class="bkx-settings-section">
		<h2><?php esc_html_e( 'Siri Intent Settings', 'bkx-apple-siri' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Enabled Intents', 'bkx-apple-siri' ); ?></th>
				<td>
					<?php
					$intent_types = $settings['intent_types'] ?? array( 'book', 'reschedule', 'cancel', 'check_availability' );
					$intents      = array(
						'book'               => __( 'Book Appointment', 'bkx-apple-siri' ),
						'reschedule'         => __( 'Reschedule Appointment', 'bkx-apple-siri' ),
						'cancel'             => __( 'Cancel Appointment', 'bkx-apple-siri' ),
						'check_availability' => __( 'Check Availability', 'bkx-apple-siri' ),
						'upcoming'           => __( 'Upcoming Appointments', 'bkx-apple-siri' ),
					);
					foreach ( $intents as $value => $label ) :
						?>
						<label style="display: block; margin-bottom: 8px;">
							<input type="checkbox" name="intent_types[]" value="<?php echo esc_attr( $value ); ?>"
								   <?php checked( in_array( $value, $intent_types, true ) ); ?>>
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
					<p class="description">
						<?php esc_html_e( 'Select which voice commands are available to users.', 'bkx-apple-siri' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="default_service_id"><?php esc_html_e( 'Default Service', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<select name="default_service_id" id="default_service_id">
						<option value=""><?php esc_html_e( 'Ask user', 'bkx-apple-siri' ); ?></option>
						<?php foreach ( $services as $service ) : ?>
							<option value="<?php echo esc_attr( $service->ID ); ?>"
									<?php selected( ( $settings['default_service_id'] ?? '' ), $service->ID ); ?>>
								<?php echo esc_html( $service->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description">
						<?php esc_html_e( 'Default service for voice bookings. If not set, Siri will ask.', 'bkx-apple-siri' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="require_confirmation"><?php esc_html_e( 'Require Confirmation', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="require_confirmation" id="require_confirmation" value="1"
							   <?php checked( ! empty( $settings['require_confirmation'] ) ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Require verbal confirmation before completing bookings.', 'bkx-apple-siri' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<!-- Voice Phrases -->
	<div class="bkx-settings-section">
		<h2><?php esc_html_e( 'Voice Phrases', 'bkx-apple-siri' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Customize the suggested voice phrases for Siri. Use {business_name} as a placeholder.', 'bkx-apple-siri' ); ?>
		</p>

		<table class="form-table">
			<?php
			$phrases = $settings['voice_phrases'] ?? array();
			$fields  = array(
				'book'               => __( 'Book Phrase', 'bkx-apple-siri' ),
				'reschedule'         => __( 'Reschedule Phrase', 'bkx-apple-siri' ),
				'cancel'             => __( 'Cancel Phrase', 'bkx-apple-siri' ),
				'check_availability' => __( 'Check Availability Phrase', 'bkx-apple-siri' ),
			);
			$defaults = array(
				'book'               => 'Book an appointment at {business_name}',
				'reschedule'         => 'Reschedule my appointment',
				'cancel'             => 'Cancel my appointment',
				'check_availability' => 'Check availability at {business_name}',
			);
			foreach ( $fields as $key => $label ) :
				?>
				<tr>
					<th scope="row">
						<label for="voice_phrases_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
					</th>
					<td>
						<input type="text" name="voice_phrases[<?php echo esc_attr( $key ); ?>]"
							   id="voice_phrases_<?php echo esc_attr( $key ); ?>" class="regular-text"
							   value="<?php echo esc_attr( $phrases[ $key ] ?? $defaults[ $key ] ); ?>">
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div>

	<!-- Shortcuts Settings -->
	<div class="bkx-settings-section">
		<h2><?php esc_html_e( 'Shortcuts Settings', 'bkx-apple-siri' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="shortcuts_enabled"><?php esc_html_e( 'Enable Shortcuts', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="shortcuts_enabled" id="shortcuts_enabled" value="1"
							   <?php checked( $settings['shortcuts_enabled'] ?? true ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Allow users to add booking shortcuts to their Shortcuts app.', 'bkx-apple-siri' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="send_booking_to_reminders"><?php esc_html_e( 'Add to Reminders', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="send_booking_to_reminders" id="send_booking_to_reminders" value="1"
							   <?php checked( ! empty( $settings['send_booking_to_reminders'] ) ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Suggest adding booking confirmations to Apple Reminders.', 'bkx-apple-siri' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<!-- Advanced Settings -->
	<div class="bkx-settings-section">
		<h2><?php esc_html_e( 'Advanced Settings', 'bkx-apple-siri' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="webhook_secret"><?php esc_html_e( 'Webhook Secret', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<input type="text" name="webhook_secret" id="webhook_secret" class="regular-text code"
						   value="<?php echo esc_attr( $settings['webhook_secret'] ?? '' ); ?>" readonly>
					<button type="button" class="button" id="bkx-regenerate-secret">
						<?php esc_html_e( 'Regenerate', 'bkx-apple-siri' ); ?>
					</button>
					<p class="description">
						<?php esc_html_e( 'Secret used for webhook signature verification.', 'bkx-apple-siri' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="log_requests"><?php esc_html_e( 'Debug Logging', 'bkx-apple-siri' ); ?></label>
				</th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="log_requests" id="log_requests" value="1"
							   <?php checked( ! empty( $settings['log_requests'] ) ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Log all Siri requests for debugging. Disable in production.', 'bkx-apple-siri' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<!-- API Endpoints -->
	<div class="bkx-settings-section">
		<h2><?php esc_html_e( 'API Endpoints', 'bkx-apple-siri' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Use these endpoints when configuring your iOS app.', 'bkx-apple-siri' ); ?>
		</p>

		<table class="form-table bkx-endpoints-table">
			<tr>
				<th><?php esc_html_e( 'Intent Handler', 'bkx-apple-siri' ); ?></th>
				<td><code><?php echo esc_url( rest_url( 'bkx-apple-siri/v1/intent' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Shortcuts', 'bkx-apple-siri' ); ?></th>
				<td><code><?php echo esc_url( rest_url( 'bkx-apple-siri/v1/shortcuts' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Availability', 'bkx-apple-siri' ); ?></th>
				<td><code><?php echo esc_url( rest_url( 'bkx-apple-siri/v1/availability' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Book', 'bkx-apple-siri' ); ?></th>
				<td><code><?php echo esc_url( rest_url( 'bkx-apple-siri/v1/book' ) ); ?></code></td>
			</tr>
		</table>
	</div>

	<p class="submit">
		<button type="submit" class="button button-primary" id="bkx-save-settings">
			<?php esc_html_e( 'Save Settings', 'bkx-apple-siri' ); ?>
		</button>
		<span class="spinner"></span>
		<span id="bkx-save-status"></span>
	</p>
</form>
