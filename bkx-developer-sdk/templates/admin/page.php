<?php
/**
 * Main admin page template.
 *
 * @package BookingX\DeveloperSDK
 */

defined( 'ABSPATH' ) || exit;

$addon       = \BookingX\DeveloperSDK\DeveloperSDKAddon::get_instance();
$templates   = $addon->get_code_templates();
$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'generator'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$tabs = array(
	'generator'     => __( 'Code Generator', 'bkx-developer-sdk' ),
	'api-explorer'  => __( 'API Explorer', 'bkx-developer-sdk' ),
	'hooks'         => __( 'Hook Inspector', 'bkx-developer-sdk' ),
	'sandbox'       => __( 'Sandbox', 'bkx-developer-sdk' ),
	'documentation' => __( 'Documentation', 'bkx-developer-sdk' ),
	'settings'      => __( 'Settings', 'bkx-developer-sdk' ),
);
?>
<div class="wrap bkx-developer-sdk">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-code-standards" style="font-size: 30px; width: 30px; height: 30px; margin-right: 10px;"></span>
		<?php esc_html_e( 'Developer SDK', 'bkx-developer-sdk' ); ?>
	</h1>

	<nav class="nav-tab-wrapper bkx-nav-tabs">
		<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-developer-sdk&tab=' . $tab_id ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-tab-content">
		<?php
		switch ( $current_tab ) {
			case 'api-explorer':
				include BKX_DEV_SDK_PATH . 'templates/admin/api-explorer.php';
				break;
			case 'hooks':
				include BKX_DEV_SDK_PATH . 'templates/admin/hooks.php';
				break;
			case 'sandbox':
				include BKX_DEV_SDK_PATH . 'templates/admin/sandbox.php';
				break;
			case 'documentation':
				include BKX_DEV_SDK_PATH . 'templates/admin/documentation.php';
				break;
			case 'settings':
				include BKX_DEV_SDK_PATH . 'templates/admin/settings.php';
				break;
			case 'generator':
			default:
				include BKX_DEV_SDK_PATH . 'templates/admin/generator.php';
				break;
		}
		?>
	</div>
</div>
