<?php
/**
 * Favorites Template
 *
 * @package BookingX\UserProfilesAdvanced
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id           = get_current_user_id();
$favorites_service = $this->addon->get_favorites_service();
$favorites         = $favorites_service->get_favorites( $user_id );
?>

<div class="bkx-favorites">
	<h3><?php esc_html_e( 'My Favorites', 'bkx-user-profiles-advanced' ); ?></h3>

	<?php if ( empty( $favorites ) ) : ?>
		<p class="bkx-no-favorites"><?php esc_html_e( 'You haven\'t added any favorites yet.', 'bkx-user-profiles-advanced' ); ?></p>
	<?php else : ?>
		<div class="bkx-favorites-grid">
			<?php foreach ( $favorites as $favorite ) : ?>
				<div class="bkx-favorite-card" data-id="<?php echo esc_attr( $favorite->item_id ); ?>" data-type="<?php echo esc_attr( $favorite->item_type ); ?>">
					<?php if ( ! empty( $favorite->thumbnail ) ) : ?>
						<div class="bkx-favorite-image">
							<img src="<?php echo esc_url( $favorite->thumbnail ); ?>" alt="<?php echo esc_attr( $favorite->title ); ?>" />
						</div>
					<?php endif; ?>
					<div class="bkx-favorite-content">
						<h4><?php echo esc_html( $favorite->title ); ?></h4>
						<?php if ( ! empty( $favorite->excerpt ) ) : ?>
							<p><?php echo esc_html( wp_trim_words( $favorite->excerpt, 15 ) ); ?></p>
						<?php endif; ?>
						<?php if ( isset( $favorite->price ) && $favorite->price > 0 ) : ?>
							<span class="bkx-favorite-price"><?php echo esc_html( number_format( (float) $favorite->price, 2 ) ); ?></span>
						<?php endif; ?>
					</div>
					<div class="bkx-favorite-actions">
						<?php if ( ! empty( $favorite->permalink ) ) : ?>
							<a href="<?php echo esc_url( $favorite->permalink ); ?>" class="bkx-button bkx-button-primary"><?php esc_html_e( 'Book Now', 'bkx-user-profiles-advanced' ); ?></a>
						<?php endif; ?>
						<button type="button" class="bkx-button bkx-remove-favorite" data-id="<?php echo esc_attr( $favorite->item_id ); ?>" data-type="<?php echo esc_attr( $favorite->item_type ); ?>">
							<?php esc_html_e( 'Remove', 'bkx-user-profiles-advanced' ); ?>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
