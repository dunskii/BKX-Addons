<?php
/**
 * Apple Siri admin page template.
 *
 * @package BookingX\AppleSiri
 */

defined( 'ABSPATH' ) || exit;

$tabs = array(
	'settings'  => __( 'Settings', 'bkx-apple-siri' ),
	'shortcuts' => __( 'Shortcuts', 'bkx-apple-siri' ),
	'testing'   => __( 'Testing', 'bkx-apple-siri' ),
	'logs'      => __( 'Logs', 'bkx-apple-siri' ),
);
?>
<div class="wrap bkx-apple-siri-admin">
	<h1>
		<span class="dashicons dashicons-microphone"></span>
		<?php esc_html_e( 'Apple Siri Integration', 'bkx-apple-siri' ); ?>
	</h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-apple-siri&tab=' . $tab_id ) ); ?>"
			   class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-apple-siri-content">
		<?php
		switch ( $tab ) {
			case 'shortcuts':
				include BKX_APPLE_SIRI_PLUGIN_DIR . 'templates/admin/shortcuts.php';
				break;

			case 'testing':
				include BKX_APPLE_SIRI_PLUGIN_DIR . 'templates/admin/testing.php';
				break;

			case 'logs':
				include BKX_APPLE_SIRI_PLUGIN_DIR . 'templates/admin/logs.php';
				break;

			default:
				include BKX_APPLE_SIRI_PLUGIN_DIR . 'templates/admin/settings.php';
				break;
		}
		?>
	</div>
</div>
