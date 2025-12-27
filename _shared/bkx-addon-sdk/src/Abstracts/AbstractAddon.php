<?php
/**
 * Abstract Addon Base Class
 *
 * Provides the foundation for all BookingX add-ons with common functionality
 * for initialization, dependency checking, licensing, and WordPress integration.
 *
 * @package    BookingX\AddonSDK
 * @subpackage Abstracts
 * @since      1.0.0
 */

namespace BookingX\AddonSDK\Abstracts;

use BookingX\AddonSDK\Contracts\AddonInterface;
use BookingX\AddonSDK\Services\LicenseService;
use BookingX\AddonSDK\Services\LoggerService;

/**
 * Abstract base class for all BookingX add-ons.
 *
 * @since 1.0.0
 */
abstract class AbstractAddon implements AddonInterface {

    /**
     * Unique identifier for this add-on.
     *
     * @var string
     */
    protected string $addon_id;

    /**
     * Display name of the add-on.
     *
     * @var string
     */
    protected string $addon_name;

    /**
     * Current version of the add-on.
     *
     * @var string
     */
    protected string $version;

    /**
     * Text domain for translations.
     *
     * @var string
     */
    protected string $text_domain;

    /**
     * Minimum required BookingX Core version.
     *
     * @var string
     */
    protected string $min_bkx_version = '2.0.0';

    /**
     * Minimum required PHP version.
     *
     * @var string
     */
    protected string $min_php_version = '7.4';

    /**
     * Minimum required WordPress version.
     *
     * @var string
     */
    protected string $min_wp_version = '5.8';

    /**
     * Path to the main plugin file.
     *
     * @var string
     */
    protected string $plugin_file;

    /**
     * Plugin directory path.
     *
     * @var string
     */
    protected string $plugin_path;

    /**
     * Plugin directory URL.
     *
     * @var string
     */
    protected string $plugin_url;

    /**
     * License service instance.
     *
     * @var LicenseService|null
     */
    protected ?LicenseService $license_service = null;

    /**
     * Logger service instance.
     *
     * @var LoggerService|null
     */
    protected ?LoggerService $logger = null;

    /**
     * Whether the add-on has been initialized.
     *
     * @var bool
     */
    protected bool $initialized = false;

    /**
     * Array of required add-on dependencies.
     *
     * @var array
     */
    protected array $required_addons = [];

    /**
     * Constructor.
     *
     * @param string $plugin_file Path to the main plugin file.
     */
    public function __construct( string $plugin_file ) {
        $this->plugin_file = $plugin_file;
        $this->plugin_path = plugin_dir_path( $plugin_file );
        $this->plugin_url  = plugin_dir_url( $plugin_file );
    }

    /**
     * Initialize the add-on.
     *
     * This is the main entry point that should be called from the main plugin file.
     *
     * @since 1.0.0
     * @return bool Whether initialization was successful.
     */
    public function init(): bool {
        if ( $this->initialized ) {
            return true;
        }

        // Initialize logger early
        $this->logger = new LoggerService( $this->addon_id );

        // Check all dependencies
        if ( ! $this->check_dependencies() ) {
            return false;
        }

        // Check license
        $this->license_service = new LicenseService( $this );
        if ( ! $this->is_license_valid() && ! $this->is_in_trial() ) {
            $this->show_license_notice();
            // Still allow admin initialization for license entry
            if ( is_admin() ) {
                $this->init_admin_license_only();
            }
            return false;
        }

        // Run database migrations
        $this->run_migrations();

        // Register with BookingX Framework
        $this->register_with_framework();

        // Initialize hooks
        $this->init_hooks();

        // Initialize admin
        if ( is_admin() ) {
            $this->init_admin();
        }

        // Initialize frontend
        if ( ! is_admin() || wp_doing_ajax() ) {
            $this->init_frontend();
        }

        // Initialize REST API
        add_action( 'rest_api_init', [ $this, 'init_rest_api' ] );

        // Mark as initialized
        $this->initialized = true;

        // Fire action for other add-ons to hook into
        do_action( "bookingx_addon_{$this->addon_id}_initialized", $this );

        $this->logger->info( "Add-on {$this->addon_name} v{$this->version} initialized successfully." );

        return true;
    }

