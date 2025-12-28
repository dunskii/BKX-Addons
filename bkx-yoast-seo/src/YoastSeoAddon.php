<?php
/**
 * Main Yoast SEO Addon Class.
 *
 * @package BookingX\YoastSeo
 * @since   1.0.0
 */

namespace BookingX\YoastSeo;

use BookingX\YoastSeo\Services\SchemaService;
use BookingX\YoastSeo\Services\MetaService;
use BookingX\YoastSeo\Services\SitemapService;
use BookingX\YoastSeo\Admin\SettingsPage;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * YoastSeoAddon Class.
 */
class YoastSeoAddon {

	/**
	 * Instance.
	 *
	 * @var YoastSeoAddon
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
	 * @return YoastSeoAddon
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
		$this->init_services();
		$this->init_admin();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Add Yoast SEO column support.
		add_filter( 'wpseo_primary_term_taxonomies', array( $this, 'add_primary_term_support' ), 10, 2 );

		// Add SEO analysis support for BookingX post types.
		add_filter( 'wpseo_accessible_post_types', array( $this, 'add_post_types' ) );

		// Title templates.
		add_filter( 'wpseo_title', array( $this, 'filter_title' ), 10, 1 );
		add_filter( 'wpseo_metadesc', array( $this, 'filter_meta_description' ), 10, 1 );

		// Breadcrumbs.
		if ( $this->get_setting( 'breadcrumbs', true ) ) {
			add_filter( 'wpseo_breadcrumb_links', array( $this, 'modify_breadcrumbs' ) );
		}

		// Canonical URLs.
		if ( $this->get_setting( 'canonical_urls', true ) ) {
			add_filter( 'wpseo_canonical', array( $this, 'filter_canonical_url' ) );
		}

		// Robots meta.
		add_filter( 'wpseo_robots', array( $this, 'filter_robots' ) );
	}

	/**
	 * Initialize services.
	 */
	private function init_services() {
		if ( $this->get_setting( 'schema_service', true ) || $this->get_setting( 'schema_local_business', true ) ) {
			SchemaService::get_instance();
		}

		MetaService::get_instance();
		SitemapService::get_instance();
	}

	/**
	 * Initialize admin.
	 */
	private function init_admin() {
		if ( is_admin() ) {
			SettingsPage::get_instance();
		}
	}

	/**
	 * Get setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = null ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	/**
	 * Add BookingX post types to Yoast.
	 *
	 * @param array $post_types Post types.
	 * @return array
	 */
	public function add_post_types( $post_types ) {
		$bkx_types = array( 'bkx_base', 'bkx_seat', 'bkx_addition' );

		foreach ( $bkx_types as $type ) {
			if ( ! in_array( $type, $post_types, true ) ) {
				$post_types[] = $type;
			}
		}

		return $post_types;
	}

	/**
	 * Add primary term support.
	 *
	 * @param array  $taxonomies Taxonomies.
	 * @param string $post_type  Post type.
	 * @return array
	 */
	public function add_primary_term_support( $taxonomies, $post_type ) {
		if ( 'bkx_base' === $post_type ) {
			$taxonomies[] = 'bkx_base_category';
		}

		return $taxonomies;
	}

	/**
	 * Filter SEO title.
	 *
	 * @param string $title Current title.
	 * @return string
	 */
	public function filter_title( $title ) {
		if ( ! is_singular() ) {
			return $title;
		}

		$post = get_post();

		if ( ! $post ) {
			return $title;
		}

		// Service title.
		if ( 'bkx_base' === $post->post_type && empty( get_post_meta( $post->ID, '_yoast_wpseo_title', true ) ) ) {
			$template = $this->get_setting( 'default_service_title', '%service_name% - Book Online | %sitename%' );
			$title    = $this->replace_title_variables( $template, $post );
		}

		// Seat title.
		if ( 'bkx_seat' === $post->post_type && empty( get_post_meta( $post->ID, '_yoast_wpseo_title', true ) ) ) {
			$template = $this->get_setting( 'default_seat_title', '%seat_name% - %seat_alias% | %sitename%' );
			$title    = $this->replace_title_variables( $template, $post );
		}

		return $title;
	}

	/**
	 * Filter meta description.
	 *
	 * @param string $description Current description.
	 * @return string
	 */
	public function filter_meta_description( $description ) {
		if ( ! $this->get_setting( 'auto_meta_description', true ) ) {
			return $description;
		}

		if ( ! is_singular() ) {
			return $description;
		}

		$post = get_post();

		if ( ! $post ) {
			return $description;
		}

		// Skip if custom description is set.
		$custom = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true );
		if ( ! empty( $custom ) ) {
			return $description;
		}

		// Auto-generate for services.
		if ( 'bkx_base' === $post->post_type ) {
			$description = $this->generate_service_description( $post );
		}

		// Auto-generate for seats.
		if ( 'bkx_seat' === $post->post_type ) {
			$description = $this->generate_seat_description( $post );
		}

