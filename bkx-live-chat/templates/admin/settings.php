<?php
/**
 * Live Chat Settings Template.
 *
 * @package BookingX\LiveChat
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'bkx_live_chat_settings', array() );
?>

<div class="wrap bkx-livechat-settings">
	<h1><?php esc_html_e( 'Live Chat Settings', 'bkx-live-chat' ); ?></h1>

	<form id="bkx-livechat-settings-form">
		<!-- General Settings -->
		<div class="bkx-settings-section">
			<h2><?php esc_html_e( 'General Settings', 'bkx-live-chat' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable Live Chat', 'bkx-live-chat' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
							<?php esc_html_e( 'Show chat widget on your website', 'bkx-live-chat' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<!-- Widget Appearance -->
		<div class="bkx-settings-section">
			<h2><?php esc_html_e( 'Widget Appearance', 'bkx-live-chat' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Position', 'bkx-live-chat' ); ?></th>
					<td>
						<select name="widget_position">
							<option value="bottom-right" <?php selected( ( $settings['widget_position'] ?? '' ), 'bottom-right' ); ?>>
								<?php esc_html_e( 'Bottom Right', 'bkx-live-chat' ); ?>
							</option>
							<option value="bottom-left" <?php selected( ( $settings['widget_position'] ?? '' ), 'bottom-left' ); ?>>
								<?php esc_html_e( 'Bottom Left', 'bkx-live-chat' ); ?>
							</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Primary Color', 'bkx-live-chat' ); ?></th>
					<td>
						<input type="color" name="widget_color" value="<?php echo esc_attr( $settings['widget_color'] ?? '#2196f3' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Widget Title', 'bkx-live-chat' ); ?></th>
					<td>
						<input type="text" name="widget_title" value="<?php echo esc_attr( $settings['widget_title'] ?? '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Chat with us', 'bkx-live-chat' ); ?>">
					</td>
				</tr>
			</table>
		</div>

		<!-- Messages -->
		<div class="bkx-settings-section">
			<h2><?php esc_html_e( 'Messages', 'bkx-live-chat' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Welcome Message', 'bkx-live-chat' ); ?></th>
					<td>
						<textarea name="welcome_message" class="large-text" rows="3"><?php echo esc_textarea( $settings['welcome_message'] ?? '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Displayed when a visitor opens the chat widget.', 'bkx-live-chat' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Offline Message', 'bkx-live-chat' ); ?></th>
					<td>
						<textarea name="offline_message" class="large-text" rows="3"><?php echo esc_textarea( $settings['offline_message'] ?? '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Displayed when no operators are online.', 'bkx-live-chat' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Pre-chat Form -->
		<div class="bkx-settings-section">
			<h2><?php esc_html_e( 'Pre-chat Form', 'bkx-live-chat' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Require Name', 'bkx-live-chat' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="require_name" value="1" <?php checked( ! empty( $settings['require_name'] ) ); ?>>
							<?php esc_html_e( 'Require visitors to enter their name', 'bkx-live-chat' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Require Email', 'bkx-live-chat' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="require_email" value="1" <?php checked( ! empty( $settings['require_email'] ) ); ?>>
							<?php esc_html_e( 'Require visitors to enter their email', 'bkx-live-chat' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<!-- Features -->
		<div class="bkx-settings-section">
			<h2><?php esc_html_e( 'Features', 'bkx-live-chat' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Typing Indicator', 'bkx-live-chat' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="typing_indicator" value="1" <?php checked( ! empty( $settings['typing_indicator'] ) ); ?>>
							<?php esc_html_e( 'Show when someone is typing', 'bkx-live-chat' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sound Notifications', 'bkx-live-chat' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="sound_notifications" value="1" <?php checked( ! empty( $settings['sound_notifications'] ) ); ?>>
							<?php esc_html_e( 'Play sound on new messages', 'bkx-live-chat' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Email Transcripts', 'bkx-live-chat' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="email_transcripts" value="1" <?php checked( ! empty( $settings['email_transcripts'] ) ); ?>>
							<?php esc_html_e( 'Send chat transcript to visitor after chat ends', 'bkx-live-chat' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Satisfaction Survey', 'bkx-live-chat' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="satisfaction_survey" value="1" <?php checked( ! empty( $settings['satisfaction_survey'] ) ); ?>>
							<?php esc_html_e( 'Ask visitors to rate their experience after chat', 'bkx-live-chat' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<!-- File Sharing -->
		<div class="bkx-settings-section">
			<h2><?php esc_html_e( 'File Sharing', 'bkx-live-chat' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable File Sharing', 'bkx-live-chat' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="file_sharing" value="1" <?php checked( ! empty( $settings['file_sharing'] ) ); ?>>
							<?php esc_html_e( 'Allow file attachments in chat', 'bkx-live-chat' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Max File Size', 'bkx-live-chat' ); ?></th>
					<td>
						<input type="number" name="max_file_size" value="<?php echo esc_attr( $settings['max_file_size'] ?? 5 ); ?>" min="1" max="20" style="width: 80px;"> MB
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Allowed File Types', 'bkx-live-chat' ); ?></th>
					<td>
						<input type="text" name="allowed_file_types" value="<?php echo esc_attr( $settings['allowed_file_types'] ?? 'jpg,jpeg,png,gif,pdf,doc,docx' ); ?>" class="regular-text">
						<p class="description"><?php esc_html_e( 'Comma-separated list of file extensions.', 'bkx-live-chat' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Session Management -->
		<div class="bkx-settings-section">
			<h2><?php esc_html_e( 'Session Management', 'bkx-live-chat' ); ?></h2>
			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Idle Timeout', 'bkx-live-chat' ); ?></th>
					<td>
						<input type="number" name="idle_timeout" value="<?php echo esc_attr( $settings['idle_timeout'] ?? 30 ); ?>" min="5" max="120" style="width: 80px;"> <?php esc_html_e( 'minutes', 'bkx-live-chat' ); ?>
						<p class="description"><?php esc_html_e( 'Automatically close chats after this period of inactivity.', 'bkx-live-chat' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'bkx-live-chat' ); ?>">
		</p>
	</form>
</div>
