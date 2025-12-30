<?php
/**
 * Plans Management Template.
 *
 * @package BookingX\MultiTenant
 */

defined( 'ABSPATH' ) || exit;

$plan_manager = \BookingX\MultiTenant\MultiTenantAddon::get_instance()->get_service( 'plan_manager' );

// Handle actions.
if ( isset( $_POST['bkx_save_plan'] ) && check_admin_referer( 'bkx_save_plan' ) ) {
	$plan_data = array(
		'name'          => sanitize_text_field( $_POST['plan_name'] ),
		'slug'          => sanitize_title( $_POST['plan_slug'] ),
		'description'   => sanitize_textarea_field( $_POST['plan_description'] ),
		'price'         => floatval( $_POST['plan_price'] ),
		'billing_cycle' => sanitize_text_field( $_POST['billing_cycle'] ),
		'is_active'     => isset( $_POST['is_active'] ),
		'sort_order'    => absint( $_POST['sort_order'] ),
		'limits'        => array(
			'bookings'     => absint( $_POST['limit_bookings'] ),
			'api_calls'    => absint( $_POST['limit_api_calls'] ),
			'storage_mb'   => absint( $_POST['limit_storage'] ),
			'users'        => absint( $_POST['limit_users'] ),
			'services'     => absint( $_POST['limit_services'] ),
		),
		'features'      => isset( $_POST['features'] ) ? array_map( 'sanitize_text_field', $_POST['features'] ) : array(),
	);

	if ( ! empty( $_POST['plan_id'] ) ) {
		$result = $plan_manager->update_plan( absint( $_POST['plan_id'] ), $plan_data );
		$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Plan updated successfully.', 'bkx-multi-tenant' );
	} else {
		$result = $plan_manager->create_plan( $plan_data );
		$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Plan created successfully.', 'bkx-multi-tenant' );
	}
}

if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && check_admin_referer( 'bkx_delete_plan' ) ) {
	$result = $plan_manager->delete_plan( absint( $_GET['id'] ) );
	$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Plan deleted successfully.', 'bkx-multi-tenant' );
}

$plans = $plan_manager->get_plans();
$edit_plan = null;
if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['id'] ) ) {
	$edit_plan = $plan_manager->get_plan( absint( $_GET['id'] ) );
}

