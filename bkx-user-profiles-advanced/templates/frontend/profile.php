<?php
/**
 * Customer Profile Template
 *
 * @package BookingX\UserProfilesAdvanced
 * @since   1.0.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$user_id         = get_current_user_id();
$user            = wp_get_current_user();
$profile_service = $this->addon->get_profile_service();
$loyalty_service = $this->addon->get_loyalty_service();

$profile = $profile_service->get_profile( $user_id );
$stats   = $profile_service->get_stats( $user_id );
$balance = $loyalty_service->get_balance( $user_id );
?>

<div class="bkx-customer-profile">
	<div class="bkx-profile-header">
		<div class="bkx-profile-avatar">
			<?php echo get_avatar( $user_id, 100 ); ?>
		</div>
		<div class="bkx-profile-info">
			<h2><?php echo esc_html( $user->display_name ); ?></h2>
			<p class="bkx-member-since">
				<?php
				printf(
					/* translators: %s: membership date */
					esc_html__( 'Member since %s', 'bkx-user-profiles-advanced' ),
					esc_html( date_i18n( get_option( 'date_format' ), strtotime( $stats['member_since'] ) ) )
				);
				?>
			</p>
		</div>
	</div>

	<div class="bkx-profile-stats">
		<div class="bkx-stat-box">
			<span class="bkx-stat-value"><?php echo esc_html( $stats['total_bookings'] ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Bookings', 'bkx-user-profiles-advanced' ); ?></span>
		</div>
		<div class="bkx-stat-box">
			<span class="bkx-stat-value"><?php echo esc_html( number_format( $stats['total_spent'], 2 ) ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Total Spent', 'bkx-user-profiles-advanced' ); ?></span>
		</div>
		<div class="bkx-stat-box">
			<span class="bkx-stat-value"><?php echo esc_html( $balance['available_points'] ); ?></span>
			<span class="bkx-stat-label"><?php esc_html_e( 'Points', 'bkx-user-profiles-advanced' ); ?></span>
		</div>
	</div>

	<?php if ( $this->addon->get_setting( 'allow_profile_edit', true ) ) : ?>
		<div class="bkx-profile-section">
			<h3><?php esc_html_e( 'Profile Information', 'bkx-user-profiles-advanced' ); ?></h3>
			<form id="bkx-profile-form" class="bkx-profile-form">
				<div class="bkx-form-row">
					<label for="bkx-email"><?php esc_html_e( 'Email', 'bkx-user-profiles-advanced' ); ?></label>
					<input type="email" id="bkx-email" value="<?php echo esc_attr( $user->user_email ); ?>" readonly />
				</div>
				<div class="bkx-form-row">
					<label for="bkx-phone"><?php esc_html_e( 'Phone', 'bkx-user-profiles-advanced' ); ?></label>
					<input type="tel" id="bkx-phone" name="phone" value="<?php echo esc_attr( $profile->phone ?? '' ); ?>" />
				</div>
				<div class="bkx-form-row">
					<label for="bkx-preferred-time"><?php esc_html_e( 'Preferred Booking Time', 'bkx-user-profiles-advanced' ); ?></label>
					<select id="bkx-preferred-time" name="preferred_time">
						<option value=""><?php esc_html_e( 'No preference', 'bkx-user-profiles-advanced' ); ?></option>
						<option value="morning" <?php selected( $profile->preferred_time ?? '', 'morning' ); ?>><?php esc_html_e( 'Morning (9am - 12pm)', 'bkx-user-profiles-advanced' ); ?></option>
						<option value="afternoon" <?php selected( $profile->preferred_time ?? '', 'afternoon' ); ?>><?php esc_html_e( 'Afternoon (12pm - 5pm)', 'bkx-user-profiles-advanced' ); ?></option>
						<option value="evening" <?php selected( $profile->preferred_time ?? '', 'evening' ); ?>><?php esc_html_e( 'Evening (5pm - 9pm)', 'bkx-user-profiles-advanced' ); ?></option>
					</select>
				</div>
				<div class="bkx-form-row">
					<label for="bkx-communication"><?php esc_html_e( 'Communication Preference', 'bkx-user-profiles-advanced' ); ?></label>
					<select id="bkx-communication" name="communication_preference">
						<option value="email" <?php selected( $profile->communication_preference ?? 'email', 'email' ); ?>><?php esc_html_e( 'Email', 'bkx-user-profiles-advanced' ); ?></option>
						<option value="sms" <?php selected( $profile->communication_preference ?? '', 'sms' ); ?>><?php esc_html_e( 'SMS', 'bkx-user-profiles-advanced' ); ?></option>
						<option value="both" <?php selected( $profile->communication_preference ?? '', 'both' ); ?>><?php esc_html_e( 'Both', 'bkx-user-profiles-advanced' ); ?></option>
					</select>
				</div>
				<div class="bkx-form-row">
					<label for="bkx-notes"><?php esc_html_e( 'Notes', 'bkx-user-profiles-advanced' ); ?></label>
					<textarea id="bkx-notes" name="notes" rows="3"><?php echo esc_textarea( $profile->notes ?? '' ); ?></textarea>
				</div>
				<div class="bkx-form-row">
					<button type="submit" class="bkx-button bkx-button-primary"><?php esc_html_e( 'Update Profile', 'bkx-user-profiles-advanced' ); ?></button>
					<span class="bkx-form-message"></span>
				</div>
			</form>
		</div>
	<?php endif; ?>

	<?php if ( $this->addon->get_setting( 'enable_loyalty', true ) ) : ?>
		<div class="bkx-profile-section">
			<h3><?php esc_html_e( 'Referral Program', 'bkx-user-profiles-advanced' ); ?></h3>
			<p><?php esc_html_e( 'Share your referral code and earn points when friends book!', 'bkx-user-profiles-advanced' ); ?></p>
			<div class="bkx-referral-code">
				<code><?php echo esc_html( $profile_service->get_referral_code( $user_id ) ); ?></code>
				<button type="button" class="bkx-copy-code" data-code="<?php echo esc_attr( $profile_service->get_referral_code( $user_id ) ); ?>">
					<?php esc_html_e( 'Copy', 'bkx-user-profiles-advanced' ); ?>
				</button>
			</div>
		</div>
	<?php endif; ?>
</div>
