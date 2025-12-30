<?php
/**
 * CCPA Do Not Sell opt-out template.
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;

$settings    = get_option( 'bkx_gdpr_compliance_settings', array() );
$user_id     = get_current_user_id();
$opted_out   = false;
$opted_date  = '';

// Check if user has already opted out.
if ( $user_id ) {
	$opt_out_status = get_user_meta( $user_id, 'bkx_ccpa_do_not_sell', true );
	$opted_out      = 'yes' === $opt_out_status;
	$opted_date     = get_user_meta( $user_id, 'bkx_ccpa_opt_out_date', true );
} else {
	// Check cookie for non-logged in users.
	if ( isset( $_COOKIE['bkx_ccpa_opt_out'] ) && 'yes' === sanitize_text_field( wp_unslash( $_COOKIE['bkx_ccpa_opt_out'] ) ) ) {
		$opted_out = true;
	}
}
?>

<div class="bkx-ccpa-opt-out">
	<h2><?php esc_html_e( 'Do Not Sell or Share My Personal Information', 'bkx-gdpr-compliance' ); ?></h2>

	<div class="bkx-ccpa-notice">
		<p>
			<?php esc_html_e( 'Under the California Consumer Privacy Act (CCPA) and California Privacy Rights Act (CPRA), California residents have the right to opt out of the sale or sharing of their personal information.', 'bkx-gdpr-compliance' ); ?>
		</p>
	</div>

	<div class="bkx-ccpa-status-box <?php echo $opted_out ? 'bkx-ccpa-opted-out' : 'bkx-ccpa-not-opted-out'; ?>">
		<?php if ( $opted_out ) : ?>
			<div class="bkx-ccpa-status-icon">
				<span class="dashicons dashicons-shield-alt"></span>
			</div>
			<div class="bkx-ccpa-status-content">
				<h3><?php esc_html_e( 'You Have Opted Out', 'bkx-gdpr-compliance' ); ?></h3>
				<p>
					<?php esc_html_e( 'We will not sell or share your personal information with third parties for cross-context behavioral advertising purposes.', 'bkx-gdpr-compliance' ); ?>
				</p>
				<?php if ( $opted_date ) : ?>
					<p class="bkx-ccpa-date">
						<?php
						printf(
							/* translators: %s: date */
							esc_html__( 'Opt-out date: %s', 'bkx-gdpr-compliance' ),
							esc_html( wp_date( get_option( 'date_format' ), strtotime( $opted_date ) ) )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<div class="bkx-ccpa-status-icon">
				<span class="dashicons dashicons-info-outline"></span>
			</div>
			<div class="bkx-ccpa-status-content">
				<h3><?php esc_html_e( 'Opt Out of Sale/Sharing', 'bkx-gdpr-compliance' ); ?></h3>
				<p>
					<?php esc_html_e( 'Click the button below to opt out of the sale or sharing of your personal information.', 'bkx-gdpr-compliance' ); ?>
				</p>
			</div>
		<?php endif; ?>
	</div>

	<form id="bkx-ccpa-opt-out-form" class="bkx-ccpa-form">
		<?php wp_nonce_field( 'bkx_ccpa_opt_out', 'bkx_ccpa_nonce' ); ?>

		<?php if ( ! $user_id ) : ?>
			<div class="bkx-ccpa-email-field">
				<label for="bkx-ccpa-email"><?php esc_html_e( 'Email Address', 'bkx-gdpr-compliance' ); ?> <span class="required">*</span></label>
				<input type="email" id="bkx-ccpa-email" name="email" required placeholder="<?php esc_attr_e( 'your@email.com', 'bkx-gdpr-compliance' ); ?>">
				<p class="description"><?php esc_html_e( 'We need your email to process and verify your opt-out request.', 'bkx-gdpr-compliance' ); ?></p>
			</div>
		<?php endif; ?>

		<div class="bkx-ccpa-actions">
			<?php if ( $opted_out ) : ?>
				<button type="button" id="bkx-ccpa-opt-in" class="bkx-ccpa-btn bkx-ccpa-btn-secondary">
					<?php esc_html_e( 'Opt Back In', 'bkx-gdpr-compliance' ); ?>
				</button>
			<?php else : ?>
				<button type="submit" id="bkx-ccpa-submit" class="bkx-ccpa-btn bkx-ccpa-btn-primary">
					<?php esc_html_e( 'Opt Out of Sale/Sharing', 'bkx-gdpr-compliance' ); ?>
				</button>
			<?php endif; ?>
		</div>

		<div class="bkx-ccpa-message" style="display: none;"></div>
	</form>

	<div class="bkx-ccpa-info">
		<h3><?php esc_html_e( 'What This Means', 'bkx-gdpr-compliance' ); ?></h3>
		<div class="bkx-ccpa-info-grid">
			<div class="bkx-ccpa-info-item">
				<h4><?php esc_html_e( 'What We Consider "Sale" or "Sharing"', 'bkx-gdpr-compliance' ); ?></h4>
				<ul>
					<li><?php esc_html_e( 'Sharing personal data with advertising networks', 'bkx-gdpr-compliance' ); ?></li>
					<li><?php esc_html_e( 'Cross-context behavioral advertising', 'bkx-gdpr-compliance' ); ?></li>
					<li><?php esc_html_e( 'Selling data to data brokers', 'bkx-gdpr-compliance' ); ?></li>
				</ul>
			</div>
			<div class="bkx-ccpa-info-item">
				<h4><?php esc_html_e( 'What Is NOT Affected', 'bkx-gdpr-compliance' ); ?></h4>
				<ul>
					<li><?php esc_html_e( 'Service providers who help us operate', 'bkx-gdpr-compliance' ); ?></li>
					<li><?php esc_html_e( 'Payment processing', 'bkx-gdpr-compliance' ); ?></li>
					<li><?php esc_html_e( 'Legal requirements', 'bkx-gdpr-compliance' ); ?></li>
					<li><?php esc_html_e( 'Your booking confirmations', 'bkx-gdpr-compliance' ); ?></li>
				</ul>
			</div>
		</div>
	</div>

	<div class="bkx-ccpa-additional-rights">
		<h3><?php esc_html_e( 'Your Additional CCPA Rights', 'bkx-gdpr-compliance' ); ?></h3>
		<ul>
			<li>
				<strong><?php esc_html_e( 'Right to Know', 'bkx-gdpr-compliance' ); ?></strong>
				<?php esc_html_e( '- Request what personal information we collect, use, and disclose', 'bkx-gdpr-compliance' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Right to Delete', 'bkx-gdpr-compliance' ); ?></strong>
				<?php esc_html_e( '- Request deletion of your personal information', 'bkx-gdpr-compliance' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Right to Correct', 'bkx-gdpr-compliance' ); ?></strong>
				<?php esc_html_e( '- Request correction of inaccurate personal information', 'bkx-gdpr-compliance' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Right to Limit Use', 'bkx-gdpr-compliance' ); ?></strong>
				<?php esc_html_e( '- Limit the use of sensitive personal information', 'bkx-gdpr-compliance' ); ?>
			</li>
			<li>
				<strong><?php esc_html_e( 'Right to Non-Discrimination', 'bkx-gdpr-compliance' ); ?></strong>
				<?php esc_html_e( '- We will not discriminate against you for exercising your rights', 'bkx-gdpr-compliance' ); ?>
			</li>
		</ul>
	</div>

	<div class="bkx-ccpa-contact">
		<p>
			<?php
			$dpo_email = isset( $settings['dpo_email'] ) ? $settings['dpo_email'] : get_option( 'admin_email' );
			printf(
				/* translators: %s: email address */
				esc_html__( 'For questions about your privacy rights or to submit a request, contact us at %s', 'bkx-gdpr-compliance' ),
				'<a href="mailto:' . esc_attr( $dpo_email ) . '">' . esc_html( $dpo_email ) . '</a>'
			);
			?>
		</p>
	</div>
</div>

<style>
.bkx-ccpa-opt-out {
	max-width: 700px;
	margin: 0 auto;
	padding: 20px;
}
.bkx-ccpa-notice {
	background: #e7f3ff;
	border-left: 4px solid #0073aa;
	padding: 15px 20px;
	margin-bottom: 25px;
	border-radius: 0 4px 4px 0;
}
.bkx-ccpa-notice p {
	margin: 0;
	color: #333;
	line-height: 1.6;
}
.bkx-ccpa-status-box {
	display: flex;
	gap: 20px;
	padding: 25px;
	border-radius: 8px;
	margin-bottom: 25px;
}
.bkx-ccpa-opted-out {
	background: #d4edda;
	border: 1px solid #c3e6cb;
}
.bkx-ccpa-not-opted-out {
	background: #fff3cd;
	border: 1px solid #ffeeba;
}
.bkx-ccpa-status-icon .dashicons {
	font-size: 48px;
	width: 48px;
	height: 48px;
}
.bkx-ccpa-opted-out .bkx-ccpa-status-icon .dashicons {
	color: #28a745;
}
.bkx-ccpa-not-opted-out .bkx-ccpa-status-icon .dashicons {
	color: #856404;
}
.bkx-ccpa-status-content h3 {
	margin: 0 0 10px;
}
.bkx-ccpa-status-content p {
	margin: 0;
	color: #333;
}
.bkx-ccpa-date {
	font-size: 13px;
	color: #666;
	margin-top: 10px !important;
}
.bkx-ccpa-email-field {
	margin-bottom: 20px;
}
.bkx-ccpa-email-field label {
	display: block;
	font-weight: 600;
	margin-bottom: 5px;
}
.bkx-ccpa-email-field input {
	width: 100%;
	max-width: 400px;
	padding: 10px;
	border: 1px solid #ddd;
	border-radius: 4px;
	font-size: 14px;
}
.bkx-ccpa-email-field .description {
	color: #666;
	font-size: 13px;
	margin-top: 5px;
}
.bkx-ccpa-actions {
	margin-bottom: 20px;
}
.bkx-ccpa-btn {
	padding: 12px 30px;
	border-radius: 4px;
	font-size: 16px;
	font-weight: 500;
	cursor: pointer;
	border: none;
	transition: background 0.2s;
}
.bkx-ccpa-btn-primary {
	background: #0073aa;
	color: #fff;
}
.bkx-ccpa-btn-primary:hover {
	background: #005a87;
}
.bkx-ccpa-btn-secondary {
	background: #f0f0f0;
	color: #333;
}
.bkx-ccpa-btn-secondary:hover {
	background: #e0e0e0;
}
.bkx-ccpa-message {
	padding: 12px;
	border-radius: 4px;
	margin-top: 15px;
}
.bkx-ccpa-message.success {
	background: #d4edda;
	color: #155724;
}
.bkx-ccpa-message.error {
	background: #f8d7da;
	color: #721c24;
}
.bkx-ccpa-info {
	background: #f9f9f9;
	padding: 25px;
	border-radius: 8px;
	margin-bottom: 25px;
}
.bkx-ccpa-info h3 {
	margin-top: 0;
}
.bkx-ccpa-info-grid {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20px;
}
.bkx-ccpa-info-item h4 {
	margin: 0 0 10px;
	font-size: 14px;
}
.bkx-ccpa-info-item ul {
	margin: 0;
	padding-left: 20px;
}
.bkx-ccpa-info-item li {
	margin-bottom: 5px;
	font-size: 14px;
	color: #555;
}
.bkx-ccpa-additional-rights {
	background: #fff;
	border: 1px solid #e0e0e0;
	padding: 25px;
	border-radius: 8px;
	margin-bottom: 25px;
}
.bkx-ccpa-additional-rights h3 {
	margin-top: 0;
}
.bkx-ccpa-additional-rights ul {
	list-style: none;
	padding: 0;
	margin: 0;
}
.bkx-ccpa-additional-rights li {
	padding: 10px 0;
	border-bottom: 1px solid #f0f0f0;
}
.bkx-ccpa-additional-rights li:last-child {
	border-bottom: none;
}
.bkx-ccpa-contact {
	text-align: center;
	padding: 20px;
	background: #f9f9f9;
	border-radius: 8px;
}
.bkx-ccpa-contact a {
	color: #0073aa;
}
.required {
	color: #d63638;
}
@media screen and (max-width: 600px) {
	.bkx-ccpa-info-grid {
		grid-template-columns: 1fr;
	}
	.bkx-ccpa-status-box {
		flex-direction: column;
		text-align: center;
	}
}
</style>
