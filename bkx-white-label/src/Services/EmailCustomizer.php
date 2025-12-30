<?php
/**
 * Email Customizer Service.
 *
 * Handles email template customization and branding.
 *
 * @package BookingX\WhiteLabel
 */

namespace BookingX\WhiteLabel\Services;

defined( 'ABSPATH' ) || exit;

/**
 * EmailCustomizer class.
 */
class EmailCustomizer {

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
	 * Initialize email hooks.
	 */
	public function init() {
		// Override from name and email.
		add_filter( 'wp_mail_from_name', array( $this, 'filter_from_name' ), 100 );
		add_filter( 'wp_mail_from', array( $this, 'filter_from_email' ), 100 );

		// Filter email content.
		add_filter( 'bkx_email_header', array( $this, 'filter_email_header' ) );
		add_filter( 'bkx_email_footer', array( $this, 'filter_email_footer' ) );
		add_filter( 'bkx_email_styles', array( $this, 'filter_email_styles' ) );

		// Replace branding in email content.
		add_filter( 'bkx_email_content', array( $this, 'filter_email_content' ) );
	}

	/**
	 * Filter from name.
	 *
	 * @param string $name Original name.
	 * @return string
	 */
	public function filter_from_name( $name ) {
		$custom_name = $this->addon->get_setting( 'email_from_name', '' );

		if ( ! empty( $custom_name ) ) {
			return $custom_name;
		}

		// Fall back to brand name if set.
		$brand_name = $this->addon->get_setting( 'brand_name', '' );
		if ( ! empty( $brand_name ) ) {
			return $brand_name;
		}

		return $name;
	}

	/**
	 * Filter from email.
	 *
	 * @param string $email Original email.
	 * @return string
	 */
	public function filter_from_email( $email ) {
		$custom_email = $this->addon->get_setting( 'email_from_address', '' );

		if ( ! empty( $custom_email ) && is_email( $custom_email ) ) {
			return $custom_email;
		}

		return $email;
	}

	/**
	 * Filter email header.
	 *
	 * @param string $header Original header.
	 * @return string
	 */
	public function filter_email_header( $header ) {
		$header_image = $this->addon->get_setting( 'email_header_image', '' );
		$brand_name   = $this->addon->get_setting( 'brand_name', '' );
		$brand_url    = $this->addon->get_setting( 'brand_url', '' );

		if ( empty( $header_image ) && empty( $brand_name ) ) {
			return $header;
		}

		$custom_header = '<div class="bkx-email-header" style="text-align: center; padding: 20px 0;">';

		if ( ! empty( $header_image ) ) {
			$img = '<img src="' . esc_url( $header_image ) . '" alt="' . esc_attr( $brand_name ?: 'Logo' ) . '" style="max-width: 200px; height: auto;">';

			if ( ! empty( $brand_url ) ) {
				$custom_header .= '<a href="' . esc_url( $brand_url ) . '" target="_blank">' . $img . '</a>';
			} else {
				$custom_header .= $img;
			}
		} elseif ( ! empty( $brand_name ) ) {
			$custom_header .= '<h1 style="margin: 0; font-size: 28px;">' . esc_html( $brand_name ) . '</h1>';
		}

		$custom_header .= '</div>';

		return $custom_header;
	}

	/**
	 * Filter email footer.
	 *
	 * @param string $footer Original footer.
	 * @return string
	 */
	public function filter_email_footer( $footer ) {
		$footer_text = $this->addon->get_setting( 'email_footer_text', '' );

		if ( empty( $footer_text ) ) {
			// Generate default footer with brand info.
			$brand_name = $this->addon->get_setting( 'brand_name', '' );
			$brand_url  = $this->addon->get_setting( 'brand_url', '' );

			if ( ! empty( $brand_name ) ) {
				$footer_text = sprintf(
					/* translators: %s: Brand name */
					__( '&copy; %1$s %2$s. All rights reserved.', 'bkx-white-label' ),
					gmdate( 'Y' ),
					$brand_name
				);
			} else {
				return $footer;
			}
		}

		$custom_footer = '<div class="bkx-email-footer" style="text-align: center; padding: 20px 0; font-size: 12px; color: #666;">';
		$custom_footer .= wp_kses_post( $footer_text );
		$custom_footer .= '</div>';

		return $custom_footer;
	}

	/**
	 * Filter email styles.
	 *
	 * @param string $styles Original styles.
	 * @return string
	 */
	public function filter_email_styles( $styles ) {
		$bg_color   = $this->addon->get_setting( 'email_background_color', '#f7f7f7' );
		$body_color = $this->addon->get_setting( 'email_body_color', '#ffffff' );
		$text_color = $this->addon->get_setting( 'email_text_color', '#636363' );
		$link_color = $this->addon->get_setting( 'email_link_color', '#2271b1' );

		$custom_styles = "
			body, .bkx-email-wrapper {
				background-color: {$bg_color} !important;
			}
			.bkx-email-body, .bkx-email-content {
				background-color: {$body_color} !important;
				color: {$text_color} !important;
			}
			.bkx-email-body a, .bkx-email-content a {
				color: {$link_color} !important;
			}
			.bkx-email-button {
				background-color: {$link_color} !important;
			}
			.bkx-email-button:hover {
				opacity: 0.9;
			}
		";

		return $styles . "\n" . $custom_styles;
	}

