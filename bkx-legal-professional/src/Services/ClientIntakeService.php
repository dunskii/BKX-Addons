<?php
/**
 * Client Intake Service.
 *
 * Handles client intake forms and client management.
 *
 * @package BookingX\LegalProfessional
 * @since   1.0.0
 */

namespace BookingX\LegalProfessional\Services;

/**
 * Client Intake Service class.
 *
 * @since 1.0.0
 */
class ClientIntakeService {

	/**
	 * Singleton instance.
	 *
	 * @var ClientIntakeService|null
	 */
	private static ?ClientIntakeService $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return ClientIntakeService
	 */
	public static function get_instance(): ClientIntakeService {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		// Private constructor for singleton.
	}

	/**
	 * Save client intake form.
	 *
	 * @since 1.0.0
	 * @param int   $template_id Template ID.
	 * @param array $data        Form data.
	 * @return int|\WP_Error Intake ID or error.
	 */
	public function save_intake( int $template_id, array $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_client_intakes';
		$user_id    = get_current_user_id();

		// Encrypt sensitive data.
		$encrypted_data = $this->encrypt_sensitive_data( $data );

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'user_id'     => $user_id,
				'template_id' => $template_id,
				'form_data'   => wp_json_encode( $encrypted_data ),
				'status'      => 'submitted',
				'created_at'  => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new \WP_Error( 'db_error', __( 'Failed to save intake form', 'bkx-legal-professional' ) );
		}

		$intake_id = $wpdb->insert_id;

		// Update user meta.
		update_user_meta( $user_id, '_bkx_client_intake_id', $intake_id );
		update_user_meta( $user_id, '_bkx_is_client', true );

		// Store basic client info.
		if ( ! empty( $data['company'] ) ) {
			update_user_meta( $user_id, '_bkx_company', sanitize_text_field( $data['company'] ) );
		}

