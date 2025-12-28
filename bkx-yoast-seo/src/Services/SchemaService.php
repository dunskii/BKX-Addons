<?php
/**
 * Schema Service.
 *
 * Adds structured data (JSON-LD) for BookingX content.
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
 * SchemaService Class.
 */
class SchemaService {

	/**
	 * Instance.
	 *
	 * @var SchemaService
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
	 * @return SchemaService
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
		// Add schema pieces.
		add_filter( 'wpseo_schema_graph_pieces', array( $this, 'add_schema_pieces' ), 10, 2 );

		// Modify existing schema.
		add_filter( 'wpseo_schema_webpage', array( $this, 'filter_webpage_schema' ), 10, 1 );

		// Add schema output.
		add_action( 'wp_head', array( $this, 'output_additional_schema' ), 5 );
	}

	/**
	 * Add custom schema pieces.
	 *
	 * @param array  $pieces  Schema pieces.
	 * @param object $context Schema context.
	 * @return array
	 */
	public function add_schema_pieces( $pieces, $context ) {
		// Service schema for bkx_base.
		if ( $this->get_setting( 'schema_service', true ) ) {
			$pieces[] = new Schema\ServiceSchema( $context );
		}

		// LocalBusiness for booking pages.
		if ( $this->get_setting( 'schema_local_business', true ) ) {
			$pieces[] = new Schema\LocalBusinessSchema( $context );
		}

		return $pieces;
	}

	/**
	 * Filter WebPage schema.
	 *
	 * @param array $schema WebPage schema.
	 * @return array
	 */
	public function filter_webpage_schema( $schema ) {
		$post = get_post();

		if ( ! $post ) {
			return $schema;
		}

		// Enhance WebPage for services.
		if ( 'bkx_base' === $post->post_type ) {
			$schema['@type']     = array( 'WebPage', 'ItemPage' );
			$schema['specialty'] = get_option( 'bkx_alias_base', 'Services' );
		}

		return $schema;
	}

	/**
	 * Output additional schema.
	 */
	public function output_additional_schema() {
		$post = get_post();

		if ( ! $post || ! is_singular() ) {
			return;
		}

		$schema = null;

		// Service schema.
		if ( 'bkx_base' === $post->post_type && $this->get_setting( 'schema_service', true ) ) {
			$schema = $this->build_service_schema( $post );
		}

		// Person schema for seats.
		if ( 'bkx_seat' === $post->post_type ) {
			$schema = $this->build_person_schema( $post );
		}

		if ( $schema ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
		}
	}

	/**
	 * Build Service schema.
	 *
	 * @param \WP_Post $post Service post.
	 * @return array
	 */
	private function build_service_schema( $post ) {
		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Service',
			'@id'         => get_permalink( $post->ID ) . '#service',
			'name'        => $post->post_title,
			'description' => ! empty( $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 30 ),
			'url'         => get_permalink( $post->ID ),
		);

		// Add provider (business).
		$schema['provider'] = array(
			'@type' => 'LocalBusiness',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url(),
		);

		// Add price.
		$price = get_post_meta( $post->ID, 'base_price', true );
		if ( $price ) {
			$schema['offers'] = array(
				'@type'         => 'Offer',
				'price'         => (float) $price,
				'priceCurrency' => get_option( 'bkx_currency', 'USD' ),
				'availability'  => 'https://schema.org/InStock',
				'url'           => get_permalink( $post->ID ),
			);
		}

		// Add duration.
		$duration = get_post_meta( $post->ID, 'base_time', true );
		if ( $duration ) {
			$hours   = floor( $duration / 60 );
			$minutes = $duration % 60;
			$schema['estimatedDuration'] = sprintf( 'PT%dH%dM', $hours, $minutes );
		}

		// Add image.
		if ( has_post_thumbnail( $post->ID ) ) {
			$schema['image'] = get_the_post_thumbnail_url( $post->ID, 'large' );
		}

