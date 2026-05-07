<?php
/**
 * AI advice orchestrator.
 *
 * @package OSQ
 */

namespace OSQ\AI;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdviceGenerator
 *
 * Builds user context from 4-axis attributes + stress scores,
 * calls OpenAI via PromptManager, runs NGWordGuard, and caches the result.
 */
class AdviceGenerator {

	/**
	 * Generate (or retrieve cached) AI advice for an employee.
	 *
	 * @param object $employee  Employee DB row.
	 * @param object $response  Stress response DB row (must be complete).
	 * @return string|\WP_Error Advice text or error.
	 */
	public function generate( $employee, $response ) {
		if ( ! $response || ! $response->is_complete ) {
			return new \WP_Error( 'osq_ai_no_response', __( 'Stress check not yet completed.', 'osq-stress-check' ) );
		}

		// Return cached result if available.
		$cached = $this->get_cached( $response->response_id );
		if ( $cached ) {
			return $cached;
		}

		// Resolve 4-axis user attributes.
		$industry_type  = (int) ( $employee->industry_type ?? 15 ); // default: other
		$position_label = $this->resolve_position_label( $employee->position ?? null );
		$age            = $this->calculate_age( $employee->date_of_birth ?? null );
		$tenure_years   = $this->calculate_tenure( $employee->hire_date ?? $employee->created_at ?? null );

		// Build system prompt with 3 principles injected.
		$system_prompt = PromptManager::build_system_prompt(
			$industry_type,
			$position_label,
			$age,
			$tenure_years
		);

		// Build user message from stress score summary.
		$user_message = $this->build_user_message( $response, $age, $tenure_years );

		$client = new OpenaiClient();

		// First attempt.
		$raw = $client->complete( $system_prompt, $user_message, 600 );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		// Get industry label for fallback message.
		$prompt_data    = PromptManager::get_prompt( $industry_type );
		$industry_label = $prompt_data['industry_label'] ?? '';

		// NGword guard with one automatic retry.
		$advice = NGWordGuard::validate_or_retry(
			$raw,
			function () use ( $client, $system_prompt, $user_message ) {
				return $client->complete( $system_prompt, $user_message, 600 );
			},
			$industry_label
		);

		// Cache the result.
		$this->cache_advice( $employee->employee_id, $response->response_id, $advice, $industry_type );

		return $advice;
	}

