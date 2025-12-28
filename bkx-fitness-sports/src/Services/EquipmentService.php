<?php
/**
 * Equipment Service
 *
 * Manages gym equipment booking and availability.
 *
 * @package BookingX\FitnessSports\Services
 * @since   1.0.0
 */

namespace BookingX\FitnessSports\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EquipmentService
 *
 * @since 1.0.0
 */
class EquipmentService {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'bkx_equipment_bookings';
	}

	/**
	 * Get all equipment.
	 *
	 * @since 1.0.0
	 * @param array $filters Filters.
	 * @return array
	 */
	public function get_equipment( array $filters = array() ): array {
		$args = array(
			'post_type'      => 'bkx_equipment',
			'post_status'    => 'publish',
			'posts_per_page' => $filters['limit'] ?? -1,
		);

		if ( ! empty( $filters['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'bkx_equipment_category',
					'field'    => 'slug',
					'terms'    => $filters['category'],
				),
			);
		}

		$equipment = get_posts( $args );

		return array_map( array( $this, 'format_equipment' ), $equipment );
	}

	/**
	 * Format equipment data.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $equipment Equipment post.
	 * @return array
	 */
	private function format_equipment( \WP_Post $equipment ): array {
		return array(
			'id'              => $equipment->ID,
			'name'            => $equipment->post_title,
			'description'     => $equipment->post_content,
			'image'           => get_the_post_thumbnail_url( $equipment->ID, 'medium' ),
			'category'        => wp_get_post_terms( $equipment->ID, 'bkx_equipment_category', array( 'fields' => 'names' ) ),
			'max_booking_duration' => absint( get_post_meta( $equipment->ID, '_bkx_max_duration', true ) ) ?: 60,
			'quantity'        => absint( get_post_meta( $equipment->ID, '_bkx_quantity', true ) ) ?: 1,
			'requires_membership' => (bool) get_post_meta( $equipment->ID, '_bkx_requires_membership', true ),
			'instructions'    => get_post_meta( $equipment->ID, '_bkx_instructions', true ),
			'location'        => get_post_meta( $equipment->ID, '_bkx_location', true ),
		);
	}

	/**
	 * Check equipment availability.
	 *
	 * @since 1.0.0
	 * @param int    $equipment_id Equipment ID.
	 * @param string $start_time   Start datetime.
	 * @param string $end_time     End datetime.
	 * @return array
	 */
	public function check_availability( int $equipment_id, string $start_time, string $end_time ): array {
		global $wpdb;

		$equipment = get_post( $equipment_id );
		if ( ! $equipment || 'bkx_equipment' !== $equipment->post_type ) {
			return array(
				'available' => false,
				'message'   => __( 'Equipment not found.', 'bkx-fitness-sports' ),
			);
		}

		$quantity = absint( get_post_meta( $equipment_id, '_bkx_quantity', true ) ) ?: 1;

		// Count overlapping bookings.
		$booked_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name}
				WHERE equipment_id = %d
				AND status IN ('confirmed', 'in_progress')
				AND (
					(start_time <= %s AND end_time > %s)
					OR (start_time < %s AND end_time >= %s)
					OR (start_time >= %s AND end_time <= %s)
				)",
				$equipment_id,
				$start_time,
				$start_time,
				$end_time,
				$end_time,
				$start_time,
				$end_time
			)
		);

		$available = $booked_count < $quantity;

		return array(
			'available'       => $available,
			'total_quantity'  => $quantity,
			'booked_count'    => absint( $booked_count ),
			'remaining'       => max( 0, $quantity - $booked_count ),
			'message'         => $available
				? __( 'Equipment is available.', 'bkx-fitness-sports' )
				: __( 'Equipment is not available at this time.', 'bkx-fitness-sports' ),
		);
	}

	/**
	 * Book equipment.
	 *
	 * @since 1.0.0
	 * @param int    $user_id      User ID.
	 * @param int    $equipment_id Equipment ID.
	 * @param string $start_time   Start datetime.
	 * @param string $end_time     End datetime.
	 * @return int|\WP_Error Booking ID or error.
	 */
	public function book_equipment( int $user_id, int $equipment_id, string $start_time, string $end_time ) {
		global $wpdb;

		// Check membership requirement.
		$requires_membership = get_post_meta( $equipment_id, '_bkx_requires_membership', true );
		if ( $requires_membership ) {
			$membership_service = new MembershipService();
			if ( ! $membership_service->has_active_membership( $user_id ) ) {
				return new \WP_Error( 'membership_required', __( 'An active membership is required to book this equipment.', 'bkx-fitness-sports' ) );
			}
		}

		// Validate duration.
		$max_duration = absint( get_post_meta( $equipment_id, '_bkx_max_duration', true ) ) ?: 60;
		$duration     = ( strtotime( $end_time ) - strtotime( $start_time ) ) / MINUTE_IN_SECONDS;

		if ( $duration > $max_duration ) {
			return new \WP_Error(
				'duration_exceeded',
				sprintf(
					/* translators: %d: max duration in minutes */
					__( 'Maximum booking duration is %d minutes.', 'bkx-fitness-sports' ),
					$max_duration
				)
			);
		}

		// Check availability.
		$availability = $this->check_availability( $equipment_id, $start_time, $end_time );
		if ( ! $availability['available'] ) {
			return new \WP_Error( 'not_available', $availability['message'] );
		}

		// Insert booking.
		$result = $wpdb->insert(
			$this->table_name,
			array(
				'user_id'      => $user_id,
				'equipment_id' => $equipment_id,
				'start_time'   => $start_time,
				'end_time'     => $end_time,
				'status'       => 'confirmed',
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'booking_failed', __( 'Failed to book equipment.', 'bkx-fitness-sports' ) );
		}

		$booking_id = $wpdb->insert_id;

		/**
		 * Fires after equipment is booked.
		 *
		 * @param int $booking_id   Booking ID.
		 * @param int $user_id      User ID.
		 * @param int $equipment_id Equipment ID.
		 */
		do_action( 'bkx_fitness_equipment_booked', $booking_id, $user_id, $equipment_id );

		return $booking_id;
	}

	/**
	 * Cancel equipment booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @param int $user_id    User ID.
	 * @return bool|\WP_Error
	 */
	public function cancel_booking( int $booking_id, int $user_id ) {
		global $wpdb;

		$booking = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d AND user_id = %d",
				$booking_id,
				$user_id
			),
			ARRAY_A
		);

		if ( ! $booking ) {
			return new \WP_Error( 'not_found', __( 'Booking not found.', 'bkx-fitness-sports' ) );
		}

		// Check if already started.
		if ( strtotime( $booking['start_time'] ) <= time() ) {
			return new \WP_Error( 'already_started', __( 'Cannot cancel a booking that has already started.', 'bkx-fitness-sports' ) );
		}

		$result = $wpdb->update(
			$this->table_name,
			array( 'status' => 'cancelled' ),
			array( 'id' => $booking_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get user's equipment bookings.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID.
	 * @param string $status  Optional status filter.
	 * @return array
	 */
	public function get_user_bookings( int $user_id, string $status = '' ): array {
		global $wpdb;

		$where = array( 'b.user_id = %d' );
		$args  = array( $user_id );

		if ( $status ) {
			$where[] = 'b.status = %s';
			$args[]  = $status;
		}

		$where_clause = implode( ' AND ', $where );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT b.*, e.post_title as equipment_name
				FROM {$this->table_name} b
				INNER JOIN {$wpdb->posts} e ON b.equipment_id = e.ID
				WHERE {$where_clause}
				ORDER BY b.start_time DESC",
				$args
			),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Render equipment booking form shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_booking_form( array $atts = array() ): string {
		$atts = shortcode_atts( array(
			'equipment_id' => 0,
			'category'     => '',
		), $atts );

		if ( $atts['equipment_id'] ) {
			$equipment = array( $this->format_equipment( get_post( $atts['equipment_id'] ) ) );
		} else {
			$equipment = $this->get_equipment( array( 'category' => $atts['category'] ) );
		}

		ob_start();
		?>
		<div class="bkx-equipment-booking">
			<div class="bkx-equipment-list">
				<?php foreach ( $equipment as $item ) : ?>
					<div class="bkx-equipment-item" data-equipment-id="<?php echo esc_attr( $item['id'] ); ?>">
						<?php if ( $item['image'] ) : ?>
							<div class="bkx-equipment-image">
								<img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>">
							</div>
						<?php endif; ?>

						<div class="bkx-equipment-details">
							<h4 class="bkx-equipment-name"><?php echo esc_html( $item['name'] ); ?></h4>

							<?php if ( $item['location'] ) : ?>
								<p class="bkx-equipment-location"><?php echo esc_html( $item['location'] ); ?></p>
							<?php endif; ?>

							<p class="bkx-equipment-duration">
								<?php
								printf(
									/* translators: %d: max duration in minutes */
									esc_html__( 'Max booking: %d minutes', 'bkx-fitness-sports' ),
									$item['max_booking_duration']
								);
								?>
							</p>

							<?php if ( $item['requires_membership'] ) : ?>
								<span class="bkx-membership-required"><?php esc_html_e( 'Membership required', 'bkx-fitness-sports' ); ?></span>
							<?php endif; ?>

							<button class="bkx-select-equipment-btn" data-equipment-id="<?php echo esc_attr( $item['id'] ); ?>">
								<?php esc_html_e( 'Select', 'bkx-fitness-sports' ); ?>
							</button>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="bkx-booking-form" style="display: none;">
				<h3><?php esc_html_e( 'Book Equipment', 'bkx-fitness-sports' ); ?></h3>

				<form id="bkx-equipment-booking-form">
					<input type="hidden" name="equipment_id" id="bkx-equipment-id">

					<div class="bkx-form-row">
						<label for="bkx-booking-date"><?php esc_html_e( 'Date', 'bkx-fitness-sports' ); ?></label>
						<input type="date" name="booking_date" id="bkx-booking-date" required min="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
					</div>

					<div class="bkx-form-row">
						<label for="bkx-start-time"><?php esc_html_e( 'Start Time', 'bkx-fitness-sports' ); ?></label>
						<select name="start_time" id="bkx-start-time" required>
							<?php
							for ( $hour = 6; $hour < 22; $hour++ ) {
								for ( $min = 0; $min < 60; $min += 15 ) {
									$time = sprintf( '%02d:%02d', $hour, $min );
									echo '<option value="' . esc_attr( $time ) . '">' . esc_html( date( 'g:i A', strtotime( $time ) ) ) . '</option>';
								}
							}
							?>
						</select>
					</div>

					<div class="bkx-form-row">
						<label for="bkx-duration"><?php esc_html_e( 'Duration (minutes)', 'bkx-fitness-sports' ); ?></label>
						<select name="duration" id="bkx-duration" required>
							<option value="15">15 <?php esc_html_e( 'minutes', 'bkx-fitness-sports' ); ?></option>
							<option value="30" selected>30 <?php esc_html_e( 'minutes', 'bkx-fitness-sports' ); ?></option>
							<option value="45">45 <?php esc_html_e( 'minutes', 'bkx-fitness-sports' ); ?></option>
							<option value="60">60 <?php esc_html_e( 'minutes', 'bkx-fitness-sports' ); ?></option>
						</select>
					</div>

					<div class="bkx-availability-status"></div>

					<button type="submit" class="bkx-book-equipment-btn">
						<?php esc_html_e( 'Book Equipment', 'bkx-fitness-sports' ); ?>
					</button>

					<button type="button" class="bkx-cancel-btn">
						<?php esc_html_e( 'Cancel', 'bkx-fitness-sports' ); ?>
					</button>
				</form>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
