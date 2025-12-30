<?php
/**
 * FreshBooks Sync Meta Box.
 *
 * @package BookingX\FreshBooks
 */

defined( 'ABSPATH' ) || exit;

$booking_id       = $post->ID;
$fb_synced        = get_post_meta( $booking_id, '_freshbooks_synced', true );
$fb_invoice_id    = get_post_meta( $booking_id, '_freshbooks_invoice_id', true );
$fb_invoice_no    = get_post_meta( $booking_id, '_freshbooks_invoice_number', true );
$fb_client_id     = get_post_meta( $booking_id, '_freshbooks_client_id', true );
$fb_payment_id    = get_post_meta( $booking_id, '_freshbooks_payment_id', true );

$addon     = \BookingX\FreshBooks\FreshBooksAddon::get_instance();
$connected = $addon->is_connected();
?>

<div class="bkx-freshbooks-meta-box">
	<?php if ( ! $connected ) : ?>
		<p class="description">
			<?php
			printf(
				/* translators: %s: settings link */
				esc_html__( 'FreshBooks is not connected. %s to configure.', 'bkx-freshbooks' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=bkx-freshbooks' ) ) . '">' . esc_html__( 'Go to settings', 'bkx-freshbooks' ) . '</a>'
			);
			?>
		</p>
	<?php elseif ( $fb_synced ) : ?>
		<div class="bkx-sync-status bkx-synced">
			<span class="dashicons dashicons-yes-alt"></span>
			<strong><?php esc_html_e( 'Synced to FreshBooks', 'bkx-freshbooks' ); ?></strong>
		</div>

		<table class="bkx-sync-details">
			<?php if ( $fb_invoice_no ) : ?>
				<tr>
					<td><?php esc_html_e( 'Invoice:', 'bkx-freshbooks' ); ?></td>
					<td><code><?php echo esc_html( $fb_invoice_no ); ?></code></td>
				</tr>
			<?php endif; ?>
			<tr>
				<td><?php esc_html_e( 'Synced:', 'bkx-freshbooks' ); ?></td>
				<td><?php echo esc_html( human_time_diff( strtotime( $fb_synced ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'bkx-freshbooks' ) ); ?></td>
			</tr>
			<?php if ( $fb_payment_id ) : ?>
				<tr>
					<td><?php esc_html_e( 'Payment:', 'bkx-freshbooks' ); ?></td>
					<td><span class="dashicons dashicons-yes"></span></td>
				</tr>
			<?php endif; ?>
		</table>

		<button type="button" class="button bkx-sync-booking" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Re-sync', 'bkx-freshbooks' ); ?>
		</button>
	<?php else : ?>
		<div class="bkx-sync-status bkx-not-synced">
			<span class="dashicons dashicons-minus"></span>
			<strong><?php esc_html_e( 'Not Synced', 'bkx-freshbooks' ); ?></strong>
		</div>

		<button type="button" class="button button-primary bkx-sync-booking" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">
			<span class="dashicons dashicons-cloud-upload"></span>
			<?php esc_html_e( 'Sync to FreshBooks', 'bkx-freshbooks' ); ?>
		</button>
	<?php endif; ?>
</div>

<style>
.bkx-freshbooks-meta-box .bkx-sync-status {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 10px;
}
.bkx-freshbooks-meta-box .bkx-synced { color: #0a9b00; }
.bkx-freshbooks-meta-box .bkx-not-synced { color: #666; }
.bkx-freshbooks-meta-box .bkx-sync-details {
	width: 100%;
	margin-bottom: 10px;
}
.bkx-freshbooks-meta-box .bkx-sync-details td {
	padding: 3px 0;
}
.bkx-freshbooks-meta-box .button .dashicons {
	font-size: 16px;
	width: 16px;
	height: 16px;
	vertical-align: text-bottom;
}
</style>
