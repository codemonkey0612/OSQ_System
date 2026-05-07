<?php
/**
 * Chart data generator for frontend visualization.
 *
 * @package OSQ
 */

namespace OSQ\Analysis;

use OSQ\Database\Schema;
use OSQ\Scoring\ConversionTables;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ChartGenerator
 *
 * Prepares Chart.js-compatible data structures for stress check visualization.
 */
class ChartGenerator {

	/**
	 * @var GroupAnalyzer
	 */
	private $analyzer;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->analyzer = new GroupAnalyzer();
	}

	/**
	 * Generate bar chart data: high-stress ratio by organization.
	 *
	 * @param string $org_level Organization level column (organization_1, organization_2, organization_3).
	 * @return array Chart.js-compatible data structure.
	 */
	public function get_bar_chart_data( $org_level = 'organization_1' ) {
		global $wpdb;

		$allowed = array( 'organization_1', 'organization_2', 'organization_3' );
		if ( ! in_array( $org_level, $allowed, true ) ) {
			$org_level = 'organization_1';
		}

		$employees_table = $wpdb->prefix . Schema::EMPLOYEES;

		// Get distinct organization values.
		$orgs = $wpdb->get_col(
			"SELECT DISTINCT {$org_level} FROM {$employees_table} WHERE {$org_level} != '' ORDER BY {$org_level}"
		);

		$labels = array();
		$data   = array();
		$colors = $this->generate_colors( count( $orgs ) );

		foreach ( $orgs as $index => $org ) {
			$filter  = array( $org_level => $org );
			$result  = $this->analyzer->analyze( $filter );

			if ( null === $result ) {
				continue; // Skip groups below 10-person threshold.
			}

			$labels[] = $org;
			$data[]   = $result['high_stress_ratio'];
		}

		return array(
			'type' => 'bar',
			'data' => array(
				'labels'   => $labels,
				'datasets' => array(
					array(
						'label'           => __( '高ストレス者割合 (%) / High-Stress Ratio (%)', 'osq-stress-check' ),
						'data'            => $data,
						'backgroundColor' => $colors,
						'borderWidth'     => 1,
					),
				),
			),
			'options' => array(
				'responsive' => true,
				'scales'     => array(
					'y' => array(
						'beginAtZero' => true,
						'max'         => 100,
						'title'       => array(
							'display' => true,
							'text'    => __( '割合 (%) / Ratio (%)', 'osq-stress-check' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Generate radar chart data: scale distribution for a group.
	 *
	 * @param array $filter Group filter.
	 * @return array|null Chart.js-compatible data or null if group too small.
	 */
	public function get_radar_chart_data( $filter = array() ) {
		$result = $this->analyzer->analyze( $filter );

		if ( null === $result || empty( $result['scale_averages'] ) ) {
			return null;
		}

		$scale_labels = $this->get_scale_labels();
		$labels = array();
		$data   = array();

		foreach ( $result['scale_averages'] as $scale => $avg ) {
			$labels[] = $scale_labels[ $scale ] ?? $scale;
			$data[]   = $avg;
		}

		return array(
			'type' => 'radar',
			'data' => array(
				'labels'   => $labels,
				'datasets' => array(
					array(
						'label'           => __( '尺度平均 / Scale Averages', 'osq-stress-check' ),
						'data'            => $data,
						'fill'            => true,
						'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
						'borderColor'     => 'rgb(54, 162, 235)',
						'pointBackgroundColor' => 'rgb(54, 162, 235)',
					),
				),
			),
			'options' => array(
				'responsive' => true,
				'scales'     => array(
					'r' => array(
						'beginAtZero' => true,
						'max'         => 5,
					),
				),
			),
		);
	}

	/**
	 * Get bilingual scale labels.
	 *
	 * @return array Keyed by scale name.
	 */
	private function get_scale_labels() {
		return array(
			'quantitative_demands' => __( '仕事の量 / Quantitative Demands', 'osq-stress-check' ),
			'qualitative_demands'  => __( '仕事の質 / Qualitative Demands', 'osq-stress-check' ),
			'physical_workload'    => __( '身体的負担 / Physical Workload', 'osq-stress-check' ),
			'interpersonal_stress' => __( '対人関係 / Interpersonal', 'osq-stress-check' ),
			'environment_stress'   => __( '職場環境 / Environment', 'osq-stress-check' ),
			'job_control'          => __( '仕事の裁量 / Job Control', 'osq-stress-check' ),
			'skill_utilization'    => __( '技能の活用 / Skill Use', 'osq-stress-check' ),
			'job_fit'              => __( '仕事の適性 / Job Fit', 'osq-stress-check' ),
			'reward'               => __( '働きがい / Reward', 'osq-stress-check' ),
			'vigor'                => __( '活気 / Vigor', 'osq-stress-check' ),
			'irritability'         => __( 'イライラ感 / Irritability', 'osq-stress-check' ),
			'fatigue'              => __( '疲労感 / Fatigue', 'osq-stress-check' ),
			'anxiety'              => __( '不安感 / Anxiety', 'osq-stress-check' ),
			'depression'           => __( '抑うつ感 / Depression', 'osq-stress-check' ),
			'physical_complaints'  => __( '身体愁訴 / Physical Complaints', 'osq-stress-check' ),
			'supervisor_support'   => __( '上司支援 / Supervisor Support', 'osq-stress-check' ),
			'colleague_support'    => __( '同僚支援 / Colleague Support', 'osq-stress-check' ),
			'family_support'       => __( '家族支援 / Family Support', 'osq-stress-check' ),
		);
	}

	/**
	 * Generate an array of distinct colors for chart bars.
	 *
	 * @param int $count
	 * @return array
	 */
	private function generate_colors( $count ) {
		$palette = array(
			'rgba(255, 99, 132, 0.7)',
			'rgba(54, 162, 235, 0.7)',
			'rgba(255, 206, 86, 0.7)',
			'rgba(75, 192, 192, 0.7)',
			'rgba(153, 102, 255, 0.7)',
			'rgba(255, 159, 64, 0.7)',
			'rgba(199, 199, 199, 0.7)',
			'rgba(83, 102, 255, 0.7)',
			'rgba(255, 99, 255, 0.7)',
			'rgba(99, 255, 132, 0.7)',
		);

		$colors = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$colors[] = $palette[ $i % count( $palette ) ];
		}

		return $colors;
	}
}
