<?php
/**
 * Merchant Manager for Reserve with Google.
 *
 * @package BookingX\ReserveGoogle
 */

namespace BookingX\ReserveGoogle\Services;

defined( 'ABSPATH' ) || exit;

/**
 * MerchantManager class.
 */
class MerchantManager {

	/**
	 * Update merchant info.
	 *
	 * @param array $settings Settings data.
	 * @return bool
	 */
	public function update_merchant( $settings ) {
		global $wpdb;

		$merchant_id = $settings['merchant_id'] ?? '';

		if ( empty( $merchant_id ) ) {
			$merchant_id = $this->generate_merchant_id();
			$settings['merchant_id'] = $merchant_id;
			update_option( 'bkx_reserve_google_settings', $settings );
		}

		$data = array(
			'merchant_id' => $merchant_id,
			'name'        => $settings['business_name'] ?? get_bloginfo( 'name' ),
			'place_id'    => $settings['place_id'] ?? null,
			'address'     => $settings['business_address'] ?? null,
			'phone'       => $settings['business_phone'] ?? null,
			'website'     => home_url(),
			'category'    => $settings['business_category'] ?? null,
			'timezone'    => $settings['timezone'] ?? wp_timezone_string(),
			'updated_at'  => current_time( 'mysql', true ),
		);

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bkx_rwg_merchants WHERE merchant_id = %s",
				$merchant_id
			)
		);

		if ( $existing ) {
			return $wpdb->update(
				$wpdb->prefix . 'bkx_rwg_merchants',
				$data,
				array( 'id' => $existing )
			);
		}

		$data['status']     = 'pending';
		$data['created_at'] = current_time( 'mysql', true );

		return $wpdb->insert( $wpdb->prefix . 'bkx_rwg_merchants', $data );
	}

	/**
	 * Generate unique merchant ID.
	 *
	 * @return string
	 */
	private function generate_merchant_id() {
		return 'merchant_' . wp_generate_uuid4();
	}

	/**
	 * Verify merchant.
	 *
	 * @param string $merchant_id Merchant ID.
	 * @return bool
	 */
	public function verify_merchant( $merchant_id ) {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'bkx_rwg_merchants',
			array(
				'status'      => 'verified',
				'verified_at' => current_time( 'mysql', true ),
			),
			array( 'merchant_id' => $merchant_id )
		);
	}

	/**
	 * Get merchant info.
	 *
	 * @param string $merchant_id Merchant ID.
	 * @return object|null
	 */
	public function get_merchant( $merchant_id = null ) {
		global $wpdb;

		if ( $merchant_id ) {
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}bkx_rwg_merchants WHERE merchant_id = %s",
					$merchant_id
				)
			);
		}

		// Get first/primary merchant.
		return $wpdb->get_row(
			"SELECT * FROM {$wpdb->prefix}bkx_rwg_merchants ORDER BY id ASC LIMIT 1"
		);
	}

	/**
	 * Sync services from BookingX.
	 *
	 * @return int Number of services synced.
	 */
	public function sync_services() {
		global $wpdb;

		$settings    = get_option( 'bkx_reserve_google_settings', array() );
		$merchant_id = $settings['merchant_id'] ?? '';

		if ( empty( $merchant_id ) ) {
			return 0;
		}

		// Get all BookingX services.
		$services = get_posts( array(
			'post_type'      => 'bkx_base',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		) );

		$synced = 0;

		foreach ( $services as $service ) {
			$price    = get_post_meta( $service->ID, 'base_price', true );
			$duration = get_post_meta( $service->ID, 'base_time', true );

			$data = array(
				'merchant_id'      => $merchant_id,
				'bkx_service_id'   => $service->ID,
				'name'             => $service->post_title,
				'description'      => wp_strip_all_tags( $service->post_content ),
				'price'            => (float) $price,
				'currency'         => get_option( 'bkx_currency', 'USD' ),
				'duration_minutes' => (int) $duration ?: 60,
				'enabled'          => 1,
				'updated_at'       => current_time( 'mysql', true ),
			);

			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}bkx_rwg_services WHERE bkx_service_id = %d",
					$service->ID
				)
			);

			if ( $existing ) {
				$wpdb->update(
					$wpdb->prefix . 'bkx_rwg_services',
					$data,
					array( 'id' => $existing )
				);
			} else {
				$data['rwg_service_id'] = 'service_' . $service->ID;
				$data['created_at']     = current_time( 'mysql', true );
				$wpdb->insert( $wpdb->prefix . 'bkx_rwg_services', $data );
			}

			$synced++;
		}

		// Disable services that no longer exist.
		$service_ids = wp_list_pluck( $services, 'ID' );
		if ( ! empty( $service_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $service_ids ), '%d' ) );
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}bkx_rwg_services SET enabled = 0 WHERE bkx_service_id NOT IN ({$placeholders})", // phpcs:ignore
					$service_ids
				)
			);
		}

		return $synced;
	}

	/**
	 * Get synced services.
	 *
	 * @param bool $enabled_only Only enabled services.
	 * @return array
	 */
	public function get_services( $enabled_only = true ) {
		global $wpdb;

		$where = $enabled_only ? 'WHERE enabled = 1' : '';

		return $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}bkx_rwg_services {$where} ORDER BY name ASC" // phpcs:ignore
		);
	}

	/**
	 * Get service by ID.
	 *
	 * @param int    $service_id Service ID.
	 * @param string $id_type    ID type: 'bkx' or 'rwg'.
	 * @return object|null
	 */
	public function get_service( $service_id, $id_type = 'rwg' ) {
		global $wpdb;

		$column = 'rwg' === $id_type ? 'rwg_service_id' : 'bkx_service_id';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_rwg_services WHERE {$column} = %s", // phpcs:ignore
				$service_id
			)
		);
	}

	/**
	 * Update service settings.
	 *
	 * @param int   $service_id Service ID.
	 * @param array $data       Service data.
	 * @return bool
	 */
	public function update_service( $service_id, $data ) {
		global $wpdb;

		$allowed = array(
			'name',
			'description',
			'price',
			'currency',
			'duration_minutes',
			'category',
			'prepayment_type',
			'require_credit_card',
			'enabled',
		);

		$update_data = array_intersect_key( $data, array_flip( $allowed ) );
		$update_data['updated_at'] = current_time( 'mysql', true );

		return $wpdb->update(
			$wpdb->prefix . 'bkx_rwg_services',
			$update_data,
			array( 'id' => $service_id )
		);
	}
}
