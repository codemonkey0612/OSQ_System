<?php
/**
 * Method 1: Total Score calculator.
 *
 * @package OSQ
 */

namespace OSQ\Scoring;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Method1Calculator
 *
 * Implements the Total Score Method for high-stress determination.
 */
class Method1Calculator {

	/**
	 * High-stress thresholds.
	 */
	const CRITERION_A_THRESHOLD_B = 77;  // Section B ≥ 77.
	const CRITERION_B_THRESHOLD_AC = 76; // Section A + C ≥ 76.
	const CRITERION_B_THRESHOLD_B  = 63; // Section B ≥ 63.

	/**
	 * Calculate Method 1 results.
	 *
	 * @param array $answers Reverse-scored answers keyed by item ID.
	 * @return array
	 */
	public function calculate( $answers ) {
		$section_a_total = $this->sum_section( $answers, 'A', 17 );
		$section_b_total = $this->sum_section( $answers, 'B', 29 );
		$section_c_total = $this->sum_section( $answers, 'C', 9 );

		$is_high_stress = $this->determine_high_stress(
			$section_a_total,
			$section_b_total,
			$section_c_total
		);

		return array(
			'method'          => 1,
			'section_a_total' => $section_a_total,
			'section_b_total' => $section_b_total,
			'section_c_total' => $section_c_total,
			'is_high_stress'  => $is_high_stress,
			'criterion_a_met' => $section_b_total >= self::CRITERION_A_THRESHOLD_B,
			'criterion_b_met' => ( $section_a_total + $section_c_total ) >= self::CRITERION_B_THRESHOLD_AC
			                     && $section_b_total >= self::CRITERION_B_THRESHOLD_B,
		);
	}

	/**
	 * Sum scores for a section.
	 *
	 * @param array  $answers
	 * @param string $section
	 * @param int    $count
	 * @return int
	 */
	private function sum_section( $answers, $section, $count ) {
		$total = 0;
		for ( $i = 1; $i <= $count; $i++ ) {
			$key = $section . '-' . $i;
			if ( isset( $answers[ $key ] ) ) {
				$total += (int) $answers[ $key ];
			}
		}
		return $total;
	}

	/**
	 * Determine if the person is high-stress by Method 1.
	 *
	 * @param int $a Section A total.
	 * @param int $b Section B total.
	 * @param int $c Section C total.
	 * @return bool
	 */
	private function determine_high_stress( $a, $b, $c ) {
		// Criterion A: Section B ≥ 77.
		if ( $b >= self::CRITERION_A_THRESHOLD_B ) {
			return true;
		}

		// Criterion B: (A + C ≥ 76) AND (B ≥ 63).
		if ( ( $a + $c ) >= self::CRITERION_B_THRESHOLD_AC && $b >= self::CRITERION_B_THRESHOLD_B ) {
			return true;
		}

		return false;
	}
}
