<?php
/**
 * Password reset handler (Phase 5, requirement 3).
 *
 * Provides:
 *  - Self-service "forgot password": user enters employee number → reset link
 *    emailed → token-validated reset form.
 *  - Admin force-reset: a company admin resets a subordinate's password and the
 *    new credentials are emailed.
 *
 * Virtual routes:
 *  - /osq-reset/            (request + reset form)
 *  AJAX:
 *  - osq_request_password_reset  (public)
 *  - osq_perform_password_reset  (public, token-guarded)
 *  - osq_admin_force_reset       (admin only)
 *
 * @package OSQ
 */

namespace OSQ\Auth;

use OSQ\Database\Schema;
use OSQ\Email\EmailService;
use OSQ\Email\EmailTemplateManager;
use OSQ\Email\MailVars;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PasswordResetHandler
 */
class PasswordResetHandler {

	const SLUG       = 'osq-reset';
	const TOKEN_TTL  = 3600; // 60 minutes.

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'parse_request', array( $this, 'parse_request' ), 8 );
		add_filter( 'template_include', array( $this, 'render' ), 96 );

		add_action( 'wp_ajax_nopriv_osq_request_password_reset', array( $this, 'ajax_request_reset' ) );
		add_action( 'wp_ajax_osq_request_password_reset', array( $this, 'ajax_request_reset' ) );
		add_action( 'wp_ajax_nopriv_osq_perform_password_reset', array( $this, 'ajax_perform_reset' ) );
		add_action( 'wp_ajax_osq_perform_password_reset', array( $this, 'ajax_perform_reset' ) );
		add_action( 'wp_ajax_osq_admin_force_reset', array( $this, 'ajax_admin_force_reset' ) );
	}

	/**
	 * @param array $vars
	 * @return array
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'osq_reset_route';
		return $vars;
	}

	/**
	 * @param \WP $wp
	 * @return void
	 */
	public function parse_request( $wp ) {
		if ( ! empty( $wp->request ) && trim( $wp->request, '/' ) === self::SLUG ) {
			$wp->query_vars['osq_reset_route'] = '1';
		}
	}

	/**
	 * Serve the reset page template.
	 *
	 * @param string $template
	 * @return string
	 */
	public function render( $template ) {
		if ( get_query_var( 'osq_reset_route' ) !== '1' ) {
			return $template;
		}
		// This is a valid virtual page, not a 404.
		status_header( 200 );
		global $wp_query;
		if ( $wp_query instanceof \WP_Query ) {
			$wp_query->is_404 = false;
		}
		$tpl = OSQ_PLUGIN_DIR . 'templates/auth/password-reset.php';
		return file_exists( $tpl ) ? $tpl : $template;
	}

	/**
	 * AJAX: request a password reset by employee number.
	 * Always returns success (to avoid user enumeration).
	 *
	 * @return void
	 */
	public function ajax_request_reset() {
		check_ajax_referer( 'osq_reset_nonce', 'nonce' );

		$employee_number = sanitize_text_field( wp_unslash( $_POST['employee_number'] ?? '' ) );
		$generic = array( 'message' => 'パスワード再設定の案内を送信しました。メールをご確認ください。' );

		if ( $employee_number === '' ) {
			wp_send_json_success( $generic );
		}

		global $wpdb;
		$emp = $wpdb->get_row( $wpdb->prepare(
			"SELECT employee_id, wp_user_id, company_id, name, email
			 FROM {$wpdb->prefix}" . Schema::EMPLOYEES . " WHERE employee_number = %s LIMIT 1",
			$employee_number
		) );

		if ( $emp && $emp->wp_user_id && $emp->email && is_email( $emp->email ) ) {
			$token = $this->create_token( (int) $emp->wp_user_id );
			$link  = add_query_arg(
				array( 'uid' => (int) $emp->wp_user_id, 'token' => $token ),
				home_url( '/' . self::SLUG . '/' )
			);

			$mailer = new EmailService();
			$vars   = array_merge( MailVars::company_base( (int) $emp->company_id ), array(
				'氏名'    => $emp->name,
				'受検URL' => $link, // reset link reuses the {受検URL} slot in the template
			) );
			$mailer->send_template(
				EmailTemplateManager::PASSWORD_RESET,
				$emp->email,
				$vars,
				(int) $emp->company_id,
				(int) $emp->employee_id
			);
		}

		wp_send_json_success( $generic );
	}

	/**
	 * AJAX: perform the reset given a valid token + new password.
	 *
	 * @return void
	 */
	public function ajax_perform_reset() {
		check_ajax_referer( 'osq_reset_nonce', 'nonce' );

		$uid      = absint( $_POST['uid'] ?? 0 );
		$token    = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
		$password = (string) wp_unslash( $_POST['password'] ?? '' );

		if ( strlen( $password ) < 8 ) {
			wp_send_json_error( array( 'message' => 'パスワードは8文字以上で設定してください。' ) );
		}

		if ( ! $this->validate_token( $uid, $token ) ) {
			wp_send_json_error( array( 'message' => 'リンクが無効か、有効期限が切れています。お手数ですが再度お手続きください。' ) );
		}

		wp_set_password( $password, $uid );
		update_user_meta( $uid, 'osq_must_change_password', false );
		$this->consume_token( $uid );

		wp_send_json_success( array( 'message' => 'パスワードを再設定しました。新しいパスワードでログインしてください。' ) );
	}

	/**
	 * AJAX: admin force-resets a subordinate's password (same company only).
	 *
	 * @return void
	 */
	public function ajax_admin_force_reset() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! CapabilityMatrix::user_has( CapabilityMatrix::MANAGE_EMPLOYEES )
			&& ! CapabilityMatrix::user_has( CapabilityMatrix::MANAGE_ALL_COMPANIES ) ) {
			wp_send_json_error( array( 'message' => 'unauthorized' ), 403 );
		}

		$employee_id = absint( $_POST['employee_id'] ?? 0 );
		global $wpdb;
		$emp = $wpdb->get_row( $wpdb->prepare(
			"SELECT employee_id, wp_user_id, company_id, name, email, employee_number
			 FROM {$wpdb->prefix}" . Schema::EMPLOYEES . " WHERE employee_id = %d",
			$employee_id
		) );

		if ( ! $emp ) {
			wp_send_json_error( array( 'message' => '対象の従業員が見つかりません。' ) );
		}
		if ( ! $emp->wp_user_id ) {
			wp_send_json_error( array( 'message' => 'この従業員にはログインアカウントが設定されていないため、パスワードを再発行できません。' ) );
		}

		// Tenant guard: admins may only reset within their own company.
		$admin_company = \OSQ\Database\DbManager::current_company_id();
		$is_super      = CapabilityMatrix::user_has( CapabilityMatrix::MANAGE_ALL_COMPANIES );
		if ( ! $is_super && (int) $emp->company_id !== (int) $admin_company ) {
			wp_send_json_error( array( 'message' => '他社の従業員は操作できません。' ), 403 );
		}

		$new_password = wp_generate_password( 12, true, false );
		wp_set_password( $new_password, (int) $emp->wp_user_id );
		update_user_meta( (int) $emp->wp_user_id, 'osq_must_change_password', true );

		// Email the new credentials if the employee has an address.
		$emailed = false;
		if ( $emp->email && is_email( $emp->email ) ) {
			$mailer = new EmailService();
			$vars   = array_merge( MailVars::company_base( (int) $emp->company_id ), array(
				'氏名'           => $emp->name,
				'ID'             => $emp->employee_number,
				'初期パスワード' => $new_password,
				'ログインURL'    => MailVars::login_url(),
			) );
			$emailed = $mailer->send_template(
				EmailTemplateManager::COMPANY_WELCOME,
				$emp->email,
				$vars,
				(int) $emp->company_id,
				(int) $emp->employee_id
			);
		}

		wp_send_json_success( array(
			'message'  => 'パスワードを再発行しました。',
			'password' => $new_password,
			'emailed'  => $emailed,
		) );
	}

	/*
	|----------------------------------------------------------------------
	| Token helpers
	|----------------------------------------------------------------------
	*/

	/**
	 * Create and store a single-use reset token.
	 *
	 * @param int $uid
	 * @return string Raw token (sent to the user).
	 */
	private function create_token( $uid ) {
		global $wpdb;
		$raw  = wp_generate_password( 40, false, false );
		$hash = wp_hash_password( $raw );

		// Invalidate any outstanding tokens for this user.
		$wpdb->delete( $wpdb->prefix . Schema::PASSWORD_RESETS, array( 'wp_user_id' => (int) $uid ) );

		$wpdb->insert(
			$wpdb->prefix . Schema::PASSWORD_RESETS,
			array(
				'wp_user_id' => (int) $uid,
				'token_hash' => $hash,
				'expires_at' => gmdate( 'Y-m-d H:i:s', time() + self::TOKEN_TTL ),
			)
		);
		return $raw;
	}

	/**
	 * Validate a token for a user (not expired, not used, hash matches).
	 *
	 * @param int    $uid
	 * @param string $token
	 * @return bool
	 */
	private function validate_token( $uid, $token ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT token_hash, expires_at, used_at FROM {$wpdb->prefix}" . Schema::PASSWORD_RESETS . "
			 WHERE wp_user_id = %d ORDER BY reset_id DESC LIMIT 1",
			(int) $uid
		) );
		if ( ! $row || $row->used_at ) {
			return false;
		}
		if ( strtotime( $row->expires_at ) < time() ) {
			return false;
		}
		return wp_check_password( $token, $row->token_hash );
	}

	/**
	 * Mark the user's outstanding token as used.
	 *
	 * @param int $uid
	 * @return void
	 */
	private function consume_token( $uid ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . Schema::PASSWORD_RESETS,
			array( 'used_at' => current_time( 'mysql' ) ),
			array( 'wp_user_id' => (int) $uid )
		);
	}
}
