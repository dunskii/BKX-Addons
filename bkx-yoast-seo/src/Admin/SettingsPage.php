<?php
/**
 * Admin Settings Page.
 *
 * @package BookingX\YoastSeo\Admin
 * @since   1.0.0
 */

namespace BookingX\YoastSeo\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SettingsPage Class.
 */
class SettingsPage {

	/**
	 * Instance.
	 *
	 * @var SettingsPage
	 */
	private static $instance = null;

	/**
	 * Settings key.
	 *
	 * @var string
	 */
	private $option_name = 'bkx_yoast_settings';

	/**
	 * Get instance.
	 *
	 * @return SettingsPage
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
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Add menu page.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'Yoast SEO Integration', 'bkx-yoast-seo' ),
			__( 'SEO Settings', 'bkx-yoast-seo' ),
			'manage_options',
			'bkx-yoast-seo',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue assets.
	 *
	 * @param string $hook Current admin page.
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'bkx-yoast-seo' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'bkx-yoast-admin',
			BKX_YOAST_URL . 'assets/css/admin.css',
			array(),
			BKX_YOAST_VERSION
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'bkx_yoast_settings', $this->option_name, array( $this, 'sanitize_settings' ) );

		// Schema Section.
		add_settings_section(
			'bkx_yoast_schema',
			__( 'Structured Data (Schema)', 'bkx-yoast-seo' ),
			array( $this, 'section_schema' ),
			'bkx-yoast-seo'
		);

		add_settings_field(
			'schema_service',
			__( 'Service Schema', 'bkx-yoast-seo' ),
			array( $this, 'field_checkbox' ),
			'bkx-yoast-seo',
			'bkx_yoast_schema',
			array(
				'id'          => 'schema_service',
				'description' => __( 'Add Service schema markup to service pages.', 'bkx-yoast-seo' ),
			)
		);

		add_settings_field(
			'schema_local_business',
			__( 'LocalBusiness Schema', 'bkx-yoast-seo' ),
			array( $this, 'field_checkbox' ),
			'bkx-yoast-seo',
			'bkx_yoast_schema',
			array(
				'id'          => 'schema_local_business',
				'description' => __( 'Add LocalBusiness schema to booking pages.', 'bkx-yoast-seo' ),
			)
		);

		// Meta Section.
		add_settings_section(
			'bkx_yoast_meta',
			__( 'Meta Tags', 'bkx-yoast-seo' ),
			array( $this, 'section_meta' ),
			'bkx-yoast-seo'
		);

		add_settings_field(
			'auto_meta_description',
			__( 'Auto Meta Descriptions', 'bkx-yoast-seo' ),
			array( $this, 'field_checkbox' ),
			'bkx-yoast-seo',
			'bkx_yoast_meta',
			array(
				'id'          => 'auto_meta_description',
				'description' => __( 'Auto-generate meta descriptions for services and staff.', 'bkx-yoast-seo' ),
			)
		);

		add_settings_field(
			'og_services',
			__( 'Open Graph', 'bkx-yoast-seo' ),
			array( $this, 'field_checkbox' ),
			'bkx-yoast-seo',
			'bkx_yoast_meta',
			array(
				'id'          => 'og_services',
				'description' => __( 'Enhance Open Graph meta for social sharing.', 'bkx-yoast-seo' ),
			)
		);

		add_settings_field(
			'twitter_cards',
			__( 'Twitter Cards', 'bkx-yoast-seo' ),
			array( $this, 'field_checkbox' ),
			'bkx-yoast-seo',
			'bkx_yoast_meta',
			array(
				'id'          => 'twitter_cards',
				'description' => __( 'Enhance Twitter Card meta for services.', 'bkx-yoast-seo' ),
			)
		);

		// Sitemap Section.
		add_settings_section(
			'bkx_yoast_sitemap',
			__( 'Sitemap Settings', 'bkx-yoast-seo' ),
			array( $this, 'section_sitemap' ),
			'bkx-yoast-seo'
		);

		add_settings_field(
			'sitemap_services',
			__( 'Include Services', 'bkx-yoast-seo' ),
			array( $this, 'field_checkbox' ),
			'bkx-yoast-seo',
			'bkx_yoast_sitemap',
			array(
				'id'          => 'sitemap_services',
				'description' => __( 'Include services in XML sitemap.', 'bkx-yoast-seo' ),
			)
		);

		add_settings_field(
			'sitemap_seats',
			__( 'Include Staff/Resources', 'bkx-yoast-seo' ),
			array( $this, 'field_checkbox' ),
			'bkx-yoast-seo',
			'bkx_yoast_sitemap',
			array(
				'id'          => 'sitemap_seats',
				'description' => __( 'Include staff/resources in XML sitemap.', 'bkx-yoast-seo' ),
			)
		);

		// Titles Section.
		add_settings_section(
			'bkx_yoast_titles',
			__( 'Title Templates', 'bkx-yoast-seo' ),
			array( $this, 'section_titles' ),
			'bkx-yoast-seo'
		);

		add_settings_field(
			'default_service_title',
			__( 'Service Title Template', 'bkx-yoast-seo' ),
			array( $this, 'field_text' ),
			'bkx-yoast-seo',
			'bkx_yoast_titles',
			array(
				'id'          => 'default_service_title',
				'description' => __( 'Variables: %service_name%, %price%, %duration%, %sitename%, %sep%', 'bkx-yoast-seo' ),
			)
		);

		add_settings_field(
			'default_seat_title',
			__( 'Staff Title Template', 'bkx-yoast-seo' ),
			array( $this, 'field_text' ),
			'bkx-yoast-seo',
			'bkx_yoast_titles',
			array(
				'id'          => 'default_seat_title',
				'description' => __( 'Variables: %seat_name%, %seat_alias%, %sitename%, %sep%', 'bkx-yoast-seo' ),
			)
		);

		// Other Section.
		add_settings_section(
			'bkx_yoast_other',
			__( 'Other Settings', 'bkx-yoast-seo' ),
			array( $this, 'section_other' ),
			'bkx-yoast-seo'
		);

		add_settings_field(
			'breadcrumbs',
			__( 'Breadcrumbs', 'bkx-yoast-seo' ),
			array( $this, 'field_checkbox' ),
			'bkx-yoast-seo',
			'bkx_yoast_other',
			array(
				'id'          => 'breadcrumbs',
				'description' => __( 'Enhance Yoast breadcrumbs for booking pages.', 'bkx-yoast-seo' ),
			)
		);

		add_settings_field(
			'canonical_urls',
			__( 'Canonical URLs', 'bkx-yoast-seo' ),
			array( $this, 'field_checkbox' ),
			'bkx-yoast-seo',
			'bkx_yoast_other',
			array(
				'id'          => 'canonical_urls',
				'description' => __( 'Ensure clean canonical URLs for booking pages.', 'bkx-yoast-seo' ),
			)
		);

		add_settings_field(
			'noindex_past_bookings',
			__( 'Noindex Past Bookings', 'bkx-yoast-seo' ),
			array( $this, 'field_checkbox' ),
			'bkx-yoast-seo',
			'bkx_yoast_other',
			array(
				'id'          => 'noindex_past_bookings',
				'description' => __( 'Set noindex for past booking pages to avoid indexing stale content.', 'bkx-yoast-seo' ),
			)
		);
	}

	/**
	 * Section: Schema.
	 */
	public function section_schema() {
		echo '<p>' . esc_html__( 'Configure structured data output for better search engine visibility.', 'bkx-yoast-seo' ) . '</p>';
	}

