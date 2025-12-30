<?php
/**
 * Mini Program settings template.
 *
 * @package BookingX\WeChat
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WeChat\WeChatAddon::get_instance();
$settings = $addon->get_settings();
?>
<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Mini Program Configuration', 'bkx-wechat' ); ?></h2>

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
				<label for="mini_program_app_id"><?php esc_html_e( 'App ID', 'bkx-wechat' ); ?></label>
			</th>
			<td>
				<input type="text" name="mini_program_app_id" id="mini_program_app_id" class="regular-text"
					   value="<?php echo esc_attr( $settings['mini_program_app_id'] ?? '' ); ?>">
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="mini_program_secret"><?php esc_html_e( 'App Secret', 'bkx-wechat' ); ?></label>
			</th>
			<td>
				<input type="password" name="mini_program_secret" id="mini_program_secret" class="regular-text"
					   value="<?php echo esc_attr( $settings['mini_program_secret'] ?? '' ); ?>">
			</td>
		</tr>
	</table>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'API Endpoints', 'bkx-wechat' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Use these endpoints in your Mini Program code.', 'bkx-wechat' ); ?>
	</p>

	<table class="form-table bkx-endpoints-table">
		<tr>
			<th><?php esc_html_e( 'Login', 'bkx-wechat' ); ?></th>
			<td><code><?php echo esc_url( rest_url( 'bkx-wechat/v1/mini/login' ) ); ?></code></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Services', 'bkx-wechat' ); ?></th>
			<td><code><?php echo esc_url( rest_url( 'bkx-wechat/v1/mini/services' ) ); ?></code></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Availability', 'bkx-wechat' ); ?></th>
			<td><code><?php echo esc_url( rest_url( 'bkx-wechat/v1/mini/availability' ) ); ?></code></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Create Booking', 'bkx-wechat' ); ?></th>
			<td><code><?php echo esc_url( rest_url( 'bkx-wechat/v1/mini/book' ) ); ?></code></td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'User Bookings', 'bkx-wechat' ); ?></th>
			<td><code><?php echo esc_url( rest_url( 'bkx-wechat/v1/mini/bookings' ) ); ?></code></td>
		</tr>
	</table>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Mini Program Code Sample', 'bkx-wechat' ); ?></h2>

	<div class="bkx-code-block">
		<div class="bkx-code-header">
			<code>app.js</code>
			<button type="button" class="button button-small" id="bkx-copy-code">
				<?php esc_html_e( 'Copy', 'bkx-wechat' ); ?>
			</button>
		</div>
		<pre id="bkx-code-content">// BookingX Mini Program API Configuration
const API_BASE = '<?php echo esc_url( rest_url( 'bkx-wechat/v1/mini' ) ); ?>';

App({
  globalData: {
    token: null,
    userInfo: null
  },

  onLaunch: function() {
    this.login();
  },

  login: function() {
    const that = this;
    wx.login({
      success: function(res) {
        wx.request({
          url: API_BASE + '/login',
          method: 'POST',
          data: { code: res.code },
          success: function(response) {
            if (response.data.success) {
              that.globalData.token = response.data.token;
              that.globalData.userInfo = response.data.user;
            }
          }
        });
      }
    });
  },

  request: function(options) {
    const token = this.globalData.token;
    return new Promise((resolve, reject) => {
      wx.request({
        url: API_BASE + options.url,
        method: options.method || 'GET',
        data: options.data || {},
        header: {
          'X-WeChat-Token': token,
          'Content-Type': 'application/json'
        },
        success: resolve,
        fail: reject
      });
    });
  }
});</pre>
	</div>
</div>

<div class="bkx-settings-section">
	<h2><?php esc_html_e( 'Mini Program QR Code', 'bkx-wechat' ); ?></h2>

	<div class="bkx-qr-generator">
		<div class="bkx-qr-form">
			<label for="mp_qr_page"><?php esc_html_e( 'Page Path', 'bkx-wechat' ); ?></label>
			<input type="text" id="mp_qr_page" class="regular-text" value="pages/index/index" placeholder="pages/index/index">

			<label for="mp_qr_scene"><?php esc_html_e( 'Scene', 'bkx-wechat' ); ?></label>
			<input type="text" id="mp_qr_scene" class="regular-text" placeholder="<?php esc_attr_e( 'Optional scene parameter', 'bkx-wechat' ); ?>">

			<button type="button" class="button" id="bkx-generate-mp-qr">
				<?php esc_html_e( 'Generate QR Code', 'bkx-wechat' ); ?>
			</button>
		</div>

		<div class="bkx-qr-preview" id="mp-qr-preview" style="display: none;">
			<img id="mp-qr-image" src="" alt="Mini Program QR">
			<button type="button" class="button" id="bkx-download-mp-qr">
				<?php esc_html_e( 'Download', 'bkx-wechat' ); ?>
			</button>
		</div>
	</div>
</div>
