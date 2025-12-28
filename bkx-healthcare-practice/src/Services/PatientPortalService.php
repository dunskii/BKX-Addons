<?php
/**
 * Patient Portal Service.
 *
 * Handles patient portal functionality including appointments, documents, and messaging.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

namespace BookingX\HealthcarePractice\Services;

/**
 * Patient Portal Service class.
 *
 * @since 1.0.0
 */
class PatientPortalService {

	/**
	 * Singleton instance.
	 *
	 * @var PatientPortalService|null
	 */
	private static ?PatientPortalService $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return PatientPortalService
	 */
	public static function get_instance(): PatientPortalService {
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
	 * Get portal data for a user.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array Portal data.
	 */
	public function get_portal_data( int $user_id ): array {
		return array(
			'patient'       => $this->get_patient_profile( $user_id ),
			'appointments'  => $this->get_patient_appointments( $user_id ),
			'documents'     => $this->get_patient_documents( $user_id ),
			'messages'      => $this->get_patient_messages( $user_id ),
			'consents'      => ConsentService::get_instance()->get_user_consent_history( $user_id ),
			'intake_forms'  => PatientIntakeService::get_instance()->get_patient_intakes( $user_id ),
		);
	}

	/**
	 * Get patient profile.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_patient_profile( int $user_id ): array {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return array();
		}

		return array(
			'id'           => $user_id,
			'name'         => $user->display_name,
			'email'        => $user->user_email,
			'phone'        => get_user_meta( $user_id, 'phone', true ),
			'date_of_birth' => get_user_meta( $user_id, '_bkx_date_of_birth', true ),
			'address'      => get_user_meta( $user_id, '_bkx_address', true ),
			'emergency_contact' => get_user_meta( $user_id, '_bkx_emergency_contact', true ),
			'insurance'    => $this->get_patient_insurance( $user_id ),
		);
	}

	/**
	 * Get patient insurance info.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function get_patient_insurance( int $user_id ): array {
		$insurance = get_user_meta( $user_id, '_bkx_insurance_info', true );

		if ( ! is_array( $insurance ) ) {
			return array();
		}

		return $insurance;
	}

	/**
	 * Get patient appointments.
	 *
	 * @since 1.0.0
	 * @param int    $user_id User ID.
	 * @param string $status  Appointment status filter.
	 * @return array
	 */
	public function get_patient_appointments( int $user_id, string $status = 'all' ): array {
		$args = array(
			'post_type'      => 'bkx_booking',
			'posts_per_page' => 50,
			'meta_query'     => array(
				array(
					'key'   => 'customer_id',
					'value' => $user_id,
				),
			),
			'orderby'        => 'meta_value',
			'meta_key'       => 'booking_date',
			'order'          => 'DESC',
		);

		if ( 'upcoming' === $status ) {
			$args['meta_query'][] = array(
				'key'     => 'booking_date',
				'value'   => current_time( 'Y-m-d' ),
				'compare' => '>=',
				'type'    => 'DATE',
			);
			$args['order'] = 'ASC';
		} elseif ( 'past' === $status ) {
			$args['meta_query'][] = array(
				'key'     => 'booking_date',
				'value'   => current_time( 'Y-m-d' ),
				'compare' => '<',
				'type'    => 'DATE',
			);
		}

		$bookings = get_posts( $args );
		$appointments = array();

		foreach ( $bookings as $booking ) {
			$appointments[] = $this->format_appointment( $booking );
		}

		return $appointments;
	}

	/**
	 * Format appointment data.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $booking Booking post object.
	 * @return array
	 */
	private function format_appointment( \WP_Post $booking ): array {
		$seat_id = get_post_meta( $booking->ID, 'seat_id', true );
		$base_id = get_post_meta( $booking->ID, 'base_id', true );

		return array(
			'id'            => $booking->ID,
			'date'          => get_post_meta( $booking->ID, 'booking_date', true ),
			'time'          => get_post_meta( $booking->ID, 'booking_time', true ),
			'status'        => $booking->post_status,
			'provider'      => $seat_id ? get_the_title( $seat_id ) : '',
			'service'       => $base_id ? get_the_title( $base_id ) : '',
			'telemedicine'  => get_post_meta( $booking->ID, 'telemedicine_enabled', true ),
			'notes'         => get_post_meta( $booking->ID, 'customer_notes', true ),
		);
	}

	/**
	 * Get patient documents.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_patient_documents( int $user_id ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_patient_documents';

		// Check if table exists.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$table_name
			)
		);

		if ( ! $table_exists ) {
			return array();
		}

		$documents = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC",
				$user_id
			),
			ARRAY_A
		);

		return $documents ?: array();
	}

	/**
	 * Get patient messages.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_patient_messages( int $user_id ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_patient_messages';

		// Check if table exists.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$table_name
			)
		);

		if ( ! $table_exists ) {
			return array();
		}

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d OR recipient_id = %d ORDER BY created_at DESC LIMIT 50",
				$user_id,
				$user_id
			),
			ARRAY_A
		);

		return $messages ?: array();
	}

	/**
	 * Get patient history.
	 *
	 * @since 1.0.0
	 * @param int $patient_id Patient/User ID.
	 * @return array
	 */
	public function get_patient_history( int $patient_id ): array {
		return array(
			'profile'       => $this->get_patient_profile( $patient_id ),
			'appointments'  => $this->get_patient_appointments( $patient_id ),
			'intake_forms'  => PatientIntakeService::get_instance()->get_patient_intakes( $patient_id ),
			'consents'      => ConsentService::get_instance()->get_user_consent_history( $patient_id ),
			'documents'     => $this->get_patient_documents( $patient_id ),
			'notes'         => $this->get_clinical_notes( $patient_id ),
		);
	}

	/**
	 * Get clinical notes for a patient.
	 *
	 * @since 1.0.0
	 * @param int $patient_id Patient ID.
	 * @return array
	 */
	private function get_clinical_notes( int $patient_id ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_clinical_notes';

		// Check if table exists.
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$table_name
			)
		);

