<?php
/**
 * Documentation Service.
 *
 * @package BookingX\DeveloperSDK
 */

namespace BookingX\DeveloperSDK\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class Documentation
 *
 * Provides developer documentation for BookingX.
 */
class Documentation {

	/**
	 * Get all documentation.
	 *
	 * @return array Documentation sections.
	 */
	public function get_all(): array {
		return array(
			'getting_started' => $this->get_getting_started(),
			'hooks'           => $this->get_hooks_documentation(),
			'rest_api'        => $this->get_rest_api_documentation(),
			'database'        => $this->get_database_documentation(),
			'classes'         => $this->get_classes_documentation(),
			'examples'        => $this->get_examples(),
		);
	}

	/**
	 * Get getting started documentation.
	 *
	 * @return array Documentation.
	 */
	private function get_getting_started(): array {
		return array(
			'title'    => __( 'Getting Started', 'bkx-developer-sdk' ),
			'sections' => array(
				array(
					'title'   => __( 'Introduction', 'bkx-developer-sdk' ),
					'content' => __( 'BookingX provides a comprehensive set of hooks, filters, and APIs for building custom integrations and add-ons.', 'bkx-developer-sdk' ),
				),
				array(
					'title'   => __( 'Add-on Structure', 'bkx-developer-sdk' ),
					'content' => __( 'BookingX add-ons follow a standard WordPress plugin structure with additional SDK integration.', 'bkx-developer-sdk' ),
					'code'    => "my-addon/\n├── my-addon.php          # Main plugin file\n├── src/\n│   ├── autoload.php     # PSR-4 autoloader\n│   └── MyAddon.php       # Main class\n├── templates/            # Template files\n├── assets/               # CSS/JS\n└── languages/            # Translations",
				),
				array(
					'title'   => __( 'SDK Abstract Classes', 'bkx-developer-sdk' ),
					'content' => __( 'The BookingX SDK provides abstract classes for common add-on types:', 'bkx-developer-sdk' ),
					'list'    => array(
						'AbstractAddon - Base class for all add-ons',
						'AbstractPaymentGateway - Payment gateway integrations',
						'AbstractNotificationProvider - SMS/Email providers',
						'AbstractCalendarProvider - Calendar sync',
						'AbstractIntegration - CRM/Marketing integrations',
					),
				),
			),
		);
	}

	/**
	 * Get hooks documentation.
	 *
	 * @return array Documentation.
	 */
	private function get_hooks_documentation(): array {
		return array(
			'title'    => __( 'Hooks Reference', 'bkx-developer-sdk' ),
			'sections' => array(
				array(
					'title' => __( 'Booking Actions', 'bkx-developer-sdk' ),
					'hooks' => array(
						array(
							'name'        => 'bkx_booking_created',
							'type'        => 'action',
							'description' => __( 'Fires after a booking is created.', 'bkx-developer-sdk' ),
							'params'      => '$booking_id, $booking_data',
							'example'     => "add_action( 'bkx_booking_created', function( \$booking_id, \$data ) {\n    // Send notification\n}, 10, 2 );",
						),
						array(
							'name'        => 'bkx_booking_updated',
							'type'        => 'action',
							'description' => __( 'Fires after a booking is updated.', 'bkx-developer-sdk' ),
							'params'      => '$booking_id, $new_data, $old_data',
						),
						array(
							'name'        => 'bkx_booking_cancelled',
							'type'        => 'action',
							'description' => __( 'Fires when a booking is cancelled.', 'bkx-developer-sdk' ),
							'params'      => '$booking_id, $reason',
						),
						array(
							'name'        => 'bkx_booking_completed',
							'type'        => 'action',
							'description' => __( 'Fires when a booking is marked complete.', 'bkx-developer-sdk' ),
							'params'      => '$booking_id',
						),
					),
				),
				array(
					'title' => __( 'Payment Actions', 'bkx-developer-sdk' ),
					'hooks' => array(
						array(
							'name'        => 'bkx_payment_completed',
							'type'        => 'action',
							'description' => __( 'Fires after payment is processed.', 'bkx-developer-sdk' ),
							'params'      => '$booking_id, $payment_data',
						),
						array(
							'name'        => 'bkx_payment_failed',
							'type'        => 'action',
							'description' => __( 'Fires when payment fails.', 'bkx-developer-sdk' ),
							'params'      => '$booking_id, $error',
						),
						array(
							'name'        => 'bkx_payment_refunded',
							'type'        => 'action',
							'description' => __( 'Fires after a refund is processed.', 'bkx-developer-sdk' ),
							'params'      => '$booking_id, $amount',
						),
					),
				),
				array(
					'title' => __( 'Booking Filters', 'bkx-developer-sdk' ),
					'hooks' => array(
						array(
							'name'        => 'bkx_booking_total',
							'type'        => 'filter',
							'description' => __( 'Filter the booking total before saving.', 'bkx-developer-sdk' ),
							'params'      => '$total, $booking_id',
							'example'     => "add_filter( 'bkx_booking_total', function( \$total, \$booking_id ) {\n    // Apply discount\n    return \$total * 0.9;\n}, 10, 2 );",
						),
						array(
							'name'        => 'bkx_available_time_slots',
							'type'        => 'filter',
							'description' => __( 'Filter available time slots.', 'bkx-developer-sdk' ),
							'params'      => '$slots, $date, $service_id, $staff_id',
						),
						array(
							'name'        => 'bkx_booking_form_fields',
							'type'        => 'filter',
							'description' => __( 'Filter booking form fields.', 'bkx-developer-sdk' ),
							'params'      => '$fields',
						),
					),
				),
			),
		);
	}

