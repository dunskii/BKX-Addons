<?php
/**
 * Admin Settings Page.
 *
 * @package BookingX\WooCommercePro\Admin
 * @since   1.0.0
 */

namespace BookingX\WooCommercePro\Admin;

use BookingX\WooCommercePro\Services\SyncService;

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
	private $option_name = 'bkx_woocommerce_settings';

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
		add_action( 'wp_ajax_bkx_woo_sync_products', array( $this, 'ajax_sync_products' ) );
		add_action( 'wp_ajax_bkx_woo_get_sync_stats', array( $this, 'ajax_get_sync_stats' ) );
	}

	/**
	 * Add menu page.
	 */
	public function add_menu_page() {
		add_submenu_page(
			'edit.php?post_type=bkx_booking',
			__( 'WooCommerce Integration', 'bkx-woocommerce-pro' ),
			__( 'WooCommerce', 'bkx-woocommerce-pro' ),
			'manage_options',
			'bkx-woocommerce',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'bkx_woocommerce_settings', $this->option_name, array( $this, 'sanitize_settings' ) );

		// General Section.
		add_settings_section(
			'bkx_woo_general',
			__( 'General Settings', 'bkx-woocommerce-pro' ),
			array( $this, 'section_general' ),
			'bkx-woocommerce'
		);

		add_settings_field(
			'enabled',
			__( 'Enable Integration', 'bkx-woocommerce-pro' ),
			array( $this, 'field_checkbox' ),
			'bkx-woocommerce',
			'bkx_woo_general',
			array(
				'id'          => 'enabled',
				'description' => __( 'Enable WooCommerce integration for BookingX.', 'bkx-woocommerce-pro' ),
			)
		);

		add_settings_field(
			'checkout_flow',
			__( 'Checkout Flow', 'bkx-woocommerce-pro' ),
			array( $this, 'field_select' ),
			'bkx-woocommerce',
			'bkx_woo_general',
			array(
				'id'          => 'checkout_flow',
				'options'     => array(
					'woocommerce' => __( 'WooCommerce Checkout', 'bkx-woocommerce-pro' ),
					'bookingx'    => __( 'BookingX Checkout', 'bkx-woocommerce-pro' ),
					'hybrid'      => __( 'Hybrid (User Chooses)', 'bkx-woocommerce-pro' ),
				),
				'description' => __( 'Select how bookings should be processed at checkout.', 'bkx-woocommerce-pro' ),
			)
		);

		// Cart Section.
		add_settings_section(
			'bkx_woo_cart',
			__( 'Cart Settings', 'bkx-woocommerce-pro' ),
			array( $this, 'section_cart' ),
			'bkx-woocommerce'
		);

		add_settings_field(
			'add_to_cart',
			__( 'Add to Cart Button', 'bkx-woocommerce-pro' ),
			array( $this, 'field_checkbox' ),
			'bkx-woocommerce',
			'bkx_woo_cart',
			array(
				'id'          => 'add_to_cart',
				'description' => __( 'Show Add to Cart button on booking forms.', 'bkx-woocommerce-pro' ),
			)
		);

		add_settings_field(
			'cart_behavior',
			__( 'After Add to Cart', 'bkx-woocommerce-pro' ),
			array( $this, 'field_select' ),
			'bkx-woocommerce',
			'bkx_woo_cart',
			array(
				'id'      => 'cart_behavior',
				'options' => array(
					'redirect' => __( 'Redirect to Cart', 'bkx-woocommerce-pro' ),
					'checkout' => __( 'Redirect to Checkout', 'bkx-woocommerce-pro' ),
					'stay'     => __( 'Stay on Page', 'bkx-woocommerce-pro' ),
				),
			)
		);

		// Sync Section.
		add_settings_section(
			'bkx_woo_sync',
			__( 'Synchronization', 'bkx-woocommerce-pro' ),
			array( $this, 'section_sync' ),
			'bkx-woocommerce'
		);

		add_settings_field(
			'sync_inventory',
			__( 'Auto-Sync Products', 'bkx-woocommerce-pro' ),
			array( $this, 'field_checkbox' ),
			'bkx-woocommerce',
			'bkx_woo_sync',
			array(
				'id'          => 'sync_inventory',
				'description' => __( 'Automatically sync service changes to products.', 'bkx-woocommerce-pro' ),
			)
		);

		add_settings_field(
			'auto_create_products',
			__( 'Auto-Create Products', 'bkx-woocommerce-pro' ),
			array( $this, 'field_checkbox' ),
			'bkx-woocommerce',
			'bkx_woo_sync',
			array(
				'id'          => 'auto_create_products',
				'description' => __( 'Automatically create WooCommerce products for new services.', 'bkx-woocommerce-pro' ),
			)
		);

		// Order Section.
		add_settings_section(
			'bkx_woo_orders',
			__( 'Order Settings', 'bkx-woocommerce-pro' ),
			array( $this, 'section_orders' ),
			'bkx-woocommerce'
		);

		add_settings_field(
			'create_orders',
			__( 'Create Orders', 'bkx-woocommerce-pro' ),
			array( $this, 'field_checkbox' ),
			'bkx-woocommerce',
			'bkx_woo_orders',
			array(
				'id'          => 'create_orders',
				'description' => __( 'Create WooCommerce orders for BookingX bookings.', 'bkx-woocommerce-pro' ),
			)
		);

		add_settings_field(
			'order_status_mapping',
			__( 'Status Mapping', 'bkx-woocommerce-pro' ),
			array( $this, 'field_status_mapping' ),
			'bkx-woocommerce',
			'bkx_woo_orders',
			array(
				'id' => 'order_status_mapping',
			)
		);

		// Features Section.
		add_settings_section(
			'bkx_woo_features',
			__( 'Features', 'bkx-woocommerce-pro' ),
			array( $this, 'section_features' ),
			'bkx-woocommerce'
		);

		add_settings_field(
			'display_in_account',
			__( 'My Account Tab', 'bkx-woocommerce-pro' ),
			array( $this, 'field_checkbox' ),
			'bkx-woocommerce',
			'bkx_woo_features',
			array(
				'id'          => 'display_in_account',
				'description' => __( 'Add a Bookings tab to My Account page.', 'bkx-woocommerce-pro' ),
			)
		);

		add_settings_field(
			'enable_coupons',
			__( 'Allow Coupons', 'bkx-woocommerce-pro' ),
			array( $this, 'field_checkbox' ),
			'bkx-woocommerce',
			'bkx_woo_features',
			array(
				'id'          => 'enable_coupons',
				'description' => __( 'Allow WooCommerce coupons for booking products.', 'bkx-woocommerce-pro' ),
			)
		);

		add_settings_field(
			'email_integration',
			__( 'Email Integration', 'bkx-woocommerce-pro' ),
			array( $this, 'field_checkbox' ),
			'bkx-woocommerce',
			'bkx_woo_features',
			array(
				'id'          => 'email_integration',
				'description' => __( 'Use WooCommerce email templates for booking notifications.', 'bkx-woocommerce-pro' ),
			)
		);

		add_settings_field(
			'bundle_services',
			__( 'Bundle Services', 'bkx-woocommerce-pro' ),
			array( $this, 'field_checkbox' ),
			'bkx-woocommerce',
			'bkx_woo_features',
			array(
				'id'          => 'bundle_services',
				'description' => __( 'Allow booking multiple services in one order.', 'bkx-woocommerce-pro' ),
			)
		);
	}

	/**
	 * Section: General.
	 */
	public function section_general() {
		echo '<p>' . esc_html__( 'Configure general integration settings.', 'bkx-woocommerce-pro' ) . '</p>';
	}

	/**
	 * Section: Cart.
	 */
	public function section_cart() {
		echo '<p>' . esc_html__( 'Configure cart and checkout behavior.', 'bkx-woocommerce-pro' ) . '</p>';
	}

	/**
	 * Section: Sync.
	 */
	public function section_sync() {
		echo '<p>' . esc_html__( 'Configure synchronization between BookingX and WooCommerce.', 'bkx-woocommerce-pro' ) . '</p>';
	}

	/**
	 * Section: Orders.
	 */
	public function section_orders() {
		echo '<p>' . esc_html__( 'Configure order creation and status synchronization.', 'bkx-woocommerce-pro' ) . '</p>';
	}

	/**
	 * Section: Features.
	 */
	public function section_features() {
		echo '<p>' . esc_html__( 'Enable or disable additional features.', 'bkx-woocommerce-pro' ) . '</p>';
	}

	/**
	 * Field: Checkbox.
	 *
	 * @param array $args Field arguments.
	 */
	public function field_checkbox( $args ) {
		$settings = get_option( $this->option_name, array() );
		$value    = isset( $settings[ $args['id'] ] ) ? $settings[ $args['id'] ] : false;
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
	 * Field: Select.
	 *
	 * @param array $args Field arguments.
	 */
	public function field_select( $args ) {
		$settings = get_option( $this->option_name, array() );
		$value    = isset( $settings[ $args['id'] ] ) ? $settings[ $args['id'] ] : '';
		$options  = isset( $args['options'] ) ? $args['options'] : array();
		$desc     = isset( $args['description'] ) ? $args['description'] : '';

		printf(
			'<select name="%s[%s]">',
			esc_attr( $this->option_name ),
			esc_attr( $args['id'] )
		);

		foreach ( $options as $key => $label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $key ),
				selected( $value, $key, false ),
				esc_html( $label )
			);
		}

		echo '</select>';

		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
	}

	/**
	 * Field: Status Mapping.
	 *
	 * @param array $args Field arguments.
	 */
	public function field_status_mapping( $args ) {
		$settings = get_option( $this->option_name, array() );
		$mapping  = isset( $settings['order_status_mapping'] ) ? $settings['order_status_mapping'] : array();

		$woo_statuses = wc_get_order_statuses();
		$bkx_statuses = array(
			'bkx-pending'   => __( 'Pending', 'bkx-woocommerce-pro' ),
			'bkx-ack'       => __( 'Acknowledged', 'bkx-woocommerce-pro' ),
			'bkx-completed' => __( 'Completed', 'bkx-woocommerce-pro' ),
			'bkx-cancelled' => __( 'Cancelled', 'bkx-woocommerce-pro' ),
			'bkx-missed'    => __( 'Missed', 'bkx-woocommerce-pro' ),
		);

		echo '<table class="bkx-status-mapping">';
		echo '<thead><tr><th>' . esc_html__( 'WooCommerce Status', 'bkx-woocommerce-pro' ) . '</th><th>' . esc_html__( 'BookingX Status', 'bkx-woocommerce-pro' ) . '</th></tr></thead>';
		echo '<tbody>';

		foreach ( $woo_statuses as $woo_status => $woo_label ) {
			$status_key = str_replace( 'wc-', '', $woo_status );
			$current    = isset( $mapping[ $status_key ] ) ? $mapping[ $status_key ] : '';

			echo '<tr>';
			echo '<td>' . esc_html( $woo_label ) . '</td>';
			echo '<td>';

			printf(
				'<select name="%s[order_status_mapping][%s]">',
				esc_attr( $this->option_name ),
				esc_attr( $status_key )
			);

			echo '<option value="">' . esc_html__( '— No Change —', 'bkx-woocommerce-pro' ) . '</option>';

			foreach ( $bkx_statuses as $bkx_status => $bkx_label ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $bkx_status ),
					selected( $current, $bkx_status, false ),
					esc_html( $bkx_label )
				);
			}

			echo '</select>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
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
		$checkboxes = array( 'enabled', 'add_to_cart', 'sync_inventory', 'auto_create_products', 'create_orders', 'display_in_account', 'enable_coupons', 'email_integration', 'bundle_services' );
		foreach ( $checkboxes as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		// Selects.
		$sanitized['checkout_flow'] = isset( $input['checkout_flow'] ) ? sanitize_text_field( $input['checkout_flow'] ) : 'woocommerce';
		$sanitized['cart_behavior'] = isset( $input['cart_behavior'] ) ? sanitize_text_field( $input['cart_behavior'] ) : 'redirect';

		// Status mapping.
		if ( isset( $input['order_status_mapping'] ) && is_array( $input['order_status_mapping'] ) ) {
			$sanitized['order_status_mapping'] = array_map( 'sanitize_text_field', $input['order_status_mapping'] );
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

		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'settings';
		?>
		<div class="wrap bkx-woo-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper">
				<a href="?post_type=bkx_booking&page=bkx-woocommerce&tab=settings" class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'bkx-woocommerce-pro' ); ?>
				</a>
				<a href="?post_type=bkx_booking&page=bkx-woocommerce&tab=sync" class="nav-tab <?php echo 'sync' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Sync Status', 'bkx-woocommerce-pro' ); ?>
				</a>
				<a href="?post_type=bkx_booking&page=bkx-woocommerce&tab=products" class="nav-tab <?php echo 'products' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Products', 'bkx-woocommerce-pro' ); ?>
				</a>
			</nav>

			<div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'sync':
						$this->render_sync_tab();
						break;
					case 'products':
						$this->render_products_tab();
						break;
					default:
						$this->render_settings_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings tab.
	 */
	private function render_settings_tab() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'bkx_woocommerce_settings' );
			do_settings_sections( 'bkx-woocommerce' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render sync tab.
	 */
	private function render_sync_tab() {
		$sync_service = SyncService::get_instance();
		$stats        = $sync_service->get_sync_stats();
		?>
		<div class="bkx-sync-dashboard">
			<div class="bkx-sync-stats">
				<div class="stat-card">
					<h3><?php esc_html_e( 'Services', 'bkx-woocommerce-pro' ); ?></h3>
					<div class="stat-value"><?php echo esc_html( $stats['total_services'] ); ?></div>
				</div>
				<div class="stat-card">
					<h3><?php esc_html_e( 'Products', 'bkx-woocommerce-pro' ); ?></h3>
					<div class="stat-value"><?php echo esc_html( $stats['total_products'] ); ?></div>
				</div>
				<div class="stat-card">
					<h3><?php esc_html_e( 'Synced', 'bkx-woocommerce-pro' ); ?></h3>
					<div class="stat-value"><?php echo esc_html( $stats['synced_services'] ); ?></div>
				</div>
				<div class="stat-card">
					<h3><?php esc_html_e( 'Unsynced', 'bkx-woocommerce-pro' ); ?></h3>
					<div class="stat-value <?php echo $stats['unsynced_services'] > 0 ? 'warning' : ''; ?>">
						<?php echo esc_html( $stats['unsynced_services'] ); ?>
					</div>
				</div>
			</div>

			<div class="bkx-sync-progress">
				<div class="progress-bar">
					<div class="progress-fill" style="width: <?php echo esc_attr( $stats['sync_percentage'] ); ?>%"></div>
				</div>
				<p class="progress-text">
					<?php
					printf(
						/* translators: %d: percentage */
						esc_html__( '%d%% Synchronized', 'bkx-woocommerce-pro' ),
						$stats['sync_percentage']
					);
					?>
				</p>
			</div>

			<div class="bkx-sync-actions">
				<button type="button" class="button button-primary" id="bkx-sync-all">
					<?php esc_html_e( 'Sync All Products', 'bkx-woocommerce-pro' ); ?>
				</button>
				<button type="button" class="button" id="bkx-refresh-stats">
					<?php esc_html_e( 'Refresh Stats', 'bkx-woocommerce-pro' ); ?>
				</button>
			</div>

			<div id="bkx-sync-results" class="bkx-sync-results" style="display: none;"></div>
		</div>
		<?php
	}

	/**
	 * Render products tab.
	 */
	private function render_products_tab() {
		$sync_service = SyncService::get_instance();
		$report       = $sync_service->export_sync_report();
		?>
		<div class="bkx-products-list">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Service', 'bkx-woocommerce-pro' ); ?></th>
						<th><?php esc_html_e( 'Service Price', 'bkx-woocommerce-pro' ); ?></th>
						<th><?php esc_html_e( 'Product', 'bkx-woocommerce-pro' ); ?></th>
						<th><?php esc_html_e( 'Product Price', 'bkx-woocommerce-pro' ); ?></th>
						<th><?php esc_html_e( 'Status', 'bkx-woocommerce-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $report as $row ) : ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( $row['service_id'] ) ); ?>">
									<?php echo esc_html( $row['service_title'] ); ?>
								</a>
							</td>
							<td><?php echo wc_price( $row['service_price'] ); ?></td>
							<td>
								<?php if ( $row['product_id'] ) : ?>
									<a href="<?php echo esc_url( get_edit_post_link( $row['product_id'] ) ); ?>">
										#<?php echo esc_html( $row['product_id'] ); ?>
									</a>
								<?php else : ?>
									<span class="not-synced"><?php esc_html_e( 'Not synced', 'bkx-woocommerce-pro' ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php echo $row['product_price'] ? wc_price( $row['product_price'] ) : '—'; ?>
							</td>
							<td>
								<?php if ( $row['synced'] ) : ?>
									<?php if ( $row['price_match'] ) : ?>
										<span class="status-synced"><?php esc_html_e( 'Synced', 'bkx-woocommerce-pro' ); ?></span>
									<?php else : ?>
										<span class="status-mismatch"><?php esc_html_e( 'Price Mismatch', 'bkx-woocommerce-pro' ); ?></span>
									<?php endif; ?>
								<?php else : ?>
									<span class="status-unsynced"><?php esc_html_e( 'Unsynced', 'bkx-woocommerce-pro' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * AJAX: Sync products.
	 */
	public function ajax_sync_products() {
		check_ajax_referer( 'bkx_woo_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-woocommerce-pro' ) ) );
		}

		$sync_service = SyncService::get_instance();
		$results      = $sync_service->sync_all_products();

		wp_send_json_success( $results );
	}

	/**
	 * AJAX: Get sync stats.
	 */
	public function ajax_get_sync_stats() {
		check_ajax_referer( 'bkx_woo_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-woocommerce-pro' ) ) );
		}

		$sync_service = SyncService::get_instance();
		$stats        = $sync_service->get_sync_stats();

		wp_send_json_success( $stats );
	}
}
