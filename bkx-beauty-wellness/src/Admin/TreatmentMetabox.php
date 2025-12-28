<?php
/**
 * Treatment Metabox
 *
 * Adds beauty-specific fields to treatment (base) posts.
 *
 * @package BookingX\BeautyWellness\Admin
 * @since   1.0.0
 */

namespace BookingX\BeautyWellness\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TreatmentMetabox
 *
 * @since 1.0.0
 */
class TreatmentMetabox {

	/**
	 * Initialize metabox.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post_bkx_base', array( $this, 'save_metabox' ), 10, 2 );
	}

	/**
	 * Add metaboxes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_metaboxes(): void {
		add_meta_box(
			'bkx_beauty_treatment_details',
			__( 'Beauty & Wellness Details', 'bkx-beauty-wellness' ),
			array( $this, 'render_details_metabox' ),
			'bkx_base',
			'normal',
			'high'
		);

		add_meta_box(
			'bkx_treatment_variations',
			__( 'Treatment Variations', 'bkx-beauty-wellness' ),
			array( $this, 'render_variations_metabox' ),
			'bkx_base',
			'normal',
			'default'
		);

		add_meta_box(
			'bkx_treatment_addons',
			__( 'Available Add-ons', 'bkx-beauty-wellness' ),
			array( $this, 'render_addons_metabox' ),
			'bkx_base',
			'side',
			'default'
		);
	}

	/**
	 * Render details metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_details_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'bkx_beauty_treatment', 'bkx_beauty_treatment_nonce' );

		$featured             = get_post_meta( $post->ID, '_bkx_featured_treatment', true );
		$consultation_required = get_post_meta( $post->ID, '_bkx_consultation_required', true );
		$contraindications    = get_post_meta( $post->ID, '_bkx_contraindications', true ) ?: array();
		$ingredients          = get_post_meta( $post->ID, '_bkx_treatment_ingredients', true ) ?: array();
		$aftercare            = get_post_meta( $post->ID, '_bkx_aftercare_notes', true );
		$prep_instructions    = get_post_meta( $post->ID, '_bkx_prep_instructions', true );
		?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="bkx_featured_treatment"><?php esc_html_e( 'Featured Treatment', 'bkx-beauty-wellness' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="bkx_featured_treatment" id="bkx_featured_treatment" value="1" <?php checked( $featured, '1' ); ?>>
						<?php esc_html_e( 'Display as featured treatment', 'bkx-beauty-wellness' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="bkx_consultation_required"><?php esc_html_e( 'Consultation Required', 'bkx-beauty-wellness' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" name="bkx_consultation_required" id="bkx_consultation_required" value="1" <?php checked( $consultation_required, '1' ); ?>>
						<?php esc_html_e( 'Require consultation form before booking', 'bkx-beauty-wellness' ); ?>
					</label>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="bkx_treatment_ingredients"><?php esc_html_e( 'Ingredients/Products', 'bkx-beauty-wellness' ); ?></label>
				</th>
				<td>
					<textarea name="bkx_treatment_ingredients" id="bkx_treatment_ingredients" rows="3" class="large-text"><?php echo esc_textarea( implode( "\n", $ingredients ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Enter each ingredient on a new line. Used for allergy alerts.', 'bkx-beauty-wellness' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="bkx_contraindications"><?php esc_html_e( 'Contraindications', 'bkx-beauty-wellness' ); ?></label>
				</th>
				<td>
					<textarea name="bkx_contraindications" id="bkx_contraindications" rows="3" class="large-text"><?php echo esc_textarea( implode( "\n", $contraindications ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Enter each contraindication on a new line (e.g., pregnancy, heart conditions).', 'bkx-beauty-wellness' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="bkx_prep_instructions"><?php esc_html_e( 'Preparation Instructions', 'bkx-beauty-wellness' ); ?></label>
				</th>
				<td>
					<?php
					wp_editor( $prep_instructions, 'bkx_prep_instructions', array(
						'textarea_rows' => 5,
						'media_buttons' => false,
						'teeny'         => true,
					) );
					?>
					<p class="description"><?php esc_html_e( 'Instructions sent to client before appointment.', 'bkx-beauty-wellness' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="bkx_aftercare_notes"><?php esc_html_e( 'Aftercare Notes', 'bkx-beauty-wellness' ); ?></label>
				</th>
				<td>
					<?php
					wp_editor( $aftercare, 'bkx_aftercare_notes', array(
						'textarea_rows' => 5,
						'media_buttons' => false,
						'teeny'         => true,
					) );
					?>
					<p class="description"><?php esc_html_e( 'Aftercare instructions sent to client after appointment.', 'bkx-beauty-wellness' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render variations metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_variations_metabox( \WP_Post $post ): void {
		$variations = get_post_meta( $post->ID, '_bkx_treatment_variations', true ) ?: array();
		?>
		<div class="bkx-variations-container">
			<p class="description"><?php esc_html_e( 'Add variations like "Express" (30 min) or "Deluxe" (90 min) versions of this treatment.', 'bkx-beauty-wellness' ); ?></p>

			<table class="widefat bkx-variations-table" id="bkx-variations-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Variation Name', 'bkx-beauty-wellness' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'Duration (min)', 'bkx-beauty-wellness' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'Price', 'bkx-beauty-wellness' ); ?></th>
						<th style="width: 60px;"><?php esc_html_e( 'Enabled', 'bkx-beauty-wellness' ); ?></th>
						<th style="width: 40px;"></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! empty( $variations ) ) : ?>
						<?php foreach ( $variations as $index => $variation ) : ?>
							<tr class="bkx-variation-row">
								<td>
									<input type="text" name="bkx_variations[<?php echo $index; ?>][name]" value="<?php echo esc_attr( $variation['name'] ?? '' ); ?>" class="regular-text">
								</td>
								<td>
									<input type="number" name="bkx_variations[<?php echo $index; ?>][duration]" value="<?php echo esc_attr( $variation['duration'] ?? '' ); ?>" min="0" step="5">
								</td>
								<td>
									<input type="number" name="bkx_variations[<?php echo $index; ?>][price]" value="<?php echo esc_attr( $variation['price'] ?? '' ); ?>" min="0" step="0.01">
								</td>
								<td style="text-align: center;">
									<input type="checkbox" name="bkx_variations[<?php echo $index; ?>][enabled]" value="1" <?php checked( ! empty( $variation['enabled'] ) ); ?>>
								</td>
								<td>
									<button type="button" class="button bkx-remove-variation">&times;</button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
				<tfoot>
					<tr>
						<td colspan="5">
							<button type="button" class="button bkx-add-variation" id="bkx-add-variation">
								<?php esc_html_e( '+ Add Variation', 'bkx-beauty-wellness' ); ?>
							</button>
						</td>
					</tr>
				</tfoot>
			</table>
		</div>

		<script>
		jQuery(document).ready(function($) {
			var variationIndex = <?php echo count( $variations ); ?>;

			$('#bkx-add-variation').on('click', function() {
				var row = '<tr class="bkx-variation-row">' +
					'<td><input type="text" name="bkx_variations[' + variationIndex + '][name]" class="regular-text"></td>' +
					'<td><input type="number" name="bkx_variations[' + variationIndex + '][duration]" min="0" step="5"></td>' +
					'<td><input type="number" name="bkx_variations[' + variationIndex + '][price]" min="0" step="0.01"></td>' +
					'<td style="text-align: center;"><input type="checkbox" name="bkx_variations[' + variationIndex + '][enabled]" value="1" checked></td>' +
					'<td><button type="button" class="button bkx-remove-variation">&times;</button></td>' +
					'</tr>';
				$('#bkx-variations-table tbody').append(row);
				variationIndex++;
			});

			$(document).on('click', '.bkx-remove-variation', function() {
				$(this).closest('tr').remove();
			});
		});
		</script>

		<style>
			.bkx-variations-table input[type="number"] { width: 80px; }
			.bkx-variation-row td { vertical-align: middle; }
			.bkx-remove-variation { color: #a00; }
		</style>
		<?php
	}

	/**
	 * Render add-ons metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_addons_metabox( \WP_Post $post ): void {
		$selected_addons = get_post_meta( $post->ID, '_bkx_treatment_addons', true ) ?: array();

		// Get all add-ons.
		$addons = get_posts( array(
			'post_type'      => 'bkx_addition',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		if ( empty( $addons ) ) {
			echo '<p>' . esc_html__( 'No add-ons available. Create add-ons first.', 'bkx-beauty-wellness' ) . '</p>';
			return;
		}
		?>
		<p class="description"><?php esc_html_e( 'Select add-ons that can be booked with this treatment.', 'bkx-beauty-wellness' ); ?></p>

		<div class="bkx-addons-checklist" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
			<?php foreach ( $addons as $addon ) : ?>
				<label style="display: block; margin-bottom: 5px;">
					<input type="checkbox" name="bkx_treatment_addons[]" value="<?php echo esc_attr( $addon->ID ); ?>" <?php checked( in_array( $addon->ID, $selected_addons, true ) ); ?>>
					<?php echo esc_html( $addon->post_title ); ?>
					<?php
					$price = get_post_meta( $addon->ID, 'addition_price', true );
					if ( $price ) {
						echo ' (' . wp_kses_post( wc_price( $price ) ) . ')';
					}
					?>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Save metabox data.
	 *
	 * @since 1.0.0
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_metabox( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['bkx_beauty_treatment_nonce'] ) || ! wp_verify_nonce( $_POST['bkx_beauty_treatment_nonce'], 'bkx_beauty_treatment' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Featured treatment.
		update_post_meta( $post_id, '_bkx_featured_treatment', isset( $_POST['bkx_featured_treatment'] ) ? '1' : '' );

		// Consultation required.
		update_post_meta( $post_id, '_bkx_consultation_required', isset( $_POST['bkx_consultation_required'] ) ? '1' : '' );

		// Ingredients.
		if ( isset( $_POST['bkx_treatment_ingredients'] ) ) {
			$ingredients = array_filter( array_map( 'sanitize_text_field', explode( "\n", $_POST['bkx_treatment_ingredients'] ) ) );
			update_post_meta( $post_id, '_bkx_treatment_ingredients', $ingredients );
		}

		// Contraindications.
		if ( isset( $_POST['bkx_contraindications'] ) ) {
			$contraindications = array_filter( array_map( 'sanitize_text_field', explode( "\n", $_POST['bkx_contraindications'] ) ) );
			update_post_meta( $post_id, '_bkx_contraindications', $contraindications );
		}

		// Prep instructions.
		if ( isset( $_POST['bkx_prep_instructions'] ) ) {
			update_post_meta( $post_id, '_bkx_prep_instructions', wp_kses_post( $_POST['bkx_prep_instructions'] ) );
		}

		// Aftercare notes.
		if ( isset( $_POST['bkx_aftercare_notes'] ) ) {
			update_post_meta( $post_id, '_bkx_aftercare_notes', wp_kses_post( $_POST['bkx_aftercare_notes'] ) );
		}

		// Variations.
		if ( isset( $_POST['bkx_variations'] ) && is_array( $_POST['bkx_variations'] ) ) {
			$variations = array();
			foreach ( $_POST['bkx_variations'] as $variation ) {
				if ( ! empty( $variation['name'] ) ) {
					$variations[] = array(
						'name'     => sanitize_text_field( $variation['name'] ),
						'duration' => absint( $variation['duration'] ?? 0 ),
						'price'    => floatval( $variation['price'] ?? 0 ),
						'enabled'  => ! empty( $variation['enabled'] ),
					);
				}
			}
			update_post_meta( $post_id, '_bkx_treatment_variations', $variations );
		} else {
			delete_post_meta( $post_id, '_bkx_treatment_variations' );
		}

		// Add-ons.
		if ( isset( $_POST['bkx_treatment_addons'] ) && is_array( $_POST['bkx_treatment_addons'] ) ) {
			$addons = array_map( 'absint', $_POST['bkx_treatment_addons'] );
			update_post_meta( $post_id, '_bkx_treatment_addons', $addons );
		} else {
			delete_post_meta( $post_id, '_bkx_treatment_addons' );
		}
	}
}