	/**
	 * Queue an async generation job (processed by WP-Cron).
	 *
	 * @param int $employee_id
	 * @param int $response_id
	 * @return int|false Job ID or false on failure.
	 */
	public function enqueue( $employee_id, $response_id ) {
		global $wpdb;
		$table = $wpdb->prefix . \OSQ\Database\Schema::AI_ADVICE_JOBS;

		// Avoid duplicate pending jobs.
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT job_id FROM {$table} WHERE employee_id = %d AND status IN ('pending','processing')",
			$employee_id
		) );

		if ( $existing ) {
			return (int) $existing;
		}

		$result = $wpdb->insert( $table, array(
			'employee_id' => $employee_id,
			'response_id' => $response_id,
			'status'      => 'pending',
		) );

		if ( false === $result ) {
			return false;
		}

		// Trigger WP-Cron immediately if not already scheduled.
		if ( ! wp_next_scheduled( 'osq_process_ai_jobs' ) ) {
			wp_schedule_single_event( time(), 'osq_process_ai_jobs' );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Check if cached advice exists for a response.
	 *
	 * @param int $response_id
	 * @return string|null
	 */
	public function get_cached( $response_id ) {
		global $wpdb;
		$table = $wpdb->prefix . \OSQ\Database\Schema::AI_ADVICE_CACHE;

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT advice_text FROM {$table} WHERE response_id = %d",
			(int) $response_id
		) );

		return $row ? $row->advice_text : null;
	}

	/**
	 * Get job status for an employee.
	 *
	 * @param int $employee_id
	 * @return string|null 'pending'|'processing'|'done'|'failed'|null
	 */
	public function get_job_status( $employee_id ) {
		global $wpdb;
		$table = $wpdb->prefix . \OSQ\Database\Schema::AI_ADVICE_JOBS;

		return $wpdb->get_var( $wpdb->prepare(
			"SELECT status FROM {$table} WHERE employee_id = %d ORDER BY created_at DESC LIMIT 1",
			(int) $employee_id
		) );
	}

	/*
	|----------------------------------------------------------------------
	| Private Helpers
	|----------------------------------------------------------------------
	*/

	/**
	 * Build the user-role message containing the score summary.
	 *
	 * @param object $response
	 * @param int    $age
	 * @param int    $tenure_years
	 * @return string
	 */
	private function build_user_message( $response, $age, $tenure_years ) {
		$m1 = maybe_unserialize( $response->method1_result ?? '' );
		if ( is_string( $m1 ) ) {
			$m1 = json_decode( $m1, true );
		}
		$m2 = maybe_unserialize( $response->method2_result ?? '' );
		if ( is_string( $m2 ) ) {
			$m2 = json_decode( $m2, true );
		}

		$is_high = $response->is_high_stress_method1 || $response->is_high_stress_method2;

		$lines = array(
			'【ストレスチェック結果サマリー】',
			'総合判定: ' . ( $is_high ? '高ストレス' : '通常範囲' ),
		);

		if ( is_array( $m1 ) ) {
			$lines[] = sprintf(
				'方法1（合計点法）: A領域=%d, B領域=%d, C領域=%d → %s',
				$m1['section_a_total'] ?? 0,
				$m1['section_b_total'] ?? 0,
				$m1['section_c_total'] ?? 0,
				( ! empty( $m1['is_high_stress'] ) ) ? '高ストレス判定' : '通常'
			);
		}

		if ( is_array( $m2 ) && isset( $m2['section_a_eval'], $m2['section_b_eval'], $m2['section_c_eval'] ) ) {
			$lines[] = sprintf(
				'方法2（換算表法）: A評価=%d, B評価=%d, C評価=%d → %s',
				$m2['section_a_eval'],
				$m2['section_b_eval'],
				$m2['section_c_eval'],
				( ! empty( $m2['is_high_stress'] ) ) ? '高ストレス判定' : '通常'
			);
		}

		if ( $age > 0 ) {
			$lines[] = '年齢: ' . $age . '歳';
		}
		if ( $tenure_years > 0 ) {
			$lines[] = '勤続年数: ' . $tenure_years . '年';
		}

		$lines[] = '';
		$lines[] = '上記の結果を踏まえ、この方に寄り添った具体的なアドバイスを日本語でお願いします。';

		return implode( "\n", $lines );
	}

	/**
	 * Store generated advice in cache table.
	 *
	 * @param int    $employee_id
	 * @param int    $response_id
	 * @param string $advice_text
	 * @param int    $industry_type
	 * @return void
	 */
	private function cache_advice( $employee_id, $response_id, $advice_text, $industry_type ) {
		global $wpdb;
		$table    = $wpdb->prefix . \OSQ\Database\Schema::AI_ADVICE_CACHE;
		$settings = get_option( 'osq_settings', array() );

		$wpdb->replace( $table, array(
			'employee_id'   => $employee_id,
			'response_id'   => $response_id,
			'advice_text'   => $advice_text,
			'industry_type' => $industry_type,
			'model_used'    => $settings['openai_model'] ?? 'gpt-4o',
		) );
	}

	/**
	 * Calculate age from date_of_birth string (Y-m-d).
	 *
	 * @param string|null $dob
	 * @return int Age in years, 0 if unknown.
	 */
	private function calculate_age( $dob ) {
		if ( empty( $dob ) ) {
			return 0;
		}
		try {
			$birth = new \DateTime( $dob );
			$today = new \DateTime();
			return (int) $birth->diff( $today )->y;
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	/**
	 * Calculate tenure in years from hire_date or created_at string (Y-m-d).
	 *
	 * @param string|null $hire_date
	 * @return int Tenure in years, 0 if unknown.
	 */
	private function calculate_tenure( $hire_date ) {
		if ( empty( $hire_date ) ) {
			return 0;
		}
		try {
			$start = new \DateTime( $hire_date );
			$today = new \DateTime();
			return (int) $start->diff( $today )->y;
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	/**
	 * Map position tinyint to human-readable Japanese label.
	 *
	 * @param int|null $position
	 * @return string
	 */
	private function resolve_position_label( $position ) {
		$map = array(
			1 => '一般社員',
			2 => '主任・係長',
			3 => '課長',
			4 => '部長',
			5 => '役員・経営者',
			6 => 'パート・アルバイト',
			7 => '契約社員・派遣社員',
		);
		return $map[ (int) $position ] ?? '';
	}
}
