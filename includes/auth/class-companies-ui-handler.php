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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_loaded', array( $this, 'process_forms' ) );
		add_action( 'wp_ajax_osq_companies_save', array( $this, 'ajax_save_company' ) );
		add_action( 'wp_ajax_osq_companies_delete', array( $this, 'ajax_delete_company' ) );
		add_action( 'wp_ajax_osq_companies_switch', array( $this, 'ajax_switch_company' ) );
		add_action( 'wp_ajax_osq_companies_init_demo', array( $this, 'ajax_init_demo' ) );
		add_action( 'wp_ajax_osq_companies_reset_demo', array( $this, 'ajax_reset_demo' ) );
	}

	public function enqueue_assets() {
		if ( 'list' !== get_query_var( 'osq_companies_route' ) || ! is_user_logged_in() ) {
			return;
		}
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'osq-admin-css', OSQ_PLUGIN_URL . 'assets/css/osq-admin.css', array(), OSQ_VERSION );
		wp_enqueue_script( 'osq-admin-js', OSQ_PLUGIN_URL . 'assets/js/osq-admin.js', array( 'jquery' ), OSQ_VERSION, true );
		wp_localize_script( 'osq-admin-js', 'osq_admin_vars', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'osq_admin_nonce' ),
		) );
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
			'org_label_4'    => sanitize_text_field( $_POST['org_label_4'] ?? '' ) ?: null,
			'org_label_5'    => sanitize_text_field( $_POST['org_label_5'] ?? '' ) ?: null,
			'min_group_size' => absint( $_POST['min_group_size'] ?? 5 ),
			'is_active'      => absint( $_POST['is_active'] ?? 1 ),
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' );

		// Clear the org-label cache for this company so fresh data is served immediately.
		if ( $company_id ) {
			wp_cache_delete( 'osq_org_labels_' . $company_id );
		}

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
	 * AJAX: Create the wellancデモ company and seed demo data (idempotent).
	 */
	public function ajax_init_demo() {
		check_ajax_referer( 'osq_companies_ajax', 'nonce' );
		if ( ! CapabilityMatrix::user_has( CapabilityMatrix::MANAGE_ALL_COMPANIES ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		global $wpdb;
		$this->seed_demo_company( $wpdb );
		wp_send_json_success( array( 'message' => 'デモ企業を初期化しました。' ) );
	}

	/**
	 * AJAX: Reset (wipe + re-seed) the demo company data.
	 */
	public function ajax_reset_demo() {
		check_ajax_referer( 'osq_companies_ajax', 'nonce' );
		if ( ! CapabilityMatrix::user_has( CapabilityMatrix::MANAGE_ALL_COMPANIES ) ) {
			wp_send_json_error( 'Unauthorized', 403 );
		}
		global $wpdb;
		$demo = $wpdb->get_row( "SELECT company_id FROM {$wpdb->prefix}osq_companies WHERE is_demo = 1 LIMIT 1" );
		if ( $demo ) {
			$cid = (int) $demo->company_id;
			$wpdb->delete( $wpdb->prefix . \OSQ\Database\Schema::RESPONSES, array( 'company_id' => $cid ), array( '%d' ) );
			$wpdb->delete( $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES, array( 'company_id' => $cid ), array( '%d' ) );
		}
		$this->seed_demo_company( $wpdb );
		wp_send_json_success( array( 'message' => 'デモデータをリセットしました。' ) );
	}

	/**
	 * Create demo company if absent, then seed employees + responses.
	 */
	private function seed_demo_company( $wpdb ) {
		$table = $wpdb->prefix . \OSQ\Database\Schema::COMPANIES;

		$demo = $wpdb->get_row( "SELECT company_id FROM {$table} WHERE is_demo = 1 LIMIT 1" );
		if ( ! $demo ) {
			$wpdb->insert( $table, array(
				'company_name'   => 'wellancデモ',
				'company_slug'   => 'wellanc-demo',
				'org_label_1'    => '部署',
				'org_label_2'    => 'チーム',
				'org_label_3'    => null,
				'org_label_4'    => null,
				'org_label_5'    => null,
				'min_group_size' => 3,
				'is_active'      => 1,
				'is_demo'        => 1,
			), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d' ) );
			$demo_id = (int) $wpdb->insert_id;
		} else {
			$demo_id = (int) $demo->company_id;
		}

		$emp_table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$res_table = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;

		// Only seed if no employees exist for this demo company.
		$existing = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$emp_table} WHERE company_id = %d", $demo_id
		) );
		if ( $existing > 0 ) {
			return;
		}

		$orgs = array(
			array( '営業部', 'チームA' ),
			array( '営業部', 'チームB' ),
			array( '開発部', 'チームC' ),
		);
		$demo_responses = $this->build_demo_method_results();
		$emp_num = 1;

		foreach ( $orgs as $org_pair ) {
			for ( $i = 1; $i <= 5; $i++ ) {
				$emp_number = sprintf( 'DEMO%03d', $emp_num );
				$wpdb->insert( $emp_table, array(
					'employee_number' => $emp_number,
					'company_id'      => $demo_id,
					'name'            => 'デモ従業員' . $emp_num,
					'email'           => 'demo' . $emp_num . '@wellanc-demo.example',
					'organization_1'  => $org_pair[0],
					'organization_2'  => $org_pair[1],
				), array( '%s', '%d', '%s', '%s', '%s', '%s' ) );

				$inserted_emp_id = (int) $wpdb->insert_id;

				// 4 of 5 in each group get completed responses (12/15 total); last employee in each group is non-respondent.
				if ( $i <= 4 && $inserted_emp_id > 0 ) {
					$r = $demo_responses[ $emp_num % count( $demo_responses ) ];
					$wpdb->insert( $res_table, array(
						'employee_id'    => $inserted_emp_id,
						'company_id'     => $demo_id,
						'is_complete'    => 1,
						'method1_result' => maybe_serialize( $r['m1'] ),
						'method2_result' => maybe_serialize( $r['m2'] ),
						'response_data'  => maybe_serialize( array() ),
						'completed_at'   => current_time( 'mysql' ),
					), array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' ) );
				}
				$emp_num++;
			}
		}
	}

	/**
	 * Pre-built demo method results for seeding.
	 */
	private function build_demo_method_results() {
		$scales_high = array(
			'quantitative_demands' => 4, 'qualitative_demands' => 4, 'physical_workload' => 3,
			'interpersonal_stress' => 3, 'environment_stress' => 2, 'job_control' => 2,
			'skill_utilization' => 3, 'job_fit' => 2, 'reward' => 2,
			'vigor' => 2, 'irritability' => 4, 'fatigue' => 4,
			'anxiety' => 4, 'depression' => 4, 'physical_complaints' => 3,
			'supervisor_support' => 2, 'colleague_support' => 3, 'family_support' => 3,
		);
		$scales_normal = array(
			'quantitative_demands' => 2, 'qualitative_demands' => 2, 'physical_workload' => 2,
			'interpersonal_stress' => 2, 'environment_stress' => 3, 'job_control' => 3,
			'skill_utilization' => 4, 'job_fit' => 4, 'reward' => 4,
			'vigor' => 4, 'irritability' => 2, 'fatigue' => 2,
			'anxiety' => 2, 'depression' => 2, 'physical_complaints' => 1,
			'supervisor_support' => 4, 'colleague_support' => 4, 'family_support' => 4,
		);
		return array(
			array( 'm1' => array( 'is_high_stress' => true,  'total' => 75 ), 'm2' => array( 'is_high_stress' => true,  'eval_points' => $scales_high ) ),
			array( 'm1' => array( 'is_high_stress' => false, 'total' => 45 ), 'm2' => array( 'is_high_stress' => false, 'eval_points' => $scales_normal ) ),
			array( 'm1' => array( 'is_high_stress' => false, 'total' => 50 ), 'm2' => array( 'is_high_stress' => false, 'eval_points' => $scales_normal ) ),
			array( 'm1' => array( 'is_high_stress' => true,  'total' => 80 ), 'm2' => array( 'is_high_stress' => true,  'eval_points' => $scales_high ) ),
		);
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
