<?php
/**
 * String Replacer Service.
 *
 * Handles text replacement throughout the admin and frontend.
 *
 * @package BookingX\WhiteLabel
 */

namespace BookingX\WhiteLabel\Services;

defined( 'ABSPATH' ) || exit;

/**
 * StringReplacer class.
 */
class StringReplacer {

	/**
	 * Addon instance.
	 *
	 * @var \BookingX\WhiteLabel\WhiteLabelAddon
	 */
	private $addon;

	/**
	 * Replacement cache.
	 *
	 * @var array
	 */
	private $replacements = array();

	/**
	 * Constructor.
	 *
	 * @param \BookingX\WhiteLabel\WhiteLabelAddon $addon Addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Initialize string replacer hooks.
	 */
	public function init() {
		$this->build_replacements();

		if ( empty( $this->replacements ) ) {
			return;
		}

		// Filter translations.
		add_filter( 'gettext', array( $this, 'filter_gettext' ), 100, 3 );
		add_filter( 'gettext_with_context', array( $this, 'filter_gettext_with_context' ), 100, 4 );
		add_filter( 'ngettext', array( $this, 'filter_ngettext' ), 100, 5 );

		// Filter document title.
		add_filter( 'document_title_parts', array( $this, 'filter_document_title' ) );

		// Filter post type labels.
		add_filter( 'post_type_labels_bkx_booking', array( $this, 'filter_post_type_labels' ) );
		add_filter( 'post_type_labels_bkx_seat', array( $this, 'filter_post_type_labels' ) );
		add_filter( 'post_type_labels_bkx_base', array( $this, 'filter_post_type_labels' ) );
		add_filter( 'post_type_labels_bkx_addition', array( $this, 'filter_post_type_labels' ) );

		// Filter the_content and the_title.
		add_filter( 'the_title', array( $this, 'replace_in_string' ), 100 );
		add_filter( 'the_content', array( $this, 'replace_in_string' ), 100 );
		add_filter( 'widget_text', array( $this, 'replace_in_string' ), 100 );
	}

	/**
	 * Build replacement array from settings.
	 */
	private function build_replacements() {
		$this->replacements = array();

		// Add brand name replacement.
		$brand_name = $this->addon->get_setting( 'brand_name', '' );
		if ( ! empty( $brand_name ) ) {
			$this->replacements['BookingX'] = $brand_name;
			$this->replacements['bookingx'] = strtolower( $brand_name );
			$this->replacements['BOOKINGX'] = strtoupper( $brand_name );
		}

		// Add custom replacements.
		$custom = $this->addon->get_setting( 'replace_strings', array() );
		if ( ! empty( $custom ) && is_array( $custom ) ) {
			foreach ( $custom as $item ) {
				if ( ! empty( $item['search'] ) ) {
					$this->replacements[ $item['search'] ] = $item['replace'] ?? '';
				}
			}
		}
	}

	/**
	 * Replace strings in text.
	 *
	 * @param string $text Original text.
	 * @return string
	 */
	public function replace_in_string( $text ) {
		if ( empty( $this->replacements ) || empty( $text ) ) {
			return $text;
		}

		return str_replace(
			array_keys( $this->replacements ),
			array_values( $this->replacements ),
			$text
		);
	}

	/**
	 * Filter gettext.
	 *
	 * @param string $translated Translated text.
	 * @param string $text       Original text.
	 * @param string $domain     Text domain.
	 * @return string
	 */
	public function filter_gettext( $translated, $text, $domain ) {
		// Only filter BookingX domains.
		if ( strpos( $domain, 'bkx' ) === false && $domain !== 'bookingx' ) {
			return $translated;
		}

		return $this->replace_in_string( $translated );
	}

	/**
	 * Filter gettext with context.
	 *
	 * @param string $translated Translated text.
	 * @param string $text       Original text.
	 * @param string $context    Context.
	 * @param string $domain     Text domain.
	 * @return string
	 */
	public function filter_gettext_with_context( $translated, $text, $context, $domain ) {
		if ( strpos( $domain, 'bkx' ) === false && $domain !== 'bookingx' ) {
			return $translated;
		}

		return $this->replace_in_string( $translated );
	}

	/**
	 * Filter ngettext.
	 *
	 * @param string $translated Translated text.
	 * @param string $single     Single form.
	 * @param string $plural     Plural form.
	 * @param int    $number     Number.
	 * @param string $domain     Text domain.
	 * @return string
	 */
	public function filter_ngettext( $translated, $single, $plural, $number, $domain ) {
		if ( strpos( $domain, 'bkx' ) === false && $domain !== 'bookingx' ) {
			return $translated;
		}

		return $this->replace_in_string( $translated );
	}

	/**
	 * Filter document title parts.
	 *
	 * @param array $title_parts Title parts.
	 * @return array
	 */
	public function filter_document_title( $title_parts ) {
		foreach ( $title_parts as $key => $part ) {
			$title_parts[ $key ] = $this->replace_in_string( $part );
		}

		return $title_parts;
	}

	/**
	 * Filter post type labels.
	 *
	 * @param object $labels Labels object.
	 * @return object
	 */
	public function filter_post_type_labels( $labels ) {
		foreach ( $labels as $key => $label ) {
			if ( is_string( $label ) ) {
				$labels->$key = $this->replace_in_string( $label );
			}
		}

		return $labels;
	}

	/**
	 * Get all replacements.
	 *
	 * @return array
	 */
	public function get_replacements() {
		return $this->replacements;
	}

	/**
	 * Add replacement.
	 *
	 * @param string $search  Search string.
	 * @param string $replace Replacement string.
	 */
	public function add_replacement( $search, $replace ) {
		$this->replacements[ $search ] = $replace;
	}

	/**
	 * Remove replacement.
	 *
	 * @param string $search Search string.
	 */
	public function remove_replacement( $search ) {
		unset( $this->replacements[ $search ] );
	}

	/**
	 * Get default replacements.
	 *
	 * @return array
	 */
	public function get_default_replacements() {
		return array(
			array(
				'search'      => 'Booking',
				'replace'     => 'Appointment',
				'description' => __( 'Replace "Booking" with "Appointment" throughout the plugin.', 'bkx-white-label' ),
			),
			array(
				'search'      => 'Seat',
				'replace'     => 'Staff',
				'description' => __( 'Replace "Seat" with "Staff" for service providers.', 'bkx-white-label' ),
			),
			array(
				'search'      => 'Base',
				'replace'     => 'Service',
				'description' => __( 'Replace "Base" with "Service" for service items.', 'bkx-white-label' ),
			),
			array(
				'search'      => 'Addition',
				'replace'     => 'Extra',
				'description' => __( 'Replace "Addition" with "Extra" for add-on items.', 'bkx-white-label' ),
			),
		);
	}
}
