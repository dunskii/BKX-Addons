<?php
/**
 * Test Data Generator Service.
 *
 * @package BookingX\DeveloperSDK
 */

namespace BookingX\DeveloperSDK\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class TestDataGenerator
 *
 * Generates test data for development and testing.
 */
class TestDataGenerator {

	/**
	 * First names for random generation.
	 *
	 * @var array
	 */
	private $first_names = array(
		'John', 'Jane', 'Michael', 'Sarah', 'David', 'Emily', 'James', 'Emma',
		'Robert', 'Olivia', 'William', 'Sophia', 'Richard', 'Isabella', 'Joseph',
		'Mia', 'Thomas', 'Charlotte', 'Charles', 'Amelia',
	);

	/**
	 * Last names for random generation.
	 *
	 * @var array
	 */
	private $last_names = array(
		'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller',
		'Davis', 'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez',
		'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
	);

	/**
	 * Service names for random generation.
	 *
	 * @var array
	 */
	private $service_names = array(
		'Consultation', 'Haircut', 'Massage', 'Dental Checkup', 'Personal Training',
		'Photography Session', 'Legal Consultation', 'Tax Preparation', 'Yoga Class',
		'Music Lesson', 'Tutoring Session', 'Pet Grooming', 'Car Wash', 'House Cleaning',
	);

	/**
	 * Generate test data.
	 *
	 * @param string $type  Data type.
	 * @param int    $count Number to generate.
	 * @return array|WP_Error Generated IDs or error.
	 */
	public function generate( string $type, int $count = 1 ) {
		$method = 'generate_' . $type;

		if ( ! method_exists( $this, $method ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid data type.', 'bkx-developer-sdk' ) );
		}

		$ids = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$id = $this->$method();
			if ( ! is_wp_error( $id ) ) {
				$ids[] = $id;
			}
		}

		return array(
			'type'    => $type,
			'count'   => count( $ids ),
			'ids'     => $ids,
			'message' => sprintf(
				/* translators: 1: count, 2: type */
				__( 'Generated %1$d %2$s.', 'bkx-developer-sdk' ),
				count( $ids ),
				$type
			),
		);
	}

	/**
	 * Generate a test booking.
	 *
	 * @return int|WP_Error Booking ID or error.
	 */
	private function generate_booking() {
		$customer    = $this->generate_customer_data();
		$date_offset = rand( 1, 30 );
		$hour        = rand( 9, 17 );
		$statuses    = array( 'bkx-pending', 'bkx-ack', 'bkx-completed', 'bkx-cancelled' );

		// Get random service and staff.
		$services = get_posts(
			array(
				'post_type'      => 'bkx_base',
				'posts_per_page' => 1,
				'orderby'        => 'rand',
			)
		);

		$staff = get_posts(
			array(
				'post_type'      => 'bkx_seat',
				'posts_per_page' => 1,
				'orderby'        => 'rand',
			)
		);

		$service_id = ! empty( $services ) ? $services[0]->ID : 0;
		$staff_id   = ! empty( $staff ) ? $staff[0]->ID : 0;

		$total = rand( 50, 300 );

		return wp_insert_post(
			array(
				'post_type'   => 'bkx_booking',
				'post_title'  => sprintf( 'Booking - %s %s', $customer['first_name'], $customer['last_name'] ),
				'post_status' => $statuses[ array_rand( $statuses ) ],
				'meta_input'  => array(
					'_test_data'      => '1',
					'booking_date'    => gmdate( 'Y-m-d', strtotime( "+{$date_offset} days" ) ),
					'booking_time'    => sprintf( '%02d:00:00', $hour ),
					'base_id'         => $service_id,
					'seat_id'         => $staff_id,
					'customer_name'   => $customer['first_name'] . ' ' . $customer['last_name'],
					'customer_email'  => $customer['email'],
					'customer_phone'  => $customer['phone'],
					'booking_total'   => $total,
					'booking_notes'   => 'This is test booking data.',
				),
			)
		);
	}

