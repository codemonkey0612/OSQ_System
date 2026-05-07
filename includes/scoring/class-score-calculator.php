<?php
/**
 * Master scoring orchestrator.
 *
 * @package OSQ
 */

namespace OSQ\Scoring;

use OSQ\Questionnaire\QuestionDefinitions;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ScoreCalculator
 *
 * Runs both scoring methods, applies reverse scoring, and aggregates results.
 */
class ScoreCalculator {

	/**
	 * Calculate stress scores using both methods.
	 *
	 * @param array $raw_answers Raw answers keyed by item ID (values 1-4).
	 * @return array Combined results with high-stress determination.
	 */
	public function calculate( $raw_answers ) {
		// Step 1: Apply reverse scoring.
		$answers = $this->apply_reverse_scoring( $raw_answers );

		// Step 2: Run both methods.
		$method1 = ( new Method1Calculator() )->calculate( $answers );
		$method2 = ( new Method2Calculator() )->calculate( $answers );

		// Step 3: Aggregate — either method flagging = high-stress.
		$is_high_stress = $method1['is_high_stress'] || $method2['is_high_stress'];

		return array(
			'method1_result'         => $method1,
			'method2_result'         => $method2,
			'is_high_stress_method1' => $method1['is_high_stress'],
			'is_high_stress_method2' => $method2['is_high_stress'],
			'is_high_stress'         => $is_high_stress,
		);
	}

	/**
	 * Apply reverse scoring to designated items.
	 *
	 * Reverses: 1→4, 2→3, 3→2, 4→1.
	 *
	 * @param array $answers Raw answers.
	 * @return array Answers with reverse items converted.
	 */
	public function apply_reverse_scoring( $answers ) {
		$reverse_map = QuestionDefinitions::get_reverse_items();
		$result      = $answers;

		foreach ( $reverse_map as $section => $numbers ) {
			foreach ( $numbers as $num ) {
				$key = $section . '-' . $num;
				if ( isset( $result[ $key ] ) ) {
					$result[ $key ] = 5 - (int) $result[ $key ]; // 1↔4, 2↔3.
				}
			}
		}

		return $result;
	}
}
