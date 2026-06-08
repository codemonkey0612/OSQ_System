<?php
/**
 * Email service — SMTP delivery, template rendering, variable substitution, logging.
 *
 * Phase 5. Sends from a common system address (no-reply@wellanc.com) with the
 * company's contact set as Reply-To, so employee replies reach each company
 * while deliverability stays anchored to one authenticated sender (A案).
 *
 * @package OSQ
 */

namespace OSQ\Email;

use OSQ\Database\Schema;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EmailService
 */
class EmailService {

	/**
	 * Register the SMTP configuration hook.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'phpmailer_init', array( $this, 'configure_smtp' ) );
	}

	/**
	 * Default SMTP / sender settings, overridable via osq_settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		// SMTP delivery is handled by the site's existing wp-mail-smtp plugin
		// (info@wellanc.com via local relay). We therefore leave our own SMTP
		// override DISABLED by default and only need to manage From/Reply-To.
		// Setting smtp_pass here would activate our phpmailer_init override —
		// only do that if wp-mail-smtp is removed.
		return array(
			'smtp_enabled'    => 0,
			'smtp_host'       => 'mail.onamae.ne.jp',
			'smtp_port'       => 587,
			'smtp_encryption' => 'tls', // tls | ssl | none
			'smtp_user'       => 'info@wellanc.com',
			'smtp_pass'       => '',
			'mail_from'       => 'info@wellanc.com',
			'mail_from_name'  => 'wellanc ストレスチェック',
		);
	}

	/**
	 * Get a resolved settings array (defaults merged with saved settings).
	 *
	 * @return array
	 */
	public static function config() {
		$saved = get_option( 'osq_email_settings', array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Configure PHPMailer to use the configured SMTP server.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer
	 * @return void
	 */
	public function configure_smtp( $phpmailer ) {
		$cfg = self::config();

		if ( empty( $cfg['smtp_enabled'] ) || empty( $cfg['smtp_host'] ) || empty( $cfg['smtp_pass'] ) ) {
			return; // Fall back to PHP mail() if SMTP not fully configured.
		}

		$phpmailer->isSMTP();
		$phpmailer->Host       = $cfg['smtp_host'];
		$phpmailer->Port       = (int) $cfg['smtp_port'];
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = $cfg['smtp_user'];
		$phpmailer->Password   = $cfg['smtp_pass'];
		$phpmailer->CharSet    = 'UTF-8';

		if ( 'ssl' === $cfg['smtp_encryption'] ) {
			$phpmailer->SMTPSecure = 'ssl';
		} elseif ( 'tls' === $cfg['smtp_encryption'] ) {
			$phpmailer->SMTPSecure = 'tls';
		} else {
			$phpmailer->SMTPSecure = '';
			$phpmailer->SMTPAutoTLS = false;
		}
	}

	/**
	 * Send an email rendered from a stored template.
	 *
	 * @param string   $template_key One of EmailTemplateManager keys.
	 * @param string   $recipient    Destination email address.
	 * @param array    $vars         Variable map for tag substitution.
	 * @param int|null $company_id   Tenant for Reply-To resolution + logging.
	 * @param int|null $employee_id  Optional employee for logging.
	 * @return bool True on success.
	 */
	public function send_template( $template_key, $recipient, $vars = array(), $company_id = null, $employee_id = null ) {
		$tpl = EmailTemplateManager::get_template( $template_key );

		if ( ! $tpl || empty( $tpl['is_active'] ) ) {
			return false;
		}

		$subject = self::render( $tpl['subject'], $vars );
		$body    = self::render( $tpl['body'], $vars );

		return $this->send_raw( $recipient, $subject, $body, $company_id, $template_key, $employee_id );
	}

	/**
	 * Send a raw email with the system From + company Reply-To.
	 *
	 * @param string   $recipient
	 * @param string   $subject
	 * @param string   $body         Plain text (newlines preserved as <br> in HTML).
	 * @param int|null $company_id
	 * @param string   $template_key For logging.
	 * @param int|null $employee_id
	 * @return bool
	 */
	public function send_raw( $recipient, $subject, $body, $company_id = null, $template_key = 'raw', $employee_id = null ) {
		$cfg = self::config();

		$recipient = sanitize_email( $recipient );
		if ( ! is_email( $recipient ) ) {
			$this->log( $company_id, $template_key, $recipient, $employee_id, $subject, 'failed', 'invalid recipient' );
			return false;
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $cfg['mail_from_name'], $cfg['mail_from'] ),
		);

		// Reply-To = company contact email, so employee replies reach the company.
		$reply_to = $this->company_reply_to( $company_id );
		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		// Convert plain-text body to simple HTML (preserve line breaks).
		$html_body = $this->to_html( $body );

		$sent = wp_mail( $recipient, $subject, $html_body, $headers );

		$this->log(
			$company_id,
			$template_key,
			$recipient,
			$employee_id,
			$subject,
			$sent ? 'sent' : 'failed',
			$sent ? null : 'wp_mail returned false'
		);

		return (bool) $sent;
	}

	/**
	 * Send using an explicit (possibly admin-edited) subject + body, rendering
	 * {tag} variables per recipient. Used by the company-side "final edit + send".
	 *
	 * @param string   $recipient
	 * @param string   $subject_tpl
	 * @param string   $body_tpl
	 * @param array    $vars
	 * @param int|null $company_id
	 * @param string   $template_key Logging label.
	 * @param int|null $employee_id
	 * @return bool
	 */
	public function send_custom( $recipient, $subject_tpl, $body_tpl, $vars, $company_id = null, $template_key = 'custom', $employee_id = null ) {
		$subject = self::render( $subject_tpl, $vars );
		$body    = self::render( $body_tpl, $vars );
		return $this->send_raw( $recipient, $subject, $body, $company_id, $template_key, $employee_id );
	}

	/**
	 * Replace {tag} placeholders in a string with provided values.
	 * Unknown tags are left intact so authors can spot typos.
	 *
	 * @param string $text
	 * @param array  $vars
	 * @return string
	 */
	public static function render( $text, $vars ) {
		if ( empty( $vars ) || ! is_array( $vars ) ) {
			return (string) $text;
		}
		$search  = array();
		$replace = array();
		foreach ( $vars as $key => $val ) {
			$search[]  = '{' . $key . '}';
			$replace[] = (string) $val;
		}
		return str_replace( $search, $replace, (string) $text );
	}

	/**
	 * Resolve the company contact email for use as Reply-To.
	 *
	 * @param int|null $company_id
	 * @return string|null
	 */
	private function company_reply_to( $company_id ) {
		if ( ! $company_id ) {
			return null;
		}
		global $wpdb;
		$email = $wpdb->get_var( $wpdb->prepare(
			"SELECT contact_email FROM {$wpdb->prefix}" . Schema::COMPANIES . " WHERE company_id = %d",
			(int) $company_id
		) );
		return ( $email && is_email( $email ) ) ? $email : null;
	}

	/**
	 * Convert a plain-text body to minimal, safe HTML.
	 *
	 * @param string $body
	 * @return string
	 */
	private function to_html( $body ) {
		// If it already looks like HTML, pass through (escaped by author trust).
		if ( $body !== wp_strip_all_tags( $body ) ) {
			return $body;
		}
		$escaped = esc_html( $body );
		$escaped = make_clickable( $escaped );
		return nl2br( $escaped );
	}

	/**
	 * Write a row to the email log.
	 *
	 * @return void
	 */
	private function log( $company_id, $template_key, $recipient, $employee_id, $subject, $status, $error ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . Schema::EMAIL_LOG,
			array(
				'company_id'    => $company_id ? (int) $company_id : null,
				'template_key'  => $template_key,
				'recipient'     => $recipient,
				'employee_id'   => $employee_id ? (int) $employee_id : null,
				'subject'       => $subject ? mb_substr( $subject, 0, 255 ) : null,
				'status'        => $status,
				'error_message' => $error,
			)
		);
	}

	/**
	 * Send a test email to verify SMTP configuration.
	 *
	 * @param string $to
	 * @return bool
	 */
	public function send_test( $to ) {
		return $this->send_raw(
			$to,
			'【wellanc】SMTP設定テストメール',
			"このメールはwellancストレスチェックシステムのSMTP設定テストです。\n\nこのメールが届いていれば、メール配信設定は正常です。",
			null,
			'smtp_test'
		);
	}
}
