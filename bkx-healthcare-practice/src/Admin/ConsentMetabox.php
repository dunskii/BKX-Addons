<?php
/**
 * Consent Form Metabox.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

namespace BookingX\HealthcarePractice\Admin;

/**
 * Consent Metabox class.
 *
 * @since 1.0.0
 */
class ConsentMetabox {

	/**
	 * Render the consent form settings metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public static function render( \WP_Post $post ): void {
		wp_nonce_field( 'bkx_healthcare_save_meta', 'bkx_healthcare_nonce' );

		$version           = get_post_meta( $post->ID, '_bkx_consent_version', true ) ?: '1.0';
		$required          = get_post_meta( $post->ID, '_bkx_consent_required', true );
		$expiry_months     = get_post_meta( $post->ID, '_bkx_consent_expiry_months', true );
		$require_signature = get_post_meta( $post->ID, '_bkx_require_signature', true );
		$require_reconsent = get_post_meta( $post->ID, '_bkx_require_reconsent_on_update', true );
		?>
		<div class="bkx-consent-settings">
			<p>
				<label for="bkx_consent_version">
					<?php esc_html_e( 'Version', 'bkx-healthcare-practice' ); ?>
				</label>
				<input type="text" id="bkx_consent_version" name="bkx_consent_version"
					   value="<?php echo esc_attr( $version ); ?>" class="widefat">
				<span class="description">
					<?php esc_html_e( 'Update version when making significant changes.', 'bkx-healthcare-practice' ); ?>
				</span>
			</p>

			<p>
				<label>
					<input type="checkbox" name="bkx_consent_required" value="1"
						   <?php checked( $required ); ?>>
					<?php esc_html_e( 'Required consent', 'bkx-healthcare-practice' ); ?>
				</label>
				<br>
				<span class="description">
					<?php esc_html_e( 'Patients must complete this before booking.', 'bkx-healthcare-practice' ); ?>
				</span>
			</p>

			<p>
				<label>
					<input type="checkbox" name="bkx_require_signature" value="1"
						   <?php checked( $require_signature ); ?>>
					<?php esc_html_e( 'Require digital signature', 'bkx-healthcare-practice' ); ?>
				</label>
			</p>

			<p>
				<label>
					<input type="checkbox" name="bkx_require_reconsent_on_update" value="1"
						   <?php checked( $require_reconsent ); ?>>
					<?php esc_html_e( 'Require re-consent on version change', 'bkx-healthcare-practice' ); ?>
				</label>
			</p>

			<p>
				<label for="bkx_consent_expiry_months">
					<?php esc_html_e( 'Expiry Period', 'bkx-healthcare-practice' ); ?>
				</label>
				<select id="bkx_consent_expiry_months" name="bkx_consent_expiry_months">
					<option value="" <?php selected( $expiry_months, '' ); ?>>
						<?php esc_html_e( 'Never expires', 'bkx-healthcare-practice' ); ?>
					</option>
					<option value="6" <?php selected( $expiry_months, '6' ); ?>>
						<?php esc_html_e( '6 months', 'bkx-healthcare-practice' ); ?>
					</option>
					<option value="12" <?php selected( $expiry_months, '12' ); ?>>
						<?php esc_html_e( '12 months', 'bkx-healthcare-practice' ); ?>
					</option>
					<option value="24" <?php selected( $expiry_months, '24' ); ?>>
						<?php esc_html_e( '24 months', 'bkx-healthcare-practice' ); ?>
					</option>
					<option value="36" <?php selected( $expiry_months, '36' ); ?>>
						<?php esc_html_e( '36 months', 'bkx-healthcare-practice' ); ?>
					</option>
				</select>
			</p>

			<hr>

			<p>
				<strong><?php esc_html_e( 'Shortcode', 'bkx-healthcare-practice' ); ?></strong>
				<br>
				<code>[bkx_consent_form form_id="<?php echo esc_attr( $post->ID ); ?>"]</code>
			</p>
		</div>
		<?php
	}

	/**
	 * Save metabox data.
	 *
	 * @since 1.0.0
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function save( int $post_id ): void {
		if ( isset( $_POST['bkx_consent_version'] ) ) {
			update_post_meta( $post_id, '_bkx_consent_version', sanitize_text_field( wp_unslash( $_POST['bkx_consent_version'] ) ) );
		}

		update_post_meta( $post_id, '_bkx_consent_required', isset( $_POST['bkx_consent_required'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_bkx_require_signature', isset( $_POST['bkx_require_signature'] ) ? 1 : 0 );
		update_post_meta( $post_id, '_bkx_require_reconsent_on_update', isset( $_POST['bkx_require_reconsent_on_update'] ) ? 1 : 0 );

		if ( isset( $_POST['bkx_consent_expiry_months'] ) ) {
			$expiry = sanitize_text_field( wp_unslash( $_POST['bkx_consent_expiry_months'] ) );
			update_post_meta( $post_id, '_bkx_consent_expiry_months', $expiry );
		}
	}
}
