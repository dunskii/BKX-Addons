<?php
/**
 * Main admin page template.
 *
 * @package BookingX\Discord
 */

defined( 'ABSPATH' ) || exit;

$current_tab = $tab ?? 'webhooks';
$tabs = array(
	'webhooks'  => __( 'Webhooks', 'bkx-discord' ),
	'settings'  => __( 'Settings', 'bkx-discord' ),
	'logs'      => __( 'Logs', 'bkx-discord' ),
);
?>

<div class="wrap bkx-discord-admin">
	<h1>
		<span class="bkx-discord-logo"></span>
		<?php esc_html_e( 'Discord Notifications', 'bkx-discord' ); ?>
	</h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_slug => $tab_name ) : ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-discord&tab=' . $tab_slug ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-discord-content">
		<?php
		switch ( $current_tab ) {
			case 'settings':
				include BKX_DISCORD_PLUGIN_DIR . 'templates/admin/settings.php';
				break;
			case 'logs':
				include BKX_DISCORD_PLUGIN_DIR . 'templates/admin/logs.php';
				break;
			default:
				include BKX_DISCORD_PLUGIN_DIR . 'templates/admin/webhooks.php';
				break;
		}
		?>
	</div>
</div>
