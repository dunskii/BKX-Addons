<?php
/**
 * Coupon Add/Edit Form Template
 *
 * @package BookingX\CouponCodes
 * @since   1.0.0
 *
 * @var int|null    $coupon_id     Coupon ID (if editing).
 * @var object|null $coupon        Coupon object (if editing).
 * @var bool        $is_edit       Whether this is an edit form.
 * @var string|null $error_message Error message (if any).
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_title = $is_edit ? __( 'Edit Coupon', 'bkx-coupon-codes' ) : __( 'Add New Coupon', 'bkx-coupon-codes' );

// Get services for restriction dropdown.
$services = get_posts(
	array(
		'post_type'   => 'bkx_base',
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
	)
);

// Get seats for restriction dropdown.
$seats = get_posts(
	array(
		'post_type'   => 'bkx_seat',
		'numberposts' => -1,
		'orderby'     => 'title',
		'order'       => 'ASC',
	)
);

// Get user roles.
$wp_roles   = wp_roles();
$user_roles = $wp_roles->get_names();

// Default values for new coupon.
$defaults = array(
	'code'               => '',
	'description'        => '',
	'discount_type'      => 'percentage',
	'discount_value'     => '',
	'min_booking_amount' => '',
	'max_discount'       => '',
	'usage_limit'        => '',
	'per_user_limit'     => '',
	'start_date'         => '',
	'end_date'           => '',
	'is_active'          => 1,
	'services'           => '',
	'seats'              => '',
	'excluded_services'  => '',
	'user_roles'         => '',
	'first_booking_only' => 0,
);

// Merge with existing coupon data.
$data = $is_edit ? (array) $coupon : $defaults;

// Decode JSON fields.
$selected_services          = ! empty( $data['services'] ) ? json_decode( $data['services'], true ) : array();
$selected_seats             = ! empty( $data['seats'] ) ? json_decode( $data['seats'], true ) : array();
$selected_excluded_services = ! empty( $data['excluded_services'] ) ? json_decode( $data['excluded_services'], true ) : array();
$selected_user_roles        = ! empty( $data['user_roles'] ) ? json_decode( $data['user_roles'], true ) : array();
?>

<div class="wrap">
	<h1><?php echo esc_html( $page_title ); ?></h1>

	<?php if ( ! empty( $error_message ) ) : ?>
		<div class="notice notice-error">
			<p><?php echo esc_html( $error_message ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post" id="bkx-coupon-form" class="bkx-coupon-form">
		<?php wp_nonce_field( 'bkx_save_coupon', 'bkx_coupon_nonce' ); ?>

		<!-- General Settings -->
		<div class="bkx-form-section">
			<h3><?php esc_html_e( 'General', 'bkx-coupon-codes' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="bkx-coupon-code"><?php esc_html_e( 'Coupon Code', 'bkx-coupon-codes' ); ?> <span class="required">*</span></label>
					</th>
					<td>
						<div class="bkx-code-field">
							<input type="text" id="bkx-coupon-code" name="code" value="<?php echo esc_attr( $data['code'] ); ?>" class="regular-text" required>
							<button type="button" id="bkx-generate-code" class="button"><?php esc_html_e( 'Generate', 'bkx-coupon-codes' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'The code customers will enter to apply this discount.', 'bkx-coupon-codes' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx-description"><?php esc_html_e( 'Description', 'bkx-coupon-codes' ); ?></label>
					</th>
					<td>
						<textarea id="bkx-description" name="description" rows="3" class="large-text"><?php echo esc_textarea( $data['description'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Internal note about this coupon (not shown to customers).', 'bkx-coupon-codes' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Status', 'bkx-coupon-codes' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="is_active" value="1" <?php checked( $data['is_active'], 1 ); ?>>
							<?php esc_html_e( 'Active', 'bkx-coupon-codes' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Only active coupons can be used by customers.', 'bkx-coupon-codes' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Discount Settings -->
		<div class="bkx-form-section">
			<h3><?php esc_html_e( 'Discount', 'bkx-coupon-codes' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="bkx-discount-type"><?php esc_html_e( 'Discount Type', 'bkx-coupon-codes' ); ?></label>
					</th>
					<td>
						<select id="bkx-discount-type" name="discount_type">
							<option value="percentage" <?php selected( $data['discount_type'], 'percentage' ); ?>>
								<?php esc_html_e( 'Percentage discount', 'bkx-coupon-codes' ); ?>
							</option>
							<option value="fixed" <?php selected( $data['discount_type'], 'fixed' ); ?>>
								<?php esc_html_e( 'Fixed amount discount', 'bkx-coupon-codes' ); ?>
							</option>
							<option value="free_service" <?php selected( $data['discount_type'], 'free_service' ); ?>>
								<?php esc_html_e( 'Free service', 'bkx-coupon-codes' ); ?>
							</option>
							<option value="free_extra" <?php selected( $data['discount_type'], 'free_extra' ); ?>>
								<?php esc_html_e( 'Free add-on/extra', 'bkx-coupon-codes' ); ?>
							</option>
						</select>
					</td>
				</tr>
				<tr id="bkx-discount-value-row">
					<th scope="row">
						<label for="bkx-discount-value"><?php esc_html_e( 'Discount Value', 'bkx-coupon-codes' ); ?></label>
					</th>
					<td>
						<input type="number" id="bkx-discount-value" name="discount_value" value="<?php echo esc_attr( $data['discount_value'] ); ?>" step="0.01" min="0" class="small-text">
						<span class="bkx-discount-suffix">%</span>
						<p class="description"><?php esc_html_e( 'Enter the discount amount.', 'bkx-coupon-codes' ); ?></p>
					</td>
				</tr>
				<tr id="bkx-max-discount-row">
					<th scope="row">
						<label for="bkx-max-discount"><?php esc_html_e( 'Maximum Discount', 'bkx-coupon-codes' ); ?></label>
					</th>
					<td>
						<input type="number" id="bkx-max-discount" name="max_discount" value="<?php echo esc_attr( $data['max_discount'] ); ?>" step="0.01" min="0" class="small-text">
						<p class="description"><?php esc_html_e( 'Maximum discount amount (for percentage discounts). Leave empty for no limit.', 'bkx-coupon-codes' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Usage Restrictions -->
		<div class="bkx-form-section">
			<h3><?php esc_html_e( 'Usage Restrictions', 'bkx-coupon-codes' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="bkx-min-booking-amount"><?php esc_html_e( 'Minimum Booking Amount', 'bkx-coupon-codes' ); ?></label>
					</th>
					<td>
						<input type="number" id="bkx-min-booking-amount" name="min_booking_amount" value="<?php echo esc_attr( $data['min_booking_amount'] ); ?>" step="0.01" min="0" class="small-text">
						<p class="description"><?php esc_html_e( 'Minimum booking total required to use this coupon.', 'bkx-coupon-codes' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx-services"><?php esc_html_e( 'Allowed Services', 'bkx-coupon-codes' ); ?></label>
					</th>
					<td>
						<select id="bkx-services" name="services[]" multiple class="bkx-multi-select">
							<?php foreach ( $services as $service ) : ?>
								<option value="<?php echo esc_attr( $service->ID ); ?>" <?php selected( in_array( $service->ID, $selected_services, true ) ); ?>>
									<?php echo esc_html( $service->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Leave empty to allow all services.', 'bkx-coupon-codes' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx-excluded-services"><?php esc_html_e( 'Excluded Services', 'bkx-coupon-codes' ); ?></label>
					</th>
					<td>
						<select id="bkx-excluded-services" name="excluded_services[]" multiple class="bkx-multi-select">
							<?php foreach ( $services as $service ) : ?>
								<option value="<?php echo esc_attr( $service->ID ); ?>" <?php selected( in_array( $service->ID, $selected_excluded_services, true ) ); ?>>
									<?php echo esc_html( $service->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Services that this coupon cannot be used with.', 'bkx-coupon-codes' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx-seats"><?php esc_html_e( 'Allowed Resources', 'bkx-coupon-codes' ); ?></label>
					</th>
					<td>
						<select id="bkx-seats" name="seats[]" multiple class="bkx-multi-select">
							<?php foreach ( $seats as $seat ) : ?>
								<option value="<?php echo esc_attr( $seat->ID ); ?>" <?php selected( in_array( $seat->ID, $selected_seats, true ) ); ?>>
									<?php echo esc_html( $seat->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Leave empty to allow all resources/staff.', 'bkx-coupon-codes' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx-user-roles"><?php esc_html_e( 'User Roles', 'bkx-coupon-codes' ); ?></label>
					</th>
					<td>
						<select id="bkx-user-roles" name="user_roles[]" multiple class="bkx-multi-select">
							<?php foreach ( $user_roles as $role_key => $role_name ) : ?>
								<option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( in_array( $role_key, $selected_user_roles, true ) ); ?>>
									<?php echo esc_html( $role_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Restrict to specific user roles. Leave empty for all users.', 'bkx-coupon-codes' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'First Booking Only', 'bkx-coupon-codes' ); ?></label>
					</th>
					<td>
						<label>
							<input type="checkbox" name="first_booking_only" value="1" <?php checked( $data['first_booking_only'], 1 ); ?>>
							<?php esc_html_e( 'Only allow for first-time customers', 'bkx-coupon-codes' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>

		<!-- Usage Limits -->
		<div class="bkx-form-section">
			<h3><?php esc_html_e( 'Usage Limits', 'bkx-coupon-codes' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="bkx-usage-limit"><?php esc_html_e( 'Usage Limit', 'bkx-coupon-codes' ); ?></label>
					</th>
					<td>
						<input type="number" id="bkx-usage-limit" name="usage_limit" value="<?php echo esc_attr( $data['usage_limit'] ); ?>" min="0" class="small-text">
						<p class="description"><?php esc_html_e( 'Maximum times this coupon can be used (0 = unlimited).', 'bkx-coupon-codes' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="bkx-per-user-limit"><?php esc_html_e( 'Per-User Limit', 'bkx-coupon-codes' ); ?></label>
					</th>
					<td>
						<input type="number" id="bkx-per-user-limit" name="per_user_limit" value="<?php echo esc_attr( $data['per_user_limit'] ); ?>" min="0" class="small-text">
						<p class="description"><?php esc_html_e( 'Maximum times a single user can use this coupon (0 = unlimited).', 'bkx-coupon-codes' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Date Range -->
		<div class="bkx-form-section">
			<h3><?php esc_html_e( 'Validity Period', 'bkx-coupon-codes' ); ?></h3>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Valid Dates', 'bkx-coupon-codes' ); ?></label>
					</th>
					<td>
						<div class="bkx-date-range">
							<input type="text" id="bkx-start-date" name="start_date" value="<?php echo esc_attr( $data['start_date'] ? wp_date( 'Y-m-d', strtotime( $data['start_date'] ) ) : '' ); ?>" placeholder="<?php esc_attr_e( 'Start date', 'bkx-coupon-codes' ); ?>">
							<span><?php esc_html_e( 'to', 'bkx-coupon-codes' ); ?></span>
							<input type="text" id="bkx-end-date" name="end_date" value="<?php echo esc_attr( $data['end_date'] ? wp_date( 'Y-m-d', strtotime( $data['end_date'] ) ) : '' ); ?>" placeholder="<?php esc_attr_e( 'End date', 'bkx-coupon-codes' ); ?>">
						</div>
						<p class="description"><?php esc_html_e( 'Leave empty for no date restrictions.', 'bkx-coupon-codes' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<?php if ( $is_edit ) : ?>
			<!-- Usage Statistics (for existing coupons) -->
			<div class="bkx-form-section">
				<h3><?php esc_html_e( 'Usage Statistics', 'bkx-coupon-codes' ); ?></h3>

				<?php
				$coupon_service = $this->addon->get_coupon_service();
				$stats          = $coupon_service->get_usage_stats( $coupon_id );
				?>

				<div class="bkx-usage-stats">
					<div class="bkx-stat-item">
						<div class="bkx-stat-value"><?php echo esc_html( $stats['total_uses'] ); ?></div>
						<div class="bkx-stat-label"><?php esc_html_e( 'Total Uses', 'bkx-coupon-codes' ); ?></div>
					</div>
					<div class="bkx-stat-item">
						<div class="bkx-stat-value"><?php echo esc_html( wc_price( $stats['total_discount'] ) ); ?></div>
						<div class="bkx-stat-label"><?php esc_html_e( 'Total Discounts', 'bkx-coupon-codes' ); ?></div>
					</div>
					<div class="bkx-stat-item">
						<div class="bkx-stat-value"><?php echo esc_html( $stats['unique_users'] ); ?></div>
						<div class="bkx-stat-label"><?php esc_html_e( 'Unique Users', 'bkx-coupon-codes' ); ?></div>
					</div>
				</div>
			</div>
		<?php endif; ?>

		<p class="submit">
			<input type="submit" name="bkx_save_coupon" class="button button-primary" value="<?php echo esc_attr( $is_edit ? __( 'Update Coupon', 'bkx-coupon-codes' ) : __( 'Create Coupon', 'bkx-coupon-codes' ) ); ?>">
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bkx_booking&page=bkx-coupons' ) ); ?>" class="button">
				<?php esc_html_e( 'Cancel', 'bkx-coupon-codes' ); ?>
			</a>
		</p>
	</form>
</div>
