<?php
/**
 * Business Intelligence Settings Template.
 *
 * @package BookingX\BusinessIntelligence
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = get_option( 'bkx_bi_settings', array() );
?>
<div class="wrap bkx-bi-settings">
	<h1><?php esc_html_e( 'Business Intelligence Settings', 'bkx-business-intelligence' ); ?></h1>

	<?php if ( isset( $_GET['settings-updated'] ) && 'true' === $_GET['settings-updated'] ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved successfully.', 'bkx-business-intelligence' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="bkx-bi-settings-form">
		<input type="hidden" name="action" value="bkx_bi_save_settings" />
		<?php wp_nonce_field( 'bkx_bi_save_settings', 'bkx_bi_settings_nonce' ); ?>

		<!-- General Settings -->
		<div class="bkx-bi-card">
			<h2><?php esc_html_e( 'General Settings', 'bkx-business-intelligence' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="default_period"><?php esc_html_e( 'Default Period', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<select id="default_period" name="bkx_bi_settings[default_period]" class="bkx-bi-select">
							<option value="week" <?php selected( $settings['default_period'] ?? 'week', 'week' ); ?>><?php esc_html_e( 'Last 7 Days', 'bkx-business-intelligence' ); ?></option>
							<option value="month" <?php selected( $settings['default_period'] ?? '', 'month' ); ?>><?php esc_html_e( 'Last 30 Days', 'bkx-business-intelligence' ); ?></option>
							<option value="quarter" <?php selected( $settings['default_period'] ?? '', 'quarter' ); ?>><?php esc_html_e( 'Last 90 Days', 'bkx-business-intelligence' ); ?></option>
							<option value="year" <?php selected( $settings['default_period'] ?? '', 'year' ); ?>><?php esc_html_e( 'Last Year', 'bkx-business-intelligence' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Default time period for dashboard and reports.', 'bkx-business-intelligence' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="currency_symbol"><?php esc_html_e( 'Currency Symbol', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<input type="text" id="currency_symbol" name="bkx_bi_settings[currency_symbol]" value="<?php echo esc_attr( $settings['currency_symbol'] ?? '$' ); ?>" class="small-text" />
						<p class="description"><?php esc_html_e( 'Symbol to display for currency values.', 'bkx-business-intelligence' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="decimal_places"><?php esc_html_e( 'Decimal Places', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<input type="number" id="decimal_places" name="bkx_bi_settings[decimal_places]" value="<?php echo esc_attr( $settings['decimal_places'] ?? 2 ); ?>" min="0" max="4" class="small-text" />
						<p class="description"><?php esc_html_e( 'Number of decimal places for currency values.', 'bkx-business-intelligence' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Dashboard Widget -->
		<div class="bkx-bi-card">
			<h2><?php esc_html_e( 'Dashboard Widget', 'bkx-business-intelligence' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="show_dashboard_widget"><?php esc_html_e( 'Show Dashboard Widget', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<label class="bkx-bi-toggle">
							<input type="checkbox" id="show_dashboard_widget" name="bkx_bi_settings[show_dashboard_widget]" value="1" <?php checked( $settings['show_dashboard_widget'] ?? 1, 1 ); ?> />
							<span class="bkx-bi-toggle-slider"></span>
						</label>
						<p class="description"><?php esc_html_e( 'Display a summary widget on the WordPress dashboard.', 'bkx-business-intelligence' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="widget_metrics"><?php esc_html_e( 'Widget Metrics', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<?php
						$widget_metrics = $settings['widget_metrics'] ?? array( 'revenue', 'bookings', 'customers' );
						$available_metrics = array(
							'revenue'    => __( 'Revenue', 'bkx-business-intelligence' ),
							'bookings'   => __( 'Bookings', 'bkx-business-intelligence' ),
							'customers'  => __( 'Customers', 'bkx-business-intelligence' ),
							'avg_value'  => __( 'Average Value', 'bkx-business-intelligence' ),
							'completion' => __( 'Completion Rate', 'bkx-business-intelligence' ),
						);
						?>
						<?php foreach ( $available_metrics as $key => $label ) : ?>
							<label style="display: block; margin-bottom: 5px;">
								<input type="checkbox" name="bkx_bi_settings[widget_metrics][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $widget_metrics, true ) ); ?> />
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</td>
				</tr>
			</table>
		</div>

		<!-- Data Aggregation -->
		<div class="bkx-bi-card">
			<h2><?php esc_html_e( 'Data Aggregation', 'bkx-business-intelligence' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="aggregation_frequency"><?php esc_html_e( 'Aggregation Frequency', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<select id="aggregation_frequency" name="bkx_bi_settings[aggregation_frequency]" class="bkx-bi-select">
							<option value="hourly" <?php selected( $settings['aggregation_frequency'] ?? 'hourly', 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'bkx-business-intelligence' ); ?></option>
							<option value="twicedaily" <?php selected( $settings['aggregation_frequency'] ?? '', 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily', 'bkx-business-intelligence' ); ?></option>
							<option value="daily" <?php selected( $settings['aggregation_frequency'] ?? '', 'daily' ); ?>><?php esc_html_e( 'Daily', 'bkx-business-intelligence' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'How often to aggregate booking data for faster reporting.', 'bkx-business-intelligence' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="cache_duration"><?php esc_html_e( 'Cache Duration', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<select id="cache_duration" name="bkx_bi_settings[cache_duration]" class="bkx-bi-select">
							<option value="1800" <?php selected( $settings['cache_duration'] ?? 3600, 1800 ); ?>><?php esc_html_e( '30 minutes', 'bkx-business-intelligence' ); ?></option>
							<option value="3600" <?php selected( $settings['cache_duration'] ?? 3600, 3600 ); ?>><?php esc_html_e( '1 hour', 'bkx-business-intelligence' ); ?></option>
							<option value="7200" <?php selected( $settings['cache_duration'] ?? 3600, 7200 ); ?>><?php esc_html_e( '2 hours', 'bkx-business-intelligence' ); ?></option>
							<option value="21600" <?php selected( $settings['cache_duration'] ?? 3600, 21600 ); ?>><?php esc_html_e( '6 hours', 'bkx-business-intelligence' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'How long to cache dashboard data.', 'bkx-business-intelligence' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Clear Cache', 'bkx-business-intelligence' ); ?></th>
					<td>
						<button type="button" id="clear-cache" class="button">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e( 'Clear All Cached Data', 'bkx-business-intelligence' ); ?>
						</button>
						<p class="description"><?php esc_html_e( 'Clear all cached analytics data. The cache will be rebuilt on the next request.', 'bkx-business-intelligence' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Scheduled Reports -->
		<div class="bkx-bi-card">
			<h2><?php esc_html_e( 'Scheduled Reports', 'bkx-business-intelligence' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="email_from_name"><?php esc_html_e( 'Email From Name', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<input type="text" id="email_from_name" name="bkx_bi_settings[email_from_name]" value="<?php echo esc_attr( $settings['email_from_name'] ?? get_bloginfo( 'name' ) ); ?>" class="regular-text" />
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="email_from_address"><?php esc_html_e( 'Email From Address', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<input type="email" id="email_from_address" name="bkx_bi_settings[email_from_address]" value="<?php echo esc_attr( $settings['email_from_address'] ?? get_option( 'admin_email' ) ); ?>" class="regular-text" />
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="default_report_format"><?php esc_html_e( 'Default Report Format', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<select id="default_report_format" name="bkx_bi_settings[default_report_format]" class="bkx-bi-select">
							<option value="pdf" <?php selected( $settings['default_report_format'] ?? 'pdf', 'pdf' ); ?>><?php esc_html_e( 'PDF', 'bkx-business-intelligence' ); ?></option>
							<option value="csv" <?php selected( $settings['default_report_format'] ?? '', 'csv' ); ?>><?php esc_html_e( 'CSV', 'bkx-business-intelligence' ); ?></option>
							<option value="excel" <?php selected( $settings['default_report_format'] ?? '', 'excel' ); ?>><?php esc_html_e( 'Excel', 'bkx-business-intelligence' ); ?></option>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="export_retention"><?php esc_html_e( 'Export File Retention', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<select id="export_retention" name="bkx_bi_settings[export_retention]" class="bkx-bi-select">
							<option value="1" <?php selected( $settings['export_retention'] ?? 7, 1 ); ?>><?php esc_html_e( '1 day', 'bkx-business-intelligence' ); ?></option>
							<option value="7" <?php selected( $settings['export_retention'] ?? 7, 7 ); ?>><?php esc_html_e( '7 days', 'bkx-business-intelligence' ); ?></option>
							<option value="14" <?php selected( $settings['export_retention'] ?? 7, 14 ); ?>><?php esc_html_e( '14 days', 'bkx-business-intelligence' ); ?></option>
							<option value="30" <?php selected( $settings['export_retention'] ?? 7, 30 ); ?>><?php esc_html_e( '30 days', 'bkx-business-intelligence' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'How long to keep exported report files before automatic cleanup.', 'bkx-business-intelligence' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Forecasting -->
		<div class="bkx-bi-card">
			<h2><?php esc_html_e( 'Forecasting', 'bkx-business-intelligence' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="forecast_enabled"><?php esc_html_e( 'Enable Forecasting', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<label class="bkx-bi-toggle">
							<input type="checkbox" id="forecast_enabled" name="bkx_bi_settings[forecast_enabled]" value="1" <?php checked( $settings['forecast_enabled'] ?? 1, 1 ); ?> />
							<span class="bkx-bi-toggle-slider"></span>
						</label>
						<p class="description"><?php esc_html_e( 'Enable revenue and booking forecasting using historical data.', 'bkx-business-intelligence' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="forecast_min_data"><?php esc_html_e( 'Minimum Data Days', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<input type="number" id="forecast_min_data" name="bkx_bi_settings[forecast_min_data]" value="<?php echo esc_attr( $settings['forecast_min_data'] ?? 14 ); ?>" min="7" max="90" class="small-text" />
						<p class="description"><?php esc_html_e( 'Minimum days of historical data required for accurate forecasting.', 'bkx-business-intelligence' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="forecast_confidence"><?php esc_html_e( 'Confidence Interval', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<select id="forecast_confidence" name="bkx_bi_settings[forecast_confidence]" class="bkx-bi-select">
							<option value="80" <?php selected( $settings['forecast_confidence'] ?? 95, 80 ); ?>><?php esc_html_e( '80%', 'bkx-business-intelligence' ); ?></option>
							<option value="90" <?php selected( $settings['forecast_confidence'] ?? 95, 90 ); ?>><?php esc_html_e( '90%', 'bkx-business-intelligence' ); ?></option>
							<option value="95" <?php selected( $settings['forecast_confidence'] ?? 95, 95 ); ?>><?php esc_html_e( '95%', 'bkx-business-intelligence' ); ?></option>
							<option value="99" <?php selected( $settings['forecast_confidence'] ?? 95, 99 ); ?>><?php esc_html_e( '99%', 'bkx-business-intelligence' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Confidence level for forecast prediction intervals.', 'bkx-business-intelligence' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Access Control -->
		<div class="bkx-bi-card">
			<h2><?php esc_html_e( 'Access Control', 'bkx-business-intelligence' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="access_roles"><?php esc_html_e( 'Access Roles', 'bkx-business-intelligence' ); ?></label>
					</th>
					<td>
						<?php
						$access_roles = $settings['access_roles'] ?? array( 'administrator' );
						$editable_roles = get_editable_roles();
						?>
						<?php foreach ( $editable_roles as $role_key => $role_info ) : ?>
							<label style="display: block; margin-bottom: 5px;">
								<input type="checkbox" name="bkx_bi_settings[access_roles][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $access_roles, true ) ); ?> <?php disabled( $role_key, 'administrator' ); ?> />
								<?php echo esc_html( $role_info['name'] ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'User roles that can access Business Intelligence. Administrators always have access.', 'bkx-business-intelligence' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Danger Zone -->
		<div class="bkx-bi-card bkx-bi-card-danger">
			<h2><?php esc_html_e( 'Danger Zone', 'bkx-business-intelligence' ); ?></h2>

			<table class="form-table">
				<tr>
					<th scope="row"><?php esc_html_e( 'Reset Aggregated Data', 'bkx-business-intelligence' ); ?></th>
					<td>
						<button type="button" id="reset-data" class="button button-secondary">
							<span class="dashicons dashicons-warning"></span>
							<?php esc_html_e( 'Reset All Aggregated Data', 'bkx-business-intelligence' ); ?>
						</button>
						<p class="description"><?php esc_html_e( 'Delete all aggregated metrics data. This will not affect your actual bookings.', 'bkx-business-intelligence' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Delete All Reports', 'bkx-business-intelligence' ); ?></th>
					<td>
						<button type="button" id="delete-reports" class="button button-secondary">
							<span class="dashicons dashicons-trash"></span>
							<?php esc_html_e( 'Delete All Saved Reports', 'bkx-business-intelligence' ); ?>
						</button>
						<p class="description"><?php esc_html_e( 'Delete all saved reports and scheduled report configurations.', 'bkx-business-intelligence' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<p class="submit">
			<button type="submit" class="button button-primary button-large">
				<?php esc_html_e( 'Save Settings', 'bkx-business-intelligence' ); ?>
			</button>
		</p>
	</form>
</div>

<script>
jQuery(document).ready(function($) {
	// Clear cache.
	$('#clear-cache').on('click', function() {
		var $btn = $(this);
		$btn.prop('disabled', true).find('.dashicons').addClass('bkx-bi-spin');

		$.post(ajaxurl, {
			action: 'bkx_bi_clear_cache',
			nonce: '<?php echo esc_js( wp_create_nonce( 'bkx_bi_admin' ) ); ?>'
		}, function(response) {
			$btn.prop('disabled', false).find('.dashicons').removeClass('bkx-bi-spin');
			if (response.success) {
				alert('<?php echo esc_js( __( 'Cache cleared successfully.', 'bkx-business-intelligence' ) ); ?>');
			}
		});
	});

	// Reset data.
	$('#reset-data').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to reset all aggregated data? This action cannot be undone.', 'bkx-business-intelligence' ) ); ?>')) {
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true);

		$.post(ajaxurl, {
			action: 'bkx_bi_reset_data',
			nonce: '<?php echo esc_js( wp_create_nonce( 'bkx_bi_admin' ) ); ?>'
		}, function(response) {
			$btn.prop('disabled', false);
			if (response.success) {
				alert('<?php echo esc_js( __( 'Data reset successfully.', 'bkx-business-intelligence' ) ); ?>');
			}
		});
	});

	// Delete reports.
	$('#delete-reports').on('click', function() {
		if (!confirm('<?php echo esc_js( __( 'Are you sure you want to delete all saved reports? This action cannot be undone.', 'bkx-business-intelligence' ) ); ?>')) {
			return;
		}

		var $btn = $(this);
		$btn.prop('disabled', true);

		$.post(ajaxurl, {
			action: 'bkx_bi_delete_all_reports',
			nonce: '<?php echo esc_js( wp_create_nonce( 'bkx_bi_admin' ) ); ?>'
		}, function(response) {
			$btn.prop('disabled', false);
			if (response.success) {
				alert('<?php echo esc_js( __( 'All reports deleted.', 'bkx-business-intelligence' ) ); ?>');
			}
		});
	});
});
</script>
