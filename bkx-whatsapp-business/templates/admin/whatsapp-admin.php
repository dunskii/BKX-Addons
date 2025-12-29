<?php
/**
 * WhatsApp Admin Template.
 *
 * @package BookingX\WhatsAppBusiness
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$tabs = array(
	'conversations' => __( 'Conversations', 'bkx-whatsapp-business' ),
	'templates'     => __( 'Templates', 'bkx-whatsapp-business' ),
	'quick_replies' => __( 'Quick Replies', 'bkx-whatsapp-business' ),
	'settings'      => __( 'Settings', 'bkx-whatsapp-business' ),
);
?>

<div class="wrap bkx-whatsapp-admin">
	<h1>
		<span class="dashicons dashicons-whatsapp" style="color: #25D366;"></span>
		<?php esc_html_e( 'WhatsApp Business', 'bkx-whatsapp-business' ); ?>
	</h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id ) ); ?>"
			   class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-whatsapp-content">
		<?php
		switch ( $tab ) {
			case 'conversations':
				include BKX_WHATSAPP_PLUGIN_DIR . 'templates/admin/tabs/conversations.php';
				break;

			case 'templates':
				include BKX_WHATSAPP_PLUGIN_DIR . 'templates/admin/tabs/templates.php';
				break;

			case 'quick_replies':
				include BKX_WHATSAPP_PLUGIN_DIR . 'templates/admin/tabs/quick-replies.php';
				break;

			case 'settings':
				include BKX_WHATSAPP_PLUGIN_DIR . 'templates/admin/tabs/settings.php';
				break;
		}
		?>
	</div>
</div>
