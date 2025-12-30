<?php
/**
 * Apple Shortcuts management template.
 *
 * @package BookingX\AppleSiri
 */

defined( 'ABSPATH' ) || exit;

$addon = \BookingX\AppleSiri\AppleSiriAddon::get_instance();
?>
<div class="bkx-shortcuts-section">
	<h2><?php esc_html_e( 'Available Shortcuts', 'bkx-apple-siri' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'These shortcuts can be added to the Apple Shortcuts app. Users can then trigger them via Siri.', 'bkx-apple-siri' ); ?>
	</p>

	<div class="bkx-shortcuts-grid">
		<!-- Book Appointment Shortcut -->
		<div class="bkx-shortcut-card">
			<div class="bkx-shortcut-icon" style="background-color: #2271b1;">
				<span class="dashicons dashicons-calendar-alt"></span>
			</div>
			<div class="bkx-shortcut-content">
				<h3><?php esc_html_e( 'Book Appointment', 'bkx-apple-siri' ); ?></h3>
				<p><?php esc_html_e( 'Schedule a new appointment via voice command.', 'bkx-apple-siri' ); ?></p>
				<p class="bkx-shortcut-phrase">
					<strong><?php esc_html_e( 'Say:', 'bkx-apple-siri' ); ?></strong>
					"<?php echo esc_html( $addon->get_setting( 'voice_phrases', array() )['book'] ?? 'Book an appointment' ); ?>"
				</p>
			</div>
			<div class="bkx-shortcut-actions">
				<button type="button" class="button" data-shortcut="book">
					<?php esc_html_e( 'Download Shortcut', 'bkx-apple-siri' ); ?>
				</button>
			</div>
		</div>

		<!-- Check Availability Shortcut -->
		<div class="bkx-shortcut-card">
			<div class="bkx-shortcut-icon" style="background-color: #00a32a;">
				<span class="dashicons dashicons-clock"></span>
			</div>
			<div class="bkx-shortcut-content">
				<h3><?php esc_html_e( 'Check Availability', 'bkx-apple-siri' ); ?></h3>
				<p><?php esc_html_e( 'See available appointment times for any date.', 'bkx-apple-siri' ); ?></p>
				<p class="bkx-shortcut-phrase">
					<strong><?php esc_html_e( 'Say:', 'bkx-apple-siri' ); ?></strong>
					"<?php echo esc_html( $addon->get_setting( 'voice_phrases', array() )['check_availability'] ?? 'Check availability' ); ?>"
				</p>
			</div>
			<div class="bkx-shortcut-actions">
				<button type="button" class="button" data-shortcut="check_availability">
					<?php esc_html_e( 'Download Shortcut', 'bkx-apple-siri' ); ?>
				</button>
			</div>
		</div>

		<!-- Reschedule Shortcut -->
		<div class="bkx-shortcut-card">
			<div class="bkx-shortcut-icon" style="background-color: #dba617;">
				<span class="dashicons dashicons-update"></span>
			</div>
			<div class="bkx-shortcut-content">
				<h3><?php esc_html_e( 'Reschedule Appointment', 'bkx-apple-siri' ); ?></h3>
				<p><?php esc_html_e( 'Change the date or time of an existing appointment.', 'bkx-apple-siri' ); ?></p>
				<p class="bkx-shortcut-phrase">
					<strong><?php esc_html_e( 'Say:', 'bkx-apple-siri' ); ?></strong>
					"<?php echo esc_html( $addon->get_setting( 'voice_phrases', array() )['reschedule'] ?? 'Reschedule my appointment' ); ?>"
				</p>
			</div>
			<div class="bkx-shortcut-actions">
				<button type="button" class="button" data-shortcut="reschedule">
					<?php esc_html_e( 'Download Shortcut', 'bkx-apple-siri' ); ?>
				</button>
			</div>
		</div>

		<!-- Cancel Shortcut -->
		<div class="bkx-shortcut-card">
			<div class="bkx-shortcut-icon" style="background-color: #d63638;">
				<span class="dashicons dashicons-no-alt"></span>
			</div>
			<div class="bkx-shortcut-content">
				<h3><?php esc_html_e( 'Cancel Appointment', 'bkx-apple-siri' ); ?></h3>
				<p><?php esc_html_e( 'Cancel an upcoming appointment.', 'bkx-apple-siri' ); ?></p>
				<p class="bkx-shortcut-phrase">
					<strong><?php esc_html_e( 'Say:', 'bkx-apple-siri' ); ?></strong>
					"<?php echo esc_html( $addon->get_setting( 'voice_phrases', array() )['cancel'] ?? 'Cancel my appointment' ); ?>"
				</p>
			</div>
			<div class="bkx-shortcut-actions">
				<button type="button" class="button" data-shortcut="cancel">
					<?php esc_html_e( 'Download Shortcut', 'bkx-apple-siri' ); ?>
				</button>
			</div>
		</div>

		<!-- Upcoming Appointments Shortcut -->
		<div class="bkx-shortcut-card">
			<div class="bkx-shortcut-icon" style="background-color: #7c3aed;">
				<span class="dashicons dashicons-list-view"></span>
			</div>
			<div class="bkx-shortcut-content">
				<h3><?php esc_html_e( 'My Appointments', 'bkx-apple-siri' ); ?></h3>
				<p><?php esc_html_e( 'View all upcoming appointments.', 'bkx-apple-siri' ); ?></p>
				<p class="bkx-shortcut-phrase">
					<strong><?php esc_html_e( 'Say:', 'bkx-apple-siri' ); ?></strong>
					"Show my appointments"
				</p>
			</div>
			<div class="bkx-shortcut-actions">
				<button type="button" class="button" data-shortcut="upcoming">
					<?php esc_html_e( 'Download Shortcut', 'bkx-apple-siri' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>

<div class="bkx-shortcuts-section">
	<h2><?php esc_html_e( 'Shortcut Gallery URL', 'bkx-apple-siri' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Share this URL with your customers to help them add shortcuts to their devices.', 'bkx-apple-siri' ); ?>
	</p>

	<div class="bkx-shortcut-url-box">
		<input type="text" class="large-text" readonly
			   value="<?php echo esc_url( rest_url( 'bkx-apple-siri/v1/shortcuts' ) ); ?>">
		<button type="button" class="button" id="bkx-copy-url">
			<?php esc_html_e( 'Copy URL', 'bkx-apple-siri' ); ?>
		</button>
	</div>
</div>

<div class="bkx-shortcuts-section">
	<h2><?php esc_html_e( 'Apple App Site Association', 'bkx-apple-siri' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Add this file to your domain for Universal Links and app association.', 'bkx-apple-siri' ); ?>
	</p>

	<?php
	$sirikit_api = $addon->get_service( 'sirikit_api' );
	$aasa        = $sirikit_api ? $sirikit_api->get_apple_app_site_association() : array();
	?>

	<div class="bkx-code-block">
		<div class="bkx-code-header">
			<code>.well-known/apple-app-site-association</code>
			<button type="button" class="button button-small" id="bkx-copy-aasa">
				<?php esc_html_e( 'Copy', 'bkx-apple-siri' ); ?>
			</button>
		</div>
		<pre id="bkx-aasa-content"><?php echo esc_html( wp_json_encode( $aasa, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
	</div>

	<p class="description">
		<?php esc_html_e( 'Place this file at:', 'bkx-apple-siri' ); ?>
		<code><?php echo esc_url( home_url( '/.well-known/apple-app-site-association' ) ); ?></code>
	</p>
</div>

<div class="bkx-shortcuts-section">
	<h2><?php esc_html_e( 'Integration Guide', 'bkx-apple-siri' ); ?></h2>

	<div class="bkx-guide-steps">
		<div class="bkx-guide-step">
			<div class="bkx-step-number">1</div>
			<div class="bkx-step-content">
				<h4><?php esc_html_e( 'Configure Apple Developer Account', 'bkx-apple-siri' ); ?></h4>
				<p><?php esc_html_e( 'Enable SiriKit capability in your iOS app on the Apple Developer Portal.', 'bkx-apple-siri' ); ?></p>
			</div>
		</div>

		<div class="bkx-guide-step">
			<div class="bkx-step-number">2</div>
			<div class="bkx-step-content">
				<h4><?php esc_html_e( 'Add Intent Extension', 'bkx-apple-siri' ); ?></h4>
				<p><?php esc_html_e( 'Create an Intents Extension in Xcode and configure supported intents.', 'bkx-apple-siri' ); ?></p>
			</div>
		</div>

		<div class="bkx-guide-step">
			<div class="bkx-step-number">3</div>
			<div class="bkx-step-content">
				<h4><?php esc_html_e( 'Configure API Endpoints', 'bkx-apple-siri' ); ?></h4>
				<p><?php esc_html_e( 'Point your intent handlers to the BookingX REST API endpoints.', 'bkx-apple-siri' ); ?></p>
			</div>
		</div>

		<div class="bkx-guide-step">
			<div class="bkx-step-number">4</div>
			<div class="bkx-step-content">
				<h4><?php esc_html_e( 'Donate Shortcuts', 'bkx-apple-siri' ); ?></h4>
				<p><?php esc_html_e( 'Donate shortcuts after successful bookings to enable personalized suggestions.', 'bkx-apple-siri' ); ?></p>
			</div>
		</div>
	</div>
</div>
