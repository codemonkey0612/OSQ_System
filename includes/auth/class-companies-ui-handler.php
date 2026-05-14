<?php
/**
 * Companies management UI handler (/osq-companies/).
 *
 * Phase 3a: wellanc super-admin only. Lists all tenant companies,
 * allows creating new ones, editing labels/settings, and switching
 * the active tenant context for cross-company testing.
 *
 * @package OSQ
 */

namespace OSQ\Auth;

use OSQ\Database\DbManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CompaniesUiHandler {

	const SLUG = 'osq-companies';

	public function init() {
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'parse_request', array( $this, 'parse_request' ) );
		add_filter( 'template_include', array( $this, 'load_template' ), 99 );
		add_action( 'wp_loaded', array( $this, 'process_forms' ) );
		add_action( 'wp_ajax_osq_companies_save', array( $this, 'ajax_save_company' ) );
		add_action( 'wp_ajax_osq_companies_delete', array( $this, 'ajax_delete_company' ) );
		add_action( 'wp_ajax_osq_companies_switch', array( $this, 'ajax_switch_company' ) );
	}

	public function register_query_vars( $vars ) {
		$vars[] = 'osq_companies_route';
		return $vars;
	}

	public function parse_request( $wp ) {
		if ( empty( $wp->request ) ) {
			return;
		}
		if ( self::SLUG === trim( $wp->request, '/' ) ) {
			$wp->query_vars['osq_companies_route'] = 'list';
		}
	}

	public function load_template( $template ) {
		if ( 'list' !== get_query_var( 'osq_companies_route' ) ) {
			return $template;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/' . EmployeeUiHandler::LOGIN_SLUG . '/' ) );
			exit;
		}

		if ( ! CapabilityMatrix::user_has( CapabilityMatrix::MANAGE_ALL_COMPANIES ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		$tpl = OSQ_PLUGIN_DIR . 'templates/auth/companies-dashboard.php';
		return file_exists( $tpl ) ? $tpl : $template;
	}

	/**
	 * Handle create-company form (non-AJAX fallback).
	 */
	public function process_forms() {
		if ( ! isset( $_POST['osq_companies_action'] ) ) {
			return;
		}
		if ( ! check_admin_referer( 'osq_companies_nonce', 'osq_companies_nonce' ) ) {
			wp_die( 'Security check failed.' );
		}
		if ( ! CapabilityMatrix::user_has( CapabilityMatrix::MANAGE_ALL_COMPANIES ) ) {
			wp_die( 'Unauthorized.' );
		}

		$action = sanitize_text_field( $_POST['osq_companies_action'] );

		if ( 'create' === $action ) {
			$this->handle_create();
		} elseif ( 'edit' === $action ) {
			$this->handle_edit();
		}

		wp_safe_redirect( home_url( '/' . self::SLUG . '/?saved=1' ) );
		exit;
	}

	private function handle_create() {
		global $wpdb;
		$name = sanitize_text_field( $_POST['company_name'] ?? '' );
		$slug = sanitize_title( $_POST['company_slug'] ?? $name );
		if ( ! $name || ! $slug ) {
			return;
		}
		$wpdb->insert(
			$wpdb->prefix . 'osq_companies',
			array(
				'company_name'  => $name,
				'company_slug'  => $slug,
				'org_label_1'   => sanitize_text_field( $_POST['org_label_1'] ?? '組織1' ),
				'org_label_2'   => sanitize_text_field( $_POST['org_label_2'] ?? '組織2' ),
				'org_label_3'   => sanitize_text_field( $_POST['org_label_3'] ?? '組織3' ),
				'min_group_size' => absint( $_POST['min_group_size'] ?? 5 ),
				'is_active'     => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);
	}

	private function handle_edit() {
		global $wpdb;
		$company_id = absint( $_POST['company_id'] ?? 0 );
		if ( ! $company_id ) {
			return;
		}
		$wpdb->update(
			$wpdb->prefix . 'osq_companies',
			array(
				'company_name'   => sanitize_text_field( $_POST['company_name'] ?? '' ),
				'company_slug'   => sanitize_title( $_POST['company_slug'] ?? '' ),
				'org_label_1'    => sanitize_text_field( $_POST['org_label_1'] ?? '組織1' ),
				'org_label_2'    => sanitize_text_field( $_POST['org_label_2'] ?? '組織2' ),
				'org_label_3'    => sanitize_text_field( $_POST['org_label_3'] ?? '組織3' ),
				'min_group_size' => absint( $_POST['min_group_size'] ?? 5 ),
				'is_active'      => absint( $_POST['is_active'] ?? 1 ),
			),
			array( 'company_id' => $company_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' ),
			array( '%d' )
		);
	}

	// ── AJAX ────────────────────────────────────────────────────────────────

	public function ajax_save_company() {
		check_ajax_referer( 'osq_companies_ajax', 'nonce' );
		if ( ! CapabilityMatrix::user_has( CapabilityMatrix::MANAGE_ALL_COMPANIES ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}

		global $wpdb;
		$table      = $wpdb->prefix . 'osq_companies';
		$company_id = absint( $_POST['company_id'] ?? 0 );
		$data       = array(
			'company_name'   => sanitize_text_field( $_POST['company_name'] ?? '' ),
			'company_slug'   => sanitize_title( $_POST['company_slug'] ?? '' ),
			'org_label_1'    => sanitize_text_field( $_POST['org_label_1'] ?? '組織1' ),
			'org_label_2'    => sanitize_text_field( $_POST['org_label_2'] ?? '組織2' ),
			'org_label_3'    => sanitize_text_field( $_POST['org_label_3'] ?? '組織3' ),
			'min_group_size' => absint( $_POST['min_group_size'] ?? 5 ),
			'is_active'      => absint( $_POST['is_active'] ?? 1 ),
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%d', '%d' );

		if ( $company_id ) {
			$wpdb->update( $table, $data, array( 'company_id' => $company_id ), $formats, array( '%d' ) );
			$new_id = $company_id;
		} else {
			if ( ! $data['company_name'] || ! $data['company_slug'] ) {
				wp_send_json_error( 'Name and slug are required.' );
			}
			$wpdb->insert( $table, $data, $formats );
			$new_id = $wpdb->insert_id;
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE company_id = %d", $new_id ) );
		wp_send_json_success( $row );
	}

	public function ajax_delete_company() {
		check_ajax_referer( 'osq_companies_ajax', 'nonce' );
		if ( ! CapabilityMatrix::user_has( CapabilityMatrix::MANAGE_ALL_COMPANIES ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		$company_id = absint( $_POST['company_id'] ?? 0 );
		if ( $company_id <= 1 ) {
			wp_send_json_error( 'Cannot delete the default wellanc company.' );
		}
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'osq_companies',
			array( 'is_active' => 0 ),
			array( 'company_id' => $company_id ),
			array( '%d' ),
			array( '%d' )
		);
		wp_send_json_success();
	}

	public function ajax_switch_company() {
		check_ajax_referer( 'osq_companies_ajax', 'nonce' );
		if ( ! CapabilityMatrix::user_has( CapabilityMatrix::MANAGE_ALL_COMPANIES ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		$company_id = absint( $_POST['company_id'] ?? 0 );
		// Store in session via transient (user-specific, 1 hour)
		set_transient( 'osq_super_admin_ctx_' . get_current_user_id(), $company_id, HOUR_IN_SECONDS );
		wp_send_json_success( array( 'company_id' => $company_id ) );
	}

	/**
	 * Get all companies for display.
	 *
	 * @return array
	 */
	public static function get_all_companies() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT c.*,
			 (SELECT COUNT(*) FROM {$wpdb->prefix}osq_employees e WHERE e.company_id = c.company_id) AS employee_count
			 FROM {$wpdb->prefix}osq_companies c
			 ORDER BY c.company_id ASC",
			ARRAY_A
		) ?: array();
	}
}
