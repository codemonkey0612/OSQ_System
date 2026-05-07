<?php
/**
 * Authentication enhancements.
 *
 * @package OSQ
 */

namespace OSQ\Auth;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LoginManager
 * 
 * Handles employee number login, rate limiting, and session timeouts.
 */
class LoginManager {

	/**
	 * Failed login attempts option name.
	 */
	const FAIL_ATTEMPTS_OPTION = 'osq_failed_login_attempts';

	/**
	 * Lockout duration in minutes (2 hours).
	 */
	const LOCKOUT_DURATION = 120;

	/**
	 * Maximum failed attempts.
	 */
	const MAX_ATTEMPTS = 5;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Employee number based login.
		add_filter( 'authenticate', array( $this, 'authenticate_by_employee_number' ), 20, 3 );

		// Login rate limiting.
		add_action( 'wp_login_failed', array( $this, 'track_failed_login' ) );
		add_filter( 'authenticate', array( $this, 'check_lockout' ), 10, 3 );

		// Restrict default login access for OSQ roles.
		add_filter( 'authenticate', array( $this, 'restrict_wp_login' ), 30, 3 );

		// Session timeout.
		add_filter( 'auth_cookie_expiration', array( $this, 'set_session_timeout' ), 10, 3 );
		// add_action( 'admin_init', array( $this, 'check_session_activity' ) );

