<?php
/**
 * Admin page template.
 *
 * @package BookingX\APIBuilder
 */

defined( 'ABSPATH' ) || exit;

$current_tab = $tab ?? 'endpoints';
$tabs        = array(
	'endpoints' => __( 'Endpoints', 'bkx-api-builder' ),
	'keys'      => __( 'API Keys', 'bkx-api-builder' ),
	'webhooks'  => __( 'Webhooks', 'bkx-api-builder' ),
	'logs'      => __( 'Request Logs', 'bkx-api-builder' ),
	'docs'      => __( 'Documentation', 'bkx-api-builder' ),
	'settings'  => __( 'Settings', 'bkx-api-builder' ),
);
?>
<div class="wrap bkx-api-builder">
	<h1><?php esc_html_e( 'API Builder', 'bkx-api-builder' ); ?></h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-api-builder&tab=' . $tab_key ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-api-content">
		<?php
		$template = BKX_API_BUILDER_PATH . 'templates/admin/' . $current_tab . '.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		?>
	</div>
</div>
