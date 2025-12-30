<?php
/**
 * WeChat Pay settings template.
 *
 * @package BookingX\WeChat
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WeChat\WeChatAddon::get_instance();
$settings = $addon->get_settings();
?>
<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'WeChat Pay Configuration', 'bkx-wechat' ); ?></h2>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="wechat_pay_enabled"><?php esc_html_e( 'Enable WeChat Pay', 'bkx-wechat' ); ?></label>
			</th>
			<td>
				<label class="bkx-toggle">
					<input type="checkbox" name="wechat_pay_enabled" id="wechat_pay_enabled" value="1"
						   <?php checked( ! empty( $settings['wechat_pay_enabled'] ) ); ?>>
					<span class="bkx-toggle-slider"></span>
				</label>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="mch_id"><?php esc_html_e( 'Merchant ID', 'bkx-wechat' ); ?></label>
			</th>
			<td>
				<input type="text" name="mch_id" id="mch_id" class="regular-text"
					   value="<?php echo esc_attr( $settings['mch_id'] ?? '' ); ?>"
					   placeholder="1234567890">
				<p class="description">
					<?php esc_html_e( 'Your WeChat Pay Merchant ID.', 'bkx-wechat' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="api_key"><?php esc_html_e( 'API Key (v2)', 'bkx-wechat' ); ?></label>
			</th>
			<td>
				<input type="password" name="api_key" id="api_key" class="regular-text"
					   value="<?php echo esc_attr( $settings['api_key'] ?? '' ); ?>">
				<p class="description">
					<?php esc_html_e( '32-character API key from WeChat Pay settings.', 'bkx-wechat' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="api_v3_key"><?php esc_html_e( 'API Key (v3)', 'bkx-wechat' ); ?></label>
			</th>
			<td>
				<input type="password" name="api_v3_key" id="api_v3_key" class="regular-text"
					   value="<?php echo esc_attr( $settings['api_v3_key'] ?? '' ); ?>">
				<p class="description">
					<?php esc_html_e( 'API v3 key for newer WeChat Pay features.', 'bkx-wechat' ); ?>
				</p>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Certificate Configuration', 'bkx-wechat' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Certificates are required for refunds and some API operations.', 'bkx-wechat' ); ?>
	</p>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="certificate_serial"><?php esc_html_e( 'Certificate Serial Number', 'bkx-wechat' ); ?></label>
			</th>
			<td>
				<input type="text" name="certificate_serial" id="certificate_serial" class="regular-text"
					   value="<?php echo esc_attr( $settings['certificate_serial'] ?? '' ); ?>">
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="certificate_path"><?php esc_html_e( 'Certificate Path', 'bkx-wechat' ); ?></label>
			</th>
			<td>
				<input type="text" name="certificate_path" id="certificate_path" class="large-text"
					   value="<?php echo esc_attr( $settings['certificate_path'] ?? '' ); ?>"
					   placeholder="/path/to/apiclient_cert.pem">
				<p class="description">
					<?php esc_html_e( 'Absolute path to your apiclient_cert.pem file.', 'bkx-wechat' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="private_key_path"><?php esc_html_e( 'Private Key Path', 'bkx-wechat' ); ?></label>
			</th>
			<td>
				<input type="text" name="private_key_path" id="private_key_path" class="large-text"
					   value="<?php echo esc_attr( $settings['private_key_path'] ?? '' ); ?>"
					   placeholder="/path/to/apiclient_key.pem">
				<p class="description">
					<?php esc_html_e( 'Absolute path to your apiclient_key.pem file.', 'bkx-wechat' ); ?>
				</p>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Payment Callback', 'bkx-wechat' ); ?></h2>

	<table class="form-table bkx-endpoints-table">
		<tr>
			<th><?php esc_html_e( 'Notify URL', 'bkx-wechat' ); ?></th>
			<td>
				<code><?php echo esc_url( rest_url( 'bkx-wechat/v1/pay/notify' ) ); ?></code>
				<p class="description">
					<?php esc_html_e( 'Configure this URL in WeChat Pay settings to receive payment notifications.', 'bkx-wechat' ); ?>
				</p>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Test Mode', 'bkx-wechat' ); ?></h2>

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
					<?php esc_html_e( 'Enable sandbox mode for testing. Payments will not be charged.', 'bkx-wechat' ); ?>
				</p>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Recent Transactions', 'bkx-wechat' ); ?></h2>

	<?php
	$transactions = get_posts(
		array(
			'post_type'      => 'bkx_booking',
			'posts_per_page' => 10,
			'meta_query'     => array(
				array(
					'key'     => '_wechat_transaction_id',
					'compare' => 'EXISTS',
				),
			),
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	?>

	<?php if ( empty( $transactions ) ) : ?>
		<p><?php esc_html_e( 'No WeChat Pay transactions yet.', 'bkx-wechat' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Booking', 'bkx-wechat' ); ?></th>
					<th><?php esc_html_e( 'Transaction ID', 'bkx-wechat' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'bkx-wechat' ); ?></th>
					<th><?php esc_html_e( 'Date', 'bkx-wechat' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $transactions as $booking ) : ?>
					<tr>
						<td>
							<a href="<?php echo esc_url( get_edit_post_link( $booking->ID ) ); ?>">
								#<?php echo esc_html( $booking->ID ); ?>
							</a>
						</td>
						<td><code><?php echo esc_html( get_post_meta( $booking->ID, '_wechat_transaction_id', true ) ); ?></code></td>
						<td>
							<?php
							$amount = get_post_meta( $booking->ID, '_wechat_paid_amount', true );
							echo '¥' . esc_html( number_format( $amount / 100, 2 ) );
							?>
						</td>
						<td><?php echo esc_html( get_post_meta( $booking->ID, '_wechat_paid_at', true ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
