<?php
/**
 * Devices Management.
 *
 * @package BookingX\MobileApp
 */

defined( 'ABSPATH' ) || exit;

$addon          = \BookingX\MobileApp\MobileAppAddon::get_instance();
$device_manager = $addon->get_service( 'device_manager' );

// Pagination.
$per_page     = 20;
$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
$offset       = ( $current_page - 1 ) * $per_page;

// Filters.
$device_type = isset( $_GET['device_type'] ) ? sanitize_text_field( wp_unslash( $_GET['device_type'] ) ) : '';

$devices = $device_manager->get_all_devices(
	array(
		'device_type' => $device_type,
		'limit'       => $per_page,
		'offset'      => $offset,
	)
);

$stats = $device_manager->get_statistics();
?>

<div class="bkx-devices">
	<!-- Filters -->
	<div class="bkx-filters">
		<form method="get" class="bkx-filter-form">
			<input type="hidden" name="page" value="bkx-mobile-app">
			<input type="hidden" name="tab" value="devices">

			<select name="device_type">
				<option value=""><?php esc_html_e( 'All Devices', 'bkx-mobile-app' ); ?></option>
				<option value="ios" <?php selected( $device_type, 'ios' ); ?>>
					<?php esc_html_e( 'iOS Only', 'bkx-mobile-app' ); ?>
				</option>
				<option value="android" <?php selected( $device_type, 'android' ); ?>>
					<?php esc_html_e( 'Android Only', 'bkx-mobile-app' ); ?>
				</option>
			</select>

			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'bkx-mobile-app' ); ?></button>
		</form>

		<div class="bkx-filter-stats">
			<span class="bkx-stat">
				<strong><?php echo esc_html( number_format( $stats['total'] ) ); ?></strong>
				<?php esc_html_e( 'Total', 'bkx-mobile-app' ); ?>
			</span>
			<span class="bkx-stat bkx-ios">
				<strong><?php echo esc_html( number_format( $stats['ios'] ) ); ?></strong>
				<?php esc_html_e( 'iOS', 'bkx-mobile-app' ); ?>
			</span>
			<span class="bkx-stat bkx-android">
				<strong><?php echo esc_html( number_format( $stats['android'] ) ); ?></strong>
				<?php esc_html_e( 'Android', 'bkx-mobile-app' ); ?>
			</span>
		</div>
	</div>

	<!-- Devices Table -->
	<?php if ( empty( $devices ) ) : ?>
		<div class="bkx-no-devices">
			<span class="dashicons dashicons-smartphone"></span>
			<h3><?php esc_html_e( 'No Devices Registered', 'bkx-mobile-app' ); ?></h3>
			<p><?php esc_html_e( 'Devices will appear here once users install and connect your mobile app.', 'bkx-mobile-app' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped bkx-devices-table">
			<thead>
				<tr>
					<th class="column-device"><?php esc_html_e( 'Device', 'bkx-mobile-app' ); ?></th>
					<th class="column-user"><?php esc_html_e( 'User', 'bkx-mobile-app' ); ?></th>
					<th class="column-version"><?php esc_html_e( 'App Version', 'bkx-mobile-app' ); ?></th>
					<th class="column-push"><?php esc_html_e( 'Push', 'bkx-mobile-app' ); ?></th>
					<th class="column-active"><?php esc_html_e( 'Last Active', 'bkx-mobile-app' ); ?></th>
					<th class="column-registered"><?php esc_html_e( 'Registered', 'bkx-mobile-app' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $devices as $device ) : ?>
					<tr>
						<td class="column-device">
							<div class="bkx-device-info">
								<span class="bkx-device-icon bkx-<?php echo esc_attr( $device->device_type ); ?>">
									<?php if ( 'ios' === $device->device_type ) : ?>
										<span class="dashicons dashicons-smartphone"></span>
									<?php else : ?>
										<span class="dashicons dashicons-smartphone"></span>
									<?php endif; ?>
								</span>
								<div class="bkx-device-details">
									<strong><?php echo esc_html( $device->device_name ?: __( 'Unknown Device', 'bkx-mobile-app' ) ); ?></strong>
									<span class="bkx-device-model">
										<?php echo esc_html( $device->device_model ?: '' ); ?>
										<?php if ( $device->os_version ) : ?>
											(<?php echo esc_html( ucfirst( $device->device_type ) . ' ' . $device->os_version ); ?>)
										<?php endif; ?>
									</span>
								</div>
							</div>
						</td>
						<td class="column-user">
							<?php if ( $device->user_id && $device->display_name ) : ?>
								<a href="<?php echo esc_url( get_edit_user_link( $device->user_id ) ); ?>">
									<?php echo esc_html( $device->display_name ); ?>
								</a>
								<br>
								<small><?php echo esc_html( $device->user_email ); ?></small>
							<?php else : ?>
								<span class="bkx-guest"><?php esc_html_e( 'Guest', 'bkx-mobile-app' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="column-version">
							<?php echo esc_html( $device->app_version ?: '-' ); ?>
						</td>
						<td class="column-push">
							<?php if ( $device->push_enabled ) : ?>
								<span class="bkx-push-enabled" title="<?php esc_attr_e( 'Push notifications enabled', 'bkx-mobile-app' ); ?>">
									<span class="dashicons dashicons-yes-alt"></span>
								</span>
							<?php else : ?>
								<span class="bkx-push-disabled" title="<?php esc_attr_e( 'Push notifications disabled', 'bkx-mobile-app' ); ?>">
									<span class="dashicons dashicons-dismiss"></span>
								</span>
							<?php endif; ?>
						</td>
						<td class="column-active">
							<?php if ( $device->last_active ) : ?>
								<?php
								$last_active = strtotime( $device->last_active );
								$now         = current_time( 'timestamp' );
								$diff        = $now - $last_active;

								if ( $diff < 3600 ) {
									echo '<span class="bkx-recently-active">' . esc_html__( 'Just now', 'bkx-mobile-app' ) . '</span>';
								} elseif ( $diff < 86400 ) {
									echo esc_html( human_time_diff( $last_active, $now ) . ' ' . __( 'ago', 'bkx-mobile-app' ) );
								} else {
									echo esc_html( wp_date( get_option( 'date_format' ), $last_active ) );
								}
								?>
							<?php else : ?>
								-
							<?php endif; ?>
						</td>
						<td class="column-registered">
							<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $device->created_at ) ) ); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		// Pagination.
		$total_items = $stats['total'];
		$total_pages = ceil( $total_items / $per_page );

		if ( $total_pages > 1 ) :
			$page_links = paginate_links(
				array(
					'base'      => add_query_arg( 'paged', '%#%' ),
					'format'    => '',
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;',
					'total'     => $total_pages,
					'current'   => $current_page,
				)
			);
			?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php echo wp_kses_post( $page_links ); ?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>
