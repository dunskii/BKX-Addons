<?php
/**
 * Main admin page template.
 *
 * @package BookingX\WebhooksManager
 */

defined( 'ABSPATH' ) || exit;

$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'webhooks'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$tabs = array(
	'webhooks'   => __( 'Webhooks', 'bkx-webhooks-manager' ),
	'deliveries' => __( 'Delivery Log', 'bkx-webhooks-manager' ),
	'settings'   => __( 'Settings', 'bkx-webhooks-manager' ),
);
?>
<div class="wrap bkx-webhooks-manager">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-rest-api" style="font-size: 30px; width: 30px; height: 30px; margin-right: 10px;"></span>
		<?php esc_html_e( 'Webhooks Manager', 'bkx-webhooks-manager' ); ?>
	</h1>

	<nav class="nav-tab-wrapper bkx-nav-tabs">
		<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-webhooks-manager&tab=' . $tab_id ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-tab-content">
		<?php
		switch ( $current_tab ) {
			case 'deliveries':
				include BKX_WEBHOOKS_PATH . 'templates/admin/deliveries.php';
				break;
			case 'settings':
				include BKX_WEBHOOKS_PATH . 'templates/admin/settings.php';
				break;
			case 'webhooks':
			default:
				include BKX_WEBHOOKS_PATH . 'templates/admin/webhooks.php';
				break;
		}
		?>
	</div>
</div>
