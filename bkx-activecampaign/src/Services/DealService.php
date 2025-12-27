<?php
/**
 * Deal Service for ActiveCampaign
 *
 * @package BookingX\ActiveCampaign
 * @since   1.0.0
 */

namespace BookingX\ActiveCampaign\Services;

use BookingX\ActiveCampaign\ActiveCampaignAddon;

/**
 * Class DealService
 *
 * Handles deal/pipeline management in ActiveCampaign.
 *
 * @since 1.0.0
 */
class DealService {

	/**
	 * API instance.
	 *
	 * @var ActiveCampaignAPI
	 */
	private ActiveCampaignAPI $api;

	/**
	 * Addon instance.
	 *
	 * @var ActiveCampaignAddon
	 */
	private ActiveCampaignAddon $addon;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param ActiveCampaignAPI   $api   API instance.
	 * @param ActiveCampaignAddon $addon Addon instance.
	 */
	public function __construct( ActiveCampaignAPI $api, ActiveCampaignAddon $addon ) {
		$this->api   = $api;
		$this->addon = $addon;
	}

	/**
	 * Create deal from booking.
	 *
	 * @since 1.0.0
	 * @param int $booking_id Booking ID.
	 * @param int $contact_id ActiveCampaign contact ID.
	 * @return int|\WP_Error Deal ID or error.
	 */
	public function create_deal_from_booking( int $booking_id, int $contact_id ) {
		$pipeline_id = $this->addon->get_setting( 'deal_pipeline_id', '' );
		$stage_id    = $this->addon->get_setting( 'deal_stage_id', '' );

		if ( empty( $pipeline_id ) || empty( $stage_id ) ) {
			return new \WP_Error(
				'missing_config',
				__( 'Pipeline and stage must be configured', 'bkx-activecampaign' )
			);
		}

		$booking      = get_post( $booking_id );
		$service_id   = get_post_meta( $booking_id, 'base_id', true );
		$service      = get_post( $service_id );
		$total        = get_post_meta( $booking_id, 'booking_total', true );
		$booking_date = get_post_meta( $booking_id, 'booking_date', true );

		$deal_data = array(
			'contact'     => $contact_id,
			'title'       => sprintf(
				/* translators: 1: Service name, 2: Booking date */
				__( 'Booking: %1$s on %2$s', 'bkx-activecampaign' ),
				$service ? $service->post_title : __( 'Service', 'bkx-activecampaign' ),
				$booking_date
			),
			'value'       => (int) ( floatval( $total ) * 100 ), // Value in cents.
			'currency'    => get_option( 'bkx_currency', 'usd' ),
			'group'       => $pipeline_id,
			'stage'       => $stage_id,
			'description' => sprintf(
				/* translators: 1: Booking ID, 2: Service name */
				__( 'Booking #%1$d for %2$s', 'bkx-activecampaign' ),
				$booking_id,
				$service ? $service->post_title : __( 'Service', 'bkx-activecampaign' )
			),
		);

		$deal_id = $this->api->create_deal( $deal_data );

		if ( ! is_wp_error( $deal_id ) ) {
			update_post_meta( $booking_id, '_activecampaign_deal_id', $deal_id );
			$this->addon->log( sprintf( 'Created deal %d for booking %d', $deal_id, $booking_id ) );
		}

		return $deal_id;
	}

	/**
	 * Update deal stage.
	 *
	 * @since 1.0.0
	 * @param int    $booking_id Booking ID.
	 * @param string $status     New status (won, lost, open).
	 * @return int|\WP_Error Deal ID or error.
	 */
	public function update_deal_stage( int $booking_id, string $status ) {
		$deal_id = get_post_meta( $booking_id, '_activecampaign_deal_id', true );

		if ( empty( $deal_id ) ) {
			return new \WP_Error(
				'no_deal',
				__( 'No deal found for this booking', 'bkx-activecampaign' )
			);
		}

		$status_map = array(
			'won'  => 1,
			'lost' => 2,
			'open' => 0,
		);

		$deal_data = array(
			'status' => $status_map[ $status ] ?? 0,
		);

		// Get stage for status if configured.
		$stage_key = $status . '_stage_id';
		$stage_id  = $this->addon->get_setting( $stage_key, '' );

		if ( ! empty( $stage_id ) ) {
			$deal_data['stage'] = $stage_id;
		}

		$result = $this->api->update_deal( (int) $deal_id, $deal_data );

		if ( ! is_wp_error( $result ) ) {
			$this->addon->log( sprintf( 'Updated deal %d to status %s', $deal_id, $status ) );
		}

		return $result;
	}

	/**
	 * Get pipelines for settings.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_pipelines(): array {
		return $this->api->get_pipelines();
	}

	/**
	 * Get stages for a pipeline.
	 *
	 * @since 1.0.0
	 * @param int $pipeline_id Pipeline ID.
	 * @return array
	 */
	public function get_stages( int $pipeline_id ): array {
		return $this->api->get_pipeline_stages( $pipeline_id );
	}
}