		if ( ! $table_exists ) {
			return array();
		}

		$notes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE patient_id = %d ORDER BY created_at DESC",
				$patient_id
			),
			ARRAY_A
		);

		return $notes ?: array();
	}

	/**
	 * Search patients.
	 *
	 * @since 1.0.0
	 * @param string $search Search term.
	 * @return array
	 */
	public function search_patients( string $search ): array {
		global $wpdb;

		$search_term = '%' . $wpdb->esc_like( $search ) . '%';

		// Search in user data.
		$users = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT u.ID, u.display_name, u.user_email
				 FROM {$wpdb->users} u
				 WHERE u.display_name LIKE %s
				    OR u.user_email LIKE %s
				 LIMIT 20",
				$search_term,
				$search_term
			)
		);

		$results = array();

		foreach ( $users as $user ) {
			// Check if user has bookings.
			$has_bookings = get_posts( array(
				'post_type'      => 'bkx_booking',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'   => 'customer_id',
						'value' => $user->ID,
					),
				),
			) );

			if ( ! empty( $has_bookings ) ) {
				$results[] = array(
					'id'    => $user->ID,
					'name'  => $user->display_name,
					'email' => $user->user_email,
					'phone' => get_user_meta( $user->ID, 'phone', true ),
				);
			}
		}

		return $results;
	}

	/**
	 * Export patient data (GDPR compliant).
	 *
	 * @since 1.0.0
	 * @param int $patient_id Patient ID.
	 * @return array Exportable data.
	 */
	public function export_patient_data( int $patient_id ): array {
		$data = array(
			'personal_info'  => $this->get_patient_profile( $patient_id ),
			'appointments'   => $this->get_patient_appointments( $patient_id ),
			'intake_forms'   => array(),
			'consent_records' => ConsentService::get_instance()->get_user_consent_history( $patient_id ),
			'documents'      => $this->get_patient_documents( $patient_id ),
		);

		// Get intake form data.
		$intakes = PatientIntakeService::get_instance()->get_patient_intakes( $patient_id );
		foreach ( $intakes as $intake ) {
			$intake_data = PatientIntakeService::get_instance()->get_intake_data( absint( $intake['id'] ) );
			if ( $intake_data ) {
				$data['intake_forms'][] = $intake_data;
			}
		}

		// Remove encrypted fields or mark as protected.
		$data = $this->sanitize_export_data( $data );

		return $data;
	}

	/**
	 * Sanitize data for export.
	 *
	 * @since 1.0.0
	 * @param array $data Raw data.
	 * @return array Sanitized data.
	 */
	private function sanitize_export_data( array $data ): array {
		// Remove any remaining encrypted markers.
		array_walk_recursive( $data, function ( &$value, $key ) {
			if ( strpos( $key, '_encrypted' ) !== false ) {
				$value = '[ENCRYPTED]';
			}
		} );

		return $data;
	}

	/**
	 * Render patient portal.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public function render_portal( array $atts ): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_prompt();
		}

		$user_id = get_current_user_id();
		$data    = $this->get_portal_data( $user_id );

		ob_start();
		?>
		<div class="bkx-patient-portal">
			<div class="bkx-portal-header">
				<h2><?php esc_html_e( 'Patient Portal', 'bkx-healthcare-practice' ); ?></h2>
				<p class="bkx-welcome-message">
					<?php
					printf(
						/* translators: %s: Patient name */
						esc_html__( 'Welcome, %s', 'bkx-healthcare-practice' ),
						esc_html( $data['patient']['name'] )
					);
					?>
				</p>
			</div>

			<div class="bkx-portal-tabs">
				<button class="bkx-tab-btn active" data-tab="appointments">
					<?php esc_html_e( 'Appointments', 'bkx-healthcare-practice' ); ?>
				</button>
				<?php if ( 'yes' === $atts['show_documents'] ) : ?>
					<button class="bkx-tab-btn" data-tab="documents">
						<?php esc_html_e( 'Documents', 'bkx-healthcare-practice' ); ?>
					</button>
				<?php endif; ?>
				<?php if ( 'yes' === $atts['show_messages'] ) : ?>
					<button class="bkx-tab-btn" data-tab="messages">
						<?php esc_html_e( 'Messages', 'bkx-healthcare-practice' ); ?>
						<?php
						$unread = $this->count_unread_messages( $user_id );
						if ( $unread > 0 ) {
							echo '<span class="bkx-badge">' . esc_html( $unread ) . '</span>';
						}
						?>
					</button>
				<?php endif; ?>
				<button class="bkx-tab-btn" data-tab="profile">
					<?php esc_html_e( 'My Profile', 'bkx-healthcare-practice' ); ?>
				</button>
			</div>

			<div class="bkx-portal-content">
				<!-- Appointments Tab -->
				<div class="bkx-tab-pane active" id="tab-appointments">
					<?php $this->render_appointments_section( $data['appointments'] ); ?>
				</div>

				<!-- Documents Tab -->
				<?php if ( 'yes' === $atts['show_documents'] ) : ?>
					<div class="bkx-tab-pane" id="tab-documents">
						<?php $this->render_documents_section( $data['documents'] ); ?>
					</div>
				<?php endif; ?>

				<!-- Messages Tab -->
				<?php if ( 'yes' === $atts['show_messages'] ) : ?>
					<div class="bkx-tab-pane" id="tab-messages">
						<?php $this->render_messages_section( $data['messages'] ); ?>
					</div>
				<?php endif; ?>

				<!-- Profile Tab -->
				<div class="bkx-tab-pane" id="tab-profile">
					<?php $this->render_profile_section( $data['patient'] ); ?>
				</div>
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
		<div class="bkx-portal-login-required">
			<h3><?php esc_html_e( 'Patient Portal', 'bkx-healthcare-practice' ); ?></h3>
			<p><?php esc_html_e( 'Please log in to access your patient portal.', 'bkx-healthcare-practice' ); ?></p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="bkx-btn bkx-btn-primary">
				<?php esc_html_e( 'Log In', 'bkx-healthcare-practice' ); ?>
			</a>
			<p class="bkx-register-link">
				<?php esc_html_e( 'New patient?', 'bkx-healthcare-practice' ); ?>
				<a href="<?php echo esc_url( wp_registration_url() ); ?>">
					<?php esc_html_e( 'Register here', 'bkx-healthcare-practice' ); ?>
				</a>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render appointments section.
	 *
	 * @since 1.0.0
	 * @param array $appointments Appointments data.
	 * @return void
	 */
	private function render_appointments_section( array $appointments ): void {
		$upcoming = array_filter( $appointments, function ( $apt ) {
			return strtotime( $apt['date'] ) >= strtotime( 'today' );
		} );

		$past = array_filter( $appointments, function ( $apt ) {
			return strtotime( $apt['date'] ) < strtotime( 'today' );
		} );
		?>
		<div class="bkx-appointments-section">
			<div class="bkx-section-header">
				<h3><?php esc_html_e( 'Upcoming Appointments', 'bkx-healthcare-practice' ); ?></h3>
				<a href="<?php echo esc_url( home_url( '/book-appointment/' ) ); ?>" class="bkx-btn bkx-btn-primary">
					<?php esc_html_e( 'Book New Appointment', 'bkx-healthcare-practice' ); ?>
				</a>
			</div>

			<?php if ( empty( $upcoming ) ) : ?>
				<p class="bkx-no-data"><?php esc_html_e( 'No upcoming appointments.', 'bkx-healthcare-practice' ); ?></p>
			<?php else : ?>
				<div class="bkx-appointment-cards">
					<?php foreach ( $upcoming as $apt ) : ?>
						<div class="bkx-appointment-card">
							<div class="bkx-apt-date">
								<span class="bkx-apt-day"><?php echo esc_html( date_i18n( 'j', strtotime( $apt['date'] ) ) ); ?></span>
								<span class="bkx-apt-month"><?php echo esc_html( date_i18n( 'M', strtotime( $apt['date'] ) ) ); ?></span>
							</div>
							<div class="bkx-apt-details">
								<h4><?php echo esc_html( $apt['service'] ); ?></h4>
								<p class="bkx-apt-provider"><?php echo esc_html( $apt['provider'] ); ?></p>
								<p class="bkx-apt-time"><?php echo esc_html( $apt['time'] ); ?></p>
								<?php if ( $apt['telemedicine'] ) : ?>
									<span class="bkx-telemedicine-badge"><?php esc_html_e( 'Telemedicine', 'bkx-healthcare-practice' ); ?></span>
								<?php endif; ?>
							</div>
							<div class="bkx-apt-actions">
								<?php if ( $apt['telemedicine'] ) : ?>
									<a href="#" class="bkx-btn bkx-btn-small bkx-join-session" data-booking-id="<?php echo esc_attr( $apt['id'] ); ?>">
										<?php esc_html_e( 'Join Session', 'bkx-healthcare-practice' ); ?>
									</a>
								<?php endif; ?>
								<a href="#" class="bkx-btn bkx-btn-small bkx-btn-secondary bkx-reschedule" data-booking-id="<?php echo esc_attr( $apt['id'] ); ?>">
									<?php esc_html_e( 'Reschedule', 'bkx-healthcare-practice' ); ?>
								</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $past ) ) : ?>
				<h3 class="bkx-past-appointments-header"><?php esc_html_e( 'Past Appointments', 'bkx-healthcare-practice' ); ?></h3>
				<table class="bkx-appointments-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Date', 'bkx-healthcare-practice' ); ?></th>
							<th><?php esc_html_e( 'Service', 'bkx-healthcare-practice' ); ?></th>
							<th><?php esc_html_e( 'Provider', 'bkx-healthcare-practice' ); ?></th>
							<th><?php esc_html_e( 'Status', 'bkx-healthcare-practice' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_slice( $past, 0, 10 ) as $apt ) : ?>
							<tr>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $apt['date'] ) ) ); ?></td>
								<td><?php echo esc_html( $apt['service'] ); ?></td>
								<td><?php echo esc_html( $apt['provider'] ); ?></td>
								<td><span class="bkx-status-<?php echo esc_attr( $apt['status'] ); ?>"><?php echo esc_html( $apt['status'] ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render documents section.
	 *
	 * @since 1.0.0
	 * @param array $documents Documents data.
	 * @return void
	 */
	private function render_documents_section( array $documents ): void {
		?>
		<div class="bkx-documents-section">
			<h3><?php esc_html_e( 'My Documents', 'bkx-healthcare-practice' ); ?></h3>

			<?php if ( empty( $documents ) ) : ?>
				<p class="bkx-no-data"><?php esc_html_e( 'No documents available.', 'bkx-healthcare-practice' ); ?></p>
			<?php else : ?>
				<table class="bkx-documents-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Document', 'bkx-healthcare-practice' ); ?></th>
							<th><?php esc_html_e( 'Type', 'bkx-healthcare-practice' ); ?></th>
							<th><?php esc_html_e( 'Date', 'bkx-healthcare-practice' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'bkx-healthcare-practice' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $documents as $doc ) : ?>
							<tr>
								<td><?php echo esc_html( $doc['title'] ); ?></td>
								<td><?php echo esc_html( $doc['type'] ); ?></td>
								<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $doc['created_at'] ) ) ); ?></td>
								<td>
									<a href="<?php echo esc_url( $doc['url'] ); ?>" class="bkx-btn bkx-btn-small" target="_blank">
										<?php esc_html_e( 'View', 'bkx-healthcare-practice' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render messages section.
	 *
	 * @since 1.0.0
	 * @param array $messages Messages data.
	 * @return void
	 */
	private function render_messages_section( array $messages ): void {
		?>
		<div class="bkx-messages-section">
			<div class="bkx-section-header">
				<h3><?php esc_html_e( 'Messages', 'bkx-healthcare-practice' ); ?></h3>
				<button class="bkx-btn bkx-btn-primary bkx-new-message">
					<?php esc_html_e( 'New Message', 'bkx-healthcare-practice' ); ?>
				</button>
			</div>

			<?php if ( empty( $messages ) ) : ?>
				<p class="bkx-no-data"><?php esc_html_e( 'No messages yet.', 'bkx-healthcare-practice' ); ?></p>
			<?php else : ?>
				<div class="bkx-messages-list">
					<?php foreach ( $messages as $message ) : ?>
						<div class="bkx-message-item <?php echo empty( $message['read_at'] ) ? 'unread' : ''; ?>">
							<div class="bkx-message-sender">
								<?php echo esc_html( $message['sender_name'] ?? __( 'Practice', 'bkx-healthcare-practice' ) ); ?>
							</div>
							<div class="bkx-message-preview">
								<?php echo esc_html( wp_trim_words( $message['content'], 20 ) ); ?>
							</div>
							<div class="bkx-message-date">
								<?php echo esc_html( human_time_diff( strtotime( $message['created_at'] ) ) ); ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render profile section.
	 *
	 * @since 1.0.0
	 * @param array $patient Patient data.
	 * @return void
	 */
	private function render_profile_section( array $patient ): void {
		?>
		<div class="bkx-profile-section">
			<h3><?php esc_html_e( 'My Profile', 'bkx-healthcare-practice' ); ?></h3>

			<form class="bkx-profile-form" id="bkx-patient-profile-form">
				<?php wp_nonce_field( 'bkx_healthcare_frontend', 'bkx_healthcare_nonce' ); ?>

				<div class="bkx-form-row">
					<div class="bkx-form-field">
						<label><?php esc_html_e( 'Full Name', 'bkx-healthcare-practice' ); ?></label>
						<input type="text" name="display_name" value="<?php echo esc_attr( $patient['name'] ); ?>" required>
					</div>
					<div class="bkx-form-field">
						<label><?php esc_html_e( 'Email', 'bkx-healthcare-practice' ); ?></label>
						<input type="email" name="email" value="<?php echo esc_attr( $patient['email'] ); ?>" required>
					</div>
				</div>

				<div class="bkx-form-row">
					<div class="bkx-form-field">
						<label><?php esc_html_e( 'Phone', 'bkx-healthcare-practice' ); ?></label>
						<input type="tel" name="phone" value="<?php echo esc_attr( $patient['phone'] ); ?>">
					</div>
					<div class="bkx-form-field">
						<label><?php esc_html_e( 'Date of Birth', 'bkx-healthcare-practice' ); ?></label>
						<input type="date" name="date_of_birth" value="<?php echo esc_attr( $patient['date_of_birth'] ); ?>">
					</div>
				</div>

				<div class="bkx-form-field">
					<label><?php esc_html_e( 'Address', 'bkx-healthcare-practice' ); ?></label>
					<textarea name="address" rows="3"><?php echo esc_textarea( $patient['address'] ); ?></textarea>
				</div>

				<h4><?php esc_html_e( 'Emergency Contact', 'bkx-healthcare-practice' ); ?></h4>
				<div class="bkx-form-row">
					<div class="bkx-form-field">
						<label><?php esc_html_e( 'Name', 'bkx-healthcare-practice' ); ?></label>
						<input type="text" name="emergency_contact[name]" value="<?php echo esc_attr( $patient['emergency_contact']['name'] ?? '' ); ?>">
					</div>
					<div class="bkx-form-field">
						<label><?php esc_html_e( 'Phone', 'bkx-healthcare-practice' ); ?></label>
						<input type="tel" name="emergency_contact[phone]" value="<?php echo esc_attr( $patient['emergency_contact']['phone'] ?? '' ); ?>">
					</div>
					<div class="bkx-form-field">
						<label><?php esc_html_e( 'Relationship', 'bkx-healthcare-practice' ); ?></label>
						<input type="text" name="emergency_contact[relationship]" value="<?php echo esc_attr( $patient['emergency_contact']['relationship'] ?? '' ); ?>">
					</div>
				</div>

				<div class="bkx-form-actions">
					<button type="submit" class="bkx-btn bkx-btn-primary">
						<?php esc_html_e( 'Save Changes', 'bkx-healthcare-practice' ); ?>
					</button>
				</div>
			</form>

			<div class="bkx-data-privacy-section">
				<h4><?php esc_html_e( 'Data Privacy', 'bkx-healthcare-practice' ); ?></h4>
				<p><?php esc_html_e( 'You can request a copy of your data or request deletion at any time.', 'bkx-healthcare-practice' ); ?></p>
				<button class="bkx-btn bkx-btn-secondary bkx-export-data">
					<?php esc_html_e( 'Export My Data', 'bkx-healthcare-practice' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Count unread messages.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return int
	 */
	private function count_unread_messages( int $user_id ): int {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_patient_messages';

		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SHOW TABLES LIKE %s",
				$table_name
			)
		);

		if ( ! $table_exists ) {
			return 0;
		}

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE recipient_id = %d AND read_at IS NULL",
				$user_id
			)
		);

		return absint( $count );
	}
}
