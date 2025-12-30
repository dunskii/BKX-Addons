<?php
/**
 * API Settings Template.
 *
 * @package BookingX\EnterpriseAPI
 */

defined( 'ABSPATH' ) || exit;

// Handle save.
if ( isset( $_POST['bkx_save_api_settings'] ) && check_admin_referer( 'bkx_api_settings' ) ) {
	$settings = array(
		'enabled'                  => isset( $_POST['enabled'] ),
		'default_rate_limit'       => absint( $_POST['default_rate_limit'] ),
		'rate_limit_window'        => absint( $_POST['rate_limit_window'] ),
		'enable_oauth'             => isset( $_POST['enable_oauth'] ),
		'enable_api_keys'          => isset( $_POST['enable_api_keys'] ),
		'enable_graphql'           => isset( $_POST['enable_graphql'] ),
		'enable_webhooks'          => isset( $_POST['enable_webhooks'] ),
		'enable_logging'           => isset( $_POST['enable_logging'] ),
		'log_request_body'         => isset( $_POST['log_request_body'] ),
		'log_response_body'        => isset( $_POST['log_response_body'] ),
		'log_retention_days'       => absint( $_POST['log_retention_days'] ),
		'oauth_token_lifetime'     => absint( $_POST['oauth_token_lifetime'] ),
		'oauth_refresh_lifetime'   => absint( $_POST['oauth_refresh_lifetime'] ),
		'cors_allowed_origins'     => array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( $_POST['cors_allowed_origins'] ) ) ) ),
		'require_https'            => isset( $_POST['require_https'] ),
		'sandbox_mode'             => isset( $_POST['sandbox_mode'] ),
		'developer_portal_enabled' => isset( $_POST['developer_portal_enabled'] ),
	);

	update_option( 'bkx_enterprise_api_settings', $settings );
	$success = true;
}

