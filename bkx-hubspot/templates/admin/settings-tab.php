<?php
/**
 * Settings tab for BookingX settings page.
 *
 * @package BookingX\HubSpot
 */

defined( 'ABSPATH' ) || exit;

$addon        = \BookingX\HubSpot\HubSpotAddon::get_instance();
$is_connected = $addon->is_connected();
?>

<div class="bkx-hs-settings-tab">
	<h3><?php esc_html_e( 'HubSpot Integration', 'bkx-hubspot' ); ?></h3>

	<?php if ( $is_connected ) : ?>
		<p class="bkx-status-connected">
			<span class="dashicons dashicons-yes-alt"></span>
			<?php esc_html_e( 'Connected to HubSpot', 'bkx-hubspot' ); ?>
		</p>
	<?php else : ?>
		<p class="bkx-status-disconnected">
			<span class="dashicons dashicons-warning"></span>
			<?php esc_html_e( 'Not connected to HubSpot', 'bkx-hubspot' ); ?>
		</p>
	<?php endif; ?>

	<p>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-hubspot' ) ); ?>" class="button">
			<?php esc_html_e( 'Go to HubSpot Settings', 'bkx-hubspot' ); ?>
		</a>
	</p>
</div>
