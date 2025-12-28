<?php
/**
 * Sitemap Service.
 *
 * Integrates BookingX content with Yoast SEO sitemaps.
 *
 * @package BookingX\YoastSeo\Services
 * @since   1.0.0
 */

namespace BookingX\YoastSeo\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SitemapService Class.
 */
class SitemapService {

	/**
	 * Instance.
	 *
	 * @var SitemapService
	 */
	private static $instance = null;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings = array();

	/**
	 * Get instance.
	 *
	 * @return SitemapService
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
		$this->settings = get_option( 'bkx_yoast_settings', array() );
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Sitemap exclusions.
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( $this, 'exclude_posts_from_sitemap' ) );

		// Sitemap priorities.
		add_filter( 'wpseo_sitemap_entry', array( $this, 'modify_sitemap_entry' ), 10, 3 );

		// Sitemap post types.
		add_filter( 'wpseo_sitemap_post_type', array( $this, 'modify_post_type_sitemap' ), 10, 2 );

		// Add image sitemap data.
		add_filter( 'wpseo_sitemap_urlimages', array( $this, 'add_sitemap_images' ), 10, 2 );

		// Ping search engines on new content.
		add_action( 'save_post_bkx_base', array( $this, 'ping_on_publish' ), 10, 2 );
		add_action( 'save_post_bkx_seat', array( $this, 'ping_on_publish' ), 10, 2 );
	}

	/**
	 * Exclude posts from sitemap.
	 *
	 * @param array $excluded_ids Excluded post IDs.
	 * @return array
	 */
	public function exclude_posts_from_sitemap( $excluded_ids ) {
		// Exclude unpublished bookings.
		$bookings = get_posts( array(
			'post_type'      => 'bkx_booking',
			'posts_per_page' => -1,
			'post_status'    => array( 'draft', 'pending', 'private', 'bkx-pending', 'bkx-cancelled' ),
			'fields'         => 'ids',
		) );

		if ( ! empty( $bookings ) ) {
			$excluded_ids = array_merge( $excluded_ids, $bookings );
		}

		// Exclude past bookings if setting enabled.
		if ( $this->get_setting( 'noindex_past_bookings', true ) ) {
			$past_bookings = get_posts( array(
				'post_type'      => 'bkx_booking',
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => 'booking_date',
						'value'   => gmdate( 'Y-m-d' ),
						'compare' => '<',
						'type'    => 'DATE',
					),
				),
			) );

			if ( ! empty( $past_bookings ) ) {
				$excluded_ids = array_merge( $excluded_ids, $past_bookings );
			}
		}

		// Exclude draft services and seats.
		$drafts = get_posts( array(
			'post_type'      => array( 'bkx_base', 'bkx_seat', 'bkx_addition' ),
			'posts_per_page' => -1,
			'post_status'    => array( 'draft', 'pending', 'private' ),
			'fields'         => 'ids',
		) );

		if ( ! empty( $drafts ) ) {
			$excluded_ids = array_merge( $excluded_ids, $drafts );
		}

		return array_unique( $excluded_ids );
	}

	/**
	 * Modify sitemap entry.
	 *
	 * @param array  $url       URL data.
	 * @param string $type      Post type.
	 * @param object $post      Post object.
	 * @return array
	 */
	public function modify_sitemap_entry( $url, $type, $post ) {
		if ( ! $post ) {
			return $url;
		}

		// Higher priority for services.
		if ( 'bkx_base' === $post->post_type ) {
			$url['pri'] = 0.8;

			// Popular services get higher priority.
			$bookings_count = $this->get_service_bookings_count( $post->ID );
			if ( $bookings_count > 50 ) {
				$url['pri'] = 0.9;
			}

			// Frequent updates.
			$url['chf'] = 'weekly';
		}

		// Medium priority for seats.
		if ( 'bkx_seat' === $post->post_type ) {
			$url['pri'] = 0.7;
			$url['chf'] = 'weekly';
		}

		// Low priority for extras.
		if ( 'bkx_addition' === $post->post_type ) {
			$url['pri'] = 0.5;
			$url['chf'] = 'monthly';
		}

		// Bookings - if included.
		if ( 'bkx_booking' === $post->post_type ) {
			$url['pri'] = 0.3;
			$url['chf'] = 'never';
		}

		return $url;
	}

	/**
	 * Modify post type sitemap settings.
	 *
	 * @param array  $sitemap   Sitemap data.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public function modify_post_type_sitemap( $sitemap, $post_type ) {
		// Enable services sitemap.
		if ( 'bkx_base' === $post_type && $this->get_setting( 'sitemap_services', true ) ) {
			$sitemap['include'] = true;
		}

		// Enable seats sitemap.
		if ( 'bkx_seat' === $post_type && $this->get_setting( 'sitemap_seats', true ) ) {
			$sitemap['include'] = true;
		}

		// Exclude bookings from sitemap by default.
		if ( 'bkx_booking' === $post_type ) {
			$sitemap['include'] = false;
		}

		return $sitemap;
	}

	/**
	 * Add images to sitemap.
	 *
	 * @param array   $images Image URLs.
	 * @param integer $post_id Post ID.
	 * @return array
	 */
	public function add_sitemap_images( $images, $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return $images;
		}

		// Add featured image.
		if ( has_post_thumbnail( $post_id ) ) {
			$image_id  = get_post_thumbnail_id( $post_id );
			$image_url = wp_get_attachment_url( $image_id );
			$image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

			$images[] = array(
				'src'   => $image_url,
				'title' => $post->post_title,
				'alt'   => $image_alt ?: $post->post_title,
			);
		}

		// Add gallery images for services.
		if ( 'bkx_base' === $post->post_type ) {
			$gallery = get_post_meta( $post_id, '_bkx_gallery', true );

			if ( ! empty( $gallery ) && is_array( $gallery ) ) {
				foreach ( $gallery as $image_id ) {
					$image_url = wp_get_attachment_url( $image_id );
					$image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

					if ( $image_url ) {
						$images[] = array(
							'src'   => $image_url,
							'title' => $post->post_title,
							'alt'   => $image_alt ?: $post->post_title,
						);
					}
				}
			}
		}

		return $images;
	}

	/**
	 * Ping search engines on publish.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function ping_on_publish( $post_id, $post ) {
		// Only on publish.
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Check if this is a new post or status change.
		$old_status = get_post_meta( $post_id, '_bkx_last_status', true );

		if ( $old_status === 'publish' ) {
			return; // Already published, skip ping.
		}

		// Update status.
		update_post_meta( $post_id, '_bkx_last_status', 'publish' );

		// Trigger Yoast sitemap ping.
		if ( class_exists( 'WPSEO_Sitemaps' ) ) {
			\WPSEO_Sitemaps::ping_search_engines();
		}
	}

	/**
	 * Get service bookings count.
	 *
	 * @param int $service_id Service ID.
	 * @return int
	 */
	private function get_service_bookings_count( $service_id ) {
		global $wpdb;

		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta}
			WHERE meta_key = 'booking_multi_base'
			AND (meta_value LIKE %s OR meta_value LIKE %s)",
			'%"' . $service_id . '"%',
			'%i:' . $service_id . ';%'
		) );

		return absint( $count );
	}

	/**
	 * Get setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function get_setting( $key, $default = null ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}
}
