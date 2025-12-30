<?php
/**
 * Code generator template.
 *
 * @package BookingX\DeveloperSDK
 */

defined( 'ABSPATH' ) || exit;

$addon     = \BookingX\DeveloperSDK\DeveloperSDKAddon::get_instance();
$templates = $addon->get_code_templates();
?>

<div class="bkx-generator">
	<div class="bkx-generator-sidebar">
		<h3><?php esc_html_e( 'Templates', 'bkx-developer-sdk' ); ?></h3>
		<ul class="bkx-template-list">
			<?php foreach ( $templates as $key => $template ) : ?>
				<li>
					<a href="#" class="bkx-template-item" data-template="<?php echo esc_attr( $key ); ?>">
						<strong><?php echo esc_html( $template['label'] ); ?></strong>
						<span><?php echo esc_html( $template['description'] ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<div class="bkx-generator-main">
		<div class="bkx-generator-form" id="bkx-generator-form">
			<h3 id="bkx-template-title"><?php esc_html_e( 'Select a template', 'bkx-developer-sdk' ); ?></h3>
			<p id="bkx-template-desc"><?php esc_html_e( 'Choose a template from the sidebar to get started.', 'bkx-developer-sdk' ); ?></p>

			<div id="bkx-template-params" style="display: none;">
				<table class="form-table" id="bkx-params-table">
					<!-- Parameters will be populated by JavaScript -->
				</table>

				<p class="submit">
					<button type="button" class="button button-primary" id="bkx-generate-code">
						<?php esc_html_e( 'Generate Code', 'bkx-developer-sdk' ); ?>
					</button>
				</p>
			</div>
		</div>

		<div class="bkx-generator-output" id="bkx-generator-output" style="display: none;">
			<div class="bkx-output-header">
				<h3><?php esc_html_e( 'Generated Code', 'bkx-developer-sdk' ); ?></h3>
				<div class="bkx-output-actions">
					<button type="button" class="button" id="bkx-copy-code">
						<span class="dashicons dashicons-clipboard"></span>
						<?php esc_html_e( 'Copy', 'bkx-developer-sdk' ); ?>
					</button>
					<button type="button" class="button" id="bkx-download-code">
						<span class="dashicons dashicons-download"></span>
						<?php esc_html_e( 'Download', 'bkx-developer-sdk' ); ?>
					</button>
				</div>
			</div>
			<pre><code id="bkx-generated-code" class="language-php"></code></pre>
		</div>
	</div>
</div>

<script type="text/template" id="bkx-template-data">
<?php echo wp_json_encode( $templates ); ?>
</script>
