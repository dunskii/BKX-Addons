<?php
/**
 * Apple Siri settings tab for BookingX settings page.
 *
 * @package BookingX\AppleSiri
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\AppleSiri\AppleSiriAddon::get_instance();
$settings = $addon->get_settings();
?>
<div class="bkx-apple-siri-tab">
	<div class="bkx-settings-intro">
		<div class="bkx-intro-icon">
			<span class="dashicons dashicons-microphone"></span>
		</div>
		<div class="bkx-intro-content">
			<h2><?php esc_html_e( 'Apple Siri Integration', 'bkx-apple-siri' ); ?></h2>
			<p>
				<?php esc_html_e( 'Enable voice booking through Siri and Apple Shortcuts integration.', 'bkx-apple-siri' ); ?>
			</p>
		</div>
	</div>

	<table class="form-table">
		<tr>
			<th scope="row">
				<?php esc_html_e( 'Status', 'bkx-apple-siri' ); ?>
			</th>
			<td>
				<?php if ( $addon->is_enabled() ) : ?>
					<span class="bkx-status-indicator bkx-status-active">
						<span class="dashicons dashicons-yes"></span>
						<?php esc_html_e( 'Active', 'bkx-apple-siri' ); ?>
					</span>
				<?php else : ?>
					<span class="bkx-status-indicator bkx-status-inactive">
						<span class="dashicons dashicons-no"></span>
						<?php esc_html_e( 'Inactive', 'bkx-apple-siri' ); ?>
					</span>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<?php esc_html_e( 'Configuration', 'bkx-apple-siri' ); ?>
			</th>
			<td>
				<?php
				$has_credentials = ! empty( $settings['team_id'] ) && ! empty( $settings['key_id'] ) && ! empty( $settings['private_key'] );
				if ( $has_credentials ) :
					?>
					<span class="bkx-config-status bkx-config-complete">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'Apple credentials configured', 'bkx-apple-siri' ); ?>
					</span>
				<?php else : ?>
					<span class="bkx-config-status bkx-config-incomplete">
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e( 'Apple credentials not configured', 'bkx-apple-siri' ); ?>
					</span>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<?php esc_html_e( 'Enabled Intents', 'bkx-apple-siri' ); ?>
			</th>
			<td>
				<?php
				$intent_types  = $settings['intent_types'] ?? array();
				$intent_labels = array(
					'book'               => __( 'Book', 'bkx-apple-siri' ),
					'reschedule'         => __( 'Reschedule', 'bkx-apple-siri' ),
					'cancel'             => __( 'Cancel', 'bkx-apple-siri' ),
					'check_availability' => __( 'Availability', 'bkx-apple-siri' ),
					'upcoming'           => __( 'Upcoming', 'bkx-apple-siri' ),
				);

				if ( empty( $intent_types ) ) :
					?>
					<em><?php esc_html_e( 'No intents enabled', 'bkx-apple-siri' ); ?></em>
				<?php else : ?>
					<?php foreach ( $intent_types as $intent ) : ?>
						<span class="bkx-intent-tag">
							<?php echo esc_html( $intent_labels[ $intent ] ?? $intent ); ?>
						</span>
					<?php endforeach; ?>
				<?php endif; ?>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<?php esc_html_e( 'Shortcuts', 'bkx-apple-siri' ); ?>
			</th>
			<td>
				<?php if ( $settings['shortcuts_enabled'] ?? true ) : ?>
					<span class="dashicons dashicons-yes"></span>
					<?php esc_html_e( 'Enabled', 'bkx-apple-siri' ); ?>
				<?php else : ?>
					<span class="dashicons dashicons-no"></span>
					<?php esc_html_e( 'Disabled', 'bkx-apple-siri' ); ?>
				<?php endif; ?>
			</td>
		</tr>
	</table>

	<p class="bkx-settings-link">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=bkx-apple-siri' ) ); ?>" class="button">
			<?php esc_html_e( 'Configure Apple Siri Settings', 'bkx-apple-siri' ); ?>
			<span class="dashicons dashicons-arrow-right-alt" style="margin-top: 4px;"></span>
		</a>
	</p>
</div>
