<?php
/**
 * BookingX settings tab integration template.
 *
 * @package BookingX\APIBuilder
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_api_builder_settings', array() );
$addon    = \BookingX\APIBuilder\APIBuilderAddon::get_instance();
$manager  = $addon->get_service( 'endpoint_manager' );
$count    = $manager->get_count( 'active' );
?>
<div class="bkx-settings-api-tab">
	<h2><?php esc_html_e( 'API Builder', 'bkx-api-builder' ); ?></h2>

	<div class="bkx-api-status">
		<div class="bkx-status-item">
			<span class="value"><?php echo esc_html( $count ); ?></span>
			<span class="label"><?php esc_html_e( 'Active Endpoints', 'bkx-api-builder' ); ?></span>
		</div>
	</div>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="bkx_api_namespace"><?php esc_html_e( 'API Namespace', 'bkx-api-builder' ); ?></label>
			</th>
			<td>
				<code><?php echo esc_html( rest_url( $settings['api_namespace'] ?? 'bkx-custom/v1' ) ); ?></code>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Rate Limiting', 'bkx-api-builder' ); ?></th>
			<td>
				<?php
				printf(
					/* translators: 1: rate limit number 2: time window */
					esc_html__( '%1$s requests per %2$s', 'bkx-api-builder' ),
					esc_html( number_format( $settings['default_rate_limit'] ?? 1000 ) ),
					esc_html( $settings['rate_limit_window'] ?? 3600 ) === '3600' ? __( 'hour', 'bkx-api-builder' ) : __( 'day', 'bkx-api-builder' )
				);
				?>
			</td>
		</tr>
	</table>

	<h3><?php esc_html_e( 'Quick Actions', 'bkx-api-builder' ); ?></h3>
	<p>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-api-builder' ) ); ?>" class="button">
			<span class="dashicons dashicons-rest-api" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Manage Endpoints', 'bkx-api-builder' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-api-builder&tab=keys' ) ); ?>" class="button">
			<span class="dashicons dashicons-admin-network" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'API Keys', 'bkx-api-builder' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-api-builder&tab=docs' ) ); ?>" class="button">
			<span class="dashicons dashicons-media-document" style="vertical-align: middle;"></span>
			<?php esc_html_e( 'Documentation', 'bkx-api-builder' ); ?>
		</a>
	</p>
</div>
