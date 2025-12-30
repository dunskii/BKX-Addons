<?php
/**
 * Branding Manager Service.
 *
 * Handles custom branding, logo replacement, and BookingX branding removal.
 *
 * @package BookingX\WhiteLabel
 */

namespace BookingX\WhiteLabel\Services;

defined( 'ABSPATH' ) || exit;

/**
 * BrandingManager class.
 */
class BrandingManager {

	/**
	 * Addon instance.
	 *
	 * @var \BookingX\WhiteLabel\WhiteLabelAddon
	 */
	private $addon;

	/**
	 * Constructor.
	 *
	 * @param \BookingX\WhiteLabel\WhiteLabelAddon $addon Addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Initialize branding hooks.
	 */
	public function init() {
		// Replace admin logo/header.
		add_action( 'admin_head', array( $this, 'add_admin_branding_css' ) );

		// Hide BookingX branding elements.
		if ( $this->addon->get_setting( 'hide_bookingx_branding', true ) ) {
			add_filter( 'bkx_show_branding', '__return_false' );
			add_action( 'admin_head', array( $this, 'hide_branding_css' ) );
		}

		// Hide powered by text.
		if ( $this->addon->get_setting( 'hide_powered_by', true ) ) {
			add_filter( 'bkx_show_powered_by', '__return_false' );
		}

		// Custom admin footer.
		$custom_footer = $this->addon->get_setting( 'custom_admin_footer', '' );
		if ( ! empty( $custom_footer ) ) {
			add_filter( 'admin_footer_text', array( $this, 'custom_admin_footer' ), 999 );
		}

		// Hide plugin notices.
		if ( $this->addon->get_setting( 'hide_plugin_notices', false ) ) {
			add_action( 'admin_head', array( $this, 'hide_notices_css' ) );
		}

		// Replace BookingX in admin bar.
		add_action( 'admin_bar_menu', array( $this, 'customize_admin_bar' ), 999 );

		// Filter page titles.
		add_filter( 'admin_title', array( $this, 'filter_admin_title' ), 999, 2 );

		// Replace BookingX in plugin list.
		add_filter( 'all_plugins', array( $this, 'filter_plugin_list' ) );
	}

