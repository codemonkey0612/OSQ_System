<?php
/**
 * Uninstall handler for OSQ Stress Check System.
 *
 * Executed only when the user explicitly deletes the plugin from the
 * WordPress admin. Removes ALL plugin data including custom tables,
 * options, capabilities, and user meta.
 *
 * @package OSQ
 */

// Security: only run when triggered by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
|--------------------------------------------------------------------------
| Drop Custom Tables
|--------------------------------------------------------------------------
*/

global $wpdb;

$tables = array(
	$wpdb->prefix . 'osq_sessions',
	$wpdb->prefix . 'osq_stress_responses',
	$wpdb->prefix . 'osq_employees',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are hardcoded.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

/*
|--------------------------------------------------------------------------
| Remove Plugin Options
|--------------------------------------------------------------------------
*/

$options = array(
	'osq_plugin_version',
	'osq_db_version',
	'osq_settings',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

/*
|--------------------------------------------------------------------------
| Remove Custom Capabilities from All Roles
|--------------------------------------------------------------------------
*/

$capabilities = array(
	// Implementation Officer capabilities.
	'osq_view_individual_responses',
	'osq_view_pdfs',
	'osq_support_high_stress',
	// General Administrator capabilities.
	'osq_manage_employees',
	'osq_view_group_analysis',
	'osq_system_config',
	// Employee capabilities.
	'osq_take_test',
	'osq_view_own_results',
	'osq_download_own_pdf',
);

global $wp_roles;

if ( isset( $wp_roles ) ) {
	foreach ( $wp_roles->roles as $role_name => $role_info ) {
		$role = get_role( $role_name );
		if ( $role ) {
			foreach ( $capabilities as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}

/*
|--------------------------------------------------------------------------
| Clean Up User Meta
|--------------------------------------------------------------------------
*/

$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'osq_%'" );

/*
|--------------------------------------------------------------------------
| Clean Up Transients
|--------------------------------------------------------------------------
*/

$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_osq_%'
	    OR option_name LIKE '_transient_timeout_osq_%'"
);
