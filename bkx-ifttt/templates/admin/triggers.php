<?php
/**
 * IFTTT Triggers Tab.
 *
 * @package BookingX\IFTTT
 */

defined( 'ABSPATH' ) || exit;

$addon           = \BookingX\IFTTT\IFTTTAddon::get_instance();
$trigger_handler = $addon->get_service( 'trigger_handler' );
$triggers        = $trigger_handler ? $trigger_handler->get_triggers() : array();
$settings        = get_option( 'bkx_ifttt_settings', array() );
$enabled_triggers = $settings['triggers'] ?? array();
?>

<div class="bkx-ifttt-triggers">
	<div class="bkx-section-header">
		<h2><?php esc_html_e( 'Available Triggers', 'bkx-ifttt' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Triggers send data from BookingX to IFTTT when specific events occur.', 'bkx-ifttt' ); ?>
		</p>
	</div>

	<form id="bkx-ifttt-triggers-form" method="post">
		<?php wp_nonce_field( 'bkx_ifttt_triggers', 'bkx_ifttt_nonce' ); ?>

		<table class="widefat bkx-triggers-table">
			<thead>
				<tr>
					<th class="column-enabled"><?php esc_html_e( 'Enabled', 'bkx-ifttt' ); ?></th>
					<th class="column-trigger"><?php esc_html_e( 'Trigger', 'bkx-ifttt' ); ?></th>
					<th class="column-description"><?php esc_html_e( 'Description', 'bkx-ifttt' ); ?></th>
					<th class="column-fields"><?php esc_html_e( 'Fields', 'bkx-ifttt' ); ?></th>
					<th class="column-actions"><?php esc_html_e( 'Actions', 'bkx-ifttt' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $triggers as $slug => $trigger ) : ?>
					<tr data-trigger="<?php echo esc_attr( $slug ); ?>">
						<td class="column-enabled">
							<label class="bkx-toggle">
								<input type="checkbox"
									   name="triggers[<?php echo esc_attr( $slug ); ?>]"
									   value="1"
									   <?php checked( ! empty( $enabled_triggers[ $slug ] ) ); ?>>
								<span class="bkx-toggle-slider"></span>
							</label>
						</td>
						<td class="column-trigger">
							<strong><?php echo esc_html( $trigger['name'] ); ?></strong>
							<code class="bkx-slug"><?php echo esc_html( $slug ); ?></code>
						</td>
						<td class="column-description">
							<?php echo esc_html( $trigger['description'] ); ?>
						</td>
						<td class="column-fields">
							<button type="button" class="button button-small bkx-view-fields"
									data-trigger="<?php echo esc_attr( $slug ); ?>">
								<span class="dashicons dashicons-visibility"></span>
								<?php echo esc_html( count( $trigger['fields'] ) ); ?> <?php esc_html_e( 'fields', 'bkx-ifttt' ); ?>
							</button>
						</td>
						<td class="column-actions">
							<button type="button" class="button button-small bkx-test-trigger"
									data-trigger="<?php echo esc_attr( $slug ); ?>">
								<span class="dashicons dashicons-controls-play"></span>
								<?php esc_html_e( 'Test', 'bkx-ifttt' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary" id="bkx-save-triggers">
				<?php esc_html_e( 'Save Trigger Settings', 'bkx-ifttt' ); ?>
			</button>
			<span class="spinner"></span>
		</p>
	</form>

	<div class="bkx-trigger-stats">
		<h3><?php esc_html_e( 'Recent Trigger Activity', 'bkx-ifttt' ); ?></h3>
		<?php
		$logs = get_option( 'bkx_ifttt_trigger_logs', array() );
		$logs = array_reverse( array_slice( $logs, -10 ) );

		if ( empty( $logs ) ) :
			?>
			<p class="description"><?php esc_html_e( 'No trigger activity recorded yet.', 'bkx-ifttt' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'bkx-ifttt' ); ?></th>
						<th><?php esc_html_e( 'Trigger', 'bkx-ifttt' ); ?></th>
						<th><?php esc_html_e( 'Booking ID', 'bkx-ifttt' ); ?></th>
						<th><?php esc_html_e( 'Webhooks Notified', 'bkx-ifttt' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log['timestamp'] ); ?></td>
							<td><code><?php echo esc_html( $log['trigger'] ); ?></code></td>
							<td>
								<?php if ( ! empty( $log['booking_id'] ) ) : ?>
									<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $log['booking_id'] . '&action=edit' ) ); ?>">
										#<?php echo esc_html( $log['booking_id'] ); ?>
									</a>
								<?php else : ?>
									-
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $log['webhook_count'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>

<!-- Fields Modal -->
<div id="bkx-fields-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'Trigger Fields', 'bkx-ifttt' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<div class="bkx-modal-body">
			<table class="widefat striped" id="bkx-fields-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Field', 'bkx-ifttt' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'bkx-ifttt' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bkx-ifttt' ); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
	</div>
</div>

<script type="text/javascript">
var bkxTriggerFields = <?php echo wp_json_encode( array_map( function( $trigger ) { return $trigger['fields']; }, $triggers ) ); ?>;
</script>
