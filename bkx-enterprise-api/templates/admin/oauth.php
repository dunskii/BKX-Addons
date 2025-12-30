<?php
/**
 * OAuth Clients Template.
 *
 * @package BookingX\EnterpriseAPI
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$clients = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}bkx_oauth_clients ORDER BY created_at DESC"
);
?>
<div class="bkx-oauth-clients">
	<div class="bkx-section-header">
		<h2><?php esc_html_e( 'OAuth Clients', 'bkx-enterprise-api' ); ?></h2>
		<button type="button" class="button button-primary" id="bkx-create-client-btn">
			<span class="dashicons dashicons-plus-alt2"></span>
			<?php esc_html_e( 'Create OAuth Client', 'bkx-enterprise-api' ); ?>
		</button>
	</div>

	<p class="description">
		<?php esc_html_e( 'OAuth clients allow third-party applications to access the API on behalf of users. Supports OAuth 2.0 with PKCE.', 'bkx-enterprise-api' ); ?>
	</p>

	<!-- Create Client Modal -->
	<div id="bkx-create-client-modal" class="bkx-modal" style="display: none;">
		<div class="bkx-modal-content">
			<span class="bkx-modal-close">&times;</span>
			<h3><?php esc_html_e( 'Create OAuth Client', 'bkx-enterprise-api' ); ?></h3>
			<form id="bkx-create-client-form" method="post">
				<?php wp_nonce_field( 'bkx_create_oauth_client' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="client_name"><?php esc_html_e( 'Application Name', 'bkx-enterprise-api' ); ?></label></th>
						<td>
							<input type="text" name="name" id="client_name" class="regular-text" required>
						</td>
					</tr>
					<tr>
						<th><label for="client_description"><?php esc_html_e( 'Description', 'bkx-enterprise-api' ); ?></label></th>
						<td>
							<textarea name="description" id="client_description" class="large-text" rows="3"></textarea>
						</td>
					</tr>
					<tr>
						<th><label for="redirect_uri"><?php esc_html_e( 'Redirect URI', 'bkx-enterprise-api' ); ?></label></th>
						<td>
							<input type="url" name="redirect_uri" id="redirect_uri" class="regular-text" required>
							<p class="description"><?php esc_html_e( 'Comma-separated URIs for OAuth callback.', 'bkx-enterprise-api' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Grant Types', 'bkx-enterprise-api' ); ?></th>
						<td>
							<label><input type="checkbox" name="grant_types[]" value="authorization_code" checked> <?php esc_html_e( 'Authorization Code', 'bkx-enterprise-api' ); ?></label><br>
							<label><input type="checkbox" name="grant_types[]" value="refresh_token" checked> <?php esc_html_e( 'Refresh Token', 'bkx-enterprise-api' ); ?></label><br>
							<label><input type="checkbox" name="grant_types[]" value="client_credentials"> <?php esc_html_e( 'Client Credentials', 'bkx-enterprise-api' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><label for="scope"><?php esc_html_e( 'Scope', 'bkx-enterprise-api' ); ?></label></th>
						<td>
							<input type="text" name="scope" id="scope" class="regular-text" value="read write">
							<p class="description"><?php esc_html_e( 'Space-separated list of allowed scopes.', 'bkx-enterprise-api' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" name="bkx_create_oauth_client" class="button button-primary"><?php esc_html_e( 'Create Client', 'bkx-enterprise-api' ); ?></button>
					<button type="button" class="button bkx-modal-close"><?php esc_html_e( 'Cancel', 'bkx-enterprise-api' ); ?></button>
				</p>
			</form>
		</div>
	</div>

	<!-- Clients Table -->
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 200px;"><?php esc_html_e( 'Application', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 250px;"><?php esc_html_e( 'Client ID', 'bkx-enterprise-api' ); ?></th>
				<th><?php esc_html_e( 'Grant Types', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 150px;"><?php esc_html_e( 'Created', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 80px;"><?php esc_html_e( 'Status', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 100px;"><?php esc_html_e( 'Actions', 'bkx-enterprise-api' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $clients ) ) : ?>
				<tr>
					<td colspan="6"><?php esc_html_e( 'No OAuth clients found.', 'bkx-enterprise-api' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $clients as $client ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $client->name ); ?></strong>
							<?php if ( $client->description ) : ?>
								<br><small><?php echo esc_html( $client->description ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<code class="bkx-client-id"><?php echo esc_html( $client->client_id ); ?></code>
							<button type="button" class="button button-small bkx-copy-btn" data-copy="<?php echo esc_attr( $client->client_id ); ?>">
								<span class="dashicons dashicons-clipboard"></span>
							</button>
						</td>
						<td>
							<?php
							$grants = explode( ',', $client->grant_types );
							foreach ( $grants as $grant ) {
								echo '<span class="bkx-permission-badge">' . esc_html( str_replace( '_', ' ', ucfirst( trim( $grant ) ) ) ) . '</span> ';
							}
							?>
						</td>
						<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $client->created_at ) ) ); ?></td>
						<td>
							<?php if ( $client->is_active ) : ?>
								<span class="bkx-status-badge bkx-status-active"><?php esc_html_e( 'Active', 'bkx-enterprise-api' ); ?></span>
							<?php else : ?>
								<span class="bkx-status-badge bkx-status-inactive"><?php esc_html_e( 'Inactive', 'bkx-enterprise-api' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<button type="button" class="button button-small"><?php esc_html_e( 'Edit', 'bkx-enterprise-api' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- OAuth Endpoints Info -->
	<div class="bkx-oauth-endpoints">
		<h3><?php esc_html_e( 'OAuth Endpoints', 'bkx-enterprise-api' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Authorization URL', 'bkx-enterprise-api' ); ?></th>
				<td><code><?php echo esc_url( rest_url( 'bookingx/v1/oauth/authorize' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Token URL', 'bkx-enterprise-api' ); ?></th>
				<td><code><?php echo esc_url( rest_url( 'bookingx/v1/oauth/token' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Revoke URL', 'bkx-enterprise-api' ); ?></th>
				<td><code><?php echo esc_url( rest_url( 'bookingx/v1/oauth/revoke' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Introspect URL', 'bkx-enterprise-api' ); ?></th>
				<td><code><?php echo esc_url( rest_url( 'bookingx/v1/oauth/introspect' ) ); ?></code></td>
			</tr>
		</table>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var modal = document.getElementById('bkx-create-client-modal');

	document.getElementById('bkx-create-client-btn').addEventListener('click', function() {
		modal.style.display = 'flex';
	});

	document.querySelectorAll('.bkx-modal-close').forEach(function(btn) {
		btn.addEventListener('click', function() {
			modal.style.display = 'none';
		});
	});

	document.querySelectorAll('.bkx-copy-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var text = this.dataset.copy;
			navigator.clipboard.writeText(text);
			this.innerHTML = '<span class="dashicons dashicons-yes"></span>';
			var that = this;
			setTimeout(function() {
				that.innerHTML = '<span class="dashicons dashicons-clipboard"></span>';
			}, 2000);
		});
	});
});
</script>
