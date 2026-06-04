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
	const DB_VERSION = '1.6.1';

	/**
	 * Fired on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::check_requirements();
		$previous_db_version = get_option( 'osq_db_version', '0' );
		self::create_tables();
		self::register_capabilities();
		self::set_default_options();
		self::seed_ai_data();
		self::migrate_to_phase_3a( $previous_db_version );
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
		global $wpdb;
		$installed_version = get_option( 'osq_db_version', '0' );

		if ( version_compare( $installed_version, Database\Schema::VERSION, '>=' ) ) {
			return; // Schema is already up to date.
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql_statements = Database\Schema::get_schema_sql();

		foreach ( $sql_statements as $sql ) {
			dbDelta( $sql );
		}

		// 1.5.0 → 1.6.0: add org_4/5 columns if not already present.
		if ( version_compare( $installed_version, '1.6.0', '<' ) ) {
			$table   = $wpdb->prefix . Database\Schema::EMPLOYEES;
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
			if ( ! in_array( 'organization_4', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN organization_4 varchar(255) DEFAULT NULL AFTER organization_3" );
			}
			if ( ! in_array( 'organization_5', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN organization_5 varchar(255) DEFAULT NULL AFTER organization_4" );
			}
		}

		// 1.6.0 → 1.6.1: add is_demo column to osq_companies.
		if ( version_compare( $installed_version, '1.6.1', '<' ) ) {
			$table   = $wpdb->prefix . Database\Schema::COMPANIES;
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
			if ( ! in_array( 'is_demo', $columns, true ) ) {
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN is_demo tinyint(1) NOT NULL DEFAULT 0 AFTER is_active" );
			}
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

	/**
	 * Phase 3a migration: seed the default "wellanc" tenant and backfill
	 * company_id on every pre-existing row across all data tables.
	 *
	 * Safe to call on every activation — only runs once when upgrading from <1.5.0.
	 *
	 * @param string $previous_db_version DB version recorded before activation.
	 * @return void
	 */
	private static function migrate_to_phase_3a( $previous_db_version ) {
		if ( version_compare( $previous_db_version, '1.5.0', '>=' ) ) {
			return; // Already migrated.
		}

		global $wpdb;
		$companies_table = $wpdb->prefix . Database\Schema::COMPANIES;
		$default_id      = Database\Schema::DEFAULT_COMPANY_ID;

		// Step 1: seed default "wellanc" company at company_id = 1 (only if empty).
		$existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$companies_table}" );
		if ( 0 === $existing ) {
			$wpdb->insert( $companies_table, array(
				'company_id'     => $default_id,
				'company_name'   => '株式会社wellanc',
				'company_slug'   => 'wellanc',
				'org_label_1'    => '組織1',
				'org_label_2'    => '組織2',
				'org_label_3'    => '組織3',
				'min_group_size' => 5,
				'is_active'      => 1,
			) );
		}

		// Step 2: backfill company_id on every data table.
		$tables_to_backfill = array(
			Database\Schema::EMPLOYEES,
			Database\Schema::RESPONSES,
			Database\Schema::RESPONSE_HISTORY,
			Database\Schema::AI_ADVICE_CACHE,
			Database\Schema::AI_ADVICE_JOBS,
			Database\Schema::FOLLOW_UP_TRACKING,
		);

		foreach ( $tables_to_backfill as $table_name ) {
			$table = $wpdb->prefix . $table_name;
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$table} SET company_id = %d WHERE company_id IS NULL",
				$default_id
			) );
		}

		// Step 2.5: drop the old single-column UNIQUE index on employee_number
		// (replaced by the composite UNIQUE (company_id, employee_number) so different
		// tenants can reuse the same employee_number). dbDelta does not drop indexes
		// automatically, so we do it manually here. Safe to run repeatedly.
		$employees_table = $wpdb->prefix . Database\Schema::EMPLOYEES;
		$has_old_index   = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM information_schema.statistics
			 WHERE table_schema = DATABASE() AND table_name = %s AND index_name = 'employee_number'",
			$employees_table
		) );
		if ( $has_old_index ) {
			$wpdb->query( "ALTER TABLE {$employees_table} DROP INDEX employee_number" );
		}

		// Step 3: backfill osq_company_id user meta on every existing WP user that
		// is linked to an OSQ employee, an OSQ officer, or an OSQ admin. WP admins
		// (role=administrator) intentionally get no meta — they act as system admins
		// who can be assigned the wellanc super-admin role later.
		$employees_table = $wpdb->prefix . Database\Schema::EMPLOYEES;
		$linked_user_ids = $wpdb->get_col(
			"SELECT DISTINCT wp_user_id FROM {$employees_table} WHERE wp_user_id IS NOT NULL"
		);
		foreach ( (array) $linked_user_ids as $uid ) {
			$existing = get_user_meta( (int) $uid, 'osq_company_id', true );
			if ( '' === $existing || null === $existing ) {
				update_user_meta( (int) $uid, 'osq_company_id', $default_id );
			}
		}

		// Also tag standalone officer/general-admin users (those without an employee row).
		$role_users = get_users( array(
			'role__in' => array( 'osq_implementation_officer', 'osq_general_administrator' ),
			'fields'   => 'ID',
		) );
		foreach ( (array) $role_users as $uid ) {
			$existing = get_user_meta( (int) $uid, 'osq_company_id', true );
			if ( '' === $existing || null === $existing ) {
				update_user_meta( (int) $uid, 'osq_company_id', $default_id );
			}
		}
	}
}
