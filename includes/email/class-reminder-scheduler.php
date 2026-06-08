<?php
/**
 * Reminder scheduler — automatic non-respondent reminder emails (Phase 5).
 *
 * Runs daily via WP-Cron. For each active survey campaign it sends up to three
 * reminders to employees who have not yet completed the stress check:
 *   - reminder 1: 3 days after the campaign start
 *   - reminder 2: 7 days after the campaign start
 *   - final     : 3 days before the deadline
 * Each reminder fires at most once per campaign (tracked on the campaign row).
 *
 * @package OSQ
 */

namespace OSQ\Email;

use OSQ\Database\Schema;
use OSQ\Database\DbManager;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ReminderScheduler
 */
class ReminderScheduler {

	const CRON_HOOK = 'osq_send_survey_reminders';

	/**
	 * Register the daily cron event + handler.
	 *
	 * @return void
	 */
	public function init() {
		add_action( self::CRON_HOOK, array( $this, 'run' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// First run ~10 minutes out, then daily.
			wp_schedule_event( time() + 600, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Clear the scheduled event (called on deactivation).
	 *
	 * @return void
	 */
	public static function unschedule() {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
		}
	}

	/**
	 * Process all active campaigns and send any due reminders.
	 *
	 * @return void
	 */
	public function run() {
		global $wpdb;
		$campaigns = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}" . Schema::SURVEY_CAMPAIGNS . " WHERE is_active = 1"
		);

		if ( empty( $campaigns ) ) {
			return;
		}

		$now = current_time( 'timestamp' );

		foreach ( $campaigns as $c ) {
			if ( empty( $c->start_date ) ) {
				continue;
			}
			$start    = strtotime( $c->start_date );
			$deadline = $c->deadline ? strtotime( $c->deadline ) : null;

			$due = null; // which reminder slot is due

			if ( empty( $c->reminder1_sent_at ) && $now >= $start + 3 * DAY_IN_SECONDS ) {
				$due = 'reminder1_sent_at';
			} elseif ( empty( $c->reminder2_sent_at ) && $now >= $start + 7 * DAY_IN_SECONDS ) {
				$due = 'reminder2_sent_at';
			} elseif ( $deadline && empty( $c->reminder_final_sent_at ) && $now >= $deadline - 3 * DAY_IN_SECONDS ) {
				$due = 'reminder_final_sent_at';
			}

			if ( ! $due ) {
				continue;
			}

			$this->send_reminders_for_company( (int) $c->company_id, $c );

			// Mark this reminder slot as sent (even if 0 recipients, to avoid re-checking daily).
			$wpdb->update(
				$wpdb->prefix . Schema::SURVEY_CAMPAIGNS,
				array( $due => current_time( 'mysql' ) ),
				array( 'campaign_id' => $c->campaign_id )
			);
		}
	}

	/**
	 * Send the reminder template to all non-respondents of a company.
	 *
	 * @param int    $company_id
	 * @param object $campaign
	 * @return int Number of emails sent.
	 */
	public function send_reminders_for_company( $company_id, $campaign ) {
		$recipients = self::get_non_respondents( $company_id );
		if ( empty( $recipients ) ) {
			return 0;
		}

		$mailer  = new EmailService();
		$vars    = MailVars::company_base( $company_id );
		$deadline = $campaign->deadline ? date_i18n( 'Y年m月d日', strtotime( $campaign->deadline ) ) : '—';
		$sent    = 0;

		foreach ( $recipients as $emp ) {
			if ( empty( $emp->email ) || ! is_email( $emp->email ) ) {
				continue;
			}
			$emp_vars = array_merge( $vars, array(
				'氏名'    => $emp->name,
				'受検URL' => MailVars::survey_url(),
				'締切日'  => $deadline,
			) );
			$ok = $mailer->send_template(
				EmailTemplateManager::SURVEY_REMINDER,
				$emp->email,
				$emp_vars,
				$company_id,
				(int) $emp->employee_id
			);
			if ( $ok ) {
				$sent++;
			}
		}
		return $sent;
	}

	/**
	 * SQL fragment that excludes management/officer/physician accounts so that
	 * survey invitations and reminders only target actual test-takers. Admin
	 * accounts have an employee record + email but cannot take the test.
	 *
	 * @return string A NOT EXISTS clause (no leading AND).
	 */
	public static function taker_only_clause() {
		global $wpdb;
		$cap_key = $wpdb->prefix . 'capabilities';
		return "NOT EXISTS (
			SELECT 1 FROM {$wpdb->usermeta} um
			WHERE um.user_id = e.wp_user_id
			  AND um.meta_key = '{$cap_key}'
			  AND ( um.meta_value LIKE '%osq_general_administrator%'
			     OR um.meta_value LIKE '%osq_implementation_officer%'
			     OR um.meta_value LIKE '%osq_industrial_physician%'
			     OR um.meta_value LIKE '%osq_wellanc_admin%'
			     OR um.meta_value LIKE '%administrator%' )
		)";
	}

	/**
	 * All survey-taker employees of a company who have an email address.
	 *
	 * @param int $company_id
	 * @return array of rows { employee_id, name, email }
	 */
	public static function get_survey_recipients( $company_id ) {
		global $wpdb;
		$emp = $wpdb->prefix . Schema::EMPLOYEES;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT e.employee_id, e.name, e.email
			 FROM {$emp} e
			 WHERE e.company_id = %d
			   AND e.email <> ''
			   AND " . self::taker_only_clause(),
			(int) $company_id
		) );
	}

	/**
	 * Get survey-taker employees who have NOT completed the stress check.
	 *
	 * @param int $company_id
	 * @return array of rows { employee_id, name, email }
	 */
	public static function get_non_respondents( $company_id ) {
		global $wpdb;
		$emp = $wpdb->prefix . Schema::EMPLOYEES;
		$res = $wpdb->prefix . Schema::RESPONSES;

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT e.employee_id, e.name, e.email
			 FROM {$emp} e
			 LEFT JOIN {$res} r
			   ON r.employee_id = e.employee_id AND r.is_complete = 1
			 WHERE e.company_id = %d
			   AND e.email <> ''
			   AND r.response_id IS NULL
			   AND " . self::taker_only_clause(),
			(int) $company_id
		) );
	}
}
