<?php
/**
 * Settings admin template.
 *
 * @package BookingX\APIBuilder
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_api_builder_settings', array() );
?>
<div class="bkx-settings-page">
	<h2><?php esc_html_e( 'API Builder Settings', 'bkx-api-builder' ); ?></h2>

	<form id="bkx-api-settings-form">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="api_namespace"><?php esc_html_e( 'API Namespace', 'bkx-api-builder' ); ?></label>
				</th>
				<td>
					<input type="text" name="api_namespace" id="api_namespace"
						   value="<?php echo esc_attr( $settings['api_namespace'] ?? 'bkx-custom/v1' ); ?>"
						   class="regular-text">
					<p class="description">
						<?php esc_html_e( 'The namespace for custom endpoints (e.g., bkx-custom/v1)', 'bkx-api-builder' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Rate Limiting', 'bkx-api-builder' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="number" name="default_rate_limit" id="default_rate_limit"
								   value="<?php echo esc_attr( $settings['default_rate_limit'] ?? 1000 ); ?>"
								   min="0" class="small-text">
							<?php esc_html_e( 'requests per', 'bkx-api-builder' ); ?>
						</label>
						<select name="rate_limit_window" id="rate_limit_window">
							<option value="60" <?php selected( $settings['rate_limit_window'] ?? 3600, 60 ); ?>><?php esc_html_e( 'minute', 'bkx-api-builder' ); ?></option>
							<option value="3600" <?php selected( $settings['rate_limit_window'] ?? 3600, 3600 ); ?>><?php esc_html_e( 'hour', 'bkx-api-builder' ); ?></option>
							<option value="86400" <?php selected( $settings['rate_limit_window'] ?? 3600, 86400 ); ?>><?php esc_html_e( 'day', 'bkx-api-builder' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Set to 0 to disable global rate limiting', 'bkx-api-builder' ); ?></p>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Security', 'bkx-api-builder' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="require_https" value="1"
								   <?php checked( $settings['require_https'] ?? true ); ?>>
							<?php esc_html_e( 'Require HTTPS for API requests', 'bkx-api-builder' ); ?>
						</label>
						<br><br>
						<label>
							<input type="checkbox" name="enable_cors" value="1"
								   <?php checked( $settings['enable_cors'] ?? true ); ?>>
							<?php esc_html_e( 'Enable CORS (Cross-Origin Resource Sharing)', 'bkx-api-builder' ); ?>
						</label>
						<br>
						<label for="allowed_origins">
							<?php esc_html_e( 'Allowed Origins:', 'bkx-api-builder' ); ?>
							<input type="text" name="allowed_origins" id="allowed_origins"
								   value="<?php echo esc_attr( $settings['allowed_origins'] ?? '*' ); ?>"
								   class="regular-text">
						</label>
						<p class="description"><?php esc_html_e( 'Use * to allow all origins, or specify domains separated by commas', 'bkx-api-builder' ); ?></p>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Request Logging', 'bkx-api-builder' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="enable_logging" value="1"
								   <?php checked( $settings['enable_logging'] ?? true ); ?>>
							<?php esc_html_e( 'Enable request logging', 'bkx-api-builder' ); ?>
						</label>
						<br><br>
						<label>
							<?php esc_html_e( 'Keep logs for:', 'bkx-api-builder' ); ?>
							<input type="number" name="log_retention_days" id="log_retention_days"
								   value="<?php echo esc_attr( $settings['log_retention_days'] ?? 30 ); ?>"
								   min="1" max="365" class="small-text">
							<?php esc_html_e( 'days', 'bkx-api-builder' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Documentation', 'bkx-api-builder' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="enable_documentation" value="1"
								   <?php checked( $settings['enable_documentation'] ?? true ); ?>>
							<?php esc_html_e( 'Enable documentation endpoint', 'bkx-api-builder' ); ?>
						</label>
						<br><br>
						<label>
							<input type="checkbox" name="documentation_public" value="1"
								   <?php checked( $settings['documentation_public'] ?? false ); ?>>
							<?php esc_html_e( 'Make documentation publicly accessible (no authentication required)', 'bkx-api-builder' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>

			<tr>
				<th scope="row"><?php esc_html_e( 'Webhooks', 'bkx-api-builder' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="enable_webhooks" value="1"
								   <?php checked( $settings['enable_webhooks'] ?? true ); ?>>
							<?php esc_html_e( 'Enable webhooks', 'bkx-api-builder' ); ?>
						</label>
						<br><br>
						<label>
							<?php esc_html_e( 'Retry failed webhooks:', 'bkx-api-builder' ); ?>
							<input type="number" name="webhook_retry_count" id="webhook_retry_count"
								   value="<?php echo esc_attr( $settings['webhook_retry_count'] ?? 3 ); ?>"
								   min="0" max="10" class="small-text">
							<?php esc_html_e( 'times', 'bkx-api-builder' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-api-builder' ); ?>
			</button>
		</p>
	</form>
</div>
