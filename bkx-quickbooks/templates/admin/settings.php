<?php
/**
 * QuickBooks Admin Settings Template.
 *
 * @package BookingX\QuickBooks
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$oauth        = new \BookingX\QuickBooks\Services\OAuthService();
$is_connected = $oauth->is_connected();
$company_info = $is_connected ? $oauth->get_company_info() : null;
$active_tab   = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'connection';
?>
<div class="wrap bkx-quickbooks-wrap">
	<h1><?php esc_html_e( 'QuickBooks Integration', 'bkx-quickbooks' ); ?></h1>

	<?php settings_errors( 'bkx_quickbooks' ); ?>

	<nav class="nav-tab-wrapper bkx-qb-nav">
		<a href="?page=bkx-quickbooks&tab=connection" class="nav-tab <?php echo 'connection' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Connection', 'bkx-quickbooks' ); ?>
		</a>
		<a href="?page=bkx-quickbooks&tab=settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Settings', 'bkx-quickbooks' ); ?>
		</a>
		<a href="?page=bkx-quickbooks&tab=sync" class="nav-tab <?php echo 'sync' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Sync', 'bkx-quickbooks' ); ?>
		</a>
		<a href="?page=bkx-quickbooks&tab=logs" class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Logs', 'bkx-quickbooks' ); ?>
		</a>
	</nav>

	<div class="bkx-qb-content">
		<?php if ( 'connection' === $active_tab ) : ?>
			<!-- Connection Tab -->
			<div class="bkx-qb-card">
				<h2><?php esc_html_e( 'QuickBooks Connection', 'bkx-quickbooks' ); ?></h2>

				<?php if ( $is_connected && $company_info ) : ?>
					<div class="bkx-qb-connected">
						<div class="bkx-qb-status bkx-qb-status-connected">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'Connected', 'bkx-quickbooks' ); ?>
						</div>

						<div class="bkx-qb-company-info">
							<h3><?php echo esc_html( $company_info['CompanyName'] ?? '' ); ?></h3>
							<p>
								<?php if ( ! empty( $company_info['CompanyAddr'] ) ) : ?>
									<?php echo esc_html( $company_info['CompanyAddr']['Line1'] ?? '' ); ?><br>
									<?php echo esc_html( $company_info['CompanyAddr']['City'] ?? '' ); ?>,
									<?php echo esc_html( $company_info['CompanyAddr']['CountrySubDivisionCode'] ?? '' ); ?>
									<?php echo esc_html( $company_info['CompanyAddr']['PostalCode'] ?? '' ); ?>
								<?php endif; ?>
							</p>
							<p class="bkx-qb-company-meta">
								<strong><?php esc_html_e( 'Company ID:', 'bkx-quickbooks' ); ?></strong>
								<?php echo esc_html( get_option( 'bkx_qb_realm_id' ) ); ?>
							</p>
						</div>

						<button type="button" class="button button-secondary" id="bkx-qb-disconnect">
							<?php esc_html_e( 'Disconnect', 'bkx-quickbooks' ); ?>
						</button>
					</div>
				<?php else : ?>
					<div class="bkx-qb-disconnected">
						<div class="bkx-qb-status bkx-qb-status-disconnected">
							<span class="dashicons dashicons-warning"></span>
							<?php esc_html_e( 'Not Connected', 'bkx-quickbooks' ); ?>
						</div>

						<p><?php esc_html_e( 'Connect your QuickBooks Online account to sync bookings, invoices, and customers.', 'bkx-quickbooks' ); ?></p>

						<form method="post" action="options.php" class="bkx-qb-credentials-form">
							<?php settings_fields( 'bkx_quickbooks' ); ?>

							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="bkx_qb_client_id"><?php esc_html_e( 'Client ID', 'bkx-quickbooks' ); ?></label>
									</th>
									<td>
										<input type="text" id="bkx_qb_client_id" name="bkx_qb_client_id"
											   value="<?php echo esc_attr( get_option( 'bkx_qb_client_id' ) ); ?>"
											   class="regular-text">
										<p class="description">
											<?php esc_html_e( 'Enter your QuickBooks app Client ID.', 'bkx-quickbooks' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="bkx_qb_client_secret"><?php esc_html_e( 'Client Secret', 'bkx-quickbooks' ); ?></label>
									</th>
									<td>
										<input type="password" id="bkx_qb_client_secret" name="bkx_qb_client_secret"
											   value="<?php echo esc_attr( get_option( 'bkx_qb_client_secret' ) ); ?>"
											   class="regular-text">
										<p class="description">
											<?php esc_html_e( 'Enter your QuickBooks app Client Secret.', 'bkx-quickbooks' ); ?>
										</p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="bkx_qb_environment"><?php esc_html_e( 'Environment', 'bkx-quickbooks' ); ?></label>
									</th>
									<td>
										<select id="bkx_qb_environment" name="bkx_qb_environment">
											<option value="sandbox" <?php selected( get_option( 'bkx_qb_environment' ), 'sandbox' ); ?>>
												<?php esc_html_e( 'Sandbox (Testing)', 'bkx-quickbooks' ); ?>
											</option>
											<option value="production" <?php selected( get_option( 'bkx_qb_environment' ), 'production' ); ?>>
												<?php esc_html_e( 'Production (Live)', 'bkx-quickbooks' ); ?>
											</option>
										</select>
									</td>
								</tr>
							</table>

							<?php submit_button( __( 'Save Credentials', 'bkx-quickbooks' ) ); ?>
						</form>

						<?php if ( get_option( 'bkx_qb_client_id' ) && get_option( 'bkx_qb_client_secret' ) ) : ?>
							<hr>
							<p><?php esc_html_e( 'Click the button below to connect to QuickBooks:', 'bkx-quickbooks' ); ?></p>
							<button type="button" class="button button-primary button-hero" id="bkx-qb-connect">
								<span class="dashicons dashicons-cloud"></span>
								<?php esc_html_e( 'Connect to QuickBooks', 'bkx-quickbooks' ); ?>
							</button>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Setup Guide -->
			<div class="bkx-qb-card">
				<h2><?php esc_html_e( 'Setup Guide', 'bkx-quickbooks' ); ?></h2>
				<ol class="bkx-qb-setup-steps">
					<li>
						<strong><?php esc_html_e( 'Create a QuickBooks Developer Account', 'bkx-quickbooks' ); ?></strong>
						<p><?php esc_html_e( 'Visit developer.intuit.com and sign up for a developer account.', 'bkx-quickbooks' ); ?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Create an App', 'bkx-quickbooks' ); ?></strong>
						<p><?php esc_html_e( 'Create a new app and select "QuickBooks Online and Payments".', 'bkx-quickbooks' ); ?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Configure Redirect URI', 'bkx-quickbooks' ); ?></strong>
						<p>
							<?php esc_html_e( 'Add this redirect URI to your app:', 'bkx-quickbooks' ); ?><br>
							<code><?php echo esc_url( admin_url( 'admin.php?page=bkx-quickbooks' ) ); ?></code>
						</p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Copy Credentials', 'bkx-quickbooks' ); ?></strong>
						<p><?php esc_html_e( 'Copy the Client ID and Client Secret from your app settings.', 'bkx-quickbooks' ); ?></p>
					</li>
					<li>
						<strong><?php esc_html_e( 'Connect', 'bkx-quickbooks' ); ?></strong>
						<p><?php esc_html_e( 'Enter your credentials above and click Connect to QuickBooks.', 'bkx-quickbooks' ); ?></p>
					</li>
				</ol>
			</div>

		<?php elseif ( 'settings' === $active_tab ) : ?>
			<!-- Settings Tab -->
			<div class="bkx-qb-card">
				<h2><?php esc_html_e( 'Sync Settings', 'bkx-quickbooks' ); ?></h2>

				<form method="post" action="options.php">
					<?php settings_fields( 'bkx_quickbooks' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-Sync Customers', 'bkx-quickbooks' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="bkx_qb_auto_sync_customers" value="1"
										<?php checked( get_option( 'bkx_qb_auto_sync_customers' ), 1 ); ?>>
									<?php esc_html_e( 'Automatically sync customers when bookings are created', 'bkx-quickbooks' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-Sync Invoices', 'bkx-quickbooks' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="bkx_qb_auto_sync_invoices" value="1"
										<?php checked( get_option( 'bkx_qb_auto_sync_invoices' ), 1 ); ?>>
									<?php esc_html_e( 'Automatically create invoices in QuickBooks for new bookings', 'bkx-quickbooks' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Auto-Sync Payments', 'bkx-quickbooks' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="bkx_qb_auto_sync_payments" value="1"
										<?php checked( get_option( 'bkx_qb_auto_sync_payments' ), 1 ); ?>>
									<?php esc_html_e( 'Automatically record payments when bookings are paid', 'bkx-quickbooks' ); ?>
								</label>
							</td>
						</tr>
					</table>

					<?php submit_button(); ?>
				</form>
			</div>

			<div class="bkx-qb-card">
				<h2><?php esc_html_e( 'Account Mapping', 'bkx-quickbooks' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Configure which QuickBooks accounts to use for booking revenue.', 'bkx-quickbooks' ); ?>
				</p>

				<form method="post" action="options.php">
					<?php settings_fields( 'bkx_quickbooks' ); ?>

					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="bkx_qb_default_income_account"><?php esc_html_e( 'Income Account', 'bkx-quickbooks' ); ?></label>
							</th>
							<td>
								<input type="text" id="bkx_qb_default_income_account" name="bkx_qb_default_income_account"
									   value="<?php echo esc_attr( get_option( 'bkx_qb_default_income_account' ) ); ?>"
									   class="regular-text">
								<p class="description">
									<?php esc_html_e( 'QuickBooks Account ID for booking revenue. Leave blank to auto-detect.', 'bkx-quickbooks' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bkx_qb_default_tax_code"><?php esc_html_e( 'Tax Code', 'bkx-quickbooks' ); ?></label>
							</th>
							<td>
								<input type="text" id="bkx_qb_default_tax_code" name="bkx_qb_default_tax_code"
									   value="<?php echo esc_attr( get_option( 'bkx_qb_default_tax_code' ) ); ?>"
									   class="regular-text">
								<p class="description">
									<?php esc_html_e( 'Optional tax code to apply to invoices.', 'bkx-quickbooks' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button(); ?>
				</form>
			</div>

		<?php elseif ( 'sync' === $active_tab ) : ?>
			<!-- Sync Tab -->
			<div class="bkx-qb-card">
				<h2><?php esc_html_e( 'Sync Status', 'bkx-quickbooks' ); ?></h2>

				<?php if ( ! $is_connected ) : ?>
					<div class="notice notice-warning inline">
						<p><?php esc_html_e( 'Please connect to QuickBooks first.', 'bkx-quickbooks' ); ?></p>
					</div>
				<?php else : ?>
					<div class="bkx-qb-sync-stats" id="bkx-qb-sync-stats">
						<div class="bkx-qb-stat">
							<span class="bkx-qb-stat-value" id="stat-customers">-</span>
							<span class="bkx-qb-stat-label"><?php esc_html_e( 'Customers Synced', 'bkx-quickbooks' ); ?></span>
						</div>
						<div class="bkx-qb-stat">
							<span class="bkx-qb-stat-value" id="stat-invoices">-</span>
							<span class="bkx-qb-stat-label"><?php esc_html_e( 'Invoices Synced', 'bkx-quickbooks' ); ?></span>
						</div>
						<div class="bkx-qb-stat">
							<span class="bkx-qb-stat-value" id="stat-payments">-</span>
							<span class="bkx-qb-stat-label"><?php esc_html_e( 'Payments Synced', 'bkx-quickbooks' ); ?></span>
						</div>
						<div class="bkx-qb-stat">
							<span class="bkx-qb-stat-value" id="stat-pending">-</span>
							<span class="bkx-qb-stat-label"><?php esc_html_e( 'Pending', 'bkx-quickbooks' ); ?></span>
						</div>
						<div class="bkx-qb-stat">
							<span class="bkx-qb-stat-value bkx-qb-stat-error" id="stat-failed">-</span>
							<span class="bkx-qb-stat-label"><?php esc_html_e( 'Failed', 'bkx-quickbooks' ); ?></span>
						</div>
					</div>

					<div class="bkx-qb-sync-actions">
						<h3><?php esc_html_e( 'Manual Sync', 'bkx-quickbooks' ); ?></h3>
						<p><?php esc_html_e( 'Manually sync data to QuickBooks. This will sync all customers, services, and bookings.', 'bkx-quickbooks' ); ?></p>

						<div class="bkx-qb-button-group">
							<button type="button" class="button" id="bkx-qb-sync-customers">
								<span class="dashicons dashicons-groups"></span>
								<?php esc_html_e( 'Sync Customers', 'bkx-quickbooks' ); ?>
							</button>
							<button type="button" class="button" id="bkx-qb-sync-products">
								<span class="dashicons dashicons-archive"></span>
								<?php esc_html_e( 'Sync Services', 'bkx-quickbooks' ); ?>
							</button>
							<button type="button" class="button" id="bkx-qb-sync-invoices">
								<span class="dashicons dashicons-media-text"></span>
								<?php esc_html_e( 'Sync Invoices', 'bkx-quickbooks' ); ?>
							</button>
							<button type="button" class="button button-primary" id="bkx-qb-sync-all">
								<span class="dashicons dashicons-update"></span>
								<?php esc_html_e( 'Sync All', 'bkx-quickbooks' ); ?>
							</button>
						</div>

						<div id="bkx-qb-sync-progress" class="bkx-qb-sync-progress" style="display: none;">
							<div class="bkx-qb-progress-bar">
								<div class="bkx-qb-progress-fill"></div>
							</div>
							<p class="bkx-qb-progress-message"></p>
						</div>
					</div>
				<?php endif; ?>
			</div>

		<?php elseif ( 'logs' === $active_tab ) : ?>
			<!-- Logs Tab -->
			<div class="bkx-qb-card">
				<h2><?php esc_html_e( 'Sync Logs', 'bkx-quickbooks' ); ?></h2>

				<table class="wp-list-table widefat fixed striped" id="bkx-qb-logs-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'bkx-quickbooks' ); ?></th>
							<th><?php esc_html_e( 'Type', 'bkx-quickbooks' ); ?></th>
							<th><?php esc_html_e( 'Entity ID', 'bkx-quickbooks' ); ?></th>
							<th><?php esc_html_e( 'QB ID', 'bkx-quickbooks' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bkx-quickbooks' ); ?></th>
							<th><?php esc_html_e( 'Message', 'bkx-quickbooks' ); ?></th>
						</tr>
					</thead>
					<tbody id="bkx-qb-logs-body">
						<tr>
							<td colspan="6"><?php esc_html_e( 'Loading...', 'bkx-quickbooks' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>
