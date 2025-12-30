<?php
/**
 * Export data template.
 *
 * @package BookingX\BackupRecovery
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="bkx-export-section">
	<div class="bkx-section-header">
		<h2><?php esc_html_e( 'Export Data', 'bkx-backup-recovery' ); ?></h2>
	</div>

	<div class="bkx-info-box">
		<span class="dashicons dashicons-info"></span>
		<?php esc_html_e( 'Export your BookingX data to CSV, JSON, or XML format. Exports can be used for reporting, migration, or external analysis.', 'bkx-backup-recovery' ); ?>
	</div>

	<form id="bkx-export-form" class="bkx-form">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="export_type"><?php esc_html_e( 'Data Type', 'bkx-backup-recovery' ); ?></label>
				</th>
				<td>
					<select name="export_type" id="export_type">
						<option value="all"><?php esc_html_e( 'All Data', 'bkx-backup-recovery' ); ?></option>
						<option value="bookings"><?php esc_html_e( 'Bookings', 'bkx-backup-recovery' ); ?></option>
						<option value="customers"><?php esc_html_e( 'Customers', 'bkx-backup-recovery' ); ?></option>
						<option value="services"><?php esc_html_e( 'Services', 'bkx-backup-recovery' ); ?></option>
						<option value="staff"><?php esc_html_e( 'Staff', 'bkx-backup-recovery' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Select the type of data to export.', 'bkx-backup-recovery' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="export_format"><?php esc_html_e( 'Export Format', 'bkx-backup-recovery' ); ?></label>
				</th>
				<td>
					<select name="export_format" id="export_format">
						<option value="csv"><?php esc_html_e( 'CSV (Comma Separated Values)', 'bkx-backup-recovery' ); ?></option>
						<option value="json"><?php esc_html_e( 'JSON (JavaScript Object Notation)', 'bkx-backup-recovery' ); ?></option>
						<option value="xml"><?php esc_html_e( 'XML (Extensible Markup Language)', 'bkx-backup-recovery' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'CSV works best with spreadsheet applications. JSON is ideal for developers. XML provides structured data.', 'bkx-backup-recovery' ); ?>
					</p>
				</td>
			</tr>
		</table>

		<div class="bkx-export-options" id="bkx-booking-export-options" style="display: none;">
			<h3><?php esc_html_e( 'Booking Export Options', 'bkx-backup-recovery' ); ?></h3>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="date_from"><?php esc_html_e( 'Date From', 'bkx-backup-recovery' ); ?></label>
					</th>
					<td>
						<input type="date" name="date_from" id="date_from">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="date_to"><?php esc_html_e( 'Date To', 'bkx-backup-recovery' ); ?></label>
					</th>
					<td>
						<input type="date" name="date_to" id="date_to">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="booking_status"><?php esc_html_e( 'Booking Status', 'bkx-backup-recovery' ); ?></label>
					</th>
					<td>
						<select name="booking_status" id="booking_status">
							<option value=""><?php esc_html_e( 'All Statuses', 'bkx-backup-recovery' ); ?></option>
							<option value="bkx-pending"><?php esc_html_e( 'Pending', 'bkx-backup-recovery' ); ?></option>
							<option value="bkx-ack"><?php esc_html_e( 'Acknowledged', 'bkx-backup-recovery' ); ?></option>
							<option value="bkx-completed"><?php esc_html_e( 'Completed', 'bkx-backup-recovery' ); ?></option>
							<option value="bkx-cancelled"><?php esc_html_e( 'Cancelled', 'bkx-backup-recovery' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<?php wp_nonce_field( 'bkx_export_action', 'bkx_export_nonce' ); ?>

		<p class="submit">
			<button type="submit" class="button button-primary" id="bkx-export-btn">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export Data', 'bkx-backup-recovery' ); ?>
			</button>
		</p>
	</form>

	<div class="bkx-export-result" id="bkx-export-result" style="display: none;">
		<div class="bkx-success-box">
			<span class="dashicons dashicons-yes-alt"></span>
			<div>
				<strong><?php esc_html_e( 'Export Complete!', 'bkx-backup-recovery' ); ?></strong>
				<p><?php esc_html_e( 'Your export file is ready for download.', 'bkx-backup-recovery' ); ?></p>
				<a href="#" id="bkx-export-download" class="button button-primary" download>
					<?php esc_html_e( 'Download File', 'bkx-backup-recovery' ); ?>
				</a>
			</div>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Show/hide booking options based on export type.
	$('#export_type').on('change', function() {
		if ($(this).val() === 'bookings') {
			$('#bkx-booking-export-options').show();
		} else {
			$('#bkx-booking-export-options').hide();
		}
	});
});
</script>
