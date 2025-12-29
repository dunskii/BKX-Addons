<?php
/**
 * Route Optimizer Service.
 *
 * @package BookingX\MobileBookings\Services
 * @since   1.0.0
 */

namespace BookingX\MobileBookings\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RouteOptimizer Class.
 */
class RouteOptimizer {

	/**
	 * Distance calculator.
	 *
	 * @var DistanceCalculator
	 */
	private $distance_calculator;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param DistanceCalculator $distance_calculator Distance calculator.
	 * @param array              $settings            Plugin settings.
	 */
	public function __construct( DistanceCalculator $distance_calculator, $settings ) {
		$this->distance_calculator = $distance_calculator;
		$this->settings            = $settings;
	}

	/**
	 * Optimize daily route for a provider.
	 *
	 * @param int    $provider_id Provider ID.
	 * @param string $date        Date (Y-m-d).
	 * @return array|\WP_Error
	 */
	public function optimize_daily_route( $provider_id, $date ) {
		$bookings = $this->get_provider_bookings_with_locations( $provider_id, $date );

		if ( empty( $bookings ) ) {
			return new \WP_Error( 'no_bookings', __( 'No bookings found for this date.', 'bkx-mobile-bookings' ) );
		}

		// Check if we should consider time windows.
		$consider_windows = ! empty( $this->settings['consider_appointment_time_windows'] );

		if ( $consider_windows ) {
			// Optimize within time constraints.
			$optimized = $this->optimize_with_time_windows( $bookings, $provider_id );
		} else {
			// Pure distance optimization.
			$optimized = $this->optimize_by_distance( $bookings, $provider_id );
		}

		// Calculate route stats.
		$route_data = $this->calculate_route_stats( $optimized, $provider_id );

		// Save the optimized route.
		$this->save_route( $provider_id, $date, $route_data );

		return $route_data;
	}

	/**
	 * Optimize bookings by distance (nearest neighbor algorithm).
	 *
	 * @param array $bookings    Bookings to optimize.
	 * @param int   $provider_id Provider ID.
	 * @return array
	 */
	private function optimize_by_distance( $bookings, $provider_id ) {
		if ( count( $bookings ) <= 1 ) {
			return $bookings;
		}

		$start_location = $this->get_provider_start_location( $provider_id );
		$optimized      = array();
		$remaining      = $bookings;
		$current_lat    = $start_location['lat'] ?? $bookings[0]['lat'];
		$current_lng    = $start_location['lng'] ?? $bookings[0]['lng'];

		while ( ! empty( $remaining ) ) {
			$nearest_index    = 0;
			$nearest_distance = PHP_FLOAT_MAX;

			foreach ( $remaining as $index => $booking ) {
				if ( empty( $booking['lat'] ) || empty( $booking['lng'] ) ) {
					continue;
				}

				$distance = $this->distance_calculator->calculate_by_coordinates(
					$current_lat,
					$current_lng,
					$booking['lat'],
					$booking['lng']
				);

				if ( ! is_wp_error( $distance ) && $distance['distance_miles'] < $nearest_distance ) {
					$nearest_distance = $distance['distance_miles'];
					$nearest_index    = $index;
				}
			}

			$next_booking = $remaining[ $nearest_index ];
			$optimized[]  = $next_booking;

			$current_lat = $next_booking['lat'];
			$current_lng = $next_booking['lng'];

			array_splice( $remaining, $nearest_index, 1 );
		}

		return $optimized;
	}

	/**
	 * Optimize bookings considering time windows.
	 *
	 * @param array $bookings    Bookings to optimize.
	 * @param int   $provider_id Provider ID.
	 * @return array
	 */
	private function optimize_with_time_windows( $bookings, $provider_id ) {
		// Sort by start time first to respect appointment times.
		usort(
			$bookings,
			function ( $a, $b ) {
				return strtotime( $a['start_time'] ) - strtotime( $b['start_time'] );
			}
		);

		// Group bookings by time clusters.
		$clusters = $this->cluster_by_time( $bookings );

		$optimized = array();

		// Optimize within each cluster.
		foreach ( $clusters as $cluster ) {
			if ( count( $cluster ) > 1 ) {
				$cluster_optimized = $this->optimize_by_distance( $cluster, $provider_id );
				$optimized         = array_merge( $optimized, $cluster_optimized );
			} else {
				$optimized = array_merge( $optimized, $cluster );
			}
		}

		return $optimized;
	}

