<?php
/**
 * BookingX settings tab integration template.
 *
 * @package BookingX\DeveloperSDK
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_developer_sdk_settings', array() );
?>
<div class="bkx-settings-sdk-tab">
	<h2><?php esc_html_e( 'Developer SDK', 'bkx-developer-sdk' ); ?></h2>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'bkx-developer-sdk' ); ?></th>
			<td>
				<?php if ( ! empty( $settings['debug_mode'] ) ) : ?>
					<span class="bkx-badge bkx-badge-warning"><?php esc_html_e( 'Debug Mode Active', 'bkx-developer-sdk' ); ?></span>
				<?php else : ?>
					<span class="bkx-badge bkx-badge-success"><?php esc_html_e( 'Production Mode', 'bkx-developer-sdk' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Features', 'bkx-developer-sdk' ); ?></th>
			<td>
				<ul style="margin: 0;">
					<li>
						<?php if ( ! empty( $settings['enable_code_generator'] ) ) : ?>
							<span class="dashicons dashicons-yes" style="color: green;"></span>
						<?php else : ?>
							<span class="dashicons dashicons-no" style="color: red;"></span>
						<?php endif; ?>
						<?php esc_html_e( 'Code Generator', 'bkx-developer-sdk' ); ?>
					</li>
					<li>
						<?php if ( ! empty( $settings['enable_api_explorer'] ) ) : ?>
							<span class="dashicons dashicons-yes" style="color: green;"></span>
						<?php else : ?>
							<span class="dashicons dashicons-no" style="color: red;"></span>
						<?php endif; ?>
						<?php esc_html_e( 'API Explorer', 'bkx-developer-sdk' ); ?>
					</li>
					<li>
						<?php if ( ! empty( $settings['enable_sandbox'] ) ) : ?>
							<span class="dashicons dashicons-yes" style="color: green;"></span>
						<?php else : ?>
							<span class="dashicons dashicons-no" style="color: red;"></span>
						<?php endif; ?>
						<?php esc_html_e( 'Sandbox', 'bkx-developer-sdk' ); ?>
					</li>
					<li>
						<?php if ( ! empty( $settings['enable_cli'] ) ) : ?>
							<span class="dashicons dashicons-yes" style="color: green;"></span>
						<?php else : ?>
							<span class="dashicons dashicons-no" style="color: red;"></span>
						<?php endif; ?>
						<?php esc_html_e( 'WP-CLI Commands', 'bkx-developer-sdk' ); ?>
					</li>
				</ul>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Quick Access', 'bkx-developer-sdk' ); ?></h3>
	<p>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-developer-sdk' ) ); ?>" class="button">
			<span class="dashicons dashicons-editor-code" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Code Generator', 'bkx-developer-sdk' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-developer-sdk&tab=api-explorer' ) ); ?>" class="button">
			<span class="dashicons dashicons-rest-api" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'API Explorer', 'bkx-developer-sdk' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-developer-sdk&tab=hooks' ) ); ?>" class="button">
			<span class="dashicons dashicons-tag" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Hook Inspector', 'bkx-developer-sdk' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-developer-sdk&tab=documentation' ) ); ?>" class="button">
			<span class="dashicons dashicons-media-document" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Documentation', 'bkx-developer-sdk' ); ?>
		</a>
	</p>
</div>
