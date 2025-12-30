<?php
/**
 * Main admin page template.
 *
 * @package BookingX\WhiteLabel
 */

defined( 'ABSPATH' ) || exit;

$addon    = \BookingX\WhiteLabel\WhiteLabelAddon::get_instance();
$settings = $addon->get_settings();

$tabs = array(
	'branding'     => __( 'Branding', 'bkx-white-label' ),
	'colors'       => __( 'Colors', 'bkx-white-label' ),
	'emails'       => __( 'Emails', 'bkx-white-label' ),
	'strings'      => __( 'Text Replacements', 'bkx-white-label' ),
	'menus'        => __( 'Menu Management', 'bkx-white-label' ),
	'login'        => __( 'Login Page', 'bkx-white-label' ),
	'custom-code'  => __( 'Custom Code', 'bkx-white-label' ),
	'import-export' => __( 'Import/Export', 'bkx-white-label' ),
);
?>
<div class="wrap bkx-white-label-page">
	<h1 class="wp-heading-inline">
		<span class="dashicons dashicons-admin-customizer"></span>
		<?php esc_html_e( 'White Label Solution', 'bkx-white-label' ); ?>
	</h1>

	<div class="bkx-white-label-header">
		<div class="bkx-enable-toggle">
			<label for="bkx-wl-enabled">
				<input type="checkbox" id="bkx-wl-enabled" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
				<strong><?php esc_html_e( 'Enable White Labeling', 'bkx-white-label' ); ?></strong>
			</label>
			<p class="description"><?php esc_html_e( 'Turn on to apply all white label customizations.', 'bkx-white-label' ); ?></p>
		</div>
	</div>

	<nav class="nav-tab-wrapper bkx-nav-tabs">
		<?php foreach ( $tabs as $tab_id => $tab_label ) : ?>
			<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id ) ); ?>" class="nav-tab <?php echo $tab === $tab_id ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html( $tab_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="bkx-tab-content">
		<?php
		$template_file = BKX_WHITE_LABEL_PLUGIN_DIR . 'templates/admin/' . $tab . '.php';
		if ( file_exists( $template_file ) ) {
			include $template_file;
		} else {
			include BKX_WHITE_LABEL_PLUGIN_DIR . 'templates/admin/branding.php';
		}
		?>
	</div>

	<div class="bkx-save-bar">
		<button type="button" class="button button-primary bkx-save-settings">
			<span class="dashicons dashicons-yes"></span>
			<?php esc_html_e( 'Save All Settings', 'bkx-white-label' ); ?>
		</button>
		<span class="bkx-save-status"></span>
	</div>
</div>