	/**
	 * Cluster bookings by time proximity.
	 *
	 * @param array $bookings Sorted bookings.
	 * @return array
	 */
	private function cluster_by_time( $bookings ) {
		$clusters         = array();
		$current_cluster  = array();
		$cluster_end_time = 0;

		foreach ( $bookings as $booking ) {
			$start_time = strtotime( $booking['start_time'] );

			// If this booking starts more than 2 hours after the cluster end, start new cluster.
			if ( $cluster_end_time > 0 && ( $start_time - $cluster_end_time ) > 7200 ) {
				$clusters[]      = $current_cluster;
				$current_cluster = array();
			}

			$current_cluster[] = $booking;
			$cluster_end_time  = strtotime( $booking['end_time'] );
		}

		if ( ! empty( $current_cluster ) ) {
			$clusters[] = $current_cluster;
		}

		return $clusters;
	}

	/**
	 * Calculate route statistics.
	 *
	 * @param array $bookings    Ordered bookings.
	 * @param int   $provider_id Provider ID.
	 * @return array
	 */
	private function calculate_route_stats( $bookings, $provider_id ) {
		$total_distance_miles = 0;
		$total_distance_km    = 0;
		$total_travel_minutes = 0;
		$route_order          = array();
		$segments             = array();

		$start_location = $this->get_provider_start_location( $provider_id );
		$current_lat    = $start_location['lat'] ?? null;
		$current_lng    = $start_location['lng'] ?? null;

		// Calculate from provider start to first booking.
		if ( $current_lat && $current_lng && ! empty( $bookings[0]['lat'] ) ) {
			$first_leg = $this->distance_calculator->calculate_by_coordinates(
				$current_lat,
				$current_lng,
				$bookings[0]['lat'],
				$bookings[0]['lng']
			);

			if ( ! is_wp_error( $first_leg ) ) {
				$total_distance_miles += $first_leg['distance_miles'];
				$total_distance_km    += $first_leg['distance_km'];
				$total_travel_minutes += $first_leg['duration_minutes'];

				$segments[] = array(
					'from'             => 'Start',
					'to'               => $bookings[0]['address'] ?? 'Booking 1',
					'distance_miles'   => $first_leg['distance_miles'],
					'duration_minutes' => $first_leg['duration_minutes'],
				);
			}
		}

		// Calculate between bookings.
		for ( $i = 0; $i < count( $bookings ); $i++ ) {
			$route_order[] = $bookings[ $i ]['booking_id'];

			if ( $i < count( $bookings ) - 1 ) {
				$from = $bookings[ $i ];
				$to   = $bookings[ $i + 1 ];

				if ( ! empty( $from['lat'] ) && ! empty( $to['lat'] ) ) {
					$leg = $this->distance_calculator->calculate_by_coordinates(
						$from['lat'],
						$from['lng'],
						$to['lat'],
						$to['lng']
					);

					if ( ! is_wp_error( $leg ) ) {
						$total_distance_miles += $leg['distance_miles'];
						$total_distance_km    += $leg['distance_km'];
						$total_travel_minutes += $leg['duration_minutes'];

						$segments[] = array(
							'from'             => $from['address'] ?? 'Booking ' . ( $i + 1 ),
							'to'               => $to['address'] ?? 'Booking ' . ( $i + 2 ),
							'distance_miles'   => $leg['distance_miles'],
							'duration_minutes' => $leg['duration_minutes'],
						);
					}
				}
			}
		}

		// Calculate return trip if configured.
		if ( ! empty( $this->settings['return_to_provider_home'] ) && $start_location ) {
			$last_booking = end( $bookings );

			if ( ! empty( $last_booking['lat'] ) ) {
				$return_leg = $this->distance_calculator->calculate_by_coordinates(
					$last_booking['lat'],
					$last_booking['lng'],
					$start_location['lat'],
					$start_location['lng']
				);

				if ( ! is_wp_error( $return_leg ) ) {
					$total_distance_miles += $return_leg['distance_miles'];
					$total_distance_km    += $return_leg['distance_km'];
					$total_travel_minutes += $return_leg['duration_minutes'];

					$segments[] = array(
						'from'             => $last_booking['address'] ?? 'Last Booking',
						'to'               => 'Home',
						'distance_miles'   => $return_leg['distance_miles'],
						'duration_minutes' => $return_leg['duration_minutes'],
					);
				}
			}
		}

		return array(
			'route_order'          => $route_order,
			'total_bookings'       => count( $bookings ),
			'total_distance_miles' => round( $total_distance_miles, 2 ),
			'total_distance_km'    => round( $total_distance_km, 2 ),
			'total_travel_minutes' => $total_travel_minutes,
			'total_travel_text'    => $this->distance_calculator->format_duration( $total_travel_minutes ),
			'segments'             => $segments,
			'bookings'             => $bookings,
		);
	}

