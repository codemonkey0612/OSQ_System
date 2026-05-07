<?php
/**
 * Admin menu registration.
 *
 * @package OSQ
 */

namespace OSQ\Admin;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdminMenu
 *
 * Registers the WordPress admin menu structure with permission-segregated subpages.
 */
class AdminMenu {

	/**
	 * Menu slug.
	 */
	const MENU_SLUG = 'osq-stress-check';

	/**
	 * Initialize admin menu hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
	}

	/**
	 * Register menu and submenu pages.
	 *
	 * @return void
	 */
	public function register_menus() {
		// Main menu — visible to OSQ Administrators. Points directly to the settings page.
		add_menu_page(
			__( 'OSQ ストレスチェック', 'osq-stress-check' ),
			__( 'OSQ Stress Check', 'osq-stress-check' ),
			'osq_system_config',
			self::MENU_SLUG . '-settings',
			array( $this, 'render_settings' ),
			'dashicons-heart',
			30
		);

		// First submenu: rename the top-level item so it doesn't appear as a duplicate.
		add_submenu_page(
			self::MENU_SLUG . '-settings',
			__( 'OSQ 設定 / Settings', 'osq-stress-check' ),
			__( '設定', 'osq-stress-check' ),
			'osq_system_config',
			self::MENU_SLUG . '-settings',
			array( $this, 'render_settings' )
		);

		// Submenu: industry-specific AI prompt editor.
		add_submenu_page(
			self::MENU_SLUG . '-settings',
			__( '業種別AIプロンプト管理 / Industry AI Prompts', 'osq-stress-check' ),
			__( 'AIプロンプト', 'osq-stress-check' ),
			'osq_system_config',
			self::MENU_SLUG . '-ai-prompts',
			array( $this, 'render_ai_prompts' )
		);

		// Submenu: NGword list management.
		add_submenu_page(
			self::MENU_SLUG . '-settings',
			__( 'NGワード管理 / NG Word Management', 'osq-stress-check' ),
			__( 'NGワード', 'osq-stress-check' ),
			'osq_system_config',
			self::MENU_SLUG . '-ngwords',
			array( $this, 'render_ngwords' )
		);

		// Note: Dashboard, CSV Import, Group Analysis, Individual Responses, and Support
		// interface menu pages have been removed from the default WP Admin.
		// These features are now exclusively accessible via the custom standalone portals.
	}

	/**
	 * Render Settings page (delegates to SettingsPage).
	 *
	 * @return void
	 */
	public function render_settings() {
		if ( ! current_user_can( 'osq_system_config' ) ) {
			wp_die( esc_html__( 'Access denied.', 'osq-stress-check' ) );
		}
		$settings_page = new SettingsPage();
		$settings_page->render();
	}

	/**
	 * Render the AI Prompts management page.
	 *
	 * @return void
	 */
	public function render_ai_prompts() {
		if ( ! current_user_can( 'osq_system_config' ) ) {
			wp_die( esc_html__( 'Access denied.', 'osq-stress-check' ) );
		}
		( new AiPromptsPage() )->render();
	}

	/**
	 * Render the NGWord management page.
	 *
	 * @return void
	 */
	public function render_ngwords() {
		if ( ! current_user_can( 'osq_system_config' ) ) {
			wp_die( esc_html__( 'Access denied.', 'osq-stress-check' ) );
		}
		( new NgwordPage() )->render();
	}
}
