<?php
/**
 * IFTTT Integration Admin Page.
 *
 * @package BookingX\IFTTT
 */

defined( 'ABSPATH' ) || exit;

$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
$tabs       = array(
	'settings' => __( 'Settings', 'bkx-ifttt' ),
	'triggers' => __( 'Triggers', 'bkx-ifttt' ),
	'actions'  => __( 'Actions', 'bkx-ifttt' ),
	'webhooks' => __( 'Webhooks', 'bkx-ifttt' ),
	'logs'     => __( 'Logs', 'bkx-ifttt' ),
);
?>
<div class="wrap bkx-ifttt-admin">
	<h1>
		<span class="dashicons dashicons-controls-repeat" style="font-size: 30px; margin-right: 10px;"></span>
		<?php esc_html_e( 'BookingX IFTTT Integration', 'bkx-ifttt' ); ?>
	</h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_slug => $tab_name ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-ifttt&tab=' . $tab_slug ) ); ?>"
			   class="nav-tab <?php echo $active_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-ifttt-content">
		<?php
		$template = BKX_IFTTT_PLUGIN_DIR . 'templates/admin/' . $active_tab . '.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		?>
	</div>
</div>
