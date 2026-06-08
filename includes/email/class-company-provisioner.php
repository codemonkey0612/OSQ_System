<?php
/**
 * Company provisioner — creates a company's admin account and emails the
 * login URL + auto-generated initial credentials (Phase 5, requirement 1).
 *
 * @package OSQ
 */

namespace OSQ\Email;

use OSQ\Database\Schema;
use OSQ\Auth\RoleManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CompanyProvisioner
 */
class CompanyProvisioner {

	/**
	 * Provision an administrator account for a newly created company and send
	 * the welcome email containing the login URL + initial credentials.
	 *
	 * @param int    $company_id
	 * @param string $company_name
	 * @param string $admin_email
	 * @param string $admin_name   Optional display name for the admin.
	 * @return array|\WP_Error { employee_number, password, wp_user_id } or error.
	 */
	public static function provision_admin( $company_id, $company_name, $admin_email, $admin_name = '' ) {
		$admin_email = sanitize_email( $admin_email );
		if ( ! is_email( $admin_email ) ) {
			return new \WP_Error( 'osq_bad_email', '管理者メールアドレスが不正です。' );
		}
		if ( email_exists( $admin_email ) ) {
			return new \WP_Error( 'osq_email_exists', 'このメールアドレスは既に登録されています。' );
		}

		global $wpdb;

		// Build a globally-unique admin login ID from the (unique) company slug.
		$slug = $wpdb->get_var( $wpdb->prepare(
			"SELECT company_slug FROM {$wpdb->prefix}" . Schema::COMPANIES . " WHERE company_id = %d",
			(int) $company_id
		) );
		$base = strtoupper( preg_replace( '/[^a-zA-Z0-9]/', '', (string) $slug ) );
		if ( $base === '' ) {
			$base = 'COMPANY' . (int) $company_id;
		}
		$employee_number = $base . '-ADMIN';

		// Ensure global uniqueness of the login ID.
		$suffix = 1;
		$candidate = $employee_number;
		while ( $wpdb->get_var( $wpdb->prepare(
			"SELECT employee_id FROM {$wpdb->prefix}" . Schema::EMPLOYEES . " WHERE employee_number = %s",
			$candidate
		) ) ) {
			$candidate = $employee_number . $suffix;
			$suffix++;
		}
		$employee_number = $candidate;

		// Create the WP user (general administrator for this tenant).
		$password   = wp_generate_password( 12, true, false );
		$wp_user_id = wp_insert_user( array(
			'user_login'   => $admin_email,
			'user_email'   => $admin_email,
			'user_pass'    => $password,
			'display_name' => $admin_name ?: $company_name . ' 管理者',
			'role'         => RoleManager::GENERAL_ADMINISTRATOR,
		) );

		if ( is_wp_error( $wp_user_id ) ) {
			return $wp_user_id;
		}

		update_user_meta( $wp_user_id, 'osq_company_id', (int) $company_id );
		update_user_meta( $wp_user_id, 'osq_must_change_password', true );

		// Create the linked employee record (login is by employee_number).
		$wpdb->insert(
			$wpdb->prefix . Schema::EMPLOYEES,
			array(
				'company_id'      => (int) $company_id,
				'wp_user_id'      => (int) $wp_user_id,
				'employee_number' => $employee_number,
				'name'            => $admin_name ?: ( $company_name . ' 管理者' ),
				'email'           => $admin_email,
			)
		);

		// Send the welcome email with credentials.
		$mailer = new EmailService();
		$vars   = array_merge( MailVars::company_base( $company_id ), array(
			'氏名'           => $admin_name ?: ( $company_name . ' ご担当者' ),
			'ID'             => $employee_number,
			'初期パスワード' => $password,
			'ログインURL'    => MailVars::login_url(),
		) );
		$mailer->send_template(
			EmailTemplateManager::COMPANY_WELCOME,
			$admin_email,
			$vars,
			$company_id
		);

		return array(
			'employee_number' => $employee_number,
			'password'        => $password,
			'wp_user_id'      => (int) $wp_user_id,
		);
	}
}
