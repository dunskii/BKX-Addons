<?php
/**
 * Insurance Service.
 *
 * Handles insurance verification and eligibility checks.
 *
 * @package BookingX\HealthcarePractice
 * @since   1.0.0
 */

namespace BookingX\HealthcarePractice\Services;

/**
 * Insurance Service class.
 *
 * @since 1.0.0
 */
class InsuranceService {

	/**
	 * Singleton instance.
	 *
	 * @var InsuranceService|null
	 */
	private static ?InsuranceService $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 * @return InsuranceService
	 */
	public static function get_instance(): InsuranceService {
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
	 * Verify insurance eligibility.
	 *
	 * @since 1.0.0
	 * @param array $insurance_data Insurance information.
	 * @return array|\WP_Error Verification result or error.
	 */
	public function verify_eligibility( array $insurance_data ) {
		$settings = get_option( 'bkx_healthcare_settings', array() );

		if ( empty( $settings['enable_insurance_verification'] ) ) {
			return new \WP_Error( 'disabled', __( 'Insurance verification is not enabled', 'bkx-healthcare-practice' ) );
		}

		// Validate required fields.
		$required = array( 'provider', 'member_id', 'dob' );
		foreach ( $required as $field ) {
			if ( empty( $insurance_data[ $field ] ) ) {
				return new \WP_Error(
					'missing_field',
					sprintf(
						/* translators: %s: Field name */
						__( 'Missing required field: %s', 'bkx-healthcare-practice' ),
						$field
					)
				);
			}
		}

		// Get API credentials.
		$api_provider = $settings['insurance_api_provider'] ?? 'none';

		switch ( $api_provider ) {
			case 'availity':
				return $this->verify_with_availity( $insurance_data );
			case 'change_healthcare':
				return $this->verify_with_change_healthcare( $insurance_data );
			case 'manual':
			default:
				return $this->manual_verification( $insurance_data );
		}
	}

	/**
	 * Verify with Availity API.
	 *
	 * @since 1.0.0
	 * @param array $insurance_data Insurance data.
	 * @return array|\WP_Error
	 */
	private function verify_with_availity( array $insurance_data ) {
		$settings = get_option( 'bkx_healthcare_settings', array() );

		$api_key    = $settings['availity_api_key'] ?? '';
		$api_secret = $settings['availity_api_secret'] ?? '';

		if ( empty( $api_key ) || empty( $api_secret ) ) {
			return new \WP_Error( 'no_credentials', __( 'Availity API credentials not configured', 'bkx-healthcare-practice' ) );
		}

		// Decrypt credentials if stored encrypted.
		if ( class_exists( 'BKX_Data_Encryption' ) ) {
			$encryption = new \BKX_Data_Encryption();
			$api_key    = $encryption->decrypt( $api_key );
			$api_secret = $encryption->decrypt( $api_secret );
		}

		// Make API request.
		$response = wp_remote_post(
			'https://api.availity.com/eligibility/v1/coverages',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->get_availity_token( $api_key, $api_secret ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'payerID'        => $insurance_data['provider'],
					'memberId'       => $insurance_data['member_id'],
					'groupNumber'    => $insurance_data['group_id'] ?? '',
					'dateOfBirth'    => $insurance_data['dob'],
					'serviceType'    => '30', // Health Benefit Plan Coverage.
				) ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new \WP_Error(
				'api_error',
				$body['message'] ?? __( 'Insurance verification failed', 'bkx-healthcare-practice' )
			);
		}

		return $this->parse_eligibility_response( $body );
	}

	/**
	 * Get Availity OAuth token.
	 *
	 * @since 1.0.0
	 * @param string $api_key    API key.
	 * @param string $api_secret API secret.
	 * @return string
	 */
	private function get_availity_token( string $api_key, string $api_secret ): string {
		$transient_key = 'bkx_availity_token';
		$token         = get_transient( $transient_key );

		if ( $token ) {
			return $token;
		}

		$response = wp_remote_post(
			'https://api.availity.com/oauth2/token',
			array(
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'grant_type'    => 'client_credentials',
					'client_id'     => $api_key,
					'client_secret' => $api_secret,
					'scope'         => 'eligibility',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['access_token'] ) ) {
			set_transient( $transient_key, $body['access_token'], $body['expires_in'] ?? 3600 );
			return $body['access_token'];
		}

		return '';
	}

	/**
	 * Verify with Change Healthcare API.
	 *
	 * @since 1.0.0
	 * @param array $insurance_data Insurance data.
	 * @return array|\WP_Error
	 */
	private function verify_with_change_healthcare( array $insurance_data ) {
		$settings = get_option( 'bkx_healthcare_settings', array() );

		$api_key = $settings['change_healthcare_api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return new \WP_Error( 'no_credentials', __( 'Change Healthcare API key not configured', 'bkx-healthcare-practice' ) );
		}

		// Decrypt if stored encrypted.
		if ( class_exists( 'BKX_Data_Encryption' ) ) {
			$encryption = new \BKX_Data_Encryption();
			$api_key    = $encryption->decrypt( $api_key );
		}

		$response = wp_remote_post(
			'https://api.changehealthcare.com/medicalnetwork/eligibility/v3',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'controlNumber'         => uniqid( 'BKX', true ),
					'tradingPartnerServiceId' => $insurance_data['provider'],
					'subscriber'            => array(
						'memberId'    => $insurance_data['member_id'],
						'dateOfBirth' => $insurance_data['dob'],
						'groupNumber' => $insurance_data['group_id'] ?? '',
					),
				) ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new \WP_Error(
				'api_error',
				$body['message'] ?? __( 'Insurance verification failed', 'bkx-healthcare-practice' )
			);
		}

		return $this->parse_eligibility_response( $body );
	}

