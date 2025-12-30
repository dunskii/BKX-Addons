<?php
/**
 * Documentation template.
 *
 * @package BookingX\DeveloperSDK
 */

defined( 'ABSPATH' ) || exit;

$addon = \BookingX\DeveloperSDK\DeveloperSDKAddon::get_instance();
$docs  = $addon->get_service( 'documentation' );

$all_docs = $docs->get_all();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : 'getting_started';
?>

<div class="bkx-documentation">
	<div class="bkx-docs-sidebar">
		<h3><?php esc_html_e( 'Contents', 'bkx-developer-sdk' ); ?></h3>
		<ul class="bkx-docs-nav">
			<?php foreach ( $all_docs as $key => $doc ) : ?>
				<li>
					<a href="<?php echo esc_url( add_query_arg( 'section', $key ) ); ?>" class="<?php echo $section === $key ? 'active' : ''; ?>">
						<?php echo esc_html( $doc['title'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<h3><?php esc_html_e( 'Resources', 'bkx-developer-sdk' ); ?></h3>
		<ul class="bkx-resources">
			<li>
				<a href="<?php echo esc_url( rest_url() ); ?>" target="_blank">
					<span class="dashicons dashicons-rest-api"></span>
					<?php esc_html_e( 'REST API Root', 'bkx-developer-sdk' ); ?>
				</a>
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-developer-sdk&tab=api-explorer' ) ); ?>">
					<span class="dashicons dashicons-search"></span>
					<?php esc_html_e( 'API Explorer', 'bkx-developer-sdk' ); ?>
				</a>
			</li>
			<li>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-developer-sdk&tab=hooks' ) ); ?>">
					<span class="dashicons dashicons-tag"></span>
					<?php esc_html_e( 'Hook Inspector', 'bkx-developer-sdk' ); ?>
				</a>
			</li>
		</ul>
	</div>

	<div class="bkx-docs-content">
		<?php
		$current_doc = $all_docs[ $section ] ?? $all_docs['getting_started'];
		?>

		<h2><?php echo esc_html( $current_doc['title'] ); ?></h2>

		<?php if ( isset( $current_doc['sections'] ) ) : ?>
			<?php foreach ( $current_doc['sections'] as $sec ) : ?>
				<div class="bkx-doc-section">
					<h3><?php echo esc_html( $sec['title'] ); ?></h3>

					<?php if ( isset( $sec['content'] ) ) : ?>
						<p><?php echo esc_html( $sec['content'] ); ?></p>
					<?php endif; ?>

					<?php if ( isset( $sec['list'] ) ) : ?>
						<ul>
							<?php foreach ( $sec['list'] as $item ) : ?>
								<li><?php echo esc_html( $item ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( isset( $sec['code'] ) ) : ?>
						<pre><code class="language-php"><?php echo esc_html( $sec['code'] ); ?></code></pre>
					<?php endif; ?>

					<?php if ( isset( $sec['example'] ) ) : ?>
						<pre><code class="language-php"><?php echo esc_html( $sec['example'] ); ?></code></pre>
					<?php endif; ?>

					<?php if ( isset( $sec['hooks'] ) ) : ?>
						<table class="bkx-hooks-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Hook', 'bkx-developer-sdk' ); ?></th>
									<th><?php esc_html_e( 'Type', 'bkx-developer-sdk' ); ?></th>
									<th><?php esc_html_e( 'Description', 'bkx-developer-sdk' ); ?></th>
									<th><?php esc_html_e( 'Parameters', 'bkx-developer-sdk' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $sec['hooks'] as $hook ) : ?>
									<tr>
										<td><code><?php echo esc_html( $hook['name'] ); ?></code></td>
										<td><span class="bkx-hook-type bkx-hook-type-<?php echo esc_attr( $hook['type'] ); ?>"><?php echo esc_html( $hook['type'] ); ?></span></td>
										<td><?php echo esc_html( $hook['description'] ); ?></td>
										<td><code><?php echo esc_html( $hook['params'] ); ?></code></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>

					<?php if ( isset( $sec['endpoints'] ) ) : ?>
						<table class="bkx-endpoints-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Method', 'bkx-developer-sdk' ); ?></th>
									<th><?php esc_html_e( 'Endpoint', 'bkx-developer-sdk' ); ?></th>
									<th><?php esc_html_e( 'Description', 'bkx-developer-sdk' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $sec['endpoints'] as $endpoint ) : ?>
									<tr>
										<td><span class="bkx-method bkx-method-<?php echo esc_attr( strtolower( $endpoint['method'] ) ); ?>"><?php echo esc_html( $endpoint['method'] ); ?></span></td>
										<td><code><?php echo esc_html( $endpoint['endpoint'] ); ?></code></td>
										<td><?php echo esc_html( $endpoint['description'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>

		<?php if ( isset( $current_doc['examples'] ) ) : ?>
			<?php foreach ( $current_doc['examples'] as $example ) : ?>
				<div class="bkx-doc-example">
					<h3><?php echo esc_html( $example['title'] ); ?></h3>
					<p><?php echo esc_html( $example['description'] ); ?></p>
					<pre><code class="language-php"><?php echo esc_html( $example['code'] ); ?></code></pre>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</div>
