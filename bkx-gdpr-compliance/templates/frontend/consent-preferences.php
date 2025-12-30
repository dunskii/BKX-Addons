<?php
/**
 * Consent preferences management template.
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;

$user_id  = get_current_user_id();
$settings = get_option( 'bkx_gdpr_compliance_settings', array() );

// Get user's current consents.
$consents = array();
if ( $user_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'bkx_consent_records';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$records = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT consent_type, status, created_at FROM %i WHERE user_id = %d AND status = 'granted' GROUP BY consent_type ORDER BY created_at DESC",
			$table,
			$user_id
		),
		ARRAY_A
	);

	foreach ( $records as $record ) {
		$consents[ $record['consent_type'] ] = $record;
	}
}

$consent_types = array(
	'privacy'     => array(
		'label'       => __( 'Privacy Policy', 'bkx-gdpr-compliance' ),
		'description' => __( 'I agree to the processing of my personal data as described in the Privacy Policy.', 'bkx-gdpr-compliance' ),
		'required'    => true,
	),
	'marketing'   => array(
		'label'       => __( 'Marketing Communications', 'bkx-gdpr-compliance' ),
		'description' => __( 'I agree to receive promotional emails, newsletters, and special offers.', 'bkx-gdpr-compliance' ),
		'required'    => false,
	),
	'third_party' => array(
		'label'       => __( 'Third Party Sharing', 'bkx-gdpr-compliance' ),
		'description' => __( 'I agree to share my data with trusted third-party service providers.', 'bkx-gdpr-compliance' ),
		'required'    => false,
	),
	'analytics'   => array(
		'label'       => __( 'Analytics & Improvement', 'bkx-gdpr-compliance' ),
		'description' => __( 'I agree to the use of analytics tools to improve services and user experience.', 'bkx-gdpr-compliance' ),
		'required'    => false,
	),
);
?>

<div class="bkx-consent-preferences">
	<h2><?php esc_html_e( 'Manage Your Consent Preferences', 'bkx-gdpr-compliance' ); ?></h2>

	<?php if ( ! $user_id ) : ?>
		<div class="bkx-consent-login-notice">
			<p>
				<?php
				printf(
					/* translators: %s: login link */
					esc_html__( 'Please %s to manage your consent preferences.', 'bkx-gdpr-compliance' ),
					'<a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'log in', 'bkx-gdpr-compliance' ) . '</a>'
				);
				?>
			</p>
		</div>
	<?php else : ?>
		<p class="bkx-consent-intro">
			<?php esc_html_e( 'Control how we use your personal data. You can update your preferences at any time. Some consents may be required for our services to function properly.', 'bkx-gdpr-compliance' ); ?>
		</p>

		<form id="bkx-consent-preferences-form" class="bkx-consent-form">
			<?php wp_nonce_field( 'bkx_consent_preferences', 'bkx_consent_nonce' ); ?>

			<?php foreach ( $consent_types as $type => $config ) : ?>
				<?php
				$is_granted = isset( $consents[ $type ] );
				$granted_date = $is_granted ? $consents[ $type ]['created_at'] : '';
				?>
				<div class="bkx-consent-item <?php echo $config['required'] ? 'bkx-consent-required' : ''; ?>">
					<div class="bkx-consent-header">
						<label class="bkx-consent-label">
							<input type="checkbox" name="consents[]" value="<?php echo esc_attr( $type ); ?>"
								<?php checked( $is_granted || $config['required'] ); ?>
								<?php disabled( $config['required'] ); ?>
								data-type="<?php echo esc_attr( $type ); ?>">
							<span class="bkx-consent-title"><?php echo esc_html( $config['label'] ); ?></span>
							<?php if ( $config['required'] ) : ?>
								<span class="bkx-consent-badge bkx-consent-badge-required"><?php esc_html_e( 'Required', 'bkx-gdpr-compliance' ); ?></span>
							<?php endif; ?>
						</label>
						<?php if ( $is_granted && ! $config['required'] ) : ?>
							<button type="button" class="bkx-consent-withdraw" data-type="<?php echo esc_attr( $type ); ?>">
								<?php esc_html_e( 'Withdraw', 'bkx-gdpr-compliance' ); ?>
							</button>
						<?php endif; ?>
					</div>
					<p class="bkx-consent-description"><?php echo esc_html( $config['description'] ); ?></p>
					<?php if ( $is_granted ) : ?>
						<p class="bkx-consent-status">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php
							printf(
								/* translators: %s: date */
								esc_html__( 'Consent granted on %s', 'bkx-gdpr-compliance' ),
								esc_html( wp_date( get_option( 'date_format' ), strtotime( $granted_date ) ) )
							);
							?>
						</p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<div class="bkx-consent-actions">
				<button type="submit" class="bkx-consent-save-btn">
					<?php esc_html_e( 'Save Preferences', 'bkx-gdpr-compliance' ); ?>
				</button>
			</div>

			<div class="bkx-consent-message" style="display: none;"></div>
		</form>

		<div class="bkx-consent-history">
			<h3><?php esc_html_e( 'Your Privacy Rights', 'bkx-gdpr-compliance' ); ?></h3>
			<p><?php esc_html_e( 'Under data protection laws, you have rights including:', 'bkx-gdpr-compliance' ); ?></p>
			<ul>
				<li><strong><?php esc_html_e( 'Right of Access', 'bkx-gdpr-compliance' ); ?></strong> - <?php esc_html_e( 'Request a copy of your personal data', 'bkx-gdpr-compliance' ); ?></li>
				<li><strong><?php esc_html_e( 'Right to Rectification', 'bkx-gdpr-compliance' ); ?></strong> - <?php esc_html_e( 'Request correction of inaccurate data', 'bkx-gdpr-compliance' ); ?></li>
				<li><strong><?php esc_html_e( 'Right to Erasure', 'bkx-gdpr-compliance' ); ?></strong> - <?php esc_html_e( 'Request deletion of your data', 'bkx-gdpr-compliance' ); ?></li>
				<li><strong><?php esc_html_e( 'Right to Restrict Processing', 'bkx-gdpr-compliance' ); ?></strong> - <?php esc_html_e( 'Request limitation of data processing', 'bkx-gdpr-compliance' ); ?></li>
				<li><strong><?php esc_html_e( 'Right to Data Portability', 'bkx-gdpr-compliance' ); ?></strong> - <?php esc_html_e( 'Request your data in a portable format', 'bkx-gdpr-compliance' ); ?></li>
			</ul>
			<p>
				<?php esc_html_e( 'To exercise any of these rights, please use our privacy request form or contact our Data Protection Officer.', 'bkx-gdpr-compliance' ); ?>
			</p>
		</div>
	<?php endif; ?>
