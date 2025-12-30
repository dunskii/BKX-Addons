<?php
/**
 * IFTTT Actions Tab.
 *
 * @package BookingX\IFTTT
 */

defined( 'ABSPATH' ) || exit;

$addon          = \BookingX\IFTTT\IFTTTAddon::get_instance();
$action_handler = $addon->get_service( 'action_handler' );
$actions        = $action_handler ? $action_handler->get_actions() : array();
$settings       = get_option( 'bkx_ifttt_settings', array() );
$enabled_actions = $settings['actions'] ?? array();
?>

<div class="bkx-ifttt-actions">
	<div class="bkx-section-header">
		<h2><?php esc_html_e( 'Available Actions', 'bkx-ifttt' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Actions allow IFTTT to perform operations in BookingX based on external triggers.', 'bkx-ifttt' ); ?>
		</p>
	</div>

	<form id="bkx-ifttt-actions-form" method="post">
		<?php wp_nonce_field( 'bkx_ifttt_actions', 'bkx_ifttt_nonce' ); ?>

		<table class="widefat bkx-actions-table">
			<thead>
				<tr>
					<th class="column-enabled"><?php esc_html_e( 'Enabled', 'bkx-ifttt' ); ?></th>
					<th class="column-action"><?php esc_html_e( 'Action', 'bkx-ifttt' ); ?></th>
					<th class="column-description"><?php esc_html_e( 'Description', 'bkx-ifttt' ); ?></th>
					<th class="column-fields"><?php esc_html_e( 'Fields', 'bkx-ifttt' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $actions as $slug => $action ) : ?>
					<tr data-action="<?php echo esc_attr( $slug ); ?>">
						<td class="column-enabled">
							<label class="bkx-toggle">
								<input type="checkbox"
									   name="actions[<?php echo esc_attr( $slug ); ?>]"
									   value="1"
									   <?php checked( ! empty( $enabled_actions[ $slug ] ) ); ?>>
								<span class="bkx-toggle-slider"></span>
							</label>
						</td>
						<td class="column-action">
							<strong><?php echo esc_html( $action['name'] ); ?></strong>
							<code class="bkx-slug"><?php echo esc_html( $slug ); ?></code>
						</td>
						<td class="column-description">
							<?php echo esc_html( $action['description'] ); ?>
						</td>
						<td class="column-fields">
							<button type="button" class="button button-small bkx-view-action-fields"
									data-action="<?php echo esc_attr( $slug ); ?>">
								<span class="dashicons dashicons-visibility"></span>
								<?php echo esc_html( count( $action['fields'] ) ); ?> <?php esc_html_e( 'fields', 'bkx-ifttt' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary" id="bkx-save-actions">
				<?php esc_html_e( 'Save Action Settings', 'bkx-ifttt' ); ?>
			</button>
			<span class="spinner"></span>
		</p>
	</form>

	<div class="bkx-action-stats">
		<h3><?php esc_html_e( 'Recent Action Activity', 'bkx-ifttt' ); ?></h3>
		<?php
		$logs = get_option( 'bkx_ifttt_action_logs', array() );
		$logs = array_reverse( array_slice( $logs, -10 ) );

		if ( empty( $logs ) ) :
			?>
			<p class="description"><?php esc_html_e( 'No action activity recorded yet.', 'bkx-ifttt' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Time', 'bkx-ifttt' ); ?></th>
						<th><?php esc_html_e( 'Action', 'bkx-ifttt' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-ifttt' ); ?></th>
						<th><?php esc_html_e( 'Details', 'bkx-ifttt' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log['timestamp'] ); ?></td>
							<td><code><?php echo esc_html( $log['action'] ); ?></code></td>
							<td>
								<?php if ( $log['success'] ) : ?>
									<span class="bkx-status-success">
										<span class="dashicons dashicons-yes-alt"></span>
										<?php esc_html_e( 'Success', 'bkx-ifttt' ); ?>
									</span>
								<?php else : ?>
									<span class="bkx-status-error">
										<span class="dashicons dashicons-warning"></span>
										<?php esc_html_e( 'Failed', 'bkx-ifttt' ); ?>
									</span>
								<?php endif; ?>
							</td>
							<td>
								<?php
								if ( ! empty( $log['data']['id'] ) ) {
									printf(
										'<a href="%s">#%d</a>',
										esc_url( admin_url( 'post.php?post=' . $log['data']['id'] . '&action=edit' ) ),
										esc_html( $log['data']['id'] )
									);
								} elseif ( ! empty( $log['error'] ) ) {
									echo esc_html( $log['error'] );
								}
								?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="bkx-action-examples">
		<h3><?php esc_html_e( 'Action Examples', 'bkx-ifttt' ); ?></h3>
		<div class="bkx-example-cards">
			<div class="bkx-example-card">
				<h4><?php esc_html_e( 'Create Booking from Google Form', 'bkx-ifttt' ); ?></h4>
				<p><?php esc_html_e( 'When a Google Form is submitted, automatically create a booking in BookingX.', 'bkx-ifttt' ); ?></p>
			</div>
			<div class="bkx-example-card">
				<h4><?php esc_html_e( 'Cancel on Email', 'bkx-ifttt' ); ?></h4>
				<p><?php esc_html_e( 'When an email with subject "Cancel Booking" is received, cancel the booking.', 'bkx-ifttt' ); ?></p>
			</div>
			<div class="bkx-example-card">
				<h4><?php esc_html_e( 'Voice Command Booking', 'bkx-ifttt' ); ?></h4>
				<p><?php esc_html_e( 'Say "Confirm booking 123" to Google Assistant to confirm a booking.', 'bkx-ifttt' ); ?></p>
			</div>
		</div>
	</div>
</div>

<!-- Action Fields Modal -->
<div id="bkx-action-fields-modal" class="bkx-modal" style="display: none;">
	<div class="bkx-modal-content">
		<div class="bkx-modal-header">
			<h3><?php esc_html_e( 'Action Fields', 'bkx-ifttt' ); ?></h3>
			<button type="button" class="bkx-modal-close">&times;</button>
		</div>
		<div class="bkx-modal-body">
			<table class="widefat striped" id="bkx-action-fields-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Field', 'bkx-ifttt' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'bkx-ifttt' ); ?></th>
						<th><?php esc_html_e( 'Type', 'bkx-ifttt' ); ?></th>
						<th><?php esc_html_e( 'Required', 'bkx-ifttt' ); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
	</div>
</div>

<script type="text/javascript">
var bkxActionFields = <?php echo wp_json_encode( array_map( function( $action ) { return $action['fields']; }, $actions ) ); ?>;
</script>
