<?php
/**
 * Main White Label Addon class.
 *
 * @package BookingX\WhiteLabel
 */

namespace BookingX\WhiteLabel;

defined( 'ABSPATH' ) || exit;

/**
 * WhiteLabelAddon class.
 */
class WhiteLabelAddon {

	/**
	 * Single instance.
	 *
	 * @var WhiteLabelAddon
	 */
	private static $instance = null;

	/**
	 * Services container.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Get instance.
	 *
	 * @return WhiteLabelAddon
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_settings();
		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Load settings.
	 */
	private function load_settings() {
		$this->settings = get_option( 'bkx_white_label_settings', array() );
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services = array(
			'branding'         => new Services\BrandingManager( $this ),
			'color_scheme'     => new Services\ColorSchemeManager( $this ),
			'email_customizer' => new Services\EmailCustomizer( $this ),
			'string_replacer'  => new Services\StringReplacer( $this ),
			'menu_manager'     => new Services\MenuManager( $this ),
			'login_customizer' => new Services\LoginCustomizer( $this ),
		);
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin menu.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 99 );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Frontend assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ), 999 );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_white_label_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_bkx_white_label_reset_settings', array( $this, 'ajax_reset_settings' ) );
		add_action( 'wp_ajax_bkx_white_label_export_settings', array( $this, 'ajax_export_settings' ) );
		add_action( 'wp_ajax_bkx_white_label_import_settings', array( $this, 'ajax_import_settings' ) );
		add_action( 'wp_ajax_bkx_white_label_preview_email', array( $this, 'ajax_preview_email' ) );

		// BookingX integration.
		add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
		add_action( 'bkx_settings_tab_white_label', array( $this, 'render_settings_tab' ) );

		// Apply white labeling if enabled.
		if ( $this->is_enabled() ) {
			$this->apply_white_labeling();
		}
	}

	/**
	 * Check if white labeling is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->settings['enabled'] );
	}

	/**
	 * Get setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = '' ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		return $this->settings;
	}

	/**
	 * Update settings.
	 *
	 * @param array $settings New settings.
	 */
	public function update_settings( array $settings ) {
		$this->settings = $settings;
		update_option( 'bkx_white_label_settings', $settings );
		delete_transient( 'bkx_white_label_custom_css' );
	}

	/**
	 * Get service.
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get_service( $name ) {
		return isset( $this->services[ $name ] ) ? $this->services[ $name ] : null;
	}

	/**
	 * Apply white labeling.
	 */
	private function apply_white_labeling() {
		// Initialize all services.
		foreach ( $this->services as $service ) {
			if ( method_exists( $service, 'init' ) ) {
				$service->init();
			}
		}
	}

	/**
	 * Add admin menu.
	 *
	 * @return void
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'White Label', 'bkx-white-label' ),
			__( 'White Label', 'bkx-white-label' ),
			'manage_options',
			'bkx-white-label',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'branding';

		include BKX_WHITE_LABEL_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only on our admin pages or BookingX pages.
		if ( strpos( $hook, 'bkx-white-label' ) === false && strpos( $hook, 'bkx_booking' ) === false ) {
			return;
		}

		// WordPress media uploader.
		wp_enqueue_media();

		// Color picker.
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Code editor.
		wp_enqueue_code_editor( array( 'type' => 'text/css' ) );

		wp_enqueue_style(
			'bkx-white-label-admin',
			BKX_WHITE_LABEL_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_WHITE_LABEL_VERSION
		);

		wp_enqueue_script(
			'bkx-white-label-admin',
			BKX_WHITE_LABEL_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			BKX_WHITE_LABEL_VERSION,
			true
		);

		wp_localize_script(
			'bkx-white-label-admin',
			'bkxWhiteLabel',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'bkx_white_label_nonce' ),
				'i18n'      => array(
					'selectImage'   => __( 'Select Image', 'bkx-white-label' ),
					'useImage'      => __( 'Use This Image', 'bkx-white-label' ),
					'saving'        => __( 'Saving...', 'bkx-white-label' ),
					'saved'         => __( 'Settings saved!', 'bkx-white-label' ),
					'error'         => __( 'An error occurred.', 'bkx-white-label' ),
					'confirmReset'  => __( 'Are you sure you want to reset all settings to defaults?', 'bkx-white-label' ),
					'confirmImport' => __( 'This will overwrite your current settings. Continue?', 'bkx-white-label' ),
				),
				'settings'  => $this->settings,
			)
		);

		// Apply admin custom CSS if enabled.
		if ( $this->is_enabled() && ! empty( $this->settings['custom_css_admin'] ) ) {
			wp_add_inline_style( 'bkx-white-label-admin', $this->settings['custom_css_admin'] );
		}
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		// Get cached CSS or generate.
		$custom_css = get_transient( 'bkx_white_label_custom_css' );

		if ( false === $custom_css ) {
			$custom_css = $this->get_service( 'color_scheme' )->generate_css();
			set_transient( 'bkx_white_label_custom_css', $custom_css, HOUR_IN_SECONDS );
		}

		// Add custom frontend CSS.
		$frontend_css = $this->get_setting( 'custom_css_frontend', '' );
		if ( ! empty( $frontend_css ) ) {
			$custom_css .= "\n" . $frontend_css;
		}

		if ( ! empty( $custom_css ) ) {
			wp_register_style( 'bkx-white-label-frontend', false, array(), BKX_WHITE_LABEL_VERSION );
			wp_enqueue_style( 'bkx-white-label-frontend' );
			wp_add_inline_style( 'bkx-white-label-frontend', $custom_css );
		}

		// Custom frontend JS.
		$frontend_js = $this->get_setting( 'custom_js_frontend', '' );
		if ( ! empty( $frontend_js ) ) {
			wp_register_script( 'bkx-white-label-frontend-js', false, array( 'jquery' ), BKX_WHITE_LABEL_VERSION, true );
			wp_enqueue_script( 'bkx-white-label-frontend-js' );
			wp_add_inline_script( 'bkx-white-label-frontend-js', $frontend_js );
		}
	}

	/**
	 * Add settings tab to BookingX.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['white_label'] = __( 'White Label', 'bkx-white-label' );
		return $tabs;
	}

	/**
	 * Render settings tab content.
	 */
	public function render_settings_tab() {
		include BKX_WHITE_LABEL_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}

	/**
	 * AJAX: Save settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'bkx_white_label_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-white-label' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();
		$sanitized = $this->sanitize_settings( $settings );

		$this->update_settings( $sanitized );

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully.', 'bkx-white-label' ) ) );
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $settings Raw settings.
	 * @return array
	 */
	private function sanitize_settings( $settings ) {
		$sanitized = array();

		// Boolean settings.
		$booleans = array(
			'enabled',
			'hide_bookingx_branding',
			'hide_powered_by',
			'hide_plugin_notices',
			'hide_changelog',
		);

		foreach ( $booleans as $key ) {
			$sanitized[ $key ] = ! empty( $settings[ $key ] );
		}

		// Text settings.
		$texts = array(
			'brand_name',
			'brand_url',
			'support_email',
			'support_url',
			'custom_admin_footer',
			'email_from_name',
			'email_footer_text',
		);

		foreach ( $texts as $key ) {
			$sanitized[ $key ] = isset( $settings[ $key ] ) ? sanitize_text_field( $settings[ $key ] ) : '';
		}

		// Email setting.
		$sanitized['email_from_address'] = isset( $settings['email_from_address'] )
			? sanitize_email( $settings['email_from_address'] )
			: '';

		// URL settings.
		$urls = array(
			'brand_logo',
			'brand_logo_dark',
			'brand_icon',
			'email_header_image',
			'login_logo',
			'login_background',
		);

		foreach ( $urls as $key ) {
			$sanitized[ $key ] = isset( $settings[ $key ] ) ? esc_url_raw( $settings[ $key ] ) : '';
		}

		// Color settings.
		$colors = array(
			'primary_color',
			'secondary_color',
			'accent_color',
			'success_color',
			'warning_color',
			'error_color',
			'text_color',
			'background_color',
			'email_background_color',
			'email_body_color',
			'email_text_color',
			'email_link_color',
		);

		foreach ( $colors as $key ) {
			$sanitized[ $key ] = isset( $settings[ $key ] ) ? sanitize_hex_color( $settings[ $key ] ) : '';
		}

		// CSS settings (allow more content).
		$css_fields = array(
			'custom_css_admin',
			'custom_css_frontend',
			'login_custom_css',
		);

		foreach ( $css_fields as $key ) {
			$sanitized[ $key ] = isset( $settings[ $key ] ) ? wp_strip_all_tags( $settings[ $key ] ) : '';
		}

		// JS settings.
		$js_fields = array(
			'custom_js_admin',
			'custom_js_frontend',
		);

		foreach ( $js_fields as $key ) {
			$sanitized[ $key ] = isset( $settings[ $key ] ) ? wp_strip_all_tags( $settings[ $key ] ) : '';
		}

		// Array settings.
		if ( isset( $settings['replace_strings'] ) && is_array( $settings['replace_strings'] ) ) {
			$sanitized['replace_strings'] = array();
			foreach ( $settings['replace_strings'] as $item ) {
				if ( ! empty( $item['search'] ) ) {
					$sanitized['replace_strings'][] = array(
						'search'  => sanitize_text_field( $item['search'] ),
						'replace' => sanitize_text_field( $item['replace'] ?? '' ),
					);
				}
			}
		} else {
			$sanitized['replace_strings'] = array();
		}

		if ( isset( $settings['hide_menu_items'] ) && is_array( $settings['hide_menu_items'] ) ) {
			$sanitized['hide_menu_items'] = array_map( 'sanitize_text_field', $settings['hide_menu_items'] );
		} else {
			$sanitized['hide_menu_items'] = array();
		}

		if ( isset( $settings['custom_menu_order'] ) && is_array( $settings['custom_menu_order'] ) ) {
			$sanitized['custom_menu_order'] = array_map( 'sanitize_text_field', $settings['custom_menu_order'] );
		} else {
			$sanitized['custom_menu_order'] = array();
		}

		return $sanitized;
	}

	/**
	 * AJAX: Reset settings.
	 */
	public function ajax_reset_settings() {
		check_ajax_referer( 'bkx_white_label_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-white-label' ) ) );
		}

		delete_option( 'bkx_white_label_settings' );
		bkx_white_label_activate();

		wp_send_json_success(
			array(
				'message'  => __( 'Settings reset to defaults.', 'bkx-white-label' ),
				'settings' => get_option( 'bkx_white_label_settings' ),
			)
		);
	}

	/**
	 * AJAX: Export settings.
	 */
	public function ajax_export_settings() {
		check_ajax_referer( 'bkx_white_label_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-white-label' ) ) );
		}

		$export = array(
			'version'    => BKX_WHITE_LABEL_VERSION,
			'exported'   => current_time( 'mysql' ),
			'site_url'   => get_site_url(),
			'settings'   => $this->settings,
		);

		wp_send_json_success(
			array(
				'data'     => $export,
				'filename' => 'bkx-white-label-' . gmdate( 'Y-m-d' ) . '.json',
			)
		);
	}

	/**
	 * AJAX: Import settings.
	 */
	public function ajax_import_settings() {
		check_ajax_referer( 'bkx_white_label_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-white-label' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$import_data = isset( $_POST['import_data'] ) ? wp_unslash( $_POST['import_data'] ) : '';

		if ( empty( $import_data ) ) {
			wp_send_json_error( array( 'message' => __( 'No import data provided.', 'bkx-white-label' ) ) );
		}

		$data = json_decode( $import_data, true );

		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['settings'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid import file format.', 'bkx-white-label' ) ) );
		}

		$sanitized = $this->sanitize_settings( $data['settings'] );
		$this->update_settings( $sanitized );

		wp_send_json_success(
			array(
				'message'  => __( 'Settings imported successfully.', 'bkx-white-label' ),
				'settings' => $sanitized,
			)
		);
	}

	/**
	 * AJAX: Preview email.
	 */
	public function ajax_preview_email() {
		check_ajax_referer( 'bkx_white_label_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-white-label' ) ) );
		}

		$html = $this->get_service( 'email_customizer' )->get_preview_html();

		wp_send_json_success( array( 'html' => $html ) );
	}
}