		// Password change mechanism for Profile tabs.
		add_action( 'wp_ajax_osq_change_password', array( $this, 'ajax_change_password' ) );
	}

	/**
	 * Allow authentication via employee_number.
	 *
	 * @param \WP_User|\WP_Error|null $user
	 * @param string                  $username Standard username or employee number.
	 * @param string                  $password
	 * @return \WP_User|\WP_Error
	 */
	public function authenticate_by_employee_number( $user, $username, $password ) {
		if ( empty( $username ) || empty( $password ) ) {
			return $user;
		}

		// If user is already found, don't override.
		if ( $user instanceof \WP_User ) {
			return $user;
		}

		// If a previous filter (e.g. lockout) returned an error, respect it.
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// Try to find employee record.
		$osq_plugin = \OSQ\Plugin::get_instance();
		$employee = $osq_plugin->db()->get_employee_by_number( $username );

		if ( ! $employee || ! $employee->wp_user_id ) {
			return $user;
		}

		// Use the linked WordPress User ID for authentication.
		$wp_user = get_user_by( 'id', $employee->wp_user_id );
		if ( ! $wp_user ) {
			return $user;
		}

		if ( wp_check_password( $password, $wp_user->user_pass, $wp_user->ID ) ) {
			return $wp_user;
		}

		return $user;
	}

	/**
	 * Track failed login attempts by IP (WordPress hook callback).
	 *
	 * @param string $username
	 * @return void
	 */
	public function track_failed_login( $username ) {
		self::record_failed_attempt();
	}

	/**
	 * Check if the user is currently locked out (WordPress hook callback).
	 *
	 * @param \WP_User|\WP_Error|null $user
	 * @return \WP_User|\WP_Error
	 */
	public function check_lockout( $user, $username, $password ) {
		if ( self::is_ip_locked_out() ) {
			$hours = self::LOCKOUT_DURATION / 60;
			return new \WP_Error( 'osq_lockout', sprintf(
				/* translators: %d: hours. */
				__( 'ログイン試行回数が上限を超えました。%d時間後に再度お試しください。 (Too many failed login attempts. Please try again in %d hours.)', 'osq-stress-check' ),
				$hours,
				$hours
			) );
		}

		return $user;
	}

	/*
	|----------------------------------------------------------------------
	| Public Static Helpers — call from any login handler
	|----------------------------------------------------------------------
	*/

	/**
	 * Check if the current IP is locked out.
	 *
	 * @return bool
	 */
	public static function is_ip_locked_out() {
		$ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$attempts = get_transient( self::FAIL_ATTEMPTS_OPTION . '_' . $ip ) ?: 0;
		return $attempts >= self::MAX_ATTEMPTS;
	}

	/**
	 * Record a failed login attempt for the current IP.
	 *
	 * @return void
	 */
	public static function record_failed_attempt() {
		$ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$attempts = get_transient( self::FAIL_ATTEMPTS_OPTION . '_' . $ip ) ?: 0;
		$new_count = $attempts + 1;
		set_transient(
			self::FAIL_ATTEMPTS_OPTION . '_' . $ip,
			$new_count,
			self::LOCKOUT_DURATION * MINUTE_IN_SECONDS
		);
		// Store the time when lockout threshold is reached.
		if ( $new_count >= self::MAX_ATTEMPTS ) {
			set_transient(
				self::FAIL_ATTEMPTS_OPTION . '_time_' . $ip,
				time(),
				self::LOCKOUT_DURATION * MINUTE_IN_SECONDS
			);
		}
	}

	/**
	 * Get the lockout error message (Japanese + English).
	 *
	 * @return string
	 */
	public static function get_lockout_message() {
		$hours = self::LOCKOUT_DURATION / 60;
		return sprintf(
			'ログイン試行回数が上限を超えました。%d時間後に再度お試しください。 (Too many failed login attempts. Please try again in %d hours.)',
			$hours,
			$hours
		);
	}

	/**
	 * Get remaining lockout seconds for the current IP.
	 *
	 * @return int Remaining seconds, 0 if not locked out.
	 */
	public static function get_lockout_remaining_seconds() {
		if ( ! self::is_ip_locked_out() ) {
			return 0;
		}
		// WordPress transients don't expose their TTL directly.
		// Store the lockout timestamp separately so we can calculate remaining time.
		$ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
		$locked_at = get_transient( self::FAIL_ATTEMPTS_OPTION . '_time_' . $ip );
		if ( ! $locked_at ) {
			// Fallback: assume full duration remains.
			return self::LOCKOUT_DURATION * 60;
		}
		$elapsed   = time() - (int) $locked_at;
		$remaining = ( self::LOCKOUT_DURATION * 60 ) - $elapsed;
		return max( 0, $remaining );
	}

	/**
	 * Restrict default login access for OSQ roles.
	 *
	 * @param \WP_User|\WP_Error|null $user
	 * @return \WP_User|\WP_Error
	 */
	public function restrict_wp_login( $user, $username, $password ) {
		if ( $user instanceof \WP_Error ) {
			return $user;
		}

		// Only apply restriction on the default wp-login.php page
		$is_wp_login = isset( $_SERVER['SCRIPT_NAME'] ) && strpos( $_SERVER['SCRIPT_NAME'], 'wp-login.php' ) !== false;
		
		if ( $is_wp_login && $user instanceof \WP_User ) {
			// Check roles and deny access with specific messages
			if ( in_array( RoleManager::EMPLOYEE, $user->roles, true ) ) {
				return new \WP_Error( 'osq_employee_restriction', __( 'Employee login from this page is prohibited. Please use the designated Employee Portal to log in.', 'osq-stress-check' ) );
			}

			if ( in_array( RoleManager::IMPLEMENTATION_OFFICER, $user->roles, true ) ) {
				return new \WP_Error( 'osq_officer_restriction', __( 'Implementation Officer login from this page is prohibited. Please use the designated Officer Portal to log in.', 'osq-stress-check' ) );
			}

			// For General Admin, check if they are exclusively the OSQ admin or a Super Admin.
			// Standard WP Administrators should still be able to login to manage the site.
			if ( in_array( RoleManager::GENERAL_ADMINISTRATOR, $user->roles, true ) && ! in_array( 'administrator', $user->roles, true ) ) {
				return new \WP_Error( 'osq_admin_restriction', __( 'General Administrator login from this page is prohibited. Please use the designated Admin Portal to log in.', 'osq-stress-check' ) );
			}
		}

		return $user;
	}

	/**
	 * Set session expiration to 8 hours for OSQ sessions.
	 *
	 * @param int $expiration Expiration in seconds.
	 * @param int $user_id    User ID.
	 * @param bool $remember   Whether to remember the user.
	 * @return int Expiration in seconds.
	 */
	public function set_session_timeout( $expiration, $user_id, $remember ) {
		if ( $this->is_osq_user( $user_id ) ) {
			return 8 * HOUR_IN_SECONDS;
		}
		return $expiration;
	}

	/**
	 * Handle AJAX password change request from the Profile tab.
	 *
	 * @return void
	 */
	public function ajax_change_password() {
		check_ajax_referer( 'osq_change_password_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to change your password.', 'osq-stress-check' ) ) );
		}

		$current_password = isset( $_POST['current_password'] ) ? wp_unslash( $_POST['current_password'] ) : '';
		$new_password     = isset( $_POST['new_password'] ) ? wp_unslash( $_POST['new_password'] ) : '';
		$confirm_password = isset( $_POST['confirm_password'] ) ? wp_unslash( $_POST['confirm_password'] ) : '';

		if ( empty( $current_password ) || empty( $new_password ) || empty( $confirm_password ) ) {
			wp_send_json_error( array( 'message' => __( 'All fields are required.', 'osq-stress-check' ) ) );
		}

		if ( $new_password !== $confirm_password ) {
			wp_send_json_error( array( 'message' => __( 'New passwords do not match.', 'osq-stress-check' ) ) );
		}

		if ( strlen( $new_password ) < 8 ) {
			wp_send_json_error( array( 'message' => __( 'New password must be at least 8 characters long.', 'osq-stress-check' ) ) );
		}

		$user = wp_get_current_user();
		
		// Verify current password
		if ( ! wp_check_password( $current_password, $user->user_pass, $user->ID ) ) {
			wp_send_json_error( array( 'message' => __( 'Current password is incorrect.', 'osq-stress-check' ) ) );
		}

		// Update password
		wp_set_password( $new_password, $user->ID );
		
		// Clear the must-change flag
		delete_user_meta( $user->ID, 'osq_must_change_password' );

		// wp_set_password logs the user out, so we need to log them back in automatically
		wp_set_auth_cookie( $user->ID, false );

		wp_send_json_success( array( 'message' => __( 'Password changed successfully.', 'osq-stress-check' ) ) );
	}

	/**
	 * Check session activity to enforce absolute timeout.
	 *
	 * @return void
	 */
	public function check_session_activity() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();

		// Only enforce for OSQ roles to avoid locking out standard admins.
		if ( ! $this->is_osq_user( $user_id ) ) {
			return;
		}

		$last_activity = get_user_meta( $user_id, 'osq_last_activity', true );
		if ( $last_activity && ( time() - $last_activity ) > ( 30 * MINUTE_IN_SECONDS ) ) {
			wp_logout();
			wp_safe_redirect( wp_login_url() . '?osq_timeout=1' );
			exit;
		}

		update_user_meta( $user_id, 'osq_last_activity', time() );
	}

	/**
	 * Check if a user has any OSQ-specific roles.
	 *
	 * @param int $user_id
	 * @return bool
	 */
	private function is_osq_user( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$osq_roles = array(
			RoleManager::IMPLEMENTATION_OFFICER,
			RoleManager::GENERAL_ADMINISTRATOR,
			RoleManager::EMPLOYEE,
		);

		foreach ( $osq_roles as $role ) {
			if ( in_array( $role, $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}
}
