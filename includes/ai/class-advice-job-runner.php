<?php
/**
 * WP-Cron job runner for async AI advice generation.
 *
 * @package OSQ
 */

namespace OSQ\AI;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AdviceJobRunner
 *
 * Processes pending AI advice jobs from the queue table.
 * Invoked by the `osq_process_ai_jobs` WP-Cron event.
 */
class AdviceJobRunner {

	/**
	 * Maximum jobs to process per cron invocation (prevents timeouts).
	 */
	const BATCH_SIZE = 5;

	/**
	 * Register WP-Cron hooks for both individual and org advice jobs.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'osq_process_ai_jobs',     array( $this, 'run' ) );
		add_action( 'osq_process_org_ai_jobs', array( $this, 'run_org' ) );
	}

	/**
	 * Process pending jobs from the queue.
	 *
	 * @return void
	 */
	public function run() {
		global $wpdb;
		$jobs_table = $wpdb->prefix . \OSQ\Database\Schema::AI_ADVICE_JOBS;
		$emp_table  = $wpdb->prefix . \OSQ\Database\Schema::EMPLOYEES;
		$res_table  = $wpdb->prefix . \OSQ\Database\Schema::RESPONSES;

		$jobs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$jobs_table} WHERE status = 'pending' ORDER BY created_at ASC LIMIT %d",
			self::BATCH_SIZE
		) );

		if ( empty( $jobs ) ) {
			return;
		}

		$generator = new AdviceGenerator();

		foreach ( $jobs as $job ) {
			// Mark as processing to prevent double-runs.
			$wpdb->update(
				$jobs_table,
				array( 'status' => 'processing' ),
				array( 'job_id' => $job->job_id )
			);

			$employee = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$emp_table} WHERE employee_id = %d",
				(int) $job->employee_id
			) );

			$response = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$res_table} WHERE response_id = %d",
				(int) $job->response_id
			) );

			if ( ! $employee || ! $response ) {
				$wpdb->update(
					$jobs_table,
					array( 'status' => 'failed', 'error_message' => 'Employee or response not found' ),
					array( 'job_id' => $job->job_id )
				);
				continue;
			}

			$result = $generator->generate( $employee, $response );

			if ( is_wp_error( $result ) ) {
				$wpdb->update(
					$jobs_table,
					array(
						'status'        => 'failed',
						'error_message' => $result->get_error_message(),
					),
					array( 'job_id' => $job->job_id )
				);
			} else {
				$wpdb->update(
					$jobs_table,
					array( 'status' => 'done' ),
					array( 'job_id' => $job->job_id )
				);
			}
		}

		// If more jobs remain, schedule another run.
		$remaining = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$jobs_table} WHERE status = 'pending'"
		);

		if ( $remaining > 0 && ! wp_next_scheduled( 'osq_process_ai_jobs' ) ) {
			wp_schedule_single_event( time() + 30, 'osq_process_ai_jobs' );
		}
	}

	/**
	 * Process pending org-level AI advice jobs from osq_ai_org_advice_cache.
	 *
	 * @return void
	 */
	public function run_org() {
		global $wpdb;
		$table = $wpdb->prefix . \OSQ\Database\Schema::AI_ORG_ADVICE_CACHE;

		$jobs = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = 'pending' ORDER BY updated_at ASC LIMIT %d",
			self::BATCH_SIZE
		) );

		if ( empty( $jobs ) ) {
			return;
		}

		$generator = new OrgAdviceGenerator();

		foreach ( $jobs as $job ) {
			// Mark as processing.
			$wpdb->update(
				$table,
				array( 'status' => 'processing' ),
				array( 'cache_id' => $job->cache_id )
			);

			$result = $generator->generate( $job->company_id, $job->org_level, $job->org_value );

			if ( is_wp_error( $result ) ) {
				$wpdb->update(
					$table,
					array(
						'status'        => 'failed',
						'error_message' => $result->get_error_message(),
					),
					array( 'cache_id' => $job->cache_id )
				);
			}
			// On success, generate() updates the row itself via cache_result().
		}

		// If more pending org jobs remain, reschedule.
		$remaining = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"
		);

		if ( $remaining > 0 && ! wp_next_scheduled( 'osq_process_org_ai_jobs' ) ) {
			wp_schedule_single_event( time() + 30, 'osq_process_org_ai_jobs' );
		}
	}
}
