<?php
/**
 * Menu Manager Service.
 *
 * Handles admin menu customization, hiding items, and reordering.
 *
 * @package BookingX\WhiteLabel
 */

namespace BookingX\WhiteLabel\Services;

defined( 'ABSPATH' ) || exit;

/**
 * MenuManager class.
 */
class MenuManager {

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
	 * Initialize menu hooks.
	 */
	public function init() {
		// Hide menu items.
		add_action( 'admin_menu', array( $this, 'hide_menu_items' ), 999 );

		// Reorder menu items.
		add_filter( 'custom_menu_order', '__return_true' );
		add_filter( 'menu_order', array( $this, 'custom_menu_order' ), 999 );

		// Customize submenu items.
		add_action( 'admin_menu', array( $this, 'customize_submenu' ), 999 );

		// Rename menu items.
		add_action( 'admin_menu', array( $this, 'rename_menu_items' ), 999 );
	}

	/**
	 * Hide specified menu items.
	 */
	public function hide_menu_items() {
		$hide_items = $this->addon->get_setting( 'hide_menu_items', array() );

		if ( empty( $hide_items ) ) {
			return;
		}

		foreach ( $hide_items as $menu_slug ) {
			remove_menu_page( $menu_slug );

			// Also try removing as submenu.
			$parent_slugs = array(
				'edit.php?post_type=bkx_booking',
				'options-general.php',
				'tools.php',
			);

			foreach ( $parent_slugs as $parent ) {
				remove_submenu_page( $parent, $menu_slug );
			}
		}
	}

	/**
	 * Custom menu order.
	 *
	 * @param array $menu_order Current menu order.
	 * @return array
	 */
	public function custom_menu_order( $menu_order ) {
		$custom_order = $this->addon->get_setting( 'custom_menu_order', array() );

		if ( empty( $custom_order ) ) {
			return $menu_order;
		}

		// Build new order based on custom settings.
		$new_order = array();

		foreach ( $custom_order as $item ) {
			if ( in_array( $item, $menu_order, true ) ) {
				$new_order[] = $item;
			}
		}

		// Add remaining items not in custom order.
		foreach ( $menu_order as $item ) {
			if ( ! in_array( $item, $new_order, true ) ) {
				$new_order[] = $item;
			}
		}

		return $new_order;
	}

	/**
	 * Customize submenu items.
	 */
	public function customize_submenu() {
		global $submenu;

		$brand_name = $this->addon->get_setting( 'brand_name', '' );

		if ( empty( $brand_name ) ) {
			return;
		}

		// Find BookingX submenu.
		$bkx_parent = 'edit.php?post_type=bkx_booking';

		if ( isset( $submenu[ $bkx_parent ] ) ) {
			foreach ( $submenu[ $bkx_parent ] as &$item ) {
				// Replace BookingX in submenu titles.
				$item[0] = str_replace( 'BookingX', $brand_name, $item[0] );
			}
		}
	}

	/**
	 * Rename menu items based on brand name.
	 */
	public function rename_menu_items() {
		global $menu;

		$brand_name = $this->addon->get_setting( 'brand_name', '' );

		if ( empty( $brand_name ) ) {
			return;
		}

		foreach ( $menu as &$item ) {
			// Replace BookingX in menu titles.
			if ( isset( $item[0] ) ) {
				$item[0] = str_replace( 'BookingX', $brand_name, $item[0] );
			}
		}
	}

	/**
	 * Get BookingX menu items for configuration.
	 *
	 * @return array
	 */
	public function get_bookingx_menu_items() {
		$items = array(
			array(
				'slug'  => 'edit.php?post_type=bkx_booking',
				'label' => __( 'Bookings', 'bkx-white-label' ),
			),
			array(
				'slug'  => 'edit.php?post_type=bkx_seat',
				'label' => __( 'Seats/Staff', 'bkx-white-label' ),
			),
			array(
				'slug'  => 'edit.php?post_type=bkx_base',
				'label' => __( 'Services', 'bkx-white-label' ),
			),
			array(
				'slug'  => 'edit.php?post_type=bkx_addition',
				'label' => __( 'Additions/Extras', 'bkx-white-label' ),
			),
			array(
				'slug'  => 'bkx-settings',
				'label' => __( 'Settings', 'bkx-white-label' ),
			),
			array(
				'slug'  => 'bkx-calendar',
				'label' => __( 'Calendar', 'bkx-white-label' ),
			),
			array(
				'slug'  => 'bkx-reports',
				'label' => __( 'Reports', 'bkx-white-label' ),
			),
		);

		return apply_filters( 'bkx_white_label_menu_items', $items );
	}

	/**
	 * Get all admin menu items for configuration UI.
	 *
	 * @return array
	 */
	public function get_all_menu_items() {
		global $menu;

		$items = array();

		if ( empty( $menu ) ) {
			return $items;
		}

		foreach ( $menu as $item ) {
			if ( empty( $item[0] ) || empty( $item[2] ) ) {
				continue;
			}

			// Skip separators.
			if ( strpos( $item[2], 'separator' ) !== false ) {
				continue;
			}

			$items[] = array(
				'slug'  => $item[2],
				'label' => wp_strip_all_tags( $item[0] ),
			);
		}

		return $items;
	}

	/**
	 * Check if a menu item is hidden.
	 *
	 * @param string $slug Menu slug.
	 * @return bool
	 */
	public function is_hidden( $slug ) {
		$hide_items = $this->addon->get_setting( 'hide_menu_items', array() );
		return in_array( $slug, $hide_items, true );
	}

	/**
	 * Hide a menu item.
	 *
	 * @param string $slug Menu slug.
	 */
	public function hide_item( $slug ) {
		$hide_items = $this->addon->get_setting( 'hide_menu_items', array() );

		if ( ! in_array( $slug, $hide_items, true ) ) {
			$hide_items[] = $slug;
			$settings     = $this->addon->get_settings();
			$settings['hide_menu_items'] = $hide_items;
			$this->addon->update_settings( $settings );
		}
	}

	/**
	 * Show a hidden menu item.
	 *
	 * @param string $slug Menu slug.
	 */
	public function show_item( $slug ) {
		$hide_items = $this->addon->get_setting( 'hide_menu_items', array() );
		$key        = array_search( $slug, $hide_items, true );

		if ( $key !== false ) {
			unset( $hide_items[ $key ] );
			$settings = $this->addon->get_settings();
			$settings['hide_menu_items'] = array_values( $hide_items );
			$this->addon->update_settings( $settings );
		}
	}
}
