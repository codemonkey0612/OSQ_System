<?php
/**
 * Questionnaire AJAX and logic handler.
 *
 * @package OSQ
 */

namespace OSQ\Questionnaire;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class QuestionnaireHandler
 *
 * Handles save progress, submit, and resume for the 57-item questionnaire.
 */
class QuestionnaireHandler {

	/**
	 * Initialize AJAX hooks.
	 *
	 * @return void
	 */
	public function init() {
		// Logged-in user hooks.
		add_action( 'wp_ajax_osq_save_progress', array( $this, 'save_progress' ) );
		add_action( 'wp_ajax_osq_submit_questionnaire', array( $this, 'submit_questionnaire' ) );
		add_action( 'wp_ajax_osq_get_progress', array( $this, 'get_progress' ) );

		// Also register nopriv hooks — the virtual page cookie may not always be
		// sent as a logged-in session. The handlers verify the nonce and user themselves.
		add_action( 'wp_ajax_nopriv_osq_save_progress', array( $this, 'save_progress' ) );
		add_action( 'wp_ajax_nopriv_osq_submit_questionnaire', array( $this, 'submit_questionnaire' ) );
		add_action( 'wp_ajax_nopriv_osq_get_progress', array( $this, 'get_progress' ) );
	}

	/**
	 * AJAX: Save partial answers (autosave / manual save).
	 *
	 * @return void
	 */
	public function save_progress() {
		try {
			$uid   = absint( $_POST['uid'] ?? 0 );
			$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );

			if ( ! $uid || ! $token ) {
				wp_send_json_error( array( 'message' => 'Missing authentication parameters.' ) );
			}

			$stored_token = get_transient( 'osq_ajax_token_' . $uid );

			if ( ! $stored_token || ! hash_equals( $stored_token, $token ) ) {
				wp_send_json_error( array( 'message' => 'Session expired or invalid token. Please refresh the page.' ) );
			}

			wp_set_current_user( $uid );

			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				wp_send_json_error( array( 'message' => __( 'Not authenticated.', 'osq-stress-check' ) ) );
			}

			$answers = $this->sanitize_answers( $_POST['answers'] ?? array() );

			if ( empty( $answers ) ) {
				wp_send_json_error( array( 'message' => __( 'No answers provided.', 'osq-stress-check' ) ) );
			}

			$db       = \OSQ\Plugin::get_instance()->db();
			$employee = $db->get_or_create_employee_for_user( $user_id );

			if ( ! $employee ) {
				wp_send_json_error( array( 'message' => __( 'Employee record not found.', 'osq-stress-check' ) ) );
			}

			// Merge incoming answers with existing so progress isn't overwritten.
			$existing_response = $db->get_response_by_employee( $employee->employee_id );
			if ( $existing_response && ! empty( $existing_response->response_data ) ) {
				$existing_answers = $db->decrypt_data( $existing_response->response_data );
				if ( is_array( $existing_answers ) ) {
					// Use + union operator because array_merge re-indexes numeric string keys like "1", "2".
					$answers = $answers + $existing_answers;
				}
			}

			$response_id = $db->save_response( $employee->employee_id, $answers, false );

			if ( false === $response_id ) {
				wp_send_json_error( array( 'message' => __( 'Failed to save progress.', 'osq-stress-check' ) ) );
			}

