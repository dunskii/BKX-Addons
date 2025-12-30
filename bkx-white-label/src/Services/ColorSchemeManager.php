<?php
/**
 * Color Scheme Manager Service.
 *
 * Handles custom color schemes for admin and frontend.
 *
 * @package BookingX\WhiteLabel
 */

namespace BookingX\WhiteLabel\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ColorSchemeManager class.
 */
class ColorSchemeManager {

	/**
	 * Addon instance.
	 *
	 * @var \BookingX\WhiteLabel\WhiteLabelAddon
	 */
	private $addon;

	/**
	 * Default colors.
	 *
	 * @var array
	 */
	private $defaults = array(
		'primary_color'    => '#2271b1',
		'secondary_color'  => '#135e96',
		'accent_color'     => '#72aee6',
		'success_color'    => '#00a32a',
		'warning_color'    => '#dba617',
		'error_color'      => '#d63638',
		'text_color'       => '#1d2327',
		'background_color' => '#ffffff',
	);

	/**
	 * Constructor.
	 *
	 * @param \BookingX\WhiteLabel\WhiteLabelAddon $addon Addon instance.
	 */
	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	/**
	 * Initialize color scheme hooks.
	 */
	public function init() {
		// Add admin color scheme CSS.
		add_action( 'admin_head', array( $this, 'add_admin_color_css' ), 100 );

		// Add frontend color scheme CSS variables.
		add_action( 'wp_head', array( $this, 'add_frontend_color_css' ), 100 );

		// Filter inline styles.
		add_filter( 'bkx_inline_styles', array( $this, 'filter_inline_styles' ) );
	}

	/**
	 * Get color value.
	 *
	 * @param string $key Color key.
	 * @return string
	 */
	public function get_color( $key ) {
		$color = $this->addon->get_setting( $key, '' );
		return ! empty( $color ) ? $color : ( $this->defaults[ $key ] ?? '#000000' );
	}

	/**
	 * Get all colors.
	 *
	 * @return array
	 */
	public function get_all_colors() {
		$colors = array();
		foreach ( array_keys( $this->defaults ) as $key ) {
			$colors[ $key ] = $this->get_color( $key );
		}
		return $colors;
	}

