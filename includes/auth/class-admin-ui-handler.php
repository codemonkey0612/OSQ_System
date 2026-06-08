<?php
/**
 * Administrator UI Handler (Virtual Pages).
 *
 * @package OSQ
 */

namespace OSQ\Auth;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminUiHandler
 *
 * Provides virtual pages for the admin login and dashboard.
 */
class AdminUiHandler {

	/**
	 * Slugs for the virtual routes.
	 */
	const LOGIN_SLUG     = 'osq-admin-login';
	const DASHBOARD_SLUG = 'osq-admin-dashboard';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Register query vars.
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );

		// Parse request.
		add_action( 'parse_request', array( $this, 'parse_virtual_requests' ) );

		// Load templates.
		add_filter( 'template_include', array( $this, 'load_virtual_templates' ), 99 );

		// Enqueue scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_ui_assets' ) );

		// Form submissions.
		add_action( 'wp_loaded', array( $this, 'process_admin_login' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_osq_admin_get_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_osq_admin_get_employees', array( $this, 'ajax_get_employees' ) );
		add_action( 'wp_ajax_osq_admin_get_group_analysis', array( $this, 'ajax_get_group_analysis' ) );
		add_action( 'wp_ajax_osq_admin_export_group_analysis_csv', array( $this, 'ajax_export_group_analysis_csv' ) );
		add_action( 'wp_ajax_osq_admin_get_org_report_data', array( $this, 'ajax_get_org_report_data' ) );
		add_action( 'wp_ajax_osq_admin_export_non_respondents', array( $this, 'ajax_export_non_respondents' ) );
		add_action( 'wp_ajax_osq_admin_import_csv', array( $this, 'ajax_import_csv' ) );
		add_action( 'wp_ajax_osq_admin_get_imported_users', array( $this, 'ajax_get_imported_users' ) );
		add_action( 'wp_ajax_osq_admin_delete_imported_user', array( $this, 'ajax_delete_imported_user' ) );
		add_action( 'wp_ajax_osq_admin_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_osq_admin_reset_all_data', array( $this, 'ajax_reset_all_data' ) );
		add_action( 'wp_ajax_osq_admin_delete_employee', array( $this, 'ajax_delete_employee' ) );
		add_action( 'wp_ajax_osq_admin_pregenerate_org_advice',   array( $this, 'ajax_pregenerate_org_advice' ) );
		add_action( 'wp_ajax_osq_admin_get_org_advice_status',    array( $this, 'ajax_get_org_advice_status' ) );
		add_action( 'wp_ajax_osq_admin_regenerate_org_advice',    array( $this, 'ajax_regenerate_org_advice' ) );
		add_action( 'wp_ajax_osq_admin_save_org_advice',          array( $this, 'ajax_save_org_advice' ) );
		add_action( 'wp_ajax_osq_admin_get_labor_report',         array( $this, 'ajax_get_labor_report' ) );
		add_action( 'wp_ajax_osq_admin_get_email_panel',          array( $this, 'ajax_get_email_panel' ) );
		add_action( 'wp_ajax_osq_admin_save_campaign',            array( $this, 'ajax_save_campaign' ) );
		add_action( 'wp_ajax_osq_admin_send_invitations',         array( $this, 'ajax_send_invitations' ) );
		add_action( 'wp_ajax_osq_admin_send_reminders_now',       array( $this, 'ajax_send_reminders_now' ) );
		add_action( 'wp_ajax_osq_compile_mo', array( $this, 'ajax_compile_mo' ) );
		add_action( 'wp_ajax_osq_test_openai_connection', array( $this, 'ajax_test_openai_connection' ) );

		// Page titles.
		add_filter( 'document_title_parts', array( $this, 'override_page_title' ) );
	}

	/**
	 * AJAX: Temporary MO Compiler
	 */
	public function ajax_compile_mo() {
		require_once ABSPATH . WPINC . '/pomo/entry.php';
		require_once ABSPATH . WPINC . '/pomo/translations.php';
		require_once ABSPATH . WPINC . '/pomo/streams.php';
		require_once ABSPATH . WPINC . '/pomo/po.php';
		require_once ABSPATH . WPINC . '/pomo/mo.php';
		$po_file = OSQ_PLUGIN_DIR . 'languages/osq-stress-check-ja.po';
		$mo_file = OSQ_PLUGIN_DIR . 'languages/osq-stress-check-ja.mo';
		$po = new \POMO_PO();
		$po->import_from_file( $po_file );
		$mo = new \MO();
		$mo->entries = $po->entries;
		$mo->set_headers( $po->headers );
		$mo->export_to_file( $mo_file );
		wp_send_json_success( "Compiled MO successfully" );
	}

	/**
	 * AJAX: Test OpenAI API connection.
	 */
	public function ajax_test_openai_connection() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => 'unauthorized' ) );
		}

		$client = new \OSQ\AI\OpenaiClient();
		$result = $client->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( '接続成功 / Connection successful', 'osq-stress-check' ) ) );
	}

	/**
	 * AJAX: Get system-wide statistics.
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		global $wpdb;
		$emp_table  = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$res_table  = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;
		$company_id = \OSQ\Database\DbManager::current_company_id();

		$total_employees = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$emp_table} WHERE company_id = %d",
			$company_id
		) );
		$total_responses = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$res_table} WHERE is_complete = 1 AND company_id = %d",
			$company_id
		) );

		$completion_rate = $total_employees > 0 ? round( ( $total_responses / $total_employees ) * 100, 1 ) : 0;
		$pending = $total_employees - $total_responses;

		wp_send_json_success( array(
			'total_employees' => $total_employees,
			'completion_rate' => $completion_rate,
			'pending'         => $pending,
		) );
	}

	/**
	 * AJAX: Get employee list.
	 */
	public function ajax_get_employees() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		global $wpdb;
		$emp_table  = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$res_table  = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;
		$company_id = \OSQ\Database\DbManager::current_company_id();

		$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';

		$where_parts = array( 'e.company_id = %d' );
		$values      = array( $company_id );

		if ( 'completed' === $status_filter ) {
			$where_parts[] = 'r.is_complete = 1';
		} elseif ( 'pending' === $status_filter ) {
			$where_parts[] = '( r.is_complete IS NULL OR r.is_complete = 0 )';
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where_parts );

		$query = $wpdb->prepare(
			"SELECT e.employee_id, e.employee_number, e.name, e.organization_1, e.organization_2, e.organization_3, e.organization_4, e.organization_5,
			        r.is_complete, r.completed_at
			 FROM {$emp_table} e
			 LEFT JOIN {$res_table} r ON e.employee_id = r.employee_id
			 {$where_sql}
			 ORDER BY e.employee_id ASC
			 LIMIT 100",
			$values
		);

		$employees = $wpdb->get_results( $query );

		foreach ( $employees as &$employee ) {
			$employee->name = htmlspecialchars( $employee->name, ENT_QUOTES, 'UTF-8' );
			$employee->organization_1 = !empty( $employee->organization_1 ) ? htmlspecialchars( $employee->organization_1, ENT_QUOTES, 'UTF-8' ) : '';
			$employee->organization_2 = !empty( $employee->organization_2 ) ? htmlspecialchars( $employee->organization_2, ENT_QUOTES, 'UTF-8' ) : '';
			$employee->organization_3 = !empty( $employee->organization_3 ) ? htmlspecialchars( $employee->organization_3, ENT_QUOTES, 'UTF-8' ) : '';
			$employee->organization_4 = !empty( $employee->organization_4 ) ? htmlspecialchars( $employee->organization_4, ENT_QUOTES, 'UTF-8' ) : '';
			$employee->organization_5 = !empty( $employee->organization_5 ) ? htmlspecialchars( $employee->organization_5, ENT_QUOTES, 'UTF-8' ) : '';
			$employee->completed_at = !empty( $employee->completed_at ) ? date_i18n( get_option( 'date_format' ), strtotime( $employee->completed_at ) ) : '';
		}

		wp_send_json_success( array(
			'employees' => $employees,
		) );
	}

	/**
	 * AJAX: Get group analysis and participation data by org level.
	 */
	public function ajax_get_group_analysis() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$org_level    = isset( $_GET['org_level'] ) ? sanitize_key( wp_unslash( $_GET['org_level'] ) ) : 'organization_1';
		$org_level    = $this->normalize_org_level( $org_level );
		$exclude_orgs = array();
		if ( ! empty( $_GET['exclude_orgs'] ) ) {
			$exclude_orgs = array_filter( array_map( 'sanitize_text_field', explode( ',', wp_unslash( $_GET['exclude_orgs'] ) ) ) );
		}
		$min_group_size = isset( $_GET['min_group_size'] ) && (int) $_GET['min_group_size'] >= 1
			? (int) $_GET['min_group_size']
			: 0;

		// Resolve the same effective threshold GroupAnalyzer will use, so participation
		// table and analysis table are always in sync.
		$effective_threshold = $this->resolve_effective_threshold( $min_group_size );

		$analyzer = new \OSQ\Analysis\GroupAnalyzer();
		$groups   = $this->get_distinct_org_values( $org_level );

		$analysis_rows      = array();
		$participation_rows = array();

		foreach ( $groups as $group_value ) {
			if ( in_array( $group_value, $exclude_orgs, true ) ) {
				continue;
			}
			$filter = array( $org_level => $group_value, 'axis' => $org_level );
			if ( $min_group_size ) {
				$filter['min_group_size'] = $min_group_size;
			}
			if ( ! empty( $exclude_orgs ) ) {
				$filter['exclude_orgs'] = $exclude_orgs;
			}
			$safe_label = sanitize_text_field( $group_value );

			$participation = $this->get_completion_counts( $org_level, $group_value );

			// Hide groups whose total headcount is below the threshold — they are too
			// small to be meaningful for either tracking or analysis.
			if ( (int) $participation['total'] < $effective_threshold ) {
				continue;
			}

			$participation_rows[] = array(
				'group_label'     => $safe_label,
				'total'           => (int) $participation['total'],
				'completed'       => (int) $participation['completed'],
				'completion_rate' => (float) $participation['completion_rate'],
			);

			// Analysis table only shows groups with enough *completed* responses (privacy rule).
			$analysis = $analyzer->analyze( $filter );
			if ( null !== $analysis ) {
				$analysis_rows[] = array(
					'group_label'       => $safe_label,
					'respondent_count'  => (int) $analysis['respondent_count'],
					'high_stress_count' => (int) $analysis['high_stress_count'],
					'high_stress_ratio' => (float) $analysis['high_stress_ratio'],
					'completion_rate'   => (float) $analysis['completion_rate'],
					'scale_averages'    => $analysis['scale_averages'],
				);
			}
		}

		wp_send_json_success( array(
			'org_level'      => $org_level,
			'analysis'       => $analysis_rows,
			'participation'  => $participation_rows,
			'min_group_size' => $effective_threshold,
		) );
	}

	/**
	 * AJAX: Export group analysis CSV by org level.
	 */
	public function ajax_export_group_analysis_csv() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$org_level = isset( $_GET['org_level'] ) ? sanitize_key( wp_unslash( $_GET['org_level'] ) ) : 'organization_1';
		$org_level = $this->normalize_org_level( $org_level );

		$exclude_orgs   = array();
		if ( ! empty( $_GET['exclude_orgs'] ) ) {
			$raw = sanitize_text_field( wp_unslash( $_GET['exclude_orgs'] ) );
			$exclude_orgs = array_filter( array_map( 'trim', explode( ',', $raw ) ) );
		}
		$min_group_size = isset( $_GET['min_group_size'] ) ? (int) $_GET['min_group_size'] : 0;

		$analyzer = new \OSQ\Analysis\GroupAnalyzer();
		$groups = $this->get_distinct_org_values( $org_level );
		$scale_labels = $this->get_scale_labels();

		$filename = sprintf( 'osq-group-analysis-%s-%s.csv', $org_level, gmdate( 'Ymd' ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			wp_die( esc_html__( 'Failed to export CSV.', 'osq-stress-check' ) );
		}

		$org_level_num = (int) str_replace( 'organization_', '', $org_level );
		$group_label   = \OSQ\Services\OrgLabelService::get_label( \OSQ\Database\DbManager::current_company_id(), $org_level_num );

		$header = array(
			$group_label,
			__( 'Respondents', 'osq-stress-check' ),
			__( 'High Stress Count', 'osq-stress-check' ),
			__( 'High Stress Ratio (%)', 'osq-stress-check' ),
			__( 'Completion Rate (%)', 'osq-stress-check' ),
		);

		foreach ( $scale_labels as $label ) {
			$header[] = $label;
		}

		fputcsv( $output, $header );

		foreach ( $groups as $group_value ) {
			$filter = array( $org_level => $group_value, 'axis' => $org_level );
			if ( ! empty( $exclude_orgs ) )   { $filter['exclude_orgs']   = $exclude_orgs; }
			if ( $min_group_size >= 1 )        { $filter['min_group_size'] = $min_group_size; }
			$analysis = $analyzer->analyze( $filter );
			if ( null === $analysis ) {
				continue;
			}

			$row = array(
				$group_value,
				(int) $analysis['respondent_count'],
				(int) $analysis['high_stress_count'],
				(float) $analysis['high_stress_ratio'],
				round( $analysis['completion_rate'] * 100, 1 ),
			);

			foreach ( array_keys( $scale_labels ) as $scale_key ) {
				$row[] = isset( $analysis['scale_averages'][ $scale_key ] ) ? $analysis['scale_averages'][ $scale_key ] : '';
			}

			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	/**
	 * AJAX: Get full org-analysis report data for PDF output.
	 * Returns rendered HTML (from org-report-pdf.php) + filename.
	 */
	public function ajax_get_org_report_data() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$org_level = isset( $_GET['org_level'] ) ? sanitize_key( wp_unslash( $_GET['org_level'] ) ) : 'organization_1';
		$org_level = $this->normalize_org_level( $org_level );

		$exclude_orgs = array();
		if ( ! empty( $_GET['exclude_orgs'] ) ) {
			$exclude_orgs = array_filter( array_map( 'sanitize_text_field', explode( ',', wp_unslash( $_GET['exclude_orgs'] ) ) ) );
		}
		$min_group_size = isset( $_GET['min_group_size'] ) && (int) $_GET['min_group_size'] >= 1
			? (int) $_GET['min_group_size']
			: 0;

		$analyzer = new \OSQ\Analysis\GroupAnalyzer();
		$groups   = $this->get_distinct_org_values( $org_level );

		$analysis_rows = array();
		foreach ( $groups as $group_value ) {
			if ( in_array( $group_value, $exclude_orgs, true ) ) {
				continue;
			}
			$filter = array( $org_level => $group_value, 'axis' => $org_level );
			if ( $min_group_size ) { $filter['min_group_size'] = $min_group_size; }
			if ( ! empty( $exclude_orgs ) ) { $filter['exclude_orgs'] = $exclude_orgs; }

			$result = $analyzer->analyze( $filter );
			if ( null !== $result ) {
				$analysis_rows[] = array(
					'group_label'       => sanitize_text_field( $group_value ),
					'respondent_count'  => (int) $result['respondent_count'],
					'high_stress_count' => (int) $result['high_stress_count'],
					'high_stress_ratio' => (float) $result['high_stress_ratio'],
					'scale_averages'    => $result['scale_averages'],
				);
			}
		}

		$chart_gen = new \OSQ\Analysis\ChartGenerator();
		$bar_chart = $chart_gen->get_bar_chart_data( $org_level );

		$company_id  = \OSQ\Database\DbManager::current_company_id();
		$org_level_n = (int) str_replace( 'organization_', '', $org_level );
		$axis_label  = \OSQ\Services\OrgLabelService::get_label( $company_id, $org_level_n );

		global $wpdb;
		$company_name = $wpdb->get_var( $wpdb->prepare(
			"SELECT company_name FROM {$wpdb->prefix}osq_companies WHERE company_id = %d",
			$company_id
		) ) ?: get_bloginfo( 'name' );

		$report_date = date_i18n( 'Y年m月d日' );
		$filename    = sprintf( 'osq-org-report-%s-%s', $org_level, gmdate( 'Ymd' ) );

		// Fetch cached AI advice for each group (Phase 4).
		$org_generator = new \OSQ\AI\OrgAdviceGenerator();
		$org_advice    = array(); // keyed by group_label => advice_text|null
		foreach ( $analysis_rows as $row ) {
			$cache = $org_generator->get_cache_row( $company_id, $org_level, $row['group_label'] );
			$org_advice[ $row['group_label'] ] = ( $cache && $cache->status === 'done' ) ? $cache->advice_text : null;
		}

		// Render template to string.
		ob_start();
		include OSQ_PLUGIN_DIR . 'templates/analysis/org-report-pdf.php';
		$html = ob_get_clean();

		wp_send_json_success( array(
			'html'     => $html,
			'filename' => $filename,
		) );
	}

	/**
	 * AJAX: Export non-respondents as UTF-8 BOM CSV (opens correctly in Japanese Excel).
	 */
	public function ajax_export_non_respondents() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		global $wpdb;
		$emp_table  = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$res_table  = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;
		$company_id = \OSQ\Database\DbManager::current_company_id();

		$where  = array( 'e.company_id = %d' );
		$values = array( $company_id );

		// Optional org filters (same params as employee list).
		foreach ( array( 'organization_1', 'organization_2', 'organization_3', 'organization_4', 'organization_5' ) as $col ) {
			if ( ! empty( $_GET[ $col ] ) ) {
				$where[]  = "e.{$col} = %s";
				$values[] = sanitize_text_field( wp_unslash( $_GET[ $col ] ) );
			}
		}

		$where_sql = implode( ' AND ', $where );

		$sql = $wpdb->prepare(
			"SELECT e.employee_id, e.name, e.email,
			        e.organization_1, e.organization_2, e.organization_3, e.organization_4, e.organization_5
			 FROM {$emp_table} e
			 LEFT JOIN {$res_table} r ON r.employee_id = e.employee_id AND r.is_complete = 1
			 WHERE {$where_sql} AND r.employee_id IS NULL
			 ORDER BY e.organization_1, e.organization_2, e.name",
			...$values
		);

		$rows = $wpdb->get_results( $sql );

		$org_labels = \OSQ\Services\OrgLabelService::get_all_labels( $company_id );

		$filename = sprintf( 'osq-non-respondents-%s.csv', gmdate( 'Ymd' ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );
		// UTF-8 BOM — required for Excel on Japanese Windows.
		fwrite( $output, "\xEF\xBB\xBF" );

		$header = array( '社員番号', '氏名', 'メールアドレス' );
		for ( $n = 1; $n <= 5; $n++ ) {
			$header[] = $org_labels[ $n ] ?? ( '組織' . $n );
		}
		fputcsv( $output, $header );

		foreach ( $rows as $row ) {
			fputcsv( $output, array(
				$row->employee_id,
				$row->name,
				$row->email,
				$row->organization_1 ?? '',
				$row->organization_2 ?? '',
				$row->organization_3 ?? '',
				$row->organization_4 ?? '',
				$row->organization_5 ?? '',
			) );
		}

		fclose( $output );
		exit;
	}

	/**
	 * AJAX: Save admin settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$settings = get_option( 'osq_settings', array() );
		
		// Update language setting
		if ( isset( $_POST['language'] ) ) {
			$language = sanitize_text_field( wp_unslash( $_POST['language'] ) );
			if ( in_array( $language, array( 'ja', 'en' ), true ) ) {
				$settings['language'] = $language;
			}
		}
		
		// Update session timeout
		if ( isset( $_POST['session_timeout'] ) ) {
			$timeout = absint( $_POST['session_timeout'] );
			$settings['session_timeout'] = max( 5, min( 120, $timeout ) );
		}

		// Update enable_group_analysis (always sent by JS as 0 or 1)
		if ( isset( $_POST['enable_group_analysis'] ) ) {
			$settings['enable_group_analysis'] = (bool) intval( $_POST['enable_group_analysis'] );
		} else {
			$settings['enable_group_analysis'] = false;
		}

		global $wpdb;
		$company_id     = \OSQ\Database\DbManager::current_company_id();
		$company_update = array();

		// Save min_group_size to osq_companies for the current tenant.
		if ( isset( $_POST['min_group_size'] ) ) {
			$company_update['min_group_size'] = max( 1, min( 100, absint( $_POST['min_group_size'] ) ) );
			wp_cache_delete( 'osq_org_labels_' . $company_id );
		}

		// Save physician name and HR contact fields.
		$text_company_fields = array( 'physician_name', 'contact_name', 'contact_phone' );
		foreach ( $text_company_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$company_update[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			}
		}
		if ( isset( $_POST['contact_email'] ) ) {
			$company_update['contact_email'] = sanitize_email( wp_unslash( $_POST['contact_email'] ) );
		}

		if ( ! empty( $company_update ) ) {
			$wpdb->update(
				$wpdb->prefix . \OSQ\Database\Schema::COMPANIES,
				$company_update,
				array( 'company_id' => $company_id )
			);
		}

		// Save settings — update_option returns false if value is unchanged,
		// so we can't rely on its return value alone to detect errors.
		update_option( 'osq_settings', $settings );

		wp_send_json_success( array(
			'message'  => __( '設定が保存されました。 (Settings saved successfully.)', 'osq-stress-check' ),
			'language' => $settings['language'] ?? 'ja',
		) );
	}

	/**
	 * AJAX: Import employees from CSV.
	 */
	public function ajax_import_csv() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( array( 'message' => 'unauthorized' ), 403 );
		}

		if ( empty( $_FILES['csv_file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No CSV file uploaded.', 'osq-stress-check' ) ), 400 );
		}

		$file = $_FILES['csv_file'];

		$validation = \OSQ\Security\SecurityManager::validate_upload( $file, array( 'csv' => 'text/csv' ) );
		if ( is_wp_error( $validation ) ) {
			wp_send_json_error( array( 'message' => $validation->get_error_message() ), 400 );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		$upload = wp_handle_upload( $file, array( 'test_form' => false ) );

		if ( isset( $upload['error'] ) ) {
			wp_send_json_error( array( 'message' => $upload['error'] ), 500 );
		}

		$file_path = $upload['file'] ?? '';
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Uploaded file not found.', 'osq-stress-check' ) ), 500 );
		}

		$importer = new \OSQ\Import\CsvImporter();
		$result = $importer->import( $file_path );

		@unlink( $file_path );

		$this->persist_imported_users( $result['added'] ?? array() );

		wp_send_json_success( array(
			'message' => __( 'CSV import completed.', 'osq-stress-check' ),
			'result'  => $result,
		) );
	}

	/**
	 * Register query variable.
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'osq_admin_page';
		return $vars;
	}

	/**
	 * Parse virtual requests.
	 */
	public function parse_virtual_requests( $wp ) {
		$request = trim( $wp->request, '/' );

		if ( self::LOGIN_SLUG === $request ) {
			$wp->set_query_var( 'osq_admin_page', 'login' );
		} elseif ( self::DASHBOARD_SLUG === $request ) {
			$wp->set_query_var( 'osq_admin_page', 'dashboard' );
		}
	}

	/**
	 * Load virtual templates.
	 */
	public function load_virtual_templates( $template ) {
		$admin_page = get_query_var( 'osq_admin_page' );

		if ( ! $admin_page ) {
			return $template;
		}

		if ( 'login' === $admin_page ) {
			if ( is_user_logged_in() && $this->is_osq_admin( get_current_user_id() ) ) {
				wp_safe_redirect( home_url( '/' . self::DASHBOARD_SLUG . '/' ) );
				exit;
			}
			$this->require_template( 'admin/admin-login.php' );
			exit;
		}

		if ( 'dashboard' === $admin_page ) {
			if ( ! is_user_logged_in() || ! $this->is_osq_admin( get_current_user_id() ) ) {
				wp_safe_redirect( home_url( '/' . self::LOGIN_SLUG . '/?osq_admin_error=unauthorized' ) );
				exit;
			}
			$this->require_template( 'admin/admin-dashboard.php' );
			exit;
		}

		return $template;
	}

	/**
	 * Enqueue assets.
	 */
	public function enqueue_admin_ui_assets() {
		$admin_page = get_query_var( 'osq_admin_page' );
		if ( ! $admin_page ) {
			return;
		}

		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'osq-admin-css', OSQ_PLUGIN_URL . 'assets/css/osq-admin.css', array(), OSQ_VERSION );
		
		if ( 'dashboard' === $admin_page ) {
			// Chart.js for group analysis.
			wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true );
			wp_enqueue_script( 'osq-admin-js', OSQ_PLUGIN_URL . 'assets/js/osq-admin.js', array( 'jquery', 'chart-js' ), OSQ_VERSION, true );
			
			// Localize script with translated strings
			wp_localize_script( 'osq-admin-js', 'osq_admin_vars', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'osq_admin_nonce' ),
				'i18n'     => array(
					'completed'      => esc_html__( '完了 (Completed)', 'osq-stress-check' ),
					'pending'        => esc_html__( '未完了 (Pending)', 'osq-stress-check' ),
					'no_employees'   => esc_html__( '従業員が見つかりません (No employees found.)', 'osq-stress-check' ),
					'loading'        => esc_html__( '従業員データを読み込み中... (Loading employee data...)', 'osq-stress-check' ),
					'selected_file'  => esc_html__( '選択されたファイル (Selected file)', 'osq-stress-check' ),
					'high_stress'    => esc_html__( 'High Stress Ratio', 'osq-stress-check' ),
					'scale_scores'   => esc_html__( 'Scale Average Scores', 'osq-stress-check' ),
					'analysis_loading' => esc_html__( 'Loading group analysis...', 'osq-stress-check' ),
					'analysis_empty'   => esc_html__( 'No groups meet the minimum size requirement.', 'osq-stress-check' ),
					'participation_empty' => esc_html__( 'No participation data found.', 'osq-stress-check' ),
					'export_failed'    => esc_html__( 'CSV export failed.', 'osq-stress-check' ),
					'export_ready'     => esc_html__( 'Download CSV', 'osq-stress-check' ),
					'csv_uploading'    => esc_html__( 'CSVをアップロード中... (Uploading CSV...)', 'osq-stress-check' ),
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
					'org_labels'          => array(
						'Customer Service' => esc_html__( 'Customer Service', 'osq-stress-check' ),
						'Engineering'      => esc_html__( 'Engineering', 'osq-stress-check' ),
						'Finance'          => esc_html__( 'Finance', 'osq-stress-check' ),
						'Human Resources'  => esc_html__( 'Human Resources', 'osq-stress-check' ),
						'Marketing'        => esc_html__( 'Marketing', 'osq-stress-check' ),
						'Operations'       => esc_html__( 'Operations', 'osq-stress-check' ),
						'Sales Department' => esc_html__( 'Sales Department', 'osq-stress-check' ),
					),
					'dash_overview'  => esc_html__( 'Overview', 'osq-stress-check' ),
					'dash_employees' => esc_html__( 'Employees', 'osq-stress-check' ),
					'dash_import'    => esc_html__( 'CSV Import', 'osq-stress-check' ),
					'dash_analysis'  => esc_html__( 'Group Analysis', 'osq-stress-check' ),
					'dash_settings'  => esc_html__( 'Settings', 'osq-stress-check' ),
				),
			) );
		}
	}

	/**
	 * Process login.
	 */
	public function process_admin_login() {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['osq_admin_login_submit'] ) ) {
			return;
		}

		if ( ! isset( $_POST['osq_admin_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['osq_admin_login_nonce'] ) ), 'osq_admin_login_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'osq-stress-check' ) );
		}

		$username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );
		$password = wp_unslash( $_POST['password'] ?? '' );

		if ( empty( $username ) || empty( $password ) ) {
			$this->redirect_back_with_error( 'empty_fields' );
		}

		// Check lockout BEFORE attempting authentication.
		if ( \OSQ\Auth\LoginManager::is_ip_locked_out() ) {
			$this->redirect_back_with_error( 'locked_out' );
		}

		$user = wp_signon( array(
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => true,
		), is_ssl() );

		if ( is_wp_error( $user ) ) {
			\OSQ\Auth\LoginManager::record_failed_attempt();
			$this->redirect_back_with_error( 'invalid_credentials' );
		}

		if ( ! $this->is_osq_admin( $user->ID ) ) {
			wp_logout();
			$this->redirect_back_with_error( 'invalid_role' );
		}

		wp_safe_redirect( home_url( '/' . self::DASHBOARD_SLUG . '/' ) );
		exit;
	}

	/**
	 * Override page title.
	 */
	public function override_page_title( $title ) {
		$admin_page = get_query_var( 'osq_admin_page' );
		if ( ! $admin_page ) {
			return $title;
		}

		if ( 'login' === $admin_page ) {
			$title['title'] = __( 'Admin Login', 'osq-stress-check' );
		} elseif ( 'dashboard' === $admin_page ) {
			$title['title'] = __( 'Admin Dashboard', 'osq-stress-check' );
		}
		return $title;
	}

	/**
	 * Is user an OSQ Administrator?
	 */
	private function is_osq_admin( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		
		// Allow full admins and General Administrators.
		if ( in_array( 'administrator', $user->roles, true ) || current_user_can( 'manage_options' ) ) {
			return true;
		}
		
		return in_array( RoleManager::GENERAL_ADMINISTRATOR, $user->roles, true );
	}

	/**
	 * Normalize org level to allowed columns.
	 *
	 * @param string $org_level
	 * @return string
	 */
	private function normalize_org_level( $org_level ) {
		$allowed = array( 'organization_1', 'organization_2', 'organization_3', 'organization_4', 'organization_5' );
		if ( ! in_array( $org_level, $allowed, true ) ) {
			return 'organization_1';
		}
		return $org_level;
	}

	/**
	 * Get distinct organization values for a column.
	 *
	 * @param string $org_level
	 * @return array
	 */
	private function get_distinct_org_values( $org_level ) {
		global $wpdb;
		$emp_table  = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$company_id = \OSQ\Database\DbManager::current_company_id();

		$orgs = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT {$org_level} FROM {$emp_table} WHERE company_id = %d AND {$org_level} IS NOT NULL AND {$org_level} != %s ORDER BY {$org_level}",
				$company_id,
				''
			)
		);

		return array_values( array_filter( $orgs ) );
	}

	/**
	 * Get completion counts for a group.
	 *
	 * @param string $org_level
	 * @param string $group_value
	 * @return array
	 */
	private function get_completion_counts( $org_level, $group_value ) {
		global $wpdb;

		$emp_table  = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$res_table  = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;
		$company_id = \OSQ\Database\DbManager::current_company_id();

		$total_sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$emp_table} WHERE company_id = %d AND {$org_level} = %s",
			$company_id,
			$group_value
		);
		$total = (int) $wpdb->get_var( $total_sql );

		$completed_sql = $wpdb->prepare(
			"SELECT COUNT(DISTINCT r.employee_id)
			 FROM {$res_table} r
			 INNER JOIN {$emp_table} e ON r.employee_id = e.employee_id
			 WHERE r.is_complete = 1 AND e.company_id = %d AND e.{$org_level} = %s",
			$company_id,
			$group_value
		);
		$completed = (int) $wpdb->get_var( $completed_sql );

		$rate = $total > 0 ? round( ( $completed / $total ), 4 ) : 0.0;

		return array(
			'total'           => $total,
			'completed'       => $completed,
			'completion_rate' => $rate,
		);
	}

	/**
	 * Resolve the effective min-group threshold, matching GroupAnalyzer::get_min_group_size().
	 * Priority: explicit override (>= 1) → per-tenant DB value → legal fallback constant.
	 *
	 * @param int $override User-supplied value, or 0 if not set.
	 * @return int
	 */
	private function resolve_effective_threshold( $override ) {
		if ( $override >= 1 ) {
			return $override;
		}
		global $wpdb;
		$val = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT min_group_size FROM {$wpdb->prefix}osq_companies WHERE company_id = %d",
			\OSQ\Database\DbManager::current_company_id()
		) );
		return $val >= 1 ? $val : \OSQ\Analysis\GroupAnalyzer::MIN_GROUP_SIZE;
	}

	/**
	 * Scale labels for CSV export.
	 *
	 * @return array
	 */
	private function get_scale_labels() {
		return array(
			'quantitative_demands' => __( 'Quantitative Demands', 'osq-stress-check' ),
			'qualitative_demands'  => __( 'Qualitative Demands', 'osq-stress-check' ),
			'physical_workload'    => __( 'Physical Workload', 'osq-stress-check' ),
			'interpersonal_stress' => __( 'Interpersonal', 'osq-stress-check' ),
			'environment_stress'   => __( 'Environment', 'osq-stress-check' ),
			'job_control'          => __( 'Job Control', 'osq-stress-check' ),
			'skill_utilization'    => __( 'Skill Use', 'osq-stress-check' ),
			'job_fit'              => __( 'Job Fit', 'osq-stress-check' ),
			'reward'               => __( 'Reward', 'osq-stress-check' ),
			'vigor'                => __( 'Vigor', 'osq-stress-check' ),
			'irritability'         => __( 'Irritability', 'osq-stress-check' ),
			'fatigue'              => __( 'Fatigue', 'osq-stress-check' ),
			'anxiety'              => __( 'Anxiety', 'osq-stress-check' ),
			'depression'           => __( 'Depression', 'osq-stress-check' ),
			'physical_complaints'  => __( 'Physical Complaints', 'osq-stress-check' ),
			'supervisor_support'   => __( 'Supervisor Support', 'osq-stress-check' ),
			'colleague_support'    => __( 'Colleague Support', 'osq-stress-check' ),
			'family_support'       => __( 'Family Support', 'osq-stress-check' ),
		);
	}

	/**
	 * AJAX: Get list of imported users with passwords.
	 */
	public function ajax_get_imported_users() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$records = get_option( 'osq_imported_users', array() );

		wp_send_json_success( array(
			'users' => is_array( $records ) ? $records : array(),
		) );
	}

	/**
	 * AJAX: Delete a previously imported user.
	 */
	public function ajax_delete_imported_user() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		if ( ! $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid user.', 'osq-stress-check' ) ) );
		}

		$records = get_option( 'osq_imported_users', array() );
		if ( ! is_array( $records ) ) {
			$records = array();
		}

		$found = false;
		foreach ( $records as $index => $record ) {
			if ( isset( $record['user_id'] ) && (int) $record['user_id'] === $user_id ) {
				$found = true;
				unset( $records[ $index ] );
				break;
			}
		}

		if ( ! $found ) {
			wp_send_json_error( array( 'message' => __( 'User not found in imported list.', 'osq-stress-check' ) ) );
		}

		$this->delete_employee_by_user_id( $user_id );
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );

		update_option( 'osq_imported_users', array_values( $records ) );

		wp_send_json_success( array(
			'message' => __( 'Imported user deleted.', 'osq-stress-check' ),
		) );
	}

	/**
	 * AJAX: Reset all employee and response data.
	 */
	public function ajax_reset_all_data() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		global $wpdb;
		$emp_table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$res_table = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;
		$ses_table = $wpdb->prefix . \OSQ\Database\Schema::SESSIONS;

		// 1. Delete associated WP Users first
		$records = get_option( 'osq_imported_users', array() );
		if ( is_array( $records ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			foreach ( $records as $record ) {
				$user_id = isset( $record['user_id'] ) ? absint( $record['user_id'] ) : 0;
				if ( $user_id > 0 ) {
					wp_delete_user( $user_id );
				}
			}
		}

		// 2. Truncate/Delete custom tables
		$wpdb->query( "DELETE FROM {$res_table}" );
		$wpdb->query( "DELETE FROM {$emp_table}" );
		$wpdb->query( "DELETE FROM {$ses_table}" );

		// 3. Reset auto-increments if supported
		$wpdb->query( "ALTER TABLE {$res_table} AUTO_INCREMENT = 1" );
		$wpdb->query( "ALTER TABLE {$emp_table} AUTO_INCREMENT = 1" );
		$wpdb->query( "ALTER TABLE {$ses_table} AUTO_INCREMENT = 1" );

		// 4. Clear the imported users list
		update_option( 'osq_imported_users', array() );

		wp_send_json_success( array(
			'message' => __( 'システムデータがリセットされました。 (System data has been reset.)', 'osq-stress-check' ),
		) );
	}

	/**
	 * AJAX: Delete individual employee.
	 */
	public function ajax_delete_employee() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );

		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$employee_id = isset( $_POST['employee_id'] ) ? absint( $_POST['employee_id'] ) : 0;
		if ( ! $employee_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid employee.', 'osq-stress-check' ) ) );
		}

		global $wpdb;
		$emp_table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$res_table = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;

		// Get WP User ID before deletion
		$employee = $wpdb->get_row( $wpdb->prepare( "SELECT wp_user_id FROM {$emp_table} WHERE employee_id = %d", $employee_id ) );
		
		if ( ! $employee ) {
			wp_send_json_error( array( 'message' => __( 'Employee not found.', 'osq-stress-check' ) ) );
		}

		// 1. Delete Response
		$wpdb->delete( $res_table, array( 'employee_id' => $employee_id ) );

		// 2. Delete Employee Record
		$wpdb->delete( $emp_table, array( 'employee_id' => $employee_id ) );

		// 3. Delete WP User (if exists and not current user)
		if ( $employee->wp_user_id && (int) $employee->wp_user_id !== (int) get_current_user_id() ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $employee->wp_user_id );
		}

		wp_send_json_success( array(
			'message' => __( '従業員データが削除されました。 (Employee deleted successfully.)', 'osq-stress-check' ),
		) );
	}

	/**
	 * Persist imported users list for admin display.
	 *
	 * @param array $added
	 * @return void
	 */
	private function persist_imported_users( $added ) {
		if ( empty( $added ) || ! is_array( $added ) ) {
			return;
		}

		$records = get_option( 'osq_imported_users', array() );
		if ( ! is_array( $records ) ) {
			$records = array();
		}

		foreach ( $added as $item ) {
			$user_id = 0;
			if ( ! empty( $item['number'] ) ) {
				$user = get_user_by( 'login', $item['number'] );
				if ( $user ) {
					$user_id = (int) $user->ID;
				}
			}

			$records[] = array(
				'user_id'         => $user_id,
				'employee_number' => sanitize_text_field( $item['number'] ?? '' ),
				'name'            => sanitize_text_field( $item['name'] ?? '' ),
				'password'        => sanitize_text_field( $item['password'] ?? '' ),
				'created_at'      => current_time( 'mysql' ),
			);
		}

		update_option( 'osq_imported_users', $records );
	}

	/**
	 * Delete employee record by WordPress user ID.
	 *
	 * @param int $user_id
	 * @return void
	 */
	private function delete_employee_by_user_id( $user_id ) {
		global $wpdb;
		$emp_table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$wpdb->delete( $emp_table, array( 'wp_user_id' => $user_id ) );
	}

	/**
	 * Helper to require template.
	 */
	private function require_template( $template_path ) {
		$full_path = OSQ_PLUGIN_DIR . 'templates/' . $template_path;
		if ( file_exists( $full_path ) ) {
			global $wp_query;
			$wp_query->is_page     = true;
			$wp_query->is_singular = true;
			
			$dummy_post = new \stdClass();
			$dummy_post->ID = 0;
			$dummy_post->post_author = 1;
			$dummy_post->post_date = current_time( 'mysql' );
			$dummy_post->post_date_gmt = current_time( 'mysql', 1 );
			$dummy_post->post_content = '';
			$dummy_post->post_title = '';
			$dummy_post->post_excerpt = '';
			$dummy_post->post_status = 'publish';
			$dummy_post->comment_status = 'closed';
			$dummy_post->ping_status = 'closed';
			$dummy_post->post_password = '';
			$dummy_post->post_name = 'osq-admin-page';
			$dummy_post->to_ping = '';
			$dummy_post->pinged = '';
			$dummy_post->post_modified = $dummy_post->post_date;
			$dummy_post->post_modified_gmt = $dummy_post->post_date_gmt;
			$dummy_post->post_content_filtered = '';
			$dummy_post->post_parent = 0;
			$dummy_post->guid = '';
			$dummy_post->menu_order = 0;
			$dummy_post->post_type = 'page';
			$dummy_post->post_mime_type = '';
			$dummy_post->comment_count = 0;
			$dummy_post->filter = 'raw';
			$wp_query->post = new \WP_Post( $dummy_post );
			$wp_query->queried_object = $wp_query->post;
			
			status_header( 200 );
			require $full_path;
		} else {
			wp_die( esc_html__( 'Template not found.', 'osq-stress-check' ) );
		}
	}

	/**
	 * AJAX: Enqueue org AI advice generation for all groups at a given level.
	 */
	public function ajax_pregenerate_org_advice() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );
		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$org_level  = sanitize_key( wp_unslash( $_POST['org_level'] ?? 'organization_1' ) );
		$company_id = \OSQ\Database\DbManager::current_company_id();
		$generator  = new \OSQ\AI\OrgAdviceGenerator();
		$count      = $generator->enqueue_all( $company_id, $org_level );

		wp_send_json_success( array( 'enqueued' => $count ) );
	}

	/**
	 * AJAX: Poll status of all org advice for a given level (used by JS polling).
	 */
	public function ajax_get_org_advice_status() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );
		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$org_level  = sanitize_key( wp_unslash( $_GET['org_level'] ?? 'organization_1' ) );
		$company_id = \OSQ\Database\DbManager::current_company_id();
		$generator  = new \OSQ\AI\OrgAdviceGenerator();
		$status_map = $generator->get_all_status( $company_id, $org_level );

		wp_send_json_success( $status_map );
	}

	/**
	 * AJAX: Regenerate org advice for one group (ignores is_edited flag).
	 */
	public function ajax_regenerate_org_advice() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );
		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$org_level  = sanitize_key( wp_unslash( $_POST['org_level'] ?? '' ) );
		$org_value  = sanitize_text_field( wp_unslash( $_POST['org_value'] ?? '' ) );
		$company_id = \OSQ\Database\DbManager::current_company_id();

		if ( ! $org_level || ! $org_value ) {
			wp_send_json_error( 'missing_params' );
		}

		$generator = new \OSQ\AI\OrgAdviceGenerator();
		$generator->regenerate( $company_id, $org_level, $org_value );

		wp_send_json_success( array( 'status' => 'pending' ) );
	}

	/**
	 * AJAX: Save inline-edited org advice text (sets is_edited=1).
	 */
	public function ajax_save_org_advice() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );
		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$org_level  = sanitize_key( wp_unslash( $_POST['org_level'] ?? '' ) );
		$org_value  = sanitize_text_field( wp_unslash( $_POST['org_value'] ?? '' ) );
		$advice     = sanitize_textarea_field( wp_unslash( $_POST['advice_text'] ?? '' ) );
		$company_id = \OSQ\Database\DbManager::current_company_id();

		if ( ! $org_level || ! $org_value ) {
			wp_send_json_error( 'missing_params' );
		}

		$generator = new \OSQ\AI\OrgAdviceGenerator();
		$ok        = $generator->save_edited( $company_id, $org_level, $org_value, $advice );

		$ok ? wp_send_json_success() : wp_send_json_error( 'save_failed' );
	}

	/**
	 * AJAX: Return labor inspection report data for the current company.
	 */
	public function ajax_get_labor_report() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );
		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		global $wpdb;
		$company_id  = \OSQ\Database\DbManager::current_company_id();
		$emp_table   = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$res_table   = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;
		$fup_table   = $wpdb->prefix . \OSQ\Database\Schema::FOLLOW_UP_TRACKING;
		$comp_table  = $wpdb->prefix . \OSQ\Database\Schema::COMPANIES;

		$start_date     = $wpdb->get_var( $wpdb->prepare(
			"SELECT MIN(created_at) FROM {$emp_table} WHERE company_id = %d", $company_id
		) );
		$total_employees = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$emp_table} WHERE company_id = %d", $company_id
		) );
		$respondents    = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$res_table} WHERE company_id = %d AND is_complete = 1", $company_id
		) );
		$high_stress    = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$res_table}
			 WHERE company_id = %d AND is_complete = 1
			   AND (is_high_stress_method1 = 1 OR is_high_stress_method2 = 1)", $company_id
		) );
		$interviews     = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$fup_table}
			 WHERE company_id = %d AND status = 'completed'", $company_id
		) );
		$company_row    = $wpdb->get_row( $wpdb->prepare(
			"SELECT physician_name FROM {$comp_table} WHERE company_id = %d", $company_id
		) );

		wp_send_json_success( array(
			'start_date'       => $start_date ? date_i18n( 'Y年m月d日', strtotime( $start_date ) ) : '—',
			'total_employees'  => $total_employees,
			'respondents'      => $respondents,
			'high_stress'      => $high_stress,
			'interviews'       => $interviews,
			'physician_name'   => $company_row->physician_name ?? '—',
		) );
	}

	/**
	 * AJAX: data for the email-distribution panel (campaign + invite template + counts).
	 */
	public function ajax_get_email_panel() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );
		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		global $wpdb;
		$company_id = \OSQ\Database\DbManager::current_company_id();

		$campaign = $wpdb->get_row( $wpdb->prepare(
			"SELECT start_date, deadline FROM {$wpdb->prefix}" . \OSQ\Database\Schema::SURVEY_CAMPAIGNS . "
			 WHERE company_id = %d AND is_active = 1 ORDER BY campaign_id DESC LIMIT 1",
			$company_id
		) );

		$tpl     = \OSQ\Email\EmailTemplateManager::get_template( \OSQ\Email\EmailTemplateManager::SURVEY_INVITE );

		$total_with_email = count( \OSQ\Email\ReminderScheduler::get_survey_recipients( $company_id ) );
		$non_respondents  = count( \OSQ\Email\ReminderScheduler::get_non_respondents( $company_id ) );

		wp_send_json_success( array(
			'start_date'       => $campaign->start_date ?? null,
			'deadline'         => $campaign->deadline ?? null,
			'invite_subject'   => $tpl['subject'],
			'invite_body'      => $tpl['body'],
			'total_with_email' => $total_with_email,
			'non_respondents'  => $non_respondents,
			'faq_url'          => \OSQ\Email\MailVars::faq_url(),
		) );
	}

	/**
	 * AJAX: save the campaign deadline (and ensure a campaign row exists).
	 */
	public function ajax_save_campaign() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );
		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}
		$deadline = sanitize_text_field( wp_unslash( $_POST['deadline'] ?? '' ) );
		$deadline = $deadline ? gmdate( 'Y-m-d H:i:s', strtotime( $deadline ) ) : null;
		$this->ensure_campaign( \OSQ\Database\DbManager::current_company_id(), null, $deadline );
		wp_send_json_success();
	}

	/**
	 * AJAX: send the (admin-edited) invitation to all employees with an email.
	 */
	public function ajax_send_invitations() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );
		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$subject  = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$body     = wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) );
		$deadline = sanitize_text_field( wp_unslash( $_POST['deadline'] ?? '' ) );
		$deadline = $deadline ? gmdate( 'Y-m-d H:i:s', strtotime( $deadline ) ) : null;

		if ( '' === $subject || '' === $body ) {
			wp_send_json_error( array( 'message' => '件名と本文を入力してください。' ) );
		}

		global $wpdb;
		$company_id = \OSQ\Database\DbManager::current_company_id();

		// Start the campaign (sets start_date now if not already set).
		$this->ensure_campaign( $company_id, current_time( 'mysql' ), $deadline );

		$employees = \OSQ\Email\ReminderScheduler::get_survey_recipients( $company_id );

		$mailer        = new \OSQ\Email\EmailService();
		$base          = \OSQ\Email\MailVars::company_base( $company_id );
		$deadline_disp = $deadline ? date_i18n( 'Y年m月d日', strtotime( $deadline ) ) : '—';
		$sent          = 0;

		foreach ( $employees as $emp ) {
			if ( ! is_email( $emp->email ) ) {
				continue;
			}
			$vars = array_merge( $base, array(
				'氏名'    => $emp->name,
				'受検URL' => \OSQ\Email\MailVars::survey_url(),
				'締切日'  => $deadline_disp,
			) );
			if ( $mailer->send_custom( $emp->email, $subject, $body, $vars, $company_id, \OSQ\Email\EmailTemplateManager::SURVEY_INVITE, (int) $emp->employee_id ) ) {
				$sent++;
			}
		}

		wp_send_json_success( array( 'sent' => $sent, 'total' => count( $employees ) ) );
	}

	/**
	 * AJAX: immediately send reminders to non-respondents.
	 */
	public function ajax_send_reminders_now() {
		check_ajax_referer( 'osq_admin_nonce', 'nonce' );
		if ( ! $this->is_osq_admin( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}
		global $wpdb;
		$company_id = \OSQ\Database\DbManager::current_company_id();
		$campaign   = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}" . \OSQ\Database\Schema::SURVEY_CAMPAIGNS . "
			 WHERE company_id = %d AND is_active = 1 ORDER BY campaign_id DESC LIMIT 1",
			$company_id
		) );
		if ( ! $campaign ) {
			$this->ensure_campaign( $company_id, current_time( 'mysql' ), null );
			$campaign = (object) array( 'deadline' => null );
		}
		$scheduler = new \OSQ\Email\ReminderScheduler();
		$sent      = $scheduler->send_reminders_for_company( $company_id, $campaign );
		wp_send_json_success( array( 'sent' => $sent ) );
	}

	/**
	 * Ensure an active campaign row exists for a company; update start/deadline.
	 *
	 * @param int         $company_id
	 * @param string|null $start_date Set start only if provided AND not already set.
	 * @param string|null $deadline
	 * @return void
	 */
	private function ensure_campaign( $company_id, $start_date, $deadline ) {
		global $wpdb;
		$table = $wpdb->prefix . \OSQ\Database\Schema::SURVEY_CAMPAIGNS;
		$row   = $wpdb->get_row( $wpdb->prepare(
			"SELECT campaign_id, start_date FROM {$table} WHERE company_id = %d AND is_active = 1 ORDER BY campaign_id DESC LIMIT 1",
			$company_id
		) );

		if ( $row ) {
			$update = array();
			if ( $start_date && empty( $row->start_date ) ) { $update['start_date'] = $start_date; }
			if ( null !== $deadline ) { $update['deadline'] = $deadline; }
			if ( ! empty( $update ) ) {
				$wpdb->update( $table, $update, array( 'campaign_id' => $row->campaign_id ) );
			}
		} else {
			$wpdb->insert( $table, array(
				'company_id' => $company_id,
				'start_date' => $start_date,
				'deadline'   => $deadline,
				'is_active'  => 1,
			) );
		}
	}

	/**
	 * Redirect with error.
	 */
	private function redirect_back_with_error( $error_code ) {
		$login_url = home_url( '/' . self::LOGIN_SLUG . '/' );
		wp_safe_redirect( add_query_arg( 'osq_admin_error', $error_code, $login_url ) );
		exit;
	}
}