    /**
     * Check if all dependencies are met.
     *
     * @since 1.0.0
     * @return bool Whether all dependencies are satisfied.
     */
    protected function check_dependencies(): bool {
        // Check PHP version
        if ( version_compare( PHP_VERSION, $this->min_php_version, '<' ) ) {
            add_action( 'admin_notices', [ $this, 'php_version_notice' ] );
            return false;
        }

        // Check WordPress version
        global $wp_version;
        if ( version_compare( $wp_version, $this->min_wp_version, '<' ) ) {
            add_action( 'admin_notices', [ $this, 'wp_version_notice' ] );
            return false;
        }

        // Check if BookingX Core is active
        if ( ! $this->is_bookingx_active() ) {
            add_action( 'admin_notices', [ $this, 'missing_bookingx_notice' ] );
            return false;
        }

        // Check BookingX version
        if ( ! $this->is_bookingx_version_compatible() ) {
            add_action( 'admin_notices', [ $this, 'incompatible_version_notice' ] );
            return false;
        }

        // Check required add-ons
        foreach ( $this->required_addons as $addon_id => $addon_name ) {
            if ( ! $this->is_addon_active( $addon_id ) ) {
                add_action( 'admin_notices', function() use ( $addon_name ) {
                    $this->missing_addon_notice( $addon_name );
                } );
                return false;
            }
        }

        return true;
    }

    /**
     * Check if BookingX Core is active.
     *
     * @since 1.0.0
     * @return bool
     */
    protected function is_bookingx_active(): bool {
        return function_exists( 'BKX' ) || class_exists( 'Bookingx' );
    }

    /**
     * Check if BookingX Core version is compatible.
     *
     * @since 1.0.0
     * @return bool
     */
    protected function is_bookingx_version_compatible(): bool {
        if ( ! function_exists( 'BKX' ) ) {
            return false;
        }

        $bkx = BKX();
        $bkx_version = $bkx->version ?? '1.0.0';

        return version_compare( $bkx_version, $this->min_bkx_version, '>=' );
    }

    /**
     * Check if a specific add-on is active.
     *
     * @since 1.0.0
     * @param string $addon_id The add-on ID to check.
     * @return bool
     */
    protected function is_addon_active( string $addon_id ): bool {
        return apply_filters( "bookingx_addon_{$addon_id}_active", false );
    }

    /**
     * Check if the license is valid.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_license_valid(): bool {
        if ( ! $this->license_service ) {
            return false;
        }

        return $this->license_service->is_valid();
    }

    /**
     * Check if the add-on is in trial mode.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_in_trial(): bool {
        if ( ! $this->license_service ) {
            return false;
        }

        return $this->license_service->is_in_trial();
    }

    /**
     * Run database migrations.
     *
     * @since 1.0.0
     * @return void
     */
    protected function run_migrations(): void {
        $migrations = $this->get_migrations();
        if ( empty( $migrations ) ) {
            return;
        }

        $installed_version = get_option( "{$this->addon_id}_db_version", '0.0.0' );

        foreach ( $migrations as $version => $migration_classes ) {
            if ( version_compare( $installed_version, $version, '<' ) ) {
                foreach ( $migration_classes as $migration_class ) {
                    if ( class_exists( $migration_class ) ) {
                        $migration = new $migration_class();
                        if ( method_exists( $migration, 'up' ) ) {
                            $migration->up();
                            $this->logger->info( "Ran migration: {$migration_class}" );
                        }
                    }
                }
                update_option( "{$this->addon_id}_db_version", $version );
            }
        }
    }

