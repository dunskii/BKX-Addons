<?php
/**
 * Location selector shortcode template.
 *
 * @package BookingX\MultiLocation
 */

defined( 'ABSPATH' ) || exit;

$style       = $atts['style'];
$show_map    = 'yes' === $atts['show_map'];
$redirect_to = $atts['redirect_to'];
?>

<div class="bkx-location-selector" data-style="<?php echo esc_attr( $style ); ?>">
	<?php if ( empty( $locations ) ) : ?>
		<p class="bkx-no-locations"><?php esc_html_e( 'No locations available.', 'bkx-multi-location' ); ?></p>
	<?php elseif ( 'dropdown' === $style ) : ?>
		<select class="bkx-location-dropdown" data-redirect="<?php echo esc_attr( $redirect_to ); ?>">
			<option value=""><?php esc_html_e( 'Select a location...', 'bkx-multi-location' ); ?></option>
			<?php foreach ( $locations as $location ) : ?>
				<option value="<?php echo esc_attr( $location->id ); ?>" data-slug="<?php echo esc_attr( $location->slug ); ?>">
					<?php echo esc_html( $location->name ); ?>
					<?php if ( $location->city ) : ?>
						- <?php echo esc_html( $location->city ); ?>
					<?php endif; ?>
				</option>
			<?php endforeach; ?>
		</select>

	<?php elseif ( 'list' === $style ) : ?>
		<ul class="bkx-location-list-simple">
			<?php foreach ( $locations as $location ) : ?>
				<li>
					<a href="#" class="bkx-location-link" data-id="<?php echo esc_attr( $location->id ); ?>" data-slug="<?php echo esc_attr( $location->slug ); ?>">
						<?php echo esc_html( $location->name ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

	<?php else : // Cards style. ?>
		<div class="bkx-location-cards">
			<?php foreach ( $locations as $location ) : ?>
				<div class="bkx-location-card" data-id="<?php echo esc_attr( $location->id ); ?>">
					<h4 class="bkx-location-card-name"><?php echo esc_html( $location->name ); ?></h4>

					<?php if ( $location->address_line_1 || $location->city ) : ?>
						<p class="bkx-location-card-address">
							<?php
							$address_parts = array_filter(
								array(
									$location->address_line_1,
									$location->city,
									$location->state,
								)
							);
							echo esc_html( implode( ', ', $address_parts ) );
							?>
						</p>
					<?php endif; ?>

					<?php if ( $location->phone ) : ?>
						<p class="bkx-location-card-phone">
							<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $location->phone ) ); ?>">
								<?php echo esc_html( $location->phone ); ?>
							</a>
						</p>
					<?php endif; ?>

					<a href="#" class="bkx-location-select-btn button" data-id="<?php echo esc_attr( $location->id ); ?>">
						<?php esc_html_e( 'Select', 'bkx-multi-location' ); ?>
					</a>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $show_map && ! empty( $locations ) ) : ?>
		<div class="bkx-location-selector-map" id="bkx-location-selector-map" style="height: 300px; margin-top: 20px;"></div>
		<script>
			window.bkxLocations = <?php echo wp_json_encode( array_map( function( $l ) {
				return array(
					'id'   => $l->id,
					'name' => $l->name,
					'lat'  => floatval( $l->latitude ),
					'lng'  => floatval( $l->longitude ),
				);
			}, array_filter( $locations, function( $l ) {
				return $l->latitude && $l->longitude;
			} ) ) ); ?>;
		</script>
	<?php endif; ?>

	<?php
	$enable_geolocation = get_option( 'bkx_ml_enable_geolocation', 'no' );
	if ( 'yes' === $enable_geolocation ) :
		?>
		<div class="bkx-location-geolocation">
			<button type="button" class="bkx-find-nearest button">
				<span class="dashicons dashicons-location"></span>
				<?php esc_html_e( 'Find Nearest Location', 'bkx-multi-location' ); ?>
			</button>
			<span class="bkx-geolocation-status"></span>
		</div>
	<?php endif; ?>
</div>
