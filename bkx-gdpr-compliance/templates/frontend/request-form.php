<?php
/**
 * Privacy request form template.
 *
 * @package BookingX\GdprCompliance
 */

defined( 'ABSPATH' ) || exit;

$types = explode( ',', $atts['types'] );
$types = array_map( 'trim', $types );

$type_labels = array(
	'export'        => __( 'Export my data', 'bkx-gdpr-compliance' ),
	'erasure'       => __( 'Delete my data', 'bkx-gdpr-compliance' ),
	'access'        => __( 'Access my data', 'bkx-gdpr-compliance' ),
	'rectification' => __( 'Correct my data', 'bkx-gdpr-compliance' ),
	'restriction'   => __( 'Restrict processing', 'bkx-gdpr-compliance' ),
	'portability'   => __( 'Data portability', 'bkx-gdpr-compliance' ),
);
?>

<div class="bkx-gdpr-request-form">
	<h3><?php esc_html_e( 'Submit a Privacy Request', 'bkx-gdpr-compliance' ); ?></h3>
	<p><?php esc_html_e( 'Use this form to exercise your privacy rights. A verification email will be sent to confirm your request.', 'bkx-gdpr-compliance' ); ?></p>

	<form id="bkx-gdpr-request-form">
		<div class="bkx-gdpr-form-field">
			<label for="bkx-gdpr-request-email"><?php esc_html_e( 'Email Address', 'bkx-gdpr-compliance' ); ?> <span class="required">*</span></label>
			<input type="email" id="bkx-gdpr-request-email" name="email" required
				   value="<?php echo is_user_logged_in() ? esc_attr( wp_get_current_user()->user_email ) : ''; ?>">
		</div>

		<div class="bkx-gdpr-form-field">
			<label for="bkx-gdpr-request-type"><?php esc_html_e( 'Request Type', 'bkx-gdpr-compliance' ); ?> <span class="required">*</span></label>
			<select id="bkx-gdpr-request-type" name="request_type" required>
				<option value=""><?php esc_html_e( 'Select request type...', 'bkx-gdpr-compliance' ); ?></option>
				<?php foreach ( $types as $type ) : ?>
					<?php if ( isset( $type_labels[ $type ] ) ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type_labels[ $type ] ); ?></option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="bkx-gdpr-form-field">
			<button type="submit" class="bkx-gdpr-submit-btn">
				<?php esc_html_e( 'Submit Request', 'bkx-gdpr-compliance' ); ?>
			</button>
		</div>

		<div class="bkx-gdpr-form-message" style="display: none;"></div>
	</form>
</div>

<style>
.bkx-gdpr-request-form {
	max-width: 500px;
	padding: 20px;
	background: #f9f9f9;
	border-radius: 8px;
}
.bkx-gdpr-form-field {
	margin-bottom: 15px;
}
.bkx-gdpr-form-field label {
	display: block;
	margin-bottom: 5px;
	font-weight: 600;
}
.bkx-gdpr-form-field input,
.bkx-gdpr-form-field select {
	width: 100%;
	padding: 10px;
	border: 1px solid #ddd;
	border-radius: 4px;
}
.bkx-gdpr-submit-btn {
	background: #0073aa;
	color: #fff;
	border: none;
	padding: 12px 24px;
	border-radius: 4px;
	cursor: pointer;
	font-size: 16px;
}
.bkx-gdpr-submit-btn:hover {
	background: #005a87;
}
.bkx-gdpr-form-message {
	margin-top: 15px;
	padding: 10px;
	border-radius: 4px;
}
.bkx-gdpr-form-message.success {
	background: #d4edda;
	color: #155724;
}
.bkx-gdpr-form-message.error {
	background: #f8d7da;
	color: #721c24;
}
.required {
	color: #d63638;
}
</style>
