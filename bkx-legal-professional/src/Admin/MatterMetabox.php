<?php
/**
 * Matter Metabox for Legal & Professional Services.
 *
 * @package BookingX\LegalProfessional
 * @since   1.0.0
 */

namespace BookingX\LegalProfessional\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Matter Metabox class.
 *
 * @since 1.0.0
 */
class MatterMetabox {

	/**
	 * Initialize the metabox.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post_bkx_matter', array( $this, 'save_meta' ), 10, 2 );
	}

	/**
	 * Add metaboxes.
	 *
	 * @return void
	 */
	public function add_metaboxes(): void {
		add_meta_box(
			'bkx_matter_details',
			__( 'Matter Details', 'bkx-legal-professional' ),
			array( $this, 'render_details_metabox' ),
			'bkx_matter',
			'normal',
			'high'
		);

		add_meta_box(
			'bkx_matter_billing',
			__( 'Billing Information', 'bkx-legal-professional' ),
			array( $this, 'render_billing_metabox' ),
			'bkx_matter',
			'normal',
			'default'
		);

		add_meta_box(
			'bkx_matter_dates',
			__( 'Important Dates', 'bkx-legal-professional' ),
			array( $this, 'render_dates_metabox' ),
			'bkx_matter',
			'side',
			'default'
		);
	}

