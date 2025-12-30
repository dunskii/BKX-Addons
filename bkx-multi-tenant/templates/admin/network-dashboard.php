<?php
/**
 * Network Dashboard Template.
 *
 * @package BookingX\MultiTenant
 */

defined( 'ABSPATH' ) || exit;

$tenant_manager = \BookingX\MultiTenant\MultiTenantAddon::get_instance()->get_service( 'tenant_manager' );
$plan_manager   = \BookingX\MultiTenant\MultiTenantAddon::get_instance()->get_service( 'plan_manager' );

// Handle actions.
if ( isset( $_POST['bkx_create_tenant'] ) && check_admin_referer( 'bkx_create_tenant' ) ) {
	$result = $tenant_manager->create_tenant( array(
		'name'    => sanitize_text_field( $_POST['tenant_name'] ),
		'domain'  => sanitize_text_field( $_POST['tenant_domain'] ),
		'plan_id' => absint( $_POST['tenant_plan'] ),
	) );

	if ( is_wp_error( $result ) ) {
		$error_message = $result->get_error_message();
	} else {
		$success_message = __( 'Tenant created successfully.', 'bkx-multi-tenant' );
	}
}

// Get tenants.
$status  = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
$tenants = $tenant_manager->get_tenants( array( 'status' => $status ) );
$plans   = $plan_manager->get_plans();

