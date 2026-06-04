<?php
/**
 * Group analysis aggregation engine.
 *
 * @package OSQ
 */

namespace OSQ\Analysis;

use OSQ\Database\DbManager;
use OSQ\Database\Schema;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GroupAnalyzer
 *
 * Aggregates stress check results by organizational group with
 * legally mandated 10-person minimum rule enforcement.
 */
class GroupAnalyzer {

	/**
	 * Minimum respondents required for group analysis (legal requirement).
	 */
	const MIN_GROUP_SIZE = 10;

	/**
	 * Analyze a group and return aggregated statistics.
	 *
	 * Returns null if the group has fewer than MIN_GROUP_SIZE respondents.
	 *
	 * @param array $filter {
	 *     Optional. Group filter parameters.
	 *     @type string $organization_1 Organization level 1.
	 *     @type string $organization_2 Organization level 2.
	 *     @type string $organization_3 Organization level 3.
	 *     @type string $department      Custom department grouping.
	 * }
	 * @return array|null Analysis results or null if group too small.
	 */
	public function analyze( $filter = array() ) {
		global $wpdb;

		$employees_table = $wpdb->prefix . Schema::EMPLOYEES;
		$responses_table = $wpdb->prefix . Schema::RESPONSES;

		// Build WHERE clause from filter.
		$where  = array( 'r.is_complete = 1' );
		$values = array();

		// Tenant scope: restrict to the current company unless cross-tenant mode is on.
		if ( ! DbManager::is_cross_tenant_mode() ) {
			$where[]  = 'e.company_id = %d';
			$values[] = DbManager::current_company_id();
		}

		if ( ! empty( $filter['organization_1'] ) ) {
			$where[]  = 'e.organization_1 = %s';
			$values[] = $filter['organization_1'];
		}
		if ( ! empty( $filter['organization_2'] ) ) {
			$where[]  = 'e.organization_2 = %s';
			$values[] = $filter['organization_2'];
		}
		if ( ! empty( $filter['organization_3'] ) ) {
			$where[]  = 'e.organization_3 = %s';
			$values[] = $filter['organization_3'];
		}
		if ( ! empty( $filter['organization_4'] ) ) {
			$where[]  = 'e.organization_4 = %s';
			$values[] = $filter['organization_4'];
		}
		if ( ! empty( $filter['organization_5'] ) ) {
			$where[]  = 'e.organization_5 = %s';
			$values[] = $filter['organization_5'];
		}

		// Exclude specific org values from the axis column.
		if ( ! empty( $filter['exclude_orgs'] ) && ! empty( $filter['axis'] ) ) {
			$axis        = $filter['axis'];
			$allowed_axes = array( 'organization_1', 'organization_2', 'organization_3', 'organization_4', 'organization_5' );
			if ( in_array( $axis, $allowed_axes, true ) ) {
				$exclude = array_filter( array_map( 'strval', (array) $filter['exclude_orgs'] ) );
				if ( ! empty( $exclude ) ) {
					$placeholders = implode( ', ', array_fill( 0, count( $exclude ), '%s' ) );
					$where[]      = "e.{$axis} NOT IN ({$placeholders})";
					$values       = array_merge( $values, array_values( $exclude ) );
				}
			}
		}

		$where_sql = implode( ' AND ', $where );

		// Count distinct respondents — enforce per-tenant minimum (legal fallback: 10).
		$count_sql = "SELECT COUNT(DISTINCT e.employee_id) FROM {$responses_table} r
			INNER JOIN {$employees_table} e ON r.employee_id = e.employee_id
			WHERE {$where_sql}";

		if ( ! empty( $values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $values );
		}

		$respondent_count = (int) $wpdb->get_var( $count_sql );

		if ( $respondent_count < $this->get_min_group_size( $filter ) ) {
			return null;
		}

		// Fetch all completed responses for this group.
		$data_sql = "SELECT r.response_data, r.method1_result, r.method2_result
			FROM {$responses_table} r
			INNER JOIN {$employees_table} e ON r.employee_id = e.employee_id
			WHERE {$where_sql}";

		if ( ! empty( $values ) ) {
			$data_sql = $wpdb->prepare( $data_sql, $values );
		}

		$rows = $wpdb->get_results( $data_sql );

		// Calculate statistics.
		return $this->compute_statistics( $rows, $respondent_count, $filter );
	}

