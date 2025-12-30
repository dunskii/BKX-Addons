<?php
/**
 * Network Settings Template.
 *
 * @package BookingX\MultiTenant
 */

defined( 'ABSPATH' ) || exit;

// Handle settings save.
if ( isset( $_POST['bkx_save_settings'] ) && check_admin_referer( 'bkx_multi_tenant_settings' ) ) {
	$settings = array(
		'enabled'                => isset( $_POST['enabled'] ),
		'isolation_mode'         => sanitize_text_field( $_POST['isolation_mode'] ),
		'default_plan'           => absint( $_POST['default_plan'] ),
		'trial_days'             => absint( $_POST['trial_days'] ),
		'auto_suspend_overdue'   => isset( $_POST['auto_suspend_overdue'] ),
		'suspend_grace_days'     => absint( $_POST['suspend_grace_days'] ),
		'allow_custom_domains'   => isset( $_POST['allow_custom_domains'] ),
		'subdomain_base'         => sanitize_text_field( $_POST['subdomain_base'] ),
		'require_email_verify'   => isset( $_POST['require_email_verify'] ),
		'default_branding'       => array(
			'primary_color'   => sanitize_hex_color( $_POST['primary_color'] ),
			'secondary_color' => sanitize_hex_color( $_POST['secondary_color'] ),
		),
		'notification_email'     => sanitize_email( $_POST['notification_email'] ),
		'notify_new_tenant'      => isset( $_POST['notify_new_tenant'] ),
		'notify_plan_change'     => isset( $_POST['notify_plan_change'] ),
		'notify_limit_warning'   => isset( $_POST['notify_limit_warning'] ),
		'limit_warning_percent'  => absint( $_POST['limit_warning_percent'] ),
	);

	update_site_option( 'bkx_multi_tenant_settings', $settings );
	$success_message = __( 'Settings saved successfully.', 'bkx-multi-tenant' );
}

$settings = get_site_option( 'bkx_multi_tenant_settings', array() );
$defaults = array(
	'enabled'               => true,
	'isolation_mode'        => 'meta',
	'default_plan'          => 0,
	'trial_days'            => 14,
	'auto_suspend_overdue'  => true,
	'suspend_grace_days'    => 7,
	'allow_custom_domains'  => true,
	'subdomain_base'        => '',
	'require_email_verify'  => true,
	'default_branding'      => array(
		'primary_color'   => '#2563eb',
		'secondary_color' => '#1e40af',
	),
	'notification_email'    => get_site_option( 'admin_email' ),
	'notify_new_tenant'     => true,
	'notify_plan_change'    => true,
	'notify_limit_warning'  => true,
	'limit_warning_percent' => 80,
);
$settings = wp_parse_args( $settings, $defaults );

