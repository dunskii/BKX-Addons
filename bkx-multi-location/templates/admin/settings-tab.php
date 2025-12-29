<?php
/**
 * Settings tab for BookingX settings page.
 *
 * @package BookingX\MultiLocation
 */

defined( 'ABSPATH' ) || exit;

$addon     = \BookingX\MultiLocation\MultiLocationAddon::get_instance();
$locations = $addon->get_service( 'locations' )->get_all();
$active    = array_filter(
	$locations,
	function ( $l ) {
		return 'active' === $l->status;
	}
);
?>

<div class="bkx-ml-settings-tab">
	<h3><?php esc_html_e( 'Multi-Location Management', 'bkx-multi-location' ); ?></h3>

	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Total Locations', 'bkx-multi-location' ); ?></th>
			<td>
				<strong><?php echo esc_html( count( $locations ) ); ?></strong>
				(<?php echo esc_html( count( $active ) ); ?> <?php esc_html_e( 'active', 'bkx-multi-location' ); ?>)
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Locations', 'bkx-multi-location' ); ?></th>
			<td>
				<?php if ( ! empty( $locations ) ) : ?>
					<ul style="margin: 0;">
						<?php foreach ( array_slice( $locations, 0, 5 ) as $location ) : ?>
							<li>
								<?php echo esc_html( $location->name ); ?>
								<?php if ( 'inactive' === $location->status ) : ?>
									<span class="bkx-ml-muted">(<?php esc_html_e( 'inactive', 'bkx-multi-location' ); ?>)</span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
						<?php if ( count( $locations ) > 5 ) : ?>
							<li><em><?php printf( esc_html__( '...and %d more', 'bkx-multi-location' ), count( $locations ) - 5 ); ?></em></li>
						<?php endif; ?>
					</ul>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'No locations configured yet.', 'bkx-multi-location' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
	</table>

	<p>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-locations' ) ); ?>" class="button">
			<?php esc_html_e( 'Manage Locations', 'bkx-multi-location' ); ?>
		</a>
	</p>
</div>
