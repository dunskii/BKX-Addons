<?php
/**
 * Remote sites list template.
 *
 * @package BookingX\BkxIntegration
 */

defined( 'ABSPATH' ) || exit;

$addon  = \BookingX\BkxIntegration\BkxIntegrationAddon::get_instance();
$sites  = $addon->get_service( 'sites' )->get_all();
$queue_stats = $addon->get_service( 'queue' )->get_stats();
?>

<div class="bkx-bkx-sites">
	<!-- Stats Row -->
	<div class="bkx-bkx-stats-row">
		<div class="bkx-bkx-stat-card">
			<span class="bkx-bkx-stat-value"><?php echo esc_html( count( $sites ) ); ?></span>
			<span class="bkx-bkx-stat-label"><?php esc_html_e( 'Connected Sites', 'bkx-bkx-integration' ); ?></span>
		</div>
		<div class="bkx-bkx-stat-card">
			<span class="bkx-bkx-stat-value"><?php echo esc_html( $queue_stats['pending'] ); ?></span>
			<span class="bkx-bkx-stat-label"><?php esc_html_e( 'Pending Sync', 'bkx-bkx-integration' ); ?></span>
		</div>
		<div class="bkx-bkx-stat-card">
			<span class="bkx-bkx-stat-value"><?php echo esc_html( $queue_stats['failed'] ); ?></span>
			<span class="bkx-bkx-stat-label"><?php esc_html_e( 'Failed', 'bkx-bkx-integration' ); ?></span>
		</div>
	</div>

	<?php if ( empty( $sites ) ) : ?>
		<div class="bkx-bkx-no-sites">
			<p><?php esc_html_e( 'No remote sites configured. Click "Add Remote Site" to connect to another BookingX installation.', 'bkx-bkx-integration' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Site', 'bkx-bkx-integration' ); ?></th>
					<th><?php esc_html_e( 'URL', 'bkx-bkx-integration' ); ?></th>
					<th><?php esc_html_e( 'Direction', 'bkx-bkx-integration' ); ?></th>
					<th><?php esc_html_e( 'Sync', 'bkx-bkx-integration' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bkx-bkx-integration' ); ?></th>
					<th><?php esc_html_e( 'Last Sync', 'bkx-bkx-integration' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'bkx-bkx-integration' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sites as $site ) : ?>
					<tr data-site-id="<?php echo esc_attr( $site->id ); ?>">
						<td>
							<strong><?php echo esc_html( $site->name ); ?></strong>
							<div class="row-actions">
								<span class="edit">
									<a href="#" class="bkx-bkx-edit-site" data-id="<?php echo esc_attr( $site->id ); ?>">
										<?php esc_html_e( 'Edit', 'bkx-bkx-integration' ); ?>
									</a>
								</span>
								|
								<span class="delete">
									<a href="#" class="bkx-bkx-delete-site" data-id="<?php echo esc_attr( $site->id ); ?>">
										<?php esc_html_e( 'Delete', 'bkx-bkx-integration' ); ?>
									</a>
								</span>
							</div>
						</td>
						<td>
							<a href="<?php echo esc_url( $site->url ); ?>" target="_blank">
								<?php echo esc_html( $site->url ); ?>
							</a>
						</td>
						<td>
							<?php
							$direction_labels = array(
								'both' => __( 'Both', 'bkx-bkx-integration' ),
								'push' => __( 'Push', 'bkx-bkx-integration' ),
								'pull' => __( 'Pull', 'bkx-bkx-integration' ),
							);
							echo esc_html( $direction_labels[ $site->direction ] ?? $site->direction );
							?>
						</td>
						<td>
							<?php
							$sync_types = array();
							if ( $site->sync_bookings ) {
								$sync_types[] = __( 'Bookings', 'bkx-bkx-integration' );
							}
							if ( $site->sync_availability ) {
								$sync_types[] = __( 'Availability', 'bkx-bkx-integration' );
							}
							if ( $site->sync_customers ) {
								$sync_types[] = __( 'Customers', 'bkx-bkx-integration' );
							}
							echo esc_html( implode( ', ', $sync_types ) ?: '—' );
							?>
						</td>
						<td>
							<?php if ( 'active' === $site->status ) : ?>
								<span class="bkx-bkx-status bkx-bkx-status-active"><?php esc_html_e( 'Active', 'bkx-bkx-integration' ); ?></span>
							<?php elseif ( 'error' === $site->status ) : ?>
								<span class="bkx-bkx-status bkx-bkx-status-error" title="<?php echo esc_attr( $site->last_error ); ?>">
									<?php esc_html_e( 'Error', 'bkx-bkx-integration' ); ?>
								</span>
							<?php else : ?>
								<span class="bkx-bkx-status bkx-bkx-status-paused"><?php esc_html_e( 'Paused', 'bkx-bkx-integration' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							if ( $site->last_sync ) {
								echo esc_html( human_time_diff( strtotime( $site->last_sync ) ) . ' ' . __( 'ago', 'bkx-bkx-integration' ) );
							} else {
								echo '<span class="bkx-bkx-muted">—</span>';
							}
							?>
						</td>
						<td>
							<button type="button" class="button button-small bkx-bkx-sync-now" data-id="<?php echo esc_attr( $site->id ); ?>">
								<?php esc_html_e( 'Sync Now', 'bkx-bkx-integration' ); ?>
							</button>
							<button type="button" class="button button-small bkx-bkx-test-site" data-id="<?php echo esc_attr( $site->id ); ?>">
								<?php esc_html_e( 'Test', 'bkx-bkx-integration' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
