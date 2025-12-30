<?php
/**
 * Import data template.
 *
 * @package BookingX\BackupRecovery
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="bkx-import-section">
	<div class="bkx-section-header">
		<h2><?php esc_html_e( 'Import Data', 'bkx-backup-recovery' ); ?></h2>
	</div>

	<div class="bkx-info-box">
		<span class="dashicons dashicons-info"></span>
		<?php esc_html_e( 'Import data from CSV, JSON, or XML files. Make sure your file format matches the expected structure.', 'bkx-backup-recovery' ); ?>
	</div>

	<div class="bkx-warning-box">
		<span class="dashicons dashicons-warning"></span>
		<p>
			<strong><?php esc_html_e( 'Important:', 'bkx-backup-recovery' ); ?></strong>
			<?php esc_html_e( 'Importing data may overwrite existing records. We strongly recommend creating a backup before proceeding.', 'bkx-backup-recovery' ); ?>
		</p>
	</div>

	<form id="bkx-import-form" class="bkx-form" enctype="multipart/form-data">
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="import_type"><?php esc_html_e( 'Data Type', 'bkx-backup-recovery' ); ?></label>
				</th>
				<td>
					<select name="import_type" id="import_type">
						<option value="bookings"><?php esc_html_e( 'Bookings', 'bkx-backup-recovery' ); ?></option>
						<option value="services"><?php esc_html_e( 'Services', 'bkx-backup-recovery' ); ?></option>
						<option value="staff"><?php esc_html_e( 'Staff', 'bkx-backup-recovery' ); ?></option>
						<option value="customers"><?php esc_html_e( 'Customers', 'bkx-backup-recovery' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Select the type of data you are importing.', 'bkx-backup-recovery' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="import_file"><?php esc_html_e( 'Import File', 'bkx-backup-recovery' ); ?></label>
				</th>
				<td>
					<input type="file" name="import_file" id="import_file" accept=".csv,.json,.xml">
					<p class="description">
						<?php esc_html_e( 'Supported formats: CSV, JSON, XML. Maximum file size: 50MB.', 'bkx-backup-recovery' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Import Options', 'bkx-backup-recovery' ); ?></th>
				<td>
					<fieldset>
						<label>
							<input type="checkbox" name="update_existing" value="1">
							<?php esc_html_e( 'Update existing records (match by email/title)', 'bkx-backup-recovery' ); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="skip_duplicates" value="1" checked>
							<?php esc_html_e( 'Skip duplicate records', 'bkx-backup-recovery' ); ?>
						</label>
						<br>
						<label>
							<input type="checkbox" name="dry_run" value="1">
							<?php esc_html_e( 'Dry run (validate only, do not import)', 'bkx-backup-recovery' ); ?>
						</label>
					</fieldset>
				</td>
			</tr>
		</table>

		<?php wp_nonce_field( 'bkx_import_action', 'bkx_import_nonce' ); ?>

		<p class="submit">
			<button type="submit" class="button button-primary" id="bkx-import-btn">
				<span class="dashicons dashicons-upload"></span>
				<?php esc_html_e( 'Import Data', 'bkx-backup-recovery' ); ?>
			</button>
		</p>
	</form>

	<div class="bkx-import-result" id="bkx-import-result" style="display: none;">
		<h3><?php esc_html_e( 'Import Results', 'bkx-backup-recovery' ); ?></h3>
		<div class="bkx-result-summary"></div>
	</div>

	<hr>

	<div class="bkx-import-templates">
		<h3><?php esc_html_e( 'Download Import Templates', 'bkx-backup-recovery' ); ?></h3>
		<p><?php esc_html_e( 'Use these templates to ensure your import file has the correct format:', 'bkx-backup-recovery' ); ?></p>

		<div class="bkx-template-buttons">
			<div class="bkx-template-group">
				<strong><?php esc_html_e( 'Bookings:', 'bkx-backup-recovery' ); ?></strong>
				<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=bkx_download_template&type=bookings&format=csv&nonce=' . wp_create_nonce( 'bkx_template' ) ) ); ?>" class="button button-small">CSV</a>
				<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=bkx_download_template&type=bookings&format=json&nonce=' . wp_create_nonce( 'bkx_template' ) ) ); ?>" class="button button-small">JSON</a>
			</div>
			<div class="bkx-template-group">
				<strong><?php esc_html_e( 'Services:', 'bkx-backup-recovery' ); ?></strong>
				<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=bkx_download_template&type=services&format=csv&nonce=' . wp_create_nonce( 'bkx_template' ) ) ); ?>" class="button button-small">CSV</a>
				<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=bkx_download_template&type=services&format=json&nonce=' . wp_create_nonce( 'bkx_template' ) ) ); ?>" class="button button-small">JSON</a>
			</div>
			<div class="bkx-template-group">
				<strong><?php esc_html_e( 'Staff:', 'bkx-backup-recovery' ); ?></strong>
				<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=bkx_download_template&type=staff&format=csv&nonce=' . wp_create_nonce( 'bkx_template' ) ) ); ?>" class="button button-small">CSV</a>
				<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=bkx_download_template&type=staff&format=json&nonce=' . wp_create_nonce( 'bkx_template' ) ) ); ?>" class="button button-small">JSON</a>
			</div>
			<div class="bkx-template-group">
				<strong><?php esc_html_e( 'Customers:', 'bkx-backup-recovery' ); ?></strong>
				<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=bkx_download_template&type=customers&format=csv&nonce=' . wp_create_nonce( 'bkx_template' ) ) ); ?>" class="button button-small">CSV</a>
				<a href="<?php echo esc_url( admin_url( 'admin-ajax.php?action=bkx_download_template&type=customers&format=json&nonce=' . wp_create_nonce( 'bkx_template' ) ) ); ?>" class="button button-small">JSON</a>
			</div>
		</div>
	</div>
</div>
