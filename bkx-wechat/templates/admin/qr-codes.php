<?php
/**
 * QR Codes template.
 *
 * @package BookingX\WeChat
 */

defined( 'ABSPATH' ) || exit;

$addon = \BookingX\WeChat\WeChatAddon::get_instance();
?>
<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Generate QR Codes', 'bkx-wechat' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Generate QR codes for various purposes. Users who scan these codes will be directed to your WeChat account.', 'bkx-wechat' ); ?>
	</p>

	<div class="bkx-qr-grid">
		<!-- Follow QR Code -->
		<div class="bkx-qr-card">
			<h3><?php esc_html_e( 'Follow QR Code', 'bkx-wechat' ); ?></h3>
			<p><?php esc_html_e( 'Generate a QR code that users can scan to follow your Official Account.', 'bkx-wechat' ); ?></p>
			<div class="bkx-qr-preview" id="qr-follow-preview"></div>
			<button type="button" class="button" data-type="follow">
				<?php esc_html_e( 'Generate', 'bkx-wechat' ); ?>
			</button>
		</div>

		<!-- Service QR Codes -->
		<div class="bkx-qr-card">
			<h3><?php esc_html_e( 'Service QR Codes', 'bkx-wechat' ); ?></h3>
			<p><?php esc_html_e( 'Generate QR codes for specific services.', 'bkx-wechat' ); ?></p>
			<select id="qr-service-select">
				<option value=""><?php esc_html_e( 'Select a service', 'bkx-wechat' ); ?></option>
				<?php
				$services = get_posts(
					array(
						'post_type'      => 'bkx_base',
						'posts_per_page' => -1,
						'orderby'        => 'title',
						'order'          => 'ASC',
					)
				);
				foreach ( $services as $service ) :
					?>
					<option value="<?php echo esc_attr( $service->ID ); ?>">
						<?php echo esc_html( $service->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<div class="bkx-qr-preview" id="qr-service-preview"></div>
			<button type="button" class="button" data-type="service" id="generate-service-qr">
				<?php esc_html_e( 'Generate', 'bkx-wechat' ); ?>
			</button>
		</div>

		<!-- Mini Program QR Code -->
		<div class="bkx-qr-card">
			<h3><?php esc_html_e( 'Mini Program QR Code', 'bkx-wechat' ); ?></h3>
			<p><?php esc_html_e( 'Generate a QR code that opens your Mini Program.', 'bkx-wechat' ); ?></p>
			<input type="text" id="qr-mp-page" class="regular-text" placeholder="pages/index/index">
			<div class="bkx-qr-preview" id="qr-mp-preview"></div>
			<button type="button" class="button" data-type="mini_program" id="generate-mp-qr">
				<?php esc_html_e( 'Generate', 'bkx-wechat' ); ?>
			</button>
		</div>
	</div>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Batch Generate', 'bkx-wechat' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Generate QR codes for all services at once.', 'bkx-wechat' ); ?>
	</p>

	<button type="button" class="button button-primary" id="bkx-batch-generate">
		<?php esc_html_e( 'Generate All Service QR Codes', 'bkx-wechat' ); ?>
	</button>

	<div id="bkx-batch-results" style="display: none; margin-top: 20px;">
		<h3><?php esc_html_e( 'Generated QR Codes', 'bkx-wechat' ); ?></h3>
		<div class="bkx-qr-batch-grid"></div>
	</div>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'QR Code Settings', 'bkx-wechat' ); ?></h2>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="qr_code_enabled"><?php esc_html_e( 'Enable QR Codes', 'bkx-wechat' ); ?></label>
			</th>
			<td>
				<label class="bkx-toggle">
					<input type="checkbox" name="qr_code_enabled" id="qr_code_enabled" value="1"
						   <?php checked( $addon->get_setting( 'qr_code_enabled', true ) ); ?>>
					<span class="bkx-toggle-slider"></span>
				</label>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Print Templates', 'bkx-wechat' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Download printable QR code templates.', 'bkx-wechat' ); ?>
	</p>

	<div class="bkx-print-templates">
		<div class="bkx-template-card">
			<h4><?php esc_html_e( 'Table Tent', 'bkx-wechat' ); ?></h4>
			<p><?php esc_html_e( 'A6 size tent card for restaurant tables.', 'bkx-wechat' ); ?></p>
			<button type="button" class="button" data-template="table_tent">
				<?php esc_html_e( 'Download Template', 'bkx-wechat' ); ?>
			</button>
		</div>

		<div class="bkx-template-card">
			<h4><?php esc_html_e( 'Counter Display', 'bkx-wechat' ); ?></h4>
			<p><?php esc_html_e( 'A5 size display for checkout counters.', 'bkx-wechat' ); ?></p>
			<button type="button" class="button" data-template="counter">
				<?php esc_html_e( 'Download Template', 'bkx-wechat' ); ?>
			</button>
		</div>

		<div class="bkx-template-card">
			<h4><?php esc_html_e( 'Poster', 'bkx-wechat' ); ?></h4>
			<p><?php esc_html_e( 'A4 size poster for walls and windows.', 'bkx-wechat' ); ?></p>
			<button type="button" class="button" data-template="poster">
				<?php esc_html_e( 'Download Template', 'bkx-wechat' ); ?>
			</button>
		</div>
	</div>
</div>
