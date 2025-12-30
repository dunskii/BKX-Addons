<?php
/**
 * HIPAA Access Control.
 *
 * @package BookingX\HIPAA
 */

defined( 'ABSPATH' ) || exit;

$addon          = \BookingX\HIPAA\HIPAAAddon::get_instance();
$access_control = $addon->get_service( 'access_control' );

$access_records = $access_control->get_all_access();
$access_levels  = $access_control->get_access_levels();
$phi_fields     = \BookingX\HIPAA\Services\PHIHandler::get_default_phi_fields();
$users          = get_users( array( 'role__in' => array( 'administrator', 'editor', 'author' ) ) );
?>

<div class="bkx-access-section">
	<h2><?php esc_html_e( 'Access Control', 'bkx-hipaa' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Manage user access to Protected Health Information (PHI).', 'bkx-hipaa' ); ?>
	</p>

	<!-- Add New Access -->
	<div class="bkx-access-form-card">
		<h3><?php esc_html_e( 'Grant Access', 'bkx-hipaa' ); ?></h3>
		<form id="bkx-access-form">
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'User', 'bkx-hipaa' ); ?></th>
					<td>
						<select name="user_id" id="bkx-access-user" required>
							<option value=""><?php esc_html_e( 'Select User', 'bkx-hipaa' ); ?></option>
							<?php foreach ( $users as $user ) : ?>
								<option value="<?php echo esc_attr( $user->ID ); ?>">
									<?php echo esc_html( $user->display_name ); ?> (<?php echo esc_html( $user->user_email ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Access Level', 'bkx-hipaa' ); ?></th>
					<td>
						<select name="access_level" id="bkx-access-level" required>
							<?php foreach ( $access_levels as $level => $label ) : ?>
								<option value="<?php echo esc_attr( $level ); ?>">
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'PHI Fields Access', 'bkx-hipaa' ); ?></th>
					<td>
						<?php foreach ( $phi_fields as $field => $label ) : ?>
							<label style="display: block; margin-bottom: 5px;">
								<input type="checkbox" name="phi_fields[]" value="<?php echo esc_attr( $field ); ?>" checked>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description">
							<?php esc_html_e( 'Leave all checked for full PHI access, or select specific fields.', 'bkx-hipaa' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<p>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Grant Access', 'bkx-hipaa' ); ?>
				</button>
				<span class="spinner"></span>
			</p>
		</form>
	</div>

	<!-- Current Access -->
	<div class="bkx-access-list">
		<h3><?php esc_html_e( 'Current Access Permissions', 'bkx-hipaa' ); ?></h3>

		<?php if ( ! empty( $access_records ) ) : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'User', 'bkx-hipaa' ); ?></th>
						<th><?php esc_html_e( 'Access Level', 'bkx-hipaa' ); ?></th>
						<th><?php esc_html_e( 'PHI Fields', 'bkx-hipaa' ); ?></th>
						<th><?php esc_html_e( 'Granted', 'bkx-hipaa' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-hipaa' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $access_records as $record ) : ?>
						<?php $record_phi = $record->phi_fields ? json_decode( $record->phi_fields, true ) : array(); ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $record->display_name ); ?></strong><br>
								<span class="bkx-user-login"><?php echo esc_html( $record->user_login ); ?></span>
							</td>
							<td>
								<span class="bkx-access-level bkx-level-<?php echo esc_attr( $record->access_level ); ?>">
									<?php echo esc_html( isset( $access_levels[ $record->access_level ] ) ? $access_levels[ $record->access_level ] : $record->access_level ); ?>
								</span>
							</td>
							<td>
								<?php if ( empty( $record_phi ) ) : ?>
									<span class="bkx-all-fields"><?php esc_html_e( 'All Fields', 'bkx-hipaa' ); ?></span>
								<?php else : ?>
									<?php
									$field_labels = array();
									foreach ( $record_phi as $field ) {
										$field_labels[] = isset( $phi_fields[ $field ] ) ? $phi_fields[ $field ] : $field;
									}
									echo esc_html( implode( ', ', $field_labels ) );
									?>
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( human_time_diff( strtotime( $record->granted_at ), current_time( 'timestamp' ) ) . ' ago' ); ?>
							</td>
							<td>
								<button type="button" class="button button-small bkx-revoke-access" data-user-id="<?php echo esc_attr( $record->user_id ); ?>">
									<?php esc_html_e( 'Revoke', 'bkx-hipaa' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<div class="bkx-no-access">
				<p><?php esc_html_e( 'No access permissions configured. Administrators have full access by default.', 'bkx-hipaa' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</div>