	/**
	 * Render details metabox.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_details_metabox( $post ): void {
		wp_nonce_field( 'bkx_matter_meta', 'bkx_matter_nonce' );

		$matter_number = get_post_meta( $post->ID, '_bkx_matter_number', true );
		$client_id     = get_post_meta( $post->ID, '_bkx_client_id', true );
		$responsible   = get_post_meta( $post->ID, '_bkx_responsible_attorney', true );
		$originating   = get_post_meta( $post->ID, '_bkx_originating_attorney', true );
		$billing       = get_post_meta( $post->ID, '_bkx_billing_attorney', true );
		$status        = get_post_meta( $post->ID, '_bkx_status', true ) ?: 'active';

		$clients   = $this->get_clients();
		$attorneys = $this->get_attorneys();
		?>
		<table class="form-table">
			<tr>
				<th><label for="bkx_matter_number"><?php esc_html_e( 'Matter Number', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<input type="text" id="bkx_matter_number" name="bkx_matter_number" value="<?php echo esc_attr( $matter_number ); ?>" class="regular-text" readonly>
					<p class="description"><?php esc_html_e( 'Auto-generated matter number.', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_client_id"><?php esc_html_e( 'Client', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<select id="bkx_client_id" name="bkx_client_id" class="regular-text">
						<option value=""><?php esc_html_e( '— Select Client —', 'bkx-legal-professional' ); ?></option>
						<?php foreach ( $clients as $client ) : ?>
							<option value="<?php echo esc_attr( $client->ID ); ?>" <?php selected( $client_id, $client->ID ); ?>>
								<?php echo esc_html( $client->display_name . ' (' . $client->user_email . ')' ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_responsible_attorney"><?php esc_html_e( 'Responsible Attorney', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<select id="bkx_responsible_attorney" name="bkx_responsible_attorney" class="regular-text">
						<option value=""><?php esc_html_e( '— Select Attorney —', 'bkx-legal-professional' ); ?></option>
						<?php foreach ( $attorneys as $attorney ) : ?>
							<option value="<?php echo esc_attr( $attorney->ID ); ?>" <?php selected( $responsible, $attorney->ID ); ?>>
								<?php echo esc_html( $attorney->display_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_originating_attorney"><?php esc_html_e( 'Originating Attorney', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<select id="bkx_originating_attorney" name="bkx_originating_attorney" class="regular-text">
						<option value=""><?php esc_html_e( '— Select Attorney —', 'bkx-legal-professional' ); ?></option>
						<?php foreach ( $attorneys as $attorney ) : ?>
							<option value="<?php echo esc_attr( $attorney->ID ); ?>" <?php selected( $originating, $attorney->ID ); ?>>
								<?php echo esc_html( $attorney->display_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_billing_attorney"><?php esc_html_e( 'Billing Attorney', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<select id="bkx_billing_attorney" name="bkx_billing_attorney" class="regular-text">
						<option value=""><?php esc_html_e( '— Select Attorney —', 'bkx-legal-professional' ); ?></option>
						<?php foreach ( $attorneys as $attorney ) : ?>
							<option value="<?php echo esc_attr( $attorney->ID ); ?>" <?php selected( $billing, $attorney->ID ); ?>>
								<?php echo esc_html( $attorney->display_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="bkx_status"><?php esc_html_e( 'Status', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<select id="bkx_status" name="bkx_status" class="regular-text">
						<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'bkx-legal-professional' ); ?></option>
						<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'bkx-legal-professional' ); ?></option>
						<option value="on_hold" <?php selected( $status, 'on_hold' ); ?>><?php esc_html_e( 'On Hold', 'bkx-legal-professional' ); ?></option>
						<option value="closed" <?php selected( $status, 'closed' ); ?>><?php esc_html_e( 'Closed', 'bkx-legal-professional' ); ?></option>
						<option value="archived" <?php selected( $status, 'archived' ); ?>><?php esc_html_e( 'Archived', 'bkx-legal-professional' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render billing metabox.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_billing_metabox( $post ): void {
		$fee_arrangement     = get_post_meta( $post->ID, '_bkx_fee_arrangement', true ) ?: 'hourly';
		$hourly_rate         = get_post_meta( $post->ID, '_bkx_hourly_rate', true );
		$flat_fee            = get_post_meta( $post->ID, '_bkx_flat_fee', true );
		$contingency_percent = get_post_meta( $post->ID, '_bkx_contingency_percent', true );
		$estimated_value     = get_post_meta( $post->ID, '_bkx_estimated_value', true );
		?>
		<table class="form-table">
			<tr>
				<th><label for="bkx_fee_arrangement"><?php esc_html_e( 'Fee Arrangement', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<select id="bkx_fee_arrangement" name="bkx_fee_arrangement" class="regular-text">
						<option value="hourly" <?php selected( $fee_arrangement, 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'bkx-legal-professional' ); ?></option>
						<option value="flat" <?php selected( $fee_arrangement, 'flat' ); ?>><?php esc_html_e( 'Flat Fee', 'bkx-legal-professional' ); ?></option>
						<option value="contingency" <?php selected( $fee_arrangement, 'contingency' ); ?>><?php esc_html_e( 'Contingency', 'bkx-legal-professional' ); ?></option>
						<option value="hybrid" <?php selected( $fee_arrangement, 'hybrid' ); ?>><?php esc_html_e( 'Hybrid', 'bkx-legal-professional' ); ?></option>
						<option value="retainer" <?php selected( $fee_arrangement, 'retainer' ); ?>><?php esc_html_e( 'Retainer', 'bkx-legal-professional' ); ?></option>
						<option value="pro_bono" <?php selected( $fee_arrangement, 'pro_bono' ); ?>><?php esc_html_e( 'Pro Bono', 'bkx-legal-professional' ); ?></option>
					</select>
				</td>
			</tr>
			<tr class="bkx-fee-hourly">
				<th><label for="bkx_hourly_rate"><?php esc_html_e( 'Hourly Rate', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<input type="number" id="bkx_hourly_rate" name="bkx_hourly_rate" value="<?php echo esc_attr( $hourly_rate ); ?>" min="0" step="0.01" class="small-text">
				</td>
			</tr>
			<tr class="bkx-fee-flat">
				<th><label for="bkx_flat_fee"><?php esc_html_e( 'Flat Fee', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<input type="number" id="bkx_flat_fee" name="bkx_flat_fee" value="<?php echo esc_attr( $flat_fee ); ?>" min="0" step="0.01" class="small-text">
				</td>
			</tr>
			<tr class="bkx-fee-contingency">
				<th><label for="bkx_contingency_percent"><?php esc_html_e( 'Contingency %', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<input type="number" id="bkx_contingency_percent" name="bkx_contingency_percent" value="<?php echo esc_attr( $contingency_percent ); ?>" min="0" max="100" step="0.1" class="small-text">
				</td>
			</tr>
			<tr>
				<th><label for="bkx_estimated_value"><?php esc_html_e( 'Estimated Value', 'bkx-legal-professional' ); ?></label></th>
				<td>
					<input type="number" id="bkx_estimated_value" name="bkx_estimated_value" value="<?php echo esc_attr( $estimated_value ); ?>" min="0" step="0.01" class="small-text">
					<p class="description"><?php esc_html_e( 'Estimated total value of the matter.', 'bkx-legal-professional' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render dates metabox.
	 *
	 * @param WP_Post $post Post object.
	 * @return void
	 */
	public function render_dates_metabox( $post ): void {
		$open_date    = get_post_meta( $post->ID, '_bkx_open_date', true );
		$close_date   = get_post_meta( $post->ID, '_bkx_close_date', true );
		$statute_date = get_post_meta( $post->ID, '_bkx_statute_of_limitations', true );
		?>
		<p>
			<label for="bkx_open_date"><strong><?php esc_html_e( 'Open Date', 'bkx-legal-professional' ); ?></strong></label><br>
			<input type="date" id="bkx_open_date" name="bkx_open_date" value="<?php echo esc_attr( $open_date ); ?>" class="widefat">
		</p>
		<p>
			<label for="bkx_close_date"><strong><?php esc_html_e( 'Close Date', 'bkx-legal-professional' ); ?></strong></label><br>
			<input type="date" id="bkx_close_date" name="bkx_close_date" value="<?php echo esc_attr( $close_date ); ?>" class="widefat">
		</p>
		<p>
			<label for="bkx_statute_of_limitations"><strong><?php esc_html_e( 'Statute of Limitations', 'bkx-legal-professional' ); ?></strong></label><br>
			<input type="date" id="bkx_statute_of_limitations" name="bkx_statute_of_limitations" value="<?php echo esc_attr( $statute_date ); ?>" class="widefat">
			<span class="description"><?php esc_html_e( 'Critical deadline', 'bkx-legal-professional' ); ?></span>
		</p>
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
		if ( ! isset( $_POST['bkx_matter_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bkx_matter_nonce'] ) ), 'bkx_matter_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Generate matter number if empty.
		$matter_number = get_post_meta( $post_id, '_bkx_matter_number', true );
		if ( empty( $matter_number ) ) {
			$settings = get_option( 'bkx_legal_settings', array() );
			$prefix   = $settings['matter_number_prefix'] ?? 'M';
			$year     = gmdate( 'Y' );

			$sequence_key = 'bkx_legal_matter_sequence_' . $year;
			$sequence     = (int) get_option( $sequence_key, 0 ) + 1;
			update_option( $sequence_key, $sequence );

			$matter_number = sprintf( '%s%s-%04d', $prefix, $year, $sequence );
			update_post_meta( $post_id, '_bkx_matter_number', $matter_number );
		}

		// Save other fields.
		$fields = array(
			'client_id'             => 'absint',
			'responsible_attorney'  => 'absint',
			'originating_attorney'  => 'absint',
			'billing_attorney'      => 'absint',
			'status'                => 'sanitize_text_field',
			'fee_arrangement'       => 'sanitize_text_field',
			'hourly_rate'           => 'floatval',
			'flat_fee'              => 'floatval',
			'contingency_percent'   => 'floatval',
			'estimated_value'       => 'floatval',
			'open_date'             => 'sanitize_text_field',
			'close_date'            => 'sanitize_text_field',
			'statute_of_limitations' => 'sanitize_text_field',
		);

		foreach ( $fields as $field => $sanitize ) {
			$key = 'bkx_' . $field;
			if ( isset( $_POST[ $key ] ) ) {
				$value = call_user_func( $sanitize, wp_unslash( $_POST[ $key ] ) );
				update_post_meta( $post_id, '_bkx_' . $field, $value );
			}
		}
	}

	/**
	 * Get clients.
	 *
	 * @return array
	 */
	private function get_clients(): array {
		return get_users( array(
			'role__in' => array( 'subscriber', 'customer' ),
			'orderby'  => 'display_name',
			'order'    => 'ASC',
		) );
	}

	/**
	 * Get attorneys.
	 *
	 * @return array
	 */
	private function get_attorneys(): array {
		return get_users( array(
			'role__in' => array( 'administrator', 'editor', 'author' ),
			'orderby'  => 'display_name',
			'order'    => 'ASC',
		) );
	}
}
