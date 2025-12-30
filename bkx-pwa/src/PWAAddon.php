<?php
/**
 * Main PWA Addon Class.
 *
 * @package BookingX\PWA
 */

namespace BookingX\PWA;

defined( 'ABSPATH' ) || exit;

/**
 * PWAAddon class.
 */
class PWAAddon {

	/**
	 * Singleton instance.
	 *
	 * @var PWAAddon
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
	 * Get singleton instance.
	 *
	 * @return PWAAddon
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
		$this->settings = get_option( 'bkx_pwa_settings', array() );
		$this->init_services();
		$this->init_hooks();
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		$this->services['manifest']        = new Services\ManifestService();
		$this->services['service_worker']  = new Services\ServiceWorkerService();
		$this->services['cache_manager']   = new Services\CacheManager();
		$this->services['offline_handler'] = new Services\OfflineHandler();
		$this->services['install_prompt']  = new Services\InstallPromptService();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Admin hooks.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
			add_filter( 'bkx_settings_tabs', array( $this, 'add_settings_tab' ) );
			add_action( 'bkx_settings_tab_pwa', array( $this, 'render_settings_tab' ) );
		}

		// Frontend hooks.
		if ( ! is_admin() && $this->is_enabled() ) {
			add_action( 'wp_head', array( $this, 'add_pwa_meta_tags' ), 1 );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
			add_action( 'wp_footer', array( $this, 'render_install_prompt' ) );
		}

		// REST API for manifest and service worker.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Rewrite rules for manifest.json and sw.js.
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_pwa_requests' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bkx_pwa_save_offline_booking', array( $this, 'ajax_save_offline_booking' ) );
		add_action( 'wp_ajax_nopriv_bkx_pwa_save_offline_booking', array( $this, 'ajax_save_offline_booking' ) );
		add_action( 'wp_ajax_bkx_pwa_sync_offline_bookings', array( $this, 'ajax_sync_offline_bookings' ) );
	}

	/**
	 * Check if PWA is enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->settings['enabled'] );
	}

	/**
	 * Get service.
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get_service( $name ) {
		return $this->services[ $name ] ?? null;
	}

	/**
	 * Get setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		return $this->settings[ $key ] ?? $default;
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bookingx',
			__( 'Progressive Web App', 'bkx-pwa' ),
			__( 'PWA', 'bkx-pwa' ),
			'manage_options',
			'bkx-pwa',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['pwa'] = __( 'PWA', 'bkx-pwa' );
		return $tabs;
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'bkx-pwa' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-pwa-admin',
			BKX_PWA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			BKX_PWA_VERSION
		);

		wp_enqueue_script(
			'bkx-pwa-admin',
			BKX_PWA_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-color-picker' ),
			BKX_PWA_VERSION,
			true
		);

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_media();

		wp_localize_script(
			'bkx-pwa-admin',
			'bkxPwaAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bkx_pwa_admin' ),
				'i18n'    => array(
					'selectIcon'  => __( 'Select Icon', 'bkx-pwa' ),
					'useThisIcon' => __( 'Use this icon', 'bkx-pwa' ),
				),
			)
		);
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_frontend_assets() {
		wp_enqueue_script(
			'bkx-pwa-app',
			BKX_PWA_PLUGIN_URL . 'assets/js/pwa-app.js',
			array(),
			BKX_PWA_VERSION,
			true
		);

		wp_localize_script(
			'bkx-pwa-app',
			'bkxPwa',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'swUrl'          => home_url( '/bkx-sw.js' ),
				'offlineUrl'     => $this->get_setting( 'offline_page' ) ?: home_url( '/offline/' ),
				'cacheStrategy'  => $this->get_setting( 'cache_strategy', 'network-first' ),
				'offlineBookings' => $this->get_setting( 'offline_bookings', true ),
				'installPrompt'  => $this->get_setting( 'install_prompt', true ),
				'promptDelay'    => $this->get_setting( 'install_prompt_delay', 30 ) * 1000,
				'nonce'          => wp_create_nonce( 'bkx_pwa_frontend' ),
			)
		);

		// Offline booking styles.
		if ( $this->get_setting( 'offline_bookings' ) ) {
			wp_enqueue_style(
				'bkx-pwa-offline',
				BKX_PWA_PLUGIN_URL . 'assets/css/offline.css',
				array(),
				BKX_PWA_VERSION
			);
		}
	}

	/**
	 * Add PWA meta tags to head.
	 */
	public function add_pwa_meta_tags() {
		$manifest_url = home_url( '/manifest.json' );
		$theme_color  = $this->get_setting( 'theme_color', '#2563eb' );
		$app_name     = $this->get_setting( 'app_name', get_bloginfo( 'name' ) );

		echo '<link rel="manifest" href="' . esc_url( $manifest_url ) . '">' . "\n";
		echo '<meta name="theme-color" content="' . esc_attr( $theme_color ) . '">' . "\n";
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="default">' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr( $app_name ) . '">' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="application-name" content="' . esc_attr( $app_name ) . '">' . "\n";

		// Apple touch icons.
		$icon_192 = $this->get_setting( 'icon_192' );
		if ( $icon_192 ) {
			echo '<link rel="apple-touch-icon" href="' . esc_url( $icon_192 ) . '">' . "\n";
		}

		// iOS splash screens.
		if ( $this->get_setting( 'ios_splash_screens' ) ) {
			$this->render_ios_splash_links();
		}
	}