	/**
	 * Get REST API documentation.
	 *
	 * @return array Documentation.
	 */
	private function get_rest_api_documentation(): array {
		return array(
			'title'    => __( 'REST API', 'bkx-developer-sdk' ),
			'sections' => array(
				array(
					'title'   => __( 'Authentication', 'bkx-developer-sdk' ),
					'content' => __( 'BookingX REST API uses WordPress REST API authentication. Include the X-WP-Nonce header or use application passwords.', 'bkx-developer-sdk' ),
					'example' => "// Using nonce\nfetch('/wp-json/wp/v2/bkx_booking', {\n    headers: { 'X-WP-Nonce': wpApiSettings.nonce }\n});",
				),
				array(
					'title'     => __( 'Endpoints', 'bkx-developer-sdk' ),
					'endpoints' => array(
						array(
							'method'      => 'GET',
							'endpoint'    => '/wp/v2/bkx_booking',
							'description' => __( 'List bookings', 'bkx-developer-sdk' ),
						),
						array(
							'method'      => 'POST',
							'endpoint'    => '/wp/v2/bkx_booking',
							'description' => __( 'Create booking', 'bkx-developer-sdk' ),
						),
						array(
							'method'      => 'GET',
							'endpoint'    => '/wp/v2/bkx_booking/{id}',
							'description' => __( 'Get booking', 'bkx-developer-sdk' ),
						),
						array(
							'method'      => 'PUT',
							'endpoint'    => '/wp/v2/bkx_booking/{id}',
							'description' => __( 'Update booking', 'bkx-developer-sdk' ),
						),
						array(
							'method'      => 'DELETE',
							'endpoint'    => '/wp/v2/bkx_booking/{id}',
							'description' => __( 'Delete booking', 'bkx-developer-sdk' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Get database documentation.
	 *
	 * @return array Documentation.
	 */
	private function get_database_documentation(): array {
		return array(
			'title'    => __( 'Database Schema', 'bkx-developer-sdk' ),
			'sections' => array(
				array(
					'title'   => __( 'Post Types', 'bkx-developer-sdk' ),
					'content' => __( 'BookingX uses custom post types for core data:', 'bkx-developer-sdk' ),
					'list'    => array(
						'bkx_booking - Booking records',
						'bkx_base - Services',
						'bkx_seat - Staff/Resources',
						'bkx_addition - Extras/Add-ons',
					),
				),
				array(
					'title'  => __( 'Booking Meta Keys', 'bkx-developer-sdk' ),
					'list'   => array(
						'booking_date - Date of booking (Y-m-d)',
						'booking_time - Time of booking (H:i:s)',
						'base_id - Service ID',
						'seat_id - Staff/Resource ID',
						'customer_name - Customer full name',
						'customer_email - Customer email',
						'customer_phone - Customer phone',
						'booking_total - Total amount',
						'booking_notes - Customer notes',
					),
				),
				array(
					'title'   => __( 'Custom Tables', 'bkx-developer-sdk' ),
					'content' => __( 'BookingX creates additional tables for specific features:', 'bkx-developer-sdk' ),
					'list'    => array(
						'wp_bkx_consents - GDPR consent records',
						'wp_bkx_booking_sessions - Multi-step form sessions',
						'wp_bkx_availability_cache - Cached availability',
					),
				),
			),
		);
	}

	/**
	 * Get classes documentation.
	 *
	 * @return array Documentation.
	 */
	private function get_classes_documentation(): array {
		return array(
			'title'    => __( 'Core Classes', 'bkx-developer-sdk' ),
			'sections' => array(
				array(
					'title'       => 'BkxBooking',
					'description' => __( 'Handles booking CRUD operations and calculations.', 'bkx-developer-sdk' ),
					'methods'     => array(
						'get_booking( $id )' => __( 'Get booking by ID', 'bkx-developer-sdk' ),
						'create_booking( $data )' => __( 'Create new booking', 'bkx-developer-sdk' ),
						'update_booking( $id, $data )' => __( 'Update booking', 'bkx-developer-sdk' ),
						'cancel_booking( $id, $reason )' => __( 'Cancel booking', 'bkx-developer-sdk' ),
						'calculate_total( $service_id, $extras )' => __( 'Calculate total price', 'bkx-developer-sdk' ),
					),
				),
				array(
					'title'       => 'BkxBase',
					'description' => __( 'Manages services/bases.', 'bkx-developer-sdk' ),
					'methods'     => array(
						'get_services()' => __( 'Get all active services', 'bkx-developer-sdk' ),
						'get_service( $id )' => __( 'Get service by ID', 'bkx-developer-sdk' ),
						'get_price( $service_id )' => __( 'Get service price', 'bkx-developer-sdk' ),
						'get_duration( $service_id )' => __( 'Get service duration', 'bkx-developer-sdk' ),
					),
				),
				array(
					'title'       => 'BkxSeat',
					'description' => __( 'Manages staff/resources.', 'bkx-developer-sdk' ),
					'methods'     => array(
						'get_staff()' => __( 'Get all active staff', 'bkx-developer-sdk' ),
						'get_staff_member( $id )' => __( 'Get staff by ID', 'bkx-developer-sdk' ),
						'get_availability( $staff_id, $date )' => __( 'Get availability for date', 'bkx-developer-sdk' ),
					),
				),
			),
		);
	}

	/**
	 * Get code examples.
	 *
	 * @return array Examples.
	 */
	private function get_examples(): array {
		return array(
			'title'    => __( 'Code Examples', 'bkx-developer-sdk' ),
			'examples' => array(
				array(
					'title'       => __( 'Create a Booking Programmatically', 'bkx-developer-sdk' ),
					'description' => __( 'Create a new booking with all required fields.', 'bkx-developer-sdk' ),
					'code'        => "<?php\n\$booking_id = wp_insert_post( array(\n    'post_type'   => 'bkx_booking',\n    'post_status' => 'bkx-pending',\n    'post_title'  => 'Booking - John Doe',\n    'meta_input'  => array(\n        'booking_date'   => '2024-01-15',\n        'booking_time'   => '10:00:00',\n        'base_id'        => 123,\n        'seat_id'        => 456,\n        'customer_name'  => 'John Doe',\n        'customer_email' => 'john@example.com',\n        'booking_total'  => 99.00,\n    ),\n) );\n\nif ( \$booking_id ) {\n    // Trigger booking created action\n    do_action( 'bkx_booking_created', \$booking_id, array() );\n}",
				),
				array(
					'title'       => __( 'Add Custom Booking Field', 'bkx-developer-sdk' ),
					'description' => __( 'Add a custom field to the booking form.', 'bkx-developer-sdk' ),
					'code'        => "<?php\n// Add field to form\nadd_filter( 'bkx_booking_form_fields', function( \$fields ) {\n    \$fields['special_request'] = array(\n        'type'        => 'textarea',\n        'label'       => __( 'Special Requests', 'my-addon' ),\n        'required'    => false,\n        'placeholder' => __( 'Any special requests?', 'my-addon' ),\n    );\n    return \$fields;\n} );\n\n// Save field value\nadd_action( 'bkx_booking_created', function( \$booking_id, \$data ) {\n    if ( isset( \$data['special_request'] ) ) {\n        update_post_meta( \$booking_id, 'special_request', sanitize_textarea_field( \$data['special_request'] ) );\n    }\n}, 10, 2 );",
				),
				array(
					'title'       => __( 'Custom Email Notification', 'bkx-developer-sdk' ),
					'description' => __( 'Send custom email when booking is created.', 'bkx-developer-sdk' ),
					'code'        => "<?php\nadd_action( 'bkx_booking_created', function( \$booking_id, \$data ) {\n    \$customer_email = get_post_meta( \$booking_id, 'customer_email', true );\n    \$customer_name  = get_post_meta( \$booking_id, 'customer_name', true );\n    \$booking_date   = get_post_meta( \$booking_id, 'booking_date', true );\n    \$booking_time   = get_post_meta( \$booking_id, 'booking_time', true );\n\n    \$subject = sprintf( 'Booking Confirmation #%d', \$booking_id );\n    \$message = sprintf(\n        \"Dear %s,\\n\\nYour booking has been confirmed.\\n\\nDate: %s\\nTime: %s\\n\\nThank you!\",\n        \$customer_name,\n        date( 'F j, Y', strtotime( \$booking_date ) ),\n        date( 'g:i A', strtotime( \$booking_time ) )\n    );\n\n    wp_mail( \$customer_email, \$subject, \$message );\n}, 10, 2 );",
				),
			),
		);
	}
}
