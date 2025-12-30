<?php
/**
 * Settings template.
 *
 * @package BookingX\WebhooksManager
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WebhooksManager\WebhooksManagerAddon::get_instance();
$settings = get_option( 'bkx_webhooks_manager_settings', array() );

$signature_service = $addon->get_service( 'signature_service' );
$algorithms        = $signature_service->get_available_algorithms();

$defaults = array(
	'enabled'                    => true,
	'async_delivery'             => true,
	'max_retries'                => 3,
	'retry_delay'                => 60,
	'default_timeout'            => 30,
	'log_retention_days'         => 30,
	'max_payload_size'           => 1048576,
	'signature_algorithm'        => 'sha256',
	'signature_tolerance'        => 300,
	'include_timestamp'          => true,
	'batch_delivery'             => false,
	'batch_size'                 => 10,
	'batch_interval'             => 60,
	'notify_on_failure'          => true,
	'failure_threshold'          => 5,
	'failure_notification_email' => get_option( 'admin_email' ),
);

$settings = wp_parse_args( $settings, $defaults );
?>

<form method="post" action="" id="bkx-webhooks-settings-form">
	<?php wp_nonce_field( 'bkx_webhooks_settings_nonce', 'bkx_settings_nonce' ); ?>

	<h2><?php esc_html_e( 'General Settings', 'bkx-webhooks-manager' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Webhooks', 'bkx-webhooks-manager' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>>
					<?php esc_html_e( 'Enable webhook dispatching', 'bkx-webhooks-manager' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'When disabled, no webhooks will be sent.', 'bkx-webhooks-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Async Delivery', 'bkx-webhooks-manager' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="async_delivery" value="1" <?php checked( $settings['async_delivery'] ); ?>>
					<?php esc_html_e( 'Deliver webhooks asynchronously', 'bkx-webhooks-manager' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Recommended. Prevents webhook delivery from blocking user actions.', 'bkx-webhooks-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="default_timeout"><?php esc_html_e( 'Default Timeout', 'bkx-webhooks-manager' ); ?></label></th>
			<td>
				<input type="number" name="default_timeout" id="default_timeout" value="<?php echo esc_attr( $settings['default_timeout'] ); ?>" min="5" max="120" class="small-text">
				<?php esc_html_e( 'seconds', 'bkx-webhooks-manager' ); ?>
				<p class="description"><?php esc_html_e( 'Default timeout for webhook requests.', 'bkx-webhooks-manager' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Retry Settings', 'bkx-webhooks-manager' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="max_retries"><?php esc_html_e( 'Max Retries', 'bkx-webhooks-manager' ); ?></label></th>
			<td>
				<input type="number" name="max_retries" id="max_retries" value="<?php echo esc_attr( $settings['max_retries'] ); ?>" min="0" max="10" class="small-text">
				<p class="description"><?php esc_html_e( 'Maximum retry attempts for failed deliveries.', 'bkx-webhooks-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="retry_delay"><?php esc_html_e( 'Retry Delay', 'bkx-webhooks-manager' ); ?></label></th>
			<td>
				<input type="number" name="retry_delay" id="retry_delay" value="<?php echo esc_attr( $settings['retry_delay'] ); ?>" min="30" max="3600" class="small-text">
				<?php esc_html_e( 'seconds', 'bkx-webhooks-manager' ); ?>
				<p class="description"><?php esc_html_e( 'Initial delay before first retry. Subsequent retries use exponential backoff.', 'bkx-webhooks-manager' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Security Settings', 'bkx-webhooks-manager' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="signature_algorithm"><?php esc_html_e( 'Signature Algorithm', 'bkx-webhooks-manager' ); ?></label></th>
			<td>
				<select name="signature_algorithm" id="signature_algorithm">
					<?php foreach ( $algorithms as $algo_key => $algo ) : ?>
						<option value="<?php echo esc_attr( $algo_key ); ?>" <?php selected( $settings['signature_algorithm'], $algo_key ); ?>>
							<?php echo esc_html( $algo['label'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Algorithm used for HMAC signature generation.', 'bkx-webhooks-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="signature_tolerance"><?php esc_html_e( 'Signature Tolerance', 'bkx-webhooks-manager' ); ?></label></th>
			<td>
				<input type="number" name="signature_tolerance" id="signature_tolerance" value="<?php echo esc_attr( $settings['signature_tolerance'] ); ?>" min="60" max="900" class="small-text">
				<?php esc_html_e( 'seconds', 'bkx-webhooks-manager' ); ?>
				<p class="description"><?php esc_html_e( 'Time window for signature verification (prevents replay attacks).', 'bkx-webhooks-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Include Timestamp', 'bkx-webhooks-manager' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="include_timestamp" value="1" <?php checked( $settings['include_timestamp'] ); ?>>
					<?php esc_html_e( 'Include Unix timestamp in payload', 'bkx-webhooks-manager' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Logging & Cleanup', 'bkx-webhooks-manager' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="log_retention_days"><?php esc_html_e( 'Log Retention', 'bkx-webhooks-manager' ); ?></label></th>
			<td>
				<input type="number" name="log_retention_days" id="log_retention_days" value="<?php echo esc_attr( $settings['log_retention_days'] ); ?>" min="1" max="365" class="small-text">
				<?php esc_html_e( 'days', 'bkx-webhooks-manager' ); ?>
				<p class="description"><?php esc_html_e( 'Delivery logs older than this will be automatically deleted.', 'bkx-webhooks-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="max_payload_size"><?php esc_html_e( 'Max Payload Size', 'bkx-webhooks-manager' ); ?></label></th>
			<td>
				<select name="max_payload_size" id="max_payload_size">
					<option value="262144" <?php selected( $settings['max_payload_size'], 262144 ); ?>>256 KB</option>
					<option value="524288" <?php selected( $settings['max_payload_size'], 524288 ); ?>>512 KB</option>
					<option value="1048576" <?php selected( $settings['max_payload_size'], 1048576 ); ?>>1 MB</option>
					<option value="2097152" <?php selected( $settings['max_payload_size'], 2097152 ); ?>>2 MB</option>
				</select>
				<p class="description"><?php esc_html_e( 'Maximum size for webhook payloads.', 'bkx-webhooks-manager' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Failure Notifications', 'bkx-webhooks-manager' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Notify on Failure', 'bkx-webhooks-manager' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="notify_on_failure" value="1" <?php checked( $settings['notify_on_failure'] ); ?>>
					<?php esc_html_e( 'Send email notifications when webhooks fail repeatedly', 'bkx-webhooks-manager' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="failure_threshold"><?php esc_html_e( 'Failure Threshold', 'bkx-webhooks-manager' ); ?></label></th>
			<td>
				<input type="number" name="failure_threshold" id="failure_threshold" value="<?php echo esc_attr( $settings['failure_threshold'] ); ?>" min="1" max="50" class="small-text">
				<?php esc_html_e( 'failures', 'bkx-webhooks-manager' ); ?>
				<p class="description"><?php esc_html_e( 'Send notification after this many consecutive failures.', 'bkx-webhooks-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="failure_notification_email"><?php esc_html_e( 'Notification Email', 'bkx-webhooks-manager' ); ?></label></th>
			<td>
				<input type="email" name="failure_notification_email" id="failure_notification_email" value="<?php echo esc_attr( $settings['failure_notification_email'] ); ?>" class="regular-text">
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Batch Delivery', 'bkx-webhooks-manager' ); ?></h2>
	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Batching', 'bkx-webhooks-manager' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="batch_delivery" value="1" <?php checked( $settings['batch_delivery'] ); ?>>
					<?php esc_html_e( 'Batch multiple events into single webhook calls', 'bkx-webhooks-manager' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'Reduces the number of HTTP requests for high-volume sites.', 'bkx-webhooks-manager' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="batch_size"><?php esc_html_e( 'Batch Size', 'bkx-webhooks-manager' ); ?></label></th>
			<td>
				<input type="number" name="batch_size" id="batch_size" value="<?php echo esc_attr( $settings['batch_size'] ); ?>" min="2" max="100" class="small-text">
				<?php esc_html_e( 'events', 'bkx-webhooks-manager' ); ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="batch_interval"><?php esc_html_e( 'Batch Interval', 'bkx-webhooks-manager' ); ?></label></th>
			<td>
				<input type="number" name="batch_interval" id="batch_interval" value="<?php echo esc_attr( $settings['batch_interval'] ); ?>" min="30" max="300" class="small-text">
				<?php esc_html_e( 'seconds', 'bkx-webhooks-manager' ); ?>
				<p class="description"><?php esc_html_e( 'Maximum time to wait before sending a partial batch.', 'bkx-webhooks-manager' ); ?></p>
			</td>
		</tr>
	</table>

	<h2><?php esc_html_e( 'Signature Verification Guide', 'bkx-webhooks-manager' ); ?></h2>
	<div class="bkx-verification-guide">
		<p><?php esc_html_e( 'All webhook requests include an X-BKX-Signature header. Here\'s how to verify signatures:', 'bkx-webhooks-manager' ); ?></p>

		<div class="bkx-code-tabs">
			<?php
			$languages = $signature_service->get_supported_languages();
			$first     = true;
			foreach ( $languages as $lang_key => $lang_label ) :
				?>
				<button type="button" class="bkx-code-tab <?php echo $first ? 'active' : ''; ?>" data-lang="<?php echo esc_attr( $lang_key ); ?>">
					<?php echo esc_html( $lang_label ); ?>
				</button>
				<?php
				$first = false;
			endforeach;
			?>
		</div>

		<?php
		$first = true;
		foreach ( $languages as $lang_key => $lang_label ) :
			?>
			<pre class="bkx-code-example" data-lang="<?php echo esc_attr( $lang_key ); ?>" style="<?php echo $first ? '' : 'display:none;'; ?>"><?php echo esc_html( $signature_service->get_verification_example( $lang_key ) ); ?></pre>
			<?php
			$first = false;
		endforeach;
		?>
	</div>

	<?php submit_button( __( 'Save Settings', 'bkx-webhooks-manager' ) ); ?>
</form>
