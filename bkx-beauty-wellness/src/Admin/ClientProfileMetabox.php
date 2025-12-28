<?php
/**
 * Client Profile Metabox
 *
 * Displays client preferences and history on booking admin.
 *
 * @package BookingX\BeautyWellness\Admin
 * @since   1.0.0
 */

namespace BookingX\BeautyWellness\Admin;

use BookingX\BeautyWellness\Services\ClientPreferencesService;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ClientProfileMetabox
 *
 * @since 1.0.0
 */
class ClientProfileMetabox {

	/**
	 * Client preferences service.
	 *
	 * @var ClientPreferencesService
	 */
	private ClientPreferencesService $preferences_service;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->preferences_service = new ClientPreferencesService();
	}

	/**
	 * Initialize metabox.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'save_post_bkx_booking', array( $this, 'save_treatment_notes' ), 10, 2 );
	}

	/**
	 * Add metabox to booking post type.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function add_metabox(): void {
		add_meta_box(
			'bkx_client_profile',
			__( 'Client Profile', 'bkx-beauty-wellness' ),
			array( $this, 'render_metabox' ),
			'bkx_booking',
			'side',
			'default'
		);

		add_meta_box(
			'bkx_treatment_notes',
			__( 'Treatment Notes', 'bkx-beauty-wellness' ),
			array( $this, 'render_treatment_notes' ),
			'bkx_booking',
			'normal',
			'default'
		);
	}

	/**
	 * Render client profile metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_metabox( \WP_Post $post ): void {
		$customer_id = get_post_meta( $post->ID, 'customer_id', true );

		if ( ! $customer_id ) {
			echo '<p>' . esc_html__( 'No client associated with this booking.', 'bkx-beauty-wellness' ) . '</p>';
			return;
		}

		$preferences = $this->preferences_service->get_preferences( absint( $customer_id ) );
		$user        = get_userdata( $customer_id );

		if ( ! $user ) {
			echo '<p>' . esc_html__( 'Client not found.', 'bkx-beauty-wellness' ) . '</p>';
			return;
		}
		?>
		<div class="bkx-client-profile">
			<div class="bkx-client-header">
				<?php echo get_avatar( $customer_id, 60 ); ?>
				<div class="bkx-client-info">
					<strong><?php echo esc_html( $user->display_name ); ?></strong>
					<span><?php echo esc_html( $user->user_email ); ?></span>
				</div>
			</div>

			<?php if ( $preferences['consultation_completed'] ) : ?>
				<div class="bkx-profile-section">
					<h4><?php esc_html_e( 'Skin Profile', 'bkx-beauty-wellness' ); ?></h4>
					<?php if ( $preferences['skin_type'] ) : ?>
						<p>
							<strong><?php esc_html_e( 'Type:', 'bkx-beauty-wellness' ); ?></strong>
							<?php echo esc_html( ucfirst( $preferences['skin_type'] ) ); ?>
						</p>
					<?php endif; ?>

					<?php if ( ! empty( $preferences['skin_concerns'] ) ) : ?>
						<p>
							<strong><?php esc_html_e( 'Concerns:', 'bkx-beauty-wellness' ); ?></strong>
							<?php echo esc_html( implode( ', ', $preferences['skin_concerns'] ) ); ?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $preferences['allergies'] ) ) : ?>
				<div class="bkx-profile-section bkx-allergies-alert">
					<h4><?php esc_html_e( 'Allergies', 'bkx-beauty-wellness' ); ?></h4>
					<ul class="bkx-allergy-list">
						<?php foreach ( $preferences['allergies'] as $allergy ) : ?>
							<li class="bkx-allergy-item"><?php echo esc_html( $allergy ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $preferences['sensitivities'] ) ) : ?>
				<div class="bkx-profile-section">
					<h4><?php esc_html_e( 'Sensitivities', 'bkx-beauty-wellness' ); ?></h4>
					<p><?php echo esc_html( implode( ', ', $preferences['sensitivities'] ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $preferences['hair_type'] ) : ?>
				<div class="bkx-profile-section">
					<h4><?php esc_html_e( 'Hair Profile', 'bkx-beauty-wellness' ); ?></h4>
					<p>
						<strong><?php esc_html_e( 'Type:', 'bkx-beauty-wellness' ); ?></strong>
						<?php echo esc_html( $preferences['hair_type'] ); ?>
					</p>
					<?php if ( ! empty( $preferences['hair_concerns'] ) ) : ?>
						<p>
							<strong><?php esc_html_e( 'Concerns:', 'bkx-beauty-wellness' ); ?></strong>
							<?php echo esc_html( implode( ', ', $preferences['hair_concerns'] ) ); ?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $preferences['pressure_preference'] || $preferences['temperature_preference'] ) : ?>
				<div class="bkx-profile-section">
					<h4><?php esc_html_e( 'Service Preferences', 'bkx-beauty-wellness' ); ?></h4>
					<?php if ( $preferences['pressure_preference'] ) : ?>
						<p>
							<strong><?php esc_html_e( 'Pressure:', 'bkx-beauty-wellness' ); ?></strong>
							<?php echo esc_html( ucfirst( $preferences['pressure_preference'] ) ); ?>
						</p>
					<?php endif; ?>
					<?php if ( $preferences['temperature_preference'] ) : ?>
						<p>
							<strong><?php esc_html_e( 'Temperature:', 'bkx-beauty-wellness' ); ?></strong>
							<?php echo esc_html( ucfirst( $preferences['temperature_preference'] ) ); ?>
						</p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $preferences['preferred_stylist'] ) : ?>
				<div class="bkx-profile-section">
					<h4><?php esc_html_e( 'Preferred Stylist', 'bkx-beauty-wellness' ); ?></h4>
					<p><?php echo esc_html( get_the_title( $preferences['preferred_stylist'] ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $preferences['notes'] ) : ?>
				<div class="bkx-profile-section">
					<h4><?php esc_html_e( 'Notes', 'bkx-beauty-wellness' ); ?></h4>
					<p><?php echo esc_html( $preferences['notes'] ); ?></p>
				</div>
			<?php endif; ?>

			<?php $this->render_allergy_alerts( $post->ID, $customer_id ); ?>

			<div class="bkx-profile-actions">
				<a href="<?php echo esc_url( admin_url( 'user-edit.php?user_id=' . $customer_id . '#bkx-beauty-preferences' ) ); ?>" class="button">
					<?php esc_html_e( 'Edit Preferences', 'bkx-beauty-wellness' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&customer_id=' . $customer_id ) ); ?>" class="button">
					<?php esc_html_e( 'View History', 'bkx-beauty-wellness' ); ?>
				</a>
			</div>
		</div>

		<style>
			.bkx-client-profile { padding: 10px 0; }
			.bkx-client-header { display: flex; gap: 10px; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #ddd; }
			.bkx-client-info { display: flex; flex-direction: column; }
			.bkx-client-info strong { font-size: 14px; }
			.bkx-client-info span { color: #666; font-size: 12px; }
			.bkx-profile-section { margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #eee; }
			.bkx-profile-section h4 { margin: 0 0 5px; font-size: 12px; color: #666; text-transform: uppercase; }
			.bkx-profile-section p { margin: 3px 0; font-size: 13px; }
			.bkx-allergies-alert { background: #fff3cd; padding: 10px; border-radius: 4px; border-left: 4px solid #ffc107; }
			.bkx-allergy-list { margin: 5px 0 0; padding-left: 20px; }
			.bkx-allergy-item { color: #856404; font-weight: 500; }
			.bkx-profile-actions { display: flex; gap: 5px; margin-top: 15px; }
			.bkx-profile-actions .button { flex: 1; text-align: center; font-size: 11px; }
		</style>
		<?php
	}

	/**
	 * Render allergy alerts for current booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id  Booking ID.
	 * @param int $customer_id Customer ID.
	 * @return void
	 */
	private function render_allergy_alerts( int $booking_id, int $customer_id ): void {
		$base_id = get_post_meta( $booking_id, 'base_id', true );

		if ( ! $base_id ) {
			return;
		}

		$alerts = $this->preferences_service->get_allergy_alerts( $customer_id, absint( $base_id ) );

		if ( empty( $alerts ) ) {
			return;
		}
		?>
		<div class="bkx-booking-alerts">
			<h4><?php esc_html_e( 'Booking Alerts', 'bkx-beauty-wellness' ); ?></h4>
			<?php foreach ( $alerts as $alert ) : ?>
				<div class="bkx-alert bkx-alert-<?php echo esc_attr( $alert['severity'] ); ?>">
					<?php echo esc_html( $alert['message'] ); ?>
				</div>
			<?php endforeach; ?>
		</div>

		<style>
			.bkx-booking-alerts { margin-top: 15px; }
			.bkx-alert { padding: 8px 10px; margin-bottom: 5px; border-radius: 4px; font-size: 12px; }
			.bkx-alert-high { background: #fff3cd; border-left: 4px solid #ffc107; color: #856404; }
			.bkx-alert-critical { background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24; }
			.bkx-alert-medium { background: #d1ecf1; border-left: 4px solid #17a2b8; color: #0c5460; }
		</style>
		<?php
	}

	/**
	 * Render treatment notes metabox.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $post Post object.
	 * @return void
	 */
	public function render_treatment_notes( \WP_Post $post ): void {
		wp_nonce_field( 'bkx_treatment_notes', 'bkx_treatment_notes_nonce' );

		$notes         = get_post_meta( $post->ID, '_bkx_treatment_notes', true );
		$products_used = get_post_meta( $post->ID, '_bkx_products_used', true ) ?: array();
		$satisfaction  = get_post_meta( $post->ID, '_bkx_satisfaction_rating', true );
		?>
		<div class="bkx-treatment-notes-form">
			<p>
				<label for="bkx_treatment_notes"><strong><?php esc_html_e( 'Treatment Notes', 'bkx-beauty-wellness' ); ?></strong></label>
				<textarea name="bkx_treatment_notes" id="bkx_treatment_notes" rows="4" class="large-text"><?php echo esc_textarea( $notes ); ?></textarea>
				<span class="description"><?php esc_html_e( 'Notes about the treatment performed, techniques used, and observations.', 'bkx-beauty-wellness' ); ?></span>
			</p>

			<p>
				<label for="bkx_products_used"><strong><?php esc_html_e( 'Products Used', 'bkx-beauty-wellness' ); ?></strong></label>
				<input type="text" name="bkx_products_used" id="bkx_products_used" value="<?php echo esc_attr( implode( ', ', $products_used ) ); ?>" class="large-text">
				<span class="description"><?php esc_html_e( 'Comma-separated list of products used during treatment.', 'bkx-beauty-wellness' ); ?></span>
			</p>

			<p>
				<label for="bkx_satisfaction_rating"><strong><?php esc_html_e( 'Client Satisfaction', 'bkx-beauty-wellness' ); ?></strong></label>
				<select name="bkx_satisfaction_rating" id="bkx_satisfaction_rating">
					<option value=""><?php esc_html_e( 'Select...', 'bkx-beauty-wellness' ); ?></option>
					<option value="5" <?php selected( $satisfaction, '5' ); ?>><?php esc_html_e( 'Very Satisfied (5)', 'bkx-beauty-wellness' ); ?></option>
					<option value="4" <?php selected( $satisfaction, '4' ); ?>><?php esc_html_e( 'Satisfied (4)', 'bkx-beauty-wellness' ); ?></option>
					<option value="3" <?php selected( $satisfaction, '3' ); ?>><?php esc_html_e( 'Neutral (3)', 'bkx-beauty-wellness' ); ?></option>
					<option value="2" <?php selected( $satisfaction, '2' ); ?>><?php esc_html_e( 'Unsatisfied (2)', 'bkx-beauty-wellness' ); ?></option>
					<option value="1" <?php selected( $satisfaction, '1' ); ?>><?php esc_html_e( 'Very Unsatisfied (1)', 'bkx-beauty-wellness' ); ?></option>
				</select>
			</p>
		</div>
		<?php
	}

	/**
	 * Save treatment notes.
	 *
	 * @since 1.0.0
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_treatment_notes( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['bkx_treatment_notes_nonce'] ) || ! wp_verify_nonce( $_POST['bkx_treatment_notes_nonce'], 'bkx_treatment_notes' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['bkx_treatment_notes'] ) ) {
			update_post_meta( $post_id, '_bkx_treatment_notes', sanitize_textarea_field( $_POST['bkx_treatment_notes'] ) );
		}

		if ( isset( $_POST['bkx_products_used'] ) ) {
			$products = array_map( 'trim', explode( ',', sanitize_text_field( $_POST['bkx_products_used'] ) ) );
			$products = array_filter( $products );
			update_post_meta( $post_id, '_bkx_products_used', $products );
		}

		if ( isset( $_POST['bkx_satisfaction_rating'] ) ) {
			update_post_meta( $post_id, '_bkx_satisfaction_rating', sanitize_text_field( $_POST['bkx_satisfaction_rating'] ) );
		}
	}
}
