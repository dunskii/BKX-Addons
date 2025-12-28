<?php
/**
 * Base Metabox
 *
 * @package BookingX\GroupBookings\Admin
 * @since   1.0.0
 */

namespace BookingX\GroupBookings\Admin;

/**
 * Metabox for base (service) post type.
 *
 * @since 1.0.0
 */
class BaseMetabox {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_bkx_base', array( $this, 'save_meta' ), 10, 2 );
		add_action( 'wp_ajax_bkx_add_pricing_tier', array( $this, 'ajax_add_tier' ) );
		add_action( 'wp_ajax_bkx_delete_pricing_tier', array( $this, 'ajax_delete_tier' ) );
	}

	/**
	 * Add meta boxes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'bkx-group-booking-settings',
			__( 'Group Booking Settings', 'bkx-group-bookings' ),
			array( $this, 'render_metabox' ),
			'bkx_base',
			'normal',
			'default'
		);
	}

	/**
	 * Render metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_metabox( \WP_Post $post ): void {
		wp_nonce_field( 'bkx_group_base_settings', 'bkx_group_base_nonce' );

		$enabled      = get_post_meta( $post->ID, '_bkx_group_enabled', true );
		$min_qty      = get_post_meta( $post->ID, '_bkx_group_min_quantity', true );
		$max_qty      = get_post_meta( $post->ID, '_bkx_group_max_quantity', true );
		$pricing_mode = get_post_meta( $post->ID, '_bkx_group_pricing_mode', true );

		// Discount settings.
		$discount_enabled = get_post_meta( $post->ID, '_bkx_group_discount_enabled', true );
		$discount_min     = get_post_meta( $post->ID, '_bkx_group_discount_min', true );
		$discount_type    = get_post_meta( $post->ID, '_bkx_group_discount_type', true );
		$discount_value   = get_post_meta( $post->ID, '_bkx_group_discount_value', true );

		// Get tiers for tiered pricing.
		$pricing_service = new \BookingX\GroupBookings\Services\GroupPricingService();
		$tiers           = $pricing_service->get_tiers( $post->ID );
		?>
		<div class="bkx-group-base-settings">
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Enable Group Booking', 'bkx-group-bookings' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_group_enabled" value="1" <?php checked( $enabled, '1' ); ?>>
							<?php esc_html_e( 'Allow multiple people for this service', 'bkx-group-bookings' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'If unchecked, uses global settings.', 'bkx-group-bookings' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Minimum People', 'bkx-group-bookings' ); ?></th>
					<td>
						<input type="number" name="bkx_group_min_quantity"
							   value="<?php echo esc_attr( $min_qty ); ?>"
							   min="1" max="100" class="small-text"
							   placeholder="<?php esc_attr_e( 'Use default', 'bkx-group-bookings' ); ?>">
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Maximum People', 'bkx-group-bookings' ); ?></th>
					<td>
						<input type="number" name="bkx_group_max_quantity"
							   value="<?php echo esc_attr( $max_qty ); ?>"
							   min="1" max="500" class="small-text"
							   placeholder="<?php esc_attr_e( 'Use default', 'bkx-group-bookings' ); ?>">
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Pricing Mode', 'bkx-group-bookings' ); ?></th>
					<td>
						<select name="bkx_group_pricing_mode" id="bkx_group_pricing_mode">
							<option value=""><?php esc_html_e( 'Use global default', 'bkx-group-bookings' ); ?></option>
							<option value="per_person" <?php selected( $pricing_mode, 'per_person' ); ?>>
								<?php esc_html_e( 'Per Person', 'bkx-group-bookings' ); ?>
							</option>
							<option value="flat_rate" <?php selected( $pricing_mode, 'flat_rate' ); ?>>
								<?php esc_html_e( 'Flat Rate', 'bkx-group-bookings' ); ?>
							</option>
							<option value="tiered" <?php selected( $pricing_mode, 'tiered' ); ?>>
								<?php esc_html_e( 'Tiered Pricing', 'bkx-group-bookings' ); ?>
							</option>
						</select>
					</td>
				</tr>
			</table>

			<!-- Service-specific discount -->
			<h4><?php esc_html_e( 'Service Discount', 'bkx-group-bookings' ); ?></h4>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Custom Discount', 'bkx-group-bookings' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_group_discount_enabled" value="1" <?php checked( $discount_enabled, '1' ); ?>>
							<?php esc_html_e( 'Use custom discount for this service', 'bkx-group-bookings' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Minimum for Discount', 'bkx-group-bookings' ); ?></th>
					<td>
						<input type="number" name="bkx_group_discount_min"
							   value="<?php echo esc_attr( $discount_min ); ?>"
							   min="2" max="100" class="small-text">
						<?php esc_html_e( 'people', 'bkx-group-bookings' ); ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Discount', 'bkx-group-bookings' ); ?></th>
					<td>
						<input type="number" name="bkx_group_discount_value"
							   value="<?php echo esc_attr( $discount_value ); ?>"
							   min="0" step="0.01" class="small-text">
						<select name="bkx_group_discount_type">
							<option value="percentage" <?php selected( $discount_type, 'percentage' ); ?>>%</option>
							<option value="fixed" <?php selected( $discount_type, 'fixed' ); ?>>$</option>
						</select>
					</td>
				</tr>
			</table>

			<!-- Tiered pricing -->
			<div id="bkx-tiered-pricing" style="<?php echo 'tiered' !== $pricing_mode ? 'display:none;' : ''; ?>">
				<h4><?php esc_html_e( 'Pricing Tiers', 'bkx-group-bookings' ); ?></h4>
				<table class="widefat" id="bkx-tiers-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Min Qty', 'bkx-group-bookings' ); ?></th>
							<th><?php esc_html_e( 'Max Qty', 'bkx-group-bookings' ); ?></th>
							<th><?php esc_html_e( 'Price Type', 'bkx-group-bookings' ); ?></th>
							<th><?php esc_html_e( 'Price', 'bkx-group-bookings' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $tiers ) ) : ?>
							<tr class="bkx-no-tiers">
								<td colspan="5"><?php esc_html_e( 'No pricing tiers defined.', 'bkx-group-bookings' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $tiers as $tier ) : ?>
								<tr data-tier-id="<?php echo esc_attr( $tier['id'] ); ?>">
									<td><?php echo esc_html( $tier['min_quantity'] ); ?></td>
									<td><?php echo esc_html( $tier['max_quantity'] ); ?></td>
									<td><?php echo esc_html( ucwords( str_replace( '_', ' ', $tier['price_type'] ) ) ); ?></td>
									<td>$<?php echo esc_html( number_format( $tier['price'], 2 ) ); ?></td>
									<td>
										<button type="button" class="button button-small bkx-delete-tier" data-id="<?php echo esc_attr( $tier['id'] ); ?>">
											<?php esc_html_e( 'Delete', 'bkx-group-bookings' ); ?>
										</button>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<div class="bkx-add-tier">
					<h5><?php esc_html_e( 'Add Tier', 'bkx-group-bookings' ); ?></h5>
					<div class="bkx-tier-form">
						<input type="number" name="new_tier_min" placeholder="<?php esc_attr_e( 'Min', 'bkx-group-bookings' ); ?>" min="1" class="small-text">
						<input type="number" name="new_tier_max" placeholder="<?php esc_attr_e( 'Max', 'bkx-group-bookings' ); ?>" min="1" class="small-text">
						<select name="new_tier_price_type">
							<option value="per_person"><?php esc_html_e( 'Per Person', 'bkx-group-bookings' ); ?></option>
							<option value="flat"><?php esc_html_e( 'Flat Rate', 'bkx-group-bookings' ); ?></option>
						</select>
						<input type="number" name="new_tier_price" placeholder="<?php esc_attr_e( 'Price', 'bkx-group-bookings' ); ?>" min="0" step="0.01" class="small-text">
						<button type="button" class="button" id="bkx-add-tier-btn"><?php esc_html_e( 'Add', 'bkx-group-bookings' ); ?></button>
					</div>
				</div>
			</div>
		</div>

		<script>
		jQuery(document).ready(function($) {
			$('#bkx_group_pricing_mode').on('change', function() {
				if ($(this).val() === 'tiered') {
					$('#bkx-tiered-pricing').show();
				} else {
					$('#bkx-tiered-pricing').hide();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Save meta data.
	 *
	 * @since 1.0.0
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta( int $post_id, \WP_Post $post ): void {
		// Verify nonce.
		if ( ! isset( $_POST['bkx_group_base_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_group_base_nonce'] ) ), 'bkx_group_base_settings' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Save fields.
		$enabled = isset( $_POST['bkx_group_enabled'] ) ? '1' : '';
		update_post_meta( $post_id, '_bkx_group_enabled', $enabled );

		$min_qty = isset( $_POST['bkx_group_min_quantity'] ) ? absint( $_POST['bkx_group_min_quantity'] ) : '';
		update_post_meta( $post_id, '_bkx_group_min_quantity', $min_qty );

		$max_qty = isset( $_POST['bkx_group_max_quantity'] ) ? absint( $_POST['bkx_group_max_quantity'] ) : '';
		update_post_meta( $post_id, '_bkx_group_max_quantity', $max_qty );

		$pricing_mode = isset( $_POST['bkx_group_pricing_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['bkx_group_pricing_mode'] ) ) : '';
		update_post_meta( $post_id, '_bkx_group_pricing_mode', $pricing_mode );

		// Discount fields.
		$discount_enabled = isset( $_POST['bkx_group_discount_enabled'] ) ? '1' : '';
		update_post_meta( $post_id, '_bkx_group_discount_enabled', $discount_enabled );

		$discount_min = isset( $_POST['bkx_group_discount_min'] ) ? absint( $_POST['bkx_group_discount_min'] ) : '';
		update_post_meta( $post_id, '_bkx_group_discount_min', $discount_min );

		$discount_type = isset( $_POST['bkx_group_discount_type'] ) ? sanitize_text_field( wp_unslash( $_POST['bkx_group_discount_type'] ) ) : '';
		update_post_meta( $post_id, '_bkx_group_discount_type', $discount_type );

		$discount_value = isset( $_POST['bkx_group_discount_value'] ) ? floatval( $_POST['bkx_group_discount_value'] ) : '';
		update_post_meta( $post_id, '_bkx_group_discount_value', $discount_value );
	}

	/**
	 * AJAX: Add pricing tier.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_add_tier(): void {
		check_ajax_referer( 'bkx_group_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-group-bookings' ) );
		}

		$base_id = isset( $_POST['base_id'] ) ? absint( $_POST['base_id'] ) : 0;

		if ( ! $base_id ) {
			wp_send_json_error( __( 'Invalid service.', 'bkx-group-bookings' ) );
		}

		$data = array(
			'min_quantity' => isset( $_POST['min_quantity'] ) ? absint( $_POST['min_quantity'] ) : 1,
			'max_quantity' => isset( $_POST['max_quantity'] ) ? absint( $_POST['max_quantity'] ) : 10,
			'price_type'   => isset( $_POST['price_type'] ) ? sanitize_text_field( wp_unslash( $_POST['price_type'] ) ) : 'per_person',
			'price'        => isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0,
		);

		$service = new \BookingX\GroupBookings\Services\GroupPricingService();
		$tier_id = $service->add_tier( $base_id, $data );

		if ( ! $tier_id ) {
			wp_send_json_error( __( 'Failed to add tier.', 'bkx-group-bookings' ) );
		}

		wp_send_json_success(
			array(
				'tier_id' => $tier_id,
				'message' => __( 'Tier added successfully.', 'bkx-group-bookings' ),
			)
		);
	}

	/**
	 * AJAX: Delete pricing tier.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function ajax_delete_tier(): void {
		check_ajax_referer( 'bkx_group_admin', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'bkx-group-bookings' ) );
		}

		$tier_id = isset( $_POST['tier_id'] ) ? absint( $_POST['tier_id'] ) : 0;

		if ( ! $tier_id ) {
			wp_send_json_error( __( 'Invalid tier.', 'bkx-group-bookings' ) );
		}

		$service = new \BookingX\GroupBookings\Services\GroupPricingService();
		$result  = $service->delete_tier( $tier_id );

		if ( ! $result ) {
			wp_send_json_error( __( 'Failed to delete tier.', 'bkx-group-bookings' ) );
		}

		wp_send_json_success( array( 'message' => __( 'Tier deleted.', 'bkx-group-bookings' ) ) );
	}
}