	/**
	 * Generate a test service.
	 *
	 * @return int|WP_Error Service ID or error.
	 */
	private function generate_service() {
		$name     = $this->service_names[ array_rand( $this->service_names ) ];
		$price    = rand( 25, 200 );
		$duration = array( 30, 45, 60, 90, 120 )[ rand( 0, 4 ) ];

		return wp_insert_post(
			array(
				'post_type'    => 'bkx_base',
				'post_title'   => $name . ' ' . rand( 100, 999 ),
				'post_status'  => 'publish',
				'post_content' => sprintf( 'This is a %d-minute %s service.', $duration, strtolower( $name ) ),
				'meta_input'   => array(
					'_test_data'  => '1',
					'base_price'  => $price,
					'base_time'   => $duration,
					'base_desc'   => 'Test service description.',
				),
			)
		);
	}

	/**
	 * Generate a test staff member.
	 *
	 * @return int|WP_Error Staff ID or error.
	 */
	private function generate_staff() {
		$first_name = $this->first_names[ array_rand( $this->first_names ) ];
		$last_name  = $this->last_names[ array_rand( $this->last_names ) ];
		$email      = strtolower( $first_name ) . '.' . strtolower( $last_name ) . '@example.com';

		return wp_insert_post(
			array(
				'post_type'    => 'bkx_seat',
				'post_title'   => $first_name . ' ' . $last_name,
				'post_status'  => 'publish',
				'post_content' => 'Test staff member.',
				'meta_input'   => array(
					'_test_data'  => '1',
					'seat_email'  => $email,
					'seat_phone'  => $this->generate_phone(),
					'seat_bio'    => sprintf( '%s is an experienced professional.', $first_name ),
				),
			)
		);
	}

	/**
	 * Generate a test extra/addition.
	 *
	 * @return int|WP_Error Extra ID or error.
	 */
	private function generate_extra() {
		$extras = array(
			'Express Service'     => 15,
			'Premium Package'     => 25,
			'Extended Session'    => 20,
			'Priority Scheduling' => 10,
			'Materials Fee'       => 5,
			'Setup Fee'           => 15,
		);

		$name  = array_rand( $extras );
		$price = $extras[ $name ];

		return wp_insert_post(
			array(
				'post_type'   => 'bkx_addition',
				'post_title'  => $name . ' ' . rand( 100, 999 ),
				'post_status' => 'publish',
				'meta_input'  => array(
					'_test_data'     => '1',
					'addition_price' => $price,
					'addition_desc'  => 'Test extra service.',
				),
			)
		);
	}

	/**
	 * Generate customer data.
	 *
	 * @return array Customer data.
	 */
	private function generate_customer_data(): array {
		$first_name = $this->first_names[ array_rand( $this->first_names ) ];
		$last_name  = $this->last_names[ array_rand( $this->last_names ) ];

		return array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'email'      => strtolower( $first_name ) . '.' . strtolower( $last_name ) . rand( 10, 99 ) . '@example.com',
			'phone'      => $this->generate_phone(),
		);
	}

	/**
	 * Generate random phone number.
	 *
	 * @return string Phone number.
	 */
	private function generate_phone(): string {
		return sprintf(
			'+1%03d%03d%04d',
			rand( 200, 999 ),
			rand( 200, 999 ),
			rand( 1000, 9999 )
		);
	}

	/**
	 * Delete all test data.
	 *
	 * @return int Number of items deleted.
	 */
	public function delete_all_test_data(): int {
		$deleted = 0;

		$post_types = array( 'bkx_booking', 'bkx_base', 'bkx_seat', 'bkx_addition' );

		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'posts_per_page' => -1,
					'meta_key'       => '_test_data',
					'meta_value'     => '1',
				)
			);

			foreach ( $posts as $post ) {
				wp_delete_post( $post->ID, true );
				$deleted++;
			}
		}

		return $deleted;
	}

	/**
	 * Get test data counts.
	 *
	 * @return array Counts by type.
	 */
	public function get_test_data_counts(): array {
		$counts = array();

		$post_types = array(
			'booking' => 'bkx_booking',
			'service' => 'bkx_base',
			'staff'   => 'bkx_seat',
			'extra'   => 'bkx_addition',
		);

		foreach ( $post_types as $key => $post_type ) {
			$query = new \WP_Query(
				array(
					'post_type'      => $post_type,
					'posts_per_page' => -1,
					'meta_key'       => '_test_data',
					'meta_value'     => '1',
					'fields'         => 'ids',
				)
			);

			$counts[ $key ] = $query->found_posts;
		}

		return $counts;
	}
}
