<?php
/**
 * Meta Service.
 *
 * Handles Open Graph and Twitter Card meta for BookingX content.
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
 * MetaService Class.
 */
class MetaService {

	/**
	 * Instance.
	 *
	 * @var MetaService
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
	 * @return MetaService
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
		// Open Graph.
		if ( $this->get_setting( 'og_services', true ) ) {
			add_filter( 'wpseo_opengraph_type', array( $this, 'filter_og_type' ) );
			add_filter( 'wpseo_opengraph_title', array( $this, 'filter_og_title' ) );
			add_filter( 'wpseo_opengraph_desc', array( $this, 'filter_og_description' ) );
			add_filter( 'wpseo_opengraph_image', array( $this, 'filter_og_image' ) );
			add_action( 'wpseo_opengraph', array( $this, 'add_og_meta' ) );
		}

		// Twitter Cards.
		if ( $this->get_setting( 'twitter_cards', true ) ) {
			add_filter( 'wpseo_twitter_card_type', array( $this, 'filter_twitter_card_type' ) );
			add_filter( 'wpseo_twitter_title', array( $this, 'filter_twitter_title' ) );
			add_filter( 'wpseo_twitter_description', array( $this, 'filter_twitter_description' ) );
		}

		// Additional meta tags.
		add_action( 'wp_head', array( $this, 'output_additional_meta' ), 5 );
	}

	/**
	 * Filter Open Graph type.
	 *
	 * @param string $type OG type.
	 * @return string
	 */
	public function filter_og_type( $type ) {
		$post = get_post();

		if ( ! $post ) {
			return $type;
		}

		// Use 'product' type for services.
		if ( 'bkx_base' === $post->post_type ) {
			return 'product';
		}

		// Use 'profile' type for seats/staff.
		if ( 'bkx_seat' === $post->post_type ) {
			return 'profile';
		}

		return $type;
	}

	/**
	 * Filter Open Graph title.
	 *
	 * @param string $title OG title.
	 * @return string
	 */
	public function filter_og_title( $title ) {
		$post = get_post();

		if ( ! $post ) {
			return $title;
		}

		if ( 'bkx_base' === $post->post_type ) {
			$price = get_post_meta( $post->ID, 'base_price', true );
			if ( $price && strpos( $title, '$' ) === false ) {
				$title .= ' - From $' . number_format( (float) $price, 2 );
			}
		}

		return $title;
	}

	/**
	 * Filter Open Graph description.
	 *
	 * @param string $description OG description.
	 * @return string
	 */
	public function filter_og_description( $description ) {
		$post = get_post();

		if ( ! $post ) {
			return $description;
		}

		// Add call to action for services.
		if ( 'bkx_base' === $post->post_type ) {
			if ( ! empty( $description ) && strpos( $description, 'book' ) === false ) {
				$description .= ' Book online today!';
			}
		}

		return $description;
	}

	/**
	 * Filter Open Graph image.
	 *
	 * @param string $image OG image URL.
	 * @return string
	 */
	public function filter_og_image( $image ) {
		$post = get_post();

		if ( ! $post ) {
			return $image;
		}

		// Use featured image if no OG image set.
		if ( empty( $image ) && has_post_thumbnail( $post->ID ) ) {
			$image = get_the_post_thumbnail_url( $post->ID, 'large' );
		}

		return $image;
	}

	/**
	 * Add additional Open Graph meta.
	 */
	public function add_og_meta() {
		$post = get_post();

		if ( ! $post || ! is_singular() ) {
			return;
		}

		// Add product-specific OG meta for services.
		if ( 'bkx_base' === $post->post_type ) {
			$price    = get_post_meta( $post->ID, 'base_price', true );
			$currency = get_option( 'bkx_currency', 'USD' );

			if ( $price ) {
				echo '<meta property="product:price:amount" content="' . esc_attr( $price ) . '" />' . "\n";
				echo '<meta property="product:price:currency" content="' . esc_attr( $currency ) . '" />' . "\n";
				echo '<meta property="product:availability" content="in stock" />' . "\n";
			}

			// Add category.
			$categories = get_the_terms( $post->ID, 'bkx_base_category' );
			if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
				echo '<meta property="product:category" content="' . esc_attr( $categories[0]->name ) . '" />' . "\n";
			}
		}

		// Add profile meta for seats.
		if ( 'bkx_seat' === $post->post_type ) {
			$name_parts = explode( ' ', $post->post_title, 2 );

			echo '<meta property="profile:first_name" content="' . esc_attr( $name_parts[0] ) . '" />' . "\n";

			if ( isset( $name_parts[1] ) ) {
				echo '<meta property="profile:last_name" content="' . esc_attr( $name_parts[1] ) . '" />' . "\n";
			}
		}
	}

	/**
	 * Filter Twitter card type.
	 *
	 * @param string $type Card type.
	 * @return string
	 */
	public function filter_twitter_card_type( $type ) {
		$post = get_post();

		if ( ! $post ) {
			return $type;
		}

		// Use summary_large_image for services with images.
		if ( in_array( $post->post_type, array( 'bkx_base', 'bkx_seat' ), true ) ) {
			if ( has_post_thumbnail( $post->ID ) ) {
				return 'summary_large_image';
			}
		}

		return $type;
	}

	/**
	 * Filter Twitter title.
	 *
	 * @param string $title Twitter title.
	 * @return string
	 */
	public function filter_twitter_title( $title ) {
		$post = get_post();

		if ( ! $post ) {
			return $title;
		}

		// Add emoji for services.
		if ( 'bkx_base' === $post->post_type ) {
			// Only if settings allow.
			if ( $this->get_setting( 'twitter_emoji', false ) ) {
				$title = '📅 ' . $title;
			}
		}

		return $title;
	}

	/**
	 * Filter Twitter description.
	 *
	 * @param string $description Twitter description.
	 * @return string
	 */
	public function filter_twitter_description( $description ) {
		$post = get_post();

		if ( ! $post ) {
			return $description;
		}

		// Shorter description for Twitter.
		if ( strlen( $description ) > 200 ) {
			$description = substr( $description, 0, 197 ) . '...';
		}

		return $description;
	}

	/**
	 * Output additional meta tags.
	 */
	public function output_additional_meta() {
		$post = get_post();

		if ( ! $post || ! is_singular() ) {
			return;
		}

		// Add geo meta for local business.
		$lat = get_option( 'bkx_business_lat', '' );
		$lng = get_option( 'bkx_business_lng', '' );

		if ( $lat && $lng ) {
			echo '<meta name="geo.position" content="' . esc_attr( $lat ) . ';' . esc_attr( $lng ) . '" />' . "\n";
			echo '<meta name="ICBM" content="' . esc_attr( $lat ) . ', ' . esc_attr( $lng ) . '" />' . "\n";
		}

		// Add business address.
		$city = get_option( 'bkx_business_city', '' );
		$state = get_option( 'bkx_business_state', '' );
		$country = get_option( 'bkx_business_country', '' );

		if ( $city && $state ) {
			echo '<meta name="geo.placename" content="' . esc_attr( $city . ', ' . $state ) . '" />' . "\n";
		}

		if ( $country ) {
			echo '<meta name="geo.region" content="' . esc_attr( $country ) . '" />' . "\n";
		}

		// Add service-specific meta.
		if ( 'bkx_base' === $post->post_type ) {
			$price = get_post_meta( $post->ID, 'base_price', true );

			if ( $price ) {
				echo '<meta name="price" content="' . esc_attr( '$' . number_format( (float) $price, 2 ) ) . '" />' . "\n";
			}
		}
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
