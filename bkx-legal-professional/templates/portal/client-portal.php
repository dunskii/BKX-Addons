<?php
/**
 * Client Portal Template.
 *
 * @package BookingX\LegalProfessional
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ensure user is logged in.
if ( ! is_user_logged_in() ) {
	echo '<div class="bkx-legal-portal">';
	echo '<p>' . esc_html__( 'Please log in to access the client portal.', 'bkx-legal-professional' ) . '</p>';
	wp_login_form();
	echo '</div>';
	return;
}

$user_id  = get_current_user_id();
$user     = wp_get_current_user();
$settings = get_option( 'bkx_legal_settings', array() );

// Get client matters.
$matters = get_posts( array(
	'post_type'      => 'bkx_matter',
	'posts_per_page' => -1,
	'meta_query'     => array(
		array(
			'key'   => '_bkx_client_id',
			'value' => $user_id,
			'type'  => 'NUMERIC',
		),
	),
	'orderby'        => 'date',
	'order'          => 'DESC',
) );

// Get upcoming appointments.
$appointments = get_posts( array(
	'post_type'      => 'bkx_booking',
	'posts_per_page' => 5,
	'meta_query'     => array(
		array(
			'key'   => 'customer_id',
			'value' => $user_id,
			'type'  => 'NUMERIC',
		),
		array(
			'key'     => 'booking_date',
			'value'   => gmdate( 'Y-m-d' ),
			'compare' => '>=',
			'type'    => 'DATE',
		),
	),
	'meta_key'       => 'booking_date',
	'orderby'        => 'meta_value',
	'order'          => 'ASC',
) );

// Get unread messages.
global $wpdb;
$messages_table = $wpdb->prefix . 'bkx_legal_messages';
$unread_count   = $wpdb->get_var( $wpdb->prepare(
	"SELECT COUNT(*) FROM %i WHERE recipient_id = %d AND is_read = 0",
	$messages_table,
	$user_id
) );

$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'matters';
?>
<div class="bkx-legal-portal">
	<div class="bkx-legal-portal-header">
		<h2><?php printf( esc_html__( 'Welcome, %s', 'bkx-legal-professional' ), esc_html( $user->display_name ) ); ?></h2>
	</div>

	<!-- Tabs -->
	<div class="bkx-legal-tabs">
		<button class="bkx-legal-tab <?php echo 'matters' === $active_tab ? 'active' : ''; ?>" data-tab="tab-matters">
			<?php esc_html_e( 'My Matters', 'bkx-legal-professional' ); ?>
		</button>
		<button class="bkx-legal-tab <?php echo 'appointments' === $active_tab ? 'active' : ''; ?>" data-tab="tab-appointments">
			<?php esc_html_e( 'Appointments', 'bkx-legal-professional' ); ?>
		</button>
		<button class="bkx-legal-tab <?php echo 'documents' === $active_tab ? 'active' : ''; ?>" data-tab="tab-documents">
			<?php esc_html_e( 'Documents', 'bkx-legal-professional' ); ?>
		</button>
		<button class="bkx-legal-tab <?php echo 'billing' === $active_tab ? 'active' : ''; ?>" data-tab="tab-billing">
			<?php esc_html_e( 'Billing', 'bkx-legal-professional' ); ?>
		</button>
		<?php if ( ! empty( $settings['allow_client_messages'] ) ) : ?>
			<button class="bkx-legal-tab <?php echo 'messages' === $active_tab ? 'active' : ''; ?>" data-tab="tab-messages">
				<?php esc_html_e( 'Messages', 'bkx-legal-professional' ); ?>
				<?php if ( $unread_count > 0 ) : ?>
					<span class="bkx-badge"><?php echo esc_html( $unread_count ); ?></span>
				<?php endif; ?>
			</button>
		<?php endif; ?>
	</div>

	<!-- Matters Tab -->
	<div id="tab-matters" class="bkx-legal-tab-content <?php echo 'matters' === $active_tab ? 'active' : ''; ?>">
		<?php if ( ! empty( $matters ) ) : ?>
			<div class="bkx-matters-grid">
				<?php foreach ( $matters as $matter ) : ?>
					<?php
					$number = get_post_meta( $matter->ID, '_bkx_matter_number', true );
					$status = get_post_meta( $matter->ID, '_bkx_status', true ) ?: 'active';
					$responsible = get_post_meta( $matter->ID, '_bkx_responsible_attorney', true );
					$attorney = get_user_by( 'id', $responsible );
					?>
					<div class="bkx-matter-card" data-status="<?php echo esc_attr( $status ); ?>">
						<div class="bkx-matter-card-header">
							<div>
								<span class="bkx-matter-number"><?php echo esc_html( $number ); ?></span>
								<h3 class="bkx-matter-title"><?php echo esc_html( $matter->post_title ); ?></h3>
							</div>
							<span class="bkx-matter-status <?php echo esc_attr( $status ); ?>">
								<?php echo esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?>
							</span>
						</div>
						<div class="bkx-matter-meta">
							<?php if ( $attorney ) : ?>
								<span><?php esc_html_e( 'Attorney:', 'bkx-legal-professional' ); ?> <?php echo esc_html( $attorney->display_name ); ?></span>
							<?php endif; ?>
						</div>
						<div class="bkx-matter-actions">
							<a href="?tab=documents&matter_id=<?php echo esc_attr( $matter->ID ); ?>"><?php esc_html_e( 'Documents', 'bkx-legal-professional' ); ?></a>
							<a href="?tab=billing&matter_id=<?php echo esc_attr( $matter->ID ); ?>"><?php esc_html_e( 'Billing', 'bkx-legal-professional' ); ?></a>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'You have no active matters.', 'bkx-legal-professional' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Appointments Tab -->
	<div id="tab-appointments" class="bkx-legal-tab-content <?php echo 'appointments' === $active_tab ? 'active' : ''; ?>">
		<?php if ( ! empty( $appointments ) ) : ?>
			<ul class="bkx-appointment-list">
				<?php foreach ( $appointments as $appointment ) : ?>
					<?php
					$booking_date = get_post_meta( $appointment->ID, 'booking_date', true );
					$booking_time = get_post_meta( $appointment->ID, 'booking_time', true );
					$seat_id      = get_post_meta( $appointment->ID, 'seat_id', true );
					$seat         = get_post( $seat_id );
					$base_id      = get_post_meta( $appointment->ID, 'base_id', true );
					$service      = get_post( $base_id );
					?>
					<li class="bkx-appointment-item">
						<div class="bkx-appointment-date">
							<span class="bkx-appointment-day"><?php echo esc_html( gmdate( 'j', strtotime( $booking_date ) ) ); ?></span>
							<span class="bkx-appointment-month"><?php echo esc_html( gmdate( 'M', strtotime( $booking_date ) ) ); ?></span>
						</div>
						<div class="bkx-appointment-details">
							<div class="bkx-appointment-title"><?php echo esc_html( $service ? $service->post_title : '' ); ?></div>
							<div class="bkx-appointment-time"><?php echo esc_html( $booking_time ); ?></div>
							<?php if ( $seat ) : ?>
								<div class="bkx-appointment-with"><?php esc_html_e( 'With:', 'bkx-legal-professional' ); ?> <?php echo esc_html( $seat->post_title ); ?></div>
							<?php endif; ?>
						</div>
						<div class="bkx-appointment-actions">
							<a href="#" class="bkx-cancel-appointment" data-booking-id="<?php echo esc_attr( $appointment->ID ); ?>">
								<?php esc_html_e( 'Cancel', 'bkx-legal-professional' ); ?>
							</a>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'No upcoming appointments.', 'bkx-legal-professional' ); ?></p>
		<?php endif; ?>

		<p><a href="<?php echo esc_url( home_url( '/book-appointment/' ) ); ?>" class="bkx-btn bkx-btn-primary"><?php esc_html_e( 'Schedule New Appointment', 'bkx-legal-professional' ); ?></a></p>
	</div>

	<!-- Documents Tab -->
	<div id="tab-documents" class="bkx-legal-tab-content <?php echo 'documents' === $active_tab ? 'active' : ''; ?>">
		<?php
		$documents_table = $wpdb->prefix . 'bkx_legal_documents';
		$matter_filter   = isset( $_GET['matter_id'] ) ? absint( $_GET['matter_id'] ) : 0;

		// Get matter IDs for this client.
		$client_matter_ids = wp_list_pluck( $matters, 'ID' );

		if ( ! empty( $client_matter_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $client_matter_ids ), '%d' ) );

			if ( $matter_filter && in_array( $matter_filter, $client_matter_ids, true ) ) {
				$documents = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM %i WHERE matter_id = %d AND category != 'confidential' AND deleted_at IS NULL ORDER BY uploaded_at DESC",
					$documents_table,
					$matter_filter
				), ARRAY_A );
			} else {
				$documents = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$documents_table} WHERE matter_id IN ($placeholders) AND category != 'confidential' AND deleted_at IS NULL ORDER BY uploaded_at DESC LIMIT 50",
					...$client_matter_ids
				), ARRAY_A );
			}
		} else {
			$documents = array();
		}
		?>

		<!-- Filter -->
		<?php if ( count( $matters ) > 1 ) : ?>
			<div class="bkx-filter-bar">
				<select id="bkx-document-matter-filter" onchange="location.href='?tab=documents&matter_id=' + this.value">
					<option value=""><?php esc_html_e( 'All Matters', 'bkx-legal-professional' ); ?></option>
					<?php foreach ( $matters as $matter ) : ?>
						<option value="<?php echo esc_attr( $matter->ID ); ?>" <?php selected( $matter_filter, $matter->ID ); ?>>
							<?php echo esc_html( $matter->post_title ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $documents ) ) : ?>
			<ul class="bkx-document-list">
				<?php foreach ( $documents as $doc ) : ?>
					<?php
					$icon_class = 'dashicons-media-default';
					if ( 'pdf' === $doc['file_type'] ) {
						$icon_class = 'dashicons-media-document';
					} elseif ( in_array( $doc['file_type'], array( 'doc', 'docx' ), true ) ) {
						$icon_class = 'dashicons-media-text';
					} elseif ( in_array( $doc['file_type'], array( 'jpg', 'jpeg', 'png' ), true ) ) {
						$icon_class = 'dashicons-format-image';
					}
					?>
					<li class="bkx-document-item">
						<div class="bkx-document-icon <?php echo esc_attr( $doc['file_type'] ); ?>">
							<span class="dashicons <?php echo esc_attr( $icon_class ); ?>"></span>
						</div>
						<div class="bkx-document-info">
							<div class="bkx-document-name"><?php echo esc_html( $doc['filename'] ); ?></div>
							<div class="bkx-document-meta">
								<?php echo esc_html( size_format( $doc['file_size'] ) ); ?> &middot;
								<?php echo esc_html( gmdate( 'M j, Y', strtotime( $doc['uploaded_at'] ) ) ); ?>
							</div>
						</div>
						<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'bkx_download_document', 'id' => $doc['id'] ), home_url() ) ); ?>" class="bkx-document-download">
							<?php esc_html_e( 'Download', 'bkx-legal-professional' ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'No documents available.', 'bkx-legal-professional' ); ?></p>
		<?php endif; ?>

		<!-- Upload form -->
		<?php if ( ! empty( $settings['allow_client_documents'] ) && ! empty( $client_matter_ids ) ) : ?>
			<h3><?php esc_html_e( 'Upload Document', 'bkx-legal-professional' ); ?></h3>
			<form id="bkx-client-doc-upload" enctype="multipart/form-data">
				<div class="bkx-form-row">
					<label for="upload_matter_id"><?php esc_html_e( 'Matter', 'bkx-legal-professional' ); ?></label>
					<select name="matter_id" id="upload_matter_id" required>
						<?php foreach ( $matters as $matter ) : ?>
							<option value="<?php echo esc_attr( $matter->ID ); ?>"><?php echo esc_html( $matter->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="bkx-form-row">
					<label for="document_file"><?php esc_html_e( 'File', 'bkx-legal-professional' ); ?></label>
					<input type="file" name="document" id="document_file" required>
				</div>
				<div class="bkx-form-row">
					<label for="document_description"><?php esc_html_e( 'Description', 'bkx-legal-professional' ); ?></label>
					<textarea name="description" id="document_description" rows="2"></textarea>
				</div>
				<div class="upload-progress" style="display: none;">
					<div class="progress-bar" style="width: 0; height: 4px; background: #2271b1;"></div>
				</div>
				<button type="submit" class="bkx-btn bkx-btn-primary"><?php esc_html_e( 'Upload', 'bkx-legal-professional' ); ?></button>
			</form>
		<?php endif; ?>
	</div>

	<!-- Billing Tab -->
	<div id="tab-billing" class="bkx-legal-tab-content <?php echo 'billing' === $active_tab ? 'active' : ''; ?>">
		<?php
		$invoices_table = $wpdb->prefix . 'bkx_legal_invoices';
		$invoices = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE client_id = %d ORDER BY invoice_date DESC LIMIT 20",
			$invoices_table,
			$user_id
		), ARRAY_A );

		$total_due = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(balance) FROM %i WHERE client_id = %d AND status NOT IN ('paid', 'draft')",
			$invoices_table,
			$user_id
		) );
		?>

		<div class="bkx-billing-summary-cards">
			<div class="bkx-billing-card">
				<h4><?php esc_html_e( 'Total Outstanding', 'bkx-legal-professional' ); ?></h4>
				<div class="bkx-billing-amount due"><?php echo esc_html( '$' . number_format( (float) $total_due, 2 ) ); ?></div>
			</div>
		</div>

		<?php if ( ! empty( $invoices ) ) : ?>
			<h3><?php esc_html_e( 'Invoices', 'bkx-legal-professional' ); ?></h3>
			<ul class="bkx-invoice-list">
				<?php foreach ( $invoices as $invoice ) : ?>
					<li class="bkx-invoice-item">
						<span class="bkx-invoice-number"><?php echo esc_html( $invoice['invoice_number'] ); ?></span>
						<span class="bkx-invoice-date"><?php echo esc_html( gmdate( 'M j, Y', strtotime( $invoice['invoice_date'] ) ) ); ?></span>
						<span class="bkx-invoice-amount"><?php echo esc_html( '$' . number_format( $invoice['total'], 2 ) ); ?></span>
						<span class="bkx-invoice-status">
							<span class="<?php echo esc_attr( $invoice['status'] ); ?>">
								<?php echo esc_html( ucfirst( $invoice['status'] ) ); ?>
							</span>
						</span>
						<span class="bkx-invoice-actions">
							<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'bkx_view_invoice', 'id' => $invoice['id'], 'token' => $invoice['view_token'] ), home_url() ) ); ?>">
								<?php esc_html_e( 'View', 'bkx-legal-professional' ); ?>
							</a>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p><?php esc_html_e( 'No invoices found.', 'bkx-legal-professional' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Messages Tab -->
	<?php if ( ! empty( $settings['allow_client_messages'] ) ) : ?>
		<div id="tab-messages" class="bkx-legal-tab-content <?php echo 'messages' === $active_tab ? 'active' : ''; ?>">
			<?php
			$messages = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM %i WHERE recipient_id = %d OR sender_id = %d ORDER BY created_at DESC LIMIT 50",
				$messages_table,
				$user_id,
				$user_id
			), ARRAY_A );
			?>

			<div class="bkx-messages-container">
				<div class="bkx-messages-list">
					<?php foreach ( $messages as $message ) : ?>
						<?php
						$sender  = get_user_by( 'id', $message['sender_id'] );
						$is_mine = (int) $message['sender_id'] === $user_id;
						?>
						<div class="bkx-message-preview <?php echo ! $message['is_read'] && ! $is_mine ? 'unread' : ''; ?>" data-message-id="<?php echo esc_attr( $message['id'] ); ?>">
							<div class="bkx-message-subject"><?php echo esc_html( $message['subject'] ); ?></div>
							<div class="bkx-message-excerpt"><?php echo esc_html( wp_trim_words( $message['message'], 10 ) ); ?></div>
							<div class="bkx-message-time">
								<?php
								if ( $is_mine ) {
									esc_html_e( 'You', 'bkx-legal-professional' );
								} else {
									echo esc_html( $sender ? $sender->display_name : '' );
								}
								?>
								&middot;
								<?php echo esc_html( human_time_diff( strtotime( $message['created_at'] ) ) ); ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<div class="bkx-message-detail">
					<p><?php esc_html_e( 'Select a message to view.', 'bkx-legal-professional' ); ?></p>
				</div>
			</div>

			<button id="bkx-new-message-btn" class="bkx-btn bkx-btn-primary" style="margin-top: 20px;">
				<?php esc_html_e( 'New Message', 'bkx-legal-professional' ); ?>
			</button>

			<!-- New Message Modal -->
			<div id="bkx-new-message-modal" class="bkx-modal-overlay" style="display: none;">
				<div class="bkx-modal">
					<div class="bkx-modal-header">
						<h3><?php esc_html_e( 'New Message', 'bkx-legal-professional' ); ?></h3>
						<button type="button" class="bkx-modal-close">&times;</button>
					</div>
					<form id="bkx-new-message-form">
						<div class="bkx-modal-body">
							<?php if ( count( $matters ) > 1 ) : ?>
								<div class="bkx-form-row">
									<label><?php esc_html_e( 'Matter', 'bkx-legal-professional' ); ?></label>
									<select name="matter_id">
										<option value=""><?php esc_html_e( 'General Inquiry', 'bkx-legal-professional' ); ?></option>
										<?php foreach ( $matters as $matter ) : ?>
											<option value="<?php echo esc_attr( $matter->ID ); ?>"><?php echo esc_html( $matter->post_title ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php endif; ?>
							<div class="bkx-form-row">
								<label><?php esc_html_e( 'Subject', 'bkx-legal-professional' ); ?></label>
								<input type="text" name="subject" required>
							</div>
							<div class="bkx-form-row">
								<label><?php esc_html_e( 'Message', 'bkx-legal-professional' ); ?></label>
								<textarea name="message" rows="5" required></textarea>
							</div>
						</div>
						<div class="bkx-modal-footer">
							<button type="submit" class="bkx-btn bkx-btn-primary"><?php esc_html_e( 'Send Message', 'bkx-legal-professional' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>
	<?php endif; ?>
</div>
