<?php
/**
 * MYOB Sync Meta Box.
 *
 * @package BookingX\MYOB
 */

defined( 'ABSPATH' ) || exit;

$booking_id      = $post->ID;
$myob_synced     = get_post_meta( $booking_id, '_myob_synced', true );
$myob_invoice_id = get_post_meta( $booking_id, '_myob_invoice_id', true );
$myob_invoice_no = get_post_meta( $booking_id, '_myob_invoice_number', true );
$myob_customer   = get_post_meta( $booking_id, '_myob_customer_id', true );
$myob_payment    = get_post_meta( $booking_id, '_myob_payment_id', true );

$addon     = \BookingX\MYOB\MYOBAddon::get_instance();
$connected = $addon->is_connected();
?>

<div class="bkx-myob-meta-box">
	<?php if ( ! $connected ) : ?>
		<p class="description">
			<?php
			printf(
				/* translators: %s: settings link */
				esc_html__( 'MYOB is not connected. %s to configure.', 'bkx-myob' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=bkx-myob' ) ) . '">' . esc_html__( 'Go to settings', 'bkx-myob' ) . '</a>'
			);
			?>
		</p>
	<?php elseif ( $myob_synced ) : ?>
		<div class="bkx-sync-status bkx-synced">
			<span class="dashicons dashicons-yes-alt"></span>
			<strong><?php esc_html_e( 'Synced to MYOB', 'bkx-myob' ); ?></strong>
		</div>

		<table class="bkx-sync-details">
			<?php if ( $myob_invoice_no ) : ?>
				<tr>
					<td><?php esc_html_e( 'Invoice:', 'bkx-myob' ); ?></td>
					<td><code><?php echo esc_html( $myob_invoice_no ); ?></code></td>
				</tr>
			<?php endif; ?>
			<tr>
				<td><?php esc_html_e( 'Synced:', 'bkx-myob' ); ?></td>
				<td><?php echo esc_html( human_time_diff( strtotime( $myob_synced ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'bkx-myob' ) ); ?></td>
			</tr>
			<?php if ( $myob_customer ) : ?>
				<tr>
					<td><?php esc_html_e( 'Customer:', 'bkx-myob' ); ?></td>
					<td><span class="dashicons dashicons-yes"></span></td>
				</tr>
			<?php endif; ?>
			<?php if ( $myob_payment ) : ?>
				<tr>
					<td><?php esc_html_e( 'Payment:', 'bkx-myob' ); ?></td>
					<td><span class="dashicons dashicons-yes"></span></td>
				</tr>
			<?php endif; ?>
		</table>

		<button type="button" class="button bkx-sync-booking" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Re-sync', 'bkx-myob' ); ?>
		</button>
	<?php else : ?>
		<div class="bkx-sync-status bkx-not-synced">
			<span class="dashicons dashicons-minus"></span>
			<strong><?php esc_html_e( 'Not Synced', 'bkx-myob' ); ?></strong>
		</div>

		<button type="button" class="button button-primary bkx-sync-booking" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">
			<span class="dashicons dashicons-cloud-upload"></span>
			<?php esc_html_e( 'Sync to MYOB', 'bkx-myob' ); ?>
		</button>
	<?php endif; ?>
</div>

<style>
.bkx-myob-meta-box .bkx-sync-status {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 10px;
}
.bkx-myob-meta-box .bkx-synced { color: #00a32a; }
.bkx-myob-meta-box .bkx-not-synced { color: #666; }
.bkx-myob-meta-box .bkx-sync-details {
	width: 100%;
	margin-bottom: 10px;
}
.bkx-myob-meta-box .bkx-sync-details td {
	padding: 3px 0;
}
.bkx-myob-meta-box .button .dashicons {
	font-size: 16px;
	width: 16px;
	height: 16px;
	vertical-align: text-bottom;
}
</style>
