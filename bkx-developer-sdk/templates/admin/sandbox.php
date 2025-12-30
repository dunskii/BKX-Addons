<?php
/**
 * Sandbox template.
 *
 * @package BookingX\DeveloperSDK
 */

defined( 'ABSPATH' ) || exit;

$addon             = \BookingX\DeveloperSDK\DeveloperSDKAddon::get_instance();
$sandbox_manager   = $addon->get_service( 'sandbox_manager' );
$data_generator    = $addon->get_service( 'test_data_generator' );

$sandboxes   = $sandbox_manager->get_all();
$test_counts = $data_generator->get_test_data_counts();
$settings    = get_option( 'bkx_developer_sdk_settings', array() );
?>

<div class="bkx-sandbox">
	<div class="bkx-sandbox-header">
		<h2><?php esc_html_e( 'Sandbox Environment', 'bkx-developer-sdk' ); ?></h2>
		<p><?php esc_html_e( 'Create isolated test environments and generate sample data for development.', 'bkx-developer-sdk' ); ?></p>
	</div>

	<div class="bkx-sandbox-grid">
		<!-- Create Sandbox -->
		<div class="bkx-sandbox-card">
			<h3>
				<span class="dashicons dashicons-database-add"></span>
				<?php esc_html_e( 'Create Sandbox', 'bkx-developer-sdk' ); ?>
			</h3>

			<?php if ( empty( $settings['enable_sandbox'] ) ) : ?>
				<div class="bkx-notice bkx-notice-warning">
					<?php esc_html_e( 'Sandbox feature is disabled. Enable it in Settings.', 'bkx-developer-sdk' ); ?>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'Create a new sandbox with sample services, staff, and bookings.', 'bkx-developer-sdk' ); ?></p>

				<div class="bkx-form-row">
					<input type="text" id="bkx-sandbox-name" placeholder="<?php esc_attr_e( 'Sandbox name', 'bkx-developer-sdk' ); ?>">
					<button type="button" class="button button-primary" id="bkx-create-sandbox">
						<?php esc_html_e( 'Create', 'bkx-developer-sdk' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>

		<!-- Generate Test Data -->
		<div class="bkx-sandbox-card">
			<h3>
				<span class="dashicons dashicons-randomize"></span>
				<?php esc_html_e( 'Generate Test Data', 'bkx-developer-sdk' ); ?>
			</h3>
			<p><?php esc_html_e( 'Generate random test data for development purposes.', 'bkx-developer-sdk' ); ?></p>

			<div class="bkx-generate-options">
				<div class="bkx-generate-row">
					<label><?php esc_html_e( 'Type:', 'bkx-developer-sdk' ); ?></label>
					<select id="bkx-data-type">
						<option value="booking"><?php esc_html_e( 'Bookings', 'bkx-developer-sdk' ); ?></option>
						<option value="service"><?php esc_html_e( 'Services', 'bkx-developer-sdk' ); ?></option>
						<option value="staff"><?php esc_html_e( 'Staff', 'bkx-developer-sdk' ); ?></option>
						<option value="extra"><?php esc_html_e( 'Extras', 'bkx-developer-sdk' ); ?></option>
					</select>
				</div>
				<div class="bkx-generate-row">
					<label><?php esc_html_e( 'Count:', 'bkx-developer-sdk' ); ?></label>
					<input type="number" id="bkx-data-count" value="5" min="1" max="50">
				</div>
				<button type="button" class="button button-primary" id="bkx-generate-data">
					<?php esc_html_e( 'Generate', 'bkx-developer-sdk' ); ?>
				</button>
			</div>
		</div>

		<!-- Test Data Summary -->
		<div class="bkx-sandbox-card">
			<h3>
				<span class="dashicons dashicons-chart-bar"></span>
				<?php esc_html_e( 'Test Data Summary', 'bkx-developer-sdk' ); ?>
			</h3>

			<div class="bkx-data-stats">
				<div class="bkx-data-stat">
					<span class="bkx-stat-value"><?php echo esc_html( $test_counts['booking'] ); ?></span>
					<span class="bkx-stat-label"><?php esc_html_e( 'Test Bookings', 'bkx-developer-sdk' ); ?></span>
				</div>
				<div class="bkx-data-stat">
					<span class="bkx-stat-value"><?php echo esc_html( $test_counts['service'] ); ?></span>
					<span class="bkx-stat-label"><?php esc_html_e( 'Test Services', 'bkx-developer-sdk' ); ?></span>
				</div>
				<div class="bkx-data-stat">
					<span class="bkx-stat-value"><?php echo esc_html( $test_counts['staff'] ); ?></span>
					<span class="bkx-stat-label"><?php esc_html_e( 'Test Staff', 'bkx-developer-sdk' ); ?></span>
				</div>
				<div class="bkx-data-stat">
					<span class="bkx-stat-value"><?php echo esc_html( $test_counts['extra'] ); ?></span>
					<span class="bkx-stat-label"><?php esc_html_e( 'Test Extras', 'bkx-developer-sdk' ); ?></span>
				</div>
			</div>

			<?php
			$total_test = array_sum( $test_counts );
			if ( $total_test > 0 ) :
				?>
				<button type="button" class="button" id="bkx-delete-test-data">
					<span class="dashicons dashicons-trash"></span>
					<?php esc_html_e( 'Delete All Test Data', 'bkx-developer-sdk' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<!-- Code Playground -->
		<?php if ( ! empty( $settings['debug_mode'] ) ) : ?>
			<div class="bkx-sandbox-card bkx-sandbox-wide">
				<h3>
					<span class="dashicons dashicons-editor-code"></span>
					<?php esc_html_e( 'Code Playground', 'bkx-developer-sdk' ); ?>
				</h3>
				<p><?php esc_html_e( 'Test PHP code snippets in a sandboxed environment.', 'bkx-developer-sdk' ); ?></p>

				<div class="bkx-playground">
					<textarea id="bkx-code-input" rows="10" placeholder="<?php esc_attr_e( '// Enter PHP code to test...', 'bkx-developer-sdk' ); ?>"><?php echo esc_textarea( "<?php\n// Example: Get all bookings\n\$bookings = get_posts( array(\n    'post_type' => 'bkx_booking',\n    'posts_per_page' => 5,\n) );\n\nforeach ( \$bookings as \$booking ) {\n    echo \$booking->post_title . \"\\n\";\n}" ); ?></textarea>

					<div class="bkx-playground-actions">
						<button type="button" class="button button-primary" id="bkx-run-code">
							<span class="dashicons dashicons-controls-play"></span>
							<?php esc_html_e( 'Run Code', 'bkx-developer-sdk' ); ?>
						</button>
						<button type="button" class="button" id="bkx-clear-code">
							<?php esc_html_e( 'Clear', 'bkx-developer-sdk' ); ?>
						</button>
					</div>

					<div id="bkx-code-output" class="bkx-code-output" style="display: none;">
						<h4><?php esc_html_e( 'Output', 'bkx-developer-sdk' ); ?></h4>
						<pre><code id="bkx-output-content"></code></pre>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<!-- Active Sandboxes -->
	<?php if ( ! empty( $sandboxes ) ) : ?>
		<div class="bkx-sandboxes-list">
			<h3><?php esc_html_e( 'Active Sandboxes', 'bkx-developer-sdk' ); ?></h3>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'bkx-developer-sdk' ); ?></th>
						<th><?php esc_html_e( 'ID', 'bkx-developer-sdk' ); ?></th>
						<th><?php esc_html_e( 'Created', 'bkx-developer-sdk' ); ?></th>
						<th><?php esc_html_e( 'Data', 'bkx-developer-sdk' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'bkx-developer-sdk' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sandboxes as $sandbox_id => $sandbox ) : ?>
						<tr>
							<td><?php echo esc_html( $sandbox['name'] ); ?></td>
							<td><code><?php echo esc_html( $sandbox_id ); ?></code></td>
							<td><?php echo esc_html( wp_date( 'M j, Y H:i', strtotime( $sandbox['created_at'] ) ) ); ?></td>
							<td>
								<?php
								$data = $sandbox['data'];
								printf(
									/* translators: 1: bookings count, 2: services count, 3: staff count */
									esc_html__( '%1$d bookings, %2$d services, %3$d staff', 'bkx-developer-sdk' ),
									count( $data['bookings'] ?? array() ),
									count( $data['services'] ?? array() ),
									count( $data['staff'] ?? array() )
								);
								?>
							</td>
							<td>
								<button type="button" class="button button-small bkx-delete-sandbox" data-id="<?php echo esc_attr( $sandbox_id ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
