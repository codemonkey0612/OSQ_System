<?php
/**
 * Unified Dashboard Handler (Virtual Page).
 *
 * Provides a single /osq-dashboard/ virtual page that is accessible to any
 * logged-in user with at least one OSQ capability. Sits between
 * PortalRouter (parse_request@5, template_include@98) and AdminUiHandler
 * (parse_request@10, template_include@99) in the hook priority chain.
 *
 * @package OSQ
 */

namespace OSQ\Auth;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UnifiedDashboardHandler
 *
 * Registers and serves the /osq-dashboard/ virtual page, enforces login and
 * capability checks, and enqueues all assets required by the unified dashboard
 * template.
 */
class UnifiedDashboardHandler {

	/**
	 * URL slug for the unified dashboard virtual page.
	 */
	const SLUG = 'osq-dashboard';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );

		// Priority 8 — between PortalRouter@5 and AdminUiHandler@10.
		add_action( 'parse_request', array( $this, 'parse_request' ), 8 );

		// Priority 97 — before PortalRouter@98.
		add_filter( 'template_include', array( $this, 'render' ), 97 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the osq_unified_route query variable.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'osq_unified_route';
		return $vars;
	}

	/**
	 * Detect requests for /osq-dashboard/ and set the query var.
	 *
	 * @param \WP $wp Current WordPress request object.
	 * @return void
	 */
	public function parse_request( $wp ) {
		if ( empty( $wp->request ) ) {
			return;
		}

		if ( trim( $wp->request, '/' ) === self::SLUG ) {
			$wp->query_vars['osq_unified_route'] = 'dashboard';
		}
	}

	/**
	 * Serve the unified dashboard template or redirect as appropriate.
	 *
	 * @param string $template Path to the current template.
	 * @return string
	 */
	public function render( $template ) {
		if ( get_query_var( 'osq_unified_route' ) !== 'dashboard' ) {
			return $template;
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/' . EmployeeUiHandler::LOGIN_SLUG . '/' ) );
			exit;
		}

		$has_access = (
			CapabilityMatrix::user_has( CapabilityMatrix::TAKE_TEST )
			|| CapabilityMatrix::user_has( CapabilityMatrix::VIEW_OWN_RESULTS )
			|| CapabilityMatrix::user_has( CapabilityMatrix::VIEW_INDIVIDUAL_RESPONSES )
			|| CapabilityMatrix::user_has( CapabilityMatrix::SUPPORT_HIGH_STRESS )
			|| CapabilityMatrix::user_has( CapabilityMatrix::MANAGE_EMPLOYEES )
			|| CapabilityMatrix::user_has( CapabilityMatrix::VIEW_GROUP_ANALYSIS )
			|| CapabilityMatrix::user_has( CapabilityMatrix::SYSTEM_CONFIG )
			|| CapabilityMatrix::user_has( CapabilityMatrix::MANAGE_ALL_COMPANIES )
		);

		if ( ! $has_access ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		return OSQ_PLUGIN_DIR . 'templates/auth/unified-dashboard.php';
	}

	/**
	 * Enqueue all scripts and styles required by the unified dashboard.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( get_query_var( 'osq_unified_route' ) !== 'dashboard' || ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'osq-admin-css', OSQ_PLUGIN_URL . 'assets/css/osq-admin.css', array(), OSQ_VERSION );

		// Chart.js for group analysis.
		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true );

		wp_enqueue_script( 'osq-admin-js', OSQ_PLUGIN_URL . 'assets/js/osq-admin.js', array( 'jquery', 'chart-js' ), OSQ_VERSION, true );
		wp_localize_script( 'osq-admin-js', 'osq_admin_vars', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'osq_admin_nonce' ),
			'i18n'     => array(
				'completed'           => esc_html__( '完了 (Completed)', 'osq-stress-check' ),
				'pending'             => esc_html__( '未完了 (Pending)', 'osq-stress-check' ),
				'no_employees'        => esc_html__( '従業員が見つかりません (No employees found.)', 'osq-stress-check' ),
				'loading'             => esc_html__( '従業員データを読み込み中... (Loading employee data...)', 'osq-stress-check' ),
				'selected_file'       => esc_html__( '選択されたファイル (Selected file)', 'osq-stress-check' ),
				'high_stress'         => esc_html__( 'High Stress Ratio', 'osq-stress-check' ),
				'scale_scores'        => esc_html__( 'Scale Average Scores', 'osq-stress-check' ),
				'analysis_loading'    => esc_html__( 'Loading group analysis...', 'osq-stress-check' ),
				'analysis_empty'      => esc_html__( 'No groups meet the minimum size requirement.', 'osq-stress-check' ),
				'participation_empty' => esc_html__( 'No participation data found.', 'osq-stress-check' ),
				'export_failed'       => esc_html__( 'CSV export failed.', 'osq-stress-check' ),
				'export_ready'        => esc_html__( 'Download CSV', 'osq-stress-check' ),
				'csv_uploading'       => esc_html__( 'CSVをアップロード中... (Uploading CSV...)', 'osq-stress-check' ),
				'csv_import_complete' => esc_html__( 'CSVインポートが完了しました (CSV import completed.)', 'osq-stress-check' ),
				'csv_import_failed'   => esc_html__( 'CSVインポートに失敗しました (CSV import failed.)', 'osq-stress-check' ),
				'csv_import_success'  => esc_html__( 'インポート済み (Imported)', 'osq-stress-check' ),
				'csv_import_skipped'  => esc_html__( 'スキップされました (Skipped)', 'osq-stress-check' ),
				'csv_import_errors'   => esc_html__( 'エラー (Errors)', 'osq-stress-check' ),
				'csv_error_details'   => esc_html__( 'エラー詳細: (Errors:)', 'osq-stress-check' ),
				'csv_error_more'      => esc_html__( 'and', 'osq-stress-check' ),
				'csv_error_more_items' => esc_html__( 'more', 'osq-stress-check' ),
				'csv_no_imports'      => esc_html__( 'No imported users.', 'osq-stress-check' ),
				'csv_delete'          => esc_html__( '削除 (Delete)', 'osq-stress-check' ),
				'csv_delete_failed'   => esc_html__( '削除に失敗しました (Delete failed.)', 'osq-stress-check' ),
				'csv_delete_confirm'  => esc_html__( 'この従業員を削除しますか？ (Delete this imported user?)', 'osq-stress-check' ),
				'org_labels'          => array(),
				'dash_overview'       => esc_html__( 'Overview', 'osq-stress-check' ),
				'dash_employees'      => esc_html__( 'Employees', 'osq-stress-check' ),
				'dash_import'         => esc_html__( 'CSV Import', 'osq-stress-check' ),
				'dash_analysis'       => esc_html__( 'Group Analysis', 'osq-stress-check' ),
				'dash_settings'       => esc_html__( 'Settings', 'osq-stress-check' ),
			),
		) );

		wp_enqueue_script( 'osq-officer-js', OSQ_PLUGIN_URL . 'assets/js/osq-officer.js', array( 'jquery' ), OSQ_VERSION, true );
		wp_localize_script( 'osq-officer-js', 'osq_officer_vars', array(
			'ajax_url'       => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'osq_officer_nonce' ),
			'password_nonce' => wp_create_nonce( 'osq_change_password_nonce' ),
			'i18n'           => array(
				'completed'                         => esc_html__( 'Completed', 'osq-stress-check' ),
				'pending'                           => esc_html__( 'Pending', 'osq-stress-check' ),
				'no_employees'                      => esc_html__( 'No employees found.', 'osq-stress-check' ),
				'loading'                           => esc_html__( 'Loading employee data...', 'osq-stress-check' ),
				'high_stress'                       => esc_html__( 'High Stress', 'osq-stress-check' ),
				'normal'                            => esc_html__( 'Normal', 'osq-stress-check' ),
				'download_pdf'                      => esc_html__( 'Download PDF', 'osq-stress-check' ),
				'label_name'                        => esc_html__( 'Name', 'osq-stress-check' ),
				'label_employee_id'                 => esc_html__( 'Employee ID', 'osq-stress-check' ),
				'label_organization'                => esc_html__( 'Organization', 'osq-stress-check' ),
				'label_completed_date'              => esc_html__( 'Completed Date', 'osq-stress-check' ),
				'label_answer'                      => esc_html__( 'Answer', 'osq-stress-check' ),
				'label_method1_results'             => esc_html__( 'Method 1 Results', 'osq-stress-check' ),
				'label_method2_results'             => esc_html__( 'Method 2 Results', 'osq-stress-check' ),
				'label_no_scoring'                  => esc_html__( 'No scoring data available', 'osq-stress-check' ),
				'label_total_score'                 => esc_html__( 'Total Score', 'osq-stress-check' ),
				'label_scale_scores'                => esc_html__( 'Scale Scores', 'osq-stress-check' ),
				'label_section'                     => esc_html__( 'Section', 'osq-stress-check' ),
				'label_section_a'                   => esc_html__( 'Section A', 'osq-stress-check' ),
				'label_section_b'                   => esc_html__( 'Section B', 'osq-stress-check' ),
				'label_section_c'                   => esc_html__( 'Section C', 'osq-stress-check' ),
				'label_section_d'                   => esc_html__( 'Section D', 'osq-stress-check' ),
				'label_section_a_total'             => esc_html__( 'Section A Total', 'osq-stress-check' ),
				'label_section_b_total'             => esc_html__( 'Section B Total', 'osq-stress-check' ),
				'label_section_c_total'             => esc_html__( 'Section C Total', 'osq-stress-check' ),
				'label_section_a_eval'              => esc_html__( 'Section A Eval', 'osq-stress-check' ),
				'label_section_b_eval'              => esc_html__( 'Section B Eval', 'osq-stress-check' ),
				'label_section_c_eval'              => esc_html__( 'Section C Eval', 'osq-stress-check' ),
				'label_high_stress'                 => esc_html__( 'High Stress', 'osq-stress-check' ),
				'label_yes'                         => esc_html__( 'Yes', 'osq-stress-check' ),
				'label_no'                          => esc_html__( 'No', 'osq-stress-check' ),
				'label_criterion_a'                 => esc_html__( 'Criterion A', 'osq-stress-check' ),
				'label_criterion_b'                 => esc_html__( 'Criterion B', 'osq-stress-check' ),
				'label_met'                         => esc_html__( 'Met', 'osq-stress-check' ),
				'label_not_met'                     => esc_html__( 'Not Met', 'osq-stress-check' ),
				'label_no_responses'                => esc_html__( 'No responses found.', 'osq-stress-check' ),
				'label_view_details'                => esc_html__( 'View Details', 'osq-stress-check' ),
				'label_follow_up'                   => esc_html__( 'Follow-up', 'osq-stress-check' ),
				'label_filter_by_organization'      => esc_html__( 'Filter by Organization', 'osq-stress-check' ),
				'label_execute'                     => esc_html__( 'Execute', 'osq-stress-check' ),
				'label_change_password'             => esc_html__( 'Change Password', 'osq-stress-check' ),
				'label_search_followups'            => esc_html__( 'Search follow-ups...', 'osq-stress-check' ),
				'label_employee'                    => esc_html__( 'Employee', 'osq-stress-check' ),
				'label_scheduled_date'              => esc_html__( 'Scheduled Date', 'osq-stress-check' ),
				'label_no_followup_data'            => esc_html__( 'No follow-up data found', 'osq-stress-check' ),
				'label_loading_followup'            => esc_html__( 'Loading follow-up data...', 'osq-stress-check' ),
				'label_edit'                        => esc_html__( 'Edit', 'osq-stress-check' ),
				'label_error_apply_filters'         => esc_html__( 'Error applying filters', 'osq-stress-check' ),
				'label_network_error_apply_filters' => esc_html__( 'Network error while applying filters', 'osq-stress-check' ),
				'label_server_error_try_again'      => esc_html__( 'A server error occurred. Please try again.', 'osq-stress-check' ),
				'label_updated_followup_statuses'   => esc_html__( 'Updated follow-up statuses', 'osq-stress-check' ),
				'org_labels'                        => array(),
			),
		) );

		wp_enqueue_script( 'osq-questionnaire-js', OSQ_PLUGIN_URL . 'assets/js/osq-questionnaire.js', array( 'jquery' ), OSQ_VERSION, true );
		wp_localize_script( 'osq-questionnaire-js', 'osq_vars', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'osq_questionnaire_nonce' ),
			'i18n'     => array(
				'complete_all'       => __( 'Please answer all questions.', 'osq-stress-check' ),
				'section_incomplete' => __( 'Please complete all questions in this section.', 'osq-stress-check' ),
			),
		) );

		wp_enqueue_script( 'osq-unified-js', OSQ_PLUGIN_URL . 'assets/js/osq-unified-dashboard.js', array( 'jquery' ), OSQ_VERSION, true );
	}
}
