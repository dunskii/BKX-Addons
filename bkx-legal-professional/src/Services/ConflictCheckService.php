<?php
/**
 * Conflict Check Service.
 *
 * Handles conflict of interest checking for new clients and matters.
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
 * Conflict Check Service class.
 *
 * @since 1.0.0
 */
class ConflictCheckService {

	/**
	 * Service instance.
	 *
	 * @var ConflictCheckService|null
	 */
	private static ?ConflictCheckService $instance = null;

	/**
	 * Get service instance.
	 *
	 * @return ConflictCheckService
	 */
	public static function get_instance(): ConflictCheckService {
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
		add_action( 'save_post_bkx_matter', array( $this, 'auto_check_on_matter_create' ), 20, 2 );
	}

	/**
	 * Run conflict check.
	 *
	 * @param array $data Data to check for conflicts.
	 * @return array Conflict check results.
	 */
	public function run_check( array $data ): array {
		$defaults = array(
			'client_name'       => '',
			'client_email'      => '',
			'opposing_parties'  => array(),
			'related_parties'   => array(),
			'matter_type'       => '',
			'practice_area'     => '',
			'description'       => '',
			'exclude_matter_id' => 0, // Exclude this matter from results.
		);

		$data = wp_parse_args( $data, $defaults );

		$results = array(
			'status'        => 'clear', // clear, potential, conflict.
			'checked_at'    => current_time( 'mysql' ),
			'checked_by'    => get_current_user_id(),
			'client_matches' => array(),
			'party_matches'  => array(),
			'matter_matches' => array(),
			'total_flags'   => 0,
		);

		// Check existing clients.
		if ( ! empty( $data['client_name'] ) || ! empty( $data['client_email'] ) ) {
			$results['client_matches'] = $this->check_existing_clients( $data );
		}

		// Check opposing parties.
		if ( ! empty( $data['opposing_parties'] ) ) {
			$results['party_matches'] = $this->check_parties(
				$data['opposing_parties'],
				$data['exclude_matter_id']
			);
		}

		// Check related parties.
		if ( ! empty( $data['related_parties'] ) ) {
			$related_matches = $this->check_parties(
				$data['related_parties'],
				$data['exclude_matter_id']
			);
			$results['party_matches'] = array_merge( $results['party_matches'], $related_matches );
		}

		// Check matter description for keywords.
		if ( ! empty( $data['description'] ) ) {
			$results['matter_matches'] = $this->check_matter_keywords(
				$data['description'],
				$data['exclude_matter_id']
			);
		}

		// Calculate total flags.
		$results['total_flags'] = count( $results['client_matches'] )
			+ count( $results['party_matches'] )
			+ count( $results['matter_matches'] );

		// Determine status.
		if ( $results['total_flags'] > 0 ) {
			// Check if any are direct conflicts.
			$has_conflict = false;
			foreach ( $results['party_matches'] as $match ) {
				if ( 'opposing' === $match['relationship'] ) {
					$has_conflict = true;
					break;
				}
			}
			$results['status'] = $has_conflict ? 'conflict' : 'potential';
		}

		return $results;
	}

	/**
	 * Check existing clients.
	 *
	 * @param array $data Check data.
	 * @return array
	 */
	private function check_existing_clients( array $data ): array {
		$matches = array();

		// Search by email.
		if ( ! empty( $data['client_email'] ) ) {
			$user = get_user_by( 'email', $data['client_email'] );
			if ( $user ) {
				$matches[] = array(
					'type'       => 'email_match',
					'user_id'    => $user->ID,
					'user_name'  => $user->display_name,
					'user_email' => $user->user_email,
					'severity'   => 'info',
					'note'       => __( 'Existing client with same email', 'bkx-legal-professional' ),
				);
			}
		}

		// Search by name (fuzzy match).
		if ( ! empty( $data['client_name'] ) ) {
			$name_parts = $this->parse_name( $data['client_name'] );

			$users = get_users( array(
				'search'         => '*' . $name_parts['last'] . '*',
				'search_columns' => array( 'display_name', 'user_nicename' ),
			) );

			foreach ( $users as $user ) {
				// Calculate similarity.
				$similarity = $this->calculate_name_similarity( $data['client_name'], $user->display_name );

				if ( $similarity >= 0.7 ) {
					$matches[] = array(
						'type'       => 'name_match',
						'user_id'    => $user->ID,
						'user_name'  => $user->display_name,
						'user_email' => $user->user_email,
						'similarity' => $similarity,
						'severity'   => $similarity >= 0.9 ? 'high' : 'medium',
						'note'       => sprintf(
							/* translators: %d: similarity percentage */
							__( 'Name similarity: %d%%', 'bkx-legal-professional' ),
							round( $similarity * 100 )
						),
					);
				}
			}
		}

		return $matches;
	}

