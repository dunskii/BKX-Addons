<?php
/**
 * MYOB Sync Tab.
 *
 * @package BookingX\MYOB
 */

defined( 'ABSPATH' ) || exit;

$addon     = \BookingX\MYOB\MYOBAddon::get_instance();
$connected = $addon->is_connected();

// Get recent bookings that need syncing.
$unsynced_bookings = get_posts(
	array(
		'post_type'      => 'bkx_booking',
		'post_status'    => array( 'bkx-ack', 'bkx-completed' ),
		'posts_per_page' => 20,
		'meta_query'     => array(
			array(
				'key'     => '_myob_synced',
				'compare' => 'NOT EXISTS',
			),
		),
	)
);

// Get recently synced bookings.
$synced_bookings = get_posts(
	array(
		'post_type'      => 'bkx_booking',
		'post_status'    => 'any',
		'posts_per_page' => 10,
		'meta_query'     => array(
			array(
				'key'     => '_myob_synced',
				'compare' => 'EXISTS',
			),
		),
		'orderby'        => 'meta_value',
		'meta_key'       => '_myob_synced',
		'order'          => 'DESC',
	)
);
?>

<div class="bkx-myob-sync">
	<?php if ( ! $connected ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Please connect to MYOB before syncing bookings.', 'bkx-myob' ); ?></p>
		</div>
	<?php else : ?>

		<!-- Sync Stats -->
		<div class="bkx-sync-stats">
			<div class="bkx-stat-card">
				<span class="bkx-stat-value"><?php echo count( $unsynced_bookings ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Pending Sync', 'bkx-myob' ); ?></span>
			</div>
			<div class="bkx-stat-card">
				<?php
				global $wpdb;
				$total_synced = $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_myob_synced'"
				);
				?>
				<span class="bkx-stat-value"><?php echo esc_html( $total_synced ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Total Synced', 'bkx-myob' ); ?></span>
			</div>
		</div>

		<!-- Unsynced Bookings -->
		<div class="bkx-sync-section">
			<h3><?php esc_html_e( 'Bookings Pending Sync', 'bkx-myob' ); ?></h3>

			<?php if ( empty( $unsynced_bookings ) ) : ?>
				<p class="description"><?php esc_html_e( 'All bookings are synced!', 'bkx-myob' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Booking', 'bkx-myob' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'bkx-myob' ); ?></th>
							<th><?php esc_html_e( 'Date', 'bkx-myob' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'bkx-myob' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bkx-myob' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bkx-myob' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $unsynced_bookings as $booking ) : ?>
							<tr data-booking-id="<?php echo esc_attr( $booking->ID ); ?>">
								<td>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $booking->ID . '&action=edit' ) ); ?>">
										#<?php echo esc_html( $booking->ID ); ?>
									</a>
								</td>
								<td><?php echo esc_html( get_post_meta( $booking->ID, 'customer_name', true ) ); ?></td>
								<td><?php echo esc_html( get_post_meta( $booking->ID, 'booking_date', true ) ); ?></td>
								<td><?php echo esc_html( get_post_meta( $booking->ID, 'total_amount', true ) ); ?></td>
								<td>
									<?php
									$status_labels = array(
										'bkx-pending'   => __( 'Pending', 'bkx-myob' ),
										'bkx-ack'       => __( 'Confirmed', 'bkx-myob' ),
										'bkx-completed' => __( 'Completed', 'bkx-myob' ),
									);
									echo esc_html( $status_labels[ $booking->post_status ] ?? $booking->post_status );
									?>
								</td>
								<td>
									<button type="button" class="button button-small bkx-sync-booking"
											data-booking-id="<?php echo esc_attr( $booking->ID ); ?>">
										<span class="dashicons dashicons-update"></span>
										<?php esc_html_e( 'Sync Now', 'bkx-myob' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p class="submit">
					<button type="button" class="button button-primary" id="bkx-sync-all">
						<?php esc_html_e( 'Sync All Pending', 'bkx-myob' ); ?>
					</button>
				</p>
			<?php endif; ?>
		</div>

		<!-- Recently Synced -->
		<div class="bkx-sync-section">
			<h3><?php esc_html_e( 'Recently Synced', 'bkx-myob' ); ?></h3>

			<?php if ( empty( $synced_bookings ) ) : ?>
				<p class="description"><?php esc_html_e( 'No bookings synced yet.', 'bkx-myob' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Booking', 'bkx-myob' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'bkx-myob' ); ?></th>
							<th><?php esc_html_e( 'MYOB Invoice', 'bkx-myob' ); ?></th>
							<th><?php esc_html_e( 'Synced At', 'bkx-myob' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $synced_bookings as $booking ) : ?>
							<tr>
								<td>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $booking->ID . '&action=edit' ) ); ?>">
										#<?php echo esc_html( $booking->ID ); ?>
									</a>
								</td>
								<td><?php echo esc_html( get_post_meta( $booking->ID, 'customer_name', true ) ); ?></td>
								<td>
									<?php
									$invoice_number = get_post_meta( $booking->ID, '_myob_invoice_number', true );
									echo $invoice_number ? esc_html( $invoice_number ) : '-';
									?>
								</td>
								<td>
									<?php
									$synced_at = get_post_meta( $booking->ID, '_myob_synced', true );
									echo $synced_at ? esc_html( human_time_diff( strtotime( $synced_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'bkx-myob' ) ) : '-';
									?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

	<?php endif; ?>
</div>
