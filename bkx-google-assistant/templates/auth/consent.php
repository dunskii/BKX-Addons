<?php
/**
 * Account linking consent page template.
 *
 * @package BookingX\GoogleAssistant
 */

defined( 'ABSPATH' ) || exit;

$current_user = wp_get_current_user();
$site_name    = get_bloginfo( 'name' );
$state        = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Link Your Account', 'bkx-google-assistant' ); ?> - <?php bloginfo( 'name' ); ?></title>
	<style>
		* {
			box-sizing: border-box;
		}
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			background: #f0f0f1;
			margin: 0;
			padding: 20px;
			display: flex;
			align-items: center;
			justify-content: center;
			min-height: 100vh;
		}
		.consent-box {
			background: #fff;
			border-radius: 8px;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
			max-width: 400px;
			width: 100%;
			padding: 40px;
			text-align: center;
		}
		.logo {
			width: 80px;
			height: 80px;
			background: #2271b1;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			margin: 0 auto 20px;
		}
		.logo svg {
			width: 40px;
			height: 40px;
			fill: #fff;
		}
		h1 {
			font-size: 24px;
			color: #1d2327;
			margin: 0 0 10px;
		}
		.site-name {
			color: #2271b1;
			font-weight: 600;
		}
		.user-info {
			background: #f6f7f7;
			border-radius: 4px;
			padding: 15px;
			margin: 20px 0;
		}
		.user-email {
			color: #50575e;
			font-size: 14px;
		}
		.permissions {
			text-align: left;
			margin: 20px 0;
		}
		.permissions h3 {
			font-size: 14px;
			color: #1d2327;
			margin: 0 0 10px;
		}
		.permissions ul {
			margin: 0;
			padding: 0 0 0 20px;
			color: #50575e;
			font-size: 14px;
		}
		.permissions li {
			margin-bottom: 8px;
		}
		.buttons {
			display: flex;
			gap: 10px;
			margin-top: 30px;
		}
		.btn {
			flex: 1;
			padding: 12px 20px;
			border: none;
			border-radius: 4px;
			font-size: 14px;
			font-weight: 500;
			cursor: pointer;
			text-decoration: none;
			display: inline-block;
		}
		.btn-primary {
			background: #2271b1;
			color: #fff;
		}
		.btn-primary:hover {
			background: #135e96;
		}
		.btn-secondary {
			background: #f0f0f1;
			color: #50575e;
		}
		.btn-secondary:hover {
			background: #dcdcde;
		}
		.notice {
			font-size: 12px;
			color: #8c8f94;
			margin-top: 20px;
		}
	</style>
</head>
<body>
	<div class="consent-box">
		<div class="logo">
			<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
				<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
			</svg>
		</div>

		<h1><?php esc_html_e( 'Link Your Account', 'bkx-google-assistant' ); ?></h1>
		<p><?php esc_html_e( 'Google Assistant wants to access your account on', 'bkx-google-assistant' ); ?> <span class="site-name"><?php echo esc_html( $site_name ); ?></span></p>

		<div class="user-info">
			<strong><?php echo esc_html( $current_user->display_name ); ?></strong>
			<div class="user-email"><?php echo esc_html( $current_user->user_email ); ?></div>
		</div>

		<div class="permissions">
			<h3><?php esc_html_e( 'This will allow Google Assistant to:', 'bkx-google-assistant' ); ?></h3>
			<ul>
				<li><?php esc_html_e( 'View your booking history', 'bkx-google-assistant' ); ?></li>
				<li><?php esc_html_e( 'Create new bookings on your behalf', 'bkx-google-assistant' ); ?></li>
				<li><?php esc_html_e( 'Cancel your existing bookings', 'bkx-google-assistant' ); ?></li>
				<li><?php esc_html_e( 'Access your name and email address', 'bkx-google-assistant' ); ?></li>
			</ul>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'bkx_assistant_consent', 'bkx_consent_nonce' ); ?>
			<input type="hidden" name="action" value="bkx_assistant_consent">
			<input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>">

			<div class="buttons">
				<a href="<?php echo esc_url( home_url() ); ?>" class="btn btn-secondary">
					<?php esc_html_e( 'Cancel', 'bkx-google-assistant' ); ?>
				</a>
				<button type="submit" class="btn btn-primary">
					<?php esc_html_e( 'Allow Access', 'bkx-google-assistant' ); ?>
				</button>
			</div>
		</form>

		<p class="notice">
			<?php esc_html_e( 'You can revoke this access at any time from your account settings.', 'bkx-google-assistant' ); ?>
		</p>
	</div>
</body>
</html>
