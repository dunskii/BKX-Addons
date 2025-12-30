<?php
/**
 * Two-Factor Authentication service.
 *
 * @package BookingX\SecurityAudit
 */

namespace BookingX\SecurityAudit\Services;

defined( 'ABSPATH' ) || exit;

/**
 * TwoFactorAuth class.
 */
class TwoFactorAuth {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_login', array( $this, 'check_2fa_required' ), 5, 2 );
		add_action( 'login_form', array( $this, 'add_2fa_field' ) );
		add_filter( 'authenticate', array( $this, 'validate_2fa' ), 100, 3 );

		// User profile.
		add_action( 'show_user_profile', array( $this, 'show_2fa_settings' ) );
		add_action( 'edit_user_profile', array( $this, 'show_2fa_settings' ) );
		add_action( 'personal_options_update', array( $this, 'save_2fa_settings' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_2fa_settings' ) );

		// AJAX.
		add_action( 'wp_ajax_bkx_security_generate_2fa_secret', array( $this, 'ajax_generate_secret' ) );
		add_action( 'wp_ajax_bkx_security_verify_2fa_setup', array( $this, 'ajax_verify_setup' ) );
		add_action( 'wp_ajax_bkx_security_disable_2fa', array( $this, 'ajax_disable_2fa' ) );
	}

	/**
	 * Generate a new TOTP secret.
	 *
	 * @return string
	 */
	public function generate_secret() {
		$chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$secret = '';

		for ( $i = 0; $i < 16; $i++ ) {
			$secret .= $chars[ wp_rand( 0, 31 ) ];
		}

		return $secret;
	}

	/**
	 * Generate TOTP code from secret.
	 *
	 * @param string $secret    Base32 secret.
	 * @param int    $timestamp Unix timestamp.
	 * @return string
	 */
	public function generate_code( $secret, $timestamp = null ) {
		if ( null === $timestamp ) {
			$timestamp = time();
		}

		$time = floor( $timestamp / 30 );
		$key  = $this->base32_decode( $secret );

		$time_bytes = pack( 'N*', 0 ) . pack( 'N*', $time );
		$hash       = hash_hmac( 'sha1', $time_bytes, $key, true );

		$offset = ord( $hash[19] ) & 0xf;
		$code   = (
			( ( ord( $hash[ $offset ] ) & 0x7f ) << 24 ) |
			( ( ord( $hash[ $offset + 1 ] ) & 0xff ) << 16 ) |
			( ( ord( $hash[ $offset + 2 ] ) & 0xff ) << 8 ) |
			( ord( $hash[ $offset + 3 ] ) & 0xff )
		) % pow( 10, 6 );

		return str_pad( (string) $code, 6, '0', STR_PAD_LEFT );
	}

	/**
	 * Verify a TOTP code.
	 *
	 * @param string $secret User's secret.
	 * @param string $code   Code to verify.
	 * @param int    $window Time window (number of 30-second periods to check).
	 * @return bool
	 */
	public function verify_code( $secret, $code, $window = 1 ) {
		$timestamp = time();

		// Check current and adjacent time periods.
		for ( $i = -$window; $i <= $window; $i++ ) {
			$check_time = $timestamp + ( $i * 30 );
			if ( $this->generate_code( $secret, $check_time ) === $code ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Decode base32 string.
	 *
	 * @param string $input Base32 encoded string.
	 * @return string
	 */
	private function base32_decode( $input ) {
		$map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		$input = strtoupper( $input );
		$input = str_split( $input );
		$output = '';
		$buffer = 0;
		$bits_remaining = 0;

		foreach ( $input as $char ) {
			$val = strpos( $map, $char );
			if ( $val === false ) {
				continue;
			}

			$buffer = ( $buffer << 5 ) | $val;
			$bits_remaining += 5;

			if ( $bits_remaining >= 8 ) {
				$bits_remaining -= 8;
				$output .= chr( ( $buffer >> $bits_remaining ) & 0xff );
			}
		}

		return $output;
	}

	/**
	 * Generate QR code URL for authenticator apps.
	 *
	 * @param string $secret User's secret.
	 * @param string $email  User's email.
	 * @return string
	 */
	public function get_qr_code_url( $secret, $email ) {
		$issuer = rawurlencode( get_bloginfo( 'name' ) );
		$email  = rawurlencode( $email );

		$uri = sprintf(
			'otpauth://totp/%s:%s?secret=%s&issuer=%s',
			$issuer,
			$email,
			$secret,
			$issuer
		);

		// Use Google Charts API for QR code.
		return sprintf(
			'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=%s',
			rawurlencode( $uri )
		);
	}

	/**
	 * Check if 2FA is enabled for a user.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_enabled_for_user( $user_id ) {
		return (bool) get_user_meta( $user_id, 'bkx_2fa_enabled', true );
	}

	/**
	 * Get user's 2FA secret.
	 *
	 * @param int $user_id User ID.
	 * @return string|false
	 */
	public function get_user_secret( $user_id ) {
		return get_user_meta( $user_id, 'bkx_2fa_secret', true );
	}

	/**
	 * Show 2FA settings on user profile.
	 *
	 * @param \WP_User $user User object.
	 */
	public function show_2fa_settings( $user ) {
		$is_enabled = $this->is_enabled_for_user( $user->ID );
		$secret     = $this->get_user_secret( $user->ID );
		?>
		<h2><?php esc_html_e( 'Two-Factor Authentication', 'bkx-security-audit' ); ?></h2>
		<table class="form-table" id="bkx-2fa-settings">
			<tr>
				<th><?php esc_html_e( 'Status', 'bkx-security-audit' ); ?></th>
				<td>
					<?php if ( $is_enabled ) : ?>
						<span style="color: #46b450; font-weight: 600;">
							<span class="dashicons dashicons-shield-alt" style="color: #46b450;"></span>
							<?php esc_html_e( 'Enabled', 'bkx-security-audit' ); ?>
						</span>
						<p class="description">
							<?php esc_html_e( 'Two-factor authentication is active on your account.', 'bkx-security-audit' ); ?>
						</p>
						<p>
							<button type="button" class="button" id="bkx-2fa-disable">
								<?php esc_html_e( 'Disable 2FA', 'bkx-security-audit' ); ?>
							</button>
						</p>
					<?php else : ?>
						<span style="color: #dba617;">
							<span class="dashicons dashicons-warning" style="color: #dba617;"></span>
							<?php esc_html_e( 'Not Enabled', 'bkx-security-audit' ); ?>
						</span>
						<p class="description">
							<?php esc_html_e( 'Add an extra layer of security to your account.', 'bkx-security-audit' ); ?>
						</p>
						<p>
							<button type="button" class="button button-primary" id="bkx-2fa-enable">
								<?php esc_html_e( 'Enable 2FA', 'bkx-security-audit' ); ?>
							</button>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr id="bkx-2fa-setup" style="display: none;">
				<th><?php esc_html_e( 'Setup', 'bkx-security-audit' ); ?></th>
				<td>
					<div id="bkx-2fa-qr-container">
						<p><?php esc_html_e( 'Scan this QR code with your authenticator app:', 'bkx-security-audit' ); ?></p>
						<img id="bkx-2fa-qr" src="" alt="QR Code">
						<p class="description">
							<?php esc_html_e( 'Or enter this code manually:', 'bkx-security-audit' ); ?>
							<code id="bkx-2fa-secret"></code>
						</p>
					</div>
					<div>
						<label for="bkx-2fa-verify-code"><?php esc_html_e( 'Verify Code:', 'bkx-security-audit' ); ?></label>
						<input type="text" id="bkx-2fa-verify-code" maxlength="6" pattern="[0-9]{6}" style="width: 100px;">
						<button type="button" class="button button-primary" id="bkx-2fa-verify">
							<?php esc_html_e( 'Verify & Enable', 'bkx-security-audit' ); ?>
						</button>
						<span id="bkx-2fa-message"></span>
					</div>
				</td>
			</tr>
		</table>

		<script>
		jQuery(document).ready(function($) {
			$('#bkx-2fa-enable').on('click', function() {
				$.post(ajaxurl, {
					action: 'bkx_security_generate_2fa_secret',
					nonce: '<?php echo esc_js( wp_create_nonce( 'bkx_2fa_setup' ) ); ?>',
					user_id: <?php echo (int) $user->ID; ?>
				}, function(response) {
					if (response.success) {
						$('#bkx-2fa-qr').attr('src', response.data.qr_url);
						$('#bkx-2fa-secret').text(response.data.secret);
						$('#bkx-2fa-setup').show();
					}
				});
			});

			$('#bkx-2fa-verify').on('click', function() {
				var code = $('#bkx-2fa-verify-code').val();
				$.post(ajaxurl, {
					action: 'bkx_security_verify_2fa_setup',
					nonce: '<?php echo esc_js( wp_create_nonce( 'bkx_2fa_setup' ) ); ?>',
					user_id: <?php echo (int) $user->ID; ?>,
					code: code
				}, function(response) {
					if (response.success) {
						location.reload();
					} else {
						$('#bkx-2fa-message').text(response.data.message).css('color', 'red');
					}
				});
			});

			$('#bkx-2fa-disable').on('click', function() {
				if (confirm('<?php echo esc_js( __( 'Are you sure you want to disable two-factor authentication?', 'bkx-security-audit' ) ); ?>')) {
					$.post(ajaxurl, {
						action: 'bkx_security_disable_2fa',
						nonce: '<?php echo esc_js( wp_create_nonce( 'bkx_2fa_setup' ) ); ?>',
						user_id: <?php echo (int) $user->ID; ?>
					}, function(response) {
						if (response.success) {
							location.reload();
						}
					});
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX: Generate 2FA secret.
	 */
	public function ajax_generate_secret() {
		check_ajax_referer( 'bkx_2fa_setup', 'nonce' );

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $user_id || ( get_current_user_id() !== $user_id && ! current_user_can( 'edit_users' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-security-audit' ) ) );
		}

		$user   = get_userdata( $user_id );
		$secret = $this->generate_secret();

		// Store temporarily until verified.
		update_user_meta( $user_id, 'bkx_2fa_temp_secret', $secret );

		wp_send_json_success( array(
			'secret' => $secret,
			'qr_url' => $this->get_qr_code_url( $secret, $user->user_email ),
		) );
	}

	/**
	 * AJAX: Verify 2FA setup.
	 */
	public function ajax_verify_setup() {
		check_ajax_referer( 'bkx_2fa_setup', 'nonce' );

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$code    = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		if ( ! $user_id || ( get_current_user_id() !== $user_id && ! current_user_can( 'edit_users' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-security-audit' ) ) );
		}

		$temp_secret = get_user_meta( $user_id, 'bkx_2fa_temp_secret', true );
		if ( ! $temp_secret ) {
			wp_send_json_error( array( 'message' => __( 'No setup in progress.', 'bkx-security-audit' ) ) );
		}

		if ( ! $this->verify_code( $temp_secret, $code ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid code. Please try again.', 'bkx-security-audit' ) ) );
		}

		// Enable 2FA.
		update_user_meta( $user_id, 'bkx_2fa_secret', $temp_secret );
		update_user_meta( $user_id, 'bkx_2fa_enabled', true );
		delete_user_meta( $user_id, 'bkx_2fa_temp_secret' );

		// Generate backup codes.
		$backup_codes = $this->generate_backup_codes( $user_id );

		wp_send_json_success( array(
			'message'      => __( '2FA enabled successfully!', 'bkx-security-audit' ),
			'backup_codes' => $backup_codes,
		) );
	}

	/**
	 * AJAX: Disable 2FA.
	 */
	public function ajax_disable_2fa() {
		check_ajax_referer( 'bkx_2fa_setup', 'nonce' );

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;

		if ( ! $user_id || ( get_current_user_id() !== $user_id && ! current_user_can( 'edit_users' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bkx-security-audit' ) ) );
		}

		delete_user_meta( $user_id, 'bkx_2fa_secret' );
		delete_user_meta( $user_id, 'bkx_2fa_enabled' );
		delete_user_meta( $user_id, 'bkx_2fa_backup_codes' );

		wp_send_json_success( array( 'message' => __( '2FA disabled.', 'bkx-security-audit' ) ) );
	}

	/**
	 * Generate backup codes.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function generate_backup_codes( $user_id ) {
		$codes = array();

		for ( $i = 0; $i < 10; $i++ ) {
			$codes[] = strtoupper( wp_generate_password( 8, false ) );
		}

		// Store hashed codes.
		$hashed_codes = array_map( 'wp_hash_password', $codes );
		update_user_meta( $user_id, 'bkx_2fa_backup_codes', $hashed_codes );

		return $codes;
	}

	/**
	 * Add 2FA field to login form.
	 */
	public function add_2fa_field() {
		?>
		<p id="bkx-2fa-field" style="display: none;">
			<label for="bkx_2fa_code"><?php esc_html_e( 'Authentication Code', 'bkx-security-audit' ); ?></label>
			<input type="text" name="bkx_2fa_code" id="bkx_2fa_code" class="input" value="" size="20" autocomplete="off">
		</p>
		<?php
	}

	/**
	 * Validate 2FA on login.
	 *
	 * @param \WP_User|\WP_Error|null $user     User or error.
	 * @param string                  $username Username.
	 * @param string                  $password Password.
	 * @return \WP_User|\WP_Error
	 */
	public function validate_2fa( $user, $username, $password ) {
		if ( is_wp_error( $user ) || ! $user ) {
			return $user;
		}

		if ( ! $this->is_enabled_for_user( $user->ID ) ) {
			return $user;
		}

		// Check if 2FA code was provided.
		$code = isset( $_POST['bkx_2fa_code'] ) ? sanitize_text_field( wp_unslash( $_POST['bkx_2fa_code'] ) ) : '';

		if ( empty( $code ) ) {
			return new \WP_Error(
				'bkx_2fa_required',
				__( '<strong>Error</strong>: Please enter your authentication code.', 'bkx-security-audit' )
			);
		}

		$secret = $this->get_user_secret( $user->ID );

		// Check TOTP code.
		if ( $this->verify_code( $secret, $code ) ) {
			return $user;
		}

		// Check backup codes.
		if ( $this->verify_backup_code( $user->ID, $code ) ) {
			return $user;
		}

		return new \WP_Error(
			'bkx_2fa_invalid',
			__( '<strong>Error</strong>: Invalid authentication code.', 'bkx-security-audit' )
		);
	}

	/**
	 * Verify a backup code.
	 *
	 * @param int    $user_id User ID.
	 * @param string $code    Backup code.
	 * @return bool
	 */
	private function verify_backup_code( $user_id, $code ) {
		$backup_codes = get_user_meta( $user_id, 'bkx_2fa_backup_codes', true );

		if ( ! is_array( $backup_codes ) ) {
			return false;
		}

		$code = strtoupper( $code );

		foreach ( $backup_codes as $index => $hashed_code ) {
			if ( wp_check_password( $code, $hashed_code ) ) {
				// Remove used code.
				unset( $backup_codes[ $index ] );
				update_user_meta( $user_id, 'bkx_2fa_backup_codes', array_values( $backup_codes ) );
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if 2FA is required on login.
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user       User object.
	 */
	public function check_2fa_required( $user_login, $user ) {
		// This is called after successful password auth.
		// The actual 2FA check happens in validate_2fa filter.
	}

	/**
	 * Save 2FA settings from profile.
	 *
	 * @param int $user_id User ID.
	 */
	public function save_2fa_settings( $user_id ) {
		// Settings are saved via AJAX, this is a placeholder.
	}
}
