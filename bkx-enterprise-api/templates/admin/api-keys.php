<?php
/**
 * API Keys Management Template.
 *
 * @package BookingX\EnterpriseAPI
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

$keys = $wpdb->get_results(
	"SELECT * FROM {$wpdb->prefix}bkx_api_keys ORDER BY created_at DESC"
);
?>
<div class="bkx-api-keys">
	<div class="bkx-section-header">
		<h2><?php esc_html_e( 'API Keys', 'bkx-enterprise-api' ); ?></h2>
		<button type="button" class="button button-primary" id="bkx-create-key-btn">
			<span class="dashicons dashicons-plus-alt2"></span>
			<?php esc_html_e( 'Create API Key', 'bkx-enterprise-api' ); ?>
		</button>
	</div>

	<p class="description">
		<?php esc_html_e( 'API keys allow programmatic access to the BookingX API. Each key can have specific permissions and rate limits.', 'bkx-enterprise-api' ); ?>
	</p>

	<!-- Create Key Modal -->
	<div id="bkx-create-key-modal" class="bkx-modal" style="display: none;">
		<div class="bkx-modal-content">
			<span class="bkx-modal-close">&times;</span>
			<h3><?php esc_html_e( 'Create API Key', 'bkx-enterprise-api' ); ?></h3>
			<form id="bkx-create-key-form">
				<table class="form-table">
					<tr>
						<th><label for="key_name"><?php esc_html_e( 'Name', 'bkx-enterprise-api' ); ?></label></th>
						<td>
							<input type="text" name="name" id="key_name" class="regular-text" required>
							<p class="description"><?php esc_html_e( 'A descriptive name for this key.', 'bkx-enterprise-api' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="key_description"><?php esc_html_e( 'Description', 'bkx-enterprise-api' ); ?></label></th>
						<td>
							<textarea name="description" id="key_description" class="large-text" rows="3"></textarea>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Permissions', 'bkx-enterprise-api' ); ?></th>
						<td>
							<label><input type="checkbox" name="permissions[]" value="read" checked> <?php esc_html_e( 'Read', 'bkx-enterprise-api' ); ?></label><br>
							<label><input type="checkbox" name="permissions[]" value="write"> <?php esc_html_e( 'Write', 'bkx-enterprise-api' ); ?></label><br>
							<label><input type="checkbox" name="permissions[]" value="delete"> <?php esc_html_e( 'Delete', 'bkx-enterprise-api' ); ?></label><br>
							<label><input type="checkbox" name="permissions[]" value="admin"> <?php esc_html_e( 'Admin', 'bkx-enterprise-api' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><label for="rate_limit"><?php esc_html_e( 'Rate Limit', 'bkx-enterprise-api' ); ?></label></th>
						<td>
							<input type="number" name="rate_limit" id="rate_limit" value="1000" min="100" class="small-text">
							<span><?php esc_html_e( 'requests per hour', 'bkx-enterprise-api' ); ?></span>
						</td>
					</tr>
					<tr>
						<th><label for="expires_at"><?php esc_html_e( 'Expires', 'bkx-enterprise-api' ); ?></label></th>
						<td>
							<input type="date" name="expires_at" id="expires_at">
							<p class="description"><?php esc_html_e( 'Leave empty for no expiration.', 'bkx-enterprise-api' ); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Create Key', 'bkx-enterprise-api' ); ?></button>
					<button type="button" class="button bkx-modal-close"><?php esc_html_e( 'Cancel', 'bkx-enterprise-api' ); ?></button>
				</p>
			</form>
		</div>
	</div>

	<!-- Key Created Modal -->
	<div id="bkx-key-created-modal" class="bkx-modal" style="display: none;">
		<div class="bkx-modal-content">
			<span class="bkx-modal-close">&times;</span>
			<h3><?php esc_html_e( 'API Key Created', 'bkx-enterprise-api' ); ?></h3>
			<div class="notice notice-warning inline">
				<p><?php esc_html_e( 'Make sure to copy your API key now. You will not be able to see it again!', 'bkx-enterprise-api' ); ?></p>
			</div>
			<div class="bkx-key-display">
				<code id="bkx-new-key-value"></code>
				<button type="button" class="button bkx-copy-btn" data-target="bkx-new-key-value">
					<span class="dashicons dashicons-clipboard"></span>
				</button>
			</div>
			<p class="submit">
				<button type="button" class="button button-primary bkx-modal-close"><?php esc_html_e( 'Done', 'bkx-enterprise-api' ); ?></button>
			</p>
		</div>
	</div>

	<!-- Keys Table -->
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 200px;"><?php esc_html_e( 'Name', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 200px;"><?php esc_html_e( 'Key ID', 'bkx-enterprise-api' ); ?></th>
				<th><?php esc_html_e( 'Permissions', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 100px;"><?php esc_html_e( 'Rate Limit', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 150px;"><?php esc_html_e( 'Last Used', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 80px;"><?php esc_html_e( 'Status', 'bkx-enterprise-api' ); ?></th>
				<th style="width: 100px;"><?php esc_html_e( 'Actions', 'bkx-enterprise-api' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $keys ) ) : ?>
				<tr>
					<td colspan="7"><?php esc_html_e( 'No API keys found. Create one to get started.', 'bkx-enterprise-api' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $keys as $key ) : ?>
					<?php $permissions = json_decode( $key->permissions, true ) ?: array(); ?>
					<tr data-key-id="<?php echo esc_attr( $key->key_id ); ?>">
						<td>
							<strong><?php echo esc_html( $key->name ); ?></strong>
							<?php if ( $key->description ) : ?>
								<br><small><?php echo esc_html( $key->description ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<code><?php echo esc_html( substr( $key->key_id, 0, 8 ) . '...' . substr( $key->key_id, -4 ) ); ?></code>
						</td>
						<td>
							<?php foreach ( $permissions as $perm ) : ?>
								<span class="bkx-permission-badge"><?php echo esc_html( ucfirst( $perm ) ); ?></span>
							<?php endforeach; ?>
						</td>
						<td><?php echo esc_html( number_format( $key->rate_limit ) ); ?>/hr</td>
						<td>
							<?php if ( $key->last_used ) : ?>
								<?php echo esc_html( human_time_diff( strtotime( $key->last_used ) ) ); ?> <?php esc_html_e( 'ago', 'bkx-enterprise-api' ); ?>
								<br><small><?php echo esc_html( $key->last_ip ); ?></small>
							<?php else : ?>
								<em><?php esc_html_e( 'Never', 'bkx-enterprise-api' ); ?></em>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $key->is_active ) : ?>
								<span class="bkx-status-badge bkx-status-active"><?php esc_html_e( 'Active', 'bkx-enterprise-api' ); ?></span>
							<?php else : ?>
								<span class="bkx-status-badge bkx-status-inactive"><?php esc_html_e( 'Inactive', 'bkx-enterprise-api' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<button type="button" class="button button-small bkx-revoke-key" data-key-id="<?php echo esc_attr( $key->key_id ); ?>">
								<?php esc_html_e( 'Revoke', 'bkx-enterprise-api' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	var createModal = document.getElementById('bkx-create-key-modal');
	var createdModal = document.getElementById('bkx-key-created-modal');

	// Open create modal.
	document.getElementById('bkx-create-key-btn').addEventListener('click', function() {
		createModal.style.display = 'flex';
	});

	// Close modals.
	document.querySelectorAll('.bkx-modal-close').forEach(function(btn) {
		btn.addEventListener('click', function() {
			createModal.style.display = 'none';
			createdModal.style.display = 'none';
		});
	});

	// Create key form.
	document.getElementById('bkx-create-key-form').addEventListener('submit', function(e) {
		e.preventDefault();
		var formData = new FormData(this);

		wp.apiFetch({
			path: '/bookingx/v1/api-keys',
			method: 'POST',
			data: {
				name: formData.get('name'),
				description: formData.get('description'),
				permissions: formData.getAll('permissions[]'),
				rate_limit: formData.get('rate_limit'),
				expires_at: formData.get('expires_at')
			}
		}).then(function(response) {
			createModal.style.display = 'none';
			document.getElementById('bkx-new-key-value').textContent = response.api_key;
			createdModal.style.display = 'flex';
			location.reload();
		}).catch(function(error) {
			alert(error.message || 'Error creating API key');
		});
	});

	// Revoke key.
	document.querySelectorAll('.bkx-revoke-key').forEach(function(btn) {
		btn.addEventListener('click', function() {
			if (!confirm('<?php esc_html_e( 'Are you sure you want to revoke this API key?', 'bkx-enterprise-api' ); ?>')) {
				return;
			}

			var keyId = this.dataset.keyId;

			wp.apiFetch({
				path: '/bookingx/v1/api-keys/' + keyId,
				method: 'DELETE'
			}).then(function() {
				document.querySelector('tr[data-key-id="' + keyId + '"]').remove();
			}).catch(function(error) {
				alert(error.message || 'Error revoking API key');
			});
		});
	});

	// Copy button.
	document.querySelectorAll('.bkx-copy-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var target = document.getElementById(this.dataset.target);
			navigator.clipboard.writeText(target.textContent);
			this.innerHTML = '<span class="dashicons dashicons-yes"></span>';
			setTimeout(function() {
				btn.innerHTML = '<span class="dashicons dashicons-clipboard"></span>';
			}, 2000);
		});
	});
});
</script>
