<?php
/**
 * Endpoints admin template.
 *
 * @package BookingX\APIBuilder
 */

defined( 'ABSPATH' ) || exit;

$addon     = \BookingX\APIBuilder\APIBuilderAddon::get_instance();
$manager   = $addon->get_service( 'endpoint_manager' );
$endpoints = $manager->get_all();
$settings  = get_option( 'bkx_api_builder_settings', array() );
$namespace = $settings['api_namespace'] ?? 'bkx-custom/v1';
?>
<div class="bkx-endpoints-page">
	<div class="bkx-page-header">
		<h2><?php esc_html_e( 'Custom Endpoints', 'bkx-api-builder' ); ?></h2>
		<button type="button" class="button button-primary" id="bkx-add-endpoint">
			<span class="dashicons dashicons-plus-alt2"></span>
			<?php esc_html_e( 'Add Endpoint', 'bkx-api-builder' ); ?>
		</button>
	</div>

	<div class="bkx-info-box">
		<strong><?php esc_html_e( 'Base URL:', 'bkx-api-builder' ); ?></strong>
		<code><?php echo esc_html( rest_url( $namespace ) ); ?></code>
	</div>

	<?php if ( empty( $endpoints ) ) : ?>
		<div class="bkx-empty-state">
			<span class="dashicons dashicons-rest-api"></span>
			<h3><?php esc_html_e( 'No endpoints yet', 'bkx-api-builder' ); ?></h3>
			<p><?php esc_html_e( 'Create your first custom API endpoint to get started.', 'bkx-api-builder' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'Method', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'Route', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'Authentication', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'bkx-api-builder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $endpoints as $endpoint ) : ?>
					<tr data-id="<?php echo esc_attr( $endpoint['id'] ); ?>">
						<td>
							<strong><?php echo esc_html( $endpoint['name'] ); ?></strong>
							<?php if ( $endpoint['description'] ) : ?>
								<br><small><?php echo esc_html( $endpoint['description'] ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<span class="bkx-method bkx-method-<?php echo esc_attr( strtolower( $endpoint['method'] ) ); ?>">
								<?php echo esc_html( $endpoint['method'] ); ?>
							</span>
						</td>
						<td><code><?php echo esc_html( $endpoint['route'] ); ?></code></td>
						<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $endpoint['authentication'] ) ) ); ?></td>
						<td>
							<span class="bkx-status bkx-status-<?php echo esc_attr( $endpoint['status'] ); ?>">
								<?php echo esc_html( ucfirst( $endpoint['status'] ) ); ?>
							</span>
						</td>
						<td>
							<button type="button" class="button bkx-edit-endpoint" data-id="<?php echo esc_attr( $endpoint['id'] ); ?>">
								<?php esc_html_e( 'Edit', 'bkx-api-builder' ); ?>
							</button>
							<button type="button" class="button bkx-test-endpoint" data-id="<?php echo esc_attr( $endpoint['id'] ); ?>">
								<?php esc_html_e( 'Test', 'bkx-api-builder' ); ?>
							</button>
							<button type="button" class="button bkx-delete-endpoint" data-id="<?php echo esc_attr( $endpoint['id'] ); ?>">
								<?php esc_html_e( 'Delete', 'bkx-api-builder' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<!-- Endpoint Modal -->
<div id="bkx-endpoint-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content bkx-modal-large">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'Endpoint Configuration', 'bkx-api-builder' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<form id="bkx-endpoint-form">
			<div class="bkx-modal-body">
				<input type="hidden" name="endpoint_id" id="endpoint_id" value="0">

				<div class="bkx-form-row">
					<label for="endpoint_name"><?php esc_html_e( 'Name', 'bkx-api-builder' ); ?></label>
					<input type="text" name="name" id="endpoint_name" class="regular-text" required>
				</div>

				<div class="bkx-form-row bkx-form-row-inline">
					<div>
						<label for="endpoint_method"><?php esc_html_e( 'Method', 'bkx-api-builder' ); ?></label>
						<select name="method" id="endpoint_method">
							<option value="GET">GET</option>
							<option value="POST">POST</option>
							<option value="PUT">PUT</option>
							<option value="PATCH">PATCH</option>
							<option value="DELETE">DELETE</option>
						</select>
					</div>
					<div style="flex: 2;">
						<label for="endpoint_route"><?php esc_html_e( 'Route', 'bkx-api-builder' ); ?></label>
						<input type="text" name="route" id="endpoint_route" placeholder="/bookings" required>
					</div>
				</div>

				<div class="bkx-form-row">
					<label for="endpoint_description"><?php esc_html_e( 'Description', 'bkx-api-builder' ); ?></label>
					<textarea name="description" id="endpoint_description" rows="2"></textarea>
				</div>

				<div class="bkx-form-row bkx-form-row-inline">
					<div>
						<label for="endpoint_handler_type"><?php esc_html_e( 'Handler Type', 'bkx-api-builder' ); ?></label>
						<select name="handler_type" id="endpoint_handler_type">
							<option value="query"><?php esc_html_e( 'Database Query', 'bkx-api-builder' ); ?></option>
							<option value="action"><?php esc_html_e( 'Built-in Action', 'bkx-api-builder' ); ?></option>
							<option value="callback"><?php esc_html_e( 'Custom Callback', 'bkx-api-builder' ); ?></option>
							<option value="proxy"><?php esc_html_e( 'Proxy Request', 'bkx-api-builder' ); ?></option>
						</select>
					</div>
					<div>
						<label for="endpoint_auth"><?php esc_html_e( 'Authentication', 'bkx-api-builder' ); ?></label>
						<select name="authentication" id="endpoint_auth">
							<option value="none"><?php esc_html_e( 'None (Public)', 'bkx-api-builder' ); ?></option>
							<option value="api_key"><?php esc_html_e( 'API Key', 'bkx-api-builder' ); ?></option>
							<option value="jwt"><?php esc_html_e( 'JWT Token', 'bkx-api-builder' ); ?></option>
							<option value="wordpress"><?php esc_html_e( 'WordPress Login', 'bkx-api-builder' ); ?></option>
							<option value="capability"><?php esc_html_e( 'Capability Check', 'bkx-api-builder' ); ?></option>
						</select>
					</div>
				</div>

				<div class="bkx-form-row">
					<label for="endpoint_handler_config"><?php esc_html_e( 'Handler Configuration (JSON)', 'bkx-api-builder' ); ?></label>
					<textarea name="handler_config" id="endpoint_handler_config" rows="6" class="code">{}</textarea>
				</div>

				<div class="bkx-form-row">
					<label for="endpoint_request_schema"><?php esc_html_e( 'Request Parameters Schema (JSON)', 'bkx-api-builder' ); ?></label>
					<textarea name="request_schema" id="endpoint_request_schema" rows="4" class="code">{}</textarea>
				</div>

				<div class="bkx-form-row bkx-form-row-inline">
					<div>
						<label for="endpoint_rate_limit"><?php esc_html_e( 'Rate Limit', 'bkx-api-builder' ); ?></label>
						<input type="number" name="rate_limit" id="endpoint_rate_limit" value="0" min="0">
						<small><?php esc_html_e( '0 = use global limit', 'bkx-api-builder' ); ?></small>
					</div>
					<div>
						<label>
							<input type="checkbox" name="cache_enabled" id="endpoint_cache_enabled" value="1">
							<?php esc_html_e( 'Enable Response Caching', 'bkx-api-builder' ); ?>
						</label>
					</div>
					<div>
						<label for="endpoint_status"><?php esc_html_e( 'Status', 'bkx-api-builder' ); ?></label>
						<select name="status" id="endpoint_status">
							<option value="active"><?php esc_html_e( 'Active', 'bkx-api-builder' ); ?></option>
							<option value="draft"><?php esc_html_e( 'Draft', 'bkx-api-builder' ); ?></option>
							<option value="disabled"><?php esc_html_e( 'Disabled', 'bkx-api-builder' ); ?></option>
						</select>
					</div>
				</div>
			</div>
			<div class="bkx-modal-footer">
				<button type="button" class="button bkx-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-api-builder' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Endpoint', 'bkx-api-builder' ); ?></button>
			</div>
		</form>
	</div>
</div>
