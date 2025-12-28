<?php
/**
 * Seat Metabox
 *
 * @package BookingX\GroupBookings\Admin
 * @since   1.0.0
 */

namespace BookingX\GroupBookings\Admin;

/**
 * Metabox for seat (resource) post type.
 *
 * @since 1.0.0
 */
class SeatMetabox {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_bkx_seat', array( $this, 'save_meta' ), 10, 2 );
	}

	/**
	 * Add meta boxes.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'bkx-group-capacity',
			__( 'Group Capacity', 'bkx-group-bookings' ),
			array( $this, 'render_metabox' ),
			'bkx_seat',
			'side',
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
		wp_nonce_field( 'bkx_group_seat_settings', 'bkx_group_seat_nonce' );

		$capacity           = get_post_meta( $post->ID, '_bkx_group_capacity', true );
		$concurrent_groups  = get_post_meta( $post->ID, '_bkx_group_concurrent', true );
		?>
		<p>
			<label for="bkx_group_capacity">
				<strong><?php esc_html_e( 'Maximum Capacity', 'bkx-group-bookings' ); ?></strong>
			</label>
			<br>
			<input type="number" id="bkx_group_capacity" name="bkx_group_capacity"
				   value="<?php echo esc_attr( $capacity ); ?>"
				   min="1" max="500" class="widefat"
				   placeholder="<?php esc_attr_e( 'Use default', 'bkx-group-bookings' ); ?>">
			<span class="description">
				<?php esc_html_e( 'Maximum people per time slot.', 'bkx-group-bookings' ); ?>
			</span>
		</p>

		<p>
			<label for="bkx_group_concurrent">
				<strong><?php esc_html_e( 'Concurrent Groups', 'bkx-group-bookings' ); ?></strong>
			</label>
			<br>
			<input type="number" id="bkx_group_concurrent" name="bkx_group_concurrent"
				   value="<?php echo esc_attr( $concurrent_groups ); ?>"
				   min="1" max="10" class="widefat"
				   placeholder="1">
			<span class="description">
				<?php esc_html_e( 'Number of groups allowed per slot.', 'bkx-group-bookings' ); ?>
			</span>
		</p>
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
		if ( ! isset( $_POST['bkx_group_seat_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_group_seat_nonce'] ) ), 'bkx_group_seat_settings' ) ) {
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
		$capacity = isset( $_POST['bkx_group_capacity'] ) ? absint( $_POST['bkx_group_capacity'] ) : '';
		update_post_meta( $post_id, '_bkx_group_capacity', $capacity );

		$concurrent = isset( $_POST['bkx_group_concurrent'] ) ? absint( $_POST['bkx_group_concurrent'] ) : '';
		update_post_meta( $post_id, '_bkx_group_concurrent', $concurrent );
	}
}
