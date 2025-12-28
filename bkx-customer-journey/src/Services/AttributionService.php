<?php
/**
 * Attribution Service.
 *
 * @package BookingX\CustomerJourney
 * @since   1.0.0
 */

namespace BookingX\CustomerJourney\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AttributionService Class.
 *
 * Provides multi-touch attribution modeling.
 */
class AttributionService {

	/**
	 * Attribution models.
	 *
	 * @var array
	 */
	private $models = array(
		'first_touch' => 'First Touch',
		'last_touch'  => 'Last Touch',
		'linear'      => 'Linear',
		'time_decay'  => 'Time Decay',
		'position'    => 'Position-Based',
	);

	/**
	 * Get available models.
	 *
	 * @return array
	 */
	public function get_models() {
		return $this->models;
	}

	/**
	 * Get attribution analysis.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @param string $model      Attribution model.
	 * @return array
	 */
	public function get_attribution( $start_date = '', $end_date = '', $model = 'first_touch' ) {
		$conversions = $this->get_conversions( $start_date, $end_date );

		if ( empty( $conversions ) ) {
			return array(
				'model'     => $model,
				'channels'  => array(),
				'summary'   => array(),
				'paths'     => array(),
			);
		}

		// Apply attribution model.
		$channel_credits = $this->apply_model( $conversions, $model );

		// Get channel summary.
		$channels = $this->aggregate_channels( $channel_credits );

		// Get top paths.
		$paths = $this->get_top_paths( $conversions );

		return array(
			'model'    => $model,
			'channels' => $channels,
			'summary'  => $this->get_attribution_summary( $channels, $conversions ),
			'paths'    => $paths,
		);
	}