// Get statistics.
global $wpdb;
$stats = array(
	'total'     => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}bkx_tenants" ),
	'active'    => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}bkx_tenants WHERE status = 'active'" ),
	'suspended' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}bkx_tenants WHERE status = 'suspended'" ),
	'trial'     => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->base_prefix}bkx_tenants WHERE status = 'trial'" ),
);
?>
<div class="wrap bkx-multi-tenant-dashboard">
	<h1><?php esc_html_e( 'Multi-Tenant Management', 'bkx-multi-tenant' ); ?></h1>

	<?php if ( ! empty( $error_message ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error_message ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $success_message ) ) : ?>
		<div class="notice notice-success"><p><?php echo esc_html( $success_message ); ?></p></div>
	<?php endif; ?>

	<!-- Statistics Cards -->
	<div class="bkx-stats-grid">
		<div class="bkx-stat-card">
			<span class="bkx-stat-icon dashicons dashicons-groups"></span>
			<div class="bkx-stat-content">
				<span class="bkx-stat-value"><?php echo esc_html( $stats['total'] ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Total Tenants', 'bkx-multi-tenant' ); ?></span>
			</div>
		</div>
		<div class="bkx-stat-card bkx-stat-success">
			<span class="bkx-stat-icon dashicons dashicons-yes-alt"></span>
			<div class="bkx-stat-content">
				<span class="bkx-stat-value"><?php echo esc_html( $stats['active'] ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Active', 'bkx-multi-tenant' ); ?></span>
			</div>
		</div>
		<div class="bkx-stat-card bkx-stat-warning">
			<span class="bkx-stat-icon dashicons dashicons-clock"></span>
			<div class="bkx-stat-content">
				<span class="bkx-stat-value"><?php echo esc_html( $stats['trial'] ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Trial', 'bkx-multi-tenant' ); ?></span>
			</div>
		</div>
		<div class="bkx-stat-card bkx-stat-danger">
			<span class="bkx-stat-icon dashicons dashicons-dismiss"></span>
			<div class="bkx-stat-content">
				<span class="bkx-stat-value"><?php echo esc_html( $stats['suspended'] ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Suspended', 'bkx-multi-tenant' ); ?></span>
			</div>
		</div>
	</div>

	<div class="bkx-tenant-layout">
		<!-- Create Tenant Form -->
		<div class="bkx-tenant-create">
			<h2><?php esc_html_e( 'Create New Tenant', 'bkx-multi-tenant' ); ?></h2>
			<form method="post" class="bkx-form">
				<?php wp_nonce_field( 'bkx_create_tenant' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="tenant_name"><?php esc_html_e( 'Name', 'bkx-multi-tenant' ); ?></label></th>
						<td><input type="text" name="tenant_name" id="tenant_name" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="tenant_domain"><?php esc_html_e( 'Domain/Subdomain', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<input type="text" name="tenant_domain" id="tenant_domain" class="regular-text" placeholder="tenant.example.com">
							<p class="description"><?php esc_html_e( 'Leave empty for slug-based URL.', 'bkx-multi-tenant' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="tenant_plan"><?php esc_html_e( 'Plan', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<select name="tenant_plan" id="tenant_plan">
								<?php foreach ( $plans as $plan ) : ?>
									<option value="<?php echo esc_attr( $plan->id ); ?>">
										<?php echo esc_html( $plan->name ); ?> - $<?php echo esc_html( $plan->price ); ?>/<?php echo esc_html( $plan->billing_cycle ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
				<p class="submit">
					<button type="submit" name="bkx_create_tenant" class="button button-primary">
						<?php esc_html_e( 'Create Tenant', 'bkx-multi-tenant' ); ?>
					</button>
				</p>
			</form>
		</div>

		<!-- Tenants List -->
		<div class="bkx-tenant-list">
			<h2><?php esc_html_e( 'Tenants', 'bkx-multi-tenant' ); ?></h2>

			<ul class="subsubsub">
				<li>
					<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=bkx-multi-tenant' ) ); ?>" <?php echo empty( $status ) ? 'class="current"' : ''; ?>>
						<?php esc_html_e( 'All', 'bkx-multi-tenant' ); ?> <span class="count">(<?php echo esc_html( $stats['total'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=bkx-multi-tenant&status=active' ) ); ?>" <?php echo 'active' === $status ? 'class="current"' : ''; ?>>
						<?php esc_html_e( 'Active', 'bkx-multi-tenant' ); ?> <span class="count">(<?php echo esc_html( $stats['active'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=bkx-multi-tenant&status=trial' ) ); ?>" <?php echo 'trial' === $status ? 'class="current"' : ''; ?>>
						<?php esc_html_e( 'Trial', 'bkx-multi-tenant' ); ?> <span class="count">(<?php echo esc_html( $stats['trial'] ); ?>)</span>
					</a> |
				</li>
				<li>
					<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=bkx-multi-tenant&status=suspended' ) ); ?>" <?php echo 'suspended' === $status ? 'class="current"' : ''; ?>>
						<?php esc_html_e( 'Suspended', 'bkx-multi-tenant' ); ?> <span class="count">(<?php echo esc_html( $stats['suspended'] ); ?>)</span>
					</a>
				</li>
			</ul>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'bkx-multi-tenant' ); ?></th>
						<th><?php esc_html_e( 'Domain', 'bkx-multi-tenant' ); ?></th>
						<th><?php esc_html_e( 'Plan', 'bkx-multi-tenant' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-multi-tenant' ); ?></th>
						<th><?php esc_html_e( 'Created', 'bkx-multi-tenant' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-multi-tenant' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $tenants ) ) : ?>
						<tr>
							<td colspan="6"><?php esc_html_e( 'No tenants found.', 'bkx-multi-tenant' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $tenants as $tenant ) : ?>
							<?php $plan = $plan_manager->get_plan( $tenant->plan_id ); ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $tenant->name ); ?></strong>
									<br><small><?php echo esc_html( $tenant->slug ); ?></small>
								</td>
								<td>
									<?php if ( $tenant->domain ) : ?>
										<a href="https://<?php echo esc_attr( $tenant->domain ); ?>" target="_blank">
											<?php echo esc_html( $tenant->domain ); ?>
										</a>
									<?php else : ?>
										<em><?php esc_html_e( 'Not configured', 'bkx-multi-tenant' ); ?></em>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $plan ) : ?>
										<span class="bkx-plan-badge"><?php echo esc_html( $plan->name ); ?></span>
									<?php else : ?>
										<em><?php esc_html_e( 'None', 'bkx-multi-tenant' ); ?></em>
									<?php endif; ?>
								</td>
								<td>
									<span class="bkx-status-badge bkx-status-<?php echo esc_attr( $tenant->status ); ?>">
										<?php echo esc_html( ucfirst( $tenant->status ) ); ?>
									</span>
								</td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $tenant->created_at ) ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=bkx-multi-tenant&action=edit&id=' . $tenant->id ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Edit', 'bkx-multi-tenant' ); ?>
									</a>
									<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=bkx-tenant-usage&tenant=' . $tenant->id ) ); ?>" class="button button-small">
										<?php esc_html_e( 'Usage', 'bkx-multi-tenant' ); ?>
									</a>
									<?php if ( 'active' === $tenant->status ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'network/admin.php?page=bkx-multi-tenant&action=suspend&id=' . $tenant->id ), 'bkx_suspend_tenant' ) ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Suspend this tenant?', 'bkx-multi-tenant' ); ?>');">
											<?php esc_html_e( 'Suspend', 'bkx-multi-tenant' ); ?>
										</a>
									<?php else : ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'network/admin.php?page=bkx-multi-tenant&action=activate&id=' . $tenant->id ), 'bkx_activate_tenant' ) ); ?>" class="button button-small">
											<?php esc_html_e( 'Activate', 'bkx-multi-tenant' ); ?>
										</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