	/**
	 * Manual verification (store for admin review).
	 *
	 * @since 1.0.0
	 * @param array $insurance_data Insurance data.
	 * @return array
	 */
	private function manual_verification( array $insurance_data ): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_insurance_verifications';
		$user_id    = get_current_user_id();

		// Store for manual verification.
		$wpdb->insert(
			$table_name,
			array(
				'user_id'       => $user_id,
				'provider'      => sanitize_text_field( $insurance_data['provider'] ),
				'member_id'     => sanitize_text_field( $insurance_data['member_id'] ),
				'group_id'      => sanitize_text_field( $insurance_data['group_id'] ?? '' ),
				'dob'           => sanitize_text_field( $insurance_data['dob'] ),
				'status'        => 'pending',
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return array(
			'status'       => 'pending',
			'message'      => __( 'Your insurance information has been submitted for verification. Our staff will review it shortly.', 'bkx-healthcare-practice' ),
			'verification_id' => $wpdb->insert_id,
		);
	}

	/**
	 * Parse eligibility response.
	 *
	 * @since 1.0.0
	 * @param array $response API response.
	 * @return array
	 */
	private function parse_eligibility_response( array $response ): array {
		$result = array(
			'status'       => 'unknown',
			'eligible'     => false,
			'coverage'     => array(),
			'copay'        => null,
			'deductible'   => null,
			'out_of_pocket' => null,
		);

		// Parse Availity format.
		if ( isset( $response['coverages'] ) ) {
			$coverage = $response['coverages'][0] ?? array();

			$result['status']   = $coverage['status'] ?? 'unknown';
			$result['eligible'] = 'active' === strtolower( $result['status'] );

			if ( isset( $coverage['benefits'] ) ) {
				foreach ( $coverage['benefits'] as $benefit ) {
					if ( 'Copay' === ( $benefit['name'] ?? '' ) ) {
						$result['copay'] = $benefit['amount'] ?? null;
					}
					if ( 'Deductible' === ( $benefit['name'] ?? '' ) ) {
						$result['deductible'] = array(
							'amount' => $benefit['amount'] ?? null,
							'remaining' => $benefit['remaining'] ?? null,
						);
					}
					if ( 'Out of Pocket' === ( $benefit['name'] ?? '' ) ) {
						$result['out_of_pocket'] = array(
							'max' => $benefit['amount'] ?? null,
							'remaining' => $benefit['remaining'] ?? null,
						);
					}
				}
			}
		}

		// Parse Change Healthcare format.
		if ( isset( $response['benefitsInformation'] ) ) {
			foreach ( $response['benefitsInformation'] as $benefit ) {
				$code = $benefit['code'] ?? '';

				if ( '1' === $code ) { // Active Coverage.
					$result['status']   = 'active';
					$result['eligible'] = true;
				}

				// Parse benefit amounts.
				if ( isset( $benefit['benefitAmount'] ) ) {
					$amount = floatval( $benefit['benefitAmount'] );
					$type   = $benefit['quantityQualifierCode'] ?? '';

					if ( 'OO' === $type ) { // Out of Pocket.
						$result['out_of_pocket'] = array( 'max' => $amount );
					} elseif ( 'DY' === $type ) { // Deductible.
						$result['deductible'] = array( 'amount' => $amount );
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Save patient insurance info.
	 *
	 * @since 1.0.0
	 * @param int   $user_id        User ID.
	 * @param array $insurance_data Insurance data.
	 * @return bool
	 */
	public function save_patient_insurance( int $user_id, array $insurance_data ): bool {
		// Encrypt sensitive data.
		if ( class_exists( 'BKX_Data_Encryption' ) ) {
			$encryption   = new \BKX_Data_Encryption();
			$sensitive    = array( 'member_id', 'group_id', 'ssn' );

			foreach ( $sensitive as $field ) {
				if ( ! empty( $insurance_data[ $field ] ) ) {
					$insurance_data[ $field ] = $encryption->encrypt( $insurance_data[ $field ] );
					$insurance_data[ $field . '_encrypted' ] = true;
				}
			}
		}

		return update_user_meta( $user_id, '_bkx_insurance_info', $insurance_data );
	}

	/**
	 * Get patient insurance info.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_patient_insurance( int $user_id ): array {
		$insurance = get_user_meta( $user_id, '_bkx_insurance_info', true );

		if ( ! is_array( $insurance ) ) {
			return array();
		}

		// Decrypt sensitive data.
		if ( class_exists( 'BKX_Data_Encryption' ) ) {
			$encryption = new \BKX_Data_Encryption();

			foreach ( $insurance as $field => $value ) {
				if ( isset( $insurance[ $field . '_encrypted' ] ) && $insurance[ $field . '_encrypted' ] ) {
					$insurance[ $field ] = $encryption->decrypt( $value );
					unset( $insurance[ $field . '_encrypted' ] );
				}
			}
		}

		return $insurance;
	}

	/**
	 * Get supported insurance providers.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_supported_providers(): array {
		return apply_filters(
			'bkx_healthcare_insurance_providers',
			array(
				'aetna'          => 'Aetna',
				'anthem'         => 'Anthem Blue Cross Blue Shield',
				'cigna'          => 'Cigna',
				'humana'         => 'Humana',
				'united'         => 'UnitedHealthcare',
				'kaiser'         => 'Kaiser Permanente',
				'bcbs'           => 'Blue Cross Blue Shield',
				'medicare'       => 'Medicare',
				'medicaid'       => 'Medicaid',
				'tricare'        => 'TRICARE',
				'other'          => __( 'Other', 'bkx-healthcare-practice' ),
			)
		);
	}

	/**
	 * Update verification status.
	 *
	 * @since 1.0.0
	 * @param int    $verification_id Verification record ID.
	 * @param string $status          New status.
	 * @param array  $data            Additional data.
	 * @return bool
	 */
	public function update_verification_status( int $verification_id, string $status, array $data = array() ): bool {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_insurance_verifications';

		$updated = $wpdb->update(
			$table_name,
			array(
				'status'       => sanitize_text_field( $status ),
				'verified_by'  => get_current_user_id(),
				'verified_at'  => current_time( 'mysql' ),
				'notes'        => sanitize_textarea_field( $data['notes'] ?? '' ),
			),
			array( 'id' => $verification_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( $updated ) {
			// Log the verification.
			do_action( 'bkx_healthcare_audit_log', 'insurance_verified', $verification_id, array(
				'status' => $status,
				'verified_by' => get_current_user_id(),
			) );
		}

		return (bool) $updated;
	}

	/**
	 * Get pending verifications.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_pending_verifications(): array {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bkx_insurance_verifications';

		$results = $wpdb->get_results(
			"SELECT v.*, u.display_name as patient_name, u.user_email as patient_email
			 FROM {$table_name} v
			 LEFT JOIN {$wpdb->users} u ON v.user_id = u.ID
			 WHERE v.status = 'pending'
			 ORDER BY v.created_at ASC",
			ARRAY_A
		);

		return $results ?: array();
	}
}
