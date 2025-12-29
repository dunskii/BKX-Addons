<?php
/**
 * Admin page template for Google Assistant settings.
 *
 * @package BookingX\GoogleAssistant
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_google_assistant_settings', array() );
$tab      = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification
?>

<div class="wrap bkx-google-assistant-wrap">
	<h1>
		<span class="dashicons dashicons-microphone"></span>
		<?php esc_html_e( 'Google Assistant Integration', 'bkx-google-assistant' ); ?>
	</h1>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-google-assistant&tab=settings' ) ); ?>"
		   class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'bkx-google-assistant' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-google-assistant&tab=setup' ) ); ?>"
		   class="nav-tab <?php echo 'setup' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Setup Guide', 'bkx-google-assistant' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-google-assistant&tab=logs' ) ); ?>"
		   class="nav-tab <?php echo 'logs' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Logs', 'bkx-google-assistant' ); ?>
		</a>
	</nav>

	<div class="bkx-assistant-content">
		<?php if ( 'settings' === $tab ) : ?>
			<!-- Settings Tab -->
			<form id="bkx-assistant-settings-form" class="bkx-settings-form">
				<?php wp_nonce_field( 'bkx_google_assistant', 'bkx_nonce' ); ?>

				<div class="bkx-card">
					<h2><?php esc_html_e( 'General Settings', 'bkx-google-assistant' ); ?></h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="enabled"><?php esc_html_e( 'Enable Integration', 'bkx-google-assistant' ); ?></label>
							</th>
							<td>
								<label class="bkx-toggle">
									<input type="checkbox" id="enabled" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Enable or disable the Google Assistant integration.', 'bkx-google-assistant' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dev_mode"><?php esc_html_e( 'Development Mode', 'bkx-google-assistant' ); ?></label>
							</th>
							<td>
								<label class="bkx-toggle">
									<input type="checkbox" id="dev_mode" name="dev_mode" value="1" <?php checked( ! empty( $settings['dev_mode'] ) ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Skip request verification for testing. Disable in production.', 'bkx-google-assistant' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="bkx-card">
					<h2><?php esc_html_e( 'Google Cloud Configuration', 'bkx-google-assistant' ); ?></h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="project_id"><?php esc_html_e( 'Project ID', 'bkx-google-assistant' ); ?></label>
							</th>
							<td>
								<input type="text" id="project_id" name="project_id" class="regular-text"
									   value="<?php echo esc_attr( $settings['project_id'] ?? '' ); ?>"
									   placeholder="your-project-id">
								<p class="description"><?php esc_html_e( 'Your Google Cloud project ID from the Actions Console.', 'bkx-google-assistant' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="client_id"><?php esc_html_e( 'OAuth Client ID', 'bkx-google-assistant' ); ?></label>
							</th>
							<td>
								<input type="text" id="client_id" name="client_id" class="large-text"
									   value="<?php echo esc_attr( $settings['client_id'] ?? '' ); ?>">
								<p class="description"><?php esc_html_e( 'OAuth 2.0 Client ID for account linking.', 'bkx-google-assistant' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="client_secret"><?php esc_html_e( 'OAuth Client Secret', 'bkx-google-assistant' ); ?></label>
							</th>
							<td>
								<input type="password" id="client_secret" name="client_secret" class="large-text"
									   value="<?php echo esc_attr( $settings['client_secret'] ?? '' ); ?>">
								<p class="description"><?php esc_html_e( 'OAuth 2.0 Client Secret for account linking.', 'bkx-google-assistant' ); ?></p>
							</td>
						</tr>
					</table>

					<p>
						<button type="button" id="bkx-test-connection" class="button">
							<?php esc_html_e( 'Test Connection', 'bkx-google-assistant' ); ?>
						</button>
						<span id="connection-status"></span>
					</p>
				</div>

				<div class="bkx-card">
					<h2><?php esc_html_e( 'Voice Experience', 'bkx-google-assistant' ); ?></h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="invocation_name"><?php esc_html_e( 'Invocation Name', 'bkx-google-assistant' ); ?></label>
							</th>
							<td>
								<input type="text" id="invocation_name" name="invocation_name" class="regular-text"
									   value="<?php echo esc_attr( $settings['invocation_name'] ?? '' ); ?>"
									   placeholder="book appointment">
								<p class="description">
									<?php esc_html_e( 'Users will say "Hey Google, talk to [invocation name]" to start.', 'bkx-google-assistant' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="welcome_message"><?php esc_html_e( 'Welcome Message', 'bkx-google-assistant' ); ?></label>
							</th>
							<td>
								<textarea id="welcome_message" name="welcome_message" class="large-text" rows="3"><?php
									echo esc_textarea( $settings['welcome_message'] ?? '' );
								?></textarea>
								<p class="description">
									<?php esc_html_e( 'Custom greeting when users start the conversation. Leave empty for default.', 'bkx-google-assistant' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="require_linking"><?php esc_html_e( 'Require Account Linking', 'bkx-google-assistant' ); ?></label>
							</th>
							<td>
								<label class="bkx-toggle">
									<input type="checkbox" id="require_linking" name="require_linking" value="1" <?php checked( ! empty( $settings['require_linking'] ) ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Require users to link their account before booking.', 'bkx-google-assistant' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="bkx-card">
					<h2><?php esc_html_e( 'Webhook Configuration', 'bkx-google-assistant' ); ?></h2>

					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Webhook URL', 'bkx-google-assistant' ); ?></th>
							<td>
								<code id="webhook-url"><?php echo esc_url( rest_url( 'bkx-assistant/v1/webhook' ) ); ?></code>
								<button type="button" class="button button-small bkx-copy-btn" data-copy="webhook-url">
									<?php esc_html_e( 'Copy', 'bkx-google-assistant' ); ?>
								</button>
								<p class="description"><?php esc_html_e( 'Configure this URL as the fulfillment webhook in Dialogflow or Actions Console.', 'bkx-google-assistant' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Authorization Endpoint', 'bkx-google-assistant' ); ?></th>
							<td>
								<code id="auth-url"><?php echo esc_url( rest_url( 'bkx-assistant/v1/auth' ) ); ?></code>
								<button type="button" class="button button-small bkx-copy-btn" data-copy="auth-url">
									<?php esc_html_e( 'Copy', 'bkx-google-assistant' ); ?>
								</button>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Token Endpoint', 'bkx-google-assistant' ); ?></th>
							<td>
								<code id="token-url"><?php echo esc_url( rest_url( 'bkx-assistant/v1/token' ) ); ?></code>
								<button type="button" class="button button-small bkx-copy-btn" data-copy="token-url">
									<?php esc_html_e( 'Copy', 'bkx-google-assistant' ); ?>
								</button>
							</td>
						</tr>
					</table>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Settings', 'bkx-google-assistant' ); ?>
					</button>
					<button type="button" id="bkx-export-package" class="button">
						<?php esc_html_e( 'Export Action Package', 'bkx-google-assistant' ); ?>
					</button>
				</p>
			</form>

		<?php elseif ( 'setup' === $tab ) : ?>
			<!-- Setup Guide Tab -->
			<div class="bkx-card">
				<h2><?php esc_html_e( 'Setup Guide', 'bkx-google-assistant' ); ?></h2>

				<div class="bkx-setup-steps">
					<div class="bkx-step">
						<div class="step-number">1</div>
						<div class="step-content">
							<h3><?php esc_html_e( 'Create Google Cloud Project', 'bkx-google-assistant' ); ?></h3>
							<p><?php esc_html_e( 'Go to the Google Cloud Console and create a new project or select an existing one.', 'bkx-google-assistant' ); ?></p>
							<a href="https://console.cloud.google.com/projectcreate" target="_blank" class="button">
								<?php esc_html_e( 'Open Cloud Console', 'bkx-google-assistant' ); ?>
							</a>
						</div>
					</div>

					<div class="bkx-step">
						<div class="step-number">2</div>
						<div class="step-content">
							<h3><?php esc_html_e( 'Create Actions Project', 'bkx-google-assistant' ); ?></h3>
							<p><?php esc_html_e( 'Go to the Actions Console and create a new project linked to your Cloud project.', 'bkx-google-assistant' ); ?></p>
							<a href="https://console.actions.google.com/" target="_blank" class="button">
								<?php esc_html_e( 'Open Actions Console', 'bkx-google-assistant' ); ?>
							</a>
						</div>
					</div>

					<div class="bkx-step">
						<div class="step-number">3</div>
						<div class="step-content">
							<h3><?php esc_html_e( 'Configure Dialogflow', 'bkx-google-assistant' ); ?></h3>
							<p><?php esc_html_e( 'Enable Dialogflow integration in Actions Console. Create intents for booking, availability, and services.', 'bkx-google-assistant' ); ?></p>
							<ul>
								<li><strong>welcome</strong> - <?php esc_html_e( 'Welcome intent', 'bkx-google-assistant' ); ?></li>
								<li><strong>list_services</strong> - <?php esc_html_e( 'List available services', 'bkx-google-assistant' ); ?></li>
								<li><strong>check_availability</strong> - <?php esc_html_e( 'Check available times', 'bkx-google-assistant' ); ?></li>
								<li><strong>book_appointment</strong> - <?php esc_html_e( 'Start booking flow', 'bkx-google-assistant' ); ?></li>
								<li><strong>select_service</strong> - <?php esc_html_e( 'Select a service', 'bkx-google-assistant' ); ?></li>
								<li><strong>select_date</strong> - <?php esc_html_e( 'Select a date', 'bkx-google-assistant' ); ?></li>
								<li><strong>select_time</strong> - <?php esc_html_e( 'Select a time', 'bkx-google-assistant' ); ?></li>
								<li><strong>confirm_booking</strong> - <?php esc_html_e( 'Confirm the booking', 'bkx-google-assistant' ); ?></li>
								<li><strong>cancel_booking</strong> - <?php esc_html_e( 'Cancel a booking', 'bkx-google-assistant' ); ?></li>
								<li><strong>my_bookings</strong> - <?php esc_html_e( 'View user bookings', 'bkx-google-assistant' ); ?></li>
							</ul>
						</div>
					</div>

					<div class="bkx-step">
						<div class="step-number">4</div>
						<div class="step-content">
							<h3><?php esc_html_e( 'Set Webhook URL', 'bkx-google-assistant' ); ?></h3>
							<p><?php esc_html_e( 'In Dialogflow Fulfillment settings, enable webhook and enter:', 'bkx-google-assistant' ); ?></p>
							<code><?php echo esc_url( rest_url( 'bkx-assistant/v1/webhook' ) ); ?></code>
						</div>
					</div>

					<div class="bkx-step">
						<div class="step-number">5</div>
						<div class="step-content">
							<h3><?php esc_html_e( 'Configure Account Linking (Optional)', 'bkx-google-assistant' ); ?></h3>
							<p><?php esc_html_e( 'To enable personalized bookings, configure OAuth 2.0 account linking:', 'bkx-google-assistant' ); ?></p>
							<ol>
								<li><?php esc_html_e( 'Create OAuth credentials in Google Cloud Console', 'bkx-google-assistant' ); ?></li>
								<li><?php esc_html_e( 'Enter Client ID and Secret in Settings tab above', 'bkx-google-assistant' ); ?></li>
								<li><?php esc_html_e( 'Configure Authorization URL and Token URL in Actions Console', 'bkx-google-assistant' ); ?></li>
							</ol>
						</div>
					</div>

					<div class="bkx-step">
						<div class="step-number">6</div>
						<div class="step-content">
							<h3><?php esc_html_e( 'Test Your Action', 'bkx-google-assistant' ); ?></h3>
							<p><?php esc_html_e( 'Use the Actions Console simulator to test your integration before publishing.', 'bkx-google-assistant' ); ?></p>
							<p><?php esc_html_e( 'Enable "Development Mode" in Settings to bypass request verification during testing.', 'bkx-google-assistant' ); ?></p>
						</div>
					</div>
				</div>
			</div>

		<?php elseif ( 'logs' === $tab ) : ?>
			<!-- Logs Tab -->
			<div class="bkx-card">
				<h2><?php esc_html_e( 'Voice Interaction Logs', 'bkx-google-assistant' ); ?></h2>

				<div class="bkx-stats-row">
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-total">-</span>
						<span class="stat-label"><?php esc_html_e( 'Total Requests', 'bkx-google-assistant' ); ?></span>
					</div>
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-successful">-</span>
						<span class="stat-label"><?php esc_html_e( 'Successful', 'bkx-google-assistant' ); ?></span>
					</div>
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-bookings">-</span>
						<span class="stat-label"><?php esc_html_e( 'Bookings Created', 'bkx-google-assistant' ); ?></span>
					</div>
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-accounts">-</span>
						<span class="stat-label"><?php esc_html_e( 'Linked Accounts', 'bkx-google-assistant' ); ?></span>
					</div>
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-today">-</span>
						<span class="stat-label"><?php esc_html_e( 'Today', 'bkx-google-assistant' ); ?></span>
					</div>
				</div>

				<?php
				global $wpdb;
				$logs = $wpdb->get_results(
					"SELECT * FROM {$wpdb->prefix}bkx_assistant_logs ORDER BY created_at DESC LIMIT 50" // phpcs:ignore
				);
				?>

				<?php if ( ! empty( $logs ) ) : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Time', 'bkx-google-assistant' ); ?></th>
								<th><?php esc_html_e( 'Intent', 'bkx-google-assistant' ); ?></th>
								<th><?php esc_html_e( 'User Query', 'bkx-google-assistant' ); ?></th>
								<th><?php esc_html_e( 'Response', 'bkx-google-assistant' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bkx-google-assistant' ); ?></th>
								<th><?php esc_html_e( 'Time (ms)', 'bkx-google-assistant' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $log ) : ?>
								<tr>
									<td>
										<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->created_at ) ) ); ?>
									</td>
									<td><code><?php echo esc_html( $log->intent ); ?></code></td>
									<td><?php echo esc_html( wp_trim_words( $log->user_query, 10 ) ); ?></td>
									<td><?php echo esc_html( wp_trim_words( $log->response_text, 15 ) ); ?></td>
									<td>
										<span class="bkx-status bkx-status-<?php echo esc_attr( $log->status ); ?>">
											<?php echo esc_html( ucfirst( $log->status ) ); ?>
										</span>
										<?php if ( $log->booking_id ) : ?>
											<a href="<?php echo esc_url( get_edit_post_link( $log->booking_id ) ); ?>">
												#<?php echo esc_html( $log->booking_id ); ?>
											</a>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $log->processing_time ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="bkx-empty-state">
						<?php esc_html_e( 'No voice interactions logged yet. Test your Google Assistant integration to see logs here.', 'bkx-google-assistant' ); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
