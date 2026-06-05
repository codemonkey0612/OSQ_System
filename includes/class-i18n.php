<?php
/**
 * Internationalization (i18n) loader.
 *
 * @package OSQ
 */

namespace OSQ;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class I18n
 *
 * Handles loading the plugin text domain for translations.
 */
class I18n {

	/**
	 * Initialize the loader.
	 *
	 * @return void
	 */
	public function init() {
		// Filter the locale to support language switching (higher priority).
		add_filter( 'locale', array( $this, 'determine_locale' ), 1 );
		
		// Handle language switching requests.
		add_action( 'init', array( $this, 'handle_language_switch' ), 5 );
		
		// Load textdomain after locale is determined.
		add_action( 'init', array( $this, 'load_textdomain' ), 20 );
	}

	/**
	 * Determine the locale based on cookie, query var, or browser detection.
	 *
	 * @param string $locale Current locale.
	 * @return string Modified locale.
	 */
	public function determine_locale( $locale ) {
		// The OSQ product is Japanese-only. Full English localization was never
		// completed, so we force Japanese here. This also neutralizes any stale
		// `osq_lang=en_US` cookie left over from the removed language toggle,
		// which would otherwise cause partial-English display.
		return 'ja';

		$original_locale = $locale;

		// Check for language cookie
		if ( isset( $_COOKIE['osq_lang'] ) ) {
			$cookie_locale = $_COOKIE['osq_lang'] === 'ja' ? 'ja' : 'en_US';
			return $cookie_locale;
		}

		// Check for explicit language switch via query parameter for current request
		if ( isset( $_GET['osq_lang'] ) ) {
			return $_GET['osq_lang'] === 'ja' ? 'ja' : 'en_US';
		}
		
		// Check for language cookie
		if ( isset( $_COOKIE['osq_lang'] ) ) {
			$cookie_locale = $_COOKIE['osq_lang'] === 'ja' ? 'ja' : 'en_US';
			
			// Debug logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'OSQ I18n: Using cookie locale: ' . $cookie_locale );
			}
			
			return $cookie_locale;
		}
		
		// Check for saved settings (second priority)
		$settings = get_option( 'osq_settings', array() );
		if ( ! empty( $settings['language'] ) ) {
			$setting_locale = $settings['language'] === 'ja' ? 'ja' : 'en_US';
			
			// Debug logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'OSQ I18n: Using saved setting locale: ' . $setting_locale );
			}
			
			return $setting_locale;
		}

		// Fallback to browser detection on first visit (for login page).
		if ( ! is_admin() && isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			$browser_lang = substr( $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2 );
			if ( 'ja' === $browser_lang ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'OSQ I18n: Using browser detection for Japanese' );
				}
				return 'ja';
			}
		}

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'OSQ I18n: Keeping original locale: ' . $original_locale );
		}

		return $locale;
	}

	/**
	 * Handle explicit language switch requests via URL.
	 *
	 * @return void
	 */
	public function handle_language_switch() {
		if ( isset( $_GET['osq_lang'] ) ) {
			$lang = $_GET['osq_lang'] === 'ja' ? 'ja' : 'en_US';
			setcookie( 'osq_lang', $lang, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
			
			// If we are on a dashboard transition, we might want to redirect to clean up URL
			// but for now just setting the cookie is enough as determine_locale reads $_GET.
		}
	}

	/**
	 * Compile PO to MO if needed.
	 */
	private function auto_compile_mo() {
		$po_file = OSQ_PLUGIN_DIR . 'languages/osq-stress-check-ja.po';
		$mo_file = OSQ_PLUGIN_DIR . 'languages/osq-stress-check-ja.mo';

		if ( ! file_exists( $po_file ) ) {
			return;
		}

		if ( ! file_exists( $mo_file ) || filemtime( $po_file ) > filemtime( $mo_file ) ) {
			if ( ! class_exists( 'PO' ) ) {
				require_once ABSPATH . WPINC . '/pomo/po.php';
			}
			if ( ! class_exists( 'MO' ) ) {
				require_once ABSPATH . WPINC . '/pomo/mo.php';
			}

			$po = new \PO();
			if ( $po->import_from_file( $po_file ) ) {
				$mo = new \MO();
				$mo->entries = $po->entries;
				$mo->set_headers( $po->headers );
				$mo->export_to_file( $mo_file );
			}
		}
	}

	/**
	 * Load the plugin text domain.
	 */
	public function load_textdomain() {
		// Auto-compile MO file from PO if needed (useful during development without WP-CLI)
		$this->auto_compile_mo();

		$result = load_plugin_textdomain(
			'osq-stress-check',
			false,
			dirname( plugin_basename( OSQ_PLUGIN_FILE ) ) . '/languages/'
		);
		
		// Debug: Log the current locale and load result
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'OSQ I18n: Current locale: ' . get_locale() . ', Load result: ' . ( $result ? 'SUCCESS' : 'FAILED' ) );
		}
	}
}
