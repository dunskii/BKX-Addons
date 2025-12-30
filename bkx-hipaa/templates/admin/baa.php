<?php
/**
 * HIPAA BAA Management.
 *
 * @package BookingX\HIPAA
 */

defined( 'ABSPATH' ) || exit;

$addon       = \BookingX\HIPAA\HIPAAAddon::get_instance();
$baa_manager = $addon->get_service( 'baa_manager' );

$baas          = $baa_manager->get_all_baas();
$status_labels = $baa_manager::get_status_labels();
?>

<div class="bkx-baa-section">
	<h2><?php esc_html_e( 'Business Associate Agreement Management', 'bkx-hipaa' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Track Business Associate Agreements with vendors who handle PHI.', 'bkx-hipaa' ); ?>
	</p>

	<!-- Add New BAA -->
	<div class="bkx-baa-form-card">
		<h3><?php esc_html_e( 'Add New BAA', 'bkx-hipaa' ); ?></h3>
		<form id="bkx-baa-form">
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Vendor Name', 'bkx-hipaa' ); ?> *</th>
					<td>
						<input type="text" name="vendor_name" class="regular-text" required>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Vendor Email', 'bkx-hipaa' ); ?> *</th>
					<td>
						<input type="email" name="vendor_email" class="regular-text" required>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Contact Name', 'bkx-hipaa' ); ?></th>
					<td>
						<input type="text" name="vendor_contact" class="regular-text">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Signed Date', 'bkx-hipaa' ); ?></th>
					<td>
						<input type="date" name="signed_date">
						<p class="description"><?php esc_html_e( 'Leave blank if not yet signed.', 'bkx-hipaa' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Expiry Date', 'bkx-hipaa' ); ?></th>
					<td>
						<input type="date" name="expiry_date">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Notes', 'bkx-hipaa' ); ?></th>
					<td>
						<textarea name="notes" rows="3" class="large-text"></textarea>
					</td>
				</tr>
			</table>
			<p>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Add BAA', 'bkx-hipaa' ); ?>
				</button>
				<span class="spinner"></span>
			</p>
		</form>
	</div>

	<!-- BAA List -->
	<div class="bkx-baa-list">
		<h3><?php esc_html_e( 'Current BAAs', 'bkx-hipaa' ); ?></h3>

		<?php if ( ! empty( $baas ) ) : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Vendor', 'bkx-hipaa' ); ?></th>
						<th><?php esc_html_e( 'Contact', 'bkx-hipaa' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-hipaa' ); ?></th>
						<th><?php esc_html_e( 'Signed', 'bkx-hipaa' ); ?></th>
						<th><?php esc_html_e( 'Expires', 'bkx-hipaa' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-hipaa' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $baas as $baa ) : ?>
						<?php
						$is_expiring = $baa->expiry_date && strtotime( $baa->expiry_date ) < strtotime( '+30 days' );
						$is_expired  = $baa->expiry_date && strtotime( $baa->expiry_date ) < time();
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $baa->vendor_name ); ?></strong><br>
								<span class="bkx-vendor-email"><?php echo esc_html( $baa->vendor_email ); ?></span>
							</td>
							<td><?php echo esc_html( $baa->vendor_contact ?: '—' ); ?></td>
							<td>
								<span class="bkx-baa-status bkx-status-<?php echo esc_attr( $baa->baa_status ); ?>">
									<?php echo esc_html( $status_labels[ $baa->baa_status ] ); ?>
								</span>
							</td>
							<td>
								<?php echo $baa->signed_date ? esc_html( gmdate( 'M j, Y', strtotime( $baa->signed_date ) ) ) : '—'; ?>
							</td>
							<td class="<?php echo $is_expiring ? 'bkx-expiring' : ''; ?> <?php echo $is_expired ? 'bkx-expired' : ''; ?>">
								<?php if ( $baa->expiry_date ) : ?>
									<?php echo esc_html( gmdate( 'M j, Y', strtotime( $baa->expiry_date ) ) ); ?>
									<?php if ( $is_expiring && ! $is_expired ) : ?>
										<span class="bkx-expiring-badge"><?php esc_html_e( 'Expiring Soon', 'bkx-hipaa' ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td>
								<button type="button" class="button button-small bkx-edit-baa" data-baa-id="<?php echo esc_attr( $baa->id ); ?>">
									<?php esc_html_e( 'Edit', 'bkx-hipaa' ); ?>
								</button>
								<?php if ( 'active' === $baa->baa_status ) : ?>
									<button type="button" class="button button-small bkx-revoke-baa" data-baa-id="<?php echo esc_attr( $baa->id ); ?>">
										<?php esc_html_e( 'Revoke', 'bkx-hipaa' ); ?>
									</button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<div class="bkx-no-baas">
				<span class="dashicons dashicons-media-document"></span>
				<p><?php esc_html_e( 'No Business Associate Agreements on file.', 'bkx-hipaa' ); ?></p>
				<p class="description">
					<?php esc_html_e( 'HIPAA requires a BAA with any vendor who creates, receives, maintains, or transmits PHI on your behalf.', 'bkx-hipaa' ); ?>
				</p>
			</div>
		<?php endif; ?>
	</div>
</div>
