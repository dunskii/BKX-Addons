<?php
/**
 * Menu management settings template.
 *
 * @package BookingX\WhiteLabel
 */

defined( 'ABSPATH' ) || exit;

$addon        = \BookingX\WhiteLabel\WhiteLabelAddon::get_instance();
$settings     = $addon->get_settings();
$menu_manager = $addon->get_service( 'menu_manager' );

$bkx_menu_items = $menu_manager->get_bookingx_menu_items();
$hidden_items   = $settings['hide_menu_items'] ?? array();
?>

<div class="bkx-menus-settings">
	<h2><?php esc_html_e( 'Menu Management', 'bkx-white-label' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Control which menu items are visible to users.', 'bkx-white-label' ); ?></p>

	<div class="bkx-menu-sections">
		<div class="bkx-menu-section">
			<h3><?php esc_html_e( 'BookingX Menu Items', 'bkx-white-label' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Hide or show BookingX-related menu items.', 'bkx-white-label' ); ?></p>

			<table class="widefat bkx-menu-table">
				<thead>
					<tr>
						<th class="bkx-col-check"><?php esc_html_e( 'Visible', 'bkx-white-label' ); ?></th>
						<th><?php esc_html_e( 'Menu Item', 'bkx-white-label' ); ?></th>
						<th><?php esc_html_e( 'Menu Slug', 'bkx-white-label' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $bkx_menu_items as $item ) : ?>
						<?php $is_hidden = in_array( $item['slug'], $hidden_items, true ); ?>
						<tr>
							<td>
								<input type="checkbox" name="visible_menus[]" value="<?php echo esc_attr( $item['slug'] ); ?>" <?php checked( ! $is_hidden ); ?> class="bkx-menu-visibility">
							</td>
							<td><?php echo esc_html( $item['label'] ); ?></td>
							<td><code><?php echo esc_html( $item['slug'] ); ?></code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="bkx-menu-section">
			<h3><?php esc_html_e( 'Additional Menu Items to Hide', 'bkx-white-label' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Add custom menu slugs to hide from the admin.', 'bkx-white-label' ); ?></p>

			<div class="bkx-custom-hide-list" id="bkx-custom-hide-list">
				<?php
				// Get custom hidden items (not in the predefined list).
				$predefined_slugs = array_column( $bkx_menu_items, 'slug' );
				$custom_hidden    = array_diff( $hidden_items, $predefined_slugs );
				?>

				<?php if ( ! empty( $custom_hidden ) ) : ?>
					<?php foreach ( $custom_hidden as $slug ) : ?>
						<div class="bkx-custom-hide-item">
							<input type="text" value="<?php echo esc_attr( $slug ); ?>" class="regular-text" readonly>
							<button type="button" class="button bkx-remove-custom-hide">
								<span class="dashicons dashicons-no-alt"></span>
							</button>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<div class="bkx-add-custom-hide">
				<input type="text" id="bkx-new-hide-slug" placeholder="<?php esc_attr_e( 'Enter menu slug', 'bkx-white-label' ); ?>" class="regular-text">
				<button type="button" class="button" id="bkx-add-hide-slug">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add', 'bkx-white-label' ); ?>
				</button>
			</div>
		</div>
	</div>

	<div class="bkx-menu-info">
		<h3><?php esc_html_e( 'Finding Menu Slugs', 'bkx-white-label' ); ?></h3>
		<p><?php esc_html_e( 'To find a menu slug:', 'bkx-white-label' ); ?></p>
		<ol>
			<li><?php esc_html_e( 'Hover over the menu item you want to hide.', 'bkx-white-label' ); ?></li>
			<li><?php esc_html_e( 'Look at the URL in your browser status bar.', 'bkx-white-label' ); ?></li>
			<li><?php esc_html_e( 'The slug is typically the "page=" parameter value.', 'bkx-white-label' ); ?></li>
		</ol>
		<p><strong><?php esc_html_e( 'Examples:', 'bkx-white-label' ); ?></strong></p>
		<ul>
			<li><code>options-general.php</code> - <?php esc_html_e( 'Settings menu', 'bkx-white-label' ); ?></li>
			<li><code>tools.php</code> - <?php esc_html_e( 'Tools menu', 'bkx-white-label' ); ?></li>
			<li><code>edit.php?post_type=page</code> - <?php esc_html_e( 'Pages menu', 'bkx-white-label' ); ?></li>
		</ul>
	</div>
</div>

<style>
.bkx-menu-sections {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 30px;
	margin: 20px 0;
}
.bkx-menu-table {
	margin: 15px 0;
}
.bkx-menu-table .bkx-col-check {
	width: 80px;
	text-align: center;
}
.bkx-custom-hide-item {
	display: flex;
	gap: 10px;
	margin-bottom: 10px;
}
.bkx-add-custom-hide {
	display: flex;
	gap: 10px;
	margin-top: 15px;
}
.bkx-menu-info {
	background: #f9f9f9;
	padding: 15px 20px;
	border-radius: 4px;
	margin-top: 30px;
}
.bkx-menu-info ol,
.bkx-menu-info ul {
	margin-left: 20px;
}
@media screen and (max-width: 1024px) {
	.bkx-menu-sections {
		grid-template-columns: 1fr;
	}
}
</style>