$settings = get_option( 'bkx_enterprise_api_settings', array() );
$defaults = array(
	'enabled'                  => true,
	'default_rate_limit'       => 1000,
	'rate_limit_window'        => 3600,
	'enable_oauth'             => true,
	'enable_api_keys'          => true,
	'enable_graphql'           => true,
	'enable_webhooks'          => true,
	'enable_logging'           => true,
	'log_request_body'         => false,
	'log_response_body'        => false,
	'log_retention_days'       => 30,
	'oauth_token_lifetime'     => 3600,
	'oauth_refresh_lifetime'   => 2592000,
	'cors_allowed_origins'     => array( '*' ),
	'require_https'            => true,
	'sandbox_mode'             => false,
	'developer_portal_enabled' => true,
);
$settings = wp_parse_args( $settings, $defaults );
?>
<div class="bkx-api-settings">
	<h2><?php esc_html_e( 'API Settings', 'bkx-enterprise-api' ); ?></h2>

	<?php if ( ! empty( $success ) ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Settings saved successfully.', 'bkx-enterprise-api' ); ?></p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'bkx_api_settings' ); ?>

		<div class="bkx-settings-section">
			<h3><?php esc_html_e( 'General', 'bkx-enterprise-api' ); ?></h3>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Enable API', 'bkx-enterprise-api' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>>
							<?php esc_html_e( 'Enable the BookingX REST API', 'bkx-enterprise-api' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Require HTTPS', 'bkx-enterprise-api' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="require_https" value="1" <?php checked( $settings['require_https'] ); ?>>
							<?php esc_html_e( 'Require HTTPS for API requests', 'bkx-enterprise-api' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Sandbox Mode', 'bkx-enterprise-api' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="sandbox_mode" value="1" <?php checked( $settings['sandbox_mode'] ); ?>>
							<?php esc_html_e( 'Enable sandbox mode for testing', 'bkx-enterprise-api' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'In sandbox mode, API actions do not affect real data.', 'bkx-enterprise-api' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="bkx-settings-section">
			<h3><?php esc_html_e( 'Features', 'bkx-enterprise-api' ); ?></h3>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Authentication Methods', 'bkx-enterprise-api' ); ?></th>
					<td>
						<label style="display: block; margin-bottom: 8px;">
							<input type="checkbox" name="enable_api_keys" value="1" <?php checked( $settings['enable_api_keys'] ); ?>>
							<?php esc_html_e( 'API Keys', 'bkx-enterprise-api' ); ?>
						</label>
						<label style="display: block;">
							<input type="checkbox" name="enable_oauth" value="1" <?php checked( $settings['enable_oauth'] ); ?>>
							<?php esc_html_e( 'OAuth 2.0', 'bkx-enterprise-api' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'GraphQL', 'bkx-enterprise-api' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enable_graphql" value="1" <?php checked( $settings['enable_graphql'] ); ?>>
							<?php esc_html_e( 'Enable GraphQL endpoint', 'bkx-enterprise-api' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Webhooks', 'bkx-enterprise-api' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enable_webhooks" value="1" <?php checked( $settings['enable_webhooks'] ); ?>>
							<?php esc_html_e( 'Enable webhook notifications', 'bkx-enterprise-api' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Developer Portal', 'bkx-enterprise-api' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="developer_portal_enabled" value="1" <?php checked( $settings['developer_portal_enabled'] ); ?>>
							<?php esc_html_e( 'Enable public API documentation portal', 'bkx-enterprise-api' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<div class="bkx-settings-section">
			<h3><?php esc_html_e( 'Rate Limiting', 'bkx-enterprise-api' ); ?></h3>
			<table class="form-table">
				<tr>
					<th><label for="default_rate_limit"><?php esc_html_e( 'Default Rate Limit', 'bkx-enterprise-api' ); ?></label></th>
					<td>
						<input type="number" name="default_rate_limit" id="default_rate_limit" min="100" class="small-text" value="<?php echo esc_attr( $settings['default_rate_limit'] ); ?>">
						<span><?php esc_html_e( 'requests per window', 'bkx-enterprise-api' ); ?></span>
					</td>
				</tr>
				<tr>
					<th><label for="rate_limit_window"><?php esc_html_e( 'Rate Limit Window', 'bkx-enterprise-api' ); ?></label></th>
					<td>
						<input type="number" name="rate_limit_window" id="rate_limit_window" min="60" class="small-text" value="<?php echo esc_attr( $settings['rate_limit_window'] ); ?>">
						<span><?php esc_html_e( 'seconds', 'bkx-enterprise-api' ); ?></span>
					</td>
				</tr>
			</table>
		</div>

		<div class="bkx-settings-section">
			<h3><?php esc_html_e( 'OAuth Settings', 'bkx-enterprise-api' ); ?></h3>
			<table class="form-table">
				<tr>
					<th><label for="oauth_token_lifetime"><?php esc_html_e( 'Access Token Lifetime', 'bkx-enterprise-api' ); ?></label></th>
					<td>
						<input type="number" name="oauth_token_lifetime" id="oauth_token_lifetime" min="300" class="small-text" value="<?php echo esc_attr( $settings['oauth_token_lifetime'] ); ?>">
						<span><?php esc_html_e( 'seconds', 'bkx-enterprise-api' ); ?></span>
						<p class="description"><?php esc_html_e( 'Default: 3600 (1 hour)', 'bkx-enterprise-api' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="oauth_refresh_lifetime"><?php esc_html_e( 'Refresh Token Lifetime', 'bkx-enterprise-api' ); ?></label></th>
					<td>
						<input type="number" name="oauth_refresh_lifetime" id="oauth_refresh_lifetime" min="3600" class="small-text" value="<?php echo esc_attr( $settings['oauth_refresh_lifetime'] ); ?>">
						<span><?php esc_html_e( 'seconds', 'bkx-enterprise-api' ); ?></span>
						<p class="description"><?php esc_html_e( 'Default: 2592000 (30 days)', 'bkx-enterprise-api' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="bkx-settings-section">
			<h3><?php esc_html_e( 'CORS Settings', 'bkx-enterprise-api' ); ?></h3>
			<table class="form-table">
				<tr>
					<th><label for="cors_allowed_origins"><?php esc_html_e( 'Allowed Origins', 'bkx-enterprise-api' ); ?></label></th>
					<td>
						<textarea name="cors_allowed_origins" id="cors_allowed_origins" class="large-text" rows="5"><?php echo esc_textarea( implode( "\n", $settings['cors_allowed_origins'] ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One origin per line. Use * to allow all origins.', 'bkx-enterprise-api' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="bkx-settings-section">
			<h3><?php esc_html_e( 'Logging', 'bkx-enterprise-api' ); ?></h3>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Enable Logging', 'bkx-enterprise-api' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enable_logging" value="1" <?php checked( $settings['enable_logging'] ); ?>>
							<?php esc_html_e( 'Log API requests', 'bkx-enterprise-api' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Log Details', 'bkx-enterprise-api' ); ?></th>
					<td>
						<label style="display: block; margin-bottom: 8px;">
							<input type="checkbox" name="log_request_body" value="1" <?php checked( $settings['log_request_body'] ); ?>>
							<?php esc_html_e( 'Log request body', 'bkx-enterprise-api' ); ?>
						</label>
						<label style="display: block;">
							<input type="checkbox" name="log_response_body" value="1" <?php checked( $settings['log_response_body'] ); ?>>
							<?php esc_html_e( 'Log response body', 'bkx-enterprise-api' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Warning: Logging bodies increases storage usage and may contain sensitive data.', 'bkx-enterprise-api' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="log_retention_days"><?php esc_html_e( 'Log Retention', 'bkx-enterprise-api' ); ?></label></th>
					<td>
						<input type="number" name="log_retention_days" id="log_retention_days" min="1" max="365" class="small-text" value="<?php echo esc_attr( $settings['log_retention_days'] ); ?>">
						<span><?php esc_html_e( 'days', 'bkx-enterprise-api' ); ?></span>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" name="bkx_save_api_settings" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-enterprise-api' ); ?>
			</button>
		</p>
	</form>
</div>

<style>
.bkx-settings-section {
	background: #fff;
	border: 1px solid #e5e7eb;
	border-radius: 8px;
	padding: 20px;
	margin-bottom: 20px;
}
.bkx-settings-section h3 {
	margin-top: 0;
	border-bottom: 1px solid #e5e7eb;
	padding-bottom: 10px;
}
</style>
