<?php
/**
 * Time Entry Metabox for bookings.
 *
 * @package BookingX\LegalProfessional
 * @since   1.0.0
 */

namespace BookingX\LegalProfessional\Admin;

use BookingX\LegalProfessional\Services\BillingService;
use BookingX\LegalProfessional\Services\CaseManagementService;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Time Entry Metabox class.
 *
 * @since 1.0.0
 */
class TimeEntryMetabox {

	/**
	 * Initialize the metabox.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post_bkx_booking', array( $this, 'save_meta' ), 10, 2 );
	}

	/**
	 * Add metaboxes.
	 *
	 * @return void
	 */
	public function add_metaboxes(): void {
		add_meta_box(
			'bkx_legal_matter_link',
			__( 'Legal Matter', 'bkx-legal-professional' ),
			array( $this, 'render_matter_link_metabox' ),
			'bkx_booking',
			'side',
			'high'
		);

		add_meta_box(
			'bkx_legal_time_entry',
			__( 'Time Entry', 'bkx-legal-professional' ),
			array( $this, 'render_time_entry_metabox' ),
			'bkx_booking',
			'normal',
			'default'
		);
	}

	/**
	 * Render matter link metabox.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_matter_link_metabox( $post ): void {
		wp_nonce_field( 'bkx_legal_booking_meta', 'bkx_legal_booking_nonce' );

		$matter_id = get_post_meta( $post->ID, '_bkx_matter_id', true );

		// Get active matters.
		$matters = get_posts( array(
			'post_type'      => 'bkx_matter',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_bkx_status',
					'value'   => array( 'active', 'pending' ),
					'compare' => 'IN',
				),
			),
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
		?>
		<p>
			<label for="bkx_matter_id"><strong><?php esc_html_e( 'Link to Matter', 'bkx-legal-professional' ); ?></strong></label>
		</p>
		<select id="bkx_matter_id" name="bkx_matter_id" class="widefat">
			<option value=""><?php esc_html_e( '— No Matter —', 'bkx-legal-professional' ); ?></option>
			<?php foreach ( $matters as $matter ) : ?>
				<?php
				$number = get_post_meta( $matter->ID, '_bkx_matter_number', true );
				$client_id = get_post_meta( $matter->ID, '_bkx_client_id', true );
				$client = get_user_by( 'id', $client_id );
				$client_name = $client ? $client->display_name : '';
				?>
				<option value="<?php echo esc_attr( $matter->ID ); ?>" <?php selected( $matter_id, $matter->ID ); ?>>
					<?php echo esc_html( $number . ' - ' . $matter->post_title . ( $client_name ? ' (' . $client_name . ')' : '' ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<?php if ( $matter_id ) : ?>
			<?php $matter = get_post( $matter_id ); ?>
			<?php if ( $matter ) : ?>
				<p style="margin-top: 10px;">
					<a href="<?php echo esc_url( get_edit_post_link( $matter_id ) ); ?>" class="button button-small">
						<?php esc_html_e( 'View Matter', 'bkx-legal-professional' ); ?>
					</a>
				</p>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render time entry metabox.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_time_entry_metabox( $post ): void {
		$matter_id   = get_post_meta( $post->ID, '_bkx_matter_id', true );
		$time_entry_id = get_post_meta( $post->ID, '_bkx_time_entry_id', true );

		// Get booking duration.
		$duration = get_post_meta( $post->ID, 'total_time_taken', true );
		$hours    = floor( $duration / 60 );
		$minutes  = $duration % 60;

		$billing_service = BillingService::get_instance();
		$activity_codes  = $billing_service->get_activity_codes();
		?>
		<table class="form-table">
			<?php if ( ! $matter_id ) : ?>
				<tr>
					<td colspan="2">
						<p class="description"><?php esc_html_e( 'Link this booking to a matter to create a time entry.', 'bkx-legal-professional' ); ?></p>
					</td>
				</tr>
			<?php elseif ( $time_entry_id ) : ?>
				<?php
				$entry = $billing_service->get_time_entry( $time_entry_id );
				if ( $entry ) :
				?>
					<tr>
						<th><?php esc_html_e( 'Time Entry', 'bkx-legal-professional' ); ?></th>
						<td>
							<strong><?php echo esc_html( $entry['hours_display'] ); ?></strong>
							<span class="description">
								<?php if ( $entry['billed'] ) : ?>
									(<?php esc_html_e( 'Billed', 'bkx-legal-professional' ); ?>)
								<?php elseif ( $entry['billable'] ) : ?>
									(<?php esc_html_e( 'Billable', 'bkx-legal-professional' ); ?>)
								<?php else : ?>
									(<?php esc_html_e( 'Non-billable', 'bkx-legal-professional' ); ?>)
								<?php endif; ?>
							</span>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Amount', 'bkx-legal-professional' ); ?></th>
						<td><?php echo esc_html( '$' . number_format( $entry['amount'], 2 ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Description', 'bkx-legal-professional' ); ?></th>
						<td><?php echo esc_html( $entry['description'] ); ?></td>
					</tr>
				<?php endif; ?>
			<?php else : ?>
				<tr>
					<th><label for="bkx_time_hours"><?php esc_html_e( 'Time', 'bkx-legal-professional' ); ?></label></th>
					<td>
						<input type="number" id="bkx_time_hours" name="bkx_time_hours" value="<?php echo esc_attr( $hours ); ?>" min="0" max="24" class="small-text">
						<label for="bkx_time_hours"><?php esc_html_e( 'hours', 'bkx-legal-professional' ); ?></label>
						<input type="number" id="bkx_time_minutes" name="bkx_time_minutes" value="<?php echo esc_attr( $minutes ); ?>" min="0" max="59" class="small-text">
						<label for="bkx_time_minutes"><?php esc_html_e( 'minutes', 'bkx-legal-professional' ); ?></label>
					</td>
				</tr>
				<tr>
					<th><label for="bkx_activity_code"><?php esc_html_e( 'Activity Code', 'bkx-legal-professional' ); ?></label></th>
					<td>
						<select id="bkx_activity_code" name="bkx_activity_code" class="regular-text">
							<option value=""><?php esc_html_e( '— Select —', 'bkx-legal-professional' ); ?></option>
							<?php foreach ( $activity_codes as $code => $label ) : ?>
								<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $code . ' - ' . $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="bkx_time_description"><?php esc_html_e( 'Description', 'bkx-legal-professional' ); ?></label></th>
					<td>
						<textarea id="bkx_time_description" name="bkx_time_description" rows="3" class="large-text"></textarea>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Billable', 'bkx-legal-professional' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="bkx_time_billable" value="1" checked>
							<?php esc_html_e( 'Mark this time as billable', 'bkx-legal-professional' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th></th>
					<td>
						<button type="button" class="button" id="bkx-create-time-entry"><?php esc_html_e( 'Create Time Entry', 'bkx-legal-professional' ); ?></button>
						<span class="spinner" style="float: none; margin-top: 0;"></span>
					</td>
				</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Save meta data.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta( int $post_id, $post ): void {
		if ( ! isset( $_POST['bkx_legal_booking_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_legal_booking_nonce'] ) ), 'bkx_legal_booking_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Save matter link.
		if ( isset( $_POST['bkx_matter_id'] ) ) {
			$matter_id = absint( $_POST['bkx_matter_id'] );
			update_post_meta( $post_id, '_bkx_matter_id', $matter_id );

			// Log the link if matter exists.
			if ( $matter_id ) {
				CaseManagementService::get_instance()->log_matter_activity(
					$matter_id,
					'booking_linked',
					sprintf(
						/* translators: %d: booking ID */
						__( 'Booking #%d linked', 'bkx-legal-professional' ),
						$post_id
					)
				);
			}
		}
	}
}
