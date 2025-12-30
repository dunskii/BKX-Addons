<?php
/**
 * Divi Integration Modules List.
 *
 * @package BookingX\Divi
 */

defined( 'ABSPATH' ) || exit;

$modules = array(
	array(
		'name'        => __( 'Booking Form', 'bkx-divi' ),
		'slug'        => 'bkx_booking_form',
		'description' => __( 'Display a complete booking form with service selection, calendar, and customer details.', 'bkx-divi' ),
		'icon'        => 'E',
	),
	array(
		'name'        => __( 'Service List', 'bkx-divi' ),
		'slug'        => 'bkx_service_list',
		'description' => __( 'Display a grid or list of available services with images, prices, and book buttons.', 'bkx-divi' ),
		'icon'        => 'C',
	),
	array(
		'name'        => __( 'Resource List', 'bkx-divi' ),
		'slug'        => 'bkx_resource_list',
		'description' => __( 'Display staff members or resources with their profiles and available services.', 'bkx-divi' ),
		'icon'        => 'A',
	),
	array(
		'name'        => __( 'Availability Calendar', 'bkx-divi' ),
		'slug'        => 'bkx_availability_calendar',
		'description' => __( 'Show an interactive calendar displaying available and unavailable time slots.', 'bkx-divi' ),
		'icon'        => 'F',
	),
	array(
		'name'        => __( 'Booking Button', 'bkx-divi' ),
		'slug'        => 'bkx_booking_button',
		'description' => __( 'A customizable call-to-action button that links to booking page or opens a modal.', 'bkx-divi' ),
		'icon'        => 'U',
	),
);
?>

<div class="bkx-modules-section">
	<h2><?php esc_html_e( 'Available Modules', 'bkx-divi' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'The following BookingX modules are available in the Divi Builder.', 'bkx-divi' ); ?>
	</p>

	<div class="bkx-modules-grid">
		<?php foreach ( $modules as $module ) : ?>
			<div class="bkx-module-card">
				<div class="bkx-module-icon">
					<span class="et-pb-icon"><?php echo esc_html( $module['icon'] ); ?></span>
				</div>
				<div class="bkx-module-content">
					<h3><?php echo esc_html( $module['name'] ); ?></h3>
					<p><?php echo esc_html( $module['description'] ); ?></p>
					<code><?php echo esc_html( $module['slug'] ); ?></code>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<div class="bkx-modules-section">
	<h2><?php esc_html_e( 'How to Use', 'bkx-divi' ); ?></h2>
	<ol>
		<li><?php esc_html_e( 'Open the Divi Builder on any page or post.', 'bkx-divi' ); ?></li>
		<li><?php esc_html_e( 'Click the "+" button to add a new module.', 'bkx-divi' ); ?></li>
		<li><?php esc_html_e( 'Search for "BKX" or "BookingX" in the module search.', 'bkx-divi' ); ?></li>
		<li><?php esc_html_e( 'Select the desired module and configure its settings.', 'bkx-divi' ); ?></li>
		<li><?php esc_html_e( 'Save and publish your page.', 'bkx-divi' ); ?></li>
	</ol>
</div>

<div class="bkx-modules-section">
	<h2><?php esc_html_e( 'Visual Builder Support', 'bkx-divi' ); ?></h2>
	<p>
		<?php esc_html_e( 'All BookingX modules support the Divi Visual Builder. You can see real-time previews of your booking forms and calendars as you design your pages.', 'bkx-divi' ); ?>
	</p>
	<p>
		<strong><?php esc_html_e( 'Note:', 'bkx-divi' ); ?></strong>
		<?php esc_html_e( 'Some dynamic features like AJAX loading and date selection may require viewing the published page to test fully.', 'bkx-divi' ); ?>
	</p>
</div>