$plan_manager = \BookingX\MultiTenant\MultiTenantAddon::get_instance()->get_service( 'plan_manager' );
$plans = $plan_manager->get_plans();
?>
<div class="wrap bkx-settings-page">
	<h1><?php esc_html_e( 'Multi-Tenant Settings', 'bkx-multi-tenant' ); ?></h1>

	<?php if ( ! empty( $success_message ) ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( $success_message ); ?></p></div>
	<?php endif; ?>

	<form method="post" class="bkx-form">
		<?php wp_nonce_field( 'bkx_multi_tenant_settings' ); ?>

		<div class="bkx-settings-sections">
			<!-- General Settings -->
			<div class="bkx-settings-section">
				<h2><?php esc_html_e( 'General Settings', 'bkx-multi-tenant' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable Multi-Tenancy', 'bkx-multi-tenant' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( $settings['enabled'] ); ?>>
								<?php esc_html_e( 'Enable multi-tenant functionality across the network', 'bkx-multi-tenant' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="isolation_mode"><?php esc_html_e( 'Data Isolation Mode', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<select name="isolation_mode" id="isolation_mode">
								<option value="meta" <?php selected( $settings['isolation_mode'], 'meta' ); ?>>
									<?php esc_html_e( 'Meta-based (shared tables with tenant ID)', 'bkx-multi-tenant' ); ?>
								</option>
								<option value="site" <?php selected( $settings['isolation_mode'], 'site' ); ?>>
									<?php esc_html_e( 'Site-based (separate WordPress sites)', 'bkx-multi-tenant' ); ?>
								</option>
								<option value="table" <?php selected( $settings['isolation_mode'], 'table' ); ?>>
									<?php esc_html_e( 'Table-based (separate tables per tenant)', 'bkx-multi-tenant' ); ?>
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Meta-based is recommended for most installations.', 'bkx-multi-tenant' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="default_plan"><?php esc_html_e( 'Default Plan', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<select name="default_plan" id="default_plan">
								<option value="0"><?php esc_html_e( 'None', 'bkx-multi-tenant' ); ?></option>
								<?php foreach ( $plans as $plan ) : ?>
									<option value="<?php echo esc_attr( $plan->id ); ?>" <?php selected( $settings['default_plan'], $plan->id ); ?>>
										<?php echo esc_html( $plan->name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Plan assigned to new tenants by default.', 'bkx-multi-tenant' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Trial & Billing -->
			<div class="bkx-settings-section">
				<h2><?php esc_html_e( 'Trial & Billing', 'bkx-multi-tenant' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="trial_days"><?php esc_html_e( 'Trial Period (Days)', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<input type="number" name="trial_days" id="trial_days" min="0" class="small-text" value="<?php echo esc_attr( $settings['trial_days'] ); ?>">
							<p class="description"><?php esc_html_e( 'Set to 0 to disable trial period.', 'bkx-multi-tenant' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Auto-Suspend Overdue', 'bkx-multi-tenant' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="auto_suspend_overdue" value="1" <?php checked( $settings['auto_suspend_overdue'] ); ?>>
								<?php esc_html_e( 'Automatically suspend tenants with overdue payments', 'bkx-multi-tenant' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="suspend_grace_days"><?php esc_html_e( 'Grace Period (Days)', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<input type="number" name="suspend_grace_days" id="suspend_grace_days" min="0" class="small-text" value="<?php echo esc_attr( $settings['suspend_grace_days'] ); ?>">
							<p class="description"><?php esc_html_e( 'Days after payment due date before suspension.', 'bkx-multi-tenant' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Domain Settings -->
			<div class="bkx-settings-section">
				<h2><?php esc_html_e( 'Domain Settings', 'bkx-multi-tenant' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Custom Domains', 'bkx-multi-tenant' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="allow_custom_domains" value="1" <?php checked( $settings['allow_custom_domains'] ); ?>>
								<?php esc_html_e( 'Allow tenants to use custom domains', 'bkx-multi-tenant' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="subdomain_base"><?php esc_html_e( 'Subdomain Base', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<input type="text" name="subdomain_base" id="subdomain_base" class="regular-text" value="<?php echo esc_attr( $settings['subdomain_base'] ); ?>" placeholder="example.com">
							<p class="description">
								<?php esc_html_e( 'Base domain for tenant subdomains (e.g., tenant.example.com).', 'bkx-multi-tenant' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>

			<!-- Registration -->
			<div class="bkx-settings-section">
				<h2><?php esc_html_e( 'Registration', 'bkx-multi-tenant' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Email Verification', 'bkx-multi-tenant' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="require_email_verify" value="1" <?php checked( $settings['require_email_verify'] ); ?>>
								<?php esc_html_e( 'Require email verification for new tenant admins', 'bkx-multi-tenant' ); ?>
							</label>
						</td>
					</tr>
				</table>
			</div>

			<!-- Default Branding -->
			<div class="bkx-settings-section">
				<h2><?php esc_html_e( 'Default Branding', 'bkx-multi-tenant' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="primary_color"><?php esc_html_e( 'Primary Color', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<input type="color" name="primary_color" id="primary_color" value="<?php echo esc_attr( $settings['default_branding']['primary_color'] ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="secondary_color"><?php esc_html_e( 'Secondary Color', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<input type="color" name="secondary_color" id="secondary_color" value="<?php echo esc_attr( $settings['default_branding']['secondary_color'] ); ?>">
						</td>
					</tr>
				</table>
			</div>

			<!-- Notifications -->
			<div class="bkx-settings-section">
				<h2><?php esc_html_e( 'Notifications', 'bkx-multi-tenant' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="notification_email"><?php esc_html_e( 'Admin Email', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<input type="email" name="notification_email" id="notification_email" class="regular-text" value="<?php echo esc_attr( $settings['notification_email'] ); ?>">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Email Triggers', 'bkx-multi-tenant' ); ?></th>
						<td>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="notify_new_tenant" value="1" <?php checked( $settings['notify_new_tenant'] ); ?>>
								<?php esc_html_e( 'New tenant registration', 'bkx-multi-tenant' ); ?>
							</label>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="notify_plan_change" value="1" <?php checked( $settings['notify_plan_change'] ); ?>>
								<?php esc_html_e( 'Plan upgrade/downgrade', 'bkx-multi-tenant' ); ?>
							</label>
							<label style="display: block; margin-bottom: 8px;">
								<input type="checkbox" name="notify_limit_warning" value="1" <?php checked( $settings['notify_limit_warning'] ); ?>>
								<?php esc_html_e( 'Usage limit warning', 'bkx-multi-tenant' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="limit_warning_percent"><?php esc_html_e( 'Warning Threshold', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<input type="number" name="limit_warning_percent" id="limit_warning_percent" min="50" max="100" class="small-text" value="<?php echo esc_attr( $settings['limit_warning_percent'] ); ?>">%
							<p class="description"><?php esc_html_e( 'Send warning when usage reaches this percentage.', 'bkx-multi-tenant' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		</div>

		<p class="submit">
			<button type="submit" name="bkx_save_settings" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'bkx-multi-tenant' ); ?>
			</button>
		</p>
	</form>
</div>
