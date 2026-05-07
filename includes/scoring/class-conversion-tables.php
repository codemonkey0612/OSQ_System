<?php
/**
 * Raw score to evaluation point conversion tables for Method 2.
 *
 * @package OSQ
 */

namespace OSQ\Scoring;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConversionTables
 *
 * Static lookup tables mapping raw scale scores → evaluation points (1-5).
 * Each table entry: array( min_raw, max_raw, eval_point ).
 * Lower evaluation points = higher stress.
 */
class ConversionTables {

	/**
	 * Scale definitions: which items belong to each scale.
	 *
	 * @return array Keyed by scale name.
	 */
	public static function get_scale_definitions() {
		return array(
			// Section A scales (9)
			'quantitative_demands'  => array( 'items' => array( 'A-1', 'A-2', 'A-3' ), 'section' => 'A' ),
			'qualitative_demands'   => array( 'items' => array( 'A-4', 'A-5', 'A-6' ), 'section' => 'A' ),
			'physical_workload'     => array( 'items' => array( 'A-7' ), 'section' => 'A' ),
			'interpersonal_stress'  => array( 'items' => array( 'A-12', 'A-13', 'A-14' ), 'section' => 'A' ),
			'environment_stress'    => array( 'items' => array( 'A-15' ), 'section' => 'A' ),
			'job_control'           => array( 'items' => array( 'A-8', 'A-9', 'A-10' ), 'section' => 'A' ),
			'skill_utilization'     => array( 'items' => array( 'A-11' ), 'section' => 'A' ),
			'job_fit'               => array( 'items' => array( 'A-16' ), 'section' => 'A' ),
			'reward'                => array( 'items' => array( 'A-17' ), 'section' => 'A' ),

			// Section B scales (6)
			'vigor'                 => array( 'items' => array( 'B-1', 'B-2', 'B-3' ), 'section' => 'B' ),
			'irritability'          => array( 'items' => array( 'B-4', 'B-5', 'B-6' ), 'section' => 'B' ),
			'fatigue'               => array( 'items' => array( 'B-7', 'B-8', 'B-9' ), 'section' => 'B' ),
			'anxiety'               => array( 'items' => array( 'B-10', 'B-11', 'B-12' ), 'section' => 'B' ),
			'depression'            => array( 'items' => array( 'B-13', 'B-14', 'B-15', 'B-16', 'B-17', 'B-18' ), 'section' => 'B' ),
			'physical_complaints'   => array( 'items' => array( 'B-19', 'B-20', 'B-21', 'B-22', 'B-23', 'B-24', 'B-25', 'B-26', 'B-27', 'B-28', 'B-29' ), 'section' => 'B' ),

			// Section C scales (3)
			'supervisor_support'    => array( 'items' => array( 'C-1', 'C-2', 'C-3' ), 'section' => 'C' ),
			'colleague_support'     => array( 'items' => array( 'C-4', 'C-5', 'C-6' ), 'section' => 'C' ),
			'family_support'        => array( 'items' => array( 'C-7', 'C-8', 'C-9' ), 'section' => 'C' ),
		);
	}

	/**
	 * Convert a raw scale score to an evaluation point (1-5).
	 *
	 * @param string $scale_name Scale identifier.
	 * @param int    $raw_score  Raw score for this scale.
	 * @return int Evaluation point (1-5), or 0 if not found.
	 */
	public static function convert( $scale_name, $raw_score ) {
		$tables = self::get_tables();

		if ( ! isset( $tables[ $scale_name ] ) ) {
			return 0;
		}

		foreach ( $tables[ $scale_name ] as $range ) {
			if ( $raw_score >= $range[0] && $raw_score <= $range[1] ) {
				return $range[2];
			}
		}

		return 0;
	}