			wp_send_json_success( array(
				'message'     => __( 'Progress saved.', 'osq-stress-check' ),
				'response_id' => $response_id,
				'answered'    => count( $answers ),
			) );

		} catch ( \Throwable $e ) {
			error_log( 'OSQ Save Error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => 'Server Error: ' . $e->getMessage() ), 500 );
		}
	}

	/**
	 * AJAX: Submit completed questionnaire.
	 *
	 * @return void
	 */
	public function submit_questionnaire() {
		try {
			$uid   = absint( $_POST['uid'] ?? 0 );
			$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );

			if ( ! $uid || ! $token ) {
				wp_send_json_error( array( 'message' => 'Missing authentication parameters.' ) );
			}

			$stored_token = get_transient( 'osq_ajax_token_' . $uid );

			if ( ! $stored_token || ! hash_equals( $stored_token, $token ) ) {
				wp_send_json_error( array( 'message' => 'Session expired or invalid token. Please refresh the page.' ) );
			}

			wp_set_current_user( $uid );

			$user_id = get_current_user_id();
			if ( ! $user_id ) {
				wp_send_json_error( array( 'message' => __( 'Not authenticated.', 'osq-stress-check' ) ) );
			}

			$answers = $this->sanitize_answers( $_POST['answers'] ?? array() );

			$db       = \OSQ\Plugin::get_instance()->db();
			$employee = $db->get_or_create_employee_for_user( $user_id );

			if ( ! $employee ) {
				wp_send_json_error( array( 'message' => __( 'Employee record not found.', 'osq-stress-check' ) ) );
			}

			// Merge incoming answers with existing so we have the full set for validation.
			$existing_response = $db->get_response_by_employee( $employee->employee_id );
			if ( $existing_response && ! empty( $existing_response->response_data ) ) {
				$existing_answers = $db->decrypt_data( $existing_response->response_data );
				if ( is_array( $existing_answers ) ) {
					// Use + union operator because array_merge re-indexes numeric string keys like "1", "2".
					$answers = $answers + $existing_answers;
				}
			}

			// Validate completeness — all 57 scored items required.
			// THIS MUST HAPPEN AFTER MERGING WITH DB DATA.
			$missing = $this->validate_completeness( $answers );
			if ( ! empty( $missing ) ) {
				wp_send_json_error( array(
					'message' => sprintf(
						/* translators: %d: number of missing answers. */
						__( 'Please answer all questions. %d items remaining.', 'osq-stress-check' ),
						count( $missing )
					),
					'missing' => $missing,
				) );
			}

			// Calculate scoring results.
			$calculator = new \OSQ\Scoring\ScoreCalculator();
			$results    = $calculator->calculate( $answers );

			$response_id = $db->save_response( $employee->employee_id, $answers, true, $results );

			if ( false === $response_id ) {
				wp_send_json_error( array( 'message' => __( 'Failed to submit questionnaire.', 'osq-stress-check' ) ) );
			}

			wp_send_json_success( array(
				'message'     => __( 'Questionnaire submitted successfully.', 'osq-stress-check' ),
				'response_id' => $response_id,
			) );

		} catch ( \Throwable $e ) {
			error_log( 'OSQ Submit Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( array( 'message' => 'Server Error: ' . $e->getMessage() ), 500 );
		}
	}

	/**
	 * AJAX: Get current progress for resume.
	 *
	 * @return void
	 */
	public function get_progress() {
		try {
			$uid   = absint( $_POST['uid'] ?? 0 );
			$token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );

			if ( ! $uid || ! $token ) {
				wp_send_json_error( array( 'message' => 'Missing authentication parameters.' ) );
			}

			$stored_token = get_transient( 'osq_ajax_token_' . $uid );

			if ( ! $stored_token || ! hash_equals( $stored_token, $token ) ) {
				wp_send_json_error( array( 'message' => 'Session expired or invalid token. Please refresh the page.' ) );
			}

			wp_set_current_user( $uid );

			$user_id = get_current_user_id();

			$db       = \OSQ\Plugin::get_instance()->db();
			$employee = $user_id ? $db->get_or_create_employee_for_user( $user_id ) : null;

			if ( ! $employee ) {
				wp_send_json_success( array( 'answers' => array(), 'answered' => 0 ) );
				return;
			}

			$response = $db->get_response_by_employee( $employee->employee_id );

			if ( ! $response || $response->is_complete ) {
				wp_send_json_success( array( 'answers' => array(), 'answered' => 0 ) );
				return;
			}

			$answers = $db->decrypt_data( $response->response_data );

			if ( ! is_array( $answers ) ) {
				$answers = array();
			}

			wp_send_json_success( array(
				'answers'  => $answers,
				'answered' => count( $answers ),
			) );

		} catch ( \Throwable $e ) {
			error_log( 'OSQ Get Progress Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			wp_send_json_error( array( 'message' => 'Server Error: ' . $e->getMessage() ), 500 );
		}
	}

	/**
	 * Sanitize the answers array.
	 *
	 * @param array $raw Raw POST data.
	 * @return array Sanitized key => value pairs.
	 */
	private function sanitize_answers( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$clean = array();
		foreach ( $raw as $key => $value ) {
			$sanitized_key   = sanitize_text_field( $key );
			$sanitized_value = absint( $value );

			// Validate value is 1-4 (Likert scale).
			if ( $sanitized_value >= 1 && $sanitized_value <= 4 ) {
				$clean[ $sanitized_key ] = $sanitized_value;
			}
		}

		return $clean;
	}

	/**
	 * Validate all required items are answered.
	 *
	 * @param array $answers
	 * @return array List of missing item IDs.
	 */
	private function validate_completeness( $answers ) {
		$questions = QuestionDefinitions::get_questions();
		$missing   = array();

		foreach ( $questions as $q ) {
			if ( ! isset( $answers[ $q['id'] ] ) ) {
				$missing[] = $q['id'];
			}
		}

		return $missing;
	}
}
