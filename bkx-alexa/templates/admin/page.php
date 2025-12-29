<?php
/**
 * Admin page template for Amazon Alexa settings.
 *
 * @package BookingX\Alexa
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_alexa_settings', array() );
$tab      = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification
?>

<div class="wrap bkx-alexa-wrap">
	<h1>
		<span class="dashicons dashicons-format-audio"></span>
		<?php esc_html_e( 'Amazon Alexa Integration', 'bkx-alexa' ); ?>
	</h1>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-alexa&tab=settings' ) ); ?>"
		   class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'bkx-alexa' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-alexa&tab=setup' ) ); ?>"
		   class="nav-tab <?php echo 'setup' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Setup Guide', 'bkx-alexa' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-alexa&tab=logs' ) ); ?>"
		   class="nav-tab <?php echo 'logs' === $tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Logs', 'bkx-alexa' ); ?>
		</a>
	</nav>

	<div class="bkx-alexa-content">
		<?php if ( 'settings' === $tab ) : ?>
			<!-- Settings Tab -->
			<form id="bkx-alexa-settings-form" class="bkx-settings-form">
				<?php wp_nonce_field( 'bkx_alexa', 'bkx_nonce' ); ?>

				<div class="bkx-card">
					<h2><?php esc_html_e( 'General Settings', 'bkx-alexa' ); ?></h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="enabled"><?php esc_html_e( 'Enable Integration', 'bkx-alexa' ); ?></label>
							</th>
							<td>
								<label class="bkx-toggle">
									<input type="checkbox" id="enabled" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Enable or disable the Amazon Alexa integration.', 'bkx-alexa' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="dev_mode"><?php esc_html_e( 'Development Mode', 'bkx-alexa' ); ?></label>
							</th>
							<td>
								<label class="bkx-toggle">
									<input type="checkbox" id="dev_mode" name="dev_mode" value="1" <?php checked( ! empty( $settings['dev_mode'] ) ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Skip request verification for testing. Disable in production.', 'bkx-alexa' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="bkx-card">
					<h2><?php esc_html_e( 'Alexa Skill Configuration', 'bkx-alexa' ); ?></h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="skill_id"><?php esc_html_e( 'Skill ID', 'bkx-alexa' ); ?></label>
							</th>
							<td>
								<input type="text" id="skill_id" name="skill_id" class="large-text"
									   value="<?php echo esc_attr( $settings['skill_id'] ?? '' ); ?>"
									   placeholder="amzn1.ask.skill.xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
								<p class="description"><?php esc_html_e( 'Your Alexa Skill ID from the Amazon Developer Console.', 'bkx-alexa' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="client_id"><?php esc_html_e( 'LWA Client ID', 'bkx-alexa' ); ?></label>
							</th>
							<td>
								<input type="text" id="client_id" name="client_id" class="large-text"
									   value="<?php echo esc_attr( $settings['client_id'] ?? '' ); ?>">
								<p class="description"><?php esc_html_e( 'Login with Amazon Client ID for account linking.', 'bkx-alexa' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="client_secret"><?php esc_html_e( 'LWA Client Secret', 'bkx-alexa' ); ?></label>
							</th>
							<td>
								<input type="password" id="client_secret" name="client_secret" class="large-text"
									   value="<?php echo esc_attr( $settings['client_secret'] ?? '' ); ?>">
								<p class="description"><?php esc_html_e( 'Login with Amazon Client Secret for account linking.', 'bkx-alexa' ); ?></p>
							</td>
						</tr>
					</table>

					<p>
						<button type="button" id="bkx-test-connection" class="button">
							<?php esc_html_e( 'Test Connection', 'bkx-alexa' ); ?>
						</button>
						<span id="connection-status"></span>
					</p>
				</div>

				<div class="bkx-card">
					<h2><?php esc_html_e( 'Voice Experience', 'bkx-alexa' ); ?></h2>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="invocation_name"><?php esc_html_e( 'Invocation Name', 'bkx-alexa' ); ?></label>
							</th>
							<td>
								<input type="text" id="invocation_name" name="invocation_name" class="regular-text"
									   value="<?php echo esc_attr( $settings['invocation_name'] ?? '' ); ?>"
									   placeholder="book appointment">
								<p class="description">
									<?php esc_html_e( 'Users will say "Alexa, open [invocation name]" to start.', 'bkx-alexa' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="welcome_message"><?php esc_html_e( 'Welcome Message', 'bkx-alexa' ); ?></label>
							</th>
							<td>
								<textarea id="welcome_message" name="welcome_message" class="large-text" rows="3"><?php
									echo esc_textarea( $settings['welcome_message'] ?? '' );
								?></textarea>
								<p class="description">
									<?php esc_html_e( 'Custom greeting when users start the skill. Leave empty for default.', 'bkx-alexa' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="require_linking"><?php esc_html_e( 'Require Account Linking', 'bkx-alexa' ); ?></label>
							</th>
							<td>
								<label class="bkx-toggle">
									<input type="checkbox" id="require_linking" name="require_linking" value="1" <?php checked( ! empty( $settings['require_linking'] ) ); ?>>
									<span class="slider"></span>
								</label>
								<p class="description"><?php esc_html_e( 'Require users to link their account before booking.', 'bkx-alexa' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="bkx-card">
					<h2><?php esc_html_e( 'Endpoint Configuration', 'bkx-alexa' ); ?></h2>

					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Skill Endpoint', 'bkx-alexa' ); ?></th>
							<td>
								<code id="skill-url"><?php echo esc_url( rest_url( 'bkx-alexa/v1/skill' ) ); ?></code>
								<button type="button" class="button button-small bkx-copy-btn" data-copy="skill-url">
									<?php esc_html_e( 'Copy', 'bkx-alexa' ); ?>
								</button>
								<p class="description"><?php esc_html_e( 'Configure this as the HTTPS endpoint in your Alexa skill.', 'bkx-alexa' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Authorization URL', 'bkx-alexa' ); ?></th>
							<td>
								<code id="auth-url"><?php echo esc_url( rest_url( 'bkx-alexa/v1/auth' ) ); ?></code>
								<button type="button" class="button button-small bkx-copy-btn" data-copy="auth-url">
									<?php esc_html_e( 'Copy', 'bkx-alexa' ); ?>
								</button>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Access Token URL', 'bkx-alexa' ); ?></th>
							<td>
								<code id="token-url"><?php echo esc_url( rest_url( 'bkx-alexa/v1/token' ) ); ?></code>
								<button type="button" class="button button-small bkx-copy-btn" data-copy="token-url">
									<?php esc_html_e( 'Copy', 'bkx-alexa' ); ?>
								</button>
							</td>
						</tr>
					</table>
				</div>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Save Settings', 'bkx-alexa' ); ?>
					</button>
					<button type="button" id="bkx-export-skill" class="button">
						<?php esc_html_e( 'Export Skill Configuration', 'bkx-alexa' ); ?>
					</button>
				</p>
			</form>

		<?php elseif ( 'setup' === $tab ) : ?>
			<!-- Setup Guide Tab -->
			<div class="bkx-card">
				<h2><?php esc_html_e( 'Setup Guide', 'bkx-alexa' ); ?></h2>

				<div class="bkx-setup-steps">
					<div class="bkx-step">
						<div class="step-number">1</div>
						<div class="step-content">
							<h3><?php esc_html_e( 'Create Amazon Developer Account', 'bkx-alexa' ); ?></h3>
							<p><?php esc_html_e( 'If you don\'t have one, create an Amazon Developer account.', 'bkx-alexa' ); ?></p>
							<a href="https://developer.amazon.com/" target="_blank" class="button">
								<?php esc_html_e( 'Amazon Developer Console', 'bkx-alexa' ); ?>
							</a>
						</div>
					</div>

					<div class="bkx-step">
						<div class="step-number">2</div>
						<div class="step-content">
							<h3><?php esc_html_e( 'Create Alexa Skill', 'bkx-alexa' ); ?></h3>
							<p><?php esc_html_e( 'Go to the Alexa Developer Console and create a new Custom skill.', 'bkx-alexa' ); ?></p>
							<ol>
								<li><?php esc_html_e( 'Click "Create Skill"', 'bkx-alexa' ); ?></li>
								<li><?php esc_html_e( 'Choose "Custom" model', 'bkx-alexa' ); ?></li>
								<li><?php esc_html_e( 'Select "Provision your own" hosting', 'bkx-alexa' ); ?></li>
								<li><?php esc_html_e( 'Choose your language and create', 'bkx-alexa' ); ?></li>
							</ol>
							<a href="https://developer.amazon.com/alexa/console/ask" target="_blank" class="button">
								<?php esc_html_e( 'Alexa Developer Console', 'bkx-alexa' ); ?>
							</a>
						</div>
					</div>

					<div class="bkx-step">
						<div class="step-number">3</div>
						<div class="step-content">
							<h3><?php esc_html_e( 'Configure Interaction Model', 'bkx-alexa' ); ?></h3>
							<p><?php esc_html_e( 'Export the interaction model and import it in the Alexa Console.', 'bkx-alexa' ); ?></p>
							<p><?php esc_html_e( 'Go to Build > Interaction Model > JSON Editor and paste the exported model.', 'bkx-alexa' ); ?></p>
							<button type="button" id="bkx-export-skill-guide" class="button">
								<?php esc_html_e( 'Export Interaction Model', 'bkx-alexa' ); ?>
							</button>
						</div>
					</div>

					<div class="bkx-step">
						<div class="step-number">4</div>
						<div class="step-content">
							<h3><?php esc_html_e( 'Set Endpoint', 'bkx-alexa' ); ?></h3>
							<p><?php esc_html_e( 'In Endpoint settings, select HTTPS and enter:', 'bkx-alexa' ); ?></p>
							<code><?php echo esc_url( rest_url( 'bkx-alexa/v1/skill' ) ); ?></code>
							<p><?php esc_html_e( 'For SSL certificate type, select "My development endpoint is a sub-domain of a domain that has a wildcard certificate from a certificate authority".', 'bkx-alexa' ); ?></p>
						</div>
					</div>

					<div class="bkx-step">
						<div class="step-number">5</div>
						<div class="step-content">
							<h3><?php esc_html_e( 'Configure Account Linking (Optional)', 'bkx-alexa' ); ?></h3>
							<p><?php esc_html_e( 'To enable personalized bookings, set up account linking:', 'bkx-alexa' ); ?></p>
							<ol>
								<li><?php esc_html_e( 'Create a Login with Amazon security profile', 'bkx-alexa' ); ?></li>
								<li><?php esc_html_e( 'Enter Client ID and Secret in Settings above', 'bkx-alexa' ); ?></li>
								<li><?php esc_html_e( 'In Alexa Console, go to Account Linking and configure:', 'bkx-alexa' ); ?>
									<ul>
										<li><?php esc_html_e( 'Authorization Grant Type: Auth Code Grant', 'bkx-alexa' ); ?></li>
										<li><?php esc_html_e( 'Authorization URI (from Settings tab)', 'bkx-alexa' ); ?></li>
										<li><?php esc_html_e( 'Access Token URI (from Settings tab)', 'bkx-alexa' ); ?></li>
									</ul>
								</li>
							</ol>
						</div>
					</div>

					<div class="bkx-step">
						<div class="step-number">6</div>
						<div class="step-content">
							<h3><?php esc_html_e( 'Test Your Skill', 'bkx-alexa' ); ?></h3>
							<p><?php esc_html_e( 'Use the Alexa Simulator in the developer console to test your skill.', 'bkx-alexa' ); ?></p>
							<p><?php esc_html_e( 'Enable "Development Mode" in Settings to bypass request verification during testing.', 'bkx-alexa' ); ?></p>
						</div>
					</div>
				</div>
			</div>

		<?php elseif ( 'logs' === $tab ) : ?>
			<!-- Logs Tab -->
			<div class="bkx-card">
				<h2><?php esc_html_e( 'Voice Interaction Logs', 'bkx-alexa' ); ?></h2>

				<div class="bkx-stats-row">
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-total">-</span>
						<span class="stat-label"><?php esc_html_e( 'Total Requests', 'bkx-alexa' ); ?></span>
					</div>
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-successful">-</span>
						<span class="stat-label"><?php esc_html_e( 'Successful', 'bkx-alexa' ); ?></span>
					</div>
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-bookings">-</span>
						<span class="stat-label"><?php esc_html_e( 'Bookings Created', 'bkx-alexa' ); ?></span>
					</div>
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-accounts">-</span>
						<span class="stat-label"><?php esc_html_e( 'Linked Accounts', 'bkx-alexa' ); ?></span>
					</div>
					<div class="bkx-stat-box">
						<span class="stat-value" id="stat-today">-</span>
						<span class="stat-label"><?php esc_html_e( 'Today', 'bkx-alexa' ); ?></span>
					</div>
				</div>

				<?php
				global $wpdb;
				$logs = $wpdb->get_results(
					"SELECT * FROM {$wpdb->prefix}bkx_alexa_logs ORDER BY created_at DESC LIMIT 50" // phpcs:ignore
				);
				?>

				<?php if ( ! empty( $logs ) ) : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Time', 'bkx-alexa' ); ?></th>
								<th><?php esc_html_e( 'Type', 'bkx-alexa' ); ?></th>
								<th><?php esc_html_e( 'Intent', 'bkx-alexa' ); ?></th>
								<th><?php esc_html_e( 'Response', 'bkx-alexa' ); ?></th>
								<th><?php esc_html_e( 'Status', 'bkx-alexa' ); ?></th>
								<th><?php esc_html_e( 'Time (ms)', 'bkx-alexa' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $logs as $log ) : ?>
								<tr>
									<td>
										<?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->created_at ) ) ); ?>
									</td>
									<td><code><?php echo esc_html( $log->request_type ); ?></code></td>
									<td><code><?php echo esc_html( $log->intent ); ?></code></td>
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
						<?php esc_html_e( 'No voice interactions logged yet. Test your Alexa skill to see logs here.', 'bkx-alexa' ); ?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
