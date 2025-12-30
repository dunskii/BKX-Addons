<?php
/**
 * WeChat settings tab for BookingX settings page.
 *
 * @package BookingX\WeChat
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WeChat\WeChatAddon::get_instance();
$settings = $addon->get_settings();
?>
<div class="bkx-wechat-tab">
	<div class="bkx-settings-intro">
		<div class="bkx-intro-icon bkx-wechat-icon-large"></div>
		<div class="bkx-intro-content">
			<h2><?php esc_html_e( 'WeChat Integration', 'bkx-wechat' ); ?></h2>
			<p>
				<?php esc_html_e( 'Connect with customers in China through WeChat Official Account, Mini Program, and WeChat Pay.', 'bkx-wechat' ); ?>
			</p>
		</div>
	</div>

	<table class="form-table">
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Status', 'bkx-wechat' ); ?>
			</th>
			<td>
				<?php if ( $addon->is_enabled() ) : ?>
					<span class="bkx-status-indicator bkx-status-active">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Active', 'bkx-wechat' ); ?>
					</span>
				<?php else : ?>
					<span class="bkx-status-indicator bkx-status-inactive">
						<span class="dashicons dashicons-no"></span>
						<?php esc_html_e( 'Inactive', 'bkx-wechat' ); ?>
					</span>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<?php esc_html_e( 'Official Account', 'bkx-wechat' ); ?>
			</th>
			<td>
				<?php if ( ! empty( $settings['official_account_enabled'] ) ) : ?>
					<span class="dashicons dashicons-yes" style="color: #00a32a;"></span>
					<?php esc_html_e( 'Enabled', 'bkx-wechat' ); ?>
				<?php else : ?>
					<span class="dashicons dashicons-no" style="color: #d63638;"></span>
					<?php esc_html_e( 'Disabled', 'bkx-wechat' ); ?>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<?php esc_html_e( 'Mini Program', 'bkx-wechat' ); ?>
			</th>
			<td>
				<?php if ( ! empty( $settings['mini_program_enabled'] ) ) : ?>
					<span class="dashicons dashicons-yes" style="color: #00a32a;"></span>
					<?php esc_html_e( 'Enabled', 'bkx-wechat' ); ?>
				<?php else : ?>
					<span class="dashicons dashicons-no" style="color: #d63638;"></span>
					<?php esc_html_e( 'Disabled', 'bkx-wechat' ); ?>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<?php esc_html_e( 'WeChat Pay', 'bkx-wechat' ); ?>
			</th>
			<td>
				<?php if ( ! empty( $settings['wechat_pay_enabled'] ) ) : ?>
					<span class="dashicons dashicons-yes" style="color: #00a32a;"></span>
					<?php esc_html_e( 'Enabled', 'bkx-wechat' ); ?>
				<?php else : ?>
					<span class="dashicons dashicons-no" style="color: #d63638;"></span>
					<?php esc_html_e( 'Disabled', 'bkx-wechat' ); ?>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<?php esc_html_e( 'Mode', 'bkx-wechat' ); ?>
			</th>
			<td>
				<?php if ( $settings['sandbox_mode'] ?? true ) : ?>
					<span class="bkx-mode-badge bkx-mode-sandbox">
						<?php esc_html_e( 'Sandbox', 'bkx-wechat' ); ?>
					</span>
				<?php else : ?>
					<span class="bkx-mode-badge bkx-mode-live">
						<?php esc_html_e( 'Live', 'bkx-wechat' ); ?>
					</span>
				<?php endif; ?>
			</td>
		</tr>
	</table>

	<p class="bkx-settings-link">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-wechat' ) ); ?>" class="button">
			<?php esc_html_e( 'Configure WeChat Settings', 'bkx-wechat' ); ?>
			<span class="dashicons dashicons-arrow-right-alt" style="margin-top: 4px;"></span>
		</a>
	</p>
</div>
