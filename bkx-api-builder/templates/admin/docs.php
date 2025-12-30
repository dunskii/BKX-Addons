<?php
/**
 * Documentation admin template.
 *
 * @package BookingX\APIBuilder
 */

defined( 'ABSPATH' ) || exit;

$addon     = \BookingX\APIBuilder\APIBuilderAddon::get_instance();
$generator = $addon->get_service( 'doc_generator' );
$docs      = $generator->generate();
$settings  = get_option( 'bkx_api_builder_settings', array() );
?>
<div class="bkx-docs-page">
	<div class="bkx-page-header">
		<h2><?php esc_html_e( 'API Documentation', 'bkx-api-builder' ); ?></h2>
		<div class="bkx-header-actions">
			<button type="button" class="button" id="bkx-export-openapi" data-format="openapi">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'OpenAPI JSON', 'bkx-api-builder' ); ?>
			</button>
			<button type="button" class="button" id="bkx-export-markdown" data-format="markdown">
				<?php esc_html_e( 'Markdown', 'bkx-api-builder' ); ?>
			</button>
			<button type="button" class="button" id="bkx-export-html" data-format="html">
				<?php esc_html_e( 'HTML', 'bkx-api-builder' ); ?>
			</button>
		</div>
	</div>

	<div class="bkx-docs-info">
		<h3><?php echo esc_html( $docs['info']['title'] ); ?></h3>
		<p><?php echo esc_html( $docs['info']['description'] ); ?></p>
		<p>
			<strong><?php esc_html_e( 'Base URL:', 'bkx-api-builder' ); ?></strong>
			<code><?php echo esc_html( $docs['servers'][0]['url'] ); ?></code>
		</p>
		<?php if ( ! empty( $settings['documentation_public'] ) ) : ?>
			<p>
				<strong><?php esc_html_e( 'Public Docs URL:', 'bkx-api-builder' ); ?></strong>
				<a href="<?php echo esc_url( rest_url( ( $settings['api_namespace'] ?? 'bkx-custom/v1' ) . '/docs' ) ); ?>" target="_blank">
					<?php echo esc_html( rest_url( ( $settings['api_namespace'] ?? 'bkx-custom/v1' ) . '/docs' ) ); ?>
				</a>
			</p>
		<?php endif; ?>
	</div>

	<div class="bkx-docs-section">
		<h3><?php esc_html_e( 'Authentication', 'bkx-api-builder' ); ?></h3>
		<div class="bkx-auth-methods">
			<?php foreach ( $docs['components']['securitySchemes'] as $name => $scheme ) : ?>
				<div class="bkx-auth-method">
					<h4><?php echo esc_html( $name ); ?></h4>
					<p><strong><?php esc_html_e( 'Type:', 'bkx-api-builder' ); ?></strong> <?php echo esc_html( $scheme['type'] ); ?></p>
					<?php if ( isset( $scheme['in'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Location:', 'bkx-api-builder' ); ?></strong> <?php echo esc_html( $scheme['in'] ); ?></p>
					<?php endif; ?>
					<?php if ( isset( $scheme['name'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Header:', 'bkx-api-builder' ); ?></strong> <code><?php echo esc_html( $scheme['name'] ); ?></code></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="bkx-docs-section">
		<h3><?php esc_html_e( 'Endpoints', 'bkx-api-builder' ); ?></h3>
		<?php if ( empty( $docs['paths'] ) ) : ?>
			<p class="description"><?php esc_html_e( 'No endpoints configured yet.', 'bkx-api-builder' ); ?></p>
		<?php else : ?>
			<div class="bkx-endpoints-list">
				<?php foreach ( $docs['paths'] as $path => $methods ) : ?>
					<?php foreach ( $methods as $method => $operation ) : ?>
						<div class="bkx-endpoint-doc">
							<div class="bkx-endpoint-header">
								<span class="bkx-method bkx-method-<?php echo esc_attr( $method ); ?>">
									<?php echo esc_html( strtoupper( $method ) ); ?>
								</span>
								<code><?php echo esc_html( $path ); ?></code>
								<span class="bkx-endpoint-summary"><?php echo esc_html( $operation['summary'] ); ?></span>
							</div>

							<?php if ( ! empty( $operation['description'] ) ) : ?>
								<p class="bkx-endpoint-description"><?php echo esc_html( $operation['description'] ); ?></p>
							<?php endif; ?>

							<?php if ( ! empty( $operation['parameters'] ) ) : ?>
								<div class="bkx-endpoint-params">
									<h5><?php esc_html_e( 'Parameters', 'bkx-api-builder' ); ?></h5>
									<table>
										<thead>
											<tr>
												<th><?php esc_html_e( 'Name', 'bkx-api-builder' ); ?></th>
												<th><?php esc_html_e( 'In', 'bkx-api-builder' ); ?></th>
												<th><?php esc_html_e( 'Type', 'bkx-api-builder' ); ?></th>
												<th><?php esc_html_e( 'Required', 'bkx-api-builder' ); ?></th>
												<th><?php esc_html_e( 'Description', 'bkx-api-builder' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ( $operation['parameters'] as $param ) : ?>
												<tr>
													<td><code><?php echo esc_html( $param['name'] ); ?></code></td>
													<td><?php echo esc_html( $param['in'] ); ?></td>
													<td><?php echo esc_html( $param['schema']['type'] ?? 'string' ); ?></td>
													<td><?php echo ! empty( $param['required'] ) ? __( 'Yes', 'bkx-api-builder' ) : __( 'No', 'bkx-api-builder' ); ?></td>
													<td><?php echo esc_html( $param['description'] ?? '' ); ?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							<?php endif; ?>

							<div class="bkx-endpoint-responses">
								<h5><?php esc_html_e( 'Responses', 'bkx-api-builder' ); ?></h5>
								<ul>
									<?php foreach ( $operation['responses'] as $code => $response ) : ?>
										<li>
											<span class="bkx-response-code"><?php echo esc_html( $code ); ?></span>
											<?php echo esc_html( $response['description'] ); ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="bkx-docs-section">
		<h3><?php esc_html_e( 'Common Schemas', 'bkx-api-builder' ); ?></h3>
		<div class="bkx-schemas">
			<?php foreach ( $docs['components']['schemas'] as $name => $schema ) : ?>
				<div class="bkx-schema">
					<h4><?php echo esc_html( $name ); ?></h4>
					<pre><code><?php echo esc_html( wp_json_encode( $schema, JSON_PRETTY_PRINT ) ); ?></code></pre>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</div>
