<?php
/**
 * Employee UI Handler (Virtual Pages).
 *
 * @package OSQ
 */

namespace OSQ\Auth;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EmployeeUiHandler
 *
 * Provides virtual pages for the login, dashboard, and questionnaire
 * without requiring shortcodes or physical pages.
 */
class EmployeeUiHandler {

	/**
	 * Slugs for the virtual routes.
	 */
	const LOGIN_SLUG         = 'osq-login';
	const DASHBOARD_SLUG     = 'osq-dashboard';
	const QUESTIONNAIRE_SLUG = 'osq-questionnaire';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Register a query var to track our custom routes.
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );

		// Parse the request to see if it matches our virtual pages.
		add_action( 'parse_request', array( $this, 'parse_virtual_requests' ) );

		// Hijack the template before WordPress loads the theme.
		add_filter( 'template_include', array( $this, 'load_virtual_templates' ), 99 );

		// IMPORTANT: wp_enqueue_scripts fires BEFORE template_include.
		// We must register enqueues here in init(), and check the query var at runtime.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_questionnaire_scripts' ) );

		// Form submissions.
		add_action( 'wp_loaded', array( $this, 'process_login' ) );

		// AJAX: polling for AI advice.
		add_action( 'wp_ajax_osq_get_ai_advice', array( $this, 'ajax_get_ai_advice' ) );

		// Title tag overrides for our virtual pages.
		add_filter( 'document_title_parts', array( $this, 'override_page_title' ) );
	}

	/**
	 * Enqueue questionnaire scripts only on the questionnaire virtual page.
	 * Called by wp_enqueue_scripts (which fires BEFORE template_include).
	 */
	public function enqueue_questionnaire_scripts() {
		$virtual_page = get_query_var( 'osq_virtual_page' );

		if ( 'questionnaire' === $virtual_page ) {
			wp_enqueue_style( 'osq-frontend-css', OSQ_PLUGIN_URL . 'assets/css/osq-frontend.css', array(), OSQ_VERSION );

			$js_ver = file_exists( OSQ_PLUGIN_DIR . 'assets/js/osq-questionnaire.js' )
				? filemtime( OSQ_PLUGIN_DIR . 'assets/js/osq-questionnaire.js' )
				: OSQ_VERSION;

			wp_enqueue_script( 'osq-questionnaire-js', OSQ_PLUGIN_URL . 'assets/js/osq-questionnaire.js', array( 'jquery' ), $js_ver, true );

			wp_localize_script( 'osq-questionnaire-js', 'osq_vars', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'osq_questionnaire_nonce' ),
				'i18n'     => array(
					'complete_all'       => __( 'Please answer all questions.', 'osq-stress-check' ),
					'section_incomplete' => __( 'Please complete all questions in this section.', 'osq-stress-check' ),
				),
			) );
		}

		if ( 'dashboard' === $virtual_page ) {
			wp_enqueue_style( 'osq-frontend-css', OSQ_PLUGIN_URL . 'assets/css/osq-frontend.css', array(), OSQ_VERSION );
			
			// Enqueue html2pdf.js from CDN
			wp_enqueue_script( 'osq-html2pdf', 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js', array(), '0.10.1', true );
			
			// Enqueue our custom PDF handler
			$pdf_js_ver = file_exists( OSQ_PLUGIN_DIR . 'assets/js/osq-pdf.js' )
				? filemtime( OSQ_PLUGIN_DIR . 'assets/js/osq-pdf.js' )
				: OSQ_VERSION;
			
			wp_enqueue_script( 'osq-pdf-js', OSQ_PLUGIN_URL . 'assets/js/osq-pdf.js', array( 'osq-html2pdf', 'jquery' ), $pdf_js_ver, true );

			wp_localize_script( 'osq-pdf-js', 'osq_pdf_vars', array(
				'filename' => __( 'stress-check-results', 'osq-stress-check' ),
			) );

			// Also enqueue questionnaire script which now handles password change logic on dashboard
			$js_ver = file_exists( OSQ_PLUGIN_DIR . 'assets/js/osq-questionnaire.js' )
				? filemtime( OSQ_PLUGIN_DIR . 'assets/js/osq-questionnaire.js' )
				: OSQ_VERSION;
			wp_enqueue_script( 'osq-questionnaire-js', OSQ_PLUGIN_URL . 'assets/js/osq-questionnaire.js', array( 'jquery' ), $js_ver, true );
			$user_id = get_current_user_id();
			$must_change = get_user_meta( $user_id, 'osq_must_change_password', true );

			wp_localize_script( 'osq-questionnaire-js', 'osq_employee_vars', array(
				'ajax_url'             => admin_url( 'admin-ajax.php' ),
				'nonce'                => wp_create_nonce( 'osq_change_password_nonce' ),
				'must_change_password' => (bool) $must_change,
			) );
		}
	}

	/**
	 * Register the custom query variable.
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'osq_virtual_page';
		return $vars;
	}

	/**
	 * Check if the requested URI matches one of our virtual slugs.
	 */
	public function parse_virtual_requests( $wp ) {
		// Clean up the request path.
		$request = trim( $wp->request, '/' );

		// If permalinks are disabled, check $_GET directly.
		if ( empty( $request ) && isset( $_GET['osq_virtual_page'] ) ) {
			$wp->set_query_var( 'osq_virtual_page', sanitize_text_field( $_GET['osq_virtual_page'] ) );
			return;
		}

		if ( self::LOGIN_SLUG === $request ) {
			$wp->set_query_var( 'osq_virtual_page', 'login' );
		} elseif ( self::DASHBOARD_SLUG === $request ) {
			$wp->set_query_var( 'osq_virtual_page', 'dashboard' );
		} elseif ( self::QUESTIONNAIRE_SLUG === $request ) {
			$wp->set_query_var( 'osq_virtual_page', 'questionnaire' );
		}
	}

	/**
	 * Intercept the template loading process for our virtual pages.
	 */
	public function load_virtual_templates( $template ) {
		$virtual_page = get_query_var( 'osq_virtual_page' );

		if ( ! $virtual_page ) {
			return $template; // Not our page, let WordPress continue normally.
		}

		// Security checks & Redirects
		if ( 'login' === $virtual_page ) {
			if ( is_user_logged_in() && $this->is_osq_employee( get_current_user_id() ) ) {
				wp_safe_redirect( home_url( '/' . self::DASHBOARD_SLUG . '/' ) );
				exit;
			}
			$this->require_template( 'auth/login-form.php' );
			exit;
		}

		if ( 'dashboard' === $virtual_page ) {
			if ( ! is_user_logged_in() || ! $this->is_osq_employee( get_current_user_id() ) ) {
				wp_safe_redirect( home_url( '/' . self::LOGIN_SLUG . '/?osq_error=unauthorized' ) );
				exit;
			}
			$this->require_template( 'auth/employee-dashboard.php' );
			exit;
		}

		if ( 'questionnaire' === $virtual_page ) {
			if ( ! is_user_logged_in() || ! $this->is_osq_employee( get_current_user_id() ) ) {
				wp_safe_redirect( home_url( '/' . self::LOGIN_SLUG . '/?osq_error=unauthorized' ) );
				exit;
			}

			// Scripts are already enqueued by enqueue_questionnaire_scripts() via wp_enqueue_scripts hook.
			$this->require_template( 'questionnaire/questionnaire-page-wrapper.php' );
			exit;
		}

		return $template;
	}

	/**
	 * Process login form submission.
	 */
	public function process_login() {
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['osq_employee_login'] ) ) {
			return;
		}

		if ( ! isset( $_POST['osq_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['osq_login_nonce'] ) ), 'osq_login_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'osq-stress-check' ) );
		}

		$employee_number = sanitize_text_field( wp_unslash( $_POST['employee_number'] ?? '' ) );
		// Do not use sanitize_text_field on passwords, it corrupts special characters (<, >, &).
		$password        = wp_unslash( $_POST['password'] ?? '' );

		if ( empty( $employee_number ) || empty( $password ) ) {
			$this->redirect_back_with_error( 'empty_fields' );
		}

		// Check lockout BEFORE attempting authentication.
		if ( LoginManager::is_ip_locked_out() ) {
			$this->redirect_back_with_error( 'locked_out' );
		}

		$db       = \OSQ\Plugin::get_instance()->db();
		$employee = $db->get_employee_by_number( $employee_number );

		if ( ! $employee || ! $employee->wp_user_id ) {
			LoginManager::record_failed_attempt();
			$this->redirect_back_with_error( 'invalid_credentials' );
		}

		$user = get_userdata( $employee->wp_user_id );

		if ( ! $user ) {
			LoginManager::record_failed_attempt();
			$this->redirect_back_with_error( 'invalid_credentials' );
		}

		// Perform native sign-on using the located user_login.
		$user_signon = wp_signon( array(
			'user_login'    => $user->user_login,
			'user_password' => $password,
			'remember'      => true,
		), is_ssl() );

		if ( is_wp_error( $user_signon ) ) {
			LoginManager::record_failed_attempt();
			$this->redirect_back_with_error( 'invalid_credentials' );
		}

		if ( ! $this->is_osq_employee( $user_signon->ID ) ) {
			wp_logout();
			$this->redirect_back_with_error( 'invalid_role' );
		}

		wp_safe_redirect( home_url( '/' . self::DASHBOARD_SLUG . '/' ) );
		exit;
	}

	/**
	 * Override the document title <title> tag for virtual pages.
	 */
	public function override_page_title( $title ) {
		$virtual_page = get_query_var( 'osq_virtual_page' );
		if ( ! $virtual_page ) {
			return $title;
		}

		if ( 'login' === $virtual_page ) {
			$title['title'] = __( 'Employee Login', 'osq-stress-check' );
		} elseif ( 'dashboard' === $virtual_page ) {
			$title['title'] = __( 'Employee Dashboard', 'osq-stress-check' );
		} elseif ( 'questionnaire' === $virtual_page ) {
			$title['title'] = __( 'Stress Check Questionnaire', 'osq-stress-check' );
		}
		return $title;
	}

	/**
	 * AJAX: Return cached AI advice for polling.
	 */
	public function ajax_get_ai_advice() {
		check_ajax_referer( 'osq_employee_nonce', 'nonce' );

		$user_id  = get_current_user_id();
		$db       = \OSQ\Plugin::get_instance()->db();
		$employee = $db->get_employee_by_user_id( $user_id );

		if ( ! $employee ) {
			wp_send_json_error( array( 'message' => 'unauthorized' ) );
		}

		$response_id = absint( $_POST['response_id'] ?? 0 );
		if ( ! $response_id ) {
			wp_send_json_error( array( 'message' => 'invalid' ) );
		}

		// Security: make sure this response belongs to this employee.
		global $wpdb;
		$res_table  = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;
		$owner_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT employee_id FROM {$res_table} WHERE response_id = %d",
			$response_id
		) );
		if ( $owner_id !== (int) $employee->employee_id ) {
			wp_send_json_error( array( 'message' => 'forbidden' ) );
		}

		$generator = new \OSQ\AI\AdviceGenerator();
		$advice    = $generator->get_cached( $response_id );

		if ( $advice ) {
			wp_send_json_success( array( 'advice' => $advice ) );
		}

		$status = $generator->get_job_status( $employee->employee_id );
		wp_send_json_success( array( 'advice' => null, 'status' => $status ) );
	}

	/**
	 * Require a template file from the plugin templates directory.
	 */
	private function require_template( $template_path ) {
		$full_path = OSQ_PLUGIN_DIR . 'templates/' . $template_path;
		if ( file_exists( $full_path ) ) {
			// To ensure the theme sees these as pages, trick the global queries slightly.
			global $wp_query;
			$wp_query->is_page = true;
			$wp_query->is_singular = true;
			$wp_query->is_home = false;
			$wp_query->is_archive = false;
			$wp_query->is_category = false;
			
			// Set a dummy post object so functions like get_header() don't throw notices.
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
			$dummy_post->post_name = 'virtual-page';
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
			wp_die( esc_html__( 'Template not found: ', 'osq-stress-check' ) . esc_html( $template_path ) );
		}
	}

	/**
	 * Redirect back to login with an error code.
	 */
	private function redirect_back_with_error( $error_code ) {
		$login_url = home_url( '/' . self::LOGIN_SLUG . '/' );
		wp_safe_redirect( add_query_arg( 'osq_error', $error_code, $login_url ) );
		exit;
	}

	/**
	 * Check if the user is an employee or an admin (for testing).
	 */
	private function is_osq_employee( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		if ( in_array( 'administrator', $user->roles, true ) || current_user_can( 'manage_options' ) ) {
			return true;
		}
		return in_array( RoleManager::EMPLOYEE, $user->roles, true );
	}
}
