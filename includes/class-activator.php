<?php
/**
 * Plugin activation handler.
 *
 * @package OSQ
 */

namespace OSQ;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 *
 * Runs on plugin activation via register_activation_hook().
 */
class Activator {

	/**
	 * Minimum required WordPress version.
	 *
	 * @var string
	 */
	const MIN_WP_VERSION = '6.9';

	/**
	 * Minimum required PHP version.
	 *
	 * @var string
	 */
	const MIN_PHP_VERSION = '7.2.24';

	/**
	 * Current database schema version — must match Database\Schema::VERSION.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.4.0';

	/**
	 * Fired on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::check_requirements();
		self::create_tables();
		self::register_capabilities();
		self::set_default_options();
		self::seed_ai_data();
	}

	/**
	 * Verify that the server meets minimum requirements.
	 *
	 * Deactivates the plugin and shows an admin notice if requirements are not met.
	 *
	 * @return void
	 */
	private static function check_requirements() {
		$errors = array();

		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: Required PHP version, 2: Current PHP version. */
				__( 'OSQ Stress Check System requires PHP %1$s or higher. You are running PHP %2$s.', 'osq-stress-check' ),
				self::MIN_PHP_VERSION,
				PHP_VERSION
			);
		}

		global $wp_version;
		if ( version_compare( $wp_version, self::MIN_WP_VERSION, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: Required WordPress version, 2: Current WordPress version. */
				__( 'OSQ Stress Check System requires WordPress %1$s or higher. You are running WordPress %2$s.', 'osq-stress-check' ),
				self::MIN_WP_VERSION,
				$wp_version
			);
		}

		if ( ! empty( $errors ) ) {
			deactivate_plugins( OSQ_PLUGIN_BASENAME );
			wp_die(
				wp_kses_post( implode( '<br>', $errors ) ),
				esc_html__( 'Plugin Activation Error', 'osq-stress-check' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Create custom database tables.
	 *
	 * Uses dbDelta() for safe schema creation and future upgrades.
	 *
	 * @return void
	 */
	private static function create_tables() {
		$installed_version = get_option( 'osq_db_version', '0' );

		if ( version_compare( $installed_version, Database\Schema::VERSION, '>=' ) ) {
			return; // Schema is already up to date.
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql_statements = Database\Schema::get_schema_sql();

		foreach ( $sql_statements as $sql ) {
			dbDelta( $sql );
		}

		update_option( 'osq_db_version', Database\Schema::VERSION );
	}

	/**
	 * Register custom roles and capabilities for custom roles.
	 *
	 * @return void
	 */
	private static function register_capabilities() {
		Auth\RoleManager::add_roles();
	}

	/**
	 * Set default plugin options.
	 *
	 * @return void
	 */
	private static function set_default_options() {
		add_option( 'osq_plugin_version', OSQ_VERSION );
		add_option( 'osq_db_version', self::DB_VERSION );
		add_option( 'osq_settings', array(
			'language'              => 'ja',
			'session_timeout'       => 30,
			'openai_api_key'        => '',
			'openai_model'          => 'gpt-4o',
			'enable_group_analysis' => false,
		) );
	}

	/**
	 * Seed initial AI prompt templates (15 industries) and NGword dictionary.
	 * Skips if data already exists (safe to call on re-activation).
	 *
	 * @return void
	 */
	private static function seed_ai_data() {
		global $wpdb;

		$prompt_table  = $wpdb->prefix . Database\Schema::AI_PROMPTS;
		$ngword_table  = $wpdb->prefix . Database\Schema::AI_NGWORDS;

		$existing_prompts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prompt_table}" );
		if ( 0 === $existing_prompts ) {
			$industries = AI\PromptManager::get_default_industry_prompts();
			foreach ( $industries as $industry ) {
				$wpdb->insert( $prompt_table, array(
					'industry_type'   => $industry['industry_type'],
					'industry_label'  => $industry['industry_label'],
					'system_prompt'   => $industry['system_prompt'],
					'background_memo' => $industry['background_memo'],
					'is_active'       => 1,
				) );
			}
		}

		$existing_ngwords = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ngword_table}" );
		if ( 0 === $existing_ngwords ) {
			$ngwords = AI\NGWordGuard::get_default_ngwords();
			foreach ( $ngwords as $item ) {
				$wpdb->insert( $ngword_table, array(
					'word'      => $item['word'],
					'reason'    => $item['reason'],
					'is_active' => 1,
				) );
			}
		}
	}
}