$available_features = array(
	'api_access'       => __( 'API Access', 'bkx-multi-tenant' ),
	'custom_branding'  => __( 'Custom Branding', 'bkx-multi-tenant' ),
	'white_label'      => __( 'White Label', 'bkx-multi-tenant' ),
	'priority_support' => __( 'Priority Support', 'bkx-multi-tenant' ),
	'analytics'        => __( 'Analytics Dashboard', 'bkx-multi-tenant' ),
	'webhooks'         => __( 'Webhooks', 'bkx-multi-tenant' ),
	'integrations'     => __( 'Third-party Integrations', 'bkx-multi-tenant' ),
	'custom_fields'    => __( 'Custom Fields', 'bkx-multi-tenant' ),
	'multi_location'   => __( 'Multi-Location', 'bkx-multi-tenant' ),
	'team_management'  => __( 'Team Management', 'bkx-multi-tenant' ),
);
?>
<div class="wrap bkx-plans-page">
	<h1><?php esc_html_e( 'Subscription Plans', 'bkx-multi-tenant' ); ?></h1>

	<?php if ( ! empty( $message ) ) : ?>
		<div class="notice <?php echo is_wp_error( $result ) ? 'notice-error' : 'notice-success'; ?>">
			<p><?php echo esc_html( $message ); ?></p>
		</div>
	<?php endif; ?>

	<div class="bkx-plans-layout">
		<!-- Plan Form -->
		<div class="bkx-plan-form-wrap">
			<h2><?php echo $edit_plan ? esc_html__( 'Edit Plan', 'bkx-multi-tenant' ) : esc_html__( 'Create Plan', 'bkx-multi-tenant' ); ?></h2>
			<form method="post" class="bkx-form">
				<?php wp_nonce_field( 'bkx_save_plan' ); ?>
				<?php if ( $edit_plan ) : ?>
					<input type="hidden" name="plan_id" value="<?php echo esc_attr( $edit_plan->id ); ?>">
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th><label for="plan_name"><?php esc_html_e( 'Plan Name', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<input type="text" name="plan_name" id="plan_name" class="regular-text" required
								value="<?php echo $edit_plan ? esc_attr( $edit_plan->name ) : ''; ?>">
						</td>
					</tr>
					<tr>
						<th><label for="plan_slug"><?php esc_html_e( 'Slug', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<input type="text" name="plan_slug" id="plan_slug" class="regular-text"
								value="<?php echo $edit_plan ? esc_attr( $edit_plan->slug ) : ''; ?>">
							<p class="description"><?php esc_html_e( 'Leave empty to auto-generate from name.', 'bkx-multi-tenant' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="plan_description"><?php esc_html_e( 'Description', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<textarea name="plan_description" id="plan_description" class="large-text" rows="3"><?php echo $edit_plan ? esc_textarea( $edit_plan->description ) : ''; ?></textarea>
						</td>
					</tr>
					<tr>
						<th><label for="plan_price"><?php esc_html_e( 'Price', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<input type="number" name="plan_price" id="plan_price" step="0.01" min="0" class="small-text"
								value="<?php echo $edit_plan ? esc_attr( $edit_plan->price ) : '0'; ?>">
							<select name="billing_cycle">
								<option value="monthly" <?php selected( $edit_plan ? $edit_plan->billing_cycle : '', 'monthly' ); ?>><?php esc_html_e( 'Monthly', 'bkx-multi-tenant' ); ?></option>
								<option value="yearly" <?php selected( $edit_plan ? $edit_plan->billing_cycle : '', 'yearly' ); ?>><?php esc_html_e( 'Yearly', 'bkx-multi-tenant' ); ?></option>
								<option value="lifetime" <?php selected( $edit_plan ? $edit_plan->billing_cycle : '', 'lifetime' ); ?>><?php esc_html_e( 'Lifetime', 'bkx-multi-tenant' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="sort_order"><?php esc_html_e( 'Sort Order', 'bkx-multi-tenant' ); ?></label></th>
						<td>
							<input type="number" name="sort_order" id="sort_order" min="0" class="small-text"
								value="<?php echo $edit_plan ? esc_attr( $edit_plan->sort_order ) : '0'; ?>">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Active', 'bkx-multi-tenant' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="is_active" value="1" <?php checked( $edit_plan ? $edit_plan->is_active : true ); ?>>
								<?php esc_html_e( 'Plan is available for selection', 'bkx-multi-tenant' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Usage Limits', 'bkx-multi-tenant' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Set to -1 for unlimited, 0 to disable.', 'bkx-multi-tenant' ); ?></p>
				<?php
				$limits = $edit_plan ? json_decode( $edit_plan->limits, true ) : array();
				?>
				<table class="form-table">
					<tr>
						<th><label for="limit_bookings"><?php esc_html_e( 'Bookings/Month', 'bkx-multi-tenant' ); ?></label></th>
						<td><input type="number" name="limit_bookings" id="limit_bookings" class="small-text" value="<?php echo esc_attr( $limits['bookings'] ?? -1 ); ?>"></td>
					</tr>
					<tr>
						<th><label for="limit_api_calls"><?php esc_html_e( 'API Calls/Month', 'bkx-multi-tenant' ); ?></label></th>
						<td><input type="number" name="limit_api_calls" id="limit_api_calls" class="small-text" value="<?php echo esc_attr( $limits['api_calls'] ?? -1 ); ?>"></td>
					</tr>
					<tr>
						<th><label for="limit_storage"><?php esc_html_e( 'Storage (MB)', 'bkx-multi-tenant' ); ?></label></th>
						<td><input type="number" name="limit_storage" id="limit_storage" class="small-text" value="<?php echo esc_attr( $limits['storage_mb'] ?? -1 ); ?>"></td>
					</tr>
					<tr>
						<th><label for="limit_users"><?php esc_html_e( 'Team Members', 'bkx-multi-tenant' ); ?></label></th>
						<td><input type="number" name="limit_users" id="limit_users" class="small-text" value="<?php echo esc_attr( $limits['users'] ?? -1 ); ?>"></td>
					</tr>
					<tr>
						<th><label for="limit_services"><?php esc_html_e( 'Services', 'bkx-multi-tenant' ); ?></label></th>
						<td><input type="number" name="limit_services" id="limit_services" class="small-text" value="<?php echo esc_attr( $limits['services'] ?? -1 ); ?>"></td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Features', 'bkx-multi-tenant' ); ?></h3>
				<?php $plan_features = $edit_plan ? json_decode( $edit_plan->features, true ) : array(); ?>
				<table class="form-table">
					<tr>
						<td colspan="2">
							<fieldset>
								<?php foreach ( $available_features as $key => $label ) : ?>
									<label style="display: block; margin-bottom: 8px;">
										<input type="checkbox" name="features[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $plan_features ?: array(), true ) ); ?>>
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="bkx_save_plan" class="button button-primary">
						<?php echo $edit_plan ? esc_html__( 'Update Plan', 'bkx-multi-tenant' ) : esc_html__( 'Create Plan', 'bkx-multi-tenant' ); ?>
					</button>
					<?php if ( $edit_plan ) : ?>
						<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=bkx-tenant-plans' ) ); ?>" class="button">
							<?php esc_html_e( 'Cancel', 'bkx-multi-tenant' ); ?>
						</a>
					<?php endif; ?>
				</p>
			</form>
		</div>

		<!-- Plans List -->
		<div class="bkx-plans-list">
			<h2><?php esc_html_e( 'Existing Plans', 'bkx-multi-tenant' ); ?></h2>
			<div class="bkx-plans-grid">
				<?php foreach ( $plans as $plan ) : ?>
					<?php
					$limits = json_decode( $plan->limits, true ) ?: array();
					$features = json_decode( $plan->features, true ) ?: array();
					?>
					<div class="bkx-plan-card <?php echo ! $plan->is_active ? 'bkx-plan-inactive' : ''; ?>">
						<div class="bkx-plan-header">
							<h3><?php echo esc_html( $plan->name ); ?></h3>
							<div class="bkx-plan-price">
								<span class="bkx-price-amount">$<?php echo esc_html( number_format( $plan->price, 2 ) ); ?></span>
								<span class="bkx-price-cycle">/<?php echo esc_html( $plan->billing_cycle ); ?></span>
							</div>
						</div>
						<div class="bkx-plan-body">
							<?php if ( $plan->description ) : ?>
								<p class="bkx-plan-desc"><?php echo esc_html( $plan->description ); ?></p>
							<?php endif; ?>
							<ul class="bkx-plan-limits">
								<li>
									<span class="dashicons dashicons-calendar-alt"></span>
									<?php
									printf(
										esc_html__( '%s bookings/month', 'bkx-multi-tenant' ),
										( $limits['bookings'] ?? -1 ) < 0 ? __( 'Unlimited', 'bkx-multi-tenant' ) : number_format( $limits['bookings'] )
									);
									?>
								</li>
								<li>
									<span class="dashicons dashicons-rest-api"></span>
									<?php
									printf(
										esc_html__( '%s API calls/month', 'bkx-multi-tenant' ),
										( $limits['api_calls'] ?? -1 ) < 0 ? __( 'Unlimited', 'bkx-multi-tenant' ) : number_format( $limits['api_calls'] )
									);
									?>
								</li>
								<li>
									<span class="dashicons dashicons-groups"></span>
									<?php
									printf(
										esc_html__( '%s team members', 'bkx-multi-tenant' ),
										( $limits['users'] ?? -1 ) < 0 ? __( 'Unlimited', 'bkx-multi-tenant' ) : $limits['users']
									);
									?>
								</li>
							</ul>
							<?php if ( ! empty( $features ) ) : ?>
								<ul class="bkx-plan-features">
									<?php foreach ( $features as $feature ) : ?>
										<li>
											<span class="dashicons dashicons-yes"></span>
											<?php echo esc_html( $available_features[ $feature ] ?? $feature ); ?>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
						<div class="bkx-plan-footer">
							<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=bkx-tenant-plans&action=edit&id=' . $plan->id ) ); ?>" class="button">
								<?php esc_html_e( 'Edit', 'bkx-multi-tenant' ); ?>
							</a>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'network/admin.php?page=bkx-tenant-plans&action=delete&id=' . $plan->id ), 'bkx_delete_plan' ) ); ?>" class="button" onclick="return confirm('<?php esc_attr_e( 'Delete this plan?', 'bkx-multi-tenant' ); ?>');">
								<?php esc_html_e( 'Delete', 'bkx-multi-tenant' ); ?>
							</a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>