	/**
	 * Check parties against existing matter parties.
	 *
	 * @param array $parties          Parties to check.
	 * @param int   $exclude_matter_id Matter ID to exclude.
	 * @return array
	 */
	private function check_parties( array $parties, int $exclude_matter_id = 0 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_parties';

		$matches = array();

		foreach ( $parties as $party ) {
			if ( empty( $party['name'] ) ) {
				continue;
			}

			$party_name = sanitize_text_field( $party['name'] );
			$party_type = isset( $party['type'] ) ? sanitize_text_field( $party['type'] ) : 'individual';

			// Search for similar party names.
			$existing = $wpdb->get_results( $wpdb->prepare(
				"SELECT p.*, m.post_title as matter_name
				FROM %i p
				LEFT JOIN {$wpdb->posts} m ON p.matter_id = m.ID
				WHERE p.deleted_at IS NULL",
				$table
			) );

			foreach ( $existing as $existing_party ) {
				if ( $exclude_matter_id > 0 && (int) $existing_party->matter_id === $exclude_matter_id ) {
					continue;
				}

				$similarity = $this->calculate_name_similarity( $party_name, $existing_party->party_name );

				if ( $similarity >= 0.8 ) {
					// Determine conflict severity.
					$severity = 'low';
					$note     = __( 'Similar party name found', 'bkx-legal-professional' );

					// If we're adding opposing party and they were a client elsewhere.
					if ( isset( $party['relationship'] ) && 'opposing' === $party['relationship'] ) {
						if ( 'client' === $existing_party->relationship ) {
							$severity = 'high';
							$note     = __( 'CONFLICT: Opposing party is existing client', 'bkx-legal-professional' );
						} elseif ( 'opposing' === $existing_party->relationship ) {
							$severity = 'medium';
							$note     = __( 'Party was opposing in another matter', 'bkx-legal-professional' );
						}
					}

					// If party is related to existing opposing party.
					if ( 'opposing' === $existing_party->relationship ) {
						$severity = 'medium';
						$note     = __( 'Party was opposing party in another matter', 'bkx-legal-professional' );
					}

					$matches[] = array(
						'type'             => 'party_match',
						'input_party'      => $party_name,
						'matched_party'    => $existing_party->party_name,
						'relationship'     => $existing_party->relationship,
						'matter_id'        => $existing_party->matter_id,
						'matter_name'      => $existing_party->matter_name,
						'similarity'       => $similarity,
						'severity'         => $severity,
						'note'             => $note,
					);
				}
			}
		}

		return $matches;
	}

	/**
	 * Check matter keywords against existing matters.
	 *
	 * @param string $description      Matter description.
	 * @param int    $exclude_matter_id Matter ID to exclude.
	 * @return array
	 */
	private function check_matter_keywords( string $description, int $exclude_matter_id = 0 ): array {
		$matches = array();

		// Extract key terms.
		$keywords = $this->extract_keywords( $description );

		if ( empty( $keywords ) ) {
			return $matches;
		}

		// Search matters with similar keywords.
		$matters = get_posts( array(
			'post_type'      => 'bkx_matter',
			'posts_per_page' => 100,
			's'              => implode( ' ', $keywords ),
			'exclude'        => $exclude_matter_id > 0 ? array( $exclude_matter_id ) : array(),
		) );

		foreach ( $matters as $matter ) {
			$matter_keywords = $this->extract_keywords( $matter->post_content );
			$common_keywords = array_intersect( $keywords, $matter_keywords );

			if ( count( $common_keywords ) >= 3 ) {
				$client_id   = get_post_meta( $matter->ID, '_bkx_client_id', true );
				$client      = get_user_by( 'id', $client_id );
				$client_name = $client ? $client->display_name : __( 'Unknown', 'bkx-legal-professional' );

				$matches[] = array(
					'type'            => 'keyword_match',
					'matter_id'       => $matter->ID,
					'matter_name'     => $matter->post_title,
					'matter_number'   => get_post_meta( $matter->ID, '_bkx_matter_number', true ),
					'client_name'     => $client_name,
					'common_keywords' => $common_keywords,
					'severity'        => count( $common_keywords ) >= 5 ? 'medium' : 'low',
					'note'            => sprintf(
						/* translators: %d: number of common keywords */
						__( '%d common keywords found', 'bkx-legal-professional' ),
						count( $common_keywords )
					),
				);
			}
		}

		return $matches;
	}

