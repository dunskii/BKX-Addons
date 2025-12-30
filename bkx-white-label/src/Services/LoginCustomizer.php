<?php
/**
 * Login Customizer Service.
 *
 * Handles WordPress login page customization.
 *
 * @package BookingX\WhiteLabel
 */

namespace BookingX\WhiteLabel\Services;

defined( 'ABSPATH' ) || exit;

/**
 * LoginCustomizer class.
 */
class LoginCustomizer {

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
	 * Initialize login customizer hooks.
	 */
	public function init() {
		// Only apply if login customization is configured.
		$logo       = $this->addon->get_setting( 'login_logo', '' );
		$background = $this->addon->get_setting( 'login_background', '' );
		$custom_css = $this->addon->get_setting( 'login_custom_css', '' );

		if ( empty( $logo ) && empty( $background ) && empty( $custom_css ) ) {
			return;
		}

		// Login page styles.
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_styles' ) );

		// Login logo URL.
		add_filter( 'login_headerurl', array( $this, 'login_logo_url' ) );

		// Login logo title.
		add_filter( 'login_headertext', array( $this, 'login_logo_title' ) );
	}

	/**
	 * Enqueue login page styles.
	 */
	public function enqueue_login_styles() {
		$logo         = $this->addon->get_setting( 'login_logo', '' );
		$background   = $this->addon->get_setting( 'login_background', '' );
		$custom_css   = $this->addon->get_setting( 'login_custom_css', '' );
		$primary      = $this->addon->get_setting( 'primary_color', '#2271b1' );
		$accent       = $this->addon->get_setting( 'accent_color', '#72aee6' );

		$css = '';

		// Background image.
		if ( ! empty( $background ) ) {
			$css .= "
				body.login {
					background-image: url('" . esc_url( $background ) . "');
					background-size: cover;
					background-position: center;
					background-repeat: no-repeat;
					background-attachment: fixed;
				}
			";
		}

		// Logo.
		if ( ! empty( $logo ) ) {
			$css .= "
				#login h1 a,
				.login h1 a {
					background-image: url('" . esc_url( $logo ) . "');
					background-size: contain;
					background-repeat: no-repeat;
					background-position: center;
					width: 100%;
					height: 100px;
					margin-bottom: 20px;
				}
			";
		}

		// Primary color styling.
		if ( ! empty( $primary ) ) {
			$css .= "
				.login #backtoblog a,
				.login #nav a,
				.login .message a {
					color: {$primary};
				}

				.login #backtoblog a:hover,
				.login #nav a:hover,
				.login .message a:hover {
					color: {$accent};
				}

				.wp-core-ui .button-primary {
					background: {$primary};
					border-color: {$primary};
				}

				.wp-core-ui .button-primary:hover,
				.wp-core-ui .button-primary:focus {
					background: {$accent};
					border-color: {$accent};
				}

				input[type='checkbox']:checked::before {
					content: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath fill='{$primary}' d='M14.83 4.89l1.34.94-5.81 8.38H9.02L5.78 9.67l1.34-1.25 2.57 2.4z'/%3E%3C/svg%3E\");
				}

				input[type='text']:focus,
				input[type='password']:focus,
				input[type='email']:focus {
					border-color: {$primary};
					box-shadow: 0 0 0 1px {$primary};
				}
			";
		}

		// Form styling.
		$css .= "
			.login form {
				border-radius: 8px;
				box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
			}

			.login #login_error,
			.login .message,
			.login .success {
				border-radius: 4px;
			}
		";

		// Custom CSS.
		if ( ! empty( $custom_css ) ) {
			$css .= "\n" . $custom_css;
		}

		if ( ! empty( $css ) ) {
			echo '<style type="text/css">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Get login logo URL.
	 *
	 * @param string $url Original URL.
	 * @return string
	 */
	public function login_logo_url( $url ) {
		$brand_url = $this->addon->get_setting( 'brand_url', '' );

		if ( ! empty( $brand_url ) ) {
			return $brand_url;
		}

		return home_url();
	}

	/**
	 * Get login logo title.
	 *
	 * @param string $title Original title.
	 * @return string
	 */
	public function login_logo_title( $title ) {
		$brand_name = $this->addon->get_setting( 'brand_name', '' );

		if ( ! empty( $brand_name ) ) {
			return $brand_name;
		}

		return get_bloginfo( 'name' );
	}

	/**
	 * Get login preview HTML.
	 *
	 * @return string
	 */
	public function get_preview_html() {
		$logo       = $this->addon->get_setting( 'login_logo', '' );
		$background = $this->addon->get_setting( 'login_background', '' );
		$brand_name = $this->addon->get_setting( 'brand_name', 'BookingX' );
		$primary    = $this->addon->get_setting( 'primary_color', '#2271b1' );

		$bg_style = ! empty( $background )
			? "background-image: url('" . esc_url( $background ) . "'); background-size: cover; background-position: center;"
			: 'background: #f0f0f1;';

		$logo_html = ! empty( $logo )
			? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $brand_name ) . '" style="max-width: 200px; height: auto; margin-bottom: 20px;">'
			: '<div style="font-size: 24px; font-weight: bold; margin-bottom: 20px; color: ' . esc_attr( $primary ) . ';">' . esc_html( $brand_name ) . '</div>';

		return '
		<div style="width: 100%; height: 400px; ' . $bg_style . ' display: flex; align-items: center; justify-content: center; border-radius: 8px;">
			<div style="background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); text-align: center; width: 320px;">
				' . $logo_html . '
				<div style="margin-bottom: 15px;">
					<input type="text" placeholder="Username" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
				</div>
				<div style="margin-bottom: 20px;">
					<input type="password" placeholder="Password" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
				</div>
				<button style="width: 100%; padding: 12px; background: ' . esc_attr( $primary ) . '; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 600;">
					' . esc_html__( 'Log In', 'bkx-white-label' ) . '
				</button>
			</div>
		</div>';
	}
}