	/**
	 * Get conversions with touchpoints.
	 *
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array
	 */
	private function get_conversions( $start_date, $end_date ) {
		global $wpdb;

		$journeys_table    = $wpdb->prefix . 'bkx_cj_journeys';
		$touchpoints_table = $wpdb->prefix . 'bkx_cj_touchpoints';
		$date_clause       = '';

		if ( ! empty( $start_date ) ) {
			$date_clause .= " AND j.journey_start >= '" . esc_sql( $start_date ) . " 00:00:00'";
		}

		if ( ! empty( $end_date ) ) {
			$date_clause .= " AND j.journey_start <= '" . esc_sql( $end_date ) . " 23:59:59'";
		}

		// Get converted journeys.
		$journeys = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT j.*,
					(SELECT total_amount FROM {$wpdb->postmeta}
					 WHERE post_id = j.booking_id AND meta_key = 'total_amount') as revenue
				FROM %i j
				WHERE j.journey_outcome = 'converted' {$date_clause}",
				$journeys_table
			),
			ARRAY_A
		);

		$conversions = array();

		foreach ( $journeys as $journey ) {
			// Get touchpoints for this journey.
			$touchpoints = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT touchpoint_type, referrer, created_at
					FROM %i
					WHERE session_id = %s
					ORDER BY created_at ASC",
					$touchpoints_table,
					$journey['session_id']
				),
				ARRAY_A
			);

			$channels = array();
			foreach ( $touchpoints as $tp ) {
				$channel = $this->determine_channel( $tp['referrer'], $tp['touchpoint_type'] );
				if ( $channel && ( empty( $channels ) || end( $channels )['channel'] !== $channel ) ) {
					$channels[] = array(
						'channel'   => $channel,
						'timestamp' => $tp['created_at'],
					);
				}
			}

			if ( ! empty( $channels ) ) {
				$conversions[] = array(
					'session_id' => $journey['session_id'],
					'booking_id' => $journey['booking_id'],
					'revenue'    => (float) ( $journey['revenue'] ?? 0 ),
					'channels'   => $channels,
				);
			}
		}

		return $conversions;
	}

	/**
	 * Apply attribution model.
	 *
	 * @param array  $conversions Conversions data.
	 * @param string $model       Attribution model.
	 * @return array
	 */
	private function apply_model( $conversions, $model ) {
		$results = array();

		foreach ( $conversions as $conversion ) {
			$channels = $conversion['channels'];
			$revenue  = $conversion['revenue'];
			$count    = count( $channels );

			if ( $count === 0 ) {
				continue;
			}

			switch ( $model ) {
				case 'first_touch':
					$credits = $this->first_touch_model( $channels, $revenue );
					break;

				case 'last_touch':
					$credits = $this->last_touch_model( $channels, $revenue );
					break;

				case 'linear':
					$credits = $this->linear_model( $channels, $revenue );
					break;

				case 'time_decay':
					$credits = $this->time_decay_model( $channels, $revenue );
					break;

				case 'position':
					$credits = $this->position_model( $channels, $revenue );
					break;

				default:
					$credits = $this->first_touch_model( $channels, $revenue );
			}

			$results[] = array(
				'session_id' => $conversion['session_id'],
				'credits'    => $credits,
			);
		}

		return $results;
	}

	/**
	 * First touch attribution model.
	 *
	 * @param array $channels Channels.
	 * @param float $revenue  Revenue.
	 * @return array
	 */
	private function first_touch_model( $channels, $revenue ) {
		$first = $channels[0]['channel'];
		return array(
			$first => array(
				'credit'       => 1,
				'revenue'      => $revenue,
				'conversions'  => 1,
			),
		);
	}

	/**
	 * Last touch attribution model.
	 *
	 * @param array $channels Channels.
	 * @param float $revenue  Revenue.
	 * @return array
	 */
	private function last_touch_model( $channels, $revenue ) {
		$last = end( $channels )['channel'];
		return array(
			$last => array(
				'credit'      => 1,
				'revenue'     => $revenue,
				'conversions' => 1,
			),
		);
	}

	/**
	 * Linear attribution model.
	 *
	 * @param array $channels Channels.
	 * @param float $revenue  Revenue.
	 * @return array
	 */
	private function linear_model( $channels, $revenue ) {
		$count   = count( $channels );
		$credit  = 1 / $count;
		$rev_per = $revenue / $count;

		$results = array();
		foreach ( $channels as $ch ) {
			$channel = $ch['channel'];
			if ( ! isset( $results[ $channel ] ) ) {
				$results[ $channel ] = array(
					'credit'      => 0,
					'revenue'     => 0,
					'conversions' => 0,
				);
			}
			$results[ $channel ]['credit']  += $credit;
			$results[ $channel ]['revenue'] += $rev_per;
		}

		// Count as 1 conversion spread across channels.
		foreach ( $results as $channel => &$data ) {
			$data['conversions'] = $data['credit'];
		}

		return $results;
	}

	/**
	 * Time decay attribution model.
	 *
	 * More recent touchpoints get more credit.
	 *
	 * @param array $channels Channels.
	 * @param float $revenue  Revenue.
	 * @return array
	 */
	private function time_decay_model( $channels, $revenue ) {
		$count       = count( $channels );
		$half_life   = 7 * 24 * 3600; // 7 days in seconds.
		$last_time   = strtotime( end( $channels )['timestamp'] );
		$total_weight = 0;
		$weights     = array();

		foreach ( $channels as $i => $ch ) {
			$time_diff   = $last_time - strtotime( $ch['timestamp'] );
			$weight      = pow( 2, -$time_diff / $half_life );
			$weights[ $i ] = $weight;
			$total_weight += $weight;
		}

		$results = array();
		foreach ( $channels as $i => $ch ) {
			$channel = $ch['channel'];
			$credit  = $weights[ $i ] / $total_weight;

			if ( ! isset( $results[ $channel ] ) ) {
				$results[ $channel ] = array(
					'credit'      => 0,
					'revenue'     => 0,
					'conversions' => 0,
				);
			}

			$results[ $channel ]['credit']      += $credit;
			$results[ $channel ]['revenue']     += $revenue * $credit;
			$results[ $channel ]['conversions'] += $credit;
		}

		return $results;
	}

	/**
	 * Position-based attribution model.
	 *
	 * 40% first, 40% last, 20% middle.
	 *
	 * @param array $channels Channels.
	 * @param float $revenue  Revenue.
	 * @return array
	 */
	private function position_model( $channels, $revenue ) {
		$count = count( $channels );

		if ( $count === 1 ) {
			return $this->first_touch_model( $channels, $revenue );
		}

		if ( $count === 2 ) {
			$first = $channels[0]['channel'];
			$last  = $channels[1]['channel'];

			return array(
				$first => array(
					'credit'      => 0.5,
					'revenue'     => $revenue * 0.5,
					'conversions' => 0.5,
				),
				$last  => array(
					'credit'      => 0.5,
					'revenue'     => $revenue * 0.5,
					'conversions' => 0.5,
				),
			);
		}

		$results       = array();
		$first_credit  = 0.4;
		$last_credit   = 0.4;
		$middle_credit = 0.2 / ( $count - 2 );

		foreach ( $channels as $i => $ch ) {
			$channel = $ch['channel'];

			if ( $i === 0 ) {
				$credit = $first_credit;
			} elseif ( $i === $count - 1 ) {
				$credit = $last_credit;
			} else {
				$credit = $middle_credit;
			}

			if ( ! isset( $results[ $channel ] ) ) {
				$results[ $channel ] = array(
					'credit'      => 0,
					'revenue'     => 0,
					'conversions' => 0,
				);
			}

			$results[ $channel ]['credit']      += $credit;
			$results[ $channel ]['revenue']     += $revenue * $credit;
			$results[ $channel ]['conversions'] += $credit;
		}

		return $results;
	}

	/**
	 * Aggregate channel credits.
	 *
	 * @param array $channel_credits Channel credits from all conversions.
	 * @return array
	 */
	private function aggregate_channels( $channel_credits ) {
		$aggregated = array();

		foreach ( $channel_credits as $conversion ) {
			foreach ( $conversion['credits'] as $channel => $data ) {
				if ( ! isset( $aggregated[ $channel ] ) ) {
					$aggregated[ $channel ] = array(
						'channel'     => $channel,
						'label'       => $this->get_channel_label( $channel ),
						'color'       => $this->get_channel_color( $channel ),
						'conversions' => 0,
						'revenue'     => 0,
						'credit'      => 0,
					);
				}

				$aggregated[ $channel ]['conversions'] += $data['conversions'];
				$aggregated[ $channel ]['revenue']     += $data['revenue'];
				$aggregated[ $channel ]['credit']      += $data['credit'];
			}
		}

		// Sort by conversions.
		usort(
			$aggregated,
			function ( $a, $b ) {
				return $b['conversions'] - $a['conversions'];
			}
		);

		return array_values( $aggregated );
	}

	/**
	 * Get attribution summary.
	 *
	 * @param array $channels    Aggregated channels.
	 * @param array $conversions Original conversions.
	 * @return array
	 */
	private function get_attribution_summary( $channels, $conversions ) {
		$total_conversions = count( $conversions );
		$total_revenue     = array_sum( array_column( $conversions, 'revenue' ) );

		// Calculate average path length.
		$path_lengths = array_map(
			function ( $c ) {
				return count( $c['channels'] );
			},
			$conversions
		);
		$avg_path_length = count( $path_lengths ) > 0 ? array_sum( $path_lengths ) / count( $path_lengths ) : 0;

		// Multi-touch rate.
		$multi_touch = count(
			array_filter(
				$conversions,
				function ( $c ) {
					return count( $c['channels'] ) > 1;
				}
			)
		);
		$multi_touch_rate = $total_conversions > 0 ? ( $multi_touch / $total_conversions ) * 100 : 0;

		return array(
			'total_conversions' => $total_conversions,
			'total_revenue'     => $total_revenue,
			'avg_path_length'   => round( $avg_path_length, 1 ),
			'multi_touch_rate'  => round( $multi_touch_rate, 1 ),
			'channels_count'    => count( $channels ),
		);
	}

	/**
	 * Get top conversion paths.
	 *
	 * @param array $conversions Conversions.
	 * @param int   $limit       Limit.
	 * @return array
	 */
	private function get_top_paths( $conversions, $limit = 10 ) {
		$paths = array();

		foreach ( $conversions as $conversion ) {
			$channel_names = array_column( $conversion['channels'], 'channel' );
			$path_key      = implode( ' → ', $channel_names );

			if ( ! isset( $paths[ $path_key ] ) ) {
				$paths[ $path_key ] = array(
					'path'        => $channel_names,
					'path_string' => $path_key,
					'count'       => 0,
					'revenue'     => 0,
				);
			}

			++$paths[ $path_key ]['count'];
			$paths[ $path_key ]['revenue'] += $conversion['revenue'];
		}

		usort(
			$paths,
			function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		return array_slice( array_values( $paths ), 0, $limit );
	}

	/**
	 * Determine channel from touchpoint.
	 *
	 * @param string $referrer       Referrer URL.
	 * @param string $touchpoint_type Touchpoint type.
	 * @return string
	 */
	private function determine_channel( $referrer, $touchpoint_type ) {
		// Check for specific touchpoint types first.
		if ( $touchpoint_type === 'email_click' ) {
			return 'email';
		}

		if ( $touchpoint_type === 'social_click' ) {
			return 'social';
		}

		if ( empty( $referrer ) ) {
			return 'direct';
		}

		$host = wp_parse_url( $referrer, PHP_URL_HOST );

		if ( ! $host ) {
			return 'direct';
		}

		// Search engines.
		$search = array( 'google', 'bing', 'yahoo', 'duckduckgo', 'baidu' );
		foreach ( $search as $engine ) {
			if ( stripos( $host, $engine ) !== false ) {
				// Check for paid.
				if ( stripos( $referrer, 'gclid' ) !== false || stripos( $referrer, 'msclkid' ) !== false ) {
					return 'paid_search';
				}
				return 'organic_search';
			}
		}

		// Social.
		$social = array( 'facebook', 'twitter', 'instagram', 'linkedin', 'pinterest', 'youtube', 'tiktok' );
		foreach ( $social as $network ) {
			if ( stripos( $host, $network ) !== false ) {
				return 'social';
			}
		}

		// Email.
		if ( stripos( $referrer, 'utm_medium=email' ) !== false ) {
			return 'email';
		}

		// Same site.
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $host === $site_host ) {
			return 'direct';
		}

		return 'referral';
	}

	/**
	 * Get channel label.
	 *
	 * @param string $channel Channel.
	 * @return string
	 */
	private function get_channel_label( $channel ) {
		$labels = array(
			'direct'         => __( 'Direct', 'bkx-customer-journey' ),
			'organic_search' => __( 'Organic Search', 'bkx-customer-journey' ),
			'paid_search'    => __( 'Paid Search', 'bkx-customer-journey' ),
			'social'         => __( 'Social Media', 'bkx-customer-journey' ),
			'email'          => __( 'Email', 'bkx-customer-journey' ),
			'referral'       => __( 'Referral', 'bkx-customer-journey' ),
		);

		return $labels[ $channel ] ?? ucfirst( str_replace( '_', ' ', $channel ) );
	}

	/**
	 * Get channel color.
	 *
	 * @param string $channel Channel.
	 * @return string
	 */
	private function get_channel_color( $channel ) {
		$colors = array(
			'direct'         => '#6B7280',
			'organic_search' => '#10B981',
			'paid_search'    => '#F59E0B',
			'social'         => '#3B82F6',
			'email'          => '#8B5CF6',
			'referral'       => '#EC4899',
		);

		return $colors[ $channel ] ?? '#9CA3AF';
	}
}
