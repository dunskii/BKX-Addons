<?php
/**
 * Document Management Service.
 *
 * Handles document uploads, versioning, templates, and retainer agreements.
 *
 * @package BookingX\LegalProfessional
 * @since   1.0.0
 */

namespace BookingX\LegalProfessional\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Document Service class.
 *
 * @since 1.0.0
 */
class DocumentService {

	/**
	 * Service instance.
	 *
	 * @var DocumentService|null
	 */
	private static ?DocumentService $instance = null;

	/**
	 * Allowed file types.
	 *
	 * @var array
	 */
	private array $allowed_types = array(
		'pdf'  => 'application/pdf',
		'doc'  => 'application/msword',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xls'  => 'application/vnd.ms-excel',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'txt'  => 'text/plain',
	);

	/**
	 * Get service instance.
	 *
	 * @return DocumentService
	 */
	public static function get_instance(): DocumentService {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'init', array( $this, 'create_upload_directory' ) );
		add_filter( 'upload_mimes', array( $this, 'add_allowed_mimes' ) );
	}

	/**
	 * Create secure upload directory.
	 *
	 * @return void
	 */
	public function create_upload_directory(): void {
		$upload_dir = $this->get_upload_dir();

		if ( ! file_exists( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );

			// Add .htaccess for security.
			$htaccess = $upload_dir . '/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Options -Indexes\nDeny from all" );
			}

			// Add index.php for security.
			$index = $upload_dir . '/index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, '<?php // Silence is golden' );
			}
		}
	}

	/**
	 * Get upload directory path.
	 *
	 * @return string
	 */
	private function get_upload_dir(): string {
		$upload_dir = wp_upload_dir();
		return $upload_dir['basedir'] . '/bkx-legal-documents';
	}

	/**
	 * Get upload directory URL.
	 *
	 * @return string
	 */
	private function get_upload_url(): string {
		$upload_dir = wp_upload_dir();
		return $upload_dir['baseurl'] . '/bkx-legal-documents';
	}

	/**
	 * Add allowed mime types.
	 *
	 * @param array $mimes Allowed mimes.
	 * @return array
	 */
	public function add_allowed_mimes( array $mimes ): array {
		return array_merge( $mimes, $this->allowed_types );
	}

	/**
	 * Upload document.
	 *
	 * @param array  $file      File array from $_FILES.
	 * @param int    $matter_id Matter ID.
	 * @param string $category  Document category.
	 * @param string $description Optional description.
	 * @return int|WP_Error Document ID or error.
	 */
	public function upload_document( array $file, int $matter_id, string $category = 'general', string $description = '' ) {
		// Validate matter exists.
		$matter = get_post( $matter_id );
		if ( ! $matter || 'bkx_matter' !== $matter->post_type ) {
			return new \WP_Error( 'invalid_matter', __( 'Invalid matter ID', 'bkx-legal-professional' ) );
		}

		// Validate file type.
		$file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! isset( $this->allowed_types[ $file_ext ] ) ) {
			return new \WP_Error( 'invalid_type', __( 'File type not allowed', 'bkx-legal-professional' ) );
		}

		// Generate unique filename.
		$unique_name = wp_unique_filename( $this->get_upload_dir(), $file['name'] );
		$upload_path = $this->get_upload_dir() . '/' . $unique_name;

		// Move uploaded file.
		if ( ! move_uploaded_file( $file['tmp_name'], $upload_path ) ) {
			return new \WP_Error( 'upload_failed', __( 'Failed to upload file', 'bkx-legal-professional' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_documents';

		// Insert document record.
		$result = $wpdb->insert(
			$table,
			array(
				'matter_id'     => $matter_id,
				'filename'      => sanitize_file_name( $file['name'] ),
				'stored_name'   => $unique_name,
				'file_type'     => $file_ext,
				'file_size'     => filesize( $upload_path ),
				'mime_type'     => $this->allowed_types[ $file_ext ],
				'category'      => sanitize_text_field( $category ),
				'description'   => sanitize_textarea_field( $description ),
				'version'       => 1,
				'uploaded_by'   => get_current_user_id(),
				'uploaded_at'   => current_time( 'mysql' ),
				'file_hash'     => hash_file( 'sha256', $upload_path ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			wp_delete_file( $upload_path );
			return new \WP_Error( 'db_error', __( 'Failed to save document record', 'bkx-legal-professional' ) );
		}

		$document_id = $wpdb->insert_id;

		// Log activity.
		CaseManagementService::get_instance()->log_matter_activity(
			$matter_id,
			'document_uploaded',
			sprintf(
				/* translators: %s: document filename */
				__( 'Document uploaded: %s', 'bkx-legal-professional' ),
				$file['name']
			)
		);

		/**
		 * Fires after a document is uploaded.
		 *
		 * @param int   $document_id The document ID.
		 * @param int   $matter_id   The matter ID.
		 * @param array $file        The file data.
		 */
		do_action( 'bkx_legal_document_uploaded', $document_id, $matter_id, $file );

		return $document_id;
	}

	/**
	 * Upload new version of document.
	 *
	 * @param array $file        File array from $_FILES.
	 * @param int   $document_id Original document ID.
	 * @param string $notes      Version notes.
	 * @return int|WP_Error New document ID or error.
	 */
	public function upload_version( array $file, int $document_id, string $notes = '' ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_documents';

		// Get original document.
		$original = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d",
			$table,
			$document_id
		) );

		if ( ! $original ) {
			return new \WP_Error( 'not_found', __( 'Original document not found', 'bkx-legal-professional' ) );
		}

		// Get current max version.
		$max_version = $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(version) FROM %i WHERE matter_id = %d AND filename = %s",
			$table,
			$original->matter_id,
			$original->filename
		) );

		// Validate file type matches original.
		$file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( $file_ext !== $original->file_type ) {
			return new \WP_Error( 'type_mismatch', __( 'File type must match original document', 'bkx-legal-professional' ) );
		}

		// Generate unique filename.
		$version    = (int) $max_version + 1;
		$base_name  = pathinfo( $file['name'], PATHINFO_FILENAME );
		$new_name   = sprintf( '%s_v%d.%s', $base_name, $version, $file_ext );
		$unique_name = wp_unique_filename( $this->get_upload_dir(), $new_name );
		$upload_path = $this->get_upload_dir() . '/' . $unique_name;

		// Move uploaded file.
		if ( ! move_uploaded_file( $file['tmp_name'], $upload_path ) ) {
			return new \WP_Error( 'upload_failed', __( 'Failed to upload file', 'bkx-legal-professional' ) );
		}

		// Insert new version record.
		$result = $wpdb->insert(
			$table,
			array(
				'matter_id'      => $original->matter_id,
				'filename'       => $original->filename,
				'stored_name'    => $unique_name,
				'file_type'      => $file_ext,
				'file_size'      => filesize( $upload_path ),
				'mime_type'      => $original->mime_type,
				'category'       => $original->category,
				'description'    => $original->description,
				'version'        => $version,
				'parent_id'      => $document_id,
				'version_notes'  => sanitize_textarea_field( $notes ),
				'uploaded_by'    => get_current_user_id(),
				'uploaded_at'    => current_time( 'mysql' ),
				'file_hash'      => hash_file( 'sha256', $upload_path ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			wp_delete_file( $upload_path );
			return new \WP_Error( 'db_error', __( 'Failed to save document version', 'bkx-legal-professional' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get document by ID.
	 *
	 * @param int $document_id Document ID.
	 * @return array|null
	 */
	public function get_document( int $document_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_documents';

		$document = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d",
			$table,
			$document_id
		), ARRAY_A );

		if ( ! $document ) {
			return null;
		}

		$user = get_user_by( 'id', $document['uploaded_by'] );
		$document['uploader_name'] = $user ? $user->display_name : __( 'Unknown', 'bkx-legal-professional' );

		return $document;
	}

	/**
	 * Get documents for matter.
	 *
	 * @param int    $matter_id Matter ID.
	 * @param string $category  Filter by category.
	 * @param bool   $latest_only Only get latest versions.
	 * @return array
	 */
	public function get_matter_documents( int $matter_id, string $category = '', bool $latest_only = true ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_documents';

		$sql = $wpdb->prepare(
			"SELECT * FROM %i WHERE matter_id = %d AND deleted_at IS NULL",
			$table,
			$matter_id
		);

		if ( ! empty( $category ) ) {
			$sql .= $wpdb->prepare( " AND category = %s", $category );
		}

		if ( $latest_only ) {
			// Only get latest version of each file.
			$sql .= " AND id IN (
				SELECT MAX(id) FROM {$table}
				WHERE matter_id = {$matter_id} AND deleted_at IS NULL
				GROUP BY filename
			)";
		}

		$sql .= ' ORDER BY uploaded_at DESC';

		$documents = $wpdb->get_results( $sql, ARRAY_A );

		// Add uploader names.
		foreach ( $documents as &$doc ) {
			$user = get_user_by( 'id', $doc['uploaded_by'] );
			$doc['uploader_name'] = $user ? $user->display_name : __( 'Unknown', 'bkx-legal-professional' );
		}

		return $documents ?: array();
	}

	/**
	 * Get document versions.
	 *
	 * @param int $document_id Document ID.
	 * @return array
	 */
	public function get_versions( int $document_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_documents';

		// Get original document.
		$original = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d",
			$table,
			$document_id
		) );

		if ( ! $original ) {
			return array();
		}

		// Get all versions.
		$documents = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE matter_id = %d AND filename = %s AND deleted_at IS NULL ORDER BY version DESC",
			$table,
			$original->matter_id,
			$original->filename
		), ARRAY_A );

		return $documents ?: array();
	}

	/**
	 * Download document.
	 *
	 * @param int $document_id Document ID.
	 * @return void
	 */
	public function download_document( int $document_id ): void {
		$document = $this->get_document( $document_id );

		if ( ! $document ) {
			wp_die( esc_html__( 'Document not found', 'bkx-legal-professional' ) );
		}

		// Check permissions.
		if ( ! $this->can_access_document( $document_id ) ) {
			wp_die( esc_html__( 'Access denied', 'bkx-legal-professional' ) );
		}

		$file_path = $this->get_upload_dir() . '/' . $document['stored_name'];

		if ( ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'File not found', 'bkx-legal-professional' ) );
		}

		// Log download.
		$this->log_document_access( $document_id, 'download' );

		// Send file headers.
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: ' . $document['mime_type'] );
		header( 'Content-Disposition: attachment; filename="' . $document['filename'] . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Content-Length: ' . $document['file_size'] );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );
		header( 'Expires: 0' );

		readfile( $file_path );
		exit;
	}

	/**
	 * Check if user can access document.
	 *
	 * @param int $document_id Document ID.
	 * @return bool
	 */
	public function can_access_document( int $document_id ): bool {
		// Admins can access all.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$document = $this->get_document( $document_id );
		if ( ! $document ) {
			return false;
		}

		$user_id = get_current_user_id();

		// Document uploader can access.
		if ( (int) $document['uploaded_by'] === $user_id ) {
			return true;
		}

		// Check if user is assigned to matter.
		$matter_id = $document['matter_id'];
		$responsible = (int) get_post_meta( $matter_id, '_bkx_responsible_attorney', true );
		$originating = (int) get_post_meta( $matter_id, '_bkx_originating_attorney', true );
		$billing     = (int) get_post_meta( $matter_id, '_bkx_billing_attorney', true );

		if ( in_array( $user_id, array( $responsible, $originating, $billing ), true ) ) {
			return true;
		}

		// Check if user is the client.
		$client_id = (int) get_post_meta( $matter_id, '_bkx_client_id', true );
		if ( $client_id === $user_id ) {
			// Clients can only access non-confidential documents.
			return 'confidential' !== $document['category'];
		}

		return false;
	}

	/**
	 * Log document access.
	 *
	 * @param int    $document_id Document ID.
	 * @param string $action      Action: view, download, edit, delete.
	 * @return void
	 */
	private function log_document_access( int $document_id, string $action ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_document_access_log';

		$wpdb->insert(
			$table,
			array(
				'document_id' => $document_id,
				'user_id'     => get_current_user_id(),
				'action'      => sanitize_text_field( $action ),
				'ip_address'  => $this->get_client_ip(),
				'accessed_at' => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Delete document (soft delete).
	 *
	 * @param int $document_id Document ID.
	 * @return bool
	 */
	public function delete_document( int $document_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_documents';

		$document = $this->get_document( $document_id );
		if ( ! $document ) {
			return false;
		}

		// Soft delete.
		$result = $wpdb->update(
			$table,
			array(
				'deleted_at' => current_time( 'mysql' ),
				'deleted_by' => get_current_user_id(),
			),
			array( 'id' => $document_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			CaseManagementService::get_instance()->log_matter_activity(
				$document['matter_id'],
				'document_deleted',
				sprintf(
					/* translators: %s: document filename */
					__( 'Document deleted: %s', 'bkx-legal-professional' ),
					$document['filename']
				)
			);
		}

		return false !== $result;
	}

	/**
	 * Create retainer agreement.
	 *
	 * @param int   $matter_id Matter ID.
	 * @param int   $template_id Retainer template ID.
	 * @param array $data      Agreement data.
	 * @return int|WP_Error Retainer ID or error.
	 */
	public function create_retainer( int $matter_id, int $template_id, array $data = array() ) {
		$template = get_post( $template_id );
		if ( ! $template || 'bkx_retainer' !== $template->post_type ) {
			return new \WP_Error( 'invalid_template', __( 'Invalid retainer template', 'bkx-legal-professional' ) );
		}

		$matter = get_post( $matter_id );
		if ( ! $matter || 'bkx_matter' !== $matter->post_type ) {
			return new \WP_Error( 'invalid_matter', __( 'Invalid matter ID', 'bkx-legal-professional' ) );
		}

		// Get client data.
		$client_id = (int) get_post_meta( $matter_id, '_bkx_client_id', true );
		$client    = get_user_by( 'id', $client_id );

		// Merge template content with data.
		$content = $template->post_content;
		$placeholders = array(
			'{client_name}'    => $client ? $client->display_name : '',
			'{client_email}'   => $client ? $client->user_email : '',
			'{matter_name}'    => $matter->post_title,
			'{matter_number}'  => get_post_meta( $matter_id, '_bkx_matter_number', true ),
			'{firm_name}'      => get_bloginfo( 'name' ),
			'{current_date}'   => current_time( 'F j, Y' ),
			'{hourly_rate}'    => get_post_meta( $matter_id, '_bkx_hourly_rate', true ),
			'{flat_fee}'       => get_post_meta( $matter_id, '_bkx_flat_fee', true ),
		);

		// Add custom placeholders from data.
		foreach ( $data as $key => $value ) {
			$placeholders[ '{' . $key . '}' ] = $value;
		}

		$content = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $content );

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_retainers';

		// Create retainer record.
		$result = $wpdb->insert(
			$table,
			array(
				'matter_id'    => $matter_id,
				'template_id'  => $template_id,
				'client_id'    => $client_id,
				'content'      => wp_kses_post( $content ),
				'status'       => 'pending',
				'created_by'   => get_current_user_id(),
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to create retainer', 'bkx-legal-professional' ) );
		}

		$retainer_id = $wpdb->insert_id;

		// Link retainer to matter.
		update_post_meta( $matter_id, '_bkx_retainer_id', $retainer_id );

		/**
		 * Fires after a retainer is created.
		 *
		 * @param int $retainer_id The retainer ID.
		 * @param int $matter_id   The matter ID.
		 */
		do_action( 'bkx_legal_retainer_created', $retainer_id, $matter_id );

		return $retainer_id;
	}

	/**
	 * Send retainer for signature.
	 *
	 * @param int $retainer_id Retainer ID.
	 * @return bool|WP_Error
	 */
	public function send_retainer_for_signature( int $retainer_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_retainers';

		$retainer = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d",
			$table,
			$retainer_id
		) );

		if ( ! $retainer ) {
			return new \WP_Error( 'not_found', __( 'Retainer not found', 'bkx-legal-professional' ) );
		}

		$client = get_user_by( 'id', $retainer->client_id );
		if ( ! $client ) {
			return new \WP_Error( 'no_client', __( 'Client not found', 'bkx-legal-professional' ) );
		}

		// Generate signature token.
		$token = wp_generate_password( 32, false );
		$signature_url = add_query_arg( array(
			'action'  => 'bkx_sign_retainer',
			'id'      => $retainer_id,
			'token'   => $token,
		), home_url() );

		// Save token.
		$wpdb->update(
			$table,
			array(
				'signature_token' => $token,
				'status'          => 'sent',
				'sent_at'         => current_time( 'mysql' ),
			),
			array( 'id' => $retainer_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Send email.
		$matter = get_post( $retainer->matter_id );

		$subject = sprintf(
			/* translators: %s: matter name */
			__( 'Retainer Agreement for Signature - %s', 'bkx-legal-professional' ),
			$matter ? $matter->post_title : ''
		);

		$message = sprintf(
			/* translators: 1: client name 2: firm name 3: signature URL 4: matter name */
			__(
				"Dear %1\$s,\n\n" .
				"Please review and sign the attached retainer agreement for your matter with %2\$s.\n\n" .
				"Click here to review and sign: %3\$s\n\n" .
				"Matter: %4\$s\n\n" .
				"If you have any questions, please contact us.\n\n" .
				"Best regards,\n%2\$s",
				'bkx-legal-professional'
			),
			$client->display_name,
			get_bloginfo( 'name' ),
			$signature_url,
			$matter ? $matter->post_title : ''
		);

		$sent = wp_mail( $client->user_email, $subject, $message );

		if ( ! $sent ) {
			return new \WP_Error( 'email_failed', __( 'Failed to send signature request', 'bkx-legal-professional' ) );
		}

		return true;
	}

	/**
	 * Sign retainer agreement.
	 *
	 * @param int    $retainer_id Retainer ID.
	 * @param string $token       Signature token.
	 * @param string $signature   Signature data (base64 encoded).
	 * @return bool|WP_Error
	 */
	public function sign_retainer( int $retainer_id, string $token, string $signature ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_retainers';

		$retainer = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d AND signature_token = %s",
			$table,
			$retainer_id,
			$token
		) );

		if ( ! $retainer ) {
			return new \WP_Error( 'invalid_token', __( 'Invalid or expired signature link', 'bkx-legal-professional' ) );
		}

		if ( 'signed' === $retainer->status ) {
			return new \WP_Error( 'already_signed', __( 'This retainer has already been signed', 'bkx-legal-professional' ) );
		}

		// Save signature.
		$result = $wpdb->update(
			$table,
			array(
				'status'          => 'signed',
				'signature_data'  => $signature,
				'signed_at'       => current_time( 'mysql' ),
				'signed_ip'       => $this->get_client_ip(),
				'signature_token' => null, // Invalidate token.
			),
			array( 'id' => $retainer_id ),
			array( '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to save signature', 'bkx-legal-professional' ) );
		}

		// Log activity.
		CaseManagementService::get_instance()->log_matter_activity(
			$retainer->matter_id,
			'retainer_signed',
			__( 'Retainer agreement signed by client', 'bkx-legal-professional' )
		);

		// Notify firm.
		$this->notify_retainer_signed( $retainer_id );

		/**
		 * Fires after a retainer is signed.
		 *
		 * @param int $retainer_id The retainer ID.
		 * @param int $matter_id   The matter ID.
		 */
		do_action( 'bkx_legal_retainer_signed', $retainer_id, $retainer->matter_id );

		return true;
	}

	/**
	 * Notify firm that retainer was signed.
	 *
	 * @param int $retainer_id Retainer ID.
	 * @return void
	 */
	private function notify_retainer_signed( int $retainer_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_retainers';

		$retainer = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d",
			$table,
			$retainer_id
		) );

		if ( ! $retainer ) {
			return;
		}

		$matter = get_post( $retainer->matter_id );
		$client = get_user_by( 'id', $retainer->client_id );
		$responsible = (int) get_post_meta( $retainer->matter_id, '_bkx_responsible_attorney', true );

		$emails = array( get_option( 'admin_email' ) );
		$attorney = get_user_by( 'id', $responsible );
		if ( $attorney ) {
			$emails[] = $attorney->user_email;
		}

		$subject = sprintf(
			/* translators: 1: client name 2: matter name */
			__( 'Retainer Signed: %1$s - %2$s', 'bkx-legal-professional' ),
			$client ? $client->display_name : 'Unknown',
			$matter ? $matter->post_title : 'Unknown'
		);

		$message = sprintf(
			/* translators: 1: client name 2: matter name 3: date */
			__(
				"The retainer agreement has been signed.\n\n" .
				"Client: %1\$s\n" .
				"Matter: %2\$s\n" .
				"Signed: %3\$s\n\n" .
				"You can view the signed agreement in your admin panel.",
				'bkx-legal-professional'
			),
			$client ? $client->display_name : 'Unknown',
			$matter ? $matter->post_title : 'Unknown',
			current_time( 'F j, Y g:i a' )
		);

		wp_mail( array_unique( $emails ), $subject, $message );
	}

	/**
	 * Get retainer by ID.
	 *
	 * @param int $retainer_id Retainer ID.
	 * @return array|null
	 */
	public function get_retainer( int $retainer_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_retainers';

		$retainer = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE id = %d",
			$table,
			$retainer_id
		), ARRAY_A );

		return $retainer ?: null;
	}

	/**
	 * Get document categories.
	 *
	 * @return array
	 */
	public function get_categories(): array {
		return array(
			'general'      => __( 'General', 'bkx-legal-professional' ),
			'pleadings'    => __( 'Pleadings', 'bkx-legal-professional' ),
			'discovery'    => __( 'Discovery', 'bkx-legal-professional' ),
			'correspondence' => __( 'Correspondence', 'bkx-legal-professional' ),
			'contracts'    => __( 'Contracts', 'bkx-legal-professional' ),
			'evidence'     => __( 'Evidence', 'bkx-legal-professional' ),
			'research'     => __( 'Research', 'bkx-legal-professional' ),
			'billing'      => __( 'Billing', 'bkx-legal-professional' ),
			'confidential' => __( 'Confidential', 'bkx-legal-professional' ),
		);
	}

	/**
	 * Get client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Generate document as PDF.
	 *
	 * @param int    $retainer_id Retainer ID.
	 * @param string $output      Output type: string, download, file.
	 * @return string|void
	 */
	public function generate_retainer_pdf( int $retainer_id, string $output = 'download' ) {
		$retainer = $this->get_retainer( $retainer_id );
		if ( ! $retainer ) {
			return;
		}

		$matter = get_post( $retainer['matter_id'] );
		$client = get_user_by( 'id', $retainer['client_id'] );

		// Use TCPDF if available, otherwise return HTML.
		if ( ! class_exists( 'TCPDF' ) ) {
			// Return HTML version.
			$html = '<html><head><title>' . esc_html__( 'Retainer Agreement', 'bkx-legal-professional' ) . '</title></head><body>';
			$html .= '<h1>' . esc_html( $matter ? $matter->post_title : '' ) . '</h1>';
			$html .= wp_kses_post( $retainer['content'] );

			if ( 'signed' === $retainer['status'] && ! empty( $retainer['signature_data'] ) ) {
				$html .= '<div class="signature"><h3>' . esc_html__( 'Client Signature', 'bkx-legal-professional' ) . '</h3>';
				$html .= '<img src="' . esc_attr( $retainer['signature_data'] ) . '" alt="Signature" />';
				$html .= '<p>' . esc_html__( 'Signed:', 'bkx-legal-professional' ) . ' ' . esc_html( $retainer['signed_at'] ) . '</p>';
				$html .= '</div>';
			}

			$html .= '</body></html>';

			if ( 'string' === $output ) {
				return $html;
			}

			header( 'Content-Type: text/html' );
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		// TCPDF implementation would go here for full PDF generation.
	}
}