	/**
	 * Get daily route for provider.
	 *
	 * @param int    $provider_id Provider ID.
	 * @param string $date        Date.
	 * @return array|null
	 */
	public function get_daily_route( $provider_id, $date ) {
		global $wpdb;

		$route = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}bkx_provider_routes
				WHERE provider_id = %d AND route_date = %s",
				$provider_id,
				$date
			),
			ARRAY_A
		);

		if ( $route ) {
			$route['route_order'] = json_decode( $route['route_order'], true );

			// Get booking details.
			$bookings = $this->get_provider_bookings_with_locations( $provider_id, $date );

			// Order by route_order.
			$ordered_bookings = array();
			foreach ( $route['route_order'] as $booking_id ) {
				foreach ( $bookings as $booking ) {
					if ( (int) $booking['booking_id'] === (int) $booking_id ) {
						$ordered_bookings[] = $booking;
						break;
					}
				}
			}

			$route['bookings'] = $ordered_bookings;
		}

		return $route;
	}

	/**
	 * Save route to database.
	 *
	 * @param int    $provider_id Provider ID.
	 * @param string $date        Date.
	 * @param array  $route_data  Route data.
	 * @return int|false
	 */
	private function save_route( $provider_id, $date, $route_data ) {
		global $wpdb;

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}bkx_provider_routes
				WHERE provider_id = %d AND route_date = %s",
				$provider_id,
				$date
			)
		);

		$data = array(
			'provider_id'              => $provider_id,
			'route_date'               => $date,
			'total_distance_miles'     => $route_data['total_distance_miles'],
			'total_distance_km'        => $route_data['total_distance_km'],
			'total_travel_time_minutes' => $route_data['total_travel_minutes'],
			'total_bookings'           => $route_data['total_bookings'],
			'route_order'              => wp_json_encode( $route_data['route_order'] ),
			'is_optimized'             => 1,
			'optimized_at'             => current_time( 'mysql' ),
			'status'                   => 'planned',
			'updated_at'               => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%f', '%f', '%d', '%d', '%s', '%d', '%s', '%s', '%s' );

		if ( $existing ) {
			return $wpdb->update(
				$wpdb->prefix . 'bkx_provider_routes',
				$data,
				array( 'id' => $existing ),
				$formats,
				array( '%d' )
			);
		}

		$data['created_at'] = current_time( 'mysql' );
		$formats[]          = '%s';

		return $wpdb->insert(
			$wpdb->prefix . 'bkx_provider_routes',
			$data,
			$formats
		);
	}

	/**
	 * Get route summary for display.
	 *
	 * @param int    $provider_id Provider ID.
	 * @param string $date        Date.
	 * @return array
	 */
	public function get_route_summary( $provider_id, $date ) {
		$route = $this->get_daily_route( $provider_id, $date );

		if ( ! $route ) {
			return array(
				'has_route'      => false,
				'total_bookings' => 0,
			);
		}

		return array(
			'has_route'            => true,
			'is_optimized'         => (bool) $route['is_optimized'],
			'total_bookings'       => $route['total_bookings'],
			'total_distance_miles' => $route['total_distance_miles'],
			'total_travel_minutes' => $route['total_travel_time_minutes'],
			'total_travel_text'    => $this->distance_calculator->format_duration( $route['total_travel_time_minutes'] ),
			'status'               => $route['status'],
			'optimized_at'         => $route['optimized_at'],
		);
	}

	/**
	 * Get provider bookings with location data.
	 *
	 * @param int    $provider_id Provider ID.
	 * @param string $date        Date.
	 * @return array
	 */
	private function get_provider_bookings_with_locations( $provider_id, $date ) {
		global $wpdb;

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID as booking_id,
				        pm1.meta_value as start_time,
				        pm2.meta_value as end_time,
				        pm4.meta_value as service_name,
				        l.latitude as lat,
				        l.longitude as lng,
				        l.address,
				        l.formatted_address
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = 'booking_start_time'
				LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'booking_end_time'
				LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = 'seat_id'
				LEFT JOIN {$wpdb->postmeta} pm4 ON p.ID = pm4.post_id AND pm4.meta_key = 'service_name'
				LEFT JOIN {$wpdb->prefix}bkx_locations l ON p.ID = l.booking_id AND l.location_type = 'customer'
				WHERE p.post_type = 'bkx_booking'
				AND p.post_status IN ('bkx-pending', 'bkx-ack')
				AND pm3.meta_value = %d
				AND DATE(pm1.meta_value) = %s
				ORDER BY pm1.meta_value ASC",
				$provider_id,
				$date
			),
			ARRAY_A
		);

		return $bookings ?: array();
	}

	/**
	 * Get provider's start location (home address).
	 *
	 * @param int $provider_id Provider ID.
	 * @return array|null
	 */
	private function get_provider_start_location( $provider_id ) {
		if ( empty( $this->settings['start_from_provider_home'] ) ) {
			return null;
		}

		global $wpdb;

		$location = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT latitude as lat, longitude as lng, address
				FROM {$wpdb->prefix}bkx_locations
				WHERE provider_id = %d AND location_type = 'provider'
				ORDER BY created_at DESC
				LIMIT 1",
				$provider_id
			),
			ARRAY_A
		);

		return $location;
	}

	/**
	 * Export route to navigation app format.
	 *
	 * @param int    $provider_id Provider ID.
	 * @param string $date        Date.
	 * @param string $app         Navigation app (google_maps, waze, apple_maps).
	 * @return string|null Navigation URL.
	 */
	public function export_route_url( $provider_id, $date, $app = 'google_maps' ) {
		$route = $this->get_daily_route( $provider_id, $date );

		if ( ! $route || empty( $route['bookings'] ) ) {
			return null;
		}

		$waypoints = array();
		foreach ( $route['bookings'] as $booking ) {
			if ( ! empty( $booking['lat'] ) && ! empty( $booking['lng'] ) ) {
				$waypoints[] = $booking['lat'] . ',' . $booking['lng'];
			}
		}

		if ( empty( $waypoints ) ) {
			return null;
		}

		$origin      = array_shift( $waypoints );
		$destination = array_pop( $waypoints ) ?? $origin;

		switch ( $app ) {
			case 'google_maps':
				$url = 'https://www.google.com/maps/dir/?api=1';
				$url = add_query_arg( 'origin', $origin, $url );
				$url = add_query_arg( 'destination', $destination, $url );

				if ( ! empty( $waypoints ) ) {
					$url = add_query_arg( 'waypoints', implode( '|', $waypoints ), $url );
				}

				return $url;

			case 'waze':
				// Waze only supports single destination.
				return 'https://www.waze.com/ul?ll=' . $destination . '&navigate=yes';

			case 'apple_maps':
				$url = 'https://maps.apple.com/?';
				$url = add_query_arg( 'saddr', $origin, $url );
				$url = add_query_arg( 'daddr', $destination, $url );

				return $url;

			default:
				return null;
		}
	}
}
