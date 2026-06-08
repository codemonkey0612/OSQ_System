<?php
/**
 * Mail variable resolver — builds the {tag} => value maps for email templates.
 *
 * Centralizes URL construction and per-company data lookup so every sender
 * (welcome, invite, reminder, reset) produces consistent variables. (Phase 5)
 *
 * @package OSQ
 */

namespace OSQ\Email;

use OSQ\Database\Schema;
use OSQ\Auth\EmployeeUiHandler;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MailVars
 */
class MailVars {

	/**
	 * The login page URL.
	 *
	 * @return string
	 */
	public static function login_url() {
		return home_url( '/' . EmployeeUiHandler::LOGIN_SLUG . '/' );
	}

	/**
	 * The questionnaire (survey) URL.
	 *
	 * @return string
	 */
	public static function survey_url() {
		return home_url( '/' . EmployeeUiHandler::QUESTIONNAIRE_SLUG . '/' );
	}

	/**
	 * The configured FAQ URL (master setting), falling back to home.
	 *
	 * @return string
	 */
	public static function faq_url() {
		$settings = get_option( 'osq_email_settings', array() );
		$faq      = $settings['faq_url'] ?? '';
		return $faq !== '' ? $faq : home_url( '/' );
	}

	/**
	 * Base variables shared by all company emails.
	 *
	 * @param int $company_id
	 * @return array
	 */
	public static function company_base( $company_id ) {
		global $wpdb;
		$company = $wpdb->get_row( $wpdb->prepare(
			"SELECT company_name, contact_name, contact_phone, contact_email
			 FROM {$wpdb->prefix}" . Schema::COMPANIES . " WHERE company_id = %d",
			(int) $company_id
		) );

		$contact_parts = array();
		if ( $company ) {
			if ( $company->contact_name )  { $contact_parts[] = $company->contact_name; }
			if ( $company->contact_phone ) { $contact_parts[] = 'TEL: ' . $company->contact_phone; }
			if ( $company->contact_email ) { $contact_parts[] = 'Email: ' . $company->contact_email; }
		}

		return array(
			'会社名'       => $company->company_name ?? '',
			'ログインURL'  => self::login_url(),
			'受検URL'      => self::survey_url(),
			'FAQ_URL'      => self::faq_url(),
			'問い合わせ先' => implode( '　', $contact_parts ),
		);
	}
}
