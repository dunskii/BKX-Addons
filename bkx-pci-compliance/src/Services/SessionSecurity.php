<?php
/**
 * Session Security Service.
 *
 * @package BookingX\PCICompliance
 */

namespace BookingX\PCICompliance\Services;

defined( 'ABSPATH' ) || exit;

/**
 * SessionSecurity class.
 *
 * Implements session security per PCI DSS Requirement 8.1.8.
 */
class SessionSecurity {

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'bkx_pci_compliance_settings', array() );
	}

	/**
	 * Initialize session security.
	 */
	public function init() {
		// Session timeout handling.
		add_action( 'init', array( $this, 'check_session_timeout' ) );
		add_action( 'wp_login', array( $this, 'set_session_start' ), 10, 2 );
		add_action( 'wp_logout', array( $this, 'clear_session_data' ) );

		// Track activity for idle timeout.
		add_action( 'wp_ajax_bkx_pci_heartbeat', array( $this, 'handle_heartbeat' ) );
		add_action( 'admin_footer', array( $this, 'output_idle_script' ) );
		add_action( 'wp_footer', array( $this, 'output_idle_script' ) );

		// Secure session cookies.
		add_filter( 'auth_cookie_expiration', array( $this, 'filter_cookie_expiration' ), 10, 3 );
		add_filter( 'secure_auth_cookie', array( $this, 'force_secure_cookie' ) );
	}

	/**
	 * Check session timeout.
	 */
	public function check_session_timeout() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$timeout = $this->settings['session_timeout'] ?? 15;
		if ( ! $timeout ) {
			return;
		}

		$user_id       = get_current_user_id();
		$last_activity = get_user_meta( $user_id, '_bkx_last_activity', true );

		if ( $last_activity ) {
			$idle_minutes = ( time() - $last_activity ) / 60;

			if ( $idle_minutes >= $timeout ) {
				// Session expired due to inactivity.
				$this->log_session_timeout( $user_id );
				wp_logout();
				wp_safe_redirect( add_query_arg( 'session_expired', '1', wp_login_url() ) );
				exit;
			}
		}

		// Update last activity.
		update_user_meta( $user_id, '_bkx_last_activity', time() );
	}

	/**
	 * Set session start time on login.
	 *
	 * @param string   $user_login Username.
	 * @param \WP_User $user       User object.
	 */
	public function set_session_start( $user_login, $user ) {
		update_user_meta( $user->ID, '_bkx_session_start', time() );
		update_user_meta( $user->ID, '_bkx_last_activity', time() );
	}

	/**
	 * Clear session data on logout.
	 */
	public function clear_session_data() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			delete_user_meta( $user_id, '_bkx_session_start' );
			delete_user_meta( $user_id, '_bkx_last_activity' );
		}
	}

	/**
	 * Handle heartbeat AJAX for tracking activity.
	 */
	public function handle_heartbeat() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'expired' => true ) );
		}

		$user_id = get_current_user_id();
		$timeout = $this->settings['session_timeout'] ?? 15;

		$last_activity = get_user_meta( $user_id, '_bkx_last_activity', true );
		$idle_minutes  = $last_activity ? ( time() - $last_activity ) / 60 : 0;

		if ( $idle_minutes >= $timeout ) {
			wp_send_json_error( array( 'expired' => true ) );
		}

		// Update activity on explicit action (not just heartbeat).
		if ( isset( $_POST['action_performed'] ) && $_POST['action_performed'] ) {
			update_user_meta( $user_id, '_bkx_last_activity', time() );
		}

		wp_send_json_success( array(
			'remaining' => max( 0, $timeout - $idle_minutes ),
		) );
	}

	/**
	 * Output idle timeout JavaScript.
	 */
	public function output_idle_script() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$timeout = $this->settings['session_timeout'] ?? 15;
		if ( ! $timeout ) {
			return;
		}

		$warning_time = max( 1, $timeout - 2 ); // Warn 2 minutes before timeout.
		?>
		<script>
		(function() {
			var idleTime = 0;
			var timeout = <?php echo absint( $timeout ); ?>;
			var warningShown = false;

			// Reset timer on activity.
			function resetTimer() {
				idleTime = 0;
				warningShown = false;
				hideWarning();
			}

			// Show warning.
			function showWarning() {
				if (warningShown) return;
				warningShown = true;

				var warning = document.createElement('div');
				warning.id = 'bkx-session-warning';
				warning.innerHTML = '<div style="position:fixed;top:0;left:0;right:0;background:#ffb900;color:#000;padding:15px;text-align:center;z-index:999999;">' +
					'<?php echo esc_js( __( 'Your session will expire due to inactivity. Click anywhere to stay logged in.', 'bkx-pci-compliance' ) ); ?>' +
					'</div>';
				document.body.appendChild(warning);
			}

			function hideWarning() {
				var warning = document.getElementById('bkx-session-warning');
				if (warning) {
					warning.remove();
				}
			}

			// Check idle time.
			function checkIdle() {
				idleTime++;
				if (idleTime >= timeout) {
					window.location.href = '<?php echo esc_url( add_query_arg( 'session_expired', '1', wp_login_url() ) ); ?>';
				} else if (idleTime >= <?php echo absint( $warning_time ); ?>) {
					showWarning();
				}
			}

			// Set up event listeners.
			document.addEventListener('mousemove', resetTimer);
			document.addEventListener('keypress', resetTimer);
			document.addEventListener('click', resetTimer);
			document.addEventListener('scroll', resetTimer);
			document.addEventListener('touchstart', resetTimer);

			// Check every minute.
			setInterval(checkIdle, 60000);
		})();
		</script>
		<?php
	}

	/**
	 * Filter cookie expiration.
	 *
	 * @param int     $expiration Cookie expiration.
	 * @param int     $user_id    User ID.
	 * @param bool    $remember   Remember me flag.
	 * @return int
	 */
	public function filter_cookie_expiration( $expiration, $user_id, $remember ) {
		// Don't allow "remember me" for admins handling payment data.
		if ( user_can( $user_id, 'manage_options' ) ) {
			$timeout = $this->settings['session_timeout'] ?? 15;
			return $timeout * 60; // Convert minutes to seconds.
		}

		return $expiration;
	}

	/**
	 * Force secure cookies when SSL is available.
	 *
	 * @param bool $secure Current secure flag.
	 * @return bool
	 */
	public function force_secure_cookie( $secure ) {
		if ( is_ssl() ) {
			return true;
		}
		return $secure;
	}

	/**
	 * Log session timeout.
	 *
	 * @param int $user_id User ID.
	 */
	private function log_session_timeout( $user_id ) {
		$addon = \BookingX\PCICompliance\PCIComplianceAddon::get_instance();
		$audit = $addon->get_service( 'audit_logger' );

		if ( $audit ) {
			$audit->log(
				'session_timeout',
				'authentication',
				'info',
				array(
					'user_id'         => $user_id,
					'pci_requirement' => '8.1.8',
				)
			);
		}
	}

	/**
	 * Regenerate session ID.
	 */
	public function regenerate_session() {
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			session_regenerate_id( true );
		}
	}

	/**
	 * Get session info.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public function get_session_info( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return array();
		}

		$session_start = get_user_meta( $user_id, '_bkx_session_start', true );
		$last_activity = get_user_meta( $user_id, '_bkx_last_activity', true );
		$timeout       = $this->settings['session_timeout'] ?? 15;

		return array(
			'session_start' => $session_start,
			'last_activity' => $last_activity,
			'idle_minutes'  => $last_activity ? ( time() - $last_activity ) / 60 : 0,
			'timeout'       => $timeout,
			'expires_in'    => $last_activity ? max( 0, $timeout - ( ( time() - $last_activity ) / 60 ) ) : null,
		);
	}

	/**
	 * Terminate all sessions for a user.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function terminate_all_sessions( $user_id ) {
		if ( ! $user_id ) {
			return false;
		}

		// Clear session tokens.
		$sessions = WP_Session_Tokens::get_instance( $user_id );
		$sessions->destroy_all();

		// Clear our meta.
		delete_user_meta( $user_id, '_bkx_session_start' );
		delete_user_meta( $user_id, '_bkx_last_activity' );

		// Log the action.
		$addon = \BookingX\PCICompliance\PCIComplianceAddon::get_instance();
		$audit = $addon->get_service( 'audit_logger' );

		if ( $audit ) {
			$audit->log(
				'sessions_terminated',
				'security',
				'warning',
				array(
					'target_user_id'  => $user_id,
					'pci_requirement' => '8.1',
				)
			);
		}

		return true;
	}
}