	/**
	 * Filter email content.
	 *
	 * @param string $content Original content.
	 * @return string
	 */
	public function filter_email_content( $content ) {
		$brand_name = $this->addon->get_setting( 'brand_name', '' );

		// Replace BookingX branding.
		if ( ! empty( $brand_name ) ) {
			$content = str_replace( 'BookingX', $brand_name, $content );
		}

		// Apply additional string replacements.
		$replacements = $this->addon->get_setting( 'replace_strings', array() );
		if ( ! empty( $replacements ) ) {
			foreach ( $replacements as $item ) {
				if ( ! empty( $item['search'] ) ) {
					$content = str_replace( $item['search'], $item['replace'] ?? '', $content );
				}
			}
		}

		return $content;
	}

	/**
	 * Get email preview HTML.
	 *
	 * @return string
	 */
	public function get_preview_html() {
		$brand_name  = $this->addon->get_setting( 'brand_name', 'BookingX' );
		$bg_color    = $this->addon->get_setting( 'email_background_color', '#f7f7f7' );
		$body_color  = $this->addon->get_setting( 'email_body_color', '#ffffff' );
		$text_color  = $this->addon->get_setting( 'email_text_color', '#636363' );
		$link_color  = $this->addon->get_setting( 'email_link_color', '#2271b1' );
		$header_img  = $this->addon->get_setting( 'email_header_image', '' );
		$footer_text = $this->addon->get_setting( 'email_footer_text', '' );

		if ( empty( $footer_text ) ) {
			$footer_text = sprintf(
				/* translators: %1$s: Year, %2$s: Brand name */
				__( '&copy; %1$s %2$s. All rights reserved.', 'bkx-white-label' ),
				gmdate( 'Y' ),
				$brand_name
			);
		}

		$html = '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: ' . esc_attr( $bg_color ) . ';">
	<table width="100%" cellpadding="0" cellspacing="0" style="background-color: ' . esc_attr( $bg_color ) . '; padding: 40px 20px;">
		<tr>
			<td align="center">
				<table width="600" cellpadding="0" cellspacing="0" style="background-color: ' . esc_attr( $body_color ) . '; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
					<!-- Header -->
					<tr>
						<td style="padding: 30px; text-align: center; border-bottom: 1px solid #eee;">';

		if ( ! empty( $header_img ) ) {
			$html .= '<img src="' . esc_url( $header_img ) . '" alt="' . esc_attr( $brand_name ) . '" style="max-width: 200px; height: auto;">';
		} else {
			$html .= '<h1 style="margin: 0; font-size: 28px; color: ' . esc_attr( $link_color ) . ';">' . esc_html( $brand_name ) . '</h1>';
		}

		$html .= '
						</td>
					</tr>
					<!-- Content -->
					<tr>
						<td style="padding: 40px 30px; color: ' . esc_attr( $text_color ) . ';">
							<h2 style="margin: 0 0 20px; color: #333;">' . esc_html__( 'Booking Confirmation', 'bkx-white-label' ) . '</h2>
							<p style="margin: 0 0 15px; line-height: 1.6;">' . esc_html__( 'Thank you for your booking! Here are the details:', 'bkx-white-label' ) . '</p>

							<table width="100%" style="margin: 20px 0; border-collapse: collapse;">
								<tr>
									<td style="padding: 10px; border-bottom: 1px solid #eee; color: #666;">' . esc_html__( 'Service:', 'bkx-white-label' ) . '</td>
									<td style="padding: 10px; border-bottom: 1px solid #eee;">' . esc_html__( 'Premium Consultation', 'bkx-white-label' ) . '</td>
								</tr>
								<tr>
									<td style="padding: 10px; border-bottom: 1px solid #eee; color: #666;">' . esc_html__( 'Date:', 'bkx-white-label' ) . '</td>
									<td style="padding: 10px; border-bottom: 1px solid #eee;">' . esc_html( gmdate( 'F j, Y' ) ) . '</td>
								</tr>
								<tr>
									<td style="padding: 10px; border-bottom: 1px solid #eee; color: #666;">' . esc_html__( 'Time:', 'bkx-white-label' ) . '</td>
									<td style="padding: 10px; border-bottom: 1px solid #eee;">10:00 AM - 11:00 AM</td>
								</tr>
								<tr>
									<td style="padding: 10px; color: #666;">' . esc_html__( 'Total:', 'bkx-white-label' ) . '</td>
									<td style="padding: 10px; font-weight: bold; color: ' . esc_attr( $link_color ) . ';">$150.00</td>
								</tr>
							</table>

							<p style="margin: 20px 0; text-align: center;">
								<a href="#" style="display: inline-block; padding: 12px 30px; background-color: ' . esc_attr( $link_color ) . '; color: #fff; text-decoration: none; border-radius: 5px; font-weight: bold;">' . esc_html__( 'View Booking', 'bkx-white-label' ) . '</a>
							</p>

							<p style="margin: 0; line-height: 1.6; font-size: 14px; color: #999;">' . esc_html__( 'If you have any questions, please don\'t hesitate to contact us.', 'bkx-white-label' ) . '</p>
						</td>
					</tr>
					<!-- Footer -->
					<tr>
						<td style="padding: 20px 30px; text-align: center; background-color: #f9f9f9; border-top: 1px solid #eee; font-size: 12px; color: #999;">
							' . wp_kses_post( $footer_text ) . '
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';

		return $html;
	}

	/**
	 * Get email color settings.
	 *
	 * @return array
	 */
	public function get_email_colors() {
		return array(
			'background' => $this->addon->get_setting( 'email_background_color', '#f7f7f7' ),
			'body'       => $this->addon->get_setting( 'email_body_color', '#ffffff' ),
			'text'       => $this->addon->get_setting( 'email_text_color', '#636363' ),
			'link'       => $this->addon->get_setting( 'email_link_color', '#2271b1' ),
		);
	}
}
