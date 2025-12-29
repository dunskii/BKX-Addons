<?php
/**
 * Settings tab template.
 *
 * @package BookingX\Salesforce
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_salesforce_settings', array() );
?>

<div class="bkx-sf-settings">
	<form id="bkx-sf-settings-form" method="post">
		<?php wp_nonce_field( 'bkx_salesforce_nonce', 'bkx_sf_nonce' ); ?>

		<!-- API Credentials -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Salesforce API Credentials', 'bkx-salesforce' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Enter your Salesforce Connected App credentials. These can be found in Salesforce Setup > Apps > App Manager.', 'bkx-salesforce' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="client_id"><?php esc_html_e( 'Consumer Key', 'bkx-salesforce' ); ?></label>
					</th>
					<td>
						<input type="text" id="client_id" name="client_id" class="regular-text"
							   value="<?php echo esc_attr( $settings['client_id'] ?? '' ); ?>">
						<p class="description">
							<?php esc_html_e( 'The Consumer Key from your Connected App.', 'bkx-salesforce' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="client_secret"><?php esc_html_e( 'Consumer Secret', 'bkx-salesforce' ); ?></label>
					</th>
					<td>
						<input type="password" id="client_secret" name="client_secret" class="regular-text"
							   value="<?php echo esc_attr( $settings['client_secret'] ?? '' ); ?>">
						<p class="description">
							<?php esc_html_e( 'The Consumer Secret from your Connected App.', 'bkx-salesforce' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Environment', 'bkx-salesforce' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="sandbox" value="1"
								   <?php checked( ! empty( $settings['sandbox'] ) ); ?>>
							<?php esc_html_e( 'Use Sandbox Environment', 'bkx-salesforce' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Check this if connecting to a Salesforce Sandbox instead of Production.', 'bkx-salesforce' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Callback URL', 'bkx-salesforce' ); ?></th>
					<td>
						<code><?php echo esc_html( add_query_arg( 'bkx_sf_oauth', '1', admin_url( 'admin.php' ) ) ); ?></code>
						<p class="description">
							<?php esc_html_e( 'Add this URL to the Callback URL field in your Connected App settings.', 'bkx-salesforce' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Sync Settings -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Sync Settings', 'bkx-salesforce' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Object Sync', 'bkx-salesforce' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="sync_contacts" value="1"
									   <?php checked( $settings['sync_contacts'] ?? true ); ?>>
								<?php esc_html_e( 'Sync customers as Contacts', 'bkx-salesforce' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="sync_leads" value="1"
									   <?php checked( ! empty( $settings['sync_leads'] ) ); ?>>
								<?php esc_html_e( 'Sync customers as Leads (for new customers)', 'bkx-salesforce' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="create_opportunities" value="1"
									   <?php checked( $settings['create_opportunities'] ?? true ); ?>>
								<?php esc_html_e( 'Create Opportunities for bookings', 'bkx-salesforce' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Automatic Sync', 'bkx-salesforce' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="sync_on_booking" value="1"
									   <?php checked( $settings['sync_on_booking'] ?? true ); ?>>
								<?php esc_html_e( 'Sync when a new booking is created', 'bkx-salesforce' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="sync_on_status" value="1"
									   <?php checked( $settings['sync_on_status'] ?? true ); ?>>
								<?php esc_html_e( 'Sync when booking status changes', 'bkx-salesforce' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			</table>
		</div>

		<!-- Default Values -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Default Values', 'bkx-salesforce' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="default_lead_status"><?php esc_html_e( 'Default Lead Status', 'bkx-salesforce' ); ?></label>
					</th>
					<td>
						<select id="default_lead_status" name="default_lead_status">
							<option value="Open - Not Contacted" <?php selected( $settings['default_lead_status'] ?? '', 'Open - Not Contacted' ); ?>>
								Open - Not Contacted
							</option>
							<option value="Working - Contacted" <?php selected( $settings['default_lead_status'] ?? '', 'Working - Contacted' ); ?>>
								Working - Contacted
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Status to assign to newly created Leads.', 'bkx-salesforce' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="default_opp_stage"><?php esc_html_e( 'Default Opportunity Stage', 'bkx-salesforce' ); ?></label>
					</th>
					<td>
						<select id="default_opp_stage" name="default_opp_stage">
							<option value="Prospecting" <?php selected( $settings['default_opp_stage'] ?? '', 'Prospecting' ); ?>>
								Prospecting
							</option>
							<option value="Qualification" <?php selected( $settings['default_opp_stage'] ?? '', 'Qualification' ); ?>>
								Qualification
							</option>
							<option value="Needs Analysis" <?php selected( $settings['default_opp_stage'] ?? '', 'Needs Analysis' ); ?>>
								Needs Analysis
							</option>
						</select>
						<p class="description">
							<?php esc_html_e( 'Stage to assign to newly created Opportunities.', 'bkx-salesforce' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Webhook Settings (for bi-directional sync) -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Webhook Settings', 'bkx-salesforce' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configure webhooks to receive updates from Salesforce when records change.', 'bkx-salesforce' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Webhook URL', 'bkx-salesforce' ); ?></th>
					<td>
						<code><?php echo esc_html( rest_url( 'bkx-salesforce/v1/webhook' ) ); ?></code>
						<p class="description">
							<?php esc_html_e( 'Use this URL when configuring Salesforce Outbound Messages or Platform Events.', 'bkx-salesforce' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="webhook_secret"><?php esc_html_e( 'Webhook Secret', 'bkx-salesforce' ); ?></label>
					</th>
					<td>
						<input type="text" id="webhook_secret" name="webhook_secret" class="regular-text"
							   value="<?php echo esc_attr( $settings['webhook_secret'] ?? '' ); ?>">
						<button type="button" class="button" id="bkx-sf-generate-secret">
							<?php esc_html_e( 'Generate', 'bkx-salesforce' ); ?>
						</button>
						<p class="description">
							<?php esc_html_e( 'Secret key for webhook signature verification.', 'bkx-salesforce' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-salesforce' ); ?>
			</button>
		</p>
	</form>
</div>
