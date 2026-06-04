<?php
/**
 * Database schema definitions.
 *
 * @package OSQ
 */

namespace OSQ\Database;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schema
 * 
 * Centralizes table names and schema SQL for dbDelta.
 */
class Schema {

	/**
	 * Database schema version.
	 *
	 * @var string
	 */
	const VERSION = '1.6.3';

	/**
	 * Table names (without prefix).
	 */
	const EMPLOYEES          = 'osq_employees';
	const RESPONSES          = 'osq_stress_responses';
	const SESSIONS           = 'osq_sessions';
	const FOLLOW_UP_TRACKING = 'osq_follow_up_tracking';
	const AI_PROMPTS         = 'osq_ai_prompts';
	const AI_NGWORDS         = 'osq_ai_ngwords';
	const AI_ADVICE_JOBS     = 'osq_ai_advice_jobs';
	const AI_ADVICE_CACHE        = 'osq_ai_advice_cache';
	const AI_ORG_ADVICE_CACHE    = 'osq_ai_org_advice_cache';
	const RESPONSE_HISTORY   = 'osq_response_history';
	const COMPANIES          = 'osq_companies';

	/**
	 * Default company_id assigned to all pre-Phase-3a data during migration.
	 */
	const DEFAULT_COMPANY_ID = 1;

	/**
	 * Returns the schema SQL for all tables.
	 *
	 * @return array Array of SQL strings for dbDelta.
	 */
	public static function get_schema_sql() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$table_employees = $wpdb->prefix . self::EMPLOYEES;
		$table_responses = $wpdb->prefix . self::RESPONSES;
		$table_sessions  = $wpdb->prefix . self::SESSIONS;

		$sql = array();

