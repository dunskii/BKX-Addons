<?php
/**
 * Location list shortcode template.
 *
 * @package BookingX\MultiLocation
 */

defined( 'ABSPATH' ) || exit;

$columns      = absint( $atts['columns'] );
$show_address = 'yes' === $atts['show_address'];
$show_phone   = 'yes' === $atts['show_phone'];
$show_hours   = 'yes' === $atts['show_hours'];

$addon = \BookingX\MultiLocation\MultiLocationAddon::get_instance();
?>

<div class="bkx-locations-grid" style="--columns: <?php echo esc_attr( $columns ); ?>;">
	<?php if ( empty( $locations ) ) : ?>
		<p class="bkx-no-locations"><?php esc_html_e( 'No locations available.', 'bkx-multi-location' ); ?></p>
	<?php else : ?>
		<?php foreach ( $locations as $location ) : ?>
			<div class="bkx-location-item" data-location-id="<?php echo esc_attr( $location->id ); ?>">
				<h3 class="bkx-location-name"><?php echo esc_html( $location->name ); ?></h3>

				<?php if ( $location->description ) : ?>
					<p class="bkx-location-description"><?php echo esc_html( $location->description ); ?></p>
				<?php endif; ?>

				<?php if ( $show_address ) : ?>
					<?php
					$address = $addon->get_service( 'locations' )->get_formatted_address( $location );
					if ( $address ) :
						?>
						<div class="bkx-location-address">
							<span class="dashicons dashicons-location"></span>
							<?php echo esc_html( $address ); ?>
						</div>

						<?php if ( $location->latitude && $location->longitude ) : ?>
							<a href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr( $location->latitude . ',' . $location->longitude ); ?>"
							   target="_blank" class="bkx-get-directions">
								<?php esc_html_e( 'Get Directions', 'bkx-multi-location' ); ?>
							</a>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $show_phone && $location->phone ) : ?>
					<div class="bkx-location-phone">
						<span class="dashicons dashicons-phone"></span>
						<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $location->phone ) ); ?>">
							<?php echo esc_html( $location->phone ); ?>
						</a>
					</div>
				<?php endif; ?>

				<?php if ( $location->email ) : ?>
					<div class="bkx-location-email">
						<span class="dashicons dashicons-email"></span>
						<a href="mailto:<?php echo esc_attr( $location->email ); ?>">
							<?php echo esc_html( $location->email ); ?>
						</a>
					</div>
				<?php endif; ?>

				<?php if ( $show_hours ) : ?>
					<?php
					$hours = $addon->get_service( 'hours' )->get_formatted_hours( $location->id );
					if ( ! empty( $hours ) ) :
						?>
						<div class="bkx-location-hours">
							<h4><?php esc_html_e( 'Hours', 'bkx-multi-location' ); ?></h4>
							<table class="bkx-hours-table">
								<?php foreach ( $hours as $day_num => $day_data ) : ?>
									<tr class="<?php echo $day_data['open'] ? '' : 'bkx-closed-day'; ?>">
										<td class="bkx-day-name"><?php echo esc_html( $day_data['day'] ); ?></td>
										<td class="bkx-day-hours"><?php echo esc_html( $day_data['hours'] ); ?></td>
									</tr>
								<?php endforeach; ?>
							</table>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<div class="bkx-location-actions">
					<a href="#" class="bkx-book-at-location button" data-location-id="<?php echo esc_attr( $location->id ); ?>">
						<?php esc_html_e( 'Book Now', 'bkx-multi-location' ); ?>
					</a>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
