<?php
/**
 * Code Generator Service.
 *
 * @package BookingX\DeveloperSDK
 */

namespace BookingX\DeveloperSDK\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class CodeGenerator
 *
 * Generates code snippets and boilerplate for BookingX development.
 */
class CodeGenerator {

	/**
	 * Generate code based on type and parameters.
	 *
	 * @param string $type   Template type.
	 * @param array  $params Parameters.
	 * @return string|WP_Error Generated code or error.
	 */
	public function generate( string $type, array $params ) {
		$method = 'generate_' . str_replace( '-', '_', $type );

		if ( ! method_exists( $this, $method ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid code template type.', 'bkx-developer-sdk' ) );
		}

		return $this->$method( $params );
	}

	/**
	 * Generate add-on boilerplate.
	 *
	 * @param array $params Parameters.
	 * @return string Generated code.
	 */
	private function generate_addon( array $params ): string {
		$name        = $params['name'] ?? 'My Add-on';
		$slug        = $params['slug'] ?? sanitize_title( $name );
		$namespace   = $params['namespace'] ?? 'MyAddon';
		$description = $params['description'] ?? 'A BookingX add-on.';

		$class_name = str_replace( ' ', '', ucwords( str_replace( '-', ' ', $slug ) ) );

		return <<<PHP
<?php
/**
 * Plugin Name: BookingX {$name}
 * Description: {$description}
 * Version: 1.0.0
 * Author: Your Name
 * Requires PHP: 7.4
 * Requires Plugins: bookingx
 *
 * @package BookingX\\{$namespace}
 */

namespace BookingX\\{$namespace};

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'BKX_{$class_name}_VERSION', '1.0.0' );
define( 'BKX_{$class_name}_FILE', __FILE__ );
define( 'BKX_{$class_name}_PATH', plugin_dir_path( __FILE__ ) );
define( 'BKX_{$class_name}_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Add-on Class.
 */
class {$class_name}Addon {

    /**
     * Singleton instance.
     *
     * @var {$class_name}Addon
     */
    private static \$instance = null;

    /**
     * Get singleton instance.
     *
     * @return {$class_name}Addon
     */
    public static function get_instance(): {$class_name}Addon {
        if ( null === self::\$instance ) {
            self::\$instance = new self();
        }
        return self::\$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        \$this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks(): void {
        // Add your hooks here.
        add_action( 'bkx_booking_created', array( \$this, 'on_booking_created' ), 10, 2 );
    }

    /**
     * Handle booking created event.
     *
     * @param int   \$booking_id   Booking ID.
     * @param array \$booking_data Booking data.
     */
    public function on_booking_created( int \$booking_id, array \$booking_data ): void {
        // Your logic here.
    }
}

/**
 * Initialize the add-on.
 */
function init_{$slug}() {
    if ( ! class_exists( 'Bookingx' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="notice notice-error"><p>BookingX {$name} requires BookingX plugin.</p></div>';
        });
        return;
    }
    {$class_name}Addon::get_instance();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\init_{$slug}', 20 );
PHP;
	}

	/**
	 * Generate payment gateway.
	 *
	 * @param array $params Parameters.
	 * @return string Generated code.
	 */
	private function generate_payment_gateway( array $params ): string {
		$name    = $params['name'] ?? 'Custom Gateway';
		$slug    = $params['slug'] ?? sanitize_title( $name );
		$api_url = $params['api_url'] ?? 'https://api.example.com';

		$class_name = str_replace( ' ', '', ucwords( str_replace( '-', ' ', $slug ) ) ) . 'Gateway';

		return <<<PHP
<?php
/**
 * {$name} Payment Gateway.
 *
 * @package BookingX\\PaymentGateways
 */

namespace BookingX\\PaymentGateways;

defined( 'ABSPATH' ) || exit;

/**
 * Class {$class_name}
 */
class {$class_name} extends AbstractPaymentGateway {

    /**
     * Gateway ID.
     *
     * @var string
     */
    protected \$id = '{$slug}';

    /**
     * Gateway title.
     *
     * @var string
     */
    protected \$title = '{$name}';

    /**
     * API URL.
     *
     * @var string
     */
    protected \$api_url = '{$api_url}';

    /**
     * Initialize the gateway.
     */
    public function __construct() {
        parent::__construct();

        \$this->supports = array(
            'payments',
            'refunds',
            'subscriptions',
        );
    }

    /**
     * Process payment.
     *
     * @param int   \$booking_id Booking ID.
     * @param array \$data       Payment data.
     * @return array Result with 'success' and 'redirect' or 'error'.
     */
    public function process_payment( int \$booking_id, array \$data ): array {
        \$booking = get_post( \$booking_id );
        \$amount  = get_post_meta( \$booking_id, 'booking_total', true );

        // Make API request.
        \$response = wp_remote_post(
            \$this->api_url . '/charges',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . \$this->get_api_key(),
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( array(
                    'amount'      => \$amount * 100, // Convert to cents.
                    'currency'    => \$this->get_currency(),
                    'description' => sprintf( 'Booking #%d', \$booking_id ),
                    'metadata'    => array(
                        'booking_id' => \$booking_id,
                    ),
                ) ),
            )
        );

        if ( is_wp_error( \$response ) ) {
            return array(
                'success' => false,
                'error'   => \$response->get_error_message(),
            );
        }

        \$body = json_decode( wp_remote_retrieve_body( \$response ), true );

        if ( ! empty( \$body['id'] ) ) {
            update_post_meta( \$booking_id, '_transaction_id', \$body['id'] );

            return array(
                'success'  => true,
                'redirect' => \$this->get_return_url( \$booking_id ),
            );
        }

        return array(
            'success' => false,
            'error'   => \$body['error']['message'] ?? __( 'Payment failed.', 'bkx-developer-sdk' ),
        );
    }

    /**
     * Process refund.
     *
     * @param int    \$booking_id Booking ID.
     * @param float  \$amount     Amount to refund.
     * @param string \$reason     Refund reason.
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function process_refund( int \$booking_id, float \$amount, string \$reason = '' ) {
        \$transaction_id = get_post_meta( \$booking_id, '_transaction_id', true );

        if ( ! \$transaction_id ) {
            return new \\WP_Error( 'no_transaction', __( 'No transaction ID found.', 'bkx-developer-sdk' ) );
        }

        \$response = wp_remote_post(
            \$this->api_url . '/refunds',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . \$this->get_api_key(),
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( array(
                    'charge' => \$transaction_id,
                    'amount' => \$amount * 100,
                    'reason' => \$reason,
                ) ),
            )
        );

        if ( is_wp_error( \$response ) ) {
            return \$response;
        }

        \$body = json_decode( wp_remote_retrieve_body( \$response ), true );

        return ! empty( \$body['id'] );
    }

    /**
     * Get settings fields.
     *
     * @return array Settings fields.
     */
    public function get_settings_fields(): array {
        return array(
            'enabled'    => array(
                'type'    => 'checkbox',
                'label'   => __( 'Enable {$name}', 'bkx-developer-sdk' ),
                'default' => false,
            ),
            'title'      => array(
                'type'    => 'text',
                'label'   => __( 'Title', 'bkx-developer-sdk' ),
                'default' => '{$name}',
            ),
            'api_key'    => array(
                'type'    => 'password',
                'label'   => __( 'API Key', 'bkx-developer-sdk' ),
            ),
            'test_mode'  => array(
                'type'    => 'checkbox',
                'label'   => __( 'Test Mode', 'bkx-developer-sdk' ),
                'default' => true,
            ),
        );
    }
}

// Register the gateway.
add_filter( 'bkx_payment_gateways', function( \$gateways ) {
    \$gateways['{$slug}'] = {$class_name}::class;
    return \$gateways;
} );
PHP;
	}

	/**
	 * Generate REST endpoint.
	 *
	 * @param array $params Parameters.
	 * @return string Generated code.
	 */
	private function generate_rest_endpoint( array $params ): string {
		$route    = $params['route'] ?? '/custom-endpoint';
		$methods  = $params['methods'] ?? 'GET';
		$callback = $params['callback'] ?? 'handle_request';

		return <<<PHP
<?php
/**
 * Custom REST Endpoint.
 *
 * @package BookingX\\CustomEndpoints
 */

namespace BookingX\\CustomEndpoints;

defined( 'ABSPATH' ) || exit;

/**
 * Register custom REST route.
 */
add_action( 'rest_api_init', function() {
    register_rest_route(
        'bkx-custom/v1',
        '{$route}',
        array(
            'methods'             => '{$methods}',
            'callback'            => __NAMESPACE__ . '\\{$callback}',
            'permission_callback' => __NAMESPACE__ . '\\check_permissions',
            'args'                => get_endpoint_args(),
        )
    );
} );

/**
 * Get endpoint arguments.
 *
 * @return array Arguments.
 */
function get_endpoint_args(): array {
    return array(
        'id' => array(
            'required'          => false,
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'description'       => __( 'Resource ID.', 'bkx-developer-sdk' ),
        ),
        'per_page' => array(
            'required'          => false,
            'type'              => 'integer',
            'default'           => 10,
            'sanitize_callback' => 'absint',
        ),
        'page' => array(
            'required'          => false,
            'type'              => 'integer',
            'default'           => 1,
            'sanitize_callback' => 'absint',
        ),
    );
}

/**
 * Check permissions.
 *
 * @param \\WP_REST_Request \$request Request object.
 * @return bool|\\WP_Error True if allowed, WP_Error otherwise.
 */
function check_permissions( \\WP_REST_Request \$request ) {
    // Customize based on your requirements.
    if ( ! current_user_can( 'read' ) ) {
        return new \\WP_Error(
            'rest_forbidden',
            __( 'You do not have permission to access this endpoint.', 'bkx-developer-sdk' ),
            array( 'status' => 403 )
        );
    }
    return true;
}

/**
 * Handle the request.
 *
 * @param \\WP_REST_Request \$request Request object.
 * @return \\WP_REST_Response|\\WP_Error Response.
 */
function {$callback}( \\WP_REST_Request \$request ) {
    \$id       = \$request->get_param( 'id' );
    \$per_page = \$request->get_param( 'per_page' );
    \$page     = \$request->get_param( 'page' );

    // Your logic here.
    \$data = array(
        'message' => 'Custom endpoint response',
        'params'  => array(
            'id'       => \$id,
            'per_page' => \$per_page,
            'page'     => \$page,
        ),
    );

    return new \\WP_REST_Response( \$data, 200 );
}
PHP;
	}

	/**
	 * Generate shortcode.
	 *
	 * @param array $params Parameters.
	 * @return string Generated code.
	 */
	private function generate_shortcode( array $params ): string {
		$tag        = $params['tag'] ?? 'bkx_custom';
		$attributes = $params['attributes'] ?? 'id, title, class';

		$atts_array = array_map( 'trim', explode( ',', $attributes ) );
		$defaults   = array();
		foreach ( $atts_array as $attr ) {
			$defaults[] = "        '{$attr}' => '',";
		}
		$defaults_str = implode( "\n", $defaults );

		return <<<PHP
<?php
/**
 * Custom Shortcode: [{$tag}]
 *
 * @package BookingX\\Shortcodes
 */

namespace BookingX\\Shortcodes;

defined( 'ABSPATH' ) || exit;

/**
 * Register the shortcode.
 */
add_shortcode( '{$tag}', __NAMESPACE__ . '\\render_{$tag}' );

/**
 * Render the shortcode.
 *
 * @param array  \$atts    Shortcode attributes.
 * @param string \$content Enclosed content.
 * @return string HTML output.
 */
function render_{$tag}( \$atts, \$content = null ): string {
    // Parse attributes with defaults.
    \$atts = shortcode_atts(
        array(
{$defaults_str}
        ),
        \$atts,
        '{$tag}'
    );

    // Start output buffering.
    ob_start();
    ?>
    <div class="bkx-{$tag} <?php echo esc_attr( \$atts['class'] ); ?>">
        <?php if ( ! empty( \$atts['title'] ) ) : ?>
            <h3><?php echo esc_html( \$atts['title'] ); ?></h3>
        <?php endif; ?>

        <div class="bkx-{$tag}-content">
            <?php
            // Your shortcode logic here.
            if ( ! empty( \$atts['id'] ) ) {
                // Example: Display booking info.
                \$booking = get_post( absint( \$atts['id'] ) );
                if ( \$booking ) {
                    echo '<p>' . esc_html( \$booking->post_title ) . '</p>';
                }
            }

            // Display enclosed content.
            if ( \$content ) {
                echo wp_kses_post( do_shortcode( \$content ) );
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Enqueue shortcode styles (optional).
 */
add_action( 'wp_enqueue_scripts', function() {
    // Only enqueue if shortcode is used.
    global \$post;
    if ( is_a( \$post, 'WP_Post' ) && has_shortcode( \$post->post_content, '{$tag}' ) ) {
        wp_enqueue_style(
            'bkx-{$tag}-style',
            plugins_url( 'assets/css/{$tag}.css', __FILE__ ),
            array(),
            '1.0.0'
        );
    }
} );
PHP;
	}

	/**
	 * Generate hook listener.
	 *
	 * @param array $params Parameters.
	 * @return string Generated code.
	 */
	private function generate_hook_listener( array $params ): string {
		$hook     = $params['hook'] ?? 'bkx_booking_created';
		$priority = $params['priority'] ?? '10';
		$callback = $params['callback'] ?? 'handle_' . str_replace( array( 'bkx_', '-' ), array( '', '_' ), $hook );

		// Determine hook type and arguments.
		$hook_info = $this->get_hook_info( $hook );

		return <<<PHP
<?php
/**
 * Hook Listener: {$hook}
 *
 * @package BookingX\\Hooks
 */

namespace BookingX\\Hooks;

defined( 'ABSPATH' ) || exit;

/**
 * Register the hook listener.
 */
add_{$hook_info['type']}( '{$hook}', __NAMESPACE__ . '\\{$callback}', {$priority}, {$hook_info['args']} );

/**
 * Handle the {$hook} hook.
 *
{$hook_info['docblock']}
 */
function {$callback}( {$hook_info['params']} ) {
    // Your logic here.

{$hook_info['example']}
}
PHP;
	}

	/**
	 * Generate cron job.
	 *
	 * @param array $params Parameters.
	 * @return string Generated code.
	 */
	private function generate_cron_job( array $params ): string {
		$name     = $params['name'] ?? 'custom_task';
		$schedule = $params['schedule'] ?? 'hourly';
		$callback = $params['callback'] ?? 'run_' . $name;

		$hook_name = 'bkx_cron_' . $name;

		return <<<PHP
<?php
/**
 * Cron Job: {$name}
 *
 * @package BookingX\\Cron
 */

namespace BookingX\\Cron;

defined( 'ABSPATH' ) || exit;

/**
 * Register the cron job on activation.
 */
function schedule_{$name}(): void {
    if ( ! wp_next_scheduled( '{$hook_name}' ) ) {
        wp_schedule_event( time(), '{$schedule}', '{$hook_name}' );
    }
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\schedule_{$name}' );

/**
 * Clear the cron job on deactivation.
 */
function unschedule_{$name}(): void {
    wp_clear_scheduled_hook( '{$hook_name}' );
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\unschedule_{$name}' );

/**
 * Register the cron callback.
 */
add_action( '{$hook_name}', __NAMESPACE__ . '\\{$callback}' );

/**
 * Execute the cron job.
 */
function {$callback}(): void {
    // Prevent timeout on long-running tasks.
    set_time_limit( 300 );

    // Log start time.
    \$start_time = microtime( true );

    try {
        // Your cron logic here.
        // Example: Process pending bookings.
        \$bookings = get_posts( array(
            'post_type'      => 'bkx_booking',
            'post_status'    => 'bkx-pending',
            'posts_per_page' => 50,
            'meta_query'     => array(
                array(
                    'key'     => '_needs_processing',
                    'value'   => '1',
                    'compare' => '=',
                ),
            ),
        ) );

        foreach ( \$bookings as \$booking ) {
            process_booking( \$booking->ID );
        }

        // Log completion.
        \$duration = round( microtime( true ) - \$start_time, 2 );
        error_log( sprintf( '[BKX Cron] {$name} completed in %s seconds. Processed %d items.', \$duration, count( \$bookings ) ) );

    } catch ( \\Exception \$e ) {
        error_log( '[BKX Cron] {$name} failed: ' . \$e->getMessage() );
    }
}

/**
 * Process a single booking.
 *
 * @param int \$booking_id Booking ID.
 */
function process_booking( int \$booking_id ): void {
    // Your processing logic here.
    update_post_meta( \$booking_id, '_needs_processing', '0' );
    update_post_meta( \$booking_id, '_processed_at', current_time( 'mysql' ) );
}

/**
 * Add custom cron schedule if needed.
 */
add_filter( 'cron_schedules', function( \$schedules ) {
    // Example: Add every 5 minutes schedule.
    \$schedules['every_5_minutes'] = array(
        'interval' => 300,
        'display'  => __( 'Every 5 Minutes', 'bkx-developer-sdk' ),
    );
    return \$schedules;
} );
PHP;
	}

	/**
	 * Get hook information.
	 *
	 * @param string $hook Hook name.
	 * @return array Hook info.
	 */
	private function get_hook_info( string $hook ): array {
		$hooks = array(
			'bkx_booking_created'   => array(
				'type'     => 'action',
				'args'     => 2,
				'params'   => 'int $booking_id, array $booking_data',
				'docblock' => " * @param int   \$booking_id   The booking ID.\n * @param array \$booking_data The booking data.",
				'example'  => "    // Example: Send notification.\n    \$customer_email = \$booking_data['customer_email'] ?? '';\n    if ( \$customer_email ) {\n        wp_mail( \$customer_email, 'Booking Confirmation', 'Your booking has been created.' );\n    }",
			),
			'bkx_booking_updated'   => array(
				'type'     => 'action',
				'args'     => 3,
				'params'   => 'int $booking_id, array $new_data, array $old_data',
				'docblock' => " * @param int   \$booking_id The booking ID.\n * @param array \$new_data   The new booking data.\n * @param array \$old_data   The previous booking data.",
				'example'  => "    // Example: Log changes.\n    if ( \$new_data['status'] !== \$old_data['status'] ) {\n        error_log( sprintf( 'Booking %d status changed from %s to %s', \$booking_id, \$old_data['status'], \$new_data['status'] ) );\n    }",
			),
			'bkx_booking_cancelled' => array(
				'type'     => 'action',
				'args'     => 2,
				'params'   => 'int $booking_id, string $reason',
				'docblock' => " * @param int    \$booking_id The booking ID.\n * @param string \$reason     Cancellation reason.",
				'example'  => "    // Example: Process refund.\n    \$total = get_post_meta( \$booking_id, 'booking_total', true );\n    // Process refund logic here...",
			),
			'bkx_payment_completed' => array(
				'type'     => 'action',
				'args'     => 2,
				'params'   => 'int $booking_id, array $payment_data',
				'docblock' => " * @param int   \$booking_id   The booking ID.\n * @param array \$payment_data Payment details.",
				'example'  => "    // Example: Update booking status.\n    wp_update_post( array(\n        'ID'          => \$booking_id,\n        'post_status' => 'bkx-ack',\n    ) );",
			),
		);

		return $hooks[ $hook ] ?? array(
			'type'     => 'action',
			'args'     => 1,
			'params'   => '$args',
			'docblock' => ' * @param mixed $args Hook arguments.',
			'example'  => '    // Add your logic here.',
		);
	}

	/**
	 * Save generated code to file.
	 *
	 * @param string $code     Generated code.
	 * @param string $filename Filename.
	 * @return string|WP_Error File URL or error.
	 */
	public function save_code( string $code, string $filename ) {
		$settings = get_option( 'bkx_developer_sdk_settings', array() );
		$path     = $settings['generated_code_path'] ?? WP_CONTENT_DIR . '/bkx-generated/';

		if ( ! file_exists( $path ) ) {
			wp_mkdir_p( $path );
		}

		$file_path = $path . $filename;

		// Add unique suffix if file exists.
		if ( file_exists( $file_path ) ) {
			$info      = pathinfo( $filename );
			$filename  = $info['filename'] . '-' . time() . '.' . ( $info['extension'] ?? 'php' );
			$file_path = $path . $filename;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$result = file_put_contents( $file_path, $code );

		if ( false === $result ) {
			return new \WP_Error( 'write_failed', __( 'Failed to write file.', 'bkx-developer-sdk' ) );
		}

		return content_url( 'bkx-generated/' . $filename );
	}
}
