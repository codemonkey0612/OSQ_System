<?php
/**
 * Org-level AI advice generator (Phase 4).
 *
 * Generates group-level AI advice from GroupAnalyzer data.
 * The cache table doubles as a job queue: rows with status='pending'
 * are picked up by AdviceJobRunner on the osq_process_org_ai_jobs cron event.
 *
 * @package OSQ
 */

namespace OSQ\AI;

use OSQ\Analysis\GroupAnalyzer;
use OSQ\Database\Schema;
use OSQ\Database\DbManager;
use OSQ\Services\OrgLabelService;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OrgAdviceGenerator
 */
class OrgAdviceGenerator {

	/**
	 * Enqueue AI generation jobs for ALL distinct org groups at the given level.
	 * Called when a user switches the org level in the analysis tab.
	 * Skips groups that already have done+non-edited advice cached.
	 *
	 * @param int    $company_id
	 * @param string $org_level  e.g. 'organization_1'
	 * @return int Number of jobs newly enqueued.
	 */
	public function enqueue_all( $company_id, $org_level ) {
		global $wpdb;

		$org_level = $this->validate_org_level( $org_level );
		$emp_table = $wpdb->prefix . Schema::EMPLOYEES;

		$values = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT {$org_level} FROM {$emp_table}
			 WHERE company_id = %d AND {$org_level} != '' AND {$org_level} IS NOT NULL
			 ORDER BY {$org_level}",
			$company_id
		) );

		if ( empty( $values ) ) {
			return 0;
		}

		$table   = $wpdb->prefix . Schema::AI_ORG_ADVICE_CACHE;
		$count   = 0;

		foreach ( $values as $org_value ) {
			$existing = $wpdb->get_row( $wpdb->prepare(
				"SELECT cache_id, status, is_edited FROM {$table}
				 WHERE company_id = %d AND org_level = %s AND org_value = %s",
				$company_id, $org_level, $org_value
			) );

			if ( $existing ) {
				// Skip groups that are already done (and not flagged for regen).
				if ( in_array( $existing->status, array( 'done', 'pending', 'processing' ), true ) ) {
					continue;
				}
				// Re-enqueue failed jobs.
				$wpdb->update(
					$table,
					array( 'status' => 'pending', 'error_message' => null, 'advice_text' => null ),
					array( 'cache_id' => $existing->cache_id )
				);
			} else {
				$wpdb->insert( $table, array(
					'company_id' => $company_id,
					'org_level'  => $org_level,
					'org_value'  => $org_value,
					'status'     => 'pending',
				) );
			}
			$count++;
		}

		if ( $count > 0 ) {
			$this->schedule_cron();
		}

		return $count;
	}

	/**
	 * Regenerate advice for a specific org group (ignores is_edited; always re-generates).
	 *
	 * @param int    $company_id
	 * @param string $org_level
	 * @param string $org_value
	 * @return void
	 */
	public function regenerate( $company_id, $org_level, $org_value ) {
		global $wpdb;
		$table = $wpdb->prefix . Schema::AI_ORG_ADVICE_CACHE;

		$wpdb->replace( $table, array(
			'company_id'    => $company_id,
			'org_level'     => $org_level,
			'org_value'     => $org_value,
			'advice_text'   => null,
			'is_edited'     => 0,
			'status'        => 'pending',
			'error_message' => null,
			'cached_at'     => null,
		) );

		$this->schedule_cron();
	}

	/**
	 * Save manually-edited advice text for an org group.
	 * Sets is_edited=1 so auto-regeneration never overwrites it.
	 *
	 * @param int    $company_id
	 * @param string $org_level
	 * @param string $org_value
	 * @param string $text
	 * @return bool
	 */
	public function save_edited( $company_id, $org_level, $org_value, $text ) {
		global $wpdb;
		$table = $wpdb->prefix . Schema::AI_ORG_ADVICE_CACHE;

		$result = $wpdb->replace( $table, array(
			'company_id'  => $company_id,
			'org_level'   => $org_level,
			'org_value'   => $org_value,
			'advice_text' => sanitize_textarea_field( $text ),
			'is_edited'   => 1,
			'status'      => 'done',
			'cached_at'   => current_time( 'mysql' ),
		) );

		return false !== $result;
	}

	/**
	 * Get cached advice for a specific org group.
	 *
	 * @param int    $company_id
	 * @param string $org_level
	 * @param string $org_value
	 * @return object|null { advice_text, status, is_edited }
	 */
	public function get_cache_row( $company_id, $org_level, $org_value ) {
		global $wpdb;
		$table = $wpdb->prefix . Schema::AI_ORG_ADVICE_CACHE;

		return $wpdb->get_row( $wpdb->prepare(
			"SELECT advice_text, status, is_edited FROM {$table}
			 WHERE company_id = %d AND org_level = %s AND org_value = %s",
			$company_id, $org_level, $org_value
		) );
	}

	/**
	 * Get status summary for all org groups at a level (for JS polling).
	 *
	 * @param int    $company_id
	 * @param string $org_level
	 * @return array  [ org_value => { status, advice_text, is_edited }, ... ]
	 */
	public function get_all_status( $company_id, $org_level ) {
		global $wpdb;
		$table = $wpdb->prefix . Schema::AI_ORG_ADVICE_CACHE;

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT org_value, status, advice_text, is_edited FROM {$table}
			 WHERE company_id = %d AND org_level = %s",
			$company_id, $org_level
		) );

		$map = array();
		foreach ( $rows as $row ) {
			$map[ $row->org_value ] = array(
				'status'      => $row->status,
				'advice_text' => $row->status === 'done' ? $row->advice_text : null,
				'is_edited'   => (bool) $row->is_edited,
			);
		}

		return $map;
	}

	/**
	 * Generate advice synchronously for one org group (called by cron runner).
	 *
	 * @param int    $company_id
	 * @param string $org_level
	 * @param string $org_value
	 * @return string|\WP_Error
	 */
	public function generate( $company_id, $org_level, $org_value ) {
		$analyzer = new GroupAnalyzer();
		$filter   = array(
			$org_level => $org_value,
			'axis'     => $org_level,
		);

		$result = $analyzer->analyze( $filter );

		if ( null === $result ) {
			return new \WP_Error( 'osq_org_ai_no_data', 'グループのデータが不足しているため分析できません。' );
		}

		// Resolve org label and company name for prompt context.
		$company_id_int  = (int) $company_id;
		$org_level_n     = (int) str_replace( 'organization_', '', $org_level );
		$org_level_label = OrgLabelService::get_label( $company_id_int, $org_level_n );

		global $wpdb;
		$company_name = $wpdb->get_var( $wpdb->prepare(
			"SELECT company_name FROM {$wpdb->prefix}osq_companies WHERE company_id = %d",
			$company_id_int
		) ) ?: get_bloginfo( 'name' );

		$system_prompt = PromptManager::build_org_system_prompt( array(
			'company_name'     => $company_name,
			'org_level_label'  => $org_level_label,
			'org_value'        => $org_value,
		) );

		$user_message = $this->build_user_message( $org_value, $org_level_label, $result );

		$client = new OpenaiClient();
		$raw    = $client->complete( $system_prompt, $user_message, 800 );

		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		// NGWord guard with one retry.
		$advice = NGWordGuard::validate_or_retry(
			$raw,
			function () use ( $client, $system_prompt, $user_message ) {
				return $client->complete( $system_prompt, $user_message, 800 );
			},
			$org_value
		);

		// Cache the result.
		$this->cache_result( $company_id, $org_level, $org_value, $advice );

		return $advice;
	}

	/*
	|----------------------------------------------------------------------
	| Private Helpers
	|----------------------------------------------------------------------
	*/

	/**
	 * Build the user message containing the group's stress analysis data.
	 *
	 * @param string $org_value
	 * @param string $org_level_label
	 * @param array  $result  GroupAnalyzer result.
	 * @return string
	 */
	private function build_user_message( $org_value, $org_level_label, $result ) {
		$lines = array(
			"【組織分析データ：{$org_level_label} / {$org_value}】",
			'受検者数: ' . (int) $result['respondent_count'] . '名',
			'高ストレス者数: ' . (int) $result['high_stress_count'] . '名',
			'高ストレス割合: ' . round( (float) $result['high_stress_ratio'], 1 ) . '%',
			'',
			'【尺度別平均スコア（1〜5点スケール）】',
		);

		$scale_labels = array(
			'quantitative_demands' => '仕事の量的負担',
			'qualitative_demands'  => '仕事の質的負担',
			'physical_workload'    => '身体的負担',
			'interpersonal_stress' => '対人関係ストレス',
			'environment_stress'   => '職場環境ストレス',
			'job_control'          => '仕事の裁量度',
			'skill_utilization'    => '技能の活用',
			'job_fit'              => '仕事の適合性',
			'reward'               => '働きがい',
			'vigor'                => '活気',
			'irritability'         => 'イライラ感',
			'fatigue'              => '疲労感',
			'anxiety'              => '不安感',
			'depression'           => '抑うつ感',
			'physical_complaints'  => '身体愁訴',
			'supervisor_support'   => '上司サポート',
			'colleague_support'    => '同僚サポート',
			'family_support'       => '家族・友人サポート',
		);

		foreach ( $result['scale_averages'] as $key => $val ) {
			$label    = $scale_labels[ $key ] ?? $key;
			$lines[]  = "  {$label}: " . round( (float) $val, 2 );
		}

		$lines[] = '';
		$lines[] = '上記の組織分析データを踏まえ、このグループ全体のストレス傾向に対する実務的な総評・対策アドバイスを日本語でお願いします。';

		return implode( "\n", $lines );
	}

	/**
	 * Store generated advice in the org cache table.
	 *
	 * @param int    $company_id
	 * @param string $org_level
	 * @param string $org_value
	 * @param string $advice_text
	 * @return void
	 */
	private function cache_result( $company_id, $org_level, $org_value, $advice_text ) {
		global $wpdb;
		$table    = $wpdb->prefix . Schema::AI_ORG_ADVICE_CACHE;
		$settings = get_option( 'osq_settings', array() );

		$wpdb->update(
			$table,
			array(
				'advice_text' => $advice_text,
				'status'      => 'done',
				'model_used'  => $settings['openai_model'] ?? 'gpt-4o',
				'cached_at'   => current_time( 'mysql' ),
			),
			array(
				'company_id' => $company_id,
				'org_level'  => $org_level,
				'org_value'  => $org_value,
			)
		);
	}

	/**
	 * Validate and sanitize org_level column name.
	 *
	 * @param string $org_level
	 * @return string
	 */
	private function validate_org_level( $org_level ) {
		$allowed = array( 'organization_1', 'organization_2', 'organization_3', 'organization_4', 'organization_5' );
		return in_array( $org_level, $allowed, true ) ? $org_level : 'organization_1';
	}

	/**
	 * Schedule the org AI cron event immediately if not already queued.
	 *
	 * @return void
	 */
	private function schedule_cron() {
		if ( ! wp_next_scheduled( 'osq_process_org_ai_jobs' ) ) {
			wp_schedule_single_event( time(), 'osq_process_org_ai_jobs' );
		}
	}
}
