<?php
/**
 * Text replacements settings template.
 *
 * @package BookingX\WhiteLabel
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WhiteLabel\WhiteLabelAddon::get_instance();
$settings = $addon->get_settings();

$replace_strings = $settings['replace_strings'] ?? array();
$string_replacer = $addon->get_service( 'string_replacer' );
$defaults        = $string_replacer->get_default_replacements();
?>

<div class="bkx-strings-settings">
	<h2><?php esc_html_e( 'Text Replacements', 'bkx-white-label' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Replace any text throughout the plugin. Useful for terminology customization.', 'bkx-white-label' ); ?></p>

	<div class="bkx-strings-suggestions">
		<h3><?php esc_html_e( 'Common Replacements', 'bkx-white-label' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Click to add these common replacements:', 'bkx-white-label' ); ?></p>

		<div class="bkx-suggestion-buttons">
			<?php foreach ( $defaults as $default ) : ?>
				<button type="button" class="button bkx-add-suggestion" data-search="<?php echo esc_attr( $default['search'] ); ?>" data-replace="<?php echo esc_attr( $default['replace'] ); ?>">
					<?php echo esc_html( $default['search'] ); ?> &rarr; <?php echo esc_html( $default['replace'] ); ?>
				</button>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="bkx-strings-list">
		<h3><?php esc_html_e( 'Custom Replacements', 'bkx-white-label' ); ?></h3>

		<table class="widefat bkx-replacements-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Find Text', 'bkx-white-label' ); ?></th>
					<th><?php esc_html_e( 'Replace With', 'bkx-white-label' ); ?></th>
					<th class="bkx-col-actions"><?php esc_html_e( 'Actions', 'bkx-white-label' ); ?></th>
				</tr>
			</thead>
			<tbody id="bkx-replacements-body">
				<?php if ( ! empty( $replace_strings ) ) : ?>
					<?php foreach ( $replace_strings as $index => $item ) : ?>
						<tr class="bkx-replacement-row">
							<td>
								<input type="text" name="replace_strings[<?php echo esc_attr( $index ); ?>][search]" value="<?php echo esc_attr( $item['search'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Original text', 'bkx-white-label' ); ?>">
							</td>
							<td>
								<input type="text" name="replace_strings[<?php echo esc_attr( $index ); ?>][replace]" value="<?php echo esc_attr( $item['replace'] ?? '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Replacement text', 'bkx-white-label' ); ?>">
							</td>
							<td>
								<button type="button" class="button bkx-remove-replacement" title="<?php esc_attr_e( 'Remove', 'bkx-white-label' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr class="bkx-no-replacements">
						<td colspan="3">
							<p><?php esc_html_e( 'No text replacements configured. Add one below.', 'bkx-white-label' ); ?></p>
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<p>
			<button type="button" class="button bkx-add-replacement">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add Replacement', 'bkx-white-label' ); ?>
			</button>
		</p>
	</div>

	<div class="bkx-strings-notes">
		<h3><?php esc_html_e( 'Notes', 'bkx-white-label' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Replacements are case-sensitive.', 'bkx-white-label' ); ?></li>
			<li><?php esc_html_e( 'Brand name replacement (BookingX) is handled automatically in the Branding tab.', 'bkx-white-label' ); ?></li>
			<li><?php esc_html_e( 'Changes apply to admin, frontend, and emails.', 'bkx-white-label' ); ?></li>
			<li><?php esc_html_e( 'Leave replacement empty to remove the text entirely.', 'bkx-white-label' ); ?></li>
		</ul>
	</div>
</div>

<template id="bkx-replacement-row-template">
	<tr class="bkx-replacement-row">
		<td>
			<input type="text" name="replace_strings[{{index}}][search]" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Original text', 'bkx-white-label' ); ?>">
		</td>
		<td>
			<input type="text" name="replace_strings[{{index}}][replace]" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Replacement text', 'bkx-white-label' ); ?>">
		</td>
		<td>
			<button type="button" class="button bkx-remove-replacement" title="<?php esc_attr_e( 'Remove', 'bkx-white-label' ); ?>">
				<span class="dashicons dashicons-trash"></span>
			</button>
		</td>
	</tr>
</template>

<style>
.bkx-suggestion-buttons {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	margin: 15px 0;
}
.bkx-replacements-table {
	margin: 15px 0;
}
.bkx-replacements-table .bkx-col-actions {
	width: 80px;
	text-align: center;
}
.bkx-replacement-row td {
	vertical-align: middle;
}
.bkx-remove-replacement .dashicons {
	color: #d63638;
}
.bkx-no-replacements td {
	text-align: center;
	padding: 30px;
}
.bkx-strings-notes {
	background: #f9f9f9;
	padding: 15px 20px;
	border-radius: 4px;
	margin-top: 30px;
}
.bkx-strings-notes ul {
	margin: 10px 0 0 20px;
}
</style>
