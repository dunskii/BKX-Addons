<?php
/**
 * Settings tab template.
 *
 * @package BookingX\HubSpot
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_hubspot_settings', array() );
?>

<div class="bkx-hs-settings">
	<form id="bkx-hs-settings-form" method="post">
		<?php wp_nonce_field( 'bkx_hubspot_nonce', 'bkx_hs_nonce' ); ?>

		<!-- API Credentials -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'HubSpot API Credentials', 'bkx-hubspot' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Enter your HubSpot App credentials. Create an app at developers.hubspot.com.', 'bkx-hubspot' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="client_id"><?php esc_html_e( 'Client ID', 'bkx-hubspot' ); ?></label>
					</th>
					<td>
						<input type="text" id="client_id" name="client_id" class="regular-text"
							   value="<?php echo esc_attr( $settings['client_id'] ?? '' ); ?>">
						<p class="description">
							<?php esc_html_e( 'The Client ID from your HubSpot App.', 'bkx-hubspot' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="client_secret"><?php esc_html_e( 'Client Secret', 'bkx-hubspot' ); ?></label>
					</th>
					<td>
						<input type="password" id="client_secret" name="client_secret" class="regular-text"
							   value="<?php echo esc_attr( $settings['client_secret'] ?? '' ); ?>">
						<p class="description">
							<?php esc_html_e( 'The Client Secret from your HubSpot App.', 'bkx-hubspot' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Redirect URL', 'bkx-hubspot' ); ?></th>
					<td>
						<code><?php echo esc_html( add_query_arg( 'bkx_hs_oauth', '1', admin_url( 'admin.php' ) ) ); ?></code>
						<p class="description">
							<?php esc_html_e( 'Add this URL to the Redirect URLs in your HubSpot App settings.', 'bkx-hubspot' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Sync Settings -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Sync Settings', 'bkx-hubspot' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Object Sync', 'bkx-hubspot' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="sync_contacts" value="1"
									   <?php checked( $settings['sync_contacts'] ?? true ); ?>>
								<?php esc_html_e( 'Sync customers as Contacts', 'bkx-hubspot' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="create_deals" value="1"
									   <?php checked( $settings['create_deals'] ?? true ); ?>>
								<?php esc_html_e( 'Create Deals for bookings', 'bkx-hubspot' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="track_activities" value="1"
									   <?php checked( ! empty( $settings['track_activities'] ) ); ?>>
								<?php esc_html_e( 'Log booking activities on contacts', 'bkx-hubspot' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Automatic Sync', 'bkx-hubspot' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="sync_on_booking" value="1"
									   <?php checked( $settings['sync_on_booking'] ?? true ); ?>>
								<?php esc_html_e( 'Sync when a new booking is created', 'bkx-hubspot' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="sync_on_status" value="1"
									   <?php checked( $settings['sync_on_status'] ?? true ); ?>>
								<?php esc_html_e( 'Sync when booking status changes', 'bkx-hubspot' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
			</table>
		</div>

		<!-- Contact List Settings -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Contact List Settings', 'bkx-hubspot' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Add to List', 'bkx-hubspot' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="add_to_list" value="1" id="bkx-add-to-list"
								   <?php checked( ! empty( $settings['add_to_list'] ) ); ?>>
							<?php esc_html_e( 'Add contacts to a HubSpot list', 'bkx-hubspot' ); ?>
						</label>
					</td>
				</tr>
				<tr class="bkx-list-row" style="<?php echo empty( $settings['add_to_list'] ) ? 'display: none;' : ''; ?>">
					<th scope="row">
						<label for="list_id"><?php esc_html_e( 'Select List', 'bkx-hubspot' ); ?></label>
					</th>
					<td>
						<select id="list_id" name="list_id">
							<option value=""><?php esc_html_e( 'Select a list...', 'bkx-hubspot' ); ?></option>
							<?php if ( ! empty( $settings['list_id'] ) ) : ?>
								<option value="<?php echo esc_attr( $settings['list_id'] ); ?>" selected>
									<?php echo esc_html( $settings['list_id'] ); ?>
								</option>
							<?php endif; ?>
						</select>
						<button type="button" class="button" id="bkx-load-lists">
							<?php esc_html_e( 'Load Lists', 'bkx-hubspot' ); ?>
						</button>
					</td>
				</tr>
			</table>
		</div>

		<!-- Deal Pipeline Settings -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Deal Pipeline Settings', 'bkx-hubspot' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="pipeline_id"><?php esc_html_e( 'Pipeline', 'bkx-hubspot' ); ?></label>
					</th>
					<td>
						<select id="pipeline_id" name="pipeline_id">
							<option value=""><?php esc_html_e( 'Default Pipeline', 'bkx-hubspot' ); ?></option>
							<?php if ( ! empty( $settings['pipeline_id'] ) ) : ?>
								<option value="<?php echo esc_attr( $settings['pipeline_id'] ); ?>" selected>
									<?php echo esc_html( $settings['pipeline_id'] ); ?>
								</option>
							<?php endif; ?>
						</select>
						<button type="button" class="button" id="bkx-load-pipelines">
							<?php esc_html_e( 'Load Pipelines', 'bkx-hubspot' ); ?>
						</button>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="default_stage_id"><?php esc_html_e( 'Default Stage', 'bkx-hubspot' ); ?></label>
					</th>
					<td>
						<select id="default_stage_id" name="default_stage_id">
							<option value=""><?php esc_html_e( 'Select a stage...', 'bkx-hubspot' ); ?></option>
							<?php if ( ! empty( $settings['default_stage_id'] ) ) : ?>
								<option value="<?php echo esc_attr( $settings['default_stage_id'] ); ?>" selected>
									<?php echo esc_html( $settings['default_stage_id'] ); ?>
								</option>
							<?php endif; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Stage to assign to newly created Deals.', 'bkx-hubspot' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Webhook Settings -->
		<div class="bkx-card">
			<h2><?php esc_html_e( 'Webhook Settings', 'bkx-hubspot' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configure webhooks to receive updates from HubSpot when records change.', 'bkx-hubspot' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Webhook URL', 'bkx-hubspot' ); ?></th>
					<td>
						<code><?php echo esc_html( rest_url( 'bkx-hubspot/v1/webhook' ) ); ?></code>
						<p class="description">
							<?php esc_html_e( 'Use this URL when configuring HubSpot webhooks in your app settings.', 'bkx-hubspot' ); ?>
						</p>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-hubspot' ); ?>
			</button>
		</p>
	</form>
</div>
