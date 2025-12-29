<?php
/**
 * Conflicts template.
 *
 * @package BookingX\BkxIntegration
 */

defined( 'ABSPATH' ) || exit;

$addon     = \BookingX\BkxIntegration\BkxIntegrationAddon::get_instance();
$conflicts = $addon->get_service( 'conflicts' )->get_pending();
?>

<div class="bkx-bkx-conflicts">
	<div class="bkx-bkx-card">
		<h2><?php esc_html_e( 'Sync Conflicts', 'bkx-bkx-integration' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'When data on both sites changes simultaneously, conflicts may occur. Review and resolve them below.', 'bkx-bkx-integration' ); ?>
		</p>

		<?php if ( empty( $conflicts ) ) : ?>
			<p class="bkx-bkx-no-items"><?php esc_html_e( 'No conflicts to resolve.', 'bkx-bkx-integration' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'bkx-bkx-integration' ); ?></th>
						<th><?php esc_html_e( 'Site', 'bkx-bkx-integration' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bkx-bkx-integration' ); ?></th>
						<th><?php esc_html_e( 'Conflict', 'bkx-bkx-integration' ); ?></th>
						<th><?php esc_html_e( 'Local Data', 'bkx-bkx-integration' ); ?></th>
						<th><?php esc_html_e( 'Remote Data', 'bkx-bkx-integration' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-bkx-integration' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $conflicts as $conflict ) : ?>
						<tr data-conflict-id="<?php echo esc_attr( $conflict->id ); ?>">
							<td>
								<?php
								echo esc_html(
									wp_date(
										get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
										strtotime( $conflict->created_at )
									)
								);
								?>
							</td>
							<td><?php echo esc_html( $conflict->site_name ?? __( 'Unknown', 'bkx-bkx-integration' ) ); ?></td>
							<td>
								<span class="bkx-bkx-badge bkx-bkx-badge-<?php echo esc_attr( $conflict->object_type ); ?>">
									<?php echo esc_html( ucfirst( $conflict->object_type ) ); ?>
								</span>
							</td>
							<td>
								<?php
								$conflict_labels = array(
									'double_booking'    => __( 'Double Booking', 'bkx-bkx-integration' ),
									'concurrent_update' => __( 'Concurrent Update', 'bkx-bkx-integration' ),
								);
								echo esc_html( $conflict_labels[ $conflict->conflict_type ] ?? $conflict->conflict_type );
								?>
							</td>
							<td>
								<button type="button" class="button button-small bkx-bkx-view-data" data-data="<?php echo esc_attr( wp_json_encode( $conflict->local_data ) ); ?>">
									<?php esc_html_e( 'View', 'bkx-bkx-integration' ); ?>
								</button>
							</td>
							<td>
								<button type="button" class="button button-small bkx-bkx-view-data" data-data="<?php echo esc_attr( wp_json_encode( $conflict->remote_data ) ); ?>">
									<?php esc_html_e( 'View', 'bkx-bkx-integration' ); ?>
								</button>
							</td>
							<td class="bkx-bkx-conflict-actions">
								<button type="button" class="button button-small bkx-bkx-resolve" data-id="<?php echo esc_attr( $conflict->id ); ?>" data-resolution="local" title="<?php esc_attr_e( 'Keep local version', 'bkx-bkx-integration' ); ?>">
									<?php esc_html_e( 'Local', 'bkx-bkx-integration' ); ?>
								</button>
								<button type="button" class="button button-small bkx-bkx-resolve" data-id="<?php echo esc_attr( $conflict->id ); ?>" data-resolution="remote" title="<?php esc_attr_e( 'Use remote version', 'bkx-bkx-integration' ); ?>">
									<?php esc_html_e( 'Remote', 'bkx-bkx-integration' ); ?>
								</button>
								<button type="button" class="button button-small bkx-bkx-resolve" data-id="<?php echo esc_attr( $conflict->id ); ?>" data-resolution="skip" title="<?php esc_attr_e( 'Skip this conflict', 'bkx-bkx-integration' ); ?>">
									<?php esc_html_e( 'Skip', 'bkx-bkx-integration' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<!-- Data View Modal -->
<div id="bkx-bkx-data-modal" class="bkx-bkx-modal" style="display: none;">
	<div class="bkx-bkx-modal-content" style="max-width: 600px;">
		<span class="bkx-bkx-modal-close">&times;</span>
		<h2><?php esc_html_e( 'Data Details', 'bkx-bkx-integration' ); ?></h2>
		<pre id="bkx-bkx-data-content" style="background: #f6f7f7; padding: 15px; overflow: auto; max-height: 400px;"></pre>
	</div>
</div>