	/**
	 * Section: Meta.
	 */
	public function section_meta() {
		echo '<p>' . esc_html__( 'Configure meta tags for social sharing and search results.', 'bkx-yoast-seo' ) . '</p>';
	}

	/**
	 * Section: Sitemap.
	 */
	public function section_sitemap() {
		echo '<p>' . esc_html__( 'Configure which content types to include in the XML sitemap.', 'bkx-yoast-seo' ) . '</p>';
	}

	/**
	 * Section: Titles.
	 */
	public function section_titles() {
		echo '<p>' . esc_html__( 'Set default title templates for booking content.', 'bkx-yoast-seo' ) . '</p>';
	}

	/**
	 * Section: Other.
	 */
	public function section_other() {
		echo '<p>' . esc_html__( 'Additional SEO settings.', 'bkx-yoast-seo' ) . '</p>';
	}

	/**
	 * Field: Checkbox.
	 *
	 * @param array $args Field arguments.
	 */
	public function field_checkbox( $args ) {
		$settings = get_option( $this->option_name, array() );
		$value    = isset( $settings[ $args['id'] ] ) ? $settings[ $args['id'] ] : true;
		$desc     = isset( $args['description'] ) ? $args['description'] : '';

		printf(
			'<label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label>',
			esc_attr( $this->option_name ),
			esc_attr( $args['id'] ),
			checked( $value, true, false ),
			esc_html( $desc )
		);
	}

