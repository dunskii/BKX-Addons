<?php
/**
 * Divi Integration Custom Styling.
 *
 * @package BookingX\Divi
 */

defined( 'ABSPATH' ) || exit;

$settings   = get_option( 'bkx_divi_settings', array() );
$custom_css = isset( $settings['custom_css'] ) ? $settings['custom_css'] : '';
?>

<div class="bkx-styling-section">
	<h2><?php esc_html_e( 'Custom CSS', 'bkx-divi' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Add custom CSS to style BookingX modules. This CSS will be loaded on pages using Divi Builder.', 'bkx-divi' ); ?>
	</p>

	<form id="bkx-divi-styling-form" method="post">
		<textarea name="custom_css" id="bkx-custom-css" rows="20" class="large-text code"><?php echo esc_textarea( $custom_css ); ?></textarea>

		<h3><?php esc_html_e( 'CSS Reference', 'bkx-divi' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'Use the following CSS classes to target BookingX modules:', 'bkx-divi' ); ?>
		</p>

		<table class="widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Element', 'bkx-divi' ); ?></th>
					<th><?php esc_html_e( 'CSS Class', 'bkx-divi' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Booking Form Container', 'bkx-divi' ); ?></td>
					<td><code>.bkx-divi-booking-form</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Service List Container', 'bkx-divi' ); ?></td>
					<td><code>.bkx-divi-service-list</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Service Card', 'bkx-divi' ); ?></td>
					<td><code>.bkx-service-card</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Resource List Container', 'bkx-divi' ); ?></td>
					<td><code>.bkx-divi-resource-list</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Resource Card', 'bkx-divi' ); ?></td>
					<td><code>.bkx-resource-card</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Availability Calendar', 'bkx-divi' ); ?></td>
					<td><code>.bkx-divi-availability-calendar</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Calendar Header', 'bkx-divi' ); ?></td>
					<td><code>.bkx-calendar-header</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Calendar Grid', 'bkx-divi' ); ?></td>
					<td><code>.bkx-calendar-grid</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Booking Button', 'bkx-divi' ); ?></td>
					<td><code>.bkx-divi-booking-button</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Book Button (in cards)', 'bkx-divi' ); ?></td>
					<td><code>.bkx-book-button</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Available Slot', 'bkx-divi' ); ?></td>
					<td><code>.bkx-slot-available</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Unavailable Slot', 'bkx-divi' ); ?></td>
					<td><code>.bkx-slot-unavailable</code></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Limited Slot', 'bkx-divi' ); ?></td>
					<td><code>.bkx-slot-limited</code></td>
				</tr>
			</tbody>
		</table>

		<p class="submit">
			<button type="submit" id="bkx-save-styling" class="button button-primary">
				<?php esc_html_e( 'Save Custom CSS', 'bkx-divi' ); ?>
			</button>
			<span class="spinner"></span>
		</p>
	</form>
</div>

<div class="bkx-styling-section">
	<h2><?php esc_html_e( 'Example CSS', 'bkx-divi' ); ?></h2>
	<pre class="bkx-css-example">
/* Custom colors for service cards */
.bkx-service-card {
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

/* Hover effect for book buttons */
.bkx-book-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Calendar slot styling */
.bkx-slot-available {
    background-color: #10b981 !important;
}

/* Custom button styling */
.bkx-divi-booking-button {
    border-radius: 50px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
}
	</pre>
</div>
