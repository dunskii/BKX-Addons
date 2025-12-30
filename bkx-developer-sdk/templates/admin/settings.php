<?php
/**
 * Settings template.
 *
 * @package BookingX\DeveloperSDK
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_developer_sdk_settings', array() );

$defaults = array(
	'debug_mode'              => false,
	'enable_sandbox'          => true,
	'sandbox_prefix'          => 'bkx_sandbox_',
	'enable_code_generator'   => true,
	'enable_api_explorer'     => true,
	'enable_testing_tools'    => true,
	'enable_documentation'    => true,
	'api_explorer_cache_ttl'  => 3600,
	'test_data_retention'     => 7,
	'log_api_requests'        => true,
	'enable_cli'              => true,
);

$settings = wp_parse_args( $settings, $defaults );
?>

<form method="post" id="bkx-sdk-settings-form">
	<?php wp_nonce_field( 'bkx_developer_sdk_settings', 'bkx_sdk_nonce' ); ?>

	<h2><?php esc_html_e( 'General Settings', 'bkx-developer-sdk' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Debug Mode', 'bkx-developer-sdk' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="debug_mode" value="1" <?php checked( $settings['debug_mode'] ); ?>>
					<?php esc_html_e( 'Enable debug mode', 'bkx-developer-sdk' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Enables code playground and debug toolbar. Only enable on development sites.', 'bkx-developer-sdk' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'WP-CLI Commands', 'bkx-developer-sdk' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="enable_cli" value="1" <?php checked( $settings['enable_cli'] ); ?>>
					<?php esc_html_e( 'Enable WP-CLI commands', 'bkx-developer-sdk' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Register BookingX commands for WP-CLI (wp bkx generate, wp bkx test, etc).', 'bkx-developer-sdk' ); ?>
				</p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Feature Toggles', 'bkx-developer-sdk' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Code Generator', 'bkx-developer-sdk' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="enable_code_generator" value="1" <?php checked( $settings['enable_code_generator'] ); ?>>
					<?php esc_html_e( 'Enable code generator', 'bkx-developer-sdk' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'API Explorer', 'bkx-developer-sdk' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="enable_api_explorer" value="1" <?php checked( $settings['enable_api_explorer'] ); ?>>
					<?php esc_html_e( 'Enable API explorer', 'bkx-developer-sdk' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Testing Tools', 'bkx-developer-sdk' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="enable_testing_tools" value="1" <?php checked( $settings['enable_testing_tools'] ); ?>>
					<?php esc_html_e( 'Enable sandbox and test data tools', 'bkx-developer-sdk' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Documentation', 'bkx-developer-sdk' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="enable_documentation" value="1" <?php checked( $settings['enable_documentation'] ); ?>>
					<?php esc_html_e( 'Enable inline documentation', 'bkx-developer-sdk' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Sandbox Settings', 'bkx-developer-sdk' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Sandbox', 'bkx-developer-sdk' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="enable_sandbox" value="1" <?php checked( $settings['enable_sandbox'] ); ?>>
					<?php esc_html_e( 'Allow creating sandbox environments', 'bkx-developer-sdk' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="sandbox_prefix"><?php esc_html_e( 'Sandbox Prefix', 'bkx-developer-sdk' ); ?></label></th>
			<td>
				<input type="text" name="sandbox_prefix" id="sandbox_prefix" value="<?php echo esc_attr( $settings['sandbox_prefix'] ); ?>" class="regular-text">
				<p class="description"><?php esc_html_e( 'Prefix for sandbox identifiers.', 'bkx-developer-sdk' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="test_data_retention"><?php esc_html_e( 'Test Data Retention', 'bkx-developer-sdk' ); ?></label></th>
			<td>
				<input type="number" name="test_data_retention" id="test_data_retention" value="<?php echo esc_attr( $settings['test_data_retention'] ); ?>" min="1" max="30" class="small-text">
				<?php esc_html_e( 'days', 'bkx-developer-sdk' ); ?>
				<p class="description"><?php esc_html_e( 'Automatically delete sandbox data older than this.', 'bkx-developer-sdk' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'API Explorer Settings', 'bkx-developer-sdk' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Log API Requests', 'bkx-developer-sdk' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="log_api_requests" value="1" <?php checked( $settings['log_api_requests'] ); ?>>
					<?php esc_html_e( 'Log API requests made from explorer', 'bkx-developer-sdk' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="api_explorer_cache_ttl"><?php esc_html_e( 'Cache TTL', 'bkx-developer-sdk' ); ?></label></th>
			<td>
				<select name="api_explorer_cache_ttl" id="api_explorer_cache_ttl">
					<option value="0" <?php selected( $settings['api_explorer_cache_ttl'], 0 ); ?>><?php esc_html_e( 'No caching', 'bkx-developer-sdk' ); ?></option>
					<option value="300" <?php selected( $settings['api_explorer_cache_ttl'], 300 ); ?>><?php esc_html_e( '5 minutes', 'bkx-developer-sdk' ); ?></option>
					<option value="3600" <?php selected( $settings['api_explorer_cache_ttl'], 3600 ); ?>><?php esc_html_e( '1 hour', 'bkx-developer-sdk' ); ?></option>
					<option value="86400" <?php selected( $settings['api_explorer_cache_ttl'], 86400 ); ?>><?php esc_html_e( '1 day', 'bkx-developer-sdk' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Cache duration for endpoint discovery.', 'bkx-developer-sdk' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'WP-CLI Commands', 'bkx-developer-sdk' ); ?></h2>
	<div class="bkx-cli-info">
		<p><?php esc_html_e( 'Available commands when CLI is enabled:', 'bkx-developer-sdk' ); ?></p>
		<pre><code># Generate code
wp bkx generate addon --name="My Addon" --slug="my-addon"
wp bkx generate payment-gateway --name="My Gateway"
wp bkx generate rest-endpoint --route="/custom"

# Test data
wp bkx test generate bookings --count=10
wp bkx test generate services --count=5
wp bkx test cleanup

# API testing
wp bkx api get /wp/v2/bkx_booking
wp bkx api post /wp/v2/bkx_booking --data='{"title":"Test"}'</code></pre>
	</div>

	<?php submit_button( __( 'Save Settings', 'bkx-developer-sdk' ) ); ?>
</form>
