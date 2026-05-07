<?php
/**
 * Request-level access control.
 *
 * @package OSQ
 */

namespace OSQ\Auth;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AccessControl
 * 
 * Middleware for enforcing capabilities on admin pages, AJAX, and REST.
 */
class AccessControl {

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'enforce_admin_page_security' ) );
		add_action( 'wp_ajax_osq_secure_action', array( $this, 'validate_ajax_request' ) );
	}

	/**
	 * Protect OSQ admin pages from unauthorized access.
	 *
	 * Blocks access if a user tries to access a page they don't have the capability for.
	 *
	 * @return void
	 */
	public function enforce_admin_page_security() {
		if ( ! is_admin() || ! isset( $_GET['page'] ) ) {
			return;
		}

		$page = sanitize_text_field( wp_unslash( $_GET['page'] ) );

		// Only handle OSQ pages.
		if ( strpos( $page, 'osq-' ) !== 0 ) {
			return;
		}

		// Map pages to capabilities.
		$security_map = array(
			'osq-individual-responses' => 'osq_view_individual_responses',
			'osq-support-interface'    => 'osq_support_high_stress',
			'osq-manage-employees'     => 'osq_manage_employees',
			'osq-csv-import'           => 'osq_manage_employees',
			'osq-group-analysis'       => 'osq_view_group_analysis',
			'osq-settings'             => 'osq_system_config',
		);

		if ( isset( $security_map[ $page ] ) && ! current_user_can( $security_map[ $page ] ) ) {
			wp_die(
				esc_html__( 'You do not have sufficient permissions to access this page.', 'osq-stress-check' ),
				esc_html__( 'Access Denied', 'osq-stress-check' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * Generic AJAX validation wrapper.
	 *
	 * @param string $capability Required capability.
	 * @param string $nonce_action Nonce action string.
	 * @return void
	 */
	public function validate_request( $capability, $nonce_action ) {
		// Nonce check.
		check_admin_referer( $nonce_action );

		// Capability check.
		if ( ! current_user_can( $capability ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'osq-stress-check' ) ), 403 );
		}
	}

	/**
	 * Filter for database queries to ensure General Admins don't see individual responses.
	 *
	 * Defense-in-depth utility.
	 *
	 * @param string $query SQL query.
	 * @return string Modified or original query.
	 */
	public function filter_sensitive_queries( $query ) {
		if ( current_user_can( 'osq_view_individual_responses' ) ) {
			return $query;
		}

		// If user is General Admin or other, and query touches osq_stress_responses.
		global $wpdb;
		$table = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;

		if ( stripos( $query, "FROM {$table}" ) !== false || stripos( $query, "JOIN {$table}" ) !== false ) {
			// This is a simplified guard; in a real app, you'd use a more robust parser 
			// or specific repository-level filters.
			return "SELECT 'PROTECTED' as warning LIMIT 0";
		}

		return $query;
	}
}