		return $intake_id;
	}

	/**
	 * Encrypt sensitive client data.
	 *
	 * @since 1.0.0
	 * @param array $data Raw data.
	 * @return array
	 */
	private function encrypt_sensitive_data( array $data ): array {
		$sensitive_fields = array( 'ssn', 'tax_id', 'bank_account', 'case_details' );

		if ( class_exists( 'BKX_Data_Encryption' ) ) {
			$encryption = new \BKX_Data_Encryption();

			foreach ( $sensitive_fields as $field ) {
				if ( isset( $data[ $field ] ) && ! empty( $data[ $field ] ) ) {
					$data[ $field ] = $encryption->encrypt( $data[ $field ] );
					$data[ $field . '_encrypted' ] = true;
				}
			}
		}

		return $data;
	}

	/**
	 * Check if user has completed intake.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function user_has_intake( int $user_id ): bool {
		$intake_id = get_user_meta( $user_id, '_bkx_client_intake_id', true );
		return ! empty( $intake_id );
	}

	/**
	 * Link intake to booking.
	 *
	 * @since 1.0.0
	 * @param int   $booking_id   Booking ID.
	 * @param array $booking_data Booking data.
	 * @return void
	 */
	public function link_to_booking( int $booking_id, array $booking_data ): void {
		$user_id = isset( $booking_data['user_id'] ) ? absint( $booking_data['user_id'] ) : get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		$intake_id = get_user_meta( $user_id, '_bkx_client_intake_id', true );

		if ( $intake_id ) {
			update_post_meta( $booking_id, 'client_intake_id', $intake_id );
		}
	}

	/**
	 * Search clients.
	 *
	 * @since 1.0.0
	 * @param string $search Search term.
	 * @return array
	 */
	public function search_clients( string $search ): array {
		global $wpdb;

		$search_term = '%' . $wpdb->esc_like( $search ) . '%';

		$users = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.ID, u.display_name, u.user_email
				 FROM {$wpdb->users} u
				 INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
				 WHERE um.meta_key = '_bkx_is_client'
				   AND um.meta_value = '1'
				   AND (u.display_name LIKE %s OR u.user_email LIKE %s)
				 LIMIT 20",
				$search_term,
				$search_term
			)
		);

		$results = array();

		foreach ( $users as $user ) {
			$results[] = array(
				'id'      => $user->ID,
				'name'    => $user->display_name,
				'email'   => $user->user_email,
				'company' => get_user_meta( $user->ID, '_bkx_company', true ),
			);
		}

		return $results;
	}

	/**
	 * Get client portal data.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_client_portal_data( int $user_id ): array {
		return array(
			'profile'     => $this->get_client_profile( $user_id ),
			'matters'     => CaseManagementService::get_instance()->get_client_matters( $user_id ),
			'appointments' => $this->get_client_appointments( $user_id ),
			'documents'   => DocumentService::get_instance()->get_client_documents( $user_id ),
			'invoices'    => BillingService::get_instance()->get_client_invoices( $user_id ),
		);
	}

	/**
	 * Get client profile.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function get_client_profile( int $user_id ): array {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return array();
		}

		return array(
			'id'      => $user_id,
			'name'    => $user->display_name,
			'email'   => $user->user_email,
			'phone'   => get_user_meta( $user_id, 'phone', true ),
			'company' => get_user_meta( $user_id, '_bkx_company', true ),
			'address' => get_user_meta( $user_id, '_bkx_address', true ),
		);
	}

	/**
	 * Get client appointments.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function get_client_appointments( int $user_id ): array {
		$bookings = get_posts( array(
			'post_type'      => 'bkx_booking',
			'posts_per_page' => 20,
			'meta_query'     => array(
				array(
					'key'   => 'customer_id',
					'value' => $user_id,
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => 'booking_date',
			'order'          => 'DESC',
		) );

		$appointments = array();

		foreach ( $bookings as $booking ) {
			$seat_id = get_post_meta( $booking->ID, 'seat_id', true );
			$base_id = get_post_meta( $booking->ID, 'base_id', true );

			$appointments[] = array(
				'id'       => $booking->ID,
				'date'     => get_post_meta( $booking->ID, 'booking_date', true ),
				'time'     => get_post_meta( $booking->ID, 'booking_time', true ),
				'status'   => $booking->post_status,
				'attorney' => $seat_id ? get_the_title( $seat_id ) : '',
				'service'  => $base_id ? get_the_title( $base_id ) : '',
			);
		}

		return $appointments;
	}

	/**
	 * Render intake form.
	 *
	 * @since 1.0.0
	 * @param int    $template_id   Template ID.
	 * @param string $practice_area Practice area filter.
	 * @return string
	 */
	public function render_form( int $template_id, string $practice_area = '' ): string {
		if ( ! $template_id ) {
			// Get default template.
			$templates = get_posts( array(
				'post_type'      => 'bkx_intake_template',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
			) );

			if ( empty( $templates ) ) {
				return '<p class="bkx-error">' . esc_html__( 'No intake form configured', 'bkx-legal-professional' ) . '</p>';
			}

			$template_id = $templates[0]->ID;
		}

		$template = get_post( $template_id );

		if ( ! $template || 'bkx_intake_template' !== $template->post_type ) {
			return '<p class="bkx-error">' . esc_html__( 'Intake form not found', 'bkx-legal-professional' ) . '</p>';
		}

		$fields = get_post_meta( $template_id, '_bkx_intake_fields', true );

		if ( ! is_array( $fields ) || empty( $fields ) ) {
			$fields = $this->get_default_fields();
		}

		ob_start();
		?>
		<div class="bkx-client-intake-form" data-template-id="<?php echo esc_attr( $template_id ); ?>">
			<h3><?php echo esc_html( $template->post_title ); ?></h3>

			<?php
			$settings = get_option( 'bkx_legal_settings', array() );
			if ( ! empty( $settings['enable_confidentiality_notice'] ) ) :
				?>
				<div class="bkx-confidentiality-notice">
					<strong><?php esc_html_e( 'Confidentiality Notice:', 'bkx-legal-professional' ); ?></strong>
					<?php esc_html_e( 'The information you provide is protected by attorney-client privilege and will be kept strictly confidential.', 'bkx-legal-professional' ); ?>
				</div>
			<?php endif; ?>

			<form id="bkx-intake-form" class="bkx-intake-form">
				<?php wp_nonce_field( 'bkx_legal_frontend', 'bkx_legal_nonce' ); ?>
				<input type="hidden" name="template_id" value="<?php echo esc_attr( $template_id ); ?>">

				<?php $this->render_form_fields( $fields ); ?>

				<div class="bkx-form-actions">
					<button type="submit" class="bkx-btn bkx-btn-primary">
						<?php esc_html_e( 'Submit Intake Form', 'bkx-legal-professional' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render form fields.
	 *
	 * @since 1.0.0
	 * @param array $fields Form fields.
	 * @return void
	 */
	private function render_form_fields( array $fields ): void {
		$current_section = '';

		foreach ( $fields as $field ) {
			if ( isset( $field['section'] ) && $field['section'] !== $current_section ) {
				if ( $current_section ) {
					echo '</fieldset>';
				}
				$current_section = $field['section'];
				echo '<fieldset class="bkx-intake-section">';
				echo '<legend>' . esc_html( $current_section ) . '</legend>';
			}

			$this->render_field( $field );
		}

		if ( $current_section ) {
			echo '</fieldset>';
		}
	}

	/**
	 * Render a single field.
	 *
	 * @since 1.0.0
	 * @param array $field Field configuration.
	 * @return void
	 */
	private function render_field( array $field ): void {
		$name     = sanitize_key( $field['name'] ?? '' );
		$label    = $field['label'] ?? '';
		$type     = $field['type'] ?? 'text';
		$required = ! empty( $field['required'] );
		$options  = $field['options'] ?? array();

		$required_attr = $required ? 'required' : '';
		$required_mark = $required ? '<span class="required">*</span>' : '';

		echo '<div class="bkx-form-field bkx-field-' . esc_attr( $type ) . '">';
		echo '<label for="bkx_' . esc_attr( $name ) . '">' . esc_html( $label ) . wp_kses_post( $required_mark ) . '</label>';

		switch ( $type ) {
			case 'text':
			case 'email':
			case 'tel':
			case 'date':
				printf(
					'<input type="%s" id="bkx_%s" name="form_data[%s]" %s>',
					esc_attr( $type ),
					esc_attr( $name ),
					esc_attr( $name ),
					esc_attr( $required_attr )
				);
				break;

			case 'textarea':
				printf(
					'<textarea id="bkx_%s" name="form_data[%s]" rows="4" %s></textarea>',
					esc_attr( $name ),
					esc_attr( $name ),
					esc_attr( $required_attr )
				);
				break;

			case 'select':
				printf( '<select id="bkx_%s" name="form_data[%s]" %s>', esc_attr( $name ), esc_attr( $name ), esc_attr( $required_attr ) );
				echo '<option value="">' . esc_html__( 'Select...', 'bkx-legal-professional' ) . '</option>';
				foreach ( $options as $opt_value => $opt_label ) {
					printf( '<option value="%s">%s</option>', esc_attr( $opt_value ), esc_html( $opt_label ) );
				}
				echo '</select>';
				break;

			case 'radio':
				echo '<div class="bkx-radio-group">';
				foreach ( $options as $opt_value => $opt_label ) {
					printf(
						'<label><input type="radio" name="form_data[%s]" value="%s"> %s</label>',
						esc_attr( $name ),
						esc_attr( $opt_value ),
						esc_html( $opt_label )
					);
				}
				echo '</div>';
				break;

			case 'checkbox':
				printf(
					'<input type="checkbox" id="bkx_%s" name="form_data[%s]" value="1">',
					esc_attr( $name ),
					esc_attr( $name )
				);
				break;
		}

		echo '</div>';
	}

	/**
	 * Get default intake fields.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private function get_default_fields(): array {
		return array(
			array(
				'name'     => 'full_name',
				'label'    => __( 'Full Legal Name', 'bkx-legal-professional' ),
				'type'     => 'text',
				'section'  => __( 'Personal Information', 'bkx-legal-professional' ),
				'required' => true,
			),
			array(
				'name'     => 'email',
				'label'    => __( 'Email Address', 'bkx-legal-professional' ),
				'type'     => 'email',
				'required' => true,
			),
			array(
				'name'     => 'phone',
				'label'    => __( 'Phone Number', 'bkx-legal-professional' ),
				'type'     => 'tel',
				'required' => true,
			),
			array(
				'name'     => 'address',
				'label'    => __( 'Address', 'bkx-legal-professional' ),
				'type'     => 'textarea',
			),
			array(
				'name'     => 'practice_area',
				'label'    => __( 'Type of Legal Matter', 'bkx-legal-professional' ),
				'type'     => 'select',
				'section'  => __( 'Matter Information', 'bkx-legal-professional' ),
				'required' => true,
				'options'  => array(
					'family'     => __( 'Family Law', 'bkx-legal-professional' ),
					'criminal'   => __( 'Criminal Defense', 'bkx-legal-professional' ),
					'personal_injury' => __( 'Personal Injury', 'bkx-legal-professional' ),
					'business'   => __( 'Business Law', 'bkx-legal-professional' ),
					'real_estate' => __( 'Real Estate', 'bkx-legal-professional' ),
					'estate'     => __( 'Estate Planning', 'bkx-legal-professional' ),
					'immigration' => __( 'Immigration', 'bkx-legal-professional' ),
					'other'      => __( 'Other', 'bkx-legal-professional' ),
				),
			),
			array(
				'name'     => 'case_summary',
				'label'    => __( 'Brief Description of Your Legal Matter', 'bkx-legal-professional' ),
				'type'     => 'textarea',
				'required' => true,
			),
			array(
				'name'     => 'urgency',
				'label'    => __( 'Urgency Level', 'bkx-legal-professional' ),
				'type'     => 'radio',
				'options'  => array(
					'routine'   => __( 'Routine - No immediate deadline', 'bkx-legal-professional' ),
					'moderate'  => __( 'Moderate - Has upcoming deadline', 'bkx-legal-professional' ),
					'urgent'    => __( 'Urgent - Immediate attention needed', 'bkx-legal-professional' ),
				),
			),
			array(
				'name'     => 'opposing_party',
				'label'    => __( 'Opposing Party Name (if applicable)', 'bkx-legal-professional' ),
				'type'     => 'text',
				'section'  => __( 'Conflict Check Information', 'bkx-legal-professional' ),
			),
			array(
				'name'     => 'opposing_counsel',
				'label'    => __( 'Opposing Counsel (if known)', 'bkx-legal-professional' ),
				'type'     => 'text',
			),
			array(
				'name'     => 'referral_source',
				'label'    => __( 'How did you hear about us?', 'bkx-legal-professional' ),
				'type'     => 'select',
				'section'  => __( 'Additional Information', 'bkx-legal-professional' ),
				'options'  => array(
					'referral'   => __( 'Client Referral', 'bkx-legal-professional' ),
					'online'     => __( 'Online Search', 'bkx-legal-professional' ),
					'bar_assoc'  => __( 'Bar Association', 'bkx-legal-professional' ),
					'advertising' => __( 'Advertising', 'bkx-legal-professional' ),
					'other'      => __( 'Other', 'bkx-legal-professional' ),
				),
			),
		);
	}

	/**
	 * Render client portal.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_client_portal( array $atts ): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_prompt();
		}

		$user_id = get_current_user_id();

		if ( ! $this->user_has_intake( $user_id ) ) {
			return '<div class="bkx-portal-notice">' .
				'<p>' . esc_html__( 'Please complete the client intake form to access the portal.', 'bkx-legal-professional' ) . '</p>' .
				'</div>';
		}

		$data = $this->get_client_portal_data( $user_id );

		ob_start();
		?>
		<div class="bkx-client-portal">
			<div class="bkx-portal-header">
				<h2><?php esc_html_e( 'Client Portal', 'bkx-legal-professional' ); ?></h2>
				<p class="bkx-welcome"><?php printf( esc_html__( 'Welcome, %s', 'bkx-legal-professional' ), esc_html( $data['profile']['name'] ) ); ?></p>
			</div>

			<div class="bkx-portal-tabs">
				<?php if ( 'yes' === $atts['show_matters'] ) : ?>
					<button class="bkx-tab-btn active" data-tab="matters"><?php esc_html_e( 'My Matters', 'bkx-legal-professional' ); ?></button>
				<?php endif; ?>
				<button class="bkx-tab-btn" data-tab="appointments"><?php esc_html_e( 'Appointments', 'bkx-legal-professional' ); ?></button>
				<?php if ( 'yes' === $atts['show_documents'] ) : ?>
					<button class="bkx-tab-btn" data-tab="documents"><?php esc_html_e( 'Documents', 'bkx-legal-professional' ); ?></button>
				<?php endif; ?>
				<?php if ( 'yes' === $atts['show_billing'] ) : ?>
					<button class="bkx-tab-btn" data-tab="billing"><?php esc_html_e( 'Billing', 'bkx-legal-professional' ); ?></button>
				<?php endif; ?>
			</div>

			<div class="bkx-portal-content">
				<?php if ( 'yes' === $atts['show_matters'] ) : ?>
					<div class="bkx-tab-pane active" id="tab-matters">
						<?php $this->render_matters_tab( $data['matters'] ); ?>
					</div>
				<?php endif; ?>

				<div class="bkx-tab-pane" id="tab-appointments">
					<?php $this->render_appointments_tab( $data['appointments'] ); ?>
				</div>

				<?php if ( 'yes' === $atts['show_documents'] ) : ?>
					<div class="bkx-tab-pane" id="tab-documents">
						<?php $this->render_documents_tab( $data['documents'] ); ?>
					</div>
				<?php endif; ?>

				<?php if ( 'yes' === $atts['show_billing'] ) : ?>
					<div class="bkx-tab-pane" id="tab-billing">
						<?php $this->render_billing_tab( $data['invoices'] ); ?>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render login prompt.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private function render_login_prompt(): string {
		ob_start();
		?>
		<div class="bkx-portal-login">
			<h3><?php esc_html_e( 'Client Portal', 'bkx-legal-professional' ); ?></h3>
			<p><?php esc_html_e( 'Please log in to access your client portal.', 'bkx-legal-professional' ); ?></p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="bkx-btn bkx-btn-primary">
				<?php esc_html_e( 'Log In', 'bkx-legal-professional' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render matters tab.
	 *
	 * @since 1.0.0
	 * @param array $matters Client matters.
	 * @return void
	 */
	private function render_matters_tab( array $matters ): void {
		if ( empty( $matters ) ) {
			echo '<p class="bkx-no-data">' . esc_html__( 'No active matters.', 'bkx-legal-professional' ) . '</p>';
			return;
		}
		?>
		<div class="bkx-matters-list">
			<?php foreach ( $matters as $matter ) : ?>
				<div class="bkx-matter-card">
					<h4><?php echo esc_html( $matter['title'] ); ?></h4>
					<p class="bkx-matter-number"><?php echo esc_html( $matter['matter_number'] ); ?></p>
					<span class="bkx-status-badge"><?php echo esc_html( $matter['status'] ); ?></span>
					<p class="bkx-matter-attorney"><?php echo esc_html( $matter['attorney'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render appointments tab.
	 *
	 * @since 1.0.0
	 * @param array $appointments Appointments.
	 * @return void
	 */
	private function render_appointments_tab( array $appointments ): void {
		$upcoming = array_filter( $appointments, function ( $apt ) {
			return strtotime( $apt['date'] ) >= strtotime( 'today' );
		} );
		?>
		<div class="bkx-section-header">
			<h3><?php esc_html_e( 'Upcoming Appointments', 'bkx-legal-professional' ); ?></h3>
			<a href="<?php echo esc_url( home_url( '/schedule-consultation/' ) ); ?>" class="bkx-btn">
				<?php esc_html_e( 'Schedule New', 'bkx-legal-professional' ); ?>
			</a>
		</div>

		<?php if ( empty( $upcoming ) ) : ?>
			<p class="bkx-no-data"><?php esc_html_e( 'No upcoming appointments.', 'bkx-legal-professional' ); ?></p>
		<?php else : ?>
			<table class="bkx-appointments-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'bkx-legal-professional' ); ?></th>
						<th><?php esc_html_e( 'Time', 'bkx-legal-professional' ); ?></th>
						<th><?php esc_html_e( 'Attorney', 'bkx-legal-professional' ); ?></th>
						<th><?php esc_html_e( 'Service', 'bkx-legal-professional' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $upcoming as $apt ) : ?>
						<tr>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $apt['date'] ) ) ); ?></td>
							<td><?php echo esc_html( $apt['time'] ); ?></td>
							<td><?php echo esc_html( $apt['attorney'] ); ?></td>
							<td><?php echo esc_html( $apt['service'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}

	/**
	 * Render documents tab.
	 *
	 * @since 1.0.0
	 * @param array $documents Documents.
	 * @return void
	 */
	private function render_documents_tab( array $documents ): void {
		if ( empty( $documents ) ) {
			echo '<p class="bkx-no-data">' . esc_html__( 'No documents available.', 'bkx-legal-professional' ) . '</p>';
			return;
		}
		?>
		<table class="bkx-documents-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Document', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Matter', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Date', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Action', 'bkx-legal-professional' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $documents as $doc ) : ?>
					<tr>
						<td><?php echo esc_html( $doc['title'] ); ?></td>
						<td><?php echo esc_html( $doc['matter'] ); ?></td>
						<td><?php echo esc_html( $doc['date'] ); ?></td>
						<td><a href="<?php echo esc_url( $doc['url'] ); ?>" target="_blank"><?php esc_html_e( 'View', 'bkx-legal-professional' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render billing tab.
	 *
	 * @since 1.0.0
	 * @param array $invoices Invoices.
	 * @return void
	 */
	private function render_billing_tab( array $invoices ): void {
		if ( empty( $invoices ) ) {
			echo '<p class="bkx-no-data">' . esc_html__( 'No invoices found.', 'bkx-legal-professional' ) . '</p>';
			return;
		}
		?>
		<table class="bkx-invoices-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Invoice #', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Date', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Status', 'bkx-legal-professional' ); ?></th>
					<th><?php esc_html_e( 'Action', 'bkx-legal-professional' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $invoices as $inv ) : ?>
					<tr>
						<td><?php echo esc_html( $inv['number'] ); ?></td>
						<td><?php echo esc_html( $inv['date'] ); ?></td>
						<td><?php echo esc_html( $inv['amount'] ); ?></td>
						<td><span class="bkx-status-<?php echo esc_attr( $inv['status'] ); ?>"><?php echo esc_html( $inv['status'] ); ?></span></td>
						<td>
							<a href="<?php echo esc_url( $inv['url'] ); ?>" target="_blank"><?php esc_html_e( 'View', 'bkx-legal-professional' ); ?></a>
							<?php if ( 'unpaid' === $inv['status'] ) : ?>
								<a href="<?php echo esc_url( $inv['pay_url'] ); ?>" class="bkx-btn bkx-btn-small"><?php esc_html_e( 'Pay Now', 'bkx-legal-professional' ); ?></a>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
