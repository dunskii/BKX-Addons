<?php
/**
 * Locations list template.
 *
 * @package BookingX\MultiLocation
 */

defined( 'ABSPATH' ) || exit;

$addon     = \BookingX\MultiLocation\MultiLocationAddon::get_instance();
$locations = $addon->get_service( 'locations' )->get_all();

$staff_counts    = $addon->get_service( 'staff' )->get_counts_by_location();
$service_counts  = $addon->get_service( 'services' )->get_counts_by_location();
$resource_counts = $addon->get_service( 'resources' )->get_counts_by_location();
?>

<div class="bkx-ml-locations">
	<?php if ( empty( $locations ) ) : ?>
		<div class="bkx-ml-no-locations">
			<p><?php esc_html_e( 'No locations found. Click "Add Location" to create your first location.', 'bkx-multi-location' ); ?></p>
		</div>
	<?php else : ?>
		<p class="description">
			<?php esc_html_e( 'Drag and drop to reorder locations. The order here determines the display order on the frontend.', 'bkx-multi-location' ); ?>
		</p>

		<table class="wp-list-table widefat striped" id="bkx-ml-locations-table">
			<thead>
				<tr>
					<th class="bkx-ml-col-handle"></th>
					<th class="bkx-ml-col-name"><?php esc_html_e( 'Name', 'bkx-multi-location' ); ?></th>
					<th class="bkx-ml-col-address"><?php esc_html_e( 'Address', 'bkx-multi-location' ); ?></th>
					<th class="bkx-ml-col-contact"><?php esc_html_e( 'Contact', 'bkx-multi-location' ); ?></th>
					<th class="bkx-ml-col-stats"><?php esc_html_e( 'Staff', 'bkx-multi-location' ); ?></th>
					<th class="bkx-ml-col-stats"><?php esc_html_e( 'Services', 'bkx-multi-location' ); ?></th>
					<th class="bkx-ml-col-status"><?php esc_html_e( 'Status', 'bkx-multi-location' ); ?></th>
					<th class="bkx-ml-col-actions"><?php esc_html_e( 'Actions', 'bkx-multi-location' ); ?></th>
				</tr>
			</thead>
			<tbody id="bkx-ml-locations-sortable">
				<?php foreach ( $locations as $location ) : ?>
					<tr data-location-id="<?php echo esc_attr( $location->id ); ?>">
						<td class="bkx-ml-col-handle">
							<span class="dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'bkx-multi-location' ); ?>"></span>
						</td>
						<td class="bkx-ml-col-name">
							<strong>
								<a href="#" class="bkx-ml-edit-location" data-id="<?php echo esc_attr( $location->id ); ?>">
									<?php echo esc_html( $location->name ); ?>
								</a>
							</strong>
							<div class="row-actions">
								<span class="edit">
									<a href="#" class="bkx-ml-edit-location" data-id="<?php echo esc_attr( $location->id ); ?>">
										<?php esc_html_e( 'Edit', 'bkx-multi-location' ); ?>
									</a>
								</span>
								|
								<span class="delete">
									<a href="#" class="bkx-ml-delete-location" data-id="<?php echo esc_attr( $location->id ); ?>">
										<?php esc_html_e( 'Delete', 'bkx-multi-location' ); ?>
									</a>
								</span>
							</div>
						</td>
						<td class="bkx-ml-col-address">
							<?php
							$address_parts = array_filter(
								array(
									$location->address_line_1,
									$location->city,
									$location->state,
								)
							);
							if ( $address_parts ) {
								echo esc_html( implode( ', ', $address_parts ) );
							} else {
								echo '<span class="bkx-ml-muted">—</span>';
							}
							?>
						</td>
						<td class="bkx-ml-col-contact">
							<?php if ( $location->phone ) : ?>
								<div><?php echo esc_html( $location->phone ); ?></div>
							<?php endif; ?>
							<?php if ( $location->email ) : ?>
								<div><a href="mailto:<?php echo esc_attr( $location->email ); ?>"><?php echo esc_html( $location->email ); ?></a></div>
							<?php endif; ?>
							<?php if ( ! $location->phone && ! $location->email ) : ?>
								<span class="bkx-ml-muted">—</span>
							<?php endif; ?>
						</td>
						<td class="bkx-ml-col-stats">
							<?php echo esc_html( $staff_counts[ $location->id ] ?? 0 ); ?>
						</td>
						<td class="bkx-ml-col-stats">
							<?php echo esc_html( $service_counts[ $location->id ] ?? 0 ); ?>
						</td>
						<td class="bkx-ml-col-status">
							<?php if ( 'active' === $location->status ) : ?>
								<span class="bkx-ml-status bkx-ml-status-active"><?php esc_html_e( 'Active', 'bkx-multi-location' ); ?></span>
							<?php else : ?>
								<span class="bkx-ml-status bkx-ml-status-inactive"><?php esc_html_e( 'Inactive', 'bkx-multi-location' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="bkx-ml-col-actions">
							<button type="button" class="button button-small bkx-ml-edit-location" data-id="<?php echo esc_attr( $location->id ); ?>">
								<span class="dashicons dashicons-edit"></span>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<div class="bkx-ml-stats-row">
			<div class="bkx-ml-stat-card">
				<span class="bkx-ml-stat-value"><?php echo esc_html( count( $locations ) ); ?></span>
				<span class="bkx-ml-stat-label"><?php esc_html_e( 'Total Locations', 'bkx-multi-location' ); ?></span>
			</div>
			<div class="bkx-ml-stat-card">
				<span class="bkx-ml-stat-value">
					<?php
					$active = array_filter(
						$locations,
						function ( $l ) {
							return 'active' === $l->status;
						}
					);
					echo esc_html( count( $active ) );
					?>
				</span>
				<span class="bkx-ml-stat-label"><?php esc_html_e( 'Active Locations', 'bkx-multi-location' ); ?></span>
			</div>
			<div class="bkx-ml-stat-card">
				<span class="bkx-ml-stat-value"><?php echo esc_html( array_sum( $staff_counts ) ); ?></span>
				<span class="bkx-ml-stat-label"><?php esc_html_e( 'Staff Assignments', 'bkx-multi-location' ); ?></span>
			</div>
		</div>
	<?php endif; ?>
</div>
