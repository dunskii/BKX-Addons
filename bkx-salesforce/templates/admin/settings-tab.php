<?php
/**
 * Settings tab for BookingX settings page.
 *
 * @package BookingX\Salesforce
 */

defined( 'ABSPATH' ) || exit;

$addon        = \BookingX\Salesforce\SalesforceAddon::get_instance();
$is_connected = $addon->is_connected();
?>

<div class="bkx-sf-settings-tab">
	<h3><?php esc_html_e( 'Salesforce Integration', 'bkx-salesforce' ); ?></h3>

	<?php if ( $is_connected ) : ?>
		<p class="bkx-status-connected">
			<span class="dashicons dashicons-yes-alt"></span>
			<?php esc_html_e( 'Connected to Salesforce', 'bkx-salesforce' ); ?>
		</p>
	<?php else : ?>
		<p class="bkx-status-disconnected">
			<span class="dashicons dashicons-warning"></span>
			<?php esc_html_e( 'Not connected to Salesforce', 'bkx-salesforce' ); ?>
		</p>
	<?php endif; ?>

	<p>
		<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-salesforce' ) ); ?>" class="button">
			<?php esc_html_e( 'Go to Salesforce Settings', 'bkx-salesforce' ); ?>
		</a>
	</p>
</div>
