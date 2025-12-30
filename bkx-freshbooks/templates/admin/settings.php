<?php
/**
 * FreshBooks Settings Tab.
 *
 * @package BookingX\FreshBooks
 */

defined( 'ABSPATH' ) || exit;

$addon     = \BookingX\FreshBooks\FreshBooksAddon::get_instance();
$oauth     = $addon->get_service( 'oauth' );
$settings  = get_option( 'bkx_freshbooks_settings', array() );
$connected = $addon->is_connected();
$auth_url  = $oauth->get_auth_url();
?>

<div class="bkx-freshbooks-settings">
	<!-- Connection Status -->
	<div class="bkx-connection-card">
		<h2><?php esc_html_e( 'FreshBooks Connection', 'bkx-freshbooks' ); ?></h2>

		<?php if ( $connected ) : ?>
			<div class="bkx-connection-status bkx-connected">
				<span class="dashicons dashicons-yes-alt"></span>
				<div class="bkx-connection-info">
					<strong><?php esc_html_e( 'Connected to FreshBooks', 'bkx-freshbooks' ); ?></strong>
					<?php if ( ! empty( $settings['account_id'] ) ) : ?>
						<p><?php printf( esc_html__( 'Account ID: %s', 'bkx-freshbooks' ), esc_html( $settings['account_id'] ) ); ?></p>
					<?php endif; ?>
				</div>
				<button type="button" class="button" id="bkx-freshbooks-disconnect">
					<?php esc_html_e( 'Disconnect', 'bkx-freshbooks' ); ?>
				</button>
			</div>
		<?php else : ?>
			<div class="bkx-connection-status bkx-disconnected">
				<span class="dashicons dashicons-warning"></span>
				<div class="bkx-connection-info">
					<strong><?php esc_html_e( 'Not Connected', 'bkx-freshbooks' ); ?></strong>
					<p><?php esc_html_e( 'Connect your FreshBooks account to start syncing.', 'bkx-freshbooks' ); ?></p>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Settings Form -->
	<form id="bkx-freshbooks-settings-form" method="post">
		<?php wp_nonce_field( 'bkx_freshbooks_settings', 'bkx_freshbooks_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="bkx-fb-enabled"><?php esc_html_e( 'Enable Integration', 'bkx-freshbooks' ); ?></label>
				</th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" id="bkx-fb-enabled" name="enabled" value="1"
							   <?php checked( ! empty( $settings['enabled'] ) ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="bkx-fb-client-id"><?php esc_html_e( 'Client ID', 'bkx-freshbooks' ); ?></label>
				</th>
				<td>
					<input type="text" id="bkx-fb-client-id" name="client_id"
						   value="<?php echo esc_attr( $settings['client_id'] ?? '' ); ?>"
						   class="regular-text">
					<p class="description">
						<?php
						printf(
							/* translators: %s: FreshBooks Developer portal link */
							esc_html__( 'Get your credentials from the %s.', 'bkx-freshbooks' ),
							'<a href="https://my.freshbooks.com/#/developer" target="_blank">FreshBooks Developer Portal</a>'
						);
						?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="bkx-fb-client-secret"><?php esc_html_e( 'Client Secret', 'bkx-freshbooks' ); ?></label>
				</th>
				<td>
					<input type="password" id="bkx-fb-client-secret" name="client_secret"
						   value="<?php echo esc_attr( $settings['client_secret'] ?? '' ); ?>"
						   class="regular-text">
				</td>
			</tr>

			<?php if ( ! $connected && ! empty( $settings['client_id'] ) && ! empty( $settings['client_secret'] ) ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Connect', 'bkx-freshbooks' ); ?></th>
					<td>
						<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary">
							<?php esc_html_e( 'Connect to FreshBooks', 'bkx-freshbooks' ); ?>
						</a>
					</td>
				</tr>
			<?php endif; ?>
		</table>

		<?php if ( $connected ) : ?>
			<h3><?php esc_html_e( 'Sync Settings', 'bkx-freshbooks' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto Sync', 'bkx-freshbooks' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="auto_sync" value="1"
								   <?php checked( ! empty( $settings['auto_sync'] ) ); ?>>
							<?php esc_html_e( 'Automatically sync bookings to FreshBooks', 'bkx-freshbooks' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Sync Options', 'bkx-freshbooks' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="sync_clients" value="1"
									   <?php checked( ! empty( $settings['sync_clients'] ) ); ?>>
								<?php esc_html_e( 'Sync clients', 'bkx-freshbooks' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="sync_invoices" value="1"
									   <?php checked( ! empty( $settings['sync_invoices'] ) ); ?>>
								<?php esc_html_e( 'Sync invoices', 'bkx-freshbooks' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="sync_payments" value="1"
									   <?php checked( ! empty( $settings['sync_payments'] ) ); ?>>
								<?php esc_html_e( 'Sync payments', 'bkx-freshbooks' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Sync Triggers', 'bkx-freshbooks' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="sync_on_complete" value="1"
									   <?php checked( ! empty( $settings['sync_on_complete'] ) ); ?>>
								<?php esc_html_e( 'Sync when booking is completed', 'bkx-freshbooks' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="sync_on_payment" value="1"
									   <?php checked( ! empty( $settings['sync_on_payment'] ) ); ?>>
								<?php esc_html_e( 'Sync when payment is received', 'bkx-freshbooks' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="bkx-fb-due-days"><?php esc_html_e( 'Invoice Due Days', 'bkx-freshbooks' ); ?></label>
					</th>
					<td>
						<input type="number" id="bkx-fb-due-days" name="invoice_due_days"
							   value="<?php echo esc_attr( $settings['invoice_due_days'] ?? 14 ); ?>"
							   min="0" max="365" class="small-text">
						<p class="description">
							<?php esc_html_e( 'Number of days until invoice is due.', 'bkx-freshbooks' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Email Invoices', 'bkx-freshbooks' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="send_invoice_email" value="1"
								   <?php checked( ! empty( $settings['send_invoice_email'] ) ); ?>>
							<?php esc_html_e( 'Automatically email invoices to customers', 'bkx-freshbooks' ); ?>
						</label>
					</td>
				</tr>
			</table>
		<?php endif; ?>

		<p class="submit">
			<button type="submit" class="button button-primary" id="bkx-save-settings">
				<?php esc_html_e( 'Save Settings', 'bkx-freshbooks' ); ?>
			</button>
			<span class="spinner"></span>
		</p>
	</form>
</div>
