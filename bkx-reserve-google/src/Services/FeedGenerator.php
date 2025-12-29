<?php
/**
 * Feed Generator for Reserve with Google.
 *
 * Generates merchant, service, and availability feeds in Google's format.
 *
 * @package BookingX\ReserveGoogle
 */

namespace BookingX\ReserveGoogle\Services;

defined( 'ABSPATH' ) || exit;

/**
 * FeedGenerator class.
 */
class FeedGenerator {

	/**
	 * Merchant manager.
	 *
	 * @var MerchantManager
	 */
	private $merchant_manager;

	/**
	 * Constructor.
	 *
	 * @param MerchantManager $merchant_manager Merchant manager.
	 */
	public function __construct( MerchantManager $merchant_manager ) {
		$this->merchant_manager = $merchant_manager;
	}

	/**
	 * Get merchant feed.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_merchant_feed( $request ) {
		$merchant = $this->merchant_manager->get_merchant();

		if ( ! $merchant ) {
			return rest_ensure_response( array( 'merchant' => array() ) );
		}

		$settings = get_option( 'bkx_reserve_google_settings', array() );

		$merchant_data = array(
			'merchant_id'  => $merchant->merchant_id,
			'name'         => $merchant->name,
			'telephone'    => $merchant->phone,
			'url'          => home_url(),
			'geo'          => $this->parse_address( $merchant->address ),
			'category'     => $merchant->category ?: 'gcid:beauty_salon',
			'action_links' => array(
				array(
					'url'  => home_url( '/book/' ),
					'type' => 'BOOK_ONLINE',
				),
			),
		);

		if ( ! empty( $merchant->place_id ) ) {
			$merchant_data['place_id'] = $merchant->place_id;
		}

		$this->log_request( 'feeds/merchants', 'GET', null, $merchant_data );

		return rest_ensure_response( array(
			'merchant' => array( $merchant_data ),
		) );
	}

	/**
	 * Get services feed.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_services_feed( $request ) {
		$merchant = $this->merchant_manager->get_merchant();

		if ( ! $merchant ) {
			return rest_ensure_response( array( 'service' => array() ) );
		}

		$services     = $this->merchant_manager->get_services( true );
		$services_data = array();

		foreach ( $services as $service ) {
			$service_data = array(
				'service_id'   => $service->rwg_service_id,
				'merchant_id'  => $merchant->merchant_id,
				'name'         => $service->name,
				'description'  => $service->description ?: '',
				'price'        => array(
					'price_micros' => (int) ( $service->price * 1000000 ),
					'currency_code' => $service->currency ?: 'USD',
				),
				'duration_sec' => $service->duration_minutes * 60,
				'rules'        => array(
					'min_advance_booking' => $this->get_min_advance_seconds(),
					'max_advance_booking' => $this->get_max_advance_seconds(),
				),
			);

			if ( $service->prepayment_type !== 'NOT_SUPPORTED' ) {
				$service_data['prepayment_type'] = $service->prepayment_type;
			}

			if ( $service->require_credit_card ) {
				$service_data['require_credit_card'] = true;
			}

			$services_data[] = $service_data;
		}

		$this->log_request( 'feeds/services', 'GET', null, array( 'service' => $services_data ) );

		return rest_ensure_response( array(
			'service' => $services_data,
		) );
	}

	/**
	 * Get availability feed.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_availability_feed( $request ) {
		$merchant = $this->merchant_manager->get_merchant();

		if ( ! $merchant ) {
			return rest_ensure_response( array( 'availability' => array() ) );
		}

		$services          = $this->merchant_manager->get_services( true );
		$availability_sync = new AvailabilitySync();
		$settings          = get_option( 'bkx_reserve_google_settings', array() );

		$start_date = gmdate( 'Y-m-d' );
		$end_date   = gmdate( 'Y-m-d', strtotime( '+' . ( $settings['advance_booking_days'] ?? 30 ) . ' days' ) );

		$availability_data = array();

		foreach ( $services as $service ) {
			$slots = $availability_sync->get_slots( $service->id, $start_date, $end_date );

			foreach ( $slots as $slot ) {
				$start_datetime = $slot->slot_date . 'T' . $slot->start_time;
				$end_datetime   = $slot->slot_date . 'T' . $slot->end_time;

				$availability_data[] = array(
					'merchant_id'  => $merchant->merchant_id,
					'service_id'   => $service->rwg_service_id,
					'start_time'   => gmdate( 'c', strtotime( $start_datetime ) ),
					'end_time'     => gmdate( 'c', strtotime( $end_datetime ) ),
					'spots_total'  => $slot->spots_total,
					'spots_open'   => $slot->spots_open,
				);
			}
		}

		$this->log_request( 'feeds/availability', 'GET', null, array( 'count' => count( $availability_data ) ) );

		return rest_ensure_response( array(
			'availability' => $availability_data,
		) );
	}

	/**
	 * Parse address into geo coordinates.
	 *
	 * @param string $address Address string.
	 * @return array|null
	 */
	private function parse_address( $address ) {
		if ( empty( $address ) ) {
			return null;
		}

		// In a real implementation, this would geocode the address.
		// For now, return the formatted address.
		return array(
			'formatted_address' => $address,
		);
	}

	/**
	 * Get minimum advance booking time in seconds.
	 *
	 * @return int
	 */
	private function get_min_advance_seconds() {
		$settings = get_option( 'bkx_reserve_google_settings', array() );
		$hours    = $settings['min_advance_hours'] ?? 1;

		return $hours * 3600;
	}

	/**
	 * Get maximum advance booking time in seconds.
	 *
	 * @return int
	 */
	private function get_max_advance_seconds() {
		$settings = get_option( 'bkx_reserve_google_settings', array() );
		$days     = $settings['advance_booking_days'] ?? 30;

		return $days * 86400;
	}

	/**
	 * Log API request.
	 *
	 * @param string $endpoint Endpoint.
	 * @param string $method   HTTP method.
	 * @param mixed  $request  Request data.
	 * @param mixed  $response Response data.
	 */
	private function log_request( $endpoint, $method, $request, $response ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bkx_rwg_logs',
			array(
				'endpoint'         => $endpoint,
				'method'           => $method,
				'request_payload'  => $request ? wp_json_encode( $request ) : null,
				'response_payload' => wp_json_encode( $response ),
				'response_code'    => 200,
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s' )
		);
	}
}
