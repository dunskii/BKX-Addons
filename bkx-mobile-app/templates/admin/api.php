<?php
/**
 * API Keys Management.
 *
 * @package BookingX\MobileApp
 */

defined( 'ABSPATH' ) || exit;

$addon       = \BookingX\MobileApp\MobileAppAddon::get_instance();
$api_manager = $addon->get_service( 'api_manager' );
$api_keys    = $api_manager->get_api_keys();

// Handle form submissions.
if ( isset( $_POST['bkx_create_api_key'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bkx_create_api_key' ) ) {
	$key_name = sanitize_text_field( wp_unslash( $_POST['key_name'] ?? '' ) );
	if ( ! empty( $key_name ) ) {
		$new_key = $api_manager->create_api_key( $key_name );
		if ( ! is_wp_error( $new_key ) ) {
			$api_keys = $api_manager->get_api_keys();
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<strong><?php esc_html_e( 'API Key Created!', 'bkx-mobile-app' ); ?></strong>
					<?php esc_html_e( 'Copy this key now - it will not be shown again:', 'bkx-mobile-app' ); ?>
				</p>
				<p><code class="bkx-new-key"><?php echo esc_html( $new_key['secret_key'] ); ?></code></p>
			</div>
			<?php
		}
	}
}

if ( isset( $_POST['bkx_revoke_api_key'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'bkx_revoke_api_key' ) ) {
	$key_id = absint( $_POST['key_id'] ?? 0 );
	if ( $key_id ) {
		$api_manager->revoke_api_key( $key_id );
		$api_keys = $api_manager->get_api_keys();
	}
}
?>

<div class="bkx-api-keys">
	<!-- Create New Key -->
	<div class="bkx-card">
		<h3><?php esc_html_e( 'Create New API Key', 'bkx-mobile-app' ); ?></h3>
		<form method="post" class="bkx-create-key-form">
			<?php wp_nonce_field( 'bkx_create_api_key' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="key_name"><?php esc_html_e( 'Key Name', 'bkx-mobile-app' ); ?></label>
					</th>
					<td>
						<input type="text" id="key_name" name="key_name" class="regular-text"
							   placeholder="<?php esc_attr_e( 'e.g., iOS Production App', 'bkx-mobile-app' ); ?>" required>
						<p class="description">
							<?php esc_html_e( 'A descriptive name to identify this key.', 'bkx-mobile-app' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="bkx_create_api_key" class="button button-primary">
					<span class="dashicons dashicons-admin-network"></span>
					<?php esc_html_e( 'Generate API Key', 'bkx-mobile-app' ); ?>
				</button>
			</p>
		</form>
	</div>

	<!-- Existing Keys -->
	<div class="bkx-card">
		<h3><?php esc_html_e( 'Active API Keys', 'bkx-mobile-app' ); ?></h3>

		<?php if ( empty( $api_keys ) ) : ?>
			<p class="bkx-no-keys">
				<?php esc_html_e( 'No API keys have been created yet.', 'bkx-mobile-app' ); ?>
			</p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'bkx-mobile-app' ); ?></th>
						<th><?php esc_html_e( 'Key Prefix', 'bkx-mobile-app' ); ?></th>
						<th><?php esc_html_e( 'Created', 'bkx-mobile-app' ); ?></th>
						<th><?php esc_html_e( 'Last Used', 'bkx-mobile-app' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-mobile-app' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-mobile-app' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $api_keys as $key ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $key->key_name ); ?></strong>
							</td>
							<td>
								<code><?php echo esc_html( substr( $key->api_key, 0, 12 ) . '...' ); ?></code>
							</td>
							<td>
								<?php echo esc_html( human_time_diff( strtotime( $key->created_at ), current_time( 'timestamp' ) ) ); ?>
								<?php esc_html_e( 'ago', 'bkx-mobile-app' ); ?>
							</td>
							<td>
								<?php if ( $key->last_used ) : ?>
									<?php echo esc_html( human_time_diff( strtotime( $key->last_used ), current_time( 'timestamp' ) ) ); ?>
									<?php esc_html_e( 'ago', 'bkx-mobile-app' ); ?>
								<?php else : ?>
									<span class="bkx-never-used"><?php esc_html_e( 'Never', 'bkx-mobile-app' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $key->is_active ) : ?>
									<span class="bkx-status-active"><?php esc_html_e( 'Active', 'bkx-mobile-app' ); ?></span>
								<?php else : ?>
									<span class="bkx-status-inactive"><?php esc_html_e( 'Revoked', 'bkx-mobile-app' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( $key->is_active ) : ?>
									<form method="post" style="display:inline;"
										  onsubmit="return confirm('<?php esc_attr_e( 'Are you sure you want to revoke this API key? This action cannot be undone.', 'bkx-mobile-app' ); ?>');">
										<?php wp_nonce_field( 'bkx_revoke_api_key' ); ?>
										<input type="hidden" name="key_id" value="<?php echo esc_attr( $key->id ); ?>">
										<button type="submit" name="bkx_revoke_api_key" class="button button-small button-link-delete">
											<?php esc_html_e( 'Revoke', 'bkx-mobile-app' ); ?>
										</button>
									</form>
								<?php else : ?>
									<span class="bkx-revoked"><?php esc_html_e( 'Revoked', 'bkx-mobile-app' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<!-- API Documentation -->
	<div class="bkx-card">
		<h3><?php esc_html_e( 'API Authentication', 'bkx-mobile-app' ); ?></h3>
		<p><?php esc_html_e( 'To authenticate API requests, include the API key in the request header:', 'bkx-mobile-app' ); ?></p>
		<pre><code>Authorization: Bearer YOUR_API_KEY</code></pre>
		<p><?php esc_html_e( 'Or use the X-API-Key header:', 'bkx-mobile-app' ); ?></p>
		<pre><code>X-API-Key: YOUR_API_KEY</code></pre>

		<h4><?php esc_html_e( 'JWT Token Authentication', 'bkx-mobile-app' ); ?></h4>
		<p><?php esc_html_e( 'For user-specific endpoints, first obtain a JWT token:', 'bkx-mobile-app' ); ?></p>
		<pre><code>POST /wp-json/bkx-mobile/v1/auth/login
{
    "username": "user@example.com",
    "password": "password"
}</code></pre>
		<p><?php esc_html_e( 'Then include the token in subsequent requests:', 'bkx-mobile-app' ); ?></p>
		<pre><code>Authorization: Bearer JWT_TOKEN</code></pre>
	</div>
</div>
