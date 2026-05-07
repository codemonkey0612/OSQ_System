<?php
/**
 * Implementation Officer UI Handler (Virtual Pages).
 *
 * @package OSQ
 */

namespace OSQ\Auth;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OfficerUiHandler
 *
 * Provides virtual pages for the Implementation Officer login and dashboard.
 */
class OfficerUiHandler {

	/**
	 * Slugs for the virtual routes.
	 */
	const LOGIN_SLUG     = 'osq-officer-login';
	const DASHBOARD_SLUG = 'osq-officer-dashboard';

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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_officer_ui_assets' ) );

		// Form submissions.
		add_action( 'wp_loaded', array( $this, 'process_officer_login' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_osq_officer_get_responses', array( $this, 'ajax_get_responses' ) );
		add_action( 'wp_ajax_nopriv_osq_officer_get_responses', array( $this, 'ajax_get_responses' ) );
		add_action( 'wp_ajax_osq_officer_get_pdf_html', array( $this, 'ajax_get_pdf_html' ) );
		add_action( 'wp_ajax_nopriv_osq_officer_get_pdf_html', array( $this, 'ajax_get_pdf_html' ) );
		add_action( 'wp_ajax_osq_officer_get_detailed_response', array( $this, 'ajax_get_detailed_response' ) );
		add_action( 'wp_ajax_nopriv_osq_officer_get_detailed_response', array( $this, 'ajax_get_detailed_response' ) );
		add_action( 'wp_ajax_osq_officer_update_follow_up', array( $this, 'ajax_update_follow_up' ) );
		add_action( 'wp_ajax_nopriv_osq_officer_update_follow_up', array( $this, 'ajax_update_follow_up' ) );
		add_action( 'wp_ajax_osq_officer_filter_employees', array( $this, 'ajax_filter_employees' ) );
		add_action( 'wp_ajax_nopriv_osq_officer_filter_employees', array( $this, 'ajax_filter_employees' ) );
		add_action( 'wp_ajax_osq_officer_get_org_filters', array( $this, 'ajax_get_org_filters' ) );
		add_action( 'wp_ajax_nopriv_osq_officer_get_org_filters', array( $this, 'ajax_get_org_filters' ) );
		add_action( 'wp_ajax_osq_officer_get_followup_tracking', array( $this, 'ajax_get_followup_tracking' ) );
		add_action( 'wp_ajax_nopriv_osq_officer_get_followup_tracking', array( $this, 'ajax_get_followup_tracking' ) );
		
		// Page titles.
		add_filter( 'document_title_parts', array( $this, 'override_page_title' ) );
	}

	/**
	 * AJAX: Get HTML for PDF generation.
	 */
	public function ajax_get_pdf_html() {
		check_ajax_referer( 'osq_officer_nonce', 'nonce' );

		if ( ! $this->is_osq_officer( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$employee_id = isset( $_POST['employee_id'] ) ? absint( $_POST['employee_id'] ) : 0;
		if ( ! $employee_id ) {
			wp_send_json_error( 'invalid_employee' );
		}

		$db       = \OSQ\Plugin::get_instance()->db();
		// We need to fetch the employee via a custom DB method if it doesn't exist.
		// Let's assume DbManager has get_employee() or we can query it manually if not.
		global $wpdb;
		$emp_table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$employee  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$emp_table} WHERE employee_id = %d", $employee_id ) );

		if ( ! $employee ) {
			wp_send_json_error( 'employee_not_found' );
		}

		$response = $db->get_response_by_employee( $employee_id );
		if ( ! $response || ! $response->is_complete ) {
			wp_send_json_error( 'incomplete_response' );
		}

		$is_high_stress = $response->is_high_stress_method1 || $response->is_high_stress_method2;
		$date_str       = date_i18n( get_option( 'date_format' ), strtotime( $response->completed_at ?? 'now' ) );
		$site_name      = get_bloginfo( 'name' );
		
		$high_stress_text = $is_high_stress ? esc_html__( 'High Stress Detected', 'osq-stress-check' ) : esc_html__( 'Normal Stress Levels', 'osq-stress-check' );
		$high_stress_desc = $is_high_stress ? esc_html__( 'Your results indicate high stress. We recommend a consultation with a physician.', 'osq-stress-check' ) : esc_html__( 'Your results do not indicate high stress at this time.', 'osq-stress-check' );
		$color            = $is_high_stress ? '#d63638' : '#1e7e34';

		ob_start();
		?>
		<div style="padding: 40px; font-family: 'Hiragino Kaku Gothic Pro', 'Meiryo', sans-serif; color: #333; line-height: 1.6;">
			<div style="text-align: center; border-bottom: 2px solid #007cba; padding-bottom: 20px; margin-bottom: 30px;">
				<h1 style="color: #007cba; margin: 0; font-size: 24px;"><?php esc_html_e( 'Stress Check Results Report', 'osq-stress-check' ); ?></h1>
				<p style="margin: 5px 0 0; color: #666;"><?php esc_html_e( 'Results Report (Japanese: ストレスチェック結果報告書)', 'osq-stress-check' ); ?></p>
			</div>

			<table style="width: 100%; margin-bottom: 30px; border-collapse: collapse;">
				<tr>
					<td style="vertical-align: top; padding: 0;">
						<p style="margin: 0 0 6px;"><strong><?php esc_html_e( 'Name:', 'osq-stress-check' ); ?></strong> <?php echo esc_html( $employee->name ?? '' ); ?></p>
						<p style="margin: 0;"><strong><?php esc_html_e( 'Employee ID:', 'osq-stress-check' ); ?></strong> <?php echo esc_html( $employee->employee_number ?? '' ); ?></p>
					</td>
					<td style="text-align: right; vertical-align: top; padding: 0;">
						<p style="margin: 0;"><strong><?php esc_html_e( 'Date:', 'osq-stress-check' ); ?></strong> <?php echo esc_html( $date_str ); ?></p>
					</td>
				</tr>
			</table>

			<div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 30px; border-left: 5px solid <?php echo $color; ?>;">
				<h2 style="margin-top: 0; font-size: 18px; color: <?php echo $color; ?>;">
					<?php esc_html_e( 'Overall Determination', 'osq-stress-check' ); ?>
				</h2>
				<p style="font-size: 20px; font-weight: bold; margin-bottom: 10px;">
					<?php echo $high_stress_text; ?>
				</p>
				<p style="margin: 0; color: #555;">
					<?php echo $high_stress_desc; ?>
				</p>
			</div>

			<div style="margin-bottom: 30px;">
				<h3 style="border-bottom: 1px solid #eee; padding-bottom: 10px;"><?php esc_html_e( 'Scoring Details', 'osq-stress-check' ); ?></h3>
				<table style="width: 100%; border-collapse: collapse;">
					<tr>
						<th style="text-align: left; padding: 10px; border-bottom: 1px solid #eee;"><?php esc_html_e( 'Method', 'osq-stress-check' ); ?></th>
						<th style="text-align: center; padding: 10px; border-bottom: 1px solid #eee;"><?php esc_html_e( 'Score', 'osq-stress-check' ); ?></th>
						<th style="text-align: right; padding: 10px; border-bottom: 1px solid #eee;"><?php esc_html_e( 'Determination', 'osq-stress-check' ); ?></th>
					</tr>
					<tr>
						<td style="padding: 10px; border-bottom: 1px solid #eee;"><?php esc_html_e( 'Method 1 (Total Points)', 'osq-stress-check' ); ?></td>
						<td style="text-align: center; padding: 10px; border-bottom: 1px solid #eee;"><?php
							$m1_data  = $this->normalize_scoring_result( $response->method1_result ?? null );
							$m1_score = isset( $m1_data['section_b_total'] ) ? $m1_data['section_b_total'] : '-';
							echo esc_html( $m1_score );
						?></td>
						<td style="text-align: right; padding: 10px; border-bottom: 1px solid #eee; color: <?php echo $response->is_high_stress_method1 ? '#d63638' : '#1e7e34'; ?>;">
							<?php echo $response->is_high_stress_method1 ? esc_html__( 'High', 'osq-stress-check' ) : esc_html__( 'Normal', 'osq-stress-check' ); ?>
						</td>
					</tr>
					<tr>
						<td style="padding: 10px; border-bottom: 1px solid #eee;"><?php esc_html_e( 'Method 2 (Scale Specific)', 'osq-stress-check' ); ?></td>
						<td style="text-align: center; padding: 10px; border-bottom: 1px solid #eee;"><?php
							$m2_data  = $this->normalize_scoring_result( $response->method2_result ?? null );
							$m2_score = isset( $m2_data['section_b_eval'] ) ? $m2_data['section_b_eval'] : '-';
							echo esc_html( $m2_score );
						?></td>
						<td style="text-align: right; padding: 10px; border-bottom: 1px solid #eee; color: <?php echo $response->is_high_stress_method2 ? '#d63638' : '#1e7e34'; ?>;">
							<?php echo $response->is_high_stress_method2 ? esc_html__( 'High', 'osq-stress-check' ) : esc_html__( 'Normal', 'osq-stress-check' ); ?>
						</td>
					</tr>
				</table>
			</div>

			<div style="font-size: 12px; color: #999; margin-top: 50px; text-align: center; border-top: 1px solid #eee; padding-top: 20px;">
				<p><?php printf( esc_html__( 'Generated by %s (Implementation Officer Portal)', 'osq-stress-check' ), esc_html( $site_name ) ); ?></p>
			</div>
		</div>
		<?php
		$html = ob_get_clean();

		wp_send_json_success( array(
			'html'     => $html,
			'filename' => 'stress-check-results-' . $employee->employee_number
		) );
	}

	/**
	 * AJAX: Get individual responses for the dashboard.
	 */
	public function ajax_get_responses() {
		check_ajax_referer( 'osq_officer_nonce', 'nonce' );

		if ( ! $this->is_osq_officer( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		global $wpdb;
		$emp_table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$res_table = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;

		// Ensure UTF-8 encoding for Japanese text
		$wpdb->query("SET NAMES utf8mb4");

		$query = "
			SELECT e.employee_id, e.employee_number, e.name, e.organization_1, e.organization_2, 
			       r.is_complete, r.completed_at, r.is_high_stress_method1, r.is_high_stress_method2
			FROM {$emp_table} e
			LEFT JOIN {$res_table} r ON e.employee_id = r.employee_id
			ORDER BY e.employee_id ASC
			LIMIT 500
		";

		$employees = $wpdb->get_results( $query );
		
		// Log query results for debugging
		error_log('OSQ Officer: Found ' . count($employees) . ' employees');
		if (count($employees) > 0) {
			error_log('OSQ Officer: First employee - ID: ' . $employees[0]->employee_id . ', Name: ' . $employees[0]->name);
		}
		
		// Ensure proper encoding for Japanese text and formatting
		foreach ( $employees as &$employee ) {
			$employee->name = htmlspecialchars( $employee->name, ENT_QUOTES, 'UTF-8' );
			$employee->organization_1 = !empty( $employee->organization_1 ) ? htmlspecialchars( $employee->organization_1, ENT_QUOTES, 'UTF-8' ) : '';
			$employee->organization_2 = !empty( $employee->organization_2 ) ? htmlspecialchars( $employee->organization_2, ENT_QUOTES, 'UTF-8' ) : '';
			$employee->completed_at = !empty( $employee->completed_at ) ? date_i18n( get_option( 'date_format' ), strtotime( $employee->completed_at ) ) : '';
			
			// Determine high stress status
			$employee->is_high_stress = ( !empty($employee->is_high_stress_method1) || !empty($employee->is_high_stress_method2) ) ? 1 : 0;
		}

		wp_send_json_success( array(
			'employees' => $employees,
			'count' => count($employees)
		) );
	}

	/**
	 * AJAX: Get detailed 57-item response for a specific employee.
	 */
	public function ajax_get_detailed_response() {
		try {
			check_ajax_referer( 'osq_officer_nonce', 'nonce' );

			if ( ! $this->is_osq_officer( get_current_user_id() ) ) {
				wp_send_json_error( array( 'message' => 'unauthorized' ), 403 );
			}

			$employee_id = isset( $_POST['employee_id'] ) ? absint( $_POST['employee_id'] ) : 0;
			if ( ! $employee_id ) {
				wp_send_json_error( array( 'message' => 'invalid_employee' ), 400 );
			}

			global $wpdb;
			$emp_table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
			$res_table = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;

			// Get employee and response data
			$query = $wpdb->prepare( "
				SELECT e.*, r.response_data, r.completed_at, r.method1_result, r.method2_result
				FROM {$emp_table} e
				LEFT JOIN {$res_table} r ON e.employee_id = r.employee_id
				WHERE e.employee_id = %d
			", $employee_id );

			$data = $wpdb->get_row( $query );

			if ( ! $data || ! $data->response_data ) {
				wp_send_json_error( array( 'message' => 'no_response_data' ), 404 );
			}

			// Decode response data
			$db = \OSQ\Plugin::get_instance()->db();
			$response_data = $db->decrypt_data( $data->response_data, true );
			if ( ! is_array( $response_data ) ) {
				$fallback = json_decode( $data->response_data, true );
				if ( is_array( $fallback ) ) {
					$response_data = $fallback;
				}
			}
			if ( ! is_array( $response_data ) ) {
				wp_send_json_error( array( 'message' => 'invalid_response_format' ), 500 );
			}

			// Get question definitions for proper labeling
			if ( ! class_exists( '\\OSQ\\Questionnaire\\QuestionDefinitions' ) ) {
				wp_send_json_error( array( 'message' => 'question_definitions_missing' ), 500 );
			}
			$question_definitions = \OSQ\Questionnaire\QuestionDefinitions::get_questions();

			$question_map = array();
			foreach ( $question_definitions as $question_def ) {
				if ( isset( $question_def['id'] ) ) {
					$question_map[ $question_def['id'] ] = $question_def;
				}
			}

			// Format detailed response data
			$method1_result = $this->normalize_scoring_result( $data->method1_result ?? null );
			$method2_result = $this->normalize_scoring_result( $data->method2_result ?? null );

			$completed_at = '';
			if ( ! empty( $data->completed_at ) ) {
				$completed_at = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $data->completed_at ) );
			}

			$use_english = ( get_locale() === 'en_US' );
			if ( isset( $_COOKIE['osq_lang'] ) ) {
				$cookie_lang = sanitize_text_field( wp_unslash( $_COOKIE['osq_lang'] ) );
				$use_english = ( 'en_US' === $cookie_lang );
			}

			$detailed_response = array(
				'employee_info' => array(
					'employee_id' => $data->employee_id,
					'employee_number' => $data->employee_number,
					'name' => htmlspecialchars( $data->name, ENT_QUOTES, 'UTF-8' ),
					'organization_1' => htmlspecialchars( $data->organization_1 ?? '', ENT_QUOTES, 'UTF-8' ),
					'organization_2' => htmlspecialchars( $data->organization_2 ?? '', ENT_QUOTES, 'UTF-8' ),
					'completed_at' => $completed_at,
				),
				'responses' => array(),
				'scoring_results' => array(
					'method1' => $method1_result,
					'method2' => $method2_result,
				)
			);

			// Process each response with question definitions
			foreach ( $response_data as $question_key => $answer_value ) {
				$question_def = isset( $question_map[ $question_key ] ) ? $question_map[ $question_key ] : null;

				$question_text = $question_key;
				$section = 'unknown';
				if ( $question_def ) {
					$section = $question_def['section'] ?? 'unknown';
					$question_text = $use_english ? ( $question_def['text_en'] ?? $question_key ) : ( $question_def['text_ja'] ?? $question_key );
				}

				$detailed_response['responses'][] = array(
					'question_key' => $question_key,
					'question_text' => $question_text,
					'answer_value' => $answer_value,
					'answer_label' => $this->get_answer_label( $answer_value ),
					'category' => $section,
					'scale' => $section,
				);
			}

			wp_send_json_success( $detailed_response );
		} catch ( \Throwable $e ) {
			error_log( 'OSQ Officer Detailed Response Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( array( 'message' => 'server_error', 'detail' => $e->getMessage() ), 500 );
		}
	}

	/**
	 * AJAX: Update follow-up tracking status for an employee.
	 */
	public function ajax_update_follow_up() {
		check_ajax_referer( 'osq_officer_nonce', 'nonce' );

		if ( ! $this->is_osq_officer( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$employee_id = isset( $_POST['employee_id'] ) ? absint( $_POST['employee_id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';
		$scheduled_date = isset( $_POST['scheduled_date'] ) ? sanitize_text_field( wp_unslash( $_POST['scheduled_date'] ) ) : '';

		if ( ! $employee_id || ! $status ) {
			wp_send_json_error( 'missing_required_fields' );
		}

		global $wpdb;
		$follow_up_table = $wpdb->prefix . \OSQ\Database\Schema::FOLLOW_UP_TRACKING;

		if ( ! $this->ensure_followup_table_exists( $follow_up_table ) ) {
			wp_send_json_error( array(
				'message' => __( 'Follow-up table is missing. Please reactivate the plugin or run the database upgrade.', 'osq-stress-check' ),
			) );
		}

		// Check if follow-up record already exists
		$existing_record = $wpdb->get_row( $wpdb->prepare( 
			"SELECT * FROM {$follow_up_table} WHERE employee_id = %d AND officer_id = %d",
			$employee_id,
			get_current_user_id()
		) );

		$officer_id = get_current_user_id();
		$current_time = current_time( 'mysql' );

		if ( $existing_record ) {
			// Update existing record
			$update_data = array(
				'status' => $status,
				'notes' => $notes,
				'updated_at' => $current_time
			);

			if ( $scheduled_date ) {
				$update_data['scheduled_date'] = date( 'Y-m-d H:i:s', strtotime( $scheduled_date ) );
			}

			if ( $status === 'Completed' && ! $existing_record->completed_date ) {
				$update_data['completed_date'] = $current_time;
			}

			$where = array(
				'follow_up_id' => $existing_record->follow_up_id
			);

			$result = $wpdb->update( $follow_up_table, $update_data, $where );
		} else {
			// Create new record
			$insert_data = array(
				'employee_id' => $employee_id,
				'officer_id' => $officer_id,
				'status' => $status,
				'notes' => $notes,
				'created_at' => $current_time,
				'updated_at' => $current_time
			);

			if ( $scheduled_date ) {
				$insert_data['scheduled_date'] = date( 'Y-m-d H:i:s', strtotime( $scheduled_date ) );
			}

			if ( $status === 'Completed' ) {
				$insert_data['completed_date'] = $current_time;
			}

			$result = $wpdb->insert( $follow_up_table, $insert_data );
		}

		if ( $result !== false ) {
			wp_send_json_success( array(
				'message' => __( 'Follow-up status updated successfully.', 'osq-stress-check' ),
				'status' => $status
			) );
		} else {
			$last_error = $wpdb->last_error;
			if ( ! empty( $last_error ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'OSQ Follow-up Update Error: ' . $last_error );
			}
			wp_send_json_error( array(
				'message' => __( 'Failed to update follow-up status.', 'osq-stress-check' ),
				'detail'  => $last_error,
			) );
		}
	}

	/**
	 * AJAX: Get organization filter options.
	 */
	public function ajax_get_org_filters() {
		check_ajax_referer( 'osq_officer_nonce', 'nonce' );

		if ( ! $this->is_osq_officer( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		global $wpdb;
		$emp_table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;

		// Get unique organization values
		$query = "SELECT DISTINCT organization_1, organization_2 FROM {$emp_table} WHERE organization_1 IS NOT NULL ORDER BY organization_1";
		$organizations = $wpdb->get_results( $query );

		wp_send_json_success( array(
			'organizations' => $organizations,
		) );
	}

	/**
	 * AJAX: Get follow-up tracking data.
	 */
	public function ajax_get_followup_tracking() {
		check_ajax_referer( 'osq_officer_nonce', 'nonce' );

		if ( ! $this->is_osq_officer( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		global $wpdb;
		$follow_up_table = $wpdb->prefix . \OSQ\Database\Schema::FOLLOW_UP_TRACKING;
		$emp_table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;

		// Get follow-up records with employee information
		$query = "
			SELECT f.*, e.name as employee_name, e.employee_number
			FROM {$follow_up_table} f
			JOIN {$emp_table} e ON f.employee_id = e.employee_id
			WHERE f.officer_id = %d
			ORDER BY f.created_at DESC
		";

		$followups = $wpdb->get_results( $wpdb->prepare( $query, get_current_user_id() ) );

		// Log query results for debugging
		error_log('OSQ Officer: Found ' . count($followups) . ' follow-up records for officer ID: ' . get_current_user_id());
		
		// Format dates
		foreach ( $followups as &$followup ) {
			$followup->scheduled_date = !empty( $followup->scheduled_date ) ? date_i18n( get_option( 'date_format' ), strtotime( $followup->scheduled_date ) ) : '';
			$followup->completed_date = !empty( $followup->completed_date ) ? date_i18n( get_option( 'date_format' ), strtotime( $followup->completed_date ) ) : '';
		}

		wp_send_json_success( array(
			'followups' => $followups,
			'count' => count($followups)
		) );
	}

	/**
	 * AJAX: Filter employees based on organization and status.
	 */
	public function ajax_filter_employees() {
		check_ajax_referer( 'osq_officer_nonce', 'nonce' );

		if ( ! $this->is_osq_officer( get_current_user_id() ) ) {
			wp_send_json_error( 'unauthorized' );
		}

		$org_level_1 = isset( $_POST['org_level_1'] ) ? sanitize_text_field( wp_unslash( $_POST['org_level_1'] ) ) : '';
		$org_level_2 = isset( $_POST['org_level_2'] ) ? sanitize_text_field( wp_unslash( $_POST['org_level_2'] ) ) : '';
		$status = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';
		$search_term = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

		global $wpdb;
		$emp_table = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$res_table = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;

		// Build dynamic query
		$where_conditions = array();
		$query_params = array();

		if ( ! empty( $org_level_1 ) ) {
			$where_conditions[] = "e.organization_1 = %s";
			$query_params[] = $org_level_1;
		}

		if ( ! empty( $org_level_2 ) ) {
			$where_conditions[] = "e.organization_2 = %s";
			$query_params[] = $org_level_2;
		}

		if ( ! empty( $status ) ) {
			if ( $status === 'completed' ) {
				$where_conditions[] = "r.is_complete = 1";
			} elseif ( $status === 'pending' ) {
				$where_conditions[] = "(r.is_complete IS NULL OR r.is_complete = 0)";
			} elseif ( $status === 'high_stress' ) {
				$where_conditions[] = "(r.is_high_stress_method1 = 1 OR r.is_high_stress_method2 = 1)";
			}
		}

		if ( ! empty( $search_term ) ) {
			$where_conditions[] = "(e.name LIKE %s OR e.employee_number LIKE %s)";
			$query_params[] = "%{$search_term}%";
			$query_params[] = "%{$search_term}%";
		}

		$where_clause = '';
		if ( ! empty( $where_conditions ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );
		}

		$query = "
			SELECT e.employee_id, e.employee_number, e.name, e.organization_1, e.organization_2, 
			       r.is_complete, r.completed_at, r.is_high_stress_method1, r.is_high_stress_method2
			FROM {$emp_table} e
			LEFT JOIN {$res_table} r ON e.employee_id = r.employee_id
			{$where_clause}
			ORDER BY e.employee_id ASC
			LIMIT 500
		";

		// Prepare query with parameters if needed
		if ( ! empty( $query_params ) ) {
			$query = $wpdb->prepare( $query, $query_params );
		}

		$employees = $wpdb->get_results( $query );
		
		// Process results
		foreach ( $employees as &$employee ) {
			$employee->name = htmlspecialchars( $employee->name, ENT_QUOTES, 'UTF-8' );
			$employee->organization_1 = !empty( $employee->organization_1 ) ? htmlspecialchars( $employee->organization_1, ENT_QUOTES, 'UTF-8' ) : '';
			$employee->organization_2 = !empty( $employee->organization_2 ) ? htmlspecialchars( $employee->organization_2, ENT_QUOTES, 'UTF-8' ) : '';
			$employee->completed_at = !empty( $employee->completed_at ) ? date_i18n( get_option( 'date_format' ), strtotime( $employee->completed_at ) ) : '';
			
			// Determine high stress status
			$employee->is_high_stress = ( !empty($employee->is_high_stress_method1) || !empty($employee->is_high_stress_method2) ) ? 1 : 0;
		}

		wp_send_json_success( array(
			'employees' => $employees,
			'count' => count( $employees )
		) );
	}

	/**
	 * Register query variable.
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'osq_officer_page';
		return $vars;
	}

	/**
	 * Parse virtual requests.
	 */
	public function parse_virtual_requests( $wp ) {
		$request = trim( $wp->request, '/' );

		if ( self::LOGIN_SLUG === $request ) {
			$wp->set_query_var( 'osq_officer_page', 'login' );
		} elseif ( self::DASHBOARD_SLUG === $request ) {
			$wp->set_query_var( 'osq_officer_page', 'dashboard' );
		}
	}

	/**
	 * Load virtual templates.
	 */
	public function load_virtual_templates( $template ) {
		$officer_page = get_query_var( 'osq_officer_page' );

		if ( ! $officer_page ) {
			return $template;
		}

		if ( 'login' === $officer_page ) {
			if ( is_user_logged_in() && $this->is_osq_officer( get_current_user_id() ) ) {
				wp_safe_redirect( home_url( '/' . self::DASHBOARD_SLUG . '/' ) );
				exit;
			}
			$this->require_template( 'officer/officer-login.php' );
			exit;
		}

		if ( 'dashboard' === $officer_page ) {
			if ( ! is_user_logged_in() || ! $this->is_osq_officer( get_current_user_id() ) ) {
				wp_safe_redirect( home_url( '/' . self::LOGIN_SLUG . '/?osq_officer_error=unauthorized' ) );
				exit;
			}
			$this->require_template( 'officer/officer-dashboard.php' );
			exit;
		}

		return $template;
	}

	/**
	 * Enqueue assets.
	 */
	public function enqueue_officer_ui_assets() {
		$officer_page = get_query_var( 'osq_officer_page' );
		if ( ! $officer_page ) {
			return;
		}

		// Reuse admin CSS for the officer dashboard since the layout is similar
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'osq-admin-css', OSQ_PLUGIN_URL . 'assets/css/osq-admin.css', array(), OSQ_VERSION );
		
		if ( 'dashboard' === $officer_page ) {
			error_log('OSQ Officer: Enqueuing officer JS script');
			wp_enqueue_script( 'osq-officer-js', OSQ_PLUGIN_URL . 'assets/js/osq-officer.js', array( 'jquery' ), OSQ_VERSION, true );
			
			// Localize script with translated strings
			wp_localize_script( 'osq-officer-js', 'osq_officer_vars', array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'osq_officer_nonce' ),
				'password_nonce' => wp_create_nonce( 'osq_change_password_nonce' ),
				'i18n'           => array(
					'completed'      => esc_html__( 'Completed', 'osq-stress-check' ),
					'pending'        => esc_html__( 'Pending', 'osq-stress-check' ),
					'no_employees'   => esc_html__( 'No employees found.', 'osq-stress-check' ),
					'loading'        => esc_html__( 'Loading employee data...', 'osq-stress-check' ),
					'high_stress'    => esc_html__( 'High Stress', 'osq-stress-check' ),
					'normal'         => esc_html__( 'Normal', 'osq-stress-check' ),
					'download_pdf'   => esc_html__( 'Download PDF', 'osq-stress-check' ),
					'label_name'            => esc_html__( 'Name', 'osq-stress-check' ),
					'label_employee_id'     => esc_html__( 'Employee ID', 'osq-stress-check' ),
					'label_organization'    => esc_html__( 'Organization', 'osq-stress-check' ),
					'label_completed_date'  => esc_html__( 'Completed Date', 'osq-stress-check' ),
					'label_answer'          => esc_html__( 'Answer', 'osq-stress-check' ),
					'label_method1_results' => esc_html__( 'Method 1 Results', 'osq-stress-check' ),
					'label_method2_results' => esc_html__( 'Method 2 Results', 'osq-stress-check' ),
					'label_no_scoring'      => esc_html__( 'No scoring data available', 'osq-stress-check' ),
					'label_total_score'     => esc_html__( 'Total Score', 'osq-stress-check' ),
					'label_scale_scores'    => esc_html__( 'Scale Scores', 'osq-stress-check' ),
					'label_section'         => esc_html__( 'Section', 'osq-stress-check' ),
					'label_section_a'       => esc_html__( 'Section A', 'osq-stress-check' ),
					'label_section_b'       => esc_html__( 'Section B', 'osq-stress-check' ),
					'label_section_c'       => esc_html__( 'Section C', 'osq-stress-check' ),
					'label_section_d'       => esc_html__( 'Section D', 'osq-stress-check' ),
					'label_section_a_total' => esc_html__( 'Section A Total', 'osq-stress-check' ),
					'label_section_b_total' => esc_html__( 'Section B Total', 'osq-stress-check' ),
					'label_section_c_total' => esc_html__( 'Section C Total', 'osq-stress-check' ),
					'label_section_a_eval'  => esc_html__( 'Section A Eval', 'osq-stress-check' ),
					'label_section_b_eval'  => esc_html__( 'Section B Eval', 'osq-stress-check' ),
					'label_section_c_eval'  => esc_html__( 'Section C Eval', 'osq-stress-check' ),
					'label_high_stress'     => esc_html__( 'High Stress', 'osq-stress-check' ),
					'label_yes'             => esc_html__( 'Yes', 'osq-stress-check' ),
					'label_no'              => esc_html__( 'No', 'osq-stress-check' ),
					'label_criterion_a'     => esc_html__( 'Criterion A', 'osq-stress-check' ),
					'label_criterion_b'     => esc_html__( 'Criterion B', 'osq-stress-check' ),
					'label_met'             => esc_html__( 'Met', 'osq-stress-check' ),
					'label_not_met'         => esc_html__( 'Not Met', 'osq-stress-check' ),
					'label_no_responses'    => esc_html__( 'No responses found.', 'osq-stress-check' ),
					'label_view_details'    => esc_html__( 'View Details', 'osq-stress-check' ),
					'label_follow_up'       => esc_html__( 'Follow-up', 'osq-stress-check' ),
					'label_filter_by_organization' => esc_html__( 'Filter by Organization', 'osq-stress-check' ),
					'label_execute'         => esc_html__( 'Execute', 'osq-stress-check' ),
					'label_change_password' => esc_html__( 'Change Password', 'osq-stress-check' ),
					'label_search_followups' => esc_html__( 'Search follow-ups...', 'osq-stress-check' ),
					'label_employee'        => esc_html__( 'Employee', 'osq-stress-check' ),
					'label_scheduled_date'  => esc_html__( 'Scheduled Date', 'osq-stress-check' ),
					'label_no_followup_data' => esc_html__( 'No follow-up data found', 'osq-stress-check' ),
					'label_loading_followup' => esc_html__( 'Loading follow-up data...', 'osq-stress-check' ),
					'label_edit'            => esc_html__( 'Edit', 'osq-stress-check' ),
					'label_error_apply_filters' => esc_html__( 'Error applying filters', 'osq-stress-check' ),
					'label_network_error_apply_filters' => esc_html__( 'Network error while applying filters', 'osq-stress-check' ),
					'label_server_error_try_again' => esc_html__( 'A server error occurred. Please try again.', 'osq-stress-check' ),
					'label_updated_followup_statuses' => esc_html__( 'Updated follow-up statuses', 'osq-stress-check' ),
					'org_labels'            => array(
						'Customer Service' => esc_html__( 'Customer Service', 'osq-stress-check' ),
						'Engineering'      => esc_html__( 'Engineering', 'osq-stress-check' ),
						'Finance'          => esc_html__( 'Finance', 'osq-stress-check' ),
						'Human Resources'  => esc_html__( 'Human Resources', 'osq-stress-check' ),
						'Marketing'        => esc_html__( 'Marketing', 'osq-stress-check' ),
						'Operations'       => esc_html__( 'Operations', 'osq-stress-check' ),
						'Sales Department' => esc_html__( 'Sales Department', 'osq-stress-check' ),
					),
				),
			) );
		}
	}

	/**
	 * Process login.
	 */
	public function process_officer_login() {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['osq_officer_login_submit'] ) ) {
			return;
		}

		if ( ! isset( $_POST['osq_officer_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['osq_officer_login_nonce'] ) ), 'osq_officer_login_action' ) ) {
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

		if ( ! $this->is_osq_officer( $user->ID ) ) {
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
		$officer_page = get_query_var( 'osq_officer_page' );
		if ( ! $officer_page ) {
			return $title;
		}

		if ( 'login' === $officer_page ) {
			$title['title'] = __( 'Implementation Officer Login', 'osq-stress-check' );
		} elseif ( 'dashboard' === $officer_page ) {
			$title['title'] = __( 'Implementation Officer Dashboard', 'osq-stress-check' );
		}
		return $title;
	}

	/**
	 * Is user an OSQ Implementation Officer?
	 */
	private function is_osq_officer( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		
		// Allow full admins and Implementation Officers.
		if ( in_array( 'administrator', $user->roles, true ) || current_user_can( 'manage_options' ) ) {
			return true;
		}
		
		return in_array( RoleManager::IMPLEMENTATION_OFFICER, $user->roles, true );
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
			$dummy_post->post_name = 'osq-officer-page';
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
	 * Get human-readable answer label from answer value.
	 *
	 * @param int $answer_value The numerical answer value (1-4)
	 * @return string The translated answer label
	 */
	private function get_answer_label( $answer_value ) {
		switch ( $answer_value ) {
			case 1:
				return __( 'Very Much', 'osq-stress-check' );
			case 2:
				return __( 'Much', 'osq-stress-check' );
			case 3:
				return __( 'Somewhat', 'osq-stress-check' );
			case 4:
				return __( 'Not At All', 'osq-stress-check' );
			default:
				return __( 'No Answer', 'osq-stress-check' );
		}
	}

	/**
	 * Normalize scoring result payload (serialized or JSON).
	 *
	 * @param mixed $value
	 * @return array|null
	 */
	private function normalize_scoring_result( $value ) {
		if ( empty( $value ) ) {
			return null;
		}

		$decoded = maybe_unserialize( $value );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		if ( is_string( $decoded ) ) {
			$json = json_decode( $decoded, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $json ) ) {
				return $json;
			}
		}

		return null;
	}

	/**
	 * Ensure follow-up tracking table exists.
	 *
	 * @param string $table_name
	 * @return bool
	 */
	private function ensure_followup_table_exists( $table_name ) {
		global $wpdb;

		$exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
		if ( $exists ) {
			return true;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql_statements = \OSQ\Database\Schema::get_schema_sql();
		foreach ( $sql_statements as $sql ) {
			dbDelta( $sql );
		}

		$exists_after = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
		return (bool) $exists_after;
	}

	/**
	 * Redirect with error.
	 */
	private function redirect_back_with_error( $error_code ) {
		$login_url = home_url( '/' . self::LOGIN_SLUG . '/' );
		wp_safe_redirect( add_query_arg( 'osq_officer_error', $error_code, $login_url ) );
		exit;
	}
}