    /**
     * Initialize admin-only license functionality.
     *
     * This is called when the license is invalid to allow license entry.
     *
     * @since 1.0.0
     * @return void
     */
    protected function init_admin_license_only(): void {
        add_action( 'admin_menu', [ $this, 'register_license_menu' ], 100 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Register the license menu item.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_license_menu(): void {
        // This will be handled by the BookingX core license page
        // Add-ons register via the addon info function
    }

    /**
     * Enqueue admin assets.
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_admin_assets(): void {
        $screen = get_current_screen();
        if ( ! $screen ) {
            return;
        }

        // Enqueue SDK admin styles
        wp_enqueue_style(
            'bkx-addon-sdk-admin',
            $this->get_sdk_url() . 'assets/css/bkx-addon-admin.css',
            [],
            $this->version
        );

        // Enqueue SDK admin scripts
        wp_enqueue_script(
            'bkx-addon-sdk-admin',
            $this->get_sdk_url() . 'assets/js/bkx-addon-admin.js',
            [ 'jquery' ],
            $this->version,
            true
        );

        // Localize script
        wp_localize_script( 'bkx-addon-sdk-admin', 'bkxAddonSDK', [
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'bkx_addon_sdk_nonce' ),
            'addonId'   => $this->addon_id,
            'addonName' => $this->addon_name,
            'i18n'      => [
                'saving'        => __( 'Saving...', 'bkx-addon-sdk' ),
                'saved'         => __( 'Saved!', 'bkx-addon-sdk' ),
                'error'         => __( 'An error occurred.', 'bkx-addon-sdk' ),
                'confirmDelete' => __( 'Are you sure you want to delete this?', 'bkx-addon-sdk' ),
            ],
        ] );
    }

    /**
     * Get the SDK directory URL.
     *
     * @since 1.0.0
     * @return string
     */
    protected function get_sdk_url(): string {
        // SDK is in the vendor directory or shared location
        return plugin_dir_url( dirname( __DIR__, 2 ) . '/bkx-addon-sdk.php' );
    }

    /**
     * Get add-on info for the BookingX license system.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_addon_info(): array {
        return [
            'addon_name'  => $this->addon_name,
            'key_name'    => "{$this->addon_id}_license_key",
            'status'      => "{$this->addon_id}_license_status",
            'text_domain' => $this->text_domain,
        ];
    }

    /**
     * Get the add-on ID.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_id(): string {
        return $this->addon_id;
    }

    /**
     * Get the add-on name.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_name(): string {
        return $this->addon_name;
    }

    /**
     * Get the add-on version.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_version(): string {
        return $this->version;
    }

    /**
     * Get the text domain.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_text_domain(): string {
        return $this->text_domain;
    }

    /**
     * Get the plugin file path.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_plugin_file(): string {
        return $this->plugin_file;
    }

    /**
     * Get the plugin directory path.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_plugin_path(): string {
        return $this->plugin_path;
    }

    /**
     * Get the plugin directory URL.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_plugin_url(): string {
        return $this->plugin_url;
    }

    /**
     * Get the logger instance.
     *
     * @since 1.0.0
     * @return LoggerService|null
     */
    public function get_logger(): ?LoggerService {
        return $this->logger;
    }

    /**
     * Show license notice.
     *
     * @since 1.0.0
     * @return void
     */
    public function show_license_notice(): void {
        add_action( 'admin_notices', function() {
            $license_url = admin_url( 'edit.php?post_type=bkx_booking&page=bkx_settings&tab=licence' );
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php echo esc_html( $this->addon_name ); ?>:</strong>
                    <?php
                    printf(
                        /* translators: %s: License settings URL */
                        esc_html__( 'Please enter a valid license key to activate this add-on. %s', 'bkx-addon-sdk' ),
                        '<a href="' . esc_url( $license_url ) . '">' . esc_html__( 'Enter License Key', 'bkx-addon-sdk' ) . '</a>'
                    );
                    ?>
                </p>
            </div>
            <?php
        } );
    }

    /**
     * Show PHP version notice.
     *
     * @since 1.0.0
     * @return void
     */
    public function php_version_notice(): void {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php echo esc_html( $this->addon_name ); ?>:</strong>
                <?php
                printf(
                    /* translators: 1: Required PHP version, 2: Current PHP version */
                    esc_html__( 'This add-on requires PHP %1$s or higher. You are running PHP %2$s.', 'bkx-addon-sdk' ),
                    esc_html( $this->min_php_version ),
                    esc_html( PHP_VERSION )
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Show WordPress version notice.
     *
     * @since 1.0.0
     * @return void
     */
    public function wp_version_notice(): void {
        global $wp_version;
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php echo esc_html( $this->addon_name ); ?>:</strong>
                <?php
                printf(
                    /* translators: 1: Required WordPress version, 2: Current WordPress version */
                    esc_html__( 'This add-on requires WordPress %1$s or higher. You are running WordPress %2$s.', 'bkx-addon-sdk' ),
                    esc_html( $this->min_wp_version ),
                    esc_html( $wp_version )
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Show missing BookingX notice.
     *
     * @since 1.0.0
     * @return void
     */
    public function missing_bookingx_notice(): void {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php echo esc_html( $this->addon_name ); ?>:</strong>
                <?php esc_html_e( 'This add-on requires the BookingX plugin to be installed and activated.', 'bkx-addon-sdk' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Show incompatible version notice.
     *
     * @since 1.0.0
     * @return void
     */
    public function incompatible_version_notice(): void {
        $bkx = function_exists( 'BKX' ) ? BKX() : null;
        $current_version = $bkx ? ( $bkx->version ?? '1.0.0' ) : 'unknown';
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php echo esc_html( $this->addon_name ); ?>:</strong>
                <?php
                printf(
                    /* translators: 1: Required BookingX version, 2: Current BookingX version */
                    esc_html__( 'This add-on requires BookingX %1$s or higher. You are running BookingX %2$s.', 'bkx-addon-sdk' ),
                    esc_html( $this->min_bkx_version ),
                    esc_html( $current_version )
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Show missing add-on notice.
     *
     * @since 1.0.0
     * @param string $addon_name Name of the required add-on.
     * @return void
     */
    public function missing_addon_notice( string $addon_name ): void {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php echo esc_html( $this->addon_name ); ?>:</strong>
                <?php
                printf(
                    /* translators: %s: Required add-on name */
                    esc_html__( 'This add-on requires %s to be installed and activated.', 'bkx-addon-sdk' ),
                    '<strong>' . esc_html( $addon_name ) . '</strong>'
                );
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Register with BookingX Framework registries.
     *
     * Override this method to register payment gateways, notification providers, etc.
     *
     * @since 1.0.0
     * @return void
     */
    abstract protected function register_with_framework(): void;

    /**
     * Initialize WordPress hooks.
     *
     * @since 1.0.0
     * @return void
     */
    abstract protected function init_hooks(): void;

    /**
     * Initialize admin functionality.
     *
     * @since 1.0.0
     * @return void
     */
    abstract protected function init_admin(): void;

    /**
     * Initialize frontend functionality.
     *
     * @since 1.0.0
     * @return void
     */
    abstract protected function init_frontend(): void;

    /**
     * Initialize REST API endpoints.
     *
     * @since 1.0.0
     * @return void
     */
    public function init_rest_api(): void {
        // Override in child class to register REST routes
    }

    /**
     * Get database migrations.
     *
     * Return an associative array where keys are version numbers and values
     * are arrays of migration class names to run for that version.
     *
     * Example:
     * [
     *     '1.0.0' => [
     *         CreateTransactionsTable::class,
     *         CreateRefundsTable::class,
     *     ],
     *     '1.1.0' => [
     *         AddIndexesToTransactions::class,
     *     ],
     * ]
     *
     * @since 1.0.0
     * @return array
     */
    abstract public function get_migrations(): array;
}