	/**
	 * Get all 18 conversion tables.
	 *
	 * Each entry: array( min_raw, max_raw, evaluation_point ).
	 * Evaluation 5 = lowest stress, 1 = highest stress.
	 *
	 * @return array Keyed by scale name.
	 */
	public static function get_tables() {
		return array(
			// --- Section A Scales (9) ---

			'quantitative_demands' => array(
				array( 3, 4, 5 ),
				array( 5, 6, 4 ),
				array( 7, 8, 3 ),
				array( 9, 10, 2 ),
				array( 11, 12, 1 ),
			),
			'qualitative_demands' => array(
				array( 3, 4, 5 ),
				array( 5, 6, 4 ),
				array( 7, 8, 3 ),
				array( 9, 10, 2 ),
				array( 11, 12, 1 ),
			),
			'physical_workload' => array(
				array( 1, 1, 5 ),
				array( 2, 2, 4 ),
				array( 3, 3, 3 ),
				array( 4, 4, 1 ),
			),
			'interpersonal_stress' => array(
				array( 3, 4, 5 ),
				array( 5, 6, 4 ),
				array( 7, 8, 3 ),
				array( 9, 10, 2 ),
				array( 11, 12, 1 ),
			),
			'environment_stress' => array(
				array( 1, 1, 5 ),
				array( 2, 2, 4 ),
				array( 3, 3, 3 ),
				array( 4, 4, 1 ),
			),
			'job_control' => array(
				array( 3, 4, 1 ),
				array( 5, 6, 2 ),
				array( 7, 8, 3 ),
				array( 9, 10, 4 ),
				array( 11, 12, 5 ),
			),
			'skill_utilization' => array(
				array( 1, 1, 1 ),
				array( 2, 2, 3 ),
				array( 3, 3, 4 ),
				array( 4, 4, 5 ),
			),
			'job_fit' => array(
				array( 1, 1, 1 ),
				array( 2, 2, 3 ),
				array( 3, 3, 4 ),
				array( 4, 4, 5 ),
			),
			'reward' => array(
				array( 1, 1, 1 ),
				array( 2, 2, 3 ),
				array( 3, 3, 4 ),
				array( 4, 4, 5 ),
			),

			// --- Section B Scales (6) ---

			'vigor' => array(
				array( 3, 4, 1 ),
				array( 5, 6, 2 ),
				array( 7, 8, 3 ),
				array( 9, 10, 4 ),
				array( 11, 12, 5 ),
			),
			'irritability' => array(
				array( 3, 4, 5 ),
				array( 5, 6, 4 ),
				array( 7, 8, 3 ),
				array( 9, 10, 2 ),
				array( 11, 12, 1 ),
			),
			'fatigue' => array(
				array( 3, 4, 5 ),
				array( 5, 6, 4 ),
				array( 7, 8, 3 ),
				array( 9, 10, 2 ),
				array( 11, 12, 1 ),
			),
			'anxiety' => array(
				array( 3, 4, 5 ),
				array( 5, 6, 4 ),
				array( 7, 8, 3 ),
				array( 9, 10, 2 ),
				array( 11, 12, 1 ),
			),
			'depression' => array(
				array( 6, 8, 5 ),
				array( 9, 11, 4 ),
				array( 12, 14, 3 ),
				array( 15, 18, 2 ),
				array( 19, 24, 1 ),
			),
			'physical_complaints' => array(
				array( 11, 15, 5 ),
				array( 16, 20, 4 ),
				array( 21, 25, 3 ),
				array( 26, 33, 2 ),
				array( 34, 44, 1 ),
			),

			// --- Section C Scales (3) ---

			'supervisor_support' => array(
				array( 3, 4, 1 ),
				array( 5, 6, 2 ),
				array( 7, 8, 3 ),
				array( 9, 10, 4 ),
				array( 11, 12, 5 ),
			),
			'colleague_support' => array(
				array( 3, 4, 1 ),
				array( 5, 6, 2 ),
				array( 7, 8, 3 ),
				array( 9, 10, 4 ),
				array( 11, 12, 5 ),
			),
			'family_support' => array(
				array( 3, 4, 1 ),
				array( 5, 6, 2 ),
				array( 7, 8, 3 ),
				array( 9, 10, 4 ),
				array( 11, 12, 5 ),
			),
		);
	}
}
