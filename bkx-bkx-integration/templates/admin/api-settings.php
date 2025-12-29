<?php
/**
 * API settings template.
 *
 * @package BookingX\BkxIntegration
 */

defined( 'ABSPATH' ) || exit;

$api_key    = get_option( 'bkx_bkx_api_key', '' );
$api_secret = get_option( 'bkx_bkx_api_secret', '' );
$endpoint   = home_url( '/wp-json/bkx-integration/v1/' );
?>

<div class="bkx-bkx-api-settings">
	<div class="bkx-bkx-card">
		<h2><?php esc_html_e( 'Your API Credentials', 'bkx-bkx-integration' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Share these credentials with other BookingX sites that want to sync with this site.', 'bkx-bkx-integration' ); ?>
		</p>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'API Endpoint', 'bkx-bkx-integration' ); ?></th>
				<td>
					<code class="bkx-bkx-code"><?php echo esc_html( $endpoint ); ?></code>
					<button type="button" class="button button-small bkx-bkx-copy" data-copy="<?php echo esc_attr( $endpoint ); ?>">
						<?php esc_html_e( 'Copy', 'bkx-bkx-integration' ); ?>
					</button>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'API Key', 'bkx-bkx-integration' ); ?></th>
				<td>
					<code class="bkx-bkx-code" id="bkx-api-key-display"><?php echo esc_html( $api_key ); ?></code>
					<button type="button" class="button button-small bkx-bkx-copy" data-copy="<?php echo esc_attr( $api_key ); ?>">
						<?php esc_html_e( 'Copy', 'bkx-bkx-integration' ); ?>
					</button>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'API Secret', 'bkx-bkx-integration' ); ?></th>
				<td>
					<code class="bkx-bkx-code bkx-bkx-secret" id="bkx-api-secret-display">
						<span class="secret-hidden">••••••••••••••••••••••••</span>
						<span class="secret-visible" style="display: none;"><?php echo esc_html( $api_secret ); ?></span>
					</code>
					<button type="button" class="button button-small bkx-bkx-toggle-secret">
						<?php esc_html_e( 'Show', 'bkx-bkx-integration' ); ?>
					</button>
					<button type="button" class="button button-small bkx-bkx-copy" data-copy="<?php echo esc_attr( $api_secret ); ?>">
						<?php esc_html_e( 'Copy', 'bkx-bkx-integration' ); ?>
					</button>
				</td>
			</tr>
		</table>

		<p>
			<button type="button" class="button" id="bkx-bkx-regenerate-keys">
				<?php esc_html_e( 'Regenerate API Keys', 'bkx-bkx-integration' ); ?>
			</button>
			<span class="description" style="margin-left: 10px;">
				<?php esc_html_e( 'Warning: This will invalidate existing connections.', 'bkx-bkx-integration' ); ?>
			</span>
		</p>
	</div>

	<div class="bkx-bkx-card">
		<h2><?php esc_html_e( 'Setup Instructions', 'bkx-bkx-integration' ); ?></h2>

		<h4><?php esc_html_e( 'To connect another BookingX site to this one:', 'bkx-bkx-integration' ); ?></h4>
		<ol>
			<li><?php esc_html_e( 'Install and activate the BKX Integration add-on on the remote site.', 'bkx-bkx-integration' ); ?></li>
			<li><?php esc_html_e( 'Go to BKX Integration > Remote Sites on the remote site.', 'bkx-bkx-integration' ); ?></li>
			<li><?php esc_html_e( 'Click "Add Remote Site" and enter this site\'s URL and API credentials.', 'bkx-bkx-integration' ); ?></li>
			<li><?php esc_html_e( 'Click "Test Connection" to verify the setup.', 'bkx-bkx-integration' ); ?></li>
			<li><?php esc_html_e( 'Configure sync options (bookings, availability, customers).', 'bkx-bkx-integration' ); ?></li>
		</ol>

		<h4><?php esc_html_e( 'API Endpoints:', 'bkx-bkx-integration' ); ?></h4>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Endpoint', 'bkx-bkx-integration' ); ?></th>
					<th><?php esc_html_e( 'Method', 'bkx-bkx-integration' ); ?></th>
					<th><?php esc_html_e( 'Description', 'bkx-bkx-integration' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>/ping</code></td>
					<td>GET</td>
					<td><?php esc_html_e( 'Test connection and get site info.', 'bkx-bkx-integration' ); ?></td>
				</tr>
				<tr>
					<td><code>/booking</code></td>
					<td>POST, PUT, DELETE</td>
					<td><?php esc_html_e( 'Create, update, or delete bookings.', 'bkx-bkx-integration' ); ?></td>
				</tr>
				<tr>
					<td><code>/availability</code></td>
					<td>POST</td>
					<td><?php esc_html_e( 'Sync availability data.', 'bkx-bkx-integration' ); ?></td>
				</tr>
				<tr>
					<td><code>/availability/check</code></td>
					<td>GET</td>
					<td><?php esc_html_e( 'Check availability for a date.', 'bkx-bkx-integration' ); ?></td>
				</tr>
				<tr>
					<td><code>/customer</code></td>
					<td>POST, PUT</td>
					<td><?php esc_html_e( 'Create or update customers.', 'bkx-bkx-integration' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
