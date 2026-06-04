<?php
/**
 * Main plugin orchestrator.
 *
 * @package OSQ
 */

namespace OSQ;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Singleton that registers all core hooks and initializes sub-components.
 */
class Plugin {

	/**
	 * Single instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Database manager instance.
	 *
	 * @var Database\DbManager|null
	 */
	private $db = null;

	/**
	 * Returns the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton.
	 */
	private function __construct() {
		// Intentionally empty — all work happens in init().
	}

	/**
	 * Initialize the plugin by registering hooks.
	 *
	 * Called on the `plugins_loaded` action.
	 *
	 * @return void
	 */
	public function init() {
		// Initialize the data layer.
		$this->db = new Database\DbManager();

		// Initialize authentication & access control.
		( new Auth\LoginManager() )->init();
		( new Auth\AccessControl() )->init();
		( new Auth\EmployeeUiHandler() )->init();
		( new Auth\AdminUiHandler() )->init();
		( new Auth\OfficerUiHandler() )->init();
		( new Auth\CompaniesUiHandler() )->init();
		( new Auth\UnifiedDashboardHandler() )->init();
		( new Auth\PortalRouter() )->init();

		// Initialize questionnaire handler (AJAX endpoints).
		( new Questionnaire\QuestionnaireHandler() )->init();

		// Initialize internationalization.
		( new I18n() )->init();

		// Initialize security manager.
		( new Security\SecurityManager() )->init();

		// Initialize AI job runner (registers WP-Cron hook — runs on both admin and frontend).
		( new AI\AdviceJobRunner() )->init();

		// Admin-only hooks — gated behind is_admin() to reduce frontend overhead.
		if ( is_admin() ) {
			( new \OSQ\Admin\AdminMenu() )->init();
			( new \OSQ\Admin\SettingsPage() )->init();
			( new \OSQ\Admin\AiPromptsPage() )->init();
			( new \OSQ\Admin\NgwordPage() )->init();
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		}

		// Frontend hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/*
	|----------------------------------------------------------------------
	| Hook Callbacks
	|----------------------------------------------------------------------
	*/

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'osq-stress-check',
			false,
			dirname( OSQ_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Register admin menu pages.
	 *
	 * Placeholder — full implementation in Component 9.
	 *
	 * @return void
	 */
	public function register_admin_menus() {
		// Will be implemented in Component 9: Admin UI & Settings.
	}

	/**
	 * Enqueue admin-side CSS and JS.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		// Only load on OSQ pages.
		if ( strpos( $hook_suffix, 'osq-stress-check' ) === false ) {
			return;
		}

		wp_enqueue_style( 'osq-admin-css', OSQ_PLUGIN_URL . 'assets/css/osq-admin.css', array(), OSQ_VERSION );
		
		// Load Chart.js for analysis.
		wp_enqueue_script( 'chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', array(), '3.9.1', true );
		
		wp_enqueue_script( 'osq-admin-js', OSQ_PLUGIN_URL . 'assets/js/osq-admin.js', array( 'jquery', 'chart-js' ), OSQ_VERSION, true );

		wp_localize_script( 'osq-admin-js', 'osq_admin_vars', array(
			'i18n' => array(
				'selected_file' => __( '選択されたファイル', 'osq-stress-check' ),
			),
		) );
	}

	/**
	 * Enqueue frontend CSS and JS.
	 *
	 * @return void
	 */
	public function enqueue_frontend_assets() {
		// Only load if the questionnaire shortcode is present or on specific pages.
		// For now, we'll load it globally if the user is an OSQ employee.
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_style( 'osq-frontend-css', OSQ_PLUGIN_URL . 'assets/css/osq-frontend.css', array(), OSQ_VERSION );
		wp_enqueue_script( 'osq-questionnaire-js', OSQ_PLUGIN_URL . 'assets/js/osq-questionnaire.js', array( 'jquery' ), OSQ_VERSION, true );

		wp_localize_script( 'osq-questionnaire-js', 'osq_vars', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'osq_questionnaire_nonce' ),
			'i18n'     => array(
				'complete_all'       => __( 'すべての質問に回答してください。', 'osq-stress-check' ),
				'section_incomplete' => __( 'このセクションのすべての質問に回答してください。', 'osq-stress-check' ),
			),
		) );
	}

	/*
	|----------------------------------------------------------------------
	| Accessors
	|----------------------------------------------------------------------
	*/

	/**
	 * Get the plugin version.
	 *
	 * @return string
	 */
	public function get_version() {
		return OSQ_VERSION;
	}

	/**
	 * Get the plugin directory path (with trailing slash).
	 *
	 * @return string
	 */
	public function get_plugin_dir() {
		return OSQ_PLUGIN_DIR;
	}

	/**
	 * Get the plugin directory URL (with trailing slash).
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		return OSQ_PLUGIN_URL;
	}

	/**
	 * Get the database manager instance.
	 *
	 * @return Database\DbManager
	 */
	public function db() {
		return $this->db;
	}
}