		// Add category.
		$categories = get_the_terms( $post->ID, 'bkx_base_category' );
		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			$schema['category'] = $categories[0]->name;
		}

		// Add aggregate rating if reviews exist.
		$rating = $this->get_service_rating( $post->ID );
		if ( $rating ) {
			$schema['aggregateRating'] = $rating;
		}

		return $schema;
	}

	/**
	 * Build Person schema for seats.
	 *
	 * @param \WP_Post $post Seat post.
	 * @return array
	 */
	private function build_person_schema( $post ) {
		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Person',
			'@id'         => get_permalink( $post->ID ) . '#person',
			'name'        => $post->post_title,
			'description' => ! empty( $post->post_excerpt ) ? $post->post_excerpt : '',
			'url'         => get_permalink( $post->ID ),
		);

		// Add image.
		if ( has_post_thumbnail( $post->ID ) ) {
			$schema['image'] = get_the_post_thumbnail_url( $post->ID, 'large' );
		}

		// Add job title.
		$job_title = get_post_meta( $post->ID, 'seat_title', true );
		if ( $job_title ) {
			$schema['jobTitle'] = $job_title;
		}

		// Add works for.
		$schema['worksFor'] = array(
			'@type' => 'LocalBusiness',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url(),
		);

		return $schema;
	}

	/**
	 * Get service aggregate rating.
	 *
	 * @param int $service_id Service ID.
	 * @return array|null
	 */
	private function get_service_rating( $service_id ) {
		// Check if ratings add-on is active.
		if ( ! function_exists( 'bkx_get_service_rating' ) ) {
			return null;
		}

		$rating = bkx_get_service_rating( $service_id );

		if ( ! $rating || empty( $rating['count'] ) ) {
			return null;
		}

		return array(
			'@type'       => 'AggregateRating',
			'ratingValue' => $rating['average'],
			'reviewCount' => $rating['count'],
			'bestRating'  => 5,
			'worstRating' => 1,
		);
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

/**
 * Service Schema Piece.
 */
namespace BookingX\YoastSeo\Services\Schema;

/**
 * ServiceSchema Class.
 */
class ServiceSchema implements \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece {

	/**
	 * Context.
	 *
	 * @var object
	 */
	private $context;

	/**
	 * Constructor.
	 *
	 * @param object $context Schema context.
	 */
	public function __construct( $context ) {
		$this->context = $context;
	}

	/**
	 * Check if this piece should be generated.
	 *
	 * @return bool
	 */
	public function is_needed() {
		return is_singular( 'bkx_base' );
	}

	/**
	 * Generate schema.
	 *
	 * @return array
	 */
	public function generate() {
		$post = get_post();

		if ( ! $post ) {
			return array();
		}

		return array(
			'@type'       => 'Service',
			'@id'         => $this->context->canonical . '#service',
			'name'        => $post->post_title,
			'description' => ! empty( $post->post_excerpt ) ? $post->post_excerpt : wp_trim_words( $post->post_content, 30 ),
			'provider'    => array( '@id' => $this->context->site_url . '#organization' ),
		);
	}
}

/**
 * LocalBusiness Schema Piece.
 */
class LocalBusinessSchema implements \Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece {

	/**
	 * Context.
	 *
	 * @var object
	 */
	private $context;

	/**
	 * Constructor.
	 *
	 * @param object $context Schema context.
	 */
	public function __construct( $context ) {
		$this->context = $context;
	}

	/**
	 * Check if this piece should be generated.
	 *
	 * @return bool
	 */
	public function is_needed() {
		return is_singular( array( 'bkx_base', 'bkx_seat' ) );
	}

	/**
	 * Generate schema.
	 *
	 * @return array
	 */
	public function generate() {
		return array(
			'@type'     => 'LocalBusiness',
			'@id'       => $this->context->site_url . '#localbusiness',
			'name'      => get_bloginfo( 'name' ),
			'url'       => home_url(),
			'telephone' => get_option( 'bkx_business_phone', '' ),
			'address'   => $this->get_address(),
		);
	}

	/**
	 * Get business address.
	 *
	 * @return array
	 */
	private function get_address() {
		return array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => get_option( 'bkx_business_address', '' ),
			'addressLocality' => get_option( 'bkx_business_city', '' ),
			'addressRegion'   => get_option( 'bkx_business_state', '' ),
			'postalCode'      => get_option( 'bkx_business_zip', '' ),
			'addressCountry'  => get_option( 'bkx_business_country', 'US' ),
		);
	}
}
