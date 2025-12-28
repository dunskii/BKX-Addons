<?php
/**
 * Xero Admin Settings Template.
 *
 * @package BookingX\Xero
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$oauth        = new \BookingX\Xero\Services\OAuthService();
$is_connected = $oauth->is_connected();
$org_info     = $is_connected ? $oauth->get_organization_info() : null;
$active_tab   = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'connection';
?>
<div class="wrap bkx-xero-wrap">
	<h1><?php esc_html_e( 'Xero Integration', 'bkx-xero' ); ?></h1>

	<?php settings_errors( 'bkx_xero' ); ?>

	<nav class="nav-tab-wrapper bkx-xero-nav">
		<a href="?page=bkx-xero&tab=connection" class="nav-tab <?php echo 'connection' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Connection', 'bkx-xero' ); ?>
		</a>
		<a href="?page=bkx-xero&tab=settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'bkx-xero' ); ?>
		</a>
		<a href="?page=bkx-xero&tab=sync" class="nav-tab <?php echo 'sync' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Sync', 'bkx-xero' ); ?>
		</a>
		<a href="?page=bkx-xero&tab=logs" class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Logs', 'bkx-xero' ); ?>
		</a>
	</nav>

	<div class="bkx-xero-content">
		<?php if ( 'connection' === $active_tab ) : ?>
			<!-- Connection Tab -->
			<div class="bkx-xero-card">
				<h2><?php esc_html_e( 'Xero Connection', 'bkx-xero' ); ?></h2>

				<?php if ( $is_connected && $org_info ) : ?>
					<div class="bkx-xero-connected">
						<div class="bkx-xero-status bkx-xero-status-connected">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Connected', 'bkx-xero' ); ?>
						</div>

						<div class="bkx-xero-org-info">
							<h3><?php echo esc_html( $org_info['Name'] ?? '' ); ?></h3>
							<p>
								<?php if ( ! empty( $org_info['LegalName'] ) && $org_info['LegalName'] !== $org_info['Name'] ) : ?>
									<strong><?php esc_html_e( 'Legal Name:', 'bkx-xero' ); ?></strong>
									<?php echo esc_html( $org_info['LegalName'] ); ?><br>
								<?php endif; ?>
								<?php if ( ! empty( $org_info['CountryCode'] ) ) : ?>
									<strong><?php esc_html_e( 'Country:', 'bkx-xero' ); ?></strong>
									<?php echo esc_html( $org_info['CountryCode'] ); ?><br>
								<?php endif; ?>
								<?php if ( ! empty( $org_info['BaseCurrency'] ) ) : ?>
									<strong><?php esc_html_e( 'Currency:', 'bkx-xero' ); ?></strong>
									<?php echo esc_html( $org_info['BaseCurrency'] ); ?>
								<?php endif; ?>
							</p>
							<p class="bkx-xero-org-meta">
								<strong><?php esc_html_e( 'Tenant ID:', 'bkx-xero' ); ?></strong>
								<?php echo esc_html( get_option( 'bkx_xero_tenant_id' ) ); ?>
							</p>
						</div>

						<button type="button" class="button button-secondary" id="bkx-xero-disconnect">
							<?php esc_html_e( 'Disconnect', 'bkx-xero' ); ?>
						</button>
					</div>
				<?php else : ?>
					<div class="bkx-xero-disconnected">
						<div class="bkx-xero-status bkx-xero-status-disconnected">
							<span class="dashicons dashicons-warning"></span>
							<?php esc_html_e( 'Not Connected', 'bkx-xero' ); ?>
						</div>

						<p><?php esc_html_e( 'Connect your Xero account to sync bookings, invoices, and contacts.', 'bkx-xero' ); ?></p>

						<form method="post" action="options.php" class="bkx-xero-credentials-form">
							<?php settings_fields( 'bkx_xero' ); ?>

							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="bkx_xero_client_id"><?php esc_html_e( 'Client ID', 'bkx-xero' ); ?></label>
									</th>
									<td>
										<input type="text" id="bkx_xero_client_id" name="bkx_xero_client_id"
											   value="<?php echo esc_attr( get_option( 'bkx_xero_client_id' ) ); ?>"
											   class="regular-text">
										<p class="description">
											<?php esc_html_e( 'Enter your Xero app Client ID.', 'bkx-xero' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="bkx_xero_client_secret"><?php esc_html_e( 'Client Secret', 'bkx-xero' ); ?></label>
									</th>
									<td>
										<input type="password" id="bkx_xero_client_secret" name="bkx_xero_client_secret"
											   value="<?php echo esc_attr( get_option( 'bkx_xero_client_secret' ) ); ?>"
											   class="regular-text">
										<p class="description">
											<?php esc_html_e( 'Enter your Xero app Client Secret.', 'bkx-xero' ); ?>
										</p>
									</td>
								</tr>
							</table>

							<?php submit_button( __( 'Save Credentials', 'bkx-xero' ) ); ?>
						</form>

						<?php if ( get_option( 'bkx_xero_client_id' ) && get_option( 'bkx_xero_client_secret' ) ) : ?>
							<hr>
							<p><?php esc_html_e( 'Click the button below to connect to Xero:', 'bkx-xero' ); ?></p>
							<button type="button" class="button button-primary button-hero" id="bkx-xero-connect">
								<span class="dashicons dashicons-cloud"></span>
								<?php esc_html_e( 'Connect to Xero', 'bkx-xero' ); ?>
							</button>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Setup Guide -->
			<div class="bkx-xero-card">
				<h2><?php esc_html_e( 'Setup Guide', 'bkx-xero' ); ?></h2>
				<ol class="bkx-xero-setup-steps">
					<li>
						<strong><?php esc_html_e( 'Create a Xero Developer Account', 'bkx-xero' ); ?></strong>
						<p><?php esc_html_e( 'Visit developer.xero.com and sign up for a developer account.', 'bkx-xero' ); ?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Create an App', 'bkx-xero' ); ?></strong>
						<p><?php esc_html_e( 'Go to My Apps and create a new Web App.', 'bkx-xero' ); ?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Configure Redirect URI', 'bkx-xero' ); ?></strong>
						<p>
							<?php esc_html_e( 'Add this redirect URI to your app:', 'bkx-xero' ); ?><br>
							<code><?php echo esc_url( admin_url( 'admin.php?page=bkx-xero' ) ); ?></code>
						</p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Copy Credentials', 'bkx-xero' ); ?></strong>
						<p><?php esc_html_e( 'Copy the Client ID and generate a Client Secret from your app settings.', 'bkx-xero' ); ?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Connect', 'bkx-xero' ); ?></strong>
						<p><?php esc_html_e( 'Enter your credentials above and click Connect to Xero.', 'bkx-xero' ); ?></p>
					</li>
				</ol>
			</div>

		<?php elseif ( 'settings' === $active_tab ) : ?>
			<!-- Settings Tab -->
			<div class="bkx-xero-card">
				<h2><?php esc_html_e( 'Sync Settings', 'bkx-xero' ); ?></h2>

				<form method="post" action="options.php">
					<?php settings_fields( 'bkx_xero' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-Sync Contacts', 'bkx-xero' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="bkx_xero_auto_sync_contacts" value="1"
										<?php checked( get_option( 'bkx_xero_auto_sync_contacts' ), 1 ); ?>>
									<?php esc_html_e( 'Automatically sync contacts when bookings are created', 'bkx-xero' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-Sync Invoices', 'bkx-xero' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="bkx_xero_auto_sync_invoices" value="1"
										<?php checked( get_option( 'bkx_xero_auto_sync_invoices' ), 1 ); ?>>
									<?php esc_html_e( 'Automatically create invoices in Xero for new bookings', 'bkx-xero' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-Sync Payments', 'bkx-xero' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="bkx_xero_auto_sync_payments" value="1"
										<?php checked( get_option( 'bkx_xero_auto_sync_payments' ), 1 ); ?>>
									<?php esc_html_e( 'Automatically record payments when bookings are paid', 'bkx-xero' ); ?>
								</label>
							</td>
						</tr>
					</table>

					<?php submit_button(); ?>
				</form>
			</div>

			<div class="bkx-xero-card">
				<h2><?php esc_html_e( 'Account Mapping', 'bkx-xero' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Configure which Xero accounts to use for booking revenue.', 'bkx-xero' ); ?>
				</p>

				<form method="post" action="options.php">
					<?php settings_fields( 'bkx_xero' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="bkx_xero_revenue_account"><?php esc_html_e( 'Revenue Account', 'bkx-xero' ); ?></label>
							</th>
							<td>
								<input type="text" id="bkx_xero_revenue_account" name="bkx_xero_revenue_account"
									   value="<?php echo esc_attr( get_option( 'bkx_xero_revenue_account' ) ); ?>"
									   class="regular-text" placeholder="200">
								<p class="description">
									<?php esc_html_e( 'Xero account code for booking revenue. Default: 200 (Sales)', 'bkx-xero' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bkx_xero_tax_type"><?php esc_html_e( 'Tax Type', 'bkx-xero' ); ?></label>
							</th>
							<td>
								<select id="bkx_xero_tax_type" name="bkx_xero_tax_type">
									<option value="" <?php selected( get_option( 'bkx_xero_tax_type' ), '' ); ?>>
										<?php esc_html_e( 'Default', 'bkx-xero' ); ?>
									</option>
									<option value="OUTPUT" <?php selected( get_option( 'bkx_xero_tax_type' ), 'OUTPUT' ); ?>>
										<?php esc_html_e( 'Tax on Sales', 'bkx-xero' ); ?>
									</option>
									<option value="NONE" <?php selected( get_option( 'bkx_xero_tax_type' ), 'NONE' ); ?>>
										<?php esc_html_e( 'No Tax', 'bkx-xero' ); ?>
									</option>
									<option value="EXEMPTOUTPUT" <?php selected( get_option( 'bkx_xero_tax_type' ), 'EXEMPTOUTPUT' ); ?>>
										<?php esc_html_e( 'Tax Exempt', 'bkx-xero' ); ?>
									</option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bkx_xero_branding_theme"><?php esc_html_e( 'Branding Theme', 'bkx-xero' ); ?></label>
							</th>
							<td>
								<input type="text" id="bkx_xero_branding_theme" name="bkx_xero_branding_theme"
									   value="<?php echo esc_attr( get_option( 'bkx_xero_branding_theme' ) ); ?>"
									   class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Optional Xero branding theme ID for invoices.', 'bkx-xero' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button(); ?>
				</form>
			</div>

		<?php elseif ( 'sync' === $active_tab ) : ?>
			<!-- Sync Tab -->
			<div class="bkx-xero-card">
				<h2><?php esc_html_e( 'Sync Status', 'bkx-xero' ); ?></h2>

				<?php if ( ! $is_connected ) : ?>
					<div class="notice notice-warning inline">
						<p><?php esc_html_e( 'Please connect to Xero first.', 'bkx-xero' ); ?></p>
					</div>
				<?php else : ?>
					<div class="bkx-xero-sync-stats" id="bkx-xero-sync-stats">
						<div class="bkx-xero-stat">
							<span class="bkx-xero-stat-value" id="stat-contacts">-</span>
							<span class="bkx-xero-stat-label"><?php esc_html_e( 'Contacts Synced', 'bkx-xero' ); ?></span>
						</div>
						<div class="bkx-xero-stat">
							<span class="bkx-xero-stat-value" id="stat-invoices">-</span>
							<span class="bkx-xero-stat-label"><?php esc_html_e( 'Invoices Synced', 'bkx-xero' ); ?></span>
						</div>
						<div class="bkx-xero-stat">
							<span class="bkx-xero-stat-value" id="stat-payments">-</span>
							<span class="bkx-xero-stat-label"><?php esc_html_e( 'Payments Synced', 'bkx-xero' ); ?></span>
						</div>
						<div class="bkx-xero-stat">
							<span class="bkx-xero-stat-value" id="stat-pending">-</span>
							<span class="bkx-xero-stat-label"><?php esc_html_e( 'Pending', 'bkx-xero' ); ?></span>
						</div>
						<div class="bkx-xero-stat">
							<span class="bkx-xero-stat-value bkx-xero-stat-error" id="stat-failed">-</span>
							<span class="bkx-xero-stat-label"><?php esc_html_e( 'Failed', 'bkx-xero' ); ?></span>
						</div>
					</div>

					<div class="bkx-xero-sync-actions">
						<h3><?php esc_html_e( 'Manual Sync', 'bkx-xero' ); ?></h3>
						<p><?php esc_html_e( 'Manually sync data to Xero.', 'bkx-xero' ); ?></p>

						<div class="bkx-xero-button-group">
							<button type="button" class="button" id="bkx-xero-sync-contacts">
								<span class="dashicons dashicons-groups"></span>
								<?php esc_html_e( 'Sync Contacts', 'bkx-xero' ); ?>
							</button>
							<button type="button" class="button" id="bkx-xero-sync-items">
								<span class="dashicons dashicons-archive"></span>
								<?php esc_html_e( 'Sync Items', 'bkx-xero' ); ?>
							</button>
							<button type="button" class="button" id="bkx-xero-sync-invoices">
								<span class="dashicons dashicons-media-text"></span>
								<?php esc_html_e( 'Sync Invoices', 'bkx-xero' ); ?>
							</button>
							<button type="button" class="button button-primary" id="bkx-xero-sync-all">
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Sync All', 'bkx-xero' ); ?>
							</button>
						</div>

						<div id="bkx-xero-sync-progress" class="bkx-xero-sync-progress" style="display: none;">
							<div class="bkx-xero-progress-bar">
								<div class="bkx-xero-progress-fill"></div>
							</div>
							<p class="bkx-xero-progress-message"></p>
						</div>
					</div>
				<?php endif; ?>
			</div>

		<?php elseif ( 'logs' === $active_tab ) : ?>
			<!-- Logs Tab -->
			<div class="bkx-xero-card">
				<h2><?php esc_html_e( 'Sync Logs', 'bkx-xero' ); ?></h2>

				<table class="wp-list-table widefat fixed striped" id="bkx-xero-logs-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'bkx-xero' ); ?></th>
							<th><?php esc_html_e( 'Type', 'bkx-xero' ); ?></th>
							<th><?php esc_html_e( 'Entity ID', 'bkx-xero' ); ?></th>
							<th><?php esc_html_e( 'Xero ID', 'bkx-xero' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bkx-xero' ); ?></th>
							<th><?php esc_html_e( 'Message', 'bkx-xero' ); ?></th>
						</tr>
					</thead>
					<tbody id="bkx-xero-logs-body">
						<tr>
							<td colspan="6"><?php esc_html_e( 'Loading...', 'bkx-xero' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>
