<?php
/**
 * Uninstall script for BKX Developer SDK.
 *
 * @package BookingX\DeveloperSDK
 */

// Exit if not called from WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all plugin data.
 */
function bkx_developer_sdk_uninstall() {
	// Delete options.
	$options = array(
		'bkx_developer_sdk_settings',
		'bkx_developer_sdk_version',
		'bkx_sandboxes',
		'bkx_api_explorer_log',
	);

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Delete generated code directory.
	$generated_path = WP_CONTENT_DIR . '/bkx-generated/';
	if ( file_exists( $generated_path ) ) {
		bkx_dev_sdk_delete_directory( $generated_path );
	}

	// Delete test data.
	bkx_dev_sdk_delete_test_data();

	// Clear any cached data.
	wp_cache_flush();
}

/**
 * Recursively delete a directory.
 *
 * @param string $dir Directory path.
 * @return bool True on success.
 */
function bkx_dev_sdk_delete_directory( $dir ) {
	if ( ! file_exists( $dir ) ) {
		return true;
	}

	if ( ! is_dir( $dir ) ) {
		return unlink( $dir );
	}

	$files = array_diff( scandir( $dir ), array( '.', '..' ) );

	foreach ( $files as $file ) {
		$path = $dir . DIRECTORY_SEPARATOR . $file;
		if ( is_dir( $path ) ) {
			bkx_dev_sdk_delete_directory( $path );
		} else {
			unlink( $path );
		}
	}

	return rmdir( $dir );
}

/**
 * Delete all test data.
 */
function bkx_dev_sdk_delete_test_data() {
	$post_types = array( 'bkx_booking', 'bkx_base', 'bkx_seat', 'bkx_addition' );

	foreach ( $post_types as $post_type ) {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => -1,
				'meta_key'       => '_test_data',
				'meta_value'     => '1',
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	// Delete sandbox data.
	$sandboxes = get_option( 'bkx_sandboxes', array() );
	foreach ( $sandboxes as $sandbox_id => $sandbox ) {
		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'posts_per_page' => -1,
					'meta_key'       => '_sandbox_id',
					'meta_value'     => $sandbox_id,
					'fields'         => 'ids',
				)
			);

			foreach ( $posts as $post_id ) {
				wp_delete_post( $post_id, true );
			}
		}
	}
}

// Run uninstall.
bkx_developer_sdk_uninstall();