	/**
	 * Field: Text.
	 *
	 * @param array $args Field arguments.
	 */
	public function field_text( $args ) {
		$settings = get_option( $this->option_name, array() );
		$value    = isset( $settings[ $args['id'] ] ) ? $settings[ $args['id'] ] : '';
		$desc     = isset( $args['description'] ) ? $args['description'] : '';

		printf(
			'<input type="text" name="%s[%s]" value="%s" class="regular-text" />',
			esc_attr( $this->option_name ),
			esc_attr( $args['id'] ),
			esc_attr( $value )
		);

		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array $input Input values.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Checkboxes.
		$checkboxes = array(
			'enabled',
			'schema_service',
			'schema_local_business',
			'auto_meta_description',
			'og_services',
			'twitter_cards',
			'sitemap_services',
			'sitemap_seats',
			'breadcrumbs',
			'canonical_urls',
			'noindex_past_bookings',
		);

		foreach ( $checkboxes as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		// Text fields.
		if ( isset( $input['default_service_title'] ) ) {
			$sanitized['default_service_title'] = sanitize_text_field( $input['default_service_title'] );
		}

		if ( isset( $input['default_seat_title'] ) ) {
			$sanitized['default_seat_title'] = sanitize_text_field( $input['default_seat_title'] );
		}

		return $sanitized;
	}

	/**
	 * Render settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap bkx-yoast-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<div class="bkx-settings-intro">
				<p>
					<?php esc_html_e( 'Enhance your booking pages for search engines with Yoast SEO integration. These settings add structured data, optimize meta tags, and configure sitemap behavior.', 'bkx-yoast-seo' ); ?>
				</p>
			</div>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'bkx_yoast_settings' );
				do_settings_sections( 'bkx-yoast-seo' );
				submit_button();
				?>
			</form>

			<div class="bkx-settings-help">
				<h3><?php esc_html_e( 'Available Title Variables', 'bkx-yoast-seo' ); ?></h3>
				<table class="widefat">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Variable', 'bkx-yoast-seo' ); ?></th>
							<th><?php esc_html_e( 'Description', 'bkx-yoast-seo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code>%service_name%</code></td>
							<td><?php esc_html_e( 'The name of the service', 'bkx-yoast-seo' ); ?></td>
						</tr>
						<tr>
							<td><code>%seat_name%</code></td>
							<td><?php esc_html_e( 'The name of the staff/resource', 'bkx-yoast-seo' ); ?></td>
						</tr>
						<tr>
							<td><code>%seat_alias%</code></td>
							<td><?php esc_html_e( 'The alias for staff (e.g., "Staff", "Therapist")', 'bkx-yoast-seo' ); ?></td>
						</tr>
						<tr>
							<td><code>%price%</code></td>
							<td><?php esc_html_e( 'The service price', 'bkx-yoast-seo' ); ?></td>
						</tr>
						<tr>
							<td><code>%duration%</code></td>
							<td><?php esc_html_e( 'The service duration', 'bkx-yoast-seo' ); ?></td>
						</tr>
						<tr>
							<td><code>%sitename%</code></td>
							<td><?php esc_html_e( 'Your site name', 'bkx-yoast-seo' ); ?></td>
						</tr>
						<tr>
							<td><code>%sep%</code></td>
							<td><?php esc_html_e( 'Separator (dash)', 'bkx-yoast-seo' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
