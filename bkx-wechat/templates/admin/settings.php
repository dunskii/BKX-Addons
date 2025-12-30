<?php
/**
 * WeChat settings template.
 *
 * @package BookingX\WeChat
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WeChat\WeChatAddon::get_instance();
$settings = $addon->get_settings();
?>
<form id="bkx-wechat-settings-form" class="bkx-settings-form">
	<?php wp_nonce_field( 'bkx_wechat_nonce', 'bkx_wechat_nonce' ); ?>

	<!-- Enable/Disable -->
	<div class="bkx-settings-section">
		<h2><?php esc_html_e( 'General Settings', 'bkx-wechat' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="enabled"><?php esc_html_e( 'Enable WeChat Integration', 'bkx-wechat' ); ?></label>
				</th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="enabled" id="enabled" value="1"
							   <?php checked( ! empty( $settings['enabled'] ) ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Enable WeChat Official Account, Mini Program, and WeChat Pay integration.', 'bkx-wechat' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<!-- Official Account Credentials -->
	<div class="bkx-settings-section">
		<h2><?php esc_html_e( 'Official Account Credentials', 'bkx-wechat' ); ?></h2>
		<p class="description">
			<?php
			printf(
				/* translators: %s: WeChat Official Account Platform URL */
				esc_html__( 'Get your credentials from %s.', 'bkx-wechat' ),
				'<a href="https://mp.weixin.qq.com" target="_blank">WeChat Official Account Platform</a>'
			);
			?>
		</p>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="official_account_enabled"><?php esc_html_e( 'Enable Official Account', 'bkx-wechat' ); ?></label>
				</th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="official_account_enabled" id="official_account_enabled" value="1"
							   <?php checked( ! empty( $settings['official_account_enabled'] ) ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="app_id"><?php esc_html_e( 'App ID', 'bkx-wechat' ); ?></label>
				</th>
				<td>
					<input type="text" name="app_id" id="app_id" class="regular-text"
						   value="<?php echo esc_attr( $settings['app_id'] ?? '' ); ?>"
						   placeholder="wx1234567890abcdef">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="app_secret"><?php esc_html_e( 'App Secret', 'bkx-wechat' ); ?></label>
				</th>
				<td>
					<input type="password" name="app_secret" id="app_secret" class="regular-text"
						   value="<?php echo esc_attr( $settings['app_secret'] ?? '' ); ?>">
				</td>
			</tr>
		</table>
	</div>

	<!-- Mini Program Credentials -->
	<div class="bkx-settings-section">
		<h2><?php esc_html_e( 'Mini Program Credentials', 'bkx-wechat' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="mini_program_enabled"><?php esc_html_e( 'Enable Mini Program', 'bkx-wechat' ); ?></label>
				</th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="mini_program_enabled" id="mini_program_enabled" value="1"
							   <?php checked( ! empty( $settings['mini_program_enabled'] ) ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="mini_program_app_id"><?php esc_html_e( 'Mini Program App ID', 'bkx-wechat' ); ?></label>
				</th>
				<td>
					<input type="text" name="mini_program_app_id" id="mini_program_app_id" class="regular-text"
						   value="<?php echo esc_attr( $settings['mini_program_app_id'] ?? '' ); ?>">
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="mini_program_secret"><?php esc_html_e( 'Mini Program Secret', 'bkx-wechat' ); ?></label>
				</th>
				<td>
					<input type="password" name="mini_program_secret" id="mini_program_secret" class="regular-text"
						   value="<?php echo esc_attr( $settings['mini_program_secret'] ?? '' ); ?>">
				</td>
			</tr>
		</table>
	</div>

	<!-- API Endpoints -->
	<div class="bkx-settings-section">
		<h2><?php esc_html_e( 'API Endpoints', 'bkx-wechat' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Use these endpoints when configuring your WeChat settings.', 'bkx-wechat' ); ?>
		</p>

		<table class="form-table bkx-endpoints-table">
			<tr>
				<th><?php esc_html_e( 'Server URL (Callback)', 'bkx-wechat' ); ?></th>
				<td><code><?php echo esc_url( rest_url( 'bkx-wechat/v1/callback' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'WeChat Pay Notify', 'bkx-wechat' ); ?></th>
				<td><code><?php echo esc_url( rest_url( 'bkx-wechat/v1/pay/notify' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Mini Program Login', 'bkx-wechat' ); ?></th>
				<td><code><?php echo esc_url( rest_url( 'bkx-wechat/v1/mini/login' ) ); ?></code></td>
			</tr>
		</table>
	</div>

	<!-- Advanced Settings -->
	<div class="bkx-settings-section">
		<h2><?php esc_html_e( 'Advanced Settings', 'bkx-wechat' ); ?></h2>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="sandbox_mode"><?php esc_html_e( 'Sandbox Mode', 'bkx-wechat' ); ?></label>
				</th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="sandbox_mode" id="sandbox_mode" value="1"
							   <?php checked( $settings['sandbox_mode'] ?? true ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Enable sandbox mode for testing. Disable for production.', 'bkx-wechat' ); ?>
					</p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="debug_mode"><?php esc_html_e( 'Debug Mode', 'bkx-wechat' ); ?></label>
				</th>
				<td>
					<label class="bkx-toggle">
						<input type="checkbox" name="debug_mode" id="debug_mode" value="1"
							   <?php checked( ! empty( $settings['debug_mode'] ) ); ?>>
						<span class="bkx-toggle-slider"></span>
					</label>
					<p class="description">
						<?php esc_html_e( 'Enable debug logging. Disable in production.', 'bkx-wechat' ); ?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<p class="submit">
		<button type="button" class="button" id="bkx-test-connection">
			<?php esc_html_e( 'Test Connection', 'bkx-wechat' ); ?>
		</button>
		<button type="submit" class="button button-primary" id="bkx-save-settings">
			<?php esc_html_e( 'Save Settings', 'bkx-wechat' ); ?>
		</button>
		<span class="spinner"></span>
		<span id="bkx-save-status"></span>
	</p>
</form>
