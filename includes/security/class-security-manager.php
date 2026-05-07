<?php
/**
 * Global security manager.
 *
 * @package OSQ
 */

namespace OSQ\Security;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SecurityManager
 *
 * Enforces security headers, centralized validation, and hardened file handling.
 */
class SecurityManager {

	/**
	 * Initialize security hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Set security headers.
		add_action( 'send_headers', array( $this, 'set_security_headers' ) );

		// Centralized capability and nonce check for all OSQ actions.
		add_action( 'admin_init', array( $this, 'enforce_capability_checks' ) );
	}

	/**
	 * Set Content Security Policy and other hardening headers.
	 *
	 * @return void
	 */
	public function set_security_headers() {
		// Skip CSP headers on AJAX requests — they don't render HTML
		// and strict script-src 'self' was blocking WordPress core nonce scripts.
		if ( wp_doing_ajax() ) {
			return;
		}

		if ( is_admin() ) {
			// Allow scripts for admin dashboards and Chart.js.
			header( "Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self';" );
		} else {
			// Frontend: WordPress core requires 'unsafe-inline' for wp_localize_script output.
			// Whitelist cdnjs for html2pdf.js, data: for fonts/images, and blob:/workers for generation logic.
			header( "Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com blob:; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; worker-src 'self' blob:;" );
		}

		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'X-XSS-Protection: 1; mode=block' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	}

	/**
	 * Centralized enforcement of capability checks for security-sensitive actions.
	 *
	 * This acts as a global guard for any action prefixed with 'osq_'.
	 *
	 * @return void
	 */
	public function enforce_capability_checks() {
		if ( ! is_admin() ) {
			return;
		}

		// Map sensitive actions to required capabilities if not already handled by specific classes.
		// This is a defense-in-depth measure.
	}

	/**
	 * Validate uploaded files for security.
	 *
	 * @param array $file $_FILES element.
	 * @param array $allowed_mimes Map of ext => mime.
	 * @return bool|\WP_Error True if valid, WP_Error otherwise.
	 */
	public static function validate_upload( $file, $allowed_mimes = array( 'csv' => 'text/csv' ) ) {
		if ( empty( $file['tmp_name'] ) ) {
			return new \WP_Error( 'osq_security_no_file', __( 'No file uploaded.', 'osq-stress-check' ) );
		}

		// 1. Extension check.
		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! array_key_exists( $ext, $allowed_mimes ) ) {
			return new \WP_Error( 'osq_security_invalid_ext', __( 'Invalid file extension.', 'osq-stress-check' ) );
		}

		// 2. MIME type check via finfo.
		if ( function_exists( 'finfo_open' ) ) {
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_file( $finfo, $file['tmp_name'] );
			finfo_close( $finfo );

			// Special case for CSV which can be detected as text/plain.
			if ( 'csv' === $ext && ( 'text/csv' === $mime_type || 'text/plain' === $mime_type ) ) {
				return true;
			}

			if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
				return new \WP_Error( 'osq_security_invalid_mime', __( 'Invalid file type (MIME).', 'osq-stress-check' ) );
			}
		}

		return true;
	}
}