	/**
	 * Render iOS splash screen links.
	 */
	private function render_ios_splash_links() {
		$splash = $this->get_setting( 'splash_screen' );
		if ( ! $splash ) {
			return;
		}

		// Common iOS device splash screen sizes.
		$sizes = array(
			'2048x2732' => '(device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2)',
			'1668x2388' => '(device-width: 834px) and (device-height: 1194px) and (-webkit-device-pixel-ratio: 2)',
			'1536x2048' => '(device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2)',
			'1125x2436' => '(device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3)',
			'1242x2688' => '(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3)',
			'828x1792'  => '(device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2)',
			'750x1334'  => '(device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2)',
			'640x1136'  => '(device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2)',
		);

		foreach ( $sizes as $size => $media ) {
			echo '<link rel="apple-touch-startup-image" href="' . esc_url( $splash ) . '" media="' . esc_attr( $media ) . '">' . "\n";
		}
	}

	/**
	 * Add rewrite rules.
	 */
	public function add_rewrite_rules() {
		add_rewrite_rule( '^manifest\.json$', 'index.php?bkx_pwa=manifest', 'top' );
		add_rewrite_rule( '^bkx-sw\.js$', 'index.php?bkx_pwa=sw', 'top' );
		add_rewrite_rule( '^offline/?$', 'index.php?bkx_pwa=offline', 'top' );
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'bkx_pwa';
		return $vars;
	}

	/**
	 * Handle PWA requests.
	 */
	public function handle_pwa_requests() {
		$pwa_request = get_query_var( 'bkx_pwa' );

		if ( ! $pwa_request ) {
			return;
		}

		switch ( $pwa_request ) {
			case 'manifest':
				$this->services['manifest']->serve_manifest();
				break;

			case 'sw':
				$this->services['service_worker']->serve_service_worker();
				break;

			case 'offline':
				$this->services['offline_handler']->render_offline_page();
				break;
		}
		exit;
	}

	/**
	 * Register REST routes.
	 */
	public function register_rest_routes() {
		register_rest_route(
			'bkx-pwa/v1',
			'/manifest',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->services['manifest'], 'get_manifest' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'bkx-pwa/v1',
			'/cache-urls',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this->services['cache_manager'], 'get_cache_urls' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'bkx-pwa/v1',
			'/sync-booking',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->services['offline_handler'], 'sync_booking' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Render install prompt.
	 */
	public function render_install_prompt() {
		if ( ! $this->get_setting( 'install_prompt' ) ) {
			return;
		}

		include BKX_PWA_PLUGIN_DIR . 'templates/install-prompt.php';
	}

	/**
	 * Render admin page.
	 */
	public function render_admin_page() {
		include BKX_PWA_PLUGIN_DIR . 'templates/admin/page.php';
	}

	/**
	 * Render settings tab.
	 */
	public function render_settings_tab() {
		include BKX_PWA_PLUGIN_DIR . 'templates/admin/settings-tab.php';
	}

	/**
	 * AJAX: Save offline booking.
	 */
	public function ajax_save_offline_booking() {
		check_ajax_referer( 'bkx_pwa_frontend', 'nonce' );

		$booking_data = json_decode( file_get_contents( 'php://input' ), true );

		if ( empty( $booking_data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid booking data.', 'bkx-pwa' ) ) );
		}

		$result = $this->services['offline_handler']->save_offline_booking( $booking_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'booking_id' => $result ) );
	}

	/**
	 * AJAX: Sync offline bookings.
	 */
	public function ajax_sync_offline_bookings() {
		check_ajax_referer( 'bkx_pwa_frontend', 'nonce' );

		$bookings = json_decode( file_get_contents( 'php://input' ), true );

		if ( empty( $bookings ) || ! is_array( $bookings ) ) {
			wp_send_json_error( array( 'message' => __( 'No bookings to sync.', 'bkx-pwa' ) ) );
		}

		$results = $this->services['offline_handler']->sync_offline_bookings( $bookings );

		wp_send_json_success( $results );
	}
}