		return $description;
	}

	/**
	 * Modify breadcrumbs.
	 *
	 * @param array $links Breadcrumb links.
	 * @return array
	 */
	public function modify_breadcrumbs( $links ) {
		$post = get_post();

		if ( ! $post ) {
			return $links;
		}

		// Add services archive to service single.
		if ( 'bkx_base' === $post->post_type ) {
			$archive = get_post_type_archive_link( 'bkx_base' );
			$label   = get_option( 'bkx_alias_base', __( 'Services', 'bkx-yoast-seo' ) );

			if ( $archive ) {
				// Insert before the last item (current page).
				$last = array_pop( $links );
				$links[] = array(
					'url'  => $archive,
					'text' => $label,
				);
				$links[] = $last;
			}
		}

		// Add seats archive to seat single.
		if ( 'bkx_seat' === $post->post_type ) {
			$archive = get_post_type_archive_link( 'bkx_seat' );
			$label   = get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-yoast-seo' ) );

			if ( $archive ) {
				$last = array_pop( $links );
				$links[] = array(
					'url'  => $archive,
					'text' => $label,
				);
				$links[] = $last;
			}
		}

		return $links;
	}

	/**
	 * Filter canonical URL.
	 *
	 * @param string $canonical Current canonical URL.
	 * @return string
	 */
	public function filter_canonical_url( $canonical ) {
		$post = get_post();

		if ( ! $post ) {
			return $canonical;
		}

		// Ensure clean canonical for BookingX pages.
		if ( in_array( $post->post_type, array( 'bkx_base', 'bkx_seat', 'bkx_addition' ), true ) ) {
			$canonical = get_permalink( $post->ID );
		}

		return $canonical;
	}

	/**
	 * Filter robots meta.
	 *
	 * @param string $robots Current robots value.
	 * @return string
	 */
	public function filter_robots( $robots ) {
		$post = get_post();

		if ( ! $post ) {
			return $robots;
		}

		// Noindex past booking pages.
		if ( 'bkx_booking' === $post->post_type && $this->get_setting( 'noindex_past_bookings', true ) ) {
			$booking_date = get_post_meta( $post->ID, 'booking_date', true );

			if ( $booking_date && strtotime( $booking_date ) < time() ) {
				$robots = 'noindex, nofollow';
			}
		}

		return $robots;
	}

	/**
	 * Replace title variables.
	 *
	 * @param string   $template Title template.
	 * @param \WP_Post $post     Post object.
	 * @return string
	 */
	private function replace_title_variables( $template, $post ) {
		$replacements = array(
			'%service_name%' => $post->post_title,
			'%seat_name%'    => $post->post_title,
			'%seat_alias%'   => get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-yoast-seo' ) ),
			'%base_alias%'   => get_option( 'bkx_alias_base', __( 'Services', 'bkx-yoast-seo' ) ),
			'%sitename%'     => get_bloginfo( 'name' ),
			'%sep%'          => '-',
		);

		// Add price.
		$price = get_post_meta( $post->ID, 'base_price', true );
		if ( $price ) {
			$replacements['%price%'] = '$' . number_format( (float) $price, 2 );
		}

		// Add duration.
		$duration = get_post_meta( $post->ID, 'base_time', true );
		if ( $duration ) {
			$hours = floor( $duration / 60 );
			$mins  = $duration % 60;
			$replacements['%duration%'] = $hours ? sprintf( '%dh %dm', $hours, $mins ) : sprintf( '%d min', $mins );
		}

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $template );
	}

	/**
	 * Generate service description.
	 *
	 * @param \WP_Post $post Service post.
	 * @return string
	 */
	private function generate_service_description( $post ) {
		$parts = array();

		// Base description from excerpt or content.
		if ( ! empty( $post->post_excerpt ) ) {
			$parts[] = wp_strip_all_tags( $post->post_excerpt );
		} elseif ( ! empty( $post->post_content ) ) {
			$parts[] = wp_trim_words( wp_strip_all_tags( $post->post_content ), 20, '' );
		}

		// Add price.
		$price = get_post_meta( $post->ID, 'base_price', true );
		if ( $price ) {
			$parts[] = sprintf( __( 'From $%s', 'bkx-yoast-seo' ), number_format( (float) $price, 2 ) );
		}

		// Add duration.
		$duration = get_post_meta( $post->ID, 'base_time', true );
		if ( $duration ) {
			$hours = floor( $duration / 60 );
			$mins  = $duration % 60;

			if ( $hours && $mins ) {
				$parts[] = sprintf( __( '%dh %dm session', 'bkx-yoast-seo' ), $hours, $mins );
			} elseif ( $hours ) {
				$parts[] = sprintf( __( '%d hour session', 'bkx-yoast-seo' ), $hours );
			} else {
				$parts[] = sprintf( __( '%d minute session', 'bkx-yoast-seo' ), $mins );
			}
		}

		$parts[] = __( 'Book online now!', 'bkx-yoast-seo' );

		$description = implode( '. ', $parts );

		// Limit to 160 characters.
		if ( strlen( $description ) > 160 ) {
			$description = substr( $description, 0, 157 ) . '...';
		}

		return $description;
	}

	/**
	 * Generate seat description.
	 *
	 * @param \WP_Post $post Seat post.
	 * @return string
	 */
	private function generate_seat_description( $post ) {
		$seat_alias = get_option( 'bkx_alias_seat', __( 'Staff', 'bkx-yoast-seo' ) );
		$parts      = array();

		// Name and alias.
		$parts[] = sprintf( __( 'Book an appointment with %s', 'bkx-yoast-seo' ), $post->post_title );

		// Add bio from excerpt.
		if ( ! empty( $post->post_excerpt ) ) {
			$parts[] = wp_strip_all_tags( $post->post_excerpt );
		}

		// Add call to action.
		$parts[] = __( 'View availability and book online.', 'bkx-yoast-seo' );

		$description = implode( '. ', $parts );

		// Limit to 160 characters.
		if ( strlen( $description ) > 160 ) {
			$description = substr( $description, 0, 157 ) . '...';
		}

		return $description;
	}
}
