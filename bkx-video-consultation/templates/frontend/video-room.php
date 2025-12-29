<?php
/**
 * Video Room Frontend Template.
 *
 * @package BookingX\VideoConsultation
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$is_host = isset( $_GET['host'] ) && current_user_can( 'manage_options' );
?>

<div class="bkx-video-room" id="bkx-video-room" data-room-id="<?php echo esc_attr( $room_id ); ?>" data-is-host="<?php echo $is_host ? 'true' : 'false'; ?>">
	<!-- Pre-call screen -->
	<div class="bkx-precall-screen" id="bkx-precall-screen">
		<div class="bkx-precall-container">
			<h2><?php esc_html_e( 'Join Video Consultation', 'bkx-video-consultation' ); ?></h2>

			<!-- Camera preview -->
			<div class="bkx-preview-container">
				<video id="bkx-local-preview" autoplay muted playsinline></video>
				<div class="bkx-preview-controls">
					<button type="button" class="bkx-control-btn" id="bkx-toggle-camera-preview" title="<?php esc_attr_e( 'Toggle Camera', 'bkx-video-consultation' ); ?>">
						<span class="dashicons dashicons-camera"></span>
					</button>
					<button type="button" class="bkx-control-btn" id="bkx-toggle-mic-preview" title="<?php esc_attr_e( 'Toggle Microphone', 'bkx-video-consultation' ); ?>">
						<span class="dashicons dashicons-microphone"></span>
					</button>
				</div>
			</div>

			<!-- Join form -->
			<form id="bkx-join-form" class="bkx-join-form">
				<?php if ( ! is_user_logged_in() ) : ?>
					<div class="bkx-form-group">
						<label for="bkx-participant-name"><?php esc_html_e( 'Your Name', 'bkx-video-consultation' ); ?></label>
						<input type="text" id="bkx-participant-name" name="name" required>
					</div>
					<div class="bkx-form-group">
						<label for="bkx-participant-email"><?php esc_html_e( 'Email (optional)', 'bkx-video-consultation' ); ?></label>
						<input type="email" id="bkx-participant-email" name="email">
					</div>
				<?php else : ?>
					<?php $user = wp_get_current_user(); ?>
					<input type="hidden" id="bkx-participant-name" value="<?php echo esc_attr( $user->display_name ); ?>">
					<input type="hidden" id="bkx-participant-email" value="<?php echo esc_attr( $user->user_email ); ?>">
					<p class="bkx-joining-as"><?php printf( esc_html__( 'Joining as %s', 'bkx-video-consultation' ), esc_html( $user->display_name ) ); ?></p>
				<?php endif; ?>

				<button type="submit" class="bkx-join-btn" id="bkx-join-btn">
					<span class="dashicons dashicons-video-alt3"></span>
					<?php esc_html_e( 'Join Now', 'bkx-video-consultation' ); ?>
				</button>
			</form>

			<p class="bkx-permissions-note">
				<?php esc_html_e( 'You will be asked to allow camera and microphone access.', 'bkx-video-consultation' ); ?>
			</p>
		</div>
	</div>

	<!-- Waiting room screen -->
	<div class="bkx-waiting-screen" id="bkx-waiting-screen" style="display: none;">
		<div class="bkx-waiting-container">
			<div class="bkx-waiting-spinner"></div>
			<h2><?php esc_html_e( 'Waiting Room', 'bkx-video-consultation' ); ?></h2>
			<p><?php esc_html_e( 'Please wait for the host to admit you...', 'bkx-video-consultation' ); ?></p>
		</div>
	</div>

	<!-- Video call screen -->
	<div class="bkx-call-screen" id="bkx-call-screen" style="display: none;">
		<!-- Remote video (main view) -->
		<div class="bkx-video-main">
			<video id="bkx-remote-video" autoplay playsinline></video>
			<div class="bkx-remote-name" id="bkx-remote-name"></div>
			<div class="bkx-connection-status" id="bkx-connection-status">
				<?php esc_html_e( 'Connecting...', 'bkx-video-consultation' ); ?>
			</div>
		</div>

		<!-- Local video (picture-in-picture) -->
		<div class="bkx-video-pip">
			<video id="bkx-local-video" autoplay muted playsinline></video>
		</div>

		<!-- Screen share view -->
		<div class="bkx-screen-share" id="bkx-screen-share" style="display: none;">
			<video id="bkx-screen-video" autoplay playsinline></video>
		</div>

		<!-- Call controls -->
		<div class="bkx-call-controls">
			<button type="button" class="bkx-control-btn" id="bkx-toggle-camera" title="<?php esc_attr_e( 'Toggle Camera', 'bkx-video-consultation' ); ?>">
				<span class="dashicons dashicons-camera"></span>
			</button>
			<button type="button" class="bkx-control-btn" id="bkx-toggle-mic" title="<?php esc_attr_e( 'Toggle Microphone', 'bkx-video-consultation' ); ?>">
				<span class="dashicons dashicons-microphone"></span>
			</button>
			<button type="button" class="bkx-control-btn" id="bkx-toggle-screen" title="<?php esc_attr_e( 'Share Screen', 'bkx-video-consultation' ); ?>">
				<span class="dashicons dashicons-desktop"></span>
			</button>
			<button type="button" class="bkx-control-btn" id="bkx-toggle-chat" title="<?php esc_attr_e( 'Chat', 'bkx-video-consultation' ); ?>">
				<span class="dashicons dashicons-format-chat"></span>
			</button>
			<?php if ( $is_host ) : ?>
				<button type="button" class="bkx-control-btn" id="bkx-toggle-record" title="<?php esc_attr_e( 'Record', 'bkx-video-consultation' ); ?>">
					<span class="dashicons dashicons-controls-record"></span>
				</button>
				<button type="button" class="bkx-control-btn" id="bkx-toggle-waiting-room" title="<?php esc_attr_e( 'Waiting Room', 'bkx-video-consultation' ); ?>">
					<span class="dashicons dashicons-groups"></span>
					<span class="bkx-waiting-badge" id="bkx-waiting-badge" style="display: none;">0</span>
				</button>
			<?php endif; ?>
			<button type="button" class="bkx-control-btn bkx-end-call" id="bkx-end-call" title="<?php esc_attr_e( 'End Call', 'bkx-video-consultation' ); ?>">
				<span class="dashicons dashicons-phone"></span>
			</button>
		</div>

		<!-- Chat panel -->
		<div class="bkx-chat-panel" id="bkx-chat-panel" style="display: none;">
			<div class="bkx-chat-header">
				<h3><?php esc_html_e( 'Chat', 'bkx-video-consultation' ); ?></h3>
				<button type="button" class="bkx-close-chat" id="bkx-close-chat">
					<span class="dashicons dashicons-no"></span>
				</button>
			</div>
			<div class="bkx-chat-messages" id="bkx-chat-messages"></div>
			<form class="bkx-chat-form" id="bkx-chat-form">
				<input type="text" id="bkx-chat-input" placeholder="<?php esc_attr_e( 'Type a message...', 'bkx-video-consultation' ); ?>">
				<button type="submit">
					<span class="dashicons dashicons-arrow-right-alt"></span>
				</button>
			</form>
		</div>

		<!-- Waiting room panel (host only) -->
		<?php if ( $is_host ) : ?>
			<div class="bkx-waiting-room-panel" id="bkx-waiting-room-panel" style="display: none;">
				<div class="bkx-panel-header">
					<h3><?php esc_html_e( 'Waiting Room', 'bkx-video-consultation' ); ?></h3>
					<button type="button" class="bkx-close-panel" id="bkx-close-waiting-panel">
						<span class="dashicons dashicons-no"></span>
					</button>
				</div>
				<div class="bkx-waiting-list" id="bkx-waiting-list">
					<p class="bkx-no-waiting"><?php esc_html_e( 'No one is waiting', 'bkx-video-consultation' ); ?></p>
				</div>
			</div>
		<?php endif; ?>

		<!-- Call timer -->
		<div class="bkx-call-timer" id="bkx-call-timer">00:00</div>
	</div>

	<!-- Session ended screen -->
	<div class="bkx-ended-screen" id="bkx-ended-screen" style="display: none;">
		<div class="bkx-ended-container">
			<span class="dashicons dashicons-yes-alt"></span>
			<h2><?php esc_html_e( 'Session Ended', 'bkx-video-consultation' ); ?></h2>
			<p><?php esc_html_e( 'Thank you for your video consultation.', 'bkx-video-consultation' ); ?></p>
			<div class="bkx-call-summary" id="bkx-call-summary"></div>
			<a href="<?php echo esc_url( home_url() ); ?>" class="bkx-return-btn">
				<?php esc_html_e( 'Return to Home', 'bkx-video-consultation' ); ?>
			</a>
		</div>
	</div>
</div>
