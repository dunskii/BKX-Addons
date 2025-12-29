<?php
/**
 * Main admin page template.
 *
 * @package BookingX\BkxIntegration
 */

defined( 'ABSPATH' ) || exit;

$tabs = array(
	'sites'     => __( 'Remote Sites', 'bkx-bkx-integration' ),
	'api'       => __( 'API Settings', 'bkx-bkx-integration' ),
	'conflicts' => __( 'Conflicts', 'bkx-bkx-integration' ),
	'logs'      => __( 'Logs', 'bkx-bkx-integration' ),
);

$current_tab = $tab;

$addon    = \BookingX\BkxIntegration\BkxIntegrationAddon::get_instance();
$sites    = $addon->get_service( 'sites' )->get_all();
$conflicts_count = $addon->get_service( 'conflicts' )->count_pending();
?>

<div class="wrap bkx-bkx-admin">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'BKX to BKX Integration', 'bkx-bkx-integration' ); ?>
	</h1>

	<?php if ( 'sites' === $current_tab ) : ?>
		<a href="#" class="page-title-action" id="bkx-bkx-add-site">
			<?php esc_html_e( 'Add Remote Site', 'bkx-bkx-integration' ); ?>
		</a>
	<?php endif; ?>

	<hr class="wp-header-end">

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-integration&tab=' . $tab_key ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
				<?php if ( 'conflicts' === $tab_key && $conflicts_count > 0 ) : ?>
					<span class="bkx-bkx-badge"><?php echo esc_html( $conflicts_count ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-bkx-content">
		<?php
		switch ( $current_tab ) {
			case 'api':
				include BKX_BKX_PLUGIN_DIR . 'templates/admin/api-settings.php';
				break;

			case 'conflicts':
				include BKX_BKX_PLUGIN_DIR . 'templates/admin/conflicts.php';
				break;

			case 'logs':
				include BKX_BKX_PLUGIN_DIR . 'templates/admin/logs.php';
				break;

			default:
				include BKX_BKX_PLUGIN_DIR . 'templates/admin/sites.php';
				break;
		}
		?>
	</div>
</div>

<!-- Site Modal -->
<div id="bkx-bkx-site-modal" class="bkx-bkx-modal" style="display: none;">
	<div class="bkx-bkx-modal-content">
		<span class="bkx-bkx-modal-close">&times;</span>
		<h2 id="bkx-bkx-modal-title"><?php esc_html_e( 'Add Remote Site', 'bkx-bkx-integration' ); ?></h2>

		<form id="bkx-bkx-site-form">
			<input type="hidden" name="site_id" id="bkx-bkx-site-id" value="0">

			<table class="form-table">
				<tr>
					<th><label for="bkx-bkx-name"><?php esc_html_e( 'Site Name', 'bkx-bkx-integration' ); ?> <span class="required">*</span></label></th>
					<td><input type="text" id="bkx-bkx-name" name="name" class="regular-text" required></td>
				</tr>
				<tr>
					<th><label for="bkx-bkx-url"><?php esc_html_e( 'Site URL', 'bkx-bkx-integration' ); ?> <span class="required">*</span></label></th>
					<td>
						<input type="url" id="bkx-bkx-url" name="url" class="large-text" required placeholder="https://example.com">
						<p class="description"><?php esc_html_e( 'The URL of the remote BookingX site.', 'bkx-bkx-integration' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="bkx-bkx-api-key"><?php esc_html_e( 'API Key', 'bkx-bkx-integration' ); ?> <span class="required">*</span></label></th>
					<td>
						<input type="text" id="bkx-bkx-api-key" name="api_key" class="regular-text" required>
						<p class="description"><?php esc_html_e( 'The API key from the remote site.', 'bkx-bkx-integration' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="bkx-bkx-api-secret"><?php esc_html_e( 'API Secret', 'bkx-bkx-integration' ); ?> <span class="required">*</span></label></th>
					<td>
						<input type="password" id="bkx-bkx-api-secret" name="api_secret" class="regular-text" required>
					</td>
				</tr>
				<tr>
					<th><label for="bkx-bkx-direction"><?php esc_html_e( 'Sync Direction', 'bkx-bkx-integration' ); ?></label></th>
					<td>
						<select id="bkx-bkx-direction" name="direction">
							<option value="both"><?php esc_html_e( 'Both (Push & Pull)', 'bkx-bkx-integration' ); ?></option>
							<option value="push"><?php esc_html_e( 'Push Only (Send to remote)', 'bkx-bkx-integration' ); ?></option>
							<option value="pull"><?php esc_html_e( 'Pull Only (Receive from remote)', 'bkx-bkx-integration' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Sync Options', 'bkx-bkx-integration' ); ?></th>
					<td>
						<fieldset>
							<label>
								<input type="checkbox" name="sync_bookings" value="1" checked>
								<?php esc_html_e( 'Sync Bookings', 'bkx-bkx-integration' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="sync_availability" value="1" checked>
								<?php esc_html_e( 'Sync Availability', 'bkx-bkx-integration' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="sync_customers" value="1">
								<?php esc_html_e( 'Sync Customers', 'bkx-bkx-integration' ); ?>
							</label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th><label for="bkx-bkx-status"><?php esc_html_e( 'Status', 'bkx-bkx-integration' ); ?></label></th>
					<td>
						<select id="bkx-bkx-status" name="status">
							<option value="active"><?php esc_html_e( 'Active', 'bkx-bkx-integration' ); ?></option>
							<option value="paused"><?php esc_html_e( 'Paused', 'bkx-bkx-integration' ); ?></option>
						</select>
					</td>
				</tr>
			</table>

			<div class="bkx-bkx-modal-footer">
				<button type="button" class="button" id="bkx-bkx-test-connection" style="margin-right: auto;">
					<?php esc_html_e( 'Test Connection', 'bkx-bkx-integration' ); ?>
				</button>
				<button type="button" class="button bkx-bkx-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-bkx-integration' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Site', 'bkx-bkx-integration' ); ?></button>
			</div>
		</form>
	</div>
</div>