	/**
	 * Save conflict check to database.
	 *
	 * @param int   $matter_id Matter ID.
	 * @param array $results   Check results.
	 * @return int|WP_Error Check ID or error.
	 */
	public function save_check( int $matter_id, array $results ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_conflict_checks';

		$result = $wpdb->insert(
			$table,
			array(
				'matter_id'     => $matter_id,
				'status'        => $results['status'],
				'total_flags'   => $results['total_flags'],
				'check_data'    => wp_json_encode( $results ),
				'checked_by'    => get_current_user_id(),
				'checked_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to save conflict check', 'bkx-legal-professional' ) );
		}

		$check_id = $wpdb->insert_id;

		// Log activity.
		CaseManagementService::get_instance()->log_matter_activity(
			$matter_id,
			'conflict_check',
			sprintf(
				/* translators: 1: status 2: flag count */
				__( 'Conflict check completed: %1$s (%2$d flags)', 'bkx-legal-professional' ),
				$results['status'],
				$results['total_flags']
			)
		);

		return $check_id;
	}

	/**
	 * Get conflict check history for matter.
	 *
	 * @param int $matter_id Matter ID.
	 * @return array
	 */
	public function get_check_history( int $matter_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_conflict_checks';

		$checks = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE matter_id = %d ORDER BY checked_at DESC",
			$table,
			$matter_id
		), ARRAY_A );

		foreach ( $checks as &$check ) {
			$check['check_data'] = json_decode( $check['check_data'], true );
			$user = get_user_by( 'id', $check['checked_by'] );
			$check['checked_by_name'] = $user ? $user->display_name : __( 'Unknown', 'bkx-legal-professional' );
		}

		return $checks ?: array();
	}

	/**
	 * Add party to matter.
	 *
	 * @param int   $matter_id Matter ID.
	 * @param array $party     Party data.
	 * @return int|WP_Error Party ID or error.
	 */
	public function add_party( int $matter_id, array $party ) {
		$defaults = array(
			'party_name'   => '',
			'party_type'   => 'individual', // individual, organization.
			'relationship' => 'related', // client, opposing, related, witness, expert.
			'email'        => '',
			'phone'        => '',
			'address'      => '',
			'notes'        => '',
		);

		$party = wp_parse_args( $party, $defaults );

		if ( empty( $party['party_name'] ) ) {
			return new \WP_Error( 'missing_name', __( 'Party name is required', 'bkx-legal-professional' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_parties';

		$result = $wpdb->insert(
			$table,
			array(
				'matter_id'    => $matter_id,
				'party_name'   => sanitize_text_field( $party['party_name'] ),
				'party_type'   => sanitize_text_field( $party['party_type'] ),
				'relationship' => sanitize_text_field( $party['relationship'] ),
				'email'        => sanitize_email( $party['email'] ),
				'phone'        => sanitize_text_field( $party['phone'] ),
				'address'      => sanitize_textarea_field( $party['address'] ),
				'notes'        => sanitize_textarea_field( $party['notes'] ),
				'created_by'   => get_current_user_id(),
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_error', __( 'Failed to add party', 'bkx-legal-professional' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get parties for matter.
	 *
	 * @param int    $matter_id    Matter ID.
	 * @param string $relationship Filter by relationship.
	 * @return array
	 */
	public function get_parties( int $matter_id, string $relationship = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_parties';

		$sql = $wpdb->prepare(
			"SELECT * FROM %i WHERE matter_id = %d AND deleted_at IS NULL",
			$table,
			$matter_id
		);

		if ( ! empty( $relationship ) ) {
			$sql .= $wpdb->prepare( " AND relationship = %s", $relationship );
		}

		$sql .= ' ORDER BY relationship ASC, party_name ASC';

		return $wpdb->get_results( $sql, ARRAY_A ) ?: array();
	}

	/**
	 * Remove party from matter.
	 *
	 * @param int $party_id Party ID.
	 * @return bool
	 */
	public function remove_party( int $party_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_parties';

		$result = $wpdb->update(
			$table,
			array(
				'deleted_at' => current_time( 'mysql' ),
				'deleted_by' => get_current_user_id(),
			),
			array( 'id' => $party_id ),
			array( '%s', '%d' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Auto-check for conflicts when matter is created.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return void
	 */
	public function auto_check_on_matter_create( int $post_id, $post ): void {
		if ( 'bkx_matter' !== $post->post_type ) {
			return;
		}

		// Only run on initial creation.
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		$settings = get_option( 'bkx_legal_settings', array() );

		if ( empty( $settings['enable_conflict_check'] ) ) {
			return;
		}

		// Check if we already ran a check.
		$existing_checks = $this->get_check_history( $post_id );
		if ( ! empty( $existing_checks ) ) {
			return;
		}

		// Get client info.
		$client_id = get_post_meta( $post_id, '_bkx_client_id', true );
		$client    = get_user_by( 'id', $client_id );

		if ( ! $client ) {
			return;
		}

		// Get parties.
		$parties = $this->get_parties( $post_id );

		$opposing = array_filter( $parties, function( $p ) {
			return 'opposing' === $p['relationship'];
		} );

		$related = array_filter( $parties, function( $p ) {
			return 'related' === $p['relationship'];
		} );

		// Run check.
		$results = $this->run_check( array(
			'client_name'       => $client->display_name,
			'client_email'      => $client->user_email,
			'opposing_parties'  => array_map( function( $p ) {
				return array( 'name' => $p['party_name'], 'relationship' => 'opposing' );
			}, $opposing ),
			'related_parties'   => array_map( function( $p ) {
				return array( 'name' => $p['party_name'], 'relationship' => 'related' );
			}, $related ),
			'description'       => $post->post_content,
			'exclude_matter_id' => $post_id,
		) );

		// Save results.
		$this->save_check( $post_id, $results );

		// Send notification if conflicts found.
		if ( 'conflict' === $results['status'] || ( 'potential' === $results['status'] && $results['total_flags'] >= 3 ) ) {
			$this->send_conflict_notification( $post_id, $results );
		}
	}

	/**
	 * Send conflict notification.
	 *
	 * @param int   $matter_id Matter ID.
	 * @param array $results   Check results.
	 * @return void
	 */
	private function send_conflict_notification( int $matter_id, array $results ): void {
		$matter = get_post( $matter_id );

		if ( ! $matter ) {
			return;
		}

		$responsible = (int) get_post_meta( $matter_id, '_bkx_responsible_attorney', true );
		$emails      = array( get_option( 'admin_email' ) );

		$attorney = get_user_by( 'id', $responsible );
		if ( $attorney ) {
			$emails[] = $attorney->user_email;
		}

		$subject = sprintf(
			/* translators: 1: status 2: matter name */
			__( '[%1$s] Conflict Check Alert: %2$s', 'bkx-legal-professional' ),
			strtoupper( $results['status'] ),
			$matter->post_title
		);

		$message = sprintf(
			/* translators: 1: matter name 2: matter number 3: status 4: flag count */
			__(
				"A conflict check has identified potential issues.\n\n" .
				"Matter: %1\$s\n" .
				"Matter Number: %2\$s\n" .
				"Status: %3\$s\n" .
				"Flags Found: %4\$d\n\n" .
				"Please review the conflict check results in your admin panel.\n\n" .
				"This requires immediate attention before proceeding with this matter.",
				'bkx-legal-professional'
			),
			$matter->post_title,
			get_post_meta( $matter_id, '_bkx_matter_number', true ),
			$results['status'],
			$results['total_flags']
		);

		wp_mail( array_unique( $emails ), $subject, $message );
	}

	/**
	 * Waive conflict.
	 *
	 * @param int    $check_id Check ID.
	 * @param string $reason   Waiver reason.
	 * @param array  $data     Additional waiver data.
	 * @return bool|WP_Error
	 */
	public function waive_conflict( int $check_id, string $reason, array $data = array() ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bkx_legal_conflict_checks';

		$check = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table, $check_id ) );

		if ( ! $check ) {
			return new \WP_Error( 'not_found', __( 'Conflict check not found', 'bkx-legal-professional' ) );
		}

		$waiver_data = array(
			'reason'               => sanitize_textarea_field( $reason ),
			'waived_by'            => get_current_user_id(),
			'waived_at'            => current_time( 'mysql' ),
			'client_consent'       => ! empty( $data['client_consent'] ),
			'opposing_consent'     => ! empty( $data['opposing_consent'] ),
			'supervising_attorney' => isset( $data['supervising_attorney'] ) ? absint( $data['supervising_attorney'] ) : 0,
		);

		$result = $wpdb->update(
			$table,
			array(
				'status'      => 'waived',
				'waiver_data' => wp_json_encode( $waiver_data ),
			),
			array( 'id' => $check_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			CaseManagementService::get_instance()->log_matter_activity(
				$check->matter_id,
				'conflict_waived',
				sprintf(
					/* translators: %s: waiver reason */
					__( 'Conflict waived: %s', 'bkx-legal-professional' ),
					wp_trim_words( $reason, 10 )
				)
			);
		}

		return false !== $result;
	}

	/**
	 * Parse name into parts.
	 *
	 * @param string $name Full name.
	 * @return array
	 */
	private function parse_name( string $name ): array {
		$parts = preg_split( '/\s+/', trim( $name ) );

		return array(
			'first' => $parts[0] ?? '',
			'last'  => end( $parts ) ?: '',
			'full'  => $name,
		);
	}

	/**
	 * Calculate similarity between two names.
	 *
	 * @param string $name1 First name.
	 * @param string $name2 Second name.
	 * @return float Similarity score 0-1.
	 */
	private function calculate_name_similarity( string $name1, string $name2 ): float {
		// Normalize names.
		$name1 = strtolower( trim( $name1 ) );
		$name2 = strtolower( trim( $name2 ) );

		// Exact match.
		if ( $name1 === $name2 ) {
			return 1.0;
		}

		// Calculate Levenshtein distance.
		$levenshtein = levenshtein( $name1, $name2 );
		$max_len     = max( strlen( $name1 ), strlen( $name2 ) );

		if ( 0 === $max_len ) {
			return 0.0;
		}

		$lev_similarity = 1 - ( $levenshtein / $max_len );

		// Calculate similar_text percentage.
		similar_text( $name1, $name2, $percent );
		$sim_similarity = $percent / 100;

		// Use average of both methods.
		return ( $lev_similarity + $sim_similarity ) / 2;
	}

	/**
	 * Extract keywords from text.
	 *
	 * @param string $text Text to extract keywords from.
	 * @return array
	 */
	private function extract_keywords( string $text ): array {
		// Remove common words and punctuation.
		$stopwords = array(
			'the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
			'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been',
			'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
			'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'this',
			'that', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they',
			'client', 'matter', 'case', 'regarding', 're', 'vs', 'versus',
		);

		// Convert to lowercase and split.
		$text  = strtolower( strip_tags( $text ) );
		$text  = preg_replace( '/[^\w\s]/', ' ', $text );
		$words = preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY );

		// Filter out stopwords and short words.
		$keywords = array_filter( $words, function( $word ) use ( $stopwords ) {
			return strlen( $word ) >= 3 && ! in_array( $word, $stopwords, true );
		} );

		// Return unique keywords.
		return array_unique( array_values( $keywords ) );
	}

	/**
	 * Get relationship types.
	 *
	 * @return array
	 */
	public function get_relationship_types(): array {
		return array(
			'client'   => __( 'Client', 'bkx-legal-professional' ),
			'opposing' => __( 'Opposing Party', 'bkx-legal-professional' ),
			'related'  => __( 'Related Party', 'bkx-legal-professional' ),
			'witness'  => __( 'Witness', 'bkx-legal-professional' ),
			'expert'   => __( 'Expert', 'bkx-legal-professional' ),
			'counsel'  => __( 'Opposing Counsel', 'bkx-legal-professional' ),
			'judge'    => __( 'Judge/Arbitrator', 'bkx-legal-professional' ),
			'other'    => __( 'Other', 'bkx-legal-professional' ),
		);
	}

	/**
	 * Get party types.
	 *
	 * @return array
	 */
	public function get_party_types(): array {
		return array(
			'individual'   => __( 'Individual', 'bkx-legal-professional' ),
			'organization' => __( 'Organization', 'bkx-legal-professional' ),
			'government'   => __( 'Government Entity', 'bkx-legal-professional' ),
			'estate'       => __( 'Estate/Trust', 'bkx-legal-professional' ),
		);
	}
}
