<?php
/**
 * Template Service.
 *
 * @package BookingX\WhatsAppBusiness\Services
 * @since   1.0.0
 */

namespace BookingX\WhatsAppBusiness\Services;

defined( 'ABSPATH' ) || exit;

/**
 * TemplateService class.
 *
 * Manages WhatsApp message templates.
 *
 * @since 1.0.0
 */
class TemplateService {

	/**
	 * API Provider.
	 *
	 * @var object
	 */
	private $provider;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param object $provider API provider instance.
	 * @param array  $settings Plugin settings.
	 */
	public function __construct( $provider, array $settings ) {
		$this->provider = $provider;
		$this->settings = $settings;
	}

	/**
	 * Sync templates from API.
	 *
	 * @since 1.0.0
	 *
	 * @return array|\WP_Error Templates or error.
	 */
	public function sync_templates() {
		$result = $this->provider->get_templates();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$templates = $result['data'] ?? $result['templates'] ?? array();

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_whatsapp_templates';

		foreach ( $templates as $template ) {
			$name        = $template['name'] ?? '';
			$template_id = $template['id'] ?? '';
			$status      = $template['status'] ?? 'pending';
			$category    = $template['category'] ?? 'UTILITY';
			$language    = $template['language'] ?? 'en';

			// Parse components.
			$header_type    = null;
			$header_content = null;
			$body_content   = '';
			$footer_content = null;
			$buttons        = null;
			$variables      = array();

			$components = $template['components'] ?? array();
			foreach ( $components as $component ) {
				$type = $component['type'] ?? '';

				switch ( $type ) {
					case 'HEADER':
						$header_type    = $component['format'] ?? 'TEXT';
						$header_content = $component['text'] ?? '';
						break;

					case 'BODY':
						$body_content = $component['text'] ?? '';
						// Extract variables.
						if ( preg_match_all( '/\{\{(\d+)\}\}/', $body_content, $matches ) ) {
							$variables = $matches[1];
						}
						break;

					case 'FOOTER':
						$footer_content = $component['text'] ?? '';
						break;

					case 'BUTTONS':
						$buttons = wp_json_encode( $component['buttons'] ?? array() );
						break;
				}
			}

			// Check if exists.
			$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"SELECT id FROM %i WHERE name = %s",
					$table,
					$name
				)
			);

			$data = array(
				'template_id'    => $template_id,
				'name'           => $name,
				'category'       => $category,
				'language'       => $language,
				'status'         => strtolower( $status ),
				'header_type'    => $header_type,
				'header_content' => $header_content,
				'body_content'   => $body_content,
				'footer_content' => $footer_content,
				'buttons'        => $buttons,
				'variables'      => wp_json_encode( $variables ),
				'synced_at'      => current_time( 'mysql' ),
			);

			if ( $existing ) {
				$wpdb->update( $table, $data, array( 'id' => $existing ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			} else {
				$wpdb->insert( $table, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			}
		}

		return $this->get_templates();
	}

	/**
	 * Get templates from database.
	 *
	 * @since 1.0.0
	 *
	 * @param string $status Filter by status.
	 * @return array Templates.
	 */
	public function get_templates( $status = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_whatsapp_templates';
		$where = '';

		if ( ! empty( $status ) ) {
			$where = $wpdb->prepare( 'WHERE status = %s', $status );
		}

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i {$where} ORDER BY name ASC",
				$table
			)
		);
	}

	/**
	 * Get template by name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Template name.
	 * @return object|null Template or null.
	 */
	public function get_template( $name ) {
		global $wpdb;

		$table = $wpdb->prefix . 'bkx_whatsapp_templates';

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM %i WHERE name = %s",
				$table,
				$name
			)
		);
	}

	/**
	 * Create a template.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Template data.
	 * @return array|\WP_Error Response or error.
	 */
	public function create_template( $data ) {
		$components = array();

		// Header.
		if ( ! empty( $data['header_type'] ) ) {
			$header = array(
				'type'   => 'HEADER',
				'format' => strtoupper( $data['header_type'] ),
			);

			if ( 'TEXT' === strtoupper( $data['header_type'] ) ) {
				$header['text'] = $data['header_content'] ?? '';
			}

			$components[] = $header;
		}

		// Body.
		$components[] = array(
			'type' => 'BODY',
			'text' => $data['body_content'] ?? '',
		);

		// Footer.
		if ( ! empty( $data['footer_content'] ) ) {
			$components[] = array(
				'type' => 'FOOTER',
				'text' => $data['footer_content'],
			);
		}

		// Buttons.
		if ( ! empty( $data['buttons'] ) ) {
			$buttons = json_decode( $data['buttons'], true );
			if ( ! empty( $buttons ) ) {
				$components[] = array(
					'type'    => 'BUTTONS',
					'buttons' => $buttons,
				);
			}
		}

		$template_data = array(
			'name'       => sanitize_title( $data['name'] ?? '' ),
			'category'   => $data['category'] ?? 'UTILITY',
			'language'   => $data['language'] ?? 'en',
			'components' => $components,
		);

		$result = $this->provider->create_template( $template_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Save to database.
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_whatsapp_templates';

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'template_id'    => $result['id'] ?? '',
				'name'           => $template_data['name'],
				'category'       => $template_data['category'],
				'language'       => $template_data['language'],
				'status'         => 'pending',
				'header_type'    => $data['header_type'] ?? null,
				'header_content' => $data['header_content'] ?? null,
				'body_content'   => $data['body_content'] ?? '',
				'footer_content' => $data['footer_content'] ?? null,
				'buttons'        => $data['buttons'] ?? null,
				'variables'      => $data['variables'] ?? null,
			)
		);

		return $result;
	}

	/**
	 * Delete a template.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Template name.
	 * @return true|\WP_Error True or error.
	 */
	public function delete_template( $name ) {
		$result = $this->provider->delete_template( $name );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_whatsapp_templates';

		$wpdb->delete( $table, array( 'name' => $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return true;
	}

	/**
	 * Get template categories.
	 *
	 * @since 1.0.0
	 *
	 * @return array Categories.
	 */
	public function get_categories() {
		return array(
			'UTILITY'        => __( 'Utility', 'bkx-whatsapp-business' ),
			'MARKETING'      => __( 'Marketing', 'bkx-whatsapp-business' ),
			'AUTHENTICATION' => __( 'Authentication', 'bkx-whatsapp-business' ),
		);
	}

	/**
	 * Get booking-related templates.
	 *
	 * @since 1.0.0
	 *
	 * @return array Templates.
	 */
	public function get_booking_templates() {
		return $this->get_templates( 'approved' );
	}
}
