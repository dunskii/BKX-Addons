<?php
/**
 * API Keys admin template.
 *
 * @package BookingX\APIBuilder
 */

defined( 'ABSPATH' ) || exit;

$addon   = \BookingX\APIBuilder\APIBuilderAddon::get_instance();
$manager = $addon->get_service( 'api_key_manager' );
$keys    = $manager->get_all();
?>
<div class="bkx-keys-page">
	<div class="bkx-page-header">
		<h2><?php esc_html_e( 'API Keys', 'bkx-api-builder' ); ?></h2>
		<button type="button" class="button button-primary" id="bkx-generate-key">
			<span class="dashicons dashicons-admin-network"></span>
			<?php esc_html_e( 'Generate API Key', 'bkx-api-builder' ); ?>
		</button>
	</div>

	<?php if ( empty( $keys ) ) : ?>
		<div class="bkx-empty-state">
			<span class="dashicons dashicons-admin-network"></span>
			<h3><?php esc_html_e( 'No API keys', 'bkx-api-builder' ); ?></h3>
			<p><?php esc_html_e( 'Generate an API key to authenticate API requests.', 'bkx-api-builder' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'API Key', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'Requests', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'Last Used', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bkx-api-builder' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'bkx-api-builder' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $keys as $key ) : ?>
					<tr data-id="<?php echo esc_attr( $key['id'] ); ?>">
						<td>
							<strong><?php echo esc_html( $key['name'] ); ?></strong>
							<?php
							$user = get_user_by( 'id', $key['user_id'] );
							if ( $user ) :
								?>
								<br><small><?php echo esc_html( $user->display_name ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<code class="bkx-api-key"><?php echo esc_html( substr( $key['api_key'], 0, 12 ) . '...' ); ?></code>
							<button type="button" class="button-link bkx-copy-key" data-key="<?php echo esc_attr( $key['api_key'] ); ?>">
								<span class="dashicons dashicons-clipboard"></span>
							</button>
						</td>
						<td><?php echo esc_html( number_format( $key['request_count'] ) ); ?></td>
						<td>
							<?php
							if ( $key['last_used_at'] ) {
								echo esc_html( human_time_diff( strtotime( $key['last_used_at'] ), current_time( 'timestamp' ) ) . ' ago' );
								echo '<br><small>' . esc_html( $key['last_ip'] ) . '</small>';
							} else {
								esc_html_e( 'Never', 'bkx-api-builder' );
							}
							?>
						</td>
						<td>
							<span class="bkx-status bkx-status-<?php echo esc_attr( $key['status'] ); ?>">
								<?php echo esc_html( ucfirst( $key['status'] ) ); ?>
							</span>
						</td>
						<td>
							<button type="button" class="button bkx-view-key" data-id="<?php echo esc_attr( $key['id'] ); ?>">
								<?php esc_html_e( 'View', 'bkx-api-builder' ); ?>
							</button>
							<button type="button" class="button bkx-revoke-key" data-id="<?php echo esc_attr( $key['id'] ); ?>">
								<?php esc_html_e( 'Revoke', 'bkx-api-builder' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<!-- Generate Key Modal -->
<div id="bkx-key-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'Generate API Key', 'bkx-api-builder' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<form id="bkx-key-form">
			<div class="bkx-modal-body">
				<div class="bkx-form-row">
					<label for="key_name"><?php esc_html_e( 'Name', 'bkx-api-builder' ); ?></label>
					<input type="text" name="name" id="key_name" class="regular-text" required placeholder="<?php esc_attr_e( 'e.g., Mobile App', 'bkx-api-builder' ); ?>">
				</div>
				<div class="bkx-form-row">
					<label for="key_rate_limit"><?php esc_html_e( 'Rate Limit (requests/hour)', 'bkx-api-builder' ); ?></label>
					<input type="number" name="rate_limit" id="key_rate_limit" value="1000" min="0">
				</div>
				<div class="bkx-form-row">
					<label for="key_expires"><?php esc_html_e( 'Expires', 'bkx-api-builder' ); ?></label>
					<input type="date" name="expires_at" id="key_expires">
					<small><?php esc_html_e( 'Leave empty for no expiration', 'bkx-api-builder' ); ?></small>
				</div>
				<div class="bkx-form-row">
					<label for="key_ips"><?php esc_html_e( 'Allowed IPs (one per line)', 'bkx-api-builder' ); ?></label>
					<textarea name="allowed_ips" id="key_ips" rows="3" placeholder="<?php esc_attr_e( 'Leave empty to allow all', 'bkx-api-builder' ); ?>"></textarea>
				</div>
			</div>
			<div class="bkx-modal-footer">
				<button type="button" class="button bkx-modal-cancel"><?php esc_html_e( 'Cancel', 'bkx-api-builder' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Generate', 'bkx-api-builder' ); ?></button>
			</div>
		</form>
	</div>
</div>

<!-- Credentials Modal -->
<div id="bkx-credentials-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'API Credentials', 'bkx-api-builder' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<div class="bkx-modal-body">
			<div class="notice notice-warning">
				<p><?php esc_html_e( 'Save these credentials now. The secret will not be shown again.', 'bkx-api-builder' ); ?></p>
			</div>
			<div class="bkx-form-row">
				<label><?php esc_html_e( 'API Key', 'bkx-api-builder' ); ?></label>
				<div class="bkx-credential-display">
					<code id="new-api-key"></code>
					<button type="button" class="button bkx-copy-credential" data-target="new-api-key">
						<span class="dashicons dashicons-clipboard"></span>
					</button>
				</div>
			</div>
			<div class="bkx-form-row">
				<label><?php esc_html_e( 'API Secret', 'bkx-api-builder' ); ?></label>
				<div class="bkx-credential-display">
					<code id="new-api-secret"></code>
					<button type="button" class="button bkx-copy-credential" data-target="new-api-secret">
						<span class="dashicons dashicons-clipboard"></span>
					</button>
				</div>
			</div>
		</div>
		<div class="bkx-modal-footer">
			<button type="button" class="button button-primary bkx-modal-close"><?php esc_html_e( 'Done', 'bkx-api-builder' ); ?></button>
		</div>
	</div>
</div>
