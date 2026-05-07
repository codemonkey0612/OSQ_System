<?php
/**
 * Method 2: Raw Score Conversion Table calculator.
 *
 * @package OSQ
 */

namespace OSQ\Scoring;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Method2Calculator
 *
 * Implements the Raw Score Conversion Table Method for high-stress determination.
 * Lower evaluation points = higher stress.
 */
class Method2Calculator {

	/**
	 * High-stress thresholds (NOTE: lower = worse).
	 */
	const CRITERION_A_THRESHOLD_B  = 12; // Section B eval total ≤ 12.
	const CRITERION_B_THRESHOLD_AC = 26; // Section A+C eval total ≤ 26.
	const CRITERION_B_THRESHOLD_B  = 17; // Section B eval total ≤ 17.

	/**
	 * Calculate Method 2 results.
	 *
	 * @param array $answers Reverse-scored answers keyed by item ID.
	 * @return array
	 */
	public function calculate( $answers ) {
		$scale_defs = ConversionTables::get_scale_definitions();

		// Step 1: Calculate raw scale scores.
		$raw_scores = array();
		foreach ( $scale_defs as $scale_name => $def ) {
			$raw_scores[ $scale_name ] = $this->calculate_raw_score( $answers, $def['items'] );
		}

		// Step 2: Convert raw scores → evaluation points (1-5).
		$eval_points = array();
		foreach ( $raw_scores as $scale_name => $raw ) {
			$eval_points[ $scale_name ] = ConversionTables::convert( $scale_name, $raw );
		}

		// Step 3: Sum evaluation points per section.
		$section_a_eval = $this->sum_eval_by_section( $eval_points, $scale_defs, 'A' );
		$section_b_eval = $this->sum_eval_by_section( $eval_points, $scale_defs, 'B' );
		$section_c_eval = $this->sum_eval_by_section( $eval_points, $scale_defs, 'C' );

		$is_high_stress = $this->determine_high_stress(
			$section_a_eval,
			$section_b_eval,
			$section_c_eval
		);

		return array(
			'method'          => 2,
			'raw_scores'      => $raw_scores,
			'eval_points'     => $eval_points,
			'section_a_eval'  => $section_a_eval,
			'section_b_eval'  => $section_b_eval,
			'section_c_eval'  => $section_c_eval,
			'is_high_stress'  => $is_high_stress,
			'criterion_a_met' => $section_b_eval <= self::CRITERION_A_THRESHOLD_B,
			'criterion_b_met' => ( $section_a_eval + $section_c_eval ) <= self::CRITERION_B_THRESHOLD_AC
			                     && $section_b_eval <= self::CRITERION_B_THRESHOLD_B,
		);
	}

	/**
	 * Calculate raw score for a scale by summing its items.
	 *
	 * @param array $answers
	 * @param array $items
	 * @return int
	 */
	private function calculate_raw_score( $answers, $items ) {
		$total = 0;
		foreach ( $items as $item_id ) {
			if ( isset( $answers[ $item_id ] ) ) {
				$total += (int) $answers[ $item_id ];
			}
		}
		return $total;
	}

	/**
	 * Sum evaluation points for all scales belonging to a section.
	 *
	 * @param array  $eval_points
	 * @param array  $scale_defs
	 * @param string $section
	 * @return int
	 */
	private function sum_eval_by_section( $eval_points, $scale_defs, $section ) {
		$total = 0;
		foreach ( $scale_defs as $scale_name => $def ) {
			if ( $def['section'] === $section && isset( $eval_points[ $scale_name ] ) ) {
				$total += $eval_points[ $scale_name ];
			}
		}
		return $total;
	}

	/**
	 * Determine high-stress by Method 2 criteria.
	 *
	 * NOTE: LOWER evaluation = HIGHER stress (inverted from Method 1).
	 *
	 * @param int $a_eval Section A evaluation total.
	 * @param int $b_eval Section B evaluation total.
	 * @param int $c_eval Section C evaluation total.
	 * @return bool
	 */
	private function determine_high_stress( $a_eval, $b_eval, $c_eval ) {
		// Criterion A: Section B eval total ≤ 12.
		if ( $b_eval <= self::CRITERION_A_THRESHOLD_B ) {
			return true;
		}

		// Criterion B: (A+C eval total ≤ 26) AND (B eval total ≤ 17).
		if ( ( $a_eval + $c_eval ) <= self::CRITERION_B_THRESHOLD_AC && $b_eval <= self::CRITERION_B_THRESHOLD_B ) {
			return true;
		}

		return false;
	}
}
