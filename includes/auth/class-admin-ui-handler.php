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
		add_action( 'wp_ajax_osq_admin_import_csv', array( $this, 'ajax_import_csv' ) );
		add_action( 'wp_ajax_osq_admin_get_imported_users', array( $this, 'ajax_get_imported_users' ) );
		add_action( 'wp_ajax_osq_admin_delete_imported_user', array( $this, 'ajax_delete_imported_user' ) );
		add_action( 'wp_ajax_osq_admin_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_osq_admin_reset_all_data', array( $this, 'ajax_reset_all_data' ) );
		add_action( 'wp_ajax_osq_admin_delete_employee', array( $this, 'ajax_delete_employee' ) );
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
		$db = \OSQ\Plugin::get_instance()->db();
		$emp_table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$res_table = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;

		$total_employees = $wpdb->get_var( "SELECT COUNT(*) FROM {$emp_table}" );
		$total_responses = $wpdb->get_var( "SELECT COUNT(*) FROM {$res_table} WHERE is_complete = 1" );
		
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
		$emp_table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$res_table = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;

		$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
		$where_clause = '';

		if ( 'completed' === $status_filter ) {
			$where_clause = 'WHERE r.is_complete = 1';
		} elseif ( 'pending' === $status_filter ) {
			$where_clause = 'WHERE ( r.is_complete IS NULL OR r.is_complete = 0 )';
		}

		// Ensure UTF-8 encoding for Japanese text
		$wpdb->query("SET NAMES utf8mb4");

		$query = "
			SELECT e.employee_id, e.employee_number, e.name, e.organization_1, e.organization_2, 
			       r.is_complete, r.completed_at
			FROM {$emp_table} e
			LEFT JOIN {$res_table} r ON e.employee_id = r.employee_id
			{$where_clause}
			ORDER BY e.employee_id ASC
			LIMIT 100
		";

		$employees = $wpdb->get_results( $query );
		
		// Debug logging for Japanese text display
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $employees ) ) {
			error_log( 'OSQ Admin: First employee name: ' . $employees[0]->name . ' (Length: ' . strlen( $employees[0]->name ) . ', Encoding detected: ' . mb_detect_encoding( $employees[0]->name, 'UTF-8, SJIS, EUC-JP', true ) . ')' );
		}
		
		// Ensure proper encoding for Japanese text
		foreach ( $employees as &$employee ) {
			$employee->name = htmlspecialchars( $employee->name, ENT_QUOTES, 'UTF-8' );
			$employee->organization_1 = !empty( $employee->organization_1 ) ? htmlspecialchars( $employee->organization_1, ENT_QUOTES, 'UTF-8' ) : '';
			$employee->organization_2 = !empty( $employee->organization_2 ) ? htmlspecialchars( $employee->organization_2, ENT_QUOTES, 'UTF-8' ) : '';
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

		$org_level = isset( $_GET['org_level'] ) ? sanitize_key( wp_unslash( $_GET['org_level'] ) ) : 'organization_1';
		$org_level = $this->normalize_org_level( $org_level );

		$analyzer = new \OSQ\Analysis\GroupAnalyzer();
		$groups = $this->get_distinct_org_values( $org_level );

		$analysis_rows = array();
		$participation_rows = array();

		foreach ( $groups as $group_value ) {
			$filter = array( $org_level => $group_value );
			$safe_label = sanitize_text_field( $group_value );

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

			$participation = $this->get_completion_counts( $org_level, $group_value );
			$participation_rows[] = array(
				'group_label'     => $safe_label,
				'total'           => (int) $participation['total'],
				'completed'       => (int) $participation['completed'],
				'completion_rate' => (float) $participation['completion_rate'],
			);
		}

		wp_send_json_success( array(
			'org_level'      => $org_level,
			'analysis'       => $analysis_rows,
			'participation'  => $participation_rows,
			'min_group_size' => \OSQ\Analysis\GroupAnalyzer::MIN_GROUP_SIZE,
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

		$header = array(
			__( 'Group', 'osq-stress-check' ),
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
			$filter = array( $org_level => $group_value );
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
		$allowed = array( 'organization_1', 'organization_2', 'organization_3' );
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
		$emp_table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;

		$orgs = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT {$org_level} FROM {$emp_table} WHERE {$org_level} IS NOT NULL AND {$org_level} != %s ORDER BY {$org_level}",
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

		$emp_table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$res_table = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;

		$total_sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$emp_table} WHERE {$org_level} = %s",
			$group_value
		);
		$total = (int) $wpdb->get_var( $total_sql );

		$completed_sql = $wpdb->prepare(
			"SELECT COUNT(DISTINCT r.employee_id)
			 FROM {$res_table} r
			 INNER JOIN {$emp_table} e ON r.employee_id = e.employee_id
			 WHERE r.is_complete = 1 AND e.{$org_level} = %s",
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
	 * Redirect with error.
	 */
	private function redirect_back_with_error( $error_code ) {
		$login_url = home_url( '/' . self::LOGIN_SLUG . '/' );
		wp_safe_redirect( add_query_arg( 'osq_admin_error', $error_code, $login_url ) );
		exit;
	}
}
