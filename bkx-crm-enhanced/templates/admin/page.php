<?php
/**
 * Main admin page template.
 *
 * @package BookingX\CRM
 */

defined( 'ABSPATH' ) || exit;

$current_tab = $tab ?? 'customers';
$tabs = array(
	'customers' => __( 'Customers', 'bkx-crm' ),
	'segments'  => __( 'Segments', 'bkx-crm' ),
	'tags'      => __( 'Tags', 'bkx-crm' ),
	'followups' => __( 'Follow-ups', 'bkx-crm' ),
	'settings'  => __( 'Settings', 'bkx-crm' ),
);
?>

<div class="wrap bkx-crm-admin">
	<h1>
		<span class="dashicons dashicons-groups"></span>
		<?php esc_html_e( 'Customer Relationship Management', 'bkx-crm' ); ?>
	</h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_slug => $tab_name ) : ?>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-crm&tab=' . $tab_slug ) ); ?>"
			   class="nav-tab <?php echo $current_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-crm-content">
		<?php
		switch ( $current_tab ) {
			case 'segments':
				include BKX_CRM_PLUGIN_DIR . 'templates/admin/segments.php';
				break;
			case 'tags':
				include BKX_CRM_PLUGIN_DIR . 'templates/admin/tags.php';
				break;
			case 'followups':
				include BKX_CRM_PLUGIN_DIR . 'templates/admin/followups.php';
				break;
			case 'settings':
				include BKX_CRM_PLUGIN_DIR . 'templates/admin/settings.php';
				break;
			default:
				include BKX_CRM_PLUGIN_DIR . 'templates/admin/customers.php';
				break;
		}
		?>
	</div>
</div>