	/**
	 * Add admin branding CSS.
	 */
	public function add_admin_branding_css() {
		$logo = $this->addon->get_setting( 'brand_logo', '' );
		$icon = $this->addon->get_setting( 'brand_icon', '' );

		if ( empty( $logo ) && empty( $icon ) ) {
			return;
		}

		echo '<style type="text/css">';

		// Replace header logo.
		if ( ! empty( $logo ) ) {
			echo '
			.bkx-admin-header .bkx-logo,
			.bkx-settings-header .bkx-logo {
				background-image: url(' . esc_url( $logo ) . ') !important;
				background-size: contain !important;
				background-repeat: no-repeat !important;
			}
			.bkx-admin-header .bkx-logo img,
			.bkx-settings-header .bkx-logo img {
				visibility: hidden !important;
			}
			';
		}

		// Replace menu icon.
		if ( ! empty( $icon ) ) {
			echo '
			#adminmenu .toplevel_page_bkx_booking .wp-menu-image,
			#adminmenu .menu-icon-bkx_booking .wp-menu-image {
				background-image: url(' . esc_url( $icon ) . ') !important;
				background-size: 20px 20px !important;
				background-repeat: no-repeat !important;
				background-position: center !important;
			}
			#adminmenu .toplevel_page_bkx_booking .wp-menu-image::before,
			#adminmenu .menu-icon-bkx_booking .wp-menu-image::before {
				content: "" !important;
			}
			';
		}

		echo '</style>';
	}

	/**
	 * Add CSS to hide BookingX branding.
	 */
	public function hide_branding_css() {
		echo '<style type="text/css">
			.bkx-branding,
			.bkx-powered-by,
			.bkx-logo-text,
			.bkx-admin-footer-branding,
			.bkx-booking-form .bkx-branding-footer,
			[class*="bookingx-branding"],
			[class*="bkx-branding"] {
				display: none !important;
				visibility: hidden !important;
			}
		</style>';
	}

	/**
	 * Add CSS to hide plugin notices.
	 */
	public function hide_notices_css() {
		echo '<style type="text/css">
			.bkx-admin-notice,
			.bkx-promo-notice,
			.bkx-upgrade-notice,
			[class*="bkx-notice"],
			.notice.bkx-notice {
				display: none !important;
			}
		</style>';
	}

	/**
	 * Custom admin footer text.
	 *
	 * @param string $text Original footer text.
	 * @return string
	 */
	public function custom_admin_footer( $text ) {
		$screen = get_current_screen();

		// Only on BookingX pages.
		if ( strpos( $screen->id, 'bkx' ) === false ) {
			return $text;
		}

		$custom = $this->addon->get_setting( 'custom_admin_footer', '' );
		if ( ! empty( $custom ) ) {
			return wp_kses_post( $custom );
		}

		return $text;
	}

	/**
	 * Customize admin bar.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar object.
	 */
	public function customize_admin_bar( $wp_admin_bar ) {
		$brand_name = $this->addon->get_setting( 'brand_name', '' );

		if ( empty( $brand_name ) ) {
			return;
		}

		// Find and update BookingX menu items.
		$node = $wp_admin_bar->get_node( 'bkx-bookings' );
		if ( $node ) {
			$node->title = str_replace( 'BookingX', $brand_name, $node->title );
			$wp_admin_bar->add_node( (array) $node );
		}
	}

	/**
	 * Filter admin page title.
	 *
	 * @param string $admin_title Current title.
	 * @param string $title       Page title.
	 * @return string
	 */
	public function filter_admin_title( $admin_title, $title ) {
		$brand_name = $this->addon->get_setting( 'brand_name', '' );

		if ( empty( $brand_name ) ) {
			return $admin_title;
		}

		// Replace BookingX with brand name.
		return str_replace( 'BookingX', $brand_name, $admin_title );
	}

	/**
	 * Filter plugin list.
	 *
	 * @param array $plugins All plugins.
	 * @return array
	 */
	public function filter_plugin_list( $plugins ) {
		$brand_name = $this->addon->get_setting( 'brand_name', '' );
		$brand_url  = $this->addon->get_setting( 'brand_url', '' );

		if ( empty( $brand_name ) ) {
			return $plugins;
		}

		foreach ( $plugins as $file => &$plugin ) {
			// Replace in BookingX related plugins.
			if ( strpos( $file, 'bookingx' ) !== false || strpos( $file, 'bkx-' ) !== false ) {
				$plugin['Name']        = str_replace( 'BookingX', $brand_name, $plugin['Name'] );
				$plugin['Title']       = str_replace( 'BookingX', $brand_name, $plugin['Title'] );
				$plugin['Description'] = str_replace( 'BookingX', $brand_name, $plugin['Description'] );

				if ( ! empty( $brand_url ) ) {
					$plugin['PluginURI'] = $brand_url;
					$plugin['AuthorURI'] = $brand_url;
				}
			}
		}

		return $plugins;
	}

	/**
	 * Get brand name.
	 *
	 * @return string
	 */
	public function get_brand_name() {
		$brand_name = $this->addon->get_setting( 'brand_name', '' );
		return ! empty( $brand_name ) ? $brand_name : 'BookingX';
	}

	/**
	 * Get brand logo URL.
	 *
	 * @param bool $dark Whether to get dark mode logo.
	 * @return string
	 */
	public function get_brand_logo( $dark = false ) {
		if ( $dark ) {
			$logo = $this->addon->get_setting( 'brand_logo_dark', '' );
			if ( ! empty( $logo ) ) {
				return $logo;
			}
		}

		return $this->addon->get_setting( 'brand_logo', '' );
	}

	/**
	 * Get brand icon URL.
	 *
	 * @return string
	 */
	public function get_brand_icon() {
		return $this->addon->get_setting( 'brand_icon', '' );
	}

	/**
	 * Get support email.
	 *
	 * @return string
	 */
	public function get_support_email() {
		$email = $this->addon->get_setting( 'support_email', '' );
		return ! empty( $email ) ? $email : get_option( 'admin_email' );
	}

	/**
	 * Get support URL.
	 *
	 * @return string
	 */
	public function get_support_url() {
		return $this->addon->get_setting( 'support_url', '' );
	}
}
