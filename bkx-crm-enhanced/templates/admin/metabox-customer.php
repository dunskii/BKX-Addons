<?php
/**
 * Customer metabox template for booking edit screen.
 *
 * @package BookingX\CRM
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $customer ) ) {
	return;
}

$tag_service = new \BookingX\CRM\Services\TagService();
?>

<?php if ( $customer ) : ?>
	<?php $tags = $tag_service->get_customer_tags( $customer->id ); ?>

	<div class="bkx-customer-metabox">
		<p>
			<strong><?php echo esc_html( trim( $customer->first_name . ' ' . $customer->last_name ) ?: __( 'Guest', 'bkx-crm' ) ); ?></strong>
		</p>

		<p class="bkx-customer-stats">
			<span>
				<strong><?php echo esc_html( $customer->total_bookings ); ?></strong>
				<?php esc_html_e( 'bookings', 'bkx-crm' ); ?>
			</span>
			<span>
				<strong>$<?php echo esc_html( number_format( (float) $customer->lifetime_value, 2 ) ); ?></strong>
				<?php esc_html_e( 'lifetime', 'bkx-crm' ); ?>
			</span>
		</p>

		<?php if ( ! empty( $tags ) ) : ?>
			<div class="bkx-crm-tags bkx-metabox-tags">
				<?php foreach ( $tags as $tag ) : ?>
					<span class="bkx-crm-tag" style="background-color: <?php echo esc_attr( $tag->color ); ?>;">
						<?php echo esc_html( $tag->name ); ?>
					</span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<p>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-crm&customer=' . $customer->id ) ); ?>" class="button button-small">
				<?php esc_html_e( 'View Full Profile', 'bkx-crm' ); ?>
			</a>
		</p>
	</div>
<?php else : ?>
	<p>
		<?php esc_html_e( 'No CRM profile found for this customer.', 'bkx-crm' ); ?>
	</p>
	<p>
		<button type="button" class="button button-small" id="bkx-create-customer-profile" data-email="<?php echo esc_attr( $email ); ?>">
			<?php esc_html_e( 'Create Profile', 'bkx-crm' ); ?>
		</button>
	</p>
<?php endif; ?>
