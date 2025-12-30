<?php
/**
 * MYOB Settings Tab.
 *
 * @package BookingX\MYOB
 */

defined( 'ABSPATH' ) || exit;

$addon     = \BookingX\MYOB\MYOBAddon::get_instance();
$oauth     = $addon->get_service( 'oauth' );
$settings  = get_option( 'bkx_myob_settings', array() );
$connected = $addon->is_connected();
$auth_url  = $oauth->get_auth_url();
?>

<div class="bkx-myob-settings">
	<!-- Connection Status -->
	<div class="bkx-myob-connection-card">
		<h2><?php esc_html_e( 'MYOB Connection', 'bkx-myob' ); ?></h2>

		<?php if ( $connected ) : ?>
			<div class="bkx-connection-status bkx-connected">
				<span class="dashicons dashicons-yes-alt"></span>
				<div class="bkx-connection-info">
					<strong><?php esc_html_e( 'Connected to MYOB', 'bkx-myob' ); ?></strong>
					<?php if ( ! empty( $settings['company_file_name'] ) ) : ?>
						<p><?php echo esc_html( $settings['company_file_name'] ); ?></p>
					<?php endif; ?>
				</div>
				<button type="button" class="button" id="bkx-myob-disconnect">
					<?php esc_html_e( 'Disconnect', 'bkx-myob' ); ?>
				</button>
			</div>
		<?php else : ?>
			<div class="bkx-connection-status bkx-disconnected">
				<span class="dashicons dashicons-warning"></span>
				<div class="bkx-connection-info">
					<strong><?php esc_html_e( 'Not Connected', 'bkx-myob' ); ?></strong>
					<p><?php esc_html_e( 'Connect your MYOB account to start syncing.', 'bkx-myob' ); ?></p>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Settings Form -->
	<form id="bkx-myob-settings-form" method="post">
		<?php wp_nonce_field( 'bkx_myob_settings', 'bkx_myob_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="bkx-myob-enabled"><?php esc_html_e( 'Enable Integration', 'bkx-myob' ); ?></label>
				</th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" id="bkx-myob-enabled" name="enabled" value="1"
							   <?php checked( ! empty( $settings['enabled'] ) ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="bkx-myob-api-type"><?php esc_html_e( 'MYOB Product', 'bkx-myob' ); ?></label>
				</th>
				<td>
					<select id="bkx-myob-api-type" name="api_type">
						<option value="essentials" <?php selected( $settings['api_type'] ?? '', 'essentials' ); ?>>
							<?php esc_html_e( 'MYOB Essentials', 'bkx-myob' ); ?>
						</option>
						<option value="accountright" <?php selected( $settings['api_type'] ?? '', 'accountright' ); ?>>
							<?php esc_html_e( 'MYOB AccountRight Live', 'bkx-myob' ); ?>
						</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Select the MYOB product you are using.', 'bkx-myob' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="bkx-myob-client-id"><?php esc_html_e( 'API Key', 'bkx-myob' ); ?></label>
				</th>
				<td>
					<input type="text" id="bkx-myob-client-id" name="client_id"
						   value="<?php echo esc_attr( $settings['client_id'] ?? '' ); ?>"
						   class="regular-text">
					<p class="description">
						<?php
						printf(
							/* translators: %s: MYOB Developer portal link */
							esc_html__( 'Get your API key from the %s.', 'bkx-myob' ),
							'<a href="https://developer.myob.com/" target="_blank">MYOB Developer Portal</a>'
						);
						?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="bkx-myob-client-secret"><?php esc_html_e( 'API Secret', 'bkx-myob' ); ?></label>
				</th>
				<td>
					<input type="password" id="bkx-myob-client-secret" name="client_secret"
						   value="<?php echo esc_attr( $settings['client_secret'] ?? '' ); ?>"
						   class="regular-text">
				</td>
			</tr>

			<?php if ( ! $connected && ! empty( $settings['client_id'] ) && ! empty( $settings['client_secret'] ) ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Connect', 'bkx-myob' ); ?></th>
					<td>
						<a href="<?php echo esc_url( $auth_url ); ?>" class="button button-primary">
							<?php esc_html_e( 'Connect to MYOB', 'bkx-myob' ); ?>
						</a>
					</td>
				</tr>
			<?php endif; ?>
		</table>

		<?php if ( $connected ) : ?>
			<h3><?php esc_html_e( 'Sync Settings', 'bkx-myob' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto Sync', 'bkx-myob' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="auto_sync" value="1"
								   <?php checked( ! empty( $settings['auto_sync'] ) ); ?>>
							<?php esc_html_e( 'Automatically sync bookings to MYOB', 'bkx-myob' ); ?>
						</label>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Sync Options', 'bkx-myob' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="sync_customers" value="1"
									   <?php checked( ! empty( $settings['sync_customers'] ) ); ?>>
								<?php esc_html_e( 'Sync customers', 'bkx-myob' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="sync_invoices" value="1"
									   <?php checked( ! empty( $settings['sync_invoices'] ) ); ?>>
								<?php esc_html_e( 'Sync invoices', 'bkx-myob' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="sync_payments" value="1"
									   <?php checked( ! empty( $settings['sync_payments'] ) ); ?>>
								<?php esc_html_e( 'Sync payments', 'bkx-myob' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Sync Triggers', 'bkx-myob' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="sync_on_complete" value="1"
									   <?php checked( ! empty( $settings['sync_on_complete'] ) ); ?>>
								<?php esc_html_e( 'Sync when booking is completed', 'bkx-myob' ); ?>
							</label><br>
							<label>
								<input type="checkbox" name="sync_on_payment" value="1"
									   <?php checked( ! empty( $settings['sync_on_payment'] ) ); ?>>
								<?php esc_html_e( 'Sync when payment is received', 'bkx-myob' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="bkx-myob-invoice-prefix"><?php esc_html_e( 'Invoice Prefix', 'bkx-myob' ); ?></label>
					</th>
					<td>
						<input type="text" id="bkx-myob-invoice-prefix" name="invoice_prefix"
							   value="<?php echo esc_attr( $settings['invoice_prefix'] ?? 'BKX-' ); ?>"
							   class="small-text">
						<p class="description">
							<?php esc_html_e( 'Prefix for invoice numbers (e.g., BKX-123)', 'bkx-myob' ); ?>
						</p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="bkx-myob-income-account"><?php esc_html_e( 'Income Account', 'bkx-myob' ); ?></label>
					</th>
					<td>
						<select id="bkx-myob-income-account" name="default_income_account">
							<option value=""><?php esc_html_e( '-- Select Account --', 'bkx-myob' ); ?></option>
						</select>
						<button type="button" class="button" id="bkx-load-accounts">
							<?php esc_html_e( 'Load Accounts', 'bkx-myob' ); ?>
						</button>
						<?php if ( ! empty( $settings['default_income_account'] ) ) : ?>
							<p class="description">
								<?php
								printf(
									/* translators: %s: account ID */
									esc_html__( 'Current: %s', 'bkx-myob' ),
									esc_html( $settings['default_income_account'] )
								);
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="bkx-myob-tax-code"><?php esc_html_e( 'Tax Code', 'bkx-myob' ); ?></label>
					</th>
					<td>
						<select id="bkx-myob-tax-code" name="default_tax_code">
							<option value=""><?php esc_html_e( '-- Select Tax Code --', 'bkx-myob' ); ?></option>
						</select>
						<button type="button" class="button" id="bkx-load-tax-codes">
							<?php esc_html_e( 'Load Tax Codes', 'bkx-myob' ); ?>
						</button>
						<?php if ( ! empty( $settings['default_tax_code'] ) ) : ?>
							<p class="description">
								<?php
								printf(
									/* translators: %s: tax code */
									esc_html__( 'Current: %s', 'bkx-myob' ),
									esc_html( $settings['default_tax_code'] )
								);
								?>
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
		<?php endif; ?>

		<p class="submit">
			<button type="submit" class="button button-primary" id="bkx-save-settings">
				<?php esc_html_e( 'Save Settings', 'bkx-myob' ); ?>
			</button>
			<span class="spinner"></span>
		</p>
	</form>
</div>
