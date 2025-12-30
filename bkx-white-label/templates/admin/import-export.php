<?php
/**
 * Import/Export settings template.
 *
 * @package BookingX\WhiteLabel
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WhiteLabel\WhiteLabelAddon::get_instance();
$settings = $addon->get_settings();
?>

<div class="bkx-import-export-settings">
	<div class="bkx-ie-grid">
		<div class="bkx-ie-section">
			<h2>
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export Settings', 'bkx-white-label' ); ?>
			</h2>
			<p class="description"><?php esc_html_e( 'Download all white label settings as a JSON file. Use this to backup your configuration or transfer settings to another site.', 'bkx-white-label' ); ?></p>

			<div class="bkx-export-info">
				<h4><?php esc_html_e( 'Export includes:', 'bkx-white-label' ); ?></h4>
				<ul>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Branding settings', 'bkx-white-label' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Color scheme', 'bkx-white-label' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Email customization', 'bkx-white-label' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Text replacements', 'bkx-white-label' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Menu settings', 'bkx-white-label' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Login page customization', 'bkx-white-label' ); ?></li>
					<li><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Custom CSS/JS code', 'bkx-white-label' ); ?></li>
				</ul>

				<p class="bkx-note">
					<span class="dashicons dashicons-info"></span>
					<?php esc_html_e( 'Note: Image URLs are exported but actual images are not included. Ensure images are accessible on the target site.', 'bkx-white-label' ); ?>
				</p>
			</div>

			<p>
				<button type="button" class="button button-primary bkx-export-settings">
					<span class="dashicons dashicons-download"></span>
					<?php esc_html_e( 'Export Settings', 'bkx-white-label' ); ?>
				</button>
			</p>
		</div>

		<div class="bkx-ie-section">
			<h2>
				<span class="dashicons dashicons-upload"></span>
				<?php esc_html_e( 'Import Settings', 'bkx-white-label' ); ?>
			</h2>
			<p class="description"><?php esc_html_e( 'Upload a previously exported JSON file to restore settings.', 'bkx-white-label' ); ?></p>

			<div class="bkx-import-area">
				<div class="bkx-import-dropzone" id="bkx-import-dropzone">
					<span class="dashicons dashicons-upload"></span>
					<p><?php esc_html_e( 'Drag and drop a JSON file here', 'bkx-white-label' ); ?></p>
					<p class="bkx-or"><?php esc_html_e( 'or', 'bkx-white-label' ); ?></p>
					<input type="file" id="bkx-import-file" accept=".json" style="display: none;">
					<button type="button" class="button" id="bkx-select-import-file">
						<?php esc_html_e( 'Select File', 'bkx-white-label' ); ?>
					</button>
				</div>

				<div class="bkx-import-preview" id="bkx-import-preview" style="display: none;">
					<h4><?php esc_html_e( 'Import Preview', 'bkx-white-label' ); ?></h4>
					<div class="bkx-import-details">
						<p><strong><?php esc_html_e( 'File:', 'bkx-white-label' ); ?></strong> <span id="bkx-import-filename"></span></p>
						<p><strong><?php esc_html_e( 'Version:', 'bkx-white-label' ); ?></strong> <span id="bkx-import-version"></span></p>
						<p><strong><?php esc_html_e( 'Exported:', 'bkx-white-label' ); ?></strong> <span id="bkx-import-date"></span></p>
						<p><strong><?php esc_html_e( 'Source Site:', 'bkx-white-label' ); ?></strong> <span id="bkx-import-site"></span></p>
					</div>

					<div class="bkx-import-warning">
						<p><strong><?php esc_html_e( 'Warning:', 'bkx-white-label' ); ?></strong> <?php esc_html_e( 'Importing will overwrite all current white label settings.', 'bkx-white-label' ); ?></p>
					</div>

					<p>
						<button type="button" class="button button-primary bkx-confirm-import">
							<span class="dashicons dashicons-yes"></span>
							<?php esc_html_e( 'Import Settings', 'bkx-white-label' ); ?>
						</button>
						<button type="button" class="button bkx-cancel-import">
							<?php esc_html_e( 'Cancel', 'bkx-white-label' ); ?>
						</button>
					</p>
				</div>
			</div>
		</div>
	</div>

	<div class="bkx-ie-section bkx-reset-section">
		<h2>
			<span class="dashicons dashicons-image-rotate"></span>
			<?php esc_html_e( 'Reset Settings', 'bkx-white-label' ); ?>
		</h2>
		<p class="description"><?php esc_html_e( 'Reset all white label settings to their default values.', 'bkx-white-label' ); ?></p>

		<div class="bkx-reset-warning">
			<p><strong><?php esc_html_e( 'Warning:', 'bkx-white-label' ); ?></strong> <?php esc_html_e( 'This action cannot be undone. Consider exporting your settings first.', 'bkx-white-label' ); ?></p>
		</div>

		<p>
			<button type="button" class="button bkx-reset-settings">
				<span class="dashicons dashicons-image-rotate"></span>
				<?php esc_html_e( 'Reset All Settings', 'bkx-white-label' ); ?>
			</button>
		</p>
	</div>
</div>

<style>
.bkx-ie-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 30px;
	margin-bottom: 30px;
}
.bkx-ie-section {
	background: #fff;
	border: 1px solid #ddd;
	border-radius: 4px;
	padding: 20px;
}
.bkx-ie-section h2 {
	display: flex;
	align-items: center;
	gap: 10px;
	margin-top: 0;
}
.bkx-export-info ul {
	list-style: none;
	margin: 15px 0;
	padding: 0;
}
.bkx-export-info li {
	display: flex;
	align-items: center;
	gap: 8px;
	margin-bottom: 8px;
}
.bkx-export-info .dashicons-yes {
	color: #00a32a;
}
.bkx-note {
	background: #f0f6fc;
	padding: 10px 15px;
	border-radius: 4px;
	display: flex;
	align-items: flex-start;
	gap: 10px;
}
.bkx-note .dashicons {
	color: #2271b1;
	margin-top: 2px;
}
.bkx-import-dropzone {
	border: 2px dashed #ddd;
	border-radius: 8px;
	padding: 40px;
	text-align: center;
	background: #f9f9f9;
	transition: all 0.3s ease;
}
.bkx-import-dropzone.dragover {
	border-color: #2271b1;
	background: #f0f6fc;
}
.bkx-import-dropzone .dashicons {
	font-size: 48px;
	width: 48px;
	height: 48px;
	color: #ddd;
}
.bkx-import-dropzone.dragover .dashicons {
	color: #2271b1;
}
.bkx-or {
	color: #666;
	margin: 10px 0;
}
.bkx-import-preview {
	margin-top: 20px;
	padding: 15px;
	background: #f9f9f9;
	border-radius: 4px;
}
.bkx-import-preview h4 {
	margin-top: 0;
}
.bkx-import-details p {
	margin: 5px 0;
}
.bkx-import-warning,
.bkx-reset-warning {
	background: #fff3cd;
	border-left: 4px solid #dba617;
	padding: 10px 15px;
	margin: 15px 0;
}
.bkx-reset-section {
	border-color: #d63638;
}
.bkx-reset-section h2 .dashicons {
	color: #d63638;
}
@media screen and (max-width: 1024px) {
	.bkx-ie-grid {
		grid-template-columns: 1fr;
	}
}
</style>