</div>

<style>
.bkx-consent-preferences {
	max-width: 700px;
	margin: 0 auto;
	padding: 20px;
}
.bkx-consent-login-notice {
	padding: 20px;
	background: #fff3cd;
	border: 1px solid #ffc107;
	border-radius: 4px;
	text-align: center;
}
.bkx-consent-intro {
	color: #666;
	margin-bottom: 25px;
	line-height: 1.6;
}
.bkx-consent-item {
	background: #fff;
	border: 1px solid #e0e0e0;
	border-radius: 8px;
	padding: 20px;
	margin-bottom: 15px;
}
.bkx-consent-item.bkx-consent-required {
	border-left: 4px solid #0073aa;
}
.bkx-consent-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 10px;
}
.bkx-consent-label {
	display: flex;
	align-items: center;
	gap: 10px;
	cursor: pointer;
	font-size: 16px;
}
.bkx-consent-label input[type="checkbox"] {
	width: 18px;
	height: 18px;
}
.bkx-consent-label input:disabled {
	cursor: not-allowed;
}
.bkx-consent-title {
	font-weight: 600;
}
.bkx-consent-badge {
	padding: 2px 8px;
	border-radius: 3px;
	font-size: 11px;
	font-weight: 500;
}
.bkx-consent-badge-required {
	background: #0073aa;
	color: #fff;
}
.bkx-consent-withdraw {
	background: none;
	border: 1px solid #d63638;
	color: #d63638;
	padding: 5px 12px;
	border-radius: 3px;
	cursor: pointer;
	font-size: 12px;
}
.bkx-consent-withdraw:hover {
	background: #d63638;
	color: #fff;
}
.bkx-consent-description {
	color: #666;
	font-size: 14px;
	line-height: 1.5;
	margin: 0 0 10px 28px;
}
.bkx-consent-status {
	display: flex;
	align-items: center;
	gap: 5px;
	color: #46b450;
	font-size: 13px;
	margin: 0 0 0 28px;
}
.bkx-consent-status .dashicons {
	font-size: 16px;
	width: 16px;
	height: 16px;
}
.bkx-consent-actions {
	margin-top: 20px;
	text-align: right;
}
.bkx-consent-save-btn {
	background: #0073aa;
	color: #fff;
	border: none;
	padding: 12px 30px;
	border-radius: 4px;
	font-size: 16px;
	cursor: pointer;
	transition: background 0.2s;
}
.bkx-consent-save-btn:hover {
	background: #005a87;
}
.bkx-consent-save-btn:disabled {
	background: #ccc;
	cursor: not-allowed;
}
.bkx-consent-message {
	margin-top: 15px;
	padding: 12px;
	border-radius: 4px;
}
.bkx-consent-message.success {
	background: #d4edda;
	color: #155724;
	border: 1px solid #c3e6cb;
}
.bkx-consent-message.error {
	background: #f8d7da;
	color: #721c24;
	border: 1px solid #f5c6cb;
}
.bkx-consent-history {
	margin-top: 30px;
	padding: 25px;
	background: #f9f9f9;
	border-radius: 8px;
}
.bkx-consent-history h3 {
	margin-top: 0;
}
.bkx-consent-history ul {
	padding-left: 20px;
}
.bkx-consent-history li {
	margin-bottom: 8px;
	line-height: 1.5;
}
</style>