	/**
	 * Get response completion rate for a group.
	 *
	 * @param array $filter Group filter.
	 * @return float Completion rate (0.0 - 1.0).
	 */
	public function get_completion_rate( $filter = array() ) {
		global $wpdb;

		$employees_table = $wpdb->prefix . Schema::EMPLOYEES;
		$responses_table = $wpdb->prefix . Schema::RESPONSES;

		// Total employees in group.
		$where  = array( '1=1' );
		$values = array();

		// Tenant scope: restrict to the current company unless cross-tenant mode is on.
		if ( ! DbManager::is_cross_tenant_mode() ) {
			$where[]  = 'e.company_id = %d';
			$values[] = DbManager::current_company_id();
		}

		if ( ! empty( $filter['organization_1'] ) ) {
			$where[]  = 'e.organization_1 = %s';
			$values[] = $filter['organization_1'];
		}
		if ( ! empty( $filter['organization_2'] ) ) {
			$where[]  = 'e.organization_2 = %s';
			$values[] = $filter['organization_2'];
		}
		if ( ! empty( $filter['organization_3'] ) ) {
			$where[]  = 'e.organization_3 = %s';
			$values[] = $filter['organization_3'];
		}
		if ( ! empty( $filter['organization_4'] ) ) {
			$where[]  = 'e.organization_4 = %s';
			$values[] = $filter['organization_4'];
		}
		if ( ! empty( $filter['organization_5'] ) ) {
			$where[]  = 'e.organization_5 = %s';
			$values[] = $filter['organization_5'];
		}

		$where_sql = implode( ' AND ', $where );

		$total_sql = "SELECT COUNT(*) FROM {$employees_table} e WHERE {$where_sql}";
		if ( ! empty( $values ) ) {
			$total_sql = $wpdb->prepare( $total_sql, $values );
		}
		$total = (int) $wpdb->get_var( $total_sql );

		if ( 0 === $total ) {
			return 0.0;
		}

		$completed_sql = "SELECT COUNT(DISTINCT r.employee_id)
			FROM {$responses_table} r
			INNER JOIN {$employees_table} e ON r.employee_id = e.employee_id
			WHERE r.is_complete = 1 AND {$where_sql}";
		if ( ! empty( $values ) ) {
			$completed_sql = $wpdb->prepare( $completed_sql, $values );
		}
		$completed = (int) $wpdb->get_var( $completed_sql );

		return round( $completed / $total, 4 );
	}

	/**
	 * Resolve the effective minimum group size.
	 * Priority: filter override → per-tenant DB value → legal fallback constant.
	 *
	 * @param array $filter
	 * @return int
	 */
	private function get_min_group_size( $filter ) {
		if ( isset( $filter['min_group_size'] ) && (int) $filter['min_group_size'] >= 1 ) {
			return (int) $filter['min_group_size'];
		}
		global $wpdb;
		$val = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT min_group_size FROM {$wpdb->prefix}osq_companies WHERE company_id = %d",
			DbManager::current_company_id()
		) );
		return $val >= 1 ? $val : self::MIN_GROUP_SIZE;
	}

	/**
	 * Compute statistics from response rows.
	 *
	 * @param array  $rows
	 * @param int    $respondent_count
	 * @param array  $filter
	 * @return array
	 */
	private function compute_statistics( $rows, $respondent_count, $filter ) {
		$high_stress_count = 0;
		$method2_scales    = array();

		$db = \OSQ\Plugin::get_instance()->db();

		foreach ( $rows as $row ) {
			// Decode Method 1/2 results — stored via maybe_serialize().
			$m1 = maybe_unserialize( $row->method1_result );
			$m2 = maybe_unserialize( $row->method2_result );

			// Fallback: try JSON if maybe_unserialize returned a string.
			if ( is_string( $m1 ) ) {
				$m1 = json_decode( $m1, true );
			}
			if ( is_string( $m2 ) ) {
				$m2 = json_decode( $m2, true );
			}

			$is_high = false;
			if ( is_array( $m1 ) && ! empty( $m1['is_high_stress'] ) ) {
				$is_high = true;
			}
			if ( is_array( $m2 ) && ! empty( $m2['is_high_stress'] ) ) {
				$is_high = true;
			}
			if ( $is_high ) {
				$high_stress_count++;
			}

			// Collect Method 2 evaluation points for scale averages.
			if ( is_array( $m2 ) && isset( $m2['eval_points'] ) ) {
				foreach ( $m2['eval_points'] as $scale => $point ) {
					if ( ! isset( $method2_scales[ $scale ] ) ) {
						$method2_scales[ $scale ] = array();
					}
					$method2_scales[ $scale ][] = (int) $point;
				}
			}
		}

		// Calculate scale averages.
		$scale_averages = array();
		foreach ( $method2_scales as $scale => $points ) {
			$scale_averages[ $scale ] = round( array_sum( $points ) / count( $points ), 2 );
		}

		$high_stress_ratio = round( ( $high_stress_count / $respondent_count ) * 100, 1 );

		return array(
			'filter'             => $filter,
			'respondent_count'   => $respondent_count,
			'high_stress_count'  => $high_stress_count,
			'high_stress_ratio'  => $high_stress_ratio,
			'scale_averages'     => $scale_averages,
			'completion_rate'    => $this->get_completion_rate( $filter ),
		);
	}
}
