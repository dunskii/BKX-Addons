<?php
/**
 * Tenant Settings Template (per-tenant admin page).
 *
 * @package BookingX\MultiTenant
 */

defined( 'ABSPATH' ) || exit;

$addon           = \BookingX\MultiTenant\MultiTenantAddon::get_instance();
$tenant          = $addon->get_current_tenant();
$branding_svc    = $addon->get_service( 'branding' );
$user_manager    = $addon->get_service( 'user_manager' );
$usage_tracker   = $addon->get_service( 'usage_tracker' );
$plan_manager    = $addon->get_service( 'plan_manager' );

if ( ! $tenant ) {
	wp_die( esc_html__( 'No tenant context found.', 'bkx-multi-tenant' ) );
}

// Handle settings save.
if ( isset( $_POST['bkx_save_tenant_settings'] ) && check_admin_referer( 'bkx_tenant_settings' ) ) {
	// Check permission.
	if ( ! $user_manager->user_can( $tenant->id, get_current_user_id(), 'manage_settings' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage settings.', 'bkx-multi-tenant' ) );
	}

	// Update branding.
	$branding_svc->update_branding( $tenant->id, array(
		'logo_url'         => esc_url_raw( $_POST['logo_url'] ),
		'favicon_url'      => esc_url_raw( $_POST['favicon_url'] ),
		'primary_color'    => sanitize_hex_color( $_POST['primary_color'] ),
		'secondary_color'  => sanitize_hex_color( $_POST['secondary_color'] ),
		'accent_color'     => sanitize_hex_color( $_POST['accent_color'] ),
		'text_color'       => sanitize_hex_color( $_POST['text_color'] ),
		'background_color' => sanitize_hex_color( $_POST['background_color'] ),
		'button_style'     => sanitize_text_field( $_POST['button_style'] ),
		'font_family'      => sanitize_text_field( $_POST['font_family'] ),
		'custom_css'       => wp_strip_all_tags( $_POST['custom_css'] ),
		'hide_powered_by'  => isset( $_POST['hide_powered_by'] ),
		'white_label'      => isset( $_POST['white_label'] ),
	) );

	$success_message = __( 'Settings saved successfully.', 'bkx-multi-tenant' );
}

$branding = $branding_svc->get_branding( $tenant->id );
$usage    = $usage_tracker->get_tenant_usage_summary( $tenant->id );
$plan     = $plan_manager->get_tenant_plan( $tenant->id );
$users    = $user_manager->get_tenant_users( $tenant->id, array( 'limit' => 10 ) );
$can_white_label = $plan && $plan_manager->tenant_has_feature( $tenant->id, 'white_label' );
?>
<div class="wrap bkx-tenant-settings">
	<h1><?php esc_html_e( 'Tenant Settings', 'bkx-multi-tenant' ); ?></h1>

	<?php if ( ! empty( $success_message ) ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( $success_message ); ?></p></div>
	<?php endif; ?>

	<div class="bkx-tenant-overview">
		<div class="bkx-tenant-header">
			<h2><?php echo esc_html( $tenant->name ); ?></h2>
			<?php if ( $plan ) : ?>
				<span class="bkx-plan-badge"><?php echo esc_html( $plan->name ); ?></span>
			<?php endif; ?>
		</div>

		<!-- Quick Stats -->
		<div class="bkx-quick-stats">
			<?php foreach ( $usage as $metric => $data ) : ?>
				<?php if ( strpos( $metric, '_total' ) !== false ) continue; ?>
				<?php
				$current = is_array( $data ) ? $data['current'] : $data;
				$limit   = is_array( $data ) ? $data['limit'] : -1;
				$percent = is_array( $data ) ? $data['percent'] : 0;
				?>
				<div class="bkx-stat-item">
					<span class="bkx-stat-label"><?php echo esc_html( ucwords( str_replace( '_', ' ', $metric ) ) ); ?></span>
					<span class="bkx-stat-value">
						<?php echo esc_html( number_format( $current ) ); ?>
						<?php if ( $limit > 0 ) : ?>
							<small>/ <?php echo esc_html( number_format( $limit ) ); ?></small>
						<?php endif; ?>
					</span>
					<?php if ( $limit > 0 ) : ?>
						<div class="bkx-mini-bar">
							<div class="bkx-mini-fill <?php echo $percent > 80 ? 'bkx-warning' : ''; ?>" style="width: <?php echo esc_attr( min( $percent, 100 ) ); ?>%"></div>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<form method="post" class="bkx-form">
		<?php wp_nonce_field( 'bkx_tenant_settings' ); ?>

		<h2 class="nav-tab-wrapper">
			<a href="#branding" class="nav-tab nav-tab-active"><?php esc_html_e( 'Branding', 'bkx-multi-tenant' ); ?></a>
			<a href="#team" class="nav-tab"><?php esc_html_e( 'Team', 'bkx-multi-tenant' ); ?></a>
		</h2>

		<!-- Branding Tab -->
		<div id="branding" class="bkx-tab-content bkx-tab-active">
			<table class="form-table">
				<tr>
					<th><label for="logo_url"><?php esc_html_e( 'Logo URL', 'bkx-multi-tenant' ); ?></label></th>
					<td>
						<input type="url" name="logo_url" id="logo_url" class="regular-text" value="<?php echo esc_attr( $branding['logo_url'] ); ?>">
						<button type="button" class="button bkx-upload-btn" data-target="logo_url"><?php esc_html_e( 'Upload', 'bkx-multi-tenant' ); ?></button>
						<?php if ( $branding['logo_url'] ) : ?>
							<div class="bkx-preview"><img src="<?php echo esc_url( $branding['logo_url'] ); ?>" alt="" style="max-height: 50px;"></div>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><label for="favicon_url"><?php esc_html_e( 'Favicon URL', 'bkx-multi-tenant' ); ?></label></th>
					<td>
						<input type="url" name="favicon_url" id="favicon_url" class="regular-text" value="<?php echo esc_attr( $branding['favicon_url'] ); ?>">
						<button type="button" class="button bkx-upload-btn" data-target="favicon_url"><?php esc_html_e( 'Upload', 'bkx-multi-tenant' ); ?></button>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Colors', 'bkx-multi-tenant' ); ?></th>
					<td>
						<div class="bkx-color-grid">
							<div class="bkx-color-field">
								<label for="primary_color"><?php esc_html_e( 'Primary', 'bkx-multi-tenant' ); ?></label>
								<input type="color" name="primary_color" id="primary_color" value="<?php echo esc_attr( $branding['primary_color'] ); ?>">
							</div>
							<div class="bkx-color-field">
								<label for="secondary_color"><?php esc_html_e( 'Secondary', 'bkx-multi-tenant' ); ?></label>
								<input type="color" name="secondary_color" id="secondary_color" value="<?php echo esc_attr( $branding['secondary_color'] ); ?>">
							</div>
							<div class="bkx-color-field">
								<label for="accent_color"><?php esc_html_e( 'Accent', 'bkx-multi-tenant' ); ?></label>
								<input type="color" name="accent_color" id="accent_color" value="<?php echo esc_attr( $branding['accent_color'] ); ?>">
							</div>
							<div class="bkx-color-field">
								<label for="text_color"><?php esc_html_e( 'Text', 'bkx-multi-tenant' ); ?></label>
								<input type="color" name="text_color" id="text_color" value="<?php echo esc_attr( $branding['text_color'] ); ?>">
							</div>
							<div class="bkx-color-field">
								<label for="background_color"><?php esc_html_e( 'Background', 'bkx-multi-tenant' ); ?></label>
								<input type="color" name="background_color" id="background_color" value="<?php echo esc_attr( $branding['background_color'] ); ?>">
							</div>
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="button_style"><?php esc_html_e( 'Button Style', 'bkx-multi-tenant' ); ?></label></th>
					<td>
						<select name="button_style" id="button_style">
							<option value="default" <?php selected( $branding['button_style'], 'default' ); ?>><?php esc_html_e( 'Default', 'bkx-multi-tenant' ); ?></option>
							<option value="rounded" <?php selected( $branding['button_style'], 'rounded' ); ?>><?php esc_html_e( 'Rounded', 'bkx-multi-tenant' ); ?></option>
							<option value="pill" <?php selected( $branding['button_style'], 'pill' ); ?>><?php esc_html_e( 'Pill', 'bkx-multi-tenant' ); ?></option>
							<option value="square" <?php selected( $branding['button_style'], 'square' ); ?>><?php esc_html_e( 'Square', 'bkx-multi-tenant' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="font_family"><?php esc_html_e( 'Font Family', 'bkx-multi-tenant' ); ?></label></th>
					<td>
						<select name="font_family" id="font_family">
							<option value="system" <?php selected( $branding['font_family'], 'system' ); ?>><?php esc_html_e( 'System Default', 'bkx-multi-tenant' ); ?></option>
							<option value="Inter" <?php selected( $branding['font_family'], 'Inter' ); ?>>Inter</option>
							<option value="Roboto" <?php selected( $branding['font_family'], 'Roboto' ); ?>>Roboto</option>
							<option value="Open Sans" <?php selected( $branding['font_family'], 'Open Sans' ); ?>>Open Sans</option>
							<option value="Lato" <?php selected( $branding['font_family'], 'Lato' ); ?>>Lato</option>
							<option value="Poppins" <?php selected( $branding['font_family'], 'Poppins' ); ?>>Poppins</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="custom_css"><?php esc_html_e( 'Custom CSS', 'bkx-multi-tenant' ); ?></label></th>
					<td>
						<textarea name="custom_css" id="custom_css" class="large-text code" rows="8"><?php echo esc_textarea( $branding['custom_css'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Add custom CSS to override default styles.', 'bkx-multi-tenant' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Powered By', 'bkx-multi-tenant' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="hide_powered_by" value="1" <?php checked( $branding['hide_powered_by'] ); ?>>
							<?php esc_html_e( 'Hide "Powered by BookingX" badge', 'bkx-multi-tenant' ); ?>
						</label>
					</td>
				</tr>
				<?php if ( $can_white_label ) : ?>
				<tr>
					<th><?php esc_html_e( 'White Label', 'bkx-multi-tenant' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="white_label" value="1" <?php checked( $branding['white_label'] ); ?>>
							<?php esc_html_e( 'Enable full white-label mode', 'bkx-multi-tenant' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Removes all BookingX branding from the interface.', 'bkx-multi-tenant' ); ?></p>
					</td>
				</tr>
				<?php else : ?>
				<tr>
					<th><?php esc_html_e( 'White Label', 'bkx-multi-tenant' ); ?></th>
					<td>
						<p class="description">
							<span class="dashicons dashicons-lock"></span>
							<?php esc_html_e( 'Upgrade your plan to enable white-label mode.', 'bkx-multi-tenant' ); ?>
						</p>
					</td>
				</tr>
				<?php endif; ?>
			</table>
		</div>

		<!-- Team Tab -->
		<div id="team" class="bkx-tab-content">
			<h3><?php esc_html_e( 'Team Members', 'bkx-multi-tenant' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'bkx-multi-tenant' ); ?></th>
						<th><?php esc_html_e( 'Email', 'bkx-multi-tenant' ); ?></th>
						<th><?php esc_html_e( 'Role', 'bkx-multi-tenant' ); ?></th>
						<th><?php esc_html_e( 'Joined', 'bkx-multi-tenant' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $users ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No team members found.', 'bkx-multi-tenant' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $users as $user ) : ?>
							<tr>
								<td><?php echo esc_html( $user->display_name ); ?></td>
								<td><?php echo esc_html( $user->user_email ); ?></td>
								<td>
									<span class="bkx-role-badge bkx-role-<?php echo esc_attr( $user->role ); ?>">
										<?php echo esc_html( ucfirst( $user->role ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $user->created_at ) ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<p class="submit">
			<button type="submit" name="bkx_save_tenant_settings" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-multi-tenant' ); ?>
			</button>
		</p>
	</form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Tab navigation.
	document.querySelectorAll('.nav-tab').forEach(function(tab) {
		tab.addEventListener('click', function(e) {
			e.preventDefault();
			document.querySelectorAll('.nav-tab').forEach(function(t) { t.classList.remove('nav-tab-active'); });
			document.querySelectorAll('.bkx-tab-content').forEach(function(c) { c.classList.remove('bkx-tab-active'); });
			this.classList.add('nav-tab-active');
			document.querySelector(this.getAttribute('href')).classList.add('bkx-tab-active');
		});
	});

	// Media uploader.
	document.querySelectorAll('.bkx-upload-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var target = document.getElementById(this.dataset.target);
			var frame = wp.media({ multiple: false });
			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				target.value = attachment.url;
			});
			frame.open();
		});
	});
});
</script>
