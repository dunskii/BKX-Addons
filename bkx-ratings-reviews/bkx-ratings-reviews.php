<?php
/**
 * Plugin Name: BookingX - Ratings & Reviews
 * Plugin URI: https://bookingx.com/addons/ratings-reviews
 * Description: Customer ratings and reviews system for services and providers with star ratings, text reviews, moderation, and aggregated scores.
 * Version: 1.0.0
 * Author: BookingX
 * Author URI: https://bookingx.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: bkx-ratings-reviews
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * BookingX requires at least: 2.0.0
 *
 * @package BookingX\RatingsReviews
 * @since   1.0.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Plugin constants.
 */
define( 'BKX_RATINGS_VERSION', '1.0.0' );
define( 'BKX_RATINGS_FILE', __FILE__ );
define( 'BKX_RATINGS_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_RATINGS_URL', plugin_dir_url( __FILE__ ) );
define( 'BKX_RATINGS_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load the autoloader.
 */
require_once BKX_RATINGS_PATH . 'src/autoload.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_ratings_reviews_init() {
	// Load text domain for translations
	load_plugin_textdomain( 'bkx-ratings-reviews', false, dirname( BKX_RATINGS_BASENAME ) . '/languages' );

	// Initialize the main addon class
	$addon = new \BookingX\RatingsReviews\RatingsReviewsAddon( BKX_RATINGS_FILE );

	// Initialize the addon
	if ( $addon->init() ) {
		// Store in global for access
		$GLOBALS['bkx_ratings_reviews'] = $addon;
	}
}
add_action( 'plugins_loaded', 'bkx_ratings_reviews_init', 10 );

/**
 * Get the main addon instance.
 *
 * @since 1.0.0
 * @return \BookingX\RatingsReviews\RatingsReviewsAddon|null
 */
function bkx_ratings() {
	return $GLOBALS['bkx_ratings_reviews'] ?? null;
}

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_ratings_reviews_activate() {
	// Check requirements before activation
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( BKX_RATINGS_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Ratings & Reviews requires PHP 7.4 or higher.', 'bkx-ratings-reviews' ),
			esc_html__( 'Plugin Activation Error', 'bkx-ratings-reviews' ),
			array( 'back_link' => true )
		);
	}

	if ( ! function_exists( 'BKX' ) ) {
		deactivate_plugins( BKX_RATINGS_BASENAME );
		wp_die(
			esc_html__( 'BookingX - Ratings & Reviews requires the BookingX plugin to be installed and activated.', 'bkx-ratings-reviews' ),
			esc_html__( 'Plugin Activation Error', 'bkx-ratings-reviews' ),
			array( 'back_link' => true )
		);
	}

	// Set activation flag for migrations
	set_transient( 'bkx_ratings_activated', true, 30 );
}
register_activation_hook( BKX_RATINGS_FILE, 'bkx_ratings_reviews_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function bkx_ratings_reviews_deactivate() {
	// Clear scheduled tasks
	wp_clear_scheduled_hook( 'bkx_ratings_send_review_requests' );
	wp_clear_scheduled_hook( 'bkx_ratings_cleanup' );

	// Clear caches
	delete_transient( 'bkx_ratings_activated' );
}
register_deactivation_hook( BKX_RATINGS_FILE, 'bkx_ratings_reviews_deactivate' );