		// Table: osq_employees (multi-tenant via company_id from Phase 3a).
		$sql[] = "CREATE TABLE {$table_employees} (
			employee_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			company_id bigint(20) unsigned DEFAULT NULL,
			wp_user_id bigint(20) unsigned DEFAULT NULL,
			employee_number varchar(50) NOT NULL,
			name varchar(255) NOT NULL DEFAULT '',
			email varchar(255) NOT NULL DEFAULT '',
			gender tinyint(1) DEFAULT NULL,
			date_of_birth date DEFAULT NULL,
			organization_1 varchar(255) DEFAULT NULL,
			organization_2 varchar(255) DEFAULT NULL,
			organization_3 varchar(255) DEFAULT NULL,
			organization_4 varchar(255) DEFAULT NULL,
			organization_5 varchar(255) DEFAULT NULL,
			job_type tinyint(1) DEFAULT NULL,
			industry_type tinyint(3) DEFAULT NULL,
			position tinyint(1) DEFAULT NULL,
			employment_type tinyint(1) DEFAULT NULL,
			hire_date date DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (employee_id),
			UNIQUE KEY company_employee_number (company_id, employee_number),
			KEY wp_user_id (wp_user_id),
			KEY company_id (company_id)
		) {$charset_collate};";

		// Table: osq_stress_responses (multi-tenant via company_id from Phase 3a).
		$sql[] = "CREATE TABLE {$table_responses} (
			response_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			company_id bigint(20) unsigned DEFAULT NULL,
			employee_id bigint(20) unsigned NOT NULL,
			response_data longtext DEFAULT NULL,
			is_complete tinyint(1) NOT NULL DEFAULT 0,
			completed_at datetime DEFAULT NULL,
			method1_result text DEFAULT NULL,
			method2_result text DEFAULT NULL,
			is_high_stress_method1 tinyint(1) DEFAULT NULL,
			is_high_stress_method2 tinyint(1) DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (response_id),
			KEY employee_id (employee_id),
			KEY company_id (company_id)
		) {$charset_collate};";

		// Table: osq_sessions
		$sql[] = "CREATE TABLE {$table_sessions} (
			session_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			employee_id bigint(20) unsigned NOT NULL,
			session_token varchar(255) NOT NULL,
			last_accessed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			expires_at datetime NOT NULL,
			PRIMARY KEY  (session_id),
			UNIQUE KEY session_token (session_token),
			KEY employee_id (employee_id)
		) {$charset_collate};";

		// Table: osq_follow_up_tracking (multi-tenant via company_id from Phase 3a).
		$table_follow_up = $wpdb->prefix . self::FOLLOW_UP_TRACKING;
		$sql[] = "CREATE TABLE {$table_follow_up} (
			follow_up_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			company_id bigint(20) unsigned DEFAULT NULL,
			employee_id bigint(20) unsigned NOT NULL,
			officer_id bigint(20) unsigned NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'Scheduled',
			notes text DEFAULT NULL,
			scheduled_date datetime DEFAULT NULL,
			completed_date datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (follow_up_id),
			KEY employee_id (employee_id),
			KEY officer_id (officer_id),
			KEY status (status),
			KEY company_id (company_id)
		) {$charset_collate};";

		// Table: osq_ai_prompts — industry-specific prompt templates (DB-managed, WP admin editable).
		$table_ai_prompts = $wpdb->prefix . self::AI_PROMPTS;
		$sql[] = "CREATE TABLE {$table_ai_prompts} (
			prompt_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			industry_type tinyint(3) unsigned NOT NULL COMMENT '1-15 industry classification',
			industry_label varchar(100) NOT NULL DEFAULT '',
			system_prompt text NOT NULL,
			background_memo text DEFAULT NULL COMMENT 'Human-readable notes for admin',
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (prompt_id),
			UNIQUE KEY industry_type (industry_type)
		) {$charset_collate};";

		// Table: osq_ai_ngwords — prohibited word/phrase dictionary.
		$table_ai_ngwords = $wpdb->prefix . self::AI_NGWORDS;
		$sql[] = "CREATE TABLE {$table_ai_ngwords} (
			ngword_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			word varchar(255) NOT NULL,
			reason varchar(255) DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (ngword_id),
			UNIQUE KEY word (word)
		) {$charset_collate};";

		// Table: osq_ai_advice_jobs — async generation queue (WP-Cron processed). Multi-tenant via company_id.
		$table_ai_jobs = $wpdb->prefix . self::AI_ADVICE_JOBS;
		$sql[] = "CREATE TABLE {$table_ai_jobs} (
			job_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			company_id bigint(20) unsigned DEFAULT NULL,
			employee_id bigint(20) unsigned NOT NULL,
			response_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending|processing|done|failed',
			attempt_count tinyint(3) NOT NULL DEFAULT 0,
			error_message text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (job_id),
			KEY employee_id (employee_id),
			KEY status (status),
			KEY company_id (company_id)
		) {$charset_collate};";

		// Table: osq_ai_advice_cache — generated AI advice, keyed by response_id. Multi-tenant via company_id.
		$table_ai_cache = $wpdb->prefix . self::AI_ADVICE_CACHE;
		$sql[] = "CREATE TABLE {$table_ai_cache} (
			cache_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			company_id bigint(20) unsigned DEFAULT NULL,
			employee_id bigint(20) unsigned NOT NULL,
			response_id bigint(20) unsigned NOT NULL,
			advice_text longtext NOT NULL,
			industry_type tinyint(3) unsigned DEFAULT NULL,
			model_used varchar(50) DEFAULT NULL,
			prompt_tokens int unsigned DEFAULT NULL,
			completion_tokens int unsigned DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (cache_id),
			UNIQUE KEY response_id (response_id),
			KEY employee_id (employee_id),
			KEY company_id (company_id)
		) {$charset_collate};";

		// Table: osq_response_history — archives completed responses (YoY radar chart). Multi-tenant via company_id.
		$table_history = $wpdb->prefix . self::RESPONSE_HISTORY;
		$sql[] = "CREATE TABLE {$table_history} (
			history_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			company_id bigint(20) unsigned DEFAULT NULL,
			employee_id bigint(20) unsigned NOT NULL,
			fiscal_year smallint(4) unsigned NOT NULL,
			method1_result text DEFAULT NULL,
			method2_result text DEFAULT NULL,
			is_high_stress_method1 tinyint(1) DEFAULT NULL,
			is_high_stress_method2 tinyint(1) DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			org_snapshot varchar(255) DEFAULT NULL,
			position_snapshot tinyint(1) DEFAULT NULL,
			archived_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (history_id),
			KEY employee_id (employee_id),
			KEY fiscal_year (fiscal_year),
			KEY company_id (company_id)
		) {$charset_collate};";

		// Table: osq_companies — tenant root (Phase 3a). Each row = one client company that uses the system.
		$table_companies = $wpdb->prefix . self::COMPANIES;
		$sql[] = "CREATE TABLE {$table_companies} (
			company_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			company_name varchar(255) NOT NULL,
			company_slug varchar(100) NOT NULL,
			org_label_1 varchar(100) DEFAULT '組織1',
			org_label_2 varchar(100) DEFAULT '組織2',
			org_label_3 varchar(100) DEFAULT '組織3',
			org_label_4 varchar(100) DEFAULT NULL,
			org_label_5 varchar(100) DEFAULT NULL,
			min_group_size tinyint unsigned NOT NULL DEFAULT 5,
			excluded_orgs text DEFAULT NULL,
			physician_name varchar(255) DEFAULT NULL,
			contact_name varchar(255) DEFAULT NULL,
			contact_phone varchar(50) DEFAULT NULL,
			contact_email varchar(255) DEFAULT NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			is_demo tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (company_id),
			UNIQUE KEY company_slug (company_slug),
			KEY is_active (is_active)
		) {$charset_collate};";

		// Table: osq_ai_org_advice_cache — org-level AI advice cache (Phase 4).
		// Acts as both cache and job queue: status=pending triggers WP-Cron processing.
		$table_org_cache = $wpdb->prefix . self::AI_ORG_ADVICE_CACHE;
		$sql[] = "CREATE TABLE {$table_org_cache} (
			cache_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			company_id bigint(20) unsigned NOT NULL,
			org_level varchar(30) NOT NULL,
			org_value varchar(255) NOT NULL,
			advice_text longtext DEFAULT NULL,
			is_edited tinyint(1) NOT NULL DEFAULT 0,
			model_used varchar(50) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			error_message text DEFAULT NULL,
			cached_at datetime DEFAULT NULL,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (cache_id),
			UNIQUE KEY company_org (company_id, org_level, org_value(100)),
			KEY status (status),
			KEY company_id (company_id)
		) {$charset_collate};";

		return $sql;
	}
}
