<?php
/**
 * FreshBooks Sync Tab.
 *
 * @package BookingX\FreshBooks
 */

defined( 'ABSPATH' ) || exit;

$addon     = \BookingX\FreshBooks\FreshBooksAddon::get_instance();
$connected = $addon->is_connected();

// Get bookings pending sync.
$unsynced_bookings = get_posts(
	array(
		'post_type'      => 'bkx_booking',
		'post_status'    => array( 'bkx-ack', 'bkx-completed' ),
		'posts_per_page' => 20,
		'meta_query'     => array(
			array(
				'key'     => '_freshbooks_synced',
				'compare' => 'NOT EXISTS',
			),
		),
	)
);

// Get recently synced.
$synced_bookings = get_posts(
	array(
		'post_type'      => 'bkx_booking',
		'post_status'    => 'any',
		'posts_per_page' => 10,
		'meta_query'     => array(
			array(
				'key'     => '_freshbooks_synced',
				'compare' => 'EXISTS',
			),
		),
		'orderby'        => 'meta_value',
		'meta_key'       => '_freshbooks_synced',
		'order'          => 'DESC',
	)
);
?>

<div class="bkx-freshbooks-sync">
	<?php if ( ! $connected ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Please connect to FreshBooks before syncing bookings.', 'bkx-freshbooks' ); ?></p>
		</div>
	<?php else : ?>

		<!-- Stats -->
		<div class="bkx-sync-stats">
			<div class="bkx-stat-card">
				<span class="bkx-stat-value"><?php echo count( $unsynced_bookings ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Pending Sync', 'bkx-freshbooks' ); ?></span>
			</div>
			<div class="bkx-stat-card">
				<?php
				global $wpdb;
				$total_synced = $wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_freshbooks_synced'"
				);
				?>
				<span class="bkx-stat-value"><?php echo esc_html( $total_synced ); ?></span>
				<span class="bkx-stat-label"><?php esc_html_e( 'Total Synced', 'bkx-freshbooks' ); ?></span>
			</div>
		</div>

		<!-- Pending -->
		<div class="bkx-sync-section">
			<h3><?php esc_html_e( 'Bookings Pending Sync', 'bkx-freshbooks' ); ?></h3>

			<?php if ( empty( $unsynced_bookings ) ) : ?>
				<p class="description"><?php esc_html_e( 'All bookings are synced!', 'bkx-freshbooks' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Booking', 'bkx-freshbooks' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'bkx-freshbooks' ); ?></th>
							<th><?php esc_html_e( 'Date', 'bkx-freshbooks' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'bkx-freshbooks' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bkx-freshbooks' ); ?></th>
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
									<button type="button" class="button button-small bkx-sync-booking"
											data-booking-id="<?php echo esc_attr( $booking->ID ); ?>">
										<span class="dashicons dashicons-update"></span>
										<?php esc_html_e( 'Sync', 'bkx-freshbooks' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p class="submit">
					<button type="button" class="button button-primary" id="bkx-sync-all">
						<?php esc_html_e( 'Sync All Pending', 'bkx-freshbooks' ); ?>
					</button>
				</p>
			<?php endif; ?>
		</div>

		<!-- Recently Synced -->
		<div class="bkx-sync-section">
			<h3><?php esc_html_e( 'Recently Synced', 'bkx-freshbooks' ); ?></h3>

			<?php if ( empty( $synced_bookings ) ) : ?>
				<p class="description"><?php esc_html_e( 'No bookings synced yet.', 'bkx-freshbooks' ); ?></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Booking', 'bkx-freshbooks' ); ?></th>
							<th><?php esc_html_e( 'Customer', 'bkx-freshbooks' ); ?></th>
							<th><?php esc_html_e( 'Invoice', 'bkx-freshbooks' ); ?></th>
							<th><?php esc_html_e( 'Synced', 'bkx-freshbooks' ); ?></th>
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
									$inv_number = get_post_meta( $booking->ID, '_freshbooks_invoice_number', true );
									echo $inv_number ? esc_html( $inv_number ) : '-';
									?>
								</td>
								<td>
									<?php
									$synced = get_post_meta( $booking->ID, '_freshbooks_synced', true );
									echo $synced ? esc_html( human_time_diff( strtotime( $synced ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'bkx-freshbooks' ) ) : '-';
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
