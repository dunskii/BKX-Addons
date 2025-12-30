<?php
/**
 * API Explorer template.
 *
 * @package BookingX\DeveloperSDK
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\DeveloperSDK\DeveloperSDKAddon::get_instance();
$explorer = $addon->get_service( 'api_explorer' );

$common_endpoints = $explorer->get_common_endpoints();
$request_log      = $explorer->get_request_log( 10 );
?>

<div class="bkx-api-explorer">
	<div class="bkx-explorer-sidebar">
		<h3><?php esc_html_e( 'Quick Access', 'bkx-developer-sdk' ); ?></h3>
		<ul class="bkx-endpoint-list">
			<?php foreach ( $common_endpoints as $endpoint ) : ?>
				<li>
					<a href="#" class="bkx-endpoint-item"
					   data-method="<?php echo esc_attr( $endpoint['method'] ); ?>"
					   data-endpoint="<?php echo esc_attr( $endpoint['endpoint'] ); ?>"
					   data-body="<?php echo esc_attr( $endpoint['body'] ?? '' ); ?>">
						<span class="bkx-method bkx-method-<?php echo esc_attr( strtolower( $endpoint['method'] ) ); ?>">
							<?php echo esc_html( $endpoint['method'] ); ?>
						</span>
						<span class="bkx-label"><?php echo esc_html( $endpoint['label'] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<h3><?php esc_html_e( 'Recent Requests', 'bkx-developer-sdk' ); ?></h3>
		<?php if ( empty( $request_log ) ) : ?>
			<p class="bkx-no-data"><?php esc_html_e( 'No recent requests.', 'bkx-developer-sdk' ); ?></p>
		<?php else : ?>
			<ul class="bkx-request-log">
				<?php foreach ( $request_log as $log ) : ?>
					<li class="bkx-log-item <?php echo $log['status'] >= 200 && $log['status'] < 300 ? 'success' : 'error'; ?>">
						<span class="bkx-method"><?php echo esc_html( $log['method'] ); ?></span>
						<span class="bkx-endpoint"><?php echo esc_html( $log['endpoint'] ); ?></span>
						<span class="bkx-status"><?php echo esc_html( $log['status'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>

	<div class="bkx-explorer-main">
		<div class="bkx-request-form">
			<div class="bkx-request-row">
				<select id="bkx-request-method" class="bkx-method-select">
					<option value="GET">GET</option>
					<option value="POST">POST</option>
					<option value="PUT">PUT</option>
					<option value="PATCH">PATCH</option>
					<option value="DELETE">DELETE</option>
				</select>
				<input type="text" id="bkx-request-endpoint" class="bkx-endpoint-input" placeholder="/wp/v2/bkx_booking">
				<button type="button" class="button button-primary" id="bkx-send-request">
					<?php esc_html_e( 'Send', 'bkx-developer-sdk' ); ?>
				</button>
			</div>

			<div class="bkx-request-body-container" id="bkx-body-container" style="display: none;">
				<label for="bkx-request-body"><?php esc_html_e( 'Request Body (JSON)', 'bkx-developer-sdk' ); ?></label>
				<textarea id="bkx-request-body" rows="8" placeholder='{"key": "value"}'></textarea>
			</div>
		</div>

		<div class="bkx-response-area">
			<div class="bkx-response-tabs">
				<button type="button" class="bkx-response-tab active" data-tab="response"><?php esc_html_e( 'Response', 'bkx-developer-sdk' ); ?></button>
				<button type="button" class="bkx-response-tab" data-tab="headers"><?php esc_html_e( 'Headers', 'bkx-developer-sdk' ); ?></button>
				<button type="button" class="bkx-response-tab" data-tab="code"><?php esc_html_e( 'Code', 'bkx-developer-sdk' ); ?></button>
			</div>

			<div class="bkx-response-content active" data-tab="response">
				<div class="bkx-response-meta" id="bkx-response-meta" style="display: none;">
					<span class="bkx-response-status" id="bkx-response-status"></span>
					<span class="bkx-response-time" id="bkx-response-time"></span>
				</div>
				<pre><code id="bkx-response-body" class="language-json"></code></pre>
			</div>

			<div class="bkx-response-content" data-tab="headers">
				<pre><code id="bkx-response-headers" class="language-json"></code></pre>
			</div>

			<div class="bkx-response-content" data-tab="code">
				<div class="bkx-code-tabs">
					<button type="button" class="bkx-code-tab active" data-lang="curl"><?php esc_html_e( 'cURL', 'bkx-developer-sdk' ); ?></button>
					<button type="button" class="bkx-code-tab" data-lang="js"><?php esc_html_e( 'JavaScript', 'bkx-developer-sdk' ); ?></button>
					<button type="button" class="bkx-code-tab" data-lang="php"><?php esc_html_e( 'PHP', 'bkx-developer-sdk' ); ?></button>
				</div>
				<pre><code id="bkx-code-sample" class="language-bash"></code></pre>
			</div>
		</div>
	</div>
</div>
