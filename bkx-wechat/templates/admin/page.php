<?php
/**
 * WeChat admin page template.
 *
 * @package BookingX\WeChat
 */

defined( 'ABSPATH' ) || exit;

$tabs = array(
	'settings'     => __( 'Settings', 'bkx-wechat' ),
	'official'     => __( 'Official Account', 'bkx-wechat' ),
	'mini_program' => __( 'Mini Program', 'bkx-wechat' ),
	'wechat_pay'   => __( 'WeChat Pay', 'bkx-wechat' ),
	'qr_codes'     => __( 'QR Codes', 'bkx-wechat' ),
	'messages'     => __( 'Messages', 'bkx-wechat' ),
);
?>
<div class="wrap bkx-wechat-admin">
	<h1>
		<span class="bkx-wechat-icon"></span>
		<?php esc_html_e( 'WeChat Integration', 'bkx-wechat' ); ?>
	</h1>

	<nav class="nav-tab-wrapper">
		<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-wechat&tab=' . $tab_id ) ); ?>"
			   class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-wechat-content">
		<?php
		switch ( $tab ) {
			case 'official':
				include BKX_WECHAT_PLUGIN_DIR . 'templates/admin/official-account.php';
				break;

			case 'mini_program':
				include BKX_WECHAT_PLUGIN_DIR . 'templates/admin/mini-program.php';
				break;

			case 'wechat_pay':
				include BKX_WECHAT_PLUGIN_DIR . 'templates/admin/wechat-pay.php';
				break;

			case 'qr_codes':
				include BKX_WECHAT_PLUGIN_DIR . 'templates/admin/qr-codes.php';
				break;

			case 'messages':
				include BKX_WECHAT_PLUGIN_DIR . 'templates/admin/messages.php';
				break;

			default:
				include BKX_WECHAT_PLUGIN_DIR . 'templates/admin/settings.php';
				break;
		}
		?>
	</div>
</div>
