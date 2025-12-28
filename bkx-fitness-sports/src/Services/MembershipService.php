<?php
/**
 * Membership Service
 *
 * Manages gym memberships, subscriptions, and access control.
 *
 * @package BookingX\FitnessSports\Services
 * @since   1.0.0
 */

namespace BookingX\FitnessSports\Services;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MembershipService
 *
 * @since 1.0.0
 */
class MembershipService {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private string $table_name;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'bkx_user_memberships';
	}

	/**
	 * Get all membership plans.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_plans(): array {
		$plans = get_posts( array(
			'post_type'      => 'bkx_membership',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		) );

		return array_map( array( $this, 'format_plan' ), $plans );
	}

	/**
	 * Get single membership plan.
	 *
	 * @since 1.0.0
	 * @param int $plan_id Plan ID.
	 * @return array|null
	 */
	public function get_plan( int $plan_id ): ?array {
		$plan = get_post( $plan_id );

		if ( ! $plan || 'bkx_membership' !== $plan->post_type ) {
			return null;
		}

		return $this->format_plan( $plan );
	}

	/**
	 * Format plan data.
	 *
	 * @since 1.0.0
	 * @param \WP_Post $plan Plan post.
	 * @return array
	 */
	private function format_plan( \WP_Post $plan ): array {
		$features = get_post_meta( $plan->ID, '_bkx_plan_features', true ) ?: array();

		return array(
			'id'               => $plan->ID,
			'name'             => $plan->post_title,
			'description'      => $plan->post_content,
			'price'            => floatval( get_post_meta( $plan->ID, '_bkx_plan_price', true ) ),
			'billing_period'   => get_post_meta( $plan->ID, '_bkx_billing_period', true ) ?: 'monthly',
			'duration_days'    => absint( get_post_meta( $plan->ID, '_bkx_duration_days', true ) ) ?: 30,
			'classes_per_month' => get_post_meta( $plan->ID, '_bkx_classes_per_month', true ) ?: 'unlimited',
			'features'         => $features,
			'includes_equipment' => (bool) get_post_meta( $plan->ID, '_bkx_includes_equipment', true ),
			'includes_personal_training' => (bool) get_post_meta( $plan->ID, '_bkx_includes_pt', true ),
			'pt_sessions'      => absint( get_post_meta( $plan->ID, '_bkx_pt_sessions', true ) ),
			'is_featured'      => (bool) get_post_meta( $plan->ID, '_bkx_featured_plan', true ),
			'image'            => get_the_post_thumbnail_url( $plan->ID, 'medium' ),
		);
	}

	/**
	 * Check if user has active membership.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function has_active_membership( int $user_id ): bool {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table_name}
				WHERE user_id = %d AND status = 'active' AND end_date >= %s",
				$user_id,
				current_time( 'Y-m-d' )
			)
		);

		return $count > 0;
	}

	/**
	 * Get user's active membership.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return array|null
	 */
	public function get_user_membership( int $user_id ): ?array {
		global $wpdb;

		$membership = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name}
				WHERE user_id = %d AND status = 'active' AND end_date >= %s
				ORDER BY end_date DESC
				LIMIT 1",
				$user_id,
				current_time( 'Y-m-d' )
			),
			ARRAY_A
		);

		if ( ! $membership ) {
			return null;
		}

		$plan = $this->get_plan( $membership['plan_id'] );

		return array(
			'id'                => absint( $membership['id'] ),
			'user_id'           => absint( $membership['user_id'] ),
			'plan_id'           => absint( $membership['plan_id'] ),
			'plan_name'         => $plan ? $plan['name'] : '',
			'status'            => $membership['status'],
			'start_date'        => $membership['start_date'],
			'end_date'          => $membership['end_date'],
			'classes_remaining' => $membership['classes_remaining'],
			'pt_sessions_remaining' => absint( $membership['pt_sessions_remaining'] ),
			'auto_renew'        => (bool) $membership['auto_renew'],
			'days_remaining'    => max( 0, ( strtotime( $membership['end_date'] ) - time() ) / DAY_IN_SECONDS ),
		);
	}

	/**
	 * Create membership for user.
	 *
	 * @since 1.0.0
	 * @param int   $user_id  User ID.
	 * @param int   $plan_id  Plan ID.
	 * @param array $options  Additional options.
	 * @return int|\WP_Error Membership ID or error.
	 */
	public function create_membership( int $user_id, int $plan_id, array $options = array() ) {
		global $wpdb;

		$plan = $this->get_plan( $plan_id );

		if ( ! $plan ) {
			return new \WP_Error( 'invalid_plan', __( 'Invalid membership plan.', 'bkx-fitness-sports' ) );
		}

		$start_date = $options['start_date'] ?? current_time( 'Y-m-d' );
		$end_date   = date( 'Y-m-d', strtotime( $start_date . ' + ' . $plan['duration_days'] . ' days' ) );

		$classes_remaining = 'unlimited' === $plan['classes_per_month'] ? -1 : absint( $plan['classes_per_month'] );

		$result = $wpdb->insert(
			$this->table_name,
			array(
				'user_id'              => $user_id,
				'plan_id'              => $plan_id,
				'status'               => 'active',
				'start_date'           => $start_date,
				'end_date'             => $end_date,
				'classes_remaining'    => $classes_remaining,
				'pt_sessions_remaining' => $plan['pt_sessions'],
				'auto_renew'           => ! empty( $options['auto_renew'] ) ? 1 : 0,
				'payment_id'           => absint( $options['payment_id'] ?? 0 ),
				'created_at'           => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create membership.', 'bkx-fitness-sports' ) );
		}

		$membership_id = $wpdb->insert_id;

		/**
		 * Fires after a membership is created.
		 *
		 * @param int   $membership_id Membership ID.
		 * @param int   $user_id       User ID.
		 * @param array $plan          Plan data.
		 */
		do_action( 'bkx_fitness_membership_created', $membership_id, $user_id, $plan );

		return $membership_id;
	}

	/**
	 * Cancel membership.
	 *
	 * @since 1.0.0
	 * @param int  $membership_id Membership ID.
	 * @param bool $immediate     Cancel immediately or at end of period.
	 * @return bool|\WP_Error
	 */
	public function cancel_membership( int $membership_id, bool $immediate = false ) {
		global $wpdb;

		$membership = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$membership_id
			),
			ARRAY_A
		);

		if ( ! $membership ) {
			return new \WP_Error( 'not_found', __( 'Membership not found.', 'bkx-fitness-sports' ) );
		}

		if ( $immediate ) {
			$result = $wpdb->update(
				$this->table_name,
				array(
					'status'       => 'cancelled',
					'end_date'     => current_time( 'Y-m-d' ),
					'cancelled_at' => current_time( 'mysql' ),
				),
				array( 'id' => $membership_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Cancel auto-renewal, let it expire naturally.
			$result = $wpdb->update(
				$this->table_name,
				array(
					'auto_renew'   => 0,
					'cancelled_at' => current_time( 'mysql' ),
				),
				array( 'id' => $membership_id ),
				array( '%d', '%s' ),
				array( '%d' )
			);
		}

		if ( false === $result ) {
			return new \WP_Error( 'cancel_failed', __( 'Failed to cancel membership.', 'bkx-fitness-sports' ) );
		}

		/**
		 * Fires after a membership is cancelled.
		 *
		 * @param int  $membership_id Membership ID.
		 * @param bool $immediate     Whether cancelled immediately.
		 */
		do_action( 'bkx_fitness_membership_cancelled', $membership_id, $immediate );

		return true;
	}

	/**
	 * Decrement class count for user.
	 *
	 * @since 1.0.0
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function use_class( int $user_id ): bool {
		$membership = $this->get_user_membership( $user_id );

		if ( ! $membership ) {
			return false;
		}

		// Unlimited classes.
		if ( -1 === $membership['classes_remaining'] ) {
			return true;
		}

		if ( $membership['classes_remaining'] <= 0 ) {
			return false;
		}

		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table_name}
				SET classes_remaining = classes_remaining - 1
				WHERE id = %d AND classes_remaining > 0",
				$membership['id']
			)
		);

		return $result > 0;
	}

	/**
	 * Check expiring memberships and send notifications.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function check_expiring_memberships(): void {
		global $wpdb;

		// Get memberships expiring in 7 days.
		$expiring = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT m.*, u.user_email, u.display_name, p.post_title as plan_name
				FROM {$this->table_name} m
				INNER JOIN {$wpdb->users} u ON m.user_id = u.ID
				INNER JOIN {$wpdb->posts} p ON m.plan_id = p.ID
				WHERE m.status = 'active'
				AND m.end_date = %s
				AND m.expiry_notified = 0",
				date( 'Y-m-d', strtotime( '+7 days' ) )
			),
			ARRAY_A
		);

		foreach ( $expiring ?: array() as $membership ) {
			$this->send_expiry_notification( $membership );

			$wpdb->update(
				$this->table_name,
				array( 'expiry_notified' => 1 ),
				array( 'id' => $membership['id'] ),
				array( '%d' ),
				array( '%d' )
			);
		}

		// Expire memberships that have passed end date.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table_name}
				SET status = 'expired'
				WHERE status = 'active' AND end_date < %s",
				current_time( 'Y-m-d' )
			)
		);
	}

	/**
	 * Send expiry notification.
	 *
	 * @since 1.0.0
	 * @param array $membership Membership data.
	 * @return void
	 */
	private function send_expiry_notification( array $membership ): void {
		$subject = __( 'Your membership is expiring soon', 'bkx-fitness-sports' );

		$message = sprintf(
			/* translators: 1: user name, 2: plan name, 3: expiry date */
			__( "Hi %1\$s,\n\nYour %2\$s membership will expire on %3\$s.\n\nTo continue enjoying our services, please renew your membership.\n\nThank you!", 'bkx-fitness-sports' ),
			$membership['display_name'],
			$membership['plan_name'],
			date_i18n( get_option( 'date_format' ), strtotime( $membership['end_date'] ) )
		);

		wp_mail( $membership['user_email'], $subject, $message );
	}

	/**
	 * Render membership plans shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render_plans( array $atts = array() ): string {
		$atts = shortcode_atts( array(
			'columns' => 3,
		), $atts );

		$plans = $this->get_plans();

		ob_start();
		?>
		<div class="bkx-membership-plans bkx-plan-columns-<?php echo esc_attr( $atts['columns'] ); ?>">
			<?php foreach ( $plans as $plan ) : ?>
				<div class="bkx-plan-card <?php echo $plan['is_featured'] ? 'bkx-plan-featured' : ''; ?>">
					<?php if ( $plan['is_featured'] ) : ?>
						<div class="bkx-plan-badge"><?php esc_html_e( 'Most Popular', 'bkx-fitness-sports' ); ?></div>
					<?php endif; ?>

					<div class="bkx-plan-header">
						<h3 class="bkx-plan-name"><?php echo esc_html( $plan['name'] ); ?></h3>
						<div class="bkx-plan-price">
							<span class="bkx-price-amount"><?php echo wp_kses_post( wc_price( $plan['price'] ) ); ?></span>
							<span class="bkx-price-period">/<?php echo esc_html( $plan['billing_period'] ); ?></span>
						</div>
					</div>

					<div class="bkx-plan-features">
						<ul>
							<li>
								<?php
								if ( 'unlimited' === $plan['classes_per_month'] ) {
									esc_html_e( 'Unlimited classes', 'bkx-fitness-sports' );
								} else {
									printf(
										/* translators: %d: number of classes */
										esc_html__( '%d classes per month', 'bkx-fitness-sports' ),
										$plan['classes_per_month']
									);
								}
								?>
							</li>

							<?php if ( $plan['includes_equipment'] ) : ?>
								<li><?php esc_html_e( 'Equipment access', 'bkx-fitness-sports' ); ?></li>
							<?php endif; ?>

							<?php if ( $plan['includes_personal_training'] && $plan['pt_sessions'] > 0 ) : ?>
								<li>
									<?php
									printf(
										/* translators: %d: number of PT sessions */
										esc_html__( '%d personal training sessions', 'bkx-fitness-sports' ),
										$plan['pt_sessions']
									);
									?>
								</li>
							<?php endif; ?>

							<?php foreach ( $plan['features'] as $feature ) : ?>
								<li><?php echo esc_html( $feature ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>

					<div class="bkx-plan-footer">
						<a href="<?php echo esc_url( add_query_arg( 'plan', $plan['id'], get_permalink( get_option( 'bkx_checkout_page' ) ) ) ); ?>" class="bkx-select-plan-btn">
							<?php esc_html_e( 'Select Plan', 'bkx-fitness-sports' ); ?>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
