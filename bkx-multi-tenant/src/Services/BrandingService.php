<?php
/**
 * Branding Service.
 *
 * @package BookingX\MultiTenant\Services
 */

namespace BookingX\MultiTenant\Services;

defined( 'ABSPATH' ) || exit;

/**
 * BrandingService class.
 */
class BrandingService {

	/**
	 * Current tenant.
	 *
	 * @var object|null
	 */
	private $tenant = null;

	/**
	 * Set tenant.
	 *
	 * @param object $tenant Tenant.
	 */
	public function set_tenant( $tenant ) {
		$this->tenant = $tenant;
	}

	/**
	 * Get branding settings.
	 *
	 * @param int|null $tenant_id Tenant ID.
	 * @return array
	 */
	public function get_branding( $tenant_id = null ) {
		if ( ! $tenant_id && $this->tenant ) {
			$tenant_id = $this->tenant->id;
		}

		if ( ! $tenant_id ) {
			return $this->get_default_branding();
		}

		global $wpdb;

		$branding = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT branding FROM {$wpdb->base_prefix}bkx_tenants WHERE id = %d",
				$tenant_id
			)
		);

		$branding = json_decode( $branding, true ) ?: array();

		return wp_parse_args( $branding, $this->get_default_branding() );
	}

	/**
	 * Get default branding.
	 *
	 * @return array
	 */
	public function get_default_branding() {
		return array(
			'logo_url'         => '',
			'favicon_url'      => '',
			'primary_color'    => '#2563eb',
			'secondary_color'  => '#1e40af',
			'accent_color'     => '#10b981',
			'text_color'       => '#1f2937',
			'background_color' => '#ffffff',
			'button_style'     => 'rounded',
			'font_family'      => 'system',
			'custom_css'       => '',
			'hide_powered_by'  => false,
			'white_label'      => false,
		);
	}

	/**
	 * Update branding.
	 *
	 * @param int   $tenant_id Tenant ID.
	 * @param array $branding  Branding settings.
	 * @return bool
	 */
	public function update_branding( $tenant_id, $branding ) {
		global $wpdb;

		$current = $this->get_branding( $tenant_id );
		$branding = wp_parse_args( $branding, $current );

		// Sanitize values.
		$branding['logo_url']         = esc_url_raw( $branding['logo_url'] );
		$branding['favicon_url']      = esc_url_raw( $branding['favicon_url'] );
		$branding['primary_color']    = sanitize_hex_color( $branding['primary_color'] );
		$branding['secondary_color']  = sanitize_hex_color( $branding['secondary_color'] );
		$branding['accent_color']     = sanitize_hex_color( $branding['accent_color'] );
		$branding['text_color']       = sanitize_hex_color( $branding['text_color'] );
		$branding['background_color'] = sanitize_hex_color( $branding['background_color'] );
		$branding['button_style']     = sanitize_text_field( $branding['button_style'] );
		$branding['font_family']      = sanitize_text_field( $branding['font_family'] );
		$branding['custom_css']       = wp_strip_all_tags( $branding['custom_css'] );
		$branding['hide_powered_by']  = (bool) $branding['hide_powered_by'];
		$branding['white_label']      = (bool) $branding['white_label'];

		return $wpdb->update(
			$wpdb->base_prefix . 'bkx_tenants',
			array( 'branding' => wp_json_encode( $branding ) ),
			array( 'id' => $tenant_id )
		) !== false;
	}

	/**
	 * Apply custom CSS.
	 */
	public function apply_custom_css() {
		if ( ! $this->tenant ) {
			return;
		}

		$branding = $this->get_branding();

		$css = ":root {\n";
		$css .= "  --bkx-primary-color: {$branding['primary_color']};\n";
		$css .= "  --bkx-secondary-color: {$branding['secondary_color']};\n";
		$css .= "  --bkx-accent-color: {$branding['accent_color']};\n";
		$css .= "  --bkx-text-color: {$branding['text_color']};\n";
		$css .= "  --bkx-background-color: {$branding['background_color']};\n";
		$css .= "}\n";

		// Button styles.
		$border_radius = '4px';
		switch ( $branding['button_style'] ) {
			case 'rounded':
				$border_radius = '8px';
				break;
			case 'pill':
				$border_radius = '50px';
				break;
			case 'square':
				$border_radius = '0';
				break;
		}
		$css .= ".bkx-btn { border-radius: {$border_radius}; }\n";

		// Font family.
		if ( 'system' !== $branding['font_family'] ) {
			$css .= ".bkx-booking-form { font-family: '{$branding['font_family']}', sans-serif; }\n";
		}

		// Custom CSS.
		if ( ! empty( $branding['custom_css'] ) ) {
			$css .= $branding['custom_css'] . "\n";
		}

		echo '<style id="bkx-tenant-branding">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get logo URL.
	 *
	 * @param int|null $tenant_id Tenant ID.
	 * @return string
	 */
	public function get_logo_url( $tenant_id = null ) {
		$branding = $this->get_branding( $tenant_id );
		return $branding['logo_url'];
	}

	/**
	 * Check if white label is enabled.
	 *
	 * @param int|null $tenant_id Tenant ID.
	 * @return bool
	 */
	public function is_white_label( $tenant_id = null ) {
		$branding = $this->get_branding( $tenant_id );

		// Check if tenant has white label feature.
		$plan_manager = \BookingX\MultiTenant\MultiTenantAddon::get_instance()->get_service( 'plan_manager' );

		if ( $tenant_id && ! $plan_manager->tenant_has_feature( $tenant_id, 'white_label' ) ) {
			return false;
		}

		return $branding['white_label'];
	}

	/**
	 * Generate CSS variables string.
	 *
	 * @param int|null $tenant_id Tenant ID.
	 * @return string
	 */
	public function get_css_variables( $tenant_id = null ) {
		$branding = $this->get_branding( $tenant_id );

		return sprintf(
			'--bkx-primary-color: %s; --bkx-secondary-color: %s; --bkx-accent-color: %s;',
			$branding['primary_color'],
			$branding['secondary_color'],
			$branding['accent_color']
		);
	}
}
