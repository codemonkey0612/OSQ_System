<?php
/**
 * Plugin deactivation handler.
 *
 * @package OSQ
 */

namespace OSQ;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 *
 * Runs on plugin deactivation via register_deactivation_hook().
 * Only cleans up temporary data — user data and options are preserved
 * so the plugin can be re-activated without data loss.
 */
class Deactivator {

	/**
	 * Fired on plugin deactivation.
	 *
	 * @return void
	 */
	public static function deactivate() {
		self::clear_transients();
		self::clear_scheduled_events();
	}

	/**
	 * Remove all plugin-specific transients.
	 *
	 * @return void
	 */
	private static function clear_transients() {
		global $wpdb;

		// Delete all transients with the osq_ prefix.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			 WHERE option_name LIKE '_transient_osq_%'
			    OR option_name LIKE '_transient_timeout_osq_%'"
		);
	}

	/**
	 * Unschedule all plugin cron events.
	 *
	 * @return void
	 */
	private static function clear_scheduled_events() {
		$cron_hooks = array(
			'osq_cleanup_expired_sessions',
			'osq_cleanup_temp_files',
		);

		foreach ( $cron_hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( false !== $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}
}
