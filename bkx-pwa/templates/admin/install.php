<?php
/**
 * PWA Install Prompt Settings Tab.
 *
 * @package BookingX\PWA
 */

defined( 'ABSPATH' ) || exit;

$settings        = get_option( 'bkx_pwa_settings', array() );
$install_service = \BookingX\PWA\PWAAddon::get_instance()->get_service( 'install_prompt' );
$install_stats   = $install_service->get_stats();
?>

<div class="bkx-card">
	<h3><?php esc_html_e( 'Install Prompt Settings', 'bkx-pwa' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row"><?php esc_html_e( 'Show Install Prompt', 'bkx-pwa' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="install_prompt" value="1"
						   <?php checked( $settings['install_prompt'] ?? true ); ?>>
					<?php esc_html_e( 'Display a custom install prompt to users', 'bkx-pwa' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'Shows a banner encouraging users to install the app.', 'bkx-pwa' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="install_prompt_delay"><?php esc_html_e( 'Prompt Delay', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<select id="install_prompt_delay" name="install_prompt_delay">
					<option value="0" <?php selected( $settings['install_prompt_delay'] ?? 30, 0 ); ?>>
						<?php esc_html_e( 'Immediately', 'bkx-pwa' ); ?>
					</option>
					<option value="10" <?php selected( $settings['install_prompt_delay'] ?? 30, 10 ); ?>>
						<?php esc_html_e( '10 seconds', 'bkx-pwa' ); ?>
					</option>
					<option value="30" <?php selected( $settings['install_prompt_delay'] ?? 30, 30 ); ?>>
						<?php esc_html_e( '30 seconds', 'bkx-pwa' ); ?>
					</option>
					<option value="60" <?php selected( $settings['install_prompt_delay'] ?? 30, 60 ); ?>>
						<?php esc_html_e( '1 minute', 'bkx-pwa' ); ?>
					</option>
					<option value="120" <?php selected( $settings['install_prompt_delay'] ?? 30, 120 ); ?>>
						<?php esc_html_e( '2 minutes', 'bkx-pwa' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'How long to wait before showing the install prompt.', 'bkx-pwa' ); ?>
				</p>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-card">
	<h3><?php esc_html_e( 'Prompt Customization', 'bkx-pwa' ); ?></h3>

	<table class="form-table">
		<tr>
			<th scope="row">
				<label for="install_prompt_title"><?php esc_html_e( 'Title', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<input type="text" id="install_prompt_title" name="install_prompt_title" class="regular-text"
					   value="<?php echo esc_attr( $settings['install_prompt_title'] ?? __( 'Install Our App', 'bkx-pwa' ) ); ?>">
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="install_prompt_description"><?php esc_html_e( 'Description', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<textarea id="install_prompt_description" name="install_prompt_description" rows="2" class="large-text"><?php
					echo esc_textarea( $settings['install_prompt_description'] ?? __( 'Install our app for a better booking experience!', 'bkx-pwa' ) );
				?></textarea>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="install_button_text"><?php esc_html_e( 'Install Button', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<input type="text" id="install_button_text" name="install_button_text" class="regular-text"
					   value="<?php echo esc_attr( $settings['install_button_text'] ?? __( 'Install', 'bkx-pwa' ) ); ?>">
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="dismiss_button_text"><?php esc_html_e( 'Dismiss Button', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<input type="text" id="dismiss_button_text" name="dismiss_button_text" class="regular-text"
					   value="<?php echo esc_attr( $settings['dismiss_button_text'] ?? __( 'Not Now', 'bkx-pwa' ) ); ?>">
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="install_prompt_position"><?php esc_html_e( 'Position', 'bkx-pwa' ); ?></label>
			</th>
			<td>
				<select id="install_prompt_position" name="install_prompt_position">
					<option value="bottom" <?php selected( $settings['install_prompt_position'] ?? 'bottom', 'bottom' ); ?>>
						<?php esc_html_e( 'Bottom', 'bkx-pwa' ); ?>
					</option>
					<option value="top" <?php selected( $settings['install_prompt_position'] ?? 'bottom', 'top' ); ?>>
						<?php esc_html_e( 'Top', 'bkx-pwa' ); ?>
					</option>
				</select>
			</td>
		</tr>
	</table>
</div>

<div class="bkx-card">
	<h3><?php esc_html_e( 'Install Statistics', 'bkx-pwa' ); ?></h3>

	<div class="bkx-install-stats">
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $install_stats['prompt_shown'] ?? 0 ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Prompts Shown', 'bkx-pwa' ); ?></span>
		</div>
		<div class="bkx-stat-card bkx-stat-success">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $install_stats['install_accepted'] ?? 0 ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Installs', 'bkx-pwa' ); ?></span>
		</div>
		<div class="bkx-stat-card">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $install_stats['install_dismissed'] ?? 0 ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Dismissed', 'bkx-pwa' ); ?></span>
		</div>
		<div class="bkx-stat-card">
			<?php
			$rate = 0;
			if ( ( $install_stats['prompt_shown'] ?? 0 ) > 0 ) {
				$rate = round( ( $install_stats['install_accepted'] ?? 0 ) / $install_stats['prompt_shown'] * 100, 1 );
			}
			?>
			<span class="bkx-stat-value"><?php echo esc_html( $rate ); ?>%</span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Conversion Rate', 'bkx-pwa' ); ?></span>
		</div>
	</div>
</div>

<div class="bkx-card">
	<h3><?php esc_html_e( 'Prompt Preview', 'bkx-pwa' ); ?></h3>

	<div class="bkx-prompt-preview">
		<div class="bkx-install-prompt-demo" style="background-color: <?php echo esc_attr( $settings['theme_color'] ?? '#2563eb' ); ?>;">
			<div class="bkx-prompt-content">
				<?php if ( ! empty( $settings['icon_192'] ) ) : ?>
					<img src="<?php echo esc_url( $settings['icon_192'] ); ?>" class="bkx-prompt-icon" width="48" height="48">
				<?php endif; ?>
				<div class="bkx-prompt-text">
					<strong><?php echo esc_html( $settings['install_prompt_title'] ?? __( 'Install Our App', 'bkx-pwa' ) ); ?></strong>
					<p><?php echo esc_html( $settings['install_prompt_description'] ?? __( 'Install our app for a better booking experience!', 'bkx-pwa' ) ); ?></p>
				</div>
			</div>
			<div class="bkx-prompt-buttons">
				<button class="bkx-btn-dismiss"><?php echo esc_html( $settings['dismiss_button_text'] ?? __( 'Not Now', 'bkx-pwa' ) ); ?></button>
				<button class="bkx-btn-install"><?php echo esc_html( $settings['install_button_text'] ?? __( 'Install', 'bkx-pwa' ) ); ?></button>
			</div>
		</div>
	</div>
</div>
