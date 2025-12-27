<?php
/**
 * Frontend Service Selector Template
 *
 * @package BookingX\MultipleServices
 * @since   1.0.0
 *
 * @var int $base_id Service ID
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$addon = bkx_multiple_services();
if ( ! $addon ) {
	return;
}

$bundle_service = $addon->get_bundle_service();
$combinations = $bundle_service->get_available_combinations( $base_id );
$display_mode = $addon->get_setting( 'display_mode', 'checkbox' );
$show_pricing = $addon->get_setting( 'show_bundle_pricing', true );

if ( empty( $combinations ) ) {
	return;
}
?>

<div class="bkx-multiple-services-selector">
	<h3><?php esc_html_e( 'Add Additional Services', 'bkx-multiple-services' ); ?></h3>

	<?php if ( 'checkbox' === $display_mode ) : ?>
		<div class="bkx-service-checkboxes">
			<?php foreach ( $combinations as $service ) : ?>
				<label class="bkx-service-option">
					<input
						type="checkbox"
						name="selected_services[]"
						value="<?php echo esc_attr( $service['id'] ); ?>"
						data-price="<?php echo esc_attr( $service['price'] ); ?>"
						data-duration="<?php echo esc_attr( $service['duration'] ); ?>"
						class="bkx-service-checkbox"
					/>
					<span class="service-title"><?php echo esc_html( $service['title'] ); ?></span>
					<?php if ( $show_pricing ) : ?>
						<span class="service-price">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: service price */
									__( '+ %s', 'bkx-multiple-services' ),
									number_format( (float) $service['price'], 2 )
								)
							);
							?>
						</span>
					<?php endif; ?>
					<span class="service-duration">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %d: duration in minutes */
								__( '(%d min)', 'bkx-multiple-services' ),
								intval( $service['duration'] )
							)
						);
						?>
					</span>
				</label>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<select name="selected_services[]" multiple class="bkx-service-dropdown">
			<?php foreach ( $combinations as $service ) : ?>
				<option
					value="<?php echo esc_attr( $service['id'] ); ?>"
					data-price="<?php echo esc_attr( $service['price'] ); ?>"
					data-duration="<?php echo esc_attr( $service['duration'] ); ?>"
				>
					<?php
					echo esc_html( $service['title'] );
					if ( $show_pricing ) {
						echo esc_html( ' - ' . number_format( (float) $service['price'], 2 ) );
					}
					?>
				</option>
			<?php endforeach; ?>
		</select>
	<?php endif; ?>

	<div class="bkx-bundle-summary" style="display:none;">
		<p class="bundle-price">
			<strong><?php esc_html_e( 'Total:', 'bkx-multiple-services' ); ?></strong>
			<span class="price-amount">$0.00</span>
		</p>
		<p class="bundle-duration">
			<strong><?php esc_html_e( 'Duration:', 'bkx-multiple-services' ); ?></strong>
			<span class="duration-amount">0 min</span>
		</p>
	</div>
</div>
