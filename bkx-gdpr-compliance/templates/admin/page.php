<?php
/**
 * Main admin page template.
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;

$tabs = array(
	'dashboard' => __( 'Dashboard', 'bkx-gdpr-compliance' ),
	'requests'  => __( 'Data Requests', 'bkx-gdpr-compliance' ),
	'consents'  => __( 'Consents', 'bkx-gdpr-compliance' ),
	'breaches'  => __( 'Breach Log', 'bkx-gdpr-compliance' ),
	'policies'  => __( 'Policy Generator', 'bkx-gdpr-compliance' ),
	'settings'  => __( 'Settings', 'bkx-gdpr-compliance' ),
);

$current_tab = $tab;

$addon = \BookingX\GdprCompliance\GdprComplianceAddon::get_instance();
$request_counts = $addon->get_service( 'requests' )->count_by_status();
$pending_requests = $request_counts['verified'] ?? 0;
?>

<div class="wrap bkx-gdpr-admin">
	<h1 class="wp-heading-inline">
		<?php esc_html_e( 'GDPR/CCPA Compliance', 'bkx-gdpr-compliance' ); ?>
	</h1>
	<hr class="wp-header-end">

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-gdpr&tab=' . $tab_key ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
				<?php if ( 'requests' === $tab_key && $pending_requests > 0 ) : ?>
					<span class="bkx-gdpr-badge"><?php echo esc_html( $pending_requests ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-gdpr-content">
		<?php
		switch ( $current_tab ) {
			case 'requests':
				include BKX_GDPR_PLUGIN_DIR . 'templates/admin/requests.php';
				break;

			case 'consents':
				include BKX_GDPR_PLUGIN_DIR . 'templates/admin/consents.php';
				break;

			case 'breaches':
				include BKX_GDPR_PLUGIN_DIR . 'templates/admin/breaches.php';
				break;

			case 'policies':
				include BKX_GDPR_PLUGIN_DIR . 'templates/admin/policies.php';
				break;

			case 'settings':
				include BKX_GDPR_PLUGIN_DIR . 'templates/admin/settings.php';
				break;

			default:
				include BKX_GDPR_PLUGIN_DIR . 'templates/admin/dashboard.php';
				break;
		}
		?>
	</div>
</div>