	/**
	 * Add admin color CSS.
	 */
	public function add_admin_color_css() {
		$screen = get_current_screen();

		// Only on BookingX pages.
		if ( strpos( $screen->id, 'bkx' ) === false ) {
			return;
		}

		$colors = $this->get_all_colors();
		$css    = $this->generate_admin_css( $colors );

		if ( ! empty( $css ) ) {
			echo '<style type="text/css" id="bkx-white-label-colors">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Add frontend color CSS.
	 */
	public function add_frontend_color_css() {
		$colors = $this->get_all_colors();
		$css    = $this->generate_css_variables( $colors );

		if ( ! empty( $css ) ) {
			echo '<style type="text/css" id="bkx-white-label-colors">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Generate CSS variables.
	 *
	 * @param array $colors Color values.
	 * @return string
	 */
	public function generate_css_variables( array $colors ) {
		$css = ':root {';

		foreach ( $colors as $key => $value ) {
			$var_name = str_replace( '_', '-', $key );
			$css     .= sprintf( '--bkx-%s: %s;', $var_name, $value );

			// Add RGB version for opacity support.
			$rgb  = $this->hex_to_rgb( $value );
			$css .= sprintf( '--bkx-%s-rgb: %s;', $var_name, implode( ', ', $rgb ) );
		}

		$css .= '}';

		return $css;
	}

	/**
	 * Generate admin CSS.
	 *
	 * @param array $colors Color values.
	 * @return string
	 */
	public function generate_admin_css( array $colors ) {
		$primary   = $colors['primary_color'];
		$secondary = $colors['secondary_color'];
		$accent    = $colors['accent_color'];
		$success   = $colors['success_color'];
		$warning   = $colors['warning_color'];
		$error     = $colors['error_color'];

		$css = "
			/* Primary color overrides */
			.bkx-admin-page .button-primary,
			.bkx-settings-page .button-primary,
			.bkx-admin-page .page-title-action,
			.bkx-nav-tabs .nav-tab-active {
				background: {$primary} !important;
				border-color: {$primary} !important;
			}

			.bkx-admin-page .button-primary:hover,
			.bkx-settings-page .button-primary:hover {
				background: {$secondary} !important;
				border-color: {$secondary} !important;
			}

			.bkx-admin-page a,
			.bkx-settings-page a,
			.bkx-booking-status-link {
				color: {$primary};
			}

			.bkx-admin-page a:hover,
			.bkx-settings-page a:hover {
				color: {$secondary};
			}

			/* Status colors */
			.bkx-status-pending,
			.bkx-badge-pending {
				background: {$warning};
			}

			.bkx-status-completed,
			.bkx-status-ack,
			.bkx-badge-success {
				background: {$success};
			}

			.bkx-status-cancelled,
			.bkx-status-missed,
			.bkx-badge-error {
				background: {$error};
			}

			/* Accent colors */
			.bkx-card-highlight,
			.bkx-stat-card .bkx-stat-icon {
				color: {$accent};
			}

			/* Tab underline */
			.bkx-nav-tabs .nav-tab-active::after {
				background: {$primary};
			}

			/* Focus states */
			.bkx-admin-page input:focus,
			.bkx-admin-page select:focus,
			.bkx-admin-page textarea:focus {
				border-color: {$primary} !important;
				box-shadow: 0 0 0 1px {$primary} !important;
			}

			/* Calendar */
			.bkx-calendar .bkx-calendar-day.selected,
			.bkx-calendar .bkx-calendar-day:hover {
				background: {$primary};
			}

			.bkx-calendar .bkx-calendar-day.has-bookings::after {
				background: {$accent};
			}

			/* Progress bars */
			.bkx-progress-bar .bkx-progress-fill {
				background: {$primary};
			}

			/* Checkboxes and radio buttons */
			.bkx-admin-page input[type='checkbox']:checked::before,
			.bkx-admin-page input[type='radio']:checked::before {
				background: {$primary};
			}
		";

		return $css;
	}

	/**
	 * Generate frontend CSS.
	 *
	 * @return string
	 */
	public function generate_css() {
		$colors = $this->get_all_colors();
		$css    = $this->generate_css_variables( $colors );

		$primary   = $colors['primary_color'];
		$secondary = $colors['secondary_color'];
		$accent    = $colors['accent_color'];
		$success   = $colors['success_color'];
		$warning   = $colors['warning_color'];
		$error     = $colors['error_color'];
		$text      = $colors['text_color'];
		$bg        = $colors['background_color'];

		$css .= "
			/* BookingX Frontend Overrides */
			.bkx-booking-form {
				background: {$bg};
				color: {$text};
			}

			.bkx-booking-form .bkx-btn-primary,
			.bkx-booking-form button[type='submit'] {
				background: {$primary};
				border-color: {$primary};
				color: #fff;
			}

			.bkx-booking-form .bkx-btn-primary:hover,
			.bkx-booking-form button[type='submit']:hover {
				background: {$secondary};
				border-color: {$secondary};
			}

			.bkx-booking-form a {
				color: {$primary};
			}

			.bkx-booking-form a:hover {
				color: {$secondary};
			}

			/* Service cards */
			.bkx-service-card.selected,
			.bkx-seat-card.selected {
				border-color: {$primary};
			}

			.bkx-service-card:hover,
			.bkx-seat-card:hover {
				border-color: {$accent};
			}

			/* Time slots */
			.bkx-time-slot.selected {
				background: {$primary};
				border-color: {$primary};
				color: #fff;
			}

			.bkx-time-slot:hover {
				border-color: {$primary};
			}

			.bkx-time-slot.unavailable {
				background: #f5f5f5;
				color: #999;
			}

			/* Calendar */
			.bkx-calendar .bkx-day.selected {
				background: {$primary};
				color: #fff;
			}

			.bkx-calendar .bkx-day.available:hover {
				background: {$accent};
				color: #fff;
			}

			.bkx-calendar .bkx-day.unavailable {
				color: #ccc;
			}

			/* Progress steps */
			.bkx-progress-step.active,
			.bkx-progress-step.completed {
				background: {$primary};
				border-color: {$primary};
				color: #fff;
			}

			.bkx-progress-line.completed {
				background: {$primary};
			}

			/* Messages */
			.bkx-message.success {
				background: " . $this->lighten_color( $success, 40 ) . ";
				border-color: {$success};
				color: " . $this->darken_color( $success, 20 ) . ";
			}

			.bkx-message.warning {
				background: " . $this->lighten_color( $warning, 40 ) . ";
				border-color: {$warning};
				color: " . $this->darken_color( $warning, 20 ) . ";
			}

			.bkx-message.error {
				background: " . $this->lighten_color( $error, 40 ) . ";
				border-color: {$error};
				color: " . $this->darken_color( $error, 20 ) . ";
			}

			/* Form inputs */
			.bkx-booking-form input:focus,
			.bkx-booking-form select:focus,
			.bkx-booking-form textarea:focus {
				border-color: {$primary};
				box-shadow: 0 0 0 2px " . $this->lighten_color( $primary, 30 ) . ";
			}

			/* Price display */
			.bkx-price,
			.bkx-total-price {
				color: {$primary};
			}

			/* Extras selection */
			.bkx-extra-item.selected {
				border-color: {$primary};
				background: " . $this->lighten_color( $primary, 45 ) . ";
			}

			/* Booking confirmation */
			.bkx-confirmation-icon {
				color: {$success};
			}

			.bkx-confirmation-message {
				color: {$text};
			}
		";

		return $css;
	}

	/**
	 * Filter inline styles.
	 *
	 * @param string $styles Existing styles.
	 * @return string
	 */
	public function filter_inline_styles( $styles ) {
		$custom_css = $this->generate_css();
		return $styles . "\n" . $custom_css;
	}

	/**
	 * Convert hex color to RGB array.
	 *
	 * @param string $hex Hex color.
	 * @return array RGB values.
	 */
	public function hex_to_rgb( $hex ) {
		$hex = ltrim( $hex, '#' );

		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return array(
			hexdec( substr( $hex, 0, 2 ) ),
			hexdec( substr( $hex, 2, 2 ) ),
			hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Lighten a color.
	 *
	 * @param string $hex    Hex color.
	 * @param int    $percent Percentage to lighten.
	 * @return string
	 */
	public function lighten_color( $hex, $percent ) {
		$rgb = $this->hex_to_rgb( $hex );

		foreach ( $rgb as &$color ) {
			$color = min( 255, $color + ( 255 - $color ) * ( $percent / 100 ) );
			$color = round( $color );
		}

		return sprintf( '#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2] );
	}

	/**
	 * Darken a color.
	 *
	 * @param string $hex    Hex color.
	 * @param int    $percent Percentage to darken.
	 * @return string
	 */
	public function darken_color( $hex, $percent ) {
		$rgb = $this->hex_to_rgb( $hex );

		foreach ( $rgb as &$color ) {
			$color = max( 0, $color - $color * ( $percent / 100 ) );
			$color = round( $color );
		}

		return sprintf( '#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2] );
	}

	/**
	 * Get contrasting text color.
	 *
	 * @param string $hex Background hex color.
	 * @return string White or black hex.
	 */
	public function get_contrast_color( $hex ) {
		$rgb = $this->hex_to_rgb( $hex );

		// Calculate luminance.
		$luminance = ( 0.299 * $rgb[0] + 0.587 * $rgb[1] + 0.114 * $rgb[2] ) / 255;

		return $luminance > 0.5 ? '#000000' : '#ffffff';
	}
}
